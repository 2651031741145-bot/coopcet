<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

$response = array();

// ดึงเฉพาะ user_level = 't' (Teacher/อาจารย์)
$sql = "SELECT users_name, full_name FROM users WHERE user_level = 't' ORDER BY full_name ASC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $teachers = array();
    while($row = $result->fetch_assoc()) {
        $teachers[] = $row;
    }
    $response['success'] = true;
    $response['data'] = $teachers;
} else {
    $response['success'] = false;
    $response['message'] = "ไม่พบข้อมูลอาจารย์ในระบบ";
}

echo json_encode($response);
$conn->close();
?>