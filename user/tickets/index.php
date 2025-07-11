<?php
// user/tickets/index.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth

$user_id = get_current_user_id();
if (!$user_id) { // Should not happen if header.php enforces login
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'برای دسترسی به این بخش باید وارد شوید.'];
    header("Location: " . $user_base_url . "/auth/login.php"); // $user_base_url from header.php
    exit;
}

// Fetch tickets created by the current user
// Also fetch the name of the user who last replied (if not the ticket creator)
$stmt_tickets = $conn->prepare("
    SELECT
        t.TicketID, t.Subject, t.Status, t.Priority, t.CreatedAt, t.UpdatedAt,
        d.DepartmentName AS AssignedDepartmentName,
        (SELECT COUNT(tr.ReplyID)
         FROM TicketReplies tr
         WHERE tr.TicketID = t.TicketID AND tr.UserID != ? AND tr.IsReadByCreator = FALSE) AS UnreadRepliesByOthers,
        lr.UserID AS LastReplierUserID,
        CONCAT(u_lr.FirstName, ' ', u_lr.LastName) as LastReplierName
    FROM Tickets t
    LEFT JOIN Departments d ON t.AssignedToDepartmentID = d.DepartmentID
    LEFT JOIN (
        SELECT TicketID, UserID, MAX(CreatedAt) as MaxDate
        FROM TicketReplies
        GROUP BY TicketID, UserID
        ORDER BY MaxDate DESC
    ) lr_sub ON lr_sub.TicketID = t.TicketID
    LEFT JOIN TicketReplies lr ON lr.TicketID = lr_sub.TicketID AND lr.CreatedAt = lr_sub.MaxDate
    LEFT JOIN Users u_lr ON lr.UserID = u_lr.UserID
    WHERE t.CreatedByUserID = ?
    GROUP BY t.TicketID, t.Subject, t.Status, t.Priority, t.CreatedAt, t.UpdatedAt, AssignedDepartmentName, LastReplierUserID, LastReplierName
    ORDER BY UnreadRepliesByOthers DESC, t.UpdatedAt DESC, t.CreatedAt DESC
");


$tickets = [];
if ($stmt_tickets) {
    $stmt_tickets->bind_param("ii", $user_id, $user_id);
    $stmt_tickets->execute();
    $result = $stmt_tickets->get_result();
    while ($row = $result->fetch_assoc()) {
        $tickets[] = $row;
    }
    $stmt_tickets->close();
} else {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا در بارگذاری تیکت‌ها: ' . $conn->error];
}

$status_persian = ['open' => 'باز', 'in_progress' => 'در حال بررسی', 'resolved' => 'حل شده', 'closed' => 'بسته شده', 'urgent' => 'فوری'];
$priority_persian = ['low' => 'کم', 'medium' => 'متوسط', 'high' => 'زیاد', 'urgent' => 'فوری'];
$status_badge_class = ['open' => 'info', 'in_progress' => 'warning', 'resolved' => 'success', 'closed' => 'secondary', 'urgent' => 'danger'];
$priority_badge_class = ['low' => 'info', 'medium' => 'warning', 'high' => 'orange', 'urgent' => 'danger'];


?>
<div class="page-header">
    <h1>تیکت‌های پشتیبانی من</h1>
    <div class="page-header-actions">
        <a href="create.php" class="btn btn-primary-user btn-lg">
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            <span>ایجاد تیکت جدید</span>
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
// For redirects from create/view
if (isset($_GET['action_status'])): ?>
    <div class="alert <?php echo ($_GET['action_status'] == 'success') ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars(urldecode($_GET['message'] ?? '')); ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close" style="/* same style */"><span aria-hidden='true'>&times;</span></button>
    </div>
<?php endif; ?>
<script> /* JS for alert dismissal */
    document.querySelectorAll('.alert .close').forEach(function(button) {
        button.addEventListener('click', function(event) {
            let alertNode = event.target.closest('.alert');
            if(alertNode) {
                if (typeof(bootstrap) !== 'undefined' && bootstrap.Alert && bootstrap.Alert.getInstance(alertNode)) { bootstrap.Alert.getInstance(alertNode).close(); }
                else { alertNode.style.display = 'none'; }
            }
        });
    });
    setTimeout(function() {
        document.querySelectorAll('.alert-dismissible.show').forEach(function(alert){
             if (typeof(bootstrap) !== 'undefined' && bootstrap.Alert && bootstrap.Alert.getInstance(alert)) { bootstrap.Alert.getInstance(alert).close(); }
             else { alert.style.display = 'none'; }
        });
    }, 7000);
</script>

<div class="card shadow-sm">
    <div class="card-header">
        <span class="card-title-text">لیست تیکت‌های شما</span>
    </div>
    <div class="card-body">
        <?php if (!empty($tickets)): ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped tickets-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>شناسه</th>
                            <th>عنوان</th>
                            <th>بخش مربوطه</th>
                            <th>وضعیت</th>
                            <th>اولویت</th>
                            <th>آخرین بروزرسانی</th>
                            <th>آخرین پاسخ از</th>
                            <th class="actions-column">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $row_num = 1; foreach ($tickets as $ticket): ?>
                            <tr class="<?php echo ($ticket['UnreadRepliesByOthers'] > 0) ? 'ticket-unread' : ''; ?>">
                                <td><?php echo $row_num++; ?></td>
                                <td><code>#<?php echo $ticket['TicketID']; ?></code></td>
                                <td>
                                    <a href="view.php?ticket_id=<?php echo $ticket['TicketID']; ?>" class="ticket-subject">
                                        <?php echo htmlspecialchars($ticket['Subject']); ?>
                                        <?php if ($ticket['UnreadRepliesByOthers'] > 0): ?>
                                            <span class="badge badge-danger ml-2"><?php echo $ticket['UnreadRepliesByOthers']; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($ticket['AssignedDepartmentName'] ?? 'پشتیبانی'); ?></td>
                                <td><span class="badge badge-<?php echo $status_badge_class[$ticket['Status']] ?? 'secondary'; ?>"><?php echo $status_persian[$ticket['Status']] ?? htmlspecialchars($ticket['Status']); ?></span></td>
                                <td><span class="badge badge-<?php echo $priority_badge_class[$ticket['Priority']] ?? 'secondary'; ?>"><?php echo $priority_persian[$ticket['Priority']] ?? htmlspecialchars($ticket['Priority']); ?></span></td>
                                <td class="small text-muted"><?php echo to_jalali($ticket['UpdatedAt'], 'yyyy/MM/dd HH:mm'); ?></td>
                                <td class="small text-muted">
                                    <?php
                                        if ($ticket['LastReplierUserID'] == $user_id) echo 'شما';
                                        elseif (!empty($ticket['LastReplierName'])) echo htmlspecialchars($ticket['LastReplierName']);
                                        else echo '-';
                                    ?>
                                </td>
                                <td class="actions-cell">
                                    <a href="view.php?ticket_id=<?php echo $ticket['TicketID']; ?>" class="btn btn-sm btn-info" title="مشاهده و پاسخ">
                                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                        <span>مشاهده</span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info mb-0">شما تاکنون هیچ تیکتی ایجاد نکرده‌اید. برای طرح سوال یا مشکل جدید، <a href="create.php" class="alert-link">یک تیکت جدید ایجاد کنید</a>.</div>
        <?php endif; ?>
    </div>
</div>
<style>
    .tickets-table th, .tickets-table td { vertical-align: middle; font-size: 0.9rem; }
    .ticket-subject { font-weight: 500; color: var(--user-panel-primary-color, #17a2b8); }
    .ticket-subject:hover { color: var(--user-panel-primary-hover-color, #138496); text-decoration: none; }
    .ticket-unread { background-color: #fff3cd !important; /* Light yellow for unread */ }
    .ticket-unread td a.ticket-subject { font-weight: bold; }
    .badge { padding: 0.4em 0.6em; font-size: 0.75rem;}
    .badge-orange { background-color: #fd7e14; color: white;} /* Custom orange badge */
</style>
<?php
require_once __DIR__ . '/../includes/footer.php';
?>
