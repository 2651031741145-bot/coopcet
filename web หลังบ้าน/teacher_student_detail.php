<?php
session_start();
require_once 'db.php';

// ตรวจสอบสิทธิ์การเข้าถึง
if (!isset($_SESSION['users_name']) || $_SESSION['user_level'] != 't' || !isset($_SESSION['session_token'])) {
    header("Location: logout.php"); exit();
}

if (!isset($_GET['id'])) { header("Location: teacher_dashboard.php"); exit(); }

$student_id = $conn->real_escape_string($_GET['id']);

// 1. ดึงข้อมูลพื้นฐานของนักศึกษา
$u_sql = "SELECT * FROM users WHERE users_name = '$student_id'";
$u_info = $conn->query($u_sql)->fetch_assoc();

// 2. ดึงข้อมูลการประเมินล่าสุดของนักศึกษาคนนี้
$eval_sql = "SELECT * FROM teacher_evaluations WHERE student_id = '$student_id' ORDER BY created_at DESC LIMIT 1";
$eval_result = $conn->query($eval_sql);
$evaluation = ($eval_result && $eval_result->num_rows > 0) ? $eval_result->fetch_assoc() : null;

// ==========================================
// ดึงชื่ออาจารย์ที่ล็อกอินอยู่มาแสดงผล
// ==========================================
$logged_in_user = $conn->real_escape_string($_SESSION['users_name']);
$t_name_query = $conn->query("SELECT full_name FROM users WHERE users_name = '$logged_in_user'");
$teacher_fullname = ($t_name_query && $t_name_query->num_rows > 0) ? $t_name_query->fetch_assoc()['full_name'] : 'อาจารย์';

// ฟังก์ชันแปลงชื่อเดือนเป็นภาษาไทย
function getThaiMonth($monthNum) {
    $months = [
        "01"=>"มกราคม", "02"=>"กุมภาพันธ์", "03"=>"มีนาคม", "04"=>"เมษายน", "05"=>"พฤษภาคม", "06"=>"มิถุนายน",
        "07"=>"กรกฎาคม", "08"=>"สิงหาคม", "09"=>"กันยายน", "10"=>"ตุลาคม", "11"=>"พฤศจิกายน", "12"=>"ธันวาคม"
    ];
    return $months[$monthNum] ?? "";
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดนักศึกษา 🌻</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
        :root { --bg-color: #fffaf0; --primary-soft: #ffe066; --secondary-soft: #ffbfa0; --text-dark: #5c4d3c; }
        body { font-family: 'Prompt', 'Poppins', sans-serif; background: linear-gradient(135deg, var(--bg-color) 0%, #ffeed9 100%); color: var(--text-dark); min-height: 100vh; }
        
        .navbar-custom { background-color: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-bottom-left-radius: 20px; border-bottom-right-radius: 20px; }
         .nav-link-custom { color: var(--text-dark); font-weight: 600; padding: 10px 20px; border-radius: 50px; transition: 0.3s; margin-left: 5px; font-size: 0.95rem;}
        .nav-link-custom:hover, .nav-link-custom.active { background-color: var(--primary-soft); color: #856404; }
        
        .card-custom { border-radius: 20px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.05); transition: 0.3s; }
        .card-custom:hover { transform: translateY(-5px); }
        
        .timeline { border-left: 3px solid var(--secondary-soft); padding-left: 20px; margin-left: 10px; }
        .timeline-item { position: relative; margin-bottom: 20px; }
        .timeline-item::before { content: ''; position: absolute; left: -28px; top: 0; width: 14px; height: 14px; background: white; border: 3px solid var(--secondary-soft); border-radius: 50%; }
        
        .btn-rounded { border-radius: 50px; font-weight: 500; padding: 5px 15px; font-size: 0.85rem;}
        .info-label { font-size: 0.85rem; color: #8a7e71; margin-bottom: 2px; display: block; }
        .info-value { font-weight: 500; color: #4a4a4a; font-size: 1rem; }
        .badge-status { padding: 8px 16px; border-radius: 50px; font-weight: 600; }

        /* Style สำหรับ Month Select */
        .month-card { cursor: pointer; border: 2px solid transparent; transition: 0.3s; background: #fff; }
        .month-card:hover { border-color: var(--primary-soft); background-color: #fffdf5; }
        .month-icon { width: 50px; height: 50px; background: #fff4cc; border-radius: 15px; display: flex; align-items: center; justify-content: center; color: #d97706; font-size: 1.5rem; }
        .modal-content { border-radius: 25px; border: none; }
        .modal-header { border-bottom: 1px solid #eee; padding: 1.5rem; }
        
        /* สไตล์ตกแต่งเพิ่มเติมสำหรับ Daily Logs */
        .log-holiday { background-color: #fff8e1 !important; }
        .log-content-box { background: #f0f7ff; padding: 10px 12px; border-radius: 8px; border-left: 4px solid #0d6efd; font-size: 0.9rem; color: #333; }
        .log-problem-box { background: #fff5f5; padding: 10px 12px; border-radius: 8px; border-left: 4px solid #dc3545; font-size: 0.9rem; color: #842029; }
        .log-solution-box { background: #f0fdf4; padding: 10px 12px; border-radius: 8px; border-left: 4px solid #198754; font-size: 0.9rem; color: #0f5132; }
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
                <?php 
                $page = basename($_SERVER['PHP_SELF']); 
                // เช็คหน้าเว็บที่เปิดก่อนหน้านี้ เพื่อให้เมนู Active ตรงกับที่มา
                $ref = isset($_SERVER['HTTP_REFERER']) ? basename(parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH)) : '';
                ?>
                <li class="nav-item"><a class="nav-link nav-link-custom <?php echo ($page=='teacher_dashboard.php' || ($page=='teacher_student_detail.php' && strpos($ref, 'teacher_dashboard.php') !== false)) ? 'active' : ''; ?>" href="teacher_dashboard.php"><i class="fas fa-users"></i> ข้อมูลนักศึกษา</a></li>
<li class="nav-item"><a class="nav-link nav-link-custom <?php echo $page_name=='teacher_evaluation.php'?'active':''; ?>" href="teacher_evaluation.php"><i class="fas fa-clipboard-list"></i> ประเมินบริษัท</a></li>
                 <li class="nav-item"><a class="nav-link nav-link-custom <?php echo $page_name=='teacher_report_evaluation.php'?'active':''; ?>" href="teacher_report_evaluation.php"><i class="fas fa-clipboard-list"></i> ประเมินอาจารย์</a></li>
                <li class="nav-item"><a class="nav-link nav-link-custom <?php echo ($page=='teacher_rounds.php' || ($page=='teacher_student_detail.php' && strpos($ref, 'teacher_rounds.php') !== false)) ? 'active' : ''; ?>" href="teacher_rounds.php"><i class="fas fa-calendar-alt"></i> จัดการรอบฝึกงาน</a></li>
                <li class="nav-item"><a class="nav-link nav-link-custom <?php echo ($page=='teacher_relocation.php' || ($page=='teacher_student_detail.php' && strpos($ref, 'teacher_relocation.php') !== false)) ? 'active' : ''; ?>" href="teacher_relocation.php"><i class="fas fa-exchange-alt"></i> คำขอย้ายสถานที่</a></li>
                <li class="nav-item"><a class="nav-link nav-link-custom <?php echo ($page=='teacher_pending.php' || ($page=='teacher_student_detail.php' && strpos($ref, 'teacher_pending.php') !== false)) ? 'active' : ''; ?>" href="teacher_pending.php"><i class="fas fa-clipboard-check"></i> อนุมัติจบฝึกงาน</a></li>
                <li class="nav-item"><a class="nav-link nav-link-custom <?php echo ($page=='teacher_import_students.php' || ($page=='teacher_student_detail.php' && strpos($ref, 'teacher_import_students.php') !== false)) ? 'active' : ''; ?>" href="teacher_import_students.php"><i class="fas fa-file-import"></i> เพิ่มรายชื่อนักศึกษาใหม่</a></li>
                <li class="nav-item"><a class="nav-link nav-link-custom <?php echo ($page=='teacher_approved.php' || ($page=='teacher_student_detail.php' && strpos($ref, 'teacher_approved.php') !== false)) ? 'active' : ''; ?>" href="teacher_approved.php"><i class="fas fa-archive"></i> คลังข้อมูล</a></li>
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold mb-0">🔍 ข้อมูลของ: <?php echo htmlspecialchars($u_info['full_name'] ?? $student_id); ?></h3>
        </div>

        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card card-custom bg-white h-100">
                    <div class="card-body">
                        <h5 class="fw-bold text-secondary mb-4"><i class="fas fa-history"></i> ประวัติสถานที่ฝึกงาน</h5>
                        <div class="timeline">
                            <?php
                            $hist_sql = "SELECT si.*, c.company_name FROM student_internships si LEFT JOIN companies c ON si.company_id = c.company_id WHERE si.student_id = '$student_id' ORDER BY si.created_at DESC";
                            $hist_result = $conn->query($hist_sql);
                            if($hist_result && $hist_result->num_rows > 0) {
                                while($h = $hist_result->fetch_assoc()) {
                                    $status_color = $h['status'] == 'active' ? 'text-success' : ($h['status'] == 'relocated' ? 'text-danger' : 'text-primary');
                                    echo "<div class='timeline-item'>";
                                    echo "<strong class='d-block'>".htmlspecialchars($h['company_name'] ?? 'ไม่ระบุบริษัท')."</strong>";
                                    echo "<small class='text-muted d-block'>สถานะ: <span class='$status_color fw-bold'>".strtoupper($h['status'])."</span></small>";
                                    if(!empty($h['relocate_reason'])) { echo "<small class='text-danger mt-1 d-block'>เหตุผลที่ย้าย: ".htmlspecialchars($h['relocate_reason'])."</small>"; }
                                    echo "</div>";
                                }
                            } else { echo "<p class='text-muted small'>ยังไม่มีประวัติการเลือกสถานที่</p>"; }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-8 mb-4">
                
                <?php if ($evaluation): ?>
                <div class="card card-custom bg-white mb-4">
                    <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h5 class="fw-bold text-success mb-0"><i class="fas fa-clipboard-check me-2"></i> ผลการประเมินการปฏิบัติงาน</h5>
                            <span class="badge bg-light text-muted border px-3 py-2"><i class="far fa-clock me-1"></i> ประเมินเมื่อ: <?php echo date('d/m/Y H:i', strtotime($evaluation['created_at'])); ?></span>
                        </div>
                    </div>
                    <div class="card-body px-4 pb-4">
                        <div class="row mb-4 mt-3">
                            <div class="col-md-4 text-center border-end">
                                <h6 class="text-muted fw-bold">คะแนนรวม</h6>
                                <h2 class="text-success fw-bold mb-0"><?php echo $evaluation['total_score']; ?> <small class="text-muted fs-6">/ 100</small></h2>
                            </div>
                            <div class="col-md-4 text-center border-end">
                                <h6 class="text-muted fw-bold">ภาพรวมคุณภาพ</h6>
                                <h4 class="text-info fw-bold mb-0"><?php echo htmlspecialchars($evaluation['overall_grade'] ?? '-'); ?></h4>
                            </div>
                            <div class="col-md-4 text-center">
                                <h6 class="text-muted fw-bold">การรับเข้าทำงาน</h6>
                                <h4 class="text-primary fw-bold mb-0"><?php echo htmlspecialchars($evaluation['hire_decision'] ?? '-'); ?></h4>
                            </div>
                        </div>
                        
                        <div class="bg-light p-3 rounded-4 mb-3 border border-white shadow-sm">
                            <h6 class="fw-bold text-dark"><i class="fas fa-star text-warning me-2"></i> จุดเด่นของนักศึกษา</h6>
                            <p class="mb-0 text-muted" style="line-height: 1.6;"><?php echo nl2br(htmlspecialchars($evaluation['strengths'] ?: '-')); ?></p>
                        </div>
                        <div class="bg-light p-3 rounded-4 mb-3 border border-white shadow-sm">
                            <h6 class="fw-bold text-dark"><i class="fas fa-tools text-secondary me-2"></i> ข้อควรปรับปรุง</h6>
                            <p class="mb-0 text-muted" style="line-height: 1.6;"><?php echo nl2br(htmlspecialchars($evaluation['improvements'] ?: '-')); ?></p>
                        </div>
                        <div class="bg-light p-3 rounded-4 border border-white shadow-sm">
                            <h6 class="fw-bold text-dark"><i class="fas fa-comment-dots text-info me-2"></i> ข้อคิดเห็นเพิ่มเติม</h6>
                            <p class="mb-0 text-muted" style="line-height: 1.6;"><?php echo nl2br(htmlspecialchars($evaluation['comments'] ?: '-')); ?></p>
                        </div>
                        
                        <div class="text-end mt-3">
                             <a href="teacher_evaluation.php?search_id=<?php echo urlencode($student_id); ?>" class="btn btn-outline-secondary btn-sm btn-rounded"><i class="fas fa-edit me-1"></i> แก้ไขการประเมิน</a>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="card card-custom mb-4 bg-light">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-clipboard text-muted opacity-50 mb-3" style="font-size: 3rem;"></i>
                        <h5 class="fw-bold text-muted mb-0">นักศึกษารายนี้ยังไม่ได้รับการประเมินผลการปฏิบัติงาน</h5>
                        <a href="teacher_evaluation.php?search_id=<?php echo urlencode($student_id); ?>" class="btn btn-primary btn-sm mt-3 px-4" style="border-radius: 50px;">
                            <i class="fas fa-edit me-1"></i> ไปยังหน้าประเมินผล
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php
                // ดึงข้อมูล latitude และ longitude จากตาราง companies
                $latest_intern_sql = "SELECT si.internship_id, c.company_name, c.address, c.latitude, c.longitude 
                                      FROM student_internships si 
                                      LEFT JOIN companies c ON si.company_id = c.company_id 
                                      WHERE si.student_id = '$student_id' 
                                      ORDER BY si.created_at DESC LIMIT 1";
                $latest_intern_res = $conn->query($latest_intern_sql);
                
                if($latest_intern_res && $latest_intern_res->num_rows > 0) {
                    $latest_intern = $latest_intern_res->fetch_assoc();
                    $intern_id = $latest_intern['internship_id'];
                    
                    $summary_sql = "SELECT * FROM internship_summaries WHERE internship_id = '$intern_id'";
                    $summary_res = $conn->query($summary_sql);
                    $sum_data = ($summary_res && $summary_res->num_rows > 0) ? $summary_res->fetch_assoc() : null;
                    
                    if($sum_data): 
                ?>
                    <div class="card card-custom bg-white mb-4">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-4" style="color: #d97706;"><i class="fas fa-id-badge me-2"></i> ข้อมูลสรุปการฝึกงาน (ปีการศึกษา <?php echo htmlspecialchars($sum_data['internship_year']); ?>)</h5>
                            <div class="row g-4">
                                <div class="col-md-6 border-end"><span class="info-label">ตำแหน่งงาน:</span><div class="info-value"><?php echo htmlspecialchars($sum_data['position'] ?? 'ไม่ระบุ'); ?></div></div>
                                <div class="col-md-6"><span class="info-label">จำนวนนักศึกษาในแผนก:</span><div class="info-value"><?php echo htmlspecialchars($sum_data['student_count']); ?> คน</div></div>
                                <div class="col-md-6 border-end"><span class="info-label">สวัสดิการ:</span><div class="info-value"><?php echo ($sum_data['has_benefits'] == 'yes') ? '<span class="text-success"><i class="fas fa-check-circle"></i> มี: '.htmlspecialchars($sum_data['benefit_details']).'</span>' : '<span class="text-muted">ไม่มี</span>'; ?></div></div>
                                <div class="col-md-6"><span class="info-label">อาจารย์ผู้นิเทศ:</span><div class="info-value"><i class="fas fa-chalkboard-teacher text-info"></i> <?php echo htmlspecialchars($sum_data['supervisor_name']); ?></div></div>
                            </div>
                        </div>
                    </div>

                    <div class="card card-custom mb-4" style="background-color: #f0f8ff;">
                        <div class="card-body d-flex justify-content-between align-items-center p-4">
                            <div><h5 class="fw-bold mb-1"><i class="fas fa-file-pdf text-danger me-2"></i> ไฟล์รายงาน / โปรเจกต์</h5><small class="text-muted">เอกสารสรุปผลการฝึกงาน</small></div>
                            <?php if(!empty($sum_data['project_file_path'])): ?>
                                <a href="../internship/app/<?php echo htmlspecialchars($sum_data['project_file_path']); ?>" target="_blank" class="btn btn-primary btn-rounded shadow-sm px-4"><i class="fas fa-eye"></i> เปิดดูไฟล์ PDF</a>
                            <?php else: ?><span class="badge bg-light text-muted border p-2 px-3 rounded-pill">ยังไม่มีการแนบไฟล์</span><?php endif; ?>
                        </div>
                    </div>

                    <?php if(!empty($latest_intern['latitude']) && !empty($latest_intern['longitude'])): ?>
                    <div class="card card-custom bg-white mb-4">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-3"><i class="fas fa-map-marked-alt text-success me-2"></i> แผนที่สถานประกอบการ</h5>
                            <p class="mb-2 fw-bold text-dark fs-5"><?php echo htmlspecialchars($latest_intern['company_name']); ?></p>
                            <p class="text-muted small mb-3">
                                <i class="fas fa-map-marker-alt text-danger me-1"></i> <?php echo htmlspecialchars($latest_intern['address']); ?>
                                <a href="https://www.google.com/maps?q=<?php echo htmlspecialchars($latest_intern['latitude']); ?>,<?php echo htmlspecialchars($latest_intern['longitude']); ?>" target="_blank" class="badge bg-primary text-white text-decoration-none ms-2 p-2 rounded-pill shadow-sm" style="font-weight: 500; font-family: 'Prompt', sans-serif;">
                                    <i class="fas fa-location-arrow me-1"></i> เปิดใน Google Maps
                                </a>
                            </p>
                            
                            <div id="companyMap" style="height: 350px; border-radius: 15px; border: 1px solid #ddd; z-index: 1;"></div>
                        </div>
                    </div>

                    <script>
                        document.addEventListener("DOMContentLoaded", function() {
                            var lat = <?php echo htmlspecialchars($latest_intern['latitude']); ?>;
                            var lng = <?php echo htmlspecialchars($latest_intern['longitude']); ?>;
                            var compName = "<?php echo addslashes(htmlspecialchars($latest_intern['company_name'])); ?>";
                            
                            // ตั้งค่าพิกัดเริ่มต้นและ Zoom Level
                            var map = L.map('companyMap').setView([lat, lng], 16);

                            // ดึงแผนที่จาก OpenStreetMap
                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                            }).addTo(map);

                            // ปักหมุดพิกัด
                            L.marker([lat, lng]).addTo(map)
                                .bindPopup("<div style='font-family: Prompt; text-align: center;'><b style='font-size: 1rem;'>" + compName + "</b><br><a href='https://www.google.com/maps?q=" + lat + "," + lng + "' target='_blank' class='btn btn-sm btn-primary mt-2 text-white' style='border-radius: 50px; font-size: 0.8rem;'><i class='fas fa-location-arrow me-1'></i> ดูบน Google Maps</a></div>")
                                .openPopup();
                        });
                    </script>
                    <?php endif; ?>

                    <h5 class="fw-bold mb-3 mt-5"><i class="fas fa-calendar-alt text-warning me-2"></i> บันทึกการฝึกงานรายเดือน (Daily Logs)</h5>
                    <div class="row g-3">
                        <?php
                        // ดึงข้อมูล Logs และจัดกลุ่มตามเดือนใน PHP
                        $log_sql = "SELECT *, DATE_FORMAT(log_date, '%Y-%m') as month_key, DATE_FORMAT(log_date, '%m') as m, DATE_FORMAT(log_date, '%Y') as y FROM daily_logs WHERE internship_id = '$intern_id' ORDER BY log_date DESC";
                        $log_res = $conn->query($log_sql);
                        
                        $grouped_logs = [];
                        if($log_res && $log_res->num_rows > 0) {
                            while($row = $log_res->fetch_assoc()) {
                                $grouped_logs[$row['month_key']]['data'][] = $row;
                                
                                // นับชั่วโมงรวม
                                if(!isset($grouped_logs[$row['month_key']]['total_hours'])) $grouped_logs[$row['month_key']]['total_hours'] = 0;
                                $grouped_logs[$row['month_key']]['total_hours'] += $row['hours_worked'];
                                
                                // นับวันหยุดรวม
                                if(!isset($grouped_logs[$row['month_key']]['total_holidays'])) $grouped_logs[$row['month_key']]['total_holidays'] = 0;
                                if($row['is_holiday'] == 1) {
                                    $grouped_logs[$row['month_key']]['total_holidays'] += 1;
                                }

                                $grouped_logs[$row['month_key']]['label'] = getThaiMonth($row['m']) . " " . ($row['y'] + 543);
                            }

                            foreach($grouped_logs as $month_id => $month_info):
                        ?>
                            <div class="col-md-6">
                                <div class="card card-custom month-card p-3" data-bs-toggle="modal" data-bs-target="#modal-<?php echo $month_id; ?>">
                                    <div class="d-flex align-items-center">
                                        <div class="month-icon me-3"><i class="fas fa-calendar-check"></i></div>
                                        <div class="flex-grow-1">
                                            <h6 class="fw-bold mb-1"><?php echo $month_info['label']; ?></h6>
                                            <small class="text-muted">บันทึกทั้งหมด <?php echo count($month_info['data']); ?> วัน</small>
                                            <?php if($month_info['total_holidays'] > 0): ?>
                                                <small class="text-danger ms-2"><i class="fas fa-bed"></i> ลา/หยุด <?php echo $month_info['total_holidays']; ?> วัน</small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-warning text-dark rounded-pill"><?php echo number_format($month_info['total_hours'], 2); ?> ชม.</span>
                                            <i class="fas fa-chevron-right ms-2 text-muted"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="modal fade" id="modal-<?php echo $month_id; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                                    <div class="modal-content">
                                        <div class="modal-header bg-light">
                                            <h5 class="modal-title fw-bold"><i class="fas fa-list-ul text-primary me-2"></i> รายละเอียดงานเดือน<?php echo $month_info['label']; ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-hover mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th class="ps-4 py-3 text-nowrap" width="120">วันที่</th>
                                                            <th class="py-3" width="30%">รายละเอียดงาน</th>
                                                            <th class="py-3" width="25%">ปัญหาที่พบ</th>
                                                            <th class="py-3" width="25%">วิธีแก้ไข</th>
                                                            <th class="py-3 text-center text-nowrap" width="10%">ชั่วโมง</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach($month_info['data'] as $detail): 
                                                            $is_holiday_row = ($detail['is_holiday'] == 1) ? 'log-holiday' : '';
                                                        ?>
                                                        <tr class="<?php echo $is_holiday_row; ?>">
                                                            <td class="ps-4 fw-bold align-top pt-3 text-nowrap"><?php echo date('d/m/Y', strtotime($detail['log_date'])); ?></td>
                                                            
                                                            <td class="align-top pt-3">
                                                                <?php if($detail['is_holiday'] == 1): ?>
                                                                    <div class="fw-bold text-warning"><i class="fas fa-bed me-1"></i> <?php echo htmlspecialchars($detail['work_done']); ?></div>
                                                                <?php else: ?>
                                                                    <div class="log-content-box" style="white-space: pre-line;"><?php echo htmlspecialchars($detail['work_done']); ?></div>
                                                                <?php endif; ?>
                                                            </td>
                                                            
                                                            <td class="align-top pt-3">
                                                                <?php if(!empty($detail['problem_found']) && trim($detail['problem_found']) != '-'): ?>
                                                                    <div class="log-problem-box"><i class="fas fa-exclamation-circle me-1"></i> <?php echo htmlspecialchars($detail['problem_found']); ?></div>
                                                                <?php else: ?>
                                                                    <span class="text-muted">-</span>
                                                                <?php endif; ?>
                                                            </td>

                                                            <td class="align-top pt-3">
                                                                <?php if(!empty($detail['solution']) && trim($detail['solution']) != '-'): ?>
                                                                    <div class="log-solution-box"><i class="fas fa-check-circle me-1"></i> <?php echo htmlspecialchars($detail['solution']); ?></div>
                                                                <?php else: ?>
                                                                    <span class="text-muted">-</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            
                                                            <td class="text-center fw-bold text-muted align-top pt-3">
                                                                <?php echo number_format($detail['hours_worked'], 2); ?>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                    <tfoot class="table-light">
                                                        <tr>
                                                            <td colspan="4" class="text-end fw-bold py-3">
                                                                <span class="text-danger me-3"><i class="fas fa-bed"></i> ลา/หยุดรวม: <?php echo $month_info['total_holidays']; ?> วัน</span>
                                                                <span class="text-dark">รวมชั่วโมงทำงานสุทธิ:</span>
                                                            </td>
                                                            <td class="text-center fw-bold text-primary py-3 fs-5"><?php echo number_format($month_info['total_hours'], 2); ?></td>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php 
                            endforeach; 
                        } else { 
                            echo "<div class='col-12'><div class='alert alert-light text-center py-5 rounded-4 border shadow-sm'>ยังไม่มีการบันทึกงานรายวัน 📝</div></div>"; 
                        } 
                        ?>
                    </div>

                <?php 
                    else: 
                        echo "<div class='alert alert-info rounded-4 shadow-sm py-4 text-center'><i class='fas fa-info-circle fa-2x mb-3'></i><br>นักศึกษาคนนี้ยังไม่ได้กรอกข้อมูลสรุปการฝึกงาน</div>";
                    endif; 
                } else { 
                    echo "<div class='alert alert-warning rounded-4 shadow-sm py-4 text-center'><i class='fas fa-exclamation-circle fa-2x mb-3 text-warning'></i><br>นักศึกษาคนนี้ยังไม่ได้เริ่มการฝึกงาน</div>"; 
                } 
                ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>