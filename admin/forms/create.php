<?php
require_once __DIR__ . '/../includes/header.php';

$form_title = '';
$form_description = '';
$form_status = 'draft'; // Default status
// Holds the JSON string of field definitions for JS and for submission if validation fails
$form_fields_json_post = isset($_POST['form_fields_json']) ? $_POST['form_fields_json'] : '[]';
$form_errors = [];

$csrf_token = generate_csrf_token('create_form_structure');

// Field Types Configuration
$field_types_config = get_form_field_types_config();


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_form_structure'])) {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'], 'create_form_structure')) {
        $form_errors['csrf'] = 'خطای امنیتی CSRF. لطفاً صفحه را رفرش کنید.';
    } else {
        $csrf_token = regenerate_csrf_token('create_form_structure');

        $form_title = sanitize_input($_POST['form_title'] ?? '');
        $form_description = sanitize_input($_POST['form_description'] ?? '');
        $form_status = in_array($_POST['form_status'] ?? 'draft', ['draft', 'published', 'archived']) ? $_POST['form_status'] : 'draft';

        // $form_fields_json_post is already set from the top
        $fields_data = json_decode($form_fields_json_post, true);

        if (empty($form_title)) $form_errors['form_title'] = "عنوان فرم نمی‌تواند خالی باشد.";
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($fields_data)) {
            $form_errors['form_fields_json'] = "ساختار فیلدهای فرم نامعتبر است (خطای JSON).";
        } elseif (empty($fields_data)) {
            $form_errors['form_fields_json'] = "فرم باید حداقل شامل یک فیلد باشد.";
        } else {
            foreach ($fields_data as $index => $field) {
                if (empty(trim($field['label'] ?? ''))) {
                    $form_errors['field_'.($index+1).'_label'] = "برچسب فیلد شماره ".($index+1)." نمی‌تواند خالی باشد.";
                }
                if (in_array($field['type'] ?? '', ['select', 'radio', 'checkbox']) && empty($field['options'] ?? [])) {
                    $form_errors['field_'.($index+1).'_options'] = "گزینه‌ها برای فیلد انتخابی شماره ".($index+1)." (با برچسب: ".htmlspecialchars($field['label'] ?? 'بدون برچسب').") باید مشخص شوند.";
                }
                 // Max length validations for field properties
                if (isset($field['label']) && mb_strlen($field['label']) > 255) $form_errors['field_'.($index+1).'_label_length'] = "برچسب فیلد شماره ".($index+1)." طولانی تر از حد مجاز است (255 کاراکتر).";
                if (isset($field['placeholder']) && mb_strlen($field['placeholder']) > 255) $form_errors['field_'.($index+1).'_placeholder_length'] = "متن راهنمای فیلد شماره ".($index+1)." طولانی تر از حد مجاز است (255 کاراکتر).";

            }
        }

        if (empty($form_errors)) {
            if ($conn) {
                $conn->begin_transaction();
                try {
                    $current_user_id = get_current_user_id();

                    $stmt_form = $conn->prepare("INSERT INTO Forms (Title, Description, Status, CreatedByUserID, CreatedAt, UpdatedAt) VALUES (?, ?, ?, ?, NOW(), NOW())");
                    if (!$stmt_form) throw new Exception("خطا در آماده سازی کوئری فرم: " . $conn->error);
                    $stmt_form->bind_param("sssi", $form_title, $form_description, $form_status, $current_user_id);
                    if (!$stmt_form->execute()) throw new Exception("خطا در ایجاد فرم: " . $stmt_form->error);
                    $new_form_id = $stmt_form->insert_id;
                    $stmt_form->close();

                    if ($new_form_id) {
                        $stmt_field = $conn->prepare("INSERT INTO FormFields (FormID, FieldLabel, FieldType, FieldOptions, IsRequired, Placeholder, SortOrder, HelperText, MinValue, MaxValue, MaxLength, FileTypes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        if (!$stmt_field) throw new Exception("خطا در آماده سازی کوئری فیلدها: " . $conn->error);

                        foreach ($fields_data as $order => $field) {
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


                            $stmt_field->bind_param(
                                "isssissssss",
                                $new_form_id, $field_label, $field_type, $options_json,
                                $is_required, $placeholder, $order, $helper_text,
                                $min_val, $max_val, $max_len, $file_types_str
                            );
                            if (!$stmt_field->execute()) throw new Exception("خطا در ایجاد فیلد '" . htmlspecialchars($field_label) . "': " . $stmt_field->error);
                        }
                        $stmt_field->close();
                    }
                    $conn->commit();
                    $_SESSION['action_success'] = "فرم '" . htmlspecialchars($form_title) . "' با موفقیت ایجاد شد.";
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
    }
}
?>

<div class="page-header">
    <h1>ایجاد فرم جدید</h1>
    <div class="page-header-actions">
        <a href="index.php" class="btn btn-secondary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-right-circle icon" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8zm15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-4.5-.5a.5.5 0 0 0 0 1h5.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3a.5.5 0 0 0 0-.708l-3-3a.5.5 0 1 0-.708.708L11.293 7.5H6.5a.5.5 0 0 0 0 1h4.793z"/></svg>
            بازگشت به لیست فرم‌ها
        </a>
    </div>
</div>

<?php if (!empty($form_errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>خطا در ثبت فرم:</strong>
        <ul class="mb-0">
            <?php foreach ($form_errors as $error_key => $error_msg): ?>
                <li><?php echo htmlspecialchars($error_msg); ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="createFormBuilder">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="form_fields_json" id="form_fields_json_input" value="<?php echo htmlspecialchars($form_fields_json_post); ?>">

    <div class="card mb-4">
        <div class="card-header">اطلاعات کلی فرم</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label for="form_title_input" class="form-label">عنوان فرم <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?php echo isset($form_errors['form_title']) ? 'is-invalid' : ''; ?>"
                           id="form_title_input" name="form_title" value="<?php echo htmlspecialchars($form_title); ?>" required>
                    <?php if (isset($form_errors['form_title'])): ?><div class="invalid-feedback"><?php echo $form_errors['form_title']; ?></div><?php endif; ?>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="form_status_select" class="form-label">وضعیت فرم</label>
                    <select class="form-select" id="form_status_select" name="form_status">
                        <option value="draft" <?php echo ($form_status === 'draft') ? 'selected' : ''; ?>>پیش‌نویس</option>
                        <option value="published" <?php echo ($form_status === 'published') ? 'selected' : ''; ?>>منتشر شده</option>
                        <option value="archived" <?php echo ($form_status === 'archived') ? 'selected' : ''; ?>>بایگانی شده</option>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label for="form_description_textarea" class="form-label">توضیحات فرم</label>
                <textarea class="form-control" id="form_description_textarea" name="form_description" rows="3"><?php echo htmlspecialchars($form_description); ?></textarea>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">فیلدهای فرم</h5>
            <div class="dropdown">
                <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" id="addFieldDropdownButton" data-bs-toggle="dropdown" aria-expanded="false">
                    <?php echo $field_types_config['add_new_field_icon'] ?? ''; ?>
                    افزودن فیلد جدید
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="addFieldDropdownButton">
                    <?php foreach ($field_types_config['types'] as $type_key => $type_info): ?>
                        <li><a class="dropdown-item add-field-btn" href="#" data-field-type="<?php echo $type_key; ?>">
                            <?php echo $type_info['icon'] . ' ' . htmlspecialchars($type_info['label']); ?>
                        </a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <div class="card-body">
            <div id="form-fields-container" class="mb-3">
                <p class="text-muted text-center placeholder-text <?php echo !empty(json_decode($form_fields_json_post, true)) ? 'd-none' : ''; ?>">
                    هنوز هیچ فیلدی به این فرم اضافه نشده است. از دکمه "افزودن فیلد جدید" در بالا استفاده کنید.
                </p>
            </div>
            <div class="form-actions text-start border-top pt-3">
                 <button type="submit" name="save_form_structure" class="btn btn-success">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check-circle-fill icon" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg>
                    ذخیره و ایجاد فرم
                </button>
            </div>
        </div>
    </div>
</form>

<?php echo get_form_field_templates_html(); // Get HTML templates from helper ?>

<script src="<?php echo get_base_url(); ?>assets/js/Sortable.min.js"></script>
<script src="<?php echo get_base_url(); ?>assets/js/form_builder.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const initialFieldsData = JSON.parse(document.getElementById('form_fields_json_input').value || '[]');
    // Initialize the form builder script from form_builder.js
    // The script should expose a function like `initializeFormBuilder(containerId, jsonInputId, initialData, fieldTypesConfig)`
    if (typeof initializeFormBuilder === 'function') {
        initializeFormBuilder(
            'form-fields-container',
            'form_fields_json_input',
            initialFieldsData,
            <?php echo json_encode($field_types_config); ?> // Pass PHP config to JS
        );
    } else {
        console.error('initializeFormBuilder function not found. Make sure form_builder.js is loaded correctly.');
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
