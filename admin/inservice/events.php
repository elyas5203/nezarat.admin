<?php
// admin/inservice/events.php - Manage In-Service Training Events
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>مدیریت رویدادهای ضمن خدمت</h1>
    <p class="page-subtitle">ایجاد، ویرایش و مشاهده جلسات و دوره‌های آموزشی برای مدرسین.</p>
</div>

<div class="alert alert-info">
    محتوای این صفحه (شامل فرم افزودن/ویرایش رویداد و لیست رویدادها) به زودی پیاده‌سازی خواهد شد. این یک ساختار اولیه است.
</div>

<!-- Placeholder for Add/Edit Event Form -->
<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="mb-0">افزودن/ویرایش رویداد (نمونه)</h5>
    </div>
    <div class="card-body">
        <form>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="eventName">نام رویداد/جلسه</label>
                    <input type="text" class="form-control" id="eventName" placeholder="مثال: کارگاه آموزش روش‌های نوین تدریس">
                </div>
                <div class="form-group col-md-3">
                    <label for="eventDate">تاریخ برگزاری</label>
                    <input type="text" class="form-control persian-date-picker" id="eventDate" placeholder="مثال: ۱۴۰۳/۰۵/۱۰">
                </div>
                <div class="form-group col-md-3">
                    <label for="eventTime">ساعت برگزاری</label>
                    <input type="time" class="form-control" id="eventTime">
                </div>
            </div>
            <div class="form-group">
                <label for="eventLocation">مکان برگزاری</label>
                <input type="text" class="form-control" id="eventLocation" placeholder="مثال: سالن همایش‌های مرکزی">
            </div>
            <div class="form-group">
                <label for="eventTeacher">استاد/ارائه‌دهنده</label>
                <input type="text" class="form-control" id="eventTeacher" placeholder="نام استاد یا ارائه‌دهنده">
            </div>
            <div class="form-group">
                <label for="eventDescription">توضیحات</label>
                <textarea class="form-control" id="eventDescription" rows="3"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">ذخیره رویداد (نمایشی)</button>
        </form>
    </div>
</div>

<!-- Placeholder for Events List -->
<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">لیست رویدادهای ضمن خدمت (نمونه)</h5>
    </div>
    <div class="card-body">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>نام رویداد</th>
                    <th>تاریخ</th>
                    <th>مکان</th>
                    <th>استاد</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>کارگاه خلاقیت در تدریس</td>
                    <td>۱۴۰۳/۰۲/۱۵</td>
                    <td>دفتر مرکزی</td>
                    <td>دکتر رضایی</td>
                    <td><a href="#" class="btn btn-sm btn-info">ویرایش</a> <a href="#" class="btn btn-sm btn-danger">حذف</a></td>
                </tr>
                <tr>
                    <td>جلسه هم‌اندیشی ماهانه</td>
                    <td>۱۴۰۳/۰۳/۰۱</td>
                    <td>سالن اجتماعات</td>
                    <td>گروهی</td>
                    <td><a href="#" class="btn btn-sm btn-info">ویرایش</a> <a href="#" class="btn btn-sm btn-danger">حذف</a></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
<script src="/my_site/assets/js/common/persian-date.min.js"></script>
<script src="/my_site/assets/js/common/persian-datepicker.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof persianDatepicker === 'function') {
            var datePickers = document.querySelectorAll('.persian-date-picker');
            datePickers.forEach(function(picker) {
                new persianDatepicker(picker, {
                    format: 'YYYY/MM/DD',
                    autoClose: true,
                    observer: true,
                    calendar: { persian: { locale: 'fa'}},
                    toolbox:{ calendarSwitch:{ enabled:false }}
                });
            });
        } else {
            console.error("Persian Datepicker library not loaded.");
        }
    });
</script>
<?php
require_once __DIR__ . '/../includes/footer.php';
?>
