
<?php
require_once '../../includes/auth.php';
checkRole(['Admin']);

require_once '../../config/db.php';

// الحصول على قائمة المستخدمين
$users = $conn->query("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.id ORDER BY u.id DESC")->fetchAll();

// الحصول على قائمة الأدوار
$roles = $conn->query("SELECT * FROM roles")->fetchAll();

// معالجة إضافة مستخدم جديد
if(isset($_POST['add_user'])) {
    try {
        // تشفير كلمة المرور
        $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, phone, role_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['username'],
            $hashed_password,
            $_POST['full_name'],
            $_POST['email'],
            $_POST['phone'],
            $_POST['role_id']
        ]);

        $user_id = $conn->lastInsertId();

        // تسجيل النشاط
        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Add User', 'users', ?)");
        $logStmt->execute([$_SESSION['user_id'], $user_id]);

        $success = "تم إضافة المستخدم بنجاح";

        // إعادة تحميل قائمة المستخدمين
        $users = $conn->query("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.id ORDER BY u.id DESC")->fetchAll();
    } catch(PDOException $e) {
        $error = "حدث خطأ: " . $e->getMessage();
    }
}

// معالجة تحديث بيانات المستخدم
if(isset($_POST['update_user'])) {
    try {
        $stmt = $conn->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, phone = ?, role_id = ?, is_active = ? WHERE id = ?");
        $stmt->execute([
            $_POST['username'],
            $_POST['full_name'],
            $_POST['email'],
            $_POST['phone'],
            $_POST['role_id'],
            isset($_POST['is_active']) ? 1 : 0,
            $_POST['user_id']
        ]);

        // تحديث كلمة المرور إذا تم إدخالها
        if(!empty($_POST['password'])) {
            $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $_POST['user_id']]);
        }

        // تسجيل النشاط
        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Update User', 'users', ?)");
        $logStmt->execute([$_SESSION['user_id'], $_POST['user_id']]);

        $success = "تم تحديث بيانات المستخدم بنجاح";

        // إعادة تحميل قائمة المستخدمين
        $users = $conn->query("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.id ORDER BY u.id DESC")->fetchAll();
    } catch(PDOException $e) {
        $error = "حدث خطأ: " . $e->getMessage();
    }
}

// الحصول على بيانات مستخدم محدد للتعديل
$edit_user = null;
if(isset($_GET['edit_id'])) {
    $edit_user = $conn->query("SELECT * FROM users WHERE id = " . $_GET['edit_id'])->fetch();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المستخدمين</title>
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
                            <a class="nav-link active" href="users.php">
                                <i class="fas fa-users me-2"></i>
                                إدارة المستخدمين
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="doctors.php">
                                <i class="fas fa-user-md me-2"></i>
                                إدارة الأطباء
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="services.php">
                                <i class="fas fa-procedures me-2"></i>
                                إدارة الخدمات
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="medicines.php">
                                <i class="fas fa-pills me-2"></i>
                                إدارة الأدوية
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="lab_tests.php">
                                <i class="fas fa-vial me-2"></i>
                                إدارة فحوصات المعمل
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="prices.php">
                                <i class="fas fa-tag me-2"></i>
                                إدارة الأسعار
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar me-2"></i>
                                التقارير
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="settings.php">
                                <i class="fas fa-cog me-2"></i>
                                إعدادات النظام
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="backup.php">
                                <i class="fas fa-database me-2"></i>
                                نسخ احتياطي
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- المحتوى الرئيسي -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">إدارة المستخدمين</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="fas fa-plus me-1"></i>
                            إضافة مستخدم جديد
                        </button>
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

                <!-- قائمة المستخدمين -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">قائمة المستخدمين</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>اسم المستخدم</th>
                                        <th>الاسم الكامل</th>
                                        <th>البريد الإلكتروني</th>
                                        <th>رقم الهاتف</th>
                                        <th>الدور</th>
                                        <th>الحالة</th>
                                        <th>آخر تسجيل دخول</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($users as $user): ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td><?php echo $user['username']; ?></td>
                                        <td><?php echo $user['full_name']; ?></td>
                                        <td><?php echo $user['email']; ?></td>
                                        <td><?php echo $user['phone']; ?></td>
                                        <td><?php echo $user['role_name']; ?></td>
                                        <td>
                                            <?php if($user['is_active']): ?>
                                            <span class="badge bg-success">نشط</span>
                                            <?php else: ?>
                                            <span class="badge bg-danger">غير نشط</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if($user['last_login']) {
                                                echo date('Y-m-d H:i', strtotime($user['last_login']));
                                            } else {
                                                echo 'لم يسجل دخوله بعد';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <a href="?edit_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-edit me-1"></i>
                                                تعديل
                                            </a>
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

    <!-- نافذة إضافة مستخدم جديد -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel">إضافة مستخدم جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="username" class="form-label">اسم المستخدم</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">كلمة المرور</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="full_name" class="form-label">الاسم الكامل</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">البريد الإلكتروني</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">رقم الهاتف</label>
                            <input type="text" class="form-control" id="phone" name="phone">
                        </div>
                        <div class="mb-3">
                            <label for="role_id" class="form-label">الدور</label>
                            <select class="form-select" id="role_id" name="role_id" required>
                                <?php foreach($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>"><?php echo $role['role_name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="add_user" class="btn btn-primary">حفظ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- نافذة تعديل مستخدم -->
    <?php if($edit_user): ?>
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true" show>
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">تعديل بيانات المستخدم</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">

                        <div class="mb-3">
                            <label for="edit_username" class="form-label">اسم المستخدم</label>
                            <input type="text" class="form-control" id="edit_username" name="username" value="<?php echo $edit_user['username']; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_password" class="form-label">كلمة المرور (اتركها فارغة إذا لم ترد تغييرها)</label>
                            <input type="password" class="form-control" id="edit_password" name="password">
                        </div>
                        <div class="mb-3">
                            <label for="edit_full_name" class="form-label">الاسم الكامل</label>
                            <input type="text" class="form-control" id="edit_full_name" name="full_name" value="<?php echo $edit_user['full_name']; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">البريد الإلكتروني</label>
                            <input type="email" class="form-control" id="edit_email" name="email" value="<?php echo $edit_user['email']; ?>">
                        </div>
                        <div class="mb-3">
                            <label for="edit_phone" class="form-label">رقم الهاتف</label>
                            <input type="text" class="form-control" id="edit_phone" name="phone" value="<?php echo $edit_user['phone']; ?>">
                        </div>
                        <div class="mb-3">
                            <label for="edit_role_id" class="form-label">الدور</label>
                            <select class="form-select" id="edit_role_id" name="role_id" required>
                                <?php foreach($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>" <?php echo ($role['id'] == $edit_user['role_id']) ? 'selected' : ''; ?>><?php echo $role['role_name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active" <?php echo ($edit_user['is_active']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="edit_is_active">نشط</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="users.php" class="btn btn-secondary">إلغاء</a>
                        <button type="submit" name="update_user" class="btn btn-primary">حفظ التغييرات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php if($edit_user): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var myModal = new bootstrap.Modal(document.getElementById('editUserModal'));
            myModal.show();
        });
    </script>
    <?php endif; ?>
</body>
</html>
