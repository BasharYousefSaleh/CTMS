 <?php
// modules/training_entity/attendance.php - إدارة الحضور مع إمكانية تغيير الحالة
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
// معالجة توثيق الحضور وتغيير الحالة
// =============================================

// توثيق الحضور (Verify)
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_attendance'])) {
    $attendance_id = $_POST['attendance_id'] ?? 0;
    $verified = isset($_POST['verified']) ? 1 : 0;
    
    if($attendance_id > 0) {
        $update = $pdo->prepare("
            UPDATE attendance SET 
                verified_by_entity = ?, 
                entity_verified_at = NOW() 
            WHERE id = ?
        ");
        $update->execute([$verified, $attendance_id]);
        
        $_SESSION['success'] = $verified ? 'تم توثيق الحضور' : 'تم إلغاء التوثيق';
    }
    
    header('Location: attendance.php');
    exit();
}

// تغيير حالة الحضور (Present/Absent/Late)
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $attendance_id = $_POST['attendance_id'] ?? 0;
    $new_status = $_POST['status'] ?? 'present';
    
    if($attendance_id > 0) {
        $update = $pdo->prepare("UPDATE attendance SET status = ? WHERE id = ?");
        $update->execute([$new_status, $attendance_id]);
        
        $_SESSION['success'] = 'تم تحديث حالة الحضور بنجاح';
    }
    
    header('Location: attendance.php');
    exit();
}

// إضافة سجل حضور جديد (لليوم)
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_attendance'])) {
    $student_id = $_POST['student_id'] ?? 0;
    $application_id = $_POST['application_id'] ?? 0;
    $attendance_date = $_POST['attendance_date'] ?? date('Y-m-d');
    $status = $_POST['status'] ?? 'present';
    $check_in = $_POST['check_in'] ?? date('H:i:s');
    $check_out = $_POST['check_out'] ?? null;
    
    // التحقق من عدم وجود تسجيل مكرر
    $check = $pdo->prepare("SELECT id FROM attendance WHERE student_id = ? AND attendance_date = ?");
    $check->execute([$student_id, $attendance_date]);
    
    if($check->fetch()) {
        $_SESSION['error'] = 'تم تسجيل حضور لهذه المتدربة في هذا التاريخ مسبقاً';
    } else {
        $insert = $pdo->prepare("
            INSERT INTO attendance (student_id, application_id, attendance_date, check_in_time, check_out_time, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $insert->execute([$student_id, $application_id, $attendance_date, $check_in, $check_out, $status]);
        
        $_SESSION['success'] = 'تم إضافة سجل حضور جديد';
    }
    
    header('Location: attendance.php');
    exit();
}

// =============================================
// جلب البيانات
// =============================================

$page_title = 'سجل الحضور';
require_once '../../includes/entity_header.php';

$trainee_filter = $_GET['trainee'] ?? 0;
$date_filter = $_GET['date'] ?? date('Y-m-d');
$status_filter = $_GET['status'] ?? 'all';
$show_add_form = isset($_GET['add']) ? true : false;

// جلب قائمة المتدربات النشطات مع طلباتهن النشطة
$trainees = $pdo->prepare("
    SELECT DISTINCT s.id, s.full_name_ar, s.student_id, a.id as application_id
    FROM students s
    JOIN applications a ON s.id = a.student_id
    JOIN training_seats ts ON a.seat_id = ts.id
    WHERE ts.entity_id = ? AND a.status IN ('approved', 'accepted_by_entity')
    ORDER BY s.full_name_ar
");
$trainees->execute([$entity['id']]);
$trainees_list = $trainees->fetchAll();

// جلب سجلات الحضور
$sql = "
    SELECT att.*, 
           s.full_name_ar, 
           s.student_id,
           ts.title_ar,
           a.id as application_id
    FROM attendance att
    JOIN students s ON att.student_id = s.id
    JOIN applications a ON att.application_id = a.id
    JOIN training_seats ts ON a.seat_id = ts.id
    WHERE ts.entity_id = ?
";
$params = [$entity['id']];

if($trainee_filter > 0) {
    $sql .= " AND att.student_id = ?";
    $params[] = $trainee_filter;
}

if(!empty($date_filter)) {
    $sql .= " AND att.attendance_date = ?";
    $params[] = $date_filter;
}

if($status_filter != 'all') {
    $sql .= " AND att.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY att.attendance_date DESC, att.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$attendance_records = $stmt->fetchAll();

// إحصائيات اليوم
$today_stats = [
    'present' => 0,
    'absent' => 0,
    'late' => 0,
    'excused' => 0,
    'total' => 0
];

foreach($attendance_records as $record) {
    if($record['attendance_date'] == date('Y-m-d')) {
        $today_stats['total']++;
        if($record['status'] == 'present') $today_stats['present']++;
        elseif($record['status'] == 'absent') $today_stats['absent']++;
        elseif($record['status'] == 'late') $today_stats['late']++;
        elseif($record['status'] == 'excused') $today_stats['excused']++;
    }
}

// إحصائيات عامة
$total_records = count($attendance_records);
$present_count = count(array_filter($attendance_records, function($a) { return $a['status'] == 'present'; }));
$absent_count = count(array_filter($attendance_records, function($a) { return $a['status'] == 'absent'; }));
$late_count = count(array_filter($attendance_records, function($a) { return $a['status'] == 'late'; }));
$excused_count = count(array_filter($attendance_records, function($a) { return $a['status'] == 'excused'; }));

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h4 class="mb-1">سجل الحضور</h4>
            <p class="text-muted mb-0">متابعة وتوثيق حضور المتدربات</p>
        </div>
        <div class="d-flex gap-2">
            <a href="?add=1" class="btn-primary-custom">
                <i class="fas fa-plus-circle ms-2"></i>
                تسجيل حضور جديد
            </a>
        </div>
    </div>
    
    <!-- إحصائيات سريعة -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="stat-card" style="background: #e8f5e9;">
                <div class="stat-number text-success"><?php echo $today_stats['present']; ?></div>
                <div class="stat-label">حاضر اليوم</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card" style="background: #ffebee;">
                <div class="stat-number text-danger"><?php echo $today_stats['absent']; ?></div>
                <div class="stat-label">غائب اليوم</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card" style="background: #fff3e0;">
                <div class="stat-number text-warning"><?php echo $today_stats['late']; ?></div>
                <div class="stat-label">متأخر اليوم</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card" style="background: #e3f2fd;">
                <div class="stat-number text-info"><?php echo $today_stats['excused']; ?></div>
                <div class="stat-label">بإذن اليوم</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_records; ?></div>
                <div class="stat-label">إجمالي السجلات</div>
            </div>
        </div>
    </div>
    
    <?php if($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <!-- نموذج إضافة حضور جديد -->
    <?php if($show_add_form): ?>
    <div class="card-custom mb-4">
        <h5 class="mb-3">تسجيل حضور جديد</h5>
        <form method="POST" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">المتدربة</label>
                <select class="form-select" name="student_id" required>
                    <option value="">اختر المتدربة</option>
                    <?php foreach($trainees_list as $t): ?>
                    <option value="<?php echo $t['id']; ?>" data-app="<?php echo $t['application_id']; ?>">
                        <?php echo htmlspecialchars($t['full_name_ar']); ?> (<?php echo $t['student_id']; ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">التاريخ</label>
                <input type="date" class="form-control" name="attendance_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">الحالة</label>
                <select class="form-select" name="status" required>
                    <option value="present">✅ حاضر</option>
                    <option value="absent">❌ غائب</option>
                    <option value="late">⏰ متأخر</option>
                    <option value="excused">📝 بإذن</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">وقت الدخول</label>
                <input type="time" class="form-control" name="check_in" value="<?php echo date('H:i'); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">وقت الخروج</label>
                <input type="time" class="form-control" name="check_out">
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <input type="hidden" name="application_id" id="application_id" value="">
                <button type="submit" name="add_attendance" class="btn-primary-custom w-100">حفظ</button>
            </div>
            <div class="col-md-12">
                <a href="attendance.php" class="btn btn-secondary">إلغاء</a>
            </div>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- فلترة -->
    <div class="card-custom mb-4">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">المتدربة</label>
                <select class="form-select" name="trainee">
                    <option value="0">جميع المتدربات</option>
                    <?php foreach($trainees_list as $t): ?>
                    <option value="<?php echo $t['id']; ?>" <?php echo $trainee_filter == $t['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($t['full_name_ar']); ?> (<?php echo $t['student_id']; ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">التاريخ</label>
                <input type="date" class="form-control" name="date" value="<?php echo $date_filter; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">الحالة</label>
                <select class="form-select" name="status">
                    <option value="all">جميع الحالات</option>
                    <option value="present" <?php echo $status_filter == 'present' ? 'selected' : ''; ?>>✅ حاضر</option>
                    <option value="absent" <?php echo $status_filter == 'absent' ? 'selected' : ''; ?>>❌ غائب</option>
                    <option value="late" <?php echo $status_filter == 'late' ? 'selected' : ''; ?>>⏰ متأخر</option>
                    <option value="excused" <?php echo $status_filter == 'excused' ? 'selected' : ''; ?>>📝 بإذن</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn-primary-custom w-100">تصفية</button>
            </div>
        </form>
    </div>
    
    <!-- جدول الحضور -->
    <div class="table-custom">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>المتدربة</th>
                    <th>الفرصة</th>
                    <th>التاريخ</th>
                    <th>الدخول</th>
                    <th>الخروج</th>
                    <th>الساعات</th>
                    <th>الحالة</th>
                    <th>توثيق</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($attendance_records)): ?>
                <tr>
                    <td colspan="10" class="text-center py-5">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <h5>لا توجد سجلات حضور</h5>
                        <p class="text-muted">لم يتم تسجيل أي حضور بعد</p>
                        <a href="?add=1" class="btn-primary-custom mt-2">
                            <i class="fas fa-plus-circle ms-2"></i>
                            تسجيل حضور جديد
                        </a>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach($attendance_records as $index => $record): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($record['full_name_ar']); ?></strong>
                            <br>
                            <small class="text-muted"><?php echo htmlspecialchars($record['student_id']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($record['title_ar']); ?></td>
                        <td><?php echo $record['attendance_date']; ?></td>
                        <td><?php echo $record['check_in_time'] ?? '---'; ?></td>
                        <td><?php echo $record['check_out_time'] ?? '---'; ?></td>
                        <td>
                            <?php if($record['total_hours']): ?>
                            <strong><?php echo number_format($record['total_hours'], 1); ?></strong> س
                            <?php else: ?>
                            ---
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $status_class = [
                                'present' => 'success',
                                'absent' => 'danger',
                                'late' => 'warning',
                                'excused' => 'info'
                            ];
                            $status_text = [
                                'present' => '✅ حاضر',
                                'absent' => '❌ غائب',
                                'late' => '⏰ متأخر',
                                'excused' => '📝 بإذن'
                            ];
                            $status_icon = [
                                'present' => 'fa-check-circle',
                                'absent' => 'fa-times-circle',
                                'late' => 'fa-clock',
                                'excused' => 'fa-file-alt'
                            ];
                            ?>
                            <span class="badge bg-<?php echo $status_class[$record['status']]; ?> p-2">
                                <i class="fas <?php echo $status_icon[$record['status']]; ?> ms-1"></i>
                                <?php echo $status_text[$record['status']]; ?>
                            </span>
                        </td>
                        <td>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="attendance_id" value="<?php echo $record['id']; ?>">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="verified" value="1" 
                                           <?php echo $record['verified_by_entity'] ? 'checked' : ''; ?>
                                           onchange="this.form.submit()">
                                    <input type="hidden" name="verify_attendance" value="1">
                                </div>
                                <?php if($record['verified_by_entity']): ?>
                                <small class="text-success">
                                    <i class="fas fa-check-circle"></i> موثق
                                </small>
                                <?php endif; ?>
                            </form>
                        </td>
                        <td>
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-outline-warning dropdown-toggle" data-bs-toggle="dropdown">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li>
                                        <form method="POST" class="dropdown-item">
                                            <input type="hidden" name="attendance_id" value="<?php echo $record['id']; ?>">
                                            <input type="hidden" name="status" value="present">
                                            <input type="hidden" name="update_status" value="1">
                                            <button type="submit" class="btn btn-link text-success p-0 w-100 text-end">
                                                <i class="fas fa-check-circle ms-2"></i> ✅ حاضر
                                            </button>
                                        </form>
                                    </li>
                                    <li>
                                        <form method="POST" class="dropdown-item">
                                            <input type="hidden" name="attendance_id" value="<?php echo $record['id']; ?>">
                                            <input type="hidden" name="status" value="absent">
                                            <input type="hidden" name="update_status" value="1">
                                            <button type="submit" class="btn btn-link text-danger p-0 w-100 text-end">
                                                <i class="fas fa-times-circle ms-2"></i> ❌ غائب
                                            </button>
                                        </form>
                                    </li>
                                    <li>
                                        <form method="POST" class="dropdown-item">
                                            <input type="hidden" name="attendance_id" value="<?php echo $record['id']; ?>">
                                            <input type="hidden" name="status" value="late">
                                            <input type="hidden" name="update_status" value="1">
                                            <button type="submit" class="btn btn-link text-warning p-0 w-100 text-end">
                                                <i class="fas fa-clock ms-2"></i> ⏰ متأخر
                                            </button>
                                        </form>
                                    </li>
                                    <li>
                                        <form method="POST" class="dropdown-item">
                                            <input type="hidden" name="attendance_id" value="<?php echo $record['id']; ?>">
                                            <input type="hidden" name="status" value="excused">
                                            <input type="hidden" name="update_status" value="1">
                                            <button type="submit" class="btn btn-link text-info p-0 w-100 text-end">
                                                <i class="fas fa-file-alt ms-2"></i> 📝 بإذن
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- إحصائيات إضافية -->
    <?php if(!empty($attendance_records)): ?>
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card-custom">
                <h5 class="mb-3">نسب الحضور</h5>
                
                <?php
                $present_percent = $total_records > 0 ? round(($present_count / $total_records) * 100) : 0;
                $absent_percent = $total_records > 0 ? round(($absent_count / $total_records) * 100) : 0;
                $late_percent = $total_records > 0 ? round(($late_count / $total_records) * 100) : 0;
                $excused_percent = $total_records > 0 ? round(($excused_count / $total_records) * 100) : 0;
                ?>
                
                                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span>✅ حاضر</span>
                        <span><?php echo $present_percent; ?>% (<?php echo $present_count; ?> يوم)</span>
                    </div>
                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar bg-success" style="width: <?php echo $present_percent; ?>%;">
                            <?php echo $present_count; ?>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span>❌ غائب</span>
                        <span><?php echo $absent_percent; ?>% (<?php echo $absent_count; ?> يوم)</span>
                    </div>
                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar bg-danger" style="width: <?php echo $absent_percent; ?>%;">
                            <?php echo $absent_count; ?>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span>⏰ متأخر</span>
                        <span><?php echo $late_percent; ?>% (<?php echo $late_count; ?> يوم)</span>
                    </div>
                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar bg-warning" style="width: <?php echo $late_percent; ?>%;">
                            <?php echo $late_count; ?>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span>📝 بإذن</span>
                        <span><?php echo $excused_percent; ?>% (<?php echo $excused_count; ?> يوم)</span>
                    </div>
                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar bg-info" style="width: <?php echo $excused_percent; ?>%;">
                            <?php echo $excused_count; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card-custom">
                <h5 class="mb-3">سجل اليوم (<?php echo date('Y-m-d'); ?>)</h5>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>المتدربة</th>
                            <th>الحالة</th>
                            <th>الوقت</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $today_records = array_filter($attendance_records, function($a) { 
                            return $a['attendance_date'] == date('Y-m-d'); 
                        });
                        
                        if(empty($today_records)): ?>
                        <tr>
                            <td colspan="3" class="text-center py-3">لا توجد سجلات لليوم</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach($today_records as $record): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record['full_name_ar']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $status_class[$record['status']]; ?>">
                                        <?php echo $status_text[$record['status']]; ?>
                                    </span>
                                </td>
                                <td><?php echo $record['check_in_time'] ?? '---'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- JavaScript -->
<script>
    // تحديث application_id عند اختيار المتدربة
    document.querySelector('select[name="student_id"]')?.addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        const appId = selected.getAttribute('data-app');
        document.getElementById('application_id').value = appId || '';
    });
</script>

<?php
require_once '../../includes/entity_footer.php';
?>