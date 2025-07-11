<?php
// includes/functions/helper_functions.php

if (session_status() == PHP_SESSION_NONE) { // Ensure session is started before using $_SESSION
    session_start();
}

// تابع برای پاکسازی ورودی‌ها
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8'); // Specify ENT_QUOTES and UTF-8
    return $data;
}

// بررسی اینکه آیا کاربر ادمین لاگین کرده است
function is_admin_logged_in() {
    return isset($_SESSION['admin_user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}

// بررسی اینکه آیا کاربر عادی لاگین کرده است
function is_user_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] !== 'admin';
}

// تابع برای بررسی سطح دسترسی کاربر
function has_permission($permission_name) {
    if (is_admin_logged_in()) {
        // Assuming 'admin' user_type is a super admin with all permissions.
        // For more granular admin roles, this would need to check their specific role permissions too.
        return true;
    }

    if (is_user_logged_in()) {
        global $conn;
        if (!$conn) {
            // error_log("Database connection unavailable in has_permission(). Trying to reconnect...");
            // require_once __DIR__ . '/../config/db_config.php'; // Adjust path as needed
            // if (!$conn) { // Check again
            //      error_log("Database reconnection failed in has_permission().");
            //      return false;
            // }
             error_log("Database connection unavailable in has_permission().");
             return false; // Fail safe if connection is truly gone
        }
        $user_id = $_SESSION['user_id'];
        $sql = "SELECT 1
                FROM UserRoles ur
                JOIN RolePermissions rp ON ur.RoleID = rp.RoleID
                JOIN Permissions p ON rp.PermissionID = p.PermissionID
                WHERE ur.UserID = ? AND p.PermissionName = ?
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed in has_permission() for permission '$permission_name': (" . $conn->errno . ") " . $conn->error);
            return false;
        }
        $stmt->bind_param("is", $user_id, $permission_name);
        $stmt->execute();
        $result = $stmt->get_result();
        $has_perm = $result->num_rows > 0;
        $stmt->close();
        return $has_perm;
    }
    return false;
}

// تابع برای نمایش تاریخ شمسی
function to_jalali($gregorian_date_str, $format = 'yyyy/MM/dd HH:mm:ss') {
    if (empty($gregorian_date_str) || $gregorian_date_str == '0000-00-00 00:00:00' || $gregorian_date_str == '0000-00-00') {
        return '';
    }

    if (class_exists('IntlDateFormatter')) {
        $timestamp = strtotime($gregorian_date_str);
        if ($timestamp === false) return $gregorian_date_str;

        try {
            $jalali_formatter = new IntlDateFormatter(
                'fa_IR@calendar=persian;numbers=arab', // Request Arabic-Indic numerals
                IntlDateFormatter::FULL,
                IntlDateFormatter::FULL,
                'Asia/Tehran',
                IntlDateFormatter::TRADITIONAL,
                $format
            );
            // Check for errors after creation
            if (intl_is_failure($jalali_formatter->getErrorCode())) {
                // error_log("IntlDateFormatter creation failed: " . intl_error_name($jalali_formatter->getErrorCode()));
                return date('Y/m/d H:i:s', $timestamp) . " (خطا ۱)";
            }
            $formatted_date = $jalali_formatter->format($timestamp);
            if ($formatted_date === false) {
                // error_log("IntlDateFormatter format failed: " . $jalali_formatter->getErrorMessage());
                 return date('Y/m/d H:i:s', $timestamp) . " (خطا ۲)";
            }
            return $formatted_date;
        } catch (Exception $e) {
            // error_log("Exception during date formatting: " . $e->getMessage());
            return date('Y/m/d H:i:s', $timestamp) . " (استثنا)";
        }
    }
    return date('Y/m/d H:i:s', strtotime($gregorian_date_str)) . " (میلادی)";
}


function display_live_time_script() {
    echo "<span id='live-time-placeholder'></span>"; // JS in footer will populate this
}

function display_current_jalali_date_script($format = 'yyyy/MM/dd') {
    echo "<span id='current-date-placeholder'></span>"; // JS in footer will populate this
}


function get_current_user_id() {
    if (is_admin_logged_in()) {
        return $_SESSION['admin_user_id'];
    } elseif (is_user_logged_in()) {
        return $_SESSION['user_id'];
    }
    return null;
}

function get_current_user_type() {
     if (is_admin_logged_in()) {
        return 'admin';
    } elseif (is_user_logged_in()) {
        return $_SESSION['user_type'];
    }
    return null;
}


// CSRF Token Functions
if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token($form_name = 'default_form') {
        if (empty($_SESSION['csrf_tokens'][$form_name])) {
            $_SESSION['csrf_tokens'][$form_name] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_tokens'][$form_name];
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token($submitted_token, $form_name = 'default_form') {
        if (isset($_SESSION['csrf_tokens'][$form_name]) && hash_equals($_SESSION['csrf_tokens'][$form_name], $submitted_token)) {
            // Token is valid. Consider invalidating it after use for critical actions.
            // For general forms, it might be okay to keep it for the session duration or until regenerated.
            // unset($_SESSION['csrf_tokens'][$form_name]); // Example: Invalidate after use
            return true;
        }
        // error_log("CSRF token verification failed for form: $form_name. Submitted: $submitted_token, Session: " . ($_SESSION['csrf_tokens'][$form_name] ?? 'NOT SET'));
        return false;
    }
}

if (!function_exists('regenerate_csrf_token')) {
    function regenerate_csrf_token($form_name = 'default_form') {
        $_SESSION['csrf_tokens'][$form_name] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_tokens'][$form_name];
    }
}
?>
