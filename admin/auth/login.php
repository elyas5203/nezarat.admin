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
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password'];

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
    <link rel="stylesheet" href="../../assets/css/common/reset.css">
    <link rel="stylesheet" href="../../assets/css/admin/login.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@100..900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: #f4f7f6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .login-container {
            background-color: #fff;
            padding: 30px 40px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        .login-container h2 {
            color: #333;
            margin-bottom: 25px;
            font-weight: 600;
        }
        .login-container label {
            display: block;
            text-align: right;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        .login-container input[type="text"],
        .login-container input[type="password"] {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
            font-family: 'Vazirmatn', sans-serif;
            font-size: 1rem;
        }
        .login-container input[type="text"]:focus,
        .login-container input[type="password"]:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }
        .login-container button[type="submit"] {
            background-color: #007bff;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            width: 100%;
            transition: background-color 0.3s ease;
        }
        .login-container button[type="submit"]:hover {
            background-color: #0056b3;
        }
        .error-message {
            color: #dc3545;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <h2>ورود ادمین</h2>
            <?php if (!empty($error_message)): ?>
                <p class="error-message"><?php echo $error_message; ?></p>
            <?php endif; ?>
            <div>
                <label for="username">نام کاربری:</label>
                <input type="text" id="username" name="username" value="<?php echo $admin_username_config; ?>" required>
            </div>
            <div>
                <label for="password">رمز عبور:</label>
                <input type="password" id="password" name="password" required autofocus>
            </div>
            <button type="submit">ورود</button>
        </form>
    </div>
</body>
</html>
