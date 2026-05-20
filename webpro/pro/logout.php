<?php
session_start();
require_once 'db.php'; // ดึงไฟล์เชื่อมต่อฐานข้อมูล

// ถ้ามีการล็อกอินค้างอยู่ ให้เข้าไปเคลียร์ Token ในฐานข้อมูลก่อน
if (isset($_SESSION['users_name'])) {
    $username = $conn->real_escape_string($_SESSION['users_name']);
    
    // อัปเดต session_token ให้เป็น NULL
    $sql = "UPDATE users SET session_token = NULL WHERE users_name = '$username'";
    $conn->query($sql);
}

// ล้างค่า Session ในเบราว์เซอร์ทั้งหมด
session_unset();
session_destroy();

// ลบ Cookie ของ Session (เพื่อความสะอาดหมดจด)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// เด้งกลับไปหน้าแรก (index.php)
header("Location: index.php");
exit();
?>