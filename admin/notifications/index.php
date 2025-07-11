<?php
// admin/notifications/index.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth, $admin_base_url

$current_admin_id_notif_page = get_current_user_id(); // This should be admin_user_id
if (!$current_admin_id_notif_page || !is_admin_logged_in()) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'لطفاً ابتدا به عنوان ادمین وارد شوید.'];
    header("Location: " . ($admin_base_url ?? '/my_site/admin') . "/auth/login.php");
    exit;
}

// Mark a specific notification as read if notif_id is in URL
if (isset($_GET['notif_id']) && is_numeric($_GET['notif_id'])) {
    $notif_to_mark_read_admin_page = (int)$_GET['notif_id'];
    $stmt_mark_one_admin_page = $conn->prepare("UPDATE Notifications SET IsRead = TRUE WHERE NotificationID = ? AND UserID = ? AND IsRead = FALSE");
    if ($stmt_mark_one_admin_page) {
        $stmt_mark_one_admin_page->bind_param("ii", $notif_to_mark_read_admin_page, $current_admin_id_notif_page);
        $stmt_mark_one_admin_page->execute();
        $stmt_mark_one_admin_page->close();
    }
}

$records_per_page_notif_admin = 20;
$page_notif_admin = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page_notif_admin < 1) $page_notif_admin = 1;
$offset_notif_admin = ($page_notif_admin - 1) * $records_per_page_notif_admin;

$total_records_notif_admin = 0; $total_pages_notif_admin = 0;
$total_stmt_notif_admin = $conn->prepare("SELECT COUNT(NotificationID) as total FROM Notifications WHERE UserID = ?");
if ($total_stmt_notif_admin) {
    $total_stmt_notif_admin->bind_param("i", $current_admin_id_notif_page);
    $total_stmt_notif_admin->execute();
    $total_records_notif_admin = $total_stmt_notif_admin->get_result()->fetch_assoc()['total'] ?? 0;
    $total_pages_notif_admin = ceil($total_records_notif_admin / $records_per_page_notif_admin);
    if($page_notif_admin > $total_pages_notif_admin && $total_pages_notif_admin > 0) { $page_notif_admin = $total_pages_notif_admin; $offset_notif_admin = ($page_notif_admin - 1) * $records_per_page_notif_admin; }
    $total_stmt_notif_admin->close();
}

$stmt_all_notifs_admin = $conn->prepare("SELECT NotificationID, Message, Link, CreatedAt, IsRead, RelatedEntityType, RelatedEntityID FROM Notifications WHERE UserID = ? ORDER BY CreatedAt DESC LIMIT ? OFFSET ?");
$all_notifications_admin_list = []; // Renamed
if ($stmt_all_notifs_admin) {
    $stmt_all_notifs_admin->bind_param("iii", $current_admin_id_notif_page, $records_per_page_notif_admin, $offset_notif_admin);
    $stmt_all_notifs_admin->execute();
    $result_all_notifs_admin = $stmt_all_notifs_admin->get_result();
    while($n_row_admin = $result_all_notifs_admin->fetch_assoc()) $all_notifications_admin_list[] = $n_row_admin;
    $stmt_all_notifs_admin->close();
} else {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا در بارگذاری اعلانات ادمین: ' . $conn->error];
}
$csrf_token_mark_all_read_admin_page = generate_csrf_token('mark_all_read_admin');
?>
<div class="page-header">
    <h1>تمام اعلانات ادمین</h1>
    <div class="page-header-actions">
         <a href="<?php echo ($admin_base_url ?? '/my_site/admin'); ?>/notifications/mark_all_read.php?csrf_token=<?php echo $csrf_token_mark_all_read_admin_page; ?>" class="btn btn-sm btn-outline-primary <?php if(empty(array_filter($all_notifications_admin_list, function($n){ return !$n['IsRead'];}))) echo 'disabled';?>" id="mark-all-read-admin-page-btn">
             <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
            <span>علامت‌گذاری همه به عنوان خوانده شده</span>
        </a>
    </div>
</div>
<?php
if (isset($_SESSION['flash_message'])) {
    $flash_notif_idx_admin = $_SESSION['flash_message'];
    echo "<div class='alert alert-{$flash_notif_idx_admin['type']} alert-dismissible fade show'>{$flash_notif_idx_admin['text']}<button type='button' class='close' data-dismiss='alert'>&times;</button></div>";
    unset($_SESSION['flash_message']);
    echo "<script> /* JS for alert dismissal ... */ </script>";
}
?>

<div class="card shadow-sm">
   <div class="card-body p-0">
       <?php if (!empty($all_notifications_admin_list)): ?>
           <ul class="list-group list-group-flush notification-page-list">
               <?php foreach ($all_notifications_admin_list as $notif_item_admin_page): ?>
                   <li class="list-group-item notification-list-item <?php echo !$notif_item_admin_page['IsRead'] ? 'unread-item font-weight-bold' : 'read-item text-muted'; ?> py-3 px-3">
                       <div class="d-flex w-100 justify-content-between">
                           <div class="message-content flex-grow-1">
                               <?php if (!empty($notif_item_admin_page['Link'])): ?>
                                   <a href="<?php echo htmlspecialchars($notif_item_admin_page['Link']) . (strpos($notif_item_admin_page['Link'], '?') === false ? '?' : '&') . 'notif_id=' . $notif_item_admin_page['NotificationID']; ?>" class="notification-link stretched-link text-decoration-none <?php echo !$notif_item_admin_page['IsRead'] ? 'text-dark' : 'text-muted'; ?>">
                                       <?php echo htmlspecialchars($notif_item_admin_page['Message']); ?>
                                   </a>
                               <?php else: ?>
                                   <?php echo htmlspecialchars($notif_item_admin_page['Message']); ?>
                               <?php endif; ?>
                           </div>
                           <small class="notification-item-time ml-3 text-nowrap"><?php echo to_jalali($notif_item_admin_page['CreatedAt'], 'yyyy/MM/dd HH:mm'); ?></small>
                       </div>
                        <small class="text-muted d-block mt-1 entity-details">
                            <?php
                            if($notif_item_admin_page['RelatedEntityType'] == 'ticket' || $notif_item_admin_page['RelatedEntityType'] == 'ticket_reply') echo "مربوط به تیکت #" . htmlspecialchars($notif_item_admin_page['RelatedEntityID'] ?? '');
                            elseif($notif_item_admin_page['RelatedEntityType'] == 'new_user') echo "کاربر جدید با شناسه " . htmlspecialchars($notif_item_admin_page['RelatedEntityID'] ?? '');
                            // Add more entity types as needed
                            ?>
                        </small>
                   </li>
               <?php endforeach; ?>
           </ul>
            <?php if ($total_pages_notif_admin > 1): ?>
            <nav class="mt-3 p-3 border-top"><ul class="pagination justify-content-center mb-0">
                <?php if ($page_notif_admin > 1): ?> <li class="page-item"><a class="page-link" href="?page=<?php echo $page_notif_admin - 1; ?>">قبلی</a></li> <?php endif; ?>
                <?php for ($i_n_a = 1; $i_n_a <= $total_pages_notif_admin; $i_n_a++): ?> <li class="page-item <?php echo ($i_n_a == $page_notif_admin) ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i_n_a; ?>"><?php echo $i_n_a; ?></a></li> <?php endfor; ?>
                <?php if ($page_notif_admin < $total_pages_notif_admin): ?> <li class="page-item"><a class="page-link" href="?page=<?php echo $page_notif_admin + 1; ?>">بعدی</a></li> <?php endif; ?>
            </ul></nav>
            <?php endif; ?>
       <?php else: ?>
           <p class="text-center text-muted p-5">شما هیچ اعلانی ندارید.</p>
       <?php endif; ?>
   </div>
</div>
<style> /* Styles are similar to user/notifications/index.php */
   .notification-list-item.unread-item { background-color: #e7f3fe; border-right: 4px solid #007bff; }
   .notification-list-item.read-item { background-color: #fdfdfd; }
</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
