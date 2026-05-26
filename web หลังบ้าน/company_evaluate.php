<?php
// 🚨 เปิดการแสดงผล Error เพื่อเช็คข้อผิดพลาด
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db.php';

// ฟังก์ชันสำหรับสร้างคำถามแบบ Radio Button
function buildCompanyRadio($name, $title, $desc) {
    $html = "<div class='score-box'><label class='fw-bold text-dark mb-1'>$title</label><p class='small text-muted mb-3'>$desc</p><div class='d-flex flex-wrap gap-4'>";
    $labels = [4 => '4 (ดีมาก)', 3 => '3 (ดี)', 2 => '2 (พอใช้)', 1 => '1 (ควรปรับปรุง)'];
    foreach($labels as $i => $label) {
        $html .= "<div class='form-check'>
                    <input class='form-check-input calc-score' type='radio' name='$name' id='{$name}_$i' value='$i' required>
                    <label class='form-check-label text-dark' for='{$name}_$i'>$label</label>
                  </div>";
    }
    $html .= "</div></div>";
    return $html;
}

// รับค่าและตรวจสอบความถูกต้องของ Link
$student_id = isset($_GET['id']) ? $conn->real_escape_string($_GET['id']) : '';
$token = isset($_GET['token']) ? $_GET['token'] : '';
$secret_key = "RMUTR_COOP_SECURE_2024";
$valid_token = hash('sha256', $student_id . $secret_key);

if (empty($student_id) || $token !== $valid_token) {
    die("
    <div style='font-family: sans-serif; text-align: center; margin-top: 50px; line-height: 1.6;'>
        <h2 style='color: #dc3545;'>❌ ลิงก์ไม่ถูกต้อง หรือถูกดัดแปลง</h2>
        <p style='color: #6c757d;'>กรุณาติดต่อนักศึกษาเพื่อขอลิงก์ประเมินผลใหม่ที่ถูกต้อง</p>
    </div>
    ");
}

// --- 1. ตรวจสอบประวัติการประเมินทันที! ---
$eval_sql = "SELECT eval_id FROM teacher_evaluations WHERE student_id = '$student_id' LIMIT 1";
$eval_res = $conn->query($eval_sql);
$existing_eval = ($eval_res && $eval_res->num_rows > 0);

// 🚨 ดักจับลิงก์ที่ถูกก๊อปปี้มาเปิดซ้ำ 🚨
// ถ้าประเมินไปแล้ว และไม่ได้มาจากการกดส่งสำเร็จ (ไม่มี success=1) ให้ตัดจบการทำงานทันที
if ($existing_eval && !isset($_GET['success'])) {
    die("
    <!DOCTYPE html>
    <html lang='th'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>ลิงก์หมดอายุ</title>
        <link href='https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap' rel='stylesheet'>
    </head>
    <body style='text-align:center; padding: 10% 20px; font-family: \"Prompt\", sans-serif; background-color: #f8f9fa; height: 100vh; margin: 0;'>
        <h1 style='color: #6c757d; font-size: 80px; margin-bottom: 20px;'>⏳</h1>
        <h2 style='color: #dc3545; font-weight: bold;'>ลิงก์นี้หมดอายุแล้ว</h2>
        <p style='color: #6c757d; font-size: 1.1rem; line-height: 1.6;'>นักศึกษารหัส <b>$student_id</b> ได้รับการประเมินผลเรียบร้อยแล้ว<br>ระบบไม่อนุญาตให้เข้าถึงลิงก์นี้ซ้ำอีก ขอบคุณครับ</p>
    </body>
    </html>
    ");
}

// --- 2. ดึงข้อมูลนักศึกษา ---
$s_sql = "SELECT u.users_name, u.full_name, c.company_name 
          FROM users u 
          LEFT JOIN student_internships si ON u.users_name = si.student_id AND si.status IN ('active', 'relocated', 'pending', 'finished')
          LEFT JOIN companies c ON si.company_id = c.company_id
          WHERE u.users_name = '$student_id' ORDER BY si.created_at DESC LIMIT 1";
$s_res = $conn->query($s_sql);
if (!$s_res || $s_res->num_rows == 0) { 
    die("<h2 style='text-align:center; margin-top:50px;'>ไม่พบข้อมูลนักศึกษาในระบบ</h2>"); 
}
$student = $s_res->fetch_assoc();

$msg = ""; $msg_type = "";

// --- 3. จัดการเมื่อบริษัทกดบันทึก ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_evaluation'])) {

    // ป้องกันการส่งข้อมูลซ้ำ: ถ้ามีข้อมูลอยู่แล้ว บล็อกการบันทึกทันที
    if ($existing_eval) {
        $msg = "นักศึกษารายนี้ได้รับการประเมินไปแล้ว ไม่สามารถบันทึกข้อมูลซ้ำได้"; 
        $msg_type = "danger";
    } else {
        $q1 = (int)$_POST['q1']; $q2 = (int)$_POST['q2']; $q3 = (int)$_POST['q3']; $q4 = (int)$_POST['q4']; 
        $q5 = (int)$_POST['q5']; $q6 = (int)$_POST['q6']; $q7 = (int)$_POST['q7']; $q8 = (int)$_POST['q8']; 
        $q9 = (int)$_POST['q9']; $q10 = (int)$_POST['q10']; $q11 = (int)$_POST['q11']; $q12 = (int)$_POST['q12']; 
        $q13 = (int)$_POST['q13']; $q14 = (int)$_POST['q14']; $q15 = (int)$_POST['q15']; $q16 = (int)$_POST['q16']; 
        $q17 = (int)$_POST['q17'];
        
        $total_score = $q1 + $q2 + $q3 + $q4 + $q5 + $q6 + $q7 + $q8 + $q9 + $q10 + $q11 + $q12 + $q13 + $q14 + $q15 + $q16 + $q17;

        $strengths = $conn->real_escape_string($_POST['strengths']);
        $improvements = $conn->real_escape_string($_POST['improvements']);
        $hire_decision = $conn->real_escape_string($_POST['hire_decision']);
        $overall_grade = $conn->real_escape_string($_POST['overall_grade']);
        $comments = $conn->real_escape_string($_POST['comments']);

        $sql = "INSERT INTO teacher_evaluations 
                (student_id, teacher_id, q1, q2, q3, q4, q5, q6, q7, q8, q9, q10, q11, q12, q13, q14, q15, q16, q17, total_score, strengths, improvements, hire_decision, overall_grade, comments) 
                VALUES ('$student_id', 'COMPANY', $q1, $q2, $q3, $q4, $q5, $q6, $q7, $q8, $q9, $q10, $q11, $q12, $q13, $q14, $q15, $q16, $q17, $total_score, '$strengths', '$improvements', '$hire_decision', '$overall_grade', '$comments')";
        
        $action = "สถานประกอบการประเมิน (ใหม่)";

        if ($conn->query($sql)) {
            $conn->query("INSERT INTO teacher_evaluations_log (student_id, total_score, overall_grade, action_type) VALUES ('$student_id', $total_score, '$overall_grade', '$action')");
            // เมื่อบันทึกเสร็จ ให้ Redirect กลับมาหน้าเดิม พร้อมแนบ success=1 
            header("Location: company_evaluate.php?id=$student_id&token=$token&success=1");
            exit();
        } else {
            $msg = "เกิดข้อผิดพลาดในการบันทึก: " . $conn->error; 
            $msg_type = "danger";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แบบประเมินนักศึกษา - สถานประกอบการ</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Prompt', sans-serif; background-color: #f8f9fa; color: #333; }
        .main-container { max-width: 900px; margin: 40px auto; background: #fff; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .eval-section { background-color: #ffffff; border: 1px solid #e9ecef; border-radius: 15px; padding: 25px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        .eval-section h5 { color: #0d6efd; font-weight: 600; border-bottom: 2px solid #cfe2ff; padding-bottom: 10px; margin-bottom: 20px; }
        .score-box { background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 10px; padding: 15px; margin-bottom: 15px; transition: 0.2s; }
        .score-box:hover { border-color: #0d6efd; background: #fff; }
        .form-control, .form-select { border-radius: 10px; }
    </style>
</head>
<body>

<div class="container">
    <div class="main-container">
        <div class="text-center mb-5">
            <h3 class="fw-bold text-primary">แบบประเมินผลการปฏิบัติงานของนักศึกษา</h3>
            <h5 class="text-muted">(สำหรับ สถานประกอบการ)</h5>
        </div>

        <?php if($msg != ""): ?>
            <div class="alert alert-<?php echo $msg_type; ?> rounded-4"><?php echo $msg; ?></div>
        <?php endif; ?>

        <div class="alert alert-light border rounded-4 mb-4 shadow-sm p-4">
            <h5 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="fas fa-info-circle text-info me-2"></i> ข้อมูลทั่วไป</h5>
            <div class="row">
                <div class="col-md-6 mb-2"><span class="text-muted">ชื่อ-นามสกุลนักศึกษา:</span> <span class="fw-bold text-dark"><?php echo htmlspecialchars($student['full_name']); ?></span></div>
                <div class="col-md-6 mb-2"><span class="text-muted">รหัสประจำตัว:</span> <span class="fw-bold text-dark"><?php echo htmlspecialchars($student['users_name']); ?></span></div>
                
                <div class="col-md-6 mb-2"><span class="text-muted">สาขาวิชา:</span> <span class="fw-bold text-dark">เทคโนโลยีวิศวกรรมคอมพิวเตอร์</span></div>
                <div class="col-md-6 mb-2"><span class="text-muted">คณะ:</span> <span class="fw-bold text-dark">อุตสาหกรรมและเทคโนโลยี</span></div>
                
                <div class="col-12"><span class="text-muted">ชื่อสถานประกอบการ:</span> <span class="fw-bold text-dark"><?php echo htmlspecialchars($student['company_name'] ?? 'ไม่ระบุ'); ?></span></div>
            </div>
        </div>

        <?php if ($existing_eval): ?>
            
            <div class="alert alert-success text-center rounded-4 p-5 shadow-sm mt-4">
                <i class="fas fa-check-circle fa-4x mb-3 text-success"></i>
                <h4 class="fw-bold">บันทึกผลการประเมินเรียบร้อยแล้ว</h4>
                <p class="mb-4 text-muted">ทางมหาวิทยาลัยได้รับข้อมูลการประเมินแล้ว ขอบพระคุณที่ให้ความร่วมมือครับ/ค่ะ</p>
                <a href="print_evaluation.php?id=<?php echo urlencode($student_id); ?>&token=<?php echo urlencode($token); ?>" target="_blank" class="btn btn-outline-success btn-lg px-4" style="border-radius: 50px;">
                    <i class="fas fa-file-pdf me-2"></i> ดูใบประเมิน / พิมพ์ PDF
                </a>
            </div>

        <?php else: ?>

            <form method="POST" action="" onsubmit="return confirm('ยืนยันการส่งผลการประเมิน? ข้อมูลจะถูกบันทึกลงระบบของทางมหาวิทยาลัยทันที และไม่สามารถกลับมาแก้ไขได้อีก');">

                <div class="eval-section">
                    <h5>1. ผลสำเร็จของงาน (คะแนนเต็ม 40)</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold text-dark">1. ปริมาณงาน (เต็ม 20)</label>
                            <p class="small text-muted mb-2">ปริมาณงานที่ปฏิบัติสำเร็จ ตามหน้าที่หรือตามที่ได้รับมอบหมายภายในระยะเวลาที่กำหนด</p>
                            <input type="number" name="q1" class="form-control calc-score bg-white" min="0" max="20" required placeholder="ระบุคะแนน 0 - 20">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold text-dark">2. คุณภาพงาน (เต็ม 20)</label>
                            <p class="small text-muted mb-2">ทำงานได้ถูกต้องครบถ้วนสมบูรณ์ มีความประณีตเรียบร้อย งานไม่ค้าง เสร็จทันเวลา</p>
                            <input type="number" name="q2" class="form-control calc-score bg-white" min="0" max="20" required placeholder="ระบุคะแนน 0 - 20">
                        </div>
                    </div>
                </div>

                <div class="eval-section">
                    <h5>2. ความรู้ความสามารถ (ข้อละ 4 คะแนน)</h5>
                    <?php 
                    echo buildCompanyRadio('q3', '1. ความรู้ความสามารถทางวิชาการ', 'มีความรู้ทางวิชาการเพียงพอที่จะปฏิบัติงานตามที่ได้รับมอบหมาย');
                    echo buildCompanyRadio('q4', '2. ความสามารถในการเรียนรู้และประยุกต์วิชาการ', 'ความรวดเร็วในการเรียนรู้ เข้าใจข้อมูล ตลอดจนการนำความรู้ไปประยุกต์ใช้งาน');
                    echo buildCompanyRadio('q5', '3. ความรู้ความชำนาญด้านปฏิบัติการ', 'เช่น การปฏิบัติงานในภาคสนาม ในห้องปฏิบัติการ เป็นต้น');
                    echo buildCompanyRadio('q6', '4. วิจารณญาณและการตัดสินใจ', 'มีการวิเคราะห์ข้อมูลและปัญหาอย่างรอบคอบ ตัดสินใจได้ดี แก้ปัญหาเฉพาะหน้าได้ดี');
                    echo buildCompanyRadio('q7', '5. ทักษะการสื่อสาร', 'การพูด การเขียน การนำเสนอ สื่อให้เข้าใจได้ง่าย ชัดเจน ถูกต้อง');
                    echo buildCompanyRadio('q8', '6. ความสามารถทางภาษาต่างประเทศ / วัฒนธรรม', 'ความสามารถในการใช้ภาษาต่างประเทศ การปรับตัวให้เข้ากับชาวต่างประเทศ');
                    echo buildCompanyRadio('q9', '7. ความเหมาะสมกับตำแหน่งงานที่ได้รับมอบหมาย', 'พัฒนาตนเองให้ปฏิบัติงานตามตำแหน่งงานได้อย่างเหมาะสม');
                    ?>
                </div>

                <div class="eval-section">
                    <h5>3. ความรับผิดชอบต่อหน้าที่ (ข้อละ 4 คะแนน)</h5>
                    <?php 
                    echo buildCompanyRadio('q10', '8. ความสามารถเริ่มต้นทำงานได้ด้วยตนเอง', 'สามารถเริ่มงานที่ต้องรับผิดชอบเป็นประจำได้ด้วยตนเอง โดยไม่ต้องรอคำสั่ง');
                    echo buildCompanyRadio('q11', '9. ความรับผิดชอบและเป็นผู้ที่ไว้วางใจได้', 'ดำเนินงานสำเร็จลุล่วงโดยคำนึงถึงเป้าหมาย ปล่อยให้ทำงานได้โดยไม่ต้องควบคุมมาก');
                    echo buildCompanyRadio('q12', '10. ความสนใจ ความอุตสาหะในการทำงาน', 'ความกระตือรือร้นในการทำงาน ความพยายามตั้งใจที่จะทำงานให้สำเร็จ ไม่ย่อท้อ');
                    echo buildCompanyRadio('q13', '11. การตอบสนองต่อการสั่งการ', 'ยินดีรับคำสั่ง คำแนะนำ รวมทั้งมีความรวดเร็วในการปฏิบัติตามคำสั่ง');
                    ?>
                </div>

                <div class="eval-section">
                    <h5>4. ลักษณะส่วนบุคคล (ข้อละ 4 คะแนน)</h5>
                    <?php 
                    echo buildCompanyRadio('q14', '1. บุคลิกภาพและการวางตัว', 'ทัศนคติ วุฒิภาวะ อ่อนน้อมถ่อมตน การแต่งกาย การตรงต่อเวลา');
                    echo buildCompanyRadio('q15', '2. มนุษยสัมพันธ์', 'ร่วมงานกับผู้อื่นได้ดี การทำงานเป็นทีม เป็นที่รักใคร่ชอบพอของผู้ร่วมงาน');
                    echo buildCompanyRadio('q16', '3. ความมีระเบียบวินัย ปฏิบัติตามวัฒนธรรมขององค์กร', 'ความสนใจเรียนรู้ ศึกษา กฎระเบียบ นโยบายต่างๆ และปฏิบัติตามโดยเต็มใจ');
                    echo buildCompanyRadio('q17', '4. คุณธรรมและจริยธรรม', 'มีความซื่อสัตย์ สุจริต มีน้ำใจ รู้จักเสียสละ เอื้อเฟื้อช่วยเหลือผู้อื่น');
                    ?>
                </div>

                <div class="eval-section">
                    <h5>5. โปรดให้ข้อคิดเห็นที่เป็นประโยชน์แก่นักศึกษา</h5>
                    <div class="mb-3">
                        <label class="fw-bold text-dark">จุดเด่นของนักศึกษา</label>
                        <textarea name="strengths" class="form-control" rows="3" placeholder="ระบุจุดเด่น..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold text-dark">ข้อควรปรับปรุงของนักศึกษา</label>
                        <textarea name="improvements" class="form-control" rows="3" placeholder="ระบุข้อควรปรับปรุง..."></textarea>
                    </div>
                    
                    <hr class="my-4">

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="fw-bold text-dark mb-2">หากสำเร็จการศึกษา ท่านจะรับเข้าทำงานหรือไม่?</label>
                            <select name="hire_decision" class="form-select bg-white" required>
                                <option value="" disabled selected>-- กรุณาเลือก --</option>
                                <option value="รับ">รับเข้าทำงาน</option>
                                <option value="ไม่แน่ใจ">ไม่แน่ใจ</option>
                                <option value="ไม่รับ">ไม่รับเข้าทำงาน</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="fw-bold text-dark mb-2">สรุปโดยภาพรวมคุณภาพนักศึกษาในระดับใด?</label>
                            <select name="overall_grade" class="form-select bg-white" required>
                                <option value="" disabled selected>-- กรุณาเลือก --</option>
                                <option value="ดีมาก">ดีมาก</option>
                                <option value="ดี">ดี</option>
                                <option value="พอใช้">พอใช้</option>
                                <option value="ควรปรับปรุง">ควรปรับปรุง</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="fw-bold text-dark">ข้อคิดเห็นเพิ่มเติม</label>
                        <textarea name="comments" class="form-control" rows="2" placeholder="ระบุข้อคิดเห็น (ถ้ามี)"></textarea>
                    </div>
                </div>

                <div class="card shadow-lg border-0 sticky-bottom mb-2" style="border-radius: 20px; background: #e0fbfc;">
                    <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <h4 class="text-primary fw-bold mb-0">คะแนนรวมสุทธิ: <span id="total_score_display" class="text-dark bg-white px-3 py-1 rounded-pill shadow-sm border border-primary">0</span> / 100</h4>
                        <button type="submit" name="save_evaluation" class="btn btn-primary btn-lg px-5 shadow" style="border-radius: 50px; font-weight: 600;">
                            <i class="fas fa-paper-plane me-2"></i> ยืนยันการประเมินผล
                        </button>
                    </div>
                </div>
            </form>
            
            <script>
                function calculateTotal() {
                    let total = 0;
                    const inputs = document.querySelectorAll('.calc-score');
                    inputs.forEach(input => {
                        if(input.type === 'number') { total += Number(input.value) || 0; } 
                        else if(input.type === 'radio' && input.checked) { total += Number(input.value) || 0; }
                    });
                    document.getElementById('total_score_display').innerText = total;
                }
                document.querySelectorAll('.calc-score').forEach(input => {
                    input.addEventListener('input', calculateTotal);
                    input.addEventListener('change', calculateTotal);
                });
            </script>

        <?php endif; ?>

    </div>
</div>

</body>
</html>