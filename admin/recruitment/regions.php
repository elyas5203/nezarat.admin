<?php
// admin/recruitment/regions.php - Manage Recruitment Regions
require_once __DIR__ . '/../includes/header.php';

// Placeholder data for regions - will be replaced by database logic
$sample_regions = [
    ['RegionID' => 1, 'RegionName' => 'منطقه وکیل آباد - راست', 'Description' => 'شامل محلات محدوده راست وکیل آباد', 'ProspectCount' => 25],
    ['RegionID' => 2, 'RegionName' => 'منطقه وکیل آباد - چپ', 'Description' => 'شامل محلات محدوده چپ وکیل آباد', 'ProspectCount' => 18],
    ['RegionID' => 3, 'RegionName' => 'منطقه احمدآباد', 'Description' => 'محدوده خیابان احمدآباد و اطراف', 'ProspectCount' => 32],
    ['RegionID' => 4, 'RegionName' => 'منطقه قاسم آباد', 'Description' => 'شهرک غرب و الهیه', 'ProspectCount' => 40],
];

$edit_mode = false;
$region_to_edit = ['RegionID' => '', 'RegionName' => '', 'Description' => '']; // Initialize for form fields

if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $edit_id = (int)$_GET['id'];
    foreach ($sample_regions as $region) {
        if ($region['RegionID'] == $edit_id) {
            $region_to_edit = $region;
            $edit_mode = true;
            break;
        }
    }
    if (!$edit_mode) {
         echo "<div class='alert alert-danger'>منطقه مورد نظر برای ویرایش یافت نشد.</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_region']) || isset($_POST['edit_region']))) {
    // Placeholder for handling form submission (add/edit)
    // In a real app, this would involve CSRF check, validation, and DB operations
    $region_name = sanitize_input($_POST['region_name'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $region_id = sanitize_input($_POST['region_id'] ?? null); // For edits

    if (!empty($region_name)) { // Basic validation
        if ($region_id) { // Edit
            $feedback_message = "منطقه \"".htmlspecialchars($region_name)."\" (نمونه) ویرایش شد.";
             // Logic to update $sample_regions would go here, or DB
        } else { // Add
            $feedback_message = "منطقه \"".htmlspecialchars($region_name)."\" (نمونه) اضافه شد.";
            // Logic to add to $sample_regions would go here, or DB
            // For now, just show message. A real add would update the $sample_regions array for display.
        }
        echo "<div class='alert alert-success mt-3'>".$feedback_message." (این عملیات هنوز به دیتابیس متصل نیست)</div>";
    } else {
        echo "<div class='alert alert-danger mt-3'>نام منطقه نمی‌تواند خالی باشد.</div>";
    }
    // To prevent form resubmission and reflect changes, redirect or clear POST
    // For simplicity here, we'll just show the message.
    // In a real app: header("Location: regions.php?status=success"); exit;
}


?>

<div class="page-header">
    <h1>مدیریت مناطق جذب</h1>
    <p class="page-subtitle">ایجاد، ویرایش و مشاهده مناطق جغرافیایی برای فعالیت‌های جذب.</p>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5 class="mb-0"><?php echo $edit_mode ? 'ویرایش منطقه: ' . htmlspecialchars($region_to_edit['RegionName']) : 'افزودن منطقه جدید'; ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" action="regions.php<?php echo $edit_mode ? '?action=edit&id='.$region_to_edit['RegionID'] : ''; ?>">
                    <?php // generate_csrf_token_input('recruitment_region_form'); ?>
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="region_id" value="<?php echo htmlspecialchars($region_to_edit['RegionID']); ?>">
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="region_name">نام منطقه:</label>
                        <input type="text" class="form-control" id="region_name" name="region_name" value="<?php echo htmlspecialchars($region_to_edit['RegionName']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="description">توضیحات (اختیاری):</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($region_to_edit['Description']); ?></textarea>
                    </div>
                    <?php if ($edit_mode): ?>
                        <button type="submit" name="edit_region" class="btn btn-primary">ذخیره تغییرات</button>
                        <a href="regions.php" class="btn btn-outline-secondary ml-2">انصراف</a>
                    <?php else: ?>
                        <button type="submit" name="add_region" class="btn btn-success">افزودن منطقه</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">لیست مناطق</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">نام منطقه</th>
                                <th scope="col">تعداد افراد (نمونه)</th>
                                <th scope="col">توضیحات</th>
                                <th scope="col">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sample_regions)): ?>
                                <tr><td colspan="5" class="text-center">هنوز منطقه‌ای ثبت نشده است.</td></tr>
                            <?php else: ?>
                                <?php foreach ($sample_regions as $index => $region): ?>
                                <tr>
                                    <th scope="row"><?php echo $region['RegionID']; // Using actual RegionID for consistency ?></th>
                                    <td><?php echo htmlspecialchars($region['RegionName']); ?></td>
                                    <td><?php echo $region['ProspectCount']; ?></td>
                                    <td><?php echo htmlspecialchars(mb_substr($region['Description'], 0, 50) . (mb_strlen($region['Description']) > 50 ? '...' : '')); ?></td>
                                    <td>
                                        <a href="regions.php?action=edit&id=<?php echo $region['RegionID']; ?>" class="btn btn-sm btn-outline-primary" title="ویرایش">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16"><path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/><path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"/></svg>
                                        </a>
                                        <a href="regions.php?action=delete&id=<?php echo $region['RegionID']; ?>" onclick="return confirm('آیا از حذف منطقه  \'<?php echo htmlspecialchars(addslashes($region['RegionName'])); ?>\' مطمئن هستید؟ این عملیات فعلا نمایشی است.');" class="btn btn-sm btn-outline-danger" title="حذف">
                                             <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash3-fill" viewBox="0 0 16 16"><path d="M11 1.5v1h3.5a.5.5 0 0 1 0 1h-.538l-.853 10.66A2 2 0 0 1 11.115 16h-6.23a2 2 0 0 1-1.994-1.84L2.038 3.5H1.5a.5.5 0 0 1 0-1H5v-1A1.5 1.5 0 0 1 6.5 0h3A1.5 1.5 0 0 1 11 1.5Zm-5 0v1h4v-1a.5.5 0 0 0-.5-.5h-3a.5.5 0 0 0-.5.5ZM4.5 5.024l.088-.88A.5.5 0 0 1 5 4h6a.5.5 0 0 1 .412.223l.088.88H4.5Z"/></svg>
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
