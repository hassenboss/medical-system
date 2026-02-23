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

        // إعادة توجيه إلى صفحة الفواتير
        header('Location: invoices.php?success=1');
        exit();
    } catch(PDOException $e) {
        $conn->rollBack();
        $error = "حدث خطأ: " . $e->getMessage();
    }
}
?>