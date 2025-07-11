<?php
// admin/inservice/checklists_templates.php - Manage In-Service Checklist Templates
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>مدیریت قالب‌های چک‌لیست ضمن خدمت</h1>
    <p class="page-subtitle">ایجاد و ویرایش قالب‌های چک‌لیست برای استفاده در رویدادهای مختلف ضمن خدمت.</p>
</div>

<div class="alert alert-info">
    محتوای این صفحه (شامل فرم ایجاد/ویرایش قالب چک‌لیست و لیست آیتم‌های آن) به زودی پیاده‌سازی خواهد شد. این یک ساختار اولیه است.
</div>

<!-- Placeholder for Add/Edit Checklist Template Form -->
<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="mb-0">ایجاد/ویرایش قالب چک‌لیست (نمونه)</h5>
    </div>
    <div class="card-body">
        <form>
            <div class="form-group">
                <label for="templateName">نام قالب چک‌لیست</label>
                <input type="text" class="form-control" id="templateName" placeholder="مثال: چک‌لیست استاندارد قبل از جلسه">
            </div>
            <div class="form-group">
                <label for="templateDescription">توضیحات قالب</label>
                <textarea class="form-control" id="templateDescription" rows="2"></textarea>
            </div>

            <h6>آیتم‌های چک‌لیست:</h6>
            <div id="checklistItemsContainer">
                <!-- Items will be added here dynamically by JS or loaded for edit -->
                <div class="form-row align-items-center mb-2">
                    <div class="col-sm-7">
                        <input type="text" class="form-control" placeholder="متن آیتم چک‌لیست">
                    </div>
                    <div class="col-sm-3">
                        <select class="form-control custom-select">
                            <option value="">نوع مسئول پیش‌فرض</option>
                            <option value="admin">ادمین</option>
                            <option value="teacher">مدرس</option>
                            <option value="department_head">مدیر بخش</option>
                        </select>
                    </div>
                    <div class="col-sm-1">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="itemRequired1">
                            <label class="form-check-label" for="itemRequired1">الزامی</label>
                        </div>
                    </div>
                    <div class="col-sm-1">
                        <button type="button" class="btn btn-sm btn-danger">&times;</button>
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-success mt-2">افزودن آیتم جدید</button>
            <hr>
            <button type="submit" class="btn btn-primary">ذخیره قالب (نمایشی)</button>
        </form>
    </div>
</div>

<!-- Placeholder for Templates List -->
<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">لیست قالب‌های چک‌لیست (نمونه)</h5>
    </div>
    <div class="card-body">
        <p class="text-muted">جدول نمایش قالب‌های چک‌لیست در اینجا قرار خواهد گرفت.</p>
         <ul class="list-group">
            <li class="list-group-item d-flex justify-content-between align-items-center">
                چک‌لیست استاندارد قبل از جلسه
                <span class="badge badge-primary badge-pill">5 آیتم</span>
                <span>
                    <a href="#" class="btn btn-sm btn-info">ویرایش</a>
                    <a href="#" class="btn btn-sm btn-danger">حذف</a>
                </span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                چک‌لیست بعد از اتمام کارگاه
                <span class="badge badge-primary badge-pill">3 آیتم</span>
                 <span>
                    <a href="#" class="btn btn-sm btn-info">ویرایش</a>
                    <a href="#" class="btn btn-sm btn-danger">حذف</a>
                </span>
            </li>
        </ul>
    </div>
</div>


<?php
require_once __DIR__ . '/../includes/footer.php';
?>
