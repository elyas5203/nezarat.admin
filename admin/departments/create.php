<?php
require_once __DIR__ . '/../includes/header.php';

$dept_name = '';
$dept_description = '';
$form_errors = [];

$csrf_token = generate_csrf_token('create_department');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'], 'create_department')) {
        $form_errors['csrf'] = 'خطای امنیتی CSRF. لطفاً صفحه را رفرش کرده و مجدداً تلاش کنید.';
    } else {
        $csrf_token = regenerate_csrf_token('create_department'); // Regenerate after use

        $dept_name = sanitize_input($_POST['department_name'] ?? '');
        $dept_description = sanitize_input($_POST['department_description'] ?? '');

        // Validation
        if (empty($dept_name)) {
            $form_errors['department_name'] = 'نام بخش نمی‌تواند خالی باشد.';
        } elseif (strlen($dept_name) > 100) {
            $form_errors['department_name'] = 'نام بخش نمی‌تواند بیشتر از 100 کاراکتر باشد.';
        } else {
            // Check if department name already exists
            if ($conn) {
                $stmt_check_name = $conn->prepare("SELECT DepartmentID FROM Departments WHERE DepartmentName = ?");
                if ($stmt_check_name) {
                    $stmt_check_name->bind_param("s", $dept_name);
                    $stmt_check_name->execute();
                    if ($stmt_check_name->get_result()->num_rows > 0) {
                        $form_errors['department_name'] = 'بخشی با این نام قبلاً ثبت شده است.';
                    }
                    $stmt_check_name->close();
                } else {
                    $form_errors['db_error'] = 'خطا در بررسی نام بخش: ' . $conn->error;
                }
            } else {
                 $form_errors['db_error'] = 'خطا در اتصال به پایگاه داده.';
            }
        }

        if (strlen($dept_description) > 1000) { // Max length for description
            $form_errors['department_description'] = 'توضیحات بخش نمی‌تواند بیشتر از 1000 کاراکتر باشد.';
        }

        if (empty($form_errors)) {
            if ($conn) {
                $stmt_insert = $conn->prepare("INSERT INTO Departments (DepartmentName, Description, CreatedAt) VALUES (?, ?, NOW())");
                if ($stmt_insert) {
                    $stmt_insert->bind_param("ss", $dept_name, $dept_description);
                    if ($stmt_insert->execute()) {
                        $_SESSION['action_success'] = "بخش '" . htmlspecialchars($dept_name) . "' با موفقیت ایجاد شد.";
                        header("Location: index.php");
                        exit;
                    } else {
                        $form_errors['db_error'] = "خطا در ایجاد بخش: " . $stmt_insert->error;
                    }
                    $stmt_insert->close();
                } else {
                    $form_errors['db_error'] = "خطا در آماده سازی کوئری ایجاد بخش: " . $conn->error;
                }
            } else {
                 $form_errors['db_error'] = 'خطا در اتصال به پایگاه داده برای ذخیره بخش.';
            }
        }
    }
}
?>

<div class="page-header">
    <h1>افزودن بخش جدید</h1>
    <div class="page-header-actions">
        <a href="index.php" class="btn btn-secondary">
             <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-right-circle icon" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8zm15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-4.5-.5a.5.5 0 0 0 0 1h5.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3a.5.5 0 0 0 0-.708l-3-3a.5.5 0 1 0-.708.708L11.293 7.5H6.5a.5.5 0 0 0 0 1h4.793z"/>
            </svg>
            بازگشت به لیست بخش‌ها
        </a>
    </div>
</div>

<?php if (!empty($form_errors['csrf'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $form_errors['csrf']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if (!empty($form_errors['db_error']) && empty($form_errors['department_name']) /* Only show general DB error if not specific field error */): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $form_errors['db_error']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="form-container needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div class="form-group mb-3">
                <label for="department_name" class="form-label">نام بخش <span class="text-danger">*</span></label>
                <input type="text" class="form-control <?php echo isset($form_errors['department_name']) || (isset($form_errors['db_error']) && strpos($form_errors['db_error'], $dept_name) !==false && !empty($dept_name) ) ? 'is-invalid' : ''; ?>"
                       id="department_name" name="department_name" value="<?php echo htmlspecialchars($dept_name); ?>" required>
                <?php if (isset($form_errors['department_name'])): ?>
                    <div class="invalid-feedback"><?php echo $form_errors['department_name']; ?></div>
                <?php elseif (isset($form_errors['db_error']) && strpos($form_errors['db_error'], $dept_name) !==false && !empty($dept_name)): ?>
                     <div class="invalid-feedback"><?php echo $form_errors['db_error']; ?></div> <!-- Show DB error related to name if it exists -->
                <?php endif; ?>
            </div>

            <div class="form-group mb-3">
                <label for="department_description" class="form-label">توضیحات</label>
                <textarea class="form-control <?php echo isset($form_errors['department_description']) ? 'is-invalid' : ''; ?>"
                          id="department_description" name="department_description" rows="4"><?php echo htmlspecialchars($dept_description); ?></textarea>
                <?php if (isset($form_errors['department_description'])): ?>
                    <div class="invalid-feedback"><?php echo $form_errors['department_description']; ?></div>
                <?php endif; ?>
                <small class="form-text text-muted">توضیح مختصری درباره وظایف و مسئولیت‌های این بخش (اختیاری، حداکثر ۱۰۰۰ کاراکتر).</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">ایجاد بخش</button>
                <a href="index.php" class="btn btn-secondary">انصراف</a>
            </div>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
