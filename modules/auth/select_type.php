 <?php
// modules/auth/select_type.php - صفحة اختيار نوع المستخدم
// ✅ الألوان: تدرجات التركوازي والأزرق (نفس ألوان الكلية التقنية)

session_start();
require_once '../../config/config.php';

// إذا كان المستخدم مسجل دخوله بالفعل
if(isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اختيار نوع المستخدم | تعاوني</title>
    
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
        
        .select-wrapper {
            width: 100%;
            max-width: 1000px;
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
        
        .select-header {
            text-align: center;
            color: white;
            margin-bottom: 50px;
        }
        
        .select-header h1 {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .select-header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .types-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
        }
        
        .type-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(42, 167, 161, 0.3);
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
            color: inherit;
            cursor: pointer;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .type-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 30px 60px rgba(42, 167, 161, 0.4);
        }
        
        .card-header {
            padding: 40px 20px;
            text-align: center;
            color: white;
        }
        
        /* تدرجات البطاقات بالألوان الجديدة */
        .card-header.student { background: linear-gradient(135deg, #2AA7A1, #3F7F95); }
        .card-header.entity { background: linear-gradient(135deg, #3F7F95, #9CCB8F); }
        .card-header.coordinator { background: linear-gradient(135deg, #9CCB8F, #2AA7A1); }
        .card-header.supervisor { background: linear-gradient(135deg, #2AA7A1, #9CCB8F); }
        
        .card-icon {
            width: 80px;
            height: 80px;
            line-height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            margin: 0 auto 20px;
            font-size: 35px;
            border: 3px solid rgba(255,255,255,0.5);
        }
        
        .card-header h3 {
            font-size: 1.8rem;
            margin-bottom: 10px;
            font-weight: 800;
        }
        
        .card-body {
            padding: 9px;
            background: white;
        }
        
        .features-list {
            list-style: none;
            padding: 0;
            margin: 0 0 20px;
        }
        
        .features-list li {
            padding: 8px 0;
            border-bottom: 1px solid #edf2f7;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
        }
        
        .features-list li i {
            color: var(--primary);
            font-size: 0.9rem;
        }
        
        .select-btn {
            display: block;
            text-align: center;
            padding: 12px;
            background: var(--gradient);
            color: white;
            text-decoration: none;
            font-weight: 600;
            border-radius: 15px;
            transition: all 0.3s ease;
        }
        
        .select-btn:hover {
            background: var(--primary);
            color: white;
        }
        
        .back-link {
            text-align: center;
            margin-top: 30px;
        }
        
        .back-link a {
            color: white;
            text-decoration: none;
            font-size: 1.5rem;
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
        
        @media (max-width: 991px) {
            .types-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 576px) {
            .types-grid {
                grid-template-columns: 1fr;
            }
            
            .select-header h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="select-wrapper">
        <div class="select-header">
            <h1>مرحباً بك في تعاوني</h1>
            <p>اختر نوع المستخدم للمتابعة</p>
        </div>
        
        <div class="types-grid">
            <!-- متدربة -->
            <a href="login.php?type=student" class="type-card">
                <div class="card-header student">
                    <div class="card-icon"><i class="fas fa-user-graduate"></i></div>
                    <h3>متدربة</h3>
                </div>
                <div class="card-body">
                    <ul class="features-list">
                        <li><i class="fas fa-check-circle"></i> تقديم على فرص تدريب</li>
                        <li><i class="fas fa-check-circle"></i> رفع التقارير الأسبوعية</li>
                        <li><i class="fas fa-check-circle"></i> متابعة حالة الطلب</li>
                        <li><i class="fas fa-check-circle"></i> استلام الشهادة الإلكترونية</li>
                    </ul>
                    <span class="select-btn">تسجيل كمتدربة <i class="fas fa-arrow-left"></i></span>
                </div>
            </a>
            
            <!-- جهة تدريبية -->
            <a href="login.php?type=training_entity" class="type-card">
                <div class="card-header entity">
                    <div class="card-icon"><i class="fas fa-building"></i></div>
                    <h3>جهة تدريبية</h3>
                </div>
                <div class="card-body">
                    <ul class="features-list">
                        <li><i class="fas fa-check-circle"></i> نشر مقاعد تدريبية</li>
                        <li><i class="fas fa-check-circle"></i> إدارة المقابلات</li>
                        <li><i class="fas fa-check-circle"></i> تقييم المتدربات</li>
                        <li><i class="fas fa-check-circle"></i> إصدار تقارير أداء</li>
                    </ul>
                    <span class="select-btn">تسجيل كجهة <i class="fas fa-arrow-left"></i></span>
                </div>
            </a>
            
            <!-- منسقة -->
            <a href="login.php?type=coordinator" class="type-card">
                <div class="card-header coordinator">
                    <div class="card-icon"><i class="fas fa-user-tie"></i></div>
                    <h3>منسقة</h3>
                </div>
                <div class="card-body">
                    <ul class="features-list">
                        <li><i class="fas fa-check-circle"></i> اعتماد الجهات</li>
                        <li><i class="fas fa-check-circle"></i> توزيع المتدربات</li>
                        <li><i class="fas fa-check-circle"></i> إحصائيات دقيقة</li>
                        <li><i class="fas fa-check-circle"></i> تقارير شاملة</li>
                    </ul>
                    <span class="select-btn">تسجيل كمنسقة <i class="fas fa-arrow-left"></i></span>
                </div>
            </a>
            
            <!-- مشرف تدريبي -->
            <a href="login.php?type=supervisor" class="type-card">
                <div class="card-header supervisor">
                    <div class="card-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                    <h3>مشرف تدريبي</h3>
                </div>
                <div class="card-body">
                    <ul class="features-list">
                        <li><i class="fas fa-check-circle"></i> متابعة المتدربات</li>
                        <li><i class="fas fa-check-circle"></i> مراجعة التقارير</li>
                        <li><i class="fas fa-check-circle"></i> زيارات ميدانية</li>
                        <li><i class="fas fa-check-circle"></i> إصدار الشهادات</li>
                    </ul>
                    <span class="select-btn">تسجيل كمشرف <i class="fas fa-arrow-left"></i></span>
                </div>
            </a>
        </div>
        
        <div class="back-link">
            <a href="../../index.php">
                <i class="fas fa-arrow-right"></i>
                العودة للصفحة الرئيسية
            </a>
        </div>
    </div>
</body>
</html>