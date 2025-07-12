<?php
require_once __DIR__ . '/../includes/header.php';

$action_sr_page_display = $_GET['action'] ?? 'list';
$report_id_sr_url_param_display = isset($_GET['report_id']) ? (int)$_GET['report_id'] : 0;
$class_id_sr_url_param_display = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;

$reports_list_sr_display = [];
$report_data_for_form_sr_display = null;
$form_errors_sr_page_display = [];
$page_title_sr_page_display = "گزارشات فصلی عملکرد کلاس‌ها";

$csrf_token_name_sr_form = 'monitoring_seasonal_report_form_action';
$csrf_token_sr_form_val = generate_csrf_token($csrf_token_name_sr_form);
$csrf_token_name_sr_delete = 'monitoring_seasonal_report_delete_action';
$csrf_token_sr_delete_val = generate_csrf_token($csrf_token_name_sr_delete);

// Data for dropdowns
$available_classes_sr_list = [];
$academic_years_sr_list = [];
$seasons_sr_options = ['بهار'=>'بهار', 'تابستان'=>'تابستان', 'پاییز'=>'پاییز', 'زمستان'=>'زمستان']; // Key and value can be same for simplicity
$overall_statuses_sr_options = ['gray' => 'ارزیابی نشده', 'green' => 'سبز (عالی)', 'yellow' => 'زرد (نیاز به بهبود)', 'red' => 'قرمز (نیاز به اقدام فوری)'];
$parents_meeting_statuses_sr_options = ['pending' => 'در دست اقدام/نامشخص', 'held' => 'برگزار شده', 'not_held' => 'برگزار نشده'];


if($conn){
    $res_cls_sr_page = $conn->query("SELECT ClassID, ClassName, AcademicYear FROM Classes ORDER BY AcademicYear DESC, ClassName ASC");
    if($res_cls_sr_page) {
        while($row_cls_sr_page = $res_cls_sr_page->fetch_assoc()) {
            $available_classes_sr_list[] = $row_cls_sr_page;
            if(!in_array($row_cls_sr_page['AcademicYear'], $academic_years_sr_list)) $academic_years_sr_list[] = $row_cls_sr_page['AcademicYear'];
        }
        rsort($academic_years_sr_list); // Show most recent years first
    } else $form_errors_sr_page_display['fetch_classes'] = "خطا بارگذاری کلاس‌ها: " . $conn->error;
} else $form_errors_sr_page_display['db_conn'] = "خطا اتصال دیتابیس.";


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_seasonal_report'])) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '', $csrf_token_name_sr_form)) {
            $form_errors_sr_page_display['csrf'] = "خطای CSRF.";
        } else {
            $csrf_token_sr_form_val = regenerate_csrf_token($csrf_token_name_sr_form);
            $report_id_posted_sr = isset($_POST['report_id']) ? (int)$_POST['report_id'] : 0;

            $class_id_form_sr_val = isset($_POST['class_id']) ? (int)$_POST['class_id'] : null;
            $academic_year_form_sr_val = sanitize_input($_POST['academic_year'] ?? '');
            $season_form_sr_val = sanitize_input($_POST['season'] ?? '');
            $report_date_jalali_sr_val = sanitize_input($_POST['report_date'] ?? '');
            $parents_meeting_status_form_sr_val = sanitize_input($_POST['parents_meeting_status'] ?? 'pending');
            $content_summary_form_sr_val = sanitize_input($_POST['content_summary'] ?? null);
            $overall_class_status_form_sr_val = sanitize_input($_POST['overall_class_status'] ?? 'gray');
            $strengths_form_sr_val = sanitize_input($_POST['strengths'] ?? null);
            $areas_for_improvement_form_sr_val = sanitize_input($_POST['areas_for_improvement'] ?? null);
            $action_plan_form_sr_val = sanitize_input($_POST['action_plan'] ?? null);
            $admin_notes_form_sr_val = sanitize_input($_POST['admin_notes'] ?? null);

            $report_data_for_form_sr_display = $_POST;
            $report_data_for_form_sr_display['ReportDate'] = $report_date_jalali_sr_val;

            if (!$class_id_form_sr_val) $form_errors_sr_page_display['class_id'] = "انتخاب کلاس الزامی است.";
            if (empty($academic_year_form_sr_val)) $form_errors_sr_page_display['academic_year'] = "سال تحصیلی الزامی است.";
            if (empty($season_form_sr_val) || !array_key_exists($season_form_sr_val, $seasons_sr_options)) $form_errors_sr_page_display['season'] = "انتخاب فصل الزامی است.";
            if (empty($report_date_jalali_sr_val)) $form_errors_sr_page_display['report_date'] = "تاریخ گزارش الزامی است.";
            $report_date_greg_sr_val = null;
            if(!empty($report_date_jalali_sr_val)){ $report_date_greg_sr_val = to_gregorian_date_for_db($report_date_jalali_sr_val); if(!$report_date_greg_sr_val) $form_errors_sr_page_display['report_date'] = "فرمت تاریخ گزارش نامعتبر.";}
            if (!array_key_exists($overall_class_status_form_sr_val, $overall_statuses_sr_options)) $form_errors_sr_page_display['overall_class_status'] = "وضعیت کلی کلاس نامعتبر.";
            if (!array_key_exists($parents_meeting_status_form_sr_val, $parents_meeting_statuses_sr_options)) $form_errors_sr_page_display['parents_meeting_status'] = "وضعیت جلسه اولیا نامعتبر.";

            // Check for duplicate report (ClassID, AcademicYear, Season)
            if(empty($form_errors_sr_page_display) && $conn){
                $sql_check_dup_sr = "SELECT ReportID FROM SeasonalReports WHERE ClassID = ? AND AcademicYear = ? AND Season = ?";
                $params_check_dup_sr = [$class_id_form_sr_val, $academic_year_form_sr_val, $season_form_sr_val];
                $types_check_dup_sr = "iss";
                if($report_id_posted_sr > 0){ // Exclude self if editing
                    $sql_check_dup_sr .= " AND ReportID != ?";
                    $params_check_dup_sr[] = $report_id_posted_sr;
                    $types_check_dup_sr .= "i";
                }
                $stmt_check_dup_sr_page = $conn->prepare($sql_check_dup_sr);
                if($stmt_check_dup_sr_page){
                    $stmt_check_dup_sr_page->bind_param($types_check_dup_sr, ...$params_check_dup_sr);
                    $stmt_check_dup_sr_page->execute();
                    if($stmt_check_dup_sr_page->get_result()->num_rows > 0) $form_errors_sr_page_display['duplicate_report'] = "گزارش فصلی برای این کلاس، سال و فصل قبلا ثبت شده است.";
                    $stmt_check_dup_sr_page->close();
                } else $form_errors_sr_page_display['db'] = "خطای بررسی تکراری بودن گزارش: ".$conn->error;
            }


            if (empty($form_errors_sr_page_display)) {
                if ($conn) {
                    $current_user_id_sr_save_page = get_current_user_id();
                    if ($report_id_posted_sr > 0) { // Update
                        $stmt_sr_save_page = $conn->prepare("UPDATE SeasonalReports SET ClassID=?, AcademicYear=?, Season=?, ReportDate=?, ParentsMeetingStatus=?, ContentSummary=?, OverallClassStatus=?, Strengths=?, AreasForImprovement=?, ActionPlan=?, AdminNotes=?, UpdatedAt=NOW(), ReportedByUserID=? WHERE ReportID=?");
                        if($stmt_sr_save_page){
                            $stmt_sr_save_page->bind_param("issssssssssii", $class_id_form_sr_val, $academic_year_form_sr_val, $season_form_sr_val, $report_date_greg_sr_val, $parents_meeting_status_form_sr_val, $content_summary_form_sr_val, $overall_class_status_form_sr_val, $strengths_form_sr_val, $areas_for_improvement_form_sr_val, $action_plan_form_sr_val, $admin_notes_form_sr_val, $current_user_id_sr_save_page, $report_id_posted_sr);
                            if ($stmt_sr_save_page->execute()) { $_SESSION['action_success_monitoring'] = "گزارش فصلی بروزرسانی شد."; header("Location: seasonal_report.php"); exit; }
                            else $form_errors_sr_page_display['db'] = "خطا بروزرسانی: " . $stmt_sr_save_page->error; $stmt_sr_save_page->close();
                        } else $form_errors_sr_page_display['db'] = "خطا آماده سازی بروزرسانی: " . $conn->error;
                    } else { // Create
                        $stmt_sr_save_page = $conn->prepare("INSERT INTO SeasonalReports (ClassID, AcademicYear, Season, ReportDate, ParentsMeetingStatus, ContentSummary, OverallClassStatus, Strengths, AreasForImprovement, ActionPlan, AdminNotes, CreatedAt, ReportedByUserID) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),?)");
                        if($stmt_sr_save_page){
                             $stmt_sr_save_page->bind_param("isssssssssssi", $class_id_form_sr_val, $academic_year_form_sr_val, $season_form_sr_val, $report_date_greg_sr_val, $parents_meeting_status_form_sr_val, $content_summary_form_sr_val, $overall_class_status_form_sr_val, $strengths_form_sr_val, $areas_for_improvement_form_sr_val, $action_plan_form_sr_val, $admin_notes_form_sr_val, $current_user_id_sr_save_page);
                            if ($stmt_sr_save_page->execute()) { $_SESSION['action_success_monitoring'] = "گزارش فصلی ایجاد شد."; header("Location: seasonal_report.php"); exit; }
                            else $form_errors_sr_page_display['db'] = "خطا ایجاد: " . $stmt_sr_save_page->error; $stmt_sr_save_page->close();
                        } else $form_errors_sr_page_display['db'] = "خطا آماده سازی ایجاد: " . $conn->error;
                    }
                } else $form_errors_sr_page_display['db'] = "عدم اتصال دیتابیس.";
            }
            $action_sr_page_display = ($report_id_posted_sr > 0) ? 'edit' : 'create';
        }
    } elseif (isset($_POST['delete_seasonal_report_confirmed'])) {
        if (!verify_csrf_token($_POST['csrf_token_delete_modal_sr'] ?? '', $csrf_token_name_sr_delete)) {
            $_SESSION['action_error_monitoring'] = "خطای CSRF.";
        } else {
            $csrf_token_sr_delete_val = regenerate_csrf_token($csrf_token_name_sr_delete);
            $report_id_to_delete_sr_conf = (int)($_POST['report_id_to_delete_confirmed'] ?? 0);
            if ($report_id_to_delete_sr_conf > 0 && $conn) {
                $stmt_del_sr_page = $conn->prepare("DELETE FROM SeasonalReports WHERE ReportID = ?");
                if($stmt_del_sr_page){
                    $stmt_del_sr_page->bind_param("i", $report_id_to_delete_sr_conf);
                    if ($stmt_del_sr_page->execute()) { $_SESSION['action_success_monitoring'] = ($stmt_del_sr_page->affected_rows > 0) ? "گزارش فصلی حذف شد." : "گزارش یافت نشد."; }
                    else $_SESSION['action_error_monitoring'] = "خطا در حذف: " . $stmt_del_sr_page->error; $stmt_del_sr_page->close();
                } else $_SESSION['action_error_monitoring'] = "خطای آماده سازی حذف: " . $conn->error;
            } else $_SESSION['action_error_monitoring'] = "شناسه نامعتبر.";
        }
        header("Location: seasonal_report.php"); exit;
    }
}


if ($conn) {
    if ($action_sr_page_display === 'list') {
        $page_title_sr_page_display = "لیست گزارشات فصلی";
        $filter_sr_class_list = isset($_GET['filter_class'])?(int)$_GET['filter_class']:null;
        $filter_sr_ay_list = isset($_GET['filter_ay'])?sanitize_input($_GET['filter_ay']):null;
        $filter_sr_season_list = isset($_GET['filter_season'])?sanitize_input($_GET['filter_season']):null;


        $sql_list_sr_page_query = "SELECT sr.ReportID, sr.AcademicYear, sr.Season, sr.ReportDate, sr.OverallClassStatus, c.ClassName
                             FROM SeasonalReports sr JOIN Classes c ON sr.ClassID = c.ClassID WHERE 1=1 ";
        $params_list_sr_page = []; $types_list_sr_page = "";
        if($filter_sr_class_list){ $sql_list_sr_page_query .= " AND sr.ClassID = ?"; $params_list_sr_page[]=$filter_sr_class_list; $types_list_sr_page.="i"; }
        if($filter_sr_ay_list){ $sql_list_sr_page_query .= " AND sr.AcademicYear = ?"; $params_list_sr_page[]=$filter_sr_ay_list; $types_list_sr_page.="s"; }
        if($filter_sr_season_list && array_key_exists($filter_sr_season_list, $seasons_sr_options)){ $sql_list_sr_page_query .= " AND sr.Season = ?"; $params_list_sr_page[]=$filter_sr_season_list; $types_list_sr_page.="s"; }

        $sql_list_sr_page_query .= " ORDER BY sr.ReportDate DESC, c.ClassName ASC";

        $stmt_list_sr_page_exec = $conn->prepare($sql_list_sr_page_query);
        if($stmt_list_sr_page_exec){
            if(!empty($params_list_sr_page)) $stmt_list_sr_page_exec->bind_param($types_list_sr_page, ...$params_list_sr_page);
            if($stmt_list_sr_page_exec->execute()){ $result_list_sr_page_exec = $stmt_list_sr_page_exec->get_result(); while($row_sr_l=$result_list_sr_page_exec->fetch_assoc()) $reports_list_sr_display[]=$row_sr_l; }
            else $form_errors_sr_page_display['db_list'] = "خطا بارگذاری لیست: " . $stmt_list_sr_page_exec->error;
            $stmt_list_sr_page_exec->close();
        } else $form_errors_sr_page_display['db_list'] = "خطای آماده سازی لیست: " . $conn->error;

    } elseif (($action_sr_page_display === 'edit' || $action_sr_page_display === 'create') && !$report_data_for_form_sr_display) { // If not repopulating from POST error
        if ($action_sr_page_display === 'edit' && $report_id_sr_url_param > 0) {
            $page_title_sr_page_display = "ویرایش گزارش فصلی";
            $stmt_sr_edit_page_load = $conn->prepare("SELECT * FROM SeasonalReports WHERE ReportID = ?");
            if($stmt_sr_edit_page_load){
                $stmt_sr_edit_page_load->bind_param("i", $report_id_sr_url_param); $stmt_sr_edit_page_load->execute();
                $result_sr_edit_page_load = $stmt_sr_edit_page_load->get_result();
                if (!($report_data_for_form_sr_display = $result_sr_edit_page_load->fetch_assoc())) { $_SESSION['action_error_monitoring'] = "گزارش یافت نشد."; header("Location: seasonal_report.php"); exit; }
                if (!empty($report_data_for_form_sr_display['ReportDate'])) { $report_data_for_form_sr_display['ReportDate'] = to_jalali($report_data_for_form_sr_display['ReportDate'], 'yyyy/MM/dd'); }
                $stmt_sr_edit_page_load->close();
            } else $form_errors_sr_page_display['db_load'] = "خطا بارگذاری: " . $conn->error;
        } else {
            $page_title_sr_page_display = "ایجاد گزارش فصلی جدید";
            $current_ay_sr_page = !empty($academic_years_sr_list) ? $academic_years_sr_list[0] : '';
            $report_data_for_form_sr_display = ['ClassID'=>$class_id_sr_url_param_display, 'AcademicYear'=>$current_ay_sr_page, 'Season'=>'', 'ReportDate'=>to_jalali(date('Y-m-d'),'yyyy/MM/dd'), 'ParentsMeetingStatus'=>'pending', 'ContentSummary'=>'', 'OverallClassStatus'=>'gray', 'Strengths'=>'', 'AreasForImprovement'=>'', 'ActionPlan'=>'', 'AdminNotes'=>''];
        }
    }
} else $form_errors_sr_page_display['db_connection_main_page'] = "خطا اتصال دیتابیس.";
?>
<div class="page-header"><h1><?php echo $page_title_sr_page_display; ?></h1><div class="page-header-actions"><a href="seasonal_report.php?action=<?php echo ($action_sr_page_display==='list'?'create':'list'); ?>" class="btn btn-<?php echo ($action_sr_page_display==='list'?'primary':'secondary');?>"><em class="bi <?php echo ($action_sr_page_display==='list'?'bi-file-earmark-plus':'bi-list-ul');?> icon"></em> <?php echo ($action_sr_page_display==='list'?'ایجاد گزارش جدید':'لیست گزارشات');?></a><a href="index.php" class="btn btn-outline-secondary ms-2"><em class="bi bi-house-door icon"></em> داشبورد نظارت</a></div></div>
<?php if(isset($_SESSION['action_success_monitoring'])):?><div class="alert alert-success alert-dismissible fade show"><button class="btn-close" data-bs-dismiss="alert"></button><?php echo $_SESSION['action_success_monitoring']; unset($_SESSION['action_success_monitoring']);?></div><?php endif;?>
<?php if(isset($_SESSION['action_error_monitoring'])):?><div class="alert alert-danger alert-dismissible fade show"><button class="btn-close" data-bs-dismiss="alert"></button><?php echo $_SESSION['action_error_monitoring']; unset($_SESSION['action_error_monitoring']);?></div><?php endif;?>
<?php if(!empty($form_errors_sr_page_display)):?><div class="alert alert-danger alert-dismissible fade show"><strong>خطا:</strong><ul class="mb-0 ps-3"><?php foreach($form_errors_sr_page_display as $e_sr_p_d=>$e_msg_sr_p_d):echo "<li>".htmlspecialchars($e_msg_sr_p_d)."</li>";endforeach;?></ul><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif;?>

<?php if($action_sr_page_display === 'list'): ?>
    <div class="filter-search-bar mb-3"><form method="GET" class="row g-2 align-items-end">
        <div class="col-md-4"><label for="filter_class_sr_list_page" class="form-label">کلاس:</label><select name="filter_class" id="filter_class_sr_list_page" class="form-select form-select-sm"><option value="">همه</option><?php foreach($available_classes_sr_list as $cls_sr_f_p):?><option value="<?php echo $cls_sr_f_p['ClassID'];?>" <?php echo (($filter_sr_class_list??0)==$cls_sr_f_p['ClassID'])?'selected':'';?>><?php echo htmlspecialchars($cls_sr_f_p['ClassName'].' ('.$cls_sr_f_p['AcademicYear'].')');?></option><?php endforeach;?></select></div>
        <div class="col-md-3"><label for="filter_ay_sr_list_page" class="form-label">سال تحصیلی:</label><select name="filter_ay" id="filter_ay_sr_list_page" class="form-select form-select-sm"><option value="">همه</option><?php foreach($academic_years_sr_list as $ay_sr_f_p):?><option value="<?php echo $ay_sr_f_p;?>" <?php echo (($filter_sr_ay_list??'')===$ay_sr_f_p)?'selected':'';?>><?php echo $ay_sr_f_p;?></option><?php endforeach;?></select></div>
        <div class="col-md-2"><label for="filter_season_sr_list_page" class="form-label">فصل:</label><select name="filter_season" id="filter_season_sr_list_page" class="form-select form-select-sm"><option value="">همه</option><?php foreach($seasons_sr_options as $sk_sr_f=>$sv_sr_f):?><option value="<?php echo $sk_sr_f;?>" <?php echo (($filter_sr_season_list??'')===$sk_sr_f)?'selected':'';?>><?php echo $sv_sr_f;?></option><?php endforeach;?></select></div>
        <div class="col-md-auto"><button type="submit" class="btn btn-info btn-sm w-100">فیلتر</button></div>
        <?php if($filter_sr_class_list||!empty($filter_sr_ay_list)||!empty($filter_sr_season_list)):?><div class="col-md-auto"><a href="seasonal_report.php" class="btn btn-secondary btn-sm w-100">پاک کردن</a></div><?php endif;?>
    </form></div>
    <div class="card"><div class="card-body">
    <?php if(empty($reports_list_sr_display)): ?><p class="text-center text-muted py-3">هیچ گزارشی یافت نشد.</p>
    <?php else: ?><div class="table-responsive"><table class="table table-hover table-sm">
        <thead class="table-light"><tr><th>#</th><th>کلاس</th><th>سال تحصیلی-فصل</th><th>تاریخ گزارش</th><th>وضعیت کلی</th><th class="actions-column">عملیات</th></tr></thead>
        <tbody><?php foreach($reports_list_sr_display as $idx_sr_ld => $sr_ld): ?>
            <tr><td><?php echo $idx_sr_ld+1;?></td><td><a href="?action=edit&report_id=<?php echo $sr_ld['ReportID'];?>"><?php echo htmlspecialchars($sr_ld['ClassName']);?></a></td><td><?php echo htmlspecialchars($sr_ld['AcademicYear'].' - '.$sr_ld['Season']);?></td><td><?php echo to_jalali($sr_ld['ReportDate'],'yyyy/MM/dd');?></td><td><span class="badge bg-<?php echo get_pge_status_badge_class_page($sr_ld['OverallClassStatus']);?>"><?php echo $overall_statuses_sr_options[$sr_ld['OverallClassStatus']]??$sr_ld['OverallClassStatus'];?></span></td>
            <td class="actions-cell"><a href="?action=edit&report_id=<?php echo $sr_ld['ReportID'];?>" class="btn btn-sm btn-outline-info" title="ویرایش"><em class="bi bi-pencil-square"></em></a><button type="button" class="btn btn-sm btn-outline-danger btn-delete-sr" data-report-id="<?php echo $sr_ld['ReportID'];?>" data-report-desc="<?php echo htmlspecialchars('گزارش '.$sr_ld['Season'].' کلاس '.$sr_ld['ClassName'].' ('.$sr_ld['AcademicYear'].')');?>"><em class="bi bi-trash3"></em></button></td></tr>
        <?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
    </div></div>
<?php elseif ($action_sr_page_display === 'create' || $action_sr_page_display === 'edit'): ?>
    <div class="card"><div class="card-body">
        <form method="POST" action="seasonal_report.php<?php echo ($action_sr_page_display==='edit'&&$report_id_sr_url_param)?'?action=edit&report_id='.$report_id_sr_url_param:'?action=create';?>" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_sr_form_val; ?>">
            <?php if($action_sr_page_display==='edit'&&$report_id_sr_url_param):?><input type="hidden" name="report_id" value="<?php echo $report_id_sr_url_param;?>"><?php endif;?>
            <div class="row"><div class="col-md-4 mb-3"><label for="sr_f_class" class="form-label">کلاس <span class="text-danger">*</span></label><select class="form-select <?php echo isset($form_errors_sr_page_display['class_id'])?'is-invalid':'';?>" id="sr_f_class" name="class_id" required><option value="">-- انتخاب --</option><?php foreach($available_classes_sr_list as $cls_sr_opt_f):?><option value="<?php echo $cls_sr_opt_f['ClassID'];?>" <?php echo (($report_data_for_form_sr_display['ClassID']??null)==$cls_sr_opt_f['ClassID'])?'selected':'';?>><?php echo htmlspecialchars($cls_sr_opt_f['ClassName'].' ('.$cls_sr_opt_f['AcademicYear'].')');?></option><?php endforeach;?></select><?php if(isset($form_errors_sr_page_display['class_id'])):?><div class="invalid-feedback"><?php echo $form_errors_sr_page_display['class_id'];?></div><?php endif;?></div>
            <div class="col-md-3 mb-3"><label for="sr_f_ay" class="form-label">سال تحصیلی <span class="text-danger">*</span></label><select class="form-select <?php echo isset($form_errors_sr_page_display['academic_year'])?'is-invalid':'';?>" id="sr_f_ay" name="academic_year" required><option value="">-- انتخاب --</option><?php foreach($academic_years_sr_list as $ay_sr_opt_f):?><option value="<?php echo $ay_sr_opt_f;?>" <?php echo (($report_data_for_form_sr_display['AcademicYear']??'')===$ay_sr_opt_f)?'selected':'';?>><?php echo $ay_sr_opt_f;?></option><?php endforeach;?></select><?php if(isset($form_errors_sr_page_display['academic_year'])):?><div class="invalid-feedback"><?php echo $form_errors_sr_page_display['academic_year'];?></div><?php endif;?></div>
            <div class="col-md-2 mb-3"><label for="sr_f_season" class="form-label">فصل <span class="text-danger">*</span></label><select class="form-select <?php echo isset($form_errors_sr_page_display['season'])?'is-invalid':'';?>" id="sr_f_season" name="season" required><option value="">-- انتخاب --</option><?php foreach($seasons_sr_options as $s_sr_opt_k=>$s_sr_opt_v):?><option value="<?php echo $s_sr_opt_k;?>" <?php echo (($report_data_for_form_sr_display['Season']??'')===$s_sr_opt_k)?'selected':'';?>><?php echo $s_sr_opt_v;?></option><?php endforeach;?></select><?php if(isset($form_errors_sr_page_display['season'])):?><div class="invalid-feedback"><?php echo $form_errors_sr_page_display['season'];?></div><?php endif;?></div>
            <div class="col-md-3 mb-3"><label for="sr_f_rdate" class="form-label">تاریخ گزارش <span class="text-danger">*</span></label><input type="text" class="form-control persian-datepicker <?php echo isset($form_errors_sr_page_display['report_date'])?'is-invalid':'';?>" id="sr_f_rdate" name="report_date" value="<?php echo htmlspecialchars($report_data_for_form_sr_display['ReportDate']??'');?>" required><?php if(isset($form_errors_sr_page_display['report_date'])):?><div class="invalid-feedback"><?php echo $form_errors_sr_page_display['report_date'];?></div><?php endif;?></div></div>

            <div class="row"><div class="col-md-6 mb-3"><label for="sr_f_pm_status" class="form-label">وضعیت جلسه اولیا <span class="text-danger">*</span></label><select class="form-select <?php echo isset($form_errors_sr_page_display['parents_meeting_status'])?'is-invalid':'';?>" id="sr_f_pm_status" name="parents_meeting_status" required><?php foreach($parents_meeting_statuses_sr_options as $pms_k_f=>$pms_v_f):?><option value="<?php echo $pms_k_f;?>" <?php echo (($report_data_for_form_sr_display['ParentsMeetingStatus']??'pending')===$pms_k_f)?'selected':'';?>><?php echo $pms_v_f;?></option><?php endforeach;?></select><?php if(isset($form_errors_sr_page_display['parents_meeting_status'])):?><div class="invalid-feedback"><?php echo $form_errors_sr_page_display['parents_meeting_status'];?></div><?php endif;?></div>
            <div class="col-md-6 mb-3"><label for="sr_f_overall_status" class="form-label">وضعیت کلی کلاس (ارزیابی شما) <span class="text-danger">*</span></label><select class="form-select <?php echo isset($form_errors_sr_page_display['overall_class_status'])?'is-invalid':'';?>" id="sr_f_overall_status" name="overall_class_status" required><?php foreach($overall_statuses_sr_options as $os_k_f=>$os_v_f):?><option value="<?php echo $os_k_f;?>" <?php echo (($report_data_for_form_sr_display['OverallClassStatus']??'gray')===$os_k_f)?'selected':'';?>><?php echo $os_v_f;?></option><?php endforeach;?></select><?php if(isset($form_errors_sr_page_display['overall_class_status'])):?><div class="invalid-feedback"><?php echo $form_errors_sr_page_display['overall_class_status'];?></div><?php endif;?></div></div>

            <div class="mb-3"><label for="sr_f_content_sum" class="form-label">خلاصه محتوای تدریس شده (تیتروار)</label><textarea class="form-control" id="sr_f_content_sum" name="content_summary" rows="4"><?php echo htmlspecialchars($report_data_for_form_sr_display['ContentSummary']??'');?></textarea></div>
            <div class="mb-3"><label for="sr_f_strengths" class="form-label">نقاط قوت کلاس</label><textarea class="form-control" id="sr_f_strengths" name="strengths" rows="3"><?php echo htmlspecialchars($report_data_for_form_sr_display['Strengths']??'');?></textarea></div>
            <div class="mb-3"><label for="sr_f_improve" class="form-label">زمینه‌های نیازمند بهبود</label><textarea class="form-control" id="sr_f_improve" name="areas_for_improvement" rows="3"><?php echo htmlspecialchars($report_data_for_form_sr_display['AreasForImprovement']??'');?></textarea></div>
            <div class="mb-3"><label for="sr_f_action_plan" class="form-label">برنامه اقدام پیشنهادی</label><textarea class="form-control" id="sr_f_action_plan" name="action_plan" rows="3"><?php echo htmlspecialchars($report_data_for_form_sr_display['ActionPlan']??'');?></textarea></div>
            <div class="mb-3"><label for="sr_f_admin_notes" class="form-label">یادداشت‌های داخلی (اختیاری)</label><textarea class="form-control" id="sr_f_admin_notes" name="admin_notes" rows="2"><?php echo htmlspecialchars($report_data_for_form_sr_display['AdminNotes']??'');?></textarea></div>

            <div class="form-actions"><button type="submit" name="save_seasonal_report" class="btn btn-success"><em class="bi bi-check-circle-fill icon"></em> ذخیره گزارش</button><a href="seasonal_report.php" class="btn btn-outline-secondary">انصراف</a></div>
        </form>
    </div></div>
<?php endif; ?>

<div class="modal fade" id="deleteSRModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="seasonal_report.php" id="deleteSRFormModal">
    <input type="hidden" name="csrf_token_delete_modal_sr" id="csrf_token_delete_modal_sr_input_val" value="">
    <input type="hidden" name="report_id_to_delete_confirmed" id="report_id_to_delete_modal_input_sr_val">
    <div class="modal-header"><h5 class="modal-title">تایید حذف گزارش</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">آیا از حذف گزارش <strong id="srDescToDeleteModalVal"></strong> مطمئن هستید؟</div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button><button type="submit" name="delete_seasonal_report_confirmed" class="btn btn-danger">حذف</button></div>
    </form></div></div></div>
<link rel="stylesheet" href="<?php echo get_base_url(); ?>assets/js/persian-datepicker/persian-datepicker.min.css"/>
<script src="<?php echo get_base_url(); ?>assets/js/jquery.min.js"></script>
<script src="<?php echo get_base_url(); ?>assets/js/persian-datepicker/persian-date.min.js"></script>
<script src="<?php echo get_base_url(); ?>assets/js/persian-datepicker/persian-datepicker.min.js"></script>
<script>
$(document).ready(function(){
    if($(".persian-datepicker").length){$(".persian-datepicker").persianDatepicker({format:'YYYY/MM/DD',autoClose:true,observer:true,initialValue:false});}
    $('.btn-delete-sr').on('click',function(){$('#report_id_to_delete_modal_input_sr_val').val($(this).data('report-id'));$('#srDescToDeleteModalVal').text($(this).data('report-desc'));$('#csrf_token_delete_modal_sr_input_val').val('<?php echo $csrf_token_sr_delete_val; ?>');new bootstrap.Modal(document.getElementById('deleteSRModal')).show();});
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
