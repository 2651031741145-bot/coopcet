<?php
header('Content-Type: text/html; charset=utf-8');
require_once 'db_connect.php';

// ==========================================
// 1. ตั้งค่า Telegram Bot
// ==========================================
$botToken = "8661999127:AAEXg2r4j8fcU8W6oxWTK-RYA7d46Ey3JoQ"; // 🚨 อย่าลืมใส่ Token คืนนะครับ
$chatId = "-5217892445"; 

// ==========================================
// 2. คำสั่ง SQL ค้นหานักศึกษาที่ขาดบันทึก (แก้บั๊ก 0000-00-00)
// ==========================================
$sql = "SELECT 
            si.student_id, 
            u.full_name,
            c.company_name,
            ir.academic_year,
            -- 🎯 ใช้ NULLIF แก้ปัญหา MySQL ชอบเก็บค่า 0000-00-00
            COALESCE(NULLIF(si.custom_start_date, '0000-00-00'), ir.start_date) as actual_start_date,
            MAX(dl.log_date) as last_log_date,
            DATEDIFF(CURRENT_DATE(), MAX(dl.log_date)) as days_since_last_log,
            DATEDIFF(CURRENT_DATE(), COALESCE(NULLIF(si.custom_start_date, '0000-00-00'), ir.start_date)) as days_since_start
        FROM student_internships si
        JOIN users u ON si.student_id = u.users_name
        JOIN companies c ON si.company_id = c.company_id
        JOIN internship_rounds ir ON si.round_id = ir.round_id
        LEFT JOIN daily_logs dl ON si.internship_id = dl.internship_id
        WHERE si.status = 'active'
          AND ir.round_status = 'open' 
          -- 🎯 ตรวจสอบวันที่ โดยข้ามปัญหา 0000-00-00
          AND CURRENT_DATE() >= COALESCE(NULLIF(si.custom_start_date, '0000-00-00'), ir.start_date) 
          AND CURRENT_DATE() <= COALESCE(NULLIF(si.custom_end_date, '0000-00-00'), ir.end_date)
        GROUP BY si.internship_id, si.student_id, u.full_name, c.company_name, ir.academic_year, ir.start_date, si.custom_start_date
        HAVING (last_log_date IS NOT NULL AND days_since_last_log >= 7) 
            OR (last_log_date IS NULL AND days_since_start >= 7)";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $message = "⚠️ *แจ้งเตือน: นักศึกษาขาดการบันทึกรายวันเกิน 7 วัน*\n\n";
    $count = 1;

    while($row = $result->fetch_assoc()) {
        $studentId = $row['student_id'];
        $studentName = $row['full_name'];
        $company = $row['company_name'];
        $academicYear = $row['academic_year']; 
        
        if (is_null($row['last_log_date'])) {
            $missingDays = "ยังไม่เคยบันทึกเลย (ผ่านมาแล้ว {$row['days_since_start']} วันนับจากวันเริ่มฝึก)";
            $lastLog = "-";
        } else {
            $missingDays = $row['days_since_last_log'] . " วัน";
            $lastLog = $row['last_log_date'];
        }

        $message .= "{$count}. *{$studentName}*\n";
        $message .= "🆔 รหัส: {$studentId}\n"; 
        $message .= "🏢 สถานที่: {$company}\n";
        $message .= "🛑 ขาดบันทึก: {$missingDays}\n";
        $message .= "📅 บันทึกล่าสุด: {$lastLog}\n\n";
        $count++;
    }

    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $data = array(
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'Markdown'
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    echo "ส่งการแจ้งเตือนสำเร็จ! มีนักศึกษาขาดบันทึกจำนวน " . ($count - 1) . " คน";
} else {
    echo "ยอดเยี่ยม! ไม่มีนักศึกษาคนไหนขาดบันทึกเกิน 7 วัน หรืออยู่นอกระยะเวลาการฝึกงานครับ";
}

$conn->close();
?>