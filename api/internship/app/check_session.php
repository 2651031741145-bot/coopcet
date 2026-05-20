<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

if (isset($_POST['users_name']) && isset($_POST['token'])) {
    $users_name = $conn->real_escape_string($_POST['users_name']);
    $token = $conn->real_escape_string($_POST['token']);

    // ดึง token ของผู้ใช้จากฐานข้อมูลมาเทียบ
    // (ดูจากโครงสร้างตาราง users ของคุณ มีคอลัมน์ app_session_token อยู่)
    $sql = "SELECT app_session_token FROM users WHERE users_name = '$users_name'";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        // ถ้า Token ตรงกันแปลว่ายังไม่ได้ถูกลบ หรือเข้าสู่ระบบซ้อน
        if ($row['app_session_token'] === $token) {
            echo json_encode(['status' => 'active']);
        } else {
            echo json_encode(['status' => 'invalid']); // ถูกเตะออก
        }
    } else {
        echo json_encode(['status' => 'invalid']); // ไม่พบผู้ใช้
    }
} else {
    echo json_encode(['status' => 'invalid']); // ส่งข้อมูลมาไม่ครบ
}
$conn->close();
?>