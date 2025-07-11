<?php
// user/tickets/create.php
require_once __DIR__ . '/../includes/header.php';

$user_id = get_current_user_id();
if (!$user_id) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'برای ایجاد تیکت باید وارد شوید.'];
    header("Location: " . ($user_base_url ?? '/my_site/user') . "/auth/login.php");
    exit;
}

$csrf_token_ticket_create = generate_csrf_token('ticket_create_form');

$errors = [];
$input_val = ['subject' => '', 'department_id' => '', 'priority' => 'medium', 'body' => ''];

$departments_query = $conn->query("SELECT DepartmentID, DepartmentName FROM Departments WHERE DepartmentName != 'Super Admin' AND DepartmentName NOT LIKE '%ادمین%' ORDER BY DepartmentName");
$available_departments = [];
if ($departments_query) {
    while($dept = $departments_query->fetch_assoc()){
        $available_departments[] = $dept;
    }
    $departments_query->close();
}

$priority_options = ['low' => 'کم', 'medium' => 'متوسط', 'high' => 'زیاد', 'urgent' => 'فوری'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input_val['subject'] = sanitize_input($_POST['subject'] ?? '');
    $input_val['department_id'] = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $input_val['priority'] = sanitize_input($_POST['priority'] ?? 'medium');
    $input_val['body'] = sanitize_input($_POST['body'] ?? ''); // Basic sanitize, for rich text editor, use a library

    if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'ticket_create_form')) {
        $errors['csrf'] = 'خطای CSRF!';
    } else {
        if (empty($input_val['subject'])) $errors['subject'] = 'عنوان تیکت الزامی است.';
        if (mb_strlen($input_val['subject'], 'UTF-8') > 255) $errors['subject'] = 'عنوان تیکت (بیش از ۲۵۵ کاراکتر).';
        if (empty($input_val['body'])) $errors['body'] = 'متن پیام الزامی است.';
        if (mb_strlen($input_val['body'], 'UTF-8') > 10000) $errors['body'] = 'متن پیام بسیار طولانی (بیش از ۱۰۰۰۰ کاراکتر).';

        if ($input_val['department_id'] !== null && $input_val['department_id'] != 0) {
            $dept_exists = false;
            foreach($available_departments as $ad) if($ad['DepartmentID'] == $input_val['department_id']) $dept_exists = true;
            if(!$dept_exists) $errors['department_id'] = "بخش نامعتبر.";
        }
        if (empty($input_val['priority']) || !array_key_exists($input_val['priority'], $priority_options)) {
            $errors['priority'] = 'اولویت نامعتبر.'; $input_val['priority'] = 'medium';
        }

        $attachment_file_id = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../../../uploads/ticket_attachments/';
            if (!is_dir($upload_dir)) { if (!mkdir($upload_dir, 0775, true)) $errors['attachment'] = 'خطا در ایجاد پوشه آپلود.'; }

            if (!isset($errors['attachment'])) { // Proceed if directory is fine
                $file_info = pathinfo($_FILES['attachment']['name']);
                $file_extension = strtolower($file_info['extension'] ?? '');
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip', 'rar', 'xls', 'xlsx', 'ppt', 'pptx', 'mp3', 'wav', 'mp4', 'mov'];
                $max_file_size = 10 * 1024 * 1024; // 10 MB

                if (!in_array($file_extension, $allowed_extensions)) $errors['attachment'] = 'نوع فایل پیوست مجاز نیست.';
                elseif ($_FILES['attachment']['size'] > $max_file_size) $errors['attachment'] = 'حجم فایل پیوست (بیش از 10MB).';
                else {
                    $safe_original_filename = preg_replace('/[^A-Za-z0-9_\-\.ء-ي]/u', '_', basename($_FILES['attachment']['name']));
                    $new_filename = uniqid('ticket_', true) . '_' . $safe_original_filename;
                    $upload_path = $upload_dir . $new_filename;

                    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_path)) {
                        $stmt_file = $conn->prepare("INSERT INTO Files (FileName, FilePath, FileType, FileSize, UploadedByUserID, UploadDate, AssociatedEntityType) VALUES (?, ?, ?, ?, ?, NOW(), 'ticket_reply')");
                        if ($stmt_file) {
                            $file_type_mime = mime_content_type($upload_path) ?: $_FILES['attachment']['type'];
                            $file_size_bytes = $_FILES['attachment']['size'];
                            $relative_path = 'uploads/ticket_attachments/' . $new_filename;

                            $stmt_file->bind_param("sssis", $safe_original_filename, $relative_path, $file_type_mime, $file_size_bytes, $user_id);
                            if ($stmt_file->execute()) $attachment_file_id = $stmt_file->insert_id;
                            else $errors['attachment'] = "خطا ذخیره اطلاعات فایل: " . $stmt_file->error;
                            $stmt_file->close();
                        } else { $errors['attachment'] = "خطا آماده سازی ذخیره فایل: " . $conn->error; }
                    } else { $errors['attachment'] = 'خطا در آپلود فایل.'; }
                }
            }
        } elseif (isset($_FILES['attachment']) && $_FILES['attachment']['error'] != UPLOAD_ERR_NO_FILE) {
            $errors['attachment'] = 'خطا در آپلود فایل پیوست (کد: '.$_FILES['attachment']['error'].')';
        }


        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                $actual_dept_id = ($input_val['department_id'] == 0 || $input_val['department_id'] === null) ? null : $input_val['department_id'];

                $stmt_ticket = $conn->prepare("INSERT INTO Tickets (Subject, CreatedByUserID, AssignedToDepartmentID, Status, Priority, CreatedAt, UpdatedAt) VALUES (?, ?, ?, 'open', ?, NOW(), NOW())");
                if (!$stmt_ticket) throw new Exception("آماده سازی تیکت ناموفق: " . $conn->error);
                $stmt_ticket->bind_param("siis", $input_val['subject'], $user_id, $actual_dept_id, $input_val['priority']);

                if ($stmt_ticket->execute()) {
                    $new_ticket_id = $stmt_ticket->insert_id;
                    $stmt_ticket->close();

                    $stmt_reply = $conn->prepare("INSERT INTO TicketReplies (TicketID, UserID, ReplyText, CreatedAt, FileID, IsReadByCreator, IsReadByAdmin) VALUES (?, ?, ?, NOW(), ?, TRUE, FALSE)");
                    if (!$stmt_reply) throw new Exception("آماده سازی پیام تیکت ناموفق: " . $conn->error);
                    $stmt_reply->bind_param("iisi", $new_ticket_id, $user_id, $input_val['body'], $attachment_file_id); // FileID can be null
                    if (!$stmt_reply->execute()) throw new Exception("ثبت پیام تیکت ناموفق: " . $stmt_reply->error);
                    $stmt_reply->close();

                    $conn->commit();
                    regenerate_csrf_token('ticket_create_form');

                    // --- Create Notification for Admins/Department ---
                    $notification_message_admin = "تیکت جدید (#" . $new_ticket_id . ") با موضوع \"" . htmlspecialchars($input_val['subject']) . "\" توسط کاربر " . htmlspecialchars($_SESSION['username'] ?? 'ناشناس') . " ایجاد شد.";
                    $notification_link_admin = ($admin_base_url ?? '/my_site/admin') . "/tickets/view.php?ticket_id=" . $new_ticket_id;

                    if ($actual_dept_id) {
                        $stmt_dept_managers = $conn->prepare("SELECT UserID FROM UserDepartments WHERE DepartmentID = ? AND IsManager = TRUE");
                        if ($stmt_dept_managers) {
                            $stmt_dept_managers->bind_param("i", $actual_dept_id);
                            $stmt_dept_managers->execute();
                            $res_dept_managers = $stmt_dept_managers->get_result();
                            while ($manager_row = $res_dept_managers->fetch_assoc()) {
                                create_notification($manager_row['UserID'], $notification_message_admin, $notification_link_admin, 'ticket', $new_ticket_id);
                            }
                            $stmt_dept_managers->close();
                        }
                        // Also notify general admins if department managers are not exclusive support for that department
                        $stmt_general_admins_for_dept_ticket = $conn->query("SELECT UserID FROM Users WHERE UserType = 'admin'");
                        if($stmt_general_admins_for_dept_ticket){
                            while($gen_admin_row = $stmt_general_admins_for_dept_ticket->fetch_assoc()){
                                // Avoid duplicate if manager is also admin, though create_notification doesn't check for duplicates itself
                                create_notification($gen_admin_row['UserID'], "[بخش: " . htmlspecialchars($available_departments[array_search($actual_dept_id, array_column($available_departments, 'DepartmentID'))]['DepartmentName'] ?? $actual_dept_id) . "] " .$notification_message_admin, $notification_link_admin, 'ticket', $new_ticket_id);
                            }
                            $stmt_general_admins_for_dept_ticket->close();
                        }

                    } else {
                        $stmt_all_admins = $conn->query("SELECT UserID FROM Users WHERE UserType = 'admin'");
                        if ($stmt_all_admins) {
                            while ($admin_row = $stmt_all_admins->fetch_assoc()) {
                                create_notification($admin_row['UserID'], $notification_message_admin, $notification_link_admin, 'ticket', $new_ticket_id);
                            }
                            $stmt_all_admins->close();
                        }
                    }
                    // --- End Notification ---

                    $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'تیکت شما با موفقیت ایجاد شد. شناسه: #' . $new_ticket_id];
                    header("Location: view.php?ticket_id=" . $new_ticket_id);
                    exit;
                } else { throw new Exception("ایجاد تیکت ناموفق: " . $stmt_ticket->error); }
            } catch (Exception $e) { $conn->rollback(); $errors['db_error'] = "خطای دیتابیس: " . $e->getMessage(); }
        }
    }
    $csrf_token_ticket_create = regenerate_csrf_token('ticket_create_form');
}
?>

<div class="page-header">
    <h1>ایجاد تیکت پشتیبانی جدید</h1>
    <div class="page-header-actions">
        <a href="index.php" class="btn btn-secondary">
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
            <span>بازگشت به لیست تیکت‌ها</span>
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <p><strong>خطا در ایجاد تیکت:</strong></p>
        <ul><?php foreach ($errors as $error_msg): ?><li><?php echo htmlspecialchars($error_msg); ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="create.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_ticket_create; ?>">

            <div class="form-group">
                <label for="subject">موضوع تیکت <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-lg <?php echo isset($errors['subject']) ? 'is-invalid' : ''; ?>" id="subject" name="subject" value="<?php echo htmlspecialchars($input_val['subject']); ?>" required maxlength="255">
                <?php if (isset($errors['subject'])): ?><div class="invalid-feedback"><?php echo $errors['subject']; ?></div><?php endif; ?>
            </div>

            <div class="row">
                <div class="form-group col-md-6">
                    <label for="department_id">ارسال به بخش</label>
                    <select class="form-control custom-select <?php echo isset($errors['department_id']) ? 'is-invalid' : ''; ?>" id="department_id" name="department_id">
                        <option value="0">-- پشتیبانی عمومی / بخش نامشخص --</option>
                        <?php foreach($available_departments as $dept): ?>
                            <option value="<?php echo $dept['DepartmentID']; ?>" <?php echo ($input_val['department_id'] == $dept['DepartmentID']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['DepartmentName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['department_id'])): ?><div class="invalid-feedback"><?php echo $errors['department_id']; ?></div><?php endif; ?>
                </div>
                <div class="form-group col-md-6">
                    <label for="priority">اولویت <span class="text-danger">*</span></label>
                    <select class="form-control custom-select <?php echo isset($errors['priority']) ? 'is-invalid' : ''; ?>" id="priority" name="priority" required>
                        <?php foreach ($priority_options as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($input_val['priority'] == $key) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['priority'])): ?><div class="invalid-feedback"><?php echo $errors['priority']; ?></div><?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="body">متن پیام <span class="text-danger">*</span></label>
                <textarea class="form-control form-control-lg <?php echo isset($errors['body']) ? 'is-invalid' : ''; ?>" id="body" name="body" rows="8" required maxlength="10000"><?php echo htmlspecialchars($input_val['body']); ?></textarea>
                <small class="form-text text-muted">لطفاً مشکل یا سوال خود را با جزئیات کامل شرح دهید.</small>
                <?php if (isset($errors['body'])): ?><div class="invalid-feedback"><?php echo $errors['body']; ?></div><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="attachment">فایل پیوست (اختیاری)</label>
                <input type="file" class="form-control-file <?php echo isset($errors['attachment']) ? 'is-invalid' : ''; ?>" id="attachment" name="attachment">
                <small class="form-text text-muted">حداکثر حجم: 10MB. فرمت‌های مجاز: تصاویر، PDF، اسناد آفیس، فشرده و ...</small>
                <?php if (isset($errors['attachment'])): ?><div class="invalid-feedback d-block"><?php echo $errors['attachment']; ?></div><?php endif; ?>
            </div>

            <div class="form-actions mt-4">
                <button type="submit" class="btn btn-primary-user btn-lg">
                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13L2 9L22 2z"></path><path d="M22 2L15 22L11 13L2 9L22 2z"></path></svg>
                    <span>ارسال تیکت</span>
                </button>
                <a href="index.php" class="btn btn-outline-secondary btn-lg">انصراف</a>
            </div>
        </form>
    </div>
</div>
<script> /* Basic Bootstrap validation */
(function () { 'use strict'; var forms = document.querySelectorAll('.needs-validation');
  Array.prototype.slice.call(forms).forEach(function (form) {
  form.addEventListener('submit', function (event) { if (!form.checkValidity()) { event.preventDefault(); event.stopPropagation(); } form.classList.add('was-validated');}, false);});})();
</script>
<?php
require_once __DIR__ . '/../includes/footer.php';
?>
