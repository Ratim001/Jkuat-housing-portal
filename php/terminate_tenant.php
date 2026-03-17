<?php
include '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tenant_id'])) {
    $tenant_id = $_POST['tenant_id'];
    
    // Update both move_out_date and status
    $stmt = $conn->prepare("UPDATE tenants SET move_out_date = NOW(), status = 'Terminated' WHERE tenant_id = ?");
    $stmt->bind_param("s", $tenant_id);
    
    if ($stmt->execute()) {
        // Also update the corresponding applicant record to reflect role change
        $appRes = $conn->prepare("SELECT applicant_id FROM tenants WHERE tenant_id = ? LIMIT 1");
        if ($appRes) {
            $appRes->bind_param('s', $tenant_id);
            $appRes->execute();
            $r = $appRes->get_result()->fetch_assoc();
            if ($r && !empty($r['applicant_id'])) {
                $aid = $r['applicant_id'];
                $conn->query("UPDATE applicants SET role = 'applicant' WHERE applicant_id = '" . $conn->real_escape_string($aid) . "'");
            }
        }
        header("Location: tenants.php");
        exit();
    } else {
        echo "Error terminating tenant: " . $conn->error;
    }
    
    $stmt->close();
}

$conn->close();
?>