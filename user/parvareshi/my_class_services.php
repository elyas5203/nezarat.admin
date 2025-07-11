<?php
// user/parvareshi/my_class_services.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth, $user_base_url

$teacher_user_id_pcs = get_current_user_id();
if (!$teacher_user_id_pcs || get_current_user_type() !== 'teacher') {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'این بخش مخصوص مدرسین است.'];
    header("Location: " . ($user_base_url ?? '/my_site/user') . "/dashboard/index.php");
    exit;
}

$csrf_token_pcs = generate_csrf_token('user_parvareshi_class_services_action');
$errors_pcs = [];
$edit_mode_pcs = false;
// Default values for the form
$service_to_edit_pcs_default = [
    'ServiceLogID' => null, 'ClassID' => '', 'EventNameKey' => '', 'CustomEventName' => '',
    'ServiceDescription' => '', 'ServiceDate' => '', 'Location' => '',
    'Status' => 'planned', 'PhotosFileID' => null, 'PhotoFilePath' => null, 'PhotoFileName' => null
];
$service_to_edit_pcs = $service_to_edit_pcs_default;


$occasions_pcs = ['ghadir' => 'غدیر', 'nime_shaban' => 'نیمه شعبان', 'muharram_shahadat' => 'محرم و شهادت‌ها', 'dahe_fajr' => 'دهه فجر', 'other' => 'سایر مناسبت‌ها'];
$service_statuses_pcs = ['planned' => 'برنامه‌ریزی شده', 'completed' => 'انجام شده', 'needs_support_requested' => 'درخواست پشتیبانی'];
$status_badge_cs_user = ['planned' => 'info', 'completed' => 'success', 'needs_support_requested' => 'primary'];


$my_classes_pcs_q = $conn->prepare("SELECT ClassID, ClassName, AcademicYear FROM Classes WHERE TeacherUserID = ? AND IsActive = TRUE ORDER BY AcademicYear DESC, ClassName");
$my_available_classes_pcs = [];
if($my_classes_pcs_q){ $my_classes_pcs_q->bind_param("i", $teacher_user_id_pcs); $my_classes_pcs_q->execute(); $res_mycls = $my_classes_pcs_q->get_result();
    while($c_pcs = $res_mycls->fetch_assoc()) $my_available_classes_pcs[$c_pcs['ClassID']] = $c_pcs; $my_classes_pcs_q->close();
}

// Handle Edit Request (GET)
if (isset($_GET['edit_log_id']) && is_numeric($_GET['edit_log_id']) && $_SERVER["REQUEST_METHOD"] != "POST") {
    $edit_log_id_get_user = (int)$_GET['edit_log_id'];
    // Ensure teacher can only edit their own class's service log
    $stmt_edit_cs_user = $conn->prepare("SELECT cs.* FROM ClassServices cs JOIN Classes c ON cs.ClassID = c.ClassID WHERE cs.ServiceLogID = ? AND c.TeacherUserID = ?");
    if ($stmt_edit_cs_user) {
        $stmt_edit_cs_user->bind_param("ii", $edit_log_id_get_user, $teacher_user_id_pcs);
        $stmt_edit_cs_user->execute();
        $result_edit_cs_user = $stmt_edit_cs_user->get_result();
        if ($data_cs_user = $result_edit_cs_user->fetch_assoc()) {
            $service_to_edit_pcs = $data_cs_user;
            $event_name_from_db = $data_cs_user['EventName'];
            $found_key = array_search($event_name_from_db, $occasions_pcs);
            if ($found_key !== false) {
                $service_to_edit_pcs['EventNameKey'] = $found_key;
                $service_to_edit_pcs['CustomEventName'] = '';
            } else {
                $service_to_edit_pcs['EventNameKey'] = 'other';
                $service_to_edit_pcs['CustomEventName'] = $event_name_from_db;
            }
            if ($service_to_edit_pcs['ServiceDate']) { try { $dt_obj_cs_edit_user = new DateTime($service_to_edit_pcs['ServiceDate']); $service_to_edit_pcs['ServiceDate'] = $dt_obj_cs_edit_user->format('Y-m-d'); } catch (Exception $e) {} }
            $edit_mode_pcs = true;
        } else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "گزارش یافت نشد یا اجازه ویرایش ندارید."]; }
        $stmt_edit_cs_user->close();
    } else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "خطا بارگذاری گزارش: " . $conn->error]; }
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_my_class_service'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'user_parvareshi_class_services_action')) {
        $errors_pcs[] = 'خطای CSRF!';
    } else {
        $service_log_id_pcs_post = isset($_POST['service_log_id']) && is_numeric($_POST['service_log_id']) ? (int)$_POST['service_log_id'] : null;
        $class_id_pcs_post = isset($_POST['class_id_cs_user']) ? (int)$_POST['class_id_cs_user'] : null; // Name from form
        $event_name_key_pcs_post = sanitize_input($_POST['event_name_key_cs_user'] ?? '');
        $custom_event_name_pcs_post = sanitize_input($_POST['custom_event_name_cs_user'] ?? '');
        $service_description_pcs_post = sanitize_input($_POST['service_description_cs_user'] ?? '');
        $service_date_pcs_post = sanitize_input($_POST['service_date_cs_user'] ?? '');
        $location_pcs_post = sanitize_input($_POST['location_cs_user'] ?? '');
        $status_pcs_post = sanitize_input($_POST['status_cs_user'] ?? 'planned');
        $photos_file_id_pcs_post = isset($_POST['existing_photos_file_id']) ? (int)$_POST['existing_photos_file_id'] : null;

        // Repopulate for sticky form
        $service_to_edit_pcs = ['ServiceLogID' => $service_log_id_pcs_post, 'ClassID' => $class_id_pcs_post, 'EventNameKey' => $event_name_key_pcs_post, 'CustomEventName' => $custom_event_name_pcs_post, 'ServiceDescription' => $service_description_pcs_post, 'ServiceDate' => $service_date_pcs_post, 'Location' => $location_pcs_post, 'Status' => $status_pcs_post, 'PhotosFileID' => $photos_file_id_pcs_post];
        $edit_mode_pcs = ($service_log_id_pcs_post !== null);


        $final_event_name_pcs_post = ($event_name_key_pcs_post === 'other' && !empty($custom_event_name_pcs_post)) ? $custom_event_name_pcs_post : ($occasions_pcs[$event_name_key_pcs_post] ?? $event_name_key_pcs_post);

        if (empty($class_id_pcs_post) || !isset($my_available_classes_pcs[$class_id_pcs_post])) $errors_pcs[] = "کلاس نامعتبر.";
        if (empty($event_name_key_pcs_post)) $errors_pcs[] = "انتخاب مناسبت الزامی.";
        if ($event_name_key_pcs_post === 'other' && empty($custom_event_name_pcs_post)) $errors_pcs[] = "نام مناسبت سفارشی را وارد کنید.";
        if (empty($service_description_pcs_post)) $errors_pcs[] = "شرح خدمت‌گزاری الزامی.";
        if (empty($service_date_pcs_post) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $service_date_pcs_post)) $errors_pcs[] = "تاریخ اجرا نامعتبر (YYYY-MM-DD).";
        if (!array_key_exists($status_pcs_post, $service_statuses_pcs)) $errors_pcs[] = "وضعیت نامعتبر.";

        // File upload logic (Simplified - use a helper function in a real app)
        if (isset($_FILES['service_photos_user']) && $_FILES['service_photos_user']['error'] == UPLOAD_ERR_OK) {
            $upload_dir_pcs_user = __DIR__ . '/../../../uploads/class_service_photos/'; // Define your upload directory
            if (!is_dir($upload_dir_pcs_user)) { if (!mkdir($upload_dir_pcs_user, 0775, true)) $errors_pcs[] = 'خطا ایجاد پوشه آپلود.'; }
            if (empty($errors_pcs)) {
                $file_info_pcs_user = pathinfo($_FILES['service_photos_user']['name']);
                $file_extension_pcs_user = strtolower($file_info_pcs_user['extension'] ?? '');
                $allowed_extensions_pcs_user = ['jpg', 'jpeg', 'png', 'gif']; $max_size_pcs_user = 5*1024*1024; // 5MB
                if(!in_array($file_extension_pcs_user, $allowed_extensions_pcs_user)) $errors_pcs[] = "فقط فایل‌های تصویری مجاز است.";
                elseif($_FILES['service_photos_user']['size'] > $max_size_pcs_user) $errors_pcs[] = "حجم فایل بیش از 5MB است.";
                else {
                    $safe_orig_name_pcs = preg_replace('/[^A-Za-z0-9_\-\.ء-ي]/u', '_', basename($_FILES['service_photos_user']['name']));
                    $new_filename_pcs = uniqid('svc_'.$class_id_pcs_post."_", true).".".$file_extension_pcs_user;
                    $upload_path_pcs = $upload_dir_pcs_user . $new_filename_pcs;
                    if(move_uploaded_file($_FILES['service_photos_user']['tmp_name'], $upload_path_pcs)){
                        $relative_path_pcs_db = 'uploads/class_service_photos/' . $new_filename_pcs;
                        $stmt_file_pcs = $conn->prepare("INSERT INTO Files (FileName, FilePath, FileType, FileSize, UploadedByUserID, AssociatedEntityType) VALUES (?,?,?,?,?, 'class_service_photo')");
                        if($stmt_file_pcs){
                            $mime_pcs = mime_content_type($upload_path_pcs) ?: $_FILES['service_photos_user']['type'];
                            $size_pcs = $_FILES['service_photos_user']['size'];
                            $stmt_file_pcs->bind_param("sssis", $safe_orig_name_pcs, $relative_path_pcs_db, $mime_pcs, $size_pcs, $teacher_user_id_pcs);
                            if($stmt_file_pcs->execute()) $photos_file_id_pcs_post = $stmt_file_pcs->insert_id;
                            else $errors_pcs[] = "خطا ذخیره اطلاعات فایل: ".$stmt_file_pcs->error;
                            $stmt_file_pcs->close();
                        } else $errors_pcs[] = "خطا آماده سازی فایل: ".$conn->error;
                    } else $errors_pcs[] = "خطا در آپلود فایل عکس.";
                }
            }
        } elseif (isset($_FILES['service_photos_user']) && $_FILES['service_photos_user']['error'] != UPLOAD_ERR_NO_FILE) {
            $errors_pcs[] = "خطا در آپلود عکس (کد: ".$_FILES['service_photos_user']['error'].")";
        }


        if (empty($errors_pcs)) {
            if ($service_log_id_pcs_post) {
                $stmt_db_pcs = $conn->prepare("UPDATE ClassServices SET ClassID=?, EventName=?, ServiceDescription=?, ServiceDate=?, Location=?, Status=?, PhotosFileID=? WHERE ServiceLogID=? AND SubmittedByUserID=?"); // Ensure teacher owns this log
                if($stmt_db_pcs) { $stmt_db_pcs->bind_param("isssssiii", $class_id_pcs_post, $final_event_name_pcs_post, $service_description_pcs_post, $service_date_pcs_post, $location_pcs_post, $status_pcs_post, $photos_file_id_pcs_post, $service_log_id_pcs_post, $teacher_user_id_pcs);
                    if($stmt_db_pcs->execute()) $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'ویرایش شد.']; else $errors_pcs[] = "خطا ویرایش: " . $stmt_db_pcs->error; $stmt_db_pcs->close();
                } else $errors_pcs[] = "خطا آماده سازی ویرایش: " . $conn->error;
            } else {
                $stmt_db_pcs = $conn->prepare("INSERT INTO ClassServices (ClassID, EventName, ServiceDescription, ServiceDate, Location, Status, PhotosFileID, SubmittedByUserID) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if($stmt_db_pcs) { $stmt_db_pcs->bind_param("isssssii", $class_id_pcs_post, $final_event_name_pcs_post, $service_description_pcs_post, $service_date_pcs_post, $location_pcs_post, $status_pcs_post, $photos_file_id_pcs_post, $teacher_user_id_pcs);
                    if($stmt_db_pcs->execute()) $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'ثبت شد.']; else $errors_pcs[] = "خطا ثبت: " . $stmt_db_pcs->error; $stmt_db_pcs->close();
                } else $errors_pcs[] = "خطا آماده سازی ثبت: " . $conn->error;
            }
            if(empty($errors_pcs)) { regenerate_csrf_token('user_parvareshi_class_services_action'); header("Location: my_class_services.php"); exit; }
        }
    }
    $csrf_token_pcs = regenerate_csrf_token('user_parvareshi_class_services_action');
}

if (isset($_GET['delete_log_id']) && is_numeric($_GET['delete_log_id'])) {
    // ... (CSRF and ownership check, then delete from ClassServices and potentially the File) ...
}

$my_service_logs_q = $conn->prepare("SELECT cs.*, c.ClassName, c.AcademicYear, f.FileName AS PhotoFileName, f.FilePath AS PhotoFilePath FROM ClassServices cs JOIN Classes c ON cs.ClassID = c.ClassID LEFT JOIN Files f ON cs.PhotosFileID = f.FileID WHERE c.TeacherUserID = ? ORDER BY cs.ServiceDate DESC");
$my_service_logs = [];
if($my_service_logs_q){ $my_service_logs_q->bind_param("i", $teacher_user_id_pcs); $my_service_logs_q->execute(); $res_mylogs = $my_service_logs_q->get_result(); while($log_pcs_item = $res_mylogs->fetch_assoc()) $my_service_logs[] = $log_pcs_item; $my_service_logs_q->close();}
?>
<div class="page-header"><h1>خدمت‌گزاری کلاس‌های من</h1><p class="page-subtitle">ثبت و مدیریت گزارش فعالیت‌های خدمت‌گزاری کلاس‌ها.</p></div>
<?php if (isset($_SESSION['flash_message'])) { /* ... Flash ... */ } ?> <?php if (!empty($errors_pcs)): ?> <!-- errors --> <?php endif; ?>

<div class="card shadow-sm mb-4"><div class="card-header"><span class="card-title-text"><?php echo $edit_mode_pcs ? 'ویرایش گزارش' : 'ثبت گزارش جدید'; ?></span></div>
<div class="card-body"> <?php if (empty($my_available_classes_pcs)): ?> <div class="alert alert-warning">کلاسی به شما تخصیص داده نشده.</div> <?php else: ?>
<form action="my_class_services.php<?php if($edit_mode_pcs && $service_to_edit_pcs['ServiceLogID']) echo '?edit_log_id='.$service_to_edit_pcs['ServiceLogID'];?>" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_pcs; ?>">
    <?php if($edit_mode_pcs && $service_to_edit_pcs['ServiceLogID']): ?><input type="hidden" name="service_log_id" value="<?php echo $service_to_edit_pcs['ServiceLogID']; ?>"><?php endif; ?>
    <?php if($edit_mode_pcs && $service_to_edit_pcs['PhotosFileID']): ?><input type="hidden" name="existing_photos_file_id" value="<?php echo $service_to_edit_pcs['PhotosFileID']; ?>"><?php endif; ?>
    <div class="form-row">
        <div class="form-group col-md-4"><label for="class_id_cs_user">کلاس <span class="text-danger">*</span></label><select name="class_id_cs_user" id="class_id_cs_user" class="form-control custom-select" required><option value="">-- انتخاب --</option><?php foreach($my_available_classes_pcs as $cid_pcs_uf => $cdata_pcs_uf): ?><option value="<?php echo $cid_pcs_uf; ?>" <?php if(($service_to_edit_pcs['ClassID'] ?? '') == $cid_pcs_uf) echo 'selected';?>><?php echo htmlspecialchars($cdata_pcs_uf['ClassName'] . ' (' . $cdata_pcs_uf['AcademicYear'] . ')'); ?></option><?php endforeach; ?></select></div>
        <div class="form-group col-md-4"><label for="event_name_key_cs_user">مناسبت <span class="text-danger">*</span></label><select name="event_name_key_cs_user" id="event_name_key_cs_user" class="form-control custom-select" required onchange="document.getElementById('custom_event_name_cs_user_group').style.display = (this.value === 'other' ? 'block' : 'none');"><option value="">-- انتخاب --</option><?php foreach($occasions_pcs as $okey_uf => $oval_uf): ?><option value="<?php echo $okey_uf; ?>" <?php if(($service_to_edit_pcs['EventNameKey'] ?? '') == $okey_uf) echo 'selected';?>><?php echo $oval_uf; ?></option><?php endforeach; ?></select></div>
        <div class="form-group col-md-4" id="custom_event_name_cs_user_group" style="<?php echo (($service_to_edit_pcs['EventNameKey'] ?? '') === 'other') ? 'display:block;' : 'display:none;'; ?>"><label for="custom_event_name_cs_user">نام مناسبت سفارشی</label><input type="text" name="custom_event_name_cs_user" id="custom_event_name_cs_user" class="form-control" value="<?php echo htmlspecialchars($service_to_edit_pcs['CustomEventName'] ?? '');?>"></div>
    </div>
    <div class="form-group"><label for="service_description_cs_user">شرح <span class="text-danger">*</span></label><textarea name="service_description_cs_user" id="service_description_cs_user" class="form-control" rows="3" required><?php echo htmlspecialchars($service_to_edit_pcs['ServiceDescription'] ?? '');?></textarea></div>
    <div class="form-row">
        <div class="form-group col-md-4"><label for="service_date_cs_user">تاریخ اجرا <span class="text-danger">*</span></label><input type="text" name="service_date_cs_user" id="service_date_cs_user" class="form-control persian-date-picker" placeholder="YYYY-MM-DD" value="<?php echo htmlspecialchars($service_to_edit_pcs['ServiceDate'] ?? '');?>" required></div>
        <div class="form-group col-md-4"><label for="location_cs_user">مکان</label><input type="text" name="location_cs_user" id="location_cs_user" class="form-control" value="<?php echo htmlspecialchars($service_to_edit_pcs['Location'] ?? '');?>"></div>
        <div class="form-group col-md-4"><label for="status_cs_user">وضعیت <span class="text-danger">*</span></label><select name="status_cs_user" id="status_cs_user" class="form-control custom-select" required><?php foreach($service_statuses_pcs as $skey_uf => $sval_uf):?><option value="<?php echo $skey_uf;?>" <?php if(($service_to_edit_pcs['Status'] ?? 'planned') == $skey_uf) echo 'selected';?>><?php echo $sval_uf;?></option><?php endforeach;?></select></div>
    </div>
    <div class="form-group"><label for="service_photos_user">آپلود عکس</label><input type="file" name="service_photos_user" id="service_photos_user" class="form-control-file">
    <?php if($edit_mode_pcs && $service_to_edit_pcs['PhotosFileID'] && !empty($service_to_edit_pcs['PhotoFilePath'])): ?> <small class="form-text text-muted">فایل فعلی: <a href="/my_site/<?php echo htmlspecialchars(ltrim($service_to_edit_pcs['PhotoFilePath'],'/')); ?>" target="_blank"><?php echo htmlspecialchars($service_to_edit_pcs['PhotoFileName'] ?? 'مشاهده فایل');?></a></small><?php endif; ?></div>
    <button type="submit" name="submit_my_class_service" class="btn btn-primary-user"><?php echo $edit_mode_pcs ? 'ذخیره تغییرات' : 'ثبت گزارش'; ?></button> <?php if ($edit_mode_pcs): ?><a href="my_class_services.php" class="btn btn-outline-secondary">لغو</a><?php endif; ?>
</form><?php endif; ?></div></div>

<div class="card shadow-sm mt-4"><div class="card-header"><span class="card-title-text">تاریخچه گزارش‌های شما</span></div><div class="card-body">
<?php if(!empty($my_service_logs)): ?><div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>#</th><th>کلاس</th><th>مناسبت</th><th>تاریخ</th><th>وضعیت</th><th>عکس</th><th>عملیات</th></tr></thead><tbody>
<?php $my_log_row_idx = 1; foreach($my_service_logs as $my_log_item): ?><tr><td><?php echo $my_log_row_idx++; ?></td><td><?php echo htmlspecialchars($my_log_item['ClassName']);?></td><td><?php echo htmlspecialchars($my_log_item['EventName']);?></td><td><?php echo to_jalali($my_log_item['ServiceDate'],'yyyy/MM/dd');?></td><td><span class="badge badge-<?php echo $status_badge_cs_user[$my_log_item['Status']] ?? 'light';?> p-1"><?php echo $service_statuses_pcs[$my_log_item['Status']] ?? $my_log_item['Status'];?></span></td>
<td><?php if($my_log_item['PhotosFileID'] && $my_log_item['PhotoFilePath']):?><a href="/my_site/<?php echo htmlspecialchars(ltrim($my_log_item['PhotoFilePath'],'/'));?>" target="_blank" class="btn btn-xs btn-outline-success py-0 px-1">نمایش</a><?php else: echo '-'; endif;?></td>
<td class="actions-cell"><a href="my_class_services.php?edit_log_id=<?php echo $my_log_item['ServiceLogID'];?>" class="btn btn-xs btn-warning" title="ویرایش"><svg class="icon" width="12" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a> <a href="my_class_services.php?delete_log_id=<?php echo $my_log_item['ServiceLogID'];?>&csrf_token=<?php echo $csrf_token_pcs; ?>" class="btn btn-xs btn-danger" title="حذف" onclick="return confirm(' مطمئنید؟');"><svg class="icon" width="12" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></a></td></tr>
<?php endforeach; ?></tbody></table></div><?php else: ?><p class="text-muted text-center">هنوز گزارشی ثبت نکرده‌اید.</p><?php endif; ?></div></div>
<link rel="stylesheet" href="https://unpkg.com/persian-datepicker@latest/dist/css/persian-datepicker.min.css"/><script src="https://unpkg.com/persian-datepicker@latest/dist/js/persian-datepicker.min.js"></script>
<script> /* Datepicker init, alert dismissal ... */ </script><style> /* styles from admin ... */ </style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
