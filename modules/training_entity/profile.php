 <?php
// modules/training_entity/profile.php - الملف الشخصي للجهة (صفحة واحدة شاملة)
session_start();
require_once '../../config/config.php';

$page_title = 'الملف الشخصي';
require_once '../../includes/entity_header.php';

$edit_mode = isset($_GET['edit']) ? true : false;

// تحديث البيانات
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $entity_name_ar = $_POST['entity_name_ar'];
    $entity_name_en = $_POST['entity_name_en'];
    $entity_type = $_POST['entity_type'];
    $commercial_reg = $_POST['commercial_reg'];
    $contact_person = $_POST['contact_person'];
    $contact_position = $_POST['contact_position'];
    $contact_phone = $_POST['contact_phone'];
    $contact_email = $_POST['contact_email'];
    $website = $_POST['website'];
    $city = $_POST['city'];
    $address = $_POST['address'];
    
    $stmt = $pdo->prepare("
        UPDATE training_entities SET
            entity_name_ar = ?, entity_name_en = ?, entity_type = ?,
            commercial_registration = ?, contact_person = ?, contact_position = ?,
            contact_phone = ?, contact_email = ?, website = ?, city = ?, address = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $entity_name_ar, $entity_name_en, $entity_type, $commercial_reg,
        $contact_person, $contact_position, $contact_phone, $contact_email,
        $website, $city, $address, $entity['id']
    ]);
    
    $_SESSION['success'] = 'تم تحديث البيانات بنجاح';
    header('Location: profile.php');
    exit();
}

// تغيير كلمة المرور
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    
    // التحقق من كلمة المرور الحالية
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if($current != $user['password']) { // في الإنتاج استخدم password_verify
        $_SESSION['error'] = 'كلمة المرور الحالية غير صحيحة';
    } elseif($new != $confirm) {
        $_SESSION['error'] = 'كلمة المرور الجديدة غير متطابقة';
    } elseif(strlen($new) < 6) {
        $_SESSION['error'] = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
    } else {
        $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update->execute([$new, $_SESSION['user_id']]);
        $_SESSION['success'] = 'تم تغيير كلمة المرور بنجاح';
    }
    
    header('Location: profile.php');
    exit();
}

// رفع الشعار
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_logo'])) {
    if(isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        
        if(in_array($ext, $allowed) && $_FILES['logo']['size'] <= 2 * 1024 * 1024) {
            $filename = 'entity_' . $entity['id'] . '_' . time() . '.' . $ext;
            $upload_path = '../../uploads/entities/' . $filename;
            
            if(move_uploaded_file($_FILES['logo']['tmp_name'], $upload_path)) {
                $stmt = $pdo->prepare("UPDATE training_entities SET logo = ? WHERE id = ?");
                $stmt->execute([$filename, $entity['id']]);
                $_SESSION['success'] = 'تم رفع الشعار بنجاح';
            }
        } else {
            $_SESSION['error'] = 'نوع الملف غير مسموح أو الحجم كبير';
        }
    }
    header('Location: profile.php');
    exit();
}

// جلب الإحصائيات للتحديث
$stats = [
    'total_seats' => $pdo->prepare("SELECT COUNT(*) FROM training_seats WHERE entity_id = ?")->execute([$entity['id']]) ? 
                    $pdo->query("SELECT COUNT(*) FROM training_seats WHERE entity_id = {$entity['id']}")->fetchColumn() : 0,
    'active_seats' => $pdo->prepare("SELECT COUNT(*) FROM training_seats WHERE entity_id = ? AND status = 'available'")->execute([$entity['id']]) ? 
                      $pdo->query("SELECT COUNT(*) FROM training_seats WHERE entity_id = {$entity['id']} AND status = 'available'")->fetchColumn() : 0,
    'total_applications' => $pdo->prepare("SELECT COUNT(*) FROM applications a JOIN training_seats ts ON a.seat_id = ts.id WHERE ts.entity_id = ?")->execute([$entity['id']]) ? 
                            $pdo->query("SELECT COUNT(*) FROM applications a JOIN training_seats ts ON a.seat_id = ts.id WHERE ts.entity_id = {$entity['id']}")->fetchColumn() : 0,
];

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h4 class="mb-1">الملف الشخصي للجهة</h4>
            <p class="text-muted mb-0">عرض وتحديث معلومات جهة التدريب</p>
        </div>
        <?php if(!$edit_mode): ?>
        <a href="?edit=1" class="btn-primary-custom">
            <i class="fas fa-edit ms-2"></i>
            تعديل البيانات
        </a>
        <?php else: ?>
        <a href="profile.php" class="btn-outline-custom">
            <i class="fas fa-times ms-2"></i>
            إلغاء التعديل
        </a>
        <?php endif; ?>
    </div>
    
    <?php if($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Main Info -->
        <div class="col-md-8">
            <?php if($edit_mode): ?>
            <!-- Edit Form -->
            <div class="card-custom">
                <h5 class="mb-3">تعديل البيانات</h5>
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">اسم الجهة (عربي) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="entity_name_ar" value="<?php echo $entity['entity_name_ar']; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">اسم الجهة (إنجليزي)</label>
                            <input type="text" class="form-control" name="entity_name_en" value="<?php echo $entity['entity_name_en']; ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">نوع الجهة</label>
                            <select class="form-select" name="entity_type">
                                <option value="شركة" <?php echo $entity['entity_type'] == 'شركة' ? 'selected' : ''; ?>>شركة</option>
                                <option value="مؤسسة" <?php echo $entity['entity_type'] == 'مؤسسة' ? 'selected' : ''; ?>>مؤسسة</option>
                                <option value="مستشفى" <?php echo $entity['entity_type'] == 'مستشفى' ? 'selected' : ''; ?>>مستشفى</option>
                                <option value="بنك" <?php echo $entity['entity_type'] == 'بنك' ? 'selected' : ''; ?>>بنك</option>
                                <option value="جهة حكومية" <?php echo $entity['entity_type'] == 'جهة حكومية' ? 'selected' : ''; ?>>جهة حكومية</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">السجل التجاري</label>
                            <input type="text" class="form-control" name="commercial_reg" value="<?php echo $entity['commercial_registration']; ?>">
                        </div>
                        
                        
                        
                       
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">الموقع الإلكتروني</label>
                            <input type="url" class="form-control" name="website" value="<?php echo $entity['website']; ?>">
                        </div>
                        
                        <hr class="my-3">
                        
                        <h6 class="mb-3">معلومات المسؤول</h6>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">اسم المسؤول <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="contact_person" value="<?php echo $entity['contact_person']; ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">مسمى المسؤول</label>
                            <input type="text" class="form-control" name="contact_position" value="<?php echo $entity['contact_position']; ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">رقم التواصل <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="contact_phone" value="<?php echo $entity['contact_phone']; ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">البريد الإلكتروني</label>
                            <input type="email" class="form-control" name="contact_email" value="<?php echo $entity['contact_email']; ?>">
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" name="update_profile" class="btn-primary-custom">
                                <i class="fas fa-save ms-2"></i>
                                حفظ التغييرات
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <!-- View Mode -->
            <div class="card-custom">
                <h5 class="mb-3">معلومات الجهة</h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small">اسم الجهة بالعربية</label>
                        <p class="fw-bold"><?php echo $entity['entity_name_ar']; ?></p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small">اسم الجهة بالإنجليزية</label>
                        <p class="fw-bold"><?php echo $entity['entity_name_en'] ?: '---'; ?></p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small">نوع الجهة</label>
                        <p class="fw-bold"><?php echo $entity['entity_type'] ?: '---'; ?></p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small">السجل التجاري</label>
                        <p class="fw-bold"><?php echo $entity['commercial_registration'] ?: '---'; ?></p>
                    </div>
                   
                    
                    <?php if($entity['website']): ?>
                    <div class="col-12 mb-3">
                        <label class="text-muted small">الموقع الإلكتروني</label>
                        <p class="fw-bold">
                            <a href="<?php echo $entity['website']; ?>" target="_blank"><?php echo $entity['website']; ?></a>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card-custom mt-3">
                <h5 class="mb-3">معلومات المسؤول</h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small">اسم المسؤول</label>
                        <p class="fw-bold"><?php echo $entity['contact_person'] ?: '---'; ?></p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small">مسمى المسؤول</label>
                        <p class="fw-bold"><?php echo $entity['contact_position'] ?: '---'; ?></p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small">رقم التواصل</label>
                        <p class="fw-bold"><?php echo $entity['contact_phone'] ?: '---'; ?></p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small">البريد الإلكتروني</label>
                        <p class="fw-bold"><?php echo $entity['contact_email'] ?: $_SESSION['user_email']; ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Sidebar -->
        <div class="col-md-4">
            <!-- Logo -->
            <div class="card-custom text-center">
                <div class="mb-3">
                    <?php if($entity['logo']): ?>
                    <img src="../../uploads/entities/<?php echo $entity['logo']; ?>" alt="Logo" style="width: 120px; height: 120px; object-fit: cover; border-radius: 20px;">
                    <?php else: ?>
                    <div style="width: 120px; height: 120px; background: var(--gradient); border-radius: 20px; margin: 0 auto; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-building fa-4x text-white"></i>
                    </div>
                    <?php endif; ?>
                </div>
                
                <form method="POST" enctype="multipart/form-data">
                    <input type="file" class="form-control mb-2" name="logo" accept="image/*">
                    <button type="submit" name="upload_logo" class="btn-outline-custom w-100">
                        <i class="fas fa-upload ms-2"></i>
                        رفع شعار
                    </button>
                </form>
            </div>
            
            <!-- Status -->
            <div class="card-custom mt-3">
                <h5 class="mb-3">حالة الجهة</h5>
                <?php if($entity['is_approved']): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    الجهة معتمدة
                </div>
                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-clock"></i>
                    في انتظار اعتماد المنسقة
                </div>
                <?php endif; ?>
                
                <p class="small text-muted mb-0">
                    <i class="fas fa-calendar"></i>
                    تاريخ التسجيل: <?php echo date('Y-m-d', strtotime($entity['created_at'])); ?>
                </p>
            </div>
            
            <!-- Stats -->
            <div class="card-custom mt-3">
                <h5 class="mb-3">إحصائيات سريعة</h5>
                <div class="d-flex justify-content-between mb-2">
                    <span>إجمالي المقاعد:</span>
                    <strong><?php echo $stats['total_seats']; ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>المقاعد النشطة:</span>
                    <strong><?php echo $stats['active_seats']; ?></strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span>إجمالي الطلبات:</span>
                    <strong><?php echo $stats['total_applications']; ?></strong>
                </div>
            </div>
            
            <!-- Change Password -->
            <div class="card-custom mt-3">
                <h5 class="mb-3">تغيير كلمة المرور</h5>
                <form method="POST">
                    <div class="mb-2">
                        <input type="password" class="form-control" name="current_password" placeholder="كلمة المرور الحالية" required>
                    </div>
                    <div class="mb-2">
                        <input type="password" class="form-control" name="new_password" placeholder="كلمة المرور الجديدة" required>
                    </div>
                    <div class="mb-2">
                        <input type="password" class="form-control" name="confirm_password" placeholder="تأكيد كلمة المرور" required>
                    </div>
                    <button type="submit" name="change_password" class="btn-outline-custom w-100">
                        <i class="fas fa-key ms-2"></i>
                        تغيير كلمة المرور
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once '../../includes/entity_footer.php';
?>