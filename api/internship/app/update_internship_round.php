<?php
// แสดง Error เพื่อการตรวจสอบ (ปิดเมื่อใช้งานจริง)
ini_set('display_errors', 0); // ปิดการโชว์ Error ตรงๆ เพื่อความปลอดภัย
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

$response = array();

// ตรวจสอบว่าส่งข้อมูลมาครบหรือไม่
if (isset($_POST['round_id']) && isset($_POST['academic_year']) && isset($_POST['start_date']) && isset($_POST['end_date'])) {
    
    // รับค่าและขจัดช่องว่าง
    $round_id = trim($_POST['round_id']);
    $academic_year = trim($_POST['academic_year']);
    $start_date = trim($_POST['start_date']);
    $end_date = trim($_POST['end_date']);

    // ตรรกะตรวจสอบ: วันที่สิ้นสุดต้องไม่ก่อนวันเริ่มต้น
    if (strtotime($end_date) < strtotime($start_date)) {
        $response['success'] = false;
        $response['message'] = "วันที่สิ้นสุดต้องไม่ก่อนวันเริ่มฝึกงาน";
        echo json_encode($response);
        exit();
    }

    // ใช้ Prepared Statement เพื่อความปลอดภัยสูงสุด
    $stmt = $conn->prepare("UPDATE internship_rounds SET academic_year = ?, start_date = ?, end_date = ? WHERE round_id = ?");
    $stmt->bind_param("sssi", $academic_year, $start_date, $end_date, $round_id);

    if ($stmt->execute()) {
        // ตรวจสอบว่ามีการเปลี่ยนแปลงข้อมูลจริงหรือไม่
        if ($stmt->affected_rows >= 0) {
            $response['success'] = true;
            $response['message'] = "แก้ไขข้อมูลปีการศึกษา $academic_year เรียบร้อยแล้ว";
        } else {
            $response['success'] = false;
            $response['message'] = "ไม่พบข้อมูลที่ต้องการแก้ไข";
        }
    } else {
        $response['success'] = false;
        $response['message'] = "เกิดข้อผิดพลาดในการแก้ไข: " . $stmt->error;
    }

    $stmt->close();

} else {
    $response['success'] = false;
    $response['message'] = "ข้อมูลไม่ครบถ้วน กรุณาลองใหม่";
}

echo json_encode($response);
$conn->close();
?>