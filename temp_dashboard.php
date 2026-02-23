<?php
require_once '../../includes/auth.php';
checkRole(['Admin', 'Doctor']);

require_once '../../config/db.php';

// الحصول على قائمة الزيارات المدفوعة للطبيب الحالي
$doctor_id = $conn->query("SELECT id FROM doctors WHERE full_name = '" . $_SESSION['full_name'] . "'")->fetch()['id'];

$visits = $conn->query("SELECT v.*, p.full_name as patient_name, p.medical_record_number, p.age, p.gender
                        FROM visits v
                        JOIN patients p ON v.patient_id = p.id
                        WHERE v.doctor_id = $doctor_id AND v.status IN ('Consultation Paid', 'In Consultation', 'Lab Payment Pending', 'Lab Paid', 'Lab Completed', 'Pharmacy Payment Pending', 'Pharmacy Paid')
                        ORDER BY v.visit_date DESC")->fetchAll();

// الحصول على قائمة فحوصات المعمل
$lab_tests = $conn->query("SELECT * FROM lab_tests WHERE is_active = 1")->fetchAll();

// الحصول على قائمة الأدوية
$medicines = $conn->query("SELECT * FROM medicines WHERE is_active = 1 ORDER BY name")->fetchAll();

// معالجة تحديث بيانات الزيارة
if(isset($_POST['update_visit'])) {
    try {
        $stmt = $conn->prepare("UPDATE visits SET symptoms = ?, vital_signs = ?, notes = ?, status = ? WHERE id = ?");
        $stmt->execute([
            $_POST['symptoms'],
            $_POST['vital_signs'],
            $_POST['notes'],
            $_POST['status'],
            $_POST['visit_id']
        ]);

        // تسجيل النشاط
        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Update Visit', 'visits', ?)");
        $logStmt->execute([$_SESSION['user_id'], $_POST['visit_id']]);

        $success = "تم تحديث بيانات الزيارة بنجاح";

        // إعادة تحميل قائمة الزيارات
        $visits = $conn->query("SELECT v.*, p.full_name as patient_name, p.medical_record_number, p.age, p.gender
                                FROM visits v
                                JOIN patients p ON v.patient_id = p.id
                                WHERE v.doctor_id = $doctor_id AND v.status IN ('Consultation Paid', 'In Consultation', 'Lab Payment Pending', 'Lab Paid', 'Lab Completed', 'Pharmacy Payment Pending', 'Pharmacy Paid')
                                ORDER BY v.visit_date DESC")->fetchAll();
    } catch(PDOException $e) {
        $error = "حدث خطأ: " . $e->getMessage();
    }
}

// معالجة طلب فحوصات المعمل
if(isset($_POST['request_lab'])) {
    try {
        $conn->beginTransaction();

        // تحديث حالة الزيارة
        $stmt = $conn->prepare("UPDATE visits SET status = 'Lab Payment Pending' WHERE id = ?");
        $stmt->execute([$_POST['visit_id']]);

        // إضافة طلبات الفحوصات
        if(!empty($_POST['lab_tests'])) {
            foreach($_POST['lab_tests'] as $lab_test_id) {
                $stmt = $conn->prepare("INSERT INTO lab_requests (visit_id, lab_test_id) VALUES (?, ?)");
                $stmt->execute([$_POST['visit_id'], $lab_test_id]);
            }

            // حساب إجمالي تكلفة الفحوصات
            $total_amount = 0;
            foreach($_POST['lab_tests'] as $lab_test_id) {
                $lab_test = $conn->query("SELECT price FROM lab_tests WHERE id = $lab_test_id")->fetch();
                $total_amount += $lab_test['price'];
            }

            // إنشاء فاتورة للفحوصات
            $visit = $conn->query("SELECT patient_id FROM visits WHERE id = " . $_POST['visit_id'])->fetch();
            $invoice_number = 'LAB-' . date('Y-m-d') . '-' . str_pad($_POST['visit_id'], 4, '0', STR_PAD_LEFT);

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
            foreach($_POST['lab_tests'] as $lab_test_id) {
                $lab_test = $conn->query("SELECT * FROM lab_tests WHERE id = $lab_test_id")->fetch();
                $stmt = $conn->prepare("INSERT INTO invoice_items (invoice_id, item_type, item_id, quantity, unit_price, total_price)
                                       VALUES (?, 'Lab Test', ?, 1, ?, ?)");
                $stmt->execute([$invoice_id, $lab_test_id, $lab_test['price'], $lab_test['price']]);
            }
        }

        $conn->commit();

        // تسجيل النشاط
        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Request Lab Tests', 'visits', ?)");
        $logStmt->execute([$_SESSION['user_id'], $_POST['visit_id']]);

        $success = "تم طلب فحوصات المعمل بنجاح";

        // إعادة تحميل قائمة الزيارات
        $visits = $conn->query("SELECT v.*, p.full_name as patient_name, p.medical_record_number, p.age, p.gender
                                FROM visits v
                                JOIN patients p ON v.patient_id = p.id
                                WHERE v.doctor_id = $doctor_id AND v.status IN ('Consultation Paid', 'In Consultation', 'Lab Payment Pending', 'Lab Paid', 'Lab Completed', 'Pharmacy Payment Pending', 'Pharmacy Paid')
                                ORDER BY v.visit_date DESC")->fetchAll();
    } catch(PDOException $e) {
        $conn->rollBack();
        $error = "حدث خطأ: " . $e->getMessage();
    }
}

// معالجة إضافة وصفة طبية
if(isset($_POST['add_prescription'])) {
    try {
        $conn->beginTransaction();

        // تحديث حالة الزيارة
        $stmt = $conn->prepare("UPDATE visits SET status = 'Pharmacy Payment Pending', diagnosis = ? WHERE id = ?");
        $stmt->execute([$_POST['diagnosis'], $_POST['visit_id']]);

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
        }

        $conn->commit();

        // تسجيل النشاط
        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Add Prescription', 'visits', ?)");
        $logStmt->execute([$_SESSION['user_id'], $_POST['visit_id']]);

        $success = "تم إضافة الوصفة الطبية بنجاح";

        // إعادة تحميل قائمة الزيارات
        $visits = $conn->query("SELECT v.*, p.full_name as patient_name, p.medical_record_number, p.age, p.gender
                                FROM visits v
                                JOIN patients p ON v.patient_id = p.id
                                WHERE v.doctor_id = $doctor_id AND v.status IN ('Consultation Paid', 'In Consultation', 'Lab Payment Pending', 'Lab Paid', 'Lab Completed', 'Pharmacy Payment Pending', 'Pharmacy Paid')
                                ORDER BY v.visit_date DESC")->fetchAll();
    } catch(PDOException $e) {
        $conn->rollBack();
        $error = "حدث خطأ: " . $e->getMessage();
    }
}

// الحصول على تفاصيل زيارة محددة
$selected_visit = null;
if(isset($_GET['visit_id'])) {
    $visit_id = $_GET['visit_id'];
    $selected_visit = $conn->query("SELECT v.*, p.full_name as patient_name, p.medical_record_number, p.age, p.gender, p.phone, p.address
                                   FROM visits v
                                   JOIN patients p ON v.patient_id = p.id
                                   WHERE v.id = $visit_id")->fetch();

    // الحصول على طلبات الفحوصات - تم تعديل هذا الاستعلام
    $lab_requests = $conn->query("SELECT lr.*, lt.name as test_name, lt.price
                                 FROM lab_requests lr
                                 JOIN lab_tests lt ON lr.lab_test_id = lt.id
                                 WHERE lr.visit_id = $visit_id")->fetchAll();

    // الحصول على نتائج الفحوصات
    $lab_results = $conn->query("SELECT lr.*, lt.name as test_name
                                 FROM lab_requests lr
                                 JOIN lab_tests lt ON lr.lab_test_id = lt.id
                                 WHERE lr.visit_id = $visit_id AND lr.status = 'Completed'")->fetchAll();

    // الحصول على الوصفات الطبية
    $prescriptions = $conn->query("SELECT pr.*, m.name as medicine_name
                                  FROM prescriptions pr
                                  JOIN medicines m ON pr.medicine_id = m.id
                                  WHERE pr.visit_id = $visit_id")->fetchAll();

    // الحصول على تاريخ الزيارات السابقة
    $previous_visits = $conn->query("SELECT v.*, d.full_name as doctor_name
                                   FROM visits v
                                   JOIN doctors d ON v.doctor_id = d.id
                                   WHERE v.patient_id = " . $selected_visit['patient_id'] . " AND v.id != $visit_id
                                   ORDER BY v.visit_date DESC")->fetchAll();
}
?>