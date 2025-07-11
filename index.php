<?php
// index.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/functions/helper_functions.php';

// اولویت با کاربر ادمین، اگر لاگین بود به داشبورد ادمین برود
if (is_admin_logged_in()) {
    header("Location: admin/dashboard/index.php");
    exit;
}
// سپس کاربر عادی
elseif (is_user_logged_in()) {
    header("Location: user/dashboard/index.php");
    exit;
}
// اگر هیچکدام لاگین نبودند، به صفحه ورود کاربران عادی هدایت شود
else {
    header("Location: user_login.php");
    exit;
}
?>
