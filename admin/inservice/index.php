<?php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, helpers, auth check

$upcoming_events = [];
$recent_events = [];
$action_error_inservice_index = ''; // Specific error variable for this page

if ($conn) {
    try {
        // Fetch upcoming events (e.g., in the next 30 days or all future events)
        $stmt_upcoming = $conn->prepare(
            "SELECT EventID, EventName, EventDate, StartTime, EndTime, Location, Status
             FROM InserviceEvents
             WHERE EventDate >= CURDATE()
             ORDER BY EventDate ASC, StartTime ASC LIMIT 10" // Limit for dashboard view
        );
        if ($stmt_upcoming && $stmt_upcoming->execute()) {
            $result_upcoming = $stmt_upcoming->get_result();
            while ($row = $result_upcoming->fetch_assoc()) {
                $upcoming_events[] = $row;
            }
            $stmt_upcoming->close();
        } elseif ($stmt_upcoming) {
            $action_error_inservice_index .= "خطا در بارگذاری رویدادهای آتی: " . $stmt_upcoming->error . "<br>";
        } else {
            $action_error_inservice_index .= "خطا در آماده سازی کوئری رویدادهای آتی: " . $conn->error . "<br>";
        }

        // Fetch recently past events (e.g., in the last 14 days)
        $stmt_recent = $conn->prepare(
            "SELECT EventID, EventName, EventDate, Status
             FROM InserviceEvents
             WHERE EventDate < CURDATE() AND EventDate >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
             ORDER BY EventDate DESC LIMIT 5" // Limit for dashboard view
        );
         if ($stmt_recent && $stmt_recent->execute()) {
            $result_recent = $stmt_recent->get_result();
            while ($row = $result_recent->fetch_assoc()) {
                $recent_events[] = $row;
            }
            $stmt_recent->close();
        } elseif ($stmt_recent) {
            $action_error_inservice_index .= "خطا در بارگذاری رویدادهای اخیر: " . $stmt_recent->error . "<br>";
        } else {
             $action_error_inservice_index .= "خطا در آماده سازی کوئری رویدادهای اخیر: " . $conn->error . "<br>";
        }
    } catch (Exception $e) {
        $action_error_inservice_index .= "خطای کلی در بارگذاری داده‌های ضمن خدمت: " . $e->getMessage() . "<br>";
    }
} else {
    $action_error_inservice_index = "خطا در اتصال به پایگاه داده.";
}

// Use session flash messages if they exist, otherwise use local error variable
if (isset($_SESSION['action_error'])) {
    $action_error_inservice_index = $_SESSION['action_error'] . $action_error_inservice_index;
    unset($_SESSION['action_error']);
}
?>

<div class="page-header">
    <h1>بخش ضمن خدمت</h1>
    <p class="page-subtitle">مدیریت رویدادها، چک‌لیست‌ها، حضور و غیاب و محتوای جلسات ضمن خدمت.</p>
    <div class="page-header-actions">
        <a href="events.php?action=create" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-calendar-plus icon" viewBox="0 0 16 16"><path d="M8 7a.5.5 0 0 1 .5.5V9H10a.5.5 0 0 1 0 1H8.5v1.5a.5.5 0 0 1-1 0V10H6a.5.5 0 0 1 0-1h1.5V7.5A.5.5 0 0 1 8 7z"/><path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/></svg>
            ایجاد رویداد جدید
        </a>
        <a href="checklists_templates.php" class="btn btn-outline-secondary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-card-checklist icon" viewBox="0 0 16 16"><path d="M14.5 3a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h13zm-13-1A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h13a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2h-13z"/><path d="M7 5.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5zm-1.496-.854a.5.5 0 0 1 0 .708l-1.5 1.5a.5.5 0 0 1-.708 0l-.5-.5a.5.5 0 1 1 .708-.708l.146.147 1.146-1.147a.5.5 0 0 1 .708 0zM7 9.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5zm-1.496-.854a.5.5 0 0 1 0 .708l-1.5 1.5a.5.5 0 0 1-.708 0l-.5-.5a.5.5 0 0 1 .708-.708l.146.147 1.146-1.147a.5.5 0 0 1 .708 0z"/></svg>
            مدیریت قالب‌های چک‌لیست
        </a>
    </div>
</div>

<?php if (!empty($action_error_inservice_index)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $action_error_inservice_index; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if (isset($_SESSION['action_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['action_success']; unset($_SESSION['action_success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>


<div class="row">
    <div class="col-lg-8 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-calendar-event me-2" viewBox="0 0 16 16"><path d="M11 6.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1z"/><path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/></svg>رویدادهای پیش رو</h5>
            </div>
            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($upcoming_events)): ?>
                    <p class="text-muted mt-3 text-center">هیچ رویداد ضمن خدمتی برای آینده نزدیک برنامه‌ریزی نشده است.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($upcoming_events as $event): ?>
                            <li class="list-group-item px-0">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">
                                        <a href="events.php?action=view&event_id=<?php echo $event['EventID']; ?>">
                                            <?php echo htmlspecialchars($event['EventName']); ?>
                                        </a>
                                        <span class="badge bg-info ms-2"><?php echo htmlspecialchars($event['Status'] ?? 'برنامه‌ریزی شده'); ?></span>
                                    </h6>
                                    <small class="text-muted"><?php echo to_jalali($event['EventDate'], 'dd MMMM yyyy'); ?></small>
                                </div>
                                <p class="mb-1 small text-muted">
                                    <?php
                                    echo htmlspecialchars($event['Location'] ?? 'مکان: نامشخص');
                                    if ($event['StartTime']) {
                                        echo " - ساعت: " . htmlspecialchars(substr($event['StartTime'], 0, 5));
                                        if ($event['EndTime']) echo " تا " . htmlspecialchars(substr($event['EndTime'], 0, 5));
                                    }
                                    ?>
                                </p>
                                <div class="mt-2">
                                    <a href="event_checklists.php?event_id=<?php echo $event['EventID']; ?>" class="btn btn-sm btn-outline-primary">چک‌لیست‌ها</a>
                                    <a href="attendance.php?event_id=<?php echo $event['EventID']; ?>" class="btn btn-sm btn-outline-success ms-1">حضور و غیاب</a>
                                    <a href="content.php?event_id=<?php echo $event['EventID']; ?>" class="btn btn-sm btn-outline-info ms-1">محتوا</a>
                                    <a href="events.php?action=edit&event_id=<?php echo $event['EventID']; ?>" class="btn btn-sm btn-outline-warning ms-1">ویرایش</a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="card-footer text-center py-2">
                 <a href="events.php" class="btn btn-secondary btn-sm">مشاهده و مدیریت همه رویدادها</a>
            </div>
        </div>
    </div>
    <div class="col-lg-4 mb-4">
        <div class="card mb-3 h-100">
            <div class="card-header">
                <h5 class="mb-0"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-calendar-check me-2" viewBox="0 0 16 16"><path d="M10.854 7.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 9.793l2.646-2.647a.5.5 0 0 1 .708 0z"/><path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/></svg>رویدادهای اخیر</h5>
            </div>
            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                 <?php if (empty($recent_events)): ?>
                    <p class="text-muted mt-3 text-center">هیچ رویدادی در ۱۴ روز گذشته برگزار نشده است.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($recent_events as $event): ?>
                            <li class="list-group-item px-0">
                                <a href="events.php?action=view&event_id=<?php echo $event['EventID']; ?>">
                                    <?php echo htmlspecialchars($event['EventName']); ?>
                                </a>
                                <small class="d-block text-muted">
                                    <?php echo to_jalali($event['EventDate'], 'dd MMMM yyyy'); ?> -
                                    وضعیت: <span class="fw-bold"><?php echo htmlspecialchars($event['Status'] ?? 'نامشخص'); ?></span>
                                </small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
     <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-check2-square me-2" viewBox="0 0 16 16"><path d="M3 14.5A1.5 1.5 0 0 1 1.5 13V3A1.5 1.5 0 0 1 3 1.5h8a.5.5 0 0 1 0 1H3a.5.5 0 0 0-.5.5v10a.5.5 0 0 0 .5.5h10a.5.5 0 0 0 .5-.5V8a.5.5 0 0 1 1 0v5a1.5 1.5 0 0 1-1.5 1.5H3z"/><path d="m8.354 10.354l7-7a.5.5 0 0 0-.708-.708L8 9.293L5.354 6.646a.5.5 0 1 0-.708.708l3 3a.5.5 0 0 0 .708 0z"/></svg>چک‌لیست‌های نیازمند اقدام</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">در این بخش، چک‌لیست‌های مربوط به رویدادهای آتی که آیتم‌های باز و انجام نشده دارند، نمایش داده خواهد شد. (نیازمند پیاده‌سازی کامل ماژول چک‌لیست)</p>
                <!-- TODO: Query and display checklists needing action, possibly linking to event_checklists.php -->
                <ul class="list-group">
                    <li class="list-group-item">چک لیست هماهنگی جلسه آموزشی هفته آینده - <span class="badge bg-warning text-dark">۳ آیتم باقی مانده</span> <a href="#" class="btn btn-sm btn-link float-end">مشاهده</a></li>
                    <li class="list-group-item">چک لیست اردوی تفریحی ماه بعد - <span class="badge bg-danger">۵ آیتم باقی مانده</span> <a href="#" class="btn btn-sm btn-link float-end">مشاهده</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>


<?php
require_once __DIR__ . '/../includes/footer.php';
?>
