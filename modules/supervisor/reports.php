 <?php
// modules/supervisor/reports.php - مراجعة التقارير الأسبوعية
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
$report_id = $_GET['id'] ?? 0;
$status_filter = $_GET['status'] ?? 'all';
$student_filter = $_GET['student'] ?? 0;

// =============================================
// معالجة تحديث حالة التقرير (مراجعة/اعتماد)
// =============================================
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_report'])) {
    
    $report_id = $_POST['report_id'];
    $status = $_POST['status'];
    $supervisor_notes = $_POST['supervisor_notes'];
    
    $update = $pdo->prepare("
        UPDATE weekly_reports SET 
            status = ?, 
            supervisor_notes = ?,
            reviewed_at = NOW()
        WHERE id = ?
    ");
    $update->execute([$status, $supervisor_notes, $report_id]);
    
    $_SESSION['success'] = 'تم تحديث حالة التقرير بنجاح';
    header('Location: reports.php?action=view&id=' . $report_id);
    exit();
}

// =============================================
// جلب المتدربات المشرف عليها (للفلترة)
// =============================================
$students = $pdo->prepare("
    SELECT s.id, s.full_name_ar, s.student_id
    FROM applications a
    JOIN students s ON a.student_id = s.id
    WHERE a.supervisor_id = ? AND a.status IN ('approved', 'accepted_by_entity')
    ORDER BY s.full_name_ar
");
$students->execute([$supervisor['id']]);
$students_list = $students->fetchAll();

// =============================================
// جلب التقارير (✅ بدون تعليقات)
// =============================================
$sql = "
    SELECT 
        r.*,
        s.full_name_ar,
        s.student_id,
        u.email,
        ts.title_ar,
        te.entity_name_ar
    FROM weekly_reports r
    JOIN applications a ON r.application_id = a.id
    JOIN students s ON a.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN training_seats ts ON a.seat_id = ts.id
    JOIN training_entities te ON ts.entity_id = te.id
    WHERE a.supervisor_id = ?
";
$params = [$supervisor['id']];

if($status_filter != 'all') {
    $sql .= " AND r.status = ?";
    $params[] = $status_filter;
}

if($student_filter > 0) {
    $sql .= " AND s.id = ?";
    $params[] = $student_filter;
}

$sql .= " ORDER BY r.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll();

// =============================================
// إحصائيات التقارير
// =============================================
$stats = [
    'total' => count($reports),
    'submitted' => count(array_filter($reports, fn($r) => $r['status'] == 'submitted')),
    'reviewed' => count(array_filter($reports, fn($r) => $r['status'] == 'reviewed')),
    'approved' => count(array_filter($reports, fn($r) => $r['status'] == 'approved')),
    'draft' => count(array_filter($reports, fn($r) => $r['status'] == 'draft')),
];

// =============================================
// جلب تقرير محدد للعرض
// =============================================
$report = null;
if($action == 'view' && $report_id > 0) {
    $stmt = $pdo->prepare("
        SELECT 
            r.*,
            s.full_name_ar,
            s.student_id,
            s.phone,
            u.email,
            ts.title_ar,
            te.entity_name_ar,
            te.contact_person,
            te.contact_phone
        FROM weekly_reports r
        JOIN applications a ON r.application_id = a.id
        JOIN students s ON a.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN training_seats ts ON a.seat_id = ts.id
        JOIN training_entities te ON ts.entity_id = te.id
        WHERE r.id = ? AND a.supervisor_id = ?
    ");
    $stmt->execute([$report_id, $supervisor['id']]);
    $report = $stmt->fetch();
    
    if(!$report) {
        header('Location: reports.php');
        exit();
    }
}

$page_title = 'التقارير الأسبوعية';
require_once '../../includes/supervisor_header.php';

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>

<div class="content">
    <?php if($action == 'view' && $report): ?>
        <!-- ========================================= -->
        <!-- عرض تفاصيل التقرير -->
        <!-- ========================================= -->
        <div class="back-link mb-3">
            <a href="reports.php"><i class="fas fa-arrow-right ms-1"></i> العودة للقائمة</a>
        </div>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card-custom">
                    <div class="d-flex justify-content-between mb-3">
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
                            'submitted' => 'مقدم للمراجعة',
                            'reviewed' => 'تمت المراجعة',
                            'approved' => 'معتمد'
                        ];
                        ?>
                        <span class="badge bg-<?php echo $status_colors[$report['status']]; ?> p-3">
                            <?php echo $status_text[$report['status']]; ?>
                        </span>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <small class="text-muted">المتدربة</small>
                            <p><strong><?php echo $report['full_name_ar']; ?></strong></p>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted">الرقم الجامعي</small>
                            <p><strong><?php echo $report['student_id']; ?></strong></p>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted">الفرصة التدريبية</small>
                            <p><strong><?php echo $report['title_ar']; ?></strong></p>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted">الجهة</small>
                            <p><strong><?php echo $report['entity_name_ar']; ?></strong></p>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted">فترة التقرير</small>
                            <p><strong><?php echo $report['start_date'] . ' إلى ' . $report['end_date']; ?></strong></p>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted">تاريخ التقديم</small>
                            <p><strong><?php echo date('Y-m-d H:i', strtotime($report['created_at'])); ?></strong></p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h6 class="mb-3">المهام المنجزة</h6>
                    <p><?php echo nl2br($report['tasks_completed']); ?></p>
                    
                    <?php if($report['challenges']): ?>
                    <hr>
                    <h6 class="mb-3">التحديات</h6>
                    <p><?php echo nl2br($report['challenges']); ?></p>
                    <?php endif; ?>
                    
                    <?php if($report['achievements']): ?>
                    <hr>
                    <h6 class="mb-3">الإنجازات</h6>
                    <p><?php echo nl2br($report['achievements']); ?></p>
                    <?php endif; ?>
                    
                    <?php if($report['report_file']): ?>
                    <hr>
                    <h6 class="mb-3">الملف المرفوع</h6>
                    <a href="../../uploads/reports/<?php echo $report['report_file']; ?>" class="btn btn-sm btn-outline-primary" download>
                        <i class="fas fa-download ms-2"></i> تحميل الملف
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- معلومات التواصل -->
                <div class="card-custom">
                    <h6 class="mb-3">معلومات التواصل</h6>
                    <p><i class="fas fa-phone ms-2 text-muted"></i> <?php echo $report['phone']; ?></p>
                    <p><i class="fas fa-envelope ms-2 text-muted"></i> <?php echo $report['email']; ?></p>
                    <hr>
                    <p><i class="fas fa-building ms-2 text-muted"></i> <?php echo $report['entity_name_ar']; ?></p>
                    <p><i class="fas fa-user-tie ms-2 text-muted"></i> <?php echo $report['contact_person'] ?? 'غير محدد'; ?></p>
                </div>
                
                <!-- نموذج المراجعة -->
                <div class="card-custom mt-3">
                    <h6 class="mb-3">مراجعة التقرير</h6>
                    
                    <form method="POST">
                        <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">الحالة</label>
                            <select class="form-select" name="status">
                                <option value="submitted" <?php echo $report['status'] == 'submitted' ? 'selected' : ''; ?>>مقدم (في انتظار المراجعة)</option>
                                <option value="reviewed" <?php echo $report['status'] == 'reviewed' ? 'selected' : ''; ?>>تمت المراجعة</option>
                                <option value="approved" <?php echo $report['status'] == 'approved' ? 'selected' : ''; ?>>معتمد</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">ملاحظات المشرف</label>
                            <textarea class="form-control" name="supervisor_notes" rows="4"><?php echo $report['supervisor_notes']; ?></textarea>
                        </div>
                        
                        <button type="submit" name="update_report" class="btn-primary-custom w-100">
                            <i class="fas fa-save ms-2"></i> حفظ المراجعة
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <!-- ========================================= -->
        <!-- قائمة التقارير -->
        <!-- ========================================= -->
        <div class="page-header">
            <div>
                <h4 class="mb-1">التقارير الأسبوعية</h4>
                <p class="text-muted">مراجعة ومتابعة تقارير المتدربات</p>
            </div>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- إحصائيات سريعة -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="stat-card" onclick="window.location.href='?status=all'">
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">الإجمالي</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card" onclick="window.location.href='?status=submitted'">
                    <div class="stat-number text-info"><?php echo $stats['submitted']; ?></div>
                    <div class="stat-label">مقدم</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card" onclick="window.location.href='?status=reviewed'">
                    <div class="stat-number text-warning"><?php echo $stats['reviewed']; ?></div>
                    <div class="stat-label">تمت المراجعة</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card" onclick="window.location.href='?status=approved'">
                    <div class="stat-number text-success"><?php echo $stats['approved']; ?></div>
                    <div class="stat-label">معتمد</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card" onclick="window.location.href='?status=draft'">
                    <div class="stat-number text-secondary"><?php echo $stats['draft']; ?></div>
                    <div class="stat-label">مسودة</div>
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
                        <option value="<?php echo $s['id']; ?>" <?php echo $student_filter == $s['id'] ? 'selected' : ''; ?>>
                            <?php echo $s['full_name_ar']; ?> (<?php echo $s['student_id']; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">الحالة</label>
                    <select name="status" class="form-select">
                        <option value="all">جميع الحالات</option>
                        <option value="submitted" <?php echo $status_filter == 'submitted' ? 'selected' : ''; ?>>مقدم</option>
                        <option value="reviewed" <?php echo $status_filter == 'reviewed' ? 'selected' : ''; ?>>تمت المراجعة</option>
                        <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>معتمد</option>
                        <option value="draft" <?php echo $status_filter == 'draft' ? 'selected' : ''; ?>>مسودة</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn-primary-custom w-100">تصفية</button>
                    <a href="reports.php" class="btn-outline-custom ms-2">إعادة</a>
                </div>
            </form>
        </div>
        
        <!-- جدول التقارير -->
        <?php if(empty($reports)): ?>
            <div class="card-custom text-center py-5">
                <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
                <h5>لا توجد تقارير</h5>
                <p class="text-muted">لم يتم رفع أي تقارير بعد</p>
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
                            <th>الأسبوع</th>
                            <th>تاريخ التقديم</th>
                            <th>الحالة</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($reports as $i => $r): ?>
                        <tr>
                            <td><?php echo $i+1; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($r['full_name_ar']); ?></strong>
                                <br><small class="text-muted"><?php echo $r['student_id']; ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($r['title_ar']); ?></td>
                            <td><?php echo htmlspecialchars($r['entity_name_ar']); ?></td>
                            <td>الأسبوع <?php echo $r['week_number']; ?></td>
                            <td><?php echo date('Y-m-d', strtotime($r['created_at'])); ?></td>
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
                                    'reviewed' => 'تمت المراجعة',
                                    'approved' => 'معتمد'
                                ];
                                ?>
                                <span class="badge bg-<?php echo $status_colors[$r['status']]; ?>">
                                    <?php echo $status_text[$r['status']]; ?>
                                </span>
                            </td>
                            <td>
                                <a href="?action=view&id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-primary">
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
.table td {
    vertical-align: middle;
}
</style>

<?php require_once '../../includes/supervisor_footer.php'; ?>