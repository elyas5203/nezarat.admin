<?php
// admin/tickets/index.php
require_once __DIR__ . '/../includes/header.php';

$admin_id = get_current_user_id();

$filter_status = sanitize_input($_GET['status'] ?? '');
$filter_priority = sanitize_input($_GET['priority'] ?? '');
$filter_department = sanitize_input($_GET['department_id'] ?? '');
$search_term = sanitize_input($_GET['search'] ?? '');

$where_clauses = [];
$params = [];
$types = "";

if (!empty($filter_status)) { $where_clauses[] = "t.Status = ?"; $params[] = $filter_status; $types .= "s"; }
if (!empty($filter_priority)) { $where_clauses[] = "t.Priority = ?"; $params[] = $filter_priority; $types .= "s"; }
if (!empty($filter_department)) {
    if ($filter_department == 'none') $where_clauses[] = "t.AssignedToDepartmentID IS NULL";
    else { $where_clauses[] = "t.AssignedToDepartmentID = ?"; $params[] = (int)$filter_department; $types .= "i"; }
}
if (!empty($search_term)) {
    $where_clauses[] = "(t.Subject LIKE ? OR t.TicketID LIKE ? OR u_creator.Username LIKE ? OR CONCAT(u_creator.FirstName, ' ', u_creator.LastName) LIKE ?)";
    $search_like = "%{$search_term}%"; array_push($params, $search_like, $search_like, $search_like, $search_like); $types .= "ssss";
}

$sql_where_tickets = "";
if (!empty($where_clauses)) $sql_where_tickets = " WHERE " . implode(" AND ", $where_clauses);

$records_per_page_admin_tickets = 20;
$page_admin_tickets = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page_admin_tickets < 1) $page_admin_tickets = 1;
$offset_admin_tickets = ($page_admin_tickets - 1) * $records_per_page_admin_tickets;

$total_sql_admin_tickets = "SELECT COUNT(t.TicketID) as total
                            FROM Tickets t
                            LEFT JOIN Users u_creator ON t.CreatedByUserID = u_creator.UserID" . $sql_where_tickets;
$stmt_total_admin_tickets = $conn->prepare($total_sql_admin_tickets);
$total_records_admin_tickets = 0; $total_pages_admin_tickets = 0;
if ($stmt_total_admin_tickets) {
    if (!empty($types)) $stmt_total_admin_tickets->bind_param($types, ...$params);
    $stmt_total_admin_tickets->execute();
    $total_records_admin_tickets = $stmt_total_admin_tickets->get_result()->fetch_assoc()['total'] ?? 0;
    $total_pages_admin_tickets = ceil($total_records_admin_tickets / $records_per_page_admin_tickets);
    if ($page_admin_tickets > $total_pages_admin_tickets && $total_pages_admin_tickets > 0) {
        $page_admin_tickets = $total_pages_admin_tickets; $offset_admin_tickets = ($page_admin_tickets - 1) * $records_per_page_admin_tickets;
    }
    $stmt_total_admin_tickets->close();
}

$sql_main_query = "
    SELECT
        t.TicketID, t.Subject, t.Status, t.Priority, t.CreatedAt, t.UpdatedAt,
        t.CreatedByUserID, u_creator.Username AS CreatorUsername, CONCAT(u_creator.FirstName, ' ', u_creator.LastName) AS CreatorFullName,
        d.DepartmentName AS AssignedDepartmentName,
        (SELECT COUNT(tr.ReplyID) FROM TicketReplies tr WHERE tr.TicketID = t.TicketID AND tr.IsReadByAdmin = FALSE AND tr.UserID != ?) AS UnreadRepliesForAdmin
    FROM Tickets t
    JOIN Users u_creator ON t.CreatedByUserID = u_creator.UserID
    LEFT JOIN Departments d ON t.AssignedToDepartmentID = d.DepartmentID
    " . $sql_where_tickets . "
    ORDER BY UnreadRepliesForAdmin DESC, FIELD(t.Priority, 'urgent', 'high', 'medium', 'low'), t.Status = 'open' DESC, t.Status = 'in_progress' DESC, t.UpdatedAt DESC
    LIMIT ? OFFSET ?";

$stmt_tickets_admin = $conn->prepare($sql_main_query);
$tickets_admin = [];
if ($stmt_tickets_admin) {
    $current_params_admin = $params; $current_types_admin = $types;
    $current_params_admin[] = $admin_id; $current_types_admin .= "i"; // For UnreadRepliesForAdmin
    $current_params_admin[] = $records_per_page_admin_tickets; $current_types_admin .= "i";
    $current_params_admin[] = $offset_admin_tickets; $current_types_admin .= "i";

    $stmt_tickets_admin->bind_param($current_types_admin, ...$current_params_admin);
    $stmt_tickets_admin->execute();
    $result_admin = $stmt_tickets_admin->get_result();
    while ($row = $result_admin->fetch_assoc()) $tickets_admin[] = $row;
    $stmt_tickets_admin->close();
} else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا بارگذاری تیکت‌ها: ' . $conn->error]; }

$status_persian_map_admin = ['open' => 'باز', 'in_progress' => 'در حال بررسی', 'resolved' => 'حل شده', 'closed' => 'بسته شده', 'urgent' => 'فوری'];
$priority_persian_map_admin = ['low' => 'کم', 'medium' => 'متوسط', 'high' => 'زیاد', 'urgent' => 'فوری'];
$status_badge_class_admin = ['open' => 'info', 'in_progress' => 'warning', 'resolved' => 'success', 'closed' => 'secondary', 'urgent' => 'danger'];
$priority_badge_class_admin = ['low' => 'info', 'medium' => 'warning', 'high' => 'orange', 'urgent' => 'danger'];

$departments_filter_query = $conn->query("SELECT DepartmentID, DepartmentName FROM Departments ORDER BY DepartmentName");
$available_departments_filter = [];
if ($departments_filter_query) { while($dept_f = $departments_filter_query->fetch_assoc()) $available_departments_filter[] = $dept_f; $departments_filter_query->close(); }
?>
<div class="page-header"><h1>مدیریت تیکت‌های پشتیبانی</h1></div>

<?php if (isset($_SESSION['flash_message'])): $flash = $_SESSION['flash_message']; unset($_SESSION['flash_message']); ?>
    <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert"><?php echo $flash['text']; ?>
    <button type="button" class="close" data-dismiss="alert" aria-label="Close" style="background:none; border:none; font-size:1.5rem; position:absolute; top:0; left:0; padding: 0.75rem 1.25rem;"><span aria-hidden="true">&times;</span></button></div>
<?php endif; ?>
<?php if (isset($_GET['action_status'])): ?>
    <div class="alert <?php echo (strpos($_GET['action_status'], 'success') !== false) ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars(urldecode($_GET['message'] ?? '')); ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close" style="/* same */"><span aria-hidden="true">&times;</span></button>
    </div>
<?php endif; ?>
<script> /* JS for alert dismissal */ /* ... */ </script>

<div class="filter-search-bar card mb-4 shadow-sm">
    <form method="GET" action="index.php" class="form-inline-flex p-3">
        <div class="form-group"><label for="search_term_admin" class="sr-only">جستجو</label><input type="text" id="search_term_admin" name="search" class="form-control" placeholder="جستجو (شناسه، موضوع، کاربر...)" value="<?php echo htmlspecialchars($search_term); ?>"></div>
        <div class="form-group"><label for="filter_status_admin" class="sr-only">وضعیت</label><select id="filter_status_admin" name="status" class="form-control custom-select"><option value="">همه وضعیت‌ها</option><?php foreach($status_persian_map_admin as $s_key => $s_val): ?><option value="<?php echo $s_key; ?>" <?php if($filter_status == $s_key) echo 'selected'; ?>><?php echo $s_val; ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label for="filter_priority_admin" class="sr-only">اولویت</label><select id="filter_priority_admin" name="priority" class="form-control custom-select"><option value="">همه اولویت‌ها</option><?php foreach($priority_persian_map_admin as $p_key => $p_val): ?><option value="<?php echo $p_key; ?>" <?php if($filter_priority == $p_key) echo 'selected'; ?>><?php echo $p_val; ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label for="filter_department_admin" class="sr-only">بخش</label><select id="filter_department_admin" name="department_id" class="form-control custom-select"><option value="">همه بخش‌ها</option><option value="none" <?php if($filter_department == 'none') echo 'selected'; ?>>بدون بخش</option><?php foreach($available_departments_filter as $dept_opt): ?><option value="<?php echo $dept_opt['DepartmentID']; ?>" <?php if($filter_department == $dept_opt['DepartmentID']) echo 'selected'; ?>><?php echo htmlspecialchars($dept_opt['DepartmentName']); ?></option><?php endforeach; ?></select></div>
        <button type="submit" class="btn btn-info">اعمال فیلتر</button><a href="index.php" class="btn btn-outline-secondary ml-2">پاک کردن</a>
    </form>
</div>

<div class="card shadow-sm">
    <div class="card-header"><span class="card-title-text">لیست تمام تیکت‌ها (کل: <?php echo $total_records_admin_tickets; ?>)</span></div>
    <div class="card-body">
        <?php if (!empty($tickets_admin)): ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped tickets-table admin-tickets-table">
                    <thead><tr><th>#</th><th>شناسه</th><th>موضوع</th><th>ایجاد کننده</th><th>بخش</th><th>وضعیت</th><th>اولویت</th><th>آخرین بروزرسانی</th><th>خوانده نشده</th><th class="actions-column">عملیات</th></tr></thead>
                    <tbody>
                        <?php $admin_row_num = $offset_admin_tickets + 1; foreach ($tickets_admin as $ticket_a): ?>
                            <tr class="<?php echo ($ticket_a['UnreadRepliesForAdmin'] > 0) ? 'ticket-unread-admin' : ''; ?>">
                                <td><?php echo $admin_row_num++; ?></td><td><code>#<?php echo $ticket_a['TicketID']; ?></code></td>
                                <td><a href="view.php?ticket_id=<?php echo $ticket_a['TicketID']; ?>" class="ticket-subject"><?php echo htmlspecialchars($ticket_a['Subject']); ?></a></td>
                                <td><?php echo htmlspecialchars($ticket_a['CreatorFullName'] . ' (@' . $ticket_a['CreatorUsername'] . ')'); ?></td>
                                <td><?php echo htmlspecialchars($ticket_a['AssignedDepartmentName'] ?? 'عمومی'); ?></td>
                                <td><span class="badge badge-<?php echo $status_badge_class_admin[$ticket_a['Status']] ?? 'secondary'; ?>"><?php echo $status_persian_map_admin[$ticket_a['Status']] ?? $ticket_a['Status']; ?></span></td>
                                <td><span class="badge badge-<?php echo $priority_badge_class_admin[$ticket_a['Priority']] ?? 'secondary'; ?>"><?php echo $priority_persian_map_admin[$ticket_a['Priority']] ?? $ticket_a['Priority']; ?></span></td>
                                <td class="small text-muted"><?php echo to_jalali($ticket_a['UpdatedAt'], 'yyyy/MM/dd HH:mm'); ?></td>
                                <td class="text-center"><?php if ($ticket_a['UnreadRepliesForAdmin'] > 0): ?><span class="badge badge-danger"><?php echo $ticket_a['UnreadRepliesForAdmin']; ?></span><?php else: ?>-<?php endif; ?></td>
                                <td class="actions-cell"><a href="view.php?ticket_id=<?php echo $ticket_a['TicketID']; ?>" class="btn btn-sm btn-primary" title="مشاهده و مدیریت"><svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path d="M20 14.66V20a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h5.34"></path><polygon points="18 2 22 6 12 16 8 16 8 12 18 2"></polygon></svg> <span>مدیریت</span></a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($total_pages_admin_tickets > 1): $query_params_admin_pagination = http_build_query(array_filter(['status' => $filter_status, 'priority' => $filter_priority, 'department_id' => $filter_department, 'search' => $search_term]));?>
            <nav class="mt-4"><ul class="pagination justify-content-center flex-wrap">
                <?php if ($page_admin_tickets > 1): ?> <li class="page-item"><a class="page-link" href="?page=1&<?php echo $query_params_admin_pagination; ?>">اولین</a></li> <li class="page-item"><a class="page-link" href="?page=<?php echo $page_admin_tickets - 1; ?>&<?php echo $query_params_admin_pagination; ?>">قبلی</a></li> <?php endif; ?>
                <?php $max_links_admin = 5; $start_page_admin = max(1, $page_admin_tickets - floor($max_links_admin / 2)); $end_page_admin = min($total_pages_admin_tickets, $start_page_admin + $max_links_admin - 1); if ($end_page_admin - $start_page_admin + 1 < $max_links_admin) $start_page_admin = max(1, $end_page_admin - $max_links_admin + 1); ?>
                <?php if ($start_page_admin > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; ?>
                <?php for ($i_admin = $start_page_admin; $i_admin <= $end_page_admin; $i_admin++): ?> <li class="page-item <?php echo ($i_admin == $page_admin_tickets) ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i_admin; ?>&<?php echo $query_params_admin_pagination; ?>"><?php echo $i_admin; ?></a></li> <?php endfor; ?>
                <?php if ($end_page_admin < $total_pages_admin_tickets) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; ?>
                <?php if ($page_admin_tickets < $total_pages_admin_tickets): ?> <li class="page-item"><a class="page-link" href="?page=<?php echo $page_admin_tickets + 1; ?>&<?php echo $query_params_admin_pagination; ?>">بعدی</a></li> <li class="page-item"><a class="page-link" href="?page=<?php echo $total_pages_admin_tickets; ?>&<?php echo $query_params_admin_pagination; ?>">آخرین</a></li> <?php endif; ?>
            </ul></nav>
            <?php endif; ?>
        <?php else: ?> <div class="alert alert-info mb-0">هیچ تیکتی<?php if(!empty(array_filter([$filter_status, $filter_priority, $filter_department, $search_term]))) echo " با این فیلترها"; ?> یافت نشد.</div> <?php endif; ?>
    </div>
</div>
<style> /* Styles from user/tickets/index.php, adapted for admin */
    .admin-tickets-table th, .admin-tickets-table td { font-size: 0.85rem; }
    .ticket-unread-admin { background-color: #fff3cd !important; }
    .ticket-unread-admin td a.ticket-subject { font-weight: bold; }
    .badge-orange { background-color: #fd7e14; color: white;}
</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
