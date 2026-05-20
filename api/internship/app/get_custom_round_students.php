<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

$response = array();

// ดึงข้อมูลนักศึกษาที่ "กำลังฝึกงานอยู่/รออนุมัติจบ" 
// และ (เคยมีประวัติย้ายสถานที่ หรือ มีการตั้งวันที่ custom ไว้แล้ว)
$sql = "SELECT si.internship_id, si.student_id, u.full_name, c.company_name, 
               si.custom_start_date, si.custom_end_date
        FROM student_internships si
        JOIN users u ON si.student_id = u.users_name
        JOIN companies c ON si.company_id = c.company_id
        WHERE si.status IN ('active', 'pending') 
        AND (
            si.custom_start_date IS NOT NULL 
            OR 
            si.student_id IN (SELECT student_id FROM student_internships WHERE status = 'relocated')
        )
        ORDER BY u.full_name ASC";

$result = $conn->query($sql);

if ($result) {
    $students = array();
    while($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    $response['success'] = true;
    $response['data'] = $students;
} else {
    $response['success'] = false;
    $response['message'] = "เกิดข้อผิดพลาด: " . $conn->error;
}

echo json_encode($response);
$conn->close();
?>