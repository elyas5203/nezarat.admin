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

// Form Builder Helper Functions
if (!function_exists('get_form_field_types_config')) {
    function get_form_field_types_config() {
        return [
            'add_new_field_icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-lg me-1" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2Z"/></svg>',
            'types' => [
                'text' => ['label' => 'متن تک خطی', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-input-cursor-text me-2" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M5 2a.5.5 0 0 1 .5.5v11a.5.5 0 0 1-1 0v-11A.5.5 0 0 1 5 2zM2 4a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V4zm1 0v8a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1z"/></svg>', 'has_options' => false, 'has_placeholder' => true, 'has_min_max_value' => false, 'has_max_length' => true, 'has_helper_text' => true, 'has_file_types' => false],
                'textarea' => ['label' => 'متن چند خطی', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-textarea-t me-2" viewBox="0 0 16 16"><path d="M1.5 2.5A1.5 1.5 0 0 1 3 1h10a1.5 1.5 0 0 1 1.5 1.5v3.563h-1v-3.556a.5.5 0 0 0-.5-.5H3a.5.5 0 0 0-.5.5v3.556h-1V2.5zM2 7v5.5A1.5 1.5 0 0 0 3.5 14h9a1.5 1.5 0 0 0 1.5-1.5V7h-1v5.5a.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5V7h-1z"/><path d="M5 10.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5zm0-2a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5z"/></svg>', 'has_options' => false, 'has_placeholder' => true, 'has_min_max_value' => false, 'has_max_length' => true, 'has_helper_text' => true, 'has_file_types' => false],
                'number' => ['label' => 'عددی', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-hash me-2" viewBox="0 0 16 16"><path d="M8.39 12.648a1.32 1.32 0 0 0-.015.18c0 .305.21.508.5.508.266 0 .492-.172.555-.477l.554-2.703h1.204c.421 0 .617-.234.617-.547 0-.312-.188-.53-.617-.53h-.985l.516-2.524h1.265c.43 0 .618-.227.618-.547 0-.313-.188-.524-.618-.524h-1.046l.476-2.304a1.06 1.06 0 0 0 .016-.164.51.51 0 0 0-.516-.516.54.54 0 0 0-.539.43l-.523 2.554H7.617l.477-2.304c.008-.04.015-.118.015-.164a.512.512 0 0 0-.512-.512.539.539 0 0 0-.531.43L6.53 5.484H5.414c-.43 0-.617.227-.617.547 0 .313.188.524.617.524h.91l-.516 2.524H4.69c-.421 0-.617.234-.617.547 0 .312.196.53.617.53h.985l-.554 2.703c-.02.118-.03.227-.03.313 0 .305.21.508.5.508.281 0 .487-.172.554-.477l.555-2.703h2.242l-.515 2.422zm-1.13-1.403h2.116l.516-2.524H6.748l.512 2.524z"/></svg>', 'has_options' => false, 'has_placeholder' => true, 'has_min_max_value' => true, 'has_max_length' => false, 'has_helper_text' => true, 'has_file_types' => false],
                'date' => ['label' => 'تاریخ (شمسی)', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-calendar3 me-2" viewBox="0 0 16 16"><path d="M14 0H2a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2zM1 3.857C1 3.384 1.448 3 2 3h12c.552 0 1 .384 1 .857v10.286c0 .473-.448.857-1 .857H2c-.552 0-1-.384-1-.857V3.857z"/><path d="M6.5 7a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-9 3a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-9 3a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/></svg>', 'has_options' => false, 'has_placeholder' => false, 'has_min_max_value' => false, 'has_max_length' => false, 'has_helper_text' => true, 'has_file_types' => false],
                'select' => ['label' => 'لیست کشویی', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-menu-button-wide-fill me-2" viewBox="0 0 16 16"><path d="M1.5 0A1.5 1.5 0 0 0 0 1.5v2A1.5 1.5 0 0 0 1.5 5h13A1.5 1.5 0 0 0 16 3.5v-2A1.5 1.5 0 0 0 14.5 0h-13zm1 2h3a.5.5 0 0 1 0 1h-3a.5.5 0 0 1 0-1zm-1 7A1.5 1.5 0 0 0 0 10.5v2A1.5 1.5 0 0 0 1.5 14h13a1.5 1.5 0 0 0 1.5-1.5v-2A1.5 1.5 0 0 0 14.5 9h-13zm1 2h3a.5.5 0 0 1 0 1h-3a.5.5 0 0 1 0-1z"/></svg>', 'has_options' => true, 'has_placeholder' => false, 'has_min_max_value' => false, 'has_max_length' => false, 'has_helper_text' => true, 'has_file_types' => false],
                'radio' => ['label' => 'گزینه‌های رادیویی', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-ui-radios me-2" viewBox="0 0 16 16"><path d="M7 2.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-7a.5.5 0 0 1-.5-.5v-1zM0 12a3 3 0 1 1 6 0 3 3 0 0 1-6 0zM7 12.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5zM3 12a1 1 0 1 0 0-2 1 1 0 0 0 0 2zM0 2a2 2 0 1 1 4 0 2 2 0 0 1-4 0zm2-1.5a.5.5 0 0 1 .5.5v3a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-3a.5.5 0 0 1 .5-.5h1z"/></svg>', 'has_options' => true, 'has_placeholder' => false, 'has_min_max_value' => false, 'has_max_length' => false, 'has_helper_text' => true, 'has_file_types' => false],
                'checkbox' => ['label' => 'چک‌باکس‌ها', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-ui-checks me-2" viewBox="0 0 16 16"><path d="M7 2.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-7a.5.5 0 0 1-.5-.5v-1zM2 1a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2H2zm0 1h2a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1pzm0 8a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2v-2a2 2 0 0 0-2-2H2zm0 1h2a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1v-2a1 1 0 0 1 1-1zm5-6.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-7a.5.5 0 0 1-.5-.5v-1zm0 8a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-7a.5.5 0 0 1-.5-.5v-1z"/></svg>', 'has_options' => true, 'has_placeholder' => false, 'has_min_max_value' => false, 'has_max_length' => false, 'has_helper_text' => true, 'has_file_types' => false],
                // 'file' => ['label' => 'آپلود فایل', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-arrow-up me-2" viewBox="0 0 16 16"><path d="M8.5 11.5a.5.5 0 0 1-1 0V7.707L6.354 8.854a.5.5 0 1 1-.708-.708l2-2a.5.5 0 0 1 .708 0l2 2a.5.5 0 0 1-.708.708L8.5 7.707V11.5z"/><path d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2zM9.5 1.5v2A1.5 1.5 0 0 0 11 5h2V2.454L9.5 1.5z"/></svg>', 'has_options' => false, 'has_placeholder' => false, 'has_min_max_value' => false, 'has_max_length' => false, 'has_helper_text' => true, 'has_file_types' => true],
            ]
        ];
    }
}

if (!function_exists('get_form_field_templates_html')) {
    function get_form_field_templates_html() {
        // This function centralizes the HTML templates for form fields.
        // It helps keep the create.php and edit.php cleaner.
        ob_start();
        ?>
        <!-- Universal Field Wrapper Template -->
        <template id="form-field-wrapper-template">
            <div class="form-field-item card mb-3 border" data-field-id="">
                <div class="card-header d-flex justify-content-between align-items-center p-2 bg-light">
                    <span class="field-type-icon-label fw-bold">
                        <!-- Icon and Label will be injected by JS -->
                    </span>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-secondary handle-sort me-1" title="جابجایی فیلد"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-grip-vertical" viewBox="0 0 16 16"><path d="M7 2a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 5a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 8a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm-3 3a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm-3 3a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"/></svg></button>
                        <button type="button" class="btn btn-sm btn-danger remove-field-btn" title="حذف فیلد"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-x-lg" viewBox="0 0 16 16"><path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8 2.146 2.854Z"/></svg></button>
                    </div>
                </div>
                <div class="card-body p-3 field-properties-container">
                    <!-- Common properties first -->
                    <div class="row gx-2">
                        <div class="col-md-12 mb-2">
                            <label class="form-label form-label-sm">برچسب (متن سوال) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm field-property" data-property="label" required>
                        </div>
                    </div>
                    <!-- Specific properties will be injected here by JS -->
                    <div class="specific-properties mb-2"></div>

                    <!-- Common advanced properties last -->
                     <div class="row gx-2">
                        <div class="col-md-6 mb-2 field-prop-placeholder" style="display:none;">
                            <label class="form-label form-label-sm">متن راهنما (Placeholder)</label>
                            <input type="text" class="form-control form-control-sm field-property" data-property="placeholder">
                        </div>
                        <div class="col-md-6 mb-2 field-prop-helper-text" style="display:none;">
                            <label class="form-label form-label-sm">متن کمکی</label>
                            <input type="text" class="form-control form-control-sm field-property" data-property="helper_text" placeholder="توضیح بیشتر زیر فیلد">
                        </div>
                    </div>
                    <div class="form-check form-switch mt-1">
                        <input class="form-check-input field-property" type="checkbox" data-property="required" id="field_required_placeholder_id">
                        <label class="form-check-label form-check-label-sm" for="field_required_placeholder_id">الزامی باشد</label>
                    </div>
                    <input type="hidden" class="field-property" data-property="type" value="">
                </div>
            </div>
        </template>

        <!-- Property Snippet: Options (for select, radio, checkbox) -->
        <template id="prop-options-template">
            <div class="mb-2 options-group">
                <label class="form-label form-label-sm">گزینه‌ها (هر گزینه در یک خط جدید)</label>
                <textarea class="form-control form-control-sm field-property options-input" data-property="options_text" rows="3" placeholder="گزینه ۱\nگزینه ۲"></textarea>
            </div>
        </template>

        <!-- Property Snippet: Min/Max Value (for number) -->
        <template id="prop-min-max-value-template">
            <div class="row gx-2">
                <div class="col-6 mb-2">
                    <label class="form-label form-label-sm">حداقل مقدار</label>
                    <input type="number" class="form-control form-control-sm field-property" data-property="min_value" placeholder="اختیاری">
                </div>
                <div class="col-6 mb-2">
                    <label class="form-label form-label-sm">حداکثر مقدار</label>
                    <input type="number" class="form-control form-control-sm field-property" data-property="max_value" placeholder="اختیاری">
                </div>
            </div>
        </template>

        <!-- Property Snippet: Max Length (for text, textarea) -->
        <template id="prop-max-length-template">
             <div class="mb-2">
                <label class="form-label form-label-sm">حداکثر طول کاراکتر</label>
                <input type="number" class="form-control form-control-sm field-property" data-property="max_length" placeholder="اختیاری (مثال: 255)">
            </div>
        </template>

        <!-- Property Snippet: File Types (for file input - if implemented) -->
        <template id="prop-file-types-template">
            <div class="mb-2">
                <label class="form-label form-label-sm">انواع فایل مجاز (با ویرگول جدا کنید)</label>
                <input type="text" class="form-control form-control-sm field-property" data-property="file_types_text" placeholder="مثال: .jpg, .png, .pdf">
                <small class="form-text text-muted field-property-sm">مثال: .jpg, .png, .pdf, image/*</small>
            </div>
        </template>

        <?php
        return ob_get_clean();
    }
}
?>
