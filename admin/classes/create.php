<?php
// admin/classes/create.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth

$csrf_token_class_create = generate_csrf_token('class_create_form');
$errors_cls_create = [];
// Default academic year: current Persian year to next, e.g., 1403-1404
// This requires a robust Jalali to Gregorian and vice-versa or a Jalali library for accurate year calculation.
// For simplicity, using Gregorian year for now, admin can adjust.
$current_gregorian_year = date('Y');
$next_gregorian_year = $current_gregorian_year + 1;
$default_academic_year = $current_gregorian_year . '-' . $next_gregorian_year; // e.g., 2024-2025

$input_cls = ['class_name' => '', 'teacher_id' => '', 'grade_level' => '', 'academic_year' => $default_academic_year, 'description' => '', 'is_active' => 1];

$teachers_cls_q = $conn->query("SELECT UserID, FirstName, LastName, Username FROM Users WHERE UserType = 'teacher' AND IsActive = TRUE ORDER BY LastName, FirstName");
$available_teachers_cls = [];
if ($teachers_cls_q) { while($tc = $teachers_cls_q->fetch_assoc()) $available_teachers_cls[] = $tc; $teachers_cls_q->close(); }

$grade_level_options = ['اول دبستان', 'دوم دبستان', 'سوم دبستان', 'چهارم دبستان', 'پنجم دبستان', 'ششم دبستان', 'متوسطه اول - هفتم', 'متوسطه اول - هشتم', 'متوسطه اول - نهم', 'متوسطه دوم - دهم', 'متوسطه دوم - یازدهم', 'متوسطه دوم - دوازدهم', 'پیش‌دبستانی', 'سطح ۱ (عمومی)', 'سطح ۲ (عمومی)', 'سطح ۳ (عمومی)', 'بزرگسالان', 'والدین'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_create_class'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'class_create_form')) {
        $errors_cls_create['csrf'] = 'خطای CSRF! درخواست نامعتبر یا توکن منقضی شده.';
    } else {
        $input_cls['class_name'] = sanitize_input($_POST['class_name'] ?? '');
        $input_cls['teacher_id'] = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
        $input_cls['grade_level'] = sanitize_input($_POST['grade_level'] ?? '');
        $input_cls['academic_year'] = sanitize_input($_POST['academic_year'] ?? '');
        $input_cls['description'] = sanitize_input($_POST['description_cls'] ?? '');
        $input_cls['is_active'] = isset($_POST['is_active_cls']) ? 1 : 0;

        if (empty($input_cls['class_name'])) $errors_cls_create['class_name'] = "نام کلاس الزامی است.";
        // Optional: Check for unique ClassName within an AcademicYear
        if (!empty($input_cls['class_name']) && !empty($input_cls['academic_year'])) {
            $stmt_check_cls_name = $conn->prepare("SELECT ClassID FROM Classes WHERE ClassName = ? AND AcademicYear = ?");
            if ($stmt_check_cls_name) {
                $stmt_check_cls_name->bind_param("ss", $input_cls['class_name'], $input_cls['academic_year']);
                $stmt_check_cls_name->execute();
                if ($stmt_check_cls_name->get_result()->num_rows > 0) {
                    $errors_cls_create['class_name'] = "کلاسی با این نام در این سال تحصیلی قبلاً ثبت شده است.";
                }
                $stmt_check_cls_name->close();
            }
        }


        if ($input_cls['teacher_id'] !== null) {
            $teacher_exists = false;
            foreach($available_teachers_cls as $atc) if($atc['UserID'] == $input_cls['teacher_id']) $teacher_exists = true;
            if(!$teacher_exists) $errors_cls_create['teacher_id'] = "مدرس انتخاب شده نامعتبر است.";
        }
        if (empty($input_cls['academic_year']) || !(preg_match('/^\d{4}-\d{4}$/', $input_cls['academic_year']) || preg_match('/^\d{4}-\d{2}$/', $input_cls['academic_year']) ) ) {
            $errors_cls_create['academic_year'] = "فرمت سال تحصیلی نامعتبر (مثال: 1403-1404 یا 1403-04 برای سال شمسی کوتاه شده).";
        }
        if (empty($input_cls['grade_level'])) $errors_cls_create['grade_level'] = "پایه / سطح کلاس الزامی است.";


        if (empty($errors_cls_create)) {
            $stmt_insert_cls = $conn->prepare("INSERT INTO Classes (ClassName, TeacherUserID, GradeLevel, AcademicYear, Description, IsActive, CreatedAt, UpdatedAt) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
            if ($stmt_insert_cls) {
                // TeacherUserID can be NULL if not selected
                $teacher_id_db = $input_cls['teacher_id'] ?: null;
                $stmt_insert_cls->bind_param("sisssi", $input_cls['class_name'], $teacher_id_db, $input_cls['grade_level'], $input_cls['academic_year'], $input_cls['description'], $input_cls['is_active']);
                if ($stmt_insert_cls->execute()) {
                    $_SESSION['flash_message'] = ['type' => 'success', 'text' => "کلاس '" . htmlspecialchars($input_cls['class_name']) . "' با موفقیت ایجاد شد."];
                    regenerate_csrf_token('class_create_form');
                    header("Location: index.php?action_status=success_create"); exit;
                } else { $errors_cls_create['db'] = "خطا در ایجاد کلاس: " . $stmt_insert_cls->error; }
                $stmt_insert_cls->close();
            } else { $errors_cls_create['db'] = "خطا در آماده سازی کوئری: " . $conn->error; }
        }
    }
    $csrf_token_class_create = regenerate_csrf_token('class_create_form');
}
?>
<div class="page-header"><h1>افزودن کلاس جدید</h1>
    <div class="page-header-actions"><a href="index.php" class="btn btn-secondary"><svg class="icon" width="16" height="16" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"></polyline></svg><span>بازگشت به لیست</span></a></div></div>

<?php if (!empty($errors_cls_create)): ?><div class="alert alert-danger"><ul><?php foreach ($errors_cls_create as $err_key => $err_val): ?><li><?php echo htmlspecialchars($err_val); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="card shadow-sm"><div class="card-body">
<form action="create.php" method="POST" class="form-container needs-validation" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_class_create; ?>">
    <div class="form-group">
        <label for="class_name_cls">نام کلاس <span class="text-danger">*</span></label>
        <input type="text" class="form-control <?php echo isset($errors_cls_create['class_name']) ? 'is-invalid' : ''; ?>" id="class_name_cls" name="class_name" value="<?php echo htmlspecialchars($input_cls['class_name']); ?>" required>
        <?php if(isset($errors_cls_create['class_name'])):?><div class="invalid-feedback"><?php echo $errors_cls_create['class_name'];?></div><?php endif;?>
    </div>
    <div class="row">
        <div class="form-group col-md-6">
            <label for="teacher_id_cls">مدرس اصلی (اختیاری)</label>
            <select name="teacher_id" id="teacher_id_cls" class="form-control custom-select <?php echo isset($errors_cls_create['teacher_id']) ? 'is-invalid' : ''; ?>">
                <option value="">-- بدون مدرس اصلی --</option>
                <?php foreach($available_teachers_cls as $teacher_opt): ?>
                <option value="<?php echo $teacher_opt['UserID']; ?>" <?php if($input_cls['teacher_id'] == $teacher_opt['UserID']) echo 'selected';?>><?php echo htmlspecialchars($teacher_opt['FirstName'].' '.$teacher_opt['LastName'].' (@'.$teacher_opt['Username'].')');?></option>
                <?php endforeach; ?>
            </select>
            <?php if(isset($errors_cls_create['teacher_id'])):?><div class="invalid-feedback"><?php echo $errors_cls_create['teacher_id'];?></div><?php endif;?>
        </div>
        <div class="form-group col-md-6">
            <label for="grade_level_cls">پایه / سطح <span class="text-danger">*</span></label>
            <input list="grade_levels_list" class="form-control <?php echo isset($errors_cls_create['grade_level']) ? 'is-invalid' : ''; ?>" id="grade_level_cls" name="grade_level" value="<?php echo htmlspecialchars($input_cls['grade_level']); ?>" placeholder="مثال: چهارم دبستان" required>
            <datalist id="grade_levels_list">
                <?php foreach($grade_level_options as $gl_opt): ?><option value="<?php echo htmlspecialchars($gl_opt); ?>"><?php endforeach; ?>
            </datalist>
            <?php if(isset($errors_cls_create['grade_level'])):?><div class="invalid-feedback"><?php echo $errors_cls_create['grade_level'];?></div><?php endif;?>
        </div>
    </div>
    <div class="form-group">
        <label for="academic_year_cls">سال تحصیلی <span class="text-danger">*</span></label>
        <input type="text" class="form-control <?php echo isset($errors_cls_create['academic_year']) ? 'is-invalid' : ''; ?>" id="academic_year_cls" name="academic_year" value="<?php echo htmlspecialchars($input_cls['academic_year']); ?>" placeholder="مثال: 1403-1404" required pattern="^\d{4}-\d{2,4}$">
        <?php if(isset($errors_cls_create['academic_year'])):?><div class="invalid-feedback"><?php echo $errors_cls_create['academic_year'];?></div><?php endif;?>
    </div>
    <div class="form-group">
        <label for="description_cls_create">توضیحات</label>
        <textarea class="form-control" id="description_cls_create" name="description_cls" rows="3"><?php echo htmlspecialchars($input_cls['description']); ?></textarea>
    </div>
    <div class="form-group">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="is_active_cls_create" name="is_active_cls" value="1" <?php echo $input_cls['is_active'] ? 'checked' : ''; ?>>
            <label class="form-check-label" for="is_active_cls_create">کلاس فعال باشد</label>
        </div>
    </div>
    <div class="form-actions mt-4">
        <button type="submit" name="submit_create_class" class="btn btn-primary btn-lg">
            <svg class="icon" width="18" height="18" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
            <span>ایجاد کلاس</span>
        </button>
        <a href="index.php" class="btn btn-outline-secondary btn-lg">انصراف</a>
    </div>
</form>
</div></div>
<script> /* Basic Bootstrap validation ... */
(function () { 'use strict'; var forms = document.querySelectorAll('.needs-validation');
  Array.prototype.slice.call(forms).forEach(function (form) {
  form.addEventListener('submit', function (event) { if (!form.checkValidity()) { event.preventDefault(); event.stopPropagation(); } form.classList.add('was-validated');}, false);});})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
