<?php
require_once '../../includes/auth.php';
checkRole(['Admin', 'Lab Technician']);

require_once '../../config/db.php';

// معالجة إضافة فحص جديد
if(isset($_POST['add_test'])) {
    try {
        $stmt = $conn->prepare("INSERT INTO lab_tests (name, description, price) VALUES (?, ?, ?)");
        $stmt->execute([
            $_POST['name'],
            $_POST['description'],
            $_POST['price'],
          
        
        ]);

        // تسجيل النشاط
        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Add Lab Test', 'lab_tests', ?)");
        $logStmt->execute([$_SESSION['user_id'], $conn->lastInsertId()]);

        $success = "تم إضافة الفحص بنجاح";
    } catch(PDOException $e) {
        $error = "حدث خطأ: " . $e->getMessage();
    }
}

// معالجة تحديث فحص
if(isset($_POST['update_test'])) {
    try {
        $stmt = $conn->prepare("UPDATE lab_tests SET name = ?, description = ?, price = ? WHERE id = ?");
        $stmt->execute([
            $_POST['name'],
            $_POST['description'],
            $_POST['price'],
           
            $_POST['test_id']
        ]);

        // تسجيل النشاط
        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Update Lab Test', 'lab_tests', ?)");
        $logStmt->execute([$_SESSION['user_id'], $_POST['test_id']]);

        $success = "تم تحديث الفحص بنجاح";
    } catch(PDOException $e) {
        $error = "حدث خطأ: " . $e->getMessage();
    }
}

// معالجة حذف فحص
if(isset($_GET['delete_test'])) {
    try {
        $test_id = $_GET['delete_test'];

        // التحقق من عدم وجود طلبات مرتبطة بالفحص
        $check = $conn->query("SELECT COUNT(*) as count FROM lab_requests WHERE lab_test_id = $test_id")->fetch();

        if($check['count'] == 0) {
            $conn->query("DELETE FROM lab_tests WHERE id = $test_id");

            // تسجيل النشاط
            $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Delete Lab Test', 'lab_tests', ?)");
            $logStmt->execute([$_SESSION['user_id'], $test_id]);

            $success = "تم حذف الفحص بنجاح";
        } else {
            $error = "لا يمكن حذف هذا الفحص لأنه مرتبط بطلبات";
        }
    } catch(PDOException $e) {
        $error = "حدث خطأ: " . $e->getMessage();
    }
}

// الحصول على قائمة الفحوصات
$tests = $conn->query("SELECT * FROM lab_tests ORDER BY name")->fetchAll();

// الحصول على تفاصيل فحص محدد للتعديل
$selected_test = null;
if(isset($_GET['edit_test'])) {
    $test_id = $_GET['edit_test'];
    $selected_test = $conn->query("SELECT * FROM lab_tests WHERE id = $test_id")->fetch();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الفحوصات</title>
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
                            <a class="nav-link active" href="tests.php">
                                <i class="fas fa-vial me-2"></i>
                                إدارة الفحوصات
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="results.php">
                                <i class="fas fa-file-medical me-2"></i>
                                نتائج الفحوصات
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
                    <h1 class="h2">إدارة الفحوصات</h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTestModal">
                        <i class="fas fa-plus me-1"></i>
                        إضافة فحص جديد
                    </button>
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

                <!-- قائمة الفحوصات -->
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-vial me-2"></i>قائمة الفحوصات</h5>
                        <span class="badge bg-light text-dark"><?php echo count($tests); ?> فحص</span>
                    </div>
                    <div class="card-body">
                        <?php if(count($tests) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>الرقم</th>
                                        <th>اسم الفحص</th>
                                        <th>الوصف</th>
                                     
                                        <th>السعر</th>
                                      
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($tests as $test): ?>
                                    <tr>
                                        <td><span class="badge bg-primary">#<?php echo $test['id']; ?></span></td>
                                        <td><?php echo $test['name']; ?></td>
                                        <td><?php echo $test['description']; ?></td>
                                      
                                        <td><?php echo number_format($test['price'], 2); ?> ريال</td>
                                        
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="?edit_test=<?php echo $test['id']; ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?delete_test=<?php echo $test['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('هل أنت متأكد من حذف هذا الفحص؟')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-vial fa-3x text-muted mb-3"></i>
                            <h5>لا توجد فحوصات</h5>
                            <p class="text-muted">لم يتم إضافة أي فحص بعد</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if($selected_test): ?>
                <!-- تعديل الفحص -->
                <div class="card mt-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">تعديل الفحص</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="test_id" value="<?php echo $selected_test['id']; ?>">

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">اسم الفحص</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo $selected_test['name']; ?>" required>
                                </div>
                               
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">الوصف</label>
                                <textarea class="form-control" id="description" name="description" rows="3" required><?php echo $selected_test['description']; ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="price" class="form-label">السعر (ريال)</label>
                                    <input type="number" step="0.01" class="form-control" id="price" name="price" value="<?php echo $selected_test['price']; ?>" required>
                                </div>
                                
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="tests.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i>
                                    إلغاء
                                </a>
                                <button type="submit" name="update_test" class="btn btn-warning">
                                    <i class="fas fa-save me-1"></i>
                                    حفظ التعديلات
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- نافذة إضافة فحص جديد -->
    <div class="modal fade" id="addTestModal" tabindex="-1" aria-labelledby="addTestModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addTestModalLabel">إضافة فحص جديد</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="modal_name" class="form-label">اسم الفحص</label>
                                <input type="text" class="form-control" id="modal_name" name="name" required>
                            </div>
                           
                        </div>

                        <div class="mb-3">
                            <label for="modal_description" class="form-label">الوصف</label>
                            <textarea class="form-control" id="modal_description" name="description" rows="3" required></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="modal_price" class="form-label">السعر (ريال)</label>
                                <input type="number" step="0.01" class="form-control" id="modal_price" name="price" required>
                            </div>
                           
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="add_test" class="btn btn-primary">إضافة الفحص</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
