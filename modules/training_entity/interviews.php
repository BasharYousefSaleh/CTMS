<?php
// modules/training_entity/interviews.php - إدارة المقابلات (صفحة واحدة)
session_start();
require_once '../../config/config.php';

// =============================================
// التحقق من تسجيل الدخول
// =============================================
if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'training_entity') {
    header('Location: ../auth/login.php?type=training_entity');
    exit();
}

// جلب بيانات الجهة
$stmt = $pdo->prepare("SELECT * FROM training_entities WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$entity = $stmt->fetch();

if(!$entity) {
    header('Location: ../auth/logout.php');
    exit();
}

// =============================================
// تعريف الثوابت العامة
// =============================================
$status_colors = [
    'scheduled' => 'primary',
    'completed' => 'success',
    'cancelled' => 'danger'
];

$status_text = [
    'scheduled' => 'مجدولة',
    'completed' => 'مكتملة',
    'cancelled' => 'ملغاة'
];

$types = [
    'online' => 'عن بعد',
    'in_person' => 'حضوري',
    'phone' => 'هاتفي'
];

// =============================================
// تحديد الصفحة الحالية
// =============================================
$action = $_GET['action'] ?? 'list';
$interview_id = $_GET['id'] ?? 0;
$application_id = $_GET['application_id'] ?? 0;
$status_filter = $_GET['status'] ?? 'all';

// =============================================
// معالجة إنشاء مقابلة جديدة
// =============================================
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_interview'])) {
    
    $application_id = $_POST['application_id'];
    $interview_date = $_POST['interview_date'];
    $interview_time = $_POST['interview_time'];
    $interview_type = $_POST['interview_type'];
    $meeting_link = $_POST['meeting_link'] ?? '';
    $location = $_POST['location'] ?? '';
    $interviewer_name = $_POST['interviewer_name'] ?? $entity['contact_person'];
    $notes = $_POST['notes'] ?? '';
    
    $datetime = $interview_date . ' ' . $interview_time . ':00';
    
    try {
        $insert = $pdo->prepare("
            INSERT INTO interviews 
            (application_id, interview_date, interview_type, meeting_link, location, interviewer_name, feedback, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', NOW())
        ");
        $insert->execute([
            $application_id, $datetime, $interview_type, $meeting_link, $location, $interviewer_name, $notes
        ]);
        
        $update = $pdo->prepare("UPDATE applications SET status = 'interview_scheduled' WHERE id = ?");
        $update->execute([$application_id]);
        
        $_SESSION['success'] = '✅ تم جدولة المقابلة بنجاح';
        header('Location: interviews.php');
        exit();
        
    } catch(Exception $e) {
        $_SESSION['error'] = '❌ حدث خطأ: ' . $e->getMessage();
    }
}

// =============================================
// معالجة تحديث مقابلة
// =============================================
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_interview'])) {
    
    $interview_id = $_POST['interview_id'];
    $status = $_POST['status'];
    $result = $_POST['result'] ?? null;
    $feedback = $_POST['feedback'] ?? '';
    
    $update = $pdo->prepare("
        UPDATE interviews SET status = ?, result = ?, feedback = ? WHERE id = ?
    ");
    $update->execute([$status, $result, $feedback, $interview_id]);
    
    $_SESSION['success'] = '✅ تم تحديث المقابلة';
    header('Location: interviews.php?action=view&id=' . $interview_id);
    exit();
}

// =============================================
// معالجة حذف مقابلة
// =============================================
if(isset($_GET['delete']) && $_GET['delete'] > 0) {
    $interview_id = $_GET['delete'];
    
    $delete = $pdo->prepare("DELETE FROM interviews WHERE id = ?");
    $delete->execute([$interview_id]);
    
    $_SESSION['success'] = '✅ تم حذف المقابلة';
    header('Location: interviews.php');
    exit();
}

// =============================================
// جلب المتدربات المرشحات (لصفحة الإنشاء)
// =============================================
$candidates = $pdo->prepare("
    SELECT a.id as application_id, s.full_name_ar, s.student_id, ts.title_ar
    FROM applications a
    JOIN students s ON a.student_id = s.id
    JOIN training_seats ts ON a.seat_id = ts.id
    WHERE ts.entity_id = ? AND a.status IN ('pending', 'under_review', 'accepted_by_entity')
    ORDER BY a.created_at DESC
");
$candidates->execute([$entity['id']]);
$candidates_list = $candidates->fetchAll();

// =============================================
// جلب المقابلات (للقائمة)
// =============================================
$sql = "
    SELECT i.*, s.full_name_ar, s.student_id, ts.title_ar, a.id as application_id
    FROM interviews i
    JOIN applications a ON i.application_id = a.id
    JOIN students s ON a.student_id = s.id
    JOIN training_seats ts ON a.seat_id = ts.id
    WHERE ts.entity_id = ?
";
$params = [$entity['id']];

if($status_filter != 'all') {
    $sql .= " AND i.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY i.interview_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$interviews = $stmt->fetchAll();

// =============================================
// جلب مقابلة محددة للعرض
// =============================================
$interview = null;
if($action == 'view' && $interview_id > 0) {
    $stmt = $pdo->prepare("
        SELECT i.*, s.full_name_ar, s.student_id, s.phone, ts.title_ar, a.id as application_id
        FROM interviews i
        JOIN applications a ON i.application_id = a.id
        JOIN students s ON a.student_id = s.id
        JOIN training_seats ts ON a.seat_id = ts.id
        WHERE i.id = ? AND ts.entity_id = ?
    ");
    $stmt->execute([$interview_id, $entity['id']]);
    $interview = $stmt->fetch();
}

// =============================================
// إحصائيات
// =============================================
$stats = [
    'total' => count($interviews),
    'scheduled' => count(array_filter($interviews, fn($i) => $i['status'] == 'scheduled')),
    'completed' => count(array_filter($interviews, fn($i) => $i['status'] == 'completed')),
    'cancelled' => count(array_filter($interviews, fn($i) => $i['status'] == 'cancelled')),
];

$page_title = 'المقابلات';
require_once '../../includes/entity_header.php';

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h4 class="mb-1">المقابلات</h4>
            <p class="text-muted">إدارة ومتابعة جميع المقابلات</p>
        </div>
        <?php if($action == 'list'): ?>
        <button class="btn-primary-custom" onclick="window.location.href='?action=create'">
            <i class="fas fa-plus-circle ms-2"></i> جدولة مقابلة جديدة
        </button>
        <?php else: ?>
        <a href="interviews.php" class="btn-outline-custom">
            <i class="fas fa-arrow-right ms-2"></i> العودة
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
    <!-- إنشاء مقابلة جديدة -->
    <!-- ========================================= -->
    <?php if($action == 'create'): ?>
    
    <div class="card-custom">
        <h5 class="mb-4">جدولة مقابلة جديدة</h5>
        
        <form method="POST">
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="form-label fw-bold">المتدربة <span class="text-danger">*</span></label>
                    <select class="form-select" name="application_id" required>
                        <option value="">-- اختر المتدربة --</option>
                        <?php foreach($candidates_list as $app): ?>
                        <option value="<?php echo $app['application_id']; ?>" <?php echo ($application_id == $app['application_id']) ? 'selected' : ''; ?>>
                            <?php echo $app['full_name_ar']; ?> - <?php echo $app['title_ar']; ?> (<?php echo $app['student_id']; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">تاريخ المقابلة</label>
                    <input type="date" class="form-control" name="interview_date" min="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">وقت المقابلة</label>
                    <input type="time" class="form-control" name="interview_time" value="10:00" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">نوع المقابلة</label>
                    <select class="form-select" name="interview_type" id="interviewType">
                        <option value="online">عن بعد</option>
                        <option value="in_person">حضوري</option>
                        <option value="phone">هاتفي</option>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3" id="linkField">
                    <label class="form-label fw-bold">رابط المقابلة</label>
                    <input type="url" class="form-control" name="meeting_link" placeholder="https://meet.google.com/...">
                </div>
                
                <div class="col-md-6 mb-3" id="locationField" style="display: none;">
                    <label class="form-label fw-bold">مكان المقابلة</label>
                    <input type="text" class="form-control" name="location" placeholder="العنوان أو المكان">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">اسم المسؤول</label>
                    <input type="text" class="form-control" name="interviewer_name" value="<?php echo $entity['contact_person']; ?>">
                </div>
                
                <div class="col-12 mb-3">
                    <label class="form-label fw-bold">ملاحظات</label>
                    <textarea class="form-control" name="notes" rows="3" placeholder="أي معلومات إضافية..."></textarea>
                </div>
                
                <div class="col-12">
                    <button type="submit" name="create_interview" class="btn-primary-custom">
                        <i class="fas fa-calendar-check ms-2"></i> جدولة المقابلة
                    </button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- ========================================= -->
    <!-- عرض تفاصيل مقابلة -->
    <!-- ========================================= -->
    <?php elseif($action == 'view' && $interview): ?>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card-custom">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h5>تفاصيل المقابلة</h5>
                    <span class="badge bg-<?php echo $status_colors[$interview['status']]; ?> p-3">
                        <?php echo $status_text[$interview['status']]; ?>
                    </span>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <small class="text-muted">المتدربة</small>
                        <p class="fw-bold"><?php echo $interview['full_name_ar']; ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <small class="text-muted">الرقم الجامعي</small>
                        <p class="fw-bold"><?php echo $interview['student_id']; ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <small class="text-muted">الفرصة التدريبية</small>
                        <p class="fw-bold"><?php echo $interview['title_ar']; ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <small class="text-muted">رقم الجوال</small>
                        <p class="fw-bold"><?php echo $interview['phone'] ?? 'غير متوفر'; ?></p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <small class="text-muted">تاريخ المقابلة</small>
                        <p class="fw-bold"><?php echo date('Y-m-d', strtotime($interview['interview_date'])); ?></p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <small class="text-muted">وقت المقابلة</small>
                        <p class="fw-bold"><?php echo date('H:i', strtotime($interview['interview_date'])); ?></p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <small class="text-muted">نوع المقابلة</small>
                        <p class="fw-bold">
                            <?php echo $types[$interview['interview_type']] ?? $interview['interview_type']; ?>
                        </p>
                    </div>
                    
                    <?php if($interview['interview_type'] == 'online' && $interview['meeting_link']): ?>
                    <div class="col-12 mb-3">
                        <small class="text-muted">رابط المقابلة</small>
                        <p class="fw-bold">
                            <a href="<?php echo $interview['meeting_link']; ?>" target="_blank"><?php echo $interview['meeting_link']; ?></a>
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($interview['interview_type'] != 'online' && $interview['location']): ?>
                    <div class="col-12 mb-3">
                        <small class="text-muted">مكان المقابلة</small>
                        <p class="fw-bold"><?php echo $interview['location']; ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($interview['feedback']): ?>
                    <div class="col-12 mb-3">
                        <small class="text-muted">ملاحظات</small>
                        <p class="fw-bold"><?php echo nl2br($interview['feedback']); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($interview['result']): ?>
                    <div class="col-12 mb-3">
                        <small class="text-muted">النتيجة</small>
                        <p class="fw-bold">
                            <span class="badge bg-<?php echo $interview['result'] == 'passed' ? 'success' : 'danger'; ?>">
                                <?php echo $interview['result'] == 'passed' ? 'ناجح' : 'راسب'; ?>
                            </span>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- تحديث الحالة -->
            <div class="card-custom">
                <h6 class="mb-3">تحديث المقابلة</h6>
                
                <form method="POST">
                    <input type="hidden" name="interview_id" value="<?php echo $interview['id']; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">الحالة</label>
                        <select class="form-select" name="status">
                            <option value="scheduled" <?php echo $interview['status'] == 'scheduled' ? 'selected' : ''; ?>>مجدولة</option>
                            <option value="completed" <?php echo $interview['status'] == 'completed' ? 'selected' : ''; ?>>مكتملة</option>
                            <option value="cancelled" <?php echo $interview['status'] == 'cancelled' ? 'selected' : ''; ?>>ملغاة</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">النتيجة</label>
                        <select class="form-select" name="result">
                            <option value="">-- اختر --</option>
                            <option value="passed" <?php echo $interview['result'] == 'passed' ? 'selected' : ''; ?>>ناجح</option>
                            <option value="failed" <?php echo $interview['result'] == 'failed' ? 'selected' : ''; ?>>راسب</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">ملاحظات</label>
                        <textarea class="form-control" name="feedback" rows="3"><?php echo $interview['feedback']; ?></textarea>
                    </div>
                    
                    <button type="submit" name="update_interview" class="btn-primary-custom w-100 mb-2">
                        تحديث
                    </button>
                </form>
                
                <a href="?delete=<?php echo $interview['id']; ?>" class="btn btn-danger w-100" onclick="return confirm('حذف هذه المقابلة؟')">
                    <i class="fas fa-trash ms-2"></i> حذف
                </a>
            </div>
            
            <!-- رابط الطلب -->
            <div class="card-custom mt-3">
                <a href="applications.php?action=view&id=<?php echo $interview['application_id']; ?>" class="btn-outline-custom w-100 text-center">
                    <i class="fas fa-file-alt ms-2"></i> عرض الطلب
                </a>
            </div>
        </div>
    </div>
    
    <!-- ========================================= -->
    <!-- قائمة المقابلات -->
    <!-- ========================================= -->
    <?php else: ?>
    
    <!-- إحصائيات -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card" onclick="window.location.href='?status=all'">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">إجمالي المقابلات</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" onclick="window.location.href='?status=scheduled'">
                <div class="stat-number text-primary"><?php echo $stats['scheduled']; ?></div>
                <div class="stat-label">مجدولة</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" onclick="window.location.href='?status=completed'">
                <div class="stat-number text-success"><?php echo $stats['completed']; ?></div>
                <div class="stat-label">مكتملة</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" onclick="window.location.href='?status=cancelled'">
                <div class="stat-number text-danger"><?php echo $stats['cancelled']; ?></div>
                <div class="stat-label">ملغاة</div>
            </div>
        </div>
    </div>
    
    <!-- فلترة -->
    <div class="mb-4">
        <a href="?status=all" class="btn <?php echo $status_filter == 'all' ? 'btn-primary-custom' : 'btn-outline-custom'; ?> me-2">الكل</a>
        <a href="?status=scheduled" class="btn <?php echo $status_filter == 'scheduled' ? 'btn-primary' : 'btn-outline-primary'; ?> me-2">مجدولة</a>
        <a href="?status=completed" class="btn <?php echo $status_filter == 'completed' ? 'btn-success' : 'btn-outline-success'; ?> me-2">مكتملة</a>
        <a href="?status=cancelled" class="btn <?php echo $status_filter == 'cancelled' ? 'btn-danger' : 'btn-outline-danger'; ?>">ملغاة</a>
    </div>
    
    <!-- جدول المقابلات -->
    <?php if(empty($interviews)): ?>
        <div class="card-custom text-center py-5">
            <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
            <h5>لا توجد مقابلات</h5>
            <p class="text-muted">لم يتم جدولة أي مقابلات بعد</p>
            <a href="?action=create" class="btn-primary-custom">
                <i class="fas fa-plus-circle ms-2"></i> جدولة مقابلة جديدة
            </a>
        </div>
    <?php else: ?>
        <div class="table-custom">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>المتدربة</th>
                        <th>الفرصة</th>
                        <th>التاريخ</th>
                        <th>الوقت</th>
                        <th>النوع</th>
                        <th>الحالة</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($interviews as $i => $inv): ?>
                    <tr>
                        <td><?php echo $i+1; ?></td>
                        <td>
                            <strong><?php echo $inv['full_name_ar']; ?></strong>
                            <br><small><?php echo $inv['student_id']; ?></small>
                        </td>
                        <td><?php echo $inv['title_ar']; ?></td>
                        <td><?php echo date('Y-m-d', strtotime($inv['interview_date'])); ?></td>
                        <td><?php echo date('H:i', strtotime($inv['interview_date'])); ?></td>
                        <td>
                            <?php echo $types[$inv['interview_type']] ?? $inv['interview_type']; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $status_colors[$inv['status']]; ?>">
                                <?php echo $status_text[$inv['status']]; ?>
                            </span>
                        </td>
                        <td>
                            <a href="?action=view&id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-outline-primary">
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

<script>
    document.getElementById('interviewType')?.addEventListener('change', function() {
        const type = this.value;
        const linkField = document.getElementById('linkField');
        const locationField = document.getElementById('locationField');
        
        if(linkField && locationField) {
            if(type === 'online') {
                linkField.style.display = 'block';
                locationField.style.display = 'none';
            } else {
                linkField.style.display = 'none';
                locationField.style.display = 'block';
            }
        }
    });
</script>

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
</style>

<?php require_once '../../includes/entity_footer.php'; ?>