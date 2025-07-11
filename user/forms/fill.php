<?php
// user/forms/fill.php
require_once __DIR__ . '/../includes/header.php';

$form_id_to_fill = null;
$form_data = null;
$form_fields_for_fill = [];
$fill_errors = [];
$fill_success_message = '';
$user_id = get_current_user_id();

// Get parameters from URL for initial load
$class_id_from_url = isset($_GET['class_id']) && is_numeric($_GET['class_id']) ? (int)$_GET['class_id'] : null;
$meeting_id_from_url = isset($_GET['meeting_id']) && is_numeric($_GET['meeting_id']) ? (int)$_GET['meeting_id'] : null;
$source_page_from_url = sanitize_input($_GET['source'] ?? '');

if (isset($_GET['form_id']) && is_numeric($_GET['form_id'])) {
    $form_id_to_fill = (int)$_GET['form_id'];
    // Generate CSRF token specific to this form and potentially context (class/meeting)
    $csrf_token_form_fill = generate_csrf_token('form_fill_action_' . $form_id_to_fill . '_' . ($class_id_from_url ?? 'c0') . '_' . ($meeting_id_from_url ?? 'm0'));

    $stmt_form = $conn->prepare("SELECT FormID, FormName, Description FROM Forms WHERE FormID = ?");
    if ($stmt_form) {
        $stmt_form->bind_param("i", $form_id_to_fill);
        $stmt_form->execute();
        $result_form = $stmt_form->get_result();
        if ($result_form->num_rows === 1) {
            $form_data = $result_form->fetch_assoc();
            $stmt_fields = $conn->prepare("SELECT FieldID, FieldName, FieldType, Options, IsRequired FROM FormFields WHERE FormID = ? ORDER BY SortOrder ASC, FieldID ASC");
            if ($stmt_fields) {
                $stmt_fields->bind_param("i", $form_id_to_fill);
                $stmt_fields->execute();
                $result_fields = $stmt_fields->get_result();
                while ($field = $result_fields->fetch_assoc()) {
                    $form_fields_for_fill[] = $field;
                }
                $stmt_fields->close();
            } else { $fill_errors['load'] = "خطا بارگذاری فیلدها: " . $conn->error; }
        } else { $fill_errors['load'] = "فرم یافت نشد."; }
        $stmt_form->close();
    } else { $fill_errors['load'] = "خطا بارگذاری فرم: " . $conn->error; }
} else { $fill_errors['load'] = "شناسه فرم نامعتبر."; }


if ($_SERVER['REQUEST_METHOD'] === 'POST' && $form_data && isset($_POST['submit_form_action'])) {
    // Get hidden fields from POST for submission logic
    $class_id_for_submission_post = isset($_POST['class_id_hidden']) && is_numeric($_POST['class_id_hidden']) ? (int)$_POST['class_id_hidden'] : null;
    $meeting_id_for_submission_post = isset($_POST['meeting_id_hidden']) && is_numeric($_POST['meeting_id_hidden']) ? (int)$_POST['meeting_id_hidden'] : null;
    $source_page_for_redirect_post = sanitize_input($_POST['source_page_hidden'] ?? '');

    // Verify CSRF token using the same parameters used for generation
    if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'form_fill_action_' . $form_id_to_fill . '_' . ($class_id_for_submission_post ?? 'c0') . '_' . ($meeting_id_for_submission_post ?? 'm0'))) {
        $fill_errors['csrf'] = 'خطای CSRF! درخواست نامعتبر یا توکن منقضی شده.';
    } else {
        $submitted_values = [];
        foreach ($form_fields_for_fill as $field) {
            $field_post_key = 'field_' . $field['FieldID'];
            $value = $_POST[$field_post_key] ?? null;

            if ($field['IsRequired'] && (is_null($value) || $value === '' || (is_array($value) && empty(array_filter($value))))) {
                $fill_errors[$field_post_key] = "فیلد \"" . htmlspecialchars($field['FieldName']) . "\" الزامی است.";
            }

            if ($field['FieldType'] === 'number' && !empty($value) && !is_numeric($value)) {
                 $fill_errors[$field_post_key] = "فیلد \"" . htmlspecialchars($field['FieldName']) . "\" باید عدد باشد.";
            }

            if ($field['FieldType'] === 'checkbox' && is_array($value)) {
                $submitted_values[$field['FieldID']] = json_encode(array_values(array_map('sanitize_input', $value)), JSON_UNESCAPED_UNICODE);
            } elseif (is_array($value)) {
                 $fill_errors[$field_post_key] = "مقدار نامعتبر برای \"" . htmlspecialchars($field['FieldName']) . "\".";
            } else {
                $submitted_values[$field['FieldID']] = sanitize_input($value);
            }
        }

        if (empty($fill_errors)) {
            $conn->begin_transaction();
            try {
                $class_id_db = filter_var($class_id_for_submission_post, FILTER_VALIDATE_INT) ?: null;
                $meeting_id_db = filter_var($meeting_id_for_submission_post, FILTER_VALIDATE_INT) ?: null;
                $session_id_db = null; // Not used yet

                $stmt_submission = $conn->prepare("INSERT INTO FormSubmissions (FormID, UserID, SubmissionDate, ClassID, SessionID, MeetingID) VALUES (?, ?, NOW(), ?, ?, ?)");
                if (!$stmt_submission) throw new Exception("آماده سازی ثبت پاسخ ناموفق: " . $conn->error);
                $stmt_submission->bind_param("iiiii", $form_id_to_fill, $user_id, $class_id_db, $session_id_db, $meeting_id_db);
                if (!$stmt_submission->execute()) throw new Exception("ثبت پاسخ اصلی ناموفق: " . $stmt_submission->error);
                $new_submission_id = $stmt_submission->insert_id;
                $stmt_submission->close();

                $stmt_value = $conn->prepare("INSERT INTO FormSubmissionValues (SubmissionID, FormFieldID, FieldValue) VALUES (?, ?, ?)");
                if (!$stmt_value) throw new Exception("آماده سازی مقادیر پاسخ ناموفق: " . $conn->error);
                foreach ($submitted_values as $form_field_id_sv => $field_val_prepared_sv) {
                    $final_val_sv = is_null($field_val_prepared_sv) ? null : (string)$field_val_prepared_sv;
                    if (mb_strlen($final_val_sv, 'UTF-8') > 65530) { // Slightly less than TEXT max for safety
                        $final_val_sv = mb_substr($final_val_sv, 0, 65530, 'UTF-8') . '... [کوتاه شده]';
                    }
                    $stmt_value->bind_param("iis", $new_submission_id, $form_field_id_sv, $final_val_sv);
                    if (!$stmt_value->execute()) throw new Exception("ذخیره مقدار فیلد ID $form_field_id_sv ناموفق: " . $stmt_value->error);
                }
                $stmt_value->close();
                $conn->commit();

                // Regenerate CSRF for the specific context if user might submit again for same context
                regenerate_csrf_token('form_fill_action_' . $form_id_to_fill . '_' . ($class_id_from_url ?? 'c0') . '_' . ($meeting_id_from_url ?? 'm0'));

                $redirect_url_after_fill_val = ($user_base_url ?? '/my_site/user') . "/forms/index.php";
                if ($source_page_for_redirect_post === 'monitoring' && $class_id_db) {
                    $redirect_url_after_fill_val = ($user_base_url ?? '/my_site/user') . "/monitoring/assessment_history.php?class_id=" . $class_id_db;
                } elseif ($source_page_for_redirect_post === 'parents_module' && $meeting_id_db) {
                    if ($class_id_db) { // If class is also known, redirect to its history
                         $redirect_url_after_fill_val = ($user_base_url ?? '/my_site/user') . "/monitoring/assessment_history.php?class_id=" . $class_id_db . "&type=parent_meeting_report"; // Example type
                    } else { // Fallback if only meeting_id is known for some reason
                        $redirect_url_after_fill_val = ($user_base_url ?? '/my_site/user') . "/parents/my_meetings.php"; // Needs to be created
                    }
                }
                $_SESSION['flash_message'] = ['type' => 'success', 'text' => "فرم '" . htmlspecialchars($form_data['FormName']) . "' با موفقیت ثبت شد."];
                header("Location: " . $redirect_url_after_fill_val . (strpos($redirect_url_after_fill_val, '?') ? '&' : '?') ."action_status=success");
                exit;
            } catch (Exception $e) { $conn->rollback(); $fill_errors['db'] = "خطای دیتابیس: " . $e->getMessage(); }
        }
    }
    // Regenerate token on POST error as well
    $csrf_token_form_fill = regenerate_csrf_token('form_fill_action_' . $form_id_to_fill . '_' . ($class_id_from_url ?? 'c0') . '_' . ($meeting_id_from_url ?? 'm0'));
}
?>
<div class="page-header">
    <h1>تکمیل فرم: <?php echo htmlspecialchars($form_data['FormName'] ?? 'بارگذاری نشده'); ?></h1>
    <div class="page-header-actions">
        <?php
        $back_link_fill = ($user_base_url ?? '/my_site/user') . "/forms/index.php";
        if ($source_page_from_url === 'monitoring' && $class_id_from_url) {
            $back_link_fill = ($user_base_url ?? '/my_site/user') . "/monitoring/submit_self_assessment.php?class_id=" . $class_id_from_url;
        } elseif ($source_page_from_url === 'parents_module' && $class_id_from_url) { // Assuming it came from a class-specific meeting list for parents module
             $back_link_fill = ($user_base_url ?? '/my_site/user') . "/parents/my_class_meetings.php?class_id=" . $class_id_from_url; // Hypothetical page
        }
        ?>
        <a href="<?php echo $back_link_fill; ?>" class="btn btn-secondary">
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"></polyline></svg>
            <span>بازگشت</span>
        </a>
    </div>
</div>

<?php if (!empty($fill_errors['load'])): ?> <div class="alert alert-danger"><?php echo htmlspecialchars($fill_errors['load']); ?></div>
<?php elseif ($form_data): ?>
    <?php if (!empty($fill_errors)): ?>
        <div class="alert alert-danger"><p><strong>خطا در ثبت فرم:</strong></p><ul>
            <?php foreach ($fill_errors as $error_msg): ?><li><?php echo htmlspecialchars($error_msg); ?></li><?php endforeach; ?>
        </ul></div>
    <?php endif; ?>
    <?php /* Success message is handled by redirect and flash message now */ ?>

    <?php if (empty($fill_success_message) && empty($fill_errors['load'])): ?>
    <div class="card">
        <div class="card-header"><h5 class="card-title mb-0"><?php echo htmlspecialchars($form_data['FormName']); ?></h5></div>
        <div class="card-body form-fill-container">
            <?php if (!empty($form_data['Description'])): ?>
                <p class="form-description text-muted lead"><?php echo nl2br(htmlspecialchars($form_data['Description'])); ?></p><hr class="my-4">
            <?php endif; ?>
            <form action="fill.php?form_id=<?php echo $form_id_to_fill; ?><?php if($class_id_from_url) echo '&class_id='.$class_id_from_url; ?><?php if($meeting_id_from_url) echo '&meeting_id='.$meeting_id_from_url; ?><?php if($source_page_from_url) echo '&source='.$source_page_from_url; ?>" method="POST" id="fillForm" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_form_fill; ?>">
                <?php if ($class_id_from_url): ?><input type="hidden" name="class_id_hidden" value="<?php echo $class_id_from_url; ?>"><?php endif; ?>
                <?php if ($meeting_id_from_url): ?><input type="hidden" name="meeting_id_hidden" value="<?php echo $meeting_id_from_url; ?>"><?php endif; ?>
                <?php if ($source_page_from_url): ?><input type="hidden" name="source_page_hidden" value="<?php echo $source_page_from_url; ?>"><?php endif; ?>

                <?php if (!empty($form_fields_for_fill)): ?>
                    <?php foreach ($form_fields_for_fill as $index => $field):
                        $field_html_id = "form_field_" . $field['FieldID'];
                        $field_post_key = "field_" . $field['FieldID'];
                        $value_repopulate = $_POST[$field_post_key] ?? '';
                        $options = [];
                        if (in_array($field['FieldType'], ['select', 'radio', 'checkbox']) && !empty($field['Options'])) {
                            $decoded = json_decode($field['Options'], true);
                             if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) $options = $decoded;
                            else $options = array_values(array_filter(array_map('trim', explode("\n", $field['Options']))));
                        }
                    ?>
                    <div class="form-group mb-4">
                        <label for="<?php echo $field_html_id; ?>" class="form-label d-block mb-2" style="font-size: 1.05rem; font-weight: 500;">
                            <?php echo htmlspecialchars($field['FieldName']); ?>
                            <?php if ($field['IsRequired']): ?><span class="text-danger font-weight-bold">*</span><?php endif; ?>
                        </label>
                        <?php if ($field['FieldType'] === 'text'): ?> <input type="text" class="form-control form-control-lg <?php echo isset($fill_errors[$field_post_key]) ? 'is-invalid' : ''; ?>" id="<?php echo $field_html_id; ?>" name="<?php echo $field_post_key; ?>" value="<?php echo htmlspecialchars(is_array($value_repopulate) ? '' : $value_repopulate); ?>" <?php echo $field['IsRequired'] ? 'required' : ''; ?>>
                        <?php elseif ($field['FieldType'] === 'textarea'): ?> <textarea class="form-control form-control-lg <?php echo isset($fill_errors[$field_post_key]) ? 'is-invalid' : ''; ?>" id="<?php echo $field_html_id; ?>" name="<?php echo $field_post_key; ?>" rows="4" <?php echo $field['IsRequired'] ? 'required' : ''; ?>><?php echo htmlspecialchars(is_array($value_repopulate) ? '' : $value_repopulate); ?></textarea>
                        <?php elseif ($field['FieldType'] === 'number'): ?> <input type="number" class="form-control form-control-lg <?php echo isset($fill_errors[$field_post_key]) ? 'is-invalid' : ''; ?>" id="<?php echo $field_html_id; ?>" name="<?php echo $field_post_key; ?>" value="<?php echo htmlspecialchars(is_array($value_repopulate) ? '' : $value_repopulate); ?>" <?php echo $field['IsRequired'] ? 'required' : ''; ?>>
                        <?php elseif ($field['FieldType'] === 'date'): ?> <input type="text" class="form-control form-control-lg persian-date-picker <?php echo isset($fill_errors[$field_post_key]) ? 'is-invalid' : ''; ?>" id="<?php echo $field_html_id; ?>" name="<?php echo $field_post_key; ?>" value="<?php echo htmlspecialchars(is_array($value_repopulate) ? '' : $value_repopulate); ?>" placeholder="YYYY-MM-DD" <?php echo $field['IsRequired'] ? 'required' : ''; ?>> <small class="form-text text-muted">فرمت: سال/ماه/روز.</small>
                        <?php elseif ($field['FieldType'] === 'select'): ?>
                            <select class="form-control form-control-lg custom-select <?php echo isset($fill_errors[$field_post_key]) ? 'is-invalid' : ''; ?>" id="<?php echo $field_html_id; ?>" name="<?php echo $field_post_key; ?>" <?php echo $field['IsRequired'] ? 'required' : ''; ?>>
                                <option value="">-- انتخاب --</option>
                                <?php foreach ($options as $opt_val): ?> <option value="<?php echo htmlspecialchars($opt_val); ?>" <?php echo ((is_array($value_repopulate)? '' : $value_repopulate) == $opt_val) ? 'selected' : ''; ?>><?php echo htmlspecialchars($opt_val); ?></option> <?php endforeach; ?>
                            </select>
                        <?php elseif ($field['FieldType'] === 'radio'): ?>
                            <div class="pt-2 radio-checkbox-group <?php echo isset($fill_errors[$field_post_key]) ? 'is-invalid-group' : ''; ?>">
                            <?php foreach ($options as $opt_idx => $opt_val): $radio_html_id = $field_html_id . "_" . $opt_idx; ?>
                                <div class="form-check form-check-inline mr-3 mb-2"><input class="form-check-input" type="radio" name="<?php echo $field_post_key; ?>" id="<?php echo $radio_html_id; ?>" value="<?php echo htmlspecialchars($opt_val); ?>" <?php echo ((is_array($value_repopulate)? '' : $value_repopulate) == $opt_val) ? 'checked' : ''; ?> <?php echo $field['IsRequired'] ? 'required' : ''; ?>> <label class="form-check-label" for="<?php echo $radio_html_id; ?>"><?php echo htmlspecialchars($opt_val); ?></label></div>
                            <?php endforeach; ?></div>
                        <?php elseif ($field['FieldType'] === 'checkbox'): ?>
                             <div class="pt-2 radio-checkbox-group <?php echo isset($fill_errors[$field_post_key]) ? 'is-invalid-group' : ''; ?>">
                            <?php foreach ($options as $opt_idx => $opt_val): $check_html_id = $field_html_id . "_" . $opt_idx; $is_checked = is_array($value_repopulate) && in_array($opt_val, $value_repopulate); ?>
                                <div class="form-check form-check-inline mr-3 mb-2"><input class="form-check-input" type="checkbox" name="<?php echo $field_post_key; ?>[]" id="<?php echo $check_html_id; ?>" value="<?php echo htmlspecialchars($opt_val); ?>" <?php echo $is_checked ? 'checked' : ''; ?>> <label class="form-check-label" for="<?php echo $check_html_id; ?>"><?php echo htmlspecialchars($opt_val); ?></label></div>
                            <?php endforeach; ?></div>
                             <?php if ($field['IsRequired']): ?> <small class="form-text text-muted d-block mt-1">حداقل یک گزینه باید انتخاب شود.</small><?php endif; ?>
                        <?php endif; ?>
                         <?php if (isset($fill_errors[$field_post_key])): ?> <div class="invalid-feedback d-block mt-1"><?php echo $fill_errors[$field_post_key]; ?></div>
                         <?php else: ?> <div class="invalid-feedback mt-1">این فیلد الزامی است.</div> <?php endif; ?>
                    </div>
                    <?php if ($index < count($form_fields_for_fill) - 1): ?><hr class="my-4"><?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?> <p class="text-muted">این فرم فیلدی برای تکمیل ندارد.</p> <?php endif; ?>
                <?php if (!empty($form_fields_for_fill)): ?>
                <div class="mt-5"><button type="submit" name="submit_form_action" class="btn btn-primary-user btn-lg">
                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline><line x1="10" y1="14" x2="16" y2="14"></line><line x1="10" y1="18" x2="16" y2="18"></line></svg>
                    <span>ثبت پاسخ فرم</span></button></div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>
<style> /* Styles from preview.php, slightly adapted */
    .form-fill-container .form-label { color: #333; }
    .form-fill-container .form-control-lg { padding: .7rem 1.1rem; font-size: 1rem; }
    .form-fill-container .custom-select.form-control-lg { height: calc(1.5em + 1.4rem + 2px); }
    .radio-checkbox-group .form-check-label { font-size: 0.95rem; margin-right: 0.3rem; }
    .radio-checkbox-group .form-check-input { width: 1.05em; height: 1.05em; margin-top: 0.2em; }
    .is-invalid-group { border: 1px solid #dc3545 !important; border-radius: .25rem; padding: .5rem; }
</style>
<script> /* Basic Bootstrap validation */
(function () { 'use strict'; var forms = document.querySelectorAll('.needs-validation');
  Array.prototype.slice.call(forms).forEach(function (form) {
  form.addEventListener('submit', function (event) { if (!form.checkValidity()) { event.preventDefault(); event.stopPropagation(); } form.classList.add('was-validated');}, false);});})();
// TODO: Add Persian Date Picker initialization script if needed
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
