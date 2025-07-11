<?php
// admin/forms/submissions.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth

$form_id_for_submissions = null;
$form_name = '';
$submissions_result = null;
$total_records = 0;
$total_pages = 0;
$page = 1;
$records_per_page = 15; // Or make this configurable

if (isset($_GET['form_id']) && is_numeric($_GET['form_id'])) {
    $form_id_for_submissions = (int)$_GET['form_id'];

    $stmt_form_name = $conn->prepare("SELECT FormName FROM Forms WHERE FormID = ?");
    if ($stmt_form_name) {
        $stmt_form_name->bind_param("i", $form_id_for_submissions);
        $stmt_form_name->execute();
        $result_fn = $stmt_form_name->get_result();
        if ($result_fn->num_rows === 1) {
            $form_name = $result_fn->fetch_assoc()['FormName'];
        } else {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "فرم با شناسه " . $form_id_for_submissions . " یافت نشد."];
            header("Location: index.php"); exit;
        }
        $stmt_form_name->close();
    } else {
         $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "خطا در بارگذاری نام فرم."];
         header("Location: index.php"); exit;
    }

    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $records_per_page;

    $total_stmt = $conn->prepare("SELECT COUNT(SubmissionID) as total FROM FormSubmissions WHERE FormID = ?");
    if ($total_stmt) {
        $total_stmt->bind_param("i", $form_id_for_submissions);
        $total_stmt->execute();
        $total_records = $total_stmt->get_result()->fetch_assoc()['total'] ?? 0;
        $total_pages = ceil($total_records / $records_per_page);
        if($page > $total_pages && $total_pages > 0) { $page = $total_pages; $offset = ($page - 1) * $records_per_page; }
        $total_stmt->close();
    }

    $stmt_submissions = $conn->prepare("
        SELECT fs.SubmissionID, fs.SubmissionDate, u.UserID, u.Username, u.FirstName, u.LastName
        FROM FormSubmissions fs
        JOIN Users u ON fs.UserID = u.UserID
        WHERE fs.FormID = ?
        ORDER BY fs.SubmissionDate DESC
        LIMIT ? OFFSET ?
    ");
    if ($stmt_submissions) {
        $stmt_submissions->bind_param("iii", $form_id_for_submissions, $records_per_page, $offset);
        $stmt_submissions->execute();
        $submissions_result = $stmt_submissions->get_result();
    } else {
        // Use flash message for error display on redirect or current page
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "خطا در بارگذاری پاسخ‌ها: " . $conn->error];
    }

} else {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "شناسه فرم برای نمایش پاسخ‌ها نامعتبر است."];
    header("Location: index.php"); exit;
}

$csrf_token_submission_actions = generate_csrf_token('form_submission_actions_formid'.$form_id_for_submissions);
regenerate_csrf_token('form_submission_actions_formid'.$form_id_for_submissions);

?>
<div class="page-header">
    <h1>پاسخ‌های فرم: "<?php echo htmlspecialchars($form_name); ?>"</h1>
    <div class="page-header-actions">
        <a href="index.php" class="btn btn-secondary">
             <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
            <span>بازگشت به لیست فرم‌ها</span>
        </a>
        <a href="preview.php?form_id=<?php echo $form_id_for_submissions; ?>" class="btn btn-info" target="_blank">
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
            <span>پیش‌نمایش ساختار</span>
        </a>
    </div>
</div>

<?php
// Display flash messages
if (isset($_SESSION['flash_message'])) {
    $flash = $_SESSION['flash_message'];
    echo "<div class='alert alert-{$flash['type']} alert-dismissible fade show' role='alert'>{$flash['text']}
          <button type='button' class='close' data-dismiss='alert' aria-label='Close' style='background:none; border:none; font-size:1.5rem; position:absolute; top:0; left:0; padding: 0.75rem 1.25rem;'><span aria-hidden='true'>&times;</span></button></div>";
    unset($_SESSION['flash_message']);
}
// Display messages from GET parameters (e.g., after delete action)
if (isset($_GET['action_status'])): ?>
    <div class="alert <?php echo (strpos($_GET['action_status'], 'success') !== false) ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars(urldecode($_GET['message'] ?? '')); ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close" style="background:none; border:none; font-size:1.5rem; position:absolute; top:0; left:0; padding: 0.75rem 1.25rem;'"><span aria-hidden='true'>&times;</span></button>
    </div>
<?php endif; ?>


<div class="card">
    <div class="card-header">
        <span>لیست پاسخ‌ها (کل: <?php echo $total_records; ?>)</span>
    </div>
    <div class="card-body">
        <?php if ($submissions_result && $submissions_result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>کاربر</th>
                            <th>نام کاربری</th>
                            <th>تاریخ ثبت</th>
                            <th class="actions-column">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $sub_row_num = $offset + 1; while ($submission = $submissions_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $sub_row_num++; ?></td>
                                <td><?php echo htmlspecialchars(($submission['FirstName'] ?? '') . ' ' . ($submission['LastName'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars($submission['Username']); ?></td>
                                <td><?php echo to_jalali($submission['SubmissionDate'], 'yyyy/MM/dd HH:mm:ss'); ?></td>
                                <td class="actions-cell">
                                    <a href="view_submission.php?submission_id=<?php echo $submission['SubmissionID']; ?>" class="btn btn-sm btn-info" title="مشاهده جزئیات">
                                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </a>
                                    <a href="actions/delete_submission.php?submission_id=<?php echo $submission['SubmissionID']; ?>&form_id=<?php echo $form_id_for_submissions; ?>&csrf_token=<?php echo $csrf_token_submission_actions; ?>"
                                       class="btn btn-sm btn-danger" title="حذف پاسخ"
                                       onclick="return confirm('آیا از حذف این پاسخ مطمئن هستید؟ این عمل غیرقابل بازگشت است.');">
                                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($total_pages > 1):
                 $query_params_pagination = http_build_query(['form_id' => $form_id_for_submissions]);?>
            <nav aria-label="Page navigation submissions" class="mt-4">
                <ul class="pagination justify-content-center flex-wrap">
                    <?php if ($page > 1): ?> <li class="page-item"><a class="page-link" href="?page=1&<?php echo $query_params_pagination; ?>">اولین</a></li> <li class="page-item"><a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo $query_params_pagination; ?>">قبلی</a></li> <?php endif; ?>
                    <?php $max_links = 5; $start_page = max(1, $page - floor($max_links / 2)); $end_page = min($total_pages, $start_page + $max_links - 1); if ($end_page - $start_page + 1 < $max_links) $start_page = max(1, $end_page - $max_links + 1); ?>
                    <?php if ($start_page > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; ?>
                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?> <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&<?php echo $query_params_pagination; ?>"><?php echo $i; ?></a></li> <?php endfor; ?>
                    <?php if ($end_page < $total_pages) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; ?>
                    <?php if ($page < $total_pages): ?> <li class="page-item"><a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo $query_params_pagination; ?>">بعدی</a></li> <li class="page-item"><a class="page-link" href="?page=<?php echo $total_pages; ?>&<?php echo $query_params_pagination; ?>">آخرین</a></li> <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        <?php elseif($submissions_result): ?>
            <div class="alert alert-info mb-0">هیچ پاسخی برای این فرم ثبت نشده است.</div>
        <?php else: ?>
             <div class="alert alert-danger mb-0">خطا در بارگذاری پاسخ‌ها.</div>
        <?php endif; if($stmt_submissions ?? null) $stmt_submissions->close(); ?>
    </div>
</div>
<script> // For alert dismissal
    document.querySelectorAll('.alert .close').forEach(function(button) {
        button.addEventListener('click', function(event) {
            let alertNode = event.target.closest('.alert');
            if(alertNode) {
                if (typeof(bootstrap) !== 'undefined' && bootstrap.Alert && bootstrap.Alert.getInstance(alertNode)) {
                    bootstrap.Alert.getInstance(alertNode).close();
                } else {
                    alertNode.style.display = 'none';
                }
            }
        });
    });
</script>
<?php
require_once __DIR__ . '/../includes/footer.php';
?>
