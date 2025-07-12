<?php
require_once __DIR__ . '/../includes/header.php';

$page_title_pcs = "مدیریت خدمت‌گزاری کلاس‌ها در مناسبت‌ها";
$action_pcs = $_GET['action'] ?? 'list'; // list, view, approve (example)
$service_id_pcs_url = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;

$form_errors_pcs_page = [];
$class_services_list_display = [];
$service_details_pcs = null; // For view/edit action

// Filters for list view
$filter_class_id_pcs_list = isset($_GET['class_id_filter']) ? (int)$_GET['class_id_filter'] : null;
$filter_occasion_pcs_list = isset($_GET['occasion_filter']) ? sanitize_input($_GET['occasion_filter']) : '';
$filter_status_pcs_list = isset($_GET['status_filter']) ? sanitize_input($_GET['status_filter']) : '';

$available_classes_pcs_dd = [];
if($conn) {
    $res_cls_pcs_dd = $conn->query("SELECT ClassID, ClassName, AcademicYear FROM Classes ORDER BY AcademicYear DESC, ClassName ASC");
    if($res_cls_pcs_dd) while($row_dd_cls = $res_cls_pcs_dd->fetch_assoc()) $available_classes_pcs_dd[] = $row_dd_cls;
}

$service_statuses_pcs_map = ['planned' => 'برنامه‌ریزی شده', 'submitted' => 'ارسال شده توسط مدرس', 'approved' => 'تایید شده', 'completed' => 'انجام شده', 'cancelled' => 'لغو شده', 'needs_improvement' => 'نیازمند بهبود/بازبینی'];
if (!function_exists('get_pcs_status_badge_class')) { function get_pcs_status_badge_class($status) { $s=strtolower($status??''); if($s=='completed')return'success';if($s=='approved')return'primary';if($s=='submitted')return'info text-dark';if($s=='planned')return'secondary';if($s=='cancelled')return'danger';if($s=='needs_improvement')return'warning text-dark';return'light text-dark';}}


// Handle POST actions (e.g., changing status)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_service_status'])) {
    // CSRF check would be here
    $service_id_to_update = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;
    $new_status = sanitize_input($_POST['new_status'] ?? '');
    $admin_notes = sanitize_input($_POST['admin_notes'] ?? null);

    if ($service_id_to_update > 0 && array_key_exists($new_status, $service_statuses_pcs_map) && $conn) {
        $stmt_update_status = $conn->prepare("UPDATE ParvareshiClassServices SET Status = ?, AdminNotes = ?, UpdatedAt = NOW(), ApprovedByUserID = ? WHERE ServiceID = ?");
        if($stmt_update_status){
            $current_admin_id_pcs_status = get_current_user_id();
            $stmt_update_status->bind_param("ssii", $new_status, $admin_notes, $current_admin_id_pcs_status, $service_id_to_update);
            if($stmt_update_status->execute()){
                $_SESSION['action_success_parvareshi'] = "وضعیت خدمت‌گزاری بروزرسانی شد.";
                 // Notify teacher?
                // create_notification($service_submitter_id, "وضعیت برنامه خدمت‌گزاری شما برای مناسبت X به Y تغییر کرد.", "user/parvareshi/my_class_services.php?service_id=".$service_id_to_update);
            } else {
                $_SESSION['action_error_parvareshi'] = "خطا در بروزرسانی وضعیت: " . $stmt_update_status->error;
            }
            $stmt_update_status->close();
        } else {
             $_SESSION['action_error_parvareshi'] = "خطا در آماده سازی بروزرسانی وضعیت: " . $conn->error;
        }
    } else {
        $_SESSION['action_error_parvareshi'] = "اطلاعات نامعتبر برای بروزرسانی وضعیت.";
    }
    header("Location: class_services.php?action=view&service_id=" . $service_id_to_update); // Redirect back to view
    exit;
}


if ($conn) {
    if ($action_pcs === 'list') {
        $sql_pcs_list = "SELECT pcs.ServiceID, pcs.OccasionName, pcs.ServiceType, pcs.ServiceDate, pcs.Status,
                                c.ClassName, c.AcademicYear, u.Username as SubmitterUsername
                         FROM ParvareshiClassServices pcs
                         JOIN Classes c ON pcs.ClassID = c.ClassID
                         LEFT JOIN Users u ON pcs.SubmittedByUserID = u.UserID
                         WHERE 1=1";
        $params_pcs_list = []; $types_pcs_list = "";
        if ($filter_class_id_pcs_list) { $sql_pcs_list .= " AND pcs.ClassID = ?"; $params_pcs_list[] = $filter_class_id_pcs_list; $types_pcs_list .= "i"; }
        if (!empty($filter_occasion_pcs_list)) { $sql_pcs_list .= " AND pcs.OccasionName LIKE ?"; $params_pcs_list[] = "%".$filter_occasion_pcs_list."%"; $types_pcs_list .= "s"; }
        if (!empty($filter_status_pcs_list)) { $sql_pcs_list .= " AND pcs.Status = ?"; $params_pcs_list[] = $filter_status_pcs_list; $types_pcs_list .= "s"; }
        $sql_pcs_list .= " ORDER BY pcs.ServiceDate DESC, c.ClassName ASC";

        $stmt_pcs_list = $conn->prepare($sql_pcs_list);
        if ($stmt_pcs_list) {
            if (!empty($params_pcs_list)) $stmt_pcs_list->bind_param($types_pcs_list, ...$params_pcs_list);
            if ($stmt_pcs_list->execute()) { $result_pcs_list = $stmt_pcs_list->get_result(); while ($row = $result_pcs_list->fetch_assoc()) $class_services_list_display[] = $row; }
            else $form_errors_pcs_page['db_list'] = "خطا در اجرای کوئری: " . $stmt_pcs_list->error;
            $stmt_pcs_list->close();
        } else $form_errors_pcs_page['db_prepare'] = "خطا در آماده سازی کوئری: " . $conn->error;
    } elseif ($action_pcs === 'view' && $service_id_pcs_url > 0) {
        $page_title_pcs = "مشاهده جزئیات خدمت‌گزاری کلاس";
        $stmt_details = $conn->prepare("SELECT pcs.*, c.ClassName, c.AcademicYear, u.Username as SubmitterUsername, u_app.Username as ApproverUsername
                                        FROM ParvareshiClassServices pcs
                                        JOIN Classes c ON pcs.ClassID = c.ClassID
                                        LEFT JOIN Users u ON pcs.SubmittedByUserID = u.UserID
                                        LEFT JOIN Users u_app ON pcs.ApprovedByUserID = u_app.UserID
                                        WHERE pcs.ServiceID = ?");
        if($stmt_details){
            $stmt_details->bind_param("i", $service_id_pcs_url);
            $stmt_details->execute();
            $res_details = $stmt_details->get_result();
            if(!($service_details_pcs = $res_details->fetch_assoc())){
                $_SESSION['action_error_parvareshi'] = "برنامه خدمت‌گزاری یافت نشد.";
                header("Location: class_services.php"); exit;
            }
            $stmt_details->close();
        } else $form_errors_pcs_page['db_load_details'] = "خطا در بارگذاری جزئیات: " . $conn->error;
    }
} else $form_errors_pcs_page['db_conn_page'] = "خطا در اتصال به پایگاه داده.";
?>
<div class="page-header">
    <h1><?php echo $page_title_pcs; ?></h1>
    <div class="page-header-actions">
        <a href="index.php" class="btn btn-outline-secondary"><em class="bi bi-house-door icon"></em> داشبورد پرورشی</a>
        <?php if($action_pcs !== 'list'): ?>
             <a href="class_services.php" class="btn btn-secondary ms-2"><em class="bi bi-list-ul icon"></em> لیست برنامه‌ها</a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_SESSION['action_success_parvareshi'])): ?><div class="alert alert-success alert-dismissible fade show"><button class="btn-close" data-bs-dismiss="alert"></button><?php echo $_SESSION['action_success_parvareshi']; unset($_SESSION['action_success_parvareshi']);?></div><?php endif;?>
<?php if (!empty($form_errors_pcs_page)): ?><div class="alert alert-danger alert-dismissible fade show"><strong>خطا:</strong><ul class="mb-0 ps-3"><?php foreach($form_errors_pcs_page as $e_key_pcs=>$e_msg_pcs):echo "<li>".htmlspecialchars($e_msg_pcs)."</li>";endforeach;?></ul><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif;?>

<?php if($action_pcs === 'list'): ?>
<div class="filter-search-bar mb-3"><form method="GET" class="row g-2 align-items-center">
    <div class="col-md-3"><select name="class_id_filter" class="form-select form-select-sm"><option value="">همه کلاس‌ها</option><?php foreach($available_classes_pcs_dd as $cls_f_pcs):?><option value="<?php echo $cls_f_pcs['ClassID'];?>" <?php echo ($filter_class_id_pcs_list==$cls_f_pcs['ClassID'])?'selected':'';?>><?php echo htmlspecialchars($cls_f_pcs['ClassName'].' ('.$cls_f_pcs['AcademicYear'].')');?></option><?php endforeach;?></select></div>
    <div class="col-md-3"><input type="text" name="occasion_filter" class="form-control form-control-sm" placeholder="نام مناسبت..." value="<?php echo htmlspecialchars($filter_occasion_pcs_list); ?>"></div>
    <div class="col-md-3"><select name="status_filter" class="form-select form-select-sm"><option value="">همه وضعیت‌ها</option><?php foreach($service_statuses_pcs_map as $sk_f_pcs=>$sv_f_pcs):?><option value="<?php echo $sk_f_pcs;?>" <?php echo ($filter_status_pcs_list===$sk_f_pcs)?'selected':'';?>><?php echo $sv_f_pcs;?></option><?php endforeach;?></select></div>
    <div class="col-md-auto"><button type="submit" class="btn btn-info btn-sm">فیلتر</button></div>
    <?php if($filter_class_id_pcs_list||!empty($filter_occasion_pcs_list)||!empty($filter_status_pcs_list)):?><div class="col-md-auto"><a href="class_services.php" class="btn btn-secondary btn-sm">پاک کردن</a></div><?php endif;?>
</form></div>

<div class="card"><div class="card-header"><span>لیست برنامه‌های خدمت‌گزاری (<?php echo count($class_services_list_display); ?> مورد)</span></div>
<div class="card-body">
    <?php if(empty($class_services_list_display)): ?><p class="text-center text-muted py-3">هیچ برنامه‌ای یافت نشد.</p>
    <?php else: ?><div class="table-responsive"><table class="table table-hover table-sm">
        <thead class="table-light"><tr><th>کلاس</th><th>مناسبت</th><th>نوع خدمت</th><th>تاریخ</th><th>وضعیت</th><th>ثبت توسط</th><th class="actions-column">جزئیات</th></tr></thead>
        <tbody><?php foreach($class_services_list_display as $s_item): ?>
            <tr><td><?php echo htmlspecialchars($s_item['ClassName'].' ('.$s_item['AcademicYear'].')'); ?></td>
            <td><?php echo htmlspecialchars($s_item['OccasionName']); ?></td>
            <td><?php echo htmlspecialchars($s_item['ServiceType']); ?></td>
            <td><?php echo to_jalali($s_item['ServiceDate'], 'yyyy/MM/dd'); ?></td>
            <td><span class="badge bg-<?php echo get_pcs_status_badge_class($s_item['Status']); ?>"><?php echo $service_statuses_pcs_map[$s_item['Status']] ?? $s_item['Status']; ?></span></td>
            <td><?php echo htmlspecialchars($s_item['SubmitterUsername'] ?: '---'); ?></td>
            <td class="actions-cell"><a href="?action=view&service_id=<?php echo $s_item['ServiceID']; ?>" class="btn btn-sm btn-outline-primary" title="مشاهده جزئیات و فایل‌ها"><em class="bi bi-eye-fill"></em></a></td></tr>
        <?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
</div></div>

<?php elseif($action_pcs === 'view' && $service_details_pcs):
    $csrf_token_status_update = generate_csrf_token('update_service_status_'.$service_details_pcs['ServiceID']);
?>
<div class="card">
    <div class="card-header"><h5 class="mb-0">جزئیات خدمت‌گزاری: <?php echo htmlspecialchars($service_details_pcs['OccasionName'] . " - کلاس " . $service_details_pcs['ClassName']);?></h5></div>
    <div class="card-body">
        <dl class="row">
            <dt class="col-sm-3">کلاس:</dt><dd class="col-sm-9"><?php echo htmlspecialchars($service_details_pcs['ClassName'].' ('.$service_details_pcs['AcademicYear'].')'); ?></dd>
            <dt class="col-sm-3">مناسبت:</dt><dd class="col-sm-9"><?php echo htmlspecialchars($service_details_pcs['OccasionName']); ?></dd>
            <dt class="col-sm-3">نوع خدمت:</dt><dd class="col-sm-9"><?php echo htmlspecialchars($service_details_pcs['ServiceType']); ?></dd>
            <dt class="col-sm-3">تاریخ برگزاری:</dt><dd class="col-sm-9"><?php echo to_jalali($service_details_pcs['ServiceDate'],'yyyy/MM/dd'); ?></dd>
            <dt class="col-sm-3">مکان:</dt><dd class="col-sm-9"><?php echo htmlspecialchars($service_details_pcs['Location'] ?: '---'); ?></dd>
            <dt class="col-sm-3">وضعیت فعلی:</dt><dd class="col-sm-9"><span class="badge fs-6 bg-<?php echo get_pcs_status_badge_class($service_details_pcs['Status']); ?>"><?php echo $service_statuses_pcs_map[$service_details_pcs['Status']] ?? $service_details_pcs['Status']; ?></span></dd>
            <dt class="col-sm-3">توضیحات مدرس:</dt><dd class="col-sm-9"><?php echo nl2br(htmlspecialchars($service_details_pcs['Description'] ?: '---')); ?></dd>
            <dt class="col-sm-3">ثبت شده توسط:</dt><dd class="col-sm-9"><?php echo htmlspecialchars($service_details_pcs['SubmitterUsername'] ?: '---'); ?> در <?php echo to_jalali($service_details_pcs['SubmittedAt'],'yyyy/MM/dd HH:mm'); ?></dd>
            <?php if($service_details_pcs['AdminNotes']): ?>
                <dt class="col-sm-3 text-primary">یادداشت ادمین:</dt><dd class="col-sm-9 text-primary"><?php echo nl2br(htmlspecialchars($service_details_pcs['AdminNotes'])); ?></dd>
            <?php endif; ?>
             <?php if($service_details_pcs['ApprovedByUserID']): ?>
                <dt class="col-sm-3">تایید/بررسی نهایی توسط:</dt><dd class="col-sm-9"><?php echo htmlspecialchars($service_details_pcs['ApproverUsername'] ?: '---'); ?> در <?php echo to_jalali($service_details_pcs['UpdatedAt'],'yyyy/MM/dd HH:mm'); ?></dd>
            <?php endif; ?>
        </dl>
        <hr>
        <h6>فایل‌های پیوست شده:</h6>
        <p>
            <?php if($service_details_pcs['GuestListPath']): ?>
                <a href="<?php echo get_base_url() . htmlspecialchars($service_details_pcs['GuestListPath']); ?>" target="_blank" class="btn btn-sm btn-outline-info"><em class="bi bi-people-fill me-1"></em> لیست مهمانان</a>
            <?php else: ?>
                <span class="text-muted">لیست مهمانان پیوست نشده.</span>
            <?php endif; ?>
        </p>
        <p>
            <?php if($service_details_pcs['PhotosPath']):
                // Assuming PhotosPath is a directory. Listing files would be more complex here.
                // For simplicity, just provide a link if path exists.
            ?>
                <a href="<?php echo get_base_url() . htmlspecialchars($service_details_pcs['PhotosPath']); ?>" target="_blank" class="btn btn-sm btn-outline-success"><em class="bi bi-images me-1"></em> مشاهده پوشه تصاویر</a>
                 <small class="text-muted d-block">توجه: این لینک ممکن است به یک پوشه اشاره کند. مدیریت نمایش تصاویر نیاز به پیاده‌سازی جداگانه دارد.</small>
            <?php else: ?>
                <span class="text-muted">تصاویر پیوست نشده‌اند.</span>
            <?php endif; ?>
        </p>
        <hr>
        <h6>تغییر وضعیت برنامه:</h6>
        <form method="POST" action="class_services.php?action=view&service_id=<?php echo $service_id_pcs_url; ?>" class="row g-3 align-items-end">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_status_update; // Use a specific token for this action ?>">
            <input type="hidden" name="service_id" value="<?php echo $service_id_pcs_url; ?>">
            <div class="col-md-4">
                <label for="new_status_pcs" class="form-label">وضعیت جدید:</label>
                <select name="new_status" id="new_status_pcs" class="form-select">
                    <?php foreach($service_statuses_pcs_map as $s_key_upd => $s_val_upd): ?>
                        <option value="<?php echo $s_key_upd; ?>" <?php echo ($service_details_pcs['Status'] === $s_key_upd) ? 'selected' : ''; ?>><?php echo $s_val_upd; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label for="admin_notes_pcs" class="form-label">یادداشت ادمین (اختیاری):</label>
                <input type="text" name="admin_notes" id="admin_notes_pcs" class="form-control" placeholder="دلیل تغییر وضعیت یا توضیحات بیشتر">
            </div>
            <div class="col-md-2">
                <button type="submit" name="update_service_status" class="btn btn-warning w-100">بروزرسانی</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
