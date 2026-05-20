<?php
// เปิดการแสดงผล Error เพื่อไม่ให้หน้าขาว (ช่วยให้รู้ว่าผิดตรงไหน)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- ตั้งค่าฐานข้อมูลของคุณตรงนี้ ---
$servername = "localhost";
$username = "coopcet";       // ถ้าอัพขึ้นโฮสต์จริง ต้องเปลี่ยนตามที่โฮสต์ให้มา
$password = "coopcet2026";           // รหัสผ่านฐานข้อมูล (XAMPP ปกติจะว่างไว้)
$dbname = "coopcet";   // ⚠️ เปลี่ยนเป็นชื่อฐานข้อมูลของคุณ (เช่น coopcet)

// สร้างการเชื่อมต่อ
$conn = new mysqli($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("<h3 style='color:red;'>เชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error . "</h3><p>กรุณาตรวจสอบชื่อผู้ใช้ รหัสผ่าน และชื่อฐานข้อมูลในไฟล์ db.php</p>");
}

// ตั้งค่าภาษาไทย
$conn->set_charset("utf8mb4");
?>