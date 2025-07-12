<?php
require_once __DIR__ . '/../includes/header.php';

$user_id_to_edit = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Initialize variables
$username_orig = ''; // To store original username for comparison and preventing edit of 'admin'
$username_form = '';
$first_name = '';
$last_name = '';
$email = '';
$phone = '';
$current_user_type = '';
$role_ids = [];
$is_active = false;
$form_errors = [];
$form_success_message = ''; // Not typically used with redirect, but good for non-redirect cases

// CSRF token generation - make it unique per user being edited to avoid clashes if multiple edit tabs are open
$csrf_token_name = 'edit_user_' . $user_id_to_edit;
$csrf_token = generate_csrf_token($csrf_token_name);

if ($user_id_to_edit <= 0 && $user_id_to_edit !== 0) { // UserID 0 might be the admin if we decide so
    $_SESSION['user_action_error'] = 'شناسه کاربر نامعتبر است.';
    header("Location: index.php");
    exit;
}

// Fetch user data for editing
if ($conn) {
    $stmt_fetch_user = $conn->prepare("SELECT UserID, Username, FirstName, LastName, Email, PhoneNumber, UserType, IsActive FROM Users WHERE UserID = ?");
    if ($stmt_fetch_user) {
        $stmt_fetch_user->bind_param("i", $user_id_to_edit);
        $stmt_fetch_user->execute();
        $result_user = $stmt_fetch_user->get_result();
        if ($user_data = $result_user->fetch_assoc()) {
            $user_id_to_edit = $user_data['UserID']; // Ensure we use the ID from DB
            $username_orig = $user_data['Username'];
            $username_form = $user_data['Username'];
            $first_name = $user_data['FirstName'];
            $last_name = $user_data['LastName'];
            $email = $user_data['Email'];
            $phone = $user_data['PhoneNumber'];
            $current_user_type = $user_data['UserType'];
            $is_active = (bool)$user_data['IsActive'];

            if (strtolower($username_orig) !== 'admin') { // Don't fetch roles for main admin if they are not managed this way
                $stmt_fetch_roles = $conn->prepare("SELECT RoleID FROM UserRoles WHERE UserID = ?");
                if ($stmt_fetch_roles) {
                    $stmt_fetch_roles->bind_param("i", $user_id_to_edit);
                    $stmt_fetch_roles->execute();
                    $result_roles = $stmt_fetch_roles->get_result();
                    while ($row_role = $result_roles->fetch_assoc()) {
                        $role_ids[] = $row_role['RoleID'];
                    }
                    $stmt_fetch_roles->close();
                } else {
                     $form_errors['db_error'] = 'خطا در بارگذاری نقش‌های کاربر: ' . $conn->error;
                }
            }
        } else {
            $_SESSION['user_action_error'] = 'کاربر با شناسه ' . htmlspecialchars($user_id_to_edit) . ' یافت نشد.';
            header("Location: index.php");
            exit;
        }
        $stmt_fetch_user->close();
    } else {
        $form_errors['db_error'] = 'خطا در آماده سازی کوئری بارگذاری کاربر: ' . $conn->error;
    }
} else {
    $form_errors['db_error'] = 'خطا در اتصال به پایگاه داده.';
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'], $csrf_token_name)) {
        $form_errors['csrf'] = 'خطای امنیتی CSRF. لطفاً صفحه را رفرش کرده و مجدداً تلاش کنید.';
    } else {
        $csrf_token = regenerate_csrf_token($csrf_token_name); // Regenerate after use

        // Get new data from form, ensuring they are populated for display if errors occur
        $username_form = sanitize_input($_POST['username'] ?? $username_orig); // Default to original if not set
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        $first_name = sanitize_input($_POST['first_name'] ?? $first_name);
        $last_name = sanitize_input($_POST['last_name'] ?? $last_name);
        $email = sanitize_input($_POST['email'] ?? $email);
        $phone = sanitize_input($_POST['phone'] ?? $phone);

        if (strtolower($username_orig) !== 'admin') {
            $role_ids = isset($_POST['role_ids']) && is_array($_POST['role_ids']) ? array_map('intval', $_POST['role_ids']) : [];
            $is_active = isset($_POST['is_active']) ? 1 : 0;
        } else {
            // For admin, roles are not changed here, and admin is always active.
            // $role_ids remain as fetched (empty for admin, or could be a special 'admin' role ID if designed that way)
            $is_active = 1; // Main admin must be active
        }


        // Validation
        if (strtolower($username_orig) !== 'admin' && empty($username_form)) { // Username can't be empty for non-admins
            $form_errors['username'] = 'نام کاربری نمی‌تواند خالی باشد.';
        } elseif (strtolower($username_orig) !== 'admin' && strlen($username_form) < 3) {
            $form_errors['username'] = 'نام کاربری باید حداقل ۳ کاراکتر باشد.';
        } elseif (strtolower($username_orig) !== 'admin' && strtolower($username_form) === 'admin') {
             $form_errors['username'] = 'نام کاربری "admin" برای کاربران عادی مجاز نیست.';
        } elseif ($username_form !== $username_orig && strtolower($username_orig) !== 'admin') {
            $stmt_check_username = $conn->prepare("SELECT UserID FROM Users WHERE Username = ? AND UserID != ?");
            if ($stmt_check_username) {
                $stmt_check_username->bind_param("si", $username_form, $user_id_to_edit);
                $stmt_check_username->execute();
                if ($stmt_check_username->get_result()->num_rows > 0) {
                    $form_errors['username'] = 'این نام کاربری قبلاً توسط کاربر دیگری استفاده شده است.';
                }
                $stmt_check_username->close();
            }
        } elseif (strtolower($username_orig) === 'admin' && $username_form !== $username_orig) {
            // This case should not happen if input is readonly, but as a safeguard:
            $form_errors['username'] = 'نام کاربری ادمین اصلی قابل تغییر نیست.';
            $username_form = $username_orig; // Reset to original
        }


        if (!empty($password)) {
            if (strlen($password) < 6) {
                $form_errors['password'] = 'رمز عبور جدید باید حداقل ۶ کاراکتر باشد.';
            } elseif ($password !== $password_confirm) {
                $form_errors['password_confirm'] = 'تکرار رمز عبور جدید مطابقت ندارد.';
            }
        }

        if (empty($first_name)) $form_errors['first_name'] = 'نام نمی‌تواند خالی باشد.';
        if (empty($last_name)) $form_errors['last_name'] = 'نام خانوادگی نمی‌تواند خالی باشد.';

        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $form_errors['email'] = 'فرمت ایمیل نامعتبر است.';
        } elseif (!empty($email) && $email !== $user_data['Email']) { // Check only if email is changed
             // Optional: Check if new email already exists for another user
        }

        if (strtolower($username_orig) !== 'admin' && empty($role_ids)) {
             $form_errors['role_ids'] = 'حداقل یک نقش باید برای کاربر انتخاب شود.';
        }

        if (empty($form_errors)) {
            $conn->begin_transaction();
            try {
                $update_fields_sql_parts = [];
                $bind_types_update = "";
                $bind_values_update = [];

                // Only add fields to update if they have changed and are valid to change
                if (strtolower($username_orig) !== 'admin' && $username_form !== $username_orig) {
                    $update_fields_sql_parts[] = "Username = ?"; $bind_types_update .= "s"; $bind_values_update[] = $username_form;
                }
                if ($first_name !== $user_data['FirstName']) { $update_fields_sql_parts[] = "FirstName = ?"; $bind_types_update .= "s"; $bind_values_update[] = $first_name; }
                if ($last_name !== $user_data['LastName']) { $update_fields_sql_parts[] = "LastName = ?"; $bind_types_update .= "s"; $bind_values_update[] = $last_name; }
                if ($email !== $user_data['Email']) { $update_fields_sql_parts[] = "Email = ?"; $bind_types_update .= "s"; $bind_values_update[] = $email; }
                if ($phone !== $user_data['PhoneNumber']) { $update_fields_sql_parts[] = "PhoneNumber = ?"; $bind_types_update .= "s"; $bind_values_update[] = $phone; }

                if (strtolower($username_orig) !== 'admin') { // IsActive only for non-admins from this form
                    if ($is_active !== (bool)$user_data['IsActive']) {
                        $update_fields_sql_parts[] = "IsActive = ?"; $bind_types_update .= "i"; $bind_values_update[] = $is_active;
                    }
                }

                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $update_fields_sql_parts[] = "Password = ?"; $bind_types_update .= "s"; $bind_values_update[] = $hashed_password;
                }

                if (!empty($update_fields_sql_parts)) {
                    $sql_update_user = "UPDATE Users SET " . implode(", ", $update_fields_sql_parts) . " WHERE UserID = ?";
                    $bind_types_update .= "i";
                    $bind_values_update[] = $user_id_to_edit;

                    $stmt_update_user = $conn->prepare($sql_update_user);
                    if (!$stmt_update_user) throw new Exception("خطا در آماده سازی کوئری بروزرسانی کاربر: " . $conn->error);

                    $stmt_update_user->bind_param($bind_types_update, ...$bind_values_update);
                    if (!$stmt_update_user->execute()) throw new Exception("خطا در بروزرسانی کاربر: " . $stmt_update_user->error . " SQL: " . $sql_update_user);
                    $stmt_update_user->close();
                }

                // Update roles (only if not the 'admin' user)
                if (strtolower($username_orig) !== 'admin') {
                    $stmt_delete_roles = $conn->prepare("DELETE FROM UserRoles WHERE UserID = ?");
                    if (!$stmt_delete_roles) throw new Exception("خطا در آماده سازی حذف نقش‌های قدیمی: " . $conn->error);
                    $stmt_delete_roles->bind_param("i", $user_id_to_edit);
                    if (!$stmt_delete_roles->execute()) throw new Exception("خطا در حذف نقش‌های قدیمی: " . $stmt_delete_roles->error);
                    $stmt_delete_roles->close();

                    if (!empty($role_ids)) {
                        $stmt_assign_role = $conn->prepare("INSERT INTO UserRoles (UserID, RoleID) VALUES (?, ?)");
                        if (!$stmt_assign_role) throw new Exception("خطا در آماده سازی تخصیص نقش جدید: " . $conn->error);
                        foreach ($role_ids as $role_id_new) {
                            $stmt_assign_role->bind_param("ii", $user_id_to_edit, $role_id_new);
                            if (!$stmt_assign_role->execute()) throw new Exception("خطا در تخصیص نقش جدید $role_id_new: " . $stmt_assign_role->error);
                        }
                        $stmt_assign_role->close();
                    }
                }

                $conn->commit();
                $_SESSION['user_action_success'] = "اطلاعات کاربر '" . htmlspecialchars($username_form) . "' با موفقیت بروزرسانی شد.";

                header("Location: index.php");
                exit;

            } catch (Exception $e) {
                $conn->rollback();
                $form_errors['db_error'] = "خطای پایگاه داده: " . $e->getMessage();
            }
        }
        // Note: If there were errors, $username_form, $first_name etc. are already set to the submitted values for redisplay.
        // $role_ids and $is_active are also set based on POST for redisplay.
    }
}
?>

<div class="page-header">
    <h1>ویرایش کاربر: <?php echo htmlspecialchars($username_orig); // Show original username in title ?></h1>
    <div class="page-header-actions">
        <a href="index.php" class="btn btn-secondary">
             <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-right-circle icon" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8zm15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-4.5-.5a.5.5 0 0 0 0 1h5.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3a.5.5 0 0 0 0-.708l-3-3a.5.5 0 1 0-.708.708L11.293 7.5H6.5a.5.5 0 0 0 0 1h4.793z"/>
            </svg>
            بازگشت به لیست کاربران
        </a>
    </div>
</div>

<?php if (!empty($form_errors['csrf'])): ?>
    <div class="alert alert-danger"><?php echo $form_errors['csrf']; ?></div>
<?php endif; ?>
<?php if (!empty($form_errors['db_error'])): ?>
    <div class="alert alert-danger"><?php echo $form_errors['db_error']; ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?user_id=" . $user_id_to_edit; ?>" class="form-container needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="username">نام کاربری <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?php echo isset($form_errors['username']) ? 'is-invalid' : ''; ?>"
                               id="username" name="username" value="<?php echo htmlspecialchars($username_form); ?>" required
                               <?php echo (strtolower($username_orig) === 'admin') ? 'readonly' : ''; ?>>
                        <?php if (isset($form_errors['username'])): ?><div class="invalid-feedback"><?php echo $form_errors['username']; ?></div><?php endif; ?>
                         <?php if (strtolower($username_orig) === 'admin'): ?><small class="form-text text-muted">نام کاربری ادمین اصلی قابل تغییر نیست.</small><?php endif; ?>
                    </div>
                </div>
                 <div class="col-md-6">
                    <div class="form-group">
                        <label for="email">ایمیل</label>
                        <input type="email" class="form-control <?php echo isset($form_errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>">
                        <?php if (isset($form_errors['email'])): ?><div class="invalid-feedback"><?php echo $form_errors['email']; ?></div><?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="password">رمز عبور جدید</label>
                        <input type="password" class="form-control <?php echo isset($form_errors['password']) ? 'is-invalid' : ''; ?>" id="password" name="password" placeholder="برای عدم تغییر، خالی بگذارید">
                        <?php if (isset($form_errors['password'])): ?><div class="invalid-feedback"><?php echo $form_errors['password']; ?></div><?php endif; ?>
                        <small class="form-text text-muted">حداقل ۶ کاراکتر در صورت تغییر.</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="password_confirm">تکرار رمز عبور جدید</label>
                        <input type="password" class="form-control <?php echo isset($form_errors['password_confirm']) ? 'is-invalid' : ''; ?>" id="password_confirm" name="password_confirm">
                        <?php if (isset($form_errors['password_confirm'])): ?><div class="invalid-feedback"><?php echo $form_errors['password_confirm']; ?></div><?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="first_name">نام <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?php echo isset($form_errors['first_name']) ? 'is-invalid' : ''; ?>" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>" required>
                        <?php if (isset($form_errors['first_name'])): ?><div class="invalid-feedback"><?php echo $form_errors['first_name']; ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="last_name">نام خانوادگی <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?php echo isset($form_errors['last_name']) ? 'is-invalid' : ''; ?>" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>" required>
                        <?php if (isset($form_errors['last_name'])): ?><div class="invalid-feedback"><?php echo $form_errors['last_name']; ?></div><?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="phone">شماره تلفن</label>
                <input type="text" class="form-control <?php echo isset($form_errors['phone']) ? 'is-invalid' : ''; ?>" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>" dir="ltr" placeholder="اختیاری">
                <?php if (isset($form_errors['phone'])): ?><div class="invalid-feedback"><?php echo $form_errors['phone']; ?></div><?php endif; ?>
            </div>

            <?php if (strtolower($username_orig) !== 'admin'): ?>
            <div class="form-group">
                <label>نقش(ها) <span class="text-danger">*</span></label>
                <div class="form-check-group <?php echo isset($form_errors['role_ids']) ? 'is-invalid' : ''; ?>">
                <?php
                if ($conn) {
                    $available_roles_query = "SELECT RoleID, RoleName, Description FROM Roles WHERE RoleName != 'admin' ORDER BY RoleName";
                    $roles_result = $conn->query($available_roles_query);
                    if ($roles_result && $roles_result->num_rows > 0) {
                        while ($role = $roles_result->fetch_assoc()) {
                            $checked = is_array($role_ids) && in_array($role['RoleID'], $role_ids) ? 'checked' : '';
                            echo '<div class="form-check form-check-inline">';
                            echo '<input class="form-check-input" type="checkbox" name="role_ids[]" id="role_' . $role['RoleID'] . '" value="' . $role['RoleID'] . '" ' . $checked . '>';
                            echo '<label class="form-check-label" for="role_' . $role['RoleID'] . '">' . htmlspecialchars($role['RoleName']) . (!empty($role['Description']) ? ' <small class="text-muted">('.htmlspecialchars($role['Description']).')</small>' : '') . '</label>';
                            echo '</div>';
                        }
                    } else {
                        echo '<p class="text-muted">هیچ نقشی (به جز ادمین اصلی) برای تخصیص تعریف نشده است.</p>';
                         if(empty($form_errors['role_ids'])) $form_errors['role_ids'] = "هیچ نقشی برای تخصیص وجود ندارد. ابتدا نقش ایجاد کنید.";
                    }
                } else {
                    echo '<p class="text-danger">خطا در بارگذاری نقش‌ها.</p>';
                     if(empty($form_errors['role_ids'])) $form_errors['role_ids'] = "خطا در بارگذاری نقش‌ها.";
                }
                ?>
                </div>
                 <?php if (isset($form_errors['role_ids'])): ?><div class="invalid-feedback d-block"><?php echo $form_errors['role_ids']; ?></div><?php endif; ?>
            </div>

            <div class="form-group">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?php echo $is_active ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="is_active">
                        کاربر فعال باشد
                    </label>
                </div>
            </div>
            <?php else: ?>
                <input type="hidden" name="is_active" value="1">
                 <div class="alert alert-info mt-3">نقش‌ها و وضعیت فعالیت ادمین اصلی از این بخش قابل تغییر نیست.</div>
            <?php endif; ?>


            <div class="form-actions">
                <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
                <a href="index.php" class="btn btn-secondary">انصراف</a>
            </div>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
