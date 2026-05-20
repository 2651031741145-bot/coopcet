<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

$internship_id = $_GET['internship_id'] ?? '';
$response = array();

if ($internship_id) {
    // ดึงบันทึกงานรายวันเรียงตามวันที่เก่าไปใหม่
    $sql = "SELECT * FROM daily_logs WHERE internship_id = '$internship_id' ORDER BY log_date ASC";
    $result = $conn->query($sql);
    
    if ($result) {
        $logs = array();
        while($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        $response['success'] = true;
        $response['data'] = $logs;
    } else {
        $response['success'] = false;
        $response['message'] = "เกิดข้อผิดพลาด: " . $conn->error;
    }
} else {
    $response['success'] = false;
    $response['message'] = "ไม่ได้ระบุ ID การฝึกงาน";
}

echo json_encode($response);
$conn->close();
?>