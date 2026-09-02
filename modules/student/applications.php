 <?php
// modules/student/applications.php - إدارة جميع الطلبات في صفحة واحدة
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
// تحديد الصفحة الحالية (view, create, edit)
// =============================================
$action = $_GET['action'] ?? 'list';
$application_id = $_GET['id'] ?? 0;
$seat_id = $_GET['seat_id'] ?? 0;

// =============================================
// معالجة إنشاء طلب جديد
// =============================================
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_application'])) {
    
    $seat_id = $_POST['seat_id'] ?? 0;
    $cv_id = $_POST['cv_id'] ?? 0;
    $notes = trim($_POST['notes'] ?? '');
    $agree_terms = isset($_POST['agree_terms']);
    
    if(!$seat_id || !$agree_terms) {
        $_SESSION['error'] = 'جميع الحقول المطلوبة يجب ملؤها';
        header('Location: applications.php?action=create&seat_id=' . $seat_id);
        exit();
    }
    
    // التحقق من وجود الفرصة
    $seat = $pdo->prepare("
        SELECT ts.*, te.is_approved, te.id as entity_id, te.entity_name_ar
        FROM training_seats ts
        JOIN training_entities te ON ts.entity_id = te.id
        WHERE ts.id = ? AND ts.status = 'available'
    ");
    $seat->execute([$seat_id]);
    $opportunity = $seat->fetch();
    
    if(!$opportunity || !$opportunity['is_approved']) {
        $_SESSION['error'] = 'فرصة التدريب غير متاحة';
        header('Location: opportunities.php');
        exit();
    }
    
    // التحقق من عدم التقديم المسبق
    $check = $pdo->prepare("SELECT id FROM applications WHERE student_id = ? AND seat_id = ?");
    $check->execute([$student['id'], $seat_id]);
    if($check->fetch()) {
        $_SESSION['error'] = 'لقد تقدمت لهذه الفرصة مسبقاً';
        header('Location: opportunities.php');
        exit();
    }
    
    // معالجة السيرة الذاتية
    $cv_path = null;
    
    if($cv_id > 0) {
        $cv = $pdo->prepare("SELECT file_path FROM attachments WHERE id = ? AND related_type = 'student' AND related_id = ?");
        $cv->execute([$cv_id, $student['id']]);
        $cv_path = $cv->fetchColumn();
    }
    
    if(isset($_FILES['new_cv']) && $_FILES['new_cv']['error'] == 0) {
        $allowed = ['pdf', 'doc', 'docx'];
        $ext = strtolower(pathinfo($_FILES['new_cv']['name'], PATHINFO_EXTENSION));
        
        if(in_array($ext, $allowed) && $_FILES['new_cv']['size'] <= 5 * 1024 * 1024) {
            $filename = 'cv_' . $student['id'] . '_' . time() . '.' . $ext;
            $upload_path = '../../uploads/students/' . $filename;
            
            if(!is_dir('../../uploads/students/')) {
                mkdir('../../uploads/students/', 0777, true);
            }
            
            if(move_uploaded_file($_FILES['new_cv']['tmp_name'], $upload_path)) {
                $insert = $pdo->prepare("
                    INSERT INTO attachments 
                    (related_type, related_id, file_name, file_path, file_type, file_size, uploaded_by, uploaded_by_type, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'student', NOW())
                ");
                $insert->execute([
                    'student',
                    $student['id'],
                    $_FILES['new_cv']['name'],
                    'students/' . $filename,
                    'cv',
                    $_FILES['new_cv']['size'],
                    $_SESSION['user_id']
                ]);
                $cv_path = 'students/' . $filename;
            }
        }
    }
    
    if(!$cv_path) {
        $_SESSION['error'] = 'الرجاء اختيار أو رفع سيرة ذاتية';
        header('Location: applications.php?action=create&seat_id=' . $seat_id);
        exit();
    }
    
    try {
        $pdo->beginTransaction();
        
        // إدراج الطلب
        $stmt = $pdo->prepare("
            INSERT INTO applications 
            (student_id, seat_id, gpa_at_application, notes, cv_file, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $student['id'],
            $seat_id,
            $student['gpa'],
            $notes,
            $cv_path,
            'pending'
        ]);
        
        $new_id = $pdo->lastInsertId();
        
        // إشعار للجهة
        $entity_user = $pdo->prepare("SELECT user_id FROM training_entities WHERE id = ?");
        $entity_user->execute([$opportunity['entity_id']]);
        $entity_user_id = $entity_user->fetchColumn();
        
        if($entity_user_id) {
            $notify = $pdo->prepare("
                INSERT INTO notifications (user_id, user_type, title, message, type, related_link, related_id, created_at)
                VALUES (?, 'training_entity', 'طلب تدريب جديد', ?, 'info', 'applications.php?view=?', ?, NOW())
            ");
            $notify->execute([
                $entity_user_id,
                'لديك طلب تدريب جديد من المتدربة ' . $student['full_name_ar'],
                $new_id,
                $new_id
            ]);
        }
        
        $pdo->commit();
        $_SESSION['success'] = 'تم تقديم الطلب بنجاح';
        header('Location: applications.php?action=view&id=' . $new_id);
        exit();
        
    } catch(Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'حدث خطأ: ' . $e->getMessage();
        header('Location: applications.php?action=create&seat_id=' . $seat_id);
        exit();
    }
}

// =============================================
// معالجة إلغاء طلب
// =============================================
if(isset($_GET['cancel']) && $application_id > 0) {
    $update = $pdo->prepare("UPDATE applications SET status = 'cancelled' WHERE id = ? AND student_id = ?");
    $update->execute([$application_id, $student['id']]);
    
    $_SESSION['success'] = 'تم إلغاء الطلب';
    header('Location: applications.php');
    exit();
}

// =============================================
// جلب البيانات حسب الصفحة
// =============================================

// إذا كان عرض طلب محدد
if($action == 'view' && $application_id > 0) {
    $stmt = $pdo->prepare("
        SELECT a.*, 
               ts.id as seat_id,
               ts.title_ar, 
               ts.description, 
               ts.requirements, 
               ts.start_date, 
               ts.end_date,
               te.id as entity_id,
               te.entity_name_ar, 
               te.contact_person, 
               te.contact_phone, 
               te.contact_email,
               s.full_name_ar as supervisor_name, 
               s.phone as supervisor_phone
        FROM applications a
        JOIN training_seats ts ON a.seat_id = ts.id
        JOIN training_entities te ON ts.entity_id = te.id
        LEFT JOIN supervisors s ON a.supervisor_id = s.id
        WHERE a.id = ? AND a.student_id = ?
    ");
    $stmt->execute([$application_id, $student['id']]);
    $application = $stmt->fetch();
    
    if(!$application) {
        header('Location: applications.php');
        exit();
    }
    
    // جلب المستندات
    $docs = $pdo->prepare("SELECT * FROM attachments WHERE related_type = 'application' AND related_id = ?");
    $docs->execute([$application_id]);
    $attachments = $docs->fetchAll();
    
    // جلب المقابلات
    $interviews = $pdo->prepare("SELECT * FROM interviews WHERE application_id = ? ORDER BY interview_date DESC");
    $interviews->execute([$application_id]);
    $interview_list = $interviews->fetchAll();
}

// إذا كان إنشاء طلب جديد
if($action == 'create' && $seat_id > 0) {
    // جلب تفاصيل الفرصة
    $stmt = $pdo->prepare("
        SELECT ts.*, te.entity_name_ar, te.city, m.major_name_ar
        FROM training_seats ts
        JOIN training_entities te ON ts.entity_id = te.id
        LEFT JOIN majors m ON ts.major_id = m.id
        WHERE ts.id = ? AND te.is_approved = 1 AND ts.status = 'available'
    ");
    $stmt->execute([$seat_id]);
    $opportunity = $stmt->fetch();
    
    if(!$opportunity) {
        $_SESSION['error'] = 'فرصة التدريب غير متاحة';
        header('Location: opportunities.php');
        exit();
    }
    
    // التحقق من عدم التقديم المسبق
    $check = $pdo->prepare("SELECT id FROM applications WHERE student_id = ? AND seat_id = ?");
    $check->execute([$student['id'], $seat_id]);
    if($check->fetch()) {
        $_SESSION['error'] = 'لقد تقدمت لهذه الفرصة مسبقاً';
        header('Location: opportunities.php');
        exit();
    }
    
    // جلب السير الذاتية
    $cvs = $pdo->prepare("
        SELECT * FROM attachments 
        WHERE related_type = 'student' AND related_id = ? AND file_type = 'cv'
        ORDER BY created_at DESC
    ");
    $cvs->execute([$student['id']]);
    $cv_list = $cvs->fetchAll();
}

// جلب جميع الطلبات (للقائمة)
$all_applications = $pdo->prepare("
    SELECT a.*, ts.title_ar, te.entity_name_ar,
           CASE 
               WHEN a.status = 'approved' THEN 'success'
               WHEN a.status = 'rejected' THEN 'danger'
               WHEN a.status = 'pending' THEN 'warning'
               WHEN a.status = 'interview_scheduled' THEN 'info'
               ELSE 'secondary'
           END as status_color
    FROM applications a
    JOIN training_seats ts ON a.seat_id = ts.id
    JOIN training_entities te ON ts.entity_id = te.id
    WHERE a.student_id = ?
    ORDER BY a.created_at DESC
");
$all_applications->execute([$student['id']]);
$applications_list = $all_applications->fetchAll();

// إحصائيات
$stats = [
    'total' => count($applications_list),
    'pending' => count(array_filter($applications_list, fn($a) => $a['status'] == 'pending')),
    'approved' => count(array_filter($applications_list, fn($a) => in_array($a['status'], ['approved', 'accepted_by_entity']))),
    'rejected' => count(array_filter($applications_list, fn($a) => $a['status'] == 'rejected')),
    'interview' => count(array_filter($applications_list, fn($a) => $a['status'] == 'interview_scheduled')),
];

$page_title = 'طلباتي';
require_once '../../includes/student_header.php';

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h4 class="mb-1">طلبات التدريب</h4>
            <p class="text-muted mb-0">تقديم ومتابعة طلباتك</p>
        </div>
        <?php if($action != 'list'): ?>
        <a href="applications.php" class="btn-outline-custom">
            <i class="fas fa-arrow-right ms-2"></i>
            العودة للقائمة
        </a>
        <?php endif; ?>
    </div>
    
    <?php if($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <!-- ========================================= -->
    <!-- عرض طلب محدد -->
    <!-- ========================================= -->
    <?php if($action == 'view' && isset($application)): ?>
    
    <div class="row">
        <div class="col-md-8">
            <!-- معلومات الطلب -->
            <div class="card-custom">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h5><?php echo htmlspecialchars($application['title_ar']); ?></h5>
                    <?php
                    $status_text = [
                        'pending' => 'قيد المراجعة',
                        'under_review' => 'قيد الدراسة',
                        'interview_scheduled' => 'مقابلة',
                        'accepted_by_entity' => 'مقبول من الجهة',
                        'approved' => 'معتمد',
                        'rejected' => 'مرفوض'
                    ];
                    $status_colors = [
                        'pending' => 'warning',
                        'under_review' => 'info',
                        'interview_scheduled' => 'primary',
                        'accepted_by_entity' => 'success',
                        'approved' => 'success',
                        'rejected' => 'danger'
                    ];
                    ?>
                    <span class="badge bg-<?php echo $status_colors[$application['status']] ?? 'secondary'; ?> p-3">
                        <?php echo $status_text[$application['status']] ?? $application['status']; ?>
                    </span>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <small class="text-muted d-block">الجهة التدريبية</small>
                        <strong><?php echo htmlspecialchars($application['entity_name_ar']); ?></strong>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">تاريخ التقديم</small>
                        <strong><?php echo date('Y-m-d', strtotime($application['created_at'])); ?></strong>
                    </div>
                </div>
                
                <?php if($application['start_date']): ?>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <small class="text-muted d-block">تاريخ البدء</small>
                        <strong><?php echo $application['start_date']; ?></strong>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">تاريخ الانتهاء</small>
                        <strong><?php echo $application['end_date'] ?? 'غير محدد'; ?></strong>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if($application['notes']): ?>
                <div class="mt-3 p-3 bg-light rounded">
                    <strong>ملاحظاتك:</strong>
                    <p class="mb-0 mt-2"><?php echo nl2br(htmlspecialchars($application['notes'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- المستندات -->
            <?php if(!empty($attachments)): ?>
            <div class="card-custom mt-3">
                <h5 class="mb-3">المستندات المرفقة</h5>
                <?php foreach($attachments as $file): ?>
                <div class="d-flex justify-content-between align-items-center p-2 border-bottom">
                    <div>
                        <i class="fas fa-file-pdf text-danger ms-2"></i>
                        <?php echo htmlspecialchars($file['file_name']); ?>
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
            <div class="card-custom mt-3">
                <h5 class="mb-3">المقابلات</h5>
                <?php foreach($interview_list as $inv): ?>
                <div class="border-bottom pb-2 mb-2">
                    <div class="d-flex justify-content-between">
                        <strong><?php echo date('Y-m-d', strtotime($inv['interview_date'])); ?></strong>
                        <span class="badge bg-<?php echo $inv['status'] == 'completed' ? 'success' : 'warning'; ?>">
                            <?php echo $inv['status']; ?>
                        </span>
                    </div>
                    <small><?php echo date('H:i', strtotime($inv['interview_date'])); ?></small>
                    <?php if($inv['meeting_link']): ?>
                    <a href="<?php echo $inv['meeting_link']; ?>" target="_blank" class="d-block small">رابط المقابلة</a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="col-md-4">
            <!-- معلومات التواصل -->
            <div class="card-custom">
                <h5 class="mb-3">معلومات التواصل</h5>
                <p><strong>الجهة:</strong> <?php echo htmlspecialchars($application['entity_name_ar']); ?></p>
                <p><strong>المسؤول:</strong> <?php echo htmlspecialchars($application['contact_person'] ?? 'غير محدد'); ?></p>
                <p><strong>الهاتف:</strong> <?php echo htmlspecialchars($application['contact_phone'] ?? 'غير محدد'); ?></p>
                <p><strong>البريد:</strong> <?php echo htmlspecialchars($application['contact_email'] ?? 'غير محدد'); ?></p>
                
                <?php if($application['supervisor_name']): ?>
                <hr>
                <p><strong>المشرف:</strong> <?php echo htmlspecialchars($application['supervisor_name']); ?></p>
                <p><strong>هاتف المشرف:</strong> <?php echo htmlspecialchars($application['supervisor_phone'] ?? 'غير محدد'); ?></p>
                <?php endif; ?>
            </div>
            
            <!-- إجراءات -->
            <div class="card-custom mt-3">
                <h5 class="mb-3">إجراءات</h5>
                <?php if($application['status'] == 'pending'): ?>
                <a href="?cancel=<?php echo $application_id; ?>" class="btn btn-danger w-100 mb-2" 
                   onclick="return confirm('هل أنت متأكدة من إلغاء هذا الطلب؟')">
                    <i class="fas fa-times ms-2"></i>
                    إلغاء الطلب
                </a>
                <?php endif; ?>
                
                <a href="messages.php?action=compose&to_entity=<?php echo $application['entity_id']; ?>" class="btn-outline-custom w-100">
                    <i class="fas fa-envelope ms-2"></i>
                    مراسلة الجهة
                </a>
            </div>
        </div>
    </div>
    
    <!-- ========================================= -->
    <!-- إنشاء طلب جديد -->
    <!-- ========================================= -->
    <?php elseif($action == 'create' && isset($opportunity)): ?>
    
    <div class="opportunity-info mb-4">
        <div class="card-custom">
            <h5 class="mb-3"><?php echo htmlspecialchars($opportunity['title_ar']); ?></h5>
            <p class="mb-2">
                <i class="fas fa-building ms-2"></i> <?php echo htmlspecialchars($opportunity['entity_name_ar']); ?>
                <?php if($opportunity['city']): ?> - <?php echo $opportunity['city']; ?><?php endif; ?>
            </p>
            <div class="d-flex gap-2">
                <span class="badge bg-primary"><?php echo $opportunity['major_name_ar'] ?? 'جميع التخصصات'; ?></span>
                <span class="badge bg-info"><?php echo $opportunity['duration_weeks'] ?? 8; ?> أسابيع</span>
            </div>
        </div>
    </div>
    
    <div class="card-custom">
        <h5 class="mb-4">تقديم الطلب</h5>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="seat_id" value="<?php echo $seat_id; ?>">
            
            <!-- السيرة الذاتية -->
            <div class="mb-4">
                <label class="form-label fw-bold">السيرة الذاتية <span class="text-danger">*</span></label>
                
                <?php if(!empty($cv_list)): ?>
                <div class="mb-3">
                    <?php foreach($cv_list as $cv): ?>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="cv_id" value="<?php echo $cv['id']; ?>" id="cv<?php echo $cv['id']; ?>">
                        <label class="form-check-label" for="cv<?php echo $cv['id']; ?>">
                            <i class="fas fa-file-pdf text-danger ms-1"></i>
                            <?php echo htmlspecialchars($cv['file_name']); ?>
                            <small class="text-muted">(<?php echo date('Y-m-d', strtotime($cv['created_at'])); ?>)</small>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <p class="text-muted small">أو</p>
                <?php endif; ?>
                
                <input type="file" class="form-control" name="new_cv" accept=".pdf,.doc,.docx">
                <small class="text-muted">PDF, Word - الحد الأقصى 5 ميجابايت</small>
            </div>
            
            <!-- رسالة تعريفية -->
            <div class="mb-4">
                <label class="form-label fw-bold">رسالة تعريفية (اختياري)</label>
                <textarea class="form-control" name="notes" rows="4" placeholder="اكتبي رسالة تعريفية أو أي معلومات إضافية..."></textarea>
            </div>
            
            <!-- الشروط -->
            <div class="mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="agree_terms" id="agreeTerms" required>
                    <label class="form-check-label" for="agreeTerms">
                        أقر بأن المعلومات المقدمة صحيحة وأوافق على الشروط
                    </label>
                </div>
            </div>
            
            <button type="submit" name="create_application" class="btn-primary-custom">
                <i class="fas fa-paper-plane ms-2"></i>
                تقديم الطلب
            </button>
        </form>
    </div>
    
    <!-- ========================================= -->
    <!-- قائمة جميع الطلبات -->
    <!-- ========================================= -->
    <?php else: ?>
    
    <!-- إحصائيات سريعة -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">إجمالي الطلبات</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card">
                <div class="stat-number text-warning"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">قيد المراجعة</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card">
                <div class="stat-number text-success"><?php echo $stats['approved']; ?></div>
                <div class="stat-label">مقبول</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card">
                <div class="stat-number text-info"><?php echo $stats['interview']; ?></div>
                <div class="stat-label">مقابلات</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card">
                <div class="stat-number text-danger"><?php echo $stats['rejected']; ?></div>
                <div class="stat-label">مرفوض</div>
            </div>
        </div>
    </div>
    
    <!-- قائمة الطلبات -->
    <?php if(empty($applications_list)): ?>
    <div class="card-custom text-center py-5">
        <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
        <h5>لا توجد طلبات</h5>
        <p class="text-muted mb-3">لم تقومي بتقديم أي طلبات بعد</p>
        <a href="opportunities.php" class="btn-primary-custom">
            <i class="fas fa-search ms-2"></i>
            استعرض فرص التدريب
        </a>
    </div>
    <?php else: ?>
    
    <div class="table-custom">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>الفرصة</th>
                    <th>الجهة</th>
                    <th>تاريخ التقديم</th>
                    <th>الحالة</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($applications_list as $index => $app): ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($app['title_ar']); ?></strong>
                    </td>
                    <td><?php echo htmlspecialchars($app['entity_name_ar']); ?></td>
                    <td><?php echo date('Y-m-d', strtotime($app['created_at'])); ?></td>
                    <td>
                        <span class="badge bg-<?php echo $app['status_color']; ?> p-2">
                            <?php 
                            $status_names = [
                                'pending' => 'قيد المراجعة',
                                'under_review' => 'قيد الدراسة',
                                'interview_scheduled' => 'مقابلة',
                                'accepted_by_entity' => 'مقبول من الجهة',
                                'approved' => 'معتمد',
                                'rejected' => 'مرفوض'
                            ];
                            echo $status_names[$app['status']] ?? $app['status'];
                            ?>
                        </span>
                    </td>
                    <td>
                        <a href="?action=view&id=<?php echo $app['id']; ?>" class="btn btn-sm btn-outline-primary">
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

<!-- CSS إضافي -->
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

.opportunity-info {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 5px 15px rgba(0,102,51,0.05);
    border-right: 4px solid var(--primary);
}

.badge {
    padding: 5px 10px;
    border-radius: 20px;
}

.table-custom td {
    vertical-align: middle;
}

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
    background: linear-gradient(135deg, #006633, #228B22);
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
}

.btn-primary-custom:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,102,51,0.3);
    color: white;
}
</style>

<?php
require_once '../../includes/student_footer.php';
?>