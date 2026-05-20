<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

$response = array();

if (isset($_POST['student_id']) && isset($_POST['company_id'])) {
    
    $student_id = $conn->real_escape_string($_POST['student_id']);
    $company_id = $conn->real_escape_string($_POST['company_id']);

    // 1. ค้นหาข้อมูลการย้ายที่เพิ่งได้รับอนุมัติ (status = 'relocated') เพื่อดึงวันที่พิเศษและ round_id เดิม
    $check_relocate = "SELECT round_id, custom_start_date, custom_end_date 
                       FROM student_internships 
                       WHERE student_id = '$student_id' AND status = 'relocated' 
                       ORDER BY internship_id DESC LIMIT 1";
    
    $relocate_result = $conn->query($check_relocate);
    
    $round_id = 0;
    $custom_start = "NULL";
    $custom_end = "NULL";

    if ($relocate_result && $relocate_result->num_rows > 0) {
        // กรณีเป็นเด็กที่ย้ายมา: ให้ดึงค่าพิเศษมาใช้เหมือนเดิม
        $row = $relocate_result->fetch_assoc();
        $round_id = $row['round_id'];
        $custom_start = $row['custom_start_date'] ? "'" . $row['custom_start_date'] . "'" : "NULL";
        $custom_end = $row['custom_end_date'] ? "'" . $row['custom_end_date'] . "'" : "NULL";
    } else {
        // 🚨 กรณีเป็นเด็กใหม่ (ไม่ได้ย้าย): แก้ไขให้หารอบที่เปิดอยู่ และตรงกับปีการศึกษาของตัวเอง 🚨
        $student_year = "";
        if (strlen($student_id) >= 3) {
            $student_year = "25" . substr($student_id, 1, 2);
        }

        $get_round = "SELECT round_id FROM internship_rounds 
                      WHERE round_status = 'open' AND academic_year = '$student_year' 
                      LIMIT 1";
        $round_res = $conn->query($get_round);

        if ($round_res && $round_res->num_rows > 0) {
            // เจอปีตรงกันเป๊ะ และเปิดอยู่
            $round_id = $round_res->fetch_assoc()['round_id'];
        } else {
            // ถ้าหาปีของตัวเองไม่เจอจริงๆ (กันเหนียว) ให้หารอบล่าสุดที่ยัง "open" อยู่มาใช้
            $get_backup_round = "SELECT round_id FROM internship_rounds WHERE round_status = 'open' ORDER BY round_id DESC LIMIT 1";
            $backup_res = $conn->query($get_backup_round);
            if ($backup_res && $backup_res->num_rows > 0) {
                $round_id = $backup_res->fetch_assoc()['round_id'];
            }
        }
    }

    if ($round_id > 0) {
        // 2. บันทึกที่ฝึกงานใหม่ โดยสืบทอดวันที่พิเศษมาด้วย (ถ้ามี)
        $sql = "INSERT INTO student_internships (student_id, company_id, round_id, status, custom_start_date, custom_end_date) 
                VALUES ('$student_id', '$company_id', '$round_id', 'active', $custom_start, $custom_end)";

        if ($conn->query($sql)) {
            $response['success'] = true;
            $response['message'] = "ลงทะเบียนสถานที่ฝึกงานเรียบร้อยแล้ว";
        } else {
            $response['success'] = false;
            $response['message'] = "เกิดข้อผิดพลาดในการบันทึก: " . $conn->error;
        }
    } else {
        $response['success'] = false;
        $response['message'] = "ไม่พบรอบการฝึกงานในระบบ";
    }
} else {
    $response['success'] = false;
    $response['message'] = "ข้อมูลไม่ครบถ้วน";
}

echo json_encode($response);
$conn->close();
?>