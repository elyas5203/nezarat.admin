<?php
require_once __DIR__ . '/../includes/header.php';

$page_title_analysis_page = "آنالیز داده‌های نظارت";
$form_errors_analysis_page = [];

// Filters for analysis
$filter_form_type_analysis_val = isset($_GET['form_type']) ? sanitize_input($_GET['form_type']) : 'all';
$filter_class_id_analysis_val = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;
$filter_academic_year_analysis_val = isset($_GET['academic_year']) ? sanitize_input($_GET['academic_year']) : null;
$filter_date_from_analysis_jalali = sanitize_input($_GET['date_from'] ?? '');
$filter_date_to_analysis_jalali = sanitize_input($_GET['date_to'] ?? '');

// Fetch supporting data for filters
$available_classes_analysis_list = [];
$academic_years_analysis_list = []; // Unique academic years

if($conn){
    $res_cls_an_page = $conn->query("SELECT ClassID, ClassName, AcademicYear FROM Classes ORDER BY AcademicYear DESC, ClassName ASC");
    if($res_cls_an_page) {
        while($row_an_cls = $res_cls_an_page->fetch_assoc()) {
            $available_classes_analysis_list[] = $row_an_cls;
            if(!in_array($row_an_cls['AcademicYear'], $academic_years_analysis_list)) {
                $academic_years_analysis_list[] = $row_an_cls['AcademicYear'];
            }
        }
        // Sort unique academic years, could be done via SQL too
        rsort($academic_years_analysis_list, SORT_STRING | SORT_FLAG_CASE);
    } else {
        $form_errors_analysis_page['fetch_classes'] = "خطا در بارگذاری کلاس‌ها: " . $conn->error;
    }
} else {
    $form_errors_analysis_page['db_conn'] = "خطا در اتصال به پایگاه داده.";
}

// TODO: Analysis Logic based on filters
// This section will be significantly expanded once the data structure and analysis requirements are finalized.
// For now, using placeholder data for summary and chart.

$analysis_results_summary_display = [
    'avg_self_assessment_score' => '۷۵.۳', // Placeholder
    'avg_observation_score' => '۸۰.۱',   // Placeholder
    'self_vs_observation_consistency' => 'بالا (۸۵٪)', // Placeholder
    'top_performing_classes' => [ // Placeholder
        ['name' => 'کلاس پنجم الف (۱۴۰۲-۱۴۰۳)', 'score' => '۹۰/۱۰۰', 'id' => 1],
        ['name' => 'کلاس ششم ب (۱۴۰۲-۱۴۰۳)', 'score' => '۸۸/۱۰۰', 'id' => 2]
    ],
    'classes_needing_attention' => [ // Placeholder
        ['name' => 'کلاس چهارم ج (۱۴۰۲-۱۴۰۳)', 'score' => '۶۰/۱۰۰', 'id' => 3],
        ['name' => 'کلاس سوم د (۱۴۰۱-۱۴۰۲)', 'score' => '۵۵/۱۰۰', 'id' => 4]
    ],
];
$chart_data_json_output = json_encode([ // Placeholder data for chart
    'labels' => ['مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'],
    'datasets' => [
        [
            'label' => 'میانگین امتیاز خوداظهاری ماهانه',
            'data' => [65, 59, 70, 81, 66, 75],
            'borderColor' => 'rgba(54, 162, 235, 1)',
            'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
            'fill' => true,
            'tension' => 0.2
        ],
        [
            'label' => 'میانگین امتیاز بازدید کلاسی ماهانه',
            'data' => [70, 68, 72, 75, 69, 78],
            'borderColor' => 'rgba(255, 99, 132, 1)',
            'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
            'fill' => true,
            'tension' => 0.2
        ]
    ]
], JSON_UNESCAPED_UNICODE);

?>
<div class="page-header">
    <h1><?php echo $page_title_analysis_page; ?></h1>
    <p class="page-subtitle">تحلیل و مقایسه داده‌های جمع‌آوری شده از فرم‌های خوداظهاری و بازدیدهای کلاسی.</p>
    <div class="page-header-actions">
        <a href="index.php" class="btn btn-outline-secondary"><em class="bi bi-house-door icon"></em> داشبورد نظارت</a>
        <a href="class_submissions.php" class="btn btn-outline-primary ms-2"><em class="bi bi-card-list icon"></em> مشاهده پاسخ‌ها</a>
    </div>
</div>

<?php if(!empty($form_errors_analysis_page)):?><div class="alert alert-danger alert-dismissible fade show"><strong>خطا:</strong><ul class="mb-0 ps-3"><?php foreach($form_errors_analysis_page as $e_an_p=>$e_msg_an_p):echo "<li>".htmlspecialchars($e_msg_an_p)."</li>";endforeach;?></ul><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif;?>

<div class="filter-search-bar card card-body mb-4">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-3"><label for="form_type_an_page" class="form-label">نوع فرم:</label><select name="form_type" id="form_type_an_page" class="form-select form-select-sm">
            <option value="all" <?php echo ($filter_form_type_analysis_val=='all')?'selected':'';?>>همه فرم‌های نظارت</option>
            <option value="self_assessment" <?php echo ($filter_form_type_analysis_val=='self_assessment')?'selected':'';?>>خوداظهاری (ID: <?php echo SELF_ASSESSMENT_FORM_ID_MONITORING; ?>)</option>
            <option value="observation" <?php echo ($filter_form_type_analysis_val=='observation')?'selected':'';?>>بازدید کلاسی (ID: <?php echo CLASS_OBSERVATION_FORM_ID_MONITORING; ?>)</option>
        </select></div>
        <div class="col-md-3"><label for="class_id_an_page" class="form-label">کلاس:</label><select name="class_id" id="class_id_an_page" class="form-select form-select-sm"><option value="">همه کلاس‌ها</option><?php foreach($available_classes_analysis_list as $cls_an_opt):?><option value="<?php echo $cls_an_opt['ClassID'];?>" <?php echo ($filter_class_id_analysis_val==$cls_an_opt['ClassID'])?'selected':'';?>><?php echo htmlspecialchars($cls_an_opt['ClassName'].' ('.$cls_an_opt['AcademicYear'].')');?></option><?php endforeach;?></select></div>
        <div class="col-md-2"><label for="academic_year_an_page" class="form-label">سال تحصیلی:</label><select name="academic_year" id="academic_year_an_page" class="form-select form-select-sm"><option value="">همه سال‌ها</option><?php foreach($academic_years_analysis_list as $ay_an_opt):?><option value="<?php echo $ay_an_opt;?>" <?php echo ($filter_academic_year_analysis_val==$ay_an_opt)?'selected':'';?>><?php echo $ay_an_opt;?></option><?php endforeach;?></select></div>
        <div class="col-md-2"><label for="date_from_an_page" class="form-label">از تاریخ:</label><input type="text" name="date_from" id="date_from_an_page" class="form-control form-control-sm persian-datepicker" value="<?php echo htmlspecialchars($filter_date_from_analysis_jalali);?>"></div>
        <div class="col-md-2"><label for="date_to_an_page" class="form-label">تا تاریخ:</label><input type="text" name="date_to" id="date_to_an_page" class="form-control form-control-sm persian-datepicker" value="<?php echo htmlspecialchars($filter_date_to_analysis_jalali);?>"></div>
        <div class="col-md-auto mt-3"><button type="submit" class="btn btn-primary w-100 btn-sm">اعمال فیلتر و نمایش آنالیز</button></div>
    </form>
</div>

<div class="alert alert-info">
    <em class="bi bi-info-circle-fill me-2"></em>
    <strong>توجه:</strong> بخش آنالیز داده‌ها در حال توسعه است. داده‌ها و نمودارهای نمایش داده شده در زیر صرفاً نمونه هستند.
</div>

<div class="row gy-4">
    <div class="col-md-6 col-lg-4"><div class="card text-center h-100 shadow-sm"><div class="card-body"><h5 class="card-title text-primary">میانگین امتیاز خوداظهاری‌ها</h5><p class="display-4 fw-bold text-primary my-3"><?php echo $analysis_results_summary_display['avg_self_assessment_score']; ?></p><small class="text-muted">بر اساس فیلترهای انتخابی</small></div></div></div>
    <div class="col-md-6 col-lg-4"><div class="card text-center h-100 shadow-sm"><div class="card-body"><h5 class="card-title text-success">میانگین امتیاز بازدیدهای کلاسی</h5><p class="display-4 fw-bold text-success my-3"><?php echo $analysis_results_summary_display['avg_observation_score']; ?></p><small class="text-muted">بر اساس فیلترهای انتخابی</small></div></div></div>
    <div class="col-md-12 col-lg-4"><div class="card text-center h-100 shadow-sm"><div class="card-body"><h5 class="card-title text-info">میزان همخوانی خوداظهاری و بازدید</h5><p class="display-4 fw-bold text-info my-3"><?php echo $analysis_results_summary_display['self_vs_observation_consistency']; ?></p><small class="text-muted">شاخص تطابق (نمونه)</small></div></div></div>
</div>

<div class="row mt-4">
    <div class="col-lg-12">
        <div class="card shadow-sm">
            <div class="card-header"><h5 class="mb-0">نمودار روند امتیازات (نمونه ماهانه)</h5></div>
            <div class="card-body"><canvas id="monitoringTrendsChartPage" style="max-height: 350px;"></canvas></div>
        </div>
    </div>
</div>

<div class="row mt-4 gy-4">
    <div class="col-md-6">
        <div class="card shadow-sm h-100"><div class="card-header"><h5 class="mb-0">کلاس‌های با عملکرد برتر (نمونه)</h5></div>
        <div class="list-group list-group-flush">
            <?php foreach($analysis_results_summary_display['top_performing_classes'] as $class_perf_item): ?>
                <a href="<?php echo $admin_base_url; ?>/monitoring/seasonal_report.php?class_id=<?php echo $class_perf_item['id']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"><?php echo htmlspecialchars($class_perf_item['name']); ?> <span class="badge bg-success rounded-pill"><?php echo htmlspecialchars($class_perf_item['score']); ?></span></a>
            <?php endforeach; ?>
            <?php if(empty($analysis_results_summary_display['top_performing_classes'])):?><div class="list-group-item text-muted">داده‌ای برای نمایش نیست.</div><?php endif;?>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm h-100"><div class="card-header"><h5 class="mb-0">کلاس‌های نیازمند توجه (نمونه)</h5></div>
        <div class="list-group list-group-flush">
            <?php foreach($analysis_results_summary_display['classes_needing_attention'] as $class_attn_item): ?>
                <a href="<?php echo $admin_base_url; ?>/monitoring/seasonal_report.php?class_id=<?php echo $class_attn_item['id']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"><?php echo htmlspecialchars($class_attn_item['name']); ?> <span class="badge bg-warning text-dark rounded-pill"><?php echo htmlspecialchars($class_attn_item['score']); ?></span></a>
            <?php endforeach; ?>
             <?php if(empty($analysis_results_summary_display['classes_needing_attention'])):?><div class="list-group-item text-muted">داده‌ای برای نمایش نیست.</div><?php endif;?>
        </div></div>
    </div>
</div>

<link rel="stylesheet" href="<?php echo get_base_url(); ?>assets/js/persian-datepicker/persian-datepicker.min.css"/>
<script src="<?php echo get_base_url(); ?>assets/js/jquery.min.js"></script>
<script src="<?php echo get_base_url(); ?>assets/js/persian-datepicker/persian-date.min.js"></script>
<script src="<?php echo get_base_url(); ?>assets/js/persian-datepicker/persian-datepicker.min.js"></script>
<script src="<?php echo get_base_url(); ?>assets/js/chart.js/chart.min.js"></script>

<script>
$(document).ready(function(){
    if($(".persian-datepicker").length){$(".persian-datepicker").persianDatepicker({format:'YYYY/MM/DD',autoClose:true,observer:true,initialValue:false});}
    const ctxAnalysis = document.getElementById('monitoringTrendsChartPage');
    if (ctxAnalysis) {
        new Chart(ctxAnalysis, { type: 'line', data: <?php echo $chart_data_json_output; ?>, options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, suggestedMax: 100 } }, plugins: { legend: { position: 'bottom' }, title: { display: true, text: 'روند میانگین امتیازات ماهانه (نمونه)'} } } });
    }
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
