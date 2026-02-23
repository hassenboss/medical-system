<?php
require_once '../../includes/auth.php';
checkRole(['Admin']);
require_once '../../config/db.php';

// الحصول على قائمة الخدمات
$services = $conn->query("SELECT * FROM services ORDER BY id DESC")->fetchAll();

// معالجة إضافة خدمة جديدة
if (
    isset($_POST['add_service']) ||
    (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        empty($_POST['service_id']) &&
        isset($_POST['name'], $_POST['price'], $_POST['type'])
    )
) {
    try {
        // التحقق من الحقول المطلوبة
        $required_fields = ['name', 'price', 'type'];
        foreach($required_fields as $field) {
            if(empty($_POST[$field])) {
                throw new Exception("حقل $field مطلوب");
            }
        }

        // تنظيف والتحقق من البيانات
        $name = htmlspecialchars(trim($_POST['name']));
        $price = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT);
        $type = htmlspecialchars(trim($_POST['type']));
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // التحقق من صحة السعر
        if($price === false || $price < 0) {
            throw new Exception("السعر غير صحيح");
        }

        // إدخال البيانات
        $stmt = $conn->prepare("INSERT INTO services (name, price, type, is_active) VALUES (?, ?, ?, ?)");
        $result = $stmt->execute([$name, $price, $type, $is_active]);

        if($result) {
            $service_id = $conn->lastInsertId();
            // تسجيل العملية
            $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Add Service', 'services', ?)");
            $logStmt->execute([$_SESSION['user_id'], $service_id]);
            $_SESSION['success'] = "✅ تم إضافة الخدمة بنجاح";
        } else {
            throw new Exception("فشل في إضافة الخدمة");
        }
    } catch(Exception $e) {
        error_log("Add Service Error: " . $e->getMessage());
        $_SESSION['error'] = "❌ " . $e->getMessage();
    }
    
    header("Location: services.php");
    exit();
}

// معالجة حذف الخدمة
if(isset($_POST['delete_service'])) {
    try {
        $service_id = filter_var($_POST['service_id'], FILTER_VALIDATE_INT);
        if(!$service_id) {
            throw new Exception("معرف الخدمة غير صحيح");
        }

        // التحقق من وجود الخدمة
        $stmt = $conn->prepare("SELECT id FROM services WHERE id = ?");
        $stmt->execute([$service_id]);
        if(!$stmt->fetch()) {
            throw new Exception("الخدمة غير موجودة");
        }

        // حذف الخدمة
        $stmt = $conn->prepare("DELETE FROM services WHERE id = ?");
        $result = $stmt->execute([$service_id]);

        if($result) {
            // تسجيل العملية
            $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Delete Service', 'services', ?)");
            $logStmt->execute([$_SESSION['user_id'], $service_id]);
            $_SESSION['success'] = "✅ تم حذف الخدمة بنجاح";
        } else {
            throw new Exception("فشل في حذف الخدمة");
        }
    } catch(Exception $e) {
        error_log("Delete Service Error: " . $e->getMessage());
        $_SESSION['error'] = "❌ " . $e->getMessage();
    }
    
    header("Location: services.php");
    exit();
}

// معالجة تحديث بيانات الخدمة
if (
    isset($_POST['update_service']) ||
    (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        !empty($_POST['service_id']) &&
        isset($_POST['name'], $_POST['price'], $_POST['type'])
    )
) {
    try {
        // التحقق من الحقول المطلوبة
        $required_fields = ['service_id', 'name', 'price', 'type'];
        foreach($required_fields as $field) {
            if(empty($_POST[$field])) {
                throw new Exception("حقل $field مطلوب");
            }
        }

        // الحصول على معرف الخدمة
        $service_id = filter_var($_POST['service_id'], FILTER_VALIDATE_INT);
        if(!$service_id) {
            throw new Exception("معرف الخدمة غير صحيح");
        }

        // تنظيف والتحقق من البيانات
        $name = htmlspecialchars(trim($_POST['name']));
        $price = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT);
        $type = htmlspecialchars(trim($_POST['type']));
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // التحقق من صحة السعر
        if($price === false || $price < 0) {
            throw new Exception("السعر غير صحيح");
        }

        // تحديث البيانات
        $stmt = $conn->prepare("UPDATE services SET name = ?, price = ?, type = ?, is_active = ? WHERE id = ?");
        $result = $stmt->execute([$name, $price, $type, $is_active, $service_id]);

        if($result) {
            // تسجيل العملية
            $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Update Service', 'services', ?)");
            $logStmt->execute([$_SESSION['user_id'], $service_id]);
            $_SESSION['success'] = "✅ تم تحديث بيانات الخدمة بنجاح";
        } else {
            throw new Exception("فشل في تحديث بيانات الخدمة");
        }
    } catch(Exception $e) {
        error_log("Update Service Error: " . $e->getMessage());
        $_SESSION['error'] = "❌ " . $e->getMessage();
    }
    
    header("Location: services.php");
    exit();
}

// البحث عن خدمة
$search_results = null;
if(isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = '%' . $_GET['search'] . '%';
    $stmt = $conn->prepare("SELECT * FROM services WHERE name LIKE ? OR type LIKE ? ORDER BY id DESC");
    $stmt->execute([$search_term, $search_term]);
    $search_results = $stmt->fetchAll();
}

// إحصائيات سريعة
$active_services = $conn->query("SELECT COUNT(*) FROM services WHERE is_active = 1")->fetchColumn();
$total_services = count($services);
$avg_price = $conn->query("SELECT AVG(price) FROM services WHERE is_active = 1")->fetchColumn() ?? 0;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الخدمات - 2050</title>
    <link href="../../assets/css/dashboardo.css" rel="stylesheet">
    <style>
        /* تخصيصات صفحة الخدمات */
        .service-card {
            background: var(--bg-card);
            border: var(--glass-border);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .service-card::before {
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
        
        .service-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-elevated), var(--shadow-neon-primary);
        }
        
        .service-card:hover::before {
            opacity: 1;
        }
        
        .service-icon {
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
        
        .service-info {
            flex: 1;
            min-width: 0;
        }
        
        .service-name {
            font-size: 1.125rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 0.25rem;
        }
        
        .service-type {
            font-size: 0.875rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .service-price {
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
            .service-card {
                flex-direction: column;
                text-align: center;
            }
            .service-icon {
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
            <li class="nav-item"><a class="nav-link active" href="services.php"><span class="icon" data-icon="🔧"></span>الخدمات</a></li>
            <li class="nav-item"><a class="nav-link" href="medicines.php"><span class="icon" data-icon="💊"></span>الأدوية</a></li>
            <li class="nav-item"><a class="nav-link" href="lab_tests.php"><span class="icon" data-icon="🧪"></span>فحوصات المعمل</a></li>
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
                <h1 class="mb-1">🔧 إدارة الخدمات</h1>
                <p class="text-muted mb-0">إدارة شاملة للخدمات الطبية والأسعار</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addServiceModal">
                <span class="icon" data-icon="➕"></span>
                إضافة خدمة
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
                <div class="value" style="color: var(--primary)"><?php echo $total_services; ?></div>
                <div class="label">إجمالي الخدمات</div>
            </div>
            <div class="stat-mini">
                <div class="value" style="color: var(--success)"><?php echo $active_services; ?></div>
                <div class="label">خدمات نشطة</div>
            </div>
            <div class="stat-mini">
                <div class="value" style="color: var(--warning)"><?php echo $total_services - $active_services; ?></div>
                <div class="label">خدمات غير نشطة</div>
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
                        <input type="text" name="search" placeholder="ابحث عن خدمة..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    </div>
                    <button type="submit" class="btn btn-outline">بحث</button>
                    <?php if(isset($_GET['search'])): ?>
                    <a href="services.php" class="btn btn-outline">مسح</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- 👥 قائمة الخدمات -->
        <div class="card animate-slide-in stagger-1">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6><span class="icon" data-icon="🔧"></span>قائمة الخدمات</h6>
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
                    $display_services = $search_results ?? $services;
                    if(empty($display_services)): 
                    ?>
                    <div class="col-12 text-center py-5">
                        <div class="icon" style="font-size: 3rem; opacity: 0.3" data-icon="🔧"></div>
                        <p class="text-muted mt-3">لا توجد خدمات لعرضها</p>
                        <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addServiceModal">
                            <span class="icon" data-icon="➕"></span> إضافة أول خدمة
                        </button>
                    </div>
                    <?php else: foreach($display_services as $service): ?>
                    <div class="col-xl-4 col-md-6">
                        <div class="service-card d-flex align-items-center gap-3">
                            <div class="service-icon">
                                <span class="icon" data-icon="<?php 
                                switch($service['type']) {
                                    case 'فحص': echo '🔍'; break;
                                    case 'تمريض': echo '💉'; break;
                                    case 'أشعة': echo '📷'; break;
                                    case 'تحليل': echo '🧪'; break;
                                    default: echo '⚕️'; 
                                }
                                ?>"></span>
                            </div>
                            <div class="service-info">
                                <div class="service-name"><?php echo htmlspecialchars($service['name']); ?></div>
                                <div class="service-type"><?php echo htmlspecialchars($service['type']); ?></div>
                                <div class="service-price"><?php echo number_format($service['price'], 2); ?> ريال</div>
                            </div>
                            <div class="d-flex flex-column gap-2">
                                <span class="status-badge <?php echo $service['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $service['is_active'] ? 'نشط' : 'غير نشط'; ?>
                                </span>
                                <button class="btn btn-outline btn-sm" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($service)); ?>)">
                                    <span class="icon" data-icon="✏️"></span>
                                </button>
                                <button class="btn btn-outline text-danger btn-sm" onclick="confirmDelete(<?php echo $service['id']; ?>, '<?php echo htmlspecialchars($service['name']); ?>')">
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
                                <th>اسم الخدمة</th>
                                <th>النوع</th>
                                <th>السعر</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                                  <th>حذف</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($display_services)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">لا توجد بيانات</td></tr>
                            <?php else: foreach($display_services as $service): ?>
                            <tr>
                                <td><?php echo $service['id']; ?></td>
                                <td><?php echo htmlspecialchars($service['name']); ?></td>
                                <td><?php echo htmlspecialchars($service['type']); ?></td>
                                <td><?php echo number_format($service['price'], 2); ?> ريال</td>
                                <td>
                                    <span class="status-badge <?php echo $service['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $service['is_active'] ? 'نشط' : 'غير نشط'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-outline btn-sm" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($service)); ?>)">
                                        <span class="icon" data-icon="✏️"></span>
                                    </button>
                                </td>
                                <td>
                                   <button class="btn btn-outline text-danger btn-sm" onclick="confirmDelete(<?php echo $service['id']; ?>, '<?php echo htmlspecialchars($service['name']); ?>')">
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

    <!-- ➕ نموذج إضافة خدمة -->
    <div class="modal fade" id="addServiceModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><span class="icon" data-icon="➕"></span> إضافة خدمة جديدة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">اسم الخدمة <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" required placeholder="أدخل اسم الخدمة">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">نوع الخدمة <span class="text-danger">*</span></label>
                            <select class="form-select" name="type" required>
                                <option value="">اختر النوع</option>
                                <option value="فحص">فحص</option>
                                <option value="تمريض">تمريض</option>
                                <option value="أشعة">أشعة</option>
                                <option value="تحليل">تحليل</option>
                                <option value="أخرى">أخرى</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">السعر (ريال) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="price" step="0.01" min="0" required placeholder="0.00">
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="is_active" id="is_active_add" checked>
                            <label class="form-check-label" for="is_active_add">تفعيل الخدمة</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="add_service" class="btn btn-primary">حفظ الخدمة</button>
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
                <p class="mt-3">هل أنت متأكد من حذف الخدمة <strong id="delete_name"></strong>؟</p>
                <small class="text-muted">لا يمكن التراجع عن هذا الإجراء</small>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-outline" data-bs-dismiss="modal">إلغاء</button>
                <form method="POST" action="" id="deleteForm">
                    <input type="hidden" name="service_id" id="delete_id">
                    <button type="submit" name="delete_service" class="btn btn-danger">نعم، احذف</button>
                </form>
            </div>
        </div>
    </div>
</div>


    <!-- ✏️ نموذج تعديل خدمة -->
    <div class="modal fade" id="editServiceModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><span class="icon" data-icon="✏️"></span> تعديل بيانات الخدمة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" id="editForm">
                    <input type="hidden" name="service_id" id="edit_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">اسم الخدمة <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="edit_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">نوع الخدمة <span class="text-danger">*</span></label>
                            <select class="form-select" name="type" id="edit_type" required>
                                <option value="فحص">فحص</option>
                                <option value="تمريض">تمريض</option>
                                <option value="أشعة">أشعة</option>
                                <option value="تحليل">تحليل</option>
                                <option value="أخرى">أخرى</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">السعر (ريال) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="price" id="edit_price" step="0.01" min="0" required>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="is_active" id="edit_is_active">
                            <label class="form-check-label" for="edit_is_active">خدمة نشطة</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="update_service" class="btn btn-primary">تحديث البيانات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 🔄 سكريبت التفاعل -->
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
function openEditModal(service) {
    document.getElementById('edit_id').value = service.id;
    document.getElementById('edit_name').value = service.name;
    document.getElementById('edit_type').value = service.type;
    document.getElementById('edit_price').value = service.price;
    document.getElementById('edit_is_active').checked = service.is_active == 1;
    
    const modal = new bootstrap.Modal(document.getElementById('editServiceModal'));
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
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // ===== 🪟 تهيئة المودال يدوياً =====
    function initModals() {
        document.querySelectorAll('.modal').forEach(modal => {
            if(!modal._initialized) {
                modal._initialized = true;
                modal.style.display = 'none';
                
                // زر الإغلاق
                modal.querySelector('[data-bs-dismiss="modal"], .btn-close')?.addEventListener('click', () => {
                    modal.classList.remove('show');
                    modal.style.display = 'none';
                    document.body.style.overflow = '';
                });
                
                // النقر خارج المودال
                modal.addEventListener('click', (e) => {
                    if(e.target === modal) {
                        modal.classList.remove('show');
                        modal.style.display = 'none';
                        document.body.style.overflow = '';
                    }
                });
            }
        });
    }
    initModals();

    // ===== ➕ فتح مودال الإضافة =====
    const addBtn = document.querySelector('[data-bs-target="#addServiceModal"]');
    if(addBtn) {
        addBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const modal = document.getElementById('addServiceModal');
            if(modal) {
                modal.classList.add('show');
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
                // تركيز على أول حقل
                setTimeout(() => modal.querySelector('input')?.focus(), 100);
            }
        });
    }

    // ===== ✏️ فتح مودال التعديل =====
    window.openEditModal = function(service) {
        // ملء الحقول
        document.getElementById('edit_id').value = service.id;
        document.getElementById('edit_name').value = service.name || '';
        document.getElementById('edit_type').value = service.type || '';
        document.getElementById('edit_price').value = service.price || '';
        document.getElementById('edit_is_active').checked = service.is_active == 1;
        
        // فتح المودال
        const modal = document.getElementById('editServiceModal');
        if(modal) {
            modal.classList.add('show');
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
            setTimeout(() => modal.querySelector('input')?.focus(), 100);
        }
    };

    // ===== 🗑️ تأكيد الحذف =====
    window.confirmDelete = function(id, name) {
        document.getElementById('delete_id').value = id;
        document.getElementById('delete_name').textContent = name;
        const modal = document.getElementById('deleteModal');
        if(modal) {
            modal.classList.add('show');
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
    };

    // ===== 🔍 البحث الفوري =====
    const searchInput = document.querySelector('input[name="search"]');
    if(searchInput) {
        searchInput.addEventListener('input', function() {
            const term = this.value.toLowerCase();
            document.querySelectorAll('.service-card').forEach(card => {
                const name = card.querySelector('.service-name')?.textContent.toLowerCase() || '';
                const type = card.querySelector('.service-type')?.textContent.toLowerCase() || '';
                card.style.display = (name.includes(term) || type.includes(term)) ? '' : 'none';
            });
        });
    }

    // ===== 🎨 تأثيرات إضافية =====
    // تأثير Hover للبطاقات
    document.querySelectorAll('.service-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-4px)';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });

    // منع التجمد عند النقر المتكرر
    document.querySelectorAll('button, .btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            if(this.dataset.processing) {
                e.preventDefault();
                return false;
            }
            // منع النقر المتكرر السريع
            this.dataset.processing = 'true';
            setTimeout(() => delete this.dataset.processing, 1000);
        });
    });

    // ===== 🔄 إغلاق المودال عند الضغط على ESC =====
    document.addEventListener('keydown', function(e) {
        if(e.key === 'Escape') {
            document.querySelectorAll('.modal.show').forEach(modal => {
                modal.classList.remove('show');
                modal.style.display = 'none';
                document.body.style.overflow = '';
            });
        }
    });

    // ===== 🎯 معالجة إرسال النماذج =====
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if(submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>جاري الحفظ...';
            }
        });
    });

    // إعادة تمكين الأزرار بعد التحميل
    window.addEventListener('load', function() {
        document.querySelectorAll('button[type="submit"]').forEach(btn => {
            btn.disabled = false;
            btn.innerHTML = btn.innerHTML.replace(/<span class="spinner-border spinner-border-sm me-2"><\/span>\s*جاري الحفظ\.\.\./, btn.dataset.originalText || 'حفظ');
        });
    });
});
</script>


    <script src="../../assets/js/bootstrap.min.js"></script>
</body>
</html>
