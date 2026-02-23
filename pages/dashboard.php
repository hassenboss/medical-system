<?php
require "../config/db.php";
$patients = $conn->query("SELECT * FROM patients ORDER BY id DESC")->fetchAll();
$services = $conn->query("SELECT * FROM services")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<link rel="stylesheet" href="../assets/css/style.css">
<title>Nova Medical – Dashboard</title>

<script>
function filterPatients(){
 let q = document.getElementById('q').value.toLowerCase();
 document.querySelectorAll('.patient-tile').forEach(p=>{
  p.style.display = p.dataset.name.includes(q) ? 'flex':'none';
 });
}

function selectPatient(id,name){
 document.getElementById('pid').value=id;
 document.getElementById('pname').innerText=name;
}

function totalCalc(){
 let t=0;
 document.querySelectorAll('.srv:checked').forEach(s=>t+=parseFloat(s.dataset.p));
 document.getElementById('sum').innerText=t+" SDG";
 document.getElementById('total').value=t;
}
</script>
</head>

<body>

<!-- Top Bar -->
<header class="top-glass">
  <div class="brand">
    <span>🧬</span>
    <strong>Noof Medical</strong>
  </div>
  <input id="q" onkeyup="filterPatients()" placeholder="Search patient..." />
</header>

<div class="app-shell">

<!-- Patients Rail -->
<aside class="rail">
<?php foreach($patients as $p): ?>
<div class="patient-tile"
data-name="<?= strtolower($p['full_name']) ?>"
onclick="selectPatient('<?= $p['id'] ?>','<?= $p['full_name'] ?>')">
<div class="avatar"><?= mb_substr($p['full_name'],0,1) ?></div>
<div>
<strong><?= $p['full_name'] ?></strong>
<small>ID #<?= $p['id'] ?></small>
</div>
</div>
<?php endforeach; ?>
</aside>

<!-- Workspace -->
<main class="workspace">
<h1 id="pname">Select Patient</h1>

<form action="print_services.php" method="POST">
<input type="hidden" name="patient_id" id="pid">
<input type="hidden" name="total" id="total">

<section class="services-modern">
<?php foreach($services as $s): ?>
<label class="service-glass">
<input type="checkbox"
class="srv"
name="services[]"
value="<?= $s['id'] ?>"
data-p="<?= $s['price'] ?>"
onchange="totalCalc()">
<div>
<strong><?= $s['name'] ?></strong>
<small><?= $s['type'] ?></small>
</div>
<span><?= $s['price'] ?> SDG</span>
</label>
<?php endforeach; ?>
</section>

<div class="footer-bar">
<div class="sum">Total: <span id="sum">0 SDG</span></div>
<button class="print-modern">Print Ticket</button>
</div>

</form>
</main>

</div>
</body>
</html>
