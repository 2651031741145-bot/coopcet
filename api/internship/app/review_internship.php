<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

$response = array();

if (isset($_POST['internship_id']) && isset($_POST['action'])) {
    $internship_id = $conn->real_escape_string($_POST['internship_id']);
    $action = $conn->real_escape_string($_POST['action']);
    
    // กำหนดสถานะเป้าหมาย: approve -> finished, reject -> active
    $new_status = ($action === 'approve') ? 'finished' : 'active';

    $sql = "UPDATE student_internships SET status = '$new_status' WHERE internship_id = '$internship_id'";

    if ($conn->query($sql)) {
        $response['success'] = true;
        $response['message'] = ($action === 'approve') ? "อนุมัติจบการฝึกงานเรียบร้อย" : "ส่งกลับให้นักศึกษาแก้ไขแล้ว";
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