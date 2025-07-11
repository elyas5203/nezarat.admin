<?php
// user/monitoring/assessment_history.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth

$teacher_user_id = get_current_user_id();
$class_id_history = null;
$class_data_history = null;
$assessment_history = [];

if (!$teacher_user_id || get_current_user_type() !== 'teacher') {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'این بخش مخصوص مدرسین است.'];
    header("Location: " . ($user_base_url ?? '/my_site/user') . "/dashboard/index.php");
    exit;
}

if (isset($_GET['class_id']) && is_numeric($_GET['class_id'])) {
    $class_id_history = (int)$_GET['class_id'];

    $stmt_class = $conn->prepare("SELECT ClassID, ClassName, GradeLevel, AcademicYear FROM Classes WHERE ClassID = ? AND TeacherUserID = ? AND IsActive = TRUE");
    if ($stmt_class) {
        $stmt_class->bind_param("ii", $class_id_history, $teacher_user_id);
        $stmt_class->execute();
        $result_class = $stmt_class->get_result();
        if ($result_class->num_rows === 1) {
            $class_data_history = $result_class->fetch_assoc();

            $stmt_history = $conn->prepare("
                SELECT fs.SubmissionID, fs.SubmissionDate, f.FormName, f.FormID
                FROM FormSubmissions fs
                JOIN Forms f ON fs.FormID = f.FormID
                WHERE fs.ClassID = ? AND fs.UserID = ? AND f.FormPurpose = 'self_assessment'
                ORDER BY fs.SubmissionDate DESC
            ");
            if ($stmt_history) {
                $stmt_history->bind_param("ii", $class_id_history, $teacher_user_id);
                $stmt_history->execute();
                $result_history = $stmt_history->get_result();
                while ($row = $result_history->fetch_assoc()) {
                    $assessment_history[] = $row;
                }
                $stmt_history->close();
            } else {
                $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا بارگذاری تاریخچه: ' . $conn->error];
            }
        } else {
            $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'کلاس یافت نشد یا شما مدرس آن نیستید.'];
            header("Location: index.php"); exit;
        }
        $stmt_class->close();
    } else {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا بارگذاری اطلاعات کلاس: ' . $conn->error];
        header("Location: index.php"); exit;
    }
} else {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'شناسه کلاس مشخص نشده.'];
    header("Location: index.php"); exit;
}
?>
<div class="page-header">
    <h1>تاریخچه خوداظهاری برای کلاس: <?php echo htmlspecialchars($class_data_history['ClassName'] ?? '...'); ?></h1>
    <?php if($class_data_history): ?>
        <p class="page-subtitle"> پایه: <?php echo htmlspecialchars($class_data_history['GradeLevel'] ?? '-'); ?> | سال تحصیلی: <?php echo htmlspecialchars($class_data_history['AcademicYear'] ?? '-'); ?> </p>
    <?php endif; ?>
    <div class="page-header-actions">
        <a href="index.php" class="btn btn-secondary mr-2">
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"></polyline></svg>
            <span>بازگشت به کلاس‌ها</span>
        </a>
        <a href="submit_self_assessment.php?class_id=<?php echo $class_id_history; ?>" class="btn btn-success">
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
            <span>ثبت خوداظهاری جدید</span>
        </a>
    </div>
</div>

<?php
if (isset($_SESSION['flash_message'])) { /* Flash message display logic */
    $flash_hist = $_SESSION['flash_message'];
    echo "<div class='alert alert-{$flash_hist['type']} alert-dismissible fade show' role='alert'>{$flash_hist['text']}
          <button type='button' class='close' data-dismiss='alert' aria-label='Close' style='/* ... */'><span aria-hidden='true'>&times;</span></button></div>";
    unset($_SESSION['flash_message']);
     echo "<script> /* JS for alert dismissal ... */ </script>";
}
if (isset($_GET['action_status']) && $_GET['action_status'] == 'success'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars(urldecode($_GET['message'] ?? 'عملیات موفق.')); ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header"><span class="card-title-text">سوابق ثبت شده خوداظهاری</span></div>
    <div class="card-body">
        <?php if (!empty($assessment_history)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>عنوان فرم خوداظهاری</th>
                            <th>تاریخ ثبت</th>
                            <th class="actions-column">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $history_row_num = 1; foreach ($assessment_history as $entry): ?>
                            <tr>
                                <td><?php echo $history_row_num++; ?></td>
                                <td><?php echo htmlspecialchars($entry['FormName']); ?></td>
                                <td><?php echo to_jalali($entry['SubmissionDate'], 'yyyy/MM/dd HH:mm'); ?></td>
                                <td class="actions-cell">
                                     <a href="<?php echo ($user_base_url ?? '/my_site/user'); ?>/forms/view_submission_user.php?submission_id=<?php echo $entry['SubmissionID']; ?>"
                                       class="btn btn-sm btn-info" title="مشاهده پاسخ ثبت شده">
                                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                        <span>مشاهده</span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-light mb-0">هنوز هیچ فرم خوداظهاری برای این کلاس توسط شما ثبت نشده است.</div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
