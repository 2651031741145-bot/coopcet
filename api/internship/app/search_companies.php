<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
include 'db_connect.php';

$query = $_GET['q'] ?? '';
$searchTerm = "%" . $query . "%";

// ค้นหาจากชื่อ หรือ ที่อยู่
$sql = "SELECT * FROM companies WHERE company_name LIKE ? OR address LIKE ? ORDER BY company_name ASC LIMIT 50";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $searchTerm, $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

$companies = array();
while($row = $result->fetch_assoc()) {
    $companies[] = $row;
}
echo json_encode($companies);
?>