
<?php
require_once '../../includes/auth.php';
checkRole(['Admin', 'Doctor']);

require_once '../../config/db.php';

// الحصول على معرف الطبيب الحالي
$doctor_id = $conn->query("SELECT id FROM doctors WHERE full_name = '" . $_SESSION['full_name'] . "'")->fetch()['id'];

// الحصول على قائمة فحوصات المعمل المطلوبة من قبل الطبيب الحالي
$lab_requests = $conn->query("SELECT lr.*, lt.name as test_name, lt.price, p.full_name as patient_name, p.medical_record_number, v.visit_date 
                              FROM lab_requests lr 
                              JOIN lab_tests lt ON lr.lab_test_id = lt.id 
                              JOIN visits v ON lr.visit_id = v.id 
                              JOIN patients p ON v.patient_id = p.id 
                              WHERE v.doctor_id = $doctor_id AND lr.status = 'Pending' 
                              ORDER BY lr.request_date DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فحوصات المعمل المطلوبة</title>
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
                            <a class="nav-link active" href="lab_requests.php">
                                <i class="fas fa-vial me-2"></i>
                                فحوصات المعمل المطلوبة
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../reception/lab_requests.php">
                                <i class="fas fa-list me-2"></i>
                                جميع طلبات الفحوصات
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
                    <h1 class="h2">فحوصات المعمل المطلوبة</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="../reception/lab_requests.php" class="btn btn-primary">
                            <i class="fas fa-money-bill-wave me-1"></i>
                            تأكيد دفع الفحوصات
                        </a>
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
                                            $pending_requests = $conn->query("SELECT COUNT(*) as count FROM lab_requests lr 
                                                                          JOIN visits v ON lr.visit_id = v.id 
                                                                          WHERE v.doctor_id = $doctor_id 
                                                                          AND lr.status = 'Pending'")->fetch()['count'];
                                            echo $pending_requests; 
                                            ?>
                                        </h4>
                                        <p class="mb-0">طلبات معلقة</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-clock fa-2x"></i>
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
                                            $paid_requests = $conn->query("SELECT COUNT(*) as count FROM lab_requests lr 
                                                                        JOIN visits v ON lr.visit_id = v.id 
                                                                        WHERE v.doctor_id = $doctor_id 
                                                                        AND lr.status = 'Paid'")->fetch()['count'];
                                            echo $paid_requests; 
                                            ?>
                                        </h4>
                                        <p class="mb-0">طلبات مدفوعة</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-money-bill-wave fa-2x"></i>
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
                                            $completed_requests = $conn->query("SELECT COUNT(*) as count FROM lab_requests lr 
                                                                             JOIN visits v ON lr.visit_id = v.id 
                                                                             WHERE v.doctor_id = $doctor_id 
                                                                             AND lr.status = 'Completed'")->fetch()['count'];
                                            echo $completed_requests; 
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
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0">
                                            <?php 
                                            $total_requests = $conn->query("SELECT COUNT(*) as count FROM lab_requests lr 
                                                                         JOIN visits v ON lr.visit_id = v.id 
                                                                         WHERE v.doctor_id = $doctor_id")->fetch()['count'];
                                            echo $total_requests; 
                                            ?>
                                        </h4>
                                        <p class="mb-0">إجمالي الطلبات</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-vial fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- فلترة البحث -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <input type="text" class="form-control" id="searchPatient" placeholder="البحث عن مريض...">
                                    <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" id="statusFilter">
                                    <option value="">جميع الحالات</option>
                                    <option value="Pending">في انتظار الدفع</option>
                                    <option value="Paid">تم الدفع</option>
                                    <option value="Completed">تم الإنجاز</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="date" class="form-control" id="dateFilter">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- قائمة الفحوصات المطلوبة -->
                <div class="card">
                    <div class="card-header bg-warning text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-vial me-2"></i>فحوصات المعمل في انتظار الدفع</h5>
                        <span class="badge bg-light text-dark"><?php echo count($lab_requests); ?> فحص</span>
                    </div>
                    <div class="card-body">
                        <?php if(count($lab_requests) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>اسم المريض</th>
                                        <th>رقم الملف</th>
                                        <th>الفحص</th>
                                        <th>السعر</th>
                                        <th>تاريخ الطلب</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($lab_requests as $request): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-primary rounded-circle text-white d-flex justify-content-center align-items-center me-2">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                                <?php echo $request['patient_name']; ?>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-secondary"><?php echo $request['medical_record_number']; ?></span></td>
                                        <td><?php echo $request['test_name']; ?></td>
                                        <td><span class="badge bg-success"><?php echo number_format($request['price'], 2); ?> ريال</span></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($request['request_date'])); ?></td>
                                        <td>
                                            <a href="../reception/lab_payment.php?visit_id=<?php echo $request['visit_id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-money-bill-wave me-1"></i>
                                                تأكيد الدفع
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-clipboard-check fa-3x text-muted mb-3"></i>
                            <h5>لا توجد فحوصات في انتظار الدفع حالياً</h5>
                            <p class="text-muted">جميع فحوصات المعمل تمت معالجتها</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // البحث في قائمة الفحوصات
        document.getElementById('searchPatient').addEventListener('input', function() {
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
            document.getElementById('searchPatient').value = '';
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                row.style.display = '';
            });
        });
        
        // فلترة حسب الحالة
        document.getElementById('statusFilter').addEventListener('change', function() {
            const status = this.value;
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                if (status === '' || row.querySelector('td:nth-child(6) a').textContent.includes(status)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // فلترة حسب التاريخ
        document.getElementById('dateFilter').addEventListener('change', function() {
            const selectedDate = this.value;
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const requestDate = row.querySelector('td:nth-child(5)').textContent;
                const formattedDate = requestDate.substring(0, 10);
                
                if (selectedDate === '' || formattedDate === selectedDate) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
