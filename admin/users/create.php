<?php
require_once __DIR__ . '/../includes/header.php';

// Initialize variables for form fields to prevent errors on first load
$username = '';
$first_name = '';
$last_name = '';
$email = '';
$phone = '';
$role_ids = []; // For multiple roles
$is_active = true; // Default to active
$form_errors = [];
$form_success_message = '';

// CSRF token generation
$csrf_token = generate_csrf_token('create_user');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'], 'create_user')) {
        $form_errors['csrf'] = 'خطای امنیتی CSRF. لطفاً صفحه را رفرش کرده و مجدداً تلاش کنید.';
    } else {
        // Regenerate token after successful verification for this instance
        $csrf_token = regenerate_csrf_token('create_user');

        $username = sanitize_input($_POST['username'] ?? '');
        $password = $_POST['password'] ?? ''; // Password is not sanitized with htmlspecialchars
        $password_confirm = $_POST['password_confirm'] ?? '';
        $first_name = sanitize_input($_POST['first_name'] ?? '');
        $last_name = sanitize_input($_POST['last_name'] ?? '');
        $email = sanitize_input($_POST['email'] ?? ''); // Email will be validated
        $phone = sanitize_input($_POST['phone'] ?? '');
        $role_ids = isset($_POST['role_ids']) && is_array($_POST['role_ids']) ? array_map('intval', $_POST['role_ids']) : [];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // Basic Validation
        if (empty($username)) {
            $form_errors['username'] = 'نام کاربری نمی‌تواند خالی باشد.';
        } elseif (strlen($username) < 3) {
            $form_errors['username'] = 'نام کاربری باید حداقل ۳ کاراکتر باشد.';
        } elseif (strtolower($username) === 'admin') {
            $form_errors['username'] = 'نام کاربری "admin" برای کاربران عادی مجاز نیست.';
        }
         else {
            // Check if username already exists
            $stmt_check_username = $conn->prepare("SELECT UserID FROM Users WHERE Username = ?");
            if ($stmt_check_username) {
                $stmt_check_username->bind_param("s", $username);
                $stmt_check_username->execute();
                if ($stmt_check_username->get_result()->num_rows > 0) {
                    $form_errors['username'] = 'این نام کاربری قبلاً استفاده شده است.';
                }
                $stmt_check_username->close();
            } else {
                $form_errors['db_error'] = 'خطا در بررسی نام کاربری: ' . $conn->error;
            }
        }

        if (empty($password)) {
            $form_errors['password'] = 'رمز عبور نمی‌تواند خالی باشد.';
        } elseif (strlen($password) < 6) {
            $form_errors['password'] = 'رمز عبور باید حداقل ۶ کاراکتر باشد.';
        } elseif ($password !== $password_confirm) {
            $form_errors['password_confirm'] = 'تکرار رمز عبور مطابقت ندارد.';
        }

        if (empty($first_name)) $form_errors['first_name'] = 'نام نمی‌تواند خالی باشد.';
        if (empty($last_name)) $form_errors['last_name'] = 'نام خانوادگی نمی‌تواند خالی باشد.';

        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $form_errors['email'] = 'فرمت ایمیل نامعتبر است.';
        } else if (!empty($email)) {
            // Optional: Check if email already exists if it's meant to be unique for non-admin users
            $stmt_check_email = $conn->prepare("SELECT UserID FROM Users WHERE Email = ? AND UserType != 'admin'");
            if ($stmt_check_email) {
                $stmt_check_email->bind_param("s", $email);
                $stmt_check_email->execute();
                if ($stmt_check_email->get_result()->num_rows > 0) {
                    // $form_errors['email'] = 'این ایمیل قبلاً توسط کاربر دیگری استفاده شده است.'; // Uncomment if email must be unique
                }
                $stmt_check_email->close();
            }
        }

        if (empty($role_ids)) {
             $form_errors['role_ids'] = 'حداقل یک نقش باید برای کاربر انتخاب شود.';
        }


        if (empty($form_errors)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $conn->begin_transaction();
            try {
                // Determine UserType based on selected roles (e.g., if 'teacher' role, UserType could be 'teacher')
                // For now, a generic 'user' or determined by the first role. This needs more definition.
                // Let's assume a default UserType 'user' for now.
                $user_type_to_insert = 'user'; // Default, can be refined.

                $stmt_insert_user = $conn->prepare("INSERT INTO Users (Username, Password, FirstName, LastName, Email, PhoneNumber, UserType, IsActive, CreatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                if (!$stmt_insert_user) throw new Exception("خطا در آماده سازی کوئری کاربر: " . $conn->error);

                $stmt_insert_user->bind_param("sssssssi", $username, $hashed_password, $first_name, $last_name, $email, $phone, $user_type_to_insert, $is_active);
                if (!$stmt_insert_user->execute()) throw new Exception("خطا در ایجاد کاربر: " . $stmt_insert_user->error);

                $new_user_id = $stmt_insert_user->insert_id;
                $stmt_insert_user->close();

                // Assign roles
                if (!empty($role_ids) && $new_user_id) {
                    $stmt_assign_role = $conn->prepare("INSERT INTO UserRoles (UserID, RoleID) VALUES (?, ?)");
                    if (!$stmt_assign_role) throw new Exception("خطا در آماده سازی کوئری تخصیص نقش: " . $conn->error);

                    foreach ($role_ids as $role_id) {
                        // Ensure role_id is valid and not 'admin' role if it exists with ID
                        $stmt_assign_role->bind_param("ii", $new_user_id, $role_id);
                        if (!$stmt_assign_role->execute()) throw new Exception("خطا در تخصیص نقش با شناسه $role_id: " . $stmt_assign_role->error);
                    }
                    $stmt_assign_role->close();
                }

                $conn->commit();
                $_SESSION['user_action_success'] = "کاربر '" . htmlspecialchars($username) . "' با موفقیت ایجاد شد.";

                // Clear form fields after successful submission by redirecting
                 header("Location: index.php");
                 exit;

            } catch (Exception $e) {
                $conn->rollback();
                $form_errors['db_error'] = "خطای پایگاه داده: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="page-header">
    <h1>افزودن کاربر جدید</h1>
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
<?php if (!empty($form_success_message)): ?> <!-- This might not be shown due to redirect -->
    <div class="alert alert-success"><?php echo $form_success_message; ?></div>
<?php endif; ?>


<div class="card">
    <div class="card-body">
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="form-container needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="username">نام کاربری <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?php echo isset($form_errors['username']) ? 'is-invalid' : ''; ?>" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                        <?php if (isset($form_errors['username'])): ?><div class="invalid-feedback"><?php echo $form_errors['username']; ?></div><?php endif; ?>
                    </div>
                </div>
                 <div class="col-md-6">
                    <div class="form-group">
                        <label for="email">ایمیل</label>
                        <input type="email" class="form-control <?php echo isset($form_errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="اختیاری">
                        <?php if (isset($form_errors['email'])): ?><div class="invalid-feedback"><?php echo $form_errors['email']; ?></div><?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="password">رمز عبور <span class="text-danger">*</span></label>
                        <input type="password" class="form-control <?php echo isset($form_errors['password']) ? 'is-invalid' : ''; ?>" id="password" name="password" required>
                        <?php if (isset($form_errors['password'])): ?><div class="invalid-feedback"><?php echo $form_errors['password']; ?></div><?php endif; ?>
                        <small class="form-text text-muted">حداقل ۶ کاراکتر.</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="password_confirm">تکرار رمز عبور <span class="text-danger">*</span></label>
                        <input type="password" class="form-control <?php echo isset($form_errors['password_confirm']) ? 'is-invalid' : ''; ?>" id="password_confirm" name="password_confirm" required>
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
                <input type="text" class="form-control <?php echo isset($form_errors['phone']) ? 'is-invalid' : ''; ?>" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>" dir="ltr" placeholder="اختیاری، مانند 09123456789">
                <?php if (isset($form_errors['phone'])): ?><div class="invalid-feedback"><?php echo $form_errors['phone']; ?></div><?php endif; ?>
            </div>

            <div class="form-group">
                <label>نقش(ها) <span class="text-danger">*</span></label>
                <div class="form-check-group <?php echo isset($form_errors['role_ids']) ? 'is-invalid' : ''; ?>">
                <?php
                // Fetch roles from DB to populate checkboxes
                // Ensure $conn is available (it should be from header.php)
                if ($conn) {
                    $available_roles_query = "SELECT RoleID, RoleName, Description FROM Roles WHERE RoleName != 'admin' ORDER BY RoleName"; // Exclude 'admin' role
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
                        echo '<p class="text-muted">هیچ نقشی (به جز ادمین اصلی) تعریف نشده است. لطفاً ابتدا از بخش <a href="roles.php">مدیریت نقش‌ها</a>، نقش‌های مورد نظر را ایجاد کنید.</p>';
                         if(empty($form_errors['role_ids'])) $form_errors['role_ids'] = "هیچ نقشی برای تخصیص وجود ندارد. ابتدا نقش ایجاد کنید."; // Add error if no roles available
                    }
                } else {
                     echo '<p class="text-danger">خطا در اتصال به پایگاه داده برای بارگذاری نقش‌ها.</p>';
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

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">ایجاد کاربر</button>
                <a href="index.php" class="btn btn-secondary">انصراف</a>
            </div>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
