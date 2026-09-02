<?php
// modules/coordinator/applications.php - إدارة جميع الطلبات
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
$app_id = $_GET['id'] ?? 0;
$status_filter = $_GET['status'] ?? 'all';
$entity_filter = $_GET['entity'] ?? 0;
$student_filter = $_GET['student'] ?? 0;

// =============================================
// جلب الجهات للفلتر
// =============================================
$entities = $pdo->query("SELECT id, entity_name_ar FROM training_entities ORDER BY entity_name_ar")->fetchAll();

// =============================================
// جلب المتدربات للفلتر
// =============================================
$students_list = $pdo->query("SELECT id, full_name_ar, student_id FROM students ORDER BY full_name_ar")->fetchAll();

// =============================================
// جلب الطلبات
// =============================================
$sql = "
    SELECT a.*, 
           s.full_name_ar, 
           s.student_id,
           s.phone as student_phone,
           ts.title_ar, 
           te.entity_name_ar,
           te.id as entity_id,
           sup.full_name_ar as supervisor_name
    FROM applications a
    JOIN students s ON a.student_id = s.id
    JOIN training_seats ts ON a.seat_id = ts.id
    JOIN training_entities te ON ts.entity_id = te.id
    LEFT JOIN supervisors sup ON a.supervisor_id = sup.id
    WHERE 1=1
";
$params = [];

if($status_filter != 'all') {
    $sql .= " AND a.status = ?";
    $params[] = $status_filter;
}

if($entity_filter > 0) {
    $sql .= " AND te.id = ?";
    $params[] = $entity_filter;
}

if($student_filter > 0) {
    $sql .= " AND s.id = ?";
    $params[] = $student_filter;
}

$sql .= " ORDER BY a.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$applications = $stmt->fetchAll();

// إحصائيات
$stats = [
    'total' => count($applications),
    'pending' => count(array_filter($applications, fn($a) => $a['status'] == 'pending')),
    'approved' => count(array_filter($applications, fn($a) => in_array($a['status'], ['approved', 'accepted_by_entity']))),
    'rejected' => count(array_filter($applications, fn($a) => $a['status'] == 'rejected')),
    'interview' => count(array_filter($applications, fn($a) => $a['status'] == 'interview_scheduled')),
];

// حالات الطلب
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

// إذا كان عرض تفاصيل طلب
if($action == 'view' && $app_id > 0) {
    $stmt = $pdo->prepare("
        SELECT a.*, 
               s.id as student_id,
               s.full_name_ar, 
               s.student_id as student_code, 
               s.phone,
               s.national_id,
               s.gpa,
               s.academic_level,
               s.expected_graduation,
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
               te.contact_email,
               te.city,
               te.address,
               sup.full_name_ar as supervisor_name,
               sup.phone as supervisor_phone
        FROM applications a
        JOIN students s ON a.student_id = s.id
        LEFT JOIN majors m ON s.major_id = m.id
        JOIN users u ON s.user_id = u.id
        JOIN training_seats ts ON a.seat_id = ts.id
        JOIN training_entities te ON ts.entity_id = te.id
        LEFT JOIN supervisors sup ON a.supervisor_id = sup.id
        WHERE a.id = ?
    ");
    $stmt->execute([$app_id]);
    $application = $stmt->fetch();
    
    if(!$application) {
        header('Location: applications.php');
        exit();
    }
    
    // جلب المستندات
    $docs = $pdo->prepare("SELECT * FROM attachments WHERE related_type = 'application' AND related_id = ?");
    $docs->execute([$app_id]);
    $attachments = $docs->fetchAll();
    
    // جلب المقابلات
    $interviews = $pdo->prepare("
        SELECT * FROM interviews 
        WHERE application_id = ? 
        ORDER BY interview_date DESC
    ");
    $interviews->execute([$app_id]);
    $interview_list = $interviews->fetchAll();
    
    // جلب التقييمات
    $eval = $pdo->prepare("SELECT * FROM evaluations WHERE application_id = ?");
    $eval->execute([$app_id]);
    $evaluation = $eval->fetch();
}

$page_title = 'الطلبات';
require_once '../../includes/coordinator_header.php';

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>

<div class="content">
    <?php if($action == 'view' && isset($application)): ?>
        <!-- ========================================= -->
        <!-- عرض تفاصيل طلب محدد -->
        <!-- ========================================= -->
        <div class="back-link mb-3">
            <a href="applications.php"><i class="fas fa-arrow-right ms-1"></i> العودة إلى قائمة الطلبات</a>
        </div>
        
        <div class="row">
            <!-- العمود الرئيسي -->
            <div class="col-lg-8">
                <!-- معلومات الطلب -->
                <div class="card-custom">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <h5 class="mb-0">تفاصيل الطلب #<?php echo $application['id']; ?></h5>
                        <span class="badge bg-<?php echo $status_colors[$application['status']]; ?> p-3">
                            <?php echo $status_text[$application['status']]; ?>
                        </span>
                    </div>
                    
                    <!-- معلومات المتدربة -->
                    <h6 class="mb-3" style="color: var(--primary);">
                        <i class="fas fa-user-graduate ms-2"></i> معلومات المتدربة
                    </h6>
                    <div class="row mb-4">
                        <div class="col-md-4 mb-2">
                            <small class="text-muted d-block">الاسم</small>
                            <strong><?php echo htmlspecialchars($application['full_name_ar']); ?></strong>
                        </div>
                        <div class="col-md-4 mb-2">
                            <small class="text-muted d-block">الرقم الجامعي</small>
                            <strong><?php echo $application['student_code']; ?></strong>
                        </div>
                        <div class="col-md-4 mb-2">
                            <small class="text-muted d-block">رقم الهوية</small>
                            <strong><?php echo $application['national_id']; ?></strong>
                        </div>
                        <div class="col-md-4 mb-2">
                            <small class="text-muted d-block">رقم الجوال</small>
                            <strong><?php echo $application['phone']; ?></strong>
                        </div>
                        <div class="col-md-4 mb-2">
                            <small class="text-muted d-block">البريد الإلكتروني</small>
                            <strong><?php echo $application['email']; ?></strong>
                        </div>
                        <div class="col-md-4 mb-2">
                            <small class="text-muted d-block">التخصص</small>
                            <strong><?php echo $application['major_name_ar'] ?? 'غير محدد'; ?></strong>
                        </div>
                        <div class="col-md-4 mb-2">
                            <small class="text-muted d-block">المعدل</small>
                            <strong><?php echo number_format($application['gpa'], 2); ?></strong>
                        </div>
                        <div class="col-md-4 mb-2">
                            <small class="text-muted d-block">المستوى</small>
                            <strong><?php echo $application['academic_level'] ?? '---'; ?></strong>
                        </div>
                        <div class="col-md-4 mb-2">
                            <small class="text-muted d-block">تاريخ التخرج</small>
                            <strong><?php echo $application['expected_graduation'] ?? '---'; ?></strong>
                        </div>
                    </div>
                    
                    <!-- معلومات الفرصة -->
                    <h6 class="mb-3" style="color: var(--primary);">
                        <i class="fas fa-briefcase ms-2"></i> معلومات الفرصة التدريبية
                    </h6>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-2">
                            <small class="text-muted d-block">عنوان الفرصة</small>
                            <strong><?php echo htmlspecialchars($application['title_ar']); ?></strong>
                        </div>
                        <div class="col-md-6 mb-2">
                            <small class="text-muted d-block">الجهة التدريبية</small>
                            <strong><?php echo htmlspecialchars($application['entity_name_ar']); ?></strong>
                        </div>
                        <div class="col-md-3 mb-2">
                            <small class="text-muted d-block">تاريخ البدء</small>
                            <strong><?php echo $application['start_date'] ?? 'غير محدد'; ?></strong>
                        </div>
                        <div class="col-md-3 mb-2">
                            <small class="text-muted d-block">تاريخ الانتهاء</small>
                            <strong><?php echo $application['end_date'] ?? 'غير محدد'; ?></strong>
                        </div>
                        <div class="col-md-3 mb-2">
                            <small class="text-muted d-block">ساعات العمل</small>
                            <strong><?php echo $application['daily_hours'] ?? 8; ?> ساعات</strong>
                        </div>
                        <div class="col-md-3 mb-2">
                            <small class="text-muted d-block">نوع التدريب</small>
                            <strong>
                                <?php if($application['is_remote']): ?>
                                    <span class="badge bg-info">عن بعد</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">حضوري</span>
                                <?php endif; ?>
                            </strong>
                        </div>
                        <?php if($application['has_salary']): ?>
                        <div class="col-12 mb-2">
                            <small class="text-muted d-block">المكافأة</small>
                            <strong class="text-success"><?php echo number_format($application['salary_amount']); ?> ريال</strong>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- ملاحظات المتدربة -->
                    <?php if($application['notes']): ?>
                    <div class="mb-4">
                        <h6 class="mb-2">ملاحظات المتدربة</h6>
                        <div class="p-3 bg-light rounded">
                            <?php echo nl2br(htmlspecialchars($application['notes'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- المستندات المرفقة -->
                    <?php if(!empty($attachments)): ?>
                    <div class="mb-4">
                        <h6 class="mb-3">المستندات المرفقة</h6>
                        <?php foreach($attachments as $file): ?>
                        <div class="d-flex justify-content-between align-items-center p-2 border-bottom">
                            <div>
                                <i class="fas fa-file-pdf text-danger ms-2"></i>
                                <?php echo htmlspecialchars($file['file_name']); ?>
                                <small class="text-muted">(<?php echo round($file['file_size'] / 1024, 2); ?> KB)</small>
                            </div>
                            <a href="../../uploads/<?php echo $file['file_path']; ?>" class="btn btn-sm btn-outline-primary" download>
                                <i class="fas fa-download"></i>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- المقابلات -->
                    <?php if(!empty($interview_list)): ?>
                    <div class="mb-4">
                        <h6 class="mb-3">المقابلات</h6>
                        <?php foreach($interview_list as $inv): ?>
                        <div class="border-bottom pb-2 mb-2">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <strong><?php echo date('Y-m-d', strtotime($inv['interview_date'])); ?></strong>
                                    <span class="mx-2">|</span>
                                    <span><?php echo date('H:i', strtotime($inv['interview_date'])); ?></span>
                                </div>
                                <span class="badge bg-<?php echo $inv['status'] == 'completed' ? 'success' : 'warning'; ?>">
                                    <?php echo $inv['status']; ?>
                                </span>
                            </div>
                            <?php if($inv['meeting_link']): ?>
                            <a href="<?php echo $inv['meeting_link']; ?>" target="_blank" class="small">
                                <i class="fas fa-video ms-1"></i> رابط المقابلة
                            </a>
                            <?php endif; ?>
                            <?php if($inv['feedback']): ?>
                            <p class="small text-muted mt-1"><?php echo $inv['feedback']; ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- العمود الجانبي -->
            <div class="col-lg-4">
                <!-- معلومات التواصل مع الجهة -->
                <div class="card-custom">
                    <h6 class="mb-3">معلومات التواصل</h6>
                    <p class="mb-2"><i class="fas fa-building ms-2 text-muted"></i> <?php echo $application['entity_name_ar']; ?></p>
                    <p class="mb-2"><i class="fas fa-user-tie ms-2 text-muted"></i> <?php echo $application['contact_person'] ?? 'غير محدد'; ?></p>
                    <p class="mb-2"><i class="fas fa-phone ms-2 text-muted"></i> <?php echo $application['contact_phone'] ?? 'غير محدد'; ?></p>
                    <p class="mb-2"><i class="fas fa-envelope ms-2 text-muted"></i> <?php echo $application['contact_email'] ?? 'غير محدد'; ?></p>
                    <?php if($application['city']): ?>
                    <p class="mb-0"><i class="fas fa-map-marker-alt ms-2 text-muted"></i> <?php echo $application['city']; ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- المشرف (إذا وجد) -->
                <?php if($application['supervisor_name']): ?>
                <div class="card-custom mt-3">
                    <h6 class="mb-3">المشرف الأكاديمي</h6>
                    <p class="mb-2"><strong><?php echo $application['supervisor_name']; ?></strong></p>
                    <p class="mb-0"><i class="fas fa-phone ms-2 text-muted"></i> <?php echo $application['supervisor_phone'] ?? 'غير محدد'; ?></p>
                </div>
                <?php endif; ?>
                
                <!-- التقييم (إذا وجد) -->
                <?php if($evaluation): ?>
                <div class="card-custom mt-3">
                    <h6 class="mb-3">التقييم النهائي</h6>
                    <div class="text-center">
                        <div class="display-4 fw-bold" style="color: var(--primary);"><?php echo $evaluation['final_grade']; ?></div>
                        <p class="mb-0"><?php echo $evaluation['final_grade_letter']; ?></p>
                        <?php if($evaluation['training_completed']): ?>
                        <span class="badge bg-success mt-2">مكتمل</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- معلومات إضافية -->
                <div class="card-custom mt-3">
                    <h6 class="mb-3">معلومات الطلب</h6>
                    <p class="mb-2">
                        <small class="text-muted d-block">تاريخ التقديم</small>
                        <strong><?php echo date('Y-m-d H:i', strtotime($application['created_at'])); ?></strong>
                    </p>
                    <p class="mb-2">
                        <small class="text-muted d-block">آخر تحديث</small>
                        <strong><?php echo date('Y-m-d H:i', strtotime($application['updated_at'])); ?></strong>
                    </p>
                    <p class="mb-0">
                        <small class="text-muted d-block">المعدل عند التقديم</small>
                        <strong><?php echo $application['gpa_at_application']; ?></strong>
                    </p>
                </div>
                
           <!-- إجراءات -->
<div class="card-custom mt-3">
    <h6 class="mb-3">إجراءات</h6>
    
    <?php if(isset($application['student_id'])): ?>
    <a href="messages.php?compose=1&to=<?php echo $application['student_id']; ?>&type=student" class="btn-outline-custom w-100 text-center mb-2">
        <i class="fas fa-envelope ms-2"></i> مراسلة المتدربة
    </a>
    <?php endif; ?>
    
    <?php if(isset($application['entity_id'])): ?>
    <a href="messages.php?compose=1&to=<?php echo $application['entity_id']; ?>&type=entity" class="btn-outline-custom w-100 text-center">
        <i class="fas fa-building ms-2"></i> مراسلة الجهة
    </a>
    <?php endif; ?>
</div>
        
    <?php else: ?>
        <!-- ========================================= -->
        <!-- قائمة جميع الطلبات -->
        <!-- ========================================= -->
        <div class="page-header">
            <div>
                <h4 class="mb-1">الطلبات</h4>
                <p class="text-muted">مراجعة ومتابعة جميع طلبات التدريب</p>
            </div>
            <div class="d-flex gap-2">
                <span class="badge bg-primary p-3">
                    <i class="fas fa-file-alt ms-2"></i> <?php echo $stats['total']; ?> إجمالي
                </span>
                <span class="badge bg-warning p-3">
                    <i class="fas fa-clock ms-2"></i> <?php echo $stats['pending']; ?> جديدة
                </span>
            </div>
        </div>
        
        <!-- إحصائيات سريعة -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="stat-card" onclick="window.location.href='?status=pending'">
                    <div class="stat-number"><?php echo $stats['pending']; ?></div>
                    <div class="stat-label">جديد</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card" onclick="window.location.href='?status=interview_scheduled'">
                    <div class="stat-number"><?php echo $stats['interview']; ?></div>
                    <div class="stat-label">مقابلات</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card" onclick="window.location.href='?status=approved'">
                    <div class="stat-number"><?php echo $stats['approved']; ?></div>
                    <div class="stat-label">مقبول</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card" onclick="window.location.href='?status=rejected'">
                    <div class="stat-number"><?php echo $stats['rejected']; ?></div>
                    <div class="stat-label">مرفوض</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card" onclick="window.location.href='?status=all'">
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">الإجمالي</div>
                </div>
            </div>
        </div>
        
        <!-- فلترة متقدمة -->
        <div class="card-custom mb-4">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">الحالة</label>
                    <select name="status" class="form-select">
                        <option value="all">جميع الحالات</option>
                        <?php foreach($status_text as $key => $value): ?>
                        <option value="<?php echo $key; ?>" <?php echo $status_filter == $key ? 'selected' : ''; ?>>
                            <?php echo $value; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">الجهة التدريبية</label>
                    <select name="entity" class="form-select">
                        <option value="0">جميع الجهات</option>
                        <?php foreach($entities as $e): ?>
                        <option value="<?php echo $e['id']; ?>" <?php echo $entity_filter == $e['id'] ? 'selected' : ''; ?>>
                            <?php echo $e['entity_name_ar']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">المتدربة</label>
                    <select name="student" class="form-select">
                        <option value="0">جميع المتدربات</option>
                        <?php foreach($students_list as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo $student_filter == $s['id'] ? 'selected' : ''; ?>>
                            <?php echo $s['full_name_ar']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn-primary-custom w-100">تصفية</button>
                    <a href="applications.php" class="btn-outline-custom ms-2">إعادة</a>
                </div>
            </form>
        </div>
        
        <!-- جدول الطلبات -->
        <?php if(empty($applications)): ?>
            <div class="card-custom text-center py-5">
                <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
                <h5>لا توجد طلبات</h5>
                <p class="text-muted">لم يتم تقديم أي طلبات بعد</p>
            </div>
        <?php else: ?>
            <div class="table-custom">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>المتدربة</th>
                            <th>الفرصة</th>
                            <th>الجهة</th>
                            <th>تاريخ التقديم</th>
                            <th>الحالة</th>
                            <th>المشرف</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($applications as $i => $app): ?>
                        <tr>
                            <td><?php echo $i+1; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($app['full_name_ar']); ?></strong>
                                <br><small class="text-muted"><?php echo $app['student_id']; ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($app['title_ar']); ?></td>
                            <td><?php echo htmlspecialchars($app['entity_name_ar']); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($app['created_at'])); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $status_colors[$app['status']]; ?> p-2">
                                    <?php echo $status_text[$app['status']]; ?>
                                </span>
                            </td>
                            <td><?php echo $app['supervisor_name'] ?? '---'; ?></td>
                            <td>
                                <a href="?action=view&id=<?php echo $app['id']; ?>" class="btn btn-sm btn-outline-primary" title="عرض التفاصيل">
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
    cursor: pointer;
    transition: all 0.3s ease;
}
.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,102,51,0.15);
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
.table-custom td {
    vertical-align: middle;
}
</style>

<?php require_once '../../includes/coordinator_footer.php'; ?>