<?php
require_once __DIR__ . '/../includes/header.php';

$action_dn_page_main = $_GET['action'] ?? 'list_donations';
$donation_id_dn_url_main = isset($_GET['donation_id']) ? (int)$_GET['donation_id'] : 0;
$donor_id_dn_url_main = isset($_GET['donor_id']) ? (int)$_GET['donor_id'] : 0;
$project_id_dn_url_main = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

$page_title_dn_page_main = "مدیریت صله و کمک‌های مالی";
$form_errors_dn_page_main = [];

$donations_list_display = []; $donors_list_display = []; $projects_list_display = [];
$donation_data_for_form_display = null; $donor_data_for_form_display = null; $project_data_for_form_display = null;

// CSRF Tokens
$csrf_token_donation_form_page = generate_csrf_token('donation_form_action_main');
$csrf_token_donor_form_page = generate_csrf_token('donor_form_action_main');
$csrf_token_project_form_page = generate_csrf_token('donation_project_form_action_main');
$csrf_token_delete_page = generate_csrf_token('finance_delete_action_main'); // Generic delete token for modals

// Statuses & Types
$donation_statuses_map_display = ['pledged' => 'تعهد شده', 'collected' => 'جمع‌آوری شده', 'pending_collection' => 'در انتظار جمع‌آوری', 'cancelled' => 'لغو شده'];
$project_statuses_map_display = ['active' => 'فعال', 'planning' => 'در حال برنامه‌ریزی', 'completed' => 'تکمیل شده', 'on_hold' => 'متوقف شده', 'cancelled' => 'لغو شده'];
if (!function_exists('get_finance_status_badge_class')) { function get_finance_status_badge_class($s_key){ $s_lower=strtolower($s_key??''); if(in_array($s_lower,['collected','completed','active'])) return 'success'; if(in_array($s_lower,['pending_collection','planning','on_hold'])) return 'warning text-dark'; if(in_array($s_lower,['pledged'])) return 'info'; if($s_lower=='cancelled') return 'danger'; return 'secondary';}}


// --- POST Handler ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Donation Save ---
    if (isset($_POST['save_donation'])) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '', $csrf_token_donation_form_page)) { $form_errors_dn_page_main['csrf'] = "خطای CSRF."; }
        else {
            $csrf_token_donation_form_page = regenerate_csrf_token($csrf_token_donation_form_page);
            $donation_id_posted = isset($_POST['donation_id']) ? (int)$_POST['donation_id'] : 0;
            $donor_id_form = isset($_POST['donor_id']) ? (int)$_POST['donor_id'] : null;
            $project_id_form = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
            $amount_form = !empty($_POST['amount']) ? filter_var(str_replace(',','',$_POST['amount']), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : 0;
            $donation_date_jalali = sanitize_input($_POST['donation_date'] ?? '');
            $payment_method_form = sanitize_input($_POST['payment_method'] ?? 'cash');
            $status_form_dn = sanitize_input($_POST['status'] ?? 'collected');
            $notes_form_dn = sanitize_input($_POST['notes'] ?? null);

            $donation_data_for_form_display = $_POST; $donation_data_for_form_display['DonationDate'] = $donation_date_jalali;

            if(!$donor_id_form) $form_errors_dn_page_main['donor_id'] = "انتخاب خیر الزامی است.";
            if($amount_form <=0) $form_errors_dn_page_main['amount'] = "مبلغ باید بیشتر از صفر باشد.";
            if(empty($donation_date_jalali)) $form_errors_dn_page_main['donation_date'] = "تاریخ کمک الزامی است.";
            $donation_date_greg = null; if(!empty($donation_date_jalali)){ $donation_date_greg = to_gregorian_date_for_db($donation_date_jalali); if(!$donation_date_greg) $form_errors_dn_page_main['donation_date'] = "فرمت تاریخ نامعتبر.";}

            if(empty($form_errors_dn_page_main) && $conn){
                $current_user_id_dn_save = get_current_user_id();
                if($donation_id_posted > 0){ // Update Donation
                    $stmt_dn_save = $conn->prepare("UPDATE Donations SET DonorID=?, ProjectID=?, Amount=?, DonationDate=?, PaymentMethod=?, Status=?, Notes=?, UpdatedAt=NOW(), RecordedByUserID=? WHERE DonationID=?");
                    if($stmt_dn_save){ $stmt_dn_save->bind_param("iidssssii", $donor_id_form, $project_id_form, $amount_form, $donation_date_greg, $payment_method_form, $status_form_dn, $notes_form_dn, $current_user_id_dn_save, $donation_id_posted);
                        if($stmt_dn_save->execute()){ $_SESSION['action_success_finance']="کمک مالی بروزرسانی شد."; header("Location: donations.php?action=list_donations"); exit; } else $form_errors_dn_page_main['db']="خطا بروزرسانی: ".$stmt_dn_save->error; $stmt_dn_save->close(); }
                } else { // Create Donation
                    $stmt_dn_save = $conn->prepare("INSERT INTO Donations (DonorID, ProjectID, Amount, DonationDate, PaymentMethod, Status, Notes, RecordedAt, RecordedByUserID) VALUES (?,?,?,?,?,?,?,NOW(),?)");
                     if($stmt_dn_save){ $stmt_dn_save->bind_param("iidssssi", $donor_id_form, $project_id_form, $amount_form, $donation_date_greg, $payment_method_form, $status_form_dn, $notes_form_dn, $current_user_id_dn_save);
                        if($stmt_dn_save->execute()){ $_SESSION['action_success_finance']="کمک مالی ثبت شد."; header("Location: donations.php?action=list_donations"); exit; } else $form_errors_dn_page_main['db']="خطا ثبت: ".$stmt_dn_save->error; $stmt_dn_save->close(); }
                }
            }
            $action_dn_page_main = $donation_id_posted > 0 ? 'edit_donation' : 'add_donation';
        }
    }
    // TODO: Add POST handlers for Donor (save_donor, delete_donor_confirmed)
    // TODO: Add POST handlers for Project (save_project, delete_project_confirmed)
}


// --- Fetch Data for Display ---
if ($conn) {
    // Always fetch donors and projects for dropdowns on form pages
    if (str_contains($action_dn_page_main, 'donation') || str_contains($action_dn_page_main, 'project')) {
        $res_donors_dd = $conn->query("SELECT DonorID, DonorName, PhoneNumber FROM Donors ORDER BY DonorName ASC");
        if($res_donors_dd) while($row_d_dd = $res_donors_dd->fetch_assoc()) $donors_list_display[] = $row_d_dd; // Used for donor dropdown
    }
    if (str_contains($action_dn_page_main, 'donation')) {
        $res_projects_dd = $conn->query("SELECT ProjectID, ProjectName FROM DonationProjects WHERE Status IN ('active', 'planning') ORDER BY ProjectName ASC");
        if($res_projects_dd) while($row_p_dd = $res_projects_dd->fetch_assoc()) $projects_list_display[] = $row_p_dd; // Used for project dropdown
    }


    if ($action_dn_page_main === 'list_donations') {
        $page_title_dn_page_main = "لیست کمک‌های مالی (صله)";
        $sql_dn_list = "SELECT d.DonationID, d.Amount, d.DonationDate, d.PaymentMethod, d.Status, dr.DonorName, dp.ProjectName
                        FROM Donations d
                        JOIN Donors dr ON d.DonorID = dr.DonorID
                        LEFT JOIN DonationProjects dp ON d.ProjectID = dp.ProjectID
                        ORDER BY d.DonationDate DESC, d.RecordedAt DESC";
        $res_dn_list_exec = $conn->query($sql_dn_list);
        if($res_dn_list_exec) while($row_dn_l = $res_dn_list_exec->fetch_assoc()) $donations_list_display[] = $row_dn_l;
        else $form_errors_dn_page_main['db_list_donations'] = "خطا بارگذاری لیست کمک‌ها: " . $conn->error;
    }
    elseif (($action_dn_page_main === 'add_donation' || ($action_dn_page_main === 'edit_donation' && $donation_id_dn_url_main > 0)) && !$donation_data_for_form_display ) {
        $page_title_dn_page_main = ($action_dn_page_main === 'add_donation') ? "ثبت کمک مالی جدید" : "ویرایش کمک مالی";
        if($action_dn_page_main === 'edit_donation'){
            $stmt_dn_edit = $conn->prepare("SELECT * FROM Donations WHERE DonationID = ?");
            if($stmt_dn_edit){ $stmt_dn_edit->bind_param("i", $donation_id_dn_url_main); $stmt_dn_edit->execute(); $res_d_edit = $stmt_dn_edit->get_result();
                if(!($donation_data_for_form_display = $res_d_edit->fetch_assoc())){ $_SESSION['action_error_finance']="کمک مالی یافت نشد."; header("Location: donations.php"); exit;}
                if(!empty($donation_data_for_form_display['DonationDate'])) $donation_data_for_form_display['DonationDate'] = to_jalali($donation_data_for_form_display['DonationDate'], 'yyyy/MM/dd');
                $stmt_dn_edit->close();
            } else {$form_errors_dn_page_main['db_load'] = "خطا بارگذاری کمک: ".$conn->error;}
        } else { // add_donation
            $donation_data_for_form_display = ['DonorID'=>null, 'ProjectID'=>null, 'Amount'=>'', 'DonationDate'=>to_jalali(date('Y-m-d'),'yyyy/MM/dd'), 'PaymentMethod'=>'cash', 'Status'=>'collected', 'Notes'=>''];
        }
    }
    // TODO: Add data fetching for list_donors, add_donor, edit_donor, list_projects, add_project, edit_project
    elseif ($action_dn_page_main === 'list_donors') {
        $page_title_dn_page_main = "لیست خیرین";
        // Donors already fetched into $donors_list_display if action contains 'donation' or 'project'
        // If navigating directly to list_donors, fetch them if not already fetched.
        if(empty($donors_list_display)){
            $res_donors_list_page = $conn->query("SELECT DonorID, DonorName, PhoneNumber, Email, SUM(d.Amount) as TotalDonated, COUNT(d.DonationID) as DonationCount FROM Donors LEFT JOIN Donations d ON Donors.DonorID = d.DonorID AND d.Status='collected' GROUP BY Donors.DonorID ORDER BY DonorName ASC");
            if($res_donors_list_page) while($row_dl = $res_donors_list_page->fetch_assoc()) $donors_list_display[] = $row_dl;
            else $form_errors_dn_page_main['db_list_donors'] = "خطا در بارگذاری لیست خیرین: " . $conn->error;
        }
    }
    elseif ($action_dn_page_main === 'list_projects') {
        $page_title_dn_page_main = "لیست پروژه‌های مالی";
         // Projects already fetched into $projects_list_display if action contains 'donation'
        if(empty($projects_list_display)){
            $res_projects_list_page = $conn->query("SELECT ProjectID, ProjectName, Description, TargetAmount, Status, StartDate, EndDate, (SELECT SUM(Amount) FROM Donations WHERE ProjectID = DonationProjects.ProjectID AND Status='collected') as CollectedAmount FROM DonationProjects ORDER BY StartDate DESC");
            if($res_projects_list_page) while($row_pl = $res_projects_list_page->fetch_assoc()) $projects_list_display[] = $row_pl;
            else $form_errors_dn_page_main['db_list_projects'] = "خطا در بارگذاری لیست پروژه‌ها: " . $conn->error;
        }
    }


} else {
    $form_errors_dn_page_main['db_conn_main_fetch'] = "خطا در اتصال به پایگاه داده.";
}
?>
<div class="page-header"><h1><?php echo $page_title_dn_page_main; ?></h1>
    <div class="btn-toolbar" role="toolbar">
        <div class="btn-group me-2 mb-2" role="group" aria-label="Donation Actions">
            <a href="donations.php?action=list_donations" class="btn <?php echo (str_contains($action_dn_page_main, 'donation') && !str_contains($action_dn_page_main,'add_') && !str_contains($action_dn_page_main,'edit_')) ?'btn-primary':'btn-outline-primary';?> btn-sm"><em class="bi bi-cash-stack icon"></em> لیست کمک‌ها</a>
            <a href="donations.php?action=add_donation" class="btn <?php echo $action_dn_page_main==='add_donation'?'btn-primary':'btn-outline-primary';?> btn-sm"><em class="bi bi-plus-circle icon"></em> ثبت کمک جدید</a>
        </div>
        <div class="btn-group me-2 mb-2" role="group" aria-label="Donor Actions">
            <a href="donations.php?action=list_donors" class="btn <?php echo (str_contains($action_dn_page_main, 'donor') && !str_contains($action_dn_page_main,'add_') && !str_contains($action_dn_page_main,'edit_')) ?'btn-primary':'btn-outline-primary';?> btn-sm"><em class="bi bi-person-heart icon"></em> لیست خیرین</a>
            <a href="donations.php?action=add_donor" class="btn <?php echo $action_dn_page_main==='add_donor'?'btn-primary':'btn-outline-primary';?> btn-sm"><em class="bi bi-person-plus icon"></em> ثبت خیر جدید</a>
        </div>
        <div class="btn-group mb-2" role="group" aria-label="Project Actions">
            <a href="donations.php?action=list_projects" class="btn <?php echo (str_contains($action_dn_page_main, 'project') && !str_contains($action_dn_page_main,'add_') && !str_contains($action_dn_page_main,'edit_')) ?'btn-primary':'btn-outline-primary';?> btn-sm"><em class="bi bi-kanban icon"></em> لیست پروژه‌ها</a>
            <a href="donations.php?action=add_project" class="btn <?php echo $action_dn_page_main==='add_project'?'btn-primary':'btn-outline-primary';?> btn-sm"><em class="bi bi-clipboard-plus icon"></em> تعریف پروژه جدید</a>
        </div>
        <a href="index.php" class="btn btn-outline-dark ms-auto mb-2 btn-sm"><em class="bi bi-coin icon"></em> داشبورد مالی</a>
    </div>
</div>

<?php if(isset($_SESSION['action_success_finance'])):?><div class="alert alert-success alert-dismissible fade show"><button class="btn-close" data-bs-dismiss="alert"></button><?php echo $_SESSION['action_success_finance']; unset($_SESSION['action_success_finance']);?></div><?php endif;?>
<?php if(isset($_SESSION['action_error_finance'])):?><div class="alert alert-danger alert-dismissible fade show"><button class="btn-close" data-bs-dismiss="alert"></button><?php echo $_SESSION['action_error_finance']; unset($_SESSION['action_error_finance']);?></div><?php endif;?>
<?php if(!empty($form_errors_dn_page_main)):?><div class="alert alert-danger alert-dismissible fade show"><strong>خطا:</strong><ul class="mb-0 ps-3"><?php foreach($form_errors_dn_page_main as $e_dn_key=>$e_dn_msg):echo "<li>".htmlspecialchars($e_dn_msg)."</li>";endforeach;?></ul><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif;?>


<?php if($action_dn_page_main === 'list_donations'): ?>
    <div class="card"><div class="card-body">
    <?php if(empty($donations_list_display)): ?><p class="text-center text-muted py-3">هیچ کمک مالی ثبت نشده. <a href="?action=add_donation">اولین کمک را ثبت کنید</a>.</p>
    <?php else: ?><div class="table-responsive"><table class="table table-hover table-sm">
        <thead class="table-light"><tr><th>#</th><th>خیر</th><th>پروژه</th><th>مبلغ (تومان)</th><th>تاریخ</th><th>روش</th><th>وضعیت</th><th class="actions-column">عملیات</th></tr></thead>
        <tbody><?php foreach($donations_list_display as $idx_dn_l => $dn_l): ?>
            <tr><td><?php echo $idx_dn_l+1;?></td><td><?php echo htmlspecialchars($dn_l['DonorName']);?></td><td><?php echo htmlspecialchars($dn_l['ProjectName']?:'عمومی');?></td><td class="text-end"><?php echo number_format($dn_l['Amount']);?></td><td><?php echo to_jalali($dn_l['DonationDate'],'yy/MM/dd');?></td><td><?php echo htmlspecialchars($dn_l['PaymentMethod']);?></td><td><span class="badge bg-<?php echo get_finance_status_badge_class($dn_l['Status']);?>"><?php echo $donation_statuses_map_display[$dn_l['Status']]??$dn_l['Status'];?></span></td>
            <td class="actions-cell"><a href="?action=edit_donation&donation_id=<?php echo $dn_l['DonationID'];?>" class="btn btn-sm btn-outline-info" title="ویرایش"><em class="bi bi-pencil-square"></em></a></td></tr>
        <?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
    </div></div>
<?php elseif($action_dn_page_main === 'add_donation' || $action_dn_page_main === 'edit_donation'): ?>
    <div class="card"><div class="card-header"><h5 class="mb-0"><?php echo $action_dn_page_main === 'add_donation' ? "ثبت کمک مالی جدید" : "ویرایش کمک مالی"; ?></h5></div><div class="card-body">
        <form method="POST" action="donations.php<?php echo ($action_dn_page_main==='edit_donation'&&$donation_id_dn_url_main)?'?action=edit_donation&donation_id='.$donation_id_dn_url_main:'?action=add_donation';?>" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_donation_form_page; ?>">
            <?php if($action_dn_page_main==='edit_donation'&&$donation_id_dn_url_main):?><input type="hidden" name="donation_id" value="<?php echo $donation_id_dn_url_main;?>"><?php endif;?>
            <div class="row">
                <div class="col-md-6 mb-3"><label for="dn_f_donor" class="form-label">خیر <span class="text-danger">*</span></label><select class="form-select <?php echo isset($form_errors_dn_page_main['donor_id'])?'is-invalid':'';?>" id="dn_f_donor" name="donor_id" required><option value="">-- انتخاب خیر --</option><?php if(empty($donors_list_display)) echo '<option value="" disabled>ابتدا یک خیر ثبت کنید</option>'; foreach($donors_list_display as $d_opt_f):?><option value="<?php echo $d_opt_f['DonorID'];?>" <?php echo (($donation_data_for_form_display['DonorID']??null)==$d_opt_f['DonorID'])?'selected':'';?>><?php echo htmlspecialchars($d_opt_f['DonorName'].($d_opt_f['PhoneNumber']?' ('.$d_opt_f['PhoneNumber'].')':''));?></option><?php endforeach;?></select><?php if(isset($form_errors_dn_page_main['donor_id'])):?><div class="invalid-feedback"><?php echo $form_errors_dn_page_main['donor_id'];?></div><?php endif;?></div>
                <div class="col-md-6 mb-3"><label for="dn_f_project" class="form-label">پروژه مرتبط</label><select class="form-select" id="dn_f_project" name="project_id"><option value="">-- عمومی / بدون پروژه --</option><?php if(empty($projects_list_display)) echo '<option value="" disabled>ابتدا یک پروژه تعریف کنید</option>'; foreach($projects_list_display as $p_opt_f):?><option value="<?php echo $p_opt_f['ProjectID'];?>" <?php echo (($donation_data_for_form_display['ProjectID']??null)==$p_opt_f['ProjectID'])?'selected':'';?>><?php echo htmlspecialchars($p_opt_f['ProjectName']);?></option><?php endforeach;?></select></div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3"><label for="dn_f_amount" class="form-label">مبلغ (تومان) <span class="text-danger">*</span></label><input type="text" class="form-control <?php echo isset($form_errors_dn_page_main['amount'])?'is-invalid':'';?>" id="dn_f_amount" name="amount" value="<?php echo isset($donation_data_for_form_display['Amount'])?htmlspecialchars(number_format($donation_data_for_form_display['Amount'],0,'',',')):'';?>" required><?php if(isset($form_errors_dn_page_main['amount'])):?><div class="invalid-feedback"><?php echo $form_errors_dn_page_main['amount'];?></div><?php endif;?></div>
                <div class="col-md-4 mb-3"><label for="dn_f_date" class="form-label">تاریخ کمک <span class="text-danger">*</span></label><input type="text" class="form-control persian-datepicker <?php echo isset($form_errors_dn_page_main['donation_date'])?'is-invalid':'';?>" id="dn_f_date" name="donation_date" value="<?php echo htmlspecialchars($donation_data_for_form_display['DonationDate']??'');?>" required><?php if(isset($form_errors_dn_page_main['donation_date'])):?><div class="invalid-feedback"><?php echo $form_errors_dn_page_main['donation_date'];?></div><?php endif;?></div>
                <div class="col-md-4 mb-3"><label for="dn_f_method" class="form-label">روش پرداخت</label><input type="text" class="form-control" id="dn_f_method" name="payment_method" value="<?php echo htmlspecialchars($donation_data_for_form_display['PaymentMethod']??'cash');?>" list="payment_methods_list_datalist"><datalist id="payment_methods_list_datalist"><option value="نقدی"><option value="کارت به کارت"><option value="دستگاه POS"><option value="چک"><option value="آنلاین"></datalist></div>
            </div>
            <div class="mb-3"><label for="dn_f_status" class="form-label">وضعیت <span class="text-danger">*</span></label><select class="form-select" id="dn_f_status" name="status" required><?php foreach($donation_statuses_map_display as $dsk_f=>$dsv_f):?><option value="<?php echo $dsk_f;?>" <?php echo (($donation_data_for_form_display['Status']??'collected')===$dsk_f)?'selected':'';?>><?php echo $dsv_f;?></option><?php endforeach;?></select></div>
            <div class="mb-3"><label for="dn_f_notes" class="form-label">ملاحظات</label><textarea class="form-control" id="dn_f_notes" name="notes" rows="2"><?php echo htmlspecialchars($donation_data_for_form_display['Notes']??'');?></textarea></div>
            <div class="form-actions"><button type="submit" name="save_donation" class="btn btn-success"><em class="bi bi-cash icon"></em> ذخیره</button><a href="donations.php?action=list_donations" class="btn btn-outline-secondary">انصراف</a></div>
        </form>
    </div></div>
<?php elseif($action_dn_page_main === 'list_donors'): ?>
    <p class="text-muted">بخش مدیریت خیرین (افزودن، ویرایش، حذف) در حال توسعه است. فعلا لیست خیرین نمایش داده می‌شود.</p>
    <div class="card"><div class="card-body">
    <?php if(empty($donors_list_display)): ?><p class="text-center text-muted py-3">هیچ خیری ثبت نشده.</p>
    <?php else: ?><div class="table-responsive"><table class="table table-hover table-sm">
        <thead class="table-light"><tr><th>#</th><th>نام خیر</th><th>شماره تماس</th><th>ایمیل</th><th>تعداد کمک‌ها</th><th>مجموع کمک (تومان)</th><th class="actions-column">عملیات</th></tr></thead>
        <tbody><?php foreach($donors_list_display as $idx_dl => $dl): ?>
            <tr><td><?php echo $idx_dl+1;?></td><td><?php echo htmlspecialchars($dl['DonorName']);?></td><td><?php echo htmlspecialchars($dl['PhoneNumber']?:'---');?></td><td><?php echo htmlspecialchars($dl['Email']?:'---');?></td><td><?php echo $dl['DonationCount'] ?? 0;?></td><td class="text-end"><?php echo number_format($dl['TotalDonated']??0);?></td>
            <td class="actions-cell"><button class="btn btn-sm btn-outline-info" disabled title="ویرایش (به زودی)"><em class="bi bi-pencil-square"></em></button></td></tr>
        <?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
    </div></div>
<?php elseif($action_dn_page_main === 'list_projects'): ?>
     <p class="text-muted">بخش مدیریت پروژه‌ها (افزودن، ویرایش، حذف) در حال توسعه است. فعلا لیست پروژه‌ها نمایش داده می‌شود.</p>
    <div class="card"><div class="card-body">
    <?php if(empty($projects_list_display)): ?><p class="text-center text-muted py-3">هیچ پروژه‌ای تعریف نشده.</p>
    <?php else: ?><div class="table-responsive"><table class="table table-hover table-sm">
        <thead class="table-light"><tr><th>#</th><th>نام پروژه</th><th>تاریخ شروع</th><th>تاریخ پایان</th><th>وضعیت</th><th>مبلغ هدف (تومان)</th><th>مبلغ جمع‌آوری شده (تومان)</th><th class="actions-column">عملیات</th></tr></thead>
        <tbody><?php foreach($projects_list_display as $idx_pl => $pl): ?>
            <tr><td><?php echo $idx_pl+1;?></td><td><?php echo htmlspecialchars($pl['ProjectName']);?></td><td><?php echo $pl['StartDate']?to_jalali($pl['StartDate'],'yy/MM/dd'):'---';?></td><td><?php echo $pl['EndDate']?to_jalali($pl['EndDate'],'yy/MM/dd'):'---';?></td><td><span class="badge bg-<?php echo get_finance_status_badge_class($pl['Status']);?>"><?php echo $project_statuses_map_display[$pl['Status']]??$pl['Status'];?></span></td><td class="text-end"><?php echo $pl['TargetAmount']?number_format($pl['TargetAmount']):'---';?></td><td class="text-end"><?php echo number_format($pl['CollectedAmount']??0);?></td>
            <td class="actions-cell"><button class="btn btn-sm btn-outline-info" disabled title="ویرایش (به زودی)"><em class="bi bi-pencil-square"></em></button></td></tr>
        <?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
    </div></div>
<?php else: ?>
    <div class="alert alert-light">برای مشاهده یا مدیریت اطلاعات، یکی از بخش‌های "کمک‌های مالی"، "خیرین" یا "پروژه‌ها" را از منوی بالا انتخاب کنید.</div>
<?php endif; ?>

<link rel="stylesheet" href="<?php echo get_base_url(); ?>assets/js/persian-datepicker/persian-datepicker.min.css"/>
<script src="<?php echo get_base_url(); ?>assets/js/jquery.min.js"></script>
<script src="<?php echo get_base_url(); ?>assets/js/persian-datepicker/persian-date.min.js"></script>
<script src="<?php echo get_base_url(); ?>assets/js/persian-datepicker/persian-datepicker.min.js"></script>
<script>
$(document).ready(function(){
    if($(".persian-datepicker").length){$(".persian-datepicker").persianDatepicker({format:'YYYY/MM/DD',autoClose:true,observer:true,initialValue:false});}
    $('input[name="amount"], input[name="budget_required"], input[name="actual_cost"]').on('keyup', function(event) { if (event.which >= 37 && event.which <= 40) return; $(this).val(function(index, value) { return value.replace(/\D/g, "").replace(/\B(?=(\d{3})+(?!\d))/g, ",");});});
    // TODO: JS for delete modals for donations, donors, projects when their list views are fully implemented with delete buttons
});
</script>
<style>.badge.bg-purple { background-color: #6f42c1; color: white; }</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
