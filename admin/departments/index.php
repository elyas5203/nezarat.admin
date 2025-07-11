<?php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth

// CSRF token for delete actions on this page
$csrf_token_dep_index = generate_csrf_token('departments_index_actions');
regenerate_csrf_token('departments_index_actions'); // Ensure it's fresh on each load if links are static

// Fetch departments along with their managers
$departments_result = $conn->query("
    SELECT d.DepartmentID, d.DepartmentName, d.Description,
           GROUP_CONCAT(DISTINCT CONCAT(u.FirstName, ' ', u.LastName) SEPARATOR ', ') AS ManagerNames,
           GROUP_CONCAT(DISTINCT u.UserID SEPARATOR ', ') AS ManagerUserIDs
    FROM Departments d
    LEFT JOIN UserDepartments ud ON d.DepartmentID = ud.DepartmentID AND ud.IsManager = TRUE
    LEFT JOIN Users u ON ud.UserID = u.UserID
    GROUP BY d.DepartmentID, d.DepartmentName, d.Description
    ORDER BY d.DepartmentName
");

?>
<div class="page-header">
    <h1>مدیریت بخش‌های سازمانی</h1>
    <div class="page-header-actions">
        <a href="create.php" class="btn btn-primary">
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            <span>افزودن بخش جدید</span>
        </a>
    </div>
</div>

<?php if (isset($_GET['action_status'])): ?>
    <div class="alert <?php echo (strpos($_GET['action_status'], 'success') !== false) ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show" role="alert">
        <?php
            $message = htmlspecialchars(urldecode($_GET['message'] ?? ''));
            if ($_GET['action_status'] == 'success_create') echo 'بخش با موفقیت ایجاد شد.';
            elseif ($_GET['action_status'] == 'success_edit') echo 'بخش با موفقیت ویرایش شد.';
            elseif ($_GET['action_status'] == 'success_delete') echo 'بخش با موفقیت حذف شد.';
            elseif ($_GET['action_status'] == 'error') echo 'عملیات با خطا مواجه شد: ' . $message;
            else echo $message ?: 'عملیات انجام شد.';
        ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close" style="background:none; border:none; font-size:1.5rem; position:absolute; top:0; left:0; padding: 0.75rem 1.25rem;">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <script>
        // Auto-dismiss alert
        setTimeout(function() {
            let alert = document.querySelector('.alert-dismissible');
            if(alert) {
                // Use Bootstrap's JS if available, otherwise simple hide
                if (typeof(bootstrap) !== 'undefined' && bootstrap.Alert) {
                    new bootstrap.Alert(alert).close();
                } else {
                    alert.style.display = 'none';
                }
            }
        }, 5000);
    </script>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <span>لیست بخش‌ها</span>
    </div>
    <div class="card-body">
        <?php if ($departments_result && $departments_result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>نام بخش</th>
                            <th>توضیحات</th>
                            <th>مدیر(ان) بخش</th>
                            <th class="actions-column">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $row_num = 1; while ($dept = $departments_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row_num++; ?></td>
                                <td><strong><?php echo htmlspecialchars($dept['DepartmentName']); ?></strong></td>
                                <td class="small"><?php echo !empty($dept['Description']) ? nl2br(htmlspecialchars($dept['Description'])) : '-'; ?></td>
                                <td>
                                    <?php
                                    if ($dept['ManagerNames']) {
                                        $manager_names_arr = explode(', ', $dept['ManagerNames']);
                                        $manager_ids_arr = explode(', ', $dept['ManagerUserIDs']);
                                        $manager_links = [];
                                        foreach ($manager_names_arr as $index => $name) {
                                            if(isset($manager_ids_arr[$index])) {
                                                $manager_links[] = '<a href="../users/edit.php?user_id=' . htmlspecialchars($manager_ids_arr[$index]) . '">' . htmlspecialchars($name) . '</a>';
                                            } else {
                                                $manager_links[] = htmlspecialchars($name);
                                            }
                                        }
                                        echo implode('، ', $manager_links);
                                    } else {
                                        echo '<span class="text-muted">- بدون مدیر -</span>';
                                    }
                                    ?>
                                </td>
                                <td class="actions-cell">
                                    <a href="edit.php?dep_id=<?php echo $dept['DepartmentID']; ?>" class="btn btn-sm btn-warning" title="ویرایش">
                                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                    </a>
                                    <a href="actions/delete_department.php?dep_id=<?php echo $dept['DepartmentID']; ?>&csrf_token=<?php echo $csrf_token_dep_index; ?>"
                                       class="btn btn-sm btn-danger" title="حذف"
                                       onclick="return confirm('آیا از حذف این بخش مطمئن هستید؟ \nتوجه: کاربران عضو این بخش، از آن خارج خواهند شد.\nوظایف و فرم‌های مربوط به این بخش ممکن است نیاز به تخصیص مجدد داشته باشند.');">
                                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($departments_result): ?>
            <div class="alert alert-info mb-0">هیچ بخشی تاکنون تعریف نشده است. برای شروع <a href="create.php" class="alert-link">یک بخش جدید اضافه کنید</a>.</div>
        <?php else: ?>
             <div class="alert alert-danger mb-0">خطا در بارگذاری لیست بخش‌ها: <?php echo htmlspecialchars($conn->error); ?></div>
        <?php endif; if($departments_result) $departments_result->close(); ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
