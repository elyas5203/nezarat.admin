<?php
// admin/parvareshi/rental_bookings.php
require_once __DIR__ . '/../includes/header.php';

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
            $conn->begin_transaction();
            try {
                $stmt_get_booking_details = $conn->prepare("SELECT ItemID, Quantity, Status FROM RentalBookings WHERE BookingID = ? FOR UPDATE"); // Lock row
                if(!$stmt_get_booking_details) throw new Exception("خطا خواندن اطلاعات رزرو: ".$conn->error);
                $stmt_get_booking_details->bind_param("i", $booking_id_to_update);
                $stmt_get_booking_details->execute();
                $booking_details_res = $stmt_get_booking_details->get_result();
                if(!($booking_current_data = $booking_details_res->fetch_assoc())) throw new Exception("رزرو یافت نشد.");
                $stmt_get_booking_details->close();

                $old_status_bk_logic = $booking_current_data['Status'];
                $item_id_bk_logic = $booking_current_data['ItemID'];
                $quantity_booked_bk_logic = $booking_current_data['Quantity'];

                $sql_update_bk = "UPDATE RentalBookings SET Status = ?, ApprovedByUserID = CASE WHEN ? IN ('approved', 'rented', 'returned') AND ApprovedByUserID IS NULL THEN ? ELSE ApprovedByUserID END, UpdatedAt = NOW()";
                $params_update_bk = [$new_status_bk, $new_status_bk, $admin_user_id_booking];
                $types_update_bk = "ssi";

                if ($new_status_bk === 'returned' && !empty($return_date_bk_post)) {
                    $sql_update_bk .= ", ReturnDate = ?"; $params_update_bk[] = $return_date_bk_post; $types_update_bk .= "s";
                } else if ($new_status_bk !== 'returned' && $old_status_bk_logic === 'returned') {
                    $sql_update_bk .= ", ReturnDate = NULL";
                }
                $sql_update_bk .= " WHERE BookingID = ?"; $params_update_bk[] = $booking_id_to_update; $types_update_bk .= "i";

                $stmt_update_bk_logic = $conn->prepare($sql_update_bk);
                if (!$stmt_update_bk_logic) throw new Exception("خطا آماده سازی بروزرسانی: " . $conn->error);
                $stmt_update_bk_logic->bind_param($types_update_bk, ...$params_update_bk);

                if ($stmt_update_bk_logic->execute()) {
                    $quantity_change_logic = 0;
                    if ($old_status_bk_logic !== $new_status_bk) {
                        if (in_array($new_status_bk, ['approved', 'rented']) && !in_array($old_status_bk_logic, ['approved', 'rented'])) {
                            $quantity_change_logic = - $quantity_booked_bk_logic;
                        } elseif (in_array($new_status_bk, ['returned', 'cancelled']) && in_array($old_status_bk_logic, ['approved', 'rented'])) {
                            $quantity_change_logic = + $quantity_booked_bk_logic;
                        }
                    }

                    if ($quantity_change_logic !== 0) {
                        $stmt_check_item_qty = $conn->prepare("SELECT QuantityAvailable FROM RentalItems WHERE ItemID = ? FOR UPDATE"); // Lock item row
                        if(!$stmt_check_item_qty) throw new Exception("خطا خواندن موجودی کالا: ".$conn->error);
                        $stmt_check_item_qty->bind_param("i", $item_id_bk_logic); $stmt_check_item_qty->execute();
                        $current_item_qty_res = $stmt_check_item_qty->get_result()->fetch_assoc();
                        $stmt_check_item_qty->close();
                        if(!$current_item_qty_res) throw new Exception("کالای مربوط به رزرو یافت نشد.");

                        if ($quantity_change_logic < 0 && $current_item_qty_res['QuantityAvailable'] < abs($quantity_change_logic)) {
                            throw new Exception("موجودی کالا (".htmlspecialchars($available_items_bk[$item_id_bk_logic]['ItemName'] ?? 'کالا').": ".$current_item_qty_res['QuantityAvailable'].") برای این تعداد (".abs($quantity_change_logic).") کافی نیست.");
                        }

                        $stmt_adj_qty_logic = $conn->prepare("UPDATE RentalItems SET QuantityAvailable = QuantityAvailable + ? WHERE ItemID = ?");
                        if(!$stmt_adj_qty_logic) throw new Exception("خطا آماده سازی تغییر موجودی: ".$conn->error);
                        $stmt_adj_qty_logic->bind_param("ii", $quantity_change_logic, $item_id_bk_logic);
                        if(!$stmt_adj_qty_logic->execute()) throw new Exception("خطا در بروزرسانی موجودی کالا: ".$stmt_adj_qty_logic->error);
                        $stmt_adj_qty_logic->close();
                    }

                    $conn->commit();
                    $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'وضعیت رزرو بروزرسانی شد.'];
                } else { throw new Exception("خطا بروزرسانی وضعیت: " . $stmt_update_bk_logic->error); }
                $stmt_update_bk_logic->close();
            } catch (Exception $e_bk_update_trans) { $conn->rollback(); $errors_bk[] = $e_bk_update_trans->getMessage(); }
            if(empty($errors_bk)) { header("Location: rental_bookings.php"); exit; }
        }
    }
    $csrf_token_bookings = regenerate_csrf_token('parvareshi_rental_bookings_action');
}

$bookings_list_q_main = $conn->query("
    SELECT rb.*, ri.ItemName, ri.QuantityAvailable AS ItemMaxQuantity,
           CONCAT(u.FirstName, ' ', u.LastName) as UserName, u.Username as UserUName,
           c.ClassName,
           CONCAT(u_appr.FirstName, ' ', u_appr.LastName) as ApproverName
    FROM RentalBookings rb
    JOIN RentalItems ri ON rb.ItemID = ri.ItemID
    JOIN Users u ON rb.UserID = u.UserID
    LEFT JOIN Classes c ON rb.ClassID = c.ClassID
    LEFT JOIN Users u_appr ON rb.ApprovedByUserID = u_appr.UserID
    ORDER BY FIELD(rb.Status, 'requested', 'approved', 'rented'), rb.RentalDate DESC, rb.BookingDate DESC
    LIMIT 100 ");
?>
<div class="page-header"><h1>مدیریت رزروهای کرایه‌چی</h1>
    <div class="page-header-actions"><a href="rental_items.php" class="btn btn-secondary">مدیریت اقلام</a></div></div>

<?php if (isset($_SESSION['flash_message'])) { $flash_bk_idx_page = $_SESSION['flash_message']; echo "<div class='alert alert-{$flash_bk_idx_page['type']} alert-dismissible fade show'>{$flash_bk_idx_page['text']}<button type='button' class='close' data-dismiss='alert'>&times;</button></div>"; unset($_SESSION['flash_message']); echo "<script> /*Dismiss JS*/</script>"; } ?>
<?php if (!empty($errors_bk)): ?> <div class="alert alert-danger"><ul><?php foreach ($errors_bk as $err_bk_item_page): ?><li><?php echo htmlspecialchars($err_bk_item_page); ?></li><?php endforeach; ?></ul></div> <?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header"><span class="card-title-text">لیست رزروها (۱۰۰ مورد اخیر)</span></div>
    <div class="card-body">
    <?php if($bookings_list_q_main && $bookings_list_q_main->num_rows > 0): ?><div class="table-responsive"><table class="table table-sm table-striped table-hover rental-bookings-table">
        <thead><tr><th>#</th><th>قلم</th><th>کاربر/کلاس</th><th>مناسبت</th><th>تاریخ کرایه</th><th>تاریخ بازگشت</th><th>تعداد</th><th>وضعیت</th><th>تاییدکننده</th><th>عملیات</th></tr></thead><tbody>
        <?php $bk_row_idx_page = 1; while($bk_item_page = $bookings_list_q_main->fetch_assoc()): ?>
        <tr class="status-row-<?php echo htmlspecialchars($bk_item_page['Status']);?>"><td><?php echo $bk_row_idx_page++; ?></td>
            <td><strong><?php echo htmlspecialchars($bk_item_page['ItemName']);?></strong></td>
            <td><?php echo htmlspecialchars($bk_item_page['UserName']);?><small class="d-block text-muted"><?php if($bk_item_page['ClassName']) echo 'کلاس: '.htmlspecialchars($bk_item_page['ClassName']); else echo '@'.$bk_item_page['UserUName'];?></small></td>
            <td><?php echo htmlspecialchars($bk_item_page['EventName'] ?? '-');?></td>
            <td><?php echo to_jalali($bk_item_page['RentalDate'], 'yyyy/MM/dd');?></td>
            <td><?php echo $bk_item_page['ReturnDate'] ? to_jalali($bk_item_page['ReturnDate'], 'yyyy/MM/dd') : '-';?></td>
            <td class="text-center"><?php echo $bk_item_page['Quantity'];?></td>
            <td><span class="badge badge-<?php echo $booking_status_badge[$bk_item_page['Status']] ?? 'light';?> p-2"><?php echo $booking_status_options[$bk_item_page['Status']] ?? $bk_item_page['Status'];?></span></td>
            <td><small><?php echo htmlspecialchars($bk_item_page['ApproverName'] ?? '-');?></small></td>
            <td class="actions-cell">
                <button type="button" class="btn btn-xs btn-info btn-change-status" data-toggle="modal" data-target="#statusModal"
                        data-bookingid="<?php echo $bk_item_page['BookingID'];?>" data-currentstatus="<?php echo $bk_item_page['Status'];?>"
                        data-itemname="<?php echo htmlspecialchars($bk_item_page['ItemName']);?>" data-username="<?php echo htmlspecialchars($bk_item_page['UserName']);?>"
                        data-returndate="<?php echo htmlspecialchars($bk_item_page['ReturnDate'] ? (new DateTime($bk_item_page['ReturnDate']))->format('Y-m-d') : ''); ?>"
                        title="تغییر وضعیت رزرو">
                    <svg class="icon" width="12" viewBox="0 0 24 24"><path d="M20 14.66V20a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h5.34"/><polygon points="18 2 22 6 12 16 8 16 8 12 18 2"/></svg>
                </button>
            </td></tr>
        <?php endwhile; ?></tbody></table></div>
    <?php else: ?><p class="text-muted text-center">هنوز رزروی ثبت نشده.</p><?php endif; if($bookings_list_q_main) $bookings_list_q_main->close();?>
    </div></div>

<div class="modal fade" id="statusModal" tabindex="-1" role="dialog" aria-labelledby="statusModalLabel" aria-hidden="true"><div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
    <form action="rental_bookings.php" method="POST"> <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_bookings; ?>"> <input type="hidden" name="booking_id_status" id="modal_booking_id_status_val">
    <div class="modal-header"><h5 class="modal-title" id="statusModalLabelNew">تغییر وضعیت رزرو</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
    <div class="modal-body">
        <p>رزرو قلم <strong id="modal_item_name_status_disp_val"></strong> برای <strong id="modal_user_name_status_disp_val"></strong>.</p>
        <div class="form-group"><label for="modal_new_status_select_val">وضعیت جدید <span class="text-danger">*</span></label>
        <select name="new_status" id="modal_new_status_select_val" class="form-control custom-select" required onchange="document.getElementById('return_date_group_modal_val').style.display = (this.value === 'returned' ? 'block' : 'none');">
            <?php foreach($booking_status_options as $skey_m_modal_val => $sval_m_modal_val): ?><option value="<?php echo $skey_m_modal_val;?>"><?php echo $sval_m_modal_val;?></option><?php endforeach; ?>
        </select></div>
        <div class="form-group" id="return_date_group_modal_val" style="display:none;">
            <label for="modal_return_date_status_input_val">تاریخ بازگشت <span class="text-danger">*</span></label>
            <input type="text" name="return_date_status" id="modal_return_date_status_input_val" class="form-control persian-date-picker" placeholder="YYYY-MM-DD">
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
            var modal = $(this); modal.find('#modal_booking_id_status_val').val(bookingId); modal.find('#modal_new_status_select_val').val(currentStatus);
            modal.find('#modal_item_name_status_disp_val').text(itemName); modal.find('#modal_user_name_status_disp_val').text(userName);
            var returnDateGroup = document.getElementById('return_date_group_modal_val');
            var returnDateInput = modal.find('#modal_return_date_status_input_val');
            returnDateGroup.style.display = (currentStatus === 'returned' ? 'block' : 'none');
            returnDateInput.val(returnDate || '');

            // Ensure datepicker is initialized or re-initialized for the modal input
            // This is a common issue with datepickers in modals.
            if(returnDateInput.length > 0 && !returnDateInput.hasClass('pwt-uid')) {
                 new persianDatepicker(returnDateInput[0], { format: 'YYYY-MM-DD', autoClose: true, observer:true, calendar:{persian:{locale:'fa'}}});
            } else if (returnDateInput.length > 0 && returnDateInput.hasClass('pwt-uid')){
                // If already initialized, might need to update its value or refresh it if library supports
            }
        });
    }
    document.querySelectorAll('.alert .close').forEach(function(button){button.addEventListener('click', function(event){event.target.closest('.alert').style.display = 'none';});});
});
</script>
<style>.rental-bookings-table td, .rental-bookings-table th {font-size:0.85rem;} .badge.p-2{padding:0.4em 0.6em!important; font-size:0.8em!important;} .status-row-requested td { background-color: #fff8e1 !important; } .status-row-approved td { background-color: #e3f2fd !important; } .status-row-rented td { background-color: #e8eaf6 !important; }</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
