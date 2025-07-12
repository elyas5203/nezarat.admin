<?php
require_once __DIR__ . '/../includes/header.php';

// Placeholder for financial stats - to be replaced with actual queries
$finance_stats = [
    'total_donations_current_year' => 0,
    'total_booklet_debt_outstanding' => 0,
    'warehouse_item_categories' => 0,
    'total_warehouse_value' => 0, // This would be complex to calculate accurately
];

if($conn){
    // Example: Total donations for the current Persian year
    // $current_persian_year_start = ... (logic to determine start of current Persian year in Gregorian)
    // $current_persian_year_end = ... (logic to determine end of current Persian year in Gregorian)
    // $stmt_donations = $conn->prepare("SELECT SUM(Amount) as total FROM Donations WHERE DonationDate >= ? AND DonationDate <= ? AND Status = 'collected'");
    // if($stmt_donations){ $stmt_donations->bind_param("ss", $current_persian_year_start, $current_persian_year_end); $stmt_donations->execute(); ... }

    // Example: Total outstanding debt for booklets
    // This would require a table like TeacherBookletAccounts with balances
    // $stmt_debt = $conn->query("SELECT SUM(OutstandingBalance) as total_debt FROM TeacherBookletAccounts WHERE OutstandingBalance > 0");
    // if($stmt_debt) $finance_stats['total_booklet_debt_outstanding'] = $stmt_debt->fetch_assoc()['total_debt'] ?? 0;

    // Example: Number of distinct categories in warehouse
    // $stmt_wh_cat = $conn->query("SELECT COUNT(DISTINCT Category) FROM WarehouseItems");
    // if($stmt_wh_cat) $finance_stats['warehouse_item_categories'] = $stmt_wh_cat->fetch_row()[0] ?? 0;
}

?>
<div class="page-header">
    <h1>داشبورد مالی و پشتیبانی</h1>
    <p class="page-subtitle">مدیریت انبار، کمک‌های مالی (صله)، جزوات و حساب‌های مرتبط.</p>
</div>

<?php if (isset($_SESSION['action_success_finance'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['action_success_finance']; unset($_SESSION['action_success_finance']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if (isset($_SESSION['action_error_finance'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['action_error_finance']; unset($_SESSION['action_error_finance']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row gy-4">
    <div class="col-lg-4 col-md-6">
        <div class="card shadow-sm">
            <div class="card-body text-center">
                <em class="bi bi-box-seam-fill fs-1 text-primary mb-3 d-block"></em>
                <h5 class="card-title">مدیریت انبار</h5>
                <p class="card-text small text-muted">ثبت و پیگیری اقلام موجود در انبار.</p>
                <a href="warehouse.php" class="btn btn-primary">ورود به انبار</a>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6">
        <div class="card shadow-sm">
            <div class="card-body text-center">
                <em class="bi bi-cash-coin fs-1 text-success mb-3 d-block"></em>
                <h5 class="card-title">مدیریت صله (کمک‌های مالی)</h5>
                <p class="card-text small text-muted">ثبت و پیگیری کمک‌های مالی دریافتی.</p>
                <a href="donations.php" class="btn btn-success">ورود به بخش صله</a>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6">
        <div class="card shadow-sm">
            <div class="card-body text-center">
                <em class="bi bi-book-half fs-1 text-info mb-3 d-block"></em>
                <h5 class="card-title">مدیریت جزوات</h5>
                <p class="card-text small text-muted">ثبت تحویل جزوات و مدیریت حساب مدرسین.</p>
                <a href="booklets.php" class="btn btn-info">ورود به بخش جزوات</a>
            </div>
        </div>
    </div>
    <!-- Placeholder for Teacher Accounts if it's a separate major section -->
    <!-- <div class="col-lg-3 col-md-6">
        <div class="card shadow-sm">
            <div class="card-body text-center">
                <em class="bi bi-person-badge fs-1 text-warning mb-3 d-block"></em>
                <h5 class="card-title">حساب‌های مالی مدرسین</h5>
                <p class="card-text small text-muted">مشاهده وضعیت کلی حساب‌های مدرسین.</p>
                <a href="teacher_accounts.php" class="btn btn-warning">مشاهده حساب‌ها</a>
            </div>
        </div>
    </div> -->
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">آمار کلی مالی (نمونه)</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <p class="mb-1"><strong>مجموع صله سال جاری:</strong> <?php echo number_format($finance_stats['total_donations_current_year']); ?> تومان</p>
                    </div>
                    <div class="col-md-4">
                        <p class="mb-1"><strong>بدهی معوقه جزوات:</strong> <?php echo number_format($finance_stats['total_booklet_debt_outstanding']); ?> تومان</p>
                    </div>
                    <div class="col-md-4">
                        <p class="mb-1"><strong>تعداد دسته‌بندی اقلام انبار:</strong> <?php echo $finance_stats['warehouse_item_categories']; ?></p>
                    </div>
                </div>
                <p class="text-muted mt-3 small">توجه: این آمارها نمونه هستند و با تکمیل ماژول‌ها، داده‌های واقعی جایگزین خواهند شد.</p>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
