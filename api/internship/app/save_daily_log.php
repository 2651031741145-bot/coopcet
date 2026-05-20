<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

$response = array();

// ตรวจสอบค่าพื้นฐานที่จำเป็น
if (isset($_POST['internship_id']) && isset($_POST['log_date'])) {
    
    // รับค่าและป้องกัน SQL Injection
    $internship_id = $conn->real_escape_string($_POST['internship_id']);
    $log_date = $conn->real_escape_string(trim($_POST['log_date']));
    
    // 🚨 1. บังคับรูปแบบวันที่ (Fail-Safe): ต้องเป็น YYYY-MM-DD เท่านั้น
    // ถ้าแอป Flutter ส่งมาผิดฟอร์แมต จะเด้ง Error บอกทันที ไม่ปล่อยให้ลงฐานข้อมูล
    if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $log_date)) {
        echo json_encode(['success' => false, 'message' => "รูปแบบวันที่ไม่ถูกต้อง (ต้องเป็นปี ค.ศ. YYYY-MM-DD) แต่แอปส่งมาเป็น: $log_date"]);
        exit();
    }

    // 🚨 2. แปลงค่าวันหยุดให้รองรับทั้งเลข '1' และคำว่า 'true' จาก Flutter
    $holiday_raw = isset($_POST['is_holiday']) ? $_POST['is_holiday'] : '0';
    $is_holiday = ($holiday_raw === '1' || strtolower($holiday_raw) === 'true') ? 1 : 0;

    // --- ลอจิกจัดการข้อมูลตามประเภทวัน (วันหยุด vs วันทำงาน) ---
    if ($is_holiday === 1) {
        // ถ้าเป็นวันหยุด
        $work_done = "วันหยุด / ลาพัก";
        $problem_found = "";
        $solution = "";
        $hours_worked = 0.00;
    } else {
        // ถ้าเป็นวันทำงาน
        $work_done = isset($_POST['work_done']) ? $conn->real_escape_string($_POST['work_done']) : '';
        $problem_found = isset($_POST['problem_found']) ? $conn->real_escape_string($_POST['problem_found']) : '';
        $solution = isset($_POST['solution']) ? $conn->real_escape_string($_POST['solution']) : '';
        
        // 🚨 3. แปลงชั่วโมงทำงาน: ถ้าเด็กปล่อยว่าง ("") ให้ถือว่าเป็น 0 
        $hours_raw = isset($_POST['hours_worked']) ? trim($_POST['hours_worked']) : '0';
        $hours_worked = ($hours_raw === '') ? 0.00 : (float)$hours_raw;
    }

    // ใช้ ON DUPLICATE KEY UPDATE เพื่อรองรับการแก้ไขข้อมูลเดิม
    $sql = "INSERT INTO daily_logs (internship_id, log_date, work_done, problem_found, solution, hours_worked, is_holiday) 
            VALUES ('$internship_id', '$log_date', '$work_done', '$problem_found', '$solution', '$hours_worked', '$is_holiday')
            ON DUPLICATE KEY UPDATE 
            work_done = VALUES(work_done), 
            problem_found = VALUES(problem_found), 
            solution = VALUES(solution), 
            hours_worked = VALUES(hours_worked),
            is_holiday = VALUES(is_holiday)";

    if ($conn->query($sql)) {
        $response['success'] = true;
        $response['message'] = $is_holiday === 1 ? "บันทึกเป็นวันหยุดเรียบร้อย" : "บันทึกข้อมูลการทำงานเรียบร้อย";
    } else {
        $response['success'] = false;
        $response['message'] = "เกิดข้อผิดพลาดทางเทคนิค: " . $conn->error;
    }
} else {
    $response['success'] = false;
    $response['message'] = "ข้อมูลไม่ครบถ้วน (internship_id หรือ log_date หายไป)";
}

echo json_encode($response);
$conn->close();
?>