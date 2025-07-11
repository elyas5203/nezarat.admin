<?php
// user/forms/index.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth

$user_id = get_current_user_id();
$user_type = get_current_user_type(); // e.g., 'teacher', 'member'

// --- Logic to determine which forms are available to the current user ---
// This is a placeholder for a more complex logic.
// Possible criteria:
// 1. Forms explicitly assigned to the user (not implemented yet).
// 2. Forms assigned to the user's department(s).
// 3. Forms generally available for the user's role/type.
// 4. Forms that are "public" or available to all logged-in users.
// 5. Forms that are recurring (e.g., weekly self-assessment) and need a new submission.

// For now, a simplified query:
// - Shows forms not assigned to any specific department (could be considered "global").
// - Shows forms assigned to departments the user is a member of.
// - TODO: Add logic for forms that need to be filled periodically (e.g. weekly self-assessment).

$user_dept_ids_str = '0'; // Default to a non-existent ID if user has no departments
if ($user_id) {
    $user_departments_query = $conn->prepare("SELECT GROUP_CONCAT(DepartmentID) as dept_ids FROM UserDepartments WHERE UserID = ?");
    if ($user_departments_query) {
        $user_departments_query->bind_param("i", $user_id);
        $user_departments_query->execute();
        $dept_res = $user_departments_query->get_result()->fetch_assoc();
        if ($dept_res && !empty($dept_res['dept_ids'])) {
            $user_dept_ids_str = $dept_res['dept_ids'];
        }
        $user_departments_query->close();
    }
}

// The SQL query to fetch forms.
// It selects forms that are either not department-specific (DepartmentID IS NULL)
// OR are linked to one of the user's departments (FIND_IN_SET).
// It also fetches the date of the user's last submission for each form and total submissions by user for that form.
$sql_forms = "
    SELECT
        f.FormID, f.FormName, f.Description, d.DepartmentName, f.CreatedAt,
        (SELECT MAX(fs.SubmissionDate)
         FROM FormSubmissions fs
         WHERE fs.FormID = f.FormID AND fs.UserID = ?) as LastSubmissionDate,
        (SELECT COUNT(*)
         FROM FormSubmissions fs
         WHERE fs.FormID = f.FormID AND fs.UserID = ?) as SubmissionCountForUser
    FROM Forms f
    LEFT JOIN Departments d ON f.DepartmentID = d.DepartmentID
    WHERE f.DepartmentID IS NULL OR FIND_IN_SET(f.DepartmentID, ?)
    ORDER BY f.FormName
";

$stmt_forms = $conn->prepare($sql_forms);
if ($stmt_forms) {
    $stmt_forms->bind_param("iis", $user_id, $user_id, $user_dept_ids_str);
    $stmt_forms->execute();
    $forms_result = $stmt_forms->get_result();
} else {
    $forms_result = false; // Error in preparing statement
    // echo "Error preparing statement: " . $conn->error; // For debugging
}

?>
<div class="page-header">
    <h1>فرم‌های در دسترس شما</h1>
    <p class="page-subtitle">در این بخش می‌توانید فرم‌های مربوط به خود را مشاهده و تکمیل نمایید.</p>
</div>

<?php if (isset($_GET['action_status'])): ?>
    <div class="alert <?php echo ($_GET['action_status'] == 'success') ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars(urldecode($_GET['message'] ?? '')); ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close" style="background:none; border:none; font-size:1.5rem; position:absolute; top:0; left:0; padding: 0.75rem 1.25rem;">
            <span aria-hidden="true">&times;</span>
        </button>
        <script>
            setTimeout(function() {
                let alert = document.querySelector('.alert-dismissible');
                if(alert) {
                    if (typeof(bootstrap) !== 'undefined' && typeof bootstrap.Alert !== 'undefined' && bootstrap.Alert.getInstance(alert)) {
                        bootstrap.Alert.getInstance(alert).close();
                    } else if (alert.style.display !== 'none') { // Fallback if Bootstrap JS not fully ready for Alert
                        alert.style.opacity = '0';
                        setTimeout(function(){ alert.style.display = 'none'; }, 600);
                    }
                }
            }, 7000);
        </script>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <span>لیست فرم‌ها</span>
    </div>
    <div class="card-body">
        <?php if ($forms_result && $forms_result->num_rows > 0): ?>
            <div class="list-group">
                <?php while ($form = $forms_result->fetch_assoc()): ?>
                    <div class="list-group-item list-group-item-action flex-column align-items-start mb-2 shadow-sm rounded">
                        <div class="d-flex w-100 justify-content-between">
                            <h5 class="mb-1 font-weight-bold text-primary-user"><?php echo htmlspecialchars($form['FormName']); ?></h5>
                            <small class="text-muted">
                                <?php if ($form['SubmissionCountForUser'] > 0): ?>
                                    <span class="badge badge-success"> <?php echo $form['SubmissionCountForUser']; ?> بار تکمیل شده</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">تکمیل نشده</span>
                                <?php endif; ?>
                            </small>
                        </div>
                        <?php if (!empty($form['Description'])): ?>
                            <p class="mb-2 text-muted" style="font-size: 0.9rem;"><?php echo nl2br(htmlspecialchars(mb_substr($form['Description'], 0, 180))) . (mb_strlen($form['Description']) > 180 ? '...' : ''); ?></p>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <div>
                                <?php if ($form['DepartmentName']): ?>
                                    <small class="text-info d-block">مربوط به بخش: <?php echo htmlspecialchars($form['DepartmentName']); ?></small>
                                <?php endif; ?>
                                <?php if ($form['LastSubmissionDate']): ?>
                                    <small class="text-success d-block">آخرین تکمیل: <?php echo to_jalali($form['LastSubmissionDate'], 'yyyy/MM/dd HH:mm'); ?></small>
                                <?php endif; ?>
                            </div>
                            <a href="fill.php?form_id=<?php echo $form['FormID']; ?>" class="btn btn-primary-user btn-sm">
                                <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                <span>تکمیل/مشاهده فرم</span>
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php elseif($forms_result): ?>
            <div class="alert alert-info mb-0">در حال حاضر هیچ فرمی برای شما در دسترس نیست.</div>
        <?php else: ?>
            <div class="alert alert-danger mb-0">خطا در بارگذاری لیست فرم‌ها. <?php // echo htmlspecialchars($conn->error); // For debugging ?></div>
        <?php endif; if($stmt_forms ?? null) $stmt_forms->close(); ?>
    </div>
</div>
<style>
    :root { /* Define user panel specific colors if not already in main CSS */
        --user-panel-primary-color: #17a2b8;      /* Example: Teal */
        --user-panel-primary-hover-color: #138496; /* Darker Teal */
    }
    .list-group-item-action h5.text-primary-user { color: var(--user-panel-primary-color); }
    .list-group-item-action:hover h5.text-primary-user,
    .list-group-item-action:focus h5.text-primary-user { color: var(--user-panel-primary-hover-color); }
    .btn-primary-user { background-color: var(--user-panel-primary-color); border-color: var(--user-panel-primary-color); color: white; }
    .btn-primary-user:hover { background-color: var(--user-panel-primary-hover-color); border-color: var(--user-panel-primary-hover-color); color: white; }
    .badge-success { background-color: #28a745; }
    .badge-warning { background-color: #ffc107; color: #212529;}
</style>
<?php
require_once __DIR__ . '/../includes/footer.php';
?>
