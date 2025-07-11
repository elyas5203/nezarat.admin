<?php
// admin/tickets/view.php
require_once __DIR__ . '/../includes/header.php';

$admin_id = get_current_user_id();
if (!$admin_id) { /* Should be handled by header */ exit; }

$ticket_id_to_view_admin = null;
$ticket_data_admin = null;
$ticket_replies_admin = [];
$errors_admin_ticket = [];

if (isset($_GET['ticket_id']) && is_numeric($_GET['ticket_id'])) {
    $ticket_id_to_view_admin = (int)$_GET['ticket_id'];
    $csrf_token_admin_ticket_reply = generate_csrf_token('admin_ticket_actions_form_' . $ticket_id_to_view_admin);

    $stmt_ticket_admin = $conn->prepare("
        SELECT t.*,
               CONCAT(u_creator.FirstName, ' ', u_creator.LastName) AS CreatorFullName, u_creator.Username AS CreatorUsername, u_creator.UserID AS CreatorUserID,
               d.DepartmentName AS AssignedDepartmentName
        FROM Tickets t
        JOIN Users u_creator ON t.CreatedByUserID = u_creator.UserID
        LEFT JOIN Departments d ON t.AssignedToDepartmentID = d.DepartmentID
        WHERE t.TicketID = ?
    ");
    if ($stmt_ticket_admin) {
        $stmt_ticket_admin->bind_param("i", $ticket_id_to_view_admin);
        $stmt_ticket_admin->execute();
        $result_ticket_admin = $stmt_ticket_admin->get_result();
        if ($result_ticket_admin->num_rows === 1) {
            $ticket_data_admin = $result_ticket_admin->fetch_assoc();

            $stmt_replies_admin = $conn->prepare("
                SELECT tr.*, u.FirstName AS ReplierFirstName, u.LastName AS ReplierLastName, u.UserType AS ReplierUserType,
                       f.FileName AS AttachedFileName, f.FilePath AS AttachedFilePath
                FROM TicketReplies tr
                JOIN Users u ON tr.UserID = u.UserID
                LEFT JOIN Files f ON tr.FileID = f.FileID
                WHERE tr.TicketID = ? ORDER BY tr.CreatedAt ASC
            ");
            if ($stmt_replies_admin) {
                $stmt_replies_admin->bind_param("i", $ticket_id_to_view_admin);
                $stmt_replies_admin->execute();
                $result_replies_admin = $stmt_replies_admin->get_result();
                while ($reply_a = $result_replies_admin->fetch_assoc()) $ticket_replies_admin[] = $reply_a;
                $stmt_replies_admin->close();

                $stmt_mark_admin_read = $conn->prepare("UPDATE TicketReplies SET IsReadByAdmin = TRUE WHERE TicketID = ? AND UserID != ? AND IsReadByAdmin = FALSE");
                if ($stmt_mark_admin_read) {
                    $stmt_mark_admin_read->bind_param("ii", $ticket_id_to_view_admin, $admin_id);
                    $stmt_mark_admin_read->execute();
                    $stmt_mark_admin_read->close();
                }
            } else { $errors_admin_ticket['load_replies'] = "خطا بارگذاری پاسخ‌ها: " . $conn->error; }
        } else { $errors_admin_ticket['load_ticket'] = "تیکت یافت نشد."; }
        $stmt_ticket_admin->close();
    } else { $errors_admin_ticket['load_ticket'] = "خطا بارگذاری تیکت: " . $conn->error; }
} else { $errors_admin_ticket['load_ticket'] = "شناسه تیکت نامعتبر."; }


if ($_SERVER["REQUEST_METHOD"] == "POST" && $ticket_data_admin) {
    $action_token_name_admin = 'admin_ticket_actions_form_' . $ticket_id_to_view_admin;
    if (!verify_csrf_token($_POST['csrf_token'] ?? '', $action_token_name_admin)) {
        $errors_admin_ticket['csrf'] = 'خطای CSRF! لطفاً صفحه را رفرش کرده و دوباره تلاش کنید.';
    } else {
        $redirect_needed = false;
        $action_successful = true;
        $current_flash_message = null; // Store potential flash message to set it once after commit

        $conn->begin_transaction();
        try {
            // Handle Admin Reply Submission
            if (isset($_POST['submit_admin_reply'])) {
                $admin_reply_body = sanitize_input($_POST['admin_reply_body'] ?? '');
                if (empty(trim($admin_reply_body))) { $errors_admin_ticket['admin_reply_body'] = 'متن پاسخ نمی‌تواند خالی باشد.'; $action_successful = false; }
                if (mb_strlen($admin_reply_body, 'UTF-8') > 5000) { $errors_admin_ticket['admin_reply_body'] = 'متن پاسخ بسیار طولانی است (حداکثر ۵۰۰۰ کاراکتر).'; $action_successful = false;}

                $admin_reply_attachment_id = null;
                if (isset($_FILES['admin_reply_attachment']) && $_FILES['admin_reply_attachment']['error'] == UPLOAD_ERR_OK) {
                    $upload_dir_admin_reply = __DIR__ . '/../../../uploads/ticket_attachments/';
                    if (!is_dir($upload_dir_admin_reply)) { if (!mkdir($upload_dir_admin_reply, 0775, true)) {throw new Exception('خطا در ایجاد پوشه آپلود.');} }

                    $file_info_admin_reply = pathinfo($_FILES['admin_reply_attachment']['name']);
                    $file_extension_admin_reply = strtolower($file_info_admin_reply['extension'] ?? '');
                    $allowed_extensions_admin_reply = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip', 'rar', 'xls', 'xlsx', 'ppt', 'pptx', 'mp3', 'wav', 'mp4', 'mov'];
                    $max_file_size_admin_reply = 10 * 1024 * 1024;
                    if (!in_array($file_extension_admin_reply, $allowed_extensions_admin_reply)) throw new Exception('نوع فایل پیوست مجاز نیست.');
                    if ($_FILES['admin_reply_attachment']['size'] > $max_file_size_admin_reply) throw new Exception('حجم فایل پیوست بیش از 10 مگابایت است.');

                    $safe_original_filename_admin_reply = preg_replace('/[^A-Za-z0-9_\-\.ء-ي]/u', '_', basename($_FILES['admin_reply_attachment']['name']));
                    $new_filename_admin_reply = uniqid('ticket_admin_reply_', true) . '_' . $safe_original_filename_admin_reply;
                    $upload_path_admin_reply = $upload_dir_admin_reply . $new_filename_admin_reply;

                    if (!move_uploaded_file($_FILES['admin_reply_attachment']['tmp_name'], $upload_path_admin_reply)) throw new Exception('خطا در آپلود فایل پیوست.');

                    $stmt_file_admin_reply = $conn->prepare("INSERT INTO Files (FileName, FilePath, FileType, FileSize, UploadedByUserID, UploadDate, AssociatedEntityType) VALUES (?, ?, ?, ?, ?, NOW(), 'ticket_reply')");
                    if (!$stmt_file_admin_reply) throw new Exception("خطا در آماده سازی اطلاعات فایل: " . $conn->error);
                    $file_type_mime_admin_reply = mime_content_type($upload_path_admin_reply) ?: $_FILES['admin_reply_attachment']['type'];
                    $file_size_bytes_admin_reply = $_FILES['admin_reply_attachment']['size'];
                    $relative_path_admin_reply = 'uploads/ticket_attachments/' . $new_filename_admin_reply;
                    $stmt_file_admin_reply->bind_param("sssis", $safe_original_filename_admin_reply, $relative_path_admin_reply, $file_type_mime_admin_reply, $file_size_bytes_admin_reply, $admin_id);
                    if (!$stmt_file_admin_reply->execute()) throw new Exception("خطا در ذخیره اطلاعات فایل پیوست: " . $stmt_file_admin_reply->error);
                    $admin_reply_attachment_id = $stmt_file_admin_reply->insert_id;
                    $stmt_file_admin_reply->close();
                } elseif (isset($_FILES['admin_reply_attachment']) && $_FILES['admin_reply_attachment']['error'] != UPLOAD_ERR_NO_FILE) {
                     throw new Exception('خطا در آپلود فایل پیوست (کد: '.$_FILES['admin_reply_attachment']['error'].')');
                }

                if($action_successful){
                    $stmt_insert_admin_reply = $conn->prepare("INSERT INTO TicketReplies (TicketID, UserID, ReplyText, CreatedAt, FileID, IsReadByCreator, IsReadByAdmin) VALUES (?, ?, ?, NOW(), ?, FALSE, TRUE)");
                    if (!$stmt_insert_admin_reply) throw new Exception("خطا در آماده سازی پاسخ ادمین: " . $conn->error);
                    $stmt_insert_admin_reply->bind_param("iisi", $ticket_id_to_view_admin, $admin_id, $admin_reply_body, $admin_reply_attachment_id);
                    if (!$stmt_insert_admin_reply->execute()) throw new Exception("خطا در ثبت پاسخ ادمین: " . $stmt_insert_admin_reply->error);
                    $stmt_insert_admin_reply->close();

                    $current_ticket_status = $ticket_data_admin['Status'];
                    $new_ticket_status_after_reply = $current_ticket_status;
                    if ($current_ticket_status == 'open' || $current_ticket_status == 'resolved') $new_ticket_status_after_reply = 'in_progress';

                    $stmt_update_ticket_ts = $conn->prepare("UPDATE Tickets SET UpdatedAt = NOW(), Status = ? WHERE TicketID = ?");
                    if(!$stmt_update_ticket_ts) throw new Exception("خطا در آماده سازی آپدیت وضعیت تیکت: " . $conn->error);
                    $stmt_update_ticket_ts->bind_param("si", $new_ticket_status_after_reply, $ticket_id_to_view_admin);
                    if(!$stmt_update_ticket_ts->execute()) throw new Exception("خطا در آپدیت وضعیت تیکت: " . $stmt_update_ticket_ts->error);
                    $stmt_update_ticket_ts->close();

                    $ticket_creator_user_id_notify = $ticket_data_admin['CreatorUserID'];
                    $notification_message_user_reply = "پاسخ جدیدی برای تیکت شما (#" . $ticket_id_to_view_admin . ") با موضوع \"" . htmlspecialchars($ticket_data_admin['Subject']) . "\" ارسال شد.";
                    $notification_link_user_ticket = ($user_base_url ?? '/my_site/user') . "/tickets/view.php?ticket_id=" . $ticket_id_to_view_admin;
                    create_notification($ticket_creator_user_id_notify, $notification_message_user_reply, $notification_link_user_ticket, 'ticket_reply', $ticket_id_to_view_admin);

                    $current_flash_message = ['type' => 'success', 'text' => 'پاسخ شما با موفقیت ثبت شد.'];
                    $redirect_needed = true;
                } else {$action_successful = false;} // Errors in reply body or attachment
            }

            // Handle Status/Priority/Department Change
            if (isset($_POST['update_ticket_meta'])) {
                $new_status_meta = sanitize_input($_POST['ticket_status'] ?? $ticket_data_admin['Status']);
                $new_priority_meta = sanitize_input($_POST['ticket_priority'] ?? $ticket_data_admin['Priority']);
                $new_department_id_meta = !empty($_POST['assigned_department_id']) ? (int)$_POST['assigned_department_id'] : null;

                $status_persian_map_check = ['open' => '', 'in_progress' => '', 'resolved' => '', 'closed' => '', 'urgent' => '']; // For key check
                $priority_persian_map_check = ['low' => '', 'medium' => '', 'high' => '', 'urgent' => '']; // For key check

                if (!array_key_exists($new_status_meta, $status_persian_map_check)) { $errors_admin_ticket['status_meta'] = "وضعیت نامعتبر."; $action_successful = false; }
                if (!array_key_exists($new_priority_meta, $priority_persian_map_check)) { $errors_admin_ticket['priority_meta'] = "اولویت نامعتبر."; $action_successful = false; }
                // Add validation for department ID if needed

                if($action_successful){
                    $stmt_update_meta = $conn->prepare("UPDATE Tickets SET Status = ?, Priority = ?, AssignedToDepartmentID = ?, UpdatedAt = NOW() WHERE TicketID = ?");
                    if (!$stmt_update_meta) throw new Exception("خطا آماده سازی بروزرسانی اطلاعات تیکت: " . $conn->error);

                    $actual_new_dept_id_meta = ($new_department_id_meta == 0) ? null : $new_department_id_meta;
                    $stmt_update_meta->bind_param("ssii", $new_status_meta, $new_priority_meta, $actual_new_dept_id_meta, $ticket_id_to_view_admin);
                    if ($stmt_update_meta->execute()) {
                        if ($stmt_update_meta->affected_rows > 0) {
                            if(!isset($current_flash_message)) $current_flash_message = ['type' => 'success', 'text' => 'اطلاعات تیکت بروزرسانی شد.'];
                            if ($new_status_meta !== $ticket_data_admin['Status']) {
                                 $status_change_msg_user_meta = "وضعیت تیکت شما (#" . $ticket_id_to_view_admin . ") با موضوع \"" . htmlspecialchars($ticket_data_admin['Subject']) . "\" به \"" . ($status_persian_map_admin_view[$new_status_meta] ?? $new_status_meta) . "\" تغییر یافت.";
                                 $user_link_ticket_status_meta = ($user_base_url ?? '/my_site/user') . "/tickets/view.php?ticket_id=" . $ticket_id_to_view_admin;
                                 create_notification($ticket_data_admin['CreatorUserID'], $status_change_msg_user_meta, $user_link_ticket_status_meta, 'ticket_status', $ticket_id_to_view_admin);
                            }
                        }
                        $redirect_needed = true;
                    } else { throw new Exception("بروزرسانی اطلاعات تیکت ناموفق: " . $stmt_update_meta->error); }
                    $stmt_update_meta->close();
                } else {$action_successful = false;}
            }

            if ($action_successful) {
                $conn->commit();
                if($current_flash_message) $_SESSION['flash_message'] = $current_flash_message;

            } else {
                $conn->rollback();
            }

        } catch (Exception $e) {
            $conn->rollback();
            $errors_admin_ticket['db_operation'] = "خطای عملیات: " . $e->getMessage();
        }

        if ($redirect_needed && empty($errors_admin_ticket) && $action_successful) {
            regenerate_csrf_token($action_token_name_admin);
            header("Location: view.php?ticket_id=" . $ticket_id_to_view_admin); exit;
        }
    }
    $csrf_token_admin_ticket_reply = regenerate_csrf_token('admin_ticket_actions_form_' . $ticket_id_to_view_admin);
}

// ... (rest of the HTML, same as before, using $csrf_token_admin_ticket_reply for forms) ...
// Fetch departments for assignment dropdown
$departments_assign_query_adm = $conn->query("SELECT DepartmentID, DepartmentName FROM Departments WHERE DepartmentName != 'Super Admin' AND DepartmentName NOT LIKE '%ادمین%' ORDER BY DepartmentName");
$assignable_departments_adm = [];
if($departments_assign_query_adm) { while($d_a = $departments_assign_query_adm->fetch_assoc()) $assignable_departments_adm[] = $d_a; $departments_assign_query_adm->close(); }

$status_persian_map_admin_view = ['open' => 'باز', 'in_progress' => 'در حال بررسی', 'resolved' => 'حل شده', 'closed' => 'بسته شده', 'urgent' => 'فوری'];
$priority_persian_map_admin_view = ['low' => 'کم', 'medium' => 'متوسط', 'high' => 'زیاد', 'urgent' => 'فوری'];
$status_badge_map_admin_view = ['open' => 'info', 'in_progress' => 'warning', 'resolved' => 'success', 'closed' => 'secondary', 'urgent' => 'danger'];
?>
<div class="page-header"><h1>مدیریت تیکت #<?php echo $ticket_id_to_view_admin; ?>: <?php echo htmlspecialchars($ticket_data_admin['Subject'] ?? '...'); ?></h1>
    <div class="page-header-actions"><a href="index.php" class="btn btn-secondary"><svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"></polyline></svg><span>بازگشت به لیست</span></a></div></div>

<?php if (isset($_SESSION['flash_message'])): $flash_admin_v = $_SESSION['flash_message']; unset($_SESSION['flash_message']); ?> <div class="alert alert-<?php echo $flash_admin_v['type']; ?> alert-dismissible fade show" role="alert"><?php echo $flash_admin_v['text']; ?><button type="button" class="close" data-dismiss="alert" aria-label="Close" style="background:none; border:none; font-size:1.5rem; position:absolute; top:0; left:0; padding: 0.75rem 1.25rem;"><span aria-hidden="true">&times;</span></button></div><?php endif; ?>
<?php if (!empty($errors_admin_ticket)): ?> <div class="alert alert-danger"><ul><?php foreach ($errors_admin_ticket as $err_key_a_v => $err_msg_a_v): ?><li><strong><?php echo htmlspecialchars(str_replace(array('_','meta','admin','reply','body'),' ',$err_key_a_v)); // Basic prettify ?>:</strong> <?php echo htmlspecialchars($err_msg_a_v); ?></li><?php endforeach; ?></ul></div> <?php endif; ?>

<?php if ($ticket_data_admin): ?>
    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm ticket-details-card">
                <div class="card-header bg-light py-3"><div class="row align-items-center">
                    <div class="col-md-7"><h5 class="mb-0"><strong>موضوع:</strong> <?php echo htmlspecialchars($ticket_data_admin['Subject']); ?></h5></div>
                    <div class="col-md-5 text-md-left mt-2 mt-md-0">
                        <span class="badge badge-lg badge-<?php echo $status_badge_map_admin_view[$ticket_data_admin['Status']] ?? 'light'; ?> p-2 mr-2">وضعیت: <?php echo $status_persian_map_admin_view[$ticket_data_admin['Status']] ?? $ticket_data_admin['Status']; ?></span>
                        <span class="badge badge-lg badge-light p-2">اولویت: <?php echo $priority_persian_map_admin_view[$ticket_data_admin['Priority']] ?? $ticket_data_admin['Priority']; ?></span>
                    </div></div></div>
                <div class="card-body small">
                    <p class="mb-1"><strong>ایجاد کننده:</strong> <?php echo htmlspecialchars($ticket_data_admin['CreatorFullName'] . ' (@' . $ticket_data_admin['CreatorUsername'] . ')'); ?></p>
                    <p class="mb-1"><strong>ایجاد شده:</strong> <?php echo to_jalali($ticket_data_admin['CreatedAt'], 'yyyy/MM/dd HH:mm'); ?></p>
                    <p class="mb-1"><strong>آخرین بروزرسانی:</strong> <?php echo to_jalali($ticket_data_admin['UpdatedAt'], 'yyyy/MM/dd HH:mm'); ?></p>
                    <?php if($ticket_data_admin['AssignedDepartmentName']): ?><p class="mb-0"><strong>ارجاع به:</strong> <?php echo htmlspecialchars($ticket_data_admin['AssignedDepartmentName']); ?></p><?php endif; ?>
                </div>
            </div>
            <div class="ticket-replies-container mt-4">
                <h4 class="mb-3">پیام‌ها و پاسخ‌ها:</h4>
                <?php if (!empty($ticket_replies_admin)): ?>
                    <?php foreach ($ticket_replies_admin as $reply_a_v): $is_admin_side_reply_v = in_array($reply_a_v['ReplierUserType'], ['admin', 'manager', 'deputy', 'member']); ?>
                    <div class="card mb-3 shadow-sm <?php echo $is_admin_side_reply_v ? 'ticket-reply-admin-side' : 'ticket-reply-user-side'; ?>">
                        <div class="card-header py-2 <?php echo $is_admin_side_reply_v ? 'bg-info text-white' : 'bg-light text-dark'; ?>">
                            <strong><?php echo htmlspecialchars($reply_a_v['ReplierFirstName'] . ' ' . $reply_a_v['ReplierLastName']); ?></strong>
                            <span class="small">(<?php echo $is_admin_side_reply_v ? 'پشتیبانی/ادمین' : 'کاربر'; ?>)</span>
                            <small class="float-left text-<?php echo $is_admin_side_reply_v ? 'white-50' : 'muted'; ?>"><?php echo to_jalali($reply_a_v['ReplyDate'], 'yyyy/MM/dd HH:mm'); ?></small>
                        </div>
                        <div class="card-body"><p class="reply-text"><?php echo nl2br(htmlspecialchars($reply_a_v['ReplyText'])); ?></p>
                            <?php if ($reply_a_v['FileID'] && $reply_a_v['AttachedFileName'] && $reply_a_v['AttachedFilePath']): ?>
                                <hr class="my-2"><p class="mb-0 attachment-link"><small><strong>پیوست:</strong> <a href="/my_site/<?php echo htmlspecialchars(ltrim($reply_a_v['AttachedFilePath'],'/')); ?>" target="_blank" download="<?php echo htmlspecialchars($reply_a_v['AttachedFileName']); ?>"><?php echo htmlspecialchars($reply_a_v['AttachedFileName']); ?> <svg class="icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg></a></small></p>
                            <?php endif; ?></div></div> <?php endforeach; ?>
                <?php else: ?> <p class="text-muted">هنوز پاسخی برای این تیکت ثبت نشده است.</p> <?php endif; ?>
            </div></div>
        <div class="col-lg-4">
            <div class="card shadow-sm mb-4 sticky-top" style="top: 80px;">
                <div class="card-header"><h5 class="mb-0">مدیریت تیکت</h5></div>
                <div class="card-body">
                    <form action="view.php?ticket_id=<?php echo $ticket_id_to_view_admin; ?>" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_admin_ticket_reply; ?>">
                        <div class="form-group"><label for="ticket_status_admin">تغییر وضعیت:</label><select name="ticket_status" id="ticket_status_admin" class="form-control custom-select <?php echo isset($errors_admin_ticket['status_meta']) ? 'is-invalid' : ''; ?>"><?php foreach($status_persian_map_admin_view as $s_key_adm_v => $s_val_adm_v): ?><option value="<?php echo $s_key_adm_v; ?>" <?php if(($ticket_data_admin['Status'] ?? '') == $s_key_adm_v) echo 'selected'; ?>><?php echo $s_val_adm_v; ?></option><?php endforeach; ?></select><?php if(isset($errors_admin_ticket['status_meta'])):?><div class="invalid-feedback"><?php echo $errors_admin_ticket['status_meta'];?></div><?php endif;?></div>
                        <div class="form-group"><label for="ticket_priority_admin">تغییر اولویت:</label><select name="ticket_priority" id="ticket_priority_admin" class="form-control custom-select <?php echo isset($errors_admin_ticket['priority_meta']) ? 'is-invalid' : ''; ?>"><?php foreach($priority_persian_map_admin_view as $p_key_adm_v => $p_val_adm_v): ?><option value="<?php echo $p_key_adm_v; ?>" <?php if(($ticket_data_admin['Priority'] ?? '') == $p_key_adm_v) echo 'selected'; ?>><?php echo $p_val_adm_v; ?></option><?php endforeach; ?></select><?php if(isset($errors_admin_ticket['priority_meta'])):?><div class="invalid-feedback"><?php echo $errors_admin_ticket['priority_meta'];?></div><?php endif;?></div>
                        <div class="form-group"><label for="assigned_department_id_admin">ارجاع به بخش:</label><select name="assigned_department_id" id="assigned_department_id_admin" class="form-control custom-select"><option value="0">-- بدون ارجاع / عمومی --</option><?php foreach($assignable_departments_adm as $dept_a_v): ?><option value="<?php echo $dept_a_v['DepartmentID']; ?>" <?php if(($ticket_data_admin['AssignedToDepartmentID'] ?? null) == $dept_a_v['DepartmentID']) echo 'selected'; ?>><?php echo htmlspecialchars($dept_a_v['DepartmentName']); ?></option><?php endforeach; ?></select></div>
                        <button type="submit" name="update_ticket_meta" class="btn btn-info btn-block">بروزرسانی اطلاعات</button>
                    </form>
                </div></div>
            <?php if (($ticket_data_admin['Status'] ?? 'closed') !== 'closed'): ?>
            <div class="card shadow-sm sticky-top" style="top: <?php echo (count($assignable_departments_adm) > 3 ? '460px' : '420px'); ?>;"> <!-- Adjusted top offset -->
                <div class="card-header"><h5 class="mb-0">ارسال پاسخ ادمین</h5></div>
                <div class="card-body">
                    <form action="view.php?ticket_id=<?php echo $ticket_id_to_view_admin; ?>" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_admin_ticket_reply; ?>">
                        <div class="form-group"><label for="admin_reply_body" class="sr-only">متن پاسخ</label><textarea class="form-control <?php echo isset($errors_admin_ticket['admin_reply_body']) ? 'is-invalid' : ''; ?>" id="admin_reply_body" name="admin_reply_body" rows="5" required placeholder="پاسخ خود را بنویسید..."></textarea><?php if (isset($errors_admin_ticket['admin_reply_body'])): ?><div class="invalid-feedback"><?php echo $errors_admin_ticket['admin_reply_body']; ?></div><?php endif; ?></div>
                        <div class="form-group"><label for="admin_reply_attachment" class="sr-only">فایل پیوست</label><input type="file" class="form-control-file <?php echo isset($errors_admin_ticket['admin_reply_attachment']) ? 'is-invalid' : ''; ?>" id="admin_reply_attachment" name="admin_reply_attachment"><small class="form-text text-muted">حداکثر: 10MB.</small><?php if (isset($errors_admin_ticket['admin_reply_attachment'])): ?><div class="invalid-feedback d-block"><?php echo $errors_admin_ticket['admin_reply_attachment']; ?></div><?php endif; ?></div>
                        <button type="submit" name="submit_admin_reply" class="btn btn-primary btn-block">ارسال پاسخ</button>
                    </form></div></div> <?php endif; ?> </div></div> <?php endif; ?>
<style>
    .badge-lg { padding: 0.5em 0.8em; font-size: 0.9rem; }
    .ticket-reply-admin-side { border-right: 4px solid var(--primary, #007bff); margin-right: 20px; margin-left: 0; }
    .ticket-reply-admin-side .card-header { background-color: var(--primary, #007bff) !important; color: white !important; }
    .ticket-reply-admin-side .card-header small { color: rgba(255,255,255,0.85) !important; }
    .ticket-reply-user-side { border-left: 4px solid #6c757d; margin-left: 20px; margin-right: 0; }
    .ticket-reply-user-side .card-header { background-color: #f0f2f5 !important; }
    html[dir="rtl"] .ticket-reply-admin-side { border-left: 4px solid var(--primary, #007bff); border-right: none; margin-left: 20px; margin-right: 0;}
    html[dir="rtl"] .ticket-reply-user-side { border-right: 4px solid #6c757d; border-left: none; margin-right: 20px; margin-left: 0;}
    html[dir="rtl"] .card-header small.float-left { float: right !important; }
    .reply-text { white-space: pre-wrap; word-wrap: break-word; font-size: 0.95rem; line-height: 1.7;}
    .attachment-link a { font-weight: 500; }
    .sticky-top { z-index: 900; }
</style>
<script> /* Basic Bootstrap validation */ /* ... */</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
```

این اصلاحات به مدیریت بهتر تراکنش‌ها و جلوگیری از خطاهای احتمالی در هنگام بروزرسانی‌های متعدد در یک درخواست POST کمک می‌کند.
اکنون به سراغ بخش بعدی یعنی بهبود نمایش نوتیفیکیشن‌ها در هدر می‌روم.
