<?php
require_once '../../includes/auth.php';
checkRole(['Admin']);
require_once '../../config/db.php';

// معالجة عمليات البحث والفلترة
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// بناء استعلام SQL
$where = "1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (p.full_name LIKE ? OR p.phone LIKE ? OR p.national_id LIKE ? OR p.email LIKE ?)";
    $searchParam = "%$search%";
    $params = array_fill(0, 4, $searchParam);
}

if ($filter !== 'all') {
    if ($filter === 'recent') {
        $where .= " AND EXISTS (SELECT 1 FROM visits v WHERE v.patient_id = p.id AND v.visit_date >= DATE_SUB(NOW(), INTERVAL 30 DAY))";
    } elseif ($filter === 'active') {
        $where .= " AND EXISTS (SELECT 1 FROM visits v WHERE v.patient_id = p.id AND v.visit_date >= DATE_SUB(NOW(), INTERVAL 30 DAY))";
    }
}

// الحصول على عدد المرضى
$countQuery = $conn->prepare("SELECT COUNT(*) FROM patients p WHERE $where");
$countQuery->execute($params);
$totalPatients = $countQuery->fetchColumn();
$totalPages = ceil($totalPatients / $limit);

// جلب بيانات المرضى
$patientsQuery = $conn->prepare("SELECT p.*, 
    (SELECT COUNT(*) FROM visits v WHERE v.patient_id = p.id) as visits_count,
    (SELECT MAX(v.visit_date) FROM visits v WHERE v.patient_id = p.id) as last_visit,
    (SELECT DATE(v.visit_date) FROM visits v WHERE v.patient_id = p.id ORDER BY v.id DESC LIMIT 1) as first_visit
    FROM patients p 
    WHERE $where 
    ORDER BY p.id DESC 
    LIMIT $limit OFFSET $offset");
$patientsQuery->execute($params);
$patients = $patientsQuery->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المرضى - نظام نوف الطبي</title>
    <link href="../../assets/css/dashboardo.css" rel="stylesheet">
    <style>
        /* تخصيصات إضافية لصفحة المرضى */
        .header-greeting { font-size: 1.5rem; font-weight: 700; }
        .header-greeting span { color: var(--primary); }
        .quick-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; }
        .quick-actions .btn { padding: 0.5rem 1rem; font-size: 0.8rem; }
        .section-title { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; }
        .section-title i { color: var(--primary); }
        .patient-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border: 1px solid var(--border);
        }
        .patient-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        .patient-avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            margin-left: 1rem;
        }
        .patient-details h5 {
            margin: 0 0 0.25rem 0;
            font-size: 1.1rem;
        }
        .patient-meta {
            display: flex;
            gap: 1rem;
            margin-top: 0.5rem;
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        .patient-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        .patient-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        .patient-actions .btn {
            padding: 0.25rem 0.75rem;
            font-size: 0.8rem;
        }
        .patient-stats {
            display: flex;
            gap: 1rem;
            margin-top: 0.5rem;
        }
        .stat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0.25rem 0.75rem;
            background: var(--bg-light);
            border-radius: 8px;
        }
        .stat-item .value {
            font-weight: 600;
            font-size: 1rem;
        }
        .stat-item .label {
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        .filter-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border);
            overflow-x: auto;
            padding-bottom: 0.5rem;
        }
        .filter-tab {
            padding: 0.5rem 1rem;
            background: none;
            border: none;
            color: var(--text-muted);
            font-weight: 500;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .filter-tab:hover {
            color: var(--primary);
        }
        .filter-tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }
        .pagination-wrapper {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
        }
        .pagination {
            display: flex;
            gap: 0.25rem;
        }
        .pagination .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: var(--bg-light);
            color: var(--text);
            text-decoration: none;
            transition: all 0.2s;
            font-weight: 500;
        }
        .pagination .page-link:hover {
            background: var(--primary);
            color: white;
        }
        .pagination .page-link.active {
            background: var(--primary);
            color: white;
        }
    </style>
</head>
<body>
    <!-- 🧭 شريط التنقل الهولوجرافيك -->
    <nav class="navbar">
        <div class="d-flex align-items-center gap-3">
            <button class="navbar-toggler d-lg-none" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <a class="navbar-brand" href="dashboard.php">
                <span class="icon" data-icon="🏥"></span>
                <span>نظام نوف الطبي</span>
            </a>
        </div>
        <div class="user-info">
            <span class="user-name">
                <i>👤</i>
                <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'مدير النظام'); ?>
            </span>
            <a href="../../includes/auth.php?logout=true" class="btn-logout">
                <span class="icon" data-icon="🚪"></span>
                خروج
            </a>
        </div>
    </nav>

    <!-- 📋 القائمة الجانبية الكريستالية -->
    <nav class="sidebar" id="sidebar">
        <div class="nav-header">
            <h6>القائمة الرئيسية</h6>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link" href="dashboard.php"><span class="icon" data-icon="📊"></span>لوحة التحكم</a></li>
            <li class="nav-item"><a class="nav-link active" href="patients.php"><span class="icon" data-icon="🤒"></span>المرضى</a></li>
            <li class="nav-item"><a class="nav-link" href="doctors.php"><span class="icon" data-icon="🩺"></span>الأطباء</a></li>
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

    <!-- طبقة التعتيم للجوال -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- 🎬 المحتوى الرئيسي -->
    <main>
        <!-- رأس الصفحة -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h1 class="header-greeting mb-1">إدارة <span>المرضى</span></h1>
                <p class="text-muted mb-0">عرض وإدارة بيانات جميع المرضى المسجلين</p>
            </div>
            <div class="quick-actions">
                <button class="btn btn-outline" onclick="window.print()"><span class="icon" data-icon="🖨️"></span>طباعة</button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPatientModal"><span class="icon" data-icon="➕"></span>مريض جديد</button>
            </div>
        </div>

        <!-- 🔍 البحث والفلترة -->
        <div class="card mb-4 animate-fade-in">
            <div class="card-body">
                <form method="GET" action="patients.php" class="d-flex gap-2 flex-wrap">
                    <input type="text" name="search" class="form-control" placeholder="البحث بالاسم، الهاتف، البريد الإلكتروني أو الرقم الوطني" value="<?php echo htmlspecialchars($search); ?>" style="flex: 1; min-width: 250px;">
                    <select name="filter" class="form-select" style="width: auto;">
                        <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>جميع المرضى</option>
                        <option value="recent" <?php echo $filter === 'recent' ? 'selected' : ''; ?>>المرضى الجدد (آخر 30 يوم)</option>
                        <option value="active" <?php echo $filter === 'active' ? 'selected' : ''; ?>>المرضى النشطون</option>
                    </select>
                    <button type="submit" class="btn btn-primary"><span class="icon" data-icon="🔍"></span>بحث</button>
                </form>
            </div>
        </div>

        <!-- 📊 إحصائيات المرضى -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-card primary animate-fade-in">
                    <div class="icon-wrapper"><span class="icon" data-icon="🤒"></span></div>
                    <div class="content">
                        <div class="label">إجمالي المرضى</div>
                        <div class="value"><?php echo number_format($totalPatients); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card success animate-fade-in stagger-1">
                    <div class="icon-wrapper"><span class="icon" data-icon="👥"></span></div>
                    <div class="content">
                        <div class="label">مرضى جدد هذا الشهر</div>
                        <div class="value">
                            <?php 
                            $newPatientsQuery = $conn->query("SELECT COUNT(DISTINCT p.id) FROM patients p JOIN visits v ON p.id = v.patient_id WHERE v.visit_date >= DATE_FORMAT(NOW(), '%Y-%m-01')");
                            echo number_format($newPatientsQuery->fetchColumn()); 
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card info animate-fade-in stagger-2">
                    <div class="icon-wrapper"><span class="icon" data-icon="📅"></span></div>
                    <div class="content">
                        <div class="label">زيارات اليوم</div>
                        <div class="value">
                            <?php 
                            $todayVisitsQuery = $conn->query("SELECT COUNT(DISTINCT patient_id) FROM visits WHERE DATE(visit_date) = CURDATE()");
                            echo number_format($todayVisitsQuery->fetchColumn()); 
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card warning animate-fade-in stagger-3">
                    <div class="icon-wrapper"><span class="icon" data-icon="⚕️"></span></div>
                    <div class="content">
                        <div class="label">مرضى نشطون</div>
                        <div class="value">
                            <?php 
                            $activePatientsQuery = $conn->query("SELECT COUNT(DISTINCT patient_id) FROM visits WHERE visit_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
                            echo number_format($activePatientsQuery->fetchColumn()); 
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 📋 قائمة المرضى -->
        <div class="card animate-slide-in">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6><span class="icon" data-icon="👥"></span>قائمة المرضى</h6>
                <span class="badge bg-primary"><?php echo number_format($totalPatients); ?> مريض</span>
            </div>
            <div class="card-body">
                <?php if (empty($patients)): ?>
                    <div class="text-center py-5">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">🔍</div>
                        <h5>لا توجد نتائج</h5>
                        <p class="text-muted">لم يتم العثور على مرضى يطابقون معايير البحث</p>
                        <a href="patients.php" class="btn btn-primary">عرض جميع المرضى</a>
                    </div>
                <?php else: ?>
                    <?php foreach($patients as $patient): ?>
                        <div class="patient-card animate-fade-in">
                            <div class="d-flex">
                                <div class="patient-avatar">
                                    <?php echo mb_substr($patient['full_name'], 0, 2); ?>
                                </div>
                                <div class="patient-details flex-grow-1">
                                    <h5><?php echo htmlspecialchars($patient['full_name']); ?></h5>
                                    <div class="patient-meta">
                                        <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient['phone']); ?></span>
                                        <span><i class="fas fa-id-card"></i> <?php echo htmlspecialchars($patient['national_id']); ?></span>
                                        <?php if (!empty($patient['email'])): ?>
                                            <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($patient['email']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="patient-stats">
                                        <div class="stat-item">
                                            <span class="value"><?php echo (int)$patient['visits_count']; ?></span>
                                            <span class="label">زيارات</span>
                                        </div>
                                        <div class="stat-item">
                                            <span class="value"><?php echo $patient['last_visit'] ? date('d/m/Y', strtotime($patient['last_visit'])) : '-'; ?></span>
                                            <span class="label">آخر زيارة</span>
                                        </div>
                                        <div class="stat-item">
                                            <span class="value"><?php echo date('d/m/Y', strtotime($patient['created_by'])); ?></span>
                                            <span class="label">تاريخ التسجيل</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="patient-actions">
                                <a href="patient_profile.php?id=<?php echo $patient['id']; ?>" class="btn btn-outline-primary btn-sm">
                                    <span class="icon" data-icon="👁️"></span>عرض التفاصيل
                                </a>
                                <a href="patient_visits.php?id=<?php echo $patient['id']; ?>" class="btn btn-outline-info btn-sm">
                                    <span class="icon" data-icon="📋"></span>الزيارات
                                </a>
                                <a href="edit_patient.php?id=<?php echo $patient['id']; ?>" class="btn btn-outline-warning btn-sm">
                                    <span class="icon" data-icon="✏️"></span>تعديل
                                </a>
                                <button class="btn btn-outline-danger btn-sm" onclick="confirmDelete(<?php echo $patient['id']; ?>, '<?php echo htmlspecialchars($patient['full_name']); ?>')">
                                    <span class="icon" data-icon="🗑️"></span>حذف
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- 📄 ترقيم الصفحات -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination-wrapper">
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-link">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>

                    <?php 
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);

                    if ($startPage > 1) {
                        echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '" class="page-link">1</a>';
                        if ($startPage > 2) echo '<span class="page-link">...</span>';
                    }

                    for ($i = $startPage; $i <= $endPage; $i++) {
                        $activeClass = $i == $page ? 'active' : '';
                        echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $i])) . '" class="page-link ' . $activeClass . '">' . $i . '</a>';
                    }

                    if ($endPage < $totalPages) {
                        if ($endPage < $totalPages - 1) echo '<span class="page-link">...</span>';
                        echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $totalPages])) . '" class="page-link">' . $totalPages . '</a>';
                    }
                    ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- مودال إضافة مريض جديد -->
    <div class="modal fade" id="addPatientModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة مريض جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addPatientForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="fullName" class="form-label">الاسم الكامل</label>
                                <input type="text" class="form-control" id="fullName" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="nationalId" class="form-label">الرقم الوطني</label>
                                <input type="text" class="form-control" id="nationalId" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">رقم الهاتف</label>
                                <input type="tel" class="form-control" id="phone" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">البريد الإلكتروني</label>
                                <input type="email" class="form-control" id="email">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="birthDate" class="form-label">تاريخ الميلاد</label>
                                <input type="date" class="form-control" id="birthDate">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="gender" class="form-label">الجنس</label>
                                <select class="form-select" id="gender">
                                    <option value="">اختر</option>
                                    <option value="Male">ذكر</option>
                                    <option value="Female">أنثى</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">العنوان</label>
                            <textarea class="form-control" id="address" rows="2"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="bloodType" class="form-label">فصيلة الدم</label>
                                <select class="form-select" id="bloodType">
                                    <option value="">اختر</option>
                                    <option value="A+">A+</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B-">B-</option>
                                    <option value="AB+">AB+</option>
                                    <option value="AB-">AB-</option>
                                    <option value="O+">O+</option>
                                    <option value="O-">O-</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="chronicDiseases" class="form-label">أمراض مزمنة</label>
                                <input type="text" class="form-control" id="chronicDiseases" placeholder="مثال: سكري، ضغط">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="allergies" class="form-label">حساسية</label>
                            <input type="text" class="form-control" id="allergies" placeholder="مثال: بنسلين، فراولة">
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label">ملاحظات إضافية</label>
                            <textarea class="form-control" id="notes" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-primary" id="savePatientBtn">حفظ المريض</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 🔄 سكريبت التفاعل -->
    <script>
    // Toggle Sidebar on Mobile
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

    // Navbar scroll effect
    window.addEventListener('scroll', () => {
        document.querySelector('.navbar')?.classList.toggle('scrolled', window.scrollY > 10);
    });

    // Stagger animation on load
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.animate-fade-in, .animate-slide-in').forEach((el, i) => {
            el.style.opacity = '0';
            el.style.animationDelay = `${i * 0.05}s`;
            setTimeout(() => { el.style.opacity = '1'; }, 100);
        });
    });

    // حفظ بيانات المريض الجديد
    document.getElementById('savePatientBtn').addEventListener('click', function() {
        const form = document.getElementById('addPatientForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const patientData = {
            full_name: document.getElementById('fullName').value,
            national_id: document.getElementById('nationalId').value,
            phone: document.getElementById('phone').value,
            email: document.getElementById('email').value,
            birth_date: document.getElementById('birthDate').value,
            gender: document.getElementById('gender').value,
            address: document.getElementById('address').value,
            blood_type: document.getElementById('bloodType').value,
            chronic_diseases: document.getElementById('chronicDiseases').value,
            allergies: document.getElementById('allergies').value,
            notes: document.getElementById('notes').value
        };

        fetch('api/add_patient.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(patientData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // إغلاق المودال
                const modal = bootstrap.Modal.getInstance(document.getElementById('addPatientModal'));
                modal.hide();

                // إعادة تعيين النموذج
                form.reset();

                // إظهار رسالة نجاح
                showToast('تم إضافة المريض بنجاح', 'success');

                // إعادة تحميل الصفحة بعد فترة قصيرة
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showToast(data.message || 'حدث خطأ أثناء إضافة المريض', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('حدث خطأ أثناء الاتصال بالخادم', 'error');
        });
    });

    // تأكيد حذف المريض
    function confirmDelete(patientId, patientName) {
        if (confirm(`هل أنت متأكد من حذف المريض "${patientName}"؟

هذا الإجراء لا يمكن التراجع عنه.`)) {
            fetch('api/delete_patient.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: patientId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('تم حذف المريض بنجاح', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showToast(data.message || 'حدث خطأ أثناء حذف المريض', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('حدث خطأ أثناء الاتصال بالخادم', 'error');
            });
        }
    }

    // دالة عرض رسائل التنبيه
    function showToast(message, type = 'info') {
        // إنشاء عنصر التوست
        const toast = document.createElement('div');
        toast.className = `toast-notification ${type}`;
        toast.textContent = message;

        // إضافة التوست إلى الصفحة
        document.body.appendChild(toast);

        // إظهار التوست
        setTimeout(() => {
            toast.classList.add('show');
        }, 100);

        // إخفاء وإزالة التوست بعد فترة
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                document.body.removeChild(toast);
            }, 300);
        }, 3000);
    }
    </script>

    <style>
    /* تنسيق رسائل التوست */
    .toast-notification {
        position: fixed;
        bottom: 20px;
        left: 20px;
        background: var(--card-bg);
        color: var(--text);
        padding: 12px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 9999;
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.3s ease;
        border-right: 4px solid var(--primary);
        max-width: 350px;
    }

    .toast-notification.success {
        border-right-color: var(--success);
    }

    .toast-notification.error {
        border-right-color: var(--danger);
    }

    .toast-notification.warning {
        border-right-color: var(--warning);
    }

    .toast-notification.show {
        transform: translateY(0);
        opacity: 1;
    }
    </style>
    
</body>
</html>