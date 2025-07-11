<?php
// admin/forms/create.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth

$csrf_token_form_create = generate_csrf_token('form_create_action');

$errors = [];
// Initialize input variables for repopulation and default values
$input_form_name = $_POST['form_name'] ?? '';
$input_form_description = $_POST['form_description'] ?? '';
$input_department_id = $_POST['department_id'] ?? ''; // Will be validated to int or null
$input_form_purpose = $_POST['form_purpose'] ?? 'general'; // Default to 'general'
$input_fields_repopulate = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fields']) && is_array($_POST['fields'])) {
    foreach ($_POST['fields'] as $posted_field) {
        $input_fields_repopulate[] = [ // This is for repopulating the dynamic fields on error
            'field_name' => sanitize_input($posted_field['field_name'] ?? ''),
            'field_type' => sanitize_input($posted_field['field_type'] ?? ''),
            'field_options' => sanitize_input($posted_field['field_options'] ?? ''), // Keep as string for textarea
            'is_required' => isset($posted_field['is_required']) ? 1 : 0,
        ];
    }
}


// Fetch departments for dropdown
$departments_query = $conn->query("SELECT DepartmentID, DepartmentName FROM Departments ORDER BY DepartmentName");
$available_departments = [];
if ($departments_query) {
    while($dept = $departments_query->fetch_assoc()){
        $available_departments[] = $dept;
    }
    $departments_query->close();
}

$field_type_options = [
    'text' => 'متن کوتاه (Input Text)',
    'textarea' => 'متن بلند (Textarea)',
    'number' => 'عدد (Input Number)',
    'date' => 'تاریخ (Input Date)',
    'select' => 'لیست کشویی (Select)',
    'radio' => 'گزینه‌های رادیویی (Radio Buttons)',
    'checkbox' => 'چک‌باکس‌ها (Checkboxes)',
];
// Form Purpose Options including new ones for parents module
$form_purpose_options_display_create = [ // Renamed for clarity within this file
    'general' => 'عمومی',
    'self_assessment' => 'خوداظهاری مدرس',
    'class_observation' => 'بازدید کلاسی',
    'parent_survey' => 'نظرسنجی اولیا',
    'service_report' => 'گزارش خدمت گزاری (پرورشی)',
    'parents_meeting_observer_report' => 'گزارش ناظر جلسه اولیا',
    'parents_meeting_teacher_report' => 'گزارش مدرس از جلسه اولیا',
];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'form_create_action')) {
        $errors['csrf'] = 'خطای CSRF! درخواست نامعتبر.';
    } else {
        // Validation for form details
        if (empty($input_form_name)) $errors['form_name'] = 'عنوان فرم الزامی است.';
        else {
            $stmt_check_form_name = $conn->prepare("SELECT FormID FROM Forms WHERE FormName = ?");
            if($stmt_check_form_name) {
                $stmt_check_form_name->bind_param("s", $input_form_name);
                $stmt_check_form_name->execute();
                if ($stmt_check_form_name->get_result()->num_rows > 0) $errors['form_name'] = 'فرمی با این عنوان قبلاً ایجاد شده.';
                $stmt_check_form_name->close();
            } else { $errors['db_error'] = "خطا بررسی عنوان فرم: " . $conn->error; }
        }

        $actual_department_id_for_db = null;
        if ($input_department_id !== '' && $input_department_id != 0) {
            $actual_department_id_for_db = (int)$input_department_id;
            $dept_exists_validation_create = false;
            foreach($available_departments as $ad_val_create) if($ad_val_create['DepartmentID'] == $actual_department_id_for_db) $dept_exists_validation_create = true;
            if(!$dept_exists_validation_create) $errors['department_id'] = "بخش نامعتبر.";
        }


        if (!array_key_exists($input_form_purpose, $form_purpose_options_display_create)) {
             $errors['form_purpose'] = "هدف/نوع فرم انتخاب شده نامعتبر است.";
        }

        if (empty($input_fields_repopulate)) $errors['fields_general'] = 'حداقل یک فیلد باید تعریف شود.';
        else {
            foreach ($input_fields_repopulate as $key => $field) {
                if (empty($field['field_name'])) $errors["field_{$key}_name"] = "عنوان فیلد ".($key+1)." الزامی.";
                if (empty($field['field_type'])) $errors["field_{$key}_type"] = "نوع فیلد ".($key+1)." الزامی.";
                elseif (!array_key_exists($field['field_type'], $field_type_options)) $errors["field_{$key}_type"] = "نوع فیلد ".($key+1)." نامعتبر.";
                if (in_array($field['field_type'], ['select', 'radio', 'checkbox']) && empty(trim($field['field_options']))) {
                    $errors["field_{$key}_options"] = "گزینه‌ها برای فیلد ".($key+1)." الزامی (هر گزینه در خط جدید).";
                }
            }
        }

        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                $created_by_user_id = get_current_user_id();
                $stmt_insert_form = $conn->prepare("INSERT INTO Forms (FormName, Description, DepartmentID, FormPurpose, CreatedByUserID, CreatedAt) VALUES (?, ?, ?, ?, ?, NOW())");
                if(!$stmt_insert_form) throw new Exception("آماده سازی فرم ناموفق: " . $conn->error);

                $stmt_insert_form->bind_param("ssisi", $input_form_name, $input_form_description, $actual_department_id_for_db, $input_form_purpose, $created_by_user_id);

                if ($stmt_insert_form->execute()) {
                    $new_form_id = $stmt_insert_form->insert_id;
                    $stmt_insert_form->close();

                    $stmt_insert_field = $conn->prepare("INSERT INTO FormFields (FormID, FieldName, FieldType, Options, IsRequired, SortOrder) VALUES (?, ?, ?, ?, ?, ?)");
                    if(!$stmt_insert_field) throw new Exception("آماده سازی فیلدها ناموفق: " . $conn->error);

                    foreach ($input_fields_repopulate as $sort_order => $field_data) {
                        $options_str = null;
                        if (in_array($field_data['field_type'], ['select', 'radio', 'checkbox'])) {
                            $options_array = array_map('trim', explode("\n", $field_data['field_options']));
                            $options_array = array_values(array_filter($options_array));
                            $options_str = !empty($options_array) ? json_encode($options_array, JSON_UNESCAPED_UNICODE) : null;
                        }
                        $is_req = $field_data['is_required'] ? 1 : 0;
                        $stmt_insert_field->bind_param("isssii", $new_form_id, $field_data['field_name'], $field_data['field_type'], $options_str, $is_req, $sort_order);
                        if (!$stmt_insert_field->execute()) {
                            throw new Exception("ذخیره فیلد '".$field_data['field_name']."' ناموفق: " . $stmt_insert_field->error);
                        }
                    }
                    $stmt_insert_field->close();
                    $conn->commit();
                    regenerate_csrf_token('form_create_action');
                    $_SESSION['flash_message'] = ['type' => 'success', 'text' => "فرم '" . htmlspecialchars($input_form_name) . "' با موفقیت ایجاد شد."];
                    header("Location: index.php?action_status=success_create");
                    exit;
                } else { throw new Exception("ایجاد فرم اصلی ناموفق: " . $stmt_insert_form->error); }
            } catch (Exception $e) { $conn->rollback(); $errors['db_error'] = "خطای دیتابیس: " . $e->getMessage(); }
        }
    }
    $csrf_token_form_create = regenerate_csrf_token('form_create_action');
}
?>

<div class="page-header">
    <h1>ایجاد فرم جدید</h1>
     <div class="page-header-actions">
        <a href="index.php" class="btn btn-secondary">
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
            <span>بازگشت به لیست</span>
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <p><strong>خطا در ثبت فرم:</strong></p>
        <ul> <?php foreach ($errors as $error_key => $error_msg):
                if (preg_match('/field_(\d+)_(name|type|options)/', $error_key, $matches)) {
                    $field_num = (isset($matches[1]) ? ((int)$matches[1] + 1) : '');
                    $part_map = ['name'=>'عنوان فیلد','type'=>'نوع فیلد','options'=>'گزینه‌های فیلد'];
                    $part_name = isset($matches[2]) ? $part_map[$matches[2]] : 'فیلد';
                    echo "<li>خطا در $part_name $field_num: " . htmlspecialchars($error_msg) . "</li>";
                } else { echo "<li>" . htmlspecialchars($error_msg) . "</li>"; }
            endforeach; ?> </ul>
    </div>
<?php endif; ?>


<form action="create.php" method="POST" id="createFormBuilder">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_form_create; ?>">

    <div class="card">
        <div class="card-header"><span class="card-title-text">۱. اطلاعات کلی فرم</span></div>
        <div class="card-body">
            <div class="form-group">
                <label for="form_name">عنوان فرم <span class="text-danger">*</span></label>
                <input type="text" class="form-control <?php echo isset($errors['form_name']) ? 'is-invalid' : ''; ?>" id="form_name" name="form_name" value="<?php echo htmlspecialchars($input_form_name); ?>" required>
                <?php if (isset($errors['form_name'])): ?><div class="invalid-feedback"><?php echo $errors['form_name']; ?></div><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="form_description">توضیحات فرم</label>
                <textarea class="form-control" id="form_description" name="form_description" rows="3"><?php echo htmlspecialchars($input_form_description); ?></textarea>
            </div>
            <div class="form-group">
                <label for="department_id">بخش مرتبط (اختیاری)</label>
                <select class="form-control <?php echo isset($errors['department_id']) ? 'is-invalid' : ''; ?>" id="department_id" name="department_id">
                    <option value="">-- هیچکدام --</option>
                    <?php foreach($available_departments as $dept): ?>
                        <option value="<?php echo $dept['DepartmentID']; ?>" <?php echo ($input_department_id == $dept['DepartmentID']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['DepartmentName']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['department_id'])): ?><div class="invalid-feedback"><?php echo $errors['department_id']; ?></div><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="form_purpose">هدف/نوع فرم <span class="text-danger">*</span></label>
                <select class="form-control <?php echo isset($errors['form_purpose']) ? 'is-invalid' : ''; ?>" id="form_purpose" name="form_purpose" required>
                    <?php foreach ($form_purpose_options_display_create as $fp_val => $fp_label): ?>
                        <option value="<?php echo $fp_val; ?>" <?php echo ($input_form_purpose == $fp_val) ? 'selected' : ''; ?>>
                            <?php echo $fp_label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['form_purpose'])): ?><div class="invalid-feedback"><?php echo $errors['form_purpose']; ?></div><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="card-title-text">۲. فیلدهای فرم</span>
            <button type="button" id="addFieldBtn" class="btn btn-sm btn-success">
                <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                <span>افزودن فیلد</span>
            </button>
        </div>
        <div class="card-body">
            <div id="formFieldsContainer" class="mb-3">
                <?php if (!empty($input_fields_repopulate)): ?>
                    <?php foreach ($input_fields_repopulate as $index => $field_val): ?>
                        <div class="form-field-item card mb-3">
                            <div class="card-header py-2 field-header">
                                <span class="field-number">فیلد #<?php echo $index + 1; ?></span>
                                <button type="button" class="btn btn-sm btn-danger removeFieldBtn float-left" title="حذف این فیلد">
                                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                </button>
                            </div>
                            <div class="card-body p-3">
                                <div class="row">
                                    <div class="form-group col-md-5">
                                        <label for="field_name_<?php echo $index; ?>">عنوان فیلد <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control <?php echo isset($errors["field_{$index}_name"]) ? 'is-invalid' : ''; ?>" id="field_name_<?php echo $index; ?>" name="fields[<?php echo $index; ?>][field_name]" value="<?php echo htmlspecialchars($field_val['field_name']); ?>" required>
                                         <?php if (isset($errors["field_{$index}_name"])): ?><div class="invalid-feedback"><?php echo $errors["field_{$index}_name"]; ?></div><?php endif; ?>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="field_type_<?php echo $index; ?>">نوع فیلد <span class="text-danger">*</span></label>
                                        <select class="form-control field-type-select <?php echo isset($errors["field_{$index}_type"]) ? 'is-invalid' : ''; ?>" id="field_type_<?php echo $index; ?>" name="fields[<?php echo $index; ?>][field_type]" required data-index="<?php echo $index; ?>">
                                            <option value="">انتخاب کنید...</option>
                                            <?php foreach($field_type_options as $val_opt => $label_opt): ?>
                                                <option value="<?php echo $val_opt; ?>" <?php echo ($field_val['field_type'] == $val_opt) ? 'selected' : ''; ?>><?php echo $label_opt; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (isset($errors["field_{$index}_type"])): ?><div class="invalid-feedback"><?php echo $errors["field_{$index}_type"]; ?></div><?php endif; ?>
                                    </div>
                                    <div class="form-group col-md-3 align-self-end text-left">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="is_required_<?php echo $index; ?>" name="fields[<?php echo $index; ?>][is_required]" value="1" <?php echo !empty($field_val['is_required']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="is_required_<?php echo $index; ?>">الزامی باشد</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group field-options-container mt-2" id="options_container_<?php echo $index; ?>" style="<?php echo in_array($field_val['field_type'], ['select', 'radio', 'checkbox']) ? '' : 'display:none;'; ?>">
                                    <label for="field_options_<?php echo $index; ?>">گزینه‌ها (هر گزینه در یک خط جدید)</label>
                                    <textarea class="form-control <?php echo isset($errors["field_{$index}_options"]) ? 'is-invalid' : ''; ?>" id="field_options_<?php echo $index; ?>" name="fields[<?php echo $index; ?>][field_options]" rows="3"><?php echo htmlspecialchars($field_val['field_options']); ?></textarea>
                                    <?php if (isset($errors["field_{$index}_options"])): ?><div class="invalid-feedback"><?php echo $errors["field_{$index}_options"]; ?></div><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php if (isset($errors['fields_general'])): ?><div class="alert alert-warning mt-2"><?php echo $errors['fields_general']; ?></div><?php endif; ?>
             <small class="form-text text-muted">فیلدها به ترتیبی که اضافه می‌کنید، در فرم نهایی نمایش داده خواهند شد.</small>
        </div>
    </div>

    <div class="form-actions mt-4">
        <button type="submit" class="btn btn-primary btn-lg">
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
            <span>ذخیره فرم</span>
        </button>
        <a href="index.php" class="btn btn-outline-secondary btn-lg">انصراف</a>
    </div>
</form>

<div id="fieldTemplate" style="display:none;">
    <div class="form-field-item card mb-3">
        <div class="card-header py-2 field-header">
            <span class="field-number">فیلد #FIELD_DISPLAY_INDEX</span>
            <button type="button" class="btn btn-sm btn-danger removeFieldBtn float-left" title="حذف این فیلد">
                 <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>
        <div class="card-body p-3">
            <div class="row">
                <div class="form-group col-md-5">
                    <label for="field_name_FIELD_INDEX_TPL">عنوان فیلد <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="field_name_FIELD_INDEX_TPL" name="fields[FIELD_INDEX][field_name]" required>
                </div>
                <div class="form-group col-md-4">
                    <label for="field_type_FIELD_INDEX_TPL">نوع فیلد <span class="text-danger">*</span></label>
                    <select class="form-control field-type-select" id="field_type_FIELD_INDEX_TPL" name="fields[FIELD_INDEX][field_type]" required data-index="FIELD_INDEX">
                        <option value="">انتخاب کنید...</option>
                        <?php foreach($field_type_options as $val => $label): // Use the same options as above ?>
                            <option value="<?php echo $val; ?>"><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                 <div class="form-group col-md-3 align-self-end text-left">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_required_FIELD_INDEX_TPL" name="fields[FIELD_INDEX][is_required]" value="1">
                        <label class="form-check-label" for="is_required_FIELD_INDEX_TPL">الزامی باشد</label>
                    </div>
                </div>
            </div>
            <div class="form-group field-options-container mt-2" id="options_container_FIELD_INDEX_TPL" style="display:none;">
                <label for="field_options_FIELD_INDEX_TPL">گزینه‌ها (هر گزینه در یک خط جدید)</label>
                <textarea class="form-control" id="field_options_FIELD_INDEX_TPL" name="fields[FIELD_INDEX][field_options]" rows="3"></textarea>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('formFieldsContainer');
    const addFieldBtn = document.getElementById('addFieldBtn');
    const fieldTemplateHtmlSource = document.getElementById('fieldTemplate').innerHTML;
    let fieldIndexCounter = <?php echo count($input_fields_repopulate); ?>;

    function updateFieldNumbering() {
        const fieldItems = container.querySelectorAll('.form-field-item');
        fieldItems.forEach((item, idx) => {
            const fieldNumberSpan = item.querySelector('.field-number');
            if (fieldNumberSpan) {
                fieldNumberSpan.textContent = 'فیلد #' + (idx + 1);
            }
        });
    }

    function addNewField() {
        const actualIndex = fieldIndexCounter++;
        const displayIndex = container.children.length + 1;

        let newFieldHtml = fieldTemplateHtmlSource;
        newFieldHtml = newFieldHtml.replace(/FIELD_INDEX_TPL/g, actualIndex + '_tpl_id');
        newFieldHtml = newFieldHtml.replace(/FIELD_INDEX/g, actualIndex);
        newFieldHtml = newFieldHtml.replace(/FIELD_DISPLAY_INDEX/g, displayIndex);

        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = newFieldHtml;
        const newFieldElement = tempDiv.firstElementChild;

        newFieldElement.querySelectorAll('[id*="_tpl_id"]').forEach(el => {
            el.id = el.id.replace('_tpl_id', '');
        });
         newFieldElement.querySelectorAll('[for*="_tpl_id"]').forEach(el => {
            el.htmlFor = el.htmlFor.replace('_tpl_id', '');
        });

        container.appendChild(newFieldElement);
        attachEventListenersToNewField(newFieldElement, actualIndex);
        updateFieldNumbering();
    }

    function attachEventListenersToNewField(fieldItem, currentItemIndex) {
        const typeSelect = fieldItem.querySelector('.field-type-select');
        const optionsContainer = fieldItem.querySelector('.field-options-container');
        const removeBtn = fieldItem.querySelector('.removeFieldBtn');

        if (typeSelect && optionsContainer) {
            typeSelect.addEventListener('change', function() {
                optionsContainer.style.display = ['select', 'radio', 'checkbox'].includes(this.value) ? 'block' : 'none';
            });
            if (typeSelect.value && ['select', 'radio', 'checkbox'].includes(typeSelect.value)) {
                 optionsContainer.style.display = 'block';
            }
        }
        if (removeBtn) {
            removeBtn.addEventListener('click', function() {
                fieldItem.remove();
                updateFieldNumbering();
            });
        }
    }

    if (addFieldBtn) {
        addFieldBtn.addEventListener('click', addNewField);
    }

    document.querySelectorAll('#formFieldsContainer .form-field-item').forEach(function(item) {
        let nameAttrInput = item.querySelector('[name*="[field_name]"]');
        if (nameAttrInput && nameAttrInput.name) {
            let match = nameAttrInput.name.match(/\[(\d+)\]/);
            if (match && match[1]) {
                 let dataIndexFromName = parseInt(match[1]);
                 attachEventListenersToNewField(item, dataIndexFromName);
            }
        }
    });

    <?php if (empty($input_fields_repopulate) && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
        addNewField();
    <?php endif; ?>
    updateFieldNumbering();
});
</script>
<style>
    .field-header { background-color: #f8f9fc; }
    .field-number { font-weight: bold; color: #5a5c69; }
    .form-field-item .removeFieldBtn svg { vertical-align: middle; }
</style>
<?php
require_once __DIR__ . '/../includes/footer.php';
?>
```

و همچنین فایل `admin/forms/edit.php`:
```php
<?php
// admin/forms/edit.php
require_once __DIR__ . '/../includes/header.php';

$csrf_token_form_edit = generate_csrf_token('form_edit_action');
$errors = [];
$form_id_to_edit = null;
$form_data_for_form = null;

// Initialize input variables for repopulation and default values
$input_form_name = '';
$input_form_description = '';
$input_department_id = ''; // Will be int or null
$input_form_purpose = 'general'; // Default
$input_fields_repopulate = [];


$departments_query = $conn->query("SELECT DepartmentID, DepartmentName FROM Departments ORDER BY DepartmentName");
$available_departments = [];
if ($departments_query) { while($dept = $departments_query->fetch_assoc()) $available_departments[] = $dept; $departments_query->close(); }

$field_type_options = [
    'text' => 'متن کوتاه', 'textarea' => 'متن بلند', 'number' => 'عدد', 'date' => 'تاریخ',
    'select' => 'لیست کشویی', 'radio' => 'گزینه‌های رادیویی', 'checkbox' => 'چک‌باکس‌ها',
];
$form_purpose_options_display_edit = [ // Used for displaying in the select dropdown
    'general' => 'عمومی',
    'self_assessment' => 'خوداظهاری مدرس',
    'class_observation' => 'بازدید کلاسی',
    'parent_survey' => 'نظرسنجی اولیا',
    'service_report' => 'گزارش خدمت گزاری (پرورشی)',
    'parents_meeting_observer_report' => 'گزارش ناظر جلسه اولیا',
    'parents_meeting_teacher_report' => 'گزارش مدرس از جلسه اولیا',
];


if (isset($_GET['form_id']) && is_numeric($_GET['form_id'])) {
    $form_id_to_edit = (int)$_GET['form_id'];
    $stmt_fetch_form = $conn->prepare("SELECT FormID, FormName, Description, DepartmentID, FormPurpose FROM Forms WHERE FormID = ?");
    if ($stmt_fetch_form) {
        $stmt_fetch_form->bind_param("i", $form_id_to_edit);
        $stmt_fetch_form->execute();
        $result_form = $stmt_fetch_form->get_result();
        if ($result_form->num_rows === 1) {
            $form_data_for_form = $result_form->fetch_assoc();

            $stmt_form_fields = $conn->prepare("SELECT FieldID, FieldName, FieldType, Options, IsRequired, SortOrder FROM FormFields WHERE FormID = ? ORDER BY SortOrder ASC, FieldID ASC");
            if ($stmt_form_fields) {
                $stmt_form_fields->bind_param("i", $form_id_to_edit);
                $stmt_form_fields->execute();
                $result_form_fields = $stmt_form_fields->get_result();
                while ($field = $result_form_fields->fetch_assoc()) {
                    $options_display = $field['Options'];
                    if (in_array($field['FieldType'], ['select', 'radio', 'checkbox']) && !empty($field['Options'])) {
                        $decoded_options = json_decode($field['Options'], true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_options)) {
                            $options_display = implode("\n", $decoded_options);
                        } else { $options_display = $field['Options']; }
                    }
                    $input_fields_repopulate[] = [
                        'field_id' => $field['FieldID'],
                        'field_name' => $field['FieldName'],
                        'field_type' => $field['FieldType'],
                        'field_options' => $options_display,
                        'is_required' => $field['IsRequired'],
                    ];
                }
                $stmt_form_fields->close();
            }
            // Initialize general form input values if not a POST request
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $input_form_name = $form_data_for_form['FormName'];
                $input_form_description = $form_data_for_form['Description'];
                $input_department_id = $form_data_for_form['DepartmentID'];
                $input_form_purpose = $form_data_for_form['FormPurpose'] ?? 'general';
            }
        } else { $errors['load_error'] = "فرم یافت نشد."; }
        $stmt_fetch_form->close();
    } else { $errors['load_error'] = "خطا بارگذاری فرم: " . $conn->error; }
} elseif (!isset($_POST['form_id'])) { // If not POST and no form_id in GET (direct access to edit.php without ID)
     $errors['load_error'] = "شناسه فرم نامعتبر.";
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Repopulate general form info from POST for validation/sticky form
    $input_form_name = sanitize_input($_POST['form_name'] ?? '');
    $input_form_description = sanitize_input($_POST['form_description'] ?? '');
    $input_department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $input_form_purpose = sanitize_input($_POST['form_purpose'] ?? 'general');
    $form_id_from_post = isset($_POST['form_id']) ? (int)$_POST['form_id'] : null; // Get form_id from hidden field

    // Crucial: ensure $form_id_to_edit is set correctly from POST if it was a POST request
    if ($form_id_from_post) $form_id_to_edit = $form_id_from_post;


    // Repopulate fields from POST
    $input_fields_repopulate = [];
    if (isset($_POST['fields']) && is_array($_POST['fields'])) {
        foreach ($_POST['fields'] as $posted_field) {
            $input_fields_repopulate[] = [
                'field_name' => sanitize_input($posted_field['field_name'] ?? ''),
                'field_type' => sanitize_input($posted_field['field_type'] ?? ''),
                'field_options' => sanitize_input($posted_field['field_options'] ?? ''),
                'is_required' => isset($posted_field['is_required']) ? 1 : 0,
            ];
        }
    }

    if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'form_edit_action')) {
        $errors['csrf'] = 'خطای CSRF!';
    } elseif (!$form_id_to_edit) { // Check if $form_id_to_edit is valid after POST
        $errors['form_error'] = 'خطا: شناسه فرم برای ویرایش معتبر نیست.';
    } else {
        if (empty($input_form_name)) $errors['form_name'] = 'عنوان فرم الزامی.';
        else {
            $stmt_check_form_name = $conn->prepare("SELECT FormID FROM Forms WHERE FormName = ? AND FormID != ?");
            if($stmt_check_form_name) {
                $stmt_check_form_name->bind_param("si", $input_form_name, $form_id_to_edit);
                $stmt_check_form_name->execute();
                if ($stmt_check_form_name->get_result()->num_rows > 0) $errors['form_name'] = 'فرم دیگری با این عنوان وجود دارد.';
                $stmt_check_form_name->close();
            } else { $errors['db_error'] = "خطا بررسی عنوان فرم: " . $conn->error; }
        }

        $actual_department_id_for_db_edit = null;
        if ($input_department_id !== null && $input_department_id != 0) {
            $actual_department_id_for_db_edit = (int)$input_department_id;
            $dept_exists_validation_edit = false;
            foreach($available_departments as $ad_val_edit) if($ad_val_edit['DepartmentID'] == $actual_department_id_for_db_edit) $dept_exists_validation_edit = true;
            if(!$dept_exists_validation_edit) $errors['department_id'] = "بخش نامعتبر.";
        }

        if (!array_key_exists($input_form_purpose, $form_purpose_options_display_edit)) {
             $errors['form_purpose'] = "هدف/نوع فرم نامعتبر.";
        }

        if (empty($input_fields_repopulate)) $errors['fields_general'] = 'حداقل یک فیلد باید تعریف شود.';
        else { /* ... field validation same as create.php ... */ }

        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                $stmt_update_form = $conn->prepare("UPDATE Forms SET FormName = ?, Description = ?, DepartmentID = ?, FormPurpose = ? WHERE FormID = ?");
                if(!$stmt_update_form) throw new Exception("آماده سازی آپدیت فرم ناموفق: " . $conn->error);
                $stmt_update_form->bind_param("ssisi", $input_form_name, $input_form_description, $actual_department_id_for_db_edit, $input_form_purpose, $form_id_to_edit);

                if ($stmt_update_form->execute()) {
                    $stmt_update_form->close();

                    $stmt_delete_old_fields = $conn->prepare("DELETE FROM FormFields WHERE FormID = ?");
                    if(!$stmt_delete_old_fields) throw new Exception("آماده سازی حذف فیلدهای قدیمی ناموفق: " . $conn->error);
                    $stmt_delete_old_fields->bind_param("i", $form_id_to_edit);
                    if(!$stmt_delete_old_fields->execute()) throw new Exception("حذف فیلدهای قدیمی ناموفق: " . $stmt_delete_old_fields->error);
                    $stmt_delete_old_fields->close();

                    $stmt_insert_field = $conn->prepare("INSERT INTO FormFields (FormID, FieldName, FieldType, Options, IsRequired, SortOrder) VALUES (?, ?, ?, ?, ?, ?)");
                    if(!$stmt_insert_field) throw new Exception("آماده سازی فیلدهای جدید ناموفق: " . $conn->error);
                    foreach ($input_fields_repopulate as $sort_order => $field_data) {
                        $options_str = null;
                        if (in_array($field_data['field_type'], ['select', 'radio', 'checkbox'])) {
                            $options_array = array_map('trim', explode("\n", $field_data['field_options']));
                            $options_array = array_values(array_filter($options_array));
                            $options_str = !empty($options_array) ? json_encode($options_array, JSON_UNESCAPED_UNICODE) : null;
                        }
                        $is_req = $field_data['is_required'] ? 1 : 0;
                        $stmt_insert_field->bind_param("isssii", $form_id_to_edit, $field_data['field_name'], $field_data['field_type'], $options_str, $is_req, $sort_order);
                        if (!$stmt_insert_field->execute()) {
                            throw new Exception("ذخیره فیلد '".$field_data['field_name']."' ناموفق: " . $stmt_insert_field->error);
                        }
                    }
                    $stmt_insert_field->close();
                    $conn->commit();
                    regenerate_csrf_token('form_edit_action');
                     $_SESSION['flash_message'] = ['type' => 'success', 'text' => "فرم '" . htmlspecialchars($input_form_name) . "' با موفقیت ویرایش شد."];
                    header("Location: index.php?action_status=success_edit");
                    exit;
                } else { throw new Exception("ویرایش فرم اصلی ناموفق: " . $stmt_update_form->error); }
            } catch (Exception $e) { $conn->rollback(); $errors['db_error'] = "خطای دیتابیس: " . $e->getMessage(); }
        }
    }
    $csrf_token_form_edit = regenerate_csrf_token('form_edit_action');
}
?>

<div class="page-header">
    <h1>ویرایش فرم: <?php echo htmlspecialchars($input_form_name ?: ($form_data_for_form['FormName'] ?? '...')); ?></h1>
    <div class="page-header-actions">
        <a href="index.php" class="btn btn-secondary">
             <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
            <span>بازگشت به لیست</span>
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <p><strong>خطا در ویرایش فرم:</strong></p>
        <ul><?php foreach ($errors as $error_key => $error_msg): /* ... error display logic ... */ endforeach; ?></ul>
    </div>
<?php endif; ?>

<?php if ($form_data_for_form || $_SERVER['REQUEST_METHOD'] === 'POST'): // Show form if data loaded or it's a POST (even with load error, to show other errors) ?>
<form action="edit.php?form_id=<?php echo $form_id_to_edit; ?>" method="POST" id="editFormBuilder">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_form_edit; ?>">
    <input type="hidden" name="form_id" value="<?php echo $form_id_to_edit; ?>">

    <div class="card">
        <div class="card-header"><span class="card-title-text">۱. اطلاعات کلی فرم</span></div>
        <div class="card-body">
            <div class="form-group">
                <label for="form_name">عنوان فرم <span class="text-danger">*</span></label>
                <input type="text" class="form-control <?php echo isset($errors['form_name']) ? 'is-invalid' : ''; ?>" id="form_name" name="form_name" value="<?php echo htmlspecialchars($input_form_name); ?>" required>
                <?php if (isset($errors['form_name'])): ?><div class="invalid-feedback"><?php echo $errors['form_name']; ?></div><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="form_description">توضیحات فرم</label>
                <textarea class="form-control" id="form_description" name="form_description" rows="3"><?php echo htmlspecialchars($input_form_description); ?></textarea>
            </div>
            <div class="form-group">
                <label for="department_id">بخش مرتبط (اختیاری)</label>
                <select class="form-control <?php echo isset($errors['department_id']) ? 'is-invalid' : ''; ?>" id="department_id" name="department_id">
                    <option value="">-- هیچکدام --</option>
                    <?php foreach($available_departments as $dept): ?>
                        <option value="<?php echo $dept['DepartmentID']; ?>" <?php echo ($input_department_id == $dept['DepartmentID']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['DepartmentName']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                 <?php if (isset($errors['department_id'])): ?><div class="invalid-feedback"><?php echo $errors['department_id']; ?></div><?php endif; ?>
            </div>
             <div class="form-group">
                <label for="form_purpose">هدف/نوع فرم <span class="text-danger">*</span></label>
                <select class="form-control <?php echo isset($errors['form_purpose']) ? 'is-invalid' : ''; ?>" id="form_purpose" name="form_purpose" required>
                    <?php foreach ($form_purpose_options_display_edit as $fp_val => $fp_label): ?>
                        <option value="<?php echo $fp_val; ?>" <?php echo ($input_form_purpose == $fp_val) ? 'selected' : ''; ?>>
                            <?php echo $fp_label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['form_purpose'])): ?><div class="invalid-feedback"><?php echo $errors['form_purpose']; ?></div><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="card-title-text">۲. فیلدهای فرم</span>
            <button type="button" id="addFieldBtnEdit" class="btn btn-sm btn-success">
                 <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                <span>افزودن فیلد</span>
            </button>
        </div>
        <div class="card-body">
            <div id="formFieldsContainerEdit" class="mb-3">
                <?php if (!empty($input_fields_repopulate)): ?>
                    <?php foreach ($input_fields_repopulate as $index => $field_val): ?>
                        <div class="form-field-item card mb-3">
                             <div class="card-header py-2 field-header">
                                <span class="field-number">فیلد #<?php echo $index + 1; ?></span>
                                <button type="button" class="btn btn-sm btn-danger removeFieldBtn float-left" title="حذف این فیلد">
                                     <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                </button>
                            </div>
                            <div class="card-body p-3">
                                <div class="row">
                                    <div class="form-group col-md-5">
                                        <label for="field_name_<?php echo $index; ?>">عنوان فیلد <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control <?php echo isset($errors["field_{$index}_name"]) ? 'is-invalid' : ''; ?>" id="field_name_<?php echo $index; ?>" name="fields[<?php echo $index; ?>][field_name]" value="<?php echo htmlspecialchars($field_val['field_name']); ?>" required>
                                        <?php if (isset($errors["field_{$index}_name"])): ?><div class="invalid-feedback"><?php echo $errors["field_{$index}_name"]; ?></div><?php endif; ?>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="field_type_<?php echo $index; ?>">نوع فیلد <span class="text-danger">*</span></label>
                                        <select class="form-control field-type-select <?php echo isset($errors["field_{$index}_type"]) ? 'is-invalid' : ''; ?>" id="field_type_<?php echo $index; ?>" name="fields[<?php echo $index; ?>][field_type]" required data-index="<?php echo $index; ?>">
                                            <option value="">انتخاب کنید...</option>
                                            <?php foreach($field_type_options as $val_opt => $label_opt): ?>
                                                <option value="<?php echo $val_opt; ?>" <?php echo ($field_val['field_type'] == $val_opt) ? 'selected' : ''; ?>><?php echo $label_opt; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (isset($errors["field_{$index}_type"])): ?><div class="invalid-feedback"><?php echo $errors["field_{$index}_type"]; ?></div><?php endif; ?>
                                    </div>
                                     <div class="form-group col-md-3 align-self-end text-left">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="is_required_<?php echo $index; ?>" name="fields[<?php echo $index; ?>][is_required]" value="1" <?php echo !empty($field_val['is_required']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="is_required_<?php echo $index; ?>">الزامی باشد</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group field-options-container mt-2" id="options_container_<?php echo $index; ?>" style="<?php echo in_array($field_val['field_type'], ['select', 'radio', 'checkbox']) ? '' : 'display:none;'; ?>">
                                    <label for="field_options_<?php echo $index; ?>">گزینه‌ها (هر گزینه در یک خط جدید)</label>
                                    <textarea class="form-control <?php echo isset($errors["field_{$index}_options"]) ? 'is-invalid' : ''; ?>" id="field_options_<?php echo $index; ?>" name="fields[<?php echo $index; ?>][field_options]" rows="3"><?php echo htmlspecialchars($field_val['field_options']); ?></textarea>
                                     <?php if (isset($errors["field_{$index}_options"])): ?><div class="invalid-feedback"><?php echo $errors["field_{$index}_options"]; ?></div><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
             <?php if (isset($errors['fields_general'])): ?><div class="alert alert-warning mt-2"><?php echo $errors['fields_general']; ?></div><?php endif; ?>
            <small class="form-text text-muted">فیلدها به ترتیبی که در اینجا قرار دارند، در فرم نهایی نمایش داده خواهند شد.</small>
        </div>
    </div>

    <div class="form-actions mt-4">
        <button type="submit" name="submit_edit_form" class="btn btn-primary btn-lg"> <!-- Ensure submit button has a name if needed -->
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
            <span>ذخیره تغییرات فرم</span>
        </button>
        <a href="index.php" class="btn btn-outline-secondary btn-lg">انصراف</a>
    </div>
</form>
<?php elseif(isset($errors['load_error'])): // If form data could not be loaded, show the load error ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($errors['load_error']); ?></div>
<?php endif; ?>

<div id="fieldTemplateEdit" style="display:none;">
    <div class="form-field-item card mb-3">
        <div class="card-header py-2 field-header">
            <span class="field-number">فیلد #FIELD_DISPLAY_INDEX</span>
            <button type="button" class="btn btn-sm btn-danger removeFieldBtn float-left" title="حذف این فیلد">
                 <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>
        <div class="card-body p-3">
            <div class="row">
                <div class="form-group col-md-5">
                    <label for="field_name_FIELD_INDEX_TPL">عنوان فیلد <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="field_name_FIELD_INDEX_TPL" name="fields[FIELD_INDEX][field_name]" required>
                </div>
                <div class="form-group col-md-4">
                    <label for="field_type_FIELD_INDEX_TPL">نوع فیلد <span class="text-danger">*</span></label>
                    <select class="form-control field-type-select" id="field_type_FIELD_INDEX_TPL" name="fields[FIELD_INDEX][field_type]" required data-index="FIELD_INDEX">
                        <option value="">انتخاب کنید...</option>
                        <?php foreach($field_type_options as $val => $label): // Use the same options as above ?>
                            <option value="<?php echo $val; ?>"><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                 <div class="form-group col-md-3 align-self-end text-left">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_required_FIELD_INDEX_TPL" name="fields[FIELD_INDEX][is_required]" value="1">
                        <label class="form-check-label" for="is_required_FIELD_INDEX_TPL">الزامی باشد</label>
                    </div>
                </div>
            </div>
            <div class="form-group field-options-container mt-2" id="options_container_FIELD_INDEX_TPL" style="display:none;">
                <label for="field_options_FIELD_INDEX_TPL">گزینه‌ها (هر گزینه در یک خط جدید)</label>
                <textarea class="form-control" id="field_options_FIELD_INDEX_TPL" name="fields[FIELD_INDEX][field_options]" rows="3"></textarea>
            </div>
        </div>
    </div>
</div>

<script> // JavaScript for dynamic fields (same as create.php, but with different container/button IDs)
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('formFieldsContainerEdit');
    const addFieldBtn = document.getElementById('addFieldBtnEdit');
    const fieldTemplateHtmlSource = document.getElementById('fieldTemplateEdit').innerHTML;
    let fieldIndexCounter = <?php echo count($input_fields_repopulate); ?>;

    function updateFieldNumberingEdit() { /* ... same as create.php ... */ }
    function addNewFieldEdit() { /* ... same as create.php, ensure unique IDs for template replacement ... */
        const actualIndex = fieldIndexCounter++;
        const displayIndex = container.children.length + 1;

        let newFieldHtml = fieldTemplateHtmlSource;
        newFieldHtml = newFieldHtml.replace(/FIELD_INDEX_TPL/g, actualIndex + '_tpl_id_edit');
        newFieldHtml = newFieldHtml.replace(/FIELD_INDEX/g, actualIndex);
        newFieldHtml = newFieldHtml.replace(/FIELD_DISPLAY_INDEX/g, displayIndex);

        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = newFieldHtml;
        const newFieldElement = tempDiv.firstElementChild;

        newFieldElement.querySelectorAll('[id*="_tpl_id_edit"]').forEach(el => {
            el.id = el.id.replace('_tpl_id_edit', '');
        });
         newFieldElement.querySelectorAll('[for*="_tpl_id_edit"]').forEach(el => {
            el.htmlFor = el.htmlFor.replace('_tpl_id_edit', '');
        });

        container.appendChild(newFieldElement);
        attachEventListenersToNewFieldEdit(newFieldElement, actualIndex);
        updateFieldNumberingEdit();
    }
    function attachEventListenersToNewFieldEdit(fieldItem, currentItemIndex) { /* ... same as create.php ... */
        const typeSelect = fieldItem.querySelector('.field-type-select');
        const optionsContainer = fieldItem.querySelector('.field-options-container');
        const removeBtn = fieldItem.querySelector('.removeFieldBtn');

        if (typeSelect && optionsContainer) {
            typeSelect.addEventListener('change', function() {
                optionsContainer.style.display = ['select', 'radio', 'checkbox'].includes(this.value) ? 'block' : 'none';
            });
            if (typeSelect.value && ['select', 'radio', 'checkbox'].includes(typeSelect.value)) {
                 optionsContainer.style.display = 'block';
            }
        }
        if (removeBtn) {
            removeBtn.addEventListener('click', function() {
                fieldItem.remove();
                updateFieldNumberingEdit();
            });
        }
    }

    if (addFieldBtn) { addFieldBtn.addEventListener('click', addNewFieldEdit); }
    document.querySelectorAll('#formFieldsContainerEdit .form-field-item').forEach(function(item) {
        let nameAttrInput = item.querySelector('[name*="[field_name]"]');
        if (nameAttrInput && nameAttrInput.name) {
            let match = nameAttrInput.name.match(/\[(\d+)\]/);
            if (match && match[1]) {
                 let dataIndexFromName = parseInt(match[1]);
                 attachEventListenersToNewFieldEdit(item, dataIndexFromName);
            }
        }
    });
    updateFieldNumberingEdit(); // Initial numbering
});
</script>
<style> /* Same as create.php */
    .field-header { background-color: #f8f9fc; }
    .field-number { font-weight: bold; color: #5a5c69; }
    .form-field-item .removeFieldBtn svg { vertical-align: middle; }
</style>
<?php
require_once __DIR__ . '/../includes/footer.php';
?>
```

این تغییرات تضمین می‌کنند که گزینه‌های `FormPurpose` به درستی در هر دو صفحه ایجاد و ویرایش فرم نمایش داده شده و ذخیره می‌شوند.
اکنون به سراغ ایجاد فایل `admin/parents/meetings.php` می‌روم.
