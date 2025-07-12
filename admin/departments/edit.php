<?php
require_once __DIR__ . '/../includes/header.php';

$dept_id_to_edit = isset($_GET['dept_id']) ? (int)$_GET['dept_id'] : 0;

$dept_name = '';
$dept_description = '';
$original_dept_name = ''; // To check if name has changed for uniqueness validation
$form_errors = [];

if ($dept_id_to_edit <= 0) {
    $_SESSION['action_error'] = 'شناسه بخش نامعتبر است.';
    header("Location: index.php");
    exit;
}

// CSRF token
$csrf_token_name = 'edit_department_' . $dept_id_to_edit;
$csrf_token = generate_csrf_token($csrf_token_name);

// Fetch department data for editing
if ($conn) {
    $stmt_fetch = $conn->prepare("SELECT DepartmentName, Description FROM Departments WHERE DepartmentID = ?");
    if ($stmt_fetch) {
        $stmt_fetch->bind_param("i", $dept_id_to_edit);
        $stmt_fetch->execute();
        $result_fetch = $stmt_fetch->get_result();
        if ($dept_data = $result_fetch->fetch_assoc()) {
            $dept_name = $dept_data['DepartmentName'];
            $original_dept_name = $dept_data['DepartmentName']; // Store original name for display in title and validation
            $dept_description = $dept_data['Description'];
        } else {
            $_SESSION['action_error'] = 'بخش با شناسه ' . htmlspecialchars($dept_id_to_edit) . ' یافت نشد.';
            header("Location: index.php");
            exit;
        }
        $stmt_fetch->close();
    } else {
        // This error will be shown in the general db_error display on the form
        $form_errors['db_error'] = 'خطا در آماده سازی کوئری بارگذاری بخش: ' . $conn->error;
    }
} else {
    $form_errors['db_error'] = 'خطا در اتصال به پایگاه داده.';
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'], $csrf_token_name)) {
        $form_errors['csrf'] = 'خطای امنیتی CSRF. لطفاً صفحه را رفرش کرده و مجدداً تلاش کنید.';
    } else {
        $csrf_token = regenerate_csrf_token($csrf_token_name); // Regenerate

        // Values from POST, default to current values if not set (though they should be)
        $new_dept_name = sanitize_input($_POST['department_name'] ?? $dept_name);
        $new_dept_description = sanitize_input($_POST['department_description'] ?? $dept_description);

        // Validation
        if (empty($new_dept_name)) {
            $form_errors['department_name'] = 'نام بخش نمی‌تواند خالی باشد.';
        } elseif (strlen($new_dept_name) > 100) {
            $form_errors['department_name'] = 'نام بخش نمی‌تواند بیشتر از 100 کاراکتر باشد.';
        } elseif ($new_dept_name !== $original_dept_name) { // Check for uniqueness only if name changed
            if ($conn) {
                $stmt_check_name_edit = $conn->prepare("SELECT DepartmentID FROM Departments WHERE DepartmentName = ? AND DepartmentID != ?");
                if ($stmt_check_name_edit) {
                    $stmt_check_name_edit->bind_param("si", $new_dept_name, $dept_id_to_edit);
                    $stmt_check_name_edit->execute();
                    if ($stmt_check_name_edit->get_result()->num_rows > 0) {
                        $form_errors['department_name'] = 'بخشی دیگر با این نام قبلاً ثبت شده است.';
                    }
                    $stmt_check_name_edit->close();
                } else {
                     $form_errors['db_error'] = 'خطا در بررسی نام بخش هنگام ویرایش: ' . $conn->error;
                }
            }
        }

        if (strlen($new_dept_description) > 1000) {
            $form_errors['department_description'] = 'توضیحات بخش نمی‌تواند بیشتر از 1000 کاراکتر باشد.';
        }

        if (empty($form_errors)) {
            if ($conn) {
                // Only update if there are actual changes to avoid unnecessary DB call
                if ($new_dept_name !== $original_dept_name || $new_dept_description !== $dept_data['Description'] /* Compare with original fetched description */ ) {
                    $stmt_update = $conn->prepare("UPDATE Departments SET DepartmentName = ?, Description = ? WHERE DepartmentID = ?");
                    if ($stmt_update) {
                        $stmt_update->bind_param("ssi", $new_dept_name, $new_dept_description, $dept_id_to_edit);
                        if ($stmt_update->execute()) {
                            $_SESSION['action_success'] = "بخش '" . htmlspecialchars($new_dept_name) . "' با موفقیت بروزرسانی شد.";
                            header("Location: index.php");
                            exit;
                        } else {
                            $form_errors['db_error'] = "خطا در بروزرسانی بخش: " . $stmt_update->error;
                        }
                        $stmt_update->close();
                    } else {
                        $form_errors['db_error'] = "خطا در آماده سازی کوئری بروزرسانی بخش: " . $conn->error;
                    }
                } else {
                     // If no actual change, redirect back or show an info message
                     $_SESSION['action_info'] = "هیچ تغییری برای ذخیره در بخش '" . htmlspecialchars($original_dept_name) . "' وجود نداشت.";
                     header("Location: index.php");
                     exit;
                }
            } else {
                 $form_errors['db_error'] = 'خطا در اتصال به پایگاه داده برای بروزرسانی بخش.';
            }
        }
        // If validation errors, update form fields to show submitted (erroneous) values for correction
        $dept_name = $new_dept_name; // This will be used to prefill the form again
        $dept_description = $new_dept_description;
    }
}
?>

<div class="page-header">
    <h1>ویرایش بخش: <?php echo htmlspecialchars($original_dept_name); // Display original name in title ?></h1>
    <div class="page-header-actions">
        <a href="index.php" class="btn btn-secondary">
             <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-right-circle icon" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8zm15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-4.5-.5a.5.5 0 0 0 0 1h5.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3a.5.5 0 0 0 0-.708l-3-3a.5.5 0 1 0-.708.708L11.293 7.5H6.5a.5.5 0 0 0 0 1h4.793z"/>
            </svg>
            بازگشت به لیست بخش‌ها
        </a>
    </div>
</div>

<?php if (isset($_SESSION['action_info'])): // For messages like "no changes" ?>
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['action_info']; unset($_SESSION['action_info']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if (!empty($form_errors['csrf'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $form_errors['csrf']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if (!empty($form_errors['db_error']) && empty($form_errors['department_name']) ): /* Show general DB error if not field specific */ ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $form_errors['db_error']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?dept_id=" . $dept_id_to_edit; ?>" class="form-container needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div class="form-group mb-3">
                <label for="department_name" class="form-label">نام بخش <span class="text-danger">*</span></label>
                <input type="text" class="form-control <?php echo isset($form_errors['department_name']) ? 'is-invalid' : ''; ?>"
                       id="department_name" name="department_name" value="<?php echo htmlspecialchars($dept_name); // Use $dept_name which holds current value for form ?>" required>
                <?php if (isset($form_errors['department_name'])): ?>
                    <div class="invalid-feedback"><?php echo $form_errors['department_name']; ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group mb-3">
                <label for="department_description" class="form-label">توضیحات</label>
                <textarea class="form-control <?php echo isset($form_errors['department_description']) ? 'is-invalid' : ''; ?>"
                          id="department_description" name="department_description" rows="4"><?php echo htmlspecialchars($dept_description); // Use $dept_description ?></textarea>
                <?php if (isset($form_errors['department_description'])): ?>
                    <div class="invalid-feedback"><?php echo $form_errors['department_description']; ?></div>
                <?php endif; ?>
                <small class="form-text text-muted">توضیح مختصری درباره وظایف و مسئولیت‌های این بخش (اختیاری، حداکثر ۱۰۰۰ کاراکتر).</small>
            </div>

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
