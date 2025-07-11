<?php
// admin/inservice/checklists.php
require_once __DIR__ . '/../includes/header.php';

$event_id_for_checklist = null;
$event_data_checklist = null;
$checklist_items_db = ['before' => [], 'after' => []];
$errors_chk = [];
$success_message_chk = ''; // Not used due to redirect on success

if (!isset($_GET['event_id']) || !is_numeric($_GET['event_id'])) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'شناسه رویداد نامعتبر است.'];
    header("Location: events.php"); exit;
}
$event_id_for_checklist = (int)$_GET['event_id'];
$csrf_token_checklists = generate_csrf_token('inservice_checklists_action_' . $event_id_for_checklist);

$stmt_event_chk = $conn->prepare("SELECT EventID, EventName, DepartmentID FROM EventCalendar WHERE EventID = ?");
if ($stmt_event_chk) {
    $stmt_event_chk->bind_param("i", $event_id_for_checklist); $stmt_event_chk->execute(); $res_evt_chk = $stmt_event_chk->get_result();
    if ($res_evt_chk->num_rows === 1) $event_data_checklist = $res_evt_chk->fetch_assoc();
    else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'رویداد یافت نشد.']; header("Location: events.php"); exit; }
    $stmt_event_chk->close();
} else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا بارگذاری رویداد: '.$conn->error]; header("Location: events.php"); exit; }

$stmt_fetch_items = $conn->prepare("SELECT ChecklistID, ItemName, IsCompleted, DueDate, ItemType, ResponsibleUserID, SortOrder FROM EventChecklists WHERE EventID = ? ORDER BY ItemType ASC, SortOrder ASC, ChecklistID ASC");
if ($stmt_fetch_items) {
    $stmt_fetch_items->bind_param("i", $event_id_for_checklist); $stmt_fetch_items->execute(); $res_items = $stmt_fetch_items->get_result();
    while ($item_db = $res_items->fetch_assoc()) {
        if ($item_db['ItemType'] === 'before' || $item_db['ItemType'] === 'after') {
             $checklist_items_db[$item_db['ItemType']][] = $item_db;
        }
    }
    $stmt_fetch_items->close();
}

$users_for_checklist_q = $conn->query("SELECT UserID, FirstName, LastName FROM Users WHERE IsActive = TRUE ORDER BY LastName, FirstName");
$available_responsible_users = [];
if ($users_for_checklist_q) { while($u_chk = $users_for_checklist_q->fetch_assoc()) $available_responsible_users[$u_chk['UserID']] = $u_chk['FirstName'].' '.$u_chk['LastName']; $users_for_checklist_q->close(); }

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_checklists'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'inservice_checklists_action_' . $event_id_for_checklist)) {
        $errors_chk[] = 'خطای CSRF!';
    } else {
        $posted_items_before = $_POST['items_before'] ?? [];
        $posted_items_after = $_POST['items_after'] ?? [];

        $conn->begin_transaction();
        try {
            $stmt_delete_all = $conn->prepare("DELETE FROM EventChecklists WHERE EventID = ?");
            if(!$stmt_delete_all) throw new Exception("خطا آماده سازی حذف چک‌لیست‌های قبلی: ".$conn->error);
            $stmt_delete_all->bind_param("i", $event_id_for_checklist);
            if(!$stmt_delete_all->execute()) throw new Exception("خطا در حذف چک‌لیست‌های قبلی: ".$stmt_delete_all->error);
            $stmt_delete_all->close();

            $stmt_insert_item = $conn->prepare("INSERT INTO EventChecklists (EventID, ItemName, DueDate, ItemType, IsCompleted, ResponsibleUserID, SortOrder) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if(!$stmt_insert_item) throw new Exception("خطا آماده سازی درج آیتم چک‌لیست: ".$conn->error);

            $process_items = function($items, $type, $start_sort_order = 0) use ($stmt_insert_item, $event_id_for_checklist, $available_responsible_users) {
                $current_sort_order = $start_sort_order;
                if (is_array($items)) {
                    foreach ($items as $item_data) {
                        $item_name = sanitize_input($item_data['name'] ?? '');
                        $due_date_raw = sanitize_input($item_data['due_date'] ?? '');
                        $due_date = (!empty($due_date_raw) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date_raw)) ? $due_date_raw : null;
                        $is_completed = isset($item_data['completed']) ? 1 : 0;
                        $responsible_user_id_raw = $item_data['responsible_user_id'] ?? '';
                        $responsible_user_id = (!empty($responsible_user_id_raw) && isset($available_responsible_users[(int)$responsible_user_id_raw])) ? (int)$responsible_user_id_raw : null;

                        if (!empty($item_name)) {
                            $stmt_insert_item->bind_param("isssiii", $event_id_for_checklist, $item_name, $due_date, $type, $is_completed, $responsible_user_id, $current_sort_order++);
                            if (!$stmt_insert_item->execute()) throw new Exception("خطا در ذخیره آیتم '".$item_name."': ".$stmt_insert_item->error);
                        }
                    }
                }
                return $current_sort_order;
            };

            $next_sort_order = $process_items($posted_items_before, 'before', 0);
            $process_items($posted_items_after, 'after', $next_sort_order);

            $stmt_insert_item->close();
            $conn->commit();
            $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'چک‌لیست با موفقیت ذخیره شد.'];
            regenerate_csrf_token('inservice_checklists_action_' . $event_id_for_checklist);
            header("Location: checklists.php?event_id=" . $event_id_for_checklist); exit;

        } catch (Exception $e) { $conn->rollback(); $errors_chk[] = $e->getMessage(); }
    }
    $csrf_token_checklists = regenerate_csrf_token('inservice_checklists_action_' . $event_id_for_checklist);
}

function render_checklist_item_row_html($type, $index, $item_data = [], $users_list = []) {
    $name = htmlspecialchars($item_data['ItemName'] ?? '');
    $due_date_val = $item_data['DueDate'] ? (new DateTime($item_data['DueDate']))->format('Y-m-d') : '';
    $completed_attr = !empty($item_data['IsCompleted']) ? 'checked' : '';
    $responsible_id_val = $item_data['ResponsibleUserID'] ?? '';
    $unique_id_prefix = $type . '_' . $index . '_' . uniqid(); // More unique IDs for template cloning

    $options_html = "<option value=''>-- انتخاب --</option>";
    foreach($users_list as $uid => $uname) { $selected_attr = ($responsible_id_val == $uid) ? 'selected' : ''; $options_html .= "<option value='{$uid}' {$selected_attr}>".htmlspecialchars($uname)."</option>"; }

    return <<<HTML
    <div class="checklist-item-row border-bottom pb-2 mb-2">
        <div class="form-group mb-1">
            <label for="{$unique_id_prefix}_name">عنوان آیتم <span class="text-danger">*</span></label>
            <input type="text" class="form-control form-control-sm" id="{$unique_id_prefix}_name" name="items_{$type}[{$index}][name]" value="{$name}" required>
        </div>
        <div class="form-row">
            <div class="form-group col-md-5 mb-1"> <label for="{$unique_id_prefix}_due_date" class="small">تاریخ سررسید</label><input type="text" class="form-control form-control-sm persian-date-picker-dynamic" id="{$unique_id_prefix}_due_date" name="items_{$type}[{$index}][due_date]" value="{$due_date_val}" placeholder="YYYY-MM-DD"></div>
            <div class="form-group col-md-5 mb-1"> <label for="{$unique_id_prefix}_responsible" class="small">مسئول</label><select class="form-control form-control-sm custom-select" id="{$unique_id_prefix}_responsible" name="items_{$type}[{$index}][responsible_user_id]">{$options_html}</select></div>
            <div class="form-group col-md-2 mb-1 d-flex align-items-end justify-content-between">
                <div class="form-check form-check-inline pt-1 ml-2"> <input type="checkbox" class="form-check-input" id="{$unique_id_prefix}_completed" name="items_{$type}[{$index}][completed]" value="1" {$completed_attr}><label class="form-check-label small" for="{$unique_id_prefix}_completed">انجام شد</label></div>
                <button type="button" class="btn btn-xs btn-danger remove-checklist-item" title="حذف آیتم">&times;</button>
            </div>
        </div>
    </div>
HTML;
}
?>
<div class="page-header"><h1>چک‌لیست رویداد: <?php echo htmlspecialchars($event_data_checklist['EventName'] ?? '...'); ?></h1><div class="page-header-actions"><a href="events.php" class="btn btn-secondary">بازگشت به رویدادها</a></div></div>

<?php if (isset($_SESSION['flash_message'])) { $flash_chk = $_SESSION['flash_message']; echo "<div class='alert alert-{$flash_chk['type']} alert-dismissible fade show'>{$flash_chk['text']}<button type='button' class='close' data-dismiss='alert'>&times;</button></div>"; unset($_SESSION['flash_message']);} ?>
<?php if (!empty($errors_chk)): ?> <div class="alert alert-danger"><ul><?php foreach ($errors_chk as $err_c_item): ?><li><?php echo htmlspecialchars($err_c_item); ?></li><?php endforeach; ?></ul></div> <?php endif; ?>

<form action="checklists.php?event_id=<?php echo $event_id_for_checklist; ?>" method="POST" id="checklistForm">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_checklists; ?>">
    <input type="hidden" name="event_id" value="<?php echo $event_id_for_checklist; ?>">
    <div class="row">
        <div class="col-md-6 mb-4"><div class="card shadow-sm"><div class="card-header d-flex justify-content-between align-items-center"><h5 class="mb-0 card-title-text">قبل از برگزاری</h5><button type="button" class="btn btn-sm btn-success add-checklist-item" data-type="before">افزودن</button></div><div class="card-body checklist-items-container" id="itemsBeforeContainer">
            <?php if(!empty($checklist_items_db['before'])): foreach($checklist_items_db['before'] as $idx_b => $item_b_db): echo render_checklist_item_row_html('before', $idx_b, $item_b_db, $available_responsible_users); endforeach; else: ?><p class="text-muted no-items-yet small">آیتمی اضافه نشده.</p><?php endif; ?>
        </div></div></div>
        <div class="col-md-6 mb-4"><div class="card shadow-sm"><div class="card-header d-flex justify-content-between align-items-center"><h5 class="mb-0 card-title-text">بعد از برگزاری</h5><button type="button" class="btn btn-sm btn-success add-checklist-item" data-type="after">افزودن</button></div><div class="card-body checklist-items-container" id="itemsAfterContainer">
            <?php if(!empty($checklist_items_db['after'])): foreach($checklist_items_db['after'] as $idx_a => $item_a_db): echo render_checklist_item_row_html('after', $idx_a, $item_a_db, $available_responsible_users); endforeach; else: ?><p class="text-muted no-items-yet small">آیتمی اضافه نشده.</p><?php endif; ?>
        </div></div></div>
    </div>
    <div class="form-actions mt-3 text-center"><button type="submit" name="submit_checklists" class="btn btn-primary btn-lg">ذخیره تغییرات چک‌لیست</button></div>
</form>

<template id="checklistItemTemplate"> <?php echo render_checklist_item_row_html('ITEM_TYPE', 'ITEM_INDEX', [], $available_responsible_users); ?> </template>

<link rel="stylesheet" href="https://unpkg.com/persian-datepicker@latest/dist/css/persian-datepicker.min.css"/>
<script src="https://unpkg.com/persian-datepicker@latest/dist/js/persian-datepicker.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const templateHtml = document.getElementById('checklistItemTemplate').innerHTML;
    let itemCounters = {
        before: <?php echo count($checklist_items_db['before'] ?? []); ?>,
        after: <?php echo count($checklist_items_db['after'] ?? []); ?>
    };

    function initDatepicker(element) {
        if (element && !element.classList.contains('hasDatepicker')) { // Avoid re-init
            new persianDatepicker(element, { девочка: 'YYYY-MM-DD', autoClose: true, observer: true, calendar:{ persian: { locale: 'fa' } }, toolbox: { calendarSwitch:{ enabled: false } } });
            element.classList.add('hasDatepicker');
        }
    }
    document.querySelectorAll(".persian-date-picker-dynamic").forEach(initDatepicker);

    document.querySelectorAll('.add-checklist-item').forEach(button => {
        button.addEventListener('click', function() {
            const type = this.dataset.type;
            const container = document.getElementById('items' + (type.charAt(0).toUpperCase() + type.slice(1)) + 'Container');
            const noItemsMsg = container.querySelector('.no-items-yet');
            if(noItemsMsg) noItemsMsg.remove();

            const index = itemCounters[type]++;
            let newItemHtml = templateHtml.replace(/ITEM_TYPE/g, type).replace(/ITEM_INDEX/g, index);

            const div = document.createElement('div');
            div.innerHTML = newItemHtml.trim();
            const newItemRow = div.firstChild;
            container.appendChild(newItemRow);

            newItemRow.querySelectorAll(".persian-date-picker-dynamic").forEach(initDatepicker);
        });
    });
    document.addEventListener('click', function(e) { if (e.target && e.target.classList.contains('remove-checklist-item')) { e.target.closest('.checklist-item-row').remove(); }});
    document.querySelectorAll('.alert .close').forEach(function(button){button.addEventListener('click', function(event){event.target.closest('.alert').style.display = 'none';});});
});
</script>
<style>.btn-xs{padding: .1rem .3rem; font-size: .75rem;} .checklist-item-row label:not(.form-check-label){font-size:0.85em; margin-bottom: .2rem !important;} .checklist-item-row .form-control-sm {height: calc(1.5em + .5rem + 2px); padding: .25rem .5rem; font-size: .875rem;}</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
