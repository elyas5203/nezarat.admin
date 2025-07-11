<?php
// includes/functions/helper_functions.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function sanitize_input($data) {
    if (is_array($data)) { return array_map('sanitize_input', $data); }
    $data = trim($data); $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function is_admin_logged_in() {
    return isset($_SESSION['admin_user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}

function is_user_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] !== 'admin';
}

function has_permission($permission_name) {
    if (is_admin_logged_in()) { return true; }
    if (is_user_logged_in()) {
        global $conn;
        if (!$conn) { error_log("DB connection unavailable in has_permission()."); return false; }
        $user_id = $_SESSION['user_id'];
        $sql = "SELECT 1 FROM UserRoles ur JOIN RolePermissions rp ON ur.RoleID = rp.RoleID JOIN Permissions p ON rp.PermissionID = p.PermissionID WHERE ur.UserID = ? AND p.PermissionName = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) { error_log("Prepare failed in has_permission(): " . $conn->error); return false; }
        $stmt->bind_param("is", $user_id, $permission_name); $stmt->execute();
        $result = $stmt->get_result(); $has_perm = $result->num_rows > 0;
        $stmt->close(); return $has_perm;
    }
    return false;
}

function to_jalali($gregorian_date_str, $format = 'yyyy/MM/dd HH:mm:ss') {
    if (empty($gregorian_date_str) || $gregorian_date_str == '0000-00-00 00:00:00' || $gregorian_date_str == '0000-00-00') return '';
    if (class_exists('IntlDateFormatter')) {
        $timestamp = strtotime($gregorian_date_str); if ($timestamp === false) return $gregorian_date_str;
        try {
            // Request Arabic-Indic numerals by adding ;numbers=arab to the locale
            $jalali_formatter = new IntlDateFormatter('fa_IR@calendar=persian;numbers=arab', IntlDateFormatter::FULL, IntlDateFormatter::FULL, 'Asia/Tehran', IntlDateFormatter::TRADITIONAL, $format);
            if (intl_is_failure($jalali_formatter->getErrorCode())) {
                error_log("IntlDateFormatter creation failed: " . intl_error_name($jalali_formatter->getErrorCode()));
                return date('Y/m/d H:i:s', $timestamp) . " (Intl Err)";
            }
            $formatted_date = $jalali_formatter->format($timestamp);
            if ($formatted_date === false) {
                error_log("IntlDateFormatter format failed: " . $jalali_formatter->getErrorMessage());
                 return date('Y/m/d H:i:s', $timestamp) . " (Format Err)";
            }
            return $formatted_date;
        } catch (Exception $e) {
            error_log("Exception during date formatting: " . $e->getMessage());
            return date('Y/m/d H:i:s', $timestamp) . " (Excp)";
        }
    }
    return date('Y/m/d H:i:s', strtotime($gregorian_date_str)) . " (Intl N/A)";
}

function to_gregorian_date_for_db($jalali_date_str) {
    if (empty($jalali_date_str)) return null;

    // Expected format: YYYY/MM/DD or YYYY-MM-DD (Persian)
    $jalali_date_str = str_replace('-', '/', $jalali_date_str);
    list($j_year, $j_month, $j_day) = array_map('intval', explode('/', $jalali_date_str));

    if (class_exists('IntlDateFormatter') && class_exists('IntlCalendar')) {
        try {
            // Create an IntlCalendar instance for Persian calendar
            $persian_cal = IntlCalendar::createInstance("Asia/Tehran", "fa_IR@calendar=persian");
            $persian_cal->set($j_year, $j_month - 1, $j_day); // Month is 0-indexed

            // Convert to Gregorian timestamp
            $timestamp = $persian_cal->toDateTime()->getTimestamp();

            // Format to YYYY-MM-DD
            return date('Y-m-d', $timestamp);

        } catch (Exception $e) {
            error_log("Exception during Jalali to Gregorian conversion: " . $e->getMessage() . " for date: " . $jalali_date_str);
            // Fallback or error handling if Intl fails
            // Basic conversion if Intl fails (less accurate, especially without jdf.php)
            // This is a very rough approximation and should be replaced by a proper library if Intl is not available/reliable
            if (function_exists('jmktime')) { // jdf.php function
                 // return date('Y-m-d', jmktime(0,0,0, $j_month, $j_day, $j_year)); // This is not how jmktime works for conversion
            }
            return null; // Or throw an error
        }
    } else {
        // Fallback if IntlDateFormatter is not available (requires a library like jdf.php)
        // For example, if jdf.php is included:
        // if (function_exists('jalali_to_gregorian')) {
        //    list($g_year, $g_month, $g_day) = jalali_to_gregorian($j_year, $j_month, $j_day);
        //    return sprintf('%04d-%02d-%02d', $g_year, $g_month, $g_day);
        // }
        error_log("IntlDateFormatter or IntlCalendar not available for Jalali to Gregorian conversion. Date: " . $jalali_date_str);
        return null; // Or indicate error
    }
}


function display_live_time_script() { echo "<span id='live-time-placeholder'></span>"; }
function display_current_jalali_date_script($format = 'yyyy/MM/dd') { echo "<span id='current-date-placeholder'></span>"; }

function get_current_user_id() {
    if (is_admin_logged_in()) return $_SESSION['admin_user_id'] ?? null; // Ensure admin_user_id is set at login
    elseif (is_user_logged_in()) return $_SESSION['user_id'] ?? null;
    return null;
}
function get_current_user_type() {
    if (is_admin_logged_in()) return 'admin';
    elseif (is_user_logged_in()) return $_SESSION['user_type'] ?? null;
    return null;
}

// CSRF Token Functions
if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token($form_name = 'default_form') {
        if (empty($_SESSION['csrf_tokens'][$form_name])) { $_SESSION['csrf_tokens'][$form_name] = bin2hex(random_bytes(32)); }
        return $_SESSION['csrf_tokens'][$form_name];
    }
}
if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token($submitted_token, $form_name = 'default_form') {
        if (isset($_SESSION['csrf_tokens'][$form_name]) && hash_equals($_SESSION['csrf_tokens'][$form_name], $submitted_token)) {
            return true;
        }
        return false;
    }
}
if (!function_exists('regenerate_csrf_token')) {
    function regenerate_csrf_token($form_name = 'default_form') {
        $_SESSION['csrf_tokens'][$form_name] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_tokens'][$form_name];
    }
}

// --- START Telegram Bot Config ---
// IMPORTANT: For production, move these to a secure, non-versioned config file (e.g., .env or config_local.php)
// and ensure that file is included here or its values are loaded into constants/globals.
// DO NOT COMMIT ACTUAL TOKENS TO A PUBLIC REPOSITORY.
if (!defined('TELEGRAM_BOT_TOKEN')) {
    // Replace 'YOUR_ACTUAL_BOT_TOKEN_HERE' with the real token from BotFather
    // For security, it's best to load this from an environment variable or a config file outside version control.
    // Example for loading from a config file (create config_telegram.php in includes/config/):
    // if (file_exists(__DIR__ . '/../config/config_telegram.php')) {
    //     require_once __DIR__ . '/../config/config_telegram.php';
    // } else {
    // define('TELEGRAM_BOT_TOKEN', 'YOUR_PLACEHOLDER_TOKEN_IF_CONFIG_MISSING');
    //     error_log("Telegram config file not found. Using placeholder token.");
    // }
    // IMPORTANT: For a real production environment, the token MUST be stored securely outside of version control,
    // e.g., in an environment variable or a local config file not committed to the repository.
    define('TELEGRAM_BOT_TOKEN', '7726563483:AAF8TeGuly0SgloqO6CGVfUj5cBNyMXC8sk'); // Dabestan Site Bot Token
}
if (!defined('TELEGRAM_BOT_USERNAME')) {
    define('TELEGRAM_BOT_USERNAME', '@Dabestan_Site_Bot'); // Dabestan Site Bot Username
}

// --- START Telegram Test/Default Config (SHOULD BE REMOVED/ADJUSTED FOR PRODUCTION) ---
if (!defined('TELEGRAM_NOTIFICATIONS_ENABLED_DEFAULT')) {
    // Default state for Telegram notifications if no DB setting is found or DB is unavailable.
    // Set to 'true' to enable by default for testing, 'false' to disable.
    define('TELEGRAM_NOTIFICATIONS_ENABLED_DEFAULT', true);
}
if (!defined('TELEGRAM_ADMIN_CHAT_IDS_DEFAULT')) {
    // IMPORTANT: Replace 'YOUR_MAIN_ADMIN_CHAT_ID' with actual admin chat ID(s),
    // comma-separated for multiple IDs. This is a fallback or for systems without AppSettings.
    // Example: define('TELEGRAM_ADMIN_CHAT_IDS_DEFAULT', '12345678,87654321');
    define('TELEGRAM_ADMIN_CHAT_IDS_DEFAULT', 'YOUR_MAIN_ADMIN_CHAT_ID'); // USER: PLEASE REPLACE THIS
}
// --- END Telegram Test/Default Config ---

// --- END Telegram Bot Config ---

if (!function_exists('send_telegram_message')) {
    function send_telegram_message($chat_id, $message, $parse_mode = 'HTML') {
        if (empty(TELEGRAM_BOT_TOKEN) || TELEGRAM_BOT_TOKEN === 'YOUR_ACTUAL_BOT_TOKEN_HERE' || TELEGRAM_BOT_TOKEN === '7726563483:AAF8TeGuly0SgloqO6CGVfUj5cBNyMXC8sk_PLACEHOLDER') { // Check against actual placeholder if needed
            error_log("Telegram Bot Token is not configured correctly or is still a placeholder.");
            return false;
        }
        // Check if $chat_id is an array (for sending to multiple admins)
        if (is_array($chat_id)) {
            $results = [];
            foreach ($chat_id as $individual_chat_id) {
                $results[$individual_chat_id] = send_telegram_message(trim($individual_chat_id), $message, $parse_mode);
            }
            // Return true if at least one message was sent successfully, or handle errors more granularly
            return in_array(true, $results, true);
        }

        if (empty($chat_id)) {
            error_log("Telegram Chat ID not provided for message: " . mb_substr($message, 0, 100, 'UTF-8') . "...");
            return false;
        }

        $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
        $data = ['chat_id' => $chat_id, 'text' => $message, 'parse_mode' => $parse_mode, 'disable_web_page_preview' => true];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        // For local XAMPP, SSL verification might be an issue.
        // The following line disables SSL verification. REMOVE FOR PRODUCTION.
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);


        $response_json = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($http_code == 200 && $response_json) {
            $response_data = json_decode($response_json, true);
            if ($response_data && isset($response_data['ok']) && $response_data['ok']) {
                return true;
            } else {
                $error_message_tg = "Telegram API Error: " . ($response_data['description'] ?? 'Unknown API error') . " (Code: " . ($response_data['error_code'] ?? 'N/A') . ")";
                // Log more details if available from $response_data
                if (isset($response_data['parameters']['retry_after'])) {
                    $error_message_tg .= " - Retry after: " . $response_data['parameters']['retry_after'] . "s";
                }
                error_log($error_message_tg . " - ChatID: $chat_id - Response: " . $response_json);
                return false;
            }
        } else {
            error_log("Telegram cURL Error (HTTP Code: $http_code): " . $curl_error . " - ChatID: $chat_id - Response: " . $response_json);
            return false;
        }
    }
}

if (!function_exists('create_notification')) {
    function create_notification($user_id_to_notify, $message_content, $link_url = null, $related_entity_type = null, $related_entity_id = null, $send_telegram_override = null) {
        global $conn;
        $db_notification_was_created = false;

        // Create DB Notification (if connection available)
        if ($conn && $user_id_to_notify) { // Ensure UserID is provided for DB notification
            $stmt_db_notif_insert = $conn->prepare("INSERT INTO Notifications (UserID, Message, Link, RelatedEntityType, RelatedEntityID, CreatedAt, IsRead) VALUES (?, ?, ?, ?, ?, NOW(), FALSE)");
            if ($stmt_db_notif_insert) {
                $sanitized_message_for_db_insert = sanitize_input(strip_tags($message_content));
                $stmt_db_notif_insert->bind_param("isssi", $user_id_to_notify, $sanitized_message_for_db_insert, $link_url, $related_entity_type, $related_entity_id);
                if ($stmt_db_notif_insert->execute()) {
                    $db_notification_was_created = true;
                } else {
                    error_log("create_notification: DB execute failed for UserID $user_id_to_notify: " . $stmt_db_notif_insert->error);
                }
                $stmt_db_notif_insert->close();
            } else {
                error_log("create_notification: DB prepare failed for DB notification: " . $conn->error);
            }
        } elseif (!$conn && $user_id_to_notify) {
            error_log("create_notification: DB connection unavailable. DB notification for UserID $user_id_to_notify was not created.");
        }


        // --- Telegram Notification Logic ---
        $telegram_notifications_enabled_from_db = null;
        if ($conn) {
            $stmt_tg_enabled_db = $conn->prepare("SELECT SettingValue FROM AppSettings WHERE SettingName = 'telegram_notifications_enabled' LIMIT 1");
            if ($stmt_tg_enabled_db) {
                if ($stmt_tg_enabled_db->execute()) {
                    $res_tg_enabled_db = $stmt_tg_enabled_db->get_result();
                    if ($row_tg_enabled_db = $res_tg_enabled_db->fetch_assoc()) {
                        $telegram_notifications_enabled_from_db = ($row_tg_enabled_db['SettingValue'] == '1' || strtolower($row_tg_enabled_db['SettingValue']) == 'true');
                    }
                } else {
                    error_log("create_notification: Failed to execute query for AppSettings 'telegram_notifications_enabled': " . $stmt_tg_enabled_db->error);
                }
                $stmt_tg_enabled_db->close();
            } else {
                 error_log("create_notification: DB prepare failed for AppSettings 'telegram_notifications_enabled': " . $conn->error);
            }
        }

        $should_send_telegram = false;
        if ($send_telegram_override !== null) {
            $should_send_telegram = $send_telegram_override; // Explicit override takes precedence
        } elseif ($telegram_notifications_enabled_from_db !== null) {
            $should_send_telegram = $telegram_notifications_enabled_from_db; // Use DB setting if available
        } else {
            // Fallback to default define if DB setting not found or DB connection failed
            $should_send_telegram = (defined('TELEGRAM_NOTIFICATIONS_ENABLED_DEFAULT') ? TELEGRAM_NOTIFICATIONS_ENABLED_DEFAULT : false);
        }

        if ($should_send_telegram) {
            // Send to User if UserID and ChatID are available
            $telegram_chat_id_for_user_notif = null;
            if ($conn && $user_id_to_notify) {
                $stmt_get_chat_id_for_user = $conn->prepare("SELECT TelegramChatID FROM Users WHERE UserID = ? AND TelegramChatID IS NOT NULL AND TelegramChatID != ''");
                if ($stmt_get_chat_id_for_user) {
                    $stmt_get_chat_id_for_user->bind_param("i", $user_id_to_notify);
                    if($stmt_get_chat_id_for_user->execute()){
                        $res_chat_id_for_user = $stmt_get_chat_id_for_user->get_result();
                        if ($row_chat_id_for_user = $res_chat_id_for_user->fetch_assoc()) {
                            $telegram_chat_id_for_user_notif = trim($row_chat_id_for_user['TelegramChatID']);
                        }
                    } else {
                        error_log("create_notification: Failed to execute query for User TelegramChatID (UserID: $user_id_to_notify): " . $stmt_get_chat_id_for_user->error);
                    }
                    $stmt_get_chat_id_for_user->close();
                } else {
                    error_log("create_notification: DB prepare failed for User TelegramChatID (UserID: $user_id_to_notify): " . $conn->error);
                }
            }

            if (!empty($telegram_chat_id_for_user_notif)) {
                $user_tg_message = $message_content; // Use original message content
                if ($link_url) {
                    $full_link_for_tg_user = $link_url;
                    if (strpos($link_url, 'http://') !== 0 && strpos($link_url, 'https://') !== 0) {
                        $protocol_tg = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
                        $host_tg = $_SERVER['HTTP_HOST'] ?? 'localhost';
                        // Try to determine project root more reliably if possible, or ensure links are passed as absolute
                        $project_base_path = '/my_site'; // This should ideally be a configurable value
                        $base_app_url_tg = $protocol_tg . $host_tg . $project_base_path;
                        $full_link_for_tg_user = rtrim($base_app_url_tg, '/') . '/' . ltrim($link_url, '/');
                    }
                    $allowed_tags_user = '<b><strong><i><em><a><code><pre>';
                    $user_tg_message = strip_tags($user_tg_message, $allowed_tags_user) . "\n\n<a href=\"" . htmlspecialchars($full_link_for_tg_user) . "\">مشاهده جزئیات</a>";
                } else {
                    $allowed_tags_user_no_link = '<b><strong><i><em><code><pre>';
                    $user_tg_message = strip_tags($user_tg_message, $allowed_tags_user_no_link);
                }
                send_telegram_message($telegram_chat_id_for_user_notif, $user_tg_message, 'HTML');
            }

            // Send to Admin Chat IDs (if configured as default or from DB)
            $admin_chat_ids_to_use_str = '';
            if ($conn) {
                $stmt_admin_ids_db = $conn->prepare("SELECT SettingValue FROM AppSettings WHERE SettingName = 'telegram_admin_chat_ids' LIMIT 1");
                if ($stmt_admin_ids_db) {
                    if($stmt_admin_ids_db->execute()){
                        $res_admin_ids_db = $stmt_admin_ids_db->get_result();
                        if ($row_admin_ids_db = $res_admin_ids_db->fetch_assoc()) {
                            $admin_chat_ids_to_use_str = $row_admin_ids_db['SettingValue'];
                        }
                    } else {
                        error_log("create_notification: Failed to execute query for AppSettings 'telegram_admin_chat_ids': " . $stmt_admin_ids_db->error);
                    }
                    $stmt_admin_ids_db->close();
                } else {
                     error_log("create_notification: DB prepare failed for AppSettings 'telegram_admin_chat_ids': " . $conn->error);
                }
            }

            // Fallback to default if DB value is empty or not found
            if (empty($admin_chat_ids_to_use_str) && defined('TELEGRAM_ADMIN_CHAT_IDS_DEFAULT')) {
                $admin_chat_ids_to_use_str = TELEGRAM_ADMIN_CHAT_IDS_DEFAULT;
            }

            if (!empty($admin_chat_ids_to_use_str) && $admin_chat_ids_to_use_str !== 'YOUR_MAIN_ADMIN_CHAT_ID') {
                $admin_chat_ids_array = array_map('trim', explode(',', $admin_chat_ids_to_use_str));
                $admin_chat_ids_array = array_filter($admin_chat_ids_array);

                if (!empty($admin_chat_ids_array)) {
                    $admin_tg_message = "[اعلان ادمین برای کاربر " . ($user_id_to_notify ?: 'سیستمی') . "]\n" . strip_tags($message_content); // Admin gets plain text + link
                     if ($link_url) {
                        $full_link_for_tg_admin = $link_url;
                         if (strpos($link_url, 'http://') !== 0 && strpos($link_url, 'https://') !== 0) {
                            $protocol_tg_admin = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
                            $host_tg_admin = $_SERVER['HTTP_HOST'] ?? 'localhost';
                            $project_base_path_admin = '/my_site'; // Consistent base path
                            $base_app_url_tg_admin = $protocol_tg_admin . $host_tg_admin . $project_base_path_admin;
                            $full_link_for_tg_admin = rtrim($base_app_url_tg_admin, '/') . '/' . ltrim($link_url, '/');
                        }
                        $admin_tg_message .= "\n\n<a href=\"" . htmlspecialchars($full_link_for_tg_admin) . "\">مشاهده جزئیات</a>";
                    }
                    send_telegram_message($admin_chat_ids_array, $admin_tg_message, 'HTML');
                }
            }
        }
        // Return true if the DB notification was created, regardless of Telegram status,
        // as the primary purpose is usually the in-app notification.
        return $db_notification_was_created;
    }
}
?>
