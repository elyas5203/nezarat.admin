<?php
// admin/recruitment/index.php - Recruitment Dashboard
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>داشبورد جذب و راه‌اندازی</h1>
    <p class="page-subtitle">نمای کلی از فعالیت‌ها و آمارهای مرتبط با جذب افراد جدید.</p>
</div>

<div class="row">
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">تعداد کل افراد جذب شده (نمونه)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">150</div>
                    </div>
                    <div class="col-auto">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" class="bi bi-people-fill text-gray-300" viewBox="0 0 16 16"><path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1H7zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/><path fill-rule="evenodd" d="M5.216 14A2.238 2.238 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.325 6.325 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1h4.216zM4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z"/></svg>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">تعداد مناطق فعال (نمونه)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">12</div>
                    </div>
                    <div class="col-auto">
                         <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" class="bi bi-map-fill text-gray-300" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M16 .5a.5.5 0 0 0-.598-.49L10.5.99 5.598.01a.5.5 0 0 0-.196 0l-5 1A.5.5 0 0 0 0 1.5v14a.5.5 0 0 0 .598.49l4.902-.98 4.902.98a.502.502 0 0 0 .196 0l5-1A.5.5 0 0 0 16 14.5V.5zM5 14.09V1.11l.5-.1.5.1v12.98l-.402-.08a.498.498 0 0 0-.196 0L5 14.09zm5 .8V1.91l.402.08a.5.5 0 0 0 .196 0L11 1.91v12.98l-.5.1-.5-.1z"/></svg>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">تعداد مراسم‌های جذب (نمونه)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">4</div>
                    </div>
                    <div class="col-auto">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" class="bi bi-calendar-event-fill text-gray-300" viewBox="0 0 16 16"><path d="M4 .5a.5.5 0 0 0-1 0V1H2a2 2 0 0 0-2 2v1h16V3a2 2 0 0 0-2-2h-1V.5a.5.5 0 0 0-1 0V1H4V.5zM16 14V5H0v9a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2zm-3.5-7h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5z"/></svg>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">جذب در ماه جاری (نمونه)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">18</div>
                    </div>
                    <div class="col-auto">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" class="bi bi-person-plus-fill text-gray-300" viewBox="0 0 16 16"><path d="M1 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H1zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/><path fill-rule="evenodd" d="M13.5 5a.5.5 0 0 1 .5.5V7h1.5a.5.5 0 0 1 0 1H14v1.5a.5.5 0 0 1-1 0V8h-1.5a.5.5 0 0 1 0-1H13V5.5a.5.5 0 0 1 .5-.5z"/></svg>
                    </div>
                </div>
            </div>
        </div>
    </div>
     <div class="col-lg-3 col-md-6 mb-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">فعال‌ترین منطقه (نمونه)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">قاسم آباد (40 نفر)</div>
                    </div>
                    <div class="col-auto">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" class="bi bi-pin-map-fill text-gray-300" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M3.1 11.2a.5.5 0 0 1 .4-.2H6a.5.5 0 0 1 0 1H3.75L1.5 15h13l-2.25-3H10a.5.5 0 0 1 0-1h2.5a.5.5 0 0 1 .4.2l3 4a.5.5 0 0 1-.4.8H.5a.5.5 0 0 1-.4-.8l3-4z"/><path fill-rule="evenodd" d="M4 4a4 4 0 1 1 4.5 3.969V13.5a.5.5 0 0 1-1 0V7.97A4 4 0 0 1 4 3.999zm2.493 8.574a.5.5 0 0 1-.411.575c-.712.118-1.28.295-1.655.493a1.319 1.319 0 0 0-.37.265.5.5 0 0 0 .822.565.29.29 0 0 0 .07-.088l.04-.04.002-.002a.5.5 0 0 1 .7-.04L8 14.09V10.5a.5.5 0 0 1 1 0v3.59l.07-.04a.5.5 0 0 1 .7.04l.002.002.04.04.07.088a.5.5 0 0 0 .822-.565 1.319 1.319 0 0 0-.37-.265c-.375-.198-.943-.375-1.655-.493a.5.5 0 0 1-.411-.575V9.99a.5.5 0 0 1 1 0v2.085a2.5 2.5 0 0 0-1-1.58V9.99a.5.5 0 0 1 1 0v.03a.5.5 0 0 1 1 0V10a.5.5 0 0 1 1 0v.03a.5.5 0 0 1 1 0v.03a.5.5 0 0 1 1 0V10a.5.5 0 0 1 .5-.5h.03a.5.5 0 0 1 .5.5v.03a.5.5 0 0 1 .5.5V10a.5.5 0 0 1 .5.5v.03a.5.5 0 0 1 .5.5V10a.5.5 0 0 1 .5.5v.03a.5.5 0 0 1 .5.5V10.5a.5.5 0 0 1 1 0V7.97A4.002 4.002 0 0 1 8 0a4 4 0 0 1 3.053 1.454L12 2.4l-.053-.046A3.98 3.98 0 0 1 8 2a3.98 3.98 0 0 1-3.947.046L4 2.4l.947-1.046A4.002 4.002 0 0 1 8 0z"/></svg>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">روند جذب در ۶ ماه گذشته (نمودار نمونه)</h5>
            </div>
            <div class="card-body">
                <div class="text-center py-5">
                    <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" class="bi bi-bar-chart-line-fill text-gray-300" viewBox="0 0 16 16">
                        <path d="M11 2a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v12h.5a.5.5 0 0 1 0 1H.5a.5.5 0 0 1 0-1H1v-3a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3h1V7a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v7h1V2z"/>
                    </svg>
                    <p class="mt-2 text-muted">محتوای نمودار به زودی در اینجا نمایش داده خواهد شد.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<p class="mt-4">این صفحه به زودی با نمودارها و اطلاعات دقیق‌تر از فعالیت‌های جذب تکمیل خواهد شد.</p>
<!-- Further content for recruitment dashboard -->

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
