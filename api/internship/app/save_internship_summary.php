<?php
// ปิดการโชว์ Error ตรงๆ เพื่อให้แอปรับ JSON ได้เสมอ
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

$response = array();
$upload_dir = 'uploads/';

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if (isset($_POST['internship_id']) && isset($_POST['student_id'])) {
    
    $internship_id = $conn->real_escape_string($_POST['internship_id']);
    $student_id = $conn->real_escape_string($_POST['student_id']);
    
    // ใส่ ?? '' เผื่อกรณีแอปส่งค่ามาไม่ครบ จะได้ไม่เกิด Error
    $internship_year = $conn->real_escape_string($_POST['internship_year'] ?? '');
    $has_benefits = $conn->real_escape_string($_POST['has_benefits'] ?? 'no');
    $benefit_details = $conn->real_escape_string($_POST['benefit_details'] ?? '');
    $position = $conn->real_escape_string($_POST['position'] ?? '');
    $student_count = (int)($_POST['student_count'] ?? 0);
    $can_publish = $conn->real_escape_string($_POST['can_publish'] ?? 'no');
    $supervisor_name = $conn->real_escape_string($_POST['supervisor_name'] ?? '');
    
    $file_path = "";
    $file_sql_update = "";
    
    // จัดการอัปโหลดไฟล์ PDF
    if (isset($_FILES['project_file']) && $_FILES['project_file']['error'] == 0) {
        $file_ext = pathinfo($_FILES['project_file']['name'], PATHINFO_EXTENSION);
        $new_file_name = "project_" . $student_id . "_" . time() . "." . $file_ext;
        $target_file = $upload_dir . $new_file_name;
        
        if (move_uploaded_file($_FILES['project_file']['tmp_name'], $target_file)) {
            $file_path = $target_file;
            $file_sql_update = ", project_file_path = VALUES(project_file_path)"; 
        } else {
            $response['success'] = false;
            $response['message'] = "อัปโหลดไฟล์ PDF ไม่สำเร็จ";
            echo json_encode($response);
            exit();
        }
    }

    // เริ่ม Transaction
    $conn->begin_transaction();

    try {
        // 1. บันทึกหรืออัปเดตข้อมูลสรุป
        $sql_summary = "INSERT INTO internship_summaries 
                (internship_id, student_id, internship_year, has_benefits, benefit_details, position, student_count, can_publish, supervisor_name, project_file_path) 
                VALUES 
                ('$internship_id', '$student_id', '$internship_year', '$has_benefits', '$benefit_details', '$position', '$student_count', '$can_publish', '$supervisor_name', '$file_path')
                ON DUPLICATE KEY UPDATE 
                internship_year = VALUES(internship_year),
                has_benefits = VALUES(has_benefits),
                benefit_details = VALUES(benefit_details),
                position = VALUES(position),
                student_count = VALUES(student_count),
                can_publish = VALUES(can_publish),
                supervisor_name = VALUES(supervisor_name)
                $file_sql_update";
        
        // 🚨 จุดที่แก้: เช็คว่าคำสั่ง SQL สำเร็จไหม ถ้าพังให้ชิ่งไป Catch ทันที
        if (!$conn->query($sql_summary)) {
            throw new Exception("ไม่สามารถบันทึกข้อมูลสรุปได้: " . $conn->error);
        }

        // 2. อัปเดตสถานะเป็น pending
        $sql_status = "UPDATE student_internships SET status = 'pending' WHERE internship_id = '$internship_id'";
        
        // 🚨 จุดที่แก้: เช็คว่าคำสั่ง SQL สำเร็จไหม
        if (!$conn->query($sql_status)) {
            throw new Exception("ไม่สามารถอัปเดตสถานะได้: " . $conn->error);
        }

        // ถ้ายืนยันสำเร็จทั้งคู่ ค่อย Commit ยืนยันข้อมูลลงฐาน
        $conn->commit();

        $response['success'] = true;
        $response['message'] = "ส่งสรุปผลการฝึกงานเรียบร้อยแล้ว (รออาจารย์อนุมัติ)";

    } catch (Exception $e) {
        // ถ้ามีข้อผิดพลาดระบบจะ Rollback ไม่ให้สถานะเปลี่ยนไปเป็น pending มั่วๆ 
        $conn->rollback();
        $response['success'] = false;
        // ส่งข้อความ Error กลับไปให้แอป เพื่อให้เรารู้ว่าพังเพราะอะไร
        $response['message'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }

} else {
    $response['success'] = false;
    $response['message'] = "ข้อมูลไม่ครบถ้วน";
}

echo json_encode($response);
$conn->close();
?>