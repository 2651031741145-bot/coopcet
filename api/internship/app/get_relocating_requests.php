<?php
// แสดง Error เพื่อการตรวจสอบ
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

$response = array();

try {
    // ใช้โค้ดเดิมของคุณที่รัดกุมมาก แต่แก้ตรงบรรทัดแรกให้เป็น si.internship_id ตรงๆ (ไม่ใช้ AS request_id)
    $sql = "SELECT 
                si.internship_id, 
                si.student_id, 
                IFNULL(u.full_name, CONCAT('ไม่ทราบชื่อ (ID: ', si.student_id, ')')) AS student_name, 
                IFNULL(c.company_name, 'ไม่พบชื่อบริษัท') AS company_name,
                si.relocate_reason, 
                si.created_at
            FROM student_internships si
            LEFT JOIN users u ON si.student_id = u.users_name
            LEFT JOIN companies c ON si.company_id = c.company_id
            WHERE si.status = 'relocating'
            ORDER BY si.created_at DESC";

    $result = $conn->query($sql);

    $data = array();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $response['success'] = true;
        $response['data'] = $data;
        $response['count'] = count($data);
    } else {
        $response['success'] = false;
        $response['message'] = "SQL Error: " . $conn->error;
    }
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = "Exception: " . $e->getMessage();
}

echo json_encode($response);
$conn->close();
?>