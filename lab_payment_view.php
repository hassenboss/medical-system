<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دفع فحوصات المعمل</title>
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
                            <a class="nav-link" href="visits.php">
                                <i class="fas fa-calendar-check me-2"></i>
                                إدارة الزيارات
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="invoices.php">
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
                    <h1 class="h2">دفع فحوصات المعمل</h1>
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

                <!-- معلومات المريض والزيارة -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">معلومات الزيارة</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">اسم المريض</label>
                                <p class="form-control-plaintext"><?php echo $visit['patient_name']; ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">رقم الملف الطبي</label>
                                <p class="form-control-plaintext"><?php echo $visit['medical_record_number']; ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">الطبيب</label>
                                <p class="form-control-plaintext"><?php echo $visit['doctor_name']; ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">تاريخ الزيارة</label>
                                <p class="form-control-plaintext"><?php echo date('Y-m-d H:i', strtotime($visit['visit_date'])); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- قائمة الفحوصات المطلوبة -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">الفحوصات المطلوبة</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>اسم الفحص</th>
                                        <th>السعر</th>
                                        <th>الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_amount = 0;
                                    foreach($lab_requests as $request): 
                                    $total_amount += $request['price'];
                                    ?>
                                    <tr>
                                        <td><?php echo $request['test_name']; ?></td>
                                        <td><?php echo number_format($request['price'], 2); ?> ريال</td>
                                        <td>
                                            <?php
                                            $statusBadge = '';
                                            $statusText = '';
                                            switch($request['status']) {
                                                case 'Pending':
                                                    $statusBadge = 'badge-warning';
                                                    $statusText = 'في انتظار الدفع';
                                                    break;
                                                case 'Paid':
                                                    $statusBadge = 'badge-success';
                                                    $statusText = 'تم الدفع';
                                                    break;
                                                case 'Completed':
                                                    $statusBadge = 'badge-info';
                                                    $statusText = 'تم الإنجاز';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $statusBadge; ?>"><?php echo $statusText; ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th class="text-end">الإجمالي</th>
                                        <th><?php echo number_format($total_amount, 2); ?> ريال</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <?php if(!$all_paid): ?>
                <!-- نموذج الدفع -->
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">معالجة الدفع</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="payment_method" class="form-label">طريقة الدفع</label>
                                    <select class="form-select" id="payment_method" name="payment_method" required>
                                        <option value="نقدي">نقدي</option>
                                        <option value="تحويل">تحويل بنكي</option>
                                        <option value="بنك كاش">بنك كاش</option>
                                        <option value="بطاقة ائتمان">بطاقة ائتمان</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="transaction_number" class="form-label">رقم العملية (اختياري)</label>
                                    <input type="text" class="form-control" id="transaction_number" name="transaction_number">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="notes" class="form-label">ملاحظات</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="submit" name="process_payment" class="btn btn-success" onclick="return confirm('هل أنت متأكد من تأكيد دفع هذه الفحوصات؟');">
                                    <i class="fas fa-check-circle me-1"></i>
                                    تأكيد الدفع
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                <!-- تأكيد الدفع -->
                <div class="card">
                    <div class="card-body text-center">
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle fa-3x mb-3"></i>
                            <h4>تم دفع الفحوصات بنجاح</h4>
                            <p>يمكن للمريض الآن التوجه إلى المعمل لإجراء الفحوصات</p>
                        </div>
                        <div class="d-flex justify-content-center">
                            <a href="visits.php" class="btn btn-primary">
                                <i class="fas fa-arrow-right me-1"></i>
                                العودة إلى قائمة الزيارات
                            </a>
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