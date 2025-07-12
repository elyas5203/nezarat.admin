<?php
require_once __DIR__ . '/../../../includes/config/db_config.php'; // Corrected path
require_once __DIR__ . '/../../../includes/functions/helper_functions.php'; // Corrected path

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
    $dept_id_to_delete = isset($_POST['dept_id']) ? (int)$_POST['dept_id'] : 0;
    $submitted_csrf_token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($submitted_csrf_token, 'delete_department')) {
        $_SESSION['action_error'] = 'خطای امنیتی CSRF. عملیات حذف بخش ناموفق بود.';
        header("Location: ../index.php");
        exit;
    }
    regenerate_csrf_token('delete_department');

    if ($dept_id_to_delete <= 0) {
        $_SESSION['action_error'] = 'شناسه بخش برای حذف نامعتبر است.';
        header("Location: ../index.php");
        exit;
    }

    if (!$conn) {
        $_SESSION['action_error'] = 'خطا در اتصال به پایگاه داده.';
        header("Location: ../index.php");
        exit;
    }

    // Check if the department to be deleted is 'Unassigned' or a default/system department (if any)
    // For example, if there's a department with a specific known name or ID that shouldn't be deleted.
    // $stmt_check_name = $conn->prepare("SELECT DepartmentName FROM Departments WHERE DepartmentID = ?");
    // $stmt_check_name->bind_param("i", $dept_id_to_delete);
    // $stmt_check_name->execute();
    // $dept_check_res = $stmt_check_name->get_result();
    // if($dept_check_data = $dept_check_res->fetch_assoc()){
    //     if(strtolower($dept_check_data['DepartmentName']) === 'بدون بخش'){ // Example name
    //         $_SESSION['action_error'] = 'بخش پیش‌فرض "بدون بخش" قابل حذف نیست.';
    //         header("Location: ../index.php");
    //         exit;
    //     }
    // }
    // $stmt_check_name->close();


    $conn->begin_transaction();
    try {
        // Before deleting a department, consider what happens to entities associated with it.
        // Example: Users in this department. You might want to reassign them to a default "Unassigned" department.
        // $default_dept_id_for_reassignment = 0; // Assuming 0 is an "Unassigned" department ID or handle as NULL
        // $stmt_reassign_users = $conn->prepare("UPDATE Users SET DepartmentID = ? WHERE DepartmentID = ?");
        // if ($stmt_reassign_users) {
        //     $stmt_reassign_users->bind_param("ii", $default_dept_id_for_reassignment, $dept_id_to_delete);
        //     $stmt_reassign_users->execute(); // Errors here might not be critical for department deletion itself
        //     $stmt_reassign_users->close();
        // } else {
        //    error_log("Failed to prepare user reassignment for dept delete: ".$conn->error);
        // }
        // Similar logic for other entities (tasks, etc.)

        $stmt_delete_dept = $conn->prepare("DELETE FROM Departments WHERE DepartmentID = ?");
        if (!$stmt_delete_dept) throw new Exception("خطا در آماده سازی حذف بخش: " . $conn->error);

        $stmt_delete_dept->bind_param("i", $dept_id_to_delete);

        if ($stmt_delete_dept->execute()) {
            if ($stmt_delete_dept->affected_rows > 0) {
                $conn->commit();
                $_SESSION['action_success'] = 'بخش با شناسه ' . $dept_id_to_delete . ' با موفقیت حذف شد.';
            } else {
                $conn->rollback();
                $_SESSION['action_error'] = 'بخش مورد نظر برای حذف یافت نشد یا قبلاً حذف شده است.';
            }
        } else {
            // Specific error for foreign key constraint violation
            if ($conn->errno == 1451) {
                 throw new Exception('امکان حذف این بخش وجود ندارد زیرا اطلاعات وابسته (مانند کاربران یا وظایف تخصیص داده شده) در سیستم موجود است. لطفاً ابتدا این وابستگی‌ها را برطرف نمایید.');
            }
            throw new Exception("خطا در اجرای حذف بخش: " . $stmt_delete_dept->error);
        }
        $stmt_delete_dept->close();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['action_error'] = $e->getMessage();
    }

} else {
    $_SESSION['action_error'] = 'درخواست نامعتبر برای حذف بخش (متد غیر POST).';
}

header("Location: ../index.php");
exit;
?>
