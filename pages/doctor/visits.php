<?php
require_once '../../includes/auth.php';
checkRole(['Admin', 'Doctor']);

require_once '../../config/db.php';

// الحصول على قائمة الزيارات
$visits = $conn->query("SELECT v.*, p.full_name as patient_name, p.medical_record_number, p.age, p.gender 
                        FROM visits v 
                        JOIN patients p ON v.patient_id = p.id 
                        WHERE v.doctor_id = (SELECT id FROM doctors WHERE full_name = '" . $_SESSION['full_name'] . "')
                        ORDER BY v.visit_date DESC")->fetchAll();

// معالجة إضافة زيارة جديدة
if(isset($_POST['add_visit'])) {
    try {
        $stmt = $conn->prepare("INSERT INTO visits (patient_id, doctor_id, visit_date, symptoms, vital_signs, notes, status) 
                               VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['patient_id'],
            $_POST['doctor_id'],
            $_POST['visit_date'],
            $_POST['symptoms'],
            $_POST['vital_signs'],
            $_POST['notes'],
            'Consultation Paid'
        ]);

        // تسجيل النشاط
        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Add Visit', 'visits', ?)");
        $logStmt->execute([$_SESSION['user_id'], $conn->lastInsertId()]);

        $success = "تم إضافة الزيارة بنجاح";

        // إعادة تحميل قائمة الزيارات
        $visits = $conn->query("SELECT v.*, p.full_name as patient_name, p.medical_record_number, p.age, p.gender 
                                FROM visits v 
                                JOIN patients p ON v.patient_id = p.id 
                                WHERE v.doctor_id = (SELECT id FROM doctors WHERE full_name = '" . $_SESSION['full_name'] . "')
                                ORDER BY v.visit_date DESC")->fetchAll();
    } catch(PDOException $e) {
        $error = "حدث خطأ: " . $e->getMessage();
    }
}

// الحصول على قائمة المرضى
$patients = $conn->query("SELECT * FROM patients ORDER BY full_name")->fetchAll();

// الحصول على بيانات الطبيب الحالي
$doctor = $conn->query("SELECT * FROM doctors WHERE full_name = '" . $_SESSION['full_name'] . "'")->fetch();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الزيارات</title>
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
                            <a class="nav-link" href="patients.php">
                                <i class="fas fa-users me-2"></i>
                                إدارة المرضى
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="visits.php">
                                <i class="fas fa-calendar-check me-2"></i>
                                إدارة الزيارات
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="prescriptions.php">
                                <i class="fas fa-prescription me-2"></i>
                                إدارة الوصفات
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../reception/lab_requests.php">
                                <i class="fas fa-vial me-2"></i>
                                طلبات فحوصات المعمل
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
                    <h1 class="h2">إدارة الزيارات</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#visitModal">
                                <i class="fas fa-plus me-1"></i>
                                زيارة جديدة
                            </button>
                        </div>
                    </div>
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

                <!-- قائمة الزيارات -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">قائمة الزيارات</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>المريض</th>
                                        <th>رقم الملف</th>
                                        <th>التاريخ</th>
                                        <th>الحالة</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($visits as $visit): ?>
                                    <tr>
                                        <td><?php echo $visit['patient_name']; ?></td>
                                        <td><?php echo $visit['medical_record_number']; ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($visit['visit_date'])); ?></td>
                                        <td>
                                            <?php
                                            $statusClass = '';
                                            switch($visit['status']) {
                                                case 'Consultation Paid':
                                                    $statusClass = 'badge-success';
                                                    break;
                                                case 'In Consultation':
                                                    $statusClass = 'badge-warning';
                                                    break;
                                                case 'Lab Payment Pending':
                                                case 'Lab Paid':
                                                case 'Pharmacy Payment Pending':
                                                case 'Pharmacy Paid':
                                                    $statusClass = 'badge-info';
                                                    break;
                                                case 'Lab Completed':
                                                    $statusClass = 'badge-primary';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?>">
                                                <?php echo $visit['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="dashboard.php?visit_id=<?php echo $visit['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- نموذج إضافة زيارة -->
    <div class="modal fade" id="visitModal" tabindex="-1" aria-labelledby="visitModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="visitModalLabel">زيارة جديدة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="patient_id" class="form-label">المريض</label>
                                <select class="form-select" id="patient_id" name="patient_id" required>
                                    <option value="">اختر مريض</option>
                                    <?php foreach($patients as $patient): ?>
                                    <option value="<?php echo $patient['id']; ?>"><?php echo $patient['full_name']; ?> - <?php echo $patient['medical_record_number']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="visit_date" class="form-label">تاريخ الزيارة</label>
                                <input type="date" class="form-control" id="visit_date" name="visit_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="symptoms" class="form-label">الأعراض</label>
                            <textarea class="form-control" id="symptoms" name="symptoms" rows="3"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="vital_signs" class="form-label">العلامات الحيوية</label>
                            <textarea class="form-control" id="vital_signs" name="vital_signs" rows="3"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">ملاحظات</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>

                        <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="add_visit" class="btn btn-primary">حفظ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>