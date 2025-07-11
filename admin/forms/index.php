<?php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth

$csrf_token_forms_index = generate_csrf_token('forms_index_actions');
// Regenerate on each load if links are static and might be re-clicked after some time
regenerate_csrf_token('forms_index_actions');

// Fetch forms
$forms_result = $conn->query("
    SELECT f.FormID, f.FormName, f.Description, d.DepartmentName, f.CreatedAt, f.CreatedByUserID,
           (SELECT COUNT(*) FROM FormFields ff WHERE ff.FormID = f.FormID) as FieldCount,
           (SELECT COUNT(*) FROM FormSubmissions fs WHERE fs.FormID = f.FormID) as SubmissionCount,
           cr_user.Username as CreatorUsername
    FROM Forms f
    LEFT JOIN Departments d ON f.DepartmentID = d.DepartmentID
    LEFT JOIN Users cr_user ON f.CreatedByUserID = cr_user.UserID
    ORDER BY f.FormName
");

?>
<div class="page-header">
    <h1>مدیریت فرم‌ها</h1>
    <div class="page-header-actions">
        <a href="create.php" class="btn btn-primary">
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            <span>ایجاد فرم جدید</span>
        </a>
    </div>
</div>

<?php if (isset($_GET['action_status'])): ?>
    <div class="alert <?php echo (strpos($_GET['action_status'], 'success') !== false) ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show" role="alert">
        <?php
            $message = htmlspecialchars(urldecode($_GET['message'] ?? ''));
            if ($_GET['action_status'] == 'success_create') echo 'فرم با موفقیت ایجاد شد.';
            elseif ($_GET['action_status'] == 'success_edit') echo 'فرم با موفقیت ویرایش شد.';
            elseif ($_GET['action_status'] == 'success_delete') echo 'فرم با موفقیت حذف شد.';
            elseif ($_GET['action_status'] == 'error') echo 'عملیات با خطا مواجه شد: ' . $message;
            else echo $message ?: 'عملیات انجام شد.';
        ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close" style="background:none; border:none; font-size:1.5rem; position:absolute; top:0; left:0; padding: 0.75rem 1.25rem;">
            <span aria-hidden="true">&times;</span>
        </button>
         <script>
            setTimeout(function() {
                let alert = document.querySelector('.alert-dismissible');
                if(alert) {
                    if (typeof(bootstrap) !== 'undefined' && bootstrap.Alert) { new bootstrap.Alert(alert).close(); }
                    else { alert.style.display = 'none'; }
                }
            }, 7000);
        </script>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <span>لیست فرم‌های ایجاد شده</span>
    </div>
    <div class="card-body">
        <?php if ($forms_result && $forms_result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>عنوان فرم</th>
                            <th>بخش مرتبط</th>
                            <th>فیلدها</th>
                            <th>پاسخ‌ها</th>
                            <th>ایجاد کننده</th>
                            <th>تاریخ ایجاد</th>
                            <th class="actions-column">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $row_num = 1; while ($form = $forms_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row_num++; ?></td>
                                <td><strong><?php echo htmlspecialchars($form['FormName']); ?></strong>
                                    <?php if(!empty($form['Description'])): ?>
                                        <small class="d-block text-muted"><?php echo htmlspecialchars(mb_substr($form['Description'], 0, 70)) . (mb_strlen($form['Description']) > 70 ? '...' : ''); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($form['DepartmentName'] ?? '-'); ?></td>
                                <td><span class="badge badge-info"><?php echo $form['FieldCount']; ?></span></td>
                                <td>
                                    <?php if ($form['SubmissionCount'] > 0): ?>
                                        <a href="submissions.php?form_id=<?php echo $form['FormID']; ?>" class="badge badge-success"><?php echo $form['SubmissionCount']; ?></a>
                                    <?php else: ?>
                                        <span class="badge badge-secondary"><?php echo $form['SubmissionCount']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($form['CreatorUsername'] ?? 'سیستم'); ?></td>
                                <td><?php echo to_jalali($form['CreatedAt'], 'yyyy/MM/dd'); ?></td>
                                <td class="actions-cell">
                                    <a href="edit.php?form_id=<?php echo $form['FormID']; ?>" class="btn btn-sm btn-warning" title="ویرایش ساختار فرم">
                                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                    </a>
                                     <a href="preview.php?form_id=<?php echo $form['FormID']; ?>" class="btn btn-sm btn-info" title="پیش‌نمایش فرم" target="_blank">
                                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </a>
                                    <a href="actions/delete_form.php?form_id=<?php echo $form['FormID']; ?>&csrf_token=<?php echo $csrf_token_forms_index; ?>"
                                       class="btn btn-sm btn-danger" title="حذف فرم"
                                       onclick="return confirm('آیا از حذف این فرم و تمام فیلدها و پاسخ‌های ثبت شده برای آن مطمئن هستید؟ این عمل غیرقابل بازگشت است.');">
                                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif($forms_result): ?>
            <div class="alert alert-info mb-0">هیچ فرمی تاکنون ایجاد نشده است. برای شروع <a href="create.php" class="alert-link">یک فرم جدید ایجاد کنید</a>.</div>
        <?php else: ?>
            <div class="alert alert-danger mb-0">خطا در بارگذاری لیست فرم‌ها: <?php echo htmlspecialchars($conn->error); ?></div>
        <?php endif; if($forms_result) $forms_result->close(); ?>
    </div>
</div>
<script>
    // Ensure Bootstrap's JS is loaded for alert dismissal
    // If not using Bootstrap JS, you'll need custom JS for the close button
    document.querySelectorAll('.alert .close').forEach(function(button) {
        button.addEventListener('click', function(event) {
            event.target.closest('.alert').style.display = 'none';
        });
    });
</script>
<?php
require_once __DIR__ . '/../includes/footer.php';
?>
