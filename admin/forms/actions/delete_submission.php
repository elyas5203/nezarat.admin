<?php
// admin/forms/actions/delete_submission.php
require_once __DIR__ . '/../../../includes/config/db_config.php';
require_once __DIR__ . '/../../../includes/functions/helper_functions.php'; // For session, auth, CSRF

if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!is_admin_logged_in()) { header("Location: " . '/my_site/admin/auth/login.php'); exit;}

$submission_id_to_delete = null;
$form_id_redirect = $_GET['form_id'] ?? null;
$error_message = '';
$success_message = '';

// CSRF Token Verification
$csrf_token_from_link = $_GET['csrf_token'] ?? '';
$csrf_form_name = 'form_submission_actions_formid' . $form_id_redirect; // Match token name from submissions.php

if (!verify_csrf_token($csrf_token_from_link, $csrf_form_name)) {
    $redirect_url_csrf_fail = $form_id_redirect ? "../submissions.php?form_id=" . $form_id_redirect : "../index.php";
    header("Location: " . $redirect_url_csrf_fail . "&action_status=error&message=" . urlencode("خطای CSRF! درخواست حذف نامعتبر."));
    exit;
}
regenerate_csrf_token($csrf_form_name); // Regenerate after verification


if (isset($_GET['submission_id']) && is_numeric($_GET['submission_id'])) {
    $submission_id_to_delete = (int)$_GET['submission_id'];

    $stmt_check = $conn->prepare("SELECT SubmissionID FROM FormSubmissions WHERE SubmissionID = ?");
    if ($stmt_check) {
        $stmt_check->bind_param("i", $submission_id_to_delete);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows === 1) {
            $conn->begin_transaction();
            try {
                // 1. Delete from FormSubmissionValues first (child table)
                $stmt_delete_values = $conn->prepare("DELETE FROM FormSubmissionValues WHERE SubmissionID = ?");
                if (!$stmt_delete_values) throw new Exception("خطا آماده سازی حذف مقادیر پاسخ: " . $conn->error);
                $stmt_delete_values->bind_param("i", $submission_id_to_delete);
                if (!$stmt_delete_values->execute()) throw new Exception("خطا در حذف مقادیر پاسخ: " . $stmt_delete_values->error);
                $stmt_delete_values->close();

                // 2. Delete from FormSubmissions (parent table)
                $stmt_delete_submission = $conn->prepare("DELETE FROM FormSubmissions WHERE SubmissionID = ?");
                if (!$stmt_delete_submission) throw new Exception("خطا آماده سازی حذف پاسخ اصلی: " . $conn->error);
                $stmt_delete_submission->bind_param("i", $submission_id_to_delete);

                if ($stmt_delete_submission->execute()) {
                    if ($stmt_delete_submission->affected_rows > 0) {
                        $conn->commit();
                        $success_message = "پاسخ با موفقیت حذف شد.";
                    } else {
                        // Should not happen if existence was checked
                        throw new Exception("پاسخ برای حذف یافت نشد (پس از بررسی اولیه).");
                    }
                } else {
                    throw new Exception("خطا در حذف پاسخ اصلی: " . $stmt_delete_submission->error);
                }
                $stmt_delete_submission->close();
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "خطای پایگاه داده: " . $e->getMessage();
            }
        } else {
            $error_message = "پاسخی با این شناسه یافت نشد.";
        }
        $stmt_check->close();
    } else {
        $error_message = "خطا در بررسی وجود پاسخ: " . $conn->error;
    }
} else {
    $error_message = "شناسه پاسخ برای حذف نامعتبر یا ارسال نشده است.";
}

// Determine redirect URL (back to submissions list for the specific form)
$redirect_url = $form_id_redirect ? "../submissions.php?form_id=" . $form_id_redirect : "../index.php";

if (!empty($success_message)) {
    $redirect_url .= (strpos($redirect_url, '?') === false ? '?' : '&') . "action_status=success_delete&message=" . urlencode($success_message);
} else {
    $redirect_url .= (strpos($redirect_url, '?') === false ? '?' : '&') . "action_status=error&message=" . urlencode($error_message ?: "خطای نامشخص در عملیات حذف پاسخ.");
}

header("Location: " . $redirect_url);
exit;
?>
