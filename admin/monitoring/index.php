<?php
// admin/monitoring/index.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth

// Fetch all active classes with teacher information
$stmt_classes_admin = $conn->prepare("
    SELECT c.ClassID, c.ClassName, c.GradeLevel, c.AcademicYear,
           CONCAT(u.FirstName, ' ', u.LastName) AS TeacherName, u.UserID AS TeacherUserID,
           (SELECT COUNT(*) FROM FormSubmissions fs JOIN Forms f ON fs.FormID = f.FormID WHERE fs.ClassID = c.ClassID AND f.FormPurpose = 'self_assessment') as SelfAssessmentCount,
           (SELECT COUNT(*) FROM FormSubmissions fs JOIN Forms f ON fs.FormID = f.FormID WHERE fs.ClassID = c.ClassID AND f.FormPurpose = 'class_observation') as ObservationCount
    FROM Classes c
    LEFT JOIN Users u ON c.TeacherUserID = u.UserID
    WHERE c.IsActive = TRUE
    ORDER BY c.AcademicYear DESC, c.GradeLevel ASC, c.ClassName ASC
");

$all_classes = [];
if ($stmt_classes_admin) {
    $stmt_classes_admin->execute();
    $result_classes_admin = $stmt_classes_admin->get_result();
    while ($row = $result_classes_admin->fetch_assoc()) {
        $all_classes[] = $row;
    }
    $stmt_classes_admin->close();
} else {
    // Use flash messages for errors that occur before full page load
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا در بارگذاری لیست کلاس‌ها: ' . $conn->error];
}

?>
<div class="page-header">
    <h1>نظارت بر کلاس‌ها و گزارش‌ها</h1>
    <p class="page-subtitle">از این بخش می‌توانید گزارش‌های مربوط به کلاس‌های مختلف، از جمله خوداظهاری مدرسین و نتایج بازدیدهای کلاسی را مشاهده کنید.</p>
     <div class="page-header-actions">
        <a href="<?php echo $admin_base_url; ?>/classes/index.php" class="btn btn-info"> <!-- Link to future class management -->
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
            <span>مدیریت کلاس‌ها</span>
        </a>
    </div>
</div>

<?php
if (isset($_SESSION['flash_message'])) {
    $flash_mon_idx = $_SESSION['flash_message'];
    echo "<div class='alert alert-{$flash_mon_idx['type']} alert-dismissible fade show' role='alert'>{$flash_mon_idx['text']}
          <button type='button' class='close' data-dismiss='alert' aria-label='Close' style='background:none; border:none; font-size:1.5rem; position:absolute; top:0; left:0; padding: 0.75rem 1.25rem;'><span aria-hidden='true'>&times;</span></button></div>";
    unset($_SESSION['flash_message']);
    echo "<script>setTimeout(function() {let alert = document.querySelector('.alert-dismissible.show'); if(alert){ if(typeof(bootstrap) !== 'undefined' && bootstrap.Alert && bootstrap.Alert.getInstance(alert)) { bootstrap.Alert.getInstance(alert).close(); } else { alert.style.display = 'none'; }}}, 7000);</script>";
}
?>

<div class="card shadow-sm">
    <div class="card-header">
        <span class="card-title-text">لیست تمام کلاس‌های فعال</span>
        <!-- TODO: Add filters for AcademicYear, GradeLevel -->
    </div>
    <div class="card-body">
        <?php if (!empty($all_classes)): ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped table-sm admin-monitoring-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>نام کلاس</th>
                            <th>پایه/سطح</th>
                            <th>سال تحصیلی</th>
                            <th>مدرس</th>
                            <th>خوداظهاری‌ها</th>
                            <th>بازدیدها</th>
                            <th class="actions-column">مشاهده جزئیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $class_row_num = 1; foreach ($all_classes as $class_item): ?>
                            <tr>
                                <td><?php echo $class_row_num++; ?></td>
                                <td><strong><?php echo htmlspecialchars($class_item['ClassName']); ?></strong></td>
                                <td><?php echo htmlspecialchars($class_item['GradeLevel'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($class_item['AcademicYear'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($class_item['TeacherName'] && trim($class_item['TeacherName']) !== ''): ?>
                                        <a href="<?php echo $admin_base_url; ?>/users/edit.php?user_id=<?php echo $class_item['TeacherUserID']; ?>">
                                            <?php echo htmlspecialchars($class_item['TeacherName']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">- بدون مدرس -</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><span class="badge badge-light"><?php echo $class_item['SelfAssessmentCount']; ?></span></td>
                                <td class="text-center"><span class="badge badge-light"><?php echo $class_item['ObservationCount']; ?></span></td>
                                <td class="actions-cell">
                                    <a href="class_submissions.php?class_id=<?php echo $class_item['ClassID']; ?>" class="btn btn-sm btn-primary">
                                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                                        <span>گزارش‌های کلاس</span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info mb-0">
                در حال حاضر هیچ کلاس فعالی در سیستم ثبت نشده است.
                (برای افزودن کلاس، به بخش "مدیریت کلاس‌ها" مراجعه کنید - این بخش در آینده اضافه خواهد شد).
            </div>
        <?php endif; ?>
    </div>
</div>
<style>
    .admin-monitoring-table th, .admin-monitoring-table td { font-size: 0.9rem; vertical-align: middle; }
    .admin-monitoring-table .badge { font-size: 0.85rem; padding: 0.3em 0.5em;}
</style>
<?php
require_once __DIR__ . '/../includes/footer.php';
?>
