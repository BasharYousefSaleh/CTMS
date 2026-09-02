 <?php
// modules/auth/register.php - صفحة إنشاء حساب جديد
// ✅ الألوان: تدرجات التركوازي والأزرق (نفس ألوان الكلية التقنية)

session_start();
require_once '../../config/config.php';

// إذا كان المستخدم مسجل دخوله بالفعل
if(isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

// الحصول على نوع المستخدم من الرابط
$type = isset($_GET['type']) ? $_GET['type'] : '';

// التحقق من صحة النوع
$allowed_types = ['student', 'training_entity', 'coordinator', 'supervisor'];
if(!in_array($type, $allowed_types)) {
    header('Location: select_type.php');
    exit();
}

$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
unset($_SESSION['error'], $_SESSION['success']);

// العناوين حسب النوع
$titles = [
    'student' => 'متدربة',
    'training_entity' => 'جهة تدريبية',
    'coordinator' => 'منسقة',
    'supervisor' => 'مشرف أكاديمي'
];

$icons = [
    'student' => 'fa-user-graduate',
    'training_entity' => 'fa-building',
    'coordinator' => 'fa-user-tie',
    'supervisor' => 'fa-chalkboard-teacher'
];

// جلب التخصصات للطالبات
$majors = [];
if($type == 'student') {
    $stmt = $pdo->query("SELECT * FROM majors WHERE is_active = 1 ORDER BY major_name_ar");
    $majors = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إنشاء حساب - <?php echo $titles[$type]; ?> | تعاوني</title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #2AA7A1;         /* التركوازي الرئيسي */
            --secondary: #3F7F95;       /* الأزرق المائل للتركواز */
            --accent: #9CCB8F;          /* الأخضر الفاتح */
            --gradient: linear-gradient(135deg, #2AA7A1, #3F7F95, #9CCB8F);
            --light: #f0f7f0;
            --dark: #3A3A3A;            /* الرمادي الداكن */
            --white: #ffffff;
            --gray: #6c757d;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #2AA7A1, #3F7F95);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }
        
        /* خلفية متحركة */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><path fill="%23ffffff" fill-opacity="0.03" d="M10 10 L 90 10 L 90 90 L 10 90 Z" stroke="%239CCB8F" stroke-width="1"/><circle cx="50" cy="50" r="20" stroke="%239CCB8F" stroke-width="1" fill="none"/></svg>');
            background-size: 50px 50px;
            animation: move 20s linear infinite;
        }
        
        @keyframes move {
            from { transform: translate(0, 0); }
            to { transform: translate(50px, 50px); }
        }
        
        .register-wrapper {
            width: 100%;
            max-width: 600px;
            position: relative;
            z-index: 2;
            animation: fadeInUp 0.6s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .register-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            box-shadow: 0 20px 40px rgba(42, 167, 161, 0.3);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .register-header {
            background: <?php 
                switch($type) {
                    case 'student': echo 'linear-gradient(135deg, #2AA7A1, #3F7F95)'; break;
                    case 'training_entity': echo 'linear-gradient(135deg, #3F7F95, #9CCB8F)'; break;
                    case 'coordinator': echo 'linear-gradient(135deg, #9CCB8F, #2AA7A1)'; break;
                    case 'supervisor': echo 'linear-gradient(135deg, #2AA7A1, #9CCB8F)'; break;
                }
            ?>;
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
        }
        
        .register-header:after {
            content: '';
            position: absolute;
            bottom: -20px;
            left: 0;
            right: 0;
            height: 40px;
            background: white;
            border-radius: 50% 50% 0 0;
            background: rgba(255,255,255,0.95);
        }
        
        .register-header .icon {
            width: 80px;
            height: 80px;
            line-height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            font-size: 40px;
            margin: 0 auto 20px;
            border: 3px solid rgba(255,255,255,0.5);
            backdrop-filter: blur(5px);
        }
        
        .register-header h2 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 5px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .register-header p {
            font-size: 16px;
            opacity: 0.9;
            margin: 0;
        }
        
        .register-body {
            padding: 40px 30px;
            background: rgba(255,255,255,0.95);
        }
        
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        .form-group i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
            font-size: 18px;
            z-index: 1;
        }
        
        .form-control, .form-select {
            padding-right: 45px;
            height: 50px;
            border-radius: 12px;
            border: 2px solid #e0e0e0;
            font-size: 16px;
            font-family: 'Cairo', sans-serif;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(42, 167, 161, 0.25);
        }
        
        .btn-register {
            background: <?php 
                switch($type) {
                    case 'student': echo 'linear-gradient(135deg, #2AA7A1, #3F7F95)'; break;
                    case 'training_entity': echo 'linear-gradient(135deg, #3F7F95, #9CCB8F)'; break;
                    case 'coordinator': echo 'linear-gradient(135deg, #9CCB8F, #2AA7A1)'; break;
                    case 'supervisor': echo 'linear-gradient(135deg, #2AA7A1, #9CCB8F)'; break;
                }
            ?>;
            color: white;
            height: 50px;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 700;
            width: 100%;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Cairo', sans-serif;
            position: relative;
            overflow: hidden;
        }
        
        .btn-register:before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255,255,255,0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .btn-register:hover:before {
            width: 300px;
            height: 300px;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(42, 167, 161, 0.4);
        }
        
        .alert {
            border-radius: 12px;
            margin-bottom: 25px;
            padding: 15px 20px;
            font-family: 'Cairo', sans-serif;
            border: none;
            animation: slideIn 0.5s ease;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .login-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #e0e0e0;
        }
        
        .login-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .login-link a:hover {
            color: var(--secondary);
            text-decoration: underline;
        }
        
        .back-link {
            text-align: center;
            margin-top: 25px;
        }
        
        .back-link a {
            color: white;
            text-decoration: none;
            font-size: 16px;
            opacity: 0.9;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .back-link a:hover {
            opacity: 1;
            transform: translateX(-5px);
        }
        
        .back-link a i {
            transition: transform 0.3s ease;
        }
        
        .back-link a:hover i {
            transform: translateX(-3px);
        }
        
        .form-text {
            font-size: 0.85rem;
            color: var(--gray);
            margin-top: 5px;
        }
        
        .password-strength {
            height: 5px;
            border-radius: 5px;
            margin-top: 5px;
            transition: all 0.3s ease;
        }
        
        .strength-weak { background: #dc3545; width: 33%; }
        .strength-medium { background: #ffc107; width: 66%; }
        .strength-strong { background: #28a745; width: 100%; }
    </style>
</head>
<body>
    <div class="register-wrapper">
        <div class="register-card">
            <div class="register-header">
                <div class="icon">
                    <i class="fas <?php echo $icons[$type]; ?>"></i>
                </div>
                <h2>إنشاء حساب جديد</h2>
                <p><?php echo $titles[$type]; ?></p>
            </div>
            
            <div class="register-body">
                <?php if($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle ms-2"></i>
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle ms-2"></i>
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <form action="process_register.php" method="POST" id="registerForm">
                    <input type="hidden" name="user_type" value="<?php echo $type; ?>">
                    
                    <!-- الحقول المشتركة للجميع -->
                    <div class="form-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" class="form-control" name="email" placeholder="البريد الإلكتروني" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" class="form-control" name="password" id="password" placeholder="كلمة المرور" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" class="form-control" name="confirm_password" id="confirm_password" placeholder="تأكيد كلمة المرور" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- مؤشر قوة كلمة المرور -->
                    <div class="password-strength mb-3" id="strengthIndicator"></div>
                    
                    <!-- حقول خاصة حسب النوع -->
                    <?php if($type == 'student'): ?>
                        <!-- حقول المتدربة -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <i class="fas fa-id-card"></i>
                                    <input type="text" class="form-control" name="student_id" placeholder="الرقم الجامعي" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <i class="fas fa-user"></i>
                                    <input type="text" class="form-control" name="full_name" placeholder="الاسم الكامل" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <i class="fas fa-id-card"></i>
                                    <input type="text" class="form-control" name="national_id" placeholder="رقم الهوية" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <i class="fas fa-phone"></i>
                                    <input type="text" class="form-control" name="phone" placeholder="رقم الجوال" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <i class="fas fa-graduation-cap"></i>
                                    <select class="form-select" name="major_id" required>
                                        <option value="">اختر التخصص</option>
                                        <?php foreach($majors as $major): ?>
                                            <option value="<?php echo $major['id']; ?>">
                                                <?php echo $major['major_name_ar']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <i class="fas fa-chart-line"></i>
                                    <input type="number" step="0.01" class="form-control" name="gpa" placeholder="المعدل التراكمي" required>
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif($type == 'training_entity'): ?>
                        <!-- حقول الجهة التدريبية -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <i class="fas fa-building"></i>
                                    <input type="text" class="form-control" name="entity_name" placeholder="اسم الجهة" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <i class="fas fa-id-card"></i>
                                    <input type="text" class="form-control" name="commercial_reg" placeholder="السجل التجاري">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <i class="fas fa-user-tie"></i>
                                    <input type="text" class="form-control" name="contact_person" placeholder="اسم المسؤول" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <i class="fas fa-phone"></i>
                                    <input type="text" class="form-control" name="contact_phone" placeholder="رقم التواصل" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <i class="fas fa-city"></i>
                                    <input type="text" class="form-control" name="city" placeholder="المدينة" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <i class="fas fa-globe"></i>
                                    <input type="url" class="form-control" name="website" placeholder="الموقع الإلكتروني">
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif($type == 'coordinator' || $type == 'supervisor'): ?>
                        <!-- حقول المنسقة والمشرف -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <i class="fas fa-id-card"></i>
                                    <input type="text" class="form-control" name="employee_id" placeholder="الرقم الوظيفي" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <i class="fas fa-user"></i>
                                    <input type="text" class="form-control" name="full_name" placeholder="الاسم الكامل" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <i class="fas fa-id-card"></i>
                                    <input type="text" class="form-control" name="national_id" placeholder="رقم الهوية" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <i class="fas fa-phone"></i>
                                    <input type="text" class="form-control" name="phone" placeholder="رقم الجوال" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <i class="fas fa-building"></i>
                            <input type="text" class="form-control" name="department" placeholder="القسم" required>
                        </div>
                        
                        <?php if($type == 'supervisor'): ?>
                        <div class="form-group">
                            <i class="fas fa-graduation-cap"></i>
                            <select class="form-select" name="academic_title">
                                <option value="">اللقب الأكاديمي (اختياري)</option>
                                <option value="أستاذ دكتور">أستاذ دكتور</option>
                                <option value="دكتور">دكتور</option>
                                <option value="أستاذ مشارك">أستاذ مشارك</option>
                                <option value="أستاذ مساعد">أستاذ مساعد</option>
                                <option value="محاضر">محاضر</option>
                            </select>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" name="agree" id="agree" required>
                        <label class="form-check-label" for="agree">
                            أوافق على <a href="#" target="_blank">شروط الاستخدام</a> و <a href="#" target="_blank">سياسة الخصوصية</a>
                        </label>
                    </div>
                    
                    <button type="submit" name="register" class="btn-register">
                        <i class="fas fa-user-plus ms-2"></i>
                        إنشاء حساب
                    </button>
                    
                    <div class="login-link">
                        <a href="login.php?type=<?php echo $type; ?>">
                            <i class="fas fa-sign-in-alt ms-1"></i>
                            لديك حساب بالفعل؟ سجل دخولك
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="back-link">
            <a href="select_type.php">
                <i class="fas fa-arrow-right"></i>
                تغيير نوع المستخدم
            </a>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // التحقق من تطابق كلمة المرور وقوتها
        const password = document.getElementById('password');
        const confirm = document.getElementById('confirm_password');
        const strengthIndicator = document.getElementById('strengthIndicator');
        
        password.addEventListener('input', checkPasswordStrength);
        confirm.addEventListener('input', checkPasswordMatch);
        
        function checkPasswordStrength() {
            const val = password.value;
            let strength = 0;
            
            if(val.length >= 6) strength++;
            if(val.match(/[a-z]+/)) strength++;
            if(val.match(/[A-Z]+/)) strength++;
            if(val.match(/[0-9]+/)) strength++;
            if(val.match(/[$@#&!]+/)) strength++;
            
            strengthIndicator.className = 'password-strength';
            if(strength <= 2) {
                strengthIndicator.classList.add('strength-weak');
            } else if(strength <= 4) {
                strengthIndicator.classList.add('strength-medium');
            } else {
                strengthIndicator.classList.add('strength-strong');
            }
        }
        
        function checkPasswordMatch() {
            if(password.value !== confirm.value) {
                confirm.setCustomValidity('كلمة المرور غير متطابقة');
                confirm.style.borderColor = '#dc3545';
            } else {
                confirm.setCustomValidity('');
                confirm.style.borderColor = '#28a745';
            }
        }
        
        // منع إرسال النموذج إذا كانت هناك أخطاء
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            if(password.value !== confirm.value) {
                e.preventDefault();
                alert('❌ كلمة المرور غير متطابقة');
            }
        });
        
        // إخفاء التنبيهات بعد 5 ثواني
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>