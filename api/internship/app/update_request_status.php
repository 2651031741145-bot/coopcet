<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php'; // ใช้ไฟล์เชื่อมต่อของคุณ

$response = array();

// ตรวจสอบว่ามีการส่งค่า request_id และ status มาหรือไม่
if (isset($_POST['request_id']) && isset($_POST['status'])) {
    
    $request_id = $conn->real_escape_string($_POST['request_id']); // คือ internship_id ในตาราง
    $action = $_POST['status']; // ค่าที่ส่งมาจากแอป (เช่น 'approved' หรือ 'rejected')
    $remark = isset($_POST['remark']) ? $conn->real_escape_string($_POST['remark']) : '';

    // กำหนดสถานะที่จะบันทึกลงฐานข้อมูลตาม Enum ใหม่
    $new_status = '';
    if ($action == 'approved') {
        $new_status = 'relocated'; // ถ้าอนุมัติ ให้เปลี่ยนเป็น relocated
    } else {
        // หากอาจารย์ไม่อนุมัติการย้าย ให้ปรับกลับเป็น active (อยู่ที่เดิม) 
        // เนื่องจากเราตัดสถานะ rejected ออกจาก Enum แล้ว
        $new_status = 'active'; 
    }

    // อัปเดตข้อมูลในตาราง student_internships
    $sql = "UPDATE student_internships 
            SET status = '$new_status', 
                relocate_reason = IF('$remark' != '', '$remark', relocate_reason)
            WHERE internship_id = '$request_id'";

    if ($conn->query($sql)) {
        $response['success'] = true;
        $response['message'] = "ดำเนินการเรียบร้อยแล้ว สถานะปัจจุบัน: $new_status";
    } else {
        $response['success'] = false;
        $response['message'] = "เกิดข้อผิดพลาดในการอัปเดต: " . $conn->error;
    }
} else {
    $response['success'] = false;
    $response['message'] = "ข้อมูลไม่ครบถ้วน (ต้องการ request_id และ status)";
}

echo json_encode($response);
$conn->close();
?>