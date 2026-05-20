<?php
// 1. ส่วนจัดการ CORS (เพื่อให้รันบน Chrome/Web ได้โดยไม่ติด Permission)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

// กรณี Browser ส่งการตรวจสอบ (Preflight Request) มาก่อน ให้ตอบกลับ OK ทันที
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 2. ข้อมูลการเชื่อมต่อฐานข้อมูล
$host = "localhost"; 
$user = "coopcet"; 
$password = "coopcet2026"; 
$database = "coopcet"; 

// สร้างการเชื่อมต่อ
$conn = new mysqli($host, $user, $password, $database);

// 3. ตั้งค่าภาษาไทย (สำคัญมาก!)
$conn->set_charset("utf8mb4");

// 4. ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    // ส่ง Error กลับเป็น JSON เพื่อให้ Flutter แจ้งเตือนได้ถูก
    die(json_encode([
        "success" => false, 
        "message" => "Database connection failed: " . $conn->connect_error
    ]));
}
?>