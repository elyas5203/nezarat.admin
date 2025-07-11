<?php
// includes/config/db_config.php

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // نام کاربری پیش‌فرض XAMPP
define('DB_PASSWORD', '');     // رمز عبور پیش‌فرض XAMPP
define('DB_NAME', 'dabestan_management_system'); // نام پایگاه داده‌ای که ایجاد خواهیم کرد

// ایجاد اتصال
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD);

// بررسی اتصال
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// انتخاب پایگاه داده (اگر وجود ندارد، بعداً ایجاد می‌شود)
$db_selected = $conn->select_db(DB_NAME);
if (!$db_selected) {
    // اگر پایگاه داده وجود ندارد، آن را ایجاد کنید
    $sql_create_db = "CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci";
    if ($conn->query($sql_create_db) === TRUE) {
        $conn->select_db(DB_NAME); // انتخاب مجدد پایگاه داده
    } else {
        die("Error creating database: " . $conn->error);
    }
}

// تنظیم charset برای ارتباط صحیح با داده‌های فارسی
$conn->set_charset("utf8mb4");

// (اختیاری) تنظیم timezone
date_default_timezone_set('Asia/Tehran');

?>
