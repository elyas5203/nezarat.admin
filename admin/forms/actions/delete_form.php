<?php
require_once __DIR__ . '/../../../includes/config/db_config.php';
require_once __DIR__ . '/../../../includes/functions/helper_functions.php'; // For session, auth, CSRF

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!is_admin_logged_in()) {
    header("Location: " . '/my_site/admin/auth/login.php'); // Adjust to your admin login path
    exit;
}

$form_id_to_delete = null;
$error_message = '';
$success_message = '';

// CSRF Token Verification
$csrf_token_from_link = $_GET['csrf_token'] ?? '';
// This token name must match the one generated on the forms index page for delete links
if (!verify_csrf_token($csrf_token_from_link, 'forms_index_actions')) {
    header("Location: ../index.php?action_status=error&message=" . urlencode("خطای CSRF! درخواست حذف نامعتبر یا توکن منقضی شده."));
    exit;
}
// Regenerate the token for the index page to prevent reuse if the user navigates back
regenerate_csrf_token('forms_index_actions');


if (isset($_GET['form_id']) && is_numeric($_GET['form_id'])) {
    $form_id_to_delete = (int)$_GET['form_id'];

    $stmt_check = $conn->prepare("SELECT FormName FROM Forms WHERE FormID = ?");
    if ($stmt_check) {
        $stmt_check->bind_param("i", $form_id_to_delete);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows === 1) {
            $conn->begin_transaction();
            try {
                // 1. Delete FormSubmissionValues (children of FormSubmissions)
                // This needs to be done carefully if files are associated with submissions
                // For now, assuming no file handling in FormSubmissionValues directly, or files are managed elsewhere.
                $stmt_delete_values = $conn->prepare(
                    "DELETE fsv FROM FormSubmissionValues fsv
                     JOIN FormSubmissions fs ON fsv.SubmissionID = fs.SubmissionID
                     WHERE fs.FormID = ?"
                );
                if (!$stmt_delete_values) throw new Exception("خطا آماده سازی حذف مقادیر پاسخ‌ها: " . $conn->error);
                $stmt_delete_values->bind_param("i", $form_id_to_delete);
                if (!$stmt_delete_values->execute()) throw new Exception("خطا در حذف مقادیر پاسخ‌های فرم: " . $stmt_delete_values->error);
                $stmt_delete_values->close();

                // 2. Delete FormSubmissions
                $stmt_delete_submissions = $conn->prepare("DELETE FROM FormSubmissions WHERE FormID = ?");
                if (!$stmt_delete_submissions) throw new Exception("خطا آماده سازی حذف پاسخ‌ها: " . $conn->error);
                $stmt_delete_submissions->bind_param("i", $form_id_to_delete);
                if (!$stmt_delete_submissions->execute()) throw new Exception("خطا در حذف پاسخ‌های فرم: " . $stmt_delete_submissions->error);
                $stmt_delete_submissions->close();

                // 3. Delete FormFields
                $stmt_delete_fields = $conn->prepare("DELETE FROM FormFields WHERE FormID = ?");
                if (!$stmt_delete_fields) throw new Exception("خطا آماده سازی حذف فیلدهای فرم: " . $conn->error);
                $stmt_delete_fields->bind_param("i", $form_id_to_delete);
                if (!$stmt_delete_fields->execute()) throw new Exception("خطا در حذف فیلدهای فرم: " . $stmt_delete_fields->error);
                $stmt_delete_fields->close();

                // 4. Delete the Form itself
                $stmt_delete_form = $conn->prepare("DELETE FROM Forms WHERE FormID = ?");
                if (!$stmt_delete_form) throw new Exception("خطا آماده سازی حذف فرم اصلی: " . $conn->error);
                $stmt_delete_form->bind_param("i", $form_id_to_delete);

                if ($stmt_delete_form->execute()) {
                    if ($stmt_delete_form->affected_rows > 0) {
                        $conn->commit();
                        $success_message = "فرم و تمام داده‌های مرتبط (فیلدها و پاسخ‌ها) با آن با موفقیت حذف شد.";
                    } else {
                        // This case implies the form was deleted between check and execution.
                        throw new Exception("فرم برای حذف یافت نشد (پس از بررسی اولیه).");
                    }
                } else {
                    throw new Exception("خطا در حذف فرم اصلی: " . $stmt_delete_form->error);
                }
                $stmt_delete_form->close();

            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "خطای پایگاه داده در عملیات حذف فرم: " . $e->getMessage();
                // Log detailed error for admin: error_log("Form Deletion Error (FormID: $form_id_to_delete): " . $e->getMessage());
            }
        } else {
            $error_message = "فرمی با این شناسه یافت نشد.";
        }
        $stmt_check->close();
    } else {
        $error_message = "خطا در بررسی وجود فرم: " . $conn->error;
    }
} else {
    $error_message = "شناسه فرم برای حذف نامعتبر یا ارسال نشده است.";
}

$redirect_url = "../index.php?"; // Redirect back to the forms list
if (!empty($success_message)) {
    $redirect_url .= "action_status=success_delete&message=" . urlencode($success_message);
} else {
    $redirect_url .= "action_status=error&message=" . urlencode($error_message ?: "خطای نامشخص در عملیات حذف فرم.");
}

header("Location: " . $redirect_url);
exit;
?>
