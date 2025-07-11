<?php
// user/inservice/my_schedule.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth, $user_base_url

$user_id_is = get_current_user_id();
// Allow teachers and other relevant staff roles to view the schedule
if (!$user_id_is || !in_array(get_current_user_type(), ['teacher', 'admin', 'manager', 'deputy', 'member'])) {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'دسترسی به این بخش برای نقش شما تعریف نشده است.'];
    header("Location: " . ($user_base_url ?? '/my_site/user') . "/dashboard/index.php");
    exit;
}

$inservice_department_id_user = null;
$stmt_is_dept_user = $conn->prepare("SELECT DepartmentID FROM Departments WHERE DepartmentName LIKE '%ضمن خدمت%' OR DepartmentName LIKE '%امید تدریس%' LIMIT 1");
if ($stmt_is_dept_user) {
    $stmt_is_dept_user->execute();
    $res_isd_user = $stmt_is_dept_user->get_result();
    if ($isd_row_user = $res_isd_user->fetch_assoc()) $inservice_department_id_user = $isd_row_user['DepartmentID'];
    $stmt_is_dept_user->close();
}
// If the "ضمن خدمت" department is not found, the query below might return no results, which is handled.
// A more robust system might create the department if it doesn't exist, or use a config setting.

$upcoming_events_is = [];
$past_events_is = [];

// Fetch events from the "ضمن خدمت" department (or similar like "امید تدریس")
// Shows events from last 3 months and all future events, excluding cancelled ones.
$sql_events_is_user = "
    SELECT EventID, EventName, EventDate, Location, Speaker, Status, Notes
    FROM EventCalendar
    WHERE DepartmentID = ? AND EventDate >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH) AND Status != 'cancelled'
    ORDER BY EventDate ASC";

$stmt_events_is_user = $conn->prepare($sql_events_is_user);

if ($stmt_events_is_user && $inservice_department_id_user) { // Proceed only if department ID is found
    $stmt_events_is_user->bind_param("i", $inservice_department_id_user);
    $stmt_events_is_user->execute();
    $result_is_user = $stmt_events_is_user->get_result();
    $today_is = new DateTime();
    $today_is->setTime(0,0,0);

    while($evt_is_u = $result_is_user->fetch_assoc()){
        try {
            $event_date_obj_is_u = new DateTime($evt_is_u['EventDate']);
            $event_date_obj_is_u->setTime(0,0,0);
            if($event_date_obj_is_u >= $today_is) {
                $upcoming_events_is[] = $evt_is_u;
            } else {
                $past_events_is[] = $evt_is_u;
            }
        } catch (Exception $e) {
            // Log error for invalid date format in DB
            error_log("Invalid date format for EventID " . $evt_is_u['EventID'] . ": " . $evt_is_u['EventDate']);
        }
    }
    $stmt_events_is_user->close();
    $past_events_is = array_reverse($past_events_is);
} elseif (!$inservice_department_id_user) {
    $_SESSION['flash_message'] = ['type' => 'info', 'text' => 'بخش "ضمن خدمت" یا "امید تدریس" در سیستم تعریف نشده است.'];
} elseif ($stmt_events_is_user === false) { // Check if prepare failed
     $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا در آماده‌سازی کوئری بارگذاری رویدادها: '.$conn->error];
}


$event_status_options_user_is = ['planned' => 'برنامه‌ریزی شده', 'confirmed' => 'قطعی شده', 'completed' => 'انجام شده', 'cancelled' => 'لغو شده'];
$status_badge_map_user_is = ['planned' => 'primary', 'confirmed' => 'info', 'completed' => 'success', 'cancelled' => 'secondary']; // Matched admin panel badges
?>
<div class="page-header">
    <h1>برنامه جلسات ضمن خدمت و امید تدریس</h1>
    <p class="page-subtitle">جلسات و رویدادهای آموزشی و معرفت‌افزایی پیش‌رو و اخیر.</p>
</div>

<?php
if (isset($_SESSION['flash_message'])) {
    $flash_is_user = $_SESSION['flash_message'];
    echo "<div class='alert alert-{$flash_is_user['type']} alert-dismissible fade show' role='alert'>{$flash_is_user['text']}
          <button type='button' class='close' data-dismiss='alert' aria-label='Close' style='/* ... */'><span aria-hidden='true'>&times;</span></button></div>";
    unset($_SESSION['flash_message']);
    echo "<script> /* JS for alert dismissal ... */ </script>";
}
?>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-primary-user text-white"><h5 class="mb-0 card-title-text">
        <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><path d="M12 11v0"></path></svg>
        جلسات و رویدادهای پیش‌رو
    </h5></div>
    <div class="card-body">
        <?php if(!empty($upcoming_events_is)): ?>
        <div class="list-group">
            <?php foreach($upcoming_events_is as $event_up_is): ?>
            <a href="event_details.php?event_id=<?php echo $event_up_is['EventID']; ?>" class="list-group-item list-group-item-action flex-column align-items-start mb-2 rounded shadow-sm-hover">
                <div class="d-flex w-100 justify-content-between">
                    <h6 class="mb-1 font-weight-bold"><?php echo htmlspecialchars($event_up_is['EventName']); ?></h6>
                    <span class="badge badge-<?php echo $status_badge_map_user_is[$event_up_is['Status']] ?? 'light'; ?> p-2"><?php echo $event_status_options_user_is[$event_up_is['Status']] ?? $event_up_is['Status']; ?></span>
                </div>
                <p class="mb-1 small text-muted">
                    <svg class="icon" width="14" height="14" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <?php echo to_jalali($event_up_is['EventDate'], 'yyyy/MM/dd HH:mm'); ?>
                    <?php if($event_up_is['Location']): ?> | <svg class="icon" width="14" height="14" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg> <?php echo htmlspecialchars($event_up_is['Location']); endif; ?>
                </p>
                <?php if($event_up_is['Speaker']): ?><p class="mb-0 small"><strong>استاد/سخنران:</strong> <?php echo htmlspecialchars($event_up_is['Speaker']); ?></p><?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?><p class="text-muted text-center my-3">جلسه پیش‌رویی در حال حاضر برنامه‌ریزی نشده است.</p><?php endif; ?>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-light"><h5 class="mb-0 card-title-text">
        <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><polyline points="12 7 12 13 15 15"></polyline></svg>
        جلسات اخیر (۳ ماه گذشته)
    </h5></div>
    <div class="card-body">
         <?php if(!empty($past_events_is)): ?>
        <div class="list-group">
            <?php foreach($past_events_is as $event_pa_is): ?>
            <a href="event_details.php?event_id=<?php echo $event_pa_is['EventID']; ?>" class="list-group-item list-group-item-action flex-column align-items-start mb-2 rounded shadow-sm-hover">
                <div class="d-flex w-100 justify-content-between">
                    <h6 class="mb-1 font-weight-bold"><?php echo htmlspecialchars($event_pa_is['EventName']); ?></h6>
                    <span class="badge badge-<?php echo $status_badge_map_user_is[$event_pa_is['Status']] ?? 'light'; ?> p-2"><?php echo $event_status_options_user_is[$event_pa_is['Status']] ?? $event_pa_is['Status']; ?></span>
                </div>
                <p class="mb-1 small text-muted"><svg class="icon" width="14" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> <?php echo to_jalali($event_pa_is['EventDate'], 'yyyy/MM/dd HH:mm'); ?> <?php if($event_pa_is['Location']): ?> | <svg class="icon" width="14" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg> <?php echo htmlspecialchars($event_pa_is['Location']); endif; ?></p>
                <?php if($event_pa_is['Speaker']): ?><p class="mb-0 small"><strong>استاد/سخنران:</strong> <?php echo htmlspecialchars($event_pa_is['Speaker']); ?></p><?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?><p class="text-muted text-center my-3">جلسه‌ای در ۳ ماه اخیر برگزار نشده است.</p><?php endif; ?>
    </div>
</div>
<style>
    .list-group-item-action.shadow-sm-hover { transition: box-shadow 0.2s ease-in-out, transform 0.2s ease-in-out; border-right: 3px solid transparent;}
    .list-group-item-action.shadow-sm-hover:hover { box-shadow: 0 .3rem 1rem rgba(0,0,0,.1)!important; transform: translateX(-2px); border-right-color: var(--user-panel-primary-color, #17a2b8); }
    .bg-primary-user { background-color: var(--user-panel-primary-color, #17a2b8) !important; }
    .icon { vertical-align: text-bottom; margin-left: 5px; }
    .badge.p-2 { padding: 0.4em 0.6em !important; font-size: 0.85em !important;}
</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
