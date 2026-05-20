<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

// ==========================================
// 1. ตั้งค่า Telegram Bot
// ==========================================
$botToken = "8661999127:AAEXg2r4j8fcU8W6oxWTK-RYA7d46Ey3JoQ"; // 🚨 ใส่ Token ของคุณศิรศักดิ์
$chatId = "-5217892445"; 

$response = array();

// 🚨 รับแค่ student_id อย่างเดียว ไม่ต้องรับเบอร์โทรแล้ว
$student_id = $_POST['student_id'] ?? '';

if (!empty($student_id)) {
    // ==========================================
    // 2. ดึงข้อมูลนักศึกษา สถานที่ฝึกงาน และเบอร์โทรศัพท์
    // ==========================================
    $sql = "SELECT 
                u.full_name,
                u.phone_number, -- 🎯 ดึงเบอร์โทรจากตาราง users
                c.company_name
            FROM student_internships si
            JOIN users u ON si.student_id = u.users_name
            JOIN companies c ON si.company_id = c.company_id
            WHERE si.student_id = '$student_id' 
              AND si.status = 'active'
            ORDER BY si.internship_id DESC LIMIT 1";

    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $studentName = $row['full_name'];
        $companyName = $row['company_name'];
        
        // 🎯 เช็กว่ามีเบอร์โทรไหม ถ้าไม่มีให้ขึ้นข้อความเตือน
        $phoneNumber = !empty($row['phone_number']) ? "`{$row['phone_number']}`" : "❌ *ไม่ได้ระบุเบอร์โทรไว้ในระบบ*";

        // ==========================================
        // 3. สร้างข้อความแจ้งเตือน SOS
        // ==========================================
        $message = "🚨 *แจ้งเตือนฉุกเฉิน (SOS)* 🚨\n\n";
        $message .= "ต้องการความช่วยเหลือด่วน!\n\n";
        $message .= "👨‍🎓 *นักศึกษา:* {$studentName}\n";
        $message .= "🆔 *รหัส:* {$student_id}\n";
        $message .= "🏢 *สถานที่:* {$companyName}\n";
        $message .= "📞 *เบอร์ติดต่อ:* {$phoneNumber}\n"; 

        // ==========================================
        // 4. ส่งข้อความเข้า Telegram
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
        $telegram_response = curl_exec($ch);
        curl_close($ch);

        $response['success'] = true;
        $response['message'] = "ส่งสัญญาณขอความช่วยเหลือเรียบร้อยแล้ว อาจารย์จะติดต่อกลับโดยเร็วที่สุด";
    } else {
        $response['success'] = false;
        $response['message'] = "ไม่พบข้อมูลสถานที่ฝึกงานของคุณในระบบ";
    }
} else {
    $response['success'] = false;
    $response['message'] = "ข้อมูลไม่ครบถ้วน";
}

echo json_encode($response);
$conn->close();
?>