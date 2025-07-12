<?php
require_once __DIR__ . '/../includes/header.php'; // General admin header

$action = $_GET['action'] ?? 'list'; // list, create, edit, view_forms
$meeting_id_url = isset($_GET['meeting_id']) ? (int)$_GET['meeting_id'] : 0; // Renamed to avoid conflict
$class_id_filter_list = isset($_GET['class_id_filter']) ? (int)$_GET['class_id_filter'] : null;

$meetings_list_display = [];
$meeting_data_for_form_display = null;
$form_errors_parents_page = []; // Specific errors for this page
$page_title_parents_page = "مدیریت جلسات اولیا";

// CSRF Tokens
$csrf_token_name_parents_form = 'parents_meeting_form_action';
$csrf_token_parents_form_val = generate_csrf_token($csrf_token_name_parents_form);
$csrf_token_name_parents_delete = 'parents_meeting_delete_action';
$csrf_token_parents_delete_val = generate_csrf_token($csrf_token_name_parents_delete);

// Fetch available classes for dropdowns
$available_classes_parents_dd = [];
if($conn){
    $res_classes_dd = $conn->query("SELECT ClassID, ClassName, AcademicYear FROM Classes ORDER BY AcademicYear DESC, ClassName ASC");
    if($res_classes_dd) while($row_dd = $res_classes_dd->fetch_assoc()) $available_classes_parents_dd[] = $row_dd;
    else $form_errors_parents_page['fetch_classes'] = "خطا بارگذاری کلاس‌ها: " . $conn->error;
} else {
    $form_errors_parents_page['db_conn'] = "خطا اتصال دیتابیس.";
}

$meeting_statuses_display = ['planned' => 'برنامه‌ریزی شده', 'completed' => 'برگزار شده', 'cancelled' => 'لغو شده', 'postponed' => 'به تعویق افتاده'];
// Badge classes for meeting statuses
if (!function_exists('get_meeting_status_badge_class')) { // Prevent re-declaration if moved to global helpers
    function get_meeting_status_badge_class($status_key) {
        switch (strtolower($status_key ?? '')) {
            case 'completed': return 'success';
            case 'planned': return 'primary';
            case 'cancelled': return 'danger';
            case 'postponed': return 'warning text-dark';
            default: return 'secondary';
        }
    }
}


// Handle POST for create/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_meeting'])) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '', $csrf_token_name_parents_form)) {
            $form_errors_parents_page['csrf'] = "خطای CSRF.";
        } else {
            $csrf_token_parents_form_val = regenerate_csrf_token($csrf_token_name_parents_form);
            $meeting_id_posted_form = isset($_POST['meeting_id']) ? (int)$_POST['meeting_id'] : 0;

            $class_id_fk_form = isset($_POST['class_id']) ? (int)$_POST['class_id'] : null;
            $meeting_date_jalali_form = sanitize_input($_POST['meeting_date'] ?? '');
            $meeting_time_form = sanitize_input($_POST['meeting_time'] ?? null);
            $location_form = sanitize_input($_POST['location'] ?? null);
            $agenda_form = sanitize_input($_POST['agenda'] ?? null);
            $status_form = sanitize_input($_POST['status'] ?? 'planned');
            $observer_form_sub_id_form = !empty($_POST['observer_form_submission_id']) ? (int)$_POST['observer_form_submission_id'] : null;
            $teacher_form_sub_id_form = !empty($_POST['teacher_form_submission_id']) ? (int)$_POST['teacher_form_submission_id'] : null;

            $meeting_data_for_form_display = $_POST;
            $meeting_data_for_form_display['MeetingDate'] = $meeting_date_jalali_form;

            if (!$class_id_fk_form) $form_errors_parents_page['class_id'] = "انتخاب کلاس الزامی است.";
            if (empty($meeting_date_jalali_form)) $form_errors_parents_page['meeting_date'] = "تاریخ جلسه الزامی است.";
            $meeting_date_gregorian_form = null;
            if(!empty($meeting_date_jalali_form)){
                $meeting_date_gregorian_form = to_gregorian_date_for_db($meeting_date_jalali_form);
                if(!$meeting_date_gregorian_form) $form_errors_parents_page['meeting_date'] = "فرمت تاریخ نامعتبر.";
            }
            if (!empty($meeting_time_form) && !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $meeting_time_form)) $form_errors_parents_page['meeting_time'] = "فرمت ساعت نامعتبر."; else if(empty($meeting_time_form)) $meeting_time_form = null;

            if (empty($form_errors_parents_page)) {
                if ($conn) {
                    if ($meeting_id_posted_form > 0) { // Update
                        $stmt_pm_update = $conn->prepare("UPDATE ParentsMeetings SET ClassID=?, MeetingDate=?, MeetingTime=?, Location=?, Agenda=?, Status=?, ObserverFormSubmissionID=?, TeacherFormSubmissionID=?, UpdatedAt=NOW(), UpdatedByUserID=? WHERE MeetingID=?");
                        if($stmt_pm_update){
                            $current_admin_id_pm_update = get_current_user_id();
                            $stmt_pm_update->bind_param("isssssiiii", $class_id_fk_form, $meeting_date_gregorian_form, $meeting_time_form, $location_form, $agenda_form, $status_form, $observer_form_sub_id_form, $teacher_form_sub_id_form, $current_admin_id_pm_update, $meeting_id_posted_form);
                            if ($stmt_pm_update->execute()) { $_SESSION['action_success_parents'] = "جلسه اولیا بروزرسانی شد."; header("Location: meetings.php"); exit; }
                            else $form_errors_parents_page['db'] = "خطا در بروزرسانی: " . $stmt_pm_update->error;
                            $stmt_pm_update->close();
                        } else $form_errors_parents_page['db'] = "خطای آماده سازی بروزرسانی: " . $conn->error;
                    } else { // Create
                        $stmt_pm_create = $conn->prepare("INSERT INTO ParentsMeetings (ClassID, MeetingDate, MeetingTime, Location, Agenda, Status, ObserverFormSubmissionID, TeacherFormSubmissionID, CreatedAt, CreatedByUserID) VALUES (?,?,?,?,?,?,?,?,NOW(),?)");
                        if($stmt_pm_create){
                            $current_admin_id_pm_create = get_current_user_id();
                            $stmt_pm_create->bind_param("isssssiii", $class_id_fk_form, $meeting_date_gregorian_form, $meeting_time_form, $location_form, $agenda_form, $status_form, $observer_form_sub_id_form, $teacher_form_sub_id_form, $current_admin_id_pm_create);
                            if ($stmt_pm_create->execute()) { $_SESSION['action_success_parents'] = "جلسه اولیا ایجاد شد."; header("Location: meetings.php"); exit; }
                            else $form_errors_parents_page['db'] = "خطا در ایجاد: " . $stmt_pm_create->error;
                            $stmt_pm_create->close();
                        } else $form_errors_parents_page['db'] = "خطای آماده سازی ایجاد: " . $conn->error;
                    }
                } else $form_errors_parents_page['db'] = "عدم اتصال به پایگاه داده.";
            }
            $action = ($meeting_id_posted_form > 0) ? 'edit' : 'create';
        }
    }  elseif (isset($_POST['delete_meeting_confirmed'])) {
        if (!verify_csrf_token($_POST['csrf_token_delete_modal_pm'] ?? '', $csrf_token_name_parents_delete)) {
            $_SESSION['action_error_parents'] = "خطای CSRF.";
        } else {
            $csrf_token_parents_delete_val = regenerate_csrf_token($csrf_token_name_parents_delete);
            $meeting_id_to_delete_confirmed = (int)($_POST['meeting_id_to_delete_confirmed'] ?? 0);
            if ($meeting_id_to_delete_confirmed > 0 && $conn) {
                $stmt_del_pm = $conn->prepare("DELETE FROM ParentsMeetings WHERE MeetingID = ?");
                if($stmt_del_pm){
                    $stmt_del_pm->bind_param("i", $meeting_id_to_delete_confirmed);
                    if ($stmt_del_pm->execute()) { $_SESSION['action_success_parents'] = ($stmt_del_pm->affected_rows > 0) ? "جلسه حذف شد." : "جلسه یافت نشد."; }
                    else $_SESSION['action_error_parents'] = "خطا در حذف: " . $stmt_del_pm->error;
                    $stmt_del_pm->close();
                } else $_SESSION['action_error_parents'] = "خطای آماده سازی حذف: " . $conn->error;
            } else $_SESSION['action_error_parents'] = "شناسه نامعتبر.";
        }
        header("Location: meetings.php" . ($class_id_filter_list ? "?class_id_filter=".$class_id_filter_list : "")); exit;
    }
}


if ($conn) {
    if ($action === 'list') {
        $page_title_parents_page = "لیست جلسات اولیا";
        $sql_list_pm_page = "SELECT pm.MeetingID, pm.MeetingDate, pm.Status, pm.ObserverFormSubmissionID, pm.TeacherFormSubmissionID, c.ClassName, c.AcademicYear
                        FROM ParentsMeetings pm
                        JOIN Classes c ON pm.ClassID = c.ClassID
                        WHERE 1=1 ";
        $params_list_pm_page = []; $types_list_pm_page = "";
        if($class_id_filter_list){
            $sql_list_pm_page .= " AND pm.ClassID = ?";
            $params_list_pm_page[] = $class_id_filter_list; $types_list_pm_page .= "i";
        }
        $sql_list_pm_page .= " ORDER BY pm.MeetingDate DESC";

        $stmt_list_pm_page = $conn->prepare($sql_list_pm_page);
        if($stmt_list_pm_page){
            if(!empty($params_list_pm_page)) $stmt_list_pm_page->bind_param($types_list_pm_page, ...$params_list_pm_page);
            if($stmt_list_pm_page->execute()){ $result_list_pm_page = $stmt_list_pm_page->get_result(); while($row_pm=$result_list_pm_page->fetch_assoc()) $meetings_list_display[]=$row_pm; }
            else $form_errors_parents_page['db_list'] = "خطا بارگذاری لیست: " . $stmt_list_pm_page->error;
            $stmt_list_pm_page->close();
        } else $form_errors_parents_page['db_list'] = "خطای آماده سازی لیست: " . $conn->error;

    } elseif (($action === 'edit' || $action === 'create') && !$meeting_data_for_form_display) {
        if ($action === 'edit' && $meeting_id_url > 0) {
            $page_title_parents_page = "ویرایش جلسه اولیا";
            $stmt_pm_edit_page = $conn->prepare("SELECT * FROM ParentsMeetings WHERE MeetingID = ?");
            if($stmt_pm_edit_page){
                $stmt_pm_edit_page->bind_param("i", $meeting_id_url); $stmt_pm_edit_page->execute();
                $result_pm_edit_page = $stmt_pm_edit_page->get_result();
                if (!($meeting_data_for_form_display = $result_pm_edit_page->fetch_assoc())) { $_SESSION['action_error_parents'] = "جلسه یافت نشد."; header("Location: meetings.php"); exit; }
                if (!empty($meeting_data_for_form_display['MeetingDate'])) { $meeting_data_for_form_display['MeetingDate'] = to_jalali($meeting_data_for_form_display['MeetingDate'], 'yyyy/MM/dd'); }
                $stmt_pm_edit_page->close();
            } else $form_errors_parents_page['db_load'] = "خطا بارگذاری: " . $conn->error;
        } else { // create
            $page_title_parents_page = "ایجاد جلسه اولیا جدید";
            $default_class_id = $class_id_filter_list ?: null; // Pre-select class if filtered
            $meeting_data_for_form_display = ['ClassID'=>$default_class_id, 'MeetingDate'=>to_jalali(date('Y-m-d'),'yyyy/MM/dd'), 'MeetingTime'=>'16:00', 'Location'=>'', 'Agenda'=>'', 'Status'=>'planned', 'ObserverFormSubmissionID'=>null, 'TeacherFormSubmissionID'=>null];
        }
    }
} else {
    $form_errors_parents_page['db_connection_page'] = "خطا در اتصال به پایگاه داده.";
}
?>
<div class="page-header">
    <h1><?php echo $page_title_parents_page; ?></h1>
    <div class="page-header-actions">
        <a href="meetings.php?action=<?php echo ($action==='list'?'create':'list') . ($class_id_filter_list ? '&class_id_filter='.$class_id_filter_list : ''); ?>" class="btn btn-<?php echo ($action==='list'?'primary':'secondary');?>"><em class="bi <?php echo ($action==='list'?'bi-calendar2-plus':'bi-list-ul');?> icon"></em> <?php echo ($action==='list'?'ایجاد جلسه جدید':'لیست جلسات');?></a>
        <a href="<?php echo $admin_base_url; ?>/dashboard/index.php" class="btn btn-outline-secondary ms-2"><em class="bi bi-house-door icon"></em> داشبورد</a>
    </div>
</div>

<?php if(isset($_SESSION['action_success_parents'])):?><div class="alert alert-success alert-dismissible fade show"><button class="btn-close" data-bs-dismiss="alert"></button><?php echo $_SESSION['action_success_parents']; unset($_SESSION['action_success_parents']);?></div><?php endif;?>
<?php if(isset($_SESSION['action_error_parents'])):?><div class="alert alert-danger alert-dismissible fade show"><button class="btn-close" data-bs-dismiss="alert"></button><?php echo $_SESSION['action_error_parents']; unset($_SESSION['action_error_parents']);?></div><?php endif;?>
<?php if(!empty($form_errors_parents_page)):?><div class="alert alert-danger alert-dismissible fade show"><strong>خطا:</strong><ul class="mb-0 ps-3"><?php foreach($form_errors_parents_page as $e_key_p=>$e_msg_p):echo "<li>".htmlspecialchars($e_msg_p)."</li>";endforeach;?></ul><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif;?>

<?php if($action === 'list'): ?>
    <div class="filter-search-bar mb-3"><form method="GET" class="row g-2 align-items-center">
        <div class="col-md-6"><label for="class_id_filter_list_page" class="form-label visually-hidden">فیلتر کلاس:</label><select name="class_id_filter" id="class_id_filter_list_page" class="form-select form-select-sm"><option value="">نمایش جلسات همه کلاس‌ها</option><?php foreach($available_classes_parents_dd as $cls_f):?><option value="<?php echo $cls_f['ClassID'];?>" <?php echo (($class_id_filter_list??0)==$cls_f['ClassID'])?'selected':'';?>><?php echo htmlspecialchars($cls_f['ClassName'].' ('.$cls_f['AcademicYear'].')');?></option><?php endforeach;?></select></div>
        <div class="col-md-auto"><button type="submit" class="btn btn-info btn-sm">اعمال فیلتر</button></div>
        <?php if($class_id_filter_list):?><div class="col-md-auto"><a href="meetings.php" class="btn btn-secondary btn-sm">پاک کردن فیلتر</a></div><?php endif;?>
    </form></div>
    <div class="card"><div class="card-body">
    <?php if(empty($meetings_list_display)): ?><p class="text-center text-muted py-3">هیچ جلسه‌ای یافت نشد. <?php if(!$class_id_filter_list) echo '<a href="?action=create">یک جلسه جدید ایجاد کنید</a>.';?></p>
    <?php else: ?><div class="table-responsive"><table class="table table-hover table-sm">
        <thead class="table-light"><tr><th>#</th><th>کلاس</th><th>تاریخ جلسه</th><th>وضعیت</th><th>گزارش ناظر</th><th>گزارش مدرس</th><th class="actions-column">عملیات</th></tr></thead>
        <tbody><?php foreach($meetings_list_display as $idx_m_l => $m_l): ?>
            <tr><td><?php echo $idx_m_l+1;?></td><td><?php echo htmlspecialchars($m_l['ClassName'].' ('.$m_l['AcademicYear'].')');?></td><td><?php echo to_jalali($m_l['MeetingDate'],'yyyy/MM/dd');?></td><td><span class="badge bg-<?php echo get_meeting_status_badge_class($m_l['Status']);?>"><?php echo $meeting_statuses_display[$m_l['Status']]??$m_l['Status'];?></span></td>
            <td><?php echo $m_l['ObserverFormSubmissionID'] ? '<a href="'.$admin_base_url.'/forms/view_submission.php?submission_id='.$m_l['ObserverFormSubmissionID'].'" target="_blank" title="مشاهده گزارش ناظر"><em class="bi bi-check-circle-fill text-success"></em> ثبت شده</a>' : '<em class="bi bi-x-circle-fill text-danger"></em> ندارد';?></td>
            <td><?php echo $m_l['TeacherFormSubmissionID'] ? '<a href="'.$admin_base_url.'/forms/view_submission.php?submission_id='.$m_l['TeacherFormSubmissionID'].'" target="_blank" title="مشاهده گزارش مدرس"><em class="bi bi-check-circle-fill text-success"></em> ثبت شده</a>' : '<em class="bi bi-x-circle-fill text-danger"></em> ندارد';?></td>
            <td class="actions-cell">
                <a href="?action=edit&meeting_id=<?php echo $m_l['MeetingID'].($class_id_filter_list ? '&class_id_filter='.$class_id_filter_list : '');?>" class="btn btn-sm btn-outline-info" title="ویرایش"><em class="bi bi-pencil-square"></em></a>
                <button type="button" class="btn btn-sm btn-outline-danger btn-delete-pm" data-meeting-id="<?php echo $m_l['MeetingID'];?>" data-meeting-desc="<?php echo htmlspecialchars($m_l['ClassName'].' - '.to_jalali($m_l['MeetingDate'],'yy/MM/dd'));?>"><em class="bi bi-trash3"></em></button>
            </td></tr>
        <?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
    </div></div>
<?php elseif ($action === 'create' || $action === 'edit'): ?>
    <div class="card"><div class="card-body">
        <form method="POST" action="meetings.php<?php echo ($action==='edit'&&$meeting_id_url)?'?action=edit&meeting_id='.$meeting_id_url:'?action=create';?>" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_parents_form_val; ?>">
            <?php if($action==='edit'&&$meeting_id_url):?><input type="hidden" name="meeting_id" value="<?php echo $meeting_id_url;?>"><?php endif;?>
            <div class="row">
                <div class="col-md-6 mb-3"><label for="pm_f_class" class="form-label">کلاس مربوطه <span class="text-danger">*</span></label><select class="form-select <?php echo isset($form_errors_parents_page['class_id'])?'is-invalid':'';?>" id="pm_f_class" name="class_id" required><option value="">-- انتخاب کلاس --</option><?php foreach($available_classes_parents_dd as $cls_opt_f):?><option value="<?php echo $cls_opt_f['ClassID'];?>" <?php echo (($meeting_data_for_form_display['ClassID']??null)==$cls_opt_f['ClassID'])?'selected':'';?>><?php echo htmlspecialchars($cls_opt_f['ClassName'].' ('.$cls_opt_f['AcademicYear'].')');?></option><?php endforeach;?></select><?php if(isset($form_errors_parents_page['class_id'])):?><div class="invalid-feedback"><?php echo $form_errors_parents_page['class_id'];?></div><?php endif;?></div>
                <div class="col-md-6 mb-3"><label for="pm_f_status" class="form-label">وضعیت جلسه <span class="text-danger">*</span></label><select class="form-select" id="pm_f_status" name="status" required><?php foreach($meeting_statuses_display as $msk_f=>$msv_f):?><option value="<?php echo $msk_f;?>" <?php echo (($meeting_data_for_form_display['Status']??'planned')===$msk_f)?'selected':'';?>><?php echo $msv_f;?></option><?php endforeach;?></select></div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3"><label for="pm_f_date" class="form-label">تاریخ جلسه <span class="text-danger">*</span></label><input type="text" class="form-control persian-datepicker <?php echo isset($form_errors_parents_page['meeting_date'])?'is-invalid':'';?>" id="pm_f_date" name="meeting_date" value="<?php echo htmlspecialchars($meeting_data_for_form_display['MeetingDate']??'');?>" required><?php if(isset($form_errors_parents_page['meeting_date'])):?><div class="invalid-feedback"><?php echo $form_errors_parents_page['meeting_date'];?></div><?php endif;?></div>
                <div class="col-md-6 mb-3"><label for="pm_f_time" class="form-label">ساعت جلسه</label><input type="time" class="form-control <?php echo isset($form_errors_parents_page['meeting_time'])?'is-invalid':'';?>" id="pm_f_time" name="meeting_time" value="<?php echo htmlspecialchars($meeting_data_for_form_display['MeetingTime']??'');?>"><?php if(isset($form_errors_parents_page['meeting_time'])):?><div class="invalid-feedback"><?php echo $form_errors_parents_page['meeting_time'];?></div><?php endif;?></div>
            </div>
            <div class="mb-3"><label for="pm_f_loc" class="form-label">مکان جلسه</label><input type="text" class="form-control" id="pm_f_loc" name="location" value="<?php echo htmlspecialchars($meeting_data_for_form_display['Location']??'');?>"></div>
            <div class="mb-3"><label for="pm_f_agenda" class="form-label">دستور جلسه / موضوعات</label><textarea class="form-control" id="pm_f_agenda" name="agenda" rows="3"><?php echo htmlspecialchars($meeting_data_for_form_display['Agenda']??'');?></textarea></div>
            <fieldset class="border p-3 mb-3"><legend class="w-auto px-2 small">فرم‌های گزارش (اختیاری)</legend>
            <p class="text-muted small mb-2">شناسه پاسخ فرم‌های گزارش ناظر و مدرس را (پس از ثبت آنها در بخش فرم‌ها) در اینجا وارد کنید.</p>
            <div class="row">
                <div class="col-md-6 mb-3"><label for="pm_f_obs_form" class="form-label">شناسه پاسخ گزارش ناظر</label><input type="number" class="form-control" id="pm_f_obs_form" name="observer_form_submission_id" value="<?php echo htmlspecialchars($meeting_data_for_form_display['ObserverFormSubmissionID']??'');?>" placeholder="مثال: 123"></div>
                <div class="col-md-6 mb-3"><label for="pm_f_teach_form" class="form-label">شناسه پاسخ گزارش مدرس</label><input type="number" class="form-control" id="pm_f_teach_form" name="teacher_form_submission_id" value="<?php echo htmlspecialchars($meeting_data_for_form_display['TeacherFormSubmissionID']??'');?>" placeholder="مثال: 124"></div>
            </div></fieldset>
            <div class="form-actions"><button type="submit" name="save_meeting" class="btn btn-success"><em class="bi bi-check-circle-fill icon"></em> ذخیره جلسه</button><a href="meetings.php<?php echo ($class_id_filter_list ? "?class_id_filter=".$class_id_filter_list : ""); ?>" class="btn btn-outline-secondary">انصراف</a></div>
        </form>
    </div></div>
<?php endif; ?>

<div class="modal fade" id="deleteParentMeetingModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="meetings.php<?php echo ($class_id_filter_list ? "?class_id_filter=".$class_id_filter_list : ""); ?>" id="deleteParentMeetingFormModal">
    <input type="hidden" name="csrf_token_delete_modal_pm" id="csrf_token_delete_modal_pm_input_val" value="">
    <input type="hidden" name="meeting_id_to_delete_confirmed" id="meeting_id_to_delete_modal_input_val_pm">
    <div class="modal-header"><h5 class="modal-title">تایید حذف جلسه اولیا</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">آیا از حذف جلسه <strong id="parentMeetingDescToDeleteModalVal"></strong> مطمئن هستید؟</div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button><button type="submit" name="delete_meeting_confirmed" class="btn btn-danger">حذف</button></div>
    </form></div></div></div>

<link rel="stylesheet" href="<?php echo get_base_url(); ?>assets/js/persian-datepicker/persian-datepicker.min.css"/>
<script src="<?php echo get_base_url(); ?>assets/js/jquery.min.js"></script>
<script src="<?php echo get_base_url(); ?>assets/js/persian-datepicker/persian-date.min.js"></script>
<script src="<?php echo get_base_url(); ?>assets/js/persian-datepicker/persian-datepicker.min.js"></script>
<script>
$(document).ready(function(){
    if($(".persian-datepicker").length){$(".persian-datepicker").persianDatepicker({format:'YYYY/MM/DD',autoClose:true,observer:true,initialValue:false});}
    $('.btn-delete-pm').on('click',function(){
        $('#meeting_id_to_delete_modal_input_val_pm').val($(this).data('meeting-id'));
        $('#parentMeetingDescToDeleteModalVal').text($(this).data('meeting-desc'));
        $('#csrf_token_delete_modal_pm_input_val').val('<?php echo $csrf_token_parents_delete_val;?>');
        new bootstrap.Modal(document.getElementById('deleteParentMeetingModal')).show();
    });
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
