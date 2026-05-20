<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

$response = array();

if (isset($_POST['round_id'])) {
    $round_id = $conn->real_escape_string($_POST['round_id']);

    // ตรวจสอบก่อนว่ามีนักศึกษาลงทะเบียนในรอบนี้หรือไม่ เพื่อป้องกันข้อมูลกำพร้า
    $check_sql = "SELECT COUNT(*) as count FROM student_internships WHERE round_id = '$round_id'";
    $check_res = $conn->query($check_sql);
    $row = $check_res->fetch_assoc();

    if ($row['count'] > 0) {
        $response['success'] = false;
        $response['message'] = "ไม่สามารถลบได้ เนื่องจากมีข้อมูลการฝึกงานของนักศึกษาผูกอยู่กับรอบนี้";
    } else {
        $sql = "DELETE FROM internship_rounds WHERE round_id = '$round_id'";
        if ($conn->query($sql)) {
            $response['success'] = true;
            $response['message'] = "ลบรอบการฝึกงานเรียบร้อยแล้ว";
        } else {
            $response['success'] = false;
            $response['message'] = "เกิดข้อผิดพลาด: " . $conn->error;
        }
    }
} else {
    $response['success'] = false;
    $response['message'] = "ไม่พบรหัสรอบการฝึกงาน";
}

echo json_encode($response);
$conn->close();
?>