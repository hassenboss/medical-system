
<?php
require_once '../../includes/auth.php';
checkRole(['Admin', 'Lab Technician']);

require_once '../../config/db.php';

// الحصول على قائمة طلبات الفحوصات المدفوعة
$lab_requests = $conn->query("SELECT lr.*, lt.name as test_name, p.full_name as patient_name, p.medical_record_number, d.full_name as doctor_name, v.visit_date 
                              FROM lab_requests lr 
                              JOIN lab_tests lt ON lr.lab_test_id = lt.id 
                              JOIN visits v ON lr.visit_id = v.id 
                              JOIN patients p ON v.patient_id = p.id 
                              JOIN doctors d ON v.doctor_id = d.id 
                              WHERE lr.status = 'Paid' 
                              ORDER BY lr.request_date DESC")->fetchAll();

// معالجة تحديث نتائج الفحوصات
if(isset($_POST['update_lab_result'])) {
    try {
        $stmt = $conn->prepare("UPDATE lab_requests SET results = ?, notes = ?, status = 'Completed', completed_by = ?, completed_date = NOW() WHERE id = ?");
        $stmt->execute([
            $_POST['results'],
            $_POST['notes'],
            $_SESSION['user_id'],
            $_POST['lab_request_id']
        ]);

        // التحقق من اكتمال جميع فحوصات الزيارة
        $visit_id = $conn->query("SELECT visit_id FROM lab_requests WHERE id = " . $_POST['lab_request_id'])->fetch()['visit_id'];

        $pending_tests = $conn->query("SELECT COUNT(*) as count FROM lab_requests WHERE visit_id = $visit_id AND status != 'Completed'")->fetch()['count'];

        if($pending_tests == 0) {
            // تحديث حالة الزيارة
            $stmt = $conn->prepare("UPDATE visits SET status = 'Lab Completed' WHERE id = ?");
            $stmt->execute([$visit_id]);
        }

        // تسجيل النشاط
        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Update Lab Result', 'lab_requests', ?)");
        $logStmt->execute([$_SESSION['user_id'], $_POST['lab_request_id']]);

        $success = "تم تحديث نتيجة الفحص بنجاح";

        // إعادة تحميل قائمة الطلبات
        $lab_requests = $conn->query("SELECT lr.*, lt.name as test_name, p.full_name as patient_name, p.medical_record_number, d.full_name as doctor_name, v.visit_date 
                                      FROM lab_requests lr 
                                      JOIN lab_tests lt ON lr.lab_test_id = lt.id 
                                      JOIN visits v ON lr.visit_id = v.id 
                                      JOIN patients p ON v.patient_id = p.id 
                                      JOIN doctors d ON v.doctor_id = d.id 
                                      WHERE lr.status = 'Paid' 
                                      ORDER BY lr.request_date DESC")->fetchAll();
    } catch(PDOException $e) {
        $error = "حدث خطأ: " . $e->getMessage();
    }
}

// الحصول على تفاصيل طلب فحص محدد
$selected_request = null;
if(isset($_GET['request_id'])) {
    $request_id = $_GET['request_id'];
    $selected_request = $conn->query("SELECT lr.*, lt.name as test_name, p.full_name as patient_name, p.medical_record_number, p.age, p.gender, d.full_name as doctor_name, v.visit_date, v.symptoms 
                                     FROM lab_requests lr 
                                     JOIN lab_tests lt ON lr.lab_test_id = lt.id 
                                     JOIN visits v ON lr.visit_id = v.id 
                                     JOIN patients p ON v.patient_id = p.id 
                                     JOIN doctors d ON v.doctor_id = d.id 
                                     WHERE lr.id = $request_id")->fetch();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم المعمل</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/dashboard.css" rel="stylesheet">
</head>
<body>
    <!-- شريط التنقل العلوي -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-hospital me-2"></i>
                نظام إدارة المستشفى
            </a>

            <div class="d-flex align-items-center">
                <span class="text-white me-3">
                    <i class="fas fa-user-circle me-1"></i>
                    <?php echo $_SESSION['full_name']; ?>
                </span>
                <a href="../../includes/auth.php?logout=true" class="btn btn-outline-light">
                    <i class="fas fa-sign-out-alt me-1"></i>
                    تسجيل الخروج
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- القائمة الجانبية -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="#">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                لوحة التحكم
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="tests.php">
                                <i class="fas fa-vial me-2"></i>
                                إدارة الفحوصات
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="results.php">
                                <i class="fas fa-file-medical me-2"></i>
                                نتائج الفحوصات
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar me-2"></i>
                                التقارير
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- المحتوى الرئيسي -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">لوحة تحكم المعمل</h1>
                </div>

                <?php if(isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <?php if(isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <!-- قائمة طلبات الفحوصات -->
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-vial me-2"></i>طلبات الفحوصات المدفوعة</h5>
                        <span class="badge bg-light text-dark"><?php echo count($lab_requests); ?> طلب</span>
                    </div>
                    <div class="card-body">
                        <?php if(count($lab_requests) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>رقم الطلب</th>
                                        <th>اسم المريض</th>
                                        <th>رقم الملف الطبي</th>
                                        <th>الفحص</th>
                                        <th>الطبيب</th>
                                        <th>تاريخ الطلب</th>
                                        <th>الأولوية</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($lab_requests as $request): ?>
                                    <tr>
                                        <td><span class="badge bg-primary">#<?php echo $request['id']; ?></span></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-info rounded-circle text-white d-flex justify-content-center align-items-center me-2">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                                <?php echo $request['patient_name']; ?>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-secondary"><?php echo $request['medical_record_number']; ?></span></td>
                                        <td>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span><?php echo $request['test_name']; ?></span>
                                                <?php 
                                                // تحديد الأولوية بناءً على تاريخ الطلب
                                                $requestDate = new DateTime($request['request_date']);
                                                $now = new DateTime();
                                                $interval = $now->diff($requestDate);
                                                $hours = $interval->h + ($interval->days * 24);
                                                
                                                $priority = 'normal';
                                                $priorityBadge = 'bg-secondary';
                                                $priorityText = 'عادي';
                                                
                                                if($hours > 24) {
                                                    $priority = 'high';
                                                    $priorityBadge = 'bg-danger';
                                                    $priorityText = 'عاجل';
                                                } elseif($hours > 12) {
                                                    $priority = 'medium';
                                                    $priorityBadge = 'bg-warning';
                                                    $priorityText = 'متوسط';
                                                }
                                                ?>
                                            </div>
                                        </td>
                                        <td><?php echo $request['doctor_name']; ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($request['request_date'])); ?></td>
                                        <td><span class="badge <?php echo $priorityBadge; ?>"><?php echo $priorityText; ?></span></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="?request_id=<?php echo $request['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-success" onclick="markAsCompleted(<?php echo $request['id']; ?>)">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-clipboard-check fa-3x text-muted mb-3"></i>
                            <h5>لا توجد فحوصات مدفوعة</h5>
                            <p class="text-muted">جميع الفحوصات تم إنجازها أو لم يتم دفعها بعد</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if($selected_request): ?>
                <!-- تفاصيل طلب الفحص -->
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">تفاصيل طلب الفحص</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">اسم المريض</label>
                                <p class="form-control-plaintext"><?php echo $selected_request['patient_name']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">رقم الملف الطبي</label>
                                <p class="form-control-plaintext"><?php echo $selected_request['medical_record_number']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">العمر</label>
                                <p class="form-control-plaintext"><?php echo $selected_request['age']; ?> سنة</p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">الجنس</label>
                                <p class="form-control-plaintext"><?php echo $selected_request['gender']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">الطبيب</label>
                                <p class="form-control-plaintext"><?php echo $selected_request['doctor_name']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">تاريخ الزيارة</label>
                                <p class="form-control-plaintext"><?php echo date('Y-m-d H:i', strtotime($selected_request['visit_date'])); ?></p>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">الأعراض</label>
                            <p class="form-control-plaintext"><?php echo $selected_request['symptoms']; ?></p>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">الفحص المطلوب</label>
                            <p class="form-control-plaintext"><?php echo $selected_request['test_name']; ?></p>
                        </div>

                        <form method="POST" action="">
                            <input type="hidden" name="lab_request_id" value="<?php echo $selected_request['id']; ?>">

                            <div class="mb-3">
                                <label for="results" class="form-label">نتائج الفحص</label>
                                <textarea class="form-control" id="results" name="results" rows="5" required><?php echo $selected_request['results']; ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="notes" class="form-label">ملاحظات</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo $selected_request['notes']; ?></textarea>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i>
                                    إلغاء
                                </a>
                                <button type="submit" name="update_lab_result" class="btn btn-success">
                                    <i class="fas fa-check me-1"></i>
                                    اعتماد النتيجة
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script>
        // تحديث حالة الفحص كمكتمل
        function markAsCompleted(requestId) {
            if(confirm('هل أنت متأكد من أنك تريد تحديث حالة هذا الفحص كمكتمل؟')) {
                // إنشاء نموذج مخفي وإرساله
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const requestIdField = document.createElement('input');
                requestIdField.type = 'hidden';
                requestIdField.name = 'lab_request_id';
                requestIdField.value = requestId;
                
                const resultsField = document.createElement('input');
                resultsField.type = 'hidden';
                resultsField.name = 'results';
                resultsField.value = 'تم إجراء الفحص بنجاح';
                
                const notesField = document.createElement('input');
                notesField.type = 'hidden';
                notesField.name = 'notes';
                notesField.value = 'تم تحديث الحالة مباشرة من القائمة';
                
                const submitField = document.createElement('input');
                submitField.type = 'hidden';
                submitField.name = 'update_lab_result';
                submitField.value = '1';
                
                form.appendChild(requestIdField);
                form.appendChild(resultsField);
                form.appendChild(notesField);
                form.appendChild(submitField);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
