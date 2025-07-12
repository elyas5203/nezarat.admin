<?php
require_once __DIR__ . '/../includes/header.php';

$action = $_GET['action'] ?? 'list'; // list, create, edit
$prospect_id_url = isset($_GET['prospect_id']) ? (int)$_GET['prospect_id'] : 0;

$prospects_list = [];
$prospect_data_for_form = null;
$form_errors_prospect = [];
$page_title_prospect = "مدیریت افراد جذب شده";

// CSRF Tokens
$csrf_token_name_prospect_form = 'recruitment_prospect_form_action';
$csrf_token_prospect_form_val = generate_csrf_token($csrf_token_name_prospect_form);
$csrf_token_name_prospect_delete = 'recruitment_prospect_delete_action';
$csrf_token_prospect_delete_val = generate_csrf_token($csrf_token_name_prospect_delete);

// Fetch available regions and recruitment events for dropdowns
$available_regions_prospect = [];
$available_rec_events_prospect = [];
if($conn){
    $res_regions = $conn->query("SELECT RegionID, RegionName FROM RecruitmentRegions WHERE IsActive = TRUE ORDER BY RegionName");
    if($res_regions) while($row = $res_regions->fetch_assoc()) $available_regions_prospect[] = $row;
    else $form_errors_prospect['fetch_regions'] = "خطا در بارگذاری مناطق: " . $conn->error;

    $res_events = $conn->query("SELECT EventID, EventName, EventDate FROM RecruitmentEvents ORDER BY EventDate DESC");
    if($res_events) while($row = $res_events->fetch_assoc()) $available_rec_events_prospect[] = $row;
    else $form_errors_prospect['fetch_events'] = "خطا در بارگذاری مراسم جذب: " . $conn->error;
} else {
    $form_errors_prospect['db_connection_initial'] = "خطا در اتصال اولیه به پایگاه داده.";
}

$prospect_statuses = ['new'=>'جدید', 'contacted'=>'تماس گرفته شده', 'interested'=>'علاقه‌مند', 'not_interested'=>'عدم تمایل', 'enrolled'=>'ثبت نام شده در دوره/کلاس', 'other' => 'سایر'];
if(!function_exists('get_prospect_status_badge')){ function get_prospect_status_badge($s){$s_lower = strtolower($s??''); if($s_lower=='new')return'primary';if($s_lower=='contacted')return'info';if($s_lower=='interested')return'success';if($s_lower=='not_interested')return'warning text-dark';if($s_lower=='enrolled')return'purple';return'secondary';}}


// Handle POST for create/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_prospect'])) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '', $csrf_token_name_prospect_form)) {
            $form_errors_prospect['csrf'] = "خطای CSRF.";
        } else {
            $csrf_token_prospect_form_val = regenerate_csrf_token($csrf_token_name_prospect_form);
            $prospect_id_posted = isset($_POST['prospect_id']) ? (int)$_POST['prospect_id'] : 0;

            $prospect_name = sanitize_input($_POST['prospect_name'] ?? '');
            $parent_name = sanitize_input($_POST['parent_name'] ?? '');
            $phone_number = sanitize_input($_POST['phone_number'] ?? '');
            $introducer_name = sanitize_input($_POST['introducer_name'] ?? null);
            $region_id_fk = !empty($_POST['region_id']) ? (int)$_POST['region_id'] : null;
            $rec_event_id_fk = !empty($_POST['rec_event_id']) ? (int)$_POST['rec_event_id'] : null;
            $joined_date_jalali = sanitize_input($_POST['joined_date'] ?? '');
            $description_prospect = sanitize_input($_POST['description'] ?? null);
            $status_prospect = sanitize_input($_POST['status'] ?? 'new');

            $prospect_data_for_form = $_POST;
            $prospect_data_for_form['JoinedDate'] = $joined_date_jalali; // Keep Jalali for form

            if (empty($prospect_name)) $form_errors_prospect['prospect_name'] = "نام فرد الزامی است.";
            if (empty($phone_number)) $form_errors_prospect['phone_number'] = "شماره تماس الزامی است.";
            elseif (!preg_match('/^(\+98|0)?9\d{9}$/', $phone_number) && !preg_match('/^0\d{10}$/', $phone_number) ) { // Basic validation
                 $form_errors_prospect['phone_number'] = "فرمت شماره تماس نامعتبر است (مثال: 09123456789 یا 0513xxxxxxx).";
            }
            if (empty($joined_date_jalali)) $form_errors_prospect['joined_date'] = "تاریخ جذب الزامی است.";
            $joined_date_gregorian = null;
            if(!empty($joined_date_jalali)){
                $joined_date_gregorian = to_gregorian_date_for_db($joined_date_jalali);
                if(!$joined_date_gregorian) $form_errors_prospect['joined_date'] = "فرمت تاریخ جذب نامعتبر.";
            }

            if (empty($form_errors_prospect)) {
                if ($conn) {
                    if ($prospect_id_posted > 0) { // Update
                        $stmt = $conn->prepare("UPDATE RecruitmentProspects SET ProspectName=?, ParentName=?, PhoneNumber=?, IntroducerName=?, RegionID=?, RecruitmentEventID=?, JoinedDate=?, Description=?, Status=?, UpdatedAt=NOW() WHERE ProspectID=?");
                        if($stmt){
                            $stmt->bind_param("ssssiissis", $prospect_name, $parent_name, $phone_number, $introducer_name, $region_id_fk, $rec_event_id_fk, $joined_date_gregorian, $description_prospect, $status_prospect, $prospect_id_posted);
                            if ($stmt->execute()) { $_SESSION['action_success_recruitment'] = "اطلاعات فرد بروزرسانی شد."; header("Location: prospects.php"); exit; }
                            else $form_errors_prospect['db'] = "خطا در بروزرسانی: " . $stmt->error;
                            $stmt->close();
                        } else $form_errors_prospect['db'] = "خطای آماده سازی بروزرسانی: " . $conn->error;
                    } else { // Create
                        $stmt = $conn->prepare("INSERT INTO RecruitmentProspects (ProspectName, ParentName, PhoneNumber, IntroducerName, RegionID, RecruitmentEventID, JoinedDate, Description, Status, CreatedAt, CreatedByUserID) VALUES (?,?,?,?,?,?,?,?,?,NOW(),?)");
                        if($stmt){
                            $current_admin_id_prospect = get_current_user_id();
                            $stmt->bind_param("ssssiissisi", $prospect_name, $parent_name, $phone_number, $introducer_name, $region_id_fk, $rec_event_id_fk, $joined_date_gregorian, $description_prospect, $status_prospect, $current_admin_id_prospect);
                            if ($stmt->execute()) { $_SESSION['action_success_recruitment'] = "فرد جدید ثبت شد."; header("Location: prospects.php"); exit; }
                            else $form_errors_prospect['db'] = "خطا در ایجاد: " . $stmt->error;
                            $stmt->close();
                        } else $form_errors_prospect['db'] = "خطای آماده سازی ایجاد: " . $conn->error;
                    }
                } else $form_errors_prospect['db'] = "عدم اتصال به پایگاه داده.";
            }
            $action = ($prospect_id_posted > 0) ? 'edit' : 'create';
        }
    } elseif (isset($_POST['delete_prospect_confirmed'])) {
        if (!verify_csrf_token($_POST['csrf_token_delete_modal_prospect'] ?? '', $csrf_token_name_prospect_delete)) {
            $_SESSION['action_error_recruitment'] = "خطای CSRF.";
        } else {
            $csrf_token_prospect_delete_val = regenerate_csrf_token($csrf_token_name_prospect_delete);
            $prospect_id_to_delete = (int)($_POST['prospect_id_to_delete_confirmed'] ?? 0);
            if ($prospect_id_to_delete > 0 && $conn) {
                $stmt_del = $conn->prepare("DELETE FROM RecruitmentProspects WHERE ProspectID = ?");
                if($stmt_del){
                    $stmt_del->bind_param("i", $prospect_id_to_delete);
                    if ($stmt_del->execute()) { $_SESSION['action_success_recruitment'] = ($stmt_del->affected_rows > 0) ? "فرد حذف شد." : "فرد یافت نشد."; }
                    else $_SESSION['action_error_recruitment'] = "خطا در حذف: " . $stmt_del->error;
                    $stmt_del->close();
                } else $_SESSION['action_error_recruitment'] = "خطای آماده سازی حذف: " . $conn->error;
            } else $_SESSION['action_error_recruitment'] = "شناسه نامعتبر.";
        }
        header("Location: prospects.php"); exit;
    }
}


if ($conn) {
    if ($action === 'list') {
        $page_title_prospect = "لیست افراد جذب شده";
        $search_p_name_list = sanitize_input($_GET['search_name'] ?? '');
        $filter_p_region_list = isset($_GET['filter_region']) ? (int)$_GET['filter_region'] : null;
        $filter_p_event_list = isset($_GET['filter_event']) ? (int)$_GET['filter_event'] : null;
        $filter_p_status_list = sanitize_input($_GET['filter_status'] ?? '');


        $sql_list_p = "SELECT rp.*, rr.RegionName, re.EventName as RecruitmentEventName
                       FROM RecruitmentProspects rp
                       LEFT JOIN RecruitmentRegions rr ON rp.RegionID = rr.RegionID
                       LEFT JOIN RecruitmentEvents re ON rp.RecruitmentEventID = re.EventID
                       WHERE 1=1 ";
        $params_list_p = []; $types_list_p = "";

        if(!empty($search_p_name_list)){
            $sql_list_p .= " AND (rp.ProspectName LIKE ? OR rp.ParentName LIKE ? OR rp.PhoneNumber LIKE ? OR rp.IntroducerName LIKE ?)";
            $like_s_p_list = "%".$search_p_name_list."%";
            array_push($params_list_p, $like_s_p_list, $like_s_p_list, $like_s_p_list, $like_s_p_list); $types_list_p .= "ssss";
        }
        if($filter_p_region_list){ $sql_list_p .= " AND rp.RegionID = ?"; $params_list_p[] = $filter_p_region_list; $types_list_p .= "i"; }
        if($filter_p_event_list){ $sql_list_p .= " AND rp.RecruitmentEventID = ?"; $params_list_p[] = $filter_p_event_list; $types_list_p .= "i"; }
        if(!empty($filter_p_status_list) && array_key_exists($filter_p_status_list, $prospect_statuses)){ $sql_list_p .= " AND rp.Status = ?"; $params_list_p[] = $filter_p_status_list; $types_list_p .= "s"; }

        $sql_list_p .= " ORDER BY rp.JoinedDate DESC, rp.ProspectName ASC";

        $stmt_list_p = $conn->prepare($sql_list_p);
        if($stmt_list_p){
            if(!empty($params_list_p)) $stmt_list_p->bind_param($types_list_p, ...$params_list_p);
            if($stmt_list_p->execute()){ $result_list_p = $stmt_list_p->get_result(); while($row=$result_list_p->fetch_assoc()) $prospects_list[]=$row; }
            else $form_errors_prospect['db_list'] = "خطا در بارگذاری: " . $stmt_list_p->error;
            $stmt_list_p->close();
        } else $form_errors_prospect['db_list'] = "خطای آماده سازی: " . $conn->error;

    } elseif (($action === 'edit' || $action === 'create') && !$prospect_data_for_form) { // If not repopulating from POST error
        if ($action === 'edit' && $prospect_id_url > 0) {
            $page_title_prospect = "ویرایش اطلاعات فرد جذب شده";
            $stmt_p_edit = $conn->prepare("SELECT * FROM RecruitmentProspects WHERE ProspectID = ?");
            if($stmt_p_edit){
                $stmt_p_edit->bind_param("i", $prospect_id_url); $stmt_p_edit->execute();
                $result_p_edit = $stmt_p_edit->get_result();
                if (!($prospect_data_for_form = $result_p_edit->fetch_assoc())) { $_SESSION['action_error_recruitment'] = "فرد یافت نشد."; header("Location: prospects.php"); exit; }
                if (!empty($prospect_data_for_form['JoinedDate'])) { $prospect_data_for_form['JoinedDate'] = to_jalali($prospect_data_for_form['JoinedDate'], 'yyyy/MM/dd'); }
                $stmt_p_edit->close();
            } else $form_errors_prospect['db_load'] = "خطا بارگذاری: " . $conn->error;
        } else { // create
            $page_title_prospect = "ثبت فرد جذب شده جدید";
            $prospect_data_for_form = ['ProspectName'=>'','ParentName'=>'','PhoneNumber'=>'','IntroducerName'=>null,'RegionID'=>null,'RecruitmentEventID'=>null,'JoinedDate'=>to_jalali(date('Y-m-d'), 'yyyy/MM/dd'),'Description'=>null,'Status'=>'new'];
        }
    }
} else {
    $form_errors_prospect['db_connection'] = "خطا در اتصال به پایگاه داده.";
}
?>
<div class="page-header">
    <h1><?php echo $page_title_prospect; ?></h1>
    <div class="page-header-actions">
        <a href="prospects.php?action=<?php echo ($action==='list'?'create':'list'); ?>" class="btn btn-<?php echo ($action==='list'?'primary':'secondary');?>"><em class="bi <?php echo ($action==='list'?'bi-person-plus-fill':'bi-list-ul');?> icon"></em> <?php echo ($action==='list'?'ثبت فرد جدید':'لیست افراد');?></a>
        <a href="index.php" class="btn btn-outline-secondary ms-2"><em class="bi bi-house-door icon"></em> داشبورد جذب</a>
    </div>
</div>

<?php if(isset($_SESSION['action_success_recruitment'])):?><div class="alert alert-success alert-dismissible fade show"><button class="btn-close" data-bs-dismiss="alert"></button><?php echo $_SESSION['action_success_recruitment']; unset($_SESSION['action_success_recruitment']);?></div><?php endif;?>
<?php if(!empty($form_errors_prospect)):?><div class="alert alert-danger alert-dismissible fade show"><strong>خطا:</strong><ul class="mb-0 ps-3"><?php foreach($form_errors_prospect as $e_key=>$e_msg):echo "<li>".htmlspecialchars($e_msg)."</li>";endforeach;?></ul><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif;?>

<?php if($action === 'list'): ?>
    <div class="filter-search-bar mb-3"><form method="GET" class="row g-2 align-items-center">
        <div class="col-md-3"><input type="text" class="form-control form-control-sm" name="search_name" placeholder="جستجو نام، والدین، شماره، معرف..." value="<?php echo htmlspecialchars($search_p_name_list ?? '');?>"></div>
        <div class="col-md-2"><select name="filter_region" class="form-select form-select-sm"><option value="">همه مناطق</option><?php foreach($available_regions_prospect as $r_f):?><option value="<?php echo $r_f['RegionID'];?>" <?php echo (($filter_p_region_list??0)==$r_f['RegionID'])?'selected':'';?>><?php echo htmlspecialchars($r_f['RegionName']);?></option><?php endforeach;?></select></div>
        <div class="col-md-3"><select name="filter_event" class="form-select form-select-sm"><option value="">همه مراسم‌ها</option><?php foreach($available_rec_events_prospect as $ev_f):?><option value="<?php echo $ev_f['EventID'];?>" <?php echo (($filter_p_event_list??0)==$ev_f['EventID'])?'selected':'';?>><?php echo htmlspecialchars($ev_f['EventName'].' ('.to_jalali($ev_f['EventDate'],'yy/MM/dd').')');?></option><?php endforeach;?></select></div>
        <div class="col-md-2"><select name="filter_status" class="form-select form-select-sm"><option value="">همه وضعیت‌ها</option><?php foreach($prospect_statuses as $psk=>$psv):?><option value="<?php echo $psk;?>" <?php echo (($filter_p_status_list??'')===$psk)?'selected':'';?>><?php echo $psv;?></option><?php endforeach;?></select></div>
        <div class="col-md-auto"><button type="submit" class="btn btn-info btn-sm">فیلتر</button></div>
        <?php if(!empty($search_p_name_list)||!empty($filter_p_region_list)||!empty($filter_p_event_list)||!empty($filter_p_status_list)):?><div class="col-md-auto"><a href="prospects.php" class="btn btn-secondary btn-sm">پاک کردن</a></div><?php endif;?>
    </form></div>
    <div class="card"><div class="card-body">
    <?php if(empty($prospects_list)): ?><p class="text-center text-muted py-3">هیچ فردی با این مشخصات یافت نشد. <?php if(empty($search_p_name_list)&&empty($filter_p_region_list)&&empty($filter_p_event_list)&&empty($filter_p_status_list)) echo '<a href="?action=create">یک فرد جدید ثبت کنید</a>.';?></p>
    <?php else: ?><div class="table-responsive"><table class="table table-hover table-sm">
        <thead class="table-light"><tr><th>#</th><th>نام فرد</th><th>والدین</th><th>شماره</th><th>منطقه</th><th>مراسم جذب</th><th>تاریخ جذب</th><th>معرف</th><th>وضعیت</th><th class="actions-column">عملیات</th></tr></thead>
        <tbody><?php foreach($prospects_list as $idx_p_l => $p_l): ?>
            <tr><td><?php echo $idx_p_l+1;?></td><td><a href="?action=edit&prospect_id=<?php echo $p_l['ProspectID'];?>"><?php echo htmlspecialchars($p_l['ProspectName']);?></a></td><td><?php echo htmlspecialchars($p_l['ParentName']?:'---');?></td><td><?php echo htmlspecialchars($p_l['PhoneNumber']);?></td><td><?php echo htmlspecialchars($p_l['RegionName']?:'---');?></td><td><?php echo htmlspecialchars($p_l['RecruitmentEventName']?:'---');?></td><td><?php echo to_jalali($p_l['JoinedDate'],'yy/MM/dd');?></td><td><?php echo htmlspecialchars($p_l['IntroducerName']?:'---');?></td><td><span class="badge bg-<?php echo get_prospect_status_badge($p_l['Status']);?>"><?php echo $prospect_statuses[$p_l['Status']]??$p_l['Status'];?></span></td>
            <td class="actions-cell"><a href="?action=edit&prospect_id=<?php echo $p_l['ProspectID'];?>" class="btn btn-sm btn-outline-info" title="ویرایش"><em class="bi bi-pencil-square"></em></a><button type="button" class="btn btn-sm btn-outline-danger btn-delete-prospect" data-prospect-id="<?php echo $p_l['ProspectID'];?>" data-prospect-name="<?php echo htmlspecialchars($p_l['ProspectName']);?>"><em class="bi bi-trash3"></em></button></td></tr>
        <?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
    </div></div>
<?php elseif ($action === 'create' || $action === 'edit'): ?>
    <div class="card"><div class="card-body">
        <form method="POST" action="prospects.php<?php echo ($action==='edit'&&$prospect_id_url)?'?action=edit&prospect_id='.$prospect_id_url:'?action=create';?>" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_prospect_form_val; ?>">
            <?php if($action==='edit'&&$prospect_id_url):?><input type="hidden" name="prospect_id" value="<?php echo $prospect_id_url;?>"><?php endif;?>
            <div class="row">
                <div class="col-md-6 mb-3"><label for="p_f_name" class="form-label">نام و نام خانوادگی فرد <span class="text-danger">*</span></label><input type="text" class="form-control <?php echo isset($form_errors_prospect['prospect_name'])?'is-invalid':'';?>" id="p_f_name" name="prospect_name" value="<?php echo htmlspecialchars($prospect_data_for_form['ProspectName']??'');?>" required><?php if(isset($form_errors_prospect['prospect_name'])):?><div class="invalid-feedback"><?php echo $form_errors_prospect['prospect_name'];?></div><?php endif;?></div>
                <div class="col-md-6 mb-3"><label for="p_f_parent" class="form-label">نام والدین</label><input type="text" class="form-control" id="p_f_parent" name="parent_name" value="<?php echo htmlspecialchars($prospect_data_for_form['ParentName']??'');?>"></div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3"><label for="p_f_phone" class="form-label">شماره تماس <span class="text-danger">*</span></label><input type="tel" class="form-control <?php echo isset($form_errors_prospect['phone_number'])?'is-invalid':'';?>" id="p_f_phone" name="phone_number" value="<?php echo htmlspecialchars($prospect_data_for_form['PhoneNumber']??'');?>" dir="ltr" required><?php if(isset($form_errors_prospect['phone_number'])):?><div class="invalid-feedback"><?php echo $form_errors_prospect['phone_number'];?></div><?php endif;?></div>
                <div class="col-md-6 mb-3"><label for="p_f_introducer" class="form-label">معرف</label><input type="text" class="form-control" id="p_f_introducer" name="introducer_name" value="<?php echo htmlspecialchars($prospect_data_for_form['IntroducerName']??'');?>"></div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3"><label for="p_f_region" class="form-label">منطقه جذب</label><select class="form-select" id="p_f_region" name="region_id"><option value="">-- انتخاب نشده --</option><?php foreach($available_regions_prospect as $reg_opt):?><option value="<?php echo $reg_opt['RegionID'];?>" <?php echo (($prospect_data_for_form['RegionID']??null)==$reg_opt['RegionID'])?'selected':'';?>><?php echo htmlspecialchars($reg_opt['RegionName']);?></option><?php endforeach;?></select></div>
                <div class="col-md-4 mb-3"><label for="p_f_event" class="form-label">مراسم جذب</label><select class="form-select" id="p_f_event" name="rec_event_id"><option value="">-- انتخاب نشده --</option><?php foreach($available_rec_events_prospect as $ev_rec_opt):?><option value="<?php echo $ev_rec_opt['EventID'];?>" <?php echo (($prospect_data_for_form['RecruitmentEventID']??null)==$ev_rec_opt['EventID'])?'selected':'';?>><?php echo htmlspecialchars($ev_rec_opt['EventName'].' ('.to_jalali($ev_rec_opt['EventDate'],'yy/MM/dd').')');?></option><?php endforeach;?></select></div>
                <div class="col-md-4 mb-3"><label for="p_f_jdate" class="form-label">تاریخ جذب <span class="text-danger">*</span></label><input type="text" class="form-control persian-datepicker <?php echo isset($form_errors_prospect['joined_date'])?'is-invalid':'';?>" id="p_f_jdate" name="joined_date" value="<?php echo htmlspecialchars($prospect_data_for_form['JoinedDate']??'');?>" required><?php if(isset($form_errors_prospect['joined_date'])):?><div class="invalid-feedback"><?php echo $form_errors_prospect['joined_date'];?></div><?php endif;?></div>
            </div>
            <div class="mb-3"><label for="p_f_status" class="form-label">وضعیت پیگیری</label><select class="form-select" id="p_f_status" name="status"><?php foreach($prospect_statuses as $psk_f=>$psv_f):?><option value="<?php echo $psk_f;?>" <?php echo (($prospect_data_for_form['Status']??'new')===$psk_f)?'selected':'';?>><?php echo $psv_f;?></option><?php endforeach;?></select></div>
            <div class="mb-3"><label for="p_f_desc" class="form-label">توضیحات و سوابق</label><textarea class="form-control" id="p_f_desc" name="description" rows="4"><?php echo htmlspecialchars($prospect_data_for_form['Description']??'');?></textarea></div>
            <div class="form-actions"><button type="submit" name="save_prospect" class="btn btn-success"><em class="bi bi-check-circle-fill icon"></em> ذخیره اطلاعات</button><a href="prospects.php" class="btn btn-outline-secondary">انصراف</a></div>
        </form>
    </div></div>
<?php endif; ?>

<div class="modal fade" id="deleteProspectModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="prospects.php" id="deleteProspectFormModal">
    <input type="hidden" name="csrf_token_delete_modal_prospect" id="csrf_token_delete_modal_prospect_input_val" value="">
    <input type="hidden" name="prospect_id_to_delete_confirmed" id="prospect_id_to_delete_modal_input_val">
    <div class="modal-header"><h5 class="modal-title">تایید حذف فرد</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">آیا از حذف <strong id="prospectNameToDeleteModalVal"></strong> مطمئن هستید؟ این عمل غیرقابل بازگشت است.</div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button><button type="submit" name="delete_prospect_confirmed" class="btn btn-danger">حذف</button></div>
    </form></div></div></div>

<link rel="stylesheet" href="<?php echo get_base_url(); ?>assets/js/persian-datepicker/persian-datepicker.min.css"/>
<script src="<?php echo get_base_url(); ?>assets/js/jquery.min.js"></script>
<script src="<?php echo get_base_url(); ?>assets/js/persian-datepicker/persian-date.min.js"></script>
<script src="<?php echo get_base_url(); ?>assets/js/persian-datepicker/persian-datepicker.min.js"></script>
<script>
$(document).ready(function(){
    if($(".persian-datepicker").length){$(".persian-datepicker").persianDatepicker({format:'YYYY/MM/DD',autoClose:true,observer:true,initialValue:false});}
    $('.btn-delete-prospect').on('click',function(){
        $('#prospect_id_to_delete_modal_input_val').val($(this).data('prospect-id'));
        $('#prospectNameToDeleteModalVal').text($(this).data('prospect-name'));
        $('#csrf_token_delete_modal_prospect_input_val').val('<?php echo $csrf_token_prospect_delete_val;?>');
        new bootstrap.Modal(document.getElementById('deleteProspectModal')).show();
    });
});
</script>
<style>.badge.bg-purple { background-color: #6f42c1; color: white; }</style> <!-- Example for custom badge color -->
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
