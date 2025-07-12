<?php
// admin/auth/login.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../includes/config/db_config.php';
require_once __DIR__ . '/../../../includes/functions/helper_functions.php';

// اگر کاربر لاگین کرده بود، به داشبورد ادمین هدایت شود
if (is_admin_logged_in()) {
    header("Location: ../dashboard/index.php");
    exit;
}

$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'], 'admin_login')) {
        $error_message = "خطای امنیتی CSRF. لطفاً صفحه را رفرش کرده و مجدداً تلاش کنید.";
    } else {
        $username = sanitize_input($_POST['username']);
        $password = $_POST['password'];

        // Regenerate CSRF token after successful verification to prevent reuse for this form instance
        regenerate_csrf_token('admin_login');

        $admin_username_config = "admin";
    $admin_password_plain_config = "Admin_dabestan_site_110_59";

    if ($username === $admin_username_config) {
        $stmt_check_admin = $conn->prepare("SELECT UserID, Password, UserType FROM Users WHERE Username = ?");
        if (!$stmt_check_admin) {
            $error_message = "خطا در آماده سازی کوئری: " . $conn->error;
        } else {
            $stmt_check_admin->bind_param("s", $admin_username_config);
            $stmt_check_admin->execute();
            $result_admin = $stmt_check_admin->get_result();

            if ($result_admin->num_rows == 0) {
                // ادمین در دیتابیس وجود ندارد، ایجاد می‌کنیم
                if ($password === $admin_password_plain_config) { // فقط اگر پسورد ورودی با پسورد کانفیگ یکی بود ایجاد کن
                    $hashed_password = password_hash($admin_password_plain_config, PASSWORD_DEFAULT);
                    $stmt_insert_admin = $conn->prepare("INSERT INTO Users (Username, Password, FirstName, LastName, UserType, IsActive) VALUES (?, ?, 'ادمین', 'اصلی', 'admin', TRUE)");
                    if (!$stmt_insert_admin) {
                        $error_message = "خطا در آماده سازی کوئری ایجاد ادمین: " . $conn->error;
                    } else {
                        $stmt_insert_admin->bind_param("ss", $admin_username_config, $hashed_password);
                        if ($stmt_insert_admin->execute()) {
                            $_SESSION['admin_user_id'] = $stmt_insert_admin->insert_id;
                            $_SESSION['admin_username'] = $admin_username_config;
                            $_SESSION['user_type'] = 'admin';
                            header("Location: ../dashboard/index.php");
                            exit;
                        } else {
                            $error_message = "خطا در ایجاد کاربر ادمین: " . $stmt_insert_admin->error;
                        }
                        $stmt_insert_admin->close();
                    }
                } else {
                    $error_message = "نام کاربری یا رمز عبور ادمین نامعتبر است.";
                }
            } else {
                // ادمین وجود دارد، رمز را بررسی می‌کنیم
                $admin_data = $result_admin->fetch_assoc();
                if ($admin_data['UserType'] !== 'admin') {
                    $error_message = "این کاربر ادمین نیست.";
                } elseif (password_verify($password, $admin_data['Password'])) {
                    $_SESSION['admin_user_id'] = $admin_data['UserID'];
                    $_SESSION['admin_username'] = $admin_username_config;
                    $_SESSION['user_type'] = 'admin';
                    // آپدیت آخرین لاگین
                    $update_login_stmt = $conn->prepare("UPDATE Users SET LastLogin = CURRENT_TIMESTAMP WHERE UserID = ?");
                    if($update_login_stmt) {
                        $update_login_stmt->bind_param("i", $admin_data['UserID']);
                        $update_login_stmt->execute();
                        $update_login_stmt->close();
                    }
                    header("Location: ../dashboard/index.php");
                    exit;
                } elseif ($password === $admin_password_plain_config) {
                    // اگر رمز عبور در دیتابیس با رمز عبور جدید مطابقت نداشت (مثلا اگر دستی تغییر کرده یا هش قدیمی است)
                    // و رمز وارد شده با رمز کانفیگ یکی بود، هش را آپدیت کن
                    $new_hashed_password = password_hash($admin_password_plain_config, PASSWORD_DEFAULT);
                    $stmt_update_pass = $conn->prepare("UPDATE Users SET Password = ? WHERE UserID = ? AND UserType = 'admin'");
                    if ($stmt_update_pass) {
                        $stmt_update_pass->bind_param("si", $new_hashed_password, $admin_data['UserID']);
                        if ($stmt_update_pass->execute()) {
                             $_SESSION['admin_user_id'] = $admin_data['UserID'];
                             $_SESSION['admin_username'] = $admin_username_config;
                             $_SESSION['user_type'] = 'admin';
                             header("Location: ../dashboard/index.php");
                             exit;
                        } else {
                            $error_message = "خطا در بروزرسانی رمز عبور ادمین.";
                        }
                        $stmt_update_pass->close();
                    } else {
                         $error_message = "خطا در آماده سازی بروزرسانی رمز عبور.";
                    }
                }
                 else {
                    $error_message = "نام کاربری یا رمز عبور ادمین نامعتبر است.";
                }
            }
            $stmt_check_admin->close();
        }
    } else {
        $error_message = "نام کاربری یا رمز عبور ادمین نامعتبر است.";
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود ادمین - سامانه مدیریت دبستان</title>
    <link rel="stylesheet" href="<?php echo get_base_url(); ?>assets/css/common/reset.css">
    <link rel="stylesheet" href="<?php echo get_base_url(); ?>assets/css/admin/login.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@100..900&display=swap" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token('admin_login'); ?>">
            <h2>ورود ادمین</h2>
            <?php if (!empty($error_message)): ?>
                <p class="error-message"><?php echo $error_message; ?></p>
            <?php endif; ?>
            <div class="form-group">
                <label for="username">نام کاربری:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($admin_username_config); ?>" required>
            </div>
            <div class="form-group">
                <label for="password">رمز عبور:</label>
                <input type="password" id="password" name="password" required autofocus>
            </div>
            <button type="submit">ورود</button>
        </form>
    </div>
</body>
</html>
