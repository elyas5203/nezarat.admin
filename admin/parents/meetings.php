<?php
// admin/parents/meetings.php (Now also handles class observations)
require_once __DIR__ . '/../includes/header.php';

$csrf_token_meetings = generate_csrf_token('meetings_general_action'); // Changed token name for broader scope

$errors_mtg = [];
$edit_mode_mtg = false;
$meeting_to_edit_values_default = [
    'MeetingID' => null, 'MeetingName' => '', 'ClassID' => '',
    'MeetingDate' => '', 'MeetingTime' => '', 'Location' => '',
    'Speaker' => '', 'ObserverUserID' => null, 'Description' => '',
    'Status' => 'planned', 'MeetingType' => 'parents_meeting' // Default type
];
$meeting_to_edit_values = $meeting_to_edit_values_default;

$classes_q_mtg = $conn->query("SELECT ClassID, ClassName, AcademicYear FROM Classes WHERE IsActive = TRUE ORDER BY AcademicYear DESC, ClassName ASC");
$available_classes_mtg = [];
if ($classes_q_mtg) { while($c_mtg = $classes_q_mtg->fetch_assoc()) $available_classes_mtg[$c_mtg['ClassID']] = $c_mtg; $classes_q_mtg->close(); }

$meeting_type_options_admin_display = [ // For display in select
    'parents_meeting' => 'جلسه اولیا',
    'class_observation_event' => 'بازدید کلاسی (نظارت)',
    'other_meeting' => 'جلسه/رویداد دیگر' // Generic type
];
$meeting_status_options_admin_display = ['planned' => 'برنامه‌ریزی شده', 'confirmed' => 'قطعی شده', 'completed' => 'انجام شده', 'cancelled' => 'لغو شده'];
$status_badge_map_mtg_display = ['planned' => 'primary', 'confirmed' => 'info', 'completed' => 'success', 'cancelled' => 'danger'];

$observers_q_admin = $conn->query("SELECT UserID, FirstName, LastName, Username FROM Users WHERE IsActive = TRUE AND UserType IN ('admin', 'manager', 'deputy', 'teacher') ORDER BY LastName, FirstName"); // Example roles for observers
$available_observers_admin = [];
if ($observers_q_admin) { while($obs_admin = $observers_q_admin->fetch_assoc()) $available_observers_admin[$obs_admin['UserID']] = $obs_admin['FirstName'].' '.$obs_admin['LastName'] . ' (@'.$obs_admin['Username'].')'; $observers_q_admin->close(); }

// Department ID: This might vary based on MeetingType. For 'parents_meeting', it's 'اولیا'. For 'class_observation_event', it might be 'نظارت' or general.
// For simplicity, we might not strictly tie observations to a department via Meetings.DepartmentID, or use a general/null one.
// Let's assume DepartmentID is optional for observations or handled differently.
$parents_dept_id_val = null; /* ... fetch 'اولیا' department ID ... */
$monitoring_dept_id_val = null; /* ... fetch 'نظارت' department ID ... */


$filter_class_id_from_monitoring_page = isset($_GET['class_filter_id']) && is_numeric($_GET['class_filter_id']) ? (int)$_GET['class_filter_id'] : null;
if ($filter_class_id_from_monitoring_page && $_SERVER["REQUEST_METHOD"] != "POST" && !$edit_mode_mtg) {
    $meeting_to_edit_values['ClassID'] = $filter_class_id_from_monitoring_page;
    // Auto-fill MeetingName based on default MeetingType and selected Class
    if(isset($available_classes_mtg[$filter_class_id_from_monitoring_page])) {
         $default_mt_text = $meeting_type_options_admin_display[$meeting_to_edit_values['MeetingType']] ?? 'جلسه';
         $meeting_to_edit_values['MeetingName'] = $default_mt_text . ' کلاس ' . $available_classes_mtg[$filter_class_id_from_monitoring_page]['ClassName'];
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_meeting'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'meetings_general_action')) {
        $errors_mtg[] = 'خطای CSRF!';
    } else {
        $meeting_id_post_val = isset($_POST['meeting_id']) && is_numeric($_POST['meeting_id']) ? (int)$_POST['meeting_id'] : null;

        // Repopulate $meeting_to_edit_values with POST data
        $meeting_to_edit_values['MeetingName'] = sanitize_input($_POST['MeetingName'] ?? '');
        $meeting_to_edit_values['ClassID'] = isset($_POST['ClassID']) ? (int)$_POST['ClassID'] : null;
        $meeting_to_edit_values['MeetingDate'] = sanitize_input($_POST['MeetingDate'] ?? '');
        $meeting_to_edit_values['MeetingTime'] = sanitize_input($_POST['MeetingTime'] ?? '');
        $meeting_to_edit_values['Location'] = sanitize_input($_POST['Location'] ?? '');
        $meeting_to_edit_values['Speaker'] = sanitize_input($_POST['Speaker'] ?? '');
        $meeting_to_edit_values['MeetingType'] = sanitize_input($_POST['MeetingType'] ?? 'parents_meeting');
        $meeting_to_edit_values['ObserverUserID'] = (!empty($_POST['ObserverUserID']) && $meeting_to_edit_values['MeetingType'] === 'class_observation_event') ? (int)$_POST['ObserverUserID'] : null;
        $meeting_to_edit_values['Description'] = sanitize_input($_POST['Description'] ?? '');
        $meeting_to_edit_values['Status'] = sanitize_input($_POST['Status'] ?? 'planned');
        if($meeting_id_post_val) $meeting_to_edit_values['MeetingID'] = $meeting_id_post_val;
        $edit_mode_mtg = ($meeting_id_post_val !== null);

        // Validation
        if (empty($meeting_to_edit_values['MeetingName'])) $errors_mtg[] = "عنوان جلسه/بازدید الزامی است.";
        if (empty($meeting_to_edit_values['ClassID']) || !isset($available_classes_mtg[$meeting_to_edit_values['ClassID']])) $errors_mtg[] = "کلاس نامعتبر.";
        if (empty($meeting_to_edit_values['MeetingDate'])) $errors_mtg[] = "تاریخ الزامی است.";
        // ... other validations for date, time, status, observer based on type ...
        if (!array_key_exists($meeting_to_edit_values['MeetingType'], $meeting_type_options_admin_display)) $errors_mtg[] = "نوع جلسه/بازدید نامعتبر.";
        if ($meeting_to_edit_values['MeetingType'] === 'class_observation_event' && $meeting_to_edit_values['ObserverUserID'] !== null && !isset($available_observers_admin[$meeting_to_edit_values['ObserverUserID']])) {
            $errors_mtg[] = "بازدیدکننده انتخاب شده برای بازدید کلاسی نامعتبر است.";
        }

        $meeting_datetime_db_val = $meeting_to_edit_values['MeetingDate'];
        // ... (Combine Date and Time into $meeting_datetime_db_val as before) ...

        if (empty($errors_mtg)) {
            // Determine DepartmentID based on MeetingType (simplified)
            $department_id_for_meeting = null;
            if ($meeting_to_edit_values['MeetingType'] === 'parents_meeting') $department_id_for_meeting = $parents_dept_id_val;
            // For class_observation_event, DepartmentID might be null or a "Monitoring" department
            // For 'other_meeting', it could be null or selected by admin.

            if ($meeting_id_post_val) {
                $stmt_db_mtg = $conn->prepare("UPDATE Meetings SET MeetingName=?, ClassID=?, MeetingDate=?, Location=?, Speaker=?, ObserverUserID=?, Description=?, Status=?, MeetingType=?, DepartmentID=? WHERE MeetingID=?");
                if ($stmt_db_mtg) { $stmt_db_mtg->bind_param("sisssisssii", $meeting_to_edit_values['MeetingName'], $meeting_to_edit_values['ClassID'], $meeting_datetime_db_val, $meeting_to_edit_values['Location'], $meeting_to_edit_values['Speaker'], $meeting_to_edit_values['ObserverUserID'], $meeting_to_edit_values['Description'], $meeting_to_edit_values['Status'], $meeting_to_edit_values['MeetingType'], $department_id_for_meeting, $meeting_id_post_val);
                    // ... (execute, set flash, redirect) ...
                } // ...
            } else {
                $stmt_db_mtg = $conn->prepare("INSERT INTO Meetings (MeetingName, ClassID, MeetingDate, Location, Speaker, ObserverUserID, Description, Status, MeetingType, DepartmentID, CreatedAt, UpdatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                if ($stmt_db_mtg) { $stmt_db_mtg->bind_param("sisssisssi", $meeting_to_edit_values['MeetingName'], $meeting_to_edit_values['ClassID'], $meeting_datetime_db_val, $meeting_to_edit_values['Location'], $meeting_to_edit_values['Speaker'], $meeting_to_edit_values['ObserverUserID'], $meeting_to_edit_values['Description'], $meeting_to_edit_values['Status'], $meeting_to_edit_values['MeetingType'], $department_id_for_meeting);
                    // ... (execute, set flash, redirect) ...
                } // ...
            }
             if(empty($errors_mtg)) { /* ... redirect with flash ... */ }
        }
    }
    $csrf_token_meetings = regenerate_csrf_token('meetings_general_action');
}

// ... (GET Edit/Delete logic - similar to before, but use $meeting_to_edit_values) ...
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id']) && $_SERVER["REQUEST_METHOD"] != "POST") {
    $edit_id_mtg_get = (int)$_GET['edit_id'];
    $stmt_edit_mtg_get = $conn->prepare("SELECT * FROM Meetings WHERE MeetingID = ?");
    if ($stmt_edit_mtg_get) {
        $stmt_edit_mtg_get->bind_param("i", $edit_id_mtg_get); $stmt_edit_mtg_get->execute(); $result_edit_mtg_get = $stmt_edit_mtg_get->get_result();
        if ($data_mtg_get = $result_edit_mtg_get->fetch_assoc()) {
            $meeting_to_edit_values = $data_mtg_get;
            if ($meeting_to_edit_values['MeetingDate']) { $dt_obj_edit_get = new DateTime($meeting_to_edit_values['MeetingDate']); $meeting_to_edit_values['MeetingDate'] = $dt_obj_edit_get->format('Y-m-d'); $meeting_to_edit_values['MeetingTime'] = $dt_obj_edit_get->format('H:i'); if ($meeting_to_edit_values['MeetingTime'] == '00:00') $meeting_to_edit_values['MeetingTime'] = '';}
            $edit_mode_mtg = true;
        } else $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "مورد یافت نشد."];
        $stmt_edit_mtg_get->close();
    } else $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "خطا بارگذاری: " . $conn->error];
}


// Fetch meetings for display
$sql_meetings_list_admin_page = "SELECT m.*, c.ClassName, c.AcademicYear, CONCAT(u_obs.FirstName, ' ', u_obs.LastName) AS ObserverName FROM Meetings m JOIN Classes c ON m.ClassID = c.ClassID LEFT JOIN Users u_obs ON m.ObserverUserID = u_obs.UserID ";
$params_meetings_list = []; $types_meetings_list = "";
if ($filter_class_id_from_monitoring_page) { $sql_meetings_list_admin_page .= " WHERE m.ClassID = ?"; $params_meetings_list[] = $filter_class_id_from_monitoring_page; $types_meetings_list .= "i"; }
$sql_meetings_list_admin_page .= " ORDER BY m.MeetingDate DESC LIMIT 100";
$stmt_meetings_list = $conn->prepare($sql_meetings_list_admin_page);
$meetings_list_display = [];
if($stmt_meetings_list){ if(!empty($types_meetings_list)) $stmt_meetings_list->bind_param($types_meetings_list, ...$params_meetings_list); $stmt_meetings_list->execute(); $res_ml = $stmt_meetings_list->get_result(); while($ml_row = $res_ml->fetch_assoc()) $meetings_list_display[] = $ml_row; $stmt_meetings_list->close();}

?>
<div class="page-header"><h1>مدیریت جلسات و بازدیدها <?php /* ... filter display ... */ ?></h1>
    <div class="page-header-actions"><a href="<?php echo $admin_base_url; ?>/monitoring/index.php" class="btn btn-outline-secondary">نظارت بر کلاس‌ها</a></div></div>

<?php /* ... Flash messages and errors ... */ ?>

<div class="row"><div class="col-lg-5 mb-4"><div class="card shadow-sm">
    <div class="card-header"><span class="card-title-text"><?php echo $edit_mode_mtg ? 'ویرایش' : 'افزودن جدید'; ?></span></div><div class="card-body">
    <form action="meetings.php<?php /* ... action URL ... */ ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_meetings; ?>">
        <?php if ($edit_mode_mtg && $meeting_to_edit_values['MeetingID']): ?><input type="hidden" name="meeting_id" value="<?php echo $meeting_to_edit_values['MeetingID']; ?>"><?php endif; ?>

        <div class="form-group"><label for="MeetingType_admin_form_id">نوع <span class="text-danger">*</span></label><select name="MeetingType" id="MeetingType_admin_form_id" class="form-control custom-select" required onchange="toggleObserverFieldAdmin(this.value)">
            <?php foreach($meeting_type_options_admin_display as $mt_key_form => $mt_val_form): ?><option value="<?php echo $mt_key_form; ?>" <?php if($meeting_to_edit_values['MeetingType'] == $mt_key_form) echo 'selected';?>><?php echo $mt_val_form; ?></option><?php endforeach; ?></select></div>

        <div class="form-group"><label for="meeting_name_mtg_form_id">عنوان <span class="text-danger">*</span></label><input type="text" class="form-control" id="meeting_name_mtg_form_id" name="MeetingName" value="<?php echo htmlspecialchars($meeting_to_edit_values['MeetingName']); ?>" required></div>
        <div class="form-group"><label for="class_id_mtg_form_page">کلاس <span class="text-danger">*</span></label><select name="ClassID" id="class_id_mtg_form_page" class="form-control custom-select" required <?php if($filter_class_id_from_monitoring_page && !$edit_mode_mtg) echo "disabled"; ?>>
            <option value="">-- انتخاب --</option><?php foreach($available_classes_mtg as $cls_id_m_form => $cls_m_data_form): ?><option value="<?php echo $cls_id_m_form; ?>" <?php if($meeting_to_edit_values['ClassID'] == $cls_id_m_form) echo 'selected';?>><?php echo htmlspecialchars($cls_m_data_form['ClassName'] . ' (' . $cls_m_data_form['AcademicYear'] . ')'); ?></option><?php endforeach; ?></select>
            <?php if($filter_class_id_from_monitoring_page && !$edit_mode_mtg): ?> <input type="hidden" name="ClassID" value="<?php echo $filter_class_id_from_monitoring_page; ?>"> <?php endif; ?></div>

        <div class="form-row"><div class="form-group col-md-7"><label for="meeting_date_mtg_page">تاریخ <span class="text-danger">*</span></label><input type="text" class="form-control persian-date-picker" id="meeting_date_mtg_page" name="MeetingDate" value="<?php echo htmlspecialchars($meeting_to_edit_values['MeetingDate']); ?>" placeholder="YYYY-MM-DD" required></div><div class="form-group col-md-5"><label for="meeting_time_mtg_page">زمان</label><input type="time" class="form-control" id="meeting_time_mtg_page" name="MeetingTime" value="<?php echo htmlspecialchars($meeting_to_edit_values['MeetingTime']); ?>"></div></div>

        <div class="form-group" id="observer_field_group_admin" style="<?php echo ($meeting_to_edit_values['MeetingType'] ?? '') === 'class_observation_event' ? 'display:block;' : 'display:none;'; ?>">
            <label for="ObserverUserID_admin_form_id">بازدیدکننده/ناظر</label><select name="ObserverUserID" id="ObserverUserID_admin_form_id" class="form-control custom-select"><option value="">-- انتخاب --</option><?php foreach($available_observers_admin as $obs_id_form => $obs_name_form): ?><option value="<?php echo $obs_id_form; ?>" <?php if(($meeting_to_edit_values['ObserverUserID'] ?? null) == $obs_id_form) echo 'selected';?>><?php echo htmlspecialchars($obs_name_form);?></option><?php endforeach; ?></select></div>

        <div class="form-group speaker_field_group_admin" style="<?php echo ($meeting_to_edit_values['MeetingType'] ?? '') === 'parents_meeting' ? 'display:block;' : 'display:none;'; ?>"><label for="speaker_mtg_form_id">سخنران</label><input type="text" class="form-control" id="speaker_mtg_form_id" name="Speaker" value="<?php echo htmlspecialchars($meeting_to_edit_values['Speaker']); ?>"></div>
        <div class="form-group"><label for="location_mtg_form_id">مکان</label><input type="text" class="form-control" id="location_mtg_form_id" name="Location" value="<?php echo htmlspecialchars($meeting_to_edit_values['Location']); ?>"></div>
        <div class="form-group"><label for="description_mtg_form_page">توضیحات</label><textarea class="form-control" id="description_mtg_form_page" name="Description" rows="2"><?php echo htmlspecialchars($meeting_to_edit_values['Description']); ?></textarea></div>
        <div class="form-group"><label for="status_mtg_form_page">وضعیت <span class="text-danger">*</span></label><select name="Status" id="status_mtg_form_page" class="form-control custom-select" required><?php foreach($meeting_status_options_admin_display as $s_key_m_form => $s_val_m_form): ?><option value="<?php echo $s_key_m_form; ?>" <?php if($meeting_to_edit_values['Status'] == $s_key_m_form) echo 'selected';?>><?php echo $s_val_m_form; ?></option><?php endforeach; ?></select></div>
        <div class="form-actions"><button type="submit" name="submit_meeting" class="btn btn-primary"><?php echo $edit_mode_mtg ? 'ذخیره' : 'ایجاد'; ?></button><?php if ($edit_mode_mtg): ?><a href="meetings.php<?php if($filter_class_id_from_monitoring_page) echo '?class_filter_id='.$filter_class_id_from_monitoring_page; ?>" class="btn btn-outline-secondary">لغو</a><?php endif; ?></div>
    </form></div></div></div>
    <div class="col-lg-7"><div class="card shadow-sm"><div class="card-header"><span class="card-title-text">لیست جلسات و بازدیدها <?php /* ... filter display ... */ ?></span></div><div class="card-body">
    <?php if (!empty($meetings_list_display)): ?><div class="table-responsive"><table class="table table-sm table-striped table-hover">
        <thead><tr><th>#</th><th>عنوان/نوع</th><th>کلاس</th><th>تاریخ</th><th>بازدیدکننده/سخنران</th><th>وضعیت</th><th>گزارش‌ها</th><th>عملیات</th></tr></thead><tbody>
        <?php $mtg_row_idx_disp = 1; foreach ($meetings_list_display as $mtg_item_disp): ?><tr>
            <td><?php echo $mtg_row_idx_disp++; ?></td>
            <td><strong><?php echo htmlspecialchars($mtg_item_disp['MeetingName']); ?></strong><small class="d-block text-info font-weight-bold"><?php echo $meeting_type_options_admin_display[$mtg_item_disp['MeetingType']] ?? $mtg_item_disp['MeetingType'];?></small></td>
            <td><?php echo htmlspecialchars($mtg_item_disp['ClassName']); ?> <small>(<?php echo $mtg_item_disp['AcademicYear'];?>)</small></td>
            <td><?php echo to_jalali($mt_item_disp['MeetingDate'], 'yyyy/MM/dd HH:mm'); ?></td>
            <td><small><?php echo $mtg_item_disp['MeetingType'] === 'class_observation_event' ? htmlspecialchars($mtg_item_disp['ObserverName'] ?? '-') : htmlspecialchars($mtg_item_disp['Speaker'] ?? '-'); ?></small></td>
            <td><span class="badge badge-<?php echo $status_badge_map_mtg_display[$mtg_item_disp['Status']] ?? 'light'; ?> p-2"><?php echo $meeting_status_options_admin_display[$mtg_item_disp['Status']] ?? $mtg_item_disp['Status']; ?></span></td>
            <td><?php /* ... Report links based on MeetingType and Status ... */ ?></td>
            <td class="actions-cell"> <!-- Edit/Delete buttons --> </td></tr><?php endforeach; ?></tbody></table></div>
    <?php else: ?><p class="text-muted text-center">موردی یافت نشد.</p><?php endif; ?>
    </div></div></div></div>
<link rel="stylesheet" href="https://unpkg.com/persian-datepicker@latest/dist/css/persian-datepicker.min.css"/>
<script src="https://unpkg.com/persian-datepicker@latest/dist/js/persian-datepicker.min.js"></script>
<script type="text/javascript">
function toggleObserverFieldAdmin(meetingType) {
    const observerGroup = document.getElementById('observer_field_group_admin');
    const speakerGroup = document.querySelector('.speaker_field_group_admin'); // Assuming class for speaker group
    if (observerGroup) observerGroup.style.display = (meetingType === 'class_observation_event') ? 'block' : 'none';
    if (speakerGroup) speakerGroup.style.display = (meetingType === 'parents_meeting' || meetingType === 'other_meeting') ? 'block' : 'none'; // Show speaker for parents or other
    // Auto-suggest MeetingName (optional improvement)
}
document.addEventListener('DOMContentLoaded', function() {
    const initialMeetingTypeCtrl = document.getElementById('MeetingType_admin_form_id');
    if(initialMeetingTypeCtrl) toggleObserverFieldAdmin(initialMeetingTypeCtrl.value);
    /* ... datepicker and alert init ... */
});
</script>
<style>/* ... styles ... */</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
