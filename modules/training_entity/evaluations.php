 <?php
// modules/training_entity/evaluations.php - إدارة التقييمات (صفحة واحدة شاملة)
// ✅ لا توجد مسافات قبل هذا السطر

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
// معالجة حفظ التقييم
// =============================================
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_evaluation'])) {
    
    $application_id = $_POST['application_id'] ?? 0;
    $commitment = (int)($_POST['commitment'] ?? 0);
    $performance = (int)($_POST['performance'] ?? 0);
    $teamwork = (int)($_POST['teamwork'] ?? 0);
    $initiative = (int)($_POST['initiative'] ?? 0);
    $skills = (int)($_POST['skills'] ?? 0);
    $report = trim($_POST['report'] ?? '');
    $completed = isset($_POST['training_completed']) ? 1 : 0;
    
    // التحقق من البيانات
    if($application_id == 0) {
        $_SESSION['error'] = 'معرف الطلب غير صالح';
        header('Location: evaluations.php');
        exit();
    }
    
    // حساب المتوسط
    $final_grade = ($commitment + $performance + $teamwork + $initiative + $skills) / 5;
    
    // تحديد التقدير
    $grade_letter = 'F';
    if($final_grade >= 95) $grade_letter = 'A+';
    elseif($final_grade >= 90) $grade_letter = 'A';
    elseif($final_grade >= 85) $grade_letter = 'B+';
    elseif($final_grade >= 80) $grade_letter = 'B';
    elseif($final_grade >= 75) $grade_letter = 'C+';
    elseif($final_grade >= 70) $grade_letter = 'C';
    elseif($final_grade >= 65) $grade_letter = 'D+';
    elseif($final_grade >= 60) $grade_letter = 'D';
    
    // التحقق من وجود تقييم سابق
    $check = $pdo->prepare("SELECT id FROM evaluations WHERE application_id = ?");
    $check->execute([$application_id]);
    $existing = $check->fetch();
    
    try {
        if($existing) {
            // تحديث تقييم موجود
            $update = $pdo->prepare("
                UPDATE evaluations SET
                    entity_commitment = ?,
                    entity_performance = ?,
                    entity_teamwork = ?,
                    entity_initiative = ?,
                    entity_skills = ?,
                    entity_report = ?,
                    final_grade = ?,
                    final_grade_letter = ?,
                    training_completed = ?,
                    updated_at = NOW()
                WHERE application_id = ?
            ");
            $update->execute([
                $commitment, $performance, $teamwork, $initiative, $skills,
                $report, $final_grade, $grade_letter, $completed, $application_id
            ]);
            $_SESSION['success'] = 'تم تحديث التقييم بنجاح';
        } else {
            // إضافة تقييم جديد
            $insert = $pdo->prepare("
                INSERT INTO evaluations (
                    application_id, student_id,
                    entity_commitment, entity_performance, entity_teamwork,
                    entity_initiative, entity_skills, entity_report,
                    final_grade, final_grade_letter, training_completed,
                    created_at, updated_at
                ) VALUES (
                    ?, ?,
                    ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?,
                    NOW(), NOW()
                )
            ");
            
            // جلب student_id من الطلب
            $student = $pdo->prepare("SELECT student_id FROM applications WHERE id = ?");
            $student->execute([$application_id]);
            $student_id = $student->fetchColumn();
            
            $insert->execute([
                $application_id, $student_id,
                $commitment, $performance, $teamwork,
                $initiative, $skills, $report,
                $final_grade, $grade_letter, $completed
            ]);
            $_SESSION['success'] = 'تم إضافة التقييم بنجاح';
        }
        
        header('Location: evaluations.php?view=' . $application_id);
        exit();
        
    } catch(Exception $e) {
        $_SESSION['error'] = 'حدث خطأ: ' . $e->getMessage();
        header('Location: evaluations.php?create=' . $application_id);
        exit();
    }
}

// =============================================
// جلب البيانات للعرض
// =============================================

$page_title = 'التقييمات';
require_once '../../includes/entity_header.php';

$create_id = $_GET['create'] ?? 0;
$view_id = $_GET['view'] ?? 0;

// إنشاء تقييم جديد
if($create_id > 0) {
    $stmt = $pdo->prepare("
        SELECT a.*, s.full_name_ar, s.student_id, ts.title_ar
        FROM applications a
        JOIN students s ON a.student_id = s.id
        JOIN training_seats ts ON a.seat_id = ts.id
        WHERE a.id = ? AND ts.entity_id = ? AND a.status IN ('approved', 'accepted_by_entity')
    ");
    $stmt->execute([$create_id, $entity['id']]);
    $trainee = $stmt->fetch();
    
    if(!$trainee) {
        $_SESSION['error'] = 'المتدربة غير موجودة أو لم يتم قبولها بعد';
        header('Location: trainees.php');
        exit();
    }
    
    // التحقق من عدم وجود تقييم سابق
    $check = $pdo->prepare("SELECT id FROM evaluations WHERE application_id = ?");
    $check->execute([$create_id]);
    if($check->fetch()) {
        header('Location: evaluations.php?view=' . $create_id);
        exit();
    }
}

// جلب جميع التقييمات
$stmt = $pdo->prepare("
    SELECT e.*, s.full_name_ar, s.student_id, ts.title_ar
    FROM evaluations e
    JOIN students s ON e.student_id = s.id
    JOIN applications a ON e.application_id = a.id
    JOIN training_seats ts ON a.seat_id = ts.id
    WHERE ts.entity_id = ?
    ORDER BY e.created_at DESC
");
$stmt->execute([$entity['id']]);
$evaluations = $stmt->fetchAll();

// جلب تقييم محدد للعرض
if($view_id > 0) {
    $stmt = $pdo->prepare("
        SELECT e.*, s.full_name_ar, s.student_id, ts.title_ar
        FROM evaluations e
        JOIN students s ON e.student_id = s.id
        JOIN applications a ON e.application_id = a.id
        JOIN training_seats ts ON a.seat_id = ts.id
        WHERE e.application_id = ? AND ts.entity_id = ?
    ");
    $stmt->execute([$view_id, $entity['id']]);
    $evaluation = $stmt->fetch();
    
    if(!$evaluation) {
        header('Location: evaluations.php');
        exit();
    }
}

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h4 class="mb-1">التقييمات</h4>
            <p class="text-muted mb-0">تقييم أداء المتدربات</p>
        </div>
        <?php if($create_id || $view_id): ?>
        <a href="evaluations.php" class="btn-outline-custom">
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
    
    <?php if($create_id > 0 && isset($trainee)): ?>
    <!-- نموذج إنشاء تقييم -->
    <div class="card-custom">
        <h5 class="mb-4">تقييم المتدربة: <?php echo htmlspecialchars($trainee['full_name_ar']); ?></h5>
        <p class="text-muted mb-4">الفرصة: <?php echo htmlspecialchars($trainee['title_ar']); ?></p>
        
        <form method="POST" id="evaluationForm">
            <input type="hidden" name="application_id" value="<?php echo $create_id; ?>">
            
            <div class="row">
                <div class="col-md-6 mb-4">
                    <label class="form-label fw-bold">الالتزام (0-100)</label>
                    <input type="range" class="form-range" name="commitment" min="0" max="100" step="1" value="80" oninput="updateValue(this, 'commitmentValue')">
                    <output id="commitmentValue" class="d-block text-center fw-bold fs-5">80</output>
                </div>
                
                <div class="col-md-6 mb-4">
                    <label class="form-label fw-bold">الأداء (0-100)</label>
                    <input type="range" class="form-range" name="performance" min="0" max="100" step="1" value="80" oninput="updateValue(this, 'performanceValue')">
                    <output id="performanceValue" class="d-block text-center fw-bold fs-5">80</output>
                </div>
                
                <div class="col-md-6 mb-4">
                    <label class="form-label fw-bold">العمل الجماعي (0-100)</label>
                    <input type="range" class="form-range" name="teamwork" min="0" max="100" step="1" value="80" oninput="updateValue(this, 'teamworkValue')">
                    <output id="teamworkValue" class="d-block text-center fw-bold fs-5">80</output>
                </div>
                
                <div class="col-md-6 mb-4">
                    <label class="form-label fw-bold">المبادرة (0-100)</label>
                    <input type="range" class="form-range" name="initiative" min="0" max="100" step="1" value="80" oninput="updateValue(this, 'initiativeValue')">
                    <output id="initiativeValue" class="d-block text-center fw-bold fs-5">80</output>
                </div>
                
                <div class="col-md-6 mb-4">
                    <label class="form-label fw-bold">المهارات (0-100)</label>
                    <input type="range" class="form-range" name="skills" min="0" max="100" step="1" value="80" oninput="updateValue(this, 'skillsValue')">
                    <output id="skillsValue" class="d-block text-center fw-bold fs-5">80</output>
                </div>
                
                <div class="col-12 mb-3">
                    <label class="form-label fw-bold">تقرير الأداء</label>
                    <textarea class="form-control" name="report" rows="4" placeholder="اكتب تقييمك التفصيلي للمتدربة..."></textarea>
                </div>
                
                <div class="col-12 mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="training_completed" id="completed" value="1">
                        <label class="form-check-label" for="completed">
                            تم إكمال التدريب بنجاح
                        </label>
                    </div>
                </div>
                
                <div class="col-12">
                    <button type="submit" name="save_evaluation" class="btn-primary-custom">
                        <i class="fas fa-save ms-2"></i>
                        حفظ التقييم
                    </button>
                    <a href="trainees.php" class="btn btn-secondary ms-2">إلغاء</a>
                </div>
            </div>
        </form>
    </div>
    
    <?php elseif($view_id > 0 && isset($evaluation)): ?>
    <!-- عرض تقييم محدد -->
    <div class="row">
        <div class="col-md-8">
            <div class="card-custom">
                <h5 class="mb-3">تفاصيل التقييم</h5>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>المتدربة:</strong> <?php echo htmlspecialchars($evaluation['full_name_ar']); ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>الرقم الجامعي:</strong> <?php echo htmlspecialchars($evaluation['student_id']); ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>الفرصة:</strong> <?php echo htmlspecialchars($evaluation['title_ar']); ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>تاريخ التقييم:</strong> <?php echo date('Y-m-d', strtotime($evaluation['created_at'])); ?>
                    </div>
                </div>
                
                <hr>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>الالتزام:</strong>
                        <div class="progress mt-1" style="height: 20px;">
                            <div class="progress-bar bg-primary" style="width: <?php echo $evaluation['entity_commitment']; ?>%;">
                                <?php echo $evaluation['entity_commitment']; ?>%
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>الأداء:</strong>
                        <div class="progress mt-1" style="height: 20px;">
                            <div class="progress-bar bg-success" style="width: <?php echo $evaluation['entity_performance']; ?>%;">
                                <?php echo $evaluation['entity_performance']; ?>%
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>العمل الجماعي:</strong>
                        <div class="progress mt-1" style="height: 20px;">
                            <div class="progress-bar bg-info" style="width: <?php echo $evaluation['entity_teamwork']; ?>%;">
                                <?php echo $evaluation['entity_teamwork']; ?>%
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>المبادرة:</strong>
                        <div class="progress mt-1" style="height: 20px;">
                            <div class="progress-bar bg-warning" style="width: <?php echo $evaluation['entity_initiative']; ?>%;">
                                <?php echo $evaluation['entity_initiative']; ?>%
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>المهارات:</strong>
                        <div class="progress mt-1" style="height: 20px;">
                            <div class="progress-bar bg-danger" style="width: <?php echo $evaluation['entity_skills']; ?>%;">
                                <?php echo $evaluation['entity_skills']; ?>%
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if($evaluation['entity_report']): ?>
                <div class="mt-3 p-3 bg-light rounded">
                    <strong>التقرير:</strong>
                    <p class="mb-0 mt-2"><?php echo nl2br(htmlspecialchars($evaluation['entity_report'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card-custom text-center">
                <h5 class="mb-3">الدرجة النهائية</h5>
                <div class="display-1 fw-bold" style="color: var(--primary);"><?php echo number_format($evaluation['final_grade'], 1); ?></div>
                <p class="fs-4"><?php echo $evaluation['final_grade_letter']; ?></p>
                <p>من 100</p>
                
                <?php if($evaluation['training_completed']): ?>
                <span class="badge bg-success fs-6 p-3">✅ مكتمل</span>
                <?php else: ?>
                <span class="badge bg-warning fs-6 p-3">⏳ غير مكتمل</span>
                <?php endif; ?>
            </div>
            
            <div class="card-custom mt-3">
                <a href="trainees.php?view=<?php echo $view_id; ?>" class="btn-outline-custom w-100 text-center">
                    <i class="fas fa-user-graduate ms-2"></i>
                    عرض ملف المتدربة
                </a>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <!-- قائمة التقييمات -->
    <div class="table-custom">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>المتدربة</th>
                    <th>الفرصة</th>
                    <th>الدرجة</th>
                    <th>التقدير</th>
                    <th>تاريخ التقييم</th>
                    <th>الحالة</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($evaluations)): ?>
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <i class="fas fa-star fa-3x text-muted mb-3"></i>
                        <h5>لا توجد تقييمات</h5>
                        <p class="text-muted">لم يتم تقييم أي متدربة بعد</p>
                        <a href="trainees.php" class="btn-primary-custom mt-2">
                            <i class="fas fa-users ms-2"></i>
                            عرض المتدربات
                        </a>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach($evaluations as $index => $eval): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($eval['full_name_ar']); ?></strong>
                            <br>
                            <small class="text-muted"><?php echo htmlspecialchars($eval['student_id']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($eval['title_ar']); ?></td>
                        <td>
                            <strong class="fs-5"><?php echo number_format($eval['final_grade'], 1); ?></strong>
                        </td>
                        <td>
                            <span class="badge bg-primary p-2"><?php echo $eval['final_grade_letter']; ?></span>
                        </td>
                        <td><?php echo date('Y-m-d', strtotime($eval['created_at'])); ?></td>
                        <td>
                            <?php if($eval['training_completed']): ?>
                            <span class="badge bg-success">مكتمل</span>
                            <?php else: ?>
                            <span class="badge bg-warning">غير مكتمل</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="?view=<?php echo $eval['application_id']; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye"></i> عرض
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- JavaScript -->
<script>
    function updateValue(input, outputId) {
        document.getElementById(outputId).value = input.value;
    }
    
    // تحديث القيم عند تحميل الصفحة
    document.addEventListener('DOMContentLoaded', function() {
        const ranges = document.querySelectorAll('input[type="range"]');
        ranges.forEach(range => {
            const outputId = range.getAttribute('oninput').match(/'([^']+)'/)[1];
            document.getElementById(outputId).value = range.value;
        });
    });
</script>

<?php
require_once '../../includes/entity_footer.php';
?>