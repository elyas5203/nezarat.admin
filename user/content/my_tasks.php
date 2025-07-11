<?php
// user/content/my_tasks.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth, $user_base_url

$user_id_content_task_page = get_current_user_id();
if (!$user_id_content_task_page) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'لطفاً ابتدا وارد شوید.'];
    header("Location: " . ($user_base_url ?? '/my_site/user') . "/auth/login.php");
    exit;
}

$csrf_token_my_content_tasks_page = generate_csrf_token('user_content_tasks_action');
$errors_my_cpt_page = [];

// Handle Status Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_task_status_user'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'user_content_tasks_action')) {
        $errors_my_cpt_page[] = 'خطای CSRF!';
    } else {
        $task_id_to_update_user_page = isset($_POST['task_id_for_status']) ? (int)$_POST['task_id_for_status'] : null;
        $new_status_user_cpt_page = sanitize_input($_POST['new_task_status'] ?? '');
        // User can only set to 'todo', 'in_progress', or 'done'. 'on_hold' is admin-only.
        $valid_user_settable_statuses = ['todo' => 'انجام نشده', 'in_progress' => 'در حال انجام', 'done' => 'انجام شده'];

        if (empty($task_id_to_update_user_page)) $errors_my_cpt_page[] = "شناسه وظیفه نامعتبر.";
        if (!array_key_exists($new_status_user_cpt_page, $valid_user_settable_statuses)) $errors_my_cpt_page[] = "وضعیت جدید نامعتبر برای کاربر.";

        if (empty($errors_my_cpt_page)) {
            $stmt_check_assign_page = $conn->prepare("SELECT TaskID, Status AS CurrentDBStatus FROM ContentProjectTasks WHERE TaskID = ? AND AssignedToUserID = ?");
            if($stmt_check_assign_page){
                $stmt_check_assign_page->bind_param("ii", $task_id_to_update_user_page, $user_id_content_task_page);
                $stmt_check_assign_page->execute();
                $task_current_data_res = $stmt_check_assign_page->get_result();
                if($task_current_data = $task_current_data_res->fetch_assoc()){
                    // User cannot change status if it's 'on_hold' (set by admin)
                    if ($task_current_data['CurrentDBStatus'] === 'on_hold' && $new_status_user_cpt_page !== 'on_hold') {
                        $errors_my_cpt_page[] = "امکان تغییر وضعیت وظیفه متوقف شده توسط شما وجود ندارد.";
                    } else {
                        $stmt_update_status_user_page = $conn->prepare("UPDATE ContentProjectTasks SET Status = ? WHERE TaskID = ?");
                        if ($stmt_update_status_user_page) {
                            $stmt_update_status_user_page->bind_param("si", $new_status_user_cpt_page, $task_id_to_update_user_page);
                            if ($stmt_update_status_user_page->execute()) {
                                $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'وضعیت وظیفه بروزرسانی شد.'];
                                // Notify project lead if task is marked as 'done' by user
                                if($new_status_user_cpt_page === 'done' && $task_current_data['CurrentDBStatus'] !== 'done'){
                                    $stmt_get_project_lead = $conn->prepare("SELECT cp.LeadUserID, cp.ProjectName, cpt.TaskName FROM ContentProjectTasks cpt JOIN ContentProjects cp ON cpt.ContentProjectID = cp.ContentProjectID WHERE cpt.TaskID = ?");
                                    if($stmt_get_project_lead){
                                        $stmt_get_project_lead->bind_param("i", $task_id_to_update_user_page);
                                        $stmt_get_project_lead->execute();
                                        $project_info_for_notif = $stmt_get_project_lead->get_result()->fetch_assoc();
                                        if($project_info_for_notif && $project_info_for_notif['LeadUserID']){
                                            $notif_msg_lead = "وظیفه \"".htmlspecialchars($project_info_for_notif['TaskName'])."\" در پروژه \"".htmlspecialchars($project_info_for_notif['ProjectName'])."\" توسط ".htmlspecialchars($_SESSION['username'])." به عنوان انجام شده علامت‌گذاری شد.";
                                            $notif_link_lead = ($admin_base_url ?? '/my_site/admin')."/content/tasks.php?project_id=".$project_info_for_notif['ContentProjectID']."&highlight_task=".$task_id_to_update_user_page;
                                            create_notification($project_info_for_notif['LeadUserID'], $notif_msg_lead, $notif_link_lead, 'content_task_done', $task_id_to_update_user_page);
                                        }
                                        $stmt_get_project_lead->close();
                                    }
                                }
                            } else { $errors_my_cpt_page[] = "خطا بروزرسانی وضعیت: " . $stmt_update_status_user_page->error; }
                            $stmt_update_status_user_page->close();
                        } else { $errors_my_cpt_page[] = "خطا آماده سازی بروزرسانی: " . $conn->error; }
                    }
                } else { $errors_my_cpt_page[] = "شما اجازه تغییر این وظیفه را ندارید یا وظیفه یافت نشد."; }
                $stmt_check_assign_page->close();
            } else {$errors_my_cpt_page[] = "خطا بررسی تخصیص: ".$conn->error;}

            if(empty($errors_my_cpt_page)) { regenerate_csrf_token('user_content_tasks_action'); header("Location: my_tasks.php"); exit; }
        }
    }
    $csrf_token_my_content_tasks_page = regenerate_csrf_token('user_content_tasks_action');
}

$stmt_my_tasks_page = $conn->prepare("
    SELECT cpt.TaskID, cpt.TaskName, cpt.Description AS TaskDescription, cpt.DueDate, cpt.Status AS TaskStatus,
           cp.ContentProjectID, cp.ProjectName
    FROM ContentProjectTasks cpt
    JOIN ContentProjects cp ON cpt.ContentProjectID = cp.ContentProjectID
    WHERE cpt.AssignedToUserID = ?
    ORDER BY FIELD(cpt.Status, 'todo', 'in_progress', 'on_hold'), cpt.DueDate ASC NULLS LAST, cp.ProjectName ASC, cpt.TaskName ASC
");
$my_content_tasks_list = [];
if ($stmt_my_tasks_page) {
    $stmt_my_tasks_page->bind_param("i", $user_id_content_task_page);
    $stmt_my_tasks_page->execute();
    $result_my_tasks_page = $stmt_my_tasks_page->get_result();
    while ($task_row_page = $result_my_tasks_page->fetch_assoc()) {
        $my_content_tasks_list[] = $task_row_page;
    }
    $stmt_my_tasks_page->close();
} else {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا بارگذاری وظایف: ' . $conn->error];
}

$task_status_options_user_cpt_view_page = ['todo' => 'انجام نشده', 'in_progress' => 'در حال انجام', 'done' => 'انجام شده', 'on_hold' => 'متوقف شده'];
$task_status_badge_user_cpt_view_page = ['todo'=>'warning', 'in_progress'=>'info', 'done'=>'success', 'on_hold'=>'secondary'];
?>
<div class="page-header"><h1>وظایف محتوایی من</h1><p class="page-subtitle">لیست وظایفی که در پروژه‌های محتوایی به شما محول شده است.</p></div>

<?php if (isset($_SESSION['flash_message'])) { $flash_mycpt = $_SESSION['flash_message']; echo "<div class='alert alert-{$flash_mycpt['type']} alert-dismissible fade show'>{$flash_mycpt['text']}<button type='button' class='close' data-dismiss='alert'>&times;</button></div>"; unset($_SESSION['flash_message']); echo "<script>/*Dismiss JS*/</script>";} ?>
<?php if (!empty($errors_my_cpt_page)): ?> <div class="alert alert-danger"><ul><?php foreach ($errors_my_cpt_page as $err_mycpt_item): ?><li><?php echo htmlspecialchars($err_mycpt_item); ?></li><?php endforeach; ?></ul></div> <?php endif; ?>

<div class="card shadow-sm"><div class="card-header"><span class="card-title-text">لیست وظایف شما</span></div>
<div class="card-body">
    <?php if (!empty($my_content_tasks_list)): ?>
        <div class="table-responsive"><table class="table table-hover table-striped table-sm user-content-tasks-table">
            <thead><tr><th>#</th><th>پروژه</th><th>عنوان وظیفه</th><th>سررسید</th><th>وضعیت</th><th style="min-width: 200px;">تغییر وضعیت</th></tr></thead>
            <tbody><?php $task_row_num_user = 1; foreach ($my_content_tasks_list as $task_item_user_disp): ?>
                <tr class="status-task-<?php echo htmlspecialchars($task_item_user_disp['TaskStatus']); ?> <?php if($task_item_user_disp['TaskID'] == ($_GET['highlight_task'] ?? null)) echo 'table-info';?>">
                    <td><?php echo $task_row_num_user++; ?></td>
                    <td><a href="<?php echo $admin_base_url; ?>/content/tasks.php?project_id=<?php echo $task_item_user_disp['ContentProjectID']; ?>" target="_blank" class="text-muted small Tooltip-Top" data-tooltip="مشاهده همه وظایف این پروژه (در پنل ادمین)"><?php echo htmlspecialchars($task_item_user_disp['ProjectName']); ?></a></td>
                    <td><strong><?php echo htmlspecialchars($task_item_user_disp['TaskName']); ?></strong>
                        <?php if(!empty($task_item_user_disp['TaskDescription'])): ?><small class="d-block text-muted"><?php echo nl2br(htmlspecialchars(mb_substr($task_item_user_disp['TaskDescription'],0,100) . (mb_strlen($task_item_user_disp['TaskDescription']) > 100 ? '...' : ''))); ?></small><?php endif; ?></td>
                    <td class="<?php if($task_item_user_disp['DueDate'] && $task_item_user_disp['TaskStatus'] !== 'done' && new DateTime($task_item_user_disp['DueDate']) < new DateTime('today')) echo 'text-danger font-weight-bold'; ?>"><?php echo $task_item_user_disp['DueDate'] ? to_jalali($task_item_user_disp['DueDate'], 'yyyy/MM/dd') : '-'; ?></td>
                    <td><span class="badge badge-<?php echo $task_status_badge_user_cpt_view_page[$task_item_user_disp['TaskStatus']] ?? 'light'; ?> p-2"><?php echo $task_status_options_user_cpt_view_page[$task_item_user_disp['TaskStatus']] ?? $task_item_user_disp['TaskStatus']; ?></span></td>
                    <td> <?php if ($task_item_user_disp['TaskStatus'] !== 'on_hold'): ?>
                        <form action="my_tasks.php" method="POST" class="form-inline-task-status">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_my_content_tasks_page; ?>">
                            <input type="hidden" name="task_id_for_status" value="<?php echo $task_item_user_disp['TaskID']; ?>">
                            <select name="new_task_status" class="form-control form-control-sm custom-select mr-2" style="min-width: 130px;" <?php if($task_item_user_disp['TaskStatus'] === 'done') echo 'disabled';?>>
                                <?php foreach($task_status_options_user_cpt_view_page as $status_key_user => $status_val_user):
                                    if ($status_key_user === 'on_hold') continue; // User cannot set to on_hold
                                    if ($task_item_user_disp['TaskStatus'] === 'done' && $status_key_user !== 'done') continue; // If done, only "done" is selectable to prevent accidental change by user
                                ?>
                                <option value="<?php echo $status_key_user; ?>" <?php if($task_item_user_disp['TaskStatus'] == $status_key_user) echo 'selected';?>><?php echo $status_val_user; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if($task_item_user_disp['TaskStatus'] !== 'done'): ?>
                            <button type="submit" name="update_task_status_user" class="btn btn-xs btn-success">ثبت</button>
                            <?php endif; ?>
                        </form>
                        <?php else: ?> <span class="text-muted small">متوقف توسط مدیر</span> <?php endif; ?>
                    </td></tr><?php endforeach; ?></tbody></table></div>
    <?php else: ?><div class="alert alert-info mb-0">وظیفه محتوایی به شما محول نشده.</div><?php endif; ?></div></div>
<style> /* Styles from previous admin task list, adapted */
    .user-content-tasks-table td, .user-content-tasks-table th { vertical-align: middle; font-size: 0.9rem;}
    .form-inline-task-status { display: flex; align-items: center; gap: 5px;}
    .form-inline-task-status .form-control-sm { height: calc(1.5em + .5rem + 2px); padding: .2rem .4rem; font-size: .8rem;}
    .form-inline-task-status .btn-xs { padding: .2rem .4rem; font-size: .75rem; }
    .status-task-done td:not(.actions-cell) { background-color: #e6ffed !important; color: #555; }
    .status-task-on_hold td { background-color: #f8f9fa !important; color: #6c757d; }
    .table-info, .table-info > th, .table-info > td { background-color: #d1ecf1 !important; } /* For highlighted task */
</style>
<script> /* Alert dismissal JS ... */ </script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
