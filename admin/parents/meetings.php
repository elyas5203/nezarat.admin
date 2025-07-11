<?php
// admin/parents/meetings.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth

$csrf_token_meetings = generate_csrf_token('parents_meetings_action');

$errors_mtg = [];
$success_message_mtg = '';
$edit_mode_mtg = false;
$meeting_to_edit_default = [
    'MeetingID' => null, 'MeetingName' => 'جلسه اولیای کلاس ', 'ClassID' => '',
    'MeetingDate' => '', 'MeetingTime' => '', 'Location' => '',
    'Speaker' => '', 'Description' => '', 'Status' => 'planned'
];
$meeting_to_edit = $meeting_to_edit_default;

$classes_q_mtg = $conn->query("SELECT ClassID, ClassName, AcademicYear FROM Classes WHERE IsActive = TRUE ORDER BY AcademicYear DESC, ClassName ASC");
$available_classes_mtg = [];
if ($classes_q_mtg) { while($c_mtg = $classes_q_mtg->fetch_assoc()) $available_classes_mtg[$c_mtg['ClassID']] = $c_mtg; $classes_q_mtg->close(); }

$meeting_status_options = ['planned' => 'برنامه‌ریزی شده', 'confirmed' => 'قطعی شده', 'completed' => 'انجام شده', 'cancelled' => 'لغو شده'];
$status_badge_map_mtg = ['planned' => 'primary', 'confirmed' => 'info', 'completed' => 'success', 'cancelled' => 'danger'];

$parents_department_id = null;
// Fetch DepartmentID for 'اولیا' more reliably
$stmt_parents_dept_fetch = $conn->prepare("SELECT DepartmentID FROM Departments WHERE DepartmentName = 'اولیا' OR DepartmentName = 'بخش اولیا' LIMIT 1");
if ($stmt_parents_dept_fetch) {
    $stmt_parents_dept_fetch->execute(); $res_pd_fetch = $stmt_parents_dept_fetch->get_result();
    if ($pd_row_fetch = $res_pd_fetch->fetch_assoc()) $parents_department_id = $pd_row_fetch['DepartmentID'];
    $stmt_parents_dept_fetch->close();
}
if (!$parents_department_id) { $parents_department_id = 1; error_log("Fallback: Parents department ID set to 1. Please ensure 'اولیا' department exists.");}

$filter_class_id_from_monitoring = isset($_GET['class_filter_id']) && is_numeric($_GET['class_filter_id']) ? (int)$_GET['class_filter_id'] : null;
if ($filter_class_id_from_monitoring && $_SERVER["REQUEST_METHOD"] != "POST" && !$edit_mode_mtg) {
    $meeting_to_edit['ClassID'] = $filter_class_id_from_monitoring;
    if(isset($available_classes_mtg[$filter_class_id_from_monitoring])) {
         $meeting_to_edit['MeetingName'] = 'جلسه اولیای کلاس ' . $available_classes_mtg[$filter_class_id_from_monitoring]['ClassName'];
    }
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_meeting'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'parents_meetings_action')) {
        $errors_mtg[] = 'خطای CSRF!';
    } else {
        $meeting_id = isset($_POST['meeting_id']) && is_numeric($_POST['meeting_id']) ? (int)$_POST['meeting_id'] : null;
        $meeting_name = sanitize_input($_POST['meeting_name'] ?? '');
        $class_id_mtg = isset($_POST['class_id_mtg']) ? (int)$_POST['class_id_mtg'] : null;
        $meeting_date_str = sanitize_input($_POST['meeting_date'] ?? '');
        $meeting_time_str = sanitize_input($_POST['meeting_time'] ?? '');
        $location_mtg = sanitize_input($_POST['location_mtg'] ?? '');
        $speaker_mtg = sanitize_input($_POST['speaker_mtg'] ?? '');
        $description_mtg = sanitize_input($_POST['description_mtg'] ?? '');
        $status_mtg = sanitize_input($_POST['status_mtg'] ?? 'planned');

        $meeting_to_edit = ['MeetingID' => $meeting_id, 'MeetingName' => $meeting_name, 'ClassID' => $class_id_mtg, 'MeetingDate' => $meeting_date_str, 'MeetingTime' => $meeting_time_str, 'Location' => $location_mtg, 'Speaker' => $speaker_mtg, 'Description' => $description_mtg, 'Status' => $status_mtg];

        if (empty($meeting_name)) $errors_mtg[] = "عنوان جلسه الزامی است.";
        if (empty($class_id_mtg) || !isset($available_classes_mtg[$class_id_mtg])) $errors_mtg[] = "کلاس نامعتبر.";
        if (empty($meeting_date_str)) $errors_mtg[] = "تاریخ جلسه الزامی است.";
        if (!empty($meeting_date_str) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $meeting_date_str)) $errors_mtg[] = "فرمت تاریخ (YYYY-MM-DD).";
        if (!empty($meeting_time_str) && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $meeting_time_str)) $errors_mtg[] = "فرمت زمان (HH:MM).";
        if (!array_key_exists($status_mtg, $meeting_status_options)) $errors_mtg[] = "وضعیت جلسه نامعتبر.";

        $meeting_datetime_db = $meeting_date_str;
        if (!empty($meeting_time_str) && empty($errors_mtg['meeting_date']) && empty($errors_mtg['meeting_time'])) {
            try { $date_obj = new DateTime($meeting_date_str . ' ' . $meeting_time_str); $meeting_datetime_db = $date_obj->format('Y-m-d H:i:s'); }
            catch (Exception $e) { if (!empty($meeting_time_str)) $errors_mtg[] = "فرمت زمان جلسه صحیح نیست."; }
        } elseif (empty($errors_mtg['meeting_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $meeting_date_str)) {
             $meeting_datetime_db = $meeting_date_str . ' 00:00:00';
        } else if(empty($errors_mtg['meeting_date'])) { // If date itself is invalid, this might be an issue
            $errors_mtg[] = "تاریخ جلسه برای پردازش معتبر نیست.";
        }


        if (empty($errors_mtg)) {
            if ($meeting_id) {
                $stmt = $conn->prepare("UPDATE Meetings SET MeetingName=?, ClassID=?, MeetingDate=?, Location=?, Speaker=?, Description=?, Status=?, DepartmentID=? WHERE MeetingID=? AND MeetingType='parents_meeting'");
                if ($stmt) { $stmt->bind_param("sisssssii", $meeting_name, $class_id_mtg, $meeting_datetime_db, $location_mtg, $speaker_mtg, $description_mtg, $status_mtg, $parents_department_id, $meeting_id);
                    if ($stmt->execute()) { $_SESSION['flash_message'] = ['type' => 'success', 'text' => "جلسه ویرایش شد."]; $edit_mode_mtg = false; $meeting_to_edit = $meeting_to_edit_default; header("Location: meetings.php".($filter_class_id_from_monitoring ? "?class_filter_id=".$filter_class_id_from_monitoring : "")); exit;} else $errors_mtg[] = "خطا ویرایش: " . $stmt->error; $stmt->close();
                } else $errors_mtg[] = "خطا آماده سازی ویرایش: " . $conn->error;
            } else {
                $stmt = $conn->prepare("INSERT INTO Meetings (MeetingName, ClassID, MeetingDate, Location, Speaker, Description, Status, MeetingType, DepartmentID, CreatedAt, UpdatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, 'parents_meeting', ?, NOW(), NOW())");
                if ($stmt) { $stmt->bind_param("sisssssi", $meeting_name, $class_id_mtg, $meeting_datetime_db, $location_mtg, $speaker_mtg, $description_mtg, $status_mtg, $parents_department_id);
                    if ($stmt->execute()) { $_SESSION['flash_message'] = ['type' => 'success', 'text' => "جلسه اولیا ایجاد شد."]; $meeting_to_edit = $meeting_to_edit_default; header("Location: meetings.php".($filter_class_id_from_monitoring ? "?class_filter_id=".$filter_class_id_from_monitoring : "")); exit;} else $errors_mtg[] = "خطا ایجاد: " . $stmt->error; $stmt->close();
                } else $errors_mtg[] = "خطا آماده سازی ایجاد: " . $conn->error;
            }
        }
    }
    $csrf_token_meetings = regenerate_csrf_token('parents_meetings_action');
}

if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id']) && $_SERVER["REQUEST_METHOD"] != "POST") {
    $edit_id_mtg = (int)$_GET['edit_id'];
    $stmt_edit_mtg = $conn->prepare("SELECT MeetingID, MeetingName, ClassID, MeetingDate, Location, Speaker, Description, Status FROM Meetings WHERE MeetingID = ? AND MeetingType='parents_meeting'");
    if ($stmt_edit_mtg) {
        $stmt_edit_mtg->bind_param("i", $edit_id_mtg); $stmt_edit_mtg->execute(); $result_edit_mtg = $stmt_edit_mtg->get_result();
        if ($data_mtg = $result_edit_mtg->fetch_assoc()) {
            $meeting_to_edit = $data_mtg;
            if ($meeting_to_edit['MeetingDate']) { $dt_obj_edit = new DateTime($meeting_to_edit['MeetingDate']); $meeting_to_edit['MeetingDate'] = $dt_obj_edit->format('Y-m-d'); $meeting_to_edit['MeetingTime'] = $dt_obj_edit->format('H:i'); if ($meeting_to_edit['MeetingTime'] == '00:00') $meeting_to_edit['MeetingTime'] = '';}
            $edit_mode_mtg = true;
        } else $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "جلسه یافت نشد."];
        $stmt_edit_mtg->close();
    } else $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "خطا بارگذاری جلسه: " . $conn->error];
}
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    if (!isset($_GET['csrf_token']) || !verify_csrf_token($_GET['csrf_token'], 'parents_meetings_action')) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطای CSRF!'];
    } else {
        $delete_id_mtg = (int)$_GET['delete_id'];
        $conn->begin_transaction();
        try {
            $stmt_unassign_fs_del = $conn->prepare("UPDATE FormSubmissions SET MeetingID = NULL WHERE MeetingID = ?");
            if (!$stmt_unassign_fs_del) throw new Exception("خطا آماده سازی بروزرسانی پاسخ‌ها: ".$conn->error);
            $stmt_unassign_fs_del->bind_param("i", $delete_id_mtg);
            if(!$stmt_unassign_fs_del->execute()) throw new Exception("خطا بروزرسانی پاسخ‌ها: ".$stmt_unassign_fs_del->error);
            $stmt_unassign_fs_del->close();

            $stmt_delete_mtg_del = $conn->prepare("DELETE FROM Meetings WHERE MeetingID = ? AND MeetingType='parents_meeting'");
            if (!$stmt_delete_mtg_del) throw new Exception("خطا آماده سازی حذف جلسه: ".$conn->error);
            $stmt_delete_mtg_del->bind_param("i", $delete_id_mtg);
            if ($stmt_delete_mtg_del->execute() && $stmt_delete_mtg_del->affected_rows > 0) {
                $conn->commit(); $_SESSION['flash_message'] = ['type' => 'success', 'text' => "جلسه حذف شد."];
            } else { throw new Exception("خطا حذف جلسه یا جلسه یافت نشد: ".$stmt_delete_mtg_del->error); }
            $stmt_delete_mtg_del->close();
        } catch (Exception $e) { $conn->rollback(); $_SESSION['flash_message'] = ['type' => 'danger', 'text' => $e->getMessage()];}
    }
    $csrf_token_meetings = regenerate_csrf_token('parents_meetings_action');
    header("Location: meetings.php" . ($filter_class_id_from_monitoring ? "?class_filter_id=".$filter_class_id_from_monitoring : "")); exit;
}

$sql_meetings_list = "SELECT m.*, c.ClassName, c.AcademicYear FROM Meetings m JOIN Classes c ON m.ClassID = c.ClassID WHERE m.MeetingType='parents_meeting'";
if ($filter_class_id_from_monitoring) {
    $sql_meetings_list .= " AND m.ClassID = " . $filter_class_id_from_monitoring;
}
$sql_meetings_list .= " ORDER BY m.MeetingDate DESC LIMIT 100";
$meetings_list_q = $conn->query($sql_meetings_list);
?>
<div class="page-header"><h1>مدیریت جلسات اولیا <?php if($filter_class_id_from_monitoring && isset($available_classes_mtg[$filter_class_id_from_monitoring])) echo " <small class='text-muted'>(کلاس: " . htmlspecialchars($available_classes_mtg[$filter_class_id_from_monitoring]['ClassName']) . ")</small>"; ?></h1>
    <div class="page-header-actions"><a href="<?php echo $admin_base_url; ?>/monitoring/index.php" class="btn btn-outline-secondary">نظارت بر کلاس‌ها</a></div></div>

<?php if (isset($_SESSION['flash_message'])) { $flash_mtg_idx = $_SESSION['flash_message']; echo "<div class='alert alert-{$flash_mtg_idx['type']} alert-dismissible fade show'>{$flash_mtg_idx['text']}<button type='button' class='close' data-dismiss='alert' aria-label='Close' style='background:none; border:none; font-size:1.5rem; position:absolute; top:0; left:0; padding: 0.75rem 1.25rem;'><span aria-hidden='true'>&times;</span></button></div>"; unset($_SESSION['flash_message']); echo "<script>setTimeout(function(){let alert = document.querySelector('.alert-dismissible.show'); if(alert){if(typeof(bootstrap)!=='undefined' && bootstrap.Alert && bootstrap.Alert.getInstance(alert)){bootstrap.Alert.getInstance(alert).close();}else{alert.style.display='none';}}},7000);</script>";} ?>
<?php if (!empty($errors_mtg)): ?> <div class="alert alert-danger"><ul><?php foreach ($errors_mtg as $err_m_idx): ?><li><?php echo htmlspecialchars($err_m_idx); ?></li><?php endforeach; ?></ul></div> <?php endif; ?>
<?php if ($success_message_mtg && empty($errors_mtg)): ?> <div class="alert alert-success alert-dismissible fade show"><?php echo htmlspecialchars($success_message_mtg); ?><button type="button" class="close" data-dismiss="alert">&times;</button></div> <?php endif; ?>

<div class="row"><div class="col-lg-5 mb-4"><div class="card shadow-sm">
    <div class="card-header"><span class="card-title-text"><?php echo $edit_mode_mtg ? 'ویرایش جلسه: ' . htmlspecialchars($meeting_to_edit['MeetingName']) : 'افزودن جلسه اولیا جدید'; ?></span></div>
    <div class="card-body">
    <form action="meetings.php<?php if($edit_mode_mtg && $meeting_to_edit['MeetingID']) echo '?edit_id='.$meeting_to_edit['MeetingID']; if($filter_class_id_from_monitoring && !$edit_mode_mtg) echo '?class_filter_id='.$filter_class_id_from_monitoring; elseif($filter_class_id_from_monitoring && $edit_mode_mtg) echo '&class_filter_id='.$filter_class_id_from_monitoring;?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_meetings; ?>">
        <?php if ($edit_mode_mtg && $meeting_to_edit['MeetingID']): ?><input type="hidden" name="meeting_id" value="<?php echo $meeting_to_edit['MeetingID']; ?>"><?php endif; ?>
        <div class="form-group"><label for="meeting_name_mtg">عنوان <span class="text-danger">*</span></label><input type="text" class="form-control" id="meeting_name_mtg" name="meeting_name" value="<?php echo htmlspecialchars($meeting_to_edit['MeetingName']); ?>" required></div>
        <div class="form-group"><label for="class_id_mtg_form">کلاس <span class="text-danger">*</span></label><select name="class_id_mtg" id="class_id_mtg_form" class="form-control custom-select" required <?php if($filter_class_id_from_monitoring && !$edit_mode_mtg) echo "disabled"; ?>>
            <option value="">-- انتخاب --</option><?php foreach($available_classes_mtg as $cls_id_m => $cls_m_data): ?><option value="<?php echo $cls_id_m; ?>" <?php if($meeting_to_edit['ClassID'] == $cls_id_m) echo 'selected';?>><?php echo htmlspecialchars($cls_m_data['ClassName'] . ' (' . $cls_m_data['AcademicYear'] . ')'); ?></option><?php endforeach; ?></select>
            <?php if($filter_class_id_from_monitoring && !$edit_mode_mtg): ?> <input type="hidden" name="class_id_mtg" value="<?php echo $filter_class_id_from_monitoring; ?>"> <?php endif; ?>
        </div>
        <div class="form-row"><div class="form-group col-md-7"><label for="meeting_date_mtg">تاریخ <span class="text-danger">*</span></label><input type="text" class="form-control persian-date-picker" id="meeting_date_mtg" name="meeting_date" value="<?php echo htmlspecialchars($meeting_to_edit['MeetingDate']); ?>" placeholder="YYYY-MM-DD" required></div><div class="form-group col-md-5"><label for="meeting_time_mtg">زمان</label><input type="time" class="form-control" id="meeting_time_mtg" name="meeting_time" value="<?php echo htmlspecialchars($meeting_to_edit['MeetingTime']); ?>"></div></div>
        <div class="form-group"><label for="location_mtg">مکان</label><input type="text" class="form-control" id="location_mtg" name="location_mtg" value="<?php echo htmlspecialchars($meeting_to_edit['Location']); ?>"></div>
        <div class="form-group"><label for="speaker_mtg">سخنران</label><input type="text" class="form-control" id="speaker_mtg" name="speaker_mtg" value="<?php echo htmlspecialchars($meeting_to_edit['Speaker']); ?>"></div>
        <div class="form-group"><label for="description_mtg_form">توضیحات</label><textarea class="form-control" id="description_mtg_form" name="description_mtg" rows="2"><?php echo htmlspecialchars($meeting_to_edit['Description']); ?></textarea></div>
        <div class="form-group"><label for="status_mtg_form">وضعیت <span class="text-danger">*</span></label><select name="status_mtg" id="status_mtg_form" class="form-control custom-select" required><?php foreach($meeting_status_options as $s_key_m => $s_val_m): ?><option value="<?php echo $s_key_m; ?>" <?php if($meeting_to_edit['Status'] == $s_key_m) echo 'selected';?>><?php echo $s_val_m; ?></option><?php endforeach; ?></select></div>
        <div class="form-actions"><button type="submit" name="submit_meeting" class="btn btn-primary"><?php echo $edit_mode_mtg ? 'ذخیره' : 'ایجاد'; ?></button><?php if ($edit_mode_mtg): ?><a href="meetings.php<?php if($filter_class_id_from_monitoring) echo '?class_filter_id='.$filter_class_id_from_monitoring; ?>" class="btn btn-outline-secondary">لغو</a><?php endif; ?></div>
    </form></div></div></div>
    <div class="col-lg-7"><div class="card shadow-sm"><div class="card-header"><span class="card-title-text">لیست جلسات اولیا <?php if($filter_class_id_from_monitoring && isset($available_classes_mtg[$filter_class_id_from_monitoring])) echo " (کلاس: " . htmlspecialchars($available_classes_mtg[$filter_class_id_from_monitoring]['ClassName']) . ")"; ?></span></div><div class="card-body">
    <?php if ($meetings_list_q && $meetings_list_q->num_rows > 0): ?><div class="table-responsive"><table class="table table-sm table-striped table-hover">
        <thead><tr><th>#</th><th>عنوان/کلاس</th><th>تاریخ</th><th>وضعیت</th><th>گزارش‌ها</th><th>عملیات</th></tr></thead><tbody>
        <?php $mtg_row_idx = 1; while ($mtg_idx = $meetings_list_q->fetch_assoc()): ?><tr>
            <td><?php echo $mtg_row_idx++; ?></td>
            <td><strong><?php echo htmlspecialchars($mtg_idx['MeetingName']); ?></strong><small class="d-block text-muted"><?php echo htmlspecialchars($mtg_idx['ClassName'] . ' (' . $mtg_idx['AcademicYear'] . ')'); ?><?php if($mtg_idx['Location']) echo ' - مکان: '.htmlspecialchars($mtg_idx['Location']);?></small></td>
            <td><?php echo to_jalali($mtg_idx['MeetingDate'], 'yyyy/MM/dd HH:mm'); ?></td>
            <td><span class="badge badge-<?php echo $status_badge_map_mtg[$mtg_idx['Status']] ?? 'light'; ?> p-2"><?php echo $meeting_status_options[$mtg_idx['Status']] ?? $mtg_idx['Status']; ?></span></td>
            <td><?php if($mtg_idx['Status'] == 'completed'): ?>
                <a href="<?php echo $admin_base_url; ?>/forms/submissions.php?meeting_id=<?php echo $mtg_idx['MeetingID']; ?>&form_purpose_filter=parents_meeting_teacher_report" class="btn btn-xs btn-outline-info my-1 py-0 px-1" title="گزارش مدرس">مدرس</a>
                <a href="<?php echo $admin_base_url; ?>/forms/submissions.php?meeting_id=<?php echo $mtg_idx['MeetingID']; ?>&form_purpose_filter=parents_meeting_observer_report" class="btn btn-xs btn-outline-secondary my-1 py-0 px-1" title="گزارش ناظر">ناظر</a>
                <?php else: echo "-"; endif; ?></td>
            <td class="actions-cell">
                <a href="meetings.php?edit_id=<?php echo $mtg_idx['MeetingID']; ?><?php if($filter_class_id_from_monitoring) echo '&class_filter_id='.$filter_class_id_from_monitoring; ?>" class="btn btn-sm btn-warning" title="ویرایش"><svg class="icon" width="14" height="14" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></a>
                <a href="meetings.php?delete_id=<?php echo $mtg_idx['MeetingID']; ?>&csrf_token=<?php echo $csrf_token_meetings; ?><?php if($filter_class_id_from_monitoring) echo '&class_filter_id='.$filter_class_id_from_monitoring; ?>" class="btn btn-sm btn-danger" title="حذف" onclick="return confirm('آیا از حذف این جلسه مطمئن هستید؟');"><svg class="icon" width="14" height="14" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></a>
            </td></tr><?php endwhile; ?></tbody></table></div>
    <?php else: ?><p class="text-muted text-center mt-3">هنوز جلسه‌ای <?php if($filter_class_id_from_monitoring) echo "برای این کلاس "; ?>ثبت نشده.</p><?php endif; if($meetings_list_q) $meetings_list_q->close(); ?>
    </div></div></div></div>
<link rel="stylesheet" href="https://unpkg.com/persian-datepicker@latest/dist/css/persian-datepicker.min.css"/>
<script src="https://unpkg.com/persian-datepicker@latest/dist/js/persian-datepicker.min.js"></script>
<script type="text/javascript">
  document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll(".persian-date-picker").forEach(function(el){
        new persianDatepicker(el, { format: 'YYYY-MM-DD', autoClose: true, observer: true, calendar:{ persian: { locale: 'fa' } }, toolbox: { calendarSwitch:{ enabled: false } } });
    });
    document.querySelectorAll('.alert .close').forEach(function(button){button.addEventListener('click', function(event){event.target.closest('.alert').style.display = 'none';});});
    <?php if($filter_class_id_from_monitoring && !$edit_mode_mtg): ?>
        const classSelectForm = document.getElementById('class_id_mtg_form');
        if(classSelectForm) {
            classSelectForm.value = '<?php echo $filter_class_id_from_monitoring; ?>';
            // classSelectForm.setAttribute('disabled', 'disabled'); // Optionally disable if always filtered
        }
    <?php endif; ?>
  });
</script>
<style>.btn-xs{padding: .1rem .3rem; font-size: .75rem; line-height: 1.2; border-radius: .2rem;} .badge.p-2 { padding: 0.4em 0.6em !important; font-size: 0.85em !important;}</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
