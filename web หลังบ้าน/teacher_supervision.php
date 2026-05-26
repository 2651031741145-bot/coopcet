<?php
session_start();
require_once 'db.php';

// ตรวจสอบสิทธิ์ (ต้องเป็นอาจารย์ 't')
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
    <title>กำหนดการนิเทศ 🌻</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg-color: #fffaf0; --primary-soft: #ffe066; --secondary-soft: #ffbfa0; --text-dark: #5c4d3c; }
        body { font-family: 'Prompt', 'Poppins', sans-serif; background: linear-gradient(135deg, var(--bg-color) 0%, #ffeed9 100%); color: var(--text-dark); min-height: 100vh; overflow-x: hidden;}
        
        .navbar-custom { background-color: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-bottom-left-radius: 20px; border-bottom-right-radius: 20px; }
        .nav-link-custom { color: var(--text-dark); font-weight: 600; padding: 10px 20px; border-radius: 50px; transition: 0.3s; margin-left: 5px; font-size: 0.95rem;}
        .nav-link-custom:hover, .nav-link-custom.active { background-color: var(--primary-soft); color: #856404; }
        
        .main-card { background: white; border-radius: 25px; box-shadow: 0 10px 30px rgba(255, 191, 160, 0.2); border: none; padding: 30px; }
        
        /* สไตล์ตาราง */
        .table-custom-header th { background-color: #ffbfa0 !important; color: white !important; font-weight: 600; padding: 15px; border: none; }
        .table-custom-header th:first-child { border-top-left-radius: 15px; }
        .table-custom-header th:last-child { border-top-right-radius: 15px; }
        .table-hover tbody tr { transition: background-color 0.2s; border-left: 4px solid transparent; }
        .table-hover tbody tr:hover { background-color: #fffdf5; border-left: 4px solid var(--secondary-soft); }
        .table td { vertical-align: middle; padding: 15px 15px; border-color: #f8f9fa;}

        .btn-rounded { border-radius: 50px; font-weight: 600; padding: 8px 20px; transition: 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .btn-rounded:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(0,0,0,0.15); }
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
                
                <li class="nav-item"><a class="nav-link nav-link-custom <?php echo $page=='teacher_supervision.php'?'active':''; ?>" href="teacher_supervision.php"><i class="fas fa-calendar-check"></i> กำหนดการนิเทศ</a></li>
                
                <li class="nav-item ms-3">
                    <a href="logout.php" class="btn btn-danger rounded-pill px-4 shadow-sm d-flex align-items-center" style="font-weight: 500; padding-top: 8px; padding-bottom: 8px;">
                        <i class="fas fa-sign-out-alt me-2"></i> ออกจากระบบ
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

    <div class="container mt-5 pb-5" style="max-width: 900px;">
        <div class="main-card">
            <h3 class="fw-bold text-dark mb-1"><i class="fas fa-file-pdf text-danger me-2"></i> กำหนดการนิเทศ (สำหรับอาจารย์)</h3>
            <p class="text-muted mb-4">ตรวจสอบและดาวน์โหลดไฟล์กำหนดการนิเทศนักศึกษาในแต่ละรอบปีการศึกษา</p>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-custom-header">
                        <tr>
                            <th class="ps-4">ปีการศึกษา</th>
                            <th>ช่วงเวลาฝึกงาน</th>
                            <th class="text-center">สถานะรอบ</th>
                            <th class="text-center pe-4">ไฟล์กำหนดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // ดึงข้อมูลรอบการฝึกงานทั้งหมดมาแสดง
                        $sql = "SELECT * FROM internship_rounds ORDER BY academic_year DESC, start_date DESC";
                        $result = $conn->query($sql);

                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $status_badge = $row['round_status'] == 'open' ? "<span class='badge bg-success rounded-pill'>เปิดรับ</span>" : "<span class='badge bg-secondary rounded-pill'>ปิดแล้ว</span>";
                                
                                echo "<tr>";
                                echo "<td class='ps-4 fw-bold' style='color: #d97706;'>ปี " . htmlspecialchars($row['academic_year']) . "</td>";
                                echo "<td><small class='text-muted'><i class='fas fa-calendar-alt me-1'></i> " . date('d/m/Y', strtotime($row['start_date'])) . " - " . date('d/m/Y', strtotime($row['end_date'])) . "</small></td>";
                                echo "<td class='text-center'>" . $status_badge . "</td>";
                                
                                echo "<td class='text-center pe-4'>";
                                // เช็คว่ามีไฟล์ PDF หรือไม่
                                if (!empty($row['supervision_schedule_pdf'])) {
                                    echo "<a href='" . htmlspecialchars($row['supervision_schedule_pdf']) . "' target='_blank' class='btn btn-success btn-sm btn-rounded'><i class='fas fa-file-download me-1'></i> ดาวน์โหลด / เปิดดู</a>";
                                } else {
                                    echo "<span class='badge bg-light text-muted border rounded-pill py-2 px-3'><i class='fas fa-clock me-1'></i> รอแอดมินอัปโหลด</span>";
                                }
                                echo "</td></tr>";
                            }
                        } else {
                            echo "<tr><td colspan='4' class='text-center py-5 text-muted fw-bold'>ยังไม่มีข้อมูลรอบการฝึกงานในระบบ</td></tr>";
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