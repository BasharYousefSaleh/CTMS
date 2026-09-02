<?php
// - الملف الشخصي للمشرف
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
$stmt = $pdo->prepare("
    SELECT s.*, u.email 
    FROM supervisors s
    JOIN users u ON s.user_id = u.id
    WHERE s.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$supervisor = $stmt->fetch();

if(!$supervisor) {
    header('Location: ../auth/logout.php');
    exit();
}

// =============================================
// معالجة تحديث البيانات
// =============================================
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    
    $full_name_ar = trim($_POST['full_name_ar'] ?? '');
    $full_name_en = trim($_POST['full_name_en'] ?? '');
    $academic_title = trim($_POST['academic_title'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $office_location = trim($_POST['office_location'] ?? '');
    $max_students = (int)($_POST['max_students'] ?? 15);
    
    if(empty($full_name_ar) || empty($phone)) {
        $_SESSION['error'] = 'الاسم ورقم الجوال حقول مطلوبة';
    } else {
        try {
            $update = $pdo->prepare("
                UPDATE supervisors SET
                    full_name_ar = ?,
                    full_name_en = ?,
                    academic_title = ?,
                    department = ?,
                    specialization = ?,
                    phone = ?,
                    office_location = ?,
                    max_students = ?
                WHERE id = ?
            ");
            $update->execute([
                $full_name_ar,
                $full_name_en,
                $academic_title,
                $department,
                $specialization,
                $phone,
                $office_location,
                $max_students,
                $supervisor['id']
            ]);
            
            $_SESSION['success'] = '✅ تم تحديث الملف الشخصي بنجاح';
            
        } catch(Exception $e) {
            $_SESSION['error'] = 'حدث خطأ: ' . $e->getMessage();
        }
    }
    
    header('Location: profile.php');
    exit();
}

// =============================================
// تغيير كلمة المرور
// =============================================
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_pass = $stmt->fetchColumn();
    
    if($current != $user_pass) { // في الإنتاج استخدم password_verify
        $_SESSION['error'] = '❌ كلمة المرور الحالية غير صحيحة';
    } elseif($new != $confirm) {
        $_SESSION['error'] = '❌ كلمة المرور الجديدة غير متطابقة';
    } elseif(strlen($new) < 6) {
        $_SESSION['error'] = '❌ كلمة المرور يجب أن تكون 6 أحرف على الأقل';
    } else {
        $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update->execute([$new, $_SESSION['user_id']]);
        $_SESSION['success'] = '✅ تم تغيير كلمة المرور بنجاح';
    }
    
    header('Location: profile.php');
    exit();
}

// =============================================
// رفع صورة شخصية
// =============================================
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_image'])) {
    
    if(isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        
        if(in_array($ext, $allowed)) {
            if($_FILES['profile_image']['size'] <= 2 * 1024 * 1024) {
                $filename = 'supervisor_' . $supervisor['id'] . '_' . time() . '.' . $ext;
                $upload_path = '../../uploads/supervisors/' . $filename;
                
                if(!is_dir('../../uploads/supervisors/')) {
                    mkdir('../../uploads/supervisors/', 0777, true);
                }
                
                if(move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                    $update = $pdo->prepare("UPDATE supervisors SET profile_image = ? WHERE id = ?");
                    $update->execute([$filename, $supervisor['id']]);
                    $_SESSION['success'] = '✅ تم تحديث الصورة الشخصية';
                } else {
                    $_SESSION['error'] = '❌ فشل رفع الملف';
                }
            } else {
                $_SESSION['error'] = '❌ حجم الصورة كبير جداً (الحد الأقصى 2 ميجابايت)';
            }
        } else {
            $_SESSION['error'] = '❌ نوع الملف غير مسموح. الأنواع المسموحة: JPG, JPEG, PNG';
        }
    } else {
        $_SESSION['error'] = '❌ الرجاء اختيار صورة';
    }
    
    header('Location: profile.php');
    exit();
}

// =============================================
// إحصائيات سريعة
// =============================================
$stats = [];

// عدد المتدربات المشرف عليها
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM applications 
    WHERE supervisor_id = ? AND status IN ('approved', 'accepted_by_entity')
");
$stmt->execute([$supervisor['id']]);
$stats['students_count'] = $stmt->fetchColumn();

// عدد الزيارات
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM visits 
    WHERE supervisor_id = ?
");
$stmt->execute([$supervisor['id']]);
$stats['visits_count'] = $stmt->fetchColumn();

// عدد التقارير
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM weekly_reports r
    JOIN applications a ON r.application_id = a.id
    WHERE a.supervisor_id = ?
");
$stmt->execute([$supervisor['id']]);
$stats['reports_count'] = $stmt->fetchColumn();

$page_title = 'الملف الشخصي';
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
            <h4 class="mb-1">الملف الشخصي</h4>
            <p class="text-muted">عرض وتحديث معلوماتك الشخصية</p>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-primary p-3">
                <i class="fas fa-user-graduate ms-2"></i> <?php echo $stats['students_count']; ?> متدربة
            </span>
            <span class="badge bg-info p-3">
                <i class="fas fa-calendar-check ms-2"></i> <?php echo $stats['visits_count']; ?> زيارة
            </span>
        </div>
    </div>
    
    <?php if($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <div class="row">
        <!-- ========================================= -->
        <!-- العمود الأيمن - الصورة والمعلومات -->
        <!-- ========================================= -->
        <div class="col-md-4">
            <div class="card-custom text-center">
                <div class="profile-image-container mb-3">
                    <?php if(!empty($supervisor['profile_image'])): ?>
                        <img src="../../uploads/supervisors/<?php echo $supervisor['profile_image']; ?>" 
                             alt="صورة المشرف" 
                             class="profile-image">
                    <?php else: ?>
                        <div class="profile-image-placeholder">
                            <i class="fas fa-chalkboard-teacher fa-5x"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <h4><?php echo htmlspecialchars($supervisor['full_name_ar']); ?></h4>
                <p class="text-muted"><?php echo $supervisor['academic_title'] ?? 'مشرف أكاديمي'; ?></p>
                <p class="text-muted small"><?php echo $supervisor['employee_id']; ?></p>
                
                <!-- رفع صورة جديدة -->
                <form method="POST" enctype="multipart/form-data" class="mt-3">
                    <div class="mb-2">
                        <input type="file" class="form-control" name="profile_image" accept="image/*" id="profileImage">
                    </div>
                    <button type="submit" name="upload_image" class="btn-outline-custom w-100">
                        <i class="fas fa-upload ms-2"></i> تحديث الصورة
                    </button>
                </form>
            </div>
            
            <!-- بطاقة الإحصائيات السريعة -->
            <div class="card-custom mt-3">
                <h5 class="mb-3">إحصائيات سريعة</h5>
                <div class="stats-list">
                    <div class="stat-item d-flex justify-content-between mb-2">
                        <span><i class="fas fa-user-graduate ms-2 text-primary"></i> المتدربات:</span>
                        <strong><?php echo $stats['students_count']; ?></strong>
                    </div>
                    <div class="stat-item d-flex justify-content-between mb-2">
                        <span><i class="fas fa-calendar-check ms-2 text-info"></i> الزيارات:</span>
                        <strong><?php echo $stats['visits_count']; ?></strong>
                    </div>
                    <div class="stat-item d-flex justify-content-between mb-2">
                        <span><i class="fas fa-file-alt ms-2 text-success"></i> التقارير:</span>
                        <strong><?php echo $stats['reports_count']; ?></strong>
                    </div>
                    <div class="stat-item d-flex justify-content-between">
                        <span><i class="fas fa-calendar ms-2 text-muted"></i> عضو منذ:</span>
                        <strong><?php echo date('Y-m-d', strtotime($supervisor['created_at'] ?? 'now')); ?></strong>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ========================================= -->
        <!-- العمود الأيسر - المعلومات التفصيلية -->
        <!-- ========================================= -->
        <div class="col-md-8">
            <!-- بطاقة المعلومات الشخصية (عرض) -->
            <div class="card-custom" id="viewMode">
                <h5 class="mb-3">
                    <i class="fas fa-user-circle ms-2" style="color: var(--primary);"></i>
                    المعلومات الشخصية
                </h5>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">الاسم الكامل بالعربية</small>
                        <p class="fw-bold"><?php echo htmlspecialchars($supervisor['full_name_ar']); ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">الاسم بالإنجليزية</small>
                        <p class="fw-bold"><?php echo htmlspecialchars($supervisor['full_name_en'] ?? '---'); ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">اللقب الأكاديمي</small>
                        <p class="fw-bold"><?php echo htmlspecialchars($supervisor['academic_title'] ?? '---'); ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">الرقم الوظيفي</small>
                        <p class="fw-bold"><?php echo $supervisor['employee_id']; ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">القسم</small>
                        <p class="fw-bold"><?php echo htmlspecialchars($supervisor['department'] ?? '---'); ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">التخصص</small>
                        <p class="fw-bold"><?php echo htmlspecialchars($supervisor['specialization'] ?? '---'); ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">رقم الجوال</small>
                        <p class="fw-bold"><?php echo $supervisor['phone'] ?? '---'; ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">البريد الإلكتروني</small>
                        <p class="fw-bold"><?php echo $_SESSION['user_email']; ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">مكان المكتب</small>
                        <p class="fw-bold"><?php echo htmlspecialchars($supervisor['office_location'] ?? '---'); ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">الحد الأقصى للطالبات</small>
                        <p class="fw-bold"><?php echo $supervisor['max_students'] ?? 15; ?> متدربة</p>
                    </div>
                </div>
                
                <div class="mt-3">
                    <button class="btn-primary-custom" onclick="showEditForm()">
                        <i class="fas fa-edit ms-2"></i> تعديل الملف الشخصي
                    </button>
                </div>
            </div>
            
            <!-- بطاقة تعديل المعلومات -->
            <div class="card-custom" id="editMode" style="display: none;">
                <h5 class="mb-3">
                    <i class="fas fa-edit ms-2" style="color: var(--primary);"></i>
                    تعديل الملف الشخصي
                </h5>
                
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">الاسم الكامل بالعربية <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="full_name_ar" 
                                   value="<?php echo htmlspecialchars($supervisor['full_name_ar']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">الاسم بالإنجليزية</label>
                            <input type="text" class="form-control" name="full_name_en" 
                                   value="<?php echo htmlspecialchars($supervisor['full_name_en'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">اللقب الأكاديمي</label>
                            <select class="form-select" name="academic_title">
                                <option value="">-- اختر --</option>
                                <option value="أستاذ دكتور" <?php echo ($supervisor['academic_title'] ?? '') == 'أستاذ دكتور' ? 'selected' : ''; ?>>أستاذ دكتور</option>
                                <option value="دكتور" <?php echo ($supervisor['academic_title'] ?? '') == 'دكتور' ? 'selected' : ''; ?>>دكتور</option>
                                <option value="أستاذ مشارك" <?php echo ($supervisor['academic_title'] ?? '') == 'أستاذ مشارك' ? 'selected' : ''; ?>>أستاذ مشارك</option>
                                <option value="أستاذ مساعد" <?php echo ($supervisor['academic_title'] ?? '') == 'أستاذ مساعد' ? 'selected' : ''; ?>>أستاذ مساعد</option>
                                <option value="محاضر" <?php echo ($supervisor['academic_title'] ?? '') == 'محاضر' ? 'selected' : ''; ?>>محاضر</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">الرقم الوظيفي</label>
                            <input type="text" class="form-control" value="<?php echo $supervisor['employee_id']; ?>" readonly disabled>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">القسم</label>
                            <input type="text" class="form-control" name="department" 
                                   value="<?php echo htmlspecialchars($supervisor['department'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">التخصص</label>
                            <input type="text" class="form-control" name="specialization" 
                                   value="<?php echo htmlspecialchars($supervisor['specialization'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">رقم الجوال <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="phone" 
                                   value="<?php echo htmlspecialchars($supervisor['phone'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">مكان المكتب</label>
                            <input type="text" class="form-control" name="office_location" 
                                   value="<?php echo htmlspecialchars($supervisor['office_location'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">الحد الأقصى للطالبات</label>
                            <input type="number" class="form-control" name="max_students" min="1" max="30" 
                                   value="<?php echo $supervisor['max_students'] ?? 15; ?>">
                        </div>
                        
                        <div class="col-12">
                            <hr>
                            <button type="submit" name="update_profile" class="btn-primary-custom">
                                <i class="fas fa-save ms-2"></i> حفظ التغييرات
                            </button>
                            <button type="button" class="btn btn-secondary ms-2" onclick="hideEditForm()">
                                <i class="fas fa-times ms-2"></i> إلغاء
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- تغيير كلمة المرور -->
            <div class="card-custom mt-3">
                <h5 class="mb-3">
                    <i class="fas fa-key ms-2" style="color: var(--primary);"></i>
                    تغيير كلمة المرور
                </h5>
                
                <form method="POST">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <input type="password" class="form-control" name="current_password" 
                                   placeholder="كلمة المرور الحالية" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <input type="password" class="form-control" name="new_password" 
                                   placeholder="كلمة المرور الجديدة" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <input type="password" class="form-control" name="confirm_password" 
                                   placeholder="تأكيد كلمة المرور" required>
                        </div>
                        <div class="col-12">
                            <button type="submit" name="change_password" class="btn-outline-custom">
                                <i class="fas fa-key ms-2"></i> تغيير كلمة المرور
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- CSS إضافي -->
<style>
.profile-image-container {
    width: 150px;
    height: 150px;
    margin: 0 auto;
    border-radius: 50%;
    overflow: hidden;
    border: 4px solid white;
    box-shadow: 0 5px 15px rgba(0,102,51,0.2);
}

.profile-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-image-placeholder {
    width: 100%;
    height: 100%;
    background: var(--gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.stat-item {
    padding: 8px 0;
    border-bottom: 1px solid #edf2f7;
}

.stat-item:last-child {
    border-bottom: none;
}

.form-control:read-only,
.form-control:disabled {
    background-color: #f8f9fa;
    opacity: 0.8;
}

.btn-outline-custom {
    background: transparent;
    color: var(--primary);
    border: 2px solid var(--primary);
    padding: 8px 20px;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
}

.btn-outline-custom:hover {
    background: var(--primary);
    color: white;
}
</style>

<!-- JavaScript -->
<script>
function showEditForm() {
    document.getElementById('viewMode').style.display = 'none';
    document.getElementById('editMode').style.display = 'block';
}

function hideEditForm() {
    document.getElementById('viewMode').style.display = 'block';
    document.getElementById('editMode').style.display = 'none';
}

// معاينة الصورة قبل الرفع
document.getElementById('profileImage')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if(file) {
        if(file.size > 2 * 1024 * 1024) {
            alert('⚠️ حجم الصورة كبير جداً. الحد الأقصى 2 ميجابايت');
            this.value = '';
        }
    }
});
</script>

<?php require_once '../../includes/supervisor_footer.php'; ?>