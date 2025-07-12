<?php
require_once __DIR__ . '/../../../includes/config/db_config.php'; // Corrected path
require_once __DIR__ . '/../../../includes/functions/helper_functions.php'; // Corrected path

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ensure admin is logged in
if (!is_admin_logged_in()) {
    $_SESSION['user_action_error'] = 'برای انجام این عملیات باید به عنوان ادمین وارد شده باشید.';
    // Adjusted redirect path based on the new location of delete_user.php
    header("Location: " . get_base_url() . "admin/auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id_to_delete = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $submitted_csrf_token = $_POST['csrf_token'] ?? '';

    // The CSRF token for deletion is generated on the index.php page with name 'delete_user'
    if (!verify_csrf_token($submitted_csrf_token, 'delete_user')) {
        $_SESSION['user_action_error'] = 'خطای امنیتی CSRF. عملیات حذف ناموفق بود.';
        header("Location: ../index.php");
        exit;
    }
    // Regenerate the 'delete_user' token to prevent replay attacks if the user goes back and tries again
    // This might be better handled by redirecting and ensuring the form always has a fresh token.
    // For now, let's regenerate it for the session.
    regenerate_csrf_token('delete_user');


    if ($user_id_to_delete <= 0 && $user_id_to_delete !== 0) { // Allow UserID 0 if it's a special system user (though usually not deletable)
        $_SESSION['user_action_error'] = 'شناسه کاربر برای حذف نامعتبر است.';
        header("Location: ../index.php");
        exit;
    }

    // Fetch the username to prevent deleting 'admin'
    // Also ensure $conn is available
    if (!$conn) {
        $_SESSION['user_action_error'] = 'خطا در اتصال به پایگاه داده.';
        header("Location: ../index.php");
        exit;
    }

    $stmt_get_username = $conn->prepare("SELECT Username FROM Users WHERE UserID = ?");
    if (!$stmt_get_username) {
        $_SESSION['user_action_error'] = 'خطا در آماده سازی کوئری (get username): ' . $conn->error;
        header("Location: ../index.php");
        exit;
    }
    $stmt_get_username->bind_param("i", $user_id_to_delete);
    $stmt_get_username->execute();
    $result_username = $stmt_get_username->get_result();

    if ($user_to_delete_data = $result_username->fetch_assoc()) {
        if (strtolower($user_to_delete_data['Username']) === 'admin') {
            $_SESSION['user_action_error'] = 'کاربر ادمین اصلی قابل حذف نیست.';
            $stmt_get_username->close();
            header("Location: ../index.php");
            exit;
        }
    } else {
        // If UserID 0 was passed and no user 0 exists, this will trigger.
        // Or if a non-existent UserID was passed.
        $_SESSION['user_action_error'] = 'کاربر مورد نظر برای حذف یافت نشد (ID: ' . $user_id_to_delete . ').';
        $stmt_get_username->close();
        header("Location: ../index.php");
        exit;
    }
    $stmt_get_username->close();

    $conn->begin_transaction();
    try {
        // 1. Delete from UserRoles
        $stmt_delete_roles = $conn->prepare("DELETE FROM UserRoles WHERE UserID = ?");
        if (!$stmt_delete_roles) throw new Exception("خطا در آماده سازی حذف نقش‌های کاربر: " . $conn->error);
        $stmt_delete_roles->bind_param("i", $user_id_to_delete);
        if (!$stmt_delete_roles->execute()) throw new Exception("خطا در حذف نقش‌های کاربر: " . $stmt_delete_roles->error);
        $stmt_delete_roles->close();

        // 2. Consider other dependencies: Notifications, FormSubmissions, etc.
        // Example: Delete notifications for this user
        $stmt_delete_notifs = $conn->prepare("DELETE FROM Notifications WHERE UserID = ?");
        if ($stmt_delete_notifs) {
            $stmt_delete_notifs->bind_param("i", $user_id_to_delete);
            $stmt_delete_notifs->execute(); // Errors here are not critical for user deletion itself, but should be logged
            $stmt_delete_notifs->close();
        } else {
            error_log("Failed to prepare statement for deleting notifications for UserID: $user_id_to_delete. Error: " . $conn->error);
        }

        // Add more dependency cleanups here if necessary (e.g., anonymize form submissions, reassign tasks)
        // For now, we'll proceed to delete the user. If foreign keys are restrictive, this will fail.

        // 3. Delete from Users
        $stmt_delete_user = $conn->prepare("DELETE FROM Users WHERE UserID = ?");
        if (!$stmt_delete_user) throw new Exception("خطا در آماده سازی حذف کاربر: " . $conn->error);
        $stmt_delete_user->bind_param("i", $user_id_to_delete);

        if ($stmt_delete_user->execute()) {
            if ($stmt_delete_user->affected_rows > 0) {
                $conn->commit();
                $_SESSION['user_action_success'] = 'کاربر با شناسه ' . $user_id_to_delete . ' با موفقیت حذف شد.';
            } else {
                $conn->rollback(); // Rollback if user was not actually deleted (e.g., already gone)
                $_SESSION['user_action_error'] = 'کاربر مورد نظر برای حذف یافت نشد یا قبلاً حذف شده است (affected_rows = 0).';
            }
        } else {
            throw new Exception("خطا در اجرای حذف کاربر: " . $stmt_delete_user->error);
        }
        $stmt_delete_user->close();

    } catch (Exception $e) {
        $conn->rollback();
        if ($conn->errno == 1451) {
             $_SESSION['user_action_error'] = 'امکان حذف این کاربر وجود ندارد زیرا اطلاعات وابسته (مانند فرم‌های ثبت‌شده یا وظایف) در سیستم موجود است. ابتدا اطلاعات وابسته را حذف یا ویرایش کنید.';
        } else {
            $_SESSION['user_action_error'] = 'خطای پایگاه داده هنگام حذف کاربر: ' . $e->getMessage();
        }
    }

} else {
    $_SESSION['user_action_error'] = 'درخواست نامعتبر برای حذف کاربر (متد غیر POST).';
}

header("Location: ../index.php");
exit;
?>
