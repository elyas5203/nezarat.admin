<?php
// user/parvareshi/public_events_info.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth, $user_base_url

$user_id_pv_info = get_current_user_id();
// No specific user role check needed beyond login, as this is public info for logged-in users,
// unless there's a requirement to restrict based on user type/role.

$stmt_pv_events = $conn->prepare("
    SELECT pp.ProjectID, pp.ProjectName, pp.ProjectType, pp.Description, pp.Proposal,
           pp.StartDate, pp.EndDate, pp.Location, pp.Status,
           CONCAT(u.FirstName, ' ', u.LastName) as LeadUserName
    FROM ParvareshiProjects pp
    LEFT JOIN Users u ON pp.LeadUserID = u.UserID
    WHERE pp.Status IN ('approved', 'ongoing', 'completed') -- Show relevant statuses to users
    ORDER BY pp.StartDate DESC, pp.ProjectName ASC
");

$public_parvareshi_events = [];
if ($stmt_pv_events) {
    $stmt_pv_events->execute();
    $result_pv_events = $stmt_pv_events->get_result();
    while ($event_pv = $result_pv_events->fetch_assoc()) {
        $stmt_files_pv = $conn->prepare("
            SELECT FileID, FileName, FilePath, Description AS FileDescription
            FROM Files
            WHERE AssociatedEntityType = 'parvareshi_project_public_file' AND AssociatedEntityID = ?
            ORDER BY FileName ASC
        ");
        if ($stmt_files_pv) {
            $stmt_files_pv->bind_param("i", $event_pv['ProjectID']);
            $stmt_files_pv->execute();
            $result_files_pv = $stmt_files_pv->get_result();
            $event_pv['AssociatedFiles'] = [];
            while($file_pv = $result_files_pv->fetch_assoc()){
                $event_pv['AssociatedFiles'][] = $file_pv;
            }
            $stmt_files_pv->close();
        }
        $public_parvareshi_events[] = $event_pv;
    }
    $stmt_pv_events->close();
} else {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا در بارگذاری رویدادهای عمومی: ' . $conn->error];
}

$project_type_options_pv_user_display = ['public_event' => 'مراسم عمومی', 'camp' => 'اردو', 'other' => 'پروژه پرورشی'];
$project_status_options_pv_user_display = [
    'planning' => 'برنامه‌ریزی', 'approved' => 'تصویب شده (به زودی)', 'ongoing' => 'در حال اجرا',
    'completed' => 'تکمیل شده', 'archived' => 'بایگانی شده', 'cancelled' => 'لغو شده'
];
// Badge classes for different statuses
$status_badge_class_pv_user = [
    'planning' => 'secondary', 'approved' => 'info', 'ongoing' => 'primary',
    'completed' => 'success', 'archived' => 'light text-dark border', 'cancelled' => 'danger'
];


?>
<div class="page-header">
    <h1>مناسبت‌های عمومی و اردوها</h1>
    <p class="page-subtitle">اطلاعات مربوط به مراسم‌های عمومی، جشن‌ها، اردوها و سایر برنامه‌های بخش پرورشی.</p>
</div>

<?php if (isset($_SESSION['flash_message'])) { $flash_pv_info = $_SESSION['flash_message']; echo "<div class='alert alert-{$flash_pv_info['type']} alert-dismissible fade show'>{$flash_pv_info['text']}<button type='button' class='close' data-dismiss='alert'>&times;</button></div>"; unset($_SESSION['flash_message']); echo "<script> /* Dismiss JS */ </script>";} ?>

<div class="container-fluid mt-3">
    <?php if (!empty($public_parvareshi_events)): ?>
        <div class="row">
            <?php foreach ($public_parvareshi_events as $event_item_pv_disp): ?>
                <div class="col-md-6 col-lg-4 mb-4 d-flex align-items-stretch">
                    <div class="card h-100 shadow-sm event-info-card status-<?php echo htmlspecialchars($event_item_pv_disp['Status']);?>">
                        <div class="card-header event-card-header-<?php echo htmlspecialchars($event_item_pv_disp['Status']);?> text-white">
                            <h5 class="card-title mb-0 font-weight-bold"><?php echo htmlspecialchars($event_item_pv_disp['ProjectName']); ?></h5>
                            <small class="text-white-75"><?php echo $project_type_options_pv_user_display[$event_item_pv_disp['ProjectType']] ?? 'پروژه پرورشی'; ?></small>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <?php if(!empty($event_item_pv_disp['Description'])): ?>
                                <p class="card-text small text-muted flex-grow-1"><?php echo nl2br(htmlspecialchars(mb_substr($event_item_pv_disp['Description'],0, 220) . (mb_strlen($event_item_pv_disp['Description']) > 220 ? '...' : ''))); ?></p>
                            <?php else: ?>
                                <p class="card-text small text-muted flex-grow-1"><em>توضیحات بیشتری ارائه نشده است.</em></p>
                            <?php endif; ?>
                            <hr class="my-2">
                            <p class="card-text mb-1 small">
                                <span class="badge badge-<?php echo $status_badge_class_pv_user[$event_item_pv_disp['Status']] ?? 'light';?> p-2 mb-2 d-inline-block"><?php echo $project_status_options_pv_user_display[$event_item_pv_disp['Status']] ?? $event_item_pv_disp['Status']; ?></span><br>
                                <?php if($event_item_pv_disp['StartDate']): ?>
                                <svg class="icon" width="14" height="14" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                <strong>تاریخ:</strong> <?php echo to_jalali($event_item_pv_disp['StartDate'], 'yyyy/MM/dd'); ?>
                                <?php if($event_item_pv_disp['EndDate'] && $event_item_pv_disp['EndDate'] != $event_item_pv_disp['StartDate']): ?>
                                    تا <?php echo to_jalali($event_item_pv_disp['EndDate'], 'yyyy/MM/dd'); ?>
                                <?php endif; ?><br>
                                <?php endif; ?>
                                <?php if($event_item_pv_disp['Location']): ?>
                                <svg class="icon" width="14" height="14" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                <strong>مکان:</strong> <?php echo htmlspecialchars($event_item_pv_disp['Location']); ?><br>
                                <?php endif; ?>
                                <?php if($event_item_pv_disp['LeadUserName']): ?>
                                <svg class="icon" width="14" height="14" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                <strong>مسئول:</strong> <?php echo htmlspecialchars($event_item_pv_disp['LeadUserName']); ?>
                                <?php endif; ?>
                            </p>

                            <?php if(!empty($event_item_pv_disp['Proposal'])): ?>
                                <button class="btn btn-sm btn-outline-info mt-2 btn-block" type="button" data-toggle="collapse" data-target="#proposal_<?php echo $event_item_pv_disp['ProjectID']; ?>" aria-expanded="false" aria-controls="proposal_<?php echo $event_item_pv_disp['ProjectID']; ?>">
                                    جزئیات بیشتر / پروپوزال
                                </button>
                                <div class="collapse mt-2" id="proposal_<?php echo $event_item_pv_disp['ProjectID']; ?>">
                                    <div class="card card-body small bg-light formatted-text">
                                        <?php echo nl2br(htmlspecialchars($event_item_pv_disp['Proposal'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if(!empty($event_item_pv_disp['AssociatedFiles'])): ?>
                        <div class="card-footer bg-white border-top pt-2 pb-2">
                            <p class="mb-1"><small class="text-muted font-weight-bold">فایل‌های پیوست:</small></p>
                            <?php foreach($event_item_pv_disp['AssociatedFiles'] as $file_assoc_pv): ?>
                                <a href="/my_site/<?php echo htmlspecialchars(ltrim($file_assoc_pv['FilePath'],'/')); ?>" target="_blank" download="<?php echo htmlspecialchars($file_assoc_pv['FileName']); ?>" class="btn btn-xs btn-outline-success m-1 py-1 px-2 d-inline-block">
                                    <svg class="icon" width="12" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                    <?php echo htmlspecialchars($file_assoc_pv['FileName']); ?>
                                    <?php if($file_assoc_pv['FileDescription']): ?> <small>(<?php echo htmlspecialchars($file_assoc_pv['FileDescription']); ?>)</small><?php endif; ?>
                                </a><br>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info text-center mt-4">در حال حاضر هیچ مناسبت عمومی یا اردویی برای نمایش وجود ندارد.</div>
    <?php endif; ?>
</div>
<style>
    .event-info-card { border-radius: 0.5rem; transition: all 0.3s ease-in-out; }
    .event-info-card:hover { transform: translateY(-5px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
    .event-info-card .card-header { border-top-left-radius: calc(0.5rem - 1px); border-top-right-radius: calc(0.5rem - 1px); }
    .event-card-header-approved, .event-card-header-ongoing { background-color: var(--user-panel-primary-color, #17a2b8) !important; }
    .event-card-header-completed { background-color: #28a745 !important; }
    .event-card-header-planning { background-color: #6c757d !important; }
    .event-card-header-cancelled, .event-card-header-archived { background-color: #adb5bd !important; }
    .formatted-text { white-space: pre-wrap; font-size: 0.85rem; max-height: 200px; overflow-y: auto; background-color: #f8f9fa; padding: 10px; border-radius: .2rem;}
    .btn-xs { padding: .15rem .4rem; font-size: .75rem; line-height: 1.3; border-radius: .2rem;}
    .text-white-75 { color: rgba(255,255,255,0.75) !important; }
    .icon { vertical-align: -0.125em; margin-left: 4px;}
</style>
<?php
require_once __DIR__ . '/../includes/footer.php';
?>
