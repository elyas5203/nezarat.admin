<?php
require_once __DIR__ . '/../includes/header.php';

if (empty($_SESSION['csrf_token_user_edit'])) {
    $_SESSION['csrf_token_user_edit'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token_user_edit'];

$errors = [];
$user_id_to_edit = null;
$user_data_for_form = null;

$user_type_options = [
    'teacher' => 'مدرس',
    'member' => 'عضو بخش',
    'manager' => 'مدیر',
    'deputy' => 'معاون',
];

$roles_query = $conn->query("SELECT RoleID, RoleName FROM Roles ORDER BY RoleName");
$available_roles = [];
if ($roles_query) {
    while ($role = $roles_query->fetch_assoc()) {
        $available_roles[] = $role;
    }
    $roles_query->close();
}

if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $user_id_to_edit = (int)$_GET['user_id'];

    if ($user_id_to_edit === ($_SESSION['admin_user_id'] ?? null)) {
         header("Location: index.php?action_status=error&message=" . urlencode("امکان ویرایش مستقیم ادمین اصلی از این طریق وجود ندارد."));
         exit;
    }

    $stmt_fetch_user = $conn->prepare("SELECT UserID, FirstName, LastName, Username, UserType, IsActive FROM Users WHERE UserID = ?");
    if ($stmt_fetch_user) {
        $stmt_fetch_user->bind_param("i", $user_id_to_edit);
        $stmt_fetch_user->execute();
        $result_user = $stmt_fetch_user->get_result();
        if ($result_user->num_rows === 1) {
            $user_data_for_form = $result_user->fetch_assoc();
            $user_data_for_form['roles'] = [];
            $stmt_user_roles = $conn->prepare("SELECT RoleID FROM UserRoles WHERE UserID = ?");
            if ($stmt_user_roles) {
                $stmt_user_roles->bind_param("i", $user_id_to_edit);
                $stmt_user_roles->execute();
                $result_user_roles = $stmt_user_roles->get_result();
                while ($row = $result_user_roles->fetch_assoc()) {
                    $user_data_for_form['roles'][] = $row['RoleID'];
                }
                $stmt_user_roles->close();
            }
        } else { $errors['load_error'] = "کاربر یافت نشد."; }
        $stmt_fetch_user->close();
    } else { $errors['load_error'] = "خطا بارگذاری: " . $conn->error; }
} else { $errors['load_error'] = "شناسه کاربر نامعتبر."; }

// Initialize input with current user data if available, otherwise use POST data or defaults
$input_val = [
    'firstname' => $_POST['firstname'] ?? ($user_data_for_form['FirstName'] ?? ''),
    'lastname'  => $_POST['lastname']  ?? ($user_data_for_form['LastName'] ?? ''),
    'username'  => $_POST['username']  ?? ($user_data_for_form['Username'] ?? ''),
    'user_type' => $_POST['user_type'] ?? ($user_data_for_form['UserType'] ?? ''),
    'is_active' => isset($_POST['is_active']) ? 1 : (isset($user_data_for_form['IsActive']) ? $user_data_for_form['IsActive'] : 0),
    'roles'     => $_POST['roles'] ?? ($user_data_for_form['roles'] ?? [])
];
// If form was submitted and there were errors, $input_val will hold the submitted (but potentially invalid) values.
// If it's the first load, $input_val will hold $user_data_for_form values.


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token_user_edit'], $_POST['csrf_token'])) {
        $errors['csrf'] = 'خطای CSRF!';
    } elseif (!$user_id_to_edit || !$user_data_for_form) {
        $errors['form_error'] = 'خطا: اطلاعات کاربر برای ویرایش بارگذاری نشده.';
    } else {
        // $input_val is already populated with POST data if available
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        if (empty($input_val['firstname'])) $errors['firstname'] = 'نام الزامی است.';
        if (empty($input_val['lastname'])) $errors['lastname'] = 'نام خانوادگی الزامی است.';

        if (empty($input_val['username'])) {
            $errors['username'] = 'نام کاربری الزامی است.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $input_val['username'])) {
            $errors['username'] = 'نام کاربری نامعتبر.';
        } else {
            $stmt_check_username = $conn->prepare("SELECT UserID FROM Users WHERE Username = ? AND UserID != ?");
            if ($stmt_check_username) {
                $stmt_check_username->bind_param("si", $input_val['username'], $user_id_to_edit);
                $stmt_check_username->execute();
                if ($stmt_check_username->get_result()->num_rows > 0) $errors['username'] = 'این نام کاربری توسط کاربر دیگری استفاده شده.';
                $stmt_check_username->close();
            } else { $errors['db_error'] = "خطا بررسی نام کاربری: " . $conn->error; }
        }

        if (!empty($password)) {
            if (strlen($password) < 6) $errors['password'] = 'رمز عبور جدید باید حداقل ۶ کاراکتر باشد.';
            if ($password !== $password_confirm) $errors['password_confirm'] = 'تکرار رمز عبور جدید مطابقت ندارد.';
        }

        if (empty($input_val['user_type'])) $errors['user_type'] = 'نوع کاربر الزامی است.';
        elseif (!array_key_exists($input_val['user_type'], $user_type_options)) $errors['user_type'] = 'نوع کاربر نامعتبر.';

        foreach($input_val['roles'] as $selected_role_id) {
            $role_is_valid = false;
            foreach($available_roles as $ar) if($ar['RoleID'] == $selected_role_id) $role_is_valid = true;
            if(!$role_is_valid) { $errors['roles'] = "نقش نامعتبر."; break; }
        }

        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                $sql_update_user = "UPDATE Users SET FirstName = ?, LastName = ?, Username = ?, UserType = ?, IsActive = ?, UpdatedAt = NOW()";
                $types_update = "ssssi"; // For FName, LName, UName, UserType, IsActive
                $params_update = [$input_val['firstname'], $input_val['lastname'], $input_val['username'], $input_val['user_type'], $input_val['is_active']];

                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $sql_update_user .= ", Password = ?";
                    $types_update .= "s";
                    $params_update[] = $hashed_password;
                }
                $sql_update_user .= " WHERE UserID = ?";
                $types_update .= "i";
                $params_update[] = $user_id_to_edit;

                $stmt_update_user = $conn->prepare($sql_update_user);
                if(!$stmt_update_user) throw new Exception("آماده سازی آپدیت کاربر ناموفق: " . $conn->error);
                $stmt_update_user->bind_param($types_update, ...$params_update);

                if ($stmt_update_user->execute()) {
                    $stmt_update_user->close();

                    $stmt_delete_roles = $conn->prepare("DELETE FROM UserRoles WHERE UserID = ?");
                    if(!$stmt_delete_roles) throw new Exception("آماده سازی حذف نقش قبلی ناموفق: " . $conn->error);
                    $stmt_delete_roles->bind_param("i", $user_id_to_edit);
                    if(!$stmt_delete_roles->execute()) throw new Exception("حذف نقش قبلی ناموفق: " . $stmt_delete_roles->error);
                    $stmt_delete_roles->close();

                    if (!empty($input_val['roles'])) {
                        $stmt_assign_role = $conn->prepare("INSERT INTO UserRoles (UserID, RoleID) VALUES (?, ?)");
                        if(!$stmt_assign_role) throw new Exception("آماده سازی تخصیص نقش جدید ناموفق: " . $conn->error);
                        foreach ($input_val['roles'] as $role_id) {
                            $stmt_assign_role->bind_param("ii", $user_id_to_edit, $role_id);
                             if(!$stmt_assign_role->execute()) throw new Exception("تخصیص نقش جدید ناموفق: " . $stmt_assign_role->error);
                        }
                        $stmt_assign_role->close();
                    }
                    $conn->commit();
                    $_SESSION['csrf_token_user_edit'] = bin2hex(random_bytes(32));
                    header("Location: index.php?action_status=success_edit&message=" . urlencode("اطلاعات کاربر با موفقیت ویرایش شد."));
                    exit;
                } else { throw new Exception("ویرایش اطلاعات کاربر ناموفق: " . $stmt_update_user->error); }
            } catch (Exception $e) { $conn->rollback(); $errors['db_error'] = $e->getMessage(); }
        }
    }
    $_SESSION['csrf_token_user_edit'] = bin2hex(random_bytes(32)); $csrf_token = $_SESSION['csrf_token_user_edit'];
}
?>

<div class="page-header">
    <h1>ویرایش کاربر: <?php echo htmlspecialchars(($user_data_for_form['FirstName'] ?? '') . ' ' . ($user_data_for_form['LastName'] ?? '')); ?></h1>
    <div class="page-header-actions">
        <a href="index.php" class="btn btn-secondary">
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
            بازگشت به لیست
        </a>
    </div>
</div>

<?php if (!empty($errors['load_error'])): ?> <div class="alert alert-danger"><?php echo htmlspecialchars($errors['load_error']); ?></div>
<?php elseif (!empty($errors['db_error'])): ?> <div class="alert alert-danger"><?php echo htmlspecialchars($errors['db_error']); ?></div>
<?php endif; ?>
<?php if (!empty($errors['csrf'])): ?> <div class="alert alert-danger"><?php echo htmlspecialchars($errors['csrf']); ?></div>
<?php endif; ?>
<?php if (count(array_diff_key($errors, array_flip(['load_error', 'db_error', 'csrf']))) > 0 && $_SERVER["REQUEST_METHOD"] == "POST"): ?>
    <div class="alert alert-danger">لطفاً خطاهای فرم را بررسی و اصلاح کنید.</div>
<?php endif; ?>


<?php if ($user_data_for_form): ?>
<div class="card">
    <div class="card-body">
        <form action="edit.php?user_id=<?php echo $user_id_to_edit; ?>" method="POST" class="form-container needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="user_id" value="<?php echo $user_id_to_edit; ?>">

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="firstname">نام <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?php echo isset($errors['firstname']) ? 'is-invalid' : ''; ?>" id="firstname" name="firstname" value="<?php echo htmlspecialchars($input_val['firstname']); ?>" required>
                    <?php if (isset($errors['firstname'])): ?><div class="invalid-feedback"><?php echo $errors['firstname']; ?></div><?php endif; ?>
                </div>
                <div class="form-group col-md-6">
                    <label for="lastname">نام خانوادگی <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?php echo isset($errors['lastname']) ? 'is-invalid' : ''; ?>" id="lastname" name="lastname" value="<?php echo htmlspecialchars($input_val['lastname']); ?>" required>
                    <?php if (isset($errors['lastname'])): ?><div class="invalid-feedback"><?php echo $errors['lastname']; ?></div><?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="username">نام کاربری <span class="text-danger">*</span></label>
                <input type="text" class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" id="username" name="username" value="<?php echo htmlspecialchars($input_val['username']); ?>" required pattern="^[a-zA-Z0-9_]{3,30}$" aria-describedby="usernameHelp">
                <small id="usernameHelp" class="form-text text-muted">حروف انگلیسی، اعداد و زیرخط (_)، حداقل ۳ کاراکتر.</small>
                <?php if (isset($errors['username'])): ?><div class="invalid-feedback"><?php echo $errors['username']; ?></div><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password">رمز عبور جدید (اختیاری)</label>
                <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" id="password" name="password" minlength="6" aria-describedby="passwordHelpEdit">
                <small id="passwordHelpEdit" class="form-text text-muted">برای تغییر رمز عبور، مقدار جدید را وارد کنید. در غیر این صورت، خالی بگذارید.</small>
                <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?php echo $errors['password']; ?></div><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="password_confirm">تکرار رمز عبور جدید</label>
                <input type="password" class="form-control <?php echo isset($errors['password_confirm']) ? 'is-invalid' : ''; ?>" id="password_confirm" name="password_confirm">
                <?php if (isset($errors['password_confirm'])): ?><div class="invalid-feedback"><?php echo $errors['password_confirm']; ?></div><?php endif; ?>
            </div>
             <div id="password-strength-edit" class="password-strength mb-3"><span></span></div>


            <div class="form-group">
                <label for="user_type">نوع کاربر <span class="text-danger">*</span></label>
                <select class="form-control <?php echo isset($errors['user_type']) ? 'is-invalid' : ''; ?>" id="user_type" name="user_type" required>
                    <option value="">انتخاب کنید...</option>
                    <?php foreach ($user_type_options as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo ($input_val['user_type'] == $value) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['user_type'])): ?><div class="invalid-feedback"><?php echo $errors['user_type']; ?></div><?php endif; ?>
            </div>

            <div class="form-group">
                <label>نقش‌ها</label>
                <div class="form-check-group p-2 border rounded">
                    <?php if (!empty($available_roles)): ?>
                        <?php foreach ($available_roles as $role): ?>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="roles[]" id="role_edit_<?php echo $role['RoleID']; ?>" value="<?php echo $role['RoleID']; ?>" <?php echo in_array($role['RoleID'], $input_val['roles']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="role_edit_<?php echo $role['RoleID']; ?>"><?php echo htmlspecialchars($role['RoleName']); ?></label>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted mb-0">هیچ نقشی تعریف نشده است.</p>
                    <?php endif; ?>
                </div>
                 <?php if (isset($errors['roles'])): ?><div class="text-danger small mt-1"><?php echo $errors['roles']; ?></div><?php endif; ?>
            </div>

            <div class="form-group">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?php echo $input_val['is_active'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="is_active">
                        کاربر فعال باشد
                    </label>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                     <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                    <span>ذخیره تغییرات</span>
                </button>
                <a href="index.php" class="btn btn-outline-secondary">انصراف</a>
            </div>
        </form>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const passwordInputEdit = document.getElementById('password');
    const strengthBarContainerEdit = document.getElementById('password-strength-edit');
    if (passwordInputEdit && strengthBarContainerEdit) {
        const strengthSpanEdit = strengthBarContainerEdit.querySelector('span');
        passwordInputEdit.addEventListener('input', function() {
            const val = passwordInputEdit.value;
            let strengthScore = 0;
            let strengthClass = '';

            if (val.length === 0) {
                 strengthSpanEdit.style.width = '0%';
                 strengthBarContainerEdit.className = 'password-strength mb-3';
                 return;
            }
            if (val.length >= 6) strengthScore++;
            if (val.length >= 8) strengthScore++;
            if (val.match(/[a-z]/) && val.match(/[A-Z]/)) strengthScore++;
            if (val.match(/\d/)) strengthScore++;
            if (val.match(/[^a-zA-Z\d]/)) strengthScore++;

            if (strengthScore <= 2) strengthClass = 'weak';
            else if (strengthScore <= 4) strengthClass = 'medium';
            else strengthClass = 'strong';

            strengthBarContainerEdit.className = 'password-strength mb-3 ' + strengthClass;
            strengthSpanEdit.style.width = (strengthScore / 5 * 100) + '%';
        });
    }
});
</script>
<?php else: ?>
    <?php if (empty($errors['load_error'])): ?>
        <div class="alert alert-warning">اطلاعات کاربر برای ویرایش در دسترس نیست یا شناسه کاربر نامعتبر است.</div>
    <?php endif; ?>
<?php endif; ?>


<?php
require_once __DIR__ . '/../includes/footer.php';
?>
