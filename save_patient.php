<?php
require "config/db.php";

$conn->beginTransaction();

$stmt = $conn->prepare("
INSERT INTO patients (full_name,phone,age,gender)
VALUES (?,?,?,?)
");
$stmt->execute([
$_POST['full_name'],
$_POST['phone'],
$_POST['age'],
$_POST['gender']
]);

$patient_id = $conn->lastInsertId();
$total = 0;

if(!empty($_POST['services'])){
  foreach($_POST['services'] as $sid){
    $s = $conn->query("SELECT price FROM services WHERE id=$sid")->fetch();
    $total += $s['price'];
  }
}

$conn->commit();

header("Location: pages/ticket.php?id=$patient_id&total=$total");
