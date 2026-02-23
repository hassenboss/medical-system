<?php
require_once '../../includes/auth.php';
checkRole(['Admin', 'Reception']);

require_once '../../config/db.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    if(isset($_POST['request_id']) && isset($_POST['status'])) {
        $request_id = $_POST['request_id'];
        $new_status = $_POST['status'];
        
        // التحقق من الحالة المسموح بها
        $allowed_statuses = ['Pending', 'Paid', 'Completed'];
        if(!in_array($new_status, $allowed_statuses)) {
            $response['message'] = 'حالة غير صالحة';
            echo json_encode($response);
            exit;
        }
        
        // تحديث حالة الفحص
        $stmt = $conn->prepare("UPDATE lab_requests SET status = ? WHERE id = ?");
        $result = $stmt->execute([$new_status, $request_id]);
        
        if($result) {
            // تسجيل النشاط
            $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Update Lab Request Status', 'lab_requests', ?)");
            $logStmt->execute([$_SESSION['user_id'], $request_id]);
            
            // إذا تم تحديث الحالة إلى Paid، قم بتحديث حالة الزيارة إذا لزم الأمر
            if($new_status == 'Paid') {
                // التحقق مما إذا كانت جميع الفحوصات لهذه الزيارة مدفوعة
                $request = $conn->query("SELECT visit_id FROM lab_requests WHERE id = $request_id")->fetch();
                $visit_id = $request['visit_id'];
                
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM lab_requests WHERE visit_id = ? AND status != 'Paid'");
                $stmt->execute([$visit_id]);
                $pending = $stmt->fetch()['count'];
                
                if($pending == 0) {
                    $stmt = $conn->prepare("UPDATE visits SET status = 'Lab Paid' WHERE id = ?");
                    $stmt->execute([$visit_id]);
                }
            }
            
            $response['success'] = true;
            $response['message'] = 'تم تحديث الحالة بنجاح';
        } else {
            $response['message'] = 'فشل تحديث الحالة';
        }
    } else {
        $response['message'] = 'بيانات غير مكتملة';
    }
} catch(PDOException $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>