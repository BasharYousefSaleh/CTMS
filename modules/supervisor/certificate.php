 <?php
// modules/supervisor/certificate.php - عرض الشهادة التدريبية
session_start();
require_once '../../config/config.php';

// =============================================
// التحقق من تسجيل الدخول
// =============================================
if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'supervisor') {
    header('Location: ../auth/login.php?type=supervisor');
    exit();
}

// جلب بيانات المشرف
$stmt = $pdo->prepare("SELECT * FROM supervisors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$supervisor = $stmt->fetch();

if(!$supervisor) {
    header('Location: ../auth/logout.php');
    exit();
}

// =============================================
// جلب بيانات الشهادة (✅ تم التعديل)
// =============================================
$application_id = $_GET['app_id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT 
        e.*,
        s.full_name_ar,
        s.student_id,
        s.national_id,
        s.gpa,
        m.major_name_ar,
        ts.title_ar,
        ts.duration_weeks,
        ts.start_date,
        ts.end_date,
        te.entity_name_ar,
        a.completion_date,
        sup.full_name_ar as supervisor_name,
        sup.academic_title as supervisor_title
    FROM evaluations e
    JOIN applications a ON e.application_id = a.id
    JOIN students s ON a.student_id = s.id
    LEFT JOIN majors m ON s.major_id = m.id
    JOIN training_seats ts ON a.seat_id = ts.id
    JOIN training_entities te ON ts.entity_id = te.id
    JOIN supervisors sup ON e.issued_by = sup.id
    WHERE a.id = ? AND a.supervisor_id = ?
");
$stmt->execute([$application_id, $supervisor['id']]);
$certificate = $stmt->fetch();

if(!$certificate) {
    $_SESSION['error'] = '❌ الشهادة غير موجودة';
    header('Location: evaluations.php');
    exit();
}

$page_title = 'شهادة تدريب';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>شهادة تدريب - <?php echo $certificate['full_name_ar']; ?></title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Cairo', 'Tajawal', sans-serif;
            background: #f0f0f0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .certificate-wrapper {
            max-width: 1100px;
            width: 100%;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        
        .certificate {
            background: white;
            padding: 50px;
            border: 20px solid #006633;
            position: relative;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><path fill="%23f0f7f0" d="M10 10 L 90 10 L 90 90 L 10 90 Z" stroke="%23006633" stroke-width="1" fill="none"/><path fill="%23f0f7f0" d="M0 0 L 100 0 L 100 100 L 0 100 Z" stroke="%23006633" stroke-width="2" fill="none" stroke-dasharray="5,5"/></svg>');
            background-repeat: repeat;
            background-size: 30px 30px;
            position: relative;
            border-radius: 20px;
        }
        
        .certificate::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.95);
            border-radius: 10px;
            pointer-events: none;
        }
        
        .certificate-content {
            position: relative;
            z-index: 2;
        }
        
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 40px;
            border-bottom: 3px solid #006633;
            padding-bottom: 20px;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #006633, #228B22, #90EE90);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 40px;
        }
        
        .logo-text h1 {
            font-size: 32px;
            font-weight: 800;
            color: #006633;
            margin-bottom: 5px;
        }
        
        .logo-text p {
            font-size: 18px;
            color: #666;
        }
        
        .certificate-number {
            text-align: left;
            font-size: 16px;
            color: #006633;
            font-weight: 600;
            border: 2px solid #006633;
            padding: 10px 20px;
            border-radius: 50px;
        }
        
        .title {
            text-align: center;
            margin: 40px 0;
        }
        
        .title h2 {
            font-size: 48px;
            font-weight: 800;
            color: #006633;
            margin-bottom: 10px;
            letter-spacing: 2px;
        }
        
        .title p {
            font-size: 20px;
            color: #666;
            font-weight: 500;
        }
        
        .body {
            text-align: center;
            margin: 40px 0;
            line-height: 2.5;
        }
        
        .body p {
            font-size: 24px;
            color: #333;
            margin-bottom: 15px;
        }
        
        .student-name {
            font-size: 48px !important;
            font-weight: 800;
            color: #006633 !important;
            margin: 20px 0 !important;
            border-bottom: 3px solid #90EE90;
            display: inline-block;
            padding-bottom: 10px;
        }
        
        .training-details {
            background: #f0f7f0;
            padding: 30px;
            border-radius: 20px;
            margin: 30px 0;
            border: 2px solid #006633;
        }
        
        .training-details p {
            font-size: 22px;
            margin: 10px 0;
        }
        
        .grade-box {
            display: inline-block;
            background: linear-gradient(135deg, #006633, #228B22);
            color: white;
            padding: 15px 40px;
            border-radius: 50px;
            font-size: 32px;
            font-weight: 800;
            margin: 20px 0;
            box-shadow: 0 5px 15px rgba(0,102,51,0.3);
        }
        
        .signatures {
            display: flex;
            justify-content: space-between;
            margin: 60px 0 30px;
        }
        
        .signature {
            text-align: center;
            width: 250px;
        }
        
        .signature-line {
            border-bottom: 2px solid #333;
            margin: 10px 0;
            padding-top: 40px;
        }
        
        .signature p {
            font-size: 18px;
            font-weight: 600;
        }
        
        .signature small {
            color: #666;
        }
        
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #006633;
            color: #666;
            font-size: 16px;
        }
        
        .qr-code {
            position: absolute;
            bottom: 50px;
            left: 50px;
            width: 100px;
            height: 100px;
            background: #f0f7f0;
            border: 2px solid #006633;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: #666;
        }
        
        .actions {
            text-align: center;
            margin-top: 30px;
        }
        
        .btn-print {
            background: linear-gradient(135deg, #006633, #228B22);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 50px;
            font-size: 20px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 0 10px;
        }
        
        .btn-print:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,102,51,0.3);
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 50px;
            font-size: 20px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-back:hover {
            background: #5a6268;
            color: white;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .certificate-wrapper {
                box-shadow: none;
            }
            
            .actions {
                display: none;
            }
            
            .certificate {
                border: 15px solid #006633;
            }
        }
    </style>
</head>
<body>
    <div class="certificate-wrapper">
        <div class="certificate">
            <div class="certificate-content">
                <!-- الهيدر -->
                <div class="header">
                    <div class="logo">
                        <div class="logo-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="logo-text">
                            <h1>تعاوني</h1>
                            <p>نظام التدريب التعاوني</p>
                        </div>
                    </div>
                    <div class="certificate-number">
                        رقم الشهادة: <?php echo $certificate['certificate_number']; ?>
                    </div>
                </div>
                
                <!-- العنوان الرئيسي -->
                <div class="title">
                    <h2>شهادة تدريب</h2>
                    <p>Collaborative Training Certificate</p>
                </div>
                
                <!-- المحتوى -->
                <div class="body">
                    <p>يشهد مركز التدريب التعاوني بأن</p>
                    <p class="student-name"><?php echo $certificate['full_name_ar']; ?></p>
                    <p>قد أتمت بنجاح برنامج التدريب التعاوني</p>
                    
                    <div class="training-details">
                        <p><strong>عنوان التدريب:</strong> <?php echo $certificate['title_ar']; ?></p>
                        <p><strong>الجهة التدريبية:</strong> <?php echo $certificate['entity_name_ar']; ?></p>
                        <p><strong>التخصص:</strong> <?php echo $certificate['major_name_ar'] ?? 'تقنية المعلومات'; ?></p>
                        <p><strong>مدة التدريب:</strong> <?php echo $certificate['duration_weeks'] ?? 8; ?> أسابيع</p>
                        <p><strong>الفترة:</strong> من <?php echo date('Y-m-d', strtotime($certificate['start_date'])); ?> إلى <?php echo date('Y-m-d', strtotime($certificate['end_date'])); ?></p>
                    </div>
                    
                    <p>وذلك بتقدير</p>
                    <div class="grade-box">
                        <?php echo $certificate['final_grade_letter']; ?> (<?php echo number_format($certificate['final_grade'], 1); ?>%)
                    </div>
                </div>
                
                <!-- التواقيع -->
                <div class="signatures">
                    <div class="signature">
                        <div class="signature-line"></div>
                        <p>مشرف التدريب</p>
                        <small><?php echo $certificate['supervisor_name']; ?></small>
                        <br>
                        <small><?php echo $certificate['supervisor_title']; ?></small>
                    </div>
                    
                    <div class="signature">
                        <div class="signature-line"></div>
                        <p>منسقة التدريب</p>
                        <small>أ. نورة الشمري</small>
                    </div>
                    
                    <div class="signature">
                        <div class="signature-line"></div>
                        <p>عميدة الكلية</p>
                        <small>د.  -----------</small>
                    </div>
                </div>
                
                <!-- التاريخ -->
                <div style="text-align: center; margin: 20px 0;">
                    <p>تاريخ الإصدار: <?php echo date('Y-m-d', strtotime($certificate['certificate_issued_at'])); ?></p>
                </div>
                
                <!-- الفوتر -->
                <div class="footer">
                    <p>جميع الحقوق محفوظة &copy; <?php echo date('Y'); ?> - نظام تعاوني للتدريب التعاوني</p>
                    <p>هذه الشهادة معتمدة إلكترونياً ويمكن التحقق منها عبر الموقع الإلكتروني</p>
                </div>
                
           
            </div>
        </div>
        
        <!-- أزرار الإجراءات (تظهر فقط في المتصفح، تختفي عند الطباعة) -->
        <div class="actions">
            <button onclick="window.print()" class="btn-print">
                <i class="fas fa-print ms-2"></i> طباعة الشهادة
            </button>
            <button onclick="window.close()" class="btn-back">
                <i class="fas fa-times ms-2"></i> إغلاق
            </button>
        </div>
    </div>
    
    <!-- Font Awesome (CDN) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    
    <script>
        // توجيه المستخدم لاستخدام الطباعة
        document.addEventListener('keydown', function(e) {
            // Ctrl+P أو Cmd+P
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });
    </script>
</body>
</html>