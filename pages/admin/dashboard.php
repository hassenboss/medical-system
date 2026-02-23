<?php
require_once '../../includes/auth.php';
checkRole(['Admin']);
require_once '../../config/db.php';

// ============================================
// 📊 جمع الإحصائيات العامة
// ============================================
$patientsCount = $conn->query("SELECT COUNT(*) as count FROM patients")->fetch()['count'] ?? 0;
$visitsCount = $conn->query("SELECT COUNT(*) as count FROM visits")->fetch()['count'] ?? 0;
$doctorsCount = $conn->query("SELECT COUNT(*) as count FROM doctors WHERE is_active = 1")->fetch()['count'] ?? 0;
$todayVisits = $conn->query("SELECT COUNT(*) as count FROM visits WHERE DATE(visit_date) = CURDATE()")->fetch()['count'] ?? 0;

// ============================================
// 💰 الإحصائيات المالية - تفصيل اليوم
// ============================================
$todayIncome = $conn->query("SELECT COALESCE(SUM(final_amount), 0) as total FROM invoices WHERE DATE(created_at) = CURDATE() AND payment_status = 'Paid'")->fetch()['total'] ?? 0;
$labIncomeToday = $conn->query("SELECT COALESCE(SUM(ii.total_price), 0) FROM invoice_items ii JOIN invoices i ON ii.invoice_id = i.id WHERE ii.item_type = 'Lab Test' AND DATE(i.created_at) = CURDATE() AND i.payment_status = 'Paid'")->fetchColumn() ?? 0;
$doctorIncomeToday = $conn->query("SELECT COALESCE(SUM(d.consultation_fee), 0) FROM visits v JOIN doctors d ON v.doctor_id = d.id WHERE DATE(v.visit_date) = CURDATE() AND v.status IN ('Consultation Paid', 'Lab Paid', 'Lab Completed', 'Pharmacy Paid', 'Completed')")->fetchColumn() ?? 0;
$pharmacyIncomeToday = $conn->query("SELECT COALESCE(SUM(ii.total_price), 0) FROM invoice_items ii JOIN invoices i ON ii.invoice_id = i.id WHERE ii.item_type = 'Medicine' AND DATE(i.created_at) = CURDATE() AND i.payment_status = 'Paid'")->fetchColumn() ?? 0;
$servicesIncomeToday = $conn->query("SELECT COALESCE(SUM(ii.total_price), 0) FROM invoice_items ii JOIN invoices i ON ii.invoice_id = i.id WHERE ii.item_type = 'Service' AND DATE(i.created_at) = CURDATE() AND i.payment_status = 'Paid'")->fetchColumn() ?? 0;

// ============================================
// 🔬 إحصائيات المعمل والصيدلية
// ============================================
$totalLabTests = $conn->query("SELECT COUNT(*) FROM lab_requests")->fetchColumn() ?? 0;
$completedLabToday = $conn->query("SELECT COUNT(*) FROM lab_requests WHERE DATE(completed_date) = CURDATE() AND status = 'Completed'")->fetchColumn() ?? 0;
$pendingLabTests = $conn->query("SELECT COUNT(*) FROM lab_requests WHERE status = 'Pending'")->fetchColumn() ?? 0;
$totalPrescriptions = $conn->query("SELECT COUNT(*) FROM prescriptions WHERE status = 'Dispensed'")->fetchColumn() ?? 0;
$prescriptionsToday = $conn->query("SELECT COUNT(*) FROM prescriptions WHERE DATE(dispensed_date) = CURDATE() AND status = 'Dispensed'")->fetchColumn() ?? 0;
$lowMedicines = $conn->query("SELECT COUNT(*) FROM medicines WHERE quantity <= min_quantity AND is_active = 1")->fetchColumn() ?? 0;

// ============================================
// 📈 بيانات الرسوم البيانية (آخر 7 أيام)
// ============================================
$chartData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dayName = ['الأحد','الاثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت'][date('w', strtotime($date))];
    
    $v = $conn->prepare("SELECT COUNT(*) FROM visits WHERE DATE(visit_date) = ?");
    $v->execute([$date]);
    
    $inc = $conn->prepare("SELECT COALESCE(SUM(final_amount), 0) FROM invoices WHERE DATE(created_at) = ? AND payment_status = 'Paid'");
    $inc->execute([$date]);
    
    $chartData[] = [
        'label' => $dayName,
        'visits' => $v->fetchColumn(),
        'income' => $inc->fetchColumn()
    ];
}

// ============================================
// 📋 آخر الزيارات والمستخدمين
// ============================================
$recentVisits = $conn->query("SELECT v.id, p.full_name as patient_name, d.full_name as doctor_name, v.visit_date, v.status FROM visits v JOIN patients p ON v.patient_id = p.id JOIN doctors d ON v.doctor_id = d.id ORDER BY v.visit_date DESC LIMIT 8")->fetchAll();
$activeUsers = $conn->query("SELECT u.full_name, r.role_name, u.last_login FROM users u JOIN roles r ON u.role_id = r.id WHERE u.is_active = 1 ORDER BY u.last_login DESC LIMIT 6")->fetchAll();

// ============================================
// 📊 نسب الحالة (للدائرة)
// ============================================
$statusCounts = $conn->query("SELECT status, COUNT(*) as count FROM visits GROUP BY status")->fetchAll();
$statusData = array_column($statusCounts, 'count', 'status');
$totalStatus = array_sum($statusData) ?: 1;
$completedPct = round(($statusData['Completed'] ?? 0) / $totalStatus * 100);
$consultPct = round((($statusData['Completed'] ?? 0) + ($statusData['In Consultation'] ?? 0)) / $totalStatus * 100);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم المدير - 2050</title>
    <link href="../../assets/css/dashboardo.css" rel="stylesheet">
    <style>
        /* تخصيصات إضافية للوحة التحكم */
        .header-greeting { font-size: 1.5rem; font-weight: 700; }
        .header-greeting span { color: var(--primary); }
        .quick-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; }
        .quick-actions .btn { padding: 0.5rem 1rem; font-size: 0.8rem; }
        .section-title { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; }
        .section-title i { color: var(--primary); }
    </style>
</head>
<body>
    <!-- 🧭 شريط التنقل الهولوجرافيك -->
    <nav class="navbar">
        <div class="d-flex align-items-center gap-3">
            <button class="navbar-toggler d-lg-none" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <a class="navbar-brand" href="#">
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
            <li class="nav-item"><a class="nav-link active" href="#"><span class="icon" data-icon="📊"></span>لوحة التحكم</a></li>
            <li class="nav-item"><a class="nav-link" href="patients.php"><span class="icon" data-icon="🤒"></span>المرضى</a></li>
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
                <h1 class="header-greeting mb-1">مرحباً، <span><?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'] ?? 'مدير')[0]); ?></span> 👋</h1>
                <p class="text-muted mb-0">إليك ملخص نشاط المستشفى اليوم</p>
            </div>
            <div class="quick-actions">
                <button class="btn btn-outline"><span class="icon" data-icon="📥"></span>تصدير</button>
                <button class="btn btn-primary"><span class="icon" data-icon="➕"></span>زيارة جديدة</button>
            </div>
        </div>

        <!-- 🎨 بطاقات الإحصائيات الرئيسية -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card primary animate-fade-in">
                    <div class="icon-wrapper"><span class="icon" data-icon="🤒"></span></div>
                    <div class="content">
                        <div class="label">إجمالي المرضى</div>
                        <div class="value"><?php echo number_format($patientsCount); ?></div>
                        <div class="trend up">↑ 12% هذا الشهر</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card success animate-fade-in stagger-1">
                    <div class="icon-wrapper"><span class="icon" data-icon="📅"></span></div>
                    <div class="content">
                        <div class="label">إجمالي الزيارات</div>
                        <div class="value"><?php echo number_format($visitsCount); ?></div>
                        <div class="trend up">↑ 8% هذا الشهر</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card info animate-fade-in stagger-2">
                    <div class="icon-wrapper"><span class="icon" data-icon="🩺"></span></div>
                    <div class="content">
                        <div class="label">الأطباء النشطون</div>
                        <div class="value"><?php echo number_format($doctorsCount); ?></div>
                        <div class="trend">مستقر</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stat-card warning animate-fade-in stagger-3">
                    <div class="icon-wrapper"><span class="icon" data-icon="⚡"></span></div>
                    <div class="content">
                        <div class="label">زيارات اليوم</div>
                        <div class="value"><?php echo number_format($todayVisits); ?></div>
                        <div class="trend up">↑ 5% عن الأمس</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 💰 بطاقات الدخل التفصيلية -->
        <div class="card mb-4 animate-slide-in">
            <div class="card-header">
                <h6><span class="icon" data-icon="💰"></span>الدخل اليوم - تفصيل الأقسام</h6>
                <span class="badge bg-primary"><?php echo number_format($todayIncome, 2); ?> ريال</span>
            </div>
            <div class="card-body">
                <div class="income-grid">
                    <div class="income-card doctor">
                        <div class="icon">🩺</div>
                        <div class="label">رسوم الكشف</div>
                        <div class="amount"><?php echo number_format($doctorIncomeToday, 2); ?></div>
                        <div class="currency">ريال</div>
                    </div>
                    <div class="income-card lab">
                        <div class="icon">🧪</div>
                        <div class="label">فحوصات المعمل</div>
                        <div class="amount"><?php echo number_format($labIncomeToday, 2); ?></div>
                        <div class="currency">ريال</div>
                    </div>
                    <div class="income-card pharmacy">
                        <div class="icon">💊</div>
                        <div class="label">مبيعات الصيدلية</div>
                        <div class="amount"><?php echo number_format($pharmacyIncomeToday, 2); ?></div>
                        <div class="currency">ريال</div>
                    </div>
                    <div class="income-card services">
                        <div class="icon">🔧</div>
                        <div class="label">خدمات أخرى</div>
                        <div class="amount"><?php echo number_format($servicesIncomeToday, 2); ?></div>
                        <div class="currency">ريال</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 🔬💊 إحصائيات المعمل والصيدلية -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card animate-fade-in">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,var(--info),#22d3ee);display:flex;align-items:center;justify-content:center;font-size:1.5rem">🧪</div>
                            <div>
                                <h6 class="mb-0">المعمل</h6>
                                <small class="text-muted">إحصائيات الفحوصات</small>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">إجمالي الفحوصات</span>
                            <strong><?php echo number_format($totalLabTests); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-success">✅ مكتمل اليوم</span>
                            <strong><?php echo number_format($completedLabToday); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-warning">⏳ قيد الانتظار</span>
                            <strong><?php echo number_format($pendingLabTests); ?></strong>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card animate-fade-in stagger-1">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#8b5cf6,#a78bfa);display:flex;align-items:center;justify-content:center;font-size:1.5rem">💊</div>
                            <div>
                                <h6 class="mb-0">الصيدلية</h6>
                                <small class="text-muted">إحصائيات الأدوية</small>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">الوصفات المصروفة</span>
                            <strong><?php echo number_format($totalPrescriptions); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-success">✅ اليوم</span>
                            <strong><?php echo number_format($prescriptionsToday); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-danger">⚠️ أدوية ناقصة</span>
                            <strong><?php echo number_format($lowMedicines); ?></strong>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card animate-fade-in stagger-2">
                    <div class="card-body text-center">
                        <h6 class="mb-3">توزيع حالات الزيارات</h6>
                        <div class="pie-chart-wrapper">
                            <div class="pie-chart" style="--completed-pct: <?php echo $completedPct; ?>%; --consult-pct: <?php echo $consultPct; ?>%;" data-center="<?php echo $completedPct; ?>%"></div>
                            <div class="pie-legend">
                                <div class="pie-legend-item"><span class="pie-color" style="background:var(--success)"></span>مكتملة</div>
                                <div class="pie-legend-item"><span class="pie-color" style="background:var(--primary)"></span>قيد الكشف</div>
                                <div class="pie-legend-item"><span class="pie-color" style="background:var(--warning)"></span>أخرى</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 📈 الرسوم البيانية -->
        <div class="row mb-4">
            <div class="col-lg-8 mb-3">
                <div class="card animate-slide-in">
                    <div class="card-header">
                        <h6><span class="icon" data-icon="📈"></span>الزيارات - آخر 7 أيام</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <div class="chart-bar-wrapper">
                                <?php foreach($chartData as $data): 
                                    $maxVisits = max(array_column($chartData, 'visits')) ?: 1;
                                    $height = ($data['visits'] / $maxVisits) * 160;
                                ?>
                                <div class="chart-bar">
                                    <div class="chart-bar-fill" style="height: <?php echo max($height, 4); ?>px;" data-value="<?php echo $data['visits']; ?>"></div>
                                    <span class="chart-bar-label"><?php echo $data['label']; ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mb-3">
                <div class="card animate-slide-in stagger-1">
                    <div class="card-header">
                        <h6><span class="icon" data-icon="⚡"></span>نظرة سريعة</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-column gap-3">
                            <div class="d-flex justify-content-between align-items-center p-3" style="background:rgba(99,102,241,0.1);border-radius:12px">
                                <span>معدل الزيارات اليومي</span>
                                <strong class="text-primary"><?php echo round($visitsCount / 30); ?>/يوم</strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center p-3" style="background:rgba(16,185,129,0.1);border-radius:12px">
                                <span>نسبة إتمام الزيارات</span>
                                <strong class="text-success"><?php echo $completedPct; ?>%</strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center p-3" style="background:rgba(245,158,11,0.1);border-radius:12px">
                                <span>متوسط قيمة الفاتورة</span>
                                <strong class="text-warning"><?php echo $todayIncome && $todayVisits ? number_format($todayIncome / $todayVisits, 0) : 0; ?> ريال</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 📋 الجداول -->
        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card animate-fade-in">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6><span class="icon" data-icon="🕐"></span>آخر الزيارات</h6>
                        <a href="visits.php" class="btn btn-outline btn-sm">عرض الكل</a>
                    </div>
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr><th>المريض</th><th>الطبيب</th><th>الوقت</th><th>الحالة</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($recentVisits as $visit): 
                                    $badge = match($visit['status']) {
                                        'Completed' => ['bg-success', 'مكتملة'],
                                        'In Consultation' => ['bg-primary', 'قيد الكشف'],
                                        'Lab Payment Pending', 'Pharmacy Payment Pending' => ['bg-warning', 'انتظار'],
                                        default => ['bg-secondary', $visit['status']]
                                    };
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($visit['patient_name']); ?></td>
                                    <td><?php echo htmlspecialchars($visit['doctor_name']); ?></td>
                                    <td><?php echo date('H:i', strtotime($visit['visit_date'])); ?></td>
                                    <td><span class="badge <?php echo $badge[0]; ?>"><?php echo $badge[1]; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-4">
                <div class="card animate-fade-in stagger-1">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6><span class="icon" data-icon="👥"></span>المستخدمون النشطون</h6>
                        <a href="users.php" class="btn btn-outline btn-sm">إدارة</a>
                    </div>
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr><th>الاسم</th><th>الدور</th><th>آخر دخول</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($activeUsers as $user): 
                                    $roleBadge = match($user['role_name']) {
                                        'Admin' => 'bg-danger',
                                        'Doctor' => 'bg-primary',
                                        'Lab Technician' => 'bg-info',
                                        'Pharmacist' => 'bg-success',
                                        default => 'bg-secondary'
                                    };
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><span class="badge <?php echo $roleBadge; ?>"><?php echo htmlspecialchars($user['role_name']); ?></span></td>
                                    <td><?php echo $user['last_login'] ? date('d/m H:i', strtotime($user['last_login'])) : '-'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

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
    
    // Chart bar hover effect
    document.querySelectorAll('.chart-bar-fill').forEach(bar => {
        bar.addEventListener('mouseenter', function() {
            this.style.transform = 'scaleY(1.05)';
            this.style.boxShadow = '0 -8px 30px var(--primary-glow)';
        });
        bar.addEventListener('mouseleave', function() {
            this.style.transform = 'scaleY(1)';
            this.style.boxShadow = '0 -4px 20px var(--primary-glow)';
        });
    });
    
    // Stagger animation on load
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.animate-fade-in, .animate-slide-in').forEach((el, i) => {
            el.style.opacity = '0';
            el.style.animationDelay = `${i * 0.05}s`;
            setTimeout(() => { el.style.opacity = '1'; }, 100);
        });
    });
    </script>
</body>
</html>