<?php
// modules/training_entity/interview_save.php - حفظ المقابلة
session_start();
require_once '../../config/config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'training_entity') {
    header('Location: ../auth/login.php?type=training_entity');
    exit();
}

if($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: applications.php');
    exit();
}

// جلب بيانات الجهة
$stmt = $pdo->prepare("SELECT id FROM training_entities WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$entity = $stmt->fetch();

$application_id = $_POST['application_id'] ?? 0;
$interview_date = $_POST['interview_date'] ?? '';
$interview_time = $_POST['interview_time'] ?? '';
$interview_type = $_POST['interview_type'] ?? 'online';
$meeting_link = $_POST['meeting_link'] ?? '';
$location = $_POST['location'] ?? '';
$interviewer_name = $_POST['interviewer_name'] ?? '';
$feedback = $_POST['feedback'] ?? '';

// التحقق من البيانات
if(empty($application_id) || empty($interview_date) || empty($interview_time)) {
    $_SESSION['error'] = 'التاريخ والوقت حقول مطلوبة';
    header('Location: interview_schedule.php?application_id=' . $application_id);
    exit();
}

// التأكد من أن الطلب تابع للجهة
$check = $pdo->prepare("
    SELECT a.*, s.user_id as student_user_id
    FROM applications a
    JOIN training_seats ts ON a.seat_id = ts.id
    JOIN students s ON a.student_id = s.id
    WHERE a.id = ? AND ts.entity_id = ?
");
$check->execute([$application_id, $entity['id']]);
$application = $check->fetch();

if(!$application) {
    header('Location: applications.php');
    exit();
}

try {
    // بدء معاملة
    $pdo->beginTransaction();
    
    // إدراج المقابلة
    $datetime = $interview_date . ' ' . $interview_time . ':00';
    
    $stmt = $pdo->prepare("
        INSERT INTO interviews 
        (application_id, interview_date, interview_type, meeting_link, location, 
         interviewer_name, feedback, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', NOW())
    ");
    $stmt->execute([
        $application_id,
        $datetime,
        $interview_type,
        $meeting_link,
        $location,
        $interviewer_name,
        $feedback
    ]);
    
    // تحديث حالة الطلب
    $update = $pdo->prepare("UPDATE applications SET status = 'interview_scheduled' WHERE id = ?");
    $update->execute([$application_id]);
    
    // إرسال إشعار للمتدربة
    $notify = $pdo->prepare("
        INSERT INTO notifications (user_id, user_type, title, message, type, related_link, related_id, created_at)
        VALUES (?, 'student', 'موعد مقابلة', ?, 'info', 'applications/view.php?id=?', ?, NOW())
    ");
    $notify->execute([
        $application['student_user_id'],
        'تم تحديد موعد مقابلة مع جهة ' . $entity['entity_name_ar'] . ' للفرصة ' . $application['title_ar'],
        $application_id,
        $application_id
    ]);
    
    // تأكيد المعاملة
    $pdo->commit();
    
    $_SESSION['success'] = 'تم جدولة المقابلة بنجاح';
    header('Location: application_view.php?id=' . $application_id);
    exit();
    
} catch(Exception $e) {
    $pdo->rollBack();
    error_log("Interview Save Error: " . $e->getMessage());
    $_SESSION['error'] = 'حدث خطأ أثناء جدولة المقابلة';
    header('Location: interview_schedule.php?application_id=' . $application_id);
    exit();
}
?>