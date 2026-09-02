 <?php
 
session_start();
require_once '../../config/config.php';

// إذا كان المستخدم مسجل دخوله بالفعل
if(isset($_SESSION['user_id'])) {
    // توجيه حسب نوع المستخدم
    switch($_SESSION['user_type']) {
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
unset($_SESSION['error']);

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
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل دخول - <?php echo $titles[$type]; ?> | تعاوني</title>
    
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
        
        .login-wrapper {
            width: 100%;
            max-width: 450px;
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
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            box-shadow: 0 20px 40px rgba(42, 167, 161, 0.3);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .login-header {
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
        
        .login-header:after {
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
        
        .login-header .icon {
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
        
        .login-header h2 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 5px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .login-header p {
            font-size: 16px;
            opacity: 0.9;
            margin: 0;
        }
        
        .login-body {
            padding: 40px 30px;
            background: rgba(255,255,255,0.95);
        }
        
        .form-group {
            margin-bottom: 25px;
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
        
        .form-control {
            padding-right: 45px;
            height: 50px;
            border-radius: 12px;
            border: 2px solid #e0e0e0;
            font-size: 16px;
            font-family: 'Cairo', sans-serif;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(42, 167, 161, 0.25);
        }
        
        .btn-login {
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
        
        .btn-login:before {
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
        
        .btn-login:hover:before {
            width: 300px;
            height: 300px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(42, 167, 161, 0.4);
        }
        
        .alert {
            border-radius: 12px;
            margin-bottom: 25px;
            padding: 15px 20px;
            font-family: 'Cairo', sans-serif;
            border: none;
            background: #f8d7da;
            color: #721c24;
            animation: shake 0.5s ease;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        .register-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #e0e0e0;
        }
        
        .register-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .register-link a:hover {
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
        
        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .forgot-password {
            text-align: left;
            margin-top: 10px;
        }
        
        .forgot-password a {
            color: var(--gray);
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .forgot-password a:hover {
            color: var(--primary);
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-header">
                <div class="icon">
                    <i class="fas <?php echo $icons[$type]; ?>"></i>
                </div>
                <h2>تسجيل الدخول</h2>
                <p><?php echo $titles[$type]; ?></p>
            </div>
            
            <div class="login-body">
                <?php if($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle ms-2"></i>
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <form action="process_login.php" method="POST" id="loginForm">
                    <input type="hidden" name="user_type" value="<?php echo $type; ?>">
                    
                    <div class="form-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" class="form-control" name="email" placeholder="البريد الإلكتروني" required autofocus>
                    </div>
                    
                    <div class="form-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" class="form-control" name="password" placeholder="كلمة المرور" required>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="remember" id="remember">
                            <label class="form-check-label" for="remember">
                                تذكرني
                            </label>
                        </div>
                        
                         
                    </div>
                    
                    <button type="submit" class="btn-login">
                        <i class="fas fa-sign-in-alt ms-2"></i>
                        دخول
                    </button>
                    
                  
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
        // إخفاء التنبيهات بعد 5 ثواني
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
        
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = this.querySelector('button[type="submit"]');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin ms-2"></i> جاري تسجيل الدخول...';
            btn.disabled = true;
        });
    </script>
</body>
</html>