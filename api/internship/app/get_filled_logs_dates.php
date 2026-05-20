<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

$internship_id = $_GET['internship_id'] ?? '';
$response = array('success' => false, 'filled_dates' => array());

if ($internship_id) {
    // ดึงทั้งวันที่ (log_date) และสถานะวันหยุด (is_holiday)
    $sql = "SELECT log_date, is_holiday FROM daily_logs WHERE internship_id = '$internship_id'";
    $result = $conn->query($sql);
    
    if ($result) {
        $dates = array();
        while($row = $result->fetch_assoc()) {
            // ส่งกลับไปเป็นชุดข้อมูล เพื่อให้ Flutter เอาไปเช็คสีปฏิทินได้
            $dates[] = array(
                'log_date' => $row['log_date'],
                'is_holiday' => $row['is_holiday']
            );
        }
        $response['success'] = true;
        $response['filled_dates'] = $dates;
    } else {
        $response['message'] = "เกิดข้อผิดพลาด: " . $conn->error;
    }
}

echo json_encode($response);
$conn->close();
?>