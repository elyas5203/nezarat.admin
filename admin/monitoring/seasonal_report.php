<?php
// admin/monitoring/seasonal_report.php
require_once __DIR__ . '/../includes/header.php';

// --- Data Fetching and Filtering ---
$selected_class_id_report = isset($_GET['class_id_report']) ? (int)$_GET['class_id_report'] : null;
$selected_academic_year_report = isset($_GET['academic_year_report']) ? trim($_GET['academic_year_report']) : null; // Or derive from class
$report_date_from = isset($_GET['date_from_report']) && !empty($_GET['date_from_report']) ? trim($_GET['date_from_report']) : null;
$report_date_to = isset($_GET['date_to_report']) && !empty($_GET['date_to_report']) ? trim($_GET['date_to_report']) : null;

$report_data = [
    'class_info' => null,
    'self_assessment_count' => 0,
    'class_observation_count' => 0,
    'parent_meeting_status' => 'نامشخص', // Example field
    'key_notes_summary' => [], // Will hold summarized text or important notes
    'average_scores' => [] // For numeric fields from forms
];
$class_name_report = "";
$teacher_name_report = "";

// Fetch classes for filter
$classes_q_report = $conn->query("SELECT c.ClassID, c.ClassName, c.AcademicYear, u.FullName as TeacherName, u.Username as TeacherUsername
                                FROM Classes c
                                LEFT JOIN Users u ON c.PrimaryTeacherID = u.UserID
                                WHERE c.IsActive = TRUE ORDER BY c.AcademicYear DESC, c.ClassName ASC");
$available_classes_report = [];
if($classes_q_report){ while($c_rep = $classes_q_report->fetch_assoc()) $available_classes_report[] = $c_rep; $classes_q_report->close(); }

if ($selected_class_id_report && $report_date_from && $report_date_to) {
    // Get Class Info
    $stmt_class_info = $conn->prepare("SELECT c.ClassName, c.AcademicYear, u.FullName as TeacherFullName, u.Username as TeacherUsername
                                       FROM Classes c
                                       LEFT JOIN Users u ON c.PrimaryTeacherID = u.UserID
                                       WHERE c.ClassID = ?");
    if($stmt_class_info){
        $stmt_class_info->bind_param("i", $selected_class_id_report);
        $stmt_class_info->execute();
        $res_class_info = $stmt_class_info->get_result();
        if($class_info_row = $res_class_info->fetch_assoc()){
            $report_data['class_info'] = $class_info_row;
            $class_name_report = $class_info_row['ClassName'];
            $teacher_name_report = $class_info_row['TeacherFullName'] ?: $class_info_row['TeacherUsername'];
        }
        $stmt_class_info->close();
    }

    $date_from_gregorian_rep = to_gregorian_date_for_db($report_date_from) . " 00:00:00";
    $date_to_gregorian_rep = to_gregorian_date_for_db($report_date_to) . " 23:59:59";

    // Count Self-Assessments
    $stmt_sa_count = $conn->prepare("SELECT COUNT(DISTINCT fs.SubmissionID) as count
                                     FROM FormSubmissions fs
                                     JOIN Forms f ON fs.FormID = f.FormID
                                     WHERE fs.ClassID = ? AND f.FormPurpose = 'self_assessment'
                                     AND fs.SubmissionDate BETWEEN ? AND ?");
    if($stmt_sa_count){
        $stmt_sa_count->bind_param("iss", $selected_class_id_report, $date_from_gregorian_rep, $date_to_gregorian_rep);
        $stmt_sa_count->execute();
        $report_data['self_assessment_count'] = $stmt_sa_count->get_result()->fetch_assoc()['count'] ?? 0;
        $stmt_sa_count->close();
    }

    // Count Class Observations
    $stmt_co_count = $conn->prepare("SELECT COUNT(DISTINCT fs.SubmissionID) as count
                                     FROM FormSubmissions fs
                                     JOIN Forms f ON fs.FormID = f.FormID
                                     WHERE fs.ClassID = ? AND f.FormPurpose = 'class_observation'
                                     AND fs.SubmissionDate BETWEEN ? AND ?");
     if($stmt_co_count){
        $stmt_co_count->bind_param("iss", $selected_class_id_report, $date_from_gregorian_rep, $date_to_gregorian_rep);
        $stmt_co_count->execute();
        $report_data['class_observation_count'] = $stmt_co_count->get_result()->fetch_assoc()['count'] ?? 0;
        $stmt_co_count->close();
    }

    // TODO: Fetch Parent Meeting Status (this needs a dedicated table or field)
    // For now, it's a placeholder. It might come from EventCalendar or a specific meetings table for the class.
    // Example: if (class had parent meeting in range) $report_data['parent_meeting_status'] = 'برگزار شده';
    // else $report_data['parent_meeting_status'] = 'برگزار نشده یا در محدوده نیست';

    // TODO: Fetch Key Notes / Summary from text fields of submitted forms (complex, requires text processing or manual input)
    // This is a simplified example, fetching some text from the latest observation form if available
    $stmt_key_notes = $conn->prepare("
        SELECT fsv.FieldValue
        FROM FormSubmissionValues fsv
        JOIN FormSubmissions fs ON fsv.SubmissionID = fs.SubmissionID
        JOIN FormFields ff ON fsv.FormFieldID = ff.FieldID
        JOIN Forms f ON fs.FormID = f.FormID
        WHERE fs.ClassID = ? AND f.FormPurpose = 'class_observation' AND ff.FieldType = 'textarea'
        AND fs.SubmissionDate BETWEEN ? AND ?
        ORDER BY fs.SubmissionDate DESC, fs.SubmissionID DESC LIMIT 3
    "); // Get up to 3 recent textarea values from observations
    if($stmt_key_notes){
        $stmt_key_notes->bind_param("iss", $selected_class_id_report, $date_from_gregorian_rep, $date_to_gregorian_rep);
        $stmt_key_notes->execute();
        $res_key_notes = $stmt_key_notes->get_result();
        while($kn_row = $res_key_notes->fetch_assoc()){
            $report_data['key_notes_summary'][] = $kn_row['FieldValue'];
        }
        $stmt_key_notes->close();
    }

    // TODO: Calculate Average Scores for key numeric questions (similar to analysis page but summarized)
    // This part would iterate over specific, predefined "key" questions for the report.
    // For now, this is a placeholder.

}
?>
<link rel="stylesheet" href="/my_site/assets/css/common/persian-datepicker.min.css"/>
<div class="page-header">
    <h1>گزارش عملکرد فصلی/دوره‌ای کلاس</h1>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="seasonal_report.php" class="form-inline-flex">
            <div class="form-group">
                <label for="class_id_report_select" class="mr-2">انتخاب کلاس:</label>
                <select name="class_id_report" id="class_id_report_select" class="form-control custom-select" required>
                    <option value="">-- انتخاب کنید --</option>
                    <?php foreach($available_classes_report as $class_opt_rep): ?>
                    <option value="<?php echo $class_opt_rep['ClassID']; ?>" <?php if($selected_class_id_report == $class_opt_rep['ClassID']) echo 'selected';?>>
                        <?php echo htmlspecialchars($class_opt_rep['ClassName'].' ('.$class_opt_rep['AcademicYear'].') - م: ' . ($class_opt_rep['TeacherName'] ?: $class_opt_rep['TeacherUsername'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="date_from_report" class="mr-2">از تاریخ:</label>
                <input type="text" name="date_from_report" id="date_from_report" class="form-control persian-date-picker" value="<?php echo htmlspecialchars($report_date_from ?? ''); ?>" placeholder="مثال: ۱۴۰۲/۰۷/۰۱" required>
            </div>
            <div class="form-group">
                <label for="date_to_report" class="mr-2">تا تاریخ:</label>
                <input type="text" name="date_to_report" id="date_to_report" class="form-control persian-date-picker" value="<?php echo htmlspecialchars($report_date_to ?? ''); ?>" placeholder="مثال: ۱۴۰۲/۰۹/۳۰" required>
            </div>
            <button type="submit" class="btn btn-primary">تولید گزارش</button>
            <a href="seasonal_report.php" class="btn btn-outline-secondary ml-2">پاک کردن</a>
        </form>
    </div>
</div>

<?php if ($selected_class_id_report && $report_date_from && $report_date_to && $report_data['class_info']): ?>
<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">
            گزارش کلاس: <?php echo htmlspecialchars($class_name_report); ?>
            (مدرس: <?php echo htmlspecialchars($teacher_name_report); ?>)
            - دوره: <?php echo htmlspecialchars(to_jalali_date($report_date_from, false)); ?> تا <?php echo htmlspecialchars(to_jalali_date($report_date_to, false)); ?>
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <div class="report-metric">
                    <h6>تعداد فرم‌های خوداظهاری:</h6>
                    <p><?php echo $report_data['self_assessment_count']; ?> عدد</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="report-metric">
                    <h6>تعداد بازدیدهای کلاسی:</h6>
                    <p><?php echo $report_data['class_observation_count']; ?> عدد</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="report-metric">
                    <h6>وضعیت جلسه اولیا در این دوره:</h6>
                    <p><?php echo htmlspecialchars($report_data['parent_meeting_status']); ?> <small>(نیاز به تکمیل منطق)</small></p>
                </div>
            </div>
        </div>
        <hr>
        <h6>نکات کلیدی / موارد قابل توجه (از بازدیدها):</h6>
        <?php if (!empty($report_data['key_notes_summary'])): ?>
            <ul>
                <?php foreach($report_data['key_notes_summary'] as $note): ?>
                    <li><?php echo nl2br(htmlspecialchars(mb_substr($note, 0, 250) . (mb_strlen($note) > 250 ? '...' : ''))); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="text-muted">نکته برجسته‌ای (از بخش متنی بازدیدها) برای نمایش در این دوره یافت نشد.</p>
        <?php endif; ?>
        <hr>
        <h6>تحلیل آماری (نمونه):</h6>
        <p class="text-muted">بخش مربوط به میانگین نمرات و تحلیل‌های آماری بیشتر، در این مرحله تکمیل نشده و نیاز به تعریف سوالات کلیدی و منطق agreggration دارد.</p>
        <!-- Placeholder for average scores or other stats -->
        <?php /*
        <?php if (!empty($report_data['average_scores'])): ?>
            <ul>
                <?php foreach($report_data['average_scores'] as $question_label => $avg_score): ?>
                    <li><strong><?php echo htmlspecialchars($question_label); ?>:</strong> <?php echo htmlspecialchars($avg_score); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>داده آماری برای نمایش موجود نیست.</p>
        <?php endif; ?>
        */ ?>
        <div class="mt-4">
            <button onclick="window.print();" class="btn btn-info"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-printer" viewBox="0 0 16 16" style="vertical-align: -2px; margin-left: 5px;"><path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/><path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4V3zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2H5zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1z"/></svg> چاپ گزارش</button>
        </div>
    </div>
</div>

<style>
.report-metric { margin-bottom: 1.5rem; padding: 1rem; background-color: #f8f9fa; border-radius: .25rem; }
.report-metric h6 { font-size: 0.9rem; color: #6c757d; margin-bottom: 0.25rem; }
.report-metric p { font-size: 1.2rem; font-weight: bold; margin-bottom: 0; }
.form-inline-flex .form-group { margin-left: 10px; margin-bottom: 10px; }
@media print {
  body * { visibility: hidden; }
  .card.shadow-sm, .card.shadow-sm * { visibility: visible; }
  .card.shadow-sm { position: absolute; left: 0; top: 0; width: 100%; margin: 0; padding:0; border: none; box-shadow: none !important;}
  .card-header h5 {font-size: 1.2rem;}
  .btn, .form-inline-flex, .page-header, .sidebar, .main-header { display: none !important; }
}
</style>
<script src="/my_site/assets/js/common/persian-date.min.js"></script>
<script src="/my_site/assets/js/common/persian-datepicker.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var datePickers = document.querySelectorAll('.persian-date-picker');
    datePickers.forEach(function(picker) {
        new persianDatepicker(picker, {
            format: 'YYYY/MM/DD',
            autoClose: true,
            observer: true,
            calendar: { persian: { locale: 'fa'}},
            toolbox:{ calendarSwitch:{ enabled:false }}
        });
    });
});
</script>
<?php else: ?>
    <?php if (!empty($_GET)): // if form submitted but no results / incomplete parameters ?>
        <div class="alert alert-warning">لطفاً کلاس و بازه زمانی را به طور کامل برای تولید گزارش انتخاب کنید.</div>
    <?php else: ?>
        <div class="alert alert-info">برای مشاهده گزارش، لطفاً یک کلاس و بازه زمانی (از تاریخ و تا تاریخ) را انتخاب کنید.</div>
    <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
