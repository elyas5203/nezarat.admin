<?php
// user/auth/login.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../includes/config/db_config.php';
require_once __DIR__ . '/../../../includes/functions/helper_functions.php';

if (is_user_logged_in()) {
    header("Location: ../dashboard/index.php");
    exit;
}
// اگر ادمین سعی کرد به این صفحه بیاید، به لاگین ادمین هدایت شود
if (is_admin_logged_in()){
    header("Location: ../../admin/auth/login.php");
    exit;
}


$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'], 'user_login')) {
        $error_message = "خطای امنیتی CSRF. لطفاً صفحه را رفرش کرده و مجدداً تلاش کنید.";
    } else {
        $username = sanitize_input($_POST['username']);
        $password = $_POST['password'];

        // Regenerate CSRF token after successful verification
        regenerate_csrf_token('user_login');

        if (empty($username) || empty($password)) {
        $error_message = "نام کاربری و رمز عبور نمی‌توانند خالی باشند.";
    } else {
        // جلوگیری از ورود با نام کاربری 'admin' به پنل کاربران عادی
        if (strtolower($username) === 'admin') {
            $error_message = "برای ورود به عنوان ادمین، لطفاً از صفحه ورود ادمین اقدام کنید.";
        } else {
            $stmt = $conn->prepare("SELECT UserID, Username, Password, UserType, IsActive FROM Users WHERE Username = ? AND UserType != 'admin'");
            if (!$stmt) {
                 $error_message = "خطا در آماده سازی کوئری: " . $conn->error;
            } else {
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();
                    if (!$user['IsActive']) {
                        $error_message = "حساب کاربری شما غیرفعال شده است. لطفاً با مدیر تماس بگیرید.";
                    } elseif (password_verify($password, $user['Password'])) {
                        $_SESSION['user_id'] = $user['UserID'];
                        $_SESSION['username'] = $user['Username'];
                        $_SESSION['user_type'] = $user['UserType'];

                        // آپدیت آخرین لاگین
                        $update_login_stmt = $conn->prepare("UPDATE Users SET LastLogin = CURRENT_TIMESTAMP WHERE UserID = ?");
                        if($update_login_stmt) {
                            $update_login_stmt->bind_param("i", $user['UserID']);
                            $update_login_stmt->execute();
                            $update_login_stmt->close();
                        }

                        header("Location: ../dashboard/index.php");
                        exit;
                    } else {
                        $error_message = "نام کاربری یا رمز عبور نامعتبر است.";
                    }
                } else {
                    $error_message = "نام کاربری یا رمز عبور نامعتبر است.";
                }
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود کاربر - سامانه مدیریت دبستان</title>
    <link rel="stylesheet" href="<?php echo get_base_url(); ?>assets/css/common/reset.css">
    <link rel="stylesheet" href="<?php echo get_base_url(); ?>assets/css/user/login.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@100..900&display=swap" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token('user_login'); ?>">
            <h2>ورود به پنل کاربری</h2>
            <?php if (!empty($error_message)): ?>
                <p class="error-message"><?php echo $error_message; ?></p>
            <?php endif; ?>
            <div class="form-group">
                <label for="username">نام کاربری:</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">رمز عبور:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">ورود</button>
            <a href="<?php echo get_base_url(); ?>admin_login.php" class="admin-login-link">ورود به پنل ادمین</a>
        </form>
    </div>
</body>
</html>
