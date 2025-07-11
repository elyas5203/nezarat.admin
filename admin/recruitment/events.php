<?php
// admin/recruitment/events.php - Manage Recruitment Events
require_once __DIR__ . '/../includes/header.php';

// Placeholder data for events
$sample_events = [
    ['EventID' => 1, 'EventName' => 'جشن بزرگ غدیر ۱۴۰۲', 'EventDate' => '1402/04/16', 'Description' => 'مراسم عمومی جشن غدیر در پارک ملت', 'AttendeesCount' => 120],
    ['EventID' => 2, 'EventName' => 'جشن نیمه شعبان ۱۴۰۳', 'EventDate' => '1402/12/06', 'Description' => 'برگزاری جشن به مناسبت میلاد امام زمان (عج) در مسجد محله', 'AttendeesCount' => 85],
    ['EventID' => 3, 'EventName' => 'اردوی فرهنگی تابستانه ۱۴۰۲', 'EventDate' => '1402/05/10', 'Description' => 'اردوی یک روزه تفریحی و فرهنگی برای بچه‌های جذب شده', 'AttendeesCount' => 45],
];

$edit_mode = false;
$event_to_edit = ['EventID' => '', 'EventName' => '', 'EventDate' => '', 'Description' => '']; // Initialize for form

if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $edit_id = (int)$_GET['id'];
    foreach ($sample_events as $event) {
        if ($event['EventID'] == $edit_id) {
            $event_to_edit = $event;
            $edit_mode = true;
            break;
        }
    }
    if (!$edit_mode) {
         echo "<div class='alert alert-danger'>مراسم مورد نظر برای ویرایش یافت نشد.</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_event']) || isset($_POST['edit_event']))) {
    $event_name = sanitize_input($_POST['event_name'] ?? '');
    $event_date_jalali = sanitize_input($_POST['event_date'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $event_id_to_update = sanitize_input($_POST['event_id'] ?? null);

    // Basic validation
    if (!empty($event_name) && !empty($event_date_jalali)) {
        // In a real app, convert $event_date_jalali to Gregorian for DB storage
        // $event_date_gregorian = to_gregorian_date_for_db($event_date_jalali);

        if ($event_id_to_update) {
            $feedback_message = "مراسم \"".htmlspecialchars($event_name)."\" (نمونه) ویرایش شد.";
            // Update logic for $sample_events or DB
        } else {
            $feedback_message = "مراسم \"".htmlspecialchars($event_name)."\" (نمونه) اضافه شد.";
            // Add logic for $sample_events or DB
        }
        echo "<div class='alert alert-success mt-3'>".$feedback_message." (این عملیات هنوز به دیتابیس متصل نیست)</div>";
        if(!$event_id_to_update){
            $event_to_edit = ['EventID' => '', 'EventName' => '', 'EventDate' => '', 'Description' => '']; // Clear form
        }
    } else {
        echo "<div class='alert alert-danger mt-3'>نام مراسم و تاریخ برگزاری نمی‌توانند خالی باشند.</div>";
    }
}

?>
<link rel="stylesheet" href="/my_site/assets/css/common/persian-datepicker.min.css"/>

<div class="page-header">
    <h1>مدیریت مراسم‌های جذب</h1>
    <p class="page-subtitle">ایجاد، ویرایش و مشاهده مراسم‌ها و رویدادهای مرتبط با جذب.</p>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5 class="mb-0"><?php echo $edit_mode ? 'ویرایش مراسم: ' . htmlspecialchars($event_to_edit['EventName']) : 'افزودن مراسم جدید'; ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" action="events.php<?php echo $edit_mode ? '?action=edit&id='.htmlspecialchars($event_to_edit['EventID']) : ''; ?>">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($event_to_edit['EventID']); ?>">
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="event_name">نام مراسم:<span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="event_name" name="event_name" value="<?php echo htmlspecialchars($event_to_edit['EventName']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="event_date">تاریخ برگزاری:<span class="text-danger">*</span></label>
                        <input type="text" class="form-control persian-date-picker" id="event_date" name="event_date" value="<?php echo htmlspecialchars($event_to_edit['EventDate']); ?>" required placeholder="مثال: ۱۴۰۲/۰۱/۱۵">
                    </div>
                    <div class="form-group">
                        <label for="description">توضیحات:</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($event_to_edit['Description']); ?></textarea>
                    </div>
                    <?php if ($edit_mode): ?>
                        <button type="submit" name="edit_event" class="btn btn-primary">ذخیره تغییرات</button>
                        <a href="events.php" class="btn btn-outline-secondary ml-2">انصراف</a>
                    <?php else: ?>
                        <button type="submit" name="add_event" class="btn btn-success">افزودن مراسم</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">لیست مراسم‌ها</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">نام مراسم</th>
                                <th scope="col">تاریخ برگزاری</th>
                                <th scope="col">حاضرین (نمونه)</th>
                                <th scope="col">توضیحات</th>
                                <th scope="col">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sample_events)): ?>
                                <tr><td colspan="6" class="text-center">هنوز مراسمی ثبت نشده است.</td></tr>
                            <?php else: ?>
                                <?php foreach ($sample_events as $event): ?>
                                <tr>
                                    <th scope="row"><?php echo $event['EventID']; ?></th>
                                    <td><?php echo htmlspecialchars($event['EventName']); ?></td>
                                    <td><?php echo htmlspecialchars($event['EventDate']); ?></td>
                                    <td><?php echo $event['AttendeesCount']; ?></td>
                                    <td title="<?php echo htmlspecialchars($event['Description']);?>"><?php echo htmlspecialchars(mb_substr($event['Description'], 0, 40) . (mb_strlen($event['Description']) > 40 ? '...' : '')); ?></td>
                                    <td>
                                        <a href="events.php?action=edit&id=<?php echo $event['EventID']; ?>" class="btn btn-sm btn-outline-primary" title="ویرایش">
                                           <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16"><path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/><path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"/></svg>
                                        </a>
                                         <a href="events.php?action=delete&id=<?php echo $event['EventID']; ?>" onclick="return confirm('آیا از حذف مراسم \'<?php echo htmlspecialchars(addslashes($event['EventName'])); ?>\' مطمئن هستید؟ این عملیات فعلا نمایشی است.');" class="btn btn-sm btn-outline-danger" title="حذف">
                                             <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash3-fill" viewBox="0 0 16 16"><path d="M11 1.5v1h3.5a.5.5 0 0 1 0 1h-.538l-.853 10.66A2 2 0 0 1 11.115 16h-6.23a2 2 0 0 1-1.994-1.84L2.038 3.5H1.5a.5.5 0 0 1 0-1H5v-1A1.5 1.5 0 0 1 6.5 0h3A1.5 1.5 0 0 1 11 1.5Zm-5 0v1h4v-1a.5.5 0 0 0-.5-.5h-3a.5.5 0 0 0-.5.5ZM4.5 5.024l.088-.88A.5.5 0 0 1 5 4h6a.5.5 0 0 1 .412.223l.088.88H4.5Z"/></svg>
                                        </a>
                                        <a href="event_attendance.php?event_id=<?php echo $event['EventID']; ?>" class="btn btn-sm btn-outline-success" title="مدیریت شرکت کنندگان (نمایشی)">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-people" viewBox="0 0 16 16"><path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1h8zm-7.978-1A.261.261 0 0 1 7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002a.274.274 0 0 1-.014.002H7.022zM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4zm3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0zM6.957 12.244A2.25 2.25 0 0 1 6 13.5a2.25 2.25 0 0 1-2.25-2.25c0-1.152.255-2.151.653-2.917.075-.143.15-.287.233-.428A3.5 3.5 0 0 1 3 5.5c0-1.41.768-2.652 1.784-3.333.05-.027.1-.053.15-.077A3.01 3.01 0 0 1 6 2a2.999 2.999 0 0 1 2.25.985c.076.087.145.181.208.283A2.5 2.5 0 0 0 9.5 5c.001.118-.012.234-.035.346A3.49 3.49 0 0 1 9 8.5c0 .926.325 1.762.852 2.425.058.076.12.147.188.213zM4.5 8a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z"/></svg>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/my_site/assets/js/common/persian-date.min.js"></script>
<script src="/my_site/assets/js/common/persian-datepicker.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var datePickers = document.querySelectorAll('.persian-date-picker');
    datePickers.forEach(function(picker) {
        new persianDatepicker(picker, {
            format: 'YYYY/MM/DD',
            autoClose: true,
            observer: true,
            calendar: { persian: { locale: 'fa'}},
            toolbox:{ calendarSwitch:{ enabled:false }}
        });
    });
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
