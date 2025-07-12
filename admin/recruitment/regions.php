<?php
require_once __DIR__ . '/../includes/header.php';

$action = $_GET['action'] ?? 'list'; // list, create, edit
$region_id_url = isset($_GET['region_id']) ? (int)$_GET['region_id'] : 0; // Use a different var name to avoid conflict

$regions_list = []; // For list view
$region_data_for_form_display = null; // For pre-filling create/edit form
$form_errors_rec_region = []; // Specific errors for this module/page
$page_title_rec_region = "مدیریت مناطق جغرافیایی جذب";

// CSRF Tokens
$csrf_token_name_rec_region_form = 'recruitment_region_form_action'; // More specific name
$csrf_token_rec_region_form_val = generate_csrf_token($csrf_token_name_rec_region_form);
$csrf_token_name_rec_region_delete = 'recruitment_region_delete_action';
$csrf_token_rec_region_delete_val = generate_csrf_token($csrf_token_name_rec_region_delete);

// Handle POST for create/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_region'])) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '', $csrf_token_name_rec_region_form)) {
            $form_errors_rec_region['csrf'] = "خطای CSRF.";
        } else {
            $csrf_token_rec_region_form_val = regenerate_csrf_token($csrf_token_name_rec_region_form);
            $region_id_posted_form = isset($_POST['region_id']) ? (int)$_POST['region_id'] : 0;
            $region_name_form = sanitize_input($_POST['region_name'] ?? '');
            $region_description_form = sanitize_input($_POST['region_description'] ?? '');
            $is_active_region_form = isset($_POST['is_active']) ? 1 : 0;

            // Repopulate form data for display in case of error
            $region_data_for_form_display = $_POST;
            $region_data_for_form_display['IsActive'] = $is_active_region_form; // Ensure checkbox state is preserved

            if (empty($region_name_form)) $form_errors_rec_region['region_name'] = "نام منطقه الزامی است.";

            if (empty($form_errors_rec_region['region_name']) && $conn) {
                $sql_check_dup_region = "SELECT RegionID FROM RecruitmentRegions WHERE RegionName = ?";
                $params_check_dup_region = [$region_name_form];
                $types_check_dup_region = "s";
                if ($region_id_posted_form > 0) {
                    $sql_check_dup_region .= " AND RegionID != ?";
                    $params_check_dup_region[] = $region_id_posted_form;
                    $types_check_dup_region .= "i";
                }
                $stmt_check_dup_region = $conn->prepare($sql_check_dup_region);
                if($stmt_check_dup_region){
                    $stmt_check_dup_region->bind_param($types_check_dup_region, ...$params_check_dup_region);
                    $stmt_check_dup_region->execute();
                    if($stmt_check_dup_region->get_result()->num_rows > 0) $form_errors_rec_region['region_name'] = "منطقه‌ای با این نام قبلا ثبت شده.";
                    $stmt_check_dup_region->close();
                } else $form_errors_rec_region['db'] = "خطا در بررسی نام تکراری: " . $conn->error;
            }

            if (empty($form_errors_rec_region)) {
                if ($conn) {
                    if ($region_id_posted_form > 0) { // Update
                        $stmt_update_reg = $conn->prepare("UPDATE RecruitmentRegions SET RegionName = ?, Description = ?, IsActive = ?, UpdatedAt = NOW() WHERE RegionID = ?");
                        if($stmt_update_reg){
                            $stmt_update_reg->bind_param("ssii", $region_name_form, $region_description_form, $is_active_region_form, $region_id_posted_form);
                            if ($stmt_update_reg->execute()) { $_SESSION['action_success_recruitment'] = "منطقه بروزرسانی شد."; header("Location: regions.php"); exit; }
                            else $form_errors_rec_region['db'] = "خطا در بروزرسانی: " . $stmt_update_reg->error;
                            $stmt_update_reg->close();
                        } else $form_errors_rec_region['db'] = "خطای آماده سازی بروزرسانی: " . $conn->error;
                    } else { // Create
                        $stmt_create_reg = $conn->prepare("INSERT INTO RecruitmentRegions (RegionName, Description, IsActive, CreatedAt) VALUES (?, ?, ?, NOW())");
                        if($stmt_create_reg){
                            $stmt_create_reg->bind_param("ssi", $region_name_form, $region_description_form, $is_active_region_form);
                            if ($stmt_create_reg->execute()) { $_SESSION['action_success_recruitment'] = "منطقه ایجاد شد."; header("Location: regions.php"); exit; }
                            else $form_errors_rec_region['db'] = "خطا در ایجاد: " . $stmt_create_reg->error;
                            $stmt_create_reg->close();
                        } else $form_errors_rec_region['db'] = "خطای آماده سازی ایجاد: " . $conn->error;
                    }
                } else $form_errors_rec_region['db'] = "عدم اتصال به پایگاه داده.";
            }
            $action = ($region_id_posted_form > 0) ? 'edit' : 'create'; // Stay on form
        }
    } elseif (isset($_POST['delete_region_confirmed'])) { // Delete action
        if (!verify_csrf_token($_POST['csrf_token_delete_modal_region'] ?? '', $csrf_token_name_rec_region_delete)) {
            $_SESSION['action_error_recruitment'] = "خطای CSRF.";
        } else {
            $csrf_token_rec_region_delete_val = regenerate_csrf_token($csrf_token_name_rec_region_delete); // Regenerate
            $region_id_to_delete_confirmed = (int)($_POST['region_id_to_delete_confirmed'] ?? 0);
            if ($region_id_to_delete_confirmed > 0 && $conn) {
                $stmt_del_reg = $conn->prepare("DELETE FROM RecruitmentRegions WHERE RegionID = ?");
                if($stmt_del_reg){
                    $stmt_del_reg->bind_param("i", $region_id_to_delete_confirmed);
                    if ($stmt_del_reg->execute()) { $_SESSION['action_success_recruitment'] = ($stmt_del_reg->affected_rows > 0) ? "منطقه حذف شد." : "منطقه یافت نشد."; }
                    else {
                        if ($conn->errno == 1451) $_SESSION['action_error_recruitment'] = "این منطقه توسط افراد جذب شده یا مراسم‌ها استفاده می‌شود و قابل حذف نیست.";
                        else $_SESSION['action_error_recruitment'] = "خطا در حذف منطقه: " . $stmt_del_reg->error;
                    }
                    $stmt_del_reg->close();
                } else $_SESSION['action_error_recruitment'] = "خطای آماده سازی حذف منطقه: " . $conn->error;
            } else $_SESSION['action_error_recruitment'] = "شناسه منطقه برای حذف نامعتبر است.";
        }
        header("Location: regions.php"); exit;
    }
}

// Fetch data for list or edit form (if not a POST with errors)
if ($conn) {
    if ($action === 'list') {
        $page_title_rec_region = "مدیریت مناطق جغرافیایی جذب";
        $search_query_rec_region = isset($_GET['search_region']) ? sanitize_input($_GET['search_region']) : '';
        $sql_list_rec_region = "SELECT rr.RegionID, rr.RegionName, rr.Description, rr.IsActive, rr.CreatedAt, COUNT(rp.ProspectID) as ProspectCount
                               FROM RecruitmentRegions rr
                               LEFT JOIN RecruitmentProspects rp ON rr.RegionID = rp.RegionID";
        if(!empty($search_query_rec_region)){
            $sql_list_rec_region .= " WHERE rr.RegionName LIKE ? OR rr.Description LIKE ?";
        }
        $sql_list_rec_region .= " GROUP BY rr.RegionID, rr.RegionName, rr.Description, rr.IsActive, rr.CreatedAt ORDER BY rr.RegionName ASC";

        $stmt_list_rec_region = $conn->prepare($sql_list_rec_region);
        if($stmt_list_rec_region){
            if(!empty($search_query_rec_region)){ $like_term_rec = "%".$search_query_rec_region."%"; $stmt_list_rec_region->bind_param("ss", $like_term_rec, $like_term_rec); }
            if($stmt_list_rec_region->execute()){ $result_list_rec_region = $stmt_list_rec_region->get_result(); while($row=$result_list_rec_region->fetch_assoc()) $regions_list[]=$row; }
            else $form_errors_rec_region['db_list'] = "خطا در بارگذاری لیست مناطق: " . $stmt_list_rec_region->error;
            $stmt_list_rec_region->close();
        } else $form_errors_rec_region['db_list'] = "خطای آماده سازی لیست مناطق: " . $conn->error;

    } elseif (($action === 'edit' || $action === 'create') && !$region_data_for_form_display) { // If not repopulating from POST error
        if ($action === 'edit' && $region_id_url > 0) {
            $page_title_rec_region = "ویرایش منطقه";
            $stmt_rec_region_edit = $conn->prepare("SELECT RegionID, RegionName, Description, IsActive FROM RecruitmentRegions WHERE RegionID = ?");
            if($stmt_rec_region_edit){
                $stmt_rec_region_edit->bind_param("i", $region_id_url); $stmt_rec_region_edit->execute();
                $result_rec_region_edit = $stmt_rec_region_edit->get_result();
                if (!($region_data_for_form_display = $result_rec_region_edit->fetch_assoc())) {
                    $_SESSION['action_error_recruitment'] = "منطقه یافت نشد."; header("Location: regions.php"); exit;
                }
                $stmt_rec_region_edit->close();
            } else $form_errors_rec_region['db_load'] = "خطا در بارگذاری اطلاعات منطقه: " . $conn->error;
        } else { // create
            $page_title_rec_region = "ایجاد منطقه جدید";
            $region_data_for_form_display = ['RegionName' => '', 'Description' => '', 'IsActive' => 1];
        }
    }
} else {
    $form_errors_rec_region['db_connection'] = "خطا در اتصال به پایگاه داده.";
}
?>
<div class="page-header">
    <h1><?php echo $page_title_rec_region; ?></h1>
    <div class="page-header-actions">
        <a href="regions.php?action=<?php echo ($action === 'list' ? 'create' : 'list'); ?>" class="btn btn-<?php echo ($action === 'list' ? 'primary' : 'secondary'); ?>">
            <em class="bi <?php echo ($action === 'list' ? 'bi-plus-circle' : 'bi-list-ul'); ?> icon"></em>
            <?php echo ($action === 'list' ? 'ایجاد منطقه جدید' : 'لیست مناطق'); ?>
        </a>
        <a href="index.php" class="btn btn-outline-secondary ms-2"><em class="bi bi-house-door icon"></em> داشبورد جذب</a>
    </div>
</div>

<?php if (isset($_SESSION['action_success_recruitment'])): ?><div class="alert alert-success alert-dismissible fade show"><button type="button" class="btn-close" data-bs-dismiss="alert"></button><?php echo $_SESSION['action_success_recruitment']; unset($_SESSION['action_success_recruitment']); ?></div><?php endif; ?>
<?php if (!empty($form_errors_rec_region)): ?><div class="alert alert-danger alert-dismissible fade show"><strong>خطا:</strong><ul class="mb-0 ps-3"><?php foreach($form_errors_rec_region as $err_key => $err_msg): echo "<li>".htmlspecialchars($err_msg)."</li>"; endforeach; ?></ul><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<?php if ($action === 'list'): ?>
    <div class="filter-search-bar mb-3"><form method="GET" class="row g-2 align-items-center"><div class="col-md"><input type="text" class="form-control form-control-sm" name="search_region" placeholder="جستجو در نام یا توضیحات منطقه..." value="<?php echo htmlspecialchars($search_query_rec_region ?? ''); ?>"></div><div class="col-md-auto"><button type="submit" class="btn btn-info btn-sm">فیلتر</button></div><?php if(!empty($search_query_rec_region)):?><div class="col-md-auto"><a href="regions.php" class="btn btn-secondary btn-sm">پاک کردن</a></div><?php endif;?></form></div>
    <div class="card"><div class="card-body">
        <?php if(empty($regions_list)): ?><p class="text-center text-muted py-3">هیچ منطقه‌ای یافت نشد. <?php if(empty($search_query_rec_region)) echo '<a href="?action=create">یک منطقه جدید ایجاد کنید</a>.';?></p>
        <?php else: ?><div class="table-responsive"><table class="table table-hover table-striped">
            <thead class="table-light"><tr><th>#</th><th>نام منطقه</th><th>تعداد افراد جذب شده</th><th>وضعیت</th><th>تاریخ ایجاد</th><th class="actions-column">عملیات</th></tr></thead>
            <tbody><?php foreach($regions_list as $idx_reg => $reg): ?>
                <tr><td><?php echo $idx_reg+1;?></td><td><a href="?action=edit&region_id=<?php echo $reg['RegionID'];?>"><?php echo htmlspecialchars($reg['RegionName']);?></a></td><td><a href="prospects.php?region_id=<?php echo $reg['RegionID']; ?>"><?php echo $reg['ProspectCount']; ?></a></td><td><span class="badge bg-<?php echo $reg['IsActive']?'success':'secondary';?>"><?php echo $reg['IsActive']?'فعال':'غیرفعال';?></span></td><td><?php echo to_jalali($reg['CreatedAt'],'yyyy/MM/dd');?></td>
                <td class="actions-cell">
                    <a href="?action=edit&region_id=<?php echo $reg['RegionID'];?>" class="btn btn-sm btn-outline-info" title="ویرایش"><em class="bi bi-pencil-square"></em></a>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-delete-rec-region" data-region-id="<?php echo $reg['RegionID'];?>" data-region-name="<?php echo htmlspecialchars($reg['RegionName']);?>"><em class="bi bi-trash3"></em></button>
                </td></tr>
            <?php endforeach; ?></tbody>
        </table></div><?php endif; ?>
    </div></div>
<?php elseif ($action === 'create' || $action === 'edit'): ?>
    <div class="card"><div class="card-body">
        <form method="POST" action="regions.php<?php echo ($action==='edit'&&$region_id_url)?'?action=edit&region_id='.$region_id_url:'?action=create';?>" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_rec_region_form_val; ?>">
            <?php if($action==='edit'&&$region_id_url):?><input type="hidden" name="region_id" value="<?php echo $region_id_url;?>"><?php endif;?>
            <div class="mb-3"><label for="rec_reg_name" class="form-label">نام منطقه <span class="text-danger">*</span></label><input type="text" class="form-control <?php echo isset($form_errors_rec_region['region_name'])?'is-invalid':'';?>" id="rec_reg_name" name="region_name" value="<?php echo htmlspecialchars($region_data_for_form_display['RegionName']??'');?>" required><?php if(isset($form_errors_rec_region['region_name'])):?><div class="invalid-feedback"><?php echo $form_errors_rec_region['region_name'];?></div><?php endif;?></div>
            <div class="mb-3"><label for="rec_reg_desc" class="form-label">توضیحات</label><textarea class="form-control" id="rec_reg_desc" name="region_description" rows="3"><?php echo htmlspecialchars($region_data_for_form_display['Description']??'');?></textarea></div>
            <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="rec_reg_active" name="is_active" value="1" <?php echo (($region_data_for_form_display['IsActive']??1)==1)?'checked':'';?>><label class="form-check-label" for="rec_reg_active">منطقه فعال باشد</label></div>
            <div class="form-actions"><button type="submit" name="save_region" class="btn btn-success"><em class="bi bi-check-circle-fill icon"></em> ذخیره</button><a href="regions.php" class="btn btn-outline-secondary">انصراف</a></div>
        </form>
    </div></div>
<?php endif; ?>

<div class="modal fade" id="deleteRecRegionModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="regions.php" id="deleteRecRegionFormModal">
    <input type="hidden" name="csrf_token_delete_modal_region" id="csrf_token_delete_modal_rec_region_input" value="">
    <input type="hidden" name="region_id_to_delete_confirmed" id="region_id_to_delete_modal_rec_input">
    <div class="modal-header"><h5 class="modal-title">تایید حذف منطقه</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">آیا از حذف منطقه <strong id="recRegionNameToDeleteModal"></strong> مطمئن هستید؟ <small class="text-danger d-block">توجه: اگر افرادی به این منطقه تخصیص داده شده باشند یا مراسمی در این منطقه ثبت شده باشد، حذف انجام نخواهد شد.</small></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button><button type="submit" name="delete_region_confirmed" class="btn btn-danger">حذف</button></div>
    </form></div></div></div>
<script>
$(document).ready(function(){ $('.btn-delete-rec-region').on('click',function(){$('#region_id_to_delete_modal_rec_input').val($(this).data('region-id'));$('#recRegionNameToDeleteModal').text($(this).data('region-name'));$('#csrf_token_delete_modal_rec_region_input').val('<?php echo $csrf_token_rec_region_delete_val; ?>');new bootstrap.Modal(document.getElementById('deleteRecRegionModal')).show();});});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
