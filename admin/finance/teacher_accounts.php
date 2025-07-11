<?php
// admin/finance/teacher_accounts.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth

// Fetch summary for each teacher regarding booklets
// Ensure that even teachers with no assignments yet are listed if they are 'teacher' type
$teacher_accounts_query = $conn->query("
    SELECT
        u.UserID,
        u.FirstName,
        u.LastName,
        u.Username,
        COALESCE(SUM(ba.TotalPrice), 0) as TotalBilled,
        COALESCE(SUM(ba.AmountPaid), 0) as TotalPaid,
        (COALESCE(SUM(ba.TotalPrice), 0) - COALESCE(SUM(ba.AmountPaid), 0)) as TotalDue
    FROM Users u
    LEFT JOIN BookletAssignments ba ON u.UserID = ba.TeacherUserID
    WHERE u.UserType = 'teacher' AND u.IsActive = TRUE
    GROUP BY u.UserID, u.FirstName, u.LastName, u.Username
    ORDER BY TotalDue DESC, u.LastName, u.FirstName
");

?>
<div class="page-header">
    <h1>صورتحساب جزوات مدرسین</h1>
    <div class="page-header-actions">
        <a href="booklets.php" class="btn btn-secondary">
             <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
            <span>مدیریت جزوات</span>
        </a>
        <a href="assignments.php" class="btn btn-info">
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
            <span>تخصیص و پرداخت‌ها</span>
        </a>
    </div>
</div>

<?php
if (isset($_SESSION['flash_message'])) {
    $flash_acc = $_SESSION['flash_message'];
    echo "<div class='alert alert-{$flash_acc['type']} alert-dismissible fade show'>{$flash_acc['text']}<button type='button' class='close' data-dismiss='alert'>&times;</button></div>";
    unset($_SESSION['flash_message']);
}
?>

<div class="card shadow-sm">
    <div class="card-header">
        <span class="card-title-text">خلاصه وضعیت مالی مدرسین بابت جزوات</span>
        <!-- TODO: Add filters (e.g., by academic year if assignments are linked to it) -->
    </div>
    <div class="card-body">
        <?php if ($teacher_accounts_query && $teacher_accounts_query->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover financial-summary-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>مدرس</th>
                            <th>نام کاربری</th>
                            <th class="text-center">جمع کل مبلغ جزوات (تومان)</th>
                            <th class="text-center">جمع کل پرداختی (تومان)</th>
                            <th class="text-center">مانده بدهی (تومان)</th>
                            <th class="actions-column text-center">جزئیات تخصیص‌ها</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $acc_row = 1; while ($acc = $teacher_accounts_query->fetch_assoc()):
                            $totalDue = $acc['TotalDue'] ?? 0;
                            $rowClass = '';
                            if ($totalDue > 0) $rowClass = 'table-danger-light';
                            elseif ($totalDue == 0 && ($acc['TotalBilled'] ?? 0) > 0) $rowClass = 'table-success-light';
                        ?>
                            <tr class="<?php echo $rowClass; ?>">
                                <td><?php echo $acc_row++; ?></td>
                                <td><?php echo htmlspecialchars($acc['FirstName'] . ' ' . $acc['LastName']); ?></td>
                                <td><small><?php echo htmlspecialchars($acc['Username']); ?></small></td>
                                <td class="text-center text-info font-weight-bold"><?php echo number_format($acc['TotalBilled'] ?? 0, 0); ?></td>
                                <td class="text-center text-success font-weight-bold"><?php echo number_format($acc['TotalPaid'] ?? 0, 0); ?></td>
                                <td class="text-center font-weight-bold <?php echo ($totalDue > 0) ? 'text-danger' : ($totalDue < 0 ? 'text-primary' : 'text-success'); ?>">
                                    <?php echo number_format($totalDue, 0); ?>
                                    <?php if ($totalDue < 0) echo " (بستانکار)"; ?>
                                </td>
                                <td class="actions-cell text-center">
                                    <a href="assignments.php?teacher_id_filter=<?php echo $acc['UserID']; ?>" class="btn btn-sm btn-outline-primary" title="مشاهده و مدیریت تخصیص‌های این مدرس">
                                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                                        <span>مشاهده تخصیص‌ها</span>
                                    </a>
                                    <!-- Future: Button to send reminder/notification -->
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted text-center mt-3">هنوز اطلاعات مالی برای مدرسین واجد شرایط (با نقش "مدرس" و فعال) ثبت نشده است یا هیچ مدرسی جزوه دریافت نکرده.</p>
        <?php endif; if($teacher_accounts_query) $teacher_accounts_query->close(); ?>
    </div>
</div>
<style>
    .financial-summary-table th, .financial-summary-table td { vertical-align: middle; }
    .table-danger-light td { background-color: #fdecea !important; }
    .table-success-light td { background-color: #d1e7dd !important; }
    .text-primary { color: #007bff !important; } /* For credit balances */
</style>
<script> /* Alert dismissal JS ... */ </script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
