<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

$response = array();

if (isset($_POST['round_id']) && isset($_POST['status'])) {
    $round_id = $conn->real_escape_string($_POST['round_id']);
    $status = $conn->real_escape_string($_POST['status']); // 'open' หรือ 'closed'

    $sql = "UPDATE internship_rounds SET round_status = '$status' WHERE round_id = '$round_id'";

    if ($conn->query($sql)) {
        $response['success'] = true;
        $response['message'] = "อัปเดตสถานะรอบการฝึกงานเรียบร้อยแล้ว";
    } else {
        $response['success'] = false;
        $response['message'] = "Error: " . $conn->error;
    }
} else {
    $response['success'] = false;
    $response['message'] = "ข้อมูลไม่ครบถ้วน";
}

echo json_encode($response);
$conn->close();
?>