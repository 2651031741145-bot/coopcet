<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

$response = array();

// ตรวจสอบว่าส่งค่ามาครบหรือไม่
if (isset($_POST['internship_id']) && isset($_POST['custom_start_date']) && isset($_POST['custom_end_date'])) {
    
    $internship_id = $conn->real_escape_string($_POST['internship_id']);
    $start_date = $conn->real_escape_string($_POST['custom_start_date']);
    $end_date = $conn->real_escape_string($_POST['custom_end_date']);

    // อัปเดตวันที่ใหม่ลงในตาราง
    $sql = "UPDATE student_internships 
            SET custom_start_date = '$start_date', custom_end_date = '$end_date' 
            WHERE internship_id = '$internship_id'";

    if ($conn->query($sql)) {
        $response['success'] = true;
        $response['message'] = "อัปเดตกำหนดการฝึกงานใหม่เรียบร้อยแล้ว";
    } else {
        $response['success'] = false;
        $response['message'] = "เกิดข้อผิดพลาดในการอัปเดต: " . $conn->error;
    }
} else {
    $response['success'] = false;
    $response['message'] = "ข้อมูลไม่ครบถ้วน";
}

echo json_encode($response);
$conn->close();
?>