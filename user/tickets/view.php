<?php
// user/tickets/view.php
require_once __DIR__ . '/../includes/header.php';

$user_id = get_current_user_id();
if (!$user_id) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'برای مشاهده تیکت باید وارد شوید.'];
    header("Location: " . ($user_base_url ?? '/my_site/user') . "/auth/login.php");
    exit;
}

$ticket_id_to_view = null;
$ticket_data = null;
$ticket_replies = [];
$errors = []; // For form submission errors primarily

if (isset($_GET['ticket_id']) && is_numeric($_GET['ticket_id'])) {
    $ticket_id_to_view = (int)$_GET['ticket_id'];
    $csrf_token_ticket_reply = generate_csrf_token('ticket_reply_form_' . $ticket_id_to_view); // Generate token early

    $stmt_ticket = $conn->prepare("
        SELECT t.TicketID, t.Subject, t.Status, t.Priority, t.CreatedAt, t.UpdatedAt, t.CreatedByUserID,
               d.DepartmentName AS AssignedDepartmentName
        FROM Tickets t
        LEFT JOIN Departments d ON t.AssignedToDepartmentID = d.DepartmentID
        WHERE t.TicketID = ? AND t.CreatedByUserID = ?");
    if ($stmt_ticket) {
        $stmt_ticket->bind_param("ii", $ticket_id_to_view, $user_id);
        $stmt_ticket->execute();
        $result_ticket = $stmt_ticket->get_result();
        if ($result_ticket->num_rows === 1) {
            $ticket_data = $result_ticket->fetch_assoc();
            $stmt_replies = $conn->prepare("
                SELECT tr.ReplyID, tr.ReplyText, tr.CreatedAt AS ReplyDate, tr.FileID,
                       u.UserID AS ReplierUserID, u.FirstName AS ReplierFirstName, u.LastName AS ReplierLastName, u.UserType AS ReplierUserType,
                       f.FileName AS AttachedFileName, f.FilePath AS AttachedFilePath
                FROM TicketReplies tr
                JOIN Users u ON tr.UserID = u.UserID
                LEFT JOIN Files f ON tr.FileID = f.FileID
                WHERE tr.TicketID = ? ORDER BY tr.CreatedAt ASC");
            if ($stmt_replies) {
                $stmt_replies->bind_param("i", $ticket_id_to_view);
                $stmt_replies->execute();
                $result_replies = $stmt_replies->get_result();
                while ($reply = $result_replies->fetch_assoc()) $ticket_replies[] = $reply;
                $stmt_replies->close();

                $stmt_mark_read = $conn->prepare("UPDATE TicketReplies SET IsReadByCreator = TRUE WHERE TicketID = ? AND UserID != ? AND IsReadByCreator = FALSE");
                if ($stmt_mark_read) { $stmt_mark_read->bind_param("ii", $ticket_id_to_view, $user_id); $stmt_mark_read->execute(); $stmt_mark_read->close(); }
            } else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "خطا بارگذاری پاسخ‌ها: " . $conn->error]; }
        } else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "تیکت یافت نشد یا اجازه دسترسی ندارید."]; header("Location: index.php"); exit;}
        $stmt_ticket->close();
    } else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "خطا بارگذاری تیکت: " . $conn->error]; header("Location: index.php"); exit;}
} else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "شناسه تیکت نامعتبر."]; header("Location: index.php"); exit;}


if ($_SERVER["REQUEST_METHOD"] == "POST" && $ticket_data) {
    $action_token_name = 'ticket_reply_form_' . $ticket_id_to_view;
    if (!verify_csrf_token($_POST['csrf_token'] ?? '', $action_token_name)) {
        $errors['csrf'] = 'خطای CSRF!';
    } else {
        if (isset($_POST['submit_reply']) && $ticket_data['Status'] !== 'closed') {
            $reply_body = sanitize_input($_POST['reply_body'] ?? '');
            if (empty($reply_body)) $errors['reply_body'] = 'متن پاسخ نمی‌تواند خالی باشد.';
            if (mb_strlen($reply_body, 'UTF-8') > 5000) $errors['reply_body'] = 'متن پاسخ (بیش از ۵۰۰۰ کاراکتر).';

            $reply_attachment_file_id = null;
            if (isset($_FILES['reply_attachment']) && $_FILES['reply_attachment']['error'] == UPLOAD_ERR_OK) {
                $upload_dir_reply = __DIR__ . '/../../../uploads/ticket_attachments/';
                if (!is_dir($upload_dir_reply)) { if (!mkdir($upload_dir_reply, 0775, true)) $errors['reply_attachment'] = 'خطا ایجاد پوشه آپلود.'; }
                if (!isset($errors['reply_attachment'])) {
                    $file_info_reply = pathinfo($_FILES['reply_attachment']['name']);
                    $file_extension_reply = strtolower($file_info_reply['extension'] ?? '');
                    $allowed_extensions_reply = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip', 'rar', 'xls', 'xlsx', 'ppt', 'pptx', 'mp3', 'wav', 'mp4', 'mov'];
                    $max_file_size_reply = 10 * 1024 * 1024; // 10 MB
                    if (!in_array($file_extension_reply, $allowed_extensions_reply)) $errors['reply_attachment'] = 'نوع فایل پیوست مجاز نیست.';
                    elseif ($_FILES['reply_attachment']['size'] > $max_file_size_reply) $errors['reply_attachment'] = 'حجم فایل (بیش از 10MB).';
                    else {
                        $safe_original_filename_reply = preg_replace('/[^A-Za-z0-9_\-\.ء-ي]/u', '_', basename($_FILES['reply_attachment']['name']));
                        $new_filename_reply = uniqid('ticket_reply_', true) . '_' . $safe_original_filename_reply;
                        $upload_path_reply = $upload_dir_reply . $new_filename_reply;
                        if (move_uploaded_file($_FILES['reply_attachment']['tmp_name'], $upload_path_reply)) {
                            $stmt_file_reply = $conn->prepare("INSERT INTO Files (FileName, FilePath, FileType, FileSize, UploadedByUserID, UploadDate, AssociatedEntityType) VALUES (?, ?, ?, ?, ?, NOW(), 'ticket_reply')");
                            if ($stmt_file_reply) {
                                $file_type_mime_reply = mime_content_type($upload_path_reply) ?: $_FILES['reply_attachment']['type'];
                                $file_size_bytes_reply = $_FILES['reply_attachment']['size'];
                                $relative_path_reply = 'uploads/ticket_attachments/' . $new_filename_reply;
                                $stmt_file_reply->bind_param("sssis", $safe_original_filename_reply, $relative_path_reply, $file_type_mime_reply, $file_size_bytes_reply, $user_id);
                                if ($stmt_file_reply->execute()) $reply_attachment_file_id = $stmt_file_reply->insert_id;
                                else $errors['reply_attachment'] = "خطا ذخیره فایل: " . $stmt_file_reply->error;
                                $stmt_file_reply->close();
                            } else { $errors['reply_attachment'] = "خطا آماده سازی فایل: " . $conn->error; }
                        } else { $errors['reply_attachment'] = 'خطا آپلود فایل.'; }
                    }
                }
            } elseif (isset($_FILES['reply_attachment']) && $_FILES['reply_attachment']['error'] != UPLOAD_ERR_NO_FILE) {
                 $errors['reply_attachment'] = 'خطا آپلود فایل (کد: '.$_FILES['reply_attachment']['error'].')';
            }

            if (empty($errors)) {
                $conn->begin_transaction();
                try {
                    $stmt_insert_reply = $conn->prepare("INSERT INTO TicketReplies (TicketID, UserID, ReplyText, CreatedAt, FileID, IsReadByCreator, IsReadByAdmin) VALUES (?, ?, ?, NOW(), ?, TRUE, FALSE)");
                    if (!$stmt_insert_reply) throw new Exception("آماده سازی پاسخ ناموفق: " . $conn->error);
                    $stmt_insert_reply->bind_param("iisi", $ticket_id_to_view, $user_id, $reply_body, $reply_attachment_file_id);
                    if (!$stmt_insert_reply->execute()) throw new Exception("ثبت پاسخ ناموفق: " . $stmt_insert_reply->error);
                    $stmt_insert_reply->close();

                    $new_status = ($ticket_data['Status'] == 'resolved' || $ticket_data['Status'] == 'closed') ? 'open' : 'in_progress';
                    if ($ticket_data['Status'] == 'closed') $new_status = 'open';
                    if ($ticket_data['Status'] == 'open' && $user_id != $ticket_data['CreatedByUserID']) $new_status = 'in_progress'; // If support replies to open ticket

                    $stmt_update_ticket = $conn->prepare("UPDATE Tickets SET UpdatedAt = NOW(), Status = ? WHERE TicketID = ?");
                    if (!$stmt_update_ticket) throw new Exception("آماده سازی آپدیت تیکت ناموفق: " . $conn->error);
                    $stmt_update_ticket->bind_param("si", $new_status, $ticket_id_to_view);
                    if (!$stmt_update_ticket->execute()) throw new Exception("آپدیت تیکت ناموفق: " . $stmt_update_ticket->error);
                    $stmt_update_ticket->close();

                    $conn->commit();
                    $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'پاسخ شما با موفقیت ثبت شد.'];
                    header("Location: view.php?ticket_id=" . $ticket_id_to_view); exit;
                } catch (Exception $e) { $conn->rollback(); $errors['db_reply'] = "خطای دیتابیس: " . $e->getMessage(); }
            }
        } elseif (isset($_POST['close_ticket_action']) && $ticket_data['Status'] !== 'closed') {
            $stmt_close = $conn->prepare("UPDATE Tickets SET Status = 'closed', UpdatedAt = NOW() WHERE TicketID = ? AND CreatedByUserID = ?");
            if ($stmt_close) {
                $stmt_close->bind_param("ii", $ticket_id_to_view, $user_id);
                if ($stmt_close->execute() && $stmt_close->affected_rows > 0) {
                     $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'تیکت با موفقیت بسته شد.'];
                     header("Location: view.php?ticket_id=" . $ticket_id_to_view); exit;
                } else { $errors['close_ticket'] = "خطا در بستن تیکت: " . $stmt_close->error; }
                $stmt_close->close();
            } else { $errors['close_ticket'] = "خطا آماده سازی: " . $conn->error; }
        }
    }
    $csrf_token_ticket_reply = regenerate_csrf_token('ticket_reply_form_' . $ticket_id_to_view);
}

$status_persian_map = ['open' => 'باز', 'in_progress' => 'در حال بررسی', 'resolved' => 'حل شده', 'closed' => 'بسته شده', 'urgent' => 'فوری'];
$priority_persian_map = ['low' => 'کم', 'medium' => 'متوسط', 'high' => 'زیاد', 'urgent' => 'فوری'];
$status_badge_map = ['open' => 'info', 'in_progress' => 'warning', 'resolved' => 'success', 'closed' => 'secondary', 'urgent' => 'danger'];
?>
<div class="page-header">
    <h1>مشاهده تیکت #<?php echo $ticket_id_to_view; ?>: <?php echo htmlspecialchars($ticket_data['Subject'] ?? '...'); ?></h1>
    <div class="page-header-actions">
        <a href="index.php" class="btn btn-secondary">
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
            <span>بازگشت به لیست</span>
        </a>
    </div>
</div>

<?php if (isset($_SESSION['flash_message'])): $flash = $_SESSION['flash_message']; unset($_SESSION['flash_message']); ?>
     <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert"><?php echo $flash['text']; ?>
     <button type="button" class="close" data-dismiss="alert" aria-label="Close" style="/* ... */"><span aria-hidden="true">&times;</span></button></div>
<?php endif; ?>
<?php if (!empty($errors)): ?> <div class="alert alert-danger"><ul><?php foreach ($errors as $err_msg): ?><li><?php echo htmlspecialchars($err_msg); ?></li><?php endforeach; ?></ul></div> <?php endif; ?>

<?php if ($ticket_data): ?>
    <div class="card shadow-sm ticket-details-card mb-4">
        <div class="card-header bg-light py-3">
            <div class="row align-items-center">
                <div class="col-md-7"><h5 class="mb-0"><strong>موضوع:</strong> <?php echo htmlspecialchars($ticket_data['Subject']); ?></h5></div>
                <div class="col-md-5 text-md-left mt-2 mt-md-0">
                    <span class="badge badge-lg badge-<?php echo $status_badge_map[$ticket_data['Status']] ?? 'light'; ?> p-2 mr-2">وضعیت: <?php echo $status_persian_map[$ticket_data['Status']] ?? htmlspecialchars($ticket_data['Status']); ?></span>
                    <span class="badge badge-lg badge-light p-2">اولویت: <?php echo $priority_persian_map[$ticket_data['Priority']] ?? htmlspecialchars($ticket_data['Priority']); ?></span>
                </div>
            </div>
        </div>
        <div class="card-body small">
            <p class="mb-1"><strong>ایجاد شده:</strong> <?php echo to_jalali($ticket_data['CreatedAt'], 'yyyy/MM/dd HH:mm'); ?></p>
            <p class="mb-1"><strong>آخرین بروزرسانی:</strong> <?php echo to_jalali($ticket_data['UpdatedAt'], 'yyyy/MM/dd HH:mm'); ?></p>
            <?php if($ticket_data['AssignedDepartmentName']): ?><p class="mb-0"><strong>ارجاع به:</strong> <?php echo htmlspecialchars($ticket_data['AssignedDepartmentName']); ?></p><?php endif; ?>
        </div>
    </div>

    <div class="ticket-replies-container">
        <h4 class="mb-3 mt-4">پیام‌ها و پاسخ‌ها:</h4>
        <?php if (!empty($ticket_replies)): ?>
            <?php foreach ($ticket_replies as $reply): $is_own_reply = ($reply['ReplierUserID'] == $user_id); ?>
            <div class="card mb-3 shadow-sm <?php echo $is_own_reply ? 'ticket-reply-user' : 'ticket-reply-other'; ?>">
                <div class="card-header py-2 <?php echo $is_own_reply ? 'bg-primary-user text-white' : 'bg-light text-dark'; ?>">
                    <strong><?php echo htmlspecialchars($reply['ReplierFirstName'] . ' ' . $reply['ReplierLastName']); ?></strong>
                    <span class="small">(<?php echo ($reply['ReplierUserType'] == 'admin' || $reply['ReplierUserType'] == 'manager' || $reply['ReplierUserType'] == 'deputy' || $reply['ReplierUserType'] == 'member') ? 'پشتیبانی/ادمین' : 'شما'; ?>)</span>
                    <small class="float-left text-<?php echo $is_own_reply ? 'white-50' : 'muted'; ?>"><?php echo to_jalali($reply['ReplyDate'], 'yyyy/MM/dd HH:mm'); ?></small>
                </div>
                <div class="card-body">
                    <p class="reply-text"><?php echo nl2br(htmlspecialchars($reply['ReplyText'])); ?></p>
                    <?php if ($reply['FileID'] && $reply['AttachedFileName'] && $reply['AttachedFilePath']): ?>
                        <hr class="my-2"><p class="mb-0 attachment-link">
                            <small><strong>پیوست:</strong> <a href="/my_site/<?php echo htmlspecialchars(ltrim($reply['AttachedFilePath'],'/')); ?>" target="_blank" download="<?php echo htmlspecialchars($reply['AttachedFileName']); ?>">
                                <?php echo htmlspecialchars($reply['AttachedFileName']); ?>
                                <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                            </a></small></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?> <p class="text-muted">هنوز پاسخی برای این تیکت ثبت نشده است.</p> <?php endif; ?>
    </div>

    <?php if ($ticket_data['Status'] !== 'closed'): ?>
    <div class="card mt-4 shadow-sm">
        <div class="card-header"><h5 class="mb-0">ارسال پاسخ جدید</h5></div>
        <div class="card-body">
            <form action="view.php?ticket_id=<?php echo $ticket_id_to_view; ?>" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_ticket_reply; ?>">
                <div class="form-group">
                    <label for="reply_body" class="sr-only">متن پاسخ شما</label>
                    <textarea class="form-control form-control-lg <?php echo isset($errors['reply_body']) ? 'is-invalid' : ''; ?>" id="reply_body" name="reply_body" rows="6" required maxlength="5000" placeholder="پاسخ خود را اینجا بنویسید..."></textarea>
                    <?php if (isset($errors['reply_body'])): ?><div class="invalid-feedback"><?php echo $errors['reply_body']; ?></div><?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="reply_attachment" class="sr-only">فایل پیوست</label>
                    <input type="file" class="form-control-file <?php echo isset($errors['reply_attachment']) ? 'is-invalid' : ''; ?>" id="reply_attachment" name="reply_attachment">
                     <small class="form-text text-muted">حداکثر حجم: 10MB.</small>
                    <?php if (isset($errors['reply_attachment'])): ?><div class="invalid-feedback d-block"><?php echo $errors['reply_attachment']; ?></div><?php endif; ?>
                </div>
                <button type="submit" name="submit_reply" class="btn btn-primary-user btn-lg">
                     <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13L2 9L22 2z"></path><path d="M22 2L15 22L11 13L2 9L22 2z"></path></svg>
                    <span>ارسال پاسخ</span>
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($ticket_data['Status'] === 'resolved' && $ticket_data['Status'] !== 'closed'): ?>
    <div class="mt-4 text-center">
         <form action="view.php?ticket_id=<?php echo $ticket_id_to_view; ?>" method="POST" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_ticket_reply; ?>">
            <button type="submit" name="close_ticket_action" class="btn btn-success btn-lg" onclick="return confirm('آیا از بستن این تیکت به عنوان حل شده رضایت دارید؟');">
                <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <span>تیکت حل شد، بستن تیکت</span>
            </button>
        </form>
    </div>
    <?php elseif ($ticket_data['Status'] === 'closed'): ?>
         <div class="alert alert-secondary mt-4 text-center">این تیکت بسته شده است. برای پیگیری مجدد، لطفاً <a href="create.php?subject=ادامه تیکت <?php echo $ticket_id_to_view; ?>" class="alert-link">یک تیکت جدید</a> با اشاره به این شناسه ایجاد کنید.</div>
    <?php endif; ?>
<?php endif; ?>
<style>
    .badge-lg { padding: 0.5em 0.8em; font-size: 0.9rem; }
    .ticket-reply-user { border-right: 4px solid var(--user-panel-primary-color, #17a2b8); margin-right: 20px; margin-left: 0; }
    .ticket-reply-user .card-header { background-color: var(--user-panel-primary-color, #17a2b8) !important; color: white !important; }
    .ticket-reply-user .card-header small { color: rgba(255,255,255,0.85) !important; }
    .ticket-reply-other { border-left: 4px solid #6c757d; margin-left: 20px; margin-right: 0; }
    .ticket-reply-other .card-header { background-color: #f0f2f5 !important; }
    html[dir="rtl"] .ticket-reply-user { border-left: 4px solid var(--user-panel-primary-color, #17a2b8); border-right: none; margin-left: 20px; margin-right: 0;}
    html[dir="rtl"] .ticket-reply-other { border-right: 4px solid #6c757d; border-left: none; margin-right: 20px; margin-left: 0;}
    html[dir="rtl"] .card-header small.float-left { float: right !important; }
    .reply-text { white-space: pre-wrap; word-wrap: break-word; font-size: 0.95rem; line-height: 1.7;}
    .attachment-link a { font-weight: 500; }
</style>
<script> /* Basic Bootstrap validation */
(function () { 'use strict'; var forms = document.querySelectorAll('.needs-validation');
  Array.prototype.slice.call(forms).forEach(function (form) {
  form.addEventListener('submit', function (event) { if (!form.checkValidity()) { event.preventDefault(); event.stopPropagation(); } form.classList.add('was-validated');}, false);});})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
