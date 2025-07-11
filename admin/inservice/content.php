<?php
// admin/inservice/content.php - Manage Content for In-Service Events
require_once __DIR__ . '/../includes/header.php';

// Placeholder data
$sample_inservice_events_for_content = [
    ['EventID' => 1, 'EventName' => 'کارگاه خلاقیت در تدریس - ۱۴۰۳/۰۲/۱۵'],
    ['EventID' => 2, 'EventName' => 'جلسه هم‌اندیشی ماهانه - ۱۴۰۳/۰۳/۰۱'],
];

$sample_event_content = [
    1 => [ // Content for EventID 1
        ['ContentID' => 101, 'FileName' => 'جزوه_خلاقیت_بخش_اول.pdf', 'UploadDate' => '۱۴۰۳/۰۲/۱۴', 'UploadedBy' => 'ادمین سیستم', 'Description' => 'اسلایدهای ارائه شده در بخش اول کارگاه', 'AccessLevel' => 'عمومی', 'FileSize' => '2.5 MB'],
        ['ContentID' => 102, 'FileName' => 'ویدیو_جلسه_پرسش_پاسخ.mp4', 'UploadDate' => '۱۴۰۳/۰۲/۱۶', 'UploadedBy' => 'ادمین سیستم', 'Description' => 'ضبط شده از بخش پرسش و پاسخ کارگاه', 'AccessLevel' => 'فقط حاضرین', 'FileSize' => '150 MB'],
    ],
    2 => [ // Content for EventID 2
        ['ContentID' => 201, 'FileName' => 'خلاصه_نکات_جلسه_هم_اندیشی.docx', 'UploadDate' => '۱۴۰۳/۰۳/۰۲', 'UploadedBy' => 'ادمین سیستم', 'Description' => 'مهمترین نکات مطرح شده در جلسه', 'AccessLevel' => 'با درخواست', 'FileSize' => '0.8 MB'],
    ],
];

$selected_event_id_content = isset($_GET['event_id_content']) ? (int)$_GET['event_id_content'] : null;
$feedback_message_content = ''; // Specific feedback variable

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_content'])) {
    // CSRF check would go here
    $event_id_posted_content = $_POST['event_id_posted_content'] ?? null;
    $description_content = sanitize_input($_POST['description_content'] ?? '');
    $access_level_content = sanitize_input($_POST['access_level_content'] ?? 'public');

    if (!$event_id_posted_content) {
        $feedback_message_content = "<div class='alert alert-danger'>لطفاً ابتدا یک رویداد را از لیست بالا انتخاب کنید.</div>";
    } elseif (isset($_FILES['content_file']) && $_FILES['content_file']['error'] == 0 ) {
        $file_name_original = sanitize_input($_FILES['content_file']['name']);
        // In a real app:
        // 1. Validate file type, size against defined limits.
        // 2. Generate a unique name for the file to prevent overwrites and for security.
        // 3. Move the uploaded file to a secure, non-web-accessible directory or a designated uploads folder.
        //    $target_dir = __DIR__ . "/../../uploads/inservice_content/"; // Example path
        //    if(!is_dir($target_dir)) { mkdir($target_dir, 0755, true); }
        //    $file_extension = pathinfo($file_name_original, PATHINFO_EXTENSION);
        //    $new_file_name = uniqid('inservice_', true) . '.' . $file_extension;
        //    $target_file = $target_dir . $new_file_name;
        //    if (move_uploaded_file($_FILES['content_file']['tmp_name'], $target_file)) {
        //        // Save $new_file_name (stored name), $file_name_original (display name), $description_content, $access_level_content to DB for $event_id_posted_content
        //        $feedback_message_content = "<div class='alert alert-success'>فایل \"".htmlspecialchars($file_name_original)."\" با موفقیت آپلود شد. (نمایشی)</div>";
        //    } else {
        //        $feedback_message_content = "<div class='alert alert-danger'>خطا در هنگام جابجایی فایل آپلود شده.</div>";
        //    }
        $feedback_message_content = "<div class='alert alert-success'>فایل \"".htmlspecialchars($file_name_original)."\" (نمونه) با موفقیت آپلود شد. (ذخیره‌سازی واقعی انجام نشد)</div>";

    } else {
        $feedback_message_content = "<div class='alert alert-danger'>خطا در آپلود فایل. لطفاً فایل معتبری انتخاب کنید. (کد خطا: ".($_FILES['content_file']['error'] ?? 'N/A').")</div>";
    }
}
?>
<div class="page-header">
    <h1>مدیریت محتوای جلسات ضمن خدمت</h1>
    <p class="page-subtitle">آپلود، مشاهده و مدیریت فایل‌های مرتبط با هر رویداد ضمن خدمت.</p>
</div>

<?php echo $feedback_message_content; ?>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="mb-0">انتخاب رویداد برای مدیریت محتوا</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="content.php" class="form-inline">
            <div class="form-group mr-sm-2 mb-2">
                <label for="event_id_content_select" class="mr-2">رویداد:</label>
                <select name="event_id_content" id="event_id_content_select" class="form-control custom-select" onchange="this.form.submit()">
                    <option value="">-- یک رویداد انتخاب کنید --</option>
                    <?php foreach($sample_inservice_events_for_content as $event): ?>
                        <option value="<?php echo $event['EventID']; ?>" <?php if($selected_event_id_content == $event['EventID']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($event['EventName']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($selected_event_id_content): ?>
                 <a href="content.php" class="btn btn-outline-secondary mb-2 ml-2">پاک کردن انتخاب</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if ($selected_event_id_content):
    $current_event_name_cont = "رویداد انتخاب شده";
    foreach($sample_inservice_events_for_content as $ev) {
        if($ev['EventID'] == $selected_event_id_content) {
            $current_event_name_cont = $ev['EventName'];
            break;
        }
    }
?>
<div class="row">
    <div class="col-lg-5 col-md-12 mb-4">
        <div class="card shadow-sm">
            <div class="card-header"><h5 class="mb-0">آپلود محتوای جدید برای: <?php echo htmlspecialchars($current_event_name_cont); ?></h5></div>
            <div class="card-body">
                <form method="POST" action="content.php?event_id_content=<?php echo $selected_event_id_content; ?>" enctype="multipart/form-data">
                    <input type="hidden" name="event_id_posted_content" value="<?php echo $selected_event_id_content; ?>">
                    <div class="form-group">
                        <label for="content_file_input">انتخاب فایل:<span class="text-danger">*</span></label>
                        <input type="file" class="form-control-file" id="content_file_input" name="content_file" required>
                        <small class="form-text text-muted">فایل‌های مجاز: PDF, DOCX, MP4, MP3, ZIP. حداکثر حجم: ۲۰ مگابایت (نمونه)</small>
                    </div>
                    <div class="form-group">
                        <label for="description_content_input">توضیحات فایل:</label>
                        <textarea class="form-control" name="description_content" id="description_content_input" rows="2" placeholder="توضیح مختصری درباره محتوای فایل"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="access_level_content_select">سطح دسترسی:</label>
                        <select name="access_level_content" id="access_level_content_select" class="form-control custom-select">
                            <option value="public">عمومی (قابل مشاهده برای همه مدرسین)</option>
                            <option value="attendees_only">فقط حاضرین در جلسه</option>
                            <option value="on_request">با درخواست (نیاز به تایید)</option>
                        </select>
                    </div>
                    <button type="submit" name="upload_content" class="btn btn-primary">آپلود محتوا (نمایشی)</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7 col-md-12 mb-4">
        <div class="card shadow-sm">
            <div class="card-header"><h5 class="mb-0">محتوای موجود برای: <?php echo htmlspecialchars($current_event_name_cont); ?></h5></div>
            <div class="card-body">
                <?php $current_event_files = $sample_event_content[$selected_event_id_content] ?? []; ?>
                <?php if(empty($current_event_files)): ?>
                    <p class="text-muted text-center py-3">هنوز محتوایی برای این رویداد بارگذاری نشده است.</p>
                <?php else: ?>
                <div class="list-group">
                    <?php foreach($current_event_files as $file_item): ?>
                    <div class="list-group-item list-group-item-action flex-column align-items-start mb-2">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1 font-weight-bold text-primary"><?php echo htmlspecialchars($file_item['FileName']); ?></h6>
                            <small class="text-muted"><?php echo htmlspecialchars($file_item['UploadDate']); ?></small>
                        </div>
                        <p class="mb-1 small"><?php echo nl2br(htmlspecialchars($file_item['Description'])); ?></p>
                        <small class="text-muted">توسط: <?php echo htmlspecialchars($file_item['UploadedBy']); ?> | دسترسی: <?php echo htmlspecialchars($file_item['AccessLevel']); ?> | حجم: <?php echo htmlspecialchars($file_item['FileSize']);?></small>
                        <div class="mt-2">
                            <a href="#" class="btn btn-sm btn-success disabled" title="دانلود (نمایشی)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-download" viewBox="0 0 16 16" style="vertical-align: -1px;"><path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/><path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/></svg>
                                دانلود
                            </a>
                            <a href="#" class="btn btn-sm btn-danger disabled ml-1" title="حذف (نمایشی)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-trash3" viewBox="0 0 16 16" style="vertical-align: -1px;"><path d="M6.5 1h3a.5.5 0 0 1 .5.5v1H6v-1a.5.5 0 0 1 .5-.5ZM11 2.5v-1A1.5 1.5 0 0 0 9.5 0h-3A1.5 1.5 0 0 0 5 1.5v1H2.506a.58.58 0 0 0-.01 0H1.5a.5.5 0 0 0 0 1h.538l.853 10.66A2 2 0 0 0 4.885 16h6.23a2 2 0 0 0 1.994-1.84l.853-10.66h.538a.5.5 0 0 0 0-1h-.995a.59.59 0 0 0-.01 0H11Zm1.958 1-.846 10.58a1 1 0 0 1-.997.92h-6.23a1 1 0 0 1-.997-.92L3.042 3.5h9.916Zm-7.487 1a.5.5 0 0 1 .528.47l.5 8.5a.5.5 0 0 1-.998.06L5 5.03a.5.5 0 0 1 .47-.53Zm5.058 0a.5.5 0 0 1 .47.53l-.5 8.5a.5.5 0 1 1-.998-.06l.5-8.5a.5.5 0 0 1 .528-.47ZM8 4.5a.5.5 0 0 1 .5.5v8.5a.5.5 0 0 1-1 0V5a.5.5 0 0 1 .5-.5Z"/></svg>
                                حذف
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
    <div class="alert alert-warning">لطفاً یک رویداد را برای مدیریت محتوای آن انتخاب کنید.</div>
<?php endif; ?>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
