<?php
// admin/forms/view_submission.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth

$submission_id_to_view = null;
$submission_data = null; // To store submission info (user, date, form name)
$form_fields_definitions = []; // To store the structure of the form (FieldName, FieldType, Options)
$submitted_field_values = []; // To store the actual values submitted by the user [FormFieldID => FieldValue]
$view_errors = [];

if (isset($_GET['submission_id']) && is_numeric($_GET['submission_id'])) {
    $submission_id_to_view = (int)$_GET['submission_id'];

    // Fetch Submission Details (FormID, UserID, SubmissionDate) and related Form and User info
    $stmt_sub = $conn->prepare("
        SELECT fs.SubmissionID, fs.FormID, fs.UserID, fs.SubmissionDate,
               f.FormName, f.Description AS FormDescription,
               u.Username, u.FirstName, u.LastName
        FROM FormSubmissions fs
        JOIN Forms f ON fs.FormID = f.FormID
        JOIN Users u ON fs.UserID = u.UserID
        WHERE fs.SubmissionID = ?
    ");
    if ($stmt_sub) {
        $stmt_sub->bind_param("i", $submission_id_to_view);
        $stmt_sub->execute();
        $result_sub = $stmt_sub->get_result();
        if ($result_sub->num_rows === 1) {
            $submission_data = $result_sub->fetch_assoc();
            $form_id_of_submission = $submission_data['FormID'];

            // Fetch all field definitions for this form, ordered by SortOrder
            $stmt_fields_def = $conn->prepare("
                SELECT FieldID, FieldName, FieldType, Options, IsRequired, SortOrder
                FROM FormFields
                WHERE FormID = ?
                ORDER BY SortOrder ASC, FieldID ASC
            ");
            if ($stmt_fields_def) {
                $stmt_fields_def->bind_param("i", $form_id_of_submission);
                $stmt_fields_def->execute();
                $result_fields_def = $stmt_fields_def->get_result();
                while ($field_def = $result_fields_def->fetch_assoc()) {
                    $form_fields_definitions[] = $field_def; // Store as an ordered array
                }
                $stmt_fields_def->close();
            } else { $view_errors[] = "خطا در بارگذاری ساختار فیلدهای فرم: " . $conn->error; }

            // Fetch submitted values for this submission
            $stmt_values = $conn->prepare("SELECT FormFieldID, FieldValue FROM FormSubmissionValues WHERE SubmissionID = ?");
            if ($stmt_values) {
                $stmt_values->bind_param("i", $submission_id_to_view);
                $stmt_values->execute();
                $result_values = $stmt_values->get_result();
                while ($val_row = $result_values->fetch_assoc()) {
                    $submitted_field_values[$val_row['FormFieldID']] = $val_row['FieldValue'];
                }
                $stmt_values->close();
            } else { $view_errors[] = "خطا در بارگذاری مقادیر پاسخ: " . $conn->error; }

        } else { $view_errors[] = "پاسخ مورد نظر یافت نشد."; }
        $stmt_sub->close();
    } else { $view_errors[] = "خطا در بارگذاری اطلاعات پاسخ: " . $conn->error; }
} else { $view_errors[] = "شناسه پاسخ نامعتبر است."; }

?>
<div class="page-header">
    <h1>مشاهده جزئیات پاسخ فرم: "<?php echo htmlspecialchars($submission_data['FormName'] ?? '...'); ?>"</h1>
    <div class="page-header-actions">
        <?php if ($submission_data): ?>
        <a href="submissions.php?form_id=<?php echo $submission_data['FormID']; ?>" class="btn btn-secondary">
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
            <span>بازگشت به لیست پاسخ‌ها</span>
        </a>
        <?php else: ?>
         <a href="index.php" class="btn btn-secondary">بازگشت به لیست فرم‌ها</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($view_errors)): ?>
    <div class="alert alert-danger"><ul><?php foreach ($view_errors as $err): ?><li><?php echo htmlspecialchars($err); ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if ($submission_data && empty($view_errors)): ?>
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">پاسخ از: <?php echo htmlspecialchars(($submission_data['FirstName'] ?? '') . ' ' . ($submission_data['LastName'] ?? '')) . ' (@' . htmlspecialchars($submission_data['Username']) . ')'; ?></h5>
            <small class="text-muted">تاریخ ثبت: <?php echo to_jalali($submission_data['SubmissionDate'], 'yyyy/MM/dd HH:mm:ss'); ?></small>
        </div>
    </div>
    <div class="card-body submission-view-container">
        <?php if (!empty($submission_data['FormDescription'])): ?>
            <p class="form-description text-muted"><em>توضیحات فرم اصلی: <?php echo nl2br(htmlspecialchars($submission_data['FormDescription'])); ?></em></p>
            <hr class="my-3">
        <?php endif; ?>

        <?php if (!empty($form_fields_definitions)): ?>
            <dl class="dl-horizontal submission-details-list">
                <?php foreach ($form_fields_definitions as $field_def):
                    $field_id_from_def = $field_def['FieldID'];
                    $submitted_value_for_field = $submitted_field_values[$field_id_from_def] ?? null;
                    $display_value = '<em class="text-muted">- بدون پاسخ -</em>'; // Default if no value

                    if ($submitted_value_for_field !== null && $submitted_value_for_field !== '') {
                        if (in_array($field_def['FieldType'], ['checkbox'])) {
                            $decoded_val = json_decode($submitted_value_for_field, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_val)) {
                                $display_value = !empty($decoded_val) ? implode('، ', array_map('htmlspecialchars', $decoded_val)) : '<em class="text-muted">- گزینه‌ای انتخاب نشده -</em>';
                            } else { // Fallback if not valid JSON (should not happen if saved correctly)
                                $display_value = htmlspecialchars($submitted_value_for_field);
                            }
                        } elseif ($field_def['FieldType'] === 'textarea') {
                            $display_value = nl2br(htmlspecialchars($submitted_value_for_field));
                        } else {
                            $display_value = htmlspecialchars($submitted_value_for_field);
                        }
                    }
                ?>
                <div class="submission-item row mb-2 pb-2 border-bottom">
                    <dt class="col-sm-4 col-md-3 text-muted"><?php echo htmlspecialchars($field_def['FieldName']); ?>:</dt>
                    <dd class="col-sm-8 col-md-9"><?php echo $display_value; ?></dd>
                </div>
                <?php endforeach; ?>
            </dl>
        <?php else: ?>
            <p class="text-info">این فرم فاقد فیلد است یا ساختار فیلدهای آن یافت نشد.</p>
        <?php endif; ?>
    </div>
    <div class="card-footer text-muted small">
        شناسه پاسخ: <?php echo $submission_data['SubmissionID']; ?> | شناسه فرم: <?php echo $submission_data['FormID']; ?> | شناسه کاربر پاسخ‌دهنده: <?php echo $submission_data['UserID']; ?>
    </div>
</div>
<?php else: ?>
    <?php if(empty($view_errors)): ?>
        <div class="alert alert-warning">اطلاعات پاسخ برای نمایش در دسترس نیست.</div>
    <?php endif; ?>
<?php endif; ?>
<style>
    .submission-view-container dt { font-weight: 600; padding-top: 0.3rem; }
    .submission-view-container dd { margin-bottom: 0.3rem; padding-top: 0.3rem; word-break: break-word; }
    .submission-details-list .row:last-child { border-bottom: none !important; margin-bottom: 0 !important; padding-bottom: 0 !important;}
</style>
<?php
require_once __DIR__ . '/../includes/footer.php';
?>
