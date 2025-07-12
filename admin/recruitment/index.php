<?php
require_once __DIR__ . '/../includes/header.php';

$recruitment_stats = [
    'total_prospects' => 0,
    'active_regions' => 0,
    'total_events' => 0,
    'prospects_current_month' => 0,
    'most_active_region_name' => '---',
    'most_active_region_count' => 0,
];

if ($conn) {
    try {
        // Total Prospects
        $stmt_total_prospects = $conn->query("SELECT COUNT(ProspectID) as count FROM RecruitmentProspects");
        $recruitment_stats['total_prospects'] = $stmt_total_prospects ? ($stmt_total_prospects->fetch_assoc()['count'] ?? 0) : 'خطا';

        // Active Regions (assuming an IsActive field or just count all)
        $stmt_active_regions = $conn->query("SELECT COUNT(RegionID) as count FROM RecruitmentRegions"); // Add WHERE IsActive = TRUE if applicable
        $recruitment_stats['active_regions'] = $stmt_active_regions ? ($stmt_active_regions->fetch_assoc()['count'] ?? 0) : 'خطا';

        // Total Recruitment Events
        $stmt_total_events = $conn->query("SELECT COUNT(EventID) as count FROM RecruitmentEvents");
        $recruitment_stats['total_events'] = $stmt_total_events ? ($stmt_total_events->fetch_assoc()['count'] ?? 0) : 'خطا';

        // Prospects this month
        $current_month_start = date('Y-m-01');
        $current_month_end = date('Y-m-t'); // 't' gives the last day of the month
        $stmt_prospects_month = $conn->prepare("SELECT COUNT(ProspectID) as count FROM RecruitmentProspects WHERE JoinedDate >= ? AND JoinedDate <= ?");
        if($stmt_prospects_month){
            $stmt_prospects_month->bind_param("ss", $current_month_start, $current_month_end);
            $stmt_prospects_month->execute();
            $recruitment_stats['prospects_current_month'] = $stmt_prospects_month->get_result()->fetch_assoc()['count'] ?? 0;
            $stmt_prospects_month->close();
        } else {$recruitment_stats['prospects_current_month'] = 'خطا';}

        // Most active region (based on number of prospects)
        // This query assumes RecruitmentProspects has a RegionID foreign key
        $stmt_active_region = $conn->query(
            "SELECT rr.RegionName, COUNT(rp.ProspectID) as prospect_count
             FROM RecruitmentRegions rr
             LEFT JOIN RecruitmentProspects rp ON rr.RegionID = rp.RegionID
             GROUP BY rr.RegionID, rr.RegionName
             ORDER BY prospect_count DESC
             LIMIT 1"
        );
        if ($stmt_active_region && $active_region_data = $stmt_active_region->fetch_assoc()) {
            $recruitment_stats['most_active_region_name'] = $active_region_data['RegionName'];
            $recruitment_stats['most_active_region_count'] = $active_region_data['prospect_count'];
        } elseif(!$stmt_active_region) {
            $recruitment_stats['most_active_region_name'] = 'خطا در کوئری';
        }

    } catch (Exception $e) {
        error_log("Recruitment dashboard data fetch error: " . $e->getMessage());
        // Set all to 'خطا' or keep default 0
        foreach ($recruitment_stats as $key => &$value) { if ($value === 0) $value = 'خطا'; }
    }
} else {
    foreach ($recruitment_stats as $key => &$value) { $value = 'خطا اتصال'; }
}
?>

<div class="page-header">
    <h1>داشبورد جذب و راه‌اندازی</h1>
    <p class="page-subtitle">نمای کلی از فعالیت‌ها و آمارهای مرتبط با جذب افراد جدید.</p>
     <div class="page-header-actions">
        <a href="prospects.php?action=create" class="btn btn-primary">
            <em class="bi bi-person-plus-fill icon"></em> ثبت فرد جدید
        </a>
        <a href="regions.php" class="btn btn-outline-secondary">
            <em class="bi bi-map icon"></em> مدیریت مناطق
        </a>
         <a href="events.php" class="btn btn-outline-secondary">
            <em class="bi bi-calendar-event icon"></em> مدیریت مراسم جذب
        </a>
    </div>
</div>

<?php if (isset($_SESSION['action_success_recruitment'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['action_success_recruitment']; unset($_SESSION['action_success_recruitment']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if (isset($_SESSION['action_error_recruitment'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['action_error_recruitment']; unset($_SESSION['action_error_recruitment']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>


<div class="row">
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card border-start border-primary border-4 shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col me-2">
                        <div class="text-xs fw-bold text-primary text-uppercase mb-1">کل افراد جذب شده</div>
                        <div class="h5 mb-0 fw-bold text-gray-800"><?php echo $recruitment_stats['total_prospects']; ?></div>
                    </div>
                    <div class="col-auto">
                        <em class="bi bi-people-fill fs-2 text-gray-300"></em>
                    </div>
                </div>
            </div>
             <a href="prospects.php" class="stretched-link"></a>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card border-start border-success border-4 shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col me-2">
                        <div class="text-xs fw-bold text-success text-uppercase mb-1">تعداد مناطق</div>
                        <div class="h5 mb-0 fw-bold text-gray-800"><?php echo $recruitment_stats['active_regions']; ?></div>
                    </div>
                    <div class="col-auto">
                         <em class="bi bi-map-fill fs-2 text-gray-300"></em>
                    </div>
                </div>
            </div>
            <a href="regions.php" class="stretched-link"></a>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card border-start border-info border-4 shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col me-2">
                        <div class="text-xs fw-bold text-info text-uppercase mb-1">مراسم‌های جذب</div>
                        <div class="h5 mb-0 fw-bold text-gray-800"><?php echo $recruitment_stats['total_events']; ?></div>
                    </div>
                    <div class="col-auto">
                        <em class="bi bi-calendar-event-fill fs-2 text-gray-300"></em>
                    </div>
                </div>
            </div>
             <a href="events.php" class="stretched-link"></a>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card border-start border-warning border-4 shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col me-2">
                        <div class="text-xs fw-bold text-warning text-uppercase mb-1">جذب ماه جاری</div>
                        <div class="h5 mb-0 fw-bold text-gray-800"><?php echo $recruitment_stats['prospects_current_month']; ?> نفر</div>
                    </div>
                    <div class="col-auto">
                        <em class="bi bi-person-plus-fill fs-2 text-gray-300"></em>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-2">
     <div class="col-lg-6 mb-4">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">فعال‌ترین منطقه جذب</h5>
            </div>
            <div class="card-body text-center">
                <em class="bi bi-pin-map-fill fs-1 text-danger mb-2 d-block"></em>
                <h3 class="mb-1"><?php echo htmlspecialchars($recruitment_stats['most_active_region_name']); ?></h3>
                <p class="text-muted mb-0">با <strong class="fs-5"><?php echo $recruitment_stats['most_active_region_count']; ?></strong> فرد جذب شده</p>
                <a href="prospects.php?region_id=<?php // TODO: Add region id if available ?>" class="btn btn-outline-danger btn-sm mt-3">مشاهده افراد این منطقه</a>
            </div>
        </div>
    </div>
    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">روند جذب در ۶ ماه گذشته (نمودار نمونه)</h5>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center" style="min-height: 200px;">
                <div class="text-center py-5">
                    <em class="bi bi-bar-chart-line-fill fs-1 text-gray-300"></em>
                    <p class="mt-2 text-muted">نمودار به زودی اضافه خواهد شد.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-2">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header"><h5 class="mb-0">نیازمند اقدام</h5></div>
            <div class="card-body">
                <p class="text-muted">در این بخش، مناطقی که نیاز به برگزاری مراسم دارند یا افرادی که نیاز به پیگیری بیشتر دارند، نمایش داده خواهند شد. (نیازمند پیاده‌سازی)</p>
                <ul>
                    <li>منطقه "الهیه": آخرین مراسم جذب ۶ ماه پیش برگزار شده است.</li>
                    <li>فرد "زهرا احمدی" (جذب شده از مراسم غدیر ۱۴۰۲): نیاز به تماس پیگیری.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
