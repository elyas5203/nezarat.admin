<?php
// admin_login.php (در ریشه پروژه)
// این فایل به عنوان یک نقطه ورود ساده برای دسترسی راحت تر به صفحه لاگین ادمین عمل می کند.
// همچنین اگر کاربر مستقیما این فایل را تایپ کند، به مسیر صحیح هدایت می شود.

// اطمینان از اینکه session قبل از هرگونه header() فراخوانی شده است.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/functions/helper_functions.php';

if (is_admin_logged_in()) {
    header("Location: admin/dashboard/index.php"); // اگر از قبل لاگین بود به داشبورد برود
    exit;
} else {
    header("Location: admin/auth/login.php"); // در غیر این صورت به صفحه لاگین ادمین برود
    exit;
}
?>
