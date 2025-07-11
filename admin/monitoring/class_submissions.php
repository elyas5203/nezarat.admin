<?php
// admin/monitoring/class_submissions.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth, $admin_base_url

$class_id_view = null;
$class_data_view = null;
$self_assessments_admin_cs = [];
$class_observations_admin_cs = [];

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
            $stmt_sa_cs = $conn->prepare("
                SELECT fs.SubmissionID, fs.SubmissionDate, f.FormName, f.FormID,
                       CONCAT(u_submitter.FirstName, ' ', u_submitter.LastName) AS SubmitterName, u_submitter.UserID AS SubmitterUserID
                FROM FormSubmissions fs
                JOIN Forms f ON fs.FormID = f.FormID
                JOIN Users u_submitter ON fs.UserID = u_submitter.UserID
                WHERE fs.ClassID = ? AND f.FormPurpose = 'self_assessment'
                ORDER BY fs.SubmissionDate DESC
            ");
            if ($stmt_sa_cs) {
                $stmt_sa_cs->bind_param("i", $class_id_view); $stmt_sa_cs->execute(); $result_sa_cs = $stmt_sa_cs->get_result();
                while ($row_sa_cs = $result_sa_cs->fetch_assoc()) $self_assessments_admin_cs[] = $row_sa_cs;
                $stmt_sa_cs->close();
            } else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا بارگذاری خوداظهاری‌ها: ' . $conn->error]; }

            // Fetch class observation submissions for this class
            $stmt_co_cs = $conn->prepare("
                SELECT fs.SubmissionID, fs.SubmissionDate, f.FormName, f.FormID,
                       CONCAT(u_submitter.FirstName, ' ', u_submitter.LastName) AS SubmitterName, /* This is the Observer who submitted */
                       m.MeetingName AS ObservationTitle, m.MeetingDate AS ObservationScheduledDate
                FROM FormSubmissions fs
                JOIN Forms f ON fs.FormID = f.FormID
                JOIN Users u_submitter ON fs.UserID = u_submitter.UserID
                LEFT JOIN Meetings m ON fs.MeetingID = m.MeetingID AND m.MeetingType = 'class_observation_event' AND m.ClassID = fs.ClassID
                WHERE fs.ClassID = ? AND f.FormPurpose = 'class_observation'
                ORDER BY fs.SubmissionDate DESC
            ");
             if ($stmt_co_cs) {
                $stmt_co_cs->bind_param("i", $class_id_view); $stmt_co_cs->execute(); $result_co_cs = $stmt_co_cs->get_result();
                while ($row_co_cs = $result_co_cs->fetch_assoc()) $class_observations_admin_cs[] = $row_co_cs;
                $stmt_co_cs->close();
            } else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا بارگذاری گزارش بازدیدها: ' . $conn->error]; }

        } else { $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'کلاس انتخاب شده یافت نشد یا فعال نیست.']; header("Location: index.php"); exit; }
        $stmt_class_info->close();
    } else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا بارگذاری اطلاعات کلاس: ' . $conn->error]; header("Location: index.php"); exit; }
} else { $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'شناسه کلاس مشخص نشده است.']; header("Location: index.php"); exit; }
?>
<div class="page-header">
    <h1>گزارش‌های کلاس: <?php echo htmlspecialchars($class_data_view['ClassName'] ?? '...'); ?></h1>
    <?php if($class_data_view): ?>
        <p class="page-subtitle">
            مدرس: <a href="<?php echo $admin_base_url . '/users/edit.php?user_id=' . ($class_data_view['TeacherUserID'] ?? ''); ?>"><?php echo htmlspecialchars($class_data_view['TeacherName'] ?? '-'); ?></a> |
            پایه: <?php echo htmlspecialchars($class_data_view['GradeLevel'] ?? '-'); ?> |
            سال تحصیلی: <?php echo htmlspecialchars($class_data_view['AcademicYear'] ?? '-'); ?>
        </p>
    <?php endif; ?>
    <div class="page-header-actions">
        <a href="index.php" class="btn btn-secondary">
             <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"></polyline></svg>
            <span>بازگشت به لیست کلاس‌ها</span>
        </a>
         <a href="<?php echo $admin_base_url; ?>/parents/meetings.php?class_filter_id=<?php echo $class_id_view; ?>&meeting_type_filter=class_observation_event" class="btn btn-outline-primary">
            <svg class="icon" viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
            <span>برنامه‌ریزی بازدید جدید</span>
        </a>
    </div>
</div>

<?php if (isset($_SESSION['flash_message'])) { $flash_cs = $_SESSION['flash_message']; echo "<div class='alert alert-{$flash_cs['type']} alert-dismissible fade show'>{$flash_cs['text']}<button type='button' class='close' data-dismiss='alert'>&times;</button></div>"; unset($_SESSION['flash_message']); echo "<script>/*Dismiss JS*/</script>";} ?>

<div class="card shadow-sm mb-4">
    <div class="card-header"><h5 class="mb-0 card-title-text">فرم‌های خوداظهاری ثبت شده مدرس</h5></div>
    <div class="card-body">
        <?php if (!empty($self_assessments_admin_cs)): ?>
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover">
                    <thead><tr><th>#</th><th>عنوان فرم</th><th>تاریخ ثبت</th><th class="actions-column">مشاهده</th></tr></thead>
                    <tbody>
                        <?php $sa_row_admin_cs_idx = 1; foreach($self_assessments_admin_cs as $sa_admin_cs_item): ?>
                        <tr>
                            <td><?php echo $sa_row_admin_cs_idx++; ?></td>
                            <td><?php echo htmlspecialchars($sa_admin_cs_item['FormName']); ?></td>
                            <td><?php echo to_jalali($sa_admin_cs_item['SubmissionDate'], 'yyyy/MM/dd HH:mm'); ?></td>
                            <td class="actions-cell">
                                <a href="<?php echo $admin_base_url; ?>/forms/view_submission.php?submission_id=<?php echo $sa_admin_cs_item['SubmissionID']; ?>" class="btn btn-sm btn-info" title="مشاهده جزئیات">
                                    <svg class="icon" width="16" height="16" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                </a></td></tr> <?php endforeach; ?></tbody></table></div>
        <?php else: ?><p class="text-muted mb-0">هنوز فرم خوداظهاری توسط مدرس برای این کلاس ثبت نشده.</p><?php endif; ?></div></div>

<div class="card shadow-sm">
    <div class="card-header"><h5 class="mb-0 card-title-text">گزارش‌های بازدید کلاسی ثبت شده</h5></div>
    <div class="card-body">
        <?php if (!empty($class_observations_admin_cs)): ?>
            <div class="table-responsive"><table class="table table-sm table-striped table-hover">
                <thead><tr><th>#</th><th>فرم بازدید</th><th>بازدیدکننده</th><th>تاریخ بازدید/ثبت</th><th class="actions-column">مشاهده</th></tr></thead>
                <tbody><?php $co_row_admin_cs_idx = 1; foreach($class_observations_admin_cs as $co_admin_cs_item): ?><tr>
                    <td><?php echo $co_row_admin_cs_idx++; ?></td>
                    <td><?php echo htmlspecialchars($co_admin_cs_item['FormName']); ?>
                        <?php if($co_admin_cs_item['ObservationTitle'] && $co_admin_cs_item['ObservationTitle'] != $co_admin_cs_item['FormName']): ?>
                            <small class="d-block text-muted">مربوط به: <?php echo htmlspecialchars($co_admin_cs_item['ObservationTitle']); ?> (<?php echo to_jalali($co_admin_cs_item['ObservationScheduledDate'] ?? $co_admin_cs_item['SubmissionDate'], 'yy/MM/dd'); ?>)</small>
                        <?php endif; ?></td>
                    <td><?php echo htmlspecialchars($co_admin_cs_item['SubmitterName']); ?></td>
                    <td><?php echo to_jalali($co_admin_cs_item['SubmissionDate'], 'yyyy/MM/dd HH:mm'); ?></td>
                    <td class="actions-cell">
                        <a href="<?php echo $admin_base_url; ?>/forms/view_submission.php?submission_id=<?php echo $co_admin_cs_item['SubmissionID']; ?>" class="btn btn-sm btn-info" title="مشاهده جزئیات">
                             <svg class="icon" width="16" height="16" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </a></td></tr> <?php endforeach; ?></tbody></table></div>
        <?php else: ?><p class="text-muted mb-0">هنوز گزارش بازدیدی برای این کلاس ثبت نشده.</p><?php endif; ?></div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
