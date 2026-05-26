<?php
// เปิด Error เพื่อเช็คข้อผิดพลาด
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'db.php';

// ตรวจสอบสิทธิ์การเข้าถึง (เฉพาะอาจารย์)
if (!isset($_SESSION['users_name']) || $_SESSION['user_level'] != 't') {
    die("<h2 style='text-align:center; color:red; margin-top:50px;'>❌ ไม่มีสิทธิ์เข้าถึงหน้านี้ หรือไม่ได้ล็อกอินในฐานะอาจารย์</h2>");
}

$export_year = isset($_GET['export_year']) ? $conn->real_escape_string($_GET['export_year']) : '';
if(empty($export_year)){
    die("<h2 style='text-align:center; color:red; margin-top:50px;'>❌ กรุณาระบุปีการศึกษาที่ต้องการดูสรุปคะแนน</h2>");
}

// คิวรี่ใช้ LEFT JOIN ทั้งหมด เพื่อดึงรายชื่อนักศึกษาตามปีที่เลือกมา "ทุกคน" 
// แล้วค่อยเช็คว่าใครมีข้อมูล internship_year บ้าง
$sql = "
    SELECT u.users_name, u.full_name,
           te.total_score AS c_score,
           tre.s1_total AS r1_score,
           tre.s2_total AS r2_score,
           isu.internship_year
    FROM users u
    LEFT JOIN internship_summaries isu ON u.users_name = isu.student_id
    LEFT JOIN teacher_evaluations te ON u.users_name = te.student_id
    LEFT JOIN teacher_report_evaluations tre ON u.users_name = tre.student_id
    WHERE u.user_level = 's' AND u.academic_year = '$export_year'
    ORDER BY u.users_name ASC
";
$result = $conn->query($sql);
if(!$result){
    die("SQL Error: " . $conn->error);
}

// เก็บข้อมูลทั้งหมดลง Array
$students = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
}

// หาปีที่ฝึกงานจริง เพื่อนำไปแสดงในหัวกระดาษ
$display_year = ''; // ตั้งค่าเริ่มต้นให้ว่างเปล่า
foreach ($students as $s) {
    if (!empty($s['internship_year'])) {
        $display_year = $s['internship_year'];
        break; // ถ้าเจอคนที่มีปีฝึกงานแล้ว ให้ดึงปีนั้นมาใช้แล้วหยุดค้นหาทันที
    }
}

// กำหนดจำนวนคนต่อ 1 หน้า
$rows_per_page = 15;
$pages = array_chunk($students, $rows_per_page);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>สรุปคะแนน กสศ.15</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ตั้งค่าหน้ากระดาษ */
        @page { 
            size: A4 landscape; 
            margin: 0; 
        } 
        
        html, body { 
            margin: 0; 
            padding: 0; 
            background-color: #525659; 
            font-family: 'Sarabun', sans-serif; 
            color: #000; 
            -webkit-print-color-adjust: exact; 
            print-color-adjust: exact;
        }

        /* กล่อง A4 แนวนอน 1 หน้า */
        .a4-page-landscape { 
            width: 297mm; 
            height: 210mm; 
            padding: 10mm 15mm; 
            margin: 20px auto; 
            background: white; 
            box-shadow: 0 0 10px rgba(0,0,0,0.5); 
            box-sizing: border-box; 
            position: relative;
            overflow: hidden;
            page-break-after: always;
        }
        
        .a4-page-landscape:last-of-type { 
            page-break-after: auto; 
        }

        /* =========================================
           ส่วนหัวกระดาษ 
           ========================================= */
        .header-wrapper {
            position: relative; 
            text-align: center; 
            margin-bottom: 10px;
            padding-top: 5px;
        }

        .header-center-group {
            display: inline-flex;
            align-items: center; 
            justify-content: center;
            gap: 15px; 
        }

        .header-logo {
            flex-shrink: 0;
        }
        .header-logo img {
            width: 65px; 
            height: auto;
        }

        .header-text-block {
            text-align: left; 
        }
        
        .txt-th-title {
            font-size: 12.5pt; 
            font-weight: 600;
            color: #555; 
            margin-bottom: 0px; 
            letter-spacing: 0.5px;
        }
        .txt-en-title {
            font-size: 10pt; 
            color: #666;
            padding-bottom: 2px; 
            margin-bottom: 3px; 
            border-bottom: 1px solid #777; 
        }
        .txt-address {
            font-size: 8.5pt; 
            line-height: 1.4; 
            color: #666;
        }
        .txt-address span.space {
            display: inline-block;
            width: 15px; 
        }

        .header-doc-box {
            position: absolute;
            right: 0;
            top: 0;
        }
        .box-15 {
            display: inline-block;
            border: 1px solid #999;
            padding: 4px 10px;
            font-size: 10pt; 
            font-weight: 600;
            color: #777; 
        }

        /* =========================================
           ชื่อเอกสาร
           ========================================= */
        .doc-title-block {
            text-align: center;
            margin-bottom: 10px; 
            line-height: 1.3; 
        }
        .doc-title-main {
            font-size: 12pt; 
            font-weight: bold;
        }
        .doc-title-sub {
            font-size: 10pt; 
            font-weight: bold;
        }
        .doc-title-info {
            font-size: 9.5pt; 
        }

        /* =========================================
           ส่วนตาราง 
           ========================================= */
        table { 
            width: 100%; 
            border-collapse: collapse; 
            font-size: 9.5pt; 
        }
        th, td { 
            border: 1px solid black; 
            padding: 4px 4px; 
            vertical-align: middle; 
        }
        th { 
            text-align: center; 
            font-weight: normal; 
        }
        
        .text-center { text-align: center; }

        /* =========================================
           ส่วนลงชื่ออาจารย์ (Signature)
           ========================================= */
        /* ปุ่ม Print */
        .print-btn-container { position: fixed; top: 20px; right: 20px; z-index: 1000; }
        .btn-print { background-color: #0d6efd; color: white; border: none; padding: 10px 20px; font-family: 'Sarabun', sans-serif; font-size: 11pt; border-radius: 5px; cursor: pointer; box-shadow: 0 4px 10px rgba(0,0,0,0.3); font-weight: 600; }
        .btn-print:hover { background-color: #0b5ed7; }

        @media print {
            body { background: white; padding: 0; }
            .a4-page-landscape { margin: 0; border: none; box-shadow: none; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="print-btn-container no-print">
    <button onclick="window.print()" class="btn-print"><i class="fas fa-print me-2"></i> พิมพ์ / บันทึก PDF</button>
</div>

<?php 
// ฟังก์ชันสำหรับแสดงส่วนหัวกระดาษ
function render_header($display_year) {
    // ถ้า $display_year มีค่า จะแสดงปี ถ้าว่าง จะเว้นช่องว่างเอาไว้
    $year_text = !empty($display_year) ? htmlspecialchars($display_year) : ''; 

    echo '
    <div class="header-wrapper">
        <div class="header-center-group">
            <div class="header-logo">
                <img src="logo.png" alt="โลโก้">
            </div>
            <div class="header-text-block">
                <div class="txt-th-title">สหกิจศึกษา &nbsp;&nbsp;มหาวิทยาลัยเทคโนโลยีราชมงคลรัตนโกสินทร์</div>
                <div class="txt-en-title">Cooperative Education Rajamangala University of Technology Rattanakosin</div>
                <div class="txt-address">งานสหกิจศึกษา คณะอุตสาหกรรมและเทคโนโลยี มหาวิทยาลัยเทคโนโลยีราชมงคลรัตนโกสินทร์ วิทยาเขตวังไกลกังวล</div>
                <div class="txt-address">กม.242 ถนน เพชรเกษม ตำบล หนองแก อำเภอ หัวหิน จังหวัด ประจวบคีรีขันธ์ 77110</div>
                <div class="txt-address">โทรศัพท์ &nbsp;032-618-500 &nbsp;ต่อ 4718 <span class="space"></span> โทรสาร &nbsp;032-618570</div>
            </div>
        </div>
        <div class="header-doc-box">
            <div class="box-15">กสศ. 15</div>
        </div>
    </div>
    
    <div class="doc-title-block">
        <div class="doc-title-main">แบบสรุปผลการประเมินผลการเข้าร่วมสหกิจศึกษา</div>
        <div class="doc-title-sub">(สำหรับอาจารย์ที่ปรึกษาสหกิจศึกษา / อาจารย์นิเทศ)</div>
        <div class="doc-title-info">คณะอุตสาหกรรมและเทคโนโลยี สาขาวิชาเทคโนโลยีวิศวกรรมคอมพิวเตอร์ ภาคการศึกษาที่ 1 / '.$year_text.'</div>
    </div>';
}

function render_signature() {
    echo '
    <div class="signature-container" style="display: flex; justify-content: flex-end; margin-top: 25px; padding-right: 60px;">
        <div class="signature-block" style="display: inline-block; text-align: left; font-size: 11pt; color: #000; line-height: 1.7; position: relative;">
            
            <div class="sig-row">
                <span style="display: inline-block; width: 45px;">ลงชื่อ</span>
                <span>...........................................................................</span>
            </div>
            
            <div class="sig-row">
                <span style="display: inline-block; width: 45px;"></span>
                <span>(.........................................................................)</span>
            </div>
            
            <div class="sig-row" style="text-align: center; padding-left: 45px; margin-top: 5px;">
                อาจารย์ที่ปรึกษาสหกิจศึกษา
            </div>
            
            <div class="sig-row" style="text-align: center; padding-left: 45px; margin-top: 8px;">
                วันที่........เดือน...........................พ.ศ...............
            </div>
        </div>
    </div>';
}

if (count($students) > 0): 
    $global_index = 1; 
    $total_pages = count($pages);
    $need_new_page_for_signature = false;

    foreach ($pages as $page_number => $chunk):
        $is_last_page = ($page_number == $total_pages - 1);
        $rows_in_this_chunk = count($chunk);
?>
    <div class="a4-page-landscape">
        
        <?php render_header($display_year); ?>

        <table>
            <thead>
                <tr>
                    <th rowspan="2" style="width: 4%;">ที่</th>
                    <th rowspan="2" style="width: 20%;">ชื่อ-นามสกุล</th>
                    <th rowspan="2" style="width: 12%;">รหัส</th>
                    <th style="width: 12%;">ประเมินผลปฏิบัติ<br>งานจากสถาน<br>ประกอบการ<br>50 %</th>
                    <th style="width: 12%;">ประเมินผล<br>โครงการ<br>เตรียมความพร้อม<br>10 %</th>
                    <th style="width: 12%;">ประเมินผล<br>รายงาน<br><br>20 %</th>
                    <th style="width: 12%;">ประเมินผล<br>การนำเสนอ<br>ผลงาน<br>20 %</th>
                    <th rowspan="2" style="width: 8%;">รวม<br>คะแนน<br><br>100 %</th>
                    <th rowspan="2" style="width: 8%;">หมายเหตุ</th>
                </tr>
                <tr>
                    <th>ตามแบบฟอร์ม<br>กสศ.11</th>
                    <th>ตามดุลยพินิจ<br>ของอาจารย์นิเทศ</th>
                    <th>ตามแบบฟอร์ม<br>กสศ.14</th>
                    <th>ตามแบบฟอร์ม<br>กสศ.14</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($chunk as $row): 
                    $c_val = is_numeric($row['c_score']) ? ($row['c_score'] / 2) : 0;
                    $r1_val = is_numeric($row['r1_score']) ? ($row['r1_score'] * 0.2) : 0;
                    $r2_val = is_numeric($row['r2_score']) ? ($row['r2_score'] * 0.2) : 0;
                    $prep_val = ($c_val > 0 || $r1_val > 0) ? 10 : 0;
                    
                    $total_score = $c_val + $r1_val + $r2_val + $prep_val;
                    
                    $show_c = ($c_val > 0) ? number_format($c_val, 1) : '-';
                    $show_prep = ($prep_val > 0) ? number_format($prep_val, 0) : '-';
                    $show_r1 = ($r1_val > 0) ? number_format($r1_val, 0) : '-';
                    $show_r2 = ($r2_val > 0) ? number_format($r2_val, 0) : '-';
                    $show_total = ($total_score > 0) ? number_format($total_score, 1) : '-';
                ?>
                <tr>
                    <td class="text-center"><?php echo $global_index; ?></td>
                    <td>&nbsp;<?php echo htmlspecialchars($row['full_name']); ?></td>
                    <td class="text-center"><?php echo htmlspecialchars($row['users_name']); ?></td>
                    <td class="text-center"><?php echo $show_c; ?></td>
                    <td class="text-center"><?php echo $show_prep; ?></td>
                    <td class="text-center"><?php echo $show_r1; ?></td>
                    <td class="text-center"><?php echo $show_r2; ?></td>
                    <td class="text-center"><?php echo $show_total; ?></td>
                    <td></td>
                </tr>
                <?php 
                    $global_index++;
                endforeach; 
                ?>
            </tbody>
        </table>

        <?php 
        if ($is_last_page) {
            if ($rows_in_this_chunk <= 10) {
                render_signature();
            } else {
                $need_new_page_for_signature = true; 
            }
        }
        ?>
    </div>
<?php 
    endforeach; 

    if ($need_new_page_for_signature):
?>
    <div class="a4-page-landscape">
        <?php 
        render_header($display_year); 
        echo '<div style="margin-top: 50px;">';
        render_signature(); 
        echo '</div>';
        ?>
    </div>
<?php
    endif;

else: 
?>
    <div class="a4-page-landscape">
        <h3 style="text-align: center; margin-top: 50px;">ไม่พบข้อมูลนักศึกษาสำหรับปีการศึกษานี้</h3>
    </div>
<?php endif; ?>

</body>
</html>