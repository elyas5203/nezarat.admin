<?php
require_once __DIR__ . '/../includes/header.php';

// CSRF token
if (empty($_SESSION['csrf_token_roles'])) {
    $_SESSION['csrf_token_roles'] = bin2hex(random_bytes(32));
}
$csrf_token_roles = $_SESSION['csrf_token_roles'];

$errors = [];
$success_message = '';
$edit_mode = false;
$role_to_edit = ['RoleID' => null, 'RoleName' => '', 'Description' => '', 'permissions' => []];

// --- START: Permission Definitions and Database Sync ---
// Define permissions that your application will use.
// Key: internal permission string (used in code and DB)
// Value: User-friendly description (for display)
$defined_app_permissions = [
    'view_admin_dashboard' => 'مشاهده داشبورد ادمین',
    'manage_users' => 'مدیریت کامل کاربران (ایجاد، ویرایش، حذف)',
    'view_users_list' => 'مشاهده لیست کاربران',
    'create_new_user' => 'ایجاد کاربر جدید',
    'edit_user_details' => 'ویرایش اطلاعات کاربران',
    'delete_system_user' => 'حذف کاربران', // Renamed for clarity
    'manage_user_roles' => 'مدیریت نقش‌های کاربران',
    'manage_system_roles' => 'مدیریت نقش‌ها و دسترسی‌های سیستمی', // For roles.php itself
    'manage_departments_section' => 'مدیریت بخش‌های سازمانی',
    'manage_forms_module' => 'مدیریت ماژول فرم‌ها (ایجاد/ویرایش فرم)',
    'view_all_form_submissions' => 'مشاهده تمامی پاسخ‌های فرم‌ها',
    'manage_tasks_module' => 'مدیریت ماژول وظایف (ایجاد/ارجاع کلی)',
    'access_user_panel' => 'دسترسی به پنل کاربری عمومی',
    'submit_own_forms' => 'ثبت فرم‌های شخصی (مانند خوداظهاری)',
    'view_assigned_tasks' => 'مشاهده وظایف محول شده شخصی',
    'manage_parvareshi_module' => 'مدیریت ماژول پرورشی',
    'manage_avaliya_module' => 'مدیریت ماژول اولیا',
    'manage_jazb_module' => 'مدیریت ماژول جذب و راه اندازی',
    'manage_nezarat_module' => 'مدیریت ماژول نظارت',
    'manage_zemnekhdmat_module' => 'مدیریت ماژول ضمن خدمت',
    'manage_mali_module' => 'مدیریت ماژول مالی و پشتیبانی',
    'manage_mohtava_module' => 'مدیریت ماژول محتوا',
    'manage_site_settings' => 'مدیریت تنظیمات کلی سایت',
    'view_system_logs' => 'مشاهده لاگ‌های سیستمی',
];

// Sync defined_app_permissions with the Permissions table in DB
foreach ($defined_app_permissions as $p_name => $p_desc) {
    $stmt_p_check = $conn->prepare("SELECT PermissionID FROM Permissions WHERE PermissionName = ?");
    if ($stmt_p_check) {
        $stmt_p_check->bind_param("s", $p_name);
        $stmt_p_check->execute();
        $result_p_check = $stmt_p_check->get_result();
        if ($result_p_check->num_rows == 0) {
            $stmt_p_insert = $conn->prepare("INSERT INTO Permissions (PermissionName, Description) VALUES (?, ?)");
            if ($stmt_p_insert) {
                $stmt_p_insert->bind_param("ss", $p_name, $p_desc);
                $stmt_p_insert->execute();
                $stmt_p_insert->close();
            } else { $errors[] = "خطا در آماده سازی درج مجوز: " . $conn->error; }
        }
        $stmt_p_check->close();
    } else { $errors[] = "خطا در آماده سازی بررسی مجوز: " . $conn->error;}
}

// Fetch all permissions from DB to display in form
$db_permissions_query = $conn->query("SELECT PermissionID, PermissionName, Description FROM Permissions ORDER BY Description");
$db_permissions_map = []; // Map PermissionName to ID and Description
if ($db_permissions_query) {
    while($p = $db_permissions_query->fetch_assoc()){
        $db_permissions_map[$p['PermissionName']] = ['id' => $p['PermissionID'], 'description' => $p['Description']];
    }
    $db_permissions_query->data_seek(0); // Reset pointer for looping in the form
} else {
    $errors[] = "خطا در خواندن مجوزها از پایگاه داده: " . $conn->error;
}
// --- END: Permission Definitions and Database Sync ---


if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    // ... (کد مربوط به حالت ویرایش، مشابه قبل با استفاده از $db_permissions_map)
    $edit_mode = true;
    $role_id_to_edit = (int)$_GET['edit_id'];
    $stmt_edit = $conn->prepare("SELECT RoleID, RoleName, Description FROM Roles WHERE RoleID = ?");
    if ($stmt_edit) {
        $stmt_edit->bind_param("i", $role_id_to_edit);
        $stmt_edit->execute();
        $result_edit = $stmt_edit->get_result();
        if ($role_data = $result_edit->fetch_assoc()) {
            $role_to_edit = $role_data;
            $stmt_perms = $conn->prepare("SELECT p.PermissionName FROM Permissions p JOIN RolePermissions rp ON p.PermissionID = rp.PermissionID WHERE rp.RoleID = ?");
            if ($stmt_perms) {
                $stmt_perms->bind_param("i", $role_id_to_edit);
                $stmt_perms->execute();
                $perms_result = $stmt_perms->get_result();
                while ($perm = $perms_result->fetch_assoc()) {
                    $role_to_edit['permissions'][] = $perm['PermissionName'];
                }
                $stmt_perms->close();
            }
        } else { $errors[] = "نقش یافت نشد."; $edit_mode = false; }
        $stmt_edit->close();
    } else { $errors[] = "خطا: " . $conn->error; $edit_mode = false; }
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token_roles'], $_POST['csrf_token'])) {
        $errors[] = 'خطای CSRF! درخواست نامعتبر.';
    } else {
        $role_name = sanitize_input($_POST['role_name'] ?? '');
        $description = sanitize_input($_POST['description'] ?? '');
        $posted_permissions = isset($_POST['permissions']) && is_array($_POST['permissions']) ? $_POST['permissions'] : [];
        $role_id = isset($_POST['role_id']) && is_numeric($_POST['role_id']) ? (int)$_POST['role_id'] : null;

        if (empty($role_name)) $errors[] = "نام نقش الزامی است.";
        else {
            $sql_check_name = "SELECT RoleID FROM Roles WHERE RoleName = ?";
            $params_check_name = [$role_name]; $types_check_name = "s";
            if ($role_id !== null) { $sql_check_name .= " AND RoleID != ?"; $params_check_name[] = $role_id; $types_check_name .= "i"; }
            $stmt_check_name = $conn->prepare($sql_check_name);
            if ($stmt_check_name) {
                $stmt_check_name->bind_param($types_check_name, ...$params_check_name);
                $stmt_check_name->execute();
                if ($stmt_check_name->get_result()->num_rows > 0) $errors[] = "این نام نقش قبلاً استفاده شده است.";
                $stmt_check_name->close();
            } else { $errors[] = "خطا در بررسی نام نقش."; }
        }

        $valid_permission_ids = [];
        foreach ($posted_permissions as $perm_key) {
            if (isset($db_permissions_map[$perm_key])) {
                $valid_permission_ids[] = $db_permissions_map[$perm_key]['id'];
            } else { $errors[] = "مجوز نامعتبر: " . htmlspecialchars($perm_key); }
        }

        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                $current_role_id_for_perms = $role_id;
                if ($role_id !== null) { // Update
                    $stmt_role_action = $conn->prepare("UPDATE Roles SET RoleName = ?, Description = ? WHERE RoleID = ?");
                    if (!$stmt_role_action) throw new Exception("آماده سازی آپدیت نقش ناموفق: " . $conn->error);
                    $stmt_role_action->bind_param("ssi", $role_name, $description, $role_id);
                    $success_message = "نقش با موفقیت ویرایش شد.";
                } else { // Create
                    $stmt_role_action = $conn->prepare("INSERT INTO Roles (RoleName, Description) VALUES (?, ?)");
                    if (!$stmt_role_action) throw new Exception("آماده سازی ایجاد نقش ناموفق: " . $conn->error);
                    $stmt_role_action->bind_param("ss", $role_name, $description);
                    $success_message = "نقش با موفقیت ایجاد شد.";
                }
                if (!$stmt_role_action->execute()) throw new Exception("اجرای عملیات نقش ناموفق: " . $stmt_role_action->error);
                if ($role_id === null) $current_role_id_for_perms = $stmt_role_action->insert_id;
                $stmt_role_action->close();

                // Update RolePermissions
                $stmt_delete_perms = $conn->prepare("DELETE FROM RolePermissions WHERE RoleID = ?");
                if (!$stmt_delete_perms) throw new Exception("آماده سازی حذف مجوزهای قبلی ناموفق: " . $conn->error);
                $stmt_delete_perms->bind_param("i", $current_role_id_for_perms);
                if (!$stmt_delete_perms->execute()) throw new Exception("حذف مجوزهای قبلی ناموفق: " . $stmt_delete_perms->error);
                $stmt_delete_perms->close();

                if (!empty($valid_permission_ids)) {
                    $stmt_assign_perm = $conn->prepare("INSERT INTO RolePermissions (RoleID, PermissionID) VALUES (?, ?)");
                    if (!$stmt_assign_perm) throw new Exception("آماده سازی تخصیص مجوز ناموفق: " . $conn->error);
                    foreach ($valid_permission_ids as $perm_id_db) {
                        $stmt_assign_perm->bind_param("ii", $current_role_id_for_perms, $perm_id_db);
                        if (!$stmt_assign_perm->execute()) throw new Exception("تخصیص مجوز ID " . $perm_id_db . " ناموفق: " . $stmt_assign_perm->error);
                    }
                    $stmt_assign_perm->close();
                }
                $conn->commit();
                $edit_mode = false; $role_to_edit = ['RoleID' => null, 'RoleName' => '', 'Description' => '', 'permissions' => []];
            } catch (Exception $e) { $conn->rollback(); $errors[] = $e->getMessage(); }
        }
    }
    $_SESSION['csrf_token_roles'] = bin2hex(random_bytes(32)); $csrf_token_roles = $_SESSION['csrf_token_roles'];
}

if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    // ... (کد مربوط به حذف، مشابه قبل)
    if (!isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token_roles'], $_GET['csrf_token'])) {
        $errors[] = 'خطای CSRF برای حذف!';
    } else {
        $role_id_to_delete = (int)$_GET['delete_id'];
        $stmt_check_assign = $conn->prepare("SELECT COUNT(UserID) as count FROM UserRoles WHERE RoleID = ?");
        if ($stmt_check_assign) {
            $stmt_check_assign->bind_param("i", $role_id_to_delete);
            $stmt_check_assign->execute();
            $assign_count = $stmt_check_assign->get_result()->fetch_assoc()['count'] ?? 0;
            $stmt_check_assign->close();
            if ($assign_count > 0) {
                $errors[] = "این نقش به ".$assign_count." کاربر تخصیص داده شده و قابل حذف نیست.";
            } else {
                $conn->begin_transaction();
                try {
                    $stmt_delete_rp = $conn->prepare("DELETE FROM RolePermissions WHERE RoleID = ?");
                    if(!$stmt_delete_rp) throw new Exception("خطا RolePermissions: " . $conn->error);
                    $stmt_delete_rp->bind_param("i", $role_id_to_delete);
                    if(!$stmt_delete_rp->execute()) throw new Exception("خطا حذف RolePermissions: " . $stmt_delete_rp->error);
                    $stmt_delete_rp->close();

                    $stmt_delete_role = $conn->prepare("DELETE FROM Roles WHERE RoleID = ?");
                    if(!$stmt_delete_role) throw new Exception("خطا Roles: " . $conn->error);
                    $stmt_delete_role->bind_param("i", $role_id_to_delete);
                    if($stmt_delete_role->execute()){ $conn->commit(); $success_message = "نقش حذف شد."; }
                    else { throw new Exception("خطا حذف Roles: " . $stmt_delete_role->error); }
                    $stmt_delete_role->close();
                } catch (Exception $e) { $conn->rollback(); $errors[] = $e->getMessage(); }
            }
        } else { $errors[] = "خطا در بررسی تخصیص نقش."; }
    }
    $_SESSION['csrf_token_roles'] = bin2hex(random_bytes(32)); $csrf_token_roles = $_SESSION['csrf_token_roles'];
}

$roles_list_query = $conn->query("SELECT RoleID, RoleName, Description FROM Roles ORDER BY RoleName");
?>

<div class="page-header">
    <h1>مدیریت نقش‌ها و دسترسی‌ها</h1>
    <div class="page-header-actions">
        <a href="index.php" class="btn btn-secondary">
             <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
            بازگشت به کاربران
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<?php if ($success_message): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-5 mb-4">
        <div class="card">
            <div class="card-header">
                <span class="card-title-text"><?php echo $edit_mode ? 'ویرایش نقش: ' . htmlspecialchars($role_to_edit['RoleName']) : 'افزودن نقش جدید'; ?></span>
            </div>
            <div class="card-body">
                <form action="roles.php<?php echo $edit_mode ? '?edit_id='.$role_to_edit['RoleID'] : ''; ?>" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_roles; ?>">
                    <?php if ($edit_mode): ?><input type="hidden" name="role_id" value="<?php echo $role_to_edit['RoleID']; ?>"><?php endif; ?>

                    <div class="form-group">
                        <label for="role_name">نام نقش <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="role_name" name="role_name" value="<?php echo htmlspecialchars($role_to_edit['RoleName']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="description">توضیحات</label>
                        <textarea class="form-control" id="description" name="description" rows="2"><?php echo htmlspecialchars($role_to_edit['Description']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>مجوزها</label>
                        <div class="form-check-group p-2 border rounded" style="max-height: 300px; overflow-y: auto;">
                            <?php if ($db_permissions_query && $db_permissions_query->num_rows > 0): $db_permissions_query->data_seek(0); ?>
                                <?php while($perm = $db_permissions_query->fetch_assoc()): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="permissions[]" id="perm_<?php echo $perm['PermissionName']; ?>" value="<?php echo $perm['PermissionName']; ?>" <?php echo in_array($perm['PermissionName'], $role_to_edit['permissions']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="perm_<?php echo $perm['PermissionName']; ?>">
                                            <?php echo htmlspecialchars($perm['Description']); ?> (<code><?php echo $perm['PermissionName']; ?></code>)
                                        </label>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p class="text-muted mb-0">هیچ مجوزی در سیستم تعریف نشده یا خطایی رخ داده است.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                            <span><?php echo $edit_mode ? 'ذخیره تغییرات' : 'ایجاد نقش'; ?></span>
                        </button>
                        <?php if ($edit_mode): ?>
                            <a href="roles.php" class="btn btn-outline-secondary">لغو ویرایش</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><span class="card-title-text">لیست نقش‌ها</span></div>
            <div class="card-body">
                <?php if ($roles_list_query && $roles_list_query->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>نام نقش</th>
                                    <th>توضیحات</th>
                                    <th>کاربران</th>
                                    <th class="actions-column">عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $role_row_num = 1; while ($role = $roles_list_query->fetch_assoc()):
                                    $stmt_count_users = $conn->prepare("SELECT COUNT(UserID) as user_count FROM UserRoles WHERE RoleID = ?");
                                    $user_count = 0;
                                    if($stmt_count_users){
                                        $stmt_count_users->bind_param("i", $role['RoleID']);
                                        $stmt_count_users->execute();
                                        $user_count = $stmt_count_users->get_result()->fetch_assoc()['user_count'] ?? 0;
                                        $stmt_count_users->close();
                                    }
                                ?>
                                <tr>
                                    <td><?php echo $role_row_num++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($role['RoleName']); ?></strong></td>
                                    <td class="small"><?php echo !empty($role['Description']) ? nl2br(htmlspecialchars($role['Description'])) : '-'; ?></td>
                                    <td><span class="badge badge-info"><?php echo $user_count; ?></span></td>
                                    <td class="actions-cell">
                                        <a href="roles.php?edit_id=<?php echo $role['RoleID']; ?>" class="btn btn-sm btn-warning" title="ویرایش">
                                            <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                        </a>
                                        <a href="roles.php?delete_id=<?php echo $role['RoleID']; ?>&csrf_token=<?php echo $csrf_token_roles; ?>"
                                           class="btn btn-sm btn-danger <?php echo ($user_count > 0 || $role['RoleName'] === 'Super Admin') ? 'disabled' : ''; ?>"
                                           title="<?php echo ($user_count > 0) ? 'این نقش به کاربر تخصیص داده شده' : (($role['RoleName'] === 'Super Admin') ? 'این نقش سیستمی قابل حذف نیست' : 'حذف'); ?>"
                                           onclick="if(<?php echo $user_count; ?> > 0 || '<?php echo $role['RoleName']; ?>' === 'Super Admin') { alert(this.title); return false; } return confirm('آیا از حذف این نقش مطمئن هستید؟');">
                                            <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">هیچ نقشی تعریف نشده است.</div>
                <?php endif; if($roles_list_query) $roles_list_query->close(); if($db_permissions_query) $db_permissions_query->close(); ?>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
