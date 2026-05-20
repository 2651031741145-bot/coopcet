<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $company_name = trim($_POST['company_name']);
    $address = trim($_POST['address']);
    $latitude = trim($_POST['latitude']);
    $longitude = trim($_POST['longitude']);

    if (empty($company_name) || empty($address)) {
        echo json_encode(["success" => false, "message" => "กรุณากรอกชื่อและที่อยู่สถานประกอบการ"]);
        exit();
    }

    $sql = "INSERT INTO companies (company_name, address, latitude, longitude) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $company_name, $address, $latitude, $longitude);

    if ($stmt->execute()) {
        // คืนค่า ID ของบริษัทที่เพิ่งเพิ่มเข้าไปด้วย เพื่อให้แอปเอาไปใช้ต่อได้เลย
        echo json_encode([
            "success" => true, 
            "message" => "เพิ่มสถานประกอบการสำเร็จ",
            "company_id" => $conn->insert_id
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "เกิดข้อผิดพลาดในการบันทึกข้อมูล"]);
    }
}
?>