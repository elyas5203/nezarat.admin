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
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password'];

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
    <link rel="stylesheet" href="../../assets/css/common/reset.css">
    <link rel="stylesheet" href="../../assets/css/user/login.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@100..900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: #f0f2f5; /* کمی متفاوت از ادمین برای تمایز */
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
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08); /* سایه کمی ملایم‌تر */
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        .login-container h2 {
            color: #1d3557; /* رنگ تیره‌تر برای عنوان */
            margin-bottom: 25px;
            font-weight: 600;
        }
        .login-container label {
            display: block;
            text-align: right;
            margin-bottom: 8px;
            color: #495057;
            font-weight: 500;
        }
        .login-container input[type="text"],
        .login-container input[type="password"] {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            box-sizing: border-box;
            font-family: 'Vazirmatn', sans-serif;
            font-size: 1rem;
        }
        .login-container input[type="text"]:focus,
        .login-container input[type="password"]:focus {
            border-color: #457b9d; /* رنگ فوکوس متفاوت */
            outline: none;
            box-shadow: 0 0 0 0.2rem rgba(69,123,157,.25);
        }
        .login-container button[type="submit"] {
            background-color: #457b9d; /* رنگ اصلی دکمه */
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
            background-color: #1d3557; /* رنگ هاور دکمه */
        }
        .error-message {
            color: #e63946; /* رنگ خطا */
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
         .admin-login-link {
            display: block;
            margin-top: 20px;
            font-size: 0.9rem;
            color: #007bff;
            text-decoration: none;
        }
        .admin-login-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <h2>ورود به پنل کاربری</h2>
            <?php if (!empty($error_message)): ?>
                <p class="error-message"><?php echo $error_message; ?></p>
            <?php endif; ?>
            <div>
                <label for="username">نام کاربری:</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            <div>
                <label for="password">رمز عبور:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">ورود</button>
            <a href="../../admin_login.php" class="admin-login-link">ورود به پنل ادمین</a>
        </form>
    </div>
</body>
</html>
