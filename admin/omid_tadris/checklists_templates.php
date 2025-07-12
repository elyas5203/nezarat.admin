<?php
require_once __DIR__ . '/../includes/header.php'; // General admin header

$action_ot_tpl = $_GET['action'] ?? 'list'; // list, create, edit
$template_id_ot = isset($_GET['template_id']) ? (int)$_GET['template_id'] : 0;

$ot_templates = []; // For list view
$ot_template_data_for_form = null; // For pre-filling create/edit form
$form_errors_ot_tpl = []; // Specific errors for this module/page
$page_title_ot_tpl = "مدیریت قالب‌های چک‌لیست امید تدریس";

// CSRF Tokens
$csrf_token_name_ot_tpl_form = 'omid_tadris_checklist_template_form';
$csrf_token_ot_tpl_form_val = generate_csrf_token($csrf_token_name_ot_tpl_form);
$csrf_token_name_ot_tpl_delete = 'omid_tadris_checklist_template_delete';
$csrf_token_ot_tpl_delete_val = generate_csrf_token($csrf_token_name_ot_tpl_delete);

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_ot_template'])) { // Create or Update Template
        if (!verify_csrf_token($_POST['csrf_token'] ?? '', $csrf_token_name_ot_tpl_form)) {
            $form_errors_ot_tpl['csrf'] = "خطای CSRF.";
        } else {
            $csrf_token_ot_tpl_form_val = regenerate_csrf_token($csrf_token_name_ot_tpl_form);

            $template_id_posted = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
            $template_name = sanitize_input($_POST['template_name'] ?? '');
            $template_description = sanitize_input($_POST['template_description'] ?? '');
            $items_posted_raw = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : [];

            $items_for_db = [];
            foreach ($items_posted_raw as $item_text_raw) {
                $sanitized_item = sanitize_input(trim($item_text_raw));
                if (!empty($sanitized_item)) {
                    $items_for_db[] = ['text' => $sanitized_item];
                }
            }
            $items_json_for_db = !empty($items_for_db) ? json_encode($items_for_db, JSON_UNESCAPED_UNICODE) : '[]';

            $ot_template_data_for_form = $_POST;
            $ot_template_data_for_form['items_for_form'] = $items_posted_raw;

            if (empty($template_name)) $form_errors_ot_tpl['template_name'] = "نام قالب الزامی است.";
            if (empty($items_for_db)) $form_errors_ot_tpl['items'] = "قالب باید حداقل یک آیتم معتبر داشته باشد.";
            else { foreach($items_for_db as $idx => $item) { if(mb_strlen($item['text']) > 255) $form_errors_ot_tpl['item_'.($idx+1)] = "متن آیتم ".($idx+1)." طولانی است."; } }

            if (empty($form_errors_ot_tpl)) {
                if ($conn) {
                    if ($template_id_posted > 0) { // Update
                        $stmt = $conn->prepare("UPDATE OmidTadrisChecklistTemplates SET TemplateName = ?, Description = ?, ItemsJSON = ?, UpdatedAt = NOW() WHERE TemplateID = ?");
                        if($stmt){
                            $stmt->bind_param("sssi", $template_name, $template_description, $items_json_for_db, $template_id_posted);
                            if ($stmt->execute()) { $_SESSION['action_success_ot'] = "قالب امید تدریس بروزرسانی شد."; header("Location: checklists_templates.php"); exit; }
                            else $form_errors_ot_tpl['db'] = "خطا در بروزرسانی: " . $stmt->error;
                            $stmt->close();
                        } else $form_errors_ot_tpl['db'] = "خطای آماده سازی بروزرسانی: " . $conn->error;
                    } else { // Create
                        $stmt = $conn->prepare("INSERT INTO OmidTadrisChecklistTemplates (TemplateName, Description, ItemsJSON, CreatedByUserID, CreatedAt) VALUES (?, ?, ?, ?, NOW())");
                        if($stmt){
                            $current_admin_id_ot_tpl = get_current_user_id();
                            $stmt->bind_param("sssi", $template_name, $template_description, $items_json_for_db, $current_admin_id_ot_tpl);
                            if ($stmt->execute()) { $_SESSION['action_success_ot'] = "قالب امید تدریس ایجاد شد."; header("Location: checklists_templates.php"); exit; }
                            else $form_errors_ot_tpl['db'] = "خطا در ایجاد: " . $stmt->error;
                            $stmt->close();
                        } else $form_errors_ot_tpl['db'] = "خطای آماده سازی ایجاد: " . $conn->error;
                    }
                } else $form_errors_ot_tpl['db'] = "عدم اتصال به پایگاه داده.";
            }
            $action_ot_tpl = ($template_id_posted > 0) ? 'edit' : 'create';
        }
    } elseif (isset($_POST['delete_ot_template_confirmed'])) { // Delete
        if (!verify_csrf_token($_POST['csrf_token_delete_modal_ot_tpl'] ?? '', $csrf_token_name_ot_tpl_delete)) {
            $_SESSION['action_error_ot'] = "خطای CSRF.";
        } else {
            $csrf_token_ot_tpl_delete_val = regenerate_csrf_token($csrf_token_name_ot_tpl_delete);
            $template_id_to_delete = (int)($_POST['template_id_to_delete_confirmed'] ?? 0);
            if ($template_id_to_delete > 0 && $conn) {
                // TODO: Dependency check for OmidTadrisEventChecklists
                $stmt_del = $conn->prepare("DELETE FROM OmidTadrisChecklistTemplates WHERE TemplateID = ?");
                if($stmt_del){
                    $stmt_del->bind_param("i", $template_id_to_delete);
                    if ($stmt_del->execute()) { $_SESSION['action_success_ot'] = ($stmt_del->affected_rows > 0) ? "قالب حذف شد." : "قالب یافت نشد.";}
                    else {
                        if ($conn->errno == 1451) $_SESSION['action_error_ot'] = "این قالب توسط چک‌لیست‌های رویداد استفاده می‌شود و قابل حذف نیست.";
                        else $_SESSION['action_error_ot'] = "خطا در حذف: " . $stmt_del->error;
                    }
                    $stmt_del->close();
                } else $_SESSION['action_error_ot'] = "خطای آماده سازی حذف: " . $conn->error;
            } else $_SESSION['action_error_ot'] = "شناسه نامعتبر برای حذف.";
        }
        header("Location: checklists_templates.php"); exit;
    }
}

// Fetch data for display
if ($conn) {
    if ($action_ot_tpl === 'list') {
        $page_title_ot_tpl = "مدیریت قالب‌های چک‌لیست امید تدریس";
        $result_list_ot_tpl = $conn->query("SELECT TemplateID, TemplateName, Description FROM OmidTadrisChecklistTemplates ORDER BY TemplateName ASC");
        if ($result_list_ot_tpl) { while ($row = $result_list_ot_tpl->fetch_assoc()) $ot_templates[] = $row; }
        else $form_errors_ot_tpl['db_list'] = "خطا در بارگذاری لیست: " . $conn->error;
    } elseif (($action_ot_tpl === 'edit' || $action_ot_tpl === 'create') && !$ot_template_data_for_form) {
        if ($action_ot_tpl === 'edit' && $template_id_ot > 0) {
            $page_title_ot_tpl = "ویرایش قالب چک‌لیست امید تدریس";
            $stmt_ot_tpl = $conn->prepare("SELECT * FROM OmidTadrisChecklistTemplates WHERE TemplateID = ?");
            if($stmt_ot_tpl){
                $stmt_ot_tpl->bind_param("i", $template_id_ot); $stmt_ot_tpl->execute();
                $result_ot_tpl = $stmt_ot_tpl->get_result();
                if ($data = $result_ot_tpl->fetch_assoc()) {
                    $ot_template_data_for_form = $data;
                    $items_from_db = json_decode($data['ItemsJSON'] ?? '[]', true);
                    $ot_template_data_for_form['items_for_form'] = array_map(function($item){ return $item['text'] ?? ''; }, $items_from_db ?: []);
                    if(empty($ot_template_data_for_form['items_for_form'])) $ot_template_data_for_form['items_for_form'] = [''];
                } else { $_SESSION['action_error_ot'] = "قالب یافت نشد."; header("Location: checklists_templates.php"); exit; }
                $stmt_ot_tpl->close();
            } else $form_errors_ot_tpl['db_load'] = "خطا در بارگذاری: " . $conn->error;
        } else { // create
            $page_title_ot_tpl = "ایجاد قالب چک‌لیست جدید (امید تدریس)";
            $ot_template_data_for_form = ['TemplateName' => '', 'Description' => '', 'items_for_form' => ['']];
        }
    }
} else {
    $form_errors_ot_tpl['db_connection'] = "خطا در اتصال به پایگاه داده.";
}
?>
<div class="page-header">
    <h1><?php echo $page_title_ot_tpl; ?></h1>
    <div class="page-header-actions">
        <a href="checklists_templates.php" class="btn btn-<?php echo ($action_ot_tpl === 'list' ? 'primary' : 'secondary'); ?>"><em class="bi bi-list-ul icon"></em> <?php echo ($action_ot_tpl === 'list' ? 'ایجاد قالب جدید' : 'لیست قالب‌ها'); ?></a>
        <a href="index.php" class="btn btn-outline-secondary ms-2"><em class="bi bi-house-door icon"></em> داشبورد امید تدریس</a>
    </div>
</div>

<?php if (isset($_SESSION['action_success_ot'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $_SESSION['action_success_ot']; unset($_SESSION['action_success_ot']); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
<?php endif; ?>
<?php if (!empty($form_errors_ot_tpl)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert"><strong>خطا:</strong><ul class="mb-0 ps-3"><?php foreach ($form_errors_ot_tpl as $err): echo "<li>".htmlspecialchars($err)."</li>"; endforeach; ?></ul><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
<?php endif; ?>


<?php if ($action_ot_tpl === 'list'): ?>
    <div class="card"><div class="card-body">
        <?php if(empty($ot_templates)): ?><p class="text-center text-muted py-3">هیچ قالبی تعریف نشده. <a href="?action=create">ایجاد کنید</a>.</p>
        <?php else: ?><div class="table-responsive"><table class="table table-hover">
            <thead class="table-light"><tr><th>#</th><th>نام قالب</th><th>توضیحات</th><th class="actions-column">عملیات</th></tr></thead>
            <tbody><?php foreach($ot_templates as $idx => $t): ?>
                <tr><td><?php echo $idx+1;?></td><td><a href="?action=edit&template_id=<?php echo $t['TemplateID'];?>"><?php echo htmlspecialchars($t['TemplateName']);?></a></td><td><?php echo htmlspecialchars(mb_substr($t['Description']??'',0,100).(mb_strlen($t['Description']??'')>100?'...':''));?></td>
                <td class="actions-cell">
                    <a href="?action=edit&template_id=<?php echo $t['TemplateID'];?>" class="btn btn-sm btn-outline-info" title="ویرایش"><em class="bi bi-pencil-square"></em></a>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-delete-ot-template" data-template-id="<?php echo $t['TemplateID'];?>" data-template-name="<?php echo htmlspecialchars($t['TemplateName']);?>"><em class="bi bi-trash3"></em></button>
                </td></tr>
            <?php endforeach; ?></tbody>
        </table></div><?php endif; ?>
    </div></div>
<?php elseif ($action_ot_tpl === 'create' || $action_ot_tpl === 'edit'): ?>
    <div class="card"><div class="card-body">
        <form method="POST" action="checklists_templates.php<?php echo ($action_ot_tpl==='edit'&&$template_id_ot)?'?action=edit&template_id='.$template_id_ot:'?action=create';?>" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_ot_tpl_form_val; ?>">
            <?php if($action_ot_tpl==='edit'&&$template_id_ot):?><input type="hidden" name="template_id" value="<?php echo $template_id_ot;?>"><?php endif;?>
            <div class="mb-3"><label for="ot_tpl_name" class="form-label">نام قالب <span class="text-danger">*</span></label><input type="text" class="form-control <?php echo isset($form_errors_ot_tpl['template_name'])?'is-invalid':'';?>" id="ot_tpl_name" name="template_name" value="<?php echo htmlspecialchars($ot_template_data_for_form['TemplateName']??'');?>" required><?php if(isset($form_errors_ot_tpl['template_name'])):?><div class="invalid-feedback"><?php echo $form_errors_ot_tpl['template_name'];?></div><?php endif;?></div>
            <div class="mb-3"><label for="ot_tpl_desc" class="form-label">توضیحات</label><textarea class="form-control" id="ot_tpl_desc" name="template_description" rows="3"><?php echo htmlspecialchars($ot_template_data_for_form['Description']??'');?></textarea></div>
            <hr><h5 class="mb-3">آیتم‌های چک‌لیست <span class="text-danger">*</span></h5>
            <div id="checklist-items-container-ot-tpl">
                <?php $items_form = $ot_template_data_for_form['items_for_form']??['']; if(empty($items_form))$items_form=['']; foreach($items_form as $item_idx=>$item_txt): $item_err_k='item_'.($item_idx+1);?>
                <div class="row gx-2 mb-2 checklist-item-row-ot-tpl align-items-center">
                    <div class="col"><input type="text" name="items[]" class="form-control <?php echo isset($form_errors_ot_tpl[$item_err_k])?'is-invalid':'';?>" value="<?php echo htmlspecialchars($item_txt);?>" placeholder="متن آیتم"><?php if(isset($form_errors_ot_tpl[$item_err_k])):?><div class="invalid-feedback d-block"><?php echo $form_errors_ot_tpl[$item_err_k];?></div><?php endif;?></div>
                    <div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger remove-item-btn-ot-tpl" title="حذف آیتم"><em class="bi bi-x-lg"></em></button></div>
                </div><?php endforeach;?>
            </div>
            <?php if(isset($form_errors_ot_tpl['items'])):?><div class="text-danger small mb-2"><?php echo $form_errors_ot_tpl['items'];?></div><?php endif;?>
            <button type="button" id="add-checklist-item-btn-ot-tpl" class="btn btn-sm btn-outline-success mb-3"><em class="bi bi-plus-circle me-1"></em> افزودن آیتم</button>
            <div class="form-actions border-top pt-3"><button type="submit" name="save_ot_template" class="btn btn-success"><em class="bi bi-check-circle-fill icon"></em> ذخیره</button><a href="checklists_templates.php" class="btn btn-outline-secondary">انصراف</a></div>
        </form>
    </div></div>
<?php endif; ?>

<div class="modal fade" id="deleteOTTemplateModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="checklists_templates.php" id="deleteOTTemplateFormModal">
    <input type="hidden" name="csrf_token_delete_modal_ot_tpl" id="csrf_token_delete_modal_ot_tpl_input_val" value="">
    <input type="hidden" name="template_id_to_delete_confirmed" id="template_id_to_delete_modal_ot_tpl_input">
    <div class="modal-header"><h5 class="modal-title">تایید حذف قالب</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">آیا از حذف قالب <strong id="templateNameToDeleteOTModal"></strong> مطمئن هستید؟</div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button><button type="submit" name="delete_ot_template_confirmed" class="btn btn-danger">حذف</button></div>
    </form></div></div></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const itemsContainerOT = document.getElementById('checklist-items-container-ot-tpl');
    const addItemBtnOT = document.getElementById('add-checklist-item-btn-ot-tpl');
    function updateRemoveButtonsOT() { if(!itemsContainerOT)return; const rows=itemsContainerOT.querySelectorAll('.checklist-item-row-ot-tpl'); rows.forEach(r=>{let btn=r.querySelector('.remove-item-btn-ot-tpl'); if(rows.length<=1){if(btn)btn.style.display='none';}else{if(btn)btn.style.display='';}}); }
    if(addItemBtnOT && itemsContainerOT){addItemBtnOT.addEventListener('click',function(){const nr=document.createElement('div');nr.className='row gx-2 mb-2 checklist-item-row-ot-tpl align-items-center';nr.innerHTML=`<div class="col"><input type="text" name="items[]" class="form-control" placeholder="متن آیتم"></div><div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger remove-item-btn-ot-tpl" title="حذف آیتم"><em class="bi bi-x-lg"></em></button></div>`;itemsContainerOT.appendChild(nr);nr.querySelector('.remove-item-btn-ot-tpl').addEventListener('click',function(){this.closest('.checklist-item-row-ot-tpl').remove();updateRemoveButtonsOT();});updateRemoveButtonsOT();});}
    if(itemsContainerOT){itemsContainerOT.addEventListener('click',function(e){if(e.target.closest('.remove-item-btn-ot-tpl')){e.target.closest('.checklist-item-row-ot-tpl').remove();updateRemoveButtonsOT();}});updateRemoveButtonsOT();}
    $('.btn-delete-ot-template').on('click', function(){$('#template_id_to_delete_modal_ot_tpl_input').val($(this).data('template-id'));$('#templateNameToDeleteOTModal').text($(this).data('template-name'));$('#csrf_token_delete_modal_ot_tpl_input_val').val('<?php echo $csrf_token_ot_tpl_delete_val; ?>');new bootstrap.Modal(document.getElementById('deleteOTTemplateModal')).show();});
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
