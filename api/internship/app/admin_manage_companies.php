<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

// รับค่า action เพื่อบอกว่าแอดมินต้องการทำอะไร (read, add, update, delete)
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$response = array();

if ($action == 'read') {
    // ดึงข้อมูลทั้งหมดเรียงจากใหม่ไปเก่า
    $sql = "SELECT * FROM companies ORDER BY company_id DESC";
    $result = $conn->query($sql);
    $data = array();
    if ($result) {
        while($row = $result->fetch_assoc()) { $data[] = $row; }
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => "Error: " . $conn->error]);
    }

} elseif ($action == 'add') {
    $name = $conn->real_escape_string($_POST['company_name']);
    $address = $conn->real_escape_string($_POST['address']);
    $lat = $conn->real_escape_string($_POST['latitude']);
    $lng = $conn->real_escape_string($_POST['longitude']);

    $sql = "INSERT INTO companies (company_name, address, latitude, longitude) VALUES ('$name', '$address', '$lat', '$lng')";
    if ($conn->query($sql)) {
        echo json_encode(['success' => true, 'message' => 'เพิ่มสถานประกอบการเรียบร้อย']);
    } else {
        echo json_encode(['success' => false, 'message' => "Error: " . $conn->error]);
    }

} elseif ($action == 'update') {
    $id = $conn->real_escape_string($_POST['company_id']);
    $name = $conn->real_escape_string($_POST['company_name']);
    $address = $conn->real_escape_string($_POST['address']);
    $lat = $conn->real_escape_string($_POST['latitude']);
    $lng = $conn->real_escape_string($_POST['longitude']);

    $sql = "UPDATE companies SET company_name='$name', address='$address', latitude='$lat', longitude='$lng' WHERE company_id='$id'";
    if ($conn->query($sql)) {
        echo json_encode(['success' => true, 'message' => 'แก้ไขข้อมูลสำเร็จ']);
    } else {
        echo json_encode(['success' => false, 'message' => "Error: " . $conn->error]);
    }

} elseif ($action == 'delete') {
    $id = $conn->real_escape_string($_POST['company_id']);
    // ตรวจสอบก่อนลบว่ามีนักศึกษาใช้สถานที่นี้อยู่ไหม (ถ้ามีการผูก Foreign Key ไว้)
    $sql = "DELETE FROM companies WHERE company_id='$id'";
    if ($conn->query($sql)) {
        echo json_encode(['success' => true, 'message' => 'ลบสถานประกอบการสำเร็จ']);
    } else {
        // ดักจับ Error กรณีลบไม่ได้เพราะมีเด็กฝึกงานอยู่ที่นี่
        if ($conn->errno == 1451) {
             echo json_encode(['success' => false, 'message' => 'ไม่สามารถลบได้ เนื่องจากมีนักศึกษากำลังฝึกงานที่นี่']);
        } else {
             echo json_encode(['success' => false, 'message' => "Error: " . $conn->error]);
        }
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
?>