<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

$internship_id = $_GET['internship_id'] ?? '';
$response = array();

if ($internship_id) {
    // JOIN ตาราง users เพื่อเอาชื่อ (full_name) และดึงที่อยู่ (address) จาก companies
    $sql = "SELECT si.student_id, u.full_name, c.company_name, c.address 
            FROM student_internships si 
            JOIN companies c ON si.company_id = c.company_id 
            JOIN users u ON si.student_id = u.users_name
            WHERE si.internship_id = '$internship_id' LIMIT 1";

    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $response['success'] = true;
        $response['data'] = $result->fetch_assoc();
    } else {
        $response['success'] = false;
        $response['message'] = "ไม่พบข้อมูลการฝึกงาน";
    }
} else {
    $response['success'] = false;
    $response['message'] = "ไม่ได้ระบุ ID การฝึกงาน";
}

echo json_encode($response);
$conn->close();
?>