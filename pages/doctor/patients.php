<?php
require_once '../../includes/auth.php';
checkRole(['Admin', 'Doctor']);

require_once '../../config/db.php';

// الحصول على قائمة المرضى
$patients = $conn->query("SELECT * FROM patients ORDER BY full_name")->fetchAll();

// معالجة إضافة مريض جديد
if(isset($_POST['add_patient'])) {
    try {
        $stmt = $conn->prepare("INSERT INTO patients (full_name, medical_record_number, age, gender, phone, address, email, blood_type, allergies, chronic_diseases) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['full_name'],
            $_POST['medical_record_number'],
            $_POST['age'],
            $_POST['gender'],
            $_POST['phone'],
            $_POST['address'],
            $_POST['email'],
            $_POST['blood_type'],
            $_POST['allergies'],
            $_POST['chronic_diseases']
        ]);

        // تسجيل النشاط
        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Add Patient', 'patients', ?)");
        $logStmt->execute([$_SESSION['user_id'], $conn->lastInsertId()]);

        $success = "تم إضافة المريض بنجاح";
        
        // إعادة تحميل قائمة المرضى
        $patients = $conn->query("SELECT * FROM patients ORDER BY full_name")->fetchAll();
    } catch(PDOException $e) {
        $error = "حدث خطأ: " . $e->getMessage();
    }
}

// معالجة تحديث بيانات المريض
if(isset($_POST['update_patient'])) {
    try {
        $stmt = $conn->prepare("UPDATE patients SET full_name = ?, medical_record_number = ?, age = ?, gender = ?, phone = ?, address = ?, email = ?, blood_type = ?, allergies = ?, chronic_diseases = ? WHERE id = ?");
        $stmt->execute([
            $_POST['full_name'],
            $_POST['medical_record_number'],
            $_POST['age'],
            $_POST['gender'],
            $_POST['phone'],
            $_POST['address'],
            $_POST['email'],
            $_POST['blood_type'],
            $_POST['allergies'],
            $_POST['chronic_diseases'],
            $_POST['patient_id']
        ]);

        // تسجيل النشاط
        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'Update Patient', 'patients', ?)");
        $logStmt->execute([$_SESSION['user_id'], $_POST['patient_id']]);

        $success = "تم تحديث بيانات المريض بنجاح";
        
        // إعادة تحميل قائمة المرضى
        $patients = $conn->query("SELECT * FROM patients ORDER BY full_name")->fetchAll();
    } catch(PDOException $e) {
        $error = "حدث خطأ: " . $e->getMessage();
    }
}

// الحصول على بيانات مريض محدد للتعديل
$edit_patient = null;
if(isset($_GET['edit_id'])) {
    $edit_patient = $conn->query("SELECT * FROM patients WHERE id = " . $_GET['edit_id'])->fetch();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المرضى</title>
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
                            <a class="nav-link active" href="patients.php">
                                <i class="fas fa-users me-2"></i>
                                إدارة المرضى
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="visits.php">
                                <i class="fas fa-calendar-check me-2"></i>
                                إدارة الزيارات
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="prescriptions.php">
                                <i class="fas fa-prescription me-2"></i>
                                إدارة الوصفات
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../reception/lab_requests.php">
                                <i class="fas fa-vial me-2"></i>
                                طلبات فحوصات المعمل
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
                    <h1 class="h2">إدارة المرضى</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#patientModal">
                                <i class="fas fa-plus me-1"></i>
                                مريض جديد
                            </button>
                        </div>
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

                <!-- قائمة المرضى -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">قائمة المرضى</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="patientsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>الاسم</th>
                                        <th>رقم الملف</th>
                                        <th>العمر</th>
                                        <th>الجنس</th>
                                        <th>الهاتف</th>
                                        <th>فصيلة الدم</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($patients as $patient): ?>
                                    <tr>
                                        <td><?php echo $patient['full_name']; ?></td>
                                        <td><?php echo $patient['medical_record_number']; ?></td>
                                        <td><?php echo $patient['age']; ?></td>
                                        <td><?php echo $patient['gender']; ?></td>
                                        <td><?php echo $patient['phone']; ?></td>
                                        <td><?php echo $patient['blood_type']; ?></td>
                                        <td>
                                            <a href="?edit_id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="visits.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-calendar-check"></i>
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

    <!-- نموذج إضافة/تعديل مريض -->
    <div class="modal fade" id="patientModal" tabindex="-1" aria-labelledby="patientModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="patientModalLabel"><?php echo $edit_patient ? 'تعديل بيانات المريض' : 'إضافة مريض جديد'; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <?php if($edit_patient): ?>
                        <input type="hidden" name="patient_id" value="<?php echo $edit_patient['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="full_name" class="form-label">الاسم الكامل</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo $edit_patient ? $edit_patient['full_name'] : ''; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="medical_record_number" class="form-label">رقم الملف الطبي</label>
                                <input type="text" class="form-control" id="medical_record_number" name="medical_record_number" value="<?php echo $edit_patient ? $edit_patient['medical_record_number'] : ''; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="age" class="form-label">العمر</label>
                                <input type="number" class="form-control" id="age" name="age" value="<?php echo $edit_patient ? $edit_patient['age'] : ''; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="gender" class="form-label">الجنس</label>
                                <select class="form-select" id="gender" name="gender" required>
                                    <option value="">اختر</option>
                                    <option value="ذكر" <?php echo $edit_patient && $edit_patient['gender'] == 'ذكر' ? 'selected' : ''; ?>>ذكر</option>
                                    <option value="أنثى" <?php echo $edit_patient && $edit_patient['gender'] == 'أنثى' ? 'selected' : ''; ?>>أنثى</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">الهاتف</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo $edit_patient ? $edit_patient['phone'] : ''; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">البريد الإلكتروني</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo $edit_patient ? $edit_patient['email'] : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="blood_type" class="form-label">فصيلة الدم</label>
                                <select class="form-select" id="blood_type" name="blood_type">
                                    <option value="">اختر</option>
                                    <option value="A+" <?php echo $edit_patient && $edit_patient['blood_type'] == 'A+' ? 'selected' : ''; ?>>A+</option>
                                    <option value="A-" <?php echo $edit_patient && $edit_patient['blood_type'] == 'A-' ? 'selected' : ''; ?>>A-</option>
                                    <option value="B+" <?php echo $edit_patient && $edit_patient['blood_type'] == 'B+' ? 'selected' : ''; ?>>B+</option>
                                    <option value="B-" <?php echo $edit_patient && $edit_patient['blood_type'] == 'B-' ? 'selected' : ''; ?>>B-</option>
                                    <option value="O+" <?php echo $edit_patient && $edit_patient['blood_type'] == 'O+' ? 'selected' : ''; ?>>O+</option>
                                    <option value="O-" <?php echo $edit_patient && $edit_patient['blood_type'] == 'O-' ? 'selected' : ''; ?>>O-</option>
                                    <option value="AB+" <?php echo $edit_patient && $edit_patient['blood_type'] == 'AB+' ? 'selected' : ''; ?>>AB+</option>
                                    <option value="AB-" <?php echo $edit_patient && $edit_patient['blood_type'] == 'AB-' ? 'selected' : ''; ?>>AB-</option>
                                </select>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="address" class="form-label">العنوان</label>
                                <input type="text" class="form-control" id="address" name="address" value="<?php echo $edit_patient ? $edit_patient['address'] : ''; ?>">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="allergies" class="form-label">الحساسية</label>
                                <textarea class="form-control" id="allergies" name="allergies" rows="2"><?php echo $edit_patient ? $edit_patient['allergies'] : ''; ?></textarea>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="chronic_diseases" class="form-label">الأمراض المزمنة</label>
                                <textarea class="form-control" id="chronic_diseases" name="chronic_diseases" rows="2"><?php echo $edit_patient ? $edit_patient['chronic_diseases'] : ''; ?></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="<?php echo $edit_patient ? 'update_patient' : 'add_patient'; ?>" class="btn btn-primary">
                            <?php echo $edit_patient ? 'تحديث' : 'إضافة'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // البحث في قائمة المرضى
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.createElement('div');
            searchInput.className = 'p-3 border-bottom';
            searchInput.innerHTML = `
                <div class="input-group">
                    <input type="text" class="form-control" id="searchPatients" placeholder="البحث عن مريض...">
                    <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            const table = document.getElementById('patientsTable');
            table.parentNode.insertBefore(searchInput, table);
            
            // البحث في الجدول
            document.getElementById('searchPatients').addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = table.querySelectorAll('tbody tr');
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            });
            
            // مسح البحث
            document.getElementById('clearSearch').addEventListener('click', function() {
                document.getElementById('searchPatients').value = '';
                const rows = table.querySelectorAll('tbody tr');
                
                rows.forEach(row => {
                    row.style.display = '';
                });
            });
        });
    </script>
</body>
</html>