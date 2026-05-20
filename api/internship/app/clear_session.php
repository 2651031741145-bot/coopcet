<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $target = $_POST['target']; // 'all' หรือ 'single'
    $users_name = $_POST['users_name'] ?? '';

    if ($target == 'all') {
        // ล้าง Session ของทุกคนในระบบ
        $sql = "UPDATE users SET app_session_token = NULL";
        $stmt = $conn->prepare($sql);
    } else {
        // ล้างเฉพาะ ID ที่ระบุ
        $sql = "UPDATE users SET app_session_token = NULL WHERE users_name = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $users_name);
    }

    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false]);
    }
}
?>