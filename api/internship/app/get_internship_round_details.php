<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

$student_id = $_GET['student_id'] ?? '';
// 🚨 รับค่าปีการศึกษาที่ส่งมาจาก Flutter
$academic_year = $_GET['academic_year'] ?? ''; 

$response = array();

if ($student_id && $academic_year) {
    // 🚨 แก้ไขการ JOIN: 
    // เปลี่ยนจาก ON si.round_id = ir.round_id 
    // เป็นการบังคับดึงรอบที่ตรงกับปีการศึกษาของเด็ก (ON ir.academic_year = '$academic_year')
    // เพื่อแก้ปัญหาเวลาใน DB บันทึก round_id ผิดปีครับ
    $sql = "SELECT 
                si.internship_id, 
                si.custom_start_date, 
                si.custom_end_date, 
                ir.academic_year, 
                ir.start_date, 
                ir.end_date
            FROM student_internships si
            JOIN internship_rounds ir ON ir.academic_year = '$academic_year'
            WHERE si.student_id = '$student_id' AND si.status = 'active'
            ORDER BY si.internship_id DESC LIMIT 1";

    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $response['success'] = true;
        $response['data'] = $result->fetch_assoc();
    } else {
        $response['success'] = false;
        $response['message'] = "ไม่พบข้อมูลรอบการฝึกงานของปีการศึกษา $academic_year";
    }
} else {
    $response['success'] = false;
    $response['message'] = "ข้อมูลไม่ครบถ้วน (ต้องการ student_id และ academic_year)";
}

echo json_encode($response);
$conn->close();
?>