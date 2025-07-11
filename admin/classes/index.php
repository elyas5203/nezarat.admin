<?php
// admin/classes/index.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth

$csrf_token_classes_index = generate_csrf_token('classes_index_actions');
regenerate_csrf_token('classes_index_actions');

// Filters
$filter_academic_year = sanitize_input($_GET['academic_year'] ?? '');
$filter_grade_level = sanitize_input($_GET['grade_level'] ?? '');
$filter_teacher = sanitize_input($_GET['teacher_id'] ?? '');
$search_class_name = sanitize_input($_GET['search_class'] ?? '');
$filter_is_active = isset($_GET['is_active']) ? sanitize_input($_GET['is_active']) : '1'; // Default to active

$where_clauses_classes = [];
$params_classes = [];
$types_classes = "";

if ($filter_is_active !== '') { // '1' for active, '0' for inactive, '' for all
    $where_clauses_classes[] = "c.IsActive = ?";
    $params_classes[] = (int)$filter_is_active;
    $types_classes .= "i";
}
if (!empty($filter_academic_year)) { $where_clauses_classes[] = "c.AcademicYear = ?"; $params_classes[] = $filter_academic_year; $types_classes .= "s"; }
if (!empty($filter_grade_level)) { $where_clauses_classes[] = "c.GradeLevel LIKE ?"; $params_classes[] = "%{$filter_grade_level}%"; $types_classes .= "s"; }
if (!empty($filter_teacher) && is_numeric($filter_teacher)) { $where_clauses_classes[] = "c.TeacherUserID = ?"; $params_classes[] = (int)$filter_teacher; $types_classes .= "i"; }
if (!empty($search_class_name)) { $where_clauses_classes[] = "c.ClassName LIKE ?"; $params_classes[] = "%{$search_class_name}%"; $types_classes .= "s"; }

$sql_where_cls = "";
if (!empty($where_clauses_classes)) $sql_where_cls = " WHERE " . implode(" AND ", $where_clauses_classes);

$records_per_page_cls = 15;
$page_cls = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page_cls < 1) $page_cls = 1;
$offset_cls = ($page_cls - 1) * $records_per_page_cls;

$total_sql_cls = "SELECT COUNT(c.ClassID) as total FROM Classes c LEFT JOIN Users u ON c.TeacherUserID = u.UserID" . $sql_where_cls;
$stmt_total_cls = $conn->prepare($total_sql_cls);
$total_records_cls = 0; $total_pages_cls = 0;
if ($stmt_total_cls) {
    if (!empty($types_classes) && !empty($params_classes)) $stmt_total_cls->bind_param($types_classes, ...$params_classes);
    $stmt_total_cls->execute();
    $total_records_cls = $stmt_total_cls->get_result()->fetch_assoc()['total'] ?? 0;
    $total_pages_cls = ceil($total_records_cls / $records_per_page_cls);
    if ($page_cls > $total_pages_cls && $total_pages_cls > 0) { $page_cls = $total_pages_cls; $offset_cls = ($page_cls - 1) * $records_per_page_cls; }
    $stmt_total_cls->close();
}

$stmt_classes_list = $conn->prepare("
    SELECT c.ClassID, c.ClassName, c.GradeLevel, c.AcademicYear, c.IsActive,
           CONCAT(u.FirstName, ' ', u.LastName) AS TeacherName, u.UserID AS TeacherUserID
    FROM Classes c LEFT JOIN Users u ON c.TeacherUserID = u.UserID
    " . $sql_where_cls . " ORDER BY c.AcademicYear DESC, c.GradeLevel ASC, c.ClassName ASC LIMIT ? OFFSET ?");
$all_classes_list = [];
if ($stmt_classes_list) {
    $current_params_cls_list = $params_classes; $current_types_cls_list = $types_classes;
    $current_params_cls_list[] = $records_per_page_cls; $current_types_cls_list .= "i";
    $current_params_cls_list[] = $offset_cls; $current_types_cls_list .= "i";
    if (!empty($current_types_cls_list)) $stmt_classes_list->bind_param($current_types_cls_list, ...$current_params_cls_list);
    else $stmt_classes_list->bind_param("ii", $records_per_page_cls, $offset_cls); // If no WHERE clauses

    $stmt_classes_list->execute();
    $result_classes_list = $stmt_classes_list->get_result();
    while ($row_cls = $result_classes_list->fetch_assoc()) $all_classes_list[] = $row_cls;
    $stmt_classes_list->close();
} else { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا بارگذاری کلاس‌ها: ' . $conn->error]; }

$distinct_years_q = $conn->query("SELECT DISTINCT AcademicYear FROM Classes WHERE AcademicYear IS NOT NULL ORDER BY AcademicYear DESC");
$distinct_grades_q = $conn->query("SELECT DISTINCT GradeLevel FROM Classes WHERE GradeLevel IS NOT NULL ORDER BY GradeLevel ASC");
$active_teachers_q = $conn->query("SELECT UserID, FirstName, LastName FROM Users WHERE UserType='teacher' AND IsActive=TRUE ORDER BY LastName, FirstName");
?>
<div class="page-header"><h1>مدیریت کلاس‌ها</h1>
    <div class="page-header-actions">
        <a href="create.php" class="btn btn-primary"><svg class="icon" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg><span>افزودن کلاس</span></a>
        <a href="<?php echo $admin_base_url; ?>/monitoring/index.php" class="btn btn-info"><svg class="icon" viewBox="0 0 24 24"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg><span>نظارت بر کلاس‌ها</span></a></div></div>

<?php if (isset($_SESSION['flash_message'])) { $flash_cls_idx = $_SESSION['flash_message']; echo "<div class='alert alert-{$flash_cls_idx['type']} alert-dismissible fade show'>{$flash_cls_idx['text']}<button type='button' class='close' data-dismiss='alert'>&times;</button></div>"; unset($_SESSION['flash_message']); } ?>
<?php if (isset($_GET['action_status'])): ?><div class="alert <?php echo (strpos($_GET['action_status'], 'success') !== false) ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show"><?php echo htmlspecialchars(urldecode($_GET['message'] ?? '')); ?><button type="button" class="close" data-dismiss="alert">&times;</button></div><?php endif; ?>
<script> /* JS for alert dismissal ... */ </script>

<div class="filter-search-bar card mb-4 shadow-sm"><form method="GET" action="index.php" class="form-inline-flex p-3">
    <div class="form-group"><label for="search_class_input" class="sr-only">نام کلاس</label><input type="text" id="search_class_input" name="search_class" class="form-control" placeholder="جستجوی نام کلاس..." value="<?php echo htmlspecialchars($search_class_name); ?>"></div>
    <div class="form-group"><label for="filter_academic_year_cls" class="sr-only">سال</label><select id="filter_academic_year_cls" name="academic_year" class="form-control custom-select"><option value="">همه سال‌ها</option><?php if($distinct_years_q) while($y = $distinct_years_q->fetch_assoc()):?><option value="<?php echo htmlspecialchars($y['AcademicYear']); ?>" <?php if($filter_academic_year == $y['AcademicYear']) echo 'selected'; ?>><?php echo htmlspecialchars($y['AcademicYear']); ?></option><?php endwhile;?></select></div>
    <div class="form-group"><label for="filter_grade_level_cls" class="sr-only">پایه</label><select id="filter_grade_level_cls" name="grade_level" class="form-control custom-select"><option value="">همه پایه‌ها</option><?php if($distinct_grades_q) while($g = $distinct_grades_q->fetch_assoc()):?><option value="<?php echo htmlspecialchars($g['GradeLevel']); ?>" <?php if($filter_grade_level == $g['GradeLevel']) echo 'selected'; ?>><?php echo htmlspecialchars($g['GradeLevel']); ?></option><?php endwhile;?></select></div>
    <div class="form-group"><label for="filter_teacher_cls" class="sr-only">مدرس</label><select id="filter_teacher_cls" name="teacher_id" class="form-control custom-select"><option value="">همه مدرسین</option><?php if($active_teachers_q) while($t = $active_teachers_q->fetch_assoc()):?><option value="<?php echo $t['UserID']; ?>" <?php if($filter_teacher == $t['UserID']) echo 'selected'; ?>><?php echo htmlspecialchars($t['FirstName'].' '.$t['LastName']); ?></option><?php endwhile;?></select></div>
    <div class="form-group"><label for="filter_is_active_cls" class="sr-only">وضعیت</label><select id="filter_is_active_cls" name="is_active" class="form-control custom-select"><option value="1" <?php if($filter_is_active == '1') echo 'selected';?>>فعال</option><option value="0" <?php if($filter_is_active == '0') echo 'selected';?>>غیرفعال</option><option value="" <?php if($filter_is_active == '') echo 'selected';?>>همه</option></select></div>
    <button type="submit" class="btn btn-info">فیلتر</button><a href="index.php" class="btn btn-outline-secondary ml-2">پاک کردن</a>
</form></div>

<div class="card shadow-sm"><div class="card-header"><span class="card-title-text">لیست کلاس‌ها (<?php echo $total_records_cls; ?> مورد)</span></div>
    <div class="card-body">
        <?php if (!empty($all_classes_list)): ?>
            <div class="table-responsive"><table class="table table-hover table-striped table-sm admin-classes-table">
                <thead><tr><th>#</th><th>نام کلاس</th><th>پایه</th><th>سال تحصیلی</th><th>مدرس</th><th>وضعیت</th><th class="actions-column">عملیات</th></tr></thead><tbody>
                <?php $cls_row_num = $offset_cls + 1; foreach ($all_classes_list as $class_item_list): ?>
                    <tr><td><?php echo $cls_row_num++; ?></td><td><strong><?php echo htmlspecialchars($class_item_list['ClassName']); ?></strong></td><td><?php echo htmlspecialchars($class_item_list['GradeLevel'] ?? '-'); ?></td><td><?php echo htmlspecialchars($class_item_list['AcademicYear'] ?? '-'); ?></td><td><?php if ($class_item_list['TeacherName'] && trim($class_item_list['TeacherName']) !== ''): ?><a href="<?php echo $admin_base_url; ?>/users/edit.php?user_id=<?php echo $class_item_list['TeacherUserID']; ?>"><?php echo htmlspecialchars($class_item_list['TeacherName']); ?></a><?php else: ?><span class="text-muted">-</span><?php endif; ?></td><td><span class="badge badge-<?php echo $class_item_list['IsActive'] ? 'success' : 'secondary'; ?>"><?php echo $class_item_list['IsActive'] ? 'فعال' : 'غیرفعال'; ?></span></td>
                    <td class="actions-cell">
                        <a href="edit.php?class_id=<?php echo $class_item_list['ClassID']; ?>" class="btn btn-sm btn-warning" title="ویرایش"><svg class="icon" width="14" height="14" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></a>
                        <a href="actions/delete_class.php?class_id=<?php echo $class_item_list['ClassID']; ?>&csrf_token=<?php echo $csrf_token_classes_index; ?>" class="btn btn-sm btn-danger" title="حذف" onclick="return confirm('آیا از حذف این کلاس مطمئن هستید؟');"><svg class="icon" width="14" height="14" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></a>
                    </td></tr><?php endforeach; ?></tbody></table></div>
            <?php if ($total_pages_cls > 1): /* Pagination HTML */
                $query_params_cls_page = http_build_query(array_filter(['academic_year' => $filter_academic_year, 'grade_level' => $filter_grade_level, 'teacher_id' => $filter_teacher, 'search_class' => $search_class_name, 'is_active' => $filter_is_active]));?>
            <nav class="mt-4"><ul class="pagination justify-content-center flex-wrap">
                <?php if ($page_cls > 1): ?> <li class="page-item"><a class="page-link" href="?page=1&<?php echo $query_params_cls_page; ?>">اولین</a></li><li class="page-item"><a class="page-link" href="?page=<?php echo $page_cls - 1; ?>&<?php echo $query_params_cls_page; ?>">قبلی</a></li><?php endif; ?>
                <?php $max_links_cls = 5; $start_page_cls = max(1, $page_cls - floor($max_links_cls / 2)); $end_page_cls = min($total_pages_cls, $start_page_cls + $max_links_cls - 1); if ($end_page_cls - $start_page_cls + 1 < $max_links_cls) $start_page_cls = max(1, $end_page_cls - $max_links_cls + 1); ?>
                <?php if ($start_page_cls > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; ?>
                <?php for ($i_cls = $start_page_cls; $i_cls <= $end_page_cls; $i_cls++): ?> <li class="page-item <?php echo ($i_cls == $page_cls) ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i_cls; ?>&<?php echo $query_params_cls_page; ?>"><?php echo $i_cls; ?></a></li><?php endfor; ?>
                <?php if ($end_page_cls < $total_pages_cls) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; ?>
                <?php if ($page_cls < $total_pages_cls): ?> <li class="page-item"><a class="page-link" href="?page=<?php echo $page_cls + 1; ?>&<?php echo $query_params_cls_page; ?>">بعدی</a></li><li class="page-item"><a class="page-link" href="?page=<?php echo $total_pages_cls; ?>&<?php echo $query_params_cls_page; ?>">آخرین</a></li><?php endif; ?>
            </ul></nav><?php endif; ?>
        <?php else: ?><div class="alert alert-info mb-0">کلاسی یافت نشد<?php if(!empty(array_filter([$filter_academic_year, $filter_grade_level, $filter_teacher, $search_class_name, $filter_is_active]))) echo " با این فیلترها"; ?>. <a href="create.php" class="alert-link">کلاس جدید ایجاد کنید</a>.</div><?php endif; ?></div></div>
<style> .admin-classes-table th, .admin-classes-table td { font-size: 0.88rem; vertical-align: middle; } .admin-classes-table .badge {font-size: 0.8rem;} </style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
