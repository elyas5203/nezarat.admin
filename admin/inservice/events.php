<?php
require_once __DIR__ . '/../includes/header.php';

$action = $_GET['action'] ?? 'list'; // list, create, edit, view
$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

$events = [];
$event_data_for_form = null; // For pre-filling create/edit form
$event_data_for_view = null; // For displaying event details in view mode
$form_errors = [];
$page_title = "مدیریت رویدادهای ضمن خدمت";

// CSRF Tokens
$csrf_token_name_create_edit = 'inservice_event_form'; // Same token for create and edit forms for simplicity
$csrf_token_create_edit_val = generate_csrf_token($csrf_token_name_create_edit);
$csrf_token_name_delete = 'inservice_event_delete'; // Separate token for delete actions
$csrf_token_delete_val = generate_csrf_token($csrf_token_name_delete);


// Event Status options
$event_statuses = [
    'planned' => 'برنامه‌ریزی شده',
    'confirmed' => 'تایید شده',
    'completed' => 'تکمیل شده',
    'cancelled' => 'لغو شده',
    'postponed' => 'به تعویق افتاده'
];

// Helper function for badge class based on status (moved here for self-containment if not in global helpers)
if (!function_exists('get_event_status_badge_class')) {
    function get_event_status_badge_class($status) {
        switch (strtolower($status ?? '')) {
            case 'completed': return 'success';
            case 'planned': return 'primary';
            case 'confirmed': return 'info';
            case 'cancelled': return 'danger';
            case 'postponed': return 'warning text-dark';
            default: return 'secondary';
        }
    }
}


// Handle POST requests for create/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_event'])) { // Create or Update
        if (!verify_csrf_token($_POST['csrf_token'] ?? '', $csrf_token_name_create_edit)) {
            $form_errors['csrf'] = "خطای CSRF. لطفاً صفحه را رفرش کنید.";
        } else {
            $csrf_token_create_edit_val = regenerate_csrf_token($csrf_token_name_create_edit);

            $event_id_posted = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
            $event_name = sanitize_input($_POST['event_name'] ?? '');
            $event_date_jalali = sanitize_input($_POST['event_date'] ?? ''); // Keep Jalali for re-display
            $start_time = sanitize_input($_POST['start_time'] ?? null);
            $end_time = sanitize_input($_POST['end_time'] ?? null);
            $location = sanitize_input($_POST['location'] ?? '');
            $description = sanitize_input($_POST['description'] ?? '');
            $status = sanitize_input($_POST['status'] ?? 'planned');
            $organizer = sanitize_input($_POST['organizer'] ?? null);
            $target_audience = sanitize_input($_POST['target_audience'] ?? null);

            // Populate $event_data_for_form for re-display in case of error
            $event_data_for_form = $_POST;
            $event_data_for_form['EventDate'] = $event_date_jalali; // Ensure Jalali date is kept for form

            // Validation
            if (empty($event_name)) $form_errors['event_name'] = "نام رویداد الزامی است.";
            if (empty($event_date_jalali)) $form_errors['event_date'] = "تاریخ رویداد الزامی است.";

            $event_date_gregorian = null;
            if (!empty($event_date_jalali)) {
                $event_date_gregorian = to_gregorian_date_for_db($event_date_jalali);
                if (!$event_date_gregorian) $form_errors['event_date'] = "فرمت تاریخ شمسی نامعتبر است. (مثال: 1403/05/15)";
            }
            if (!empty($start_time) && !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $start_time)) $form_errors['start_time'] = "فرمت ساعت شروع نامعتبر (HH:MM)."; else if (empty($start_time)) $start_time = null;
            if (!empty($end_time) && !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $end_time)) $form_errors['end_time'] = "فرمت ساعت پایان نامعتبر (HH:MM)."; else if (empty($end_time)) $end_time = null;

            if (empty($form_errors)) {
                if ($conn) {
                    if ($event_id_posted > 0) { // Update
                        $stmt = $conn->prepare("UPDATE InserviceEvents SET EventName = ?, EventDate = ?, StartTime = ?, EndTime = ?, Location = ?, Description = ?, Status = ?, Organizer = ?, TargetAudience = ?, UpdatedAt = NOW() WHERE EventID = ?");
                        if ($stmt) {
                            $stmt->bind_param("sssssssssi", $event_name, $event_date_gregorian, $start_time, $end_time, $location, $description, $status, $organizer, $target_audience, $event_id_posted);
                            if ($stmt->execute()) { $_SESSION['action_success'] = "رویداد بروزرسانی شد."; header("Location: events.php"); exit; }
                            else $form_errors['db'] = "خطا در بروزرسانی: " . $stmt->error;
                            $stmt->close();
                        } else $form_errors['db'] = "خطای آماده سازی بروزرسانی: " . $conn->error;
                    } else { // Create
                        $stmt = $conn->prepare("INSERT INTO InserviceEvents (EventName, EventDate, StartTime, EndTime, Location, Description, Status, Organizer, TargetAudience, CreatedByUserID, CreatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                        if ($stmt) {
                            $current_admin_id_event = get_current_user_id();
                            $stmt->bind_param("sssssssssi", $event_name, $event_date_gregorian, $start_time, $end_time, $location, $description, $status, $organizer, $target_audience, $current_admin_id_event);
                            if ($stmt->execute()) { $_SESSION['action_success'] = "رویداد ایجاد شد."; header("Location: events.php"); exit; }
                            else $form_errors['db'] = "خطا در ایجاد: " . $stmt->error;
                            $stmt->close();
                        } else $form_errors['db'] = "خطای آماده سازی ایجاد: " . $conn->error;
                    }
                } else $form_errors['db'] = "عدم اتصال به پایگاه داده.";
            }
            $action = ($event_id_posted > 0) ? 'edit' : 'create'; // Stay on form if error
        }
    } elseif (isset($_POST['delete_event_confirmed'])) { // Delete action (from modal confirmation)
        if (!verify_csrf_token($_POST['csrf_token_delete_modal'] ?? '', $csrf_token_name_delete)) {
            $_SESSION['action_error'] = "خطای CSRF هنگام حذف.";
        } else {
            $csrf_token_delete_val = regenerate_csrf_token($csrf_token_name_delete); // Regenerate
            $event_id_to_delete = (int)($_POST['event_id_to_delete_confirmed'] ?? 0);
            if ($event_id_to_delete > 0 && $conn) {
                // TODO: Add dependency checks (checklists, attendance, content) before deleting
                // For now, simple delete.
                $stmt_del = $conn->prepare("DELETE FROM InserviceEvents WHERE EventID = ?");
                if ($stmt_del) {
                    $stmt_del->bind_param("i", $event_id_to_delete);
                    if ($stmt_del->execute()) {
                        $_SESSION['action_success'] = ($stmt_del->affected_rows > 0) ? "رویداد حذف شد." : "رویداد یافت نشد یا قبلا حذف شده.";
                    } else $_SESSION['action_error'] = "خطا در حذف: " . $stmt_del->error;
                    $stmt_del->close();
                } else $_SESSION['action_error'] = "خطای آماده سازی حذف: " . $conn->error;
            } else $_SESSION['action_error'] = "شناسه رویداد برای حذف نامعتبر.";
        }
        header("Location: events.php"); exit;
    }
}


// Fetch data for list, edit form, or view details
if ($conn) {
    if ($action === 'list') {
        $page_title = "لیست رویدادهای ضمن خدمت";
        $search_term_list = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
        $status_filter_list = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';

        $sql_list = "SELECT EventID, EventName, EventDate, StartTime, Location, Status FROM InserviceEvents WHERE 1=1";
        $params_list = []; $types_list = "";
        if (!empty($search_term_list)) {
            $sql_list .= " AND (EventName LIKE ? OR Location LIKE ? OR Description LIKE ?)";
            $like_search_list = "%".$search_term_list."%";
            array_push($params_list, $like_search_list, $like_search_list, $like_search_list); $types_list .= "sss";
        }
        if(!empty($status_filter_list) && array_key_exists($status_filter_list, $event_statuses)){
            $sql_list .= " AND Status = ?";
            $params_list[] = $status_filter_list; $types_list .= "s";
        }
        $sql_list .= " ORDER BY EventDate DESC, StartTime DESC";

        $stmt_list = $conn->prepare($sql_list);
        if($stmt_list){
            if(!empty($params_list)) $stmt_list->bind_param($types_list, ...$params_list);
            if($stmt_list->execute()){
                $result_list = $stmt_list->get_result();
                while($row = $result_list->fetch_assoc()){ $events[] = $row; }
                $stmt_list->close();
            } else $form_errors['db_list'] = "خطا در بارگذاری لیست: " . $stmt_list->error;
        } else $form_errors['db_list'] = "خطای آماده سازی لیست: " . $conn->error;

    } elseif (($action === 'edit' || $action === 'create') && !$event_data_for_form) { // Load for form if not already populated by POST error
        if ($action === 'edit' && $event_id > 0) {
            $page_title = "ویرایش رویداد";
            $stmt_event = $conn->prepare("SELECT * FROM InserviceEvents WHERE EventID = ?");
            if($stmt_event){
                $stmt_event->bind_param("i", $event_id); $stmt_event->execute();
                $result_event = $stmt_event->get_result();
                if ($data = $result_event->fetch_assoc()) {
                    $event_data_for_form = $data;
                    if (!empty($event_data_for_form['EventDate'])) {
                        $event_data_for_form['EventDate'] = to_jalali($event_data_for_form['EventDate'], 'yyyy/MM/dd');
                    }
                } else { $_SESSION['action_error'] = "رویداد یافت نشد."; header("Location: events.php"); exit; }
                $stmt_event->close();
            } else $form_errors['db_load_event'] = "خطا در بارگذاری رویداد: " . $conn->error;
        } else { // 'create' action
            $page_title = "ایجاد رویداد جدید";
            // Default values for create form
            $event_data_for_form = ['EventName' => '', 'EventDate' => '', 'StartTime' => '09:00', 'EndTime' => '12:00', 'Location' => '', 'Description' => '', 'Status' => 'planned', 'Organizer' => '', 'TargetAudience' => ''];
        }
    } elseif ($action === 'view' && $event_id > 0) {
        $page_title = "مشاهده جزئیات رویداد";
        $stmt_event_view = $conn->prepare("SELECT e.*, u_created.Username as CreatorUsername, u_updated.Username as UpdaterUsername
                                          FROM InserviceEvents e
                                          LEFT JOIN Users u_created ON e.CreatedByUserID = u_created.UserID
                                          LEFT JOIN Users u_updated ON e.UpdatedByUserID = u_updated.UserID
                                          WHERE e.EventID = ?");
        if($stmt_event_view){
            $stmt_event_view->bind_param("i", $event_id); $stmt_event_view->execute();
            $result_event_view = $stmt_event_view->get_result();
            if (!($event_data_for_view = $result_event_view->fetch_assoc())) {
                $_SESSION['action_error'] = "رویداد یافت نشد."; header("Location: events.php"); exit;
            }
            $stmt_event_view->close();
        } else $form_errors['db_load_event_view'] = "خطا در بارگذاری جزئیات: " . $conn->error;
    }
} else {
    $form_errors['db_connection'] = "خطا در اتصال به پایگاه داده.";
}

?>

<div class="page-header">
    <h1><?php echo $page_title; ?></h1>
    <?php if ($action === 'list'): ?>
    <div class="page-header-actions">
        <a href="?action=create" class="btn btn-primary"><em class="bi bi-calendar-plus icon"></em> ایجاد رویداد جدید</a>
    </div>
    <?php else: ?>
    <div class="page-header-actions">
        <a href="events.php" class="btn btn-secondary"><em class="bi bi-arrow-right-circle icon"></em> بازگشت به لیست</a>
    </div>
    <?php endif; ?>
</div>

<?php if (isset($_SESSION['action_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $_SESSION['action_success']; unset($_SESSION['action_success']); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
<?php endif; ?>
<?php if (!empty($form_errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>خطا:</strong>
        <ul class="mb-0 ps-3"> <!-- Added padding for RTL list -->
            <?php foreach ($form_errors as $error_msg): echo "<li>" . htmlspecialchars($error_msg) . "</li>"; endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>


<?php if ($action === 'list'): ?>
    <div class="filter-search-bar mb-3">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-5">
                <input type="text" class="form-control form-control-sm" name="search" placeholder="جستجو در نام، مکان، توضیحات..." value="<?php echo htmlspecialchars($search_term_list ?? ''); ?>">
            </div>
            <div class="col-md-4">
                <select name="status" class="form-select form-select-sm">
                    <option value="">همه وضعیت‌ها</option>
                    <?php foreach ($event_statuses as $s_key => $s_val): ?>
                        <option value="<?php echo $s_key; ?>" <?php echo ($status_filter_list ?? '') === $s_key ? 'selected' : ''; ?>><?php echo $s_val; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-info btn-sm">فیلتر</button>
            </div>
             <?php if (!empty($search_term_list) || !empty($status_filter_list)): ?>
             <div class="col-md-auto">
                <a href="events.php" class="btn btn-secondary btn-sm">پاک کردن</a>
            </div>
            <?php endif; ?>
        </form>
    </div>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>نام رویداد</th>
                            <th>تاریخ</th>
                            <th>زمان</th>
                            <th>مکان</th>
                            <th>وضعیت</th>
                            <th class="actions-column">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($events)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">هیچ رویدادی یافت نشد.</td></tr>
                        <?php else: ?>
                            <?php foreach ($events as $idx => $event): ?>
                            <tr>
                                <td><?php echo $idx + 1; ?></td>
                                <td><a href="?action=view&event_id=<?php echo $event['EventID']; ?>"><?php echo htmlspecialchars($event['EventName']); ?></a></td>
                                <td><?php echo to_jalali($event['EventDate'], 'yyyy/MM/dd'); ?></td>
                                <td><?php echo htmlspecialchars(substr($event['StartTime'] ?? '', 0, 5) ?: '---'); ?></td>
                                <td><?php echo htmlspecialchars($event['Location'] ?? '---'); ?></td>
                                <td><span class="badge bg-<?php echo get_event_status_badge_class($event['Status']); ?>"><?php echo $event_statuses[$event['Status']] ?? htmlspecialchars($event['Status']); ?></span></td>
                                <td class="actions-cell">
                                    <a href="?action=edit&event_id=<?php echo $event['EventID']; ?>" class="btn btn-sm btn-outline-info" title="ویرایش"><em class="bi bi-pencil-square"></em></a>
                                    <a href="event_checklists.php?event_id=<?php echo $event['EventID']; ?>" class="btn btn-sm btn-outline-secondary" title="چک‌لیست‌ها"><em class="bi bi-card-checklist"></em></a>
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-delete-event" data-event-id="<?php echo $event['EventID']; ?>" data-event-name="<?php echo htmlspecialchars($event['EventName']); ?>" title="حذف"><em class="bi bi-trash3"></em></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($action === 'create' || $action === 'edit'): ?>
    <div class="card">
        <div class="card-body">
            <form method="POST" action="events.php" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_create_edit_val; ?>">
                <?php if ($action === 'edit' && $event_id): ?>
                    <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label for="event_name_input" class="form-label">نام رویداد <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?php echo isset($form_errors['event_name']) ? 'is-invalid' : ''; ?>" id="event_name_input" name="event_name" value="<?php echo htmlspecialchars($event_data_for_form['EventName'] ?? ''); ?>" required>
                        <?php if(isset($form_errors['event_name'])):?><div class="invalid-feedback"><?php echo $form_errors['event_name'];?></div><?php endif;?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="event_status_select" class="form-label">وضعیت <span class="text-danger">*</span></label>
                        <select class="form-select <?php echo isset($form_errors['status']) ? 'is-invalid' : ''; ?>" id="event_status_select" name="status" required>
                            <?php foreach ($event_statuses as $s_key => $s_val): ?>
                                <option value="<?php echo $s_key; ?>" <?php echo (($event_data_for_form['Status'] ?? 'planned') === $s_key) ? 'selected' : ''; ?>><?php echo $s_val; ?></option>
                            <?php endforeach; ?>
                        </select>
                         <?php if(isset($form_errors['status'])):?><div class="invalid-feedback"><?php echo $form_errors['status'];?></div><?php endif;?>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="event_date_input" class="form-label">تاریخ رویداد (شمسی) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control persian-datepicker <?php echo isset($form_errors['event_date']) ? 'is-invalid' : ''; ?>" id="event_date_input" name="event_date" value="<?php echo htmlspecialchars($event_data_for_form['EventDate'] ?? ''); ?>" placeholder="مثال: 1403/05/15" required>
                        <?php if(isset($form_errors['event_date'])):?><div class="invalid-feedback"><?php echo $form_errors['event_date'];?></div><?php endif;?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="start_time_input" class="form-label">ساعت شروع</label>
                        <input type="time" class="form-control <?php echo isset($form_errors['start_time']) ? 'is-invalid' : ''; ?>" id="start_time_input" name="start_time" value="<?php echo htmlspecialchars($event_data_for_form['StartTime'] ?? ''); ?>">
                        <?php if(isset($form_errors['start_time'])):?><div class="invalid-feedback"><?php echo $form_errors['start_time'];?></div><?php endif;?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="end_time_input" class="form-label">ساعت پایان</label>
                        <input type="time" class="form-control <?php echo isset($form_errors['end_time']) ? 'is-invalid' : ''; ?>" id="end_time_input" name="end_time" value="<?php echo htmlspecialchars($event_data_for_form['EndTime'] ?? ''); ?>">
                         <?php if(isset($form_errors['end_time'])):?><div class="invalid-feedback"><?php echo $form_errors['end_time'];?></div><?php endif;?>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="location_input" class="form-label">مکان</label>
                    <input type="text" class="form-control" id="location_input" name="location" value="<?php echo htmlspecialchars($event_data_for_form['Location'] ?? ''); ?>" placeholder="مثال: سالن اجتماعات مرکزی">
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="organizer_input" class="form-label">برگزارکننده/مسئول</label>
                        <input type="text" class="form-control" id="organizer_input" name="organizer" value="<?php echo htmlspecialchars($event_data_for_form['Organizer'] ?? ''); ?>" placeholder="نام فرد یا بخش">
                    </div>
                     <div class="col-md-6 mb-3">
                        <label for="target_audience_input" class="form-label">مخاطبین</label>
                        <input type="text" class="form-control" id="target_audience_input" name="target_audience" value="<?php echo htmlspecialchars($event_data_for_form['TargetAudience'] ?? ''); ?>" placeholder="مثال: مدرسین پایه اول تا سوم">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="description_textarea" class="form-label">توضیحات رویداد</label>
                    <textarea class="form-control" id="description_textarea" name="description" rows="4"><?php echo htmlspecialchars($event_data_for_form['Description'] ?? ''); ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" name="save_event" class="btn btn-success"><em class="bi bi-check-circle-fill icon"></em> ذخیره رویداد</button>
                    <a href="events.php" class="btn btn-outline-secondary">انصراف</a>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($action === 'view' && $event_data_for_view): ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">جزئیات رویداد: <?php echo htmlspecialchars($event_data_for_view['EventName']); ?></h5>
            <a href="?action=edit&event_id=<?php echo $event_data_for_view['EventID']; ?>" class="btn btn-sm btn-outline-primary"><em class="bi bi-pencil-square me-1"></em>ویرایش این رویداد</a>
        </div>
        <div class="card-body">
            <dl class="row">
                <dt class="col-sm-3">شناسه رویداد:</dt><dd class="col-sm-9"><?php echo $event_data_for_view['EventID']; ?></dd>
                <dt class="col-sm-3">تاریخ:</dt><dd class="col-sm-9"><?php echo to_jalali($event_data_for_view['EventDate'], 'dddd، dd MMMM yyyy'); ?></dd>
                <?php if($event_data_for_view['StartTime']): ?>
                <dt class="col-sm-3">زمان شروع:</dt><dd class="col-sm-9"><?php echo htmlspecialchars(substr($event_data_for_view['StartTime'],0,5)); ?></dd>
                <?php endif; ?>
                <?php if($event_data_for_view['EndTime']): ?>
                <dt class="col-sm-3">زمان پایان:</dt><dd class="col-sm-9"><?php echo htmlspecialchars(substr($event_data_for_view['EndTime'],0,5)); ?></dd>
                <?php endif; ?>
                <dt class="col-sm-3">مکان:</dt><dd class="col-sm-9"><?php echo htmlspecialchars($event_data_for_view['Location'] ?: '---'); ?></dd>
                <dt class="col-sm-3">وضعیت:</dt><dd class="col-sm-9"><span class="badge bg-<?php echo get_event_status_badge_class($event_data_for_view['Status']); ?>"><?php echo $event_statuses[$event_data_for_view['Status']] ?? htmlspecialchars($event_data_for_view['Status']); ?></span></dd>
                <dt class="col-sm-3">برگزارکننده:</dt><dd class="col-sm-9"><?php echo htmlspecialchars($event_data_for_view['Organizer'] ?: '---'); ?></dd>
                <dt class="col-sm-3">مخاطبین:</dt><dd class="col-sm-9"><?php echo htmlspecialchars($event_data_for_view['TargetAudience'] ?: '---'); ?></dd>
                <dt class="col-sm-3">توضیحات:</dt><dd class="col-sm-9"><?php echo nl2br(htmlspecialchars($event_data_for_view['Description'] ?: '---')); ?></dd>
                <dt class="col-sm-3">ایجاد شده:</dt><dd class="col-sm-9"><?php echo to_jalali($event_data_for_view['CreatedAt'], 'yyyy/MM/dd HH:mm'); ?> توسط <?php echo htmlspecialchars($event_data_for_view['CreatorUsername'] ?: 'سیستم'); ?></dd>
                <?php if($event_data_for_view['UpdatedAt'] && $event_data_for_view['UpdatedAt'] != $event_data_for_view['CreatedAt']): ?>
                <dt class="col-sm-3">آخرین بروزرسانی:</dt><dd class="col-sm-9"><?php echo to_jalali($event_data_for_view['UpdatedAt'], 'yyyy/MM/dd HH:mm'); ?> توسط <?php echo htmlspecialchars($event_data_for_view['UpdaterUsername'] ?: 'سیستم'); ?></dd>
                <?php endif; ?>
            </dl>
            <hr>
            <h6 class="mt-4">عملیات مرتبط با این رویداد:</h6>
            <a href="event_checklists.php?event_id=<?php echo $event_data_for_view['EventID']; ?>" class="btn btn-primary"><em class="bi bi-card-checklist me-1"></em>مدیریت چک‌لیست‌ها</a>
            <a href="attendance.php?event_id=<?php echo $event_data_for_view['EventID']; ?>" class="btn btn-success ms-2"><em class="bi bi-person-check me-1"></em>ثبت حضور و غیاب</a>
            <a href="content.php?event_id=<?php echo $event_data_for_view['EventID']; ?>" class="btn btn-info ms-2"><em class="bi bi-folder-symlink me-1"></em>مدیریت محتوای جلسه</a>
        </div>
    </div>
<?php endif; ?>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteEventModal" tabindex="-1" aria-labelledby="deleteEventModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="events.php" id="deleteEventFormModal">
        <input type="hidden" name="csrf_token_delete_modal" id="csrf_token_delete_modal_input" value="<?php echo $csrf_token_delete_val; ?>">
        <input type="hidden" name="event_id_to_delete_confirmed" id="event_id_to_delete_modal_input">
        <div class="modal-header">
          <h5 class="modal-title" id="deleteEventModalLabel">تایید حذف رویداد</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          آیا از حذف رویداد <strong id="eventNameToDeleteModal"></strong> مطمئن هستید؟ این عمل غیرقابل بازگشت است و ممکن است اطلاعات وابسته (چک‌لیست‌ها، حضور و غیاب، محتوا) نیز حذف شوند.
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
          <button type="submit" name="delete_event_confirmed" class="btn btn-danger">بله، حذف کن</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Include Persian Datepicker JS and CSS if not already globally included -->
<link rel="stylesheet" href="<?php echo get_base_url(); ?>assets/js/persian-datepicker/persian-datepicker.min.css"/>
<script src="<?php echo get_base_url(); ?>assets/js/jquery.min.js"></script> <!-- Datepicker might need jQuery -->
<script src="<?php echo get_base_url(); ?>assets/js/persian-datepicker/persian-date.min.js"></script>
<script src="<?php echo get_base_url(); ?>assets/js/persian-datepicker/persian-datepicker.min.js"></script>
<script type="text/javascript">
  $(document).ready(function() {
    // Initialize datepicker for create/edit forms
    if ($(".persian-datepicker").length) {
        $(".persian-datepicker").persianDatepicker({
          format: 'YYYY/MM/DD',
          autoClose: true,
          observer: true, // Updates the input element on select
          initialValue: false, // Let PHP handle initial value from $event_data_for_form
           toolbox: {
                calendarSwitch: {
                    enabled: true,
                    format: 'YYYY/MM/DD'
                }
            }
        });
    }

    // Handle delete button click to populate modal
    $('.btn-delete-event').on('click', function() {
        const eventId = $(this).data('event-id');
        const eventName = $(this).data('event-name');
        $('#event_id_to_delete_modal_input').val(eventId);
        $('#eventNameToDeleteModal').text(eventName);
        // CSRF token for modal form should be the general delete token
        $('#csrf_token_delete_modal_input').val('<?php echo $csrf_token_delete_val; ?>');
        var deleteModal = new bootstrap.Modal(document.getElementById('deleteEventModal'));
        deleteModal.show();
    });
  });
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
