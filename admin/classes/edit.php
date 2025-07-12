<?php
require_once __DIR__ . '/../includes/header.php';

$class_id_to_edit = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

$class_name = '';
$grade_level = '';
$academic_year = '';
$description = '';
$teacher_ids_selected = []; // Holds IDs of teachers selected in the form on POST or fetched from DB
$original_class_name = '';    // To display in title and for uniqueness check if name changes
$original_academic_year = ''; // For uniqueness check if year changes
$form_errors = [];

if ($class_id_to_edit <= 0) {
    $_SESSION['action_error'] = 'شناسه کلاس نامعتبر است.';
    header("Location: index.php");
    exit;
}

$csrf_token_name = 'edit_class_' . $class_id_to_edit;
$csrf_token = generate_csrf_token($csrf_token_name);

// Populate academic years dropdown
$current_gregorian_year_e = date('Y');
$current_jalali_year_parts_e = explode('/', to_jalali($current_gregorian_year_e.'-03-21', 'yyyy/MM/dd'));
$current_jalali_year_numeric_e = isset($current_jalali_year_parts_e[0]) ? (int)$current_jalali_year_parts_e[0] : (((int)date('Y')) - 622);
$academic_years_dropdown_edit = [];
// Generate a range of academic years, e.g., 5 years past to 2 years future from current Jalali year
for ($i = $current_jalali_year_numeric_e - 5; $i <= $current_jalali_year_numeric_e + 2; $i++) {
    $academic_years_dropdown_edit[] = $i . '-' . ($i + 1);
}

// Fetch available teachers
$available_teachers_edit = [];
if ($conn) {
    $stmt_teachers_edit = $conn->query(
        "SELECT DISTINCT u.UserID, u.FirstName, u.LastName, u.Username
         FROM Users u
         LEFT JOIN UserRoles ur ON u.UserID = ur.UserID
         LEFT JOIN Roles r ON ur.RoleID = r.RoleID
         WHERE u.IsActive = TRUE AND (u.UserType = 'teacher' OR r.RoleName = 'مدرس')
         ORDER BY u.LastName, u.FirstName"
    );
    if ($stmt_teachers_edit) {
        while ($teacher = $stmt_teachers_edit->fetch_assoc()) {
            $available_teachers_edit[] = $teacher;
        }
        $stmt_teachers_edit->close();
    } else {
        $form_errors['fetch_teachers'] = "خطا در بارگذاری لیست مدرسین: " . $conn->error;
    }

    // Fetch class data for editing (on initial GET request)
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { // Only fetch on initial load, not on POST error display
        $stmt_fetch_class = $conn->prepare("SELECT ClassName, GradeLevel, AcademicYear, Description FROM Classes WHERE ClassID = ?");
        if ($stmt_fetch_class) {
            $stmt_fetch_class->bind_param("i", $class_id_to_edit);
            $stmt_fetch_class->execute();
            $result_class_fetch = $stmt_fetch_class->get_result();
            if ($class_data = $result_class_fetch->fetch_assoc()) {
                $class_name = $class_data['ClassName'];
                $original_class_name = $class_data['ClassName'];
                $grade_level = $class_data['GradeLevel'];
                $academic_year = $class_data['AcademicYear'];
                $original_academic_year = $class_data['AcademicYear'];
                $description = $class_data['Description'];

                $stmt_fetch_assigned_teachers = $conn->prepare("SELECT UserID FROM ClassTeachers WHERE ClassID = ?");
                if ($stmt_fetch_assigned_teachers) {
                    $stmt_fetch_assigned_teachers->bind_param("i", $class_id_to_edit);
                    $stmt_fetch_assigned_teachers->execute();
                    $result_assigned_teachers = $stmt_fetch_assigned_teachers->get_result();
                    while ($row_teacher = $result_assigned_teachers->fetch_assoc()) {
                        $teacher_ids_selected[] = $row_teacher['UserID'];
                    }
                    $stmt_fetch_assigned_teachers->close();
                } else {
                    $form_errors['fetch_assigned_teachers'] = "خطا در بارگذاری مدرسین فعلی کلاس: " . $conn->error;
                }
            } else {
                $_SESSION['action_error'] = 'کلاس با شناسه ' . htmlspecialchars($class_id_to_edit) . ' یافت نشد.';
                header("Location: index.php");
                exit;
            }
            $stmt_fetch_class->close();
        } else {
            $form_errors['db_error'] = 'خطا در آماده سازی کوئری بارگذاری کلاس: ' . $conn->error;
        }
    } else { // On POST, repopulate from POST data if validation fails
        $class_name = sanitize_input($_POST['class_name'] ?? '');
        $grade_level = sanitize_input($_POST['grade_level'] ?? '');
        $academic_year = sanitize_input($_POST['academic_year'] ?? '');
        $description = sanitize_input($_POST['description'] ?? '');
        $teacher_ids_selected = isset($_POST['teacher_ids']) && is_array($_POST['teacher_ids']) ? array_map('intval', $_POST['teacher_ids']) : [];
        // Need to fetch original_class_name and original_academic_year again for comparison if not already set
        if(empty($original_class_name) && $conn) {
            $stmt_orig = $conn->prepare("SELECT ClassName, AcademicYear FROM Classes WHERE ClassID = ?");
            if($stmt_orig) {
                $stmt_orig->bind_param("i", $class_id_to_edit); $stmt_orig->execute();
                $res_orig = $stmt_orig->get_result();
                if($d_orig = $res_orig->fetch_assoc()){ $original_class_name = $d_orig['ClassName']; $original_academic_year = $d_orig['AcademicYear'];}
                $stmt_orig->close();
            }
        }
    }
} else {
    $form_errors['db_error'] = 'خطا در اتصال به پایگاه داده.';
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'], $csrf_token_name)) {
        $form_errors['csrf'] = 'خطای امنیتی CSRF.';
    } else {
        $csrf_token = regenerate_csrf_token($csrf_token_name);

        // $class_name, $grade_level etc. are already populated from POST above if it's a POST request

        // Validation
        if (empty($class_name)) $form_errors['class_name'] = 'نام کلاس نمی‌تواند خالی باشد.';
        if (empty($grade_level)) $form_errors['grade_level'] = 'مقطع تحصیلی نمی‌تواند خالی باشد.';
        if (empty($academic_year)) $form_errors['academic_year'] = 'سال تحصیلی نمی‌تواند خالی باشد.';
        elseif (!preg_match('/^\d{4}-\d{4}$/', $academic_year)) {
            $form_errors['academic_year'] = 'فرمت سال تحصیلی نامعتبر است (مثال: 1403-1404).';
        }
        if (strlen($class_name) > 150) $form_errors['class_name'] = 'نام کلاس طولانی تر از حد مجاز است (150 کاراکتر).';
        if (strlen($grade_level) > 100) $form_errors['grade_level'] = 'مقطع تحصیلی طولانی تر از حد مجاز است (100 کاراکتر).';
        if (strlen($description) > 1000) $form_errors['description'] = 'توضیحات طولانی تر از حد مجاز است (1000 کاراکتر).';


        if (empty($form_errors['class_name']) && empty($form_errors['academic_year']) &&
            ($class_name !== $original_class_name || $academic_year !== $original_academic_year) && $conn) {
            $stmt_check_class_edit = $conn->prepare("SELECT ClassID FROM Classes WHERE ClassName = ? AND AcademicYear = ? AND ClassID != ?");
            if ($stmt_check_class_edit) {
                $stmt_check_class_edit->bind_param("ssi", $class_name, $academic_year, $class_id_to_edit);
                $stmt_check_class_edit->execute();
                if ($stmt_check_class_edit->get_result()->num_rows > 0) {
                    $form_errors['class_name'] = "کلاس دیگری با این نام در این سال تحصیلی قبلاً ثبت شده است.";
                }
                $stmt_check_class_edit->close();
            } else {
                $form_errors['db_error'] = "خطا در بررسی تکراری بودن کلاس (ویرایش): " . $conn->error;
            }
        }

        if (empty($form_errors)) {
            if ($conn) {
                $conn->begin_transaction();
                try {
                    $stmt_update_class = $conn->prepare("UPDATE Classes SET ClassName = ?, GradeLevel = ?, AcademicYear = ?, Description = ? WHERE ClassID = ?");
                    if (!$stmt_update_class) throw new Exception("خطا در آماده سازی کوئری بروزرسانی کلاس: " . $conn->error);

                    $stmt_update_class->bind_param("ssssi", $class_name, $grade_level, $academic_year, $description, $class_id_to_edit);
                    if (!$stmt_update_class->execute()) throw new Exception("خطا در بروزرسانی کلاس: " . $stmt_update_class->error);
                    $stmt_update_class->close();

                    $stmt_delete_teachers = $conn->prepare("DELETE FROM ClassTeachers WHERE ClassID = ?");
                    if (!$stmt_delete_teachers) throw new Exception("خطا در آماده سازی حذف مدرسین قدیمی: " . $conn->error);
                    $stmt_delete_teachers->bind_param("i", $class_id_to_edit);
                    if (!$stmt_delete_teachers->execute()) throw new Exception("خطا در حذف مدرسین قدیمی: " . $stmt_delete_teachers->error);
                    $stmt_delete_teachers->close();

                    if (!empty($teacher_ids_selected)) {
                        $stmt_assign_teacher_edit = $conn->prepare("INSERT INTO ClassTeachers (ClassID, UserID) VALUES (?, ?)");
                        if (!$stmt_assign_teacher_edit) throw new Exception("خطا در آماده سازی تخصیص مدرس (ویرایش): " . $conn->error);
                        foreach ($teacher_ids_selected as $teacher_id) {
                             if ($teacher_id > 0) {
                                $stmt_assign_teacher_edit->bind_param("ii", $class_id_to_edit, $teacher_id);
                                if (!$stmt_assign_teacher_edit->execute()) throw new Exception("خطا در تخصیص مدرس $teacher_id (ویرایش): " . $stmt_assign_teacher_edit->error);
                             }
                        }
                        $stmt_assign_teacher_edit->close();
                    }

                    $conn->commit();
                    $_SESSION['action_success'] = "کلاس '" . htmlspecialchars($class_name) . " (" . htmlspecialchars($academic_year) . ")' با موفقیت بروزرسانی شد.";
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
    <h1>ویرایش کلاس: <?php echo htmlspecialchars($original_class_name . ' (' . $original_academic_year . ')'); ?></h1>
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
<?php if (!empty($form_errors['fetch_teachers']) || !empty($form_errors['fetch_assigned_teachers'])): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <?php echo $form_errors['fetch_teachers'] ?? ''; ?> <?php echo $form_errors['fetch_assigned_teachers'] ?? ''; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>


<div class="card">
    <div class="card-body">
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?class_id=" . $class_id_to_edit; ?>" class="form-container needs-validation" novalidate>
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
                           id="grade_level" name="grade_level" value="<?php echo htmlspecialchars($grade_level); ?>" required>
                    <?php if (isset($form_errors['grade_level'])): ?><div class="invalid-feedback"><?php echo $form_errors['grade_level']; ?></div><?php endif; ?>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="academic_year" class="form-label">سال تحصیلی <span class="text-danger">*</span></label>
                    <select class="form-select <?php echo isset($form_errors['academic_year']) ? 'is-invalid' : ''; ?>"
                            id="academic_year" name="academic_year" required>
                        <option value="">انتخاب کنید...</option>
                        <?php foreach ($academic_years_dropdown_edit as $year_val_edit): ?>
                            <option value="<?php echo $year_val_edit; ?>" <?php echo ($academic_year === $year_val_edit) ? 'selected' : ''; ?>>
                                <?php echo $year_val_edit; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($form_errors['academic_year'])): ?><div class="invalid-feedback"><?php echo $form_errors['academic_year']; ?></div><?php endif; ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="teacher_ids" class="form-label">مدرس(ها)</label>
                    <select class="form-select <?php echo isset($form_errors['teacher_ids']) ? 'is-invalid' : ''; ?>"
                            id="teacher_ids" name="teacher_ids[]" multiple>
                        <?php if (empty($available_teachers_edit) && empty($form_errors['fetch_teachers'])): ?>
                            <option value="" disabled>هیچ مدرس فعالی یافت نشد.</option>
                        <?php elseif (!empty($available_teachers_edit)): ?>
                            <?php foreach ($available_teachers_edit as $teacher_edit): ?>
                                <option value="<?php echo $teacher_edit['UserID']; ?>" <?php echo in_array($teacher_edit['UserID'], $teacher_ids_selected) ? 'selected' : ''; ?>>
                                     <?php echo htmlspecialchars(trim($teacher_edit['LastName'] . ' ' . $teacher_edit['FirstName'])) . ' (@' . htmlspecialchars($teacher_edit['Username']) . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                     <?php if (isset($form_errors['teacher_ids'])): ?><div class="invalid-feedback d-block"><?php echo $form_errors['teacher_ids']; ?></div><?php endif; ?>
                </div>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">توضیحات</label>
                <textarea class="form-control <?php echo isset($form_errors['description']) ? 'is-invalid' : ''; ?>"
                          id="description" name="description" rows="3"><?php echo htmlspecialchars($description); ?></textarea>
                <?php if (isset($form_errors['description'])): ?><div class="invalid-feedback"><?php echo $form_errors['description']; ?></div><?php endif; ?>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
                <a href="index.php" class="btn btn-secondary">انصراف</a>
            </div>
        </form>
    </div>
</div>

<link href="<?php echo get_base_url(); ?>assets/js/select2/css/select2.min.css" rel="stylesheet" />
<link href="<?php echo get_base_url(); ?>assets/js/select2-bootstrap-5-theme/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<script src="<?php echo get_base_url(); ?>assets/js/select2/js/select2.full.min.js"></script>
<script src="<?php echo get_base_url(); ?>assets/js/select2/js/i18n/fa.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
        $('#teacher_ids').select2({
            dir: "rtl",
            placeholder: "یک یا چند مدرس انتخاب کنید...",
            allowClear: true,
            language: "fa",
            theme: "bootstrap-5"
        });
    }
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
