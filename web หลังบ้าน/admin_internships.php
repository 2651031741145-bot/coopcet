<?php
session_start();
require_once 'db.php';

// --- ส่วนตรวจสอบสิทธิ์ ---
if (!isset($_SESSION['users_name']) || $_SESSION['user_level'] != 'a' || !isset($_SESSION['session_token'])) {
    header("Location: logout.php");
    exit();
}

$admin_username = $conn->real_escape_string($_SESSION['users_name']);
$t_name_query = $conn->query("SELECT full_name FROM users WHERE users_name = '$admin_username'");
$admin_fullname = ($t_name_query && $t_name_query->num_rows > 0) ? $t_name_query->fetch_assoc()['full_name'] : 'ผู้ดูแลระบบ';

$msg = "";
$msg_type = "";

// ==========================================
// Action: อัปเดตสถานะการฝึกงาน หรือ รีเซ็ตข้อมูล
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_internship_status'])) {
    $internship_id = $conn->real_escape_string($_POST['internship_id']);
    $student_id = $conn->real_escape_string($_POST['student_id']);
    $new_status = $conn->real_escape_string($_POST['status']);

    if ($new_status === 'reset') {
        // 1. ลบไฟล์เอกสารโปรเจกต์
        $stmt_file = $conn->query("SELECT project_file_path FROM internship_summaries WHERE internship_id = '$internship_id'");
        if ($stmt_file && $row_file = $stmt_file->fetch_assoc()) {
            $file_path = "../" . $row_file['project_file_path']; // ปรับ Path ให้ตรงกับโฟลเดอร์เก็บไฟล์
            if (!empty($row_file['project_file_path']) && file_exists($file_path)) {
                unlink($file_path);
            }
        }
        // 2. ลบข้อมูลสรุปการฝึกงาน
        $conn->query("DELETE FROM internship_summaries WHERE internship_id = '$internship_id'");
        // 3. ลบข้อมูลการประเมิน
        $conn->query("DELETE FROM teacher_evaluations WHERE student_id = '$student_id'");
        $conn->query("DELETE FROM teacher_evaluations_log WHERE student_id = '$student_id'");
        // 4. ลบข้อมูลสถานะหลัก
        if ($conn->query("DELETE FROM student_internships WHERE internship_id = '$internship_id'")) {
            $msg = "♻️ ล้างข้อมูลการฝึกงานของ $student_id กลับไปเป็น 'ยังไม่ยื่นเรื่อง' สำเร็จ";
            $msg_type = "success";
        } else {
            $msg = "😿 เกิดข้อผิดพลาดในการรีเซ็ต";
            $msg_type = "danger";
        }
    } else {
        // อัปเดตสถานะปกติ
        $sql = "UPDATE student_internships SET status = '$new_status' WHERE internship_id = '$internship_id'";
        if ($conn->query($sql)) {
            $msg = "✅ ปรับปรุงสถานะการฝึกงานเรียบร้อยแล้ว";
            $msg_type = "success";
        } else {
            $msg = "😿 เกิดข้อผิดพลาดในการปรับปรุงสถานะ";
            $msg_type = "danger";
        }
    }
}

// ==========================================
// ระบบค้นหา สำหรับ INTERNSHIPS
// ==========================================
$search_int_text = isset($_GET['search_int_text']) ? $conn->real_escape_string($_GET['search_int_text']) : '';
$search_int_year = isset($_GET['search_int_year']) ? $conn->real_escape_string($_GET['search_int_year']) : '';

$int_where_clauses = [];
if ($search_int_text != '') {
    $int_where_clauses[] = "(u.users_name LIKE '%$search_int_text%' OR u.full_name LIKE '%$search_int_text%')";
}
if ($search_int_year != '') {
    $int_where_clauses[] = "ir.academic_year = '$search_int_year'";
}
$int_where_sql = count($int_where_clauses) > 0 ? "WHERE " . implode(" AND ", $int_where_clauses) : "";

// ดึงปีการศึกษาที่มีในตาราง internship_rounds
$int_year_sql = "SELECT DISTINCT ir.academic_year FROM student_internships si JOIN internship_rounds ir ON si.round_id = ir.round_id ORDER BY ir.academic_year DESC";
$int_year_result = $conn->query($int_year_sql);

// ฟังก์ชันแปลงสถานะเป็นภาษาไทยและสี
function getStatusDetails($status) {
    switch ($status) {
        case 'active': return ['text' => 'กำลังฝึกงาน', 'class' => 'bg-pastel-status-active', 'icon' => 'fa-check-circle'];
        case 'relocating': return ['text' => 'ขอย้ายสถานที่', 'class' => 'bg-pastel-status-relocating', 'icon' => 'fa-exchange-alt'];
        case 'relocated': return ['text' => 'ย้ายสถานที่แล้ว', 'class' => 'bg-pastel-status-relocated', 'icon' => 'fa-truck'];
        case 'pending': return ['text' => 'รออนุมัติจบ', 'class' => 'bg-pastel-status-pending', 'icon' => 'fa-clock'];
        case 'finished': return ['text' => 'จบการฝึกงาน', 'class' => 'bg-pastel-status-finished', 'icon' => 'fa-flag-checkered'];
        default: return ['text' => 'ไม่ทราบสถานะ', 'class' => 'bg-secondary text-white', 'icon' => 'fa-question-circle'];
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสถานะการฝึกงาน ✨</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --bg-color: #f0f7ff;
            --card-bg: #ffffff;
            --primary-soft: #a2d2ff;
            --text-dark: #4a4e69;
        }

        body {
            font-family: 'Prompt', 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--bg-color) 0%, #e2eaff 100%);
            color: var(--text-dark);
            min-height: 100vh;
        }

        .navbar-custom {
            background-color: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border-bottom-left-radius: 20px;
            border-bottom-right-radius: 20px;
        }

        .main-card {
            background: var(--card-bg);
            border-radius: 25px;
            box-shadow: 0 10px 30px rgba(162, 210, 255, 0.2);
            border: none;
            padding: 30px;
        }

        .btn-rounded { border-radius: 50px; font-weight: 600; padding: 8px 20px; transition: 0.3s; border: none; }
        .btn-rounded:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); }
        .btn-pastel-primary { background-color: var(--primary-soft); color: #0d6efd; }
        
        .table-responsive { overflow-x: auto; }
        .table thead th { background-color: var(--primary-soft); color: #fff; border: none; padding: 15px; }
        .table thead th:first-child { border-top-left-radius: 15px; }
        .table thead th:last-child { border-top-right-radius: 15px; }
        .table td { vertical-align: middle; padding: 12px 15px; }
        .table-hover tbody tr:hover { background-color: #f8fbff; border-left: 4px solid var(--primary-soft); }

        .badge-pastel { border-radius: 20px; padding: 6px 12px; font-weight: 500; letter-spacing: 0.5px; }
        
        /* สีสถานะการฝึกงาน */
        .bg-pastel-status-active { background-color: #d1e7dd; color: #0f5132; border: 1px solid #badbcc;}
        .bg-pastel-status-relocating { background-color: #ffe5d0; color: #b05c0d; border: 1px solid #ffcfab;}
        .bg-pastel-status-relocated { background-color: #cff4fc; color: #055160; border: 1px solid #b6effb;}
        .bg-pastel-status-pending { background-color: #fff3cd; color: #856404; border: 1px solid #ffecb5;}
        .bg-pastel-status-finished { background-color: #e2e3e5; color: #41464b; border: 1px solid #d3d6d8;}

        .modal-content { border-radius: 25px; border: none; }
        .modal-header { background-color: var(--primary-soft); color: white; border-top-left-radius: 25px; border-top-right-radius: 25px; }
        .form-control, .form-select { border-radius: 15px; padding: 12px; border: 2px solid #f0f0f0; }
    </style>
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-custom sticky-top py-3">
        <div class="container">
            <a class="navbar-brand fw-bold" href="admin_dashboard.php"><i class="fas fa-arrow-left me-2"></i> กลับหน้าหลัก</a>
            <div class="d-flex align-items-center">
                <span class="me-3 text-muted" style="font-weight: 500;">👋 สวัสดี, คุณ <?php echo htmlspecialchars($admin_fullname); ?></span>
            </div>
        </div>
    </nav>

    <div class="container mt-4 pb-5">

        <?php if ($msg != ""): ?>
            <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show shadow-sm rounded-pill px-4" role="alert">
                <?php echo $msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="margin-top: 2px;"></button>
            </div>
        <?php endif; ?>

        <div class="main-card">
            <div class="mb-4">
                <h3 class="fw-bold mb-1" style="color: var(--text-dark);"><i class="fas fa-user-check text-primary me-2"></i> จัดการสถานะการฝึกงาน</h3>
                <p class="text-muted mb-0">ตรวจสอบและปรับปรุงสถานะนักศึกษาทั้งหมด</p>
            </div>

            <form method="GET" action="admin_internships.php" class="row g-3 mb-4 bg-light p-3 rounded-4 shadow-sm align-items-end">
                <div class="col-md-5">
                    <label class="form-label text-muted small fw-bold ms-2">ค้นหาชื่อนักศึกษา / รหัส 🔍</label>
                    <input type="text" name="search_int_text" class="form-control border-0 shadow-sm" placeholder="พิมพ์ชื่อหรือรหัส..." value="<?php echo htmlspecialchars($search_int_text); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-bold ms-2">นักศึกษาปีการศึกษา (รอบฝึกงาน) 📅</label>
                    <select name="search_int_year" class="form-select border-0 shadow-sm">
                        <option value="">-- แสดงทุกปีการศึกษา --</option>
                        <?php
                        if ($int_year_result && $int_year_result->num_rows > 0) {
                            while ($yr = $int_year_result->fetch_assoc()) {
                                $selected = ($search_int_year == $yr['academic_year']) ? 'selected' : '';
                                echo "<option value='" . $yr['academic_year'] . "' $selected>นักศึกษาปีการศึกษา " . $yr['academic_year'] . "</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-pastel-primary btn-rounded flex-grow-1 shadow-sm"><i class="fas fa-filter"></i> กรองข้อมูล</button>
                    <a href="admin_internships.php" class="btn btn-light btn-rounded border text-muted shadow-sm"><i class="fas fa-undo"></i></a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">รหัสนักศึกษา</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>สถานที่ฝึกงาน</th>
                            <th class="text-center">นักศึกษาปีการศึกษา</th>
                            <th class="text-center">สถานะปัจจุบัน</th>
                            <th class="text-center pe-4">ปรับปรุงสถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql_int = "SELECT si.internship_id, si.status, u.users_name AS student_id, u.full_name, c.company_name, ir.academic_year 
                                    FROM student_internships si
                                    JOIN users u ON si.student_id = u.users_name
                                    JOIN companies c ON si.company_id = c.company_id
                                    JOIN internship_rounds ir ON si.round_id = ir.round_id
                                    $int_where_sql
                                    ORDER BY ir.academic_year DESC, si.created_at DESC";
                        $res_int = $conn->query($sql_int);

                        if ($res_int && $res_int->num_rows > 0) {
                            while ($row = $res_int->fetch_assoc()) {
                                $status_info = getStatusDetails($row['status']);
                                
                                echo "<tr>";
                                echo "<td class='ps-4 fw-bold text-muted'>" . htmlspecialchars($row['student_id']) . "</td>";
                                echo "<td class='fw-bold text-dark'>" . htmlspecialchars($row['full_name']) . "</td>";
                                echo "<td><small class='text-muted'><i class='fas fa-building me-1'></i>" . htmlspecialchars($row['company_name']) . "</small></td>";
                                echo "<td class='text-center'><span class='badge bg-light text-dark rounded-pill border'>ปี " . htmlspecialchars($row['academic_year']) . "</span></td>";
                                echo "<td class='text-center'><span class='badge badge-pastel " . $status_info['class'] . "'><i class='fas " . $status_info['icon'] . " me-1'></i> " . $status_info['text'] . "</span></td>";
                                echo "<td class='text-center pe-4'>";
                                echo "<button class='btn btn-rounded btn-outline-primary btn-sm' onclick='openEditInternshipModal(\"" . $row['internship_id'] . "\", \"" . htmlspecialchars($row['student_id'], ENT_QUOTES) . "\", \"" . htmlspecialchars($row['full_name'], ENT_QUOTES) . "\", \"" . $row['status'] . "\")'><i class='fas fa-edit'></i> จัดการ</button>";
                                echo "</td></tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6' class='text-center text-muted py-5 fw-bold'>📭 ไม่พบข้อมูลการฝึกงานในระบบ</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editInternshipModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg">
                <form action="admin_internships.php" method="POST" id="formUpdateInternship">
                    <input type="hidden" name="update_internship_status" value="1">
                    <input type="hidden" name="internship_id" id="modal_int_id">
                    <input type="hidden" name="student_id" id="modal_int_student_id">
                    <div class="modal-header border-0 pb-0" style="background-color: var(--primary-soft);">
                        <h5 class="modal-title fw-bold text-white"><i class="fas fa-tasks me-2"></i>ปรับปรุงสถานะการฝึกงาน</h5>
                        <button type="button" class="btn-close btn-close-white me-2 mt-2" data-bs-dismiss="modal" style="filter: brightness(0) invert(1);"></button>
                    </div>
                    <div class="modal-body pt-4 px-4">
                        <div class="mb-3 bg-light p-3 rounded-3 border">
                            <span class="text-muted small">นักศึกษา:</span><br>
                            <span class="fw-bold fs-5 text-dark" id="modal_int_name">-</span>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold text-muted ms-1">เลือกสถานะใหม่:</label>
                            <select name="status" id="modal_int_status" class="form-select fw-bold text-dark">
                                <option value="active">🟢 กำลังฝึกงาน (Active)</option>
                                <option value="relocating">🟠 ขอย้ายสถานที่ (Relocating)</option>
                                <option value="relocated">🔵 ย้ายสถานที่แล้ว (Relocated)</option>
                                <option value="pending">🟡 รออนุมัติจบ (Pending)</option>
                                <option value="finished">🔘 จบการฝึกงาน (Finished)</option>
                                <optgroup label="--------- เขตอันตราย ---------">
                                    <option value="reset" class="text-danger fw-bold">🔴 ยังไม่ยื่นเรื่อง (รีเซ็ตข้อมูลใหม่ทั้งหมด)</option>
                                </optgroup>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0 pb-4 px-4">
                        <button type="button" class="btn btn-rounded btn-light border px-4" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="button" class="btn btn-rounded btn-pastel-primary px-4" onclick="confirmInternshipUpdate()">💾 บันทึกสถานะ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openEditInternshipModal(internship_id, student_id, student_name, current_status) {
            document.getElementById('modal_int_id').value = internship_id;
            document.getElementById('modal_int_student_id').value = student_id;
            document.getElementById('modal_int_name').innerText = student_name;
            document.getElementById('modal_int_status').value = current_status;
            new bootstrap.Modal(document.getElementById('editInternshipModal')).show();
        }

        function confirmInternshipUpdate() {
            let status = document.getElementById('modal_int_status').value;
            let studentName = document.getElementById('modal_int_name').innerText;
            
            if (status === 'reset') {
                if(confirm('⚠️ คำเตือนขั้นสุดยอด!\n\nคุณกำลังจะลบข้อมูลการยื่นเรื่องของ "' + studentName + '"\nระบบจะล้างข้อมูลสรุป คะแนนประเมิน และไฟล์ที่เกี่ยวข้องทิ้งทั้งหมด!\n\nยืนยันการลบใช่หรือไม่?')) {
                    document.getElementById('formUpdateInternship').submit();
                }
            } else {
                document.getElementById('formUpdateInternship').submit();
            }
        }
    </script>
</body>
</html>