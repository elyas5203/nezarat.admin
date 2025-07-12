<?php
require_once __DIR__ . '/../includes/header.php'; // General admin header

// This page will serve as the dashboard for the "Omid Tadris" (Hope for Teaching / Teacher Candidacy) module.
// It will display upcoming Omid Tadris events, recent activities, and quick links.

$upcoming_ot_events = [];
$action_error_ot_index = '';

if ($conn) {
    try {
        // Fetch upcoming Omid Tadris events
        $stmt_upcoming_ot = $conn->prepare(
            "SELECT EventID, EventName, EventDate, StartTime, Location, Status
             FROM OmidTadrisEvents
             WHERE EventDate >= CURDATE()
             ORDER BY EventDate ASC, StartTime ASC LIMIT 5" // Show a few upcoming events
        );
        if ($stmt_upcoming_ot && $stmt_upcoming_ot->execute()) {
            $result_upcoming_ot = $stmt_upcoming_ot->get_result();
            while ($row = $result_upcoming_ot->fetch_assoc()) {
                $upcoming_ot_events[] = $row;
            }
            $stmt_upcoming_ot->close();
        } elseif ($stmt_upcoming_ot) {
            $action_error_ot_index .= "خطا در بارگذاری رویدادهای آتی امید تدریس: " . $stmt_upcoming_ot->error . "<br>";
        } else {
            $action_error_ot_index .= "خطا در آماده سازی کوئری رویدادهای آتی امید تدریس: " . $conn->error . "<br>";
        }
    } catch (Exception $e) {
        $action_error_ot_index .= "خطای کلی در بارگذاری داده‌های امید تدریس: " . $e->getMessage() . "<br>";
    }
} else {
    $action_error_ot_index = "خطا در اتصال به پایگاه داده.";
}

if (isset($_SESSION['action_error_ot'])) { // Check for specific session errors for this module
    $action_error_ot_index = $_SESSION['action_error_ot'] . $action_error_ot_index;
    unset($_SESSION['action_error_ot']);
}
?>

<div class="page-header">
    <h1>بخش امید تدریس (پرورش مدرس)</h1>
    <p class="page-subtitle">مدیریت جلسات، چک‌لیست‌ها و حضور و غیاب داوطلبان تدریس.</p>
    <div class="page-header-actions">
        <a href="events.php?action=create" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-calendar-plus icon" viewBox="0 0 16 16"><path d="M8 7a.5.5 0 0 1 .5.5V9H10a.5.5 0 0 1 0 1H8.5v1.5a.5.5 0 0 1-1 0V10H6a.5.5 0 0 1 0-1h1.5V7.5A.5.5 0 0 1 8 7z"/><path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/></svg>
            ایجاد جلسه / رویداد جدید
        </a>
        <a href="checklists_templates.php" class="btn btn-outline-secondary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-card-checklist icon" viewBox="0 0 16 16"><path d="M14.5 3a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h13zm-13-1A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h13a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2h-13z"/><path d="M7 5.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5zm-1.496-.854a.5.5 0 0 1 0 .708l-1.5 1.5a.5.5 0 0 1-.708 0l-.5-.5a.5.5 0 1 1 .708-.708l.146.147 1.146-1.147a.5.5 0 0 1 .708 0zM7 9.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5zm-1.496-.854a.5.5 0 0 1 0 .708l-1.5 1.5a.5.5 0 0 1-.708 0l-.5-.5a.5.5 0 0 1 .708-.708l.146.147 1.146-1.147a.5.5 0 0 1 .708 0z"/></svg>
            مدیریت قالب‌های چک‌لیست (امید تدریس)
        </a>
    </div>
</div>

<?php if (!empty($action_error_ot_index)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $action_error_ot_index; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if (isset($_SESSION['action_success_ot'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['action_success_ot']; unset($_SESSION['action_success_ot']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-calendar-event me-2" viewBox="0 0 16 16"><path d="M11 6.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1z"/><path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/></svg>جلسات و رویدادهای آتی امید تدریس</h5>
            </div>
            <div class="card-body">
                <?php if (empty($upcoming_ot_events)): ?>
                    <p class="text-muted mt-3 text-center">هیچ جلسه یا رویدادی برای امید تدریس در آینده نزدیک برنامه‌ریزی نشده است.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($upcoming_ot_events as $event_ot): ?>
                            <li class="list-group-item px-0">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">
                                        <a href="events.php?action=view&event_id=<?php echo $event_ot['EventID']; ?>">
                                            <?php echo htmlspecialchars($event_ot['EventName']); ?>
                                        </a>
                                        <span class="badge bg-info ms-2"><?php echo htmlspecialchars($event_ot['Status'] ?? 'برنامه‌ریزی شده'); ?></span>
                                    </h6>
                                    <small class="text-muted"><?php echo to_jalali($event_ot['EventDate'], 'dd MMMM yyyy'); ?></small>
                                </div>
                                <p class="mb-1 small text-muted">
                                    <?php
                                    echo htmlspecialchars($event_ot['Location'] ?? 'مکان: نامشخص');
                                    if ($event_ot['StartTime']) {
                                        echo " - ساعت: " . htmlspecialchars(substr($event_ot['StartTime'], 0, 5));
                                    }
                                    ?>
                                </p>
                                <div class="mt-2">
                                    <a href="event_checklists.php?event_id=<?php echo $event_ot['EventID']; ?>" class="btn btn-sm btn-outline-primary">چک‌لیست‌ها</a>
                                    <a href="attendance.php?event_id=<?php echo $event_ot['EventID']; ?>" class="btn btn-sm btn-outline-success ms-1">حضور و غیاب داوطلبان</a>
                                    <a href="events.php?action=edit&event_id=<?php echo $event_ot['EventID']; ?>" class="btn btn-sm btn-outline-warning ms-1">ویرایش</a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="card-footer text-center py-2">
                 <a href="events.php" class="btn btn-secondary btn-sm">مشاهده و مدیریت همه رویدادهای امید تدریس</a>
            </div>
        </div>
    </div>
    <!-- Add more sections for Omid Tadris dashboard as needed -->
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
