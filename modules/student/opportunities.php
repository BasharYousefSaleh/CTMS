 <?php
// modules/student/opportunities.php - فرص التدريب المتاحة للمتدربة
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
// معالجة البحث والفلترة
// =============================================
$search = $_GET['search'] ?? '';
$major = $_GET['major'] ?? '';
$city = $_GET['city'] ?? '';
$type = $_GET['type'] ?? '';

// =============================================
// جلب فرص التدريب
// =============================================
$sql = "
    SELECT 
        ts.*, 
        te.entity_name_ar, 
        te.city as entity_city, 
        te.logo,
        m.major_name_ar,
        (SELECT COUNT(*) FROM applications WHERE seat_id = ts.id) as applications_count,
        CASE 
            WHEN EXISTS (SELECT 1 FROM applications WHERE seat_id = ts.id AND student_id = ?) THEN 1
            ELSE 0
        END as has_applied
    FROM training_seats ts
    JOIN training_entities te ON ts.entity_id = te.id
    LEFT JOIN majors m ON ts.major_id = m.id
    WHERE te.is_approved = 1 AND ts.status = 'available'
";

$params = [$student['id']];

if(!empty($search)) {
    $sql .= " AND (ts.title_ar LIKE ? OR te.entity_name_ar LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if(!empty($major)) {
    $sql .= " AND ts.major_id = ?";
    $params[] = $major;
}

if(!empty($city)) {
    $sql .= " AND te.city LIKE ?";
    $params[] = "%$city%";
}

if($type == 'remote') {
    $sql .= " AND ts.is_remote = 1";
} elseif($type == 'onsite') {
    $sql .= " AND ts.is_remote = 0";
}

$sql .= " ORDER BY ts.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$opportunities = $stmt->fetchAll();

// =============================================
// جلب التخصصات للفلتر
// =============================================
$majors = $pdo->query("SELECT * FROM majors WHERE is_active = 1 ORDER BY major_name_ar")->fetchAll();

// =============================================
// جلب المدن المتاحة
// =============================================
$cities = $pdo->query("SELECT DISTINCT city FROM training_entities WHERE city IS NOT NULL AND city != '' ORDER BY city")->fetchAll();

// =============================================
// إحصائيات سريعة
// =============================================
$total_opportunities = count($opportunities);
$remote_count = count(array_filter($opportunities, function($o) { return $o['is_remote'] == 1; }));
$with_salary_count = count(array_filter($opportunities, function($o) { return $o['has_salary'] == 1; }));

$page_title = 'فرص التدريب';
require_once '../../includes/student_header.php';

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h4 class="mb-1">فرص التدريب المتاحة</h4>
            <p class="text-muted mb-0">استعرضي وتقدمي للفرص المناسبة لتخصصك</p>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-primary p-3">📊 <?php echo $total_opportunities; ?> فرصة</span>
            <span class="badge bg-info p-3">🌐 <?php echo $remote_count; ?> عن بعد</span>
            <span class="badge bg-success p-3">💰 <?php echo $with_salary_count; ?> بمكافأة</span>
        </div>
    </div>
    
    <?php if($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <!-- ========================================= -->
    <!-- بحث وتصفية متقدم -->
    <!-- ========================================= -->
    <div class="card-custom mb-4">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">بحث</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control" name="search" 
                           placeholder="عنوان التدريب أو اسم الجهة" 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">التخصص</label>
                <select class="form-select" name="major">
                    <option value="">الكل</option>
                    <?php foreach($majors as $m): ?>
                    <option value="<?php echo $m['id']; ?>" <?php echo $major == $m['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($m['major_name_ar']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">المدينة</label>
                <select class="form-select" name="city">
                    <option value="">الكل</option>
                    <?php foreach($cities as $c): ?>
                    <option value="<?php echo $c['city']; ?>" <?php echo $city == $c['city'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['city']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">نوع التدريب</label>
                <select class="form-select" name="type">
                    <option value="">الكل</option>
                    <option value="remote" <?php echo $type == 'remote' ? 'selected' : ''; ?>>عن بعد</option>
                    <option value="onsite" <?php echo $type == 'onsite' ? 'selected' : ''; ?>>حضوري</option>
                </select>
            </div>
            
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn-primary-custom w-100">
                    <i class="fas fa-filter ms-2"></i>
                    تصفية
                </button>
            </div>
        </form>
    </div>
    
    <!-- ========================================= -->
    <!-- قائمة فرص التدريب -->
    <!-- ========================================= -->
    <?php if(empty($opportunities)): ?>
    <div class="card-custom text-center py-5">
        <i class="fas fa-briefcase fa-4x text-muted mb-3"></i>
        <h5>لا توجد فرص تدريب متاحة</h5>
        <p class="text-muted mb-3">لا توجد فرص تدريب تطابق معايير البحث حالياً</p>
        <a href="opportunities.php" class="btn-primary-custom">
            <i class="fas fa-redo ms-2"></i>
            عرض الكل
        </a>
    </div>
    <?php else: ?>
        <?php foreach($opportunities as $opp): ?>
        <div class="opportunity-card" onclick="window.location.href='applications.php?action=create&seat_id=<?php echo $opp['id']; ?>'">
            <div class="row">
                <div class="col-md-8">
                    <div class="d-flex">
                        <!-- شعار الجهة -->
                        <div class="company-logo me-3">
                            <?php if($opp['logo']): ?>
                            <img src="../../uploads/entities/<?php echo $opp['logo']; ?>" alt="شعار">
                            <?php else: ?>
                            <i class="fas fa-building"></i>
                            <?php endif; ?>
                        </div>
                        
                        <!-- معلومات الفرصة -->
                        <div class="flex-grow-1">
                            <h5 class="opportunity-title mb-1">
                                <?php echo htmlspecialchars($opp['title_ar']); ?>
                                <?php if($opp['has_applied']): ?>
                                <span class="badge bg-success ms-2">تم التقديم</span>
                                <?php endif; ?>
                            </h5>
                            
                            <p class="company-name mb-2">
                                <i class="fas fa-building ms-1"></i> 
                                <?php echo htmlspecialchars($opp['entity_name_ar']); ?>
                                <?php if($opp['entity_city']): ?>
                                - <i class="fas fa-map-marker-alt ms-1"></i> <?php echo $opp['entity_city']; ?>
                                <?php endif; ?>
                            </p>
                            
                            <!-- الوسوم -->
                            <div class="opportunity-tags mb-2">
                                <span class="tag">
                                    <i class="fas fa-graduation-cap"></i> 
                                    <?php echo $opp['major_name_ar'] ?? 'جميع التخصصات'; ?>
                                </span>
                                
                                <span class="tag">
                                    <i class="fas fa-clock"></i> 
                                    <?php echo $opp['duration_weeks'] ?? 8; ?> أسابيع
                                </span>
                                
                                <?php if($opp['is_remote']): ?>
                                <span class="tag remote">
                                    <i class="fas fa-wifi"></i> عن بعد
                                </span>
                                <?php endif; ?>
                                
                                <?php if($opp['has_salary']): ?>
                                <span class="tag salary">
                                    <i class="fas fa-money-bill"></i> 
                                    <?php echo $opp['salary_amount'] ? number_format($opp['salary_amount']) . ' ريال' : 'مكافأة'; ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- وصف مختصر -->
                            <p class="opportunity-description">
                                <?php echo mb_substr(strip_tags($opp['description'] ?? ''), 0, 150); ?>...
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="opportunity-stats">
                        <!-- المقاعد المتبقية -->
                        <?php 
                        $remaining = $opp['available_seats'] - ($opp['filled_seats'] ?? 0);
                        $percentage = ($opp['filled_seats'] ?? 0) > 0 ? 
                            round((($opp['filled_seats'] ?? 0) / $opp['available_seats']) * 100) : 0;
                        ?>
                        <div class="seats-info mb-2">
                            <div class="d-flex justify-content-between mb-1">
                                <span>المقاعد:</span>
                                <strong><?php echo $remaining; ?>/<?php echo $opp['available_seats']; ?></strong>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar" style="width: <?php echo $percentage; ?>%;"></div>
                            </div>
                        </div>
                        
                        <!-- تاريخ البدء -->
                        <?php if($opp['start_date']): ?>
                        <div class="date-info mb-2">
                            <i class="fas fa-calendar-alt text-muted ms-1"></i>
                            <small>البداية: <?php echo date('Y-m-d', strtotime($opp['start_date'])); ?></small>
                        </div>
                        <?php endif; ?>
                        
                        <!-- عدد المتقدمين -->
                        <div class="applicants-info mb-3">
                            <i class="fas fa-users text-muted ms-1"></i>
                            <small><?php echo $opp['applications_count']; ?> متدربة تقدمن</small>
                        </div>
                        
                        <!-- زر التقديم -->
                        <?php if($opp['has_applied']): ?>
                        <a href="applications.php?action=view&seat_id=<?php echo $opp['id']; ?>" class="btn-applied w-100" onclick="event.stopPropagation()">
                            <i class="fas fa-check-circle ms-2"></i>
                            تم التقديم
                        </a>
                        <?php elseif($remaining > 0): ?>
                        <a href="applications.php?action=create&seat_id=<?php echo $opp['id']; ?>" class="btn-apply w-100" onclick="event.stopPropagation()">
                            <i class="fas fa-paper-plane ms-2"></i>
                            قدم الآن
                        </a>
                        <?php else: ?>
                        <button class="btn-apply w-100" disabled onclick="event.stopPropagation()">
                            <i class="fas fa-times-circle ms-2"></i>
                            المقاعد ممتلئة
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- CSS إضافي للصفحة -->
<style>
.opportunity-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 5px 15px rgba(0,102,51,0.05);
    transition: all 0.3s ease;
    cursor: pointer;
    border-right: 4px solid transparent;
}

.opportunity-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,102,51,0.1);
    border-right-color: var(--accent);
}

.company-logo {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #006633, #228B22, #90EE90);
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 30px;
    overflow: hidden;
}

.company-logo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.opportunity-title {
    color: var(--primary);
    font-size: 1.3rem;
    margin-bottom: 5px;
}

.company-name {
    color: #6c757d;
    font-size: 0.95rem;
}

.opportunity-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.tag {
    background: #f0f7f0;
    padding: 4px 12px;
    border-radius: 50px;
    font-size: 0.85rem;
    color: var(--primary);
}

.tag.remote {
    background: #e3f2fd;
    color: #1976d2;
}

.tag.salary {
    background: #e8f5e9;
    color: #388e3c;
}

.opportunity-description {
    color: #6c757d;
    font-size: 0.9rem;
    line-height: 1.6;
}

.opportunity-stats {
    background: #f8faff;
    padding: 15px;
    border-radius: 12px;
    height: 100%;
}

.btn-apply {
    background: linear-gradient(135deg, #006633, #228B22, #90EE90);
    color: white;
    border: none;
    padding: 10px;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
    text-align: center;
    display: inline-block;
    text-decoration: none;
}

.btn-apply:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,102,51,0.4);
    color: white;
}

.btn-apply:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-applied {
    background: #28a745;
    color: white;
    border: none;
    padding: 10px;
    border-radius: 8px;
    font-weight: 600;
    text-align: center;
    display: inline-block;
    text-decoration: none;
    cursor: default;
}

.progress {
    background-color: #e9ecef;
}

.progress-bar {
    background: linear-gradient(135deg, #006633, #228B22);
}

@media (max-width: 768px) {
    .opportunity-card {
        padding: 15px;
    }
    
    .company-logo {
        width: 60px;
        height: 60px;
        font-size: 24px;
    }
    
    .opportunity-title {
        font-size: 1.1rem;
    }
}
</style>

<?php
require_once '../../includes/student_footer.php';
?>