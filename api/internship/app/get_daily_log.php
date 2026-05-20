<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

$internship_id = $_GET['internship_id'] ?? '';
$log_date = $_GET['log_date'] ?? '';
$response = array('success' => false, 'data' => null);

if ($internship_id && $log_date) {
    $sql = "SELECT * FROM daily_logs WHERE internship_id = '$internship_id' AND log_date = '$log_date' LIMIT 1";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $response['success'] = true;
        $response['data'] = $result->fetch_assoc();
    } else {
        $response['success'] = true; 
        $response['message'] = "ยังไม่มีข้อมูลของวันนี้";
    }
} else {
    $response['message'] = "ข้อมูลไม่ครบถ้วน";
}

echo json_encode($response);
$conn->close();
?>