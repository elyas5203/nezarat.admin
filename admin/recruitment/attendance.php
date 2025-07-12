<?php
require_once __DIR__ . '/../includes/header.php';

$selected_event_id = isset($_REQUEST['event_id']) ? (int)$_REQUEST['event_id'] : null;
$event_details = null;
$prospects_for_event_display = [];
$attendance_records_map = [];

$form_errors_rec_att = [];
$page_title_rec_att = "ثبت حضور و غیاب در مراسم جذب";
$csrf_token_name_rec_att_form = 'rec_event_attendance_form_action';
$csrf_token_rec_att_form_val = generate_csrf_token($csrf_token_name_rec_att_form);

// Fetch all recruitment events for dropdown
$all_rec_events_dropdown = [];
if($conn){
    $res_all_ev_dd = $conn->query("SELECT EventID, EventName, EventDate FROM RecruitmentEvents ORDER BY EventDate DESC");
    if($res_all_ev_dd) while($row_dd = $res_all_ev_dd->fetch_assoc()) $all_rec_events_dropdown[] = $row_dd;
    else $form_errors_rec_att['fetch_events'] = "خطا در بارگذاری لیست مراسم: " . $conn->error;
} else {
    $form_errors_rec_att['db_conn'] = "خطا در اتصال اولیه به پایگاه داده.";
}

if ($selected_event_id && $conn) {
    $stmt_ev_details = $conn->prepare("SELECT EventID, EventName, EventDate FROM RecruitmentEvents WHERE EventID = ?");
    if($stmt_ev_details){
        $stmt_ev_details->bind_param("i", $selected_event_id);
        $stmt_ev_details->execute();
        $res_ev_details = $stmt_ev_details->get_result();
        if(!($event_details = $res_ev_details->fetch_assoc())){
            $_SESSION['action_error_recruitment'] = "مراسم جذب انتخاب شده یافت نشد."; $selected_event_id = null;
        }
        $stmt_ev_details->close();
    } else { $form_errors_rec_att['db_load_event'] = "خطا بارگذاری اطلاعات مراسم: " . $conn->error; $selected_event_id = null; }

    if($event_details){
        // Fetch all prospects, and mark if they are primarily linked to THIS event.
        $stmt_prospects_list = $conn->query("SELECT ProspectID, ProspectName, ParentName, PhoneNumber, RecruitmentEventID as PrimarilyLinkedEventID FROM RecruitmentProspects ORDER BY ProspectName ASC");
        if($stmt_prospects_list) while($row_p = $stmt_prospects_list->fetch_assoc()) $prospects_for_event_display[] = $row_p;
        else $form_errors_rec_att['db_load_prospects'] = "خطا در بارگذاری لیست افراد.";

        $stmt_att_records = $conn->prepare("SELECT ProspectID, AttendanceStatus, Notes FROM RecruitmentEventAttendance WHERE EventID = ?");
        if($stmt_att_records){
            $stmt_att_records->bind_param("i", $selected_event_id); $stmt_att_records->execute();
            $res_att_records = $stmt_att_records->get_result();
            while($row_att = $res_att_records->fetch_assoc()) $attendance_records_map[$row_att['ProspectID']] = $row_att;
            $stmt_att_records->close();
        } else $form_errors_rec_att['db_load_attendance'] = "خطا در بارگذاری سوابق حضور.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '', $csrf_token_name_rec_att_form)) {
        $form_errors_rec_att['csrf'] = "خطای CSRF.";
    } else {
        $csrf_token_rec_att_form_val = regenerate_csrf_token($csrf_token_name_rec_att_form);
        $posted_event_id_att = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
        $attendances_posted_data = $_POST['attendance'] ?? [];

        if ($posted_event_id_att > 0 && $conn) {
            // Re-fetch event_details for the posted event_id to ensure it's valid and get its date
            $stmt_posted_ev = $conn->prepare("SELECT EventDate FROM RecruitmentEvents WHERE EventID = ?");
            $event_date_for_attendance_record = null;
            if($stmt_posted_ev){
                $stmt_posted_ev->bind_param("i", $posted_event_id_att); $stmt_posted_ev->execute();
                $res_posted_ev = $stmt_posted_ev->get_result();
                if($ed_posted = $res_posted_ev->fetch_assoc()) $event_date_for_attendance_record = $ed_posted['EventDate'];
                $stmt_posted_ev->close();
            }
            if(!$event_date_for_attendance_record) $event_date_for_attendance_record = date('Y-m-d'); // Fallback

            $conn->begin_transaction();
            try {
                $stmt_del_old_att_rec = $conn->prepare("DELETE FROM RecruitmentEventAttendance WHERE EventID = ?");
                if(!$stmt_del_old_att_rec) throw new Exception("خطا آماده سازی حذف سوابق: ".$conn->error);
                $stmt_del_old_att_rec->bind_param("i", $posted_event_id_att);
                if(!$stmt_del_old_att_rec->execute()) throw new Exception("خطا در حذف سوابق: ".$stmt_del_old_att_rec->error);
                $stmt_del_old_att_rec->close();

                $stmt_ins_att_rec = $conn->prepare("INSERT INTO RecruitmentEventAttendance (EventID, ProspectID, AttendanceDate, AttendanceStatus, Notes) VALUES (?, ?, ?, ?, ?)");
                if(!$stmt_ins_att_rec) throw new Exception("خطا آماده سازی درج حضور: ".$conn->error);

                foreach ($attendances_posted_data as $prospect_id_att_p => $att_data_p) {
                    $prospect_id_att_int_p = (int)$prospect_id_att_p;
                    $status_att_p = sanitize_input($att_data_p['status'] ?? 'absent'); // Default to absent if not set but submitted
                    $notes_att_p = sanitize_input($att_data_p['notes'] ?? null);

                    // Only insert if a status is explicitly chosen (not the default "---" or empty)
                    if (!empty($status_att_p) && in_array($status_att_p, ['attended', 'absent', 'tentative'])) {
                        $stmt_ins_att_rec->bind_param("iisss", $posted_event_id_att, $prospect_id_att_int_p, $event_date_for_attendance_record, $status_att_p, $notes_att_p);
                        if(!$stmt_ins_att_rec->execute()) throw new Exception("خطا ثبت حضور فرد ID ".$prospect_id_att_int_p.": ".$stmt_ins_att_rec->error);
                    }
                }
                $stmt_ins_att_rec->close();
                $conn->commit();
                $_SESSION['action_success_recruitment'] = "حضور و غیاب ثبت شد.";
                header("Location: attendance.php?event_id=" . $posted_event_id_att);
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                $form_errors_rec_att['db'] = "خطای پایگاه داده: " . $e->getMessage();
            }
        } else {
            $form_errors_rec_att['event_id'] = "ابتدا یک مراسم را انتخاب کنید یا خطایی در اتصال به دیتابیس رخ داده.";
        }
        $selected_event_id = $posted_event_id_att; // Keep selected event on error
        // Re-fetch data for display if POST failed, using $selected_event_id
        // This logic is already at the top of the script, so it will re-run if $selected_event_id is set.
    }
}
?>
<div class="page-header">
    <h1><?php echo $page_title_rec_att; ?></h1>
    <div class="page-header-actions">
        <a href="index.php" class="btn btn-outline-secondary"><em class="bi bi-house-door icon"></em> داشبورد جذب</a>
        <a href="events.php" class="btn btn-outline-secondary ms-2"><em class="bi bi-calendar-event icon"></em> مدیریت مراسم‌ها</a>
    </div>
</div>

<?php if(isset($_SESSION['action_success_recruitment'])):?><div class="alert alert-success alert-dismissible fade show"><button class="btn-close" data-bs-dismiss="alert"></button><?php echo $_SESSION['action_success_recruitment']; unset($_SESSION['action_success_recruitment']);?></div><?php endif;?>
<?php if(!empty($form_errors_rec_att)):?><div class="alert alert-danger alert-dismissible fade show"><strong>خطا:</strong><ul class="mb-0 ps-3"><?php foreach($form_errors_rec_att as $e_key_att=>$e_msg_att):echo "<li>".htmlspecialchars($e_msg_att)."</li>";endforeach;?></ul><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif;?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="attendance.php" class="row g-3 align-items-center">
            <div class="col-md-8">
                <label for="event_id_select_att_page" class="form-label">انتخاب مراسم جذب:</label>
                <select name="event_id" id="event_id_select_att_page" class="form-select" onchange="this.form.submit()">
                    <option value="">--- یک مراسم را انتخاب کنید ---</option>
                    <?php foreach($all_rec_events_dropdown as $ev_opt_att): ?>
                        <option value="<?php echo $ev_opt_att['EventID']; ?>" <?php echo ($selected_event_id == $ev_opt_att['EventID']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ev_opt_att['EventName'] . ' (' . to_jalali($ev_opt_att['EventDate'], 'yyyy/MM/dd') . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 align-self-end">
                <button type="submit" class="btn btn-primary w-100">نمایش لیست افراد</button>
            </div>
        </form>
    </div>
</div>

<?php if ($selected_event_id && $event_details): ?>
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">ثبت حضور برای: <?php echo htmlspecialchars($event_details['EventName']); ?> (<?php echo to_jalali($event_details['EventDate'], 'yyyy/MM/dd'); ?>)</h5>
    </div>
    <div class="card-body">
        <?php if(empty($prospects_for_event_display)): ?>
            <p class="text-muted">هیچ فردی برای ثبت حضور یافت نشد. ابتدا از بخش <a href="prospects.php?action=create">ثبت افراد</a>، آنها را اضافه کنید.</p>
        <?php else: ?>
            <form method="POST" action="attendance.php">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_rec_att_form_val; ?>">
                <input type="hidden" name="event_id" value="<?php echo $selected_event_id; ?>">
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead class="table-light">
                            <tr><th>نام فرد</th><th>والدین</th><th>شماره تماس</th><th>وضعیت حضور</th><th>ملاحظات</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($prospects_for_event_display as $prospect_att):
                                $pid_att = $prospect_att['ProspectID'];
                                $current_status_att = $attendance_records_map[$pid_att]['AttendanceStatus'] ?? '';
                                $current_notes_att = $attendance_records_map[$pid_att]['Notes'] ?? '';
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($prospect_att['ProspectName']); ?>
                                    <?php if($prospect_att['PrimarilyLinkedEventID'] == $selected_event_id): ?><em class="bi bi-link-45deg text-success ms-1" title="مرتبط با این مراسم"></em><?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($prospect_att['ParentName'] ?: '---'); ?></td>
                                <td><?php echo htmlspecialchars($prospect_att['PhoneNumber']); ?></td>
                                <td>
                                    <select name="attendance[<?php echo $pid_att; ?>][status]" class="form-select form-select-sm">
                                        <option value="" <?php echo ($current_status_att === '') ? 'selected' : ''; ?>>-- انتخاب --</option>
                                        <option value="attended" <?php echo ($current_status_att === 'attended') ? 'selected' : ''; ?>>حاضر</option>
                                        <option value="absent" <?php echo ($current_status_att === 'absent') ? 'selected' : ''; ?>>غایب</option>
                                        <option value="tentative" <?php echo ($current_status_att === 'tentative') ? 'selected' : ''; ?>>احتمالی</option>
                                    </select>
                                </td>
                                <td><input type="text" name="attendance[<?php echo $pid_att; ?>][notes]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($current_notes_att); ?>" placeholder="اختیاری"></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="form-actions mt-3"><button type="submit" name="save_attendance" class="btn btn-success"><em class="bi bi-check-all icon"></em> ذخیره حضور و غیاب</button></div>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php elseif ($selected_event_id && !$event_details && empty($form_errors_rec_att['db_load_event'])): ?>
    <div class="alert alert-warning">مراسم انتخاب شده یافت نشد.</div>
<?php elseif (!$selected_event_id && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['event_id'])): ?>
    <div class="alert alert-info">لطفا یک مراسم را از لیست بالا برای ثبت حضور و غیاب انتخاب کنید.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
