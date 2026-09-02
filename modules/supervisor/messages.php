 <?php
// modules/supervisor/messages.php - نظام الرسائل للمشرف
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
// تحديد الصفحة الحالية
// =============================================
$action = $_GET['action'] ?? 'list';
$folder = $_GET['folder'] ?? 'inbox';
$view_id = $_GET['view'] ?? 0;
$compose = isset($_GET['compose']) ? true : false;
$reply_to = $_GET['reply'] ?? 0;

// =============================================
// معالجة إرسال رسالة جديدة
// =============================================
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    
    $receiver_type = $_POST['receiver_type'];
    $receiver_id = $_POST['receiver_id'];
    $subject = $_POST['subject'];
    $message = $_POST['message'];
    $application_id = $_POST['application_id'] ?? null;
    
    $insert = $pdo->prepare("
        INSERT INTO messages 
        (sender_id, sender_type, receiver_id, receiver_type, subject, message, application_id, created_at)
        VALUES (?, 'supervisor', ?, ?, ?, ?, ?, NOW())
    ");
    $insert->execute([$_SESSION['user_id'], $receiver_id, $receiver_type, $subject, $message, $application_id]);
    
    $_SESSION['success'] = 'تم إرسال الرسالة بنجاح';
    header('Location: messages.php?folder=sent');
    exit();
}

// =============================================
// معالجة حذف رسالة
// =============================================
if(isset($_GET['delete']) && $_GET['delete'] > 0) {
    $message_id = $_GET['delete'];
    
    $delete = $pdo->prepare("DELETE FROM messages WHERE id = ? AND (sender_id = ? OR receiver_id = ?)");
    $delete->execute([$message_id, $_SESSION['user_id'], $_SESSION['user_id']]);
    
    $_SESSION['success'] = 'تم حذف الرسالة';
    header('Location: messages.php?folder=' . $folder);
    exit();
}

// =============================================
// معالجة تحديث حالة القراءة
// =============================================
if(isset($_GET['mark_read']) && $_GET['mark_read'] > 0) {
    $update = $pdo->prepare("UPDATE messages SET is_read = 1, read_at = NOW() WHERE id = ? AND receiver_id = ?");
    $update->execute([$_GET['mark_read'], $_SESSION['user_id']]);
    
    header('Location: messages.php?view=' . $_GET['mark_read']);
    exit();
}

// =============================================
// جلب قائمة المستلمين المحتملين
// =============================================

// المتدربات المشرف عليها
$students = $pdo->prepare("
    SELECT DISTINCT 
        u.id as user_id,
        s.full_name_ar,
        'متدربة' as role,
        ts.title_ar as training
    FROM students s
    JOIN users u ON s.user_id = u.id
    JOIN applications a ON s.id = a.student_id
    JOIN training_seats ts ON a.seat_id = ts.id
    WHERE a.supervisor_id = ? AND a.status IN ('approved', 'accepted_by_entity')
    ORDER BY s.full_name_ar
");
$students->execute([$supervisor['id']]);
$students_list = $students->fetchAll();

// المنسقة
$coordinators = $pdo->prepare("
    SELECT 
        u.id as user_id,
        c.full_name_ar,
        'منسقة' as role
    FROM coordinators c
    JOIN users u ON c.user_id = u.id
");
$coordinators->execute();
$coordinators_list = $coordinators->fetchAll();

// الجهات (المرتبطة بطالبات المشرف)
$entities = $pdo->prepare("
    SELECT DISTINCT
        u.id as user_id,
        te.entity_name_ar as full_name_ar,
        'جهة' as role
    FROM training_entities te
    JOIN users u ON te.user_id = u.id
    JOIN training_seats ts ON te.id = ts.entity_id
    JOIN applications a ON ts.id = a.seat_id
    WHERE a.supervisor_id = ? AND a.status IN ('approved', 'accepted_by_entity')
    ORDER BY te.entity_name_ar
");
$entities->execute([$supervisor['id']]);
$entities_list = $entities->fetchAll();

// =============================================
// جلب طلبات المتدربات (لربط الرسالة)
// =============================================
$applications = $pdo->prepare("
    SELECT 
        a.id,
        s.full_name_ar,
        ts.title_ar
    FROM applications a
    JOIN students s ON a.student_id = s.id
    JOIN training_seats ts ON a.seat_id = ts.id
    WHERE a.supervisor_id = ? AND a.status IN ('approved', 'accepted_by_entity')
    ORDER BY s.full_name_ar
");
$applications->execute([$supervisor['id']]);
$applications_list = $applications->fetchAll();

// =============================================
// جلب الرسائل حسب المجلد
// =============================================
if($folder == 'inbox') {
    $sql = "
        SELECT m.*,
               CASE 
                   WHEN m.sender_type = 'student' THEN (SELECT full_name_ar FROM students WHERE user_id = m.sender_id)
                   WHEN m.sender_type = 'coordinator' THEN (SELECT full_name_ar FROM coordinators WHERE user_id = m.sender_id)
                   WHEN m.sender_type = 'training_entity' THEN (SELECT entity_name_ar FROM training_entities WHERE user_id = m.sender_id)
               END as sender_name,
               CASE 
                   WHEN m.sender_type = 'student' THEN 'متدربة'
                   WHEN m.sender_type = 'coordinator' THEN 'منسقة'
                   WHEN m.sender_type = 'training_entity' THEN 'جهة'
               END as sender_role
        FROM messages m
        WHERE m.receiver_id = ? AND m.receiver_type = 'supervisor'
        ORDER BY m.created_at DESC
    ";
    $params = [$_SESSION['user_id']];
} else {
    $sql = "
        SELECT m.*,
               CASE 
                   WHEN m.receiver_type = 'student' THEN (SELECT full_name_ar FROM students WHERE user_id = m.receiver_id)
                   WHEN m.receiver_type = 'coordinator' THEN (SELECT full_name_ar FROM coordinators WHERE user_id = m.receiver_id)
                   WHEN m.receiver_type = 'training_entity' THEN (SELECT entity_name_ar FROM training_entities WHERE user_id = m.receiver_id)
               END as receiver_name,
               CASE 
                   WHEN m.receiver_type = 'student' THEN 'متدربة'
                   WHEN m.receiver_type = 'coordinator' THEN 'منسقة'
                   WHEN m.receiver_type = 'training_entity' THEN 'جهة'
               END as receiver_role
        FROM messages m
        WHERE m.sender_id = ? AND m.sender_type = 'supervisor'
        ORDER BY m.created_at DESC
    ";
    $params = [$_SESSION['user_id']];
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$messages = $stmt->fetchAll();

// =============================================
// جلب رسالة محددة للعرض (✅ مع جميع الحقول)
// =============================================
$message = null;
if($view_id > 0) {
    $stmt = $pdo->prepare("
        SELECT m.*,
               CASE 
                   WHEN m.sender_type = 'student' THEN (SELECT full_name_ar FROM students WHERE user_id = m.sender_id)
                   WHEN m.sender_type = 'coordinator' THEN (SELECT full_name_ar FROM coordinators WHERE user_id = m.sender_id)
                   WHEN m.sender_type = 'training_entity' THEN (SELECT entity_name_ar FROM training_entities WHERE user_id = m.sender_id)
                   WHEN m.sender_type = 'supervisor' THEN ?
               END as sender_name,
               CASE 
                   WHEN m.sender_type = 'student' THEN 'متدربة'
                   WHEN m.sender_type = 'coordinator' THEN 'منسقة'
                   WHEN m.sender_type = 'training_entity' THEN 'جهة'
                   WHEN m.sender_type = 'supervisor' THEN 'مشرف'
               END as sender_role,
               CASE 
                   WHEN m.receiver_type = 'student' THEN (SELECT full_name_ar FROM students WHERE user_id = m.receiver_id)
                   WHEN m.receiver_type = 'coordinator' THEN (SELECT full_name_ar FROM coordinators WHERE user_id = m.receiver_id)
                   WHEN m.receiver_type = 'training_entity' THEN (SELECT entity_name_ar FROM training_entities WHERE user_id = m.receiver_id)
                   WHEN m.receiver_type = 'supervisor' THEN ?
               END as receiver_name,
               CASE 
                   WHEN m.receiver_type = 'student' THEN 'متدربة'
                   WHEN m.receiver_type = 'coordinator' THEN 'منسقة'
                   WHEN m.receiver_type = 'training_entity' THEN 'جهة'
                   WHEN m.receiver_type = 'supervisor' THEN 'مشرف'
               END as receiver_role
        FROM messages m
        WHERE m.id = ? AND (m.sender_id = ? OR m.receiver_id = ?)
    ");
    $stmt->execute([$supervisor['full_name_ar'], $supervisor['full_name_ar'], $view_id, $_SESSION['user_id'], $_SESSION['user_id']]);
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
}

// =============================================
// جلب بيانات الرسالة للرد
// =============================================
$reply_message = null;
if($reply_to > 0) {
    $stmt = $pdo->prepare("
        SELECT m.*,
               CASE 
                   WHEN m.sender_type = 'student' THEN (SELECT full_name_ar FROM students WHERE user_id = m.sender_id)
                   WHEN m.sender_type = 'coordinator' THEN (SELECT full_name_ar FROM coordinators WHERE user_id = m.sender_id)
                   WHEN m.sender_type = 'training_entity' THEN (SELECT entity_name_ar FROM training_entities WHERE user_id = m.sender_id)
               END as sender_name,
               m.sender_id,
               m.sender_type
        FROM messages m
        WHERE m.id = ? AND (m.sender_id = ? OR m.receiver_id = ?)
    ");
    $stmt->execute([$reply_to, $_SESSION['user_id'], $_SESSION['user_id']]);
    $reply_message = $stmt->fetch();
}

// =============================================
// إحصائيات
// =============================================
$unread_stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND receiver_type = 'supervisor' AND is_read = 0");
$unread_stmt->execute([$_SESSION['user_id']]);
$inbox_unread = $unread_stmt->fetchColumn();

$inbox_stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND receiver_type = 'supervisor'");
$inbox_stmt->execute([$_SESSION['user_id']]);
$inbox_total = $inbox_stmt->fetchColumn();

$sent_stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE sender_id = ? AND sender_type = 'supervisor'");
$sent_stmt->execute([$_SESSION['user_id']]);
$sent_total = $sent_stmt->fetchColumn();

$stats = [
    'inbox_unread' => $inbox_unread,
    'inbox_total' => $inbox_total,
    'sent_total' => $sent_total,
];

$page_title = 'الرسائل';
require_once '../../includes/supervisor_header.php';

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h4 class="mb-1">الرسائل</h4>
            <p class="text-muted">تواصل مع المتدربات والمنسقات والجهات</p>
        </div>
        <?php if(!$compose && !$view_id): ?>
        <button class="btn-primary-custom" onclick="showCompose()">
            <i class="fas fa-pen ms-2"></i> رسالة جديدة
        </button>
        <?php elseif($view_id): ?>
        <a href="messages.php?folder=<?php echo $folder; ?>" class="btn-outline-custom">
            <i class="fas fa-arrow-right ms-2"></i> العودة
        </a>
        <?php else: ?>
        <a href="messages.php" class="btn-outline-custom">
            <i class="fas fa-times ms-2"></i> إلغاء
        </a>
        <?php endif; ?>
    </div>
    
    <?php if($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <!-- ========================================= -->
    <!-- نموذج رسالة جديدة -->
    <!-- ========================================= -->
    <?php if($compose): ?>
    <div class="card-custom mb-4" id="composeForm">
        <h5 class="mb-3"><?php echo $reply_message ? 'رد على رسالة' : 'رسالة جديدة'; ?></h5>
        
        <?php if($reply_message): ?>
        <div class="original-message p-3 bg-light rounded mb-3">
            <div class="d-flex justify-content-between">
                <strong><?php echo htmlspecialchars($reply_message['sender_name'] ?? ''); ?></strong>
                <small class="text-muted"><?php echo isset($reply_message['created_at']) ? date('Y-m-d H:i', strtotime($reply_message['created_at'])) : ''; ?></small>
            </div>
            <p class="mb-0 mt-2"><?php echo isset($reply_message['message']) ? nl2br(htmlspecialchars($reply_message['message'])) : ''; ?></p>
        </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">إلى</label>
                    <select class="form-select" name="receiver_type" id="receiverType" required>
                        <option value="">اختر نوع المستلم</option>
                        <option value="student" <?php echo ($reply_message && $reply_message['sender_type'] == 'student') ? 'selected' : ''; ?>>متدربة</option>
                        <option value="coordinator" <?php echo ($reply_message && $reply_message['sender_type'] == 'coordinator') ? 'selected' : ''; ?>>منسقة</option>
                        <option value="training_entity" <?php echo ($reply_message && $reply_message['sender_type'] == 'training_entity') ? 'selected' : ''; ?>>جهة تدريبية</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">اختر المستلم</label>
                    <select class="form-select" name="receiver_id" id="receiverSelect" required>
                        <option value="">اختر أولاً نوع المستلم</option>
                        <?php if($reply_message): ?>
                        <option value="<?php echo $reply_message['sender_id']; ?>" selected>
                            <?php echo htmlspecialchars($reply_message['sender_name'] ?? ''); ?>
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
                    <input type="text" class="form-control" name="subject" 
                           value="<?php echo $reply_message ? 'رد: ' . ($reply_message['subject'] ?? '') : ''; ?>" required>
                </div>
                
                <div class="col-12 mb-3">
                    <label class="form-label">الرسالة</label>
                    <textarea class="form-control" name="message" rows="8" required><?php 
                        echo $reply_message ? "\n\n----- الرسالة الأصلية -----\n" . ($reply_message['message'] ?? '') : ''; 
                    ?></textarea>
                </div>
                
                <div class="col-12">
                    <button type="submit" name="send_message" class="btn-primary-custom">
                        <i class="fas fa-paper-plane ms-2"></i> إرسال
                    </button>
                    <a href="messages.php" class="btn btn-secondary ms-2">إلغاء</a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- ========================================= -->
    <!-- عرض رسالة محددة (✅ مع حل مشكلة undefined) -->
    <!-- ========================================= -->
    <?php elseif($view_id && $message): ?>
    <div class="row">
        <div class="col-md-8">
            <div class="card-custom">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h4><?php echo htmlspecialchars($message['subject'] ?? ''); ?></h4>
                        <div class="text-muted">
                            <i class="fas fa-user ms-1"></i> 
                            <?php if($message['sender_id'] == $_SESSION['user_id']): ?>
                                أنت → 
                                <?php 
                                $receiver_name = $message['receiver_name'] ?? 'مستلم';
                                $receiver_role = $message['receiver_role'] ?? '';
                                echo htmlspecialchars($receiver_name) . ($receiver_role ? ' (' . htmlspecialchars($receiver_role) . ')' : '');
                                ?>
                            <?php else: ?>
                                <?php 
                                $sender_name = $message['sender_name'] ?? 'مرسل';
                                $sender_role = $message['sender_role'] ?? '';
                                echo htmlspecialchars($sender_name) . ($sender_role ? ' (' . htmlspecialchars($sender_role) . ')' : '') . ' → أنت';
                                ?>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted">
                            <i class="fas fa-clock ms-1"></i>
                            <?php echo isset($message['created_at']) ? date('Y-m-d H:i', strtotime($message['created_at'])) : ''; ?>
                        </small>
                    </div>
                    
                    <?php if(!empty($message['application_id'])): ?>
                    <a href="students.php?action=view&app_id=<?php echo $message['application_id']; ?>" class="btn btn-sm btn-outline-info">
                        <i class="fas fa-external-link-alt ms-1"></i> عرض الطلب
                    </a>
                    <?php endif; ?>
                </div>
                
                <hr>
                
                <div class="message-body p-3">
                    <?php echo isset($message['message']) ? nl2br(htmlspecialchars($message['message'])) : ''; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card-custom">
                <h6 class="mb-3">إجراءات</h6>
                <?php if($message['sender_id'] != $_SESSION['user_id']): ?>
                <a href="?compose=1&reply=<?php echo $view_id; ?>" class="btn-primary-custom w-100 mb-2 text-center">
                    <i class="fas fa-reply ms-2"></i> رد على الرسالة
                </a>
                <?php endif; ?>
                <a href="?delete=<?php echo $view_id; ?>&folder=<?php echo $folder; ?>" class="btn btn-danger w-100"
                   onclick="return confirm('هل أنت متأكد من حذف هذه الرسالة؟')">
                    <i class="fas fa-trash ms-2"></i> حذف
                </a>
            </div>
        </div>
    </div>
    
    <!-- ========================================= -->
    <!-- قائمة الرسائل -->
    <!-- ========================================= -->
    <?php else: ?>
    
    <!-- تبويبات المجلدات -->
    <div class="folder-tabs mb-4">
        <a href="?folder=inbox" class="btn <?php echo $folder == 'inbox' ? 'btn-primary-custom' : 'btn-outline-custom'; ?> me-2">
            <i class="fas fa-inbox ms-2"></i> الوارد
            <?php if($stats['inbox_unread'] > 0): ?>
            <span class="badge bg-danger ms-1"><?php echo $stats['inbox_unread']; ?></span>
            <?php endif; ?>
        </a>
        <a href="?folder=sent" class="btn <?php echo $folder == 'sent' ? 'btn-primary-custom' : 'btn-outline-custom'; ?>">
            <i class="fas fa-paper-plane ms-2"></i> المرسل
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
    <?php if(empty($messages)): ?>
        <div class="card-custom text-center py-5">
            <i class="fas fa-envelope-open-text fa-4x text-muted mb-3"></i>
            <h5>لا توجد رسائل</h5>
            <p class="text-muted">لم يتم العثور على أي رسائل في هذا المجلد</p>
            <button class="btn-primary-custom" onclick="showCompose()">
                <i class="fas fa-pen ms-2"></i> إرسال رسالة جديدة
            </button>
        </div>
    <?php else: ?>
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
                    <?php foreach($messages as $i => $msg): ?>
                    <tr class="<?php echo ($folder == 'inbox' && empty($msg['is_read'])) ? 'table-light' : ''; ?>">
                        <td><?php echo $i+1; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($folder == 'inbox' ? ($msg['sender_name'] ?? '') : ($msg['receiver_name'] ?? '')); ?></strong>
                            <br><small class="text-muted"><?php echo htmlspecialchars($folder == 'inbox' ? ($msg['sender_role'] ?? '') : ($msg['receiver_role'] ?? '')); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($msg['subject'] ?? ''); ?></td>
                        <td>
                            <?php 
                            if(isset($msg['created_at'])) {
                                $time = strtotime($msg['created_at']);
                                if(date('Y-m-d') == date('Y-m-d', $time)) {
                                    echo 'اليوم ' . date('H:i', $time);
                                } elseif(date('Y-m-d', strtotime('-1 day')) == date('Y-m-d', $time)) {
                                    echo 'أمس ' . date('H:i', $time);
                                } else {
                                    echo date('Y-m-d H:i', $time);
                                }
                            }
                            ?>
                        </td>
                        <td>
                            <?php if($folder == 'inbox' && empty($msg['is_read'])): ?>
                            <span class="badge bg-primary">جديدة</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="?view=<?php echo $msg['id']; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye"></i>
                            </a>
                            <?php if($folder == 'inbox'): ?>
                            <a href="?compose=1&reply=<?php echo $msg['id']; ?>" class="btn btn-sm btn-outline-info">
                                <i class="fas fa-reply"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.stat-card {
    background: white;
    border-radius: 10px;
    padding: 15px;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0,102,51,0.05);
}
.stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary);
    line-height: 1;
}
.stat-label {
    color: #6c757d;
    font-size: 0.9rem;
    margin-top: 5px;
}
.folder-tabs .btn {
    border-radius: 50px;
    padding: 8px 20px;
}
.table td {
    vertical-align: middle;
}
.table-light {
    background-color: #f0f7f0;
}
</style>

<!-- JavaScript -->
<script>
const students = <?php echo json_encode($students_list); ?>;
const coordinators = <?php echo json_encode($coordinators_list); ?>;
const entities = <?php echo json_encode($entities_list); ?>;

document.getElementById('receiverType')?.addEventListener('change', function() {
    const type = this.value;
    const select = document.getElementById('receiverSelect');
    select.innerHTML = '<option value="">اختر المستلم</option>';
    
    let list = [];
    if(type === 'student') list = students;
    else if(type === 'coordinator') list = coordinators;
    else if(type === 'training_entity') list = entities;
    
    list.forEach(item => {
        const option = document.createElement('option');
        option.value = item.user_id;
        option.textContent = item.full_name_ar + ' (' + item.role + ')' + (item.training ? ' - ' + item.training : '');
        select.appendChild(option);
    });
});

function showCompose() {
    window.location.href = '?compose=1';
}

function hideCompose() {
    window.location.href = 'messages.php';
}
</script>

<?php require_once '../../includes/supervisor_footer.php'; ?>