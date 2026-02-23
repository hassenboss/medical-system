<?php
require_once '../../includes/auth.php';
checkRole(['Admin', 'Lab Technician']);

require_once '../../config/db.php';

// معالجة تحديث نتيجة فحص
if(isset($_POST['update_result'])) {
    try {
        $stmt = $conn->prepare("UPDATE lab_requests SET results = ?, notes = ?, status = 'Completed', completed_by = ?, completed_date = NOW() WHERE id = ?");
        $stmt->execute([
            $_POST['results'],
            $_POST['notes'],
            $_SESSION['user_id'],
            $_POST['request_id']
        ]);

        // تسجيل النشاط
        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Update Lab Result', 'lab_requests', ?)");
        $logStmt->execute([$_SESSION['user_id'], $_POST['request_id']]);

        $success = "تم تحديث نتيجة الفحص بنجاح";
    } catch(PDOException $e) {
        $error = "حدث خطأ: " . $e->getMessage();
    }
}

// معالجة حذف نتيجة فحص
if(isset($_GET['delete_result'])) {
    try {
        $request_id = $_GET['delete_result'];

        $stmt = $conn->prepare("UPDATE lab_requests SET results = NULL, notes = NULL, status = 'Paid', completed_by = NULL, completed_date = NULL WHERE id = ?");
        $stmt->execute([$request_id]);

        // تسجيل النشاط
        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Delete Lab Result', 'lab_requests', ?)");
        $logStmt->execute([$_SESSION['user_id'], $request_id]);

        $success = "تم حذف نتيجة الفحص بنجاح";
    } catch(PDOException $e) {
        $error = "حدث خطأ: " . $e->getMessage();
    }
}

// الحصول على قائمة نتائج الفحوصات
$results = $conn->query("SELECT lr.*, lt.name as test_name, p.full_name as patient_name, p.medical_record_number, d.full_name as doctor_name, v.visit_date, u.full_name as completed_by_name
                         FROM lab_requests lr 
                         JOIN lab_tests lt ON lr.lab_test_id = lt.id 
                         JOIN visits v ON lr.visit_id = v.id 
                         JOIN patients p ON v.patient_id = p.id 
                         JOIN doctors d ON v.doctor_id = d.id 
                         LEFT JOIN users u ON lr.completed_by = u.id
                         WHERE lr.status = 'Completed' AND lr.results IS NOT NULL
                         ORDER BY lr.completed_date DESC")->fetchAll();

// الحصول على تفاصيل نتيجة فحص محدد للتعديل
$selected_result = null;
if(isset($_GET['edit_result'])) {
    $request_id = $_GET['edit_result'];
    $selected_result = $conn->query("SELECT lr.*, lt.name as test_name, p.full_name as patient_name, p.medical_record_number, p.age, p.gender, d.full_name as doctor_name, v.visit_date, v.symptoms 
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
    <title>نتائج الفحوصات</title>
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
                            <a class="nav-link" href="dashboard.php">
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
                            <a class="nav-link active" href="results.php">
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
                    <h1 class="h2">نتائج الفحوصات</h1>
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

                <!-- قائمة نتائج الفحوصات -->
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-file-medical me-2"></i>نتائج الفحوصات</h5>
                        <span class="badge bg-light text-dark"><?php echo count($results); ?> نتيجة</span>
                    </div>
                    <div class="card-body">
                        <?php if(count($results) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>رقم الطلب</th>
                                        <th>اسم المريض</th>
                                        <th>رقم الملف الطبي</th>
                                        <th>الفحص</th>
                                        <th>الطبيب</th>
                                        <th>تاريخ الإنجاز</th>
                                        <th>أجرى الفحص</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($results as $result): ?>
                                    <tr>
                                        <td><span class="badge bg-primary">#<?php echo $result['id']; ?></span></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-info rounded-circle text-white d-flex justify-content-center align-items-center me-2">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                                <?php echo $result['patient_name']; ?>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-secondary"><?php echo $result['medical_record_number']; ?></span></td>
                                        <td><?php echo $result['test_name']; ?></td>
                                        <td><?php echo $result['doctor_name']; ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($result['completed_date'])); ?></td>
                                        <td><?php echo $result['completed_by_name']; ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="?edit_result=<?php echo $result['id']; ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?delete_result=<?php echo $result['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('هل أنت متأكد من حذف هذه النتيجة؟')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-file-medical fa-3x text-muted mb-3"></i>
                            <h5>لا توجد نتائج فحوصات</h5>
                            <p class="text-muted">لم يتم إدخال أي نتائج فحوصات بعد</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if($selected_result): ?>
                <!-- تعديل نتيجة الفحص -->
                <div class="card mt-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">تعديل نتيجة الفحص</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">اسم المريض</label>
                                <p class="form-control-plaintext"><?php echo $selected_result['patient_name']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">رقم الملف الطبي</label>
                                <p class="form-control-plaintext"><?php echo $selected_result['medical_record_number']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">العمر</label>
                                <p class="form-control-plaintext"><?php echo $selected_result['age']; ?> سنة</p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">الجنس</label>
                                <p class="form-control-plaintext"><?php echo $selected_result['gender']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">الطبيب</label>
                                <p class="form-control-plaintext"><?php echo $selected_result['doctor_name']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">تاريخ الزيارة</label>
                                <p class="form-control-plaintext"><?php echo date('Y-m-d H:i', strtotime($selected_result['visit_date'])); ?></p>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">الأعراض</label>
                            <p class="form-control-plaintext"><?php echo $selected_result['symptoms']; ?></p>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">الفحص المطلوب</label>
                            <p class="form-control-plaintext"><?php echo $selected_result['test_name']; ?></p>
                        </div>

                        <form method="POST" action="">
                            <input type="hidden" name="request_id" value="<?php echo $selected_result['id']; ?>">

                            <div class="mb-3">
                                <label for="results" class="form-label">نتائج الفحص</label>
                                <textarea class="form-control" id="results" name="results" rows="5" required><?php echo $selected_result['results']; ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="notes" class="form-label">ملاحظات</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo $selected_result['notes']; ?></textarea>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="results.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i>
                                    إلغاء
                                </a>
                                <button type="submit" name="update_result" class="btn btn-success">
                                    <i class="fas fa-check me-1"></i>
                                    حفظ التعديلات
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
