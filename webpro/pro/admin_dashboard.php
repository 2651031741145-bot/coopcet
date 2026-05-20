<?php
session_start();
require_once 'db.php';

// --- ส่วนตรวจสอบสิทธิ์ ---
if (!isset($_SESSION['users_name']) || $_SESSION['user_level'] != 'a' || !isset($_SESSION['session_token'])) {
    header("Location: logout.php");
    exit();
}

$admin_username = $conn->real_escape_string($_SESSION['users_name']);
// ดึงชื่อแอดมินที่ล็อกอินอยู่มาแสดงผลแทน ID
$t_name_query = $conn->query("SELECT full_name FROM users WHERE users_name = '$admin_username'");
$admin_fullname = ($t_name_query && $t_name_query->num_rows > 0) ? $t_name_query->fetch_assoc()['full_name'] : 'ผู้ดูแลระบบ';
$current_token = $_SESSION['session_token'];

$check_sql = "SELECT session_token FROM users WHERE users_name = '$admin_username'";
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

// ==========================================
// Action: ดาวน์โหลดไฟล์ Template Excel (.xls)
// ==========================================
if (isset($_GET['action']) && $_GET['action'] == 'download_template') {
    ob_clean();
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=sample_students.xls");

    // สร้างตาราง HTML ปลอมตัวเป็นไฟล์ Excel เพื่อให้อ่านภาษาไทยได้เป๊ะๆ
    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head><meta http-equiv="content-type" content="application/vnd.ms-excel; charset=UTF-8"></head>';
    echo '<body>';
    echo '<table border="1">';
    echo '<tr><td style="background-color: #fce4d6; font-weight: bold;">รหัสนักศึกษา</td><td style="background-color: #d9e1f2; font-weight: bold;">ชื่อ-สกุล</td></tr>';

    // ทริค: ใช้ mso-number-format:'\@'; เพื่อบังคับให้ Excel มองรหัสนักศึกษาเป็น "ข้อความ" (Text) กันปัญหาเลขเพี้ยนเป็น E+
    echo '<tr><td style="mso-number-format:\'\@\';">2671031741101</td><td>นายสมจิต จงจอหอ</td></tr>';
    echo '<tr><td style="mso-number-format:\'\@\';">2671031741102</td><td>นายสมคิด จิตดี</td></tr>';
    echo '</table>';
    echo '</body></html>';
    exit();
}

$msg = "";
$msg_type = "";
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'users';

// ==========================================
// ส่วนจัดการ Action: ผู้ใช้งาน (USERS)
// ==========================================
if (isset($_GET['action']) && $_GET['action'] == 'clear_all_sessions') {
    $sql = "UPDATE users SET session_token = NULL, app_session_token = NULL WHERE users_name != '$admin_username'";
    if ($conn->query($sql)) {
        $msg = "✨ ล้าง Session (ทั้ง Web และ App) ของทุกคนเรียบร้อยแล้ว ✨";
        $msg_type = "success";
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'clear_session' && isset($_GET['id'])) {
    $target_user = $conn->real_escape_string($_GET['id']);
    if ($target_user != $admin_username) {
        $sql = "UPDATE users SET session_token = NULL, app_session_token = NULL WHERE users_name = '$target_user'";
        if ($conn->query($sql)) {
            $msg = "👋 บ๊ายบาย! เตะผู้ใช้ $target_user ออกจากระบบ (Web & App) แล้ว";
            $msg_type = "warning";
        }
    }
}

// Action: ลบผู้ใช้ออกจากระบบ (ฟังก์ชันใหม่ที่เพิ่มมา)
if (isset($_GET['action']) && $_GET['action'] == 'delete_user' && isset($_GET['id'])) {
    $del_id = $conn->real_escape_string($_GET['id']);
    if ($del_id != $admin_username) { // ป้องกันการเผลอลบแอดมินตัวเอง
        $sql = "DELETE FROM users WHERE users_name = '$del_id'";
        if ($conn->query($sql)) {
            $msg = "🗑️ ลบข้อมูลผู้ใช้รหัส $del_id ออกจากระบบถาวรเรียบร้อยแล้ว";
            $msg_type = "warning";
        } else {
            $msg = "😿 เกิดข้อผิดพลาดในการลบข้อมูลผู้ใช้";
            $msg_type = "danger";
        }
    } else {
        $msg = "⚠️ ไม่สามารถลบบัญชีผู้ดูแลระบบของตัวเองได้!";
        $msg_type = "danger";
    }
    $active_tab = 'users';
}

// ==========================================
// Action: เพิ่มบุคลากรใหม่ (อาจารย์ / แอดมิน)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_personnel'])) {
    $p_id = $conn->real_escape_string($_POST['users_name']);
    $p_name = $conn->real_escape_string($_POST['full_name']);
    $p_level = $conn->real_escape_string($_POST['user_level']);
    $p_year = $conn->real_escape_string($_POST['academic_year']);

    // ถ้ารหัสผ่านว่างเปล่า ให้ใช้รหัสประจำตัว (ID) เป็นรหัสผ่านเริ่มต้น
    $raw_password = !empty($_POST['password']) ? $_POST['password'] : $p_id;
    $hashed_pwd = password_hash($raw_password, PASSWORD_DEFAULT);

    // เช็คว่ามี ID นี้ในระบบแล้วหรือยัง
    $check_exist = $conn->query("SELECT users_name FROM users WHERE users_name = '$p_id'");
    if ($check_exist && $check_exist->num_rows > 0) {
        $msg = "⚠️ ไม่สามารถเพิ่มบุคลากรได้ เนื่องจาก ID: <b>$p_id</b> มีอยู่ในระบบแล้ว!";
        $msg_type = "danger";
    } else {
        $sql = "INSERT INTO users (users_name, full_name, academic_year, password, user_level) 
                VALUES ('$p_id', '$p_name', '$p_year', '$hashed_pwd', '$p_level')";
        if ($conn->query($sql)) {
            $msg = "🎉 เพิ่มข้อมูลบุคลากร <b>$p_name</b> เรียบร้อยแล้ว!";
            $msg_type = "success";
        } else {
            $msg = "😿 เกิดข้อผิดพลาดในการเพิ่มข้อมูลลงฐานข้อมูล";
            $msg_type = "danger";
        }
    }
    $active_tab = 'users';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_user'])) {
    $old_id = $conn->real_escape_string($_POST['old_users_name']);
    $new_id = $conn->real_escape_string($_POST['users_name']);
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $academic_year = $conn->real_escape_string($_POST['academic_year']);
    $user_level = $conn->real_escape_string($_POST['user_level']);

    // --- ส่วนที่เพิ่มเข้ามาใหม่: รับค่ารหัสผ่านใหม่ ---
    $new_password = $_POST['new_password'] ?? '';
    $pwd_update_sql = "";
    if (!empty($new_password)) {
        $hashed_pwd = password_hash($new_password, PASSWORD_DEFAULT);
        $pwd_update_sql = ", password = '$hashed_pwd'"; // เตรียม SQL ไว้ต่อท้าย
    }
    // ----------------------------------------

    if ($old_id != $new_id) {
        $check_dup = $conn->query("SELECT users_name FROM users WHERE users_name = '$new_id'");
        if ($check_dup && $check_dup->num_rows > 0) {
            $msg = "⚠️ ไม่สามารถเปลี่ยน ID ได้ เนื่องจาก ID: $new_id มีอยู่ในระบบแล้ว!";
            $msg_type = "danger";
        } else {
            // เพิ่ม $pwd_update_sql เข้าไปในคำสั่ง UPDATE
            $sql = "UPDATE users SET users_name = '$new_id', full_name = '$full_name', academic_year = '$academic_year', user_level = '$user_level' $pwd_update_sql WHERE users_name = '$old_id'";
            if ($conn->query($sql)) {
                $msg = "💖 อัปเดตข้อมูลและเปลี่ยนรหัส ID เรียบร้อยแล้วจ้า";
                $msg_type = "success";
            } else {
                $msg = "😿 เกิดข้อผิดพลาดในการอัปเดตข้อมูล";
                $msg_type = "danger";
            }
        }
    } else {
        // เพิ่ม $pwd_update_sql เข้าไปในคำสั่ง UPDATE
        $sql = "UPDATE users SET full_name = '$full_name', academic_year = '$academic_year', user_level = '$user_level' $pwd_update_sql WHERE users_name = '$old_id'";
        if ($conn->query($sql)) {
            $msg = "💖 อัปเดตข้อมูล $old_id เรียบร้อยแล้วจ้า";
            $msg_type = "success";
        } else {
            $msg = "😿 เกิดข้อผิดพลาดในการอัปเดต";
            $msg_type = "danger";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password'])) {
    $reset_id = $conn->real_escape_string($_POST['reset_users_name']);
    $new_hashed_password = password_hash($reset_id, PASSWORD_DEFAULT);

    $sql = "UPDATE users SET password = '$new_hashed_password' WHERE users_name = '$reset_id'";
    if ($conn->query($sql)) {
        $msg = "🔑 รีเซ็ตรหัสผ่านของ <b>$reset_id</b> กลับเป็นค่าเริ่มต้น (ใช้ ID ตัวเองเป็นรหัสผ่าน) เรียบร้อยแล้ว!";
        $msg_type = "success";
    } else {
        $msg = "😿 เกิดข้อผิดพลาดในการรีเซ็ตรหัสผ่าน";
        $msg_type = "danger";
    }
}

// ==========================================
// Action: นำเข้าข้อมูลนักศึกษาด้วย CSV (โค้ดอาจารย์ 100% + กันบั๊ก Array to String)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file']) && isset($_POST['import_csv'])) {
    $file_mimes = array('text/x-comma-separated-values', 'text/comma-separated-values', 'application/octet-stream', 'application/vnd.ms-excel', 'application/x-csv', 'text/x-csv', 'text/csv', 'application/csv', 'application/excel', 'application/vnd.msexcel', 'text/plain');

    // ตรวจสอบว่าเป็นไฟล์ CSV จริงหรือไม่
    if (!empty($_FILES['csv_file']['name']) && in_array($_FILES['csv_file']['type'], $file_mimes)) {
        if (is_uploaded_file($_FILES['csv_file']['tmp_name'])) {

            ini_set('auto_detect_line_endings', TRUE); // แก้ปัญหาขึ้นบรรทัดใหม่จาก Mac
            $csv_file = fopen($_FILES['csv_file']['tmp_name'], 'r');

            // ข้าม BOM ถ้ามี (สำหรับไฟล์ UTF-8 ที่เซฟจาก Excel)
            fseek($csv_file, 0);
            $bom = fread($csv_file, 3);
            if ($bom != "\xEF\xBB\xBF") {
                rewind($csv_file);
            }

            $success_count = 0;
            $duplicate_count = 0;
            $error_count = 0;

            // อ่านทีละบรรทัด
            while (($row = fgetcsv($csv_file, 10000, ",")) !== FALSE) {
                // เช็คให้ชัวร์ว่าเป็น Array ที่มีข้อมูลจริงๆ และป้องกัน Array to string
                if (!is_array($row))
                    continue;

                $col_0 = (isset($row[0]) && !is_array($row[0])) ? (string) $row[0] : '';
                $col_1 = (isset($row[1]) && !is_array($row[1])) ? (string) $row[1] : '';

                // คาดหวังว่า Column 0 = รหัสนักศึกษา, Column 1 = ชื่อ-นามสกุล
                $student_id = trim($col_0);
                $full_name = trim($col_1);

                // เคลียร์อักขระพิเศษที่ติดมาจาก Excel ให้เหลือแต่ตัวเลข
                $student_id = preg_replace('/[^0-9]/', '', $student_id);

                // ตรวจสอบว่าเป็นรหัสนักศึกษาจริงๆ (ข้ามแถวหัวข้อ และเช็คความยาวรหัส)
                if (is_numeric($student_id) && strlen($student_id) >= 10 && !empty($full_name)) {

                    // 1. ดึงปีการศึกษาจากรหัส (เช่น 26610... -> ดึง 66 -> +2500 = 2566)
                    $year_code = substr($student_id, 1, 2);
                    $academic_year = 2500 + (int) $year_code;

                    // 2. ตั้งรหัสผ่านเป็นรหัสนักศึกษา (เข้ารหัส Hash เพื่อความปลอดภัย)
                    $password_hashed = password_hash($student_id, PASSWORD_DEFAULT);

                    // 3. ป้องกัน SQL Injection
                    $safe_student_id = $conn->real_escape_string($student_id);
                    $safe_full_name = $conn->real_escape_string($full_name);

                    // เช็คว่ามีรหัสนี้ในระบบแล้วหรือยัง (เช็ครายชื่อซ้ำ)
                    $check_exist = $conn->query("SELECT users_name FROM users WHERE users_name = '$safe_student_id'");

                    if ($check_exist && $check_exist->num_rows == 0) {
                        // ถ้ายังไม่มี ค่อยบันทึกลงฐานข้อมูล (เพิ่มเฉพาะชื่อใหม่)
                        $insert_sql = "INSERT INTO users (users_name, full_name, academic_year, password, user_level) 
                                       VALUES ('$safe_student_id', '$safe_full_name', '$academic_year', '$password_hashed', 's')";
                        if ($conn->query($insert_sql)) {
                            $success_count++;
                        } else {
                            $error_count++;
                        }
                    } else {
                        // ถ้ารหัสมีอยู่แล้ว ให้นับเป็นข้อมูลซ้ำและข้ามไป
                        $duplicate_count++;
                    }
                }
            }
            fclose($csv_file);

            if ($success_count > 0) {
                $msg = "✅ นำเข้ารายชื่อใหม่สำเร็จ <b>$success_count</b> รายการ " . ($duplicate_count > 0 ? "<br><small>(พบรหัสซ้ำและข้ามไป $duplicate_count รายการ)</small>" : "");
                $msg_type = "success";
            } elseif ($duplicate_count > 0) {
                $msg = "⚠️ ข้อมูลที่อัปโหลดมีในระบบอยู่แล้วทั้งหมด ($duplicate_count รายการ) ไม่มีรายชื่อใหม่ถูกเพิ่ม";
                $msg_type = "warning";
            } else {
                $msg = "❌ ไม่พบข้อมูลที่สามารถนำเข้าได้ กรุณาตรวจสอบรูปแบบไฟล์ CSV ให้ตรงกับไฟล์ตัวอย่าง";
                $msg_type = "danger";
            }
        } else {
            $msg = "❌ เกิดข้อผิดพลาดในการอัปโหลดไฟล์";
            $msg_type = "danger";
        }
    } else {
        $msg = "❌ กรุณาอัปโหลดไฟล์นามสกุล .csv เท่านั้น";
        $msg_type = "danger";
    }
    $active_tab = 'users';
}

// ==========================================
// ส่วนจัดการ Action: ข้อมูลบริษัท (COMPANIES)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_company'])) {
    $c_name = $conn->real_escape_string($_POST['company_name']);
    $c_address = $conn->real_escape_string($_POST['address']);
    $c_lat = $conn->real_escape_string($_POST['latitude']);
    $c_lng = $conn->real_escape_string($_POST['longitude']);
    $sql = "INSERT INTO companies (company_name, address, latitude, longitude) VALUES ('$c_name', '$c_address', '$c_lat', '$c_lng')";
    if ($conn->query($sql)) {
        $msg = "🏢 เพิ่มข้อมูลบริษัทเรียบร้อยแล้ว";
        $msg_type = "success";
    } else {
        $msg = "😿 เกิดข้อผิดพลาดในการเพิ่มบริษัท";
        $msg_type = "danger";
    }
    $active_tab = 'companies';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_company'])) {
    $c_id = $conn->real_escape_string($_POST['company_id']);
    $c_name = $conn->real_escape_string($_POST['company_name']);
    $c_address = $conn->real_escape_string($_POST['address']);
    $c_lat = $conn->real_escape_string($_POST['latitude']);
    $c_lng = $conn->real_escape_string($_POST['longitude']);
    $sql = "UPDATE companies SET company_name='$c_name', address='$c_address', latitude='$c_lat', longitude='$c_lng' WHERE company_id='$c_id'";
    if ($conn->query($sql)) {
        $msg = "🏢 อัปเดตข้อมูลบริษัทเรียบร้อยแล้ว";
        $msg_type = "success";
    } else {
        $msg = "😿 เกิดข้อผิดพลาดในการอัปเดต";
        $msg_type = "danger";
    }
    $active_tab = 'companies';
}

if (isset($_GET['action']) && $_GET['action'] == 'delete_company' && isset($_GET['id'])) {
    $del_id = $conn->real_escape_string($_GET['id']);
    $sql = "DELETE FROM companies WHERE company_id = '$del_id'";
    if ($conn->query($sql)) {
        $msg = "🗑️ ลบข้อมูลบริษัทเรียบร้อยแล้ว";
        $msg_type = "warning";
    }
    $active_tab = 'companies';
}

// ==========================================
// ระบบค้นหา & แบ่งหน้า (Search & Pagination)
// ==========================================
$search_text = isset($_GET['search_text']) ? $conn->real_escape_string($_GET['search_text']) : '';
$search_level = isset($_GET['search_level']) ? $conn->real_escape_string($_GET['search_level']) : '';
$search_year = isset($_GET['search_year']) ? $conn->real_escape_string($_GET['search_year']) : '';

$where_clauses = [];
if ($search_text != '') {
    $where_clauses[] = "(users_name LIKE '%$search_text%' OR full_name LIKE '%$search_text%')";
}
if ($search_level != '') {
    $where_clauses[] = "user_level = '$search_level'";
}
if ($search_year != '') {
    $where_clauses[] = "academic_year = '$search_year'";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

$limit = 10;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $limit;

$count_sql = "SELECT COUNT(users_name) as total FROM users $where_sql";
$count_result = $conn->query($count_sql);
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

$year_sql = "SELECT DISTINCT academic_year FROM users ORDER BY academic_year DESC";
$year_result = $conn->query($year_sql);

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard ✨</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Prompt:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

    <style>
        :root {
            --bg-color: #f0f7ff;
            --card-bg: #ffffff;
            --primary-soft: #a2d2ff;
            --text-dark: #4a4e69;
            --danger-soft: #ffc8dd;
        }

        body {
            font-family: 'Prompt', 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--bg-color) 0%, #e2eaff 100%);
            color: var(--text-dark);
            min-height: 100vh;
            overflow-x: hidden;
        }

        .navbar-custom {
            background-color: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border-bottom-left-radius: 20px;
            border-bottom-right-radius: 20px;
        }

        /* 🚨 สไตล์สำหรับแบนเนอร์ดาวน์โหลดแอป (ธีมแอดมิน) 🚨 */
        .download-banner {
            background: linear-gradient(135deg, #a2d2ff 0%, #cff4fc 100%);
            border-radius: 25px;
            padding: 25px 30px;
            color: #4a4e69;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 10px 25px rgba(162, 210, 255, 0.4);
            margin-bottom: 25px;
            transition: transform 0.3s ease;
        }
        .download-banner:hover {
            transform: translateY(-5px);
        }
        .download-icon {
            font-size: 3rem;
            color: #0d6efd;
            text-shadow: 2px 2px 10px rgba(255,255,255,0.6);
        }
        .btn-download-app {
            background: white;
            color: #0d6efd;
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
            background: #f8f9fa;
            color: #0a58ca;
            transform: scale(1.05);
        }
        @media (max-width: 576px) {
            .download-banner {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
        }

        .main-card {
            background: var(--card-bg);
            border-radius: 25px;
            box-shadow: 0 10px 30px rgba(162, 210, 255, 0.2);
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
            transition: 0.3s;
        }

        .nav-pills .nav-link.active {
            background-color: var(--primary-soft);
            color: #0d6efd;
            border-color: var(--primary-soft);
            box-shadow: 0 4px 10px rgba(162, 210, 255, 0.4);
        }

        .btn-rounded {
            border-radius: 50px;
            font-weight: 600;
            padding: 8px 20px;
            transition: 0.3s;
            border: none;
        }

        .btn-rounded:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .btn-pastel-danger {
            background-color: var(--danger-soft);
            color: #d63384;
        }

        .btn-pastel-primary {
            background-color: var(--primary-soft);
            color: #0d6efd;
        }

        .btn-pastel-edit {
            background-color: #ffec99;
            color: #856404;
        }

        .table-responsive {
            overflow-x: auto;
            padding-bottom: 5px;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background-color: var(--primary-soft);
            color: #fff;
            border: none;
            font-weight: 600;
            padding: 15px;
            white-space: nowrap;
        }

        .table thead th:first-child {
            border-top-left-radius: 15px;
        }

        .table thead th:last-child {
            border-top-right-radius: 15px;
        }

        .table td {
            vertical-align: middle;
            border-color: #f8fbff;
            padding: 12px 15px;
        }

        .table-hover tbody tr:hover {
            background-color: #f8fbff;
            border-left: 4px solid var(--primary-soft);
        }

        .badge-pastel {
            border-radius: 20px;
            padding: 6px 12px;
            font-weight: 500;
            letter-spacing: 0.5px;
        }

        .bg-pastel-success {
            background-color: #d4edda;
            color: #155724;
        }

        .bg-pastel-secondary {
            background-color: #e9ecef;
            color: #6c757d;
        }

        .bg-pastel-role-a {
            background-color: #f8d7da;
            color: #842029;
        }

        .bg-pastel-role-t {
            background-color: #fff3cd;
            color: #664d03;
        }

        .bg-pastel-role-s {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        .modal-content {
            border-radius: 25px;
            border: none;
        }

        .modal-header {
            background-color: var(--primary-soft);
            color: white;
            border-top-left-radius: 25px;
            border-top-right-radius: 25px;
        }

        .form-control,
        .form-select {
            border-radius: 15px;
            padding: 12px;
            border: 2px solid #f0f0f0;
        }

        .page-link {
            border: none;
            color: var(--text-dark);
            margin: 0 4px;
            border-radius: 50px !important;
            font-weight: 600;
            transition: 0.3s;
        }

        .page-item.active .page-link {
            background-color: var(--primary-soft);
            color: #0d6efd;
        }

        /* สไตล์สำหรับกล่องอัปโหลด CSV แบบอาจารย์ */
        .upload-area {
            border: 2px dashed #a2d2ff;
            border-radius: 20px;
            padding: 40px 20px;
            text-align: center;
            background-color: #f8fbff;
            transition: all 0.3s;
            cursor: pointer;
        }

        .upload-area:hover {
            background-color: #e2eaff;
            border-color: #0d6efd;
            transform: translateY(-2px);
        }

        .upload-icon {
            font-size: 3rem;
            color: #0d6efd;
            margin-bottom: 15px;
        }

        .btn-upload {
            background-color: #0d9488;
            color: white;
            border-radius: 50px;
            padding: 12px 35px;
            font-weight: 600;
            transition: 0.3s;
            border: none;
            font-size: 1.1rem;
        }

        .btn-upload:hover {
            background-color: #0f766e;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13, 148, 136, 0.3);
        }

        .instruction-box {
            background-color: #f8f9fa;
            border-left: 4px solid #ff9800;
            border-radius: 10px;
            padding: 25px;
        }

        .instruction-box ol li {
            margin-bottom: 8px;
            color: #5c4d3c;
        }

        .download-btn {
            background-color: #fff4e6;
            color: #ff9800;
            border: 1px solid #ffbfa0;
            border-radius: 50px;
            font-weight: 500;
            padding: 8px 20px;
            transition: 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .download-btn:hover {
            background-color: #ffe066;
            color: #856404;
            border-color: #ffe066;
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-custom sticky-top py-3">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#"><i class="fas fa-wand-magic-sparkles text-warning"></i> Admin
                Panel</a>
            <div class="d-flex align-items-center">
                <span class="me-3 text-muted" style="font-weight: 500;">👋 สวัสดี, คุณ
                    <?php echo htmlspecialchars($admin_fullname); ?></span>
                <a href="logout.php" class="btn btn-rounded btn-pastel-danger btn-sm"><i
                        class="fas fa-arrow-right-from-bracket"></i> ออกจากระบบ</a>
            </div>
        </div>
    </nav>

    <div class="container mt-5 pb-5">

        <?php if ($msg != ""): ?>
            <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show shadow-sm rounded-pill px-4"
                role="alert">
                <?php echo $msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"
                    style="margin-top: 2px;"></button>
            </div>
        <?php endif; ?>

        <?php if ($msg != ""): ?>
            <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show shadow-sm rounded-pill px-4" role="alert">
                <?php echo $msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="margin-top: 2px;"></button>
            </div>
        <?php endif; ?>

        <div class="download-banner">
            <div class="d-flex align-items-center gap-3">
                <div class="download-icon"><i class="fab fa-android"></i></div>
                <div class="text-start">
                    <h5 class="fw-bold mb-1">จัดการระบบได้ทุกที่! โหลดแอปสำหรับผู้ดูแลระบบเลย</h5>
                    <p class="mb-0 small" style="opacity: 0.9;">ปรับสถานะ เพิ่มข้อมูล หรือตรวจสอบภาพรวมได้สะดวกยิ่งขึ้นผ่านสมาร์ทโฟน</p>
                </div>
            </div>
            <a href="app-release.apk" class="btn-download-app" download>
                <i class="fas fa-download me-2"></i> โหลด APK
            </a>
        </div>

        <div class="main-card">

            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h3 class="fw-bold mb-1" style="color: var(--text-dark);">✨ จัดการระบบหลังบ้าน</h3>
                    <p class="text-muted mb-0">ควบคุมข้อมูลผู้ใช้งานและสถานประกอบการ</p>
                </div>
                <a href="admin_dashboard.php?action=clear_all_sessions"
                    class="btn btn-rounded btn-pastel-danger py-2 px-4"
                    onclick="return confirm('⚠️ คำเตือนน่ารักๆ: คุณแน่ใจนะว่าจะเตะทุกคนออกจากระบบ (ทั้ง Web และ App)? (ยกเว้นตัวคุณเอง)');">
                    <i class="fas fa-broom"></i> ล้าง Session ทั้งหมด
                </a>
            </div>

            <ul class="nav nav-pills mb-4" id="adminTabs">
                <li class="nav-item">
                    <a class="nav-link <?php echo $active_tab == 'users' ? 'active' : ''; ?>" href="?tab=users">
                        <i class="fas fa-users me-1"></i> ข้อมูลผู้ใช้งาน
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $active_tab == 'companies' ? 'active' : ''; ?>" href="?tab=companies">
                        <i class="fas fa-building me-1"></i> ข้อมูลบริษัท/สถานที่ฝึกงาน
                    </a>
                </li>
                <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                    <a class="nav-link bg-white border border-primary text-primary shadow-sm"
                        href="admin_internships.php">
                        <i class="fas fa-user-check me-1"></i> จัดการสถานะฝึกงาน <i class="fas fa-arrow-right ms-1"
                            style="font-size: 0.8em;"></i>
                    </a>
                </li>
                <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                    <a class="nav-link bg-white border border-success text-success shadow-sm"
                        href="admin_supervision.php">
                        <i class="fas fa-calendar-check me-1"></i> กำหนดการนิเทศ <i class="fas fa-arrow-right ms-1"
                            style="font-size: 0.8em;"></i>
                    </a>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade <?php echo $active_tab == 'users' ? 'show active' : ''; ?>" id="users">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <p class="text-muted mb-0"><i class="fas fa-info-circle text-primary"></i>
                            พบข้อมูลผู้ใช้งานทั้งหมด <strong class="text-primary"><?php echo $total_rows; ?></strong>
                            รายการ</p>
                        <div>
                            <button class="btn btn-pastel-primary btn-rounded shadow-sm px-4 me-2"
                                style="padding: 8px 25px; font-size: 1rem;" data-bs-toggle="modal"
                                data-bs-target="#addPersonnelModal">
                                <i class="fas fa-user-plus me-1"></i> เพิ่มบุคลากร
                            </button>
                            <button class="btn btn-upload btn-rounded shadow-sm px-4"
                                style="padding: 8px 25px; font-size: 1rem;" data-bs-toggle="modal"
                                data-bs-target="#importCsvModal">
                                <i class="fas fa-file-csv me-1"></i> นำเข้ารายชื่อนักศึกษา
                            </button>
                        </div>
                    </div>

                    <form method="GET" action="admin_dashboard.php"
                        class="row g-3 mb-4 bg-light p-3 rounded-4 shadow-sm align-items-end">
                        <input type="hidden" name="tab" value="users">
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-bold ms-2">ค้นหาชื่อ / รหัส 🔍</label>
                            <input type="text" name="search_text" class="form-control border-0 shadow-sm"
                                placeholder="พิมพ์ชื่อหรือรหัส..."
                                value="<?php echo htmlspecialchars($search_text); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-bold ms-2">ค้นหาตามบทบาท</label>
                            <select name="search_level" class="form-select border-0 shadow-sm">
                                <option value="">-- ทุกบทบาท --</option>
                                <option value="s" <?php if ($search_level == 's')
                                    echo 'selected'; ?>>🎓 นักศึกษา</option>
                                <option value="t" <?php if ($search_level == 't')
                                    echo 'selected'; ?>>👨‍🏫 อาจารย์
                                </option>
                                <option value="a" <?php if ($search_level == 'a')
                                    echo 'selected'; ?>>👑 ผู้ดูแลระบบ
                                </option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-bold ms-2">นักศึกษาปีการศึกษา</label>
                            <select name="search_year" class="form-select border-0 shadow-sm">
                                <option value="">-- ทุกปีการศึกษา --</option>
                                <?php
                                if ($year_result && $year_result->num_rows > 0) {
                                    while ($yr = $year_result->fetch_assoc()) {
                                        $selected = ($search_year == $yr['academic_year']) ? 'selected' : '';
                                        echo "<option value='" . $yr['academic_year'] . "' $selected>ปี " . $yr['academic_year'] . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-pastel-primary btn-rounded flex-grow-1 shadow-sm"><i
                                    class="fas fa-search"></i> ค้นหา</button>
                            <a href="admin_dashboard.php?tab=users"
                                class="btn btn-light btn-rounded border text-muted shadow-sm"><i
                                    class="fas fa-undo"></i> ล้างค่า</a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">ID ผู้ใช้</th>
                                    <th>ชื่อ-นามสกุล</th>
                                    <th class="text-center">นักศึกษาปีการศึกษา</th>
                                    <th class="text-center">บทบาท</th>
                                    <th class="text-center">สถานะ</th>
                                    <th class="text-center pe-4">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sql = "SELECT * FROM users $where_sql ORDER BY user_level, users_name LIMIT $limit OFFSET $offset";
                                $result = $conn->query($sql);

                                if ($result && $result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        $level_badge = "";
                                        if ($row['user_level'] == 'a')
                                            $level_badge = "<span class='badge badge-pastel bg-pastel-role-a'>👑 Admin</span>";
                                        elseif ($row['user_level'] == 't')
                                            $level_badge = "<span class='badge badge-pastel bg-pastel-role-t'>👨‍🏫 Teacher</span>";
                                        else
                                            $level_badge = "<span class='badge badge-pastel bg-pastel-role-s'>🎓 Student</span>";

                                        $is_online = !empty($row['session_token']) || !empty($row['app_session_token']);
                                        $status_badge = $is_online ? "<span class='badge badge-pastel bg-pastel-success'><i class='fas fa-circle fa-xs me-1'></i> ออนไลน์</span>" : "<span class='badge badge-pastel bg-pastel-secondary'>ออฟไลน์</span>";

                                        echo "<tr>";
                                        echo "<td class='ps-4 fw-bold text-muted text-nowrap'>" . htmlspecialchars($row['users_name']) . "</td>";
                                        echo "<td class='text-nowrap'>" . htmlspecialchars($row['full_name'] ?? '-') . "</td>";
                                        echo "<td class='text-center'><span class='badge bg-light text-dark rounded-pill border'>" . htmlspecialchars($row['academic_year']) . "</span></td>";
                                        echo "<td class='text-center'>" . $level_badge . "</td>";
                                        echo "<td class='text-center'>" . $status_badge . "</td>";
                                        echo "<td class='text-center pe-4 text-nowrap'>";

                                        // ปุ่มแก้ไข
                                        echo "<button class='btn btn-rounded btn-pastel-edit btn-sm me-1' onclick='openEditModal(\"" . $row['users_name'] . "\", \"" . htmlspecialchars($row['full_name'], ENT_QUOTES) . "\", \"" . $row['academic_year'] . "\", \"" . $row['user_level'] . "\")'><i class='fas fa-pencil-alt'></i> แก้ไข</button>";

                                        // ปุ่มเตะออก
                                        if ($row['users_name'] != $admin_username && $is_online) {
                                            echo "<a href='admin_dashboard.php?action=clear_session&id=" . $row['users_name'] . "&tab=users' class='btn btn-rounded btn-pastel-danger btn-sm me-1' onclick='return confirm(\"แน่ใจนะว่าจะเตะผู้ใช้นี้ออกจากระบบ?\");'><i class='fas fa-shoe-prints'></i> เตะออก</a>";
                                        } else {
                                            echo "<button class='btn btn-rounded btn-light text-muted btn-sm border me-1' style='opacity: 0.5; cursor: not-allowed;' disabled><i class='fas fa-ban'></i></button>";
                                        }

                                        // ปุ่มลบผู้ใช้งาน (เพิ่มใหม่ตามคำขอ)
                                        if ($row['users_name'] != $admin_username) {
                                            echo "<a href='admin_dashboard.php?action=delete_user&id=" . $row['users_name'] . "&tab=users' class='btn btn-rounded btn-danger shadow-sm btn-sm' onclick='return confirm(\"⚠️ คำเตือน: ยืนยันการลบผู้ใช้ [ " . $row['users_name'] . " ] ถาวร?\\n\\nหากลบแล้วข้อมูลที่เกี่ยวข้องจะหายไปทั้งหมด!\");'><i class='fas fa-trash-alt'></i> ลบ</a>";
                                        }

                                        echo "</td></tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='6' class='text-center text-muted py-5 fw-bold'>😿 ไม่พบข้อมูลผู้ใช้งานเลย</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages > 1):
                        $query_string = "&search_text=" . urlencode($search_text) . "&search_level=" . urlencode($search_level) . "&search_year=" . urlencode($search_year) . "&tab=users";
                        ?>
                        <nav aria-label="Page navigation" class="mt-4 pt-3 border-top">
                            <ul class="pagination justify-content-center mb-0">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link shadow-sm bg-white"
                                        href="?page=<?php echo $page - 1 . $query_string; ?>"><i
                                            class="fas fa-chevron-left"></i> ก่อนหน้า</a>
                                </li>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                        <a class="page-link shadow-sm bg-white"
                                            href="?page=<?php echo $i . $query_string; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link shadow-sm bg-white"
                                        href="?page=<?php echo $page + 1 . $query_string; ?>">ถัดไป <i
                                            class="fas fa-chevron-right"></i></a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>

                <div class="tab-pane fade <?php echo $active_tab == 'companies' ? 'show active' : ''; ?>"
                    id="companies">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <p class="text-muted mb-0"><i class="fas fa-info-circle text-primary"></i>
                            ข้อมูลบริษัทจะถูกแสดงให้นักศึกษาเลือกในขั้นตอนยื่นฝึกงาน</p>
                        <button class="btn btn-pastel-primary btn-rounded shadow-sm" data-bs-toggle="modal"
                            data-bs-target="#addCompanyModal">
                            <i class="fas fa-plus me-1"></i> เพิ่มบริษัทใหม่
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">ID</th>
                                    <th>ชื่อบริษัท / สถานประกอบการ</th>
                                    <th>ที่อยู่</th>
                                    <th class="text-center">พิกัด (Lat, Lng)</th>
                                    <th class="text-center pe-4">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $comp_sql = "SELECT * FROM companies ORDER BY company_id DESC";
                                $comp_res = $conn->query($comp_sql);

                                if ($comp_res && $comp_res->num_rows > 0) {
                                    while ($c = $comp_res->fetch_assoc()) {
                                        echo "<tr>";
                                        echo "<td class='ps-4 fw-bold text-muted'>" . $c['company_id'] . "</td>";
                                        echo "<td class='fw-bold text-dark'>" . htmlspecialchars($c['company_name']) . "</td>";
                                        echo "<td><small class='text-muted'>" . htmlspecialchars($c['address']) . "</small></td>";
                                        echo "<td class='text-center'><span class='badge bg-light text-dark border'>" . $c['latitude'] . " , " . $c['longitude'] . "</span></td>";
                                        echo "<td class='text-center pe-4 text-nowrap'>";
                                        echo "<button class='btn btn-rounded btn-pastel-edit btn-sm me-2' onclick='openEditCompanyModal(\"" . $c['company_id'] . "\", \"" . htmlspecialchars($c['company_name'], ENT_QUOTES) . "\", \"" . htmlspecialchars($c['address'], ENT_QUOTES) . "\", \"" . $c['latitude'] . "\", \"" . $c['longitude'] . "\")'><i class='fas fa-pencil-alt'></i> แก้ไข</button>";
                                        echo "<a href='admin_dashboard.php?action=delete_company&id=" . $c['company_id'] . "' class='btn btn-rounded btn-danger shadow-sm btn-sm' onclick='return confirm(\"⚠️ คำเตือน: คุณแน่ใจหรือไม่ที่จะลบบริษัทนี้?\\nหากลบ ข้อมูลนักศึกษาที่ฝึกงานที่นี่อาจได้รับผลกระทบ!\");'><i class='fas fa-trash-alt'></i> ลบ</a>";
                                        echo "</td></tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='5' class='text-center text-muted py-5 fw-bold'>🏢 ยังไม่มีข้อมูลบริษัทในระบบ</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div class="modal fade" id="importCsvModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content shadow-lg border-0" style="border-radius: 25px;">
                <div class="modal-header border-0 pb-0 mt-3 px-4">
                    <h4 class="modal-title fw-bold text-dark"><i class="fas fa-file-csv text-success me-2"></i>
                        นำเข้ารายชื่อนักศึกษา (CSV)</h4>
                    <button type="button" class="btn-close mt-1 me-1" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body px-4 pt-2 pb-4">

                    <div class="instruction-box mb-4 shadow-sm">
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-list-ol text-warning me-2"></i>
                                วิธีการใช้งานและคำแนะนำ</h6>
                            <a href="admin_dashboard.php?action=download_template"
                                class="download-btn shadow-sm text-decoration-none"
                                style="background-color: #e6f4ea; color: #1e8e3e; border-color: #cce8d6;">
                                <i class="fas fa-file-excel me-1"></i> โหลดไฟล์ตัวอย่าง Excel
                            </a>
                        </div>
                        <ol class="mb-0 small" style="line-height: 1.8;">
                            <li><b>ดาวน์โหลดไฟล์ตัวอย่าง</b> จากปุ่มด้านบน เปิดด้วยโปรแกรม Excel เพื่อดูโครงสร้าง</li>
                            <li><b>กรอกข้อมูลนักศึกษา:</b> โดยให้ <span class="text-danger fw-bold">คอลัมน์ A คือ
                                    "รหัสนักศึกษา"</span> และ <span class="text-primary fw-bold">คอลัมน์ B คือ
                                    "ชื่อ-นามสกุล"</span></li>
                            <li class="p-2 mt-2 mb-2 bg-white rounded border border-warning">
                                <b class="text-danger"><i class="fas fa-exclamation-triangle"></i> สำคัญมาก!
                                    ขั้นตอนการบันทึกไฟล์:</b><br>
                                เมื่อกรอกข้อมูลใน Excel เสร็จแล้ว ให้ไปที่ <b>File > Save As (บันทึกเป็น)</b> <br>
                                แล้วเปลี่ยนช่อง <i>Save as type</i> ให้เป็น <b
                                    class="text-danger text-decoration-underline">CSV UTF-8 (Comma delimited)
                                    (*.csv)</b> เท่านั้น
                            </li>
                            <li><b>อัปโหลดไฟล์:</b> นำไฟล์นามสกุล <b>.csv</b> ที่ได้จากข้อ 3
                                มาลากวางหรืออัปโหลดที่ช่องด้านล่าง</li>
                            <li><b>ระบบจะตรวจสอบและเพิ่มข้อมูล:</b> รหัสผ่านเริ่มต้นจะถูกตั้งเป็น "รหัสนักศึกษา"
                                ให้อัตโนมัติ (ข้ามรายชื่อที่ซ้ำ)</li>
                        </ol>
                    </div>

                    <form action="admin_dashboard.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="import_csv" value="1">

                        <div class="upload-area mb-4 shadow-sm" onclick="document.getElementById('csv_file').click()">
                            <i class="fas fa-cloud-upload-alt upload-icon"></i>
                            <h5 class="fw-bold text-dark mb-2">คลิกเพื่อเลือกไฟล์ หรือ ลากไฟล์ .csv มาวางที่นี่</h5>
                            <p class="text-muted mb-0 small">รองรับเฉพาะไฟล์ .csv ที่จัดรูปแบบตามคำแนะนำเท่านั้น</p>
                            <input type="file" name="csv_file" id="csv_file" class="d-none" accept=".csv" required
                                onchange="updateFileName(this)">
                        </div>

                        <div id="file-name-display"
                            class="text-center fw-bold text-success mb-4 d-none p-2 bg-light rounded-pill border">
                            <i class="fas fa-file-alt me-2"></i> ไฟล์ที่เลือก: <span id="file-name-text"></span>
                        </div>

                        <div class="text-center mt-4">
                            <button type="button" class="btn btn-rounded btn-light border px-4 me-2"
                                data-bs-dismiss="modal">ยกเลิก</button>
                            <button type="submit" class="btn btn-upload shadow"><i class="fas fa-save me-2"></i>
                                อัปโหลดและเพิ่มรายชื่อเข้าสู่ระบบ</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg">
                <div class="modal-header border-0 pb-0" style="background-color: var(--primary-soft);">
                    <h5 class="modal-title fw-bold text-white"><i class="fas fa-user-pen me-2"></i>แก้ไขข้อมูลสมาชิก
                    </h5>
                    <button type="button" class="btn-close btn-close-white me-2 mt-2" data-bs-dismiss="modal"
                        style="filter: brightness(0) invert(1);"></button>
                </div>
                <div class="modal-body pt-4 px-4">
                    <form action="admin_dashboard.php" method="POST" id="editUserForm">
                        <input type="hidden" name="edit_user" value="1">
                        <input type="hidden" name="old_users_name" id="modal_old_username">

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted ms-2">รหัสผู้ใช้ (ID)</label>
                            <input type="text" name="users_name" id="modal_username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted ms-2">ชื่อ-นามสกุล 📛</label>
                            <input type="text" name="full_name" id="modal_fullname" class="form-control" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small text-muted ms-2">นักศึกษาปีการศึกษา 📅</label>
                                <input type="text" name="academic_year" id="modal_year" class="form-control text-center"
                                    required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small text-muted ms-2">บทบาท 🎭</label>
                                <select name="user_level" id="modal_level" class="form-select" required>
                                    <option value="s">🎓 นักศึกษา</option>
                                    <option value="t">👨‍🏫 อาจารย์</option>
                                    <option value="a">👑 ผู้ดูแลระบบ</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted ms-2">กำหนดรหัสผ่านใหม่ 🔑 <span
                                    class="text-danger fw-normal"
                                    style="font-size: 0.85em;">(ปล่อยว่างไว้หากไม่ต้องการเปลี่ยน)</span></label>
                            <input type="password" name="new_password" id="modal_new_password" class="form-control"
                                placeholder="พิมพ์รหัสผ่านใหม่ที่ต้องการ...">
                        </div>
                    </form>
                    <hr class="my-4">
                    <form action="admin_dashboard.php" method="POST"
                        onsubmit="return confirm('แน่ใจหรือไม่ที่จะรีเซ็ตรหัสผ่าน? \n(รหัสผ่านจะถูกตั้งให้เป็นเลข ID ของผู้ใช้นี้)');">
                        <input type="hidden" name="reset_password" value="1">
                        <input type="hidden" name="reset_users_name" id="modal_reset_username">
                        <div class="d-flex justify-content-between align-items-center bg-light p-3 rounded-4 border">
                            <div>
                                <h6 class="fw-bold text-danger mb-1"><i class="fas fa-key"></i> รีเซ็ตรหัสผ่าน</h6>
                                <small class="text-muted">คืนค่ารหัสผ่านกลับเป็นเลข ID</small>
                            </div>
                            <button type="submit" class="btn btn-outline-danger btn-sm btn-rounded"><i
                                    class="fas fa-undo"></i> รีเซ็ตเลย</button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4 mt-3">
                    <button type="button" class="btn btn-rounded btn-light border px-4"
                        data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-rounded btn-pastel-primary px-4"
                        onclick="document.getElementById('editUserForm').submit();">💾 บันทึกการแก้ไข</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addCompanyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg">
                <form action="admin_dashboard.php" method="POST">
                    <input type="hidden" name="add_company" value="1">
                    <div class="modal-header border-0 pb-0" style="background-color: var(--primary-soft);">
                        <h5 class="modal-title fw-bold text-white"><i
                                class="fas fa-building me-2"></i>เพิ่มข้อมูลบริษัทใหม่</h5>
                        <button type="button" class="btn-close btn-close-white me-2 mt-2" data-bs-dismiss="modal"
                            style="filter: brightness(0) invert(1);"></button>
                    </div>
                    <div class="modal-body pt-4 px-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted ms-2">ชื่อบริษัท / สถานประกอบการ</label>
                            <input type="text" name="company_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted ms-2">ที่อยู่แบบเต็ม</label>
                            <textarea name="address" class="form-control" rows="2" required></textarea>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold small text-muted ms-2">ละติจูด (Latitude)</label>
                                <input type="text" name="latitude" id="add_lat" class="form-control"
                                    placeholder="เช่น 13.7563">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold small text-muted ms-2">ลองจิจูด (Longitude)</label>
                                <input type="text" name="longitude" id="add_lng" class="form-control"
                                    placeholder="เช่น 100.5018">
                            </div>
                        </div>

                        <div class="col-12 mb-3">
                            <button type="button" class="btn btn-sm btn-outline-primary w-100 rounded-pill mb-2"
                                onclick="showMap('add')">
                                <i class="fas fa-map-marked-alt"></i> แสดง/อัปเดตแผนที่จากพิกัด
                            </button>
                            <div id="map_add"
                                style="height: 250px; width: 100%; border-radius: 15px; display: none; border: 1px solid #ced4da;">
                            </div>
                            <small class="text-muted d-none mt-1 ms-2" id="map_add_help">* สามารถเลื่อนหมุดบนแผนที่
                                หรือคลิกเพื่อเปลี่ยนพิกัดได้</small>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0 pb-4 px-4">
                        <button type="button" class="btn btn-rounded btn-light border px-4"
                            data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-rounded btn-pastel-primary px-4">💾 บันทึกบริษัท</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editCompanyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg">
                <form action="admin_dashboard.php" method="POST">
                    <input type="hidden" name="edit_company" value="1">
                    <input type="hidden" name="company_id" id="modal_company_id">
                    <div class="modal-header border-0 pb-0" style="background-color: #ffec99;">
                        <h5 class="modal-title fw-bold text-dark"><i class="fas fa-edit me-2"></i>แก้ไขข้อมูลบริษัท</h5>
                        <button type="button" class="btn-close me-2 mt-2" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body pt-4 px-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted ms-2">ชื่อบริษัท / สถานประกอบการ</label>
                            <input type="text" name="company_name" id="modal_company_name" class="form-control"
                                required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted ms-2">ที่อยู่แบบเต็ม</label>
                            <textarea name="address" id="modal_company_address" class="form-control" rows="2"
                                required></textarea>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold small text-muted ms-2">ละติจูด (Latitude)</label>
                                <input type="text" name="latitude" id="modal_company_lat" class="form-control">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold small text-muted ms-2">ลองจิจูด (Longitude)</label>
                                <input type="text" name="longitude" id="modal_company_lng" class="form-control">
                            </div>
                        </div>

                        <div class="col-12 mb-3">
                            <button type="button" class="btn btn-sm btn-outline-primary w-100 rounded-pill mb-2"
                                onclick="showMap('edit')">
                                <i class="fas fa-map-marked-alt"></i> แสดง/อัปเดตแผนที่จากพิกัด
                            </button>
                            <div id="map_edit"
                                style="height: 250px; width: 100%; border-radius: 15px; display: none; border: 1px solid #ced4da;">
                            </div>
                            <small class="text-muted d-none mt-1 ms-2" id="map_edit_help">* สามารถเลื่อนหมุดบนแผนที่
                                หรือคลิกเพื่อเปลี่ยนพิกัดได้</small>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0 pb-4 px-4">
                        <button type="button" class="btn btn-rounded btn-light border px-4"
                            data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-rounded btn-pastel-edit px-4">💾 บันทึกการแก้ไข</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addPersonnelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg border-0" style="border-radius: 25px;">
                <form action="admin_dashboard.php" method="POST">
                    <input type="hidden" name="add_personnel" value="1">
                    <div class="modal-header border-0 pb-0 mt-2 px-4">
                        <h5 class="modal-title fw-bold" style="color: #0d6efd;"><i
                                class="fas fa-user-tie me-2"></i>เพิ่มบุคลากรใหม่</h5>
                        <button type="button" class="btn-close mt-1 me-1" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body pt-4 px-4 pb-2">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted ms-2">รหัสประจำตัว (ID) <span
                                    class="text-danger">*</span></label>
                            <input type="text" name="users_name" class="form-control" required
                                placeholder="เช่น T001, ADMIN01">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted ms-2">ชื่อ-นามสกุล <span
                                    class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" required
                                placeholder="เช่น ดร.สมชาย ใจดี">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small text-muted ms-2">บทบาท <span
                                        class="text-danger">*</span></label>
                                <select name="user_level" class="form-select" required>
                                    <option value="t" selected>👨‍🏫 อาจารย์</option>
                                    <option value="a">👑 ผู้ดูแลระบบ</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small text-muted ms-2">ปีการศึกษา
                                    <small>(ถ้ามี)</small></label>
                                <input type="text" name="academic_year" class="form-control text-center" value="-">
                            </div>
                        </div>
                        <div class="mb-3 p-3 bg-light rounded-4 border">
                            <label class="form-label fw-bold small text-dark ms-2 mb-1"><i
                                    class="fas fa-key text-warning"></i> ตั้งรหัสผ่านเริ่มต้น</label><br>
                            <small class="text-muted ms-2 d-block mb-2">หากปล่อยว่างไว้ ระบบจะใช้ <b>"รหัสประจำตัว
                                    (ID)"</b> เป็นรหัสผ่าน</small>
                            <input type="password" name="password" class="form-control"
                                placeholder="พิมพ์รหัสผ่าน (ไม่บังคับ)">
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0 pb-4 px-4 mt-2">
                        <button type="button" class="btn btn-rounded btn-light border px-4"
                            data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-rounded btn-pastel-primary px-4 shadow-sm"><i
                                class="fas fa-save me-1"></i> บันทึกข้อมูล</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <script>
        // Modal จัดการผู้ใช้งาน
        function openEditModal(username, fullname, year, level) {
            document.getElementById('modal_old_username').value = username;
            document.getElementById('modal_username').value = username;
            document.getElementById('modal_fullname').value = fullname;
            document.getElementById('modal_year').value = year;
            document.getElementById('modal_level').value = level;
            document.getElementById('modal_reset_username').value = username;
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }

        // Modal จัดการบริษัท
        function openEditCompanyModal(id, name, address, lat, lng) {
            document.getElementById('modal_company_id').value = id;
            document.getElementById('modal_company_name').value = name;
            document.getElementById('modal_company_address').value = address;
            document.getElementById('modal_company_lat').value = lat;
            document.getElementById('modal_company_lng').value = lng;

            // ซ่อนแผนที่ตอนเปิด Modal ครั้งแรกเพื่อให้ผู้ใช้กดปุ่มเรียกเมื่อต้องการเท่านั้น
            document.getElementById('map_edit').style.display = 'none';
            document.getElementById('map_edit_help').classList.add('d-none');

            new bootstrap.Modal(document.getElementById('editCompanyModal')).show();
        }

        // ระบบอัปโหลด CSV
        function updateFileName(input) {
            const display = document.getElementById('file-name-display');
            const text = document.getElementById('file-name-text');
            if (input.files && input.files[0]) {
                text.textContent = input.files[0].name;
                display.classList.remove('d-none');
            } else {
                display.classList.add('d-none');
            }
        }

        // ===============================================
        // ระบบแผนที่ Leaflet.js
        // ===============================================
        let maps = {};
        let markers = {};

        function showMap(mode) {
            let latId = mode === 'add' ? 'add_lat' : 'modal_company_lat';
            let lngId = mode === 'add' ? 'add_lng' : 'modal_company_lng';
            let mapId = mode === 'add' ? 'map_add' : 'map_edit';
            let helpId = mode === 'add' ? 'map_add_help' : 'map_edit_help';

            let latInput = document.getElementById(latId);
            let lngInput = document.getElementById(lngId);
            let mapDiv = document.getElementById(mapId);
            let helpText = document.getElementById(helpId);

            // ใช้ค่าที่กรอกมา หรือถ้าว่างให้เริ่มที่กรุงเทพฯ (อนุสาวรีย์ชัยสมรภูมิ)
            let lat = parseFloat(latInput.value) || 13.7563;
            let lng = parseFloat(lngInput.value) || 100.5018;

            // แสดงพื้นที่แผนที่
            mapDiv.style.display = 'block';
            helpText.classList.remove('d-none');

            // ถ้าแผนที่ยังไม่ถูกสร้างใน Modal นี้
            if (!maps[mode]) {
                maps[mode] = L.map(mapId).setView([lat, lng], 15);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(maps[mode]);

                // สร้างหมุดปัก
                markers[mode] = L.marker([lat, lng], { draggable: true }).addTo(maps[mode]);

                // อัปเดตพิกัดเข้าช่อง Input ทันทีเมื่อผู้ใช้ลากหมุดเสร็จ
                markers[mode].on('dragend', function (e) {
                    let position = markers[mode].getLatLng();
                    latInput.value = position.lat.toFixed(6);
                    lngInput.value = position.lng.toFixed(6);
                });

                // เมื่อคลิกพื้นที่ว่างบนแผนที่ ให้ย้ายหมุดมาตรงที่คลิกและอัปเดต Input
                maps[mode].on('click', function (e) {
                    markers[mode].setLatLng(e.latlng);
                    latInput.value = e.latlng.lat.toFixed(6);
                    lngInput.value = e.latlng.lng.toFixed(6);
                });
            } else {
                // หากเคยสร้างแผนที่แล้ว แค่อัปเดตตำแหน่งจุดศูนย์กลาง
                maps[mode].setView([lat, lng], 15);
                markers[mode].setLatLng([lat, lng]);
            }

            // ทริคสำคัญ: ป้องกันบั๊กแผนที่โหลดไม่เต็มกรอบเมื่อเปิดมาจาก Modal ที่เคยถูกซ่อน
            setTimeout(function () {
                maps[mode].invalidateSize();
            }, 200);
        }
    </script>
</body>

</html>