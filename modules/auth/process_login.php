 <?php
// modules/auth/process_login.php - معالج تسجيل الدخول
session_start();
require_once '../../config/config.php';

if($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: login.php');
    exit();
}

// تنظيف المدخلات
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$user_type = $_POST['user_type'] ?? '';

// التحقق من المدخلات
if(empty($email) || empty($password) || empty($user_type)) {
    $_SESSION['error'] = 'جميع الحقول مطلوبة';
    header('Location: login.php?type=' . $user_type);
    exit();
}

// التحقق من صحة البريد
if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = 'البريد الإلكتروني غير صالح';
    header('Location: login.php?type=' . $user_type);
    exit();
}

try {
    // البحث عن المستخدم
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND user_type = ? AND is_active = 1");
    $stmt->execute([$email, $user_type]);
    $user = $stmt->fetch();
    
    if(!$user) {
        $_SESSION['error'] = 'بيانات الدخول غير صحيحة';
        header('Location: login.php?type=' . $user_type);
        exit();
    }
    
    // التحقق من كلمة المرور (في الإنتاج استخدم password_verify)
    if($password != $user['password']) {
        $_SESSION['error'] = 'بيانات الدخول غير صحيحة';
        header('Location: login.php?type=' . $user_type);
        exit();
    }
    
    // تسجيل الدخول
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_type'] = $user['user_type'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['logged_in'] = true;
    
    // تحديث آخر دخول
    $update = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $update->execute([$user['id']]);
    
    // تسجيل النشاط (اختياري)
    $log = $pdo->prepare("INSERT INTO activity_log (user_id, user_type, action, description) VALUES (?, ?, 'login', 'تسجيل دخول')");
    $log->execute([$user['id'], $user['user_type']]);
    
    // التوجيه حسب نوع المستخدم
    switch($user['user_type']) {
        case 'student':
            header('Location: ../student/dashboard.php');
            break;
        case 'training_entity':
            header('Location: ../training_entity/dashboard.php');
            break;
        case 'coordinator':
            header('Location: ../coordinator/dashboard.php');
            break;
        case 'supervisor':
            header('Location: ../supervisor/dashboard.php');
            break;
        default:
            header('Location: ../../index.php');
    }
    exit();
    
} catch(PDOException $e) {
    error_log("Login Error: " . $e->getMessage());
    $_SESSION['error'] = 'حدث خطأ في النظام، الرجاء المحاولة مرة أخرى';
    header('Location: login.php?type=' . $user_type);
    exit();
}
?>