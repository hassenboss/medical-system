<?php
require_once '../../includes/auth.php';
checkRole(['Admin']);
require_once '../../config/db.php';

// الحصول على قائمة فحوصات المعمل
$lab_tests = $conn->query("SELECT * FROM lab_tests ORDER BY id DESC")->fetchAll();

// معالجة إضافة فحص جديد
if (
    isset($_POST['add_lab_test']) ||
    (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        empty($_POST['lab_test_id']) &&
        isset($_POST['name'], $_POST['price'])
    )
) {
    try {
        // التحقق من الحقول المطلوبة
        $required_fields = ['name', 'price'];
        foreach($required_fields as $field) {
            if(empty($_POST[$field])) {
                throw new Exception("حقل $field مطلوب");
            }
        }

        // تنظيف والتحقق من البيانات
        $name = htmlspecialchars(trim($_POST['name']));
        $price = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT);
        $description = htmlspecialchars(trim($_POST['description'] ?? ''));
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // التحقق من صحة السعر
        if($price === false || $price < 0) {
            throw new Exception("السعر غير صحيح");
        }

        // إدخال البيانات
        $stmt = $conn->prepare("INSERT INTO lab_tests (name, price, description, is_active) VALUES (?, ?, ?, ?)");
        $result = $stmt->execute([$name, $price, $description, $is_active]);

        if($result) {
            $lab_test_id = $conn->lastInsertId();
            // تسجيل العملية
            $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Add Lab Test', 'lab_tests', ?)");
            $logStmt->execute([$_SESSION['user_id'], $lab_test_id]);
            $_SESSION['success'] = "✅ تم إضافة الفحص بنجاح";
        } else {
            throw new Exception("فشل في إضافة الفحص");
        }
    } catch(Exception $e) {
        error_log("Add Lab Test Error: " . $e->getMessage());
        $_SESSION['error'] = "❌ " . $e->getMessage();
    }
    
    header("Location: lab_tests.php");
    exit();
}

// معالجة تحديث بيانات الفحص
if (
    isset($_POST['update_lab_test']) ||
    (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        !empty($_POST['lab_test_id']) &&
        isset($_POST['name'], $_POST['price'])
    )
) {
    try {
        // التحقق من الحقول المطلوبة
        $required_fields = ['lab_test_id', 'name', 'price'];
        foreach($required_fields as $field) {
            if(empty($_POST[$field])) {
                throw new Exception("حقل $field مطلوب");
            }
        }

        // الحصول على معرف الفحص
        $lab_test_id = filter_var($_POST['lab_test_id'], FILTER_VALIDATE_INT);
        if(!$lab_test_id) {
            throw new Exception("معرف الفحص غير صحيح");
        }

        // تنظيف والتحقق من البيانات
        $name = htmlspecialchars(trim($_POST['name']));
        $price = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT);
        $description = htmlspecialchars(trim($_POST['description'] ?? ''));
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // التحقق من صحة السعر
        if($price === false || $price < 0) {
            throw new Exception("السعر غير صحيح");
        }

        // تحديث البيانات
        $stmt = $conn->prepare("UPDATE lab_tests SET name = ?, price = ?, description = ?, is_active = ? WHERE id = ?");
        $result = $stmt->execute([$name, $price, $description, $is_active, $lab_test_id]);

        if($result) {
            // تسجيل العملية
            $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Update Lab Test', 'lab_tests', ?)");
            $logStmt->execute([$_SESSION['user_id'], $lab_test_id]);
            $_SESSION['success'] = "✅ تم تحديث بيانات الفحص بنجاح";
        } else {
            throw new Exception("فشل في تحديث بيانات الفحص");
        }
    } catch(Exception $e) {
        error_log("Update Lab Test Error: " . $e->getMessage());
        $_SESSION['error'] = "❌ " . $e->getMessage();
    }
    
    header("Location: lab_tests.php");
    exit();
}

// معالجة حذف الفحص
if(isset($_POST['delete_lab_test'])) {
    try {
        $lab_test_id = filter_var($_POST['lab_test_id'], FILTER_VALIDATE_INT);
        if(!$lab_test_id) {
            throw new Exception("معرف الفحص غير صحيح");
        }

        // التحقق من وجود الفحص
        $stmt = $conn->prepare("SELECT id FROM lab_tests WHERE id = ?");
        $stmt->execute([$lab_test_id]);
        if(!$stmt->fetch()) {
            throw new Exception("الفحص غير موجود");
        }

        // حذف الفحص
        $stmt = $conn->prepare("DELETE FROM lab_tests WHERE id = ?");
        $result = $stmt->execute([$lab_test_id]);

        if($result) {
            // تسجيل العملية
            $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Delete Lab Test', 'lab_tests', ?)");
            $logStmt->execute([$_SESSION['user_id'], $lab_test_id]);
            $_SESSION['success'] = "✅ تم حذف الفحص بنجاح";
        } else {
            throw new Exception("فشل في حذف الفحص");
        }
    } catch(Exception $e) {
        error_log("Delete Lab Test Error: " . $e->getMessage());
        $_SESSION['error'] = "❌ " . $e->getMessage();
    }
    
    header("Location: lab_tests.php");
    exit();
}

// البحث عن فحص
$search_results = null;
if(isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = '%' . $_GET['search'] . '%';
    $stmt = $conn->prepare("SELECT * FROM lab_tests WHERE name LIKE ? OR description LIKE ? ORDER BY id DESC");
    $stmt->execute([$search_term, $search_term]);
    $search_results = $stmt->fetchAll();
}

// إحصائيات سريعة
$active_tests = $conn->query("SELECT COUNT(*) FROM lab_tests WHERE is_active = 1")->fetchColumn();
$total_tests = count($lab_tests);
$avg_price = $conn->query("SELECT AVG(price) FROM lab_tests WHERE is_active = 1")->fetchColumn() ?? 0;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة فحوصات المعمل - 2050</title>
    <link href="../../assets/css/dashboardo.css" rel="stylesheet">
    <style>
        /* تخصيصات صفحة فحوصات المعمل */
        .test-card {
            background: var(--bg-card);
            border: var(--glass-border);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .test-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 4px;
            height: 100%;
            background: var(--gradient-primary);
            opacity: 0;
            transition: var(--transition);
        }
        
        .test-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-elevated), var(--shadow-neon-primary);
        }
        
        .test-card:hover::before {
            opacity: 1;
        }
        
        .test-icon {
            width: 60px;
            height: 60px;
            border-radius: var(--radius-lg);
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #fff;
            box-shadow: 0 8px 24px var(--primary-glow);
        }
        
        .test-info {
            flex: 1;
            min-width: 0;
        }
        
        .test-name {
            font-size: 1.125rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 0.25rem;
        }
        
        .test-description {
            font-size: 0.875rem;
            color: rgba(241, 245, 249, 0.7);
            margin-bottom: 0.5rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .test-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--success);
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-badge.active {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
            border: 1px solid var(--success);
        }
        
        .status-badge.inactive {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
            border: 1px solid var(--danger);
        }
        
        .search-glass {
            background: rgba(15, 23, 42, 0.6);
            border: var(--glass-border);
            border-radius: var(--radius-lg);
            padding: 0.5rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            max-width: 400px;
        }
        
        .search-glass input {
            background: transparent;
            border: none;
            color: #fff;
            width: 100%;
            outline: none;
        }
        
        .search-glass input::placeholder {
            color: rgba(241, 245, 249, 0.5);
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-mini {
            background: var(--bg-card);
            border: var(--glass-border);
            border-radius: var(--radius-lg);
            padding: 1rem;
            text-align: center;
        }
        
        .stat-mini .value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #fff;
        }
        
        .stat-mini .label {
            font-size: 0.75rem;
            color: rgba(241, 245, 249, 0.7);
        }
        
        @media (max-width: 768px) {
            .test-card {
                flex-direction: column;
                text-align: center;
            }
            .test-icon {
                margin: 0 auto 1rem;
            }
            .search-glass {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- 🧭 شريط التنقل -->
    <nav class="navbar">
        <div class="d-flex align-items-center gap-3">
            <button class="navbar-toggler d-lg-none" id="sidebarToggle">
                <span class="icon" data-icon="☰"></span>
            </button>
            <a class="navbar-brand" href="#">
                <span class="icon" data-icon="🏥"></span>
                <span>نظام نوف الطبي</span>
            </a>
        </div>
        <div class="user-info">
            <span class="user-name">
                <span class="icon" data-icon="👤"></span>
                <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'مدير النظام'); ?>
            </span>
            <a href="../../includes/auth.php?logout=true" class="btn-logout">
                <span class="icon" data-icon="🚪"></span>
                خروج
            </a>
        </div>
    </nav>

    <!-- 📋 القائمة الجانبية -->
    <nav class="sidebar" id="sidebar">
        <div class="nav-header">
            <h6>القائمة الرئيسية</h6>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link" href="dashboard.php"><span class="icon" data-icon="📊"></span>لوحة التحكم</a></li>
            <li class="nav-item"><a class="nav-link" href="patients.php"><span class="icon" data-icon="🤒"></span>المرضى</a></li>
            <li class="nav-item"><a class="nav-link" href="doctors.php"><span class="icon" data-icon="🩺"></span>الأطباء</a></li>
            <li class="nav-item"><a class="nav-link" href="services.php"><span class="icon" data-icon="🔧"></span>الخدمات</a></li>
            <li class="nav-item"><a class="nav-link" href="medicines.php"><span class="icon" data-icon="💊"></span>الأدوية</a></li>
            <li class="nav-item"><a class="nav-link active" href="lab_tests.php"><span class="icon" data-icon="🧪"></span>فحوصات المعمل</a></li>
            <div class="nav-header mt-3"><h6>الإدارة</h6></div>
            <li class="nav-item"><a class="nav-link" href="prices.php"><span class="icon" data-icon="💰"></span>الأسعار</a></li>
            <li class="nav-item"><a class="nav-link" href="users.php"><span class="icon" data-icon="👥"></span>المستخدمون</a></li>
            <li class="nav-item"><a class="nav-link" href="reports.php"><span class="icon" data-icon="📈"></span>التقارير</a></li>
            <li class="nav-item"><a class="nav-link" href="settings.php"><span class="icon" data-icon="⚙️"></span>الإعدادات</a></li>
        </ul>
    </nav>
    
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- 🎬 المحتوى الرئيسي -->
    <main>
        <!-- رأس الصفحة -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h1 class="mb-1">🧪 إدارة فحوصات المعمل</h1>
                <p class="text-muted mb-0">إدارة شاملة لفحوصات المعمل والأسعار</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLabTestModal">
                <span class="icon" data-icon="➕"></span>
                إضافة فحص
            </button>
        </div>

        <!-- 🔔 التنبيهات -->
        <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show animate-fade-in">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show animate-fade-in">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- 📊 إحصائيات سريعة -->
        <div class="stats-row">
            <div class="stat-mini">
                <div class="value" style="color: var(--primary)"><?php echo $total_tests; ?></div>
                <div class="label">إجمالي الفحوصات</div>
            </div>
            <div class="stat-mini">
                <div class="value" style="color: var(--success)"><?php echo $active_tests; ?></div>
                <div class="label">فحوصات نشطة</div>
            </div>
            <div class="stat-mini">
                <div class="value" style="color: var(--warning)"><?php echo $total_tests - $active_tests; ?></div>
                <div class="label">فحوصات غير نشطة</div>
            </div>
            <div class="stat-mini">
                <div class="value" style="color: var(--info)"><?php echo number_format($avg_price, 0); ?></div>
                <div class="label">متوسط السعر</div>
            </div>
        </div>

        <!-- 🔍 شريط البحث -->
        <div class="card mb-4 animate-slide-in">
            <div class="card-body">
                <form method="GET" action="" class="d-flex gap-3 flex-wrap">
                    <div class="search-glass">
                        <span class="icon" data-icon="🔍"></span>
                        <input type="text" name="search" placeholder="ابحث عن فحص..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    </div>
                    <button type="submit" class="btn btn-outline">بحث</button>
                    <?php if(isset($_GET['search'])): ?>
                    <a href="lab_tests.php" class="btn btn-outline">مسح</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- 👥 قائمة الفحوصات -->
        <div class="card animate-slide-in stagger-1">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6><span class="icon" data-icon="🧪"></span>قائمة الفحوصات</h6>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline btn-sm" onclick="toggleView('grid')">
                        <span class="icon" data-icon="▦"></span> شبكة
                    </button>
                    <button class="btn btn-outline btn-sm" onclick="toggleView('table')">
                        <span class="icon" data-icon="☰"></span> جدول
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- عرض الشبكة -->
                <div id="gridView" class="row g-3">
                    <?php 
                    $display_tests = $search_results ?? $lab_tests;
                    if(empty($display_tests)): 
                    ?>
                    <div class="col-12 text-center py-5">
                        <div class="icon" style="font-size: 3rem; opacity: 0.3" data-icon="🧪"></div>
                        <p class="text-muted mt-3">لا توجد فحوصات لعرضها</p>
                        <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addLabTestModal">
                            <span class="icon" data-icon="➕"></span> إضافة أول فحص
                        </button>
                    </div>
                    <?php else: foreach($display_tests as $test): ?>
                    <div class="col-xl-4 col-md-6">
                        <div class="test-card d-flex align-items-center gap-3">
                            <div class="test-icon">
                                <span class="icon" data-icon="🧪"></span>
                            </div>
                            <div class="test-info">
                                <div class="test-name"><?php echo htmlspecialchars($test['name']); ?></div>
                                <div class="test-description"><?php echo htmlspecialchars($test['description'] ?: 'لا يوجد وصف'); ?></div>
                                <div class="test-price"><?php echo number_format($test['price'], 2); ?> ريال</div>
                            </div>
                            <div class="d-flex flex-column gap-2">
                                <span class="status-badge <?php echo $test['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $test['is_active'] ? 'نشط' : 'غير نشط'; ?>
                                </span>
                                <button class="btn btn-outline btn-sm" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($test)); ?>)">
                                    <span class="icon" data-icon="✏️"></span>
                                </button>
                                <button class="btn btn-outline text-danger btn-sm" onclick="confirmDelete(<?php echo $test['id']; ?>, '<?php echo htmlspecialchars($test['name']); ?>')">
                                    <span class="icon" data-icon="🗑️"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>

                <!-- عرض الجدول (مخفي افتراضياً) -->
                <div id="tableView" class="table-responsive" style="display: none;">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>اسم الفحص</th>
                                <th>الوصف</th>
                                <th>السعر</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($display_tests)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">لا توجد بيانات</td></tr>
                            <?php else: foreach($display_tests as $test): ?>
                            <tr>
                                <td><?php echo $test['id']; ?></td>
                                <td><?php echo htmlspecialchars($test['name']); ?></td>
                                <td><?php echo htmlspecialchars($test['description'] ?: 'لا يوجد وصف'); ?></td>
                                <td><?php echo number_format($test['price'], 2); ?> ريال</td>
                                <td>
                                    <span class="status-badge <?php echo $test['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $test['is_active'] ? 'نشط' : 'غير نشط'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-outline btn-sm" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($test)); ?>)">
                                        <span class="icon" data-icon="✏️"></span>
                                    </button>
                                    <button class="btn btn-outline text-danger btn-sm" onclick="confirmDelete(<?php echo $test['id']; ?>, '<?php echo htmlspecialchars($test['name']); ?>')">
                                        <span class="icon" data-icon="🗑️"></span>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- ➕ نموذج إضافة فحص -->
    <div class="modal fade" id="addLabTestModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><span class="icon" data-icon="➕"></span> إضافة فحص جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">اسم الفحص <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" required placeholder="أدخل اسم الفحص">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">الوصف</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="أدخل وصف الفحص"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">السعر (ريال) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="price" step="0.01" min="0" required placeholder="0.00">
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="is_active" id="is_active_add" checked>
                            <label class="form-check-label" for="is_active_add">تفعيل الفحص</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="add_lab_test" class="btn btn-primary">حفظ الفحص</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ✏️ نموذج تعديل فحص -->
    <div class="modal fade" id="editLabTestModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><span class="icon" data-icon="✏️"></span> تعديل بيانات الفحص</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" id="editForm">
                    <input type="hidden" name="lab_test_id" id="edit_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">اسم الفحص <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="edit_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">الوصف</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">السعر (ريال) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="price" id="edit_price" step="0.01" min="0" required>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="is_active" id="edit_is_active">
                            <label class="form-check-label" for="edit_is_active">فحص نشط</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="update_lab_test" class="btn btn-primary">تحديث البيانات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 🗑️ نموذج تأكيد الحذف -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><span class="icon" data-icon="⚠️"></span> تأكيد الحذف</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="icon" style="font-size: 3rem; color: var(--danger)" data-icon="🗑️"></div>
                    <p class="mt-3">هل أنت متأكد من حذف الفحص <strong id="delete_name"></strong>؟</p>
                    <small class="text-muted">لا يمكن التراجع عن هذا الإجراء</small>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-outline" data-bs-dismiss="modal">إلغاء</button>
                    <form method="POST" action="" id="deleteForm">
                        <input type="hidden" name="lab_test_id" id="delete_id">
                        <button type="submit" name="delete_lab_test" class="btn btn-danger">نعم، احذف</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- 🔄 سكريبت التفاعل -->
    <script>
    // Toggle Sidebar
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggleBtn = document.getElementById('sidebarToggle');
    
    function toggleSidebar() {
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
        document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
    }
    toggleBtn?.addEventListener('click', toggleSidebar);
    overlay?.addEventListener('click', toggleSidebar);

    // Toggle View (Grid/Table)
    function toggleView(view) {
        document.getElementById('gridView').style.display = view === 'grid' ? '' : 'none';
        document.getElementById('tableView').style.display = view === 'table' ? '' : 'none';
    }

    // Open Edit Modal
    function openEditModal(test) {
        document.getElementById('edit_id').value = test.id;
        document.getElementById('edit_name').value = test.name;
        document.getElementById('edit_description').value = test.description || '';
        document.getElementById('edit_price').value = test.price;
        document.getElementById('edit_is_active').checked = test.is_active == 1;
        
        const modal = new bootstrap.Modal(document.getElementById('editLabTestModal'));
        modal.show();
    }

    // Confirm Delete
    function confirmDelete(id, name) {
        document.getElementById('delete_id').value = id;
        document.getElementById('delete_name').textContent = name;
        const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
        modal.show();
    }

    // Navbar scroll effect
    window.addEventListener('scroll', () => {
        document.querySelector('.navbar')?.classList.toggle('scrolled', window.scrollY > 10);
    });

    // Stagger animation on load
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.animate-fade-in, .animate-slide-in').forEach((el, i) => {
            el.style.opacity = '0';
            setTimeout(() => { el.style.opacity = '1'; }, 100 + i * 50);
        });
    });
    </script>
    
    
    <script src="../../assets/js/bootstrap.min.js"></script>
</body>
</html>
