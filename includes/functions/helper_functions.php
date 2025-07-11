<?php
// includes/functions/helper_functions.php

// تابع برای پاکسازی ورودی‌ها
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// بررسی اینکه آیا کاربر ادمین لاگین کرده است
function is_admin_logged_in() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['admin_user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}

// بررسی اینکه آیا کاربر عادی لاگین کرده است
function is_user_logged_in() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] !== 'admin';
}

// تابع برای بررسی سطح دسترسی کاربر (نیازمند تکمیل بر اساس سیستم Roles و Permissions)
function has_permission($permission_name) {
    // این تابع باید کامل شود
    if (is_admin_logged_in()) return true; // ادمین به همه چیز دسترسی دارد

    if (is_user_logged_in()) {
        global $conn; // اطمینان از دسترسی به $conn
        if (!$conn) {
            // اگر $conn در دسترس نیست، سعی کنید دوباره آن را مقداردهی کنید یا خطا برگردانید
            // این حالت نباید رخ دهد اگر db_config.php به درستی include شده باشد
            // require_once __DIR__ . '/../config/db_config.php'; // ممکن است نیاز باشد مسیر را تنظیم کنید
            error_log("Database connection not available in has_permission function.");
            return false;
        }
        $user_id = $_SESSION['user_id'];
        $sql = "SELECT p.PermissionName
                FROM UserRoles ur
                JOIN RolePermissions rp ON ur.RoleID = rp.RoleID
                JOIN Permissions p ON rp.PermissionID = p.PermissionID
                WHERE ur.UserID = ? AND p.PermissionName = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed in has_permission: (" . $conn->errno . ") " . $conn->error);
            return false;
        }
        $stmt->bind_param("is", $user_id, $permission_name);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        return $result->num_rows > 0;
    }
    return false; // به صورت پیش‌فرض دسترسی ندارد
}

// تابع برای نمایش تاریخ شمسی
function to_jalali($gregorian_date_str, $format = 'yyyy/MM/dd HH:mm:ss') { // فرمت Intl
    if (empty($gregorian_date_str) || $gregorian_date_str == '0000-00-00 00:00:00' || $gregorian_date_str == '0000-00-00') {
        return '';
    }

    if (class_exists('IntlDateFormatter')) {
        $timestamp = strtotime($gregorian_date_str);
        if ($timestamp === false) return $gregorian_date_str;

        $jalali_formatter = new IntlDateFormatter(
            'fa_IR@calendar=persian',
            IntlDateFormatter::FULL, // Date type
            IntlDateFormatter::FULL, // Time type
            'Asia/Tehran',
            IntlDateFormatter::TRADITIONAL, // Calendar type
            $format
        );
        if (!$jalali_formatter) {
            // error_log("IntlDateFormatter creation failed: " . intl_get_error_message());
            return date('Y/m/d H:i:s', $timestamp) . " (خطا در تبدیل به شمسی)";
        }
        $formatted_date = $jalali_formatter->format($timestamp);
        if ($formatted_date === false) {
            // error_log("IntlDateFormatter format failed: " . intl_get_error_message());
             return date('Y/m/d H:i:s', $timestamp) . " (خطا در فرمت شمسی)";
        }
        return $formatted_date;
    }
    return date('Y/m/d H:i:s', strtotime($gregorian_date_str)) . " (میلادی - intl غیرفعال)";
}

// تابع برای نمایش ساعت به صورت زنده (نیاز به جاوااسکریپت سمت کاربر)
function display_live_time_script() {
    echo "<span id='live-time'></span>
    <script>
        function updateLiveTime() {
            const now = new Date();
            const optionsTime = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false, timeZone: 'Asia/Tehran', numberingSystem: 'latn' };
            let timeString;
            try {
                // از fa-IR-u-nu-arab برای اعداد عربی-فارسی استفاده می‌کنیم اگر fa-IR اعداد فارسی را به درستی نمایش ندهد
                timeString = new Intl.DateTimeFormat('fa-IR-u-nu-arab', optionsTime).format(now);
            } catch (e) {
                timeString = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false, timeZone: 'Asia/Tehran' });
                const persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
                timeString = timeString.replace(/[0-9]/g, function (w) {
                    return persianNumbers[+w];
                });
            }
            if (document.getElementById('live-time')) {
                document.getElementById('live-time').innerText = timeString;
            }
        }
        setInterval(updateLiveTime, 1000);
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', updateLiveTime);
        } else {
            updateLiveTime(); // Initial call if DOM is already loaded
        }
    </script>";
}

function display_current_jalali_date_script($format = 'yyyy/MM/dd') { // فرمت Intl
    echo "<span id='current-date'>";
    if (class_exists('IntlDateFormatter')) {
        $jalali_formatter = new IntlDateFormatter(
            'fa_IR@calendar=persian',
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE,
            'Asia/Tehran',
            IntlDateFormatter::TRADITIONAL,
            $format
        );
        if ($jalali_formatter) {
            echo $jalali_formatter->format(time());
        } else {
            echo date('Y/m/d') . " (خطا در نمایش تاریخ شمسی)";
        }
    } else {
        echo date('Y/m/d') . " (میلادی - intl غیرفعال)";
    }
    echo "</span>";
    // JS برای آپدیت تاریخ در نیمه شب می‌تواند پیچیده باشد و نیاز به مدیریت دقیق timezone دارد.
    // ساده‌ترین راه، رفرش صفحه یا درخواست ایجکس برای دریافت تاریخ جدید از سرور است.
    // فعلا فقط نمایش اولیه با PHP انجام می‌شود.
}

function get_current_user_id() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (is_admin_logged_in()) {
        return $_SESSION['admin_user_id'];
    } elseif (is_user_logged_in()) {
        return $_SESSION['user_id'];
    }
    return null;
}

function get_current_user_type() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
     if (is_admin_logged_in()) {
        return 'admin';
    } elseif (is_user_logged_in()) {
        return $_SESSION['user_type'];
    }
    return null;
}

?>
