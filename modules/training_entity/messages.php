<?php
// modules/training_entity/messages.php - نظام الرسائل الداخلي
session_start();
require_once '../../config/config.php';

// =============================================
// التحقق من تسجيل الدخول
// =============================================
if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'training_entity') {
    header('Location: ../auth/login.php?type=training_entity');
    exit();
}

// جلب بيانات الجهة
$stmt = $pdo->prepare("SELECT * FROM training_entities WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$entity = $stmt->fetch();

if(!$entity) {
    header('Location: ../auth/logout.php');
    exit();
}

// =============================================
// معالجة إرسال رسالة جديدة
// =============================================
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    
    $receiver_type = $_POST['receiver_type'] ?? '';
    $receiver_id = $_POST['receiver_id'] ?? 0;
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $application_id = $_POST['application_id'] ?? null;
    
    // التحقق من البيانات
    if(empty($receiver_type) || empty($receiver_id) || empty($subject) || empty($message)) {
        $_SESSION['error'] = 'جميع الحقول مطلوبة';
        header('Location: messages.php?compose=1');
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO messages 
            (sender_id, sender_type, receiver_id, receiver_type, subject, message, application_id, created_at)
            VALUES (?, 'training_entity', ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$_SESSION['user_id'], $receiver_id, $receiver_type, $subject, $message, $application_id]);
        
        $_SESSION['success'] = 'تم إرسال الرسالة بنجاح';
        
    } catch(Exception $e) {
        $_SESSION['error'] = 'حدث خطأ: ' . $e->getMessage();
    }
    
    header('Location: messages.php?folder=sent');
    exit();
}

// =============================================
// معالجة حذف رسالة
// =============================================
if(isset($_GET['delete'])) {
    $message_id = $_GET['delete'];
    
    $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ? AND (sender_id = ? OR receiver_id = ?)");
    $stmt->execute([$message_id, $_SESSION['user_id'], $_SESSION['user_id']]);
    
    $_SESSION['success'] = 'تم حذف الرسالة';
    header('Location: messages.php?folder=' . ($_GET['folder'] ?? 'inbox'));
    exit();
}

// =============================================
// معالجة تحديث حالة القراءة
// =============================================
if(isset($_GET['mark_read'])) {
    $message_id = $_GET['mark_read'];
    
    $update = $pdo->prepare("UPDATE messages SET is_read = 1, read_at = NOW() WHERE id = ? AND receiver_id = ?");
    $update->execute([$message_id, $_SESSION['user_id']]);
    
    header('Location: messages.php?view=' . $message_id);
    exit();
}

// =============================================
// جلب البيانات
// =============================================

$page_title = 'الرسائل';
require_once '../../includes/entity_header.php';

$folder = $_GET['folder'] ?? 'inbox';
$view_id = $_GET['view'] ?? 0;
$compose = isset($_GET['compose']) ? true : false;
$reply_to = $_GET['reply'] ?? 0;

// جلب قائمة المتدربات للرسائل
$students = $pdo->prepare("
    SELECT DISTINCT s.user_id, s.full_name_ar, s.student_id
    FROM students s
    JOIN applications a ON s.id = a.student_id
    JOIN training_seats ts ON a.seat_id = ts.id
    WHERE ts.entity_id = ?
    ORDER BY s.full_name_ar
");
$students->execute([$entity['id']]);
$students_list = $students->fetchAll();

// جلب قائمة المشرفين
$supervisors = $pdo->prepare("
    SELECT id as user_id, full_name_ar, 'مشرف' as role
    FROM supervisors
    ORDER BY full_name_ar
");
$supervisors->execute();
$supervisors_list = $supervisors->fetchAll();

// جلب قائمة المنسقات
$coordinators = $pdo->prepare("
    SELECT id as user_id, full_name_ar, 'منسقة' as role
    FROM coordinators
");
$coordinators->execute();
$coordinators_list = $coordinators->fetchAll();

// جلب طلبات المتدربات النشطة
$applications = $pdo->prepare("
    SELECT a.id, s.full_name_ar, ts.title_ar
    FROM applications a
    JOIN students s ON a.student_id = s.id
    JOIN training_seats ts ON a.seat_id = ts.id
    WHERE ts.entity_id = ? AND a.status IN ('approved', 'accepted_by_entity')
    ORDER BY a.created_at DESC
");
$applications->execute([$entity['id']]);
$applications_list = $applications->fetchAll();

// جلب الرسائل حسب المجلد
if($folder == 'inbox') {
    $sql = "
        SELECT m.*,
               CASE 
                   WHEN m.sender_type = 'student' THEN (SELECT full_name_ar FROM students WHERE user_id = m.sender_id)
                   WHEN m.sender_type = 'supervisor' THEN (SELECT full_name_ar FROM supervisors WHERE user_id = m.sender_id)
                   WHEN m.sender_type = 'coordinator' THEN (SELECT full_name_ar FROM coordinators WHERE user_id = m.sender_id)
               END as sender_name,
               CASE 
                   WHEN m.sender_type = 'student' THEN 'متدربة'
                   WHEN m.sender_type = 'supervisor' THEN 'مشرف'
                   WHEN m.sender_type = 'coordinator' THEN 'منسقة'
               END as sender_role
        FROM messages m
        WHERE m.receiver_id = ? AND m.receiver_type = 'training_entity'
        ORDER BY m.created_at DESC
    ";
    $params = [$_SESSION['user_id']];
} else {
    $sql = "
        SELECT m.*,
               CASE 
                   WHEN m.receiver_type = 'student' THEN (SELECT full_name_ar FROM students WHERE user_id = m.receiver_id)
                   WHEN m.receiver_type = 'supervisor' THEN (SELECT full_name_ar FROM supervisors WHERE user_id = m.receiver_id)
                   WHEN m.receiver_type = 'coordinator' THEN (SELECT full_name_ar FROM coordinators WHERE user_id = m.receiver_id)
               END as receiver_name,
               CASE 
                   WHEN m.receiver_type = 'student' THEN 'متدربة'
                   WHEN m.receiver_type = 'supervisor' THEN 'مشرف'
                   WHEN m.receiver_type = 'coordinator' THEN 'منسقة'
               END as receiver_role
        FROM messages m
        WHERE m.sender_id = ? AND m.sender_type = 'training_entity'
        ORDER BY m.created_at DESC
    ";
    $params = [$_SESSION['user_id']];
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$messages = $stmt->fetchAll();

// جلب رسالة محددة للعرض
if($view_id > 0) {
    $stmt = $pdo->prepare("
        SELECT m.*,
               CASE 
                   WHEN m.sender_type = 'student' THEN (SELECT full_name_ar FROM students WHERE user_id = m.sender_id)
                   WHEN m.sender_type = 'supervisor' THEN (SELECT full_name_ar FROM supervisors WHERE user_id = m.sender_id)
                   WHEN m.sender_type = 'coordinator' THEN (SELECT full_name_ar FROM coordinators WHERE user_id = m.sender_id)
                   WHEN m.sender_type = 'training_entity' THEN ?
               END as sender_name,
               CASE 
                   WHEN m.receiver_type = 'student' THEN (SELECT full_name_ar FROM students WHERE user_id = m.receiver_id)
                   WHEN m.receiver_type = 'supervisor' THEN (SELECT full_name_ar FROM supervisors WHERE user_id = m.receiver_id)
                   WHEN m.receiver_type = 'coordinator' THEN (SELECT full_name_ar FROM coordinators WHERE user_id = m.receiver_id)
                   WHEN m.receiver_type = 'training_entity' THEN ?
               END as receiver_name
        FROM messages m
        WHERE m.id = ? AND (m.sender_id = ? OR m.receiver_id = ?)
    ");
    $stmt->execute([$entity['entity_name_ar'], $entity['entity_name_ar'], $view_id, $_SESSION['user_id'], $_SESSION['user_id']]);
    $message = $stmt->fetch();
    
    if(!$message) {
        header('Location: messages.php');
        exit();
    }
    
    // تحديث حالة القراءة إذا كانت واردة
    if($message['receiver_id'] == $_SESSION['user_id'] && !$message['is_read']) {
        $update = $pdo->prepare("UPDATE messages SET is_read = 1, read_at = NOW() WHERE id = ?");
        $update->execute([$view_id]);
    }
    
    // جلب الردود
    $replies = $pdo->prepare("
        SELECT m.*,
               CASE 
                   WHEN m.sender_type = 'student' THEN (SELECT full_name_ar FROM students WHERE user_id = m.sender_id)
                   WHEN m.sender_type = 'supervisor' THEN (SELECT full_name_ar FROM supervisors WHERE user_id = m.sender_id)
                   WHEN m.sender_type = 'coordinator' THEN (SELECT full_name_ar FROM coordinators WHERE user_id = m.sender_id)
                   WHEN m.sender_type = 'training_entity' THEN ?
               END as sender_name
        FROM messages m
        WHERE m.reply_to = ?
        ORDER BY m.created_at ASC
    ");
    $replies->execute([$entity['entity_name_ar'], $view_id]);
    $replies_list = $replies->fetchAll();
}

// جلب بيانات الرسالة للرد
$reply_message = null;
if($reply_to > 0) {
    $stmt = $pdo->prepare("
        SELECT m.*,
               CASE 
                   WHEN m.sender_type = 'student' THEN (SELECT full_name_ar FROM students WHERE user_id = m.sender_id)
                   WHEN m.sender_type = 'supervisor' THEN (SELECT full_name_ar FROM supervisors WHERE user_id = m.sender_id)
                   WHEN m.sender_type = 'coordinator' THEN (SELECT full_name_ar FROM coordinators WHERE user_id = m.sender_id)
               END as sender_name
        FROM messages m
        WHERE m.id = ? AND (m.sender_id = ? OR m.receiver_id = ?)
    ");
    $stmt->execute([$reply_to, $_SESSION['user_id'], $_SESSION['user_id']]);
    $reply_message = $stmt->fetch();
}

// إحصائيات
$stats = [
    'inbox_unread' => $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND receiver_type = 'training_entity' AND is_read = 0")->execute([$_SESSION['user_id']]) ? 
                      $pdo->query("SELECT COUNT(*) FROM messages WHERE receiver_id = {$_SESSION['user_id']} AND receiver_type = 'training_entity' AND is_read = 0")->fetchColumn() : 0,
    'inbox_total' => $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND receiver_type = 'training_entity'")->execute([$_SESSION['user_id']]) ? 
                     $pdo->query("SELECT COUNT(*) FROM messages WHERE receiver_id = {$_SESSION['user_id']} AND receiver_type = 'training_entity'")->fetchColumn() : 0,
    'sent_total' => $pdo->prepare("SELECT COUNT(*) FROM messages WHERE sender_id = ? AND sender_type = 'training_entity'")->execute([$_SESSION['user_id']]) ? 
                    $pdo->query("SELECT COUNT(*) FROM messages WHERE sender_id = {$_SESSION['user_id']} AND sender_type = 'training_entity'")->fetchColumn() : 0,
];

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h4 class="mb-1">الرسائل</h4>
            <p class="text-muted mb-0">تواصل مع المتدربات والمشرفين والمنسقات</p>
        </div>
        <?php if(!$compose && !$view_id): ?>
        <a href="?compose=1" class="btn-primary-custom">
            <i class="fas fa-pen ms-2"></i>
            رسالة جديدة
        </a>
        <?php elseif($view_id): ?>
        <a href="messages.php?folder=<?php echo $folder; ?>" class="btn-outline-custom">
            <i class="fas fa-arrow-right ms-2"></i>
            العودة
        </a>
        <?php else: ?>
        <a href="messages.php" class="btn-outline-custom">
            <i class="fas fa-times ms-2"></i>
            إلغاء
        </a>
        <?php endif; ?>
    </div>
    
    <?php if($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <?php if($compose): ?>
    <!-- ========================================= -->
    <!-- نموذج رسالة جديدة -->
    <!-- ========================================= -->
    <div class="card-custom">
        <h5 class="mb-3"><?php echo $reply_message ? 'رد على رسالة' : 'رسالة جديدة'; ?></h5>
        
        <?php if($reply_message): ?>
        <div class="original-message p-3 bg-light rounded mb-3">
            <div class="d-flex justify-content-between">
                <strong><?php echo htmlspecialchars($reply_message['sender_name']); ?></strong>
                <small class="text-muted"><?php echo date('Y-m-d H:i', strtotime($reply_message['created_at'])); ?></small>
            </div>
            <p class="mb-0 mt-2"><?php echo nl2br(htmlspecialchars($reply_message['message'])); ?></p>
        </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">إلى</label>
                    <select class="form-select" name="receiver_type" id="receiverType" required>
                        <option value="">اختر نوع المستلم</option>
                        <option value="student" <?php echo $reply_message && $reply_message['sender_type'] == 'student' ? 'selected' : ''; ?>>متدربة</option>
                        <option value="supervisor" <?php echo $reply_message && $reply_message['sender_type'] == 'supervisor' ? 'selected' : ''; ?>>مشرف</option>
                        <option value="coordinator" <?php echo $reply_message && $reply_message['sender_type'] == 'coordinator' ? 'selected' : ''; ?>>منسقة</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">اختر المستلم</label>
                    <select class="form-select" name="receiver_id" id="receiverSelect" required>
                        <option value="">اختر أولاً نوع المستلم</option>
                        <?php if($reply_message): ?>
                        <option value="<?php echo $reply_message['sender_id']; ?>" selected>
                            <?php echo htmlspecialchars($reply_message['sender_name']); ?>
                        </option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="col-12 mb-3">
                    <label class="form-label">مرتبط بطلب تدريب (اختياري)</label>
                    <select class="form-select" name="application_id">
                        <option value="">بدون</option>
                        <?php foreach($applications_list as $app): ?>
                        <option value="<?php echo $app['id']; ?>">
                            <?php echo htmlspecialchars($app['full_name_ar']); ?> - <?php echo htmlspecialchars($app['title_ar']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-12 mb-3">
                    <label class="form-label">الموضوع</label>
                    <input type="text" class="form-control" name="subject" value="<?php echo $reply_message ? 'رد: ' . $reply_message['subject'] : ''; ?>" required>
                </div>
                
                <div class="col-12 mb-3">
                    <label class="form-label">الرسالة</label>
                    <textarea class="form-control" name="message" rows="8" required><?php echo $reply_message ? "\n\n----- الرسالة الأصلية -----\n" . $reply_message['message'] : ''; ?></textarea>
                </div>
                
                <div class="col-12">
                    <button type="submit" name="send_message" class="btn-primary-custom">
                        <i class="fas fa-paper-plane ms-2"></i>
                        إرسال
                    </button>
                    <a href="messages.php" class="btn btn-secondary ms-2">إلغاء</a>
                </div>
            </div>
        </form>
    </div>
    
    <?php elseif($view_id && isset($message)): ?>
    <!-- ========================================= -->
    <!-- عرض رسالة محددة -->
    <!-- ========================================= -->
    <div class="row">
        <div class="col-md-8">
            <div class="card-custom">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h4><?php echo htmlspecialchars($message['subject']); ?></h4>
                        <div class="text-muted">
                            <i class="fas fa-user ms-1"></i> 
                            <?php if($message['sender_id'] == $_SESSION['user_id']): ?>
                                أنت → <?php echo htmlspecialchars($message['receiver_name']); ?> (<?php echo $message['receiver_role']; ?>)
                            <?php else: ?>
                                <?php echo htmlspecialchars($message['sender_name']); ?> (<?php echo $message['sender_role']; ?>) → أنت
                            <?php endif; ?>
                        </div>
                        <small class="text-muted">
                            <i class="fas fa-clock ms-1"></i>
                            <?php echo date('Y-m-d H:i', strtotime($message['created_at'])); ?>
                        </small>
                    </div>
                    
                    <?php if($message['application_id']): ?>
                    <a href="applications.php?view=<?php echo $message['application_id']; ?>" class="btn btn-sm btn-outline-info">
                        <i class="fas fa-external-link-alt ms-1"></i>
                        عرض الطلب
                    </a>
                    <?php endif; ?>
                </div>
                
                <hr>
                
                <div class="message-body p-3">
                    <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                </div>
            </div>
            
            <!-- الردود -->
            <?php if(!empty($replies_list)): ?>
            <div class="card-custom mt-3">
                <h5 class="mb-3">الردود</h5>
                <?php foreach($replies_list as $reply): ?>
                <div class="reply-card p-3 bg-light rounded mb-2">
                    <div class="d-flex justify-content-between">
                        <strong><?php echo htmlspecialchars($reply['sender_name']); ?></strong>
                        <small class="text-muted"><?php echo date('Y-m-d H:i', strtotime($reply['created_at'])); ?></small>
                    </div>
                    <p class="mb-0 mt-2"><?php echo nl2br(htmlspecialchars($reply['message'])); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="col-md-4">
            <!-- معلومات المرسل -->
            <div class="card-custom">
                <h5 class="mb-3">معلومات المرسل</h5>
                <?php if($message['sender_id'] != $_SESSION['user_id']): ?>
                <p><strong>الاسم:</strong> <?php echo htmlspecialchars($message['sender_name']); ?></p>
                <p><strong>النوع:</strong> <?php echo $message['sender_role']; ?></p>
                <?php endif; ?>
            </div>
            
            <!-- إجراءات -->
            <div class="card-custom mt-3">
                <h5 class="mb-3">إجراءات</h5>
                <div class="d-grid gap-2">
                    <?php if($message['sender_id'] != $_SESSION['user_id']): ?>
                    <a href="?reply=<?php echo $view_id; ?>" class="btn-primary-custom">
                        <i class="fas fa-reply ms-2"></i>
                        رد على الرسالة
                    </a>
                    <?php endif; ?>
                    <a href="?delete=<?php echo $view_id; ?>&folder=<?php echo $folder; ?>" class="btn btn-danger" onclick="return confirm('هل أنت متأكد من حذف هذه الرسالة؟')">
                        <i class="fas fa-trash ms-2"></i>
                        حذف
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <!-- ========================================= -->
    <!-- قائمة الرسائل -->
    <!-- ========================================= -->
    
    <!-- تبويبات المجلدات -->
    <div class="folder-tabs mb-4">
        <a href="?folder=inbox" class="btn <?php echo $folder == 'inbox' ? 'btn-primary' : 'btn-outline-primary'; ?> me-2">
            <i class="fas fa-inbox ms-1"></i>
            الوارد
            <?php if($stats['inbox_unread'] > 0): ?>
            <span class="badge bg-danger ms-1"><?php echo $stats['inbox_unread']; ?></span>
            <?php endif; ?>
        </a>
        <a href="?folder=sent" class="btn <?php echo $folder == 'sent' ? 'btn-success' : 'btn-outline-success'; ?>">
            <i class="fas fa-paper-plane ms-1"></i>
            المرسل
        </a>
    </div>
    
    <!-- إحصائيات سريعة -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['inbox_total']; ?></div>
                <div class="stat-label">إجمالي الوارد</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['inbox_unread']; ?></div>
                <div class="stat-label">غير مقروء</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['sent_total']; ?></div>
                <div class="stat-label">المرسل</div>
            </div>
        </div>
    </div>
    
    <!-- قائمة الرسائل -->
    <div class="table-custom">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th><?php echo $folder == 'inbox' ? 'من' : 'إلى'; ?></th>
                    <th>الموضوع</th>
                    <th>التاريخ</th>
                    <th>الحالة</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($messages)): ?>
                <tr>
                    <td colspan="6" class="text-center py-5">
                        <i class="fas fa-envelope-open-text fa-3x text-muted mb-3"></i>
                        <h5>لا توجد رسائل</h5>
                        <p class="text-muted">لم يتم العثور على أي رسائل في هذا المجلد</p>
                        <a href="?compose=1" class="btn-primary-custom mt-2">
                            <i class="fas fa-pen ms-2"></i>
                            إرسال رسالة جديدة
                        </a>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach($messages as $index => $msg): ?>
                    <tr class="<?php echo ($folder == 'inbox' && !$msg['is_read']) ? 'table-light' : ''; ?>">
                        <td><?php echo $index + 1; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($folder == 'inbox' ? $msg['sender_name'] : $msg['receiver_name']); ?></strong>
                            <br>
                            <small class="text-muted"><?php echo $folder == 'inbox' ? $msg['sender_role'] : $msg['receiver_role']; ?></small>
                        </td>
                        <td>
                            <?php if($folder == 'inbox' && !$msg['is_read']): ?>
                            <strong><?php echo htmlspecialchars($msg['subject']); ?></strong>
                            <?php else: ?>
                            <?php echo htmlspecialchars($msg['subject']); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $time = strtotime($msg['created_at']);
                            if(date('Y-m-d') == date('Y-m-d', $time)) {
                                echo 'اليوم ' . date('H:i', $time);
                            } elseif(date('Y-m-d', strtotime('-1 day')) == date('Y-m-d', $time)) {
                                echo 'أمس ' . date('H:i', $time);
                            } else {
                                echo date('Y-m-d H:i', $time);
                            }
                            ?>
                        </td>
                        <td>
                            <?php if($folder == 'inbox'): ?>
                                <?php if($msg['is_read']): ?>
                                <span class="badge bg-secondary">مقروءة</span>
                                <?php else: ?>
                                <span class="badge bg-primary">جديدة</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <a href="?view=<?php echo $msg['id']; ?>" class="btn btn-sm btn-outline-primary" title="عرض">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if($folder == 'inbox'): ?>
                                <a href="?reply=<?php echo $msg['id']; ?>" class="btn btn-sm btn-outline-info" title="رد">
                                    <i class="fas fa-reply"></i>
                                </a>
                                <?php endif; ?>
                                <a href="?delete=<?php echo $msg['id']; ?>&folder=<?php echo $folder; ?>" class="btn btn-sm btn-outline-danger" title="حذف" 
                                   onclick="return confirm('هل أنت متأكد من حذف هذه الرسالة؟')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- JavaScript لتحميل قائمة المستلمين -->
<script>
    const students = <?php echo json_encode($students_list); ?>;
    const supervisors = <?php echo json_encode($supervisors_list); ?>;
    const coordinators = <?php echo json_encode($coordinators_list); ?>;
    
    document.getElementById('receiverType')?.addEventListener('change', function() {
        const type = this.value;
        const select = document.getElementById('receiverSelect');
        select.innerHTML = '<option value="">اختر المستلم</option>';
        
        let list = [];
        if(type === 'student') list = students;
        else if(type === 'supervisor') list = supervisors;
        else if(type === 'coordinator') list = coordinators;
        
        list.forEach(item => {
            const option = document.createElement('option');
            option.value = item.user_id;
            option.textContent = item.full_name_ar + (item.role ? ' (' + item.role + ')' : '');
            select.appendChild(option);
        });
    });
</script>

<?php
require_once '../../includes/entity_footer.php';
?>