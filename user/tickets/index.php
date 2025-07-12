<?php
require_once __DIR__ . '/../includes/header.php';

$user_id_ti = get_current_user_id(); // Renamed to avoid conflict
if (!$user_id_ti) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'برای مشاهده تیکت‌ها باید وارد شوید.'];
    header("Location: " . ($user_base_url ?? '/my_site/user') . "/auth/login.php");
    exit;
}

$page_title_ut_index_page = "تیکت‌های پشتیبانی من";
$tickets_list_user_display = [];
$errors_ut_index_page = [];

// Ticket Statuses and Badges
$ticket_statuses_user_map_display = [
    'open' => ['label' => 'باز', 'badge' => 'primary'],
    'pending_admin_reply' => ['label' => 'در انتظار پاسخ ادمین', 'badge' => 'info text-dark'],
    'pending_user_reply' => ['label' => 'در انتظار پاسخ شما', 'badge' => 'warning text-dark'],
    'on_hold' => ['label' => 'معلق', 'badge' => 'secondary'],
    'closed' => ['label' => 'بسته شده', 'badge' => 'success'],
    'resolved' => ['label' => 'حل شده', 'badge' => 'success'],
];
$priority_display_map_user_page = ['low' => 'کم', 'medium' => 'متوسط', 'high' => 'زیاد', 'urgent' => 'فوری'];


if($conn){
    $stmt_tickets_page = $conn->prepare(
        "SELECT t.TicketID, t.Subject, t.Status, t.Priority, t.CreatedAt, t.LastUpdatedAt,
                (SELECT COUNT(tr.ReplyID) FROM TicketReplies tr WHERE tr.TicketID = t.TicketID AND tr.IsReadByCreator = FALSE AND tr.IsAdminReply = TRUE) as UnreadAdminRepliesCount
         FROM Tickets t
         WHERE t.CreatedByUserID = ?
         ORDER BY t.LastUpdatedAt DESC"
    );
    if($stmt_tickets_page){
        $stmt_tickets_page->bind_param("i", $user_id_ti);
        if($stmt_tickets_page->execute()){
            $result_tickets_page = $stmt_tickets_page->get_result();
            while($row_ti = $result_tickets_page->fetch_assoc()){
                $tickets_list_user_display[] = $row_ti;
            }
        } else {
            $errors_ut_index_page['db_fetch'] = "خطا در بارگذاری لیست تیکت‌ها: " . $stmt_tickets_page->error;
        }
        $stmt_tickets_page->close();
    } else {
        $errors_ut_index_page['db_prepare'] = "خطا در آماده سازی کوئری تیکت‌ها: " . $conn->error;
    }
} else {
    $errors_ut_index_page['db_conn'] = "خطا در اتصال به پایگاه داده.";
}
?>
<div class="page-header">
    <h1><?php echo $page_title_ut_index_page; ?></h1>
    <div class="page-header-actions">
        <a href="create.php" class="btn btn-primary-user btn-lg"> <!-- Made button larger -->
            <em class="bi bi-plus-circle-fill icon me-2"></em> ایجاد تیکت جدید
        </a>
    </div>
</div>

<?php if (isset($_SESSION['flash_message'])): $flash_ti = $_SESSION['flash_message']; ?>
    <div class="alert alert-<?php echo $flash_ti['type']; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($flash_ti['text']); unset($_SESSION['flash_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if (!empty($errors_ut_index_page)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <p class="mb-0"><strong>خطا:</strong></p>
        <ul class="mb-0 ps-3"><?php foreach ($errors_ut_index_page as $error_msg_ti): ?><li><?php echo htmlspecialchars($error_msg_ti); ?></li><?php endforeach; ?></ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0">لیست تیکت‌های شما</h5>
    </div>
    <div class="card-body p-0"> <!-- Remove padding for full-width table -->
        <?php if (empty($tickets_list_user_display)): ?>
            <div class="text-center py-5">
                <em class="bi bi-ticket-detailed fs-1 text-muted mb-3 d-block"></em>
                <p class="mt-3 lead">شما هنوز هیچ تیکتی ایجاد نکرده‌اید.</p>
                <a href="create.php" class="btn btn-lg btn-primary-user mt-2 px-4">ایجاد اولین تیکت</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0"> <!-- mb-0 to remove bottom margin -->
                    <thead class="table-light">
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">شناسه</th>
                            <th scope="col">موضوع</th>
                            <th scope="col">اولویت</th>
                            <th scope="col">وضعیت</th>
                            <th scope="col">آخرین بروزرسانی</th>
                            <th scope="col" class="text-center">پاسخ جدید</th>
                            <th scope="col" class="text-center">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($tickets_list_user_display as $index_ti => $ticket_item): ?>
                            <tr class="<?php echo ($ticket_item['UnreadAdminRepliesCount'] > 0) ? 'table-info fw-bold' : ''; ?>">
                                <td><?php echo $index_ti + 1; ?></td>
                                <td>#<?php echo $ticket_item['TicketID']; ?></td>
                                <td>
                                    <a href="view.php?ticket_id=<?php echo $ticket_item['TicketID']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($ticket_item['Subject']); ?>
                                    </a>
                                </td>
                                <td><?php echo $priority_display_map_user_page[$ticket_item['Priority']] ?? htmlspecialchars($ticket_item['Priority']); ?></td>
                                <td>
                                    <span class="badge fs-xs bg-<?php echo $ticket_statuses_user_map_display[$ticket_item['Status']]['badge'] ?? 'secondary'; ?>">
                                        <?php echo $ticket_statuses_user_map_display[$ticket_item['Status']]['label'] ?? htmlspecialchars($ticket_item['Status']); ?>
                                    </span>
                                </td>
                                <td><?php echo to_jalali($ticket_item['LastUpdatedAt'], 'yyyy/MM/dd HH:mm'); ?></td>
                                <td class="text-center">
                                    <?php if($ticket_item['UnreadAdminRepliesCount'] > 0): ?>
                                        <span class="badge bg-danger rounded-pill"><?php echo $ticket_item['UnreadAdminRepliesCount']; ?></span>
                                    <?php else: echo '---'; endif; ?>
                                </td>
                                <td class="text-center">
                                    <a href="view.php?ticket_id=<?php echo $ticket_item['TicketID']; ?>" class="btn btn-sm btn-outline-primary-user" title="مشاهده و پاسخ به تیکت">
                                        <em class="bi bi-chat-dots-fill"></em> مشاهده
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- TODO: Pagination if many tickets -->
        <?php endif; ?>
    </div>
</div>
<style>.fs-xs { font-size: .78rem; }</style>
<?php
require_once __DIR__ . '/../includes/footer.php';
?>
