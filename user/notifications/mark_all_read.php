<?php
require_once __DIR__ . '/../../includes/config/db_config.php';
require_once __DIR__ . '/../../includes/functions/helper_functions.php'; // For session_start, get_current_user_id, verify_csrf_token, etc.

if (session_status() == PHP_SESSION_NONE) { session_start(); }

$current_user_id_mark = get_current_user_id();
// $user_base_url should be available from header.php context if it was included,
// but since this is a direct action file, it might not be. Define it or use a relative path.
$user_panel_redirect_path = ($user_base_url ?? '/my_site/user'); // Fallback if $user_base_url is not set globally

if (!$current_user_id_mark || !is_user_logged_in()) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'دسترسی نامعتبر. لطفاً ابتدا وارد شوید.'];
    // Try to determine login path correctly
    $login_path = $user_panel_redirect_path . "/auth/login.php";
    if (defined('USER_BASE_URL_CONST')) $login_path = USER_BASE_URL_CONST . "/auth/login.php"; // If you define a constant
    header("Location: " . $login_path);
    exit;
}

if (!isset($_GET['csrf_token']) || !verify_csrf_token($_GET['csrf_token'], 'mark_all_read_user')) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطای CSRF! عملیات نامعتبر یا توکن منقضی شده.'];
    header("Location: " . $user_panel_redirect_path . "/notifications/index.php");
    exit;
}
// Regenerate the token for the next time the link is used on the notifications page
regenerate_csrf_token('mark_all_read_user');


$stmt_mark_all_user = $conn->prepare("UPDATE Notifications SET IsRead = TRUE WHERE UserID = ? AND IsRead = FALSE");
if ($stmt_mark_all_user) {
    $stmt_mark_all_user->bind_param("i", $current_user_id_mark);
    $stmt_mark_all_user->execute();
    // $affected_rows = $stmt_mark_all_user->affected_rows; // For debugging or more specific message
    $stmt_mark_all_user->close();
    $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'تمام اعلانات به عنوان خوانده شده علامت‌گذاری شدند.'];
} else {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا در علامت‌گذاری اعلانات: ' . $conn->error];
}

header("Location: " . $user_panel_redirect_path . "/notifications/index.php");
exit;
?>
