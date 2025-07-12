<?php
require_once __DIR__ . '/../includes/header.php'; // General admin header

// This page will serve as the dashboard for the "Parvareshi" (Cultural/Educational Activities) module.
// It will provide quick links to various sub-modules and potentially some overview stats.

// Example stats (to be replaced with actual queries later)
$parvareshi_stats = [
    'active_class_services' => 0, // Number of classes with active service plans for upcoming events
    'rental_items_available' => 0, // Number of distinct rental items available
    'upcoming_general_events' => 0, // Number of upcoming general events (like Nime Shaban, Ghadir by Parvareshi team)
    'pending_rental_requests' => 0, // Number of rental requests needing approval/action
];

if ($conn) {
    // Placeholder: Query for active class services (e.g., service plans for next 30 days)
    // $stmt_cs = $conn->query("SELECT COUNT(DISTINCT ClassID) FROM ParvareshiClassServices WHERE EventDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND Status = 'approved'");
    // if($stmt_cs) $parvareshi_stats['active_class_services'] = $stmt_cs->fetch_row()[0] ?? 0;

    // Placeholder: Query for available rental items
    // $stmt_ri = $conn->query("SELECT COUNT(ItemID) FROM ParvareshiRentalItems WHERE IsAvailable = TRUE");
    // if($stmt_ri) $parvareshi_stats['rental_items_available'] = $stmt_ri->fetch_row()[0] ?? 0;

    // Placeholder: Query for upcoming general events by Parvareshi
    // $stmt_ge = $conn->query("SELECT COUNT(GeneralEventID) FROM ParvareshiGeneralEvents WHERE EventDate >= CURDATE() AND Status = 'planned'");
    // if($stmt_ge) $parvareshi_stats['upcoming_general_events'] = $stmt_ge->fetch_row()[0] ?? 0;

    // Placeholder: Query for pending rental requests
    // $stmt_rr = $conn->query("SELECT COUNT(RequestID) FROM ParvareshiRentalRequests WHERE Status = 'pending_approval'");
    // if($stmt_rr) $parvareshi_stats['pending_rental_requests'] = $stmt_rr->fetch_row()[0] ?? 0;
}

?>

<div class="page-header">
    <h1>بخش پرورشی</h1>
    <p class="page-subtitle">مدیریت خدمت‌گزاری کلاس‌ها، کرایه‌چی، مناسبت‌های عمومی و اردوها.</p>
</div>

<?php if (isset($_SESSION['action_success_parvareshi'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['action_success_parvareshi']; unset($_SESSION['action_success_parvareshi']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if (isset($_SESSION['action_error_parvareshi'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['action_error_parvareshi']; unset($_SESSION['action_error_parvareshi']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row gy-4">
    <!-- Quick Links Column -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">دسترسی سریع</h5></div>
            <div class="list-group list-group-flush">
                <a href="class_services.php" class="list-group-item list-group-item-action">
                    <em class="bi bi-stars me-2"></em> مدیریت خدمت‌گزاری کلاس‌ها
                </a>
                <a href="rental_items.php" class="list-group-item list-group-item-action">
                    <em class="bi bi-box-seam me-2"></em> مدیریت اقلام کرایه‌چی
                </a>
                <a href="rental_bookings.php" class="list-group-item list-group-item-action">
                    <em class="bi bi-calendar-check me-2"></em> رزروهای کرایه‌چی
                </a>
                <a href="events_general.php" class="list-group-item list-group-item-action">
                    <em class="bi bi-calendar3-event me-2"></em> مدیریت مناسبت‌های عمومی و اردوها
                </a>
                <!-- Add more links as sub-modules are developed -->
            </div>
        </div>
    </div>

    <!-- Stats/Overview Column -->
    <div class="col-lg-8">
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card border-start border-info border-4 shadow-sm h-100">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs fw-bold text-info text-uppercase mb-1">خدمت‌گزاری فعال کلاس‌ها</div>
                                <div class="h5 mb-0 fw-bold text-gray-800"><?php echo $parvareshi_stats['active_class_services']; ?> مورد</div>
                            </div>
                            <div class="col-auto"><em class="bi bi-award-fill fs-2 text-gray-300"></em></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card border-start border-success border-4 shadow-sm h-100">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs fw-bold text-success text-uppercase mb-1">اقلام کرایه‌چی موجود</div>
                                <div class="h5 mb-0 fw-bold text-gray-800"><?php echo $parvareshi_stats['rental_items_available']; ?> قلم</div>
                            </div>
                            <div class="col-auto"><em class="bi bi-box2-heart-fill fs-2 text-gray-300"></em></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card border-start border-primary border-4 shadow-sm h-100">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs fw-bold text-primary text-uppercase mb-1">مناسبت/اردوی عمومی پیش رو</div>
                                <div class="h5 mb-0 fw-bold text-gray-800"><?php echo $parvareshi_stats['upcoming_general_events']; ?> مورد</div>
                            </div>
                            <div class="col-auto"><em class="bi bi-flag-fill fs-2 text-gray-300"></em></div>
                        </div>
                    </div>
                </div>
            </div>
             <div class="col-md-6 mb-4">
                <div class="card border-start border-warning border-4 shadow-sm h-100">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs fw-bold text-warning text-uppercase mb-1">درخواست‌های رزرو کرایه‌چی</div>
                                <div class="h5 mb-0 fw-bold text-gray-800"><?php echo $parvareshi_stats['pending_rental_requests']; ?> درخواست</div>
                            </div>
                            <div class="col-auto"><em class="bi bi-hourglass-split fs-2 text-gray-300"></em></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-2">
            <div class="card-header"><h5 class="mb-0">تاریخچه فعالیت‌ها (نمونه)</h5></div>
            <div class="card-body" style="max-height: 250px; overflow-y: auto;">
                <ul class="list-unstyled">
                    <li class="mb-2 pb-2 border-bottom">
                        <small class="text-muted float-end">2 ساعت پیش</small>
                        <strong>کلاس پنجم الف:</strong> درخواست رزرو پروژکتور برای جشن غدیر ثبت شد.
                    </li>
                     <li class="mb-2 pb-2 border-bottom">
                        <small class="text-muted float-end">دیروز</small>
                        <strong>مراسم عمومی نیمه شعبان:</strong> گزارش نهایی و تصاویر بارگذاری شد.
                    </li>
                     <li class="mb-2">
                        <small class="text-muted float-end">۳ روز پیش</small>
                        <strong>کرایه‌چی:</strong> آیتم "ریسه LED جدید (۱۰ متر)" اضافه شد.
                    </li>
                </ul>
                <!-- Actual activity log to be implemented -->
            </div>
        </div>

    </div>
</div>


<?php
require_once __DIR__ . '/../includes/footer.php';
?>
