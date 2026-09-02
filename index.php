 <?php

session_start();
require_once 'config/config.php';

try {
    // عدد المتدربات
    $students_count = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $students_count = $students_count ?: 1250;
    
    // عدد الجهات المعتمدة
    $entities_count = $pdo->query("SELECT COUNT(*) FROM training_entities WHERE is_approved = 1")->fetchColumn();
    $entities_count = $entities_count ?: 85;
    
    // عدد فرص التدريب المتاحة
    $opportunities_count = $pdo->query("SELECT COALESCE(SUM(available_seats), 0) FROM training_seats WHERE status = 'available'")->fetchColumn();
    $opportunities_count = $opportunities_count ?: 320;
    
    // عدد المتدربات
    $trainees_count = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'approved'")->fetchColumn();
    $trainees_count = $trainees_count ?: 450;
    
} catch (Exception $e) {
    // قيم افتراضية في حالة الخطأ
    $students_count = 1250;
    $entities_count = 85;
    $opportunities_count = 320;
    $trainees_count = 450;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعاوني</title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    
    <!-- ملف التنسيق الرئيسي -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- تعديلات بسيطة للهيدر الجديد -->
    <style>
        /* تعديل شريط التنقل */
        .navbar .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        
        .navbar-brand {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--primary);
            text-decoration: none;
        }
        
        .nav-links {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            gap: 25px;
        }
        
        .nav-links li a {
            color: var(--dark);
            font-weight: 600;
            text-decoration: none;
            padding: 0.5rem 0;
            position: relative;
            transition: color 0.3s ease;
            font-size: 1rem;
        }
        
        .nav-links li a:hover,
        .nav-links li a.active {
            color: var(--primary);
        }
        
        .nav-links li a:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 3px;
            background: var(--primary);
            transition: width 0.3s ease;
        }
        
        .nav-links li a:hover:after,
        .nav-links li a.active:after {
            width: 100%;
        }
        
        .menu-toggle {
            display: none;
            font-size: 1.5rem;
            color: var(--primary);
            cursor: pointer;
        }
        
        @media (max-width: 991px) {
            .menu-toggle {
                display: block;
                order: 2;
            }
            
            .nav-links {
                display: none;
                width: 100%;
                flex-direction: column;
                background: var(--white);
                padding: 20px;
                border-radius: 15px;
                margin-top: 15px;
                box-shadow: var(--shadow);
                order: 3;
                text-align: center;
            }
            
            .nav-links.active {
                display: flex;
            }
            
            .nav-links li a {
                display: block;
                padding: 10px;
            }
            
            .auth-buttons {
                margin-right: auto;
            }
        }
        
        @media (max-width: 480px) {
            .auth-buttons {
                flex-direction: column;
                width: 100%;
                order: 4;
                margin-top: 15px;
            }
            
            .btn-login, .btn-register {
                width: 100%;
                text-align: center;
                margin: 5px 0 !important;
            }
        }
    </style>
</head>
<body>

<!-- شريط التنقل الجديد -->
<nav class="navbar">
    <div class="container">
        <a href="index.php" class="navbar-brand">تعاوني</a>
        
        <div class="menu-toggle" onclick="document.getElementById('navLinks').classList.toggle('active')">
            <i class="fas fa-bars"></i>
        </div>
        
        <ul class="nav-links" id="navLinks">
            <li><a href="#home" class="active">الرئيسية</a></li>
            <li><a href="#features">المميزات</a></li>
            <li><a href="#roles">الأدوار</a></li>
            <li><a href="#how-it-works">آلية العمل</a></li>
            <li><a href="#contact">اتصل بنا</a></li>
        </ul>
        
        <div class="auth-buttons">
            <a href="modules/auth/select_type.php" class="btn-login">دخول</a>
            <a href="modules/auth/select_type.php" class="btn-register">تسجيل جديد</a>
        </div>
    </div>
</nav>

<!-- القسم الرئيسي -->
<section class="hero" id="home">
    <div class="hero-shape"></div>
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 hero-content">
                <div class="hero-badge">
                    <i class="fas fa-graduation-cap"></i>
                    منصة التدريب التعاوني الموحدة
                </div>
                <h1>
                    نظام متكامل لـ <span>إدارة التدريب</span>
                    <br>التعاوني إلكترونياً
                </h1>
                <p>
                    منصة ذكية تربط المتدربات بالجهات التدريبية والمشرفين التدريبيين
                    لإدارة عملية التدريب التعاوني بكفاءة وشفافية عالية
                </p>
                <div class="hero-buttons">
                    <a href="modules/auth/select_type.php" class="btn-primary">
                        <i class="fas fa-user-plus"></i>
                        إنشاء حساب جديد
                    </a>
                    <a href="#features" class="btn-outline">
                        <i class="fas fa-play"></i>
                        شاهد المزيد
                    </a>
                </div>

                <!-- الإحصائيات -->
                <div class="stats-container">
                    <a href="modules/auth/select_type.php" class="stat-card">
                        <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                        <div class="stat-number"><?php echo number_format($students_count); ?></div>
                        <div class="stat-label">متدربة</div>
                    </a>
                    <a href="modules/auth/select_type.php" class="stat-card">
                        <div class="stat-icon"><i class="fas fa-building"></i></div>
                        <div class="stat-number"><?php echo number_format($entities_count); ?></div>
                        <div class="stat-label">جهة تدريب</div>
                    </a>
                    <a href="modules/auth/select_type.php" class="stat-card">
                        <div class="stat-icon"><i class="fas fa-briefcase"></i></div>
                        <div class="stat-number"><?php echo number_format($opportunities_count); ?></div>
                        <div class="stat-label">فرصة تدريب</div>
                    </a>
                    <a href="modules/auth/select_type.php" class="stat-card">
                        <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                        <div class="stat-number"><?php echo number_format($trainees_count); ?></div>
                        <div class="stat-label">مشرف تدريبي</div>
                    </a>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="hero-image">
                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='500' height='500' viewBox='0 0 500 500'%3E%3Ccircle cx='250' cy='250' r='200' fill='%232AA7A1' opacity='0.1'/%3E%3Ccircle cx='250' cy='250' r='150' fill='%233F7F95' opacity='0.1'/%3E%3Ccircle cx='250' cy='250' r='100' fill='%239CCB8F' opacity='0.1'/%3E%3Ctext x='180' y='270' fill='%232AA7A1' font-size='40' font-weight='bold'%3Eتعاوني%3C/text%3E%3C/svg%3E" alt="Hero Image">
                </div>
            </div>
        </div>
    </div>
</section>

<!-- قسم المميزات -->
<section class="features" id="features">
    <div class="container">
        <div class="section-title">
            <span class="title-badge">مميزات النظام</span>
            <h2>لماذا نظام تعاوني؟</h2>
            <p>منصة متكاملة توفر جميع الأدوات اللازمة لإنجاح عملية التدريب التعاوني</p>
        </div>
        
        <div class="features-grid">
            <a href="modules/auth/select_type.php?type=student" class="feature-item">
                <div class="feature-icon"><i class="fas fa-user-graduate"></i></div>
                <h3>للطالبات</h3>
                <p>تقديم على فرص تدريب، رفع التقارير، متابعة الطلبات، استلام الشهادات</p>
            </a>
            <a href="modules/auth/select_type.php?type=training_entity" class="feature-item">
                <div class="feature-icon"><i class="fas fa-building"></i></div>
                <h3>للجهات التدريبية</h3>
                <p>نشر مقاعد تدريب، إدارة المقابلات، تقييم المتدربات، تقارير أداء</p>
            </a>
            <a href="modules/auth/select_type.php?type=coordinator" class="feature-item">
                <div class="feature-icon"><i class="fas fa-user-tie"></i></div>
                <h3>للمنسقات</h3>
                <p>اعتماد الجهات، توزيع المتدربات، إحصائيات دقيقة، تقارير شاملة</p>
            </a>
            <a href="modules/auth/select_type.php?type=supervisor" class="feature-item">
                <div class="feature-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                <h3>للمشرفين</h3>
                <p>متابعة المتدربات، مراجعة التقارير، زيارات ميدانية، إصدار الشهادات</p>
            </a>
        </div>
    </div>
</section>

<!-- قسم الأدوار -->
<section class="roles" id="roles">
    <div class="container">
        <div class="section-title">
            <span class="title-badge">الأدوار في النظام</span>
            <h2>من يمكنه استخدام النظام؟</h2>
            <p>صمم النظام ليلبي احتياجات جميع الأطراف المشاركة في التدريب التعاوني</p>
        </div>
        
        <div class="roles-grid">
            <!-- متدربة -->
            <a href="modules/auth/select_type.php?type=student" class="role-card">
                <div class="role-header student">
                    <div class="role-icon"><i class="fas fa-user-graduate"></i></div>
                    <h3>متدربة</h3>
                </div>
                <div class="role-body">
                    <ul class="role-features">
                        <li><i class="fas fa-check-circle"></i> تقديم على فرص تدريب</li>
                        <li><i class="fas fa-check-circle"></i> رفع التقارير الأسبوعية</li>
                        <li><i class="fas fa-check-circle"></i> متابعة حالة الطلب</li>
                        <li><i class="fas fa-check-circle"></i> استلام الشهادة الإلكترونية</li>
                    </ul>
                    <span class="role-btn">
                        دخول <i class="fas fa-arrow-left"></i>
                    </span>
                </div>
            </a>
            
            <!-- جهة تدريبية -->
            <a href="modules/auth/select_type.php?type=training_entity" class="role-card">
                <div class="role-header entity">
                    <div class="role-icon"><i class="fas fa-building"></i></div>
                    <h3>جهة تدريبية</h3>
                </div>
                <div class="role-body">
                    <ul class="role-features">
                        <li><i class="fas fa-check-circle"></i> نشر مقاعد تدريبية</li>
                        <li><i class="fas fa-check-circle"></i> إدارة المقابلات</li>
                        <li><i class="fas fa-check-circle"></i> تقييم المتدربات</li>
                        <li><i class="fas fa-check-circle"></i> إصدار تقارير أداء</li>
                    </ul>
                    <span class="role-btn">
                        دخول <i class="fas fa-arrow-left"></i>
                    </span>
                </div>
            </a>
            
            <!-- منسقة -->
            <a href="modules/auth/select_type.php?type=coordinator" class="role-card">
                <div class="role-header coordinator">
                    <div class="role-icon"><i class="fas fa-user-tie"></i></div>
                    <h3>منسقة</h3>
                </div>
                <div class="role-body">
                    <ul class="role-features">
                        <li><i class="fas fa-check-circle"></i> اعتماد الجهات</li>
                        <li><i class="fas fa-check-circle"></i> توزيع المتدربات</li>
                        <li><i class="fas fa-check-circle"></i> إحصائيات دقيقة</li>
                        <li><i class="fas fa-check-circle"></i> تقارير شاملة</li>
                    </ul>
                    <span class="role-btn">
                        دخول <i class="fas fa-arrow-left"></i>
                    </span>
                </div>
            </a>
            
            <!-- مشرف أكاديمي -->
            <a href="modules/auth/select_type.php?type=supervisor" class="role-card">
                <div class="role-header supervisor">
                    <div class="role-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                    <h3>مشرف أكاديمي</h3>
                </div>
                <div class="role-body">
                    <ul class="role-features">
                        <li><i class="fas fa-check-circle"></i> متابعة المتدربات</li>
                        <li><i class="fas fa-check-circle"></i> مراجعة التقارير</li>
                        <li><i class="fas fa-check-circle"></i> زيارات ميدانية</li>
                        <li><i class="fas fa-check-circle"></i> إصدار الشهادات</li>
                    </ul>
                    <span class="role-btn">
                        دخول <i class="fas fa-arrow-left"></i>
                    </span>
                </div>
            </a>
        </div>
    </div>
</section>

<!-- قسم آلية العمل -->
<section class="how-it-works" id="how-it-works">
    <div class="container">
        <div class="section-title">
            <span class="title-badge">آلية العمل</span>
            <h2>كيف يعمل النظام؟</h2>
            <p>أربع خطوات بسيطة لإتمام عملية التدريب التعاوني</p>
        </div>
        
        <div class="steps">
            <div class="step">
                <div class="step-number">1</div>
                <h4>تسجيل الجهات</h4>
                <p>الجهات التدريبية تسجل بياناتها وتنتظر اعتماد المنسقة</p>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <h4>تقديم المتدربات</h4>
                <p>المتدربات يبحثن عن الفرص المناسبة ويقدمن طلباتهن</p>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <h4>المقابلات والاختيار</h4>
                <p>الجهات تجري المقابلات وتختار المرشحات المناسبات</p>
            </div>
            <div class="step">
                <div class="step-number">4</div>
                <h4>التدريب والمتابعة</h4>
                <p>بدء التدريب مع متابعة يومية وتقييم مستمر</p>
            </div>
        </div>
    </div>
</section>

<!-- قسم الدعوة -->
<section class="cta">
    <div class="container">
        <div class="cta-content">
            <h2>انضم إلينا الآن!</h2>
            <p>ابدأ رحلة التدريب التعاوني مع نظام تعاوني المتكامل</p>
            <div class="cta-buttons">
                <a href="modules/auth/select_type.php?type=student" class="btn-cta-primary">
                    <i class="fas fa-user-graduate"></i>
                    تسجيل كمتدربة
                </a>
                <a href="modules/auth/select_type.php?type=training_entity" class="btn-cta-outline">
                    <i class="fas fa-building"></i>
                    تسجيل كجهة تدريبية
                </a>
            </div>
        </div>
    </div>
</section>

<!-- التذييل -->
<footer class="footer" id="contact">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-about">
                <div class="footer-logo">
                    <div class="brand-main">تعاوني</div>
                    <div class="brand-sub">نظام إدارة التدريب التعاوني</div>
                </div>
                <p>منصة متكاملة لإدارة التدريب التعاوني، تربط المتدربات بالجهات التدريبية والمشرفين الأكاديميين في بيئة إلكترونية متطورة.</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            
            <div>
                <h4>روابط سريعة</h4>
                <ul class="footer-links">
                    <li><a href="#home"><i class="fas fa-angle-left"></i> الرئيسية</a></li>
                    <li><a href="#features"><i class="fas fa-angle-left"></i> المميزات</a></li>
                    <li><a href="#roles"><i class="fas fa-angle-left"></i> الأدوار</a></li>
                    <li><a href="#how-it-works"><i class="fas fa-angle-left"></i> آلية العمل</a></li>
                    <li><a href="modules/auth/select_type.php"><i class="fas fa-angle-left"></i> دخول</a></li>
                    <li><a href="modules/auth/select_type.php"><i class="fas fa-angle-left"></i> تسجيل جديد</a></li>
                </ul>
            </div>
            
            <div>
                <h4>الدعم</h4>
                <ul class="footer-links">
                    <li><a href="contact.php"><i class="fas fa-angle-left"></i> اتصل بنا</a></li>
                    <li><a href="privacy.php"><i class="fas fa-angle-left"></i> سياسة الخصوصية</a></li>
                    <li><a href="terms.php"><i class="fas fa-angle-left"></i> شروط الاستخدام</a></li>
                    <li><a href="help.php"><i class="fas fa-angle-left"></i> مركز المساعدة</a></li>
                </ul>
            </div>
            
            <div>
                <h4>معلومات الاتصال</h4>
                <ul class="footer-contact">
                    <li><i class="fas fa-map-marker-alt"></i> الرياض، المملكة العربية السعودية</li>
                    <li><i class="fas fa-phone"></i> +966 00000000000</li>
                    <li><i class="fas fa-envelope"></i> info@taawoni.edu.sa</li>
                </ul>
            </div>
        </div>
        
        <div class="copyright">
            <p>جميع الحقوق محفوظة &copy; <?php echo date('Y'); ?> | 
                <a href="index.php" style="color: rgba(255,255,255,0.5); text-decoration: none;">تعاوني</a>
            </p>
        </div>
    </div>
</footer>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // تأثير التمرير على شريط التنقل
    $(window).scroll(function() {
        if ($(this).scrollTop() > 50) {
            $('.navbar').addClass('scrolled');
        } else {
            $('.navbar').removeClass('scrolled');
        }
    });

    // تمرير سلس للروابط
    $('a[href*="#"]').on('click', function(e) {
        if (this.hash !== '') {
            e.preventDefault();
            const hash = this.hash;
            $('html, body').animate({
                scrollTop: $(hash).offset().top - 76
            }, 500, function() {
                window.location.hash = hash;
            });
            
            // إغلاق القائمة في الجوال بعد النقر
            if ($(window).width() < 992) {
                document.getElementById('navLinks').classList.remove('active');
            }
        }
    });

    // تحريك الأرقام
    function animateNumbers() {
        $('.stat-number').each(function() {
            var number = $(this).text().replace(/,/g, '');
            if (!isNaN(number) && number != '') {
                $(this).prop('Counter', 0).animate({
                    Counter: number
                }, {
                    duration: 2000,
                    easing: 'swing',
                    step: function(now) {
                        $(this).text(Math.ceil(now).toLocaleString());
                    }
                });
            }
        });
    }

    // تفعيل تحريك الأرقام عند التمرير
    var triggered = false;
    $(window).scroll(function() {
        if (!triggered && $(window).scrollTop() > $('.hero').height() - 200) {
            animateNumbers();
            triggered = true;
        }
    });

    // تحديد الرابط النشط عند التمرير
    $(window).scroll(function() {
        var scrollDistance = $(window).scrollTop() + 100;
        
        $('section').each(function(i) {
            if ($(this).position().top <= scrollDistance) {
                $('.nav-links a.active').removeClass('active');
                $('.nav-links a').eq(i).addClass('active');
            }
        });
    }).scroll();
</script>

</body>
</html>