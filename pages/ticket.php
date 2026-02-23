<?php
require "../config/db.php";
$p = $conn->query("SELECT * FROM patients WHERE id=".$_GET['id'])->fetch();
?>
<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<link rel="stylesheet" href="../assets/css/style.css">
<title>تذكرة معاينة</title>
</head>

<body onload="window.print()">

<div class="ticket2050">

<div class="ticket-header">
  <h1>مجمع نوف الطبي</h1>
  <span>Noof Medical Complex</span>
</div>

<div class="ticket-body">
  <h2>🎫 تذكرة معاينة مجانية</h2>

  <div class="row">
    <div>
      <small>اسم المريض</small>
      <p><?= $p['full_name'] ?></p>
    </div>
    <div>
      <small>العمر</small>
      <p><?= $p['age'] ?> سنة</p>
    </div>
  </div>

  <div class="row">
    <div>
      <small>رقم الهاتف</small>
      <p><?= $p['phone'] ?></p>
    </div>
    <div>
      <small>التاريخ</small>
      <p><?= date('Y-m-d') ?></p>
    </div>
  </div>

  <div class="free-box">
    ✅ المعاينة مجانية
  </div>

</div>

<div class="ticket-footer">
  <p>هذه التذكرة صالحة لمقابلة الطبيب فقط</p>
  <p>نشكركم لاختياركم مجمع نوف الطبي</p>
</div>

</div>

</body>
</html>
