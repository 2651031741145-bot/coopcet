<?php
session_start();
require_once 'db.php';

// ตรวจสอบสิทธิ์ (ต้องเป็นอาจารย์ 't')
if (!isset($_SESSION['users_name']) || $_SESSION['user_level'] != 't' || !isset($_SESSION['session_token'])) {
    header("Location: logout.php");
    exit();
}

$teacher_username = $conn->real_escape_string($_SESSION['users_name']);
$current_token = $_SESSION['session_token'];

$check_sql = "SELECT session_token FROM users WHERE users_name = '$teacher_username'";
$result = $conn->query($check_sql);
if ($result && $result->num_rows > 0) {
    if ($result->fetch_assoc()['session_token'] !== $current_token) {
        header("Location: logout.php");
        exit();
    }
} else {
    header("Location: logout.php");
    exit();
}

// ==========================================
// ระบบดาวน์โหลดไฟล์ตัวอย่าง Excel (.xls)
// ==========================================
if (isset($_GET['download_sample'])) {
    ob_clean();
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=sample_students.xls");

    // สร้างตาราง HTML ปลอมตัวเป็นไฟล์ Excel เพื่อให้อ่านภาษาไทยได้เป๊ะๆ
    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head><meta http-equiv="content-type" content="application/vnd.ms-excel; charset=UTF-8"></head>';
    echo '<body>';
    echo '<table border="1">';
    echo '<tr><td style="background-color: #fce4d6; font-weight: bold;">รหัสนักศึกษา</td><td style="background-color: #d9e1f2; font-weight: bold;">ชื่อ-สกุล</td></tr>';

    // ทริค: ใช้ mso-number-format:'\@'; เพื่อบังคับให้ Excel มองรหัสนักศึกษาเป็น "ข้อความ" (Text)
     echo '<tr><td style="mso-number-format:\'\@\';">2671031741101</td><td>นายสมจิต จงจอหอ</td></tr>';
    echo '<tr><td style="mso-number-format:\'\@\';">2671031741102</td><td>นายสมคิด จิตดี</td></tr>';
    echo '</table>';
    echo '</body></html>';
    exit();
}

$msg = "";
$msg_type = "";

// ==========================================
// จัดการอัปโหลดและประมวลผลไฟล์ CSV
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    $file_mimes = array('text/x-comma-separated-values', 'text/comma-separated-values', 'application/octet-stream', 'application/vnd.ms-excel', 'application/x-csv', 'text/x-csv', 'text/csv', 'application/csv', 'application/excel', 'application/vnd.msexcel', 'text/plain');

    // ตรวจสอบว่าเป็นไฟล์ CSV จริงหรือไม่
    if (!empty($_FILES['csv_file']['name']) && in_array($_FILES['csv_file']['type'], $file_mimes)) {

        if (is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
            $csv_file = fopen($_FILES['csv_file']['tmp_name'], 'r');

            // ข้าม BOM ถ้ามี (สำหรับไฟล์ UTF-8 ที่เซฟจาก Excel)
            fseek($csv_file, 0);
            $bom = fread($csv_file, 3);
            if ($bom != "\xEF\xBB\xBF") {
                rewind($csv_file);
            }

            $success_count = 0;
            $duplicate_count = 0;
            $error_count = 0;

            // อ่านทีละบรรทัด
            while (($row = fgetcsv($csv_file, 10000, ",")) !== FALSE) {
                // คาดหวังว่า Column 0 = รหัสนักศึกษา, Column 1 = ชื่อ-นามสกุล
                $student_id = trim($row[0]);
                $full_name = trim($row[1] ?? '');

                // ตรวจสอบว่าเป็นรหัสนักศึกษาจริงๆ (ข้ามแถวหัวข้อ และเช็คความยาวรหัส)
                if (is_numeric($student_id) && strlen($student_id) >= 10 && !empty($full_name)) {

                    // 1. ดึงปีการศึกษาจากรหัส (เช่น 26610... -> ดึง 66 -> +2500 = 2566)
                    $year_code = substr($student_id, 1, 2);
                    $academic_year = 2500 + (int) $year_code;

                    // 2. ตั้งรหัสผ่านเป็นรหัสนักศึกษา (เข้ารหัส Hash เพื่อความปลอดภัย)
                    $password_hashed = password_hash($student_id, PASSWORD_DEFAULT);

                    // 3. ป้องกัน SQL Injection
                    $safe_student_id = $conn->real_escape_string($student_id);
                    $safe_full_name = $conn->real_escape_string($full_name);

                    // เช็คว่ามีรหัสนี้ในระบบแล้วหรือยัง (เช็ครายชื่อซ้ำ)
                    $check_exist = $conn->query("SELECT users_name FROM users WHERE users_name = '$safe_student_id'");

                    if ($check_exist->num_rows == 0) {
                        // ถ้ายังไม่มี ค่อยบันทึกลงฐานข้อมูล (เพิ่มเฉพาะชื่อใหม่)
                        $insert_sql = "INSERT INTO users (users_name, full_name, academic_year, password, user_level) 
                                       VALUES ('$safe_student_id', '$safe_full_name', '$academic_year', '$password_hashed', 's')";
                        if ($conn->query($insert_sql)) {
                            $success_count++;
                        } else {
                            $error_count++;
                        }
                    } else {
                        // ถ้ารหัสมีอยู่แล้ว ให้นับเป็นข้อมูลซ้ำและข้ามไป
                        $duplicate_count++;
                    }
                }
            }
            fclose($csv_file);

            if ($success_count > 0) {
                $msg = "✅ นำเข้ารายชื่อใหม่สำเร็จ <b>$success_count</b> รายการ " . ($duplicate_count > 0 ? "<br><small>(พบรหัสซ้ำและข้ามไป $duplicate_count รายการ)</small>" : "");
                $msg_type = "success";
            } elseif ($duplicate_count > 0) {
                $msg = "⚠️ ข้อมูลที่อัปโหลดมีในระบบอยู่แล้วทั้งหมด ($duplicate_count รายการ) ไม่มีรายชื่อใหม่ถูกเพิ่ม";
                $msg_type = "warning";
            } else {
                $msg = "❌ ไม่พบข้อมูลที่สามารถนำเข้าได้ กรุณาตรวจสอบรูปแบบไฟล์ CSV ให้ตรงกับไฟล์ตัวอย่าง";
                $msg_type = "danger";
            }
        } else {
            $msg = "❌ เกิดข้อผิดพลาดในการอัปโหลดไฟล์";
            $msg_type = "danger";
        }
    } else {
        $msg = "❌ กรุณาอัปโหลดไฟล์นามสกุล .csv เท่านั้น";
        $msg_type = "danger";
    }
}

// ==========================================
// ดึงชื่ออาจารย์ที่ล็อกอินอยู่มาแสดงผล
// ==========================================
$logged_in_user = $conn->real_escape_string($_SESSION['users_name']);
$t_name_query = $conn->query("SELECT full_name FROM users WHERE users_name = '$logged_in_user'");
$teacher_fullname = ($t_name_query && $t_name_query->num_rows > 0) ? $t_name_query->fetch_assoc()['full_name'] : 'อาจารย์';
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>นำเข้ารายชื่อนักศึกษา 📥</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Prompt:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-color: #fffaf0;
            --primary-soft: #ffe066;
            --text-dark: #5c4d3c;
        }

        body {
            font-family: 'Prompt', 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--bg-color) 0%, #ffeed9 100%);
            color: var(--text-dark);
            min-height: 100vh;
        }

        .navbar-custom {
            background-color: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border-bottom-left-radius: 20px;
            border-bottom-right-radius: 20px;
        }

         .nav-link-custom { color: var(--text-dark); font-weight: 600; padding: 10px 20px; border-radius: 50px; transition: 0.3s; margin-left: 5px; font-size: 0.95rem;}
        .nav-link-custom:hover, .nav-link-custom.active { background-color: var(--primary-soft); color: #856404; }
        

        .main-card {
            background: white;
            border-radius: 25px;
            box-shadow: 0 10px 30px rgba(255, 191, 160, 0.2);
            border: none;
            padding: 40px;
        }

        .upload-area {
            border: 2px dashed #ffbfa0;
            border-radius: 20px;
            padding: 40px 20px;
            text-align: center;
            background-color: #fffdf5;
            transition: all 0.3s;
            cursor: pointer;
        }

        .upload-area:hover {
            background-color: #fffaf0;
            border-color: #ff9800;
            transform: translateY(-2px);
        }

        .upload-icon {
            font-size: 3rem;
            color: #ff9800;
            margin-bottom: 15px;
        }

        .btn-upload {
            background-color: #0d9488;
            color: white;
            border-radius: 50px;
            padding: 12px 35px;
            font-weight: 600;
            transition: 0.3s;
            border: none;
            font-size: 1.1rem;
        }

        .btn-upload:hover {
            background-color: #0f766e;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13, 148, 136, 0.3);
        }

        .instruction-box {
            background-color: #f8f9fa;
            border-left: 4px solid #ff9800;
            border-radius: 10px;
            padding: 25px;
            margin-top: 30px;
        }

        .instruction-box ol li {
            margin-bottom: 8px;
            color: #5c4d3c;
        }

        .download-btn {
            background-color: #fff4e6;
            color: #ff9800;
            border: 1px solid #ffbfa0;
            border-radius: 50px;
            font-weight: 500;
            padding: 8px 20px;
            transition: 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .download-btn:hover {
            background-color: #ffe066;
            color: #856404;
            border-color: #ffe066;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-custom sticky-top py-3">
        <div class="container-fluid px-4">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="teacher_dashboard.php">
                <i class="fas fa-chalkboard-teacher text-warning me-2"></i> ระบบอาจารย์ 🌻
                <span class="ms-3 fs-6 fw-normal text-muted border-start ps-3" style="font-size: 0.95rem !important;">
                    <i class="fas fa-user-circle text-secondary me-1"></i> <?php echo htmlspecialchars($teacher_fullname); ?>
                </span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"><span
                    class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <?php $page = basename($_SERVER['PHP_SELF']); ?>
                    <li class="nav-item"><a
                            class="nav-link nav-link-custom <?php echo ($page == 'teacher_dashboard.php' || $page == 'teacher_student_detail.php') ? 'active' : ''; ?>"
                            href="teacher_dashboard.php"><i class="fas fa-users"></i> ข้อมูลนักศึกษา</a></li>

                    <li class="nav-item"><a class="nav-link nav-link-custom <?php echo $page_name=='teacher_evaluation.php'?'active':''; ?>" href="teacher_evaluation.php"><i class="fas fa-clipboard-list"></i> ประเมินบริษัท</a></li>
                 <li class="nav-item"><a class="nav-link nav-link-custom <?php echo $page_name=='teacher_report_evaluation.php'?'active':''; ?>" href="teacher_report_evaluation.php"><i class="fas fa-clipboard-list"></i> ประเมินอาจารย์</a></li>

                    <li class="nav-item"><a
                            class="nav-link nav-link-custom <?php echo $page == 'teacher_rounds.php' ? 'active' : ''; ?>"
                            href="teacher_rounds.php"><i class="fas fa-calendar-alt"></i> จัดการรอบฝึกงาน</a></li>
                    <li class="nav-item"><a
                            class="nav-link nav-link-custom <?php echo $page == 'teacher_relocation.php' ? 'active' : ''; ?>"
                            href="teacher_relocation.php"><i class="fas fa-exchange-alt"></i> คำขอย้ายสถานที่</a></li>
                    <li class="nav-item"><a
                            class="nav-link nav-link-custom <?php echo $page == 'teacher_pending.php' ? 'active' : ''; ?>"
                            href="teacher_pending.php"><i class="fas fa-clipboard-check"></i> อนุมัติจบฝึกงาน</a></li>
                    <li class="nav-item"><a
                            class="nav-link nav-link-custom <?php echo $page == 'teacher_import_students.php' ? 'active' : ''; ?>"
                            href="teacher_import_students.php"><i class="fas fa-file-import"></i>
                            เพิ่มรายชื่อนักศึกษาใหม่</a></li>
                    <li class="nav-item"><a
                            class="nav-link nav-link-custom <?php echo $page == 'teacher_approved.php' ? 'active' : ''; ?>"
                            href="teacher_approved.php"><i class="fas fa-archive"></i> คลังข้อมูล</a></li>
                    <li class="nav-item ms-3">
                        <a href="logout.php" class="btn btn-danger rounded-pill px-4 shadow-sm d-flex align-items-center" style="font-weight: 500; padding-top: 8px; padding-bottom: 8px;">
                            <i class="fas fa-sign-out-alt me-2"></i> ออกจากระบบ
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-5 pb-5" style="max-width: 800px;">

        <?php if ($msg != ""): ?>
            <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show shadow-sm rounded-pill px-4"
                role="alert">
                <?php echo $msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="main-card">
            <h3 class="fw-bold text-dark mb-4 text-center"><i class="fas fa-file-csv text-success me-2"></i>
                นำเข้ารายชื่อนักศึกษา (CSV)</h3>

            <div class="instruction-box mb-4 shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-list-ol text-warning me-2"></i>
                        วิธีการใช้งานและคำแนะนำ</h5>
                    <a href="?download_sample=1" class="download-btn shadow-sm text-decoration-none"
                        style="background-color: #e6f4ea; color: #1e8e3e; border-color: #cce8d6;">
                        <i class="fas fa-file-excel me-1"></i> โหลดไฟล์ตัวอย่าง Excel
                    </a>
                </div>
                <ol class="mb-0 small" style="line-height: 1.8;">
                    <li><b>ดาวน์โหลดไฟล์ตัวอย่าง</b> จากปุ่มด้านบน เปิดด้วยโปรแกรม Excel เพื่อดูโครงสร้าง</li>
                    <li><b>กรอกข้อมูลนักศึกษา:</b> โดยให้ <span class="text-danger fw-bold">คอลัมน์ A คือ
                            "รหัสนักศึกษา"</span> และ <span class="text-primary fw-bold">คอลัมน์ B คือ
                            "ชื่อ-นามสกุล"</span></li>
                    <li class="p-2 mt-2 mb-2 bg-white rounded border border-warning">
                        <b class="text-danger"><i class="fas fa-exclamation-triangle"></i> สำคัญมาก!
                            ขั้นตอนการบันทึกไฟล์:</b><br>
                        เมื่อกรอกข้อมูลใน Excel เสร็จแล้ว ให้ไปที่ <b>File > Save As (บันทึกเป็น)</b> <br>
                        แล้วเปลี่ยนช่อง <i>Save as type</i> ให้เป็น <b class="text-danger text-decoration-underline">CSV
                            UTF-8 (Comma delimited)
                            (*.csv)</b> เท่านั้น
                    </li>
                    <li><b>อัปโหลดไฟล์:</b> นำไฟล์นามสกุล <b>.csv</b> ที่ได้จากข้อ 3 มาลากวางหรืออัปโหลดที่ช่องด้านล่าง
                    </li>
                    <li><b>ระบบจะตรวจสอบและเพิ่มข้อมูล:</b> รหัสผ่านเริ่มต้นจะถูกตั้งเป็น "รหัสนักศึกษา" ให้อัตโนมัติ
                        (ข้ามรายชื่อที่ซ้ำ)</li>
                </ol>
            </div>

            <form action="" method="POST" enctype="multipart/form-data">
                <div class="upload-area mb-4 shadow-sm" onclick="document.getElementById('csv_file').click()">
                    <i class="fas fa-cloud-upload-alt upload-icon"></i>
                    <h5 class="fw-bold text-dark mb-2">คลิกเพื่อเลือกไฟล์ หรือ ลากไฟล์ .csv มาวางที่นี่</h5>
                    <p class="text-muted mb-0">รองรับเฉพาะไฟล์ .csv ที่จัดรูปแบบตามคำแนะนำเท่านั้น</p>
                    <input type="file" name="csv_file" id="csv_file" class="d-none" accept=".csv" required
                        onchange="updateFileName(this)">
                </div>

                <div id="file-name-display"
                    class="text-center fw-bold text-success mb-4 d-none p-2 bg-light rounded-pill border">
                    <i class="fas fa-file-alt me-2"></i> ไฟล์ที่เลือก: <span id="file-name-text"></span>
                </div>

                <div class="text-center">
                    <button type="submit" class="btn btn-upload shadow"><i class="fas fa-save me-2"></i>
                        อัปโหลดและเพิ่มรายชื่อเข้าสู่ระบบ</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateFileName(input) {
            const display = document.getElementById('file-name-display');
            const text = document.getElementById('file-name-text');
            if (input.files && input.files[0]) {
                text.textContent = input.files[0].name;
                display.classList.remove('d-none');
            } else {
                display.classList.add('d-none');
            }
        }
    </script>
</body>

</html>