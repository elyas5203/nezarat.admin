<?php
// admin/monitoring/index.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth

// Fetch all active classes with teacher information and parents' meeting status
$stmt_classes_admin = $conn->prepare("
    SELECT
        c.ClassID, c.ClassName, c.GradeLevel, c.AcademicYear,
        CONCAT(u.FirstName, ' ', u.LastName) AS TeacherName, u.UserID AS TeacherUserID,
        (SELECT COUNT(*) FROM FormSubmissions fs JOIN Forms f ON fs.FormID = f.FormID WHERE fs.ClassID = c.ClassID AND f.FormPurpose = 'self_assessment') as SelfAssessmentCount,
        (SELECT COUNT(*) FROM FormSubmissions fs JOIN Forms f ON fs.FormID = f.FormID WHERE fs.ClassID = c.ClassID AND f.FormPurpose = 'class_observation') as ObservationCount,
        pm_latest.MeetingDate AS LastParentsMeetingDateOverall,
        pm_latest.Status AS LastParentsMeetingStatus,
        pm_latest.MeetingID AS LastParentsMeetingID,
        (SELECT COUNT(DISTINCT fs_pm_t.SubmissionID)
            FROM FormSubmissions fs_pm_t
            JOIN Forms f_pm_t ON fs_pm_t.FormID = f_pm_t.FormID
            WHERE fs_pm_t.MeetingID = pm_latest.MeetingID AND f_pm_t.FormPurpose = 'parents_meeting_teacher_report') AS LastTeacherReportCount,
        (SELECT COUNT(DISTINCT fs_pm_o.SubmissionID)
            FROM FormSubmissions fs_pm_o
            JOIN Forms f_pm_o ON fs_pm_o.FormID = f_pm_o.FormID
            WHERE fs_pm_o.MeetingID = pm_latest.MeetingID AND f_pm_o.FormPurpose = 'parents_meeting_observer_report') AS LastObserverReportCount
    FROM Classes c
    LEFT JOIN Users u ON c.TeacherUserID = u.UserID
    LEFT JOIN Meetings pm_latest ON pm_latest.MeetingID = (
            SELECT MAX(pm_inner.MeetingID)
            FROM Meetings pm_inner
            WHERE pm_inner.ClassID = c.ClassID AND pm_inner.MeetingType = 'parents_meeting'
            -- Consider filtering by current/relevant academic year for pm_latest for more accurate status
            -- AND pm_inner.AcademicYear = c.AcademicYear
        )
    WHERE c.IsActive = TRUE
    GROUP BY c.ClassID, c.ClassName, c.GradeLevel, c.AcademicYear, TeacherName, TeacherUserID, pm_latest.MeetingDate, pm_latest.Status, pm_latest.MeetingID
    ORDER BY c.AcademicYear DESC, c.GradeLevel ASC, c.ClassName ASC
");


$all_classes_monitoring = [];
if ($stmt_classes_admin) {
    $stmt_classes_admin->execute();
    $result_classes_admin = $stmt_classes_admin->get_result();
    while ($row = $result_classes_admin->fetch_assoc()) {
        $all_classes_monitoring[] = $row;
    }
    $stmt_classes_admin->close();
} else {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا در بارگذاری لیست کلاس‌ها: ' . $conn->error];
}
?>
<div class="page-header">
    <h1>نظارت بر کلاس‌ها و گزارش‌ها</h1>
    <p class="page-subtitle">وضعیت کلی کلاس‌ها، خوداظهاری‌ها، بازدیدها و جلسات اولیا.</p>
     <div class="page-header-actions">
        <a href="<?php echo $admin_base_url; ?>/classes/index.php" class="btn btn-info">
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
            <span>مدیریت کلاس‌ها</span>
        </a>
        <a href="<?php echo $admin_base_url; ?>/parents/meetings.php" class="btn btn-primary">
             <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            <span>مدیریت جلسات اولیا</span>
        </a>
    </div>
</div>

<?php
if (isset($_SESSION['flash_message'])) {
    $flash_mon_idx_main = $_SESSION['flash_message'];
    echo "<div class='alert alert-{$flash_mon_idx_main['type']} alert-dismissible fade show' role='alert'>{$flash_mon_idx_main['text']}<button type='button' class='close' data-dismiss='alert' aria-label='Close' style='background:none; border:none; font-size:1.5rem; position:absolute; top:0; left:0; padding: 0.75rem 1.25rem;'><span aria-hidden='true'>&times;</span></button></div>";
    unset($_SESSION['flash_message']);
    echo "<script>setTimeout(function() {let alert = document.querySelector('.alert-dismissible.show'); if(alert){ if(typeof(bootstrap) !== 'undefined' && bootstrap.Alert && bootstrap.Alert.getInstance(alert)) { bootstrap.Alert.getInstance(alert).close(); } else { alert.style.display = 'none'; }}}, 7000);</script>";
}
?>

<div class="card shadow-sm">
    <div class="card-header">
        <span class="card-title-text">وضعیت کلی کلاس‌ها</span>
    </div>
    <div class="card-body">
        <?php if (!empty($all_classes_monitoring)): ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped table-sm admin-monitoring-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>کلاس</th>
                            <th>پایه</th>
                            <th>سال</th>
                            <th>مدرس</th>
                            <th class="text-center">وضعیت جلسه اولیا</th>
                            <th class="text-center Tooltip-Top" data-tooltip="تعداد خوداظهاری مدرس">خ.م</th>
                            <th class="text-center Tooltip-Top" data-tooltip="تعداد بازدید کلاسی">ب.ک</th>
                            <th class="actions-column">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $class_mon_row_main = 1; foreach ($all_classes_monitoring as $class_item_main):
                            $pm_status_badge_main = '<span class="badge badge-danger Tooltip-Top" data-tooltip="جلسه اولیا برگزار نشده یا نیاز به برنامه‌ریزی دارد">برگزار نشده</span>';
                            $pm_details_main = 'آخرین جلسه: -';

                            $current_academic_year_parts = explode('-', $class_item_main['AcademicYear'] ?? '');
                            $class_start_academic_year = $current_academic_year_parts[0] ?? null;


                            if ($class_item_main['LastParentsMeetingDateOverall']) {
                                $last_meeting_date_obj_main = new DateTime($class_item_main['LastParentsMeetingDateOverall']);
                                $last_meeting_status_main = $class_item_main['LastParentsMeetingStatus'];
                                $last_meeting_date_jalali_main = to_jalali($class_item_main['LastParentsMeetingDateOverall'], 'yyyy/MM/dd');

                                $is_meeting_relevant_for_current_status = false;
                                if ($class_start_academic_year) {
                                    // This is a simplified check. A robust check would convert Jalali academic year to a Gregorian range.
                                    // For example, 1403-1404 roughly corresponds to Sept 2024 - June 2025.
                                    // Let's assume if meeting year matches the start of academic year, it's relevant.
                                    $meeting_gregorian_year = $last_meeting_date_obj_main->format('Y');
                                    // This comparison is very rough and needs a proper Jalali to Gregorian conversion for the academic year range.
                                    // For now, we'll assume meetings are relevant if they exist.
                                    $is_meeting_relevant_for_current_status = true;
                                }


                                if ($last_meeting_status_main == 'completed' && $is_meeting_relevant_for_current_status) {
                                    $teacher_report_ok_main = ($class_item_main['LastTeacherReportCount'] ?? 0) > 0;
                                    $observer_report_ok_main = ($class_item_main['LastObserverReportCount'] ?? 0) > 0;

                                    if ($teacher_report_ok_main && $observer_report_ok_main) {
                                        $pm_status_badge_main = '<span class="badge badge-success Tooltip-Top" data-tooltip="جلسه برگزار شده و گزارشات کامل است">کامل</span>';
                                    } elseif ($teacher_report_ok_main || $observer_report_ok_main) {
                                        $pm_status_badge_main = '<span class="badge badge-warning Tooltip-Top" data-tooltip="جلسه برگزار شده اما گزارشات ناقص است">ناقص</span>';
                                    } else {
                                         $pm_status_badge_main = '<span class="badge badge-secondary Tooltip-Top" data-tooltip="جلسه برگزار شده اما گزارشی ثبت نشده">بدون گزارش</span>';
                                    }
                                    $pm_details_main = 'آخرین: ' . $last_meeting_date_jalali_main;
                                } elseif (in_array($last_meeting_status_main, ['planned', 'confirmed']) && $is_meeting_relevant_for_current_status) {
                                    $meeting_date_obj_check_main = new DateTime($class_item_main['LastParentsMeetingDateOverall']);
                                    if ($meeting_date_obj_check_main >= (new DateTime())->modify('-7 days') ) {
                                        $pm_status_badge_main = '<span class="badge badge-info Tooltip-Top" data-tooltip="جلسه برای آینده برنامه‌ریزی شده">برنامه‌ریزی شده</span>';
                                        $pm_details_main = 'تاریخ آتی: ' . $last_meeting_date_jalali_main;
                                    } else {
                                        $pm_status_badge_main = '<span class="badge badge-danger Tooltip-Top" data-tooltip="جلسه برنامه‌ریزی شده بود اما تاریخ آن گذشته و وضعیت بروز نشده">گذشته</span>';
                                        $pm_details_main = 'برنامه‌ریزی برای: ' . $last_meeting_date_jalali_main;
                                    }
                                } elseif ($last_meeting_status_main == 'cancelled' && $is_meeting_relevant_for_current_status) {
                                    $pm_status_badge_main = '<span class="badge badge-light text-dark border Tooltip-Top" data-tooltip="آخرین جلسه برنامه‌ریزی شده لغو گردید">لغو شده</span>';
                                     $pm_details_main = 'لغو شده در: ' . $last_meeting_date_jalali_main;
                                }
                            }
                        ?>
                            <tr>
                                <td><?php echo $class_mon_row_main++; ?></td>
                                <td><strong><?php echo htmlspecialchars($class_item_main['ClassName']); ?></strong></td>
                                <td><?php echo htmlspecialchars($class_item_main['GradeLevel'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($class_item_main['AcademicYear'] ?? '-'); ?></td>
                                <td><?php if ($class_item_main['TeacherName'] && trim($class_item_main['TeacherName'])!=='') echo htmlspecialchars($class_item_main['TeacherName']); else echo "<small class='text-muted'>-</small>"; ?></td>
                                <td class="text-center"><?php echo $pm_status_badge_main; ?><?php if(!empty($pm_details_main) && $pm_details_main !== 'آخرین جلسه: -'): ?><small class="d-block text-muted"><?php echo $pm_details_main; ?></small><?php endif; ?></td>
                                <td class="text-center"><span class="badge badge-pill badge-light border"><?php echo $class_item_main['SelfAssessmentCount']; ?></span></td>
                                <td class="text-center"><span class="badge badge-pill badge-light border"><?php echo $class_item_main['ObservationCount']; ?></span></td>
                                <td class="actions-cell">
                                    <a href="class_submissions.php?class_id=<?php echo $class_item_main['ClassID']; ?>" class="btn btn-sm btn-primary Tooltip-Top" data-tooltip="مشاهده تمام گزارش‌های این کلاس"><svg class="icon" width="16" height="16" viewBox="0 0 24 24"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg> <span>گزارش‌ها</span></a>
                                    <a href="<?php echo $admin_base_url; ?>/parents/meetings.php?class_filter_id=<?php echo $class_item_main['ClassID']; ?>" class="btn btn-sm btn-outline-secondary mt-1 Tooltip-Top" data-tooltip="مدیریت جلسات اولیای این کلاس"><svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg> <span>جلسات</span></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?> <div class="alert alert-info mb-0">کلاسی برای نمایش یافت نشد. <a href="<?php echo $admin_base_url; ?>/classes/create.php" class="alert-link">یک کلاس جدید ایجاد کنید</a>.</div> <?php endif; ?>
    </div>
</div>
<style>
    .admin-monitoring-table th, .admin-monitoring-table td { font-size: 0.88rem; vertical-align: middle; }
    .admin-monitoring-table .badge {font-size: 0.8rem; padding: 0.35em 0.55em;}
    .Tooltip-Top { position: relative; }
    .Tooltip-Top::after { content: attr(data-tooltip); position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); background-color: #333; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; white-space: nowrap; opacity: 0; visibility: hidden; transition: opacity 0.2s, visibility 0.2s; margin-bottom: 5px; z-index:100;}
    .Tooltip-Top:hover::after { opacity: 1; visibility: visible; }
</style>
<script> /* JS for alert dismissal ... */
    document.querySelectorAll('.alert .close').forEach(function(button) {
        button.addEventListener('click', function(event) {
            let alertNode = event.target.closest('.alert');
            if(alertNode) {
                if (typeof(bootstrap) !== 'undefined' && bootstrap.Alert && bootstrap.Alert.getInstance(alertNode)) {
                    bootstrap.Alert.getInstance(alertNode).close();
                } else {
                    alertNode.style.display = 'none';
                }
            }
        });
    });
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
```

**بخش کاربر (مدرس/ناظر) برای ثبت گزارش جلسه اولیا:**
این بخش نیاز به ایجاد رابط کاربری مناسب دارد تا مدرس یا ناظر بتواند به فرم مربوطه دسترسی پیدا کند.

*   **برای مدرس:** در `user/monitoring/index.php` (لیست کلاس‌ها) یا `user/monitoring/assessment_history.php` (تاریخچه خوداظهاری)، می‌توان لیستی از جلسات اولیای "انجام شده" برای کلاس‌هایش نمایش داد. اگر فرم "گزارش مدرس از جلسه اولیا" (`FormPurpose='parents_meeting_teacher_report'`) برای آن جلسه هنوز توسط او پر نشده باشد، لینک به `user/forms/fill.php?form_id=FORM_ID_TEACHER_REPORT&meeting_id=MEETING_ID&class_id=CLASS_ID&source=parents_module` نمایش داده می‌شود.
    *   `FORM_ID_TEACHER_REPORT` باید از دیتابیس (بر اساس `FormPurpose`) یا تنظیمات خوانده شود.

*   **برای ناظر:**
    *   نیاز به تعریف نقش "ناظر جلسه اولیا" یا یک سیستم تخصیص وظیفه برای نظارت بر یک جلسه خاص به یک کاربر.
    *   پس از تخصیص، ناظر در پنل خود (مثلاً در داشبورد یا بخش وظایف) لیستی از جلساتی که باید نظارت کند را مشاهده می‌کند.
    *   پس از اتمام جلسه، لینک تکمیل فرم `parents_meeting_observer_report` برای او فعال می‌شود: `user/forms/fill.php?form_id=FORM_ID_OBSERVER_REPORT&meeting_id=MEETING_ID&class_id=CLASS_ID&source=parents_module`.

پیاده‌سازی کامل این بخش‌های کاربری (مخصوصاً برای ناظر) ممکن است نیاز به طراحی دقیق‌تر جریان کاری داشته باشد و در مراحل بعدی و با جزئیات بیشتر انجام خواهد شد. فعلاً زیرساخت اصلی (فرم‌ساز، ذخیره `MeetingID` و `ClassID` در پاسخ‌ها، و صفحه مدیریت جلسات توسط ادمین) آماده شده است.

با این توضیحات، مرحله اول ماژول اولیا (بخش ادمین و آماده‌سازی اولیه برای بخش کاربر) را تکمیل شده در نظر می‌گیرم.
