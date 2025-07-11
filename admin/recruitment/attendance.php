<?php
// admin/recruitment/attendance.php - Manage Event Attendance (Recruitment)
require_once __DIR__ . '/../includes/header.php';

// --- Placeholder Data (Normally from DB) ---
$sample_prospects_for_attendance = [
    ['ProspectID' => 1, 'FullName' => 'علی رضایی'],
    ['ProspectID' => 2, 'FullName' => 'زهرا محمدی'],
    ['ProspectID' => 3, 'FullName' => 'محمد حسینی'],
    ['ProspectID' => 4, 'FullName' => 'فاطمه کاظمی'],
];

$sample_events_for_attendance = [
    ['EventID' => 1, 'EventName' => 'جشن بزرگ غدیر ۱۴۰۲'],
    ['EventID' => 2, 'EventName' => 'جشن نیمه شعبان ۱۴۰۳'],
    ['EventID' => 3, 'EventName' => 'اردوی فرهنگی تابستانه ۱۴۰۲'],
];

// Sample attendance data (ProspectID => [EventID1, EventID2, ...])
$sample_attendance_history = [
    1 => [ // Ali Rezaei
        ['EventName' => 'جشن بزرگ غدیر ۱۴۰۲', 'AttendanceDate' => '1402/04/16'],
        ['EventName' => 'اردوی فرهنگی تابستانه ۱۴۰۲', 'AttendanceDate' => '1402/05/10'],
    ],
    2 => [ // Zahra Mohammadi
        ['EventName' => 'جشن نیمه شعبان ۱۴۰۳', 'AttendanceDate' => '1402/12/06'],
    ],
];

// Sample attendees for a specific event (EventID => [ProspectFullName1, ProspectFullName2, ...])
$sample_event_attendees_list = [
    1 => ['علی رضایی', 'محمد حسینی', 'سارا احمدی (نمونه)'], // Ghadeer 1402
    2 => ['زهرا محمدی', 'فاطمه کاظمی (نمونه)'],    // Nime Sha'ban 1403
    3 => ['علی رضایی'],                         // Summer Camp 1402
];

$selected_prospect_id_history = isset($_GET['prospect_id_history']) ? (int)$_GET['prospect_id_history'] : null;
$selected_event_id_list = isset($_GET['event_id_list']) ? (int)$_GET['event_id_list'] : null;
$feedback_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    $prospect_id_att = sanitize_input($_POST['prospect_id_att'] ?? null);
    $event_id_att = sanitize_input($_POST['event_id_att'] ?? null);

    if ($prospect_id_att && $event_id_att) {
        // Find names for feedback
        $p_name = "فرد با ID: ".$prospect_id_att;
        $e_name = "مراسم با ID: ".$event_id_att;
        foreach($sample_prospects_for_attendance as $p) if($p['ProspectID'] == $prospect_id_att) $p_name = $p['FullName'];
        foreach($sample_events_for_attendance as $e) if($e['EventID'] == $event_id_att) $e_name = $e['EventName'];

        $feedback_message = "حضور ".htmlspecialchars($p_name)." در مراسم \"".htmlspecialchars($e_name)."\" (نمونه) ثبت شد. (این عملیات هنوز به دیتابیس متصل نیست)";
        echo "<div class='alert alert-success'>".$feedback_message."</div>";
    } else {
        echo "<div class='alert alert-danger'>لطفاً فرد و مراسم را برای ثبت حضور انتخاب کنید.</div>";
    }
}

?>
<div class="page-header">
    <h1>ثبت و نمایش حضور و غیاب در مراسم جذب</h1>
    <p class="page-subtitle">مدیریت حضور افراد در رویدادهای مختلف جذب.</p>
</div>

<?php if ($feedback_message && $_SERVER['REQUEST_METHOD'] !== 'POST'): // Show feedback if redirected with GET ?>
    <div class="alert alert-info"><?php echo $feedback_message; ?></div>
<?php endif; ?>

<div class="row">
    <!-- Section 1: Mark Attendance -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm">
            <div class="card-header"><h5 class="mb-0">ثبت حضور جدید (نمایشی)</h5></div>
            <div class="card-body">
                <form method="POST" action="attendance.php">
                    <div class="form-group">
                        <label for="prospect_id_att">انتخاب فرد:</label>
                        <select name="prospect_id_att" id="prospect_id_att" class="form-control custom-select" required>
                            <option value="">-- فرد را انتخاب کنید --</option>
                            <?php foreach ($sample_prospects_for_attendance as $prospect): ?>
                                <option value="<?php echo $prospect['ProspectID']; ?>"><?php echo htmlspecialchars($prospect['FullName']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="event_id_att">انتخاب مراسم:</label>
                        <select name="event_id_att" id="event_id_att" class="form-control custom-select" required>
                            <option value="">-- مراسم را انتخاب کنید --</option>
                             <?php foreach ($sample_events_for_attendance as $event): ?>
                                <option value="<?php echo $event['EventID']; ?>"><?php echo htmlspecialchars($event['EventName']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="mark_attendance" class="btn btn-primary">ثبت حضور</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Section 2: View Attendance History for a Prospect -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm">
            <div class="card-header"><h5 class="mb-0">مشاهده تاریخچه حضور فرد (نمایشی)</h5></div>
            <div class="card-body">
                <form method="GET" action="attendance.php" class="mb-3">
                    <div class="form-group">
                        <label for="prospect_id_history_select">انتخاب فرد:</label>
                        <select name="prospect_id_history" id="prospect_id_history_select" class="form-control custom-select" onchange="this.form.submit()">
                            <option value="">-- فرد را برای مشاهده تاریخچه انتخاب کنید --</option>
                            <?php foreach ($sample_prospects_for_attendance as $prospect): ?>
                                <option value="<?php echo $prospect['ProspectID']; ?>" <?php if ($selected_prospect_id_history == $prospect['ProspectID']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($prospect['FullName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
                <?php if ($selected_prospect_id_history && isset($sample_attendance_history[$selected_prospect_id_history])): ?>
                    <h6>تاریخچه حضور برای: <?php echo htmlspecialchars(array_values(array_filter($sample_prospects_for_attendance, function($p) use ($selected_prospect_id_history){ return $p['ProspectID'] == $selected_prospect_id_history; }))[0]['FullName'] ?? ''); ?></h6>
                    <ul class="list-group">
                        <?php foreach ($sample_attendance_history[$selected_prospect_id_history] as $att_rec): ?>
                            <li class="list-group-item"><?php echo htmlspecialchars($att_rec['EventName']); ?> - تاریخ: <?php echo htmlspecialchars($att_rec['AttendanceDate']); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php elseif($selected_prospect_id_history): ?>
                    <p class="text-muted">تاریخچه حضوری برای این فرد (نمونه) ثبت نشده است.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mt-3">
    <!-- Section 3: View Attendees for an Event -->
    <div class="col-md-12 mb-4">
        <div class="card shadow-sm">
            <div class="card-header"><h5 class="mb-0">مشاهده لیست حاضرین مراسم (نمایشی)</h5></div>
            <div class="card-body">
                <form method="GET" action="attendance.php" class="mb-3 form-inline">
                    <div class="form-group mr-2">
                        <label for="event_id_list_select" class="mr-2">انتخاب مراسم:</label>
                        <select name="event_id_list" id="event_id_list_select" class="form-control custom-select">
                            <option value="">-- مراسم را انتخاب کنید --</option>
                            <?php foreach ($sample_events_for_attendance as $event): ?>
                                <option value="<?php echo $event['EventID']; ?>" <?php if ($selected_event_id_list == $event['EventID']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($event['EventName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-info">نمایش لیست</button>
                     <a href="attendance.php" class="btn btn-outline-secondary ml-2">پاک کردن</a>
                </form>

                <?php if ($selected_event_id_list && isset($sample_event_attendees_list[$selected_event_id_list])): ?>
                    <h6>لیست حاضرین در مراسم: <?php echo htmlspecialchars(array_values(array_filter($sample_events_for_attendance, function($e) use ($selected_event_id_list){ return $e['EventID'] == $selected_event_id_list; }))[0]['EventName'] ?? ''); ?></h6>
                    <ul class="list-group">
                        <?php foreach ($sample_event_attendees_list[$selected_event_id_list] as $attendee_name): ?>
                            <li class="list-group-item"><?php echo htmlspecialchars($attendee_name); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php elseif($selected_event_id_list): ?>
                    <p class="text-muted">لیست حاضرینی برای این مراسم (نمونه) ثبت نشده است.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
