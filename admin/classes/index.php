<?php
require_once __DIR__ . '/../includes/header.php';

$delete_csrf_token = generate_csrf_token('delete_class');
$classes = [];
$search_term = isset($_GET['search_term']) ? sanitize_input($_GET['search_term']) : '';
$academic_year_filter = isset($_GET['academic_year']) ? sanitize_input($_GET['academic_year']) : '';

// Populate academic years for filter (example: last 5 years + next 2 years from current Jalali year)
$current_gregorian_year = date('Y');
$current_jalali_year_parts = explode('/', to_jalali($current_gregorian_year.'-01-01', 'yyyy/MM/dd'));
$current_jalali_year_numeric = isset($current_jalali_year_parts[0]) ? (int)$current_jalali_year_parts[0] : (int)date('Y') - 622; // Fallback

$academic_years_options = [];
for ($i = $current_jalali_year_numeric - 5; $i <= $current_jalali_year_numeric + 2; $i++) {
    $academic_years_options[] = $i . '-' . ($i + 1);
}


if ($conn) {
    // Modified query to correctly handle cases where a class might not have a teacher assigned yet.
    // Also ensures UserType is 'teacher' for the join.
    $sql = "SELECT c.ClassID, c.ClassName, c.GradeLevel, c.AcademicYear, c.Description,
                   GROUP_CONCAT(DISTINCT CONCAT(u.FirstName, ' ', u.LastName) SEPARATOR ', ') as TeachersNames,
                   c.CreatedAt
            FROM Classes c
            LEFT JOIN ClassTeachers ct ON c.ClassID = ct.ClassID
            LEFT JOIN Users u ON ct.UserID = u.UserID AND (u.UserType = 'teacher' OR EXISTS (SELECT 1 FROM UserRoles ur JOIN Roles r ON ur.RoleID = r.RoleID WHERE ur.UserID = u.UserID AND r.RoleName = 'مدرس'))
            WHERE 1=1";

    $params = [];
    $types = "";

    if (!empty($search_term)) {
        $sql .= " AND (c.ClassName LIKE ? OR c.GradeLevel LIKE ? OR c.Description LIKE ? OR CONCAT(u.FirstName, ' ', u.LastName) LIKE ?)";
        $like_search = "%" . $search_term . "%";
        array_push($params, $like_search, $like_search, $like_search, $like_search);
        $types .= "ssss";
    }
    if (!empty($academic_year_filter)) {
        $sql .= " AND c.AcademicYear = ?";
        $params[] = $academic_year_filter;
        $types .= "s";
    }

    $sql .= " GROUP BY c.ClassID ORDER BY c.AcademicYear DESC, c.ClassName ASC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $classes[] = $row;
            }
        } else {
            $_SESSION['action_error'] = "خطا در اجرای کوئری کلاس‌ها: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['action_error'] = "خطا در آماده سازی کوئری کلاس‌ها: " . $conn->error;
    }
} else {
    $_SESSION['action_error'] = "خطا در اتصال به پایگاه داده.";
}
?>

<div class="page-header">
    <h1>مدیریت کلاس‌ها</h1>
    <div class="page-header-actions">
        <a href="create.php" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-lg icon" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2Z"/></svg>
            افزودن کلاس جدید
        </a>
    </div>
</div>

<div class="filter-search-bar">
    <form method="GET" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="form-inline-flex">
        <div class="form-group">
            <label for="search_term" class="sr-only">جستجو:</label>
            <input type="text" class="form-control" id="search_term" name="search_term" placeholder="نام کلاس، مقطع، مدرس..." value="<?php echo htmlspecialchars($search_term); ?>">
        </div>
        <div class="form-group">
            <label for="academic_year" class="sr-only">سال تحصیلی:</label>
            <select class="form-control" id="academic_year" name="academic_year">
                <option value="">همه سال‌های تحصیلی</option>
                <?php foreach ($academic_years_options as $year_option): ?>
                    <option value="<?php echo $year_option; ?>" <?php echo ($academic_year_filter === $year_option) ? 'selected' : ''; ?>>
                        <?php echo $year_option; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-info">فیلتر</button>
        <?php if (!empty($search_term) || !empty($academic_year_filter)): ?>
            <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary ml-2">پاک کردن فیلترها</a>
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
        <span>لیست کلاس‌ها (<?php echo count($classes); ?> مورد)</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>نام کلاس</th>
                        <th>مقطع</th>
                        <th>سال تحصیلی</th>
                        <th>مدرس(ها)</th>
                        <th>توضیحات</th>
                        <th>تاریخ ایجاد</th>
                        <th class="actions-column">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($classes)): ?>
                        <tr>
                            <td colspan="8" class="text-center">
                                <?php if (!empty($search_term) || !empty($academic_year_filter)): ?>
                                    هیچ کلاسی با فیلترهای اعمال شده یافت نشد.
                                <?php else: ?>
                                    هیچ کلاسی تعریف نشده است. برای شروع، یک <a href="create.php">کلاس جدید ایجاد کنید</a>.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($classes as $index => $class_item): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($class_item['ClassName']); ?></td>
                                <td><?php echo htmlspecialchars($class_item['GradeLevel'] ?? '---'); ?></td>
                                <td><?php echo htmlspecialchars($class_item['AcademicYear'] ?? '---'); ?></td>
                                <td><?php echo !empty($class_item['TeachersNames']) ? htmlspecialchars($class_item['TeachersNames']) : '<span class="text-muted"><em>بدون مدرس</em></span>'; ?></td>
                                <td><?php echo nl2br(htmlspecialchars(mb_substr($class_item['Description'] ?? '', 0, 70))) . (mb_strlen($class_item['Description'] ?? '') > 70 ? '...' : ''); ?></td>
                                <td><?php echo $class_item['CreatedAt'] ? to_jalali($class_item['CreatedAt'], 'yyyy/MM/dd') : '---'; ?></td>
                                <td class="actions-cell">
                                    <a href="edit.php?class_id=<?php echo $class_item['ClassID']; ?>" class="btn btn-sm btn-info" title="ویرایش">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-square icon" viewBox="0 0 16 16"><path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/><path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"/></svg>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-danger btn-delete-class"
                                            data-class-id="<?php echo $class_item['ClassID']; ?>"
                                            data-class-name="<?php echo htmlspecialchars($class_item['ClassName'] . ' (' . ($class_item['AcademicYear'] ?? '') . ')'); ?>"
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
        <!-- Pagination (TODO) -->
    </div>
</div>

<!-- Delete Confirmation Modal for Classes -->
<div id="deleteClassModal" style="display:none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1050; align-items: center; justify-content: center; direction: rtl;">
    <div style="background-color: white; padding: 20px; border-radius: 5px; width: 90%; max-width: 500px; box-shadow: 0 5px 15px rgba(0,0,0,0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">
            <h5 style="margin:0; font-size: 1.25rem;">تایید حذف کلاس</h5>
            <button type="button" onclick="closeModal('deleteClassModal')" style="background:none; border:none; font-size:1.5rem; cursor:pointer; padding:0;">&times;</button>
        </div>
        <div style="margin-bottom: 20px;">
            آیا از حذف کلاس <strong id="classNameToDelete"></strong> مطمئن هستید؟ این عمل ممکن است بر اطلاعات وابسته (مانند فرم‌های ثبت شده برای این کلاس یا تخصیص دانش آموزان) تاثیر بگذارد.
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 10px;">
            <form id="deleteClassForm" method="POST" action="actions/delete_class.php" style="display:inline;">
                <input type="hidden" name="class_id" id="classIdToDelete" value="">
                <input type="hidden" name="csrf_token" id="csrfTokenToDeleteClass" value="">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteClassModal')">انصراف</button>
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
    const deleteClassButtons = document.querySelectorAll('.btn-delete-class');
    const classNameToDeleteElement = document.getElementById('classNameToDelete');
    const classIdToDeleteElement = document.getElementById('classIdToDelete');
    const csrfTokenToDeleteClassElement = document.getElementById('csrfTokenToDeleteClass');

    deleteClassButtons.forEach(button => {
        button.addEventListener('click', function () {
            const classId = this.dataset.classId;
            const className = this.dataset.className;
            const csrfToken = this.dataset.csrfToken;

            if (classNameToDeleteElement) classNameToDeleteElement.textContent = className;
            if (classIdToDeleteElement) classIdToDeleteElement.value = classId;
            if (csrfTokenToDeleteClassElement) csrfTokenToDeleteClassElement.value = csrfToken;

            openModal('deleteClassModal');
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === "Escape") {
            closeModal('deleteClassModal');
        }
    });

    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            if (typeof bootstrap !== 'undefined' && bootstrap.Alert && bootstrap.Alert.getInstance(alert)) {
                 bootstrap.Alert.getInstance(alert).close();
            } else {
                alert.style.display = 'none';
            }
        }, 7000); // Increased time for alerts
    });
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
