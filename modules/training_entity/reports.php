<?php
// modules/training_entity/reports.php - التقارير (صفحة واحدة شاملة)
session_start();
require_once '../../config/config.php';

$page_title = 'التقارير';
require_once '../../includes/entity_header.php';

$report_type = $_GET['type'] ?? 'dashboard';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// إحصائيات عامة
$stats = [
    'total_seats' => $pdo->prepare("SELECT COUNT(*) FROM training_seats WHERE entity_id = ?")->execute([$entity['id']]) ? 
                    $pdo->query("SELECT COUNT(*) FROM training_seats WHERE entity_id = {$entity['id']}")->fetchColumn() : 0,
    
    'active_seats' => $pdo->prepare("SELECT COUNT(*) FROM training_seats WHERE entity_id = ? AND status = 'available'")->execute([$entity['id']]) ? 
                      $pdo->query("SELECT COUNT(*) FROM training_seats WHERE entity_id = {$entity['id']} AND status = 'available'")->fetchColumn() : 0,
    
    'total_applications' => $pdo->prepare("SELECT COUNT(*) FROM applications a JOIN training_seats ts ON a.seat_id = ts.id WHERE ts.entity_id = ?")->execute([$entity['id']]) ? 
                            $pdo->query("SELECT COUNT(*) FROM applications a JOIN training_seats ts ON a.seat_id = ts.id WHERE ts.entity_id = {$entity['id']}")->fetchColumn() : 0,
    
    'pending_applications' => $pdo->prepare("SELECT COUNT(*) FROM applications a JOIN training_seats ts ON a.seat_id = ts.id WHERE ts.entity_id = ? AND a.status = 'pending'")->execute([$entity['id']]) ? 
                              $pdo->query("SELECT COUNT(*) FROM applications a JOIN training_seats ts ON a.seat_id = ts.id WHERE ts.entity_id = {$entity['id']} AND a.status = 'pending'")->fetchColumn() : 0,
    
    'accepted_applications' => $pdo->prepare("SELECT COUNT(*) FROM applications a JOIN training_seats ts ON a.seat_id = ts.id WHERE ts.entity_id = ? AND a.status IN ('approved', 'accepted_by_entity')")->execute([$entity['id']]) ? 
                               $pdo->query("SELECT COUNT(*) FROM applications a JOIN training_seats ts ON a.seat_id = ts.id WHERE ts.entity_id = {$entity['id']} AND a.status IN ('approved', 'accepted_by_entity')")->fetchColumn() : 0,
    
    'rejected_applications' => $pdo->prepare("SELECT COUNT(*) FROM applications a JOIN training_seats ts ON a.seat_id = ts.id WHERE ts.entity_id = ? AND a.status = 'rejected'")->execute([$entity['id']]) ? 
                               $pdo->query("SELECT COUNT(*) FROM applications a JOIN training_seats ts ON a.seat_id = ts.id WHERE ts.entity_id = {$entity['id']} AND a.status = 'rejected'")->fetchColumn() : 0,
    
    'active_trainees' => $pdo->prepare("SELECT COUNT(*) FROM applications a JOIN training_seats ts ON a.seat_id = ts.id WHERE ts.entity_id = ? AND a.status = 'approved'")->execute([$entity['id']]) ? 
                         $pdo->query("SELECT COUNT(*) FROM applications a JOIN training_seats ts ON a.seat_id = ts.id WHERE ts.entity_id = {$entity['id']} AND a.status = 'approved'")->fetchColumn() : 0,
    
    'completed_trainees' => $pdo->prepare("SELECT COUNT(*) FROM evaluations e JOIN applications a ON e.application_id = a.id JOIN training_seats ts ON a.seat_id = ts.id WHERE ts.entity_id = ? AND e.training_completed = 1")->execute([$entity['id']]) ? 
                            $pdo->query("SELECT COUNT(*) FROM evaluations e JOIN applications a ON e.application_id = a.id JOIN training_seats ts ON a.seat_id = ts.id WHERE ts.entity_id = {$entity['id']} AND e.training_completed = 1")->fetchColumn() : 0,
];

// تقرير الطلبات حسب الشهر
$monthly_apps = $pdo->prepare("
    SELECT DATE_FORMAT(a.created_at, '%Y-%m') as month, COUNT(*) as count
    FROM applications a
    JOIN training_seats ts ON a.seat_id = ts.id
    WHERE ts.entity_id = ? AND a.created_at BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(a.created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
");
$monthly_apps->execute([$entity['id'], $start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$monthly_data = $monthly_apps->fetchAll();

// تقرير المقاعد الأكثر طلباً
$popular_seats = $pdo->prepare("
    SELECT ts.title_ar, COUNT(a.id) as applications_count
    FROM training_seats ts
    LEFT JOIN applications a ON ts.id = a.seat_id
    WHERE ts.entity_id = ?
    GROUP BY ts.id
    ORDER BY applications_count DESC
    LIMIT 5
");
$popular_seats->execute([$entity['id']]);
$popular = $popular_seats->fetchAll();
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h4 class="mb-1">التقارير والإحصائيات</h4>
            <p class="text-muted mb-0">نظرة شاملة على أداء التدريب</p>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_seats']; ?></div>
                <div class="stat-label">إجمالي المقاعد</div>
                <small class="text-muted"><?php echo $stats['active_seats']; ?> نشط</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_applications']; ?></div>
                <div class="stat-label">إجمالي الطلبات</div>
                <small class="text-muted"><?php echo $stats['pending_applications']; ?> جديد</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['active_trainees']; ?></div>
                <div class="stat-label">متدربات حاليات</div>
                <small class="text-muted">قيد التدريب</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['completed_trainees']; ?></div>
                <div class="stat-label">متدربات سابقات</div>
                <small class="text-muted">أكملن التدريب</small>
            </div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="row">
        <div class="col-md-6">
            <div class="card-custom">
                <h5 class="mb-3">حالة الطلبات</h5>
                <canvas id="applicationsChart" style="height: 300px;"></canvas>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card-custom">
                <h5 class="mb-3">أكثر المقاعد طلباً</h5>
                <canvas id="popularChart" style="height: 300px;"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Date Filter -->
    <div class="card-custom mt-4">
        <h5 class="mb-3">تقرير الطلبات الشهرية</h5>
        
        <form method="GET" class="row mb-4">
            <input type="hidden" name="type" value="monthly">
            <div class="col-md-4">
                <label class="form-label">من تاريخ</label>
                <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">إلى تاريخ</label>
                <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn-primary-custom w-100">عرض</button>
            </div>
        </form>
        
        <table class="table">
            <thead>
                <tr>
                    <th>الشهر</th>
                    <th>عدد الطلبات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($monthly_data as $month): ?>
                <tr>
                    <td><?php echo $month['month']; ?></td>
                    <td><?php echo $month['count']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    
  
<!-- Charts JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Applications Chart
    const appCtx = document.getElementById('applicationsChart').getContext('2d');
    new Chart(appCtx, {
        type: 'doughnut',
        data: {
            labels: ['قيد المراجعة', 'مقبولة', 'مرفوضة'],
            datasets: [{
                data: [<?php echo $stats['pending_applications']; ?>, 
                       <?php echo $stats['accepted_applications']; ?>, 
                       <?php echo $stats['rejected_applications']; ?>],
                backgroundColor: ['#ffc107', '#28a745', '#dc3545']
            }]
        }
    });
    
    // Popular Seats Chart
    const popCtx = document.getElementById('popularChart').getContext('2d');
    new Chart(popCtx, {
        type: 'bar',
        data: {
            labels: [<?php foreach($popular as $p) echo "'" . addslashes($p['title_ar']) . "',"; ?>],
            datasets: [{
                label: 'عدد الطلبات',
                data: [<?php foreach($popular as $p) echo $p['applications_count'] . ","; ?>],
                backgroundColor: '#1e3c72'
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
</script>

<?php
require_once '../../includes/entity_footer.php';
?>