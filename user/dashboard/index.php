<?php
require_once __DIR__ . '/../includes/header.php';

$user_id = $_SESSION['user_id'] ?? null;
$username_display = $_SESSION['username'] ?? 'کاربر'; // Renamed to avoid conflict if $username is fetched from DB
$session_user_type = $_SESSION['user_type'] ?? ''; // e.g., 'teacher', 'member', etc.

$user_display_role = 'کاربر'; // Default display role

// Fetch user's full name and potentially more specific roles if needed
$user_full_name = $username_display;
if ($conn && $user_id) {
    $stmt_user_info = $conn->prepare("SELECT FirstName, LastName FROM Users WHERE UserID = ?");
    if ($stmt_user_info) {
        $stmt_user_info->bind_param("i", $user_id);
        $stmt_user_info->execute();
        $user_info_res = $stmt_user_info->get_result();
        if ($user_info_data = $user_info_res->fetch_assoc()) {
            $user_full_name = trim($user_info_data['FirstName'] . ' ' . $user_info_data['LastName']);
        }
        $stmt_user_info->close();
    }

    // Fetch specific roles to display (could be multiple)
    $stmt_user_roles = $conn->prepare("SELECT r.RoleName FROM UserRoles ur JOIN Roles r ON ur.RoleID = r.RoleID WHERE ur.UserID = ?");
    if ($stmt_user_roles) {
        $stmt_user_roles->bind_param("i", $user_id);
        $stmt_user_roles->execute();
        $user_roles_res = $stmt_user_roles->get_result();
        $role_names_arr = [];
        while($role_row = $user_roles_res->fetch_assoc()){
            $role_names_arr[] = $role_row['RoleName'];
        }
        if(!empty($role_names_arr)){
            $user_display_role = implode('، ', $role_names_arr);
        }
        $stmt_user_roles->close();
    }
}


$stats_user = [
    'pending_tasks' => 0,
    'open_tickets_user' => 0,
    'unread_notifications_user' => 0,
    'pending_self_assessment' => false, // Example for teacher
    'next_inservice_event_date' => null // Example for teacher
];

if ($conn && $user_id) {
    try {
        // Pending Tasks
        $stmt_tasks = $conn->prepare("SELECT COUNT(TaskID) as count FROM Tasks WHERE AssignedToUserID = ? AND Status = 'pending'"); // Assuming Tasks table
        if($stmt_tasks){
            $stmt_tasks->bind_param("i", $user_id);
            $stmt_tasks->execute();
            $stats_user['pending_tasks'] = $stmt_tasks->get_result()->fetch_assoc()['count'] ?? 0;
            $stmt_tasks->close();
        }

        // Open Tickets created by user
        $stmt_tickets_user = $conn->prepare("SELECT COUNT(TicketID) as count FROM Tickets WHERE CreatedByUserID = ? AND (Status = 'open' OR Status = 'pending_reply')"); // Assuming Tickets table
        if($stmt_tickets_user){
            $stmt_tickets_user->bind_param("i", $user_id);
            $stmt_tickets_user->execute();
            $stats_user['open_tickets_user'] = $stmt_tickets_user->get_result()->fetch_assoc()['count'] ?? 0;
            $stmt_tickets_user->close();
        }

        // Unread Notifications
        $stmt_unread_notif = $conn->prepare("SELECT COUNT(NotificationID) as count FROM Notifications WHERE UserID = ? AND IsRead = FALSE");
        if($stmt_unread_notif){
            $stmt_unread_notif->bind_param("i", $user_id);
            $stmt_unread_notif->execute();
            $stats_user['unread_notifications_user'] = $stmt_unread_notif->get_result()->fetch_assoc()['count'] ?? 0;
            $stmt_unread_notif->close();
        }

        // Example: Check if self-assessment is due (for teachers)
        // This requires knowing the FormID for self-assessment and checking FormSubmissions
        // $is_teacher = (strpos(strtolower($user_display_role), 'مدرس') !== false);
        // if ($is_teacher) {
        //    $stats_user['pending_self_assessment'] = true; // Placeholder logic
        // }

    } catch (Exception $e) {
        error_log("User dashboard data fetch error: " . $e->getMessage());
        // Set relevant stats to 'خطا'
    }
}
?>

<div class="page-header">
    <h1>داشبورد کاربری</h1>
    <p class="page-subtitle">سلام <?php echo htmlspecialchars($user_full_name); ?> (نقش: <?php echo htmlspecialchars($user_display_role); ?>)، به پنل کاربری خود در سامانه دبستان خوش آمدید.</p>
</div>

<div class="dashboard-widgets-grid user-widgets">
    <div class="widget widget-profile">
        <div class="widget-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </div>
        <div class="widget-content">
            <h3>پروفایل من</h3>
            <p class="widget-data-small">ویرایش اطلاعات و تغییر رمز عبور.</p>
        </div>
        <a href="<?php echo $user_base_url; ?>/profile/index.php" class="widget-link">مشاهده پروفایل &rarr;</a>
    </div>

    <div class="widget widget-forms">
        <div class="widget-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
        </div>
        <div class="widget-content">
            <h3>فرم‌های من</h3>
            <p class="widget-data-small">دسترسی به فرم‌های خوداظهاری، بازدید و ...</p>
        </div>
        <a href="<?php echo $user_base_url; ?>/forms/index.php" class="widget-link">مشاهده فرم‌ها &rarr;</a>
    </div>

    <div class="widget widget-tasks">
        <div class="widget-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/></svg>
        </div>
        <div class="widget-content">
            <h3>وظایف من</h3>
            <p class="widget-data"><?php echo $stats_user['pending_tasks']; ?></p>
            <p class="widget-data-small">وظیفه در انتظار اقدام</p>
        </div>
        <a href="<?php echo $user_base_url; ?>/content/my_tasks.php" class="widget-link">مشاهده وظایف &rarr;</a>
    </div>

    <div class="widget widget-tickets">
        <div class="widget-icon">
             <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V14M14 4h6m-3-3v6m-4 5h4m-4 4h4m-9-5h.01M7 12h.01"/></svg>
        </div>
        <div class="widget-content">
            <h3>تیکت‌های شما</h3>
             <p class="widget-data"><?php echo $stats_user['open_tickets_user']; ?></p>
            <p class="widget-data-small">تیکت باز یا در انتظار پاسخ</p>
        </div>
        <a href="<?php echo $user_base_url; ?>/tickets/index.php" class="widget-link">مشاهده تیکت‌ها &rarr;</a>
    </div>

    <!-- Example Role-Specific Widget: Self-Assessment Reminder for Teachers -->
    <?php if (strpos(strtolower($user_display_role), 'مدرس') !== false && $stats_user['pending_self_assessment']): ?>
    <div class="widget widget-reminder" style="background-color: #fff3cd;"> <!-- Yellowish background for reminder -->
        <div class="widget-icon" style="color: #856404;">
             <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <div class="widget-content">
            <h3 style="color: #856404;">یادآوری مهم</h3>
            <p class="widget-data-small">لطفاً فرم خوداظهاری این هفته را تکمیل نمایید.</p>
        </div>
        <a href="<?php echo $user_base_url; ?>/monitoring/submit_self_assessment.php" class="widget-link" style="color: #856404;">ثبت فرم خوداظهاری &rarr;</a>
    </div>
    <?php endif; ?>

    <!-- Example: Next In-service Training Session -->
    <?php if ($stats_user['next_inservice_event_date']): ?>
    <div class="widget widget-inservice-next">
        <div class="widget-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 3v7m10-7v7M7 14h.01M12 14h.01M17 14h.01M7 18h.01M12 18h.01M17 18h.01M3 6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6z"/></svg>
        </div>
        <div class="widget-content">
            <h3>جلسه ضمن خدمت بعدی</h3>
            <p class="widget-data-small">تاریخ: <?php echo htmlspecialchars($stats_user['next_inservice_event_date']); ?></p>
        </div>
        <a href="<?php echo $user_base_url; ?>/inservice/my_schedule.php" class="widget-link">مشاهده برنامه &rarr;</a>
    </div>
    <?php endif; ?>

</div>

<div class="content-section">
    <h2>اعلانات و پیام‌های مهم (<?php echo $stats_user['unread_notifications_user']; ?> خوانده نشده)</h2>
    <div class="notifications-preview">
        <?php
        if ($conn && $user_id) {
            $stmt_notif_user = $conn->prepare("SELECT NotificationID, Message, CreatedAt, Link, IsRead FROM Notifications WHERE UserID = ? ORDER BY IsRead ASC, CreatedAt DESC LIMIT 5");
            if ($stmt_notif_user) {
                $stmt_notif_user->bind_param("i", $user_id);
                $stmt_notif_user->execute();
                $notif_result_user = $stmt_notif_user->get_result();
                if ($notif_result_user->num_rows > 0) {
                    echo "<ul class='list-group list-group-flush'>";
                    while ($notification = $notif_result_user->fetch_assoc()) {
                        $notif_link = !empty($notification['Link']) ? htmlspecialchars($notification['Link']) . (strpos($notification['Link'], '?') === false ? '?' : '&') . 'notif_id=' . $notification['NotificationID'] : '#';
                        echo "<li class='list-group-item " . (!$notification['IsRead'] ? "list-group-item-info" : "") . "'>";
                        echo "<small class='text-muted float-end'>" . to_jalali($notification['CreatedAt'], 'yy/MM/dd HH:mm') . "</small>";
                        echo "<strong>" . htmlspecialchars(mb_substr($notification['Message'], 0, 80)) . (mb_strlen($notification['Message']) > 80 ? '...' : '') . "</strong>";
                        if (!empty($notification['Link'])) {
                            echo " <a href='" . $notif_link . "' class='notif-link stretched-link ms-2'>مشاهده</a>";
                        }
                        if (!$notification['IsRead']) {
                            echo " <span class='badge bg-primary rounded-pill ms-2'>جدید</span>";
                        }
                        echo "</li>";
                    }
                    echo "</ul>";
                } else {
                    echo "<p class='text-muted'>پیام جدیدی برای شما وجود ندارد.</p>";
                }
                $stmt_notif_user->close();
            } else {
                 echo "<p class='text-danger'>خطا در بارگذاری اعلانات.</p>";
            }
        }
        ?>
    </div>
    <a href="<?php echo $user_base_url; ?>/notifications/index.php" class="view-all-notifications d-block mt-3">مشاهده همه اعلانات</a>
</div>


<?php
require_once __DIR__ . '/../includes/footer.php';
?>
