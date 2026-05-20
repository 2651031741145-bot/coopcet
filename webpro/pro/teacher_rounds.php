<?php
session_start();
require_once 'db.php';

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

$msg = "";
$msg_type = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_round'])) {
        $academic_year = $conn->real_escape_string($_POST['academic_year']);
        $start_date = $conn->real_escape_string($_POST['start_date']);
        $end_date = $conn->real_escape_string($_POST['end_date']);

        $sql = "INSERT INTO internship_rounds (academic_year, start_date, end_date, created_by) 
                VALUES ('$academic_year', '$start_date', '$end_date', '$teacher_username')";
        if ($conn->query($sql)) {
            $msg = "✨ เพิ่มรอบการฝึกงานปี $academic_year สำเร็จ!";
            $msg_type = "success";
        }
    }

    if (isset($_POST['edit_round'])) {
        $round_id = $conn->real_escape_string($_POST['edit_round_id']);
        $academic_year = $conn->real_escape_string($_POST['edit_academic_year']);
        $start_date = $conn->real_escape_string($_POST['edit_start_date']);
        $end_date = $conn->real_escape_string($_POST['edit_end_date']);

        $sql = "UPDATE internship_rounds SET academic_year = '$academic_year', start_date = '$start_date', end_date = '$end_date' WHERE round_id = '$round_id'";
        if ($conn->query($sql)) {
            $msg = "📝 แก้ไขข้อมูลรอบการฝึกงานปี $academic_year เรียบร้อยแล้ว!";
            $msg_type = "success";
        }
    }

    if (isset($_POST['delete_round'])) {
        $round_id = $conn->real_escape_string($_POST['round_id']);
        if ($conn->query("DELETE FROM internship_rounds WHERE round_id = '$round_id'")) {
            $msg = "🗑️ ลบรอบการฝึกงานเรียบร้อยแล้ว";
            $msg_type = "warning";
        }
    }

    if (isset($_POST['toggle_status'])) {
        $round_id = $conn->real_escape_string($_POST['round_id']);
        $new_status = $conn->real_escape_string($_POST['new_status']);
        if ($conn->query("UPDATE internship_rounds SET round_status = '$new_status' WHERE round_id = '$round_id'")) {
            $msg = "🔄 เปลี่ยนสถานะเรียบร้อยแล้ว";
            $msg_type = "success";
        }
    }

    if (isset($_POST['set_custom_date'])) {
        $internship_id = $conn->real_escape_string($_POST['internship_id']);
        $c_start = $conn->real_escape_string($_POST['custom_start']);
        $c_end = $conn->real_escape_string($_POST['custom_end']);

        $start_val = empty($c_start) ? "NULL" : "'$c_start'";
        $end_val = empty($c_end) ? "NULL" : "'$c_end'";

        $sql = "UPDATE student_internships SET custom_start_date = $start_val, custom_end_date = $end_val WHERE internship_id = '$internship_id'";
        if ($conn->query($sql)) {
            $msg = "📅 อัปเดตวันฝึกงานเฉพาะบุคคลเรียบร้อยแล้ว!";
            $msg_type = "success";
            $_GET['tab'] = 'custom';
        }
    }
}
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'standard';

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
    <title>จัดการรอบฝึกงาน 📅</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Prompt:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-color: #fffaf0;
            --primary-soft: #ffe066;
            --secondary-soft: #ffbfa0;
            --success-soft: #c3f0ca;
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

        .nav-link-custom {
            color: var(--text-dark);
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 50px;
            transition: 0.3s;
            margin-left: 5px;
        }

        .nav-link-custom:hover,
        .nav-link-custom.active {
            background-color: var(--primary-soft);
            color: #856404;
        }

        .main-card {
            background: white;
            border-radius: 25px;
            box-shadow: 0 10px 30px rgba(255, 191, 160, 0.2);
            border: none;
            padding: 30px;
        }

        .nav-pills .nav-link {
            border-radius: 50px;
            font-weight: 600;
            color: var(--text-dark);
            padding: 10px 25px;
            margin-right: 10px;
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
        }

        .nav-pills .nav-link.active {
            background-color: #0d9488;
            color: white;
            border-color: #0d9488;
            box-shadow: 0 4px 10px rgba(13, 148, 136, 0.3);
        }

        /* การ์ดรอบปกติ */
        .round-card {
            border-radius: 20px;
            border: 1px solid #f0e6d2;
            transition: 0.3s;
        }

        .round-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
        }

        .icon-box {
            width: 50px;
            height: 50px;
            background-color: #e0f2fe;
            color: #0284c7;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .btn-rounded {
            border-radius: 50px;
            font-weight: 500;
            padding: 5px 15px;
            font-size: 0.85rem;
        }

        .btn-add {
            background-color: #0d9488;
            color: white;
            padding: 10px 25px;
        }

        .btn-add:hover {
            background-color: #0f766e;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13, 148, 136, 0.3);
        }

        .form-control,
        .form-select {
            border-radius: 15px;
            padding: 12px;
            border: 2px solid #f0e6d2;
        }

        /* การ์ดรอบเฉพาะบุคคล */
        .custom-round-card {
            border: 1px solid #ff9800;
            border-radius: 15px;
            padding: 20px;
            background: white;
            box-shadow: 0 4px 15px rgba(255, 152, 0, 0.1);
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            transition: 0.2s;
        }

        .custom-round-card:hover {
            box-shadow: 0 6px 20px rgba(255, 152, 0, 0.2);
            transform: translateY(-2px);
        }

        .icon-user-orange {
            width: 55px;
            height: 55px;
            background-color: #fff4e6;
            color: #ff9800;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            margin-right: 20px;
            flex-shrink: 0;
        }

        .btn-edit-orange {
            background: white;
            color: #ff9800;
            border: 1px solid #ff9800;
            border-radius: 50px;
            padding: 6px 25px;
            font-weight: 500;
            font-size: 0.95rem;
            transition: 0.3s;
            white-space: nowrap;
        }

        .btn-edit-orange:hover {
            background: #fff4e6;
            transform: translateY(-2px);
        }

        .text-orange-date {
            color: #ff9800;
            font-weight: 600;
            font-size: 0.95rem;
            margin-top: 5px;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-custom sticky-top py-3">
        <div class="container-fluid px-4">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="teacher_dashboard.php">
                <i class="fas fa-chalkboard-teacher text-warning me-2"></i> ระบบอาจารย์ 🌻
                <span class="ms-3 fs-6 fw-normal text-muted border-start ps-3" style="font-size: 0.95rem !important;">
                    <i class="fas fa-user-circle text-secondary me-1"></i>
                    <?php echo htmlspecialchars($teacher_fullname); ?>
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

                    <li class="nav-item"><a
                            class="nav-link nav-link-custom <?php echo $page == 'teacher_evaluation.php' ? 'active' : ''; ?>"
                            href="teacher_evaluation.php"><i class="fas fa-clipboard-list"></i> ประเมิน</a></li>

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
                        <a href="logout.php"
                            class="btn btn-danger rounded-pill px-4 shadow-sm d-flex align-items-center"
                            style="font-weight: 500; padding-top: 8px; padding-bottom: 8px;">
                            <i class="fas fa-sign-out-alt me-2"></i> ออกจากระบบ
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-4 pb-5">
        <?php if ($msg != ""): ?>
            <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show shadow-sm rounded-pill px-4"
                role="alert">
                <?php echo $msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="main-card">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <h3 class="fw-bold text-dark mb-0"><i class="fas fa-calendar-alt text-primary"></i> จัดการรอบการฝึกงาน
                </h3>
                <?php if ($active_tab == 'standard'): ?>
                    <button class="btn btn-add btn-rounded" data-bs-toggle="modal" data-bs-target="#addRoundModal"><i
                            class="fas fa-plus"></i> เพิ่มรอบฝึกงาน</button>
                <?php endif; ?>
            </div>

            <ul class="nav nav-pills mb-4">
                <li class="nav-item"><a class="nav-link <?php echo $active_tab == 'standard' ? 'active' : ''; ?>"
                        href="?tab=standard">รอบปกติ (ตามรหัสนักศึกษา)</a></li>
                <li class="nav-item"><a class="nav-link <?php echo $active_tab == 'custom' ? 'active' : ''; ?>"
                        href="?tab=custom">รอบเฉพาะบุคคล (ย้ายสถานที่)</a></li>
            </ul>

            <div class="tab-content">
                <?php if ($active_tab == 'standard'): ?>
                    <div class="row">
                        <?php
                        $sql = "SELECT * FROM internship_rounds ORDER BY academic_year DESC, start_date DESC";
                        $result = $conn->query($sql);
                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $is_open = $row['round_status'] == 'open';
                                $bg_color = $is_open ? "bg-white" : "bg-light";
                                $toggle_status = $is_open ? 'closed' : 'open';
                                ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card round-card <?php echo $bg_color; ?> h-100 p-3 shadow-sm">
                                        <div class="d-flex align-items-center justify-content-between mb-3">
                                            <div class="d-flex align-items-center">
                                                <div class="icon-box me-3"><i class="far fa-calendar-check"></i></div>
                                                <div>
                                                    <h5 class="fw-bold mb-0">รอบนักศึกษาปีการศึกษา
                                                        <?php echo htmlspecialchars($row['academic_year']); ?></h5>
                                                    <small
                                                        class="<?php echo $is_open ? 'text-success' : 'text-muted'; ?> fw-bold"><?php echo $is_open ? '● เปิดรับสมัคร' : '○ ปิดรับสมัคร'; ?></small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="small text-muted mb-1"><i class="fas fa-play text-success me-1"></i> เริ่ม:
                                                <?php echo date('d/m/Y', strtotime($row['start_date'])); ?></div>
                                            <div class="small text-muted"><i class="fas fa-stop text-danger me-1"></i> จบ:
                                                <?php echo date('d/m/Y', strtotime($row['end_date'])); ?></div>

                                            <div class="mt-3">
                                                <?php if (!empty($row['supervision_schedule_pdf'])): ?>
                                                    <a href="https://student.cet.rmutr.ac.th/coopcet/internship/app/<?php echo htmlspecialchars($row['supervision_schedule_pdf']); ?>"
                                                        target="_blank"
                                                        class="btn btn-sm btn-outline-primary btn-rounded w-100 shadow-sm">
                                                        <i class="fas fa-file-pdf me-1"></i> ดูกำหนดการนิเทศ
                                                    </a>
                                                <?php else: ?>
                                                    <div class="text-center w-100 p-1 bg-light border rounded-pill small text-muted">
                                                        <i class="fas fa-info-circle me-1"></i> ยังไม่มีไฟล์กำหนดการ
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-end gap-2 mt-auto border-top pt-3">
                                            <button type="button" class="btn btn-outline-info btn-sm btn-rounded"
                                                title="แก้ไขข้อมูล"
                                                onclick="openEditRoundModal('<?php echo $row['round_id']; ?>', '<?php echo htmlspecialchars($row['academic_year']); ?>', '<?php echo $row['start_date']; ?>', '<?php echo $row['end_date']; ?>')"><i
                                                    class="fas fa-edit"></i></button>
                                            <form method="POST" style="margin: 0;">
                                                <input type="hidden" name="round_id" value="<?php echo $row['round_id']; ?>">
                                                <input type="hidden" name="new_status" value="<?php echo $toggle_status; ?>">
                                                <button type="submit" name="toggle_status"
                                                    class="btn btn-light border btn-sm btn-rounded"><i
                                                        class="fas fa-toggle-<?php echo $is_open ? 'on text-success' : 'off text-secondary'; ?> fs-5 align-middle"></i></button>
                                            </form>
                                            <form method="POST" style="margin: 0;"
                                                onsubmit="return confirm('แน่ใจหรือไม่ที่จะลบ?');">
                                                <input type="hidden" name="round_id" value="<?php echo $row['round_id']; ?>">
                                                <button type="submit" name="delete_round"
                                                    class="btn btn-outline-danger btn-sm btn-rounded"><i
                                                        class="fas fa-trash-alt"></i></button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php }
                        } else {
                            echo "<div class='col-12 text-center py-5 text-muted'>ยังไม่มีการสร้างรอบการฝึกงาน</div>";
                        } ?>
                    </div>

                <?php
                // =====================================
                // TAB 2: รอบเฉพาะบุคคล แบบ Card UI ใหม่
                // =====================================
            elseif ($active_tab == 'custom'):
                ?>
                    <div class="row px-2">
                        <?php
                        // ดึงข้อมูลเฉพาะนักศึกษาที่อยู่ใน "รอบพิเศษ"
                        // 1. เพิ่งอนุมัติย้าย (relocated) 
                        // 2. หรือ เลือกที่ใหม่แล้วกำลังฝึกงาน (active) โดยต้องมีประวัติว่าเคยขอย้าย (relocated) มาก่อน
                        // *หากสถานะล่าสุดเป็น finished จะหายไปจากหน้านี้อัตโนมัติ
                        $cus_sql = "
                            SELECT si.internship_id, si.custom_start_date, si.custom_end_date, 
                                   u.users_name, u.full_name, c.company_name 
                            FROM student_internships si 
                            JOIN users u ON si.student_id = u.users_name 
                            LEFT JOIN companies c ON si.company_id = c.company_id 
                            WHERE si.internship_id = (
                                SELECT MAX(internship_id) 
                                FROM student_internships 
                                WHERE student_id = si.student_id
                            )
                            AND (
                                si.status = 'relocated' 
                                OR (
                                    si.status = 'active' 
                                    AND EXISTS (
                                        SELECT 1 FROM student_internships 
                                        WHERE student_id = si.student_id AND status = 'relocated'
                                    )
                                )
                            )
                            ORDER BY si.created_at DESC
                        ";

                        $cus_res = $conn->query($cus_sql);

                        if ($cus_res && $cus_res->num_rows > 0) {
                            while ($c = $cus_res->fetch_assoc()) {

                                if (!empty($c['custom_start_date']) && !empty($c['custom_end_date'])) {
                                    $date_display = "เริ่ม: " . date('Y-m-d', strtotime($c['custom_start_date'])) . " ถึง <br>" . date('Y-m-d', strtotime($c['custom_end_date']));
                                } else {
                                    $date_display = "รอการอัปเดตวันฝึกงาน";
                                }
                                ?>
                                <div class="col-md-6 col-lg-5 px-2">
                                    <div class="custom-round-card">
                                        <div class="icon-user-orange"><i class="fas fa-user"></i></div>
                                        <div class="flex-grow-1 pe-2">
                                            <h5 class="fw-bold mb-1 text-dark" style="font-size: 1.1rem;">
                                                <?php echo htmlspecialchars($c['full_name']); ?></h5>
                                            <div class="text-muted small mb-1" style="line-height: 1.4;">
                                                สถานที่: <?php echo htmlspecialchars($c['company_name'] ?? 'ไม่ระบุสถานที่'); ?>
                                            </div>
                                            <div class="text-orange-date"><?php echo $date_display; ?></div>
                                        </div>
                                        <div>
                                            <button class="btn btn-edit-orange"
                                                onclick="openCustomDateModal('<?php echo $c['internship_id']; ?>', '<?php echo $c['custom_start_date']; ?>', '<?php echo $c['custom_end_date']; ?>', '<?php echo htmlspecialchars($c['full_name'], ENT_QUOTES); ?>')">แก้ไข</button>
                                        </div>
                                    </div>
                                </div>
                            <?php }
                        } else {
                            echo "<div class='col-12 text-center py-5 text-muted fw-bold'>ไม่มีนักศึกษาที่อยู่ในรอบเฉพาะบุคคล (ย้ายสถานที่) ในขณะนี้ 🎉</div>";
                        } ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addRoundModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px;">
                <form method="POST">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold text-dark"><i class="fas fa-calendar-plus text-primary me-2"></i>
                            สร้างรอบฝึกงานใหม่</h5><button type="button" class="btn-close"
                            data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3"><label class="form-label fw-bold small text-muted">ปีการศึกษา
                                (ตามรหัสนักศึกษา เช่นรหัส 65 ให้บันทึกเป็นปี 2565)</label><input type="number" name="academic_year" class="form-control"
                                value="<?php echo date('Y') + 543; ?>" required></div>
                        <div class="mb-3"><label
                                class="form-label fw-bold small text-muted">วันเริ่มฝึกงาน</label><input type="date"
                                name="start_date" class="form-control" required></div>
                        <div class="mb-3"><label
                                class="form-label fw-bold small text-muted">วันสิ้นสุดฝึกงาน</label><input type="date"
                                name="end_date" class="form-control" required></div>
                    </div>
                    <div class="modal-footer border-0"><button type="button" class="btn btn-light btn-rounded px-4"
                            data-bs-dismiss="modal">ยกเลิก</button><button type="submit" name="add_round"
                            class="btn btn-add btn-rounded px-4">บันทึกข้อมูล</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editRoundModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px;">
                <form method="POST">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold text-dark"><i class="fas fa-edit text-info me-2"></i>
                            แก้ไขรอบฝึกงาน</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="edit_round_id" id="edit_round_id">
                        <div class="mb-3"><label class="form-label fw-bold small text-muted">ปีการศึกษา
                                (ตามรหัสนักศึกษา เช่นรหัส 65 ให้บันทึกเป็นปี 2565)</label><input type="number" name="edit_academic_year" id="edit_academic_year"
                                class="form-control" required></div>
                        <div class="mb-3"><label
                                class="form-label fw-bold small text-muted">วันเริ่มฝึกงาน</label><input type="date"
                                name="edit_start_date" id="edit_start_date" class="form-control" required></div>
                        <div class="mb-3"><label
                                class="form-label fw-bold small text-muted">วันสิ้นสุดฝึกงาน</label><input type="date"
                                name="edit_end_date" id="edit_end_date" class="form-control" required></div>
                    </div>
                    <div class="modal-footer border-0"><button type="button" class="btn btn-light btn-rounded px-4"
                            data-bs-dismiss="modal">ยกเลิก</button><button type="submit" name="edit_round"
                            class="btn btn-info text-white btn-rounded px-4">บันทึกการแก้ไข</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="customDateModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px;">
                <form method="POST" action="teacher_rounds.php?tab=custom">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold text-dark"><i class="fas fa-user-clock text-warning me-2"></i>
                            แก้ไขวันที่เฉพาะบุคคล</h5><button type="button" class="btn-close"
                            data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="internship_id" id="cus_intern_id">
                        <p class="mb-3 text-primary fw-bold" id="cus_student_name"></p>
                        <div class="mb-3"><label
                                class="form-label fw-bold small text-muted">วันเริ่มฝึกงาน</label><input type="date"
                                name="custom_start" id="cus_start" class="form-control"></div>
                        <div class="mb-3"><label
                                class="form-label fw-bold small text-muted">วันสิ้นสุดฝึกงาน</label><input type="date"
                                name="custom_end" id="cus_end" class="form-control"></div>
                    </div>
                    <div class="modal-footer border-0"><button type="button" class="btn btn-light btn-rounded px-4"
                            data-bs-dismiss="modal">ยกเลิก</button><button type="submit" name="set_custom_date"
                            class="btn btn-add btn-rounded px-4"
                            style="background-color: #ff9800; border:none;">อัปเดตข้อมูล</button></div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openCustomDateModal(id, start, end, name) {
            document.getElementById('cus_intern_id').value = id;
            document.getElementById('cus_start').value = start;
            document.getElementById('cus_end').value = end;
            document.getElementById('cus_student_name').innerText = "นักศึกษา: " + name;
            new bootstrap.Modal(document.getElementById('customDateModal')).show();
        }
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