<?php
require_once __DIR__ . '/../includes/header.php';

$csrf_token_dep_edit = generate_csrf_token('department_edit_form');

$errors = [];
$department_id_to_edit = null;
$department_data_for_form = null;

$manager_users_query = $conn->query("
    SELECT UserID, FirstName, LastName, Username
    FROM Users
    WHERE UserType IN ('manager', 'deputy', 'admin') AND IsActive = TRUE
    ORDER BY LastName, FirstName
");
$available_managers = [];
if ($manager_users_query) {
    while ($mu = $manager_users_query->fetch_assoc()) {
        $available_managers[] = $mu;
    }
    $manager_users_query->close();
}

if (isset($_GET['dep_id']) && is_numeric($_GET['dep_id'])) {
    $department_id_to_edit = (int)$_GET['dep_id'];
    $stmt_fetch_dept = $conn->prepare("SELECT DepartmentID, DepartmentName, Description FROM Departments WHERE DepartmentID = ?");
    if ($stmt_fetch_dept) {
        $stmt_fetch_dept->bind_param("i", $department_id_to_edit);
        $stmt_fetch_dept->execute();
        $result_dept = $stmt_fetch_dept->get_result();
        if ($result_dept->num_rows === 1) {
            $department_data_for_form = $result_dept->fetch_assoc();
            $department_data_for_form['managers'] = [];
            $stmt_dept_managers = $conn->prepare("SELECT UserID FROM UserDepartments WHERE DepartmentID = ? AND IsManager = TRUE");
            if ($stmt_dept_managers) {
                $stmt_dept_managers->bind_param("i", $department_id_to_edit);
                $stmt_dept_managers->execute();
                $result_dept_managers = $stmt_dept_managers->get_result();
                while ($row = $result_dept_managers->fetch_assoc()) {
                    $department_data_for_form['managers'][] = $row['UserID'];
                }
                $stmt_dept_managers->close();
            }
        } else { $errors['load_error'] = "بخش یافت نشد."; }
        $stmt_fetch_dept->close();
    } else { $errors['load_error'] = "خطا بارگذاری: " . $conn->error; }
} else { $errors['load_error'] = "شناسه بخش نامعتبر."; }

$input_val = [
    'department_name' => $_POST['department_name'] ?? ($department_data_for_form['DepartmentName'] ?? ''),
    'description'     => $_POST['description']     ?? ($department_data_for_form['Description'] ?? ''),
    'managers'        => $_POST['managers'] ?? ($department_data_for_form['managers'] ?? [])
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'department_edit_form')) {
        $errors['csrf'] = 'خطای CSRF!';
    } elseif (!$department_id_to_edit || !$department_data_for_form) {
        $errors['form_error'] = 'خطا: اطلاعات بخش بارگذاری نشده.';
    } else {
        if (empty($input_val['department_name'])) {
            $errors['department_name'] = 'نام بخش الزامی است.';
        } else {
            $stmt_check_name = $conn->prepare("SELECT DepartmentID FROM Departments WHERE DepartmentName = ? AND DepartmentID != ?");
            if ($stmt_check_name) {
                $stmt_check_name->bind_param("si", $input_val['department_name'], $department_id_to_edit);
                $stmt_check_name->execute();
                if ($stmt_check_name->get_result()->num_rows > 0) {
                    $errors['department_name'] = 'بخشی دیگری با این نام قبلاً ثبت شده.';
                }
                $stmt_check_name->close();
            } else { $errors['db_error'] = "خطا بررسی نام بخش: " . $conn->error; }
        }

        foreach($input_val['managers'] as $manager_id) {
            $manager_exists = false;
            foreach($available_managers as $am) { if($am['UserID'] == $manager_id) {$manager_exists = true; break;} }
            if(!$manager_exists) { $errors['managers'] = "مدیر نامعتبر."; break; }
        }

        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                $stmt_update_dept = $conn->prepare("UPDATE Departments SET DepartmentName = ?, Description = ? WHERE DepartmentID = ?");
                if (!$stmt_update_dept) throw new Exception("خطا آماده سازی آپدیت: " . $conn->error);
                $stmt_update_dept->bind_param("ssi", $input_val['department_name'], $input_val['description'], $department_id_to_edit);

                if ($stmt_update_dept->execute()) {
                    $stmt_update_dept->close();

                    $stmt_delete_managers = $conn->prepare("DELETE FROM UserDepartments WHERE DepartmentID = ? AND IsManager = TRUE");
                    if (!$stmt_delete_managers) throw new Exception("خطا آماده سازی حذف مدیران قبلی: " . $conn->error);
                    $stmt_delete_managers->bind_param("i", $department_id_to_edit);
                    if (!$stmt_delete_managers->execute()) throw new Exception("خطا حذف مدیران قبلی: " . $stmt_delete_managers->error);
                    $stmt_delete_managers->close();

                    if (!empty($input_val['managers'])) {
                        $stmt_assign_manager = $conn->prepare("INSERT INTO UserDepartments (UserID, DepartmentID, IsManager) VALUES (?, ?, TRUE)");
                        if (!$stmt_assign_manager) throw new Exception("خطا آماده سازی تخصیص مدیر جدید: " . $conn->error);
                        foreach ($input_val['managers'] as $manager_user_id) {
                            $stmt_assign_manager->bind_param("ii", $manager_user_id, $department_id_to_edit);
                            if (!$stmt_assign_manager->execute() && $conn->errno !== 1062) {
                                throw new Exception("خطا تخصیص مدیر جدید: " . $stmt_assign_manager->error);
                            }
                        }
                        $stmt_assign_manager->close();
                    }
                    $conn->commit();
                    regenerate_csrf_token('department_edit_form');
                    header("Location: index.php?action_status=success_edit&message=" . urlencode("بخش با موفقیت ویرایش شد."));
                    exit;
                } else { throw new Exception("خطا در ویرایش بخش: " . $stmt_update_dept->error); }
            } catch (Exception $e) { $conn->rollback(); $errors['db_error'] = $e->getMessage(); }
        }
    }
    $csrf_token_dep_edit = regenerate_csrf_token('department_edit_form');
}
?>

<div class="page-header">
    <h1>ویرایش بخش: <?php echo htmlspecialchars($department_data_for_form['DepartmentName'] ?? '...'); ?></h1>
    <div class="page-header-actions">
        <a href="index.php" class="btn btn-secondary">
             <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
            <span>بازگشت به لیست</span>
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

<?php if ($department_data_for_form): ?>
<div class="card">
    <div class="card-body">
        <form action="edit.php?dep_id=<?php echo $department_id_to_edit; ?>" method="POST" class="form-container needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_dep_edit; ?>">
            <input type="hidden" name="dep_id" value="<?php echo $department_id_to_edit; ?>">

            <div class="form-group">
                <label for="department_name">نام بخش <span class="text-danger">*</span></label>
                <input type="text" class="form-control <?php echo isset($errors['department_name']) ? 'is-invalid' : ''; ?>" id="department_name" name="department_name" value="<?php echo htmlspecialchars($input_val['department_name']); ?>" required>
                <?php if (isset($errors['department_name'])): ?><div class="invalid-feedback"><?php echo $errors['department_name']; ?></div><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="description">توضیحات</label>
                <textarea class="form-control <?php echo isset($errors['description']) ? 'is-invalid' : ''; ?>" id="description" name="description" rows="3"><?php echo htmlspecialchars($input_val['description']); ?></textarea>
                <?php if (isset($errors['description'])): ?><div class="invalid-feedback"><?php echo $errors['description']; ?></div><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="managers_edit">مدیر(ان) بخش</label>
                <select class="form-control <?php echo isset($errors['managers']) ? 'is-invalid' : ''; ?>" id="managers_edit" name="managers[]" multiple data-placeholder="یک یا چند مدیر انتخاب کنید...">
                     <?php if (!empty($available_managers)): ?>
                        <?php foreach ($available_managers as $manager): ?>
                            <option value="<?php echo $manager['UserID']; ?>" <?php echo in_array($manager['UserID'], $input_val['managers']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($manager['FirstName'] . ' ' . $manager['LastName'] . ' (@' . $manager['Username'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="" disabled>هیچ کاربری با نقش مناسب برای مدیریت یافت نشد.</option>
                    <?php endif; ?>
                </select>
                <small class="form-text text-muted">برای انتخاب چند مدیر، کلید Ctrl (یا Cmd در مک) را نگه دارید و کلیک کنید.</small>
                <?php if (isset($errors['managers'])): ?><div class="invalid-feedback d-block"><?php echo $errors['managers']; ?></div><?php endif; ?>
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
<?php else: ?>
    <?php if (empty($errors['load_error'])): ?>
    <div class="alert alert-warning">اطلاعات بخش برای ویرایش در دسترس نیست.</div>
    <?php endif; ?>
<?php endif; ?>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
