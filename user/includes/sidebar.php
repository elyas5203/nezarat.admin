<?php
// user/includes/sidebar.php
if (empty($user_base_url)) {
    $user_base_url = '/my_site/user'; // Fallback
}
if (empty($base_asset_url)) {
    $base_asset_url = '/my_site/assets'; // Fallback
}
if (empty($current_page)) {
    $current_page = basename($_SERVER['PHP_SELF']);
}
if (empty($current_dir)) {
    $current_dir = basename(dirname($_SERVER['PHP_SELF']));
}

// Function to check active menu, can be defined in helper_functions.php if used globally
if (!function_exists('is_active_user_menu')) {
    function is_active_user_menu($dirs, $page = null) {
        global $current_dir, $current_page;
        if (!is_array($dirs)) {
            $dirs = [$dirs];
        }
        if (in_array($current_dir, $dirs)) {
            if ($page === null) return true;
            return $current_page === $page;
        }
        return false;
    }
}
?>
<aside class="sidebar user-sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="<?php echo $user_base_url; ?>/dashboard/index.php" class="logo">
            <img src="<?php echo $base_asset_url; ?>/images/logo-placeholder-user.png" alt="لوگو کاربر" style="width: 35px; height: 35px; margin-left: 10px; border-radius: 50%;">
            <span class="logo-text">پنل کاربری</span>
        </a>
        <button id="close-sidebar" class="close-sidebar-btn" aria-label="بستن منو">
             <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
    </div>
    <nav class="sidebar-nav">
        <ul>
            <li class="<?php echo is_active_user_menu('dashboard', 'index.php') ? 'active' : ''; ?>">
                <a href="<?php echo $user_base_url; ?>/dashboard/index.php">
                    <svg class="menu-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                    <span>داشبورد</span>
                </a>
            </li>
            <li class="<?php echo is_active_user_menu('profile') ? 'active' : ''; ?>">
                <a href="<?php echo $user_base_url; ?>/profile/index.php">
                     <svg class="menu-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    <span>پروفایل من</span>
                </a>
            </li>
            <li class="<?php echo is_active_user_menu('forms') ? 'active' : ''; ?>">
                <a href="<?php echo $user_base_url; ?>/forms/index.php">
                    <svg class="menu-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline><line x1="10" y1="14" x2="16" y2="14"></line><line x1="10" y1="18" x2="16" y2="18"></line></svg>
                    <span>فرم‌های من</span>
                </a>
            </li>
             <li class="<?php echo is_active_user_menu('tasks') ? 'active' : ''; ?>">
                <a href="<?php echo $user_base_url; ?>/tasks/index.php">
                    <svg class="menu-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><line x1="12" y1="10" x2="12" y2="18"></line><line x1="8" y1="14" x2="16" y2="14"></line></svg>
                    <span>وظایف من</span>
                </a>
            </li>
            <li class="<?php echo is_active_user_menu('tickets') ? 'active' : ''; ?>">
                <a href="<?php echo $user_base_url; ?>/tickets/index.php">
                    <svg class="menu-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path><line x1="9" y1="10" x2="15" y2="10"></line><line x1="9" y1="14" x2="13" y2="14"></line></svg>
                    <span>تیکت‌های پشتیبانی</span>
                </a>
            </li>
            <li class="<?php echo is_active_user_menu('notifications') ? 'active' : ''; ?>">
                <a href="<?php echo $user_base_url; ?>/notifications/index.php">
                    <svg class="menu-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                    <span>اعلانات</span>
                     <span class="menu-badge">3+</span> <!-- Example badge -->
                </a>
            </li>
            <!-- Add other user-specific menu items here -->
        </ul>
    </nav>
    <div class="sidebar-footer">
        <small>&copy; <?php echo to_jalali(date('Y-m-d'), 'yyyy'); ?> سامانه دبستان</small>
    </div>
</aside>
<div class="sidebar-overlay" id="sidebar-overlay"></div>
