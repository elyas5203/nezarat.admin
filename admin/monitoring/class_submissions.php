<?php
// admin/monitoring/class_submissions.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth

$class_id_view = null;
$class_data_view = null;
$self_assessments_admin = []; // Renamed to avoid conflict
$class_observations_admin = [];

if (isset($_GET['class_id']) && is_numeric($_GET['class_id'])) {
    $class_id_view = (int)$_GET['class_id'];

    $stmt_class_info = $conn->prepare("
        SELECT c.ClassID, c.ClassName, c.GradeLevel, c.AcademicYear,
               CONCAT(u.FirstName, ' ', u.LastName) AS TeacherName, u.UserID AS TeacherUserID
        FROM Classes c
        LEFT JOIN Users u ON c.TeacherUserID = u.UserID
        WHERE c.ClassID = ? AND c.IsActive = TRUE
    ");
    if ($stmt_class_info) {
        $stmt_class_info->bind_param("i", $class_id_view);
        $stmt_class_info->execute();
        $result_class_info = $stmt_class_info->get_result();
        if ($result_class_info->num_rows === 1) {
            $class_data_view = $result_class_info->fetch_assoc();

            // Fetch self-assessment submissions for this class
            $stmt_sa_admin = $conn->prepare("
                SELECT fs.SubmissionID, fs.SubmissionDate, f.FormName, f.FormID,
                       CONCAT(u_submitter.FirstName, ' ', u_submitter.LastName) AS SubmitterName, u_submitter.UserID AS SubmitterUserID
                FROM FormSubmissions fs
                JOIN Forms f ON fs.FormID = f.FormID
                JOIN Users u_submitter ON fs.UserID = u_submitter.UserID
                WHERE fs.ClassID = ? AND f.FormPurpose = 'self_assessment'
                ORDER BY fs.SubmissionDate DESC
            ");
            if ($stmt_sa_admin) {
                $stmt_sa_admin->bind_param("i", $class_id_view);
                $stmt_sa_admin->execute();
                $result_sa_admin = $stmt_sa_admin->get_result();
                while ($row_sa_admin = $result_sa_admin->fetch_assoc()) {
                    $self_assessments_admin[] = $row_sa_admin;
                }
                $stmt_sa_admin->close();
            } else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا بارگذاری خوداظهاری‌ها: ' . $conn->error]; }

            // Fetch class observation submissions for this class
            $stmt_co_admin = $conn->prepare("
                SELECT fs.SubmissionID, fs.SubmissionDate, f.FormName, f.FormID,
                       CONCAT(u_submitter.FirstName, ' ', u_submitter.LastName) AS SubmitterName, u_submitter.UserID AS SubmitterUserID
                       /* Add observer name if different from submitter, e.g. from a dedicated field in FormSubmissions or linked table */
                FROM FormSubmissions fs
                JOIN Forms f ON fs.FormID = f.FormID
                JOIN Users u_submitter ON fs.UserID = u_submitter.UserID /* Assuming submitter is the observer */
                WHERE fs.ClassID = ? AND f.FormPurpose = 'class_observation'
                ORDER BY fs.SubmissionDate DESC
            ");
             if ($stmt_co_admin) {
                $stmt_co_admin->bind_param("i", $class_id_view);
                $stmt_co_admin->execute();
                $result_co_admin = $stmt_co_admin->get_result();
                while ($row_co_admin = $result_co_admin->fetch_assoc()) {
                    $class_observations_admin[] = $row_co_admin;
                }
                $stmt_co_admin->close();
            } else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا بارگذاری گزارش بازدیدها: ' . $conn->error]; }


        } else {
            $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'کلاس انتخاب شده یافت نشد یا فعال نیست.'];
            header("Location: index.php"); exit;
        }
        $stmt_class_info->close();
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
    <h1>گزارش‌های کلاس: <?php echo htmlspecialchars($class_data_view['ClassName'] ?? '...'); ?></h1>
    <?php if($class_data_view): ?>
        <p class="page-subtitle">
            مدرس: <a href="<?php echo $admin_base_url . '/users/edit.php?user_id=' . $class_data_view['TeacherUserID']; ?>"><?php echo htmlspecialchars($class_data_view['TeacherName'] ?? '-'); ?></a> |
            پایه: <?php echo htmlspecialchars($class_data_view['GradeLevel'] ?? '-'); ?> |
            سال تحصیلی: <?php echo htmlspecialchars($class_data_view['AcademicYear'] ?? '-'); ?>
        </p>
    <?php endif; ?>
    <div class="page-header-actions">
        <a href="index.php" class="btn btn-secondary">
             <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"></polyline></svg>
            <span>بازگشت به لیست کلاس‌ها</span>
        </a>
        <!-- Add button to "Add Observation" if admin can initiate it -->
    </div>
</div>

<?php if (isset($_SESSION['flash_message'])) { /* Flash message display ... */ } ?>

<div class="card shadow-sm mb-4">
    <div class="card-header"><h5 class="mb-0 card-title-text">فرم‌های خوداظهاری ثبت شده مدرس</h5></div>
    <div class="card-body">
        <?php if (!empty($self_assessments_admin)): ?>
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover">
                    <thead><tr><th>#</th><th>عنوان فرم</th><th>تاریخ ثبت</th><th class="actions-column">مشاهده</th></tr></thead>
                    <tbody>
                        <?php $sa_row_admin = 1; foreach($self_assessments_admin as $sa_admin): ?>
                        <tr>
                            <td><?php echo $sa_row_admin++; ?></td>
                            <td><?php echo htmlspecialchars($sa_admin['FormName']); ?></td>
                            <td><?php echo to_jalali($sa_admin['SubmissionDate'], 'yyyy/MM/dd HH:mm'); ?></td>
                            <td class="actions-cell">
                                <a href="<?php echo $admin_base_url; ?>/forms/view_submission.php?submission_id=<?php echo $sa_admin['SubmissionID']; ?>" class="btn btn-sm btn-info" title="مشاهده جزئیات">
                                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted mb-0">هنوز هیچ فرم خوداظهاری توسط مدرس برای این کلاس ثبت نشده است.</p>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header"><h5 class="mb-0 card-title-text">گزارش‌های بازدید کلاسی</h5></div>
    <div class="card-body">
        <?php if (!empty($class_observations_admin)): ?>
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover">
                     <thead><tr><th>#</th><th>عنوان فرم بازدید</th><th>بازدید کننده/ثبت کننده</th><th>تاریخ بازدید/ثبت</th><th class="actions-column">مشاهده</th></tr></thead>
                    <tbody>
                        <?php $co_row_admin = 1; foreach($class_observations_admin as $co_admin): ?>
                        <tr>
                            <td><?php echo $co_row_admin++; ?></td>
                            <td><?php echo htmlspecialchars($co_admin['FormName']); ?></td>
                            <td><?php echo htmlspecialchars($co_admin['SubmitterName']); ?></td> <!-- Assuming submitter is the observer -->
                            <td><?php echo to_jalali($co_admin['SubmissionDate'], 'yyyy/MM/dd HH:mm'); ?></td>
                            <td class="actions-cell">
                                <a href="<?php echo $admin_base_url; ?>/forms/view_submission.php?submission_id=<?php echo $co_admin['SubmissionID']; ?>" class="btn btn-sm btn-info" title="مشاهده جزئیات">
                                     <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted mb-0">هنوز هیچ گزارش بازدیدی برای این کلاس ثبت نشده است.</p>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
