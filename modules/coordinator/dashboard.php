 <?php
// modules/coordinator/dashboard.php - لوحة التحكم الرئيسية للمنسقة
session_start();
require_once '../../config/config.php';

// التحقق من تسجيل الدخول
// =============================================
if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'coordinator') {
    header('Location: ../auth/login.php?type=coordinator');
    exit();
}

// جلب بيانات المنسقة
$stmt = $pdo->prepare("
    SELECT c.*, u.email 
    FROM coordinators c
    JOIN users u ON c.user_id = u.id
    WHERE c.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$coordinator = $stmt->fetch();

if(!$coordinator) {
    header('Location: ../auth/logout.php');
    exit();
}

// =============================================
// جلب الإحصائيات العامة
// =============================================

// الجهات التدريبية
$stats['total_entities'] = $pdo->query("SELECT COUNT(*) FROM training_entities")->fetchColumn();
$stats['pending_entities'] = $pdo->query("SELECT COUNT(*) FROM training_entities WHERE is_approved = 0")->fetchColumn();
$stats['approved_entities'] = $stats['total_entities'] - $stats['pending_entities'];

// المتدربات
$stats['total_students'] = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$stats['graduating'] = $pdo->query("SELECT COUNT(*) FROM students WHERE is_graduating = 1 OR expected_graduation <= DATE_ADD(NOW(), INTERVAL 6 MONTH)")->fetchColumn();

// الطلبات
$stats['total_apps'] = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
$stats['pending_apps'] = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'pending'")->fetchColumn();
$stats['approved_apps'] = $pdo->query("SELECT COUNT(*) FROM applications WHERE status IN ('approved', 'accepted_by_entity')")->fetchColumn();
$stats['interview_apps'] = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'interview_scheduled'")->fetchColumn();

// التدريب
$stats['active_training'] = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'approved'")->fetchColumn();
$stats['completed'] = $pdo->query("SELECT COUNT(*) FROM evaluations WHERE training_completed = 1")->fetchColumn();

// =============================================
// جلب آخر الرسائل
// =============================================
$recent_messages = $pdo->prepare("
    SELECT m.*,
           CASE 
               WHEN m.sender_type = 'student' THEN (SELECT full_name_ar FROM students WHERE user_id = m.sender_id)
               WHEN m.sender_type = 'supervisor' THEN (SELECT full_name_ar FROM supervisors WHERE user_id = m.sender_id)
               WHEN m.sender_type = 'training_entity' THEN (SELECT entity_name_ar FROM training_entities WHERE user_id = m.sender_id)
           END as sender_name
    FROM messages m
    WHERE m.receiver_id = ? AND m.receiver_type = 'coordinator'
    ORDER BY m.created_at DESC
    LIMIT 5
");
$recent_messages->execute([$_SESSION['user_id']]);
$messages = $recent_messages->fetchAll();

// عدد الرسائل غير المقروءة
$unread_messages = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND receiver_type = 'coordinator' AND is_read = 0");
$unread_messages->execute([$_SESSION['user_id']]);
$unread_count = $unread_messages->fetchColumn();

// =============================================
// جلب آخر الجهات المسجلة
// =============================================
$recent_entities = $pdo->query("
    SELECT * FROM training_entities 
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll();

// =============================================
// جلب آخر الطلبات
// =============================================
$recent_apps = $pdo->query("
    SELECT a.*, s.full_name_ar, ts.title_ar, te.entity_name_ar
    FROM applications a
    JOIN students s ON a.student_id = s.id
    JOIN training_seats ts ON a.seat_id = ts.id
    JOIN training_entities te ON ts.entity_id = te.id
    ORDER BY a.created_at DESC
    LIMIT 5
")->fetchAll();

$page_title = 'لوحة التحكم';
require_once '../../includes/coordinator_header.php';
?>

<div class="content">
    <!-- ========================================= -->
    <!-- الترحيب والإحصائيات السريعة -->
    <!-- ========================================= -->
    <div class="welcome-card">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2>مرحباً، <?php echo htmlspecialchars($coordinator['full_name_ar']); ?> 👋</h2>
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
                <div class="stats-badge">
                    <div class="d-flex gap-2">
                        <span class="badge bg-white text-dark p-3">
                            <i class="fas fa-building ms-2"></i> <?php echo $stats['total_entities']; ?> جهة
                        </span>
                        <span class="badge bg-white text-dark p-3">
                            <i class="fas fa-user-graduate ms-2"></i> <?php echo $stats['total_students']; ?> متدربة
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================= -->
    <!-- بطاقات الإحصائيات الرئيسية -->
    <!-- ========================================= -->
    <div class="stats-grid">
        <div class="stat-card" onclick="window.location.href='entities.php?status=pending'">
            <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                <i class="fas fa-building"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['pending_entities']; ?></div>
                <div class="stat-label">جهات في انتظار الاعتماد</div>
                <div class="stat-trend up">
                    <i class="fas fa-arrow-up"></i> +2 هذا الأسبوع
                </div>
            </div>
        </div>

        <div class="stat-card" onclick="window.location.href='applications.php?status=pending'">
            <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6, #60a5fa);">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['pending_apps']; ?></div>
                <div class="stat-label">طلبات جديدة</div>
                <div class="stat-trend up">
                    <i class="fas fa-arrow-up"></i> +5 اليوم
                </div>
            </div>
        </div>

        <div class="stat-card" onclick="window.location.href='applications.php?status=interview_scheduled'">
            <div class="stat-icon" style="background: linear-gradient(135deg, #8b5cf6, #a78bfa);">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['interview_apps']; ?></div>
                <div class="stat-label">مقابلات مجدولة</div>
                <div class="stat-trend">
                    <i class="fas fa-calendar"></i> هذا الأسبوع
                </div>
            </div>
        </div>

        <div class="stat-card" onclick="window.location.href='students.php?graduating=1'">
            <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #34d399);">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['graduating']; ?></div>
                <div class="stat-label">طالبات متخرجات</div>
                <div class="stat-trend">
                    <i class="fas fa-clock"></i> خلال 6 أشهر
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================= -->
    <!-- صف الإجراءات السريعة -->
    <!-- ========================================= -->
    <div class="quick-actions">
        <a href="entities.php" class="action-card">
            <div class="action-icon"><i class="fas fa-building"></i></div>
            <div class="action-info">
                <h5>إدارة الجهات</h5>
                <p>اعتماد ومتابعة جهات التدريب</p>
            </div>
            <div class="action-arrow">
                <i class="fas fa-arrow-left"></i>
            </div>
        </a>

        <a href="students.php" class="action-card">
            <div class="action-icon"><i class="fas fa-user-graduate"></i></div>
            <div class="action-info">
                <h5>إدارة المتدربات</h5>
                <p>عرض ومتابعة بيانات المتدربات</p>
            </div>
            <div class="action-arrow">
                <i class="fas fa-arrow-left"></i>
            </div>
        </a>

        <a href="applications.php" class="action-card">
            <div class="action-icon"><i class="fas fa-file-alt"></i></div>
            <div class="action-info">
                <h5>الطلبات</h5>
                <p>مراجعة طلبات التدريب</p>
            </div>
            <div class="action-arrow">
                <i class="fas fa-arrow-left"></i>
            </div>
        </a>
 
    </div>

    <!-- ========================================= -->
    <!-- صف المعلومات التفصيلية + الرسائل -->
    <!-- ========================================= -->
    <div class="row">
        <!-- آخر الجهات المسجلة -->
        <div class="col-lg-4">
            <div class="recent-card">
                <div class="card-header">
                    <h5>
                        <i class="fas fa-building ms-2" style="color: var(--primary);"></i>
                        آخر الجهات المسجلة
                    </h5>
                    <a href="entities.php" class="btn-link">عرض الكل</a>
                </div>
                <div class="card-body">
                    <?php if(empty($recent_entities)): ?>
                        <div class="empty-state">
                            <i class="fas fa-building"></i>
                            <p>لا توجد جهات مسجلة</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($recent_entities as $entity): ?>
                        <div class="recent-item" onclick="window.location.href='entities.php?action=view&id=<?php echo $entity['id']; ?>'">
                            <div class="item-icon">
                                <i class="fas fa-building"></i>
                            </div>
                            <div class="item-content">
                                <h6><?php echo htmlspecialchars($entity['entity_name_ar']); ?></h6>
                                <div class="item-meta">
                                    <span><i class="fas fa-city"></i> <?php echo $entity['city'] ?? 'غير محدد'; ?></span>
                                    <span class="date"><i class="fas fa-calendar"></i> <?php echo date('Y-m-d', strtotime($entity['created_at'])); ?></span>
                                </div>
                            </div>
                            <div class="item-status">
                                <?php if($entity['is_approved']): ?>
                                    <span class="badge bg-success">معتمدة</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">في الانتظار</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- آخر الطلبات -->
        <div class="col-lg-4">
            <div class="recent-card">
                <div class="card-header">
                    <h5>
                        <i class="fas fa-file-alt ms-2" style="color: var(--primary);"></i>
                        آخر الطلبات
                    </h5>
                    <a href="applications.php" class="btn-link">عرض الكل</a>
                </div>
                <div class="card-body">
                    <?php if(empty($recent_apps)): ?>
                        <div class="empty-state">
                            <i class="fas fa-file-alt"></i>
                            <p>لا توجد طلبات</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($recent_apps as $app): ?>
                        <div class="recent-item" onclick="window.location.href='applications.php?action=view&id=<?php echo $app['id']; ?>'">
                            <div class="item-icon">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div class="item-content">
                                <h6><?php echo htmlspecialchars($app['full_name_ar']); ?></h6>
                                <div class="item-meta">
                                    <span><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($app['title_ar']); ?></span>
                                    <span class="date"><i class="fas fa-calendar"></i> <?php echo date('Y-m-d', strtotime($app['created_at'])); ?></span>
                                </div>
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
                                    'pending' => 'جديد',
                                    'under_review' => 'قيد المراجعة',
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

        <!-- آخر الرسائل -->
        <div class="col-lg-4">
            <div class="recent-card messages-card">
                <div class="card-header">
                    <h5>
                        <i class="fas fa-envelope ms-2" style="color: var(--primary);"></i>
                        آخر الرسائل
                        <?php if($unread_count > 0): ?>
                        <span class="unread-badge"><?php echo $unread_count; ?> جديدة</span>
                        <?php endif; ?>
                    </h5>
                    <a href="messages.php" class="btn-link">عرض الكل</a>
                </div>
                <div class="card-body">
                    <?php if(empty($messages)): ?>
                        <div class="empty-state">
                            <i class="fas fa-envelope-open-text"></i>
                            <p>لا توجد رسائل جديدة</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($messages as $msg): ?>
                        <div class="message-item <?php echo !$msg['is_read'] ? 'unread' : ''; ?>" 
                             onclick="window.location.href='messages.php?view=<?php echo $msg['id']; ?>'">
                            <div class="message-avatar">
                                <?php
                                $avatar_color = [
                                    'student' => '#3b82f6',
                                    'supervisor' => '#10b981',
                                    'training_entity' => '#f59e0b'
                                ];
                                $bg = $avatar_color[$msg['sender_type']] ?? '#6c757d';
                                ?>
                                <div class="avatar" style="background: <?php echo $bg; ?>">
                                    <?php echo mb_substr($msg['sender_name'] ?? '?', 0, 1); ?>
                                </div>
                            </div>
                            <div class="message-content">
                                <div class="message-header">
                                    <strong><?php echo htmlspecialchars($msg['sender_name'] ?? 'مرسل'); ?></strong>
                                    <small><?php 
                                        $time = strtotime($msg['created_at']);
                                        if(date('Y-m-d') == date('Y-m-d', $time)) {
                                            echo 'اليوم ' . date('H:i', $time);
                                        } else {
                                            echo date('Y-m-d', $time);
                                        }
                                    ?></small>
                                </div>
                                <div class="message-subject"><?php echo htmlspecialchars($msg['subject']); ?></div>
                                <div class="message-preview">
                                    <?php echo mb_substr(strip_tags($msg['message']), 0, 50); ?>...
                                </div>
                            </div>
                            <?php if(!$msg['is_read']): ?>
                            <div class="message-dot"></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================= -->
    <!-- إحصائيات إضافية -->
    <!-- ========================================= -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="chart-card">
                <h5 class="mb-3">توزيع الطلبات حسب الحالة</h5>
                <div class="chart-container">
                    <canvas id="applicationsChart" style="height: 250px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="chart-card">
                <h5 class="mb-3">نشاط النظام</h5>
                <div class="activity-timeline">
                    <div class="timeline-item">
                        <div class="timeline-icon bg-success">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="timeline-content">
                            <strong>جهة جديدة</strong>
                            <p>شركة تكامل سجلت اليوم</p>
                            <small>منذ ساعة</small>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-icon bg-primary">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="timeline-content">
                            <strong>طلب جديد</strong>
                            <p>سارة أحمد قدمت طلب تدريب</p>
                            <small>منذ 3 ساعات</small>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-icon bg-warning">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="timeline-content">
                            <strong>تم اعتماد جهة</strong>
                            <p>تم اعتماد مؤسسة رواد المستقبل</p>
                            <small>أمس</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CSS إضافي للصفحة -->
<style>
.welcome-card {
    background: var(--gradient);
    color: white;
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(0,102,51,0.2);
}

.welcome-card .badge {
    background: rgba(255,255,255,0.2);
    border-radius: 50px;
    font-size: 0.9rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

.stat-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 15px;
    border: 1px solid rgba(0,102,51,0.05);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,102,51,0.1);
    border-color: rgba(0,102,51,0.2);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}

.stat-content {
    flex: 1;
}

.stat-value {
    font-size: 2rem;
    font-weight: 800;
    color: var(--dark);
    line-height: 1;
    margin-bottom: 5px;
}

.stat-label {
    color: #6c757d;
    font-size: 0.9rem;
    font-weight: 600;
}

.stat-trend {
    font-size: 0.8rem;
    margin-top: 5px;
    color: #10b981;
}

.stat-trend.up { color: #10b981; }
.stat-trend i { font-size: 0.7rem; }

.quick-actions {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 30px;
}

@media (max-width: 768px) {
    .quick-actions {
        grid-template-columns: repeat(2, 1fr);
    }
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
    box-shadow: 0 2px 10px rgba(0,0,0,0.02);
}

.action-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(0,102,51,0.1);
    border-color: rgba(0,102,51,0.2);
}

.action-icon {
    width: 45px;
    height: 45px;
    background: rgba(0,102,51,0.1);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 20px;
    transition: all 0.3s ease;
}

.action-card:hover .action-icon {
    background: var(--gradient);
    color: white;
    transform: rotate(360deg);
}

.action-info {
    flex: 1;
}

.action-info h5 {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--dark);
}

.action-info p {
    margin: 2px 0 0;
    font-size: 0.75rem;
    color: #6c757d;
}

.action-arrow {
    color: #adb5bd;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.action-card:hover .action-arrow {
    color: var(--primary);
    transform: translateX(-5px);
}

.recent-card {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    height: 100%;
    display: flex;
    flex-direction: column;
}

.recent-card .card-header {
    padding: 15px 20px;
    border-bottom: 1px solid #edf2f7;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.recent-card .card-header h5 {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: var(--dark);
    display: flex;
    align-items: center;
}

.recent-card .card-body {
    padding: 10px;
    flex: 1;
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

.recent-item {
    display: flex;
    align-items: center;
    padding: 12px;
    border-radius: 10px;
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
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 16px;
    margin-left: 12px;
}

.item-content {
    flex: 1;
}

.item-content h6 {
    margin: 0;
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--dark);
}

.item-meta {
    font-size: 0.75rem;
    color: #6c757d;
    display: flex;
    gap: 10px;
    margin-top: 2px;
}

.item-meta span {
    display: flex;
    align-items: center;
    gap: 3px;
}

.item-meta i {
    font-size: 0.7rem;
}

.item-status .badge {
    font-size: 0.7rem;
    padding: 3px 8px;
}

/* تنسيق الرسائل */
.messages-card .card-body {
    padding: 5px;
}

.message-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    margin-bottom: 2px;
}

.message-item:hover {
    background: #f8f9fa;
}

.message-item.unread {
    background: #f0f7f0;
}

.message-avatar .avatar {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 16px;
}

.message-content {
    flex: 1;
}

.message-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 3px;
}

.message-header strong {
    font-size: 0.9rem;
    color: var(--dark);
}

.message-header small {
    font-size: 0.7rem;
    color: #6c757d;
}

.message-subject {
    font-weight: 600;
    font-size: 0.85rem;
    color: var(--dark);
    margin-bottom: 2px;
}

.message-preview {
    font-size: 0.75rem;
    color: #6c757d;
}

.message-dot {
    width: 8px;
    height: 8px;
    background: var(--primary);
    border-radius: 50%;
    position: absolute;
    top: 20px;
    right: 10px;
}

.unread-badge {
    background: #dc3545;
    color: white;
    font-size: 0.7rem;
    padding: 2px 8px;
    border-radius: 50px;
    margin-right: 8px;
}

.chart-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
}

.chart-container {
    position: relative;
    height: 250px;
}

.activity-timeline {
    padding: 10px 0;
}

.timeline-item {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    position: relative;
}

.timeline-item:last-child {
    margin-bottom: 0;
}

.timeline-icon {
    width: 35px;
    height: 35px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 16px;
}

.timeline-content {
    flex: 1;
}

.timeline-content strong {
    font-size: 0.9rem;
    color: var(--dark);
    display: block;
    margin-bottom: 2px;
}

.timeline-content p {
    margin: 0;
    font-size: 0.8rem;
    color: #6c757d;
}

.timeline-content small {
    font-size: 0.7rem;
    color: #adb5bd;
}

.btn-link {
    color: var(--primary);
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-link:hover {
    color: var(--secondary);
    text-decoration: underline;
}
</style>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // رسم بياني للطلبات
    const ctx = document.getElementById('applicationsChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['جديد', 'مقابلة', 'مقبول', 'مرفوض'],
            datasets: [{
                data: [
                    <?php echo $stats['pending_apps']; ?>,
                    <?php echo $stats['interview_apps']; ?>,
                    <?php echo $stats['approved_apps']; ?>,
                    <?php 
                    $rejected = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'rejected'")->fetchColumn();
                    echo $rejected;
                    ?>
                ],
                backgroundColor: [
                    '#f59e0b',
                    '#3b82f6',
                    '#10b981',
                    '#ef4444'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: {
                            family: 'Cairo'
                        }
                    }
                }
            },
            cutout: '60%'
        }
    });
</script>

<?php require_once '../../includes/coordinator_footer.php'; ?>