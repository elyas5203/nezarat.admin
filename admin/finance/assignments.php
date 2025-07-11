<?php
// admin/finance/assignments.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth

$csrf_token_assignments = generate_csrf_token('booklet_assignments_action');

$errors = [];
$success_message = '';

// Fetch data for dropdowns
$booklets_q = $conn->query("SELECT BookletID, BookletName, Price FROM Booklets ORDER BY BookletName");
$available_booklets = [];
if ($booklets_q) { while($b = $booklets_q->fetch_assoc()) $available_booklets[$b['BookletID']] = $b; $booklets_q->close(); }

$teachers_q = $conn->query("SELECT UserID, FirstName, LastName, Username FROM Users WHERE UserType = 'teacher' AND IsActive = TRUE ORDER BY LastName, FirstName");
$available_teachers = [];
if ($teachers_q) { while($t = $teachers_q->fetch_assoc()) $available_teachers[$t['UserID']] = $t; $teachers_q->close(); }

$classes_q = $conn->query("SELECT ClassID, ClassName, TeacherUserID FROM Classes WHERE IsActive = TRUE ORDER BY ClassName");
$available_classes = [];
if ($classes_q) { while($c = $classes_q->fetch_assoc()) $available_classes[$c['ClassID']] = $c; $classes_q->close(); }

// Handle New Assignment Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_assignment'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'booklet_assignments_action')) {
        $errors[] = 'خطای CSRF!';
    } else {
        $booklet_id = isset($_POST['booklet_id']) ? (int)$_POST['booklet_id'] : null;
        $class_id = isset($_POST['class_id']) ? (int)$_POST['class_id'] : null;
        $teacher_user_id = isset($_POST['teacher_user_id']) ? (int)$_POST['teacher_user_id'] : null;
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : null;
        $assignment_month_year = sanitize_input($_POST['assignment_month_year'] ?? '');
        $notes_assignment = sanitize_input($_POST['notes_assignment'] ?? '');

        if (empty($booklet_id) || !isset($available_booklets[$booklet_id])) $errors[] = "جزوه نامعتبر.";
        if (empty($class_id) || !isset($available_classes[$class_id])) $errors[] = "کلاس نامعتبر.";
        if (empty($teacher_user_id) || !isset($available_teachers[$teacher_user_id])) $errors[] = "مدرس نامعتبر.";

        if (empty($quantity) || $quantity <= 0) $errors[] = "تعداد باید مثبت باشد.";
        if (empty($assignment_month_year) || !preg_match('/^\d{4}-\d{2}$/', $assignment_month_year)) {
            $errors[] = "فرمت ماه و سال (مثال: 1403-05).";
        } else { list($year, $month) = explode('-', $assignment_month_year); if ($month < 1 || $month > 12) $errors[] = "ماه نامعتبر."; }

        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO BookletAssignments (BookletID, ClassID, TeacherUserID, Quantity, AssignmentDate, Month, Notes) VALUES (?, ?, ?, ?, CURDATE(), ?, ?)");
            if ($stmt) {
                $stmt->bind_param("iiiiss", $booklet_id, $class_id, $teacher_user_id, $quantity, $assignment_month_year, $notes_assignment);
                if ($stmt->execute()) $success_message = "تخصیص جزوه ثبت شد.";
                else $errors[] = "خطا ثبت تخصیص: " . $stmt->error;
                $stmt->close();
            } else $errors[] = "خطا آماده سازی تخصیص: " . $conn->error;
        }
    }
    $csrf_token_assignments = regenerate_csrf_token('booklet_assignments_action');
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_payment'])) {
     if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'booklet_assignments_action')) {
        $errors[] = 'خطای CSRF!';
    } else {
        $assignment_id_payment = isset($_POST['assignment_id_payment']) ? (int)$_POST['assignment_id_payment'] : null;
        $amount_paid_new = isset($_POST['amount_paid']) ? floatval($_POST['amount_paid']) : null;

        if (empty($assignment_id_payment)) $errors[] = "شناسه تخصیص نامعتبر.";
        if (!is_numeric($amount_paid_new) || $amount_paid_new < 0) $errors[] = "مبلغ پرداختی نامعتبر.";

        if (empty($errors)) {
            $stmt_fetch_assign = $conn->prepare("SELECT TotalPrice FROM BookletAssignments WHERE AssignmentID = ?");
            if ($stmt_fetch_assign) {
                $stmt_fetch_assign->bind_param("i", $assignment_id_payment);
                $stmt_fetch_assign->execute();
                $assign_data = $stmt_fetch_assign->get_result()->fetch_assoc();
                $stmt_fetch_assign->close();
                if ($assign_data) {
                    $total_price_assign = $assign_data['TotalPrice'];
                    $new_payment_status = 'unpaid';
                    if ($amount_paid_new >= $total_price_assign) $new_payment_status = 'paid';
                    elseif ($amount_paid_new > 0) $new_payment_status = 'partially_paid';

                    $payment_notes_from_modal = sanitize_input($_POST['payment_notes'] ?? '');
                    $existing_notes_stmt = $conn->prepare("SELECT Notes FROM BookletAssignments WHERE AssignmentID = ?");
                    $existing_notes = '';
                    if($existing_notes_stmt){
                        $existing_notes_stmt->bind_param("i", $assignment_id_payment);
                        $existing_notes_stmt->execute();
                        $existing_notes_res = $existing_notes_stmt->get_result()->fetch_assoc();
                        $existing_notes = $existing_notes_res['Notes'] ?? '';
                        $existing_notes_stmt->close();
                    }
                    $updated_notes = $existing_notes;
                    if(!empty($payment_notes_from_modal)){
                         $updated_notes .= ($existing_notes ? "\n" : "") . "یادداشت پرداخت (" . to_jalali(date('Y-m-d H:i:s'), 'yyyy/MM/dd HH:mm') . "): " . $payment_notes_from_modal;
                    }


                    $stmt_pay = $conn->prepare("UPDATE BookletAssignments SET AmountPaid = ?, PaymentStatus = ?, Notes = ? WHERE AssignmentID = ?");
                    if($stmt_pay){
                        $stmt_pay->bind_param("dssi", $amount_paid_new, $new_payment_status, $updated_notes, $assignment_id_payment);
                        if($stmt_pay->execute()) $success_message = "پرداخت ثبت شد.";
                        else $errors[] = "خطا ثبت پرداخت: " . $stmt_pay->error;
                        $stmt_pay->close();
                    } else $errors[] = "خطا آماده سازی پرداخت: " . $conn->error;
                } else $errors[] = "تخصیص یافت نشد.";
            } else $errors[] = "خطا خواندن اطلاعات تخصیص: " . $conn->error;
        }
    }
    $csrf_token_assignments = regenerate_csrf_token('booklet_assignments_action');
}

$assignments_list_q = $conn->query("
    SELECT ba.*, b.BookletName, b.Price AS UnitPrice, c.ClassName,
           CONCAT(u.FirstName, ' ', u.LastName) as TeacherName
    FROM BookletAssignments ba
    JOIN Booklets b ON ba.BookletID = b.BookletID
    JOIN Classes c ON ba.ClassID = c.ClassID
    JOIN Users u ON ba.TeacherUserID = u.UserID
    ORDER BY ba.AssignmentID DESC LIMIT 50 -- Show recent 50, add pagination later
");
?>
<div class="page-header"><h1>تخصیص جزوات و پرداخت‌ها</h1>
    <div class="page-header-actions"><a href="booklets.php" class="btn btn-secondary">مدیریت جزوات</a><a href="teacher_accounts.php" class="btn btn-success">صورتحساب مدرسین</a></div></div>

<?php if (!empty($errors)): ?> <div class="alert alert-danger"><ul><?php foreach ($errors as $err): ?><li><?php echo htmlspecialchars($err); ?></li><?php endforeach; ?></ul></div> <?php endif; ?>
<?php if ($success_message): ?> <div class="alert alert-success alert-dismissible fade show"><?php echo htmlspecialchars($success_message); ?><button type="button" class="close" data-dismiss="alert">&times;</button></div> <?php endif; ?>

<div class="card mb-4 shadow-sm">
    <div class="card-header"><span class="card-title-text">ثبت تخصیص جزوه جدید</span></div>
    <div class="card-body">
        <form action="assignments.php" method="POST"> <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_assignments; ?>">
            <div class="form-row">
                <div class="form-group col-md-4"><label for="booklet_id">جزوه <span class="text-danger">*</span></label><select name="booklet_id" id="booklet_id" class="form-control custom-select" required><option value="">-- انتخاب --</option><?php foreach($available_booklets as $bid => $bdata): ?><option value="<?php echo $bid; ?>" data-price="<?php echo $bdata['Price']; ?>"><?php echo htmlspecialchars($bdata['BookletName'] . " (".number_format($bdata['Price'],0). " ت)"); ?></option><?php endforeach; ?></select></div>
                <div class="form-group col-md-4"><label for="class_id">کلاس <span class="text-danger">*</span></label><select name="class_id" id="class_id" class="form-control custom-select" required><option value="">-- انتخاب --</option><?php foreach($available_classes as $cid => $cdata): ?><option value="<?php echo $cid; ?>" data-teacherid="<?php echo $cdata['TeacherUserID']; ?>"><?php echo htmlspecialchars($cdata['ClassName']); ?></option><?php endforeach; ?></select></div>
                <div class="form-group col-md-4"><label for="teacher_user_id">مدرس <span class="text-danger">*</span></label><select name="teacher_user_id" id="teacher_user_id" class="form-control custom-select" required><option value="">-- انتخاب --</option><?php foreach($available_teachers as $tid => $tdata): ?><option value="<?php echo $tid; ?>"><?php echo htmlspecialchars($tdata['FirstName']." ".$tdata['LastName'] . " (@".$tdata['Username'].")"); ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-3"><label for="quantity">تعداد <span class="text-danger">*</span></label><input type="number" name="quantity" id="quantity" class="form-control" min="1" required></div>
                <div class="form-group col-md-3"><label for="assignment_month_year">ماه تخصیص <span class="text-danger">*</span></label><input type="text" name="assignment_month_year" id="assignment_month_year" class="form-control" placeholder="مثال: 1403-05" required pattern="\d{4}-\d{2}"></div>
                <div class="form-group col-md-6"><label for="notes_assignment">یادداشت</label><input type="text" name="notes_assignment" id="notes_assignment" class="form-control"></div>
            </div><button type="submit" name="submit_assignment" class="btn btn-primary">ثبت تخصیص</button></form></div></div>

<div class="card shadow-sm">
    <div class="card-header"><span class="card-title-text">لیست تخصیص‌ها و پرداخت‌ها (۵۰ مورد اخیر)</span></div>
    <div class="card-body">
        <?php if($assignments_list_q && $assignments_list_q->num_rows > 0): ?>
        <div class="table-responsive"><table class="table table-sm table-striped table-hover">
            <thead><tr><th>#</th><th>جزوه</th><th>کلاس</th><th>مدرس</th><th>تعداد</th><th>ماه</th><th>جمع کل</th><th>پرداختی</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>
            <?php $assign_row = 1; while($assign = $assignments_list_q->fetch_assoc()): $balance = $assign['TotalPrice'] - $assign['AmountPaid']; ?>
            <tr><td><?php echo $assign_row++; ?></td><td><?php echo htmlspecialchars($assign['BookletName']); ?></td><td><?php echo htmlspecialchars($assign['ClassName']); ?></td><td><?php echo htmlspecialchars($assign['TeacherName']); ?></td><td><?php echo $assign['Quantity']; ?></td><td><?php echo htmlspecialchars($assign['Month']); ?></td><td><?php echo number_format($assign['TotalPrice'],0); ?> ت</td><td><?php echo number_format($assign['AmountPaid'],0); ?> ت</td>
            <td> <?php if($assign['PaymentStatus'] == 'paid'): ?> <span class="badge badge-success">پرداخت شده</span> <?php elseif($assign['PaymentStatus'] == 'partially_paid'): ?> <span class="badge badge-warning">پرداخت ناقص (مانده: <?php echo number_format($balance,0); ?> ت)</span> <?php else: ?> <span class="badge badge-danger">پرداخت نشده (بدهی: <?php echo number_format($balance,0); ?> ت)</span> <?php endif; ?> </td>
            <td class="actions-cell"><button type="button" class="btn btn-sm btn-info btn-payment" data-toggle="modal" data-target="#paymentModal" data-assignmentid="<?php echo $assign['AssignmentID']; ?>" data-totalprice="<?php echo $assign['TotalPrice']; ?>" data-amountpaid="<?php echo $assign['AmountPaid']; ?>" data-teachername="<?php echo htmlspecialchars($assign['TeacherName']); ?>" data-bookletname="<?php echo htmlspecialchars($assign['BookletName']); ?>" title="ثبت/ویرایش پرداخت"><svg class="icon" width="16" height="16" viewBox="0 0 24 24"><path d="M20 12V8H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v4m-8 0h-4m4 4H4m16-4L14 2m0 0L4 12m10-2v10m0 0L4 22m10-2h10"/></svg> پرداخت</button></td></tr>
            <?php endwhile; ?></tbody></table></div>
        <?php else: ?><p class="text-muted">هنوز تخصیصی ثبت نشده.</p><?php endif; if($assignments_list_q) $assignments_list_q->close(); ?></div></div>

<div class="modal fade" id="paymentModal" tabindex="-1" role="dialog" aria-labelledby="paymentModalLabel" aria-hidden="true"> <div class="modal-dialog modal-dialog-centered" role="document"> <div class="modal-content">
    <form action="assignments.php" method="POST"> <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_assignments; ?>"> <input type="hidden" name="assignment_id_payment" id="modal_assignment_id">
    <div class="modal-header"><h5 class="modal-title" id="paymentModalLabel">ثبت/ویرایش پرداخت: <span id="modal_teacher_booklet" class="font-weight-bold"></span></h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
    <div class="modal-body">
        <p><strong>مبلغ کل فاکتور:</strong> <span id="modal_total_price_display"></span> تومان</p>
        <p><strong>مبلغ پرداخت شده قبلی:</strong> <span id="modal_current_paid_display"></span> تومان</p>
        <div class="form-group"><label for="modal_amount_paid">مبلغ کل پرداختی جدید <span class="text-danger">*</span></label><input type="number" step="1" min="0" class="form-control" id="modal_amount_paid" name="amount_paid" required><small class="form-text text-muted">مجموع کل مبلغی که تاکنون برای این فاکتور پرداخت شده را وارد کنید.</small></div>
        <div class="form-group"><label for="modal_payment_notes">یادداشت پرداخت (اختیاری)</label><textarea class="form-control" id="modal_payment_notes" name="payment_notes" rows="2"></textarea></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">بستن</button><button type="submit" name="submit_payment" class="btn btn-primary">ذخیره پرداخت</button></div></form></div></div></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const classSelect = document.getElementById('class_id');
    const teacherSelect = document.getElementById('teacher_user_id');
    if(classSelect && teacherSelect){
        classSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const teacherId = selectedOption.getAttribute('data-teacherid');
            if (teacherId) teacherSelect.value = teacherId; else teacherSelect.value = '';
        });
    }

    // For Bootstrap Modal and alert dismissal (ensure jQuery and Bootstrap JS are loaded in footer)
    if (typeof $ !== 'undefined' && $.fn.modal) {
        $('#paymentModal').on('show.bs.modal', function (event) {
          var button = $(event.relatedTarget);
          var assignmentId = button.data('assignmentid');
          var totalPrice = parseFloat(button.data('totalprice'));
          var amountPaid = parseFloat(button.data('amountpaid'));
          var teacherName = button.data('teachername');
          var bookletName = button.data('bookletname');

          var modal = $(this);
          modal.find('#modal_assignment_id').val(assignmentId);
          modal.find('#modal_teacher_booklet').text(teacherName + ' - ' + bookletName);
          modal.find('#modal_total_price_display').text(totalPrice.toLocaleString('fa-IR'));
          modal.find('#modal_current_paid_display').text(amountPaid.toLocaleString('fa-IR'));
          modal.find('#modal_amount_paid').val(amountPaid).attr('max', totalPrice);
          modal.find('#modal_payment_notes').val(''); // Clear previous notes
        });
    }
    document.querySelectorAll('.alert .close').forEach(function(button){button.addEventListener('click', function(event){event.target.closest('.alert').style.display = 'none';});});
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
