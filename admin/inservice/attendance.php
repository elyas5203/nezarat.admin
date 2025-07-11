<?php
// admin/inservice/attendance.php - Manage Attendance for In-Service Events
require_once __DIR__ . '/../includes/header.php';

// Placeholder data
$sample_inservice_events_for_attendance = [
    ['EventID' => 1, 'EventName' => 'کارگاه خلاقیت در تدریس - ۱۴۰۳/۰۲/۱۵'],
    ['EventID' => 2, 'EventName' => 'جلسه هم‌اندیشی ماهانه - ۱۴۰۳/۰۳/۰۱'],
];

$sample_teachers_for_attendance = [
    ['UserID' => 1, 'FullName' => 'آقای دکتر رضایی (مدرس)'],
    ['UserID' => 2, 'FullName' => 'خانم مهندس کریمی (مدرس)'],
    ['UserID' => 3, 'FullName' => 'آقای دکتر حسینی (مدرس)'],
];

// Sample attendance data for a selected event (EventID => [UserID => status, ...])
$sample_event_attendance_data = [
    1 => [ // For EventID 1
        1 => ['IsPresent' => true, 'ArrivalTime' => '08:55', 'DepartureTime' => '12:05', 'GuestName' => ''],
        2 => ['IsPresent' => true, 'ArrivalTime' => '09:00', 'DepartureTime' => '12:00', 'GuestName' => ''],
        3 => ['IsPresent' => false, 'ArrivalTime' => '', 'DepartureTime' => '', 'GuestName' => ''],
    ],
    2 => [ // For EventID 2
        1 => ['IsPresent' => true, 'ArrivalTime' => '14:00', 'DepartureTime' => '16:00', 'GuestName' => ''],
    ],
];
$guest_attendees_sample = [
    1 => [ // Guests for EventID 1
        ['GuestName' => 'آقای دکتر فاطمی (مهمان)', 'ArrivalTime' => '09:10', 'DepartureTime' => '11:30']
    ]
];


$selected_event_id_att = isset($_GET['event_id_att']) ? (int)$_GET['event_id_att'] : null;
$feedback_message_att = ''; // Specific feedback variable for this page

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    // In a real app, add CSRF check here
    $event_id_posted = $_POST['event_id_posted'] ?? null;
    // Placeholder for saving attendance data
    // foreach ($_POST['attendance'] as $user_id => $data) {
    //    $is_present = isset($data['is_present']) ? 1 : 0;
    //    $arrival = $data['arrival_time'];
    //    $departure = $data['departure_time'];
    //    // Save to DB for $event_id_posted and $user_id
    // }
    // foreach ($_POST['guest_name'] as $key => $name) {
    //    if(!empty($name)) {
    //        $guest_arrival = $_POST['guest_arrival_time'][$key];
    //        $guest_departure = $_POST['guest_departure_time'][$key];
    //        // save guest for $event_id_posted
    //    }
    // }
    $feedback_message_att = "<div class='alert alert-success'>لیست حضور و غیاب (نمونه) برای رویداد ID: ".htmlspecialchars($event_id_posted)." ذخیره شد. (این عملیات هنوز به دیتابیس متصل نیست)</div>";
}


?>

<div class="page-header">
    <h1>حضور و غیاب جلسات ضمن خدمت</h1>
    <p class="page-subtitle">ثبت و مشاهده وضعیت حضور مدرسین و مهمانان در رویدادهای ضمن خدمت.</p>
</div>

<?php echo $feedback_message_att; // Display feedback if any ?>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="mb-0">انتخاب رویداد برای مدیریت حضور و غیاب</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="attendance.php" class="form-inline">
            <div class="form-group mr-2">
                <label for="event_id_att_select" class="mr-2">رویداد:</label>
                <select name="event_id_att" id="event_id_att_select" class="form-control custom-select" onchange="this.form.submit()">
                    <option value="">-- یک رویداد انتخاب کنید --</option>
                    <?php foreach($sample_inservice_events_for_attendance as $event): ?>
                        <option value="<?php echo $event['EventID']; ?>" <?php if($selected_event_id_att == $event['EventID']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($event['EventName']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
             <?php if ($selected_event_id_att): ?>
                 <a href="attendance.php" class="btn btn-outline-secondary ml-2">پاک کردن انتخاب</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if ($selected_event_id_att):
    $current_event_name = "رویداد انتخاب شده";
    foreach($sample_inservice_events_for_attendance as $ev) {
        if($ev['EventID'] == $selected_event_id_att) {
            $current_event_name = $ev['EventName'];
            break;
        }
    }
?>
<form method="POST" action="attendance.php?event_id_att=<?php echo $selected_event_id_att; ?>">
    <input type="hidden" name="event_id_posted" value="<?php echo $selected_event_id_att; ?>">
    <div class="card shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">لیست حضور و غیاب برای: <?php echo htmlspecialchars($current_event_name); ?></h5>
        </div>
        <div class="card-body">
            <h6>مدرسین ثبت شده:</h6>
            <div class="table-responsive mb-3">
                <table class="table table-bordered table-sm">
                    <thead>
                        <tr>
                            <th>نام مدرس</th>
                            <th style="width: 80px;">حاضر؟</th>
                            <th style="width: 120px;">ساعت ورود</th>
                            <th style="width: 120px;">ساعت خروج</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($sample_teachers_for_attendance as $teacher):
                            $att_data = $sample_event_attendance_data[$selected_event_id_att][$teacher['UserID']] ?? ['IsPresent' => false, 'ArrivalTime' => '', 'DepartureTime' => ''];
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($teacher['FullName']); ?></td>
                            <td>
                                <input type="checkbox" name="attendance[<?php echo $teacher['UserID']; ?>][is_present]" value="1" <?php if($att_data['IsPresent']) echo 'checked'; ?>>
                            </td>
                            <td><input type="time" class="form-control form-control-sm" name="attendance[<?php echo $teacher['UserID']; ?>][arrival_time]" value="<?php echo htmlspecialchars($att_data['ArrivalTime']); ?>"></td>
                            <td><input type="time" class="form-control form-control-sm" name="attendance[<?php echo $teacher['UserID']; ?>][departure_time]" value="<?php echo htmlspecialchars($att_data['DepartureTime']); ?>"></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h6>افزودن مهمانان حاضر:</h6>
            <div id="guestAttendeesContainer">
                <?php
                $current_guests_for_event = $guest_attendees_sample[$selected_event_id_att] ?? [];
                if (!empty($current_guests_for_event)) {
                    foreach($current_guests_for_event as $idx => $guest): ?>
                    <div class="form-row align-items-center mb-2 guest-row">
                        <div class="col-sm-5"><input type="text" name="guest_name[]" class="form-control form-control-sm" placeholder="نام و نام خانوادگی مهمان" value="<?php echo htmlspecialchars($guest['GuestName']); ?>"></div>
                        <div class="col-sm-3"><input type="time" name="guest_arrival_time[]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($guest['ArrivalTime']); ?>"></div>
                        <div class="col-sm-3"><input type="time" name="guest_departure_time[]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($guest['DepartureTime']); ?>"></div>
                        <div class="col-sm-1"><button type="button" class="btn btn-sm btn-danger remove-guest-btn">&times;</button></div>
                    </div>
                    <?php endforeach;
                }?>
            </div>
            <button type="button" id="addGuestAttendee" class="btn btn-sm btn-outline-success mt-2">افزودن مهمان</button>

        </div>
        <div class="card-footer text-right">
            <button type="submit" name="save_attendance" class="btn btn-primary">ذخیره لیست حضور و غیاب (نمایشی)</button>
        </div>
    </div>
</form>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const addGuestBtn = document.getElementById('addGuestAttendee');
    if(addGuestBtn) {
        addGuestBtn.addEventListener('click', function() {
            const container = document.getElementById('guestAttendeesContainer');
            const newRow = document.createElement('div');
            newRow.classList.add('form-row', 'align-items-center', 'mb-2', 'guest-row');
            newRow.innerHTML = `
                <div class="col-sm-5"><input type="text" name="guest_name[]" class="form-control form-control-sm" placeholder="نام و نام خانوادگی مهمان"></div>
                <div class="col-sm-3"><input type="time" name="guest_arrival_time[]" class="form-control form-control-sm"></div>
                <div class="col-sm-3"><input type="time" name="guest_departure_time[]" class="form-control form-control-sm"></div>
                <div class="col-sm-1"><button type="button" class="btn btn-sm btn-danger remove-guest-btn">&times;</button></div>
            `;
            container.appendChild(newRow);
        });
    }

    const guestContainer = document.getElementById('guestAttendeesContainer');
    if(guestContainer){
        guestContainer.addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('remove-guest-btn')) {
                e.target.closest('.guest-row').remove();
            }
        });
    }
});
</script>
<?php else: ?>
    <div class="alert alert-warning">لطفاً یک رویداد را برای مدیریت حضور و غیاب انتخاب کنید.</div>
<?php endif; ?>


<?php
require_once __DIR__ . '/../includes/footer.php';
?>
