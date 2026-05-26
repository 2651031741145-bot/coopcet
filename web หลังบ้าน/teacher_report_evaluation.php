<?php
session_start();
require_once 'db.php';

// ตรวจสอบสิทธิ์อาจารย์
if (!isset($_SESSION['users_name']) || $_SESSION['user_level'] != 't' || !isset($_SESSION['session_token'])) {
    header("Location: logout.php"); exit();
}

$teacher_username = $conn->real_escape_string($_SESSION['users_name']);
$msg = ""; $msg_type = "";

// ดึงชื่ออาจารย์ที่ล็อกอิน
$t_name_query = $conn->query("SELECT full_name FROM users WHERE users_name = '$teacher_username'");
$teacher_fullname = ($t_name_query && $t_name_query->num_rows > 0) ? $t_name_query->fetch_assoc()['full_name'] : 'อาจารย์';

// ==========================================
// 🚨 Action: ส่งออกข้อมูลคะแนนรายงานเป็นไฟล์ Excel (แยกตามปี)
// ==========================================
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    $export_year = isset($_GET['export_year']) ? $conn->real_escape_string($_GET['export_year']) : '';
    
    ob_clean();
    $filename = "Report_Evaluation_Scores_" . ($export_year ? "Year_$export_year" : "All") . "_" . date('Ymd') . ".xls";
    
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=$filename");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head><meta http-equiv="content-type" content="application/vnd.ms-excel; charset=UTF-8"></head>';
    echo '<body>';
    
    if ($export_year) {
        echo '<h3 style="font-family: Arial;">รายงานคะแนนประเมินรายงานและการนำเสนอ ปีการศึกษา: ' . htmlspecialchars($export_year) . '</h3>';
    }
    
    echo '<table border="1" style="font-family: Arial, sans-serif;">';
    
    // สร้างหัวตาราง Excel
    echo '<tr>
            <th style="background-color: #fff2cc;">รหัสนักศึกษา</th>
            <th style="background-color: #fff2cc;">ชื่อ-นามสกุล</th>
            <th style="background-color: #fff2cc;">ปีการศึกษา</th>
            <th style="background-color: #fff2cc;">หัวข้อรายงาน / โครงงาน</th>
            <th style="background-color: #fce4d6;">คะแนนส่วนที่ 1 รายงาน (เต็ม 100)</th>
            <th style="background-color: #e2efda;">คะแนนส่วนที่ 2 นำเสนอ (เต็ม 100)</th>
            <th style="background-color: #ddebf7;">คะแนนรวมสุทธิ (เต็ม 200)</th>
            <th style="background-color: #f2f2f2;">ข้อคิดเห็นเพิ่มเติม</th>
            <th style="background-color: #f2f2f2;">วันที่ประเมิน</th>
          </tr>';

    // กรองข้อมูลตามปีการศึกษาที่เลือก
    $where_clause = "";
    if (!empty($export_year)) {
        $where_clause = " WHERE u.academic_year = '$export_year' ";
    }

    // ดึงข้อมูลคะแนนทั้งหมด
    $export_sql = "SELECT tr.*, u.full_name, u.academic_year 
                   FROM teacher_report_evaluations tr 
                   JOIN users u ON tr.student_id = u.users_name 
                   $where_clause
                   ORDER BY tr.created_at DESC";
    $export_res = $conn->query($export_sql);

    if ($export_res && $export_res->num_rows > 0) {
        while($row = $export_res->fetch_assoc()) {
            $total_score = $row['s1_total'] + $row['s2_total'];
            echo '<tr>';
            echo '<td style="mso-number-format:\'\@\';">' . htmlspecialchars($row['student_id']) . '</td>';
            echo '<td>' . htmlspecialchars($row['full_name']) . '</td>';
            echo '<td style="text-align: center;">' . htmlspecialchars($row['academic_year']) . '</td>';
            echo '<td>' . htmlspecialchars($row['report_title_th']) . '</td>';
            echo '<td style="text-align: center;">' . $row['s1_total'] . '</td>';
            echo '<td style="text-align: center;">' . $row['s2_total'] . '</td>';
            echo '<td style="font-weight: bold; text-align: center; color: #0d6efd;">' . $total_score . '</td>';
            echo '<td>' . htmlspecialchars($row['comments']) . '</td>';
            echo '<td>' . date('d/m/Y H:i', strtotime($row['created_at'])) . '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="9" style="text-align: center;">ยังไม่มีข้อมูลการประเมินรายงานในระบบสำหรับปีการศึกษานี้</td></tr>';
    }
    
    echo '</table></body></html>';
    exit();
}

// ==========================================
// 🚨 จัดการเมื่อมีการกด "ลบการประเมิน"
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_report_eval'])) {
    $student_id = $conn->real_escape_string($_POST['student_id']);
    
    $del_sql = "DELETE FROM teacher_report_evaluations WHERE student_id = '$student_id'";
    if ($conn->query($del_sql)) {
        $action_type = "ลบการประเมินรายงาน";
        $conn->query("INSERT INTO teacher_report_evaluations_log (student_id, s1_total, s2_total, action_type) 
                      VALUES ('$student_id', 0, 0, '$action_type')");
        
        header("Location: teacher_report_evaluation.php?tab=evaluate&deleted=1&sid=$student_id");
        exit();
    } else {
        $msg = "❌ เกิดข้อผิดพลาดในการลบ: " . $conn->error;
        $msg_type = "danger";
    }
}

if (isset($_GET['deleted']) && $_GET['deleted'] == '1' && isset($_GET['sid'])) {
    $msg = "🗑️ ลบข้อมูลการประเมินรายงานของรหัส " . htmlspecialchars($_GET['sid']) . " ออกจากระบบแล้ว!";
    $msg_type = "warning";
}

// ==========================================
// บันทึกข้อมูลการประเมินรายงาน (ทับตารางหลัก + ลง Log)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_report_eval'])) {
    $student_id = $conn->real_escape_string($_POST['student_id']);
    $report_title = $conn->real_escape_string($_POST['report_title_th']);
    
    // รับคะแนนส่วนที่ 1
    $s1_scores = [];
    for($i=1; $i<=12; $i++) { $s1_scores["s1_q$i"] = (int)$_POST["s1_q$i"]; }
    $s1_total = array_sum($s1_scores);

    // รับคะแนนส่วนที่ 2
    $s2_scores = [];
    for($i=1; $i<=10; $i++) { $s2_scores["s2_q$i"] = (int)$_POST["s2_q$i"]; }
    $s2_total = array_sum($s2_scores);

    $comments = $conn->real_escape_string($_POST['comments']);

    // ตรวจสอบว่าเคยประเมินหรือยัง
    $check = $conn->query("SELECT report_eval_id FROM teacher_report_evaluations WHERE student_id = '$student_id'");
    
    if ($check->num_rows > 0) {
        $sql = "UPDATE teacher_report_evaluations SET 
                report_title_th='$report_title', 
                s1_q1={$s1_scores['s1_q1']}, s1_q2={$s1_scores['s1_q2']}, s1_q3={$s1_scores['s1_q3']}, 
                s1_q4={$s1_scores['s1_q4']}, s1_q5={$s1_scores['s1_q5']}, s1_q6={$s1_scores['s1_q6']}, 
                s1_q7={$s1_scores['s1_q7']}, s1_q8={$s1_scores['s1_q8']}, s1_q9={$s1_scores['s1_q9']}, 
                s1_q10={$s1_scores['s1_q10']}, s1_q11={$s1_scores['s1_q11']}, s1_q12={$s1_scores['s1_q12']},
                s1_total=$s1_total,
                s2_q1={$s2_scores['s2_q1']}, s2_q2={$s2_scores['s2_q2']}, s2_q3={$s2_scores['s2_q3']}, 
                s2_q4={$s2_scores['s2_q4']}, s2_q5={$s2_scores['s2_q5']}, s2_q6={$s2_scores['s2_q6']}, 
                s2_q7={$s2_scores['s2_q7']}, s2_q8={$s2_scores['s2_q8']}, s2_q9={$s2_scores['s2_q9']}, 
                s2_q10={$s2_scores['s2_q10']},
                s2_total=$s2_total,
                comments='$comments', created_at=NOW()
                WHERE student_id='$student_id'";
                
        $log_c = $conn->query("SELECT COUNT(*) as cnt FROM teacher_report_evaluations_log WHERE student_id='$student_id'");
        $edit_times = $log_c->fetch_assoc()['cnt'];
        $action_type = "แก้ไขรายงานครั้งที่ " . $edit_times;

    } else {
        $sql = "INSERT INTO teacher_report_evaluations (student_id, teacher_id, report_title_th, 
                s1_q1, s1_q2, s1_q3, s1_q4, s1_q5, s1_q6, s1_q7, s1_q8, s1_q9, s1_q10, s1_q11, s1_q12, s1_total,
                s2_q1, s2_q2, s2_q3, s2_q4, s2_q5, s2_q6, s2_q7, s2_q8, s2_q9, s2_q10, s2_total, comments)
                VALUES ('$student_id', '$teacher_username', '$report_title', 
                {$s1_scores['s1_q1']}, {$s1_scores['s1_q2']}, {$s1_scores['s1_q3']}, {$s1_scores['s1_q4']}, {$s1_scores['s1_q5']}, {$s1_scores['s1_q6']}, {$s1_scores['s1_q7']}, {$s1_scores['s1_q8']}, {$s1_scores['s1_q9']}, {$s1_scores['s1_q10']}, {$s1_scores['s1_q11']}, {$s1_scores['s1_q12']}, $s1_total,
                {$s2_scores['s2_q1']}, {$s2_scores['s2_q2']}, {$s2_scores['s2_q3']}, {$s2_scores['s2_q4']}, {$s2_scores['s2_q5']}, {$s2_scores['s2_q6']}, {$s2_scores['s2_q7']}, {$s2_scores['s2_q8']}, {$s2_scores['s2_q9']}, {$s2_scores['s2_q10']}, $s2_total, '$comments')";
                
        $action_type = "ประเมินรายงานใหม่";
    }

    if ($conn->query($sql)) {
        // บันทึกลง Log
        $conn->query("INSERT INTO teacher_report_evaluations_log (student_id, s1_total, s2_total, action_type) 
                      VALUES ('$student_id', $s1_total, $s2_total, '$action_type')");

        $msg = "✅ บันทึกแบบประเมินสำเร็จ! ($action_type)"; $msg_type = "success";
    } else {
        $msg = "❌ เกิดข้อผิดพลาด: " . $conn->error; $msg_type = "danger";
    }
}

// ==========================================
// ค้นหานักศึกษา
// ==========================================
$search_student = null;
$existing_eval = null;
if (isset($_GET['search_id'])) {
    $sid = $conn->real_escape_string($_GET['search_id']);
    $res = $conn->query("SELECT u.users_name, u.full_name, c.company_name 
                         FROM users u 
                         LEFT JOIN student_internships si ON u.users_name = si.student_id AND si.status IN ('active','finished')
                         LEFT JOIN companies c ON si.company_id = c.company_id
                         WHERE u.users_name = '$sid' AND u.user_level = 's' 
                         ORDER BY si.internship_id DESC LIMIT 1");
                         
    if ($res && $res->num_rows > 0) {
        $search_student = $res->fetch_assoc();
        $check_eval = $conn->query("SELECT * FROM teacher_report_evaluations WHERE student_id = '$sid'");
        if ($check_eval->num_rows > 0) { 
            $existing_eval = $check_eval->fetch_assoc(); 
        }
    } else {
        $msg = "❌ ไม่พบรหัสนักศึกษานี้ในระบบ (หรือนักศึกษายังไม่มีสถานะฝึกงาน)"; 
        $msg_type = "danger";
    }
}

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'evaluate';
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ประเมินรายงานสหกิจศึกษา 📝</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg-color: #fffaf0; --primary-soft: #ffe066; --text-dark: #5c4d3c; }
        body { font-family: 'Prompt', 'Poppins', sans-serif; background: linear-gradient(135deg, var(--bg-color) 0%, #ffeed9 100%); color: var(--text-dark); min-height: 100vh; }
        
        .navbar-custom { background-color: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-bottom-left-radius: 20px; border-bottom-right-radius: 20px; }
        .nav-link-custom { color: var(--text-dark); font-weight: 600; padding: 10px 20px; border-radius: 50px; transition: 0.3s; margin-left: 5px; font-size: 0.95rem;}
        .nav-link-custom:hover, .nav-link-custom.active { background-color: var(--primary-soft); color: #856404; }
        
        .main-card { background: white; border-radius: 25px; padding: 35px; box-shadow: 0 10px 30px rgba(255, 191, 160, 0.2); }
        .nav-pills .nav-link { border-radius: 50px; font-weight: 600; color: var(--text-dark); padding: 10px 25px; margin-right: 10px; background-color: #f8f9fa; border: 1px solid #e9ecef;}
        .nav-pills .nav-link.active { background-color: #0d9488; color: white; border-color: #0d9488; box-shadow: 0 4px 10px rgba(13, 148, 136, 0.3);}
        
        .table-eval th { background-color: #fdfaf6; text-align: center; color: #0d9488;}
        .table-log th { background-color: #ffbfa0; color: white; border:none; }
        .sticky-summary { position: sticky; bottom: 20px; z-index: 100; }
        .form-control { border-radius: 10px; border: 1px solid #e0e0e0; }
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
                    <li class="nav-item"><a class="nav-link nav-link-custom <?php echo $page_name=='teacher_evaluation.php'?'active':''; ?>" href="teacher_evaluation.php"><i class="fas fa-clipboard-list"></i> ประเมินบริษัท</a></li>
                    <li class="nav-item"><a class="nav-link nav-link-custom <?php echo $page_name=='teacher_report_evaluation.php'?'active':''; ?>" href="teacher_report_evaluation.php"><i class="fas fa-clipboard-list"></i> ประเมินอาจารย์</a></li>
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
        <?php if($msg): ?>
            <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show shadow-sm rounded-pill px-4" role="alert">
                <?php echo $msg; ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="main-card">
            <h3 class="fw-bold text-dark mb-4"><i class="fas fa-book-reader text-primary me-2"></i> ระบบประเมินรายงานและการนำเสนอ (อ.นิเทศ)</h3>

            <ul class="nav nav-pills mb-4">
                <li class="nav-item"><a class="nav-link <?php echo $active_tab == 'evaluate' ? 'active' : ''; ?>" href="?tab=evaluate">กรอก/แก้ไข ประเมินรายงาน</a></li>
                <li class="nav-item"><a class="nav-link <?php echo $active_tab == 'log' ? 'active' : ''; ?>" href="?tab=log">ประวัติการประเมิน/สรุปผลคะแนน</a></li>
            </ul>

            <div class="tab-content">
                <?php 
                // =====================================
                // TAB 1: กรอกแบบประเมินรายงาน
                // =====================================
                if ($active_tab == 'evaluate'): 
                ?>
                    <form method="GET" action="teacher_report_evaluation.php" class="mb-4 d-flex gap-2">
                        <input type="hidden" name="tab" value="evaluate">
                        <input type="text" name="search_id" class="form-control" placeholder="พิมพ์รหัสนักศึกษา 13 หลัก..." required style="max-width: 300px;" value="<?php echo htmlspecialchars($_GET['search_id'] ?? ''); ?>">
                        <button type="submit" class="btn btn-primary" style="border-radius: 10px;"><i class="fas fa-search me-1"></i> ค้นหานักศึกษา</button>
                    </form>

                    <?php if($search_student): ?>
                        <form method="POST">
                            <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($search_student['users_name']); ?>">
                            
                            <div class="alert alert-info rounded-4 border-0 shadow-sm mb-4">
                                <h5 class="fw-bold mb-3 border-bottom pb-2 border-info"><i class="fas fa-info-circle me-2"></i>ข้อมูลนักศึกษา</h5>
                                <div class="row">
                                    <div class="col-md-6 mb-2"><span class="text-muted">ชื่อ-นามสกุล:</span> <b class="text-dark"><?php echo htmlspecialchars($search_student['full_name']); ?></b></div>
                                    <div class="col-md-6 mb-2"><span class="text-muted">รหัสประจำตัว:</span> <b class="text-dark"><?php echo htmlspecialchars($search_student['users_name']); ?></b></div>
                                    <div class="col-12"><span class="text-muted">สถานประกอบการ:</span> <b class="text-dark"><?php echo htmlspecialchars($search_student['company_name'] ?? 'ไม่ระบุสถานที่'); ?></b></div>
                                </div>
                            </div>

                            <div class="mb-4 p-4 border rounded shadow-sm bg-white border-primary" style="border-width: 2px !important;">
                                <label class="fw-bold text-primary mb-2 h5"><i class="fas fa-heading me-2"></i>ชื่อเรื่องรายงาน (Report Title)</label>
                                <input type="text" name="report_title_th" class="form-control form-control-lg" placeholder="พิมพ์หัวข้อรายงาน..." value="<?php echo htmlspecialchars($existing_eval['report_title_th'] ?? ''); ?>" required>
                            </div>

                            <div class="table-responsive mb-5">
                                <h5 class="fw-bold text-dark"><i class="fas fa-file-alt text-warning me-2"></i>ส่วนที่ 1 ด้านเนื้อหารูปแบบโครงงาน (เต็ม 100 คะแนน)</h5>
                                <table class="table table-bordered table-eval align-middle table-hover">
                                    <thead>
                                        <tr>
                                            <th width="5%">ข้อ</th>
                                            <th>หัวข้อประเมิน</th>
                                            <th width="15%">คะแนนเต็ม</th>
                                            <th width="15%">คะแนนที่ได้</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $s1_items = [
                                            1 => ["บทคัดย่อ (Abstract)", 5],
                                            2 => ["วัตถุประสงค์ (Objectives)", 5],
                                            3 => ["การทบทวนวรรณกรรม แนวคิดทฤษฎีที่เกี่ยวข้อง (Literature Review Concepts, theories)", 10],
                                            4 => ["วิธีการศึกษา (Method of Education)", 15],
                                            5 => ["ผลการศึกษา (Result)", 15],
                                            6 => ["การวิเคราะห์ผลการศึกษา (Analysis)", 10],
                                            7 => ["สรุปผลการศึกษา (Conclusion)", 10],
                                            8 => ["ข้อเสนอแนะ (Recommendation)", 5],
                                            9 => ["สำนวนการเขียน และการสื่อความหมาย (Idiom and Meaning)", 10],
                                            10 => ["ความถูกต้องตัวสะกด (Spelling)", 5],
                                            11 => ["รูปแบบ และความสวยงาม ของรูปเล่ม (Pattern)", 5],
                                            12 => ["เอกสารอ้างอิง (References)", 5]
                                        ];
                                        foreach($s1_items as $num => $data) {
                                            $val = $existing_eval["s1_q$num"] ?? '';
                                            echo "<tr>
                                                    <td class='text-center fw-bold text-muted'>$num</td>
                                                    <td>{$data[0]}</td>
                                                    <td class='text-center text-muted fw-bold'>{$data[1]}</td>
                                                    <td><input type='number' name='s1_q$num' class='form-control s1-input text-center' min='0' max='{$data[1]}' value='$val' required></td>
                                                  </tr>";
                                        }
                                        ?>
                                    </tbody>
                                    <tfoot>
                                        <tr style="background-color: #fff9e6;">
                                            <td colspan="2" class="text-end fw-bold text-warning-emphasis">รวมคะแนนส่วนที่ 1</td>
                                            <td colspan="2" class="text-center h5 fw-bold text-dark"><span id="s1_total_display">0</span> / 100</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <div class="table-responsive mb-5">
                                <h5 class="fw-bold text-dark"><i class="fas fa-chalkboard-teacher text-success me-2"></i>ส่วนที่ 2 ด้านการนำเสนอ (เต็ม 100 คะแนน)</h5>
                                <table class="table table-bordered table-eval align-middle table-hover">
                                    <thead>
                                        <tr>
                                            <th width="5%">ข้อ</th>
                                            <th>หัวข้อประเมิน (ข้อละ 10 คะแนน)</th>
                                            <th width="15%">คะแนนที่ได้</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $s2_items = [
                                            1 => "ความครบถ้วนของเนื้อหา (ด้านวิชาการ)",
                                            2 => "การแก้ปัญหาตรงกับวัตถุประสงค์",
                                            3 => "ความชัดเจนในการเสนอแนะแนวทางแก้ไข",
                                            4 => "ความชัดเจนในการตอบคำถาม",
                                            5 => "ผลสำเร็จของงาน",
                                            6 => "ความเหมาะสมของเนื้อหาและการใช้สื่อ (ด้านการนำเสนอ)",
                                            7 => "ลำดับขั้นตอนการนำเสนอ",
                                            8 => "การใช้เวลาในการนำเสนอ",
                                            9 => "ความครบถ้วนของเนื้อหา",
                                            10 => "การแต่งกายและบุคลิกภาพ"
                                        ];
                                        foreach($s2_items as $num => $title) {
                                            $val = $existing_eval["s2_q$num"] ?? '';
                                            echo "<tr>
                                                    <td class='text-center fw-bold text-muted'>$num</td>
                                                    <td>$title</td>
                                                    <td><input type='number' name='s2_q$num' class='form-control s2-input text-center' min='0' max='10' value='$val' required></td>
                                                  </tr>";
                                        }
                                        ?>
                                    </tbody>
                                    <tfoot>
                                        <tr style="background-color: #e6fffa;">
                                            <td colspan="2" class="text-end fw-bold text-success">รวมคะแนนส่วนที่ 2</td>
                                            <td class="text-center h5 fw-bold text-dark"><span id="s2_total_display">0</span> / 100</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <div class="mb-5">
                                <label class="fw-bold mb-2 h5 text-dark"><i class="fas fa-comment-dots text-secondary me-2"></i>ข้อคิดเห็น/ข้อเสนอแนะเพิ่มเติม</label>
                                <textarea name="comments" class="form-control" rows="3" placeholder="ระบุข้อเสนอแนะ..."><?php echo htmlspecialchars($existing_eval['comments'] ?? ''); ?></textarea>
                            </div>

                            <div class="sticky-summary">
                                <div class="card shadow-lg border-0 p-3" style="border-radius: 50px; background-color: #212529; opacity: 0.97;">
                                    <div class="d-flex justify-content-between align-items-center px-4 flex-wrap gap-2">
                                        <div class="text-white">
                                            <span class="me-4" style="font-size: 0.9rem;">ส่วนรายงาน (20%): <b class="text-warning fs-5 ms-1" id="s1_weight">0%</b></span>
                                            <span style="font-size: 0.9rem;">ส่วนนำเสนอ (20%): <b class="text-info fs-5 ms-1" id="s2_weight">0%</b></span>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <?php if ($existing_eval): ?>
                                                <button type="submit" name="delete_report_eval" class="btn btn-outline-danger btn-lg px-4" style="border-radius: 50px;" onclick="return confirm('คุณแน่ใจหรือไม่ที่จะลบข้อมูลการประเมินนี้? ข้อมูลจะสูญหายถาวร');">
                                                    <i class="fas fa-trash-alt me-2"></i> ลบทิ้ง
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button type="submit" name="save_report_eval" class="btn btn-warning btn-lg rounded-pill px-4 shadow fw-bold" onclick="return confirm('ยืนยันการบันทึกข้อมูลหรือไม่?');">
                                                <i class="fas fa-save me-2"></i> <?php echo $existing_eval ? 'บันทึกการแก้ไข' : 'บันทึกผลประเมิน'; ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>

                <?php 
                // =====================================
                // TAB 2: ประวัติการประเมินรายงาน (Log)
                // =====================================
                elseif ($active_tab == 'log'): 
                    // ดึงรายการปีการศึกษาสำหรับ Dropdown
                    $year_sql = "SELECT DISTINCT academic_year FROM users WHERE user_level = 's' AND academic_year IS NOT NULL AND academic_year != '' ORDER BY academic_year DESC";
                    $year_res = $conn->query($year_sql);
                    $academic_years = [];
                    if ($year_res && $year_res->num_rows > 0) {
                        while ($y = $year_res->fetch_assoc()) {
                            $academic_years[] = $y['academic_year'];
                        }
                    }
                ?>
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h5 class="text-dark fw-bold mb-0"><i class="fas fa-history text-secondary me-2"></i> ประวัติการดำเนินการ (Log)</h5>
                        
                        <form method="GET" class="d-flex align-items-center gap-2 bg-light p-2 rounded-pill border">
                            <label class="fw-bold text-dark mb-0 ms-2" style="white-space: nowrap; font-size: 0.9rem;">ปีการศึกษา:</label>
                            <select name="export_year" class="form-select form-select-sm border-primary rounded-pill" style="width: auto; min-width: 100px;" required>
                                <option value="" disabled selected>-- เลือกปี --</option>
                                <?php foreach($academic_years as $y): ?>
                                    <option value="<?php echo htmlspecialchars($y); ?>"><?php echo htmlspecialchars($y); ?></option>
                                <?php endforeach; ?>
                            </select>
                            
                            <button type="submit" formaction="print_summary_15.php" formtarget="_blank" class="btn btn-primary btn-sm rounded-pill shadow-sm px-3 d-flex align-items-center" style="white-space: nowrap;">
                                <i class="fas fa-print me-1"></i> สรุปคะแนน (กสศ.15)
                            </button>
                            
                            <button type="submit" name="export" value="excel" formaction="teacher_report_evaluation.php" class="btn btn-success btn-sm rounded-pill shadow-sm px-3 d-flex align-items-center" style="white-space: nowrap;">
                                <i class="fas fa-file-excel me-1"></i> ดาวน์โหลด Excel
                            </button>
                        </form>
                    </div>
                    
                    <div class="table-responsive border rounded">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-log">
                                <tr>
                                    <th class="ps-3">วัน/เวลาที่บันทึก</th>
                                    <th>ประเภท</th>
                                    <th>รหัสนักศึกษา (พิมพ์ฟอร์ม กสศ.14)</th>
                                    <th>ชื่อนักศึกษา</th>
                                    <th class="text-center">ส่วนที่ 1 (รายงาน)</th>
                                    <th class="text-center">ส่วนที่ 2 (นำเสนอ)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $log_sql = "SELECT l.*, u.full_name 
                                            FROM teacher_report_evaluations_log l 
                                            JOIN users u ON l.student_id = u.users_name 
                                            ORDER BY l.created_at DESC LIMIT 50";
                                $log_res = $conn->query($log_sql);
                                
                                if ($log_res && $log_res->num_rows > 0) {
                                    while($l = $log_res->fetch_assoc()) {
                                        
                                        // ป้ายกำกับสีตามรูปแบบการแก้ไข
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
                                        
                                        echo "<td>
                                                <a href='print_report_evaluation.php?student_id=" . htmlspecialchars($l['student_id']) . "' target='_blank' class='btn btn-sm btn-outline-primary rounded-pill shadow-sm px-3' title='คลิกเพื่อพิมพ์แบบประเมิน CWIE 14'>
                                                    <i class='fas fa-print me-1'></i> " . htmlspecialchars($l['student_id']) . "
                                                </a>
                                              </td>";

                                        echo "<td>".htmlspecialchars($l['full_name'])."</td>";
                                        echo "<td class='text-center fw-bold text-warning-emphasis'>".$l['s1_total']."</td>";
                                        echo "<td class='text-center fw-bold text-success'>".$l['s2_total']."</td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='6' class='text-center py-5 text-muted'>ยังไม่มีประวัติการประเมินรายงาน</td></tr>";
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
    function calculateTotals() {
        let s1 = 0;
        document.querySelectorAll('.s1-input').forEach(input => { s1 += Number(input.value) || 0; });
        let s1_disp = document.getElementById('s1_total_display');
        if(s1_disp) s1_disp.innerText = s1;
        
        let s1_w = document.getElementById('s1_weight');
        if(s1_w) s1_w.innerText = ((s1 * 20) / 100).toFixed(2) + "%";

        let s2 = 0;
        document.querySelectorAll('.s2-input').forEach(input => { s2 += Number(input.value) || 0; });
        let s2_disp = document.getElementById('s2_total_display');
        if(s2_disp) s2_disp.innerText = s2;
        
        let s2_w = document.getElementById('s2_weight');
        if(s2_w) s2_w.innerText = ((s2 * 20) / 100).toFixed(2) + "%";
    }

    document.querySelectorAll('input[type="number"]').forEach(input => {
        input.addEventListener('input', calculateTotals);
    });

    window.onload = function() { calculateTotals(); };
    </script>

</body>
</html>