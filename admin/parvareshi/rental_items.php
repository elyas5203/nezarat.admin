<?php
require_once __DIR__ . '/../includes/header.php';

$action_ri_page = $_GET['action'] ?? 'list'; // list, create, edit
$item_id_ri_url_param = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;

$rental_items_list_display = [];
$item_data_for_form_ri_display = null;
$form_errors_ri_page_display = [];
$page_title_ri_page_display = "مدیریت اقلام کرایه‌چی";

$csrf_token_name_ri_form = 'parvareshi_rental_item_form_action';
$csrf_token_ri_form_val = generate_csrf_token($csrf_token_name_ri_form);
$csrf_token_name_ri_delete = 'parvareshi_rental_item_delete_action';
$csrf_token_ri_delete_val = generate_csrf_token($csrf_token_name_ri_delete);

// Handle POST for create/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_rental_item'])) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '', $csrf_token_name_ri_form)) {
            $form_errors_ri_page_display['csrf'] = "خطای CSRF.";
        } else {
            $csrf_token_ri_form_val = regenerate_csrf_token($csrf_token_name_ri_form);
            $item_id_posted_form = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;

            $item_name_form = sanitize_input($_POST['item_name'] ?? '');
            $description_ri_form = sanitize_input($_POST['description'] ?? null);
            $quantity_total_form = isset($_POST['quantity_total']) ? (int)$_POST['quantity_total'] : 0;
            $is_active_ri_form = isset($_POST['is_active']) ? 1 : 0;
            $image_path_ri_form = sanitize_input($_POST['image_path'] ?? null); // Basic path input for now

            $item_data_for_form_ri_display = $_POST; // Repopulate form on error

            if (empty($item_name_form)) $form_errors_ri_page_display['item_name'] = "نام قلم الزامی است.";
            if ($quantity_total_form < 0) $form_errors_ri_page_display['quantity_total'] = "تعداد کل نمی‌تواند منفی باشد.";

            if (empty($form_errors_ri_page_display)) {
                if ($conn) {
                    if ($item_id_posted_form > 0) { // Update
                        // Fetch current QuantityAvailable before updating QuantityTotal
                        $current_available = 0;
                        $stmt_curr_avail = $conn->prepare("SELECT QuantityAvailable FROM ParvareshiRentalItems WHERE ItemID = ?");
                        if($stmt_curr_avail){
                            $stmt_curr_avail->bind_param("i", $item_id_posted_form);
                            $stmt_curr_avail->execute();
                            $res_curr_avail = $stmt_curr_avail->get_result();
                            if($d_curr_avail = $res_curr_avail->fetch_assoc()) $current_available = $d_curr_avail['QuantityAvailable'];
                            $stmt_curr_avail->close();
                        }
                        // If total quantity is reduced, available cannot be more than total.
                        // This is a simplified logic. A more robust one would check against current bookings.
                        $new_quantity_available = ($quantity_total_form < $current_available) ? $quantity_total_form : $current_available;
                        // If total is increased, available might increase too, but this depends on booking logic.
                        // For now, let's assume if total increases, available increases by the same amount IF it was already equal to total.
                        // Or simply, if total increases, available = total - (old_total - old_available)
                        // This needs careful thought based on how bookings affect QuantityAvailable.
                        // A simpler approach for now: if total increases, available just stays as is, unless it means more are free.
                        // Let's simplify: when admin changes total, they are responsible for available count.
                        // The system should primarily decrement available on booking and increment on return.
                        // So, we might not directly update QuantityAvailable here unless specifically intended.
                        // For now, let's NOT auto-update QuantityAvailable when QuantityTotal changes, to avoid complexity.
                        // Admin must manage QuantityAvailable via a separate mechanism or it's updated by booking system.

                        $stmt_ri_update_form = $conn->prepare("UPDATE ParvareshiRentalItems SET ItemName=?, Description=?, QuantityTotal=?, ImagePath=?, IsActive=?, UpdatedAt=NOW() WHERE ItemID=?");
                        if($stmt_ri_update_form){
                            $stmt_ri_update_form->bind_param("ssisii", $item_name_form, $description_ri_form, $quantity_total_form, $image_path_ri_form, $is_active_ri_form, $item_id_posted_form);
                            if ($stmt_ri_update_form->execute()) { $_SESSION['action_success_parvareshi'] = "قلم بروزرسانی شد."; header("Location: rental_items.php"); exit; }
                            else $form_errors_ri_page_display['db'] = "خطا در بروزرسانی: " . $stmt_ri_update_form->error;
                            $stmt_ri_update_form->close();
                        } else $form_errors_ri_page_display['db'] = "خطای آماده سازی بروزرسانی: " . $conn->error;
                    } else { // Create
                        $quantity_available_new_item = $quantity_total_form;
                        $stmt_ri_create_form = $conn->prepare("INSERT INTO ParvareshiRentalItems (ItemName, Description, QuantityTotal, QuantityAvailable, ImagePath, IsActive, AddedAt, AddedByUserID) VALUES (?,?,?,?,?,?,NOW(),?)");
                        if($stmt_ri_create_form){
                            $current_admin_id_ri_form = get_current_user_id();
                            $stmt_ri_create_form->bind_param("ssiisis", $item_name_form, $description_ri_form, $quantity_total_form, $quantity_available_new_item, $image_path_ri_form, $is_active_ri_form, $current_admin_id_ri_form);
                            if ($stmt_ri_create_form->execute()) { $_SESSION['action_success_parvareshi'] = "قلم جدید اضافه شد."; header("Location: rental_items.php"); exit; }
                            else $form_errors_ri_page_display['db'] = "خطا در ایجاد: " . $stmt_ri_create_form->error;
                            $stmt_ri_create_form->close();
                        } else $form_errors_ri_page_display['db'] = "خطای آماده سازی ایجاد: " . $conn->error;
                    }
                } else $form_errors_ri_page_display['db'] = "عدم اتصال به پایگاه داده.";
            }
            $action_ri_page = ($item_id_posted_form > 0) ? 'edit' : 'create';
        }
    } elseif (isset($_POST['delete_rental_item_confirmed'])) {
        if (!verify_csrf_token($_POST['csrf_token_delete_modal_ri'] ?? '', $csrf_token_name_ri_delete)) {
            $_SESSION['action_error_parvareshi'] = "خطای CSRF.";
        } else {
            $csrf_token_ri_delete_val = regenerate_csrf_token($csrf_token_name_ri_delete);
            $item_id_to_delete_conf = (int)($_POST['item_id_to_delete_confirmed'] ?? 0);
            if ($item_id_to_delete_conf > 0 && $conn) {
                $stmt_del_ri_page = $conn->prepare("DELETE FROM ParvareshiRentalItems WHERE ItemID = ?");
                if($stmt_del_ri_page){
                    $stmt_del_ri_page->bind_param("i", $item_id_to_delete_conf);
                    if ($stmt_del_ri_page->execute()) { $_SESSION['action_success_parvareshi'] = ($stmt_del_ri_page->affected_rows > 0) ? "قلم حذف شد." : "قلم یافت نشد."; }
                    else {
                         if ($conn->errno == 1451) $_SESSION['action_error_parvareshi'] = "این قلم در رزروهای فعال استفاده می‌شود و قابل حذف نیست.";
                         else $_SESSION['action_error_parvareshi'] = "خطا در حذف: " . $stmt_del_ri_page->error;
                    }
                    $stmt_del_ri_page->close();
                } else $_SESSION['action_error_parvareshi'] = "خطای آماده سازی حذف: " . $conn->error;
            } else $_SESSION['action_error_parvareshi'] = "شناسه نامعتبر.";
        }
        header("Location: rental_items.php"); exit;
    }
}


if ($conn) {
    if ($action_ri_page === 'list') {
        $page_title_ri_page_display = "لیست اقلام کرایه‌چی";
        $search_ri_name_list = sanitize_input($_GET['search_item_name'] ?? '');
        $sql_list_ri_page = "SELECT ItemID, ItemName, QuantityTotal, QuantityAvailable, IsActive, ImagePath FROM ParvareshiRentalItems";
        $params_list_ri = []; $types_list_ri = "";
        if(!empty($search_ri_name_list)){
            $sql_list_ri_page .= " WHERE ItemName LIKE ? OR Description LIKE ?";
            $like_term_ri_list = "%".$search_ri_name_list."%";
            $params_list_ri[] = $like_term_ri_list; $params_list_ri[] = $like_term_ri_list;
            $types_list_ri = "ss";
        }
        $sql_list_ri_page .= " ORDER BY ItemName ASC";

        $stmt_list_ri_page = $conn->prepare($sql_list_ri_page);
        if($stmt_list_ri_page){
            if(!empty($params_list_ri)) $stmt_list_ri_page->bind_param($types_list_ri, ...$params_list_ri);
            if($stmt_list_ri_page->execute()){ $result_list_ri_page = $stmt_list_ri_page->get_result(); while($row_ri=$result_list_ri_page->fetch_assoc()) $rental_items_list_display[]=$row_ri; }
            else $form_errors_ri_page_display['db_list'] = "خطا بارگذاری لیست: " . $stmt_list_ri_page->error;
            $stmt_list_ri_page->close();
        } else $form_errors_ri_page_display['db_list'] = "خطای آماده سازی لیست: " . $conn->error;

    } elseif (($action_ri_page === 'edit' || $action_ri_page === 'create') && !$item_data_for_form_ri_display) {
        if ($action_ri_page === 'edit' && $item_id_ri_url_param > 0) {
            $page_title_ri_page_display = "ویرایش قلم کرایه‌چی";
            $stmt_ri_edit_page = $conn->prepare("SELECT * FROM ParvareshiRentalItems WHERE ItemID = ?");
            if($stmt_ri_edit_page){
                $stmt_ri_edit_page->bind_param("i", $item_id_ri_url_param); $stmt_ri_edit_page->execute();
                $result_ri_edit_page = $stmt_ri_edit_page->get_result();
                if (!($item_data_for_form_ri_display = $result_ri_edit_page->fetch_assoc())) { $_SESSION['action_error_parvareshi'] = "قلم یافت نشد."; header("Location: rental_items.php"); exit; }
                $stmt_ri_edit_page->close();
            } else $form_errors_ri_page_display['db_load'] = "خطا بارگذاری: " . $conn->error;
        } else {
            $page_title_ri_page_display = "افزودن قلم جدید به کرایه‌چی";
            $item_data_for_form_ri_display = ['ItemName'=>'','Description'=>'','QuantityTotal'=>1,'ImagePath'=>'','IsActive'=>1, 'QuantityAvailable'=>1];
        }
    }
} else $form_errors_ri_page_display['db_connection'] = "خطا اتصال دیتابیس.";
?>
<div class="page-header">
    <h1><?php echo $page_title_ri_page_display; ?></h1>
    <div class="page-header-actions">
        <a href="rental_items.php?action=<?php echo ($action_ri_page==='list'?'create':'list'); ?>" class="btn btn-<?php echo ($action_ri_page==='list'?'primary':'secondary');?>"><em class="bi <?php echo ($action_ri_page==='list'?'bi-plus-circle':'bi-list-ul');?> icon"></em> <?php echo ($action_ri_page==='list'?'افزودن قلم جدید':'لیست اقلام');?></a>
        <a href="index.php" class="btn btn-outline-secondary ms-2"><em class="bi bi-house-door icon"></em> داشبورد پرورشی</a>
    </div>
</div>

<?php if(isset($_SESSION['action_success_parvareshi'])):?><div class="alert alert-success alert-dismissible fade show"><button class="btn-close" data-bs-dismiss="alert"></button><?php echo $_SESSION['action_success_parvareshi']; unset($_SESSION['action_success_parvareshi']);?></div><?php endif;?>
<?php if(!empty($form_errors_ri_page_display)):?><div class="alert alert-danger alert-dismissible fade show"><strong>خطا:</strong><ul class="mb-0 ps-3"><?php foreach($form_errors_ri_page_display as $e_ri_p=>$e_msg_ri_p):echo "<li>".htmlspecialchars($e_msg_ri_p)."</li>";endforeach;?></ul><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif;?>

<?php if($action_ri_page === 'list'): ?>
    <div class="filter-search-bar mb-3"><form method="GET" class="row g-2 align-items-center"><div class="col-md-6"><input type="text" class="form-control form-control-sm" name="search_item_name" placeholder="جستجو در نام یا توضیحات قلم..." value="<?php echo htmlspecialchars($search_ri_name_list ?? '');?>"></div><div class="col-md-auto"><button type="submit" class="btn btn-info btn-sm">فیلتر</button></div><?php if(!empty($search_ri_name_list)):?><div class="col-md-auto"><a href="rental_items.php" class="btn btn-secondary btn-sm">پاک کردن</a></div><?php endif;?></form></div>
    <div class="card"><div class="card-body">
    <?php if(empty($rental_items_list_display)): ?><p class="text-center text-muted py-3">هیچ قلمی در کرایه‌چی ثبت نشده. <?php if(empty($search_ri_name_list)) echo '<a href="?action=create">یک قلم جدید اضافه کنید</a>.';?></p>
    <?php else: ?><div class="table-responsive"><table class="table table-hover table-sm align-middle">
        <thead class="table-light"><tr><th>#</th><th>تصویر</th><th>نام قلم</th><th>تعداد کل</th><th>تعداد موجود</th><th>وضعیت</th><th class="actions-column">عملیات</th></tr></thead>
        <tbody><?php foreach($rental_items_list_display as $idx_ri_l => $ri_l): ?>
            <tr><td><?php echo $idx_ri_l+1;?></td>
            <td><img src="<?php echo !empty($ri_l['ImagePath']) ? get_base_url().htmlspecialchars($ri_l['ImagePath']) : get_base_url().'assets/images/logo-placeholder.png';?>" alt="<?php echo htmlspecialchars($ri_l['ItemName']);?>" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;"></td>
            <td><a href="?action=edit&item_id=<?php echo $ri_l['ItemID'];?>"><?php echo htmlspecialchars($ri_l['ItemName']);?></a></td><td><?php echo $ri_l['QuantityTotal'];?></td><td><?php echo $ri_l['QuantityAvailable'];?></td><td><span class="badge bg-<?php echo $ri_l['IsActive']?'success':'secondary';?>"><?php echo $ri_l['IsActive']?'فعال':'غیرفعال';?></span></td>
            <td class="actions-cell"><a href="?action=edit&item_id=<?php echo $ri_l['ItemID'];?>" class="btn btn-sm btn-outline-info" title="ویرایش"><em class="bi bi-pencil-square"></em></a><button type="button" class="btn btn-sm btn-outline-danger btn-delete-rental-item" data-item-id="<?php echo $ri_l['ItemID'];?>" data-item-name="<?php echo htmlspecialchars($ri_l['ItemName']);?>"><em class="bi bi-trash3"></em></button></td></tr>
        <?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
    </div></div>
<?php elseif ($action_ri_page === 'create' || $action_ri_page === 'edit'): ?>
    <div class="card"><div class="card-body">
        <form method="POST" action="rental_items.php<?php echo ($action_ri_page==='edit'&&$item_id_ri_url_param)?'?action=edit&item_id='.$item_id_ri_url_param:'?action=create';?>" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_ri_form_val; ?>">
            <?php if($action_ri_page==='edit'&&$item_id_ri_url_param):?><input type="hidden" name="item_id" value="<?php echo $item_id_ri_url_param;?>"><?php endif;?>
            <div class="row">
                <div class="col-md-8 mb-3"><label for="ri_f_name" class="form-label">نام قلم <span class="text-danger">*</span></label><input type="text" class="form-control <?php echo isset($form_errors_ri_page_display['item_name'])?'is-invalid':'';?>" id="ri_f_name" name="item_name" value="<?php echo htmlspecialchars($item_data_for_form_ri_display['ItemName']??'');?>" required><?php if(isset($form_errors_ri_page_display['item_name'])):?><div class="invalid-feedback"><?php echo $form_errors_ri_page_display['item_name'];?></div><?php endif;?></div>
                <div class="col-md-4 mb-3"><label for="ri_f_qty" class="form-label">تعداد کل <span class="text-danger">*</span></label><input type="number" class="form-control <?php echo isset($form_errors_ri_page_display['quantity_total'])?'is-invalid':'';?>" id="ri_f_qty" name="quantity_total" value="<?php echo htmlspecialchars($item_data_for_form_ri_display['QuantityTotal']??'1');?>" min="0" required><?php if(isset($form_errors_ri_page_display['quantity_total'])):?><div class="invalid-feedback"><?php echo $form_errors_ri_page_display['quantity_total'];?></div><?php endif;?></div>
            </div>
             <?php if($action_ri_page === 'edit'): ?>
             <div class="mb-3"><label class="form-label">تعداد موجود فعلی:</label> <span class="fw-bold fs-5"><?php echo htmlspecialchars($item_data_for_form_ri_display['QuantityAvailable'] ?? '0'); ?></span> <small class="text-muted">(این مقدار توسط سیستم رزرو مدیریت می‌شود. برای تغییر دستی، با احتیاط عمل کنید یا از طریق صفحه رزروها اقدام نمایید.)</small></div>
            <?php endif; ?>
            <div class="mb-3"><label for="ri_f_desc" class="form-label">توضیحات</label><textarea class="form-control" id="ri_f_desc" name="description" rows="3"><?php echo htmlspecialchars($item_data_for_form_ri_display['Description']??'');?></textarea></div>
            <div class="mb-3"><label for="ri_f_img" class="form-label">مسیر تصویر (اختیاری)</label><input type="text" class="form-control" id="ri_f_img" name="image_path" value="<?php echo htmlspecialchars($item_data_for_form_ri_display['ImagePath']??'');?>" placeholder="مثال: assets/images/rentals/item.jpg"><small class="form-text text-muted">در صورت آپلود فایل، این مسیر باید به محل ذخیره فایل اشاره کند.</small></div>
            <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="ri_f_active" name="is_active" value="1" <?php echo (($item_data_for_form_ri_display['IsActive']??1)==1)?'checked':'';?>><label class="form-check-label" for="ri_f_active">قلم فعال و قابل کرایه باشد</label></div>
            <div class="form-actions"><button type="submit" name="save_rental_item" class="btn btn-success"><em class="bi bi-check-circle-fill icon"></em> ذخیره</button><a href="rental_items.php" class="btn btn-outline-secondary">انصراف</a></div>
        </form>
    </div></div>
<?php endif; ?>

<div class="modal fade" id="deleteRentalItemModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="rental_items.php" id="deleteRentalItemFormModal">
    <input type="hidden" name="csrf_token_delete_modal_ri" id="csrf_token_delete_modal_ri_input_val" value="">
    <input type="hidden" name="item_id_to_delete_confirmed" id="item_id_to_delete_modal_input_ri_val">
    <div class="modal-header"><h5 class="modal-title">تایید حذف قلم کرایه‌چی</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">آیا از حذف قلم <strong id="itemNameToDeleteModalRIVal"></strong> مطمئن هستید؟ <small class="text-danger d-block">توجه: اگر این قلم در رزروهای فعال استفاده شده باشد، حذف انجام نخواهد شد.</small></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button><button type="submit" name="delete_rental_item_confirmed" class="btn btn-danger">حذف</button></div>
    </form></div></div></div>
<script>
$(document).ready(function(){ $('.btn-delete-rental-item').on('click',function(){$('#item_id_to_delete_modal_input_ri_val').val($(this).data('item-id'));$('#itemNameToDeleteModalRIVal').text($(this).data('item-name'));$('#csrf_token_delete_modal_ri_input_val').val('<?php echo $csrf_token_ri_delete_val; ?>');new bootstrap.Modal(document.getElementById('deleteRentalItemModal')).show();});});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
