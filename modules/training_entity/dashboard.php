 <?php
// modules/training_entity/dashboard.php - لوحة التحكم الرئيسية للجهة
session_start();
require_once '../../config/config.php';

// =============================================
// التحقق من تسجيل الدخول
// =============================================
if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'training_entity') {
    header('Location: ../auth/login.php?type=training_entity');
    exit();
}

// جلب بيانات الجهة
$stmt = $pdo->prepare("SELECT * FROM training_entities WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$entity = $stmt->fetch();

if(!$entity) {
    header('Location: ../auth/logout.php');
    exit();
}

// =============================================
// جلب الإحصائيات
// =============================================

// إحصائيات المقاعد
$stmt = $pdo->prepare("SELECT COUNT(*) FROM training_seats WHERE entity_id = ?");
$stmt->execute([$entity['id']]);
$stats['total_seats'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM training_seats WHERE entity_id = ? AND status = 'available'");
$stmt->execute([$entity['id']]);
$stats['active_seats'] = $stmt->fetchColumn();

// إحصائيات الطلبات
$stmt = $pdo->prepare("SELECT COUNT(*) FROM applications a JOIN training_seats ts ON a.seat_id = ts.id WHERE ts.entity_id = ?");
$stmt->execute([$entity['id']]);
$stats['total_applications'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM applications a JOIN training_seats ts ON a.seat_id = ts.id WHERE ts.entity_id = ? AND a.status = 'pending'");
$stmt->execute([$entity['id']]);
$stats['pending_applications'] = $stmt->fetchColumn();

// إحصائيات المتدربات
$stmt = $pdo->prepare("SELECT COUNT(*) FROM applications a JOIN training_seats ts ON a.seat_id = ts.id WHERE ts.entity_id = ? AND a.status IN ('approved', 'accepted_by_entity')");
$stmt->execute([$entity['id']]);
$stats['active_trainees'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM evaluations e JOIN applications a ON e.application_id = a.id JOIN training_seats ts ON a.seat_id = ts.id WHERE ts.entity_id = ? AND e.training_completed = 1");
$stmt->execute([$entity['id']]);
$stats['completed_trainees'] = $stmt->fetchColumn();

// إحصائيات المقابلات
$stmt = $pdo->prepare("SELECT COUNT(*) FROM interviews i JOIN applications a ON i.application_id = a.id JOIN training_seats ts ON a.seat_id = ts.id WHERE ts.entity_id = ? AND i.status = 'scheduled'");
$stmt->execute([$entity['id']]);
$stats['upcoming_interviews'] = $stmt->fetchColumn();

// =============================================
// جلب آخر الطلبات
// =============================================
$stmt = $pdo->prepare("
    SELECT a.*, s.full_name_ar, s.student_id, s.gpa, ts.title_ar
    FROM applications a
    JOIN students s ON a.student_id = s.id
    JOIN training_seats ts ON a.seat_id = ts.id
    WHERE ts.entity_id = ?
    ORDER BY a.created_at DESC
    LIMIT 5
");
$stmt->execute([$entity['id']]);
$recent_applications = $stmt->fetchAll();

// =============================================
// جلب آخر المقاعد المضافة
// =============================================
$stmt = $pdo->prepare("
    SELECT * FROM training_seats 
    WHERE entity_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$entity['id']]);
$recent_seats = $stmt->fetchAll();

// =============================================
// جلب آخر المقابلات
// =============================================
$stmt = $pdo->prepare("
    SELECT i.*, s.full_name_ar, ts.title_ar
    FROM interviews i
    JOIN applications a ON i.application_id = a.id
    JOIN students s ON a.student_id = s.id
    JOIN training_seats ts ON a.seat_id = ts.id
    WHERE ts.entity_id = ? AND i.status = 'scheduled'
    ORDER BY i.interview_date ASC
    LIMIT 5
");
$stmt->execute([$entity['id']]);
$upcoming_interviews = $stmt->fetchAll();

$page_title = 'لوحة التحكم';
require_once '../../includes/entity_header.php';
?>

<div class="content">
    <!-- ========================================= -->
    <!-- الترحيب -->
    <!-- ========================================= -->
    <div class="welcome-card">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2>مرحباً، <?php echo htmlspecialchars($entity['entity_name_ar']); ?> 👋</h2>
                <p class="mb-2">
                    <i class="fas fa-calendar-alt ms-2"></i> 
                    <?php 
                    $days = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
                    echo $days[date('w')] . ' ' . date('d F Y'); 
                    ?>
                </p>
                <p class="mb-0 text-white-50">
                    <i class="fas fa-clock ms-2"></i> <?php echo date('h:i A'); ?>
                </p>
            </div>
            <div class="col-md-4 text-md-left mt-3 mt-md-0">
                <div class="bg-white bg-opacity-25 p-3 rounded">
                    <div class="d-flex gap-2">
                        <span class="badge bg-white text-dark p-2">
                            <i class="fas fa-chair ms-1"></i> <?php echo $stats['active_seats']; ?>/<?php echo $stats['total_seats']; ?>
                        </span>
                        <span class="badge bg-white text-dark p-2">
                            <i class="fas fa-users ms-1"></i> <?php echo $stats['active_trainees']; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================= -->
    <!-- إحصائيات سريعة -->
    <!-- ========================================= -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card" onclick="window.location.href='seats.php'">
                <div class="stat-icon"><i class="fas fa-chair"></i></div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['total_seats']; ?></div>
                    <div class="stat-label">إجمالي المقاعد</div>
                    <small class="text-muted"><?php echo $stats['active_seats']; ?> متاح</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card" onclick="window.location.href='applications.php?status=pending'">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);"><i class="fas fa-file-alt"></i></div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['pending_applications']; ?></div>
                    <div class="stat-label">طلبات جديدة</div>
                    <small class="text-muted">في انتظار المراجعة</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card" onclick="window.location.href='interviews.php?status=scheduled'">
                <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6, #60a5fa);"><i class="fas fa-calendar-check"></i></div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['upcoming_interviews']; ?></div>
                    <div class="stat-label">مقابلات قادمة</div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card" onclick="window.location.href='trainees.php'">
                <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #34d399);"><i class="fas fa-users"></i></div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['active_trainees']; ?></div>
                    <div class="stat-label">متدربات حالياً</div>
                    <small class="text-success"><?php echo $stats['completed_trainees']; ?> مكتمل</small>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================= -->
    <!-- صف الإجراءات السريعة -->
    <!-- ========================================= -->
    <div class="row mb-4">
        <div class="col-md-3">
            <a href="seat_create.php" class="action-card">
                <div class="action-icon"><i class="fas fa-plus-circle"></i></div>
                <div class="action-info">
                    <h5>إضافة مقعد</h5>
                    <p>أضف فرصة تدريب جديدة</p>
                </div>
                <div class="action-arrow">
                    <i class="fas fa-arrow-left"></i>
                </div>
            </a>
        </div>
        
        <div class="col-md-3">
            <a href="applications.php?status=pending" class="action-card">
                <div class="action-icon"><i class="fas fa-file-alt"></i></div>
                <div class="action-info">
                    <h5>مراجعة الطلبات</h5>
                    <p><?php echo $stats['pending_applications']; ?> طلب في انتظار</p>
                </div>
                <div class="action-arrow">
                    <i class="fas fa-arrow-left"></i>
                </div>
            </a>
        </div>
        
        <div class="col-md-3">
            <a href="interviews.php?action=create" class="action-card">
                <div class="action-icon"><i class="fas fa-calendar-plus"></i></div>
                <div class="action-info">
                    <h5>جدولة مقابلة</h5>
                    <p>حدد موعد مقابلة</p>
                </div>
                <div class="action-arrow">
                    <i class="fas fa-arrow-left"></i>
                </div>
            </a>
        </div>
        
        <div class="col-md-3">
            <a href="attendance.php" class="action-card">
                <div class="action-icon"><i class="fas fa-clock"></i></div>
                <div class="action-info">
                    <h5>توثيق الحضور</h5>
                    <p>سجل حضور المتدربات</p>
                </div>
                <div class="action-arrow">
                    <i class="fas fa-arrow-left"></i>
                </div>
            </a>
        </div>
    </div>

    <!-- ========================================= -->
    <!-- صف المعلومات -->
    <!-- ========================================= -->
    <div class="row">
        <!-- آخر الطلبات -->
        <div class="col-md-6">
            <div class="recent-card">
                <div class="card-header">
                    <h5>
                        <i class="fas fa-file-alt ms-2" style="color: var(--primary);"></i>
                        آخر الطلبات الواردة
                    </h5>
                    <a href="applications.php" class="btn-link">عرض الكل</a>
                </div>
                <div class="card-body">
                    <?php if(empty($recent_applications)): ?>
                        <div class="empty-state">
                            <i class="fas fa-file-alt"></i>
                            <p>لا توجد طلبات جديدة</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($recent_applications as $app): ?>
                        <div class="recent-item" onclick="window.location.href='applications.php?action=view&id=<?php echo $app['id']; ?>'">
                            <div class="item-icon">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div class="item-content">
                                <h6><?php echo htmlspecialchars($app['full_name_ar']); ?></h6>
                                <div class="item-meta">
                                    <span><i class="fas fa-id-card"></i> <?php echo $app['student_id']; ?></span>
                                    <span><i class="fas fa-star"></i> <?php echo $app['gpa']; ?></span>
                                </div>
                                <small><?php echo $app['title_ar']; ?></small>
                            </div>
                            <div class="item-status">
                                <?php
                                $status_colors = [
                                    'pending' => 'warning',
                                    'under_review' => 'info',
                                    'interview_scheduled' => 'primary',
                                    'accepted_by_entity' => 'success',
                                    'approved' => 'success',
                                    'rejected' => 'danger'
                                ];
                                $status_text = [
                                    'pending' => 'قيد المراجعة',
                                    'under_review' => 'قيد الدراسة',
                                    'interview_scheduled' => 'مقابلة',
                                    'accepted_by_entity' => 'مقبول',
                                    'approved' => 'معتمد',
                                    'rejected' => 'مرفوض'
                                ];
                                ?>
                                <span class="badge bg-<?php echo $status_colors[$app['status']]; ?>">
                                    <?php echo $status_text[$app['status']]; ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- آخر المقاعد المضافة والمقابلات -->
        <div class="col-md-6">
            <div class="recent-card mb-3">
                <div class="card-header">
                    <h5>
                        <i class="fas fa-chair ms-2" style="color: var(--primary);"></i>
                        آخر المقاعد المضافة
                    </h5>
                    <a href="seats.php" class="btn-link">عرض الكل</a>
                </div>
                <div class="card-body">
                    <?php if(empty($recent_seats)): ?>
                        <div class="empty-state">
                            <i class="fas fa-chair"></i>
                            <p>لا توجد مقاعد مضافة</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($recent_seats as $seat): ?>
                        <div class="recent-item" onclick="window.location.href='seats.php?action=view&id=<?php echo $seat['id']; ?>'">
                            <div class="item-icon">
                                <i class="fas fa-briefcase"></i>
                            </div>
                            <div class="item-content">
                                <h6><?php echo htmlspecialchars($seat['title_ar']); ?></h6>
                                <div class="item-meta">
                                    <span><i class="fas fa-users"></i> <?php echo $seat['available_seats']; ?> مقعد</span>
                                    <span class="badge bg-<?php echo $seat['status'] == 'available' ? 'success' : 'secondary'; ?>">
                                        <?php echo $seat['status'] == 'available' ? 'متاح' : 'مكتمل'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- المقابلات القادمة -->
            <div class="recent-card">
                <div class="card-header">
                    <h5>
                        <i class="fas fa-calendar-check ms-2" style="color: var(--primary);"></i>
                        المقابلات القادمة
                    </h5>
                    <a href="interviews.php" class="btn-link">عرض الكل</a>
                </div>
                <div class="card-body">
                    <?php if(empty($upcoming_interviews)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <p>لا توجد مقابلات قادمة</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($upcoming_interviews as $interview): ?>
                        <div class="recent-item" onclick="window.location.href='interviews.php?action=view&id=<?php echo $interview['id']; ?>'">
                            <div class="item-icon">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <div class="item-content">
                                <h6><?php echo htmlspecialchars($interview['full_name_ar']); ?></h6>
                                <div class="item-meta">
                                    <span><i class="fas fa-calendar"></i> <?php echo date('Y-m-d', strtotime($interview['interview_date'])); ?></span>
                                    <span><i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($interview['interview_date'])); ?></span>
                                </div>
                                <small><?php echo $interview['title_ar']; ?></small>
                            </div>
                            <div class="item-status">
                                <span class="badge bg-<?php echo $interview['status'] == 'scheduled' ? 'primary' : 'success'; ?>">
                                    <?php echo $interview['status'] == 'scheduled' ? 'مجدولة' : 'مكتملة'; ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CSS إضافي -->
<style>
.welcome-card {
    background: var(--gradient);
    color: white;
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(0,102,51,0.2);
}

.stat-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 5px 15px rgba(0,102,51,0.05);
    display: flex;
    align-items: center;
    gap: 15px;
    cursor: pointer;
    transition: all 0.3s ease;
    height: 100%;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,102,51,0.1);
}

.stat-icon {
    width: 50px;
    height: 50px;
    background: var(--gradient);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
    flex-shrink: 0;
}

.stat-content {
    flex: 1;
}

.stat-value {
    font-size: 1.8rem;
    font-weight: 800;
    color: var(--dark);
    line-height: 1;
}

.stat-label {
    color: #6c757d;
    font-size: 0.85rem;
}

.action-card {
    background: white;
    border-radius: 12px;
    padding: 15px;
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    color: inherit;
    transition: all 0.3s ease;
    border: 1px solid rgba(0,102,51,0.05);
    height: 100%;
    width: 100%;
}

.action-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(0,102,51,0.1);
}

.action-icon {
    width: 40px;
    height: 40px;
    background: rgba(0,102,51,0.1);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 18px;
    flex-shrink: 0;
}

.action-card:hover .action-icon {
    background: var(--gradient);
    color: white;
}

.action-info {
    flex: 1;
}

.action-info h5 {
    margin: 0;
    font-size: 0.9rem;
    font-weight: 700;
}

.action-info p {
    margin: 2px 0 0;
    font-size: 0.7rem;
    color: #6c757d;
}

.action-arrow {
    color: #adb5bd;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    flex-shrink: 0;
}

.action-card:hover .action-arrow {
    color: var(--primary);
    transform: translateX(-5px);
}

.recent-card {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,102,51,0.05);
    height: 100%;
}

.recent-card .card-header {
    padding: 15px;
    border-bottom: 1px solid #edf2f7;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8f9fa;
}

.recent-card .card-header h5 {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 5px;
}

.recent-card .card-body {
    padding: 10px;
}

.recent-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-bottom: 5px;
}

.recent-item:hover {
    background: #f8f9fa;
}

.item-icon {
    width: 35px;
    height: 35px;
    background: rgba(0,102,51,0.1);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    flex-shrink: 0;
}

.item-content {
    flex: 1;
}

.item-content h6 {
    margin: 0;
    font-size: 0.9rem;
    font-weight: 600;
}

.item-meta {
    font-size: 0.75rem;
    color: #6c757d;
    display: flex;
    gap: 10px;
    margin-top: 2px;
    flex-wrap: wrap;
}

.item-status {
    flex-shrink: 0;
}

.btn-link {
    color: var(--primary);
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 600;
}

.btn-link:hover {
    text-decoration: underline;
}

.empty-state {
    text-align: center;
    padding: 30px 15px;
}

.empty-state i {
    font-size: 40px;
    color: #dee2e6;
    margin-bottom: 10px;
}

.empty-state p {
    color: #6c757d;
    margin: 0;
    font-size: 0.9rem;
}
</style>

<?php require_once '../../includes/entity_footer.php'; ?>