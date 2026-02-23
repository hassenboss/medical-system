
<?php
require_once '../../includes/auth.php';
checkRole(['Admin']);

require_once '../../config/db.php';

// الحصول على قائمة الأسعار
$prices = $conn->query("SELECT pl.*, 
                        CASE 
                            WHEN pl.item_type = 'Service' THEN (SELECT name FROM services WHERE id = pl.item_id)
                            WHEN pl.item_type = 'Lab Test' THEN (SELECT name FROM lab_tests WHERE id = pl.item_id)
                            WHEN pl.item_type = 'Medicine' THEN (SELECT name FROM medicines WHERE id = pl.item_id)
                        END as item_name
                        FROM price_list pl 
                        ORDER BY pl.effective_date DESC")->fetchAll();

// معالجة إضافة سعر جديد
if(isset($_POST['add_price'])) {
    try {
        $stmt = $conn->prepare("INSERT INTO price_list (item_type, item_id, price, effective_date, created_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['item_type'],
            $_POST['item_id'],
            $_POST['price'],
            $_POST['effective_date'],
            $_SESSION['user_id']
        ]);

        $price_id = $conn->lastInsertId();

        // تحديث السعر في الجدول الأصلي
        if($_POST['item_type'] == 'Service') {
            $stmt = $conn->prepare("UPDATE services SET price = ? WHERE id = ?");
            $stmt->execute([$_POST['price'], $_POST['item_id']]);
        } elseif($_POST['item_type'] == 'Lab Test') {
            $stmt = $conn->prepare("UPDATE lab_tests SET price = ? WHERE id = ?");
            $stmt->execute([$_POST['price'], $_POST['item_id']]);
        } elseif($_POST['item_type'] == 'Medicine') {
            $stmt = $conn->prepare("UPDATE medicines SET price = ? WHERE id = ?");
            $stmt->execute([$_POST['price'], $_POST['item_id']]);
        }

        // تسجيل النشاط
        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Add Price', 'price_list', ?)");
        $logStmt->execute([$_SESSION['user_id'], $price_id]);

        $success = "تم إضافة السعر بنجاح";

        // إعادة تحميل قائمة الأسعار
        $prices = $conn->query("SELECT pl.*, 
                            CASE 
                                WHEN pl.item_type = 'Service' THEN (SELECT name FROM services WHERE id = pl.item_id)
                                WHEN pl.item_type = 'Lab Test' THEN (SELECT name FROM lab_tests WHERE id = pl.item_id)
                                WHEN pl.item_type = 'Medicine' THEN (SELECT name FROM medicines WHERE id = pl.item_id)
                            END as item_name
                            FROM price_list pl 
                            ORDER BY pl.effective_date DESC")->fetchAll();
    } catch(PDOException $e) {
        $error = "حدث خطأ: " . $e->getMessage();
    }
}

// الحصول على قائمة الخدمات
$services = $conn->query("SELECT * FROM services WHERE is_active = 1 ORDER BY name")->fetchAll();

// الحصول على قائمة فحوصات المعمل
$lab_tests = $conn->query("SELECT * FROM lab_tests WHERE is_active = 1 ORDER BY name")->fetchAll();

// الحصول على قائمة الأدوية
$medicines = $conn->query("SELECT * FROM medicines WHERE is_active = 1 ORDER BY name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الأسعار</title>
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
                            <a class="nav-link" href="users.php">
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
                            <a class="nav-link active" href="prices.php">
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
                    <h1 class="h2">إدارة الأسعار</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPriceModal">
                            <i class="fas fa-plus me-1"></i>
                            إضافة سعر جديد
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

                <!-- قائمة الأسعار -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">قائمة الأسعار</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>نوع الصنف</th>
                                        <th>اسم الصنف</th>
                                        <th>السعر</th>
                                        <th>تاريخ السريان</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($prices as $price): ?>
                                    <tr>
                                        <td><?php echo $price['id']; ?></td>
                                        <td>
                                            <?php 
                                            switch($price['item_type']) {
                                                case 'Service':
                                                    echo 'خدمة';
                                                    break;
                                                case 'Lab Test':
                                                    echo 'فحص معمل';
                                                    break;
                                                case 'Medicine':
                                                    echo 'دواء';
                                                    break;
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo $price['item_name']; ?></td>
                                        <td><?php echo number_format($price['price'], 2); ?> ريال</td>
                                        <td><?php echo date('Y-m-d', strtotime($price['effective_date'])); ?></td>
                                        <td>
                                            <a href="?edit_id=<?php echo $price['id']; ?>" class="btn btn-sm btn-info">
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

    <!-- نموذج إضافة سعر جديد -->
    <div class="modal fade" id="addPriceModal" tabindex="-1" aria-labelledby="addPriceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addPriceModalLabel">إضافة سعر جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="item_type" class="form-label">نوع الصنف</label>
                            <select class="form-select" id="item_type" name="item_type" required onchange="updateItemOptions()">
                                <option value="">اختر نوع الصنف</option>
                                <option value="Service">خدمة</option>
                                <option value="Lab Test">فحص معمل</option>
                                <option value="Medicine">دواء</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="item_id" class="form-label">الصنف</label>
                            <select class="form-select" id="item_id" name="item_id" required>
                                <option value="">اختر الصنف أولاً</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="price" class="form-label">السعر</label>
                            <input type="number" step="0.01" class="form-control" id="price" name="price" required>
                        </div>
                        <div class="mb-3">
                            <label for="effective_date" class="form-label">تاريخ السريان</label>
                            <input type="date" class="form-control" id="effective_date" name="effective_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="add_price" class="btn btn-primary">حفظ</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateItemOptions() {
            const itemType = document.getElementById('item_type').value;
            const itemSelect = document.getElementById('item_id');

            // مسح الخيارات الحالية
            itemSelect.innerHTML = '<option value="">اختر الصنف</option>';

            if(itemType === 'Service') {
                <?php foreach($services as $service): ?>
                itemSelect.innerHTML += '<option value="<?php echo $service['id']; ?>"><?php echo $service['name']; ?></option>';
                <?php endforeach; ?>
            } else if(itemType === 'Lab Test') {
                <?php foreach($lab_tests as $lab_test): ?>
                itemSelect.innerHTML += '<option value="<?php echo $lab_test['id']; ?>"><?php echo $lab_test['name']; ?></option>';
                <?php endforeach; ?>
            } else if(itemType === 'Medicine') {
                <?php foreach($medicines as $medicine): ?>
                itemSelect.innerHTML += '<option value="<?php echo $medicine['id']; ?>"><?php echo $medicine['name']; ?></option>';
                <?php endforeach; ?>
            }
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
