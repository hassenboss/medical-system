
<?php
require_once '../../includes/auth.php';
checkRole(['Admin', 'Reception']);

require_once '../../config/db.php';

// التحقق من وجود معرف الزيارة
if(!isset($_GET['visit_id'])) {
    header('Location: dashboard.php');
    exit();
}

$visit_id = $_GET['visit_id'];

// الحصول على بيانات الزيارة
$visit = $conn->query("SELECT v.*, p.full_name as patient_name, p.medical_record_number, d.full_name as doctor_name 
                      FROM visits v 
                      JOIN patients p ON v.patient_id = p.id 
                      JOIN doctors d ON v.doctor_id = d.id 
                      WHERE v.id = $visit_id")->fetch();

// الحصول على طلبات الفحوصات
$lab_requests = $conn->query("SELECT lr.*, lt.name as test_name, lt.price
                              FROM lab_requests lr
                              JOIN lab_tests lt ON lr.lab_test_id = lt.id
                              WHERE lr.visit_id = $visit_id
                              ORDER BY lr.request_date DESC")->fetchAll();
                              
// التحقق مما إذا كانت جميع الفحوصات مدفوعة
$all_paid = true;
foreach($lab_requests as $request) {
    if($request['status'] == 'Pending') {
        $all_paid = false;
        break;
    }
}

// معالجة الدفع
if(isset($_POST['process_payment'])) {
    try {
        $conn->beginTransaction();

        // حساب إجمالي تكلفة الفحوصات
        $total_amount = 0;
        foreach($lab_requests as $request) {
            if($request['status'] == 'Pending') {
                $total_amount += $request['price'];
            }
        }

        // إنشاء فاتورة للفحوصات
        $invoice_number = 'LAB-' . date('Y-m-d') . '-' . str_pad($visit_id, 4, '0', STR_PAD_LEFT);

        $stmt = $conn->prepare("INSERT INTO invoices (invoice_number, patient_id, visit_id, total_amount, final_amount, payment_status, created_by) 
                               VALUES (?, ?, ?, ?, ?, 'Paid', ?)");
        $stmt->execute([
            $invoice_number,
            $visit['patient_id'],
            $visit_id,
            $total_amount,
            $total_amount,
            $_SESSION['user_id']
        ]);

        $invoice_id = $conn->lastInsertId();

        // إضافة تفاصيل الفاتورة وتحديث حالة الطلبات
        foreach($lab_requests as $request) {
            if($request['status'] == 'Pending') {
                $stmt = $conn->prepare("INSERT INTO invoice_items (invoice_id, item_type, item_id, quantity, unit_price, total_price) 
                                       VALUES (?, 'Lab Test', ?, 1, ?, ?)");
                $stmt->execute([$invoice_id, $request['lab_test_id'], $request['price'], $request['price']]);

                // تحديث حالة طلب الفحص
                $stmt = $conn->prepare("UPDATE lab_requests SET status = 'Paid' WHERE id = ?");
                $stmt->execute([$request['id']]);
            }
        }

        // إضافة سجل الدفع
        $stmt = $conn->prepare("INSERT INTO payments (invoice_id, amount, payment_method, transaction_number, notes, created_by) 
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $invoice_id,
            $total_amount,
            $_POST['payment_method'],
            $_POST['transaction_number'],
            $_POST['notes'],
            $_SESSION['user_id']
        ]);

        // تحديث حالة الزيارة
        $stmt = $conn->prepare("UPDATE visits SET status = 'Lab Paid' WHERE id = ?");
        $stmt->execute([$visit_id]);

        $conn->commit();

        // تسجيل النشاط
        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Process Lab Payment', 'visits', ?)");
        $logStmt->execute([$_SESSION['user_id'], $visit_id]);

        $success = "تمت عملية دفع الفحوصات بنجاح";

        // إعادة تحميل البيانات
        $lab_requests = $conn->query("SELECT lr.*, lt.name as test_name, lt.price 
                                      FROM lab_requests lr 
                                      JOIN lab_tests lt ON lr.lab_test_id = lt.id 
                                      WHERE lr.visit_id = $visit_id AND lr.status = 'Paid'")->fetchAll();
    } catch(PDOException $e) {
        $conn->rollBack();
        $error = "حدث خطأ: " . $e->getMessage();
    }
}
?>

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

                <?php if($lab_requests[0]['status'] == 'Pending'): ?>
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
                                <button type="submit" name="process_payment" class="btn btn-success">
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
