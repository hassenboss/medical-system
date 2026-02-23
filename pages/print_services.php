<?php
require "../config/db.php";

$patient = $conn->query("
SELECT * FROM patients WHERE id=".$_POST['patient_id']
)->fetch();

$services = [];
if(!empty($_POST['services'])){
  $ids = implode(",", $_POST['services']);
  $services = $conn->query("
  SELECT * FROM services WHERE id IN ($ids)
  ")->fetchAll();
}

$total = $_POST['total'];
?>
<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<link rel="stylesheet" href="../assets/css/style.css">
<title>تذكرة فحوصات</title>
</head>

<body onload="window.print()">

<div class="ticket2050">

<div class="ticket-header">
<h1>مجمع نوف الطبي</h1>
<span>Noof Medical Complex</span>
</div>

<div class="ticket-body">
<h2>🧾 تذكرة فحوصات</h2>

<p><strong>المريض:</strong> <?= $patient['full_name'] ?></p>
<p><strong>التاريخ:</strong> <?= date('Y-m-d H:i') ?></p>

<hr>

<?php foreach($services as $s): ?>
<p>✔ <?= $s['name'] ?> — <?= $s['price'] ?> SDG</p>
<?php endforeach; ?>

<hr>

<h3>💰 الإجمالي: <?= $total ?> SDG</h3>
</div>

<div class="ticket-footer">
<p>نتمنى لكم دوام الصحة</p>
<p>مجمع نوف الطبي</p>
</div>

</div>

</body>
</html>
