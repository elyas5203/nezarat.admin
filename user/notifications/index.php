<?php
// user/notifications/index.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth, $user_base_url

$current_user_id_notif_page = get_current_user_id();
if (!$current_user_id_notif_page || !is_user_logged_in()) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'لطفاً ابتدا وارد شوید.'];
    header("Location: " . ($user_base_url ?? '/my_site/user') . "/auth/login.php");
    exit;
}

// Mark a specific notification as read if notif_id is in URL (when user clicks a notification link)
if (isset($_GET['notif_id']) && is_numeric($_GET['notif_id'])) {
    $notif_to_mark_read_page = (int)$_GET['notif_id'];
    $stmt_mark_one_page = $conn->prepare("UPDATE Notifications SET IsRead = TRUE WHERE NotificationID = ? AND UserID = ? AND IsRead = FALSE");
    if ($stmt_mark_one_page) {
        $stmt_mark_one_page->bind_param("ii", $notif_to_mark_read_page, $current_user_id_notif_page);
        $stmt_mark_one_page->execute();
        // We don't show a message for this, just update status. The link itself will take them to the content.
        $stmt_mark_one_page->close();
    }
}

$records_per_page_notif = 20; // Number of notifications per page
$page_notif = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page_notif < 1) $page_notif = 1;
$offset_notif = ($page_notif - 1) * $records_per_page_notif;

$total_records_notif = 0; $total_pages_notif = 0;
$total_stmt_notif = $conn->prepare("SELECT COUNT(NotificationID) as total FROM Notifications WHERE UserID = ?");
if ($total_stmt_notif) {
    $total_stmt_notif->bind_param("i", $current_user_id_notif_page);
    $total_stmt_notif->execute();
    $total_records_notif = $total_stmt_notif->get_result()->fetch_assoc()['total'] ?? 0;
    $total_pages_notif = ceil($total_records_notif / $records_per_page_notif);
    if($page_notif > $total_pages_notif && $total_pages_notif > 0) { $page_notif = $total_pages_notif; $offset_notif = ($page_notif - 1) * $records_per_page_notif; }
    $total_stmt_notif->close();
}

$stmt_all_notifs = $conn->prepare("SELECT NotificationID, Message, Link, CreatedAt, IsRead, RelatedEntityType, RelatedEntityID FROM Notifications WHERE UserID = ? ORDER BY CreatedAt DESC LIMIT ? OFFSET ?");
$all_notifications_user = []; // Renamed to avoid conflict
if ($stmt_all_notifs) {
    $stmt_all_notifs->bind_param("iii", $current_user_id_notif_page, $records_per_page_notif, $offset_notif);
    $stmt_all_notifs->execute();
    $result_all_notifs = $stmt_all_notifs->get_result();
    while($n_row = $result_all_notifs->fetch_assoc()) $all_notifications_user[] = $n_row;
    $stmt_all_notifs->close();
} else {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا در بارگذاری اعلانات: ' . $conn->error];
}
$csrf_token_mark_all_read_user_page = generate_csrf_token('mark_all_read_user'); // For the button on this page
?>
<div class="page-header">
    <h1>تمام اعلانات شما</h1>
    <div class="page-header-actions">
         <a href="<?php echo ($user_base_url ?? '/my_site/user'); ?>/notifications/mark_all_read.php?csrf_token=<?php echo $csrf_token_mark_all_read_user_page; ?>" class="btn btn-sm btn-outline-primary <?php if(empty(array_filter($all_notifications_user, function($n){ return !$n['IsRead'];}))) echo 'disabled';?>" id="mark-all-read-page-btn">
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
            <span>علامت‌گذاری همه به عنوان خوانده شده</span>
        </a>
    </div>
</div>
<?php
if (isset($_SESSION['flash_message'])) {
    $flash_notif_idx = $_SESSION['flash_message'];
    echo "<div class='alert alert-{$flash_notif_idx['type']} alert-dismissible fade show'>{$flash_notif_idx['text']}<button type='button' class='close' data-dismiss='alert'>&times;</button></div>";
    unset($_SESSION['flash_message']);
     echo "<script>setTimeout(function() {let alert = document.querySelector('.alert-dismissible.show'); if(alert){ if(typeof(bootstrap) !== 'undefined' && bootstrap.Alert && bootstrap.Alert.getInstance(alert)) { bootstrap.Alert.getInstance(alert).close(); } else { alert.style.display = 'none'; }}}, 7000);</script>";
}
?>

<div class="card shadow-sm">
   <div class="card-body p-0"> <!-- p-0 to make list-group flush with card borders -->
       <?php if (!empty($all_notifications_user)): ?>
           <ul class="list-group list-group-flush notification-page-list">
               <?php foreach ($all_notifications_user as $notif_item_page): ?>
                   <li class="list-group-item notification-list-item <?php echo !$notif_item_page['IsRead'] ? 'unread-item font-weight-bold' : 'read-item text-muted'; ?> py-3 px-3">
                       <div class="d-flex w-100 justify-content-between">
                           <div class="message-content flex-grow-1">
                               <?php if (!empty($notif_item_page['Link'])): ?>
                                   <a href="<?php echo htmlspecialchars($notif_item_page['Link']) . (strpos($notif_item_page['Link'], '?') === false ? '?' : '&') . 'notif_id=' . $notif_item_page['NotificationID']; ?>" class="notification-link stretched-link text-decoration-none <?php echo !$notif_item_page['IsRead'] ? 'text-dark' : 'text-muted'; ?>">
                                       <?php echo htmlspecialchars($notif_item_page['Message']); ?>
                                   </a>
                               <?php else: ?>
                                   <?php echo htmlspecialchars($notif_item_page['Message']); ?>
                               <?php endif; ?>
                           </div>
                           <small class="notification-item-time ml-3 text-nowrap"><?php echo to_jalali($notif_item_page['CreatedAt'], 'yyyy/MM/dd HH:mm'); ?></small>
                       </div>
                        <small class="text-muted d-block mt-1 entity-details">
                            <?php
                            if($notif_item_page['RelatedEntityType'] == 'ticket' || $notif_item_page['RelatedEntityType'] == 'ticket_reply') echo "مربوط به تیکت #" . htmlspecialchars($notif_item_page['RelatedEntityID'] ?? '');
                            elseif($notif_item_page['RelatedEntityType'] == 'ticket_status') echo "تغییر وضعیت تیکت #" . htmlspecialchars($notif_item_page['RelatedEntityID'] ?? '');
                            // Add more entity types as needed
                            ?>
                        </small>
                   </li>
               <?php endforeach; ?>
           </ul>
            <?php if ($total_pages_notif > 1): ?>
            <nav class="mt-3 p-3 border-top"><ul class="pagination justify-content-center mb-0">
                <?php if ($page_notif > 1): ?> <li class="page-item"><a class="page-link" href="?page=<?php echo $page_notif - 1; ?>">قبلی</a></li> <?php endif; ?>
                <?php for ($i_n_p = 1; $i_n_p <= $total_pages_notif; $i_n_p++): ?> <li class="page-item <?php echo ($i_n_p == $page_notif) ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i_n_p; ?>"><?php echo $i_n_p; ?></a></li> <?php endfor; ?>
                <?php if ($page_notif < $total_pages_notif): ?> <li class="page-item"><a class="page-link" href="?page=<?php echo $page_notif + 1; ?>">بعدی</a></li> <?php endif; ?>
            </ul></nav>
            <?php endif; ?>
       <?php else: ?>
           <p class="text-center text-muted p-5">شما هیچ اعلانی ندارید.</p>
       <?php endif; ?>
   </div>
</div>
<style>
   .notification-page-list .list-group-item { border-bottom: 1px solid #eee !important; }
   .notification-page-list .list-group-item:last-child { border-bottom: none !important; }
   .notification-list-item.unread-item { background-color: #e9f5ff; border-right: 4px solid #007bff; }
   .notification-list-item.read-item { background-color: #fdfdfd; }
   .notification-list-item .message-content { line-height: 1.4; }
   .notification-list-item .notification-link:hover { color: var(--user-panel-primary-hover-color, #0056b3); }
   .notification-item-time { font-size: 0.8em; white-space: nowrap; }
   .entity-details {font-size: 0.75em;}
</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
