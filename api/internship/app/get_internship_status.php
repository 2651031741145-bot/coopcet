<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
include 'db_connect.php';

$student_id = $_GET['student_id'] ?? '';

if (empty($student_id)) {
    echo json_encode(["success" => false, "message" => "ไม่พบรหัสนักศึกษา"]);
    exit();
}

// ดึงข้อมูลคำร้องล่าสุด (ORDER BY internship_id DESC LIMIT 1)
$sql = "SELECT si.status, si.custom_start_date, si.custom_end_date, 
               c.company_name, 
               r.start_date, r.end_date 
        FROM student_internships si
        JOIN companies c ON si.company_id = c.company_id
        JOIN internship_rounds r ON si.round_id = r.round_id
        WHERE si.student_id = ? 
        ORDER BY si.internship_id DESC LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    
    // จัดการเรื่องวันที่ ถ้าอาจารย์มีกำหนดวันแบบ Custom ให้ใช้วันนั้นแทนวันปกติ
    $start = !empty($row['custom_start_date']) ? $row['custom_start_date'] : $row['start_date'];
    $end = !empty($row['custom_end_date']) ? $row['custom_end_date'] : $row['end_date'];
    
    $row['active_start_date'] = $start;
    $row['active_end_date'] = $end;

    echo json_encode(["success" => true, "data" => $row]);
} else {
    echo json_encode(["success" => false, "message" => "ยังไม่ได้ยื่นคำร้อง"]);
}
?>