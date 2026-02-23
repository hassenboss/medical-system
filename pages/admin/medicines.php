<?php
require_once '../../includes/auth.php';
checkRole(['Admin']);
require_once '../../config/db.php';

$medicines = $conn->query("SELECT * FROM medicines ORDER BY id DESC")->fetchAll();

if (
    isset($_POST['add_medicine']) ||
    (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        empty($_POST['medicine_id']) &&
        isset($_POST['name'], $_POST['price'], $_POST['quantity'], $_POST['min_quantity'], $_POST['expiry_date'])
    )
) {
    try {
        $required_fields = ['name', 'price', 'quantity', 'min_quantity', 'expiry_date'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || $_POST[$field] === '') {
                throw new Exception("حقل $field مطلوب");
            }
        }

        $name = htmlspecialchars(trim($_POST['name']));
        $description = htmlspecialchars(trim($_POST['description'] ?? ''));
        $price = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT);
        $quantity = filter_var($_POST['quantity'], FILTER_VALIDATE_INT);
        $min_quantity = filter_var($_POST['min_quantity'], FILTER_VALIDATE_INT);
        $expiry_date = trim($_POST['expiry_date']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($price === false || $price < 0) throw new Exception("السعر غير صحيح");
        if ($quantity === false || $quantity < 0) throw new Exception("الكمية غير صحيحة");
        if ($min_quantity === false || $min_quantity < 0) throw new Exception("الحد الأدنى غير صحيح");
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry_date)) throw new Exception("تاريخ انتهاء الصلاحية غير صحيح");

        $stmt = $conn->prepare("INSERT INTO medicines (name, description, price, quantity, min_quantity, expiry_date, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([$name, $description, $price, $quantity, $min_quantity, $expiry_date, $is_active]);

        if ($result) {
            $medicine_id = $conn->lastInsertId();
            $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Add Medicine', 'medicines', ?)");
            $logStmt->execute([$_SESSION['user_id'], $medicine_id]);
            $_SESSION['success'] = "✅ تم إضافة الدواء بنجاح";
        } else {
            throw new Exception("فشل في إضافة الدواء");
        }
    } catch (Exception $e) {
        error_log("Add Medicine Error: " . $e->getMessage());
        $_SESSION['error'] = "❌ " . $e->getMessage();
    }

    header("Location: medicines.php");
    exit();
}

if (
    isset($_POST['update_medicine']) ||
    (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        !empty($_POST['medicine_id']) &&
        isset($_POST['name'], $_POST['price'], $_POST['quantity'], $_POST['min_quantity'], $_POST['expiry_date'])
    )
) {
    try {
        $required_fields = ['medicine_id', 'name', 'price', 'quantity', 'min_quantity', 'expiry_date'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || $_POST[$field] === '') {
                throw new Exception("حقل $field مطلوب");
            }
        }

        $medicine_id = filter_var($_POST['medicine_id'], FILTER_VALIDATE_INT);
        if (!$medicine_id) throw new Exception("معرف الدواء غير صحيح");

        $name = htmlspecialchars(trim($_POST['name']));
        $description = htmlspecialchars(trim($_POST['description'] ?? ''));
        $price = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT);
        $quantity = filter_var($_POST['quantity'], FILTER_VALIDATE_INT);
        $min_quantity = filter_var($_POST['min_quantity'], FILTER_VALIDATE_INT);
        $expiry_date = trim($_POST['expiry_date']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($price === false || $price < 0) throw new Exception("السعر غير صحيح");
        if ($quantity === false || $quantity < 0) throw new Exception("الكمية غير صحيحة");
        if ($min_quantity === false || $min_quantity < 0) throw new Exception("الحد الأدنى غير صحيح");
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry_date)) throw new Exception("تاريخ انتهاء الصلاحية غير صحيح");

        $stmt = $conn->prepare("UPDATE medicines SET name = ?, description = ?, price = ?, quantity = ?, min_quantity = ?, expiry_date = ?, is_active = ? WHERE id = ?");
        $result = $stmt->execute([$name, $description, $price, $quantity, $min_quantity, $expiry_date, $is_active, $medicine_id]);

        if ($result) {
            $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Update Medicine', 'medicines', ?)");
            $logStmt->execute([$_SESSION['user_id'], $medicine_id]);
            $_SESSION['success'] = "✅ تم تحديث بيانات الدواء بنجاح";
        } else {
            throw new Exception("فشل في تحديث بيانات الدواء");
        }
    } catch (Exception $e) {
        error_log("Update Medicine Error: " . $e->getMessage());
        $_SESSION['error'] = "❌ " . $e->getMessage();
    }

    header("Location: medicines.php");
    exit();
}

$search_results = null;
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = '%' . $_GET['search'] . '%';
    $stmt = $conn->prepare("SELECT * FROM medicines WHERE name LIKE ? OR description LIKE ? ORDER BY id DESC");
    $stmt->execute([$search_term, $search_term]);
    $search_results = $stmt->fetchAll();
}

$active_medicines = $conn->query("SELECT COUNT(*) FROM medicines WHERE is_active = 1")->fetchColumn();
$total_medicines = count($medicines);
$low_stock = $conn->query("SELECT COUNT(*) FROM medicines WHERE quantity <= min_quantity")->fetchColumn();
$avg_price = $conn->query("SELECT AVG(price) FROM medicines WHERE is_active = 1")->fetchColumn() ?? 0;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الأدوية - 2050</title>
    <link href="../../assets/css/dashboardo.css" rel="stylesheet">
    <style>
        .medicine-card{background:var(--bg-card);border:var(--glass-border);border-radius:var(--radius-xl);padding:1.25rem;transition:var(--transition);position:relative;overflow:hidden;height:100%}
        .medicine-card::before{content:'';position:absolute;top:0;right:0;width:4px;height:100%;background:var(--gradient-primary);opacity:0;transition:var(--transition)}
        .medicine-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-elevated),var(--shadow-neon-primary)}
        .medicine-card:hover::before{opacity:1}
        .medicine-icon{width:56px;height:56px;border-radius:var(--radius-lg);background:var(--gradient-primary);display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#fff;box-shadow:0 8px 24px var(--primary-glow)}
        .medicine-name{font-size:1.05rem;font-weight:700;color:#fff}
        .medicine-desc{font-size:.82rem;color:rgba(241,245,249,.75);min-height:1.1rem}
        .medicine-meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.5rem;margin-top:.75rem}
        .meta-pill{background:rgba(15,23,42,.5);border:1px solid rgba(255,255,255,.08);border-radius:var(--radius);padding:.35rem .6rem;font-size:.76rem;color:rgba(241,245,249,.85)}
        .status-badge{padding:.25rem .75rem;border-radius:var(--radius-full);font-size:.75rem;font-weight:600}
        .status-badge.active{background:rgba(16,185,129,.15);color:var(--success);border:1px solid var(--success)}
        .status-badge.inactive{background:rgba(239,68,68,.15);color:var(--danger);border:1px solid var(--danger)}
        .low-stock-badge{background:rgba(245,158,11,.15);color:var(--warning);border:1px solid var(--warning);padding:.15rem .55rem;border-radius:var(--radius-full);font-size:.7rem}
        .search-glass{background:rgba(15,23,42,.6);border:var(--glass-border);border-radius:var(--radius-lg);padding:.5rem 1rem;display:flex;align-items:center;gap:.75rem;max-width:430px;width:100%}
        .search-glass input{background:transparent;border:none;color:#fff;width:100%;outline:none}
        .stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:1.5rem}
        .stat-mini{background:var(--bg-card);border:var(--glass-border);border-radius:var(--radius-lg);padding:1rem;text-align:center}
        .stat-mini .value{font-size:1.5rem;font-weight:700;color:#fff}
        .stat-mini .label{font-size:.75rem;color:rgba(241,245,249,.7)}
        .view-toggle-btn.active{background:var(--gradient-primary);color:#fff;border-color:transparent;box-shadow:var(--shadow-neon-primary)}
        .table-medicines th{background:rgba(99,102,241,.1);font-size:.75rem;color:rgba(241,245,249,.82)}
        .table-medicines td{border-color:rgba(255,255,255,.05);color:rgba(241,245,249,.9);vertical-align:middle}
        .modal-content{background:var(--bg-card);border:var(--glass-border);border-radius:var(--radius-xl);box-shadow:var(--shadow-elevated);overflow:hidden}
        .modal-header{border-bottom:var(--glass-border);background:linear-gradient(135deg,rgba(99,102,241,.12),rgba(16,185,129,.12))}
        .modal-title{color:#fff;font-weight:700}.modal-footer{border-top:var(--glass-border)}
        .form-label{color:rgba(241,245,249,.85);font-weight:600}
        .form-control,.form-select{background:rgba(15,23,42,.55);border-color:rgba(255,255,255,.12);color:#fff}
        .form-control:focus,.form-select:focus{background:rgba(15,23,42,.8);border-color:var(--primary);box-shadow:0 0 0 3px var(--primary-glow)}
        .modal-note{background:rgba(14,165,233,.1);border:1px dashed rgba(14,165,233,.45);border-radius:var(--radius);padding:.75rem 1rem;font-size:.85rem;color:rgba(241,245,249,.85)}
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="d-flex align-items-center gap-3">
            <button class="navbar-toggler d-lg-none" id="sidebarToggle"><span class="icon" data-icon="☰"></span></button>
            <a class="navbar-brand" href="#"><span class="icon" data-icon="🏥"></span><span>نظام نوف الطبي</span></a>
        </div>
        <div class="user-info">
            <span class="user-name"><span class="icon" data-icon="👤"></span><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'مدير النظام'); ?></span>
            <a href="../../includes/auth.php?logout=true" class="btn-logout"><span class="icon" data-icon="🚪"></span>خروج</a>
        </div>
    </nav>

    <nav class="sidebar" id="sidebar">
        <div class="nav-header"><h6>القائمة الرئيسية</h6></div>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link" href="dashboard.php"><span class="icon" data-icon="📊"></span>لوحة التحكم</a></li>
            <li class="nav-item"><a class="nav-link" href="patients.php"><span class="icon" data-icon="🤒"></span>المرضى</a></li>
            <li class="nav-item"><a class="nav-link" href="doctors.php"><span class="icon" data-icon="🩺"></span>الأطباء</a></li>
            <li class="nav-item"><a class="nav-link" href="services.php"><span class="icon" data-icon="🔧"></span>الخدمات</a></li>
            <li class="nav-item"><a class="nav-link active" href="medicines.php"><span class="icon" data-icon="💊"></span>الأدوية</a></li>
            <li class="nav-item"><a class="nav-link" href="lab_tests.php"><span class="icon" data-icon="🧪"></span>فحوصات المعمل</a></li>
            <div class="nav-header mt-3"><h6>الإدارة</h6></div>
            <li class="nav-item"><a class="nav-link" href="prices.php"><span class="icon" data-icon="💰"></span>الأسعار</a></li>
            <li class="nav-item"><a class="nav-link" href="users.php"><span class="icon" data-icon="👥"></span>المستخدمون</a></li>
            <li class="nav-item"><a class="nav-link" href="reports.php"><span class="icon" data-icon="📈"></span>التقارير</a></li>
            <li class="nav-item"><a class="nav-link" href="settings.php"><span class="icon" data-icon="⚙️"></span>الإعدادات</a></li>
        </ul>
    </nav>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main>
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h1 class="mb-1">💊 إدارة الأدوية</h1>
                <p class="text-muted mb-0">إدارة شاملة لمخزون الأدوية والأسعار والصلاحية</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMedicineModal"><span class="icon" data-icon="➕"></span>إضافة دواء</button>
        </div>

        <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show animate-fade-in"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show animate-fade-in"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <div class="stats-row">
            <div class="stat-mini"><div class="value" style="color:var(--primary)"><?php echo $total_medicines; ?></div><div class="label">إجمالي الأدوية</div></div>
            <div class="stat-mini"><div class="value" style="color:var(--success)"><?php echo $active_medicines; ?></div><div class="label">أدوية نشطة</div></div>
            <div class="stat-mini"><div class="value" style="color:var(--warning)"><?php echo $low_stock; ?></div><div class="label">مخزون منخفض</div></div>
            <div class="stat-mini"><div class="value" style="color:var(--info)"><?php echo number_format($avg_price,2); ?></div><div class="label">متوسط السعر</div></div>
        </div>

        <div class="card mb-4 animate-slide-in">
            <div class="card-body">
                <form method="GET" action="" class="d-flex gap-3 flex-wrap">
                    <div class="search-glass"><span class="icon" data-icon="🔍"></span><input type="text" name="search" placeholder="ابحث عن دواء..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"></div>
                    <button type="submit" class="btn btn-outline">بحث</button>
                    <?php if(isset($_GET['search'])): ?><a href="medicines.php" class="btn btn-outline">مسح</a><?php endif; ?>
                </form>
            </div>
        </div>

        <div class="card animate-slide-in stagger-1">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6><span class="icon" data-icon="💊"></span>قائمة الأدوية</h6>
                <div class="d-flex gap-2">
                    <button id="gridToggleBtn" class="btn btn-outline btn-sm view-toggle-btn active" onclick="toggleView('grid')"><span class="icon" data-icon="▦"></span> شبكة</button>
                    <button id="tableToggleBtn" class="btn btn-outline btn-sm view-toggle-btn" onclick="toggleView('table')"><span class="icon" data-icon="☰"></span> جدول</button>
                </div>
            </div>
            <div class="card-body">
                <div id="gridView" class="row g-3">
                    <?php $display_medicines = $search_results ?? $medicines; if(empty($display_medicines)): ?>
                    <div class="col-12 text-center py-5"><div class="icon" style="font-size:3rem;opacity:.3" data-icon="💊"></div><p class="text-muted mt-3">لا توجد أدوية لعرضها</p><button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addMedicineModal"><span class="icon" data-icon="➕"></span> إضافة أول دواء</button></div>
                    <?php else: foreach($display_medicines as $medicine): ?>
                    <div class="col-xl-4 col-md-6">
                        <div class="medicine-card">
                            <div class="d-flex align-items-center gap-3"><div class="medicine-icon"><span class="icon" data-icon="💊"></span></div><div><div class="medicine-name"><?php echo htmlspecialchars($medicine['name']); ?></div><div class="medicine-desc"><?php echo htmlspecialchars($medicine['description'] ?: 'بدون وصف'); ?></div></div></div>
                            <div class="medicine-meta">
                                <div class="meta-pill">السعر: <strong><?php echo number_format($medicine['price'],2); ?> ريال</strong></div>
                                <div class="meta-pill">الكمية: <strong><?php echo (int)$medicine['quantity']; ?></strong> <?php if((int)$medicine['quantity'] <= (int)$medicine['min_quantity']): ?><span class="low-stock-badge">منخفض</span><?php endif; ?></div>
                                <div class="meta-pill">الحد الأدنى: <strong><?php echo (int)$medicine['min_quantity']; ?></strong></div>
                                <div class="meta-pill">الصلاحية: <strong><?php echo htmlspecialchars($medicine['expiry_date']); ?></strong></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <span class="status-badge <?php echo $medicine['is_active'] ? 'active' : 'inactive'; ?>"><?php echo $medicine['is_active'] ? 'نشط' : 'غير نشط'; ?></span>
                                <button class="btn btn-outline btn-sm" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($medicine)); ?>)"><span class="icon" data-icon="✏️"></span> تعديل</button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>

                <div id="tableView" class="table-responsive" style="display:none;">
                    <table class="table table-hover mb-0 table-medicines">
                        <thead><tr><th>#</th><th>اسم الدواء</th><th>الوصف</th><th>السعر</th><th>الكمية</th><th>الحد الأدنى</th><th>الصلاحية</th><th>الحالة</th><th>الإجراءات</th></tr></thead>
                        <tbody>
                            <?php if(empty($display_medicines)): ?>
                            <tr><td colspan="9" class="text-center py-4 text-muted">لا توجد بيانات</td></tr>
                            <?php else: foreach($display_medicines as $medicine): ?>
                            <tr>
                                <td><?php echo $medicine['id']; ?></td>
                                <td><?php echo htmlspecialchars($medicine['name']); ?></td>
                                <td><?php echo htmlspecialchars($medicine['description'] ?: '-'); ?></td>
                                <td><?php echo number_format($medicine['price'],2); ?> ريال</td>
                                <td><?php echo (int)$medicine['quantity']; ?> <?php if((int)$medicine['quantity'] <= (int)$medicine['min_quantity']): ?><span class="low-stock-badge">منخفض</span><?php endif; ?></td>
                                <td><?php echo (int)$medicine['min_quantity']; ?></td>
                                <td><?php echo htmlspecialchars($medicine['expiry_date']); ?></td>
                                <td><span class="status-badge <?php echo $medicine['is_active'] ? 'active' : 'inactive'; ?>"><?php echo $medicine['is_active'] ? 'نشط' : 'غير نشط'; ?></span></td>
                                <td><button class="btn btn-outline btn-sm" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($medicine)); ?>)"><span class="icon" data-icon="✏️"></span></button></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <div class="modal fade" id="addMedicineModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><span class="icon" data-icon="➕"></span> إضافة دواء جديد</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST" action=""><div class="modal-body">
                <div class="modal-note mb-3">أدخل بيانات الدواء بدقة لضمان متابعة المخزون والتنبيهات بشكل صحيح.</div>
                <div class="mb-3"><label class="form-label">اسم الدواء <span class="text-danger">*</span></label><input type="text" class="form-control" name="name" required></div>
                <div class="mb-3"><label class="form-label">الوصف</label><textarea class="form-control" name="description" rows="2"></textarea></div>
                <div class="row"><div class="col-md-6 mb-3"><label class="form-label">السعر (ريال) <span class="text-danger">*</span></label><input type="number" class="form-control" name="price" step="0.01" min="0" required></div><div class="col-md-6 mb-3"><label class="form-label">تاريخ الصلاحية <span class="text-danger">*</span></label><input type="date" class="form-control" name="expiry_date" required></div></div>
                <div class="row align-items-end"><div class="col-md-4 mb-3"><label class="form-label">الكمية <span class="text-danger">*</span></label><input type="number" class="form-control" name="quantity" min="0" required></div><div class="col-md-4 mb-3"><label class="form-label">الحد الأدنى <span class="text-danger">*</span></label><input type="number" class="form-control" name="min_quantity" min="0" required></div><div class="col-md-4 mb-3"><div class="form-check mt-md-4"><input type="checkbox" class="form-check-input" name="is_active" id="is_active_add" checked><label class="form-check-label" for="is_active_add">دواء نشط</label></div></div></div>
            </div><div class="modal-footer"><button type="button" class="btn btn-outline" data-bs-dismiss="modal">إلغاء</button><button type="submit" name="add_medicine" class="btn btn-primary">حفظ الدواء</button></div></form>
        </div></div>
    </div>

    <div class="modal fade" id="editMedicineModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><span class="icon" data-icon="✏️"></span> تعديل بيانات الدواء</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST" action=""><input type="hidden" name="medicine_id" id="edit_id"><div class="modal-body">
                <div class="modal-note mb-3">عدل البيانات المطلوبة ثم اضغط تحديث لحفظ التغييرات مباشرة.</div>
                <div class="mb-3"><label class="form-label">اسم الدواء <span class="text-danger">*</span></label><input type="text" class="form-control" name="name" id="edit_name" required></div>
                <div class="mb-3"><label class="form-label">الوصف</label><textarea class="form-control" name="description" id="edit_description" rows="2"></textarea></div>
                <div class="row"><div class="col-md-6 mb-3"><label class="form-label">السعر (ريال) <span class="text-danger">*</span></label><input type="number" class="form-control" name="price" id="edit_price" step="0.01" min="0" required></div><div class="col-md-6 mb-3"><label class="form-label">تاريخ الصلاحية <span class="text-danger">*</span></label><input type="date" class="form-control" name="expiry_date" id="edit_expiry_date" required></div></div>
                <div class="row align-items-end"><div class="col-md-4 mb-3"><label class="form-label">الكمية <span class="text-danger">*</span></label><input type="number" class="form-control" name="quantity" id="edit_quantity" min="0" required></div><div class="col-md-4 mb-3"><label class="form-label">الحد الأدنى <span class="text-danger">*</span></label><input type="number" class="form-control" name="min_quantity" id="edit_min_quantity" min="0" required></div><div class="col-md-4 mb-3"><div class="form-check mt-md-4"><input type="checkbox" class="form-check-input" name="is_active" id="edit_is_active"><label class="form-check-label" for="edit_is_active">دواء نشط</label></div></div></div>
            </div><div class="modal-footer"><button type="button" class="btn btn-outline" data-bs-dismiss="modal">إلغاء</button><button type="submit" name="update_medicine" class="btn btn-primary">تحديث البيانات</button></div></form>
        </div></div>
    </div>
    <script>
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggleBtn = document.getElementById('sidebarToggle');
    function toggleSidebar(){sidebar.classList.toggle('show');overlay.classList.toggle('show');document.body.style.overflow=sidebar.classList.contains('show')?'hidden':'';}
    toggleBtn?.addEventListener('click',toggleSidebar);overlay?.addEventListener('click',toggleSidebar);

    document.addEventListener('DOMContentLoaded', function() {
        function initModals(){
            document.querySelectorAll('.modal').forEach(modal=>{
                if(!modal._initialized){
                    modal._initialized=true;modal.style.display='none';
                    modal.querySelector('[data-bs-dismiss="modal"], .btn-close')?.addEventListener('click',()=>{modal.classList.remove('show');modal.style.display='none';document.body.style.overflow='';});
                    modal.addEventListener('click',(e)=>{if(e.target===modal){modal.classList.remove('show');modal.style.display='none';document.body.style.overflow='';}});
                }
            });
        }
        initModals();

        window.toggleView = function(view){
            document.getElementById('gridView').style.display = view === 'grid' ? '' : 'none';
            document.getElementById('tableView').style.display = view === 'table' ? '' : 'none';
            document.getElementById('gridToggleBtn')?.classList.toggle('active', view === 'grid');
            document.getElementById('tableToggleBtn')?.classList.toggle('active', view === 'table');
        };

        const addBtn = document.querySelector('[data-bs-target="#addMedicineModal"]');
        if(addBtn){
            addBtn.addEventListener('click',function(e){
                e.preventDefault();
                const modal=document.getElementById('addMedicineModal');
                if(modal){modal.classList.add('show');modal.style.display='block';document.body.style.overflow='hidden';setTimeout(()=>modal.querySelector('input')?.focus(),100);}
            });
        }

        window.openEditModal = function(medicine){
            document.getElementById('edit_id').value = medicine.id;
            document.getElementById('edit_name').value = medicine.name || '';
            document.getElementById('edit_description').value = medicine.description || '';
            document.getElementById('edit_price').value = medicine.price || '';
            document.getElementById('edit_quantity').value = medicine.quantity || 0;
            document.getElementById('edit_min_quantity').value = medicine.min_quantity || 0;
            document.getElementById('edit_expiry_date').value = medicine.expiry_date || '';
            document.getElementById('edit_is_active').checked = medicine.is_active == 1;
            const modal=document.getElementById('editMedicineModal');
            if(modal){modal.classList.add('show');modal.style.display='block';document.body.style.overflow='hidden';setTimeout(()=>modal.querySelector('input')?.focus(),100);}
        };

        const searchInput = document.querySelector('input[name="search"]');
        if(searchInput){
            searchInput.addEventListener('input', function(){
                const term=this.value.toLowerCase();
                document.querySelectorAll('.medicine-card').forEach(card=>{
                    const name=card.querySelector('.medicine-name')?.textContent.toLowerCase()||'';
                    const desc=card.querySelector('.medicine-desc')?.textContent.toLowerCase()||'';
                    card.parentElement.style.display = (name.includes(term)||desc.includes(term)) ? '' : 'none';
                });
            });
        }

        document.querySelectorAll('button, .btn').forEach(btn=>{
            btn.addEventListener('click', function(e){
                if(this.dataset.processing){e.preventDefault();return false;}
                this.dataset.processing='true'; setTimeout(()=>delete this.dataset.processing,1000);
            });
        });

        document.addEventListener('keydown', function(e){
            if(e.key==='Escape'){
                document.querySelectorAll('.modal.show').forEach(modal=>{modal.classList.remove('show');modal.style.display='none';document.body.style.overflow='';});
            }
        });

        document.querySelectorAll('form').forEach(form=>{
            form.addEventListener('submit', function(){
                const submitBtn=this.querySelector('button[type="submit"]');
                if(submitBtn){submitBtn.disabled=true;submitBtn.innerHTML='<span class="spinner-mini"></span> جاري الحفظ...';}
            });
        });

        window.addEventListener('load', function(){
            document.querySelectorAll('button[type="submit"]').forEach(btn=>{
                btn.disabled=false;
                btn.innerHTML=btn.innerHTML.replace(/<span class="spinner-mini"><\/span>\s*جاري الحفظ\.\.\./, btn.dataset.originalText || 'حفظ');
            });
        });
    });
    </script>

    <script src="../../assets/js/bootstrap.min.js"></script>
</body>
</html>
