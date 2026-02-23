<?php
require_once '../../includes/auth.php';
checkRole(['Admin']);
require_once '../../config/db.php';

// ============================================
// 📋 معالجة البيانات
// ============================================

// الحصول على قائمة الأطباء
$doctors = $conn->query("SELECT * FROM doctors ORDER BY id DESC")->fetchAll();

// معالجة إضافة طبيب جديد
// معالجة إضافة طبيب جديد
if (
    isset($_POST['add_doctor']) ||
    (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        empty($_POST['doctor_id']) &&
        isset($_POST['full_name'], $_POST['specialization'], $_POST['consultation_fee'])
    )
) {
    try {
        // التحقق من الحقول المطلوبة
        $required_fields = ['full_name', 'specialization', 'consultation_fee'];
        foreach($required_fields as $field) {
            if(empty($_POST[$field])) {
                throw new Exception("حقل $field مطلوب");
            }
        }

        // تنظيف والتحقق من البيانات
        $full_name = htmlspecialchars(trim($_POST['full_name']));
        $specialization = htmlspecialchars(trim($_POST['specialization']));
        $phone = htmlspecialchars(trim($_POST['phone'] ?? ''));
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $consultation_fee = filter_var($_POST['consultation_fee'], FILTER_VALIDATE_FLOAT);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // التحقق من صحة البريد الإلكتروني
        if(!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("البريد الإلكتروني غير صحيح");
        }

        // إدخال البيانات
        $stmt = $conn->prepare("INSERT INTO doctors (full_name, specialization, phone, email, consultation_fee, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([$full_name, $specialization, $phone, $email, $consultation_fee, $is_active]);

        if($result) {
            $doctor_id = $conn->lastInsertId();
            // تسجيل العملية
            $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Add Doctor', 'doctors', ?)");
            $logStmt->execute([$_SESSION['user_id'], $doctor_id]);
            $_SESSION['success'] = "✅ تم إضافة الطبيب بنجاح";
        } else {
            throw new Exception("فشل في إضافة الطبيب");
        }
    } catch(Exception $e) {
        error_log("Add Doctor Error: " . $e->getMessage());
        $_SESSION['error'] = "❌ " . $e->getMessage();
    }
    
    // إعادة التوجيه
    header("Location: doctors.php");
    exit();
}

// معالجة تحديث بيانات الطبيب
if (
    isset($_POST['update_doctor']) ||
    (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        !empty($_POST['doctor_id']) &&
        isset($_POST['full_name'], $_POST['specialization'], $_POST['consultation_fee'])
    )
) {
    try {
        // التحقق من الحقول المطلوبة
        $required_fields = ['doctor_id', 'full_name', 'specialization', 'consultation_fee'];
        foreach($required_fields as $field) {
            if(empty($_POST[$field])) {
                throw new Exception("حقل $field مطلوب");
            }
        }

        // الحصول على معرف الطبيب
        $doctor_id = filter_var($_POST['doctor_id'], FILTER_VALIDATE_INT);
        if(!$doctor_id) {
            throw new Exception("معرف الطبيب غير صحيح");
        }

        // تنظيف والتحقق من البيانات
        $full_name = htmlspecialchars(trim($_POST['full_name']));
        $specialization = htmlspecialchars(trim($_POST['specialization']));
        $phone = htmlspecialchars(trim($_POST['phone'] ?? ''));
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $consultation_fee = filter_var($_POST['consultation_fee'], FILTER_VALIDATE_FLOAT);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // التحقق من صحة البريد الإلكتروني
        if(!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("البريد الإلكتروني غير صحيح");
        }

        // تحديث البيانات
        $stmt = $conn->prepare("UPDATE doctors SET full_name = ?, specialization = ?, phone = ?, email = ?, consultation_fee = ?, is_active = ? WHERE id = ?");
        $result = $stmt->execute([$full_name, $specialization, $phone, $email, $consultation_fee, $is_active, $doctor_id]);

        if($result) {
            // تسجيل العملية
            $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Update Doctor', 'doctors', ?)");
            $logStmt->execute([$_SESSION['user_id'], $doctor_id]);
            $_SESSION['success'] = "✅ تم تحديث بيانات الطبيب بنجاح";
        } else {
            throw new Exception("فشل في تحديث بيانات الطبيب");
        }
    } catch(Exception $e) {
        error_log("Update Doctor Error: " . $e->getMessage());
        $_SESSION['error'] = "❌ " . $e->getMessage();
    }
    
    // إعادة التوجيه
    header("Location: doctors.php");
    exit();
}


// البحث عن طبيب
$search_results = null;
if(isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = '%' . $_GET['search'] . '%';
    $stmt = $conn->prepare("SELECT * FROM doctors WHERE full_name LIKE ? OR specialization LIKE ? OR phone LIKE ? ORDER BY id DESC");
    $stmt->execute([$search_term, $search_term, $search_term]);
    $search_results = $stmt->fetchAll();
}

// الحصول على بيانات طبيب للتعديل
$edit_doctor = null;
if(isset($_GET['edit_id'])) {
    $edit_id = filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT);
    if($edit_id) {
        $stmt = $conn->prepare("SELECT * FROM doctors WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_doctor = $stmt->fetch();
    }
}

// إحصائيات سريعة
$active_doctors = $conn->query("SELECT COUNT(*) FROM doctors WHERE is_active = 1")->fetchColumn();
$total_consultations = $conn->query("SELECT SUM(consultation_fee) FROM doctors WHERE is_active = 1")->fetchColumn() ?? 0;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الأطباء - 2050</title>
    <link href="../../assets/css/dashboardo.css" rel="stylesheet">
    <style>
        /* ✨ تخصيصات صفحة الأطباء */
        .doctor-card {
            background: var(--bg-card);
            border: var(--glass-border);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .doctor-card::before {
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
        
        .doctor-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-elevated), var(--shadow-neon-primary);
        }
        
        .doctor-card:hover::before {
            opacity: 1;
        }
        
        .doctor-avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            color: #fff;
            font-weight: 700;
            box-shadow: 0 8px 24px var(--primary-glow);
            flex-shrink: 0;
        }
        
        .doctor-info {
            flex: 1;
            min-width: 0;
        }
        
        .doctor-name {
            font-size: 1.125rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 0.25rem;
        }
        
        .doctor-specialty {
            font-size: 0.875rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }
        
        .doctor-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            font-size: 0.8rem;
            color: rgba(241, 245, 249, 0.7);
        }
        
        .doctor-meta span {
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }
        
        .doctor-actions {
            display: flex;
            gap: 0.5rem;
            flex-shrink: 0;
        }
        
        .doctor-actions .btn {
            padding: 0.5rem;
            width: 36px;
            height: 36px;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
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
        
        .search-glass .icon {
            color: var(--primary);
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
        
        .table-doctors th {
            background: rgba(99, 102, 241, 0.1);
            font-weight: 600;
            color: rgba(241, 245, 249, 0.8);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .table-doctors td {
            border-color: rgba(255, 255, 255, 0.05);
            color: rgba(241, 245, 249, 0.9);
        }
        
        .table-doctors tbody tr:hover {
            background: rgba(99, 102, 241, 0.08);
        }
        
        .modal-content {
            background: var(--bg-card);
            border: var(--glass-border);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-elevated);
        }
        
        .modal-header {
            border-bottom: var(--glass-border);
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(236, 72, 153, 0.1));
        }
        
        .modal-title {
            color: #fff;
        }
        
        .modal-body {
            color: rgba(241, 245, 249, 0.9);
        }
        
        .form-label {
            color: rgba(241, 245, 249, 0.8);
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            background: rgba(15, 23, 42, 0.5);
            border-color: rgba(255, 255, 255, 0.1);
            color: #fff;
        }
        
        .form-control:focus {
            background: rgba(15, 23, 42, 0.8);
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }
        
        .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }
        
        @media (max-width: 768px) {
            .doctor-card {
                flex-direction: column;
                text-align: center;
            }
            .doctor-avatar {
                margin: 0 auto 1rem;
            }
            .doctor-actions {
                justify-content: center;
                margin-top: 1rem;
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
            <li class="nav-item"><a class="nav-link active" href="doctors.php"><span class="icon" data-icon="🩺"></span>الأطباء</a></li>
            <li class="nav-item"><a class="nav-link" href="services.php"><span class="icon" data-icon="🔧"></span>الخدمات</a></li>
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
                <h1 class="mb-1">🩺 إدارة الأطباء</h1>
                <p class="text-muted mb-0">إدارة شاملة لفريق الأطباء والتخصصات</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDoctorModal">
                <span class="icon" data-icon="➕"></span>
                إضافة طبيب
            </button>
        </div>

        <!-- 🔔 التنبيهات -->
        <?php if(isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show animate-fade-in">
            <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        <?php if(isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show animate-fade-in">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- 📊 إحصائيات سريعة -->
        <div class="stats-row">
            <div class="stat-mini">
                <div class="value" style="color: var(--primary)"><?php echo count($doctors); ?></div>
                <div class="label">إجمالي الأطباء</div>
            </div>
            <div class="stat-mini">
                <div class="value" style="color: var(--success)"><?php echo $active_doctors; ?></div>
                <div class="label">نشطون حالياً</div>
            </div>
            <div class="stat-mini">
                <div class="value" style="color: var(--warning)"><?php echo count($doctors) - $active_doctors; ?></div>
                <div class="label">غير نشطين</div>
            </div>
            <div class="stat-mini">
                <div class="value" style="color: var(--info)"><?php echo number_format($total_consultations, 0); ?></div>
                <div class="label">متوسط الرسوم (ريال)</div>
            </div>
        </div>

        <!-- 🔍 شريط البحث -->
        <div class="card mb-4 animate-slide-in">
            <div class="card-body">
                <form method="GET" action="" class="d-flex gap-3 flex-wrap">
                    <div class="search-glass">
                        <span class="icon" data-icon="🔍"></span>
                        <input type="text" name="search" placeholder="ابحث عن طبيب، تخصص، أو هاتف..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    </div>
                    <button type="submit" class="btn btn-outline">بحث</button>
                    <?php if(isset($_GET['search'])): ?>
                    <a href="doctors.php" class="btn btn-outline">مسح</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- 👥 قائمة الأطباء (عرض بطاقات) -->
        <div class="card animate-slide-in stagger-1">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6><span class="icon" data-icon="👨‍⚕️"></span>قائمة الأطباء</h6>
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
                    $display_doctors = $search_results ?? $doctors;
                    if(empty($display_doctors)): 
                    ?>
                    <div class="col-12 text-center py-5">
                        <div class="icon" style="font-size: 3rem; opacity: 0.3" data-icon="🩺"></div>
                        <p class="text-muted mt-3">لا يوجد أطباء لعرضهم</p>
                        <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addDoctorModal">
                            <span class="icon" data-icon="➕"></span> إضافة أول طبيب
                        </button>
                    </div>
                    <?php else: foreach($display_doctors as $doctor): 
                        $initial = mb_substr($doctor['full_name'], 0, 1);
                    ?>
                    <div class="col-xl-4 col-md-6">
                        <div class="doctor-card d-flex align-items-center gap-3">
                            <div class="doctor-avatar"><?php echo htmlspecialchars($initial); ?></div>
                            <div class="doctor-info">
                                <div class="doctor-name"><?php echo htmlspecialchars($doctor['full_name']); ?></div>
                                <div class="doctor-specialty">
                                    <span class="icon" data-icon="🎯"></span>
                                    <?php echo htmlspecialchars($doctor['specialization'] ?: 'غير محدد'); ?>
                                </div>
                                <div class="doctor-meta">
                                    <span><span class="icon" data-icon="📱"></span><?php echo htmlspecialchars($doctor['phone'] ?: '-'); ?></span>
                                    <span><span class="icon" data-icon="💰"></span><?php echo number_format($doctor['consultation_fee'], 2); ?> ريال</span>
                                </div>
                            </div>
                            <div class="doctor-actions">
                                <span class="status-badge <?php echo $doctor['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $doctor['is_active'] ? 'نشط' : 'غير نشط'; ?>
                                </span>
                                <button class="btn btn-outline" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($doctor)); ?>)" title="تعديل">
                                    <span class="icon" data-icon="✏️"></span>
                                </button>
                                <button class="btn btn-outline text-danger" onclick="confirmDelete(<?php echo $doctor['id']; ?>, '<?php echo htmlspecialchars($doctor['full_name']); ?>')" title="حذف">
                                    <span class="icon" data-icon="🗑️"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>

                <!-- عرض الجدول (مخفي افتراضياً) -->
                <div id="tableView" class="table-responsive" style="display: none;">
                    <table class="table table-doctors mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>الطبيب</th>
                                <th>التخصص</th>
                                <th>الهاتف</th>
                                <th>البريد</th>
                                <th>الرسوم</th>
                                <th>الحالة</th>
                                <th>إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($display_doctors)): ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">لا يوجد بيانات</td></tr>
                            <?php else: foreach($display_doctors as $doctor): ?>
                            <tr>
                                <td><?php echo $doctor['id']; ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="doctor-avatar" style="width:40px;height:40px;font-size:1rem"><?php echo htmlspecialchars(mb_substr($doctor['full_name'], 0, 1)); ?></div>
                                        <span><?php echo htmlspecialchars($doctor['full_name']); ?></span>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($doctor['specialization'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($doctor['phone'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($doctor['email'] ?: '-'); ?></td>
                                <td><?php echo number_format($doctor['consultation_fee'], 2); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $doctor['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $doctor['is_active'] ? 'نشط' : 'غير نشط'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <button class="btn btn-outline btn-sm" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($doctor)); ?>)">
                                            <span class="icon" data-icon="✏️"></span>
                                        </button>
                                        <button class="btn btn-outline btn-sm text-danger" onclick="confirmDelete(<?php echo $doctor['id']; ?>, '<?php echo htmlspecialchars($doctor['full_name']); ?>')">
                                            <span class="icon" data-icon="🗑️"></span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- ➕ نموذج إضافة طبيب -->
    <div class="modal fade" id="addDoctorModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><span class="icon" data-icon="➕"></span> إضافة طبيب جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">الاسم الكامل <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="full_name" required placeholder="أدخل اسم الطبيب">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">التخصص <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="specialization" required placeholder="مثال: طب عام، قلب، جراحة...">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">رقم الهاتف</label>
                                <input type="tel" class="form-control" name="phone" placeholder="05xxxxxxxx">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">البريد الإلكتروني</label>
                                <input type="email" class="form-control" name="email" placeholder="doctor@example.com">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">رسوم الكشف (ريال) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="consultation_fee" step="0.01" min="0" required placeholder="0.00">
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="is_active" id="is_active_add" checked>
                            <label class="form-check-label" for="is_active_add">تفعيل الحساب فوراً</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="add_doctor" class="btn btn-primary">حفظ الطبيب</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ✏️ نموذج تعديل طبيب -->
    <div class="modal fade" id="editDoctorModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><span class="icon" data-icon="✏️"></span> تعديل بيانات الطبيب</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" id="editForm">
                    <input type="hidden" name="doctor_id" id="edit_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">الاسم الكامل <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="full_name" id="edit_full_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">التخصص <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="specialization" id="edit_specialization" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">رقم الهاتف</label>
                                <input type="tel" class="form-control" name="phone" id="edit_phone">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">البريد الإلكتروني</label>
                                <input type="email" class="form-control" name="email" id="edit_email">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">رسوم الكشف (ريال) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="consultation_fee" id="edit_consultation_fee" step="0.01" min="0" required>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="is_active" id="edit_is_active">
                            <label class="form-check-label" for="edit_is_active">حساب نشط</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="update_doctor" class="btn btn-primary">تحديث البيانات</button>
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
                    <p class="mt-3">هل أنت متأكد من حذف الطبيب <strong id="delete_name"></strong>؟</p>
                    <small class="text-muted">لا يمكن التراجع عن هذا الإجراء</small>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-outline" data-bs-dismiss="modal">إلغاء</button>
                    <form method="POST" action="delete_doctor.php" id="deleteForm">
                        <input type="hidden" name="doctor_id" id="delete_id">
                        <button type="submit" class="btn btn-danger">نعم، احذف</button>
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
    function openEditModal(doctor) {
        document.getElementById('edit_id').value = doctor.id;
        document.getElementById('edit_full_name').value = doctor.full_name;
        document.getElementById('edit_specialization').value = doctor.specialization || '';
        document.getElementById('edit_phone').value = doctor.phone || '';
        document.getElementById('edit_email').value = doctor.email || '';
        document.getElementById('edit_consultation_fee').value = doctor.consultation_fee;
        document.getElementById('edit_is_active').checked = doctor.is_active == 1;
        
        const modal = new bootstrap.Modal(document.getElementById('editDoctorModal'));
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
    const addBtn = document.querySelector('[data-bs-target="#addDoctorModal"]');
    if(addBtn) {
        addBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const modal = document.getElementById('addDoctorModal');
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
    window.openEditModal = function(doctor) {
        // ملء الحقول
        document.getElementById('edit_id').value = doctor.id;
        document.getElementById('edit_full_name').value = doctor.full_name || '';
        document.getElementById('edit_specialization').value = doctor.specialization || '';
        document.getElementById('edit_phone').value = doctor.phone || '';
        document.getElementById('edit_email').value = doctor.email || '';
        document.getElementById('edit_consultation_fee').value = doctor.consultation_fee || '';
        document.getElementById('edit_is_active').checked = doctor.is_active == 1;
        
        // فتح المودال
        const modal = document.getElementById('editDoctorModal');
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
    const searchInput = document.getElementById('searchVisits');
    if(searchInput) {
        searchInput.addEventListener('input', function() {
            const term = this.value.toLowerCase();
            document.querySelectorAll('tbody tr').forEach(row => {
                const name = row.querySelector('td:first-child')?.textContent.toLowerCase() || '';
                row.style.display = name.includes(term) ? '' : 'none';
            });
        });
    }

    // ===== 🎨 تأثيرات إضافية =====
    // تأثير Hover للبطاقات
    document.querySelectorAll('.doctor-card').forEach(card => {
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
                submitBtn.innerHTML = '<span class="spinner-mini"></span> جاري الحفظ...';
            }
        });
    });

    // إعادة تمكين الأزرار بعد التحميل
    window.addEventListener('load', function() {
        document.querySelectorAll('button[type="submit"]').forEach(btn => {
            btn.disabled = false;
            btn.innerHTML = btn.innerHTML.replace(/<span class="spinner-mini"><\/span>\s*جاري الحفظ\.\.\./, btn.dataset.originalText || 'حفظ');
        });
    });
});
</script>
    
    <script src="../../assets/js/bootstrap.min.js"></script>
</body>
</html>
