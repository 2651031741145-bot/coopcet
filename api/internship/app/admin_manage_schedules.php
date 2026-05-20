<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
include 'db_connect.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method == 'GET') {
    // ดึงข้อมูลรอบการฝึกงานทั้งหมดมาแสดงใน Dropdown
    $sql = "SELECT * FROM internship_rounds ORDER BY academic_year DESC, round_id DESC";
    $result = $conn->query($sql);
    $data = array();

    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    echo json_encode(["success" => true, "data" => $data]);
    exit();
} 
elseif ($method == 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // 1. สร้างรอบการฝึกงานใหม่
    if ($action == 'create_round') {
        $academic_year = $_POST['academic_year'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $admin_id = isset($_POST['admin_id']) ? $_POST['admin_id'] : 'admin';

        $sql = "INSERT INTO internship_rounds (academic_year, start_date, end_date, created_by) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $academic_year, $start_date, $end_date, $admin_id);
        
        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "สร้างรอบฝึกงานปี $academic_year สำเร็จ"]);
        } else {
            echo json_encode(["success" => false, "message" => "เกิดข้อผิดพลาด: " . $conn->error]);
        }
        exit();
    }

    // 🚨 2. แก้ไขรอบการฝึกงาน (ใหม่) 🚨
    if ($action == 'edit_round') {
        $round_id = $_POST['round_id'];
        $academic_year = $_POST['academic_year'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];

        $sql = "UPDATE internship_rounds SET academic_year=?, start_date=?, end_date=? WHERE round_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $academic_year, $start_date, $end_date, $round_id);
        
        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "อัปเดตข้อมูลปี $academic_year สำเร็จ!"]);
        } else {
            echo json_encode(["success" => false, "message" => "แก้ไขไม่สำเร็จ: " . $conn->error]);
        }
        exit();
    }

    // 🚨 3. ลบรอบการฝึกงาน (ใหม่) 🚨
    if ($action == 'delete_round') {
        $round_id = $_POST['round_id'];

        // ค้นหาว่ามีไฟล์ PDF ไหม ถ้ามีให้ลบไฟล์จริงออกจากเซิร์ฟเวอร์ด้วย
        $check_sql = "SELECT supervision_schedule_pdf FROM internship_rounds WHERE round_id=?";
        $stmt_check = $conn->prepare($check_sql);
        $stmt_check->bind_param("i", $round_id);
        $stmt_check->execute();
        $res = $stmt_check->get_result();
        if ($row = $res->fetch_assoc()) {
            if (!empty($row['supervision_schedule_pdf']) && file_exists($row['supervision_schedule_pdf'])) {
                @unlink($row['supervision_schedule_pdf']); // ลบไฟล์
            }
        }

        // ลบข้อมูลจากฐานข้อมูล
        $del_sql = "DELETE FROM internship_rounds WHERE round_id=?";
        $stmt_del = $conn->prepare($del_sql);
        $stmt_del->bind_param("i", $round_id);
        
        if ($stmt_del->execute()) {
            echo json_encode(["success" => true, "message" => "ลบรอบการฝึกงานเรียบร้อยแล้ว! 🗑️"]);
        } else {
            echo json_encode(["success" => false, "message" => "ลบไม่สำเร็จ: " . $conn->error]);
        }
        exit();
    }

    // 4. อัปโหลดไฟล์ PDF กำหนดการนิเทศ
    if ($action == 'upload_pdf') {
        $round_id = $_POST['round_id'];
        
        $target_dir = "uploads/schedules/";
        if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }

        if(isset($_FILES["pdf_file"])) {
            $file_name = "supervision_round_" . $round_id . "_" . time() . ".pdf";
            $target_file = $target_dir . $file_name;
            $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

            if($file_type != "pdf") {
                echo json_encode(["success" => false, "message" => "กรุณาอัปโหลดไฟล์ PDF เท่านั้นครับ 😅"]);
                exit();
            }

            if (move_uploaded_file($_FILES["pdf_file"]["tmp_name"], $target_file)) {
                // ลบไฟล์เก่าออกก่อนถ้ามี
                $old_sql = "SELECT supervision_schedule_pdf FROM internship_rounds WHERE round_id = ?";
                $stmt_old = $conn->prepare($old_sql);
                $stmt_old->bind_param("i", $round_id);
                $stmt_old->execute();
                $res_old = $stmt_old->get_result();
                if ($old_row = $res_old->fetch_assoc()) {
                    if (!empty($old_row['supervision_schedule_pdf']) && file_exists($old_row['supervision_schedule_pdf'])) {
                        @unlink($old_row['supervision_schedule_pdf']);
                    }
                }

                $sql = "UPDATE internship_rounds SET supervision_schedule_pdf = ? WHERE round_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $target_file, $round_id);
                
                if ($stmt->execute()) {
                    echo json_encode(["success" => true, "message" => "อัปโหลดไฟล์กำหนดการนิเทศสำเร็จ! 🎉"]);
                }
            } else {
                echo json_encode(["success" => false, "message" => "อัปโหลดไฟล์ไม่สำเร็จ"]);
            }
        } else {
            echo json_encode(["success" => false, "message" => "ไม่พบไฟล์ที่ถูกส่งมา"]);
        }
        exit();
    }
}
?>