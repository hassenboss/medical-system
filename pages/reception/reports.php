
<?php
require_once '../../includes/auth.php';
checkRole(['Admin', 'Reception']);

require_once '../../config/db.php';

// الحصول على تاريخ اليوم
$today = date('Y-m-d');

// الحصول على تاريخ بداية ونهاية الشهر
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

// الحصول على إحصائيات اليوم
$today_visits = $conn->query("SELECT COUNT(*) as count FROM visits WHERE DATE(visit_date) = '$today'")->fetch()['count'];
$today_income = $conn->query("SELECT SUM(final_amount) as total FROM invoices WHERE DATE(created_at) = '$today' AND payment_status = 'Paid'")->fetch()['total'];

// الحصول على إحصائيات الشهر
$month_visits = $conn->query("SELECT COUNT(*) as count FROM visits WHERE DATE(visit_date) BETWEEN '$month_start' AND '$month_end'")->fetch()['count'];
$month_income = $conn->query("SELECT SUM(final_amount) as total FROM invoices WHERE DATE(created_at) BETWEEN '$month_start' AND '$month_end' AND payment_status = 'Paid'")->fetch()['total'];

// الحصول على إحصائيات الأطباء
$doctor_stats = $conn->query("SELECT d.full_name, COUNT(v.id) as visits_count 
                              FROM doctors d 
                              LEFT JOIN visits v ON d.id = v.doctor_id 
                              WHERE DATE(v.visit_date) BETWEEN '$month_start' AND '$month_end' 
                              GROUP BY d.id 
                              ORDER BY visits_count DESC")->fetchAll();

// الحصول على إحصائيات الخدمات
$service_stats = $conn->query("SELECT s.name, COUNT(ii.id) as usage_count 
                               FROM services s 
                               LEFT JOIN invoice_items ii ON (s.id = ii.item_id AND ii.item_type = 'Service') 
                               LEFT JOIN invoices i ON ii.invoice_id = i.id 
                               WHERE DATE(i.created_at) BETWEEN '$month_start' AND '$month_end' 
                               GROUP BY s.id 
                               ORDER BY usage_count DESC")->fetchAll();

// الحصول على إحصائيات طرق الدفع
$payment_stats = $conn->query("SELECT payment_method, SUM(amount) as total 
                                FROM payments 
                                WHERE DATE(created_at) BETWEEN '$month_start' AND '$month_end' 
                                GROUP BY payment_method")->fetchAll();

// الحصول على الأدوية الناقصة
$low_medicines = $conn->query("SELECT * FROM medicines WHERE quantity <= min_quantity AND is_active = 1")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>التقارير</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js" rel="stylesheet">
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
                            <a class="nav-link active" href="reports.php">
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
                    <h1 class="h2">التقارير</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary" onclick="window.print()">
                            <i class="fas fa-print me-1"></i>
                            طباعة التقرير
                        </button>
                    </div>
                </div>

                <!-- إحصائيات اليوم -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            زيارات اليوم
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $today_visits; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calendar-day fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            دخل اليوم
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($today_income, 2); ?> ريال
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            زيارات الشهر
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $month_visits; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calendar fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            دخل الشهر
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($month_income, 2); ?> ريال
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- إحصائيات الأطباء -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">إحصائيات الأطباء</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>اسم الطبيب</th>
                                        <th>عدد الزيارات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($doctor_stats as $stat): ?>
                                    <tr>
                                        <td><?php echo $stat['full_name']; ?></td>
                                        <td><?php echo $stat['visits_count']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- إحصائيات الخدمات -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">إحصائيات الخدمات</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>اسم الخدمة</th>
                                        <th>عدد مرات الاستخدام</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($service_stats as $stat): ?>
                                    <tr>
                                        <td><?php echo $stat['name']; ?></td>
                                        <td><?php echo $stat['usage_count']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- إحصائيات طرق الدفع -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">إحصائيات طرق الدفع</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>طريقة الدفع</th>
                                        <th>المبلغ الإجمالي</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($payment_stats as $stat): ?>
                                    <tr>
                                        <td><?php echo $stat['payment_method']; ?></td>
                                        <td><?php echo number_format($stat['total'], 2); ?> ريال</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- الأدوية الناقصة -->
                <div class="card">
                    <div class="card-header bg-warning text-white">
                        <h5 class="mb-0">الأدوية الناقصة</h5>
                    </div>
                    <div class="card-body">
                        <?php if(count($low_medicines) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>اسم الدواء</th>
                                        <th>الكمية الحالية</th>
                                        <th>الحد الأدنى</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($low_medicines as $medicine): ?>
                                    <tr>
                                        <td><?php echo $medicine['name']; ?></td>
                                        <td><?php echo $medicine['quantity']; ?></td>
                                        <td><?php echo $medicine['min_quantity']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-success">
                            جميع الأدوية متوفرة بالكميات المطلوبة
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
</body>
</html>
