<?php
require_once __DIR__ . '/../includes/header.php'; // Also includes db_config.php and helper_functions.php

$class_name = '';
$grade_level = '';
$academic_year = ''; // Will be populated by default
$description = '';
$teacher_ids_selected = []; // Array for multiple teachers selected in form
$form_errors = [];

$csrf_token = generate_csrf_token('create_class');

// Populate academic years for dropdown (e.g., current Jalali year +/- 2 years)
$current_gregorian_year_c = date('Y');
// Ensure to_jalali is available (from helper_functions.php)
$current_jalali_year_parts_c = explode('/', to_jalali($current_gregorian_year_c.'-03-21', 'yyyy/MM/dd')); // Use a date in spring for Jalali year start
$current_jalali_year_numeric_c = isset($current_jalali_year_parts_c[0]) ? (int)$current_jalali_year_parts_c[0] : ((int)date('Y')) - 622;

$academic_years_dropdown = [];
for ($i = $current_jalali_year_numeric_c - 3; $i <= $current_jalali_year_numeric_c + 2; $i++) {
    $academic_years_dropdown[] = $i . '-' . ($i + 1);
}
// Set default academic year to current one if not already set by POST error or previous input
if (empty($academic_year) && $_SERVER['REQUEST_METHOD'] !== 'POST') { // Only set default on first load
    $academic_year = $current_jalali_year_numeric_c . '-' . ($current_jalali_year_numeric_c + 1);
}


// Fetch available teachers (Users with 'teacher' type or 'مدرس' role)
$available_teachers = [];
if ($conn) {
    $stmt_teachers = $conn->query(
        "SELECT DISTINCT u.UserID, u.FirstName, u.LastName, u.Username
         FROM Users u
         LEFT JOIN UserRoles ur ON u.UserID = ur.UserID
         LEFT JOIN Roles r ON ur.RoleID = r.RoleID
         WHERE u.IsActive = TRUE AND (u.UserType = 'teacher' OR r.RoleName = 'مدرس')
         ORDER BY u.LastName, u.FirstName"
    );
    if ($stmt_teachers) {
        while ($teacher = $stmt_teachers->fetch_assoc()) {
            $available_teachers[] = $teacher;
        }
        $stmt_teachers->close(); // Close the statement object
    } else {
        $form_errors['fetch_teachers'] = "خطا در بارگذاری لیست مدرسین: " . $conn->error;
    }
} else {
    $form_errors['fetch_teachers'] = "خطا: عدم اتصال به پایگاه داده برای بارگذاری مدرسین.";
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'], 'create_class')) {
        $form_errors['csrf'] = 'خطای امنیتی CSRF. لطفاً صفحه را رفرش کرده و مجدداً تلاش کنید.';
    } else {
        $csrf_token = regenerate_csrf_token('create_class'); // Regenerate after use

        $class_name = sanitize_input($_POST['class_name'] ?? '');
        $grade_level = sanitize_input($_POST['grade_level'] ?? '');
        $academic_year = sanitize_input($_POST['academic_year'] ?? $academic_year); // Keep default if not posted
        $description = sanitize_input($_POST['description'] ?? '');
        $teacher_ids_selected = isset($_POST['teacher_ids']) && is_array($_POST['teacher_ids']) ? array_map('intval', $_POST['teacher_ids']) : [];

        // Validation
        if (empty($class_name)) $form_errors['class_name'] = 'نام کلاس نمی‌تواند خالی باشد.';
        else if (strlen($class_name) > 150) $form_errors['class_name'] = 'نام کلاس طولانی تر از حد مجاز است (150 کاراکتر).';

        if (empty($grade_level)) $form_errors['grade_level'] = 'مقطع تحصیلی نمی‌تواند خالی باشد.';
        else if (strlen($grade_level) > 100) $form_errors['grade_level'] = 'مقطع تحصیلی طولانی تر از حد مجاز است (100 کاراکتر).';

        if (empty($academic_year)) $form_errors['academic_year'] = 'سال تحصیلی نمی‌تواند خالی باشد.';
        // Basic format check for academic year (e.g., YYYY-YYYY)
        elseif (!preg_match('/^\d{4}-\d{4}$/', $academic_year)) {
            $form_errors['academic_year'] = 'فرمت سال تحصیلی نامعتبر است (مثال: 1403-1404).';
        }

        if (strlen($description) > 1000) $form_errors['description'] = 'توضیحات طولانی تر از حد مجاز است (1000 کاراکتر).';

        // Check for duplicate class (name + academic year)
        if (empty($form_errors['class_name']) && empty($form_errors['academic_year']) && $conn) {
            $stmt_check_class = $conn->prepare("SELECT ClassID FROM Classes WHERE ClassName = ? AND AcademicYear = ?");
            if ($stmt_check_class) {
                $stmt_check_class->bind_param("ss", $class_name, $academic_year);
                $stmt_check_class->execute();
                if ($stmt_check_class->get_result()->num_rows > 0) {
                    $form_errors['class_name'] = "کلاسی با این نام در این سال تحصیلی قبلاً ثبت شده است.";
                }
                $stmt_check_class->close();
            } else {
                $form_errors['db_error'] = "خطا در بررسی تکراری بودن کلاس: " . $conn->error;
            }
        }


        if (empty($form_errors)) {
            if ($conn) {
                $conn->begin_transaction();
                try {
                    $stmt_insert_class = $conn->prepare("INSERT INTO Classes (ClassName, GradeLevel, AcademicYear, Description, CreatedAt) VALUES (?, ?, ?, ?, NOW())");
                    if (!$stmt_insert_class) throw new Exception("خطا در آماده سازی کوئری ایجاد کلاس: " . $conn->error);

                    $stmt_insert_class->bind_param("ssss", $class_name, $grade_level, $academic_year, $description);
                    if (!$stmt_insert_class->execute()) throw new Exception("خطا در ایجاد کلاس: " . $stmt_insert_class->error);

                    $new_class_id = $stmt_insert_class->insert_id;
                    $stmt_insert_class->close();

                    if ($new_class_id && !empty($teacher_ids_selected)) {
                        $stmt_assign_teacher = $conn->prepare("INSERT INTO ClassTeachers (ClassID, UserID) VALUES (?, ?)");
                        if (!$stmt_assign_teacher) throw new Exception("خطا در آماده سازی کوئری تخصیص مدرس: " . $conn->error);

                        foreach ($teacher_ids_selected as $teacher_id) {
                            if ($teacher_id > 0) { // Basic check for valid UserID
                                $stmt_assign_teacher->bind_param("ii", $new_class_id, $teacher_id);
                                if (!$stmt_assign_teacher->execute()) throw new Exception("خطا در تخصیص مدرس با شناسه $teacher_id: " . $stmt_assign_teacher->error);
                            }
                        }
                        $stmt_assign_teacher->close();
                    }

                    $conn->commit();
                    $_SESSION['action_success'] = "کلاس '" . htmlspecialchars($class_name) . " (" . htmlspecialchars($academic_year) . ")' با موفقیت ایجاد شد.";
                    header("Location: index.php");
                    exit;

                } catch (Exception $e) {
                    $conn->rollback();
                    $form_errors['db_error'] = "خطای پایگاه داده: " . $e->getMessage();
                }
            } else {
                $form_errors['db_error'] = 'خطا در اتصال به پایگاه داده.';
            }
        }
    }
}
?>

<div class="page-header">
    <h1>افزودن کلاس جدید</h1>
    <div class="page-header-actions">
        <a href="index.php" class="btn btn-secondary">
             <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-right-circle icon" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8zm15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-4.5-.5a.5.5 0 0 0 0 1h5.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3a.5.5 0 0 0 0-.708l-3-3a.5.5 0 1 0-.708.708L11.293 7.5H6.5a.5.5 0 0 0 0 1h4.793z"/></svg>
            بازگشت به لیست کلاس‌ها
        </a>
    </div>
</div>

<?php if (!empty($form_errors['csrf'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo $form_errors['csrf']; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
<?php endif; ?>
<?php if (!empty($form_errors['db_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo $form_errors['db_error']; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
<?php endif; ?>
<?php if (!empty($form_errors['fetch_teachers'])): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert"><?php echo $form_errors['fetch_teachers']; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
<?php endif; ?>


<div class="card">
    <div class="card-body">
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="form-container needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="class_name" class="form-label">نام کلاس <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?php echo isset($form_errors['class_name']) ? 'is-invalid' : ''; ?>"
                           id="class_name" name="class_name" value="<?php echo htmlspecialchars($class_name); ?>" required>
                    <?php if (isset($form_errors['class_name'])): ?><div class="invalid-feedback"><?php echo $form_errors['class_name']; ?></div><?php endif; ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="grade_level" class="form-label">مقطع تحصیلی / سطح <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?php echo isset($form_errors['grade_level']) ? 'is-invalid' : ''; ?>"
                           id="grade_level" name="grade_level" value="<?php echo htmlspecialchars($grade_level); ?>" placeholder="مثال: پنجم ابتدایی، سطح یک نوجوان" required>
                    <?php if (isset($form_errors['grade_level'])): ?><div class="invalid-feedback"><?php echo $form_errors['grade_level']; ?></div><?php endif; ?>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="academic_year" class="form-label">سال تحصیلی <span class="text-danger">*</span></label>
                    <select class="form-select <?php echo isset($form_errors['academic_year']) ? 'is-invalid' : ''; ?>"
                            id="academic_year" name="academic_year" required>
                        <option value="">انتخاب کنید...</option>
                        <?php foreach ($academic_years_dropdown as $year_val): ?>
                            <option value="<?php echo $year_val; ?>" <?php echo ($academic_year === $year_val) ? 'selected' : ''; ?>>
                                <?php echo $year_val; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($form_errors['academic_year'])): ?><div class="invalid-feedback"><?php echo $form_errors['academic_year']; ?></div><?php endif; ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="teacher_ids" class="form-label">مدرس(ها)</label>
                    <select class="form-select <?php echo isset($form_errors['teacher_ids']) ? 'is-invalid' : ''; ?>"
                            id="teacher_ids" name="teacher_ids[]" multiple data-placeholder="یک یا چند مدرس انتخاب کنید...">
                        <?php if (empty($available_teachers) && empty($form_errors['fetch_teachers'])): ?>
                            <option value="" disabled>هیچ مدرس فعالی برای تخصیص یافت نشد.</option>
                        <?php elseif (!empty($available_teachers)): ?>
                            <?php foreach ($available_teachers as $teacher): ?>
                                <option value="<?php echo $teacher['UserID']; ?>" <?php echo in_array($teacher['UserID'], $teacher_ids_selected) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(trim($teacher['LastName'] . ' ' . $teacher['FirstName'])) . ' (@' . htmlspecialchars($teacher['Username']) . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <?php if (isset($form_errors['teacher_ids'])): ?><div class="invalid-feedback d-block"><?php echo $form_errors['teacher_ids']; ?></div><?php endif; ?>
                    <small class="form-text text-muted">می‌توانید یک یا چند مدرس را انتخاب کنید (اختیاری). کاربران با نقش "مدرس" یا نوع "teacher" در این لیست نمایش داده می‌شوند.</small>
                </div>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">توضیحات</label>
                <textarea class="form-control <?php echo isset($form_errors['description']) ? 'is-invalid' : ''; ?>"
                          id="description" name="description" rows="3"><?php echo htmlspecialchars($description); ?></textarea>
                <?php if (isset($form_errors['description'])): ?><div class="invalid-feedback"><?php echo $form_errors['description']; ?></div><?php endif; ?>
                <small class="form-text text-muted">هرگونه اطلاعات اضافی درباره کلاس (اختیاری، حداکثر ۱۰۰۰ کاراکتر).</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">ایجاد کلاس</button>
                <a href="index.php" class="btn btn-secondary">انصراف</a>
            </div>
        </form>
    </div>
</div>

<!-- Select2 JS and CSS -->
<link href="<?php echo get_base_url(); ?>assets/js/select2/css/select2.min.css" rel="stylesheet" />
<link href="<?php echo get_base_url(); ?>assets/js/select2-bootstrap-5-theme/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<script src="<?php echo get_base_url(); ?>assets/js/select2/js/select2.full.min.js"></script>
<script src="<?php echo get_base_url(); ?>assets/js/select2/js/i18n/fa.js"></script> <!-- Ensure Persian translation is available -->


<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
        $('#teacher_ids').select2({
            dir: "rtl",
            placeholder: "یک یا چند مدرس انتخاب کنید...",
            allowClear: true,
            language: "fa",
            theme: "bootstrap-5" // Apply Bootstrap 5 theme
        });
    } else {
        console.warn('jQuery or Select2 not available for teacher multi-select enhancement.');
    }
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
