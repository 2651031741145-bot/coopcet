<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
include 'db_connect.php';

// ตั้งค่าโซนเวลาของไทย เพื่อให้เวลาล็อกแม่นยำ
date_default_timezone_set('Asia/Bangkok');
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $users_name = trim($_POST['users_name']);
    $password = trim($_POST['password']);

    $sql = "SELECT * FROM users WHERE users_name = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $users_name);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $now = date('Y-m-d H:i:s');

        // --- [ส่วนที่ 1: ตรวจสอบสถานะการล็อกบัญชี] ---
        if ($row['failed_login_attempts'] >= 5 && !empty($row['lockout_time'])) {
            if ($now < $row['lockout_time']) {
                // ยังอยู่ในช่วงเวลาถูกล็อก คคำนวณว่าเหลืออีกกี่นาที
                $diff = strtotime($row['lockout_time']) - strtotime($now);
                $minutes_left = ceil($diff / 60);
                
                echo json_encode([
                    "success" => false, 
                    "message" => "บัญชีถูกระงับชั่วคราว กรุณาลองใหม่ในอีก $minutes_left นาที"
                ]);
                exit();
            } else {
                // เวลาล็อกหมดแล้ว ให้รีเซ็ตจำนวนครั้งที่ผิดเป็น 0
                $row['failed_login_attempts'] = 0;
                $row['lockout_time'] = null;
                
                $resetSql = "UPDATE users SET failed_login_attempts = 0, lockout_time = NULL WHERE users_name = ?";
                $resetStmt = $conn->prepare($resetSql);
                $resetStmt->bind_param("s", $users_name);
                $resetStmt->execute();
            }
        }

        // --- [ส่วนที่ 2: ตรวจสอบรหัสผ่าน] ---
        if (password_verify($password, $row['password'])) {

            // ถ้ารหัสถูก ให้เคลียร์ประวัติการกรอกผิดทิ้งให้สะอาด
            $clearFailSql = "UPDATE users SET failed_login_attempts = 0, lockout_time = NULL WHERE users_name = ?";
            $clearFailStmt = $conn->prepare($clearFailSql);
            $clearFailStmt->bind_param("s", $users_name);
            $clearFailStmt->execute();

            // เช็คการล็อกอินซ้อนในแอปมือถือ
            if (!empty($row['app_session_token'])) {
                echo json_encode([
                    "success" => false,
                    "message" => "บัญชีนี้มีการเข้าสู่ระบบค้างไว้ในแอปพลิเคชันจากอุปกรณ์อื่น"
                ]);
                exit();
            }

            // --- [สร้าง Session สำหรับแอปพลิเคชัน] ---
            $token = bin2hex(random_bytes(32));

            $updateSql = "UPDATE users SET app_session_token = ? WHERE users_name = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("ss", $token, $users_name);

            if ($updateStmt->execute()) {
                $fullname = $row['full_name'];

                echo json_encode([
                    "success" => true,
                    "message" => "เข้าสู่ระบบสำเร็จ",
                    "user_level" => $row['user_level'],
                    "users_name" => $row['users_name'],
                    "fullname" => $fullname,
                    "token" => $token, 
                    "academic_year" => $row['academic_year']
                ]);
            } else {
                echo json_encode(["success" => false, "message" => "เกิดข้อผิดพลาดในการสร้าง Session"]);
            }

        } else {
            // --- [ส่วนที่ 3: กรณีใส่รหัสผ่านผิด] ---
            $attempts = $row['failed_login_attempts'] + 1;
            
            if ($attempts >= 5) {
                // ผิดครบ 5 ครั้ง -> ล็อก 15 นาที
                $lockout_time = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                $msg = "คุณกรอกรหัสผ่านผิด 5 ครั้ง บัญชีถูกล็อกเป็นเวลา 15 นาที";
                
                $failSql = "UPDATE users SET failed_login_attempts = ?, lockout_time = ? WHERE users_name = ?";
                $failStmt = $conn->prepare($failSql);
                $failStmt->bind_param("iss", $attempts, $lockout_time, $users_name);
            } else {
                // ยังผิดไม่ครบ 5 ครั้ง -> นับเพิ่มและบอกจำนวนครั้งที่เหลือ
                $left = 5 - $attempts;
                $msg = "รหัสผ่านไม่ถูกต้อง (เหลือโอกาสอีก $left ครั้ง)";
                
                $failSql = "UPDATE users SET failed_login_attempts = ? WHERE users_name = ?";
                $failStmt = $conn->prepare($failSql);
                $failStmt->bind_param("is", $attempts, $users_name);
            }
            
            $failStmt->execute();
            echo json_encode(["success" => false, "message" => $msg]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "ไม่พบชื่อผู้ใช้นี้"]);
    }
}
?>