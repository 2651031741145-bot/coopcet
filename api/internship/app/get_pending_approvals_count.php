<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

$response = array();

// นับจำนวนนักศึกษาที่ส่งสรุปแล้ว (สถานะเป็น pending)
$sql = "SELECT COUNT(*) as total FROM student_internships WHERE status = 'pending'";
$result = $conn->query($sql);

if ($result) {
    $row = $result->fetch_assoc();
    $response['success'] = true;
    $response['count'] = (int)$row['total'];
} else {
    $response['success'] = false;
    $response['count'] = 0;
}

echo json_encode($response);
$conn->close();
?>