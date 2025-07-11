<?php
// admin/forms/preview.php
// This page is for previewing the form structure. It does not submit data.
// It can be accessed by admin or users who have permission to view/fill the form.
// For simplicity, using the admin header which checks for admin login.
require_once __DIR__ . '/../includes/header.php';

$form_id_to_preview = null;
$form_data = null;
$form_fields_for_preview = []; // Renamed to avoid conflict with $form_fields if used elsewhere
$preview_errors = [];

if (isset($_GET['form_id']) && is_numeric($_GET['form_id'])) {
    $form_id_to_preview = (int)$_GET['form_id'];

    // Fetch Form Details
    $stmt_form = $conn->prepare("SELECT FormID, FormName, Description FROM Forms WHERE FormID = ?");
    if ($stmt_form) {
        $stmt_form->bind_param("i", $form_id_to_preview);
        $stmt_form->execute();
        $result_form = $stmt_form->get_result();
        if ($result_form->num_rows === 1) {
            $form_data = $result_form->fetch_assoc();

            // Fetch Form Fields
            $stmt_fields = $conn->prepare("SELECT FieldName, FieldType, Options, IsRequired FROM FormFields WHERE FormID = ? ORDER BY SortOrder ASC");
            if ($stmt_fields) {
                $stmt_fields->bind_param("i", $form_id_to_preview);
                $stmt_fields->execute();
                $result_fields = $stmt_fields->get_result();
                while ($field = $result_fields->fetch_assoc()) {
                    $form_fields_for_preview[] = $field;
                }
                $stmt_fields->close();
                if (empty($form_fields_for_preview) && $result_fields->num_rows === 0) {
                    // This is not an error, just a form with no fields.
                }
            } else { $preview_errors[] = "خطا در بارگذاری فیلدهای فرم: " . $conn->error; }
        } else { $preview_errors[] = "فرم مورد نظر برای پیش‌نمایش یافت نشد."; }
        $stmt_form->close();
    } else { $preview_errors[] = "خطا در بارگذاری اطلاعات فرم: " . $conn->error; }
} else { $preview_errors[] = "شناسه فرم برای پیش‌نمایش نامعتبر است."; }

?>
<div class="page-header">
    <h1>پیش‌نمایش فرم: <?php echo htmlspecialchars($form_data['FormName'] ?? '...'); ?></h1>
    <div class="page-header-actions">
        <a href="index.php" class="btn btn-secondary">
             <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
            <span>بازگشت به لیست فرم‌ها</span>
        </a>
        <?php if ($form_data && empty($preview_errors)): // Show edit button only if form loaded successfully ?>
        <a href="edit.php?form_id=<?php echo $form_id_to_preview; ?>" class="btn btn-warning">
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
            <span>ویرایش این فرم</span>
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($preview_errors)): ?>
    <div class="alert alert-danger">
        <ul><?php foreach ($preview_errors as $err): ?><li><?php echo htmlspecialchars($err); ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<?php if ($form_data && empty($preview_errors)): ?>
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0"><?php echo htmlspecialchars($form_data['FormName']); ?></h5>
    </div>
    <div class="card-body form-preview-container">
        <?php if (!empty($form_data['Description'])): ?>
            <p class="form-description text-muted lead"><?php echo nl2br(htmlspecialchars($form_data['Description'])); ?></p>
            <hr class="my-4">
        <?php endif; ?>

        <form id="formPreviewRender" class="needs-validation" novalidate onsubmit="alert('این یک پیش‌نمایش است و اطلاعات ثبت نمی‌شوند.'); return false;">
            <?php if (!empty($form_fields_for_preview)): ?>
                <?php foreach ($form_fields_for_preview as $index => $field):
                    $field_id_attr = "preview_field_" . $form_id_to_preview . "_" . $index;
                    $field_name_attr = "preview_field_name_" . $index; // Name attribute for form submission (though disabled)
                    $options = [];
                    if (in_array($field['FieldType'], ['select', 'radio', 'checkbox']) && !empty($field['Options'])) {
                        $decoded = json_decode($field['Options'], true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $options = $decoded;
                        } else {
                            $options = array_values(array_filter(array_map('trim', explode("\n", $field['Options']))));
                        }
                    }
                ?>
                <div class="form-group mb-4">
                    <label for="<?php echo $field_id_attr; ?>" class="form-label d-block mb-2" style="font-size: 1.05rem; font-weight: 500;">
                        <?php echo htmlspecialchars($field['FieldName']); ?>
                        <?php if ($field['IsRequired']): ?><span class="text-danger font-weight-bold">*</span><?php endif; ?>
                    </label>

                    <?php if ($field['FieldType'] === 'text'): ?>
                        <input type="text" class="form-control form-control-lg" id="<?php echo $field_id_attr; ?>" name="<?php echo $field_name_attr; ?>" <?php echo $field['IsRequired'] ? 'required' : ''; ?>>
                    <?php elseif ($field['FieldType'] === 'textarea'): ?>
                        <textarea class="form-control form-control-lg" id="<?php echo $field_id_attr; ?>" name="<?php echo $field_name_attr; ?>" rows="4" <?php echo $field['IsRequired'] ? 'required' : ''; ?>></textarea>
                    <?php elseif ($field['FieldType'] === 'number'): ?>
                        <input type="number" class="form-control form-control-lg" id="<?php echo $field_id_attr; ?>" name="<?php echo $field_name_attr; ?>" <?php echo $field['IsRequired'] ? 'required' : ''; ?>>
                    <?php elseif ($field['FieldType'] === 'date'): ?>
                        <input type="text" class="form-control form-control-lg persian-date-picker-preview" id="<?php echo $field_id_attr; ?>" name="<?php echo $field_name_attr; ?>" <?php echo $field['IsRequired'] ? 'required' : ''; ?> placeholder="YYYY/MM/DD">
                         <small class="form-text text-muted">فرمت تاریخ: سال/ماه/روز (مثال: 1403/05/15)</small>
                    <?php elseif ($field['FieldType'] === 'select'): ?>
                        <select class="form-control form-control-lg custom-select" id="<?php echo $field_id_attr; ?>" name="<?php echo $field_name_attr; ?>" <?php echo $field['IsRequired'] ? 'required' : ''; ?>>
                            <option value="">-- لطفاً انتخاب کنید --</option>
                            <?php foreach ($options as $opt_val): ?>
                                <option value="<?php echo htmlspecialchars($opt_val); ?>"><?php echo htmlspecialchars($opt_val); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ($field['FieldType'] === 'radio'): ?>
                        <div class="pt-2 radio-checkbox-group">
                        <?php foreach ($options as $opt_idx => $opt_val):
                            $radio_id_attr = $field_id_attr . "_" . $opt_idx;
                        ?>
                            <div class="form-check form-check-inline mr-3 mb-2">
                                <input class="form-check-input" type="radio" name="<?php echo $field_name_attr; ?>" id="<?php echo $radio_id_attr; ?>" value="<?php echo htmlspecialchars($opt_val); ?>" <?php echo $field['IsRequired'] ? 'required' : ''; ?>>
                                <label class="form-check-label" for="<?php echo $radio_id_attr; ?>"><?php echo htmlspecialchars($opt_val); ?></label>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php elseif ($field['FieldType'] === 'checkbox'): ?>
                         <div class="pt-2 radio-checkbox-group">
                        <?php foreach ($options as $opt_idx => $opt_val):
                             $check_id_attr = $field_id_attr . "_" . $opt_idx;
                        ?>
                            <div class="form-check form-check-inline mr-3 mb-2">
                                <input class="form-check-input" type="checkbox" name="<?php echo $field_name_attr; ?>[]" id="<?php echo $check_id_attr; ?>" value="<?php echo htmlspecialchars($opt_val); ?>">
                                <label class="form-check-label" for="<?php echo $check_id_attr; ?>"><?php echo htmlspecialchars($opt_val); ?></label>
                            </div>
                        <?php endforeach; ?>
                         </div>
                         <?php if ($field['IsRequired']): ?> <small class="form-text text-muted d-block mt-1">حداقل یک گزینه باید انتخاب شود (این مورد در پیش‌نمایش اعتبارسنجی نمی‌شود).</small><?php endif; ?>
                    <?php endif; ?>
                     <div class="invalid-feedback mt-1">این فیلد الزامی است.</div>
                </div>
                <?php if ($index < count($form_fields_for_preview) - 1): ?><hr class="my-4"><?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted">این فرم هنوز هیچ فیلدی ندارد. برای افزودن فیلد، <a href="edit.php?form_id=<?php echo $form_id_to_preview; ?>">فرم را ویرایش کنید</a>.</p>
            <?php endif; ?>

            <div class="mt-5">
                <button type="submit" class="btn btn-primary btn-lg" disabled>ثبت پاسخ (غیرفعال در پیش‌نمایش)</button>
                <small class="d-block mt-2 text-info">این صفحه فقط برای پیش‌نمایش ظاهر فرم است و اطلاعات وارد شده ثبت نخواهند شد.</small>
            </div>
        </form>
    </div>
</div>
<?php else: ?>
    <?php if(empty($preview_errors)): ?>
        <div class="alert alert-warning">فرم مورد نظر برای پیش‌نمایش یافت نشد یا خطایی در بارگذاری آن رخ داده است.</div>
    <?php endif; ?>
<?php endif; ?>

<style>
    .form-preview-container .form-label { color: #333; }
    .form-preview-container .form-control-lg { padding: .8rem 1.2rem; font-size: 1.1rem; }
    .form-preview-container .custom-select.form-control-lg { height: calc(1.5em + 1.6rem + 2px); }
    .radio-checkbox-group .form-check-label { font-size: 1rem; margin-right: 0.3rem; }
    .radio-checkbox-group .form-check-input { width: 1.1em; height: 1.1em; margin-top: 0.25em; }
</style>

<script>
// Basic script for Bootstrap validation feedback
(function () {
  'use strict'
  var forms = document.querySelectorAll('.needs-validation')
  Array.prototype.slice.call(forms)
    .forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
          event.preventDefault()
          event.stopPropagation()
        }
        form.classList.add('was-validated');
        // Actual submission is prevented by onsubmit attribute in the form tag for preview
      }, false)
    })
})()
// Placeholder for initializing a Persian Date Picker if you add one
// Example:
// $(document).ready(function() {
//   $(".persian-date-picker-preview").datepicker({
//     isRTL: true,
//     format: 'yyyy/mm/dd',
//     autoclose: true,
//     language: 'fa' // Requires Persian language file for the datepicker
//   });
// });
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
