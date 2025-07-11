<?php
// admin/recruitment/regions.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth

$csrf_token_regions = generate_csrf_token('recruitment_regions_action');

$errors_region = [];
$success_message_region = '';
$edit_mode_region = false;
$region_to_edit_values = ['RegionID' => null, 'RegionName' => '', 'Description' => '']; // Renamed to avoid conflict

// Handle Form Submission (Create or Update Region)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_region'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'recruitment_regions_action')) {
        $errors_region[] = 'خطای CSRF! درخواست نامعتبر.';
    } else {
        $region_id_post = isset($_POST['region_id']) && is_numeric($_POST['region_id']) ? (int)$_POST['region_id'] : null;
        $region_name_post = sanitize_input($_POST['region_name'] ?? '');
        $description_region_post = sanitize_input($_POST['description_region'] ?? '');

        // For sticky form
        $region_to_edit_values = ['RegionID' => $region_id_post, 'RegionName' => $region_name_post, 'Description' => $description_region_post];
        $edit_mode_region = ($region_id_post !== null);


        if (empty($region_name_post)) $errors_region[] = "نام منطقه الزامی است.";

        $sql_check_rname_post = "SELECT RegionID FROM Regions WHERE RegionName = ?";
        $params_rname_post = [$region_name_post]; $types_rname_post = "s";
        if ($region_id_post) { $sql_check_rname_post .= " AND RegionID != ?"; $params_rname_post[] = $region_id_post; $types_rname_post .= "i"; }
        $stmt_check_rname_post = $conn->prepare($sql_check_rname_post);
        if ($stmt_check_rname_post) {
            $stmt_check_rname_post->bind_param($types_rname_post, ...$params_rname_post); $stmt_check_rname_post->execute();
            if ($stmt_check_rname_post->get_result()->num_rows > 0) $errors_region[] = "منطقه‌ای با این نام قبلاً ثبت شده است.";
            $stmt_check_rname_post->close();
        } else { $errors_region[] = "خطا در بررسی نام منطقه: " . $conn->error; }

        if (empty($errors_region)) {
            if ($region_id_post) { // Update
                $stmt_r_post = $conn->prepare("UPDATE Regions SET RegionName = ?, Description = ? WHERE RegionID = ?");
                if ($stmt_r_post) { $stmt_r_post->bind_param("ssi", $region_name_post, $description_region_post, $region_id_post);
                    if ($stmt_r_post->execute()) { $_SESSION['flash_message'] = ['type' => 'success', 'text' => "منطقه با موفقیت ویرایش شد."]; $edit_mode_region = false; $region_to_edit_values = ['RegionID'=>null, 'RegionName'=>'', 'Description'=>''];} // Clear form
                    else $errors_region[] = "خطا در ویرایش منطقه: " . $stmt_r_post->error; $stmt_r_post->close();
                } else $errors_region[] = "خطا آماده سازی ویرایش: " . $conn->error;
            } else { // Create
                $stmt_r_post = $conn->prepare("INSERT INTO Regions (RegionName, Description) VALUES (?, ?)");
                if ($stmt_r_post) { $stmt_r_post->bind_param("ss", $region_name_post, $description_region_post);
                    if ($stmt_r_post->execute()) { $_SESSION['flash_message'] = ['type' => 'success', 'text' => "منطقه با موفقیت ایجاد شد."]; $region_to_edit_values = ['RegionID'=>null, 'RegionName'=>'', 'Description'=>''];} // Clear form
                    else $errors_region[] = "خطا در ایجاد منطقه: " . $stmt_r_post->error; $stmt_r_post->close();
                } else $errors_region[] = "خطا آماده سازی ایجاد: " . $conn->error;
            }
             if(empty($errors_region)) { // Redirect on success to prevent re-submission and show flash
                regenerate_csrf_token('recruitment_regions_action');
                header("Location: regions.php"); exit;
            }
        }
    }
    $csrf_token_regions = regenerate_csrf_token('recruitment_regions_action');
}


if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id']) && $_SERVER["REQUEST_METHOD"] != "POST") {
    $edit_id_r_get = (int)$_GET['edit_id'];
    $stmt_edit_r_get = $conn->prepare("SELECT RegionID, RegionName, Description FROM Regions WHERE RegionID = ?");
    if ($stmt_edit_r_get) { $stmt_edit_r_get->bind_param("i", $edit_id_r_get); $stmt_edit_r_get->execute(); $result_edit_r_get = $stmt_edit_r_get->get_result();
        if ($data_r_get = $result_edit_r_get->fetch_assoc()) { $region_to_edit_values = $data_r_get; $edit_mode_region = true; }
        else $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "منطقه برای ویرایش یافت نشد."]; $stmt_edit_r_get->close();
    } else $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "خطا بارگذاری منطقه: " . $conn->error];
}
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    if (!isset($_GET['csrf_token']) || !verify_csrf_token($_GET['csrf_token'], 'recruitment_regions_action')) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطای CSRF! عملیات حذف نامعتبر.'];
    } else {
        $delete_id_r_get = (int)$_GET['delete_id'];
        $stmt_check_prospects_del = $conn->prepare("SELECT COUNT(*) as count FROM Prospects WHERE RegionID = ?");
        if($stmt_check_prospects_del){ $stmt_check_prospects_del->bind_param("i", $delete_id_r_get); $stmt_check_prospects_del->execute(); $prospect_count_del = $stmt_check_prospects_del->get_result()->fetch_assoc()['count'] ?? 0; $stmt_check_prospects_del->close();
            if($prospect_count_del > 0){ $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "حذف ناموفق: ".$prospect_count_del." فرد جذب شده به این منطقه مرتبط هستند."]; }
            else {
                $stmt_delete_r_get = $conn->prepare("DELETE FROM Regions WHERE RegionID = ?");
                if ($stmt_delete_r_get) { $stmt_delete_r_get->bind_param("i", $delete_id_r_get);
                    if ($stmt_delete_r_get->execute() && $stmt_delete_r_get->affected_rows > 0) $_SESSION['flash_message'] = ['type' => 'success', 'text' => "منطقه با موفقیت حذف شد."];
                    else $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "خطا در حذف منطقه: " . $stmt_delete_r_get->error]; $stmt_delete_r_get->close();
                } else $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "خطا آماده سازی حذف: " . $conn->error];
            }
        } else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "خطا بررسی وابستگی منطقه: " . $conn->error]; }
    }
    $csrf_token_regions = regenerate_csrf_token('recruitment_regions_action');
    header("Location: regions.php"); exit;
}

$regions_list_q_main = $conn->query("SELECT r.RegionID, r.RegionName, r.Description, COUNT(p.ProspectID) as ProspectCount FROM Regions r LEFT JOIN Prospects p ON r.RegionID = p.RegionID GROUP BY r.RegionID, r.RegionName, r.Description ORDER BY r.RegionName");
?>
<div class="page-header"><h1>مدیریت مناطق جذب</h1>
    <div class="page-header-actions"><a href="prospects.php" class="btn btn-info">
         <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="17.5" y1="8" x2="17.5" y2="14"></line><line x1="20.5" y1="11" x2="14.5" y2="11"></line></svg>
        <span>مدیریت افراد جذب شده</span></a></div></div>

<?php if (isset($_SESSION['flash_message'])) { $flash_reg = $_SESSION['flash_message']; echo "<div class='alert alert-{$flash_reg['type']} alert-dismissible fade show'>{$flash_reg['text']}<button type='button' class='close' data-dismiss='alert'>&times;</button></div>"; unset($_SESSION['flash_message']); echo "<script>setTimeout(function(){let alert = document.querySelector('.alert-dismissible.show'); if(alert){if(typeof(bootstrap)!=='undefined' && bootstrap.Alert && bootstrap.Alert.getInstance(alert)){bootstrap.Alert.getInstance(alert).close();}else{alert.style.display='none';}}},7000);</script>";} ?>
<?php if (!empty($errors_region)): ?> <div class="alert alert-danger"><ul><?php foreach ($errors_region as $err_r_item): ?><li><?php echo htmlspecialchars($err_r_item); ?></li><?php endforeach; ?></ul></div> <?php endif; ?>
<?php if ($success_message_region && empty($errors_region)): ?> <div class="alert alert-success alert-dismissible fade show"><?php echo htmlspecialchars($success_message_region); ?><button type="button" class="close" data-dismiss="alert">&times;</button></div> <?php endif; ?>

<div class="row"><div class="col-lg-5 mb-4"><div class="card shadow-sm">
    <div class="card-header"><span class="card-title-text"><?php echo $edit_mode_region ? 'ویرایش منطقه: ' . htmlspecialchars($region_to_edit_values['RegionName']) : 'افزودن منطقه جدید'; ?></span></div>
    <div class="card-body">
    <form action="regions.php<?php if($edit_mode_region && $region_to_edit_values['RegionID']) echo '?edit_id='.$region_to_edit_values['RegionID']; ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_regions; ?>">
        <?php if ($edit_mode_region && $region_to_edit_values['RegionID']): ?><input type="hidden" name="region_id" value="<?php echo $region_to_edit_values['RegionID']; ?>"><?php endif; ?>
        <div class="form-group"><label for="region_name">نام منطقه <span class="text-danger">*</span></label><input type="text" class="form-control <?php echo (!empty($errors_region) && isset($_POST['region_name'])) ? 'is-invalid' : ''; ?>" id="region_name" name="region_name" value="<?php echo htmlspecialchars($region_to_edit_values['RegionName']); ?>" required></div>
        <div class="form-group"><label for="description_region">توضیحات</label><textarea class="form-control" id="description_region" name="description_region" rows="2"><?php echo htmlspecialchars($region_to_edit_values['Description']); ?></textarea></div>
        <div class="form-actions"><button type="submit" name="submit_region" class="btn btn-primary"><?php echo $edit_mode_region ? 'ذخیره تغییرات' : 'افزودن منطقه'; ?></button><?php if ($edit_mode_region): ?><a href="regions.php" class="btn btn-outline-secondary">لغو</a><?php endif; ?></div>
    </form></div></div></div>
    <div class="col-lg-7"><div class="card shadow-sm"><div class="card-header"><span class="card-title-text">لیست مناطق</span></div><div class="card-body">
    <?php if ($regions_list_q_main && $regions_list_q_main->num_rows > 0): ?><div class="table-responsive"><table class="table table-sm table-striped table-hover">
        <thead><tr><th>#</th><th>نام منطقه</th><th>توضیحات</th><th class="text-center">تعداد افراد</th><th>عملیات</th></tr></thead><tbody>
        <?php $r_row_idx = 1; while ($r_item = $regions_list_q_main->fetch_assoc()): ?><tr>
            <td><?php echo $r_row_idx++; ?></td><td><strong><?php echo htmlspecialchars($r_item['RegionName']); ?></strong></td>
            <td class="small"><?php echo htmlspecialchars(mb_substr($r_item['Description'] ?? '', 0, 70) . (mb_strlen($r_item['Description'] ?? '') > 70 ? '...' : '')); ?></td>
            <td class="text-center"><span class="badge badge-info p-2"><?php echo $r_item['ProspectCount']; ?></span></td>
            <td class="actions-cell">
                <a href="regions.php?edit_id=<?php echo $r_item['RegionID']; ?>" class="btn btn-sm btn-warning" title="ویرایش"><svg class="icon" width="14" height="14" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></a>
                <a href="regions.php?delete_id=<?php echo $r_item['RegionID']; ?>&csrf_token=<?php echo $csrf_token_regions; ?>" class="btn btn-sm btn-danger <?php if($r_item['ProspectCount']>0) echo 'disabled';?>" title="<?php if($r_item['ProspectCount']>0) echo 'ابتدا افراد جذب شده این منطقه را حذف یا به منطقه دیگری منتقل کنید'; else echo 'حذف';?>" onclick="if(<?php echo $r_item['ProspectCount'];?> > 0) { alert(this.title); return false; } return confirm('آیا از حذف این منطقه مطمئن هستید؟');"><svg class="icon" width="14" height="14" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></a>
            </td></tr><?php endwhile; ?></tbody></table></div>
    <?php else: ?><p class="text-muted text-center mt-3">هنوز منطقه‌ای تعریف نشده است.</p><?php endif; if($regions_list_q_main) $regions_list_q_main->close(); ?>
    </div></div></div></div>
<script> /* Alert dismissal JS ... */ </script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
