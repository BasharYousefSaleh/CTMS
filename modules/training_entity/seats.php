 <?php
// modules/training_entity/seats.php - إدارة المقاعد التدريبية

session_start();
require_once '../../config/config.php';

// =============================================
// المعالجة الأولية (قبل أي HTML أو includes)
// =============================================

// التحقق من تسجيل الدخول
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
// معالجة الإجراءات (حذف - حفظ)
// =============================================

// حذف مقعد
if(isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    
    // التحقق من عدم وجود طلبات مرتبطة
    $check = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE seat_id = ?");
    $check->execute([$delete_id]);
    $apps_count = $check->fetchColumn();
    
    if($apps_count > 0) {
        $_SESSION['error'] = 'لا يمكن حذف المقعد لوجود طلبات مرتبطة به';
    } else {
        $stmt = $pdo->prepare("DELETE FROM training_seats WHERE id = ? AND entity_id = ?");
        $stmt->execute([$delete_id, $entity['id']]);
        $_SESSION['success'] = 'تم حذف المقعد بنجاح';
    }
    
    header('Location: seats.php');
    exit();
}

// حفظ مقعد (إضافة أو تحديث)
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_seat'])) {
    
    $seat_id = $_POST['seat_id'] ?? 0;
    $title_ar = trim($_POST['title_ar'] ?? '');
    $title_en = trim($_POST['title_en'] ?? '');
    $major_id = $_POST['major_id'] ?: null;
    $available_seats = (int)($_POST['available_seats'] ?? 1);
    $daily_hours = (float)($_POST['daily_hours'] ?? 8);
    $start_date = $_POST['start_date'] ?: null;
    $end_date = $_POST['end_date'] ?: null;
    $duration_weeks = (int)($_POST['duration_weeks'] ?? 8);
    $training_type = $_POST['training_type'] ?? 'تعاوني';
    $location = trim($_POST['location'] ?? '');
    $is_remote = isset($_POST['is_remote']) ? 1 : 0;
    $has_salary = isset($_POST['has_salary']) ? 1 : 0;
    $salary_amount = $has_salary ? (float)($_POST['salary_amount'] ?? 0) : null;
    $description = trim($_POST['description'] ?? '');
    $requirements = trim($_POST['requirements'] ?? '');
    
    // التحقق من البيانات
    if(empty($title_ar) || $available_seats < 1) {
        $_SESSION['error'] = 'العنوان وعدد المقاعد حقول مطلوبة';
        header('Location: seats.php' . ($seat_id ? '?edit=' . $seat_id : ''));
        exit();
    }
    
    try {
        if($seat_id > 0) {
            // تحديث مقعد موجود
            $stmt = $pdo->prepare("
                UPDATE training_seats SET
                    major_id = ?, 
                    title_ar = ?, 
                    title_en = ?, 
                    description = ?, 
                    requirements = ?,
                    available_seats = ?, 
                    start_date = ?, 
                    end_date = ?, 
                    duration_weeks = ?,
                    daily_hours = ?, 
                    training_type = ?, 
                    location = ?, 
                    is_remote = ?, 
                    has_salary = ?, 
                    salary_amount = ?
                WHERE id = ? AND entity_id = ?
            ");
            
            $stmt->execute([
                $major_id, 
                $title_ar, 
                $title_en, 
                $description, 
                $requirements,
                $available_seats, 
                $start_date, 
                $end_date, 
                $duration_weeks,
                $daily_hours, 
                $training_type, 
                $location, 
                $is_remote,
                $has_salary, 
                $salary_amount, 
                $seat_id, 
                $entity['id']
            ]);
            
            $_SESSION['success'] = 'تم تحديث المقعد بنجاح';
        } else {
            // إضافة مقعد جديد
            $stmt = $pdo->prepare("
                INSERT INTO training_seats 
                (entity_id, major_id, title_ar, title_en, description, requirements, 
                 available_seats, filled_seats, start_date, end_date, duration_weeks, 
                 daily_hours, training_type, location, is_remote, has_salary, salary_amount, 
                 status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available', NOW())
            ");
            
            $stmt->execute([
                $entity['id'], 
                $major_id, 
                $title_ar, 
                $title_en, 
                $description, 
                $requirements,
                $available_seats, 
                $start_date, 
                $end_date, 
                $duration_weeks,
                $daily_hours, 
                $training_type, 
                $location, 
                $is_remote,
                $has_salary, 
                $salary_amount
            ]);
            
            $_SESSION['success'] = 'تم إضافة المقعد بنجاح';
        }
        
        header('Location: seats.php');
        exit();
        
    } catch(Exception $e) {
        error_log("Seat Save Error: " . $e->getMessage());
        $_SESSION['error'] = 'حدث خطأ أثناء حفظ المقعد';
        header('Location: seats.php' . ($seat_id ? '?edit=' . $seat_id : ''));
        exit();
    }
}

// =============================================
// جلب البيانات للعرض
// =============================================

// جلب التخصصات
$majors = $pdo->query("SELECT * FROM majors WHERE is_active = 1 ORDER BY major_name_ar")->fetchAll();

// جلب جميع المقاعد
$stmt = $pdo->prepare("
    SELECT s.*, 
           (SELECT COUNT(*) FROM applications WHERE seat_id = s.id) as applications_count,
           (s.available_seats - COALESCE(s.filled_seats, 0)) as remaining,
           m.major_name_ar
    FROM training_seats s
    LEFT JOIN majors m ON s.major_id = m.id
    WHERE s.entity_id = ?
    ORDER BY s.created_at DESC
");
$stmt->execute([$entity['id']]);
$seats = $stmt->fetchAll();

// =============================================
// ✅ متغيرات التحكم في عرض النموذج
// =============================================
$show_form = false;
$edit_seat = null;
$form_title = 'إضافة مقعد جديد';
$form_icon = 'fa-plus-circle';

if(isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    
    if($edit_id == 0) {
        // إضافة جديدة
        $show_form = true;
        $form_title = 'إضافة مقعد جديد';
        $form_icon = 'fa-plus-circle';
    } else {
        // تعديل مقعد موجود
        $stmt = $pdo->prepare("SELECT * FROM training_seats WHERE id = ? AND entity_id = ?");
        $stmt->execute([$edit_id, $entity['id']]);
        $edit_seat = $stmt->fetch();
        
        if($edit_seat) {
            $show_form = true;
            $form_title = 'تعديل المقعد: ' . htmlspecialchars($edit_seat['title_ar']);
            $form_icon = 'fa-edit';
        } else {
            // إذا كان id غير صحيح، نوجه للصفحة الرئيسية
            header('Location: seats.php');
            exit();
        }
    }
}

// =============================================
// الآن نبدأ بعرض HTML
// =============================================

$page_title = 'المقاعد التدريبية';
require_once '../../includes/entity_header.php';

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h4 class="mb-1">المقاعد التدريبية</h4>
            <p class="text-muted mb-0">إضافة، تعديل، وعرض جميع المقاعد</p>
        </div>
        <?php if(!$show_form): ?>
        <a href="?edit=0" class="btn-primary-custom">
            <i class="fas fa-plus-circle ms-2"></i>
            إضافة مقعد جديد
        </a>
        <?php else: ?>
        <a href="seats.php" class="btn-outline-custom">
            <i class="fas fa-times ms-2"></i>
            إلغاء <?php echo $edit_seat ? 'التعديل' : 'الإضافة'; ?>
        </a>
        <?php endif; ?>
    </div>
    
    <?php if($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <!-- نموذج الإضافة/التعديل -->
    <?php if($show_form): ?>
    <div class="card-custom mb-4">
        <h5 class="mb-3">
            <i class="fas <?php echo $form_icon; ?> ms-2"></i>
            <?php echo $form_title; ?>
        </h5>
        
        <form method="POST" action="seats.php">
            <?php if($edit_seat): ?>
            <input type="hidden" name="seat_id" value="<?php echo $edit_seat['id']; ?>">
            <?php endif; ?>
            
            <div class="row">
                <!-- المعلومات الأساسية -->
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">عنوان التدريب <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="title_ar" value="<?php echo htmlspecialchars($edit_seat['title_ar'] ?? ''); ?>" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">العنوان بالإنجليزية</label>
                    <input type="text" class="form-control" name="title_en" value="<?php echo htmlspecialchars($edit_seat['title_en'] ?? ''); ?>">
                </div>
                
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">التخصص المطلوب</label>
                    <select class="form-select" name="major_id">
                        <option value="">جميع التخصصات</option>
                        <?php foreach($majors as $major): ?>
                        <option value="<?php echo $major['id']; ?>" <?php echo ($edit_seat['major_id'] ?? '') == $major['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($major['major_name_ar']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">عدد المقاعد <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" name="available_seats" min="1" value="<?php echo $edit_seat['available_seats'] ?? 1; ?>" required>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">ساعات العمل اليومية</label>
                    <input type="number" class="form-control" name="daily_hours" min="1" max="12" value="<?php echo $edit_seat['daily_hours'] ?? 8; ?>">
                </div>
                
                <!-- التواريخ -->
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">تاريخ بداية التدريب</label>
                    <input type="date" class="form-control" name="start_date" value="<?php echo $edit_seat['start_date'] ?? ''; ?>">
                </div>
                
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">تاريخ نهاية التدريب</label>
                    <input type="date" class="form-control" name="end_date" value="<?php echo $edit_seat['end_date'] ?? ''; ?>">
                </div>
                
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">مدة التدريب (أسابيع)</label>
                    <input type="number" class="form-control" name="duration_weeks" min="1" value="<?php echo $edit_seat['duration_weeks'] ?? 8; ?>">
                </div>
                
                <!-- نوع ومكان التدريب -->
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">نوع التدريب</label>
                    <select class="form-select" name="training_type">
                        <option value="تعاوني" <?php echo ($edit_seat['training_type'] ?? '') == 'تعاوني' ? 'selected' : ''; ?>>تدريب تعاوني</option>
                        <option value="صيفي" <?php echo ($edit_seat['training_type'] ?? '') == 'صيفي' ? 'selected' : ''; ?>>تدريب صيفي</option>
                        <option value="ميداني" <?php echo ($edit_seat['training_type'] ?? '') == 'ميداني' ? 'selected' : ''; ?>>تدريب ميداني</option>
                    </select>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">مكان التدريب</label>
                    <input type="text" class="form-control" name="location" value="<?php echo htmlspecialchars($edit_seat['location'] ?? ''); ?>" placeholder="مقر الجهة أو عنوان آخر">
                </div>
                
                <!-- خيارات إضافية -->
                <div class="col-12 mb-3">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="is_remote" id="is_remote" value="1" <?php echo ($edit_seat['is_remote'] ?? 0) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_remote">تدريب عن بعد</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="has_salary" id="has_salary" value="1" <?php echo ($edit_seat['has_salary'] ?? 0) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="has_salary">يوجد مكافأة مالية</label>
                    </div>
                </div>
                
                <!-- حقل المكافأة (يظهر فقط إذا تم اختيار مكافأة) -->
                <div class="col-md-6 mb-3" id="salary_field" style="display: <?php echo ($edit_seat['has_salary'] ?? 0) ? 'block' : 'none'; ?>;">
                    <label class="form-label fw-bold">قيمة المكافأة (ريال)</label>
                    <input type="number" class="form-control" name="salary_amount" min="0" step="100" value="<?php echo $edit_seat['salary_amount'] ?? ''; ?>">
                </div>
                
                <!-- الوصف والمتطلبات -->
                <div class="col-12 mb-3">
                    <label class="form-label fw-bold">وصف التدريب</label>
                    <textarea class="form-control" name="description" rows="4" placeholder="اكتب وصفاً تفصيلياً لمهام ومسؤوليات المتدربة..."><?php echo htmlspecialchars($edit_seat['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="col-12 mb-3">
                    <label class="form-label fw-bold">متطلبات التدريب</label>
                    <textarea class="form-control" name="requirements" rows="3" placeholder="المهارات المطلوبة، المؤهلات، الخبرات..."><?php echo htmlspecialchars($edit_seat['requirements'] ?? ''); ?></textarea>
                </div>
                
                <hr class="my-3">
                
                <div class="col-12">
                    <button type="submit" name="save_seat" class="btn-primary-custom">
                        <i class="fas fa-save ms-2"></i>
                        حفظ المقعد
                    </button>
                    <a href="seats.php" class="btn btn-secondary ms-2">إلغاء</a>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- قائمة المقاعد -->
    <div class="table-custom">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>عنوان التدريب</th>
                    <th>التخصص</th>
                    <th>المقاعد</th>
                    <th>التاريخ</th>
                    <th>الطلبات</th>
                    <th>الحالة</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($seats)): ?>
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <i class="fas fa-chair fa-3x text-muted mb-3"></i>
                        <h5>لا توجد مقاعد تدريبية</h5>
                        <p class="text-muted">لم تقم بإضافة أي مقاعد تدريبية بعد</p>
                        <a href="?edit=0" class="btn-primary-custom mt-2">
                            <i class="fas fa-plus-circle ms-2"></i>
                            إضافة أول مقعد
                        </a>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach($seats as $index => $seat): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($seat['title_ar']); ?></strong>
                            <?php if($seat['title_en']): ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars($seat['title_en']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($seat['major_name_ar'] ?? 'عام'); ?></td>
                        <td>
                            <span class="fw-bold"><?php echo $seat['remaining']; ?>/<?php echo $seat['available_seats']; ?></span>
                        </td>
                        <td>
                            <?php if($seat['start_date']): ?>
                            <?php echo date('Y-m-d', strtotime($seat['start_date'])); ?>
                            <?php else: ?>
                            غير محدد
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-info"><?php echo $seat['applications_count']; ?> طلب</span>
                        </td>
                        <td>
                            <?php
                            $status_color = 'success';
                            $status_text = 'متاح';
                            
                            if($seat['status'] == 'full' || $seat['remaining'] <= 0) {
                                $status_color = 'warning';
                                $status_text = 'مكتمل';
                            } elseif($seat['status'] == 'closed') {
                                $status_color = 'secondary';
                                $status_text = 'مغلق';
                            }
                            ?>
                            <span class="badge bg-<?php echo $status_color; ?>"><?php echo $status_text; ?></span>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <a href="?edit=<?php echo $seat['id']; ?>" class="btn btn-sm btn-outline-warning" title="تعديل">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?delete=<?php echo $seat['id']; ?>" class="btn btn-sm btn-outline-danger" title="حذف" 
                                   onclick="return confirm('هل أنت متأكد من حذف هذا المقعد؟')">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <a href="applications.php?seat_id=<?php echo $seat['id']; ?>" class="btn btn-sm btn-outline-info" title="عرض الطلبات">
                                    <i class="fas fa-file-alt"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- JavaScript -->
<script>
    // التحكم في إظهار/إخفاء حقل المكافأة
    document.addEventListener('DOMContentLoaded', function() {
        const hasSalaryCheckbox = document.getElementById('has_salary');
        const salaryField = document.getElementById('salary_field');
        
        if(hasSalaryCheckbox) {
            hasSalaryCheckbox.addEventListener('change', function() {
                if(salaryField) {
                    salaryField.style.display = this.checked ? 'block' : 'none';
                }
            });
        }
    });
</script>

<?php
require_once '../../includes/entity_footer.php';
?>