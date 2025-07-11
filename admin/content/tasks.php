<?php
// admin/content/tasks.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth

$project_id_for_tasks = null;
$project_data_tasks = null;
$project_tasks_db = []; // To store tasks from DB for listing
$errors_cpt = [];
$edit_mode_cpt = false;
// Default values for the task form
$task_to_edit_values_default = ['TaskID'=>null, 'TaskName'=>'', 'Description'=>'', 'AssignedToUserID'=>null, 'DueDate'=>'', 'Status'=>'todo'];
$task_to_edit_values = $task_to_edit_values_default;


if (!isset($_GET['project_id']) || !is_numeric($_GET['project_id'])) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'شناسه پروژه محتوایی نامعتبر است.'];
    header("Location: projects.php"); exit;
}
$project_id_for_tasks = (int)$_GET['project_id'];
$csrf_token_content_tasks = generate_csrf_token('content_project_tasks_action_' . $project_id_for_tasks);

// Fetch Project Details
$stmt_project_cpt = $conn->prepare("SELECT ContentProjectID, ProjectName FROM ContentProjects WHERE ContentProjectID = ?");
if ($stmt_project_cpt) {
    $stmt_project_cpt->bind_param("i", $project_id_for_tasks); $stmt_project_cpt->execute(); $res_proj_cpt = $stmt_project_cpt->get_result();
    if ($res_proj_cpt->num_rows === 1) $project_data_tasks = $res_proj_cpt->fetch_assoc();
    else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'پروژه محتوایی یافت نشد.']; header("Location: projects.php"); exit; }
    $stmt_project_cpt->close();
} else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا بارگذاری پروژه: '.$conn->error]; header("Location: projects.php"); exit; }

// Fetch users for AssignedToUserID dropdown
$users_cpt_q = $conn->query("SELECT UserID, FirstName, LastName, Username FROM Users WHERE IsActive = TRUE ORDER BY LastName, FirstName");
$available_assignees_cpt = [];
if($users_cpt_q){ while($u_cpt = $users_cpt_q->fetch_assoc()) $available_assignees_cpt[$u_cpt['UserID']] = $u_cpt['FirstName'].' '.$u_cpt['LastName'] . ' (@'.$u_cpt['Username'].')'; $users_cpt_q->close(); }

$task_status_options_cpt = ['todo' => 'انجام نشده', 'in_progress' => 'در حال انجام', 'done' => 'انجام شده', 'on_hold' => 'متوقف شده'];
$task_status_badge_cpt = ['todo'=>'warning', 'in_progress'=>'info', 'done'=>'success', 'on_hold'=>'secondary'];

// Handle GET request for editing a task
if (isset($_GET['edit_task_id']) && is_numeric($_GET['edit_task_id']) && $_SERVER["REQUEST_METHOD"] != "POST") {
    $edit_task_id_get = (int)$_GET['edit_task_id'];
    $stmt_edit_task_get = $conn->prepare("SELECT * FROM ContentProjectTasks WHERE TaskID = ? AND ContentProjectID = ?");
    if($stmt_edit_task_get){
        $stmt_edit_task_get->bind_param("ii", $edit_task_id_get, $project_id_for_tasks);
        $stmt_edit_task_get->execute();
        $res_edit_task = $stmt_edit_task_get->get_result();
        if($data_task_edit = $res_edit_task->fetch_assoc()){
            $task_to_edit_values = $data_task_edit;
            if($task_to_edit_values['DueDate']) $task_to_edit_values['DueDate'] = (new DateTime($task_to_edit_values['DueDate']))->format('Y-m-d');
            $edit_mode_cpt = true;
        } else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'وظیفه برای ویرایش یافت نشد.'];}
        $stmt_edit_task_get->close();
    } else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا در بارگذاری وظیفه: '.$conn->error];}
}


// Handle Form Submission (Create/Update Task)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_content_task'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'content_project_tasks_action_' . $project_id_for_tasks)) {
        $errors_cpt[] = 'خطای CSRF!';
    } else {
        $task_id_post = isset($_POST['task_id_cpt']) && is_numeric($_POST['task_id_cpt']) ? (int)$_POST['task_id_cpt'] : null;

        $task_to_edit_values['TaskName'] = sanitize_input($_POST['TaskName'] ?? '');
        $task_to_edit_values['Description'] = sanitize_input($_POST['Description'] ?? '');
        $task_to_edit_values['AssignedToUserID'] = !empty($_POST['AssignedToUserID']) ? (int)$_POST['AssignedToUserID'] : null;
        $task_to_edit_values['DueDate'] = !empty($_POST['DueDate']) ? sanitize_input($_POST['DueDate']) : null;
        $task_to_edit_values['Status'] = sanitize_input($_POST['Status'] ?? 'todo');
        if($task_id_post) $task_to_edit_values['TaskID'] = $task_id_post; // Keep TaskID if editing
        $edit_mode_cpt = ($task_id_post !== null);


        if (empty($task_to_edit_values['TaskName'])) $errors_cpt[] = "نام وظیفه الزامی است.";
        if (!array_key_exists($task_to_edit_values['Status'], $task_status_options_cpt)) $errors_cpt[] = "وضعیت وظیفه نامعتبر.";
        if($task_to_edit_values['DueDate'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $task_to_edit_values['DueDate'])) $errors_cpt[] = "فرمت تاریخ سررسید (YYYY-MM-DD).";
        if ($task_to_edit_values['AssignedToUserID'] !== null && !isset($available_assignees_cpt[$task_to_edit_values['AssignedToUserID']])) $errors_cpt[] = "کاربر تخصیص یافته نامعتبر.";

        if (empty($errors_cpt)) {
            if ($task_id_post) {
                $stmt_cpt_db_action = $conn->prepare("UPDATE ContentProjectTasks SET TaskName=?, Description=?, AssignedToUserID=?, DueDate=?, Status=? WHERE TaskID=? AND ContentProjectID=?");
                if($stmt_cpt_db_action) { $stmt_cpt_db_action->bind_param("ssisssi", $task_to_edit_values['TaskName'], $task_to_edit_values['Description'], $task_to_edit_values['AssignedToUserID'], $task_to_edit_values['DueDate'], $task_to_edit_values['Status'], $task_id_post, $project_id_for_tasks);
                    if($stmt_cpt_db_action->execute()) $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'وظیفه ویرایش شد.']; else $errors_cpt[] = "خطا ویرایش: ".$stmt_cpt_db_action->error; $stmt_cpt_db_action->close();
                } else $errors_cpt[] = "خطا آماده سازی ویرایش: ".$conn->error;
            } else {
                $stmt_cpt_db_action = $conn->prepare("INSERT INTO ContentProjectTasks (ContentProjectID, TaskName, Description, AssignedToUserID, DueDate, Status) VALUES (?, ?, ?, ?, ?, ?)");
                if($stmt_cpt_db_action) { $stmt_cpt_db_action->bind_param("ississ", $project_id_for_tasks, $task_to_edit_values['TaskName'], $task_to_edit_values['Description'], $task_to_edit_values['AssignedToUserID'], $task_to_edit_values['DueDate'], $task_to_edit_values['Status']);
                    if($stmt_cpt_db_action->execute()){ $new_task_id_cpt_notify = $stmt_cpt_db_action->insert_id; $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'وظیفه ایجاد شد.'];
                        if($task_to_edit_values['AssignedToUserID']){
                            $notif_msg_cpt_create = "وظیفه جدید '".htmlspecialchars($task_to_edit_values['TaskName'])."' در پروژه '".htmlspecialchars($project_data_tasks['ProjectName'])."' به شما محول شد.";
                            $notif_link_cpt_create = ($admin_base_url ?? '/my_site/admin')."/content/tasks.php?project_id=".$project_id_for_tasks."&highlight_task=".$new_task_id_cpt_notify; // Highlight task
                            create_notification($task_to_edit_values['AssignedToUserID'], $notif_msg_cpt_create, $notif_link_cpt_create, 'content_task_assigned', $new_task_id_cpt_notify);
                        }
                    } else $errors_cpt[] = "خطا ایجاد: ".$stmt_cpt_db_action->error; $stmt_cpt_db_action->close();
                } else $errors_cpt[] = "خطا آماده سازی ایجاد: ".$conn->error;
            }
            if(empty($errors_cpt)) { regenerate_csrf_token('content_project_tasks_action_' . $project_id_for_tasks); header("Location: tasks.php?project_id=".$project_id_for_tasks); exit; }
        }
    }
    $csrf_token_content_tasks = regenerate_csrf_token('content_project_tasks_action_' . $project_id_for_tasks);
}

// Handle Delete Task (GET action)
if (isset($_GET['delete_task_id']) && is_numeric($_GET['delete_task_id'])) {
    if (!isset($_GET['csrf_token_del']) || !verify_csrf_token($_GET['csrf_token_del'], 'content_project_tasks_action_' . $project_id_for_tasks)) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطای CSRF برای حذف وظیفه!'];
    } else {
        $task_id_to_delete_get = (int)$_GET['delete_task_id'];
        $stmt_del_cpt = $conn->prepare("DELETE FROM ContentProjectTasks WHERE TaskID = ? AND ContentProjectID = ?");
        if($stmt_del_cpt){
            $stmt_del_cpt->bind_param("ii", $task_id_to_delete_get, $project_id_for_tasks);
            if($stmt_del_cpt->execute() && $stmt_del_cpt->affected_rows > 0) $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'وظیفه با موفقیت حذف شد.'];
            else $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا در حذف وظیفه یا وظیفه یافت نشد: '.$stmt_del_cpt->error];
            $stmt_del_cpt->close();
        } else $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا در آماده سازی حذف وظیفه: '.$conn->error];
    }
    regenerate_csrf_token('content_project_tasks_action_' . $project_id_for_tasks); // Regenerate main form token
    header("Location: tasks.php?project_id=" . $project_id_for_tasks); exit;
}


$cpt_list_q_main = $conn->prepare("SELECT cpt.*, CONCAT(u.FirstName, ' ', u.LastName) as AssigneeName FROM ContentProjectTasks cpt LEFT JOIN Users u ON cpt.AssignedToUserID = u.UserID WHERE cpt.ContentProjectID = ? ORDER BY cpt.Status ASC, cpt.DueDate ASC, cpt.TaskName ASC");
if($cpt_list_q_main){ $cpt_list_q_main->bind_param("i", $project_id_for_tasks); $cpt_list_q_main->execute(); $res_cpt_list_main = $cpt_list_q_main->get_result();
    while($task_cpt_item = $res_cpt_list_main->fetch_assoc()) $project_tasks_db[] = $task_cpt_item; $cpt_list_q_main->close();
}
?>
<div class="page-header"><h1>وظایف پروژه: <?php echo htmlspecialchars($project_data_tasks['ProjectName'] ?? '...');?></h1>
    <div class="page-header-actions"><a href="projects.php" class="btn btn-secondary"><svg class="icon" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"></polyline></svg><span>بازگشت به پروژه‌ها</span></a></div></div>

<?php if (isset($_SESSION['flash_message'])) { /* Flash */ } ?>
<?php if (!empty($errors_cpt)): ?> <div class="alert alert-danger"><ul><?php foreach ($errors_cpt as $err_cpt_item_msg): ?><li><?php echo htmlspecialchars($err_cpt_item_msg); ?></li><?php endforeach; ?></ul></div> <?php endif; ?>

<div class="row"><div class="col-lg-5 mb-4"><div class="card shadow-sm">
    <div class="card-header"><span class="card-title-text"><?php echo $edit_mode_cpt ? 'ویرایش وظیفه' : 'افزودن وظیفه جدید'; ?></span></div><div class="card-body">
    <form action="tasks.php?project_id=<?php echo $project_id_for_tasks; ?><?php if($edit_mode_cpt && $task_to_edit_values['TaskID']) echo '&edit_task_id='.$task_to_edit_values['TaskID']; ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_content_tasks; ?>">
        <?php if ($edit_mode_cpt && $task_to_edit_values['TaskID']): ?><input type="hidden" name="task_id_cpt" value="<?php echo $task_to_edit_values['TaskID']; ?>"><?php endif; ?>
        <div class="form-group"><label for="TaskName_cpt_form_main">نام وظیفه <span class="text-danger">*</span></label><input type="text" class="form-control" id="TaskName_cpt_form_main" name="TaskName" value="<?php echo htmlspecialchars($task_to_edit_values['TaskName']); ?>" required></div>
        <div class="form-group"><label for="Description_cpt_form_main">شرح</label><textarea class="form-control" id="Description_cpt_form_main" name="Description" rows="3"><?php echo htmlspecialchars($task_to_edit_values['Description']); ?></textarea></div>
        <div class="form-row">
            <div class="form-group col-md-6"><label for="AssignedToUserID_cpt_form_main">تخصیص به</label><select name="AssignedToUserID" id="AssignedToUserID_cpt_form_main" class="form-control custom-select"><option value="">-- انتخاب --</option><?php foreach($available_assignees_cpt as $uid_cpt_f => $uname_cpt_f):?><option value="<?php echo $uid_cpt_f;?>" <?php if($task_to_edit_values['AssignedToUserID']==$uid_cpt_f) echo 'selected';?>><?php echo htmlspecialchars($uname_cpt_f);?></option><?php endforeach;?></select></div>
            <div class="form-group col-md-6"><label for="DueDate_cpt_form_main">سررسید</label><input type="text" class="form-control persian-date-picker" id="DueDate_cpt_form_main" name="DueDate" value="<?php echo htmlspecialchars($task_to_edit_values['DueDate']); ?>" placeholder="YYYY-MM-DD"></div>
        </div>
        <div class="form-group"><label for="Status_cpt_form_main">وضعیت <span class="text-danger">*</span></label><select name="Status" id="Status_cpt_form_main" class="form-control custom-select" required><?php foreach($task_status_options_cpt as $tsk_k_f => $tsk_v_f):?><option value="<?php echo $tsk_k_f;?>" <?php if($task_to_edit_values['Status']==$tsk_k_f) echo 'selected';?>><?php echo $tsk_v_f;?></option><?php endforeach;?></select></div>
        <div class="form-actions"><button type="submit" name="submit_content_task" class="btn btn-primary"><?php echo $edit_mode_cpt ? 'ذخیره' : 'ایجاد'; ?></button><?php if ($edit_mode_cpt): ?><a href="tasks.php?project_id=<?php echo $project_id_for_tasks; ?>" class="btn btn-outline-secondary">لغو</a><?php endif; ?></div>
    </form></div></div></div>
    <div class="col-lg-7"><div class="card shadow-sm"><div class="card-header"><span class="card-title-text">لیست وظایف</span></div><div class="card-body">
    <?php if(!empty($project_tasks_db)): ?><div class="table-responsive"><table class="table table-sm table-striped table-hover">
        <thead><tr><th>#</th><th>وظیفه</th><th>مسئول</th><th>سررسید</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>
        <?php $cpt_row_idx = 1; foreach($project_tasks_db as $task_item_cpt_list): ?><tr>
            <td><?php echo $cpt_row_idx++;?></td><td><strong><?php echo htmlspecialchars($task_item_cpt_list['TaskName']);?></strong><small class="d-block text-muted"><?php echo htmlspecialchars(mb_substr($task_item_cpt_list['Description']??'',0,50)).(mb_strlen($task_item_cpt_list['Description']??'')>50?'...':'');?></small></td>
            <td><small><?php echo htmlspecialchars($task_item_cpt_list['AssigneeName'] ?? '-');?></small></td>
            <td><small><?php echo $task_item_cpt_list['DueDate'] ? to_jalali($task_item_cpt_list['DueDate'],'yy/MM/dd') : '-';?></small></td>
            <td><span class="badge badge-<?php echo $task_status_badge_cpt[$task_item_cpt_list['Status']] ?? 'light';?> p-1"><?php echo $task_status_options_cpt[$task_item_cpt_list['Status']] ?? '-';?></span></td>
            <td class="actions-cell"><a href="tasks.php?project_id=<?php echo $project_id_for_tasks;?>&edit_task_id=<?php echo $task_item_cpt_list['TaskID'];?>" class="btn btn-xs btn-warning" title="ویرایش"><svg class="icon" width="12" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a>
            <a href="tasks.php?project_id=<?php echo $project_id_for_tasks;?>&delete_task_id=<?php echo $task_item_cpt_list['TaskID'];?>&csrf_token_del=<?php echo $csrf_token_content_tasks; ?>" class="btn btn-xs btn-danger" title="حذف" onclick="return confirm('آیا از حذف این وظیفه مطمئن هستید؟');"><svg class="icon" width="12" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></a>
            </td></tr><?php endforeach; ?></tbody></table></div>
    <?php else: ?><p class="text-muted">هنوز وظیفه‌ای برای این پروژه ثبت نشده.</p><?php endif; ?>
    </div></div></div></div>
<link rel="stylesheet" href="https://unpkg.com/persian-datepicker@latest/dist/css/persian-datepicker.min.css"/>
<script src="https://unpkg.com/persian-datepicker@latest/dist/js/persian-datepicker.min.js"></script>
<script> /* Datepicker init ... */ </script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
