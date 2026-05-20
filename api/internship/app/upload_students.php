<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
include 'db_connect.php'; // เปลี่ยนชื่อไฟล์เชื่อมฐานข้อมูลให้ตรงกับของคุณ

// เช็คว่ามีการส่งไฟล์มาหรือไม่
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    
    $file = $_FILES['csv_file']['tmp_name'];
    
    // เปิดไฟล์ CSV
    if (($handle = fopen($file, "r")) !== FALSE) {
        
        // ข้ามบรรทัดแรก (Header: รหัสนักศึกษา, ชื่อ-สกุล)
        fgetcsv($handle, 1000, ","); 
        
        $successCount = 0;
        $errorCount = 0;

        // วนลูปอ่านข้อมูลทีละบรรทัด
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            
            $student_id = trim($data[0]); // คอลัมน์ A: รหัสนักศึกษา
            $full_name = trim($data[1]);  // คอลัมน์ B: ชื่อ-สกุล (รวมคำนำหน้ามาแล้วจาก CSV)
            
            // ข้ามถ้าบรรทัดนั้นรหัสว่าง
            if (empty($student_id)) continue; 

            // 1. คำนวณปีการศึกษาจากรหัส
            // เช่น รหัส "265..." เอาตัวที่ 2 และ 3 จะได้ "65" นำหน้าด้วย "25" -> "2565"
            $year_suffix = substr($student_id, 1, 2); 
            $academic_year = "25" . $year_suffix;

            // 2. ตั้งรหัสผ่านเป็นรหัสนักศึกษา และเข้ารหัสด้วย password_hash
            $hashed_password = password_hash($student_id, PASSWORD_DEFAULT);
            
            // 3. กำหนดสิทธิ์เป็นนักศึกษา ('u')
            $user_level = 'u';

            // 4. บันทึกลงฐานข้อมูล (ถ้ามีรหัสซ้ำ ให้อัปเดตข้อมูลแทน)
            $sql = "INSERT INTO users (users_name, full_name, academic_year, password, user_level) 
                    VALUES (?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                    full_name = ?, academic_year = ?, password = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssss", 
                $student_id, $full_name, $academic_year, $hashed_password, $user_level, 
                $full_name, $academic_year, $hashed_password
            );

            if ($stmt->execute()) {
                $successCount++;
            } else {
                $errorCount++;
            }
        }
        fclose($handle);
        
        echo json_encode([
            "success" => true, 
            "message" => "นำเข้าข้อมูลสำเร็จ $successCount รายการ, ผิดพลาด $errorCount รายการ"
        ]);
        
    } else {
        echo json_encode(["success" => false, "message" => "ไม่สามารถอ่านไฟล์ CSV ได้"]);
    }
} else {
    echo json_encode(["success" => false, "message" => "ไม่มีไฟล์ที่ส่งมา"]);
}
?>