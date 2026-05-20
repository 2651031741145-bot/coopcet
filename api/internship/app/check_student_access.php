<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

$student_id = $_GET['student_id'] ?? '';
$response = array('is_readonly' => true, 'message' => '');

if ($student_id) {
    // 1. ดึงปีการศึกษาจากรหัสนักศึกษา (เช่น 266... ตัดเอา 66 มาทำเป็น 2566)
    // หรือถ้าคุณมีคอลัมน์ academic_year ในตาราง users ให้ JOIN มาใช้จะแม่นยำกว่าครับ
    $student_year = "25" . substr($student_id, 1, 2); 

    // 2. เช็คว่ามีรอบการฝึกงานของปีนี้ที่สถานะเป็น 'open' หรือไม่
    $sql = "SELECT round_status FROM internship_rounds 
            WHERE academic_year = '$student_year' AND round_status = 'open' 
            LIMIT 1";

    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        // พบรอบที่เปิดอยู่สำหรับปีการศึกษานี้
        $response['is_readonly'] = false;
        $response['message'] = "อยู่ในช่วงรอบการฝึกงาน";
    } else {
        // ไม่พบรอบที่เปิด หรือรอบถูกปิดไปแล้ว
        $response['is_readonly'] = true;
        $response['message'] = "ไม่อยู่ในช่วงรอบการฝึกงาน หรือรอบถูกปิดแล้ว";
    }
}

echo json_encode($response);
$conn->close();
?>