 <?php


if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'supervisor') {
    header('Location: ../auth/login.php?type=supervisor');
    exit();
}

// جلب بيانات المشرف
$stmt = $pdo->prepare("
    SELECT s.*, u.email 
    FROM supervisors s
    JOIN users u ON s.user_id = u.id
    WHERE s.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$supervisor = $stmt->fetch();

if(!$supervisor) {
    header('Location: ../auth/logout.php');
    exit();
}

// جلب الإشعارات غير المقروءة
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND user_type = 'supervisor' AND is_read = 0");
$stmt->execute([$_SESSION['user_id']]);
$unread_notifications = $stmt->fetchColumn();

// جلب عدد المتدربات المشرف عليها
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM applications 
    WHERE supervisor_id = ? AND status IN ('approved', 'accepted_by_entity')
");
$stmt->execute([$supervisor['id']]);
$my_students_count = $stmt->fetchColumn();

// جلب عدد التقارير في انتظار المراجعة
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM weekly_reports r
    JOIN applications a ON r.application_id = a.id
    WHERE a.supervisor_id = ? AND r.status = 'submitted'
");
$stmt->execute([$supervisor['id']]);
$pending_reports = $stmt->fetchColumn();

$page_title = $page_title ?? 'لوحة التحكم';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | <?php echo SITE_NAME; ?></title>
    
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
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: var(--light);
            color: var(--dark);
            overflow-x: hidden;
        }
        
        /* Navbar */
        .navbar {
            background: white;
            box-shadow: 0 2px 20px rgba(42, 167, 161, 0.1);
            padding: 0.8rem 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .navbar.scrolled {
            padding: 0.5rem 0;
            background: rgba(255,255,255,0.98);
            box-shadow: 0 5px 30px rgba(42, 167, 161, 0.15);
        }
        
        .navbar-brand {
            display: flex;
            align-items: center;
            text-decoration: none;
        }
        
        .brand-text {
            margin-right: 10px;
        }
        
        .brand-main {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1.2;
        }
        
        .brand-sub {
            font-size: 0.75rem;
            color: var(--gray);
            letter-spacing: 1px;
        }
        
        /* Wrapper */
        .wrapper {
            display: flex;
            margin-top: 76px;
        }
        
        /* Sidebar */
        .sidebar {
            width: 280px;
            background: white;
            min-height: calc(100vh - 76px);
            box-shadow: 0 0 20px rgba(42, 167, 161, 0.05);
            position: fixed;
            right: 0;
            overflow-y: auto;
            transition: all 0.3s ease;
        }
        
        .sidebar .user-info {
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid #edf2f7;
        }
        
        .avatar-circle {
            width: 100px;
            height: 100px;
            background: var(--gradient);
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 40px;
            border: 4px solid white;
            box-shadow: 0 5px 15px rgba(42, 167, 161, 0.3);
        }
        
        .user-info h5 {
            color: var(--primary);
            margin-bottom: 5px;
            font-weight: 700;
        }
        
        .user-info p {
            color: var(--gray);
            font-size: 0.9rem;
            margin: 0;
        }
        
        .nav-menu {
            padding: 20px 0;
        }
        
        .nav-link {
            color: #4a5568;
            padding: 12px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            border-right: 4px solid transparent;
            text-decoration: none;
            display: block;
        }
        
        .nav-link:hover,
        .nav-link.active {
            background: rgba(42, 167, 161, 0.05);
            color: var(--primary);
            border-right-color: var(--primary);
        }
        
        .nav-link i {
            margin-left: 10px;
            width: 25px;
            color: var(--primary);
        }
        
        .nav-link:hover i {
            color: var(--primary);
        }
        
        .badge-count {
            background: var(--danger);
            color: white;
            padding: 2px 8px;
            border-radius: 50px;
            font-size: 0.7rem;
            margin-right: 5px;
        }
        
        /* Main Content */
        .content {
            width: calc(100% - 280px);
            margin-right: 280px;
            padding: 30px;
        }
        
        /* Page Header */
        .page-header {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(42, 167, 161, 0.05);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        /* Buttons */
        .btn-primary-custom {
            background: var(--gradient);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(42, 167, 161, 0.3);
            color: white;
        }
        
        .btn-outline-custom {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
        }
        
        .btn-outline-custom:hover {
            background: var(--primary);
            color: white;
        }
        
        /* Cards */
        .card-custom {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(42, 167, 161, 0.05);
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .card-custom:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(42, 167, 161, 0.1);
        }
        
        /* Tables */
        .table-custom {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(42, 167, 161, 0.05);
        }
        
        .table-custom thead th {
            background: var(--gradient);
            color: white;
            font-weight: 600;
            border: none;
            padding: 15px;
        }
        
        .table-custom tbody td {
            padding: 15px;
            vertical-align: middle;
            border-bottom: 1px solid #edf2f7;
        }
        
        .table-custom tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* Alerts */
        .alert {
            border-radius: 12px;
            margin-bottom: 20px;
            padding: 15px 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        /* Badges */
        .badge {
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .badge.bg-success { background: #28a745; color: white; }
        .badge.bg-warning { background: #ffc107; color: #212529; }
        .badge.bg-danger { background: #dc3545; color: white; }
        .badge.bg-info { background: #17a2b8; color: white; }
        .badge.bg-primary { background: var(--primary); color: white; }
        .badge.bg-secondary { background: #6c757d; color: white; }
        
        /* Forms */
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            padding: 10px 15px;
            font-family: 'Cairo', sans-serif;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(42, 167, 161, 0.25);
            outline: none;
        }
        
        /* Progress */
        .progress {
            height: 8px;
            border-radius: 10px;
            background: #f0f0f0;
        }
        
        .progress-bar {
            background: var(--gradient);
            border-radius: 10px;
        }
        
        /* Back Link */
        .back-link {
            margin-bottom: 20px;
        }
        
        .back-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .sidebar {
                right: -280px;
            }
            
            .sidebar.active {
                right: 0;
            }
            
            .content {
                width: 100%;
                margin-right: 0;
            }
            
            .menu-toggle {
                display: block;
            }
        }
        
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="container-fluid px-4">
            <a class="navbar-brand" href="dashboard.php">
                <div class="brand-text">
                    <div class="brand-main">تعاوني</div>
                    <div class="brand-sub">Taawoni</div>
                </div>
            </a>
            
            <div class="d-flex align-items-center">
                <!-- Notifications -->
                <div class="dropdown mx-3">
                    <button class="btn btn-light position-relative" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-bell"></i>
                        <?php if($unread_notifications > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?php echo $unread_notifications; ?>
                        </span>
                        <?php endif; ?>
                    </button>
                </div>
                
                <!-- User Menu -->
                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-chalkboard-teacher ms-2"></i>
                        <?php echo htmlspecialchars($supervisor['full_name_ar']); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user ms-2"></i> الملف الشخصي</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="../auth/logout.php"><i class="fas fa-sign-out-alt ms-2"></i> تسجيل خروج</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
    
    <div class="wrapper">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="user-info">
                <div class="avatar-circle">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <h5><?php echo htmlspecialchars($supervisor['full_name_ar']); ?></h5>
                <p><?php echo $supervisor['academic_title'] ?? 'مشرف أكاديمي'; ?></p>
                <p><small class="text-muted"><?php echo $supervisor['department'] ?? ''; ?></small></p>
            </div>
            
            <div class="nav-menu">
                <?php
                $menu_items = [
                    ['dashboard.php', 'fa-home', 'الرئيسية'],
                    ['profile.php', 'fa-user', 'الملف الشخصي'],
                    ['students.php', 'fa-user-graduate', 'المتدربات المشرف عليها', $my_students_count],
                    ['reports.php', 'fa-file-alt', 'التقارير', $pending_reports],
                    ['visits.php', 'fa-calendar-check', 'الزيارات الميدانية'],
                    ['evaluations.php', 'fa-star', 'التقييم النهائي'],
                    ['messages.php', 'fa-envelope', 'الرسائل']
                ];
                
                $current_page = basename($_SERVER['PHP_SELF']);
                
                foreach($menu_items as $item):
                    $active = ($current_page == $item[0]) ? 'active' : '';
                ?>
                <a class="nav-link <?php echo $active; ?>" href="<?php echo $item[0]; ?>">
                    <i class="fas <?php echo $item[1]; ?>"></i>
                    <?php echo $item[2]; ?>
                    <?php if(isset($item[3]) && $item[3] > 0): ?>
                    <span class="badge-count float-start"><?php echo $item[3]; ?></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>