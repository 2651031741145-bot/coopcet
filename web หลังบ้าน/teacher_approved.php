<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['users_name']) || $_SESSION['user_level'] != 't' || !isset($_SESSION['session_token'])) {
    header("Location: logout.php"); exit();
}

$msg = ""; $msg_type = "";

// ==========================================
// จัดการระบบแบ่งหน้า (Pagination) และ ตัวกรองปี (Filter)
// ==========================================
$filter_year = isset($_GET['year']) ? $conn->real_escape_string($_GET['year']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

$limit = 20; // จำนวนรายการต่อหน้า
$offset = ($page - 1) * $limit;

// ---- แก้ไขเงื่อนไขการค้นหา: ดึงเฉพาะที่ can_publish = 'yes' และสถานะการฝึกงานเป็น 'finished' ----
$where_clause = "s.can_publish = 'yes' AND si.status = 'finished'";
if (!empty($filter_year)) {
    $where_clause .= " AND s.internship_year = '$filter_year'";
}

// นับจำนวนข้อมูลทั้งหมดเพื่อคำนวณหน้า (ต้อง JOIN ตาราง student_internships ด้วย)
$count_sql = "SELECT COUNT(*) as total 
              FROM internship_summaries s 
              JOIN student_internships si ON s.internship_id = si.internship_id 
              WHERE $where_clause";
$total_res = $conn->query($count_sql);
$total_rows = $total_res ? $total_res->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_rows / $limit);

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
    <title>คลังข้อมูลผู้ผ่านการฝึกงาน 🌻</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg-color: #fffaf0; --primary-soft: #ffe066; --text-dark: #5c4d3c; }
        body { font-family: 'Prompt', 'Poppins', sans-serif; background: linear-gradient(135deg, var(--bg-color) 0%, #ffeed9 100%); color: var(--text-dark); min-height: 100vh; }
         .nav-link-custom { color: var(--text-dark); font-weight: 600; padding: 10px 20px; border-radius: 50px; transition: 0.3s; margin-left: 5px; font-size: 0.95rem;}
        .nav-link-custom:hover, .nav-link-custom.active { background-color: var(--primary-soft); color: #856404; }
        .main-card { background: white; border-radius: 25px; box-shadow: 0 10px 30px rgba(255, 191, 160, 0.2); border: none; padding: 30px; }
        .btn-rounded { border-radius: 50px; font-weight: 500; padding: 5px 15px; font-size: 0.85rem;}
        
        /* ตกแต่งส่วนตารางให้คล้ายต้นฉบับ */
        .table-custom th { background-color: #f8f9fa; color: #5c4d3c; border-bottom: 2px solid #e9ecef; font-weight: 600; padding: 12px; }
        .table-custom td { vertical-align: middle; padding: 12px; border-bottom: 1px solid #f1f1f1; }
        .table-custom tbody tr:hover { background-color: #fffdf5; }
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

<div class="container-fluid mt-4 pb-5 px-4">
    <?php if($msg != ""): ?>
        <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show shadow-sm rounded-pill px-4 w-50 mx-auto" role="alert">
            <?php echo $msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="main-card">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
            <h4 class="fw-bold mb-0 text-dark"><i class="fas fa-folder-open text-warning me-2"></i> คลังข้อมูลผู้ผ่านการฝึกงาน</h4>
            
            <form method="GET" action="teacher_approved.php" class="d-flex align-items-center bg-light p-2 rounded-pill border">
                <i class="fas fa-filter text-muted ms-2 me-2"></i>
                <label class="me-2 fw-bold text-muted mb-0">ปีที่ฝึก:</label>
                <select name="year" class="form-select form-select-sm border-0 bg-transparent fw-bold text-dark" style="width: 120px; box-shadow: none;" onchange="this.form.submit()">
                    <option value="">ทั้งหมด</option>
                    <?php
                    // เพิ่ม JOIN เพื่อให้ตัวกรองดึงเฉพาะปีที่มีเด็ก 'finished' เท่านั้น
                    $years_list_sql = "SELECT DISTINCT s.internship_year 
                                       FROM internship_summaries s 
                                       JOIN student_internships si ON s.internship_id = si.internship_id 
                                       WHERE s.can_publish = 'yes' AND si.status = 'finished' 
                                       ORDER BY s.internship_year DESC";
                    $years_list_res = $conn->query($years_list_sql);
                    if ($years_list_res) {
                        while($y = $years_list_res->fetch_assoc()) {
                            $selected = ($filter_year == $y['internship_year']) ? 'selected' : '';
                            echo "<option value='".$y['internship_year']."' $selected>".$y['internship_year']."</option>";
                        }
                    }
                    ?>
                </select>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-custom">
                <thead>
                    <tr>
                        <th>ปีที่ฝึก</th>
                        <th>รหัส</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th>สถานที่</th>
                        <th>ตำแหน่ง</th>
                        <th>สวัสดิการ</th>
                        <th>อาจารย์นิเทศ</th>
                        <th class="text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // คิวรีดึงข้อมูล (เปลี่ยน LEFT JOIN student_internships เป็น JOIN เฉยๆ เพื่อบังคับกรองเฉพาะตัวที่มีข้อมูลและสถานะ finished)
                    $data_sql = "
                        SELECT 
                            s.summary_id, 
                            s.internship_year, 
                            u.users_name AS student_id, 
                            u.full_name AS student_name, 
                            c.company_name, 
                            s.position, 
                            s.has_benefits,
                            s.benefit_details, 
                            s.supervisor_name
                        FROM internship_summaries s
                        JOIN users u ON s.student_id = u.users_name
                        JOIN student_internships si ON s.internship_id = si.internship_id
                        LEFT JOIN companies c ON si.company_id = c.company_id
                        WHERE $where_clause
                        ORDER BY s.internship_year DESC, u.users_name ASC
                        LIMIT $limit OFFSET $offset
                    ";
                    
                    $data_res = $conn->query($data_sql);

                    if ($data_res && $data_res->num_rows > 0) {
                        while($d = $data_res->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>".$d['internship_year']."</td>";
                            echo "<td>".$d['student_id']."</td>";
                            echo "<td>".htmlspecialchars($d['student_name'])."</td>";
                            echo "<td>".htmlspecialchars($d['company_name'] ?? '-')."</td>";
                            echo "<td>".htmlspecialchars($d['position'] ?? '-')."</td>";
                            
                            // เช็คเงื่อนไขสวัสดิการ
                            if ($d['has_benefits'] == 'yes' && !empty($d['benefit_details'])) {
                                echo "<td>".htmlspecialchars($d['benefit_details'])."</td>";
                            } else {
                                echo "<td class='text-muted'>ไม่มี</td>";
                            }
                            
                            echo "<td>".htmlspecialchars($d['supervisor_name'] ?? 'ไม่มี')."</td>";
                            
                            // ปุ่มดูรายละเอียด (ลิงก์ไปยังหน้า teacher_student_detail.php พร้อมแนบรหัสนักศึกษา)
                            echo "<td class='text-center'>
                                    <a href='teacher_student_detail.php?id=".$d['student_id']."' class='btn btn-info btn-sm btn-rounded text-white'>
                                        <i class='fas fa-search'></i> รายละเอียด
                                    </a>
                                  </td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='8' class='text-center py-5 text-muted'>ไม่มีข้อมูลในคลัง</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation" class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo !empty($filter_year) ? '&year='.$filter_year : ''; ?>">ก่อนหน้า</a>
                </li>
                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($filter_year) ? '&year='.$filter_year : ''; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo !empty($filter_year) ? '&year='.$filter_year : ''; ?>">ถัดไป</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>

    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>