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

$msg = ""; $msg_type = "";

// ==========================================
// จัดการ Action อนุมัติ(พร้อมตั้งวัน) / ไม่อนุมัติ
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 1. กรณี "อนุมัติ" พร้อมรับค่า วันที่ใหม่
    if (isset($_POST['approve_with_dates'])) {
        $internship_id = $conn->real_escape_string($_POST['internship_id']);
        $new_start = $conn->real_escape_string($_POST['new_start_date']);
        $new_end = $conn->real_escape_string($_POST['new_end_date']);
        
        // อัปเดตสถานะเป็น relocated พร้อมอัปเดต custom_start_date และ custom_end_date
        $sql = "UPDATE student_internships 
                SET status = 'relocated', 
                    custom_start_date = '$new_start', 
                    custom_end_date = '$new_end' 
                WHERE internship_id = '$internship_id'";
                
        if ($conn->query($sql)) {
            $msg = "✅ อนุมัติการขอย้ายสถานที่ และกำหนดวันฝึกงานใหม่เรียบร้อยแล้ว!";
            $msg_type = "success";
        }
    }

    // 2. กรณี "ไม่อนุมัติ" (ปฏิเสธการย้าย)
    if (isset($_POST['reject_relocate'])) {
        $internship_id = $conn->real_escape_string($_POST['internship_id']);
        
        // อัปเดตสถานะกลับไปเป็น 'active' และล้างเหตุผลทิ้ง
        $sql = "UPDATE student_internships SET status = 'active', relocate_reason = NULL WHERE internship_id = '$internship_id'";
        if ($conn->query($sql)) {
            $msg = "❌ ไม่อนุมัติคำขอ! ระบบได้ปรับสถานะนักศึกษากลับไปเป็น 'กำลังฝึกงาน' ที่สถานที่เดิมแล้ว";
            $msg_type = "warning";
        }
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
    <title>คำขอย้ายสถานที่ 🔄</title>
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
        
        /* กล่องเหตุผลที่ขอย้าย */
        .reason-box { 
            background-color: #fdfaf6; 
            border: 1px solid #f0e6d2;
            border-left: 4px solid #ffc107; 
            padding: 12px 15px; 
            border-radius: 10px; 
            font-size: 0.95rem; 
            color: #6c757d;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        }

        .company-badge {
            background-color: white; color: #4a4a4a; border: 1px solid #e0e0e0; padding: 6px 15px; border-radius: 50px; font-weight: 500; font-size: 0.85rem; box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        }
        
        /* ปุ่มการจัดการ */
        .btn-approve-relocate { 
            background-color: #198754; color: white; border-radius: 50px; padding: 8px 20px; font-weight: 600; border: none; transition: 0.3s; box-shadow: 0 4px 10px rgba(25, 135, 84, 0.2);
        }
        .btn-approve-relocate:hover { background-color: #157347; color: white; transform: translateY(-2px); }

        .btn-reject-relocate { 
            background-color: white; color: #dc3545; border: 1px solid #f8d7da; border-radius: 50px; padding: 8px 20px; font-weight: 600; transition: 0.3s;
        }
        .btn-reject-relocate:hover { background-color: #fff5f5; border-color: #dc3545; transform: translateY(-2px); }

        /* Modal */
        .form-control { border-radius: 15px; padding: 12px; border: 2px solid #f0e6d2; }
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
                <?php echo $msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="main-card">
            <h3 class="fw-bold text-dark mb-1"><i class="fas fa-exchange-alt text-warning me-2"></i> รายการคำขอย้ายสถานที่ฝึกงาน</h3>
            <p class="text-muted mb-4">พิจารณาคำร้องขอย้ายสถานที่ฝึกงานของนักศึกษา</p>

            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-custom-header">
                        <tr>
                            <th class="ps-4">รหัสนักศึกษา</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>บริษัทที่ฝึกปัจจุบัน</th>
                            <th style="width: 40%;">เหตุผลที่ขอย้าย</th>
                            <th class="text-center pe-4">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT si.internship_id, si.relocate_reason, si.created_at, 
                                       u.users_name, u.full_name, c.company_name 
                                FROM student_internships si 
                                JOIN users u ON si.student_id = u.users_name 
                                LEFT JOIN companies c ON si.company_id = c.company_id 
                                WHERE si.status = 'relocating' 
                                ORDER BY si.created_at ASC";
                        
                        $result = $conn->query($sql);

                        if ($result && $result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) {
                                echo "<tr class='border-bottom'>";
                                echo "<td class='ps-4 fw-bold text-muted text-nowrap'>".$row['users_name']."</td>";
                                echo "<td class='text-nowrap fw-medium'>".htmlspecialchars($row['full_name'] ?? '-')."</td>";
                                echo "<td><span class='company-badge'>".htmlspecialchars($row['company_name'] ?? 'ไม่ระบุ')."</span></td>";
                                echo "<td><div class='reason-box'>".nl2br(htmlspecialchars($row['relocate_reason']))."</div></td>";
                                
                                echo "<td class='text-center pe-4'>";
                                echo "<div class='d-flex justify-content-center gap-2'>";
                                
                                // เปลี่ยนปุ่มอนุมัติ ให้เรียกเปิด Modal แทนการ Submit ทันที
                                echo "<button type='button' class='btn-approve-relocate' onclick='openApproveModal(\"".$row['internship_id']."\", \"".htmlspecialchars($row['full_name'], ENT_QUOTES)."\")'><i class='fas fa-check'></i> อนุมัติ</button>";
                                
                                // ฟอร์มปฏิเสธ (ทำงานทันทีเหมือนเดิม)
                                echo "<form method='POST' style='margin:0;' onsubmit='return confirm(\"แน่ใจหรือไม่ที่จะไม่อนุมัติคำขอนี้?\");'>";
                                echo "<input type='hidden' name='internship_id' value='".$row['internship_id']."'>";
                                echo "<button type='submit' name='reject_relocate' class='btn-reject-relocate'><i class='fas fa-times'></i> ไม่อนุมัติ</button>";
                                echo "</form>";

                                echo "</div>";
                                echo "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5' class='text-center py-5 text-muted fw-bold'>ไม่มีคำขอย้ายสถานที่ในขณะนี้ 🎉</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px;">
                <form method="POST">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold text-dark"><i class="fas fa-calendar-plus text-success me-2"></i> ตั้งเวลาฝึกงานใหม่ (ย้ายสถานที่)</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="internship_id" id="modal_internship_id">
                        <p class="mb-3 text-primary fw-bold" id="modal_student_name"></p>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">เริ่มฝึกงานที่ใหม่</label>
                            <input type="date" name="new_start_date" class="form-control" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold small text-muted">สิ้นสุดการฝึกงานที่ใหม่</label>
                            <input type="date" name="new_end_date" class="form-control" required>
                        </div>
                        <div class="alert alert-warning small border-0 rounded-3 mb-0">
                            <i class="fas fa-info-circle"></i> เมื่อกดยืนยัน สถานะนักศึกษาจะถูกเปลี่ยนให้สามารถยื่นเลือกสถานที่ใหม่ได้
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light" style="border-radius: 50px; font-weight: 600; padding: 8px 20px;" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" name="approve_with_dates" class="btn-approve-relocate border-0 shadow-sm">บันทึกและอนุมัติ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ฟังก์ชันเปิด Modal พร้อมดึงข้อมูลรหัสและชื่อไปใส่ไว้
        function openApproveModal(internshipId, studentName) {
            document.getElementById('modal_internship_id').value = internshipId;
            document.getElementById('modal_student_name').innerText = "นักศึกษา: " + studentName;
            new bootstrap.Modal(document.getElementById('approveModal')).show();
        }
    </script>
</body>
</html>