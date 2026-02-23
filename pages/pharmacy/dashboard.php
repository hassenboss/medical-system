
<?php
require_once '../../includes/auth.php';
checkRole(['Admin', 'Pharmacist']);

require_once '../../config/db.php';

// الحصول على قائمة الوصفات الطبية المدفوعة
$prescriptions = $conn->query("SELECT pr.*, m.name as medicine_name, p.full_name as patient_name, p.medical_record_number, d.full_name as doctor_name, v.visit_date 
                               FROM prescriptions pr 
                               JOIN medicines m ON pr.medicine_id = m.id 
                               JOIN visits v ON pr.visit_id = v.id 
                               JOIN patients p ON v.patient_id = p.id 
                               JOIN doctors d ON v.doctor_id = d.id 
                               WHERE pr.status = 'Paid' 
                               ORDER BY pr.created_at DESC")->fetchAll();

// معالجة صرف الدواء
if(isset($_POST['dispense_medicine'])) {
    try {
        $conn->beginTransaction();

        // تحديث حالة الوصفة
        $stmt = $conn->prepare("UPDATE prescriptions SET status = 'Dispensed', dispensed_by = ?, dispensed_date = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $_POST['prescription_id']]);

        // الحصول على بيانات الدواء
        $prescription = $conn->query("SELECT * FROM prescriptions WHERE id = " . $_POST['prescription_id'])->fetch();

        // خصم الكمية من المخزون
        $stmt = $conn->prepare("UPDATE medicines SET quantity = quantity - ? WHERE id = ?");
        $stmt->execute([1, $prescription['medicine_id']]);

        // تسجيل عملية البيع
        $medicine = $conn->query("SELECT * FROM medicines WHERE id = " . $prescription['medicine_id'])->fetch();
        $stmt = $conn->prepare("INSERT INTO pharmacy_sales (prescription_id, medicine_id, quantity, unit_price, total_price, sold_by) 
                               VALUES (?, ?, 1, ?, ?)");
        $stmt->execute([
            $_POST['prescription_id'],
            $prescription['medicine_id'],
            $medicine['price'],
            $medicine['price']
        ]);

        // التحقق من اكتمال جميع أدوية الزيارة
        $visit_id = $conn->query("SELECT visit_id FROM prescriptions WHERE id = " . $_POST['prescription_id'])->fetch()['visit_id'];

        $pending_medicines = $conn->query("SELECT COUNT(*) as count FROM prescriptions WHERE visit_id = $visit_id AND status != 'Dispensed'")->fetch()['count'];

        if($pending_medicines == 0) {
            // تحديث حالة الزيارة
            $stmt = $conn->prepare("UPDATE visits SET status = 'Completed' WHERE id = ?");
            $stmt->execute([$visit_id]);
        }

        $conn->commit();

        // تسجيل النشاط
        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Dispense Medicine', 'prescriptions', ?)");
        $logStmt->execute([$_SESSION['user_id'], $_POST['prescription_id']]);

        $success = "تم صرف الدواء بنجاح";

        // إعادة تحميل قائمة الوصفات
        $prescriptions = $conn->query("SELECT pr.*, m.name as medicine_name, p.full_name as patient_name, p.medical_record_number, d.full_name as doctor_name, v.visit_date 
                                      FROM prescriptions pr 
                                      JOIN medicines m ON pr.medicine_id = m.id 
                                      JOIN visits v ON pr.visit_id = v.id 
                                      JOIN patients p ON v.patient_id = p.id 
                                      JOIN doctors d ON v.doctor_id = d.id 
                                      WHERE pr.status = 'Paid' 
                                      ORDER BY pr.created_at DESC")->fetchAll();
    } catch(PDOException $e) {
        $conn->rollBack();
        $error = "حدث خطأ: " . $e->getMessage();
    }
}

// الحصول على تفاصيل وصفة محددة
$selected_prescription = null;
if(isset($_GET['prescription_id'])) {
    $prescription_id = $_GET['prescription_id'];
    $selected_prescription = $conn->query("SELECT pr.*, m.name as medicine_name, m.quantity as medicine_quantity, m.min_quantity, p.full_name as patient_name, p.medical_record_number, p.age, p.gender, d.full_name as doctor_name, v.visit_date, v.diagnosis 
                                          FROM prescriptions pr 
                                          JOIN medicines m ON pr.medicine_id = m.id 
                                          JOIN visits v ON pr.visit_id = v.id 
                                          JOIN patients p ON v.patient_id = p.id 
                                          JOIN doctors d ON v.doctor_id = d.id 
                                          WHERE pr.id = $prescription_id")->fetch();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم الصيدلية</title>
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
                            <a class="nav-link" href="medicines.php">
                                <i class="fas fa-pills me-2"></i>
                                إدارة الأدوية
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="sales.php">
                                <i class="fas fa-cash-register me-2"></i>
                                سجل المبيعات
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
                    <h1 class="h2">لوحة تحكم الصيدلية</h1>
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

                <!-- قائمة الوصفات الطبية -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">الوصفات الطبية المدفوعة</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>رقم الوصفة</th>
                                        <th>اسم المريض</th>
                                        <th>رقم الملف الطبي</th>
                                        <th>الدواء</th>
                                        <th>الطبيب</th>
                                        <th>تاريخ الوصفة</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($prescriptions as $prescription): ?>
                                    <tr>
                                        <td><?php echo $prescription['id']; ?></td>
                                        <td><?php echo $prescription['patient_name']; ?></td>
                                        <td><?php echo $prescription['medical_record_number']; ?></td>
                                        <td><?php echo $prescription['medicine_name']; ?></td>
                                        <td><?php echo $prescription['doctor_name']; ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($prescription['created_at'])); ?></td>
                                        <td>
                                            <a href="?prescription_id=<?php echo $prescription['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye me-1"></i>
                                                عرض
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <?php if($selected_prescription): ?>
                <!-- تفاصيل الوصفة الطبية -->
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">تفاصيل الوصفة الطبية</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">اسم المريض</label>
                                <p class="form-control-plaintext"><?php echo $selected_prescription['patient_name']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">رقم الملف الطبي</label>
                                <p class="form-control-plaintext"><?php echo $selected_prescription['medical_record_number']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">العمر</label>
                                <p class="form-control-plaintext"><?php echo $selected_prescription['age']; ?> سنة</p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">الجنس</label>
                                <p class="form-control-plaintext"><?php echo $selected_prescription['gender']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">الطبيب</label>
                                <p class="form-control-plaintext"><?php echo $selected_prescription['doctor_name']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">تاريخ الزيارة</label>
                                <p class="form-control-plaintext"><?php echo date('Y-m-d H:i', strtotime($selected_prescription['visit_date'])); ?></p>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">التشخيص</label>
                                <p class="form-control-plaintext"><?php echo $selected_prescription['diagnosis']; ?></p>
                            </div>
                        </div>

                        <hr>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">اسم الدواء</label>
                                <p class="form-control-plaintext"><?php echo $selected_prescription['medicine_name']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">الجرعة</label>
                                <p class="form-control-plaintext"><?php echo $selected_prescription['dosage']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">المدة</label>
                                <p class="form-control-plaintext"><?php echo $selected_prescription['duration']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">التعليمات</label>
                                <p class="form-control-plaintext"><?php echo $selected_prescription['instructions']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">الكمية المتوفرة</label>
                                <p class="form-control-plaintext">
                                    <?php 
                                    echo $selected_prescription['medicine_quantity'];
                                    if($selected_prescription['medicine_quantity'] <= $selected_prescription['min_quantity']) {
                                        echo ' <span class="badge bg-warning">ناقص</span>';
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>

                        <?php if($selected_prescription['status'] == 'Paid'): ?>
                        <form method="POST" action="">
                            <input type="hidden" name="prescription_id" value="<?php echo $selected_prescription['id']; ?>">
                            <button type="submit" name="dispense_medicine" class="btn btn-success">
                                <i class="fas fa-check me-1"></i>
                                صرف الدواء
                            </button>
                        </form>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-1"></i>
                            تم صرف هذا الدواء في <?php echo date('Y-m-d H:i', strtotime($selected_prescription['dispensed_date'])); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
