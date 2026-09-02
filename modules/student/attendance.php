<?php
// modules/student/attendance.php - صفحة الحضور (عرض فقط - بدون تسجيل)
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
// جلب بيانات التدريب النشط
// =============================================
$active_training = null;

$stmt = $pdo->prepare("
    SELECT a.*, ts.title_ar, ts.daily_hours, te.entity_name_ar,
           s.full_name_ar as supervisor_name
    FROM applications a
    JOIN training_seats ts ON a.seat_id = ts.id
    JOIN training_entities te ON ts.entity_id = te.id
    LEFT JOIN supervisors s ON a.supervisor_id = s.id
    WHERE a.student_id = ? AND a.status IN ('approved', 'accepted_by_entity')
    ORDER BY a.created_at DESC
    LIMIT 1
");
$stmt->execute([$student['id']]);
$active_training = $stmt->fetch();

// =============================================
// جلب سجل الحضور
// =============================================
$stmt = $pdo->prepare("
    SELECT a.*, 
           u.email as verified_by_email,
           sup.full_name_ar as verified_by_name
    FROM attendance a
    LEFT JOIN users u ON a.verified_by_entity = u.id
    LEFT JOIN supervisors sup ON a.verified_by_supervisor = sup.id
    WHERE a.student_id = ?
    ORDER BY a.attendance_date DESC
");
$stmt->execute([$student['id']]);
$attendance_history = $stmt->fetchAll();

// =============================================
// إحصائيات الحضور
// =============================================
$stats = [
    'total_days' => count($attendance_history),
    'present_days' => count(array_filter($attendance_history, fn($a) => $a['status'] == 'present')),
    'absent_days' => count(array_filter($attendance_history, fn($a) => $a['status'] == 'absent')),
    'late_days' => count(array_filter($attendance_history, fn($a) => $a['status'] == 'late')),
    'excused_days' => count(array_filter($attendance_history, fn($a) => $a['status'] == 'excused')),
    'total_hours' => array_sum(array_column($attendance_history, 'total_hours')),
];

$page_title = 'سجل الحضور';
require_once '../../includes/student_header.php';
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h4 class="mb-1">سجل الحضور</h4>
            <p class="text-muted mb-0">عرض سجل حضورك في التدريب (يتم التوثيق بواسطة المشرف)</p>
        </div>
    </div>
    
    <!-- ========================================= -->
    <!-- بطاقة التدريب النشط (للمعلومات فقط) -->
    <!-- ========================================= -->
    <?php if($active_training): ?>
    <div class="active-training-card mb-4">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h5 class="mb-2">
                    <i class="fas fa-briefcase ms-2"></i>
                    التدريب الحالي
                </h5>
                <p class="mb-1">
                    <strong><?php echo htmlspecialchars($active_training['title_ar']); ?></strong>
                </p>
                <p class="mb-0 text-white-50">
                    <i class="fas fa-building ms-2"></i> <?php echo htmlspecialchars($active_training['entity_name_ar']); ?>
                    <?php if($active_training['supervisor_name']): ?>
                    | <i class="fas fa-user-tie ms-2"></i> المشرف: <?php echo $active_training['supervisor_name']; ?>
                    <?php endif; ?>
                </p>
            </div>
            <div class="col-md-4 text-md-left mt-3 mt-md-0">
                <div class="bg-white bg-opacity-25 p-3 rounded text-center">
                    <i class="fas fa-info-circle fa-2x mb-2"></i>
                    <h6 class="mb-0">يتم توثيق الحضور بواسطة المشرف</h6>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle ms-2"></i>
        لا يوجد تدريب نشط حالياً.
        <a href="opportunities.php" class="alert-link">استعرض فرص التدريب</a>
    </div>
    <?php endif; ?>
    
    <!-- ========================================= -->
    <!-- إحصائيات سريعة -->
    <!-- ========================================= -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_days']; ?></div>
                <div class="stat-label">إجمالي الأيام</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card">
                <div class="stat-number text-success"><?php echo $stats['present_days']; ?></div>
                <div class="stat-label">أيام الحضور</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card">
                <div class="stat-number text-danger"><?php echo $stats['absent_days']; ?></div>
                <div class="stat-label">أيام الغياب</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card">
                <div class="stat-number text-warning"><?php echo $stats['late_days']; ?></div>
                <div class="stat-label">أيام التأخير</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card">
                <div class="stat-number text-info"><?php echo $stats['excused_days']; ?></div>
                <div class="stat-label">بإذن</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card">
                <div class="stat-number"><?php echo round($stats['total_hours'], 1); ?></div>
                <div class="stat-label">إجمالي الساعات</div>
            </div>
        </div>
    </div>
    
    <!-- ========================================= -->
    <!-- سجل الحضور -->
    <!-- ========================================= -->
    <h5 class="mb-3">
        <i class="fas fa-history ms-2" style="color: var(--primary);"></i>
        سجل الحضور السابق
    </h5>
    
    <div class="table-custom">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>التاريخ</th>
                    <th>اليوم</th>
                    <th>وقت الدخول</th>
                    <th>وقت الخروج</th>
                    <th>إجمالي الساعات</th>
                    <th>الحالة</th>
                    <th>تم التوثيق بواسطة</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($attendance_history)): ?>
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <h5>لا توجد سجلات حضور</h5>
                        <p class="text-muted">لم يتم تسجيل أي حضور بعد</p>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach($attendance_history as $index => $record): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><?php echo $record['attendance_date']; ?></td>
                        <td>
                            <?php 
                            $days = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
                            echo $days[date('w', strtotime($record['attendance_date']))];
                            ?>
                        </td>
                        <td><?php echo $record['check_in_time'] ?? '---'; ?></td>
                        <td><?php echo $record['check_out_time'] ?? '---'; ?></td>
                        <td>
                            <?php if($record['total_hours']): ?>
                            <strong><?php echo number_format($record['total_hours'], 1); ?></strong>
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
                            ?>
                            <span class="badge bg-<?php echo $status_class[$record['status']]; ?>">
                                <?php echo $status_text[$record['status']]; ?>
                            </span>
                        </td>
                        <td>
                            <?php if($record['verified_by_supervisor']): ?>
                                <span class="badge bg-success" title="موثق بواسطة المشرف">
                                    <i class="fas fa-check-circle"></i> مشرف
                                </span>
                            <?php elseif($record['verified_by_entity']): ?>
                                <span class="badge bg-info" title="موثق بواسطة الجهة">
                                    <i class="fas fa-building"></i> جهة
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary" title="في انتظار التوثيق">
                                    <i class="fas fa-clock"></i> في انتظار
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- ========================================= -->
    <!-- ملاحظة توضيحية -->
    <!-- ========================================= -->
    <div class="alert alert-info mt-3">
        <i class="fas fa-info-circle ms-2"></i>
        <strong>ملاحظة:</strong> يتم توثيق الحضور بواسطة المشرف الأكاديمي والجهة التدريبية. لا يمكن للمتدربة تسجيل الحضور بنفسها.
    </div>
</div>

<!-- CSS إضافي -->
<style>
.active-training-card {
    background: var(--gradient);
    color: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 10px 30px rgba(0,102,51,0.3);
}

.stat-card {
    background: white;
    border-radius: 10px;
    padding: 15px;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0,102,51,0.05);
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

.table-custom td {
    vertical-align: middle;
}
</style>

<?php require_once '../../includes/student_footer.php'; ?>