
<?php
require_once '../../includes/auth.php';
checkRole(['Admin', 'Reception']);

require_once '../../config/db.php';

// الحصول على قائمة المرضى
$patients = $conn->query("SELECT * FROM patients ORDER BY id DESC")->fetchAll();

// معالجة البحث عن مريض
if(isset($_GET['search'])) {
    $search_term = '%' . $_GET['search'] . '%';
    $patients = $conn->prepare("SELECT * FROM patients WHERE full_name LIKE ? OR medical_record_number LIKE ? OR phone LIKE ? ORDER BY id DESC");
    $patients->execute([$search_term, $search_term, $search_term]);
    $patients = $patients->fetchAll();
}

// الحصول على بيانات مريض محدد
$selected_patient = null;
if(isset($_GET['patient_id'])) {
    $patient_id = $_GET['patient_id'];
    $selected_patient = $conn->query("SELECT * FROM patients WHERE id = $patient_id")->fetch();

    // الحصول على تاريخ الزيارات
    $visits = $conn->query("SELECT v.*, d.full_name as doctor_name 
                            FROM visits v 
                            JOIN doctors d ON v.doctor_id = d.id 
                            WHERE v.patient_id = $patient_id 
                            ORDER BY v.visit_date DESC")->fetchAll();
}

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المرضى</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/dashboard.css" rel="stylesheet">
</head>
<!-- في أعلى ملف patients.php بعد body -->
<?php if(isset($_GET['error'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i>
    <?php 
        echo htmlspecialchars($_GET['error'] == 'duplicate' ? 
            'رقم الملف الطبي مكرر. يرجى المحاولة مرة أخرى.' : 
            'حدث خطأ غير متوقع. يرجى التواصل مع الدعم الفني.'); 
    ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
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
                            <a class="nav-link active" href="patients.php">
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
                    <h1 class="h2">إدارة المرضى</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="dashboard.php" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>
                            تسجيل مريض جديد
                        </a>
                    </div>
                </div>

                <!-- البحث عن مريض -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" placeholder="ابحث عن مريض بالاسم أو رقم الملف أو الهاتف" value="<?php echo isset($_GET['search']) ? $_GET['search'] : ''; ?>">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- قائمة المرضى -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">قائمة المرضى</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>رقم الملف</th>
                                        <th>الاسم الكامل</th>
                                        <th>الرقم الوطني</th>
                                        <th>رقم الهاتف</th>
                                        <th>العمر</th>
                                        <th>الجنس</th>
                                        <th>تاريخ التسجيل</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($patients as $patient): ?>
                                    <tr>
                                        <td><?php echo $patient['medical_record_number']; ?></td>
                                        <td><?php echo $patient['full_name']; ?></td>
                                        <td><?php echo $patient['national_id']; ?></td>
                                        <td><?php echo $patient['phone']; ?></td>
                                        <td><?php echo $patient['age']; ?></td>
                                        <td><?php echo $patient['gender']; ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($patient['registration_date'])); ?></td>
                                        <td>
                                            <a href="?patient_id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye me-1"></i>
                                                عرض
                                            </a>
                                            <a href="dashboard.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-success">
                                                <i class="fas fa-plus me-1"></i>
                                                زيارة جديدة
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <?php if($selected_patient): ?>
                <!-- تفاصيل المريض -->
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">تفاصيل المريض</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">رقم الملف الطبي</label>
                                <p class="form-control-plaintext"><?php echo $selected_patient['medical_record_number']; ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">الاسم الكامل</label>
                                <p class="form-control-plaintext"><?php echo $selected_patient['full_name']; ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">الرقم الوطني</label>
                                <p class="form-control-plaintext"><?php echo $selected_patient['national_id']; ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">رقم الهاتف</label>
                                <p class="form-control-plaintext"><?php echo $selected_patient['phone']; ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">العنوان</label>
                                <p class="form-control-plaintext"><?php echo $selected_patient['address']; ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">العمر</label>
                                <p class="form-control-plaintext"><?php echo $selected_patient['age']; ?> سنة</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">الجنس</label>
                                <p class="form-control-plaintext"><?php echo $selected_patient['gender']; ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">تاريخ التسجيل</label>
                                <p class="form-control-plaintext"><?php echo date('Y-m-d H:i', strtotime($selected_patient['registration_date'])); ?></p>
                            </div>
                        </div>

                        <hr>

                        <h5 class="mb-3">تاريخ الزيارات</h5>

                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>رقم الزيارة</th>
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
                                        <td><?php echo $visit['doctor_name']; ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($visit['visit_date'])); ?></td>
                                        <td>
                                            <?php 
                                            switch($visit['status']) {
                                                case 'Registered':
                                                    echo '<span class="badge bg-warning">مسجل</span>';
                                                    break;
                                                case 'Consultation Paid':
                                                    echo '<span class="badge bg-info">مدفوع الكشف</span>';
                                                    break;
                                                case 'In Consultation':
                                                    echo '<span class="badge bg-primary">في الكشف</span>';
                                                    break;
                                                case 'Lab Payment Pending':
                                                    echo '<span class="badge bg-warning">في انتظار دفع المعمل</span>';
                                                    break;
                                                case 'Lab Paid':
                                                     echo '<span class="badge bg-info">مدفوع المعمل</span>';
                                                    break;
                                                case 'Lab Completed':
                                                    echo '<span class="badge bg-success">مكتمل المعمل</span>';
                                                    break;
                                                case 'Pharmacy Payment Pending':
                                                    echo '<span class="badge bg-warning">في انتظار دفع الصيدلية</span>';
                                                    break;
                                                case 'Pharmacy Paid':
                                                     echo '<span class="badge bg-info">مدفوع الصيدلية</span>';
                                                    break;
                                                case 'Completed':
                                                    echo '<span class="badge bg-success">مكتمل</span>';
                                                    break;
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <a href="visit_details.php?visit_id=<?php echo $visit['id']; ?>" class="btn btn-sm btn-info">
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
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
