<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['users_name']) || $_SESSION['user_level'] != 't' || !isset($_SESSION['session_token'])) {
    header("Location: logout.php"); exit();
}

$msg = ""; $msg_type = "";

// จัดการเมื่อกดปุ่ม "อนุมัติ"
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['approve_internship'])) {
    $internship_id = $conn->real_escape_string($_POST['internship_id']);
    $conn->query("UPDATE student_internships SET status = 'finished' WHERE internship_id = '$internship_id'");
    $conn->query("UPDATE internship_summaries SET can_publish = 'yes' WHERE internship_id = '$internship_id'");
    
    $msg = "✨ อนุมัติข้อมูลการฝึกงานเรียบร้อยแล้ว!"; $msg_type = "success";
} 
// จัดการเมื่อกดปุ่ม "ไม่อนุมัติ"
elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reject_internship'])) {
    $internship_id = $conn->real_escape_string($_POST['internship_id']);
    
    // ---------------------------------------------------------
    // แก้ไขตรงนี้: เปลี่ยนจาก 'in_progress' เป็น 'active' 
    // เพื่อให้ฐานข้อมูลยอมรับการอัปเดตและตีกลับไปสถานะปกติ
    // ---------------------------------------------------------
    $conn->query("UPDATE student_internships SET status = 'active' WHERE internship_id = '$internship_id'");
    $conn->query("UPDATE internship_summaries SET can_publish = 'no' WHERE internship_id = '$internship_id'");
    
    $msg = "❌ ไม่อนุมัติข้อมูลการฝึกงาน (ตีกลับให้แก้ไขแล้ว)"; $msg_type = "danger";
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
    <title>อนุมัติจบฝึกงาน 🌻</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Prompt:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg-color: #fffaf0; --primary-soft: #ffe066; --secondary-soft: #ffbfa0; --success-soft: #c3f0ca; --text-dark: #5c4d3c; }
        body { font-family: 'Prompt', 'Poppins', sans-serif; background: linear-gradient(135deg, var(--bg-color) 0%, #ffeed9 100%); color: var(--text-dark); min-height: 100vh; }
        .navbar-custom { background-color: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-bottom-left-radius: 20px; border-bottom-right-radius: 20px; }
        .nav-link-custom { color: var(--text-dark); font-weight: 600; padding: 10px 20px; border-radius: 50px; transition: 0.3s; margin-left: 5px; font-size: 0.95rem;}
        .nav-link-custom:hover, .nav-link-custom.active { background-color: var(--primary-soft); color: #856404; }
        .main-card { background: white; border-radius: 25px; box-shadow: 0 10px 30px rgba(255, 191, 160, 0.2); border: none; padding: 30px; }
        .table thead th { background-color: var(--secondary-soft); color: white; border: none; font-weight: 600; padding: 15px; }
        .table thead th:first-child { border-top-left-radius: 15px; }
        .table thead th:last-child { border-top-right-radius: 15px; }
        .btn-rounded { border-radius: 50px; font-weight: 500; padding: 5px 15px; font-size: 0.85rem;}
        
        /* สไตล์ปุ่มกดต่างๆ */
        .btn-approve { background-color: var(--success-soft); color: #155724; padding: 8px 15px;}
        .btn-approve:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .btn-reject { background-color: #f8d7da; color: #721c24; padding: 8px 15px;}
        .btn-reject:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .btn-view { background-color: #cce5ff; color: #004085; padding: 8px 15px; text-decoration: none;}
        .btn-view:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); color: #004085;}
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
                <?php $page = basename($_SERVER['PHP_SELF']); ?>
                <li class="nav-item"><a class="nav-link nav-link-custom <?php echo ($page=='teacher_dashboard.php' || $page=='teacher_student_detail.php')?'active':''; ?>" href="teacher_dashboard.php"><i class="fas fa-users"></i> ข้อมูลนักศึกษา</a></li>
                <li class="nav-item"><a class="nav-link nav-link-custom <?php echo $page_name=='teacher_evaluation.php'?'active':''; ?>" href="teacher_evaluation.php"><i class="fas fa-clipboard-list"></i> ประเมินบริษัท</a></li>
                 <li class="nav-item"><a class="nav-link nav-link-custom <?php echo $page_name=='teacher_report_evaluation.php'?'active':''; ?>" href="teacher_report_evaluation.php"><i class="fas fa-clipboard-list"></i> ประเมินอาจารย์</a></li>
                <li class="nav-item"><a class="nav-link nav-link-custom <?php echo $page=='teacher_rounds.php'?'active':''; ?>" href="teacher_rounds.php"><i class="fas fa-calendar-alt"></i> จัดการรอบฝึกงาน</a></li>
                <li class="nav-item"><a class="nav-link nav-link-custom <?php echo $page=='teacher_relocation.php'?'active':''; ?>" href="teacher_relocation.php"><i class="fas fa-exchange-alt"></i> คำขอย้ายสถานที่</a></li>
                <li class="nav-item"><a class="nav-link nav-link-custom <?php echo $page=='teacher_pending.php'?'active':''; ?>" href="teacher_pending.php"><i class="fas fa-clipboard-check"></i> อนุมัติจบฝึกงาน</a></li>
                <li class="nav-item"><a class="nav-link nav-link-custom <?php echo $page=='teacher_import_students.php'?'active':''; ?>" href="teacher_import_students.php"><i class="fas fa-file-import"></i> เพิ่มรายชื่อนักศึกษาใหม่</a></li>
                <li class="nav-item"><a class="nav-link nav-link-custom <?php echo $page=='teacher_approved.php'?'active':''; ?>" href="teacher_approved.php"><i class="fas fa-archive"></i> คลังข้อมูล</a></li>
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
                <?php echo $msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="main-card">
            <h3 class="fw-bold mb-1 text-dark">⏳ รายการขอจบการฝึกงาน (รออนุมัติ)</h3>
            <p class="text-muted mb-4">นักศึกษาที่ส่งรายงานและบันทึกครบถ้วนแล้ว</p>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr><th>รหัสนักศึกษา</th><th>ชื่อ-นามสกุล</th><th>ตำแหน่งที่ฝึก</th><th class="text-center">การจัดการ</th></tr>
                    </thead>
                    <tbody>
                        <?php
                        // ดึงข้อมูลเฉพาะรายการที่รออนุมัติ (pending)
                        $p_sql = "SELECT u.users_name, u.full_name, si.internship_id, s.position 
                                  FROM users u 
                                  JOIN student_internships si ON u.users_name = si.student_id 
                                  JOIN internship_summaries s ON si.internship_id = s.internship_id 
                                  WHERE si.status = 'pending'";
                        $p_res = $conn->query($p_sql);
                        
                        if ($p_res && $p_res->num_rows > 0) {
                            while($p = $p_res->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td class='fw-bold text-muted'>".$p['users_name']."</td>";
                                echo "<td>".htmlspecialchars($p['full_name'])."</td>";
                                echo "<td>".htmlspecialchars($p['position'])."</td>";
                                
                                echo "<td class='text-center text-nowrap'>";
                                
                                echo "<a href='teacher_student_detail.php?id=".$p['users_name']."' class='btn btn-view btn-rounded btn-sm shadow-sm me-1'><i class='fas fa-search'></i> ดูรายละเอียด</a>";
                                
                                // ปุ่ม อนุมัติ
                                echo "<form method='POST' style='display:inline;' action='teacher_pending.php'>";
                                echo "<input type='hidden' name='internship_id' value='".$p['internship_id']."'>";
                                echo "<button type='submit' name='approve_internship' class='btn btn-approve btn-rounded btn-sm shadow-sm me-1' onclick='return confirm(\"ยืนยันการอนุมัติจบการฝึกงาน?\");'><i class='fas fa-check-circle'></i> อนุมัติ</button>";
                                echo "</form>";

                                // ปุ่ม ไม่อนุมัติ
                                echo "<form method='POST' style='display:inline;' action='teacher_pending.php'>";
                                echo "<input type='hidden' name='internship_id' value='".$p['internship_id']."'>";
                                echo "<button type='submit' name='reject_internship' class='btn btn-reject btn-rounded btn-sm shadow-sm' onclick='return confirm(\"ยืนยัน ไม่อนุมัติ จบการฝึกงานใช่หรือไม่?\");'><i class='fas fa-times-circle'></i> ไม่อนุมัติ</button>";
                                echo "</form>";

                                echo "</td></tr>";
                            }
                        } else { 
                            echo "<tr><td colspan='4' class='text-center py-5 text-muted'>ไม่มีรายการรออนุมัติ 🎉</td></tr>"; 
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>