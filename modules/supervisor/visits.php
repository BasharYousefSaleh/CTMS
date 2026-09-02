<?php
// modules/supervisor/visits.php - إدارة الزيارات الميدانية
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
$visit_id = $_GET['id'] ?? 0;
$status_filter = $_GET['status'] ?? 'all';

// =============================================
// معالجة إضافة زيارة جديدة
// =============================================
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_visit'])) {
    
    $application_id = $_POST['application_id'];
    $student_id = $_POST['student_id'];
    $visit_date = $_POST['visit_date'];
    $visit_time = $_POST['visit_time'];
    $location = $_POST['location'];
    $notes = $_POST['notes'];
    
    $insert = $pdo->prepare("
        INSERT INTO visits 
        (application_id, supervisor_id, student_id, visit_date, visit_time, location, notes, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', NOW())
    ");
    $insert->execute([$application_id, $supervisor['id'], $student_id, $visit_date, $visit_time, $location, $notes]);
    
    $_SESSION['success'] = '✅ تم جدولة الزيارة بنجاح';
    header('Location: visits.php');
    exit();
}

// =============================================
// معالجة تحديث حالة الزيارة
// =============================================
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_visit'])) {
    
    $visit_id = $_POST['visit_id'];
    $status = $_POST['status'];
    $notes = $_POST['notes'];
    $report_file = null;
    
    // معالجة رفع تقرير الزيارة
    if(isset($_FILES['report_file']) && $_FILES['report_file']['error'] == 0) {
        $allowed = ['pdf', 'doc', 'docx'];
        $ext = strtolower(pathinfo($_FILES['report_file']['name'], PATHINFO_EXTENSION));
        
        if(in_array($ext, $allowed) && $_FILES['report_file']['size'] <= 5 * 1024 * 1024) {
            $filename = 'visit_' . $visit_id . '_' . time() . '.' . $ext;
            $upload_path = '../../uploads/visits/' . $filename;
            
            if(!is_dir('../../uploads/visits/')) {
                mkdir('../../uploads/visits/', 0777, true);
            }
            
            if(move_uploaded_file($_FILES['report_file']['tmp_name'], $upload_path)) {
                $report_file = $filename;
            }
        }
    }
    
    if($report_file) {
        $update = $pdo->prepare("
            UPDATE visits SET 
                status = ?, 
                notes = ?, 
                report_file = ? 
            WHERE id = ? AND supervisor_id = ?
        ");
        $update->execute([$status, $notes, $report_file, $visit_id, $supervisor['id']]);
    } else {
        $update = $pdo->prepare("
            UPDATE visits SET 
                status = ?, 
                notes = ? 
            WHERE id = ? AND supervisor_id = ?
        ");
        $update->execute([$status, $notes, $visit_id, $supervisor['id']]);
    }
    
    $_SESSION['success'] = '✅ تم تحديث الزيارة بنجاح';
    header('Location: visits.php?action=view&id=' . $visit_id);
    exit();
}

// =============================================
// معالجة إلغاء زيارة
// =============================================
if(isset($_GET['cancel']) && $_GET['cancel'] > 0) {
    $visit_id = $_GET['cancel'];
    
    $update = $pdo->prepare("
        UPDATE visits SET 
            status = 'cancelled' 
        WHERE id = ? AND supervisor_id = ?
    ");
    $update->execute([$visit_id, $supervisor['id']]);
    
    $_SESSION['success'] = '🔄 تم إلغاء الزيارة';
    header('Location: visits.php');
    exit();
}

// =============================================
// جلب المتدربات المتاحة للزيارة
// =============================================
$students = $pdo->prepare("
    SELECT 
        a.id as application_id,
        s.id as student_id,
        s.full_name_ar,
        s.student_id as student_code,
        ts.title_ar,
        te.entity_name_ar,
        te.city,
        te.address
    FROM applications a
    JOIN students s ON a.student_id = s.id
    JOIN training_seats ts ON a.seat_id = ts.id
    JOIN training_entities te ON ts.entity_id = te.id
    WHERE a.supervisor_id = ? AND a.status = 'approved'
    ORDER BY s.full_name_ar
");
$students->execute([$supervisor['id']]);
$students_list = $students->fetchAll();

// =============================================
// جلب الزيارات
// =============================================
$sql = "
    SELECT 
        v.*,
        s.full_name_ar,
        s.student_id as student_code,
        ts.title_ar,
        te.entity_name_ar,
        te.city,
        te.address
    FROM visits v
    JOIN students s ON v.student_id = s.id
    JOIN applications a ON v.application_id = a.id
    JOIN training_seats ts ON a.seat_id = ts.id
    JOIN training_entities te ON ts.entity_id = te.id
    WHERE v.supervisor_id = ?
";
$params = [$supervisor['id']];

if($status_filter != 'all') {
    $sql .= " AND v.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY 
    CASE v.status 
        WHEN 'scheduled' THEN 1 
        WHEN 'completed' THEN 2 
        ELSE 3 
    END,
    v.visit_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$visits = $stmt->fetchAll();

// =============================================
// جلب زيارة محددة للعرض
// =============================================
$visit = null;
if($action == 'view' && $visit_id > 0) {
    $stmt = $pdo->prepare("
        SELECT 
            v.*,
            s.full_name_ar,
            s.student_id as student_code,
            s.phone,
            u.email,
            ts.title_ar,
            ts.description,
            ts.requirements,
            te.entity_name_ar,
            te.contact_person,
            te.contact_phone,
            te.city as entity_city,
            te.address as entity_address
        FROM visits v
        JOIN students s ON v.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN applications a ON v.application_id = a.id
        JOIN training_seats ts ON a.seat_id = ts.id
        JOIN training_entities te ON ts.entity_id = te.id
        WHERE v.id = ? AND v.supervisor_id = ?
    ");
    $stmt->execute([$visit_id, $supervisor['id']]);
    $visit = $stmt->fetch();
    
    if(!$visit) {
        header('Location: visits.php');
        exit();
    }
}

// =============================================
// إحصائيات
// =============================================
$stats = [
    'total' => count($visits),
    'scheduled' => count(array_filter($visits, fn($v) => $v['status'] == 'scheduled')),
    'completed' => count(array_filter($visits, fn($v) => $v['status'] == 'completed')),
    'cancelled' => count(array_filter($visits, fn($v) => $v['status'] == 'cancelled')),
];

$page_title = 'الزيارات الميدانية';
require_once '../../includes/supervisor_header.php';

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
            <h4 class="mb-1">الزيارات الميدانية</h4>
            <p class="text-muted">جدولة ومتابعة زيارات المتدربات</p>
        </div>
        <?php if($action == 'list'): ?>
        <button class="btn-primary-custom" onclick="showAddForm()">
            <i class="fas fa-plus-circle ms-2"></i> زيارة جديدة
        </button>
        <?php elseif($action == 'view'): ?>
        <a href="visits.php" class="btn-outline-custom">
            <i class="fas fa-arrow-right ms-2"></i> العودة للقائمة
        </a>
        <?php endif; ?>
    </div>
    
    <?php if($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <!-- ========================================= -->
    <!-- نموذج إضافة زيارة جديدة -->
    <!-- ========================================= -->
    <?php if($action == 'list'): ?>
    <div class="card-custom mb-4" id="addForm" style="display: none;">
        <h5 class="mb-3">📅 جدولة زيارة جديدة</h5>
        
        <form method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">المتدربة</label>
                    <select class="form-select" name="application_id" id="studentSelect" required>
                        <option value="">-- اختر المتدربة --</option>
                        <?php foreach($students_list as $s): ?>
                        <option value="<?php echo $s['application_id']; ?>" data-student="<?php echo $s['student_id']; ?>">
                            <?php echo $s['full_name_ar']; ?> - <?php echo $s['entity_name_ar']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="student_id" id="studentId">
                </div>
                
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">تاريخ الزيارة</label>
                    <input type="date" class="form-control" name="visit_date" min="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">وقت الزيارة</label>
                    <input type="time" class="form-control" name="visit_time" value="10:00" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">مكان الزيارة</label>
                    <input type="text" class="form-control" name="location" placeholder="مقر التدريب أو رابط الاجتماع" required>
                </div>
                
                <div class="col-12 mb-3">
                    <label class="form-label fw-bold">ملاحظات</label>
                    <textarea class="form-control" name="notes" rows="3" placeholder="ملاحظات أولية عن الزيارة..."></textarea>
                </div>
                
                <div class="col-12">
                    <button type="submit" name="add_visit" class="btn-primary-custom">
                        <i class="fas fa-calendar-check ms-2"></i> جدولة الزيارة
                    </button>
                    <button type="button" class="btn btn-secondary ms-2" onclick="hideAddForm()">إلغاء</button>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>
    
    <?php if($action == 'view' && $visit): ?>
    <!-- ========================================= -->
    <!-- عرض تفاصيل الزيارة -->
    <!-- ========================================= -->
    <div class="row">
        <div class="col-md-8">
            <div class="card-custom">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h5 class="mb-0">تفاصيل الزيارة</h5>
                    <?php
                    $status_colors = [
                        'scheduled' => 'primary',
                        'completed' => 'success',
                        'cancelled' => 'danger'
                    ];
                    $status_text = [
                        'scheduled' => '🔵 مجدولة',
                        'completed' => '✅ مكتملة',
                        'cancelled' => '❌ ملغاة'
                    ];
                    ?>
                    <span class="badge bg-<?php echo $status_colors[$visit['status']]; ?> p-3">
                        <?php echo $status_text[$visit['status']]; ?>
                    </span>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">المتدربة</small>
                        <p class="fw-bold"><?php echo htmlspecialchars($visit['full_name_ar']); ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">الرقم الجامعي</small>
                        <p class="fw-bold"><?php echo $visit['student_code']; ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">الفرصة التدريبية</small>
                        <p class="fw-bold"><?php echo htmlspecialchars($visit['title_ar']); ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">الجهة</small>
                        <p class="fw-bold"><?php echo htmlspecialchars($visit['entity_name_ar']); ?></p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">تاريخ الزيارة</small>
                        <p class="fw-bold"><?php echo $visit['visit_date']; ?></p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">وقت الزيارة</small>
                        <p class="fw-bold"><?php echo $visit['visit_time']; ?></p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">مكان الزيارة</small>
                        <p class="fw-bold"><?php echo htmlspecialchars($visit['location']); ?></p>
                    </div>
                    
                    <?php if($visit['notes']): ?>
                    <div class="col-12 mb-3">
                        <small class="text-muted d-block">ملاحظات الزيارة</small>
                        <div class="p-3 bg-light rounded">
                            <?php echo nl2br(htmlspecialchars($visit['notes'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($visit['report_file']): ?>
                    <div class="col-12">
                        <small class="text-muted d-block">تقرير الزيارة</small>
                        <div class="mt-2">
                            <a href="../../uploads/visits/<?php echo $visit['report_file']; ?>" class="btn btn-sm btn-outline-primary" download>
                                <i class="fas fa-download ms-2"></i> تحميل التقرير
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- معلومات التواصل -->
            <div class="card-custom">
                <h6 class="mb-3">معلومات التواصل</h6>
                <p><i class="fas fa-phone ms-2 text-muted"></i> <?php echo $visit['phone'] ?? 'غير محدد'; ?></p>
                <p><i class="fas fa-envelope ms-2 text-muted"></i> <?php echo $visit['email'] ?? 'غير محدد'; ?></p>
                <hr>
                <p><i class="fas fa-building ms-2 text-muted"></i> <?php echo $visit['entity_name_ar']; ?></p>
                <p><i class="fas fa-user-tie ms-2 text-muted"></i> <?php echo $visit['contact_person'] ?? 'غير محدد'; ?></p>
                <p><i class="fas fa-phone ms-2 text-muted"></i> <?php echo $visit['contact_phone'] ?? 'غير محدد'; ?></p>
            </div>
            
            <!-- تحديث الحالة -->
            <div class="card-custom mt-3">
                <h6 class="mb-3">تحديث الحالة</h6>
                
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="visit_id" value="<?php echo $visit['id']; ?>">
                    
                    <div class="mb-3">
                        <select class="form-select" name="status">
                            <option value="scheduled" <?php echo $visit['status'] == 'scheduled' ? 'selected' : ''; ?>>🔵 مجدولة</option>
                            <option value="completed" <?php echo $visit['status'] == 'completed' ? 'selected' : ''; ?>>✅ مكتملة</option>
                            <option value="cancelled" <?php echo $visit['status'] == 'cancelled' ? 'selected' : ''; ?>>❌ ملغاة</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <textarea class="form-control" name="notes" rows="3" placeholder="ملاحظات الزيارة..."><?php echo htmlspecialchars($visit['notes'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small">تقرير الزيارة (PDF, Word)</label>
                        <input type="file" class="form-control" name="report_file" accept=".pdf,.doc,.docx">
                    </div>
                    
                    <button type="submit" name="update_visit" class="btn-primary-custom w-100">
                        <i class="fas fa-save ms-2"></i> تحديث الزيارة
                    </button>
                </form>
                
                <?php if($visit['status'] == 'scheduled'): ?>
                <div class="mt-3">
                    <a href="?cancel=<?php echo $visit['id']; ?>" class="btn btn-outline-danger w-100" 
                       onclick="return confirm('هل أنت متأكد من إلغاء هذه الزيارة؟')">
                        <i class="fas fa-times ms-2"></i> إلغاء الزيارة
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <!-- ========================================= -->
    <!-- قائمة الزيارات -->
    <!-- ========================================= -->
    
    <!-- إحصائيات -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card" onclick="window.location.href='?status=all'">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">📊 إجمالي الزيارات</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" onclick="window.location.href='?status=scheduled'">
                <div class="stat-number text-primary"><?php echo $stats['scheduled']; ?></div>
                <div class="stat-label">🔵 مجدولة</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" onclick="window.location.href='?status=completed'">
                <div class="stat-number text-success"><?php echo $stats['completed']; ?></div>
                <div class="stat-label">✅ مكتملة</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" onclick="window.location.href='?status=cancelled'">
                <div class="stat-number text-danger"><?php echo $stats['cancelled']; ?></div>
                <div class="stat-label">❌ ملغاة</div>
            </div>
        </div>
    </div>
    
    <!-- فلترة -->
    <div class="filter-tabs mb-4">
        <a href="?status=all" class="btn <?php echo $status_filter == 'all' ? 'btn-primary-custom' : 'btn-outline-custom'; ?> me-2">
            الكل <span class="badge bg-light text-dark ms-1"><?php echo $stats['total']; ?></span>
        </a>
        <a href="?status=scheduled" class="btn <?php echo $status_filter == 'scheduled' ? 'btn-primary' : 'btn-outline-primary'; ?> me-2">
            مجدولة <span class="badge bg-light text-dark ms-1"><?php echo $stats['scheduled']; ?></span>
        </a>
        <a href="?status=completed" class="btn <?php echo $status_filter == 'completed' ? 'btn-success' : 'btn-outline-success'; ?> me-2">
            مكتملة <span class="badge bg-light text-dark ms-1"><?php echo $stats['completed']; ?></span>
        </a>
        <a href="?status=cancelled" class="btn <?php echo $status_filter == 'cancelled' ? 'btn-danger' : 'btn-outline-danger'; ?>">
            ملغاة <span class="badge bg-light text-dark ms-1"><?php echo $stats['cancelled']; ?></span>
        </a>
    </div>
    
    <!-- جدول الزيارات -->
    <?php if(empty($visits)): ?>
    <div class="card-custom text-center py-5">
        <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
        <h5>لا توجد زيارات</h5>
        <p class="text-muted">لم يتم جدولة أي زيارات بعد</p>
        <?php if(!empty($students_list)): ?>
        <button class="btn-primary-custom" onclick="showAddForm()">
            <i class="fas fa-plus-circle ms-2"></i> جدولة زيارة جديدة
        </button>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="table-custom">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>المتدربة</th>
                    <th>الجهة</th>
                    <th>التاريخ</th>
                    <th>الوقت</th>
                    <th>المكان</th>
                    <th>الحالة</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($visits as $i => $v): ?>
                <tr>
                    <td><?php echo $i+1; ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($v['full_name_ar']); ?></strong>
                        <br><small class="text-muted"><?php echo $v['student_code']; ?></small>
                    </td>
                    <td><?php echo htmlspecialchars($v['entity_name_ar']); ?></td>
                    <td><?php echo $v['visit_date']; ?></td>
                    <td><?php echo $v['visit_time']; ?></td>
                    <td><?php echo htmlspecialchars($v['location']); ?></td>
                    <td>
                        <?php
                        $status_badges = [
                            'scheduled' => 'primary',
                            'completed' => 'success',
                            'cancelled' => 'danger'
                        ];
                        $status_icons = [
                            'scheduled' => '🔵',
                            'completed' => '✅',
                            'cancelled' => '❌'
                        ];
                        ?>
                        <span class="badge bg-<?php echo $status_badges[$v['status']]; ?>">
                            <?php echo $status_icons[$v['status']] . ' ' . ($v['status'] == 'scheduled' ? 'مجدولة' : ($v['status'] == 'completed' ? 'مكتملة' : 'ملغاة')); ?>
                        </span>
                    </td>
                    <td>
                        <a href="?action=view&id=<?php echo $v['id']; ?>" class="btn btn-sm btn-outline-primary" title="عرض التفاصيل">
                            <i class="fas fa-eye"></i>
                        </a>
                        <?php if($v['status'] == 'scheduled'): ?>
                        <a href="?cancel=<?php echo $v['id']; ?>" class="btn btn-sm btn-outline-danger" title="إلغاء" 
                           onclick="return confirm('إلغاء هذه الزيارة؟')">
                            <i class="fas fa-times"></i>
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

<!-- JavaScript -->
<script>
function showAddForm() {
    document.getElementById('addForm').style.display = 'block';
    window.scrollTo({
        top: document.getElementById('addForm').offsetTop - 100,
        behavior: 'smooth'
    });
}

function hideAddForm() {
    document.getElementById('addForm').style.display = 'none';
}

document.getElementById('studentSelect')?.addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    const studentId = selected.getAttribute('data-student');
    document.getElementById('studentId').value = studentId || '';
});
</script>

<!-- CSS إضافي -->
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

.filter-tabs .btn {
    border-radius: 50px;
    padding: 8px 20px;
}

.btn-outline-primary {
    border: 2px solid #0d6efd;
    color: #0d6efd;
}
.btn-outline-primary:hover {
    background: #0d6efd;
    color: white;
}

.btn-outline-success {
    border: 2px solid #198754;
    color: #198754;
}
.btn-outline-success:hover {
    background: #198754;
    color: white;
}

.btn-outline-danger {
    border: 2px solid #dc3545;
    color: #dc3545;
}
.btn-outline-danger:hover {
    background: #dc3545;
    color: white;
}

.table td {
    vertical-align: middle;
}
</style>

<?php require_once '../../includes/supervisor_footer.php'; ?>