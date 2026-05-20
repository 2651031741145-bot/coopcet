<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
include 'db_connect.php';

// ดึงข้อมูลเรียงจากปีการศึกษาล่าสุดลงไป
// 🚨 แก้ไข: เพิ่ม supervision_schedule_pdf เข้าไปในคำสั่ง SELECT หรือใช้ * เพื่อดึงทุกคอลัมน์ 🚨
$sql = "SELECT round_id, academic_year, start_date, end_date, created_by, round_status, supervision_schedule_pdf FROM internship_rounds ORDER BY academic_year DESC";
$result = $conn->query($sql);

$rounds = array();
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $rounds[] = $row;
    }
}
echo json_encode($rounds);
?>