<?php
// admin/content/projects.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth

$csrf_token_content_projects = generate_csrf_token('content_projects_action');
$errors_cp = [];
$edit_mode_cp = false;
$project_cp_to_edit_values_default = [
    'ContentProjectID' => null, 'ProjectName' => '', 'Description' => '', 'Roadmap' => '',
    'CurrentStage' => '', 'Status' => 'ideation', 'LeadUserID' => null,
    'StartDate' => '', 'TargetEndDate' => ''
];
$project_cp_to_edit_values = $project_cp_to_edit_values_default;

$content_project_status_options = [
    'ideation' => 'ایده‌پردازی', 'planning' => 'برنامه‌ریزی', 'in_progress' => 'در حال انجام',
    'review' => 'در حال بازبینی', 'completed' => 'تکمیل شده', 'on_hold' => 'متوقف شده', 'cancelled' => 'لغو شده'
];
$status_badge_cp = ['ideation'=>'secondary', 'planning'=>'info', 'in_progress'=>'primary', 'review'=>'warning', 'completed'=>'success', 'on_hold'=>'light text-dark border', 'cancelled'=>'danger'];

$lead_users_cp_q = $conn->query("SELECT UserID, FirstName, LastName, Username FROM Users WHERE IsActive = TRUE ORDER BY LastName, FirstName");
$available_leads_cp = [];
if($lead_users_cp_q){ while($lu_cp = $lead_users_cp_q->fetch_assoc()) $available_leads_cp[$lu_cp['UserID']] = $lu_cp['FirstName'].' '.$lu_cp['LastName'] . ' (@'.$lu_cp['Username'].')'; $lead_users_cp_q->close(); }

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_content_project'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'content_projects_action')) {
        $errors_cp[] = 'خطای CSRF!';
    } else {
        $project_id_cp_post = isset($_POST['content_project_id']) && is_numeric($_POST['content_project_id']) ? (int)$_POST['content_project_id'] : null;

        $project_cp_to_edit_values['ProjectName'] = sanitize_input($_POST['ProjectName'] ?? '');
        $project_cp_to_edit_values['Description'] = sanitize_input($_POST['Description'] ?? '');
        $project_cp_to_edit_values['Roadmap'] = sanitize_input($_POST['Roadmap'] ?? '');
        $project_cp_to_edit_values['CurrentStage'] = sanitize_input($_POST['CurrentStage'] ?? '');
        $project_cp_to_edit_values['Status'] = sanitize_input($_POST['Status'] ?? 'ideation');
        $project_cp_to_edit_values['LeadUserID'] = !empty($_POST['LeadUserID']) ? (int)$_POST['LeadUserID'] : null;
        $project_cp_to_edit_values['StartDate'] = !empty($_POST['StartDate']) ? sanitize_input($_POST['StartDate']) : null;
        $project_cp_to_edit_values['TargetEndDate'] = !empty($_POST['TargetEndDate']) ? sanitize_input($_POST['TargetEndDate']) : null;
        if($project_id_cp_post) $project_cp_to_edit_values['ContentProjectID'] = $project_id_cp_post;
        $edit_mode_cp = ($project_id_cp_post !== null);

        if (empty($project_cp_to_edit_values['ProjectName'])) $errors_cp[] = "نام پروژه الزامی است.";
        if (!array_key_exists($project_cp_to_edit_values['Status'], $content_project_status_options)) $errors_cp[] = "وضعیت نامعتبر.";
        if($project_cp_to_edit_values['StartDate'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $project_cp_to_edit_values['StartDate'])) $errors_cp[] = "فرمت تاریخ شروع (YYYY-MM-DD).";
        if($project_cp_to_edit_values['TargetEndDate'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $project_cp_to_edit_values['TargetEndDate'])) $errors_cp[] = "فرمت تاریخ پایان (YYYY-MM-DD).";
        if($project_cp_to_edit_values['TargetEndDate'] && $project_cp_to_edit_values['StartDate'] && $project_cp_to_edit_values['TargetEndDate'] < $project_cp_to_edit_values['StartDate']) $errors_cp[] = "تاریخ پایان قبل از شروع.";
        if ($project_cp_to_edit_values['LeadUserID'] !== null && !isset($available_leads_cp[$project_cp_to_edit_values['LeadUserID']])) $errors_cp[] = "مسئول نامعتبر.";

        if (empty($errors_cp)) {
            if ($project_id_cp_post) {
                $stmt_cp_db = $conn->prepare("UPDATE ContentProjects SET ProjectName=?, Description=?, Roadmap=?, CurrentStage=?, Status=?, LeadUserID=?, StartDate=?, TargetEndDate=? WHERE ContentProjectID=?");
                if($stmt_cp_db) { $stmt_cp_db->bind_param("sssssissi", $project_cp_to_edit_values['ProjectName'], $project_cp_to_edit_values['Description'], $project_cp_to_edit_values['Roadmap'], $project_cp_to_edit_values['CurrentStage'], $project_cp_to_edit_values['Status'], $project_cp_to_edit_values['LeadUserID'], $project_cp_to_edit_values['StartDate'], $project_cp_to_edit_values['TargetEndDate'], $project_id_cp_post);
                    if($stmt_cp_db->execute()) $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'پروژه ویرایش شد.']; else $errors_cp[] = "خطا ویرایش: ".$stmt_cp_db->error; $stmt_cp_db->close();
                } else $errors_cp[] = "خطا آماده سازی ویرایش: ".$conn->error;
            } else {
                $stmt_cp_db = $conn->prepare("INSERT INTO ContentProjects (ProjectName, Description, Roadmap, CurrentStage, Status, LeadUserID, StartDate, TargetEndDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if($stmt_cp_db) { $stmt_cp_db->bind_param("sssssiss", $project_cp_to_edit_values['ProjectName'], $project_cp_to_edit_values['Description'], $project_cp_to_edit_values['Roadmap'], $project_cp_to_edit_values['CurrentStage'], $project_cp_to_edit_values['Status'], $project_cp_to_edit_values['LeadUserID'], $project_cp_to_edit_values['StartDate'], $project_cp_to_edit_values['TargetEndDate']);
                    if($stmt_cp_db->execute()) $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'پروژه ایجاد شد.']; else $errors_cp[] = "خطا ایجاد: ".$stmt_cp_db->error; $stmt_cp_db->close();
                } else $errors_cp[] = "خطا آماده سازی ایجاد: ".$conn->error;
            }
            if(empty($errors_cp)) { regenerate_csrf_token('content_projects_action'); header("Location: projects.php"); exit; }
        }
    }
    $csrf_token_content_projects = regenerate_csrf_token('content_projects_action');
}

if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id']) && $_SERVER["REQUEST_METHOD"] != "POST") {
    $edit_id_cp_get = (int)$_GET['edit_id'];
    $stmt_edit_cp_get = $conn->prepare("SELECT * FROM ContentProjects WHERE ContentProjectID = ?");
    if ($stmt_edit_cp_get) { $stmt_edit_cp_get->bind_param("i", $edit_id_cp_get); $stmt_edit_cp_get->execute(); $result_edit_cp_get = $stmt_edit_cp_get->get_result();
        if ($data_cp_get = $result_edit_cp_get->fetch_assoc()) {
            $project_cp_to_edit_values = $data_cp_get;
            if($project_cp_to_edit_values['StartDate']) $project_cp_to_edit_values['StartDate'] = (new DateTime($project_cp_to_edit_values['StartDate']))->format('Y-m-d');
            if($project_cp_to_edit_values['TargetEndDate']) $project_cp_to_edit_values['TargetEndDate'] = (new DateTime($project_cp_to_edit_values['TargetEndDate']))->format('Y-m-d');
            $edit_mode_cp = true;
        } else $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "پروژه یافت نشد."]; $stmt_edit_cp_get->close();
    } else $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "خطا بارگذاری: " . $conn->error];
}
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    if (!isset($_GET['csrf_token']) || !verify_csrf_token($_GET['csrf_token'], 'content_projects_action')) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطای CSRF!'];
    } else {
        $delete_id_cp_get = (int)$_GET['delete_id'];
        $conn->begin_transaction();
        try {
            $stmt_del_tasks = $conn->prepare("DELETE FROM ContentProjectTasks WHERE ContentProjectID = ?");
            if(!$stmt_del_tasks) throw new Exception("خطا آماده سازی حذف وظایف: ".$conn->error);
            $stmt_del_tasks->bind_param("i", $delete_id_cp_get);
            if(!$stmt_del_tasks->execute()) throw new Exception("خطا حذف وظایف: ".$stmt_del_tasks->error);
            $stmt_del_tasks->close();

            $stmt_del_cp_get = $conn->prepare("DELETE FROM ContentProjects WHERE ContentProjectID = ?");
            if(!$stmt_del_cp_get) throw new Exception("خطا آماده سازی حذف پروژه: ".$conn->error);
            $stmt_del_cp_get->bind_param("i", $delete_id_cp_get);
            if($stmt_del_cp_get->execute() && $stmt_del_cp_get->affected_rows > 0) {
                $conn->commit(); $_SESSION['flash_message'] = ['type' => 'success', 'text' => "پروژه و وظایف آن حذف شدند."];
            } else { throw new Exception("خطا حذف پروژه یا پروژه یافت نشد: ".$stmt_del_cp_get->error); }
            $stmt_del_cp_get->close();
        } catch (Exception $e_del_cp) { $conn->rollback(); $_SESSION['flash_message'] = ['type' => 'danger', 'text' => $e_del_cp->getMessage()];}
    }
    $csrf_token_content_projects = regenerate_csrf_token('content_projects_action');
    header("Location: projects.php"); exit;
}

$cp_list_q_main = $conn->query("SELECT cp.*, CONCAT(u.FirstName, ' ', u.LastName) as LeadUserName FROM ContentProjects cp LEFT JOIN Users u ON cp.LeadUserID = u.UserID ORDER BY cp.TargetEndDate DESC, cp.ProjectName ASC LIMIT 50");
?>
<div class="page-header"><h1>مدیریت پروژه‌های محتوایی</h1></div>

<?php if (isset($_SESSION['flash_message'])) { $flash_cp_idx = $_SESSION['flash_message']; echo "<div class='alert alert-{$flash_cp_idx['type']} alert-dismissible fade show'>{$flash_cp_idx['text']}<button type='button' class='close' data-dismiss='alert'>&times;</button></div>"; unset($_SESSION['flash_message']); echo "<script>/*Dismiss JS*/</script>"; } ?>
<?php if (!empty($errors_cp)): ?> <div class="alert alert-danger"><ul><?php foreach ($errors_cp as $err_cp_item_msg): ?><li><?php echo htmlspecialchars($err_cp_item_msg); ?></li><?php endforeach; ?></ul></div> <?php endif; ?>

<div class="row"><div class="col-lg-5 mb-4"><div class="card shadow-sm">
    <div class="card-header"><span class="card-title-text"><?php echo $edit_mode_cp ? 'ویرایش پروژه: ' . htmlspecialchars($project_cp_to_edit_values['ProjectName']) : 'افزودن پروژه جدید'; ?></span></div><div class="card-body">
    <form action="projects.php<?php if($edit_mode_cp && $project_cp_to_edit_values['ContentProjectID']) echo '?edit_id='.$project_cp_to_edit_values['ContentProjectID']; ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_content_projects; ?>">
        <?php if ($edit_mode_cp && $project_cp_to_edit_values['ContentProjectID']): ?><input type="hidden" name="content_project_id" value="<?php echo $project_cp_to_edit_values['ContentProjectID']; ?>"><?php endif; ?>
        <div class="form-group"><label for="ProjectName_cp_form">نام پروژه <span class="text-danger">*</span></label><input type="text" class="form-control" id="ProjectName_cp_form" name="ProjectName" value="<?php echo htmlspecialchars($project_cp_to_edit_values['ProjectName']); ?>" required></div>
        <div class="form-group"><label for="Description_cp_form">توضیحات</label><textarea class="form-control" id="Description_cp_form" name="Description" rows="2"><?php echo htmlspecialchars($project_cp_to_edit_values['Description']); ?></textarea></div>
        <div class="form-group"><label for="Roadmap_cp_form">رودمپ/مراحل</label><textarea class="form-control" id="Roadmap_cp_form" name="Roadmap" rows="3"><?php echo htmlspecialchars($project_cp_to_edit_values['Roadmap']); ?></textarea></div>
        <div class="form-group"><label for="CurrentStage_cp_form">مرحله کنونی</label><input type="text" class="form-control" id="CurrentStage_cp_form" name="CurrentStage" value="<?php echo htmlspecialchars($project_cp_to_edit_values['CurrentStage']); ?>"></div>
        <div class="form-row">
            <div class="form-group col-md-6"><label for="Status_cp_form">وضعیت <span class="text-danger">*</span></label><select name="Status" id="Status_cp_form" class="form-control custom-select" required><?php foreach($content_project_status_options as $stc_k_f => $stc_v_f):?><option value="<?php echo $stc_k_f;?>" <?php if($project_cp_to_edit_values['Status']==$stc_k_f) echo 'selected';?>><?php echo $stc_v_f;?></option><?php endforeach;?></select></div>
            <div class="form-group col-md-6"><label for="LeadUserID_cp_form">مسئول</label><select name="LeadUserID" id="LeadUserID_cp_form" class="form-control custom-select"><option value="">-- انتخاب --</option><?php foreach($available_leads_cp as $lid_cp_f_sel => $lname_cp_f_sel):?><option value="<?php echo $lid_cp_f_sel;?>" <?php if($project_cp_to_edit_values['LeadUserID']==$lid_cp_f_sel) echo 'selected';?>><?php echo htmlspecialchars($lname_cp_f_sel);?></option><?php endforeach;?></select></div>
        </div>
        <div class="form-row">
            <div class="form-group col-md-6"><label for="StartDate_cp_form">تاریخ شروع</label><input type="text" class="form-control persian-date-picker" id="StartDate_cp_form" name="StartDate" value="<?php echo htmlspecialchars($project_cp_to_edit_values['StartDate']); ?>" placeholder="YYYY-MM-DD"></div>
            <div class="form-group col-md-6"><label for="TargetEndDate_cp_form">ددلاین</label><input type="text" class="form-control persian-date-picker" id="TargetEndDate_cp_form" name="TargetEndDate" value="<?php echo htmlspecialchars($project_cp_to_edit_values['TargetEndDate']); ?>" placeholder="YYYY-MM-DD"></div>
        </div>
        <div class="form-actions"><button type="submit" name="submit_content_project" class="btn btn-primary"><?php echo $edit_mode_cp ? 'ذخیره' : 'ایجاد'; ?></button><?php if ($edit_mode_cp): ?><a href="projects.php" class="btn btn-outline-secondary">لغو</a><?php endif; ?></div>
    </form></div></div></div>
    <div class="col-lg-7"><div class="card shadow-sm"><div class="card-header"><span class="card-title-text">لیست پروژه‌ها (۵۰ اخیر)</span></div><div class="card-body">
    <?php if($cp_list_q_main && $cp_list_q_main->num_rows > 0): ?><div class="table-responsive"><table class="table table-sm table-striped table-hover">
        <thead><tr><th>#</th><th>پروژه</th><th>وضعیت</th><th>مسئول</th><th>ددلاین</th><th>عملیات</th></tr></thead><tbody>
        <?php $cp_row_idx = 1; while($cp_item_idx = $cp_list_q_main->fetch_assoc()): ?><tr>
            <td><?php echo $cp_row_idx++;?></td><td><a href="tasks.php?project_id=<?php echo $cp_item_idx['ContentProjectID'];?>"><strong><?php echo htmlspecialchars($cp_item_idx['ProjectName']);?></strong></a><small class="d-block text-muted"><?php echo htmlspecialchars(mb_substr($cp_item_idx['CurrentStage'] ?? '',0,70)).(mb_strlen($cp_item_idx['CurrentStage'] ?? '') > 70 ? '...' : '');?></small></td>
            <td><span class="badge badge-<?php echo $status_badge_cp[$cp_item_idx['Status']] ?? 'light';?> p-1"><?php echo $content_project_status_options[$cp_item_idx['Status']] ?? '-';?></span></td>
            <td><small><?php echo htmlspecialchars($cp_item_idx['LeadUserName'] ?? '-');?></small></td>
            <td><small><?php echo $cp_item_idx['TargetEndDate'] ? to_jalali($cp_item_idx['TargetEndDate'],'yy/MM/dd') : '-';?></small></td>
            <td class="actions-cell">
                <a href="projects.php?edit_id=<?php echo $cp_item_idx['ContentProjectID'];?>" class="btn btn-xs btn-warning" title="ویرایش پروژه"><svg class="icon" width="12" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a>
                <a href="tasks.php?project_id=<?php echo $cp_item_idx['ContentProjectID'];?>" class="btn btn-xs btn-info" title="مدیریت وظایف"><svg class="icon" width="12" viewBox="0 0 24 24"><path d="M15.5 2H8.6c-.4 0-.8.2-1.1.5-.3.3-.5.7-.5 1.1V21c0 .4.2.8.5 1.1.3.3.7.5 1.1.5h10.8c.4 0 .8-.2 1.1-.5.3-.3.5-.7.5-1.1V8.9L15.5 2zM15 2v5h5M8 16h8M8 12h8M8 8h4"/></svg></a>
                <a href="projects.php?delete_id=<?php echo $cp_item_idx['ContentProjectID'];?>&csrf_token=<?php echo $csrf_token_content_projects; ?>" class="btn btn-xs btn-danger" title="حذف پروژه" onclick="return confirm('با حذف پروژه، تمام وظایف مرتبط با آن نیز حذف خواهند شد. آیا مطمئن هستید؟');"><svg class="icon" width="12" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></a>
            </td></tr><?php endwhile; ?></tbody></table></div>
    <?php else: ?><p class="text-muted text-center">هنوز پروژه‌ای ثبت نشده.</p><?php endif; if($cp_list_q_main) $cp_list_q_main->close();?>
    </div></div></div></div>
<link rel="stylesheet" href="https://unpkg.com/persian-datepicker@latest/dist/css/persian-datepicker.min.css"/>
<script src="https://unpkg.com/persian-datepicker@latest/dist/js/persian-datepicker.min.js"></script>
<script>document.addEventListener('DOMContentLoaded', function() { document.querySelectorAll(".persian-date-picker").forEach(function(el){ new persianDatepicker(el, { format: 'YYYY-MM-DD', autoClose: true, observer: true, calendar:{ persian: { locale: 'fa' } } });}); document.querySelectorAll('.alert .close').forEach(function(button){button.addEventListener('click', function(event){event.target.closest('.alert').style.display = 'none';});});});</script>
<style>.badge.p-1{padding:0.25em 0.4em !important; font-size:0.8em !important;} .btn-xs{padding: .1rem .3rem; font-size: .75rem;}</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
