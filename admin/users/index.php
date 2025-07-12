<?php
require_once __DIR__ . '/../includes/header.php';
// Logic for fetching users will be added here later
// For now, CSRF token for delete action
$delete_csrf_token = generate_csrf_token('delete_user');
?>

<div class="page-header">
    <h1>مدیریت کاربران</h1>
    <div class="page-header-actions">
        <a href="create.php" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-lg icon" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2Z"/></svg>
            افزودن کاربر جدید
        </a>
    </div>
</div>

<div class="filter-search-bar">
    <form method="GET" action="" class="form-inline-flex">
        <div class="form-group">
            <label for="search_term" class="sr-only">جستجو:</label>
            <input type="text" class="form-control" id="search_term" name="search_term" placeholder="نام، نام خانوادگی، نام کاربری..." value="<?php echo isset($_GET['search_term']) ? htmlspecialchars($_GET['search_term']) : ''; ?>">
        </div>
        <div class="form-group">
            <label for="role_filter" class="sr-only">نقش:</label>
            <select class="form-control" id="role_filter" name="role_filter">
                <option value="">همه نقش‌ها</option>
                <?php
                // Later: Populate with actual roles from DB
                // Example:
                // global $conn;
                // $selected_role = $_GET['role_filter'] ?? '';
                // $roles_q = $conn->query("SELECT RoleID, RoleName FROM Roles ORDER BY RoleName");
                // if ($roles_q) {
                //    while ($role = $roles_q->fetch_assoc()) {
                //        $is_selected = ($selected_role == $role['RoleID']) ? 'selected' : '';
                //        echo "<option value=\"".htmlspecialchars($role['RoleID'])."\" $is_selected>".htmlspecialchars($role['RoleName'])."</option>";
                //    }
                // }
                ?>
            </select>
        </div>
        <button type="submit" class="btn btn-info">فیلتر</button>
        <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary ml-2">پاک کردن فیلترها</a>
    </form>
</div>

<?php if (isset($_SESSION['user_action_success'])): ?>
    <div class="alert alert-success"><?php echo $_SESSION['user_action_success']; unset($_SESSION['user_action_success']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['user_action_error'])): ?>
    <div class="alert alert-danger"><?php echo $_SESSION['user_action_error']; unset($_SESSION['user_action_error']); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <span>لیست کاربران</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>نام کاربری</th>
                        <th>نام</th>
                        <th>نام خانوادگی</th>
                        <th>نقش(ها)</th>
                        <th>وضعیت</th>
                        <th>آخرین ورود</th>
                        <th class="actions-column">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Placeholder data - replace with actual data from DB
                    $users_placeholder = [
                        ['UserID' => 1, 'Username' => 'teacher1', 'FirstName' => 'مدرس', 'LastName' => 'اول', 'Roles' => 'مدرس', 'IsActive' => true, 'LastLogin' => to_jalali('2024-06-21 10:00:00')],
                        ['UserID' => 2, 'Username' => 'observer2', 'FirstName' => 'ناظر', 'LastName' => 'دوم', 'Roles' => 'ناظر, عضو اولیا', 'IsActive' => true, 'LastLogin' => to_jalali('2024-06-22 12:30:00')],
                        ['UserID' => 3, 'Username' => 'user_inactive', 'FirstName' => 'کاربر', 'LastName' => 'غیرفعال', 'Roles' => 'مدرس', 'IsActive' => false, 'LastLogin' => null],
                         ['UserID' => 0, 'Username' => 'admin', 'FirstName' => 'ادمین', 'LastName' => 'اصلی', 'Roles' => 'ادمین کل', 'IsActive' => true, 'LastLogin' => to_jalali(date('Y-m-d H:i:s'))],
                    ];
                    // In a real scenario, you would fetch users from the database here based on filters
                    // e.g., $users = fetch_users($_GET['search_term'] ?? '', $_GET['role_filter'] ?? '');

                    if (empty($users_placeholder)) : ?>
                        <tr>
                            <td colspan="8" class="text-center">هیچ کاربری یافت نشد.</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($users_placeholder as $index => $user) : ?>
                            <tr>
                                <td><?php echo $index + 1; // Or $user['UserID'] if it's sequential and you prefer ?></td>
                                <td><?php echo htmlspecialchars($user['Username']); ?></td>
                                <td><?php echo htmlspecialchars($user['FirstName']); ?></td>
                                <td><?php echo htmlspecialchars($user['LastName']); ?></td>
                                <td><?php echo htmlspecialchars($user['Roles']); // This will need better handling for multiple roles from DB ?></td>
                                <td>
                                    <?php if ($user['IsActive']) : ?>
                                        <span class="badge badge-success">فعال</span>
                                    <?php else : ?>
                                        <span class="badge badge-danger">غیرفعال</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $user['LastLogin'] ? htmlspecialchars($user['LastLogin']) : '---'; ?></td>
                                <td class="actions-cell">
                                    <a href="edit.php?user_id=<?php echo $user['UserID']; ?>" class="btn btn-sm btn-info" title="ویرایش">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-square icon" viewBox="0 0 16 16"><path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/><path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"/></svg>
                                    </a>
                                    <?php if ($user['Username'] !== 'admin'): // Prevent deleting the main admin ?>
                                    <button type="button" class="btn btn-sm btn-danger btn-delete-user"
                                            data-user-id="<?php echo $user['UserID']; ?>"
                                            data-username="<?php echo htmlspecialchars($user['Username']); ?>"
                                            data-csrf-token="<?php echo $delete_csrf_token; ?>"
                                            title="حذف">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash3 icon" viewBox="0 0 16 16"><path d="M6.5 1h3a.5.5 0 0 1 .5.5v1H6v-1a.5.5 0 0 1 .5-.5ZM11 2.5v-1A1.5 1.5 0 0 0 9.5 0h-3A1.5 1.5 0 0 0 5 1.5v1H2.506a.58.58 0 0 0-.01 0H1.5a.5.5 0 0 0 0 1h.538l.853 10.66A2 2 0 0 0 4.885 16h6.23a2 2 0 0 0 1.994-1.84l.853-10.66h.538a.5.5 0 0 0 0-1h-.995a.59.59 0 0 0-.01 0H11Zm1.958 1-.846 10.58a1 1 0 0 1-.997.92h-6.23a1 1 0 0 1-.997-.92L3.042 3.5h9.916Zm-7.487 1a.5.5 0 0 1 .528.47l.5 8.5a.5.5 0 0 1-.998.06L5 5.03a.5.5 0 0 1 .47-.528ZM8 4.5a.5.5 0 0 1 .5.5v8.5a.5.5 0 0 1-1 0V5a.5.5 0 0 1 .5-.5Zm3.5.5a.5.5 0 0 0-.528-.47l-.5 8.5a.5.5 0 0 0 .998.06l.5-8.5a.5.5 0 0 0-.47-.528Z"/></svg>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- Pagination will be added here later: Example: -->
        <!--
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <li class="page-item disabled"><a class="page-link" href="#">قبلی</a></li>
                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                <li class="page-item"><a class="page-link" href="#">2</a></li>
                <li class="page-item"><a class="page-link" href="#">3</a></li>
                <li class="page-item"><a class="page-link" href="#">بعدی</a></li>
            </ul>
        </nav>
        -->
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteUserModal" style="display:none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1050; align-items: center; justify-content: center;">
    <div style="background-color: white; padding: 20px; border-radius: 5px; width: 90%; max-width: 500px; box-shadow: 0 5px 15px rgba(0,0,0,0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">
            <h5 style="margin:0; font-size: 1.25rem;">تایید حذف کاربر</h5>
            <button type="button" onclick="closeModal('deleteUserModal')" style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
        </div>
        <div style="margin-bottom: 20px;">
            آیا از حذف کاربر <strong id="usernameToDelete"></strong> مطمئن هستید؟ این عمل غیرقابل بازگشت است.
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 10px;">
            <form id="deleteUserForm" method="POST" action="actions/delete_user.php" style="display:inline;">
                <input type="hidden" name="user_id" id="userIdToDelete" value="">
                <input type="hidden" name="csrf_token" id="csrfTokenToDelete" value="">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteUserModal')">انصراف</button>
                <button type="submit" class="btn btn-danger">حذف</button>
            </form>
        </div>
    </div>
</div>


<script>
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'flex';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function () {
    const deleteButtons = document.querySelectorAll('.btn-delete-user');
    const usernameToDeleteElement = document.getElementById('usernameToDelete');
    const userIdToDeleteElement = document.getElementById('userIdToDelete');
    const csrfTokenToDeleteElement = document.getElementById('csrfTokenToDelete');

    deleteButtons.forEach(button => {
        button.addEventListener('click', function () {
            const userId = this.dataset.userId;
            const username = this.dataset.username;
            const csrfToken = this.dataset.csrfToken;

            usernameToDeleteElement.textContent = username;
            userIdToDeleteElement.value = userId;
            csrfTokenToDeleteElement.value = csrfToken; // Set CSRF token for the form

            openModal('deleteUserModal');
        });
    });

    // Close modal if escape key is pressed
    document.addEventListener('keydown', function (event) {
        if (event.key === "Escape") {
            closeModal('deleteUserModal');
        }
    });
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
