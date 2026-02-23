
<?php
session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// التحقق من الصلاحيات
function checkRole($allowedRoles) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles)) {
        header('Location: ../unauthorized.php');
        exit();
    }
}

// تسجيل الخروج
if (isset($_GET['logout'])) {
    // تسجيل النشاط
    require_once '../config/db.php';
    $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, ip_address) VALUES (?, 'Logout', ?)");
    $logStmt->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);

    // تدمير الجلسة
    session_unset();
    session_destroy();
    header('Location: ../login.php');
    exit();
}
?>
