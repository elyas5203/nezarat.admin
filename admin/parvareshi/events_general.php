<?php
// admin/parvareshi/events_general.php
require_once __DIR__ . '/../includes/header.php';

$csrf_token_pv_events = generate_csrf_token('parvareshi_events_action');
$errors_pv_evt = [];
$edit_mode_pv_evt = false;
$event_pv_to_edit_values_default = [
    'ProjectID' => null, 'ProjectName' => '', 'ProjectType' => 'public_event',
    'Description' => '', 'Proposal' => '', 'StartDate' => '', 'EndDate' => '',
    'Budget' => '', 'Location' => '', 'Status' => 'planning', 'LeadUserID' => null
];
$event_pv_to_edit_values = $event_pv_to_edit_values_default;

$project_type_options_pv_disp = ['public_event' => 'مراسم عمومی', 'camp' => 'اردو', 'other' => 'سایر پروژه‌ها'];
$project_status_options_pv_disp = ['planning' => 'برنامه‌ریزی', 'approved' => 'تصویب شده', 'ongoing' => 'در حال اجرا', 'completed' => 'تکمیل شده', 'archived' => 'بایگانی', 'cancelled' => 'لغو شده'];
$status_badge_pv_evt_disp = ['planning'=>'info','approved'=>'primary','ongoing'=>'warning','completed'=>'success','archived'=>'secondary','cancelled'=>'danger'];

$lead_users_q_pv = $conn->query("SELECT UserID, FirstName, LastName, Username FROM Users WHERE IsActive = TRUE ORDER BY LastName, FirstName");
$available_leads_pv_disp = [];
if($lead_users_q_pv){ while($lu_pv_item = $lead_users_q_pv->fetch_assoc()) $available_leads_pv_disp[$lu_pv_item['UserID']] = $lu_pv_item['FirstName'].' '.$lu_pv_item['LastName'] . ' (@'.$lu_pv_item['Username'].')'; $lead_users_q_pv->close(); }

// --- START: Load Edit Data or Handle POST for Main Form ---
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id']) && $_SERVER["REQUEST_METHOD"] != "POST") {
    $edit_id_pv_get_val = (int)$_GET['edit_id'];
    $stmt_edit_pv_get_val = $conn->prepare("SELECT * FROM ParvareshiProjects WHERE ProjectID = ?");
    if ($stmt_edit_pv_get_val) {
        $stmt_edit_pv_get_val->bind_param("i", $edit_id_pv_get_val);
        $stmt_edit_pv_get_val->execute();
        $result_edit_pv_get_val = $stmt_edit_pv_get_val->get_result();
        if ($data_pv_get_val = $result_edit_pv_get_val->fetch_assoc()) {
            $event_pv_to_edit_values = $data_pv_get_val;
            if($event_pv_to_edit_values['StartDate']) $event_pv_to_edit_values['StartDate'] = (new DateTime($event_pv_to_edit_values['StartDate']))->format('Y-m-d');
            if($event_pv_to_edit_values['EndDate']) $event_pv_to_edit_values['EndDate'] = (new DateTime($event_pv_to_edit_values['EndDate']))->format('Y-m-d');
            $edit_mode_pv_evt = true;
        } else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "پروژه یافت نشد."]; }
        $stmt_edit_pv_get_val->close();
    } else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "خطا بارگذاری: " . $conn->error]; }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" ) {
    $current_project_id_for_redirect = isset($_POST['project_id']) ? (int)$_POST['project_id'] : (isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : null);

    if (isset($_POST['submit_pv_event'])) { // MAIN FORM SUBMISSION
        if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'parvareshi_events_action')) {
            $errors_pv_evt[] = 'خطای CSRF (فرم اصلی)!';
        } else {
            $project_id_post_main = isset($_POST['project_id']) && is_numeric($_POST['project_id']) ? (int)$_POST['project_id'] : null;
            // Repopulate $event_pv_to_edit_values with POST data
            foreach (array_keys($event_pv_to_edit_values_default) as $key_pv_main) { // Use default keys to ensure all are covered
                if($key_pv_main !== 'ProjectID') $event_pv_to_edit_values[$key_pv_main] = sanitize_input($_POST[$key_pv_main] ?? ($event_pv_to_edit_values_default[$key_pv_main] ?? null));
            }
            if($project_id_post_main) $event_pv_to_edit_values['ProjectID'] = $project_id_post_main;
            $event_pv_to_edit_values['LeadUserID'] = !empty($_POST['LeadUserID']) ? (int)$_POST['LeadUserID'] : null;
            $event_pv_to_edit_values['Budget'] = !empty($_POST['Budget']) ? floatval(preg_replace('/[^0-9.]/', '', $_POST['Budget'])) : null;
            $event_pv_to_edit_values['StartDate'] = !empty($_POST['StartDate']) ? sanitize_input($_POST['StartDate']) : null;
            $event_pv_to_edit_values['EndDate'] = !empty($_POST['EndDate']) ? sanitize_input($_POST['EndDate']) : null;
            $edit_mode_pv_evt = ($project_id_post_main !== null);

            // Validation...
            if (empty($event_pv_to_edit_values['ProjectName'])) $errors_pv_evt[] = "نام پروژه الزامی است.";
            // ... other validations ...

            if (empty($errors_pv_evt)) {
                // DB Operation (INSERT or UPDATE for ParvareshiProjects)
                // ... (same as before) ...
                if(empty($errors_pv_evt)) {
                    $_SESSION['flash_message'] = ['type' => 'success', 'text' => $project_id_post_main ? 'پروژه ویرایش شد.' : 'پروژه ایجاد شد.'];
                    regenerate_csrf_token('parvareshi_events_action');
                    header("Location: events_general.php" . ($project_id_post_main ? "?edit_id=".$project_id_post_main :( $stmt_pv_db->insert_id ? "?edit_id=".$stmt_pv_db->insert_id : ""))); // Redirect to edit page of created/updated project
                    exit;
                }
            }
        }
        $csrf_token_pv_events = regenerate_csrf_token('parvareshi_events_action');
    }
    elseif (isset($_POST['submit_pv_event_file']) && $current_project_id_for_redirect) { // FILE UPLOAD SUBMISSION
        $csrf_file_token_name = 'parvareshi_event_file_upload_' . $current_project_id_for_redirect;
        if (!verify_csrf_token($_POST['csrf_token_file'] ?? '', $csrf_file_token_name )) {
            $errors_pv_evt[] = 'خطای CSRF (آپلود فایل)!';
        } else {
            $file_description_pv_post = sanitize_input($_POST['file_description_pv'] ?? '');
            if (isset($_FILES['project_general_file']) && $_FILES['project_general_file']['error'] == UPLOAD_ERR_OK) {
                // ... (Full file upload logic as in previous response, using $current_project_id_for_redirect)
                // On success: $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'فایل آپلود شد.'];
                // On error: $errors_pv_evt[] = "Error message";
            } // ... other file error checks ...

            regenerate_csrf_token($csrf_file_token_name); // Regenerate specific file upload token
            if(empty($errors_pv_evt) && isset($_SESSION['flash_message'])) { header("Location: events_general.php?edit_id=" . $current_project_id_for_redirect); exit;}
            // If errors, page will reload and show them, $event_pv_to_edit_values should be repopulated from hidden fields or re-fetched
        }
    }
}
// --- END: Load Edit Data or Handle POST ---


// Handle Delete Public File for Project (GET action)
if (isset($_GET['delete_public_file_id']) && is_numeric($_GET['delete_public_file_id']) && isset($_GET['project_ref_id']) && is_numeric($_GET['project_ref_id'])) {
    $file_id_to_delete_pv = (int)$_GET['delete_public_file_id'];
    $project_ref_id_pv = (int)$_GET['project_ref_id'];
    $csrf_token_file_delete_pv = $_GET['csrf_token_file_del'] ?? '';

    if (!verify_csrf_token($csrf_token_file_delete_pv, 'parvareshi_event_file_delete_'.$file_id_to_delete_pv)) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطای CSRF (حذف فایل)!'];
    } else {
        // ... (Fetch FilePath, unlink, delete from Files table - similar to other delete file actions) ...
        $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'فایل پیوست حذف شد.']; // Or error
    }
    regenerate_csrf_token('parvareshi_event_file_delete_'.$file_id_to_delete_pv);
    header("Location: events_general.php?edit_id=" . $project_ref_id_pv); exit;
}

// Fetch associated public files if in edit mode
$associated_public_files_pv_disp = [];
if($edit_mode_pv_evt && $event_pv_to_edit_values['ProjectID']){ /* ... fetch files ... */ }

$pv_events_list_q_main_disp = $conn->query("SELECT pp.*, CONCAT(u.FirstName, ' ', u.LastName) as LeadUserName FROM ParvareshiProjects pp LEFT JOIN Users u ON pp.LeadUserID = u.UserID ORDER BY pp.StartDate DESC, pp.ProjectName ASC LIMIT 50");
?>
<div class="page-header"><h1>مدیریت مناسبت‌های عمومی و اردوها</h1><div class="page-header-actions"><a href="class_services.php" class="btn btn-secondary">خدمت‌گزاری کلاس‌ها</a> <a href="rental_items.php" class="btn btn-info">کرایه‌چی</a></div></div>

<?php if (isset($_SESSION['flash_message'])) { /* ... Flash ... */ } ?>
<?php if (!empty($errors_pv_evt)): ?> <div class="alert alert-danger"><ul><?php foreach ($errors_pv_evt as $err_pv_item_disp): ?><li><?php echo htmlspecialchars($err_pv_item_disp); ?></li><?php endforeach; ?></ul></div> <?php endif; ?>

<div class="row"><div class="col-lg-<?php echo $edit_mode_pv_evt ? '12' : '5';?> mb-4"><div class="card shadow-sm">
    <div class="card-header"><span class="card-title-text"><?php echo $edit_mode_pv_evt ? 'ویرایش پروژه: ' . htmlspecialchars($event_pv_to_edit_values['ProjectName']) : 'افزودن پروژه/رویداد جدید'; ?></span></div>
    <div class="card-body">
    <form action="events_general.php<?php if($edit_mode_pv_evt && $event_pv_to_edit_values['ProjectID']) echo '?edit_id='.$event_pv_to_edit_values['ProjectID']; ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_pv_events; ?>">
        <?php if ($edit_mode_pv_evt && $event_pv_to_edit_values['ProjectID']): ?><input type="hidden" name="project_id" value="<?php echo $event_pv_to_edit_values['ProjectID']; ?>"><?php endif; ?>

        <div class="form-group"><label for="ProjectName_pv_form_id">نام پروژه <span class="text-danger">*</span></label><input type="text" class="form-control" id="ProjectName_pv_form_id" name="ProjectName" value="<?php echo htmlspecialchars($event_pv_to_edit_values['ProjectName']); ?>" required></div>
        <div class="form-row">
            <div class="form-group col-md-6"><label for="ProjectType_pv_form_id">نوع <span class="text-danger">*</span></label><select name="ProjectType" id="ProjectType_pv_form_id" class="form-control custom-select" required><?php foreach($project_type_options_pv_disp as $ptk_f_disp => $ptv_f_disp):?><option value="<?php echo $ptk_f_disp;?>" <?php if($event_pv_to_edit_values['ProjectType']==$ptk_f_disp) echo 'selected';?>><?php echo $ptv_f_disp;?></option><?php endforeach;?></select></div>
            <div class="form-group col-md-6"><label for="Status_pv_form_id">وضعیت <span class="text-danger">*</span></label><select name="Status" id="Status_pv_form_id" class="form-control custom-select" required><?php foreach($project_status_options_pv_disp as $psk_f_disp => $psv_f_disp):?><option value="<?php echo $psk_f_disp;?>" <?php if($event_pv_to_edit_values['Status']==$psk_f_disp) echo 'selected';?>><?php echo $psv_f_disp;?></option><?php endforeach;?></select></div>
        </div>
        <div class="form-row">
            <div class="form-group col-md-6"><label for="StartDate_pv_form_id">تاریخ شروع</label><input type="text" class="form-control persian-date-picker" id="StartDate_pv_form_id" name="StartDate" value="<?php echo htmlspecialchars($event_pv_to_edit_values['StartDate']); ?>" placeholder="YYYY-MM-DD"></div>
            <div class="form-group col-md-6"><label for="EndDate_pv_form_id">تاریخ پایان</label><input type="text" class="form-control persian-date-picker" id="EndDate_pv_form_id" name="EndDate" value="<?php echo htmlspecialchars($event_pv_to_edit_values['EndDate']); ?>" placeholder="YYYY-MM-DD"></div>
        </div>
        <div class="form-group"><label for="Location_pv_form_id">مکان</label><input type="text" class="form-control" id="Location_pv_form_id" name="Location" value="<?php echo htmlspecialchars($event_pv_to_edit_values['Location']); ?>"></div>
        <div class="form-row">
            <div class="form-group col-md-6"><label for="Budget_pv_form_id">بودجه (تومان)</label><input type="text" class="form-control" id="Budget_pv_form_id" name="Budget" value="<?php echo htmlspecialchars(number_format($event_pv_to_edit_values['Budget'] ?? 0, 0, '.', '')); ?>" placeholder="مثال: 5000000"></div>
            <div class="form-group col-md-6"><label for="LeadUserID_pv_form_id">مسئول</label><select name="LeadUserID" id="LeadUserID_pv_form_id" class="form-control custom-select"><option value="">-- انتخاب --</option><?php foreach($available_leads_pv_disp as $lid_f_disp => $lname_f_disp):?><option value="<?php echo $lid_f_disp;?>" <?php if($event_pv_to_edit_values['LeadUserID']==$lid_f_disp) echo 'selected';?>><?php echo htmlspecialchars($lname_f_disp);?></option><?php endforeach;?></select></div>
        </div>
        <div class="form-group"><label for="Description_pv_form_id">توضیحات</label><textarea class="form-control" id="Description_pv_form_id" name="Description" rows="3"><?php echo htmlspecialchars($event_pv_to_edit_values['Description']); ?></textarea></div>
        <div class="form-group"><label for="Proposal_pv_form_id">پروپوزال/گزارش</label><textarea class="form-control" id="Proposal_pv_form_id" name="Proposal" rows="5"><?php echo htmlspecialchars($event_pv_to_edit_values['Proposal']); ?></textarea></div>
        <div class="form-actions"><button type="submit" name="submit_pv_event" class="btn btn-primary"><?php echo $edit_mode_pv_evt ? 'ذخیره' : 'ایجاد'; ?></button><?php if ($edit_mode_pv_evt): ?><a href="events_general.php" class="btn btn-outline-secondary">لغو</a><?php endif; ?></div>
    </form>

    <?php if ($edit_mode_pv_evt && $event_pv_to_edit_values['ProjectID']):
        $csrf_token_file_upload = generate_csrf_token('parvareshi_event_file_upload_' . $event_pv_to_edit_values['ProjectID']);
    ?>
    <hr class="my-4">
    <h6 class="mb-3">فایل‌های پیوست عمومی</h6>
    <?php if(!empty($associated_public_files_pv_disp)): ?><ul class="list-group list-group-flush mb-3 small">
        <?php foreach($associated_public_files_pv_disp as $apf_disp):
            $csrf_token_file_delete = generate_csrf_token('parvareshi_event_file_delete_'.$apf_disp['FileID']);
        ?>
        <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-1">
            <div><a href="/my_site/<?php echo htmlspecialchars(ltrim($apf_disp['FilePath'],'/'));?>" target="_blank" download="<?php echo htmlspecialchars($apf_disp['FileName']);?>"><?php echo htmlspecialchars($apf_disp['FileName']);?></a> <?php if($apf_disp['Description']):?>(<?php echo htmlspecialchars($apf_disp['Description']);?>)<?php endif;?></div>
            <a href="events_general.php?edit_id=<?php echo $event_pv_to_edit_values['ProjectID']; ?>&delete_public_file_id=<?php echo $apf_disp['FileID'];?>&project_ref_id=<?php echo $event_pv_to_edit_values['ProjectID']; ?>&csrf_token_file_del=<?php echo $csrf_token_file_delete; ?>" class="btn btn-xs btn-danger" onclick="return confirm('آیا از حذف این فایل مطمئنید؟');">&times;</a>
        </li><?php endforeach; ?></ul><?php else: ?><p class="text-muted small">فایلی پیوست نشده.</p><?php endif; ?>
    <form action="events_general.php?edit_id=<?php echo $event_pv_to_edit_values['ProjectID']; ?>" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token_file" value="<?php echo $csrf_token_file_upload; ?>">
        <input type="hidden" name="project_id" value="<?php echo $event_pv_to_edit_values['ProjectID']; ?>">
        <div class="form-group"><label for="project_general_file_upload_id" class="small">آپلود فایل جدید:</label><input type="file" class="form-control-file form-control-sm" name="project_general_file" id="project_general_file_upload_id"><small class="form-text text-muted">مثال: کارت دعوت، رضایت نامه</small></div>
        <div class="form-group"><label for="file_description_pv_upload_id" class="small">توضیحات فایل:</label><input type="text" class="form-control form-control-sm" name="file_description_pv" id="file_description_pv_upload_id"></div>
        <button type="submit" name="submit_pv_event_file" class="btn btn-success btn-sm">آپلود پیوست</button>
    </form>
    <?php endif; ?>
    </div></div></div>
    <?php if(!$edit_mode_pv_evt): // Show list only if not in edit mode, or adjust layout for edit mode to show list too ?>
    <div class="col-lg-7"><div class="card shadow-sm"><div class="card-header"><span class="card-title-text">لیست پروژه‌ها (۵۰ اخیر)</span></div><div class="card-body">
    <?php if($pv_events_list_q_main && $pv_events_list_q_main->num_rows > 0): ?><div class="table-responsive"><table class="table table-sm table-striped table-hover">
        <thead><tr><th>#</th><th>پروژه</th><th>نوع</th><th>تاریخ</th><th>وضعیت</th><th>مسئول</th><th>عملیات</th></tr></thead><tbody>
        <?php $pv_row_idx_disp = 1; while($pv_e_item_disp = $pv_events_list_q_main->fetch_assoc()): ?><tr>
            <td><?php echo $pv_row_idx_disp++;?></td><td><strong><a href="events_general.php?edit_id=<?php echo $pv_e_item_disp['ProjectID'];?>"><?php echo htmlspecialchars($pv_e_item_disp['ProjectName']);?></a></strong></td><td><?php echo $project_type_options_pv_disp[$pv_e_item_disp['ProjectType']] ?? '-';?></td><td><small><?php echo $pv_e_item_disp['StartDate']?to_jalali($pv_e_item_disp['StartDate'],'yy/MM/dd'):'-';?> تا <?php echo $pv_e_item_disp['EndDate']?to_jalali($pv_e_item_disp['EndDate'],'yy/MM/dd'):'-';?></small></td>
            <td><span class="badge badge-<?php echo $status_badge_pv_evt_disp[$pv_e_item_disp['Status']] ?? 'light';?> p-1"><?php echo $project_status_options_pv_disp[$pv_e_item_disp['Status']] ?? '-';?></span></td>
            <td><small><?php echo htmlspecialchars($pv_e_item_disp['LeadUserName'] ?? '-');?></small></td>
            <td class="actions-cell"><a href="events_general.php?edit_id=<?php echo $pv_e_item_disp['ProjectID'];?>" class="btn btn-xs btn-warning" title="ویرایش"><svg class="icon" width="12" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a> <!-- Delete --></td>
            </tr><?php endwhile; ?></tbody></table></div>
    <?php else: ?><p class="text-muted">پروژه‌ای ثبت نشده.</p><?php endif; if($pv_events_list_q_main) $pv_events_list_q_main->close();?>
    </div></div></div>
    <?php endif; ?>
</div>
<link rel="stylesheet" href="https://unpkg.com/persian-datepicker@latest/dist/css/persian-datepicker.min.css"/>
<script src="https://unpkg.com/persian-datepicker@latest/dist/js/persian-datepicker.min.js"></script>
<script> document.addEventListener('DOMContentLoaded', function() { document.querySelectorAll(".persian-date-picker").forEach(function(el){ new persianDatepicker(el, { format: 'YYYY-MM-DD', autoClose: true, observer: true, calendar:{ persian: { locale: 'fa' } } });}); document.querySelectorAll('.alert .close').forEach(function(button){button.addEventListener('click', function(event){event.target.closest('.alert').style.display = 'none';});});});</script>
<style>.badge.p-1{padding:0.25em 0.4em !important; font-size:0.8em !important;} .btn-xs{padding: .1rem .3rem; font-size: .75rem;} .form-actions{margin-top:1.5rem;}</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
