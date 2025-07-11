<?php
// user/inservice/event_details.php
require_once __DIR__ . '/../includes/header.php';

$user_id_is_detail_page = get_current_user_id(); // Renamed to avoid conflict
$event_id_is_detail_page = null;
$event_data_is_detail_page = null;
$user_checklist_items_is_page = ['before' => [], 'after' => []];
$shared_content_is_page = [];
$errors_is_detail_page = [];

if (!isset($_GET['event_id']) || !is_numeric($_GET['event_id'])) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'شناسه رویداد نامعتبر است.'];
    header("Location: my_schedule.php"); exit;
}
$event_id_is_detail_page = (int)$_GET['event_id'];
$csrf_token_event_detail_actions_page = generate_csrf_token('inservice_event_detail_action_' . $event_id_is_detail_page);

$inservice_department_id_detail_page = null;
$stmt_is_dept_detail_page = $conn->prepare("SELECT DepartmentID FROM Departments WHERE DepartmentName LIKE '%ضمن خدمت%' OR DepartmentName LIKE '%امید تدریس%' LIMIT 1");
if ($stmt_is_dept_detail_page) { /* ... fetch department ID ... */ $stmt_is_dept_detail_page->execute(); $res_isd_detail_page = $stmt_is_dept_detail_page->get_result(); if ($isd_row_detail_page = $res_isd_detail_page->fetch_assoc()) $inservice_department_id_detail_page = $isd_row_detail_page['DepartmentID']; $stmt_is_dept_detail_page->close(); }
if (!$inservice_department_id_detail_page) $inservice_department_id_detail_page = 2; // Fallback

$stmt_event_det_page = $conn->prepare("SELECT EventID, EventName, EventDate, Location, Speaker, Status, Notes FROM EventCalendar WHERE EventID = ? AND DepartmentID = ?"); // Added Speaker, Notes
if ($stmt_event_det_page) {
    $stmt_event_det_page->bind_param("ii", $event_id_is_detail_page, $inservice_department_id_detail_page); $stmt_event_det_page->execute(); $res_evt_det_page = $stmt_event_det_page->get_result();
    if ($res_evt_det_page->num_rows === 1) $event_data_is_detail_page = $res_evt_det_page->fetch_assoc();
    else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'رویداد یافت نشد یا اجازه دسترسی ندارید.']; header("Location: my_schedule.php"); exit; }
    $stmt_event_det_page->close();
} else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا بارگذاری رویداد: '.$conn->error]; header("Location: my_schedule.php"); exit; }


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_checklist_item_status'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'inservice_event_detail_action_' . $event_id_is_detail_page)) {
        $errors_is_detail_page[] = 'خطای CSRF!';
    } else {
        $checklist_id_to_update_page = isset($_POST['checklist_id']) ? (int)$_POST['checklist_id'] : null;
        $new_is_completed_status_page = isset($_POST['is_completed_chk_' . $checklist_id_to_update_page]) ? 1 : 0;

        if ($checklist_id_to_update_page && $user_id_is_detail_page) {
            $stmt_update_chk_status_page = $conn->prepare("UPDATE EventChecklists SET IsCompleted = ? WHERE ChecklistID = ? AND EventID = ? AND ResponsibleUserID = ?");
            if ($stmt_update_chk_status_page) {
                $stmt_update_chk_status_page->bind_param("iiii", $new_is_completed_status_page, $checklist_id_to_update_page, $event_id_is_detail_page, $user_id_is_detail_page);
                if ($stmt_update_chk_status_page->execute()) {
                    if ($stmt_update_chk_status_page->affected_rows > 0) $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'وضعیت آیتم چک‌لیست بروزرسانی شد.'];
                    else $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'تغییری اعمال نشد.'];
                } else { $errors_is_detail_page[] = "خطا بروزرسانی چک‌لیست: " . $stmt_update_chk_status_page->error; }
                $stmt_update_chk_status_page->close();
            } else { $errors_is_detail_page[] = "خطا آماده سازی بروزرسانی چک‌لیست: " . $conn->error; }
            // Regenerate CSRF token for the page as it's reloaded
            $csrf_token_event_detail_actions_page = regenerate_csrf_token('inservice_event_detail_action_' . $event_id_is_detail_page);
            if(empty($errors_is_detail_page)) { header("Location: event_details.php?event_id=" . $event_id_is_detail_page); exit; }
        } else { $errors_is_detail_page[] = "اطلاعات نامعتبر برای بروزرسانی چک‌لیست."; }
    }
     $csrf_token_event_detail_actions_page = regenerate_csrf_token('inservice_event_detail_action_' . $event_id_is_detail_page);
}

$stmt_user_chk_page = $conn->prepare("SELECT ChecklistID, ItemName, IsCompleted, DueDate, ItemType FROM EventChecklists WHERE EventID = ? AND ResponsibleUserID = ? ORDER BY ItemType ASC, SortOrder ASC, ChecklistID ASC");
if($stmt_user_chk_page){
    $stmt_user_chk_page->bind_param("ii", $event_id_is_detail_page, $user_id_is_detail_page); $stmt_user_chk_page->execute(); $res_uchk_page = $stmt_user_chk_page->get_result();
    while($uchk_row_page = $res_uchk_page->fetch_assoc()) {
        if(isset($user_checklist_items_is_page[$uchk_row_page['ItemType']])) {
            $user_checklist_items_is_page[$uchk_row_page['ItemType']][] = $uchk_row_page;
        }
    }
    $stmt_user_chk_page->close();
}

$stmt_shared_files_page = $conn->prepare("SELECT FileID, FileName, FilePath, Description, UploadDate FROM Files WHERE AssociatedEntityID = ? AND AssociatedEntityType = 'inservice_content' ORDER BY UploadDate DESC");
if($stmt_shared_files_page){
    $stmt_shared_files_page->bind_param("i", $event_id_is_detail_page); $stmt_shared_files_page->execute(); $res_shf_page = $stmt_shared_files_page->get_result();
    while($shf_row_page = $res_shf_page->fetch_assoc()) $shared_content_is_page[] = $shf_row_page;
    $stmt_shared_files_page->close();
}

$event_status_options_is_detail_disp = ['planned' => 'برنامه‌ریزی شده', 'confirmed' => 'قطعی شده', 'completed' => 'انجام شده', 'cancelled' => 'لغو شده'];
$status_badge_map_is_detail_disp = ['planned' => 'primary', 'confirmed' => 'info', 'completed' => 'success', 'cancelled' => 'secondary'];
?>
<div class="page-header"><h1>جزئیات جلسه: <?php echo htmlspecialchars($event_data_is_detail_page['EventName'] ?? '...'); ?></h1>
    <div class="page-header-actions"><a href="my_schedule.php" class="btn btn-secondary"><svg class="icon" width="16" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"></polyline></svg><span>بازگشت به برنامه</span></a></div></div>

<?php if (isset($_SESSION['flash_message'])) { $flash_is_det_user_page = $_SESSION['flash_message']; echo "<div class='alert alert-{$flash_is_det_user_page['type']} alert-dismissible fade show'>{$flash_is_det_user_page['text']}<button type='button' class='close' data-dismiss='alert'>&times;</button></div>"; unset($_SESSION['flash_message']); echo "<script>/*Dismiss JS*/</script>";} ?>
<?php if (!empty($errors_is_detail_page)): ?> <div class="alert alert-danger"><ul><?php foreach ($errors_is_detail_page as $err_isd_item_user_page): ?><li><?php echo htmlspecialchars($err_isd_item_user_page); ?></li><?php endforeach; ?></ul></div> <?php endif; ?>

<?php if ($event_data_is_detail_page): ?>
<div class="card shadow-sm mb-4"><div class="card-header bg-light py-3"><div class="d-flex justify-content-between align-items-center">
    <h5 class="mb-0 card-title-text"><?php echo htmlspecialchars($event_data_is_detail_page['EventName']); ?></h5>
    <span class="badge badge-<?php echo $status_badge_map_is_detail_disp[$event_data_is_detail_page['Status']] ?? 'light'; ?> p-2"><?php echo $event_status_options_is_detail_disp[$event_data_is_detail_page['Status']] ?? $event_data_is_detail_page['Status']; ?></span>
</div></div>
<div class="card-body">
    <p><strong>تاریخ و زمان:</strong> <?php echo to_jalali($event_data_is_detail_page['EventDate'], 'yyyy/MM/dd HH:mm'); ?></p>
    <?php if($event_data_is_detail_page['Location']): ?><p><strong>مکان:</strong> <?php echo htmlspecialchars($event_data_is_detail_page['Location']); ?></p><?php endif; ?>
    <?php if($event_data_is_detail_page['Speaker']): ?><p><strong>استاد/سخنران:</strong> <?php echo htmlspecialchars($event_data_is_detail_page['Speaker']); ?></p><?php endif; ?>
    <?php
        // Parse structured notes from admin if they exist
        $admin_notes_parsed = ['speakers' => '', 'meeting_notes' => '', 'hospitality' => ''];
        $event_admin_notes = $event_data_is_detail_page['Notes'] ?? '';
        if(preg_match('/سخنرانان: (.*?)\n/s', $event_admin_notes, $m_spk)) $admin_notes_parsed['speakers'] = trim($m_spk[1]);
        if(preg_match('/نکات جلسه:\n(.*?)(?:\n---|$)/s', $event_admin_notes, $m_nts)) $admin_notes_parsed['meeting_notes'] = trim($m_nts[1]);
        if(preg_match('/پذیرایی: (.*?)(?:\n|$)/s', $event_admin_notes, $m_hsp)) $admin_notes_parsed['hospitality'] = trim($m_hsp[1]);

        if(!empty($admin_notes_parsed['meeting_notes'])):
    ?>
    <div class="mt-3 p-3 bg-light border rounded small"><strong>نکات جلسه (ثبت شده توسط ادمین):</strong><br><?php echo nl2br(htmlspecialchars($admin_notes_parsed['meeting_notes'])); ?></div>
    <?php elseif (!empty($event_admin_notes) && empty($admin_notes_parsed['speakers']) && empty($admin_notes_parsed['hospitality'])): // Display raw notes if not structured for specific fields ?>
         <div class="mt-3 p-3 bg-light border rounded small"><strong>یادداشت‌های جلسه:</strong><br><?php echo nl2br(htmlspecialchars($event_admin_notes)); ?></div>
    <?php endif; ?>
     <?php if(!empty($admin_notes_parsed['hospitality'])): ?>
        <p class="mt-2 small"><strong>پذیرایی:</strong> <?php echo htmlspecialchars($admin_notes_parsed['hospitality']); ?></p>
    <?php endif; ?>
</div></div>

<?php if (!empty($user_checklist_items_is_page['before']) || !empty($user_checklist_items_is_page['after'])): ?>
<div class="card shadow-sm mb-4"><div class="card-header"><h5 class="mb-0 card-title-text">چک‌لیست‌های شما برای این رویداد</h5></div>
<div class="card-body">
    <?php foreach(['before' => 'قبل از برگزاری', 'after' => 'بعد از برگزاری'] as $type_key_is_user_disp => $type_label_is_user_disp): ?>
        <?php if(!empty($user_checklist_items_is_page[$type_key_is_user_disp])): ?>
        <h6 class="mt-3 text-muted font-weight-bold"><?php echo $type_label_is_user_disp; ?>:</h6>
        <ul class="list-group">
        <?php foreach($user_checklist_items_is_page[$type_key_is_user_disp] as $item_chk_u_item_disp_val): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center <?php if($item_chk_u_item_disp_val['IsCompleted']) echo 'list-group-item-light';?>">
                <form action="event_details.php?event_id=<?php echo $event_id_is_detail_page; ?>" method="POST" class="w-100 d-flex justify-content-between align-items-center m-0 p-0">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_event_detail_actions_page; ?>">
                    <input type="hidden" name="checklist_id" value="<?php echo $item_chk_u_item_disp_val['ChecklistID']; ?>">
                    <input type="hidden" name="update_checklist_item_status" value="1">
                    <div>
                        <input type="checkbox" class="form-check-input-inline" name="is_completed_chk_<?php echo $item_chk_u_item_disp_val['ChecklistID'];?>" value="1" <?php if($item_chk_u_item_disp_val['IsCompleted']) echo 'checked'; ?> onchange="this.form.submit();" id="chk_user_<?php echo $item_chk_u_item_disp_val['ChecklistID'];?>">
                        <label for="chk_user_<?php echo $item_chk_u_item_disp_val['ChecklistID'];?>" class="<?php if($item_chk_u_item_disp_val['IsCompleted']) echo 'text-decoration-line-through text-muted';?>"><?php echo htmlspecialchars($item_chk_u_item_disp_val['ItemName']); ?></label>
                        <?php if($item_chk_u_item_disp_val['DueDate']): ?><small class="d-block text-info">سررسید: <?php echo to_jalali($item_chk_u_item_disp_val['DueDate'], 'yyyy/MM/dd');?></small><?php endif; ?>
                    </div>
                </form>
                <span class="badge badge-<?php echo $item_chk_u_item_disp_val['IsCompleted'] ? 'success':'warning';?> badge-pill"><?php echo $item_chk_u_item_disp_val['IsCompleted'] ? 'انجام شده':'در انتظار';?></span>
            </li>
        <?php endforeach; ?></ul> <?php endif; ?> <?php endforeach; ?></div></div>
<?php elseif ($user_id_is_detail_page && get_current_user_type() === 'teacher'): ?>
    <div class="alert alert-light small">چک‌لیستی برای شما در این رویداد تعریف نشده است.</div>
<?php endif; ?>

<div class="card shadow-sm"><div class="card-header"><h5 class="mb-0 card-title-text">محتوای به اشتراک گذاشته شده</h5></div>
<div class="card-body">
    <?php if(!empty($shared_content_is_page)): ?><ul class="list-unstyled">
        <?php foreach($shared_content_is_page as $file_sh_item_disp_val): ?>
        <li class="mb-2 pb-2 border-bottom">
            <div class="d-flex align-items-center">
                <svg class="icon text-primary-user mr-2" width="20" height="20" viewBox="0 0 24 24"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
                <a href="/my_site/<?php echo htmlspecialchars(ltrim($file_sh_item_disp_val['FilePath'],'/')); ?>" target="_blank" download="<?php echo htmlspecialchars($file_sh_item_disp_val['FileName']); ?>">
                    <strong><?php echo htmlspecialchars($file_sh_item_disp_val['FileName']); ?></strong>
                </a>
            </div>
            <?php if($file_sh_item_disp_val['Description']):?><small class="d-block text-muted ml-4 pl-2"><?php echo htmlspecialchars($file_sh_item_disp_val['Description']);?></small><?php endif;?>
            <small class="d-block text-muted ml-4 pl-2">تاریخ آپلود: <?php echo to_jalali($file_sh_item_disp_val['UploadDate'], 'yyyy/MM/dd');?></small>
        </li>
        <?php endforeach; ?></ul>
    <?php else: ?><p class="text-muted">هنوز محتوایی برای این جلسه به اشتراک گذاشته نشده است.</p><?php endif; ?>
</div></div>
<style> .text-decoration-line-through { text-decoration: line-through; } .form-check-input-inline { margin-left: 0.5rem; vertical-align: middle;} .ml-4 { margin-right: 1.5rem !important; /* RTL */ } .pl-2 {padding-right: 0.5rem !important; /* RTL */} .mr-2 {margin-left: 0.5rem !important; /* RTL */} .badge.p-2 {padding:0.4em 0.6em !important; font-size:0.85em !important;} .icon{vertical-align: -0.125em; margin-left: 4px;}</style>
<?php else: if(empty($errors_is_detail_page)):?><div class="alert alert-warning">اطلاعات رویداد برای نمایش در دسترس نیست.</div><?php endif; endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
