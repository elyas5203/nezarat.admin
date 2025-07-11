<?php
// admin/parvareshi/events_general.php
require_once __DIR__ . '/../includes/header.php';

$csrf_token_pv_events = generate_csrf_token('parvareshi_events_action');
$errors_pv_evt = [];
$edit_mode_pv_evt = false;
$event_pv_to_edit_values_default = [
    'ProjectID' => null, 'ProjectName' => '', 'ProjectType' => 'public_event',
    'Description' => '', 'Proposal' => '', 'StartDate' => '', 'EndDate' => '',
    'Budget' => '', 'Location' => '', 'Status' => 'planning', 'LeadUserID' => null
];
$event_pv_to_edit_values = $event_pv_to_edit_values_default;

$project_type_options_pv = ['public_event' => 'مراسم عمومی (جشن، عزاداری)', 'camp' => 'اردو', 'other' => 'سایر پروژه‌های پرورشی'];
$project_status_options_pv = ['planning' => 'در حال برنامه‌ریزی', 'approved' => 'تصویب شده', 'ongoing' => 'در حال اجرا', 'completed' => 'تکمیل شده', 'archived' => 'بایگانی شده', 'cancelled' => 'لغو شده'];
$status_badge_pv_evt = ['planning'=>'info','approved'=>'primary','ongoing'=>'warning','completed'=>'success','archived'=>'secondary','cancelled'=>'danger'];

$lead_users_q = $conn->query("SELECT UserID, FirstName, LastName, Username FROM Users WHERE IsActive = TRUE ORDER BY LastName, FirstName");
$available_leads = [];
if($lead_users_q){ while($lu = $lead_users_q->fetch_assoc()) $available_leads[$lu['UserID']] = $lu['FirstName'].' '.$lu['LastName'] . ' (@'.$lu['Username'].')'; $lead_users_q->close(); }

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_pv_event'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'parvareshi_events_action')) {
        $errors_pv_evt[] = 'خطای CSRF!';
    } else {
        $project_id_post = isset($_POST['project_id']) && is_numeric($_POST['project_id']) ? (int)$_POST['project_id'] : null;

        $event_pv_to_edit_values['ProjectName'] = sanitize_input($_POST['ProjectName'] ?? '');
        $event_pv_to_edit_values['ProjectType'] = sanitize_input($_POST['ProjectType'] ?? 'public_event');
        $event_pv_to_edit_values['Description'] = sanitize_input($_POST['Description'] ?? '');
        $event_pv_to_edit_values['Proposal'] = sanitize_input($_POST['Proposal'] ?? ''); // Consider allowing some HTML if using rich text editor
        $event_pv_to_edit_values['StartDate'] = !empty($_POST['StartDate']) ? sanitize_input($_POST['StartDate']) : null;
        $event_pv_to_edit_values['EndDate'] = !empty($_POST['EndDate']) ? sanitize_input($_POST['EndDate']) : null;
        $event_pv_to_edit_values['Budget'] = !empty($_POST['Budget']) ? floatval(preg_replace('/[^0-9.]/', '', $_POST['Budget'])) : null;
        $event_pv_to_edit_values['Location'] = sanitize_input($_POST['Location'] ?? '');
        $event_pv_to_edit_values['Status'] = sanitize_input($_POST['Status'] ?? 'planning');
        $event_pv_to_edit_values['LeadUserID'] = !empty($_POST['LeadUserID']) ? (int)$_POST['LeadUserID'] : null;
        if($project_id_post) $event_pv_to_edit_values['ProjectID'] = $project_id_post;
        $edit_mode_pv_evt = ($project_id_post !== null);

        if (empty($event_pv_to_edit_values['ProjectName'])) $errors_pv_evt[] = "نام پروژه الزامی است.";
        if (!array_key_exists($event_pv_to_edit_values['ProjectType'], $project_type_options_pv)) $errors_pv_evt[] = "نوع پروژه نامعتبر.";
        if (!array_key_exists($event_pv_to_edit_values['Status'], $project_status_options_pv)) $errors_pv_evt[] = "وضعیت نامعتبر.";
        if($event_pv_to_edit_values['StartDate'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_pv_to_edit_values['StartDate'])) $errors_pv_evt[] = "فرمت تاریخ شروع (YYYY-MM-DD).";
        if($event_pv_to_edit_values['EndDate'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_pv_to_edit_values['EndDate'])) $errors_pv_evt[] = "فرمت تاریخ پایان (YYYY-MM-DD).";
        if($event_pv_to_edit_values['EndDate'] && $event_pv_to_edit_values['StartDate'] && $event_pv_to_edit_values['EndDate'] < $event_pv_to_edit_values['StartDate']) $errors_pv_evt[] = "تاریخ پایان نمی‌تواند قبل از تاریخ شروع باشد.";
        if ($event_pv_to_edit_values['LeadUserID'] !== null && !isset($available_leads[$event_pv_to_edit_values['LeadUserID']])) $errors_pv_evt[] = "مسئول پروژه نامعتبر.";


        if (empty($errors_pv_evt)) {
            if ($project_id_post) {
                $stmt_pv_db = $conn->prepare("UPDATE ParvareshiProjects SET ProjectName=?, ProjectType=?, Description=?, Proposal=?, StartDate=?, EndDate=?, Budget=?, Location=?, Status=?, LeadUserID=? WHERE ProjectID=?");
                if($stmt_pv_db) { $stmt_pv_db->bind_param("ssssssdssii", $event_pv_to_edit_values['ProjectName'], $event_pv_to_edit_values['ProjectType'], $event_pv_to_edit_values['Description'], $event_pv_to_edit_values['Proposal'], $event_pv_to_edit_values['StartDate'], $event_pv_to_edit_values['EndDate'], $event_pv_to_edit_values['Budget'], $event_pv_to_edit_values['Location'], $event_pv_to_edit_values['Status'], $event_pv_to_edit_values['LeadUserID'], $project_id_post);
                    if($stmt_pv_db->execute()) $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'پروژه ویرایش شد.']; else $errors_pv_evt[] = "خطا ویرایش: ".$stmt_pv_db->error; $stmt_pv_db->close();
                } else $errors_pv_evt[] = "خطا آماده سازی ویرایش: ".$conn->error;
            } else {
                $stmt_pv_db = $conn->prepare("INSERT INTO ParvareshiProjects (ProjectName, ProjectType, Description, Proposal, StartDate, EndDate, Budget, Location, Status, LeadUserID) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if($stmt_pv_db) { $stmt_pv_db->bind_param("ssssssdssi", $event_pv_to_edit_values['ProjectName'], $event_pv_to_edit_values['ProjectType'], $event_pv_to_edit_values['Description'], $event_pv_to_edit_values['Proposal'], $event_pv_to_edit_values['StartDate'], $event_pv_to_edit_values['EndDate'], $event_pv_to_edit_values['Budget'], $event_pv_to_edit_values['Location'], $event_pv_to_edit_values['Status'], $event_pv_to_edit_values['LeadUserID']);
                    if($stmt_pv_db->execute()) $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'پروژه ایجاد شد.']; else $errors_pv_evt[] = "خطا ایجاد: ".$stmt_pv_db->error; $stmt_pv_db->close();
                } else $errors_pv_evt[] = "خطا آماده سازی ایجاد: ".$conn->error;
            }
            if(empty($errors_pv_evt)) { regenerate_csrf_token('parvareshi_events_action'); header("Location: events_general.php"); exit; }
        }
    }
    $csrf_token_pv_events = regenerate_csrf_token('parvareshi_events_action');
}

if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id']) && $_SERVER["REQUEST_METHOD"] != "POST") {
    $edit_id_pv_get = (int)$_GET['edit_id'];
    $stmt_edit_pv_get = $conn->prepare("SELECT * FROM ParvareshiProjects WHERE ProjectID = ?");
    if ($stmt_edit_pv_get) { $stmt_edit_pv_get->bind_param("i", $edit_id_pv_get); $stmt_edit_pv_get->execute(); $result_edit_pv_get = $stmt_edit_pv_get->get_result();
        if ($data_pv_get = $result_edit_pv_get->fetch_assoc()) {
            $event_pv_to_edit_values = $data_pv_get;
            if($event_pv_to_edit_values['StartDate']) $event_pv_to_edit_values['StartDate'] = (new DateTime($event_pv_to_edit_values['StartDate']))->format('Y-m-d');
            if($event_pv_to_edit_values['EndDate']) $event_pv_to_edit_values['EndDate'] = (new DateTime($event_pv_to_edit_values['EndDate']))->format('Y-m-d');
            $edit_mode_pv_evt = true;
        } else $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "پروژه یافت نشد."]; $stmt_edit_pv_get->close();
    } else $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "خطا بارگذاری: " . $conn->error];
}
// Delete logic (TODO: Add CSRF, dependency checks e.g. on Donations table if ProjectID is linked)
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) { /* ... */ }


$pv_events_list_q_main = $conn->query("SELECT pp.*, CONCAT(u.FirstName, ' ', u.LastName) as LeadUserName FROM ParvareshiProjects pp LEFT JOIN Users u ON pp.LeadUserID = u.UserID ORDER BY pp.StartDate DESC, pp.ProjectName ASC LIMIT 50");
?>
<div class="page-header"><h1>مدیریت مناسبت‌های عمومی و اردوها</h1>
    <div class="page-header-actions"><a href="class_services.php" class="btn btn-secondary">خدمت‌گزاری کلاس‌ها</a> <a href="rental_items.php" class="btn btn-info">کرایه‌چی</a></div></div>

<?php if (isset($_SESSION['flash_message'])) { /* ... Flash ... */ } ?>
<?php if (!empty($errors_pv_evt)): ?> <div class="alert alert-danger"><ul><?php foreach ($errors_pv_evt as $err_pv_item): ?><li><?php echo htmlspecialchars($err_pv_item); ?></li><?php endforeach; ?></ul></div> <?php endif; ?>

<div class="row"><div class="col-lg-5 mb-4"><div class="card shadow-sm">
    <div class="card-header"><span class="card-title-text"><?php echo $edit_mode_pv_evt ? 'ویرایش: ' . htmlspecialchars($event_pv_to_edit_values['ProjectName']) : 'افزودن پروژه/رویداد جدید'; ?></span></div>
    <div class="card-body">
    <form action="events_general.php<?php if($edit_mode_pv_evt && $event_pv_to_edit_values['ProjectID']) echo '?edit_id='.$event_pv_to_edit_values['ProjectID']; ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_pv_events; ?>">
        <?php if ($edit_mode_pv_evt && $event_pv_to_edit_values['ProjectID']): ?><input type="hidden" name="project_id" value="<?php echo $event_pv_to_edit_values['ProjectID']; ?>"><?php endif; ?>
        <div class="form-group"><label for="ProjectName_pv_form">نام پروژه/رویداد <span class="text-danger">*</span></label><input type="text" class="form-control" id="ProjectName_pv_form" name="ProjectName" value="<?php echo htmlspecialchars($event_pv_to_edit_values['ProjectName']); ?>" required></div>
        <div class="form-row">
            <div class="form-group col-md-6"><label for="ProjectType_pv_form">نوع <span class="text-danger">*</span></label><select name="ProjectType" id="ProjectType_pv_form" class="form-control custom-select" required><?php foreach($project_type_options_pv as $ptk_f => $ptv_f):?><option value="<?php echo $ptk_f;?>" <?php if($event_pv_to_edit_values['ProjectType']==$ptk_f) echo 'selected';?>><?php echo $ptv_f;?></option><?php endforeach;?></select></div>
            <div class="form-group col-md-6"><label for="Status_pv_form">وضعیت <span class="text-danger">*</span></label><select name="Status" id="Status_pv_form" class="form-control custom-select" required><?php foreach($project_status_options_pv as $psk_f => $psv_f):?><option value="<?php echo $psk_f;?>" <?php if($event_pv_to_edit_values['Status']==$psk_f) echo 'selected';?>><?php echo $psv_f;?></option><?php endforeach;?></select></div>
        </div>
        <div class="form-row">
            <div class="form-group col-md-6"><label for="StartDate_pv_form">تاریخ شروع</label><input type="text" class="form-control persian-date-picker" id="StartDate_pv_form" name="StartDate" value="<?php echo htmlspecialchars($event_pv_to_edit_values['StartDate']); ?>" placeholder="YYYY-MM-DD"></div>
            <div class="form-group col-md-6"><label for="EndDate_pv_form">تاریخ پایان</label><input type="text" class="form-control persian-date-picker" id="EndDate_pv_form" name="EndDate" value="<?php echo htmlspecialchars($event_pv_to_edit_values['EndDate']); ?>" placeholder="YYYY-MM-DD"></div>
        </div>
        <div class="form-group"><label for="Location_pv_form">مکان</label><input type="text" class="form-control" id="Location_pv_form" name="Location" value="<?php echo htmlspecialchars($event_pv_to_edit_values['Location']); ?>"></div>
        <div class="form-row">
            <div class="form-group col-md-6"><label for="Budget_pv_form">بودجه (تومان)</label><input type="text" class="form-control" id="Budget_pv_form" name="Budget" value="<?php echo htmlspecialchars(number_format($event_pv_to_edit_values['Budget'] ?? 0, 0, '.', '')); ?>" placeholder="مثال: 5000000"></div>
            <div class="form-group col-md-6"><label for="LeadUserID_pv_form">مسئول پروژه</label><select name="LeadUserID" id="LeadUserID_pv_form" class="form-control custom-select"><option value="">-- انتخاب --</option><?php foreach($available_leads as $lid_f => $lname_f):?><option value="<?php echo $lid_f;?>" <?php if($event_pv_to_edit_values['LeadUserID']==$lid_f) echo 'selected';?>><?php echo htmlspecialchars($lname_f);?></option><?php endforeach;?></select></div>
        </div>
        <div class="form-group"><label for="Description_pv_form">توضیحات پروژه</label><textarea class="form-control" id="Description_pv_form" name="Description" rows="3"><?php echo htmlspecialchars($event_pv_to_edit_values['Description']); ?></textarea></div>
        <div class="form-group"><label for="Proposal_pv_form">پروپوزال/گزارش کامل</label><textarea class="form-control" id="Proposal_pv_form" name="Proposal" rows="5"><?php echo htmlspecialchars($event_pv_to_edit_values['Proposal']); ?></textarea></div>
        <div class="form-actions"><button type="submit" name="submit_pv_event" class="btn btn-primary"><?php echo $edit_mode_pv_evt ? 'ذخیره' : 'ایجاد'; ?></button><?php if ($edit_mode_pv_evt): ?><a href="events_general.php" class="btn btn-outline-secondary">لغو</a><?php endif; ?></div>
    </form></div></div></div>
    <div class="col-lg-7"><div class="card shadow-sm"><div class="card-header"><span class="card-title-text">لیست پروژه‌های پرورشی (۵۰ اخیر)</span></div><div class="card-body">
    <?php if($pv_events_list_q_main && $pv_events_list_q_main->num_rows > 0): ?><div class="table-responsive"><table class="table table-sm table-striped table-hover">
        <thead><tr><th>#</th><th>پروژه</th><th>نوع</th><th>تاریخ</th><th>وضعیت</th><th>مسئول</th><th>عملیات</th></tr></thead><tbody>
        <?php $pv_row_idx = 1; while($pv_e_item = $pv_events_list_q_main->fetch_assoc()): ?><tr>
            <td><?php echo $pv_row_idx++;?></td><td><strong><?php echo htmlspecialchars($pv_e_item['ProjectName']);?></strong><small class="d-block text-muted"><?php echo htmlspecialchars(mb_substr($pv_e_item['Description'] ?? '',0,50)).(mb_strlen($pv_e_item['Description'] ?? '') > 50 ? '...' : '');?></small></td><td><?php echo $project_type_options_pv[$pv_e_item['ProjectType']] ?? '-';?></td><td><small><?php echo $pv_e_item['StartDate']?to_jalali($pv_e_item['StartDate'],'yy/MM/dd'):'-';?> تا <?php echo $pv_e_item['EndDate']?to_jalali($pv_e_item['EndDate'],'yy/MM/dd'):'-';?></small></td>
            <td><span class="badge badge-<?php echo $status_badge_pv_evt[$pv_e_item['Status']] ?? 'light';?> p-1"><?php echo $project_status_options_pv[$pv_e_item['Status']] ?? '-';?></span></td>
            <td><small><?php echo htmlspecialchars($pv_e_item['LeadUserName'] ?? '-');?></small></td>
            <td class="actions-cell"><a href="events_general.php?edit_id=<?php echo $pv_e_item['ProjectID'];?>" class="btn btn-xs btn-warning" title="ویرایش"><svg class="icon" width="12" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a> <!-- Delete --></td>
            </tr><?php endwhile; ?></tbody></table></div>
    <?php else: ?><p class="text-muted">پروژه‌ای ثبت نشده.</p><?php endif; if($pv_events_list_q_main) $pv_events_list_q_main->close();?>
    </div></div></div></div>
<link rel="stylesheet" href="https://unpkg.com/persian-datepicker@latest/dist/css/persian-datepicker.min.css"/>
<script src="https://unpkg.com/persian-datepicker@latest/dist/js/persian-datepicker.min.js"></script>
<script> document.addEventListener('DOMContentLoaded', function() { document.querySelectorAll(".persian-date-picker").forEach(function(el){ new persianDatepicker(el, { format: 'YYYY-MM-DD', autoClose: true, observer: true, calendar:{ persian: { locale: 'fa' } } });});});</script>
<style>.badge.p-1{padding:0.25em 0.4em !important; font-size:0.8em !important;}</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
