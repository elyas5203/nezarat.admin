<?php
// admin/classes/actions/delete_class.php
require_once __DIR__ . '/../../../includes/config/db_config.php'; // Adjusted path
require_once __DIR__ . '/../../../includes/functions/helper_functions.php'; // For session, auth, CSRF

if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!is_admin_logged_in()) {
    // Adjust to your admin login path if different
    header("Location: " . ($admin_base_url ?? '/my_site/admin') . "/auth/login.php");
    exit;
}

$class_id_to_delete = null;
$error_message_cls_del = '';
$success_message_cls_del = '';

// CSRF Token Verification - Token name must match the one generated on the index page for delete links
$csrf_token_from_link_cls_del = $_GET['csrf_token'] ?? '';
if (!verify_csrf_token($csrf_token_from_link_cls_del, 'classes_index_actions')) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطای CSRF! درخواست حذف نامعتبر یا توکن منقضی شده.'];
    header("Location: ../index.php");
    exit;
}
// Regenerate the token for the index page to prevent reuse of the same token if user navigates back
regenerate_csrf_token('classes_index_actions');

if (isset($_GET['class_id']) && is_numeric($_GET['class_id'])) {
    $class_id_to_delete = (int)$_GET['class_id'];

    $stmt_check_cls_del = $conn->prepare("SELECT ClassName FROM Classes WHERE ClassID = ?");
    if ($stmt_check_cls_del) {
        $stmt_check_cls_del->bind_param("i", $class_id_to_delete);
        $stmt_check_cls_del->execute();
        $result_check_cls_del = $stmt_check_cls_del->get_result();

        if ($result_check_cls_del->num_rows === 1) {
            $conn->begin_transaction();
            try {
                // --- Handle Dependencies ---
                // 1. FormSubmissions: Set ClassID to NULL
                $stmt_update_submissions = $conn->prepare("UPDATE FormSubmissions SET ClassID = NULL WHERE ClassID = ?");
                if (!$stmt_update_submissions) throw new Exception("خطا بروزرسانی FormSubmissions: " . $conn->error);
                $stmt_update_submissions->bind_param("i", $class_id_to_delete);
                if (!$stmt_update_submissions->execute()) throw new Exception("اجرای بروزرسانی FormSubmissions ناموفق: " . $stmt_update_submissions->error);
                $stmt_update_submissions->close();

                // 2. BookletAssignments: Set ClassID to NULL
                $stmt_update_booklets = $conn->prepare("UPDATE BookletAssignments SET ClassID = NULL WHERE ClassID = ?");
                if (!$stmt_update_booklets) throw new Exception("خطا بروزرسانی BookletAssignments: " . $conn->error);
                $stmt_update_booklets->bind_param("i", $class_id_to_delete);
                if (!$stmt_update_booklets->execute()) throw new Exception("اجرای بروزرسانی BookletAssignments ناموفق: " . $stmt_update_booklets->error);
                $stmt_update_booklets->close();

                // 3. ClassServices (Parvareshi): Set ClassID to NULL
                // Check if table exists before trying to update (optional, good for modularity)
                // if ($conn->query("SHOW TABLES LIKE 'ClassServices'")->num_rows > 0) { ... }
                $stmt_update_class_services = $conn->prepare("UPDATE ClassServices SET ClassID = NULL WHERE ClassID = ?");
                if ($stmt_update_class_services) { // Proceed even if table doesn't exist (prepare will be false)
                    $stmt_update_class_services->bind_param("i", $class_id_to_delete);
                    $stmt_update_class_services->execute(); // Errors here might be acceptable if table doesn't exist or no rows match
                    $stmt_update_class_services->close();
                }

                // 4. RentalBookings (Parvareshi): Set ClassID to NULL
                $stmt_update_rental_bookings = $conn->prepare("UPDATE RentalBookings SET ClassID = NULL WHERE ClassID = ?");
                if ($stmt_update_rental_bookings) {
                    $stmt_update_rental_bookings->bind_param("i", $class_id_to_delete);
                    $stmt_update_rental_bookings->execute();
                    $stmt_update_rental_bookings->close();
                }

                // 5. Meetings (e.g., parents_meeting): Set ClassID to NULL
                $stmt_update_meetings = $conn->prepare("UPDATE Meetings SET ClassID = NULL WHERE ClassID = ?");
                if ($stmt_update_meetings) {
                    $stmt_update_meetings->bind_param("i", $class_id_to_delete);
                    $stmt_update_meetings->execute();
                    $stmt_update_meetings->close();
                }

                // --- Delete the class itself ---
                $stmt_delete_class_main = $conn->prepare("DELETE FROM Classes WHERE ClassID = ?");
                if (!$stmt_delete_class_main) throw new Exception("خطا آماده سازی حذف کلاس: " . $conn->error);
                $stmt_delete_class_main->bind_param("i", $class_id_to_delete);

                if ($stmt_delete_class_main->execute()) {
                    if ($stmt_delete_class_main->affected_rows > 0) {
                        $conn->commit();
                        $success_message_cls_del = "کلاس با موفقیت حذف شد. وابستگی‌های مرتبط بروزرسانی شدند.";
                    } else {
                        throw new Exception("کلاس برای حذف یافت نشد (پس از بررسی اولیه).");
                    }
                } else {
                    throw new Exception("خطا در حذف کلاس: " . $stmt_delete_class_main->error);
                }
                $stmt_delete_class_main->close();

            } catch (mysqli_sql_exception $e_cls_del_sql) {
                $conn->rollback();
                if ($e_cls_del_sql->getCode() == 1451) {
                     $error_message_cls_del = "حذف ناموفق: این کلاس هنوز به موجودیت‌های دیگری وابسته است که به طور خودکار قابل بروزرسانی نیستند. لطفاً ابتدا این وابستگی‌ها را بررسی و مدیریت کنید.";
                } else {
                    $error_message_cls_del = "خطای پایگاه داده: " . $e_cls_del_sql->getMessage();
                }
            } catch (Exception $e_cls_del) {
                $conn->rollback();
                $error_message_cls_del = "خطای عمومی: " . $e_cls_del->getMessage();
            }
        } else {
            $error_message_cls_del = "کلاسی با این شناسه یافت نشد.";
        }
        $stmt_check_cls_del->close();
    } else {
        $error_message_cls_del = "خطا در بررسی وجود کلاس: " . $conn->error;
    }
} else {
    $error_message_cls_del = "شناسه کلاس برای حذف نامعتبر یا ارسال نشده است.";
}

$redirect_url_cls_del = "../index.php"; // Redirect back to classes list
if (!empty($success_message_cls_del)) {
    $_SESSION['flash_message'] = ['type' => 'success', 'text' => $success_message_cls_del];
} else {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => $error_message_cls_del ?: "خطای نامشخص در عملیات حذف کلاس."];
}

header("Location: " . $redirect_url_cls_del);
exit;
?>
