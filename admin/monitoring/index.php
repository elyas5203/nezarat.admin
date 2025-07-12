<?php
require_once __DIR__ . '/../includes/header.php';

// Assume FormIDs for self-assessment and observation forms are known
// These should ideally be configurable in AppSettings or a config file.
// For now, using hardcoded values. Ensure these forms exist via admin/forms/create.php
if (!defined('SELF_ASSESSMENT_FORM_ID_CONFIG')) define('SELF_ASSESSMENT_FORM_ID_CONFIG', 1);
if (!defined('CLASS_OBSERVATION_FORM_ID_CONFIG')) define('CLASS_OBSERVATION_FORM_ID_CONFIG', 2);

$monitoring_stats_page = [
    'total_self_assessments' => 0,
    'self_assessments_last_week' => 0,
    'total_observations' => 0,
    'observations_last_month' => 0,
];
$action_error_monitoring_page = '';

if($conn){
    try {
        $sa_form_id_val = SELF_ASSESSMENT_FORM_ID_CONFIG;
        $obs_form_id_val = CLASS_OBSERVATION_FORM_ID_CONFIG;

        $stmt_sa_total_page = $conn->prepare("SELECT COUNT(SubmissionID) as count FROM FormSubmissions WHERE FormID = ?");
        if($stmt_sa_total_page){
            $stmt_sa_total_page->bind_param("i", $sa_form_id_val); $stmt_sa_total_page->execute();
            $monitoring_stats_page['total_self_assessments'] = $stmt_sa_total_page->get_result()->fetch_assoc()['count'] ?? 0;
            $stmt_sa_total_page->close();
        } else {$action_error_monitoring_page .= "خطا بارگذاری کل خود اظهاری‌ها. ";}

        $one_week_ago_mon_page = date('Y-m-d H:i:s', strtotime('-7 days'));
        $stmt_sa_week_page = $conn->prepare("SELECT COUNT(SubmissionID) as count FROM FormSubmissions WHERE FormID = ? AND SubmittedAt >= ?");
        if($stmt_sa_week_page){
            $stmt_sa_week_page->bind_param("is", $sa_form_id_val, $one_week_ago_mon_page); $stmt_sa_week_page->execute();
            $monitoring_stats_page['self_assessments_last_week'] = $stmt_sa_week_page->get_result()->fetch_assoc()['count'] ?? 0;
            $stmt_sa_week_page->close();
        } else {$action_error_monitoring_page .= "خطا بارگذاری خوداظهاری‌های هفته. ";}

        $stmt_obs_total_page = $conn->prepare("SELECT COUNT(SubmissionID) as count FROM FormSubmissions WHERE FormID = ?");
         if($stmt_obs_total_page){
            $stmt_obs_total_page->bind_param("i", $obs_form_id_val); $stmt_obs_total_page->execute();
            $monitoring_stats_page['total_observations'] = $stmt_obs_total_page->get_result()->fetch_assoc()['count'] ?? 0;
            $stmt_obs_total_page->close();
        } else {$action_error_monitoring_page .= "خطا بارگذاری کل بازدیدها. ";}

        $one_month_ago_mon_page = date('Y-m-d H:i:s', strtotime('-1 month'));
        $stmt_obs_month_page = $conn->prepare("SELECT COUNT(SubmissionID) as count FROM FormSubmissions WHERE FormID = ? AND SubmittedAt >= ?");
        if($stmt_obs_month_page){
            $stmt_obs_month_page->bind_param("is", $obs_form_id_val, $one_month_ago_mon_page); $stmt_obs_month_page->execute();
            $monitoring_stats_page['observations_last_month'] = $stmt_obs_month_page->get_result()->fetch_assoc()['count'] ?? 0;
            $stmt_obs_month_page->close();
        } else {$action_error_monitoring_page .= "خطا بارگذاری بازدیدهای ماه. ";}

    } catch (Exception $e) {
        $action_error_monitoring_page .= "خطای کلی در بارگذاری آمار: " . $e->getMessage();
    }
} else {
    $action_error_monitoring_page = "خطا در اتصال به پایگاه داده.";
}

if (isset($_SESSION['action_error_monitoring'])) {
    $action_error_monitoring_page = $_SESSION['action_error_monitoring'] . $action_error_monitoring_page;
    unset($_SESSION['action_error_monitoring']);
}
?>
<div class="page-header">
    <h1>بخش نظارت و ارزیابی</h1>
    <p class="page-subtitle">مشاهده و تحلیل فرم‌های خوداظهاری، بازدیدهای کلاسی و گزارشات فصلی.</p>
    <div class="page-header-actions">
        <a href="class_submissions.php" class="btn btn-primary"><em class="bi bi-card-list icon"></em> مشاهده پاسخ فرم‌ها</a>
        <a href="analysis.php" class="btn btn-info"><em class="bi bi-graph-up icon"></em> آنالیز داده‌ها</a>
        <a href="seasonal_report.php" class="btn btn-success"><em class="bi bi-file-earmark-text icon"></em> گزارشات فصلی</a>
    </div>
</div>

<?php if(isset($_SESSION['action_success_monitoring'])):?><div class="alert alert-success alert-dismissible fade show"><button class="btn-close" data-bs-dismiss="alert"></button><?php echo $_SESSION['action_success_monitoring']; unset($_SESSION['action_success_monitoring']);?></div><?php endif;?>
<?php if(!empty($action_error_monitoring_page)):?><div class="alert alert-danger alert-dismissible fade show"><button class="btn-close" data-bs-dismiss="alert"></button><?php echo $action_error_monitoring_page;?></div><?php endif;?>

<div class="row gy-4">
    <div class="col-md-6 col-lg-3"><div class="card border-start border-primary border-4 shadow-sm h-100"><div class="card-body"><div class="text-xs fw-bold text-primary text-uppercase mb-1">کل خوداظهاری‌ها</div><div class="h5 mb-0 fw-bold text-gray-800"><?php echo $monitoring_stats_page['total_self_assessments']; ?></div></div><a href="class_submissions.php?form_id_filter=<?php echo SELF_ASSESSMENT_FORM_ID_CONFIG; ?>" class="stretched-link"></a></div></div>
    <div class="col-md-6 col-lg-3"><div class="card border-start border-info border-4 shadow-sm h-100"><div class="card-body"><div class="text-xs fw-bold text-info text-uppercase mb-1">خوداظهاری هفته اخیر</div><div class="h5 mb-0 fw-bold text-gray-800"><?php echo $monitoring_stats_page['self_assessments_last_week']; ?></div></div><a href="class_submissions.php?form_id_filter=<?php echo SELF_ASSESSMENT_FORM_ID_CONFIG; ?>&date_filter=last_week" class="stretched-link"></a></div></div>
    <div class="col-md-6 col-lg-3"><div class="card border-start border-success border-4 shadow-sm h-100"><div class="card-body"><div class="text-xs fw-bold text-success text-uppercase mb-1">کل بازدیدهای کلاسی</div><div class="h5 mb-0 fw-bold text-gray-800"><?php echo $monitoring_stats_page['total_observations']; ?></div></div><a href="class_submissions.php?form_id_filter=<?php echo CLASS_OBSERVATION_FORM_ID_CONFIG; ?>" class="stretched-link"></a></div></div>
    <div class="col-md-6 col-lg-3"><div class="card border-start border-warning border-4 shadow-sm h-100"><div class="card-body"><div class="text-xs fw-bold text-warning text-uppercase mb-1">بازدیدهای ماه اخیر</div><div class="h5 mb-0 fw-bold text-gray-800"><?php echo $monitoring_stats_page['observations_last_month']; ?></div></div><a href="class_submissions.php?form_id_filter=<?php echo CLASS_OBSERVATION_FORM_ID_CONFIG; ?>&date_filter=last_month" class="stretched-link"></a></div></div>
</div>

<div class="row mt-4">
    <div class="col-lg-7 mb-4">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0">خلاصه وضعیت عملکرد کلاس‌ها (بر اساس آخرین گزارش فصلی - نمونه)</h5></div>
            <div class="card-body" style="max-height: 350px; overflow-y: auto;">
                <p class="text-muted small">این بخش نیازمند پیاده‌سازی کامل ماژول گزارش فصلی و سیستم امتیازدهی/وضعیت‌دهی است.</p>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">کلاس پنجم شهید فهمیده <span class="badge bg-success rounded-pill">عالی</span></li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">کلاس چهارم امید <span class="badge bg-warning text-dark rounded-pill">نیاز به توجه</span></li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">کلاس ششم ایثار <span class="badge bg-primary rounded-pill">خوب</span></li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">کلاس پنجم دکتر حسابی <span class="badge bg-danger rounded-pill">ضعیف</span></li>
                </ul>
            </div>
             <div class="card-footer text-center"><a href="seasonal_report.php" class="btn btn-sm btn-outline-secondary">مشاهده همه گزارشات فصلی</a></div>
        </div>
    </div>
    <div class="col-lg-5 mb-4">
         <div class="card h-100">
            <div class="card-header"><h5 class="mb-0">لینک‌های سریع بخش نظارت</h5></div>
            <div class="list-group list-group-flush">
                <a href="<?php echo $admin_base_url; ?>/forms/edit.php?form_id=<?php echo SELF_ASSESSMENT_FORM_ID_CONFIG; ?>" class="list-group-item list-group-item-action">ویرایش ساختار فرم خوداظهاری</a>
                <a href="<?php echo $admin_base_url; ?>/forms/edit.php?form_id=<?php echo CLASS_OBSERVATION_FORM_ID_CONFIG; ?>" class="list-group-item list-group-item-action">ویرایش ساختار فرم بازدید کلاسی</a>
                <a href="class_submissions.php?form_id_filter=<?php echo SELF_ASSESSMENT_FORM_ID_CONFIG; ?>" class="list-group-item list-group-item-action">مشاهده همه پاسخ‌های خوداظهاری</a>
                <a href="class_submissions.php?form_id_filter=<?php echo CLASS_OBSERVATION_FORM_ID_CONFIG; ?>" class="list-group-item list-group-item-action">مشاهده همه پاسخ‌های بازدید کلاسی</a>
                 <a href="analysis.php" class="list-group-item list-group-item-action list-group-item-info">مشاهده آنالیزها و نمودارها</a>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
