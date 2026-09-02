 <?php
// modules/student/dashboard.php - لوحة التحكم الرئيسية للمتدربة
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
$stmt = $pdo->prepare("
    SELECT s.*, m.major_name_ar, u.email 
    FROM students s
    LEFT JOIN majors m ON s.major_id = m.id
    JOIN users u ON s.user_id = u.id
    WHERE s.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();

if(!$student) {
    header('Location: ../auth/logout.php');
    exit();
}

// =============================================
// جلب الإحصائيات
// =============================================

// إحصائيات الطلبات
$stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE student_id = ?");
$stmt->execute([$student['id']]);
$stats['total_applications'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE student_id = ? AND status = 'pending'");
$stmt->execute([$student['id']]);
$stats['pending_applications'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE student_id = ? AND status IN ('approved', 'accepted_by_entity')");
$stmt->execute([$student['id']]);
$stats['accepted_applications'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE student_id = ? AND status = 'rejected'");
$stmt->execute([$student['id']]);
$stats['rejected_applications'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE student_id = ? AND status = 'interview_scheduled'");
$stmt->execute([$student['id']]);
$stats['interview_scheduled'] = $stmt->fetchColumn();

// إحصائيات الحضور
$stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE student_id = ? AND status = 'present'");
$stmt->execute([$student['id']]);
$stats['present_days'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_hours), 0) FROM attendance WHERE student_id = ? AND status = 'present'");
$stmt->execute([$student['id']]);
$stats['total_hours'] = $stmt->fetchColumn();

// إحصائيات التقارير
$stmt = $pdo->prepare("SELECT COUNT(*) FROM weekly_reports WHERE student_id = ?");
$stmt->execute([$student['id']]);
$stats['total_reports'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM weekly_reports WHERE student_id = ? AND status = 'approved'");
$stmt->execute([$student['id']]);
$stats['approved_reports'] = $stmt->fetchColumn();

// إحصائيات الرسائل والإشعارات
$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND receiver_type = 'student' AND is_read = 0");
$stmt->execute([$_SESSION['user_id']]);
$stats['unread_messages'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND user_type = 'student' AND is_read = 0");
$stmt->execute([$_SESSION['user_id']]);
$stats['unread_notifications'] = $stmt->fetchColumn();

// =============================================
// جلب آخر طلب نشط
// =============================================
$active_application = null;
if($stats['accepted_applications'] > 0) {
    $stmt = $pdo->prepare("
        SELECT a.*, ts.title_ar, ts.start_date, ts.end_date, ts.daily_hours,
               te.entity_name_ar, te.city, te.logo,
               s.full_name_ar as supervisor_name, s.phone as supervisor_phone
        FROM applications a
        JOIN training_seats ts ON a.seat_id = ts.id
        JOIN training_entities te ON ts.entity_id = te.id
        LEFT JOIN supervisors s ON a.supervisor_id = s.id
        WHERE a.student_id = ? AND a.status IN ('approved', 'accepted_by_entity')
        ORDER BY a.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$student['id']]);
    $active_application = $stmt->fetch();
}

// =============================================
// جلب آخر الطلبات
// =============================================
$stmt = $pdo->prepare("
    SELECT a.*, ts.title_ar, te.entity_name_ar
    FROM applications a
    JOIN training_seats ts ON a.seat_id = ts.id
    JOIN training_entities te ON ts.entity_id = te.id
    WHERE a.student_id = ?
    ORDER BY a.created_at DESC
    LIMIT 5
");
$stmt->execute([$student['id']]);
$recent_apps = $stmt->fetchAll();

// =============================================
// جلب آخر التقارير
// =============================================
$stmt = $pdo->prepare("
    SELECT * FROM weekly_reports 
    WHERE student_id = ? 
    ORDER BY created_at DESC 
    LIMIT 3
");
$stmt->execute([$student['id']]);
$recent_reps = $stmt->fetchAll();

// =============================================
// جلب آخر الرسائل
// =============================================
$stmt = $pdo->prepare("
    SELECT m.*,
           CASE 
               WHEN m.sender_type = 'training_entity' THEN (SELECT entity_name_ar FROM training_entities WHERE user_id = m.sender_id)
               WHEN m.sender_type = 'supervisor' THEN (SELECT full_name_ar FROM supervisors WHERE user_id = m.sender_id)
               WHEN m.sender_type = 'coordinator' THEN 'منسقة التدريب'
           END as sender_name
    FROM messages m
    WHERE m.receiver_id = ? AND m.receiver_type = 'student'
    ORDER BY m.created_at DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recent_msgs = $stmt->fetchAll();

// =============================================
// جلب آخر الإشعارات
// =============================================
$stmt = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? AND user_type = 'student' 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recent_notifications = $stmt->fetchAll();

$page_title = 'لوحة التحكم';
require_once '../../includes/student_header.php';
?>

<div class="content">
    <!-- ========================================= -->
    <!-- الترحيب -->
    <!-- ========================================= -->
    <div class="welcome-card">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2>مرحباً، <?php echo htmlspecialchars($student['full_name_ar']); ?> 👋</h2>
                <p class="mb-2">
                    <i class="fas fa-id-card ms-2"></i> الرقم الجامعي: <?php echo $student['student_id']; ?>
                    <?php if($student['major_name_ar']): ?>
                    | <i class="fas fa-graduation-cap ms-2"></i> <?php echo $student['major_name_ar']; ?>
                    | <i class="fas fa-star ms-2"></i> المعدل: <?php echo $student['gpa']; ?>
                    <?php endif; ?>
                </p>
                <p class="mb-0 text-white-50">
                    <?php 
                    $days = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
                    echo $days[date('w')] . ' ' . date('d F Y'); 
                    ?> - <?php echo date('h:i A'); ?>
                </p>
            </div>
            <div class="col-md-4 text-md-left mt-3 mt-md-0">
                <?php if($active_application): ?>
                <div class="bg-white bg-opacity-25 p-3 rounded">
                    <strong>التدريب الحالي:</strong>
                    <p class="mb-0"><?php echo htmlspecialchars($active_application['title_ar']); ?></p>
                    <small><?php echo htmlspecialchars($active_application['entity_name_ar']); ?></small>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- ========================================= -->
    <!-- إحصائيات سريعة (مرتبة في 4 أعمدة) -->
    <!-- ========================================= -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card" onclick="window.location.href='applications.php'">
                <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['total_applications']; ?></div>
                    <div class="stat-label">إجمالي الطلبات</div>
                    <?php if($stats['pending_applications'] > 0): ?>
                    <span class="badge bg-warning mt-2"><?php echo $stats['pending_applications']; ?> قيد المراجعة</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card" onclick="window.location.href='applications.php?status=approved'">
                <div class="stat-icon" style="background: linear-gradient(135deg, #28a745, #20c997);"><i class="fas fa-check-circle"></i></div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['accepted_applications']; ?></div>
                    <div class="stat-label">طلبات مقبولة</div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card" onclick="window.location.href='attendance.php'">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ffc107, #fd7e14);"><i class="fas fa-clock"></i></div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['present_days']; ?></div>
                    <div class="stat-label">أيام الحضور</div>
                    <small class="text-muted"><?php echo $stats['total_hours']; ?> ساعة</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card" onclick="window.location.href='reports.php'">
                <div class="stat-icon" style="background: linear-gradient(135deg, #17a2b8, #6c757d);"><i class="fas fa-chart-bar"></i></div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['total_reports']; ?></div>
                    <div class="stat-label">التقارير</div>
                    <small class="text-success"><?php echo $stats['approved_reports']; ?> معتمد</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ========================================= -->
    <!-- صف الإجراءات السريعة (مرتب في 4 أعمدة) -->
    <!-- ========================================= -->
    <div class="row mb-4">
        <div class="col-md-3">
            <a href="opportunities.php" class="action-card">
                <div class="action-icon"><i class="fas fa-search"></i></div>
                <div class="action-info">
                    <h5>استعراض الفرص</h5>
                    <p>تصفح فرص التدريب المتاحة</p>
                </div>
                <div class="action-arrow">
                    <i class="fas fa-arrow-left"></i>
                </div>
            </a>
        </div>
        
        <div class="col-md-3">
            <a href="applications.php?action=create" class="action-card">
                <div class="action-icon"><i class="fas fa-paper-plane"></i></div>
                <div class="action-info">
                    <h5>تقديم طلب</h5>
                    <p>قدم طلب تدريب جديد</p>
                </div>
                <div class="action-arrow">
                    <i class="fas fa-arrow-left"></i>
                </div>
            </a>
        </div>
        
        <?php if($active_application): ?>
        <div class="col-md-3">
            <a href="attendance.php" class="action-card">
                <div class="action-icon"><i class="fas fa-clock"></i></div>
                <div class="action-info">
                    <h5>سجل الحضور</h5>
                    <p>عرض سجل حضورك</p>
                </div>
                <div class="action-arrow">
                    <i class="fas fa-arrow-left"></i>
                </div>
            </a>
        </div>
        
        <div class="col-md-3">
            <a href="reports.php?action=create" class="action-card">
                <div class="action-icon"><i class="fas fa-upload"></i></div>
                <div class="action-info">
                    <h5>رفع تقرير</h5>
                    <p>ارفع تقريرك الأسبوعي</p>
                </div>
                <div class="action-arrow">
                    <i class="fas fa-arrow-left"></i>
                </div>
            </a>
        </div>
        <?php else: ?>
        <div class="col-md-3">
            <a href="opportunities.php" class="action-card">
                <div class="action-icon"><i class="fas fa-briefcase"></i></div>
                <div class="action-info">
                    <h5>ابحث عن تدريب</h5>
                    <p>ابدأ رحلة التدريب</p>
                </div>
                <div class="action-arrow">
                    <i class="fas fa-arrow-left"></i>
                </div>
            </a>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- ========================================= -->
    <!-- صف المعلومات (3 أعمدة متساوية) -->
    <!-- ========================================= -->
    <div class="row">
        <!-- آخر الطلبات -->
        <div class="col-md-4">
            <div class="recent-card">
                <div class="card-header">
                    <h5>
                        <i class="fas fa-history ms-2" style="color: var(--primary);"></i>
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
                                <i class="fas fa-briefcase"></i>
                            </div>
                            <div class="item-content">
                                <h6><?php echo htmlspecialchars($app['title_ar']); ?></h6>
                                <div class="item-meta">
                                    <span><i class="fas fa-building"></i> <?php echo $app['entity_name_ar']; ?></span>
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
        
        <!-- آخر التقارير -->
        <div class="col-md-4">
            <div class="recent-card">
                <div class="card-header">
                    <h5>
                        <i class="fas fa-file-alt ms-2" style="color: var(--primary);"></i>
                        آخر التقارير
                    </h5>
                    <a href="reports.php" class="btn-link">عرض الكل</a>
                </div>
                <div class="card-body">
                    <?php if(empty($recent_reps)): ?>
                        <div class="empty-state">
                            <i class="fas fa-file-alt"></i>
                            <p>لا توجد تقارير</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($recent_reps as $r): ?>
                        <div class="recent-item" onclick="window.location.href='reports.php?action=view&id=<?php echo $r['id']; ?>'">
                            <div class="item-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="item-content">
                                <h6>التقرير الأسبوعي <?php echo $r['week_number']; ?></h6>
                                <div class="item-meta">
                                    <span><i class="fas fa-calendar"></i> <?php echo date('Y-m-d', strtotime($r['created_at'])); ?></span>
                                </div>
                            </div>
                            <div class="item-status">
                                <?php
                                $report_status_colors = [
                                    'draft' => 'secondary',
                                    'submitted' => 'info',
                                    'reviewed' => 'warning',
                                    'approved' => 'success'
                                ];
                                ?>
                                <span class="badge bg-<?php echo $report_status_colors[$r['status']]; ?>">
                                    <?php echo $r['status']; ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- آخر الرسائل والإشعارات -->
        <div class="col-md-4">
            <div class="recent-card messages-card">
                <div class="card-header">
                    <h5>
                        <i class="fas fa-envelope ms-2" style="color: var(--primary);"></i>
                        آخر الرسائل
                        <?php if($stats['unread_messages'] > 0): ?>
                        <span class="unread-badge"><?php echo $stats['unread_messages']; ?> جديدة</span>
                        <?php endif; ?>
                    </h5>
                    <a href="messages.php" class="btn-link">عرض الكل</a>
                </div>
                <div class="card-body" style="max-height: 250px; overflow-y: auto;">
                    <?php if(empty($recent_msgs)): ?>
                        <div class="empty-state">
                            <i class="fas fa-envelope-open-text"></i>
                            <p>لا توجد رسائل</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($recent_msgs as $msg): ?>
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
            
            <!-- آخر الإشعارات -->
            <div class="recent-card notifications-card mt-3">
                <div class="card-header">
                    <h5>
                        <i class="fas fa-bell ms-2" style="color: var(--primary);"></i>
                        آخر الإشعارات
                        <?php if($stats['unread_notifications'] > 0): ?>
                        <span class="unread-badge"><?php echo $stats['unread_notifications']; ?> جديدة</span>
                        <?php endif; ?>
                    </h5>
                    <a href="notifications.php" class="btn-link">عرض الكل</a>
                </div>
                <div class="card-body" style="max-height: 200px; overflow-y: auto;">
                    <?php if(empty($recent_notifications)): ?>
                        <div class="empty-state">
                            <i class="fas fa-bell-slash"></i>
                            <p>لا توجد إشعارات</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($recent_notifications as $note): ?>
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
                                    <small>
                                        <?php 
                                        $time = strtotime($note['created_at']);
                                        if(date('Y-m-d') == date('Y-m-d', $time)) {
                                            echo 'اليوم';
                                        } elseif(date('Y-m-d', strtotime('-1 day')) == date('Y-m-d', $time)) {
                                            echo 'أمس';
                                        } else {
                                            echo date('Y-m-d', $time);
                                        }
                                        ?>
                                    </small>
                                </div>
                                <p class="notification-message"><?php echo htmlspecialchars($note['message']); ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ========================================= -->
    <!-- معلومات التدريب النشط -->
    <!-- ========================================= -->
    <?php if($active_application): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="active-training-card">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="mb-3">
                            <i class="fas fa-briefcase ms-2"></i>
                            التدريب الحالي
                        </h5>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <strong>الفرصة:</strong> <?php echo htmlspecialchars($active_application['title_ar']); ?>
                            </div>
                            <div class="col-md-6 mb-2">
                                <strong>الجهة:</strong> <?php echo htmlspecialchars($active_application['entity_name_ar']); ?>
                            </div>
                            <div class="col-md-6 mb-2">
                                <strong>تاريخ البدء:</strong> <?php echo $active_application['start_date'] ? date('Y-m-d', strtotime($active_application['start_date'])) : 'غير محدد'; ?>
                            </div>
                            <div class="col-md-6 mb-2">
                                <strong>تاريخ الانتهاء:</strong> <?php echo $active_application['end_date'] ? date('Y-m-d', strtotime($active_application['end_date'])) : 'غير محدد'; ?>
                            </div>
                            <?php if($active_application['supervisor_name']): ?>
                            <div class="col-12 mb-2">
                                <strong>المشرف:</strong> <?php echo htmlspecialchars($active_application['supervisor_name']); ?> 
                                (<?php echo $active_application['supervisor_phone']; ?>)
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-left mt-3 mt-md-0">
                        <div class="training-progress">
                            <?php
                            $start = strtotime($active_application['start_date']);
                            $end = strtotime($active_application['end_date']);
                            $today = time();
                            $progress = 0;
                            
                            if($start && $end && $today >= $start) {
                                $total = ($end - $start) / (60 * 60 * 24);
                                $elapsed = ($today - $start) / (60 * 60 * 24);
                                if($total > 0) {
                                    $progress = min(100, round(($elapsed / $total) * 100));
                                }
                            }
                            ?>
                            <div class="progress mb-2" style="height: 10px;">
                                <div class="progress-bar" style="width: <?php echo $progress; ?>%;"></div>
                            </div>
                            <p class="mb-0 text-center">تقدم التدريب: <?php echo $progress; ?>%</p>
                            <a href="attendance.php" class="btn-primary-custom btn-sm w-100 mt-3">
                                <i class="fas fa-clock ms-2"></i>
                                عرض سجل الحضور
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
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
    transition: all 0.3s ease;
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
    color: var(--dark);
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
    display: flex;
    flex-direction: column;
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
    color: var(--dark);
}

.recent-card .card-body {
    padding: 10px;
    flex: 1;
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
    color: var(--dark);
}

.item-meta {
    font-size: 0.75rem;
    color: #6c757d;
    display: flex;
    gap: 10px;
    margin-top: 2px;
    flex-wrap: wrap;
}

.item-meta span, .item-meta .date {
    display: flex;
    align-items: center;
    gap: 3px;
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

.unread-badge {
    background: #dc3545;
    color: white;
    font-size: 0.7rem;
    padding: 2px 8px;
    border-radius: 50px;
    margin-right: 8px;
}

.active-training-card {
    background: var(--gradient);
    color: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 10px 30px rgba(0,102,51,0.2);
}

.active-training-card .progress {
    background: rgba(255,255,255,0.3);
    height: 10px;
    border-radius: 10px;
}

.active-training-card .progress-bar {
    background: white;
}

.message-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-bottom: 5px;
}

.message-item:hover {
    background: #f8f9fa;
}

.message-item.unread {
    background: #f0f7f0;
}

.message-avatar {
    flex-shrink: 0;
}

.message-avatar .avatar {
    width: 35px;
    height: 35px;
    background: var(--gradient);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
}

.message-content {
    flex: 1;
}

.message-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2px;
}

.message-header strong {
    font-size: 0.85rem;
    color: var(--dark);
}

.message-header small {
    font-size: 0.7rem;
    color: #6c757d;
}

.message-subject {
    font-size: 0.8rem;
    color: #6c757d;
}

.notification-item {
    display: flex;
    gap: 10px;
    padding: 8px;
    border-radius: 8px;
    margin-bottom: 5px;
    transition: all 0.3s ease;
    align-items: flex-start;
}

.notification-item.unread {
    background: #f0f7f0;
}

.notification-icon {
    width: 25px;
    font-size: 16px;
    text-align: center;
    flex-shrink: 0;
}

.notification-content {
    flex: 1;
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2px;
}

.notification-header strong {
    font-size: 0.8rem;
    color: var(--dark);
}

.notification-header small {
    font-size: 0.65rem;
    color: #6c757d;
}

.notification-message {
    font-size: 0.75rem;
    color: #6c757d;
    margin: 0;
}

.btn-primary-custom {
    background: linear-gradient(135deg, #006633, #228B22);
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    font-size: 0.9rem;
}

.btn-primary-custom:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,102,51,0.3);
    color: white;
}

.badge {
    font-size: 0.7rem;
    padding: 3px 8px;
}
</style>

<?php require_once '../../includes/student_footer.php'; ?>