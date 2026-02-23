
<?php
require_once '../../includes/auth.php';
checkRole(['Admin', 'Reception']);

require_once '../../config/db.php';

// الحصول على قائمة الفواتير
$invoices = $conn->query("SELECT i.*, p.full_name as patient_name, p.medical_record_number, v.visit_date 
                           FROM invoices i 
                           LEFT JOIN patients p ON i.patient_id = p.id 
                           LEFT JOIN visits v ON i.visit_id = v.id 
                           ORDER BY i.created_at DESC")->fetchAll();

// معالجة البحث عن فاتورة
if(isset($_GET['search'])) {
    $search_term = '%' . $_GET['search'] . '%';
    $invoices = $conn->prepare("SELECT i.*, p.full_name as patient_name, p.medical_record_number, v.visit_date 
                               FROM invoices i 
                               LEFT JOIN patients p ON i.patient_id = p.id 
                               LEFT JOIN visits v ON i.visit_id = v.id 
                               WHERE i.invoice_number LIKE ? OR p.full_name LIKE ? OR p.medical_record_number LIKE ? 
                               ORDER BY i.created_at DESC");
    $invoices->execute([$search_term, $search_term, $search_term]);
    $invoices = $invoices->fetchAll();
}

// الحصول على بيانات فاتورة محددة
$selected_invoice = null;
if(isset($_GET['invoice_id'])) {
    $invoice_id = $_GET['invoice_id'];
    $selected_invoice = $conn->query("SELECT i.*, p.full_name as patient_name, p.medical_record_number, p.age, p.gender, p.phone, p.address, v.visit_date, d.full_name as doctor_name 
                                    FROM invoices i 
                                    LEFT JOIN patients p ON i.patient_id = p.id 
                                    LEFT JOIN visits v ON i.visit_id = v.id 
                                    LEFT JOIN doctors d ON v.doctor_id = d.id 
                                    WHERE i.id = $invoice_id")->fetch();

    // الحصول على تفاصيل الفاتورة
    $invoice_items = $conn->query("SELECT ii.*, 
                                  CASE 
                                      WHEN ii.item_type = 'Service' THEN (SELECT name FROM services WHERE id = ii.item_id)
                                      WHEN ii.item_type = 'Lab Test' THEN (SELECT name FROM lab_tests WHERE id = ii.item_id)
                                      WHEN ii.item_type = 'Medicine' THEN (SELECT name FROM medicines WHERE id = ii.item_id)
                                  END as item_name
                                  FROM invoice_items ii 
                                  WHERE ii.invoice_id = $invoice_id")->fetchAll();

    // الحصول على المدفوعات
    $payments = $conn->query("SELECT * FROM payments WHERE invoice_id = $invoice_id ORDER BY created_at DESC")->fetchAll();

    // حساب المبلغ المتبقي
    $total_paid = 0;
    foreach($payments as $payment) {
        $total_paid += $payment['amount'];
    }
    $remaining_amount = $selected_invoice['final_amount'] - $total_paid;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الفواتير</title>
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
                    <h1 class="h2">إدارة الفواتير</h1>
                </div>

                <!-- البحث عن فاتورة -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" placeholder="ابحث عن فاتورة بالرقم أو اسم المريض" value="<?php echo isset($_GET['search']) ? $_GET['search'] : ''; ?>">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- قائمة الفواتير -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">قائمة الفواتير</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>رقم الفاتورة</th>
                                        <th>اسم المريض</th>
                                        <th>رقم الملف</th>
                                        <th>تاريخ الزيارة</th>
                                        <th>الإجمالي</th>
                                        <th>حالة الدفع</th>
                                        <th>تاريخ الإنشاء</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($invoices as $invoice): ?>
                                    <tr>
                                        <td><?php echo $invoice['invoice_number']; ?></td>
                                        <td><?php echo $invoice['patient_name']; ?></td>
                                        <td><?php echo $invoice['medical_record_number']; ?></td>
                                        <td>
                                            <?php 
                                            if($invoice['visit_date']) {
                                                echo date('Y-m-d', strtotime($invoice['visit_date']));
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo number_format($invoice['final_amount'], 2); ?> ريال</td>
                                        <td>
                                            <?php 
                                            $status_class = '';
                                            switch($invoice['payment_status']) {
                                                case 'Pending':
                                                    $status_class = 'bg-warning';
                                                    break;
                                                case 'Partial':
                                                    $status_class = 'bg-info';
                                                    break;
                                                case 'Paid':
                                                    $status_class = 'bg-success';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>">
                                                <?php 
                                                switch($invoice['payment_status']) {
                                                    case 'Pending':
                                                        echo 'في انتظار الدفع';
                                                        break;
                                                    case 'Partial':
                                                        echo 'مدفوع جزئياً';
                                                        break;
                                                    case 'Paid':
                                                        echo 'مدفوع بالكامل';
                                                        break;
                                                }
                                                ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('Y-m-d', strtotime($invoice['created_at'])); ?></td>
                                        <td>
                                            <a href="?invoice_id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye me-1"></i>
                                                عرض
                                            </a>
                                            <a href="print_invoice.php?invoice_id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-secondary" target="_blank">
                                                <i class="fas fa-print me-1"></i>
                                                طباعة
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <?php if($selected_invoice): ?>
                <!-- تفاصيل الفاتورة -->
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">تفاصيل الفاتورة</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">رقم الفاتورة</label>
                                <p class="form-control-plaintext"><?php echo $selected_invoice['invoice_number']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">التاريخ</label>
                                <p class="form-control-plaintext"><?php echo date('Y-m-d', strtotime($selected_invoice['created_at'])); ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">اسم المريض</label>
                                <p class="form-control-plaintext"><?php echo $selected_invoice['patient_name']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">رقم الملف الطبي</label>
                                <p class="form-control-plaintext"><?php echo $selected_invoice['medical_record_number']; ?></p>
                            </div>
                            <?php if($selected_invoice['visit_date']): ?>
                            <div class="col-md-6">
                                <label class="form-label">الطبيب</label>
                                <p class="form-control-plaintext"><?php echo $selected_invoice['doctor_name']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">تاريخ الزيارة</label>
                                <p class="form-control-plaintext"><?php echo date('Y-m-d', strtotime($selected_invoice['visit_date'])); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>

                        <hr>

                        <h5 class="mb-3">تفاصيل الخدمات</h5>

                        <div class="table-responsive mb-3">
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
                                    <?php foreach($invoice_items as $item): ?>
                                    <tr>
                                        <td>
                                            <?php 
                                            switch($item['item_type']) {
                                                case 'Service':
                                                    if($item['item_id'] == 0) {
                                                        echo 'رسوم الكشف';
                                                    } else {
                                                        echo $item['item_name'];
                                                    }
                                                    break;
                                                case 'Lab Test':
                                                    echo $item['item_name'];
                                                    break;
                                                case 'Medicine':
                                                    echo $item['item_name'];
                                                    break;
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
                                        <th><?php echo number_format($selected_invoice['final_amount'], 2); ?> ريال</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <hr>

                        <h5 class="mb-3">سجل المدفوعات</h5>

                        <div class="table-responsive mb-3">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>المبلغ</th>
                                        <th>طريقة الدفع</th>
                                        <th>رقم العملية</th>
                                        <th>ملاحظات</th>
                                        <th>التاريخ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($payments as $payment): ?>
                                    <tr>
                                        <td><?php echo number_format($payment['amount'], 2); ?> ريال</td>
                                        <td><?php echo $payment['payment_method']; ?></td>
                                        <td><?php echo $payment['transaction_number']; ?></td>
                                        <td><?php echo $payment['notes']; ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($payment['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="4" class="text-end">المبلغ المدفوع</th>
                                        <th><?php echo number_format($total_paid, 2); ?> ريال</th>
                                    </tr>
                                    <tr>
                                        <th colspan="4" class="text-end">المبلغ المتبقي</th>
                                        <th><?php echo number_format($remaining_amount, 2); ?> ريال</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <?php if($remaining_amount > 0): ?>
                        <div class="d-flex justify-content-end">
                            <a href="payment.php?invoice_id=<?php echo $selected_invoice['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-credit-card me-1"></i>
                                تسجيل دفع
                            </a>
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
