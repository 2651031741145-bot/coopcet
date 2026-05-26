<?php
// เปิด Error เพื่อเช็คข้อผิดพลาด
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'db.php';

// รองรับการส่งค่า id มา
$student_id = isset($_GET['student_id']) ? $conn->real_escape_string($_GET['student_id']) : '';

// -------------------------------------------------------------------------
// ตรวจสอบสิทธิ์การเข้าถึง (อนุญาตให้อาจารย์ที่ล็อกอินอยู่เข้าดูได้)
// -------------------------------------------------------------------------
$is_teacher = false;
$teacher_fullname = "";

if (isset($_SESSION['users_name']) && $_SESSION['user_level'] == 't' && isset($_SESSION['session_token'])) {
    $teacher_username = $conn->real_escape_string($_SESSION['users_name']);
    $current_token = $_SESSION['session_token'];
    
    // ตรวจสอบความถูกต้องของ Session Token
    $check_sql = "SELECT session_token, full_name FROM users WHERE users_name = '$teacher_username'";
    $result = $conn->query($check_sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if ($row['session_token'] === $current_token) {
            $is_teacher = true;
            $teacher_fullname = $row['full_name'];
        }
    }
}

// หากไม่ใช่สิทธิ์อาจารย์ ไม่อนุญาตให้เข้าถึง
if (empty($student_id) || !$is_teacher) { 
    die("<h2 style='text-align:center; color:red; margin-top:50px;'>❌ ไม่มีสิทธิ์เข้าถึงหน้านี้ หรือไม่ได้ล็อกอินในฐานะอาจารย์</h2>"); 
}
// -------------------------------------------------------------------------

// 1. ดึงข้อมูลนักศึกษา
$s_sql = "SELECT u.users_name, u.full_name, c.company_name 
          FROM users u 
          LEFT JOIN student_internships si ON u.users_name = si.student_id AND si.status IN ('active', 'relocated', 'pending', 'finished')
          LEFT JOIN companies c ON si.company_id = c.company_id
          WHERE u.users_name = '$student_id' 
          ORDER BY si.created_at DESC LIMIT 1";

$s_res = $conn->query($s_sql);
if (!$s_res) { die("<h3 style='color:red;'>SQL Error (Student Data): " . $conn->error . "</h3>"); }
$student = $s_res->fetch_assoc();

if (!$student) { die("<h2 style='text-align:center; margin-top:50px;'>ไม่พบข้อมูลนักศึกษารายนี้ในระบบ</h2>"); }

// 2. ดึงข้อมูลคะแนนการประเมินรายงาน
$eval_sql = "SELECT * FROM teacher_report_evaluations WHERE student_id = '$student_id' LIMIT 1";
$res = $conn->query($eval_sql);
if (!$res) { die("<h3 style='color:red;'>SQL Error (Evaluation Data): " . $conn->error . "</h3>"); }

if ($res->num_rows == 0) { 
    die("<h2 style='text-align:center; margin-top:50px;'>ยังไม่มีการประเมินผลรายงานสหกิจศึกษาสำหรับนักศึกษารายนี้</h2>"); 
}
$eval = $res->fetch_assoc();

// ฟังก์ชันสร้าง Header ของมหาวิทยาลัย ตามฟอร์ม CWIE 14
function renderHeader() {
    return '
    <div class="uni-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 25px; gap: 20px;">
        <div class="logo-box" style="flex: 0 0 65px; text-align: center;">
            <img src="logo.png" alt="RMUTR Logo" style="width: 58px; height: 102px;">
        </div>
        <div class="text-box" style="flex: 1; text-align: left; line-height: 1.35; font-size: 8.5pt; color: #000; padding-top: 5px;">
            <div style="font-size: 10.5pt; font-weight: normal; border-bottom: 1px solid #000; padding-bottom: 4px; margin-bottom: 5px; white-space: nowrap;">
                มหาวิทยาลัยเทคโนโลยีราชมงคลรัตนโกสินทร์
            </div>
            <div style="margin-bottom: 2px; white-space: nowrap;">
                ด้านสหกิจศึกษาและการศึกษาเชิงบูรณาการกับการทำงาน คณะอุตสาหกรรมและเทคโนโลยี
            </div>
            <div style="margin-bottom: 2px; white-space: nowrap;">
                มหาวิทยาลัยเทคโนโลยีราชมงคลรัตนโกสินทร์ วิทยาเขตวังไกลกังวล
            </div>
            <div style="margin-bottom: 2px; white-space: nowrap;">
                ถนนเพชรเกษม ตำบลหนองแก อำเภอหัวหิน จังหวัดประจวบคีรีขันธ์ 77110
            </div>
            <div style="white-space: nowrap;">
                <span style="font-weight: bold;">โทรศัพท์</span> 032-618-500 ต่อ 4714
            </div>
        </div>
        <div class="cwie-box" style="flex: 0 0 70px; text-align: right; padding-top: 5px;">
            <div style="border: 1px solid #999; color: #555; padding: 6px 0; font-size: 9pt; text-align: center; width: 100%;">
                CWIE 14
            </div>
        </div>
    </div>
    ';
}

function renderRow($num, $title, $max_score, $actual_score) {
    return "
    <tr>
        <td class='text-center'>$num</td>
        <td>$title</td>
        <td class='text-center'>$max_score</td>
        <td class='text-center fw-bold' style='font-size:12pt;'>$actual_score</td>
    </tr>";
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แบบประเมินผลรายงาน - <?php echo htmlspecialchars($student['users_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @page { size: A4; margin: 0; } 
        html, body { margin: 0; padding: 0; background-color: #525659; }
        body { font-family: 'Sarabun', sans-serif; font-size: 11pt; color: #000; line-height: 1.3; padding: 20px 0; }
        .a4-page { 
            width: 210mm; min-height: 297mm; padding: 10mm 15mm; margin: 0 auto 20px auto; 
            background: white; box-shadow: 0 0 10px rgba(0,0,0,0.5); box-sizing: border-box; position: relative; 
        }
        @media print {
            body { background: white; padding: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .a4-page { margin: 0; border: none; box-shadow: none; width: 210mm; height: 297mm; padding: 10mm 15mm; page-break-after: always; overflow: hidden; }
            .a4-page:last-of-type { page-break-after: auto; } 
            .no-print { display: none !important; }
        }
        .text-center { text-align: center; }
        .fw-bold { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 10.5pt; }
        th, td { border: 1px solid black; padding: 6px 8px; vertical-align: middle; }
        th { text-align: center; font-weight: bold; }
        .dotted-line { border-bottom: 1px dotted black; display: inline-block; }
        .print-btn-container { position: fixed; top: 20px; right: 20px; z-index: 1000; }
        .btn-print { background-color: #0d6efd; color: white; border: none; padding: 10px 20px; font-family: 'Sarabun', sans-serif; font-size: 12pt; border-radius: 50px; cursor: pointer; box-shadow: 0 4px 10px rgba(0,0,0,0.3); font-weight: 600; }
        .btn-print:hover { background-color: #0b5ed7; }
    </style>
</head>
<body>

<div class="print-btn-container no-print">
    <button onclick="window.print()" class="btn-print"><i class="fas fa-print me-2"></i> พิมพ์ / บันทึก PDF</button>
</div>

<div class="a4-page">
    <?php echo renderHeader(); ?>

    <div style="text-align: center; margin-top: 10px; margin-bottom: 15px; font-weight: bold; line-height: 1.3;">
        <span style="font-size: 13pt;">แบบประเมินผลรายงานสหกิจศึกษา</span>
    </div>
    
    <p class="fw-bold" style="font-size: 11pt; margin-bottom: 5px;">คำชี้แจง</p>
    <div style="font-size: 10.5pt; line-height: 1.5; padding-left: 10px; margin-bottom: 15px;">
        1. ผู้ให้ข้อมูลในแบบประเมินนี้ต้องเป็นอาจารย์ที่ปรึกษาสหกิจศึกษา ของนักศึกษาสหกิจศึกษาหรือ บุคคลที่ได้รับมอบหมายให้ทำหน้าที่แทน<br>
        2. แบบประเมินผลนี้มีทั้งหมด 22 ข้อ โปรดให้ข้อมูลครบทุกข้อ เพื่อความสมบูรณ์ของการประเมินผล<br>
        3. โปรดให้คะแนนในช่อง ประเมิน ในแต่ละหัวข้อการประเมิน หากไม่มีข้อมูลให้ใส่เครื่องหมาย – และโปรดให้ความคิดเห็นเพิ่มเติม (ถ้ามี)<br>
        4. เมื่อประเมินผลเรียบร้อยแล้ว โปรดส่งคืนกองสหกิจศึกษา ภายใน 1 สัปดาห์
    </div>

    <p class="fw-bold" style="margin-top: 15px; font-size: 12pt; margin-bottom: 5px;">ข้อมูลทั่วไป</p>
    <div style="font-size: 10.5pt;">
        <div style="display: flex; align-items: baseline; width: 100%; margin-bottom: 8px;">
            <span style="white-space: nowrap;">ชื่อ-นามสกุลนักศึกษา</span>
            <span class="dotted-line" style="flex: 2; text-align: center; margin: 0 10px;"><?php echo htmlspecialchars($student['full_name']); ?></span>
            <span style="white-space: nowrap;">รหัสประจำตัว</span>
            <span class="dotted-line" style="flex: 1; text-align: center; margin-left: 10px;"><?php echo htmlspecialchars($student['users_name']); ?></span>
        </div>
        <div style="display: flex; align-items: baseline; width: 100%; margin-bottom: 8px;">
            <span style="white-space: nowrap;">สาขาวิชา</span>
            <span class="dotted-line" style="flex: 1.5; text-align: center; margin: 0 10px;">เทคโนโลยีวิศวกรรมคอมพิวเตอร์</span>
            <span style="white-space: nowrap;">คณะ</span>
            <span class="dotted-line" style="flex: 1; text-align: center; margin-left: 10px;">อุตสาหกรรมและเทคโนโลยี</span>
        </div>
        <div style="display: flex; align-items: baseline; width: 100%; margin-bottom: 8px;">
            <span style="white-space: nowrap;">ชื่อสถานประกอบการ</span>
            <span class="dotted-line" style="flex: 1; text-align: center; margin-left: 10px;"><?php echo htmlspecialchars($student['company_name'] ?? '-'); ?></span>
        </div>
        <div style="display: flex; align-items: baseline; width: 100%; margin-bottom: 8px;">
            <span style="white-space: nowrap;">หัวข้อรายงาน/Report title (ภาษาไทย)</span>
            <span class="dotted-line" style="flex: 1; text-align: center; margin-left: 10px;"><?php echo htmlspecialchars($eval['report_title_th'] ?? '-'); ?></span>
        </div>
    </div>

    <p class="fw-bold" style="margin-top: 15px; font-size: 11.5pt; text-decoration: underline;">ส่วนที่ 1 ด้านเนื้อหารูปแบบโครงงานวิชาการสหกิจศึกษา</p>
    <table>
        <tr style="background-color: #f2f2f2;">
            <th rowspan="2" style="width: 10%;">ข้อที่</th>
            <th rowspan="2" style="width: 60%;">หัวข้อประเมิน</th>
            <th colspan="2" style="width: 30%;">คะแนน</th>
        </tr>
        <tr style="background-color: #f2f2f2;">
            <th>เต็ม</th>
            <th>ประเมิน</th>
        </tr>
        <?php 
        echo renderRow(1, "บทคัดย่อ (Abstract)", 5, $eval['s1_q1']);
        echo renderRow(2, "วัตถุประสงค์ (Objectives)", 5, $eval['s1_q2']);
        echo renderRow(3, "การทบทวนวรรณกรรม แนวคิดทฤษฎีที่เกี่ยวข้อง (Literature Review Concepts, theories)", 10, $eval['s1_q3']);
        echo renderRow(4, "วิธีการศึกษา (Method of Education)", 15, $eval['s1_q4']);
        echo renderRow(5, "ผลการศึกษา (Result)", 15, $eval['s1_q5']);
        echo renderRow(6, "การวิเคราะห์ผลการศึกษา (Analysis)", 10, $eval['s1_q6']);
        echo renderRow(7, "สรุปผลการศึกษา (Conclusion)", 10, $eval['s1_q7']);
        echo renderRow(8, "ข้อเสนอแนะ (Recommendation)", 5, $eval['s1_q8']);
        echo renderRow(9, "สำนวนการเขียน และการสื่อความหมาย (Idiom and Meaning)", 10, $eval['s1_q9']);
        echo renderRow(10, "ความถูกต้องตัวสะกด (Spelling)", 5, $eval['s1_q10']);
        echo renderRow(11, "รูปแบบ และความสวยงาม ของรูปเล่ม (Pattern)", 5, $eval['s1_q11']);
        echo renderRow(12, "เอกสารอ้างอิง (References)", 5, $eval['s1_q12']);
        ?>
        <tr>
            <td colspan="2" class="text-center fw-bold" style="background-color: #f9f9f9;">คะแนนรวม</td>
            <td class="text-center fw-bold" style="background-color: #f9f9f9;">100</td>
            <td class="text-center fw-bold text-success" style="font-size:13pt; background-color: #e6fffa;"><?php echo $eval['s1_total']; ?></td>
        </tr>
        <tr>
            <td colspan="2" class="text-center fw-bold" style="background-color: #fff9e6;">คะแนนเต็ม 100 คะแนน คิดเป็น 20%</td>
            <td colspan="2" class="text-center fw-bold text-primary" style="font-size:13pt; background-color: #fff9e6;"><?php echo number_format($eval['s1_total'] * 0.2, 2); ?></td>
        </tr>
    </table>
    
    <div style="position: absolute; bottom: 15mm; right: 20mm; left: 20mm;">
        <div style="border-top: 1px solid #ddd; width: 100%; margin-bottom: 5px;"></div>
        <div style="text-align: right; font-size: 10pt; color: #555; font-weight: bold;">1 | 2</div>
    </div>
</div>

<div class="a4-page">
    <?php echo renderHeader(); ?>

    <p class="fw-bold" style="font-size: 11.5pt; text-decoration: underline;">ส่วนที่ 2 ด้านการนำเสนอ</p>
    <table>
        <tr style="background-color: #f2f2f2;">
            <th rowspan="2" style="width: 10%;">ข้อที่</th>
            <th rowspan="2" style="width: 60%;">หัวข้อประเมิน</th>
            <th colspan="2" style="width: 30%;">คะแนน</th>
        </tr>
        <tr style="background-color: #f2f2f2;">
            <th>เต็ม</th>
            <th>ประเมิน</th>
        </tr>
        <tr>
            <td colspan="4" class="fw-bold" style="background-color: #fafafa;">ด้านวิชาการ</td>
        </tr>
        <?php 
        echo renderRow(1, "ความครบถ้วนของเนื้อหา", 10, $eval['s2_q1']);
        echo renderRow(2, "การแก้ปัญหาตรงกับวัตถุประสงค์", 10, $eval['s2_q2']);
        echo renderRow(3, "ความชัดเจนในการเสนอแนะแนวทางแก้ไข", 10, $eval['s2_q3']);
        echo renderRow(4, "ความชัดเจนในการตอบคำถาม", 10, $eval['s2_q4']);
        echo renderRow(5, "ผลสำเร็จของงาน", 10, $eval['s2_q5']);
        ?>
        <tr>
            <td colspan="4" class="fw-bold" style="background-color: #fafafa;">การนำเสนอ</td>
        </tr>
        <?php 
        echo renderRow(6, "ความเหมาะสมของเนื้อหาและการใช้สื่อ", 10, $eval['s2_q6']);
        echo renderRow(7, "ลำดับขั้นตอนการนำเสนอ", 10, $eval['s2_q7']);
        echo renderRow(8, "การใช้เวลาในการนำเสนอ", 10, $eval['s2_q8']);
        echo renderRow(9, "ความครบถ้วนของเนื้อหา", 10, $eval['s2_q9']);
        echo renderRow(10, "การแต่งกายและบุคลิกภาพ", 10, $eval['s2_q10']);
        ?>
        <tr>
            <td colspan="2" class="text-center fw-bold" style="background-color: #f9f9f9;">คะแนนรวม</td>
            <td class="text-center fw-bold" style="background-color: #f9f9f9;">100</td>
            <td class="text-center fw-bold text-success" style="font-size:13pt; background-color: #e6fffa;"><?php echo $eval['s2_total']; ?></td>
        </tr>
        <tr>
            <td colspan="2" class="text-center fw-bold" style="background-color: #fff9e6;">คะแนนเต็ม 100 คะแนน คิดเป็น 20%</td>
            <td colspan="2" class="text-center fw-bold text-primary" style="font-size:13pt; background-color: #fff9e6;"><?php echo number_format($eval['s2_total'] * 0.2, 2); ?></td>
        </tr>
    </table>

    <p class="fw-bold" style="margin-top: 15px; font-size: 11.5pt;">ข้อคิดเห็นเพิ่มเติม</p>
    <div style="line-height: 25px; font-size: 10.5pt; margin-bottom: 25px;">
        <div style="display: flex; align-items: baseline; width: 100%;">
            <span style="border-bottom: 1px dotted black; flex: 1; min-height: 25px; padding-left: 10px;">
                <?php echo nl2br(htmlspecialchars($eval['comments'] ?? '')); ?>
            </span>
        </div>
        <div style="border-bottom: 1px dotted black; width: 100%; min-height: 25px;"></div>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-top: 25px; font-size: 10.5pt; width: 100%;">
        
        <div style="width: 42%; border: 1px solid black; padding: 15px; line-height: 1.6; font-size: 10.5pt; font-weight: bold; box-sizing: border-box;">
            <u>หมายเหตุ</u> : หากคณะที่นักศึกษาสังกัด<br>
            ไม่ได้รับแบบประเมินนี้ภายในระยะเวลา<br>
            ที่กำหนด นักศึกษาจะไม่ผ่านการประเมินผล
        </div>

        <div style="width: 55%; box-sizing: border-box; line-height: 2.2;">
            <div style="display: flex; align-items: baseline; width: 100%;">
                <div style="white-space: nowrap;">ลงชื่อ</div>
                <div style="flex: 1; border-bottom: 1px dotted black; margin: 0 5px;"></div>
                <div style="white-space: nowrap;">อาจารย์ประเมิน</div>
            </div>
            <div style="display: flex; align-items: baseline; justify-content: center; width: 100%;">
                <div style="width: 15px; text-align: right;">(</div>
                <div style="flex: 1; border-bottom: 1px dotted black; text-align: center; white-space: nowrap;">
                    <?php echo htmlspecialchars($teacher_fullname); ?>
                </div>
                <div style="width: 15px; text-align: left;">)</div>
            </div>
            <div style="display: flex; align-items: baseline; justify-content: center; width: 100%;">
                <div style="white-space: nowrap;">วันที่</div>
                <div style="border-bottom: 1px dotted black; flex: 1; text-align: center; margin: 0 5px;">
                    <?php echo date('d', strtotime($eval['created_at'])); ?>
                </div>
                <div style="white-space: nowrap;">เดือน</div>
                <div style="border-bottom: 1px dotted black; flex: 1.5; text-align: center; margin: 0 5px;">
                    <?php 
                    $months = ["", "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"];
                    echo $months[(int)date('m', strtotime($eval['created_at']))]; 
                    ?>
                </div>
                <div style="white-space: nowrap;">พ.ศ.</div>
                <div style="border-bottom: 1px dotted black; flex: 1; text-align: center; margin-left: 5px;">
                    <?php echo (date('Y', strtotime($eval['created_at'])) + 543); ?>
                </div>
            </div>
        </div>

    </div>
    
    <div style="position: absolute; bottom: 15mm; right: 20mm; left: 20mm;">
        <div style="border-top: 1px solid #ddd; width: 100%; margin-bottom: 5px;"></div>
        <div style="text-align: right; font-size: 10pt; color: #555; font-weight: bold;">2 | 2</div>
    </div>
</div>

</body>
</html>