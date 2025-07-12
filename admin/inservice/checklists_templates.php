<?php
require_once __DIR__ . '/../includes/header.php';

$action = $_GET['action'] ?? 'list'; // list, create, edit
$template_id = isset($_GET['template_id']) ? (int)$_GET['template_id'] : 0;

$templates = [];
$template_data_for_form = null; // For pre-filling create/edit form
$form_errors = [];
$page_title = "مدیریت قالب‌های چک‌لیست ضمن خدمت";

// CSRF Tokens
$csrf_token_name_form = 'inservice_checklist_template_form';
$csrf_token_form_val = generate_csrf_token($csrf_token_name_form);
$csrf_token_name_delete = 'inservice_checklist_template_delete';
$csrf_token_delete_val = generate_csrf_token($csrf_token_name_delete);

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_template'])) { // Create or Update Template
        if (!verify_csrf_token($_POST['csrf_token'] ?? '', $csrf_token_name_form)) {
            $form_errors['csrf'] = "خطای CSRF. لطفاً صفحه را رفرش کنید.";
        } else {
            $csrf_token_form_val = regenerate_csrf_token($csrf_token_name_form);

            $template_id_posted = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
            $template_name = sanitize_input($_POST['template_name'] ?? '');
            $template_description = sanitize_input($_POST['template_description'] ?? '');
            $items_posted_raw = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : [];

            $items_for_db = [];
            foreach ($items_posted_raw as $item_text_raw) {
                $sanitized_item = sanitize_input(trim($item_text_raw));
                if (!empty($sanitized_item)) {
                    // For now, items are simple text. Could be extended to {text: "...", default_status: "pending", details: ""}
                    $items_for_db[] = ['text' => $sanitized_item];
                }
            }
            $items_json_for_db = !empty($items_for_db) ? json_encode($items_for_db, JSON_UNESCAPED_UNICODE) : '[]';

            // Repopulate form data for display in case of error
            $template_data_for_form = $_POST;
            $template_data_for_form['items_for_form'] = $items_posted_raw; // Keep raw items for form display

            // Validation
            if (empty($template_name)) $form_errors['template_name'] = "نام قالب الزامی است.";
            if (empty($items_for_db)) $form_errors['items'] = "قالب باید حداقل شامل یک آیتم معتبر باشد.";
            else {
                foreach($items_for_db as $idx => $item) { // Validate sanitized items
                    if(mb_strlen($item['text']) > 255) $form_errors['item_'.($idx+1)] = "متن آیتم شماره ".($idx+1)." طولانی تر از 255 کاراکتر است.";
                }
            }

            if (empty($form_errors)) {
                if ($conn) {
                    if ($template_id_posted > 0) { // Update
                        $stmt = $conn->prepare("UPDATE InserviceChecklistTemplates SET TemplateName = ?, Description = ?, ItemsJSON = ?, UpdatedAt = NOW() WHERE TemplateID = ?");
                        if ($stmt) {
                            $stmt->bind_param("sssi", $template_name, $template_description, $items_json_for_db, $template_id_posted);
                            if ($stmt->execute()) { $_SESSION['action_success'] = "قالب بروزرسانی شد."; header("Location: checklists_templates.php"); exit; }
                            else $form_errors['db'] = "خطا در بروزرسانی: " . $stmt->error;
                            $stmt->close();
                        } else $form_errors['db'] = "خطای آماده سازی بروزرسانی: " . $conn->error;
                    } else { // Create
                        $stmt = $conn->prepare("INSERT INTO InserviceChecklistTemplates (TemplateName, Description, ItemsJSON, CreatedByUserID, CreatedAt) VALUES (?, ?, ?, ?, NOW())");
                        if ($stmt) {
                            $current_admin_id_tpl = get_current_user_id();
                            $stmt->bind_param("sssi", $template_name, $template_description, $items_json_for_db, $current_admin_id_tpl);
                            if ($stmt->execute()) { $_SESSION['action_success'] = "قالب ایجاد شد."; header("Location: checklists_templates.php"); exit; }
                            else $form_errors['db'] = "خطا در ایجاد: " . $stmt->error;
                            $stmt->close();
                        } else $form_errors['db'] = "خطای آماده سازی ایجاد: " . $conn->error;
                    }
                } else $form_errors['db'] = "عدم اتصال به پایگاه داده.";
            }
            $action = ($template_id_posted > 0) ? 'edit' : 'create'; // Stay on form if error
        }
    } elseif (isset($_POST['delete_template_confirmed'])) { // Delete action
         if (!verify_csrf_token($_POST['csrf_token_delete_modal_tpl'] ?? '', $csrf_token_name_delete)) {
            $_SESSION['action_error'] = "خطای CSRF هنگام حذف.";
        } else {
            $csrf_token_delete_val = regenerate_csrf_token($csrf_token_name_delete);
            $template_id_to_delete = (int)($_POST['template_id_to_delete_confirmed'] ?? 0);
            if ($template_id_to_delete > 0 && $conn) {
                // Check if template is used by any EventChecklists before deleting
                $stmt_check_usage = $conn->prepare("SELECT COUNT(*) as count FROM InserviceEventChecklists WHERE TemplateID = ?");
                if($stmt_check_usage){
                    $stmt_check_usage->bind_param("i", $template_id_to_delete);
                    $stmt_check_usage->execute();
                    $usage_count = $stmt_check_usage->get_result()->fetch_assoc()['count'] ?? 0;
                    $stmt_check_usage->close();
                    if($usage_count > 0){
                        $_SESSION['action_error'] = "امکان حذف این قالب وجود ندارد زیرا توسط ". $usage_count ." چک‌لیست رویداد فعال استفاده می‌شود.";
                    } else {
                         $stmt_del_tpl = $conn->prepare("DELETE FROM InserviceChecklistTemplates WHERE TemplateID = ?");
                        if ($stmt_del_tpl) {
                            $stmt_del_tpl->bind_param("i", $template_id_to_delete);
                            if ($stmt_del_tpl->execute()) {
                                 $_SESSION['action_success'] = ($stmt_del_tpl->affected_rows > 0) ? "قالب چک‌لیست حذف شد." : "قالب یافت نشد.";
                            } else $_SESSION['action_error'] = "خطا در حذف قالب: " . $stmt_del_tpl->error;
                            $stmt_del_tpl->close();
                        } else $_SESSION['action_error'] = "خطای آماده سازی حذف: " . $conn->error;
                    }
                } else {
                     $_SESSION['action_error'] = "خطا در بررسی وابستگی قالب: " . $conn->error;
                }
            } else $_SESSION['action_error'] = "شناسه قالب برای حذف نامعتبر.";
        }
        header("Location: checklists_templates.php"); exit;
    }
}


// Fetch data for list or edit form (if not a POST request with errors)
if ($conn) {
    if ($action === 'list') {
        $page_title = "مدیریت قالب‌های چک‌لیست";
        $result_list = $conn->query("SELECT TemplateID, TemplateName, Description, CreatedAt FROM InserviceChecklistTemplates ORDER BY TemplateName ASC");
        if ($result_list) {
            while ($row = $result_list->fetch_assoc()) $templates[] = $row;
        } else $form_errors['db_list'] = "خطا در بارگذاری لیست: " . $conn->error;
    } elseif ($action === 'edit' && $template_id > 0 && !$template_data_for_form) {
        $page_title = "ویرایش قالب چک‌لیست";
        $stmt_tpl = $conn->prepare("SELECT TemplateID, TemplateName, Description, ItemsJSON FROM InserviceChecklistTemplates WHERE TemplateID = ?");
        if ($stmt_tpl) {
            $stmt_tpl->bind_param("i", $template_id); $stmt_tpl->execute();
            $result_tpl = $stmt_tpl->get_result();
            if ($data = $result_tpl->fetch_assoc()) {
                $template_data_for_form = $data;
                $items_from_db = json_decode($data['ItemsJSON'] ?? '[]', true);
                // Ensure items_for_form is an array of strings (item texts)
                $template_data_for_form['items_for_form'] = array_map(function($item_obj){ return $item_obj['text'] ?? ''; }, $items_from_db ?: []);
                if(empty($template_data_for_form['items_for_form'])) $template_data_for_form['items_for_form'] = ['']; // Ensure at least one input
            } else { $_SESSION['action_error'] = "قالب یافت نشد."; header("Location: checklists_templates.php"); exit; }
            $stmt_tpl->close();
        } else $form_errors['db_load'] = "خطا در بارگذاری قالب: " . $conn->error;
    } elseif ($action === 'create' && !$template_data_for_form) {
        $page_title = "ایجاد قالب چک‌لیست جدید";
        $template_data_for_form = ['TemplateName' => '', 'Description' => '', 'items_for_form' => ['']];
    }
} else {
    $form_errors['db_connection'] = "خطا در اتصال به پایگاه داده.";
}

?>

<div class="page-header">
    <h1><?php echo $page_title; ?></h1>
    <?php if ($action === 'list'): ?>
    <div class="page-header-actions">
        <a href="?action=create" class="btn btn-primary"><em class="bi bi-plus-square-dotted icon"></em> ایجاد قالب جدید</a>
        <a href="index.php" class="btn btn-outline-secondary ms-2"><em class="bi bi-house-door icon"></em> بازگشت به داشبورد ضمن خدمت</a>
    </div>
    <?php else: ?>
    <div class="page-header-actions">
        <a href="checklists_templates.php" class="btn btn-secondary"><em class="bi bi-arrow-right-circle icon"></em> بازگشت به لیست قالب‌ها</a>
    </div>
    <?php endif; ?>
</div>

<?php if (isset($_SESSION['action_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $_SESSION['action_success']; unset($_SESSION['action_success']); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
<?php endif; ?>
<?php if (!empty($form_errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>خطا:</strong>
        <ul class="mb-0 ps-3">
            <?php foreach ($form_errors as $error_msg): echo "<li>" . htmlspecialchars($error_msg) . "</li>"; endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>


<?php if ($action === 'list'): ?>
    <div class="card">
        <div class="card-body">
            <?php if (empty($templates)): ?>
                <p class="text-center text-muted py-3">هیچ قالب چک‌لیستی تعریف نشده است. <a href="?action=create">یک قالب جدید ایجاد کنید</a>.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>نام قالب</th>
                            <th>توضیحات</th>
                            <th>تاریخ ایجاد</th>
                            <th class="actions-column">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($templates as $idx => $tpl): ?>
                        <tr>
                            <td><?php echo $idx + 1; ?></td>
                            <td><a href="?action=edit&template_id=<?php echo $tpl['TemplateID']; ?>"><?php echo htmlspecialchars($tpl['TemplateName']); ?></a></td>
                            <td><?php echo htmlspecialchars(mb_substr($tpl['Description'] ?? '', 0, 100) . (mb_strlen($tpl['Description'] ?? '') > 100 ? '...' : '')); ?></td>
                            <td><?php echo to_jalali($tpl['CreatedAt'], 'yyyy/MM/dd'); ?></td>
                            <td class="actions-cell">
                                <a href="?action=edit&template_id=<?php echo $tpl['TemplateID']; ?>" class="btn btn-sm btn-outline-info" title="ویرایش"><em class="bi bi-pencil-square"></em></a>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-delete-template" data-template-id="<?php echo $tpl['TemplateID']; ?>" data-template-name="<?php echo htmlspecialchars($tpl['TemplateName']); ?>" title="حذف"><em class="bi bi-trash3"></em></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($action === 'create' || $action === 'edit'): ?>
    <div class="card">
        <div class="card-body">
            <form method="POST" action="checklists_templates.php<?php echo ($action === 'edit' && $template_id) ? '?action=edit&template_id='.$template_id : '?action=create'; ?>" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_form_val; ?>">
                <?php if ($action === 'edit' && $template_id): ?>
                    <input type="hidden" name="template_id" value="<?php echo $template_id; ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label for="template_name_input" class="form-label">نام قالب <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?php echo isset($form_errors['template_name']) ? 'is-invalid' : ''; ?>" id="template_name_input" name="template_name" value="<?php echo htmlspecialchars($template_data_for_form['TemplateName'] ?? ''); ?>" required>
                    <?php if(isset($form_errors['template_name'])):?><div class="invalid-feedback"><?php echo $form_errors['template_name'];?></div><?php endif;?>
                </div>
                <div class="mb-3">
                    <label for="template_description_input" class="form-label">توضیحات قالب</label>
                    <textarea class="form-control" id="template_description_input" name="template_description" rows="3"><?php echo htmlspecialchars($template_data_for_form['Description'] ?? ''); ?></textarea>
                </div>

                <hr>
                <h5 class="mb-3">آیتم‌های چک‌لیست <span class="text-danger">*</span></h5>
                <div id="checklist-items-container-tpl">
                    <?php
                    $items_to_display_form = $template_data_for_form['items_for_form'] ?? [''];
                    if (empty($items_to_display_form)) $items_to_display_form = [''];

                    foreach ($items_to_display_form as $item_index_form => $item_text_form):
                        $item_error_key_form = 'item_' . ($item_index_form + 1);
                    ?>
                    <div class="row gx-2 mb-2 checklist-item-row-tpl align-items-center">
                        <div class="col">
                            <input type="text" name="items[]" class="form-control <?php echo isset($form_errors[$item_error_key_form]) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($item_text_form); ?>" placeholder="متن آیتم چک‌لیست (مثال: هماهنگی مکان انجام شد)">
                            <?php if(isset($form_errors[$item_error_key_form])):?><div class="invalid-feedback d-block"><?php echo $form_errors[$item_error_key_form];?></div><?php endif;?>
                        </div>
                        <div class="col-auto">
                            <button type="button" class="btn btn-sm btn-outline-danger remove-item-btn-tpl" title="حذف آیتم" <?php echo (count($items_to_display_form) <= 1) ? 'style="display:none;"' : ''; ?>><em class="bi bi-x-lg"></em></button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                 <?php if(isset($form_errors['items'])):?><div class="text-danger small mb-2"><?php echo $form_errors['items'];?></div><?php endif;?>

                <button type="button" id="add-checklist-item-btn-tpl" class="btn btn-sm btn-outline-success mb-3"><em class="bi bi-plus-circle me-1"></em> افزودن آیتم دیگر</button>

                <div class="form-actions border-top pt-3">
                    <button type="submit" name="save_template" class="btn btn-success"><em class="bi bi-check-circle-fill icon"></em> ذخیره قالب</button>
                    <a href="checklists_templates.php" class="btn btn-outline-secondary">انصراف</a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Delete Confirmation Modal for Templates -->
<div class="modal fade" id="deleteTemplateModal" tabindex="-1" aria-labelledby="deleteTemplateModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="checklists_templates.php" id="deleteTemplateFormModal">
        <input type="hidden" name="csrf_token_delete_modal_tpl" id="csrf_token_delete_modal_tpl_input" value="">
        <input type="hidden" name="template_id_to_delete_confirmed" id="template_id_to_delete_modal_input">
        <div class="modal-header">
          <h5 class="modal-title" id="deleteTemplateModalLabel">تایید حذف قالب چک‌لیست</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          آیا از حذف قالب <strong id="templateNameToDeleteModal"></strong> مطمئن هستید؟ <br>
          <small class="text-danger">توجه: اگر این قالب توسط چک‌لیست‌های رویدادها در حال استفاده باشد، حذف نخواهد شد.</small>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
          <button type="submit" name="delete_template_confirmed" class="btn btn-danger">بله، حذف کن</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const itemsContainerTpl = document.getElementById('checklist-items-container-tpl');
    const addItemBtnTpl = document.getElementById('add-checklist-item-btn-tpl');

    function updateRemoveButtonsVisibilityTpl() {
        if (!itemsContainerTpl) return;
        const allRows = itemsContainerTpl.querySelectorAll('.checklist-item-row-tpl');
        allRows.forEach((row) => {
            let removeBtn = row.querySelector('.remove-item-btn-tpl');
            if (allRows.length <= 1) {
                if (removeBtn) removeBtn.style.display = 'none';
            } else {
                if (removeBtn) removeBtn.style.display = '';
            }
        });
    }

    if (addItemBtnTpl && itemsContainerTpl) {
        addItemBtnTpl.addEventListener('click', function() {
            const newItemRow = document.createElement('div');
            newItemRow.className = 'row gx-2 mb-2 checklist-item-row-tpl align-items-center';
            newItemRow.innerHTML = `
                <div class="col">
                    <input type="text" name="items[]" class="form-control" placeholder="متن آیتم چک‌لیست (مثال: هماهنگی مکان انجام شد)">
                </div>
                <div class="col-auto">
                    <button type="button" class="btn btn-sm btn-outline-danger remove-item-btn-tpl" title="حذف آیتم"><em class="bi bi-x-lg"></em></button>
                </div>
            `;
            itemsContainerTpl.appendChild(newItemRow);
            newItemRow.querySelector('.remove-item-btn-tpl').addEventListener('click', function() {
                this.closest('.checklist-item-row-tpl').remove();
                updateRemoveButtonsVisibilityTpl();
            });
            updateRemoveButtonsVisibilityTpl();
        });
    }

    if(itemsContainerTpl){
        itemsContainerTpl.addEventListener('click', function(event) {
            if (event.target.closest('.remove-item-btn-tpl')) {
                event.target.closest('.checklist-item-row-tpl').remove();
                updateRemoveButtonsVisibilityTpl();
            }
        });
        updateRemoveButtonsVisibilityTpl(); // Initial check
    }

    // Handle delete template button click to populate modal
    $('.btn-delete-template').on('click', function() {
        const templateId = $(this).data('template-id');
        const templateName = $(this).data('template-name');
        $('#template_id_to_delete_modal_input').val(templateId);
        $('#templateNameToDeleteModal').text(templateName);
        $('#csrf_token_delete_modal_tpl_input').val('<?php echo $csrf_token_delete_val; ?>');
        var deleteModalTpl = new bootstrap.Modal(document.getElementById('deleteTemplateModal'));
        deleteModalTpl.show();
    });
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
