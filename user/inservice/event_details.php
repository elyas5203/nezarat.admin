<?php
// user/inservice/event_details.php
require_once __DIR__ . '/../includes/header.php';

$user_id_is_detail = get_current_user_id();
$event_id_is_detail = null;
$event_data_is_detail = null;
$user_checklist_items_is = ['before' => [], 'after' => []]; // Initialize for both types
$shared_content_is = [];
$errors_is_detail = [];

if (!isset($_GET['event_id']) || !is_numeric($_GET['event_id'])) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'شناسه رویداد نامعتبر است.'];
    header("Location: my_schedule.php"); exit;
}
$event_id_is_detail = (int)$_GET['event_id'];
$csrf_token_event_detail_actions = generate_csrf_token('inservice_event_detail_action_' . $event_id_is_detail);

$inservice_department_id_detail = null;
$stmt_is_dept_detail = $conn->prepare("SELECT DepartmentID FROM Departments WHERE DepartmentName LIKE '%ضمن خدمت%' OR DepartmentName LIKE '%امید تدریس%' LIMIT 1");
if ($stmt_is_dept_detail) { $stmt_is_dept_detail->execute(); $res_isd_detail = $stmt_is_dept_detail->get_result(); if ($isd_row_detail = $res_isd_detail->fetch_assoc()) $inservice_department_id_detail = $isd_row_detail['DepartmentID']; $stmt_is_dept_detail->close(); }
if (!$inservice_department_id_detail) $inservice_department_id_detail = 2; // Fallback

$stmt_event_det = $conn->prepare("SELECT * FROM EventCalendar WHERE EventID = ? AND DepartmentID = ?");
if ($stmt_event_det) {
    $stmt_event_det->bind_param("ii", $event_id_is_detail, $inservice_department_id_detail); $stmt_event_det->execute(); $res_evt_det = $stmt_event_det->get_result();
    if ($res_evt_det->num_rows === 1) $event_data_is_detail = $res_evt_det->fetch_assoc();
    else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'رویداد یافت نشد یا شما اجازه دسترسی ندارید.']; header("Location: my_schedule.php"); exit; }
    $stmt_event_det->close();
} else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا بارگذاری رویداد: '.$conn->error]; header("Location: my_schedule.php"); exit; }

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_checklist_item_status'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'inservice_event_detail_action_' . $event_id_is_detail)) {
        $errors_is_detail[] = 'خطای CSRF!';
    } else {
        $checklist_id_to_update = isset($_POST['checklist_id']) ? (int)$_POST['checklist_id'] : null;
        // The value of checkbox 'is_completed_X' will be its 'value' attribute if checked, or not present in POST if unchecked.
        // So, we check if the specific checkbox name (e.g., is_completed_X) is set in POST.
        $new_is_completed_status = isset($_POST['is_completed_' . $checklist_id_to_update]) ? 1 : 0;

        if ($checklist_id_to_update) {
            $stmt_update_chk_status = $conn->prepare("UPDATE EventChecklists SET IsCompleted = ? WHERE ChecklistID = ? AND EventID = ? AND ResponsibleUserID = ?");
            if ($stmt_update_chk_status) {
                $stmt_update_chk_status->bind_param("iiii", $new_is_completed_status, $checklist_id_to_update, $event_id_is_detail, $user_id_is_detail);
                if ($stmt_update_chk_status->execute()) {
                    if ($stmt_update_chk_status->affected_rows > 0) $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'وضعیت آیتم چک‌لیست بروزرسانی شد.'];
                    else $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'تغییری اعمال نشد (ممکن است شما مسئول این آیتم نباشید یا وضعیت قبلاً همین بوده).'];
                } else { $errors_is_detail[] = "خطا در بروزرسانی چک‌لیست: " . $stmt_update_chk_status->error; }
                $stmt_update_chk_status->close();
            } else { $errors_is_detail[] = "خطا آماده سازی بروزرسانی چک‌لیست: " . $conn->error; }
            regenerate_csrf_token('inservice_event_detail_action_' . $event_id_is_detail);
            header("Location: event_details.php?event_id=" . $event_id_is_detail); exit;
        } else { $errors_is_detail[] = "شناسه آیتم چک‌لیست نامعتبر است."; }
    }
     $csrf_token_event_detail_actions = regenerate_csrf_token('inservice_event_detail_action_' . $event_id_is_detail); // Regenerate on error too
}

$stmt_user_chk = $conn->prepare("SELECT ChecklistID, ItemName, IsCompleted, DueDate, ItemType FROM EventChecklists WHERE EventID = ? AND ResponsibleUserID = ? ORDER BY ItemType ASC, SortOrder ASC, ChecklistID ASC");
if($stmt_user_chk){
    $stmt_user_chk->bind_param("ii", $event_id_is_detail, $user_id_is_detail); $stmt_user_chk->execute(); $res_uchk = $stmt_user_chk->get_result();
    while($uchk_row = $res_uchk->fetch_assoc()) {
        if(isset($user_checklist_items_is[$uchk_row['ItemType']])) { // Ensure key exists
            $user_checklist_items_is[$uchk_row['ItemType']][] = $uchk_row;
        }
    }
    $stmt_user_chk->close();
}

$stmt_shared_files = $conn->prepare("SELECT FileID, FileName, FilePath, Description, UploadDate FROM Files WHERE AssociatedEntityID = ? AND AssociatedEntityType = 'inservice_content' ORDER BY UploadDate DESC");
if($stmt_shared_files){
    $stmt_shared_files->bind_param("i", $event_id_is_detail); $stmt_shared_files->execute(); $res_shf = $stmt_shared_files->get_result();
    while($shf_row = $res_shf->fetch_assoc()) $shared_content_is[] = $shf_row;
    $stmt_shared_files->close();
}

$event_status_options_is_detail = ['planned' => 'برنامه‌ریزی شده', 'confirmed' => 'قطعی شده', 'completed' => 'انجام شده', 'cancelled' => 'لغو شده'];
$status_badge_map_is_detail = ['planned' => 'primary', 'confirmed' => 'info', 'completed' => 'success', 'cancelled' => 'secondary'];
?>
<div class="page-header"><h1>جزئیات جلسه: <?php echo htmlspecialchars($event_data_is_detail['EventName'] ?? '...'); ?></h1>
    <div class="page-header-actions"><a href="my_schedule.php" class="btn btn-secondary">
        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"></polyline></svg>
        <span>بازگشت به برنامه</span></a></div></div>

<?php if (isset($_SESSION['flash_message'])) { $flash_is_det = $_SESSION['flash_message']; echo "<div class='alert alert-{$flash_is_det['type']} alert-dismissible fade show'>{$flash_is_det['text']}<button type='button' class='close' data-dismiss='alert'>&times;</button></div>"; unset($_SESSION['flash_message']); echo "<script> /* Dismiss JS */ </script>";} ?>
<?php if (!empty($errors_is_detail)): ?> <div class="alert alert-danger"><ul><?php foreach ($errors_is_detail as $err_isd_item): ?><li><?php echo htmlspecialchars($err_isd_item); ?></li><?php endforeach; ?></ul></div> <?php endif; ?>

<?php if ($event_data_is_detail): ?>
<div class="card shadow-sm mb-4"><div class="card-header bg-light py-3"><div class="d-flex justify-content-between align-items-center">
    <h5 class="mb-0 card-title-text"><?php echo htmlspecialchars($event_data_is_detail['EventName']); ?></h5>
    <span class="badge badge-<?php echo $status_badge_map_is_detail[$event_data_is_detail['Status']] ?? 'light'; ?> p-2"><?php echo $event_status_options_is_detail[$event_data_is_detail['Status']] ?? $event_data_is_detail['Status']; ?></span>
</div></div>
<div class="card-body">
    <p><strong>تاریخ و زمان:</strong> <?php echo to_jalali($event_data_is_detail['EventDate'], 'yyyy/MM/dd HH:mm'); ?></p>
    <?php if($event_data_is_detail['Location']): ?><p><strong>مکان:</strong> <?php echo htmlspecialchars($event_data_is_detail['Location']); ?></p><?php endif; ?>
    <?php if($event_data_is_detail['Speaker']): ?><p><strong>استاد/سخنران:</strong> <?php echo htmlspecialchars($event_data_is_detail['Speaker']); ?></p><?php endif; ?>
    <?php if($event_data_is_detail['Notes']): ?><div class="mt-2 p-3 bg-light border rounded small"><strong>یادداشت‌های جلسه (ثبت شده توسط ادمین):</strong><br><?php echo nl2br(htmlspecialchars($event_data_is_detail['Notes'])); ?></div><?php endif; ?>
</div></div>

<?php if (!empty($user_checklist_items_is['before']) || !empty($user_checklist_items_is['after'])): ?>
<div class="card shadow-sm mb-4"><div class="card-header"><h5 class="mb-0 card-title-text">چک‌لیست‌های شما برای این رویداد</h5></div>
<div class="card-body">
    <?php foreach(['before' => 'قبل از برگزاری', 'after' => 'بعد از برگزاری'] as $type_key_is => $type_label_is): ?>
        <?php if(!empty($user_checklist_items_is[$type_key_is])): ?>
        <h6 class="mt-3 text-muted font-weight-bold"><?php echo $type_label_is; ?>:</h6>
        <ul class="list-group">
        <?php foreach($user_checklist_items_is[$type_key_is] as $item_chk_u_item): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center <?php if($item_chk_u_item['IsCompleted']) echo 'list-group-item-light';?>">
                <form action="event_details.php?event_id=<?php echo $event_id_is_detail; ?>" method="POST" class="w-100 d-flex justify-content-between align-items-center">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_event_detail_actions; ?>">
                    <input type="hidden" name="checklist_id" value="<?php echo $item_chk_u_item['ChecklistID']; ?>">
                    <input type="hidden" name="update_checklist_item_status" value="1">
                    <div>
                        <input type="checkbox" class="form-check-input-inline" name="is_completed_<?php echo $item_chk_u_item['ChecklistID'];?>" value="1" <?php if($item_chk_u_item['IsCompleted']) echo 'checked'; ?> onchange="this.form.submit();" id="chk_user_<?php echo $item_chk_u_item['ChecklistID'];?>">
                        <label for="chk_user_<?php echo $item_chk_u_item['ChecklistID'];?>" class="<?php if($item_chk_u_item['IsCompleted']) echo 'text-decoration-line-through text-muted';?>"><?php echo htmlspecialchars($item_chk_u_item['ItemName']); ?></label>
                        <?php if($item_chk_u_item['DueDate']): ?><small class="d-block text-info">سررسید: <?php echo to_jalali($item_chk_u_item['DueDate'], 'yyyy/MM/dd');?></small><?php endif; ?>
                    </div>
                </form>
                <span class="badge badge-<?php echo $item_chk_u_item['IsCompleted'] ? 'success':'warning';?> badge-pill"><?php echo $item_chk_u_item['IsCompleted'] ? 'انجام شده':'در انتظار';?></span>
            </li>
        <?php endforeach; ?>
        </ul> <?php endif; ?>
    <?php endforeach; ?></div></div>
<?php elseif ($user_id_is_detail && get_current_user_type() === 'teacher'): // Show message if user is teacher and has no checklist items assigned ?>
    <div class="alert alert-light small">چک‌لیستی برای شما در این رویداد تعریف نشده است.</div>
<?php endif; ?>

<div class="card shadow-sm"><div class="card-header"><h5 class="mb-0 card-title-text">محتوای به اشتراک گذاشته شده</h5></div>
<div class="card-body">
    <?php if(!empty($shared_content_is)): ?><ul class="list-unstyled">
        <?php foreach($shared_content_is as $file_sh_item): ?>
        <li class="mb-2 pb-2 border-bottom">
            <div class="d-flex align-items-center">
                <svg class="icon text-primary-user mr-2" width="20" height="20" viewBox="0 0 24 24"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
                <a href="/my_site/<?php echo htmlspecialchars(ltrim($file_sh_item['FilePath'],'/')); ?>" target="_blank" download="<?php echo htmlspecialchars($file_sh_item['FileName']); ?>">
                    <strong><?php echo htmlspecialchars($file_sh_item['FileName']); ?></strong>
                </a>
            </div>
            <?php if($file_sh_item['Description']):?><small class="d-block text-muted ml-4 pl-2"><?php echo htmlspecialchars($file_sh_item['Description']);?></small><?php endif;?>
            <small class="d-block text-muted ml-4 pl-2">تاریخ آپلود: <?php echo to_jalali($file_sh_item['UploadDate'], 'yyyy/MM/dd');?></small>
        </li>
        <?php endforeach; ?></ul>
    <?php else: ?><p class="text-muted">هنوز محتوایی برای این جلسه به اشتراک گذاشته نشده است.</p><?php endif; ?>
</div></div>
<style> .text-decoration-line-through { text-decoration: line-through; } .form-check-input-inline { margin-left: 0.5rem; vertical-align: middle;} .ml-4 { margin-right: 1.5rem !important; /* RTL */ } .pl-2 {padding-right: 0.5rem !important; /* RTL */} .mr-2 {margin-left: 0.5rem !important; /* RTL */} </style>
<?php else: if(empty($errors_is_detail)):?><div class="alert alert-warning">اطلاعات رویداد برای نمایش در دسترس نیست.</div><?php endif; endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
