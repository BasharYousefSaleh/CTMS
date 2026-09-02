 <?php
// modules/supervisor/evaluations.php - التقييم النهائي وإصدار الشهادات
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
$evaluation_id = $_GET['id'] ?? 0;
$application_id = $_GET['app_id'] ?? 0;
$student_filter = $_GET['student'] ?? 0;
$status_filter = $_GET['status'] ?? 'all';

// =============================================
// معالجة إضافة/تحديث تقييم (✅ تم التعديل)
// =============================================
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_evaluation'])) {
    
    $application_id = $_POST['application_id'];
    $student_id = $_POST['student_id'];
    $supervisor_evaluation = $_POST['supervisor_evaluation'];
    $supervisor_report = $_POST['supervisor_report'];
    $visit_date = $_POST['visit_date'] ?? null;
    $visit_notes = $_POST['visit_notes'] ?? null;
    $training_completed = isset($_POST['training_completed']) ? 1 : 0;
    
    // ✅ التحقق من أن student_id موجود في جدول students
    $check_student = $pdo->prepare("SELECT id FROM students WHERE id = ?");
    $check_student->execute([$student_id]);
    if(!$check_student->fetch()) {
        $_SESSION['error'] = '❌ المتدربة غير موجودة في النظام';
        header('Location: evaluations.php?action=create&app_id=' . $application_id);
        exit();
    }
    
    // التحقق من وجود تقييم سابق
    $check = $pdo->prepare("SELECT id FROM evaluations WHERE application_id = ?");
    $check->execute([$application_id]);
    $existing = $check->fetch();
    
    // حساب التقدير بناءً على الدرجة
    $final_grade = $supervisor_evaluation;
    $final_grade_letter = 'F';
    if($final_grade >= 95) $final_grade_letter = 'A+';
    elseif($final_grade >= 90) $final_grade_letter = 'A';
    elseif($final_grade >= 85) $final_grade_letter = 'B+';
    elseif($final_grade >= 80) $final_grade_letter = 'B';
    elseif($final_grade >= 75) $final_grade_letter = 'C+';
    elseif($final_grade >= 70) $final_grade_letter = 'C';
    elseif($final_grade >= 65) $final_grade_letter = 'D+';
    elseif($final_grade >= 60) $final_grade_letter = 'D';
    
    try {
        if($existing) {
            // تحديث التقييم الموجود
            $update = $pdo->prepare("
                UPDATE evaluations SET
                    supervisor_evaluation = ?,
                    supervisor_report = ?,
                    visit_date = ?,
                    visit_notes = ?,
                    training_completed = ?,
                    final_grade = ?,
                    final_grade_letter = ?,
                    updated_at = NOW()
                WHERE application_id = ?
            ");
            $update->execute([
                $supervisor_evaluation, 
                $supervisor_report, 
                $visit_date, 
                $visit_notes, 
                $training_completed,
                $final_grade,
                $final_grade_letter,
                $application_id
            ]);
            $_SESSION['success'] = '✅ تم تحديث التقييم بنجاح';
        } else {
            // ✅ إضافة تقييم جديد - مع التأكد من وجود student_id
            $insert = $pdo->prepare("
                INSERT INTO evaluations (
                    application_id, student_id, 
                    supervisor_evaluation, supervisor_report,
                    visit_date, visit_notes, training_completed,
                    final_grade, final_grade_letter,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $insert->execute([
                $application_id, 
                $student_id, 
                $supervisor_evaluation, 
                $supervisor_report, 
                $visit_date, 
                $visit_notes, 
                $training_completed,
                $final_grade,
                $final_grade_letter
            ]);
            $_SESSION['success'] = '✅ تم إضافة التقييم بنجاح';
        }
        
        // إذا تم إكمال التدريب، نضيف تاريخ الإكمال
        if($training_completed) {
            $update_app = $pdo->prepare("
                UPDATE applications SET completion_date = CURDATE() WHERE id = ?
            ");
            $update_app->execute([$application_id]);
        }
        
    } catch(Exception $e) {
        $_SESSION['error'] = '❌ حدث خطأ: ' . $e->getMessage();
    }
    
    header('Location: evaluations.php?action=view&app_id=' . $application_id);
    exit();
}

// =============================================
// معالجة إصدار شهادة
// =============================================
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['issue_certificate'])) {
    
    $application_id = $_POST['application_id'];
    $student_name = $_POST['student_name'];
    $training_title = $_POST['training_title'];
    $entity_name = $_POST['entity_name'];
    $completion_date = $_POST['completion_date'];
    $final_grade = $_POST['final_grade'];
    $final_grade_letter = $_POST['final_grade_letter'];
    
    // إنشاء رقم شهادة فريد
    $certificate_number = 'CERT-' . date('Y') . '-' . str_pad($application_id, 5, '0', STR_PAD_LEFT);
    
    // تحديث جدول التقييمات برقم الشهادة
    $update = $pdo->prepare("
        UPDATE evaluations SET 
            certificate_number = ?,
            certificate_issued_at = NOW(),
            issued_by = ?
        WHERE application_id = ?
    ");
    $update->execute([$certificate_number, $supervisor['id'], $application_id]);
    
    $_SESSION['success'] = '🎓 تم إصدار الشهادة بنجاح - رقم الشهادة: ' . $certificate_number;
    
    // إضافة علامة خاصة في الجلسة للتعامل مع رسالة الشهادة
    $_SESSION['certificate_issued'] = true;
    
    header('Location: evaluations.php?action=view&app_id=' . $application_id);
    exit();
}

// =============================================
// جلب المتدربات المشرف عليها
// =============================================
$students = $pdo->prepare("
    SELECT 
        a.id as application_id,
        s.id as student_id,
        s.full_name_ar,
        s.student_id as student_code,
        ts.title_ar,
        te.entity_name_ar,
        (SELECT id FROM evaluations WHERE application_id = a.id) as has_evaluation,
        (SELECT certificate_number FROM evaluations WHERE application_id = a.id) as certificate_number
    FROM applications a
    JOIN students s ON a.student_id = s.id
    JOIN training_seats ts ON a.seat_id = ts.id
    JOIN training_entities te ON ts.entity_id = te.id
    WHERE a.supervisor_id = ? AND a.status IN ('approved', 'accepted_by_entity')
    ORDER BY s.full_name_ar
");
$students->execute([$supervisor['id']]);
$students_list = $students->fetchAll();

// =============================================
// جلب التقييمات
// =============================================
$sql = "
    SELECT 
        e.*,
        s.full_name_ar,
        s.student_id,
        ts.title_ar,
        te.entity_name_ar
    FROM evaluations e
    JOIN applications a ON e.application_id = a.id
    JOIN students s ON a.student_id = s.id
    JOIN training_seats ts ON a.seat_id = ts.id
    JOIN training_entities te ON ts.entity_id = te.id
    WHERE a.supervisor_id = ?
";
$params = [$supervisor['id']];

if($student_filter > 0) {
    $sql .= " AND s.id = ?";
    $params[] = $student_filter;
}

if($status_filter == 'completed') {
    $sql .= " AND e.training_completed = 1";
} elseif($status_filter == 'pending') {
    $sql .= " AND e.training_completed = 0";
} elseif($status_filter == 'certified') {
    $sql .= " AND e.certificate_number IS NOT NULL";
}

$sql .= " ORDER BY e.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$evaluations = $stmt->fetchAll();

// =============================================
// إحصائيات
// =============================================
$stats = [
    'total' => count($evaluations),
    'completed' => count(array_filter($evaluations, fn($e) => $e['training_completed'] == 1)),
    'pending' => count(array_filter($evaluations, fn($e) => $e['training_completed'] == 0)),
    'certified' => count(array_filter($evaluations, fn($e) => !empty($e['certificate_number']))),
];

// =============================================
// جلب بيانات للتقييم (✅ تم التعديل)
// =============================================
$eval_student = null;
if($action == 'create' && $application_id > 0) {
    $stmt = $pdo->prepare("
        SELECT 
            a.id as application_id,
            s.id as student_id,
            s.full_name_ar,
            s.student_id as student_code,
            ts.title_ar,
            te.entity_name_ar
        FROM applications a
        JOIN students s ON a.student_id = s.id
        JOIN training_seats ts ON a.seat_id = ts.id
        JOIN training_entities te ON ts.entity_id = te.id
        WHERE a.id = ? AND a.supervisor_id = ?
    ");
    $stmt->execute([$application_id, $supervisor['id']]);
    $eval_student = $stmt->fetch();
    
    if(!$eval_student) {
        $_SESSION['error'] = '❌ المتدربة غير موجودة أو غير مسموح لك بتقييمها';
        header('Location: evaluations.php');
        exit();
    }
}

// =============================================
// جلب تقييم محدد للعرض
// =============================================
$evaluation = null;
if($action == 'view' && $application_id > 0) {
    $stmt = $pdo->prepare("
        SELECT 
            e.*,
            s.full_name_ar,
            s.student_id,
            s.phone,
            u.email,
            ts.title_ar,
            te.entity_name_ar,
            te.contact_person,
            te.contact_phone,
            a.completion_date
        FROM evaluations e
        JOIN applications a ON e.application_id = a.id
        JOIN students s ON a.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN training_seats ts ON a.seat_id = ts.id
        JOIN training_entities te ON ts.entity_id = te.id
        WHERE a.id = ? AND a.supervisor_id = ?
    ");
    $stmt->execute([$application_id, $supervisor['id']]);
    $evaluation = $stmt->fetch();
}

$page_title = 'التقييم النهائي والشهادات';
require_once '../../includes/supervisor_header.php';

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
$certificate_issued = $_SESSION['certificate_issued'] ?? false;
unset($_SESSION['error'], $_SESSION['success'], $_SESSION['certificate_issued']);
?>

<div class="content">
    <?php if($action == 'create' && $eval_student): ?>
        <!-- ========================================= -->
        <!-- نموذج إضافة تقييم جديد -->
        <!-- ========================================= -->
        <div class="back-link mb-3">
            <a href="evaluations.php"><i class="fas fa-arrow-right ms-1"></i> العودة للقائمة</a>
        </div>
        
        <div class="card-custom">
            <h5 class="mb-3">📝 تقييم المتدربة: <?php echo $eval_student['full_name_ar']; ?></h5>
            <p class="text-muted mb-4"><?php echo $eval_student['title_ar']; ?> - <?php echo $eval_student['entity_name_ar']; ?></p>
            
            <form method="POST">
                <input type="hidden" name="application_id" value="<?php echo $eval_student['application_id']; ?>">
                <input type="hidden" name="student_id" value="<?php echo $eval_student['student_id']; ?>">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">تقييم المشرف (من 100) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="supervisor_evaluation" min="0" max="100" step="0.01" required>
                        <small class="text-muted">أدخل الدرجة من 100</small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">تاريخ الزيارة الميدانية</label>
                        <input type="date" class="form-control" name="visit_date">
                    </div>
                    
                    <div class="col-12 mb-3">
                        <label class="form-label fw-bold">تقرير المشرف</label>
                        <textarea class="form-control" name="supervisor_report" rows="5" placeholder="اكتب تقييمك المفصل عن أداء المتدربة..."></textarea>
                    </div>
                    
                    <div class="col-12 mb-3">
                        <label class="form-label fw-bold">ملاحظات الزيارة</label>
                        <textarea class="form-control" name="visit_notes" rows="3" placeholder="ملاحظات حول الزيارة الميدانية..."></textarea>
                    </div>
                    
                    <div class="col-12 mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="training_completed" id="completed" value="1">
                            <label class="form-check-label" for="completed">
                                ✅ تم إكمال التدريب بنجاح
                            </label>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <button type="submit" name="save_evaluation" class="btn-primary-custom">
                            <i class="fas fa-save ms-2"></i> حفظ التقييم
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
    <?php elseif($action == 'view' && $evaluation): ?>
        <!-- ========================================= -->
        <!-- عرض تفاصيل التقييم وإصدار الشهادة -->
        <!-- ========================================= -->
        <div class="back-link mb-3">
            <a href="evaluations.php"><i class="fas fa-arrow-right ms-1"></i> العودة للقائمة</a>
        </div>
        
        <?php if($certificate_issued): ?>
        <div class="alert alert-success certificate-alert" style="border-right: 5px solid #28a745;">
            <i class="fas fa-certificate fa-2x float-end ms-3"></i>
            <h5 class="alert-heading">🎉 تم إصدار الشهادة بنجاح!</h5>
            <p>رقم الشهادة: <strong><?php echo $evaluation['certificate_number']; ?></strong></p>
            <hr>
            <p class="mb-0">يمكنك الآن طباعة الشهادة أو إرسالها للمتدربة.</p>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="float: left;"></button>
        </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card-custom">
                    <div class="d-flex justify-content-between mb-3">
                        <h5>📋 تقييم المتدربة: <?php echo $evaluation['full_name_ar']; ?></h5>
                        <?php if($evaluation['training_completed']): ?>
                            <span class="badge bg-success p-3">✅ مكتمل</span>
                        <?php else: ?>
                            <span class="badge bg-warning p-3">⏳ غير مكتمل</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <small class="text-muted">الرقم الجامعي</small>
                            <p><strong><?php echo $evaluation['student_id']; ?></strong></p>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted">الفرصة التدريبية</small>
                            <p><strong><?php echo $evaluation['title_ar']; ?></strong></p>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted">الجهة</small>
                            <p><strong><?php echo $evaluation['entity_name_ar']; ?></strong></p>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted">تاريخ التقييم</small>
                            <p><strong><?php echo date('Y-m-d', strtotime($evaluation['created_at'])); ?></strong></p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <h6>تقييم المشرف</h6>
                            <div class="display-4 fw-bold text-primary"><?php echo number_format($evaluation['supervisor_evaluation'], 1); ?></div>
                            <p>من 100</p>
                            <?php if($evaluation['final_grade_letter']): ?>
                            <span class="badge bg-info p-2">التقدير: <?php echo $evaluation['final_grade_letter']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if($evaluation['visit_date']): ?>
                        <div class="col-md-6 mb-3">
                            <h6>تاريخ الزيارة</h6>
                            <p><strong><?php echo $evaluation['visit_date']; ?></strong></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if($evaluation['supervisor_report']): ?>
                    <hr>
                    <h6 class="mb-3">📄 تقرير المشرف</h6>
                    <div class="p-3 bg-light rounded">
                        <?php echo nl2br($evaluation['supervisor_report']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($evaluation['visit_notes']): ?>
                    <hr>
                    <h6 class="mb-3">📌 ملاحظات الزيارة</h6>
                    <div class="p-3 bg-light rounded">
                        <?php echo nl2br($evaluation['visit_notes']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($evaluation['certificate_number']): ?>
                    <hr>
                    <div class="alert alert-success certificate-info">
                        <div class="row">
                            <div class="col-md-8">
                                <h6 class="mb-2">🎓 تم إصدار الشهادة</h6>
                                <p><strong>رقم الشهادة:</strong> <?php echo $evaluation['certificate_number']; ?></p>
                                <p><strong>تاريخ الإصدار:</strong> <?php echo date('Y-m-d H:i', strtotime($evaluation['certificate_issued_at'])); ?></p>
                            </div>
                            <div class="col-md-4 text-md-left">
                                <a href="certificate.php?app_id=<?php echo $application_id; ?>" class="btn btn-primary-custom" target="_blank">
                                    <i class="fas fa-print ms-2"></i> طباعة
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- معلومات التواصل -->
                <div class="card-custom">
                    <h6 class="mb-3">معلومات التواصل</h6>
                    <p><i class="fas fa-phone ms-2 text-muted"></i> <?php echo $evaluation['phone'] ?? 'غير محدد'; ?></p>
                    <p><i class="fas fa-envelope ms-2 text-muted"></i> <?php echo $evaluation['email'] ?? 'غير محدد'; ?></p>
                    <hr>
                    <p><i class="fas fa-building ms-2 text-muted"></i> <?php echo $evaluation['entity_name_ar']; ?></p>
                    <p><i class="fas fa-user-tie ms-2 text-muted"></i> <?php echo $evaluation['contact_person'] ?? 'غير محدد'; ?></p>
                </div>
                
                <!-- إجراءات -->
                <div class="card-custom mt-3">
                    <h6 class="mb-3">الإجراءات</h6>
                    
                    <a href="?action=create&app_id=<?php echo $application_id; ?>" class="btn-outline-custom w-100 text-center mb-2">
                        <i class="fas fa-edit ms-2"></i> تعديل التقييم
                    </a>
                    
                    <?php if($evaluation['training_completed'] && empty($evaluation['certificate_number'])): ?>
                    <!-- نموذج إصدار الشهادة -->
                    <button type="button" class="btn-primary-custom w-100" data-bs-toggle="modal" data-bs-target="#certificateModal">
                        <i class="fas fa-certificate ms-2"></i> إصدار شهادة
                    </button>
                    
                    <!-- مودال إصدار الشهادة -->
                    <div class="modal fade" id="certificateModal" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header" style="background: var(--gradient); color: white;">
                                    <h5 class="modal-title">🎓 إصدار شهادة تدريب</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST">
                                    <div class="modal-body">
                                        <input type="hidden" name="application_id" value="<?php echo $application_id; ?>">
                                        <input type="hidden" name="student_name" value="<?php echo $evaluation['full_name_ar']; ?>">
                                        <input type="hidden" name="training_title" value="<?php echo $evaluation['title_ar']; ?>">
                                        <input type="hidden" name="entity_name" value="<?php echo $evaluation['entity_name_ar']; ?>">
                                        <input type="hidden" name="completion_date" value="<?php echo $evaluation['completion_date'] ?? date('Y-m-d'); ?>">
                                        <input type="hidden" name="final_grade" value="<?php echo $evaluation['supervisor_evaluation']; ?>">
                                        <input type="hidden" name="final_grade_letter" value="<?php echo $evaluation['final_grade_letter']; ?>">
                                        
                                        <div class="text-center mb-4">
                                            <i class="fas fa-certificate fa-4x text-primary"></i>
                                        </div>
                                        
                                        <p>سيتم إصدار شهادة تدريب للمتدربة:</p>
                                        <p class="fw-bold"><?php echo $evaluation['full_name_ar']; ?></p>
                                        <p>عن التدريب: <?php echo $evaluation['title_ar']; ?></p>
                                        <p>بتقدير: <?php echo $evaluation['final_grade_letter']; ?> (<?php echo number_format($evaluation['supervisor_evaluation'], 1); ?>%)</p>
                                        
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle"></i>
                                            سيتم إنشاء رقم شهادة فريد تلقائياً.
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                        <button type="submit" name="issue_certificate" class="btn-primary-custom">
                                            <i class="fas fa-check ms-2"></i> تأكيد الإصدار
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <?php elseif($evaluation['certificate_number']): ?>
                    <div class="alert alert-success mt-3 text-center">
                        <i class="fas fa-certificate fa-2x mb-2"></i>
                        <p class="mb-1">تم إصدار الشهادة</p>
                        <small>رقم: <?php echo $evaluation['certificate_number']; ?></small>
                    </div>
                    
                    <!-- زر عرض الشهادة -->
                    <a href="certificate.php?app_id=<?php echo $application_id; ?>" class="btn-outline-custom w-100 text-center mt-2" target="_blank">
                        <i class="fas fa-eye ms-2"></i> عرض الشهادة
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <!-- ========================================= -->
        <!-- قائمة التقييمات -->
        <!-- ========================================= -->
        <div class="page-header">
            <div>
                <h4 class="mb-1">التقييم النهائي والشهادات</h4>
                <p class="text-muted">تقييم أداء المتدربات وإصدار الشهادات</p>
            </div>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="alert alert-success <?php echo strpos($success, 'شهادة') !== false ? 'certificate-success' : ''; ?>">
                <?php if(strpos($success, 'شهادة') !== false): ?>
                <i class="fas fa-certificate fa-2x float-end ms-3"></i>
                <?php endif; ?>
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="float: left;"></button>
            </div>
        <?php endif; ?>
        
        <!-- إحصائيات -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card" onclick="window.location.href='?status=all'">
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">📊 إجمالي التقييمات</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" onclick="window.location.href='?status=completed'">
                    <div class="stat-number text-success"><?php echo $stats['completed']; ?></div>
                    <div class="stat-label">✅ مكتمل</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" onclick="window.location.href='?status=pending'">
                    <div class="stat-number text-warning"><?php echo $stats['pending']; ?></div>
                    <div class="stat-label">⏳ غير مكتمل</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" onclick="window.location.href='?status=certified'">
                    <div class="stat-number text-info"><?php echo $stats['certified']; ?></div>
                    <div class="stat-label">🎓 صدرت شهادة</div>
                </div>
            </div>
        </div>
        
        <!-- فلترة -->
        <div class="card-custom mb-4">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">المتدربة</label>
                    <select name="student" class="form-select">
                        <option value="0">جميع المتدربات</option>
                        <?php foreach($students_list as $s): ?>
                        <option value="<?php echo $s['student_id']; ?>" <?php echo $student_filter == $s['student_id'] ? 'selected' : ''; ?>>
                            <?php echo $s['full_name_ar']; ?> (<?php echo $s['student_code']; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">الحالة</label>
                    <select name="status" class="form-select">
                        <option value="all">الكل</option>
                        <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>✅ مكتمل</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>⏳ غير مكتمل</option>
                        <option value="certified" <?php echo $status_filter == 'certified' ? 'selected' : ''; ?>>🎓 صدرت شهادة</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn-primary-custom w-100">تصفية</button>
                    <a href="evaluations.php" class="btn-outline-custom ms-2">إعادة</a>
                </div>
            </form>
        </div>
        
        <!-- قائمة المتدربات للتقييم -->
        <div class="row">
            <?php if(empty($students_list)): ?>
                <div class="col-12">
                    <div class="card-custom text-center py-5">
                        <i class="fas fa-star fa-4x text-muted mb-3"></i>
                        <h5>لا توجد طالبات</h5>
                        <p class="text-muted">ليس لديك طالبات لتقييمهن</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach($students_list as $s): ?>
                <div class="col-md-4 mb-3">
                    <div class="student-eval-card">
                        <div class="card-header">
                            <h6><?php echo $s['full_name_ar']; ?></h6>
                            <small class="text-muted"><?php echo $s['student_code']; ?></small>
                        </div>
                        <div class="card-body">
                            <p class="mb-2"><i class="fas fa-briefcase ms-2 text-muted"></i> <?php echo $s['title_ar']; ?></p>
                            <p class="mb-2"><i class="fas fa-building ms-2 text-muted"></i> <?php echo $s['entity_name_ar']; ?></p>
                            
                            <?php if($s['certificate_number']): ?>
                                <span class="badge bg-info mb-3">🎓 شهادة صادرة</span>
                                <a href="?action=view&app_id=<?php echo $s['application_id']; ?>" class="btn btn-sm btn-outline-primary w-100">
                                    <i class="fas fa-eye ms-2"></i> عرض التفاصيل
                                </a>
                            <?php elseif($s['has_evaluation']): ?>
                                <span class="badge bg-success mb-3">✅ تم التقييم</span>
                                <a href="?action=view&app_id=<?php echo $s['application_id']; ?>" class="btn btn-sm btn-outline-primary w-100">
                                    <i class="fas fa-eye ms-2"></i> عرض التقييم
                                </a>
                            <?php else: ?>
                                <span class="badge bg-warning mb-3">⏳ في انتظار التقييم</span>
                                <a href="?action=create&app_id=<?php echo $s['application_id']; ?>" class="btn btn-sm btn-primary-custom w-100">
                                    <i class="fas fa-star ms-2"></i> تقييم
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- جدول التقييمات -->
        <?php if(!empty($evaluations)): ?>
        <div class="table-custom mt-4">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>المتدربة</th>
                        <th>الفرصة</th>
                        <th>الجهة</th>
                        <th>التقييم</th>
                        <th>التقدير</th>
                        <th>الحالة</th>
                        <th>الشهادة</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($evaluations as $i => $e): ?>
                    <tr>
                        <td><?php echo $i+1; ?></td>
                        <td>
                            <strong><?php echo $e['full_name_ar']; ?></strong>
                            <br><small><?php echo $e['student_id']; ?></small>
                        </td>
                        <td><?php echo $e['title_ar']; ?></td>
                        <td><?php echo $e['entity_name_ar']; ?></td>
                        <td>
                            <strong><?php echo number_format($e['supervisor_evaluation'], 1); ?></strong>
                        </td>
                        <td>
                            <span class="badge bg-info"><?php echo $e['final_grade_letter']; ?></span>
                        </td>
                        <td>
                            <?php if($e['training_completed']): ?>
                                <span class="badge bg-success">مكتمل</span>
                            <?php else: ?>
                                <span class="badge bg-warning">غير مكتمل</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($e['certificate_number']): ?>
                                <span class="badge bg-info">🎓 صادرة</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">---</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="?action=view&app_id=<?php echo $e['application_id']; ?>" class="btn btn-sm btn-outline-primary">
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
    box-shadow: 0 5px 15px rgba(0,102,51,0.1);
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

.student-eval-card {
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,102,51,0.05);
    height: 100%;
    transition: all 0.3s ease;
}
.student-eval-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,102,51,0.1);
}
.student-eval-card .card-header {
    background: #f8f9fa;
    padding: 15px;
    border-bottom: 1px solid #dee2e6;
}
.student-eval-card .card-body {
    padding: 15px;
}

.btn-primary-custom {
    background: var(--gradient);
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 5px;
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

.modal-content {
    border-radius: 15px;
    border: none;
}

.modal-header {
    border-radius: 15px 15px 0 0;
}

.certificate-alert {
    background: #d4edda;
    border: 2px solid #28a745;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    position: relative;
}

.certificate-info {
    background: #e8f5e9;
    border-right: 5px solid #28a745;
}

.certificate-success {
    background: #d4edda;
    border: 2px solid #28a745;
    border-radius: 10px;
    padding: 15px;
    font-size: 1.1rem;
}
</style>

<!-- إضافة كود JavaScript لمنع إخفاء رسالة الشهادة -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // منع إخفاء رسائل الشهادة
    let certificateAlerts = document.querySelectorAll('.certificate-alert, .certificate-success, .alert-success:has(.fa-certificate)');
    
    certificateAlerts.forEach(function(alert) {
        // إلغاء أي timeout سابق
        alert.style.animation = 'none';
        alert.style.opacity = '1';
        alert.style.transition = 'none';
        
        // إضافة زر إغلاق إذا لم يكن موجوداً
        if (!alert.querySelector('.btn-close')) {
            let closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'btn-close';
            closeBtn.setAttribute('data-bs-dismiss', 'alert');
            closeBtn.setAttribute('aria-label', 'Close');
            closeBtn.style.cssText = 'float: left; position: relative; top: -10px;';
            alert.appendChild(closeBtn);
        }
    });
});
</script>

<?php require_once '../../includes/supervisor_footer.php'; ?>