<?php
// admin/inservice/index.php - In-Service Training Dashboard/Calendar
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>داشبورد و تقویم ضمن خدمت</h1>
    <p class="page-subtitle">نمای کلی و برنامه‌ریزی جلسات و دوره‌های ضمن خدمت برای مدرسین.</p>
</div>

<div class="alert alert-info">
    محتوای این صفحه (شامل تقویم رویدادها و آمارهای مرتبط) به زودی پیاده‌سازی خواهد شد.
</div>

<!-- Placeholder for calendar or event list -->
<div class="row">
    <div class="col-md-12">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">تقویم رویدادهای ضمن خدمت (نمونه)</h5>
            </div>
            <div class="card-body" style="min-height: 400px;">
                <p class="text-muted text-center py-5">
                    <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" class="bi bi-calendar3-week-fill mb-3 text-gray-300" viewBox="0 0 16 16"><path d="M2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H2zm10.5 3.5h-9a.5.5 0 0 1 0-1h9a.5.5 0 0 1 0 1zm-2 2h-5a.5.5 0 0 1 0-1h5a.5.5 0 0 1 0 1zm-5.509 6.354a.662.662 0 0 1 .01-.011l.34-.341a.662.662 0 0 1 .936.001l.145.144a.66.66 0 0 1 .01.011l.34-.341a.662.662 0 0 1 .936.001l.145.144a.66.66 0 0 1 .01.011l.34-.34a.662.662 0 0 1 .936.001l.145.144a.66.66 0 0 1 .01.011l.34-.34a.662.662 0 0 1 .936.001l.145.144a.66.66 0 0 1 .01.011l.34-.341a.662.662 0 0 1 .936.001l.145.144a.66.66 0 0 1 .01.011l.34-.34a.662.662 0 0 1 .936.001l.145.144a.66.66 0 0 1 .01.011l.34-.34a.662.662 0 0 1 .936.001l.145.144a.66.66 0 0 1 .01.011l.34-.34a.662.662 0 0 1 .936.001l.145.144a.66.66 0 0 1 .01.011l.34-.34a.662.662 0 0 1 .936.001l.145.144a.66.66 0 0 1 .01.011zM1 5.583V14h14V5.583H1z"/></svg>
                    <br>
                    تقویم در اینجا بارگذاری خواهد شد.
                </p>
            </div>
        </div>
    </div>
</div>


<?php
require_once __DIR__ . '/../includes/footer.php';
?>
