<?php
require "../config/db.php";
$id = $_GET['id'];

$p = $conn->query("SELECT * FROM patients WHERE id=$id")->fetch();
?>
<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body onload="window.print()">

<div class="ticket">
<h3>🎟️ تذكرة دخول طبيب</h3>
<p>الاسم: <?= $p['full_name'] ?></p>
<p>العمر: <?= $p['age'] ?></p>
<p>الرقم: <?= $p['phone'] ?></p>
<p>التاريخ: <?= date('Y-m-d') ?></p>
</div>

</body>
</html>
