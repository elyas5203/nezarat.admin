<?php
// admin/finance/donations.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth

$csrf_token_donations = generate_csrf_token('finance_donations_action');
$errors_don = [];
$edit_mode_don = false;
$donation_to_edit_values_default = [
    'DonationID' => null, 'DonorName' => '', 'DonorPhoneNumber' => '', 'ProjectID' => null,
    'Amount' => '', 'DonationDate' => date('Y-m-d'), 'Status' => 'pledged', 'Notes' => '',
    'CollectedByUserID' => null
];
$donation_to_edit_values = $donation_to_edit_values_default;


$projects_q_don_page = $conn->query("SELECT ProjectID, ProjectName FROM ParvareshiProjects WHERE Status NOT IN ('archived', 'cancelled') ORDER BY ProjectName");
$available_projects_don_page = [];
if ($projects_q_don_page) { while($proj_d_page = $projects_q_don_page->fetch_assoc()) $available_projects_don_page[$proj_d_page['ProjectID']] = $proj_d_page['ProjectName']; $projects_q_don_page->close(); }

$donation_status_options_disp = ['pledged' => 'تعهد شده', 'collected' => 'جمع‌آوری شده', 'follow_up_needed' => 'نیاز به پیگیری', 'cancelled' => 'لغو شده'];
$donation_status_badge_disp = ['pledged' => 'info', 'collected' => 'success', 'follow_up_needed' => 'warning', 'cancelled' => 'secondary'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_donation'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'finance_donations_action')) {
        $errors_don[] = 'خطای CSRF!';
    } else {
        $donation_id_post_val = isset($_POST['donation_id']) && is_numeric($_POST['donation_id']) ? (int)$_POST['donation_id'] : null;

        $donation_to_edit_values['DonorName'] = sanitize_input($_POST['DonorName'] ?? '');
        $donation_to_edit_values['DonorPhoneNumber'] = sanitize_input($_POST['DonorPhoneNumber'] ?? '');
        $donation_to_edit_values['ProjectID'] = !empty($_POST['ProjectID']) ? (int)$_POST['ProjectID'] : null;
        $donation_to_edit_values['Amount'] = !empty($_POST['Amount']) ? floatval(preg_replace('/[^0-9.]/', '', $_POST['Amount'])) : '';
        $donation_to_edit_values['DonationDate'] = sanitize_input($_POST['DonationDate'] ?? date('Y-m-d'));
        $donation_to_edit_values['Status'] = sanitize_input($_POST['Status'] ?? 'pledged');
        $donation_to_edit_values['Notes'] = sanitize_input($_POST['Notes'] ?? '');
        // Preserve CollectedByUserID if already set and status is 'collected', otherwise determined by current action
        $existing_collector_id = null;
        if($donation_id_post_val){
            $stmt_get_collector = $conn->prepare("SELECT CollectedByUserID FROM Donations WHERE DonationID = ?");
            if($stmt_get_collector){ $stmt_get_collector->bind_param("i", $donation_id_post_val); $stmt_get_collector->execute();
                $existing_collector_id = $stmt_get_collector->get_result()->fetch_assoc()['CollectedByUserID'] ?? null;
                $stmt_get_collector->close();
            }
        }
        $donation_to_edit_values['CollectedByUserID'] = $existing_collector_id;


        if($donation_id_post_val) $donation_to_edit_values['DonationID'] = $donation_id_post_val;
        $edit_mode_don = ($donation_id_post_val !== null);

        if (empty($donation_to_edit_values['DonorName'])) $errors_don[] = "نام اهداکننده الزامی است (می‌توانید 'ناشناس' وارد کنید).";
        if ($donation_to_edit_values['Amount'] === '' || !is_numeric($donation_to_edit_values['Amount']) || $donation_to_edit_values['Amount'] <= 0) $errors_don[] = "مبلغ کمک باید یک عدد مثبت باشد.";
        if (empty($donation_to_edit_values['DonationDate']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $donation_to_edit_values['DonationDate'])) $errors_don[] = "تاریخ کمک نامعتبر است (فرمت YYYY-MM-DD).";
        if (!array_key_exists($donation_to_edit_values['Status'], $donation_status_options_disp)) $errors_don[] = "وضعیت کمک نامعتبر است.";
        if ($donation_to_edit_values['ProjectID'] !== null && !isset($available_projects_don_page[$donation_to_edit_values['ProjectID']])) $errors_don[] = "پروژه انتخاب شده نامعتبر است.";

        if (empty($errors_don)) {
            $collected_by_user_id_for_db_don = $donation_to_edit_values['CollectedByUserID'];
            if ($donation_to_edit_values['Status'] == 'collected' && empty($collected_by_user_id_for_db_don)) {
                $collected_by_user_id_for_db_don = get_current_user_id();
            } elseif ($donation_to_edit_values['Status'] != 'collected') {
                $collected_by_user_id_for_db_don = null;
            }

            if ($donation_id_post_val) {
                $stmt_don_db_action_val = $conn->prepare("UPDATE Donations SET DonorName=?, DonorPhoneNumber=?, ProjectID=?, Amount=?, DonationDate=?, Status=?, Notes=?, CollectedByUserID=? WHERE DonationID=?");
                if($stmt_don_db_action_val) { $stmt_don_db_action_val->bind_param("ssidisssi", $donation_to_edit_values['DonorName'], $donation_to_edit_values['DonorPhoneNumber'], $donation_to_edit_values['ProjectID'], $donation_to_edit_values['Amount'], $donation_to_edit_values['DonationDate'], $donation_to_edit_values['Status'], $donation_to_edit_values['Notes'], $collected_by_user_id_for_db_don, $donation_id_post_val);
                    if($stmt_don_db_action_val->execute()) $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'اطلاعات کمک ویرایش شد.']; else $errors_don[] = "خطا ویرایش: " . $stmt_don_db_action_val->error; $stmt_don_db_action_val->close();
                } else $errors_don[] = "خطا آماده سازی ویرایش: " . $conn->error;
            } else {
                $stmt_don_db_action_val = $conn->prepare("INSERT INTO Donations (DonorName, DonorPhoneNumber, ProjectID, Amount, DonationDate, Status, Notes, CollectedByUserID) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                 if($stmt_don_db_action_val) { $stmt_don_db_action_val->bind_param("ssidisss", $donation_to_edit_values['DonorName'], $donation_to_edit_values['DonorPhoneNumber'], $donation_to_edit_values['ProjectID'], $donation_to_edit_values['Amount'], $donation_to_edit_values['DonationDate'], $donation_to_edit_values['Status'], $donation_to_edit_values['Notes'], $collected_by_user_id_for_db_don);
                    if($stmt_don_db_action_val->execute()) $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'کمک مالی جدید ثبت شد.']; else $errors_don[] = "خطا ایجاد: " . $stmt_don_db_action_val->error; $stmt_don_db_action_val->close();
                } else $errors_don[] = "خطا آماده سازی ایجاد: " . $conn->error;
            }
            if(empty($errors_don)) { regenerate_csrf_token('finance_donations_action'); header("Location: donations.php"); exit; }
        }
    }
    $csrf_token_donations = regenerate_csrf_token('finance_donations_action');
}

if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id']) && $_SERVER["REQUEST_METHOD"] != "POST") {
    $edit_id_don_get_val = (int)$_GET['edit_id'];
    $stmt_edit_don_get_val = $conn->prepare("SELECT * FROM Donations WHERE DonationID = ?");
    if ($stmt_edit_don_get_val) { $stmt_edit_don_get_val->bind_param("i", $edit_id_don_get_val); $stmt_edit_don_get_val->execute(); $result_edit_don_get_val = $stmt_edit_don_get_val->get_result();
        if ($data_don_get_val = $result_edit_don_get_val->fetch_assoc()) {
            $donation_to_edit_values = $data_don_get_val;
            if($donation_to_edit_values['DonationDate']) $donation_to_edit_values['DonationDate'] = (new DateTime($donation_to_edit_values['DonationDate']))->format('Y-m-d');
            $edit_mode_don = true;
        } else $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "کمک مالی یافت نشد."]; $stmt_edit_don_get_val->close();
    } else $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "خطا بارگذاری: " . $conn->error];
}
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    if (!isset($_GET['csrf_token']) || !verify_csrf_token($_GET['csrf_token'], 'finance_donations_action')) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطای CSRF!'];
    } else {
        $delete_id_don_get_val = (int)$_GET['delete_id'];
        $stmt_delete_don_page_val = $conn->prepare("DELETE FROM Donations WHERE DonationID = ?");
        if ($stmt_delete_don_page_val) { $stmt_delete_don_page_val->bind_param("i", $delete_id_don_get_val);
            if ($stmt_delete_don_page_val->execute() && $stmt_delete_don_page_val->affected_rows > 0) $_SESSION['flash_message'] = ['type' => 'success', 'text' => "رکورد حذف شد."];
            else $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "خطا حذف: " . $stmt_delete_don_page_val->error]; $stmt_delete_don_page_val->close();
        } else $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "خطا آماده سازی حذف: " . $conn->error];
    }
    $csrf_token_donations = regenerate_csrf_token('finance_donations_action');
    header("Location: donations.php"); exit;
}

$donations_list_q_main_page = $conn->query("SELECT d.*, pp.ProjectName, CONCAT(u_coll.FirstName, ' ', u_coll.LastName) as CollectorName FROM Donations d LEFT JOIN ParvareshiProjects pp ON d.ProjectID = pp.ProjectID LEFT JOIN Users u_coll ON d.CollectedByUserID = u_coll.UserID ORDER BY d.DonationDate DESC, d.DonationID DESC LIMIT 50");
?>
<div class="page-header"><h1>مدیریت صله و کمک‌های مالی</h1><div class="page-header-actions"><a href="warehouse.php" class="btn btn-secondary">انبار</a> <a href="booklets.php" class="btn btn-info">جزوات</a></div></div>

<?php if (isset($_SESSION['flash_message'])) { $flash_don_page = $_SESSION['flash_message']; echo "<div class='alert alert-{$flash_don_page['type']} alert-dismissible fade show'>{$flash_don_page['text']}<button type='button' class='close' data-dismiss='alert'>&times;</button></div>"; unset($_SESSION['flash_message']); echo "<script>/*Dismiss JS*/</script>";} ?>
<?php if (!empty($errors_don)): ?> <div class="alert alert-danger"><ul><?php foreach ($errors_don as $err_don_item_page): ?><li><?php echo htmlspecialchars($err_don_item_page); ?></li><?php endforeach; ?></ul></div> <?php endif; ?>

<div class="row"><div class="col-lg-5 mb-4"><div class="card shadow-sm">
    <div class="card-header"><span class="card-title-text"><?php echo $edit_mode_don ? 'ویرایش کمک مالی: '.htmlspecialchars($donation_to_edit_values['DonorName']) : 'ثبت کمک مالی جدید'; ?></span></div><div class="card-body">
    <form action="donations.php<?php if($edit_mode_don && $donation_to_edit_values['DonationID']) echo '?edit_id='.$donation_to_edit_values['DonationID']; ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_donations; ?>">
        <?php if ($edit_mode_don && $donation_to_edit_values['DonationID']): ?><input type="hidden" name="donation_id" value="<?php echo $donation_to_edit_values['DonationID']; ?>"><?php endif; ?>
        <div class="form-group"><label for="DonorName_don_form_id">نام اهداکننده <span class="text-danger">*</span></label><input type="text" class="form-control" id="DonorName_don_form_id" name="DonorName" value="<?php echo htmlspecialchars($donation_to_edit_values['DonorName']); ?>" required></div>
        <div class="form-group"><label for="DonorPhoneNumber_don_form_id">شماره تماس</label><input type="text" class="form-control" id="DonorPhoneNumber_don_form_id" name="DonorPhoneNumber" value="<?php echo htmlspecialchars($donation_to_edit_values['DonorPhoneNumber']); ?>" placeholder="09..."></div>
        <div class="form-row"><div class="form-group col-md-7"><label for="Amount_don_form_id">مبلغ (تومان) <span class="text-danger">*</span></label><input type="text" class="form-control" id="Amount_don_form_id" name="Amount" value="<?php echo htmlspecialchars(number_format(floatval($donation_to_edit_values['Amount'] ?? 0), 0, '.', '')); ?>" required></div><div class="form-group col-md-5"><label for="DonationDate_don_form_id">تاریخ <span class="text-danger">*</span></label><input type="text" class="form-control persian-date-picker" id="DonationDate_don_form_id" name="DonationDate" value="<?php echo htmlspecialchars($donation_to_edit_values['DonationDate']); ?>" required></div></div>
        <div class="form-row"><div class="form-group col-md-7"><label for="ProjectID_don_form_id">برای پروژه</label><select name="ProjectID" id="ProjectID_don_form_id" class="form-control custom-select"><option value="">-- عمومی --</option><?php foreach($available_projects_don_page as $pid_f_page => $pname_f_page):?><option value="<?php echo $pid_f_page;?>" <?php if($donation_to_edit_values['ProjectID']==$pid_f_page) echo 'selected';?>><?php echo htmlspecialchars($pname_f_page);?></option><?php endforeach;?></select></div><div class="form-group col-md-5"><label for="Status_don_form_id">وضعیت <span class="text-danger">*</span></label><select name="Status" id="Status_don_form_id" class="form-control custom-select" required><?php foreach($donation_status_options_disp as $dsk_f_page => $dsv_f_page):?><option value="<?php echo $dsk_f_page;?>" <?php if($donation_to_edit_values['Status']==$dsk_f_page) echo 'selected';?>><?php echo $dsv_f_page;?></option><?php endforeach;?></select></div></div>
        <div class="form-group"><label for="Notes_don_form_id">یادداشت</label><textarea class="form-control" id="Notes_don_form_id" name="Notes" rows="2"><?php echo htmlspecialchars($donation_to_edit_values['Notes']); ?></textarea></div>
        <div class="form-actions"><button type="submit" name="submit_donation" class="btn btn-primary"><?php echo $edit_mode_don ? 'ذخیره' : 'ثبت'; ?></button><?php if ($edit_mode_don): ?><a href="donations.php" class="btn btn-outline-secondary">لغو</a><?php endif; ?></div>
    </form></div></div></div>
    <div class="col-lg-7"><div class="card shadow-sm"><div class="card-header"><span class="card-title-text">لیست کمک‌ها (۵۰ اخیر)</span></div><div class="card-body">
    <?php if($donations_list_q_main_page && $donations_list_q_main_page->num_rows > 0): ?><div class="table-responsive"><table class="table table-sm table-striped table-hover">
        <thead><tr><th>#</th><th>اهداکننده</th><th>مبلغ (ت)</th><th>پروژه</th><th>تاریخ</th><th>وضعیت</th><th>جمع‌آورنده</th><th>عملیات</th></tr></thead><tbody>
        <?php $don_row_idx_disp = 1; while($don_item_disp = $donations_list_q_main_page->fetch_assoc()): ?><tr>
            <td><?php echo $don_row_idx_disp++;?></td><td><strong><?php echo htmlspecialchars($don_item_disp['DonorName']);?></strong><small class="d-block text-muted"><?php echo htmlspecialchars($don_item_disp['DonorPhoneNumber'] ?? '-');?></small></td>
            <td class="text-success font-weight-bold"><?php echo number_format($don_item_disp['Amount'],0);?></td><td><small><?php echo htmlspecialchars($don_item_disp['ProjectName'] ?? 'عمومی');?></small></td>
            <td><small><?php echo to_jalali($don_item_disp['DonationDate'],'yy/MM/dd');?></small></td><td><span class="badge badge-<?php echo $donation_status_badge_disp[$don_item_disp['Status']] ?? 'light';?> p-1"><?php echo $donation_status_options_disp[$don_item_disp['Status']] ?? $don_item_disp['Status'];?></span></td>
            <td><small><?php echo htmlspecialchars($don_item_disp['CollectorName'] ?? '-');?></small></td>
            <td class="actions-cell">
                <a href="donations.php?edit_id=<?php echo $don_item_disp['DonationID'];?>" class="btn btn-xs btn-warning" title="ویرایش"><svg class="icon" width="12" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a>
                <a href="donations.php?delete_id=<?php echo $don_item_disp['DonationID'];?>&csrf_token=<?php echo $csrf_token_donations; ?>" class="btn btn-xs btn-danger" title="حذف" onclick="return confirm('آیا از حذف این رکورد مطمئن هستید؟');"><svg class="icon" width="12" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></a>
            </td></tr><?php endwhile; ?></tbody></table></div>
    <?php else: ?><p class="text-muted text-center">هنوز کمک مالی ثبت نشده.</p><?php endif; if($donations_list_q_main_page) $donations_list_q_main_page->close();?>
    </div></div></div></div>
<link rel="stylesheet" href="https://unpkg.com/persian-datepicker@latest/dist/css/persian-datepicker.min.css"/>
<script src="https://unpkg.com/persian-datepicker@latest/dist/js/persian-datepicker.min.js"></script>
<script> document.addEventListener('DOMContentLoaded', function() { document.querySelectorAll(".persian-date-picker").forEach(function(el){ new persianDatepicker(el, { format: 'YYYY-MM-DD', autoClose: true, observer: true, calendar:{ persian: { locale: 'fa' } } });}); document.querySelectorAll('.alert .close').forEach(function(button){button.addEventListener('click', function(event){event.target.closest('.alert').style.display = 'none';});});}); </script>
<style>.badge.p-1{padding:0.25em 0.4em !important; font-size:0.8em !important;} .btn-xs{padding: .1rem .3rem; font-size: .75rem;}</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
