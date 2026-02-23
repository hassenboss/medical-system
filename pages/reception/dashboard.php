
<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
checkRole(['Admin', 'Reception']);

require_once '../../config/db.php';

// الحصول على قائمة المرضى
$patients = $conn->query("SELECT * FROM patients ORDER BY id DESC")->fetchAll();

// الحصول على قائمة الأطباء
$doctors = $conn->query("SELECT * FROM doctors WHERE is_active = 1")->fetchAll();

// الحصول على قائمة الخدمات
$services = $conn->query("SELECT * FROM services WHERE is_active = 1")->fetchAll();

// في بداية الملف


// ... داخل معالجة إضافة مريض جديد ...

if(isset($_POST['add_patient'])) {
    try {
        $conn->beginTransaction();

        // ✅ استخدام الدالة الآمنة لتوليد الرقم
        $medical_record_number = generateUniqueMedicalRecord($conn);

        $stmt = $conn->prepare("
            INSERT INTO patients (
                medical_record_number, full_name, national_id, 
                phone, address, age, gender, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $medical_record_number,
            $_POST['full_name'],
            $_POST['national_id'] ?? null,
            $_POST['phone'] ?? null,
            $_POST['address'] ?? null,
            $_POST['age'] ?? null,
            $_POST['gender'],
            $_SESSION['user_id']
        ]);

        $patient_id = $conn->lastInsertId();

        // تسجيل النشاط
        $logStmt = $conn->prepare("
            INSERT INTO activity_logs (user_id, action, table_name, record_id) 
            VALUES (?, 'Add Patient', 'patients', ?)
        ");
        $logStmt->execute([$_SESSION['user_id'], $patient_id]);

        $conn->commit();
        
        $success = "تم تسجيل المريض بنجاح. رقم الملف: $medical_record_number";
        
        // إعادة التوجيه أو تحديث القائمة
        header("Location: patients.php?patient_id=$patient_id&success=1");
        exit();
        
    } catch(PDOException $e) {
        $conn->rollBack();
        
        // ✅ معالجة خطأ التكرار بشكل خاص
        if ($e->getCode() == 23000) {
            $error = "رقم الملف الطبي مكرر بالفعل. يرجى المحاولة مرة أخرى.";
        } else {
            $error = "حدث خطأ في قاعدة البيانات: " . $e->getMessage();
        }
        
        // تسجيل الخطأ في الـ log للتحقق لاحقاً
        error_log("Patient Registration Error: " . $e->getMessage());
    }
}

// معالجة إنشاء زيارة جديدة
if(isset($_POST['create_visit'])) {
    try {
        $conn->beginTransaction();

        // إنشاء زيارة جديدة
        $stmt = $conn->prepare("INSERT INTO visits (patient_id, doctor_id, status, created_by) 
                               VALUES (?, ?, 'Registered', ?)");
        $stmt->execute([
            $_POST['patient_id'],
            $_POST['doctor_id'],
            $_SESSION['user_id']
        ]);

        $visit_id = $conn->lastInsertId();

        // حساب إجمالي التكلفة
        $total_amount = 0;
        if(!empty($_POST['services'])) {
            foreach($_POST['services'] as $service_id) {
                $service = $conn->query("SELECT * FROM services WHERE id = $service_id")->fetch();
                $total_amount += $service['price'];
            }
        }

        // إضافة رسوم الكشف
        $doctor = $conn->query("SELECT * FROM doctors WHERE id = " . $_POST['doctor_id'])->fetch();
        $total_amount += $doctor['consultation_fee'];

        // إنشاء فاتورة
        $invoice_number = 'INV-' . date('Y-m-d') . '-' . str_pad($visit_id, 4, '0', STR_PAD_LEFT);

        $stmt = $conn->prepare("INSERT INTO invoices (invoice_number, patient_id, visit_id, total_amount, final_amount, payment_status, created_by) 
                               VALUES (?, ?, ?, ?, ?, 'Pending', ?)");
        $stmt->execute([
            $invoice_number,
            $_POST['patient_id'],
            $visit_id,
            $total_amount,
            $total_amount,
            $_SESSION['user_id']
        ]);

        $invoice_id = $conn->lastInsertId();

        // إضافة تفاصيل الفاتورة
        // رسوم الكشف
        $stmt = $conn->prepare("INSERT INTO invoice_items (invoice_id, item_type, item_id, quantity, unit_price, total_price) 
                               VALUES (?, 'Service', ?, 1, ?, ?)");
        $stmt->execute([$invoice_id, 0, $doctor['consultation_fee'], $doctor['consultation_fee']]);

        // الخدمات المطلوبة
        if(!empty($_POST['services'])) {
            foreach($_POST['services'] as $service_id) {
                $service = $conn->query("SELECT * FROM services WHERE id = $service_id")->fetch();
                $stmt = $conn->prepare("INSERT INTO invoice_items (invoice_id, item_type, item_id, quantity, unit_price, total_price) 
                                       VALUES (?, 'Service', ?, 1, ?, ?)");
                $stmt->execute([$invoice_id, $service_id, $service['price'], $service['price']]);
            }
        }

        $conn->commit();

        // تسجيل النشاط
        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Create Visit', 'visits', ?)");
        $logStmt->execute([$_SESSION['user_id'], $visit_id]);

        $success = "تم إنشاء الزيارة بنجاح";

        // إعادة التوجيه إلى صفحة الدفع
        header("Location: payment.php?visit_id=$visit_id");
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
    <title>لوحة تحكم الاستقبال</title>
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
                            <a class="nav-link active" href="#">
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
                            <a class="nav-link" href="lab_requests.php">
                                <i class="fas fa-vial me-2"></i>
                                طلبات فحوصات المعمل
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
                    <h1 class="h2">لوحة تحكم الاستقبال</h1>
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

                <!-- علامات التبويب -->
                <ul class="nav nav-tabs" id="receptionTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="new-patient-tab" data-bs-toggle="tab" data-bs-target="#new-patient" type="button" role="tab" aria-controls="new-patient" aria-selected="true">
                            <i class="fas fa-user-plus me-1"></i>
                            تسجيل مريض جديد
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="new-visit-tab" data-bs-toggle="tab" data-bs-target="#new-visit" type="button" role="tab" aria-controls="new-visit" aria-selected="false">
                            <i class="fas fa-calendar-plus me-1"></i>
                            إنشاء زيارة جديدة
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="search-patient-tab" data-bs-toggle="tab" data-bs-target="#search-patient" type="button" role="tab" aria-controls="search-patient" aria-selected="false">
                            <i class="fas fa-search me-1"></i>
                            البحث عن مريض
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="receptionTabsContent">
                    <!-- تبويب تسجيل مريض جديد -->
                    <div class="tab-pane fade show active" id="new-patient" role="tabpanel" aria-labelledby="new-patient-tab">
                        <div class="card mt-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">تسجيل مريض جديد</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="full_name" class="form-label">الاسم الكامل</label>
                                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="national_id" class="form-label">الرقم الوطني</label>
                                            <input type="text" class="form-control" id="national_id" name="national_id">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="phone" class="form-label">رقم الهاتف</label>
                                            <input type="text" class="form-control" id="phone" name="phone">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="age" class="form-label">العمر</label>
                                            <input type="number" class="form-control" id="age" name="age">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="gender" class="form-label">الجنس</label>
                                            <select class="form-select" id="gender" name="gender" required>
                                                <option value="">اختر...</option>
                                                <option value="ذكر">ذكر</option>
                                                <option value="أنثى">أنثى</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="address" class="form-label">العنوان</label>
                                            <input type="text" class="form-control" id="address" name="address">
                                        </div>
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" name="add_patient" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i>
                                            حفظ بيانات المريض
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- تبويب إنشاء زيارة جديدة -->
                    <div class="tab-pane fade" id="new-visit" role="tabpanel" aria-labelledby="new-visit-tab">
                        <div class="card mt-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">إنشاء زيارة جديدة</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="patient_id" class="form-label">المريض</label>
                                            <select class="form-select" id="patient_id" name="patient_id" required>
                                                <option value="">اختر مريض...</option>
                                                <?php foreach($patients as $patient): ?>
                                                <option value="<?php echo $patient['id']; ?>">
                                                    <?php echo $patient['full_name']; ?> (<?php echo $patient['medical_record_number']; ?>)
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="doctor_id" class="form-label">الطبيب</label>
                                            <select class="form-select" id="doctor_id" name="doctor_id" required>
                                                <option value="">اختر طبيب...</option>
                                                <?php foreach($doctors as $doctor): ?>
                                                <option value="<?php echo $doctor['id']; ?>">
                                                    <?php echo $doctor['full_name']; ?> (<?php echo $doctor['specialization']; ?>)
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">الخدمات المطلوبة</label>
                                        <div class="row">
                                            <?php foreach($services as $service): ?>
                                            <div class="col-md-4 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" value="<?php echo $service['id']; ?>" id="service_<?php echo $service['id']; ?>" name="services[]">
                                                    <label class="form-check-label" for="service_<?php echo $service['id']; ?>">
                                                        <?php echo $service['name']; ?> - <?php echo $service['price']; ?> ريال
                                                    </label>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" name="create_visit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i>
                                            إنشاء زيارة
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- تبويب البحث عن مريض -->
                    <div class="tab-pane fade" id="search-patient" role="tabpanel" aria-labelledby="search-patient-tab">
                        <div class="card mt-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">البحث عن مريض</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <input type="text" class="form-control" id="patient_search" placeholder="ابحث بالاسم أو رقم الملف الطبي...">
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover" id="patients_table">
                                        <thead>
                                            <tr>
                                                <th>رقم الملف</th>
                                                <th>الاسم</th>
                                                <th>الهاتف</th>
                                                <th>العمر</th>
                                                <th>الجنس</th>
                                                <th>إجراءات</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($patients as $patient): ?>
                                            <tr>
                                                <td><?php echo $patient['medical_record_number']; ?></td>
                                                <td><?php echo $patient['full_name']; ?></td>
                                                <td><?php echo $patient['phone']; ?></td>
                                                <td><?php echo $patient['age']; ?></td>
                                                <td><?php echo $patient['gender']; ?></td>
                                                <td>
                                                    <a href="patient_details.php?id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i>
                                                        عرض
                                                    </a>
                                                    <button class="btn btn-sm btn-primary" onclick="selectPatient(<?php echo $patient['id']; ?>, '<?php echo $patient['full_name']; ?>')">
                                                        <i class="fas fa-check"></i>
                                                        اختيار
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        // تهيئة جدول المرضى
        $(document).ready(function() {
            $('#patients_table').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/ar.json'
                },
                pageLength: 10
            });

            // البحث الفوري
            $('#patient_search').on('keyup', function() {
                $('#patients_table').DataTable().search($(this).val()).draw();
            });
        });

        // اختيار مريض
        function selectPatient(id, name) {
            // التبديل إلى تبويب إنشاء زيارة جديدة
            $('#new-visit-tab').tab('show');

            // تحديد المريض
            $('#patient_id').val(id);

            // إظهار رسالة تأكيد
            alert('تم اختيار المريض: ' + name);
        }
    </script>
</body>
</html>
