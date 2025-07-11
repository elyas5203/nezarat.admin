<?php
// admin/includes/header.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Adjust the path to db_config.php and helper_functions.php based on the actual location
// __DIR__ gives the directory of the current file (admin/includes)
require_once __DIR__ . '/../../../includes/config/db_config.php';
require_once __DIR__ . '/../../../includes/functions/helper_functions.php';

if (!is_admin_logged_in()) {
    // Construct the correct absolute or relative path to the login page
    // Assuming 'my_site' is the root directory of your project in htdocs
    $login_url = '/my_site/admin/auth/login.php';
    header("Location: " . $login_url);
    exit;
}
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF'])); // To help differentiate index.php in different folders

// Define a base URL for assets to simplify paths
$base_asset_url = '/my_site/assets';
$admin_base_url = '/my_site/admin';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل ادمین - سامانه مدیریت دبستان</title>
    <link rel="stylesheet" href="<?php echo $base_asset_url; ?>/css/common/reset.css">
    <link rel="stylesheet" href="<?php echo $base_asset_url; ?>/css/common/sidebar.css">
    <link rel="stylesheet" href="<?php echo $base_asset_url; ?>/css/admin/dashboard.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@100..900&display=swap" rel="stylesheet">
    <script src="<?php echo $base_asset_url; ?>/js/common/sidebar.js" defer></script>
</head>
<body class="admin-panel">
    <div id="loading-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(255,255,255,0.9); z-index: 10000; display: flex; justify-content: center; align-items: center;">
        <div class="spinner" style="border: 8px solid #f3f3f3; border-top: 8px solid #007bff; border-radius: 50%; width: 60px; height: 60px; animation: spin 1s linear infinite;"></div>
        <style> @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } } </style>
    </div>

    <div class="dashboard-container">
        <?php include_once __DIR__ . '/sidebar.php'; // Correct path to sidebar.php ?>
        <div class="main-content">
            <header class="main-header">
                <div class="header-left">
                    <button id="hamburger-menu" aria-label="Toggle Menu">
                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                    </button>
                    <span class="welcome-message">خوش آمدید، <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'ادمین'); ?>!</span>
                </div>
                <div class="header-right">
                    <div class="datetime">
                        <span id="current-date-placeholder">درحال بارگذاری تاریخ...</span> | <span id="live-time-placeholder">درحال بارگذاری ساعت...</span>
                    </div>
                    <a href="<?php echo $admin_base_url; ?>/auth/logout.php" class="logout-btn" aria-label="خروج">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                        <span>خروج</span>
                    </a>
                </div>
            </header>
            <main class="page-content">
                <!-- Page specific content will go here -->
