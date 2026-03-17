<?php
include '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_id = $_POST['service_id'];
    $type_of_bill = $_POST['type_of_bill'];
    $amount = $_POST['amount'];
    $date_billed = $_POST['date_billed'];
    $status = $_POST['status'];


    // Generate new bill_id in the format B001, B002...
    $result = mysqli_query($conn, "SELECT bill_id FROM bills ORDER BY bill_id DESC LIMIT 1");

    if ($row = mysqli_fetch_assoc($result)) {
        $last_id = $row['bill_id'];
        $num = (int)substr($last_id, 1); // remove "B"
        $num++;
        $new_id = 'B' . str_pad($num, 3, '0', STR_PAD_LEFT);
    } else {
        $new_id = 'B001';
    }

    // Insert into the `bills` table with date_settled explicitly set to NULL
    $stmt = $conn->prepare("INSERT INTO bills (bill_id, service_id, type_of_bill, amount, date_billed, date_settled, status) VALUES (?, ?, ?, ?, ?, NULL, ?)");
    // types: s = string, d = double (amount), s = string (date_billed), s = string (status)
    $stmt->bind_param("sssdss", $new_id, $service_id, $type_of_bill, $amount, $date_billed, $status);

    if ($stmt->execute()) {
        // Notify tenant/applicant about manual bill insertion if linked to a service
        require_once __DIR__ . '/../includes/helpers.php';
        $infoQ = $conn->prepare("SELECT s.tenant_id, t.applicant_id, a.email, a.name FROM service_requests s LEFT JOIN tenants t ON s.tenant_id = t.tenant_id LEFT JOIN applicants a ON t.applicant_id = a.applicant_id WHERE s.service_id = ? LIMIT 1");
        if ($infoQ) {
            $infoQ->bind_param('s', $service_id);
            $infoQ->execute();
            $info = $infoQ->get_result()->fetch_assoc();
            $tenantId = $info['tenant_id'] ?? null;
            $applicantId = $info['applicant_id'] ?? null;
            $email = $info['email'] ?? null;
            $name = $info['name'] ?? 'Tenant';
            $msg = "A bill ({$new_id}) has been created for service {$service_id}. Amount: {$amount}.";
            if ($tenantId && function_exists('notify_insert_safe')) {
                notify_insert_safe($conn, uniqid('NT'), 'admin', 'tenant', $tenantId, $msg, date('Y-m-d H:i:s'), 'unread', 'Bill Issued');
            }
            if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $subject = 'New bill issued — JKUAT Housing';
                $bodyHtml = '<p>' . htmlspecialchars($msg) . '</p>';
                if (function_exists('notify_and_email')) notify_and_email($conn, 'applicant', $applicantId ?? $tenantId, $email, $subject, $bodyHtml, 'Bill Issued');
            }
        }

        echo "Bill successfully inserted with ID: " . $new_id;
    } else {
        echo "Error inserting bill: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
} else {
    echo "Invalid request method.";
}
?>
