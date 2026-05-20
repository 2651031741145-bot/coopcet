<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
include 'db_connect.php';

$method = $_SERVER['REQUEST_METHOD'];

// ==========================================
// 1. กรณี GET: ดึงข้อมูลนักศึกษาและสถานะการฝึกงาน
// ==========================================
if ($method == 'GET') {
    // 🚨 เพิ่มการดึง ir.academic_year เข้ามาด้วย
    $sql = "SELECT 
                si.internship_id, 
                si.status, 
                si.created_at,
                u.users_name AS student_id, 
                u.full_name, 
                c.company_name,
                ir.academic_year
            FROM student_internships si
            JOIN users u ON si.student_id = u.users_name
            JOIN companies c ON si.company_id = c.company_id
            JOIN internship_rounds ir ON si.round_id = ir.round_id
            ORDER BY ir.academic_year DESC, si.created_at DESC";
            
    $result = $conn->query($sql);
    $data = array();

    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode(["success" => true, "data" => $data]);
    } else {
        echo json_encode(["success" => true, "data" => []]);
    }
}
// ==========================================
// 2. กรณี POST: อัปเดตสถานะการฝึกงาน หรือ รีเซ็ต
// ==========================================
elseif ($method == 'POST') {
    $internship_id = isset($_POST['internship_id']) ? trim($_POST['internship_id']) : '';
    $new_status = isset($_POST['status']) ? trim($_POST['status']) : '';

    if (empty($internship_id) || empty($new_status)) {
        echo json_encode(["success" => false, "message" => "ข้อมูลไม่ครบถ้วน"]);
        exit();
    }

    // 🚨 ถ้ารับค่าเป็น 'reset' ให้ทำการ "ล้างบางข้อมูลทั้งหมด" ที่เกี่ยวข้องกับรอบการฝึกงานนี้
    if ($new_status === 'reset') {
        
        // 1. ดึงรหัสนักศึกษาก่อน เพื่อเอาไปตามลบข้อมูลการประเมิน
        $find_student_sql = "SELECT student_id FROM student_internships WHERE internship_id = ?";
        $stmt_find = $conn->prepare($find_student_sql);
        $stmt_find->bind_param("i", $internship_id);
        $stmt_find->execute();
        $res = $stmt_find->get_result();
        $student_id = "";
        if ($row = $res->fetch_assoc()) {
            $student_id = $row['student_id'];
        }

        // 2. ลบ "ไฟล์เอกสารโปรเจกต์" ออกจากโฟลเดอร์บนเซิร์ฟเวอร์ (ป้องกันไฟล์ขยะเต็ม)
        $find_file_sql = "SELECT project_file_path FROM internship_summaries WHERE internship_id = ?";
        $stmt_file = $conn->prepare($find_file_sql);
        $stmt_file->bind_param("i", $internship_id);
        $stmt_file->execute();
        $res_file = $stmt_file->get_result();
        if ($row_file = $res_file->fetch_assoc()) {
            // สมมติว่าไฟล์อยู่ในโฟลเดอร์ระดับเดียวกับโฟลเดอร์ app หรืออยู่ข้างนอก 
            // อาจจะต้องปรับ '../' ตามตำแหน่งโฟลเดอร์ที่เก็บไฟล์จริงๆ ของคุณศิรศักดิ์ครับ
            $file_path = "../" . $row_file['project_file_path']; 
            if (!empty($row_file['project_file_path']) && file_exists($file_path)) {
                unlink($file_path); // คำสั่งลบไฟล์ทิ้ง
            }
        }

        // 3. กวาดลบข้อมูลจากตาราง "สรุปการฝึกงาน"
        $del_summary_sql = "DELETE FROM internship_summaries WHERE internship_id = ?";
        $stmt_del_sum = $conn->prepare($del_summary_sql);
        $stmt_del_sum->bind_param("i", $internship_id);
        $stmt_del_sum->execute();

        // 4. กวาดลบข้อมูล "การประเมินของอาจารย์" (ถ้ามี)
        if (!empty($student_id)) {
            $del_eval_sql = "DELETE FROM teacher_evaluations WHERE student_id = ?";
            $stmt_del_eval = $conn->prepare($del_eval_sql);
            $stmt_del_eval->bind_param("s", $student_id);
            $stmt_del_eval->execute();
            
            // ลบ log การประเมินด้วย
            $del_eval_log_sql = "DELETE FROM teacher_evaluations_log WHERE student_id = ?";
            $stmt_del_eval_log = $conn->prepare($del_eval_log_sql);
            $stmt_del_eval_log->bind_param("s", $student_id);
            $stmt_del_eval_log->execute();
        }

        // 5. ลบข้อมูลในตาราง "สถานะหลัก" (ส่วนตาราง daily_logs จะสลายไปเองเพราะมี ON DELETE CASCADE)
        $sql = "DELETE FROM student_internships WHERE internship_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $internship_id);

        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "รีเซ็ตสถานะและกวาดล้างข้อมูลเก่าทั้งหมดสำเร็จ"]);
        } else {
            echo json_encode(["success" => false, "message" => "เกิดข้อผิดพลาดในการรีเซ็ต: " . $conn->error]);
        }
    } 
    // ==========================================
    // ถ้าเป็นสถานะอื่นๆ (active, relocating, ฯลฯ) ให้อัปเดตตามปกติ
    else {
        $sql = "UPDATE student_internships SET status = ? WHERE internship_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $new_status, $internship_id);

        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "อัปเดตสถานะการฝึกงานเรียบร้อยแล้ว"]);
        } else {
            echo json_encode(["success" => false, "message" => "เกิดข้อผิดพลาดในการอัปเดตข้อมูล: " . $conn->error]);
        }
    }
} else {
    echo json_encode(["success" => false, "message" => "Invalid Request Method"]);
}
?>