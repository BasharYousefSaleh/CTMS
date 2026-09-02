 <?php
// modules/coordinator/entities.php - إدارة الجهات التدريبية
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
// تحديد الصفحة الحالية
// =============================================
$action = $_GET['action'] ?? 'list';
$entity_id = $_GET['id'] ?? 0;
$status = $_GET['status'] ?? 'all';

// =============================================
// معالجة اعتماد/رفض/إلغاء جهة
// =============================================
if(isset($_GET['approve']) && $_GET['approve'] > 0) {
    $update = $pdo->prepare("UPDATE training_entities SET is_approved = 1, approved_at = NOW() WHERE id = ?");
    $update->execute([$_GET['approve']]);
    $_SESSION['success'] = '✅ تم اعتماد الجهة بنجاح';
    header('Location: entities.php');
    exit();
}

if(isset($_GET['reject']) && $_GET['reject'] > 0) {
    $update = $pdo->prepare("UPDATE training_entities SET is_approved = 0 WHERE id = ?");
    $update->execute([$_GET['reject']]);
    $_SESSION['success'] = '❌ تم رفض الجهة';
    header('Location: entities.php');
    exit();
}

// ✅ إضافة إلغاء الاعتماد
if(isset($_GET['unapprove']) && $_GET['unapprove'] > 0) {
    $update = $pdo->prepare("UPDATE training_entities SET is_approved = 0, approved_at = NULL WHERE id = ?");
    $update->execute([$_GET['unapprove']]);
    $_SESSION['success'] = '🔄 تم إلغاء اعتماد الجهة';
    header('Location: entities.php');
    exit();
}

// =============================================
// جلب البيانات
// =============================================
$sql = "SELECT * FROM training_entities";
if($status == 'pending') $sql .= " WHERE is_approved = 0";
elseif($status == 'approved') $sql .= " WHERE is_approved = 1";
$sql .= " ORDER BY created_at DESC";
$entities = $pdo->query($sql)->fetchAll();

// إحصائيات
$stats = [
    'total' => count($entities),
    'pending' => count(array_filter($entities, fn($e) => $e['is_approved'] == 0)),
    'approved' => count(array_filter($entities, fn($e) => $e['is_approved'] == 1)),
];

// إذا كان عرض تفاصيل جهة
if($action == 'view' && $entity_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM training_entities WHERE id = ?");
    $stmt->execute([$entity_id]);
    $entity = $stmt->fetch();
    
    if(!$entity) {
        header('Location: entities.php');
        exit();
    }
    
    // جلب مقاعد الجهة
    $seats = $pdo->prepare("
        SELECT s.*, m.major_name_ar,
               (SELECT COUNT(*) FROM applications WHERE seat_id = s.id) as applications_count
        FROM training_seats s
        LEFT JOIN majors m ON s.major_id = m.id
        WHERE s.entity_id = ?
        ORDER BY s.created_at DESC
    ");
    $seats->execute([$entity_id]);
    $training_seats = $seats->fetchAll();
}

$page_title = 'الجهات التدريبية';
require_once '../../includes/coordinator_header.php';

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>

<div class="content">
    <?php if($action == 'view' && isset($entity)): ?>
        <!-- ========================================= -->
        <!-- عرض تفاصيل جهة -->
        <!-- ========================================= -->
        <div class="back-link mb-3">
            <a href="entities.php"><i class="fas fa-arrow-right ms-1"></i> العودة للقائمة</a>
        </div>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card-custom">
                    <div class="d-flex justify-content-between mb-3">
                        <h5><?php echo htmlspecialchars($entity['entity_name_ar']); ?></h5>
                        <?php if($entity['is_approved']): ?>
                            <span class="badge bg-success p-3">✅ معتمدة</span>
                        <?php else: ?>
                            <span class="badge bg-warning p-3">⏳ في انتظار الاعتماد</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <small class="text-muted">الاسم بالإنجليزية</small>
                            <p><?php echo $entity['entity_name_en'] ?? '---'; ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <small class="text-muted">نوع الجهة</small>
                            <p><?php echo $entity['entity_type'] ?? 'شركة'; ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <small class="text-muted">السجل التجاري</small>
                            <p><?php echo $entity['commercial_registration'] ?? '---'; ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <small class="text-muted">المدينة</small>
                            <p><?php echo $entity['city'] ?? '---'; ?></p>
                        </div>
                        <div class="col-12 mb-3">
                            <small class="text-muted">العنوان</small>
                            <p><?php echo $entity['address'] ?? '---'; ?></p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h6 class="mb-3">معلومات المسؤول</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <small class="text-muted">الاسم</small>
                            <p><?php echo $entity['contact_person'] ?? '---'; ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <small class="text-muted">رقم التواصل</small>
                            <p><?php echo $entity['contact_phone'] ?? '---'; ?></p>
                        </div>
                        <div class="col-12 mb-3">
                            <small class="text-muted">البريد الإلكتروني</small>
                            <p><?php echo $entity['contact_email'] ?? '---'; ?></p>
                        </div>
                    </div>
                    
                    <!-- المقاعد التدريبية -->
                    <h6 class="mt-4 mb-3">المقاعد التدريبية</h6>
                    <?php if(empty($training_seats)): ?>
                        <p class="text-muted">لا توجد مقاعد تدريبية</p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>العنوان</th>
                                    <th>التخصص</th>
                                    <th>المقاعد</th>
                                    <th>المتبقي</th>
                                    <th>الطلبات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($training_seats as $seat): ?>
                                <tr>
                                    <td><?php echo $seat['title_ar']; ?></td>
                                    <td><?php echo $seat['major_name_ar'] ?? 'عام'; ?></td>
                                    <td><?php echo $seat['available_seats']; ?></td>
                                    <td><?php echo $seat['available_seats'] - ($seat['filled_seats'] ?? 0); ?></td>
                                    <td><?php echo $seat['applications_count']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card-custom">
                    <h6 class="mb-3">الإجراءات</h6>
                    
                    <?php if(!$entity['is_approved']): ?>
                        <!-- جهة غير معتمدة -->
                        <a href="?approve=<?php echo $entity['id']; ?>" class="btn btn-success w-100 mb-2" onclick="return confirm('اعتماد هذه الجهة؟')">
                            <i class="fas fa-check-circle ms-2"></i> اعتماد الجهة
                        </a>
                        <a href="?reject=<?php echo $entity['id']; ?>" class="btn btn-danger w-100" onclick="return confirm('رفض هذه الجهة؟')">
                            <i class="fas fa-times-circle ms-2"></i> رفض الجهة
                        </a>
                        
                    <?php else: ?>
                        <!-- جهة معتمدة - يمكن إلغاء الاعتماد -->
                        <div class="alert alert-success mb-3">
                            <i class="fas fa-check-circle"></i> تم الاعتماد
                            <?php if($entity['approved_at']): ?>
                                <br><small><?php echo date('Y-m-d', strtotime($entity['approved_at'])); ?></small>
                            <?php endif; ?>
                        </div>
                        
                        <!-- ✅ زر إلغاء الاعتماد -->
                        <a href="?unapprove=<?php echo $entity['id']; ?>" class="btn btn-warning w-100" onclick="return confirm('إلغاء اعتماد هذه الجهة؟')">
                            <i class="fas fa-undo-alt ms-2"></i> إلغاء الاعتماد
                        </a>
                        
                        <hr>
                        <a href="?reject=<?php echo $entity['id']; ?>" class="btn btn-outline-danger w-100" onclick="return confirm('رفض هذه الجهة؟')">
                            <i class="fas fa-times ms-2"></i> رفض الجهة
                        </a>
                    <?php endif; ?>
                    
                    <hr>
                    <a href="mailto:<?php echo $entity['contact_email']; ?>" class="btn-outline-custom w-100 text-center">
                        <i class="fas fa-envelope ms-2"></i> إرسال بريد
                    </a>
                </div>
                
                <div class="card-custom mt-3">
                    <small class="text-muted">تاريخ التسجيل</small>
                    <p><?php echo date('Y-m-d', strtotime($entity['created_at'])); ?></p>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <!-- ========================================= -->
        <!-- قائمة الجهات -->
        <!-- ========================================= -->
        <div class="page-header">
            <h4 class="mb-1">الجهات التدريبية</h4>
            <p class="text-muted">إدارة واعتماد جهات التدريب</p>
        </div>
        
        <?php if($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
        <?php if($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
        
        <!-- تبويبات -->
        <div class="mb-4">
            <a href="?status=all" class="btn <?php echo $status == 'all' ? 'btn-primary-custom' : 'btn-outline-custom'; ?> me-2">
                الكل <span class="badge bg-light text-dark"><?php echo $stats['total']; ?></span>
            </a>
            <a href="?status=pending" class="btn <?php echo $status == 'pending' ? 'btn-warning' : 'btn-outline-warning'; ?> me-2">
                في الانتظار <span class="badge bg-light text-dark"><?php echo $stats['pending']; ?></span>
            </a>
            <a href="?status=approved" class="btn <?php echo $status == 'approved' ? 'btn-success' : 'btn-outline-success'; ?>">
                معتمدة <span class="badge bg-light text-dark"><?php echo $stats['approved']; ?></span>
            </a>
        </div>
        
        <!-- جدول الجهات -->
        <?php if(empty($entities)): ?>
            <div class="card-custom text-center py-5">
                <i class="fas fa-building fa-4x text-muted mb-3"></i>
                <h5>لا توجد جهات</h5>
            </div>
        <?php else: ?>
            <div class="table-custom">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>اسم الجهة</th>
                            <th>المدينة</th>
                            <th>المسؤول</th>
                            <th>التسجيل</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($entities as $i => $e): ?>
                        <tr>
                            <td><?php echo $i+1; ?></td>
                            <td>
                                <strong><?php echo $e['entity_name_ar']; ?></strong>
                                <br><small><?php echo $e['entity_type']; ?></small>
                            </td>
                            <td><?php echo $e['city'] ?? '---'; ?></td>
                            <td><?php echo $e['contact_person'] ?? '---'; ?></td>
                            <td><?php echo date('Y-m-d', strtotime($e['created_at'])); ?></td>
                            <td>
                                <?php if($e['is_approved']): ?>
                                    <span class="badge bg-success">معتمدة</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">في الانتظار</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?action=view&id=<?php echo $e['id']; ?>" class="btn btn-sm btn-outline-primary" title="عرض">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <?php if(!$e['is_approved']): ?>
                                    <!-- جهة غير معتمدة -->
                                    <a href="?approve=<?php echo $e['id']; ?>" class="btn btn-sm btn-outline-success" title="اعتماد" onclick="return confirm('اعتماد هذه الجهة؟')">
                                        <i class="fas fa-check"></i>
                                    </a>
                                <?php else: ?>
                                    <!-- جهة معتمدة - إلغاء الاعتماد -->
                                    <a href="?unapprove=<?php echo $e['id']; ?>" class="btn btn-sm btn-outline-warning" title="إلغاء الاعتماد" onclick="return confirm('إلغاء اعتماد هذه الجهة؟')">
                                        <i class="fas fa-undo-alt"></i>
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

<style>
.btn-success { background: var(--gradient); color: white; border: none; }
.btn-success:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,102,51,0.3); }
.btn-warning { background: #f59e0b; color: white; border: none; }
.btn-warning:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(245,158,11,0.3); }
.btn-outline-warning { border: 2px solid #ffc107; color: #856404; }
.btn-outline-warning:hover { background: #ffc107; color: white; }
.btn-outline-danger { border: 2px solid #dc3545; color: #dc3545; }
.btn-outline-danger:hover { background: #dc3545; color: white; }
</style>

<?php require_once '../../includes/coordinator_footer.php'; ?>