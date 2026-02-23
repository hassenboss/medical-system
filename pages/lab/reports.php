<?php
require_once '../../includes/auth.php';
checkRole(['Admin', 'Lab Technician']);

require_once '../../config/db.php';

// معالجة حذف تقرير
if(isset($_GET['delete_report'])) {
    try {
        $report_id = $_GET['delete_report'];
        $conn->query("DELETE FROM lab_reports WHERE id = $report_id");

        // تسجيل النشاط
        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Delete Lab Report', 'lab_reports', ?)");
        $logStmt->execute([$_SESSION['user_id'], $report_id]);

        $success = "تم حذف التقرير بنجاح";
    } catch(PDOException $e) {
        $error = "حدث خطأ: " . $e->getMessage();
    }
}

// الحصول على قائمة التقارير
$reports = $conn->query("SELECT lr.*, u.full_name as created_by_name 
                         FROM lab_reports lr 
                         JOIN users u ON lr.created_by = u.id
                         ORDER BY lr.created_date DESC")->fetchAll();

// الحصول على تفاصيل تقرير محدد للتعديل
$selected_report = null;
if(isset($_GET['view_report'])) {
    $report_id = $_GET['view_report'];
    $selected_report = $conn->query("SELECT lr.*, u.full_name as created_by_name 
                                    FROM lab_reports lr 
                                    JOIN users u ON lr.created_by = u.id
                                    WHERE lr.id = $report_id")->fetch();
}

// معالجة إضافة تقرير جديد
if(isset($_POST['add_report'])) {
    try {
        $stmt = $conn->prepare("INSERT INTO lab_reports (title, content, report_type, created_by, created_date) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([
            $_POST['title'],
            $_POST['content'],
            $_POST['report_type'],
            $_SESSION['user_id']
        ]);

        // تسجيل النشاط
        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Add Lab Report', 'lab_reports', ?)");
        $logStmt->execute([$_SESSION['user_id'], $conn->lastInsertId()]);

        $success = "تم إضافة التقرير بنجاح";
        header("Location: reports.php");
    } catch(PDOException $e) {
        $error = "حدث خطأ: " . $e->getMessage();
    }
}

// معالجة تحديث تقرير
if(isset($_POST['update_report'])) {
    try {
        $stmt = $conn->prepare("UPDATE lab_reports SET title = ?, content = ?, report_type = ?, updated_by = ?, updated_date = NOW() WHERE id = ?");
        $stmt->execute([
            $_POST['title'],
            $_POST['content'],
            $_POST['report_type'],
            $_SESSION['user_id'],
            $_POST['report_id']
        ]);

        // تسجيل النشاط
        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Update Lab Report', 'lab_reports', ?)");
        $logStmt->execute([$_SESSION['user_id'], $_POST['report_id']]);

        $success = "تم تحديث التقرير بنجاح";
        header("Location: reports.php");
    } catch(PDOException $e) {
        $error = "حدث خطأ: " . $e->getMessage();
    }
}

// الحصول على تفاصيل تقرير محدد للتعديل
$edit_report = null;
if(isset($_GET['edit_report'])) {
    $report_id = $_GET['edit_report'];
    $edit_report = $conn->query("SELECT * FROM lab_reports WHERE id = $report_id")->fetch();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقارير المعمل</title>
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
                            <a class="nav-link" href="tests.php">
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
                            <a class="nav-link active" href="reports.php">
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
                    <h1 class="h2">تقارير المعمل</h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addReportModal">
                        <i class="fas fa-plus me-1"></i>
                        إضافة تقرير جديد
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

                <!-- قائمة التقارير -->
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>قائمة التقارير</h5>
                        <span class="badge bg-light text-dark"><?php echo count($reports); ?> تقرير</span>
                    </div>
                    <div class="card-body">
                        <?php if(count($reports) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>الرقم</th>
                                        <th>العنوان</th>
                                        <th>النوع</th>
                                        <th>تاريخ الإنشاء</th>
                                        <th>أنشأ بواسطة</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($reports as $report): ?>
                                    <tr>
                                        <td><span class="badge bg-primary">#<?php echo $report['id']; ?></span></td>
                                        <td><?php echo $report['title']; ?></td>
                                        <td>
                                            <?php 
                                            $typeBadge = 'bg-secondary';
                                            $typeText = 'غير محدد';

                                            switch($report['report_type']) {
                                                case 'daily':
                                                    $typeBadge = 'bg-info';
                                                    $typeText = 'يومي';
                                                    break;
                                                case 'weekly':
                                                    $typeBadge = 'bg-success';
                                                    $typeText = 'أسبوعي';
                                                    break;
                                                case 'monthly':
                                                    $typeBadge = 'bg-warning';
                                                    $typeText = 'شهري';
                                                    break;
                                                case 'yearly':
                                                    $typeBadge = 'bg-danger';
                                                    $typeText = 'سنوي';
                                                    break;
                                                case 'custom':
                                                    $typeBadge = 'bg-primary';
                                                    $typeText = 'مخصص';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $typeBadge; ?>"><?php echo $typeText; ?></span>
                                        </td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($report['created_date'])); ?></td>
                                        <td><?php echo $report['created_by_name']; ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="?view_report=<?php echo $report['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="?edit_report=<?php echo $report['id']; ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?delete_report=<?php echo $report['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('هل أنت متأكد من حذف هذا التقرير؟')">
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
                            <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                            <h5>لا توجد تقارير</h5>
                            <p class="text-muted">لم يتم إنشاء أي تقارير بعد</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if($selected_report): ?>
                <!-- عرض التقرير -->
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">عرض التقرير</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">العنوان</label>
                                <p class="form-control-plaintext"><?php echo $selected_report['title']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">النوع</label>
                                <p class="form-control-plaintext"><?php echo $selected_report['report_type']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">تاريخ الإنشاء</label>
                                <p class="form-control-plaintext"><?php echo date('Y-m-d H:i', strtotime($selected_report['created_date'])); ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">أنشأ بواسطة</label>
                                <p class="form-control-plaintext"><?php echo $selected_report['created_by_name']; ?></p>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">المحتوى</label>
                            <div class="border rounded p-3 bg-light">
                                <?php echo nl2br($selected_report['content']); ?>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="reports.php" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i>
                                إغلاق
                            </a>
                            <a href="?edit_report=<?php echo $selected_report['id']; ?>" class="btn btn-warning">
                                <i class="fas fa-edit me-1"></i>
                                تعديل
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if($edit_report): ?>
                <!-- تعديل التقرير -->
                <div class="card mt-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">تعديل التقرير</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="report_id" value="<?php echo $edit_report['id']; ?>">

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="title" class="form-label">العنوان</label>
                                    <input type="text" class="form-control" id="title" name="title" value="<?php echo $edit_report['title']; ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="report_type" class="form-label">نوع التقرير</label>
                                    <select class="form-select" id="report_type" name="report_type" required>
                                        <option value="daily" <?php echo $edit_report['report_type'] == 'daily' ? 'selected' : ''; ?>>يومي</option>
                                        <option value="weekly" <?php echo $edit_report['report_type'] == 'weekly' ? 'selected' : ''; ?>>أسبوعي</option>
                                        <option value="monthly" <?php echo $edit_report['report_type'] == 'monthly' ? 'selected' : ''; ?>>شهري</option>
                                        <option value="yearly" <?php echo $edit_report['report_type'] == 'yearly' ? 'selected' : ''; ?>>سنوي</option>
                                        <option value="custom" <?php echo $edit_report['report_type'] == 'custom' ? 'selected' : ''; ?>>مخصص</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="content" class="form-label">المحتوى</label>
                                <textarea class="form-control" id="content" name="content" rows="10" required><?php echo $edit_report['content']; ?></textarea>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="reports.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i>
                                    إلغاء
                                </a>
                                <button type="submit" name="update_report" class="btn btn-warning">
                                    <i class="fas fa-edit me-1"></i>
                                    تحديث التقرير
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- نافذة إضافة تقرير جديد -->
    <div class="modal fade" id="addReportModal" tabindex="-1" aria-labelledby="addReportModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addReportModalLabel">إضافة تقرير جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="modal_title" class="form-label">العنوان</label>
                                <input type="text" class="form-control" id="modal_title" name="title" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="modal_report_type" class="form-label">نوع التقرير</label>
                                <select class="form-select" id="modal_report_type" name="report_type" required>
                                    <option value="daily">يومي</option>
                                    <option value="weekly">أسبوعي</option>
                                    <option value="monthly">شهري</option>
                                    <option value="yearly">سنوي</option>
                                    <option value="custom">مخصص</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="modal_content" class="form-label">المحتوى</label>
                            <textarea class="form-control" id="modal_content" name="content" rows="10" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="add_report" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>
                            إضافة التقرير
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
