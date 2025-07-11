<?php
// admin/monitoring/analysis.php
require_once __DIR__ . '/../includes/header.php';

// --- Data Fetching and Filtering ---
// For demonstration, let's assume we want to analyze 'self_assessment' forms
// We might want to filter by: specific Form (template), Class, Teacher, Date Range

$available_forms_for_analysis_q = $conn->query("SELECT FormID, FormName FROM Forms WHERE FormPurpose IN ('self_assessment', 'class_observation') ORDER BY FormName");
$forms_for_select = [];
if($available_forms_for_analysis_q){ while($f_an = $available_forms_for_analysis_q->fetch_assoc()) $forms_for_select[] = $f_an; $available_forms_for_analysis_q->close(); }

$selected_form_id_analysis = isset($_GET['form_id_analysis']) ? (int)$_GET['form_id_analysis'] : null;
$selected_class_id_analysis = isset($_GET['class_id_analysis']) ? (int)$_GET['class_id_analysis'] : null;
$selected_teacher_id_analysis = isset($_GET['teacher_id_analysis']) ? (int)$_GET['teacher_id_analysis'] : null;
$selected_academic_year_analysis = isset($_GET['academic_year_analysis']) ? trim($_GET['academic_year_analysis']) : null;
$selected_date_from_analysis = isset($_GET['date_from_analysis']) && !empty($_GET['date_from_analysis']) ? trim($_GET['date_from_analysis']) : null;
$selected_date_to_analysis = isset($_GET['date_to_analysis']) && !empty($_GET['date_to_analysis']) ? trim($_GET['date_to_analysis']) : null;

$analysis_data = [];
$form_fields_for_analysis_header = [];
$form_name_analyzed = "";

if ($selected_form_id_analysis) {
    // Get form name
    $fn_stmt = $conn->prepare("SELECT FormName FROM Forms WHERE FormID = ?");
    if($fn_stmt){ $fn_stmt->bind_param("i", $selected_form_id_analysis); $fn_stmt->execute(); $fn_res = $fn_stmt->get_result(); if($fn_row = $fn_res->fetch_assoc()) $form_name_analyzed = $fn_row['FormName']; $fn_stmt->close(); }

    // Get fields of the selected form that might be analyzable (e.g., numeric, select, radio)
    $stmt_fields_an = $conn->prepare("SELECT FieldID, FieldName, FieldType, Options FROM FormFields WHERE FormID = ? AND FieldType IN ('number', 'select', 'radio', 'checkbox') ORDER BY SortOrder");
    if($stmt_fields_an){
        $stmt_fields_an->bind_param("i", $selected_form_id_analysis); $stmt_fields_an->execute(); $res_fields_an = $stmt_fields_an->get_result();
        while($f_row_an = $res_fields_an->fetch_assoc()){ $form_fields_for_analysis_header[$f_row_an['FieldID']] = $f_row_an; }
        $stmt_fields_an->close();
    }

    // Fetch submission values for these fields
    // This query can become very complex for advanced analysis. This is a basic example.
    $sql_analysis_data = "
        SELECT fs.SubmissionID, fs.UserID, fs.ClassID, fs.SubmissionDate, u.Username, cl.ClassName,
               fsv.FormFieldID, ff.FieldName, ff.FieldType, fsv.FieldValue
        FROM FormSubmissions fs
        JOIN FormSubmissionValues fsv ON fs.SubmissionID = fsv.SubmissionID
        JOIN FormFields ff ON fsv.FormFieldID = ff.FieldID
        JOIN Users u ON fs.UserID = u.UserID
        LEFT JOIN Classes cl ON fs.ClassID = cl.ClassID
        WHERE fs.FormID = ? AND ff.FieldType IN ('number', 'select', 'radio', 'checkbox')
    ";
    $params_analysis = [$selected_form_id_analysis]; $types_analysis = "i";

    if($selected_class_id_analysis){ $sql_analysis_data .= " AND fs.ClassID = ?"; $params_analysis[] = $selected_class_id_analysis; $types_analysis .= "i"; }
    if($selected_teacher_id_analysis){ $sql_analysis_data .= " AND fs.UserID = ?"; $params_analysis[] = $selected_teacher_id_analysis; $types_analysis .= "i"; }
    if($selected_academic_year_analysis){ $sql_analysis_data .= " AND cl.AcademicYear = ?"; $params_analysis[] = $selected_academic_year_analysis; $types_analysis .= "s"; }
    if($selected_date_from_analysis){
        $date_from_gregorian = !empty($selected_date_from_analysis) ? to_gregorian_date_for_db($selected_date_from_analysis) : null;
        if ($date_from_gregorian) {
            $sql_analysis_data .= " AND fs.SubmissionDate >= ?"; $params_analysis[] = $date_from_gregorian . " 00:00:00"; $types_analysis .= "s";
        }
    }
    if($selected_date_to_analysis){
        $date_to_gregorian = !empty($selected_date_to_analysis) ? to_gregorian_date_for_db($selected_date_to_analysis) : null;
        if ($date_to_gregorian) {
            $sql_analysis_data .= " AND fs.SubmissionDate <= ?"; $params_analysis[] = $date_to_gregorian . " 23:59:59"; $types_analysis .= "s";
        }
    }

    $sql_analysis_data .= " ORDER BY fs.SubmissionDate DESC, fs.SubmissionID ASC, ff.SortOrder ASC";

    $stmt_analysis = $conn->prepare($sql_analysis_data);
    if($stmt_analysis){
        $stmt_analysis->bind_param($types_analysis, ...$params_analysis); $stmt_analysis->execute(); $res_analysis = $stmt_analysis->get_result();
        $temp_analysis_data = [];
        while($row_an = $res_analysis->fetch_assoc()){
            $temp_analysis_data[$row_an['SubmissionID']]['details'] = ['UserID' => $row_an['UserID'], 'Username' => $row_an['Username'], 'ClassID' => $row_an['ClassID'], 'ClassName' => $row_an['ClassName'], 'SubmissionDate' => $row_an['SubmissionDate']];
            $temp_analysis_data[$row_an['SubmissionID']]['fields'][$row_an['FormFieldID']] = ['value' => $row_an['FieldValue'], 'name' => $row_an['FieldName'], 'type' => $row_an['FieldType']];
        }
        // Process $temp_analysis_data to generate summaries, averages etc.
        // For numeric:
        foreach($form_fields_for_analysis_header as $field_id_an => $field_info_an){
            if($field_info_an['FieldType'] == 'number'){
                $sum = 0; $count = 0; $values_num = [];
                foreach($temp_analysis_data as $sub_id_an => $sub_data_an){
                    if(isset($sub_data_an['fields'][$field_id_an]) && is_numeric($sub_data_an['fields'][$field_id_an]['value'])){
                        $num_val = floatval($sub_data_an['fields'][$field_id_an]['value']);
                        $sum += $num_val; $count++; $values_num[] = $num_val;
                    }
                }
                if($count > 0) $analysis_data[$field_id_an]['numeric_avg'] = round($sum / $count, 2);
                else $analysis_data[$field_id_an]['numeric_avg'] = 'N/A';
                $analysis_data[$field_id_an]['numeric_count'] = $count;
            } elseif (in_array($field_info_an['FieldType'], ['select', 'radio', 'checkbox'])) {
                $options_counts = [];
                if($field_info_an['FieldType'] == 'checkbox'){ // Value is JSON array
                    foreach($temp_analysis_data as $sub_id_an_cb => $sub_data_an_cb){
                        if(isset($sub_data_an_cb['fields'][$field_id_an])){
                            $decoded_cb_vals = json_decode($sub_data_an_cb['fields'][$field_id_an]['value'], true);
                            if(is_array($decoded_cb_vals)){ foreach($decoded_cb_vals as $cb_val) $options_counts[$cb_val] = ($options_counts[$cb_val] ?? 0) + 1; }
                        }
                    }
                } else { // Single value for select/radio
                     foreach($temp_analysis_data as $sub_id_an_s => $sub_data_an_s){
                        if(isset($sub_data_an_s['fields'][$field_id_an])){ $val_s = $sub_data_an_s['fields'][$field_id_an]['value']; $options_counts[$val_s] = ($options_counts[$val_s] ?? 0) + 1;}
                    }
                }
                arsort($options_counts); // Sort by frequency
                $analysis_data[$field_id_an]['options_freq'] = $options_counts;
            }
        }
    }
}

// Fetch classes for filter
$classes_q_an = $conn->query("SELECT ClassID, ClassName, AcademicYear FROM Classes WHERE IsActive = TRUE ORDER BY AcademicYear DESC, ClassName ASC");
$available_classes_an = [];
if($classes_q_an){ while($c_an = $classes_q_an->fetch_assoc()) $available_classes_an[] = $c_an; $classes_q_an->close(); }

// Fetch teachers (Users with a role that indicates they are teachers, e.g., UserType 'teacher' or specific RoleID)
// For simplicity, assuming UserType 'teacher' or if a more complex role system, adjust query
$teachers_q_an = $conn->query("SELECT UserID, Username, FullName FROM Users WHERE UserType = 'teacher' AND IsActive = TRUE ORDER BY FullName ASC"); // Or filter by RoleID
$available_teachers_an = [];
if($teachers_q_an){ while($t_an = $teachers_q_an->fetch_assoc()) $available_teachers_an[] = $t_an; $teachers_q_an->close(); }

// Get distinct academic years from Classes table
$academic_years_an = [];
foreach($available_classes_an as $class_data_ay){
    if(!in_array($class_data_ay['AcademicYear'], $academic_years_an)){
        $academic_years_an[] = $class_data_ay['AcademicYear'];
    }
}
sort($academic_years_an); // Optional: sort them

?>
<link rel="stylesheet" href="/my_site/assets/css/common/persian-datepicker.min.css"/>
<div class="page-header">
    <h1>آنالیز و تحلیل داده‌های نظارتی</h1>
    <p class="page-subtitle">بررسی آماری پاسخ‌های ثبت شده برای فرم‌های خوداظهاری و بازدید کلاسی.</p>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="analysis.php" class="form-inline-flex">
            <div class="form-group"><label for="form_id_analysis_select" class="mr-2">انتخاب فرم:</label>
                <select name="form_id_analysis" id="form_id_analysis_select" class="form-control custom-select">
                    <option value="">-- همه فرم‌های نظارتی --</option>
                    <?php foreach($forms_for_select as $form_opt_an): ?>
                    <option value="<?php echo $form_opt_an['FormID']; ?>" <?php if($selected_form_id_analysis == $form_opt_an['FormID']) echo 'selected';?>><?php echo htmlspecialchars($form_opt_an['FormName']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label for="class_id_analysis_select" class="mr-2">کلاس:</label>
                <select name="class_id_analysis" id="class_id_analysis_select" class="form-control custom-select">
                    <option value="">-- همه کلاس‌ها --</option>
                    <?php foreach($available_classes_an as $class_opt_an): ?>
                    <option value="<?php echo $class_opt_an['ClassID']; ?>" <?php if($selected_class_id_analysis == $class_opt_an['ClassID']) echo 'selected';?>><?php echo htmlspecialchars($class_opt_an['ClassName'].' ('.$class_opt_an['AcademicYear'].')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
             <div class="form-group"><label for="teacher_id_analysis_select" class="mr-2">مدرس:</label>
                <select name="teacher_id_analysis" id="teacher_id_analysis_select" class="form-control custom-select">
                    <option value="">-- همه مدرسین --</option>
                    <?php foreach($available_teachers_an as $teacher_opt_an): ?>
                    <option value="<?php echo $teacher_opt_an['UserID']; ?>" <?php if($selected_teacher_id_analysis == $teacher_opt_an['UserID']) echo 'selected';?>><?php echo htmlspecialchars($teacher_opt_an['FullName'] ?: $teacher_opt_an['Username']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label for="academic_year_analysis_select" class="mr-2">سال تحصیلی:</label>
                <select name="academic_year_analysis" id="academic_year_analysis_select" class="form-control custom-select">
                    <option value="">-- همه سال‌ها --</option>
                    <?php foreach($academic_years_an as $year_opt_an): ?>
                    <option value="<?php echo htmlspecialchars($year_opt_an); ?>" <?php if($selected_academic_year_analysis == $year_opt_an) echo 'selected';?>><?php echo htmlspecialchars($year_opt_an); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label for="date_from_analysis" class="mr-2">از تاریخ:</label>
                <input type="text" name="date_from_analysis" id="date_from_analysis" class="form-control persian-date-picker" value="<?php echo htmlspecialchars($selected_date_from_analysis ?? ''); ?>" placeholder="مثال: ۱۴۰۲/۰۱/۰۱">
            </div>
            <div class="form-group"><label for="date_to_analysis" class="mr-2">تا تاریخ:</label>
                <input type="text" name="date_to_analysis" id="date_to_analysis" class="form-control persian-date-picker" value="<?php echo htmlspecialchars($selected_date_to_analysis ?? ''); ?>" placeholder="مثال: ۱۴۰۲/۱۲/۲۹">
            </div>
            <button type="submit" class="btn btn-primary">نمایش آمار</button>
            <a href="analysis.php" class="btn btn-outline-secondary ml-2">پاک کردن فیلترها</a>
        </form>
    </div>
</div>

<?php if ($selected_form_id_analysis && !empty($form_fields_for_analysis_header)): ?>
<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">نتایج تحلیل برای فرم: "<?php echo htmlspecialchars($form_name_analyzed); ?>"
        <?php
            $filter_details = [];
            if($selected_class_id_analysis && ($class_key = array_search($selected_class_id_analysis, array_column($available_classes_an, 'ClassID'))) !== false) $filter_details[] = "کلاس: ".htmlspecialchars($available_classes_an[$class_key]['ClassName']);
            if($selected_teacher_id_analysis && ($teacher_key = array_search($selected_teacher_id_analysis, array_column($available_teachers_an, 'UserID'))) !== false) $filter_details[] = "مدرس: ".htmlspecialchars($available_teachers_an[$teacher_key]['FullName'] ?: $available_teachers_an[$teacher_key]['Username']);
            if($selected_academic_year_analysis) $filter_details[] = "سال: ".htmlspecialchars($selected_academic_year_analysis);
            if($selected_date_from_analysis) $filter_details[] = "از: ".htmlspecialchars($selected_date_from_analysis);
            if($selected_date_to_analysis) $filter_details[] = "تا: ".htmlspecialchars($selected_date_to_analysis);
            if(!empty($filter_details)) echo " <small class='text-muted'>(".implode(" - ", $filter_details).")</small>";
        ?>
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($analysis_data)): ?>
            <p class="text-muted">داده‌ای برای تحلیل با فیلترهای انتخابی یافت نشد.</p>
        <?php else: ?>
            <div class="row">
            <?php foreach($form_fields_for_analysis_header as $field_id_disp => $field_info_disp): ?>
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card h-100">
                        <div class="card-header bg-light py-2"><small class="font-weight-bold"><?php echo htmlspecialchars($field_info_disp['FieldName']); ?></small> <span class="badge badge-secondary float-left"><?php echo $field_type_options[$field_info_disp['FieldType']] ?? $field_info_disp['FieldType']; ?></span></div>
                        <div class="card-body small p-2">
                            <?php if ($field_info_disp['FieldType'] == 'number' && isset($analysis_data[$field_id_disp])): ?>
                                <p class="mb-1"><strong>میانگین:</strong> <?php echo $analysis_data[$field_id_disp]['numeric_avg']; ?></p>
                                <p class="mb-0"><strong>تعداد پاسخ‌های عددی:</strong> <?php echo $analysis_data[$field_id_disp]['numeric_count']; ?></p>
                                <!-- TODO: Chart for number distribution -->
                            <?php elseif (in_array($field_info_disp['FieldType'], ['select', 'radio', 'checkbox']) && isset($analysis_data[$field_id_disp]['options_freq'])): ?>
                                <?php if(empty($analysis_data[$field_id_disp]['options_freq'])): ?>
                                    <p class="text-muted mb-0">پاسخی برای این گزینه‌ها ثبت نشده.</p>
                                <?php else: ?>
                                <ul class="list-unstyled mb-0">
                                <?php $opt_count = 0; foreach($analysis_data[$field_id_disp]['options_freq'] as $option_val => $freq): if($opt_count++ >= 5 && count($analysis_data[$field_id_disp]['options_freq']) > 6) { echo "<li>... و موارد دیگر</li>"; break; } /* Limit display */ ?>
                                    <li><?php echo htmlspecialchars($option_val); ?>: <span class="badge badge-info"><?php echo $freq; ?></span> بار</li>
                                <?php endforeach; ?>
                                </ul>
                                <?php endif; ?>
                                <!-- TODO: Bar chart for option frequencies -->
                            <?php else: ?>
                                <p class="text-muted mb-0">تحلیل برای این نوع فیلد هنوز پشتیبانی نمی‌شود یا داده‌ای ندارد.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php elseif ($selected_form_id_analysis && empty($form_fields_for_analysis_header)): ?>
    <div class="alert alert-warning">فرم انتخاب شده فاقد فیلدهای قابل تحلیل (عددی، انتخابی) است.</div>
<?php elseif ($selected_form_id_analysis): ?>
     <div class="alert alert-info">داده‌ای برای تحلیل یافت نشد.</div>
<?php else: ?>
    <div class="alert alert-info">لطفاً یک فرم را برای مشاهده آمار و تحلیل انتخاب کنید.</div>
<?php endif; ?>

<style>.card-header small {font-size: 0.8em;} .form-inline-flex .form-group { margin-left: 10px; margin-bottom: 10px; }</style>
<script src="/my_site/assets/js/common/persian-date.min.js"></script>
<script src="/my_site/assets/js/common/persian-datepicker.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Persian Date Pickers
    var datePickers = document.querySelectorAll('.persian-date-picker');
    datePickers.forEach(function(picker) {
        new persianDatepicker(picker, {
            format: 'YYYY/MM/DD',
            autoClose: true,
            observer: true,
            calendar: {
                persian: {
                    locale: 'fa'
                }
            },
            toolbox:{
                calendarSwitch:{
                    enabled:false // Disable switching to Gregorian
                }
            }
        });
    });
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
```

این یک پیاده‌سازی بسیار اولیه برای بخش آنالیز است. قابلیت‌های پیشرفته‌تر مانند نمودارها، مقایسه‌ها و فیلترهای پیچیده‌تر نیاز به کار بسیار بیشتری دارند.
