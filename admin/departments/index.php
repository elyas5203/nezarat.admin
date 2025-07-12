<?php
require_once __DIR__ . '/../includes/header.php';

// CSRF token for delete action
$delete_csrf_token = generate_csrf_token('delete_department');

// Fetch departments from database
$departments = [];
$search_term = isset($_GET['search_term']) ? sanitize_input($_GET['search_term']) : '';

if ($conn) {
    $sql = "SELECT DepartmentID, DepartmentName, Description, CreatedAt FROM Departments";
    $params = [];
    $types = "";

    if (!empty($search_term)) {
        $sql .= " WHERE DepartmentName LIKE ? OR Description LIKE ?";
        $like_search_term = "%" . $search_term . "%";
        $params[] = $like_search_term;
        $params[] = $like_search_term;
        $types .= "ss";
    }
    $sql .= " ORDER BY DepartmentName ASC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $departments[] = $row;
            }
        } else {
            // Use session flash message for errors to display after redirect or on current page
            $_SESSION['action_error'] = "خطا در اجرای کوئری بخش‌ها: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['action_error'] = "خطا در آماده سازی کوئری بخش‌ها: " . $conn->error;
    }
} else {
    $_SESSION['action_error'] = "خطا در اتصال به پایگاه داده.";
}
?>

<div class="page-header">
    <h1>مدیریت بخش‌های سازمان</h1>
    <div class="page-header-actions">
        <a href="create.php" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-lg icon" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2Z"/></svg>
            افزودن بخش جدید
        </a>
    </div>
</div>

<div class="filter-search-bar">
    <form method="GET" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="form-inline-flex">
        <div class="form-group">
            <label for="search_term" class="sr-only">جستجو:</label>
            <input type="text" class="form-control" id="search_term" name="search_term" placeholder="نام یا توضیحات بخش..." value="<?php echo htmlspecialchars($search_term); ?>">
        </div>
        <button type="submit" class="btn btn-info">فیلتر</button>
        <?php if (!empty($search_term)): ?>
            <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary ml-2">پاک کردن فیلتر</a>
        <?php endif; ?>
    </form>
</div>

<?php if (isset($_SESSION['action_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['action_success']; unset($_SESSION['action_success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if (isset($_SESSION['action_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['action_error']; unset($_SESSION['action_error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <span>لیست بخش‌ها (<?php echo count($departments); ?> مورد)</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>نام بخش</th>
                        <th>توضیحات</th>
                        <th>تاریخ ایجاد</th>
                        <th class="actions-column">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($departments)): ?>
                        <tr>
                            <td colspan="5" class="text-center">
                                <?php if (!empty($search_term)): ?>
                                    هیچ بخشی با عبارت جستجو شده یافت نشد.
                                <?php else: ?>
                                    هیچ بخشی تعریف نشده است. برای شروع، یک <a href="create.php">بخش جدید ایجاد کنید</a>.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($departments as $index => $dept): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($dept['DepartmentName']); ?></td>
                                <td><?php echo nl2br(htmlspecialchars(mb_substr($dept['Description'] ?? '', 0, 100))) . (mb_strlen($dept['Description'] ?? '') > 100 ? '...' : ''); ?></td>
                                <td><?php echo $dept['CreatedAt'] ? to_jalali($dept['CreatedAt'], 'yyyy/MM/dd') : '---'; ?></td>
                                <td class="actions-cell">
                                    <a href="edit.php?dept_id=<?php echo $dept['DepartmentID']; ?>" class="btn btn-sm btn-info" title="ویرایش">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-square icon" viewBox="0 0 16 16"><path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/><path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"/></svg>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-danger btn-delete-department"
                                            data-dept-id="<?php echo $dept['DepartmentID']; ?>"
                                            data-dept-name="<?php echo htmlspecialchars($dept['DepartmentName']); ?>"
                                            data-csrf-token="<?php echo $delete_csrf_token; ?>"
                                            title="حذف">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash3 icon" viewBox="0 0 16 16"><path d="M6.5 1h3a.5.5 0 0 1 .5.5v1H6v-1a.5.5 0 0 1 .5-.5ZM11 2.5v-1A1.5 1.5 0 0 0 9.5 0h-3A1.5 1.5 0 0 0 5 1.5v1H2.506a.58.58 0 0 0-.01 0H1.5a.5.5 0 0 0 0 1h.538l.853 10.66A2 2 0 0 0 4.885 16h6.23a2 2 0 0 0 1.994-1.84l.853-10.66h.538a.5.5 0 0 0 0-1h-.995a.59.59 0 0 0-.01 0H11Zm1.958 1-.846 10.58a1 1 0 0 1-.997.92h-6.23a1 1 0 0 1-.997-.92L3.042 3.5h9.916Zm-7.487 1a.5.5 0 0 1 .528.47l.5 8.5a.5.5 0 0 1-.998.06L5 5.03a.5.5 0 0 1 .47-.528ZM8 4.5a.5.5 0 0 1 .5.5v8.5a.5.5 0 0 1-1 0V5a.5.5 0 0 1 .5-.5Zm3.5.5a.5.5 0 0 0-.528-.47l-.5 8.5a.5.5 0 0 0 .998.06l.5-8.5a.5.5 0 0 0-.47-.528Z"/></svg>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- Pagination (TODO: Implement if many departments) -->
    </div>
</div>

<!-- Delete Confirmation Modal for Departments -->
<div id="deleteDepartmentModal" style="display:none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1050; align-items: center; justify-content: center; direction: rtl;">
    <div style="background-color: white; padding: 20px; border-radius: 5px; width: 90%; max-width: 500px; box-shadow: 0 5px 15px rgba(0,0,0,0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">
            <h5 style="margin:0; font-size: 1.25rem;">تایید حذف بخش</h5>
            <button type="button" onclick="closeModal('deleteDepartmentModal')" style="background:none; border:none; font-size:1.5rem; cursor:pointer; padding:0;">&times;</button>
        </div>
        <div style="margin-bottom: 20px;">
            آیا از حذف بخش <strong id="departmentNameToDelete"></strong> مطمئن هستید؟ این عمل ممکن است بر کاربران یا اطلاعات وابسته به این بخش تاثیر بگذارد.
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 10px;">
            <form id="deleteDepartmentForm" method="POST" action="actions/delete_department.php" style="display:inline;">
                <input type="hidden" name="dept_id" id="departmentIdToDelete" value="">
                <input type="hidden" name="csrf_token" id="csrfTokenToDeleteDept" value="">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteDepartmentModal')">انصراف</button>
                <button type="submit" class="btn btn-danger">حذف</button>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'flex';
}
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function () {
    const deleteDeptButtons = document.querySelectorAll('.btn-delete-department');
    const departmentNameToDeleteElement = document.getElementById('departmentNameToDelete');
    const departmentIdToDeleteElement = document.getElementById('departmentIdToDelete');
    const csrfTokenToDeleteDeptElement = document.getElementById('csrfTokenToDeleteDept');

    deleteDeptButtons.forEach(button => {
        button.addEventListener('click', function () {
            const deptId = this.dataset.deptId;
            const deptName = this.dataset.deptName;
            const csrfToken = this.dataset.csrfToken;

            if (departmentNameToDeleteElement) departmentNameToDeleteElement.textContent = deptName;
            if (departmentIdToDeleteElement) departmentIdToDeleteElement.value = deptId;
            if (csrfTokenToDeleteDeptElement) csrfTokenToDeleteDeptElement.value = csrfToken;

            openModal('deleteDepartmentModal');
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === "Escape") {
            closeModal('deleteDepartmentModal');
        }
    });

    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(alert); // Requires Bootstrap JS for smooth dismiss
            if (bsAlert) bsAlert.close(); else alert.style.display = 'none';
        }, 5000);
    });
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
