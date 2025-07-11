<?php
// user_login.php (در ریشه پروژه)
// نقطه ورود ساده برای کاربران عادی

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/functions/helper_functions.php';

if (is_user_logged_in()) {
    header("Location: user/dashboard/index.php"); // اگر از قبل لاگین بود به داشبورد کاربر برود
    exit;
} elseif (is_admin_logged_in()) {
    // اگر ادمین به اشتباه اینجا آمد، به داشبورد ادمین هدایت شود یا به لاگین ادمین
     header("Location: admin/dashboard/index.php");
    exit;
}
else {
    header("Location: user/auth/login.php"); // در غیر این صورت به صفحه لاگین کاربر برود
    exit;
}
?>
