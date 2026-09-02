 <?php
// modules/auth/logout.php - تسجيل الخروج
session_start();

// تسجيل نشاط الخروج (اختياري)
if(isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    try {
        require_once '../../config/config.php';
        $log = $pdo->prepare("INSERT INTO activity_log (user_id, user_type, action, description) VALUES (?, ?, 'logout', 'تسجيل خروج')");
        $log->execute([$_SESSION['user_id'], $_SESSION['user_type']]);
    } catch(Exception $e) {
        // تجاهل الخطأ
    }
}

// إتلاف الجلسة
$_SESSION = array();
session_destroy();

// حذف الكوكيز إذا وجدت
if(isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// التوجيه للصفحة الرئيسية
header('Location: ../../index.php');
exit();
?>