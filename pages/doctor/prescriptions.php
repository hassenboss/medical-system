<?php
require_once '../../includes/auth.php';
checkRole(['Admin', 'Doctor']);

require_once '../../config/db.php';

// الحصول على قائمة الوصفات الطبية
$prescriptions = $conn->query("SELECT pr.*, p.full_name as patient_name, p.medical_record_number, m.name as medicine_name, v.visit_date 
                                FROM prescriptions pr 
                                JOIN medicines m ON pr.medicine_id = m.id 
                                JOIN visits v ON pr.visit_id = v.id 
                                JOIN patients p ON v.patient_id = p.id 
                                WHERE v.doctor_id = (SELECT id FROM doctors WHERE full_name = '" . $_SESSION['full_name'] . "')
                                ORDER BY v.visit_date DESC")->fetchAll();

// معالجة إضافة وصفة طبية
if(isset($_POST['add_prescription'])) {
    try {
        $conn->beginTransaction();

        // إضافة الأدوية
        if(!empty($_POST['medicines'])) {
            foreach($_POST['medicines'] as $index => $medicine_id) {
                $stmt = $conn->prepare("INSERT INTO prescriptions (visit_id, medicine_id, dosage, duration, instructions) 
                                       VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['visit_id'],
                    $medicine_id,
                    $_POST['dosage'][$index],
                    $_POST['duration'][$index],
                    $_POST['instructions'][$index]
                ]);
            }

            // حساب إجمالي تكلفة الأدوية
            $total_amount = 0;
            foreach($_POST['medicines'] as $medicine_id) {
                $medicine = $conn->query("SELECT price FROM medicines WHERE id = $medicine_id")->fetch();
                $total_amount += $medicine['price'];
            }

            // إنشاء فاتورة للأدوية
            $visit = $conn->query("SELECT patient_id FROM visits WHERE id = " . $_POST['visit_id'])->fetch();
            $invoice_number = 'PHARM-' . date('Y-m-d') . '-' . str_pad($_POST['visit_id'], 4, '0', STR_PAD_LEFT);

            $stmt = $conn->prepare("INSERT INTO invoices (invoice_number, patient_id, visit_id, total_amount, final_amount, payment_status, created_by) 
                                   VALUES (?, ?, ?, ?, ?, 'Pending', ?)");
            $stmt->execute([
                $invoice_number,
                $visit['patient_id'],
                $_POST['visit_id'],
                $total_amount,
                $total_amount,
                $_SESSION['user_id']
            ]);

            $invoice_id = $conn->lastInsertId();

            // إضافة تفاصيل الفاتورة
            foreach($_POST['medicines'] as $medicine_id) {
                $medicine = $conn->query("SELECT * FROM medicines WHERE id = $medicine_id")->fetch();
                $stmt = $conn->prepare("INSERT INTO invoice_items (invoice_id, item_type, item_id, quantity, unit_price, total_price) 
                                       VALUES (?, 'Medicine', ?, 1, ?, ?)");
                $stmt->execute([$invoice_id, $medicine_id, $medicine['price'], $medicine['price']]);
            }

            // تحديث حالة الزيارة
            $stmt = $conn->prepare("UPDATE visits SET status = 'Pharmacy Payment Pending', diagnosis = ? WHERE id = ?");
            $stmt->execute([$_POST['diagnosis'], $_POST['visit_id']]);
        }

        $conn->commit();

        // تسجيل النشاط
        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Add Prescription', 'visits', ?)");
        $logStmt->execute([$_SESSION['user_id'], $_POST['visit_id']]);

        $success = "تم إضافة الوصفة الطبية بنجاح";

        // إعادة تحميل قائمة الوصفات
        $prescriptions = $conn->query("SELECT pr.*, p.full_name as patient_name, p.medical_record_number, m.name as medicine_name, v.visit_date 
                                      FROM prescriptions pr 
                                      JOIN medicines m ON pr.medicine_id = m.id 
                                      JOIN visits v ON pr.visit_id = v.id 
                                      JOIN patients p ON v.patient_id = p.id 
                                      WHERE v.doctor_id = (SELECT id FROM doctors WHERE full_name = '" . $_SESSION['full_name'] . "')
                                      ORDER BY v.visit_date DESC")->fetchAll();
    } catch(PDOException $e) {
        $conn->rollBack();
        $error = "حدث خطأ: " . $e->getMessage();
    }
}

// الحصول على قائمة الأدوية
$medicines = $conn->query("SELECT * FROM medicines WHERE is_active = 1 ORDER BY name")->fetchAll();

// الحصول على قائمة الزيارات للطبيب الحالي
$visits = $conn->query("SELECT v.*, p.full_name as patient_name, p.medical_record_number 
                         FROM visits v 
                         JOIN patients p ON v.patient_id = p.id 
                         WHERE v.doctor_id = (SELECT id FROM doctors WHERE full_name = '" . $_SESSION['full_name'] . "') 
                         AND v.status IN ('Consultation Paid', 'In Consultation', 'Lab Completed')
                         ORDER BY v.visit_date DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الوصفات الطبية</title>
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
                            <a class="nav-link active" href="prescriptions.php">
                                <i class="fas fa-prescription me-2"></i>
                                إدارة الوصفات
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../reception/lab_requests.php">
                                <i class="fas fa-vial me-2"></i>
                                طلبات فحوصات المعمل
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
                    <h1 class="h2">إدارة الوصفات الطبية</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#prescriptionModal">
                                <i class="fas fa-plus me-1"></i>
                                وصفة جديدة
                            </button>
                        </div>
                    </div>
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

                <!-- قائمة الوصفات الطبية -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">قائمة الوصفات الطبية</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>المريض</th>
                                        <th>رقم الملف</th>
                                        <th>الدواء</th>
                                        <th>الجرعة</th>
                                        <th>المدة</th>
                                        <th>تاريخ الزيارة</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($prescriptions as $prescription): ?>
                                    <tr>
                                        <td><?php echo $prescription['patient_name']; ?></td>
                                        <td><?php echo $prescription['medical_record_number']; ?></td>
                                        <td><?php echo $prescription['medicine_name']; ?></td>
                                        <td><?php echo $prescription['dosage']; ?></td>
                                        <td><?php echo $prescription['duration']; ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($prescription['visit_date'])); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-info" onclick="showPrescriptionDetails(<?php echo $prescription['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- نموذج إضافة وصفة طبية -->
    <div class="modal fade" id="prescriptionModal" tabindex="-1" aria-labelledby="prescriptionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="prescriptionModalLabel">إضافة وصفة طبية</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="visit_id" class="form-label">الزيارة</label>
                            <select class="form-select" id="visit_id" name="visit_id" required>
                                <option value="">اختر زيارة</option>
                                <?php foreach($visits as $visit): ?>
                                <option value="<?php echo $visit['id']; ?>"><?php echo $visit['patient_name']; ?> - <?php echo date('d/m/Y', strtotime($visit['visit_date'])); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="diagnosis" class="form-label">التشخيص</label>
                            <textarea class="form-control" id="diagnosis" name="diagnosis" rows="3" required></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">الأدوية</label>
                            <div id="medicines_container">
                                <div class="row mb-2">
                                    <div class="col-md-6">
                                        <select class="form-select medicine-select" name="medicines[]" required>
                                            <option value="">اختر دواء</option>
                                            <?php foreach($medicines as $medicine): ?>
                                            <option value="<?php echo $medicine['id']; ?>"><?php echo $medicine['name']; ?> - <?php echo number_format($medicine['price'], 2); ?> ريال</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="text" class="form-control" name="dosage[]" placeholder="الجرعة" required>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="text" class="form-control" name="duration[]" placeholder="المدة" required>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-danger btn-sm remove-medicine">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <button type="button" id="add_medicine" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-plus me-1"></i>
                                إضافة دواء
                            </button>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="add_prescription" class="btn btn-primary">حفظ الوصفة</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // إضافة دواء جديد
        document.getElementById('add_medicine').addEventListener('click', function() {
            const container = document.getElementById('medicines_container');
            const newRow = document.createElement('div');
            newRow.className = 'row mb-2';

            // إنشاء قائمة الأدوية
            const medicineSelect = document.createElement('select');
            medicineSelect.className = 'form-select medicine-select';
            medicineSelect.name = 'medicines[]';
            medicineSelect.required = true;

            // إضافة خيار افتراضي
            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = 'اختر دواء';
            medicineSelect.appendChild(defaultOption);

            // إضافة خيارات الأدوية
            <?php foreach($medicines as $medicine): ?>
            const option<?php echo $medicine['id']; ?> = document.createElement('option');
            option<?php echo $medicine['id']; ?>.value = '<?php echo $medicine['id']; ?>';
            option<?php echo $medicine['id']; ?>.textContent = '<?php echo $medicine['name']; ?> - <?php echo number_format($medicine['price'], 2); ?> ريال';
            medicineSelect.appendChild(option<?php echo $medicine['id']; ?>);
            <?php endforeach; ?>

            // إنشاء حقول الإدخال
            const dosageInput = document.createElement('input');
            dosageInput.type = 'text';
            dosageInput.className = 'form-control';
            dosageInput.name = 'dosage[]';
            dosageInput.placeholder = 'الجرعة';
            dosageInput.required = true;

            const durationInput = document.createElement('input');
            durationInput.type = 'text';
            durationInput.className = 'form-control';
            durationInput.name = 'duration[]';
            durationInput.placeholder = 'المدة';
            durationInput.required = true;

            // إنشاء زر الحذف
            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'btn btn-danger btn-sm remove-medicine';
            deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
            deleteBtn.addEventListener('click', function() {
                newRow.remove();
            });

            // إضافة العناصر إلى الصف
            const col1 = document.createElement('div');
            col1.className = 'col-md-6';
            col1.appendChild(medicineSelect);

            const col2 = document.createElement('div');
            col2.className = 'col-md-2';
            col2.appendChild(dosageInput);

            const col3 = document.createElement('div');
            col3.className = 'col-md-2';
            col3.appendChild(durationInput);

            const col4 = document.createElement('div');
            col4.className = 'col-md-2';
            col4.appendChild(deleteBtn);

            newRow.appendChild(col1);
            newRow.appendChild(col2);
            newRow.appendChild(col3);
            newRow.appendChild(col4);

            container.appendChild(newRow);
        });

        // حذف دواء
        document.querySelectorAll('.remove-medicine').forEach(btn => {
            btn.addEventListener('click', function() {
                this.closest('.row').remove();
            });
        });

        // عرض تفاصيل الوصفة
        function showPrescriptionDetails(prescriptionId) {
            // في تطبيق حقيقي، يمكن جلب البيانات عبر AJAX
            // هنا سنعرض رسالة توضيحية
            alert('عرض تفاصيل الوصفة رقم: ' + prescriptionId);
        }
    </script>
</body>
</html>