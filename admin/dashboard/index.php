<?php
// admin/dashboard/index.php
require_once __DIR__ . '/../includes/header.php'; // This will handle session, db, helpers and auth check
?>

<div class="page-header">
    <h1>داشبورد ادمین</h1>
    <p class="page-subtitle">به پنل مدیریت سامانه دبستان خوش آمدید. از این بخش می‌توانید به امکانات مختلف مدیریتی دسترسی پیدا کنید.</p>
</div>

<div class="dashboard-widgets-grid">
    <div class="widget widget-users">
        <div class="widget-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
        </div>
        <div class="widget-content">
            <h3>کاربران فعال</h3>
            <p class="widget-data">
                <?php
                try {
                    $stmt = $conn->query("SELECT COUNT(UserID) as count FROM Users WHERE IsActive = TRUE AND UserType != 'admin'");
                    echo $stmt ? ($stmt->fetch_assoc()['count'] ?? '0') : 'خطا';
                } catch (Exception $e) { echo 'خطا'; }
                ?>
            </p>
        </div>
        <a href="<?php echo $admin_base_url; ?>/users/index.php" class="widget-link">مدیریت کاربران &rarr;</a>
    </div>

    <div class="widget widget-departments">
        <div class="widget-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"></path><path d="M2 17l10 5 10-5"></path><path d="M2 12l10 5 10-5"></path></svg>
        </div>
        <div class="widget-content">
            <h3>تعداد بخش‌ها</h3>
            <p class="widget-data">
                 <?php
                    try {
                        $stmt = $conn->query("SELECT COUNT(DepartmentID) as count FROM Departments");
                        echo $stmt ? ($stmt->fetch_assoc()['count'] ?? '0') : 'خطا';
                    } catch (Exception $e) { echo 'خطا'; }
                ?>
            </p>
        </div>
        <a href="<?php echo $admin_base_url; ?>/departments/index.php" class="widget-link">مدیریت بخش‌ها &rarr;</a>
    </div>

    <div class="widget widget-forms">
        <div class="widget-icon">
             <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
        </div>
        <div class="widget-content">
            <h3>فرم‌های ایجاد شده</h3>
            <p class="widget-data">
                <?php
                    try {
                        $stmt = $conn->query("SELECT COUNT(FormID) as count FROM Forms");
                        echo $stmt ? ($stmt->fetch_assoc()['count'] ?? '0') : 'خطا';
                    } catch (Exception $e) { echo 'خطا'; }
                ?>
            </p>
        </div>
         <a href="<?php echo $admin_base_url; ?>/forms/index.php" class="widget-link">مدیریت فرم‌ها &rarr;</a>
    </div>

    <div class="widget widget-last-login">
         <div class="widget-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
        </div>
        <div class="widget-content">
            <h3>آخرین ورود شما</h3>
            <p class="widget-data-small">
                <?php
                $admin_user_id_for_login = $_SESSION['admin_user_id'] ?? null;
                if ($admin_user_id_for_login) {
                    $stmt_login = $conn->prepare("SELECT LastLogin FROM Users WHERE UserID = ?");
                    if ($stmt_login) {
                        $stmt_login->bind_param("i", $admin_user_id_for_login);
                        $stmt_login->execute();
                        $login_result = $stmt_login->get_result()->fetch_assoc();
                        echo $login_result && $login_result['LastLogin'] ? to_jalali($login_result['LastLogin'], 'yyyy/MM/dd HH:mm') : 'نامشخص';
                        $stmt_login->close();
                    } else {
                        echo "خطا";
                    }
                } else {
                    echo 'نامشخص';
                }
                ?>
            </p>
        </div>
    </div>
</div>

<div class="content-section">
    <h2>خلاصه فعالیت‌های اخیر</h2>
    <p>در این بخش خلاصه‌ای از فعالیت‌های اخیر در سامانه نمایش داده خواهد شد. (مثلاً آخرین فرم‌های پر شده، وظایف جدید و ...)</p>
    <!-- Placeholder for recent activities -->
    <ul class="recent-activities">
        <li><span>1403/04/01 10:30</span> - کاربر 'مدرس نمونه' فرم خوداظهاری کلاس 'پنجم الف' را ثبت کرد.</li>
        <li><span>1403/04/01 09:15</span> - وظیفه جدید "بررسی گزارش جلسه اولیا" به بخش نظارت ارجاع داده شد.</li>
        <li><span>1403/03/30 17:00</span> - کاربر جدید 'علی رضایی' با نقش 'مدرس' توسط ادمین ثبت شد.</li>
    </ul>
</div>

<div class="content-section">
    <h2>دسترسی سریع</h2>
    <div class="quick-access-links">
        <a href="<?php echo $admin_base_url; ?>/users/create.php" class="quick-link">ایجاد کاربر جدید</a>
        <a href="<?php echo $admin_base_url; ?>/forms/create.php" class="quick-link">ایجاد فرم جدید</a>
        <a href="<?php echo $admin_base_url; ?>/tasks/create.php" class="quick-link">ارجاع وظیفه جدید</a>
        <a href="<?php echo $admin_base_url; ?>/settings/index.php" class="quick-link">تنظیمات سامانه</a>
    </div>
</div>


<?php
require_once __DIR__ . '/../includes/footer.php';
?>
