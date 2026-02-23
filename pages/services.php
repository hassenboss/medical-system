<?php
require "../config/db.php";

if(isset($_POST['name'])){
  $stmt = $conn->prepare("INSERT INTO services (name,price,type) VALUES (?,?,?)");
  $stmt->execute([$_POST['name'],$_POST['price'],$_POST['type']]);
}

$services = $conn->query("SELECT * FROM services")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<link rel="stylesheet" href="../assets/css/style.css">
<title>الخدمات الطبية</title>
</head>
<body>

<div class="card">
<h2>🧾 إضافة خدمة</h2>
<form method="POST">
<input name="name" placeholder="اسم الخدمة" required>
<input name="price" placeholder="السعر" required>
<select name="type">
  <option value="فحص">فحص</option>
  <option value="تمريض">تمريض</option>
</select>
<button>حفظ</button>
</form>

<hr>

<h3>📋 الخدمات الحالية</h3>
<?php foreach($services as $s): ?>
<p>✔ <?= $s['name'] ?> - <?= $s['price'] ?> SDG (<?= $s['type'] ?>)</p>
<?php endforeach; ?>

</div>
</body>
</html>
