
<?php
require_once '../../includes/auth.php';
checkRole(['Admin', 'Reception']);

require_once '../../config/db.php';

// التحقق من وجود معرف الزيارة
if(!isset($_GET['visit_id'])) {
    header('Location: dashboard.php');
    exit();
}

$visit_id = $_GET['visit_id'];

// الحصول على بيانات الزيارة
$visit = $conn->query("SELECT v.*, p.full_name as patient_name, p.medical_record_number, p.age, p.gender, p.phone, d.full_name as doctor_name 
                      FROM visits v 
                      JOIN patients p ON v.patient_id = p.id 
                      JOIN doctors d ON v.doctor_id = d.id 
                      WHERE v.id = $visit_id")->fetch();

// الحصول على بيانات الفاتورة
$invoice = $conn->query("SELECT i.*, ii.item_type, ii.item_id, ii.quantity, ii.unit_price, ii.total_price, s.name as service_name 
                        FROM invoices i 
                        LEFT JOIN invoice_items ii ON i.id = ii.invoice_id 
                        LEFT JOIN services s ON (ii.item_type = 'Service' AND ii.item_id = s.id) 
                        WHERE i.visit_id = $visit_id")->fetchAll();

// حساب الإجمالي
$total_amount = 0;
foreach($invoice as $item) {
    $total_amount += $item['total_price'];
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تذكرة الزيارة</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
        }

        .ticket-container {
            max-width: 400px;
            margin: 0 auto;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }

        .ticket-header {
            text-align: center;
            border-bottom: 2px dashed #ccc;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }

        .ticket-header h1 {
            font-size: 24px;
            color: #0d6efd;
            margin-bottom: 5px;
        }

        .ticket-header p {
            margin: 0;
            color: #666;
        }

        .ticket-body {
            margin-bottom: 15px;
        }

        .ticket-body h2 {
            font-size: 18px;
            text-align: center;
            margin-bottom: 15px;
            color: #333;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .info-label {
            font-weight: bold;
            color: #555;
        }

        .services-table {
            width: 100%;
            margin: 15px 0;
            border-collapse: collapse;
        }

        .services-table th,
        .services-table td {
            padding: 5px;
            text-align: right;
        }

        .services-table th {
            border-bottom: 1px solid #ddd;
            color: #555;
        }

        .total-row {
            font-weight: bold;
            border-top: 1px dashed #ccc;
            padding-top: 5px;
            margin-top: 10px;
            text-align: center;
            font-size: 16px;
        }

        .ticket-footer {
            text-align: center;
            border-top: 2px dashed #ccc;
            padding-top: 15px;
            color: #666;
            font-size: 14px;
        }

        .action-buttons {
            text-align: center;
            margin-top: 20px;
        }

        @media print {
            body {
                background-color: white;
                padding: 0;
            }

            .action-buttons {
                display: none;
            }

            .ticket-container {
                box-shadow: none;
                margin: 0;
                max-width: 100%;
            }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="ticket-container">
        <div class="ticket-header">
            <h1>مجمع نوف الطبي</h1>
            <p>Noof Medical Complex</p>
        </div>

        <div class="ticket-body">
            <h2>تذكرة زيارة</h2>

            <div class="info-row">
                <span class="info-label">اسم المريض:</span>
                <span><?php echo $visit['patient_name']; ?></span>
            </div>

            <div class="info-row">
                <span class="info-label">رقم الملف الطبي:</span>
                <span><?php echo $visit['medical_record_number']; ?></span>
            </div>

            <div class="info-row">
                <span class="info-label">العمر:</span>
                <span><?php echo $visit['age']; ?> سنة</span>
            </div>

            <div class="info-row">
                <span class="info-label">الجنس:</span>
                <span><?php echo $visit['gender']; ?></span>
            </div>

            <div class="info-row">
                <span class="info-label">الهاتف:</span>
                <span><?php echo $visit['phone']; ?></span>
            </div>

            <div class="info-row">
                <span class="info-label">الطبيب:</span>
                <span><?php echo $visit['doctor_name']; ?></span>
            </div>

            <div class="info-row">
                <span class="info-label">التاريخ:</span>
                <span><?php echo date('Y-m-d H:i', strtotime($visit['visit_date'])); ?></span>
            </div>

            <h3 class="mt-4 mb-3">الخدمات المطلوبة</h3>

            <table class="services-table">
                <thead>
                    <tr>
                        <th>الخدمة</th>
                        <th>السعر</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($invoice as $item): ?>
                    <tr>
                        <td>
                            <?php 
                            if($item['item_type'] == 'Service' && $item['item_id'] == 0) {
                                echo 'رسوم الكشف';
                            } else {
                                echo $item['service_name'];
                            }
                            ?>
                        </td>
                        <td><?php echo number_format($item['total_price'], 2); ?> ريال</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="total-row">
                الإجمالي: <?php echo number_format($total_amount, 2); ?> ريال
            </div>
        </div>

        <div class="ticket-footer">
            <p>شكراً لزيارتكم مجمع نوف الطبي</p>
            <p>نتمنى لكم دوام الصحة والعافية</p>
        </div>
    </div>

    <div class="action-buttons">
        <a href="dashboard.php" class="btn btn-primary">
            <i class="fas fa-arrow-right me-1"></i>
            العودة إلى لوحة التحكم
        </a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
