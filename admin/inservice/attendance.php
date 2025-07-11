<?php
// admin/inservice/attendance.php
require_once __DIR__ . '/../includes/header.php';

$event_id_for_attendance = null;
$event_data_attendance = null;
$teachers_list_att = [];
$attendance_records_db = []; // [UserID => ['IsPresent' => status_code, 'Notes' => text]]
$meeting_details_db = ['speakers' => '', 'meeting_notes' => '', 'hospitality' => '']; // Default for form
$errors_att = [];

if (!isset($_GET['event_id']) || !is_numeric($_GET['event_id'])) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'شناسه رویداد نامعتبر است.'];
    header("Location: events.php"); exit;
}
$event_id_for_attendance = (int)$_GET['event_id'];
$csrf_token_attendance = generate_csrf_token('inservice_attendance_action_' . $event_id_for_attendance);

$stmt_event_att = $conn->prepare("SELECT EventID, EventName, EventDate, Speaker, Notes FROM EventCalendar WHERE EventID = ?");
if ($stmt_event_att) {
    $stmt_event_att->bind_param("i", $event_id_for_attendance); $stmt_event_att->execute(); $res_evt_att = $stmt_event_att->get_result();
    if ($res_evt_att->num_rows === 1) {
        $event_data_attendance = $res_evt_att->fetch_assoc();
        // Attempt to pre-fill details from structured notes or dedicated columns
        $meeting_details_db['speakers'] = $event_data_attendance['Speaker'] ?? ''; // If Speaker is a dedicated column
        // Simple parsing from Notes if structured like "نکات جلسه: X\nپذیرایی: Y"
        $notes_content_att = $event_data_attendance['Notes'] ?? '';
        if(preg_match('/نکات جلسه: (.*?)(?:\nپذیرایی:|$)/s', $notes_content_att, $matches_notes_att)) $meeting_details_db['meeting_notes'] = trim($matches_notes_att[1]);
        else if (!preg_match('/سخنرانان:|پذیرایی:/s', $notes_content_att)) $meeting_details_db['meeting_notes'] = $notes_content_att; // If no other keywords, assume all is notes
        if(preg_match('/پذیرایی: (.*?)(?:\n|$)/s', $notes_content_att, $matches_hosp_att)) $meeting_details_db['hospitality'] = trim($matches_hosp_att[1]);

    } else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'رویداد یافت نشد.']; header("Location: events.php"); exit; }
    $stmt_event_att->close();
} else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا بارگذاری رویداد: '.$conn->error]; header("Location: events.php"); exit; }

$stmt_teachers = $conn->query("SELECT UserID, FirstName, LastName, Username FROM Users WHERE UserType = 'teacher' AND IsActive = TRUE ORDER BY LastName, FirstName");
if ($stmt_teachers) { while($t_att = $stmt_teachers->fetch_assoc()) $teachers_list_att[$t_att['UserID']] = $t_att; $stmt_teachers->close(); }

$stmt_fetch_att = $conn->prepare("SELECT UserID, IsPresent, Notes AS AttendanceNotes FROM MeetingAttendance WHERE EventID = ?");
if($stmt_fetch_att){
    $stmt_fetch_att->bind_param("i", $event_id_for_attendance); $stmt_fetch_att->execute(); $res_att_db = $stmt_fetch_att->get_result();
    while($att_rec = $res_att_db->fetch_assoc()){ $attendance_records_db[$att_rec['UserID']] = ['IsPresent' => $att_rec['IsPresent'], 'Notes' => $att_rec['AttendanceNotes']];}
    $stmt_fetch_att->close();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_attendance_details'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'inservice_attendance_action_' . $event_id_for_attendance)) {
        $errors_att[] = 'خطای CSRF!';
    } else {
        $posted_attendance = $_POST['attendance'] ?? [];
        $meeting_speakers_post = sanitize_input($_POST['meeting_speakers'] ?? '');
        $meeting_general_notes_post = sanitize_input($_POST['meeting_general_notes'] ?? '');
        $meeting_hospitality_post = sanitize_input($_POST['meeting_hospitality'] ?? '');

        // Repopulate for sticky form on error
        $meeting_details_db = ['speakers' => $meeting_speakers_post, 'meeting_notes' => $meeting_general_notes_post, 'hospitality' => $meeting_hospitality_post];

        $conn->begin_transaction();
        try {
            $stmt_upsert_att = $conn->prepare("INSERT INTO MeetingAttendance (EventID, UserID, IsPresent, Notes, AttendeeName) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE IsPresent=VALUES(IsPresent), Notes=VALUES(Notes)");
            if(!$stmt_upsert_att) throw new Exception("خطا آماده سازی حضورغیاب: ".$conn->error);
            foreach ($teachers_list_att as $teacher_id_att_post => $teacher_data_att_post) {
                $status_val_post = $posted_attendance[$teacher_id_att_post]['status'] ?? '0'; // Default absent (0)
                $att_notes_post = sanitize_input($posted_attendance[$teacher_id_att_post]['notes'] ?? '');
                $is_present_db_val_post = 0;
                if ($status_val_post === '1') $is_present_db_val_post = 1; // present
                elseif ($status_val_post === '2') $is_present_db_val_post = 2; // late

                $attendee_name_db_post = $teacher_data_att_post['FirstName'] . ' ' . $teacher_data_att_post['LastName'];
                $stmt_upsert_att->bind_param("iiiss", $event_id_for_attendance, $teacher_id_att_post, $is_present_db_val_post, $att_notes_post, $attendee_name_db_post);
                if(!$stmt_upsert_att->execute()) throw new Exception("خطا ثبت حضورغیاب برای ".$attendee_name_db_post.": ".$stmt_upsert_att->error);
            }
            $stmt_upsert_att->close();

            // Update EventCalendar with speakers and potentially structured notes
            $structured_notes_to_store = "سخنرانان: " . $meeting_speakers_post . "\n---\nنکات جلسه:\n" . $meeting_general_notes_post . "\n---\nپذیرایی: " . $meeting_hospitality_post;
            $stmt_update_event_details_post = $conn->prepare("UPDATE EventCalendar SET Notes = ?, Speaker = ? WHERE EventID = ?");
             if($stmt_update_event_details_post){
                 $stmt_update_event_details_post->bind_param("ssi", $structured_notes_to_store, $meeting_speakers_post, $event_id_for_attendance);
                 if(!$stmt_update_event_details_post->execute()) throw new Exception("خطا بروزرسانی جزئیات رویداد: ".$stmt_update_event_details_post->error);
                 $stmt_update_event_details_post->close();
             } else { throw new Exception("خطا آماده سازی بروزرسانی جزئیات رویداد: ".$conn->error); }

            $conn->commit();
            $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'اطلاعات با موفقیت ذخیره شد.'];
            regenerate_csrf_token('inservice_attendance_action_' . $event_id_for_attendance);
            header("Location: attendance.php?event_id=" . $event_id_for_attendance); exit;
        } catch (Exception $e) { $conn->rollback(); $errors_att[] = $e->getMessage(); }
    }
    $csrf_token_attendance = regenerate_csrf_token('inservice_attendance_action_' . $event_id_for_attendance);
}
?>
<div class="page-header">
    <h1>حضور و غیاب و جزئیات جلسه: <?php echo htmlspecialchars($event_data_attendance['EventName'] ?? '...'); ?></h1>
    <p class="page-subtitle">تاریخ جلسه: <?php echo to_jalali($event_data_attendance['EventDate'] ?? '', 'yyyy/MM/dd HH:mm'); ?></p>
    <div class="page-header-actions"><a href="events.php" class="btn btn-secondary">بازگشت به رویدادها</a></div>
</div>

<?php if (isset($_SESSION['flash_message'])) { $flash_att = $_SESSION['flash_message']; echo "<div class='alert alert-{$flash_att['type']} alert-dismissible fade show'>{$flash_att['text']}<button type='button' class='close' data-dismiss='alert'>&times;</button></div>"; unset($_SESSION['flash_message']); echo "<script> /* Dismiss JS */</script>";} ?>
<?php if (!empty($errors_att)): ?> <div class="alert alert-danger"><ul><?php foreach ($errors_att as $err_att_item_msg): ?><li><?php echo htmlspecialchars($err_att_item_msg); ?></li><?php endforeach; ?></ul></div> <?php endif; ?>

<form action="attendance.php?event_id=<?php echo $event_id_for_attendance; ?>" method="POST" id="attendanceForm">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_attendance; ?>">
    <input type="hidden" name="event_id" value="<?php echo $event_id_for_attendance; ?>">

    <div class="card shadow-sm mb-4"><div class="card-header"><h5 class="mb-0 card-title-text">لیست حضور و غیاب مدرسین</h5></div>
    <div class="card-body"> <?php if(!empty($teachers_list_att)): ?> <div class="table-responsive">
    <table class="table table-sm table-hover"><thead><tr><th>مدرس</th><th>وضعیت حضور</th><th>یادداشت غیبت/تاخیر</th></tr></thead><tbody>
    <?php foreach($teachers_list_att as $tid_att_form => $teacher_form):
        $current_status_form = $attendance_records_db[$tid_att_form]['IsPresent'] ?? '0'; // Default absent (0)
        $current_notes_form = $attendance_records_db[$tid_att_form]['Notes'] ?? '';
    ?>
    <tr><td><?php echo htmlspecialchars($teacher_form['FirstName'].' '.$teacher_form['LastName']);?></td>
    <td><select name="attendance[<?php echo $tid_att_form;?>][status]" class="form-control form-control-sm custom-select">
        <option value="1" <?php if($current_status_form == 1) echo 'selected';?>>حاضر</option>
        <option value="0" <?php if($current_status_form == 0) echo 'selected';?>>غایب</option>
        <option value="2" <?php if($current_status_form == 2) echo 'selected';?>>تاخیر</option>
    </select></td>
    <td><input type="text" name="attendance[<?php echo $tid_att_form;?>][notes]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($current_notes_form);?>" placeholder="علت غیبت/میزان تاخیر"></td>
    </tr><?php endforeach; ?></tbody></table></div>
    <?php else: ?><p class="text-muted">مدرسی برای ثبت حضور و غیاب یافت نشد.</p><?php endif; ?>
    </div></div>

    <div class="card shadow-sm"><div class="card-header"><h5 class="mb-0 card-title-text">جزئیات تکمیلی جلسه</h5></div>
    <div class="card-body">
        <div class="form-group"><label for="meeting_speakers_form">افراد صحبت کننده در جلسه (نام‌ها با کاما جدا شوند)</label><input type="text" class="form-control" id="meeting_speakers_form" name="meeting_speakers" value="<?php echo htmlspecialchars($meeting_details_db['speakers']); ?>"></div>
        <div class="form-group"><label for="meeting_general_notes_form">نکات خاص و مهم جلسه</label><textarea class="form-control" id="meeting_general_notes_form" name="meeting_general_notes" rows="3"><?php echo htmlspecialchars($meeting_details_db['meeting_notes']); ?></textarea></div>
        <div class="form-group"><label for="meeting_hospitality_form">پذیرایی</label><input type="text" class="form-control" id="meeting_hospitality_form" name="meeting_hospitality" value="<?php echo htmlspecialchars($meeting_details_db['hospitality']); ?>"></div>
    </div></div>
    <div class="form-actions mt-4 text-center"><button type="submit" name="submit_attendance_details" class="btn btn-primary btn-lg">ذخیره اطلاعات جلسه</button></div>
</form>
<script> /* Alert dismissal JS... */</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
