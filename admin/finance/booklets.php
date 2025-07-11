<?php
// admin/finance/booklets.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth

$csrf_token_booklets = generate_csrf_token('booklets_form_action');

$errors = [];
$success_message = '';
$edit_mode_booklet = false;
$booklet_to_edit = ['BookletID' => null, 'BookletName' => '', 'Price' => '', 'Description' => ''];

// Handle Form Submission (Create or Update Booklet)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_booklet'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'booklets_form_action')) {
        $errors[] = 'خطای CSRF! درخواست نامعتبر.';
    } else {
        $booklet_id = isset($_POST['booklet_id']) && is_numeric($_POST['booklet_id']) ? (int)$_POST['booklet_id'] : null;
        $booklet_name = sanitize_input($_POST['booklet_name'] ?? '');
        $price = sanitize_input($_POST['price'] ?? ''); // Will be validated as numeric
        $description_booklet = sanitize_input($_POST['description_booklet'] ?? '');

        if (empty($booklet_name)) $errors[] = "نام جزوه الزامی است.";
        if (!is_numeric($price) || floatval($price) < 0) {
            $errors[] = "قیمت باید یک عدد معتبر (مثبت یا صفر) باشد.";
        } else {
            $price = floatval($price); // Convert to float after validation
        }

        // Check uniqueness of booklet name (excluding current booklet if editing)
        $sql_check_bname = "SELECT BookletID FROM Booklets WHERE BookletName = ?";
        $params_bname = [$booklet_name];
        $types_bname = "s";
        if ($booklet_id) {
            $sql_check_bname .= " AND BookletID != ?";
            $params_bname[] = $booklet_id;
            $types_bname .= "i";
        }
        $stmt_check_bname = $conn->prepare($sql_check_bname);
        if ($stmt_check_bname) {
            $stmt_check_bname->bind_param($types_bname, ...$params_bname);
            $stmt_check_bname->execute();
            if ($stmt_check_bname->get_result()->num_rows > 0) {
                $errors[] = "جزوه دیگری با این نام قبلاً ثبت شده است.";
            }
            $stmt_check_bname->close();
        } else { $errors[] = "خطا در بررسی نام جزوه: " . $conn->error; }


        if (empty($errors)) {
            if ($booklet_id) { // Update
                $stmt = $conn->prepare("UPDATE Booklets SET BookletName = ?, Price = ?, Description = ? WHERE BookletID = ?");
                if ($stmt) {
                    $stmt->bind_param("sdsi", $booklet_name, $price, $description_booklet, $booklet_id);
                    if ($stmt->execute()) {
                        $success_message = "جزوه با موفقیت ویرایش شد.";
                        $edit_mode_booklet = false; // Exit edit mode after successful update
                        $booklet_to_edit = ['BookletID' => null, 'BookletName' => '', 'Price' => '', 'Description' => ''];//clear form
                    } else $errors[] = "خطا در ویرایش جزوه: " . $stmt->error;
                    $stmt->close();
                } else $errors[] = "خطا آماده سازی ویرایش: " . $conn->error;
            } else { // Create
                $stmt = $conn->prepare("INSERT INTO Booklets (BookletName, Price, Description) VALUES (?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("sds", $booklet_name, $price, $description_booklet);
                    if ($stmt->execute()) {
                        $success_message = "جزوه با موفقیت ایجاد شد.";
                        // Clear form fields for next entry
                        $booklet_to_edit = ['BookletID' => null, 'BookletName' => '', 'Price' => '', 'Description' => ''];
                    } else $errors[] = "خطا در ایجاد جزوه: " . $stmt->error;
                    $stmt->close();
                } else $errors[] = "خطا آماده سازی ایجاد: " . $conn->error;
            }
        }
    }
    $csrf_token_booklets = regenerate_csrf_token('booklets_form_action');
}

// Handle Edit Request (when 'edit_id' is in GET)
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id']) && $_SERVER["REQUEST_METHOD"] != "POST") { // Only on GET
    $edit_id_booklet = (int)$_GET['edit_id'];
    $stmt_edit_b = $conn->prepare("SELECT BookletID, BookletName, Price, Description FROM Booklets WHERE BookletID = ?");
    if ($stmt_edit_b) {
        $stmt_edit_b->bind_param("i", $edit_id_booklet);
        $stmt_edit_b->execute();
        $result_edit_b = $stmt_edit_b->get_result();
        if ($data = $result_edit_b->fetch_assoc()) {
            $booklet_to_edit = $data;
            $edit_mode_booklet = true;
        } else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "جزوه برای ویرایش یافت نشد."]; }
        $stmt_edit_b->close();
    } else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "خطا بارگذاری جزوه: " . $conn->error]; }
}

// Handle Delete Request
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
     if (!isset($_GET['csrf_token']) || !verify_csrf_token($_GET['csrf_token'], 'booklets_form_action')) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطای CSRF برای حذف!'];
    } else {
        $delete_id_booklet = (int)$_GET['delete_id'];
        $stmt_check_assign = $conn->prepare("SELECT COUNT(*) as count FROM BookletAssignments WHERE BookletID = ?");
        if ($stmt_check_assign) {
            $stmt_check_assign->bind_param("i", $delete_id_booklet);
            $stmt_check_assign->execute();
            $assign_count_b = $stmt_check_assign->get_result()->fetch_assoc()['count'] ?? 0;
            $stmt_check_assign->close();
            if ($assign_count_b > 0) {
                $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "حذف ناموفق: این جزوه به ".$assign_count_b." مورد تخصیص داده شده است."];
            } else {
                $stmt_delete_b = $conn->prepare("DELETE FROM Booklets WHERE BookletID = ?");
                if ($stmt_delete_b) {
                    $stmt_delete_b->bind_param("i", $delete_id_booklet);
                    if ($stmt_delete_b->execute()  && $stmt_delete_b->affected_rows > 0) {
                         $_SESSION['flash_message'] = ['type' => 'success', 'text' => "جزوه با موفقیت حذف شد."];
                    } else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "خطا در حذف جزوه: " . $stmt_delete_b->error];}
                    $stmt_delete_b->close();
                } else $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "خطا آماده سازی حذف: " . $conn->error];
            }
        } else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "خطا بررسی وابستگی جزوه: " . $conn->error]; }
    }
    $csrf_token_booklets = regenerate_csrf_token('booklets_form_action');
    header("Location: booklets.php"); // Redirect to clear GET params and show flash message
    exit;
}

// Fetch all booklets for display
$booklets_list_query = $conn->query("SELECT BookletID, BookletName, Price, Description, (SELECT COUNT(*) FROM BookletAssignments ba WHERE ba.BookletID = b.BookletID) as assignment_count FROM Booklets b ORDER BY BookletName");
?>

<div class="page-header">
    <h1>مدیریت جزوات</h1>
    <div class="page-header-actions">
        <a href="assignments.php" class="btn btn-info">
             <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
            <span>تخصیص و پرداخت‌ها</span></a>
        <a href="teacher_accounts.php" class="btn btn-success">
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" x2="12" y1="2" y2="22"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
            <span>صورتحساب مدرسین</span></a>
    </div>
</div>

<?php
if (isset($_SESSION['flash_message'])) { /* Flash message display */
    $flash_bk = $_SESSION['flash_message'];
    echo "<div class='alert alert-{$flash_bk['type']} alert-dismissible fade show'>{$flash_bk['text']}<button type='button' class='close' data-dismiss='alert'>&times;</button></div>";
    unset($_SESSION['flash_message']);
}
if (!empty($errors)): ?> <div class="alert alert-danger"><ul><?php foreach ($errors as $err_bk): ?><li><?php echo htmlspecialchars($err_bk); ?></li><?php endforeach; ?></ul></div> <?php endif; ?>
<?php if ($success_message && empty($errors)): /* Only show if no new errors after success */ ?> <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div> <?php endif; ?>


<div class="row">
    <div class="col-lg-5 mb-4">
        <div class="card shadow-sm">
            <div class="card-header"><span class="card-title-text"><?php echo $edit_mode_booklet ? 'ویرایش جزوه: ' . htmlspecialchars($booklet_to_edit['BookletName']) : 'افزودن جزوه جدید'; ?></span></div>
            <div class="card-body">
                <form action="booklets.php<?php if($edit_mode_booklet && $booklet_to_edit['BookletID']) echo '?edit_id='.$booklet_to_edit['BookletID']; ?>" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_booklets; ?>">
                    <?php if ($edit_mode_booklet && $booklet_to_edit['BookletID']): ?><input type="hidden" name="booklet_id" value="<?php echo $booklet_to_edit['BookletID']; ?>"><?php endif; ?>
                    <div class="form-group">
                        <label for="booklet_name">نام جزوه <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="booklet_name" name="booklet_name" value="<?php echo htmlspecialchars($booklet_to_edit['BookletName']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="price">قیمت واحد (تومان) <span class="text-danger">*</span></label>
                        <input type="number" step="1" min="0" class="form-control" id="price" name="price" value="<?php echo htmlspecialchars($booklet_to_edit['Price']); ?>" required placeholder="مثال: 15000">
                    </div>
                    <div class="form-group">
                        <label for="description_booklet">توضیحات</label>
                        <textarea class="form-control" id="description_booklet" name="description_booklet" rows="2"><?php echo htmlspecialchars($booklet_to_edit['Description']); ?></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="submit_booklet" class="btn btn-primary">
                             <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                            <span><?php echo $edit_mode_booklet ? 'ذخیره تغییرات' : 'افزودن جزوه'; ?></span>
                        </button>
                        <?php if ($edit_mode_booklet): ?><a href="booklets.php" class="btn btn-outline-secondary">لغو ویرایش</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header"><span class="card-title-text">لیست جزوات تعریف شده</span></div>
            <div class="card-body">
                <?php if ($booklets_list_query && $booklets_list_query->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-hover">
                            <thead><tr><th>#</th><th>نام جزوه</th><th>قیمت (تومان)</th><th>تعداد تخصیص</th><th>عملیات</th></tr></thead>
                            <tbody>
                                <?php $b_row = 1; while ($b = $booklets_list_query->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $b_row++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($b['BookletName']); ?></strong>
                                        <?php if(!empty($b['Description'])):?><small class="d-block text-muted"><?php echo htmlspecialchars(mb_substr($b['Description'],0,70)).(mb_strlen($b['Description']) > 70 ? '...' : '');?></small><?php endif;?>
                                    </td>
                                    <td><?php echo number_format($b['Price'], 0, '.', ','); ?></td>
                                    <td><span class="badge badge-info"><?php echo $b['assignment_count']; ?></span></td>
                                    <td class="actions-cell">
                                        <a href="booklets.php?edit_id=<?php echo $b['BookletID']; ?>" class="btn btn-sm btn-warning" title="ویرایش"><svg class="icon" width="14" height="14" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></a>
                                        <a href="booklets.php?delete_id=<?php echo $b['BookletID']; ?>&csrf_token=<?php echo $csrf_token_booklets; ?>"
                                           class="btn btn-sm btn-danger <?php if($b['assignment_count'] > 0) echo 'disabled';?>"
                                           title="<?php if($b['assignment_count'] > 0) echo 'این جزوه تخصیص داده شده و قابل حذف نیست'; else echo 'حذف جزوه';?>"
                                           onclick="if(<?php echo $b['assignment_count']; ?> > 0) { alert(this.title); return false; } return confirm('آیا از حذف این جزوه مطمئن هستید؟');"><svg class="icon" width="14" height="14" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?><p class="text-muted text-center mt-3">هنوز جزوه‌ای در سیستم تعریف نشده است.</p><?php endif; if($booklets_list_query) $booklets_list_query->close(); ?>
            </div>
        </div>
    </div>
</div>
<script> /* Alert dismissal JS ... */ </script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
