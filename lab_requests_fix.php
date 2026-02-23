<?php
// الحصول على طلبات الفحوصات - تم تعديل هذا الاستعلام
$lab_requests = $conn->query("SELECT lr.*, lt.name as test_name, lt.price
                             FROM lab_requests lr
                             JOIN lab_tests lt ON lr.lab_test_id = lt.id
                             WHERE lr.visit_id = $visit_id")->fetchAll();
?>