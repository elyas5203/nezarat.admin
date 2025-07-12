<?php
require_once __DIR__ . '/../includes/header.php'; // General admin header

$action = $_GET['action'] ?? 'list'; // list, create, edit, view
$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

$ot_events = []; // For list view
$ot_event_data_for_form = null; // For pre-filling create/edit form
$ot_event_data_for_view = null; // For displaying event details in view mode
$form_errors_ot = []; // Specific errors for this module
$page_title_ot = "مدیریت رویدادهای امید تدریس";

// CSRF Tokens
$csrf_token_name_ot_form = 'omid_tadris_event_form';
$csrf_token_ot_form_val = generate_csrf_token($csrf_token_name_ot_form);
$csrf_token_name_ot_delete = 'omid_tadris_event_delete';
$csrf_token_ot_delete_val = generate_csrf_token($csrf_token_name_ot_delete);

// Event Status options (can be shared or specific)
$ot_event_statuses = [
    'planned' => 'برنامه‌ریزی شده',
    'confirmed' => 'تایید شده',
    'completed' => 'تکمیل شده',
    'cancelled' => 'لغو شده',
];

// Helper for badge class (can be moved to global helper if identical)
if (!function_exists('get_ot_event_status_badge_class')) {
    function get_ot_event_status_badge_class($status) {
        switch (strtolower($status ?? '')) {
            case 'completed': return 'success';
            case 'planned': return 'primary';
            case 'confirmed': return 'info';
            case 'cancelled': return 'danger';
            default: return 'secondary';
        }
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_ot_event'])) { // Create or Update
        if (!verify_csrf_token($_POST['csrf_token'] ?? '', $csrf_token_name_ot_form)) {
            $form_errors_ot['csrf'] = "خطای CSRF.";
        } else {
            $csrf_token_ot_form_val = regenerate_csrf_token($csrf_token_name_ot_form);

            $event_id_posted = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
            $event_name = sanitize_input($_POST['event_name'] ?? '');
            $event_date_jalali = sanitize_input($_POST['event_date'] ?? '');
            $start_time = sanitize_input($_POST['start_time'] ?? null);
            $location = sanitize_input($_POST['location'] ?? '');
            $description = sanitize_input($_POST['description'] ?? '');
            $status = sanitize_input($_POST['status'] ?? 'planned');
            $facilitator = sanitize_input($_POST['facilitator'] ?? null); // e.g., instructor/speaker

            $ot_event_data_for_form = $_POST; // Repopulate form
            $ot_event_data_for_form['EventDate'] = $event_date_jalali;

            if (empty($event_name)) $form_errors_ot['event_name'] = "نام جلسه/رویداد الزامی است.";
            if (empty($event_date_jalali)) $form_errors_ot['event_date'] = "تاریخ الزامی است.";

            $event_date_gregorian = null;
            if (!empty($event_date_jalali)) {
                $event_date_gregorian = to_gregorian_date_for_db($event_date_jalali);
                if (!$event_date_gregorian) $form_errors_ot['event_date'] = "فرمت تاریخ شمسی نامعتبر.";
            }
            if (!empty($start_time) && !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $start_time)) $form_errors_ot['start_time'] = "فرمت ساعت نامعتبر."; else if (empty($start_time)) $start_time = null;

            if (empty($form_errors_ot)) {
                if ($conn) {
                    if ($event_id_posted > 0) { // Update
                        $stmt = $conn->prepare("UPDATE OmidTadrisEvents SET EventName = ?, EventDate = ?, StartTime = ?, Location = ?, Description = ?, Status = ?, Facilitator = ?, UpdatedAt = NOW() WHERE EventID = ?");
                        if($stmt) {
                            $stmt->bind_param("sssssssi", $event_name, $event_date_gregorian, $start_time, $location, $description, $status, $facilitator, $event_id_posted);
                            if ($stmt->execute()) { $_SESSION['action_success_ot'] = "رویداد امید تدریس بروزرسانی شد."; header("Location: events.php"); exit; }
                            else $form_errors_ot['db'] = "خطا در بروزرسانی: " . $stmt->error;
                            $stmt->close();
                        } else $form_errors_ot['db'] = "خطای آماده سازی بروزرسانی: " . $conn->error;
                    } else { // Create
                        $stmt = $conn->prepare("INSERT INTO OmidTadrisEvents (EventName, EventDate, StartTime, Location, Description, Status, Facilitator, CreatedByUserID, CreatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                        if ($stmt) {
                            $current_admin_id_ot_event = get_current_user_id();
                            $stmt->bind_param("sssssssi", $event_name, $event_date_gregorian, $start_time, $location, $description, $status, $facilitator, $current_admin_id_ot_event);
                            if ($stmt->execute()) { $_SESSION['action_success_ot'] = "رویداد امید تدریس ایجاد شد."; header("Location: events.php"); exit; }
                            else $form_errors_ot['db'] = "خطا در ایجاد: " . $stmt->error;
                            $stmt->close();
                        } else $form_errors_ot['db'] = "خطای آماده سازی ایجاد: " . $conn->error;
                    }
                } else $form_errors_ot['db'] = "عدم اتصال به پایگاه داده.";
            }
            $action = ($event_id_posted > 0) ? 'edit' : 'create'; // Stay on form
        }
    } elseif (isset($_POST['delete_ot_event_confirmed'])) { // Delete
        if (!verify_csrf_token($_POST['csrf_token_delete_modal_ot'] ?? '', $csrf_token_name_ot_delete)) {
            $_SESSION['action_error_ot'] = "خطای CSRF.";
        } else {
            $csrf_token_ot_delete_val = regenerate_csrf_token($csrf_token_name_ot_delete);
            $event_id_to_delete = (int)($_POST['event_id_to_delete_confirmed'] ?? 0);
            if ($event_id_to_delete > 0 && $conn) {
                // TODO: Dependency checks (OmidTadrisEventChecklists, OmidTadrisAttendance)
                $stmt_del = $conn->prepare("DELETE FROM OmidTadrisEvents WHERE EventID = ?");
                // Also delete from OmidTadrisEventChecklists and OmidTadrisAttendance associated with this EventID
                if ($stmt_del) {
                    $stmt_del->bind_param("i", $event_id_to_delete);
                    // Execute delete for dependent tables first within a transaction if possible
                    if ($stmt_del->execute()) { $_SESSION['action_success_ot'] = ($stmt_del->affected_rows > 0) ? "رویداد امید تدریس حذف شد." : "رویداد یافت نشد."; }
                    else $_SESSION['action_error_ot'] = "خطا در حذف: " . $stmt_del->error;
                    $stmt_del->close();
                } else $_SESSION['action_error_ot'] = "خطای آماده سازی حذف: " . $conn->error;
            } else $_SESSION['action_error_ot'] = "شناسه نامعتبر برای حذف.";
        }
        header("Location: events.php"); exit;
    }
}


// Fetch data for display
if ($conn) {
    if ($action === 'list') {
        $page_title_ot = "لیست رویدادهای امید تدریس";
        // Basic list fetching, add search/filter later if needed
        $result_list_ot = $conn->query("SELECT EventID, EventName, EventDate, StartTime, Location, Status FROM OmidTadrisEvents ORDER BY EventDate DESC, StartTime DESC");
        if ($result_list_ot) {
            while ($row = $result_list_ot->fetch_assoc()) $ot_events[] = $row;
        } else $form_errors_ot['db_list'] = "خطا در بارگذاری لیست: " . $conn->error;
    } elseif (($action === 'edit' || $action === 'create') && !$ot_event_data_for_form) {
        if ($action === 'edit' && $event_id > 0) {
            $page_title_ot = "ویرایش رویداد امید تدریس";
            $stmt_ot_event = $conn->prepare("SELECT * FROM OmidTadrisEvents WHERE EventID = ?");
            if($stmt_ot_event) {
                $stmt_ot_event->bind_param("i", $event_id); $stmt_ot_event->execute();
                $result_ot_event = $stmt_ot_event->get_result();
                if ($data = $result_ot_event->fetch_assoc()) {
                    $ot_event_data_for_form = $data;
                    if (!empty($ot_event_data_for_form['EventDate'])) {
                        $ot_event_data_for_form['EventDate'] = to_jalali($ot_event_data_for_form['EventDate'], 'yyyy/MM/dd');
                    }
                } else { $_SESSION['action_error_ot'] = "رویداد امید تدریس یافت نشد."; header("Location: events.php"); exit; }
                $stmt_ot_event->close();
            } else $form_errors_ot['db_load'] = "خطا در بارگذاری رویداد: " . $conn->error;
        } else { // create
            $page_title_ot = "ایجاد رویداد جدید امید تدریس";
            $ot_event_data_for_form = ['EventName' => '', 'EventDate' => '', 'StartTime' => '10:00', 'Location' => '', 'Description' => '', 'Status' => 'planned', 'Facilitator' => ''];
        }
    } elseif ($action === 'view' && $event_id > 0) {
        $page_title_ot = "مشاهده جزئیات رویداد امید تدریس";
        // Fetch with creator username
        $stmt_ot_view = $conn->prepare("SELECT oe.*, u.Username as CreatorUsername
                                       FROM OmidTadrisEvents oe
                                       LEFT JOIN Users u ON oe.CreatedByUserID = u.UserID
                                       WHERE oe.EventID = ?");
        if($stmt_ot_view){
            $stmt_ot_view->bind_param("i", $event_id); $stmt_ot_view->execute();
            $result_ot_view = $stmt_ot_view->get_result();
            if (!($ot_event_data_for_view = $result_ot_view->fetch_assoc())) {
                 $_SESSION['action_error_ot'] = "رویداد امید تدریس یافت نشد."; header("Location: events.php"); exit;
            }
            $stmt_ot_view->close();
        } else $form_errors_ot['db_load_view'] = "خطا در بارگذاری جزئیات: " . $conn->error;
    }
} else {
    $form_errors_ot['db_connection'] = "خطا در اتصال به پایگاه داده.";
}

?>
<div class="page-header">
    <h1><?php echo $page_title_ot; ?></h1>
    <div class="page-header-actions">
        <a href="events.php" class="btn btn-<?php echo ($action === 'list' ? 'primary' : 'secondary'); ?>"><em class="bi bi-list-ul icon"></em> <?php echo ($action === 'list' ? 'ایجاد رویداد جدید' : 'لیست رویدادها'); ?></a>
        <?php if ($action !== 'list'): ?>
            <a href="index.php" class="btn btn-outline-secondary ms-2"><em class="bi bi-house-door icon"></em> داشبورد امید تدریس</a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_SESSION['action_success_ot'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $_SESSION['action_success_ot']; unset($_SESSION['action_success_ot']); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
<?php endif; ?>
<?php if (!empty($form_errors_ot)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert"><strong>خطا:</strong><ul class="mb-0 ps-3"><?php foreach ($form_errors_ot as $err): echo "<li>".htmlspecialchars($err)."</li>"; endforeach; ?></ul><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead class="table-light"><tr><th>#</th><th>نام رویداد</th><th>تاریخ</th><th>زمان</th><th>مکان</th><th>وضعیت</th><th class="actions-column">عملیات</th></tr></thead>
                    <tbody>
                        <?php if(empty($ot_events)): ?><tr><td colspan="7" class="text-center py-4 text-muted">هیچ رویدادی برای امید تدریس ثبت نشده است.</td></tr><?php endif; ?>
                        <?php foreach($ot_events as $idx => $e): ?>
                        <tr>
                            <td><?php echo $idx + 1; ?></td>
                            <td><a href="?action=view&event_id=<?php echo $e['EventID']; ?>"><?php echo htmlspecialchars($e['EventName']); ?></a></td>
                            <td><?php echo to_jalali($e['EventDate'], 'yyyy/MM/dd'); ?></td>
                            <td><?php echo htmlspecialchars(substr($e['StartTime'] ?? '',0,5) ?: '---'); ?></td>
                            <td><?php echo htmlspecialchars($e['Location'] ?: '---'); ?></td>
                            <td><span class="badge bg-<?php echo get_ot_event_status_badge_class($e['Status']); ?>"><?php echo $ot_event_statuses[$e['Status']] ?? $e['Status']; ?></span></td>
                            <td class="actions-cell">
                                <a href="?action=edit&event_id=<?php echo $e['EventID']; ?>" class="btn btn-sm btn-outline-info" title="ویرایش"><em class="bi bi-pencil-square"></em></a>
                                <a href="event_checklists.php?event_id=<?php echo $e['EventID']; ?>" class="btn btn-sm btn-outline-secondary" title="چک‌لیست‌ها"><em class="bi bi-card-checklist"></em></a>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-delete-ot-event" data-event-id="<?php echo $e['EventID']; ?>" data-event-name="<?php echo htmlspecialchars($e['EventName']); ?>"><em class="bi bi-trash3"></em></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php elseif ($action === 'create' || $action === 'edit'): ?>
    <div class="card">
        <div class="card-body">
            <form method="POST" action="events.php" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_ot_form_val; ?>">
                <?php if ($action === 'edit' && $event_id): ?><input type="hidden" name="event_id" value="<?php echo $event_id; ?>"><?php endif; ?>
                <div class="row">
                    <div class="col-md-8 mb-3"><label for="ot_event_name" class="form-label">نام جلسه/رویداد <span class="text-danger">*</span></label><input type="text" class="form-control <?php echo isset($form_errors_ot['event_name'])?'is-invalid':'';?>" id="ot_event_name" name="event_name" value="<?php echo htmlspecialchars($ot_event_data_for_form['EventName'] ?? '');?>" required><?php if(isset($form_errors_ot['event_name'])):?><div class="invalid-feedback"><?php echo $form_errors_ot['event_name'];?></div><?php endif;?></div>
                    <div class="col-md-4 mb-3"><label for="ot_event_status" class="form-label">وضعیت <span class="text-danger">*</span></label><select class="form-select <?php echo isset($form_errors_ot['status'])?'is-invalid':'';?>" id="ot_event_status" name="status" required><?php foreach($ot_event_statuses as $sk=>$sv):?><option value="<?php echo $sk;?>" <?php echo (($ot_event_data_for_form['Status']??'planned')===$sk)?'selected':'';?>><?php echo $sv;?></option><?php endforeach;?></select><?php if(isset($form_errors_ot['status'])):?><div class="invalid-feedback"><?php echo $form_errors_ot['status'];?></div><?php endif;?></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label for="ot_event_date" class="form-label">تاریخ (شمسی) <span class="text-danger">*</span></label><input type="text" class="form-control persian-datepicker <?php echo isset($form_errors_ot['event_date'])?'is-invalid':'';?>" id="ot_event_date" name="event_date" value="<?php echo htmlspecialchars($ot_event_data_for_form['EventDate'] ?? '');?>" placeholder="مثال: 1403/06/20" required><?php if(isset($form_errors_ot['event_date'])):?><div class="invalid-feedback"><?php echo $form_errors_ot['event_date'];?></div><?php endif;?></div>
                    <div class="col-md-6 mb-3"><label for="ot_start_time" class="form-label">ساعت شروع</label><input type="time" class="form-control <?php echo isset($form_errors_ot['start_time'])?'is-invalid':'';?>" id="ot_start_time" name="start_time" value="<?php echo htmlspecialchars($ot_event_data_for_form['StartTime'] ?? '');?>"><?php if(isset($form_errors_ot['start_time'])):?><div class="invalid-feedback"><?php echo $form_errors_ot['start_time'];?></div><?php endif;?></div>
                </div>
                <div class="mb-3"><label for="ot_location" class="form-label">مکان</label><input type="text" class="form-control" id="ot_location" name="location" value="<?php echo htmlspecialchars($ot_event_data_for_form['Location'] ?? '');?>"></div>
                <div class="mb-3"><label for="ot_facilitator" class="form-label">استاد / تسهیلگر</label><input type="text" class="form-control" id="ot_facilitator" name="facilitator" value="<?php echo htmlspecialchars($ot_event_data_for_form['Facilitator'] ?? '');?>" placeholder="نام استاد یا مسئول جلسه"></div>
                <div class="mb-3"><label for="ot_description" class="form-label">توضیحات</label><textarea class="form-control" id="ot_description" name="description" rows="4"><?php echo htmlspecialchars($ot_event_data_for_form['Description'] ?? '');?></textarea></div>
                <div class="form-actions"><button type="submit" name="save_ot_event" class="btn btn-success"><em class="bi bi-check-circle-fill icon"></em> ذخیره</button><a href="events.php" class="btn btn-outline-secondary">انصراف</a></div>
            </form>
        </div>
    </div>
<?php elseif ($action === 'view' && $ot_event_data_for_view): ?>
     <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center"><h5 class="mb-0">جزئیات: <?php echo htmlspecialchars($ot_event_data_for_view['EventName']); ?></h5><a href="?action=edit&event_id=<?php echo $ot_event_data_for_view['EventID']; ?>" class="btn btn-sm btn-outline-primary"><em class="bi bi-pencil-square me-1"></em>ویرایش</a></div>
        <div class="card-body">
            <dl class="row">
                <dt class="col-sm-3">تاریخ:</dt><dd class="col-sm-9"><?php echo to_jalali($ot_event_data_for_view['EventDate'], 'dddd، dd MMMM yyyy'); ?> <?php if($ot_event_data_for_view['StartTime']) echo "، ساعت ".htmlspecialchars(substr($ot_event_data_for_view['StartTime'],0,5)); ?></dd>
                <dt class="col-sm-3">مکان:</dt><dd class="col-sm-9"><?php echo htmlspecialchars($ot_event_data_for_view['Location'] ?: '---'); ?></dd>
                <dt class="col-sm-3">وضعیت:</dt><dd class="col-sm-9"><span class="badge bg-<?php echo get_ot_event_status_badge_class($ot_event_data_for_view['Status']); ?>"><?php echo $ot_event_statuses[$ot_event_data_for_view['Status']] ?? $ot_event_data_for_view['Status']; ?></span></dd>
                <dt class="col-sm-3">استاد/تسهیلگر:</dt><dd class="col-sm-9"><?php echo htmlspecialchars($ot_event_data_for_view['Facilitator'] ?: '---'); ?></dd>
                <dt class="col-sm-3">توضیحات:</dt><dd class="col-sm-9"><?php echo nl2br(htmlspecialchars($ot_event_data_for_view['Description'] ?: '---')); ?></dd>
                <dt class="col-sm-3">ایجاد شده:</dt><dd class="col-sm-9"><?php echo to_jalali($ot_event_data_for_view['CreatedAt'], 'yyyy/MM/dd HH:mm'); ?> توسط <?php echo htmlspecialchars($ot_event_data_for_view['CreatorUsername'] ?: 'سیستم'); ?></dd>
            </dl>
            <hr><h6 class="mt-4">عملیات مرتبط:</h6>
            <a href="event_checklists.php?event_id=<?php echo $ot_event_data_for_view['EventID']; ?>" class="btn btn-primary"><em class="bi bi-card-checklist me-1"></em>چک‌لیست‌ها</a>
            <a href="attendance.php?event_id=<?php echo $ot_event_data_for_view['EventID']; ?>" class="btn btn-success ms-2"><em class="bi bi-person-lines-fill me-1"></em>حضور و غیاب داوطلبان</a>
        </div>
    </div>
<?php endif; ?>

<!-- Delete Confirmation Modal for Omid Tadris Events -->
<div class="modal fade" id="deleteOTEventModal" tabindex="-1" aria-labelledby="deleteOTEventModalLabel" aria-hidden="true">
  <div class="modal-dialog"> <div class="modal-content">
      <form method="POST" action="events.php" id="deleteOTEventFormModal">
        <input type="hidden" name="csrf_token_delete_modal_ot" id="csrf_token_delete_modal_ot_input" value="">
        <input type="hidden" name="event_id_to_delete_confirmed" id="event_id_to_delete_modal_ot_input">
        <div class="modal-header"><h5 class="modal-title" id="deleteOTEventModalLabel">تایید حذف رویداد امید تدریس</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">آیا از حذف رویداد <strong id="eventNameToDeleteOTModal"></strong> مطمئن هستید؟</div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button><button type="submit" name="delete_ot_event_confirmed" class="btn btn-danger">بله، حذف</button></div>
      </form></div></div></div>

<!-- Common JS for Datepicker and Modal -->
<link rel="stylesheet" href="<?php echo get_base_url(); ?>assets/js/persian-datepicker/persian-datepicker.min.css"/>
<script src="<?php echo get_base_url(); ?>assets/js/jquery.min.js"></script>
<script src="<?php echo get_base_url(); ?>assets/js/persian-datepicker/persian-date.min.js"></script>
<script src="<?php echo get_base_url(); ?>assets/js/persian-datepicker/persian-datepicker.min.js"></script>
<script type="text/javascript">
  $(document).ready(function() {
    if ($(".persian-datepicker").length) {
        $(".persian-datepicker").persianDatepicker({ format: 'YYYY/MM/DD', autoClose: true, observer: true, initialValue: false });
    }
    $('.btn-delete-ot-event').on('click', function() {
        $('#event_id_to_delete_modal_ot_input').val($(this).data('event-id'));
        $('#eventNameToDeleteOTModal').text($(this).data('event-name'));
        $('#csrf_token_delete_modal_ot_input').val('<?php echo $csrf_token_ot_delete_val; ?>');
        new bootstrap.Modal(document.getElementById('deleteOTEventModal')).show();
    });
  });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
