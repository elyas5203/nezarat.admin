<?php
require_once __DIR__ . '/../includes/header.php';

$delete_csrf_token = generate_csrf_token('delete_form');
$forms = [];
$search_term = isset($_GET['search_term']) ? sanitize_input($_GET['search_term']) : '';

if ($conn) {
    $sql = "SELECT f.FormID, f.Title, f.Description, f.Status, f.CreatedAt, u.Username as CreatedByUsername,
                   (SELECT COUNT(*) FROM FormSubmissions fs WHERE fs.FormID = f.FormID) as SubmissionCount
            FROM Forms f
            LEFT JOIN Users u ON f.CreatedByUserID = u.UserID
            WHERE 1=1"; // Base condition

    $params = [];
    $types = "";

    if (!empty($search_term)) {
        $sql .= " AND (f.Title LIKE ? OR f.Description LIKE ?)";
        $like_search = "%" . $search_term . "%";
        array_push($params, $like_search, $like_search); // Use array_push for clarity
        $types .= "ss";
    }

    $sql .= " ORDER BY f.CreatedAt DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $forms[] = $row;
            }
            $stmt->close(); // Close statement after fetching results
        } else {
            $_SESSION['action_error'] = "خطا در اجرای کوئری فرم‌ها: " . $stmt->error;
        }
    } else {
        $_SESSION['action_error'] = "خطا در آماده سازی کوئری فرم‌ها: " . $conn->error;
    }
} else {
    $_SESSION['action_error'] = "خطا در اتصال به پایگاه داده.";
}
?>

<div class="page-header">
    <h1>مدیریت فرم‌ها</h1>
    <div class="page-header-actions">
        <a href="create.php" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-circle-dotted icon" viewBox="0 0 16 16"><path d="M8 0c-.176 0-.35.006-.523.017l.005.99a6.979 6.979 0 0 1 .518-.016A7.001 7.001 0 0 1 8 1V0zM7.477.017A7.001 7.001 0 0 0 1 7.016v.966A6.979 6.979 0 0 1 .017 7.477l.99.005A6.979 6.979 0 0 1 1 7.016V7a.5.5 0 0 0 .5.5H2a.5.5 0 0 0 .5-.5V6a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 0 .5.5H6a.5.5 0 0 0 .5-.5V6a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 0 .5.5h.016a6.979 6.979 0 0 1-.518.983l.005.99a6.979 6.979 0 0 1 .518-.016A7.001 7.001 0 0 0 8 1V0h-.523zM1 8.523A7.001 7.001 0 0 0 7.016 15h.966A6.979 6.979 0 0 1 7.477 15.983l.005-.99A6.979 6.979 0 0 1 7.016 15H7a.5.5 0 0 0-.5.5V14a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 0-.5-.5H3a.5.5 0 0 0-.5.5V14a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 0-.5-.5h-.016a6.979 6.979 0 0 1 .518-.983l-.005-.99zm13.985-.506A6.979 6.979 0 0 1 15 8.983v-.966A7.001 7.001 0 0 0 8.984 1H8V0h.523a7.001 7.001 0 0 1 6.46 6.983l.005.005.99-.005zm-.507 6.46a7.001 7.001 0 0 1-6.46 6.46V16h.523A7.001 7.001 0 0 0 15 8.983l-.005-.005-.99.005A6.979 6.979 0 0 1 15 8.016v.966zM8.5 4.5a.5.5 0 0 0-1 0v3h-3a.5.5 0 0 0 0 1h3v3a.5.5 0 0 0 1 0v-3h3a.5.5 0 0 0 0-1h-3v-3z"/></svg>
            ایجاد فرم جدید
        </a>
    </div>
</div>

<div class="filter-search-bar">
    <form method="GET" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="form-inline-flex">
        <div class="form-group">
            <label for="search_term" class="sr-only">جستجو:</label>
            <input type="text" class="form-control" id="search_term" name="search_term" placeholder="عنوان یا توضیحات فرم..." value="<?php echo htmlspecialchars($search_term); ?>">
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
        <span>لیست فرم‌ها (<?php echo count($forms); ?> مورد)</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>عنوان فرم</th>
                        <th>توضیحات</th>
                        <th>وضعیت</th>
                        <th>تعداد پاسخ‌ها</th>
                        <th>ایجاد کننده</th>
                        <th>تاریخ ایجاد</th>
                        <th class="actions-column">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($forms)): ?>
                        <tr>
                            <td colspan="8" class="text-center">
                                <?php if (!empty($search_term)): ?>
                                    هیچ فرمی با عبارت جستجو شده یافت نشد.
                                <?php else: ?>
                                    هیچ فرمی تعریف نشده است. برای شروع، یک <a href="create.php">فرم جدید ایجاد کنید</a>.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($forms as $index => $form): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <a href="preview.php?form_id=<?php echo $form['FormID']; ?>" title="پیش‌نمایش فرم">
                                        <?php echo htmlspecialchars($form['Title']); ?>
                                    </a>
                                </td>
                                <td><?php echo nl2br(htmlspecialchars(mb_substr($form['Description'] ?? '', 0, 70))) . (mb_strlen($form['Description'] ?? '') > 70 ? '...' : ''); ?></td>
                                <td>
                                    <?php
                                    $status_text = 'نامشخص'; $status_badge_class = 'secondary'; // Default
                                    switch ($form['Status']) {
                                        case 'draft': $status_text = 'پیش‌نویس'; $status_badge_class = 'warning text-dark'; break;
                                        case 'published': $status_text = 'منتشر شده'; $status_badge_class = 'success'; break;
                                        case 'archived': $status_text = 'بایگانی شده'; $status_badge_class = 'dark'; break;
                                    }
                                    echo "<span class='badge bg-" . $status_badge_class . "'>" . htmlspecialchars($status_text) . "</span>";
                                    ?>
                                </td>
                                <td>
                                    <a href="submissions.php?form_id=<?php echo $form['FormID']; ?>">
                                        <?php echo $form['SubmissionCount']; ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($form['CreatedByUsername'] ?? 'سیستم'); ?></td>
                                <td><?php echo $form['CreatedAt'] ? to_jalali($form['CreatedAt'], 'yyyy/MM/dd HH:mm') : '---'; ?></td>
                                <td class="actions-cell">
                                    <a href="edit.php?form_id=<?php echo $form['FormID']; ?>" class="btn btn-sm btn-info" title="ویرایش ساختار فرم">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-square icon" viewBox="0 0 16 16"><path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/><path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"/></svg>
                                    </a>
                                    <a href="submissions.php?form_id=<?php echo $form['FormID']; ?>" class="btn btn-sm btn-success" title="مشاهده پاسخ‌ها">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-card-list icon" viewBox="0 0 16 16"><path d="M14.5 3a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h13zm-13-1A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h13a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2h-13z"/><path d="M5 8a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7A.5.5 0 0 1 5 8zm0-2.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5zm0 5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5zm-1-5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0zM4 8a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0zm0 2.5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0z"/></svg>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-danger btn-delete-form"
                                            data-form-id="<?php echo $form['FormID']; ?>"
                                            data-form-title="<?php echo htmlspecialchars($form['Title']); ?>"
                                            data-csrf-token="<?php echo $delete_csrf_token; ?>"
                                            title="حذف فرم و تمام پاسخ‌های آن">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash3-fill icon" viewBox="0 0 16 16"><path d="M11 1.5v1h3.5a.5.5 0 0 1 0 1h-.538l-.853 10.66A2 2 0 0 1 11.115 16h-6.23a2 2 0 0 1-1.994-1.84L2.038 3.5H1.5a.5.5 0 0 1 0-1H5v-1A1.5 1.5 0 0 1 6.5 0h3A1.5 1.5 0 0 1 11 1.5Zm-5 0v1h4v-1a.5.5 0 0 0-.5-.5h-3a.5.5 0 0 0-.5.5ZM4.5 5.029l.5 8.5a.5.5 0 1 0 .998-.06l-.5-8.5a.5.5 0 1 0-.998.06Zm6.53-.528a.5.5 0 0 0-.528.47l-.5 8.5a.5.5 0 0 0 .998.058l.5-8.5a.5.5 0 0 0-.47-.528ZM8 4.5a.5.5 0 0 0-.5.5v8.5a.5.5 0 0 0 1 0V5a.5.5 0 0 0-.5-.5Z"/></svg>
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

<!-- Delete Confirmation Modal for Forms -->
<div id="deleteFormModal" style="display:none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1050; align-items: center; justify-content: center; direction: rtl;">
    <div style="background-color: white; padding: 20px; border-radius: 5px; width: 90%; max-width: 500px; box-shadow: 0 5px 15px rgba(0,0,0,0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">
            <h5 style="margin:0; font-size: 1.25rem;">تایید حذف فرم</h5>
            <button type="button" onclick="closeModal('deleteFormModal')" style="background:none; border:none; font-size:1.5rem; cursor:pointer; padding:0;">&times;</button>
        </div>
        <div style="margin-bottom: 20px;">
            آیا از حذف فرم <strong id="formTitleToDelete"></strong> مطمئن هستید؟ <br>
            <strong class="text-danger">توجه: با حذف فرم، تمامی فیلدهای آن و همچنین تمامی پاسخ‌های ثبت شده برای این فرم نیز برای همیشه حذف خواهند شد. این عمل غیرقابل بازگشت است.</strong>
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 10px;">
            <form id="deleteFormForm" method="POST" action="actions/delete_form.php" style="display:inline;">
                <input type="hidden" name="form_id" id="formIdToDelete" value="">
                <input type="hidden" name="csrf_token" id="csrfTokenToDeleteForm" value="">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteFormModal')">انصراف</button>
                <button type="submit" class="btn btn-danger">بله، حذف کن</button>
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
    const deleteFormButtons = document.querySelectorAll('.btn-delete-form');
    const formTitleToDeleteElement = document.getElementById('formTitleToDelete');
    const formIdToDeleteElement = document.getElementById('formIdToDelete');
    const csrfTokenToDeleteFormElement = document.getElementById('csrfTokenToDeleteForm');

    deleteFormButtons.forEach(button => {
        button.addEventListener('click', function () {
            const formId = this.dataset.formId;
            const formTitle = this.dataset.formTitle;
            const csrfToken = this.dataset.csrfToken;

            if (formTitleToDeleteElement) formTitleToDeleteElement.textContent = formTitle;
            if (formIdToDeleteElement) formIdToDeleteElement.value = formId;
            if (csrfTokenToDeleteFormElement) csrfTokenToDeleteFormElement.value = csrfToken;

            openModal('deleteFormModal');
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === "Escape") {
            closeModal('deleteFormModal');
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
        }, 7000);
    });
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
