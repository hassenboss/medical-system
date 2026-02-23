
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
$visit = $conn->query("SELECT v.*, p.full_name as patient_name, p.medical_record_number, d.full_name as doctor_name, d.consultation_fee 
                      FROM visits v 
                      JOIN patients p ON v.patient_id = p.id 
                      JOIN doctors d ON v.doctor_id = d.id 
                      WHERE v.id = $visit_id")->fetch();

// الحصول على بيانات الفاتورة
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

// معالجة الدفع
if(isset($_POST['process_payment'])) {
    try {
        $conn->beginTransaction();

        // تحديث حالة الفاتورة
        $stmt = $conn->prepare("UPDATE invoices SET payment_status = 'Paid', payment_method = ? WHERE id = ?");
        $stmt->execute([$_POST['payment_method'], $invoice[0]['id']]);

        // إضافة سجل الدفع
        $stmt = $conn->prepare("INSERT INTO payments (invoice_id, amount, payment_method, transaction_number, notes, created_by) 
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $invoice[0]['id'],
            $total_amount,
            $_POST['payment_method'],
            $_POST['transaction_number'],
            $_POST['notes'],
            $_SESSION['user_id']
        ]);

        // تحديث حالة الزيارة
        $stmt = $conn->prepare("UPDATE visits SET status = 'Consultation Paid' WHERE id = ?");
        $stmt->execute([$visit_id]);

        $conn->commit();

        // تسجيل النشاط
        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Process Payment', 'invoices', ?)");
        $logStmt->execute([$_SESSION['user_id'], $invoice[0]['id']]);

        $success = "تمت عملية الدفع بنجاح";

        // إعادة التوجيه إلى صفحة طباعة التذكرة
        header("Location: print_ticket.php?visit_id=$visit_id");
        exit();
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
    <title>معالجة الدفع</title>
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
                    <h1 class="h2">معالجة الدفع</h1>
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

                <!-- تفاصيل الفاتورة -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">تفاصيل الفاتورة</h5>
                    </div>
                    <div class="card-body">
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
                    </div>
                </div>

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
                                    <label for="transaction_number" class="form-label">رقم العملية</label>
                                    <input type="text" class="form-control" id="transaction_number" name="transaction_number">
                                </div>
                                <div class="col-12 mb-3">
                                    <label for="notes" class="form-label">ملاحظات</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <a href="dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-right me-1"></i>
                                    إلغاء
                                </a>
                                <button type="submit" name="process_payment" class="btn btn-success">
                                    <i class="fas fa-check me-1"></i>
                                    تأكيد الدفع
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
