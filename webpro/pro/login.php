<?php
session_start();
require_once 'db.php'; 

// ตั้งค่าโซนเวลาของไทย
date_default_timezone_set('Asia/Bangkok');

// เช็คว่าถ้ามี Session ครบถ้วนแล้ว ให้ข้ามหน้า Login ไปเลย
if (isset($_SESSION['users_name']) && isset($_SESSION['user_level']) && isset($_SESSION['session_token'])) {
    if ($_SESSION['user_level'] == 'a') { header("Location: admin_dashboard.php"); exit(); }
    if ($_SESSION['user_level'] == 't') { header("Location: teacher_dashboard.php"); exit(); }
    if ($_SESSION['user_level'] == 's') { header("Location: student_dashboard.php"); exit(); }
}

$error_msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE users_name = '$username'";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $now = date('Y-m-d H:i:s');
        
        // --- [ส่วนที่ 1: ตรวจสอบสถานะการล็อกบัญชี] ---
        $is_locked = false;
        if ($user['failed_login_attempts'] >= 5 && !empty($user['lockout_time'])) {
            if ($now < $user['lockout_time']) {
                // ยังอยู่ในช่วงเวลาถูกล็อก
                $diff = strtotime($user['lockout_time']) - strtotime($now);
                $minutes_left = ceil($diff / 60);
                $error_msg = "บัญชีถูกระงับชั่วคราว ลองใหม่ในอีก $minutes_left นาที ⏳";
                $is_locked = true;
            } else {
                // เวลาล็อกหมดแล้ว ให้รีเซ็ตค่าในระบบและในตัวแปร
                $user['failed_login_attempts'] = 0;
                $user['lockout_time'] = null;
                $conn->query("UPDATE users SET failed_login_attempts = 0, lockout_time = NULL WHERE users_name = '$username'");
            }
        }

        // --- [ส่วนที่ 2: ถ้าบัญชีไม่ได้ถูกล็อก ให้เช็ครหัสผ่านต่อ] ---
        if (!$is_locked) {
            // เช็ครหัสผ่าน Hash จากฐานข้อมูล
            if (password_verify($password, $user['password'])) {
                
                // ถ้ารหัสถูก ให้เคลียร์ประวัติการกรอกผิดทิ้งให้สะอาด
                $conn->query("UPDATE users SET failed_login_attempts = 0, lockout_time = NULL WHERE users_name = '$username'");
                
                // 1. สร้าง Token และอัปเดตลงฐานข้อมูล
                $session_token = bin2hex(random_bytes(32)); 
                $update_sql = "UPDATE users SET session_token = '$session_token' WHERE users_name = '$username'";
                $conn->query($update_sql);

                // 2. เก็บลง Session เบราว์เซอร์
                $_SESSION['users_name'] = $user['users_name'];
                $_SESSION['user_level'] = $user['user_level'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['session_token'] = $session_token; 
                
                // 3. แยกหน้าตามระดับสิทธิ์
                if ($user['user_level'] == 'a') { header("Location: admin_dashboard.php"); exit(); }
                if ($user['user_level'] == 't') { header("Location: teacher_dashboard.php"); exit(); }
                if ($user['user_level'] == 's') { header("Location: student_dashboard.php"); exit(); }

            } else {
                // --- [ส่วนที่ 3: กรณีใส่รหัสผ่านผิด] ---
                $attempts = $user['failed_login_attempts'] + 1;
                
                if ($attempts >= 5) {
                    // ผิดครบ 5 ครั้ง -> ล็อก 15 นาที
                    $lockout_time = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                    $conn->query("UPDATE users SET failed_login_attempts = $attempts, lockout_time = '$lockout_time' WHERE users_name = '$username'");
                    $error_msg = "รหัสผิด 5 ครั้ง! บัญชีถูกล็อก 15 นาที 🚫";
                } else {
                    // ยังผิดไม่ครบ 5 ครั้ง -> นับเพิ่มและบอกจำนวนครั้งที่เหลือ
                    $conn->query("UPDATE users SET failed_login_attempts = $attempts WHERE users_name = '$username'");
                    $left = 5 - $attempts;
                    $error_msg = "รหัสผ่านไม่ถูกต้องจ้า (เหลือโอกาสอีก $left ครั้ง) 😿";
                }
            }
        }
    } else {
        $error_msg = "ไม่พบรหัสสมาชิกนี้ในระบบคลังข้อมูล 🧐";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - คลังข้อมูลฝึกงาน</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Prompt:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --lib-bg: #fdfaf6;
            --lib-primary: #a2d2ff;
            --lib-accent: #ffb5a7;
            --lib-text: #5e503f;
        }

        body { 
            background-color: var(--lib-bg); 
            background-image: radial-gradient(#e5e0d8 1.5px, transparent 1.5px);
            background-size: 25px 25px;
            height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-family: 'Prompt', 'Poppins', sans-serif;
            color: var(--lib-text);
        }

        /* การ์ดล็อกอิน (สไตล์สมุดพก) */
        .login-card { 
            background: white; 
            padding: 40px 30px; 
            border-radius: 30px; 
            box-shadow: 0 15px 35px rgba(94, 80, 63, 0.1); 
            width: 100%; 
            max-width: 400px; 
            text-align: center; 
            position: relative;
            border-top: 10px solid var(--lib-primary);
        }

        /* ตกแต่งด้านบนการ์ดให้เหมือนที่คั่นหนังสือ */
        .bookmark {
            position: absolute;
            top: -10px;
            right: 40px;
            width: 30px;
            height: 50px;
            background-color: var(--lib-accent);
            border-bottom-left-radius: 15px;
            border-bottom-right-radius: 15px;
            box-shadow: 2px 2px 5px rgba(0,0,0,0.1);
        }

        .login-title {
            font-weight: 700;
            color: var(--lib-text);
            font-size: 2rem;
            margin-bottom: 5px;
        }

        /* ช่องกรอกข้อมูลโค้งมน */
        .form-control {
            border-radius: 20px;
            padding: 12px 20px;
            border: 2px solid #f0efe9;
            background-color: #faf9f5;
            color: var(--lib-text);
        }
        .form-control:focus {
            border-color: var(--lib-primary);
            box-shadow: 0 0 0 0.2rem rgba(162, 210, 255, 0.25);
            background-color: white;
        }
        
        /* ปุ่มเข้าสู่ระบบ */
        .btn-login { 
            background-color: var(--lib-primary); 
            color: white; 
            font-weight: 600; 
            border-radius: 50px; 
            transition: 0.3s; 
            border: none;
            font-size: 1.1rem;
        }
        .btn-login:hover { 
            background-color: #8bbdf0; 
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(162, 210, 255, 0.4);
            color: white;
        }

        /* ข้อความ Error น่ารักๆ */
        .alert-cute {
            background-color: #ffe5ec;
            color: #fb6f92;
            border: none;
            border-radius: 15px;
            font-weight: 500;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="bookmark"></div>

        <div class="mb-4">
            <i class="fas fa-book-reader text-primary" style="font-size: 3rem; color: var(--lib-primary) !important;"></i>
            <h3 class="login-title mt-2">Login</h3>
            <p class="text-muted small">กรอกรหัสเพื่อเข้าคลังข้อมูล 🎟️</p>
        </div>

        <?php if($error_msg != ""): ?>
            <div class="alert alert-cute p-2 mb-4"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST" class="text-start">
            <div class="mb-3">
                <label class="form-label text-muted small fw-bold ms-2">รหัสผู้ใช้งาน 🆔</label>
                <input type="text" name="username" class="form-control" placeholder="รหัสนักศึกษา / อาจารย์" required>
            </div>
            <div class="mb-4">
                <label class="form-label text-muted small fw-bold ms-2">รหัสผ่าน 🔑</label>
                <input type="password" name="password" class="form-control" placeholder="รหัสผ่านของคุณ" required>
            </div>
            <button type="submit" class="btn btn-login w-100 py-2 mt-2 shadow-sm">
                <i class="fas fa-unlock-alt me-2"></i> ยืนยันตัวตน
            </button>
        </form>

        <div class="mt-4 pt-3 border-top" style="border-color: #f0efe9 !important;">
            <a href="index.php" class="text-muted small text-decoration-none fw-bold">
                <i class="fas fa-arrow-left me-1"></i> กลับหน้าหลัก
            </a>
        </div>
    </div>

</body>
</html>