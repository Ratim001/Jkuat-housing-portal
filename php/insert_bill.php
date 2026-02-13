<?php
include '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_id = $_POST['service_id'];
    $type_of_bill = $_POST['type_of_bill'];
    $amount = $_POST['amount'];
    $date_billed = $_POST['date_billed'];
    $status = $_POST['status'];

    // Set date_settled to NULL initially (not settled yet)
    $date_settled = null;

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

    // Insert into the `bills` table with date_settled as NULL
    $stmt = $conn->prepare("INSERT INTO bills (bill_id, service_id, type_of_bill, amount, date_billed, date_settled, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $new_id, $service_id, $type_of_bill, $amount, $date_billed, $date_settled, $status);

    // Manually set date_settled to NULL in query
    $stmt->send_long_data(5, null);

    if ($stmt->execute()) {
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
