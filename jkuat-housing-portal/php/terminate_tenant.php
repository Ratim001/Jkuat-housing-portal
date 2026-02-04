<?php
include '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tenant_id'])) {
    $tenant_id = $_POST['tenant_id'];
    
    // Update both move_out_date and status
    $stmt = $conn->prepare("UPDATE tenants SET move_out_date = NOW(), status = 'Terminated' WHERE tenant_id = ?");
    $stmt->bind_param("s", $tenant_id);
    
    if ($stmt->execute()) {
        header("Location: tenants.php");
        exit();
    } else {
        echo "Error terminating tenant: " . $conn->error;
    }
    
    $stmt->close();
}

$conn->close();
?>