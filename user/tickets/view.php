<?php
require_once __DIR__ . '/../includes/header.php';

$user_id_tv_page = get_current_user_id();
$ticket_id_tv_url_page = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;

if (!$user_id_tv_page) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'برای مشاهده تیکت باید وارد شوید.'];
    header("Location: " . ($user_base_url ?? '/my_site/user') . "/auth/login.php");
    exit;
}
if ($ticket_id_tv_url_page <= 0) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'شناسه تیکت نامعتبر است.'];
    header("Location: index.php");
    exit;
}

$page_title_ut_view_page = "مشاهده تیکت #" . $ticket_id_tv_url_page;
$ticket_details_uv_page = null;
$ticket_replies_uv_list = [];
$errors_ut_view_page = [];
$input_val_reply_uv_page = ['body' => ''];

$csrf_token_name_ticket_reply = 'ticket_reply_form_' . $ticket_id_tv_url_page; // Unique token per ticket view
$csrf_token_ticket_reply_val = generate_csrf_token($csrf_token_name_ticket_reply);


if ($conn) {
    $stmt_ticket_uv_page = $conn->prepare("SELECT t.*, d.DepartmentName
                                    FROM Tickets t
                                    LEFT JOIN Departments d ON t.AssignedToDepartmentID = d.DepartmentID
                                    WHERE t.TicketID = ? AND t.CreatedByUserID = ?");
    if ($stmt_ticket_uv_page) {
        $stmt_ticket_uv_page->bind_param("ii", $ticket_id_tv_url_page, $user_id_tv_page);
        $stmt_ticket_uv_page->execute();
        $result_ticket_uv_page = $stmt_ticket_uv_page->get_result();
        if (!($ticket_details_uv_page = $result_ticket_uv_page->fetch_assoc())) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'تیکت یافت نشد یا شما دسترسی به آن ندارید.'];
            header("Location: index.php");
            exit;
        }
        $stmt_ticket_uv_page->close();

        // Mark admin replies as read by creator (user) upon viewing
        $stmt_mark_read_page = $conn->prepare("UPDATE TicketReplies SET IsReadByCreator = TRUE WHERE TicketID = ? AND IsAdminReply = TRUE AND IsReadByCreator = FALSE");
        if($stmt_mark_read_page){ $stmt_mark_read_page->bind_param("i", $ticket_id_tv_url_page); $stmt_mark_read_page->execute(); $stmt_mark_read_page->close(); }

        $stmt_replies_uv_page = $conn->prepare(
            "SELECT tr.*, u.Username as ReplierUsername, u.FirstName as ReplierFirstName, u.LastName as ReplierLastName, f.FileName, f.FilePath, f.FileSize
             FROM TicketReplies tr
             LEFT JOIN Users u ON tr.UserID = u.UserID  -- Changed from JOIN to LEFT JOIN in case user is deleted but replies remain
             LEFT JOIN Files f ON tr.FileID = f.FileID
             WHERE tr.TicketID = ?
             ORDER BY tr.CreatedAt ASC"
        );
        if ($stmt_replies_uv_page) {
            $stmt_replies_uv_page->bind_param("i", $ticket_id_tv_url_page);
            $stmt_replies_uv_page->execute();
            $result_replies_uv_page = $stmt_replies_uv_page->get_result();
            while ($row_r_page = $result_replies_uv_page->fetch_assoc()) {
                $ticket_replies_uv_list[] = $row_r_page;
            }
            $stmt_replies_uv_page->close();
        } else { $errors_ut_view_page['db_replies'] = "خطا در بارگذاری پاسخ‌ها: " . $conn->error; }
    } else { $errors_ut_view_page['db_ticket'] = "خطا در بارگذاری تیکت: " . $conn->error; }
} else { $errors_ut_view_page['db_conn'] = "خطا در اتصال به پایگاه داده."; }


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_reply'])) {
    $input_val_reply_uv_page['body'] = sanitize_input($_POST['reply_body'] ?? '');

    if (!verify_csrf_token($_POST['csrf_token'] ?? '', $csrf_token_name_ticket_reply)) {
        $errors_ut_view_page['csrf'] = 'خطای CSRF!';
    } else {
        $csrf_token_ticket_reply_val = regenerate_csrf_token($csrf_token_name_ticket_reply); // Regenerate after use
        if (empty($input_val_reply_uv_page['body'])) $errors_ut_view_page['reply_body'] = 'متن پاسخ نمی‌تواند خالی باشد.';
        if (mb_strlen($input_val_reply_uv_page['body'], 'UTF-8') > 5000) $errors_ut_view_page['reply_body'] = 'متن پاسخ طولانی (بیش از ۵۰۰۰ کاراکتر).';

        $attachment_file_id_reply_page = null;
        if (isset($_FILES['reply_attachment']) && $_FILES['reply_attachment']['error'] == UPLOAD_ERR_OK) {
            $upload_dir_reply_page = __DIR__ . '/../../../uploads/ticket_attachments/';
            if (!is_dir($upload_dir_reply_page)) { if (!mkdir($upload_dir_reply_page, 0775, true)) $errors_ut_view_page['reply_attachment'] = 'خطا ایجاد پوشه آپلود.'; }
            if(!isset($errors_ut_view_page['reply_attachment'])){
                // File validation logic (same as create.php, consider refactoring to a function)
                $file_info_reply_page = pathinfo($_FILES['reply_attachment']['name']);
                $file_extension_reply_page = strtolower($file_info_reply_page['extension'] ?? '');
                $allowed_extensions_reply_page = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip', 'rar', 'xls', 'xlsx', 'mp3', 'wav', 'mp4', 'mov']; // Keep it consistent
                $max_file_size_reply_page = 10 * 1024 * 1024; // 10 MB

                if (!in_array($file_extension_reply_page, $allowed_extensions_reply_page)) $errors_ut_view_page['reply_attachment'] = 'نوع فایل پیوست مجاز نیست.';
                elseif ($_FILES['reply_attachment']['size'] > $max_file_size_reply_page) $errors_ut_view_page['reply_attachment'] = 'حجم فایل پیوست (بیش از 10MB).';
                else {
                    $safe_orig_fname_reply_page = preg_replace('/[^A-Za-z0-9_\-\.ء-ي]/u', '_', basename($_FILES['reply_attachment']['name']));
                    $new_fname_reply_page = uniqid('ticketreply_', true) . '_' . $safe_orig_fname_reply_page;
                    $upload_path_reply_page = $upload_dir_reply_page . $new_fname_reply_page;
                    if (move_uploaded_file($_FILES['reply_attachment']['tmp_name'], $upload_path_reply_page)) {
                        $stmt_file_reply_page = $conn->prepare("INSERT INTO Files (FileName, FilePath, FileType, FileSize, UploadedByUserID, UploadDate, AssociatedEntityType) VALUES (?, ?, ?, ?, ?, NOW(), 'ticket_reply')");
                        if ($stmt_file_reply_page) {
                            $ft_mime_reply_page = mime_content_type($upload_path_reply_page) ?: $_FILES['reply_attachment']['type'];
                            $fs_bytes_reply_page = $_FILES['reply_attachment']['size'];
                            $rel_path_reply_page = 'uploads/ticket_attachments/' . $new_fname_reply_page;
                            $stmt_file_reply_page->bind_param("sssis", $safe_orig_fname_reply_page, $rel_path_reply_page, $ft_mime_reply_page, $fs_bytes_reply_page, $user_id_tv_page);
                            if ($stmt_file_reply_page->execute()) $attachment_file_id_reply_page = $stmt_file_reply_page->insert_id;
                            else $errors_ut_view_page['reply_attachment'] = "خطا ذخیره فایل: " . $stmt_file_reply_page->error;
                            $stmt_file_reply_page->close();
                        } else { $errors_ut_view_page['reply_attachment'] = "خطا آماده سازی فایل: " . $conn->error; }
                    } else { $errors_ut_view_page['reply_attachment'] = 'خطا در آپلود فایل.'; }
                }
            }
        } elseif (isset($_FILES['reply_attachment']) && $_FILES['reply_attachment']['error'] != UPLOAD_ERR_NO_FILE) {
            $errors_ut_view_page['reply_attachment'] = 'خطا در آپلود (کد: '.$_FILES['reply_attachment']['error'].')';
        }

        if (empty($errors_ut_view_page)) {
            if ($conn && $ticket_details_uv_page) { // Ensure ticket details are loaded
                $conn->begin_transaction();
                try {
                    $stmt_ins_reply_page = $conn->prepare("INSERT INTO TicketReplies (TicketID, UserID, ReplyText, CreatedAt, FileID, IsAdminReply, IsReadByAdmin, IsReadByCreator) VALUES (?, ?, ?, NOW(), ?, FALSE, FALSE, TRUE)");
                    if (!$stmt_ins_reply_page) throw new Exception("آماده سازی پاسخ ناموفق: " . $conn->error);

                    $stmt_ins_reply_page->bind_param("iisi", $ticket_id_tv_url_page, $user_id_tv_page, $input_val_reply_uv_page['body'], $attachment_file_id_reply_page);
                    if (!$stmt_ins_reply_page->execute()) throw new Exception("ثبت پاسخ ناموفق: " . $stmt_ins_reply_page->error);
                    $new_reply_id = $stmt_ins_reply_page->insert_id;
                    $stmt_ins_reply_page->close();

                    $new_ticket_status_page = 'pending_admin_reply';
                    if ($ticket_details_uv_page['Status'] === 'closed' || $ticket_details_uv_page['Status'] === 'resolved') {
                        $new_ticket_status_page = 'open';
                    }
                    $stmt_update_ticket_page = $conn->prepare("UPDATE Tickets SET LastUpdatedAt = NOW(), Status = ? WHERE TicketID = ?");
                    if(!$stmt_update_ticket_page) throw new Exception("آماده سازی بروزرسانی تیکت ناموفق: ".$conn->error);
                    $stmt_update_ticket_page->bind_param("si", $new_ticket_status_page, $ticket_id_tv_url_page);
                    if(!$stmt_update_ticket_page->execute()) throw new Exception("بروزرسانی تیکت ناموفق: ".$stmt_update_ticket_page->error);
                    $stmt_update_ticket_page->close();

                    $conn->commit();

                    // --- Notification for Admins/Department ---
                    $notification_message_admin_reply = "کاربر ".htmlspecialchars($_SESSION['username']??"ناشناس")." به تیکت #".$ticket_id_tv_url_page." پاسخ جدیدی ارسال کرد.";
                    $notification_link_admin_reply = ($admin_base_url ?? '/my_site/admin')."/tickets/view.php?ticket_id=".$ticket_id_tv_url_page;
                    $admin_ids_to_notify_reply = [];
                    if($ticket_details_uv_page['AssignedToAdminID']) $admin_ids_to_notify_reply[] = $ticket_details_uv_page['AssignedToAdminID'];

                    if($ticket_details_uv_page['AssignedToDepartmentID']){
                        $stmt_dept_mngrs_reply = $conn->prepare("SELECT UserID FROM UserDepartments WHERE DepartmentID = ? AND IsManager = TRUE");
                        if($stmt_dept_mngrs_reply){ $stmt_dept_mngrs_reply->bind_param("i", $ticket_details_uv_page['AssignedToDepartmentID']); $stmt_dept_mngrs_reply->execute(); $res_mngrs_reply = $stmt_dept_mngrs_reply->get_result(); while($m_row_reply = $res_mngrs_reply->fetch_assoc()) $admin_ids_to_notify_reply[] = $m_row_reply['UserID']; $stmt_dept_mngrs_reply->close();}
                    }
                    if(empty($admin_ids_to_notify_reply)){ // If no specific admin/dept manager, notify all admins
                        $stmt_all_admins_reply_page = $conn->query("SELECT UserID FROM Users WHERE UserType = 'admin' AND IsActive = TRUE");
                        if ($stmt_all_admins_reply_page) while ($admin_r_page = $stmt_all_admins_reply_page->fetch_assoc()) $admin_ids_to_notify_reply[] = $admin_r_page['UserID'];
                    }
                    foreach(array_unique($admin_ids_to_notify_reply) as $admin_id_notify_reply) {
                        create_notification($admin_id_notify_reply, $notification_message_admin_reply, $notification_link_admin_reply, 'ticket_reply', $new_reply_id); // Use new_reply_id for context
                    }
                    // --- End Notification ---

                    $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'پاسخ شما با موفقیت ثبت شد.'];
                    header("Location: view.php?ticket_id=" . $ticket_id_tv_url_page);
                    exit;
                } catch (Exception $e_reply) { $conn->rollback(); $errors_ut_view_page['db_error'] = "خطای دیتابیس: " . $e_reply->getMessage(); }
            } else { $errors_ut_view_page['db_conn_reply'] = "خطا در اتصال به پایگاه داده."; }
        }
    }
}

$priority_display_map_uv_page = ['low' => 'کم', 'medium' => 'متوسط', 'high' => 'زیاد', 'urgent' => 'فوری'];
$ticket_statuses_map_uv_page = $ticket_statuses_user_map_display; // Use the map defined earlier

?>
<div class="page-header">
    <h1><?php echo $page_title_ut_view_page; ?></h1>
    <div class="page-header-actions"><a href="index.php" class="btn btn-secondary"><em class="bi bi-list-ul icon"></em> بازگشت به لیست تیکت‌ها</a></div>
</div>

<?php if (isset($_SESSION['flash_message'])): $flash_uv_page = $_SESSION['flash_message']; ?>
    <div class="alert alert-<?php echo $flash_uv_page['type']; ?> alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($flash_uv_page['text']); unset($_SESSION['flash_message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if (!empty($errors_ut_view_page)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert"><strong>خطا:</strong><ul class="mb-0 ps-3"><?php foreach ($errors_ut_view_page as $err_uv_page): echo "<li>".htmlspecialchars($err_uv_page)."</li>"; endforeach; ?></ul><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<?php if ($ticket_details_uv_page): ?>
<div class="card shadow-sm mb-4">
    <div class="card-header bg-light py-3">
        <h5 class="mb-0 d-flex justify-content-between align-items-center">
            <?php echo htmlspecialchars($ticket_details_uv_page['Subject']); ?>
            <span class="badge fs-sm bg-<?php echo $ticket_statuses_map_uv_page[$ticket_details_uv_page['Status']]['badge'] ?? 'secondary'; ?>"><?php echo $ticket_statuses_map_uv_page[$ticket_details_uv_page['Status']]['label'] ?? htmlspecialchars($ticket_details_uv_page['Status']); ?></span>
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6"><p><strong>شناسه تیکت:</strong> #<?php echo $ticket_details_uv_page['TicketID']; ?></p></div>
            <div class="col-md-6"><p><strong>اولویت:</strong> <?php echo $priority_display_map_uv_page[$ticket_details_uv_page['Priority']] ?? htmlspecialchars($ticket_details_uv_page['Priority']); ?></p></div>
            <div class="col-md-6"><p><strong>بخش مربوطه:</strong> <?php echo htmlspecialchars($ticket_details_uv_page['DepartmentName'] ?: 'پشتیبانی عمومی'); ?></p></div>
            <div class="col-md-6"><p><strong>تاریخ ایجاد:</strong> <?php echo to_jalali($ticket_details_uv_page['CreatedAt'], 'yyyy/MM/dd HH:mm'); ?></p></div>
        </div>
    </div>
</div>

<h5 class="mb-3 mt-4 pt-2 border-top">پیام‌های تیکت:</h5>
<?php if (empty($ticket_replies_uv_list)): ?>
    <p class="text-muted">هنوز پیامی برای این تیکت ثبت نشده است (این حالت نباید رخ دهد چون پیام اولیه کاربر باید موجود باشد).</p>
<?php else: ?>
    <?php foreach($ticket_replies_uv_list as $reply_item):
        $is_admin_reply_item = (bool)$reply_item['IsAdminReply'];
        $replier_name_display = $is_admin_reply_item ? 'پشتیبانی '.SITE_NAME : htmlspecialchars(trim(($reply_item['ReplierFirstName']??'').' '.($reply_item['ReplierLastName']??$_SESSION['username'])));
        if(empty(trim($replier_name_display))) $replier_name_display = htmlspecialchars($reply_item['ReplierUsername'] ?? 'کاربر');
    ?>
    <div class="card mb-3 <?php echo $is_admin_reply_item ? 'border-info' : 'border-primary-user'; ?>">
        <div class="card-header <?php echo $is_admin_reply_item ? 'bg-info-soft' : 'bg-primary-user-soft'; ?> py-2 px-3 d-flex justify-content-between align-items-center">
            <strong class="<?php echo $is_admin_reply_item ? 'text-info' : 'text-primary-user'; ?>"><?php echo $replier_name_display; ?></strong>
            <small class="text-muted"><?php echo to_jalali($reply_item['CreatedAt'], 'yyyy/MM/dd HH:mm'); ?></small>
        </div>
        <div class="card-body py-3 px-3">
            <p style="white-space: pre-wrap;"><?php echo nl2br(htmlspecialchars($reply_item['ReplyText'])); ?></p>
            <?php if ($reply_item['FileID'] && $reply_item['FileName'] && $reply_item['FilePath']): ?>
                <p class="mt-2 mb-0 border-top pt-2">
                    <em class="bi bi-paperclip"></em> پیوست:
                    <a href="<?php echo get_base_url() . htmlspecialchars($reply_item['FilePath']); ?>" target="_blank" download="<?php echo htmlspecialchars($reply_item['FileName']); ?>">
                        <?php echo htmlspecialchars($reply_item['FileName']); ?>
                    </a>
                    (<?php echo round(($reply_item['FileSize'] ?? 0) / 1024, 1); ?> KB)
                </p>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($ticket_details_uv_page['Status'] !== 'closed' && $ticket_details_uv_page['Status'] !== 'resolved'): ?>
<div class="card shadow-sm mt-4">
    <div class="card-header bg-light"><h5 class="mb-0">ارسال پاسخ جدید</h5></div>
    <div class="card-body">
        <form action="view.php?ticket_id=<?php echo $ticket_id_tv_url_page; ?>" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_ticket_reply_val; ?>">
            <div class="form-group mb-3">
                <label for="reply_body_uv_form" class="form-label">متن پاسخ شما <span class="text-danger">*</span></label>
                <textarea class="form-control <?php echo isset($errors_ut_view_page['reply_body']) ? 'is-invalid' : ''; ?>" id="reply_body_uv_form" name="reply_body" rows="6" required maxlength="5000"><?php echo htmlspecialchars($input_val_reply_uv_page['body']); ?></textarea>
                <?php if (isset($errors_ut_view_page['reply_body'])): ?><div class="invalid-feedback"><?php echo $errors_ut_view_page['reply_body']; ?></div><?php endif; ?>
            </div>
            <div class="form-group mb-3">
                <label for="reply_attachment_uv_form" class="form-label">فایل پیوست (اختیاری)</label>
                <input type="file" class="form-control <?php echo isset($errors_ut_view_page['reply_attachment']) ? 'is-invalid' : ''; ?>" id="reply_attachment_uv_form" name="reply_attachment">
                <small class="form-text text-muted">حداکثر 10MB.</small>
                <?php if (isset($errors_ut_view_page['reply_attachment'])): ?><div class="invalid-feedback d-block"><?php echo $errors_ut_view_page['reply_attachment']; ?></div><?php endif; ?>
            </div>
            <div class="form-actions">
                <button type="submit" name="submit_reply" class="btn btn-primary-user btn-lg px-4">
                    <em class="bi bi-send-fill icon me-2"></em> ارسال پاسخ
                </button>
            </div>
        </form>
    </div>
</div>
<?php else: ?>
    <div class="alert alert-warning text-center mt-4 lead">این تیکت بسته شده است و امکان ارسال پاسخ جدید وجود ندارد. در صورت نیاز، <a href="create.php" class="fw-bold">یک تیکت جدید ایجاد کنید</a>.</div>
<?php endif; ?>

<?php else: ?>
    <div class="alert alert-danger">خطا: اطلاعات تیکت برای نمایش یافت نشد.</div>
<?php endif; ?>
<style>
    .bg-info-soft { background-color: #e7f5ff !important; border-left: 4px solid #0dcaf0; }
    .bg-primary-user-soft { background-color: #f8f9fa !important; border-left: 4px solid #0d6efd; }
    .text-primary-user { color: #0a58ca !important; }
    .fs-xs { font-size: .78rem !important; }
</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
