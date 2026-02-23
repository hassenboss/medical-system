
<?php
require_once '../../includes/auth.php';
checkRole(['Admin', 'Reception']);

require_once '../../config/db.php';

// الحصول على قائمة طلبات الفحوصات في انتظار الدفع
$lab_requests = $conn->query("SELECT lr.*, lt.name as test_name, lt.price, v.visit_date, p.full_name as patient_name, p.medical_record_number, d.full_name as doctor_name 
                              FROM lab_requests lr 
                              JOIN lab_tests lt ON lr.lab_test_id = lt.id 
                              JOIN visits v ON lr.visit_id = v.id 
                              JOIN patients p ON v.patient_id = p.id 
                              JOIN doctors d ON v.doctor_id = d.id 
                              WHERE lr.status = 'Pending' 
                              ORDER BY lr.request_date DESC")->fetchAll();

// معالجة الدفع
if(isset($_POST['process_payment'])) {
    try {
        $conn->beginTransaction();

        // حساب إجمالي تكلفة الفحوصات
        $total_amount = 0;
        $lab_request_ids = explode(',', $_POST['lab_request_ids']);
        foreach($lab_request_ids as $request_id) {
            $request = $conn->query("SELECT lr.*, lt.price FROM lab_requests lr JOIN lab_tests lt ON lr.lab_test_id = lt.id WHERE lr.id = $request_id")->fetch();
            $total_amount += $request['price'];
        }

        // إنشاء فاتورة للفحوصات
        $lab_ids = explode(',', $_POST['lab_request_ids']);
        $first_request = $conn->query("SELECT * FROM lab_requests WHERE id = " . $lab_ids[0])->fetch();
        $visit_id = $first_request['visit_id'];
        $visit = $conn->query("SELECT * FROM visits WHERE id = $visit_id")->fetch();

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
        foreach($lab_request_ids as $request_id) {
            $request = $conn->query("SELECT lr.*, lt.price FROM lab_requests lr JOIN lab_tests lt ON lr.lab_test_id = lt.id WHERE lr.id = $request_id")->fetch();

            $stmt = $conn->prepare("INSERT INTO invoice_items (invoice_id, item_type, item_id, quantity, unit_price, total_price) 
                                   VALUES (?, 'Lab Test', ?, 1, ?, ?)");
            $stmt->execute([$invoice_id, $request['lab_test_id'], $request['price'], $request['price']]);

            // تحديث حالة طلب الفحص
            $stmt = $conn->prepare("UPDATE lab_requests SET status = 'Paid' WHERE id = ?");
            $stmt->execute([$request_id]);
            
            // تسجيل تحديث حالة الفحص
            $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Update Lab Request Status', 'lab_requests', ?)");
            $logStmt->execute([$_SESSION['user_id'], $request_id]);
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

        // إعادة تحميل قائمة الطلبات
        $lab_requests = $conn->query("SELECT lr.*, lt.name as test_name, lt.price, v.visit_date, p.full_name as patient_name, p.medical_record_number, d.full_name as doctor_name 
                                      FROM lab_requests lr 
                                      JOIN lab_tests lt ON lr.lab_test_id = lt.id 
                                      JOIN visits v ON lr.visit_id = v.id 
                                      JOIN patients p ON v.patient_id = p.id 
                                      JOIN doctors d ON v.doctor_id = d.id 
                                      WHERE lr.status = 'Pending' 
                                      ORDER BY lr.request_date DESC")->fetchAll();
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
    <title>طلبات فحوصات المعمل</title>
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
                    <h1 class="h2">طلبات فحوصات المعمل</h1>
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

                <!-- قائمة طلبات الفحوصات -->
                <!-- إحصائيات سريعة -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0">
                                            <?php 
                                            $pending_requests = $conn->query("SELECT COUNT(*) as count FROM lab_requests WHERE status = 'Pending'")->fetch()['count'];
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
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0">
                                            <?php 
                                            $paid_requests = $conn->query("SELECT COUNT(*) as count FROM lab_requests WHERE status = 'Paid'")->fetch()['count'];
                                            echo $paid_requests; 
                                            ?>
                                        </h4>
                                        <p class="mb-0">طلبات مدفوعة</p>
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
                                            $completed_requests = $conn->query("SELECT COUNT(*) as count FROM lab_requests WHERE status = 'Completed'")->fetch()['count'];
                                            echo $completed_requests; 
                                            ?>
                                        </h4>
                                        <p class="mb-0">فحوصات مكتملة</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-clipboard-check fa-2x"></i>
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
                                            $total_revenue = $conn->query("SELECT SUM(price) as total FROM lab_requests lr JOIN lab_tests lt ON lr.lab_test_id = lt.id WHERE lr.status = 'Paid'")->fetch()['total'];
                                            echo number_format($total_revenue, 0); 
                                            ?>
                                        </h4>
                                        <p class="mb-0">إجمالي الإيرادات</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-money-bill-wave fa-2x"></i>
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

                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-vial me-2"></i>طلبات الفحوصات</h5>
                        <span class="badge bg-light text-dark"><?php echo count($lab_requests); ?> طلب</span>
                    </div>
                    <div class="card-body">
                        <?php if(count($lab_requests) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th><input type="checkbox" id="selectAll" onchange="toggleAllCheckboxes()"></th>
                                        <th>اسم المريض</th>
                                        <th>رقم الملف</th>
                                        <th>الفحص</th>
                                        <th>السعر</th>
                                        <th>الطبيب</th>
                                        <th>تاريخ الطلب</th>
                                        <th>الحالة</th>
                                        <th>إجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($lab_requests as $request): ?>
                                    <tr>
                                        <td><input type="checkbox" name="lab_request_ids[]" value="<?php echo $request['id']; ?>" class="lab-request-checkbox"></td>
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
                                        <td><?php echo $request['doctor_name']; ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($request['request_date'])); ?></td>
                                        <td>
                                            <select class="form-select form-select-sm status-select" data-request-id="<?php echo $request['id']; ?>" data-original-status="<?php echo $request['status']; ?>" onchange="updateLabRequestStatus(this)">
                                                <option value="Pending" <?php echo $request['status'] == 'Pending' ? 'selected' : ''; ?>>في انتظار الدفع</option>
                                                <option value="Paid" <?php echo $request['status'] == 'Paid' ? 'selected' : ''; ?>>تم الدفع</option>
                                                <option value="Completed" <?php echo $request['status'] == 'Completed' ? 'selected' : ''; ?>>تم الإنجاز</option>
                                            </select>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-info" onclick="showLabRequestDetails(<?php echo $request['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-clipboard-check fa-3x text-muted mb-3"></i>
                            <h5>لا توجد طلبات فحوصات</h5>
                            <p class="text-muted">جميع طلبات الفحوصات تمت معالجتها</p>
                        </div>
                        <?php endif; ?>

                        <?php if(count($lab_requests) > 0): ?>
                        <div class="mt-3">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#paymentModal" disabled>
                                <i class="fas fa-money-bill-wave me-1"></i>
                                معالجة الدفع للطلبات المحددة
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- نموذج الدفع -->
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="paymentModalLabel">معالجة الدفع</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" onsubmit="return validatePaymentForm()">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="payment_method" class="form-label">طريقة الدفع</label>
                            <select class="form-select" id="payment_method" name="payment_method" required>
                                <option value="نقدي">نقدي</option>
                                <option value="تحويل">تحويل بنكي</option>
                                <option value="بنك كاش">بنك كاش</option>
                                <option value="بطاقة ائتمان">بطاقة ائتمان</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="transaction_number" class="form-label">رقم العملية</label>
                            <input type="text" class="form-control" id="transaction_number" name="transaction_number">
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label">ملاحظات</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>
                        <input type="hidden" name="lab_request_ids" id="selectedLabRequests">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-success">تأكيد الدفع</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleAllCheckboxes() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.lab-request-checkbox');

            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });

            updateSelectedRequests();
        }

        function updateSelectedRequests() {
            const checkboxes = document.querySelectorAll('.lab-request-checkbox:checked');
            const selectedRequests = Array.from(checkboxes).map(cb => cb.value);
            document.getElementById('selectedLabRequests').value = selectedRequests.join(',');
            
            // تحديث نص زر الدفع بناءً على عدد الطلبات المحددة
            const paymentBtn = document.querySelector('button[data-bs-target="#paymentModal"]');
            if (paymentBtn) {
                if (selectedRequests.length > 0) {
                    paymentBtn.innerHTML = `<i class="fas fa-money-bill-wave me-1"></i> معالجة الدفع (${selectedRequests.length} طلب)`;
                    paymentBtn.disabled = false;
                } else {
                    paymentBtn.innerHTML = `<i class="fas fa-money-bill-wave me-1"></i> معالجة الدفع للطلبات المحددة`;
                    paymentBtn.disabled = true;
                }
            }
        }

        // إضافة مستمعي الأحداث لجميع مربعات الاختيار
        document.querySelectorAll('.lab-request-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectedRequests);
        });

        // عرض تفاصيل طلب الفحص
        function showLabRequestDetails(requestId) {
            // في تطبيق حقيقي، يمكن جلب البيانات عبر AJAX
            // هنا سنعرض رسالة توضيحية
            alert('عرض تفاصيل طلب الفحص رقم: ' + requestId);
        }
        
        // تحديث حالة طلب الفحص
        function updateLabRequestStatus(selectElement) {
            const requestId = selectElement.getAttribute('data-request-id');
            const newStatus = selectElement.value;
            
            if(confirm('هل أنت متأكد من تغيير حالة الفحص إلى "' + selectElement.options[selectElement.selectedIndex].text + '"؟')) {
                // إرسال طلب AJAX لتحديث الحالة
                fetch('update_lab_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'request_id=' + requestId + '&status=' + newStatus
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        alert('تم تحديث حالة الفحص بنجاح');
                        // تحديث الصفحة لعرض البيانات المحدثة
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        alert('حدث خطأ: ' + data.message);
                        // إعادة القائمة المنسدلة إلى قيمتها الأصلية
                        selectElement.value = selectElement.getAttribute('data-original-status');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('حدث خطأ أثناء تحديث حالة الفحص');
                    // إعادة القائمة المنسدلة إلى قيمتها الأصلية
                    selectElement.value = selectElement.getAttribute('data-original-status');
                });
            } else {
                // إعادة القائمة المنسدلة إلى قيمتها الأصلية إذا ألغى المستخدم
                selectElement.value = selectElement.getAttribute('data-original-status');
            }
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
