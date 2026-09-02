<?php
// config/config.php - ملف الإعدادات الرئيسي

// بدء الجلسة إذا لم تكن قد بدأت
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// إعدادات قاعدة البيانات
define('DB_HOST', 'localhost');
define('DB_NAME', 'ctms_db1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// إعدادات الموقع
define('SITE_NAME', ' تعاوني');
define('SITE_URL', 'http://localhost/ctms/');

// المنطقة الزمنية
date_default_timezone_set('Asia/Riyadh');

// وضع التطوير
define('DEVELOPMENT_MODE', true);

// الاتصال بقاعدة البيانات
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
    
    // تخزين الاتصال في متغير عام
    $GLOBALS['pdo'] = $pdo;
    
} catch(PDOException $e) {
    if(DEVELOPMENT_MODE) {
        die("خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage());
    } else {
        die("عذراً، حدث خطأ في النظام. الرجاء المحاولة لاحقاً.");
    }
}

// دالة مساعدة للاستعلامات
function db_query($sql, $params = []) {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

// دالة لجلب سجل واحد
function db_fetch_one($sql, $params = []) {
    $stmt = db_query($sql, $params);
    return $stmt->fetch();
}

// دالة لجلب جميع السجلات
function db_fetch_all($sql, $params = []) {
    $stmt = db_query($sql, $params);
    return $stmt->fetchAll();
}
?>