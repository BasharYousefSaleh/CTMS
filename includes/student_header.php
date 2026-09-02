 <?php
 

if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'student') {
    header('Location: ../auth/login.php?type=student');
    exit();
}

// جلب بيانات المتدربة
$stmt = $pdo->prepare("
    SELECT s.*, m.major_name_ar, u.email 
    FROM students s
    LEFT JOIN majors m ON s.major_id = m.id
    JOIN users u ON s.user_id = u.id
    WHERE s.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();

if(!$student) {
    header('Location: ../auth/logout.php');
    exit();
}

// جلب الإشعارات غير المقروءة
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND user_type = 'student' AND is_read = 0");
$stmt->execute([$_SESSION['user_id']]);
$unread_notifications = $stmt->fetchColumn();

// جلب عدد الرسائل غير المقروءة
$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND receiver_type = 'student' AND is_read = 0");
$stmt->execute([$_SESSION['user_id']]);
$unread_messages = $stmt->fetchColumn();

$page_title = $page_title ?? 'لوحة التحكم';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | تعاوني</title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #2AA7A1;
            --secondary: #9CCB8F;
            --accent: #3F7F95;
            --gradient: linear-gradient(135deg, #2AA7A1, #9CCB8F, #3F7F95);
            --light: #F5F5F5;
            --dark: #3A3A3A;
            --white: #FFFFFF;
            --gray: #666666;
            --shadow: 0 10px 40px rgba(42, 167, 161, 0.1);
            --shadow-hover: 0 20px 60px rgba(42, 167, 161, 0.15);
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
            background: var(--white);
            box-shadow: 0 2px 20px rgba(42, 167, 161, 0.1);
            padding: 0.8rem 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
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
        
        .menu-toggle {
            display: none;
            font-size: 1.5rem;
            color: var(--primary);
            cursor: pointer;
        }
        
        .nav-links {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            gap: 20px;
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
        
        /* Navbar Icons */
        .nav-icons {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .icon-btn {
            background: transparent;
            border: none;
            color: var(--primary);
            font-size: 1.2rem;
            position: relative;
            transition: all 0.3s ease;
            padding: 5px;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }
        
        .icon-btn:hover {
            background: rgba(42, 167, 161, 0.1);
            color: var(--primary);
            transform: translateY(-2px);
        }
        
        .badge-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 50px;
            min-width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* زر الخروج */
        .btn-logout {
            background: transparent;
            color: var(--danger);
            border: 2px solid var(--danger);
            border-radius: 50px;
            padding: 0.5rem 1.2rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }
        
        .btn-logout:hover {
            background: var(--danger);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220,53,69,0.3);
        }
        
        /* User Menu */
        .user-menu {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(42, 167, 161, 0.05);
            padding: 0.3rem 0.8rem;
            border-radius: 50px;
            border: 1px solid rgba(42, 167, 161, 0.2);
        }
        
        .user-name {
            font-weight: 600;
            color: var(--dark);
            font-size: 0.9rem;
        }
        
        .user-avatar {
            width: 35px;
            height: 35px;
            background: var(--gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
        }
        
        .dropdown-menu {
            border: none;
            box-shadow: var(--shadow);
            border-radius: 15px;
            padding: 10px;
        }
        
        .dropdown-item {
            border-radius: 10px;
            padding: 8px 15px;
            transition: all 0.3s ease;
        }
        
        .dropdown-item:hover {
            background: rgba(42, 167, 161, 0.1);
            color: var(--primary);
        }
        
        .dropdown-item.text-danger:hover {
            background: rgba(220,53,69,0.1);
            color: var(--danger);
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
        
        .badge-count-sidebar {
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
            .menu-toggle {
                display: block;
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
            
            .nav-icons {
                margin-right: auto;
            }
            
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
        }
        
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .nav-icons {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .user-menu {
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="container">
            <a href="dashboard.php" class="navbar-brand">تعاوني</a>
            
            <div class="menu-toggle" onclick="document.getElementById('navLinks').classList.toggle('active')">
                <i class="fas fa-bars"></i>
            </div>
            
            <ul class="nav-links" id="navLinks">
                <li><a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">الرئيسية</a></li>
                <li><a href="profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">الملف الشخصي</a></li>
                <li><a href="opportunities.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'opportunities.php' ? 'active' : ''; ?>">فرص التدريب</a></li>
                <li><a href="applications.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'applications.php' ? 'active' : ''; ?>">طلباتي</a></li>
                <li><a href="attendance.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'active' : ''; ?>">الحضور</a></li>
                <li><a href="reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">التقارير</a></li>
                <li><a href="documents.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'documents.php' ? 'active' : ''; ?>">المستندات</a></li>
            </ul>
            
            <div class="nav-icons">
                <a href="notifications.php" class="icon-btn">
                    <i class="fas fa-bell"></i>
                    <?php if($unread_notifications > 0): ?>
                    <span class="badge-count"><?php echo $unread_notifications; ?></span>
                    <?php endif; ?>
                </a>
                
                <a href="messages.php" class="icon-btn">
                    <i class="fas fa-envelope"></i>
                    <?php if($unread_messages > 0): ?>
                    <span class="badge-count"><?php echo $unread_messages; ?></span>
                    <?php endif; ?>
                </a>
                
                <div class="dropdown">
                    <div class="user-menu" data-bs-toggle="dropdown">
                        <span class="user-name"><?php echo htmlspecialchars($student['full_name_ar']); ?></span>
                        <div class="user-avatar">
                            <?php echo mb_substr($student['full_name_ar'], 0, 1); ?>
                        </div>
                    </div>
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
                    <?php echo mb_substr($student['full_name_ar'], 0, 1); ?>
                </div>
                <h5><?php echo htmlspecialchars($student['full_name_ar']); ?></h5>
                <p><?php echo htmlspecialchars($student['student_id']); ?></p>
                <p><small class="text-muted"><?php echo htmlspecialchars($student['major_name_ar'] ?? 'غير محدد'); ?></small></p>
            </div>
            
            <div class="nav-menu">
                <?php
                $menu_items = [
                    ['dashboard.php', 'fa-home', 'الرئيسية'],
                    ['profile.php', 'fa-user', 'الملف الشخصي'],
                    ['opportunities.php', 'fa-briefcase', 'فرص التدريب'],
                    ['applications.php', 'fa-file-alt', 'طلباتي'],
                    ['attendance.php', 'fa-calendar-check', 'الحضور'],
                    ['reports.php', 'fa-chart-bar', 'التقارير'],
                    ['documents.php', 'fa-folder', 'المستندات'],
                    ['notifications.php', 'fa-bell', 'الإشعارات', $unread_notifications],
                    ['messages.php', 'fa-envelope', 'الرسائل', $unread_messages]
                ];
                
                $current_page = basename($_SERVER['PHP_SELF']);
                
                foreach($menu_items as $item):
                    $active = ($current_page == $item[0]) ? 'active' : '';
                ?>
                <a class="nav-link <?php echo $active; ?>" href="<?php echo $item[0]; ?>">
                    <i class="fas <?php echo $item[1]; ?>"></i>
                    <?php echo $item[2]; ?>
                    <?php if(isset($item[3]) && $item[3] > 0): ?>
                    <span class="badge-count-sidebar float-start"><?php echo $item[3]; ?></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
