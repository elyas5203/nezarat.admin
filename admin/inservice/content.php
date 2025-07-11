<?php
// admin/inservice/content.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth

$event_id_for_content = null;
$event_data_content = null;
$event_files_db = [];
$errors_cont = [];

if (!isset($_GET['event_id']) || !is_numeric($_GET['event_id'])) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'شناسه رویداد نامعتبر است.'];
    header("Location: events.php"); exit;
}
$event_id_for_content = (int)$_GET['event_id'];
$csrf_token_content = generate_csrf_token('inservice_content_action_' . $event_id_for_content);

$stmt_event_cont = $conn->prepare("SELECT EventID, EventName, EventDate FROM EventCalendar WHERE EventID = ?");
if ($stmt_event_cont) {
    $stmt_event_cont->bind_param("i", $event_id_for_content); $stmt_event_cont->execute(); $res_evt_cont = $stmt_event_cont->get_result();
    if ($res_evt_cont->num_rows === 1) $event_data_content = $res_evt_cont->fetch_assoc();
    else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'رویداد یافت نشد.']; header("Location: events.php"); exit; }
    $stmt_event_cont->close();
} else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا بارگذاری رویداد: '.$conn->error]; header("Location: events.php"); exit; }

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_content_file'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'inservice_content_action_' . $event_id_for_content)) {
        $errors_cont[] = 'خطای CSRF!';
    } else {
        $file_description = sanitize_input($_POST['file_description'] ?? '');
        if (isset($_FILES['content_file']) && $_FILES['content_file']['error'] == UPLOAD_ERR_OK) {
            $upload_dir_cont = __DIR__ . '/../../../uploads/inservice_content/';
            if (!is_dir($upload_dir_cont)) { if (!mkdir($upload_dir_cont, 0775, true)) $errors_cont[] = 'خطا در ایجاد پوشه آپلود.'; }

            if (empty($errors_cont)) {
                $file_info_cont = pathinfo($_FILES['content_file']['name']);
                $file_extension_cont = strtolower($file_info_cont['extension'] ?? '');
                $allowed_extensions_cont = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar', 'mp3', 'wav', 'mp4', 'mov', 'avi', 'mkv'];
                $max_file_size_cont = 50 * 1024 * 1024; // 50 MB

                if (!in_array($file_extension_cont, $allowed_extensions_cont)) $errors_cont[] = 'نوع فایل مجاز نیست.';
                elseif ($_FILES['content_file']['size'] > $max_file_size_cont) $errors_cont[] = 'حجم فایل (بیش از 50MB).';
                else {
                    $safe_original_filename_cont = preg_replace('/[^A-Za-z0-9_\-\.ء-ي]/u', '_', basename($_FILES['content_file']['name']));
                    $new_filename_cont = uniqid('inservice_' . $event_id_for_content . '_', true) . '.' . $file_extension_cont; // Ensure extension is preserved
                    $upload_path_cont = $upload_dir_cont . $new_filename_cont;

                    if (move_uploaded_file($_FILES['content_file']['tmp_name'], $upload_path_cont)) {
                        $current_uploader_id = get_current_user_id();
                        $relative_path_cont = 'uploads/inservice_content/' . $new_filename_cont;
                        $file_type_mime_cont = mime_content_type($upload_path_cont) ?: $_FILES['content_file']['type'];
                        $file_size_bytes_cont = $_FILES['content_file']['size'];

                        $stmt_insert_file_db = $conn->prepare("INSERT INTO Files (FileName, FilePath, FileType, FileSize, UploadedByUserID, UploadDate, AssociatedEntityType, AssociatedEntityID, Description) VALUES (?, ?, ?, ?, ?, NOW(), 'inservice_content', ?, ?)");
                        if ($stmt_insert_file_db) {
                            $stmt_insert_file_db->bind_param("sssiisi", $safe_original_filename_cont, $relative_path_cont, $file_type_mime_cont, $file_size_bytes_cont, $current_uploader_id, $event_id_for_content, $file_description);
                            if ($stmt_insert_file_db->execute()) {
                                $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'فایل آپلود شد.'];
                            } else { $errors_cont[] = "خطا ذخیره اطلاعات فایل: " . $stmt_insert_file_db->error; }
                            $stmt_insert_file_db->close();
                        } else { $errors_cont[] = "خطا آماده سازی کوئری فایل: " . $conn->error; }
                    } else { $errors_cont[] = 'خطا در آپلود فایل.'; }
                }
            }
        } elseif(!isset($_FILES['content_file']) || $_FILES['content_file']['error'] != UPLOAD_ERR_NO_FILE) {
             $errors_cont[] = 'فایلی انتخاب نشده یا خطایی در آپلود (کد: '.($_FILES['content_file']['error'] ?? 'N/A').')';
        } else { $errors_cont[] = 'فایلی برای آپلود انتخاب نشده.'; }
    }
    $csrf_token_content = regenerate_csrf_token('inservice_content_action_' . $event_id_for_content);
    if(empty($errors_cont) && isset($_SESSION['flash_message']) && $_SESSION['flash_message']['type'] == 'success') { header("Location: content.php?event_id=" . $event_id_for_content); exit; }
}

if (isset($_GET['delete_file_id']) && is_numeric($_GET['delete_file_id'])) {
    if (!isset($_GET['csrf_token']) || !verify_csrf_token($_GET['csrf_token'], 'inservice_content_action_' . $event_id_for_content)) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطای CSRF!'];
    } else {
        $file_id_to_delete = (int)$_GET['delete_file_id'];
        $stmt_get_file = $conn->prepare("SELECT FilePath FROM Files WHERE FileID = ? AND AssociatedEntityID = ? AND AssociatedEntityType = 'inservice_content'");
        if($stmt_get_file){
            $stmt_get_file->bind_param("ii", $file_id_to_delete, $event_id_for_content); $stmt_get_file->execute(); $res_file_path = $stmt_get_file->get_result();
            if($file_to_del_data = $res_file_path->fetch_assoc()){
                $file_path_on_server = __DIR__ . '/../../../' . ltrim($file_to_del_data['FilePath'], '/');
                $stmt_delete_file_db = $conn->prepare("DELETE FROM Files WHERE FileID = ?");
                if ($stmt_delete_file_db) {
                    $stmt_delete_file_db->bind_param("i", $file_id_to_delete);
                    if ($stmt_delete_file_db->execute() && $stmt_delete_file_db->affected_rows > 0) {
                        if (file_exists($file_path_on_server) && is_writable($file_path_on_server)) { unlink($file_path_on_server); }
                        $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'فایل حذف شد.'];
                    } else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا حذف از دیتابیس: ' . $stmt_delete_file_db->error]; }
                    $stmt_delete_file_db->close();
                } else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا آماده سازی حذف: ' . $conn->error]; }
            } else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'فایل یافت نشد.']; }
            $stmt_get_file->close();
        } else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا یافتن فایل: ' . $conn->error]; }
    }
    $csrf_token_content = regenerate_csrf_token('inservice_content_action_' . $event_id_for_content);
    header("Location: content.php?event_id=" . $event_id_for_content); exit;
}

$stmt_fetch_files = $conn->prepare("SELECT FileID, FileName, FilePath, FileType, FileSize, UploadDate, Description FROM Files WHERE AssociatedEntityID = ? AND AssociatedEntityType = 'inservice_content' ORDER BY UploadDate DESC");
if ($stmt_fetch_files) { $stmt_fetch_files->bind_param("i", $event_id_for_content); $stmt_fetch_files->execute(); $res_files_db = $stmt_fetch_files->get_result(); while ($file_db_row = $res_files_db->fetch_assoc()) $event_files_db[] = $file_db_row; $stmt_fetch_files->close(); }
?>
<div class="page-header"><h1>محتوای جلسه: <?php echo htmlspecialchars($event_data_content['EventName'] ?? '...'); ?></h1><p class="page-subtitle">تاریخ: <?php echo to_jalali($event_data_content['EventDate'] ?? '', 'yyyy/MM/dd HH:mm'); ?></p><div class="page-header-actions"><a href="events.php" class="btn btn-secondary">بازگشت به رویدادها</a></div></div>

<?php if (isset($_SESSION['flash_message'])) { /* ... Flash ... */ } ?>
<?php if (!empty($errors_cont)): ?> <div class="alert alert-danger"><ul><?php foreach ($errors_cont as $err_cont_item_msg): ?><li><?php echo htmlspecialchars($err_cont_item_msg); ?></li><?php endforeach; ?></ul></div> <?php endif; ?>

<div class="card shadow-sm mb-4"><div class="card-header"><h5 class="mb-0 card-title-text">آپلود فایل محتوای جدید</h5></div>
<div class="card-body">
    <form action="content.php?event_id=<?php echo $event_id_for_content; ?>" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_content; ?>">
        <div class="form-group"><label for="content_file_upload">انتخاب فایل <span class="text-danger">*</span></label><input type="file" class="form-control-file" id="content_file_upload" name="content_file" required><small class="form-text text-muted">حداکثر 50MB. انواع مجاز: تصاویر، PDF، اسناد، ویدیو، صوت، فشرده.</small></div>
        <div class="form-group"><label for="file_description_upload">توضیحات (اختیاری)</label><input type="text" class="form-control" id="file_description_upload" name="file_description" placeholder="مثلاً: اسلایدهای ارائه"></div>
        <button type="submit" name="submit_content_file" class="btn btn-primary">آپلود</button>
    </form></div></div>

<div class="card shadow-sm"><div class="card-header"><h5 class="mb-0 card-title-text">فایل‌های آپلود شده</h5></div>
<div class="card-body">
    <?php if(!empty($event_files_db)): ?><div class="table-responsive"><table class="table table-sm table-hover">
        <thead><tr><th>#</th><th>نام فایل</th><th>نوع</th><th>حجم</th><th>تاریخ</th><th>توضیحات</th><th>عملیات</th></tr></thead><tbody>
        <?php $file_row_num_idx = 1; foreach($event_files_db as $file_item_idx): ?><tr>
            <td><?php echo $file_row_num_idx++; ?></td>
            <td><a href="/my_site/<?php echo htmlspecialchars(ltrim($file_item_idx['FilePath'],'/')); ?>" target="_blank" download="<?php echo htmlspecialchars($file_item_idx['FileName']); ?>"><?php echo htmlspecialchars($file_item_idx['FileName']); ?></a></td>
            <td><small><?php echo htmlspecialchars($file_item_idx['FileType']); ?></small></td>
            <td><small><?php echo round($file_item_idx['FileSize'] / (1024*1024), 2); ?> MB</small></td>
            <td><small><?php echo to_jalali($file_item_idx['UploadDate'], 'yyyy/MM/dd HH:mm'); ?></small></td>
            <td class="small"><?php echo htmlspecialchars($file_item_idx['Description'] ?? '-'); ?></td>
            <td class="actions-cell">
                <a href="content.php?event_id=<?php echo $event_id_for_content; ?>&delete_file_id=<?php echo $file_item_idx['FileID']; ?>&csrf_token=<?php echo $csrf_token_content; ?>" class="btn btn-xs btn-danger" title="حذف" onclick="return confirm('آیا از حذف این فایل مطمئن هستید؟');"><svg class="icon" width="12" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></a>
                <!-- TODO: Share button/modal for setting access permissions -->
            </td></tr><?php endforeach; ?></tbody></table></div>
    <?php else: ?><p class="text-muted">فایلی آپلود نشده.</p><?php endif; ?>
</div></div>
<script> /* Alert dismissal JS ... */</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
