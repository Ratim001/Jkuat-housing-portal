<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');
// prevent caching so clients always fetch fresh ballot state
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$resp = ['success' => false, 'is_open' => false, 'end_date' => null];
try {
    $res = mysqli_query($conn, "SELECT is_open, end_date FROM ballot_control WHERE id = 1 LIMIT 1");
    if ($res && $row = mysqli_fetch_assoc($res)) {
        $resp['success'] = true;
        $resp['is_open'] = (bool)$row['is_open'];
        $resp['end_date'] = $row['end_date'];
    }
} catch (Exception $e) {
    $resp['error'] = $e->getMessage();
}

echo json_encode($resp);
