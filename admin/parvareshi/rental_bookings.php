<?php
require_once __DIR__ . '/../includes/header.php';

$action_rb_page = $_GET['action'] ?? 'list';
$booking_id_rb_url_param = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

$bookings_list_display = [];
$booking_details_rb_display = null;
$form_errors_rb_page_display = [];
$page_title_rb_page_display = "مدیریت رزروهای کرایه‌چی";

$csrf_token_name_rb_status_update = 'parvareshi_booking_status_update_action';
// Note: The CSRF token for the main form of this page (if any for list filters) would be different.
// This token is for the status update form within the view page.

// Booking Statuses and Badge classes (can be moved to a helper if used elsewhere)
$booking_statuses_rb_map_display = [
    'pending_approval' => 'در انتظار تایید',
    'approved' => 'تایید شده (آماده تحویل)',
    'rejected' => 'رد شده',
    'picked_up' => 'تحویل داده شده',
    'returned' => 'بازگردانده شده',
    'cancelled_by_user' => 'لغو توسط کاربر',
    'cancelled_by_admin' => 'لغو توسط ادمین'
];
if (!function_exists('get_booking_status_badge_class_rb_page')) {
    function get_booking_status_badge_class_rb_page($status_key) {
        $s_rb_page = strtolower($status_key ?? '');
        if (in_array($s_rb_page, ['approved', 'picked_up'])) return 'success';
        if (in_array($s_rb_page, ['rejected', 'cancelled_by_user', 'cancelled_by_admin'])) return 'danger';
        if ($s_rb_page === 'pending_approval') return 'warning text-dark';
        if ($s_rb_page === 'returned') return 'info';
        return 'secondary';
    }
}

// Handle POST for status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_booking_status'])) {
    // Generate/regenerate token for this specific action instance if it's inside a loop or multiple forms on page
    $booking_id_to_update_status = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
    $csrf_token_rb_status_update_val = generate_csrf_token($csrf_token_name_rb_status_update . '_' . $booking_id_to_update_status);

    if (!verify_csrf_token($_POST['csrf_token'] ?? '', $csrf_token_name_rb_status_update . '_' . $booking_id_to_update_status)) {
        $form_errors_rb_page_display['csrf'] = "خطای CSRF.";
    } else {
        // Regenerate after successful verification if needed, or manage per instance
        // For simplicity, assume one update action per page load for now, or use unique token names.
        $new_status_rb_update = sanitize_input($_POST['new_status'] ?? '');
        $admin_notes_rb_update = sanitize_input($_POST['admin_notes'] ?? null);

        if ($booking_id_to_update_status > 0 && array_key_exists($new_status_rb_update, $booking_statuses_rb_map_display) && $conn) {
            $conn->begin_transaction();
            try {
                // Fetch old status and item details for quantity adjustment
                $stmt_old_data = $conn->prepare("SELECT ItemID, QuantityRequested, Status FROM ParvareshiRentalBookings WHERE BookingID = ?");
                if(!$stmt_old_data) throw new Exception("خطا در خواندن اطلاعات رزرو: " . $conn->error);
                $stmt_old_data->bind_param("i", $booking_id_to_update_status);
                $stmt_old_data->execute();
                $old_data_res = $stmt_old_data->get_result();
                if(!($old_booking_data = $old_data_res->fetch_assoc())) throw new Exception("رزرو برای بروزرسانی یافت نشد.");
                $stmt_old_data->close();

                $item_id_booked = $old_booking_data['ItemID'];
                $quantity_booked = $old_booking_data['QuantityRequested'];
                $old_status_booked = $old_booking_data['Status'];

                // QuantityAvailable Adjustment Logic
                if ($new_status_rb_update === 'picked_up' && $old_status_booked !== 'picked_up') {
                    // Item picked up, decrement available quantity
                    $stmt_qty_dec = $conn->prepare("UPDATE ParvareshiRentalItems SET QuantityAvailable = QuantityAvailable - ? WHERE ItemID = ? AND QuantityAvailable >= ?");
                    if(!$stmt_qty_dec) throw new Exception("خطا آماده سازی کسر موجودی: ".$conn->error);
                    $stmt_qty_dec->bind_param("iii", $quantity_booked, $item_id_booked, $quantity_booked);
                    if(!$stmt_qty_dec->execute()) throw new Exception("خطا در کسر موجودی: ".$stmt_qty_dec->error);
                    if($stmt_qty_dec->affected_rows === 0 && $new_status_rb_update === 'picked_up') {
                        // This means not enough quantity was available, this check should ideally happen before 'approved'
                        // For now, we'll throw an error if it happens at 'picked_up' stage.
                         throw new Exception("موجودی قلم کافی نیست. تعداد درخواستی: $quantity_booked");
                    }
                    $stmt_qty_dec->close();
                } elseif ($new_status_rb_update === 'returned' && $old_status_booked === 'picked_up') {
                    // Item returned, increment available quantity
                    $stmt_qty_inc = $conn->prepare("UPDATE ParvareshiRentalItems SET QuantityAvailable = QuantityAvailable + ? WHERE ItemID = ?");
                     if(!$stmt_qty_inc) throw new Exception("خطا آماده سازی افزایش موجودی: ".$conn->error);
                    $stmt_qty_inc->bind_param("ii", $quantity_booked, $item_id_booked);
                    if(!$stmt_qty_inc->execute()) throw new Exception("خطا در افزایش موجودی: ".$stmt_qty_inc->error);
                    $stmt_qty_inc->close();
                } elseif (in_array($new_status_rb_update, ['rejected', 'cancelled_by_user', 'cancelled_by_admin']) && $old_status_booked === 'picked_up'){
                    // If a picked-up item is then rejected/cancelled (unlikely flow, but handle), it means it was returned.
                     $stmt_qty_inc_cancel = $conn->prepare("UPDATE ParvareshiRentalItems SET QuantityAvailable = QuantityAvailable + ? WHERE ItemID = ?");
                     if(!$stmt_qty_inc_cancel) throw new Exception("خطا آماده سازی افزایش موجودی (لغو): ".$conn->error);
                    $stmt_qty_inc_cancel->bind_param("ii", $quantity_booked, $item_id_booked);
                    if(!$stmt_qty_inc_cancel->execute()) throw new Exception("خطا در افزایش موجودی (لغو): ".$stmt_qty_inc_cancel->error);
                    $stmt_qty_inc_cancel->close();
                }


                $stmt_update_rb_status_page = $conn->prepare("UPDATE ParvareshiRentalBookings SET Status = ?, AdminNotes = ?, ProcessedAt = NOW(), ApprovedByUserID = ? WHERE BookingID = ?");
                if(!$stmt_update_rb_status_page) throw new Exception("خطای آماده سازی بروزرسانی وضعیت: ".$conn->error);

                $current_admin_id_rb_status_page = get_current_user_id();
                $stmt_update_rb_status_page->bind_param("ssii", $new_status_rb_update, $admin_notes_rb_update, $current_admin_id_rb_status_page, $booking_id_to_update_status);
                if(!$stmt_update_rb_status_page->execute()) throw new Exception("خطا در بروزرسانی وضعیت رزرو: ".$stmt_update_rb_status_page->error);

                $stmt_update_rb_status_page->close();
                $conn->commit();
                $_SESSION['action_success_parvareshi'] = "وضعیت رزرو بروزرسانی شد.";
                // TODO: Notify user about status change
            } catch (Exception $e){
                $conn->rollback();
                $_SESSION['action_error_parvareshi'] = $e->getMessage();
            }
        } else {
            $_SESSION['action_error_parvareshi'] = "اطلاعات نامعتبر برای بروزرسانی وضعیت.";
        }
        header("Location: rental_bookings.php?action=view&booking_id=" . $booking_id_to_update_status);
        exit;
    }
}


if ($conn) {
    if ($action_rb_page === 'list') {
        $page_title_rb_page_display = "لیست رزروهای کرایه‌چی";
        $filter_rb_status_list = sanitize_input($_GET['filter_status'] ?? '');
        $filter_rb_item_list = isset($_GET['filter_item_id']) ? (int)$_GET['filter_item_id'] : null;
        $filter_rb_user_list = isset($_GET['filter_user_id']) ? (int)$_GET['filter_user_id'] : null;


        $sql_list_rb_page = "SELECT prb.BookingID, prb.RequestDate, prb.BookingDateStart, prb.BookingDateEnd, prb.Status, prb.QuantityRequested,
                               pri.ItemName, u.Username as RequesterUsername, c.ClassName
                        FROM ParvareshiRentalBookings prb
                        JOIN ParvareshiRentalItems pri ON prb.ItemID = pri.ItemID
                        JOIN Users u ON prb.UserID = u.UserID
                        LEFT JOIN Classes c ON prb.ClassID = c.ClassID
                        WHERE 1=1 ";
        $params_list_rb_page = []; $types_list_rb_page = "";
        if(!empty($filter_rb_status_list) && array_key_exists($filter_rb_status_list, $booking_statuses_rb_map_display)){ $sql_list_rb_page .= " AND prb.Status = ?"; $params_list_rb_page[] = $filter_rb_status_list; $types_list_rb_page .= "s";}
        if($filter_rb_item_list){ $sql_list_rb_page .= " AND prb.ItemID = ?"; $params_list_rb_page[] = $filter_rb_item_list; $types_list_rb_page .= "i";}
        if($filter_rb_user_list){ $sql_list_rb_page .= " AND prb.UserID = ?"; $params_list_rb_page[] = $filter_rb_user_list; $types_list_rb_page .= "i";}

        $sql_list_rb_page .= " ORDER BY prb.RequestDate DESC";

        $stmt_list_rb_page = $conn->prepare($sql_list_rb_page);
        if($stmt_list_rb_page){
            if(!empty($params_list_rb_page)) $stmt_list_rb_page->bind_param($types_list_rb_page, ...$params_list_rb_page);
            if($stmt_list_rb_page->execute()){ $result_list_rb_page = $stmt_list_rb_page->get_result(); while($row_rb=$result_list_rb_page->fetch_assoc()) $bookings_list_display[]=$row_rb; }
            else $form_errors_rb_page_display['db_list'] = "خطا بارگذاری لیست: " . $stmt_list_rb_page->error;
            $stmt_list_rb_page->close();
        } else $form_errors_rb_page_display['db_list'] = "خطای آماده سازی لیست: " . $conn->error;

    } elseif ($action_rb_page === 'view' && $booking_id_rb_url_param > 0) {
        $page_title_rb_page_display = "مشاهده جزئیات رزرو";
        $stmt_details_rb_page = $conn->prepare("SELECT prb.*, pri.ItemName, pri.ImagePath as ItemImagePath,
                                            u_req.FirstName as ReqFirstName, u_req.LastName as ReqLastName, u_req.Username as ReqUsername,
                                            u_app.Username as ApproverUsername, c.ClassName, c.AcademicYear
                                     FROM ParvareshiRentalBookings prb
                                     JOIN ParvareshiRentalItems pri ON prb.ItemID = pri.ItemID
                                     JOIN Users u_req ON prb.UserID = u_req.UserID
                                     LEFT JOIN Users u_app ON prb.ApprovedByUserID = u_app.UserID
                                     LEFT JOIN Classes c ON prb.ClassID = c.ClassID
                                     WHERE prb.BookingID = ?");
        if($stmt_details_rb_page){
            $stmt_details_rb_page->bind_param("i", $booking_id_rb_url_param); $stmt_details_rb_page->execute();
            $res_details_rb_page = $stmt_details_rb_page->get_result();
            if(!($booking_details_rb_display = $res_details_rb_page->fetch_assoc())){
                $_SESSION['action_error_parvareshi'] = "رزرو یافت نشد."; header("Location: rental_bookings.php"); exit;
            }
            $stmt_details_rb_page->close();
             // Generate CSRF for the status update form on this specific view page
            $csrf_token_rb_status_update_val = generate_csrf_token($csrf_token_name_rb_status_update . '_' . $booking_details_rb_display['BookingID']);
        } else $form_errors_rb_page_display['db_load_details'] = "خطا بارگذاری جزئیات: " . $conn->error;
    }
} else $form_errors_rb_page_display['db_conn_page'] = "خطا اتصال دیتابیس.";
?>
<div class="page-header">
    <h1><?php echo $page_title_rb_page_display; ?></h1>
    <div class="page-header-actions">
        <a href="index.php" class="btn btn-outline-secondary"><em class="bi bi-house-door icon"></em> داشبورد پرورشی</a>
        <?php if($action_rb_page !== 'list'):?><a href="rental_bookings.php" class="btn btn-secondary ms-2"><em class="bi bi-list-ul icon"></em> لیست رزروها</a><?php endif; ?>
    </div>
</div>

<?php if(isset($_SESSION['action_success_parvareshi'])):?><div class="alert alert-success alert-dismissible fade show"><button class="btn-close" data-bs-dismiss="alert"></button><?php echo $_SESSION['action_success_parvareshi']; unset($_SESSION['action_success_parvareshi']);?></div><?php endif;?>
<?php if(isset($_SESSION['action_error_parvareshi'])):?><div class="alert alert-danger alert-dismissible fade show"><button class="btn-close" data-bs-dismiss="alert"></button><?php echo $_SESSION['action_error_parvareshi']; unset($_SESSION['action_error_parvareshi']);?></div><?php endif;?>
<?php if(!empty($form_errors_rb_page_display)):?><div class="alert alert-danger alert-dismissible fade show"><strong>خطا:</strong><ul class="mb-0 ps-3"><?php foreach($form_errors_rb_page_display as $e_rb_p=>$e_msg_rb_p):echo "<li>".htmlspecialchars($e_msg_rb_p)."</li>";endforeach;?></ul><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif;?>

<?php if($action_rb_page === 'list'):
    $all_rental_items_filter_list = []; $all_users_filter_list = [];
    if($conn){
        $res_items_f_list = $conn->query("SELECT ItemID, ItemName FROM ParvareshiRentalItems ORDER BY ItemName"); if($res_items_f_list) while($r_i_f_l=$res_items_f_list->fetch_assoc()) $all_rental_items_filter_list[] = $r_i_f_l;
        $res_users_f_list = $conn->query("SELECT UserID, Username, FirstName, LastName FROM Users WHERE UserType != 'admin' ORDER BY LastName, FirstName"); if($res_users_f_list) while($u_f_l=$res_users_f_list->fetch_assoc()) $all_users_filter_list[] = $u_f_l;
    }
?>
    <div class="filter-search-bar mb-3"><form method="GET" class="row g-2 align-items-center">
        <div class="col-md-3"><select name="filter_item_id" class="form-select form-select-sm"><option value="">همه اقلام</option><?php foreach($all_rental_items_filter_list as $item_f_l):?><option value="<?php echo $item_f_l['ItemID'];?>" <?php echo (($filter_rb_item_list??0)==$item_f_l['ItemID'])?'selected':'';?>><?php echo htmlspecialchars($item_f_l['ItemName']);?></option><?php endforeach;?></select></div>
        <div class="col-md-3"><select name="filter_user_id" class="form-select form-select-sm"><option value="">همه کاربران</option><?php foreach($all_users_filter_list as $user_f_l):?><option value="<?php echo $user_f_l['UserID'];?>" <?php echo (($filter_rb_user_list??0)==$user_f_l['UserID'])?'selected':'';?>><?php echo htmlspecialchars(trim($user_f_l['FirstName'].' '.$user_f_l['LastName']).' (@'.$user_f_l['Username'].')');?></option><?php endforeach;?></select></div>
        <div class="col-md-3"><select name="filter_status" class="form-select form-select-sm"><option value="">همه وضعیت‌ها</option><?php foreach($booking_statuses_rb_map_display as $sk_rb_f_l=>$sv_rb_f_l):?><option value="<?php echo $sk_rb_f_l;?>" <?php echo (($filter_rb_status_list??'')===$sk_rb_f_l)?'selected':'';?>><?php echo $sv_rb_f_l;?></option><?php endforeach;?></select></div>
        <div class="col-md-auto"><button type="submit" class="btn btn-info btn-sm">فیلتر</button></div>
        <?php if(!empty($filter_rb_item_list)||!empty($filter_rb_status_list)||!empty($filter_rb_user_list)):?><div class="col-md-auto"><a href="rental_bookings.php" class="btn btn-secondary btn-sm">پاک کردن</a></div><?php endif;?>
    </form></div>
    <div class="card"><div class="card-body">
    <?php if(empty($bookings_list_display)): ?><p class="text-center text-muted py-3">هیچ رزروی یافت نشد.</p>
    <?php else: ?><div class="table-responsive"><table class="table table-hover table-sm">
        <thead class="table-light"><tr><th>#</th><th>قلم</th><th>درخواست‌دهنده</th><th>کلاس</th><th>تاریخ درخواست</th><th>تاریخ رزرو</th><th>تعداد</th><th>وضعیت</th><th class="actions-column">عملیات</th></tr></thead>
        <tbody><?php foreach($bookings_list_display as $idx_rb_ld => $b_ld): ?>
            <tr><td><?php echo $idx_rb_ld+1;?></td><td><?php echo htmlspecialchars($b_ld['ItemName']);?></td><td><?php echo htmlspecialchars($b_ld['RequesterUsername']);?></td><td><?php echo htmlspecialchars($b_ld['ClassName']?:'---');?></td><td><?php echo to_jalali($b_ld['RequestDate'],'yy/MM/dd');?></td><td><?php echo to_jalali($b_ld['BookingDateStart'],'yy/MM/dd');?></td><td><?php echo $b_ld['QuantityRequested'];?></td><td><span class="badge bg-<?php echo get_booking_status_badge_class_rb_page($b_ld['Status']);?>"><?php echo $booking_statuses_rb_map_display[$b_ld['Status']]??$b_ld['Status'];?></span></td>
            <td class="actions-cell"><a href="?action=view&booking_id=<?php echo $b_ld['BookingID'];?>" class="btn btn-sm btn-outline-primary" title="مشاهده و تغییر وضعیت"><em class="bi bi-eye-fill"></em></a></td></tr>
        <?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
    </div></div>
<?php elseif($action_rb_page === 'view' && $booking_details_rb_display): ?>
<div class="card">
    <div class="card-header"><h5 class="mb-0">جزئیات رزرو: <?php echo htmlspecialchars($booking_details_rb_display['ItemName']); ?> (کد: <?php echo $booking_details_rb_display['BookingID']; ?>)</h5></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3 text-center mb-3">
                <img src="<?php echo !empty($booking_details_rb_display['ItemImagePath']) ? get_base_url().htmlspecialchars($booking_details_rb_display['ItemImagePath']) : get_base_url().'assets/images/logo-placeholder.png';?>" alt="<?php echo htmlspecialchars($booking_details_rb_display['ItemName']);?>" class="img-fluid rounded mb-2" style="max-height: 150px; border: 1px solid #ddd;">
                <h6><?php echo htmlspecialchars($booking_details_rb_display['ItemName']); ?></h6>
            </div>
            <div class="col-md-9">
                <dl class="row gy-2">
                    <dt class="col-sm-4">درخواست دهنده:</dt><dd class="col-sm-8"><?php echo htmlspecialchars(trim($booking_details_rb_display['ReqFirstName'].' '.$booking_details_rb_display['ReqLastName'])); ?> <small class="text-muted">(@<?php echo htmlspecialchars($booking_details_rb_display['ReqUsername']); ?>)</small></dd>
                    <dt class="col-sm-4">کلاس (در صورت ارتباط):</dt><dd class="col-sm-8"><?php echo htmlspecialchars($booking_details_rb_display['ClassName']? ($booking_details_rb_display['ClassName'].' ('.$booking_details_rb_display['AcademicYear'].')') : '---'); ?></dd>
                    <dt class="col-sm-4">تاریخ ثبت درخواست:</dt><dd class="col-sm-8"><?php echo to_jalali($booking_details_rb_display['RequestDate'],'yyyy/MM/dd HH:mm'); ?></dd>
                    <dt class="col-sm-4">تاریخ شروع رزرو:</dt><dd class="col-sm-8"><?php echo to_jalali($booking_details_rb_display['BookingDateStart'],'yyyy/MM/dd'); ?></dd>
                    <dt class="col-sm-4">تاریخ پایان رزرو:</dt><dd class="col-sm-8"><?php echo to_jalali($booking_details_rb_display['BookingDateEnd'],'yyyy/MM/dd'); ?></dd>
                    <dt class="col-sm-4">تعداد درخواستی:</dt><dd class="col-sm-8"><?php echo $booking_details_rb_display['QuantityRequested']; ?></dd>
                    <dt class="col-sm-4">وضعیت فعلی:</dt><dd class="col-sm-8"><span class="badge fs-6 bg-<?php echo get_booking_status_badge_class_rb_page($booking_details_rb_display['Status']);?>"><?php echo $booking_statuses_rb_map_display[$booking_details_rb_display['Status']]??$booking_details_rb_display['Status'];?></span></dd>
                    <dt class="col-sm-4">یادداشت درخواست‌دهنده:</dt><dd class="col-sm-8"><?php echo nl2br(htmlspecialchars($booking_details_rb_display['RequesterNotes']?:'---')); ?></dd>
                    <?php if($booking_details_rb_display['AdminNotes']):?><dt class="col-sm-4 text-primary">یادداشت ادمین:</dt><dd class="col-sm-8 text-primary"><?php echo nl2br(htmlspecialchars($booking_details_rb_display['AdminNotes']));?></dd><?php endif;?>
                    <?php if($booking_details_rb_display['ApprovedByUserID']):?><dt class="col-sm-4">بررسی شده توسط:</dt><dd class="col-sm-8"><?php echo htmlspecialchars($booking_details_rb_display['ApproverUsername']?:'---');?> در <?php echo to_jalali($booking_details_rb_display['ProcessedAt'],'yy/MM/dd HH:mm');?></dd><?php endif;?>
                </dl>
            </div>
        </div>
        <hr>
        <h6>تغییر وضعیت رزرو:</h6>
        <form method="POST" action="rental_bookings.php?action=view&booking_id=<?php echo $booking_details_rb_display['BookingID']; ?>" class="row g-3 align-items-end">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_rb_status_update_val; // This should be specific to this form instance ?>">
            <input type="hidden" name="booking_id" value="<?php echo $booking_details_rb_display['BookingID']; ?>">
            <div class="col-md-4"><label for="new_status_rb_update_page" class="form-label">وضعیت جدید:</label><select name="new_status" id="new_status_rb_update_page" class="form-select"><?php foreach($booking_statuses_rb_map_display as $sk_upd_p=>$sv_upd_p):?><option value="<?php echo $sk_upd_p;?>" <?php echo ($booking_details_rb_display['Status']===$sk_upd_p)?'selected':'';?>><?php echo $sv_upd_p;?></option><?php endforeach;?></select></div>
            <div class="col-md-6"><label for="admin_notes_rb_update_page" class="form-label">یادداشت ادمین:</label><input type="text" name="admin_notes" id="admin_notes_rb_update_page" class="form-control" placeholder="اختیاری (مثلا دلیل رد یا شرایط تحویل)"></div>
            <div class="col-md-2"><button type="submit" name="update_booking_status" class="btn btn-warning w-100">بروزرسانی</button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
