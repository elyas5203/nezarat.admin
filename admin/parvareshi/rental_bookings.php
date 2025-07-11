<?php
// admin/parvareshi/rental_bookings.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth

$csrf_token_bookings = generate_csrf_token('parvareshi_rental_bookings_action');
$errors_bk = [];
$admin_user_id_booking = get_current_user_id();

$booking_status_options = [
    'requested' => 'درخواست شده', 'approved' => 'تایید شده', 'rented' => 'تحویل داده شده',
    'returned' => 'بازگردانده شده', 'cancelled' => 'لغو شده'
];
$booking_status_badge = [
    'requested' => 'warning', 'approved' => 'info', 'rented' => 'primary',
    'returned' => 'success', 'cancelled' => 'secondary'
];

// Fetch items for new booking dropdown (not implementing new booking form on this page for now)
// $items_q_bk = $conn->query("SELECT ItemID, ItemName, QuantityAvailable FROM RentalItems ORDER BY ItemName"); ...

// Handle Status Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_booking_status'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'parvareshi_rental_bookings_action')) {
        $errors_bk[] = 'خطای CSRF!';
    } else {
        $booking_id_to_update = isset($_POST['booking_id_status']) ? (int)$_POST['booking_id_status'] : null;
        $new_status_bk = sanitize_input($_POST['new_status'] ?? '');
        $return_date_bk_post = sanitize_input($_POST['return_date_status'] ?? '');

        if (empty($booking_id_to_update)) $errors_bk[] = "شناسه رزرو نامعتبر.";
        if (!array_key_exists($new_status_bk, $booking_status_options)) $errors_bk[] = "وضعیت جدید نامعتبر.";
        if ($new_status_bk === 'returned' && empty($return_date_bk_post)) {
            $errors_bk[] = "برای وضعیت 'بازگردانده شده'، تاریخ بازگشت الزامی است.";
        } elseif (!empty($return_date_bk_post) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $return_date_bk_post)) {
            $errors_bk[] = "فرمت تاریخ بازگشت نامعتبر (YYYY-MM-DD).";
        }


        if (empty($errors_bk)) {
            $conn->begin_transaction(); // Start transaction
            try {
                // Fetch current booking details to check old status and quantity
                $stmt_get_booking_details = $conn->prepare("SELECT ItemID, Quantity, Status FROM RentalBookings WHERE BookingID = ?");
                if(!$stmt_get_booking_details) throw new Exception("خطا در خواندن اطلاعات رزرو: ".$conn->error);
                $stmt_get_booking_details->bind_param("i", $booking_id_to_update);
                $stmt_get_booking_details->execute();
                $booking_details_res = $stmt_get_booking_details->get_result();
                if(!($booking_current_data = $booking_details_res->fetch_assoc())) throw new Exception("رزرو یافت نشد.");
                $stmt_get_booking_details->close();

                $old_status_bk = $booking_current_data['Status'];
                $item_id_bk = $booking_current_data['ItemID'];
                $quantity_booked_bk = $booking_current_data['Quantity'];

                $sql_update_bk = "UPDATE RentalBookings SET Status = ?, ApprovedByUserID = CASE WHEN ? IN ('approved', 'rented', 'returned') AND ApprovedByUserID IS NULL THEN ? ELSE ApprovedByUserID END, UpdatedAt = NOW()";
                $params_update_bk = [$new_status_bk, $new_status_bk, $admin_user_id_booking];
                $types_update_bk = "ssi";

                if ($new_status_bk === 'returned' && !empty($return_date_bk_post)) {
                    $sql_update_bk .= ", ReturnDate = ?"; $params_update_bk[] = $return_date_bk_post; $types_update_bk .= "s";
                } else if ($new_status_bk !== 'returned' && $old_status_bk === 'returned') { // If changing from returned to something else, nullify ReturnDate
                    $sql_update_bk .= ", ReturnDate = NULL";
                }
                $sql_update_bk .= " WHERE BookingID = ?"; $params_update_bk[] = $booking_id_to_update; $types_update_bk .= "i";

                $stmt_update_bk = $conn->prepare($sql_update_bk);
                if (!$stmt_update_bk) throw new Exception("خطا آماده سازی بروزرسانی: " . $conn->error);
                $stmt_update_bk->bind_param($types_update_bk, ...$params_update_bk);

                if ($stmt_update_bk->execute()) {
                    // Adjust QuantityAvailable in RentalItems
                    if ($old_status_bk !== $new_status_bk) { // Only adjust if status actually changed
                        if (in_array($new_status_bk, ['returned', 'cancelled']) && !in_array($old_status_bk, ['returned', 'cancelled'])) {
                            // Item is being returned or booking cancelled (was previously active) -> Increase available quantity
                            $stmt_adj_qty = $conn->prepare("UPDATE RentalItems SET QuantityAvailable = QuantityAvailable + ? WHERE ItemID = ?");
                            if(!$stmt_adj_qty) throw new Exception("خطا آماده سازی افزایش موجودی: ".$conn->error);
                            $stmt_adj_qty->bind_param("ii", $quantity_booked_bk, $item_id_bk);
                            if(!$stmt_adj_qty->execute()) throw new Exception("خطا در افزایش موجودی: ".$stmt_adj_qty->error);
                            $stmt_adj_qty->close();
                        } elseif (in_array($old_status_bk, ['returned', 'cancelled', 'requested']) && in_array($new_status_bk, ['approved', 'rented'])) {
                            // Item is being newly approved/rented (was previously available or just requested) -> Decrease available quantity
                            $stmt_adj_qty = $conn->prepare("UPDATE RentalItems SET QuantityAvailable = QuantityAvailable - ? WHERE ItemID = ?");
                             if(!$stmt_adj_qty) throw new Exception("خطا آماده سازی کاهش موجودی: ".$conn->error);
                            $stmt_adj_qty->bind_param("ii", $quantity_booked_bk, $item_id_bk);
                            if(!$stmt_adj_qty->execute()) throw new Exception("خطا در کاهش موجودی: ".$stmt_adj_qty->error);
                            $stmt_adj_qty->close();
                        }
                        // Other transitions (e.g. approved to rented) don't change overall availability count
                    }
                    $conn->commit();
                    $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'وضعیت رزرو بروزرسانی شد.'];
                } else { throw new Exception("خطا بروزرسانی وضعیت: " . $stmt_update_bk->error); }
                $stmt_update_bk->close();
            } catch (Exception $e) { $conn->rollback(); $errors_bk[] = $e->getMessage(); }
            if(empty($errors_bk)) { header("Location: rental_bookings.php"); exit; }
        }
    }
    $csrf_token_bookings = regenerate_csrf_token('parvareshi_rental_bookings_action');
}

// Fetch bookings (add filters and pagination later)
$bookings_list_q = $conn->query("
    SELECT rb.*, ri.ItemName, ri.QuantityAvailable AS ItemMaxQuantity,
           CONCAT(u.FirstName, ' ', u.LastName) as UserName, u.Username as UserUName,
           c.ClassName,
           CONCAT(u_appr.FirstName, ' ', u_appr.LastName) as ApproverName
    FROM RentalBookings rb
    JOIN RentalItems ri ON rb.ItemID = ri.ItemID
    JOIN Users u ON rb.UserID = u.UserID
    LEFT JOIN Classes c ON rb.ClassID = c.ClassID
    LEFT JOIN Users u_appr ON rb.ApprovedByUserID = u_appr.UserID
    ORDER BY FIELD(rb.Status, 'requested', 'approved', 'rented', 'returned', 'cancelled'), rb.RentalDate DESC, rb.BookingDate DESC
    LIMIT 100
");
?>
<div class="page-header"><h1>مدیریت رزروهای کرایه‌چی</h1>
    <div class="page-header-actions"><a href="rental_items.php" class="btn btn-secondary">مدیریت اقلام</a></div></div>

<?php if (isset($_SESSION['flash_message'])) { $flash_bk_idx = $_SESSION['flash_message']; echo "<div class='alert alert-{$flash_bk_idx['type']} alert-dismissible fade show'>{$flash_bk_idx['text']}<button type='button' class='close' data-dismiss='alert'>&times;</button></div>"; unset($_SESSION['flash_message']); echo "<script> /*Dismiss JS*/</script>"; } ?>
<?php if (!empty($errors_bk)): ?> <div class="alert alert-danger"><ul><?php foreach ($errors_bk as $err_bk_item_msg): ?><li><?php echo htmlspecialchars($err_bk_item_msg); ?></li><?php endforeach; ?></ul></div> <?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header"><span class="card-title-text">لیست رزروها (۱۰۰ مورد اخیر)</span></div>
    <div class="card-body">
    <?php if($bookings_list_q && $bookings_list_q->num_rows > 0): ?><div class="table-responsive"><table class="table table-sm table-striped table-hover rental-bookings-table">
        <thead><tr><th>#</th><th>قلم</th><th>کاربر/کلاس</th><th>مناسبت</th><th>تاریخ کرایه</th><th>تاریخ بازگشت</th><th>تعداد</th><th>وضعیت</th><th>تاییدکننده</th><th>عملیات</th></tr></thead><tbody>
        <?php $bk_row_idx = 1; while($bk_item = $bookings_list_q->fetch_assoc()): ?>
        <tr class="status-row-<?php echo htmlspecialchars($bk_item['Status']);?>"><td><?php echo $bk_row_idx++; ?></td>
            <td><strong><?php echo htmlspecialchars($bk_item['ItemName']);?></strong></td>
            <td><?php echo htmlspecialchars($bk_item['UserName']);?><small class="d-block text-muted"><?php if($bk_item['ClassName']) echo 'کلاس: '.htmlspecialchars($bk_item['ClassName']); else echo '@'.$bk_item['UserUName'];?></small></td>
            <td><?php echo htmlspecialchars($bk_item['EventName'] ?? '-');?></td>
            <td><?php echo to_jalali($bk_item['RentalDate'], 'yyyy/MM/dd');?></td>
            <td><?php echo $bk_item['ReturnDate'] ? to_jalali($bk_item['ReturnDate'], 'yyyy/MM/dd') : '-';?></td>
            <td class="text-center"><?php echo $bk_item['Quantity'];?></td>
            <td><span class="badge badge-<?php echo $booking_status_badge[$bk_item['Status']] ?? 'light';?> p-2"><?php echo $booking_status_options[$bk_item['Status']] ?? $bk_item['Status'];?></span></td>
            <td><small><?php echo htmlspecialchars($bk_item['ApproverName'] ?? '-');?></small></td>
            <td class="actions-cell">
                <button type="button" class="btn btn-xs btn-info btn-change-status" data-toggle="modal" data-target="#statusModal"
                        data-bookingid="<?php echo $bk_item['BookingID'];?>" data-currentstatus="<?php echo $bk_item['Status'];?>"
                        data-itemname="<?php echo htmlspecialchars($bk_item['ItemName']);?>" data-username="<?php echo htmlspecialchars($bk_item['UserName']);?>"
                        data-returndate="<?php echo htmlspecialchars($bk_item['ReturnDate'] ? (new DateTime($bk_item['ReturnDate']))->format('Y-m-d') : ''); ?>"
                        title="تغییر وضعیت رزرو">
                    <svg class="icon" width="12" viewBox="0 0 24 24"><path d="M20 14.66V20a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h5.34"/><polygon points="18 2 22 6 12 16 8 16 8 12 18 2"/></svg>
                </button>
            </td></tr>
        <?php endwhile; ?></tbody></table></div>
    <?php else: ?><p class="text-muted text-center">هنوز رزروی ثبت نشده.</p><?php endif; if($bookings_list_q) $bookings_list_q->close();?>
    </div></div>

<div class="modal fade" id="statusModal" tabindex="-1" role="dialog" aria-labelledby="statusModalLabel" aria-hidden="true"><div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
    <form action="rental_bookings.php" method="POST"> <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_bookings; ?>"> <input type="hidden" name="booking_id_status" id="modal_booking_id_status">
    <div class="modal-header"><h5 class="modal-title" id="statusModalLabel">تغییر وضعیت رزرو</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
    <div class="modal-body">
        <p>رزرو قلم <strong id="modal_item_name_status_disp"></strong> برای <strong id="modal_user_name_status_disp"></strong>.</p>
        <div class="form-group"><label for="modal_new_status_select">وضعیت جدید <span class="text-danger">*</span></label>
        <select name="new_status" id="modal_new_status_select" class="form-control custom-select" required onchange="document.getElementById('return_date_group_modal').style.display = (this.value === 'returned' ? 'block' : 'none');">
            <?php foreach($booking_status_options as $skey_m_modal => $sval_m_modal): ?><option value="<?php echo $skey_m_modal;?>"><?php echo $sval_m_modal;?></option><?php endforeach; ?>
        </select></div>
        <div class="form-group" id="return_date_group_modal" style="display:none;">
            <label for="modal_return_date_status_input">تاریخ بازگشت <span class="text-danger">*</span></label>
            <input type="text" name="return_date_status" id="modal_return_date_status_input" class="form-control persian-date-picker" placeholder="YYYY-MM-DD">
        </div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">لغو</button><button type="submit" name="update_booking_status" class="btn btn-primary">ذخیره وضعیت</button></div></form></div></div></div>

<link rel="stylesheet" href="https://unpkg.com/persian-datepicker@latest/dist/css/persian-datepicker.min.css"/>
<script src="https://unpkg.com/persian-datepicker@latest/dist/js/persian-datepicker.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof $ !== 'undefined' && $.fn.modal) {
        $('#statusModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget); var bookingId = button.data('bookingid'); var currentStatus = button.data('currentstatus');
            var itemName = button.data('itemname'); var userName = button.data('username'); var returnDate = button.data('returndate');
            var modal = $(this); modal.find('#modal_booking_id_status').val(bookingId); modal.find('#modal_new_status_select').val(currentStatus);
            modal.find('#modal_item_name_status_disp').text(itemName); modal.find('#modal_user_name_status_disp').text(userName);
            var returnDateGroup = document.getElementById('return_date_group_modal');
            var returnDateInput = modal.find('#modal_return_date_status_input');
            returnDateGroup.style.display = (currentStatus === 'returned' ? 'block' : 'none');
            returnDateInput.val(returnDate || ''); // Pre-fill if exists
            if(!returnDateInput.hasClass('pwt-uid')) { // Initialize datepicker if not already
                new persianDatepicker(returnDateInput[0], { format: 'YYYY-MM-DD', autoClose: true, observer:true, calendar:{persian:{locale:'fa'}}});
            }
        });
    }
    document.querySelectorAll('.alert .close').forEach(function(button){button.addEventListener('click', function(event){event.target.closest('.alert').style.display = 'none';});});
});
</script>
<style>.rental-bookings-table td, .rental-bookings-table th {font-size:0.85rem;} .badge.p-2{padding:0.4em 0.6em!important; font-size:0.8em!important;} .status-row-requested td { background-color: #fff8e1 !important; } .status-row-approved td { background-color: #e3f2fd !important; } .status-row-rented td { background-color: #e8eaf6 !important; }</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
