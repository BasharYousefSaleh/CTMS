<?php
// modules/coordinator/distribution.php - توزيع المتدربات على المشرفين
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
// معالجة توزيع متدربة على مشرف
// =============================================
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign'])) {
    $application_id = $_POST['application_id'];
    $supervisor_id = $_POST['supervisor_id'];
    
    $update = $pdo->prepare("UPDATE applications SET supervisor_id = ? WHERE id = ?");
    $update->execute([$supervisor_id, $application_id]);
    
    $_SESSION['success'] = '✅ تم توزيع المتدربة بنجاح';
    header('Location: distribution.php');
    exit();
}

// =============================================
// ✅ إضافة معالجة إلغاء التوزيع
// =============================================
if(isset($_GET['unassign']) && $_GET['unassign'] > 0) {
    $application_id = $_GET['unassign'];
    
    $update = $pdo->prepare("UPDATE applications SET supervisor_id = NULL WHERE id = ?");
    $update->execute([$application_id]);
    
    $_SESSION['success'] = '🔄 تم إلغاء توزيع المتدربة';
    header('Location: distribution.php');
    exit();
}

// =============================================
// جلب المتدربات المقبولات (التي لم يتم توزيعها بعد)
// =============================================
$students = $pdo->query("
    SELECT a.id as application_id, 
           a.student_id, 
           s.full_name_ar, 
           s.student_id as student_code,
           ts.title_ar, 
           te.entity_name_ar,
           te.id as entity_id
    FROM applications a
    JOIN students s ON a.student_id = s.id
    JOIN training_seats ts ON a.seat_id = ts.id
    JOIN training_entities te ON ts.entity_id = te.id
    WHERE a.status IN ('approved', 'accepted_by_entity') AND a.supervisor_id IS NULL
    ORDER BY a.created_at DESC
")->fetchAll();

// =============================================
// جلب المتدربات الموزعات (مع المشرفين)
// =============================================
$assigned = $pdo->query("
    SELECT a.id as application_id,
           a.*, 
           s.full_name_ar, 
           s.student_id as student_code,
           ts.title_ar, 
           te.entity_name_ar,
           te.id as entity_id,
           sup.id as supervisor_id,
           sup.full_name_ar as supervisor_name,
           sup.phone as supervisor_phone,
           sup.department as supervisor_department
    FROM applications a
    JOIN students s ON a.student_id = s.id
    JOIN training_seats ts ON a.seat_id = ts.id
    JOIN training_entities te ON ts.entity_id = te.id
    LEFT JOIN supervisors sup ON a.supervisor_id = sup.id
    WHERE a.status IN ('approved', 'accepted_by_entity') AND a.supervisor_id IS NOT NULL
    ORDER BY a.updated_at DESC
")->fetchAll();

// =============================================
// جلب المشرفين
// =============================================
$supervisors = $pdo->query("
    SELECT * FROM supervisors 
    ORDER BY full_name_ar
")->fetchAll();

// إحصائيات
$stats = [
    'total' => count($students) + count($assigned),
    'unassigned' => count($students),
    'assigned' => count($assigned)
];

$page_title = 'توزيع المتدربات';
require_once '../../includes/coordinator_header.php';

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>

<div class="content">
    <!-- ========================================= -->
    <!-- رأس الصفحة -->
    <!-- ========================================= -->
    <div class="page-header">
        <div>
            <h4 class="mb-1">توزيع المتدربات على المشرفين</h4>
            <p class="text-muted">إدارة توزيع المتدربات وإلغاء التوزيع</p>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-primary p-3">
                <i class="fas fa-users ms-2"></i> <?php echo $stats['total']; ?> إجمالي
            </span>
            <span class="badge bg-warning p-3">
                <i class="fas fa-clock ms-2"></i> <?php echo $stats['unassigned']; ?> غير موزعات
            </span>
            <span class="badge bg-success p-3">
                <i class="fas fa-check-circle ms-2"></i> <?php echo $stats['assigned']; ?> موزعات
            </span>
        </div>
    </div>
    
    <?php if($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <!-- ========================================= -->
    <!-- صفين: غير موزعات + موزعات -->
    <!-- ========================================= -->
    <div class="row">
        <!-- ========================================= -->
        <!-- المتدربات غير الموزعات -->
        <!-- ========================================= -->
        <div class="col-md-6">
            <div class="card-custom">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-user-clock ms-2" style="color: var(--primary);"></i>
                        في انتظار التوزيع
                        <span class="badge bg-warning ms-2"><?php echo count($students); ?></span>
                    </h5>
                </div>
                
                <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                    <?php if(empty($students)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <p class="text-muted">جميع المتدربات موزعات ✅</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($students as $s): ?>
                        <div class="student-item mb-3 p-3 border rounded" style="background: #fff9e6;">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($s['full_name_ar']); ?></h6>
                                    <div class="student-details">
                                        <small class="text-muted d-block">
                                            <i class="fas fa-id-card ms-1"></i> <?php echo $s['student_code']; ?>
                                        </small>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-briefcase ms-1"></i> <?php echo $s['title_ar']; ?>
                                        </small>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-building ms-1"></i> <?php echo $s['entity_name_ar']; ?>
                                        </small>
                                    </div>
                                </div>
                                
                                <button class="btn btn-sm btn-primary-custom assign-btn" 
                                        data-app="<?php echo $s['application_id']; ?>"
                                        data-name="<?php echo $s['full_name_ar']; ?>"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#assignModal">
                                    <i class="fas fa-user-plus ms-1"></i> توزيع
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- ========================================= -->
        <!-- المتدربات الموزعات (مع إضافة زر إلغاء التوزيع) -->
        <!-- ========================================= -->
        <div class="col-md-6">
            <div class="card-custom">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-user-check ms-2" style="color: var(--primary);"></i>
                        المتدربات الموزعات
                        <span class="badge bg-success ms-2"><?php echo count($assigned); ?></span>
                    </h5>
                </div>
                
                <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                    <?php if(empty($assigned)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                            <p class="text-muted">لا توجد طالبات موزعات بعد</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($assigned as $a): ?>
                        <div class="assigned-item mb-3 p-3 border rounded" style="background: #e8f5e9;">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($a['full_name_ar']); ?></h6>
                                    <div class="student-details">
                                        <small class="text-muted d-block">
                                            <i class="fas fa-id-card ms-1"></i> <?php echo $a['student_code']; ?>
                                        </small>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-briefcase ms-1"></i> <?php echo $a['title_ar']; ?>
                                        </small>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-building ms-1"></i> <?php echo $a['entity_name_ar']; ?>
                                        </small>
                                    </div>
                                    
                                    <!-- معلومات المشرف -->
                                    <div class="supervisor-info mt-2 p-2 bg-light rounded">
                                        <small class="text-success d-block">
                                            <i class="fas fa-user-tie ms-1"></i> 
                                            <strong>المشرف:</strong> <?php echo $a['supervisor_name'] ?? 'غير محدد'; ?>
                                        </small>
                                        <?php if($a['supervisor_phone']): ?>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-phone ms-1"></i> <?php echo $a['supervisor_phone']; ?>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- ✅ زر إلغاء التوزيع -->
                                <a href="?unassign=<?php echo $a['application_id']; ?>" 
                                   class="btn btn-sm btn-outline-danger" 
                                   onclick="return confirm('هل أنت متأكد من إلغاء توزيع المتدربة <?php echo addslashes($a['full_name_ar']); ?>؟')"
                                   title="إلغاء التوزيع">
                                    <i class="fas fa-undo-alt"></i> إلغاء
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ========================================= -->
    <!-- إحصائيات التوزيع -->
    <!-- ========================================= -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card-custom">
                <h5 class="mb-3">إحصائيات التوزيع</h5>
                <div class="row">
                    <div class="col-md-4">
                        <div class="stat-box text-center p-3 bg-light rounded">
                            <div class="stat-number"><?php echo $stats['total']; ?></div>
                            <div class="stat-label">إجمالي المتدربات</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-box text-center p-3 bg-light rounded">
                            <div class="stat-number text-warning"><?php echo $stats['unassigned']; ?></div>
                            <div class="stat-label">غير موزعات</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-box text-center p-3 bg-light rounded">
                            <div class="stat-number text-success"><?php echo $stats['assigned']; ?></div>
                            <div class="stat-label">موزعات</div>
                        </div>
                    </div>
                </div>
                
                <?php if($stats['total'] > 0): ?>
                <div class="progress mt-3" style="height: 20px;">
                    <?php $assigned_percent = round(($stats['assigned'] / $stats['total']) * 100); ?>
                    <div class="progress-bar bg-success" style="width: <?php echo $assigned_percent; ?>%;">
                        <?php echo $assigned_percent; ?>% موزعات
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ========================================= -->
<!-- مودال التوزيع -->
<!-- ========================================= -->
<div class="modal fade" id="assignModal" tabindex="-1" aria-labelledby="assignModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: var(--gradient); color: white;">
                <h5 class="modal-title" id="assignModalLabel">
                    <i class="fas fa-user-plus ms-2"></i> توزيع متدربة على مشرف
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="application_id" id="modalAppId">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">المتدربة</label>
                        <input type="text" class="form-control" id="modalStudentName" readonly disabled style="background: #f8f9fa;">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">اختر المشرف <span class="text-danger">*</span></label>
                        <select name="supervisor_id" class="form-select" required>
                            <option value="">-- اختر مشرف --</option>
                            <?php foreach($supervisors as $sup): ?>
                            <option value="<?php echo $sup['id']; ?>">
                                <?php echo $sup['full_name_ar']; ?> 
                                <?php if($sup['department']): ?>- <?php echo $sup['department']; ?><?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        سيتم إشعار المشرف والمتدربة بعملية التوزيع.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" name="assign" class="btn-primary-custom">
                        <i class="fas fa-check ms-2"></i> تأكيد التوزيع
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- CSS إضافي -->
<style>
.student-item, .assigned-item {
    transition: all 0.3s ease;
    border-right: 4px solid transparent;
}

.student-item:hover {
    transform: translateX(-5px);
    border-right-color: var(--primary);
    box-shadow: 0 5px 15px rgba(0,102,51,0.1);
}

.assigned-item:hover {
    transform: translateX(-5px);
    border-right-color: #dc3545;
    box-shadow: 0 5px 15px rgba(220,53,69,0.1);
}

.stat-box {
    transition: all 0.3s ease;
}

.stat-box:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,102,51,0.1);
}

.stat-number {
    font-size: 2rem;
    font-weight: 800;
    color: var(--primary);
    line-height: 1;
}

.stat-label {
    color: #6c757d;
    font-size: 0.9rem;
    margin-top: 5px;
}

.card-header {
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.btn-outline-danger {
    border: 1px solid #dc3545;
    color: #dc3545;
    background: white;
}

.btn-outline-danger:hover {
    background: #dc3545;
    color: white;
}

.progress {
    border-radius: 10px;
}

.progress-bar {
    border-radius: 10px;
    font-weight: 600;
}
</style>

<!-- JavaScript -->
<script>
// تمرير بيانات المتدربة إلى المودال
document.querySelectorAll('.assign-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const appId = this.dataset.app;
        const studentName = this.dataset.name;
        
        document.getElementById('modalAppId').value = appId;
        document.getElementById('modalStudentName').value = studentName;
    });
});
</script>

<?php require_once '../../includes/coordinator_footer.php'; ?>