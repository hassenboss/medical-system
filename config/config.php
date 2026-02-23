// config/db.php
<?php
$conn = new PDO("mysql:host=localhost;dbname=clinic_system","root","");
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
?>
