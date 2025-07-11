<?php
// user/parvareshi/my_class_services.php
require_once __DIR__ . '/../includes/header.php';

$teacher_user_id_pcs_user = get_current_user_id();
if (!$teacher_user_id_pcs_user || get_current_user_type() !== 'teacher') {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'این بخش مخصوص مدرسین است.'];
    header("Location: " . ($user_base_url ?? '/my_site/user') . "/dashboard/index.php");
    exit;
}

$csrf_token_pcs_user = generate_csrf_token('user_parvareshi_class_services_action');
$errors_pcs_user = [];
$edit_mode_pcs_user = false;
$service_to_edit_pcs_user_default = [
    'ServiceLogID' => null, 'ClassID' => '', 'EventNameKey' => '', 'CustomEventName' => '',
    'ServiceDescription' => '', 'ServiceDate' => date('Y-m-d'), 'Location' => '',
    'Status' => 'planned', 'PhotosFileID' => null, 'PhotoFilePath' => null, 'PhotoFileName' => null,
    'NeedsSupport' => 0 // Added NeedsSupport field
];
$service_to_edit_pcs_user = $service_to_edit_pcs_user_default;

// Fetch classes taught by this teacher
$my_classes_pcs_user_q = $conn->prepare("SELECT ClassID, ClassName, AcademicYear FROM Classes WHERE TeacherUserID = ? AND IsActive = TRUE ORDER BY AcademicYear DESC, ClassName");
$my_available_classes_pcs_user = [];
if($my_classes_pcs_user_q){ $my_classes_pcs_user_q->bind_param("i", $teacher_user_id_pcs_user); $my_classes_pcs_user_q->execute(); $res_mycls_user = $my_classes_pcs_user_q->get_result();
    while($c_pcs_user = $res_mycls_user->fetch_assoc()) $my_available_classes_pcs_user[$c_pcs_user['ClassID']] = $c_pcs_user; $my_classes_pcs_user_q->close();
}

$occasions_pcs_user = ['ghadir' => 'غدیر', 'nime_shaban' => 'نیمه شعبان', 'muharram_shahadat' => 'محرم و شهادت‌ها', 'dahe_fajr' => 'دهه فجر', 'other' => 'سایر مناسبت‌ها'];
$service_statuses_pcs_user = ['planned' => 'برنامه‌ریزی شده', 'completed' => 'انجام شده', 'needs_support_requested' => 'درخواست پشتیبانی'];
$status_badge_cs_user_page = ['planned' => 'info', 'completed' => 'success', 'needs_support_requested' => 'primary'];

// Handle Edit Request (GET)
if (isset($_GET['edit_log_id']) && is_numeric($_GET['edit_log_id']) && $_SERVER["REQUEST_METHOD"] != "POST") {
    $edit_log_id_get_user_val = (int)$_GET['edit_log_id'];
    $stmt_edit_cs_user_val = $conn->prepare("
        SELECT cs.*, f.FilePath as PhotoFilePath, f.FileName as PhotoFileName
        FROM ClassServices cs
        JOIN Classes c ON cs.ClassID = c.ClassID
        LEFT JOIN Files f ON cs.PhotosFileID = f.FileID
        WHERE cs.ServiceLogID = ? AND c.TeacherUserID = ?");
    if ($stmt_edit_cs_user_val) {
        $stmt_edit_cs_user_val->bind_param("ii", $edit_log_id_get_user_val, $teacher_user_id_pcs_user);
        $stmt_edit_cs_user_val->execute();
        $result_edit_cs_user_val = $stmt_edit_cs_user_val->get_result();
        if ($data_cs_user_val = $result_edit_cs_user_val->fetch_assoc()) {
            $service_to_edit_pcs_user = $data_cs_user_val;
            $event_name_from_db_user = $data_cs_user_val['EventName'];
            $found_key_user = array_search($event_name_from_db_user, $occasions_pcs_user);
            if ($found_key_user !== false) {
                $service_to_edit_pcs_user['EventNameKey'] = $found_key_user;
                $service_to_edit_pcs_user['CustomEventName'] = '';
            } else {
                $service_to_edit_pcs_user['EventNameKey'] = 'other';
                $service_to_edit_pcs_user['CustomEventName'] = $event_name_from_db_user;
            }
            if ($service_to_edit_pcs_user['ServiceDate']) { try { $dt_obj_cs_edit_user_val = new DateTime($service_to_edit_pcs_user['ServiceDate']); $service_to_edit_pcs_user['ServiceDate'] = $dt_obj_cs_edit_user_val->format('Y-m-d'); } catch (Exception $e) {} }
            $edit_mode_pcs_user = true;
        } else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "گزارش یافت نشد یا اجازه ویرایش ندارید."]; }
        $stmt_edit_cs_user_val->close();
    } else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "خطا بارگذاری گزارش: " . $conn->error]; }
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_my_class_service'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'user_parvareshi_class_services_action')) {
        $errors_pcs_user[] = 'خطای CSRF!';
    } else {
        $service_log_id_pcs_post_val = isset($_POST['service_log_id']) && is_numeric($_POST['service_log_id']) ? (int)$_POST['service_log_id'] : null;
        $class_id_pcs_post_val = isset($_POST['class_id_cs_user']) ? (int)$_POST['class_id_cs_user'] : null;
        $event_name_key_pcs_post_val = sanitize_input($_POST['event_name_key_cs_user'] ?? '');
        $custom_event_name_pcs_post_val = sanitize_input($_POST['custom_event_name_cs_user'] ?? '');
        $service_description_pcs_post_val = sanitize_input($_POST['service_description_cs_user'] ?? '');
        $service_date_pcs_post_val = sanitize_input($_POST['service_date_cs_user'] ?? '');
        $location_pcs_post_val = sanitize_input($_POST['location_cs_user'] ?? '');
        $status_pcs_post_val = sanitize_input($_POST['status_cs_user'] ?? 'planned');
        $needs_support_pcs_post_val = isset($_POST['needs_support_cs_user']) ? 1 : 0;
        $photos_file_id_pcs_post_val = isset($_POST['existing_photos_file_id']) ? (int)$_POST['existing_photos_file_id'] : null;

        $service_to_edit_pcs_user = ['ServiceLogID' => $service_log_id_pcs_post_val, 'ClassID' => $class_id_pcs_post_val, 'EventNameKey' => $event_name_key_pcs_post_val, 'CustomEventName' => $custom_event_name_pcs_post_val, 'ServiceDescription' => $service_description_pcs_post_val, 'ServiceDate' => $service_date_pcs_post_val, 'Location' => $location_pcs_post_val, 'Status' => $status_pcs_post_val, 'PhotosFileID' => $photos_file_id_pcs_post_val, 'NeedsSupport' => $needs_support_pcs_post_val];
        $edit_mode_pcs_user = ($service_log_id_pcs_post_val !== null);

        $final_event_name_pcs_post_val = ($event_name_key_pcs_post_val === 'other' && !empty($custom_event_name_pcs_post_val)) ? $custom_event_name_pcs_post_val : ($occasions_pcs_user[$event_name_key_pcs_post_val] ?? $event_name_key_pcs_post_val);

        if (empty($class_id_pcs_post_val) || !isset($my_available_classes_pcs_user[$class_id_pcs_post_val])) $errors_pcs_user[] = "کلاس نامعتبر.";
        // ... (Other validations similar to admin side) ...

        // File upload
        if (isset($_FILES['service_photos_user']) && $_FILES['service_photos_user']['error'] == UPLOAD_ERR_OK) {
            // ... (Full file upload logic here, similar to admin/inservice/content.php)
            // On success: $photos_file_id_pcs_post_val = $new_file_id_from_upload;
            // If updating and new file uploaded, delete old file from server and Files table if $service_to_edit_pcs_user['PhotosFileID'] was set.
        }

        if (empty($errors_pcs_user)) {
            if ($service_log_id_pcs_post_val) {
                $stmt_db_pcs_user = $conn->prepare("UPDATE ClassServices SET ClassID=?, EventName=?, ServiceDescription=?, ServiceDate=?, Location=?, Status=?, PhotosFileID=?, NeedsSupport=? WHERE ServiceLogID=? AND SubmittedByUserID=?");
                if($stmt_db_pcs_user) { $stmt_db_pcs_user->bind_param("isssssiiii", $class_id_pcs_post_val, $final_event_name_pcs_post_val, $service_description_pcs_post_val, $service_date_pcs_post_val, $location_pcs_post_val, $status_pcs_post_val, $photos_file_id_pcs_post_val, $needs_support_pcs_post_val, $service_log_id_pcs_post_val, $teacher_user_id_pcs_user);
                    if($stmt_db_pcs_user->execute()) $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'گزارش ویرایش شد.']; else $errors_pcs_user[] = "خطا ویرایش: " . $stmt_db_pcs_user->error; $stmt_db_pcs_user->close();
                } else $errors_pcs_user[] = "خطا آماده سازی ویرایش: " . $conn->error;
            } else {
                $stmt_db_pcs_user = $conn->prepare("INSERT INTO ClassServices (ClassID, EventName, ServiceDescription, ServiceDate, Location, Status, PhotosFileID, SubmittedByUserID, NeedsSupport) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if($stmt_db_pcs_user) { $stmt_db_pcs_user->bind_param("isssssiii", $class_id_pcs_post_val, $final_event_name_pcs_post_val, $service_description_pcs_post_val, $service_date_pcs_post_val, $location_pcs_post_val, $status_pcs_post_val, $photos_file_id_pcs_post_val, $teacher_user_id_pcs_user, $needs_support_pcs_post_val);
                    if($stmt_db_pcs_user->execute()) $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'گزارش ثبت شد.']; else $errors_pcs_user[] = "خطا ثبت: " . $stmt_db_pcs_user->error; $stmt_db_pcs_user->close();
                } else $errors_pcs_user[] = "خطا آماده سازی ثبت: " . $conn->error;
            }
            if(empty($errors_pcs_user)) { regenerate_csrf_token('user_parvareshi_class_services_action'); header("Location: my_class_services.php"); exit; }
        }
    }
    $csrf_token_pcs_user = regenerate_csrf_token('user_parvareshi_class_services_action');
}

// ... (Handle Delete Request for user's own logs) ...

$my_service_logs_q_user_list = $conn->prepare("SELECT cs.*, c.ClassName, c.AcademicYear, f.FileName AS PhotoFileName, f.FilePath AS PhotoFilePath FROM ClassServices cs JOIN Classes c ON cs.ClassID = c.ClassID LEFT JOIN Files f ON cs.PhotosFileID = f.FileID WHERE c.TeacherUserID = ? ORDER BY cs.ServiceDate DESC");
$my_service_logs_user_list = [];
if($my_service_logs_q_user_list){ $my_service_logs_q_user_list->bind_param("i", $teacher_user_id_pcs_user); $my_service_logs_q_user_list->execute(); $res_mylogs_user = $my_service_logs_q_user_list->get_result(); while($log_pcs_item_user = $res_mylogs_user->fetch_assoc()) $my_service_logs_user_list[] = $log_pcs_item_user; $my_service_logs_q_user_list->close();}
?>
<div class="page-header"><h1>خدمت‌گزاری کلاس‌های من</h1><p class="page-subtitle">ثبت و مدیریت گزارش فعالیت‌های خدمت‌گزاری.</p></div>
<?php if (isset($_SESSION['flash_message'])) { /* Flash */ } ?> <?php if (!empty($errors_pcs_user)): /* Errors */ endif; ?>

<div class="card shadow-sm mb-4"><div class="card-header"><span class="card-title-text"><?php echo $edit_mode_pcs_user ? 'ویرایش گزارش' : 'ثبت گزارش جدید'; ?></span></div>
<div class="card-body"> <?php if (empty($my_available_classes_pcs_user)): ?> <div class="alert alert-warning">کلاسی به شما تخصیص داده نشده.</div> <?php else: ?>
<form action="my_class_services.php<?php if($edit_mode_pcs_user && $service_to_edit_pcs_user['ServiceLogID']) echo '?edit_log_id='.$service_to_edit_pcs_user['ServiceLogID'];?>" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_pcs_user; ?>">
    <?php if($edit_mode_pcs_user && $service_to_edit_pcs_user['ServiceLogID']): ?><input type="hidden" name="service_log_id" value="<?php echo $service_to_edit_pcs_user['ServiceLogID']; ?>"><?php endif; ?>
    <?php if($edit_mode_pcs_user && $service_to_edit_pcs_user['PhotosFileID']): ?><input type="hidden" name="existing_photos_file_id" value="<?php echo $service_to_edit_pcs_user['PhotosFileID']; ?>"><?php endif; ?>
    <div class="form-row">
        <div class="form-group col-md-4"><label for="class_id_cs_user_form_id">کلاس <span class="text-danger">*</span></label><select name="class_id_cs_user" id="class_id_cs_user_form_id" class="form-control custom-select" required><?php foreach($my_available_classes_pcs_user as $cid_pcs_uf_val => $cdata_pcs_uf_val): ?><option value="<?php echo $cid_pcs_uf_val; ?>" <?php if(($service_to_edit_pcs_user['ClassID'] ?? '') == $cid_pcs_uf_val) echo 'selected';?>><?php echo htmlspecialchars($cdata_pcs_uf_val['ClassName']); ?></option><?php endforeach; ?></select></div>
        <div class="form-group col-md-4"><label for="event_name_key_cs_user_form">مناسبت <span class="text-danger">*</span></label><select name="event_name_key_cs_user" id="event_name_key_cs_user_form" class="form-control custom-select" required onchange="document.getElementById('custom_event_name_cs_user_group_form').style.display = (this.value === 'other' ? 'block' : 'none');"><option value="">-- انتخاب --</option><?php foreach($occasions_pcs_user as $okey_uf_val => $oval_uf_val): ?><option value="<?php echo $okey_uf_val; ?>" <?php if(($service_to_edit_pcs_user['EventNameKey'] ?? '') == $okey_uf_val) echo 'selected';?>><?php echo $oval_uf_val; ?></option><?php endforeach; ?></select></div>
        <div class="form-group col-md-4" id="custom_event_name_cs_user_group_form" style="<?php echo (($service_to_edit_pcs_user['EventNameKey'] ?? '') === 'other') ? 'display:block;' : 'display:none;'; ?>"><label for="custom_event_name_cs_user_input_form">نام مناسبت سفارشی</label><input type="text" name="custom_event_name_cs_user" id="custom_event_name_cs_user_input_form" class="form-control" value="<?php echo htmlspecialchars($service_to_edit_pcs_user['CustomEventName'] ?? '');?>"></div>
    </div>
    <div class="form-group"><label for="service_description_cs_user_form_id">شرح <span class="text-danger">*</span></label><textarea name="service_description_cs_user" id="service_description_cs_user_form_id" class="form-control" rows="3" required><?php echo htmlspecialchars($service_to_edit_pcs_user['ServiceDescription'] ?? '');?></textarea></div>
    <div class="form-row">
        <div class="form-group col-md-4"><label for="service_date_cs_user_form_id">تاریخ اجرا <span class="text-danger">*</span></label><input type="text" name="service_date_cs_user" id="service_date_cs_user_form_id" class="form-control persian-date-picker" value="<?php echo htmlspecialchars($service_to_edit_pcs_user['ServiceDate'] ?? date('Y-m-d'));?>" required></div>
        <div class="form-group col-md-4"><label for="location_cs_user_form_id">مکان</label><input type="text" name="location_cs_user" id="location_cs_user_form_id" class="form-control" value="<?php echo htmlspecialchars($service_to_edit_pcs_user['Location'] ?? '');?>"></div>
        <div class="form-group col-md-4"><label for="status_cs_user_form_id">وضعیت <span class="text-danger">*</span></label><select name="status_cs_user" id="status_cs_user_form_id" class="form-control custom-select" required><?php foreach($service_statuses_pcs_user as $skey_uf_val => $sval_uf_val):?><option value="<?php echo $skey_uf_val;?>" <?php if(($service_to_edit_pcs_user['Status'] ?? 'planned') == $skey_uf_val) echo 'selected';?>><?php echo $sval_uf_val;?></option><?php endforeach;?></select></div>
    </div>
    <div class="form-group"><div class="form-check"><input class="form-check-input" type="checkbox" name="needs_support_cs_user" id="needs_support_cs_user_form" value="1" <?php if(!empty($service_to_edit_pcs_user['NeedsSupport'])) echo 'checked';?>><label class="form-check-label" for="needs_support_cs_user_form">نیاز به پشتیبانی تیم پرورشی دارم</label></div></div>
    <div class="form-group"><label for="service_photos_user_upload_id">آپلود عکس</label><input type="file" name="service_photos_user" id="service_photos_user_upload_id" class="form-control-file">
    <?php if($edit_mode_pcs_user && $service_to_edit_pcs_user['PhotosFileID'] && !empty($service_to_edit_pcs_user['PhotoFilePath'])): ?> <small class="form-text text-muted">فایل فعلی: <a href="/my_site/<?php echo htmlspecialchars(ltrim($service_to_edit_pcs_user['PhotoFilePath'],'/')); ?>" target="_blank"><?php echo htmlspecialchars($service_to_edit_pcs_user['PhotoFileName'] ?? 'مشاهده');?></a></small><?php endif; ?></div>
    <button type="submit" name="submit_my_class_service" class="btn btn-primary-user"><?php echo $edit_mode_pcs_user ? 'ذخیره تغییرات' : 'ثبت گزارش'; ?></button> <?php if ($edit_mode_pcs_user): ?><a href="my_class_services.php" class="btn btn-outline-secondary">لغو</a><?php endif; ?>
</form><?php endif; ?></div></div>

<div class="card shadow-sm mt-4"><div class="card-header"><span class="card-title-text">تاریخچه گزارش‌های شما</span></div><div class="card-body">
<?php if(!empty($my_service_logs_user_list)): ?><div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>#</th><th>کلاس</th><th>مناسبت</th><th>تاریخ</th><th>وضعیت</th><th>عکس</th><th>عملیات</th></tr></thead><tbody>
<?php $my_log_row_idx_disp = 1; foreach($my_service_logs_user_list as $my_log_item_disp): ?><tr><td><?php echo $my_log_row_idx_disp++; ?></td><td><?php echo htmlspecialchars($my_log_item_disp['ClassName']);?></td><td><?php echo htmlspecialchars($my_log_item_disp['EventName']);?></td><td><?php echo to_jalali($my_log_item_disp['ServiceDate'],'yyyy/MM/dd');?></td><td><span class="badge badge-<?php echo $status_badge_cs_user_page[$my_log_item_disp['Status']] ?? 'light';?> p-1"><?php echo $service_statuses_pcs_user[$my_log_item_disp['Status']] ?? $my_log_item_disp['Status'];?></span></td>
<td><?php if($my_log_item_disp['PhotosFileID'] && $my_log_item_disp['PhotoFilePath']):?><a href="/my_site/<?php echo htmlspecialchars(ltrim($my_log_item_disp['PhotoFilePath'],'/'));?>" target="_blank" class="btn btn-xs btn-outline-success py-0 px-1">نمایش</a><?php else: echo '-'; endif;?></td>
<td class="actions-cell"><a href="my_class_services.php?edit_log_id=<?php echo $my_log_item_disp['ServiceLogID'];?>" class="btn btn-xs btn-warning" title="ویرایش"><svg class="icon" width="12" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a> <a href="my_class_services.php?delete_log_id=<?php echo $my_log_item_disp['ServiceLogID'];?>&csrf_token=<?php echo $csrf_token_pcs_user; ?>" class="btn btn-xs btn-danger" title="حذف" onclick="return confirm(' مطمئنید؟');"><svg class="icon" width="12" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></a></td></tr>
<?php endforeach; ?></tbody></table></div><?php else: ?><p class="text-muted text-center">هنوز گزارشی ثبت نکرده‌اید.</p><?php endif; ?></div></div>
<link rel="stylesheet" href="https://unpkg.com/persian-datepicker@latest/dist/css/persian-datepicker.min.css"/><script src="https://unpkg.com/persian-datepicker@latest/dist/js/persian-datepicker.min.js"></script>
<script>document.addEventListener('DOMContentLoaded', function() { document.querySelectorAll(".persian-date-picker").forEach(function(el){ new persianDatepicker(el, { format: 'YYYY-MM-DD', autoClose: true, observer: true, calendar:{ persian: { locale: 'fa' } } });}); document.querySelectorAll('.alert .close').forEach(function(button){button.addEventListener('click', function(event){event.target.closest('.alert').style.display = 'none';});});});</script>
<style> /* Styles from admin/parvareshi/class_services.php can be adapted */ </style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
