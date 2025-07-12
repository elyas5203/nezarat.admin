<?php
require_once __DIR__ . '/../includes/header.php';

// Configurable Form IDs
if (!defined('SELF_ASSESSMENT_FORM_ID_MONITORING')) define('SELF_ASSESSMENT_FORM_ID_MONITORING', 1);
if (!defined('CLASS_OBSERVATION_FORM_ID_MONITORING')) define('CLASS_OBSERVATION_FORM_ID_MONITORING', 2);

$page_title_cs_page = "مشاهده پاسخ فرم‌های نظارت";
$form_errors_cs_page_display = [];
$submissions_list_display = [];

// Filters
$filter_form_id_cs_val = isset($_GET['form_id_filter']) ? (int)$_GET['form_id_filter'] : null;
$filter_class_id_cs_val = isset($_GET['class_id_filter']) ? (int)$_GET['class_id_filter'] : null;
$filter_user_id_cs_val = isset($_GET['user_id_filter']) ? (int)$_GET['user_id_filter'] : null;
$filter_date_from_cs_jalali_val = sanitize_input($_GET['date_from'] ?? '');
$filter_date_to_cs_jalali_val = sanitize_input($_GET['date_to'] ?? '');

$forms_for_filter_cs_list = [];
$available_classes_cs_list = [];
$available_users_cs_list = [];

if($conn){
    $form_ids_to_fetch_filter = [SELF_ASSESSMENT_FORM_ID_MONITORING, CLASS_OBSERVATION_FORM_ID_MONITORING];
    $in_clause_filter = implode(',', array_fill(0, count($form_ids_to_fetch_filter), '?'));
    $stmt_forms_filter_page = $conn->prepare("SELECT FormID, Title FROM Forms WHERE FormID IN ($in_clause_filter) ORDER BY Title");
    if($stmt_forms_filter_page){
        $stmt_forms_filter_page->bind_param(str_repeat('i', count($form_ids_to_fetch_filter)), ...$form_ids_to_fetch_filter);
        $stmt_forms_filter_page->execute();
        $res_forms_filter_page = $stmt_forms_filter_page->get_result();
        while($row_ff_page = $res_forms_filter_page->fetch_assoc()) $forms_for_filter_cs_list[] = $row_ff_page;
        $stmt_forms_filter_page->close();
    } else { $form_errors_cs_page_display['fetch_forms'] = "خطا بارگذاری انواع فرم: " . $conn->error;}

    $res_cls_cs_page = $conn->query("SELECT ClassID, ClassName, AcademicYear FROM Classes ORDER BY AcademicYear DESC, ClassName ASC");
    if($res_cls_cs_page) while($row_cls_page = $res_cls_cs_page->fetch_assoc()) $available_classes_cs_list[] = $row_cls_page;
    else { $form_errors_cs_page_display['fetch_classes'] = "خطا بارگذاری کلاس‌ها: " . $conn->error;}

    $res_users_cs_page = $conn->query("SELECT UserID, FirstName, LastName, Username FROM Users WHERE UserType != 'admin' AND IsActive = TRUE ORDER BY LastName, FirstName");
    if($res_users_cs_page) while($row_usr_page = $res_users_cs_page->fetch_assoc()) $available_users_cs_list[] = $row_usr_page;
    else { $form_errors_cs_page_display['fetch_users'] = "خطا بارگذاری کاربران: " . $conn->error;}


    $sql_cs_list_page = "SELECT fs.SubmissionID, fs.SubmittedAt, fs.RelatedClassID, fs.AnonymousSubmissionName,
                           f.FormID, f.Title as FormTitle,
                           u.Username as SubmitterUsername, u.FirstName as SubmitterFirstName, u.LastName as SubmitterLastName,
                           c.ClassName, c.AcademicYear
                    FROM FormSubmissions fs
                    JOIN Forms f ON fs.FormID = f.FormID
                    LEFT JOIN Users u ON fs.SubmittedByUserID = u.UserID
                    LEFT JOIN Classes c ON fs.RelatedClassID = c.ClassID
                    WHERE 1=1 ";
    $params_cs_list_page = []; $types_cs_list_page = "";

    if($filter_form_id_cs_val){ $sql_cs_list_page .= " AND fs.FormID = ?"; $params_cs_list_page[] = $filter_form_id_cs_val; $types_cs_list_page .= "i"; }
    else {
         $sql_cs_list_page .= " AND fs.FormID IN (?,?) "; // Default to monitoring forms
         $params_cs_list_page[] = SELF_ASSESSMENT_FORM_ID_MONITORING; $params_cs_list_page[] = CLASS_OBSERVATION_FORM_ID_MONITORING;
         $types_cs_list_page .= "ii";
    }
    if($filter_class_id_cs_val){ $sql_cs_list_page .= " AND fs.RelatedClassID = ?"; $params_cs_list_page[] = $filter_class_id_cs_val; $types_cs_list_page .= "i"; }
    if($filter_user_id_cs_val){ $sql_cs_list_page .= " AND fs.SubmittedByUserID = ?"; $params_cs_list_page[] = $filter_user_id_cs_val; $types_cs_list_page .= "i"; }

    if(!empty($filter_date_from_cs_jalali_val)){
        $date_from_greg_cs_page = to_gregorian_date_for_db($filter_date_from_cs_jalali_val);
        if($date_from_greg_cs_page){ $sql_cs_list_page .= " AND DATE(fs.SubmittedAt) >= ?"; $params_cs_list_page[] = $date_from_greg_cs_page; $types_cs_list_page .= "s"; }
        else $form_errors_cs_page_display['date_from'] = "فرمت تاریخ شروع نامعتبر.";
    }
    if(!empty($filter_date_to_cs_jalali_val)){
        $date_to_greg_cs_page = to_gregorian_date_for_db($filter_date_to_cs_jalali_val);
        if($date_to_greg_cs_page){ $sql_cs_list_page .= " AND DATE(fs.SubmittedAt) <= ?"; $params_cs_list_page[] = $date_to_greg_cs_page; $types_cs_list_page .= "s"; }
        else $form_errors_cs_page_display['date_to'] = "فرمت تاریخ پایان نامعتبر.";
    }

    $sql_cs_list_page .= " ORDER BY fs.SubmittedAt DESC";

    if(empty($form_errors_cs_page_display)){
        $stmt_cs_list_page = $conn->prepare($sql_cs_list_page);
        if($stmt_cs_list_page){
            if(!empty($params_cs_list_page)) $stmt_cs_list_page->bind_param($types_cs_list_page, ...$params_cs_list_page);
            if($stmt_cs_list_page->execute()){ $result_cs_list_page = $stmt_cs_list_page->get_result(); while($row_cs_page=$result_cs_list_page->fetch_assoc()) $submissions_list_display[]=$row_cs_page; }
            else $form_errors_cs_page_display['db_list'] = "خطا بارگذاری پاسخ‌ها: " . $stmt_cs_list_page->error;
            $stmt_cs_list_page->close();
        } else $form_errors_cs_page_display['db_prepare'] = "خطای آماده سازی لیست پاسخ‌ها: " . $conn->error;
    }
} else {
    $form_errors_cs_page_display['db_conn_page_main'] = "خطا در اتصال به پایگاه داده.";
}
?>
<div class="page-header">
    <h1><?php echo $page_title_cs_page; ?></h1>
    <div class="page-header-actions">
        <a href="index.php" class="btn btn-outline-secondary"><em class="bi bi-house-door icon"></em> داشبورد نظارت</a>
    </div>
</div>

<?php if(isset($_SESSION['action_success_monitoring'])):?><div class="alert alert-success alert-dismissible fade show"><button class="btn-close" data-bs-dismiss="alert"></button><?php echo $_SESSION['action_success_monitoring']; unset($_SESSION['action_success_monitoring']);?></div><?php endif;?>
<?php if(!empty($form_errors_cs_page_display)):?><div class="alert alert-danger alert-dismissible fade show"><strong>خطا:</strong><ul class="mb-0 ps-3"><?php foreach($form_errors_cs_page_display as $e_cs_p=>$e_msg_cs_p):echo "<li>".htmlspecialchars($e_msg_cs_p)."</li>";endforeach;?></ul><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif;?>

<div class="filter-search-bar card card-body mb-3">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-3"><label for="form_id_filter_cs_page_form" class="form-label">نوع فرم:</label><select name="form_id_filter" id="form_id_filter_cs_page_form" class="form-select form-select-sm"><option value="">همه فرم‌های نظارت</option><?php foreach($forms_for_filter_cs_list as $form_opt_f):?><option value="<?php echo $form_opt_f['FormID'];?>" <?php echo ($filter_form_id_cs_val==$form_opt_f['FormID'])?'selected':'';?>><?php echo htmlspecialchars($form_opt_f['Title']);?></option><?php endforeach;?></select></div>
        <div class="col-md-3"><label for="class_id_filter_cs_page_form" class="form-label">کلاس:</label><select name="class_id_filter" id="class_id_filter_cs_page_form" class="form-select form-select-sm"><option value="">همه کلاس‌ها</option><?php foreach($available_classes_cs_list as $cls_cs_opt_f):?><option value="<?php echo $cls_cs_opt_f['ClassID'];?>" <?php echo ($filter_class_id_cs_val==$cls_cs_opt_f['ClassID'])?'selected':'';?>><?php echo htmlspecialchars($cls_cs_opt_f['ClassName'].' ('.$cls_cs_opt_f['AcademicYear'].')');?></option><?php endforeach;?></select></div>
        <div class="col-md-3"><label for="user_id_filter_cs_page_form" class="form-label">ثبت کننده:</label><select name="user_id_filter" id="user_id_filter_cs_page_form" class="form-select form-select-sm"><option value="">همه کاربران</option><?php foreach($available_users_cs_list as $usr_cs_opt_f):?><option value="<?php echo $usr_cs_opt_f['UserID'];?>" <?php echo ($filter_user_id_cs_val==$usr_cs_opt_f['UserID'])?'selected':'';?>><?php echo htmlspecialchars(trim($usr_cs_opt_f['FirstName'].' '.$usr_cs_opt_f['LastName']).' (@'.$usr_cs_opt_f['Username'].')');?></option><?php endforeach;?></select></div>
        <div class="col-md-3"><label for="date_from_cs_page_form" class="form-label">از تاریخ:</label><input type="text" name="date_from" id="date_from_cs_page_form" class="form-control form-control-sm persian-datepicker" value="<?php echo htmlspecialchars($filter_date_from_cs_jalali_val);?>" placeholder="اختیاری"></div>
        <div class="col-md-3"><label for="date_to_cs_page_form" class="form-label">تا تاریخ:</label><input type="text" name="date_to" id="date_to_cs_page_form" class="form-control form-control-sm persian-datepicker" value="<?php echo htmlspecialchars($filter_date_to_cs_jalali_val);?>" placeholder="اختیاری"></div>
        <div class="col-md-auto"><button type="submit" class="btn btn-info btn-sm w-100 mt-3">اعمال فیلتر</button></div>
        <div class="col-md-auto"><a href="class_submissions.php" class="btn btn-secondary btn-sm w-100 mt-3">پاک کردن همه فیلترها</a></div>
    </form>
</div>

<div class="card">
    <div class="card-header"><span>لیست پاسخ‌های ثبت شده (<?php echo count($submissions_list_display); ?> مورد)</span></div>
    <div class="card-body">
    <?php if(empty($submissions_list_display)): ?>
        <p class="text-center text-muted py-3">هیچ پاسخی با این مشخصات یافت نشد.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-sm">
                <thead class="table-light">
                    <tr><th>#</th><th>عنوان فرم</th><th>کلاس مربوطه</th><th>ثبت کننده</th><th>تاریخ ثبت</th><th class="actions-column">مشاهده</th></tr>
                </thead>
                <tbody>
                <?php foreach($submissions_list_display as $idx_cs_l => $sub_l): ?>
                    <tr>
                        <td><?php echo $idx_cs_l + 1; ?></td>
                        <td><a href="<?php echo $admin_base_url; ?>/forms/view_submission.php?submission_id=<?php echo $sub_l['SubmissionID']; ?>"><?php echo htmlspecialchars($sub_l['FormTitle']); ?></a></td>
                        <td><?php echo htmlspecialchars($sub_l['ClassName'] ? $sub_l['ClassName'].' ('.$sub_l['AcademicYear'].')' : ($sub_l['RelatedClassID'] ? 'کلاس حذف شده (ID:'.$sub_l['RelatedClassID'].')' :'---')); ?></td>
                        <td><?php echo $sub_l['SubmitterUsername'] ? htmlspecialchars(trim($sub_l['SubmitterFirstName'].' '.$sub_l['SubmitterLastName']).' (@'.$sub_l['SubmitterUsername'].')') : ($sub_l['AnonymousSubmissionName'] ? htmlspecialchars($sub_l['AnonymousSubmissionName']).' (ناشناس)' : '---'); ?></td>
                        <td><?php echo to_jalali($sub_l['SubmittedAt'], 'yyyy/MM/dd HH:mm'); ?></td>
                        <td class="actions-cell">
                            <a href="<?php echo $admin_base_url; ?>/forms/view_submission.php?submission_id=<?php echo $sub_l['SubmissionID']; ?>" class="btn btn-sm btn-outline-primary" title="مشاهده جزئیات پاسخ"><em class="bi bi-eye-fill"></em></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <!-- TODO: Pagination if many submissions -->
    <?php endif; ?>
    </div>
</div>

<link rel="stylesheet" href="<?php echo get_base_url(); ?>assets/js/persian-datepicker/persian-datepicker.min.css"/>
<script src="<?php echo get_base_url(); ?>assets/js/jquery.min.js"></script>
<script src="<?php echo get_base_url(); ?>assets/js/persian-datepicker/persian-date.min.js"></script>
<script src="<?php echo get_base_url(); ?>assets/js/persian-datepicker/persian-datepicker.min.js"></script>
<script>
$(document).ready(function(){
    if($(".persian-datepicker").length){$(".persian-datepicker").persianDatepicker({format:'YYYY/MM/DD',autoClose:true,observer:true,initialValue:false});}
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
