<?php
// admin/recruitment/prospects.php - Manage Recruitment Prospects (Children)
require_once __DIR__ . '/../includes/header.php';

// Placeholder data for regions - this would normally come from the database
$sample_regions_for_select = [
    ['RegionID' => 1, 'RegionName' => 'منطقه وکیل آباد - راست'],
    ['RegionID' => 2, 'RegionName' => 'منطقه وکیل آباد - چپ'],
    ['RegionID' => 3, 'RegionName' => 'منطقه احمدآباد'],
    ['RegionID' => 4, 'RegionName' => 'منطقه قاسم آباد'],
];

// Placeholder data for prospects
$sample_prospects = [
    ['ProspectID' => 1, 'FirstName' => 'علی', 'LastName' => 'رضایی', 'ReferrerName' => 'حسین احمدی', 'ParentName' => 'محمد رضایی', 'PhoneNumber' => '09150000001', 'RegionID' => 1, 'RegionName' => 'منطقه وکیل آباد - راست', 'DateAdded' => '1402/10/15', 'Notes' => 'علاقمند به کلاس‌های تابستانی'],
    ['ProspectID' => 2, 'FirstName' => 'زهرا', 'LastName' => 'محمدی', 'ReferrerName' => 'فاطمه کریمی', 'ParentName' => 'کاظم محمدی', 'PhoneNumber' => '09150000002', 'RegionID' => 3, 'RegionName' => 'منطقه احمدآباد', 'DateAdded' => '1402/11/01', 'Notes' => ''],
    ['ProspectID' => 3, 'FirstName' => 'محمد', 'LastName' => 'حسینی', 'ReferrerName' => 'خودشان', 'ParentName' => 'جواد حسینی', 'PhoneNumber' => '09150000003', 'RegionID' => 1, 'RegionName' => 'منطقه وکیل آباد - راست', 'DateAdded' => '1403/01/20', 'Notes' => 'قبلا هم در مراسم غدیر شرکت کرده بودند.'],
];

$edit_mode = false;
$prospect_to_edit = [
    'ProspectID' => '', 'FirstName' => '', 'LastName' => '', 'ReferrerName' => '',
    'ParentName' => '', 'PhoneNumber' => '', 'RegionID' => '', 'Notes' => ''
]; // Initialize for form fields

if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $edit_id = (int)$_GET['id'];
    foreach ($sample_prospects as $prospect) {
        if ($prospect['ProspectID'] == $edit_id) {
            $prospect_to_edit = $prospect;
            $edit_mode = true;
            break;
        }
    }
     if (!$edit_mode) { // If prospect not found for editing
         echo "<div class='alert alert-danger'>فرد مورد نظر برای ویرایش یافت نشد. <a href='prospects.php'>بازگشت به لیست</a></div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_prospect']) || isset($_POST['edit_prospect']))) {
    // CSRF check would go here
    $first_name = sanitize_input($_POST['first_name'] ?? '');
    $last_name = sanitize_input($_POST['last_name'] ?? '');
    $referrer_name = sanitize_input($_POST['referrer_name'] ?? '');
    $parent_name = sanitize_input($_POST['parent_name'] ?? '');
    $phone_number = sanitize_input($_POST['phone_number'] ?? '');
    $region_id = sanitize_input($_POST['region_id'] ?? '');
    $notes = sanitize_input($_POST['notes'] ?? '');
    $prospect_id_to_update = sanitize_input($_POST['prospect_id'] ?? null); // For edits

    if (!empty($first_name) && !empty($last_name)) { // Basic validation
        if ($prospect_id_to_update) { // Edit
            $feedback_message = "اطلاعات ".htmlspecialchars($first_name." ".$last_name)." (نمونه) ویرایش شد.";
            // Logic to update $sample_prospects or DB would go here
        } else { // Add
            $feedback_message = "فرد ".htmlspecialchars($first_name." ".$last_name)." (نمونه) اضافه شد.";
            // Logic to add to $sample_prospects or DB
        }
        echo "<div class='alert alert-success mt-3'>".$feedback_message." (این عملیات هنوز به دیتابیس متصل نیست)</div>";
        // To prevent re-submission and clear form, redirect or clear variables.
        // For now, we'll reset $prospect_to_edit to clear the form for a new entry if it was an add.
        if(!$prospect_id_to_update) {
             $prospect_to_edit = [ /* reset array */
                'ProspectID' => '', 'FirstName' => '', 'LastName' => '', 'ReferrerName' => '',
                'ParentName' => '', 'PhoneNumber' => '', 'RegionID' => '', 'Notes' => ''
            ];
        }

    } else {
         echo "<div class='alert alert-danger mt-3'>نام و نام خانوادگی فرد نمی‌تواند خالی باشد.</div>";
    }
}

// Filtering logic
$filter_region_id = isset($_GET['filter_region_id']) ? (int)$_GET['filter_region_id'] : null;
$search_term = isset($_GET['search']) ? sanitize_input(trim($_GET['search'])) : '';

$filtered_prospects = $sample_prospects;

if ($filter_region_id && $filter_region_id > 0) {
    $filtered_prospects = array_filter($filtered_prospects, function($p) use ($filter_region_id) {
        return isset($p['RegionID']) && $p['RegionID'] == $filter_region_id;
    });
}
if (!empty($search_term)) {
    $search_term_lower = mb_strtolower($search_term, 'UTF-8');
    $filtered_prospects = array_filter($filtered_prospects, function($p) use ($search_term_lower) {
        return (mb_stripos($p['FirstName'], $search_term_lower, 0, 'UTF-8') !== false ||
                mb_stripos($p['LastName'], $search_term_lower, 0, 'UTF-8') !== false ||
                (isset($p['ParentName']) && mb_stripos($p['ParentName'], $search_term_lower, 0, 'UTF-8') !== false) ||
                (isset($p['PhoneNumber']) && mb_stripos($p['PhoneNumber'], $search_term_lower, 0, 'UTF-8') !== false) ||
                (isset($p['ReferrerName']) && mb_stripos($p['ReferrerName'], $search_term_lower, 0, 'UTF-8') !== false)
            );
    });
}
?>

<div class="page-header">
    <h1>مدیریت افراد جذب شده (بچه‌ها)</h1>
    <p class="page-subtitle">ثبت، ویرایش و مشاهده اطلاعات افراد جدید جذب شده.</p>
</div>

<div class="row">
    <div class="col-lg-4 col-md-12">
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                 <h5 class="mb-0"><?php echo $edit_mode ? 'ویرایش اطلاعات: ' . htmlspecialchars($prospect_to_edit['FirstName'].' '.$prospect_to_edit['LastName']) : 'افزودن فرد جدید'; ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" action="prospects.php<?php echo $edit_mode ? '?action=edit&id='.htmlspecialchars($prospect_to_edit['ProspectID']) : ''; ?>">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="prospect_id" value="<?php echo htmlspecialchars($prospect_to_edit['ProspectID']); ?>">
                    <?php endif; ?>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="first_name">نام:<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($prospect_to_edit['FirstName']); ?>" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="last_name">نام خانوادگی:<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($prospect_to_edit['LastName']); ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="referrer_name">نام معرف:</label>
                        <input type="text" class="form-control" id="referrer_name" name="referrer_name" value="<?php echo htmlspecialchars($prospect_to_edit['ReferrerName']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="parent_name">نام پدر یا مادر:</label>
                        <input type="text" class="form-control" id="parent_name" name="parent_name" value="<?php echo htmlspecialchars($prospect_to_edit['ParentName']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="phone_number">شماره تماس:</label>
                        <input type="tel" class="form-control" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($prospect_to_edit['PhoneNumber']); ?>" pattern="[0-9۰-۹]{10,11}" title="شماره موبایل معتبر 10 یا 11 رقمی وارد کنید">
                    </div>
                    <div class="form-group">
                        <label for="region_id">منطقه:</label>
                        <select class="form-control custom-select" id="region_id" name="region_id">
                            <option value="">-- انتخاب منطقه --</option>
                            <?php foreach ($sample_regions_for_select as $region_opt): ?>
                                <option value="<?php echo $region_opt['RegionID']; ?>" <?php if ($prospect_to_edit['RegionID'] == $region_opt['RegionID']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($region_opt['RegionName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="notes">ملاحظات:</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($prospect_to_edit['Notes']); ?></textarea>
                    </div>
                     <?php if ($edit_mode): ?>
                        <button type="submit" name="edit_prospect" class="btn btn-primary">ذخیره تغییرات</button>
                        <a href="prospects.php" class="btn btn-outline-secondary ml-2">انصراف</a>
                    <?php else: ?>
                        <button type="submit" name="add_prospect" class="btn btn-success">افزودن فرد</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8 col-md-12">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">لیست افراد جذب شده (کل نمایش: <?php echo count($filtered_prospects); ?> / کل واقعی: <?php echo count($sample_prospects);?>)</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="prospects.php" class="form-inline mb-3">
                    <div class="form-group mr-sm-2 mb-2">
                        <label for="search_input" class="sr-only">جستجو</label>
                        <input type="text" name="search" id="search_input" class="form-control" placeholder="جستجو..." value="<?php echo htmlspecialchars($search_term); ?>">
                    </div>
                    <div class="form-group mr-sm-2 mb-2">
                        <label for="filter_region_id_select" class="sr-only">منطقه</label>
                        <select name="filter_region_id" id="filter_region_id_select" class="form-control custom-select">
                            <option value="">همه مناطق</option>
                             <?php foreach ($sample_regions_for_select as $region_opt): ?>
                                <option value="<?php echo $region_opt['RegionID']; ?>" <?php if ($filter_region_id == $region_opt['RegionID']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($region_opt['RegionName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-info mb-2">فیلتر/جستجو</button>
                     <a href="prospects.php" class="btn btn-outline-secondary ml-sm-2 mb-2">پاک کردن</a>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-hover table-sm">
                        <thead>
                            <tr>
                                <th>نام</th>
                                <th>نام خانوادگی</th>
                                <th>والدین</th>
                                <th>تماس</th>
                                <th>منطقه</th>
                                <th>تاریخ ثبت</th>
                                <th>یادداشت</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($filtered_prospects)): ?>
                                <tr><td colspan="8" class="text-center">هیچ فردی با این مشخصات یافت نشد.</td></tr>
                            <?php else: ?>
                                <?php foreach ($filtered_prospects as $prospect): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($prospect['FirstName']); ?></td>
                                    <td><?php echo htmlspecialchars($prospect['LastName']); ?></td>
                                    <td><?php echo htmlspecialchars($prospect['ParentName'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($prospect['PhoneNumber'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($prospect['RegionName'] ?? 'نامشخص'); ?></td>
                                    <td><?php echo htmlspecialchars($prospect['DateAdded'] ?? '-'); ?></td>
                                    <td title="<?php echo htmlspecialchars($prospect['Notes']); ?>"><?php echo htmlspecialchars(mb_substr($prospect['Notes'],0, 20).(mb_strlen($prospect['Notes']) > 20 ? '...' : '')); ?></td>
                                    <td>
                                        <a href="prospects.php?action=edit&id=<?php echo $prospect['ProspectID']; ?>" class="btn btn-sm btn-outline-primary" title="ویرایش">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16"><path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/><path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"/></svg>
                                        </a>
                                        <a href="prospects.php?action=delete&id=<?php echo $prospect['ProspectID']; ?>" onclick="return confirm('آیا از حذف \'<?php echo htmlspecialchars(addslashes($prospect['FirstName'].' '.$prospect['LastName'])); ?>\' مطمئن هستید؟ این عملیات فعلا نمایشی است.');" class="btn btn-sm btn-outline-danger" title="حذف">
                                             <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-trash3-fill" viewBox="0 0 16 16"><path d="M11 1.5v1h3.5a.5.5 0 0 1 0 1h-.538l-.853 10.66A2 2 0 0 1 11.115 16h-6.23a2 2 0 0 1-1.994-1.84L2.038 3.5H1.5a.5.5 0 0 1 0-1H5v-1A1.5 1.5 0 0 1 6.5 0h3A1.5 1.5 0 0 1 11 1.5Zm-5 0v1h4v-1a.5.5 0 0 0-.5-.5h-3a.5.5 0 0 0-.5.5ZM4.5 5.024l.088-.88A.5.5 0 0 1 5 4h6a.5.5 0 0 1 .412.223l.088.88H4.5Z"/></svg>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
