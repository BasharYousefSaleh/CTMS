<?php
// modules/coordinator/students.php - إدارة المتدربات
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
// تحديد الصفحة الحالية
// =============================================
$action = $_GET['action'] ?? 'list';
$student_id = $_GET['id'] ?? 0;
$major_filter = $_GET['major'] ?? 'all';
$level_filter = $_GET['level'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';

// =============================================
// جلب التخصصات للفلتر
// =============================================
$majors = $pdo->query("SELECT * FROM majors WHERE is_active = 1 ORDER BY major_name_ar")->fetchAll();

// =============================================
// جلب المتدربات
// =============================================
$sql = "
    SELECT s.*, 
           m.major_name_ar,
           (SELECT COUNT(*) FROM applications WHERE student_id = s.id) as apps_count,
           (SELECT COUNT(*) FROM applications WHERE student_id = s.id AND status = 'approved') as accepted_count,
           (SELECT COUNT(*) FROM applications WHERE student_id = s.id AND status = 'pending') as pending_count,
           (SELECT COUNT(*) FROM applications WHERE student_id = s.id AND status = 'interview_scheduled') as interview_count
    FROM students s
    LEFT JOIN majors m ON s.major_id = m.id
    WHERE 1=1
";
$params = [];

if($major_filter != 'all') {
    $sql .= " AND s.major_id = ?";
    $params[] = $major_filter;
}

if($level_filter != 'all') {
    $sql .= " AND s.academic_level = ?";
    $params[] = $level_filter;
}

if($status_filter == 'graduating') {
    $sql .= " AND (s.is_graduating = 1 OR s.expected_graduation <= DATE_ADD(NOW(), INTERVAL 6 MONTH))";
} elseif($status_filter == 'active') {
    $sql .= " AND EXISTS (SELECT 1 FROM applications WHERE student_id = s.id AND status = 'approved')";
}

$sql .= " ORDER BY s.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// إحصائيات
$stats = [
    'total' => count($students),
    'graduating' => count(array_filter($students, fn($s) => $s['is_graduating'] == 1 || (!empty($s['expected_graduation']) && strtotime($s['expected_graduation']) <= strtotime('+6 months')))),
    'active' => count(array_filter($students, fn($s) => $s['accepted_count'] > 0)),
    'new' => count(array_filter($students, fn($s) => strtotime($s['created_at']) >= strtotime('-30 days'))),
];

// إذا كان عرض تفاصيل متدربة
if($action == 'view' && $student_id > 0) {
    $stmt = $pdo->prepare("
        SELECT s.*, 
               m.major_name_ar,
               u.email
        FROM students s
        LEFT JOIN majors m ON s.major_id = m.id
        JOIN users u ON s.user_id = u.id
        WHERE s.id = ?
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    if(!$student) {
        header('Location: students.php');
        exit();
    }
    
    // جلب طلبات المتدربة
    $applications = $pdo->prepare("
        SELECT a.*, 
               ts.title_ar, 
               te.entity_name_ar,
               sup.full_name_ar as supervisor_name
        FROM applications a
        JOIN training_seats ts ON a.seat_id = ts.id
        JOIN training_entities te ON ts.entity_id = te.id
        LEFT JOIN supervisors sup ON a.supervisor_id = sup.id
        WHERE a.student_id = ?
        ORDER BY a.created_at DESC
    ");
    $applications->execute([$student_id]);
    $student_apps = $applications->fetchAll();
    
    // جلب حضور المتدربة
    $attendance = $pdo->prepare("
        SELECT * FROM attendance 
        WHERE student_id = ?
        ORDER BY attendance_date DESC
        LIMIT 10
    ");
    $attendance->execute([$student_id]);
    $attendance_records = $attendance->fetchAll();
    
    // إحصائيات الحضور
    $att_stats = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
            COALESCE(SUM(total_hours), 0) as total_hours
        FROM attendance 
        WHERE student_id = ?
    ");
    $att_stats->execute([$student_id]);
    $attendance_stats = $att_stats->fetch();
}

$page_title = 'المتدربات';
require_once '../../includes/coordinator_header.php';

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>

<div class="content">
    <?php if($action == 'view' && isset($student)): ?>
        <!-- ========================================= -->
        <!-- عرض تفاصيل متدربة -->
        <!-- ========================================= -->
        <div class="back-link mb-3">
            <a href="students.php"><i class="fas fa-arrow-right ms-1"></i> العودة للقائمة</a>
        </div>
        
        <div class="row">
            <div class="col-md-8">
                <!-- المعلومات الشخصية -->
                <div class="card-custom">
                    <h5 class="mb-3">الملف الشخصي للمتدربة</h5>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <small class="text-muted d-block">الاسم الكامل</small>
                            <strong><?php echo htmlspecialchars($student['full_name_ar']); ?></strong>
                        </div>
                        <div class="col-md-6 mb-3">
                            <small class="text-muted d-block">الاسم بالإنجليزية</small>
                            <strong><?php echo htmlspecialchars($student['full_name_en'] ?? '---'); ?></strong>
                        </div>
                        <div class="col-md-4 mb-3">
                            <small class="text-muted d-block">الرقم الجامعي</small>
                            <strong><?php echo $student['student_id']; ?></strong>
                        </div>
                        <div class="col-md-4 mb-3">
                            <small class="text-muted d-block">رقم الهوية</small>
                            <strong><?php echo $student['national_id']; ?></strong>
                        </div>
                        <div class="col-md-4 mb-3">
                            <small class="text-muted d-block">رقم الجوال</small>
                            <strong><?php echo $student['phone'] ?? '---'; ?></strong>
                        </div>
                        <div class="col-md-4 mb-3">
                            <small class="text-muted d-block">البريد الإلكتروني</small>
                            <strong><?php echo $student['email']; ?></strong>
                        </div>
                        <div class="col-md-4 mb-3">
                            <small class="text-muted d-block">التخصص</small>
                            <strong><?php echo $student['major_name_ar'] ?? 'غير محدد'; ?></strong>
                        </div>
                        <div class="col-md-4 mb-3">
                            <small class="text-muted d-block">المعدل التراكمي</small>
                            <strong><?php echo number_format($student['gpa'], 2); ?></strong>
                        </div>
                        <div class="col-md-4 mb-3">
                            <small class="text-muted d-block">المستوى</small>
                            <strong><?php echo $student['academic_level'] ?? '---'; ?></strong>
                        </div>
                        <div class="col-md-4 mb-3">
                            <small class="text-muted d-block">الساعات المكتملة</small>
                            <strong><?php echo $student['total_hours'] ?? '0'; ?></strong>
                        </div>
                        <div class="col-md-4 mb-3">
                            <small class="text-muted d-block">تاريخ التخرج المتوقع</small>
                            <strong><?php echo $student['expected_graduation'] ?? '---'; ?></strong>
                        </div>
                        <div class="col-md-4 mb-3">
                            <small class="text-muted d-block">تاريخ التسجيل</small>
                            <strong><?php echo date('Y-m-d', strtotime($student['created_at'])); ?></strong>
                        </div>
                    </div>
                </div>
                
                <!-- طلبات المتدربة -->
                <div class="card-custom mt-3">
                    <h5 class="mb-3">الطلبات المقدمة</h5>
                    
                    <?php if(empty($student_apps)): ?>
                        <p class="text-muted text-center py-3">لا توجد طلبات</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>الفرصة</th>
                                        <th>الجهة</th>
                                        <th>تاريخ التقديم</th>
                                        <th>الحالة</th>
                                        <th>المشرف</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($student_apps as $app): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($app['title_ar']); ?></td>
                                        <td><?php echo htmlspecialchars($app['entity_name_ar']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($app['created_at'])); ?></td>
                                        <td>
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
                                                'accepted_by_entity' => 'مقبول من الجهة',
                                                'approved' => 'معتمد',
                                                'rejected' => 'مرفوض'
                                            ];
                                            ?>
                                            <span class="badge bg-<?php echo $status_colors[$app['status']]; ?>">
                                                <?php echo $status_text[$app['status']]; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $app['supervisor_name'] ?? '---'; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- سجل الحضور -->
                <div class="card-custom mt-3">
                    <h5 class="mb-3">سجل الحضور</h5>
                    
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="text-center p-2 bg-light rounded">
                                <strong class="d-block fs-4"><?php echo $attendance_stats['present'] ?? 0; ?></strong>
                                <small>أيام حضور</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-2 bg-light rounded">
                                <strong class="d-block fs-4"><?php echo $attendance_stats['absent'] ?? 0; ?></strong>
                                <small>غياب</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-2 bg-light rounded">
                                <strong class="d-block fs-4"><?php echo $attendance_stats['late'] ?? 0; ?></strong>
                                <small>تأخير</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-2 bg-light rounded">
                                <strong class="d-block fs-4"><?php echo round($attendance_stats['total_hours'] ?? 0, 1); ?></strong>
                                <small>ساعات</small>
                            </div>
                        </div>
                    </div>
                    
                    <?php if(!empty($attendance_records)): ?>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>التاريخ</th>
                                <th>الدخول</th>
                                <th>الخروج</th>
                                <th>الساعات</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($attendance_records as $att): ?>
                            <tr>
                                <td><?php echo $att['attendance_date']; ?></td>
                                <td><?php echo $att['check_in_time'] ?? '---'; ?></td>
                                <td><?php echo $att['check_out_time'] ?? '---'; ?></td>
                                <td><?php echo $att['total_hours'] ?? '---'; ?></td>
                                <td>
                                    <?php
                                    $att_status = [
                                        'present' => 'success',
                                        'absent' => 'danger',
                                        'late' => 'warning',
                                        'excused' => 'info'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $att_status[$att['status']]; ?>">
                                        <?php echo $att['status']; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- بطاقة سريعة -->
                <div class="card-custom">
                    <div class="text-center">
                        <div class="avatar-circle mx-auto mb-3" style="width: 100px; height: 100px; background: var(--gradient);">
                            <span style="font-size: 40px;"><?php echo mb_substr($student['full_name_ar'], 0, 1); ?></span>
                        </div>
                        <h5><?php echo htmlspecialchars($student['full_name_ar']); ?></h5>
                        <p class="text-muted"><?php echo $student['student_id']; ?></p>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span>إجمالي الطلبات:</span>
                        <strong><?php echo count($student_apps); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>الطلبات المقبولة:</span>
                        <strong class="text-success"><?php echo count(array_filter($student_apps, fn($a) => in_array($a['status'], ['approved', 'accepted_by_entity']))); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>في انتظار:</span>
                        <strong class="text-warning"><?php echo count(array_filter($student_apps, fn($a) => $a['status'] == 'pending')); ?></strong>
                    </div>
                    
                    <?php if($student['is_graduating']): ?>
                    <div class="alert alert-success mt-3">
                        <i class="fas fa-graduation-cap"></i> متدربة متخرجة
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- إجراءات سريعة -->
                <div class="card-custom mt-3">
                    <h6 class="mb-3">إجراءات</h6>
                    <a href="messages.php?compose=1&to=<?php echo $student['user_id']; ?>" class="btn-outline-custom w-100 text-center mb-2">
                        <i class="fas fa-envelope ms-2"></i> إرسال رسالة
                    </a>
                    <a href="applications.php?student=<?php echo $student['id']; ?>" class="btn-outline-custom w-100 text-center">
                        <i class="fas fa-file-alt ms-2"></i> عرض جميع الطلبات
                    </a>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <!-- ========================================= -->
        <!-- قائمة المتدربات -->
        <!-- ========================================= -->
        <div class="page-header">
            <div>
                <h4 class="mb-1">المتدربات</h4>
                <p class="text-muted">إدارة ومتابعة المتدربات المسجلات</p>
            </div>
            <div class="d-flex gap-2">
                <span class="badge bg-primary p-3">
                    <i class="fas fa-user-graduate ms-2"></i> <?php echo $stats['total']; ?> متدربة
                </span>
                <span class="badge bg-success p-3">
                    <i class="fas fa-graduation-cap ms-2"></i> <?php echo $stats['graduating']; ?> متخرجة
                </span>
            </div>
        </div>
        
        <!-- إحصائيات سريعة -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">إجمالي المتدربات</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['active']; ?></div>
                    <div class="stat-label">متدربات حالياً</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['graduating']; ?></div>
                    <div class="stat-label">متخرجة قريباً</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['new']; ?></div>
                    <div class="stat-label">جديدة (30 يوم)</div>
                </div>
            </div>
        </div>
        
        <!-- فلترة -->
        <div class="card-custom mb-4">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">التخصص</label>
                    <select name="major" class="form-select">
                        <option value="all">كل التخصصات</option>
                        <?php foreach($majors as $m): ?>
                        <option value="<?php echo $m['id']; ?>" <?php echo $major_filter == $m['id'] ? 'selected' : ''; ?>>
                            <?php echo $m['major_name_ar']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">المستوى</label>
                    <select name="level" class="form-select">
                        <option value="all">كل المستويات</option>
                        <?php for($i=1; $i<=8; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo $level_filter == $i ? 'selected' : ''; ?>>
                            المستوى <?php echo $i; ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">الحالة</label>
                    <select name="status" class="form-select">
                        <option value="all">الكل</option>
                        <option value="graduating" <?php echo $status_filter == 'graduating' ? 'selected' : ''; ?>>متخرجة قريباً</option>
                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>لديها تدريب</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn-primary-custom w-100">تصفية</button>
                    <a href="students.php" class="btn-outline-custom ms-2">إعادة</a>
                </div>
            </form>
        </div>
        
        <!-- جدول المتدربات -->
        <?php if(empty($students)): ?>
            <div class="card-custom text-center py-5">
                <i class="fas fa-user-graduate fa-4x text-muted mb-3"></i>
                <h5>لا توجد طالبات</h5>
                <p class="text-muted">لم يتم تسجيل أي طالبات بعد</p>
            </div>
        <?php else: ?>
            <div class="table-custom">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>المتدربة</th>
                            <th>الرقم الجامعي</th>
                            <th>التخصص</th>
                            <th>المستوى</th>
                            <th>المعدل</th>
                            <th>الطلبات</th>
                            <th>الحالة</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($students as $i => $s): ?>
                        <tr>
                            <td><?php echo $i+1; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($s['full_name_ar']); ?></strong>
                                <br><small class="text-muted"><?php echo $s['student_id']; ?></small>
                            </td>
                            <td><?php echo $s['student_id']; ?></td>
                            <td><?php echo $s['major_name_ar'] ?? '---'; ?></td>
                            <td><?php echo $s['academic_level'] ?? '---'; ?></td>
                            <td><?php echo number_format($s['gpa'], 2); ?></td>
                            <td>
                                <span class="badge bg-info" title="إجمالي"><?php echo $s['apps_count']; ?></span>
                                <span class="badge bg-success" title="مقبول"><?php echo $s['accepted_count']; ?></span>
                                <span class="badge bg-warning" title="في انتظار"><?php echo $s['pending_count']; ?></span>
                            </td>
                            <td>
                                <?php if($s['is_graduating']): ?>
                                    <span class="badge bg-success">متخرجة</span>
                                <?php elseif($s['accepted_count'] > 0): ?>
                                    <span class="badge bg-primary">متدربة</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">جديدة</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?action=view&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.stat-card {
    background: white;
    border-radius: 10px;
    padding: 15px;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0,102,51,0.05);
}
.stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary);
    line-height: 1;
}
.stat-label {
    color: #6c757d;
    font-size: 0.9rem;
    margin-top: 5px;
}
.avatar-circle {
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
}
</style>

<?php require_once '../../includes/coordinator_footer.php'; ?>