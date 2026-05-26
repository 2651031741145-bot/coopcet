<?php
session_start();
require_once 'db.php';

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

$search_text = isset($_GET['search_text']) ? $conn->real_escape_string($_GET['search_text']) : '';
$search_year = isset($_GET['search_year']) ? $conn->real_escape_string($_GET['search_year']) : '';
$search_status = isset($_GET['search_status']) ? $conn->real_escape_string($_GET['search_status']) : '';

$base_query = "SELECT u.users_name, u.full_name, u.academic_year, (SELECT status FROM student_internships WHERE student_id = u.users_name ORDER BY created_at DESC LIMIT 1) as intern_status FROM users u WHERE u.user_level = 's'";

$where_clauses = [];
if (!empty($search_text)) { $where_clauses[] = "(u.users_name LIKE '%$search_text%' OR u.full_name LIKE '%$search_text%')"; }
if (!empty($search_year)) { $where_clauses[] = "u.academic_year = '$search_year'"; }

$sql_condition = count($where_clauses) > 0 ? " AND " . implode(" AND ", $where_clauses) : "";
$final_sql = "SELECT * FROM ($base_query $sql_condition) as student_data WHERE 1=1";

if (!empty($search_status)) {
    if ($search_status == 'none') { $final_sql .= " AND intern_status IS NULL"; } 
    elseif ($search_status == 'active') { $final_sql .= " AND intern_status IN ('active', 'relocating', 'relocated')"; } 
    elseif ($search_status == 'pending') { $final_sql .= " AND intern_status = 'pending'"; } 
    elseif ($search_status == 'finished') { $final_sql .= " AND intern_status = 'finished'"; }
}

// ตั้งค่าจำนวนการแสดงผล (20 รายชื่อต่อหน้า)
$limit = 20; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$count_result = $conn->query($final_sql);
$total_rows = $count_result->num_rows;
$total_pages = ceil($total_rows / $limit);

// เรียงลำดับจากปีการศึกษาใหม่ไปเก่า
$final_sql .= " ORDER BY academic_year DESC, users_name ASC LIMIT $limit OFFSET $offset";
$result_data = $conn->query($final_sql);

$year_sql = "SELECT DISTINCT academic_year FROM users WHERE user_level = 's' ORDER BY academic_year DESC";
$year_result = $conn->query($year_sql);

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
    <title>Teacher Dashboard 🌻</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Prompt:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg-color: #fffaf0; --primary-soft: #ffe066; --secondary-soft: #ffbfa0; --text-dark: #5c4d3c; }
        body { font-family: 'Prompt', 'Poppins', sans-serif; background: linear-gradient(135deg, var(--bg-color) 0%, #ffeed9 100%); color: var(--text-dark); min-height: 100vh; overflow-x: hidden;}
        .navbar-custom { background-color: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-bottom-left-radius: 20px; border-bottom-right-radius: 20px; }
        .nav-link-custom { color: var(--text-dark); font-weight: 600; padding: 10px 20px; border-radius: 50px; transition: 0.3s; margin-left: 5px; font-size: 0.95rem;}
        .nav-link-custom:hover, .nav-link-custom.active { background-color: var(--primary-soft); color: #856404; }
        .main-card { background: white; border-radius: 25px; box-shadow: 0 10px 30px rgba(255, 191, 160, 0.2); border: none; padding: 30px; }
        
        /* 🚨 สไตล์สำหรับแบนเนอร์ดาวน์โหลดแอป (ธีมอาจารย์) 🚨 */
        .download-banner {
            background: linear-gradient(135deg, #ffe066 0%, #ffbfa0 100%);
            border-radius: 25px;
            padding: 25px 30px;
            color: #5c4d3c;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 10px 25px rgba(255, 191, 160, 0.4);
            margin-bottom: 25px;
            transition: transform 0.3s ease;
        }
        .download-banner:hover {
            transform: translateY(-5px);
        }
        .download-icon {
            font-size: 3rem;
            color: #d97706;
            text-shadow: 2px 2px 10px rgba(255,255,255,0.6);
        }
        .btn-download-app {
            background: white;
            color: #d97706;
            font-weight: bold;
            border-radius: 50px;
            padding: 12px 25px;
            text-decoration: none;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            display: inline-flex;
            align-items: center;
            white-space: nowrap;
        }
        .btn-download-app:hover {
            background: #fffdf5;
            color: #b45309;
            transform: scale(1.05);
        }
        @media (max-width: 576px) {
            .download-banner {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
        }

        .filter-box { background-color: #fdfaf6; border-radius: 20px; padding: 20px; border: 1px solid #f0e6d2; }
        .table thead th { background-color: var(--secondary-soft); color: white; border: none; font-weight: 600; padding: 15px; }
        .table thead th:first-child { border-top-left-radius: 15px; }
        .table thead th:last-child { border-top-right-radius: 15px; }
        .table-hover tbody tr { transition: background-color 0.2s; border-left: 4px solid transparent; }
        .table-hover tbody tr:hover { background-color: #fffdf5; border-left: 4px solid var(--secondary-soft); }
        .table td { vertical-align: middle; padding: 12px 15px; border-color: #f8f9fa;}
        .btn-rounded { border-radius: 50px; font-weight: 500; padding: 5px 15px; font-size: 0.85rem;}
        .btn-action { background-color: var(--primary-soft); color: #856404; }
        .btn-action:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); background-color: #ffd633; }
        .badge-status { border-radius: 50px; padding: 6px 15px; font-weight: 500; font-size: 0.85rem; letter-spacing: 0.3px;}
        .status-none { background-color: #e9ecef; color: #6c757d; }
        .status-active { background-color: #cce5ff; color: #004085; }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-finished { background-color: #d4edda; color: #155724; }
        .form-control, .form-select { border-radius: 15px; padding: 10px 15px; border: 2px solid #f0e6d2; }
        .form-control:focus, .form-select:focus { border-color: var(--secondary-soft); box-shadow: none; }
        
        .page-link { border: none; color: var(--text-dark); margin: 0 5px; border-radius: 50px !important; font-weight: 600; transition: 0.3s; padding: 8px 16px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .page-item.active .page-link { background-color: var(--primary-soft); color: #856404; box-shadow: 0 4px 10px rgba(255, 191, 160, 0.5); }
        .page-link:hover { background-color: var(--secondary-soft); color: white; transform: translateY(-2px); }
        .page-item.disabled .page-link { background-color: #f8f9fa; color: #adb5bd; opacity: 0.7; pointer-events: none; box-shadow: none; }
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
        
        <div class="download-banner">
            <div class="d-flex align-items-center gap-3">
                <div class="download-icon"><i class="fab fa-android"></i></div>
                <div class="text-start">
                    <h5 class="fw-bold mb-1">สะดวกกว่าเดิม! โหลดแอปสำหรับอาจารย์เลย</h5>
                    <p class="mb-0 small" style="opacity: 0.9;">ตรวจสอบคำขอ อนุมัติจบ และประเมินนักศึกษาผ่านมือถือได้ทุกที่ทุกเวลา</p>
                </div>
            </div>
            <a href="app-release.apk" class="btn-download-app" download>
                <i class="fas fa-download me-2"></i> Download Application
            </a>
        </div>

        <div class="main-card">
            <h3 class="fw-bold text-dark mb-4">👩‍🎓 แดชบอร์ดรายชื่อนักศึกษา</h3>

            <form method="GET" action="teacher_dashboard.php" class="filter-box mb-4 shadow-sm">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted ms-2">ค้นหาชื่อ / รหัส 🔍</label>
                        <input type="text" name="search_text" class="form-control" placeholder="พิมพ์ชื่อหรือรหัส..." value="<?php echo htmlspecialchars($search_text); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted ms-2">นักศึกษาปีการศึกษา 📅</label>
                        <select name="search_year" class="form-select">
                            <option value="">-- ทุกปี --</option>
                            <?php 
                            if ($year_result && $year_result->num_rows > 0) {
                                while($yr = $year_result->fetch_assoc()) {
                                    $selected = ($search_year == $yr['academic_year']) ? 'selected' : '';
                                    echo "<option value='".$yr['academic_year']."' $selected>".$yr['academic_year']."</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted ms-2">สถานะการฝึกงาน 📌</label>
                        <select name="search_status" class="form-select">
                            <option value="">-- ทุกสถานะ --</option>
                            <option value="none" <?php if($search_status == 'none') echo 'selected'; ?>>ยังไม่ยื่นเรื่อง</option>
                            <option value="active" <?php if($search_status == 'active') echo 'selected'; ?>>กำลังฝึกงาน</option>
                            <option value="pending" <?php if($search_status == 'pending') echo 'selected'; ?>>รออนุมัติจบ</option>
                            <option value="finished" <?php if($search_status == 'finished') echo 'selected'; ?>>อนุมัติแล้ว</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-action btn-rounded flex-grow-1 shadow-sm"><i class="fas fa-search"></i> ค้นหา</button>
                        <a href="teacher_dashboard.php" class="btn btn-light btn-rounded border text-muted shadow-sm flex-grow-1 text-center" style="text-decoration: none; padding-top: 10px;"><i class="fas fa-undo"></i> ล้างค่า</a>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">รหัสนักศึกษา</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th class="text-center">นักศึกษาปีการศึกษา</th>
                            <th class="text-center">สถานะ</th>
                            <th class="text-center pe-4">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result_data && $result_data->num_rows > 0) {
                            while($row = $result_data->fetch_assoc()) {
                                $status_badge = "<span class='badge-status status-none'><i class='fas fa-minus-circle me-1'></i> ยังไม่ยื่นเรื่อง</span>";
                                if ($row['intern_status'] == 'active' || $row['intern_status'] == 'relocating' || $row['intern_status'] == 'relocated') {
                                    $status_badge = "<span class='badge-status status-active'><i class='fas fa-running me-1'></i> กำลังฝึกงาน</span>";
                                } elseif ($row['intern_status'] == 'pending') {
                                    $status_badge = "<span class='badge-status status-pending'><i class='fas fa-hourglass-half me-1'></i> รออนุมัติจบ</span>";
                                } elseif ($row['intern_status'] == 'finished') {
                                    $status_badge = "<span class='badge-status status-finished'><i class='fas fa-check-circle me-1'></i> อนุมัติแล้ว</span>";
                                }

                                echo "<tr>";
                                echo "<td class='ps-4 fw-bold text-muted'>".htmlspecialchars($row['users_name'])."</td>";
                                echo "<td>".htmlspecialchars($row['full_name'] ?? '-')."</td>";
                                echo "<td class='text-center'><span class='badge bg-light text-dark border rounded-pill'>".htmlspecialchars($row['academic_year'])."</span></td>";
                                echo "<td class='text-center'>".$status_badge."</td>";
                                echo "<td class='text-center pe-4'>";
                                echo "<a href='teacher_student_detail.php?id=".$row['users_name']."' class='btn btn-action btn-rounded btn-sm'><i class='fas fa-search'></i> ดูรายละเอียด</a>";
                                echo "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5' class='text-center py-5 text-muted fw-bold'>ไม่พบข้อมูลที่ค้นหา 😿</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <?php 
            // โค้ดส่วนปุ่มเปลี่ยนหน้า (Pagination)
            if ($total_pages > 1): 
                $q_str = "&search_text=".urlencode($search_text)."&search_year=".urlencode($search_year)."&search_status=".urlencode($search_status);
            ?>
            <nav class="mt-5 pt-4 border-top">
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link bg-white" href="<?php echo ($page <= 1) ? '#' : '?page='.($page - 1).$q_str; ?>"><i class="fas fa-chevron-left me-1"></i> ก่อนหน้า</a>
                    </li>
                    <?php 
                    // ป้องกันปุ่มยาวเกินไปเวลาหน้าเยอะ
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    
                    for($i = $start; $i <= $end; $i++): 
                    ?>
                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                            <a class="page-link bg-white" href="?page=<?php echo $i . $q_str; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link bg-white" href="<?php echo ($page >= $total_pages) ? '#' : '?page='.($page + 1).$q_str; ?>">ถัดไป <i class="fas fa-chevron-right ms-1"></i></a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>

        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>