 <?php
// modules/training_entity/applications.php - إدارة الطلبات مع إمكانية الموافقة/الرفض
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
// معالجة تحديث حالة الطلب (موافقة / رفض / مقابلة)
// =============================================
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_application'])) {
    
    $application_id = $_POST['application_id'];
    $new_status = $_POST['status'];
    $rejection_reason = $_POST['rejection_reason'] ?? '';
    $interview_date = $_POST['interview_date'] ?? '';
    $interview_time = $_POST['interview_time'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    try {
        // بدء معاملة
        $pdo->beginTransaction();
        
        // تحديث حالة الطلب
        $update = $pdo->prepare("UPDATE applications SET status = ?, notes = ? WHERE id = ?");
        $update->execute([$new_status, $notes, $application_id]);
        
        // إذا كانت الحالة "مقابلة"، أضف سجل مقابلة
        if($new_status == 'interview_scheduled' && !empty($interview_date) && !empty($interview_time)) {
            $datetime = $interview_date . ' ' . $interview_time . ':00';
            
            $interview = $pdo->prepare("
                INSERT INTO interviews (application_id, interview_date, interview_type, status, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $interview->execute([$application_id, $datetime, 'online', 'scheduled']);
        }
        
        // إذا كانت الحالة "مرفوض"، سجل سبب الرفض
        if($new_status == 'rejected' && !empty($rejection_reason)) {
            $notes_update = $pdo->prepare("UPDATE applications SET notes = CONCAT(notes, ?) WHERE id = ?");
            $reason_text = "\nسبب الرفض: " . $rejection_reason;
            $notes_update->execute([$reason_text, $application_id]);
        }
        
        // جلب معلومات المتدربة لإرسال إشعار
        $app_info = $pdo->prepare("
            SELECT a.student_id, s.user_id, ts.title_ar 
            FROM applications a
            JOIN students s ON a.student_id = s.id
            JOIN training_seats ts ON a.seat_id = ts.id
            WHERE a.id = ?
        ");
        $app_info->execute([$application_id]);
        $info = $app_info->fetch();
        
        // إرسال إشعار للمتدربة
        if($info) {
            // إعداد عنوان ورسالة الإشعار
            $title = '';
            $message = '';
            $type = 'info';
            
            switch($new_status) {
                case 'accepted_by_entity':
                    $title = 'تم قبول طلبك';
                    $message = 'تم قبول طلبك في فرصة ' . $info['title_ar'] . ' من قبل الجهة التدريبية';
                    $type = 'success';
                    break;
                case 'rejected':
                    $title = 'نأسف، تم رفض طلبك';
                    $message = 'لم يتم قبول طلبك في فرصة ' . $info['title_ar'] . ($rejection_reason ? ' - السبب: ' . $rejection_reason : '');
                    $type = 'error';
                    break;
                case 'interview_scheduled':
                    $title = 'موعد مقابلة';
                    $message = 'تم تحديد موعد مقابلة للفرصة ' . $info['title_ar'] . ' بتاريخ ' . $interview_date;
                    $type = 'info';
                    break;
                default:
                    $title = 'تحديث حالة الطلب';
                    $message = 'تم تحديث حالة طلبك في فرصة ' . $info['title_ar'];
                    $type = 'info';
            }
            
            $notify = $pdo->prepare("
                INSERT INTO notifications (user_id, user_type, title, message, type, related_link, related_id, created_at)
                VALUES (?, 'student', ?, ?, ?, ?, ?, NOW())
            ");
            $related_link = 'applications/view.php?id=' . $application_id;
            $notify->execute([
                $info['user_id'], 
                $title, 
                $message, 
                $type, 
                $related_link, 
                $application_id
            ]);
        }
        
        $pdo->commit();
        $_SESSION['success'] = 'تم تحديث حالة الطلب بنجاح';
        
    } catch(Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'حدث خطأ: ' . $e->getMessage();
    }
    
    header('Location: applications.php?view=' . $application_id);
    exit();
}

// =============================================
// جلب البيانات
// =============================================

$page_title = 'الطلبات الواردة';
require_once '../../includes/entity_header.php';

$view_id = $_GET['view'] ?? 0;
$seat_filter = $_GET['seat_id'] ?? 0;
// ✅ تعديل هنا: الفلتر الافتراضي 'all' بدلاً من 'pending'
$status_filter = $_GET['status'] ?? 'all';

// جلب قائمة المقاعد للفلترة
$seats = $pdo->prepare("SELECT id, title_ar FROM training_seats WHERE entity_id = ? ORDER BY created_at DESC");
$seats->execute([$entity['id']]);
$seats_list = $seats->fetchAll();

// إذا كان هناك عرض لطلب معين
if($view_id > 0) {
    $stmt = $pdo->prepare("
        SELECT a.*, 
               s.id as student_id, 
               s.full_name_ar, 
               s.student_id as student_code, 
               s.national_id, 
               s.gpa, 
               s.phone,
               s.major_id, 
               m.major_name_ar, 
               ts.title_ar, 
               ts.description, 
               ts.requirements,
               u.email
        FROM applications a
        JOIN students s ON a.student_id = s.id
        LEFT JOIN majors m ON s.major_id = m.id
        JOIN training_seats ts ON a.seat_id = ts.id
        JOIN users u ON s.user_id = u.id
        WHERE a.id = ? AND ts.entity_id = ?
    ");
    $stmt->execute([$view_id, $entity['id']]);
    $application = $stmt->fetch();
    
    if(!$application) {
        header('Location: applications.php');
        exit();
    }
    
    // جلب المستندات
    $docs = $pdo->prepare("SELECT * FROM attachments WHERE related_type = 'application' AND related_id = ?");
    $docs->execute([$view_id]);
    $attachments = $docs->fetchAll();
    
    // جلب المقابلات
    $interviews = $pdo->prepare("SELECT * FROM interviews WHERE application_id = ? ORDER BY interview_date DESC");
    $interviews->execute([$view_id]);
    $interview_list = $interviews->fetchAll();
}

// جلب جميع الطلبات
$sql = "
    SELECT a.*, 
           s.full_name_ar, 
           s.student_id as student_code, 
           s.gpa, 
           ts.title_ar,
           ts.id as seat_id
    FROM applications a
    JOIN students s ON a.student_id = s.id
    JOIN training_seats ts ON a.seat_id = ts.id
    WHERE ts.entity_id = ?
";
$params = [$entity['id']];

if($seat_filter > 0) {
    $sql .= " AND a.seat_id = ?";
    $params[] = $seat_filter;
}

if($status_filter != 'all') {
    $sql .= " AND a.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY a.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$applications = $stmt->fetchAll();

// حالات الطلب
$status_labels = [
    'pending' => 'جديد',
    'under_review' => 'قيد المراجعة',
    'interview_scheduled' => 'مقابلة',
    'accepted_by_entity' => 'مقبول',
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

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h4 class="mb-1">الطلبات الواردة</h4>
            <p class="text-muted mb-0">مراجعة وقبول أو رفض طلبات التدريب</p>
        </div>
        <?php if($view_id > 0): ?>
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
    
    <?php if($view_id > 0 && isset($application)): ?>
        <!-- ========================================= -->
        <!-- عرض تفاصيل الطلب + نموذج الموافقة/الرفض -->
        <!-- ========================================= -->
        <div class="row">
            <div class="col-md-8">
                <div class="card-custom">
                    <h5 class="mb-3">معلومات المتقدمة</h5>
                    <div class="row">
                        <div class="col-md-4 mb-2"><strong>الاسم:</strong></div>
                        <div class="col-md-8 mb-2"><?php echo htmlspecialchars($application['full_name_ar']); ?></div>
                        
                        <div class="col-md-4 mb-2"><strong>الرقم الجامعي:</strong></div>
                        <div class="col-md-8 mb-2"><?php echo htmlspecialchars($application['student_code']); ?></div>
                        
                        <div class="col-md-4 mb-2"><strong>المعدل:</strong></div>
                        <div class="col-md-8 mb-2"><?php echo $application['gpa']; ?></div>
                        
                        <div class="col-md-4 mb-2"><strong>رقم الجوال:</strong></div>
                        <div class="col-md-8 mb-2"><?php echo htmlspecialchars($application['phone']); ?></div>
                        
                        <div class="col-md-4 mb-2"><strong>البريد:</strong></div>
                        <div class="col-md-8 mb-2"><?php echo htmlspecialchars($application['email']); ?></div>
                        
                        <div class="col-md-4 mb-2"><strong>التخصص:</strong></div>
                        <div class="col-md-8 mb-2"><?php echo htmlspecialchars($application['major_name_ar'] ?? 'غير محدد'); ?></div>
                    </div>
                    
                    <?php if($application['notes']): ?>
                    <div class="mt-3 p-3 bg-light rounded">
                        <strong>ملاحظات المتقدمة:</strong>
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
            </div>
            
            <div class="col-md-4">
                <!-- حالة الطلب الحالية -->
                <div class="card-custom">
                    <h5 class="mb-3">حالة الطلب</h5>
                    <div class="text-center mb-3">
                        <span class="badge bg-<?php echo $status_colors[$application['status']]; ?> p-3 fs-6">
                            <?php echo $status_labels[$application['status']]; ?>
                        </span>
                    </div>
                    
                    <!-- تاريخ التقديم -->
                    <p class="text-muted small">
                        <i class="fas fa-calendar"></i>
                        تاريخ التقديم: <?php echo date('Y-m-d', strtotime($application['created_at'])); ?>
                    </p>
                </div>
                
                <!-- نموذج تحديث الحالة -->
                <div class="card-custom mt-3">
                    <h5 class="mb-3">تحديث حالة الطلب</h5>
                    
                    <form method="POST" id="applicationForm">
                        <input type="hidden" name="application_id" value="<?php echo $view_id; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">الإجراء</label>
                            <select class="form-select" name="status" id="statusSelect" onchange="toggleFields()">
                                <option value="under_review">🚧 قيد المراجعة</option>
                                <option value="interview_scheduled">📅 تحديد مقابلة</option>
                                <option value="accepted_by_entity">✅ قبول الطلب</option>
                                <option value="rejected">❌ رفض الطلب</option>
                            </select>
                        </div>
                        
                        <!-- حقل سبب الرفض (يظهر فقط عند اختيار رفض) -->
                        <div class="mb-3" id="rejectionField" style="display: none;">
                            <label class="form-label">سبب الرفض</label>
                            <textarea class="form-control" name="rejection_reason" rows="2" placeholder="اكتب سبب الرفض..."></textarea>
                        </div>
                        
                        <!-- حقول المقابلة (تظهر فقط عند اختيار مقابلة) -->
                        <div id="interviewFields" style="display: none;">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">تاريخ المقابلة</label>
                                    <input type="date" class="form-control" name="interview_date" min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">وقت المقابلة</label>
                                    <input type="time" class="form-control" name="interview_time">
                                </div>
                            </div>
                        </div>
                        
                        <!-- ملاحظات -->
                        <div class="mb-3">
                            <label class="form-label">ملاحظات (اختياري)</label>
                            <textarea class="form-control" name="notes" rows="2"><?php echo htmlspecialchars($application['notes'] ?? ''); ?></textarea>
                        </div>
                        
                        <button type="submit" name="update_application" class="btn-primary-custom w-100">
                            <i class="fas fa-save ms-2"></i>
                            حفظ التغييرات
                        </button>
                    </form>
                </div>
                
                <!-- المقابلات السابقة -->
                <?php if(!empty($interview_list)): ?>
                <div class="card-custom mt-3">
                    <h5 class="mb-3">المقابلات</h5>
                    <?php foreach($interview_list as $interview): ?>
                    <div class="border-bottom pb-2 mb-2">
                        <strong><?php echo date('Y-m-d', strtotime($interview['interview_date'])); ?></strong>
                        <br>
                        <small><?php echo date('H:i', strtotime($interview['interview_date'])); ?></small>
                        <span class="badge bg-<?php echo $interview['status'] == 'completed' ? 'success' : 'warning'; ?> float-start">
                            <?php echo $interview['status']; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
    <?php else: ?>
        <!-- ========================================= -->
        <!-- قائمة الطلبات مع أزرار الإجراءات -->
        <!-- ========================================= -->
        
        <!-- فلترة -->
        <div class="card-custom mb-4">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <select class="form-select" name="seat_id">
                        <option value="0">جميع المقاعد</option>
                        <?php foreach($seats_list as $seat): ?>
                        <option value="<?php echo $seat['id']; ?>" <?php echo $seat_filter == $seat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($seat['title_ar']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <select class="form-select" name="status">
                        <option value="all">جميع الحالات</option>
                        <?php foreach($status_labels as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $status_filter == $key ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn-primary-custom w-100">تصفية</button>
                </div>
            </form>
        </div>
        
        <!-- إحصائيات سريعة -->
        <?php
        $pending_count = count(array_filter($applications, function($a) { return $a['status'] == 'pending'; }));
        $accepted_count = count(array_filter($applications, function($a) { return $a['status'] == 'accepted_by_entity'; }));
        $interview_count = count(array_filter($applications, function($a) { return $a['status'] == 'interview_scheduled'; }));
        $rejected_count = count(array_filter($applications, function($a) { return $a['status'] == 'rejected'; }));
        ?>
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($applications); ?></div>
                    <div class="stat-label">الكل</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $pending_count; ?></div>
                    <div class="stat-label">جديد</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $interview_count; ?></div>
                    <div class="stat-label">مقابلة</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $accepted_count; ?></div>
                    <div class="stat-label">مقبول</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $rejected_count; ?></div>
                    <div class="stat-label">مرفوض</div>
                </div>
            </div>
        </div>
        
        <!-- قائمة الطلبات -->
        <div class="table-custom">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>المتدربة</th>
                        <th>الفرصة</th>
                        <th>المعدل</th>
                        <th>تاريخ التقديم</th>
                        <th>الحالة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($applications)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5>لا توجد طلبات</h5>
                            <p class="text-muted">لم يتم استلام أي طلبات بعد</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach($applications as $index => $app): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($app['full_name_ar']); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($app['student_code']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($app['title_ar']); ?></td>
                            <td><?php echo $app['gpa']; ?></td>
                            <td><?php echo date('Y-m-d', strtotime($app['created_at'])); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $status_colors[$app['status']]; ?> p-2">
                                    <?php echo $status_labels[$app['status']]; ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="?view=<?php echo $app['id']; ?>" class="btn btn-sm btn-outline-primary" title="عرض التفاصيل">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <?php if($app['status'] == 'pending'): ?>
                                    <a href="?view=<?php echo $app['id']; ?>" class="btn btn-sm btn-outline-success" title="قبول سريع"
                                       onclick="return quickAccept(<?php echo $app['id']; ?>)">
                                        <i class="fas fa-check"></i>
                                    </a>
                                    <a href="?view=<?php echo $app['id']; ?>" class="btn btn-sm btn-outline-danger" title="رفض سريع"
                                       onclick="return quickReject(<?php echo $app['id']; ?>)">
                                        <i class="fas fa-times"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- JavaScript للتحكم في ظهور الحقول والإجراءات السريعة -->
<script>
    function toggleFields() {
        const status = document.getElementById('statusSelect');
        if(!status) return;
        
        const rejectionField = document.getElementById('rejectionField');
        const interviewFields = document.getElementById('interviewFields');
        
        rejectionField.style.display = 'none';
        interviewFields.style.display = 'none';
        
        if(status.value === 'rejected') {
            rejectionField.style.display = 'block';
        } else if(status.value === 'interview_scheduled') {
            interviewFields.style.display = 'block';
        }
    }
    
    function quickAccept(id) {
        if(confirm('قبول هذا الطلب؟')) {
            // يمكن تنفيذ إجراء سريع هنا
            window.location.href = '?view=' + id;
        }
        return false;
    }
    
    function quickReject(id) {
        if(confirm('رفض هذا الطلب؟')) {
            window.location.href = '?view=' + id;
        }
        return false;
    }
    
    // تشغيل الدالة عند تحميل الصفحة
    document.addEventListener('DOMContentLoaded', function() {
        toggleFields();
    });
</script>

<?php
require_once '../../includes/entity_footer.php';
?>