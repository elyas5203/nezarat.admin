<?php
require_once __DIR__ . '/../../includes/config/db_config.php';
require_once __DIR__ . '/../../includes/functions/helper_functions.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }

$current_admin_id_mark = get_current_user_id();
$admin_panel_redirect_path = ($admin_base_url ?? '/my_site/admin'); // Fallback if $admin_base_url is not set globally

if (!$current_admin_id_mark || !is_admin_logged_in()) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'دسترسی نامعتبر. لطفاً ابتدا به عنوان ادمین وارد شوید.'];
    $login_path_admin = $admin_panel_redirect_path . "/auth/login.php";
    if (defined('ADMIN_BASE_URL_CONST')) $login_path_admin = ADMIN_BASE_URL_CONST . "/auth/login.php";
    header("Location: " . $login_path_admin);
    exit;
}

if (!isset($_GET['csrf_token']) || !verify_csrf_token($_GET['csrf_token'], 'mark_all_read_admin')) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطای CSRF! عملیات نامعتبر یا توکن منقضی شده.'];
    header("Location: " . $admin_panel_redirect_path . "/notifications/index.php");
    exit;
}
regenerate_csrf_token('mark_all_read_admin');

$stmt_mark_all_admin = $conn->prepare("UPDATE Notifications SET IsRead = TRUE WHERE UserID = ? AND IsRead = FALSE");
if ($stmt_mark_all_admin) {
    $stmt_mark_all_admin->bind_param("i", $current_admin_id_mark);
    $stmt_mark_all_admin->execute();
    $stmt_mark_all_admin->close();
    $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'تمام اعلانات ادمین به عنوان خوانده شده علامت‌گذاری شدند.'];
} else {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا در علامت‌گذاری اعلانات ادمین: ' . $conn->error];
}

header("Location: " . $admin_panel_redirect_path . "/notifications/index.php");
exit;
?>
