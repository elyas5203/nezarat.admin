<?php
require_once __DIR__ . '/../includes/header.php';

$csrf_token_dep_create = generate_csrf_token('department_create_form');

$errors = [];
$input_val = ['department_name' => '', 'description' => '', 'managers' => []];

// Fetch potential managers
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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Populate $input_val with POST data first for repopulation
    $input_val['department_name'] = sanitize_input($_POST['department_name'] ?? '');
    $input_val['description'] = sanitize_input($_POST['description'] ?? '');
    $input_val['managers'] = isset($_POST['managers']) && is_array($_POST['managers']) ? array_map('intval', $_POST['managers']) : [];


    if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'department_create_form')) {
        $errors['csrf'] = 'خطای CSRF! درخواست نامعتبر.';
    } else {
        // Validation
        if (empty($input_val['department_name'])) {
            $errors['department_name'] = 'نام بخش الزامی است.';
        } else {
            $stmt_check_name = $conn->prepare("SELECT DepartmentID FROM Departments WHERE DepartmentName = ?");
            if ($stmt_check_name) {
                $stmt_check_name->bind_param("s", $input_val['department_name']);
                $stmt_check_name->execute();
                if ($stmt_check_name->get_result()->num_rows > 0) {
                    $errors['department_name'] = 'بخشی با این نام قبلاً ثبت شده است.';
                }
                $stmt_check_name->close();
            } else {
                $errors['db_error'] = "خطا در بررسی نام بخش: " . $conn->error;
            }
        }

        foreach($input_val['managers'] as $manager_id) {
            $manager_exists = false;
            foreach($available_managers as $am) {
                if($am['UserID'] == $manager_id) { $manager_exists = true; break; }
            }
            if(!$manager_exists) { $errors['managers'] = "مدیر انتخاب شده نامعتبر است."; break; }
        }

        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                $stmt_insert_dept = $conn->prepare("INSERT INTO Departments (DepartmentName, Description) VALUES (?, ?)");
                if (!$stmt_insert_dept) throw new Exception("خطا آماده سازی: " . $conn->error);

                $stmt_insert_dept->bind_param("ss", $input_val['department_name'], $input_val['description']);
                if ($stmt_insert_dept->execute()) {
                    $new_department_id = $stmt_insert_dept->insert_id;
                    $stmt_insert_dept->close();

                    if (!empty($input_val['managers']) && $new_department_id) {
                        $stmt_assign_manager = $conn->prepare("INSERT INTO UserDepartments (UserID, DepartmentID, IsManager) VALUES (?, ?, TRUE)");
                        if (!$stmt_assign_manager) throw new Exception("خطا آماده سازی تخصیص مدیر: " . $conn->error);

                        foreach ($input_val['managers'] as $manager_user_id) {
                            $stmt_assign_manager->bind_param("ii", $manager_user_id, $new_department_id);
                            if (!$stmt_assign_manager->execute() && $conn->errno !== 1062) { // 1062 is duplicate entry
                                 throw new Exception("خطا تخصیص مدیر: " . $stmt_assign_manager->error);
                            }
                        }
                        $stmt_assign_manager->close();
                    }
                    $conn->commit();
                    regenerate_csrf_token('department_create_form');
                    header("Location: index.php?action_status=success_create&message=" . urlencode("بخش با موفقیت ایجاد شد."));
                    exit;
                } else { throw new Exception("خطا ایجاد بخش: " . $stmt_insert_dept->error); }
            } catch (Exception $e) { $conn->rollback(); $errors['db_error'] = $e->getMessage(); }
        }
    }
    $csrf_token_dep_create = regenerate_csrf_token('department_create_form');
}
?>

<div class="page-header">
    <h1>افزودن بخش سازمانی جدید</h1>
    <div class="page-header-actions">
        <a href="index.php" class="btn btn-secondary">
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
            <span>بازگشت به لیست</span>
        </a>
    </div>
</div>

<?php if (!empty($errors['db_error'])): ?> <div class="alert alert-danger"><?php echo htmlspecialchars($errors['db_error']); ?></div> <?php endif; ?>
<?php if (!empty($errors['csrf'])): ?> <div class="alert alert-danger"><?php echo htmlspecialchars($errors['csrf']); ?></div> <?php endif; ?>
<?php if (count(array_diff_key($errors, array_flip(['db_error', 'csrf']))) > 0 && $_SERVER["REQUEST_METHOD"] == "POST"): ?>
    <div class="alert alert-danger">لطفاً خطاهای فرم را بررسی و اصلاح کنید.</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form action="create.php" method="POST" class="form-container needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_dep_create; ?>">

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
                <label for="managers">مدیر(ان) بخش</label>
                <select class="form-control <?php echo isset($errors['managers']) ? 'is-invalid' : ''; ?>" id="managers" name="managers[]" multiple data-placeholder="یک یا چند مدیر انتخاب کنید...">
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
                <small class="form-text text-muted">می‌توانید یک یا چند مدیر برای این بخش انتخاب کنید (با نگه داشتن Ctrl یا Cmd و کلیک).</small>
                <?php if (isset($errors['managers'])): ?><div class="invalid-feedback d-block"><?php echo $errors['managers']; ?></div><?php endif; ?>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                     <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                    <span>ایجاد بخش</span>
                </button>
                <a href="index.php" class="btn btn-outline-secondary">انصراف</a>
            </div>
        </form>
    </div>
</div>
<!-- Placeholder for Select2 or Choices.js initialization if you add them -->
<!--
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
        $('#managers').select2({
            placeholder: "یک یا چند مدیر انتخاب کنید...",
            language: "fa",
            dir: "rtl",
            width: '100%' // Ensures it fits the form-control style
        });
    } else {
        console.warn('Select2 library not loaded or jQuery not available.');
    }
});
</script>
-->
<?php
require_once __DIR__ . '/../includes/footer.php';
?>
