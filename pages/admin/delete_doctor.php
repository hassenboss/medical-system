<?php
require_once '../../includes/auth.php';
checkRole(['Admin']);
require_once '../../config/db.php';

if(isset($_POST['doctor_id'])) {
    try {
        $doctor_id = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
        if($doctor_id) {
            // التحقق من وجود الطبيب
            $stmt = $conn->prepare("SELECT id FROM doctors WHERE id = ?");
            $stmt->execute([$doctor_id]);
            if($stmt->fetch()) {
                // حذف الطبيب
                $stmt = $conn->prepare("DELETE FROM doctors WHERE id = ?");
                $stmt->execute([$doctor_id]);
                
                // تسجيل العملية
                $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Delete Doctor', 'doctors', ?)");
                $logStmt->execute([$_SESSION['user_id'], $doctor_id]);
                
                $_SESSION['success'] = "✅ تم حذف الطبيب بنجاح";
            } else {
                $_SESSION['error'] = "❌ الطبيب غير موجود";
            }
        } else {
            $_SESSION['error'] = "❌ معرف الطبيب غير صالح";
        }
    } catch(PDOException $e) {
        error_log("Delete Doctor Error: " . $e->getMessage());
        $_SESSION['error'] = "❌ حدث خطأ في قاعدة البيانات";
    }
}

// إعادة التوجيه إلى صفحة الأطباء
header("Location: doctors.php");
exit();
