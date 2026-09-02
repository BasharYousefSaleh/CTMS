 <?php
// modules/student/reports.php - صفحة التقارير الأسبوعية
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
// تحديد الصفحة الحالية
// =============================================
$action = $_GET['action'] ?? 'list'; // list, create, view, edit
$report_id = $_GET['id'] ?? 0;

// =============================================
// جلب التدريب النشط للمتدربة
// =============================================
$active_training = null;
$stmt = $pdo->prepare("
    SELECT a.*, ts.title_ar, te.entity_name_ar,
           ts.duration_weeks,
           (SELECT MAX(week_number) FROM weekly_reports WHERE application_id = a.id) as last_week
    FROM applications a
    JOIN training_seats ts ON a.seat_id = ts.id
    JOIN training_entities te ON ts.entity_id = te.id
    WHERE a.student_id = ? AND a.status IN ('approved', 'accepted_by_entity')
    ORDER BY a.created_at DESC
    LIMIT 1
");
$stmt->execute([$student['id']]);
$active_training = $stmt->fetch();

// =============================================
// معالجة إنشاء تقرير جديد
// =============================================
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_report'])) {
    
    $application_id = $_POST['application_id'] ?? 0;
    $week_number = $_POST['week_number'] ?? 1;
    $start_date = $_POST['start_date'] ?? date('Y-m-d', strtotime('monday this week'));
    $end_date = $_POST['end_date'] ?? date('Y-m-d', strtotime('sunday this week'));
    $tasks_completed = trim($_POST['tasks_completed'] ?? '');
    $challenges = trim($_POST['challenges'] ?? '');
    $achievements = trim($_POST['achievements'] ?? '');
    $status = $_POST['status'] ?? 'submitted'; // draft or submitted
    
    if(empty($tasks_completed)) {
        $_SESSION['error'] = 'حقل المهام المنجزة مطلوب';
        header('Location: reports.php?action=create');
        exit();
    }
    
    try {
        // معالجة رفع الملف إذا وجد
        $report_file = null;
        if(isset($_FILES['report_file']) && $_FILES['report_file']['error'] == 0) {
            $allowed = ['pdf', 'doc', 'docx'];
            $ext = strtolower(pathinfo($_FILES['report_file']['name'], PATHINFO_EXTENSION));
            
            if(in_array($ext, $allowed) && $_FILES['report_file']['size'] <= 10 * 1024 * 1024) {
                $filename = 'report_' . $student['id'] . '_' . time() . '.' . $ext;
                $upload_path = '../../uploads/reports/' . $filename;
                
                if(!is_dir('../../uploads/reports/')) {
                    mkdir('../../uploads/reports/', 0777, true);
                }
                
                if(move_uploaded_file($_FILES['report_file']['tmp_name'], $upload_path)) {
                    $report_file = $filename;
                }
            }
        }
        
        // إدراج التقرير
        $insert = $pdo->prepare("
            INSERT INTO weekly_reports 
            (student_id, application_id, week_number, start_date, end_date, 
             tasks_completed, challenges, achievements, report_file, status, submitted_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $insert->execute([
            $student['id'],
            $application_id,
            $week_number,
            $start_date,
            $end_date,
            $tasks_completed,
            $challenges,
            $achievements,
            $report_file,
            $status
        ]);
        
        $new_id = $pdo->lastInsertId();
        
        $_SESSION['success'] = ($status == 'draft') ? 'تم حفظ المسودة بنجاح' : 'تم تقديم التقرير بنجاح';
        header('Location: reports.php?action=view&id=' . $new_id);
        exit();
        
    } catch(Exception $e) {
        $_SESSION['error'] = 'حدث خطأ: ' . $e->getMessage();
        header('Location: reports.php?action=create');
        exit();
    }
}

// =============================================
// معالجة تحديث تقرير
// =============================================
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_report'])) {
    
    $report_id = $_POST['report_id'] ?? 0;
    $tasks_completed = trim($_POST['tasks_completed'] ?? '');
    $challenges = trim($_POST['challenges'] ?? '');
    $achievements = trim($_POST['achievements'] ?? '');
    $status = $_POST['status'] ?? 'submitted';
    
    if(empty($tasks_completed)) {
        $_SESSION['error'] = 'حقل المهام المنجزة مطلوب';
        header('Location: reports.php?action=edit&id=' . $report_id);
        exit();
    }
    
    try {
        // معالجة رفع ملف جديد إذا وجد
        $report_file = null;
        if(isset($_FILES['report_file']) && $_FILES['report_file']['error'] == 0) {
            $allowed = ['pdf', 'doc', 'docx'];
            $ext = strtolower(pathinfo($_FILES['report_file']['name'], PATHINFO_EXTENSION));
            
            if(in_array($ext, $allowed) && $_FILES['report_file']['size'] <= 10 * 1024 * 1024) {
                $filename = 'report_' . $student['id'] . '_' . time() . '.' . $ext;
                $upload_path = '../../uploads/reports/' . $filename;
                
                if(!is_dir('../../uploads/reports/')) {
                    mkdir('../../uploads/reports/', 0777, true);
                }
                
                if(move_uploaded_file($_FILES['report_file']['tmp_name'], $upload_path)) {
                    $report_file = $filename;
                }
            }
        }
        
        // تحديث التقرير
        if($report_file) {
            $update = $pdo->prepare("
                UPDATE weekly_reports SET
                    tasks_completed = ?,
                    challenges = ?,
                    achievements = ?,
                    report_file = ?,
                    status = ?,
                    submitted_at = NOW()
                WHERE id = ? AND student_id = ?
            ");
            $update->execute([$tasks_completed, $challenges, $achievements, $report_file, $status, $report_id, $student['id']]);
        } else {
            $update = $pdo->prepare("
                UPDATE weekly_reports SET
                    tasks_completed = ?,
                    challenges = ?,
                    achievements = ?,
                    status = ?,
                    submitted_at = NOW()
                WHERE id = ? AND student_id = ?
            ");
            $update->execute([$tasks_completed, $challenges, $achievements, $status, $report_id, $student['id']]);
        }
        
        $_SESSION['success'] = 'تم تحديث التقرير بنجاح';
        header('Location: reports.php?action=view&id=' . $report_id);
        exit();
        
    } catch(Exception $e) {
        $_SESSION['error'] = 'حدث خطأ: ' . $e->getMessage();
        header('Location: reports.php?action=edit&id=' . $report_id);
        exit();
    }
}

// =============================================
// معالجة حذف تقرير (مسودة فقط)
// =============================================
if(isset($_GET['delete']) && $report_id > 0) {
    $delete = $pdo->prepare("DELETE FROM weekly_reports WHERE id = ? AND student_id = ? AND status = 'draft'");
    $delete->execute([$report_id, $student['id']]);
    
    $_SESSION['success'] = 'تم حذف المسودة';
    header('Location: reports.php');
    exit();
}

// =============================================
// جلب التقارير
// =============================================
$all_reports = $pdo->prepare("
    SELECT r.*, ts.title_ar, te.entity_name_ar
    FROM weekly_reports r
    JOIN applications a ON r.application_id = a.id
    JOIN training_seats ts ON a.seat_id = ts.id
    JOIN training_entities te ON ts.entity_id = te.id
    WHERE r.student_id = ?
    ORDER BY r.week_number DESC, r.created_at DESC
");
$all_reports->execute([$student['id']]);
$reports_list = $all_reports->fetchAll();

// =============================================
// جلب تقرير محدد للعرض
// =============================================
$report = null;
if($action == 'view' && $report_id > 0) {
    $stmt = $pdo->prepare("
        SELECT r.*, ts.title_ar, te.entity_name_ar,
               s.full_name_ar as supervisor_name
        FROM weekly_reports r
        JOIN applications a ON r.application_id = a.id
        JOIN training_seats ts ON a.seat_id = ts.id
        JOIN training_entities te ON ts.entity_id = te.id
        LEFT JOIN supervisors s ON a.supervisor_id = s.id
        WHERE r.id = ? AND r.student_id = ?
    ");
    $stmt->execute([$report_id, $student['id']]);
    $report = $stmt->fetch();
    
    if(!$report) {
        header('Location: reports.php');
        exit();
    }
}

// =============================================
// جلب تقرير للتعديل
// =============================================
$edit_report = null;
if($action == 'edit' && $report_id > 0) {
    $stmt = $pdo->prepare("
        SELECT * FROM weekly_reports 
        WHERE id = ? AND student_id = ? AND status = 'draft'
    ");
    $stmt->execute([$report_id, $student['id']]);
    $edit_report = $stmt->fetch();
    
    if(!$edit_report) {
        $_SESSION['error'] = 'لا يمكن تعديل هذا التقرير';
        header('Location: reports.php');
        exit();
    }
}

// =============================================
// إحصائيات التقارير
// =============================================
$stats = [
    'total' => count($reports_list),
    'draft' => count(array_filter($reports_list, fn($r) => $r['status'] == 'draft')),
    'submitted' => count(array_filter($reports_list, fn($r) => $r['status'] == 'submitted')),
    'reviewed' => count(array_filter($reports_list, fn($r) => $r['status'] == 'reviewed')),
    'approved' => count(array_filter($reports_list, fn($r) => $r['status'] == 'approved')),
];

$page_title = 'التقارير الأسبوعية';
require_once '../../includes/student_header.php';

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h4 class="mb-1">التقارير الأسبوعية</h4>
            <p class="text-muted mb-0">رفع ومتابعة تقارير التدريب</p>
        </div>
        <?php if($action == 'list'): ?>
            <?php if($active_training): ?>
            <a href="?action=create" class="btn-primary-custom">
                <i class="fas fa-plus-circle ms-2"></i>
                تقرير جديد
            </a>
            <?php endif; ?>
        <?php else: ?>
        <a href="reports.php" class="btn-outline-custom">
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
    <!-- إنشاء تقرير جديد -->
    <!-- ========================================= -->
    <?php if($action == 'create'): ?>
    
    <?php if(!$active_training): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle ms-2"></i>
        لا يوجد تدريب نشط حالياً. يجب أن يكون لديك تدريب مقبول لرفع التقارير.
        <a href="opportunities.php" class="alert-link">استعرض فرص التدريب</a>
    </div>
    <?php else: ?>
    
    <div class="card-custom">
        <h5 class="mb-4">تقرير أسبوعي جديد</h5>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="application_id" value="<?php echo $active_training['id']; ?>">
            <input type="hidden" name="week_number" value="<?php echo ($active_training['last_week'] ?? 0) + 1; ?>">
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">التدريب</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($active_training['title_ar']); ?>" readonly disabled>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">الجهة</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($active_training['entity_name_ar']); ?>" readonly disabled>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">الأسبوع</label>
                    <input type="text" class="form-control" value="الأسبوع <?php echo ($active_training['last_week'] ?? 0) + 1; ?>" readonly disabled>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">عدد الأسابيع المطلوبة</label>
                    <input type="text" class="form-control" value="<?php echo $active_training['duration_weeks'] ?? 8; ?> أسابيع" readonly disabled>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">تاريخ البداية</label>
                    <input type="date" class="form-control" name="start_date" 
                           value="<?php echo date('Y-m-d', strtotime('monday this week')); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">تاريخ النهاية</label>
                    <input type="date" class="form-control" name="end_date" 
                           value="<?php echo date('Y-m-d', strtotime('sunday this week')); ?>" required>
                </div>
                
                <div class="col-12 mb-3">
                    <label class="form-label fw-bold">المهام المنجزة <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="tasks_completed" rows="5" 
                              placeholder="اذكري المهام التي أنجزتها هذا الأسبوع..." required></textarea>
                </div>
                
                <div class="col-12 mb-3">
                    <label class="form-label fw-bold">التحديات التي واجهتك</label>
                    <textarea class="form-control" name="challenges" rows="3" 
                              placeholder="هل واجهتك أي صعوبات؟"></textarea>
                </div>
                
                <div class="col-12 mb-3">
                    <label class="form-label fw-bold">الإنجازات والمهارات المكتسبة</label>
                    <textarea class="form-control" name="achievements" rows="3" 
                              placeholder="ما الذي تعلمته أو أنجزته؟"></textarea>
                </div>
                
                <div class="col-12 mb-3">
                    <label class="form-label fw-bold">رفع ملف التقرير (PDF, Word)</label>
                    <input type="file" class="form-control" name="report_file" accept=".pdf,.doc,.docx">
                    <small class="text-muted">اختياري - الحد الأقصى 10 ميجابايت</small>
                </div>
                
                <div class="col-12">
                    <hr>
                    <button type="submit" name="create_report" value="submitted" class="btn-primary-custom">
                        <i class="fas fa-paper-plane ms-2"></i>
                        تقديم التقرير
                    </button>
                    <button type="submit" name="create_report" value="draft" class="btn-outline-custom ms-2">
                        <i class="fas fa-save ms-2"></i>
                        حفظ كمسودة
                    </button>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- ========================================= -->
    <!-- تعديل تقرير -->
    <!-- ========================================= -->
    <?php elseif($action == 'edit' && $edit_report): ?>
    
    <div class="card-custom">
        <h5 class="mb-4">تعديل التقرير - الأسبوع <?php echo $edit_report['week_number']; ?></h5>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="report_id" value="<?php echo $edit_report['id']; ?>">
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">تاريخ البداية</label>
                    <input type="date" class="form-control" name="start_date" 
                           value="<?php echo $edit_report['start_date']; ?>" readonly disabled>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">تاريخ النهاية</label>
                    <input type="date" class="form-control" name="end_date" 
                           value="<?php echo $edit_report['end_date']; ?>" readonly disabled>
                </div>
                
                <div class="col-12 mb-3">
                    <label class="form-label fw-bold">المهام المنجزة <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="tasks_completed" rows="5" required><?php echo htmlspecialchars($edit_report['tasks_completed']); ?></textarea>
                </div>
                
                <div class="col-12 mb-3">
                    <label class="form-label fw-bold">التحديات التي واجهتك</label>
                    <textarea class="form-control" name="challenges" rows="3"><?php echo htmlspecialchars($edit_report['challenges']); ?></textarea>
                </div>
                
                <div class="col-12 mb-3">
                    <label class="form-label fw-bold">الإنجازات والمهارات المكتسبة</label>
                    <textarea class="form-control" name="achievements" rows="3"><?php echo htmlspecialchars($edit_report['achievements']); ?></textarea>
                </div>
                
                <?php if($edit_report['report_file']): ?>
                <div class="col-12 mb-3">
                    <label class="form-label fw-bold">الملف الحالي</label>
                    <div class="d-flex align-items-center">
                        <i class="fas fa-file-pdf text-danger fa-2x ms-2"></i>
                        <span><?php echo $edit_report['report_file']; ?></span>
                        <a href="../../uploads/reports/<?php echo $edit_report['report_file']; ?>" class="btn btn-sm btn-outline-primary ms-3" download>
                            <i class="fas fa-download"></i> تحميل
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="col-12 mb-3">
                    <label class="form-label fw-bold">رفع ملف جديد (اختياري)</label>
                    <input type="file" class="form-control" name="report_file" accept=".pdf,.doc,.docx">
                </div>
                
                <div class="col-12">
                    <hr>
                    <button type="submit" name="update_report" value="submitted" class="btn-primary-custom">
                        <i class="fas fa-paper-plane ms-2"></i>
                        تقديم التقرير
                    </button>
                    <button type="submit" name="update_report" value="draft" class="btn-outline-custom ms-2">
                        <i class="fas fa-save ms-2"></i>
                        حفظ التعديلات
                    </button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- ========================================= -->
    <!-- عرض تقرير محدد -->
    <!-- ========================================= -->
    <?php elseif($action == 'view' && $report): ?>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card-custom">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h5>التقرير الأسبوعي <?php echo $report['week_number']; ?></h5>
                    <?php
                    $status_colors = [
                        'draft' => 'secondary',
                        'submitted' => 'info',
                        'reviewed' => 'warning',
                        'approved' => 'success'
                    ];
                    $status_text = [
                        'draft' => 'مسودة',
                        'submitted' => 'مقدم',
                        'reviewed' => 'تمت المراجعة',
                        'approved' => 'معتمد'
                    ];
                    ?>
                    <span class="badge bg-<?php echo $status_colors[$report['status']]; ?> p-3">
                        <?php echo $status_text[$report['status']]; ?>
                    </span>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <small class="text-muted d-block">التدريب</small>
                        <strong><?php echo htmlspecialchars($report['title_ar']); ?></strong>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">الجهة</small>
                        <strong><?php echo htmlspecialchars($report['entity_name_ar']); ?></strong>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <small class="text-muted d-block">تاريخ البداية</small>
                        <strong><?php echo $report['start_date']; ?></strong>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">تاريخ النهاية</small>
                        <strong><?php echo $report['end_date']; ?></strong>
                    </div>
                </div>
                
                <div class="mb-3">
                    <h6 class="fw-bold">المهام المنجزة</h6>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($report['tasks_completed'])); ?></p>
                </div>
                
                <?php if($report['challenges']): ?>
                <div class="mb-3">
                    <h6 class="fw-bold">التحديات</h6>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($report['challenges'])); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if($report['achievements']): ?>
                <div class="mb-3">
                    <h6 class="fw-bold">الإنجازات</h6>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($report['achievements'])); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if($report['supervisor_notes']): ?>
                <div class="mt-4 p-3 bg-light rounded">
                    <h6 class="fw-bold text-primary">ملاحظات المشرف</h6>
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($report['supervisor_notes'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- معلومات الملف -->
            <?php if($report['report_file']): ?>
            <div class="card-custom">
                <h5 class="mb-3">الملف المرفوع</h5>
                <div class="text-center">
                    <i class="fas fa-file-pdf text-danger fa-3x mb-2"></i>
                    <p><?php echo $report['report_file']; ?></p>
                    <a href="../../uploads/reports/<?php echo $report['report_file']; ?>" class="btn-primary-custom w-100" download>
                        <i class="fas fa-download ms-2"></i>
                        تحميل الملف
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- إجراءات -->
            <div class="card-custom mt-3">
                <h5 class="mb-3">إجراءات</h5>
                <?php if($report['status'] == 'draft'): ?>
                <a href="?action=edit&id=<?php echo $report['id']; ?>" class="btn-outline-custom w-100 mb-2">
                    <i class="fas fa-edit ms-2"></i>
                    تعديل التقرير
                </a>
                <a href="?delete=<?php echo $report['id']; ?>" class="btn btn-danger w-100" 
                   onclick="return confirm('هل أنت متأكدة من حذف هذه المسودة؟')">
                    <i class="fas fa-trash ms-2"></i>
                    حذف المسودة
                </a>
                <?php endif; ?>
            </div>
            
            <!-- معلومات إضافية -->
            <div class="card-custom mt-3">
                <h5 class="mb-3">معلومات</h5>
                <p class="mb-1"><small class="text-muted">تاريخ التقديم:</small></p>
                <p><?php echo date('Y-m-d H:i', strtotime($report['submitted_at'] ?? $report['created_at'])); ?></p>
                
                <?php if($report['supervisor_name']): ?>
                <p class="mb-1"><small class="text-muted">المشرف:</small></p>
                <p><?php echo htmlspecialchars($report['supervisor_name']); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- ========================================= -->
    <!-- قائمة التقارير -->
    <!-- ========================================= -->
    <?php else: ?>
    
    <!-- إحصائيات سريعة -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">الإجمالي</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card">
                <div class="stat-number text-secondary"><?php echo $stats['draft']; ?></div>
                <div class="stat-label">مسودة</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card">
                <div class="stat-number text-info"><?php echo $stats['submitted']; ?></div>
                <div class="stat-label">مقدم</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card">
                <div class="stat-number text-warning"><?php echo $stats['reviewed']; ?></div>
                <div class="stat-label">مراجع</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card">
                <div class="stat-number text-success"><?php echo $stats['approved']; ?></div>
                <div class="stat-label">معتمد</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card">
                <div class="stat-number"><?php echo $active_training ? ($active_training['last_week'] ?? 0) . '/' . ($active_training['duration_weeks'] ?? 8) : '0/0'; ?></div>
                <div class="stat-label">التقدم</div>
            </div>
        </div>
    </div>
    
    <!-- رسالة إذا ما في تدريب نشط -->
    <?php if(!$active_training): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle ms-2"></i>
        لا يوجد تدريب نشط حالياً. يمكنك رفع التقارير بعد قبول طلب تدريب.
        <a href="opportunities.php" class="alert-link">استعرض فرص التدريب</a>
    </div>
    <?php endif; ?>
    
    <!-- قائمة التقارير -->
    <?php if(empty($reports_list)): ?>
    <div class="card-custom text-center py-5">
        <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
        <h5>لا توجد تقارير</h5>
        <p class="text-muted mb-3">لم تقومي برفع أي تقارير بعد</p>
        <?php if($active_training): ?>
        <a href="?action=create" class="btn-primary-custom">
            <i class="fas fa-plus-circle ms-2"></i>
            إنشاء أول تقرير
        </a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    
    <div class="table-custom">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>الأسبوع</th>
                    <th>الفترة</th>
                    <th>تاريخ التقديم</th>
                    <th>الحالة</th>
                    <th>الملف</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($reports_list as $index => $rep): ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td><strong>الأسبوع <?php echo $rep['week_number']; ?></strong></td>
                    <td><?php echo $rep['start_date'] . ' إلى ' . $rep['end_date']; ?></td>
                    <td><?php echo date('Y-m-d', strtotime($rep['created_at'])); ?></td>
                    <td>
                        <?php
                        $status_colors = [
                            'draft' => 'secondary',
                            'submitted' => 'info',
                            'reviewed' => 'warning',
                            'approved' => 'success'
                        ];
                        $status_text = [
                            'draft' => 'مسودة',
                            'submitted' => 'مقدم',
                            'reviewed' => 'مراجع',
                            'approved' => 'معتمد'
                        ];
                        ?>
                        <span class="badge bg-<?php echo $status_colors[$rep['status']]; ?>">
                            <?php echo $status_text[$rep['status']]; ?>
                        </span>
                    </td>
                    <td>
                        <?php if($rep['report_file']): ?>
                        <a href="../../uploads/reports/<?php echo $rep['report_file']; ?>" class="btn btn-sm btn-outline-primary" download>
                            <i class="fas fa-download"></i>
                        </a>
                        <?php else: ?>
                        <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="?action=view&id=<?php echo $rep['id']; ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-eye"></i>
                        </a>
                        <?php if($rep['status'] == 'draft'): ?>
                        <a href="?action=edit&id=<?php echo $rep['id']; ?>" class="btn btn-sm btn-outline-warning">
                            <i class="fas fa-edit"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- CSS -->
<style>
.stat-card {
    background: white;
    border-radius: 10px;
    padding: 15px;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.stat-number {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--primary);
    line-height: 1;
}

.stat-label {
    color: #6c757d;
    font-size: 0.85rem;
    margin-top: 5px;
}

.badge {
    padding: 5px 10px;
    border-radius: 20px;
}
</style>

<?php
require_once '../../includes/student_footer.php';
?>