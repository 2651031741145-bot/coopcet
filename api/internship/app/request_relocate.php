<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

$response = array();

if (isset($_POST['internship_id']) && isset($_POST['reason'])) {
    
    $internship_id = $conn->real_escape_string($_POST['internship_id']);
    $reason = $conn->real_escape_string($_POST['reason']);

    // อัปเดตสถานะเป็น 'relocating' และเก็บเหตุผล
    $sql = "UPDATE student_internships 
            SET status = 'relocating', relocate_reason = '$reason' 
            WHERE internship_id = '$internship_id' AND status = 'active'";

    if ($conn->query($sql)) {
        if ($conn->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = "ส่งคำร้องขอย้ายสถานที่เรียบร้อยแล้ว กรุณารออาจารย์อนุมัติ";
        } else {
            $response['success'] = false;
            $response['message'] = "ไม่สามารถทำรายการได้ (สถานะอาจไม่ถูกต้อง หรือไม่มีข้อมูล)";
        }
    } else {
        $response['success'] = false;
        $response['message'] = "เกิดข้อผิดพลาด: " . $conn->error;
    }
} else {
    $response['success'] = false;
    $response['message'] = "ข้อมูลไม่ครบถ้วน";
}

echo json_encode($response);
$conn->close();
?>