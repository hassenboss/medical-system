
<?php
require_once '../../includes/auth.php';
checkRole(['Admin', 'Doctor']);

require_once '../../config/db.php';

// الحصول على قائمة الزيارات المدفوعة للطبيب الحالي
$doctor_id = $conn->query("SELECT id FROM doctors WHERE full_name = '" . $_SESSION['full_name'] . "'")->fetch()['id'];

$visits = $conn->query("SELECT v.*, p.full_name as patient_name, p.medical_record_number, p.age, p.gender 
                        FROM visits v 
                        JOIN patients p ON v.patient_id = p.id 
                        WHERE v.doctor_id = $doctor_id AND v.status IN ('Consultation Paid', 'In Consultation', 'Lab Payment Pending', 'Lab Paid', 'Lab Completed', 'Pharmacy Payment Pending', 'Pharmacy Paid')
                        ORDER BY v.visit_date DESC")->fetchAll();

// الحصول على قائمة فحوصات المعمل
$lab_tests = $conn->query("SELECT * FROM lab_tests WHERE is_active = 1")->fetchAll();

// الحصول على قائمة الأدوية
$medicines = $conn->query("SELECT * FROM medicines WHERE is_active = 1 ORDER BY name")->fetchAll();

// معالجة تحديث بيانات الزيارة
if(isset($_POST['update_visit'])) {
    try {
        $stmt = $conn->prepare("UPDATE visits SET symptoms = ?, vital_signs = ?, notes = ?, status = ? WHERE id = ?");
        $stmt->execute([
            $_POST['symptoms'],
            $_POST['vital_signs'],
            $_POST['notes'],
            $_POST['status'],
            $_POST['visit_id']
        ]);

        // تسجيل النشاط
        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Update Visit', 'visits', ?)");
        $logStmt->execute([$_SESSION['user_id'], $_POST['visit_id']]);

        $success = "تم تحديث بيانات الزيارة بنجاح";

        // إعادة تحميل قائمة الزيارات
        $visits = $conn->query("SELECT v.*, p.full_name as patient_name, p.medical_record_number, p.age, p.gender 
                                FROM visits v 
                                JOIN patients p ON v.patient_id = p.id 
                                WHERE v.doctor_id = $doctor_id AND v.status IN ('Consultation Paid', 'In Consultation', 'Lab Payment Pending', 'Lab Paid', 'Lab Completed', 'Pharmacy Payment Pending', 'Pharmacy Paid')
                                ORDER BY v.visit_date DESC")->fetchAll();
    } catch(PDOException $e) {
        $error = "حدث خطأ: " . $e->getMessage();
    }
}

// معالجة طلب فحوصات المعمل
if(isset($_POST['request_lab'])) {
    try {
        $conn->beginTransaction();

        // تحديث حالة الزيارة
        $stmt = $conn->prepare("UPDATE visits SET status = 'Lab Payment Pending' WHERE id = ?");
        $stmt->execute([$_POST['visit_id']]);

        // إضافة طلبات الفحوصات
        if(!empty($_POST['lab_tests'])) {
            foreach($_POST['lab_tests'] as $lab_test_id) {
                $stmt = $conn->prepare("INSERT INTO lab_requests (visit_id, lab_test_id) VALUES (?, ?)");
                $stmt->execute([$_POST['visit_id'], $lab_test_id]);
            }

            // حساب إجمالي تكلفة الفحوصات
            $total_amount = 0;
            foreach($_POST['lab_tests'] as $lab_test_id) {
                $lab_test = $conn->query("SELECT price FROM lab_tests WHERE id = $lab_test_id")->fetch();
                $total_amount += $lab_test['price'];
            }

            // إنشاء فاتورة للفحوصات
            $visit = $conn->query("SELECT patient_id FROM visits WHERE id = " . $_POST['visit_id'])->fetch();
            $invoice_number = 'LAB-' . date('Y-m-d') . '-' . str_pad($_POST['visit_id'], 4, '0', STR_PAD_LEFT);

            $stmt = $conn->prepare("INSERT INTO invoices (invoice_number, patient_id, visit_id, total_amount, final_amount, payment_status, created_by) 
                                   VALUES (?, ?, ?, ?, ?, 'Pending', ?)");
            $stmt->execute([
                $invoice_number,
                $visit['patient_id'],
                $_POST['visit_id'],
                $total_amount,
                $total_amount,
                $_SESSION['user_id']
            ]);

            $invoice_id = $conn->lastInsertId();

            // إضافة تفاصيل الفاتورة
            foreach($_POST['lab_tests'] as $lab_test_id) {
                $lab_test = $conn->query("SELECT * FROM lab_tests WHERE id = $lab_test_id")->fetch();
                $stmt = $conn->prepare("INSERT INTO invoice_items (invoice_id, item_type, item_id, quantity, unit_price, total_price) 
                                       VALUES (?, 'Lab Test', ?, 1, ?, ?)");
                $stmt->execute([$invoice_id, $lab_test_id, $lab_test['price'], $lab_test['price']]);
            }
        }

        $conn->commit();

        // تسجيل النشاط
        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Request Lab Tests', 'visits', ?)");
        $logStmt->execute([$_SESSION['user_id'], $_POST['visit_id']]);

        $success = "تم طلب فحوصات المعمل بنجاح";

        // إعادة تحميل قائمة الزيارات
        $visits = $conn->query("SELECT v.*, p.full_name as patient_name, p.medical_record_number, p.age, p.gender 
                                FROM visits v 
                                JOIN patients p ON v.patient_id = p.id 
                                WHERE v.doctor_id = $doctor_id AND v.status IN ('Consultation Paid', 'In Consultation', 'Lab Payment Pending', 'Lab Paid', 'Lab Completed', 'Pharmacy Payment Pending', 'Pharmacy Paid')
                                ORDER BY v.visit_date DESC")->fetchAll();
    } catch(PDOException $e) {
        $conn->rollBack();
        $error = "حدث خطأ: " . $e->getMessage();
    }
}

// معالجة إضافة نتائج فحوصات المعمل
if(isset($_POST['add_lab_results'])) {
    try {
        $conn->beginTransaction();

        // تحديث حالة الزيارة
        $stmt = $conn->prepare("UPDATE visits SET status = 'Lab Completed' WHERE id = ?");
        $stmt->execute([$_POST['visit_id']]);

        // إضافة نتائج الفحوصات
        if(!empty($_POST['lab_request_ids'])) {
            foreach($_POST['lab_request_ids'] as $index => $request_id) {
                $stmt = $conn->prepare("UPDATE lab_requests SET results = ?, notes = ?, status = 'Completed', completed_date = NOW() WHERE id = ?");
                $stmt->execute([
                    $_POST['results'][$index],
                    $_POST['notes'][$index],
                    $request_id
                ]);
            }
        }

        $conn->commit();

        // تسجيل النشاط
        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Add Lab Results', 'visits', ?)");
        $logStmt->execute([$_SESSION['user_id'], $_POST['visit_id']]);

        $success = "تم إضافة نتائج فحوصات المعمل بنجاح";

        // إعادة تحميل قائمة الزيارات
        $visits = $conn->query("SELECT v.*, p.full_name as patient_name, p.medical_record_number, p.age, p.gender 
                                FROM visits v 
                                JOIN patients p ON v.patient_id = p.id 
                                WHERE v.doctor_id = $doctor_id AND v.status IN ('Consultation Paid', 'In Consultation', 'Lab Payment Pending', 'Lab Paid', 'Lab Completed', 'Pharmacy Payment Pending', 'Pharmacy Paid')
                                ORDER BY v.visit_date DESC")->fetchAll();
    } catch(PDOException $e) {
        $conn->rollBack();
        $error = "حدث خطأ: " . $e->getMessage();
    }
}

// معالجة إضافة وصفة طبية
if(isset($_POST['add_prescription'])) {
    try {
        $conn->beginTransaction();

        // تحديث حالة الزيارة
        $stmt = $conn->prepare("UPDATE visits SET status = 'Pharmacy Payment Pending', diagnosis = ? WHERE id = ?");
        $stmt->execute([$_POST['diagnosis'], $_POST['visit_id']]);

        // إضافة الأدوية
        if(!empty($_POST['medicines'])) {
            foreach($_POST['medicines'] as $index => $medicine_id) {
                $stmt = $conn->prepare("INSERT INTO prescriptions (visit_id, medicine_id, dosage, duration, instructions) 
                                       VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['visit_id'],
                    $medicine_id,
                    $_POST['dosage'][$index],
                    $_POST['duration'][$index],
                    $_POST['instructions'][$index]
                ]);
            }

            // حساب إجمالي تكلفة الأدوية
            $total_amount = 0;
            foreach($_POST['medicines'] as $medicine_id) {
                $medicine = $conn->query("SELECT price FROM medicines WHERE id = $medicine_id")->fetch();
                $total_amount += $medicine['price'];
            }

            // إنشاء فاتورة للأدوية
            $visit = $conn->query("SELECT patient_id FROM visits WHERE id = " . $_POST['visit_id'])->fetch();
            $invoice_number = 'PHARM-' . date('Y-m-d') . '-' . str_pad($_POST['visit_id'], 4, '0', STR_PAD_LEFT);

            $stmt = $conn->prepare("INSERT INTO invoices (invoice_number, patient_id, visit_id, total_amount, final_amount, payment_status, created_by) 
                                   VALUES (?, ?, ?, ?, ?, 'Pending', ?)");
            $stmt->execute([
                $invoice_number,
                $visit['patient_id'],
                $_POST['visit_id'],
                $total_amount,
                $total_amount,
                $_SESSION['user_id']
            ]);

            $invoice_id = $conn->lastInsertId();

            // إضافة تفاصيل الفاتورة
            foreach($_POST['medicines'] as $medicine_id) {
                $medicine = $conn->query("SELECT * FROM medicines WHERE id = $medicine_id")->fetch();
                $stmt = $conn->prepare("INSERT INTO invoice_items (invoice_id, item_type, item_id, quantity, unit_price, total_price) 
                                       VALUES (?, 'Medicine', ?, 1, ?, ?)");
                $stmt->execute([$invoice_id, $medicine_id, $medicine['price'], $medicine['price']]);
            }
        }

        $conn->commit();

        // تسجيل النشاط
        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Add Prescription', 'visits', ?)");
        $logStmt->execute([$_SESSION['user_id'], $_POST['visit_id']]);

        $success = "تم إضافة الوصفة الطبية بنجاح";

        // إعادة تحميل قائمة الزيارات
        $visits = $conn->query("SELECT v.*, p.full_name as patient_name, p.medical_record_number, p.age, p.gender 
                                FROM visits v 
                                JOIN patients p ON v.patient_id = p.id 
                                WHERE v.doctor_id = $doctor_id AND v.status IN ('Consultation Paid', 'In Consultation', 'Lab Payment Pending', 'Lab Paid', 'Lab Completed', 'Pharmacy Payment Pending', 'Pharmacy Paid')
                                ORDER BY v.visit_date DESC")->fetchAll();
    } catch(PDOException $e) {
        $conn->rollBack();
        $error = "حدث خطأ: " . $e->getMessage();
    }
}

// الحصول على تفاصيل زيارة محددة
$selected_visit = null;
if(isset($_GET['visit_id'])) {
    $visit_id = $_GET['visit_id'];
    $selected_visit = $conn->query("SELECT v.*, p.full_name as patient_name, p.medical_record_number, p.age, p.gender, p.phone, p.address 
                                   FROM visits v 
                                   JOIN patients p ON v.patient_id = p.id 
                                   WHERE v.id = $visit_id")->fetch();

    // الحصول على طلبات الفحوصات
    $lab_requests = $conn->query("SELECT lr.*, lt.name as test_name, lt.price 
                                 FROM lab_requests lr 
                                 JOIN lab_tests lt ON lr.lab_test_id = lt.id 
                                 WHERE lr.visit_id = $visit_id")->fetchAll();

    // الحصول على نتائج الفحوصات
    $lab_results = $conn->query("SELECT lr.*, lt.name as test_name 
                                 FROM lab_requests lr 
                                 JOIN lab_tests lt ON lr.lab_test_id = lt.id 
                                 WHERE lr.visit_id = $visit_id AND lr.status = 'Completed'")->fetchAll();

    // الحصول على الوصفات الطبية
    $prescriptions = $conn->query("SELECT pr.*, m.name as medicine_name 
                                  FROM prescriptions pr 
                                  JOIN medicines m ON pr.medicine_id = m.id 
                                  WHERE pr.visit_id = $visit_id")->fetchAll();

    // الحصول على تاريخ الزيارات السابقة
    $previous_visits = $conn->query("SELECT v.*, d.full_name as doctor_name 
                                   FROM visits v 
                                   JOIN doctors d ON v.doctor_id = d.id 
                                   WHERE v.patient_id = " . $selected_visit['patient_id'] . " AND v.id != $visit_id 
                                   ORDER BY v.visit_date DESC")->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم الطبيب</title>
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
                            <a class="nav-link" href="patients.php">
                                <i class="fas fa-users me-2"></i>
                                إدارة المرضى
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="visits.php">
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
                    <h1 class="h2">لوحة تحكم الطبيب</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="new_visit.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-plus me-1"></i>
                                زيارة جديدة
                            </a>
                            <a href="quick_search.php" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#quickSearchModal">
                                <i class="fas fa-search me-1"></i>
                                بحث سريع
                            </a>
                        </div>
                    </div>
                </div>

                <!-- إحصائيات سريعة -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0">
                                            <?php 
                                            $today_visits = $conn->query("SELECT COUNT(*) as count FROM visits WHERE doctor_id = $doctor_id AND DATE(visit_date) = CURDATE()")->fetch()['count'];
                                            echo $today_visits; 
                                            ?>
                                        </h4>
                                        <p class="mb-0">زيارات اليوم</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-calendar-day fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0">
                                            <?php 
                                            $completed_visits = $conn->query("SELECT COUNT(*) as count FROM visits WHERE doctor_id = $doctor_id AND status = 'Lab Completed'")->fetch()['count'];
                                            echo $completed_visits; 
                                            ?>
                                        </h4>
                                        <p class="mb-0">فحوصات مكتملة</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-check-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0">
                                            <?php 
                                            $pending_visits = $conn->query("SELECT COUNT(*) as count FROM visits WHERE doctor_id = $doctor_id AND status IN ('In Consultation', 'Lab Payment Pending', 'Lab Paid', 'Pharmacy Payment Pending')")->fetch()['count'];
                                            echo $pending_visits; 
                                            ?>
                                        </h4>
                                        <p class="mb-0">زيارات معلقة</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-clock fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0">
                                            <?php 
                                            $total_patients = $conn->query("SELECT COUNT(DISTINCT patient_id) as count FROM visits WHERE doctor_id = $doctor_id")->fetch()['count'];
                                            echo $total_patients; 
                                            ?>
                                        </h4>
                                        <p class="mb-0">إجمالي المرضى</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-users fa-2x"></i>
                                    </div>
                                </div>
                            </div>
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

                <div class="row">
                    <!-- قائمة الزيارات -->
                    <div class="col-md-5">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">قائمة الزيارات</h5>
                            </div>
                            <div class="card-body p-0">
                                <!-- شريط البحث -->
                                <div class="p-3 border-bottom">
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="searchVisits" placeholder="البحث عن مريض...">
                                        <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>المريض</th>
                                                <th>التاريخ</th>
                                                <th>الحالة</th>
                                                <th>إجراء</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($visits as $visit): ?>
                                            <tr>
                                                <td><?php echo $visit['patient_name']; ?></td>
                                                <td><?php echo date('d/m/Y', strtotime($visit['visit_date'])); ?></td>
                                                <td>
                                                    <?php
                                                    $statusClass = '';
                                                    switch($visit['status']) {
                                                        case 'Consultation Paid':
                                                            $statusClass = 'status-paid';
                                                            break;
                                                        case 'In Consultation':
                                                            $statusClass = 'status-pending';
                                                            break;
                                                        case 'Lab Payment Pending':
                                                        case 'Lab Paid':
                                                        case 'Pharmacy Payment Pending':
                                                        case 'Pharmacy Paid':
                                                            $statusClass = 'status-pending';
                                                            break;
                                                        case 'Lab Completed':
                                                            $statusClass = 'status-paid';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $statusClass; ?>">
                                                        <?php echo $visit['status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="?visit_id=<?php echo $visit['id']; ?>" class="btn btn-sm btn-primary">
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
                    </div>

                    <!-- تفاصيل الزيارة -->
                    <div class="col-md-7">
                        <?php if($selected_visit): ?>
                        <!-- معلومات المريض -->
                        <div class="card mb-3">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">معلومات المريض</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <strong>الاسم:</strong> <?php echo $selected_visit['patient_name']; ?>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <strong>رقم الملف الطبي:</strong> <?php echo $selected_visit['medical_record_number']; ?>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <strong>العمر:</strong> <?php echo $selected_visit['age']; ?> سنة
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <strong>الجنس:</strong> <?php echo $selected_visit['gender']; ?>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <strong>الهاتف:</strong> <?php echo $selected_visit['phone']; ?>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <strong>العنوان:</strong> <?php echo $selected_visit['address']; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- علامات التبويب -->
                        <ul class="nav nav-tabs" id="visitTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="examination-tab" data-bs-toggle="tab" data-bs-target="#examination" type="button" role="tab" aria-controls="examination" aria-selected="true">
                                    <i class="fas fa-stethoscope me-1"></i>
                                    الفحص
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="lab-tab" data-bs-toggle="tab" data-bs-target="#lab" type="button" role="tab" aria-controls="lab" aria-selected="false">
                                    <i class="fas fa-vial me-1"></i>
                                    فحوصات المعمل
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="prescription-tab" data-bs-toggle="tab" data-bs-target="#prescription" type="button" role="tab" aria-controls="prescription" aria-selected="false">
                                    <i class="fas fa-pills me-1"></i>
                                    الوصفة الطبية
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab" aria-controls="history" aria-selected="false">
                                    <i class="fas fa-history me-1"></i>
                                    التاريخ الطبي
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="visitTabsContent">
                            <!-- تبويب الفحص -->
                            <div class="tab-pane fade show active" id="examination" role="tabpanel" aria-labelledby="examination-tab">
                                <div class="card mt-3">
                                    <div class="card-body">
                                        <form method="POST" action="">
                                            <input type="hidden" name="visit_id" value="<?php echo $selected_visit['id']; ?>">

                                            <div class="mb-3">
                                                <label for="symptoms" class="form-label">الأعراض</label>
                                                <textarea class="form-control" id="symptoms" name="symptoms" rows="3"><?php echo $selected_visit['symptoms']; ?></textarea>
                                            </div>

                                            <div class="mb-3">
                                                <label for="vital_signs" class="form-label">العلامات الحيوية</label>
                                                <textarea class="form-control" id="vital_signs" name="vital_signs" rows="3"><?php echo $selected_visit['vital_signs']; ?></textarea>
                                            </div>

                                            <div class="mb-3">
                                                <label for="notes" class="form-label">ملاحظات</label>
                                                <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo $selected_visit['notes']; ?></textarea>
                                            </div>

                                            <div class="mb-3">
                                                <label for="status" class="form-label">الحالة</label>
                                                <select class="form-select" id="status" name="status">
                                                    <option value="Consultation Paid" <?php echo $selected_visit['status'] == 'Consultation Paid' ? 'selected' : ''; ?>>مدفوع</option>
                                                    <option value="In Consultation" <?php echo $selected_visit['status'] == 'In Consultation' ? 'selected' : ''; ?>>جاري الفحص</option>
                                                    <option value="Lab Payment Pending" <?php echo $selected_visit['status'] == 'Lab Payment Pending' ? 'selected' : ''; ?>>في انتظار دفع الفحوصات</option>
                                                    <option value="Lab Paid" <?php echo $selected_visit['status'] == 'Lab Paid' ? 'selected' : ''; ?>>فحوصات مدفوعة</option>
                                                    <option value="Lab Completed" <?php echo $selected_visit['status'] == 'Lab Completed' ? 'selected' : ''; ?>>نتائج الفحوصات جاهزة</option>
                                                    <option value="Pharmacy Payment Pending" <?php echo $selected_visit['status'] == 'Pharmacy Payment Pending' ? 'selected' : ''; ?>>في انتظار دفع الأدوية</option>
                                                    <option value="Pharmacy Paid" <?php echo $selected_visit['status'] == 'Pharmacy Paid' ? 'selected' : ''; ?>>أدوية مدفوعة</option>
                                                </select>
                                            </div>

                                            <button type="submit" name="update_visit" class="btn btn-primary">
                                                <i class="fas fa-save me-1"></i>
                                                حفظ
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- تبويب فحوصات المعمل -->
                            <div class="tab-pane fade" id="lab" role="tabpanel" aria-labelledby="lab-tab">
                                <div class="card mt-3">
                                    <div class="card-body">
                                        <?php if($selected_visit['status'] == 'Lab Completed'): ?>
                                        <!-- عرض نتائج الفحوصات -->
                                        <h5 class="mb-3"><i class="fas fa-clipboard-check me-2"></i>نتائج الفحوصات</h5>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>الفحص</th>
                                                        <th>النتيجة</th>
                                                        <th>ملاحظات</th>
                                                        <th>تاريخ الإنجاز</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach($lab_results as $result): ?>
                                                    <tr>
                                                        <td><?php echo $result['test_name']; ?></td>
                                                        <td><?php echo $result['results']; ?></td>
                                                        <td><?php echo $result['notes']; ?></td>
                                                        <td><?php echo date('Y-m-d H:i', strtotime($result['completed_date'])); ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <?php elseif($selected_visit['status'] == 'Lab Payment Pending' || $selected_visit['status'] == 'Lab Paid'): ?>
                                        <!-- عرض الفحوصات المطلوبة -->
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>
                                            <?php if($selected_visit['status'] == 'Lab Payment Pending'): ?>
                                            تم إرسال طلبات الفحوصات إلى قسم الاستقبال بانتظار تأكيد الدفع.
                                            <?php else: ?>
                                            تم دفع تكلفة الفحوصات وسيتم عرضها في المعمل للمعالجة.
                                            <?php endif; ?>
                                        </div>
                                        <h5 class="mb-3"><i class="fas fa-vial me-2"></i>الفحوصات المطلوبة</h5>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>الفحص</th>
                                                        <th>السعر</th>
                                                        <th>الحالة</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach($lab_requests as $request): ?>
                                                    <tr>
                                                        <td><?php echo $request['test_name']; ?></td>
                                                        <td><?php echo number_format($request['price'], 2); ?> ريال</td>
                                                        <td>
                                                            <?php
                                                            $statusBadge = '';
                                                            switch($request['status']) {
                                                                case 'Pending':
                                                                    $statusBadge = 'badge-warning';
                                                                    $statusText = 'في انتظار الدفع';
                                                                    break;
                                                                case 'Paid':
                                                                    $statusBadge = 'badge-info';
                                                                    $statusText = 'تم الدفع';
                                                                    break;
                                                                case 'Completed':
                                                                    $statusBadge = 'badge-success';
                                                                    $statusText = 'تم الإنجاز';
                                                                    break;
                                                            }
                                                            ?>
                                                            <span class="badge <?php echo $statusBadge; ?>"><?php echo $statusText; ?></span>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <?php else: ?>
                                        <!-- طلب فحوصات جديدة -->
                                        <h5 class="mb-3"><i class="fas fa-plus-circle me-2"></i>طلب فحوصات جديدة</h5>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>
                                            سيتم إرسال هذه الفحوصات إلى قسم الاستقبال لتأكيد الدفع، ثم إلى المعمل للتنفيذ.
                                        </div>
                                        <form method="POST" action="">
                                            <input type="hidden" name="visit_id" value="<?php echo $selected_visit['id']; ?>">

                                            <div class="mb-3">
                                                <label class="form-label fw-bold">اختر الفحوصات المطلوبة:</label>
                                                <div class="row">
                                                    <?php foreach($lab_tests as $test): ?>
                                                    <div class="col-md-6 mb-2">
                                                        <div class="card">
                                                            <div class="card-body p-2">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="lab_tests[]" value="<?php echo $test['id']; ?>" id="test_<?php echo $test['id']; ?>">
                                                                    <label class="form-check-label d-flex justify-content-between align-items-center" for="test_<?php echo $test['id']; ?>">
                                                                        <span><?php echo $test['name']; ?></span>
                                                                        <span class="badge bg-primary"><?php echo number_format($test['price'], 2); ?> ريال</span>
                                                                    </label>
                                                                </div>
                                                                <?php if(!empty($test['description'])): ?>
                                                                <small class="text-muted d-block mt-1"><?php echo $test['description']; ?></small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>

                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="selectAllTests" onchange="toggleAllLabTests()">
                                                    <label class="form-check-label" for="selectAllTests">
                                                        تحديد الكل
                                                    </label>
                                                </div>
                                                <button type="submit" name="request_lab" class="btn btn-primary">
                                                    <i class="fas fa-paper-plane me-1"></i>
                                                    طلب الفحوصات
                                                </button>
                                            </div>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- تبويب الوصفة الطبية -->
                            <div class="tab-pane fade" id="prescription" role="tabpanel" aria-labelledby="prescription-tab">
                                <div class="card mt-3">
                                    <div class="card-body">
                                        <?php if($selected_visit['status'] == 'Pharmacy Paid'): ?>
                                        <!-- عرض الوصفة الطبية -->
                                        <h5 class="mb-3">الوصفة الطبية</h5>
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>الدواء</th>
                                                        <th>الجرعة</th>
                                                        <th>المدة</th>
                                                        <th>التعليمات</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach($prescriptions as $prescription): ?>
                                                    <tr>
                                                        <td><?php echo $prescription['medicine_name']; ?></td>
                                                        <td><?php echo $prescription['dosage']; ?></td>
                                                        <td><?php echo $prescription['duration']; ?></td>
                                                        <td><?php echo $prescription['instructions']; ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <?php else: ?>
                                        <!-- إضافة وصفة طبية -->
                                        <h5 class="mb-3">إضافة وصفة طبية</h5>
                                        <form method="POST" action="">
                                            <input type="hidden" name="visit_id" value="<?php echo $selected_visit['id']; ?>">

                                            <div class="mb-3">
                                                <label for="diagnosis" class="form-label">التشخيص</label>
                                                <textarea class="form-control" id="diagnosis" name="diagnosis" rows="3"><?php echo $selected_visit['diagnosis']; ?></textarea>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">الأدوية</label>
                                                <div id="medicines_container">
                                                    <div class="row mb-2">
                                                        <div class="col-md-6">
                                                            <select class="form-select medicine-select" name="medicines[]">
                                                                <option value="">اختر دواء</option>
                                                                <?php foreach($medicines as $medicine): ?>
                                                                <option value="<?php echo $medicine['id']; ?>"><?php echo $medicine['name']; ?> - <?php echo number_format($medicine['price'], 2); ?> ريال</option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <input type="text" class="form-control" name="dosage[]" placeholder="الجرعة">
                                                        </div>
                                                        <div class="col-md-2">
                                                            <input type="text" class="form-control" name="duration[]" placeholder="المدة">
                                                        </div>
                                                        <div class="col-md-2">
                                                            <button type="button" class="btn btn-danger btn-sm remove-medicine">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <button type="button" id="add_medicine" class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-plus me-1"></i>
                                                    إضافة دواء
                                                </button>
                                            </div>

                                            <button type="submit" name="add_prescription" class="btn btn-primary">
                                                <i class="fas fa-save me-1"></i>
                                                حفظ الوصفة
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- تبويب التاريخ الطبي -->
                            <div class="tab-pane fade" id="history" role="tabpanel" aria-labelledby="history-tab">
                                <div class="card mt-3">
                                    <div class="card-body">
                                        <h5 class="mb-3">الزيارات السابقة</h5>
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>التاريخ</th>
                                                        <th>الطبيب</th>
                                                        <th>التشخيص</th>
                                                        <th>الإجراء</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach($previous_visits as $prev_visit): ?>
                                                    <tr>
                                                        <td><?php echo date('Y-m-d', strtotime($prev_visit['visit_date'])); ?></td>
                                                        <td><?php echo $prev_visit['doctor_name']; ?></td>
                                                        <td><?php echo $prev_visit['diagnosis']; ?></td>
                                                        <td>
                                                            <a href="?visit_id=<?php echo $prev_visit['id']; ?>" class="btn btn-sm btn-primary">
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
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-calendar-check fa-3x text-muted mb-3"></i>
                                <h5>اختر زيارة من القائمة</h5>
                                <p class="text-muted">يرجى اختيار زيارة لعرض التفاصيل</p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- نافذة البحث السريع -->
    <div class="modal fade" id="quickSearchModal" tabindex="-1" aria-labelledby="quickSearchModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="quickSearchModalLabel">البحث السريع</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="quickSearchInput" class="form-label">البحث عن مريض</label>
                        <input type="text" class="form-control" id="quickSearchInput" placeholder="أدخل اسم المريض أو رقم الملف الطبي">
                    </div>
                    <div id="quickSearchResults" class="list-group">
                        <!-- سيتم عرض نتائج البحث هنا -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // إضافة دواء جديد
        document.getElementById('add_medicine').addEventListener('click', function() {
            const container = document.getElementById('medicines_container');
            const newRow = document.createElement('div');
            newRow.className = 'row mb-2';

            // إنشاء قائمة الأدوية
            const medicineSelect = document.createElement('select');
            medicineSelect.className = 'form-select medicine-select';
            medicineSelect.name = 'medicines[]';

            // إضافة خيار افتراضي
            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = 'اختر دواء';
            medicineSelect.appendChild(defaultOption);

            // إضافة خيارات الأدوية
            <?php foreach($medicines as $medicine): ?>
            const option<?php echo $medicine['id']; ?> = document.createElement('option');
            option<?php echo $medicine['id']; ?>.value = '<?php echo $medicine['id']; ?>';
            option<?php echo $medicine['id']; ?>.textContent = '<?php echo $medicine['name']; ?> - <?php echo number_format($medicine['price'], 2); ?> ريال';
            medicineSelect.appendChild(option<?php echo $medicine['id']; ?>);
            <?php endforeach; ?>

            // إنشاء حقول الإدخال
            const dosageInput = document.createElement('input');
            dosageInput.type = 'text';
            dosageInput.className = 'form-control';
            dosageInput.name = 'dosage[]';
            dosageInput.placeholder = 'الجرعة';

            const durationInput = document.createElement('input');
            durationInput.type = 'text';
            durationInput.className = 'form-control';
            durationInput.name = 'duration[]';
            durationInput.placeholder = 'المدة';

            // إنشاء زر الحذف
            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'btn btn-danger btn-sm remove-medicine';
            deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
            deleteBtn.addEventListener('click', function() {
                newRow.remove();
            });

            // إضافة العناصر إلى الصف
            const col1 = document.createElement('div');
            col1.className = 'col-md-6';
            col1.appendChild(medicineSelect);

            const col2 = document.createElement('div');
            col2.className = 'col-md-2';
            col2.appendChild(dosageInput);

            const col3 = document.createElement('div');
            col3.className = 'col-md-2';
            col3.appendChild(durationInput);

            const col4 = document.createElement('div');
            col4.className = 'col-md-2';
            col4.appendChild(deleteBtn);

            newRow.appendChild(col1);
            newRow.appendChild(col2);
            newRow.appendChild(col3);
            newRow.appendChild(col4);

            container.appendChild(newRow);
        });

        // حذف دواء
        document.querySelectorAll('.remove-medicine').forEach(btn => {
            btn.addEventListener('click', function() {
                this.closest('.row').remove();
            });
        });

        // تحديد/إلغاء تحديد جميع الفحوصات
        function toggleAllLabTests() {
            const selectAll = document.getElementById('selectAllTests');
            const checkboxes = document.querySelectorAll('input[name="lab_tests[]"]');

            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        }
        
        // البحث في قائمة الزيارات
        document.getElementById('searchVisits').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const patientName = row.querySelector('td:first-child').textContent.toLowerCase();
                
                if (patientName.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // مسح البحث
        document.getElementById('clearSearch').addEventListener('click', function() {
            document.getElementById('searchVisits').value = '';
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                row.style.display = '';
            });
        });
        
        // تصفية الزيارات حسب الحالة
        function filterVisitsByStatus(status) {
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const statusBadge = row.querySelector('.badge');
                
                if (status === 'all' || statusBadge.textContent.includes(status)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        // إضافة أزرار التصفية
        const filterButtons = document.createElement('div');
        filterButtons.className = 'btn-group mb-2';
        filterButtons.innerHTML = `
            <button class="btn btn-sm btn-outline-secondary active" onclick="filterVisitsByStatus('all')">الكل</button>
            <button class="btn btn-sm btn-outline-primary" onclick="filterVisitsByStatus('مدفوع')">مدفوع</button>
            <button class="btn btn-sm btn-outline-warning" onclick="filterVisitsByStatus('جاري')">جاري الفحص</button>
            <button class="btn btn-sm btn-outline-info" onclick="filterVisitsByStatus('فحوصات')">فحوصات</button>
            <button class="btn btn-sm btn-outline-success" onclick="filterVisitsByStatus('مكتملة')">مكتملة</button>
        `;
        
        const searchContainer = document.querySelector('.p-3.border-bottom');
        searchContainer.appendChild(filterButtons);
    </script>
</body>
</html>
