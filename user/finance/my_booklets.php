<?php
// user/finance/my_booklets.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth

$teacher_user_id = get_current_user_id();
// Ensure user is a teacher, otherwise redirect or show error
if (!$teacher_user_id || get_current_user_type() !== 'teacher') {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'این بخش مخصوص مدرسین است.'];
    header("Location: " . ($user_base_url ?? '/my_site/user') . "/dashboard/index.php");
    exit;
}

// Fetch booklet assignments for the current teacher
$assignments_teacher_q = $conn->prepare("
    SELECT ba.AssignmentID, ba.Quantity, ba.Month, ba.TotalPrice, ba.AmountPaid, ba.PaymentStatus, ba.Notes,
           b.BookletName, b.Price AS UnitPrice,
           c.ClassName, ba.AssignmentDate
    FROM BookletAssignments ba
    JOIN Booklets b ON ba.BookletID = b.BookletID
    JOIN Classes c ON ba.ClassID = c.ClassID
    WHERE ba.TeacherUserID = ?
    ORDER BY ba.Month DESC, b.BookletName ASC
");

$teacher_assignments = [];
if ($assignments_teacher_q) {
    $assignments_teacher_q->bind_param("i", $teacher_user_id);
    $assignments_teacher_q->execute();
    $result_assign = $assignments_teacher_q->get_result();
    while ($assign_t = $result_assign->fetch_assoc()) {
        $teacher_assignments[] = $assign_t;
    }
    $assignments_teacher_q->close();
} else {
    // Log the error and set a user-friendly flash message
    error_log("Error fetching booklet assignments for user $teacher_user_id: " . $conn->error);
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا در بارگذاری اطلاعات جزوات شما. لطفاً بعداً تلاش کنید یا با پشتیبانی تماس بگیرید.'];
}

// Calculate overall financial summary for the teacher
$total_billed_teacher = 0;
$total_paid_teacher = 0;
foreach ($teacher_assignments as $ta) {
    $total_billed_teacher += floatval($ta['TotalPrice']);
    $total_paid_teacher += floatval($ta['AmountPaid']);
}
$total_due_teacher = $total_billed_teacher - $total_paid_teacher;

$payment_status_persian_map_user = [
    'unpaid' => 'پرداخت نشده',
    'partially_paid' => 'پرداخت ناقص',
    'paid' => 'پرداخت شده'
];
$payment_status_badge_map_user = [
    'unpaid' => 'danger',
    'partially_paid' => 'warning',
    'paid' => 'success'
];

?>
<div class="page-header">
    <h1>جزوات و حساب مالی من</h1>
    <p class="page-subtitle">در این بخش می‌توانید تاریخچه جزوات دریافتی و وضعیت پرداخت‌های خود را مشاهده کنید.</p>
</div>

<?php
if (isset($_SESSION['flash_message'])) {
    $flash_myb_user = $_SESSION['flash_message'];
    echo "<div class='alert alert-{$flash_myb_user['type']} alert-dismissible fade show' role='alert'>{$flash_myb_user['text']}
          <button type='button' class='close' data-dismiss='alert' aria-label='Close' style='background:none; border:none; font-size:1.5rem; position:absolute; top:0; left:0; padding: 0.75rem 1.25rem;'><span aria-hidden='true'>&times;</span></button></div>";
    unset($_SESSION['flash_message']);
    echo "<script>setTimeout(function() {let alert = document.querySelector('.alert-dismissible.show'); if(alert){ if(typeof(bootstrap) !== 'undefined' && bootstrap.Alert && bootstrap.Alert.getInstance(alert)) { bootstrap.Alert.getInstance(alert).close(); } else { alert.style.display = 'none'; }}}, 7000);</script>";
}
?>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-light py-3">
        <h5 class="mb-0 card-title-text">خلاصه وضعیت مالی شما (بابت جزوات)</h5>
    </div>
    <div class="card-body">
        <div class="row text-center financial-summary-cards">
            <div class="col-md-4 mb-3 mb-md-0">
                <div class="stat-card p-3 border rounded bg-light-blue">
                    <h6>جمع کل مبلغ جزوات</h6>
                    <p class="h3 font-weight-bold text-info mb-0"><?php echo number_format($total_billed_teacher, 0); ?> <small>تومان</small></p>
                </div>
            </div>
            <div class="col-md-4 mb-3 mb-md-0">
                 <div class="stat-card p-3 border rounded bg-light-green">
                    <h6>جمع کل پرداختی شما</h6>
                    <p class="h3 font-weight-bold text-success mb-0"><?php echo number_format($total_paid_teacher, 0); ?> <small>تومان</small></p>
                </div>
            </div>
            <div class="col-md-4">
                 <div class="stat-card p-3 border rounded <?php echo ($total_due_teacher > 0) ? 'bg-light-red' : (($total_due_teacher == 0 && $total_billed_teacher > 0) ? 'bg-light-green' : 'bg-light-grey'); ?>">
                    <h6>مانده حساب</h6>
                    <p class="h3 font-weight-bold <?php echo ($total_due_teacher > 0) ? 'text-danger' : (($total_due_teacher < 0) ? 'text-primary' : 'text-success'); ?> mb-0">
                        <?php echo number_format(abs($total_due_teacher), 0); ?> <small>تومان</small>
                    </p>
                    <small class="text-muted"><?php
                        if ($total_due_teacher > 0) echo '(بدهکار)';
                        elseif ($total_due_teacher < 0) echo '(بستانکار)';
                        elseif ($total_billed_teacher > 0) echo '(تسویه شده)';
                        else echo '(بدون تراکنش)';
                    ?></small>
                </div>
            </div>
        </div>
        <?php if ($total_due_teacher > 0): ?>
        <div class="alert alert-warning mt-3 text-center small">
            شما مبلغ <strong><?php echo number_format($total_due_teacher, 0); ?> تومان</strong> بابت جزوات بدهکار هستید. لطفاً جهت تسویه حساب با مسئول مالی هماهنگ فرمایید.
        </div>
        <?php endif; ?>
    </div>
</div>


<div class="card shadow-sm">
    <div class="card-header">
        <span class="card-title-text">تاریخچه جزوات دریافتی و پرداخت‌ها</span>
    </div>
    <div class="card-body">
        <?php if (!empty($teacher_assignments)): ?>
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover user-finance-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>جزوه</th>
                            <th>کلاس</th>
                            <th>ماه تخصیص</th>
                            <th class="text-center">تعداد</th>
                            <th class="text-center">مبلغ کل (تومان)</th>
                            <th class="text-center">پرداختی (تومان)</th>
                            <th class="text-center">وضعیت</th>
                            <th>یادداشت‌ها</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $assign_t_row = 1; foreach ($teacher_assignments as $assign):
                            $balance_t_user = floatval($assign['TotalPrice']) - floatval($assign['AmountPaid']);
                        ?>
                            <tr>
                                <td><?php echo $assign_t_row++; ?></td>
                                <td><?php echo htmlspecialchars($assign['BookletName']); ?></td>
                                <td><?php echo htmlspecialchars($assign['ClassName']); ?></td>
                                <td><?php echo htmlspecialchars($assign['Month']); ?></td>
                                <td class="text-center"><?php echo $assign['Quantity']; ?></td>
                                <td class="text-center"><?php echo number_format(floatval($assign['TotalPrice']),0); ?></td>
                                <td class="text-center text-success"><?php echo number_format(floatval($assign['AmountPaid']),0); ?></td>
                                <td class="text-center">
                                    <span class="badge badge-<?php echo $payment_status_badge_map_user[$assign['PaymentStatus']] ?? 'secondary'; ?>">
                                        <?php echo $payment_status_persian_map_user[$assign['PaymentStatus']] ?? htmlspecialchars($assign['PaymentStatus']); ?>
                                    </span>
                                     <?php if($assign['PaymentStatus'] !== 'paid' && $balance_t_user > 0): ?>
                                        <br><small class="text-danger">(مانده: <?php echo number_format($balance_t_user,0); ?> ت)</small>
                                    <?php elseif ($assign['PaymentStatus'] !== 'paid' && $balance_t_user < 0): ?>
                                        <br><small class="text-primary">(بستانکاری: <?php echo number_format(abs($balance_t_user),0); ?> ت)</small>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?php echo !empty(trim($assign['Notes'])) ? nl2br(htmlspecialchars($assign['Notes'])) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted text-center mt-3">هنوز هیچ جزوه‌ای برای شما ثبت نشده است.</p>
        <?php endif; ?>
    </div>
</div>
<style>
    .user-finance-table th, .user-finance-table td { vertical-align: middle; font-size: 0.9rem; }
    .stat-card { background-color: #f8f9fa; transition: transform 0.2s ease-in-out; }
    .stat-card:hover { transform: translateY(-3px); }
    .bg-light-blue { background-color: #e7f3fe !important; border-left: 3px solid #007bff; }
    .bg-light-green { background-color: #e6f9f0 !important; border-left: 3px solid #28a745;}
    .bg-light-red { background-color: #feedec !important; border-left: 3px solid #dc3545;}
    .bg-light-grey { background-color: #f8f9fa !important; border-left: 3px solid #6c757d;}
    .stat-card h6 { font-size: 0.9rem; color: #555; margin-bottom: 0.5rem; }
    .financial-summary-cards .col-md-4:not(:last-child) .stat-card { margin-bottom: 1rem } /* Spacing for mobile */
    @media (min-width: 768px) {
        .financial-summary-cards .col-md-4 .stat-card { margin-bottom: 0; }
    }
</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
