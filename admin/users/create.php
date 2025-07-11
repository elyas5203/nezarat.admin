<?php
require_once __DIR__ . '/../includes/header.php';

// CSRF token generation (should be in helper or a dedicated class)
if (empty($_SESSION['csrf_token_user_create'])) { // Use a specific token name for this form
    $_SESSION['csrf_token_user_create'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token_user_create'];


$errors = [];
$input = ['firstname' => '', 'lastname' => '', 'username' => '', 'user_type' => '', 'is_active' => 1, 'roles' => []];

$user_type_options = [
    'teacher' => 'مدرس',
    'member' => 'عضو بخش',
    'manager' => 'مدیر',
    'deputy' => 'معاون',
];

// Fetch available roles
$roles_query = $conn->query("SELECT RoleID, RoleName FROM Roles ORDER BY RoleName");
$available_roles = [];
if ($roles_query) {
    while ($role = $roles_query->fetch_assoc()) {
        $available_roles[] = $role;
    }
    $roles_query->close();
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token_user_create'], $_POST['csrf_token'])) {
        $errors['csrf'] = 'خطای CSRF! درخواست نامعتبر یا تاریخ گذشته. لطفاً صفحه را رفرش کرده و دوباره تلاش کنید.';
    } else {
        $input['firstname'] = sanitize_input($_POST['firstname'] ?? '');
        $input['lastname'] = sanitize_input($_POST['lastname'] ?? '');
        $input['username'] = sanitize_input(trim($_POST['username'] ?? ''));
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        $input['user_type'] = sanitize_input($_POST['user_type'] ?? '');
        $input['is_active'] = isset($_POST['is_active']) ? 1 : 0;
        $input['roles'] = isset($_POST['roles']) && is_array($_POST['roles']) ? array_map('intval', $_POST['roles']) : [];

        // Validation
        if (empty($input['firstname'])) $errors['firstname'] = 'نام الزامی است.';
        if (empty($input['lastname'])) $errors['lastname'] = 'نام خانوادگی الزامی است.';

        if (empty($input['username'])) {
            $errors['username'] = 'نام کاربری الزامی است.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $input['username'])) {
            $errors['username'] = 'نام کاربری باید شامل حروف انگلیسی، اعداد و زیرخط (_) و بین ۳ تا ۳۰ کاراکتر باشد.';
        } else {
            $stmt_check_username = $conn->prepare("SELECT UserID FROM Users WHERE Username = ?");
            if ($stmt_check_username) {
                $stmt_check_username->bind_param("s", $input['username']);
                $stmt_check_username->execute();
                if ($stmt_check_username->get_result()->num_rows > 0) {
                    $errors['username'] = 'این نام کاربری قبلاً استفاده شده است.';
                }
                $stmt_check_username->close();
            } else {
                $errors['db_error'] = "خطا در بررسی نام کاربری: " . $conn->error;
            }
        }

        if (empty($password)) $errors['password'] = 'رمز عبور الزامی است.';
        elseif (strlen($password) < 6) $errors['password'] = 'رمز عبور باید حداقل ۶ کاراکتر باشد.';
        if ($password !== $password_confirm) $errors['password_confirm'] = 'تکرار رمز عبور مطابقت ندارد.';

        if (empty($input['user_type'])) {
            $errors['user_type'] = 'نوع کاربر الزامی است.';
        } elseif (!array_key_exists($input['user_type'], $user_type_options)) {
            $errors['user_type'] = 'نوع کاربر نامعتبر است.';
        }

        // Validate selected roles
        foreach($input['roles'] as $selected_role_id) {
            $role_is_valid = false;
            foreach($available_roles as $ar) if($ar['RoleID'] == $selected_role_id) $role_is_valid = true;
            if(!$role_is_valid) {
                $errors['roles'] = "نقش انتخاب شده نامعتبر است.";
                break;
            }
        }


        if (empty($errors)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $conn->begin_transaction();
            try {
                $stmt_insert_user = $conn->prepare("INSERT INTO Users (FirstName, LastName, Username, Password, UserType, IsActive, CreatedAt, UpdatedAt) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
                if (!$stmt_insert_user) throw new Exception("خطا در آماده سازی کوئری کاربر: " . $conn->error);

                $stmt_insert_user->bind_param("sssssi", $input['firstname'], $input['lastname'], $input['username'], $hashed_password, $input['user_type'], $input['is_active']);

                if ($stmt_insert_user->execute()) {
                    $new_user_id = $stmt_insert_user->insert_id;
                    $stmt_insert_user->close();

                    if (!empty($input['roles']) && $new_user_id) {
                        $stmt_assign_role = $conn->prepare("INSERT INTO UserRoles (UserID, RoleID) VALUES (?, ?)");
                        if (!$stmt_assign_role) throw new Exception("خطا در آماده سازی کوئری نقش: " . $conn->error);

                        foreach ($input['roles'] as $role_id) {
                            $stmt_assign_role->bind_param("ii", $new_user_id, $role_id);
                            if(!$stmt_assign_role->execute()){
                                 throw new Exception("خطا در تخصیص نقش: " . $stmt_assign_role->error);
                            }
                        }
                        $stmt_assign_role->close();
                    }
                    $conn->commit();
                    // Regenerate token after successful submission to prevent reuse on back button
                    $_SESSION['csrf_token_user_create'] = bin2hex(random_bytes(32));
                    header("Location: index.php?action_status=success_create&message=کاربر با موفقیت ایجاد شد.");
                    exit;
                } else {
                    throw new Exception("خطا در ایجاد کاربر: " . $stmt_insert_user->error);
                }
            } catch (Exception $e) {
                $conn->rollback();
                $errors['db_error'] = $e->getMessage();
            }
        }
    }
    // Regenerate CSRF token after POST to prevent reuse on error/refresh if not already done on success
    if (isset($_SESSION['csrf_token_user_create'])) { // Check if it exists before overwriting
         $_SESSION['csrf_token_user_create'] = bin2hex(random_bytes(32));
         $csrf_token = $_SESSION['csrf_token_user_create'];
    }
}
?>

<div class="page-header">
    <h1>افزودن کاربر جدید</h1>
    <div class="page-header-actions">
        <a href="index.php" class="btn btn-secondary">
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
            بازگشت به لیست
        </a>
    </div>
</div>

<?php if (!empty($errors['db_error'])): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($errors['db_error']); ?></div>
<?php endif; ?>
<?php if (!empty($errors['csrf'])): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($errors['csrf']); ?></div>
<?php endif; ?>
<?php if (count(array_diff_key($errors, array_flip(['db_error', 'csrf']))) > 0 && $_SERVER["REQUEST_METHOD"] == "POST"): ?>
    <div class="alert alert-danger">لطفاً خطاهای فرم را بررسی و اصلاح کنید.</div>
<?php endif; ?>


<div class="card">
    <div class="card-body">
        <form action="create.php" method="POST" class="form-container needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="firstname">نام <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?php echo isset($errors['firstname']) ? 'is-invalid' : ''; ?>" id="firstname" name="firstname" value="<?php echo htmlspecialchars($input['firstname']); ?>" required>
                    <?php if (isset($errors['firstname'])): ?><div class="invalid-feedback"><?php echo $errors['firstname']; ?></div><?php endif; ?>
                </div>
                <div class="form-group col-md-6">
                    <label for="lastname">نام خانوادگی <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?php echo isset($errors['lastname']) ? 'is-invalid' : ''; ?>" id="lastname" name="lastname" value="<?php echo htmlspecialchars($input['lastname']); ?>" required>
                    <?php if (isset($errors['lastname'])): ?><div class="invalid-feedback"><?php echo $errors['lastname']; ?></div><?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="username">نام کاربری <span class="text-danger">*</span></label>
                <input type="text" class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" id="username" name="username" value="<?php echo htmlspecialchars($input['username']); ?>" required pattern="^[a-zA-Z0-9_]{3,30}$" aria-describedby="usernameHelp">
                <small id="usernameHelp" class="form-text text-muted">حروف انگلیسی، اعداد و زیرخط (_)، بین ۳ تا ۳۰ کاراکتر.</small>
                <?php if (isset($errors['username'])): ?><div class="invalid-feedback"><?php echo $errors['username']; ?></div><?php endif; ?>
            </div>

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="password">رمز عبور <span class="text-danger">*</span></label>
                    <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" id="password" name="password" required minlength="6">
                    <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?php echo $errors['password']; ?></div><?php endif; ?>
                </div>
                <div class="form-group col-md-6">
                    <label for="password_confirm">تکرار رمز عبور <span class="text-danger">*</span></label>
                    <input type="password" class="form-control <?php echo isset($errors['password_confirm']) ? 'is-invalid' : ''; ?>" id="password_confirm" name="password_confirm" required>
                    <?php if (isset($errors['password_confirm'])): ?><div class="invalid-feedback"><?php echo $errors['password_confirm']; ?></div><?php endif; ?>
                </div>
            </div>
            <div id="password-strength" class="password-strength mb-3"><span></span></div>


            <div class="form-group">
                <label for="user_type">نوع کاربر <span class="text-danger">*</span></label>
                <select class="form-control <?php echo isset($errors['user_type']) ? 'is-invalid' : ''; ?>" id="user_type" name="user_type" required>
                    <option value="">انتخاب کنید...</option>
                    <?php foreach ($user_type_options as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo ($input['user_type'] == $value) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['user_type'])): ?><div class="invalid-feedback"><?php echo $errors['user_type']; ?></div><?php endif; ?>
            </div>

            <div class="form-group">
                <label>نقش‌ها</label>
                <div class="form-check-group p-2">
                    <?php if (!empty($available_roles)): ?>
                        <?php foreach ($available_roles as $role): ?>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="roles[]" id="role_<?php echo $role['RoleID']; ?>" value="<?php echo $role['RoleID']; ?>" <?php echo in_array($role['RoleID'], $input['roles']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="role_<?php echo $role['RoleID']; ?>"><?php echo htmlspecialchars($role['RoleName']); ?></label>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted mb-0">هیچ نقشی تعریف نشده است. لطفاً ابتدا از بخش <a href="roles.php">مدیریت نقش‌ها</a>، نقش ایجاد کنید.</p>
                    <?php endif; ?>
                </div>
                 <?php if (isset($errors['roles'])): ?><div class="text-danger small mt-1"><?php echo $errors['roles']; ?></div><?php endif; ?>
            </div>


            <div class="form-group">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?php echo $input['is_active'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="is_active">
                        کاربر فعال باشد
                    </label>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                     <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                    <span>ذخیره کاربر</span>
                </button>
                <a href="index.php" class="btn btn-outline-secondary">انصراف</a>
            </div>
        </form>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('password');
    const strengthBarContainer = document.getElementById('password-strength');
    if (passwordInput && strengthBarContainer) {
        const strengthSpan = strengthBarContainer.querySelector('span');
        passwordInput.addEventListener('input', function() {
            const val = passwordInput.value;
            let strengthScore = 0;
            let strengthClass = '';

            if (val.length === 0) {
                 strengthSpan.style.width = '0%';
                 strengthBarContainer.className = 'password-strength';
                 return;
            }
            if (val.length >= 6) strengthScore++;
            if (val.length >= 8) strengthScore++;
            if (val.match(/[a-z]/) && val.match(/[A-Z]/)) strengthScore++;
            if (val.match(/\d/)) strengthScore++;
            if (val.match(/[^a-zA-Z\d]/)) strengthScore++; // Special character

            if (strengthScore <= 2) strengthClass = 'weak';
            else if (strengthScore <= 4) strengthClass = 'medium';
            else strengthClass = 'strong';

            strengthBarContainer.className = 'password-strength ' + strengthClass;
            strengthSpan.style.width = (strengthScore / 5 * 100) + '%';
        });
    }
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
