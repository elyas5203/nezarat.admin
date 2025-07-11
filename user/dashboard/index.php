<?php
// user/dashboard/index.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, helpers, auth check

$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? 'کاربر';
$user_type_display = ''; // You can map UserType to a display name if needed

switch ($_SESSION['user_type'] ?? '') {
    case 'teacher': $user_type_display = 'مدرس'; break;
    case 'member': $user_type_display = 'عضو بخش'; break;
    case 'manager': $user_type_display = 'مدیر'; break;
    case 'deputy': $user_type_display = 'معاون'; break;
    default: $user_type_display = 'کاربر'; break;
}

?>

<div class="page-header">
    <h1>داشبورد کاربری</h1>
    <p class="page-subtitle">سلام <?php echo htmlspecialchars($username); ?> (<?php echo htmlspecialchars($user_type_display); ?>)، به پنل کاربری خود در سامانه دبستان خوش آمدید.</p>
</div>

<div class="dashboard-widgets-grid user-widgets">
    <div class="widget widget-profile">
        <div class="widget-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
        </div>
        <div class="widget-content">
            <h3>پروفایل من</h3>
            <p class="widget-data-small">اطلاعات کاربری و تنظیمات حساب</p>
        </div>
        <a href="<?php echo $user_base_url; ?>/profile/index.php" class="widget-link">مشاهده پروفایل &rarr;</a>
    </div>

    <div class="widget widget-forms">
        <div class="widget-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>
        </div>
        <div class="widget-content">
            <h3>فرم‌های من</h3>
            <p class="widget-data-small">دسترسی به فرم‌های خوداظهاری و ...</p>
        </div>
        <a href="<?php echo $user_base_url; ?>/forms/index.php" class="widget-link">مشاهده فرم‌ها &rarr;</a>
    </div>

    <div class="widget widget-tasks">
        <div class="widget-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path></svg>
        </div>
        <div class="widget-content">
            <h3>وظایف من</h3>
            <p class="widget-data-small">
                <?php
                // Example: Fetch pending tasks for the user
                $pending_tasks = 0;
                if ($user_id) {
                    $stmt_tasks = $conn->prepare("SELECT COUNT(TaskID) as count FROM Tasks WHERE AssignedToUserID = ? AND Status = 'pending'");
                    if($stmt_tasks){
                        $stmt_tasks->bind_param("i", $user_id);
                        $stmt_tasks->execute();
                        $task_result = $stmt_tasks->get_result()->fetch_assoc();
                        $pending_tasks = $task_result['count'] ?? 0;
                        $stmt_tasks->close();
                    }
                }
                echo $pending_tasks . " وظیفه در انتظار";
                ?>
            </p>
        </div>
        <a href="<?php echo $user_base_url; ?>/tasks/index.php" class="widget-link">مشاهده وظایف &rarr;</a>
    </div>

    <div class="widget widget-tickets">
        <div class="widget-icon">
             <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
        </div>
        <div class="widget-content">
            <h3>تیکت‌های پشتیبانی</h3>
            <p class="widget-data-small">
                 <?php
                $open_tickets = 0;
                if ($user_id) {
                    $stmt_tickets = $conn->prepare("SELECT COUNT(TicketID) as count FROM Tickets WHERE CreatedByUserID = ? AND Status = 'open'");
                     if($stmt_tickets){
                        $stmt_tickets->bind_param("i", $user_id);
                        $stmt_tickets->execute();
                        $ticket_result = $stmt_tickets->get_result()->fetch_assoc();
                        $open_tickets = $ticket_result['count'] ?? 0;
                        $stmt_tickets->close();
                    }
                }
                echo $open_tickets . " تیکت باز";
                ?>
            </p>
        </div>
        <a href="<?php echo $user_base_url; ?>/tickets/index.php" class="widget-link">مشاهده تیکت‌ها &rarr;</a>
    </div>
</div>

<div class="content-section">
    <h2>اعلانات و پیام‌های مهم</h2>
    <div class="notifications-preview">
        <?php
        // Example: Fetch recent notifications for the user
        if ($user_id) {
            $stmt_notif = $conn->prepare("SELECT Message, CreatedAt, Link FROM Notifications WHERE UserID = ? AND IsRead = FALSE ORDER BY CreatedAt DESC LIMIT 3");
            if ($stmt_notif) {
                $stmt_notif->bind_param("i", $user_id);
                $stmt_notif->execute();
                $notif_result = $stmt_notif->get_result();
                if ($notif_result->num_rows > 0) {
                    echo "<ul>";
                    while ($notification = $notif_result->fetch_assoc()) {
                        echo "<li>";
                        echo "<span class='notif-time'>" . to_jalali($notification['CreatedAt'], 'yyyy/MM/dd HH:mm') . "</span> - ";
                        echo htmlspecialchars($notification['Message']);
                        if (!empty($notification['Link'])) {
                            echo " <a href='" . htmlspecialchars($notification['Link']) . "' class='notif-link'>مشاهده</a>";
                        }
                        echo "</li>";
                    }
                    echo "</ul>";
                } else {
                    echo "<p>پیام جدیدی برای شما وجود ندارد.</p>";
                }
                $stmt_notif->close();
            } else {
                 echo "<p>خطا در بارگذاری اعلانات.</p>";
            }
        }
        ?>
    </div>
    <a href="<?php echo $user_base_url; ?>/notifications/index.php" class="view-all-notifications">مشاهده همه اعلانات</a>
</div>


<?php
require_once __DIR__ . '/../includes/footer.php';
?>
