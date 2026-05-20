<?php
header('Content-Type: text/html; charset=utf-8');
require_once 'db_connect.php';

// ==========================================
// 1. ตั้งค่า Telegram Bot
// ==========================================
$botToken = "8661999127:AAEXg2r4j8fcU8W6oxWTK-RYA7d46Ey3JoQ"; // 🚨 อย่าลืมใส่ Token คืนนะครับ
$chatId = "-5217892445"; // Chat ID ของกลุ่ม

// ==========================================
// 2. คำสั่ง SQL ค้นหานักศึกษาที่ขาดบันทึก
// ==========================================
$sql = "SELECT 
            si.student_id, 
            u.full_name,
            c.company_name,
            MAX(dl.log_date) as last_log_date,
            DATEDIFF(CURRENT_DATE(), MAX(dl.log_date)) as days_missing
        FROM student_internships si
        JOIN users u ON si.student_id = u.users_name
        JOIN companies c ON si.company_id = c.company_id
        LEFT JOIN daily_logs dl ON si.internship_id = dl.internship_id
        WHERE si.status = 'active'
        GROUP BY si.internship_id, si.student_id, u.full_name, c.company_name
        HAVING days_missing >= 7 OR last_log_date IS NULL";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $message = "⚠️ *แจ้งเตือน: นักศึกษาขาดการบันทึกรายวันเกิน 7 วัน*\n\n";
    $count = 1;

    while($row = $result->fetch_assoc()) {
        $studentId = $row['student_id']; // 🚨 ดึงรหัสนักศึกษามาเก็บไว้
        $studentName = $row['full_name'];
        $company = $row['company_name'];
        
        if (is_null($row['last_log_date'])) {
            $missingDays = "ยังไม่เคยบันทึกเลยตั้งแต่เริ่มฝึกงาน";
            $lastLog = "-";
        } else {
            $missingDays = $row['days_missing'] . " วัน";
            $lastLog = $row['last_log_date'];
        }

        // 🚨 เพิ่มบรรทัดรหัสนักศึกษาเข้าไปในข้อความ
        $message .= "{$count}. *{$studentName}*\n";
        $message .= "🆔 รหัส: {$studentId}\n"; 
        $message .= "🏢 สถานที่: {$company}\n";
        $message .= "🛑 ขาดบันทึก: {$missingDays}\n";
        $message .= "📅 บันทึกล่าสุด: {$lastLog}\n\n";
        $count++;
    }

    // ==========================================
    // 3. ส่งข้อความเข้า Telegram
    // ==========================================
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
    echo "ยอดเยี่ยม! ไม่มีนักศึกษาคนไหนขาดบันทึกเกิน 7 วันครับ";
}

$conn->close();
?>