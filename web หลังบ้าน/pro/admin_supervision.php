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

// ==========================================
// ระบบแสดงข้อความแจ้งเตือน (Flash Message)
// ==========================================
$msg = "";
$msg_type = "";
if (isset($_SESSION['flash_msg'])) {
    $msg = $_SESSION['flash_msg'];
    $msg_type = $_SESSION['flash_msg_type'];
    unset($_SESSION['flash_msg']);
    unset($_SESSION['flash_msg_type']);
}

// สร้างโฟลเดอร์สำหรับเก็บไฟล์ PDF (ชี้ไปที่โฟลเดอร์หลักฝั่งแอป)
$upload_dir = "../internship/app/uploads/schedules/";
if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, 0777, true);
}

// ==========================================
// Action: เพิ่มรอบการฝึกงานใหม่
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_round'])) {
    $academic_year = $conn->real_escape_string($_POST['academic_year']);
    $start_date = $conn->real_escape_string($_POST['start_date']);
    $end_date = $conn->real_escape_string($_POST['end_date']);
    
    $sql = "INSERT INTO internship_rounds (academic_year, start_date, end_date, created_by) 
            VALUES ('$academic_year', '$start_date', '$end_date', '$admin_username')";
    if ($conn->query($sql)) {
        $_SESSION['flash_msg'] = "✨ เพิ่มรอบการฝึกงานปี $academic_year สำเร็จ!";
        $_SESSION['flash_msg_type'] = "success";
    } else {
        $_SESSION['flash_msg'] = "😿 เกิดข้อผิดพลาดในการสร้างรอบฝึกงาน: " . $conn->error;
        $_SESSION['flash_msg_type'] = "danger";
    }
    header("Location: admin_supervision.php"); exit();
}

// ==========================================
// Action: แก้ไขรอบการฝึกงาน
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_round'])) {
    $round_id = $conn->real_escape_string($_POST['edit_round_id']);
    $academic_year = $conn->real_escape_string($_POST['edit_academic_year']);
    $start_date = $conn->real_escape_string($_POST['edit_start_date']);
    $end_date = $conn->real_escape_string($_POST['edit_end_date']);
    
    $sql = "UPDATE internship_rounds SET academic_year = '$academic_year', start_date = '$start_date', end_date = '$end_date' WHERE round_id = '$round_id'";
    if ($conn->query($sql)) {
        $_SESSION['flash_msg'] = "📝 แก้ไขข้อมูลรอบการฝึกงานปี $academic_year เรียบร้อยแล้ว!";
        $_SESSION['flash_msg_type'] = "success";
    }
    header("Location: admin_supervision.php"); exit();
}

// ==========================================
// Action: ลบรอบการฝึกงาน
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_round'])) {
    $round_id = $conn->real_escape_string($_POST['round_id']);
    
    // เช็คและลบไฟล์ PDF ก่อนลบรอบจากฐานข้อมูล
    $check_sql = "SELECT supervision_schedule_pdf FROM internship_rounds WHERE round_id = '$round_id'";
    $check_res = $conn->query($check_sql);
    if ($check_res && $row = $check_res->fetch_assoc()) {
        if (!empty($row['supervision_schedule_pdf'])) {
            // 🚨 แก้ไขการอ้างอิง Path เพื่อลบไฟล์จริงให้ตรงโฟลเดอร์
            $clean_pdf = str_replace("../internship/app/", "", $row['supervision_schedule_pdf']);
            $real_pdf = "../internship/app/" . $clean_pdf;
            if (file_exists($real_pdf)) {
                @unlink($real_pdf);
            }
        }
    }
    
    if ($conn->query("DELETE FROM internship_rounds WHERE round_id = '$round_id'")) {
        $_SESSION['flash_msg'] = "🗑️ ลบรอบการฝึกงานเรียบร้อยแล้ว";
        $_SESSION['flash_msg_type'] = "warning";
    }
    header("Location: admin_supervision.php"); exit();
}

// ==========================================
// Action: เปลี่ยนสถานะ เปิด-ปิด รอบ
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_status'])) {
    $round_id = $conn->real_escape_string($_POST['round_id']);
    $new_status = $conn->real_escape_string($_POST['new_status']);
    if ($conn->query("UPDATE internship_rounds SET round_status = '$new_status' WHERE round_id = '$round_id'")) {
        $_SESSION['flash_msg'] = "🔄 เปลี่ยนสถานะเรียบร้อยแล้ว";
        $_SESSION['flash_msg_type'] = "success";
    }
    header("Location: admin_supervision.php"); exit();
}

// ==========================================
// Action: อัปโหลดไฟล์ PDF
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_pdf'])) {
    $round_id = $conn->real_escape_string($_POST['round_id']);
    $file = $_FILES['pdf_file'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file_ext === 'pdf') {
            $new_filename = "supervision_round_" . $round_id . "_" . time() . ".pdf";
            $target_path = $upload_dir . $new_filename; // Physical path: ../internship/app/...

            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                // ลบไฟล์เก่าออกก่อน
                $old_sql = "SELECT supervision_schedule_pdf FROM internship_rounds WHERE round_id = '$round_id'";
                $old_res = $conn->query($old_sql);
                if ($old_res && $old_row = $old_res->fetch_assoc()) {
                    if (!empty($old_row['supervision_schedule_pdf'])) {
                        // 🚨 แก้ไขการลบไฟล์เก่าให้ตรงโฟลเดอร์แอป
                        $clean_old = str_replace("../internship/app/", "", $old_row['supervision_schedule_pdf']);
                        $real_old = "../internship/app/" . $clean_old;
                        if (file_exists($real_old)) {
                            @unlink($real_old);
                        }
                    }
                }
                // 🚨 บันทึกแบบ Relative Path มาตรฐาน เพื่อให้แอปดึงไปใช้ง่ายๆ
                $db_save_path = "uploads/schedules/" . $new_filename;
                $update_sql = "UPDATE internship_rounds SET supervision_schedule_pdf = '$db_save_path' WHERE round_id = '$round_id'";
                $conn->query($update_sql);
                
                $_SESSION['flash_msg'] = "✅ อัปโหลดไฟล์กำหนดการนิเทศสำเร็จ!";
                $_SESSION['flash_msg_type'] = "success";
            } else {
                $_SESSION['flash_msg'] = "❌ อัปโหลดไฟล์ไม่สำเร็จ กรุณาลองใหม่";
                $_SESSION['flash_msg_type'] = "danger";
            }
        } else {
            $_SESSION['flash_msg'] = "⚠️ กรุณาอัปโหลดไฟล์นามสกุล .pdf เท่านั้น";
            $_SESSION['flash_msg_type'] = "warning";
        }
    }
    header("Location: admin_supervision.php"); exit();
}

// ==========================================
// Action: ลบไฟล์ PDF อย่างเดียว
// ==========================================
if (isset($_GET['action']) && $_GET['action'] == 'delete_pdf' && isset($_GET['round_id'])) {
    $round_id = $conn->real_escape_string($_GET['round_id']);
    $check_sql = "SELECT supervision_schedule_pdf FROM internship_rounds WHERE round_id = '$round_id'";
    $check_res = $conn->query($check_sql);
    if ($check_res && $row = $check_res->fetch_assoc()) {
        if (!empty($row['supervision_schedule_pdf'])) {
            // 🚨 ลบไฟล์ด้วย Physical Path ให้ถูกต้อง
            $clean_pdf = str_replace("../internship/app/", "", $row['supervision_schedule_pdf']);
            $real_pdf = "../internship/app/" . $clean_pdf;
            if (file_exists($real_pdf)) {
                @unlink($real_pdf);
            }
        }
        $conn->query("UPDATE internship_rounds SET supervision_schedule_pdf = NULL WHERE round_id = '$round_id'");
        $_SESSION['flash_msg'] = "🗑️ ลบไฟล์กำหนดการนิเทศเรียบร้อยแล้ว";
        $_SESSION['flash_msg_type'] = "warning";
    }
    header("Location: admin_supervision.php"); exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการกำหนดการนิเทศ ✨</title>
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
        body { font-family: 'Prompt', 'Poppins', sans-serif; background: linear-gradient(135deg, var(--bg-color) 0%, #e2eaff 100%); color: var(--text-dark); min-height: 100vh; overflow-x: hidden; }
        .navbar-custom { background-color: rgba(255, 255, 255, 0.8); backdrop-filter: blur(10px); box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05); border-bottom-left-radius: 20px; border-bottom-right-radius: 20px; }
        .main-card { background: var(--card-bg); border-radius: 25px; box-shadow: 0 10px 30px rgba(162, 210, 255, 0.2); border: none; padding: 30px; }
        
        .btn-rounded { border-radius: 50px; font-weight: 600; padding: 8px 20px; transition: 0.3s; border: none; }
        .btn-rounded:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); }
        .btn-pastel-primary { background-color: var(--primary-soft); color: #0d6efd; }
        
        /* สไตล์ Card รอบฝึกงาน */
        .round-card { border-radius: 20px; border: 1px solid #e2eaff; transition: 0.3s; }
        .round-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(162, 210, 255, 0.3); border-color: var(--primary-soft); }
        .icon-box { width: 50px; height: 50px; background-color: #e2eaff; color: #0d6efd; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        
        .pdf-box { background-color: #f8fbff; border: 1px dashed #a2d2ff; border-radius: 15px; padding: 15px; text-align: center; }
        .form-control, .form-select { border-radius: 15px; padding: 10px; border: 2px solid #e2eaff; }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-custom sticky-top py-3">
        <div class="container">
            <a class="navbar-brand fw-bold" href="admin_dashboard.php"><i class="fas fa-arrow-left text-primary me-2"></i> กลับหน้าหลักแอดมิน</a>
            <div class="d-flex align-items-center">
                <span class="me-3 text-muted" style="font-weight: 500;">👋 สวัสดี, คุณ <?php echo htmlspecialchars($admin_fullname); ?></span>
            </div>
        </div>
    </nav>

    <div class="container mt-5 pb-5">
        <?php if ($msg != ""): ?>
            <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show shadow-sm rounded-pill px-4" role="alert">
                <?php echo $msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="margin-top: 2px;"></button>
            </div>
        <?php endif; ?>

        <div class="main-card">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h3 class="fw-bold mb-1" style="color: var(--text-dark);"><i class="fas fa-chalkboard-teacher text-primary me-2"></i> กำหนดวันนิเทศของอาจารย์</h3>
                    <p class="text-muted mb-0">สร้างรอบ และแนบไฟล์ PDF เพื่อแสดงให้นักศึกษาและอาจารย์ทราบ</p>
                </div>
                <button class="btn btn-pastel-primary btn-rounded shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#addRoundModal">
                    <i class="fas fa-plus me-1"></i> สร้างรอบฝึกงานใหม่
                </button>
            </div>

            <div class="row g-4 mt-2">
                <?php
                $sql = "SELECT * FROM internship_rounds ORDER BY academic_year DESC, start_date DESC";
                $result = $conn->query($sql);

                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $is_open = $row['round_status'] == 'open';
                        $bg_color = $is_open ? "bg-white" : "bg-light opacity-75";
                        $toggle_status = $is_open ? 'closed' : 'open';
                ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card round-card <?php echo $bg_color; ?> h-100 p-3 shadow-sm">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="icon-box me-3"><i class="far fa-calendar-check"></i></div>
                                    <div>
                                        <h5 class="fw-bold mb-0 text-dark">รอบนักศึกษาปีการศึกษา <?php echo htmlspecialchars($row['academic_year']); ?></h5>
                                        <small class="<?php echo $is_open ? 'text-success' : 'text-muted'; ?> fw-bold">
                                            <?php echo $is_open ? '<i class="fas fa-circle fa-xs me-1"></i> เปิดรับสมัคร' : '<i class="far fa-circle fa-xs me-1"></i> ปิดรอบแล้ว'; ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3 ps-2">
                                <div class="small text-muted mb-1"><i class="fas fa-play text-primary me-2"></i> เริ่ม: <?php echo date('d/m/Y', strtotime($row['start_date'])); ?></div>
                                <div class="small text-muted"><i class="fas fa-stop text-danger me-2"></i> จบ: <?php echo date('d/m/Y', strtotime($row['end_date'])); ?></div>
                            </div>

                            <div class="pdf-box mb-3">
                                <?php if (!empty($row['supervision_schedule_pdf'])): 
                                    // 🚨 สร้าง URL ที่ชี้ไปยังโฟลเดอร์ของแอปเสมอ 🚨
                                    $clean_pdf_path = str_replace("../internship/app/", "", $row['supervision_schedule_pdf']);
                                    $pdf_url = "https://student.cet.rmutr.ac.th/coopcet/internship/app/" . $clean_pdf_path;
                                ?>
                                    <h6 class="fw-bold text-success mb-2"><i class="fas fa-check-circle"></i> มีไฟล์กำหนดการแล้ว</h6>
                                    <div class="d-flex gap-2 justify-content-center">
                                        <a href="<?php echo htmlspecialchars($pdf_url); ?>" target="_blank" class="btn btn-primary btn-sm btn-rounded shadow-sm px-3"><i class="fas fa-eye"></i> ดูไฟล์</a>
                                        <a href="admin_supervision.php?action=delete_pdf&round_id=<?php echo $row['round_id']; ?>" class="btn btn-danger btn-sm btn-rounded shadow-sm px-3" onclick="return confirm('ยืนยันการลบไฟล์กำหนดการนี้?');"><i class="fas fa-trash"></i> ลบไฟล์</a>
                                    </div>
                                <?php else: ?>
                                    <h6 class="fw-bold text-muted mb-2"><i class="fas fa-exclamation-circle"></i> ยังไม่มีไฟล์กำหนดการ</h6>
                                    <form method="POST" enctype="multipart/form-data" class="d-flex flex-column gap-2 align-items-center" style="margin:0;">
                                        <input type="hidden" name="round_id" value="<?php echo $row['round_id']; ?>">
                                        <input type="file" name="pdf_file" class="form-control form-control-sm bg-white" accept=".pdf" required>
                                        <button type="submit" name="upload_pdf" class="btn btn-success btn-sm btn-rounded w-100 shadow-sm"><i class="fas fa-upload me-1"></i> อัปโหลด PDF</button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex justify-content-end gap-2 mt-auto border-top pt-3">
                                <button type="button" class="btn btn-outline-primary btn-sm btn-rounded" title="แก้ไขข้อมูล" onclick="openEditRoundModal('<?php echo $row['round_id']; ?>', '<?php echo htmlspecialchars($row['academic_year']); ?>', '<?php echo $row['start_date']; ?>', '<?php echo $row['end_date']; ?>')"><i class="fas fa-edit"></i></button>
                                
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="round_id" value="<?php echo $row['round_id']; ?>">
                                    <input type="hidden" name="new_status" value="<?php echo $toggle_status; ?>">
                                    <button type="submit" name="toggle_status" class="btn btn-light border btn-sm btn-rounded shadow-sm" title="<?php echo $is_open ? 'กดเพื่อปิดรอบ' : 'กดเพื่อเปิดรอบ'; ?>">
                                        <i class="fas fa-toggle-<?php echo $is_open ? 'on text-success' : 'off text-secondary'; ?> fs-5 align-middle"></i>
                                    </button>
                                </form>

                                <form method="POST" style="margin: 0;" onsubmit="return confirm('⚠️ คำเตือน: แน่ใจหรือไม่ที่จะลบรอบนี้?\n(หากมีไฟล์ PDF อยู่ จะถูกลบออกจากระบบด้วย)');">
                                    <input type="hidden" name="round_id" value="<?php echo $row['round_id']; ?>">
                                    <button type="submit" name="delete_round" class="btn btn-outline-danger btn-sm btn-rounded" title="ลบรอบ"><i class="fas fa-trash-alt"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php 
                    }
                } else {
                    echo "<div class='col-12 text-center py-5 text-muted fw-bold'>
                            <i class=" . '"' . "fas fa-folder-open fa-3x mb-3 text-secondary" . '"' . "></i><br>
                            ยังไม่มีข้อมูลรอบการฝึกงานในระบบ
                          </div>";
                }
                ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addRoundModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg border-0" style="border-radius: 25px;">
                <form action="admin_supervision.php" method="POST">
                    <input type="hidden" name="add_round" value="1">
                    <div class="modal-header border-0 pb-0 mt-2 px-4" style="background-color: var(--primary-soft);">
                        <h5 class="modal-title fw-bold text-white"><i class="fas fa-calendar-plus me-2"></i>สร้างรอบฝึกงานใหม่</h5>
                        <button type="button" class="btn-close btn-close-white mt-1 me-1" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body pt-4 px-4 pb-2">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">ปีการศึกษา (ตามรหัสนักศึกษา เช่นรหัส 65 ให้บันทึกเป็นปี 2565)</label>
                            <input type="number" name="academic_year" class="form-control" value="<?php echo date('Y')+543; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">วันเริ่มฝึกงาน</label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">วันสิ้นสุดฝึกงาน</label>
                            <input type="date" name="end_date" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0 pb-4 px-4 mt-2">
                        <button type="button" class="btn btn-rounded btn-light border px-4" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-rounded btn-pastel-primary px-4 shadow-sm"><i class="fas fa-save me-1"></i> บันทึก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editRoundModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg border-0" style="border-radius: 25px;">
                <form action="admin_supervision.php" method="POST">
                    <input type="hidden" name="edit_round" value="1">
                    <input type="hidden" name="edit_round_id" id="edit_round_id">
                    <div class="modal-header border-0 pb-0 mt-2 px-4">
                        <h5 class="modal-title fw-bold text-primary"><i class="fas fa-edit me-2"></i>แก้ไขรอบฝึกงาน</h5>
                        <button type="button" class="btn-close mt-1 me-1" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body pt-4 px-4 pb-2">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">ปีการศึกษา (ตามรหัสนักศึกษา เช่นรหัส 65 ให้บันทึกเป็นปี 2565)</label>
                            <input type="number" name="edit_academic_year" id="edit_academic_year" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">วันเริ่มฝึกงาน</label>
                            <input type="date" name="edit_start_date" id="edit_start_date" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">วันสิ้นสุดฝึกงาน</label>
                            <input type="date" name="edit_end_date" id="edit_end_date" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0 pb-4 px-4 mt-2">
                        <button type="button" class="btn btn-rounded btn-light border px-4" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-rounded btn-primary px-4 shadow-sm"><i class="fas fa-save me-1"></i> บันทึกการแก้ไข</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ฟังก์ชันเปิด Modal แก้ไขและส่งค่าเข้าไป
        function openEditRoundModal(id, year, start, end) { 
            document.getElementById('edit_round_id').value = id; 
            document.getElementById('edit_academic_year').value = year; 
            document.getElementById('edit_start_date').value = start; 
            document.getElementById('edit_end_date').value = end; 
            new bootstrap.Modal(document.getElementById('editRoundModal')).show(); 
        }
    </script>
</body>
</html>