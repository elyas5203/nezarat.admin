<?php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth

$csrf_token_form_create = generate_csrf_token('form_create_action');

$errors = [];
$input_form_name = '';
$input_form_description = '';
$input_department_id = '';
$input_fields_repopulate = []; // To repopulate fields on error

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
    // 'file' => 'آپلود فایل', // Future consideration
];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'form_create_action')) {
        $errors['csrf'] = 'خطای CSRF! درخواست نامعتبر.';
    } else {
        $input_form_name = sanitize_input($_POST['form_name'] ?? '');
        $input_form_description = sanitize_input($_POST['form_description'] ?? '');
        $input_department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;

        // Repopulate fields for error display
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
        if ($input_department_id !== null) {
            $dept_exists = false;
            foreach($available_departments as $ad) if($ad['DepartmentID'] == $input_department_id) $dept_exists = true;
            if(!$dept_exists && $input_department_id != 0) $errors['department_id'] = "بخش نامعتبر."; // 0 or empty is valid for no department
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
                $stmt_insert_form = $conn->prepare("INSERT INTO Forms (FormName, Description, DepartmentID, CreatedByUserID, CreatedAt) VALUES (?, ?, ?, ?, NOW())");
                if(!$stmt_insert_form) throw new Exception("آماده سازی فرم ناموفق: " . $conn->error);
                // DepartmentID can be NULL if $input_department_id is null (or 0 if your DB schema requires an int and 0 means no department)
                $actual_department_id = ($input_department_id == 0 || $input_department_id === null) ? null : $input_department_id;

                $stmt_insert_form->bind_param("ssii", $input_form_name, $input_form_description, $actual_department_id, $created_by_user_id);

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
                    header("Location: index.php?action_status=success_create&message=" . urlencode("فرم با موفقیت ایجاد شد."));
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
        <ul>
            <?php foreach ($errors as $error_key => $error_msg):
                if (preg_match('/field_(\d+)_(name|type|options)/', $error_key, $matches)) {
                    $field_num = $matches[1] + 1; $part_map = ['name'=>'عنوان فیلد','type'=>'نوع فیلد','options'=>'گزینه‌های فیلد'];
                    echo "<li>خطا در ".$part_map[$matches[2]]." $field_num: " . htmlspecialchars($error_msg) . "</li>";
                } else { echo "<li>" . htmlspecialchars($error_msg) . "</li>"; }
            endforeach; ?>
        </ul>
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
                    <option value="0">-- هیچکدام --</option>
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
                    <?php
                    $form_purpose_options_create = [
                        'general' => 'عمومی', 'self_assessment' => 'خوداظهاری مدرس',
                        'class_observation' => 'بازدید کلاسی', 'parent_survey' => 'نظرسنجی اولیا',
                        'service_report' => 'گزارش خدمت گزاری (پرورشی)',
                    ];
                    global $input_form_purpose; // Ensure $input_form_purpose is accessible
                    if (!isset($input_form_purpose)) $input_form_purpose = 'general'; // Default if not set by POST

                    foreach ($form_purpose_options_create as $fp_val => $fp_label): ?>
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
                    <label for="field_name_FIELD_INDEX">عنوان فیلد <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="field_name_FIELD_INDEX" name="fields[FIELD_INDEX][field_name]" required>
                </div>
                <div class="form-group col-md-4">
                    <label for="field_type_FIELD_INDEX">نوع فیلد <span class="text-danger">*</span></label>
                    <select class="form-control field-type-select" id="field_type_FIELD_INDEX" name="fields[FIELD_INDEX][field_type]" required data-index="FIELD_INDEX">
                        <option value="">انتخاب کنید...</option>
                        <?php foreach($field_type_options as $val => $label): ?>
                            <option value="<?php echo $val; ?>"><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-3 align-self-end text-left">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_required_FIELD_INDEX" name="fields[FIELD_INDEX][is_required]" value="1">
                        <label class="form-check-label" for="is_required_FIELD_INDEX">الزامی باشد</label>
                    </div>
                </div>
            </div>
            <div class="form-group field-options-container mt-2" id="options_container_FIELD_INDEX" style="display:none;">
                <label for="field_options_FIELD_INDEX">گزینه‌ها (هر گزینه در یک خط جدید)</label>
                <textarea class="form-control" id="field_options_FIELD_INDEX" name="fields[FIELD_INDEX][field_options]" rows="3"></textarea>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('formFieldsContainer');
    const addFieldBtn = document.getElementById('addFieldBtn');
    const fieldTemplateHtml = document.getElementById('fieldTemplate').innerHTML;
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
        const actualIndex = fieldIndexCounter++; // Use a counter that always increments
        const displayIndex = container.children.length + 1;

        const newFieldHtml = fieldTemplateHtml.replace(/FIELD_INDEX/g, actualIndex)
                                          .replace(/FIELD_DISPLAY_INDEX/g, displayIndex);

        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = newFieldHtml;
        const newFieldElement = tempDiv.firstElementChild;
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
                if (['select', 'radio', 'checkbox'].includes(this.value)) {
                    optionsContainer.style.display = 'block';
                } else {
                    optionsContainer.style.display = 'none';
                }
            });
            // Trigger change for pre-populated fields
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

    document.querySelectorAll('#formFieldsContainer .form-field-item').forEach(function(item, idx) {
        // For repopulated fields, the index in the name="fields[INDEX]" is already set by PHP.
        // We just need to ensure JS event listeners are attached.
        // The `idx` here is the visual order, `fieldIndexCounter` tracks the next available unique index for new fields.
        let dataIndexFromName = item.querySelector('[name*="[field_name]"]').name.match(/\[(\d+)\]/)[1];
        attachEventListenersToNewField(item, parseInt(dataIndexFromName));
    });

    <?php if (empty($input_fields_repopulate) && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
        addNewField();
    <?php endif; ?>
    updateFieldNumbering(); // Initial numbering for any repopulated fields
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
