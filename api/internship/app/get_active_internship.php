<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

$student_id = $_GET['student_id'] ?? '';
$response = array();

if ($student_id) {
    // กลับมาใช้ SQL เดิมที่ทำงานได้ 100%
    $sql = "SELECT si.*, c.company_name, c.address, c.latitude, c.longitude 
            FROM student_internships si
            JOIN companies c ON si.company_id = c.company_id
            WHERE si.student_id = '$student_id' 
            AND si.status IN ('active', 'relocating', 'pending', 'finished') 
            ORDER BY si.internship_id DESC LIMIT 1";

    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $response['success'] = true;
        $response['data'] = $result->fetch_assoc();
    } else {
        $response['success'] = false;
        $response['message'] = "ยังไม่มีข้อมูลการฝึกงานที่ใช้งานอยู่";
    }
} else {
    $response['success'] = false;
    $response['message'] = "ระบุรหัสนักศึกษาไม่ถูกต้อง";
}

echo json_encode($response);
$conn->close();
?>