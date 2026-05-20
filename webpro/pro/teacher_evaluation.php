<?php
session_start();
require_once 'db.php';

// ตรวจสอบสิทธิ์
if (!isset($_SESSION['users_name']) || $_SESSION['user_level'] != 't' || !isset($_SESSION['session_token'])) {
    header("Location: logout.php"); exit();
}

$teacher_username = $conn->real_escape_string($_SESSION['users_name']);
$current_token = $_SESSION['session_token'];

$check_sql = "SELECT session_token FROM users WHERE users_name = '$teacher_username'";
$result = $conn->query($check_sql);
if ($result && $result->num_rows > 0) {
    if ($result->fetch_assoc()['session_token'] !== $current_token) { header("Location: logout.php"); exit(); }
} else { header("Location: logout.php"); exit(); }

$msg = ""; $msg_type = "";

// ==========================================
// 🚨 จัดการเมื่อมีการกด "ลบการประเมิน"
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_evaluation'])) {
    $student_id = $conn->real_escape_string($_POST['student_id']);
    
    // ลบข้อมูลออกจากตารางหลัก
    $del_sql = "DELETE FROM teacher_evaluations WHERE student_id = '$student_id'";
    if ($conn->query($del_sql)) {
        // บันทึกประวัติการลบลง Log
        $action_type = "ลบการประเมิน";
        $conn->query("INSERT INTO teacher_evaluations_log (student_id, total_score, overall_grade, action_type) 
                      VALUES ('$student_id', 0, '-', '$action_type')");
        
        // รีไดเรกต์เพื่อล้างฟอร์ม
        header("Location: teacher_evaluation.php?tab=evaluate&deleted=1&sid=$student_id");
        exit();
    } else {
        $msg = "❌ เกิดข้อผิดพลาดในการลบ: " . $conn->error;
        $msg_type = "danger";
    }
}

// ตรวจสอบสถานะการลบเพื่อแสดงข้อความ
if (isset($_GET['deleted']) && $_GET['deleted'] == '1' && isset($_GET['sid'])) {
    $msg = "🗑️ ลบข้อมูลการประเมินของนักศึกษารหัส " . htmlspecialchars($_GET['sid']) . " ออกจากระบบเรียบร้อยแล้ว!";
    $msg_type = "warning";
}

// ==========================================
// บันทึกข้อมูลการประเมิน (แก้ไขทับตารางหลัก + เก็บประวัติลง Log)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_evaluation'])) {
    $student_id = $conn->real_escape_string($_POST['student_id']);
    
    // รับค่าคะแนน
    $q1 = (int)$_POST['q1']; $q2 = (int)$_POST['q2']; $q3 = (int)$_POST['q3']; $q4 = (int)$_POST['q4']; 
    $q5 = (int)$_POST['q5']; $q6 = (int)$_POST['q6']; $q7 = (int)$_POST['q7']; $q8 = (int)$_POST['q8']; 
    $q9 = (int)$_POST['q9']; $q10 = (int)$_POST['q10']; $q11 = (int)$_POST['q11']; $q12 = (int)$_POST['q12']; 
    $q13 = (int)$_POST['q13']; $q14 = (int)$_POST['q14']; $q15 = (int)$_POST['q15']; $q16 = (int)$_POST['q16']; 
    $q17 = (int)$_POST['q17'];
    
    // คำนวณคะแนนรวม
    $total_score = $q1 + $q2 + $q3 + $q4 + $q5 + $q6 + $q7 + $q8 + $q9 + $q10 + $q11 + $q12 + $q13 + $q14 + $q15 + $q16 + $q17;

    // รับค่าข้อความ
    $strengths = $conn->real_escape_string($_POST['strengths']);
    $improvements = $conn->real_escape_string($_POST['improvements']);
    $hire_decision = $conn->real_escape_string($_POST['hire_decision']);
    $overall_grade = $conn->real_escape_string($_POST['overall_grade']);
    $comments = $conn->real_escape_string($_POST['comments']);

    // 1. ตรวจสอบว่าเคยมีข้อมูลในตารางหลักหรือไม่
    $check_exist = $conn->query("SELECT eval_id FROM teacher_evaluations WHERE student_id = '$student_id'");
    
    if ($check_exist->num_rows > 0) {
        // กรณีมีอยู่แล้ว -> UPDATE ทับข้อมูลเดิม
        $sql = "UPDATE teacher_evaluations SET 
                teacher_id = '$teacher_username',
                q1=$q1, q2=$q2, q3=$q3, q4=$q4, q5=$q5, q6=$q6, q7=$q7, q8=$q8, q9=$q9, q10=$q10, 
                q11=$q11, q12=$q12, q13=$q13, q14=$q14, q15=$q15, q16=$q16, q17=$q17, 
                total_score=$total_score, strengths='$strengths', improvements='$improvements', 
                hire_decision='$hire_decision', overall_grade='$overall_grade', comments='$comments',
                created_at=CURRENT_TIMESTAMP
                WHERE student_id='$student_id'";
                
        // ดึงจำนวนครั้งที่เคยบันทึกใน Log เพื่อตั้งชื่อสถานะ
        $log_c = $conn->query("SELECT COUNT(*) as cnt FROM teacher_evaluations_log WHERE student_id='$student_id'");
        $edit_times = $log_c->fetch_assoc()['cnt'];
        $action_type = "แก้ไขครั้งที่ " . $edit_times;
        
    } else {
        // กรณีไม่มี -> INSERT สร้างใหม่
        $sql = "INSERT INTO teacher_evaluations 
                (student_id, teacher_id, q1, q2, q3, q4, q5, q6, q7, q8, q9, q10, q11, q12, q13, q14, q15, q16, q17, total_score, strengths, improvements, hire_decision, overall_grade, comments) 
                VALUES 
                ('$student_id', '$teacher_username', $q1, $q2, $q3, $q4, $q5, $q6, $q7, $q8, $q9, $q10, $q11, $q12, $q13, $q14, $q15, $q16, $q17, $total_score, '$strengths', '$improvements', '$hire_decision', '$overall_grade', '$comments')";
        
        $action_type = "อาจารย์ประเมินใหม่";
    }

    // ทำการบันทึก
    if ($conn->query($sql)) {
        // 2. เมื่อบันทึกสำเร็จ ให้สร้างประวัติลงตาราง Log เสมอ
        $conn->query("INSERT INTO teacher_evaluations_log (student_id, total_score, overall_grade, action_type) 
                      VALUES ('$student_id', $total_score, '$overall_grade', '$action_type')");
        
        $msg = "✅ บันทึกข้อมูลสำเร็จ! (บันทึกเข้าระบบ: $action_type)";
        $msg_type = "success";
    } else {
        $msg = "❌ เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $conn->error;
        $msg_type = "danger";
    }
}

// ==========================================
// ค้นหานักศึกษาและดึงข้อมูลมาแสดงในฟอร์ม
// ==========================================
$search_student = null;
$existing_eval = null; 
if (isset($_GET['search_id'])) {
    $search_id = $conn->real_escape_string($_GET['search_id']);
    
    // 1. ดึงข้อมูลนักศึกษาและสถานะ (status)
    $s_sql = "SELECT u.users_name, u.full_name, c.company_name, si.status 
              FROM users u 
              LEFT JOIN student_internships si ON u.users_name = si.student_id AND si.internship_id = (SELECT MAX(internship_id) FROM student_internships WHERE student_id = u.users_name)
              LEFT JOIN companies c ON si.company_id = c.company_id
              WHERE u.users_name = '$search_id' AND u.user_level = 's'";
    $s_res = $conn->query($s_sql);
    
    if ($s_res && $s_res->num_rows > 0) {
        $search_student = $s_res->fetch_assoc();
        
        // 🚨 2. เช็คสถานะการฝึกงาน (ต้องเป็น 'active' หรือ 'finished' เท่านั้น)
        if (empty($search_student['status']) || !in_array($search_student['status'], ['active', 'finished'])) {
            
            // แสดงสถานะปัจจุบันให้เห็นด้วยว่าติดขัดที่สถานะอะไร
            $current_status = !empty($search_student['status']) ? $search_student['status'] : 'ยังไม่มีข้อมูลสถานะ';
            
            $msg = "❌ ไม่สามารถประเมินได้: นักศึกษารายนี้อยู่ในสถานะ <b>'{$current_status}'</b> (ต้องอยู่ในสถานะ 'active' หรือ 'finished' เท่านั้น)";
            $msg_type = "danger";
            $search_student = null; // รีเซ็ตตัวแปรเพื่อซ่อนฟอร์มกรอกคะแนน
            
        } else {
            // ถ้าสถานะถูกต้อง ค่อยให้เช็คว่ามีประวัติการประเมินหรือยัง
            $check_eval = $conn->query("SELECT * FROM teacher_evaluations WHERE student_id = '$search_id' LIMIT 1");
            if ($check_eval->num_rows > 0) {
                $existing_eval = $check_eval->fetch_assoc();
                $last_eval = date('d/m/Y H:i', strtotime($existing_eval['created_at']));
                $evaluator = ($existing_eval['teacher_id'] == 'COMPANY') ? 'สถานประกอบการ' : 'อาจารย์';
                
                $msg = "⚠️ <b>เคยถูกประเมินแล้วโดย: $evaluator</b><br>ประเมินไว้เมื่อ $last_eval ข้อมูลเดิมถูกดึงมาใส่ฟอร์มแล้ว คุณสามารถแก้ไขหรือเลือกลบทิ้งได้";
                $msg_type = "warning";
            }
        }
    } else {
        $msg = "❌ ไม่พบรหัสนักศึกษานี้ในระบบ";
        $msg_type = "danger";
    }
}

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'evaluate';

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
    <title>ประเมินผลนักศึกษา 📝</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg-color: #fffaf0; --primary-soft: #ffe066; --text-dark: #5c4d3c; }
        body { font-family: 'Prompt', 'Poppins', sans-serif; background: linear-gradient(135deg, var(--bg-color) 0%, #ffeed9 100%); color: var(--text-dark); min-height: 100vh; }
        .navbar-custom { background-color: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-bottom-left-radius: 20px; border-bottom-right-radius: 20px; }
        .nav-link-custom { color: var(--text-dark); font-weight: 600; padding: 10px 20px; border-radius: 50px; transition: 0.3s; margin-left: 5px;}
        .nav-link-custom:hover, .nav-link-custom.active { background-color: var(--primary-soft); color: #856404; }
        
        .main-card { background: white; border-radius: 25px; box-shadow: 0 10px 30px rgba(255, 191, 160, 0.2); border: none; padding: 30px; }
        .nav-pills .nav-link { border-radius: 50px; font-weight: 600; color: var(--text-dark); padding: 10px 25px; margin-right: 10px; background-color: #f8f9fa; border: 1px solid #e9ecef;}
        .nav-pills .nav-link.active { background-color: #0d9488; color: white; border-color: #0d9488; box-shadow: 0 4px 10px rgba(13, 148, 136, 0.3);}
        
        .eval-section { background-color: #fdfaf6; border: 1px solid #f0e6d2; border-radius: 15px; padding: 20px; margin-bottom: 25px; }
        .eval-section h5 { color: #0d9488; font-weight: 600; border-bottom: 2px solid #c3f0ca; padding-bottom: 10px; margin-bottom: 20px; }
        
        .form-control, .form-select { border-radius: 10px; border: 1px solid #e0e0e0; }
        .table-log th { background-color: #ffbfa0; color: white; border:none; }
        
        .score-box { background: white; border: 1px solid #e0e0e0; border-radius: 10px; padding: 15px; margin-bottom: 15px; transition: 0.2s;}
        .score-box:hover { border-color: #0d9488; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
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
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <?php $page_name = basename($_SERVER['PHP_SELF']); ?>
                    <li class="nav-item"><a class="nav-link nav-link-custom <?php echo ($page_name=='teacher_dashboard.php' || $page_name=='teacher_student_detail.php')?'active':''; ?>" href="teacher_dashboard.php"><i class="fas fa-users"></i> ข้อมูลนักศึกษา</a></li>
                    <li class="nav-item"><a class="nav-link nav-link-custom <?php echo $page_name=='teacher_evaluation.php'?'active':''; ?>" href="teacher_evaluation.php"><i class="fas fa-clipboard-list"></i> ประเมิน</a></li>
                    <li class="nav-item"><a class="nav-link nav-link-custom <?php echo $page_name=='teacher_rounds.php'?'active':''; ?>" href="teacher_rounds.php"><i class="fas fa-calendar-alt"></i> จัดการรอบฝึกงาน</a></li>
                    <li class="nav-item"><a class="nav-link nav-link-custom <?php echo $page_name=='teacher_relocation.php'?'active':''; ?>" href="teacher_relocation.php"><i class="fas fa-exchange-alt"></i> คำขอย้ายสถานที่</a></li>
                    <li class="nav-item"><a class="nav-link nav-link-custom <?php echo $page_name=='teacher_pending.php'?'active':''; ?>" href="teacher_pending.php"><i class="fas fa-clipboard-check"></i> อนุมัติจบฝึกงาน</a></li>
                    <li class="nav-item"><a class="nav-link nav-link-custom <?php echo $page_name=='teacher_import_students.php'?'active':''; ?>" href="teacher_import_students.php"><i class="fas fa-file-import"></i> เพิ่มรายชื่อนักศึกษาใหม่</a></li>
                    <li class="nav-item"><a class="nav-link nav-link-custom <?php echo $page_name=='teacher_approved.php'?'active':''; ?>" href="teacher_approved.php"><i class="fas fa-archive"></i> คลังข้อมูล</a></li>
                    <li class="nav-item ms-3">
                        <a href="logout.php" class="btn btn-danger rounded-pill px-4 shadow-sm d-flex align-items-center" style="font-weight: 500; padding-top: 8px; padding-bottom: 8px;">
                            <i class="fas fa-sign-out-alt me-2"></i> ออกจากระบบ
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4 pb-5">
        <?php if($msg != ""): ?>
            <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show shadow-sm rounded-pill px-4" role="alert">
                <?php echo $msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="main-card">
            <h3 class="fw-bold text-dark mb-4"><i class="fas fa-file-signature text-primary me-2"></i> ระบบประเมินผลการปฏิบัติงาน</h3>

            <ul class="nav nav-pills mb-4">
                <li class="nav-item"><a class="nav-link <?php echo $active_tab == 'evaluate' ? 'active' : ''; ?>" href="?tab=evaluate">กรอก/แก้ไข แบบประเมิน</a></li>
                <li class="nav-item"><a class="nav-link <?php echo $active_tab == 'log' ? 'active' : ''; ?>" href="?tab=log">ประวัติการประเมิน (Log)</a></li>
            </ul>

            <div class="tab-content">
                <?php 
                // =====================================
                // TAB 1: กรอกแบบประเมิน
                // =====================================
                if ($active_tab == 'evaluate'): 
                ?>
                <form method="GET" action="teacher_evaluation.php" class="mb-4 d-flex gap-2">
                    <input type="hidden" name="tab" value="evaluate">
                    <input type="text" name="search_id" class="form-control" placeholder="พิมพ์รหัสนักศึกษา 13 หลัก..." required style="max-width: 300px;" value="<?php echo htmlspecialchars($_GET['search_id'] ?? ''); ?>">
                    <button type="submit" class="btn btn-primary" style="border-radius: 10px;"><i class="fas fa-search"></i> ค้นหานักศึกษา</button>
                </form>

                <?php if ($search_student): ?>
                    <div class="alert alert-info rounded-4 border-0 shadow-sm mb-4">
                        <h5 class="fw-bold mb-1"><i class="fas fa-user-graduate me-2"></i>นักศึกษา: <?php echo htmlspecialchars($search_student['full_name']); ?> (<?php echo htmlspecialchars($_GET['search_id']); ?>)</h5>
                        <p class="mb-0 text-muted small"><i class="fas fa-building me-2"></i>สถานที่ฝึกงาน: <?php echo htmlspecialchars($search_student['company_name'] ?? 'ไม่ระบุสถานที่'); ?></p>
                    </div>

                    <form method="POST" action="teacher_evaluation.php?tab=evaluate">
                        <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($_GET['search_id']); ?>">

                        <div class="eval-section">
                            <h5>1. ผลสำเร็จของงาน (คะแนนเต็ม 40)</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="fw-bold">1. ปริมาณงาน (เต็ม 20)</label>
                                    <p class="small text-muted mb-2">ปริมาณงานที่ปฏิบัติสำเร็จ ตามหน้าที่หรือตามที่ได้รับมอบหมายภายในเวลา</p>
                                    <input type="number" name="q1" class="form-control calc-score" min="0" max="20" required placeholder="0-20" value="<?php echo $existing_eval['q1'] ?? ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="fw-bold">2. คุณภาพงาน (เต็ม 20)</label>
                                    <p class="small text-muted mb-2">ทำงานถูกต้องสมบูรณ์ ประณีต ไม่เกิดปัญหา งานไม่ค้าง เสร็จทันเวลา</p>
                                    <input type="number" name="q2" class="form-control calc-score" min="0" max="20" required placeholder="0-20" value="<?php echo $existing_eval['q2'] ?? ''; ?>">
                                </div>
                            </div>
                        </div>

                        <div class="eval-section">
                            <h5>2. ความรู้ความสามารถ (ข้อละ 4 คะแนน)</h5>
                            <p class="small text-danger fw-bold">1=ควรปรับปรุง, 2=พอใช้, 3=ดี, 4=ดีมาก</p>
                            <?php 
                            $questions_2 = [
                                'q3' => 'ความรู้ความสามารถทางวิชาการ', 'q4' => 'ความสามารถในการเรียนรู้และประยุกต์วิชาการ',
                                'q5' => 'ความรู้ความชำนาญด้านปฏิบัติการ', 'q6' => 'วิจารณญาณและการตัดสินใจ',
                                'q7' => 'ทักษะการสื่อสาร', 'q8' => 'ความสามารถทางภาษาต่างประเทศ/วัฒนธรรม',
                                'q9' => 'ความเหมาะสมกับตำแหน่งงานที่ได้รับมอบหมาย'
                            ];
                            foreach($questions_2 as $key => $title) { echo buildRadioQuestion($key, $title, $existing_eval[$key] ?? null); }
                            ?>
                        </div>

                        <div class="eval-section">
                            <h5>3. ความรับผิดชอบต่อหน้าที่ (ข้อละ 4 คะแนน)</h5>
                            <?php 
                            $questions_3 = [
                                'q10' => 'ความสามารถเริ่มต้นทำงานได้ด้วยตนเอง', 'q11' => 'ความรับผิดชอบและเป็นผู้ที่ไว้วางใจได้',
                                'q12' => 'ความสนใจ ความอุตสาหะในการทำงาน', 'q13' => 'การตอบสนองต่อการสั่งการ'
                            ];
                            foreach($questions_3 as $key => $title) { echo buildRadioQuestion($key, $title, $existing_eval[$key] ?? null); }
                            ?>
                        </div>

                        <div class="eval-section">
                            <h5>4. ลักษณะส่วนบุคคล (ข้อละ 4 คะแนน)</h5>
                            <?php 
                            $questions_4 = [
                                'q14' => 'บุคลิกภาพและการวางตัว', 'q15' => 'มนุษยสัมพันธ์',
                                'q16' => 'ความมีระเบียบวินัย ปฏิบัติตามวัฒนธรรมขององค์กร', 'q17' => 'คุณธรรมและจริยธรรม'
                            ];
                            foreach($questions_4 as $key => $title) { echo buildRadioQuestion($key, $title, $existing_eval[$key] ?? null); }
                            ?>
                        </div>

                        <div class="eval-section">
                            <h5>5. ข้อคิดเห็นที่เป็นประโยชน์</h5>
                            <div class="mb-3">
                                <label class="fw-bold">จุดเด่นของนักศึกษา</label>
                                <textarea name="strengths" class="form-control" rows="2"><?php echo htmlspecialchars($existing_eval['strengths'] ?? ''); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="fw-bold">ข้อควรปรับปรุงของนักศึกษา</label>
                                <textarea name="improvements" class="form-control" rows="2"><?php echo htmlspecialchars($existing_eval['improvements'] ?? ''); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="fw-bold">หากสำเร็จการศึกษาแล้ว ท่านจะรับเข้าทำงานหรือไม่?</label>
                                <select name="hire_decision" class="form-select w-50" required>
                                    <option value="" disabled <?php echo empty($existing_eval['hire_decision']) ? 'selected' : ''; ?>>-- กรุณาเลือก --</option>
                                    <option value="รับ" <?php echo (($existing_eval['hire_decision'] ?? '') == 'รับ') ? 'selected' : ''; ?>>รับ</option>
                                    <option value="ไม่แน่ใจ" <?php echo (($existing_eval['hire_decision'] ?? '') == 'ไม่แน่ใจ') ? 'selected' : ''; ?>>ไม่แน่ใจ</option>
                                    <option value="ไม่รับ" <?php echo (($existing_eval['hire_decision'] ?? '') == 'ไม่รับ') ? 'selected' : ''; ?>>ไม่รับ</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="fw-bold">สรุปภาพรวมคุณภาพนักศึกษาในระดับใด?</label>
                                <select name="overall_grade" class="form-select w-50" required>
                                    <option value="" disabled <?php echo empty($existing_eval['overall_grade']) ? 'selected' : ''; ?>>-- กรุณาเลือก --</option>
                                    <option value="ควรปรับปรุง" <?php echo (($existing_eval['overall_grade'] ?? '') == 'ควรปรับปรุง') ? 'selected' : ''; ?>>ควรปรับปรุง</option>
                                    <option value="พอใช้" <?php echo (($existing_eval['overall_grade'] ?? '') == 'พอใช้') ? 'selected' : ''; ?>>พอใช้</option>
                                    <option value="ดี" <?php echo (($existing_eval['overall_grade'] ?? '') == 'ดี') ? 'selected' : ''; ?>>ดี</option>
                                    <option value="ดีมาก" <?php echo (($existing_eval['overall_grade'] ?? '') == 'ดีมาก') ? 'selected' : ''; ?>>ดีมาก</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="fw-bold">ข้อคิดเห็นเพิ่มเติม</label>
                                <textarea name="comments" class="form-control" rows="3"><?php echo htmlspecialchars($existing_eval['comments'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="card shadow-lg border-success sticky-bottom mb-4">
                            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
                                <h4 class="text-success fw-bold mb-0">คะแนนรวมสุทธิ: <span id="total_score_display"><?php echo $existing_eval['total_score'] ?? '0'; ?></span> / 100</h4>
                                
                                <div class="d-flex gap-2">
                                    <?php if ($existing_eval): ?>
                                        <button type="submit" name="delete_evaluation" class="btn btn-outline-danger btn-lg px-4" style="border-radius: 50px;" onclick="return confirm('คุณแน่ใจหรือไม่ที่จะลบข้อมูลการประเมินนี้? ข้อมูลจะสูญหายถาวร');">
                                            <i class="fas fa-trash-alt me-2"></i> ลบทิ้ง
                                        </button>
                                    <?php endif; ?>

                                    <button type="submit" name="save_evaluation" class="btn btn-success btn-lg px-5" style="border-radius: 50px;" onclick="return confirm('ยืนยันการบันทึกข้อมูลหรือไม่?');">
                                        <?php echo $existing_eval ? '<i class="fas fa-edit me-2"></i> ยืนยันการแก้ไขข้อมูล' : '<i class="fas fa-save me-2"></i> บันทึกผลการประเมิน'; ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>

                <?php 
                // =====================================
                // TAB 2: ประวัติการประเมิน (Log)
                // =====================================
                elseif ($active_tab == 'log'): 
                ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-log">
                                <tr>
                                    <th class="ps-3">วัน/เวลาที่บันทึก</th>
                                    <th>ประเภท</th>
                                    <th>รหัสนักศึกษา</th>
                                    <th>ชื่อนักศึกษา</th>
                                    <th class="text-center">คะแนนรวม</th>
                                    <th class="text-center">เกรดภาพรวม</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // คิวรีประวัติจากตาราง Log แยกออกมา
                                $log_sql = "SELECT l.*, u.full_name 
                                            FROM teacher_evaluations_log l 
                                            JOIN users u ON l.student_id = u.users_name 
                                            ORDER BY l.created_at DESC LIMIT 50";
                                $log_res = $conn->query($log_sql);
                                
                                if ($log_res && $log_res->num_rows > 0) {
                                    while($l = $log_res->fetch_assoc()) {
                                        
                                        // แยกสีของป้ายกำกับ
                                        if (strpos($l['action_type'], 'ลบ') !== false) {
                                            $action_badge = "<span class='badge bg-danger'><i class='fas fa-trash-alt me-1'></i> ".$l['action_type']."</span>";
                                        } elseif (strpos($l['action_type'], 'ใหม่') !== false) {
                                            $action_badge = "<span class='badge bg-success'><i class='fas fa-plus-circle me-1'></i> ".$l['action_type']."</span>";
                                        } else {
                                            $action_badge = "<span class='badge bg-warning text-dark border'><i class='fas fa-edit me-1'></i> ".$l['action_type']."</span>";
                                        }

                                        echo "<tr>";
                                        echo "<td class='ps-3 text-muted small'><i class='far fa-clock me-1'></i>".date('d/m/Y H:i', strtotime($l['created_at']))."</td>";
                                        echo "<td>".$action_badge."</td>";
                                        echo "<td class='fw-bold'>".$l['student_id']."</td>";
                                        echo "<td>".htmlspecialchars($l['full_name'])."</td>";
                                        echo "<td class='text-center fw-bold text-success'>".$l['total_score']."</td>";
                                        echo "<td class='text-center'><span class='badge bg-info text-dark rounded-pill'>".$l['overall_grade']."</span></td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='6' class='text-center py-5 text-muted'>ยังไม่มีประวัติการประเมินผล</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function calculateTotal() {
            let total = 0;
            const inputs = document.querySelectorAll('.calc-score');
            inputs.forEach(input => {
                if(input.type === 'number') {
                    total += Number(input.value) || 0;
                } else if(input.type === 'radio' && input.checked) {
                    total += Number(input.value) || 0;
                }
            });
            document.getElementById('total_score_display').innerText = total;
        }
        
        window.onload = function() { calculateTotal(); };
        document.querySelectorAll('.calc-score').forEach(input => {
            input.addEventListener('input', calculateTotal);
            input.addEventListener('change', calculateTotal);
        });
    </script>
</body>
</html>

<?php
function buildRadioQuestion($name, $title, $current_val = null) {
    $html = "<div class='score-box'><label class='fw-bold mb-2'>$title</label><div class='d-flex gap-4'>";
    for($i=1; $i<=4; $i++) {
        $checked = ($current_val == $i) ? "checked" : "";
        $html .= "<div class='form-check'>
                    <input class='form-check-input calc-score' type='radio' name='$name' id='{$name}_$i' value='$i' required $checked>
                    <label class='form-check-label' for='{$name}_$i'>$i</label>
                  </div>";
    }
    $html .= "</div></div>";
    return $html;
}
?>