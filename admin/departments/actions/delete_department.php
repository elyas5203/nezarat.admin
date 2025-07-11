<?php
require_once __DIR__ . '/../../../includes/config/db_config.php';
require_once __DIR__ . '/../../../includes/functions/helper_functions.php'; // For session_start, is_admin_logged_in, CSRF

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!is_admin_logged_in()) {
    // Adjust path to your admin login page
    header("Location: " . '/my_site/admin/auth/login.php');
    exit;
}

$department_id_to_delete = null;
$error_message = '';
$success_message = '';

// CSRF Token Verification
$csrf_token_from_link = $_GET['csrf_token'] ?? '';
// The token name must match the one generated on the index page for delete links
if (!verify_csrf_token($csrf_token_from_link, 'departments_index_actions')) {
    header("Location: ../index.php?action_status=error&message=" . urlencode("خطای CSRF! درخواست حذف نامعتبر یا توکن منقضی شده."));
    exit;
}
// It's good practice to regenerate the token that was just used, or the one on the target page.
// Since we are redirecting to index.php, the token on that page will be used/regenerated on its load.


if (isset($_GET['dep_id']) && is_numeric($_GET['dep_id'])) {
    $department_id_to_delete = (int)$_GET['dep_id'];

    $stmt_check = $conn->prepare("SELECT DepartmentName FROM Departments WHERE DepartmentID = ?");
    if ($stmt_check) {
        $stmt_check->bind_param("i", $department_id_to_delete);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows === 1) {
            $conn->begin_transaction();
            try {
                // 1. Remove all user assignments (managers and members) from UserDepartments
                $stmt_delete_user_assignments = $conn->prepare("DELETE FROM UserDepartments WHERE DepartmentID = ?");
                if (!$stmt_delete_user_assignments) throw new Exception("خطا آماده سازی حذف تخصیص کاربران به بخش: " . $conn->error);
                $stmt_delete_user_assignments->bind_param("i", $department_id_to_delete);
                if (!$stmt_delete_user_assignments->execute()) throw new Exception("خطا در حذف تخصیص کاربران به بخش: " . $stmt_delete_user_assignments->error);
                $stmt_delete_user_assignments->close();

                // 2. Handle other direct dependencies on the Departments table.
                // Example: If Tasks.AssignedToDepartmentID has a foreign key to Departments.DepartmentID
                // and it's ON DELETE RESTRICT (default) or ON DELETE NO ACTION,
                // you must manually handle these or the DELETE on Departments will fail.
                // If it's ON DELETE SET NULL, the DB will handle it.
                // If it's ON DELETE CASCADE, related tasks would be deleted (usually not desired for department deletion).

                // For example, disassociate tasks:
                // $stmt_update_tasks = $conn->prepare("UPDATE Tasks SET AssignedToDepartmentID = NULL WHERE AssignedToDepartmentID = ?");
                // if ($stmt_update_tasks) {
                //     $stmt_update_tasks->bind_param("i", $department_id_to_delete);
                //     $stmt_update_tasks->execute();
                //     $stmt_update_tasks->close();
                // } else { throw new Exception("خطا در به‌روزرسانی وظایف مرتبط با بخش.");}

                // Similarly for Forms.DepartmentID, Meetings.DepartmentID, etc.
                // For now, we will attempt to delete and let DB foreign key constraints (if any are RESTRICT) handle it.
                // A more robust solution would check these dependencies first or use SET NULL.

                // 3. Delete the department itself
                $stmt_delete_dept = $conn->prepare("DELETE FROM Departments WHERE DepartmentID = ?");
                if (!$stmt_delete_dept) throw new Exception("خطا آماده سازی حذف بخش: " . $conn->error);
                $stmt_delete_dept->bind_param("i", $department_id_to_delete);

                if ($stmt_delete_dept->execute()) {
                    if ($stmt_delete_dept->affected_rows > 0) {
                        $conn->commit();
                        $success_message = "بخش با موفقیت حذف شد. تمامی کاربران از این بخش خارج شدند.";
                    } else {
                        // This state implies the department was deleted between check and execution, or didn't exist.
                        throw new Exception("بخش برای حذف یافت نشد (پس از بررسی اولیه).");
                    }
                } else {
                    throw new Exception("خطا در حذف بخش: " . $stmt_delete_dept->error);
                }
                $stmt_delete_dept->close();

            } catch (mysqli_sql_exception $e) {
                $conn->rollback();
                // MySQL error code 1451: Cannot delete or update a parent row: a foreign key constraint fails
                if ($e->getCode() == 1451) {
                     $error_message = "امکان حذف این بخش وجود ندارد زیرا موجودیت‌های دیگری (مانند وظایف، فرم‌ها یا جلسات فعال) به آن ارجاع دارند. لطفاً ابتدا این وابستگی‌ها را حذف یا ویرایش کنید.";
                } else {
                    $error_message = "خطای پایگاه داده در حذف بخش: " . $e->getMessage() . " (Code: " . $e->getCode() . ")";
                }
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "خطای عمومی: " . $e->getMessage();
            }
        } else {
            $error_message = "بخشی با این شناسه یافت نشد.";
        }
        $stmt_check->close();
    } else {
        $error_message = "خطا در بررسی وجود بخش: " . $conn->error;
    }
} else {
    $error_message = "شناسه بخش برای حذف نامعتبر یا ارسال نشده است.";
}

// After operation, always regenerate the token for the page we are redirecting to, if it uses one for its own actions.
// The token 'departments_index_actions' is used on index.php for its delete links.
regenerate_csrf_token('departments_index_actions');

$redirect_url = "../index.php?";
if (!empty($success_message)) {
    $redirect_url .= "action_status=success_delete&message=" . urlencode($success_message);
} else {
    $redirect_url .= "action_status=error&message=" . urlencode($error_message ?: "خطای نامشخص در عملیات حذف بخش.");
}

header("Location: " . $redirect_url);
exit;
?>
