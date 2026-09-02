 <?php
// modules/training_entity/trainees.php - إدارة المتدربات (صفحة واحدة شاملة)
session_start();
require_once '../../config/config.php';

$page_title = 'المتدربات';
require_once '../../includes/entity_header.php';

$view_id = $_GET['view'] ?? 0;
$status = $_GET['status'] ?? 'active';

// جلب قائمة المتدربات
$sql = "
    SELECT a.*, 
           s.full_name_ar, 
           s.student_id, 
           s.gpa, 
           s.phone,
           u.email,
           ts.title_ar, 
           ts.start_date, 
           ts.end_date,
           e.id as evaluation_id, 
           e.final_grade, 
           e.training_completed
    FROM applications a
    JOIN students s ON a.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN training_seats ts ON a.seat_id = ts.id
    LEFT JOIN evaluations e ON a.id = e.application_id
    WHERE ts.entity_id = ? AND a.status IN ('approved', 'accepted_by_entity')
";
$params = [$entity['id']];

if($status == 'active') {
    $sql .= " AND (e.training_completed = 0 OR e.training_completed IS NULL)";
} elseif($status == 'completed') {
    $sql .= " AND e.training_completed = 1";
}

$sql .= " ORDER BY a.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$trainees = $stmt->fetchAll();

// جلب عدد الطلبات الواردة (pending applications)
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM applications a
    JOIN training_seats ts ON a.seat_id = ts.id
    WHERE ts.entity_id = ? AND a.status = 'pending'
");
$stmt->execute([$entity['id']]);
$pending_applications_count = $stmt->fetchColumn();

// جلب عدد المقابلات القادمة
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM interviews i
    JOIN applications a ON i.application_id = a.id
    JOIN training_seats ts ON a.seat_id = ts.id
    WHERE ts.entity_id = ? AND i.status = 'scheduled'
");
$stmt->execute([$entity['id']]);
$upcoming_interviews_count = $stmt->fetchColumn();

// إذا كان هناك عرض لملف متدربة
if($view_id > 0) {
    $stmt = $pdo->prepare("
        SELECT a.*, 
               s.full_name_ar, 
               s.student_id, 
               s.national_id, 
               s.gpa, 
               s.phone,
               u.email,
               s.major_id, 
               m.major_name_ar, 
               ts.title_ar, 
               ts.start_date, 
               ts.end_date,
               e.*
        FROM applications a
        JOIN students s ON a.student_id = s.id
        JOIN users u ON s.user_id = u.id
        LEFT JOIN majors m ON s.major_id = m.id
        JOIN training_seats ts ON a.seat_id = ts.id
        LEFT JOIN evaluations e ON a.id = e.application_id
        WHERE a.id = ? AND ts.entity_id = ?
    ");
    $stmt->execute([$view_id, $entity['id']]);
    $trainee = $stmt->fetch();
    
    if(!$trainee) {
        header('Location: trainees.php');
        exit();
    }
    
    // جلب سجل الحضور
    $attendance = $pdo->prepare("
        SELECT * FROM attendance 
        WHERE student_id = ? AND application_id = ?
        ORDER BY attendance_date DESC
        LIMIT 10
    ");
    $attendance->execute([$trainee['student_id'], $view_id]);
    $attendance_records = $attendance->fetchAll();
    
    // إحصائيات الحضور
    $stats = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
            COALESCE(SUM(total_hours), 0) as total_hours
        FROM attendance 
        WHERE student_id = ? AND application_id = ?
    ");
    $stats->execute([$trainee['student_id'], $view_id]);
    $attendance_stats = $stats->fetch();
}

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h4 class="mb-1">المتدربات</h4>
            <p class="text-muted mb-0">متابعة المتدربات الحاليات والسابقات</p>
        </div>
        <div class="d-flex gap-2">
            <?php if($view_id > 0): ?>
            <a href="trainees.php" class="btn-outline-custom">
                <i class="fas fa-arrow-right ms-2"></i>
                العودة للقائمة
            </a>
            <?php else: ?>
            <!-- أزرار الصفحات الرئيسية -->
            <a href="evaluations.php" class="btn-primary-custom" style="background: var(--gradient);">
                <i class="fas fa-star ms-2"></i>
                التقييمات
            </a>
            <a href="applications.php" class="btn-primary-custom" style="background: var(--gradient);">
                <i class="fas fa-file-alt ms-2"></i>
                الطلبات الواردة
                <?php if($pending_applications_count > 0): ?>
                <span class="badge bg-danger ms-1"><?php echo $pending_applications_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="interviews.php" class="btn-primary-custom" style="background: var(--gradient);">
                <i class="fas fa-calendar-check ms-2"></i>
                المقابلات
                <?php if($upcoming_interviews_count > 0): ?>
                <span class="badge bg-danger ms-1"><?php echo $upcoming_interviews_count; ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <?php if(!$view_id): ?>
    <!-- تبويبات الحالة -->
    <div class="mb-4">
        <a href="?status=active" class="btn <?php echo $status == 'active' ? 'btn-primary' : 'btn-outline-primary'; ?> me-2">
            <i class="fas fa-users ms-1"></i>
            متدربات حاليات
        </a>
        <a href="?status=completed" class="btn <?php echo $status == 'completed' ? 'btn-success' : 'btn-outline-success'; ?>">
            <i class="fas fa-check-circle ms-1"></i>
            متدربات سابقات
        </a>
    </div>
    
    <!-- قائمة المتدربات -->
    <div class="table-custom">
        <table class="table">
            <thead>
                <tr>
                    <th>الاسم</th>
                    <th>الرقم الجامعي</th>
                    <th>البريد الإلكتروني</th>
                    <th>الفرصة التدريبية</th>
                    <th>تاريخ البدء</th>
                    <th>التقييم</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($trainees)): ?>
                <tr>
                    <td colspan="7" class="text-center py-4">
                        <i class="fas fa-users fa-2x text-muted mb-2"></i>
                        <p>لا توجد متدربات</p>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach($trainees as $t): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($t['full_name_ar']); ?></strong>
                        </td>
                        <td><?php echo htmlspecialchars($t['student_id']); ?></td>
                        <td><?php echo htmlspecialchars($t['email']); ?></td>
                        <td><?php echo htmlspecialchars($t['title_ar']); ?></td>
                        <td><?php echo $t['start_date'] ? date('Y-m-d', strtotime($t['start_date'])) : 'غير محدد'; ?></td>
                        <td>
                            <?php if($t['final_grade']): ?>
                            <span class="badge bg-success"><?php echo $t['final_grade']; ?></span>
                            <?php else: ?>
                            <span class="badge bg-secondary">لم يقيم</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="?view=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye"></i> عرض
                            </a>
                            <?php if(!$t['evaluation_id']): ?>
                            <a href="evaluations.php?create=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-success">
                                <i class="fas fa-star"></i> تقييم
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php else: ?>
    <!-- عرض ملف متدربة -->
    <div class="row">
        <div class="col-md-8">
            <div class="card-custom">
                <h5 class="mb-3">البيانات الشخصية</h5>
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <strong>الاسم:</strong>
                    </div>
                    <div class="col-md-8 mb-2">
                        <?php echo htmlspecialchars($trainee['full_name_ar']); ?>
                    </div>
                    
                    <div class="col-md-4 mb-2">
                        <strong>الرقم الجامعي:</strong>
                    </div>
                    <div class="col-md-8 mb-2">
                        <?php echo htmlspecialchars($trainee['student_id']); ?>
                    </div>
                    
                    <div class="col-md-4 mb-2">
                        <strong>رقم الهوية:</strong>
                    </div>
                    <div class="col-md-8 mb-2">
                        <?php echo htmlspecialchars($trainee['national_id']); ?>
                    </div>
                    
                    <div class="col-md-4 mb-2">
                        <strong>رقم الجوال:</strong>
                    </div>
                    <div class="col-md-8 mb-2">
                        <?php echo htmlspecialchars($trainee['phone']); ?>
                    </div>
                    
                    <div class="col-md-4 mb-2">
                        <strong>البريد الإلكتروني:</strong>
                    </div>
                    <div class="col-md-8 mb-2">
                        <?php echo htmlspecialchars($trainee['email']); ?>
                    </div>
                    
                    <div class="col-md-4 mb-2">
                        <strong>التخصص:</strong>
                    </div>
                    <div class="col-md-8 mb-2">
                        <?php echo htmlspecialchars($trainee['major_name_ar'] ?? 'غير محدد'); ?>
                    </div>
                    
                    <div class="col-md-4 mb-2">
                        <strong>المعدل:</strong>
                    </div>
                    <div class="col-md-8 mb-2">
                        <?php echo $trainee['gpa']; ?>
                    </div>
                </div>
            </div>
            
            <div class="card-custom mt-3">
                <h5 class="mb-3">معلومات التدريب</h5>
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <strong>الفرصة:</strong>
                    </div>
                    <div class="col-md-8 mb-2">
                        <?php echo htmlspecialchars($trainee['title_ar']); ?>
                    </div>
                    
                    <div class="col-md-4 mb-2">
                        <strong>تاريخ البدء:</strong>
                    </div>
                    <div class="col-md-8 mb-2">
                        <?php echo $trainee['start_date'] ? date('Y-m-d', strtotime($trainee['start_date'])) : 'غير محدد'; ?>
                    </div>
                    
                    <div class="col-md-4 mb-2">
                        <strong>تاريخ الانتهاء:</strong>
                    </div>
                    <div class="col-md-8 mb-2">
                        <?php echo $trainee['end_date'] ? date('Y-m-d', strtotime($trainee['end_date'])) : 'غير محدد'; ?>
                    </div>
                </div>
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
                            <strong class="d-block fs-4"><?php echo $attendance_stats['total_hours'] ?? 0; ?></strong>
                            <small>ساعات</small>
                        </div>
                    </div>
                </div>
                
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
                        <?php if(empty($attendance_records)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-3">لا توجد سجلات حضور</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach($attendance_records as $att): ?>
                            <tr>
                                <td><?php echo $att['attendance_date']; ?></td>
                                <td><?php echo $att['check_in_time'] ?? '---'; ?></td>
                                <td><?php echo $att['check_out_time'] ?? '---'; ?></td>
                                <td><?php echo $att['total_hours'] ?? '---'; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $att['status'] == 'present' ? 'success' : ($att['status'] == 'late' ? 'warning' : 'danger'); ?>">
                                        <?php echo $att['status']; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- التقييم -->
            <div class="card-custom">
                <h5 class="mb-3">التقييم</h5>
                
                <?php if(isset($trainee['evaluation_id']) && $trainee['evaluation_id']): ?>
                <div class="text-center">
                    <div class="display-1 fw-bold" style="color: var(--primary);">
                        <?php echo $trainee['final_grade'] ?? '---'; ?>
                    </div>
                    <p>الدرجة النهائية</p>
                    
                    <?php if(isset($trainee['training_completed']) && $trainee['training_completed']): ?>
                    <span class="badge bg-success fs-6">مكتمل</span>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <p class="text-muted text-center py-3">لم يتم تقييم المتدربة بعد</p>
                <a href="evaluations.php?create=<?php echo $view_id; ?>" class="btn-primary-custom w-100">
                    <i class="fas fa-star ms-2"></i>
                    إنشاء تقييم
                </a>
                <?php endif; ?>
            </div>
            
            <!-- إجراءات سريعة -->
            <div class="card-custom mt-3">
                <h5 class="mb-3">إجراءات</h5>
                <div class="d-grid gap-2">
                    <a href="attendance_verify.php?trainee=<?php echo $view_id; ?>" class="btn-outline-custom">
                        <i class="fas fa-clock ms-2"></i>
                        توثيق الحضور
                    </a>
                    <a href="message_compose.php?to=<?php echo $trainee['student_id'] ?? 0; ?>" class="btn-outline-custom">
                        <i class="fas fa-envelope ms-2"></i>
                        إرسال رسالة
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
require_once '../../includes/entity_footer.php';
?>