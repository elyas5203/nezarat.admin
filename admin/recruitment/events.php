<?php
require_once __DIR__ . '/../includes/header.php';

$action = $_GET['action'] ?? 'list'; // list, create, edit, view
$rec_event_id_url = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

$rec_events_list = [];
$rec_event_data_for_form = null;
$form_errors_rec_event = [];
$page_title_rec_event = "مدیریت مراسم‌های جذب";

$csrf_token_name_rec_event_form = 'recruitment_event_form_action';
$csrf_token_rec_event_form_val = generate_csrf_token($csrf_token_name_rec_event_form);
$csrf_token_name_rec_event_delete = 'recruitment_event_delete_action';
$csrf_token_rec_event_delete_val = generate_csrf_token($csrf_token_name_rec_event_delete);

// Define event types - can be moved to a config or fetched from DB if they become dynamic
$rec_event_types = ['ghadir' => 'جشن غدیر', 'nime_shaban' => 'جشن نیمه شعبان', 'public_gathering' => 'همایش عمومی', 'school_outreach' => 'ارتباط با مدارس', 'other' => 'سایر'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_rec_event'])) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '', $csrf_token_name_rec_event_form)) {
            $form_errors_rec_event['csrf'] = "خطای CSRF.";
        } else {
            $csrf_token_rec_event_form_val = regenerate_csrf_token($csrf_token_name_rec_event_form);
            $event_id_posted = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;

            $event_name = sanitize_input($_POST['event_name'] ?? '');
            $event_type = sanitize_input($_POST['event_type'] ?? 'other');
            $event_date_jalali = sanitize_input($_POST['event_date'] ?? '');
            $location = sanitize_input($_POST['location'] ?? null);
            $description = sanitize_input($_POST['description'] ?? null);

            $rec_event_data_for_form = $_POST;
            $rec_event_data_for_form['EventDate'] = $event_date_jalali;

            if (empty($event_name)) $form_errors_rec_event['event_name'] = "نام مراسم الزامی است.";
            if (empty($event_date_jalali)) $form_errors_rec_event['event_date'] = "تاریخ مراسم الزامی است.";
            $event_date_gregorian = null;
            if(!empty($event_date_jalali)){
                $event_date_gregorian = to_gregorian_date_for_db($event_date_jalali);
                if(!$event_date_gregorian) $form_errors_rec_event['event_date'] = "فرمت تاریخ نامعتبر.";
            }
            if(!array_key_exists($event_type, $rec_event_types)) $form_errors_rec_event['event_type'] = "نوع مراسم نامعتبر است.";

            if (empty($form_errors_rec_event)) {
                if ($conn) {
                    if ($event_id_posted > 0) { // Update
                        $stmt = $conn->prepare("UPDATE RecruitmentEvents SET EventName=?, EventType=?, EventDate=?, Location=?, Description=?, UpdatedAt=NOW() WHERE EventID=?");
                        if($stmt){
                            $stmt->bind_param("sssssi", $event_name, $event_type, $event_date_gregorian, $location, $description, $event_id_posted);
                            if ($stmt->execute()) { $_SESSION['action_success_recruitment'] = "مراسم جذب بروزرسانی شد."; header("Location: events.php"); exit; }
                            else $form_errors_rec_event['db'] = "خطا در بروزرسانی: " . $stmt->error;
                            $stmt->close();
                        } else $form_errors_rec_event['db'] = "خطای آماده سازی بروزرسانی: " . $conn->error;
                    } else { // Create
                        $stmt = $conn->prepare("INSERT INTO RecruitmentEvents (EventName, EventType, EventDate, Location, Description, CreatedAt, CreatedByUserID) VALUES (?,?,?,?,?,NOW(),?)");
                        if($stmt){
                            $current_admin_id_rec_ev = get_current_user_id();
                            $stmt->bind_param("sssssi", $event_name, $event_type, $event_date_gregorian, $location, $description, $current_admin_id_rec_ev);
                            if ($stmt->execute()) { $_SESSION['action_success_recruitment'] = "مراسم جذب ایجاد شد."; header("Location: events.php"); exit; }
                            else $form_errors_rec_event['db'] = "خطا در ایجاد: " . $stmt->error;
                            $stmt->close();
                        } else $form_errors_rec_event['db'] = "خطای آماده سازی ایجاد: " . $conn->error;
                    }
                } else $form_errors_rec_event['db'] = "عدم اتصال به پایگاه داده.";
            }
            $action = ($event_id_posted > 0) ? 'edit' : 'create';
        }
    } elseif (isset($_POST['delete_rec_event_confirmed'])) {
        if (!verify_csrf_token($_POST['csrf_token_delete_modal_rec_event'] ?? '', $csrf_token_name_rec_event_delete)) {
            $_SESSION['action_error_recruitment'] = "خطای CSRF.";
        } else {
            $csrf_token_rec_event_delete_val = regenerate_csrf_token($csrf_token_name_rec_event_delete);
            $event_id_to_delete = (int)($_POST['event_id_to_delete_confirmed'] ?? 0);
            if ($event_id_to_delete > 0 && $conn) {
                $conn->begin_transaction();
                try {
                    // Set RecruitmentEventID to NULL for prospects linked to this event
                    $stmt_update_prospects = $conn->prepare("UPDATE RecruitmentProspects SET RecruitmentEventID = NULL WHERE RecruitmentEventID = ?");
                    if(!$stmt_update_prospects) throw new Exception("خطای آماده سازی بروزرسانی افراد جذب شده: " . $conn->error);
                    $stmt_update_prospects->bind_param("i", $event_id_to_delete);
                    if(!$stmt_update_prospects->execute()) throw new Exception("خطا در بروزرسانی افراد جذب شده: " . $stmt_update_prospects->error);
                    $stmt_update_prospects->close();

                    // Then delete the event
                    $stmt_del = $conn->prepare("DELETE FROM RecruitmentEvents WHERE EventID = ?");
                    if(!$stmt_del) throw new Exception("خطای آماده سازی حذف مراسم: " . $conn->error);
                    $stmt_del->bind_param("i", $event_id_to_delete);
                    if ($stmt_del->execute()) {
                        if ($stmt_del->affected_rows > 0) {
                            $conn->commit();
                            $_SESSION['action_success_recruitment'] = "مراسم جذب و ارتباط آن با افراد جذب شده با موفقیت حذف شد.";
                        } else {
                             $conn->rollback(); // Rollback if event itself wasn't found, though prospects might have been updated
                            $_SESSION['action_error_recruitment'] = "مراسم جذب یافت نشد یا قبلاً حذف شده است.";
                        }
                    } else throw new Exception("خطا در حذف مراسم: " . $stmt_del->error);
                    $stmt_del->close();
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['action_error_recruitment'] = $e->getMessage();
                }
            } else $_SESSION['action_error_recruitment'] = "شناسه مراسم برای حذف نامعتبر است.";
        }
        header("Location: events.php"); exit;
    }
}

// Fetch data for list or edit form
if ($conn) {
    if ($action === 'list') {
        $page_title_rec_event = "لیست مراسم‌های جذب";
        $search_rec_ev_name = sanitize_input($_GET['search_event_name'] ?? '');
        $filter_rec_ev_type = sanitize_input($_GET['filter_event_type'] ?? '');

        $sql_list_rec_ev = "SELECT re.EventID, re.EventName, re.EventType, re.EventDate, re.Location,
                                   (SELECT COUNT(*) FROM RecruitmentProspects rp WHERE rp.RecruitmentEventID = re.EventID) as ProspectCount
                            FROM RecruitmentEvents re WHERE 1=1 ";
        $params_list_rec_ev = []; $types_list_rec_ev = "";
        if(!empty($search_rec_ev_name)){
            $sql_list_rec_ev .= " AND re.EventName LIKE ?";
            $params_list_rec_ev[] = "%".$search_rec_ev_name."%"; $types_list_rec_ev .= "s";
        }
        if(!empty($filter_rec_ev_type) && array_key_exists($filter_rec_ev_type, $rec_event_types)){
            $sql_list_rec_ev .= " AND re.EventType = ?";
            $params_list_rec_ev[] = $filter_rec_ev_type; $types_list_rec_ev .= "s";
        }
        $sql_list_rec_ev .= " ORDER BY re.EventDate DESC";

        $stmt_list_rec_ev = $conn->prepare($sql_list_rec_ev);
        if($stmt_list_rec_ev){
            if(!empty($params_list_rec_ev)) $stmt_list_rec_ev->bind_param($types_list_rec_ev, ...$params_list_rec_ev);
            if($stmt_list_rec_ev->execute()){ $result_list_rec_ev = $stmt_list_rec_ev->get_result(); while($row=$result_list_rec_ev->fetch_assoc()) $rec_events_list[]=$row; }
            else $form_errors_rec_event['db_list'] = "خطا در بارگذاری لیست مراسم: " . $stmt_list_rec_ev->error;
            $stmt_list_rec_ev->close();
        } else $form_errors_rec_event['db_list'] = "خطای آماده سازی لیست مراسم: " . $conn->error;

    } elseif (($action === 'edit' || $action === 'create') && !$rec_event_data_for_form) {
        if ($action === 'edit' && $rec_event_id_url > 0) {
            $page_title_rec_event = "ویرایش مراسم جذب";
            $stmt_rec_ev_edit = $conn->prepare("SELECT * FROM RecruitmentEvents WHERE EventID = ?");
            if($stmt_rec_ev_edit){
                $stmt_rec_ev_edit->bind_param("i", $rec_event_id_url); $stmt_rec_ev_edit->execute();
                $result_rec_ev_edit = $stmt_rec_ev_edit->get_result();
                if (!($rec_event_data_for_form = $result_rec_ev_edit->fetch_assoc())) { $_SESSION['action_error_recruitment'] = "مراسم یافت نشد."; header("Location: events.php"); exit; }
                if (!empty($rec_event_data_for_form['EventDate'])) { $rec_event_data_for_form['EventDate'] = to_jalali($rec_event_data_for_form['EventDate'], 'yyyy/MM/dd'); }
                $stmt_rec_ev_edit->close();
            } else $form_errors_rec_event['db_load'] = "خطا بارگذاری: " . $conn->error;
        } else { // create
            $page_title_rec_event = "ایجاد مراسم جذب جدید";
            $rec_event_data_for_form = ['EventName'=>'','EventType'=>'other','EventDate'=>to_jalali(date('Y-m-d'),'yyyy/MM/dd'),'Location'=>'','Description'=>''];
        }
    }
} else $form_errors_rec_event['db_connection'] = "خطا اتصال دیتابیس.";
?>
<div class="page-header">
    <h1><?php echo $page_title_rec_event; ?></h1>
    <div class="page-header-actions">
        <a href="events.php?action=<?php echo ($action==='list'?'create':'list'); ?>" class="btn btn-<?php echo ($action==='list'?'primary':'secondary');?>"><em class="bi <?php echo ($action==='list'?'bi-calendar-plus':'bi-list-ul');?> icon"></em> <?php echo ($action==='list'?'ایجاد مراسم جدید':'لیست مراسم‌ها');?></a>
        <a href="index.php" class="btn btn-outline-secondary ms-2"><em class="bi bi-house-door icon"></em> داشبورد جذب</a>
    </div>
</div>

<?php if(isset($_SESSION['action_success_recruitment'])):?><div class="alert alert-success alert-dismissible fade show"><button class="btn-close" data-bs-dismiss="alert"></button><?php echo $_SESSION['action_success_recruitment']; unset($_SESSION['action_success_recruitment']);?></div><?php endif;?>
<?php if(!empty($form_errors_rec_event)):?><div class="alert alert-danger alert-dismissible fade show"><strong>خطا:</strong><ul class="mb-0 ps-3"><?php foreach($form_errors_rec_event as $e_key=>$e_msg):echo "<li>".htmlspecialchars($e_msg)."</li>";endforeach;?></ul><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif;?>

<?php if($action === 'list'): ?>
    <div class="filter-search-bar mb-3"><form method="GET" class="row g-2 align-items-center">
        <div class="col-md-5"><input type="text" class="form-control form-control-sm" name="search_event_name" placeholder="جستجو در نام مراسم..." value="<?php echo htmlspecialchars($search_rec_ev_name ?? '');?>"></div>
        <div class="col-md-4"><select name="filter_event_type" class="form-select form-select-sm"><option value="">همه انواع</option><?php foreach($rec_event_types as $etk_f=>$etv_f):?><option value="<?php echo $etk_f;?>" <?php echo (($filter_rec_ev_type??'')===$etk_f)?'selected':'';?>><?php echo $etv_f;?></option><?php endforeach;?></select></div>
        <div class="col-md-auto"><button type="submit" class="btn btn-info btn-sm">فیلتر</button></div>
        <?php if(!empty($search_rec_ev_name)||!empty($filter_rec_ev_type)):?><div class="col-md-auto"><a href="events.php" class="btn btn-secondary btn-sm">پاک کردن</a></div><?php endif;?>
    </form></div>
    <div class="card"><div class="card-body">
    <?php if(empty($rec_events_list)): ?><p class="text-center text-muted py-3">هیچ مراسم جذبی یافت نشد.</p>
    <?php else: ?><div class="table-responsive"><table class="table table-hover table-sm">
        <thead class="table-light"><tr><th>#</th><th>نام مراسم</th><th>نوع</th><th>تاریخ</th><th>مکان</th><th>تعداد جذب شده</th><th class="actions-column">عملیات</th></tr></thead>
        <tbody><?php foreach($rec_events_list as $idx_re_l => $re_l): ?>
            <tr><td><?php echo $idx_re_l+1;?></td><td><a href="prospects.php?filter_event=<?php echo $re_l['EventID'];?>"><?php echo htmlspecialchars($re_l['EventName']);?></a></td><td><?php echo htmlspecialchars($rec_event_types[$re_l['EventType']]??$re_l['EventType']);?></td><td><?php echo to_jalali($re_l['EventDate'],'yyyy/MM/dd');?></td><td><?php echo htmlspecialchars($re_l['Location']?:'---');?></td><td><?php echo $re_l['ProspectCount'];?> نفر</td>
            <td class="actions-cell"><a href="?action=edit&event_id=<?php echo $re_l['EventID'];?>" class="btn btn-sm btn-outline-info" title="ویرایش"><em class="bi bi-pencil-square"></em></a><button type="button" class="btn btn-sm btn-outline-danger btn-delete-rec-event" data-event-id="<?php echo $re_l['EventID'];?>" data-event-name="<?php echo htmlspecialchars($re_l['EventName']);?>"><em class="bi bi-trash3"></em></button></td></tr>
        <?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
    </div></div>
<?php elseif ($action === 'create' || $action === 'edit'): ?>
    <div class="card"><div class="card-body">
        <form method="POST" action="events.php<?php echo ($action==='edit'&&$rec_event_id_url)?'?action=edit&event_id='.$rec_event_id_url:'?action=create';?>" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_rec_event_form_val; ?>">
            <?php if($action==='edit'&&$rec_event_id_url):?><input type="hidden" name="event_id" value="<?php echo $rec_event_id_url;?>"><?php endif;?>
            <div class="row">
                <div class="col-md-7 mb-3"><label for="rec_ev_f_name" class="form-label">نام مراسم <span class="text-danger">*</span></label><input type="text" class="form-control <?php echo isset($form_errors_rec_event['event_name'])?'is-invalid':'';?>" id="rec_ev_f_name" name="event_name" value="<?php echo htmlspecialchars($rec_event_data_for_form['EventName']??'');?>" required><?php if(isset($form_errors_rec_event['event_name'])):?><div class="invalid-feedback"><?php echo $form_errors_rec_event['event_name'];?></div><?php endif;?></div>
                <div class="col-md-5 mb-3"><label for="rec_ev_f_type" class="form-label">نوع مراسم <span class="text-danger">*</span></label><select class="form-select <?php echo isset($form_errors_rec_event['event_type'])?'is-invalid':'';?>" id="rec_ev_f_type" name="event_type" required><?php foreach($rec_event_types as $etk_opt=>$etv_opt):?><option value="<?php echo $etk_opt;?>" <?php echo (($rec_event_data_for_form['EventType']??'other')===$etk_opt)?'selected':'';?>><?php echo $etv_opt;?></option><?php endforeach;?></select><?php if(isset($form_errors_rec_event['event_type'])):?><div class="invalid-feedback"><?php echo $form_errors_rec_event['event_type'];?></div><?php endif;?></div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3"><label for="rec_ev_f_date" class="form-label">تاریخ مراسم <span class="text-danger">*</span></label><input type="text" class="form-control persian-datepicker <?php echo isset($form_errors_rec_event['event_date'])?'is-invalid':'';?>" id="rec_ev_f_date" name="event_date" value="<?php echo htmlspecialchars($rec_event_data_for_form['EventDate']??'');?>" required><?php if(isset($form_errors_rec_event['event_date'])):?><div class="invalid-feedback"><?php echo $form_errors_rec_event['event_date'];?></div><?php endif;?></div>
                <div class="col-md-6 mb-3"><label for="rec_ev_f_loc" class="form-label">مکان</label><input type="text" class="form-control" id="rec_ev_f_loc" name="location" value="<?php echo htmlspecialchars($rec_event_data_for_form['Location']??'');?>" placeholder="اختیاری"></div>
            </div>
            <div class="mb-3"><label for="rec_ev_f_desc" class="form-label">توضیحات</label><textarea class="form-control" id="rec_ev_f_desc" name="description" rows="3"><?php echo htmlspecialchars($rec_event_data_for_form['Description']??'');?></textarea></div>
            <div class="form-actions"><button type="submit" name="save_rec_event" class="btn btn-success"><em class="bi bi-check-circle-fill icon"></em> ذخیره</button><a href="events.php" class="btn btn-outline-secondary">انصراف</a></div>
        </form>
    </div></div>
<?php endif; ?>

<div class="modal fade" id="deleteRecEventModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="events.php" id="deleteRecEventFormModal">
    <input type="hidden" name="csrf_token_delete_modal_rec_event" id="csrf_token_delete_modal_rec_event_input_val" value="">
    <input type="hidden" name="event_id_to_delete_confirmed" id="event_id_to_delete_modal_rec_event_input_val">
    <div class="modal-header"><h5 class="modal-title">تایید حذف مراسم جذب</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">آیا از حذف مراسم <strong id="recEventNameToDeleteModalVal"></strong> مطمئن هستید؟ <small class="text-danger d-block">توجه: این عمل، ارتباط افراد جذب شده از این مراسم را با آن قطع خواهد کرد (اما خود افراد حذف نمی‌شوند).</small></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button><button type="submit" name="delete_rec_event_confirmed" class="btn btn-danger">حذف</button></div>
    </form></div></div></div>

<link rel="stylesheet" href="<?php echo get_base_url(); ?>assets/js/persian-datepicker/persian-datepicker.min.css"/>
<script src="<?php echo get_base_url(); ?>assets/js/jquery.min.js"></script>
<script src="<?php echo get_base_url(); ?>assets/js/persian-datepicker/persian-date.min.js"></script>
<script src="<?php echo get_base_url(); ?>assets/js/persian-datepicker/persian-datepicker.min.js"></script>
<script>
$(document).ready(function(){
    if($(".persian-datepicker").length){$(".persian-datepicker").persianDatepicker({format:'YYYY/MM/DD',autoClose:true,observer:true,initialValue:false});}
    $('.btn-delete-rec-event').on('click',function(){
        $('#event_id_to_delete_modal_rec_event_input_val').val($(this).data('event-id'));
        $('#recEventNameToDeleteModalVal').text($(this).data('event-name'));
        $('#csrf_token_delete_modal_rec_event_input_val').val('<?php echo $csrf_token_rec_event_delete_val;?>'); // Use the general delete token for the modal
        new bootstrap.Modal(document.getElementById('deleteRecEventModal')).show();
    });
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
