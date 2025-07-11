<?php
// admin/inservice/attendance.php
require_once __DIR__ . '/../includes/header.php';

$event_id_for_attendance = null;
$event_data_attendance = null;
$teachers_list_att = [];
$attendance_records_db = [];
$meeting_details_db_form = ['speakers' => '', 'meeting_specific_notes' => '', 'hospitality_details' => '', 'guest_attendees_text' => ''];
$errors_att = [];

if (!isset($_GET['event_id']) || !is_numeric($_GET['event_id'])) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'شناسه رویداد نامعتبر.'];
    header("Location: events.php"); exit;
}
$event_id_for_attendance = (int)$_GET['event_id'];
$csrf_token_attendance = generate_csrf_token('inservice_attendance_action_' . $event_id_for_attendance);

$stmt_event_att = $conn->prepare("SELECT EventID, EventName, EventDate, Speaker, Notes, MeetingSpecificNotes, HospitalityDetails FROM EventCalendar WHERE EventID = ?");
if ($stmt_event_att) {
    $stmt_event_att->bind_param("i", $event_id_for_attendance); $stmt_event_att->execute(); $res_evt_att = $stmt_event_att->get_result();
    if ($res_evt_att->num_rows === 1) {
        $event_data_attendance = $res_evt_att->fetch_assoc();
        // Pre-fill form with existing details
        $meeting_details_db_form['speakers'] = $event_data_attendance['Speaker'] ?? '';
        $meeting_details_db_form['meeting_specific_notes'] = $event_data_attendance['MeetingSpecificNotes'] ?? '';
        $meeting_details_db_form['hospitality_details'] = $event_data_attendance['HospitalityDetails'] ?? '';
    } else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'رویداد یافت نشد.']; header("Location: events.php"); exit; }
    $stmt_event_att->close();
} else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا بارگذاری رویداد: '.$conn->error]; header("Location: events.php"); exit; }

$stmt_teachers_att_list = $conn->query("SELECT UserID, FirstName, LastName, Username FROM Users WHERE UserType = 'teacher' AND IsActive = TRUE ORDER BY LastName, FirstName");
if ($stmt_teachers_att_list) { while($t_att_item = $stmt_teachers_att_list->fetch_assoc()) $teachers_list_att[$t_att_item['UserID']] = $t_att_item; $stmt_teachers_att_list->close(); }

$stmt_fetch_att_db = $conn->prepare("SELECT UserID, AttendeeName, IsPresent, Notes AS AttendanceNotes FROM MeetingAttendance WHERE EventID = ?");
if($stmt_fetch_att_db){
    $stmt_fetch_att_db->bind_param("i", $event_id_for_attendance); $stmt_fetch_att_db->execute(); $res_att_db_fetch = $stmt_fetch_att_db->get_result();
    $guest_names_for_textarea = [];
    while($att_rec_db = $res_att_db_fetch->fetch_assoc()){
        if($att_rec_db['UserID'] !== null){ // Registered user
            $attendance_records_db[$att_rec_db['UserID']] = ['IsPresent' => $att_rec_db['IsPresent'], 'Notes' => $att_rec_db['AttendanceNotes']];
        } else { // Guest
            $guest_names_for_textarea[] = $att_rec_db['AttendeeName'];
        }
    }
    $meeting_details_db_form['guest_attendees_text'] = implode("\n", $guest_names_for_textarea);
    $stmt_fetch_att_db->close();
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_attendance_details'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'inservice_attendance_action_' . $event_id_for_attendance)) {
        $errors_att[] = 'خطای CSRF!';
    } else {
        $posted_attendance_data = $_POST['attendance'] ?? [];
        $meeting_speakers_post_data = sanitize_input($_POST['meeting_speakers'] ?? '');
        $meeting_general_notes_post_data = sanitize_input($_POST['meeting_general_notes'] ?? '');
        $meeting_hospitality_post_data = sanitize_input($_POST['meeting_hospitality'] ?? '');
        $guest_attendees_str_data = sanitize_input($_POST['guest_attendees'] ?? '');

        // Repopulate for sticky form on error
        $meeting_details_db_form = ['speakers' => $meeting_speakers_post_data, 'meeting_specific_notes' => $meeting_general_notes_post_data, 'hospitality_details' => $meeting_hospitality_post_data, 'guest_attendees_text' => $guest_attendees_str_data];

        $conn->begin_transaction();
        try {
            // Clear previous attendance for this event to avoid duplicates or old entries
            $stmt_clear_old_att = $conn->prepare("DELETE FROM MeetingAttendance WHERE EventID = ?");
            if(!$stmt_clear_old_att) throw new Exception("خطا در پاکسازی حضورغیاب قبلی: ".$conn->error);
            $stmt_clear_old_att->bind_param("i", $event_id_for_attendance);
            if(!$stmt_clear_old_att->execute()) throw new Exception("اجرای پاکسازی حضورغیاب قبلی ناموفق: ".$stmt_clear_old_att->error);
            $stmt_clear_old_att->close();

            $stmt_upsert_att_new = $conn->prepare("INSERT INTO MeetingAttendance (EventID, UserID, IsPresent, Notes, AttendeeName) VALUES (?, ?, ?, ?, ?)");
            if(!$stmt_upsert_att_new) throw new Exception("خطا آماده سازی حضورغیاب: ".$conn->error);

            foreach ($teachers_list_att as $teacher_id_att_form_item => $teacher_data_att_form_item) {
                $status_val_form = $posted_attendance_data[$teacher_id_att_form_item]['status'] ?? '0';
                $att_notes_form = sanitize_input($posted_attendance_data[$teacher_id_att_form_item]['notes'] ?? '');
                $is_present_db_val_form = 0;
                if ($status_val_form === '1') $is_present_db_val_form = 1;
                elseif ($status_val_form === '2') $is_present_db_val_form = 2;

                $attendee_name_db_form = $teacher_data_att_form_item['FirstName'] . ' ' . $teacher_data_att_form_item['LastName'];
                $stmt_upsert_att_new->bind_param("iiiss", $event_id_for_attendance, $teacher_id_att_form_item, $is_present_db_val_form, $att_notes_form, $attendee_name_db_form);
                if(!$stmt_upsert_att_new->execute()) throw new Exception("خطا ثبت حضورغیاب برای ".$attendee_name_db_form.": ".$stmt_upsert_att_new->error);
            }

            if (!empty($guest_attendees_str_data)) {
                $guest_names_arr_data = array_filter(array_map('trim', explode("\n", $guest_attendees_str_data)));
                if (!empty($guest_names_arr_data)) {
                    foreach ($guest_names_arr_data as $guest_name_item) {
                        if(!empty($guest_name_item)){
                             $stmt_upsert_att_new->bind_param("iiiss", $event_id_for_attendance, $null_user_id, $one_is_present, $null_notes, $guest_name_item); // UserID is NULL for guests, IsPresent=1, Notes=NULL
                             $null_user_id = null; $one_is_present = 1; $null_notes = null; // PHP 8 requires variables for bind_param
                             if(!$stmt_upsert_att_new->execute()) throw new Exception("خطا ثبت مهمان '".$guest_name_item."': ".$stmt_upsert_att_new->error);
                        }
                    }
                }
            }
            $stmt_upsert_att_new->close();

            $stmt_update_event_details_final = $conn->prepare("UPDATE EventCalendar SET Speakers = ?, MeetingSpecificNotes = ?, HospitalityDetails = ?, UpdatedAt = NOW() WHERE EventID = ?");
             if($stmt_update_event_details_final){
                 $stmt_update_event_details_final->bind_param("sssi", $meeting_speakers_post_data, $meeting_general_notes_post_data, $meeting_hospitality_post_data, $event_id_for_attendance);
                 if(!$stmt_update_event_details_final->execute()) throw new Exception("خطا بروزرسانی جزئیات رویداد: ".$stmt_update_event_details_final->error);
                 $stmt_update_event_details_final->close();
             } else { throw new Exception("خطا آماده سازی بروزرسانی جزئیات رویداد: ".$conn->error); }

            $conn->commit();
            $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'اطلاعات با موفقیت ذخیره شد.'];
            regenerate_csrf_token('inservice_attendance_action_' . $event_id_for_attendance);
            header("Location: attendance.php?event_id=" . $event_id_for_attendance); exit;
        } catch (Exception $e_att_submit) { $conn->rollback(); $errors_att[] = $e_att_submit->getMessage(); }
    }
    $csrf_token_attendance = regenerate_csrf_token('inservice_attendance_action_' . $event_id_for_attendance);
}
?>
<div class="page-header">
    <h1>حضور و غیاب و جزئیات جلسه: <?php echo htmlspecialchars($event_data_attendance['EventName'] ?? '...'); ?></h1>
    <p class="page-subtitle">تاریخ جلسه: <?php echo to_jalali($event_data_attendance['EventDate'] ?? '', 'yyyy/MM/dd HH:mm'); ?></p>
    <div class="page-header-actions"><a href="events.php" class="btn btn-secondary">بازگشت به رویدادها</a></div>
</div>

<?php if (isset($_SESSION['flash_message'])) { $flash_att_page = $_SESSION['flash_message']; echo "<div class='alert alert-{$flash_att_page['type']} alert-dismissible fade show'>{$flash_att_page['text']}<button type='button' class='close' data-dismiss='alert'>&times;</button></div>"; unset($_SESSION['flash_message']); echo "<script> /* Dismiss JS */</script>";} ?>
<?php if (!empty($errors_att)): ?> <div class="alert alert-danger"><ul><?php foreach ($errors_att as $err_att_item_page): ?><li><?php echo htmlspecialchars($err_att_item_page); ?></li><?php endforeach; ?></ul></div> <?php endif; ?>

<form action="attendance.php?event_id=<?php echo $event_id_for_attendance; ?>" method="POST" id="attendanceFormAdmin">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_attendance; ?>">
    <input type="hidden" name="event_id" value="<?php echo $event_id_for_attendance; ?>">

    <div class="card shadow-sm mb-4"><div class="card-header"><h5 class="mb-0 card-title-text">لیست حضور و غیاب مدرسین</h5></div>
    <div class="card-body"> <?php if(!empty($teachers_list_att)): ?> <div class="table-responsive">
    <table class="table table-sm table-hover"><thead><tr><th>مدرس</th><th>وضعیت حضور</th><th>یادداشت غیبت/تاخیر</th></tr></thead><tbody>
    <?php foreach($teachers_list_att as $tid_att_form_disp => $teacher_form_disp):
        $current_status_form_disp = $attendance_records_db[$tid_att_form_disp]['IsPresent'] ?? '0';
        $current_notes_form_disp = $attendance_records_db[$tid_att_form_disp]['Notes'] ?? '';
    ?>
    <tr><td><?php echo htmlspecialchars($teacher_form_disp['FirstName'].' '.$teacher_form_disp['LastName']);?></td>
    <td><select name="attendance[<?php echo $tid_att_form_disp;?>][status]" class="form-control form-control-sm custom-select">
        <option value="1" <?php if($current_status_form_disp == 1) echo 'selected';?>>حاضر</option>
        <option value="0" <?php if($current_status_form_disp == 0) echo 'selected';?>>غایب</option>
        <option value="2" <?php if($current_status_form_disp == 2) echo 'selected';?>>تاخیر</option>
    </select></td>
    <td><input type="text" name="attendance[<?php echo $tid_att_form_disp;?>][notes]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($current_notes_form_disp);?>" placeholder="علت غیبت/میزان تاخیر"></td>
    </tr><?php endforeach; ?></tbody></table></div>
    <?php else: ?><p class="text-muted">مدرسی برای ثبت حضور و غیاب یافت نشد.</p><?php endif; ?>
    </div></div>

    <div class="card shadow-sm mb-4"><div class="card-header"><h5 class="mb-0 card-title-text">شرکت‌کنندگان مهمان</h5></div>
    <div class="card-body"><div class="form-group">
        <label for="guest_attendees_area_form">اسامی مهمانان (هر نام در یک خط)</label>
        <textarea class="form-control" id="guest_attendees_area_form" name="guest_attendees" rows="3" placeholder="مثال: جناب آقای دکتر رضایی&#10;خانم مهندس احمدی"><?php echo htmlspecialchars($meeting_details_db_form['guest_attendees_text']); ?></textarea>
    </div></div></div>

    <div class="card shadow-sm"><div class="card-header"><h5 class="mb-0 card-title-text">جزئیات تکمیلی جلسه</h5></div>
    <div class="card-body">
        <div class="form-group"><label for="meeting_speakers_form_admin_id">سخنران(ان)</label><input type="text" class="form-control" id="meeting_speakers_form_admin_id" name="meeting_speakers" value="<?php echo htmlspecialchars($meeting_details_db_form['speakers']); ?>" placeholder="نام سخنرانان، با کاما یا خط جدید جدا شوند"></div>
        <div class="form-group"><label for="meeting_general_notes_form_admin_id">نکات جلسه</label><textarea class="form-control" id="meeting_general_notes_form_admin_id" name="meeting_general_notes" rows="3"><?php echo htmlspecialchars($meeting_details_db_form['meeting_specific_notes']); ?></textarea></div>
        <div class="form-group"><label for="meeting_hospitality_form_admin_id">پذیرایی</label><input type="text" class="form-control" id="meeting_hospitality_form_admin_id" name="meeting_hospitality" value="<?php echo htmlspecialchars($meeting_details_db_form['hospitality_details']); ?>"></div>
    </div></div>
    <div class="form-actions mt-4 text-center"><button type="submit" name="submit_attendance_details" class="btn btn-primary btn-lg">ذخیره اطلاعات جلسه</button></div>
</form>
<script> /* Alert dismissal JS... */
 document.querySelectorAll('.alert .close').forEach(function(button){button.addEventListener('click', function(event){event.target.closest('.alert').style.display = 'none';});});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
