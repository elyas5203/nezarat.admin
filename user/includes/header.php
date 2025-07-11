<?php
// user/includes/header.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../includes/config/db_config.php';
require_once __DIR__ . '/../../../includes/functions/helper_functions.php';

if (!is_user_logged_in()) {
    $login_url = '/my_site/user/auth/login.php';
    header("Location: " . $login_url);
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

$base_asset_url = '/my_site/assets';
$user_base_url = '/my_site/user';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل کاربری - سامانه مدیریت دبستان</title>
    <link rel="stylesheet" href="<?php echo $base_asset_url; ?>/css/common/reset.css">
    <link rel="stylesheet" href="<?php echo $base_asset_url; ?>/css/common/sidebar.css"> <!-- Common sidebar styles -->
    <link rel="stylesheet" href="<?php echo $base_asset_url; ?>/css/user/dashboard.css">   <!-- User specific dashboard styles -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@100..900&display=swap" rel="stylesheet">
    <script src="<?php echo $base_asset_url; ?>/js/common/sidebar.js" defer></script>
</head>
<body class="user-panel">
    <div id="loading-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(255,255,255,0.9); z-index: 10000; display: flex; justify-content: center; align-items: center;">
        <div class="spinner" style="border: 8px solid #f3f3f3; border-top: 8px solid #17a2b8; border-radius: 50%; width: 60px; height: 60px; animation: spin 1s linear infinite;"></div>
         <style> @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } } </style>
    </div>

    <div class="dashboard-container">
        <?php include_once __DIR__ . '/sidebar.php'; ?>
        <div class="main-content">
            <header class="main-header">
                <div class="header-left">
                    <button id="hamburger-menu" aria-label="Toggle Menu">
                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                    </button>
                    <span class="welcome-message">سلام، <?php echo htmlspecialchars($_SESSION['username'] ?? 'کاربر گرامی'); ?>!</span>
                </div>
                <div class="header-right">
                    <div class="datetime">
                        <span id="current-date-placeholder">درحال بارگذاری تاریخ...</span> | <span id="live-time-placeholder">درحال بارگذاری ساعت...</span>
                    </div>
                    <?php
                    // Notification data fetching logic (moved from the bottom of the file for clarity)
                    $current_user_id_for_notif_header = get_current_user_id();
                    $unread_notifications_count_header = 0;
                    $recent_notifications_header = [];

                    if ($current_user_id_for_notif_header) {
                        $stmt_count_header = $conn->prepare("SELECT COUNT(NotificationID) as count FROM Notifications WHERE UserID = ? AND IsRead = FALSE");
                        if ($stmt_count_header) {
                            $stmt_count_header->bind_param("i", $current_user_id_for_notif_header);
                            $stmt_count_header->execute();
                            $unread_notifications_count_header = $stmt_count_header->get_result()->fetch_assoc()['count'] ?? 0;
                            $stmt_count_header->close();
                        }

                        $limit_notif_display_header = 5;
                        $stmt_recent_header = $conn->prepare("
                            SELECT NotificationID, Message, Link, CreatedAt, IsRead
                            FROM Notifications WHERE UserID = ? ORDER BY IsRead ASC, CreatedAt DESC LIMIT ?");
                        if ($stmt_recent_header) {
                            $stmt_recent_header->bind_param("ii", $current_user_id_for_notif_header, $limit_notif_display_header);
                            $stmt_recent_header->execute();
                            $result_recent_header = $stmt_recent_header->get_result();
                            while ($row_notif_header = $result_recent_header->fetch_assoc()) {
                                $recent_notifications_header[] = $row_notif_header;
                            }
                            $stmt_recent_header->close();
                        }
                    }
                    $csrf_token_mark_all_read_user = generate_csrf_token('mark_all_read_user');
                    ?>
                    <div class="dropdown notification-dropdown">
                        <a href="#" class="header-icon-btn notification-btn dropdown-toggle" id="userNotificationDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="اعلانات">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                            <?php if ($unread_notifications_count_header > 0): ?>
                                <span class="notification-badge" id="user-notification-badge-count"><?php echo $unread_notifications_count_header; ?></span>
                            <?php endif; ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-left dropdown-menu-arrow animated--fade-in" aria-labelledby="userNotificationDropdown" id="user-notification-dropdown-menu">
                            <div class="dropdown-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">اعلانات</h6>
                                <?php if ($unread_notifications_count_header > 0): ?>
                                <a href="<?php echo $user_base_url; ?>/notifications/mark_all_read.php?csrf_token=<?php echo $csrf_token_mark_all_read_user; ?>" class="small mark-all-read-link" id="user-mark-all-notifications-read">خوانده شدن همه</a>
                                <?php endif; ?>
                            </div>
                            <div class="dropdown-divider my-0"></div>
                            <div class="notification-items-container py-2" style="max-height: 300px; overflow-y:auto;">
                            <?php if (!empty($recent_notifications_header)): ?>
                                <?php foreach ($recent_notifications_header as $notif_h_u): ?>
                                    <a class="dropdown-item notification-item d-flex align-items-center <?php echo !$notif_h_u['IsRead'] ? 'unread font-weight-bold' : ''; ?>"
                                       href="<?php echo !empty($notif_h_u['Link']) ? htmlspecialchars($notif_h_u['Link']) . (strpos($notif_h_u['Link'], '?') === false ? '?' : '&') . 'notif_id=' . $notif_h_u['NotificationID'] : '#'; ?>"
                                       data-notif-id="<?php echo $notif_h_u['NotificationID']; ?>">
                                       <div class="mr-3"> <!-- RTL: ml-3 -->
                                            <div class="icon-circle bg-primary-user"> <!-- Use user panel primary color -->
                                                <svg class="text-white" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 15c-.55 0-1-.45-1-1v-4c0-.55.45-1 1-1s1 .45 1 1v4c0 .55-.45 1-1 1zm0-8c-.55 0-1-.45-1-1V7c0-.55.45-1 1-1s1 .45 1 1v1c0 .55-.45 1-1 1z"/></svg>
                                            </div>
                                        </div>
                                       <div>
                                           <div class="small text-gray-500"><?php echo to_jalali($notif_h_u['CreatedAt'], 'yyyy/MM/dd HH:mm'); ?></div>
                                           <span class="notification-message"><?php echo mb_substr(htmlspecialchars($notif_h_u['Message']), 0, 60) . (mb_strlen($notif_h_u['Message']) > 60 ? '...' : ''); ?></span>
                                       </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="dropdown-item text-muted text-center small py-3">هیچ اعلانی وجود ندارد.</span>
                            <?php endif; ?>
                            </div>
                            <a class="dropdown-item text-center small text-gray-500 py-2" href="<?php echo $user_base_url; ?>/notifications/index.php">مشاهده همه اعلانات</a>
                        </div>
                    </div>

                    <a href="<?php echo $user_base_url; ?>/auth/logout.php" class="logout-btn" aria-label="خروج">
                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                        <span>خروج</span>
                    </a>
                </div>
            </header>
            <main class="page-content">
                <!-- Page specific content will go here -->
