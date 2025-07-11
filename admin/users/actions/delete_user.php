<?php
require_once __DIR__ . '/../../../includes/config/db_config.php'; // Adjusted path
require_once __DIR__ . '/../../../includes/functions/helper_functions.php'; // For session start and CSRF

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
if (!is_admin_logged_in()) {
    header("Location: " . '/my_site/admin/auth/login.php'); // Adjust to your login path
    exit;
}

$user_id_to_delete = null;
$error_message = '';
$success_message = '';

// CSRF Token Verification
$user_id_for_token = $_GET['user_id'] ?? 'invalid_id'; // Get user_id for token name
$csrf_token_from_link = $_GET['csrf_token'] ?? '';

if (!verify_csrf_token($csrf_token_from_link, 'delete_user_form_' . $user_id_for_token)) {
    header("Location: ../index.php?action_status=error&message=" . urlencode("خطای CSRF! درخواست حذف نامعتبر یا توکن منقضی شده."));
    exit;
}
// Regenerate token for this specific action to prevent reuse on replay, though redirect happens immediately
regenerate_csrf_token('delete_user_form_' . $user_id_for_token);


if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $user_id_to_delete = (int)$_GET['user_id'];

    if ($user_id_to_delete === ($_SESSION['admin_user_id'] ?? null)) {
        $error_message = "امکان حذف حساب کاربری ادمین اصلی که با آن وارد شده‌اید، وجود ندارد.";
    } else {
        $stmt_check = $conn->prepare("SELECT Username FROM Users WHERE UserID = ?");
        if ($stmt_check) {
            $stmt_check->bind_param("i", $user_id_to_delete);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();

            if ($result_check->num_rows === 1) {
                $conn->begin_transaction();
                try {
                    // Delete from UserRoles
                    $stmt_delete_roles = $conn->prepare("DELETE FROM UserRoles WHERE UserID = ?");
                    if (!$stmt_delete_roles) throw new Exception("خطا آماده سازی حذف نقش‌ها: " . $conn->error);
                    $stmt_delete_roles->bind_param("i", $user_id_to_delete);
                    if (!$stmt_delete_roles->execute()) throw new Exception("خطا حذف نقش‌ها: " . $stmt_delete_roles->error);
                    $stmt_delete_roles->close();

                    // Add deletions or updates for other related tables here if foreign keys don't handle it
                    // Example: Set AssignedToUserID to NULL in Tasks
                    // $stmt_update_tasks = $conn->prepare("UPDATE Tasks SET AssignedToUserID = NULL WHERE AssignedToUserID = ?");
                    // if ($stmt_update_tasks) {
                    //    $stmt_update_tasks->bind_param("i", $user_id_to_delete);
                    //    $stmt_update_tasks->execute();
                    //    $stmt_update_tasks->close();
                    // } else { throw new Exception("خطا در به‌روزرسانی وظایف کاربر.");}
                    // Similar for FormSubmissions, Files etc. or ensure DB constraints (ON DELETE SET NULL / CASCADE)

                    // Delete from Users table
                    $stmt_delete_user = $conn->prepare("DELETE FROM Users WHERE UserID = ?");
                    if (!$stmt_delete_user) throw new Exception("خطا آماده سازی حذف کاربر: " . $conn->error);
                    $stmt_delete_user->bind_param("i", $user_id_to_delete);

                    if ($stmt_delete_user->execute()) {
                        if ($stmt_delete_user->affected_rows > 0) {
                            $conn->commit();
                            $success_message = "کاربر با موفقیت حذف شد.";
                        } else {
                            throw new Exception("کاربر برای حذف یافت نشد (پس از بررسی اولیه).");
                        }
                    } else {
                        throw new Exception("خطا در حذف کاربر: " . $stmt_delete_user->error);
                    }
                    $stmt_delete_user->close();

                } catch (Exception $e) {
                    $conn->rollback();
                    $error_message = "خطای پایگاه داده: " . $e->getMessage();
                     // Log detailed error: error_log($e->getMessage());
                }
            } else {
                $error_message = "کاربری با این شناسه یافت نشد.";
            }
            $stmt_check->close();
        } else {
            $error_message = "خطا در بررسی وجود کاربر: " . $conn->error;
        }
    }
} else {
    $error_message = "شناسه کاربر برای حذف نامعتبر یا ارسال نشده است.";
}

$redirect_url = "../index.php?"; // Go back to the users list
if (!empty($success_message)) {
    $redirect_url .= "action_status=success_delete&message=" . urlencode($success_message);
} else {
    $redirect_url .= "action_status=error&message=" . urlencode($error_message ?: "خطای نامشخص در عملیات حذف.");
}

header("Location: " . $redirect_url);
exit;
?>
