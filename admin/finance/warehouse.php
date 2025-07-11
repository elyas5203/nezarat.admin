<?php
// admin/finance/warehouse.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth

$csrf_token_warehouse = generate_csrf_token('finance_warehouse_action');
$errors_wh = [];
$edit_mode_wh = false;
$item_wh_to_edit_values = ['WarehouseItemID' => null, 'ItemName' => '', 'Description' => '', 'Quantity' => 0, 'UnitPrice' => '', 'CategoryID' => null];

// Handle Form Submission (Create or Update Warehouse Item)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_warehouse_item'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'finance_warehouse_action')) {
        $errors_wh[] = 'خطای CSRF!';
    } else {
        $item_id_wh_post = isset($_POST['item_id_wh']) && is_numeric($_POST['item_id_wh']) ? (int)$_POST['item_id_wh'] : null;
        $item_wh_to_edit_values['ItemName'] = sanitize_input($_POST['item_name_wh'] ?? '');
        $item_wh_to_edit_values['Description'] = sanitize_input($_POST['description_wh'] ?? '');
        $item_wh_to_edit_values['Quantity'] = isset($_POST['quantity_wh']) && is_numeric($_POST['quantity_wh']) ? (int)$_POST['quantity_wh'] : 0;
        // Ensure UnitPrice is treated as float, and NULL if empty string after sanitizing
        $unit_price_input = preg_replace('/[^0-9.]/', '', sanitize_input($_POST['unit_price_wh'] ?? ''));
        $item_wh_to_edit_values['UnitPrice'] = ($unit_price_input !== '') ? floatval($unit_price_input) : null;

        if($item_id_wh_post) $item_wh_to_edit_values['WarehouseItemID'] = $item_id_wh_post;
        $edit_mode_wh = ($item_id_wh_post !== null);

        if (empty($item_wh_to_edit_values['ItemName'])) $errors_wh[] = "نام قلم انبار الزامی است.";
        if ($item_wh_to_edit_values['Quantity'] < 0) $errors_wh[] = "تعداد موجودی نمی‌تواند منفی باشد.";
        if ($item_wh_to_edit_values['UnitPrice'] !== null && $item_wh_to_edit_values['UnitPrice'] < 0) $errors_wh[] = "قیمت واحد نمی‌تواند منفی باشد.";

        $sql_check_wh_name = "SELECT WarehouseItemID FROM WarehouseItems WHERE ItemName = ?";
        $params_wh_name = [$item_wh_to_edit_values['ItemName']]; $types_wh_name = "s";
        if ($item_id_wh_post) { $sql_check_wh_name .= " AND WarehouseItemID != ?"; $params_wh_name[] = $item_id_wh_post; $types_wh_name .= "i"; }
        $stmt_check_wh_name = $conn->prepare($sql_check_wh_name);
        if ($stmt_check_wh_name) {
            $stmt_check_wh_name->bind_param($types_wh_name, ...$params_wh_name); $stmt_check_wh_name->execute();
            if ($stmt_check_wh_name->get_result()->num_rows > 0) $errors_wh[] = "قلم دیگری با این نام در انبار موجود است.";
            $stmt_check_wh_name->close();
        } else { $errors_wh[] = "خطا در بررسی نام قلم انبار: " . $conn->error; }

        if (empty($errors_wh)) {
            if ($item_id_wh_post) {
                $stmt_wh_db_action = $conn->prepare("UPDATE WarehouseItems SET ItemName = ?, Description = ?, Quantity = ?, UnitPrice = ?, LastStockUpdate=NOW() WHERE WarehouseItemID = ?");
                if($stmt_wh_db_action) { $stmt_wh_db_action->bind_param("ssidi", $item_wh_to_edit_values['ItemName'], $item_wh_to_edit_values['Description'], $item_wh_to_edit_values['Quantity'], $item_wh_to_edit_values['UnitPrice'], $item_id_wh_post);
                    if($stmt_wh_db_action->execute()) $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'قلم انبار ویرایش شد.']; else $errors_wh[] = "خطا ویرایش: " . $stmt_wh_db_action->error; $stmt_wh_db_action->close();
                } else $errors_wh[] = "خطا آماده سازی ویرایش: " . $conn->error;
            } else {
                $stmt_wh_db_action = $conn->prepare("INSERT INTO WarehouseItems (ItemName, Description, Quantity, UnitPrice, LastStockUpdate) VALUES (?, ?, ?, ?, NOW())");
                 if($stmt_wh_db_action) { $stmt_wh_db_action->bind_param("ssid", $item_wh_to_edit_values['ItemName'], $item_wh_to_edit_values['Description'], $item_wh_to_edit_values['Quantity'], $item_wh_to_edit_values['UnitPrice']);
                    if($stmt_wh_db_action->execute()) $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'قلم به انبار افزوده شد.']; else $errors_wh[] = "خطا ایجاد: " . $stmt_wh_db_action->error; $stmt_wh_db_action->close();
                } else $errors_wh[] = "خطا آماده سازی ایجاد: " . $conn->error;
            }
            if(empty($errors_wh)) { regenerate_csrf_token('finance_warehouse_action'); header("Location: warehouse.php"); exit; }
        }
    }
    $csrf_token_warehouse = regenerate_csrf_token('finance_warehouse_action');
}

if (isset($_GET['edit_item_id']) && is_numeric($_GET['edit_item_id']) && $_SERVER["REQUEST_METHOD"] != "POST") {
    $edit_id_wh_get_val = (int)$_GET['edit_item_id'];
    $stmt_edit_wh_get_val = $conn->prepare("SELECT WarehouseItemID, ItemName, Description, Quantity, UnitPrice, CategoryID FROM WarehouseItems WHERE WarehouseItemID = ?");
    if ($stmt_edit_wh_get_val) { $stmt_edit_wh_get_val->bind_param("i", $edit_id_wh_get_val); $stmt_edit_wh_get_val->execute(); $result_edit_wh_get_val = $stmt_edit_wh_get_val->get_result();
        if ($data_wh_get_val = $result_edit_wh_get_val->fetch_assoc()) { $item_wh_to_edit_values = $data_wh_get_val; $edit_mode_wh = true; }
        else $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "قلم انبار یافت نشد."]; $stmt_edit_wh_get_val->close();
    } else $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "خطا بارگذاری قلم: " . $conn->error];
}
if (isset($_GET['delete_item_id']) && is_numeric($_GET['delete_item_id'])) {
    if (!isset($_GET['csrf_token']) || !verify_csrf_token($_GET['csrf_token'], 'finance_warehouse_action')) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطای CSRF! عملیات حذف نامعتبر.'];
    } else {
        $delete_id_wh_get_val = (int)$_GET['delete_item_id'];
        // TODO: Add dependency check if warehouse items are logged in transactions (e.g., in a WarehouseLogs table)
        // For now, direct delete
        $stmt_delete_wh_page = $conn->prepare("DELETE FROM WarehouseItems WHERE WarehouseItemID = ?");
        if ($stmt_delete_wh_page) {
            $stmt_delete_wh_page->bind_param("i", $delete_id_wh_get_val);
            if ($stmt_delete_wh_page->execute()) {
                if ($stmt_delete_wh_page->affected_rows > 0) $_SESSION['flash_message'] = ['type' => 'success', 'text' => "قلم از انبار با موفقیت حذف شد."];
                else $_SESSION['flash_message'] = ['type' => 'warning', 'text' => "قلم برای حذف یافت نشد یا قبلاً حذف شده است."];
            } else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "خطا در عملیات حذف از انبار: " . $stmt_delete_wh_page->error];}
            $stmt_delete_wh_page->close();
        } else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "خطا در آماده سازی کوئری حذف از انبار: " . $conn->error];}
    }
    regenerate_csrf_token('finance_warehouse_action');
    header("Location: warehouse.php"); exit;
}

$wh_items_list_q_main = $conn->query("SELECT wi.*, (wi.Quantity * wi.UnitPrice) as TotalValue FROM WarehouseItems wi ORDER BY wi.ItemName LIMIT 100");
?>
<div class="page-header"><h1>مدیریت انبار عمومی</h1>
    <div class="page-header-actions"><a href="donations.php" class="btn btn-success">مدیریت صله</a> <a href="booklets.php" class="btn btn-secondary">مدیریت جزوات</a></div></div>

<?php if (isset($_SESSION['flash_message'])) { $flash_wh_page = $_SESSION['flash_message']; echo "<div class='alert alert-{$flash_wh_page['type']} alert-dismissible fade show'>{$flash_wh_page['text']}<button type='button' class='close' data-dismiss='alert'>&times;</button></div>"; unset($_SESSION['flash_message']); echo "<script>/*Dismiss JS*/</script>";} ?>
<?php if (!empty($errors_wh)): ?> <div class="alert alert-danger"><ul><?php foreach ($errors_wh as $err_wh_item_page): ?><li><?php echo htmlspecialchars($err_wh_item_page); ?></li><?php endforeach; ?></ul></div> <?php endif; ?>

<div class="row"><div class="col-lg-5 mb-4"><div class="card shadow-sm">
    <div class="card-header"><span class="card-title-text"><?php echo $edit_mode_wh ? 'ویرایش قلم: ' . htmlspecialchars($item_wh_to_edit_values['ItemName']) : 'افزودن قلم جدید به انبار'; ?></span></div>
    <div class="card-body">
    <form action="warehouse.php<?php if($edit_mode_wh && $item_wh_to_edit_values['WarehouseItemID']) echo '?edit_item_id='.$item_wh_to_edit_values['WarehouseItemID']; ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_warehouse; ?>">
        <?php if ($edit_mode_wh && $item_wh_to_edit_values['WarehouseItemID']): ?><input type="hidden" name="item_id_wh" value="<?php echo $item_wh_to_edit_values['WarehouseItemID']; ?>"><?php endif; ?>
        <div class="form-group"><label for="item_name_wh_form_id">نام قلم <span class="text-danger">*</span></label><input type="text" class="form-control <?php echo (!empty($errors_wh) && isset($_POST['item_name_wh'])) ? 'is-invalid' : ''; ?>" id="item_name_wh_form_id" name="item_name_wh" value="<?php echo htmlspecialchars($item_wh_to_edit_values['ItemName']); ?>" required></div>
        <div class="form-row">
            <div class="form-group col-md-6"><label for="quantity_wh_form_id">تعداد موجود <span class="text-danger">*</span></label><input type="number" class="form-control <?php echo (!empty($errors_wh) && isset($_POST['quantity_wh'])) ? 'is-invalid' : ''; ?>" id="quantity_wh_form_id" name="quantity_wh" value="<?php echo htmlspecialchars($item_wh_to_edit_values['Quantity']); ?>" min="0" required></div>
            <div class="form-group col-md-6"><label for="unit_price_wh_form_id">قیمت واحد (تومان)</label><input type="text" class="form-control <?php echo (!empty($errors_wh) && isset($_POST['unit_price_wh'])) ? 'is-invalid' : ''; ?>" id="unit_price_wh_form_id" name="unit_price_wh" value="<?php echo htmlspecialchars($item_wh_to_edit_values['UnitPrice'] ?? ''); ?>" placeholder="اختیاری"></div>
        </div>
        <div class="form-group"><label for="description_wh_form_id">توضیحات</label><textarea class="form-control" id="description_wh_form_id" name="description_wh" rows="2"><?php echo htmlspecialchars($item_wh_to_edit_values['Description']); ?></textarea></div>
        <div class="form-actions"><button type="submit" name="submit_warehouse_item" class="btn btn-primary"><?php echo $edit_mode_wh ? 'ذخیره تغییرات' : 'افزودن به انبار'; ?></button><?php if ($edit_mode_wh): ?><a href="warehouse.php" class="btn btn-outline-secondary">لغو</a><?php endif; ?></div>
    </form></div></div></div>
    <div class="col-lg-7"><div class="card shadow-sm"><div class="card-header"><span class="card-title-text">لیست اقلام انبار (۱۰۰ اخیر)</span></div><div class="card-body">
    <?php if($wh_items_list_q_main && $wh_items_list_q_main->num_rows > 0): ?><div class="table-responsive"><table class="table table-sm table-striped table-hover">
        <thead><tr><th>#</th><th>نام قلم</th><th class="text-center">تعداد</th><th class="text-center">قیمت واحد</th><th class="text-center">ارزش کل</th><th>عملیات</th></tr></thead><tbody>
        <?php $wh_row_idx_disp = 1; while($wh_item_idx_disp = $wh_items_list_q_main->fetch_assoc()): ?><tr>
            <td><?php echo $wh_row_idx_disp++; ?></td><td><strong><?php echo htmlspecialchars($wh_item_idx_disp['ItemName']);?></strong><small class="d-block text-muted"><?php echo htmlspecialchars(mb_substr($wh_item_idx_disp['Description'] ?? '',0,50)).(mb_strlen($wh_item_idx_disp['Description'] ?? '') > 50 ? '...' : '');?></small></td>
            <td class="text-center font-weight-bold"><?php echo $wh_item_idx_disp['Quantity'];?></td>
            <td class="text-center"><?php echo $wh_item_idx_disp['UnitPrice'] ? number_format($wh_item_idx_disp['UnitPrice'],0) . ' ت' : '-';?></td>
            <td class="text-center"><?php echo ($wh_item_idx_disp['UnitPrice'] !== null && $wh_item_idx_disp['Quantity'] !== null) ? number_format(floatval($wh_item_idx_disp['Quantity']) * floatval($wh_item_idx_disp['UnitPrice']),0) . ' ت' : '-';?></td>
            <td class="actions-cell">
                <a href="warehouse.php?edit_item_id=<?php echo $wh_item_idx_disp['WarehouseItemID'];?>" class="btn btn-xs btn-warning" title="ویرایش"><svg class="icon" width="12" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a>
                <a href="warehouse.php?delete_item_id=<?php echo $wh_item_idx_disp['WarehouseItemID'];?>&csrf_token=<?php echo $csrf_token_warehouse; ?>" class="btn btn-xs btn-danger" title="حذف" onclick="return confirm('آیا از حذف این قلم از انبار مطمئن هستید؟');"><svg class="icon" width="12" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></a>
            </td></tr><?php endwhile; ?></tbody></table></div>
    <?php else: ?><p class="text-muted text-center mt-3">هنوز قلمی در انبار ثبت نشده است.</p><?php endif; if($wh_items_list_q_main) $wh_items_list_q_main->close();?>
    </div></div></div></div>
<script> /* Alert dismissal JS ... */
document.querySelectorAll('.alert .close').forEach(function(button){button.addEventListener('click', function(event){event.target.closest('.alert').style.display = 'none';});});
</script>
<style>.btn-xs{padding: .1rem .3rem; font-size: .75rem;} .font-weight-bold{font-weight:600!important;}</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
