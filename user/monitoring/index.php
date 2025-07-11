<?php
// user/monitoring/index.php
require_once __DIR__ . '/../includes/header.php'; // Handles session, db, auth

$teacher_user_id = get_current_user_id();
$is_teacher = (get_current_user_type() === 'teacher');

if (!$teacher_user_id) { // Should be caught by header, but double check
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'برای دسترسی به این بخش، لطفا ابتدا وارد شوید.'];
    header("Location: " . ($user_base_url ?? '/my_site/user') . "/auth/login.php");
    exit;
}

// Fetch classes taught by the current user
$stmt_classes = $conn->prepare("
    SELECT ClassID, ClassName, GradeLevel, AcademicYear, Description
    FROM Classes
    WHERE TeacherUserID = ? AND IsActive = TRUE
    ORDER BY AcademicYear DESC, ClassName ASC
");
$classes = [];
if ($stmt_classes) {
    $stmt_classes->bind_param("i", $teacher_user_id);
    $stmt_classes->execute();
    $result = $stmt_classes->get_result();
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
    $stmt_classes->close();
} else {
    // Log error or set flash message if query prep fails
    error_log("Error preparing statement for fetching classes: " . $conn->error);
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'خطا در بارگذاری لیست کلاس‌ها.'];
}

?>
<div class="page-header">
    <h1>کلاس‌های من (بخش نظارت و خوداظهاری)</h1>
    <?php if ($is_teacher): ?>
    <p class="page-subtitle">لیست کلاس‌هایی که شما مدرس آن هستید. از این بخش می‌توانید فرم‌های خوداظهاری را ثبت و تاریخچه آنها را مشاهده کنید.</p>
    <?php else: ?>
    <p class="page-subtitle text-warning">توجه: این بخش برای کاربران با نقش "مدرس" در نظر گرفته شده است.</p>
    <?php endif; ?>
</div>

<?php
// Display flash messages
if (isset($_SESSION['flash_message'])) {
    $flash = $_SESSION['flash_message'];
    echo "<div class='alert alert-{$flash['type']} alert-dismissible fade show' role='alert'>{$flash['text']}
          <button type='button' class='close' data-dismiss='alert' aria-label='Close' style='background:none; border:none; font-size:1.5rem; position:absolute; top:0; left:0; padding: 0.75rem 1.25rem;'><span aria-hidden='true'>&times;</span></button></div>";
    unset($_SESSION['flash_message']);
     echo "<script>setTimeout(function() { let alert = document.querySelector('.alert-dismissible.show'); if(alert){ if(typeof(bootstrap) !== 'undefined' && bootstrap.Alert && bootstrap.Alert.getInstance(alert)) { bootstrap.Alert.getInstance(alert).close(); } else { alert.style.display = 'none'; }}}, 7000);</script>";
}
?>

<div class="card shadow-sm">
    <div class="card-header">
        <span class="card-title-text">لیست کلاس‌های فعال شما</span>
    </div>
    <div class="card-body">
        <?php if ($is_teacher && !empty($classes)): ?>
            <div class="list-group">
                <?php foreach ($classes as $class): ?>
                    <div class="list-group-item list-group-item-action flex-column align-items-start mb-3 p-3 shadow-hover rounded">
                        <div class="d-flex w-100 justify-content-between align-items-center">
                            <h5 class="mb-1 font-weight-bold text-primary-user"><?php echo htmlspecialchars($class['ClassName']); ?></h5>
                            <small class="text-muted">سال تحصیلی: <?php echo htmlspecialchars($class['AcademicYear'] ?? '-'); ?></small>
                        </div>
                        <?php if (!empty($class['GradeLevel'])): ?>
                            <p class="mb-1 text-secondary"><strong>پایه/سطح:</strong> <?php echo htmlspecialchars($class['GradeLevel']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($class['Description'])): ?>
                            <p class="mb-2 text-muted small"><em><?php echo nl2br(htmlspecialchars(mb_substr($class['Description'],0,150) . (mb_strlen($class['Description']) > 150 ? '...' : ''))); ?></em></p>
                        <?php endif; ?>

                        <div class="mt-2 class-actions">
                            <a href="submit_self_assessment.php?class_id=<?php echo $class['ClassID']; ?>" class="btn btn-success btn-sm mr-2">
                                <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                                <span>ثبت خوداظهاری</span>
                            </a>
                            <a href="assessment_history.php?class_id=<?php echo $class['ClassID']; ?>" class="btn btn-info btn-sm">
                                 <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>
                                <span>تاریخچه خوداظهاری‌ها</span>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif ($is_teacher && empty($classes)): ?>
            <div class="alert alert-info mb-0">در حال حاضر هیچ کلاس فعالی به شما به عنوان مدرس تخصیص داده نشده است. لطفاً با مدیر سیستم تماس بگیرید.</div>
        <?php elseif (!$is_teacher): ?>
             <div class="alert alert-warning mb-0">این بخش تنها برای کاربرانی که نقش "مدرس" دارند، قابل استفاده است.</div>
        <?php else: ?>
            <div class="alert alert-info mb-0">در حال حاضر کلاسی برای نمایش وجود ندارد.</div>
        <?php endif; ?>
    </div>
</div>
<style>
    .list-group-item-action.shadow-hover { transition: box-shadow 0.2s ease-in-out, transform 0.2s ease-in-out; }
    .list-group-item-action.shadow-hover:hover { box-shadow: 0 .5rem 1.2rem rgba(0,0,0,.12)!important; transform: translateY(-3px); }
    .class-actions .btn .icon { margin-left: 6px; vertical-align: text-bottom; }
    .text-primary-user { color: var(--user-panel-primary-color, #17a2b8); }
</style>
<?php
require_once __DIR__ . '/../includes/footer.php';
?>
