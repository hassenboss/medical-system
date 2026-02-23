<?php
// includes/functions.php

/**
 * توليد رقم ملف طبي فريد وغير مكرر
 */
function generateUniqueMedicalRecord($conn) {
    $year = date('Y');
    $maxAttempts = 100;
    $attempt = 0;
    
    do {
        // الحصول على آخر رقم مستخدم لهذا العام
        $stmt = $conn->prepare("
            SELECT medical_record_number 
            FROM patients 
            WHERE medical_record_number LIKE ? 
            ORDER BY id DESC 
            LIMIT 1
        ");
        $stmt->execute(["MR-$year-%"]);
        $last = $stmt->fetch();
        
        if ($last) {
            // استخراج الرقم من آخر سجل: MR-2026-00045 -> 45
            preg_match('/MR-\d{4}-(\d{5})/', $last['medical_record_number'], $matches);
            $nextNumber = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
        } else {
            $nextNumber = 1;
        }
        
        $newNumber = 'MR-' . $year . '-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
        $attempt++;
        
        // التحقق من عدم وجود هذا الرقم مسبقاً
        $checkStmt = $conn->prepare("SELECT id FROM patients WHERE medical_record_number = ?");
        $checkStmt->execute([$newNumber]);
        
    } while ($checkStmt->fetch() && $attempt < $maxAttempts);
    
    if ($attempt >= $maxAttempts) {
        throw new Exception("فشل في توليد رقم ملف فريد بعد عدة محاولات");
    }
    
    return $newNumber;
}
?>