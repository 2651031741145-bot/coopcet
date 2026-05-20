<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

$response = array();

// เพิ่ม Subquery ดึง old_company_name และ relocate_reason เฉพาะคนที่เคยย้าย
$sql = "SELECT si.internship_id, si.student_id, u.full_name, 
               c.company_name, c.address, c.latitude, c.longitude, 
               s.internship_year, s.position, s.project_file_path, s.created_at,
               s.has_benefits, s.benefit_details, s.student_count, s.can_publish, s.supervisor_name,
               
               (SELECT c_old.company_name 
                FROM student_internships si_old 
                JOIN companies c_old ON si_old.company_id = c_old.company_id 
                WHERE si_old.student_id = si.student_id AND si_old.status = 'relocated' 
                ORDER BY si_old.internship_id DESC LIMIT 1) AS old_company_name,
                
               (SELECT si_old.relocate_reason 
                FROM student_internships si_old 
                WHERE si_old.student_id = si.student_id AND si_old.status = 'relocated' 
                ORDER BY si_old.internship_id DESC LIMIT 1) AS relocate_reason

        FROM student_internships si
        JOIN users u ON si.student_id = u.users_name
        JOIN companies c ON si.company_id = c.company_id
        JOIN internship_summaries s ON si.internship_id = s.internship_id
        WHERE si.status = 'pending'
        ORDER BY s.created_at ASC"; 

$result = $conn->query($sql);

if ($result) {
    $approvals = array();
    while($row = $result->fetch_assoc()) {
        $approvals[] = $row;
    }
    $response['success'] = true;
    $response['data'] = $approvals;
} else {
    $response['success'] = false;
    $response['message'] = "เกิดข้อผิดพลาด: " . $conn->error;
}

echo json_encode($response);
$conn->close();
?>