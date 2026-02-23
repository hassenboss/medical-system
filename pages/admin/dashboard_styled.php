<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم الإدارية</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/dashboard.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3498db; /* سماوي */
            --secondary-color: #f39c12; /* برتقالي */
            --accent-color: #e8f4fc; /* سماوي فاتح جداً */
            --light-color: #ffffff; /* أبيض */
            --dark-color: #2c3e50; /* داكن */
            --success-color: #2ecc71; /* أخضر */
            --warning-color: #f1c40f; /* أصفر */
            --danger-color: #e74c3c; /* أحمر */
            --info-color: #1abc9c; /* تركوازي */
        }

        body {
            background-color: var(--accent-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)) !important;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .sidebar {
            background-color: var(--light-color) !important;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.05);
        }

        .nav-link {
            color: var(--dark-color) !important;
            border-radius: 0.5rem;
            margin: 0.2rem 0;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            background-color: var(--accent-color) !important;
            transform: translateX(-5px);
        }

        .nav-link.active {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color)) !important;
            color: var(--light-color) !important;
        }

        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: var(--light-color);
            font-weight: bold;
            border: none;
        }

        .stat-card {
            border-radius: 1rem;
            padding: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(30px, -30px);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-card.primary {
            background: linear-gradient(135deg, var(--primary-color), #5dade2);
        }

        .stat-card.secondary {
            background: linear-gradient(135deg, var(--secondary-color), #f8c471);
        }

        .stat-card.success {
            background: linear-gradient(135deg, var(--success-color), #58d68d);
        }

        .stat-card.info {
            background: linear-gradient(135deg, var(--info-color), #76d7c4);
        }

        .btn {
            border-radius: 0.5rem;
            padding: 0.5rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(to right, var(--primary-color), #5dade2);
            border: none;
        }

        .btn-secondary {
            background: linear-gradient(to right, var(--secondary-color), #f8c471);
            border: none;
        }

        .table {
            border-radius: 0.5rem;
            overflow: hidden;
        }

        .table thead {
            background-color: var(--accent-color);
        }

        .badge {
            padding: 0.5rem 1rem;
            border-radius: 1rem;
            font-weight: 500;
        }

        h1, h2, h3, h4, h5, h6 {
            color: var(--dark-color);
            font-weight: 600;
        }

        .alert {
            border: none;
            border-radius: 0.5rem;
        }

        .form-control, .form-select {
            border-radius: 0.5rem;
            border: 1px solid #ddd;
            padding: 0.75rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
        }

        footer {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: var(--light-color);
            padding: 20px 0;
            margin-top: 30px;
            border-radius: 1rem 1rem 0 0;
        }
    </style>
</head>
<body>
    <!-- شريط التنقل العلوي -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-hospital me-2"></i>
                نظام إدارة المستشفى
            </a>

            <div class="d-flex align-items-center">
                <span class="text-white me-3">
                    <i class="fas fa-user-circle me-1"></i>
                    مدير النظام
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
                            <a class="nav-link" href="doctors.php">
                                <i class="fas fa-user-md me-2"></i>
                                الأطباء
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="patients.php">
                                <i class="fas fa-user-injured me-2"></i>
                                المرضى
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="lab_tests.php">
                                <i class="fas fa-vial me-2"></i>
                                فحوصات المعمل
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="medicines.php">
                                <i class="fas fa-pills me-2"></i>
                                الأدوية
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="services.php">
                                <i class="fas fa-concierge-bell me-2"></i>
                                الخدمات
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="prices.php">
                                <i class="fas fa-tag me-2"></i>
                                الأسعار
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users me-2"></i>
                                المستخدمون
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- المحتوى الرئيسي -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">لوحة التحكم الإدارية</h1>
                </div>

                <!-- بطاقات الإحصائيات -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-4">
                        <div class="stat-card primary text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="mb-0">125</h4>
                                    <p>المرضى المسجلين</p>
                                </div>
                                <div class="fs-1 opacity-50">
                                    <i class="fas fa-user-injured"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="stat-card secondary text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="mb-0">28</h4>
                                    <p>الأطباء</p>
                                </div>
                                <div class="fs-1 opacity-50">
                                    <i class="fas fa-user-md"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="stat-card success text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="mb-0">342</h4>
                                    <p>الزيارات اليوم</p>
                                </div>
                                <div class="fs-1 opacity-50">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="stat-card info text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="mb-0">15</h4>
                                    <p>فحوصات معمل اليوم</p>
                                </div>
                                <div class="fs-1 opacity-50">
                                    <i class="fas fa-vial"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- الرسوم البيانية -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">إحصائيات الزيارات الشهرية</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="visitsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">توزيع الفحوصات</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="testsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- الجدول -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">آخر الزيارات</h5>
                        <a href="#" class="btn btn-sm btn-light">عرض الكل</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>رقم الزيارة</th>
                                        <th>اسم المريض</th>
                                        <th>الطبيب</th>
                                        <th>التاريخ</th>
                                        <th>الحالة</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>#1254</td>
                                        <td>أحمد محمد</td>
                                        <td>د. خالد العتيبي</td>
                                        <td>2023-06-15 10:30</td>
                                        <td><span class="badge bg-success">مكتملة</span></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="#" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="#" class="btn btn-sm btn-secondary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>#1253</td>
                                        <td>فاطمة علي</td>
                                        <td>د. نورة السعيد</td>
                                        <td>2023-06-15 09:45</td>
                                        <td><span class="badge bg-warning">قيد المعالجة</span></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="#" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="#" class="btn btn-sm btn-secondary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>#1252</td>
                                        <td>محمد سعيد</td>
                                        <td>د. عبدالله القحطاني</td>
                                        <td>2023-06-14 16:20</td>
                                        <td><span class="badge bg-info">في انتظار الفحص</span></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="#" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="#" class="btn btn-sm btn-secondary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- تذييل الصفحة -->
    <footer class="text-center py-3">
        <p class="mb-0">جميع الحقوق محفوظة &copy; 2023 نظام إدارة المستشفى</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>    
        // بيانات الرسم البياني للزيارات
        const visitsCtx = document.getElementById('visitsChart').getContext('2d');
        new Chart(visitsCtx, {
            type: 'line',
            data: {
                labels: ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو'],
                datasets: [{
                    label: 'عدد الزيارات',
                    data: [320, 450, 380, 520, 490, 610],
                    backgroundColor: 'rgba(52, 152, 219, 0.2)',
                    borderColor: 'rgba(52, 152, 219, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // بيانات الرسم البياني للفحوصات
        const testsCtx = document.getElementById('testsChart').getContext('2d');
        new Chart(testsCtx, {
            type: 'doughnut',
            data: {
                labels: ['دم', 'بول', 'أشعة', 'أخرى'],
                datasets: [{
                    data: [35, 25, 20, 20],
                    backgroundColor: [
                        'rgba(52, 152, 219, 0.8)',
                        'rgba(243, 156, 18, 0.8)',
                        'rgba(46, 204, 113, 0.8)',
                        'rgba(155, 89, 182, 0.8)'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>