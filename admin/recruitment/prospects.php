<?php
// admin/recruitment/prospects.php
require_once __DIR__ . '/../includes/header.php';

$csrf_token_prospects = generate_csrf_token('recruitment_prospects_action');

$errors_prospect = [];
$success_message_prospect = ''; // Not directly used due to redirect, use flash messages
$edit_mode_prospect = false;
$prospect_to_edit_values = [
    'ProspectID' => null, 'FirstName' => '', 'LastName' => '', 'ParentName' => '',
    'PhoneNumber' => '', 'RegionID' => '', 'ReferrerName' => '',
    'SourceEvent' => '', 'Notes' => ''
];
$prospect_attendance_edit = [];

$regions_q_prospect = $conn->query("SELECT RegionID, RegionName FROM Regions ORDER BY RegionName");
$available_regions_prospect = [];
if ($regions_q_prospect) { while($r_p = $regions_q_prospect->fetch_assoc()) $available_regions_prospect[$r_p['RegionID']] = $r_p['RegionName']; $regions_q_prospect->close(); }

$source_event_options = ['غدیر', 'نیمه شعبان', 'محرم', 'اردو تابستانه', 'معرفی دوستان', 'تماس تلفنی', 'شبکه مجازی', 'وبسایت', 'سایر'];
// For attendance tracking, it's better if events are managed in a table or config with specific dates.
// For now, using a simple list for demonstration in the form.
$defined_events_for_attendance = [
    'غدیر ۱۴۰۳' => '1403-04-05', // Example date, admin should be able to define these events
    'نیمه شعبان ۱۴۰۳' => '1403-12-07',
    'اردو تابستان ۱۴۰۳' => '1403-05-15',
    'غدیر ۱۴۰۲' => '1402-04-16',
    'نیمه شعبان ۱۴۰۲' => '1402-12-18'
];


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_prospect'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '', 'recruitment_prospects_action')) {
        $errors_prospect[] = 'خطای CSRF!';
    } else {
        $prospect_id_post = isset($_POST['prospect_id']) && is_numeric($_POST['prospect_id']) ? (int)$_POST['prospect_id'] : null;

        $prospect_to_edit_values['FirstName'] = sanitize_input($_POST['FirstName'] ?? '');
        $prospect_to_edit_values['LastName'] = sanitize_input($_POST['LastName'] ?? '');
        $prospect_to_edit_values['ParentName'] = sanitize_input($_POST['ParentName'] ?? '');
        $prospect_to_edit_values['PhoneNumber'] = sanitize_input($_POST['PhoneNumber'] ?? '');
        $prospect_to_edit_values['RegionID'] = !empty($_POST['RegionID']) ? (int)$_POST['RegionID'] : null;
        $prospect_to_edit_values['ReferrerName'] = sanitize_input($_POST['ReferrerName'] ?? '');
        $prospect_to_edit_values['SourceEvent'] = sanitize_input($_POST['SourceEvent'] ?? '');
        $prospect_to_edit_values['Notes'] = sanitize_input($_POST['Notes'] ?? '');
        if($prospect_id_post) $prospect_to_edit_values['ProspectID'] = $prospect_id_post;
        $edit_mode_prospect = ($prospect_id_post !== null);

        $posted_attendance = $_POST['attendance'] ?? [];

        if (empty($prospect_to_edit_values['FirstName'])) $errors_prospect[] = "نام فرد الزامی است.";
        if (empty($prospect_to_edit_values['PhoneNumber'])) $errors_prospect[] = "شماره تماس الزامی است.";
        elseif (!preg_match('/^09[0-9]{9}$/', preg_replace('/[^0-9]/', '', $prospect_to_edit_values['PhoneNumber']))) {
             $errors_prospect[] = "فرمت شماره تماس نامعتبر (مثال: 09123456789).";
        } else {
            $sql_check_phone = "SELECT ProspectID FROM Prospects WHERE PhoneNumber = ?";
            $params_phone = [preg_replace('/[^0-9]/', '', $prospect_to_edit_values['PhoneNumber'])]; $types_phone = "s";
            if($prospect_id_post) { $sql_check_phone .= " AND ProspectID != ?"; $params_phone[] = $prospect_id_post; $types_phone .= "i"; }
            $stmt_check_phone = $conn->prepare($sql_check_phone);
            if($stmt_check_phone){ $stmt_check_phone->bind_param($types_phone, ...$params_phone); $stmt_check_phone->execute();
                if($stmt_check_phone->get_result()->num_rows > 0) $errors_prospect[] = "این شماره تماس قبلاً برای فرد دیگری ثبت شده.";
                $stmt_check_phone->close();
            }
        }
        if ($prospect_to_edit_values['RegionID'] !== null && !isset($available_regions_prospect[$prospect_to_edit_values['RegionID']])) $errors_prospect[] = "منطقه نامعتبر.";
        if (!empty($prospect_to_edit_values['SourceEvent']) && !in_array($prospect_to_edit_values['SourceEvent'], $source_event_options)) $errors_prospect[] = "منبع جذب نامعتبر.";

        if (empty($errors_prospect)) {
            $conn->begin_transaction();
            try {
                if ($prospect_id_post) {
                    $stmt_p = $conn->prepare("UPDATE Prospects SET FirstName=?, LastName=?, ParentName=?, PhoneNumber=?, RegionID=?, ReferrerName=?, SourceEvent=?, Notes=?, UpdatedAt=NOW() WHERE ProspectID=?");
                    if (!$stmt_p) throw new Exception("آماده سازی ویرایش: ".$conn->error);
                    $stmt_p->bind_param("ssssisssi", $prospect_to_edit_values['FirstName'], $prospect_to_edit_values['LastName'], $prospect_to_edit_values['ParentName'], $prospect_to_edit_values['PhoneNumber'], $prospect_to_edit_values['RegionID'], $prospect_to_edit_values['ReferrerName'], $prospect_to_edit_values['SourceEvent'], $prospect_to_edit_values['Notes'], $prospect_id_post);
                } else {
                    $stmt_p = $conn->prepare("INSERT INTO Prospects (FirstName, LastName, ParentName, PhoneNumber, RegionID, ReferrerName, SourceEvent, Notes, CreatedAt, UpdatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                    if (!$stmt_p) throw new Exception("آماده سازی ایجاد: ".$conn->error);
                    $stmt_p->bind_param("ssssisss", $prospect_to_edit_values['FirstName'], $prospect_to_edit_values['LastName'], $prospect_to_edit_values['ParentName'], $prospect_to_edit_values['PhoneNumber'], $prospect_to_edit_values['RegionID'], $prospect_to_edit_values['ReferrerName'], $prospect_to_edit_values['SourceEvent'], $prospect_to_edit_values['Notes']);
                }
                if (!$stmt_p->execute()) throw new Exception("عملیات پایگاه داده: ".$stmt_p->error);
                $current_prospect_id = $prospect_id_post ?: $stmt_p->insert_id;
                $stmt_p->close();

                $stmt_delete_attendance = $conn->prepare("DELETE FROM ProspectAttendance WHERE ProspectID = ?");
                if(!$stmt_delete_attendance) throw new Exception("آماده سازی حذف حضور: ".$conn->error);
                $stmt_delete_attendance->bind_param("i", $current_prospect_id);
                if(!$stmt_delete_attendance->execute()) throw new Exception("خطا حذف حضور: ".$stmt_delete_attendance->error);
                $stmt_delete_attendance->close();

                if(!empty($posted_attendance)){
                    $stmt_insert_attendance = $conn->prepare("INSERT INTO ProspectAttendance (ProspectID, EventName, EventDate) VALUES (?, ?, ?)");
                    if(!$stmt_insert_attendance) throw new Exception("آماده سازی ثبت حضور: ".$conn->error);
                    foreach($posted_attendance as $event_name_key => $event_date_val){
                        if(!empty($event_date_val)){
                            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date_val)) continue; // Skip invalid date
                            $stmt_insert_attendance->bind_param("iss", $current_prospect_id, $event_name_key, $event_date_val);
                            if(!$stmt_insert_attendance->execute()) throw new Exception("خطا ثبت حضور برای ".$event_name_key.": ".$stmt_insert_attendance->error);
                        }
                    }
                    $stmt_insert_attendance->close();
                }

                $conn->commit();
                $_SESSION['flash_message'] = ['type' => 'success', 'text' => $prospect_id_post ? "اطلاعات فرد ویرایش شد." : "فرد جدید ثبت شد."];
                regenerate_csrf_token('recruitment_prospects_action');
                header("Location: prospects.php" . ($prospect_id_post ? "?edit_id=".$prospect_id_post : "")); exit;
            } catch (Exception $e) { $conn->rollback(); $errors_prospect[] = $e->getMessage(); }
        }
    }
    $csrf_token_prospects = regenerate_csrf_token('recruitment_prospects_action');
}

if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id']) && $_SERVER["REQUEST_METHOD"] != "POST") {
    $edit_id_p_get = (int)$_GET['edit_id'];
    $stmt_edit_p_get = $conn->prepare("SELECT * FROM Prospects WHERE ProspectID = ?");
    if ($stmt_edit_p_get) { $stmt_edit_p_get->bind_param("i", $edit_id_p_get); $stmt_edit_p_get->execute(); $result_edit_p_get = $stmt_edit_p_get->get_result();
        if ($data_p_get = $result_edit_p_get->fetch_assoc()) { $prospect_to_edit_values = $data_p_get; $edit_mode_prospect = true;
            $stmt_get_attendance_edit = $conn->prepare("SELECT EventName, EventDate FROM ProspectAttendance WHERE ProspectID = ?");
            if($stmt_get_attendance_edit){ $stmt_get_attendance_edit->bind_param("i", $edit_id_p_get); $stmt_get_attendance_edit->execute(); $res_att_edit = $stmt_get_attendance_edit->get_result();
                while($att_row_edit = $res_att_edit->fetch_assoc()) $prospect_attendance_edit[$att_row_edit['EventName']] = $att_row_edit['EventDate'];
                $stmt_get_attendance_edit->close(); }
        } else $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "فرد یافت نشد."]; $stmt_edit_p_get->close();
    } else $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "خطا بارگذاری: " . $conn->error];
}
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    if (!isset($_GET['csrf_token']) || !verify_csrf_token($_GET['csrf_token'], 'recruitment_prospects_action')) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطای CSRF!'];
    } else {
        $delete_id_p_get = (int)$_GET['delete_id'];
        $conn->begin_transaction();
        try {
            $stmt_del_att = $conn->prepare("DELETE FROM ProspectAttendance WHERE ProspectID = ?");
            if(!$stmt_del_att) throw new Exception("خطا آماده سازی حذف حضورها: ".$conn->error);
            $stmt_del_att->bind_param("i", $delete_id_p_get);
            if(!$stmt_del_att->execute()) throw new Exception("خطا حذف حضورها: ".$stmt_del_att->error);
            $stmt_del_att->close();

            $stmt_del_p = $conn->prepare("DELETE FROM Prospects WHERE ProspectID = ?");
            if(!$stmt_del_p) throw new Exception("خطا آماده سازی حذف فرد: ".$conn->error);
            $stmt_del_p->bind_param("i", $delete_id_p_get);
            if($stmt_del_p->execute() && $stmt_del_p->affected_rows > 0) {
                $conn->commit(); $_SESSION['flash_message'] = ['type' => 'success', 'text' => "فرد و سوابق حضور او حذف شدند."];
            } else { throw new Exception("خطا حذف فرد یا فرد یافت نشد: ".$stmt_del_p->error); }
            $stmt_del_p->close();
        } catch (Exception $e_del_p) { $conn->rollback(); $_SESSION['flash_message'] = ['type' => 'danger', 'text' => $e_del_p->getMessage()];}
    }
    $csrf_token_prospects = regenerate_csrf_token('recruitment_prospects_action');
    header("Location: prospects.php"); exit;
}

// Filters for prospect list
$filter_region_prospect = isset($_GET['filter_region']) ? (int)$_GET['filter_region'] : '';
$filter_source_prospect = isset($_GET['filter_source']) ? sanitize_input($_GET['filter_source']) : '';
$search_name_prospect = isset($_GET['search_name']) ? sanitize_input($_GET['search_name']) : '';

$where_prospects = []; $params_prospects = []; $types_prospects = "";
if(!empty($filter_region_prospect)){ $where_prospects[]="p.RegionID = ?"; $params_prospects[]=$filter_region_prospect; $types_prospects.="i"; }
if(!empty($filter_source_prospect)){ $where_prospects[]="p.SourceEvent = ?"; $params_prospects[]=$filter_source_prospect; $types_prospects.="s"; }
if(!empty($search_name_prospect)){ $where_prospects[]="(p.FirstName LIKE ? OR p.LastName LIKE ? OR CONCAT(p.FirstName, ' ', p.LastName) LIKE ? OR p.PhoneNumber LIKE ?)"; $search_name_like = "%{$search_name_prospect}%"; array_push($params_prospects, $search_name_like, $search_name_like, $search_name_like, $search_name_like); $types_prospects.="ssss"; }
$sql_where_prospects = !empty($where_prospects) ? " WHERE ".implode(" AND ", $where_prospects) : "";

$prospects_list_q_main = $conn->prepare("SELECT p.*, r.RegionName, (SELECT COUNT(*) FROM ProspectAttendance pa WHERE pa.ProspectID = p.ProspectID) as AttendanceCount FROM Prospects p LEFT JOIN Regions r ON p.RegionID = r.RegionID $sql_where_prospects ORDER BY p.CreatedAt DESC LIMIT 50");
if($prospects_list_q_main){ if(!empty($types_prospects)) $prospects_list_q_main->bind_param($types_prospects, ...$params_prospects); $prospects_list_q_main->execute(); $res_pl = $prospects_list_q_main->get_result(); }
else { $res_pl = false; $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا بارگذاری لیست افراد.']; }

?>
<div class="page-header"><h1>مدیریت افراد جذب شده</h1><div class="page-header-actions"><a href="regions.php" class="btn btn-secondary">مدیریت مناطق</a></div></div>

<?php if (isset($_SESSION['flash_message'])) { /* ... Flash ... */ } ?>
<?php if (!empty($errors_prospect)): ?> <div class="alert alert-danger"><ul><?php foreach ($errors_prospect as $err_p_item): ?><li><?php echo htmlspecialchars($err_p_item); ?></li><?php endforeach; ?></ul></div> <?php endif; ?>

<div class="row"><div class="col-lg-5 mb-4"><div class="card shadow-sm">
    <div class="card-header"><span class="card-title-text"><?php echo $edit_mode_prospect ? 'ویرایش: ' . htmlspecialchars($prospect_to_edit_values['FirstName'].' '.$prospect_to_edit_values['LastName']) : 'افزودن فرد جدید'; ?></span></div>
    <div class="card-body">
    <form action="prospects.php<?php if($edit_mode_prospect && $prospect_to_edit_values['ProspectID']) echo '?edit_id='.$prospect_to_edit_values['ProspectID']; ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_prospects; ?>">
        <?php if ($edit_mode_prospect && $prospect_to_edit_values['ProspectID']): ?><input type="hidden" name="prospect_id" value="<?php echo $prospect_to_edit_values['ProspectID']; ?>"><?php endif; ?>
        <div class="form-row"><div class="form-group col-md-6"><label for="FirstNameP">نام <span class="text-danger">*</span></label><input type="text" class="form-control" id="FirstNameP" name="FirstName" value="<?php echo htmlspecialchars($prospect_to_edit_values['FirstName']); ?>" required></div><div class="form-group col-md-6"><label for="LastNameP">نام خانوادگی</label><input type="text" class="form-control" id="LastNameP" name="LastName" value="<?php echo htmlspecialchars($prospect_to_edit_values['LastName']); ?>"></div></div>
        <div class="form-group"><label for="ParentNameP">نام والدین</label><input type="text" class="form-control" id="ParentNameP" name="ParentName" value="<?php echo htmlspecialchars($prospect_to_edit_values['ParentName']); ?>"></div>
        <div class="form-group"><label for="PhoneNumberP">شماره تماس <span class="text-danger">*</span></label><input type="text" class="form-control" id="PhoneNumberP" name="PhoneNumber" value="<?php echo htmlspecialchars($prospect_to_edit_values['PhoneNumber']); ?>" required placeholder="09..."></div>
        <div class="form-row"><div class="form-group col-md-6"><label for="RegionIDP">منطقه</label><select name="RegionID" id="RegionIDP" class="form-control custom-select"><option value="">-- انتخاب --</option><?php foreach($available_regions_prospect as $rid_p => $rname_p):?><option value="<?php echo $rid_p;?>" <?php if($prospect_to_edit_values['RegionID'] == $rid_p) echo 'selected';?>><?php echo htmlspecialchars($rname_p);?></option><?php endforeach;?></select></div><div class="form-group col-md-6"><label for="ReferrerNameP">معرف</label><input type="text" class="form-control" id="ReferrerNameP" name="ReferrerName" value="<?php echo htmlspecialchars($prospect_to_edit_values['ReferrerName']); ?>"></div></div>
        <div class="form-group"><label for="SourceEventP">منبع جذب</label><select name="SourceEvent" id="SourceEventP" class="form-control custom-select"><option value="">-- انتخاب --</option><?php foreach($source_event_options as $se_opt_p):?><option value="<?php echo $se_opt_p;?>" <?php if($prospect_to_edit_values['SourceEvent'] == $se_opt_p) echo 'selected';?>><?php echo htmlspecialchars($se_opt_p);?></option><?php endforeach;?></select></div>
        <?php if($edit_mode_prospect): ?>
        <fieldset class="border p-3 mt-3 rounded"><legend class="w-auto px-2 small font-weight-bold bg-light">حضور در مراسم‌ها</legend>
            <?php foreach($defined_events_for_attendance as $event_name_form => $default_date_form): $attended_date_form = $prospect_attendance_edit[$event_name_form] ?? '';?>
            <div class="form-group row mb-2"><label for="attendance_<?php echo str_replace(' ', '_', $event_name_form);?>" class="col-sm-5 col-form-label col-form-label-sm"><?php echo htmlspecialchars($event_name_form); ?>:</label><div class="col-sm-7"><input type="text" class="form-control form-control-sm persian-date-picker" id="attendance_<?php echo str_replace(' ', '_', $event_name_form);?>" name="attendance[<?php echo htmlspecialchars($event_name_form); ?>]" value="<?php echo htmlspecialchars($attended_date_form); ?>" placeholder="تاریخ حضور YYYY-MM-DD"></div></div><?php endforeach; ?>
        </fieldset><?php endif; ?>
        <div class="form-group mt-3"><label for="NotesP">یادداشت</label><textarea class="form-control" name="Notes" id="NotesP" rows="2"><?php echo htmlspecialchars($prospect_to_edit_values['Notes']); ?></textarea></div>
        <div class="form-actions"><button type="submit" name="submit_prospect" class="btn btn-primary"><?php echo $edit_mode_prospect ? 'ذخیره' : 'ثبت'; ?></button><?php if ($edit_mode_prospect): ?><a href="prospects.php" class="btn btn-outline-secondary">لغو</a><?php endif; ?></div>
    </form></div></div></div>
    <div class="col-lg-7"><div class="card shadow-sm"><div class="card-header"><span class="card-title-text">لیست افراد (۵۰ اخیر)</span></div><div class="card-body">
    <?php if ($res_pl && $res_pl->num_rows > 0): ?><div class="table-responsive"><table class="table table-sm table-striped table-hover">
        <thead><tr><th>#</th><th>نام</th><th>تماس</th><th>منطقه</th><th>منبع</th><th>حضورها</th><th>ثبت</th><th>عملیات</th></tr></thead><tbody>
        <?php $p_row_idx = 1; while ($p_item = $res_pl->fetch_assoc()): ?><tr>
            <td><?php echo $p_row_idx++; ?></td><td><strong><?php echo htmlspecialchars($p_item['FirstName'].' '.$p_item['LastName']); ?></strong><small class="d-block text-muted"><?php echo htmlspecialchars($p_item['ParentName'] ?? '-');?></small></td>
            <td><?php echo htmlspecialchars($p_item['PhoneNumber']); ?></td><td><?php echo htmlspecialchars($p_item['RegionName'] ?? '-'); ?></td>
            <td><?php echo htmlspecialchars($p_item['SourceEvent'] ?? '-'); ?></td>
            <td class="text-center"><span class="badge badge-secondary"><?php echo $p_item['AttendanceCount']; ?></span></td>
            <td class="small"><?php echo to_jalali($p_item['CreatedAt'], 'yy/MM/dd'); ?></td>
            <td class="actions-cell"><a href="prospects.php?edit_id=<?php echo $p_item['ProspectID']; ?>" class="btn btn-sm btn-warning" title="ویرایش"><svg class="icon" width="14" height="14" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></a>
            <a href="prospects.php?delete_id=<?php echo $p_item['ProspectID']; ?>&csrf_token=<?php echo $csrf_token_prospects; ?>" class="btn btn-sm btn-danger" title="حذف" onclick="return confirm('آیا از حذف این فرد مطمئن هستید؟ سوابق حضور او نیز حذف خواهد شد.');"><svg class="icon" width="14" height="14" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></a>
            </td></tr><?php endwhile; ?></tbody></table></div>
    <?php else: ?><p class="text-muted text-center">هنوز فردی ثبت نشده.</p><?php endif; if($prospects_list_q_main) $prospects_list_q_main->close(); ?>
    </div></div></div></div>
<link rel="stylesheet" href="https://unpkg.com/persian-datepicker@latest/dist/css/persian-datepicker.min.css"/>
<script src="https://unpkg.com/persian-datepicker@latest/dist/js/persian-datepicker.min.js"></script>
<script> document.addEventListener('DOMContentLoaded', function() { document.querySelectorAll(".persian-date-picker").forEach(function(el){ new persianDatepicker(el, { format: 'YYYY-MM-DD', autoClose: true, observer: true, calendar:{ persian: { locale: 'fa' } } });});});</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
