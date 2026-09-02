 <?php
// modules/coordinator/reports.php - التقارير والإحصائيات الشاملة
session_start();
require_once '../../config/config.php';

// =============================================
// التحقق من تسجيل الدخول
// =============================================
if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'coordinator') {
    header('Location: ../auth/login.php?type=coordinator');
    exit();
}

// جلب بيانات المنسقة
$stmt = $pdo->prepare("SELECT * FROM coordinators WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$coordinator = $stmt->fetch();

if(!$coordinator) {
    header('Location: ../auth/logout.php');
    exit();
}

// =============================================
// معالجة تغيير نوع التقرير
// =============================================
$report_type = isset($_GET['type']) ? $_GET['type'] : 'dashboard';

// =============================================
// إحصائيات عامة (لجميع التقارير)
// =============================================
$stats = [];

// الجهات التدريبية
$stats['total_entities'] = $pdo->query("SELECT COUNT(*) FROM training_entities")->fetchColumn();
$stats['approved_entities'] = $pdo->query("SELECT COUNT(*) FROM training_entities WHERE is_approved = 1")->fetchColumn();
$stats['pending_entities'] = $stats['total_entities'] - $stats['approved_entities'];

// المتدربات
$stats['total_students'] = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$stats['graduating'] = $pdo->query("SELECT COUNT(*) FROM students WHERE is_graduating = 1 OR expected_graduation <= DATE_ADD(NOW(), INTERVAL 6 MONTH)")->fetchColumn();
$stats['active_students'] = $pdo->query("SELECT COUNT(DISTINCT student_id) FROM applications WHERE status = 'approved'")->fetchColumn();

// الطلبات
$stats['total_apps'] = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
$stats['pending_apps'] = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'pending'")->fetchColumn();
$stats['approved_apps'] = $pdo->query("SELECT COUNT(*) FROM applications WHERE status IN ('approved', 'accepted_by_entity')")->fetchColumn();
$stats['rejected_apps'] = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'rejected'")->fetchColumn();
$stats['interview_apps'] = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'interview_scheduled'")->fetchColumn();

// التدريب
$stats['active_training'] = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'approved'")->fetchColumn();
$stats['completed_training'] = $pdo->query("SELECT COUNT(*) FROM evaluations WHERE training_completed = 1")->fetchColumn();

// =============================================
// بيانات إضافية حسب نوع التقرير
// =============================================
$monthly_stats = [];
$top_majors = [];
$top_entities = [];
$level_distribution = [];
$attendance_stats = [];
$interview_stats = [];

if($report_type == 'dashboard' || $report_type == 'applications') {
    $monthly_stats = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status IN ('approved', 'accepted_by_entity') THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM applications
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
        LIMIT 12
    ")->fetchAll();
}

if($report_type == 'dashboard' || $report_type == 'students') {
    $top_majors = $pdo->query("
        SELECT 
            m.major_name_ar,
            COUNT(a.id) as count
        FROM applications a
        JOIN students s ON a.student_id = s.id
        JOIN majors m ON s.major_id = m.id
        GROUP BY m.id
        ORDER BY count DESC
        LIMIT 10
    ")->fetchAll();

    $level_distribution = $pdo->query("
        SELECT 
            academic_level,
            COUNT(*) as count
        FROM students
        WHERE academic_level IS NOT NULL
        GROUP BY academic_level
        ORDER BY academic_level
    ")->fetchAll();
}

if($report_type == 'dashboard' || $report_type == 'entities') {
    $top_entities = $pdo->query("
        SELECT 
            te.entity_name_ar,
            COUNT(a.id) as applications_count,
            COUNT(DISTINCT a.student_id) as students_count
        FROM applications a
        JOIN training_seats ts ON a.seat_id = ts.id
        JOIN training_entities te ON ts.entity_id = te.id
        GROUP BY te.id
        ORDER BY applications_count DESC
        LIMIT 10
    ")->fetchAll();
}

if($report_type == 'dashboard' || $report_type == 'attendance') {
    $attendance_stats = $pdo->query("
        SELECT 
            COUNT(*) as total_records,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
            COALESCE(SUM(total_hours), 0) as total_hours
        FROM attendance
    ")->fetch();

    $interview_stats = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
            SUM(CASE WHEN result = 'passed' THEN 1 ELSE 0 END) as passed
        FROM interviews
    ")->fetch();
}

$page_title = 'التقارير والإحصائيات';
require_once '../../includes/coordinator_header.php';
?>

<div class="content">
    <!-- ========================================= -->
    <!-- رأس الصفحة -->
    <!-- ========================================= -->
    <div class="page-header">
        <div>
            <h4 class="mb-1">التقارير والإحصائيات</h4>
            <p class="text-muted">نظرة شاملة وتحليلات متقدمة للنظام</p>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-primary p-3">
                <i class="fas fa-calendar ms-2"></i> <?php echo date('Y'); ?>
            </span>
        </div>
    </div>

    <!-- ========================================= -->
    <!-- تبويبات التقارير - باستخدام GET parameters -->
    <!-- ========================================= -->
    <div class="report-tabs mb-4">
        <a href="?type=dashboard" class="btn <?php echo $report_type == 'dashboard' ? 'btn-primary-custom' : 'btn-outline-custom'; ?> me-2">
            <i class="fas fa-home ms-2"></i> لوحة المؤشرات
        </a>
        <a href="?type=applications" class="btn <?php echo $report_type == 'applications' ? 'btn-primary-custom' : 'btn-outline-custom'; ?> me-2">
            <i class="fas fa-file-alt ms-2"></i> تقارير الطلبات
        </a>
        <a href="?type=students" class="btn <?php echo $report_type == 'students' ? 'btn-primary-custom' : 'btn-outline-custom'; ?> me-2">
            <i class="fas fa-user-graduate ms-2"></i> تقارير المتدربات
        </a>
        <a href="?type=entities" class="btn <?php echo $report_type == 'entities' ? 'btn-primary-custom' : 'btn-outline-custom'; ?> me-2">
            <i class="fas fa-building ms-2"></i> تقارير الجهات
        </a>
        <a href="?type=attendance" class="btn <?php echo $report_type == 'attendance' ? 'btn-primary-custom' : 'btn-outline-custom'; ?>">
            <i class="fas fa-clock ms-2"></i> تقارير الحضور
        </a>
    </div>

    <!-- ========================================= -->
    <!-- محتوى حسب نوع التقرير -->
    <!-- ========================================= -->
    
    <?php if($report_type == 'dashboard'): ?>
        <!-- ========================================= -->
        <!-- لوحة المؤشرات الرئيسية -->
        <!-- ========================================= -->
        
        <!-- بطاقات المؤشرات الرئيسية -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="kpi-card">
                    <div class="kpi-icon" style="background: linear-gradient(135deg, #006633, #228B22);">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="kpi-content">
                        <div class="kpi-value"><?php echo $stats['total_entities']; ?></div>
                        <div class="kpi-label">إجمالي الجهات</div>
                        <div class="kpi-detail">
                            <span class="text-success"><?php echo $stats['approved_entities']; ?> معتمدة</span>
                            <span class="text-warning"><?php echo $stats['pending_entities']; ?> في الانتظار</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="kpi-card">
                    <div class="kpi-icon" style="background: linear-gradient(135deg, #228B22, #32CD32);">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="kpi-content">
                        <div class="kpi-value"><?php echo $stats['total_students']; ?></div>
                        <div class="kpi-label">إجمالي المتدربات</div>
                        <div class="kpi-detail">
                            <span class="text-success"><?php echo $stats['active_students']; ?> متدربة</span>
                            <span class="text-info"><?php echo $stats['graduating']; ?> متخرجة</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="kpi-card">
                    <div class="kpi-icon" style="background: linear-gradient(135deg, #32CD32, #90EE90);">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="kpi-content">
                        <div class="kpi-value"><?php echo $stats['total_apps']; ?></div>
                        <div class="kpi-label">إجمالي الطلبات</div>
                        <div class="kpi-detail">
                            <span class="text-success"><?php echo $stats['approved_apps']; ?> مقبول</span>
                            <span class="text-warning"><?php echo $stats['pending_apps']; ?> جديد</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="kpi-card">
                    <div class="kpi-icon" style="background: linear-gradient(135deg, #90EE90, #006633);">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="kpi-content">
                        <div class="kpi-value"><?php echo $stats['active_training']; ?></div>
                        <div class="kpi-label">متدربات حالياً</div>
                        <div class="kpi-detail">
                            <span class="text-success"><?php echo $stats['completed_training']; ?> مكتمل</span>
                            <span class="text-info"><?php echo $stats['interview_apps']; ?> مقابلة</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- صف التحليلات -->
        <div class="row">
            <div class="col-lg-6">
                <div class="analysis-card">
                    <div class="analysis-header">
                        <h5><i class="fas fa-graduation-cap ms-2"></i> أكثر التخصصات طلباً</h5>
                    </div>
                    <div class="analysis-body">
                        <?php if(empty($top_majors)): ?>
                            <p class="text-muted text-center py-4">لا توجد بيانات كافية</p>
                        <?php else: ?>
                            <?php foreach($top_majors as $major): ?>
                            <div class="progress-item">
                                <div class="d-flex justify-content-between mb-1">
                                    <span><?php echo $major['major_name_ar']; ?></span>
                                    <span class="badge bg-primary"><?php echo $major['count']; ?> طلب</span>
                                </div>
                                <div class="progress">
                                    <?php $width = ($major['count'] / $top_majors[0]['count']) * 100; ?>
                                    <div class="progress-bar" style="width: <?php echo $width; ?>%;"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="analysis-card">
                    <div class="analysis-header">
                        <h5><i class="fas fa-building ms-2"></i> أكثر الجهات نشاطاً</h5>
                    </div>
                    <div class="analysis-body">
                        <?php if(empty($top_entities)): ?>
                            <p class="text-muted text-center py-4">لا توجد بيانات كافية</p>
                        <?php else: ?>
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>الجهة</th>
                                        <th>الطلبات</th>
                                        <th>المتدربات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($top_entities as $entity): ?>
                                    <tr>
                                        <td><?php echo $entity['entity_name_ar']; ?></td>
                                        <td><span class="badge bg-info"><?php echo $entity['applications_count']; ?></span></td>
                                        <td><span class="badge bg-success"><?php echo $entity['students_count']; ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif($report_type == 'applications'): ?>
        <!-- ========================================= -->
        <!-- تقارير الطلبات -->
        <!-- ========================================= -->
        
        <div class="row">
            <div class="col-md-6">
                <div class="stats-card">
                    <h5 class="mb-3">ملخص الطلبات</h5>
                    <table class="table table-borderless">
                        <tr>
                            <td>إجمالي الطلبات:</td>
                            <td><strong><?php echo $stats['total_apps']; ?></strong></td>
                        </tr>
                        <tr>
                            <td>الطلبات الجديدة:</td>
                            <td><span class="badge bg-warning p-2"><?php echo $stats['pending_apps']; ?></span></td>
                        </tr>
                        <tr>
                            <td>الطلبات المقبولة:</td>
                            <td><span class="badge bg-success p-2"><?php echo $stats['approved_apps']; ?></span></td>
                        </tr>
                        <tr>
                            <td>الطلبات المرفوضة:</td>
                            <td><span class="badge bg-danger p-2"><?php echo $stats['rejected_apps']; ?></span></td>
                        </tr>
                        <tr>
                            <td>مقابلات مجدولة:</td>
                            <td><span class="badge bg-primary p-2"><?php echo $stats['interview_apps']; ?></span></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="stats-card">
                    <h5 class="mb-3">معدلات القبول</h5>
                    <?php
                    $acceptance_rate = $stats['total_apps'] > 0 ? round(($stats['approved_apps'] / $stats['total_apps']) * 100, 1) : 0;
                    $rejection_rate = $stats['total_apps'] > 0 ? round(($stats['rejected_apps'] / $stats['total_apps']) * 100, 1) : 0;
                    $pending_rate = $stats['total_apps'] > 0 ? round(($stats['pending_apps'] / $stats['total_apps']) * 100, 1) : 0;
                    ?>
                    <div class="rate-item mb-3">
                        <div class="d-flex justify-content-between">
                            <span>نسبة القبول</span>
                            <strong><?php echo $acceptance_rate; ?>%</strong>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-success" style="width: <?php echo $acceptance_rate; ?>%;"></div>
                        </div>
                    </div>
                    <div class="rate-item mb-3">
                        <div class="d-flex justify-content-between">
                            <span>نسبة الرفض</span>
                            <strong><?php echo $rejection_rate; ?>%</strong>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-danger" style="width: <?php echo $rejection_rate; ?>%;"></div>
                        </div>
                    </div>
                    <div class="rate-item">
                        <div class="d-flex justify-content-between">
                            <span>قيد الانتظار</span>
                            <strong><?php echo $pending_rate; ?>%</strong>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-warning" style="width: <?php echo $pending_rate; ?>%;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="table-card">
                    <h5 class="mb-3">الطلبات الشهرية</h5>
                    <?php if(empty($monthly_stats)): ?>
                        <p class="text-muted text-center py-4">لا توجد بيانات كافية</p>
                    <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>الشهر</th>
                                <th>الإجمالي</th>
                                <th>جديد</th>
                                <th>مقبول</th>
                                <th>مرفوض</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($monthly_stats as $m): ?>
                            <tr>
                                <td><?php echo $m['month']; ?></td>
                                <td><?php echo $m['total']; ?></td>
                                <td><span class="badge bg-warning"><?php echo $m['pending']; ?></span></td>
                                <td><span class="badge bg-success"><?php echo $m['approved']; ?></span></td>
                                <td><span class="badge bg-danger"><?php echo $m['rejected']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php elseif($report_type == 'students'): ?>
        <!-- ========================================= -->
        <!-- تقارير المتدربات -->
        <!-- ========================================= -->
        
        <div class="row">
            <div class="col-md-4">
                <div class="stats-card">
                    <h5 class="mb-3">إحصائيات المتدربات</h5>
                    <div class="stat-circle text-center mb-4">
                        <div class="big-number"><?php echo $stats['total_students']; ?></div>
                        <p>إجمالي المتدربات</p>
                    </div>
                    <table class="table table-borderless">
                        <tr>
                            <td>متدربات حالياً:</td>
                            <td><strong><?php echo $stats['active_students']; ?></strong></td>
                        </tr>
                        <tr>
                            <td>متخرجة قريباً:</td>
                            <td><strong><?php echo $stats['graduating']; ?></strong></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="stats-card">
                    <h5 class="mb-3">توزيع المتدربات حسب التخصص</h5>
                    <?php if(empty($top_majors)): ?>
                        <p class="text-muted text-center py-4">لا توجد بيانات كافية</p>
                    <?php else: ?>
                        <?php foreach($top_majors as $major): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><?php echo $major['major_name_ar']; ?></span>
                            <span class="badge bg-primary"><?php echo $major['count']; ?> متدربة</span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php elseif($report_type == 'entities'): ?>
        <!-- ========================================= -->
        <!-- تقارير الجهات -->
        <!-- ========================================= -->
        
        <div class="row">
            <div class="col-md-6">
                <div class="stats-card">
                    <h5 class="mb-3">ملخص الجهات</h5>
                    <table class="table table-borderless">
                        <tr>
                            <td>إجمالي الجهات:</td>
                            <td><strong><?php echo $stats['total_entities']; ?></strong></td>
                        </tr>
                        <tr>
                            <td>الجهات المعتمدة:</td>
                            <td><span class="badge bg-success p-2"><?php echo $stats['approved_entities']; ?></span></td>
                        </tr>
                        <tr>
                            <td>في انتظار الاعتماد:</td>
                            <td><span class="badge bg-warning p-2"><?php echo $stats['pending_entities']; ?></span></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="stats-card">
                    <h5 class="mb-3">أكثر الجهات نشاطاً</h5>
                    <?php if(empty($top_entities)): ?>
                        <p class="text-muted text-center py-4">لا توجد بيانات كافية</p>
                    <?php else: ?>
                        <?php foreach($top_entities as $entity): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><?php echo $entity['entity_name_ar']; ?></span>
                            <span class="badge bg-info"><?php echo $entity['applications_count']; ?> طلب</span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php elseif($report_type == 'attendance'): ?>
        <!-- ========================================= -->
        <!-- تقارير الحضور -->
        <!-- ========================================= -->
        
        <div class="row">
            <div class="col-md-3">
                <div class="kpi-card small">
                    <div class="kpi-value"><?php echo $attendance_stats['total_records'] ?? 0; ?></div>
                    <div class="kpi-label">إجمالي سجلات الحضور</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="kpi-card small success">
                    <div class="kpi-value"><?php echo $attendance_stats['present'] ?? 0; ?></div>
                    <div class="kpi-label">أيام حضور</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="kpi-card small danger">
                    <div class="kpi-value"><?php echo $attendance_stats['absent'] ?? 0; ?></div>
                    <div class="kpi-label">أيام غياب</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="kpi-card small warning">
                    <div class="kpi-value"><?php echo $attendance_stats['late'] ?? 0; ?></div>
                    <div class="kpi-label">أيام تأخير</div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="stats-card">
                    <h5 class="mb-3">إحصائيات الحضور</h5>
                    <?php
                    $total = $attendance_stats['total_records'] ?? 0;
                    $present = $attendance_stats['present'] ?? 0;
                    $absent = $attendance_stats['absent'] ?? 0;
                    $late = $attendance_stats['late'] ?? 0;
                    
                    $present_rate = $total > 0 ? round(($present / $total) * 100, 1) : 0;
                    $absent_rate = $total > 0 ? round(($absent / $total) * 100, 1) : 0;
                    $late_rate = $total > 0 ? round(($late / $total) * 100, 1) : 0;
                    ?>
                    <div class="rate-item mb-3">
                        <div class="d-flex justify-content-between">
                            <span>نسبة الحضور</span>
                            <strong><?php echo $present_rate; ?>%</strong>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-success" style="width: <?php echo $present_rate; ?>%;"></div>
                        </div>
                    </div>
                    <div class="rate-item mb-3">
                        <div class="d-flex justify-content-between">
                            <span>نسبة الغياب</span>
                            <strong><?php echo $absent_rate; ?>%</strong>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-danger" style="width: <?php echo $absent_rate; ?>%;"></div>
                        </div>
                    </div>
                    <div class="rate-item">
                        <div class="d-flex justify-content-between">
                            <span>نسبة التأخير</span>
                            <strong><?php echo $late_rate; ?>%</strong>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-warning" style="width: <?php echo $late_rate; ?>%;"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="stats-card">
                    <h5 class="mb-3">إحصائيات المقابلات</h5>
                    <table class="table">
                        <tr>
                            <td>إجمالي المقابلات:</td>
                            <td><strong><?php echo $interview_stats['total'] ?? 0; ?></strong></td>
                        </tr>
                        <tr>
                            <td>مقابلات منجزة:</td>
                            <td><span class="badge bg-success"><?php echo $interview_stats['completed'] ?? 0; ?></span></td>
                        </tr>
                        <tr>
                            <td>مقابلات مجدولة:</td>
                            <td><span class="badge bg-primary"><?php echo $interview_stats['scheduled'] ?? 0; ?></span></td>
                        </tr>
                        <tr>
                            <td>ناجح في المقابلة:</td>
                            <td><span class="badge bg-info"><?php echo $interview_stats['passed'] ?? 0; ?></span></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    

<!-- CSS مخصص للصفحة -->
<style>
/* بطاقات KPI */
.kpi-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 5px 15px rgba(0,102,51,0.05);
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 20px;
    transition: all 0.3s ease;
}

.kpi-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,102,51,0.1);
}

.kpi-card.small {
    padding: 15px;
    flex-direction: column;
    text-align: center;
    gap: 10px;
}

.kpi-card.small.success .kpi-value { color: #28a745; }
.kpi-card.small.danger .kpi-value { color: #dc3545; }
.kpi-card.small.warning .kpi-value { color: #ffc107; }

.kpi-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}

.kpi-content {
    flex: 1;
}

.kpi-value {
    font-size: 2rem;
    font-weight: 800;
    color: var(--dark);
    line-height: 1;
    margin-bottom: 5px;
}

.kpi-label {
    color: #6c757d;
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 5px;
}

.kpi-detail {
    font-size: 0.8rem;
}

.kpi-detail span {
    margin-left: 10px;
}

/* بطاقات التحليل */
.analysis-card {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,102,51,0.05);
    margin-bottom: 20px;
}

.analysis-header {
    padding: 15px 20px;
    border-bottom: 1px solid #edf2f7;
    background: #f8f9fa;
}

.analysis-header h5 {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: var(--dark);
}

.analysis-body {
    padding: 20px;
}

.progress-item {
    margin-bottom: 15px;
}

.progress-item:last-child {
    margin-bottom: 0;
}

.progress {
    height: 8px;
    border-radius: 4px;
    background: #e9ecef;
}

.progress-bar {
    background: var(--gradient);
    border-radius: 4px;
}

/* بطاقات الإحصائيات */
.stats-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 5px 15px rgba(0,102,51,0.05);
    margin-bottom: 20px;
}

.table-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 5px 15px rgba(0,102,51,0.05);
}

.export-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 5px 15px rgba(0,102,51,0.05);
}

.stat-circle {
    padding: 20px;
}

.big-number {
    font-size: 4rem;
    font-weight: 800;
    color: var(--primary);
    line-height: 1;
}

.rate-item {
    margin-bottom: 15px;
}

.report-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}

.report-tabs .btn {
    border-radius: 50px;
    padding: 8px 20px;
    font-size: 0.9rem;
}

.table td {
    vertical-align: middle;
}

/* تنسيق للأزرار */
.btn-outline-custom {
    background: transparent;
    color: var(--primary);
    border: 2px solid var(--primary);
    padding: 8px 20px;
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

.btn-primary-custom {
    background: var(--gradient);
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
}
</style>

<!-- سكربت بسيط للتمرير لأعلى الصفحة -->
<script>
// التمرير لأعلى الصفحة عند تغيير التقرير
document.querySelectorAll('.report-tabs a').forEach(link => {
    link.addEventListener('click', function() {
        setTimeout(() => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }, 50);
    });
});
</script>

<?php require_once '../../includes/coordinator_footer.php'; ?>