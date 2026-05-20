<?php
// เปิดแสดง Error เพื่อให้ตรวจสอบง่ายขึ้น
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

$response = array();

// ตรวจสอบว่าแอปส่งค่าที่จำเป็นมาครบหรือไม่
if (isset($_POST['internship_id']) && isset($_POST['status'])) {
    
    $internship_id = $conn->real_escape_string($_POST['internship_id']);
    $status = $conn->real_escape_string($_POST['status']); 
    
    // รับค่าวันที่ ถ้าไม่ได้ส่งมาหรือเป็นค่าว่าง ให้กำหนดเป็นสตริงว่าง
    $custom_start = isset($_POST['custom_start_date']) ? $conn->real_escape_string($_POST['custom_start_date']) : '';
    $custom_end = isset($_POST['custom_end_date']) ? $conn->real_escape_string($_POST['custom_end_date']) : '';
    $remark = isset($_POST['remark']) ? $conn->real_escape_string($_POST['remark']) : '';

    // สร้างคำสั่ง SQL ตามสถานะการอนุมัติ
    if ($status === 'relocated') {
        // กรณีอนุมัติย้าย: ให้อัปเดตสถานะ และกำหนดวันที่เริ่ม/จบใหม่
        // ใช้ NULLIF เพื่อบอกฐานข้อมูลว่า ถ้าค่าเป็น '' (ว่าง) ให้บันทึกเป็น NULL
        $sql = "UPDATE student_internships 
                SET status = '$status', 
                    custom_start_date = NULLIF('$custom_start', ''), 
                    custom_end_date = NULLIF('$custom_end', '')
                WHERE internship_id = '$internship_id'";
    } else {
        // กรณีไม่อนุมัติ (ตีกลับเป็น active หรืออื่นๆ): อัปเดตแค่สถานะ
        $sql = "UPDATE student_internships 
                SET status = '$status' 
                WHERE internship_id = '$internship_id'";
    }

    if ($conn->query($sql)) {
        // เช็คว่า query สำเร็จและไม่มี error (บางครั้ง affected_rows อาจเป็น 0 ถ้าข้อมูลเหมือนเดิมเป๊ะ)
        $response['success'] = true;
        $response['message'] = "บันทึกการอนุมัติและอัปเดตวันที่สำเร็จ";
    } else {
        $response['success'] = false;
        $response['message'] = "เกิดข้อผิดพลาดในการบันทึก: " . $conn->error;
    }
} else {
    $response['success'] = false;
    $response['message'] = "ส่งข้อมูลไม่ครบถ้วน (ต้องการ internship_id และ status)";
}

echo json_encode($response);
$conn->close();
?>