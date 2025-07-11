<?php
// user/parvareshi/rental_request.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth, $user_base_url

$user_id_rental_req = get_current_user_id();
if (!$user_id_rental_req) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'برای درخواست، لطفا ابتدا وارد شوید.'];
    header("Location: " . ($user_base_url ?? '/my_site/user') . "/auth/login.php");
    exit;
}

$csrf_token_rental_req = generate_csrf_token('user_rental_request_action');
$errors_req = [];

$items_rental_q = $conn->query("SELECT ItemID, ItemName, Description, QuantityAvailable FROM RentalItems WHERE QuantityAvailable > 0 ORDER BY ItemName");
$available_items_for_rent = [];
if ($items_rental_q) { while($it_r = $items_rental_q->fetch_assoc()) $available_items_for_rent[$it_r['ItemID']] = $it_r; $items_rental_q->close(); }

$my_classes_rental_q = $conn->prepare("SELECT ClassID, ClassName FROM Classes WHERE TeacherUserID = ? AND IsActive = TRUE ORDER BY ClassName");
$my_available_classes_rental = [];
if($my_classes_rental_q && get_current_user_type() === 'teacher'){ // Only fetch classes if user is a teacher
    $my_classes_rental_q->bind_param("i", $user_id_rental_req); $my_classes_rental_q->execute(); $res_mycls_rent = $my_classes_rental_q->get_result();
    while($c_rent = $res_mycls_rent->fetch_assoc()) $my_available_classes_rental[$c_rent['ClassID']] = $c_rent['ClassName']; $my_classes_rental_q->close();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_rental_request'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'user_rental_request_action')) {
        $errors_req[] = 'خطای CSRF!';
    } else {
        $item_id_req = isset($_POST['item_id_rental']) ? (int)$_POST['item_id_rental'] : null;
        $class_id_req = isset($_POST['class_id_rental']) && !empty($_POST['class_id_rental']) ? (int)$_POST['class_id_rental'] : null;
        $event_name_req = sanitize_input($_POST['event_name_rental'] ?? '');
        $rental_date_req_str = sanitize_input($_POST['rental_date_rental'] ?? '');
        $quantity_req = isset($_POST['quantity_rental']) ? (int)$_POST['quantity_rental'] : 1;
        $notes_req = sanitize_input($_POST['notes_rental'] ?? '');

        if (empty($item_id_req) || !isset($available_items_for_rent[$item_id_req])) $errors_req[] = "قلم نامعتبر.";
        else if ($available_items_for_rent[$item_id_req]['QuantityAvailable'] < $quantity_req) $errors_req[] = "تعداد درخواستی برای \"".htmlspecialchars($available_items_for_rent[$item_id_req]['ItemName'])."\" بیش از موجودی (". $available_items_for_rent[$item_id_req]['QuantityAvailable'] .") است.";

        if ($class_id_req !== null && !isset($my_available_classes_rental[$class_id_req]) && get_current_user_type() === 'teacher') $errors_req[] = "کلاس نامعتبر.";
        elseif ($class_id_req !== null && get_current_user_type() !== 'teacher') $class_id_req = null; // Non-teachers cannot select class

        if (empty($rental_date_req_str) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $rental_date_req_str)) $errors_req[] = "تاریخ نامعتبر (YYYY-MM-DD).";
        else {
            $today_date_check_rent = new DateTime(); $today_date_check_rent->setTime(0,0,0);
            try { $requested_date_check_rent = new DateTime($rental_date_req_str); $requested_date_check_rent->setTime(0,0,0);
                if($requested_date_check_rent < $today_date_check_rent) $errors_req[] = "تاریخ نمی‌تواند گذشته باشد.";
            } catch (Exception $e){ $errors_req[] = "فرمت تاریخ نامعتبر.";}
        }
        if ($quantity_req <= 0) $errors_req[] = "تعداد باید مثبت باشد.";

        if (empty($errors_req)) {
            $conn->begin_transaction();
            try {
                $stmt_req_db = $conn->prepare("INSERT INTO RentalBookings (ItemID, UserID, ClassID, EventName, RentalDate, Quantity, Notes, Status, BookingDate, ApprovedByUserID, UpdatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, 'requested', NOW(), NULL, NOW())");
                if(!$stmt_req_db) throw new Exception("خطا آماده سازی: ".$conn->error);
                $stmt_req_db->bind_param("iiissis", $item_id_req, $user_id_rental_req, $class_id_req, $event_name_req, $rental_date_req_str, $quantity_req, $notes_req);

                if ($stmt_req_db->execute()) {
                    $new_booking_id_user = $stmt_req_db->insert_id;
                    $stmt_update_item_qty_user = $conn->prepare("UPDATE RentalItems SET QuantityAvailable = QuantityAvailable - ? WHERE ItemID = ? AND QuantityAvailable >= ?");
                    if(!$stmt_update_item_qty_user) throw new Exception("خطا آماده سازی موجودی: ".$conn->error);
                    $stmt_update_item_qty_user->bind_param("iii", $quantity_req, $item_id_req, $quantity_req);
                    if(!$stmt_update_item_qty_user->execute() || $stmt_update_item_qty_user->affected_rows == 0){ throw new Exception("خطا بروزرسانی موجودی یا موجودی کافی نیست."); }
                    $stmt_update_item_qty_user->close();
                    $conn->commit();
                    $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'درخواست شما ثبت شد. منتظر تایید بمانید.'];

                    $admin_notif_msg_rent = "درخواست کرایه جدید برای \"".htmlspecialchars($available_items_for_rent[$item_id_req]['ItemName'])."\" (".$quantity_req." عدد) توسط ".htmlspecialchars($_SESSION['username'])." ثبت شد.";
                    $admin_notif_link_rent = ($admin_base_url ?? '/my_site/admin')."/parvareshi/rental_bookings.php?highlight_booking=".$new_booking_id_user;
                    $stmt_all_admins_rent_notify = $conn->query("SELECT UserID FROM Users WHERE UserType = 'admin' OR UserType = 'manager'"); // Notify managers too
                    if ($stmt_all_admins_rent_notify) { while ($admin_row_rent_n = $stmt_all_admins_rent_notify->fetch_assoc()) { create_notification($admin_row_rent_n['UserID'], $admin_notif_msg_rent, $admin_notif_link_rent, 'rental_request', $new_booking_id_user); } $stmt_all_admins_rent_notify->close(); }

                } else { throw new Exception("خطا ثبت درخواست: " . $stmt_req_db->error); }
                $stmt_req_db->close();
            } catch (Exception $e_rent_user) { $conn->rollback(); $errors_req[] = $e_rent_user->getMessage(); }
            if(empty($errors_req)) { regenerate_csrf_token('user_rental_request_action'); header("Location: rental_request.php"); exit; }
        }
    }
    $csrf_token_rental_req = regenerate_csrf_token('user_rental_request_action');
}

$my_rental_requests_q_user = $conn->prepare("SELECT rb.*, ri.ItemName FROM RentalBookings rb JOIN RentalItems ri ON rb.ItemID = ri.ItemID WHERE rb.UserID = ? ORDER BY rb.BookingDate DESC LIMIT 20");
$my_rental_requests_user = [];
if($my_rental_requests_q_user){ $my_rental_requests_q_user->bind_param("i", $user_id_rental_req); $my_rental_requests_q_user->execute(); $res_myreq_user = $my_rental_requests_q_user->get_result();
    while($req_user = $res_myreq_user->fetch_assoc()) $my_rental_requests_user[] = $req_user; $my_rental_requests_q_user->close();
}
$booking_status_options_user_view = ['requested' => 'درخواست شده', 'approved' => 'تایید شده', 'rented' => 'تحویل داده شده', 'returned' => 'بازگردانده شده', 'cancelled' => 'لغو شده'];
$booking_status_badge_user_view = ['requested' => 'warning', 'approved' => 'info', 'rented' => 'primary', 'returned' => 'success', 'cancelled' => 'secondary'];
?>
<div class="page-header"><h1>درخواست اقلام از کرایه‌چی</h1><p class="page-subtitle">اقلام مورد نیاز خود را برای مراسم‌ها و برنامه‌ها از اینجا درخواست دهید.</p></div>

<?php if (isset($_SESSION['flash_message'])) { /* Flash */ $flash_rent_u = $_SESSION['flash_message']; echo "<div class='alert alert-{$flash_rent_u['type']} alert-dismissible fade show'>{$flash_rent_u['text']}<button type='button' class='close' data-dismiss='alert'>&times;</button></div>"; unset($_SESSION['flash_message']);} ?>
<?php if (!empty($errors_req)): ?> <div class="alert alert-danger"><ul><?php foreach ($errors_req as $err_req_item_u): ?><li><?php echo htmlspecialchars($err_req_item_u); ?></li><?php endforeach; ?></ul></div> <?php endif; ?>

<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm"><div class="card-header"><h5 class="mb-0 card-title-text">ثبت درخواست جدید</h5></div>
        <div class="card-body">
        <?php if(!empty($available_items_for_rent)): ?>
        <form action="rental_request.php" method="POST"> <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_rental_req; ?>">
            <div class="form-group"><label for="item_id_rental_user">قلم <span class="text-danger">*</span></label><select name="item_id_rental" id="item_id_rental_user" class="form-control custom-select" required onchange="updateMaxQuantity(this)"><option value="">-- انتخاب --</option><?php foreach($available_items_for_rent as $iid_r_u => $idata_r_u): ?><option value="<?php echo $iid_r_u; ?>" data-maxqty="<?php echo $idata_r_u['QuantityAvailable']; ?>"><?php echo htmlspecialchars($idata_r_u['ItemName'] . " (موجودی: " . $idata_r_u['QuantityAvailable'] . ")"); ?></option><?php endforeach; ?></select></div>
            <div class="form-row"><div class="form-group col-md-6"><label for="quantity_rental_user">تعداد <span class="text-danger">*</span></label><input type="number" name="quantity_rental" id="quantity_rental_user" class="form-control" min="1" value="1" required></div><div class="form-group col-md-6"><label for="rental_date_rental_user">تاریخ نیاز <span class="text-danger">*</span></label><input type="text" name="rental_date_rental" id="rental_date_rental_user" class="form-control persian-date-picker" placeholder="YYYY-MM-DD" required></div></div>
            <?php if(get_current_user_type() === 'teacher' && !empty($my_available_classes_rental)): ?>
            <div class="form-group"><label for="class_id_rental_user">برای کلاس (اختیاری)</label><select name="class_id_rental" id="class_id_rental_user" class="form-control custom-select"><option value="">-- عمومی / بدون کلاس --</option><?php foreach($my_available_classes_rental as $cid_rent_u => $cname_rent_u):?><option value="<?php echo $cid_rent_u;?>"><?php echo htmlspecialchars($cname_rent_u);?></option><?php endforeach;?></select></div>
            <?php endif; ?>
            <div class="form-group"><label for="event_name_rental_user">مناسبت/برنامه</label><input type="text" name="event_name_rental" id="event_name_rental_user" class="form-control" placeholder="مثال: جشن غدیر کلاس پنجم"></div>
            <div class="form-group"><label for="notes_rental_user">توضیحات</label><textarea name="notes_rental" id="notes_rental_user" class="form-control" rows="2"></textarea></div>
            <button type="submit" name="submit_rental_request" class="btn btn-primary-user">ثبت درخواست</button>
        </form>
        <?php else: ?><p class="text-muted">فعلا قلمی برای کرایه موجود نیست.</p><?php endif; ?>
        </div></div></div>
    <div class="col-lg-6"><div class="card shadow-sm"><div class="card-header"><h5 class="mb-0 card-title-text">تاریخچه درخواست‌های شما</h5></div>
    <div class="card-body">
    <?php if(!empty($my_rental_requests_user)): ?><div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>قلم</th><th>تعداد</th><th>تاریخ نیاز</th><th>وضعیت</th></tr></thead><tbody>
    <?php foreach($my_rental_requests_user as $my_req_u): ?><tr><td><?php echo htmlspecialchars($my_req_u['ItemName']);?></td><td class="text-center"><?php echo $my_req_u['Quantity'];?></td><td><?php echo to_jalali($my_req_u['RentalDate'],'yy/MM/dd');?></td><td><span class="badge badge-<?php echo $booking_status_badge_user_view[$my_req_u['Status']] ?? 'light';?> p-1"><?php echo $booking_status_options_user_view[$my_req_u['Status']] ?? $my_req_u['Status'];?></span></td></tr>
    <?php endforeach; ?></tbody></table></div><?php else: ?><p class="text-muted text-center">هنوز درخواستی ثبت نکرده‌اید.</p><?php endif; ?></div></div></div></div>
<link rel="stylesheet" href="https://unpkg.com/persian-datepicker@latest/dist/css/persian-datepicker.min.css"/>
<script src="https://unpkg.com/persian-datepicker@latest/dist/js/persian-datepicker.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll(".persian-date-picker").forEach(function(el){ new persianDatepicker(el, { format: 'YYYY-MM-DD', autoClose: true, observer: true, calendar:{ persian: { locale: 'fa' } }, minDate: new Date() });});
    document.querySelectorAll('.alert .close').forEach(function(button){button.addEventListener('click', function(event){event.target.closest('.alert').style.display = 'none';});});
});
function updateMaxQuantity(selectElement) {
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const maxQty = selectedOption.getAttribute('data-maxqty');
    const qtyInput = document.getElementById('quantity_rental_user');
    if (maxQty && qtyInput) { qtyInput.max = maxQty; if (parseInt(qtyInput.value) > parseInt(maxQty)) qtyInput.value = maxQty; } else if (qtyInput) { qtyInput.removeAttribute('max');}
}
</script>
<style>.badge.p-1{padding:0.25em 0.4em !important; font-size:0.8em !important;}</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
