<?php
require "../config/db.php";
$services = $conn->query("SELECT * FROM services")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<link rel="stylesheet" href="../assets/css/style.css">
<title>تسجيل مريض</title>
</head>
<body>

<div class="card">
<h2>🩺 تسجيل مريض</h2>

<form action="../save_patient.php" method="POST">
<input name="full_name" placeholder="الاسم الكامل" required>
<input name="phone" placeholder="رقم الهاتف">
<input name="age" placeholder="العمر">
<select name="gender">
<option>ذكر</option>
<option>أنثى</option>
</select>


<button>حفظ وطباعة التذكرة</button>
</form>

</div>
</body>
</html>
