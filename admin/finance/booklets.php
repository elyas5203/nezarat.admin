<?php
require_once __DIR__ . '/../includes/header.php';

$action_bk_page_main = $_GET['action'] ?? 'list_assignments';
$assignment_id_bk_url_main = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;
$booklet_id_bk_url_main = isset($_GET['booklet_id']) ? (int)$_GET['booklet_id'] : 0;

$page_title_bk_page_main = "مدیریت جزوات و حساب مدرسین";
$form_errors_bk_page_main = [];

$assignments_list_bk_display = []; $booklets_list_bk_display = [];
$assignment_data_form_bk_display = null; $booklet_data_form_bk_display = null;
$available_teachers_bk_list = []; $available_booklets_bk_list = [];

// CSRF Tokens
$csrf_token_booklet_form_page = generate_csrf_token('finance_booklet_form_action');
$csrf_token_assignment_form_page = generate_csrf_token('finance_booklet_assignment_form_action');
$csrf_token_delete_page_bk = generate_csrf_token('finance_delete_action_booklets');


if($conn){
    $res_teachers_bk_page = $conn->query("SELECT UserID, FirstName, LastName, Username FROM Users WHERE UserType = 'teacher' OR EXISTS (SELECT 1 FROM UserRoles ur JOIN Roles r ON ur.RoleID = r.RoleID WHERE ur.UserID = Users.UserID AND r.RoleName = 'مدرس') ORDER BY LastName, FirstName");
    if($res_teachers_bk_page) while($row_t = $res_teachers_bk_page->fetch_assoc()) $available_teachers_bk_list[] = $row_t;

    $res_booklets_bk_page = $conn->query("SELECT BookletID, Title, CostPrice, StockQuantity FROM Booklets ORDER BY Title");
    if($res_booklets_bk_page) while($row_b = $res_booklets_bk_page->fetch_assoc()) $available_booklets_bk_list[] = $row_b;
}

// --- POST Handler ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_booklet_definition'])) { // Save/Update Booklet Definition
        if(!verify_csrf_token($_POST['csrf_token'] ?? '', $csrf_token_booklet_form_page)) { $form_errors_bk_page_main['csrf_bk'] = "خطای CSRF."; }
        else {
            $csrf_token_booklet_form_page = regenerate_csrf_token($csrf_token_booklet_form_page);
            $booklet_id_posted = isset($_POST['booklet_id']) ? (int)$_POST['booklet_id'] : 0;
            $title_bk = sanitize_input($_POST['title'] ?? '');
            $description_bk = sanitize_input($_POST['description'] ?? null);
            $cost_price_bk = !empty($_POST['cost_price']) ? filter_var(str_replace(',','',$_POST['cost_price']), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : 0;
            $stock_qty_bk = isset($_POST['stock_quantity']) ? (int)$_POST['stock_quantity'] : 0;

            $booklet_data_form_bk_display = $_POST;

            if(empty($title_bk)) $form_errors_bk_page_main['title'] = "عنوان جزوه الزامی است.";
            if($cost_price_bk < 0) $form_errors_bk_page_main['cost_price'] = "هزینه نمی‌تواند منفی باشد.";
            if($stock_qty_bk < 0) $form_errors_bk_page_main['stock_quantity'] = "موجودی نمی‌تواند منفی باشد.";

            if(empty($form_errors_bk_page_main) && $conn){
                if($booklet_id_posted > 0){ // Update Booklet
                    $stmt_bk_save = $conn->prepare("UPDATE Booklets SET Title=?, Description=?, CostPrice=?, StockQuantity=? WHERE BookletID=?");
                    if($stmt_bk_save){ $stmt_bk_save->bind_param("ssdii", $title_bk, $description_bk, $cost_price_bk, $stock_qty_bk, $booklet_id_posted);
                        if($stmt_bk_save->execute()){ $_SESSION['action_success_finance'] = "جزوه بروزرسانی شد."; header("Location: booklets.php?action=list_booklets"); exit;} else $form_errors_bk_page_main['db_bk'] = "خطا بروزرسانی جزوه: ".$stmt_bk_save->error; $stmt_bk_save->close();}
                } else { // Create Booklet
                    $stmt_bk_save = $conn->prepare("INSERT INTO Booklets (Title, Description, CostPrice, StockQuantity, CreatedAt) VALUES (?,?,?,?,NOW())");
                    if($stmt_bk_save){ $stmt_bk_save->bind_param("ssdi", $title_bk, $description_bk, $cost_price_bk, $stock_qty_bk);
                        if($stmt_bk_save->execute()){ $_SESSION['action_success_finance'] = "جزوه جدید تعریف شد."; header("Location: booklets.php?action=list_booklets"); exit;} else $form_errors_bk_page_main['db_bk'] = "خطا ایجاد جزوه: ".$stmt_bk_save->error; $stmt_bk_save->close();}
                }
            }
            $action_bk_page_main = $booklet_id_posted > 0 ? 'edit_booklet' : 'create_booklet';
        }
    }
    elseif (isset($_POST['save_booklet_assignment'])) {
        if(!verify_csrf_token($_POST['csrf_token'] ?? '', $csrf_token_assignment_form_page)) { $form_errors_bk_page_main['csrf_assign'] = "خطای CSRF."; }
        else {
            $csrf_token_assignment_form_page = regenerate_csrf_token($csrf_token_assignment_form_page);
            $assignment_id_posted_ba = isset($_POST['assignment_id']) ? (int)$_POST['assignment_id'] : 0;
            $booklet_id_form_ba = isset($_POST['booklet_id']) ? (int)$_POST['booklet_id'] : null;
            $user_id_form_teacher_ba = isset($_POST['user_id']) ? (int)$_POST['user_id'] : null;
            $qty_assigned_form_ba = isset($_POST['quantity_assigned']) ? (int)$_POST['quantity_assigned'] : 0;
            $assignment_date_jalali_ba = sanitize_input($_POST['assignment_date'] ?? '');
            $month_id_form_ba = sanitize_input($_POST['month_identifier'] ?? '');
            $amount_paid_form_ba = !empty($_POST['amount_paid']) ? filter_var(str_replace(',','',$_POST['amount_paid']), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : 0;
            $notes_form_bk_ba = sanitize_input($_POST['notes'] ?? null);

            $assignment_data_form_bk_display = $_POST;
            $assignment_data_form_bk_display['AssignmentDate'] = $assignment_date_jalali_ba;

            if(!$booklet_id_form_ba) $form_errors_bk_page_main['booklet_id'] = "انتخاب جزوه الزامی است.";
            if(!$user_id_form_teacher_ba) $form_errors_bk_page_main['user_id'] = "انتخاب مدرس الزامی است.";
            if($qty_assigned_form_ba <= 0) $form_errors_bk_page_main['quantity_assigned'] = "تعداد باید بیشتر از صفر باشد.";
            if(empty($assignment_date_jalali_ba)) $form_errors_bk_page_main['assignment_date'] = "تاریخ تحویل الزامی است.";
            $assignment_date_greg_ba = null; if(!empty($assignment_date_jalali_ba)){ $assignment_date_greg_ba = to_gregorian_date_for_db($assignment_date_jalali_ba); if(!$assignment_date_greg_ba) $form_errors_bk_page_main['assignment_date'] = "فرمت تاریخ نامعتبر.";}
            if(empty($month_id_form_ba) || !preg_match('/^\d{4}-\d{2}$/', $month_id_form_ba)) $form_errors_bk_page_main['month_identifier'] = "فرمت ماه شناسایی (مثال: 1403-05) نامعتبر.";

            if(empty($form_errors_bk_page_main) && $conn){
                $booklet_cost_ba = 0;
                $stmt_cost_ba = $conn->prepare("SELECT CostPrice FROM Booklets WHERE BookletID = ?");
                if($stmt_cost_ba){ $stmt_cost_ba->bind_param("i", $booklet_id_form_ba); $stmt_cost_ba->execute(); $res_cost_ba = $stmt_cost_ba->get_result(); if($d_cost_ba = $res_cost_ba->fetch_assoc()) $booklet_cost_ba = $d_cost_ba['CostPrice']; $stmt_cost_ba->close();}
                else { $form_errors_bk_page_main['db'] = "خطا در دریافت قیمت جزوه."; }

                if(empty($form_errors_bk_page_main['db'])){
                    $total_charge_calc_ba = $booklet_cost_ba * $qty_assigned_form_ba;
                    $outstanding_balance_calc_ba = $total_charge_calc_ba - $amount_paid_form_ba;
                    $current_user_id_bk_save_ba = get_current_user_id();

                    if($assignment_id_posted_ba > 0){
                        $stmt_ba_save_page = $conn->prepare("UPDATE BookletAssignments SET BookletID=?, UserID=?, QuantityAssigned=?, AssignmentDate=?, MonthIdentifier=?, TotalCharge=?, AmountPaid=?, OutstandingBalance=?, Notes=?, RecordedByUserID=?, RecordedAt=NOW() WHERE AssignmentID=?");
                        if($stmt_ba_save_page){ $stmt_ba_save_page->bind_param("iiisssdssii", $booklet_id_form_ba, $user_id_form_teacher_ba, $qty_assigned_form_ba, $assignment_date_greg_ba, $month_id_form_ba, $total_charge_calc_ba, $amount_paid_form_ba, $outstanding_balance_calc_ba, $notes_form_bk_ba, $current_user_id_bk_save_ba, $assignment_id_posted_ba);
                            if($stmt_ba_save_page->execute()){ $_SESSION['action_success_finance'] = "تخصیص جزوه بروزرسانی شد."; header("Location: booklets.php?action=list_assignments"); exit;} else $form_errors_bk_page_main['db'] = "خطا بروزرسانی: ".$stmt_ba_save_page->error; $stmt_ba_save_page->close(); }
                    } else {
                         $stmt_ba_save_page = $conn->prepare("INSERT INTO BookletAssignments (BookletID, UserID, QuantityAssigned, AssignmentDate, MonthIdentifier, TotalCharge, AmountPaid, OutstandingBalance, Notes, RecordedByUserID, RecordedAt) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())");
                         if($stmt_ba_save_page){ $stmt_ba_save_page->bind_param("iiisssdssi", $booklet_id_form_ba, $user_id_form_teacher_ba, $qty_assigned_form_ba, $assignment_date_greg_ba, $month_id_form_ba, $total_charge_calc_ba, $amount_paid_form_ba, $outstanding_balance_calc_ba, $notes_form_bk_ba, $current_user_id_bk_save_ba);
                            if($stmt_ba_save_page->execute()){ $_SESSION['action_success_finance'] = "جزوه تخصیص داده شد."; header("Location: booklets.php?action=list_assignments"); exit;} else $form_errors_bk_page_main['db'] = "خطا ایجاد: ".$stmt_ba_save_page->error; $stmt_ba_save_page->close(); }
                    }
                }
            }
            $action_bk_page_main = $assignment_id_posted_ba > 0 ? 'edit_assignment' : 'assign_booklet';
        }
    }
    // TODO: Add POST handlers for deleting booklets and assignments
}


// --- Fetch Data for Display ---
if($conn){
    if($action_bk_page_main === 'list_assignments'){
        $page_title_bk_page_main = "لیست جزوات تخصیص داده شده به مدرسین";
        $sql_ba_list_page = "SELECT ba.*, b.Title as BookletTitle, u.FirstName, u.LastName, u.Username
                        FROM BookletAssignments ba
                        JOIN Booklets b ON ba.BookletID = b.BookletID
                        JOIN Users u ON ba.UserID = u.UserID
                        ORDER BY ba.AssignmentDate DESC, u.LastName ASC";
        $res_ba_list_page = $conn->query($sql_ba_list_page);
        if($res_ba_list_page) while($row_ba_l=$res_ba_list_page->fetch_assoc()) $assignments_list_bk_display[] = $row_ba_l;
        else $form_errors_bk_page_main['db_list_assign'] = "خطا بارگذاری تخصیص‌ها: ".$conn->error;
    }
    elseif(($action_bk_page_main === 'assign_booklet' || ($action_bk_page_main === 'edit_assignment' && $assignment_id_bk_url_main > 0)) && !$assignment_data_form_bk_display){
        $page_title_bk_page_main = $action_bk_page_main === 'assign_booklet' ? "تخصیص جزوه جدید به مدرس" : "ویرایش تخصیص جزوه";
        if($action_bk_page_main === 'edit_assignment'){
            $stmt_ba_edit_page = $conn->prepare("SELECT * FROM BookletAssignments WHERE AssignmentID = ?");
            if($stmt_ba_edit_page){ $stmt_ba_edit_page->bind_param("i", $assignment_id_bk_url_main); $stmt_ba_edit_page->execute(); $res_ba_edit_page=$stmt_ba_edit_page->get_result();
                if(!($assignment_data_form_bk_display = $res_ba_edit_page->fetch_assoc())){ $_SESSION['action_error_finance']="تخصیص یافت نشد."; header("Location: booklets.php?action=list_assignments"); exit;}
                if(!empty($assignment_data_form_bk_display['AssignmentDate'])) $assignment_data_form_bk_display['AssignmentDate'] = to_jalali($assignment_data_form_bk_display['AssignmentDate'], 'yyyy/MM/dd');
                $stmt_ba_edit_page->close();
            } else $form_errors_bk_page_main['db_load_assign'] = "خطا بارگذاری تخصیص: ".$conn->error;
        } else {
            $assignment_data_form_bk_display = ['BookletID'=>null, 'UserID'=>null, 'QuantityAssigned'=>1, 'AssignmentDate'=>to_jalali(date('Y-m-d'),'yyyy/MM/dd'), 'MonthIdentifier'=>date('Y-m'), 'AmountPaid'=>'0', 'Notes'=>''];
        }
    }
     elseif ($action_bk_page_main === 'list_booklets') {
        $page_title_bk_page_main = "لیست جزوات تعریف شده";
        $booklets_list_bk_display = $available_booklets_bk_list; // Use already fetched list
    }
    elseif(($action_bk_page_main === 'create_booklet' || ($action_bk_page_main === 'edit_booklet' && $booklet_id_bk_url_main > 0)) && !$booklet_data_form_bk_display){
        $page_title_bk_page_main = $action_bk_page_main === 'create_booklet' ? "تعریف جزوه جدید" : "ویرایش جزوه";
        if($action_bk_page_main === 'edit_booklet'){
            $stmt_bk_edit_page = $conn->prepare("SELECT * FROM Booklets WHERE BookletID = ?");
            if($stmt_bk_edit_page){ $stmt_bk_edit_page->bind_param("i", $booklet_id_bk_url_main); $stmt_bk_edit_page->execute(); $res_bk_edit_page=$stmt_bk_edit_page->get_result();
                if(!($booklet_data_form_bk_display = $res_bk_edit_page->fetch_assoc())){ $_SESSION['action_error_finance']="جزوه یافت نشد."; header("Location: booklets.php?action=list_booklets"); exit;}
                $stmt_bk_edit_page->close();
            } else $form_errors_bk_page_main['db_load_bk'] = "خطا بارگذاری جزوه: ".$conn->error;
        } else { // create_booklet
            $booklet_data_form_bk_display = ['Title'=>'','Description'=>'','CostPrice'=>'','StockQuantity'=>0];
        }
    }
} else { $form_errors_bk_page_main['db_conn_main_bk_page'] = "خطا اتصال دیتابیس."; }
?>
<div class="page-header"><h1><?php echo $page_title_bk_page_main; ?></h1>
    <div class="page-header-actions btn-group">
        <a href="booklets.php?action=list_assignments" class="btn <?php echo (str_contains($action_bk_page_main,'assign'))?'btn-primary':'btn-outline-primary';?> btn-sm"><em class="bi bi-person-check-fill icon"></em> تخصیص جزوات</a>
        <a href="booklets.php?action=list_booklets" class="btn <?php echo (str_contains($action_bk_page_main,'booklet') && !str_contains($action_bk_page_main,'assign'))?'btn-primary':'btn-outline-primary';?> btn-sm"><em class="bi bi-journals icon"></em> مدیریت جزوات</a>
    </div>
    <a href="index.php" class="btn btn-outline-dark ms-auto btn-sm"><em class="bi bi-coin icon"></em> داشبورد مالی</a>
</div>

<?php if(isset($_SESSION['action_success_finance'])):?><div class="alert alert-success alert-dismissible fade show"><button class="btn-close" data-bs-dismiss="alert"></button><?php echo $_SESSION['action_success_finance']; unset($_SESSION['action_success_finance']);?></div><?php endif;?>
<?php if(isset($_SESSION['action_error_finance'])):?><div class="alert alert-danger alert-dismissible fade show"><button class="btn-close" data-bs-dismiss="alert"></button><?php echo $_SESSION['action_error_finance']; unset($_SESSION['action_error_finance']);?></div><?php endif;?>
<?php if(!empty($form_errors_bk_page_main)):?><div class="alert alert-danger alert-dismissible fade show"><strong>خطا:</strong><ul class="mb-0 ps-3"><?php foreach($form_errors_bk_page_main as $e_bk_key=>$e_bk_msg):echo "<li>".htmlspecialchars($e_bk_msg)."</li>";endforeach;?></ul><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif;?>

<?php if($action_bk_page_main === 'list_assignments'): ?>
    <div class="mb-3"><a href="?action=assign_booklet" class="btn btn-success"><em class="bi bi-journal-plus me-2"></em>تخصیص جزوه جدید به مدرس</a></div>
    <div class="card"><div class="card-body">
    <?php if(empty($assignments_list_bk_display)): ?><p class="text-center text-muted py-3">هیچ جزوه‌ای به مدرسین تخصیص داده نشده.</p>
    <?php else: ?><div class="table-responsive"><table class="table table-hover table-sm">
        <thead class="table-light"><tr><th>#</th><th>جزوه</th><th>مدرس</th><th>تعداد</th><th>تاریخ تحویل</th><th>ماه مربوطه</th><th>مبلغ کل(تومان)</th><th>پرداختی(تومان)</th><th>باقیمانده(تومان)</th><th class="actions-column">عملیات</th></tr></thead>
        <tbody><?php foreach($assignments_list_bk_display as $idx_ba_l => $ba_l): ?>
            <tr><td><?php echo $idx_ba_l+1;?></td><td><?php echo htmlspecialchars($ba_l['BookletTitle']);?></td><td><?php echo htmlspecialchars(trim($ba_l['FirstName'].' '.$ba_l['LastName']));?></td><td><?php echo $ba_l['QuantityAssigned'];?></td><td><?php echo to_jalali($ba_l['AssignmentDate'],'yy/MM/dd');?></td><td><?php echo htmlspecialchars($ba_l['MonthIdentifier']);?></td><td class="text-end"><?php echo number_format($ba_l['TotalCharge']);?></td><td class="text-end"><?php echo number_format($ba_l['AmountPaid']);?></td><td class="text-end <?php echo $ba_l['OutstandingBalance']>0 ? 'text-danger fw-bold':'text-success';?>"><?php echo number_format($ba_l['OutstandingBalance']);?></td>
            <td class="actions-cell"><a href="?action=edit_assignment&assignment_id=<?php echo $ba_l['AssignmentID'];?>" class="btn btn-sm btn-outline-info" title="ویرایش"><em class="bi bi-pencil-square"></em></a></td></tr>
        <?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
    </div></div>
<?php elseif($action_bk_page_main === 'assign_booklet' || $action_bk_page_main === 'edit_assignment'): ?>
    <div class="card"><div class="card-header"><h5 class="mb-0"><?php echo $action_bk_page_main === 'assign_booklet' ? "تخصیص جزوه به مدرس" : "ویرایش تخصیص جزوه"; ?></h5></div><div class="card-body">
        <form method="POST" action="booklets.php<?php echo ($action_bk_page_main==='edit_assignment'&&$assignment_id_bk_url_main)?'?action=edit_assignment&assignment_id='.$assignment_id_bk_url_main:'?action=assign_booklet';?>" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_assignment_form_page; ?>">
            <?php if($action_bk_page_main==='edit_assignment'&&$assignment_id_bk_url_main):?><input type="hidden" name="assignment_id" value="<?php echo $assignment_id_bk_url_main;?>"><?php endif;?>
            <div class="row">
                <div class="col-md-6 mb-3"><label for="bk_f_teacher" class="form-label">مدرس <span class="text-danger">*</span></label><select class="form-select <?php echo isset($form_errors_bk_page_main['user_id'])?'is-invalid':'';?>" id="bk_f_teacher" name="user_id" required><option value="">-- انتخاب مدرس --</option><?php foreach($available_teachers_bk_list as $t_opt_f):?><option value="<?php echo $t_opt_f['UserID'];?>" <?php echo (($assignment_data_form_bk_display['UserID']??null)==$t_opt_f['UserID'])?'selected':'';?>><?php echo htmlspecialchars(trim($t_opt_f['LastName'].' '.$t_opt_f['FirstName']).' (@'.$t_opt_f['Username'].')');?></option><?php endforeach;?></select><?php if(isset($form_errors_bk_page_main['user_id'])):?><div class="invalid-feedback"><?php echo $form_errors_bk_page_main['user_id'];?></div><?php endif;?></div>
                <div class="col-md-6 mb-3"><label for="bk_f_booklet" class="form-label">جزوه <span class="text-danger">*</span></label><select class="form-select <?php echo isset($form_errors_bk_page_main['booklet_id'])?'is-invalid':'';?>" id="bk_f_booklet" name="booklet_id" required><option value="">-- انتخاب جزوه --</option><?php foreach($available_booklets_bk_list as $b_opt_f):?><option value="<?php echo $b_opt_f['BookletID'];?>" data-cost="<?php echo $b_opt_f['CostPrice'];?>" <?php echo (($assignment_data_form_bk_display['BookletID']??null)==$b_opt_f['BookletID'])?'selected':'';?>><?php echo htmlspecialchars($b_opt_f['Title'].' ('.number_format($b_opt_f['CostPrice']).' تومان)');?></option><?php endforeach;?></select><?php if(isset($form_errors_bk_page_main['booklet_id'])):?><div class="invalid-feedback"><?php echo $form_errors_bk_page_main['booklet_id'];?></div><?php endif;?></div>
            </div>
            <div class="row">
                <div class="col-md-3 mb-3"><label for="bk_f_qty" class="form-label">تعداد <span class="text-danger">*</span></label><input type="number" class="form-control <?php echo isset($form_errors_bk_page_main['quantity_assigned'])?'is-invalid':'';?>" id="bk_f_qty" name="quantity_assigned" value="<?php echo htmlspecialchars($assignment_data_form_bk_display['QuantityAssigned']??'1');?>" min="1" required><?php if(isset($form_errors_bk_page_main['quantity_assigned'])):?><div class="invalid-feedback"><?php echo $form_errors_bk_page_main['quantity_assigned'];?></div><?php endif;?></div>
                <div class="col-md-3 mb-3"><label for="bk_f_adate" class="form-label">تاریخ تحویل <span class="text-danger">*</span></label><input type="text" class="form-control persian-datepicker <?php echo isset($form_errors_bk_page_main['assignment_date'])?'is-invalid':'';?>" id="bk_f_adate" name="assignment_date" value="<?php echo htmlspecialchars($assignment_data_form_bk_display['AssignmentDate']??'');?>" required><?php if(isset($form_errors_bk_page_main['assignment_date'])):?><div class="invalid-feedback"><?php echo $form_errors_bk_page_main['assignment_date'];?></div><?php endif;?></div>
                <div class="col-md-3 mb-3"><label for="bk_f_month" class="form-label">ماه مربوطه <span class="text-danger">*</span></label><input type="text" class="form-control <?php echo isset($form_errors_bk_page_main['month_identifier'])?'is-invalid':'';?>" id="bk_f_month" name="month_identifier" value="<?php echo htmlspecialchars($assignment_data_form_bk_display['MonthIdentifier']?? date('Y-m'));?>" placeholder="مثال: 1403-05" required pattern="\d{4}-\d{2}"><?php if(isset($form_errors_bk_page_main['month_identifier'])):?><div class="invalid-feedback"><?php echo $form_errors_bk_page_main['month_identifier'];?></div><?php endif;?></div>
                <div class="col-md-3 mb-3"><label for="bk_f_paid" class="form-label">مبلغ پرداختی (تومان)</label><input type="text" class="form-control" id="bk_f_paid" name="amount_paid" value="<?php echo isset($assignment_data_form_bk_display['AmountPaid'])?htmlspecialchars(number_format($assignment_data_form_bk_display['AmountPaid'],0,'',',')):'0';?>"><?php if(isset($form_errors_bk_page_main['amount_paid'])):?><div class="invalid-feedback"><?php echo $form_errors_bk_page_main['amount_paid'];?></div><?php endif;?></div>
            </div>
            <div class="mb-3"><label for="bk_f_notes" class="form-label">ملاحظات</label><textarea class="form-control" id="bk_f_notes" name="notes" rows="2"><?php echo htmlspecialchars($assignment_data_form_bk_display['Notes']??'');?></textarea></div>
            <div class="form-actions"><button type="submit" name="save_booklet_assignment" class="btn btn-success"><em class="bi bi-journal-check icon"></em> ذخیره</button><a href="booklets.php?action=list_assignments" class="btn btn-outline-secondary">انصراف</a></div>
        </form>
    </div></div>
<?php elseif($action_bk_page_main === 'list_booklets'): ?>
     <div class="mb-3"><a href="?action=create_booklet" class="btn btn-success"><em class="bi bi-journal-plus me-2"></em>تعریف جزوه جدید</a></div>
    <div class="card"><div class="card-body">
    <?php if(empty($booklets_list_bk_display)): ?><p class="text-center text-muted py-3">هیچ جزوه‌ای تعریف نشده.</p>
    <?php else: ?><div class="table-responsive"><table class="table table-hover table-sm">
        <thead class="table-light"><tr><th>#</th><th>عنوان جزوه</th><th>هزینه (تومان)</th><th>موجودی انبار (نمونه)</th><th class="actions-column">عملیات</th></tr></thead>
        <tbody><?php foreach($booklets_list_bk_display as $idx_bkl_d => $bkl_d): ?>
            <tr><td><?php echo $idx_bkl_d+1;?></td><td><a href="?action=edit_booklet&booklet_id=<?php echo $bkl_d['BookletID'];?>"><?php echo htmlspecialchars($bkl_d['Title']);?></a></td><td class="text-end"><?php echo number_format($bkl_d['CostPrice']);?></td><td><?php echo $bkl_d['StockQuantity'] ?? 'ن/م';?></td>
            <td class="actions-cell"><a href="?action=edit_booklet&booklet_id=<?php echo $bkl_d['BookletID'];?>" class="btn btn-sm btn-outline-info" title="ویرایش"><em class="bi bi-pencil-square"></em></a></td></tr>
        <?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
    </div></div>
<?php elseif($action_bk_page_main === 'create_booklet' || $action_bk_page_main === 'edit_booklet'): ?>
    <div class="card"><div class="card-header"><h5 class="mb-0"><?php echo $action_bk_page_main === 'create_booklet' ? "تعریف جزوه جدید" : "ویرایش جزوه"; ?></h5></div><div class="card-body">
        <form method="POST" action="booklets.php<?php echo ($action_bk_page_main==='edit_booklet'&&$booklet_id_bk_url_main)?'?action=edit_booklet&booklet_id='.$booklet_id_bk_url_main:'?action=create_booklet';?>" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_booklet_form_page; ?>">
            <?php if($action_bk_page_main==='edit_booklet'&&$booklet_id_bk_url_main):?><input type="hidden" name="booklet_id" value="<?php echo $booklet_id_bk_url_main;?>"><?php endif;?>
            <div class="mb-3"><label for="bk_def_title" class="form-label">عنوان جزوه <span class="text-danger">*</span></label><input type="text" class="form-control <?php echo isset($form_errors_bk_page_main['title'])?'is-invalid':'';?>" id="bk_def_title" name="title" value="<?php echo htmlspecialchars($booklet_data_form_bk_display['Title']??'');?>" required><?php if(isset($form_errors_bk_page_main['title'])):?><div class="invalid-feedback"><?php echo $form_errors_bk_page_main['title'];?></div><?php endif;?></div>
            <div class="row"><div class="col-md-6 mb-3"><label for="bk_def_cost" class="form-label">هزینه/قیمت واحد (تومان) <span class="text-danger">*</span></label><input type="text" class="form-control <?php echo isset($form_errors_bk_page_main['cost_price'])?'is-invalid':'';?>" id="bk_def_cost" name="cost_price" value="<?php echo isset($booklet_data_form_bk_display['CostPrice'])?htmlspecialchars(number_format($booklet_data_form_bk_display['CostPrice'],0,'',',')):'';?>" required><?php if(isset($form_errors_bk_page_main['cost_price'])):?><div class="invalid-feedback"><?php echo $form_errors_bk_page_main['cost_price'];?></div><?php endif;?></div>
            <div class="col-md-6 mb-3"><label for="bk_def_stock" class="form-label">موجودی اولیه انبار (اختیاری)</label><input type="number" class="form-control <?php echo isset($form_errors_bk_page_main['stock_quantity'])?'is-invalid':'';?>" id="bk_def_stock" name="stock_quantity" value="<?php echo htmlspecialchars($booklet_data_form_bk_display['StockQuantity']??'0');?>" min="0"><?php if(isset($form_errors_bk_page_main['stock_quantity'])):?><div class="invalid-feedback"><?php echo $form_errors_bk_page_main['stock_quantity'];?></div><?php endif;?></div></div>
            <div class="mb-3"><label for="bk_def_desc" class="form-label">توضیحات جزوه</label><textarea class="form-control" id="bk_def_desc" name="description" rows="3"><?php echo htmlspecialchars($booklet_data_form_bk_display['Description']??'');?></textarea></div>
            <div class="form-actions"><button type="submit" name="save_booklet_definition" class="btn btn-success"><em class="bi bi-journal-bookmark-fill icon"></em> ذخیره جزوه</button><a href="booklets.php?action=list_booklets" class="btn btn-outline-secondary">انصراف</a></div>
        </form>
    </div></div>
<?php endif; ?>

<link rel="stylesheet" href="<?php echo get_base_url(); ?>assets/js/persian-datepicker/persian-datepicker.min.css"/>
<script src="<?php echo get_base_url(); ?>assets/js/jquery.min.js"></script>
<script src="<?php echo get_base_url(); ?>assets/js/persian-datepicker/persian-date.min.js"></script>
<script src="<?php echo get_base_url(); ?>assets/js/persian-datepicker/persian-datepicker.min.js"></script>
<script>
$(document).ready(function(){
    if($(".persian-datepicker").length){$(".persian-datepicker").persianDatepicker({format:'YYYY/MM/DD',autoClose:true,observer:true,initialValue:false});}
    $('input[name="amount_paid"], input[name="cost_price"]').on('keyup', function(event) { if (event.which >= 37 && event.which <= 40) return; $(this).val(function(index, value) { return value.replace(/\D/g, "").replace(/\B(?=(\d{3})+(?!\d))/g, ",");});});
    // TODO: JS for delete modals for assignments and booklets
});
</script>
<style>.badge.bg-purple { background-color: #6f42c1; color: white; }</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
