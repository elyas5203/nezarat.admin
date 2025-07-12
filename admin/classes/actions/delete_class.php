<?php
require_once __DIR__ . '/../../../includes/config/db_config.php';
require_once __DIR__ . '/../../../includes/functions/helper_functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ensure admin is logged in
if (!is_admin_logged_in()) {
    $_SESSION['action_error'] = 'برای انجام این عملیات باید به عنوان ادمین وارد شده باشید.';
    header("Location: " . get_base_url() . "admin/auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_id_to_delete = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
    $submitted_csrf_token = $_POST['csrf_token'] ?? '';

    // The CSRF token for deletion is generated on the index.php page with name 'delete_class'
    if (!verify_csrf_token($submitted_csrf_token, 'delete_class')) {
        $_SESSION['action_error'] = 'خطای امنیتی CSRF. عملیات حذف کلاس ناموفق بود.';
        header("Location: ../index.php");
        exit;
    }
    regenerate_csrf_token('delete_class'); // Regenerate after successful verification

    if ($class_id_to_delete <= 0) {
        $_SESSION['action_error'] = 'شناسه کلاس برای حذف نامعتبر است.';
        header("Location: ../index.php");
        exit;
    }

    if (!$conn) {
        $_SESSION['action_error'] = 'خطا در اتصال به پایگاه داده.';
        header("Location: ../index.php");
        exit;
    }

    $conn->begin_transaction();
    try {
        // 1. Delete teacher assignments for this class from ClassTeachers
        $stmt_delete_teachers = $conn->prepare("DELETE FROM ClassTeachers WHERE ClassID = ?");
        if (!$stmt_delete_teachers) throw new Exception("خطا در آماده سازی حذف تخصیص مدرسین: " . $conn->error);
        $stmt_delete_teachers->bind_param("i", $class_id_to_delete);
        // Execute, but don't throw error if it fails due to no teachers (it's fine)
        $stmt_delete_teachers->execute();
        $stmt_delete_teachers->close();

        // 2. Consider other dependencies:
        //    - FormSubmissions related to this ClassID.
        //    - Student enrollments (if a StudentsInClass table exists with ClassID).
        //    - Parent meetings (if ParentsMeetings table exists with ClassID).
        //    - Any other table that might have a ClassID foreign key.
        //    Policy: For now, we rely on DB foreign key constraints (ON DELETE RESTRICT by default)
        //    to prevent deletion if critical dependencies exist. The error message will guide admin.
        //    A more robust solution might involve:
        //      a) Explicitly checking and warning/preventing.
        //      b) Archiving the class instead of deleting.
        //      c) Setting ClassID to NULL in dependent tables if the schema allows (ON DELETE SET NULL).

        // 3. Delete the class itself from Classes table
        $stmt_delete_class = $conn->prepare("DELETE FROM Classes WHERE ClassID = ?");
        if (!$stmt_delete_class) throw new Exception("خطا در آماده سازی حذف کلاس: " . $conn->error);

        $stmt_delete_class->bind_param("i", $class_id_to_delete);

        if ($stmt_delete_class->execute()) {
            if ($stmt_delete_class->affected_rows > 0) {
                $conn->commit();
                $_SESSION['action_success'] = 'کلاس با شناسه ' . $class_id_to_delete . ' و تمامی تخصیص‌های مدرس آن (در صورت وجود) با موفقیت حذف شدند.';
            } else {
                $conn->rollback();
                $_SESSION['action_error'] = 'کلاس مورد نظر برای حذف یافت نشد یا قبلاً حذف شده است.';
            }
        } else {
            if ($conn->errno == 1451) {
                 throw new Exception('امکان حذف این کلاس وجود ندارد زیرا اطلاعات وابسته دیگری (مانند فرم‌های ثبت شده، تخصیص دانش آموزان، یا جلسات اولیا مرتبط با این کلاس) در سیستم موجود است. لطفاً ابتدا این وابستگی‌ها را برطرف نمایید.');
            }
            throw new Exception("خطا در اجرای حذف کلاس: " . $stmt_delete_class->error);
        }
        $stmt_delete_class->close();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['action_error'] = $e->getMessage();
    }

} else {
    $_SESSION['action_error'] = 'درخواست نامعتبر برای حذف کلاس (متد غیر POST).';
}

header("Location: ../index.php");
exit;
?>
