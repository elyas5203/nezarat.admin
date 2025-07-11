<?php
// admin/parvareshi/class_services.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth

$csrf_token_class_services = generate_csrf_token('parvareshi_class_services_action');

$errors_cs = [];
$success_message_cs = ''; // Using flash messages now

$edit_mode_cs = false;
$service_log_to_edit = [
    'ServiceLogID' => null, 'ClassID' => '', 'EventName' => '', 'CustomEventName' => '',
    'ServiceDescription' => '', 'ServiceDate' => '', 'Location' => '',
    'Status' => 'planned', 'PhotosFileID' => null
];


$occasions = ['ghadir' => 'غدیر', 'nime_shaban' => 'نیمه شعبان', 'muharram_shahadat' => 'محرم و شهادت‌ها', 'dahe_fajr' => 'دهه فجر', 'other' => 'سایر مناسبت‌ها'];
$service_statuses = ['planned' => 'برنامه‌ریزی شده', 'in_progress' => 'در دست اقدام', 'completed' => 'انجام شده', 'needs_support' => 'نیاز به پشتیبانی ویژه'];
$status_badge_cs = ['planned' => 'info', 'in_progress' => 'warning', 'completed' => 'success', 'needs_support' => 'primary'];


$classes_q_cs = $conn->query("SELECT ClassID, ClassName, AcademicYear FROM Classes WHERE IsActive = TRUE ORDER BY AcademicYear DESC, ClassName");
$available_classes_cs = [];
if ($classes_q_cs) { while($c_cs = $classes_q_cs->fetch_assoc()) $available_classes_cs[$c_cs['ClassID']] = $c_cs; $classes_q_cs->close(); }

// Handle Edit Request (GET) - Load data into $service_log_to_edit
if (isset($_GET['edit_log_id']) && is_numeric($_GET['edit_log_id']) && $_SERVER["REQUEST_METHOD"] != "POST") {
    $edit_log_id_get = (int)$_GET['edit_log_id'];
    $stmt_edit_cs_get = $conn->prepare("SELECT * FROM ClassServices WHERE ServiceLogID = ?");
    if ($stmt_edit_cs_get) {
        $stmt_edit_cs_get->bind_param("i", $edit_log_id_get);
        $stmt_edit_cs_get->execute();
        $result_edit_cs_get = $stmt_edit_cs_get->get_result();
        if ($data_cs_get = $result_edit_cs_get->fetch_assoc()) {
            $service_log_to_edit = $data_cs_get;
            // Check if EventName is one of the predefined keys, if not, it's a custom one
            if (!array_key_exists($data_cs_get['EventName'], $occasions) && !in_array($data_cs_get['EventName'], $occasions)) {
                $service_log_to_edit['CustomEventName'] = $data_cs_get['EventName'];
                $service_log_to_edit['EventName'] = 'other'; // Set select to 'other'
            } else {
                 // Find key for predefined occasion
                $event_key = array_search($data_cs_get['EventName'], $occasions);
                if ($event_key !== false) {
                    $service_log_to_edit['EventName'] = $event_key;
                } else { // If it's a value but not a key (e.g. old data), treat as custom
                     $service_log_to_edit['CustomEventName'] = $data_cs_get['EventName'];
                     $service_log_to_edit['EventName'] = 'other';
                }
            }

            if ($service_log_to_edit['ServiceDate']) {
                try { $dt_obj_cs_edit = new DateTime($service_log_to_edit['ServiceDate']); $service_log_to_edit['ServiceDate'] = $dt_obj_cs_edit->format('Y-m-d'); } catch (Exception $e) { /* Keep original if invalid */ }
            }
            $edit_mode_cs = true;
        } else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "گزارش خدمت‌گزاری برای ویرایش یافت نشد."]; }
        $stmt_edit_cs_get->close();
    } else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "خطا در بارگذاری گزارش: " . $conn->error]; }
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_class_service'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'parvareshi_class_services_action')) {
        $errors_cs[] = 'خطای CSRF!';
    } else {
        $service_log_id = isset($_POST['service_log_id']) && is_numeric($_POST['service_log_id']) ? (int)$_POST['service_log_id'] : null;
        $class_id_cs = isset($_POST['class_id_cs']) ? (int)$_POST['class_id_cs'] : null;
        $event_name_key_cs = sanitize_input($_POST['event_name_cs'] ?? '');
        $custom_event_name_cs = sanitize_input($_POST['custom_event_name_cs'] ?? '');
        $service_description_cs = sanitize_input($_POST['service_description_cs'] ?? '');
        $service_date_cs = sanitize_input($_POST['service_date_cs'] ?? '');
        $location_cs = sanitize_input($_POST['location_cs'] ?? '');
        $status_cs = sanitize_input($_POST['status_cs'] ?? 'planned');
        $submitted_by_user_id_cs = get_current_user_id();
        $photos_file_id_cs = isset($_POST['existing_photos_file_id']) ? (int)$_POST['existing_photos_file_id'] : null;

        // Repopulate for sticky form
        $service_log_to_edit = ['ServiceLogID' => $service_log_id, 'ClassID' => $class_id_cs, 'EventName' => $event_name_key_cs, 'CustomEventName' => $custom_event_name_cs, 'ServiceDescription' => $service_description_cs, 'ServiceDate' => $service_date_cs, 'Location' => $location_cs, 'Status' => $status_cs, 'PhotosFileID' => $photos_file_id_cs];
        $edit_mode_cs = ($service_log_id !== null);

        $final_event_name_cs = ($event_name_key_cs === 'other' && !empty($custom_event_name_cs)) ? $custom_event_name_cs : ($occasions[$event_name_key_cs] ?? $event_name_key_cs);

        if (empty($class_id_cs) || !isset($available_classes_cs[$class_id_cs])) $errors_cs[] = "کلاس نامعتبر.";
        if (empty($event_name_key_cs)) $errors_cs[] = "انتخاب مناسبت الزامی.";
        if ($event_name_key_cs === 'other' && empty($custom_event_name_cs)) $errors_cs[] = "نام مناسبت سفارشی را وارد کنید.";
        if (empty($service_description_cs)) $errors_cs[] = "شرح خدمت‌گزاری الزامی.";
        if (empty($service_date_cs) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $service_date_cs)) $errors_cs[] = "تاریخ اجرا نامعتبر (YYYY-MM-DD).";
        if (!array_key_exists($status_cs, $service_statuses)) $errors_cs[] = "وضعیت نامعتبر.";

        // File upload logic
        if (isset($_FILES['service_photos']) && $_FILES['service_photos']['error'] == UPLOAD_ERR_OK) {
            // ... (Full file upload logic as in other modules, creating entry in Files table, getting $new_file_id)
            // For example:
            // $new_file_id = handle_file_upload($_FILES['service_photos'], 'class_service_photos', $submitted_by_user_id_cs);
            // if ($new_file_id) { $photos_file_id_cs = $new_file_id; } else { $errors_cs[] = "خطا در آپلود عکس."; }
            // For now, placeholder:
            // $errors_cs[] = "آپلود فایل هنوز به طور کامل پیاده سازی نشده است.";
        }

        if (empty($errors_cs)) {
            if ($service_log_id) {
                $stmt_cs_db = $conn->prepare("UPDATE ClassServices SET ClassID=?, EventName=?, ServiceDescription=?, ServiceDate=?, Location=?, Status=?, PhotosFileID=?, SubmittedByUserID=? WHERE ServiceLogID=?");
                if($stmt_cs_db) { $stmt_cs_db->bind_param("isssssiii", $class_id_cs, $final_event_name_cs, $service_description_cs, $service_date_cs, $location_cs, $status_cs, $photos_file_id_cs, $submitted_by_user_id_cs, $service_log_id);
                    if($stmt_cs_db->execute()) $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'گزارش ویرایش شد.'];
                    else $errors_cs[] = "خطا ویرایش: " . $stmt_cs_db->error; $stmt_cs_db->close();
                } else $errors_cs[] = "خطا آماده سازی ویرایش: " . $conn->error;
            } else {
                $stmt_cs_db = $conn->prepare("INSERT INTO ClassServices (ClassID, EventName, ServiceDescription, ServiceDate, Location, Status, PhotosFileID, SubmittedByUserID) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if($stmt_cs_db) { $stmt_cs_db->bind_param("isssssii", $class_id_cs, $final_event_name_cs, $service_description_cs, $service_date_cs, $location_cs, $status_cs, $photos_file_id_cs, $submitted_by_user_id_cs);
                    if($stmt_cs_db->execute()) $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'گزارش ثبت شد.'];
                    else $errors_cs[] = "خطا ثبت: " . $stmt_cs_db->error; $stmt_cs_db->close();
                } else $errors_cs[] = "خطا آماده سازی ثبت: " . $conn->error;
            }
            if(empty($errors_cs)) { regenerate_csrf_token('parvareshi_class_services_action'); header("Location: class_services.php"); exit; }
        }
    }
    $csrf_token_class_services = regenerate_csrf_token('parvareshi_class_services_action');
}

// Handle Delete Request
if (isset($_GET['delete_log_id']) && is_numeric($_GET['delete_log_id'])) {
    if (!isset($_GET['csrf_token']) || !verify_csrf_token($_GET['csrf_token'], 'parvareshi_class_services_action')) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطای CSRF!'];
    } else {
        $delete_log_id_get = (int)$_GET['delete_log_id'];
        // TODO: Delete associated file from server and Files table if PhotosFileID exists
        $stmt_del_cs = $conn->prepare("DELETE FROM ClassServices WHERE ServiceLogID = ?");
        if($stmt_del_cs){ $stmt_del_cs->bind_param("i", $delete_log_id_get);
            if($stmt_del_cs->execute() && $stmt_del_cs->affected_rows > 0) $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'گزارش خدمت‌گزاری حذف شد.'];
            else $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا در حذف گزارش: '.$stmt_del_cs->error];
            $stmt_del_cs->close();
        } else $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا آماده سازی حذف: '.$conn->error];
    }
    regenerate_csrf_token('parvareshi_class_services_action');
    header("Location: class_services.php"); exit;
}


$service_logs_q_main = $conn->query("
    SELECT cs.*, c.ClassName, c.AcademicYear, u.Username AS SubmitterUsername, f.FileName AS PhotoFileName, f.FilePath AS PhotoFilePath
    FROM ClassServices cs
    JOIN Classes c ON cs.ClassID = c.ClassID
    LEFT JOIN Users u ON cs.SubmittedByUserID = u.UserID
    LEFT JOIN Files f ON cs.PhotosFileID = f.FileID
    ORDER BY cs.ServiceDate DESC, c.ClassName ASC LIMIT 50");
?>
<div class="page-header"><h1>مدیریت خدمت‌گزاری کلاس‌ها</h1>
    <div class="page-header-actions"> <a href="rental_items.php" class="btn btn-info">مدیریت کرایه‌چی</a> <a href="events_general.php" class="btn btn-success">مناسبت‌های عمومی</a></div></div>

<?php if (isset($_SESSION['flash_message'])) { /* ... Flash ... */ } ?>
<?php if (!empty($errors_cs)): ?> <div class="alert alert-danger"><ul><?php foreach ($errors_cs as $err_cs_item_msg): ?><li><?php echo htmlspecialchars($err_cs_item_msg); ?></li><?php endforeach; ?></ul></div> <?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-header"><span class="card-title-text"><?php echo $edit_mode_cs ? 'ویرایش گزارش خدمت‌گزاری' : 'ثبت گزارش خدمت‌گزاری جدید'; ?></span></div>
    <div class="card-body">
        <form action="class_services.php<?php if($edit_mode_cs && $service_log_to_edit['ServiceLogID']) echo '?edit_log_id='.$service_log_to_edit['ServiceLogID']; ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_class_services; ?>">
            <?php if($edit_mode_cs && $service_log_to_edit['ServiceLogID']): ?><input type="hidden" name="service_log_id" value="<?php echo $service_log_to_edit['ServiceLogID']; ?>"><?php endif; ?>
            <?php if($edit_mode_cs && $service_log_to_edit['PhotosFileID']): ?><input type="hidden" name="existing_photos_file_id" value="<?php echo $service_log_to_edit['PhotosFileID']; ?>"><?php endif; ?>

            <div class="form-row">
                <div class="form-group col-md-4"><label for="class_id_cs_form">کلاس <span class="text-danger">*</span></label><select name="class_id_cs" id="class_id_cs_form" class="form-control custom-select" required><option value="">-- انتخاب --</option><?php foreach($available_classes_cs as $cid_cs_f => $cdata_cs_f): ?><option value="<?php echo $cid_cs_f; ?>" <?php if($service_log_to_edit['ClassID'] == $cid_cs_f) echo 'selected';?>><?php echo htmlspecialchars($cdata_cs_f['ClassName'] . ' (' . $cdata_cs_f['AcademicYear'] . ')'); ?></option><?php endforeach; ?></select></div>
                <div class="form-group col-md-4"><label for="event_name_cs_form">مناسبت <span class="text-danger">*</span></label><select name="event_name_cs" id="event_name_cs_form" class="form-control custom-select" required onchange="document.getElementById('custom_event_name_cs_group').style.display = (this.value === 'other' ? 'block' : 'none');"><option value="">-- انتخاب --</option><?php foreach($occasions as $okey_f => $oval_f): ?><option value="<?php echo $okey_f; ?>" <?php if($service_log_to_edit['EventName'] == $okey_f || ($service_log_to_edit['EventName'] === 'other' && $service_log_to_edit['CustomEventName'] == $oval_f)) echo 'selected';?>><?php echo $oval_f; ?></option><?php endforeach; ?></select></div>
                <div class="form-group col-md-4" id="custom_event_name_cs_group" style="<?php echo ($service_log_to_edit['EventName'] ?? '') === 'other' ? 'display:block;' : 'display:none;'; ?>"><label for="custom_event_name_cs_input">نام مناسبت سفارشی</label><input type="text" name="custom_event_name_cs" id="custom_event_name_cs_input" class="form-control" value="<?php echo htmlspecialchars($service_log_to_edit['CustomEventName'] ?? '');?>"></div>
            </div>
            <div class="form-group"><label for="service_description_cs_form">شرح <span class="text-danger">*</span></label><textarea name="service_description_cs" id="service_description_cs_form" class="form-control" rows="3" required><?php echo htmlspecialchars($service_log_to_edit['ServiceDescription']);?></textarea></div>
            <div class="form-row">
                <div class="form-group col-md-4"><label for="service_date_cs_form">تاریخ اجرا <span class="text-danger">*</span></label><input type="text" name="service_date_cs" id="service_date_cs_form" class="form-control persian-date-picker" placeholder="YYYY-MM-DD" value="<?php echo htmlspecialchars($service_log_to_edit['ServiceDate']);?>" required></div>
                <div class="form-group col-md-4"><label for="location_cs_form">مکان</label><input type="text" name="location_cs" id="location_cs_form" class="form-control" value="<?php echo htmlspecialchars($service_log_to_edit['Location']);?>"></div>
                <div class="form-group col-md-4"><label for="status_cs_form">وضعیت <span class="text-danger">*</span></label><select name="status_cs" id="status_cs_form" class="form-control custom-select" required><?php foreach($service_statuses as $skey_f => $sval_f):?><option value="<?php echo $skey_f;?>" <?php if($service_log_to_edit['Status'] == $skey_f) echo 'selected';?>><?php echo $sval_f;?></option><?php endforeach;?></select></div>
            </div>
            <div class="form-group"><label for="service_photos_upload">آپلود عکس</label><input type="file" name="service_photos" id="service_photos_upload" class="form-control-file">
            <?php if($edit_mode_cs && $service_log_to_edit['PhotosFileID'] && isset($service_log_to_edit['PhotoFilePath'])): ?>
                <small class="form-text text-muted">فایل فعلی: <a href="/my_site/<?php echo htmlspecialchars(ltrim($service_log_to_edit['PhotoFilePath'],'/')); ?>" target="_blank"><?php echo htmlspecialchars($service_log_to_edit['PhotoFileName'] ?? 'مشاهده فایل');?></a>. برای جایگزینی، فایل جدید انتخاب کنید.</small>
            <?php endif; ?>
            </div>
            <button type="submit" name="submit_class_service" class="btn btn-primary"><?php echo $edit_mode_cs ? 'ذخیره تغییرات' : 'ثبت گزارش'; ?></button>
             <?php if ($edit_mode_cs): ?><a href="class_services.php" class="btn btn-outline-secondary">لغو ویرایش</a><?php endif; ?>
        </form>
    </div>
</div>

<div class="card shadow-sm"><div class="card-header"><span class="card-title-text">لیست گزارش‌های خدمت‌گزاری (۵۰ اخیر)</span></div>
<div class="card-body">
    <?php if($service_logs_q_main && $service_logs_q_main->num_rows > 0): ?><div class="table-responsive"><table class="table table-sm table-striped table-hover">
    <thead><tr><th>#</th><th>کلاس</th><th>مناسبت</th><th>تاریخ</th><th>وضعیت</th><th>ثبت کننده</th><th>عکس</th><th>عملیات</th></tr></thead><tbody>
    <?php $log_row_idx = 1; while($log_item = $service_logs_q_main->fetch_assoc()): ?>
    <tr><td><?php echo $log_row_idx++; ?></td><td><?php echo htmlspecialchars($log_item['ClassName']);?> <small>(<?php echo htmlspecialchars($log_item['AcademicYear']);?>)</small></td><td><?php echo htmlspecialchars($log_item['EventName']);?></td><td><?php echo to_jalali($log_item['ServiceDate'],'yyyy/MM/dd');?></td><td><span class="badge badge-<?php echo $status_badge_cs[$log_item['Status']] ?? 'light';?> p-1"><?php echo $service_statuses[$log_item['Status']] ?? $log_item['Status'];?></span></td><td><small><?php echo htmlspecialchars($log_item['SubmitterUsername'] ?? '-');?></small></td>
    <td><?php if($log_item['PhotosFileID'] && $log_item['PhotoFilePath']):?><a href="/my_site/<?php echo htmlspecialchars(ltrim($log_item['PhotoFilePath'],'/'));?>" target="_blank" class="btn btn-xs btn-outline-success py-0 px-1">نمایش</a><?php else: echo '-'; endif;?></td>
    <td class="actions-cell"> <a href="class_services.php?edit_log_id=<?php echo $log_item['ServiceLogID'];?>" class="btn btn-xs btn-warning" title="ویرایش"><svg class="icon" width="12" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a>
    <a href="class_services.php?delete_log_id=<?php echo $log_item['ServiceLogID'];?>&csrf_token=<?php echo $csrf_token_class_services; ?>" class="btn btn-xs btn-danger" title="حذف" onclick="return confirm('آیا از حذف این گزارش مطمئن هستید؟');"><svg class="icon" width="12" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></a>
    </td></tr>
    <?php endwhile; ?></tbody></table></div>
    <?php else: ?><p class="text-muted">هنوز گزارشی ثبت نشده.</p><?php endif; if($service_logs_q_main) $service_logs_q_main->close(); ?>
</div></div>
<link rel="stylesheet" href="https://unpkg.com/persian-datepicker@latest/dist/css/persian-datepicker.min.css"/>
<script src="https://unpkg.com/persian-datepicker@latest/dist/js/persian-datepicker.min.js"></script>
<script> /* Datepicker init, alert dismissal ... */
document.addEventListener('DOMContentLoaded', function() { document.querySelectorAll(".persian-date-picker").forEach(function(el){ new persianDatepicker(el, { format: 'YYYY-MM-DD', autoClose: true, observer: true, calendar:{ persian: { locale: 'fa' } } });});});
</script>
<style>.badge.p-1{padding:0.25em 0.4em !important; font-size:0.8em !important;}</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
