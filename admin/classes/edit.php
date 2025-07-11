<?php
// admin/classes/edit.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth

$csrf_token_class_edit = generate_csrf_token('class_edit_form');
$errors_cls_edit = [];
$class_id_to_edit = null;
$class_data_for_form = null; // Holds data fetched from DB for the class being edited

// Fetch active teachers for dropdown
$teachers_cls_q_edit = $conn->query("SELECT UserID, FirstName, LastName, Username FROM Users WHERE UserType = 'teacher' AND IsActive = TRUE ORDER BY LastName, FirstName");
$available_teachers_cls_edit = [];
if ($teachers_cls_q_edit) { while($tc_e = $teachers_cls_q_edit->fetch_assoc()) $available_teachers_cls_edit[] = $tc_e; $teachers_cls_q_edit->close(); }

$grade_level_options_edit = ['اول دبستان', 'دوم دبستان', 'سوم دبستان', 'چهارم دبستان', 'پنجم دبستان', 'ششم دبستان', 'متوسطه اول - هفتم', 'متوسطه اول - هشتم', 'متوسطه اول - نهم', 'متوسطه دوم - دهم', 'متوسطه دوم - یازدهم', 'متوسطه دوم - دوازدهم', 'پیش‌دبستانی', 'سطح ۱ (عمومی)', 'سطح ۲ (عمومی)', 'سطح ۳ (عمومی)', 'بزرگسالان', 'والدین'];

// Attempt to load class data if class_id is provided in GET
if (isset($_GET['class_id']) && is_numeric($_GET['class_id'])) {
    $class_id_to_edit = (int)$_GET['class_id'];
    $stmt_fetch_cls = $conn->prepare("SELECT ClassID, ClassName, TeacherUserID, GradeLevel, AcademicYear, Description, IsActive FROM Classes WHERE ClassID = ?");
    if ($stmt_fetch_cls) {
        $stmt_fetch_cls->bind_param("i", $class_id_to_edit);
        $stmt_fetch_cls->execute();
        $result_cls = $stmt_fetch_cls->get_result();
        if ($result_cls->num_rows === 1) {
            $class_data_for_form = $result_cls->fetch_assoc();
        } else { $errors_cls_edit['load'] = "کلاس مورد نظر برای ویرایش یافت نشد."; }
        $stmt_fetch_cls->close();
    } else { $errors_cls_edit['load'] = "خطا در بارگذاری اطلاعات کلاس: " . $conn->error; }
} elseif (!isset($_POST['class_id_hidden'])) { // If not POST and no class_id in GET
     $errors_cls_edit['load'] = "شناسه کلاس برای ویرایش نامعتبر یا مشخص نشده است.";
}


// Initialize input_cls_edit:
// 1. If POST, use POST data (sticky form).
// 2. Else if $class_data_for_form is loaded (from GET), use that.
// 3. Else, empty/defaults.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $class_id_to_edit = isset($_POST['class_id_hidden']) ? (int)$_POST['class_id_hidden'] : $class_id_to_edit; // Ensure class_id is maintained
    $input_cls_edit = [
        'class_name'    => sanitize_input($_POST['class_name'] ?? ''),
        'teacher_id'    => !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null,
        'grade_level'   => sanitize_input($_POST['grade_level'] ?? ''),
        'academic_year' => sanitize_input($_POST['academic_year'] ?? ''),
        'description'   => sanitize_input($_POST['description_cls_edit'] ?? ''),
        'is_active'     => isset($_POST['is_active_cls_edit']) ? 1 : 0
    ];
} elseif ($class_data_for_form) {
    $input_cls_edit = [
        'class_name'    => $class_data_for_form['ClassName'],
        'teacher_id'    => $class_data_for_form['TeacherUserID'],
        'grade_level'   => $class_data_for_form['GradeLevel'],
        'academic_year' => $class_data_for_form['AcademicYear'],
        'description'   => $class_data_for_form['Description'],
        'is_active'     => $class_data_for_form['IsActive']
    ];
} else { // Fallback if no data could be loaded or posted (e.g. direct access with invalid/no ID and not POST)
    $input_cls_edit = ['class_name' => '', 'teacher_id' => '', 'grade_level' => '', 'academic_year' => '', 'description' => '', 'is_active' => 1];
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_edit_class'])) {
    if (!$class_id_to_edit) { // This check is crucial if class_id wasn't in GET and not in POST hidden
        $errors_cls_edit['form'] = "خطا: شناسه کلاس برای ویرایش در دسترس نیست.";
    } elseif (!verify_csrf_token($_POST['csrf_token'] ?? '', 'class_edit_form')) {
        $errors_cls_edit['csrf'] = 'خطای CSRF! درخواست نامعتبر یا توکن منقضی شده.';
    } else {
        // Validation logic (using $input_cls_edit which is already populated from POST)
        if (empty($input_cls_edit['class_name'])) $errors_cls_edit['class_name'] = "نام کلاس الزامی است.";
        if (!empty($input_cls_edit['class_name']) && !empty($input_cls_edit['academic_year'])) {
            $stmt_check_cls_name_edit = $conn->prepare("SELECT ClassID FROM Classes WHERE ClassName = ? AND AcademicYear = ? AND ClassID != ?");
            if ($stmt_check_cls_name_edit) {
                $stmt_check_cls_name_edit->bind_param("ssi", $input_cls_edit['class_name'], $input_cls_edit['academic_year'], $class_id_to_edit);
                $stmt_check_cls_name_edit->execute();
                if ($stmt_check_cls_name_edit->get_result()->num_rows > 0) {
                    $errors_cls_edit['class_name'] = "کلاس دیگری با این نام در این سال تحصیلی قبلاً ثبت شده است.";
                }
                $stmt_check_cls_name_edit->close();
            } else {$errors_cls_edit['db'] = "خطا بررسی نام کلاس: ".$conn->error;}
        }

        if ($input_cls_edit['teacher_id'] !== null) { // teacher_id can be empty string from select if "-- بدون مدرس --" selected
            $teacher_exists_edit = false;
            if($input_cls_edit['teacher_id'] == '') $input_cls_edit['teacher_id'] = null; // Treat empty string as NULL for DB
            else {
                 foreach($available_teachers_cls_edit as $atc_e) if($atc_e['UserID'] == $input_cls_edit['teacher_id']) $teacher_exists_edit = true;
                 if(!$teacher_exists_edit) $errors_cls_edit['teacher_id'] = "مدرس انتخاب شده نامعتبر است.";
            }
        }
         if (empty($input_cls_edit['academic_year']) || !(preg_match('/^\d{4}-\d{4}$/', $input_cls_edit['academic_year']) || preg_match('/^\d{4}-\d{2}$/', $input_cls_edit['academic_year']) ) ) {
            $errors_cls_edit['academic_year'] = "فرمت سال تحصیلی نامعتبر (مثال: 1403-1404).";
        }
        if (empty($input_cls_edit['grade_level'])) $errors_cls_edit['grade_level'] = "پایه / سطح کلاس الزامی است.";

        if (empty($errors_cls_edit)) {
            $stmt_update_cls = $conn->prepare("UPDATE Classes SET ClassName = ?, TeacherUserID = ?, GradeLevel = ?, AcademicYear = ?, Description = ?, IsActive = ?, UpdatedAt = NOW() WHERE ClassID = ?");
            if ($stmt_update_cls) {
                $stmt_update_cls->bind_param("sisssii", $input_cls_edit['class_name'], $input_cls_edit['teacher_id'], $input_cls_edit['grade_level'], $input_cls_edit['academic_year'], $input_cls_edit['description'], $input_cls_edit['is_active'], $class_id_to_edit);
                if ($stmt_update_cls->execute()) {
                    $_SESSION['flash_message'] = ['type' => 'success', 'text' => "کلاس '" . htmlspecialchars($input_cls_edit['class_name']) . "' با موفقیت ویرایش شد."];
                    regenerate_csrf_token('class_edit_form');
                    header("Location: index.php?action_status=success_edit"); exit;
                } else { $errors_cls_edit['db'] = "خطا در ویرایش کلاس: " . $stmt_update_cls->error; }
                $stmt_update_cls->close();
            } else { $errors_cls_edit['db'] = "خطا در آماده سازی کوئری ویرایش: " . $conn->error; }
        }
    }
    $csrf_token_class_edit = regenerate_csrf_token('class_edit_form');
}
?>
<div class="page-header"><h1>ویرایش کلاس: <?php echo htmlspecialchars($input_cls_edit['class_name'] ?: ($class_data_for_form['ClassName'] ?? '...'));?></h1>
    <div class="page-header-actions"><a href="index.php" class="btn btn-secondary"><svg class="icon" width="16" height="16" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"></polyline></svg><span>بازگشت به لیست</span></a></div></div>

<?php if (!empty($errors_cls_edit)): ?><div class="alert alert-danger"><ul><?php foreach ($errors_cls_edit as $err_val_e): ?><li><?php echo htmlspecialchars($err_val_e); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<?php if ($class_data_for_form || $_SERVER["REQUEST_METHOD"] == "POST"): // Show form if data loaded or if it's a POST request (even with load errors) ?>
<div class="card shadow-sm"><div class="card-body">
<form action="edit.php?class_id=<?php echo $class_id_to_edit; ?>" method="POST" class="form-container needs-validation" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_class_edit; ?>">
    <input type="hidden" name="class_id_hidden" value="<?php echo $class_id_to_edit; ?>">
    <div class="form-group">
        <label for="class_name_cls_edit">نام کلاس <span class="text-danger">*</span></label>
        <input type="text" class="form-control <?php echo isset($errors_cls_edit['class_name']) ? 'is-invalid' : ''; ?>" id="class_name_cls_edit" name="class_name" value="<?php echo htmlspecialchars($input_cls_edit['class_name']); ?>" required>
        <?php if(isset($errors_cls_edit['class_name'])):?><div class="invalid-feedback"><?php echo $errors_cls_edit['class_name'];?></div><?php endif;?>
    </div>
    <div class="row">
        <div class="form-group col-md-6">
            <label for="teacher_id_cls_edit">مدرس اصلی</label>
            <select name="teacher_id" id="teacher_id_cls_edit" class="form-control custom-select <?php echo isset($errors_cls_edit['teacher_id']) ? 'is-invalid' : ''; ?>">
                <option value="">-- بدون مدرس اصلی --</option>
                <?php foreach($available_teachers_cls_edit as $teacher_opt_e): ?>
                <option value="<?php echo $teacher_opt_e['UserID']; ?>" <?php if($input_cls_edit['teacher_id'] == $teacher_opt_e['UserID']) echo 'selected';?>><?php echo htmlspecialchars($teacher_opt_e['FirstName'].' '.$teacher_opt_e['LastName'].' (@'.$teacher_opt_e['Username'].')');?></option>
                <?php endforeach; ?>
            </select>
            <?php if(isset($errors_cls_edit['teacher_id'])):?><div class="invalid-feedback"><?php echo $errors_cls_edit['teacher_id'];?></div><?php endif;?>
        </div>
        <div class="form-group col-md-6">
            <label for="grade_level_cls_edit">پایه / سطح <span class="text-danger">*</span></label>
            <input list="grade_levels_list_edit" class="form-control <?php echo isset($errors_cls_edit['grade_level']) ? 'is-invalid' : ''; ?>" id="grade_level_cls_edit" name="grade_level" value="<?php echo htmlspecialchars($input_cls_edit['grade_level']); ?>" required>
            <datalist id="grade_levels_list_edit">
                <?php foreach($grade_level_options_edit as $gl_opt_e): ?><option value="<?php echo htmlspecialchars($gl_opt_e); ?>"><?php endforeach; ?>
            </datalist>
            <?php if(isset($errors_cls_edit['grade_level'])):?><div class="invalid-feedback"><?php echo $errors_cls_edit['grade_level'];?></div><?php endif;?>
        </div>
    </div>
    <div class="form-group">
        <label for="academic_year_cls_edit">سال تحصیلی <span class="text-danger">*</span></label>
        <input type="text" class="form-control <?php echo isset($errors_cls_edit['academic_year']) ? 'is-invalid' : ''; ?>" id="academic_year_cls_edit" name="academic_year" value="<?php echo htmlspecialchars($input_cls_edit['academic_year']); ?>" placeholder="مثال: 1403-1404" required pattern="^\d{4}-\d{2,4}$">
        <?php if(isset($errors_cls_edit['academic_year'])):?><div class="invalid-feedback"><?php echo $errors_cls_edit['academic_year'];?></div><?php endif;?>
    </div>
    <div class="form-group">
        <label for="description_cls_edit_area">توضیحات</label>
        <textarea class="form-control" id="description_cls_edit_area" name="description_cls_edit" rows="3"><?php echo htmlspecialchars($input_cls_edit['description']); ?></textarea>
    </div>
    <div class="form-group">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="is_active_cls_edit_check" name="is_active_cls_edit" value="1" <?php echo $input_cls_edit['is_active'] ? 'checked' : ''; ?>>
            <label class="form-check-label" for="is_active_cls_edit_check">کلاس فعال باشد</label>
        </div>
    </div>
    <div class="form-actions mt-4">
        <button type="submit" name="submit_edit_class" class="btn btn-primary btn-lg">
            <svg class="icon" width="18" height="18" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
            <span>ذخیره تغییرات</span>
        </button>
        <a href="index.php" class="btn btn-outline-secondary btn-lg">انصراف</a>
    </div>
</form>
</div></div>
<?php elseif(isset($errors_cls_edit['load'])): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($errors_cls_edit['load']); ?></div>
<?php endif; ?>
<script> /* Basic Bootstrap validation ... */
(function () { 'use strict'; var forms = document.querySelectorAll('.needs-validation');
  Array.prototype.slice.call(forms).forEach(function (form) {
  form.addEventListener('submit', function (event) { if (!form.checkValidity()) { event.preventDefault(); event.stopPropagation(); } form.classList.add('was-validated');}, false);});})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
