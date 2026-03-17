<?php
session_start();
include '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['applicant_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    http_response_code(401);
    exit;
}

$category = $_GET['category'] ?? '';

if (empty($category)) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT house_id, house_no, category FROM houses WHERE category = ? AND status = 'Vacant' LIMIT 50");
    $stmt->bind_param('s', $category);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $houses = [];
    while ($row = $result->fetch_assoc()) {
        $houses[] = $row;
    }
    
    echo json_encode($houses);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
