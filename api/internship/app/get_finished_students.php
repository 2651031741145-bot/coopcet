<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

$year = $_GET['year'] ?? 'all';
$response = array();

// 1. ดึงรายการปี พ.ศ. ทั้งหมดที่มีข้อมูล finished
$sql_years = "SELECT DISTINCT s.internship_year FROM internship_summaries s 
              JOIN student_internships si ON s.internship_id = si.internship_id 
              WHERE si.status = 'finished' ORDER BY s.internship_year DESC";
$res_years = $conn->query($sql_years);
$years = array();
if ($res_years) {
    while($y = $res_years->fetch_assoc()) { $years[] = $y['internship_year']; }
}

// 2. กรองข้อมูลตามปี
$whereClause = "si.status = 'finished'";
if ($year !== 'all') {
    $year_escaped = $conn->real_escape_string($year);
    $whereClause .= " AND s.internship_year = '$year_escaped'";
}

// 3. ดึงข้อมูลที่ต้องการแสดง 
$sql = "SELECT si.internship_id, si.student_id, u.full_name, c.company_name, c.address, c.latitude, c.longitude,
               s.position, s.internship_year, s.has_benefits, s.benefit_details, s.supervisor_name, 
               s.project_file_path, s.student_count, s.can_publish,
               
               (SELECT c_old.company_name 
                FROM student_internships si_old 
                JOIN companies c_old ON si_old.company_id = c_old.company_id 
                WHERE si_old.student_id = si.student_id AND si_old.status = 'relocated' 
                ORDER BY si_old.internship_id DESC LIMIT 1) AS old_company_name,
                
               (SELECT si_old.relocate_reason 
                FROM student_internships si_old 
                WHERE si_old.student_id = si.student_id AND si_old.status = 'relocated' 
                ORDER BY si_old.internship_id DESC LIMIT 1) AS relocate_reason,

               -- 🚨 เพิ่มการดึงข้อมูลประเมินจากตาราง teacher_evaluations 🚨
               te.eval_id, 
               te.total_score, 
               te.overall_grade, 
               te.hire_decision, 
               te.strengths, 
               te.improvements, 
               te.comments

        FROM student_internships si
        JOIN users u ON si.student_id = u.users_name
        JOIN companies c ON si.company_id = c.company_id
        JOIN internship_summaries s ON si.internship_id = s.internship_id
        LEFT JOIN teacher_evaluations te ON si.student_id = te.student_id -- 🚨 เชื่อมตารางประเมินด้วย LEFT JOIN
        WHERE $whereClause
        ORDER BY s.internship_year DESC, si.student_id ASC";

$result = $conn->query($sql);

if ($result) {
    $data = array();
    while($row = $result->fetch_assoc()) { $data[] = $row; }
    $response['success'] = true;
    $response['data'] = $data;
    $response['years'] = $years;
} else {
    $response['success'] = false;
    $response['message'] = "เกิดข้อผิดพลาด: " . $conn->error;
}

echo json_encode($response);
$conn->close();
?>