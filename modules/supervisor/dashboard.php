 <?php
// modules/supervisor/dashboard.php - لوحة التحكم الرئيسية للمشرف
session_start();
require_once '../../config/config.php';

// =============================================
// التحقق من تسجيل الدخول
// =============================================
if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'supervisor') {
    header('Location: ../auth/login.php?type=supervisor');
    exit();
}

// جلب بيانات المشرف
$stmt = $pdo->prepare("SELECT * FROM supervisors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$supervisor = $stmt->fetch();

if(!$supervisor) {
    header('Location: ../auth/logout.php');
    exit();
}

// =============================================
// جلب الإحصائيات
// =============================================

// عدد المتدربات المشرف عليها
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM applications 
    WHERE supervisor_id = ? AND status IN ('approved', 'accepted_by_entity')
");
$stmt->execute([$supervisor['id']]);
$stats['my_students'] = $stmt->fetchColumn();

// عدد التقارير في انتظار المراجعة
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM weekly_reports r
    JOIN applications a ON r.application_id = a.id
    WHERE a.supervisor_id = ? AND r.status = 'submitted'
");
$stmt->execute([$supervisor['id']]);
$stats['pending_reports'] = $stmt->fetchColumn();

// عدد التقارير المعتمدة
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM weekly_reports r
    JOIN applications a ON r.application_id = a.id
    WHERE a.supervisor_id = ? AND r.status = 'approved'
");
$stmt->execute([$supervisor['id']]);
$stats['approved_reports'] = $stmt->fetchColumn();

// عدد الزيارات المطلوبة
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM visits 
    WHERE supervisor_id = ? AND status = 'scheduled'
");
$stmt->execute([$supervisor['id']]);
$stats['pending_visits'] = $stmt->fetchColumn();

// عدد الزيارات المكتملة
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM visits 
    WHERE supervisor_id = ? AND status = 'completed'
");
$stmt->execute([$supervisor['id']]);
$stats['completed_visits'] = $stmt->fetchColumn();

// عدد التقييمات النهائية
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM evaluations e
    JOIN applications a ON e.application_id = a.id
    WHERE a.supervisor_id = ?
");
$stmt->execute([$supervisor['id']]);
$stats['evaluations'] = $stmt->fetchColumn();

// =============================================
// جلب آخر المتدربات
// =============================================
$stmt = $pdo->prepare("
    SELECT s.full_name_ar, s.student_id, a.id as application_id,
           ts.title_ar, te.entity_name_ar
    FROM applications a
    JOIN students s ON a.student_id = s.id
    JOIN training_seats ts ON a.seat_id = ts.id
    JOIN training_entities te ON ts.entity_id = te.id
    WHERE a.supervisor_id = ? AND a.status IN ('approved', 'accepted_by_entity')
    ORDER BY a.updated_at DESC
    LIMIT 5
");
$stmt->execute([$supervisor['id']]);
$my_students = $stmt->fetchAll();

// =============================================
// جلب آخر التقارير
// =============================================
$stmt = $pdo->prepare("
    SELECT r.*, s.full_name_ar, ts.title_ar
    FROM weekly_reports r
    JOIN applications a ON r.application_id = a.id
    JOIN students s ON a.student_id = s.id
    JOIN training_seats ts ON a.seat_id = ts.id
    WHERE a.supervisor_id = ?
    ORDER BY r.created_at DESC
    LIMIT 5
");
$stmt->execute([$supervisor['id']]);
$reports_list = $stmt->fetchAll();

// =============================================
// جلب آخر الزيارات
// =============================================
$stmt = $pdo->prepare("
    SELECT v.*, s.full_name_ar, s.student_id, ts.title_ar, te.entity_name_ar
    FROM visits v
    JOIN students s ON v.student_id = s.id
    JOIN applications a ON v.application_id = a.id
    JOIN training_seats ts ON a.seat_id = ts.id
    JOIN training_entities te ON ts.entity_id = te.id
    WHERE v.supervisor_id = ?
    ORDER BY v.visit_date DESC
    LIMIT 5
");
$stmt->execute([$supervisor['id']]);
$recent_visits = $stmt->fetchAll();

// =============================================
// جلب آخر الرسائل
// =============================================
$stmt = $pdo->prepare("
    SELECT m.*,
           CASE 
               WHEN m.sender_type = 'student' THEN (SELECT full_name_ar FROM students WHERE user_id = m.sender_id)
               WHEN m.sender_type = 'coordinator' THEN (SELECT full_name_ar FROM coordinators WHERE user_id = m.sender_id)
               WHEN m.sender_type = 'training_entity' THEN (SELECT entity_name_ar FROM training_entities WHERE user_id = m.sender_id)
           END as sender_name
    FROM messages m
    WHERE m.receiver_id = ? AND m.receiver_type = 'supervisor'
    ORDER BY m.created_at DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$messages = $stmt->fetchAll();

// عدد الرسائل غير المقروءة
$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND receiver_type = 'supervisor' AND is_read = 0");
$stmt->execute([$_SESSION['user_id']]);
$unread_count = $stmt->fetchColumn();

$page_title = 'لوحة التحكم';
require_once '../../includes/supervisor_header.php';
?>

<div class="content">
    <!-- ========================================= -->
    <!-- الترحيب -->
    <!-- ========================================= -->
    <div class="welcome-card">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2>مرحباً، <?php echo htmlspecialchars($supervisor['full_name_ar']); ?> 👋</h2>
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
                    <span class="badge bg-white text-dark p-3">
                        <i class="fas fa-user-graduate ms-2"></i> <?php echo $stats['my_students']; ?> متدربة
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================= -->
    <!-- بطاقات الإحصائيات -->
    <!-- ========================================= -->
    <div class="stats-grid">
        <div class="stat-card" onclick="window.location.href='students.php'">
            <div class="stat-icon" style="background: linear-gradient(135deg, #006633, #228B22);">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['my_students']; ?></div>
                <div class="stat-label">المتدربات المشرف عليها</div>
            </div>
        </div>

        <div class="stat-card" onclick="window.location.href='reports.php?status=pending'">
            <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['pending_reports']; ?></div>
                <div class="stat-label">تقارير في انتظار المراجعة</div>
            </div>
        </div>

        <div class="stat-card" onclick="window.location.href='visits.php?status=scheduled'">
            <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6, #60a5fa);">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['pending_visits']; ?></div>
                <div class="stat-label">زيارات مجدولة</div>
                <small class="text-muted">مكتمل: <?php echo $stats['completed_visits']; ?></small>
            </div>
        </div>

        <div class="stat-card" onclick="window.location.href='evaluations.php'">
            <div class="stat-icon" style="background: linear-gradient(135deg, #8b5cf6, #a78bfa);">
                <i class="fas fa-star"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['evaluations']; ?></div>
                <div class="stat-label">تقييمات نهائية</div>
            </div>
        </div>
    </div>

    <!-- ========================================= -->
    <!-- صف الإجراءات السريعة -->
    <!-- ========================================= -->
    <div class="quick-actions">
        <a href="students.php" class="action-card">
            <div class="action-icon"><i class="fas fa-user-graduate"></i></div>
            <div class="action-info">
                <h5>المتدربات</h5>
                <p>متابعة المتدربات المشرف عليها</p>
            </div>
            <div class="action-arrow">
                <i class="fas fa-arrow-left"></i>
            </div>
        </a>

        <a href="reports.php" class="action-card">
            <div class="action-icon"><i class="fas fa-file-alt"></i></div>
            <div class="action-info">
                <h5>التقارير</h5>
                <p>مراجعة التقارير الأسبوعية</p>
            </div>
            <div class="action-arrow">
                <i class="fas fa-arrow-left"></i>
            </div>
        </a>

        <a href="visits.php" class="action-card">
            <div class="action-icon"><i class="fas fa-calendar-check"></i></div>
            <div class="action-info">
                <h5>الزيارات</h5>
                <p>جدولة وتوثيق الزيارات</p>
            </div>
            <div class="action-arrow">
                <i class="fas fa-arrow-left"></i>
            </div>
        </a>

        <a href="evaluations.php" class="action-card">
            <div class="action-icon"><i class="fas fa-star"></i></div>
            <div class="action-info">
                <h5>التقييم</h5>
                <p>التقييم النهائي للطالبات</p>
            </div>
            <div class="action-arrow">
                <i class="fas fa-arrow-left"></i>
            </div>
        </a>
    </div>

    <!-- ========================================= -->
    <!-- صف المعلومات الأول -->
    <!-- ========================================= -->
    <div class="row">
        <!-- آخر المتدربات -->
        <div class="col-lg-4">
            <div class="recent-card">
                <div class="card-header">
                    <h5>
                        <i class="fas fa-user-graduate ms-2" style="color: var(--primary);"></i>
                        آخر المتدربات
                    </h5>
                    <a href="students.php" class="btn-link">عرض الكل</a>
                </div>
                <div class="card-body">
                    <?php if(empty($my_students)): ?>
                        <div class="empty-state">
                            <i class="fas fa-user-graduate"></i>
                            <p>لا توجد طالبات</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($my_students as $s): ?>
                        <div class="recent-item" onclick="window.location.href='student_view.php?id=<?php echo $s['application_id']; ?>'">
                            <div class="item-icon">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div class="item-content">
                                <h6><?php echo htmlspecialchars($s['full_name_ar']); ?></h6>
                                <div class="item-meta">
                                    <span><i class="fas fa-id-card"></i> <?php echo $s['student_id']; ?></span>
                                    <span><i class="fas fa-building"></i> <?php echo $s['entity_name_ar']; ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- آخر التقارير -->
        <div class="col-lg-4">
            <div class="recent-card">
                <div class="card-header">
                    <h5>
                        <i class="fas fa-file-alt ms-2" style="color: var(--primary);"></i>
                        آخر التقارير
                    </h5>
                    <a href="reports.php" class="btn-link">عرض الكل</a>
                </div>
                <div class="card-body">
                    <?php if(empty($reports_list)): ?>
                        <div class="empty-state">
                            <i class="fas fa-file-alt"></i>
                            <p>لا توجد تقارير</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($reports_list as $r): ?>
                        <div class="recent-item" onclick="window.location.href='report_view.php?id=<?php echo $r['id']; ?>'">
                            <div class="item-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="item-content">
                                <h6><?php echo htmlspecialchars($r['full_name_ar']); ?></h6>
                                <div class="item-meta">
                                    <span>الأسبوع <?php echo $r['week_number']; ?></span>
                                    <?php
                                    $status_colors = [
                                        'draft' => 'secondary',
                                        'submitted' => 'info',
                                        'reviewed' => 'warning',
                                        'approved' => 'success'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $status_colors[$r['status']]; ?>">
                                        <?php echo $r['status']; ?>
                                    </span>
                                </div>
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
                            <p>لا توجد رسائل</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($messages as $msg): ?>
                        <div class="message-item <?php echo !$msg['is_read'] ? 'unread' : ''; ?>" 
                             onclick="window.location.href='messages.php?view=<?php echo $msg['id']; ?>'">
                            <div class="message-avatar">
                                <div class="avatar">
                                    <?php echo mb_substr($msg['sender_name'] ?? '?', 0, 1); ?>
                                </div>
                            </div>
                            <div class="message-content">
                                <div class="message-header">
                                    <strong><?php echo htmlspecialchars($msg['sender_name'] ?? 'مرسل'); ?></strong>
                                    <small><?php echo date('Y-m-d', strtotime($msg['created_at'])); ?></small>
                                </div>
                                <div class="message-subject"><?php echo htmlspecialchars($msg['subject']); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================= -->
    <!-- صف الزيارات الأخيرة -->
    <!-- ========================================= -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="recent-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-check ms-2" style="color: var(--primary);"></i>
                        آخر الزيارات الميدانية
                        <span class="badge bg-primary ms-2"><?php echo $stats['pending_visits']; ?> مجدولة</span>
                        <span class="badge bg-success ms-2"><?php echo $stats['completed_visits']; ?> مكتملة</span>
                    </h5>
                    <a href="visits.php" class="btn-link">عرض الكل</a>
                </div>
                <div class="card-body">
                    <?php if(empty($recent_visits)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <p class="text-muted">لا توجد زيارات مجدولة</p>
                            <a href="visits.php?action=add" class="btn-primary-custom btn-sm">
                                <i class="fas fa-plus-circle ms-2"></i> جدولة زيارة
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>المتدربة</th>
                                        <th>الجهة</th>
                                        <th>التاريخ</th>
                                        <th>الوقت</th>
                                        <th>المكان</th>
                                        <th>الحالة</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($recent_visits as $v): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($v['full_name_ar']); ?></strong>
                                            <br><small class="text-muted"><?php echo $v['student_id']; ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($v['entity_name_ar']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($v['visit_date'])); ?></td>
                                        <td><?php echo date('H:i', strtotime($v['visit_time'])); ?></td>
                                        <td><?php echo htmlspecialchars($v['location']); ?></td>
                                        <td>
                                            <?php
                                            $status_class = [
                                                'scheduled' => 'primary',
                                                'completed' => 'success',
                                                'cancelled' => 'danger'
                                            ];
                                            $status_text = [
                                                'scheduled' => 'مجدولة',
                                                'completed' => 'مكتملة',
                                                'cancelled' => 'ملغاة'
                                            ];
                                            ?>
                                            <span class="badge bg-<?php echo $status_class[$v['status']]; ?>">
                                                <?php echo $status_text[$v['status']]; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="visits.php?action=view&id=<?php echo $v['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
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
    box-shadow: 0 5px 15px rgba(0,102,51,0.05);
    display: flex;
    align-items: center;
    gap: 15px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,102,51,0.1);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
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
}

.item-meta span {
    display: flex;
    align-items: center;
    gap: 3px;
}

.btn-link {
    color: var(--primary);
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 600;
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

.unread-badge {
    background: #dc3545;
    color: white;
    font-size: 0.7rem;
    padding: 2px 8px;
    border-radius: 50px;
    margin-right: 8px;
}

.table td {
    vertical-align: middle;
}

.badge {
    padding: 5px 10px;
    border-radius: 20px;
}
</style>

<?php require_once '../../includes/supervisor_footer.php'; ?>