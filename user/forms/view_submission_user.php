<?php
// user/forms/view_submission_user.php
require_once __DIR__ . '/../includes/header.php';

$user_id = get_current_user_id();
if (!$user_id) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'برای مشاهده پاسخ باید وارد شوید.'];
    header("Location: " . ($user_base_url ?? '/my_site/user') . "/auth/login.php");
    exit;
}

$submission_id_to_view_user = null;
$submission_data_user = null;
$form_fields_definitions_user = [];
$submitted_field_values_user = [];
$view_errors_user = [];

if (isset($_GET['submission_id']) && is_numeric($_GET['submission_id'])) {
    $submission_id_to_view_user = (int)$_GET['submission_id'];

    $stmt_sub_user = $conn->prepare("
        SELECT fs.SubmissionID, fs.FormID, fs.UserID, fs.SubmissionDate, fs.ClassID,
               f.FormName, f.Description AS FormDescription,
               c.ClassName
        FROM FormSubmissions fs
        JOIN Forms f ON fs.FormID = f.FormID
        LEFT JOIN Classes c ON fs.ClassID = c.ClassID
        WHERE fs.SubmissionID = ? AND fs.UserID = ?
    ");
    if ($stmt_sub_user) {
        $stmt_sub_user->bind_param("ii", $submission_id_to_view_user, $user_id);
        $stmt_sub_user->execute();
        $result_sub_user = $stmt_sub_user->get_result();
        if ($result_sub_user->num_rows === 1) {
            $submission_data_user = $result_sub_user->fetch_assoc();
            $form_id_of_submission_user = $submission_data_user['FormID'];

            $stmt_fields_def_user = $conn->prepare("SELECT FieldID, FieldName, FieldType, Options, SortOrder FROM FormFields WHERE FormID = ? ORDER BY SortOrder ASC, FieldID ASC");
            if ($stmt_fields_def_user) {
                $stmt_fields_def_user->bind_param("i", $form_id_of_submission_user);
                $stmt_fields_def_user->execute();
                $result_fields_def_user = $stmt_fields_def_user->get_result();
                while ($field_def_user = $result_fields_def_user->fetch_assoc()) {
                    $form_fields_definitions_user[] = $field_def_user;
                }
                $stmt_fields_def_user->close();
            } else { $view_errors_user[] = "خطا بارگذاری ساختار فیلدها: " . $conn->error; }

            $stmt_values_user = $conn->prepare("SELECT FormFieldID, FieldValue FROM FormSubmissionValues WHERE SubmissionID = ?");
            if ($stmt_values_user) {
                $stmt_values_user->bind_param("i", $submission_id_to_view_user);
                $stmt_values_user->execute();
                $result_values_user = $stmt_values_user->get_result();
                while ($val_row_user = $result_values_user->fetch_assoc()) {
                    $submitted_field_values_user[$val_row_user['FormFieldID']] = $val_row_user['FieldValue'];
                }
                $stmt_values_user->close();
            } else { $view_errors_user[] = "خطا بارگذاری مقادیر پاسخ: " . $conn->error; }
        } else { $view_errors_user[] = "پاسخ یافت نشد یا شما اجازه دسترسی ندارید."; }
        $stmt_sub_user->close();
    } else { $view_errors_user[] = "خطا بارگذاری اطلاعات پاسخ: " . $conn->error; }
} else { $view_errors_user[] = "شناسه پاسخ نامعتبر."; }
?>
<div class="page-header">
    <h1>مشاهده پاسخ ثبت شده برای فرم: "<?php echo htmlspecialchars($submission_data_user['FormName'] ?? '...'); ?>"</h1>
    <div class="page-header-actions">
        <?php
        $back_link = ($user_base_url ?? '/my_site/user') . "/forms/index.php"; // Default back link
        $back_text = "بازگشت به لیست فرم‌ها";
        if ($submission_data_user && $submission_data_user['ClassID']) {
            // Check if the form was a self_assessment to determine the correct back link
            $stmt_form_purpose = $conn->prepare("SELECT FormPurpose FROM Forms WHERE FormID = ?");
            if($stmt_form_purpose){
                $stmt_form_purpose->bind_param("i", $submission_data_user['FormID']);
                $stmt_form_purpose->execute();
                $form_purpose_result = $stmt_form_purpose->get_result()->fetch_assoc();
                if($form_purpose_result && $form_purpose_result['FormPurpose'] == 'self_assessment'){
                    $back_link = ($user_base_url ?? '/my_site/user') . "/monitoring/assessment_history.php?class_id=" . $submission_data_user['ClassID'];
                    $back_text = "بازگشت به تاریخچه خوداظهاری کلاس";
                }
                $stmt_form_purpose->close();
            }
        }
        ?>
        <a href="<?php echo $back_link; ?>" class="btn btn-secondary">
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"></polyline></svg>
            <span><?php echo $back_text; ?></span>
        </a>
    </div>
</div>

<?php if (!empty($view_errors_user)): ?> <div class="alert alert-danger"><ul><?php foreach ($view_errors_user as $err_u): ?><li><?php echo htmlspecialchars($err_u); ?></li><?php endforeach; ?></ul></div> <?php endif; ?>

<?php if ($submission_data_user && empty($view_errors_user)): ?>
<div class="card shadow-sm">
    <div class="card-header bg-light py-3">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">فرم: <?php echo htmlspecialchars($submission_data_user['FormName']); ?></h5>
            <small class="text-muted">تاریخ ثبت: <?php echo to_jalali($submission_data_user['SubmissionDate'], 'yyyy/MM/dd HH:mm:ss'); ?></small>
        </div>
        <?php if ($submission_data_user['ClassName']): ?>
            <p class="mb-0 mt-1 small text-info">مربوط به کلاس: <?php echo htmlspecialchars($submission_data_user['ClassName']); ?></p>
        <?php endif; ?>
    </div>
    <div class="card-body submission-view-container user-view">
        <?php if (!empty($submission_data_user['FormDescription'])): ?>
            <p class="form-description text-muted"><em>توضیحات فرم: <?php echo nl2br(htmlspecialchars($submission_data_user['FormDescription'])); ?></em></p>
            <hr class="my-3">
        <?php endif; ?>

        <?php if (!empty($form_fields_definitions_user)): ?>
            <dl class="dl-horizontal submission-details-list">
                <?php foreach ($form_fields_definitions_user as $field_def_u):
                    $field_id_from_def_u = $field_def_u['FieldID'];
                    $submitted_value_for_field_u = $submitted_field_values_user[$field_id_from_def_u] ?? null;
                    $display_value_u = '<em class="text-muted">- بدون پاسخ -</em>';

                    if ($submitted_value_for_field_u !== null && $submitted_value_for_field_u !== '') {
                        if (in_array($field_def_u['FieldType'], ['checkbox'])) {
                            $decoded_val_u = json_decode($submitted_value_for_field_u, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_val_u)) {
                                $display_value_u = !empty($decoded_val_u) ? implode('، ', array_map('htmlspecialchars', $decoded_val_u)) : '<em class="text-muted">- گزینه‌ای انتخاب نشده -</em>';
                            } else { $display_value_u = htmlspecialchars($submitted_value_for_field_u); }
                        } elseif ($field_def_u['FieldType'] === 'textarea') {
                            $display_value_u = nl2br(htmlspecialchars($submitted_value_for_field_u));
                        } else {
                            $display_value_u = htmlspecialchars($submitted_value_for_field_u);
                        }
                    }
                ?>
                <div class="submission-item row mb-2 pb-2 <?php if(next($form_fields_definitions_user)) echo 'border-bottom'; ?>">
                    <dt class="col-sm-4 col-md-3 text-muted"><?php echo htmlspecialchars($field_def_u['FieldName']); ?>:</dt>
                    <dd class="col-sm-8 col-md-9"><?php echo $display_value_u; ?></dd>
                </div>
                <?php endforeach; ?>
            </dl>
        <?php else: ?>
            <p class="text-info">این فرم فاقد فیلد است یا ساختار فیلدهای آن یافت نشد.</p>
        <?php endif; ?>
    </div>
    <div class="card-footer text-muted small"> شناسه پاسخ: <?php echo $submission_data_user['SubmissionID']; ?> </div>
</div>
<?php else: ?>
    <?php if(empty($view_errors_user)): ?> <div class="alert alert-warning">اطلاعات پاسخ برای نمایش در دسترس نیست.</div> <?php endif; ?>
<?php endif; ?>
<style>
    .submission-view-container.user-view dt { font-weight: 500; }
    .submission-view-container.user-view dd { word-break: break-word; }
    .submission-details-list .row:last-child { border-bottom: none !important; }
    .submission-item.border-bottom { border-bottom: 1px dashed #eee !important; }
</style>
<?php
require_once __DIR__ . '/../includes/footer.php';
?>
