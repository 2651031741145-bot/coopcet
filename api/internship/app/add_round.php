<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

$response = array();

if (isset($_POST['academic_year']) && isset($_POST['start_date']) && isset($_POST['end_date'])) {
    
    $year = $conn->real_escape_string($_POST['academic_year']);
    $start = $conn->real_escape_string($_POST['start_date']);
    $end = $conn->real_escape_string($_POST['end_date']);
    $created_by = isset($_POST['created_by']) ? $conn->real_escape_string($_POST['created_by']) : 'system';

    // บันทึกข้อมูล โดยกำหนด round_status ให้เป็น 'open' โดยอัตโนมัติ
    $sql = "INSERT INTO internship_rounds (academic_year, start_date, end_date, created_by, round_status) 
            VALUES ('$year', '$start', '$end', '$created_by', 'open')";

    if ($conn->query($sql)) {
        $response['success'] = true;
        $response['message'] = "เพิ่มรอบการฝึกงานปี $year เรียบร้อยแล้ว";
    } else {
        $response['success'] = false;
        $response['message'] = "เกิดข้อผิดพลาด: " . $conn->error;
    }
} else {
    $response['success'] = false;
    $response['message'] = "ข้อมูลไม่ครบถ้วน";
}

echo json_encode($response);
$conn->close();
?>