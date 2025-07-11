<?php
// user/monitoring/my_assigned_observations.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth, $user_base_url

$observer_user_id = get_current_user_id();
// A more robust role check might be needed if not all users can be observers
if (!$observer_user_id ) {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'برای دسترسی به این بخش، لطفا ابتدا وارد شوید.'];
    header("Location: " . ($user_base_url ?? '/my_site/user') . "/dashboard/index.php");
    exit;
}

// Find the ID of the 'class_observation' form
$class_observation_form_id = null;
$form_obs_q_check = $conn->query("SELECT FormID, FormName FROM Forms WHERE FormPurpose = 'class_observation' LIMIT 1");
if($form_obs_q_check && $form_obs_q_check->num_rows > 0){
    $obs_form_data = $form_obs_q_check->fetch_assoc();
    $class_observation_form_id = $obs_form_data['FormID'];
    $class_observation_form_name = $obs_form_data['FormName']; // For display
} else {
    if(empty($_SESSION['flash_message'])){ // Avoid overwriting other important messages
         $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'فرم استاندارد "بازدید کلاسی" در سیستم تعریف نشده است. امکان ثبت گزارش وجود ندارد. لطفاً به ادمین اطلاع دهید.'];
    }
}
$form_obs_q_check->close();


// Fetch observations (Meetings of type 'class_observation_event') assigned to this user
$stmt_obs = $conn->prepare("
    SELECT
        m.MeetingID, m.MeetingName, m.MeetingDate, m.Status AS ObservationStatus,
        c.ClassID, c.ClassName, c.AcademicYear,
        CONCAT(u_teacher.FirstName, ' ', u_teacher.LastName) AS TeacherName,
        (SELECT COUNT(fs.SubmissionID)
         FROM FormSubmissions fs
         WHERE fs.MeetingID = m.MeetingID AND fs.UserID = ? AND fs.ClassID = m.ClassID AND fs.FormID = ?) as SubmissionCount
    FROM Meetings m
    JOIN Classes c ON m.ClassID = c.ClassID
    LEFT JOIN Users u_teacher ON c.TeacherUserID = u_teacher.UserID
    WHERE m.MeetingType = 'class_observation_event'
      AND m.ObserverUserID = ?
      AND m.Status IN ('planned', 'confirmed', 'completed')
    ORDER BY m.MeetingDate DESC
");

$assigned_observations = [];
if ($stmt_obs) {
    $stmt_obs->bind_param("iii", $observer_user_id, $class_observation_form_id, $observer_user_id);
    $stmt_obs->execute();
    $result_obs = $stmt_obs->get_result();
    while ($row_obs = $result_obs->fetch_assoc()) {
        $assigned_observations[] = $row_obs;
    }
    $stmt_obs->close();
} else {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا در بارگذاری لیست بازدیدهای محول شده: ' . $conn->error];
}

$obs_status_options_user_display = ['planned' => 'برنامه‌ریزی شده', 'confirmed' => 'قطعی شده', 'completed' => 'انجام شده', 'cancelled' => 'لغو شده'];
$obs_status_badge_user_display = ['planned' => 'info', 'confirmed' => 'primary', 'completed' => 'success', 'cancelled' => 'secondary'];

// Helper function to get submission ID for an observation
if (!function_exists('get_observation_submission_id')) { // Prevent re-declaration if included elsewhere
    function get_observation_submission_id($db_conn, $meeting_id, $observer_id, $class_id, $form_id) {
        if (!$form_id || !$meeting_id || !$observer_id || !$class_id) return null;
        $stmt_get_sub_id = $db_conn->prepare("SELECT SubmissionID FROM FormSubmissions WHERE MeetingID = ? AND UserID = ? AND ClassID = ? AND FormID = ? ORDER BY SubmissionDate DESC LIMIT 1");
        if ($stmt_get_sub_id) {
            $stmt_get_sub_id->bind_param("iiii", $meeting_id, $observer_id, $class_id, $form_id);
            $stmt_get_sub_id->execute();
            $res_sub_id = $stmt_get_sub_id->get_result();
            if ($row_sub_id = $res_sub_id->fetch_assoc()) return $row_sub_id['SubmissionID'];
            $stmt_get_sub_id->close();
        }
        return null;
    }
}
?>
<div class="page-header">
    <h1>بازدیدهای کلاسی محول شده به شما</h1>
    <p class="page-subtitle">لیست بازدیدهایی که شما به عنوان ناظر/بازدیدکننده برای آنها تعیین شده‌اید.</p>
</div>

<?php if (isset($_SESSION['flash_message'])) { $flash_obs_u = $_SESSION['flash_message']; echo "<div class='alert alert-{$flash_obs_u['type']} alert-dismissible fade show'>{$flash_obs_u['text']}<button type='button' class='close' data-dismiss='alert'>&times;</button></div>"; unset($_SESSION['flash_message']); echo "<script> /* Dismiss JS */ </script>"; } ?>

<div class="card shadow-sm">
    <div class="card-header"><span class="card-title-text">لیست بازدیدها</span></div>
    <div class="card-body">
        <?php if (!empty($assigned_observations)): ?>
            <div class="list-group">
                <?php foreach ($assigned_observations as $obs_item): ?>
                    <div class="list-group-item list-group-item-action flex-column align-items-start mb-2 rounded shadow-sm-hover">
                        <div class="d-flex w-100 justify-content-between">
                            <h5 class="mb-1 font-weight-bold text-primary-user">
                                <?php echo htmlspecialchars($obs_item['MeetingName'] ?: 'بازدید از کلاس ' . $obs_item['ClassName']); ?>
                            </h5>
                            <span class="badge badge-<?php echo $obs_status_badge_user_display[$obs_item['ObservationStatus']] ?? 'light'; ?> p-2">
                                <?php echo $obs_status_options_user_display[$obs_item['ObservationStatus']] ?? $obs_item['ObservationStatus']; ?>
                            </span>
                        </div>
                        <p class="mb-1">
                            <strong>کلاس:</strong> <?php echo htmlspecialchars($obs_item['ClassName'] . ' (' . $obs_item['AcademicYear'] . ')'); ?> <br>
                            <strong>مدرس کلاس:</strong> <?php echo htmlspecialchars($obs_item['TeacherName'] ?? '-'); ?> <br>
                            <strong>تاریخ بازدید:</strong> <?php echo to_jalali($obs_item['MeetingDate'], 'yyyy/MM/dd HH:mm'); ?>
                        </p>

                        <?php if (in_array($obs_item['ObservationStatus'], ['planned', 'confirmed', 'completed'])): ?>
                            <?php if ($class_observation_form_id):
                                $submission_id_obs = get_observation_submission_id($conn, $obs_item['MeetingID'], $observer_user_id, $obs_item['ClassID'], $class_observation_form_id);
                            ?>
                                <?php if (!$submission_id_obs): // If no submission yet ?>
                                    <a href="<?php echo $user_base_url; ?>/forms/fill.php?form_id=<?php echo $class_observation_form_id; ?>&meeting_id=<?php echo $obs_item['MeetingID']; ?>&class_id=<?php echo $obs_item['ClassID']; ?>&source=observation"
                                       class="btn btn-success btn-sm mt-2">
                                        <svg class="icon" width="16" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                        <span>ثبت گزارش بازدید</span>
                                    </a>
                                <?php else: ?>
                                     <a href="<?php echo $user_base_url; ?>/forms/view_submission_user.php?submission_id=<?php echo $submission_id_obs; ?>"
                                       class="btn btn-info btn-sm mt-2 Tooltip-Top" data-tooltip="شما قبلاً گزارش این بازدید را ثبت کرده‌اید.">
                                        <svg class="icon" width="16" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                        <span>مشاهده گزارش ثبت شده</span>
                                    </a>
                                <?php endif; ?>
                            <?php elseif(!$class_observation_form_id && empty($_SESSION['flash_message']) ): // Show error only if no other flash message ?>
                                <div class="alert alert-warning small mt-2 py-1 px-2">فرم بازدید کلاسی تعریف نشده.</div>
                            <?php endif; ?>
                        <?php elseif ($obs_item['ObservationStatus'] === 'cancelled'): ?>
                            <span class="text-muted small mt-2 d-block">این بازدید لغو شده است.</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info mb-0">در حال حاضر هیچ بازدید کلاسی به شما محول نشده است.</div>
        <?php endif; ?>
    </div>
</div>
<style> /* Styles from user/monitoring/index.php */
    .list-group-item-action.shadow-sm-hover { transition: box-shadow 0.2s ease-in-out, transform 0.2s ease-in-out; }
    .list-group-item-action.shadow-sm-hover:hover { box-shadow: 0 .3rem 1rem rgba(0,0,0,.1)!important; transform: translateY(-2px); }
    .text-primary-user { color: var(--user-panel-primary-color, #17a2b8); }
    .icon { vertical-align: -0.125em; margin-left: 5px; }
    .badge.p-2 { padding: 0.4em 0.6em !important; font-size: 0.85em !important;}
</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
