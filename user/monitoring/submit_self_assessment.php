<?php
// user/monitoring/submit_self_assessment.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth

$teacher_user_id = get_current_user_id();
$class_id_for_assessment = null;
$class_data = null;
$available_assessment_forms = [];

if (!$teacher_user_id || get_current_user_type() !== 'teacher') {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'این بخش مخصوص مدرسین است.'];
    header("Location: " . ($user_base_url ?? '/my_site/user') . "/dashboard/index.php");
    exit;
}

if (isset($_GET['class_id']) && is_numeric($_GET['class_id'])) {
    $class_id_for_assessment = (int)$_GET['class_id'];

    $stmt_class = $conn->prepare("SELECT ClassID, ClassName, GradeLevel, AcademicYear FROM Classes WHERE ClassID = ? AND TeacherUserID = ? AND IsActive = TRUE");
    if ($stmt_class) {
        $stmt_class->bind_param("ii", $class_id_for_assessment, $teacher_user_id);
        $stmt_class->execute();
        $result_class = $stmt_class->get_result();
        if ($result_class->num_rows === 1) {
            $class_data = $result_class->fetch_assoc();

            $stmt_forms = $conn->prepare("SELECT FormID, FormName, Description FROM Forms WHERE FormPurpose = 'self_assessment' ORDER BY FormName");
            if ($stmt_forms) {
                $stmt_forms->execute();
                $result_forms = $stmt_forms->get_result();
                while ($form = $result_forms->fetch_assoc()) {
                    $available_assessment_forms[] = $form;
                }
                $stmt_forms->close();
            } else {
                $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا بارگذاری فرم‌های خوداظهاری: ' . $conn->error];
            }
        } else {
            $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'کلاس یافت نشد یا شما مدرس این کلاس نیستید.'];
            header("Location: index.php"); exit;
        }
        $stmt_class->close();
    } else {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا بارگذاری اطلاعات کلاس: ' . $conn->error];
        header("Location: index.php"); exit;
    }
} else {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'شناسه کلاس مشخص نشده است.'];
    header("Location: index.php"); exit;
}
?>
<div class="page-header">
    <h1>ثبت فرم خوداظهاری برای کلاس: <?php echo htmlspecialchars($class_data['ClassName'] ?? '...'); ?></h1>
    <?php if($class_data): ?>
        <p class="page-subtitle"> پایه: <?php echo htmlspecialchars($class_data['GradeLevel'] ?? '-'); ?> | سال تحصیلی: <?php echo htmlspecialchars($class_data['AcademicYear'] ?? '-'); ?> </p>
    <?php endif; ?>
    <div class="page-header-actions">
        <a href="index.php" class="btn btn-secondary">
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"></polyline></svg>
            <span>بازگشت به کلاس‌ها</span>
        </a>
    </div>
</div>

<?php
if (isset($_SESSION['flash_message'])) { /* Flash message display logic from previous files */ }
?>

<div class="card shadow-sm">
    <div class="card-header"><span class="card-title-text">انتخاب فرم خوداظهاری</span></div>
    <div class="card-body">
        <?php if (!empty($available_assessment_forms)): ?>
            <p class="mb-3">لطفاً یکی از فرم‌های زیر را برای تکمیل انتخاب کنید. پس از تکمیل، به این صفحه بازگردانده نمی‌شوید و می‌توانید از بخش "تاریخچه خوداظهاری‌ها" سوابق خود را مشاهده کنید.</p>
            <div class="list-group">
                <?php foreach ($available_assessment_forms as $form): ?>
                    <a href="<?php echo ($user_base_url ?? '/my_site/user'); ?>/forms/fill.php?form_id=<?php echo $form['FormID']; ?>&class_id=<?php echo $class_id_for_assessment; ?>&source=monitoring"
                       class="list-group-item list-group-item-action list-group-item-light rounded mb-2 shadow-sm-hover">
                        <div class="d-flex w-100 justify-content-between">
                            <h5 class="mb-1 text-primary-user font-weight-bold"><?php echo htmlspecialchars($form['FormName']); ?></h5>
                            <svg class="icon text-primary-user" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </div>
                        <?php if (!empty($form['Description'])): ?>
                            <small class="text-muted d-block mt-1"><?php echo nl2br(htmlspecialchars(mb_substr($form['Description'],0,120) . (mb_strlen($form['Description']) > 120 ? '...' : ''))); ?></small>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-warning mb-0">در حال حاضر هیچ فرم خوداظهاری توسط ادمین تعریف نشده است یا فرمی با هدف "خوداظهاری مدرس" یافت نشد.</div>
        <?php endif; ?>
    </div>
</div>

<div class="mt-4">
    <a href="assessment_history.php?class_id=<?php echo $class_id_for_assessment; ?>" class="btn btn-outline-info btn-lg">
        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>
        <span>مشاهده تاریخچه خوداظهاری‌های این کلاس</span>
    </a>
</div>
<style>
    .shadow-sm-hover { transition: box-shadow 0.2s ease-in-out; }
    .shadow-sm-hover:hover { box-shadow: 0 .25rem .75rem rgba(0,0,0,.08)!important; }
    .list-group-item-light { background-color: #f8f9fa; border-color: rgba(0,0,0,.075); }
    .list-group-item-light:hover { background-color: #e9ecef; }
</style>
<?php
require_once __DIR__ . '/../includes/footer.php';
?>
