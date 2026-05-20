<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
include 'db_connect.php';

// ดึงเฉพาะคนที่มี app_session_token (ไม่เป็น NULL และไม่ว่าง)
$sql = "SELECT users_name, full_name, user_level FROM users WHERE app_session_token IS NOT NULL AND app_session_token != ''";
$result = $conn->query($sql);

$sessions = array();
while($row = $result->fetch_assoc()) {
    $sessions[] = $row;
}
echo json_encode($sessions);
?>