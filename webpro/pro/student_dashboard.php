<?php
session_start();
require_once 'db.php';

// --- 1. ตรวจสอบสิทธิ์ (ต้องเป็นนักศึกษา 's' และมี Session Token ที่ถูกต้อง) ---
if (!isset($_SESSION['users_name']) || $_SESSION['user_level'] != 's' || !isset($_SESSION['session_token'])) {
    header("Location: logout.php");
    exit();
}

$username = $conn->real_escape_string($_SESSION['users_name']);
$current_token = $_SESSION['session_token'];

// ตรวจสอบ Token ป้องกันการล็อกอินซ้อน
$check_sql = "SELECT session_token FROM users WHERE users_name = '$username'";
$result = $conn->query($check_sql);
if ($result && $result->num_rows > 0) {
    $user_data = $result->fetch_assoc();
    if ($user_data['session_token'] !== $current_token) {
        header("Location: logout.php");
        exit();
    }
} else {
    header("Location: logout.php");
    exit();
}

$msg = "";
$msg_type = "";

// --- 2. จัดการเมื่อมีการกดบันทึกข้อมูล ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $full_name = $conn->real_escape_string($_POST['full_name']);
    
    // 🚨 รับค่าเบอร์โทรศัพท์ที่เพิ่มมาใหม่
    $phone_number = $conn->real_escape_string($_POST['phone_number']);
    
    $new_password = $_POST['new_password'];

    // ถ้ามีการกรอกรหัสผ่านใหม่มา ให้ทำการ Hash รหัสผ่านใหม่ด้วย
    if (!empty($new_password)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        // 🚨 อัปเดตเบอร์โทรศัพท์ลงไปด้วย
        $update_sql = "UPDATE users SET full_name = '$full_name', phone_number = '$phone_number', password = '$hashed_password' WHERE users_name = '$username'";
    } else {
        // ถ้าปล่อยช่องรหัสผ่านว่างไว้ ให้อัปเดตแค่ชื่อ และเบอร์โทร
        $update_sql = "UPDATE users SET full_name = '$full_name', phone_number = '$phone_number' WHERE users_name = '$username'";
    }

    if ($conn->query($update_sql)) {
        $_SESSION['full_name'] = $full_name; // อัปเดตชื่อใน Session ด้วย
        $msg = "💖 บันทึกข้อมูลส่วนตัวเรียบร้อยแล้วจ้า!";
        $msg_type = "success";
    } else {
        $msg = "😿 เกิดข้อผิดพลาดในการบันทึกข้อมูล";
        $msg_type = "danger";
    }
}

// --- 3. ดึงข้อมูลล่าสุดของตัวเองมาแสดงในฟอร์ม ---
$sql_me = "SELECT * FROM users WHERE users_name = '$username'";
$result_me = $conn->query($sql_me);
$my_info = $result_me->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>โปรไฟล์นักศึกษา ✨🎓</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Prompt:wght@300;400;600&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* --- 🎨 Theme พาสเทลสำหรับนักศึกษา (ฟ้า-เขียว) --- */
        :root {
            --bg-color: #f0fbff;
            --card-bg: #ffffff;
            --primary-soft: #a2d2ff;
            --success-soft: #bde0fe;
            --accent-color: #ffafcc;
            --text-dark: #4a4e69;
        }

        body {
            font-family: 'Prompt', 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--bg-color) 0%, #e0fbfc 100%);
            color: var(--text-dark);
            min-height: 100vh;
        }

        /* Navbar */
        .navbar-custom {
            background-color: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            border-bottom-left-radius: 20px;
            border-bottom-right-radius: 20px;
        }

        /* 🚨 สไตล์สำหรับแบนเนอร์ดาวน์โหลดแอป 🚨 */
        .download-banner {
            background: linear-gradient(135deg, #e0c3fc 0%, #8ec5fc 100%);
            border-radius: 25px;
            padding: 25px 30px;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 10px 25px rgba(142, 197, 252, 0.4);
            margin-bottom: 30px;
            transition: transform 0.3s ease;
        }

        .download-banner:hover {
            transform: translateY(-5px);
        }

        .download-icon {
            font-size: 3rem;
            text-shadow: 2px 2px 10px rgba(0, 0, 0, 0.1);
        }

        .btn-download-app {
            background: white;
            color: #8ec5fc;
            font-weight: bold;
            border-radius: 50px;
            padding: 12px 25px;
            text-decoration: none;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            display: inline-flex;
            align-items: center;
            white-space: nowrap;
        }

        .btn-download-app:hover {
            background: #f8f9fa;
            color: #7baff5;
            transform: scale(1.05);
        }

        @media (max-width: 576px) {
            .download-banner {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
        }

        /* Card โปรไฟล์ */
        .profile-card {
            background: var(--card-bg);
            border-radius: 30px;
            box-shadow: 0 10px 40px rgba(162, 210, 255, 0.2);
            border: none;
            overflow: hidden;
        }

        .profile-header {
            background: linear-gradient(135deg, var(--primary-soft) 0%, var(--success-soft) 100%);
            padding: 40px 20px 60px;
            text-align: center;
            color: white;
            position: relative;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            background: white;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 3.5rem;
            color: var(--primary-soft);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            position: absolute;
            bottom: -60px;
            left: 50%;
            transform: translateX(-50%);
            border: 5px solid white;
        }

        .profile-body {
            padding: 80px 40px 40px;
        }

        /* ฟอร์มและปุ่ม */
        .form-control {
            border-radius: 15px;
            padding: 12px 20px;
            border: 2px solid #f0f0f0;
            background-color: #fafbfc;
            color: var(--text-dark);
        }

        .form-control:focus {
            border-color: var(--primary-soft);
            box-shadow: 0 0 0 0.2rem rgba(162, 210, 255, 0.25);
            background-color: white;
        }

        .form-control[readonly] {
            background-color: #f1f3f5;
            cursor: not-allowed;
        }

        .btn-rounded {
            border-radius: 50px;
            font-weight: 600;
            padding: 12px 30px;
            transition: all 0.3s;
            border: none;
        }

        .btn-rounded:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .btn-save {
            background: var(--primary-soft);
            color: #0d6efd;
            font-size: 1.1rem;
        }

        .btn-save:hover {
            background: #8ec3f8;
            color: white;
        }

        .btn-logout {
            background-color: #ffe5ec;
            color: #d63384;
            padding: 8px 20px;
        }

        /* Icon ในช่อง Input */
        .input-group-text {
            background: transparent;
            border: 2px solid #f0f0f0;
            border-right: none;
            border-top-left-radius: 15px;
            border-bottom-left-radius: 15px;
            color: #adb5bd;
            transition: all 0.3s;
        }

        .form-control-with-icon {
            border-left: none;
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
            transition: all 0.3s;
        }

        /* 🚨 สไตล์สำหรับไฮไลท์แจ้งเตือนให้กรอกเบอร์โทร 🚨 */
        @keyframes pulse-alert {
            0% { box-shadow: 0 0 0 0 rgba(255, 107, 107, 0.6); }
            70% { box-shadow: 0 0 0 12px rgba(255, 107, 107, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 107, 107, 0); }
        }
        .highlight-alert {
            animation: pulse-alert 1.5s infinite !important;
            border-color: #ff6b6b !important;
            background-color: #fff5f5 !important;
        }
        .highlight-alert-icon {
            border-color: #ff6b6b !important;
            background-color: #fff5f5 !important;
            color: #ff6b6b !important;
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-custom sticky-top py-3">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="#">🎓 Student Portal</a>
            <div class="ms-auto d-flex align-items-center">
                <span class="me-3 text-muted fw-bold d-none d-sm-block">👋 ดีจ้า,
                    <?php echo htmlspecialchars($my_info['full_name'] ?? $username); ?></span>
                <a href="logout.php" class="btn btn-rounded btn-logout btn-sm"><i class="fas fa-sign-out-alt"></i>
                    ออกจากระบบ</a>
            </div>
        </div>
    </nav>

    <div class="container mt-5 pb-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">

                <?php if ($msg != ""): ?>
                    <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show shadow-sm rounded-pill px-4 text-center"
                        role="alert">
                        <i
                            class="<?php echo $msg_type == 'success' ? 'fas fa-check-circle' : 'fas fa-times-circle'; ?> me-2"></i>
                        <?php echo $msg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"
                            style="margin-top: 2px;"></button>
                    </div>
                <?php endif; ?>

                <div class="download-banner">
                    <div class="d-flex align-items-center gap-3">
                        <div class="download-icon"><i class="fab fa-android"></i></div>
                        <div class="text-start">
                            <h5 class="fw-bold mb-1">สะดวกกว่าเดิม! โหลดแอปเลย</h5>
                            <p class="mb-0 small" style="opacity: 0.9;">จัดการข้อมูลสหกิจ แจ้งพิกัด
                                และยื่นเรื่องผ่านมือถือได้ง่ายๆ</p>
                        </div>
                    </div>
                    <a href="app-release.apk" class="btn-download-app">
                        <i class="fas fa-download me-2"></i> โหลด APK
                    </a>
                </div>

                <div class="profile-card">
                    <div class="profile-header">
                        <h3 class="fw-bold mb-1">ข้อมูลส่วนตัว 💖</h3>
                        <p class="mb-0 opacity-75">จัดการชื่อและรหัสผ่านของคุณได้ที่นี่</p>
                        <div class="profile-avatar shadow-sm">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    </div>

                    <div class="profile-body">
                        <form action="student_dashboard.php" method="POST">
                            <input type="hidden" name="update_profile" value="1">

                            <div class="mb-4">
                                <label class="form-label fw-bold text-muted ms-2 small">รหัสนักศึกษา
                                    (ไม่สามารถเปลี่ยนได้) 🆔</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                    <input type="text" class="form-control form-control-with-icon"
                                        value="<?php echo htmlspecialchars($my_info['users_name']); ?>" readonly>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold text-muted ms-2 small">ชื่อ - นามสกุล 📛</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user-edit"></i></span>
                                    <input type="text" name="full_name" class="form-control form-control-with-icon"
                                        value="<?php echo htmlspecialchars($my_info['full_name'] ?? ''); ?>" required
                                        placeholder="กรอกชื่อ-นามสกุลของคุณ">
                                </div>
                            </div>

                            <?php 
                                $isPhoneEmpty = empty($my_info['phone_number']); 
                                $phoneClass = $isPhoneEmpty ? 'highlight-alert' : '';
                                $iconClass = $isPhoneEmpty ? 'highlight-alert-icon' : '';
                            ?>
                            <div class="mb-4">
                                <label class="form-label fw-bold text-muted ms-2 small">เบอร์โทรศัพท์ติดต่อ 📱 <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text <?php echo $iconClass; ?>" id="phone-icon"><i class="fas fa-phone-alt"></i></span>
                                    <input type="tel" name="phone_number" id="phone_input" 
                                        class="form-control form-control-with-icon <?php echo $phoneClass; ?>"
                                        value="<?php echo htmlspecialchars($my_info['phone_number'] ?? ''); ?>" required
                                        maxlength="10" placeholder="กรอกเบอร์โทรศัพท์ 10 หลัก (เช่น 0812345678)"
                                        pattern="[0-9]{9,10}" title="กรุณากรอกเบอร์โทรศัพท์ให้ถูกต้อง (ตัวเลข 9-10 หลัก)">
                                </div>
                                <?php if($isPhoneEmpty): ?>
                                    <div id="phone-warning" class="text-danger ms-2 mt-2" style="font-size: 0.85rem; font-weight: bold;">
                                        <i class="fas fa-exclamation-circle"></i> กรุณาอัปเดตเบอร์โทรศัพท์ของคุณ เพื่อใช้สำหรับแจ้งเหตุฉุกเฉิน
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold text-muted ms-2 small">นักศึกษาปีการศึกษา 📅</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                    <input type="text" class="form-control form-control-with-icon"
                                        value="<?php echo htmlspecialchars($my_info['academic_year']); ?>" readonly>
                                </div>
                            </div>

                            <hr class="my-4" style="border-color: #e0e0e0; opacity: 1;">

                            <div class="mb-4">
                                <label class="form-label fw-bold text-muted ms-2 small">เปลี่ยนรหัสผ่านใหม่ 🔑
                                    (หากไม่เปลี่ยน ให้เว้นว่างไว้)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" name="new_password"
                                        class="form-control form-control-with-icon"
                                        placeholder="กรอกรหัสผ่านใหม่ (ถ้าต้องการเปลี่ยน)">
                                </div>
                                <div class="form-text text-muted ms-2 mt-2" style="font-size: 0.8rem;">
                                    * รหัสผ่านของคุณจะถูกเข้ารหัสลับ (Hash) เพื่อความปลอดภัย
                                </div>
                            </div>

                            <div class="d-grid gap-2 mt-5">
                                <button type="submit" class="btn btn-save btn-rounded shadow-sm">
                                    <i class="fas fa-save me-2"></i> บันทึกข้อมูลส่วนตัว
                                </button>
                            </div>
                        </form>
                    </div> 
                </div> 

                <?php
                // ตรวจสอบสถานะการฝึกงาน (ต้องมีที่ฝึกงานแล้วถึงจะสร้างลิงก์ประเมินได้)
                $intern_check_sql = "SELECT status FROM student_internships WHERE student_id = '$username' ORDER BY created_at DESC LIMIT 1";
                $intern_res = $conn->query($intern_check_sql);
                $intern_status = ($intern_res && $intern_res->num_rows > 0) ? $intern_res->fetch_assoc()['status'] : '';

                // ตรวจสอบว่ามีการประเมินไปแล้วหรือยัง?
                $eval_check_sql = "SELECT eval_id FROM teacher_evaluations WHERE student_id = '$username' LIMIT 1";
                $eval_res = $conn->query($eval_check_sql);
                $has_evaluated = ($eval_res && $eval_res->num_rows > 0);

                // ถ้ามีสถานะฝึกงาน แต่ "ยังไม่ได้ประเมิน" ให้โชว์กล่องคัดลอกลิงก์
                if (in_array($intern_status, ['active', 'relocated', 'pending', 'finished'])):
                    if (!$has_evaluated):
                        // 🔑 สร้าง Token เข้ารหัสลับ
                        $secret_key = "RMUTR_COOP_SECURE_2024"; 
                        $eval_token = hash('sha256', $username . $secret_key);
                        
                        // สร้าง URL ปัจจุบันแบบ Dynamic
                        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                        $domain = $_SERVER['HTTP_HOST'];
                        $base_url = $protocol . "://" . $domain . dirname($_SERVER['PHP_SELF']);
                        $eval_link = $base_url . "/company_evaluate.php?id=" . $username . "&token=" . $eval_token;
                ?>
                        <div class="card card-custom mt-4 mb-4 shadow-sm" style="border: 2px solid var(--primary-soft); background-color: #fffdf5; border-radius: 25px;">
                            <div class="card-body text-center p-4 p-md-5">
                                <div class="mb-3">
                                    <i class="fas fa-paper-plane text-warning" style="font-size: 3rem;"></i>
                                </div>
                                <h4 class="fw-bold text-dark mb-2">ส่งแบบประเมินให้สถานประกอบการ</h4>
                                <p class="text-muted mb-4">คัดลอกลิงก์ด้านล่างนี้ ส่งให้พี่เลี้ยงหรือผู้ควบคุมการฝึกงานของคุณ<br>เพื่อทำการประเมินผลออนไลน์ (ไม่ต้องใช้กระดาษ)</p>
                                
                                <div class="input-group input-group-lg mb-0 shadow-sm" style="border-radius: 50px; overflow: hidden;">
                                    <span class="input-group-text bg-white border-warning text-warning"><i class="fas fa-link"></i></span>
                                    <input type="text" class="form-control bg-white border-warning text-muted" id="evalLink" value="<?php echo $eval_link; ?>" readonly>
                                    <button class="btn btn-warning text-dark fw-bold px-4" onclick="copyEvalLink()" style="border-radius: 0 50px 50px 0;">
                                        <i class="fas fa-copy me-1"></i> คัดลอก
                                    </button>
                                </div>
                            </div>
                        </div>

                        <script>
                        function copyEvalLink() {
                            var copyText = document.getElementById("evalLink");
                            copyText.select();
                            copyText.setSelectionRange(0, 99999);
                            navigator.clipboard.writeText(copyText.value).then(() => {
                                alert("✅ คัดลอกลิงก์ประเมินเรียบร้อยแล้ว!\nสามารถส่งให้พี่เลี้ยงทาง Line หรือ Email ได้เลยครับ");
                            });
                        }
                        </script>
                <?php 
                    else:
                        // ถ้าประเมินแล้ว กล่องลิงก์จะหายไป และโชว์กล่องนี้แทน
                ?>
                        <div class="card card-custom mt-4 mb-4 shadow-sm" style="border: 2px solid #bde0fe; background-color: #f0fbff; border-radius: 25px;">
                            <div class="card-body text-center p-4 p-md-5">
                                <div class="mb-3">
                                    <i class="fas fa-check-circle text-primary" style="font-size: 3.5rem;"></i>
                                </div>
                                <h4 class="fw-bold text-dark mb-2">ได้รับการประเมินเรียบร้อยแล้ว 🎉</h4>
                                <p class="text-muted mb-0">สถานประกอบการ หรือ อาจารย์ ได้ทำการประเมินผลการปฏิบัติงานของคุณเสร็จสิ้นแล้ว</p>
                            </div>
                        </div>
                <?php
                    endif;
                endif; 
                ?>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const phoneInput = document.getElementById('phone_input');
            const phoneIcon = document.getElementById('phone-icon');
            const phoneWarning = document.getElementById('phone-warning');

            if(phoneInput) {
                phoneInput.addEventListener('input', function() {
                    // ถ้าเริ่มพิมพ์ตัวเลข (ความยาวมากกว่า 0) ให้เอาสีแดงออก
                    if(this.value.length > 0) {
                        this.classList.remove('highlight-alert');
                        if(phoneIcon) phoneIcon.classList.remove('highlight-alert-icon');
                        if(phoneWarning) phoneWarning.style.display = 'none';
                    } else {
                        // ถ้าลบจนหมดให้กลับมาเตือนสีแดงใหม่
                        this.classList.add('highlight-alert');
                        if(phoneIcon) phoneIcon.classList.add('highlight-alert-icon');
                        if(phoneWarning) phoneWarning.style.display = 'block';
                    }
                });
            }
        });
    </script>
</body>

</html>