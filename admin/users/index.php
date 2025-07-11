<?php
require_once __DIR__ . '/../includes/header.php'; // header.php session, db, auth را هندل می‌کند

// تنظیمات صفحه‌بندی
$records_per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $records_per_page;

// جستجو و فیلتر
$search_term = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$filter_user_type = isset($_GET['user_type']) ? sanitize_input($_GET['user_type']) : '';
$filter_status = isset($_GET['status_filter']) ? sanitize_input($_GET['status_filter']) : ''; // Renamed to avoid conflict with status messages


$where_clauses = [];
$params = [];
$types = "";

// همیشه ادمین اصلی را از لیست خارج کن (با فرض اینکه UserID ادمین اصلی در سشن موجود است)
// یا اگر یک ID ثابت برای ادمین اصلی دارید، از آن استفاده کنید.
if (isset($_SESSION['admin_user_id'])) {
     $where_clauses[] = "UserID != ?";
     $params[] = $_SESSION['admin_user_id'];
     $types .= "i";
} else {
    // اگر admin_user_id در سشن نبود، شاید بهتر باشد یک مقدار پیشفرض یا خطا در نظر گرفت
    // مثلا اگر ادمین اصلی همیشه UserID = 1 دارد:
    // $where_clauses[] = "UserID != 1";
}


if (!empty($search_term)) {
    $where_clauses[] = "(Username LIKE ? OR FirstName LIKE ? OR LastName LIKE ? OR CONCAT(FirstName, ' ', LastName) LIKE ?)";
    $search_like = "%{$search_term}%";
    array_push($params, $search_like, $search_like, $search_like, $search_like);
    $types .= "ssss";
}
if (!empty($filter_user_type)) {
    $where_clauses[] = "UserType = ?";
    $params[] = $filter_user_type;
    $types .= "s";
}
if ($filter_status !== '') {
    $where_clauses[] = "IsActive = ?";
    $params[] = (int)$filter_status;
    $types .= "i";
}


$sql_where = "";
if (!empty($where_clauses)) {
    $sql_where = " WHERE " . implode(" AND ", $where_clauses);
}

// دریافت تعداد کل کاربران برای صفحه‌بندی
$total_sql = "SELECT COUNT(UserID) as total FROM Users" . $sql_where;
$stmt_total = $conn->prepare($total_sql);

if ($stmt_total) {
    if (!empty($types) && !empty($params)) { // Only bind if params exist
        $stmt_total->bind_param($types, ...$params);
    }
    $stmt_total->execute();
    $total_result = $stmt_total->get_result();
    $total_records = $total_result->fetch_assoc()['total'] ?? 0;
    $total_pages = ceil($total_records / $records_per_page);
    if($page > $total_pages && $total_pages > 0) { // Redirect to last page if current page is out of bounds
        $page = $total_pages;
        $offset = ($page - 1) * $records_per_page;
    }
    $stmt_total->close();
} else {
    $total_records = 0;
    $total_pages = 0;
    echo "<div class='alert alert-danger'>خطا در شمارش کاربران: " . htmlspecialchars($conn->error) . "</div>";
}


// دریافت لیست کاربران برای صفحه فعلی
$users_sql = "SELECT UserID, Username, FirstName, LastName, UserType, IsActive, CreatedAt FROM Users" . $sql_where . " ORDER BY CreatedAt DESC LIMIT ? OFFSET ?";
$stmt_users = $conn->prepare($users_sql);

$current_params_for_data = $params;
$current_types_for_data = $types;

$current_params_for_data[] = $records_per_page;
$current_types_for_data .= "i";
$current_params_for_data[] = $offset;
$current_types_for_data .= "i";

if ($stmt_users) {
    if (!empty($current_types_for_data) && !empty($current_params_for_data)) {
         $stmt_users->bind_param($current_types_for_data, ...$current_params_for_data);
    }
    $stmt_users->execute();
    $users_result = $stmt_users->get_result();
} else {
    echo "<div class='alert alert-danger'>خطا در دریافت لیست کاربران: " . htmlspecialchars($conn->error) . "</div>";
    $users_result = false;
}

$user_type_persian = [
    'admin' => 'ادمین',
    'manager' => 'مدیر',
    'deputy' => 'معاون',
    'member' => 'عضو بخش',
    'teacher' => 'مدرس'
];

?>

<div class="page-header">
    <h1>مدیریت کاربران</h1>
    <div class="page-header-actions">
        <a href="create.php" class="btn btn-primary">
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            افزودن کاربر جدید
        </a>
        <a href="roles.php" class="btn btn-secondary">
             <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            مدیریت نقش‌ها
        </a>
    </div>
</div>

<div class="filter-search-bar card">
    <form method="GET" action="index.php" class="form-inline-flex">
        <div class="form-group">
            <label for="search_input" class="sr-only">جستجو</label>
            <input type="text" id="search_input" name="search" class="form-control" placeholder="جستجو (نام، نام کاربری...)" value="<?php echo htmlspecialchars($search_term); ?>">
        </div>
        <div class="form-group">
            <label for="user_type_filter" class="sr-only">نوع کاربر</label>
            <select id="user_type_filter" name="user_type" class="form-control">
                <option value="">همه نوع کاربران</option>
                <?php foreach ($user_type_persian as $type_en => $type_fa): ?>
                    <?php if ($type_en === 'admin' && (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'super_admin')) continue; ?>
                    <option value="<?php echo $type_en; ?>" <?php echo ($filter_user_type == $type_en) ? 'selected' : ''; ?>><?php echo $type_fa; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="status_filter" class="sr-only">وضعیت</label>
            <select id="status_filter" name="status_filter" class="form-control">
                <option value="">همه وضعیت‌ها</option>
                <option value="1" <?php echo ($filter_status === '1') ? 'selected' : ''; ?>>فعال</option>
                <option value="0" <?php echo ($filter_status === '0') ? 'selected' : ''; ?>>غیرفعال</option>
            </select>
        </div>
        <button type="submit" class="btn btn-info">اعمال فیلتر</button>
        <a href="index.php" class="btn btn-outline-secondary ml-2">پاک کردن</a>
    </form>
</div>

<?php if (isset($_GET['action_status'])): ?>
    <div class="alert <?php echo (strpos($_GET['action_status'], 'success') !== false) ? 'alert-success' : 'alert-danger'; ?>">
        <?php
            if ($_GET['action_status'] == 'success_create') echo 'کاربر با موفقیت ایجاد شد.';
            elseif ($_GET['action_status'] == 'success_edit') echo 'کاربر با موفقیت ویرایش شد.';
            elseif ($_GET['action_status'] == 'success_delete') echo 'کاربر با موفقیت حذف شد.';
            elseif ($_GET['action_status'] == 'error') echo 'عملیات با خطا مواجه شد: ' . htmlspecialchars($_GET['message'] ?? '');
            else echo htmlspecialchars($_GET['message'] ?? 'عملیات انجام شد.');
        ?>
    </div>
<?php endif; ?>


<div class="card">
    <div class="card-header">
        <span>لیست کاربران (<?php echo $total_records; ?>)</span>
    </div>
    <div class="card-body">
        <?php if ($users_result && $users_result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>نام</th>
                            <th>نام کاربری</th>
                            <th>نوع کاربر</th>
                            <th>نقش(ها)</th>
                            <th>وضعیت</th>
                            <th>تاریخ ایجاد</th>
                            <th class="actions-column">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $row_num = $offset + 1;
                        while ($user = $users_result->fetch_assoc()):
                            $roles_str = '-';
                            $stmt_roles = $conn->prepare("SELECT r.RoleName FROM Roles r JOIN UserRoles ur ON r.RoleID = ur.RoleID WHERE ur.UserID = ?");
                            if($stmt_roles){
                                $stmt_roles->bind_param("i", $user['UserID']);
                                $stmt_roles->execute();
                                $roles_result = $stmt_roles->get_result();
                                $user_roles_arr = [];
                                while($role = $roles_result->fetch_assoc()){
                                    $user_roles_arr[] = htmlspecialchars($role['RoleName']);
                                }
                                if(!empty($user_roles_arr)) $roles_str = implode('، ', $user_roles_arr);
                                $stmt_roles->close();
                            }
                        ?>
                            <tr>
                                <td><?php echo $row_num++; ?></td>
                                <td><?php echo htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']); ?></td>
                                <td><?php echo htmlspecialchars($user['Username']); ?></td>
                                <td><?php echo $user_type_persian[$user['UserType']] ?? htmlspecialchars($user['UserType']); ?></td>
                                <td><?php echo $roles_str; ?></td>
                                <td>
                                    <span class="badge <?php echo $user['IsActive'] ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo $user['IsActive'] ? 'فعال' : 'غیرفعال'; ?>
                                    </span>
                                </td>
                                <td><?php echo to_jalali($user['CreatedAt'], 'yyyy/MM/dd'); ?></td>
                                <td class="actions-cell">
                                    <a href="edit.php?user_id=<?php echo $user['UserID']; ?>" class="btn btn-sm btn-warning" title="ویرایش">
                                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                    </a>
                                    <a href="actions/delete_user.php?user_id=<?php echo $user['UserID']; ?>&csrf_token=<?php echo generate_csrf_token(); /* Function to be created */ ?>" class="btn btn-sm btn-danger" title="حذف" onclick="return confirm('آیا از حذف این کاربر مطمئن هستید؟ این عمل غیرقابل بازگشت است.');">
                                         <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1):
                $query_params = http_build_query(array_filter([
                    'search' => $search_term,
                    'user_type' => $filter_user_type,
                    'status_filter' => $filter_status
                ]));
            ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center flex-wrap">
                    <?php if ($page > 1): ?>
                        <li class="page-item"><a class="page-link" href="?page=1&<?php echo $query_params; ?>">اولین</a></li>
                        <li class="page-item"><a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo $query_params; ?>">قبلی</a></li>
                    <?php endif; ?>

                    <?php
                        $max_links = 5; // Max number of page links to show
                        $start_page = max(1, $page - floor($max_links / 2));
                        $end_page = min($total_pages, $start_page + $max_links - 1);
                        if ($end_page - $start_page + 1 < $max_links) { // Adjust start_page if end_page is at max
                            $start_page = max(1, $end_page - $max_links + 1);
                        }

                        if ($start_page > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo $query_params; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor;
                        if ($end_page < $total_pages) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    ?>

                    <?php if ($page < $total_pages): ?>
                        <li class="page-item"><a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo $query_params; ?>">بعدی</a></li>
                        <li class="page-item"><a class="page-link" href="?page=<?php echo $total_pages; ?>&<?php echo $query_params; ?>">آخرین</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>

        <?php elseif ($users_result): ?>
            <div class="alert alert-info mt-3">هیچ کاربری با این مشخصات یافت نشد.</div>
        <?php endif; ?>
        <?php if($stmt_users && is_object($stmt_users)) $stmt_users->close(); ?>
    </div>
</div>

<?php
// Function to generate CSRF token (should be in helper_functions.php or a security helper)
if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}
if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
// Generate a token for use in forms on this page if needed, e.g. delete links
generate_csrf_token();
?>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
