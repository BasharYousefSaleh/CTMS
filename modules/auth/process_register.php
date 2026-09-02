<?php
// modules/auth/process_register.php - معالج إنشاء حساب جديد
session_start();
require_once '../../config/config.php';

if($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: register.php');
    exit();
}

// الحصول على نوع المستخدم
$user_type = $_POST['user_type'] ?? '';

// التحقق من صحة النوع
$allowed_types = ['student', 'training_entity', 'coordinator', 'supervisor'];
if(!in_array($user_type, $allowed_types)) {
    $_SESSION['error'] = 'نوع المستخدم غير صالح';
    header('Location: select_type.php');
    exit();
}

try {
    // بدء معاملة
    $pdo->beginTransaction();
    
    // البيانات المشتركة
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // التحقق من المدخلات المشتركة
    if(empty($email) || empty($password)) {
        throw new Exception('جميع الحقول المطلوبة يجب ملؤها');
    }
    
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('البريد الإلكتروني غير صالح');
    }
    
    if(strlen($password) < 6) {
        throw new Exception('كلمة المرور يجب أن تكون 6 أحرف على الأقل');
    }
    
    if($password != $confirm_password) {
        throw new Exception('كلمة المرور غير متطابقة');
    }
    
    // التحقق من الموافقة على الشروط
    if(!isset($_POST['agree'])) {
        throw new Exception('يجب الموافقة على شروط الاستخدام');
    }
    
    // التحقق من عدم تكرار البريد
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    if($check->fetch()) {
        throw new Exception('البريد الإلكتروني مستخدم بالفعل');
    }
    
    // إدراج المستخدم في جدول users
    $hashed_password = $password; // في الإنتاج استخدم: password_hash($password, PASSWORD_DEFAULT)
    
    $stmt = $pdo->prepare("INSERT INTO users (email, password, user_type, is_active, created_at) VALUES (?, ?, ?, 1, NOW())");
    $stmt->execute([$email, $hashed_password, $user_type]);
    $user_id = $pdo->lastInsertId();
    
    // إدراج البيانات حسب النوع
    switch($user_type) {
        case 'student':
            $student_id = $_POST['student_id'] ?? '';
            $full_name = $_POST['full_name'] ?? '';
            $national_id = $_POST['national_id'] ?? '';
            $major_id = $_POST['major_id'] ?? '';
            $gpa = $_POST['gpa'] ?? '';
            $phone = $_POST['phone'] ?? '';
            
            if(empty($student_id) || empty($full_name) || empty($national_id) || empty($major_id) || empty($gpa) || empty($phone)) {
                throw new Exception('جميع حقول المتدربة مطلوبة');
            }
            
            // التحقق من عدم تكرار الرقم الجامعي
            $check = $pdo->prepare("SELECT id FROM students WHERE student_id = ? OR national_id = ?");
            $check->execute([$student_id, $national_id]);
            if($check->fetch()) {
                throw new Exception('الرقم الجامعي أو رقم الهوية مستخدم بالفعل');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO students 
                (user_id, student_id, full_name_ar, national_id, major_id, gpa, phone, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$user_id, $student_id, $full_name, $national_id, $major_id, $gpa, $phone]);
            break;
            
        case 'training_entity':
            $entity_name = $_POST['entity_name'] ?? '';
            $commercial_reg = $_POST['commercial_reg'] ?? '';
            $contact_person = $_POST['contact_person'] ?? '';
            $contact_phone = $_POST['contact_phone'] ?? '';
            $city = $_POST['city'] ?? '';
            
            if(empty($entity_name) || empty($contact_person) || empty($contact_phone) || empty($city)) {
                throw new Exception('جميع حقول الجهة مطلوبة');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO training_entities 
                (user_id, entity_name_ar, commercial_registration, contact_person, contact_phone, city, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$user_id, $entity_name, $commercial_reg, $contact_person, $contact_phone, $city]);
            break;
            
        case 'coordinator':
            $employee_id = $_POST['employee_id'] ?? '';
            $full_name = $_POST['full_name'] ?? '';
            $national_id = $_POST['national_id'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $department = $_POST['department'] ?? '';
            
            if(empty($employee_id) || empty($full_name) || empty($national_id) || empty($phone) || empty($department)) {
                throw new Exception('جميع الحقول مطلوبة');
            }
            
            // التحقق من عدم التكرار
            $check = $pdo->prepare("SELECT id FROM coordinators WHERE employee_id = ? OR national_id = ?");
            $check->execute([$employee_id, $national_id]);
            if($check->fetch()) {
                throw new Exception('الرقم الوظيفي أو رقم الهوية مستخدم بالفعل');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO coordinators 
                (user_id, employee_id, full_name_ar, national_id, phone, department, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$user_id, $employee_id, $full_name, $national_id, $phone, $department]);
            break;
            
        case 'supervisor':
            $employee_id = $_POST['employee_id'] ?? '';
            $full_name = $_POST['full_name'] ?? '';
            $national_id = $_POST['national_id'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $department = $_POST['department'] ?? '';
            
            if(empty($employee_id) || empty($full_name) || empty($national_id) || empty($phone) || empty($department)) {
                throw new Exception('جميع الحقول مطلوبة');
            }
            
            // التحقق من عدم التكرار
            $check = $pdo->prepare("SELECT id FROM supervisors WHERE employee_id = ? OR national_id = ?");
            $check->execute([$employee_id, $national_id]);
            if($check->fetch()) {
                throw new Exception('الرقم الوظيفي أو رقم الهوية مستخدم بالفعل');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO supervisors 
                (user_id, employee_id, full_name_ar, national_id, phone, department, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$user_id, $employee_id, $full_name, $national_id, $phone, $department]);
            break;
    }
    
    // تأكيد المعاملة
    $pdo->commit();
    
    $_SESSION['success'] = 'تم إنشاء الحساب بنجاح، يمكنك تسجيل الدخول الآن';
    header('Location: login.php?type=' . $user_type);
    exit();
    
} catch(Exception $e) {
    // تراجع عن المعاملة في حالة الخطأ
    if($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $_SESSION['error'] = $e->getMessage();
    header('Location: register.php?type=' . $user_type);
    exit();
}
?>