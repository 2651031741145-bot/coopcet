<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // รับค่า users_name (Primary Key) ที่ต้องการลบ
    $users_name = trim($_POST['users_name']);

    if (empty($users_name)) {
        echo json_encode(["success" => false, "message" => "ไม่พบรหัสผู้ใช้ที่ต้องการลบ"]);
        exit();
    }

    // เตรียมคำสั่ง SQL สำหรับลบผู้ใช้
    $sql = "DELETE FROM users WHERE users_name = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $users_name);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "ลบผู้ใช้งานระบบสำเร็จ"]);
    } else {
        // ดักจับ Error กรณีลบไม่ได้เพราะอาจารย์หรือนักศึกษามีข้อมูลประเมิน/ฝึกงานค้างอยู่ในตารางอื่น (Foreign Key)
        if ($conn->errno == 1451) {
             echo json_encode(["success" => false, "message" => "ไม่สามารถลบได้ เนื่องจากผู้ใช้นี้มีข้อมูลประวัติการฝึกงานหรือการประเมินผูกอยู่"]);
        } else {
             echo json_encode(["success" => false, "message" => "ไม่สามารถลบข้อมูลได้: " . $conn->error]);
        }
    }
}
?>