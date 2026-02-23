
<?php
session_start();
require_once 'config/db.php';

// التحقق من تسجيل الدخول
if(isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT u.*, r.role_name FROM users u 
                            JOIN roles r ON u.role_id = r.id 
                            WHERE u.username = ? AND u.is_active = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role_name'];

        // تحديث آخر تسجيل دخول
        $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);

        // تسجيل النشاط
        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, ip_address) VALUES (?, 'Login', ?)");
        $logStmt->execute([$user['id'], $_SERVER['REMOTE_ADDR']]);

        // التوجيه حسب الدور
        switch($user['role_name']) {
            case 'Admin':
                header('Location: pages/admin/dashboard.php');
                break;
            case 'Reception':
                header('Location: pages/reception/dashboard.php');
                break;
            case 'Doctor':
                header('Location: pages/doctor/dashboard.php');
                break;
            case 'Lab Technician':
                header('Location: pages/lab/dashboard.php');
                break;
            case 'Pharmacist':
                header('Location: pages/pharmacy/dashboard.php');
                break;
            case 'Accountant':
                header('Location: pages/accountant/dashboard.php');
                break;
            default:
                header('Location: pages/dashboard.php');
        }
        exit;
    } else {
        $error = "اسم المستخدم أو كلمة المرور غير صحيحة";
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة المستشفى - تسجيل الدخول</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/login.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid h-100">
        <div class="row h-100">
            <div class="col-md-6 d-none d-md-flex bg-gradient align-items-center justify-content-center">
                <div class="text-center text-white">
                    <h1 class="display-4 fw-bold mb-4">نظام إدارة المستشفى</h1>
                    <p class="lead">نظام متكامل لإدارة العمليات الطبية والإدارية</p>
                    <div class="mt-5">
                        <i class="fas fa-hospital fa-5x"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-6 d-flex align-items-center justify-content-center">
                <div class="card shadow-lg border-0 rounded-4" style="width: 400px;">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-user-md fa-3x text-primary mb-3"></i>
                            <h3 class="fw-bold">تسجيل الدخول</h3>
                            <p class="text-muted">أدخل بياناتك للوصول إلى النظام</p>
                        </div>

                        <?php if(isset($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo $error; ?>
                        </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" name="username" placeholder="اسم المستخدم" required>
                                </div>
                            </div>
                            <div class="mb-4">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" name="password" placeholder="كلمة المرور" required>
                                </div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" name="login" class="btn btn-primary btn-lg rounded-pill">دخول</button>
                            </div>
                        </form>

                        <div class="text-center mt-4">
                            <p class="text-muted small">© 2023 نظام إدارة المستشفى</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
