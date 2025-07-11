<?php
// admin/inservice/events.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth

$csrf_token_is_events = generate_csrf_token('inservice_events_action');

$errors_is_evt = [];
$success_message_is_evt = ''; // Will use flash messages for redirects
$edit_mode_is_evt = false;
$event_to_edit_values_default = [
    'EventID' => null, 'DepartmentID' => null,
    'EventName' => '', 'EventDate' => '', 'EventTime' => '', 'Location' => '',
    'Speaker' => '', 'Status' => 'planned', 'Notes' => ''
];
$event_to_edit_values = $event_to_edit_values_default;

$inservice_department_id = null;
$stmt_is_dept = $conn->prepare("SELECT DepartmentID FROM Departments WHERE DepartmentName LIKE '%ضمن خدمت%' OR DepartmentName LIKE '%امید تدریس%' LIMIT 1");
if ($stmt_is_dept) {
    $stmt_is_dept->execute(); $res_isd = $stmt_is_dept->get_result();
    if ($isd_row = $res_isd->fetch_assoc()) $inservice_department_id = $isd_row['DepartmentID'];
    $stmt_is_dept->close();
}
if (!$inservice_department_id) {
    // Try to create "ضمن خدمت" department if it doesn't exist
    $stmt_create_dept = $conn->prepare("INSERT INTO Departments (DepartmentName, Description) VALUES ('ضمن خدمت', 'بخش مربوط به جلسات و رویدادهای ضمن خدمت مدرسین')");
    if ($stmt_create_dept && $stmt_create_dept->execute()) {
        $inservice_department_id = $stmt_create_dept->insert_id;
    } else {
        error_log("Failed to find or create 'ضمن خدمت' department. Using fallback ID 2. Error: " . ($stmt_create_dept ? $stmt_create_dept->error : $conn->error));
        $inservice_department_id = 2; // Fallback, ensure this department ID makes sense or exists
    }
    if($stmt_create_dept) $stmt_create_dept->close();
}
$event_to_edit_values['DepartmentID'] = $inservice_department_id;

$event_status_options = ['planned' => 'برنامه‌ریزی شده', 'confirmed' => 'قطعی شده', 'completed' => 'انجام شده', 'cancelled' => 'لغو شده'];
$status_badge_map_is_evt = ['planned' => 'primary', 'confirmed' => 'info', 'completed' => 'success', 'cancelled' => 'danger'];


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_inservice_event'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'inservice_events_action')) {
        $errors_is_evt[] = 'خطای CSRF!';
    } else {
        $event_id_post = isset($_POST['event_id']) && is_numeric($_POST['event_id']) ? (int)$_POST['event_id'] : null;

        $event_to_edit_values['EventName'] = sanitize_input($_POST['EventName'] ?? '');
        $event_to_edit_values['EventDate'] = sanitize_input($_POST['EventDate'] ?? '');
        $event_to_edit_values['EventTime'] = sanitize_input($_POST['EventTime'] ?? '');
        $event_to_edit_values['Location'] = sanitize_input($_POST['Location'] ?? '');
        $event_to_edit_values['Speaker'] = sanitize_input($_POST['Speaker'] ?? '');
        $event_to_edit_values['Status'] = sanitize_input($_POST['Status'] ?? 'planned');
        $event_to_edit_values['Notes'] = sanitize_input($_POST['Notes'] ?? '');
        if($event_id_post) $event_to_edit_values['EventID'] = $event_id_post;
        $edit_mode_is_evt = ($event_id_post !== null);


        if (empty($event_to_edit_values['EventName'])) $errors_is_evt[] = "نام رویداد الزامی است.";
        if (empty($event_to_edit_values['EventDate'])) $errors_is_evt[] = "تاریخ رویداد الزامی است.";
        if (!empty($event_to_edit_values['EventDate']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_to_edit_values['EventDate'])) $errors_is_evt[] = "فرمت تاریخ (YYYY-MM-DD).";
        if (!empty($event_to_edit_values['EventTime']) && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $event_to_edit_values['EventTime'])) $errors_is_evt[] = "فرمت زمان (HH:MM).";
        if (!array_key_exists($event_to_edit_values['Status'], $event_status_options)) $errors_is_evt[] = "وضعیت نامعتبر.";

        $event_datetime_db_is = $event_to_edit_values['EventDate'];
        if (!empty($event_to_edit_values['EventTime']) && empty($errors_is_evt['EventDate']) && empty($errors_is_evt['EventTime'])) {
            try { $dt_obj_is_post = new DateTime($event_to_edit_values['EventDate'] . ' ' . $event_to_edit_values['EventTime']); $event_datetime_db_is = $dt_obj_is_post->format('Y-m-d H:i:s');}
            catch (Exception $e) { if(!empty($event_to_edit_values['EventTime'])) $errors_is_evt[] = "فرمت زمان صحیح نیست."; }
        } elseif (empty($errors_is_evt['EventDate']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_to_edit_values['EventDate'])) {
            $event_datetime_db_is = $event_to_edit_values['EventDate'] . ' 00:00:00';
        } elseif(empty($errors_is_evt['EventDate'])) { $errors_is_evt[] = "تاریخ برای پردازش معتبر نیست.";}

        if (empty($errors_is_evt)) {
            if ($event_id_post) {
                $stmt_is_post = $conn->prepare("UPDATE EventCalendar SET EventName=?, EventDate=?, Location=?, Speaker=?, Status=?, Notes=? WHERE EventID=? AND DepartmentID=?");
                if($stmt_is_post) { $stmt_is_post->bind_param("ssssssii", $event_to_edit_values['EventName'], $event_datetime_db_is, $event_to_edit_values['Location'], $event_to_edit_values['Speaker'], $event_to_edit_values['Status'], $event_to_edit_values['Notes'], $event_id_post, $inservice_department_id);
                    if($stmt_is_post->execute()) { $_SESSION['flash_message'] = ['type'=>'success', 'text'=>'رویداد ویرایش شد.']; header("Location: events.php"); exit;}
                    else $errors_is_evt[] = "خطا ویرایش: ".$stmt_is_post->error; $stmt_is_post->close();
                } else $errors_is_evt[] = "خطا آماده سازی ویرایش: ".$conn->error;
            } else {
                $stmt_is_post = $conn->prepare("INSERT INTO EventCalendar (DepartmentID, EventName, EventDate, Location, Speaker, Status, Notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if($stmt_is_post) { $stmt_is_post->bind_param("issssss", $inservice_department_id, $event_to_edit_values['EventName'], $event_datetime_db_is, $event_to_edit_values['Location'], $event_to_edit_values['Speaker'], $event_to_edit_values['Status'], $event_to_edit_values['Notes']);
                    if($stmt_is_post->execute()) { $_SESSION['flash_message'] = ['type'=>'success', 'text'=>'رویداد ایجاد شد.']; header("Location: events.php"); exit;}
                    else $errors_is_evt[] = "خطا ایجاد: ".$stmt_is_post->error; $stmt_is_post->close();
                } else $errors_is_evt[] = "خطا آماده سازی ایجاد: ".$conn->error;
            }
        }
    }
    $csrf_token_is_events = regenerate_csrf_token('inservice_events_action');
}

if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id']) && $_SERVER["REQUEST_METHOD"] != "POST") {
    $edit_id_is_evt_get = (int)$_GET['edit_id'];
    $stmt_edit_is_evt_get = $conn->prepare("SELECT EventID, DepartmentID, EventName, EventDate, Location, Speaker, Status, Notes FROM EventCalendar WHERE EventID = ? AND DepartmentID = ?");
    if ($stmt_edit_is_evt_get) {
        $stmt_edit_is_evt_get->bind_param("ii", $edit_id_is_evt_get, $inservice_department_id); $stmt_edit_is_evt_get->execute(); $result_edit_is_evt_get = $stmt_edit_is_evt_get->get_result();
        if ($data_is_evt_get = $result_edit_is_evt_get->fetch_assoc()) {
            $event_to_edit_values = $data_is_evt_get;
            if ($event_to_edit_values['EventDate']) { $dt_obj_is_edit = new DateTime($event_to_edit_values['EventDate']); $event_to_edit_values['EventDate'] = $dt_obj_is_edit->format('Y-m-d'); $event_to_edit_values['EventTime'] = $dt_obj_is_edit->format('H:i'); if ($event_to_edit_values['EventTime'] == '00:00') $event_to_edit_values['EventTime'] = '';}
            $edit_mode_is_evt = true;
        } else $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "رویداد یافت نشد."];
        $stmt_edit_is_evt_get->close();
    } else $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "خطا بارگذاری رویداد: " . $conn->error];
}

if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    if (!isset($_GET['csrf_token']) || !verify_csrf_token($_GET['csrf_token'], 'inservice_events_action')) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطای CSRF!'];
    } else {
        $delete_id_is_evt_get = (int)$_GET['delete_id'];
        // Check dependencies: EventChecklists, MeetingAttendance
        // For now, simple delete. Add dependency checks later or rely on DB constraints (ON DELETE CASCADE for checklists, SET NULL for attendance user if any)
        $conn->begin_transaction();
        try {
            $stmt_del_chk = $conn->prepare("DELETE FROM EventChecklists WHERE EventID = ?");
            if($stmt_del_chk){ $stmt_del_chk->bind_param("i", $delete_id_is_evt_get); if(!$stmt_del_chk->execute()) throw new Exception("خطا حذف چک‌لیست‌ها: ".$stmt_del_chk->error); $stmt_del_chk->close();} else throw new Exception("خطا آماده سازی حذف چک‌لیست‌ها: ".$conn->error);
            // $stmt_del_att = $conn->prepare("DELETE FROM MeetingAttendance WHERE EventID = ?"); // Or UPDATE to set EventID = NULL
            // ...
            $stmt_delete_is_evt_get = $conn->prepare("DELETE FROM EventCalendar WHERE EventID = ? AND DepartmentID = ?");
            if ($stmt_delete_is_evt_get) { $stmt_delete_is_evt_get->bind_param("ii", $delete_id_is_evt_get, $inservice_department_id);
                if ($stmt_delete_is_evt_get->execute() && $stmt_delete_is_evt_get->affected_rows > 0) { $conn->commit(); $_SESSION['flash_message'] = ['type' => 'success', 'text' => "رویداد حذف شد."]; }
                else { throw new Exception("خطا حذف رویداد یا رویداد یافت نشد: ".$stmt_delete_is_evt_get->error); } $stmt_delete_is_evt_get->close();
            } else { throw new Exception("خطا آماده سازی حذف: ".$conn->error); }
        } catch (Exception $e_del_is) { $conn->rollback(); $_SESSION['flash_message'] = ['type' => 'danger', 'text' => $e_del_is->getMessage()];}
    }
    $csrf_token_is_events = regenerate_csrf_token('inservice_events_action');
    header("Location: events.php"); exit;
}

$events_list_q_is = $conn->query("SELECT * FROM EventCalendar WHERE DepartmentID = $inservice_department_id ORDER BY EventDate DESC LIMIT 50");
?>
<div class="page-header"><h1>مدیریت رویدادهای ضمن خدمت</h1></div>

<?php if (isset($_SESSION['flash_message'])) { /* ... Flash ... */ } ?>
<?php if (!empty($errors_is_evt)): ?> <div class="alert alert-danger"><ul><?php foreach ($errors_is_evt as $err_is_item): ?><li><?php echo htmlspecialchars($err_is_item); ?></li><?php endforeach; ?></ul></div> <?php endif; ?>

<div class="row"><div class="col-lg-5 mb-4"><div class="card shadow-sm">
    <div class="card-header"><span class="card-title-text"><?php echo $edit_mode_is_evt ? 'ویرایش رویداد' : 'افزودن رویداد جدید'; ?></span></div>
    <div class="card-body">
    <form action="events.php<?php if($edit_mode_is_evt && $event_to_edit_values['EventID']) echo '?edit_id='.$event_to_edit_values['EventID']; ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_is_events; ?>">
        <?php if ($edit_mode_is_evt && $event_to_edit_values['EventID']): ?><input type="hidden" name="event_id" value="<?php echo $event_to_edit_values['EventID']; ?>"><?php endif; ?>
        <div class="form-group"><label for="EventName_is">نام رویداد/جلسه <span class="text-danger">*</span></label><input type="text" class="form-control" id="EventName_is" name="EventName" value="<?php echo htmlspecialchars($event_to_edit_values['EventName']); ?>" required></div>
        <div class="form-row"><div class="form-group col-md-7"><label for="EventDate_is">تاریخ <span class="text-danger">*</span></label><input type="text" class="form-control persian-date-picker" id="EventDate_is" name="EventDate" value="<?php echo htmlspecialchars($event_to_edit_values['EventDate']); ?>" placeholder="YYYY-MM-DD" required></div><div class="form-group col-md-5"><label for="EventTime_is">زمان</label><input type="time" class="form-control" id="EventTime_is" name="EventTime" value="<?php echo htmlspecialchars($event_to_edit_values['EventTime']); ?>"></div></div>
        <div class="form-group"><label for="Location_is">مکان</label><input type="text" class="form-control" id="Location_is" name="Location" value="<?php echo htmlspecialchars($event_to_edit_values['Location']); ?>"></div>
        <div class="form-group"><label for="Speaker_is">استاد/سخنران</label><input type="text" class="form-control" id="Speaker_is" name="Speaker" value="<?php echo htmlspecialchars($event_to_edit_values['Speaker']); ?>"></div>
        <div class="form-group"><label for="Status_is_form">وضعیت <span class="text-danger">*</span></label><select name="Status" id="Status_is_form" class="form-control custom-select" required><?php foreach($event_status_options as $key_es_form => $val_es_form):?><option value="<?php echo $key_es_form;?>" <?php if($event_to_edit_values['Status']==$key_es_form) echo 'selected';?>><?php echo $val_es_form;?></option><?php endforeach;?></select></div>
        <div class="form-group"><label for="Notes_is_form">یادداشت</label><textarea class="form-control" id="Notes_is_form" name="Notes" rows="2"><?php echo htmlspecialchars($event_to_edit_values['Notes']); ?></textarea></div>
        <div class="form-actions"><button type="submit" name="submit_inservice_event" class="btn btn-primary"><?php echo $edit_mode_is_evt ? 'ذخیره' : 'ایجاد'; ?></button><?php if ($edit_mode_is_evt): ?><a href="events.php" class="btn btn-outline-secondary">لغو</a><?php endif; ?></div>
    </form></div></div></div>
    <div class="col-lg-7"><div class="card shadow-sm"><div class="card-header"><span class="card-title-text">لیست رویدادها (۵۰ اخیر)</span></div><div class="card-body">
    <?php if($events_list_q_is && $events_list_q_is->num_rows > 0):?><div class="table-responsive"><table class="table table-sm table-striped table-hover">
        <thead><tr><th>#</th><th>رویداد</th><th>تاریخ</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>
        <?php $evt_r_idx = 1; while($evt_item = $events_list_q_is->fetch_assoc()): ?><tr>
            <td><?php echo $evt_r_idx++;?></td><td><strong><?php echo htmlspecialchars($evt_item['EventName']);?></strong><small class="d-block text-muted"><?php echo htmlspecialchars($evt_item['Location'] ?? '');?></small></td><td><?php echo to_jalali($evt_item['EventDate'], 'yyyy/MM/dd HH:mm');?></td><td><span class="badge badge-<?php echo $status_badge_map_is_evt[$evt_item['Status']] ?? 'light';?> p-2"><?php echo $event_status_options[$evt_item['Status']] ?? $evt_item['Status'];?></span></td>
            <td class="actions-cell">
                <a href="events.php?edit_id=<?php echo $evt_item['EventID'];?>" class="btn btn-sm btn-warning" title="ویرایش رویداد"><svg class="icon" width="14" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a>
                <a href="checklists.php?event_id=<?php echo $evt_item['EventID'];?>" class="btn btn-sm btn-info" title="مدیریت چک‌لیست‌ها"><svg class="icon" width="14" viewBox="0 0 24 24"><path d="M15.5 2H8.6c-.4 0-.8.2-1.1.5-.3.3-.5.7-.5 1.1V21c0 .4.2.8.5 1.1.3.3.7.5 1.1.5h10.8c.4 0 .8-.2 1.1-.5.3-.3.5-.7.5-1.1V8.9L15.5 2z"/><path d="M15 2v5h5"/><polyline points="9 16 11 18 15 14"/></svg></a>
                <a href="attendance.php?event_id=<?php echo $evt_item['EventID'];?>" class="btn btn-sm btn-secondary" title="حضور و غیاب"><svg class="icon" width="14" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></a>
                <a href="content.php?event_id=<?php echo $evt_item['EventID'];?>" class="btn btn-sm btn-success" title="محتوای جلسه"><svg class="icon" width="14" viewBox="0 0 24 24"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/><path d="M12 11v6"/><path d="M9 14h6"/></svg></a>
                <a href="events.php?delete_id=<?php echo $evt_item['EventID'];?>&csrf_token=<?php echo $csrf_token_is_events; ?>" class="btn btn-sm btn-danger" title="حذف رویداد" onclick="return confirm('آیا از حذف این رویداد و چک‌لیست‌های مرتبط با آن مطمئن هستید؟');"><svg class="icon" width="14" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></a>
            </td></tr><?php endwhile; ?></tbody></table></div>
    <?php else: ?><p class="text-muted">رویدادی ثبت نشده.</p><?php endif; if($events_list_q_is) $events_list_q_is->close();?>
    </div></div></div></div>
<link rel="stylesheet" href="https://unpkg.com/persian-datepicker@latest/dist/css/persian-datepicker.min.css"/>
<script src="https://unpkg.com/persian-datepicker@latest/dist/js/persian-datepicker.min.js"></script>
<script> /* Datepicker init, alert dismissal ... */
 document.addEventListener('DOMContentLoaded', function() { document.querySelectorAll(".persian-date-picker").forEach(function(el){ new persianDatepicker(el, { format: 'YYYY-MM-DD', autoClose: true, observer: true, calendar:{ persian: { locale: 'fa' } }, toolbox: { calendarSwitch:{ enabled: false } } });}); document.querySelectorAll('.alert .close').forEach(function(button){button.addEventListener('click', function(event){event.target.closest('.alert').style.display = 'none';});});});</script>
<style>.badge.p-2 {padding:0.4em 0.6em !important; font-size:0.85rem !important;}</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
