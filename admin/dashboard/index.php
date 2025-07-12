<?php
require_once __DIR__ . '/../includes/header.php';

// Fetch data for dashboard widgets
$stats = [
    'active_users' => 0,
    'departments' => 0,
    'forms_created' => 0,
    'total_classes' => 0,
    'self_assessments_week' => 0,
    'observations_month' => 0,
    'open_tickets' => 0,
    'admin_last_login' => 'نامشخص'
];

if ($conn) {
    try {
        // Active Users (excluding admin)
        $stmt_users = $conn->query("SELECT COUNT(UserID) as count FROM Users WHERE IsActive = TRUE AND UserType != 'admin'");
        $stats['active_users'] = $stmt_users ? ($stmt_users->fetch_assoc()['count'] ?? 0) : 'خطا';

        // Departments
        $stmt_depts = $conn->query("SELECT COUNT(DepartmentID) as count FROM Departments");
        $stats['departments'] = $stmt_depts ? ($stmt_depts->fetch_assoc()['count'] ?? 0) : 'خطا';

        // Forms Created
        $stmt_forms = $conn->query("SELECT COUNT(FormID) as count FROM Forms");
        $stats['forms_created'] = $stmt_forms ? ($stmt_forms->fetch_assoc()['count'] ?? 0) : 'خطا';

        // Total Classes
        $stmt_classes = $conn->query("SELECT COUNT(ClassID) as count FROM Classes"); // Assuming table name is 'Classes'
        $stats['total_classes'] = $stmt_classes ? ($stmt_classes->fetch_assoc()['count'] ?? 0) : 'خطا';

        // Self-assessments in the last week (Assuming FormSubmissions table and a way to identify self-assessment forms)
        // This is a placeholder query, actual implementation depends on Form and FormSubmission structure
        // $one_week_ago = date('Y-m-d H:i:s', strtotime('-7 days'));
        // $stmt_self_assess = $conn->prepare("SELECT COUNT(fs.SubmissionID) as count FROM FormSubmissions fs JOIN Forms f ON fs.FormID = f.FormID WHERE f.FormType = 'self_assessment' AND fs.SubmittedAt >= ?");
        // if($stmt_self_assess) { $stmt_self_assess->bind_param("s", $one_week_ago); $stmt_self_assess->execute(); $stats['self_assessments_week'] = $stmt_self_assess->get_result()->fetch_assoc()['count'] ?? 0; $stmt_self_assess->close(); }
        // else { $stats['self_assessments_week'] = 'خطا';}
        $stats['self_assessments_week'] = "ن/م"; // Placeholder for now: نیازمند مدل داده فرم‌ها

        // Observations in the last month (Similar placeholder)
        // $one_month_ago = date('Y-m-d H:i:s', strtotime('-1 month'));
        // $stmt_obs = $conn->prepare("SELECT COUNT(fs.SubmissionID) as count FROM FormSubmissions fs JOIN Forms f ON fs.FormID = f.FormID WHERE f.FormType = 'observation' AND fs.SubmittedAt >= ?");
        // if($stmt_obs) { $stmt_obs->bind_param("s", $one_month_ago); $stmt_obs->execute(); $stats['observations_month'] = $stmt_obs->get_result()->fetch_assoc()['count'] ?? 0; $stmt_obs->close(); }
        // else { $stats['observations_month'] = 'خطا'; }
        $stats['observations_month'] = "ن/م"; // Placeholder

        // Open Tickets (Assuming a Tickets table with a status column)
        // $stmt_tickets = $conn->query("SELECT COUNT(TicketID) as count FROM Tickets WHERE Status = 'open' OR Status = 'pending_reply'");
        // $stats['open_tickets'] = $stmt_tickets ? ($stmt_tickets->fetch_assoc()['count'] ?? 0) : 'خطا';
        $stats['open_tickets'] = "ن/م"; // Placeholder

        // Admin Last Login
        $admin_user_id_for_login = $_SESSION['admin_user_id'] ?? null;
        if ($admin_user_id_for_login) {
            $stmt_login = $conn->prepare("SELECT LastLogin FROM Users WHERE UserID = ?");
            if ($stmt_login) {
                $stmt_login->bind_param("i", $admin_user_id_for_login);
                $stmt_login->execute();
                $login_result = $stmt_login->get_result()->fetch_assoc();
                $stats['admin_last_login'] = $login_result && $login_result['LastLogin'] ? to_jalali($login_result['LastLogin'], 'yyyy/MM/dd HH:mm') : 'نامشخص';
                $stmt_login->close();
            }
        }
    } catch (Exception $e) {
        // Log error and set stats to 'خطا' or a default
        error_log("Dashboard data fetch error: " . $e->getMessage());
        foreach ($stats as $key => $value) { if ($value === 0) $stats[$key] = 'خطا'; } // Only overwrite if not already set
    }
} else {
    foreach ($stats as $key => $value) { $stats[$key] = 'خطا در اتصال'; }
}
?>

<div class="page-header">
    <h1>داشبورد ادمین</h1>
    <p class="page-subtitle">به پنل مدیریت سامانه دبستان خوش آمدید. از این بخش می‌توانید به امکانات مختلف مدیریتی دسترسی پیدا کنید.</p>
</div>

<div class="dashboard-widgets-grid">
    <div class="widget widget-users">
        <div class="widget-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div class="widget-content">
            <h3>کاربران فعال</h3>
            <p class="widget-data"><?php echo $stats['active_users']; ?></p>
        </div>
        <a href="<?php echo $admin_base_url; ?>/users/index.php" class="widget-link">مدیریت کاربران &rarr;</a>
    </div>

    <div class="widget widget-departments">
        <div class="widget-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2L2 7l10 5 10-5l-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
        </div>
        <div class="widget-content">
            <h3>تعداد بخش‌ها</h3>
            <p class="widget-data"><?php echo $stats['departments']; ?></p>
        </div>
        <a href="<?php echo $admin_base_url; ?>/departments/index.php" class="widget-link">مدیریت بخش‌ها &rarr;</a>
    </div>

    <div class="widget widget-forms">
        <div class="widget-icon">
             <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
        </div>
        <div class="widget-content">
            <h3>فرم‌های سیستمی</h3>
            <p class="widget-data"><?php echo $stats['forms_created']; ?></p>
        </div>
         <a href="<?php echo $admin_base_url; ?>/forms/index.php" class="widget-link">مدیریت فرم‌ها &rarr;</a>
    </div>

    <div class="widget widget-classes"> <!-- New Widget -->
        <div class="widget-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9l9-7l9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        </div>
        <div class="widget-content">
            <h3>تعداد کل کلاس‌ها</h3>
            <p class="widget-data"><?php echo $stats['total_classes']; ?></p>
        </div>
         <a href="<?php echo $admin_base_url; ?>/classes/index.php" class="widget-link">مدیریت کلاس‌ها &rarr;</a>
    </div>

    <div class="widget widget-self-assessment"> <!-- New Widget -->
        <div class="widget-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v2m-5 11l4-4l-4-4m4 4H7"/></svg>
        </div>
        <div class="widget-content">
            <h3>خوداظهاری هفته</h3>
            <p class="widget-data"><?php echo $stats['self_assessments_week']; ?></p>
        </div>
         <a href="<?php echo $admin_base_url; ?>/monitoring/index.php?type=self_assessment" class="widget-link">مشاهده گزارشات &rarr;</a>
    </div>

    <div class="widget widget-observations"> <!-- New Widget -->
        <div class="widget-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1 12s4-8 11-8s11 8 11 8s-4 8-11 8s-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </div>
        <div class="widget-content">
            <h3>بازدیدهای ماه</h3>
            <p class="widget-data"><?php echo $stats['observations_month']; ?></p>
        </div>
         <a href="<?php echo $admin_base_url; ?>/monitoring/index.php?type=observation" class="widget-link">مشاهده گزارشات &rarr;</a>
    </div>

    <div class="widget widget-tickets"> <!-- New Widget -->
        <div class="widget-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
        </div>
        <div class="widget-content">
            <h3>تیکت‌های باز</h3>
            <p class="widget-data"><?php echo $stats['open_tickets']; ?></p>
        </div>
         <a href="<?php echo $admin_base_url; ?>/tickets/index.php?status=open" class="widget-link">مدیریت تیکت‌ها &rarr;</a>
    </div>

    <div class="widget widget-last-login">
         <div class="widget-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 22s8-4 8-10V5l-8-3l-8 3v7c0 6 8 10 8 10z"/></svg>
        </div>
        <div class="widget-content">
            <h3>آخرین ورود شما</h3>
            <p class="widget-data-small"><?php echo $stats['admin_last_login']; ?></p>
        </div>
    </div>
</div>

<div class="content-section">
    <h2>خلاصه فعالیت‌های اخیر</h2>
    <p class="text-muted">این بخش در آینده با داده‌های واقعی از سیستم تکمیل خواهد شد.</p>
    <!-- Placeholder for recent activities - Will be implemented with a logging/notification system -->
    <ul class="recent-activities">
        <li><span class="activity-time">1403/04/01 10:30</span> - کاربر 'مدرس نمونه' فرم خوداظهاری کلاس 'پنجم الف' را ثبت کرد. (نمونه)</li>
        <li><span class="activity-time">1403/04/01 09:15</span> - وظیفه جدید "بررسی گزارش جلسه اولیا" به بخش نظارت ارجاع داده شد. (نمونه)</li>
        <li><span class="activity-time">1403/03/30 17:00</span> - کاربر جدید 'علی رضایی' با نقش 'مدرس' توسط ادمین ثبت شد. (نمونه)</li>
    </ul>
</div>

<div class="content-section">
    <h2>دسترسی سریع</h2>
    <div class="quick-access-links">
        <a href="<?php echo $admin_base_url; ?>/users/create.php" class="quick-link">ایجاد کاربر جدید</a>
        <a href="<?php echo $admin_base_url; ?>/forms/create.php" class="quick-link">ایجاد فرم جدید</a>
        <!-- <a href="<?php echo $admin_base_url; ?>/tasks/create.php" class="quick-link">ارجاع وظیفه جدید</a> -->
        <a href="<?php echo $admin_base_url; ?>/inservice/events.php?action=create" class="quick-link">ایجاد رویداد ضمن خدمت</a>
        <a href="<?php echo $admin_base_url; ?>/monitoring/seasonal_report.php" class="quick-link">ثبت گزارش فصلی</a>
        <a href="<?php echo $admin_base_url; ?>/settings/index.php" class="quick-link">تنظیمات سامانه</a>
    </div>
</div>


<?php
require_once __DIR__ . '/../includes/footer.php';
?>
