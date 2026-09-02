 <?php
// modules/student/notifications.php - صفحة الإشعارات للمتدربة
session_start();
require_once '../../config/config.php';

// =============================================
// التحقق من تسجيل الدخول
// =============================================
if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'student') {
    header('Location: ../auth/login.php?type=student');
    exit();
}

// جلب بيانات المتدربة
$stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();

if(!$student) {
    header('Location: ../auth/logout.php');
    exit();
}

// =============================================
// معالجة تحديد الكل كمقروء
// =============================================
if(isset($_GET['mark_all_read'])) {
    $update = $pdo->prepare("
        UPDATE notifications 
        SET is_read = 1, read_at = NOW() 
        WHERE user_id = ? AND user_type = 'student' AND is_read = 0
    ");
    $update->execute([$_SESSION['user_id']]);
    
    $_SESSION['success'] = '✅ تم تحديد جميع الإشعارات كمقروءة';
    header('Location: notifications.php');
    exit();
}

// =============================================
// معالجة تحديد إشعار كمقروء
// =============================================
if(isset($_GET['mark_read']) && $_GET['mark_read'] > 0) {
    $update = $pdo->prepare("
        UPDATE notifications 
        SET is_read = 1, read_at = NOW() 
        WHERE id = ? AND user_id = ? AND user_type = 'student'
    ");
    $update->execute([$_GET['mark_read'], $_SESSION['user_id']]);
    
    header('Location: notifications.php');
    exit();
}

// =============================================
// معالجة حذف إشعار
// =============================================
if(isset($_GET['delete']) && $_GET['delete'] > 0) {
    $delete = $pdo->prepare("
        DELETE FROM notifications 
        WHERE id = ? AND user_id = ? AND user_type = 'student'
    ");
    $delete->execute([$_GET['delete'], $_SESSION['user_id']]);
    
    $_SESSION['success'] = '✅ تم حذف الإشعار';
    header('Location: notifications.php');
    exit();
}

// =============================================
// معالجة حذف الكل
// =============================================
if(isset($_GET['delete_all'])) {
    $delete = $pdo->prepare("
        DELETE FROM notifications 
        WHERE user_id = ? AND user_type = 'student'
    ");
    $delete->execute([$_SESSION['user_id']]);
    
    $_SESSION['success'] = '✅ تم حذف جميع الإشعارات';
    header('Location: notifications.php');
    exit();
}

// =============================================
// جلب الإشعارات (✅ نسخة مصححة)
// =============================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// ✅ الطريقة الصحيحة: استخدام intval أو تحويل المتغيرات إلى أرقام صحيحة
$stmt = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? AND user_type = 'student' 
    ORDER BY created_at DESC 
    LIMIT " . intval($limit) . " OFFSET " . intval($offset)
);
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll();

// =============================================
// إحصائيات الإشعارات
// =============================================
$total_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND user_type = 'student'");
$total_stmt->execute([$_SESSION['user_id']]);
$total_notifications = $total_stmt->fetchColumn();

$unread_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND user_type = 'student' AND is_read = 0");
$unread_stmt->execute([$_SESSION['user_id']]);
$unread_count = $unread_stmt->fetchColumn();

// حساب عدد الصفحات
$total_pages = ceil($total_notifications / $limit);

$page_title = 'الإشعارات';
require_once '../../includes/student_header.php';

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h4 class="mb-1">الإشعارات</h4>
            <p class="text-muted mb-0">جميع الإشعارات والتنبيهات</p>
        </div>
        <div class="d-flex gap-2">
            <?php if($unread_count > 0): ?>
            <a href="?mark_all_read=1" class="btn-outline-custom" onclick="return confirm('تحديد الكل كمقروء؟')">
                <i class="fas fa-check-double ms-2"></i> تحديد الكل مقروء
            </a>
            <?php endif; ?>
            <?php if($total_notifications > 0): ?>
            <a href="?delete_all=1" class="btn btn-danger" onclick="return confirm('حذف جميع الإشعارات؟')">
                <i class="fas fa-trash ms-2"></i> حذف الكل
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <!-- إحصائيات سريعة -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_notifications; ?></div>
                <div class="stat-label">إجمالي الإشعارات</div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="stat-card">
                <div class="stat-number text-primary"><?php echo $unread_count; ?></div>
                <div class="stat-label">غير مقروء</div>
            </div>
        </div>
    </div>
    
    <!-- قائمة الإشعارات -->
    <?php if(empty($notifications)): ?>
        <div class="card-custom text-center py-5">
            <i class="fas fa-bell-slash fa-4x text-muted mb-3"></i>
            <h5>لا توجد إشعارات</h5>
            <p class="text-muted">ليس لديك أي إشعارات حالياً</p>
        </div>
    <?php else: ?>
        <div class="notifications-list">
            <?php foreach($notifications as $note): ?>
            <div class="notification-item <?php echo !$note['is_read'] ? 'unread' : ''; ?>">
                <div class="notification-icon">
                    <?php if($note['type'] == 'success'): ?>
                        <i class="fas fa-check-circle text-success"></i>
                    <?php elseif($note['type'] == 'error'): ?>
                        <i class="fas fa-exclamation-circle text-danger"></i>
                    <?php elseif($note['type'] == 'warning'): ?>
                        <i class="fas fa-exclamation-triangle text-warning"></i>
                    <?php else: ?>
                        <i class="fas fa-info-circle text-info"></i>
                    <?php endif; ?>
                </div>
                <div class="notification-content">
                    <div class="notification-header">
                        <strong><?php echo htmlspecialchars($note['title']); ?></strong>
                        <small class="text-muted">
                            <?php 
                            $time = strtotime($note['created_at']);
                            if(date('Y-m-d') == date('Y-m-d', $time)) {
                                echo 'اليوم ' . date('H:i', $time);
                            } elseif(date('Y-m-d', strtotime('-1 day')) == date('Y-m-d', $time)) {
                                echo 'أمس ' . date('H:i', $time);
                            } else {
                                echo date('Y-m-d H:i', $time);
                            }
                            ?>
                        </small>
                    </div>
                    <p class="notification-message"><?php echo htmlspecialchars($note['message']); ?></p>
                    
                    <?php if($note['related_link']): ?>
                    <div class="notification-link">
                        <a href="<?php echo $note['related_link']; ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-eye ms-1"></i> عرض التفاصيل
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="notification-actions">
                    <?php if(!$note['is_read']): ?>
                    <a href="?mark_read=<?php echo $note['id']; ?>" class="btn btn-sm btn-outline-success" title="تحديد كمقروء">
                        <i class="fas fa-check"></i>
                    </a>
                    <?php endif; ?>
                    <a href="?delete=<?php echo $note['id']; ?>" class="btn btn-sm btn-outline-danger" title="حذف" 
                       onclick="return confirm('حذف هذا الإشعار؟')">
                        <i class="fas fa-trash"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
        <div class="pagination-container mt-4">
            <nav>
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                    
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.stat-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0,102,51,0.05);
}
.stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--primary);
    line-height: 1;
}
.stat-label {
    color: #6c757d;
    font-size: 1rem;
    margin-top: 5px;
}

.notifications-list {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,102,51,0.05);
}

.notification-item {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    padding: 20px;
    border-bottom: 1px solid #edf2f7;
    transition: all 0.3s ease;
}

.notification-item:last-child {
    border-bottom: none;
}

.notification-item:hover {
    background: #f8f9fa;
}

.notification-item.unread {
    background: #f0f7f0;
    border-right: 4px solid var(--primary);
}

.notification-icon {
    width: 40px;
    font-size: 24px;
    text-align: center;
}

.notification-content {
    flex: 1;
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 5px;
}

.notification-header strong {
    font-size: 1.1rem;
    color: var(--dark);
}

.notification-message {
    color: #6c757d;
    margin-bottom: 10px;
}

.notification-link {
    margin-top: 5px;
}

.notification-actions {
    display: flex;
    gap: 5px;
}

.btn-outline-custom {
    background: transparent;
    color: var(--primary);
    border: 2px solid var(--primary);
    padding: 8px 15px;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
}
.btn-outline-custom:hover {
    background: var(--primary);
    color: white;
}

.pagination .page-link {
    color: var(--primary);
    border: none;
    margin: 0 3px;
    border-radius: 50px;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.pagination .page-item.active .page-link {
    background: var(--gradient);
    color: white;
}

.pagination .page-item.disabled .page-link {
    color: #dee2e6;
    pointer-events: none;
}

.btn-outline-primary {
    border: 2px solid var(--primary);
    color: var(--primary);
}
.btn-outline-primary:hover {
    background: var(--primary);
    color: white;
}
</style>

<?php require_once '../../includes/student_footer.php'; ?>