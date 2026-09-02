<?php
// modules/coordinator/profile.php - الملف الشخصي للمنسقة
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
$stmt = $pdo->prepare("
    SELECT c.*, u.email 
    FROM coordinators c
    JOIN users u ON c.user_id = u.id
    WHERE c.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$coordinator = $stmt->fetch();

if(!$coordinator) {
    header('Location: ../auth/logout.php');
    exit();
}

// =============================================
// معالجة تحديث البيانات
// =============================================
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    
    $full_name_ar = $_POST['full_name_ar'] ?? '';
    $full_name_en = $_POST['full_name_en'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $department = $_POST['department'] ?? '';
    $position = $_POST['position'] ?? '';
    $office_location = $_POST['office_location'] ?? '';
    
    if(empty($full_name_ar) || empty($phone)) {
        $_SESSION['error'] = 'الحقول المطلوبة يجب ملؤها';
    } else {
        $update = $pdo->prepare("
            UPDATE coordinators SET
                full_name_ar = ?,
                full_name_en = ?,
                phone = ?,
                department = ?,
                position = ?,
                office_location = ?
            WHERE id = ?
        ");
        $update->execute([$full_name_ar, $full_name_en, $phone, $department, $position, $office_location, $coordinator['id']]);
        
        $_SESSION['success'] = 'تم تحديث الملف الشخصي';
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
    
    $user = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $user->execute([$_SESSION['user_id']]);
    $current_pass = $user->fetchColumn();
    
    if($current != $current_pass) {
        $_SESSION['error'] = 'كلمة المرور الحالية غير صحيحة';
    } elseif($new != $confirm) {
        $_SESSION['error'] = 'كلمة المرور غير متطابقة';
    } elseif(strlen($new) < 6) {
        $_SESSION['error'] = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
    } else {
        $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update->execute([$new, $_SESSION['user_id']]);
        $_SESSION['success'] = 'تم تغيير كلمة المرور';
    }
    
    header('Location: profile.php');
    exit();
}

$page_title = 'الملف الشخصي';
require_once '../../includes/coordinator_header.php';

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>

<div class="content">
    <div class="page-header">
        <h4 class="mb-1">الملف الشخصي</h4>
        <p class="text-muted">معلومات المنسقة</p>
    </div>
    
    <?php if($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
    <?php if($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
    
    <div class="row">
        <!-- ========================================= -->
        <!-- معلومات المنسقة -->
        <!-- ========================================= -->
        <div class="col-md-4">
            <div class="card-custom text-center">
                <div class="avatar-circle mx-auto mb-3" style="width: 120px; height: 120px; background: var(--gradient);">
                    <i class="fas fa-user-tie fa-4x text-white"></i>
                </div>
                <h5><?php echo $coordinator['full_name_ar']; ?></h5>
                <p class="text-muted"><?php echo $coordinator['position'] ?? 'منسقة التدريب'; ?></p>
                <p class="text-muted small"><?php echo $coordinator['employee_id']; ?></p>
            </div>
        </div>
        
        <div class="col-md-8">
            <!-- نموذج التعديل -->
            <div class="card-custom">
                <h5 class="mb-3">تعديل البيانات</h5>
                
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">الاسم بالعربية</label>
                            <input type="text" class="form-control" name="full_name_ar" value="<?php echo $coordinator['full_name_ar']; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">الاسم بالإنجليزية</label>
                            <input type="text" class="form-control" name="full_name_en" value="<?php echo $coordinator['full_name_en'] ?? ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">رقم الجوال</label>
                            <input type="text" class="form-control" name="phone" value="<?php echo $coordinator['phone'] ?? ''; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">البريد الإلكتروني</label>
                            <input type="email" class="form-control" value="<?php echo $_SESSION['user_email']; ?>" readonly disabled>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">القسم</label>
                            <input type="text" class="form-control" name="department" value="<?php echo $coordinator['department'] ?? ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">المسمى الوظيفي</label>
                            <input type="text" class="form-control" name="position" value="<?php echo $coordinator['position'] ?? 'منسقة التدريب'; ?>">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">موقع المكتب</label>
                            <input type="text" class="form-control" name="office_location" value="<?php echo $coordinator['office_location'] ?? ''; ?>">
                        </div>
                        <div class="col-12">
                            <button type="submit" name="update_profile" class="btn-primary-custom">
                                <i class="fas fa-save ms-2"></i> حفظ التغييرات
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- تغيير كلمة المرور -->
            <div class="card-custom mt-3">
                <h5 class="mb-3">تغيير كلمة المرور</h5>
                
                <form method="POST">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <input type="password" class="form-control" name="current_password" placeholder="كلمة المرور الحالية" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <input type="password" class="form-control" name="new_password" placeholder="كلمة المرور الجديدة" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <input type="password" class="form-control" name="confirm_password" placeholder="تأكيد كلمة المرور" required>
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

<?php require_once '../../includes/coordinator_footer.php'; ?>