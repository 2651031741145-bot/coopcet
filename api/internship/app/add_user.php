<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $users_name = trim($_POST['users_name']);
    $full_name  = trim($_POST['full_name']);
    $user_level = trim($_POST['user_level']);
    
    // ตรวจสอบข้อมูลว่าง
    if (empty($users_name) || empty($full_name)) {
        echo json_encode(["success" => false, "message" => "กรุณากรอกข้อมูลให้ครบถ้วน"]);
        exit();
    }

    // ตรวจสอบชื่อผู้ใช้ซ้ำ
    $checkSql = "SELECT users_name FROM users WHERE users_name = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("s", $users_name);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        echo json_encode(["success" => false, "message" => "ชื่อผู้ใช้งานนี้มีอยู่ในระบบแล้ว"]);
        exit();
    }

    // Hash รหัสผ่าน (ใช้ชื่อผู้ใช้งานเป็นรหัสผ่านเริ่มต้น)
    $hashed_password = password_hash($users_name, PASSWORD_DEFAULT);
    $academic_year = ""; // สำหรับอาจารย์และแอดมินไม่ต้องมีปีการศึกษา

    $sql = "INSERT INTO users (users_name, full_name, academic_year, password, user_level) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $users_name, $full_name, $academic_year, $hashed_password, $user_level);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "เพิ่มผู้ใช้งานเรียบร้อยแล้ว"]);
    } else {
        echo json_encode(["success" => false, "message" => "เกิดข้อผิดพลาดในการบันทึกข้อมูล"]);
    }
}
?>