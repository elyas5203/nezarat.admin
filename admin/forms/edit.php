<?php
require_once __DIR__ . '/../includes/header.php';

$form_id_to_edit = isset($_GET['form_id']) ? (int)$_GET['form_id'] : 0;

$form_title = '';
$form_description = '';
$form_status = 'draft';
// JSON string of fields: fetched from DB on GET, or from POST on validation error
$form_fields_json_for_builder = '[]';
$form_errors = [];

if ($form_id_to_edit <= 0) {
    $_SESSION['action_error'] = 'شناسه فرم نامعتبر است.';
    header("Location: index.php");
    exit;
}

$csrf_token_name = 'edit_form_structure_' . $form_id_to_edit;
$csrf_token = generate_csrf_token($csrf_token_name);
$field_types_config = get_form_field_types_config();

// Fetch Form and Fields Data for Editing
if ($conn) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $stmt_form_data = $conn->prepare("SELECT Title, Description, Status FROM Forms WHERE FormID = ?");
        if ($stmt_form_data) {
            $stmt_form_data->bind_param("i", $form_id_to_edit);
            $stmt_form_data->execute();
            $result_form = $stmt_form_data->get_result();
            if ($form_db_data = $result_form->fetch_assoc()) {
                $form_title = $form_db_data['Title'];
                $form_description = $form_db_data['Description'];
                $form_status = $form_db_data['Status'];

                $stmt_fields_data = $conn->prepare(
                    "SELECT FieldLabel as label, FieldType as type, FieldOptions as options_json, IsRequired as required, Placeholder as placeholder, HelperText as helper_text, MinValue as min_value, MaxValue as max_value, MaxLength as max_length, FileTypes as file_types_str
                     FROM FormFields WHERE FormID = ? ORDER BY SortOrder ASC"
                );
                if ($stmt_fields_data) {
                    $stmt_fields_data->bind_param("i", $form_id_to_edit);
                    $stmt_fields_data->execute();
                    $result_fields = $stmt_fields_data->get_result();
                    $fields_array_for_json = [];
                    $field_id_counter = 0; // Simple counter for client-side unique IDs
                    while ($field_row = $result_fields->fetch_assoc()) {
                        $field_id_counter++;
                        $current_field_data = [
                            'id' => 'dbfield_' . $field_row['type'] . '_' . $field_id_counter . '_' . $form_id_to_edit,
                            'label' => $field_row['label'],
                            'type' => $field_row['type'],
                            'required' => (bool)$field_row['required'],
                            'placeholder' => $field_row['placeholder'] ?? '',
                            'helper_text' => $field_row['helper_text'] ?? '',
                            'options' => !empty($field_row['options_json']) ? (json_decode($field_row['options_json'], true) ?: []) : [],
                            'min_value' => $field_row['min_value'] !== null ? (int)$field_row['min_value'] : null,
                            'max_value' => $field_row['max_value'] !== null ? (int)$field_row['max_value'] : null,
                            'max_length' => $field_row['max_length'] !== null ? (int)$field_row['max_length'] : null,
                            'file_types' => !empty($field_row['file_types_str']) ? array_map('trim', explode(',', $field_row['file_types_str'])) : [],
                        ];
                        $fields_array_for_json[] = $current_field_data;
                    }
                    $form_fields_json_for_builder = json_encode($fields_array_for_json, JSON_UNESCAPED_UNICODE);
                    $stmt_fields_data->close();
                } else {
                    $form_errors['db_error'] = "خطا در بارگذاری فیلدهای فرم: " . $conn->error;
                }
            } else {
                $_SESSION['action_error'] = 'فرم با شناسه ' . htmlspecialchars($form_id_to_edit) . ' یافت نشد.';
                header("Location: index.php");
                exit;
            }
            $stmt_form_data->close();
        } else {
            $form_errors['db_error'] = 'خطا در آماده سازی کوئری بارگذاری فرم: ' . $conn->error;
        }
    } else { // On POST, repopulate from POST data for display if validation fails
        $form_title = sanitize_input($_POST['form_title'] ?? '');
        $form_description = sanitize_input($_POST['form_description'] ?? '');
        $form_status = in_array($_POST['form_status'] ?? 'draft', ['draft', 'published', 'archived']) ? $_POST['form_status'] : 'draft';
        $form_fields_json_for_builder = $_POST['form_fields_json'] ?? '[]';
    }
} else {
    $form_errors['db_error'] = 'خطا در اتصال به پایگاه داده.';
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_form_structure'])) {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'], $csrf_token_name)) {
        $form_errors['csrf'] = 'خطای امنیتی CSRF.';
    } else {
        $csrf_token = regenerate_csrf_token($csrf_token_name);

        // Form general info already populated from POST handling section above
        $updated_fields_json = $_POST['form_fields_json'] ?? '[]'; // This is the current state from form builder
        $updated_fields_data = json_decode($updated_fields_json, true);

        // Validations (similar to create.php)
        if (empty($form_title)) $form_errors['form_title'] = "عنوان فرم نمی‌تواند خالی باشد.";
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($updated_fields_data)) {
            $form_errors['form_fields_json'] = "ساختار فیلدهای فرم نامعتبر است (خطای JSON).";
        } elseif (empty($updated_fields_data)) {
            $form_errors['form_fields_json'] = "فرم باید حداقل شامل یک فیلد باشد.";
        } else {
            foreach ($updated_fields_data as $index => $field) {
                if (empty(trim($field['label'] ?? ''))) {
                    $form_errors['field_'.($index+1).'_label'] = "برچسب فیلد شماره ".($index+1)." نمی‌تواند خالی باشد.";
                }
                if (in_array($field['type'] ?? '', ['select', 'radio', 'checkbox']) && empty($field['options'] ?? [])) {
                     $form_errors['field_'.($index+1).'_options'] = "گزینه‌ها برای فیلد انتخابی شماره ".($index+1)." (با برچسب: ".htmlspecialchars($field['label'] ?? 'بدون برچسب').") باید مشخص شوند.";
                }
                if (isset($field['label']) && mb_strlen($field['label']) > 255) $form_errors['field_'.($index+1).'_label_length'] = "برچسب فیلد ".($index+1)." طولانی است.";
            }
        }

        if (empty($form_errors)) {
            if ($conn) {
                $conn->begin_transaction();
                try {
                    $current_user_id = get_current_user_id();

                    $stmt_update_form = $conn->prepare("UPDATE Forms SET Title = ?, Description = ?, Status = ?, UpdatedAt = NOW(), UpdatedByUserID = ? WHERE FormID = ?");
                    if (!$stmt_update_form) throw new Exception("خطا در آماده سازی کوئری بروزرسانی فرم: " . $conn->error);
                    $stmt_update_form->bind_param("sssii", $form_title, $form_description, $form_status, $current_user_id, $form_id_to_edit);
                    if (!$stmt_update_form->execute()) throw new Exception("خطا در بروزرسانی فرم: " . $stmt_update_form->error);
                    $stmt_update_form->close();

                    $stmt_delete_fields = $conn->prepare("DELETE FROM FormFields WHERE FormID = ?");
                    if (!$stmt_delete_fields) throw new Exception("خطا در آماده سازی حذف فیلدهای قدیمی: " . $conn->error);
                    $stmt_delete_fields->bind_param("i", $form_id_to_edit);
                    if (!$stmt_delete_fields->execute()) throw new Exception("خطا در حذف فیلدهای قدیمی: " . $stmt_delete_fields->error);
                    $stmt_delete_fields->close();

                    $stmt_insert_field = $conn->prepare("INSERT INTO FormFields (FormID, FieldLabel, FieldType, FieldOptions, IsRequired, Placeholder, SortOrder, HelperText, MinValue, MaxValue, MaxLength, FileTypes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    if (!$stmt_insert_field) throw new Exception("خطا در آماده سازی کوئری فیلدها (ویرایش): " . $conn->error);

                    foreach ($updated_fields_data as $order => $field) {
                        $options_json = !empty($field['options']) ? json_encode($field['options'], JSON_UNESCAPED_UNICODE) : null;
                        $is_required = (bool)($field['required'] ?? false);
                        $placeholder = sanitize_input($field['placeholder'] ?? null);
                        $field_label = sanitize_input($field['label']);
                        $field_type = sanitize_input($field['type']);
                        $helper_text = sanitize_input($field['helper_text'] ?? null);
                        $min_val = isset($field['min_value']) && is_numeric($field['min_value']) ? (int)$field['min_value'] : null;
                        $max_val = isset($field['max_value']) && is_numeric($field['max_value']) ? (int)$field['max_value'] : null;
                        $max_len = isset($field['max_length']) && is_numeric($field['max_length']) ? (int)$field['max_length'] : null;
                        $file_types_str = isset($field['file_types']) && is_array($field['file_types']) ? implode(',', $field['file_types']) : null;

                        $stmt_insert_field->bind_param(
                            "isssissssss",
                            $form_id_to_edit, $field_label, $field_type, $options_json,
                            $is_required, $placeholder, $order, $helper_text,
                            $min_val, $max_val, $max_len, $file_types_str
                        );
                        if (!$stmt_insert_field->execute()) throw new Exception("خطا در ایجاد/بروزرسانی فیلد '" . htmlspecialchars($field_label) . "': " . $stmt_insert_field->error);
                    }
                    $stmt_insert_field->close();

                    $conn->commit();
                    $_SESSION['action_success'] = "فرم '" . htmlspecialchars($form_title) . "' با موفقیت بروزرسانی شد.";
                    header("Location: index.php");
                    exit;
                } catch (Exception $e) {
                    $conn->rollback();
                    $form_errors['db_error'] = "خطای پایگاه داده: " . $e->getMessage();
                }
            } else {
                 $form_errors['db_error'] = 'خطا در اتصال به پایگاه داده.';
            }
        }
         // If errors, $form_fields_json_for_builder is already set to the submitted JSON from POST handling at the top
    }
}
?>

<div class="page-header">
    <h1>ویرایش فرم: <?php echo htmlspecialchars($form_title ?: 'فرم بدون عنوان'); ?></h1>
     <div class="page-header-actions">
        <a href="index.php" class="btn btn-secondary">بازگشت به لیست</a>
        <a href="preview.php?form_id=<?php echo $form_id_to_edit; ?>" class="btn btn-outline-info" target="_blank">پیش‌نمایش فرم</a>
    </div>
</div>

<?php if (!empty($form_errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>خطا در بروزرسانی فرم:</strong>
        <ul class="mb-0">
            <?php foreach ($form_errors as $error_msg): ?>
                <li><?php echo htmlspecialchars($error_msg); ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?form_id=" . $form_id_to_edit; ?>" id="editFormBuilderForm">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="form_fields_json" id="form_fields_json_input_edit" value="<?php echo htmlspecialchars($form_fields_json_for_builder); ?>">

    <div class="card mb-4">
        <div class="card-header">اطلاعات کلی فرم</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label for="form_title_edit_input" class="form-label">عنوان فرم <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?php echo isset($form_errors['form_title']) ? 'is-invalid' : ''; ?>"
                           id="form_title_edit_input" name="form_title" value="<?php echo htmlspecialchars($form_title); ?>" required>
                    <?php if (isset($form_errors['form_title'])): ?><div class="invalid-feedback"><?php echo $form_errors['form_title']; ?></div><?php endif; ?>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="form_status_edit_select" class="form-label">وضعیت فرم</label>
                    <select class="form-select" id="form_status_edit_select" name="form_status">
                        <option value="draft" <?php echo ($form_status === 'draft') ? 'selected' : ''; ?>>پیش‌نویس</option>
                        <option value="published" <?php echo ($form_status === 'published') ? 'selected' : ''; ?>>منتشر شده</option>
                        <option value="archived" <?php echo ($form_status === 'archived') ? 'selected' : ''; ?>>بایگانی شده</option>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label for="form_description_edit_textarea" class="form-label">توضیحات فرم</label>
                <textarea class="form-control" id="form_description_edit_textarea" name="form_description" rows="3"><?php echo htmlspecialchars($form_description); ?></textarea>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">فیلدهای فرم</h5>
            <div class="dropdown">
                <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" id="addFieldDropdownButtonEdit" data-bs-toggle="dropdown" aria-expanded="false">
                     <?php echo $field_types_config['add_new_field_icon'] ?? ''; ?>
                    افزودن فیلد جدید
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="addFieldDropdownButtonEdit">
                     <?php foreach ($field_types_config['types'] as $type_key => $type_info): ?>
                        <li><a class="dropdown-item add-field-btn" href="#" data-field-type="<?php echo $type_key; ?>">
                            <?php echo $type_info['icon'] . ' ' . htmlspecialchars($type_info['label']); ?>
                        </a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <div class="card-body">
            <div id="form-fields-container-edit" class="mb-3">
                <p class="text-muted text-center placeholder-text <?php echo !empty(json_decode($form_fields_json_for_builder, true)) ? 'd-none' : ''; ?>">
                    این فرم در حال حاضر هیچ فیلدی ندارد یا فیلدها در حال بارگذاری هستند.
                </p>
            </div>
            <div class="form-actions text-start border-top pt-3">
                 <button type="submit" name="update_form_structure" class="btn btn-success">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-save-fill icon" viewBox="0 0 16 16"><path d="M8.5 1.5A1.5 1.5 0 0 1 10 0h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h2.5S.5 0 .5 1.5V4h1.572L5.7 7.596l1.376.786L8.5 9.118l1.428-.736.68-.346L12.5 6.43V1.5zM9.5 2H6v1.572l-2.5 2.904V14h10V6.376L9.5 2zM8 5.5a.5.5 0 0 0 0 1H5v-1h3z"/></svg>
                    ذخیره تغییرات فرم
                </button>
            </div>
        </div>
    </div>
</form>

<?php echo get_form_field_templates_html(); ?>

<script src="<?php echo get_base_url(); ?>assets/js/Sortable.min.js"></script>
<script src="<?php echo get_base_url(); ?>assets/js/form_builder.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const initialFieldsDataEdit = JSON.parse(document.getElementById('form_fields_json_input_edit').value || '[]');
    if (typeof initializeFormBuilder === 'function') {
        initializeFormBuilder(
            'form-fields-container-edit',
            'form_fields_json_input_edit',
            initialFieldsDataEdit,
            <?php echo json_encode($field_types_config); ?>
        );
    } else {
        console.error('initializeFormBuilder function not found.');
    }
});
</script>
<style>
    .form-field-item .card-header { background-color: #f8f9fa; padding: 0.5rem 0.75rem; }
    .form-field-item .handle-sort { cursor: grab; }
    .sortable-ghost { opacity: 0.4; background: #c8ebfb; border: 1px dashed #007bff; }
    .sortable-chosen, .sortable-drag { opacity: 1 !important; background: #e9ecef; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
    .field-property-sm { font-size: 0.8rem; margin-top: 0.2rem; }
    .options-list-item { display: flex; align-items: center; margin-bottom: 0.3rem; }
    .options-list-item input[type="text"] { flex-grow: 1; margin-right: 0.5rem; /* RTL */ }
</style>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
