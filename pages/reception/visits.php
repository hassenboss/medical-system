
<?php
require_once '../../includes/auth.php';
checkRole(['Admin', 'Reception']);

require_once '../../config/db.php';

// الحصول على قائمة الزيارات
$visits = $conn->query("SELECT v.*, p.full_name as patient_name, p.medical_record_number, d.full_name as doctor_name 
                        FROM visits v 
                        JOIN patients p ON v.patient_id = p.id 
                        JOIN doctors d ON v.doctor_id = d.id 
                        ORDER BY v.visit_date DESC")->fetchAll();

// معالجة البحث عن زيارة
if(isset($_GET['search'])) {
    $search_term = '%' . $_GET['search'] . '%';
    $visits = $conn->prepare("SELECT v.*, p.full_name as patient_name, p.medical_record_number, d.full_name as doctor_name 
                            FROM visits v 
                            JOIN patients p ON v.patient_id = p.id 
                            JOIN doctors d ON v.doctor_id = d.id 
                            WHERE p.full_name LIKE ? OR p.medical_record_number LIKE ? OR d.full_name LIKE ? 
                            ORDER BY v.visit_date DESC");
    $visits->execute([$search_term, $search_term, $search_term]);
    $visits = $visits->fetchAll();
}

// الحصول على بيانات زيارة محددة
$selected_visit = null;
if(isset($_GET['visit_id'])) {
    $visit_id = $_GET['visit_id'];
    $selected_visit = $conn->query("SELECT v.*, p.full_name as patient_name, p.medical_record_number, p.age, p.gender, p.phone, p.address, d.full_name as doctor_name, d.consultation_fee 
                                 FROM visits v 
                                 JOIN patients p ON v.patient_id = p.id 
                                 JOIN doctors d ON v.doctor_id = d.id 
                                 WHERE v.id = $visit_id")->fetch();

    // الحصول على تفاصيل الفاتورة
    $invoice = $conn->query("SELECT i.*, ii.item_type, ii.item_id, ii.quantity, ii.unit_price, ii.total_price, s.name as service_name 
                            FROM invoices i 
                            LEFT JOIN invoice_items ii ON i.id = ii.invoice_id 
                            LEFT JOIN services s ON (ii.item_type = 'Service' AND ii.item_id = s.id) 
                            WHERE i.visit_id = $visit_id")->fetchAll();

    // حساب الإجمالي
    $total_amount = 0;
    foreach($invoice as $item) {
        $total_amount += $item['total_price'];
    }

    // الحصول على طلبات الفحوصات
    $lab_requests = $conn->query("SELECT lr.*, lt.name as test_name 
                                 FROM lab_requests lr 
                                 JOIN lab_tests lt ON lr.lab_test_id = lt.id 
                                 WHERE lr.visit_id = $visit_id")->fetchAll();

    // الحصول على الوصفات الطبية
    $prescriptions = $conn->query("SELECT pr.*, m.name as medicine_name 
                                  FROM prescriptions pr 
                                  JOIN medicines m ON pr.medicine_id = m.id 
                                  WHERE pr.visit_id = $visit_id")->fetchAll();
}
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
                            <a class="nav-link" href="invoices.php">
                                <i class="fas fa-file-invoice-dollar me-2"></i>
                                إدارة الفواتير
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
                        <a href="dashboard.php" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>
                            زيارة جديدة
                        </a>
                    </div>
                </div>

                <!-- البحث عن زيارة -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" placeholder="ابحث عن زيارة باسم المريض أو الطبيب" value="<?php echo isset($_GET['search']) ? $_GET['search'] : ''; ?>">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- قائمة الزيارات -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">قائمة الزيارات</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>رقم الزيارة</th>
                                        <th>اسم المريض</th>
                                        <th>رقم الملف</th>
                                        <th>الطبيب</th>
                                        <th>التاريخ</th>
                                        <th>الحالة</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($visits as $visit): ?>
                                    <tr>
                                        <td><?php echo $visit['id']; ?></td>
                                        <td><?php echo $visit['patient_name']; ?></td>
                                        <td><?php echo $visit['medical_record_number']; ?></td>
                                        <td><?php echo $visit['doctor_name']; ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($visit['visit_date'])); ?></td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            switch($visit['status']) {
                                                case 'Registered':
                                                    $status_class = 'bg-warning';
                                                    break;
                                                case 'Consultation Paid':
                                                case 'In Consultation':
                                                    $status_class = 'bg-info';
                                                    break;
                                                case 'Lab Payment Pending':
                                                case 'Lab Paid':
                                                    $status_class = 'bg-primary';
                                                    break;
                                                case 'Lab Completed':
                                                case 'Pharmacy Payment Pending':
                                                case 'Pharmacy Paid':
                                                    $status_class = 'bg-secondary';
                                                    break;
                                                case 'Completed':
                                                    $status_class = 'bg-success';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>">
                                                <?php 
                                                switch($visit['status']) {
                                                    case 'Registered':
                                                        echo 'مسجل';
                                                        break;
                                                    case 'Consultation Paid':
                                                        echo 'مدفوع الكشف';
                                                        break;
                                                    case 'In Consultation':
                                                        echo 'جاري الكشف';
                                                        break;
                                                    case 'Lab Payment Pending':
                                                        echo 'في انتظار دفع المعمل';
                                                        break;
                                                    case 'Lab Paid':
                                                        echo 'مدفوع المعمل';
                                                        break;
                                                    case 'Lab Completed':
                                                        echo 'مكتمل المعمل';
                                                        break;
                                                    case 'Pharmacy Payment Pending':
                                                        echo 'في انتظار دفع الصيدلية';
                                                        break;
                                                    case 'Pharmacy Paid':
                                                        echo 'مدفوع الصيدلية';
                                                        break;
                                                    case 'Completed':
                                                        echo 'مكتمل';
                                                        break;
                                                }
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="?visit_id=<?php echo $visit['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye me-1"></i>
                                                عرض
                                            </a>
                                            <?php if($visit['status'] == 'Registered'): ?>
                                            <a href="payment.php?visit_id=<?php echo $visit['id']; ?>" class="btn btn-sm btn-success">
                                                <i class="fas fa-money-bill me-1"></i>
                                                دفع
                                            </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <?php if($selected_visit): ?>
                <!-- تفاصيل الزيارة -->
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">تفاصيل الزيارة</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">اسم المريض</label>
                                <p class="form-control-plaintext"><?php echo $selected_visit['patient_name']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">رقم الملف الطبي</label>
                                <p class="form-control-plaintext"><?php echo $selected_visit['medical_record_number']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">الطبيب</label>
                                <p class="form-control-plaintext"><?php echo $selected_visit['doctor_name']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">تاريخ الزيارة</label>
                                <p class="form-control-plaintext"><?php echo date('Y-m-d H:i', strtotime($selected_visit['visit_date'])); ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">الحالة</label>
                                <p class="form-control-plaintext">
                                    <?php 
                                    switch($selected_visit['status']) {
                                        case 'Registered':
                                            echo 'مسجل';
                                            break;
                                        case 'Consultation Paid':
                                            echo 'مدفوع الكشف';
                                            break;
                                        case 'In Consultation':
                                            echo 'جاري الكشف';
                                            break;
                                        case 'Lab Payment Pending':
                                            echo 'في انتظار دفع المعمل';
                                            break;
                                        case 'Lab Paid':
                                            echo 'مدفوع المعمل';
                                            break;
                                        case 'Lab Completed':
                                            echo 'مكتمل المعمل';
                                            break;
                                        case 'Pharmacy Payment Pending':
                                            echo 'في انتظار دفع الصيدلية';
                                            break;
                                        case 'Pharmacy Paid':
                                            echo 'مدفوع الصيدلية';
                                            break;
                                        case 'Completed':
                                            echo 'مكتمل';
                                            break;
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>

                        <hr>

                        <h5 class="mb-3">تفاصيل الفاتورة</h5>

                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>الخدمة</th>
                                        <th>الكمية</th>
                                        <th>السعر</th>
                                        <th>الإجمالي</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($invoice as $item): ?>
                                    <tr>
                                        <td>
                                            <?php 
                                            if($item['item_type'] == 'Service' && $item['item_id'] == 0) {
                                                echo 'رسوم الكشف';
                                            } else {
                                                echo $item['service_name'];
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td><?php echo number_format($item['unit_price'], 2); ?> ريال</td>
                                        <td><?php echo number_format($item['total_price'], 2); ?> ريال</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="3" class="text-end">الإجمالي</th>
                                        <th><?php echo number_format($total_amount, 2); ?> ريال</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <?php if(!empty($lab_requests)): ?>
                        <hr>

                        <h5 class="mb-3">فحوصات المعمل</h5>

                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>الفحص</th>
                                        <th>تاريخ الطلب</th>
                                        <th>الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($lab_requests as $request): ?>
                                    <tr>
                                        <td><?php echo $request['test_name']; ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($request['request_date'])); ?></td>
                                        <td>
                                            <?php 
                                            if($request['status'] == 'Pending') {
                                                echo '<span class="badge bg-warning">في الانتظار</span>';
                                            } elseif($request['status'] == 'Paid') {
                                                echo '<span class="badge bg-info">مدفوع</span>';
                                            } elseif($request['status'] == 'Completed') {
                                                echo '<span class="badge bg-success">مكتمل</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>

                        <?php if(!empty($prescriptions)): ?>
                        <hr>

                        <h5 class="mb-3">الوصفات الطبية</h5>

                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>الدواء</th>
                                        <th>الجرعة</th>
                                        <th>المدة</th>
                                        <th>التعليمات</th>
                                        <th>الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($prescriptions as $prescription): ?>
                                    <tr>
                                        <td><?php echo $prescription['medicine_name']; ?></td>
                                        <td><?php echo $prescription['dosage']; ?></td>
                                        <td><?php echo $prescription['duration']; ?></td>
                                        <td><?php echo $prescription['instructions']; ?></td>
                                        <td>
                                            <?php 
                                            if($prescription['status'] == 'Pending') {
                                                echo '<span class="badge bg-warning">في الانتظار</span>';
                                            } elseif($prescription['status'] == 'Paid') {
                                                echo '<span class="badge bg-info">مدفوع</span>';
                                            } elseif($prescription['status'] == 'Dispensed') {
                                                echo '<span class="badge bg-success">تم الصرف</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
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
