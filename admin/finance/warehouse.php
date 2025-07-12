<?php
require_once __DIR__ . '/../includes/header.php';

$action_wh_page_main = $_GET['action'] ?? 'list_items'; // list_items, create_item, edit_item, list_categories, create_category, edit_category
$item_id_wh_url_main = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
$category_id_wh_url_main = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

$wh_items_list_display = [];
$wh_categories_list_display = [];
$wh_item_data_for_form_display = null;
$wh_category_data_for_form_display = null;
$form_errors_wh_page_main = [];
$page_title_wh_page_main = "مدیریت انبار"; // Default, will be overridden

// CSRF Tokens
$csrf_token_wh_item_form_page = generate_csrf_token('warehouse_item_form_action');
$csrf_token_wh_category_form_page = generate_csrf_token('warehouse_category_form_action');
$csrf_token_wh_item_delete_page = generate_csrf_token('warehouse_item_delete_action');
$csrf_token_wh_category_delete_page = generate_csrf_token('warehouse_category_delete_action');


// --- Category Management Logic ---
if (str_contains($action_wh_page_main, 'category')) {
    $page_title_wh_page_main = "مدیریت دسته‌بندی‌های انبار";
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['save_wh_category'])) {
            if (!verify_csrf_token($_POST['csrf_token'] ?? '', $csrf_token_wh_category_form_page)) {
                $form_errors_wh_page_main['csrf_cat'] = "خطای CSRF.";
            } else {
                $csrf_token_wh_category_form_page = regenerate_csrf_token($csrf_token_wh_category_form_page);
                $cat_id_posted_page = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
                $cat_name_page = sanitize_input($_POST['category_name'] ?? '');
                $cat_desc_page = sanitize_input($_POST['category_description'] ?? null);
                $wh_category_data_for_form_display = $_POST;

                if (empty($cat_name_page)) $form_errors_wh_page_main['category_name'] = "نام دسته‌بندی الزامی است.";
                if(empty($form_errors_wh_page_main['category_name']) && $conn){
                    $sql_chk_cat_page = "SELECT CategoryID FROM WarehouseCategories WHERE CategoryName = ?";
                    $params_chk_cat_page = [$cat_name_page]; $types_chk_cat_page = "s";
                    if($cat_id_posted_page > 0) { $sql_chk_cat_page .= " AND CategoryID != ?"; $params_chk_cat_page[] = $cat_id_posted_page; $types_chk_cat_page .= "i"; }
                    $stmt_chk_cat_page_exec = $conn->prepare($sql_chk_cat_page);
                    if($stmt_chk_cat_page_exec){ $stmt_chk_cat_page_exec->bind_param($types_chk_cat_page, ...$params_chk_cat_page); $stmt_chk_cat_page_exec->execute(); if($stmt_chk_cat_page_exec->get_result()->num_rows > 0) $form_errors_wh_page_main['category_name'] = "دسته‌بندی با این نام موجود است."; $stmt_chk_cat_page_exec->close(); }
                }

                if (empty($form_errors_wh_page_main)) {
                    if ($conn) {
                        if ($cat_id_posted_page > 0) {
                            $stmt_cat_save_page = $conn->prepare("UPDATE WarehouseCategories SET CategoryName = ?, Description = ? WHERE CategoryID = ?");
                            if($stmt_cat_save_page){ $stmt_cat_save_page->bind_param("ssi", $cat_name_page, $cat_desc_page, $cat_id_posted_page); if ($stmt_cat_save_page->execute()) { $_SESSION['action_success_finance'] = "دسته‌بندی بروزرسانی شد."; header("Location: warehouse.php?action=list_categories"); exit; } else $form_errors_wh_page_main['db_cat'] = "خطا بروزرسانی: ".$stmt_cat_save_page->error; $stmt_cat_save_page->close(); }
                        } else {
                            $stmt_cat_save_page = $conn->prepare("INSERT INTO WarehouseCategories (CategoryName, Description) VALUES (?, ?)");
                            if($stmt_cat_save_page){ $stmt_cat_save_page->bind_param("ss", $cat_name_page, $cat_desc_page); if ($stmt_cat_save_page->execute()) { $_SESSION['action_success_finance'] = "دسته‌بندی ایجاد شد."; header("Location: warehouse.php?action=list_categories"); exit; } else $form_errors_wh_page_main['db_cat'] = "خطا ایجاد: ".$stmt_cat_save_page->error; $stmt_cat_save_page->close(); }
                        }
                    } else $form_errors_wh_page_main['db_cat'] = "عدم اتصال دیتابیس.";
                }
                $action_wh_page_main = ($cat_id_posted_page > 0) ? 'edit_category' : 'create_category';
            }
        } elseif (isset($_POST['delete_wh_category_confirmed'])) {
            if (!verify_csrf_token($_POST['csrf_token_delete_modal_wh_cat'] ?? '', $csrf_token_wh_category_delete_page)) { $_SESSION['action_error_finance'] = "خطای CSRF."; }
            else {
                $csrf_token_wh_category_delete_page = regenerate_csrf_token($csrf_token_wh_category_delete_page);
                $cat_id_to_delete_page = (int)($_POST['category_id_to_delete_confirmed'] ?? 0);
                if ($cat_id_to_delete_page > 0 && $conn) {
                    $conn->begin_transaction();
                    try {
                        $stmt_update_items = $conn->prepare("UPDATE WarehouseItems SET CategoryID = NULL WHERE CategoryID = ?");
                        if(!$stmt_update_items) throw new Exception("خطای آماده سازی بروزرسانی اقلام: ".$conn->error);
                        $stmt_update_items->bind_param("i", $cat_id_to_delete_page);
                        if(!$stmt_update_items->execute()) throw new Exception("خطا در بروزرسانی اقلام مرتبط: ".$stmt_update_items->error);
                        $stmt_update_items->close();

                        $stmt_del_cat_page = $conn->prepare("DELETE FROM WarehouseCategories WHERE CategoryID = ?");
                        if(!$stmt_del_cat_page) throw new Exception("خطای آماده سازی حذف دسته: ".$conn->error);
                        $stmt_del_cat_page->bind_param("i", $cat_id_to_delete_page);
                        if ($stmt_del_cat_page->execute()) {
                            if($stmt_del_cat_page->affected_rows > 0) { $conn->commit(); $_SESSION['action_success_finance'] = "دسته‌بندی حذف شد."; }
                            else { $conn->rollback(); $_SESSION['action_error_finance'] = "دسته‌بندی یافت نشد.";}
                        } else throw new Exception("خطا در حذف دسته: ".$stmt_del_cat_page->error);
                        $stmt_del_cat_page->close();
                    } catch (Exception $e){
                        $conn->rollback();
                        $_SESSION['action_error_finance'] = $e->getMessage();
                    }
                } else $_SESSION['action_error_finance'] = "شناسه نامعتبر.";
            }
            header("Location: warehouse.php?action=list_categories"); exit;
        }
    }
    if ($conn) { // Fetch data for category list or edit form (if not POST error)
        if ($action_wh_page_main === 'list_categories') {
            $res_cat_list_page = $conn->query("SELECT wc.CategoryID, wc.CategoryName, wc.Description, COUNT(wi.ItemID) as ItemCount FROM WarehouseCategories wc LEFT JOIN WarehouseItems wi ON wc.CategoryID = wi.CategoryID GROUP BY wc.CategoryID ORDER BY wc.CategoryName ASC");
            if($res_cat_list_page) while($row_cat_l=$res_cat_list_page->fetch_assoc()) $wh_categories_list_display[] = $row_cat_l;
        } elseif (($action_wh_page_main === 'edit_category' || $action_wh_page_main === 'create_category') && !$wh_category_data_for_form_display) {
            if ($action_wh_page_main === 'edit_category' && $category_id_wh_url_main > 0) {
                $stmt_cat_edit_page = $conn->prepare("SELECT * FROM WarehouseCategories WHERE CategoryID = ?");
                if($stmt_cat_edit_page){ $stmt_cat_edit_page->bind_param("i", $category_id_wh_url_main); $stmt_cat_edit_page->execute(); $res_cat_edit_page = $stmt_cat_edit_page->get_result(); if(!($wh_category_data_for_form_display = $res_cat_edit_page->fetch_assoc())){ $_SESSION['action_error_finance'] = "دسته یافت نشد."; header("Location: warehouse.php?action=list_categories"); exit;} $stmt_cat_edit_page->close();}
            } else { $wh_category_data_for_form_display = ['CategoryName' => '', 'Description' => '']; }
        }
    }
}
// --- Item Management Logic ---
else { // Default to item actions if not category action
    $page_title_wh_page_main = "مدیریت اقلام انبار";
    if($conn && ($action_wh_page_main === 'create_item' || $action_wh_page_main === 'edit_item')){
        $res_cat_list_form_item = $conn->query("SELECT CategoryID, CategoryName FROM WarehouseCategories ORDER BY CategoryName ASC");
        if($res_cat_list_form_item) while($row_cat_form_item = $res_cat_list_form_item->fetch_assoc()) $wh_categories_list_display[] = $row_cat_form_item;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['save_wh_item'])) {
             if (!verify_csrf_token($_POST['csrf_token'] ?? '', $csrf_token_wh_item_form_page)) { $form_errors_wh_page_main['csrf_item'] = "خطای CSRF."; }
             else {
                $csrf_token_wh_item_form_page = regenerate_csrf_token($csrf_token_wh_item_form_page);
                $item_id_posted_page = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
                $item_name_page = sanitize_input($_POST['item_name'] ?? '');
                $item_desc_page = sanitize_input($_POST['item_description'] ?? null);
                $category_id_fk_page = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
                $qty_stock_page = isset($_POST['quantity_in_stock']) ? (int)$_POST['quantity_in_stock'] : 0;
                $unit_page = sanitize_input($_POST['unit'] ?? 'عدد');
                $price_page = !empty($_POST['purchase_price']) ? filter_var(str_replace(',','',$_POST['purchase_price']), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : null;
                $supplier_page = sanitize_input($_POST['supplier_info'] ?? null);
                $notes_page = sanitize_input($_POST['notes'] ?? null);
                // ImagePath is not handled with file upload here

                $wh_item_data_for_form_display = $_POST;

                if(empty($item_name_page)) $form_errors_wh_page_main['item_name'] = "نام قلم الزامی است.";
                if($qty_stock_page < 0) $form_errors_wh_page_main['quantity_in_stock'] = "موجودی نمی‌تواند منفی باشد.";

                if(empty($form_errors_wh_page_main)){
                    if($conn){
                        $current_admin_id_wh_item_save = get_current_user_id();
                        if($item_id_posted_page > 0){
                             $stmt_item_save_page = $conn->prepare("UPDATE WarehouseItems SET ItemName=?, Description=?, CategoryID=?, QuantityInStock=?, Unit=?, PurchasePrice=?, SupplierInfo=?, Notes=?, LastUpdatedAt=NOW(), LastUpdatedByUserID=? WHERE ItemID=?");
                             if($stmt_item_save_page){ $stmt_item_save_page->bind_param("ssiisdssii", $item_name_page, $item_desc_page, $category_id_fk_page, $qty_stock_page, $unit_page, $price_page, $supplier_page, $notes_page, $current_admin_id_wh_item_save, $item_id_posted_page); if($stmt_item_save_page->execute()){ $_SESSION['action_success_finance'] = "قلم بروزرسانی شد."; header("Location: warehouse.php"); exit;} else $form_errors_wh_page_main['db_item']="خطا بروزرسانی: ".$stmt_item_save_page->error; $stmt_item_save_page->close(); }
                        } else {
                            $qty_available_new_item_page = $qty_stock_page; // On create, available = total
                            $stmt_item_save_page = $conn->prepare("INSERT INTO WarehouseItems (ItemName, Description, CategoryID, QuantityInStock, QuantityAvailable, Unit, PurchasePrice, SupplierInfo, Notes, DateAdded, AddedByUserID) VALUES (?,?,?,?,?,?,?,?,NOW(),?)");
                            if($stmt_item_save_page){ $stmt_item_save_page->bind_param("ssiiisdssi", $item_name_page, $item_desc_page, $category_id_fk_page, $qty_stock_page, $qty_available_new_item_page, $unit_page, $price_page, $supplier_page, $notes_page, $current_admin_id_wh_item_save); if($stmt_item_save_page->execute()){ $_SESSION['action_success_finance'] = "قلم ایجاد شد."; header("Location: warehouse.php"); exit;} else $form_errors_wh_page_main['db_item']="خطا ایجاد: ".$stmt_item_save_page->error; $stmt_item_save_page->close(); }
                        }
                    } else $form_errors_wh_page_main['db_item'] = "عدم اتصال دیتابیس.";
                }
                $action_wh_page_main = ($item_id_posted_page > 0) ? 'edit_item' : 'create_item';
            }
        } elseif (isset($_POST['delete_wh_item_confirmed'])) {
            if (!verify_csrf_token($_POST['csrf_token_delete_modal_wh_item'] ?? '', $csrf_token_wh_item_delete_page)) { $_SESSION['action_error_finance'] = "خطای CSRF."; }
            else {
                $csrf_token_wh_item_delete_page = regenerate_csrf_token($csrf_token_wh_item_delete_page);
                $item_id_to_delete_page = (int)($_POST['item_id_to_delete_confirmed'] ?? 0);
                 if ($item_id_to_delete_page > 0 && $conn) {
                    $stmt_del_item_page = $conn->prepare("DELETE FROM WarehouseItems WHERE ItemID = ?");
                    if($stmt_del_item_page){ $stmt_del_item_page->bind_param("i", $item_id_to_delete_page); if($stmt_del_item_page->execute()){ $_SESSION['action_success_finance'] = ($stmt_del_item_page->affected_rows>0)?"قلم حذف شد.":"قلم یافت نشد.";} else { if($conn->errno == 1451) $_SESSION['action_error_finance'] = "این قلم در رزروها یا سایر بخش‌ها استفاده شده و قابل حذف نیست."; else $_SESSION['action_error_finance'] = "خطا در حذف: ".$stmt_del_item_page->error;} $stmt_del_item_page->close(); }
                 } else $_SESSION['action_error_finance'] = "شناسه نامعتبر.";
            }
            header("Location: warehouse.php"); exit;
        }
    }
    if ($conn) { // Fetch data for item list or edit form
        if ($action_wh_page_main === 'list_items') {
            $page_title_wh_page_main = "لیست اقلام انبار";
             $res_item_list_page = $conn->query("SELECT wi.*, wc.CategoryName FROM WarehouseItems wi LEFT JOIN WarehouseCategories wc ON wi.CategoryID = wc.CategoryID ORDER BY wi.ItemName ASC");
            if($res_item_list_page) while($row_item_l = $res_item_list_page->fetch_assoc()) $wh_items_list_display[] = $row_item_l;
        } elseif (($action_wh_page_main === 'edit_item' || $action_wh_page_main === 'create_item') && !$wh_item_data_for_form_display) {
             if ($action_wh_page_main === 'edit_item' && $item_id_wh_url_main > 0) {
                $stmt_item_edit_page = $conn->prepare("SELECT * FROM WarehouseItems WHERE ItemID = ?");
                if($stmt_item_edit_page){ $stmt_item_edit_page->bind_param("i", $item_id_wh_url_main); $stmt_item_edit_page->execute(); $res_item_edit_page = $stmt_item_edit_page->get_result(); if(!($wh_item_data_for_form_display = $res_item_edit_page->fetch_assoc())){ $_SESSION['action_error_finance'] = "قلم یافت نشد."; header("Location: warehouse.php"); exit;} $stmt_item_edit_page->close();}
            } else {
                $wh_item_data_for_form_display = ['ItemName'=>'','Description'=>'','CategoryID'=>null,'QuantityInStock'=>0, 'QuantityAvailable'=>0, 'Unit'=>'عدد','PurchasePrice'=>'','SupplierInfo'=>'','Notes'=>'', 'IsActive'=>1];
            }
        }
    }
}
?>
<div class="page-header"><h1><?php echo $page_title_wh_page_main; ?></h1><div class="page-header-actions">
    <?php if(str_contains($action_wh_page_main, 'category')): ?>
        <a href="warehouse.php?action=list_categories" class="btn <?php echo $action_wh_page_main==='list_categories'?'btn-primary':'btn-secondary';?>"><em class="bi bi-tags-fill icon"></em> لیست دسته‌بندی‌ها</a>
        <a href="warehouse.php?action=create_category" class="btn <?php echo $action_wh_page_main==='create_category'?'btn-primary':'btn-outline-secondary';?> ms-2"><em class="bi bi-tag icon"></em> ایجاد دسته‌بندی</a>
        <a href="warehouse.php?action=list_items" class="btn btn-outline-info ms-2"><em class="bi bi-box-seam icon"></em> مدیریت اقلام</a>
    <?php else: ?>
        <a href="warehouse.php?action=list_items" class="btn <?php echo $action_wh_page_main==='list_items'?'btn-primary':'btn-secondary';?>"><em class="bi bi-list-ul icon"></em> لیست اقلام</a>
        <a href="warehouse.php?action=create_item" class="btn <?php echo $action_wh_page_main==='create_item'?'btn-primary':'btn-outline-secondary';?> ms-2"><em class="bi bi-plus-circle icon"></em> افزودن قلم</a>
        <a href="warehouse.php?action=list_categories" class="btn btn-outline-info ms-2"><em class="bi bi-tags icon"></em> مدیریت دسته‌بندی‌ها</a>
    <?php endif; ?>
    <a href="index.php" class="btn btn-outline-dark ms-2"><em class="bi bi-coin icon"></em> داشبورد مالی</a>
</div></div>

<?php if(isset($_SESSION['action_success_finance'])):?><div class="alert alert-success alert-dismissible fade show"><button class="btn-close" data-bs-dismiss="alert"></button><?php echo $_SESSION['action_success_finance']; unset($_SESSION['action_success_finance']);?></div><?php endif;?>
<?php if(isset($_SESSION['action_error_finance'])):?><div class="alert alert-danger alert-dismissible fade show"><button class="btn-close" data-bs-dismiss="alert"></button><?php echo $_SESSION['action_error_finance']; unset($_SESSION['action_error_finance']);?></div><?php endif;?>
<?php if(!empty($form_errors_wh_page_main)):?><div class="alert alert-danger alert-dismissible fade show"><strong>خطا:</strong><ul class="mb-0 ps-3"><?php foreach($form_errors_wh_page_main as $e_wh_p=>$e_msg_wh_p):echo "<li>".htmlspecialchars($e_msg_wh_p)."</li>";endforeach;?></ul><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif;?>

<?php if($action_wh_page_main === 'list_items'): ?>
    <div class="card"><div class="card-body">
    <?php if(empty($wh_items_list_display)): ?><p class="text-center text-muted py-3">هیچ قلمی در انبار ثبت نشده.</p>
    <?php else: ?><div class="table-responsive"><table class="table table-hover table-sm align-middle">
        <thead class="table-light"><tr><th>#</th><th>تصویر</th><th>نام قلم</th><th>دسته‌بندی</th><th>موجودی کل</th><th>موجودی قابل استفاده</th><th>واحد</th><th>قیمت خرید</th><th class="actions-column">عملیات</th></tr></thead>
        <tbody><?php foreach($wh_items_list_display as $idx_whi_l => $whi_l): ?>
            <tr><td><?php echo $idx_whi_l+1;?></td>
            <td><img src="<?php echo !empty($whi_l['ImagePath']) ? get_base_url().htmlspecialchars($whi_l['ImagePath']) : get_base_url().'assets/images/logo-placeholder.png';?>" alt="<?php echo htmlspecialchars($whi_l['ItemName']);?>" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;"></td>
            <td><a href="?action=edit_item&item_id=<?php echo $whi_l['ItemID'];?>"><?php echo htmlspecialchars($whi_l['ItemName']);?></a></td><td><?php echo htmlspecialchars($whi_l['CategoryName']?:'---');?></td><td><?php echo $whi_l['QuantityInStock'];?></td><td><?php echo $whi_l['QuantityAvailable'];?></td><td><?php echo htmlspecialchars($whi_l['Unit']);?></td><td><?php echo $whi_l['PurchasePrice']?number_format($whi_l['PurchasePrice']):'---';?></td>
            <td class="actions-cell"><a href="?action=edit_item&item_id=<?php echo $whi_l['ItemID'];?>" class="btn btn-sm btn-outline-info" title="ویرایش"><em class="bi bi-pencil-square"></em></a><button type="button" class="btn btn-sm btn-outline-danger btn-delete-wh-item" data-item-id="<?php echo $whi_l['ItemID'];?>" data-item-name="<?php echo htmlspecialchars($whi_l['ItemName']);?>"><em class="bi bi-trash3"></em></button></td></tr>
        <?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
    </div></div>
<?php elseif($action_wh_page_main === 'create_item' || $action_wh_page_main === 'edit_item'): ?>
    <div class="card"><div class="card-body">
        <form method="POST" action="warehouse.php<?php echo ($action_wh_page_main==='edit_item'&&$item_id_wh_url_main)?'?action=edit_item&item_id='.$item_id_wh_url_main:'?action=create_item';?>" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_wh_item_form_page; ?>">
            <?php if($action_wh_page_main==='edit_item'&&$item_id_wh_url_main):?><input type="hidden" name="item_id" value="<?php echo $item_id_wh_url_main;?>"><?php endif;?>
            <div class="row"><div class="col-md-8 mb-3"><label for="whi_f_name" class="form-label">نام قلم <span class="text-danger">*</span></label><input type="text" class="form-control <?php echo isset($form_errors_wh_page_main['item_name'])?'is-invalid':'';?>" id="whi_f_name" name="item_name" value="<?php echo htmlspecialchars($wh_item_data_for_form_display['ItemName']??'');?>" required><?php if(isset($form_errors_wh_page_main['item_name'])):?><div class="invalid-feedback"><?php echo $form_errors_wh_page_main['item_name'];?></div><?php endif;?></div>
            <div class="col-md-4 mb-3"><label for="whi_f_cat" class="form-label">دسته‌بندی</label><select class="form-select" id="whi_f_cat" name="category_id"><option value="">-- بدون دسته --</option><?php foreach($wh_categories_list_display as $cat_opt_f):?><option value="<?php echo $cat_opt_f['CategoryID'];?>" <?php echo (($wh_item_data_for_form_display['CategoryID']??null)==$cat_opt_f['CategoryID'])?'selected':'';?>><?php echo htmlspecialchars($cat_opt_f['CategoryName']);?></option><?php endforeach;?></select></div></div>
            <div class="row"><div class="col-md-4 mb-3"><label for="whi_f_qty" class="form-label">تعداد کل در انبار <span class="text-danger">*</span></label><input type="number" class="form-control <?php echo isset($form_errors_wh_page_main['quantity_in_stock'])?'is-invalid':'';?>" id="whi_f_qty" name="quantity_in_stock" value="<?php echo htmlspecialchars($wh_item_data_for_form_display['QuantityInStock']??'0');?>" min="0" required><?php if(isset($form_errors_wh_page_main['quantity_in_stock'])):?><div class="invalid-feedback"><?php echo $form_errors_wh_page_main['quantity_in_stock'];?></div><?php endif;?></div>
            <div class="col-md-4 mb-3"><label for="whi_f_unit" class="form-label">واحد شمارش <span class="text-danger">*</span></label><input type="text" class="form-control" id="whi_f_unit" name="unit" value="<?php echo htmlspecialchars($wh_item_data_for_form_display['Unit']??'عدد');?>" required></div>
            <div class="col-md-4 mb-3"><label for="whi_f_price" class="form-label">قیمت خرید (تومان)</label><input type="text" class="form-control" id="whi_f_price" name="purchase_price" value="<?php echo isset($wh_item_data_for_form_display['PurchasePrice'])?number_format($wh_item_data_for_form_display['PurchasePrice'],0,'',','):'';?>" placeholder="مثال: 150,000"></div></div>
            <?php if($action_wh_page_main === 'edit_item'): ?><div class="mb-3"><label class="form-label">تعداد موجود قابل استفاده:</label> <span class="fw-bold fs-5"><?php echo htmlspecialchars($wh_item_data_for_form_display['QuantityAvailable'] ?? '0'); ?></span> <small class="text-muted">(این مقدار با رزروها تغییر می‌کند.)</small></div><?php endif; ?>
            <div class="mb-3"><label for="whi_f_supplier" class="form-label">تامین کننده</label><input type="text" class="form-control" id="whi_f_supplier" name="supplier_info" value="<?php echo htmlspecialchars($wh_item_data_for_form_display['SupplierInfo']??'');?>"></div>
            <div class="mb-3"><label for="whi_f_desc" class="form-label">توضیحات قلم</label><textarea class="form-control" id="whi_f_desc" name="item_description" rows="2"><?php echo htmlspecialchars($wh_item_data_for_form_display['Description']??'');?></textarea></div>
            <div class="mb-3"><label for="whi_f_notes" class="form-label">یادداشت‌های داخلی</label><textarea class="form-control" id="whi_f_notes" name="notes" rows="2"><?php echo htmlspecialchars($wh_item_data_for_form_display['Notes']??'');?></textarea></div>
            <div class="form-actions"><button type="submit" name="save_wh_item" class="btn btn-success"><em class="bi bi-check-circle-fill icon"></em> ذخیره</button><a href="warehouse.php" class="btn btn-outline-secondary">انصراف</a></div>
        </form>
    </div></div>
<?php elseif($action_wh_page_main === 'list_categories'): ?>
     <div class="card"><div class="card-body">
    <?php if(empty($wh_categories_list_display)): ?><p class="text-center text-muted py-3">هیچ دسته‌بندی برای انبار تعریف نشده. <a href="?action=create_category">یک دسته‌بندی جدید ایجاد کنید</a>.</p>
    <?php else: ?><div class="table-responsive"><table class="table table-hover table-sm">
        <thead class="table-light"><tr><th>#</th><th>نام دسته‌بندی</th><th>توضیحات</th><th>تعداد اقلام در این دسته</th><th class="actions-column">عملیات</th></tr></thead>
        <tbody><?php foreach($wh_categories_list_display as $idx_whc_l => $whc_l): ?>
            <tr><td><?php echo $idx_whc_l+1;?></td><td><a href="?action=edit_category&category_id=<?php echo $whc_l['CategoryID'];?>"><?php echo htmlspecialchars($whc_l['CategoryName']);?></a></td><td><?php echo htmlspecialchars(mb_substr($whc_l['Description']??'',0,100).(mb_strlen($whc_l['Description']??'')>100?'...':''));?></td><td><a href="warehouse.php?action=list_items&filter_category_id=<?php echo $whc_l['CategoryID'];?>"><?php echo $whc_l['ItemCount'];?></a></td>
            <td class="actions-cell"><a href="?action=edit_category&category_id=<?php echo $whc_l['CategoryID'];?>" class="btn btn-sm btn-outline-info" title="ویرایش"><em class="bi bi-pencil-square"></em></a><button type="button" class="btn btn-sm btn-outline-danger btn-delete-wh-category" data-category-id="<?php echo $whc_l['CategoryID'];?>" data-category-name="<?php echo htmlspecialchars($whc_l['CategoryName']);?>"><em class="bi bi-trash3"></em></button></td></tr>
        <?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
    </div></div>
<?php elseif($action_wh_page_main === 'create_category' || $action_wh_page_main === 'edit_category'): ?>
    <div class="card"><div class="card-body">
        <form method="POST" action="warehouse.php<?php echo ($action_wh_page_main==='edit_category'&&$category_id_wh_url_main)?'?action=edit_category&category_id='.$category_id_wh_url_main:'?action=create_category';?>" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_wh_category_form_page; ?>">
            <?php if($action_wh_page_main==='edit_category'&&$category_id_wh_url_main):?><input type="hidden" name="category_id" value="<?php echo $category_id_wh_url_main;?>"><?php endif;?>
            <div class="mb-3"><label for="whc_f_name" class="form-label">نام دسته‌بندی <span class="text-danger">*</span></label><input type="text" class="form-control <?php echo isset($form_errors_wh_page_main['category_name'])?'is-invalid':'';?>" id="whc_f_name" name="category_name" value="<?php echo htmlspecialchars($wh_category_data_for_form_display['CategoryName']??'');?>" required><?php if(isset($form_errors_wh_page_main['category_name'])):?><div class="invalid-feedback"><?php echo $form_errors_wh_page_main['category_name'];?></div><?php endif;?></div>
            <div class="mb-3"><label for="whc_f_desc" class="form-label">توضیحات</label><textarea class="form-control" id="whc_f_desc" name="category_description" rows="3"><?php echo htmlspecialchars($wh_category_data_for_form_display['Description']??'');?></textarea></div>
            <div class="form-actions"><button type="submit" name="save_wh_category" class="btn btn-success"><em class="bi bi-check-circle-fill icon"></em> ذخیره</button><a href="warehouse.php?action=list_categories" class="btn btn-outline-secondary">انصراف</a></div>
        </form>
    </div></div>
<?php endif; ?>

<div class="modal fade" id="deleteWHItemModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="warehouse.php" id="deleteWHItemFormModal"><input type="hidden" name="csrf_token_delete_modal_wh_item" id="csrf_token_delete_modal_wh_item_input_val" value=""><input type="hidden" name="item_id_to_delete_confirmed" id="item_id_to_delete_modal_input_whi_val"><div class="modal-header"><h5 class="modal-title">تایید حذف قلم</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">آیا از حذف <strong id="itemNameWHToDeleteModalVal"></strong> مطمئن هستید؟</div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button><button type="submit" name="delete_wh_item_confirmed" class="btn btn-danger">حذف</button></div></form></div></div></div>
<div class="modal fade" id="deleteWHCategoryModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="warehouse.php" id="deleteWHCategoryFormModal"><input type="hidden" name="csrf_token_delete_modal_wh_cat" id="csrf_token_delete_modal_wh_cat_input_val" value=""><input type="hidden" name="category_id_to_delete_confirmed" id="category_id_to_delete_modal_input_whc_val"><div class="modal-header"><h5 class="modal-title">تایید حذف دسته‌بندی</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">آیا از حذف <strong id="categoryNameWHToDeleteModalVal"></strong> مطمئن هستید؟ <small class='text-danger d-block'>توجه: اقلام موجود در این دسته‌بندی، بدون دسته خواهند شد.</small></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button><button type="submit" name="delete_wh_category_confirmed" class="btn btn-danger">حذف</button></div></form></div></div></div>

<script>
$(document).ready(function(){
    $('.btn-delete-wh-item').on('click',function(){$('#item_id_to_delete_modal_input_whi_val').val($(this).data('item-id'));$('#itemNameWHToDeleteModalVal').text($(this).data('item-name'));$('#csrf_token_delete_modal_wh_item_input_val').val('<?php echo $csrf_token_wh_item_delete_page; ?>');new bootstrap.Modal(document.getElementById('deleteWHItemModal')).show();});
    $('.btn-delete-wh-category').on('click',function(){$('#category_id_to_delete_modal_input_whc_val').val($(this).data('category-id'));$('#categoryNameWHToDeleteModalVal').text($(this).data('category-name'));$('#csrf_token_delete_modal_wh_cat_input_val').val('<?php echo $csrf_token_wh_category_delete_page; ?>');new bootstrap.Modal(document.getElementById('deleteWHCategoryModal')).show();});
    $('input[name="purchase_price"]').on('keyup', function(event) { if (event.which >= 37 && event.which <= 40) return; $(this).val(function(index, value) { return value.replace(/\D/g, "").replace(/\B(?=(\d{3})+(?!\d))/g, ",");});});
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
