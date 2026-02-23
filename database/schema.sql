
-- إنشاء قاعدة البيانات
CREATE DATABASE IF NOT EXISTS medical_system;
USE medical_system;

-- جدول الأدوار (Roles)
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- إضافة الأدوار الأساسية
INSERT INTO roles (role_name, description) VALUES
('Admin', 'مدير النظام'),
('Reception', 'الاستقبال'),
('Doctor', 'الطبيب'),
('Lab Technician', 'المعمل'),
('Pharmacist', 'الصيدلية'),
('Accountant', 'المحاسب');

-- جدول المستخدمين
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    role_id INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

-- جدول المرضى
CREATE TABLE patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medical_record_number VARCHAR(20) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    national_id VARCHAR(20),
    phone VARCHAR(20),
    address VARCHAR(255),
    age INT,
    gender ENUM('ذكر', 'أنثى') NOT NULL,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- جدول الأطباء
CREATE TABLE doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    specialization VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    consultation_fee DECIMAL(10, 2) NOT NULL DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- جدول الخدمات
CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    type ENUM('فحص', 'تمريض', 'أشعة', 'تحليل', 'أخرى') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- جدول الزيارات
CREATE TABLE visits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    visit_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Registered', 'Consultation Paid', 'In Consultation', 'Lab Payment Pending', 'Lab Paid', 'Lab Completed', 'Pharmacy Payment Pending', 'Pharmacy Paid', 'Completed') NOT NULL DEFAULT 'Registered',
    symptoms TEXT,
    vital_signs TEXT,
    notes TEXT,
    diagnosis TEXT,
    created_by INT,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- جدول فحوصات المعمل
CREATE TABLE lab_tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- جدول طلبات الفحوصات
CREATE TABLE lab_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visit_id INT NOT NULL,
    lab_test_id INT NOT NULL,
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Pending', 'Paid', 'Completed') NOT NULL DEFAULT 'Pending',
    results TEXT,
    notes TEXT,
    completed_by INT,
    completed_date TIMESTAMP NULL,
    FOREIGN KEY (visit_id) REFERENCES visits(id),
    FOREIGN KEY (lab_test_id) REFERENCES lab_tests(id),
    FOREIGN KEY (completed_by) REFERENCES users(id)
);

-- جدول الأدوية
CREATE TABLE medicines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    min_quantity INT NOT NULL DEFAULT 10,
    expiry_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- جدول الوصفات الطبية
CREATE TABLE prescriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visit_id INT NOT NULL,
    medicine_id INT NOT NULL,
    dosage VARCHAR(100) NOT NULL,
    duration VARCHAR(50) NOT NULL,
    instructions TEXT,
    status ENUM('Pending', 'Paid', 'Dispensed') NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    dispensed_by INT,
    dispensed_date TIMESTAMP NULL,
    FOREIGN KEY (visit_id) REFERENCES visits(id),
    FOREIGN KEY (medicine_id) REFERENCES medicines(id),
    FOREIGN KEY (dispensed_by) REFERENCES users(id)
);

-- جدول الفواتير
CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(20) NOT NULL UNIQUE,
    patient_id INT NOT NULL,
    visit_id INT,
    total_amount DECIMAL(10, 2) NOT NULL,
    discount_amount DECIMAL(10, 2) DEFAULT 0,
    tax_amount DECIMAL(10, 2) DEFAULT 0,
    final_amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('نقدي', 'تحويل', 'بنك كاش', 'بطاقة ائتمان') NOT NULL,
    payment_status ENUM('Pending', 'Paid', 'Partial') NOT NULL DEFAULT 'Pending',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (visit_id) REFERENCES visits(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- جدول تفاصيل الفواتير
CREATE TABLE invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    item_type ENUM('Service', 'Lab Test', 'Medicine') NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10, 2) NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id)
);

-- جدول المدفوعات
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('نقدي', 'تحويل', 'بنك كاش', 'بطاقة ائتمان') NOT NULL,
    transaction_number VARCHAR(50),
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- جدول مبيعات الصيدلية
CREATE TABLE pharmacy_sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prescription_id INT NOT NULL,
    medicine_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    sold_by INT,
    sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id),
    FOREIGN KEY (medicine_id) REFERENCES medicines(id),
    FOREIGN KEY (sold_by) REFERENCES users(id)
);

-- جدول سجل النشاط
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_values TEXT,
    new_values TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- جدول قائمة الأسعار
CREATE TABLE price_list (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_type ENUM('Service', 'Lab Test', 'Medicine') NOT NULL,
    item_id INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    effective_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- إضافة مستخدم مدير افتراضي
INSERT INTO users (username, password, full_name, email, phone, role_id) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مدير النظام', 'admin@hospital.com', '0123456789', 1);

-- إضافة بعض الأطباء
INSERT INTO doctors (full_name, specialization, phone, email, consultation_fee) VALUES
('د. أحمد محمد', 'طب عام', '01234567890', 'ahmed@hospital.com', 150),
('د. سارة أحمد', 'أمراض القلب', '01234567891', 'sara@hospital.com', 300),
('د. محمد علي', 'جراحة', '01234567892', 'mohammed@hospital.com', 500);

-- إضافة بعض الخدمات
INSERT INTO services (name, price, type) VALUES
('كشف عام', 150, 'فحص'),
('كشف قلب', 300, 'فحص'),
('قياس ضغط الدم', 50, 'فحص'),
('حقنة عضل', 30, 'تمريض'),
('محلول وريدي', 100, 'تمريض'),
('أشعة سينية', 200, 'أشعة'),
('أشعة مقطعية', 500, 'أشعة');

-- إضافة بعض فحوصات المعمل
INSERT INTO lab_tests (name, price, description) VALUES
('تحليل دم كامل', 100, 'تحليل شامل للدم'),
('تحليل وظائف الكلى', 150, 'فحص وظائف الكلى'),
('تحليل وظائف الكبد', 150, 'فحص وظائف الكبد'),
('تحليل السكر', 50, 'قياس مستوى السكر في الدم'),
('تحليل الكوليسترول', 80, 'قياس مستوى الكوليسترول');

-- إضافة بعض الأدوية
INSERT INTO medicines (name, description, price, quantity, min_quantity, expiry_date) VALUES
('باراسيتامول', 'مسكن ألم وخافض حرارة', 20, 100, 20, '2025-12-31'),
('أموكسيسيلين', 'مضاد حيوي', 50, 80, 15, '2025-06-30'),
('فيتامين سي', 'مكمل غذائي', 30, 150, 30, '2026-03-31'),
('أسبرين', 'مسكن ألم ومضاد التهاب', 25, 120, 25, '2025-09-30');
