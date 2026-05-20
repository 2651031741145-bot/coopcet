<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
include 'db_connect.php';

error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $users_name = $_POST['users_name'];

    if (!empty($users_name)) {
        // อัปเดต app_session_token ให้กลับเป็น NULL
        $sql = "UPDATE users SET app_session_token = NULL WHERE users_name = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $users_name);
        
        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "เคลียร์ Session สำเร็จ"]);
        } else {
            echo json_encode(["success" => false, "message" => "เกิดข้อผิดพลาดในการอัปเดตฐานข้อมูล"]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "ไม่พบชื่อผู้ใช้งาน"]);
    }
}
?>