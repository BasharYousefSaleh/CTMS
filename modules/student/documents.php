 <?php
// modules/student/documents.php - إدارة المستندات والملفات
session_start();
require_once '../../config/config.php';

// =============================================
// التحقق من تسجيل الدخول
// =============================================
if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'student') {
    header('Location: ../auth/login.php?type=student');
    exit();
}

// جلب بيانات المتدربة
$stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();

if(!$student) {
    header('Location: ../auth/logout.php');
    exit();
}

// =============================================
// التحقق من وجود عمود description وإضافته إذا لم يكن موجوداً
// =============================================
try {
    $check_column = $pdo->query("SHOW COLUMNS FROM attachments LIKE 'description'");
    if($check_column->rowCount() == 0) {
        $pdo->exec("ALTER TABLE attachments ADD COLUMN description TEXT NULL AFTER file_type");
    }
} catch(Exception $e) {
    // تجاهل الخطأ
}

// =============================================
// تحديد الصفحة الحالية - ✅ دعم id أو delete
// =============================================
$action = $_GET['action'] ?? 'list'; // list, upload, view
$doc_id = $_GET['id'] ?? $_GET['delete'] ?? 0; // يدعم id و delete
$type = $_GET['type'] ?? '';

// =============================================
// معالجة رفع مستند جديد
// =============================================
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_document'])) {
    
    $doc_type = $_POST['doc_type'] ?? 'other';
    $doc_name = trim($_POST['doc_name'] ?? '');
    
    if(!isset($_FILES['document_file']) || $_FILES['document_file']['error'] != 0) {
        $_SESSION['error'] = 'الرجاء اختيار ملف لرفعه';
        header('Location: documents.php?action=upload');
        exit();
    }
    
    $file = $_FILES['document_file'];
    $filename = $file['name'];
    $filesize = $file['size'];
    $filetmp = $file['tmp_name'];
    
    // التحقق من نوع الملف
    $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'xls', 'xlsx', 'ppt', 'pptx'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if(!in_array($ext, $allowed)) {
        $_SESSION['error'] = 'نوع الملف غير مسموح. الأنواع المسموحة: ' . implode(', ', $allowed);
        header('Location: documents.php?action=upload');
        exit();
    }
    
    // التحقق من حجم الملف (10 MB كحد أقصى)
    if($filesize > 10 * 1024 * 1024) {
        $_SESSION['error'] = 'حجم الملف كبير جداً. الحد الأقصى 10 ميجابايت';
        header('Location: documents.php?action=upload');
        exit();
    }
    
    // إنشاء اسم فريد للملف
    $new_filename = 'doc_' . $student['id'] . '_' . time() . '_' . uniqid() . '.' . $ext;
    $upload_path = '../../uploads/students/' . $new_filename;
    
    // التأكد من وجود المجلد
    if(!is_dir('../../uploads/students/')) {
        mkdir('../../uploads/students/', 0777, true);
    }
    
    // رفع الملف
    if(move_uploaded_file($filetmp, $upload_path)) {
        $display_name = !empty($doc_name) ? $doc_name : $filename;
        
        $insert = $pdo->prepare("
            INSERT INTO attachments 
            (related_type, related_id, file_name, file_path, file_type, file_size, uploaded_by, uploaded_by_type, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'student', NOW())
        ");
        $insert->execute([
            'student',
            $student['id'],
            $display_name,
            'students/' . $new_filename,
            $doc_type,
            $filesize,
            $_SESSION['user_id']
        ]);
        
        $_SESSION['success'] = 'تم رفع المستند بنجاح';
        header('Location: documents.php');
        exit();
        
    } else {
        $_SESSION['error'] = 'حدث خطأ أثناء رفع الملف';
        header('Location: documents.php?action=upload');
        exit();
    }
}

// =============================================
// معالجة حذف مستند - ✅ نسخة محسنة
// =============================================
if(isset($_GET['delete']) && $doc_id > 0) {
    
    // جلب معلومات الملف
    $stmt = $pdo->prepare("SELECT * FROM attachments WHERE id = ? AND related_type = 'student' AND related_id = ?");
    $stmt->execute([$doc_id, $student['id']]);
    $doc = $stmt->fetch();
    
    if($doc) {
        // ✅ احذف من قاعدة البيانات أولاً
        $delete = $pdo->prepare("DELETE FROM attachments WHERE id = ?");
        if($delete->execute([$doc_id])) {
            // ✅ بعدها حاول تحذف الملف
            $file_path = __DIR__ . '/../../uploads/' . $doc['file_path'];
            if(file_exists($file_path)) {
                unlink($file_path);
            }
            $_SESSION['success'] = 'تم حذف المستند بنجاح';
        } else {
            $_SESSION['error'] = 'فشل حذف المستند';
        }
    } else {
        $_SESSION['error'] = 'المستند غير موجود';
    }
    
    header('Location: documents.php');
    exit();
}

// =============================================
// جلب المستندات
// =============================================
$all_docs = $pdo->prepare("
    SELECT * FROM attachments 
    WHERE related_type = 'student' AND related_id = ?
    ORDER BY created_at DESC
");
$all_docs->execute([$student['id']]);
$documents = $all_docs->fetchAll();

// =============================================
// جلب مستند محدد للعرض
// =============================================
$document = null;
if($action == 'view' && $doc_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM attachments WHERE id = ? AND related_type = 'student' AND related_id = ?");
    $stmt->execute([$doc_id, $student['id']]);
    $document = $stmt->fetch();
    
    if(!$document) {
        header('Location: documents.php');
        exit();
    }
}

// =============================================
// إحصائيات المستندات
// =============================================
$stats = [
    'total' => count($documents),
    'cv' => count(array_filter($documents, fn($d) => $d['file_type'] == 'cv')),
    'id_card' => count(array_filter($documents, fn($d) => $d['file_type'] == 'id_card')),
    'transcript' => count(array_filter($documents, fn($d) => $d['file_type'] == 'transcript')),
    'other' => count(array_filter($documents, fn($d) => !in_array($d['file_type'], ['cv', 'id_card', 'transcript']))),
];

// أنواع المستندات
$doc_types = [
    'cv' => 'السيرة الذاتية',
    'id_card' => 'بطاقة الهوية',
    'university_card' => 'بطاقة الجامعة',
    'transcript' => 'كشف الدرجات',
    'training_letter' => 'خطاب التدريب',
    'insurance' => 'وثيقة التأمين',
    'certificate' => 'شهادة',
    'other' => 'أخرى'
];

$page_title = 'المستندات';
require_once '../../includes/student_header.php';

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h4 class="mb-1">المستندات</h4>
            <p class="text-muted mb-0">إدارة ورفع جميع المستندات الخاصة بك</p>
        </div>
        <?php if($action == 'list'): ?>
        <a href="?action=upload" class="btn-primary-custom">
            <i class="fas fa-cloud-upload-alt ms-2"></i>
            رفع مستند جديد
        </a>
        <?php else: ?>
        <a href="documents.php" class="btn-outline-custom">
            <i class="fas fa-arrow-right ms-2"></i>
            العودة للقائمة
        </a>
        <?php endif; ?>
    </div>
    
    <?php if($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <!-- ========================================= -->
    <!-- رفع مستند جديد -->
    <!-- ========================================= -->
    <?php if($action == 'upload'): ?>
    
    <div class="card-custom">
        <h5 class="mb-4">رفع مستند جديد</h5>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">نوع المستند</label>
                    <select class="form-select" name="doc_type" required>
                        <option value="">اختر نوع المستند</option>
                        <?php foreach($doc_types as $key => $value): ?>
                        <option value="<?php echo $key; ?>" <?php echo $type == $key ? 'selected' : ''; ?>>
                            <?php echo $value; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">اسم المستند (اختياري)</label>
                    <input type="text" class="form-control" name="doc_name" placeholder="اتركه فارغاً لاستخدام اسم الملف">
                </div>
                
                <div class="col-12 mb-3">
                    <label class="form-label fw-bold">اختيار ملف <span class="text-danger">*</span></label>
                    <input type="file" class="form-control" name="document_file" required id="fileInput">
                    <small class="text-muted">
                        الأنواع المسموحة: PDF, Word, Excel, PowerPoint, JPG, PNG (الحد الأقصى 10 ميجابايت)
                    </small>
                </div>
                
                <!-- معاينة الملف -->
                <div class="col-12 mb-3" id="filePreview" style="display: none;">
                    <div class="preview-box p-3 bg-light rounded">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-file-pdf text-danger fa-2x ms-3" id="fileIcon"></i>
                            <div>
                                <strong id="fileName"></strong>
                                <br>
                                <small class="text-muted" id="fileSize"></small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-12">
                    <hr>
                    <button type="submit" name="upload_document" class="btn-primary-custom">
                        <i class="fas fa-cloud-upload-alt ms-2"></i>
                        رفع المستند
                    </button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- ========================================= -->
    <!-- عرض مستند محدد -->
    <!-- ========================================= -->
    <?php elseif($action == 'view' && $document): ?>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card-custom">
                <h5 class="mb-3">تفاصيل المستند</h5>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">اسم المستند</small>
                        <strong><?php echo htmlspecialchars($document['file_name']); ?></strong>
                    </div>
                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">نوع المستند</small>
                        <strong><?php echo $doc_types[$document['file_type']] ?? $document['file_type']; ?></strong>
                    </div>
                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">تاريخ الرفع</small>
                        <strong><?php echo date('Y-m-d H:i', strtotime($document['created_at'])); ?></strong>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">حجم الملف</small>
                        <strong><?php echo round($document['file_size'] / 1024, 2); ?> KB</strong>
                    </div>
                    <div class="col-md-4 mb-3">
                        <small class="text-muted d-block">نوع الملف</small>
                        <strong><?php echo strtoupper(pathinfo($document['file_path'], PATHINFO_EXTENSION)); ?></strong>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- معاينة -->
            <div class="card-custom text-center">
                <div class="file-preview mb-3">
                    <?php
                    $ext = strtolower(pathinfo($document['file_path'], PATHINFO_EXTENSION));
                    $icon = 'fa-file';
                    $color = 'text-secondary';
                    
                    if(in_array($ext, ['pdf'])) {
                        $icon = 'fa-file-pdf';
                        $color = 'text-danger';
                    } elseif(in_array($ext, ['doc', 'docx'])) {
                        $icon = 'fa-file-word';
                        $color = 'text-primary';
                    } elseif(in_array($ext, ['xls', 'xlsx'])) {
                        $icon = 'fa-file-excel';
                        $color = 'text-success';
                    } elseif(in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                        $icon = 'fa-file-image';
                        $color = 'text-info';
                    }
                    ?>
                    <i class="fas <?php echo $icon . ' ' . $color; ?> fa-5x"></i>
                </div>
                
                <h6><?php echo htmlspecialchars($document['file_name']); ?></h6>
                
                <div class="d-grid gap-2 mt-3">
                    <a href="../../uploads/<?php echo $document['file_path']; ?>" class="btn-primary-custom" target="_blank">
                        <i class="fas fa-eye ms-2"></i>
                        عرض الملف
                    </a>
                    <a href="../../uploads/<?php echo $document['file_path']; ?>" class="btn-outline-custom" download>
                        <i class="fas fa-download ms-2"></i>
                        تحميل
                    </a>
                    <a href="?delete=<?php echo $document['id']; ?>" class="btn btn-danger" 
                       onclick="return confirm('هل أنت متأكدة من حذف هذا المستند؟')">
                        <i class="fas fa-trash ms-2"></i>
                        حذف
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ========================================= -->
    <!-- قائمة المستندات -->
    <!-- ========================================= -->
    <?php else: ?>
    
    <!-- إحصائيات سريعة -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">إجمالي المستندات</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['cv']; ?></div>
                <div class="stat-label">السير الذاتية</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['id_card']; ?></div>
                <div class="stat-label">بطاقات هوية</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['transcript']; ?></div>
                <div class="stat-label">كشوف درجات</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['other']; ?></div>
                <div class="stat-label">مستندات أخرى</div>
            </div>
        </div>
    </div>
    
    <!-- قائمة المستندات -->
    <?php if(empty($documents)): ?>
    <div class="card-custom text-center py-5">
        <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
        <h5>لا توجد مستندات</h5>
        <p class="text-muted mb-3">لم تقومي برفع أي مستندات بعد</p>
        <a href="?action=upload" class="btn-primary-custom">
            <i class="fas fa-cloud-upload-alt ms-2"></i>
            رفع أول مستند
        </a>
    </div>
    <?php else: ?>
    
    <div class="row">
        <?php foreach($documents as $doc): ?>
        <?php
        $ext = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
        $icon = 'fa-file';
        $color = 'text-secondary';
        
        if(in_array($ext, ['pdf'])) {
            $icon = 'fa-file-pdf';
            $color = 'text-danger';
        } elseif(in_array($ext, ['doc', 'docx'])) {
            $icon = 'fa-file-word';
            $color = 'text-primary';
        } elseif(in_array($ext, ['xls', 'xlsx'])) {
            $icon = 'fa-file-excel';
            $color = 'text-success';
        } elseif(in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            $icon = 'fa-file-image';
            $color = 'text-info';
        }
        ?>
        <div class="col-md-3 mb-3">
            <div class="document-card">
                <div class="document-type">
                    <span class="badge"><?php echo $doc_types[$doc['file_type']] ?? 'أخرى'; ?></span>
                </div>
                <div class="document-icon">
                    <i class="fas <?php echo $icon . ' ' . $color; ?>"></i>
                </div>
                <div class="document-name">
                    <?php echo htmlspecialchars(mb_substr($doc['file_name'], 0, 20)) . (mb_strlen($doc['file_name']) > 20 ? '...' : ''); ?>
                </div>
                <div class="document-date">
                    <?php echo date('Y-m-d', strtotime($doc['created_at'])); ?>
                </div>
                <div class="document-actions">
                    <a href="?action=view&id=<?php echo $doc['id']; ?>" class="btn btn-sm btn-outline-primary" title="عرض">
                        <i class="fas fa-eye"></i>
                    </a>
                    <a href="../../uploads/<?php echo $doc['file_path']; ?>" class="btn btn-sm btn-outline-success" title="تحميل" download>
                        <i class="fas fa-download"></i>
                    </a>
                    <a href="?delete=<?php echo $doc['id']; ?>" class="btn btn-sm btn-outline-danger" title="حذف" 
                       onclick="return confirm('هل أنت متأكدة من حذف هذا المستند؟')">
                        <i class="fas fa-trash"></i>
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- CSS إضافي -->
<style>
.stat-card {
    background: white;
    border-radius: 10px;
    padding: 15px;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.stat-number {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--primary);
    line-height: 1;
}

.stat-label {
    color: #6c757d;
    font-size: 0.85rem;
    margin-top: 5px;
}

.document-card {
    background: white;
    border-radius: 10px;
    padding: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    position: relative;
    height: 100%;
}

.document-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}

.document-type {
    position: absolute;
    top: 10px;
    left: 10px;
}

.document-type .badge {
    background: var(--gradient-pink);
    color: white;
    font-size: 0.7rem;
    padding: 3px 8px;
}

.document-icon {
    text-align: center;
    font-size: 3rem;
    margin: 20px 0 10px;
}

.document-name {
    text-align: center;
    font-weight: 600;
    margin-bottom: 5px;
    font-size: 0.9rem;
}

.document-date {
    text-align: center;
    color: #6c757d;
    font-size: 0.8rem;
    margin-bottom: 15px;
}

.document-actions {
    display: flex;
    justify-content: center;
    gap: 5px;
}

.preview-box {
    border: 2px dashed var(--primary);
}

#filePreview {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<!-- JavaScript -->
<script>
// معاينة الملف قبل الرفع
document.getElementById('fileInput')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if(file) {
        const preview = document.getElementById('filePreview');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const fileIcon = document.getElementById('fileIcon');
        
        fileName.textContent = file.name;
        fileSize.textContent = (file.size / 1024).toFixed(2) + ' KB';
        
        // تغيير الأيقونة حسب نوع الملف
        const ext = file.name.split('.').pop().toLowerCase();
        const iconMap = {
            'pdf': 'fa-file-pdf text-danger',
            'doc': 'fa-file-word text-primary',
            'docx': 'fa-file-word text-primary',
            'xls': 'fa-file-excel text-success',
            'xlsx': 'fa-file-excel text-success',
            'jpg': 'fa-file-image text-info',
            'jpeg': 'fa-file-image text-info',
            'png': 'fa-file-image text-info'
        };
        
        if(iconMap[ext]) {
            const [icon, color] = iconMap[ext].split(' ');
            fileIcon.className = 'fas ' + icon + ' ' + color + ' fa-2x ms-3';
        } else {
            fileIcon.className = 'fas fa-file text-secondary fa-2x ms-3';
        }
        
        preview.style.display = 'block';
    }
});
</script>

<?php
require_once '../../includes/student_footer.php';
?>