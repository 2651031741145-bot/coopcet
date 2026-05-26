<?php
// เปิด Error เพื่อเช็คข้อผิดพลาด
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'db.php';

// รองรับทั้งการส่งค่ามาเป็น student_id (จากหน้าอาจารย์) และ id (จากลิงก์เดิม)
$student_id = '';
if (isset($_GET['student_id'])) {
    $student_id = $conn->real_escape_string($_GET['student_id']);
} elseif (isset($_GET['id'])) {
    $student_id = $conn->real_escape_string($_GET['id']);
}

$token = isset($_GET['token']) ? $_GET['token'] : '';
$secret_key = "RMUTR_COOP_SECURE_2024";
$valid_token = hash('sha256', $student_id . $secret_key);

// -------------------------------------------------------------------------
// ตรวจสอบสิทธิ์การเข้าถึง (อนุญาตให้อาจารย์ที่ล็อกอินอยู่เข้าดูได้โดยไม่ต้องมี Token)
// -------------------------------------------------------------------------
$is_teacher = false;
if (isset($_SESSION['users_name']) && $_SESSION['user_level'] == 't' && isset($_SESSION['session_token'])) {
    $teacher_username = $conn->real_escape_string($_SESSION['users_name']);
    $current_token = $_SESSION['session_token'];
    
    // ตรวจสอบความถูกต้องของ Session Token ในฐานข้อมูล
    $check_sql = "SELECT session_token FROM users WHERE users_name = '$teacher_username'";
    $result = $conn->query($check_sql);
    if ($result && $result->num_rows > 0) {
        if ($result->fetch_assoc()['session_token'] === $current_token) {
            $is_teacher = true; // ยืนยันสิทธิ์ว่าเป็นอาจารย์จริง
        }
    }
}

// หากรหัสนักศึกษาว่าง หรือ (ไม่ใช่สิทธิ์อาจารย์ และ Token ไม่ตรงกับค่าที่กำหนดไว้) จะไม่ให้เข้าถึง
if (empty($student_id) || (!$is_teacher && $token !== $valid_token)) { 
    die("<h2 style='text-align:center; color:red; margin-top:50px;'>❌ ลิงก์ไม่ถูกต้อง หรือไม่มีสิทธิ์เข้าถึงหน้านี้</h2>"); 
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

// 2. ดึงข้อมูลคะแนน
$eval_sql = "SELECT * FROM teacher_evaluations WHERE student_id = '$student_id' LIMIT 1";
$res = $conn->query($eval_sql);
if (!$res) { die("<h3 style='color:red;'>SQL Error (Evaluation Data): " . $conn->error . "</h3>"); }

if ($res->num_rows == 0) { 
    die("<h2 style='text-align:center; margin-top:50px;'>ยังไม่มีการประเมินผลสำหรับนักศึกษารายนี้</h2>"); 
}
$eval = $res->fetch_assoc();

// ฟังก์ชันช่วยแสดง Checkbox แบบต่างๆ
function roundCheck($val, $target) { return ($val == $target) ? "( <b>✔</b> )" : "( &nbsp;&nbsp;&nbsp; )"; }
function squareCheck($val, $target) { return ($val == $target) ? "☑" : "☐"; }

// ฟังก์ชันสร้าง Header ของมหาวิทยาลัย
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
                CWIE 11
            </div>
        </div>
    </div>
    ';
}

// ฟังก์ชันสร้างตารางประเมิน
function renderItemRow($num, $title, $desc, $max_score, $actual_score) {
    $html = "<tr>";
    $html .= "<td rowspan='2' class='text-center' style='width: 5%;'>$num.</td>";
    $html .= "<td class='bold' style='border-bottom: none; width: 80%; font-size: 10.5pt;'>$title</td>";
    $html .= "<td class='text-center' style='width: 15%; font-size: 10pt;'>$max_score คะแนน</td>";
    $html .= "</tr><tr>";
    $html .= "<td style='border-top: none; font-size: 10.5pt; padding-top: 0;'>$desc</td>";
    $html .= "<td class='text-center bold' style='font-size: 12pt; vertical-align: middle;'>$actual_score</td>";
    $html .= "</tr>";
    return $html;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แบบประเมินผล - <?php echo htmlspecialchars($student['users_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @page { 
            size: A4; 
            margin: 0; 
        } 
        html, body {
            margin: 0;
            padding: 0;
            background-color: #525659;
        }
        body { 
            font-family: 'Sarabun', sans-serif; 
            font-size: 11pt; 
            color: #000; 
            line-height: 1.3; 
            padding: 20px 0; 
        }
        .a4-page { 
            width: 210mm; 
            min-height: 297mm; 
            padding: 10mm 15mm; 
            margin: 0 auto 20px auto; 
            background: white; 
            box-shadow: 0 0 10px rgba(0,0,0,0.5); 
            box-sizing: border-box; 
            position: relative; 
        }
        @media print {
            body { 
                background: white; 
                padding: 0; 
                -webkit-print-color-adjust: exact; 
                print-color-adjust: exact;
            }
            .a4-page { 
                margin: 0; 
                border: none; 
                box-shadow: none; 
                width: 210mm; 
                height: 297mm; 
                padding: 10mm 15mm; 
                page-break-after: always; 
                overflow: hidden; 
            }
            .a4-page:last-of-type { 
                page-break-after: auto; 
            } 
            .no-print { display: none !important; }
        }
        .text-center { text-align: center; }
        .bold { font-weight: bold; }
        h3 { text-align: center; margin: 0 0 10px 0; font-weight: bold; font-size: 13pt; }
        p { margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 10.5pt; }
        th, td { border: 1px solid black; padding: 5px 8px; vertical-align: top; }
        th { text-align: left; }
        .info-lines { margin-bottom: 15px; font-size: 10.5pt;}
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
        <span style="font-size: 13pt;">แบบประเมินผลการปฏิบัติงานของนักศึกษา</span><br>
        <span style="font-size: 11pt;">(สำหรับ สถานประกอบการ)</span>
    </div>
    
    <p class="bold" style="font-size: 11pt;">คำชี้แจง</p>
    <div style="font-size: 10.5pt; line-height: 1.4; padding-left: 10px;">
        1. ผู้ให้ข้อมูลในแบบประเมินนี้ต้องเป็นพนักงานที่ปรึกษา (Job supervisor) ของนักศึกษาสหกิจศึกษาหรือบุคคลที่ได้รับมอบหมายให้ทำหน้าที่แทน<br>
        2. แบบประเมินผลนี้มีทั้งหมด 17 ข้อ โปรดให้ข้อมูลครบทุกข้อ เพื่อความสมบูรณ์ของการประเมิน<br>
        3. โปรดให้คะแนนในช่อง ในแต่ละหัวข้อการประเมิน โปรดกรอกคะแนนให้ครบทุกข้อ เพื่อความสมบูรณ์ของการประเมินผล<br>
        4. เมื่อประเมินผลเรียบร้อยแล้ว โปรดนำเอกสารนี้ใส่ซองประทับตรา "ลับ" หรือลงนามกำกับบริเวณรอยปิดผนึก และนำส่งมายังคณะที่นักศึกษาสังกัดภายใน 1 สัปดาห์ หลังจากสิ้นสุดการปฏิบัติงาน
    </div>
    <p style="font-size: 10.5pt; margin-left: 10px; margin-top: 10px;"><b>การจัดส่ง</b> ให้นักศึกษานำมาส่งกองสหกิจศึกษาหรือสถานประกอบการนำส่งทางไปรษณีย์</p>

    <p class="bold" style="margin-top: 15px; font-size: 12pt;">ข้อมูลทั่วไป</p>
    <div class="info-lines" style="margin-top: 5px;">
        <div style="display: flex; align-items: baseline; width: 100%; margin-bottom: 12px;">
            <span style="white-space: nowrap;">ชื่อ-นามสกุลนักศึกษา</span>
            <span class="dotted-line" style="flex: 2; text-align: center; margin: 0 15px 0 10px;"><?php echo htmlspecialchars($student['full_name']); ?></span>
            <span style="white-space: nowrap;">รหัสประจำตัว</span>
            <span class="dotted-line" style="flex: 1; text-align: center; margin-left: 10px;"><?php echo htmlspecialchars($student['users_name']); ?></span>
        </div>
        <div style="display: flex; align-items: baseline; width: 100%; margin-bottom: 12px;">
            <span style="white-space: nowrap;">สาขาวิชา</span>
            <span class="dotted-line" style="flex: 1.5; text-align: center; margin: 0 15px 0 10px;">เทคโนโลยีวิศวกรรมคอมพิวเตอร์</span>
            <span style="white-space: nowrap;">คณะ</span>
            <span class="dotted-line" style="flex: 1; text-align: center; margin-left: 10px;">อุตสาหกรรมและเทคโนโลยี</span>
        </div>
        <div style="display: flex; align-items: baseline; width: 100%; margin-bottom: 12px;">
            <span style="white-space: nowrap;">ชื่อสถานประกอบการ</span>
            <span class="dotted-line" style="flex: 1; text-align: center; margin-left: 10px;"><?php echo htmlspecialchars($student['company_name'] ?? '-'); ?></span>
        </div>
        <div style="display: flex; align-items: baseline; width: 100%; margin-bottom: 12px;">
            <span style="white-space: nowrap;">ชื่อ-นามสกุลผู้ประเมิน</span>
            <span class="dotted-line" style="flex: 1; margin-left: 10px;"></span>
        </div>
        <div style="display: flex; align-items: baseline; width: 100%; margin-bottom: 12px;">
            <span style="white-space: nowrap;">ตำแหน่ง</span>
            <span class="dotted-line" style="flex: 1; margin: 0 15px 0 10px;"></span>
            <span style="white-space: nowrap;">แผนก</span>
            <span class="dotted-line" style="flex: 1; margin-left: 10px;"></span>
        </div>
    </div>

    <p class="bold" style="margin-top: 15px; font-size: 12pt;">ผลสำเร็จของงาน</p>
    <table style="border: none; width: 100%; margin-bottom: 5px; font-size: 10.5pt; line-height: 1.4;">
        <tr>
            <td style="border: none; padding: 0 15px 0 0; white-space: nowrap; width: 20%;">คำชี้แจงในการกรอกคะแนน :</td>
            <td style="border: none; padding: 0; width: 10%;">1 - 5</td>
            <td style="border: none; padding: 0; width: 5%; text-align: center;">=</td>
            <td style="border: none; padding: 0; width: 15%;">ควรปรับปรุง</td>
            <td style="border: none; padding: 0; width: 10%;">11 - 15</td>
            <td style="border: none; padding: 0; width: 5%; text-align: center;">=</td>
            <td style="border: none; padding: 0;">ดี</td>
        </tr>
        <tr>
            <td style="border: none;"></td>
            <td style="border: none; padding: 0;">6 - 10</td>
            <td style="border: none; padding: 0; text-align: center;">=</td>
            <td style="border: none; padding: 0;">พอใช้</td>
            <td style="border: none; padding: 0;">16 - 20</td>
            <td style="border: none; padding: 0; text-align: center;">=</td>
            <td style="border: none; padding: 0;">ดีมาก</td>
        </tr>
    </table>
    
    <table>
        <tr><td colspan="3" class="text-center bold">หัวข้อประเมิน</td></tr>
        <?php 
        echo renderItemRow(1, "ปริมาณงาน", "ปริมาณงานที่ปฏิบัติสำเร็จ ตามหน้าที่หรือตามที่ได้รับมอบหมายภายในระยะเวลาที่กำหนด (ในระดับที่นักศึกษาสหกิจศึกษาจะปฏิบัติได้) และเทียบกับนักศึกษาทั่ว ๆ ไป", 20, $eval['q1']);
        echo renderItemRow(2, "คุณภาพงาน", "ทำงานได้ถูกต้องครบถ้วนสมบูรณ์ มีความประณีตเรียบร้อย มีความรอบคอบ ไม่เกิดปัญหาตามมา งานไม่ค้าง ทำงานเสร็จทันเวลาหรือก่อนเวลาที่กำหนด", 20, $eval['q2']);
        ?>
    </table>
    
    <div style="position: absolute; bottom: 15mm; right: 20mm; left: 20mm;">
        <div style="border-top: 1px solid #ddd; width: 100%; margin-bottom: 5px;"></div>
        <div style="text-align: right; font-size: 10pt; color: #555; font-weight: bold;">1 | 4</div>
    </div>
</div>

<div class="a4-page">
    <?php echo renderHeader(); ?>

    <p class="bold" style="font-size: 12pt;">ความรู้ความสามารถ</p>
    <p style="font-size: 10.5pt; margin-bottom: 5px;">คำชี้แจงในการกรอกคะแนน : &nbsp;&nbsp; 1 = ควรปรับปรุง &nbsp;&nbsp; 2 = พอใช้ &nbsp;&nbsp; 3 = ดี &nbsp;&nbsp; 4 = ดีมาก</p>
    
    <table>
        <tr><td colspan="3" class="text-center bold">หัวข้อประเมิน</td></tr>
        <?php 
        echo renderItemRow(1, "ความรู้ความสามารถทางวิชาการ", "นักศึกษาสหกิจศึกษามีความรู้ทางวิชาการเพียงพอที่จะปฏิบัติงาน ตามที่ได้รับมอบหมาย", 4, $eval['q3']);
        echo renderItemRow(2, "ความสามารถในการเรียนรู้และประยุกต์วิชาการ", "ความรวดเร็วในการเรียนรู้ เข้าใจข้อมูล ข่าวสาร และวิธีการทำงาน ตลอดจนการนำ ความรู้ไปประยุกต์ใช้งาน", 4, $eval['q4']);
        echo renderItemRow(3, "ความรู้ความชำนาญด้านปฏิบัติการ", "เช่น การปฏิบัติงานในภาคสนาม ในห้องปฏิบัติการ เป็นต้น", 4, $eval['q5']);
        echo renderItemRow(4, "วิจารณญาณและการตัดสินใจ", "มีการวิเคราะห์ข้อมูลและปัญหาต่างๆ อย่างรอบคอบก่อนการตัดสินใจ ทำให้ตัดสินใจได้ดี ถูกต้อง รวดเร็ว สามารถแก้ปัญหาเฉพาะหน้าได้ดี และสามารถไว้วางใจให้ตัดสินใจได้ด้วยตนเอง", 4, $eval['q6']);
        echo renderItemRow(5, "ทักษะการสื่อสาร", "ความสามารถในการติดต่อสื่อสาร การพูด การเขียน และการนำเสนอ (Presentation) สามารถสื่อให้เข้าใจได้ง่าย ชัดเจน ถูกต้อง รัดกุม มีลำดับขั้นตอนที่ดี ไม่ก่อให้เกิดความสับสนต่อการทำงาน รู้จักสอบถาม รู้จักชี้แจงผลการปฏิบัติงานและข้อขัดข้องให้ทราบ", 4, $eval['q7']);
        echo renderItemRow(6, "ความสามารถทางภาษาต่างประเทศ / วัฒนธรรมต่างประเทศ", "ความสามารถในการใช้ภาษาต่างประเทศ เช่น ภาษาอังกฤษ ภาษาจีน ในการปฏิบัติงานและติดต่อสื่อสาร ตลอดจนมีความเข้าใจและการปรับตัวให้เข้ากับการทำงานกับชาวต่างประเทศ", 4, $eval['q8']);
        echo renderItemRow(7, "ความเหมาะสมกับตำแหน่งงานที่ได้รับมอบหมาย", "นักศึกษาสหกิจศึกษาพัฒนาตนเองให้ปฏิบัติงานตามตำแหน่งงาน (Job position) และลักษณะงาน (Job description) ที่ได้รับมอบหมายได้อย่างเหมาะสม", 4, $eval['q9']);
        ?>
    </table>
    
    <div style="position: absolute; bottom: 15mm; right: 20mm; left: 20mm;">
        <div style="border-top: 1px solid #ddd; width: 100%; margin-bottom: 5px;"></div>
        <div style="text-align: right; font-size: 10pt; color: #555; font-weight: bold;">2 | 4</div>
    </div>
</div>

<div class="a4-page">
    <?php echo renderHeader(); ?>
    
    <p class="bold" style="font-size: 12pt;">ความรับผิดชอบต่อหน้าที่</p>
    <p style="font-size: 10.5pt; margin-bottom: 5px;">คำชี้แจงในการกรอกคะแนน : &nbsp;&nbsp; 1 = ควรปรับปรุง &nbsp;&nbsp; 2 = พอใช้ &nbsp;&nbsp; 3 = ดี &nbsp;&nbsp; 4 = ดีมาก</p>
    
    <table>
        <tr><td colspan="3" class="text-center bold">หัวข้อประเมิน</td></tr>
        <?php 
        echo renderItemRow(8, "ความสามารถเริ่มต้นทำงานได้ด้วยตนเอง", "สามารถเริ่มงานที่ต้องรับผิดชอบเป็นประจำได้ด้วยตนเอง โดยไม่ต้องรอคำสั่งตลอดจนมีการเสนอตัวเข้าช่วยงานอื่นๆ ที่นอกเหนือจากหน้าที่ประจำ โดยไม่ปล่อยเวลาว่างให้เปล่าประโยชน์", 4, $eval['q10']);
        echo renderItemRow(9, "ความรับผิดชอบและเป็นผู้ที่ไว้วางใจได้", "ดำเนินงานสำเร็จลุล่วงโดยคำนึงถึงเป้าหมาย และความสำเร็จของงานเป็นหลัก ยอมรับผลที่เกิดจากการทำงานอย่างมีเหตุผล สามารถปล่อยให้ทำงาน (กรณีงานประจำ) ได้โดยไม่ต้องควบคุมมากจนเกินไป", 4, $eval['q11']);
        echo renderItemRow(10, "ความสนใจ ความอุตสาหะในการทำงาน", "ความสนใจและความกระตือรือร้นในการทำงาน มีความอุตสาหะ ความพยายามความตั้งใจที่จะทำงานให้สำเร็จ ความมานะบากบั่น ไม่ย่อท้อต่ออุปสรรค และปัญหา", 4, $eval['q12']);
        echo renderItemRow(11, "การตอบสนองต่อการสั่งการ", "ยินดีรับคำสั่ง คำแนะนำ คำวิจารณ์ หรือคำตักเตือน รวมทั้งมีความรวดเร็วในการปฏิบัติตามคำสั่ง และมีความพร้อมที่จะปรับตัวให้เป็นไปตามคำแนะนำ ข้อเสนอแนะและวิจารณ์", 4, $eval['q13']);
        ?>
    </table>

    <p class="bold" style="font-size: 12pt; margin-top: 15px;">ลักษณะส่วนบุคคล</p>
    <p style="font-size: 10.5pt; margin-bottom: 5px;">คำชี้แจงในการกรอกคะแนน : &nbsp;&nbsp; 1 = ควรปรับปรุง &nbsp;&nbsp; 2 = พอใช้ &nbsp;&nbsp; 3 = ดี &nbsp;&nbsp; 4 = ดีมาก</p>
    <table>
        <tr><td colspan="3" class="text-center bold">หัวข้อประเมิน</td></tr>
        <?php 
        echo renderItemRow(1, "บุคลิกภาพและการวางตัว", "มีบุคลิกภาพและการวางตัวได้อย่างเหมาะสม เช่น ทัศนคติ วุฒิภาวะ อ่อนน้อมถ่อมตน การแต่งกาย กิริยาวาจา การตรงต่อเวลา และอื่นๆ", 4, $eval['q14']);
        echo renderItemRow(2, "มนุษยสัมพันธ์", "สามารถร่วมงานกับผู้อื่นได้ดี การทำงานเป็นทีม สร้างมนุษยสัมพันธ์ได้ดี เป็นที่รักใคร่ชอบพอของผู้ร่วมงาน เป็นผู้ที่ช่วยก่อให้เกิดความร่วมมือประสานงาน", 4, $eval['q15']);
        echo renderItemRow(3, "ความมีระเบียบวินัย ปฏิบัติตามวัฒนธรรมขององค์กร", "ความสนใจเรียนรู้ ศึกษา กฎระเบียบ นโยบายต่างๆ ขององค์กรและปฏิบัติตามโดยเต็มใจ เช่น การปฏิบัติตามระเบียบบริหารงานบุคคล (การเข้างาน ลางาน) ปฏิบัติตามกฎการรักษาความปลอดภัยในโรงงาน การควบคุมคุณภาพ 5 ส และอื่นๆ", 4, $eval['q16']);
        echo renderItemRow(4, "คุณธรรมและจริยธรรม", "มีความซื่อสัตย์ สุจริต มีน้ำใจ รู้จักเสียสละ ไม่เห็นแก่ตัว เอื้อเฟื้อช่วยเหลือผู้อื่น", 4, $eval['q17']);
        ?>
    </table>
    
    <div style="position: absolute; bottom: 15mm; right: 20mm; left: 20mm;">
        <div style="border-top: 1px solid #ddd; width: 100%; margin-bottom: 5px;"></div>
        <div style="text-align: right; font-size: 10pt; color: #555; font-weight: bold;">3 | 4</div>
    </div>
</div>

<div class="a4-page">
    <?php echo renderHeader(); ?>

    <p class="bold" style="font-size: 12pt;">โปรดให้ข้อคิดเห็นที่เป็นประโยชน์แก่นักศึกษา</p>
    <table>
        <tr>
            <td class="text-center bold" style="width: 50%;">จุดเด่นของนักศึกษา</td>
            <td class="text-center bold" style="width: 50%;">ข้อควรปรับปรุงของนักศึกษา</td>
        </tr>
        <tr>
            <td style="height: 100px; padding: 10px; font-size: 10.5pt;"><?php echo nl2br(htmlspecialchars($eval['strengths'] ?? '')); ?></td>
            <td style="height: 100px; padding: 10px; font-size: 10.5pt;"><?php echo nl2br(htmlspecialchars($eval['improvements'] ?? '')); ?></td>
        </tr>
    </table>

    <div style="margin-top: 15px; line-height: 1.8; font-size: 10.5pt;">
        <p class="bold">หากนักศึกษาผู้นี้สำเร็จการศึกษาแล้ว ท่านจะรับเข้าทำงานในสถานประกอบการนี้หรือไม่ (หากมีโอกาสเลือก)</p>
        <p style="margin-left: 20px;">
            <?php echo roundCheck($eval['hire_decision'], 'รับ'); ?> รับ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <?php echo roundCheck($eval['hire_decision'], 'ไม่แน่ใจ'); ?> ไม่แน่ใจ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <?php echo roundCheck($eval['hire_decision'], 'ไม่รับ'); ?> ไม่รับ
        </p>
    </div>

    <div style="margin-top: 15px; line-height: 1.8; font-size: 10.5pt;">
        <p class="bold">สรุปโดยภาพรวมท่านมีความคิดเห็นต่อคุณภาพนักศึกษาคนนี้ในระดับใด</p>
        <p style="margin-left: 20px;">
            <?php echo squareCheck($eval['overall_grade'], 'ควรปรับปรุง'); ?> ควรปรับปรุง &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <?php echo squareCheck($eval['overall_grade'], 'พอใช้'); ?> พอใช้ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <?php echo squareCheck($eval['overall_grade'], 'ดี'); ?> ดี &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <?php echo squareCheck($eval['overall_grade'], 'ดีมาก'); ?> ดีมาก
        </p>
    </div>

    <p class="bold" style="margin-top: 15px; font-size: 11.5pt; margin-bottom: 5px;">ข้อคิดเห็นเพิ่มเติม</p>
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
                <div style="width: 140px; white-space: nowrap; text-align: left;">ลงชื่อพนักงานที่ปรึกษา</div>
                <div style="flex: 1; border-bottom: 1px dotted black; margin-left: 5px;"></div>
            </div>
            <div style="display: flex; align-items: baseline; width: 100%;">
                <div style="width: 140px;"></div>
                <div style="margin-left: 5px; margin-right: 5px;">(</div>
                <div style="flex: 1; border-bottom: 1px dotted black;"></div>
                <div style="margin-left: 5px;">)</div>
            </div>
            <div style="display: flex; align-items: baseline; width: 100%;">
                <div style="width: 140px; text-align: right; padding-right: 15px; box-sizing: border-box;">ตำแหน่ง</div>
                <div style="flex: 1; border-bottom: 1px dotted black; margin-left: 5px;"></div>
            </div>
            <div style="display: flex; align-items: baseline; width: 100%;">
                <div style="width: 140px; text-align: right; padding-right: 15px; box-sizing: border-box;">วันที่</div>
                <div style="border-bottom: 1px dotted black; flex: 1; margin-left: 5px;"></div>
                <div style="margin: 0 10px;">เดือน</div>
                <div style="border-bottom: 1px dotted black; flex: 1.5;"></div>
                <div style="margin: 0 10px;">พ.ศ.</div>
                <div style="border-bottom: 1px dotted black; flex: 1;"></div>
            </div>
        </div>
    </div>

    <div style="border: 1px solid black; width: 95%; margin: 20px auto; padding: 25px 20px; box-sizing: border-box;">
        <div style="text-align: center; font-weight: bold; font-size: 11.5pt; margin-bottom: 25px;">สรุปคะแนน</div>
        
        <div style="width: 85%; margin: 0 auto; font-size: 10.5pt; line-height: 2.5;">
            <div style="display: flex; align-items: baseline; justify-content: center; width: 100%;">
                <div style="width: 45%; text-align: right; padding-right: 15px; box-sizing: border-box; white-space: nowrap;">รวม</div>
                <div style="width: 20px; text-align: center;">=</div>
                <div style="flex: 1; text-align: center; border-bottom: 1px dotted black; margin: 0 10px; font-weight: bold;">
                    <?php echo $eval['total_score']; ?>
                </div>
                <div style="width: 25%; padding-left: 10px; box-sizing: border-box;">คะแนน</div>
            </div>
            
            <div style="display: flex; align-items: baseline; justify-content: center; width: 100%;">
                <div style="width: 45%; text-align: right; padding-right: 15px; box-sizing: border-box; white-space: nowrap;">คะแนนเต็ม 100 คะแนน คิดเป็น 50%</div>
                <div style="width: 20px; text-align: center;">=</div>
                <div style="flex: 1; text-align: center; border-bottom: 1px dotted black; margin: 0 10px; font-weight: bold;">
                    <?php echo ($eval['total_score'] / 2); ?>
                </div>
                <div style="width: 25%; padding-left: 10px; box-sizing: border-box;">คะแนน</div>
            </div>
            
            <div style="display: flex; align-items: baseline; justify-content: center; width: 100%; margin-top: 15px;">
                <div style="width: 20%; text-align: right; padding-right: 15px; box-sizing: border-box;">ลงชื่อ</div>
                <div style="flex: 1; border-bottom: 1px dotted black; margin: 0 10px;"></div>
                <div style="width: 30%; white-space: nowrap; padding-left: 10px;">พนักงานที่ปรึกษา</div>
            </div>
            
            <div style="display: flex; align-items: baseline; justify-content: center; width: 100%;">
                <div style="width: 20%; text-align: right; padding-right: 15px; box-sizing: border-box;">วันที่</div>
                <div style="flex: 1; border-bottom: 1px dotted black; margin: 0 10px;"></div>
                <div style="width: 30%; padding-left: 10px;"></div>
            </div>
        </div>
    </div>
    
    <div style="width: 100%; margin-top: 20px; padding-bottom: 10px;">
        <div style="border-top: 1px solid #ddd; width: 100%; margin-bottom: 5px;"></div>
        <div style="text-align: right; font-size: 10pt; color: #555; font-weight: bold;">4 | 4</div>
    </div>
</div>

</body>
</html>