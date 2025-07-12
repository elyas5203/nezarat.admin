<?php
require_once __DIR__ . '/../includes/header.php';

$action_pge_page = $_GET['action'] ?? 'list'; // list, create, edit, view
$event_id_pge_url_param = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

$pge_events_list_display = [];
$pge_event_data_for_form_display = null;
$form_errors_pge_page_display = [];
$page_title_pge_page_display = "مدیریت مناسبت‌های عمومی و اردوها";

$csrf_token_name_pge_form = 'parvareshi_general_event_form_action';
$csrf_token_pge_form_val = generate_csrf_token($csrf_token_name_pge_form);
$csrf_token_name_pge_delete = 'parvareshi_general_event_delete_action';
$csrf_token_pge_delete_val = generate_csrf_token($csrf_token_name_pge_delete);

// Define event types and statuses
$pge_event_types_map_display = [
    'public_ghadir' => 'جشن عمومی غدیر',
    'public_nime_shaban' => 'جشن عمومی نیمه شعبان',
    'public_shahadat' => 'مراسم عمومی شهادت/وفات',
    'camp_summer' => 'اردوی تابستانی',
    'camp_other' => 'اردوی متفرقه',
    'other_general' => 'سایر مراسمات عمومی',
    'educational_workshop' => 'کارگاه آموزشی عمومی'
];
$pge_event_statuses_map_display = [
    'planning' => 'در حال برنامه‌ریزی',
    'approved' => 'تصویب شده',
    'ongoing' => 'در حال برگزاری',
    'completed' => 'پایان یافته',
    'cancelled' => 'لغو شده',
    'archived' => 'بایگانی شده'
];
if (!function_exists('get_pge_status_badge_class_page')) {
    function get_pge_status_badge_class_page($status_key) {
        $sl_page = strtolower($status_key ?? '');
        if($sl_page=='completed')return'success';
        if($sl_page=='approved')return'primary';
        if($sl_page=='ongoing')return'info';
        if($sl_page=='cancelled')return'danger';
        if($sl_page=='planning')return'warning text-dark';
        return 'secondary';
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_pge_event'])) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '', $csrf_token_name_pge_form)) {
            $form_errors_pge_page_display['csrf'] = "خطای CSRF.";
        } else {
            $csrf_token_pge_form_val = regenerate_csrf_token($csrf_token_name_pge_form);
            $event_id_posted_pge = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;

            $event_name_pge = sanitize_input($_POST['event_name'] ?? '');
            $event_type_pge = sanitize_input($_POST['event_type'] ?? 'other_general');
            $event_date_start_jalali_pge = sanitize_input($_POST['event_date_start'] ?? '');
            $event_date_end_jalali_pge = sanitize_input($_POST['event_date_end'] ?? null);
            $location_pge = sanitize_input($_POST['location'] ?? null);
            $target_audience_pge = sanitize_input($_POST['target_audience_description'] ?? null);
            $budget_required_pge = !empty($_POST['budget_required']) ? filter_var(str_replace(',', '',$_POST['budget_required']), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : null;
            $actual_cost_pge = !empty($_POST['actual_cost']) ? filter_var(str_replace(',', '',$_POST['actual_cost']), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : null;
            $status_pge = sanitize_input($_POST['status'] ?? 'planning');
            $description_pge_form = sanitize_input($_POST['description'] ?? null);
            $project_proposal_path_pge = sanitize_input($_POST['project_proposal_path'] ?? null);
            $manpower_plan_path_pge = sanitize_input($_POST['manpower_plan_path'] ?? null);
            $report_path_pge = sanitize_input($_POST['report_path'] ?? null);

            $pge_event_data_for_form_display = $_POST;
            $pge_event_data_for_form_display['EventDateStart'] = $event_date_start_jalali_pge;
            $pge_event_data_for_form_display['EventDateEnd'] = $event_date_end_jalali_pge;

            if (empty($event_name_pge)) $form_errors_pge_page_display['event_name'] = "نام مراسم/اردو الزامی است.";
            if (empty($event_date_start_jalali_pge)) $form_errors_pge_page_display['event_date_start'] = "تاریخ شروع الزامی است.";
            $event_date_start_greg_pge = null;
            if(!empty($event_date_start_jalali_pge)){ $event_date_start_greg_pge = to_gregorian_date_for_db($event_date_start_jalali_pge); if(!$event_date_start_greg_pge) $form_errors_pge_page_display['event_date_start'] = "فرمت تاریخ شروع نامعتبر.";}

            $event_date_end_greg_pge = null;
            if(!empty($event_date_end_jalali_pge)){ $event_date_end_greg_pge = to_gregorian_date_for_db($event_date_end_jalali_pge); if(!$event_date_end_greg_pge) $form_errors_pge_page_display['event_date_end'] = "فرمت تاریخ پایان نامعتبر.";}

            if($event_date_start_greg_pge && $event_date_end_greg_pge && $event_date_end_greg_pge < $event_date_start_greg_pge) $form_errors_pge_page_display['event_date_end'] = "تاریخ پایان قبل از شروع است.";
            if(!array_key_exists($event_type_pge, $pge_event_types_map_display)) $form_errors_pge_page_display['event_type'] = "نوع مراسم نامعتبر.";


            if (empty($form_errors_pge_page_display)) {
                if ($conn) {
                    $current_admin_id_pge_save = get_current_user_id();
                    if ($event_id_posted_pge > 0) { // Update
                        $stmt_pge_save = $conn->prepare("UPDATE ParvareshiGeneralEvents SET EventName=?, EventType=?, EventDateStart=?, EventDateEnd=?, Location=?, TargetAudienceDescription=?, ProjectProposalPath=?, BudgetRequired=?, ActualCost=?, ManpowerPlanPath=?, ReportPath=?, Status=?, Description=?, UpdatedAt=NOW(), UpdatedByUserID=? WHERE GeneralEventID=?");
                        if($stmt_pge_save){
                            $stmt_pge_save->bind_param("sssssssdsssssii", $event_name_pge, $event_type_pge, $event_date_start_greg_pge, $event_date_end_greg_pge, $location_pge, $target_audience_pge, $project_proposal_path_pge, $budget_required_pge, $actual_cost_pge, $manpower_plan_path_pge, $report_path_pge, $status_pge, $description_pge_form, $current_admin_id_pge_save, $event_id_posted_pge);
                            if ($stmt_pge_save->execute()) { $_SESSION['action_success_parvareshi'] = "مراسم/اردو بروزرسانی شد."; header("Location: events_general.php"); exit; }
                            else $form_errors_pge_page_display['db'] = "خطا بروزرسانی: " . $stmt_pge_save->error; $stmt_pge_save->close();
                        } else $form_errors_pge_page_display['db'] = "خطا آماده سازی بروزرسانی: " . $conn->error;
                    } else { // Create
                        $stmt_pge_save = $conn->prepare("INSERT INTO ParvareshiGeneralEvents (EventName, EventType, EventDateStart, EventDateEnd, Location, TargetAudienceDescription, ProjectProposalPath, BudgetRequired, ManpowerPlanPath, Status, Description, CreatedAt, CreatedByUserID) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),?)");
                        if($stmt_pge_save){
                             $stmt_pge_save->bind_param("sssssssdssssi", $event_name_pge, $event_type_pge, $event_date_start_greg_pge, $event_date_end_greg_pge, $location_pge, $target_audience_pge, $project_proposal_path_pge, $budget_required_pge, $manpower_plan_path_pge, $status_pge, $description_pge_form, $current_admin_id_pge_save);
                            if ($stmt_pge_save->execute()) { $_SESSION['action_success_parvareshi'] = "مراسم/اردو ایجاد شد."; header("Location: events_general.php"); exit; }
                            else $form_errors_pge_page_display['db'] = "خطا ایجاد: " . $stmt_pge_save->error; $stmt_pge_save->close();
                        } else $form_errors_pge_page_display['db'] = "خطا آماده سازی ایجاد: " . $conn->error;
                    }
                } else $form_errors_pge_page_display['db'] = "عدم اتصال دیتابیس.";
            }
            $action_pge_page = ($event_id_posted_pge > 0) ? 'edit' : 'create';
        }
    }  elseif (isset($_POST['delete_pge_event_confirmed'])) {
        if (!verify_csrf_token($_POST['csrf_token_delete_modal_pge'] ?? '', $csrf_token_name_pge_delete)) {
            $_SESSION['action_error_parvareshi'] = "خطای CSRF.";
        } else {
            $csrf_token_pge_delete_val = regenerate_csrf_token($csrf_token_name_pge_delete);
            $event_id_to_delete_pge = (int)($_POST['event_id_to_delete_confirmed'] ?? 0);
            if ($event_id_to_delete_pge > 0 && $conn) {
                // TODO: Handle dependencies, e.g., if financial records are linked, or if recruitment prospects were linked to this general event.
                // For now, a simple delete. If RecruitmentProspects.RecruitmentEventID could point here, it needs handling.
                $stmt_del_pge = $conn->prepare("DELETE FROM ParvareshiGeneralEvents WHERE GeneralEventID = ?");
                if($stmt_del_pge){
                    $stmt_del_pge->bind_param("i", $event_id_to_delete_pge);
                    if ($stmt_del_pge->execute()) { $_SESSION['action_success_parvareshi'] = ($stmt_del_pge->affected_rows > 0) ? "مراسم/اردو حذف شد." : "مورد یافت نشد."; }
                    else {
                        if ($conn->errno == 1451) $_SESSION['action_error_parvareshi'] = "این مورد به دلیل وابستگی‌های دیگر (مثلا سوابق مالی) قابل حذف نیست.";
                        else $_SESSION['action_error_parvareshi'] = "خطا در حذف: " . $stmt_del_pge->error;
                    }
                    $stmt_del_pge->close();
                } else $_SESSION['action_error_parvareshi'] = "خطای آماده سازی حذف: " . $conn->error;
            } else $_SESSION['action_error_parvareshi'] = "شناسه نامعتبر.";
        }
        header("Location: events_general.php"); exit;
    }
}


if ($conn) {
    if ($action_pge_page === 'list') {
        $page_title_pge_page_display = "لیست مناسبت‌های عمومی و اردوها";
        $res_list_pge_page = $conn->query("SELECT GeneralEventID, EventName, EventType, EventDateStart, EventDateEnd, Status, BudgetRequired, ActualCost FROM ParvareshiGeneralEvents ORDER BY EventDateStart DESC");
        if ($res_list_pge_page) { while ($row_pge = $res_list_pge_page->fetch_assoc()) $pge_events_list_display[] = $row_pge; }
        else $form_errors_pge_page_display['db_list'] = "خطا بارگذاری لیست: " . $conn->error;
    } elseif (($action_pge_page === 'edit' || $action_pge_page === 'create') && !$pge_event_data_for_form_display) {
        if ($action_pge_page === 'edit' && $event_id_pge_url_param > 0) {
            $page_title_pge_page_display = "ویرایش مراسم/اردو";
            $stmt_pge_edit_page = $conn->prepare("SELECT * FROM ParvareshiGeneralEvents WHERE GeneralEventID = ?");
            if($stmt_pge_edit_page){
                $stmt_pge_edit_page->bind_param("i", $event_id_pge_url_param); $stmt_pge_edit_page->execute();
                $res_pge_edit_page = $stmt_pge_edit_page->get_result();
                if (!($pge_event_data_for_form_display = $res_pge_edit_page->fetch_assoc())) { $_SESSION['action_error_parvareshi'] = "مورد یافت نشد."; header("Location: events_general.php"); exit; }
                if (!empty($pge_event_data_for_form_display['EventDateStart'])) $pge_event_data_for_form_display['EventDateStart'] = to_jalali($pge_event_data_for_form_display['EventDateStart'], 'yyyy/MM/dd');
                if (!empty($pge_event_data_for_form_display['EventDateEnd'])) $pge_event_data_for_form_display['EventDateEnd'] = to_jalali($pge_event_data_for_form_display['EventDateEnd'], 'yyyy/MM/dd');
                $stmt_pge_edit_page->close();
            } else $form_errors_pge_page_display['db_load'] = "خطا بارگذاری: " . $conn->error;
        } else {
            $page_title_pge_page_display = "ایجاد مراسم/اردوی جدید";
            $pge_event_data_for_form_display = ['EventName'=>'','EventType'=>'other_general','EventDateStart'=>to_jalali(date('Y-m-d'),'yyyy/MM/dd'), 'EventDateEnd'=>'','Location'=>'','TargetAudienceDescription'=>'','ProjectProposalPath'=>'','BudgetRequired'=>'','ActualCost'=>'','ManpowerPlanPath'=>'','ReportPath'=>'','Status'=>'planning','Description'=>''];
        }
    }
} else $form_errors_pge_page_display['db_connection'] = "خطا اتصال دیتابیس.";
?>
<div class="page-header"><h1><?php echo $page_title_pge_page_display; ?></h1><div class="page-header-actions"><a href="events_general.php?action=<?php echo ($action_pge_page==='list'?'create':'list'); ?>" class="btn btn-<?php echo ($action_pge_page==='list'?'primary':'secondary');?>"><em class="bi <?php echo ($action_pge_page==='list'?'bi-calendar2-event':'bi-list-ul');?> icon"></em> <?php echo ($action_pge_page==='list'?'ایجاد مورد جدید':'لیست مراسم/اردوها');?></a><a href="index.php" class="btn btn-outline-secondary ms-2"><em class="bi bi-house-door icon"></em> داشبورد پرورشی</a></div></div>
<?php if(isset($_SESSION['action_success_parvareshi'])):?><div class="alert alert-success alert-dismissible fade show"><button class="btn-close" data-bs-dismiss="alert"></button><?php echo $_SESSION['action_success_parvareshi']; unset($_SESSION['action_success_parvareshi']);?></div><?php endif;?>
<?php if(isset($_SESSION['action_error_parvareshi'])):?><div class="alert alert-danger alert-dismissible fade show"><button class="btn-close" data-bs-dismiss="alert"></button><?php echo $_SESSION['action_error_parvareshi']; unset($_SESSION['action_error_parvareshi']);?></div><?php endif;?>
<?php if(!empty($form_errors_pge_page_display)):?><div class="alert alert-danger alert-dismissible fade show"><strong>خطا:</strong><ul class="mb-0 ps-3"><?php foreach($form_errors_pge_page_display as $e_pge_p=>$e_msg_pge_p):echo "<li>".htmlspecialchars($e_msg_pge_p)."</li>";endforeach;?></ul><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif;?>

<?php if($action_pge_page === 'list'): ?>
    <div class="card"><div class="card-body">
    <?php if(empty($pge_events_list_display)): ?><p class="text-center text-muted py-3">هیچ مراسم یا اردویی ثبت نشده.</p>
    <?php else: ?><div class="table-responsive"><table class="table table-hover table-sm">
        <thead class="table-light"><tr><th>#</th><th>نام</th><th>نوع</th><th>تاریخ شروع</th><th>تاریخ پایان</th><th>وضعیت</th><th>بودجه (تومان)</th><th class="actions-column">عملیات</th></tr></thead>
        <tbody><?php foreach($pge_events_list_display as $idx_pge_l => $pge_l): ?>
            <tr><td><?php echo $idx_pge_l+1;?></td><td><a href="?action=edit&event_id=<?php echo $pge_l['GeneralEventID'];?>"><?php echo htmlspecialchars($pge_l['EventName']);?></a></td><td><?php echo htmlspecialchars($pge_event_types_map_display[$pge_l['EventType']]??$pge_l['EventType']);?></td><td><?php echo to_jalali($pge_l['EventDateStart'],'yyyy/MM/dd');?></td><td><?php echo $pge_l['EventDateEnd']?to_jalali($pge_l['EventDateEnd'],'yyyy/MM/dd'):'---';?></td><td><span class="badge bg-<?php echo get_pge_status_badge_class_page($pge_l['Status']);?>"><?php echo $pge_event_statuses_map_display[$pge_l['Status']]??$pge_l['Status'];?></span></td><td><?php echo $pge_l['BudgetRequired'] ? number_format($pge_l['BudgetRequired']) : '---';?></td>
            <td class="actions-cell"><a href="?action=edit&event_id=<?php echo $pge_l['GeneralEventID'];?>" class="btn btn-sm btn-outline-info" title="ویرایش"><em class="bi bi-pencil-square"></em></a><button type="button" class="btn btn-sm btn-outline-danger btn-delete-pge-event" data-event-id="<?php echo $pge_l['GeneralEventID'];?>" data-event-name="<?php echo htmlspecialchars($pge_l['EventName']);?>"><em class="bi bi-trash3"></em></button></td></tr>
        <?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
    </div></div>
<?php elseif ($action_pge_page === 'create' || $action_pge_page === 'edit'): ?>
    <div class="card"><div class="card-body">
        <form method="POST" action="events_general.php<?php echo ($action_pge_page==='edit'&&$event_id_pge_url_param)?'?action=edit&event_id='.$event_id_pge_url_param:'?action=create';?>" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_pge_form_val; ?>">
            <?php if($action_pge_page==='edit'&&$event_id_pge_url_param):?><input type="hidden" name="event_id" value="<?php echo $event_id_pge_url_param;?>"><?php endif;?>
            <div class="row"><div class="col-md-7 mb-3"><label for="pge_f_name" class="form-label">نام مراسم/اردو <span class="text-danger">*</span></label><input type="text" class="form-control <?php echo isset($form_errors_pge_page_display['event_name'])?'is-invalid':'';?>" id="pge_f_name" name="event_name" value="<?php echo htmlspecialchars($pge_event_data_for_form_display['EventName']??'');?>" required><?php if(isset($form_errors_pge_page_display['event_name'])):?><div class="invalid-feedback"><?php echo $form_errors_pge_page_display['event_name'];?></div><?php endif;?></div>
            <div class="col-md-5 mb-3"><label for="pge_f_type" class="form-label">نوع <span class="text-danger">*</span></label><select class="form-select <?php echo isset($form_errors_pge_page_display['event_type'])?'is-invalid':'';?>" id="pge_f_type" name="event_type" required><?php foreach($pge_event_types_map_display as $etk_pge_opt=>$etv_pge_opt):?><option value="<?php echo $etk_pge_opt;?>" <?php echo (($pge_event_data_for_form_display['EventType']??'other_general')===$etk_pge_opt)?'selected':'';?>><?php echo $etv_pge_opt;?></option><?php endforeach;?></select><?php if(isset($form_errors_pge_page_display['event_type'])):?><div class="invalid-feedback"><?php echo $form_errors_pge_page_display['event_type'];?></div><?php endif;?></div></div>
            <div class="row"><div class="col-md-4 mb-3"><label for="pge_f_date_start" class="form-label">تاریخ شروع <span class="text-danger">*</span></label><input type="text" class="form-control persian-datepicker <?php echo isset($form_errors_pge_page_display['event_date_start'])?'is-invalid':'';?>" id="pge_f_date_start" name="event_date_start" value="<?php echo htmlspecialchars($pge_event_data_for_form_display['EventDateStart']??'');?>" required><?php if(isset($form_errors_pge_page_display['event_date_start'])):?><div class="invalid-feedback"><?php echo $form_errors_pge_page_display['event_date_start'];?></div><?php endif;?></div>
            <div class="col-md-4 mb-3"><label for="pge_f_date_end" class="form-label">تاریخ پایان</label><input type="text" class="form-control persian-datepicker <?php echo isset($form_errors_pge_page_display['event_date_end'])?'is-invalid':'';?>" id="pge_f_date_end" name="event_date_end" value="<?php echo htmlspecialchars($pge_event_data_for_form_display['EventDateEnd']??'');?>"><?php if(isset($form_errors_pge_page_display['event_date_end'])):?><div class="invalid-feedback"><?php echo $form_errors_pge_page_display['event_date_end'];?></div><?php endif;?></div>
            <div class="col-md-4 mb-3"><label for="pge_f_loc" class="form-label">مکان</label><input type="text" class="form-control" id="pge_f_loc" name="location" value="<?php echo htmlspecialchars($pge_event_data_for_form_display['Location']??'');?>"></div></div>
            <div class="mb-3"><label for="pge_f_target" class="form-label">مخاطبین</label><input type="text" class="form-control" id="pge_f_target" name="target_audience_description" value="<?php echo htmlspecialchars($pge_event_data_for_form_display['TargetAudienceDescription']??'');?>"></div>
            <div class="row"><div class="col-md-6 mb-3"><label for="pge_f_budget" class="form-label">بودجه مورد نیاز (تومان)</label><input type="text" class="form-control" id="pge_f_budget" name="budget_required" value="<?php echo isset($pge_event_data_for_form_display['BudgetRequired']) ? number_format($pge_event_data_for_form_display['BudgetRequired'],0,'',',') : '';?>" placeholder="مثال: 5,000,000"></div>
            <div class="col-md-6 mb-3"><label for="pge_f_actual_cost" class="form-label">هزینه واقعی (تومان)</label><input type="text" class="form-control" id="pge_f_actual_cost" name="actual_cost" value="<?php echo isset($pge_event_data_for_form_display['ActualCost']) ? number_format($pge_event_data_for_form_display['ActualCost'],0,'',',') : '';?>" placeholder="پس از اتمام وارد شود"></div></div>
            <div class="row"><div class="col-md-4 mb-3"><label for="pge_f_proposal" class="form-label">مسیر فایل پروپوزال</label><input type="text" class="form-control" id="pge_f_proposal" name="project_proposal_path" value="<?php echo htmlspecialchars($pge_event_data_for_form_display['ProjectProposalPath']??'');?>"><small class="form-text text-muted">در صورت وجود، لینک یا مسیر فایل را وارد کنید.</small></div>
            <div class="col-md-4 mb-3"><label for="pge_f_manpower" class="form-label">مسیر فایل برنامه نیروی انسانی</label><input type="text" class="form-control" id="pge_f_manpower" name="manpower_plan_path" value="<?php echo htmlspecialchars($pge_event_data_for_form_display['ManpowerPlanPath']??'');?>"></div>
            <div class="col-md-4 mb-3"><label for="pge_f_report" class="form-label">مسیر فایل گزارش نهایی</label><input type="text" class="form-control" id="pge_f_report" name="report_path" value="<?php echo htmlspecialchars($pge_event_data_for_form_display['ReportPath']??'');?>"></div></div>
            <div class="mb-3"><label for="pge_f_status" class="form-label">وضعیت <span class="text-danger">*</span></label><select class="form-select" id="pge_f_status" name="status" required><?php foreach($pge_event_statuses_map_display as $stk_pge_f=>$stv_pge_f):?><option value="<?php echo $stk_pge_f;?>" <?php echo (($pge_event_data_for_form_display['Status']??'planning')===$stk_pge_f)?'selected':'';?>><?php echo $stv_pge_f;?></option><?php endforeach;?></select></div>
            <div class="mb-3"><label for="pge_f_desc" class="form-label">توضیحات کلی</label><textarea class="form-control" id="pge_f_desc" name="description" rows="3"><?php echo htmlspecialchars($pge_event_data_for_form_display['Description']??'');?></textarea></div>
            <div class="form-actions"><button type="submit" name="save_pge_event" class="btn btn-success"><em class="bi bi-check-circle-fill icon"></em> ذخیره</button><a href="events_general.php" class="btn btn-outline-secondary">انصراف</a></div>
        </form>
    </div></div>
<?php endif; ?>

<div class="modal fade" id="deletePGEModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="events_general.php" id="deletePGEFormModal">
    <input type="hidden" name="csrf_token_delete_modal_pge" id="csrf_token_delete_modal_pge_input_val" value="">
    <input type="hidden" name="event_id_to_delete_confirmed" id="event_id_to_delete_modal_pge_input_val">
    <div class="modal-header"><h5 class="modal-title">تایید حذف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">آیا از حذف <strong id="pgeEventNameToDeleteModalVal"></strong> مطمئن هستید؟ <small class="text-danger d-block">توجه: این عمل ممکن است بر اطلاعات وابسته تاثیر بگذارد.</small></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button><button type="submit" name="delete_pge_event_confirmed" class="btn btn-danger">حذف</button></div>
    </form></div></div></div>
<link rel="stylesheet" href="<?php echo get_base_url(); ?>assets/js/persian-datepicker/persian-datepicker.min.css"/>
<script src="<?php echo get_base_url(); ?>assets/js/jquery.min.js"></script>
<script src="<?php echo get_base_url(); ?>assets/js/persian-datepicker/persian-date.min.js"></script>
<script src="<?php echo get_base_url(); ?>assets/js/persian-datepicker/persian-datepicker.min.js"></script>
<script>
$(document).ready(function(){
    if($(".persian-datepicker").length){$(".persian-datepicker").persianDatepicker({format:'YYYY/MM/DD',autoClose:true,observer:true,initialValue:false});}
    $('.btn-delete-pge-event').on('click',function(){$('#event_id_to_delete_modal_pge_input_val').val($(this).data('event-id'));$('#pgeEventNameToDeleteModalVal').text($(this).data('event-name'));$('#csrf_token_delete_modal_pge_input_val').val('<?php echo $csrf_token_pge_delete_val; ?>');new bootstrap.Modal(document.getElementById('deletePGEModal')).show();});
    // Script to format currency inputs
    $('input[name="budget_required"], input[name="actual_cost"]').on('keyup', function(event) {
        if (event.which >= 37 && event.which <= 40) return; // Allow arrow keys
        $(this).val(function(index, value) {
            return value.replace(/\D/g, "").replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        });
    });
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
