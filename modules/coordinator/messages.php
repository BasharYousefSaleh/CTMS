<?php
// modules/coordinator/messages.php - نظام الرسائل الداخلي
session_start();
require_once '../../config/config.php';

// =============================================
// التحقق من تسجيل الدخول
// =============================================
if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'coordinator') {
    header('Location: ../auth/login.php?type=coordinator');
    exit();
}

// جلب بيانات المنسقة
$stmt = $pdo->prepare("SELECT * FROM coordinators WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$coordinator = $stmt->fetch();

if(!$coordinator) {
    header('Location: ../auth/logout.php');
    exit();
}

// =============================================
// معالجة إرسال رسالة
// =============================================
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    
    $receiver_type = $_POST['receiver_type'];
    $receiver_id = $_POST['receiver_id'];
    $subject = $_POST['subject'];
    $message = $_POST['message'];
    
    $insert = $pdo->prepare("
        INSERT INTO messages (sender_id, sender_type, receiver_id, receiver_type, subject, message, created_at)
        VALUES (?, 'coordinator', ?, ?, ?, ?, NOW())
    ");
    $insert->execute([$_SESSION['user_id'], $receiver_id, $receiver_type, $subject, $message]);
    
    $_SESSION['success'] = 'تم إرسال الرسالة';
    header('Location: messages.php?folder=sent');
    exit();
}

// =============================================
// معالجة حذف رسالة
// =============================================
if(isset($_GET['delete']) && $_GET['delete'] > 0) {
    $delete = $pdo->prepare("DELETE FROM messages WHERE id = ? AND (sender_id = ? OR receiver_id = ?)");
    $delete->execute([$_GET['delete'], $_SESSION['user_id'], $_SESSION['user_id']]);
    
    $_SESSION['success'] = 'تم حذف الرسالة';
    header('Location: messages.php?folder=' . ($_GET['folder'] ?? 'inbox'));
    exit();
}

// =============================================
// تحديد المجلد
// =============================================
$folder = $_GET['folder'] ?? 'inbox';
$view_id = $_GET['view'] ?? 0;

// =============================================
// جلب قائمة المستلمين
// =============================================
$students = $pdo->query("SELECT id as user_id, full_name_ar, 'متدربة' as role FROM students ORDER BY full_name_ar")->fetchAll();
$supervisors = $pdo->query("SELECT id as user_id, full_name_ar, 'مشرف' as role FROM supervisors ORDER BY full_name_ar")->fetchAll();
$entities = $pdo->query("SELECT id as user_id, entity_name_ar as full_name_ar, 'جهة' as role FROM training_entities ORDER BY entity_name_ar")->fetchAll();

// =============================================
// جلب الرسائل
// =============================================
if($folder == 'inbox') {
    $sql = "
        SELECT m.*,
               CASE 
                   WHEN m.sender_type = 'student' THEN (SELECT full_name_ar FROM students WHERE user_id = m.sender_id)
                   WHEN m.sender_type = 'supervisor' THEN (SELECT full_name_ar FROM supervisors WHERE user_id = m.sender_id)
                   WHEN m.sender_type = 'training_entity' THEN (SELECT entity_name_ar FROM training_entities WHERE user_id = m.sender_id)
               END as sender_name
        FROM messages m
        WHERE m.receiver_id = ? AND m.receiver_type = 'coordinator'
        ORDER BY m.created_at DESC
    ";
    $params = [$_SESSION['user_id']];
} else {
    $sql = "
        SELECT m.*,
               CASE 
                   WHEN m.receiver_type = 'student' THEN (SELECT full_name_ar FROM students WHERE user_id = m.receiver_id)
                   WHEN m.receiver_type = 'supervisor' THEN (SELECT full_name_ar FROM supervisors WHERE user_id = m.receiver_id)
                   WHEN m.receiver_type = 'training_entity' THEN (SELECT entity_name_ar FROM training_entities WHERE user_id = m.receiver_id)
               END as receiver_name
        FROM messages m
        WHERE m.sender_id = ? AND m.sender_type = 'coordinator'
        ORDER BY m.created_at DESC
    ";
    $params = [$_SESSION['user_id']];
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$messages = $stmt->fetchAll();

// =============================================
// جلب رسالة محددة للعرض
// =============================================
$message = null;
if($view_id > 0) {
    $stmt = $pdo->prepare("
        SELECT m.*,
               CASE 
                   WHEN m.sender_type = 'student' THEN (SELECT full_name_ar FROM students WHERE user_id = m.sender_id)
                   WHEN m.sender_type = 'supervisor' THEN (SELECT full_name_ar FROM supervisors WHERE user_id = m.sender_id)
                   WHEN m.sender_type = 'training_entity' THEN (SELECT entity_name_ar FROM training_entities WHERE user_id = m.sender_id)
               END as sender_name,
               CASE 
                   WHEN m.receiver_type = 'student' THEN (SELECT full_name_ar FROM students WHERE user_id = m.receiver_id)
                   WHEN m.receiver_type = 'supervisor' THEN (SELECT full_name_ar FROM supervisors WHERE user_id = m.receiver_id)
                   WHEN m.receiver_type = 'training_entity' THEN (SELECT entity_name_ar FROM training_entities WHERE user_id = m.receiver_id)
               END as receiver_name
        FROM messages m
        WHERE m.id = ? AND (m.sender_id = ? OR m.receiver_id = ?)
    ");
    $stmt->execute([$view_id, $_SESSION['user_id'], $_SESSION['user_id']]);
    $message = $stmt->fetch();
    
    if($message && $message['receiver_id'] == $_SESSION['user_id'] && !$message['is_read']) {
        $update = $pdo->prepare("UPDATE messages SET is_read = 1, read_at = NOW() WHERE id = ?");
        $update->execute([$view_id]);
    }
}

// =============================================
// إحصائيات
// =============================================
$unread = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND receiver_type = 'coordinator' AND is_read = 0");
$unread->execute([$_SESSION['user_id']]);
$unread_count = $unread->fetchColumn();

$page_title = 'الرسائل';
require_once '../../includes/coordinator_header.php';

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>

<div class="content">
    <div class="page-header">
        <h4 class="mb-1">الرسائل</h4>
        <p class="text-muted">التواصل مع المتدربات والمشرفين والجهات</p>
        <?php if(!$view_id): ?>
        <button class="btn-primary-custom" onclick="showCompose()">
            <i class="fas fa-pen ms-2"></i> رسالة جديدة
        </button>
        <?php else: ?>
        <a href="messages.php?folder=<?php echo $folder; ?>" class="btn-outline-custom">
            <i class="fas fa-arrow-right ms-2"></i> العودة
        </a>
        <?php endif; ?>
    </div>
    
    <?php if($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
    <?php if($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
    
    <!-- ========================================= -->
    <!-- نموذج رسالة جديدة -->
    <!-- ========================================= -->
    <div class="card-custom mb-4" id="composeForm" style="display: none;">
        <h5 class="mb-3">رسالة جديدة</h5>
        
        <form method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <select class="form-select" name="receiver_type" id="receiverType" required>
                        <option value="">نوع المستلم</option>
                        <option value="student">متدربة</option>
                        <option value="supervisor">مشرف</option>
                        <option value="training_entity">جهة تدريبية</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <select class="form-select" name="receiver_id" id="receiverSelect" required>
                        <option value="">اختر المستلم</option>
                    </select>
                </div>
                <div class="col-12 mb-3">
                    <input type="text" class="form-control" name="subject" placeholder="الموضوع" required>
                </div>
                <div class="col-12 mb-3">
                    <textarea class="form-control" name="message" rows="5" placeholder="الرسالة..." required></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" name="send_message" class="btn-primary-custom">
                        <i class="fas fa-paper-plane ms-2"></i> إرسال
                    </button>
                    <button type="button" class="btn btn-secondary ms-2" onclick="hideCompose()">إلغاء</button>
                </div>
            </div>
        </form>
    </div>
    
    <?php if($view_id && $message): ?>
        <!-- ========================================= -->
        <!-- عرض رسالة محددة -->
        <!-- ========================================= -->
        <div class="row">
            <div class="col-md-8">
                <div class="card-custom">
                    <h4 class="mb-3"><?php echo $message['subject']; ?></h4>
                    
                    <div class="d-flex justify-content-between mb-3">
                        <div>
                            <strong>من:</strong> 
                            <?php if($message['sender_id'] == $_SESSION['user_id']): ?>
                                أنا
                            <?php else: ?>
                                <?php echo $message['sender_name']; ?> (<?php echo $message['sender_type']; ?>)
                            <?php endif; ?>
                        </div>
                        <div>
                            <strong>إلى:</strong>
                            <?php if($message['receiver_id'] == $_SESSION['user_id']): ?>
                                أنا
                            <?php else: ?>
                                <?php echo $message['receiver_name']; ?> (<?php echo $message['receiver_type']; ?>)
                            <?php endif; ?>
                        </div>
                        <div>
                            <small class="text-muted"><?php echo date('Y-m-d H:i', strtotime($message['created_at'])); ?></small>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="message-body p-3">
                        <?php echo nl2br($message['message']); ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card-custom">
                    <h6 class="mb-3">إجراءات</h6>
                    <?php if($message['sender_id'] != $_SESSION['user_id']): ?>
                    <a href="?compose=1&reply=<?php echo $message['id']; ?>" class="btn-primary-custom w-100 mb-2 text-center">
                        <i class="fas fa-reply ms-2"></i> رد
                    </a>
                    <?php endif; ?>
                    <a href="?delete=<?php echo $message['id']; ?>&folder=<?php echo $folder; ?>" class="btn btn-danger w-100"
                       onclick="return confirm('حذف الرسالة؟')">
                        <i class="fas fa-trash ms-2"></i> حذف
                    </a>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <!-- ========================================= -->
        <!-- تبويبات المجلدات -->
        <!-- ========================================= -->
        <div class="mb-4">
            <a href="?folder=inbox" class="btn <?php echo $folder == 'inbox' ? 'btn-primary-custom' : 'btn-outline-custom'; ?> me-2">
                <i class="fas fa-inbox ms-2"></i> الوارد
                <?php if($unread_count > 0): ?>
                <span class="badge bg-danger ms-1"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="?folder=sent" class="btn <?php echo $folder == 'sent' ? 'btn-primary-custom' : 'btn-outline-custom'; ?>">
                <i class="fas fa-paper-plane ms-2"></i> المرسل
            </a>
        </div>
        
        <!-- ========================================= -->
        <!-- قائمة الرسائل -->
        <!-- ========================================= -->
        <?php if(empty($messages)): ?>
            <div class="card-custom text-center py-5">
                <i class="fas fa-envelope-open-text fa-4x text-muted mb-3"></i>
                <h5>لا توجد رسائل</h5>
                <p class="text-muted">المجلد فارغ</p>
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
                        <tr class="<?php echo ($folder == 'inbox' && !$msg['is_read']) ? 'table-light' : ''; ?>">
                            <td><?php echo $i+1; ?></td>
                            <td>
                                <strong><?php echo $folder == 'inbox' ? $msg['sender_name'] : $msg['receiver_name']; ?></strong>
                            </td>
                            <td><?php echo $msg['subject']; ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($msg['created_at'])); ?></td>
                            <td>
                                <?php if($folder == 'inbox' && !$msg['is_read']): ?>
                                <span class="badge bg-primary">جديدة</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?view=<?php echo $msg['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
const students = <?php echo json_encode($students); ?>;
const supervisors = <?php echo json_encode($supervisors); ?>;
const entities = <?php echo json_encode($entities); ?>;

document.getElementById('receiverType')?.addEventListener('change', function() {
    const type = this.value;
    const select = document.getElementById('receiverSelect');
    select.innerHTML = '<option value="">اختر المستلم</option>';
    
    let list = [];
    if(type === 'student') list = students;
    else if(type === 'supervisor') list = supervisors;
    else if(type === 'training_entity') list = entities;
    
    list.forEach(item => {
        const option = document.createElement('option');
        option.value = item.user_id;
        option.textContent = item.full_name_ar + ' (' + item.role + ')';
        select.appendChild(option);
    });
});

function showCompose() {
    document.getElementById('composeForm').style.display = 'block';
}

function hideCompose() {
    document.getElementById('composeForm').style.display = 'none';
}
</script>

<?php require_once '../../includes/coordinator_footer.php'; ?>