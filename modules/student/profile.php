 <?php
// modules/student/profile.php - الملف الشخصي للمتدربة (بالتصميم الجديد)
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
$stmt = $pdo->prepare("
    SELECT s.*, m.major_name_ar, u.email 
    FROM students s
    LEFT JOIN majors m ON s.major_id = m.id
    JOIN users u ON s.user_id = u.id
    WHERE s.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();

if(!$student) {
    header('Location: ../auth/logout.php');
    exit();
}

// =============================================
// معالجة تحديث البيانات
// =============================================
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    
    $full_name_ar = trim($_POST['full_name_ar'] ?? '');
    $full_name_en = trim($_POST['full_name_en'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $nationality = trim($_POST['nationality'] ?? 'سعودية');
    $birth_date = $_POST['birth_date'] ?? null;
    $major_id = $_POST['major_id'] ?? null;
    $gpa = $_POST['gpa'] ?? 0;
    $academic_level = $_POST['academic_level'] ?? null;
    $total_hours = $_POST['total_hours'] ?? 0;
    $expected_graduation = $_POST['expected_graduation'] ?? null;
    
    // التحقق من البيانات
    if(empty($full_name_ar) || empty($phone) || empty($gpa)) {
        $_SESSION['error'] = 'جميع الحقول المطلوبة يجب ملؤها';
    } else {
        try {
            // تحديث بيانات المتدربة
            $update = $pdo->prepare("
                UPDATE students SET
                    full_name_ar = ?,
                    full_name_en = ?,
                    phone = ?,
                    nationality = ?,
                    birth_date = ?,
                    major_id = ?,
                    gpa = ?,
                    academic_level = ?,
                    total_hours = ?,
                    expected_graduation = ?
                WHERE id = ?
            ");
            $update->execute([
                $full_name_ar,
                $full_name_en,
                $phone,
                $nationality,
                $birth_date,
                $major_id,
                $gpa,
                $academic_level,
                $total_hours,
                $expected_graduation,
                $student['id']
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
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        
        if(in_array($ext, $allowed)) {
            if($_FILES['profile_image']['size'] <= 2 * 1024 * 1024) {
                $filename = 'student_' . $student['id'] . '_' . time() . '.' . $ext;
                $upload_path = '../../uploads/students/' . $filename;
                
                if(!is_dir('../../uploads/students/')) {
                    mkdir('../../uploads/students/', 0777, true);
                }
                
                if(move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                    // التأكد من وجود عمود profile_image
                    try {
                        $check = $pdo->query("SHOW COLUMNS FROM students LIKE 'profile_image'");
                        if($check->rowCount() == 0) {
                            $pdo->exec("ALTER TABLE students ADD COLUMN profile_image VARCHAR(255) NULL AFTER phone");
                        }
                        
                        $update = $pdo->prepare("UPDATE students SET profile_image = ? WHERE id = ?");
                        $update->execute([$filename, $student['id']]);
                        $_SESSION['success'] = '✅ تم تحديث الصورة الشخصية';
                        
                    } catch(Exception $e) {
                        $_SESSION['error'] = 'خطأ في قاعدة البيانات: ' . $e->getMessage();
                    }
                } else {
                    $_SESSION['error'] = '❌ فشل رفع الملف';
                }
            } else {
                $_SESSION['error'] = '❌ حجم الصورة كبير جداً (الحد الأقصى 2 ميجابايت)';
            }
        } else {
            $_SESSION['error'] = '❌ نوع الملف غير مسموح. الأنواع المسموحة: JPG, JPEG, PNG, GIF';
        }
    } else {
        $_SESSION['error'] = '❌ الرجاء اختيار صورة';
    }
    
    header('Location: profile.php');
    exit();
}

// =============================================
// جلب التخصصات
// =============================================
$majors = $pdo->query("SELECT * FROM majors WHERE is_active = 1 ORDER BY major_name_ar")->fetchAll();

// =============================================
// جلب إحصائيات سريعة
// =============================================
$stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE student_id = ?");
$stmt->execute([$student['id']]);
$total_applications = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE student_id = ? AND status IN ('approved', 'accepted_by_entity')");
$stmt->execute([$student['id']]);
$accepted_applications = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE student_id = ? AND status = 'present'");
$stmt->execute([$student['id']]);
$total_attendance = $stmt->fetchColumn();

$page_title = 'الملف الشخصي';
require_once '../../includes/student_header.php';

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h4 class="mb-1">الملف الشخصي</h4>
            <p class="text-muted mb-0">عرض وتحديث معلوماتك الشخصية والأكاديمية</p>
        </div>
        <button class="btn-primary-custom" onclick="showEditForm()">
            <i class="fas fa-edit ms-2"></i>
            تعديل الملف الشخصي
        </button>
    </div>
    
    <?php if($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <div class="row">
        <!-- ========================================= -->
        <!-- العمود الأيمن - الصورة والمعلومات الأساسية -->
        <!-- ========================================= -->
        <div class="col-md-4">
            <!-- بطاقة الصورة الشخصية -->
            <div class="card-custom text-center">
                <div class="profile-image-container mb-3">
                    <?php if(!empty($student['profile_image'])): ?>
                    <img src="../../uploads/students/<?php echo $student['profile_image']; ?>" 
                         alt="صورة المتدربة" 
                         class="profile-image"
                         onerror="this.style.display='none'; this.parentNode.innerHTML='<div class=\'profile-image-placeholder\'><i class=\'fas fa-user-graduate fa-5x\'></i></div>';">
                    <?php else: ?>
                    <div class="profile-image-placeholder">
                        <i class="fas fa-user-graduate fa-5x"></i>
                    </div>
                    <?php endif; ?>
                </div>
                
                <h4><?php echo htmlspecialchars($student['full_name_ar']); ?></h4>
                <p class="text-muted"><?php echo $student['student_id']; ?></p>
                
                <!-- رفع صورة جديدة -->
                <form method="POST" enctype="multipart/form-data" class="mt-3">
                    <div class="mb-2">
                        <input type="file" class="form-control" name="profile_image" accept="image/*" id="profileImage" required>
                    </div>
                    <button type="submit" name="upload_image" class="btn-outline-custom w-100">
                        <i class="fas fa-upload ms-2"></i>
                        تحديث الصورة
                    </button>
                </form>
            </div>
            
            <!-- بطاقة الإحصائيات السريعة -->
            <div class="card-custom mt-3">
                <h5 class="mb-3">إحصائيات سريعة</h5>
                <div class="stats-list">
                    <div class="stat-item d-flex justify-content-between mb-2">
                        <span>إجمالي الطلبات:</span>
                        <strong><?php echo $total_applications; ?></strong>
                    </div>
                    <div class="stat-item d-flex justify-content-between mb-2">
                        <span>الطلبات المقبولة:</span>
                        <strong class="text-success"><?php echo $accepted_applications; ?></strong>
                    </div>
                    <div class="stat-item d-flex justify-content-between mb-2">
                        <span>أيام الحضور:</span>
                        <strong><?php echo $total_attendance; ?></strong>
                    </div>
                    <div class="stat-item d-flex justify-content-between">
                        <span>عضو منذ:</span>
                        <strong><?php echo date('Y-m-d', strtotime($student['created_at'])); ?></strong>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ========================================= -->
        <!-- العمود الأيسر - المعلومات التفصيلية -->
        <!-- ========================================= -->
        <div class="col-md-8">
            <!-- بطاقة المعلومات الشخصية (وضع العرض) -->
            <div class="card-custom" id="viewMode">
                <h5 class="mb-3">
                    <i class="fas fa-user-circle ms-2" style="color: var(--primary);"></i>
                    المعلومات الشخصية
                </h5>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">الاسم الكامل بالعربية</label>
                        <p class="fw-bold"><?php echo htmlspecialchars($student['full_name_ar']); ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">الاسم بالإنجليزية</label>
                        <p class="fw-bold"><?php echo htmlspecialchars($student['full_name_en'] ?? '---'); ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">رقم الهوية</label>
                        <p class="fw-bold"><?php echo htmlspecialchars($student['national_id']); ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">الجنسية</label>
                        <p class="fw-bold"><?php echo htmlspecialchars($student['nationality'] ?? 'سعودية'); ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">تاريخ الميلاد</label>
                        <p class="fw-bold"><?php echo $student['birth_date'] ? date('Y-m-d', strtotime($student['birth_date'])) : '---'; ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">رقم الجوال</label>
                        <p class="fw-bold"><?php echo htmlspecialchars($student['phone'] ?? '---'); ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">البريد الإلكتروني</label>
                        <p class="fw-bold"><?php echo htmlspecialchars($_SESSION['user_email']); ?></p>
                    </div>
                </div>
                
                <hr>
                
                <h5 class="mb-3 mt-3">
                    <i class="fas fa-graduation-cap ms-2" style="color: var(--primary);"></i>
                    المعلومات الأكاديمية
                </h5>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">الرقم الجامعي</label>
                        <p class="fw-bold"><?php echo $student['student_id']; ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">التخصص</label>
                        <p class="fw-bold"><?php echo htmlspecialchars($student['major_name_ar'] ?? 'غير محدد'); ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">المعدل التراكمي</label>
                        <p class="fw-bold"><?php echo number_format($student['gpa'], 2); ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">المستوى</label>
                        <p class="fw-bold"><?php echo $student['academic_level'] ?? '---'; ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">الساعات المكتملة</label>
                        <p class="fw-bold"><?php echo $student['total_hours'] ?? '0'; ?> ساعة</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">تاريخ التخرج المتوقع</label>
                        <p class="fw-bold"><?php echo $student['expected_graduation'] ?? '---'; ?></p>
                    </div>
                </div>
            </div>
            
            <!-- ========================================= -->
            <!-- نموذج التعديل (مخفي افتراضياً) -->
            <!-- ========================================= -->
            <div class="card-custom" id="editMode" style="display: none;">
                <h5 class="mb-3">
                    <i class="fas fa-edit ms-2" style="color: var(--primary);"></i>
                    تعديل الملف الشخصي
                </h5>
                
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">الاسم الكامل بالعربية <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="full_name_ar" 
                                   value="<?php echo htmlspecialchars($student['full_name_ar']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">الاسم بالإنجليزية</label>
                            <input type="text" class="form-control" name="full_name_en" 
                                   value="<?php echo htmlspecialchars($student['full_name_en'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">رقم الهوية</label>
                            <input type="text" class="form-control" value="<?php echo $student['national_id']; ?>" readonly disabled>
                            <small class="text-muted">لا يمكن تعديل رقم الهوية</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">الجنسية</label>
                            <input type="text" class="form-control" name="nationality" 
                                   value="<?php echo htmlspecialchars($student['nationality'] ?? 'سعودية'); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">تاريخ الميلاد</label>
                            <input type="date" class="form-control" name="birth_date" 
                                   value="<?php echo $student['birth_date'] ?? ''; ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">رقم الجوال <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="phone" 
                                   value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">البريد الإلكتروني</label>
                            <input type="email" class="form-control" value="<?php echo $_SESSION['user_email']; ?>" readonly disabled>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">التخصص</label>
                            <select class="form-select" name="major_id">
                                <option value="">اختر التخصص</option>
                                <?php foreach($majors as $major): ?>
                                <option value="<?php echo $major['id']; ?>" 
                                    <?php echo ($student['major_id'] == $major['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($major['major_name_ar']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">المعدل التراكمي <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" max="5" class="form-control" name="gpa" 
                                   value="<?php echo $student['gpa']; ?>" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">المستوى</label>
                            <input type="number" min="1" max="12" class="form-control" name="academic_level" 
                                   value="<?php echo $student['academic_level'] ?? ''; ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">الساعات المكتملة</label>
                            <input type="number" min="0" class="form-control" name="total_hours" 
                                   value="<?php echo $student['total_hours'] ?? '0'; ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">تاريخ التخرج المتوقع</label>
                            <input type="date" class="form-control" name="expected_graduation" 
                                   value="<?php echo $student['expected_graduation'] ?? ''; ?>">
                        </div>
                        
                        <div class="col-12">
                            <hr>
                            <button type="submit" name="update_profile" class="btn-primary-custom">
                                <i class="fas fa-save ms-2"></i>
                                حفظ التغييرات
                            </button>
                            <button type="button" class="btn btn-secondary ms-2" onclick="hideEditForm()">
                                <i class="fas fa-times ms-2"></i>
                                إلغاء
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- ========================================= -->
            <!-- تغيير كلمة المرور -->
            <!-- ========================================= -->
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
                                <i class="fas fa-key ms-2"></i>
                                تغيير كلمة المرور
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
    
    // التحقق من الصورة قبل الرفع
    document.getElementById('profileImage')?.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if(file) {
            // التحقق من نوع الملف
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if(!allowedTypes.includes(file.type)) {
                alert('نوع الملف غير مسموح. الرجاء اختيار صورة JPG, JPEG, PNG, GIF');
                this.value = '';
                return;
            }
            
            // التحقق من الحجم (2MB)
            if(file.size > 2 * 1024 * 1024) {
                alert('حجم الصورة كبير جداً. الحد الأقصى 2 ميجابايت');
                this.value = '';
                return;
            }
        }
    });
</script>

<?php
require_once '../../includes/student_footer.php';
?>