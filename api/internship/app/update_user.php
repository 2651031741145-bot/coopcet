<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $users_name = trim($_POST['users_name']);
    $full_name  = trim($_POST['full_name']);
    $user_level = trim($_POST['user_level']);
    $password   = trim($_POST['password']); // รับค่ารหัสผ่านใหม่

    if (empty($users_name) || empty($full_name)) {
        echo json_encode(["success" => false, "message" => "ข้อมูลไม่ครบถ้วน"]);
        exit();
    }

    // กรณีมีการกรอกรหัสผ่านใหม่มาด้วย
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET full_name = ?, user_level = ?, password = ? WHERE users_name = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $full_name, $user_level, $hashed_password, $users_name);
    } else {
        // กรณีไม่ต้องการเปลี่ยนรหัสผ่าน (อัปเดตเฉพาะชื่อและระดับ)
        $sql = "UPDATE users SET full_name = ?, user_level = ? WHERE users_name = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $full_name, $user_level, $users_name);
    }

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "อัปเดตข้อมูลสำเร็จ"]);
    } else {
        echo json_encode(["success" => false, "message" => "ไม่สามารถอัปเดตข้อมูลได้"]);
    }
}
?>