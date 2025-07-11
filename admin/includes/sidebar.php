<?php
// admin/includes/sidebar.php
if (empty($admin_base_url)) {
    $admin_base_url = '/my_site/admin'; // Fallback
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

// Updated is_active_menu to better handle parent and child states
function is_active_menu($target_dir, $target_page = null) {
    global $current_dir, $current_page;
    if ($current_dir === $target_dir) {
        if ($target_page === null) { // Parent item, active if in the directory
            return true;
        }
        return $current_page === $target_page; // Child item, active if page matches
    }
    return false;
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="<?php echo $admin_base_url; ?>/dashboard/index.php" class="logo">
            <img src="<?php echo $base_asset_url; ?>/images/logo-placeholder.png" alt="لوگو" style="width: 35px; height: 35px; margin-left: 10px; border-radius: 50%;">
            <span class="logo-text">سایت دبستان</span>
        </a>
        <button id="close-sidebar" class="close-sidebar-btn" aria-label="بستن منو">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
    </div>
    <nav class="sidebar-nav">
        <ul>
            <li class="<?php echo is_active_menu('dashboard', 'index.php') ? 'active' : ''; ?>">
                <a href="<?php echo $admin_base_url; ?>/dashboard/index.php">
                    <svg class="menu-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                    <span>داشبورد</span>
                </a>
            </li>
            <li class="<?php echo is_active_menu('users') ? 'active' : ''; ?>">
                <a href="<?php echo $admin_base_url; ?>/users/index.php">
                     <svg class="menu-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    <span>مدیریت کاربران</span>
                </a>
            </li>
            <li class="<?php echo is_active_menu('departments') ? 'active' : ''; ?>">
                <a href="<?php echo $admin_base_url; ?>/departments/index.php">
                    <svg class="menu-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"></path><path d="M2 17l10 5 10-5"></path><path d="M2 12l10 5 10-5"></path></svg>
                    <span>مدیریت بخش‌ها</span>
                </a>
            </li>
             <li class="<?php echo is_active_menu('forms') ? 'active' : ''; ?>">
                <a href="<?php echo $admin_base_url; ?>/forms/index.php">
                    <svg class="menu-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    <span>مدیریت فرم‌ها</span>
                </a>
            </li>
            <li class="<?php echo is_active_menu('tasks') ? 'active' : ''; ?>">
                <a href="<?php echo $admin_base_url; ?>/tasks/index.php">
                     <svg class="menu-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15.5 2H8.6c-.4 0-.8.2-1.1.5-.3.3-.5.7-.5 1.1V21c0 .4.2.8.5 1.1.3.3.7.5 1.1.5h10.8c.4 0 .8-.2 1.1-.5.3-.3.5-.7.5-1.1V8.9L15.5 2z"></path><path d="M15 2v5h5"></path><path d="M8 16h8"></path><path d="M8 12h8"></path><path d="M8 8h4"></path></svg>
                    <span>مدیریت وظایف</span>
                </a>
            </li>
            <li class="has-submenu <?php echo is_active_menu('settings') ? 'active open' : ''; ?>">
                <a href="#"> <!-- The main link for parent doesn't need to go anywhere if it's just a toggle -->
                    <svg class="menu-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06-.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                    <span>تنظیمات</span>
                    <svg class="submenu-arrow" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </a>
                <ul class="submenu" <?php echo is_active_menu('settings') ? 'style="display:block;"' : ''; ?>>
                    <li class="<?php echo is_active_menu('settings', 'index.php') ? 'active' : ''; ?>">
                        <a href="<?php echo $admin_base_url; ?>/settings/index.php">تنظیمات عمومی</a>
                    </li>
                    <li class="<?php echo is_active_menu('settings', 'telegram_bot.php') ? 'active' : ''; ?>">
                        <a href="<?php echo $admin_base_url; ?>/settings/telegram_bot.php">تنظیمات ربات تلگرام</a>
                    </li>
                    <!-- Add other settings sub-menu items here -->
                </ul>
            </li>
            <!-- Placeholder for future menu items -->
            <!--
            <li class="menu-separator"><hr></li>
            <li>
                <a href="#">
                    <svg class="menu-icon" ...></svg>
                    <span>گزارشات</span>
                </a>
            </li>
            -->
        </ul>
    </nav>
    <div class="sidebar-footer">
        <small>&copy; <?php echo to_jalali(date('Y-m-d'), 'yyyy'); ?> سامانه دبستان</small>
    </div>
</aside>
<div class="sidebar-overlay" id="sidebar-overlay"></div>
<script>
// Basic submenu toggle
document.addEventListener('DOMContentLoaded', function () {
    const submenuParents = document.querySelectorAll('.sidebar-nav .has-submenu > a');
    submenuParents.forEach(function (parent) {
        parent.addEventListener('click', function (e) {
            // Allow link to work if it's not just '#'
            if (this.getAttribute('href') === '#') {
                 e.preventDefault();
            }
            const submenu = this.nextElementSibling;
            if (submenu && submenu.classList.contains('submenu')) {
                // Toggle 'open' class on parent <li> for styling the arrow or parent item
                this.parentElement.classList.toggle('open');
                // Toggle display of submenu
                if (submenu.style.display === 'block') {
                    submenu.style.display = 'none';
                } else {
                    submenu.style.display = 'block';
                }
            }
        });
    });
});
</script>
