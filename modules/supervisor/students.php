 <?php
// modules/supervisor/students.php - عرض المتدربات المشرف عليها
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
// تحديد الصفحة الحالية
// =============================================
$action = $_GET['action'] ?? 'list';
$student_id = $_GET['id'] ?? 0;

// =============================================
// جلب المتدربات المشرف عليها (✅ تم التعديل)
// =============================================
$stmt = $pdo->prepare("
    SELECT 
        a.id as application_id,
        s.id as student_id,
        s.full_name_ar,
        s.student_id as student_code,
        s.gpa,
        s.phone,
        u.email,
        s.academic_level,
        m.major_name_ar,
        ts.title_ar as training_title,
        te.entity_name_ar,
        te.city,
        ts.start_date,
        ts.end_date,
        a.status,
        (SELECT COUNT(*) FROM weekly_reports WHERE application_id = a.id) as reports_count,
        (SELECT COUNT(*) FROM weekly_reports WHERE application_id = a.id AND status = 'approved') as approved_reports,
        (SELECT COUNT(*) FROM visits WHERE application_id = a.id) as visits_count,
        (SELECT MAX(visit_date) FROM visits WHERE application_id = a.id AND status = 'completed') as last_visit
    FROM applications a
    JOIN students s ON a.student_id = s.id
    JOIN users u ON s.user_id = u.id
    LEFT JOIN majors m ON s.major_id = m.id
    JOIN training_seats ts ON a.seat_id = ts.id
    JOIN training_entities te ON ts.entity_id = te.id
    WHERE a.supervisor_id = ? AND a.status IN ('approved', 'accepted_by_entity')
    ORDER BY a.created_at DESC
");
$stmt->execute([$supervisor['id']]);
$students = $stmt->fetchAll();

// =============================================
// إذا كان عرض تفاصيل متدربة
// =============================================
if($action == 'view' && $student_id > 0) {
    $stmt = $pdo->prepare("
        SELECT 
            a.id as application_id,
            s.*,
            m.major_name_ar,
            u.email,
            ts.title_ar,
            ts.description,
            ts.requirements,
            ts.start_date,
            ts.end_date,
            ts.daily_hours,
            ts.is_remote,
            ts.has_salary,
            ts.salary_amount,
            te.entity_name_ar,
            te.contact_person,
            te.contact_phone,
            te.city as entity_city,
            te.address
        FROM applications a
        JOIN students s ON a.student_id = s.id
        LEFT JOIN majors m ON s.major_id = m.id
        JOIN users u ON s.user_id = u.id
        JOIN training_seats ts ON a.seat_id = ts.id
        JOIN training_entities te ON ts.entity_id = te.id
        WHERE a.supervisor_id = ? AND a.student_id = ? AND a.status IN ('approved', 'accepted_by_entity')
    ");
    $stmt->execute([$supervisor['id'], $student_id]);
    $student_details = $stmt->fetch();
    
    if(!$student_details) {
        header('Location: students.php');
        exit();
    }
    
    // جلب تقارير المتدربة
    $reports = $pdo->prepare("
        SELECT * FROM weekly_reports 
        WHERE application_id = ? 
        ORDER BY week_number DESC
    ");
    $reports->execute([$student_details['application_id']]);
    $student_reports = $reports->fetchAll();
    
    // جلب زيارات المتدربة
    $visits = $pdo->prepare("
        SELECT * FROM visits 
        WHERE application_id = ? 
        ORDER BY visit_date DESC
    ");
    $visits->execute([$student_details['application_id']]);
    $student_visits = $visits->fetchAll();
}

$page_title = 'المتدربات المشرف عليها';
require_once '../../includes/supervisor_header.php';
?>


<div class="content">
    <?php if($action == 'view' && isset($student_details)): ?>
        <!-- ========================================= -->
        <!-- عرض تفاصيل المتدربة -->
        <!-- ========================================= -->
        <div class="back-link mb-3">
            <a href="students.php"><i class="fas fa-arrow-right ms-1"></i> العودة للقائمة</a>
        </div>
        
        <div class="row">
            <!-- معلومات المتدربة -->
            <div class="col-md-8">
                <div class="card-custom">
                    <h5 class="mb-3">الملف الشخصي للمتدربة</h5>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <small class="text-muted">الاسم الكامل</small>
                            <p><strong><?php echo htmlspecialchars($student_details['full_name_ar']); ?></strong></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <small class="text-muted">الرقم الجامعي</small>
                            <p><strong><?php echo $student_details['student_id']; ?></strong></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <small class="text-muted">التخصص</small>
                            <p><strong><?php echo htmlspecialchars($student_details['major_name_ar'] ?? 'غير محدد'); ?></strong></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <small class="text-muted">المعدل التراكمي</small>
                            <p><strong><?php echo number_format($student_details['gpa'], 2); ?></strong></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <small class="text-muted">رقم الجوال</small>
                            <p><strong><?php echo $student_details['phone']; ?></strong></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <small class="text-muted">البريد الإلكتروني</small>
                            <p><strong><?php echo $student_details['email']; ?></strong></p>
                        </div>
                    </div>
                </div>
                
                <!-- معلومات التدريب -->
                <div class="card-custom mt-3">
                    <h5 class="mb-3">التدريب الحالي</h5>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <small class="text-muted">الفرصة التدريبية</small>
                            <p><strong><?php echo htmlspecialchars($student_details['title_ar']); ?></strong></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <small class="text-muted">الجهة التدريبية</small>
                            <p><strong><?php echo htmlspecialchars($student_details['entity_name_ar']); ?></strong></p>
                        </div>
                        <div class="col-md-4 mb-3">
                            <small class="text-muted">تاريخ البدء</small>
                            <p><strong><?php echo $student_details['start_date'] ?? 'غير محدد'; ?></strong></p>
                        </div>
                        <div class="col-md-4 mb-3">
                            <small class="text-muted">تاريخ الانتهاء</small>
                            <p><strong><?php echo $student_details['end_date'] ?? 'غير محدد'; ?></strong></p>
                        </div>
                        <div class="col-md-4 mb-3">
                            <small class="text-muted">ساعات العمل</small>
                            <p><strong><?php echo $student_details['daily_hours'] ?? 8; ?> ساعات</strong></p>
                        </div>
                    </div>
                    
                    <?php if($student_details['description']): ?>
                    <div class="mt-3">
                        <small class="text-muted">وصف التدريب</small>
                        <p><?php echo nl2br(htmlspecialchars($student_details['description'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- التقارير الأسبوعية -->
                <div class="card-custom mt-3">
                    <h5 class="mb-3">التقارير الأسبوعية</h5>
                    
                    <?php if(empty($student_reports)): ?>
                        <p class="text-muted text-center py-3">لا توجد تقارير بعد</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>الأسبوع</th>
                                        <th>الفترة</th>
                                        <th>تاريخ التقديم</th>
                                        <th>الحالة</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($student_reports as $rep): ?>
                                    <tr>
                                        <td>الأسبوع <?php echo $rep['week_number']; ?></td>
                                        <td><?php echo $rep['start_date'] . ' إلى ' . $rep['end_date']; ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($rep['created_at'])); ?></td>
                                        <td>
                                            <?php
                                            $status_color = [
                                                'draft' => 'secondary',
                                                'submitted' => 'info',
                                                'reviewed' => 'warning',
                                                'approved' => 'success'
                                            ];
                                            ?>
                                            <span class="badge bg-<?php echo $status_color[$rep['status']]; ?>">
                                                <?php echo $rep['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="report_view.php?id=<?php echo $rep['id']; ?>" class="btn btn-sm btn-outline-primary">
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
                
                <!-- الزيارات الميدانية -->
                <div class="card-custom mt-3">
                    <h5 class="mb-3">الزيارات الميدانية</h5>
                    
                    <?php if(empty($student_visits)): ?>
                        <p class="text-muted text-center py-3">لا توجد زيارات</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>التاريخ</th>
                                        <th>الوقت</th>
                                        <th>المكان</th>
                                        <th>الحالة</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($student_visits as $v): ?>
                                    <tr>
                                        <td><?php echo $v['visit_date']; ?></td>
                                        <td><?php echo $v['visit_time']; ?></td>
                                        <td><?php echo htmlspecialchars($v['location']); ?></td>
                                        <td>
                                            <?php
                                            $v_status = [
                                                'scheduled' => 'primary',
                                                'completed' => 'success',
                                                'cancelled' => 'danger'
                                            ];
                                            ?>
                                            <span class="badge bg-<?php echo $v_status[$v['status']]; ?>">
                                                <?php echo $v['status']; ?>
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
            
            <!-- معلومات سريعة -->
            <div class="col-md-4">
                <div class="card-custom">
                    <div class="text-center mb-3">
                        <div class="avatar-circle mx-auto" style="width: 80px; height: 80px; background: var(--gradient);">
                            <span style="font-size: 30px;"><?php echo mb_substr($student_details['full_name_ar'], 0, 1); ?></span>
                        </div>
                        <h6 class="mt-2"><?php echo htmlspecialchars($student_details['full_name_ar']); ?></h6>
                        <p class="text-muted small"><?php echo $student_details['student_id']; ?></p>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span>التقارير:</span>
                        <strong><?php echo count($student_reports); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>المعتمدة:</span>
                        <strong class="text-success"><?php echo count(array_filter($student_reports, fn($r) => $r['status'] == 'approved')); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>الزيارات:</span>
                        <strong><?php echo count($student_visits); ?></strong>
                    </div>
                    
                    <hr>
                    
                    <a href="messages.php?compose=1&to=<?php echo $student_details['user_id']; ?>" class="btn-outline-custom w-100 text-center mb-2">
                        <i class="fas fa-envelope ms-2"></i> إرسال رسالة
                    </a>
                    <a href="visits.php?action=add&student=<?php echo $student_details['id']; ?>" class="btn-outline-custom w-100 text-center">
                        <i class="fas fa-calendar-plus ms-2"></i> جدولة زيارة
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
                <h4 class="mb-1">المتدربات المشرف عليها</h4>
                <p class="text-muted">عدد المتدربات: <?php echo count($students); ?></p>
            </div>
        </div>
        
        <?php if(empty($students)): ?>
            <div class="card-custom text-center py-5">
                <i class="fas fa-user-graduate fa-4x text-muted mb-3"></i>
                <h5>لا توجد طالبات</h5>
                <p class="text-muted">لم يتم توزيع أي طالبات عليك بعد</p>
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
                            <th>الجهة</th>
                            <th>المعدل</th>
                            <th>التقارير</th>
                            <th>آخر زيارة</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($students as $i => $s): ?>
                        <tr>
                            <td><?php echo $i+1; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($s['full_name_ar']); ?></strong>
                            </td>
                            <td><?php echo $s['student_code']; ?></td>
                            <td><?php echo htmlspecialchars($s['major_name_ar'] ?? '---'); ?></td>
                            <td><?php echo htmlspecialchars($s['entity_name_ar']); ?></td>
                            <td><?php echo number_format($s['gpa'], 2); ?></td>
                            <td>
                                <span class="badge bg-info"><?php echo $s['reports_count']; ?></span>
                                <span class="badge bg-success"><?php echo $s['approved_reports']; ?></span>
                            </td>
                            <td><?php echo $s['last_visit'] ?? '---'; ?></td>
                            <td>
                                <a href="?action=view&id=<?php echo $s['student_id']; ?>" class="btn btn-sm btn-outline-primary">
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
.avatar-circle {
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
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