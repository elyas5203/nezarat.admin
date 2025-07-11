<?php
// admin/inservice/event_checklists.php - Manage Checklists for Specific In-Service Events
require_once __DIR__ . '/../includes/header.php';

// Placeholder data
$sample_inservice_events_for_checklist = [
    ['EventID' => 1, 'EventName' => 'کارگاه خلاقیت در تدریس - ۱۴۰۳/۰۲/۱۵'],
    ['EventID' => 2, 'EventName' => 'جلسه هم‌اندیشی ماهانه - ۱۴۰۳/۰۳/۰۱'],
];

$sample_checklist_items_for_event = [
    ['ItemText' => 'هماهنگی مکان برگزاری', 'ResponsibleUser' => 'آقای احمدی', 'Status' => 'انجام شده', 'DueDate' => '۱۴۰۳/۰۲/۱۰'],
    ['ItemText' => 'ارسال دعوتنامه به مدرسین', 'ResponsibleUser' => 'خانم کریمی', 'Status' => 'در حال انجام', 'DueDate' => '۱۴۰۳/۰۲/۱۲'],
    ['ItemText' => 'آماده‌سازی محتوای ارائه', 'ResponsibleUser' => 'دکتر رضایی', 'Status' => 'انجام نشده', 'DueDate' => '۱۴۰۳/۰۲/۱۴'],
    ['ItemText' => 'جمع‌آوری بازخوردها پس از جلسه', 'ResponsibleUser' => 'آقای احمدی', 'Status' => 'انجام نشده', 'DueDate' => '۱۴۰۳/۰۲/۱۷'],
];

$selected_event_id = null;
if(isset($_GET['event_id'])) {
    $selected_event_id = (int)$_GET['event_id'];
    // In a real app, you would fetch the event details and its checklist items from DB
}

?>

<div class="page-header">
    <h1>مدیریت چک‌لیست رویدادهای ضمن خدمت</h1>
    <p class="page-subtitle">اختصاص، پیگیری و به‌روزرسانی وضعیت آیتم‌های چک‌لیست برای هر رویداد.</p>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="mb-0">انتخاب رویداد برای مدیریت چک‌لیست</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="event_checklists.php" class="form-inline">
            <div class="form-group mr-2">
                <label for="event_id_select" class="mr-2">رویداد:</label>
                <select name="event_id" id="event_id_select" class="form-control custom-select" onchange="this.form.submit()">
                    <option value="">-- یک رویداد انتخاب کنید --</option>
                    <?php foreach($sample_inservice_events_for_checklist as $event): ?>
                        <option value="<?php echo $event['EventID']; ?>" <?php if($selected_event_id == $event['EventID']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($event['EventName']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($selected_event_id): ?>
                 <a href="event_checklists.php" class="btn btn-outline-secondary ml-2">پاک کردن انتخاب</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if ($selected_event_id): ?>
<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">چک‌لیست برای رویداد: <?php
            $eventNameDisplay = "انتخاب نشده";
            foreach($sample_inservice_events_for_checklist as $ev) if($ev['EventID'] == $selected_event_id) $eventNameDisplay = $ev['EventName'];
            echo htmlspecialchars($eventNameDisplay);
        ?></h5>
        <small>(این یک نمایش نمونه است. در نسخه نهایی، امکان تخصیص چک‌لیست از قالب‌های تعریف شده وجود خواهد داشت.)</small>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>آیتم چک‌لیست</th>
                        <th>مسئول پیگیری (نمونه)</th>
                        <th>وضعیت (نمونه)</th>
                        <th>مهلت انجام (نمونه)</th>
                        <th>عملیات (نمونه)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($sample_checklist_items_for_event as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['ItemText']); ?></td>
                        <td><?php echo htmlspecialchars($item['ResponsibleUser']); ?></td>
                        <td>
                            <select class="form-control form-control-sm custom-select">
                                <option <?php if($item['Status'] == 'انجام نشده') echo 'selected'; ?>>انجام نشده</option>
                                <option <?php if($item['Status'] == 'در حال انجام') echo 'selected'; ?>>در حال انجام</option>
                                <option <?php if($item['Status'] == 'انجام شده') echo 'selected'; ?>>انجام شده</option>
                                <option <?php if($item['Status'] == 'نیاز به بازبینی') echo 'selected'; ?>>نیاز به بازبینی</option>
                            </select>
                        </td>
                        <td><?php echo htmlspecialchars($item['DueDate']); ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-success" title="ذخیره وضعیت (نمایشی)">ذخیره</button>
                            <button class="btn btn-sm btn-outline-info" title="افزودن یادداشت (نمایشی)">یادداشت</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="mt-3">
             <button class="btn btn-secondary">اعمال چک‌لیست از قالب (نمایشی)</button>
        </div>
    </div>
</div>
<?php else: ?>
    <div class="alert alert-warning">لطفاً یک رویداد را برای مشاهده یا مدیریت چک‌لیست آن انتخاب کنید.</div>
<?php endif; ?>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
