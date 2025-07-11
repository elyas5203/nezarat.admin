<?php
// admin/parvareshi/rental_items.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth

$csrf_token_rental_items = generate_csrf_token('parvareshi_rental_items_action');
$errors_ri = [];
$edit_mode_ri = false;
$item_to_edit_values = ['ItemID' => null, 'ItemName' => '', 'Description' => '', 'QuantityAvailable' => 0, 'CategoryID' => null];

// TODO: Fetch categories if you implement item categories:
// $item_categories_q = $conn->query("SELECT CategoryID, CategoryName FROM RentalItemCategories ORDER BY CategoryName");
// $available_categories_ri = [];
// if($item_categories_q) { while($cat = $item_categories_q->fetch_assoc()) $available_categories_ri[$cat['CategoryID']] = $cat['CategoryName']; $item_categories_q->close(); }


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_rental_item'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'parvareshi_rental_items_action')) {
        $errors_ri[] = 'خطای CSRF!';
    } else {
        $item_id_post = isset($_POST['item_id']) && is_numeric($_POST['item_id']) ? (int)$_POST['item_id'] : null;
        $item_name_post = sanitize_input($_POST['item_name'] ?? '');
        $description_ri_post = sanitize_input($_POST['description_ri'] ?? '');
        $quantity_post = isset($_POST['quantity_available']) && is_numeric($_POST['quantity_available']) ? (int)$_POST['quantity_available'] : 0;
        // $category_id_post = (isset($_POST['category_id_ri']) && !empty($_POST['category_id_ri'])) ? (int)$_POST['category_id_ri'] : null;

        $item_to_edit_values = ['ItemID' => $item_id_post, 'ItemName' => $item_name_post, 'Description' => $description_ri_post, 'QuantityAvailable' => $quantity_post, 'CategoryID' => null /* $category_id_post */];
        $edit_mode_ri = ($item_id_post !== null);

        if (empty($item_name_post)) $errors_ri[] = "نام قلم الزامی است.";
        if ($quantity_post < 0) $errors_ri[] = "تعداد موجودی نمی‌تواند منفی باشد.";

        $sql_check_iname = "SELECT ItemID FROM RentalItems WHERE ItemName = ?";
        $params_iname = [$item_name_post]; $types_iname = "s";
        if ($item_id_post) { $sql_check_iname .= " AND ItemID != ?"; $params_iname[] = $item_id_post; $types_iname .= "i"; }
        $stmt_check_iname = $conn->prepare($sql_check_iname);
        if ($stmt_check_iname) {
            $stmt_check_iname->bind_param($types_iname, ...$params_iname); $stmt_check_iname->execute();
            if ($stmt_check_iname->get_result()->num_rows > 0) $errors_ri[] = "قلم دیگری با این نام قبلاً ثبت شده است.";
            $stmt_check_iname->close();
        } else { $errors_ri[] = "خطا در بررسی نام قلم."; }


        if (empty($errors_ri)) {
            if ($item_id_post) {
                $stmt_ri_db = $conn->prepare("UPDATE RentalItems SET ItemName = ?, Description = ?, QuantityAvailable = ? WHERE ItemID = ?"); // Add CategoryID=?
                if($stmt_ri_db) { $stmt_ri_db->bind_param("ssii", $item_name_post, $description_ri_post, $quantity_post, $item_id_post); // Add type for CategoryID
                    if($stmt_ri_db->execute()) $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'قلم با موفقیت ویرایش شد.'];
                    else $errors_ri[] = "خطا ویرایش: " . $stmt_ri_db->error; $stmt_ri_db->close();
                } else $errors_ri[] = "خطا آماده سازی ویرایش: " . $conn->error;
            } else {
                $stmt_ri_db = $conn->prepare("INSERT INTO RentalItems (ItemName, Description, QuantityAvailable) VALUES (?, ?, ?)"); // Add CategoryID
                 if($stmt_ri_db) { $stmt_ri_db->bind_param("ssi", $item_name_post, $description_ri_post, $quantity_post); // Add type for CategoryID
                    if($stmt_ri_db->execute()) $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'قلم جدید افزوده شد.'];
                    else $errors_ri[] = "خطا ایجاد: " . $stmt_ri_db->error; $stmt_ri_db->close();
                } else $errors_ri[] = "خطا آماده سازی ایجاد: " . $conn->error;
            }
            if(empty($errors_ri)) { regenerate_csrf_token('parvareshi_rental_items_action'); header("Location: rental_items.php"); exit; }
        }
    }
    $csrf_token_rental_items = regenerate_csrf_token('parvareshi_rental_items_action');
}

if (isset($_GET['edit_item_id']) && is_numeric($_GET['edit_item_id']) && $_SERVER["REQUEST_METHOD"] != "POST") {
    $edit_id_ri_get_val = (int)$_GET['edit_item_id'];
    $stmt_edit_ri_get_val = $conn->prepare("SELECT ItemID, ItemName, Description, QuantityAvailable, CategoryID FROM RentalItems WHERE ItemID = ?");
    if ($stmt_edit_ri_get_val) { $stmt_edit_ri_get_val->bind_param("i", $edit_id_ri_get_val); $stmt_edit_ri_get_val->execute(); $result_edit_ri_get_val = $stmt_edit_ri_get_val->get_result();
        if ($data_ri_get_val = $result_edit_ri_get_val->fetch_assoc()) { $item_to_edit_values = $data_ri_get_val; $edit_mode_ri = true; }
        else $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "قلم یافت نشد."]; $stmt_edit_ri_get_val->close();
    } else $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "خطا بارگذاری: " . $conn->error];
}
if (isset($_GET['delete_item_id']) && is_numeric($_GET['delete_item_id'])) {
    if (!isset($_GET['csrf_token']) || !verify_csrf_token($_GET['csrf_token'], 'parvareshi_rental_items_action')) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطای CSRF!'];
    } else {
        $delete_id_ri_get = (int)$_GET['delete_item_id'];
        $stmt_check_bookings_del = $conn->prepare("SELECT COUNT(*) as count FROM RentalBookings WHERE ItemID = ? AND Status NOT IN ('returned', 'cancelled')");
        if($stmt_check_bookings_del){ $stmt_check_bookings_del->bind_param("i", $delete_id_ri_get); $stmt_check_bookings_del->execute(); $booking_count_del = $stmt_check_bookings_del->get_result()->fetch_assoc()['count'] ?? 0; $stmt_check_bookings_del->close();
            if($booking_count_del > 0) $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "حذف ناموفق: ".$booking_count_del." رزرو فعال برای این قلم وجود دارد."];
            else {
                // Also delete from RentalBookings if no active ones, or handle as per business logic (e.g. keep history)
                // For now, only delete item if no *active* bookings.
                $stmt_delete_ri_get = $conn->prepare("DELETE FROM RentalItems WHERE ItemID = ?");
                if ($stmt_delete_ri_get) { $stmt_delete_ri_get->bind_param("i", $delete_id_ri_get);
                    if ($stmt_delete_ri_get->execute() && $stmt_delete_ri_get->affected_rows > 0) $_SESSION['flash_message'] = ['type' => 'success', 'text' => "قلم حذف شد."];
                    else $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "خطا حذف: " . $stmt_delete_ri_get->error]; $stmt_delete_ri_get->close();
                } else $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "خطا آماده سازی حذف: " . $conn->error];
            }
        } else $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "خطا بررسی وابستگی قلم: " . $conn->error];
    }
    $csrf_token_rental_items = regenerate_csrf_token('parvareshi_rental_items_action');
    header("Location: rental_items.php"); exit;
}

$rental_items_list_q_main = $conn->query("SELECT ri.*, (SELECT COUNT(*) FROM RentalBookings rb WHERE rb.ItemID = ri.ItemID AND rb.Status NOT IN ('returned', 'cancelled')) as ActiveBookingsCount FROM RentalItems ri ORDER BY ri.ItemName LIMIT 100");
?>
<div class="page-header"><h1>مدیریت اقلام کرایه‌چی</h1>
    <div class="page-header-actions"><a href="class_services.php" class="btn btn-secondary">خدمت‌گزاری کلاس‌ها</a> <a href="rental_bookings.php" class="btn btn-info">مدیریت رزروها</a></div></div>

<?php if (isset($_SESSION['flash_message'])) { $flash_ri = $_SESSION['flash_message']; echo "<div class='alert alert-{$flash_ri['type']} alert-dismissible fade show'>{$flash_ri['text']}<button type='button' class='close' data-dismiss='alert'>&times;</button></div>"; unset($_SESSION['flash_message']); echo "<script> /* Dismiss JS */</script>";} ?>
<?php if (!empty($errors_ri)): ?> <div class="alert alert-danger"><ul><?php foreach ($errors_ri as $err_ri_item_msg): ?><li><?php echo htmlspecialchars($err_ri_item_msg); ?></li><?php endforeach; ?></ul></div> <?php endif; ?>

<div class="row"><div class="col-lg-5 mb-4"><div class="card shadow-sm">
    <div class="card-header"><span class="card-title-text"><?php echo $edit_mode_ri ? 'ویرایش قلم: ' . htmlspecialchars($item_to_edit_values['ItemName']) : 'افزودن قلم جدید'; ?></span></div>
    <div class="card-body">
    <form action="rental_items.php<?php if($edit_mode_ri && $item_to_edit_values['ItemID']) echo '?edit_item_id='.$item_to_edit_values['ItemID']; ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_rental_items; ?>">
        <?php if ($edit_mode_ri && $item_to_edit_values['ItemID']): ?><input type="hidden" name="item_id" value="<?php echo $item_to_edit_values['ItemID']; ?>"><?php endif; ?>
        <div class="form-group"><label for="item_name_ri">نام قلم <span class="text-danger">*</span></label><input type="text" class="form-control <?php echo (!empty($errors_ri) && isset($_POST['item_name'])) ? 'is-invalid' : ''; ?>" id="item_name_ri" name="item_name" value="<?php echo htmlspecialchars($item_to_edit_values['ItemName']); ?>" required></div>
        <div class="form-group"><label for="quantity_available_ri">تعداد موجود <span class="text-danger">*</span></label><input type="number" class="form-control <?php echo (!empty($errors_ri) && isset($_POST['quantity_available'])) ? 'is-invalid' : ''; ?>" id="quantity_available_ri" name="quantity_available" value="<?php echo htmlspecialchars($item_to_edit_values['QuantityAvailable']); ?>" min="0" required></div>
        <div class="form-group"><label for="description_ri_form">توضیحات</label><textarea class="form-control" id="description_ri_form" name="description_ri" rows="2"><?php echo htmlspecialchars($item_to_edit_values['Description']); ?></textarea></div>
        <!-- <div class="form-group"><label for="category_id_ri">دسته‌بندی (اختیاری)</label><select name="category_id_ri" id="category_id_ri" class="form-control custom-select"><option value="">-- انتخاب --</option><?php // foreach($available_categories_ri as $cat_id => $cat_name): ?><option value="<?php // echo $cat_id;?>" <?php // if($item_to_edit_values['CategoryID'] == $cat_id) echo 'selected';?>><?php // echo htmlspecialchars($cat_name);?></option><?php // endforeach;?></select></div> -->
        <div class="form-actions"><button type="submit" name="submit_rental_item" class="btn btn-primary"><?php echo $edit_mode_ri ? 'ذخیره تغییرات' : 'افزودن قلم'; ?></button><?php if ($edit_mode_ri): ?><a href="rental_items.php" class="btn btn-outline-secondary">لغو</a><?php endif; ?></div>
    </form></div></div></div>
    <div class="col-lg-7"><div class="card shadow-sm"><div class="card-header"><span class="card-title-text">لیست اقلام کرایه‌چی (۱۰۰ مورد اخیر)</span></div><div class="card-body">
    <?php if($rental_items_list_q_main && $rental_items_list_q_main->num_rows > 0): ?><div class="table-responsive"><table class="table table-sm table-striped table-hover">
        <thead><tr><th>#</th><th>نام قلم</th><th class="text-center">موجودی</th><th class="text-center">رزرو فعال</th><th>عملیات</th></tr></thead><tbody>
        <?php $ri_row_idx = 1; while($ri_item_idx = $rental_items_list_q_main->fetch_assoc()): ?><tr>
            <td><?php echo $ri_row_idx++; ?></td><td><strong><?php echo htmlspecialchars($ri_item_idx['ItemName']);?></strong><small class="d-block text-muted"><?php echo htmlspecialchars(mb_substr($ri_item_idx['Description'] ?? '',0,60)).(mb_strlen($ri_item_idx['Description'] ?? '')>60?'...':'');?></small></td>
            <td class="text-center font-weight-bold"><?php echo $ri_item_idx['QuantityAvailable'];?></td>
            <td class="text-center"><span class="badge badge-<?php echo $ri_item_idx['ActiveBookingsCount'] > 0 ? 'warning' : 'light';?> p-2"><?php echo $ri_item_idx['ActiveBookingsCount'];?></span></td>
            <td class="actions-cell">
                <a href="rental_items.php?edit_item_id=<?php echo $ri_item_idx['ItemID'];?>" class="btn btn-xs btn-warning" title="ویرایش"><svg class="icon" width="12" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a>
                <a href="rental_items.php?delete_item_id=<?php echo $ri_item_idx['ItemID'];?>&csrf_token=<?php echo $csrf_token_rental_items; ?>" class="btn btn-xs btn-danger <?php if($ri_item_idx['ActiveBookingsCount']>0) echo 'disabled';?>" title="<?php if($ri_item_idx['ActiveBookingsCount']>0) echo 'این قلم رزرو فعال دارد'; else echo 'حذف';?>" onclick="if(<?php echo $ri_item_idx['ActiveBookingsCount'];?>>0){ alert(this.title); return false; } return confirm('آیا از حذف این قلم مطمئن هستید؟');"><svg class="icon" width="12" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></a>
            </td></tr><?php endwhile;?></tbody></table></div>
    <?php else: ?><p class="text-muted text-center mt-3">هنوز قلمی در کرایه‌چی ثبت نشده است.</p><?php endif; if($rental_items_list_q_main) $rental_items_list_q_main->close();?>
    </div></div></div></div>
<script> /* Alert dismissal JS ... */ </script>
<style>.badge.p-2 {padding:0.4em 0.6em !important; font-size:0.85rem !important;} .btn-xs{padding: .1rem .3rem; font-size: .75rem; line-height: 1.2; border-radius: .2rem;}</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
