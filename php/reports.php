<?php
include '../includes/db.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CS Admin - Reports | JKUAT Staff Housing Portal</title>
    <link rel="stylesheet" href="../css/csdashboard.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <style>
        .main-content {
            margin-left: 250px;
            padding: 20px;
            background-color: #fff;
            min-height: 100vh;
        }

        .main-content h2 {
            color: #005826;
            margin-bottom: 20px;
        }

        #reportTitle {
            font-size: 22px;
            font-weight: bold;
            color: #005826;
            margin-bottom: 15px;
        }

        select, .export-btn, #searchInput {
            padding: 8px 12px;
            margin: 10px 5px 10px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
        }

        select {
            min-width: 250px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 14px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        table th {
            background-color: #005826;
            color: white;
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
            font-weight: 600;
        }

        table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }

        table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        table tr:hover {
            background-color: #f1f1f1;
        }

        .sidebar ul li a.active {
            background-color: #ffffff;
            color: #005826;
            font-weight: bold;
        }

        .export-section {
            margin: 15px 0;
        }

        .export-btn {
            background-color: #005826;
            color: white;
            border: none;
            cursor: pointer;
            padding: 8px 15px;
            border-radius: 4px;
            margin-right: 10px;
            font-size: 14px;
            transition: background-color 0.3s;
        }

        .export-btn:hover {
            background-color: #003d1a;
        }

        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background: #f1f1f1;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }

        .top-header h1 {
            margin: 0;
            color: #006400;
            font-size: 24px;
        }

        .user-icon {
            width: 40px;
            height: 40px;
            cursor: pointer;
        }

        /* Special formatting for update logs */
        .update-details {
            white-space: pre-wrap;
            max-width: 400px;
        }

        .timestamp {
            color: #666;
            font-size: 0.9em;
            white-space: nowrap;
        }

        .currency {
            text-align: right;
            font-family: monospace;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <img src="../images/2logo.png" alt="Logo">
    <h2>CS ADMIN</h2>
    <p>REPORTS</p>
    <nav>
        <ul>
            <li><a href="csdashboard.php">Dashboard</a></li>
            <li><a href="houses.php">Houses</a></li>
            <li><a href="tenants.php">Tenants</a></li>
            <li><a href="service_requests.php">Service Requests</a></li>
            <li><a href="manage_applicants.php">Manage Applicants</a></li>
            <li><a href="notices.php">Notices</a></li>
            <li><a href="bills.php">Bills</a></li>
            <li><a href="reports.php">Reports</a></li>
        </ul>
    </nav>
</div>

<div class="main-content">
    <header class="top-header">
        <h1>JKUAT STAFF HOUSING PORTAL</h1>
        <img src="../images/p-icon.png" alt="User Icon" class="user-icon" onclick="toggleMenu()">
    </header>

    <h2>Reports</h2>
    <h3 id="reportTitle"></h3>

    <form method="POST">
        <label for="report_type">Select Report:</label>
        <select name="report_type" id="report_type" required onchange="this.form.submit()">
            <option value="">-- Choose Report Type --</option>
            <option value="houses" <?= isset($_POST['report_type']) && $_POST['report_type'] === 'houses' ? 'selected' : '' ?>>Houses</option>
            <option value="tenants" <?= isset($_POST['report_type']) && $_POST['report_type'] === 'tenants' ? 'selected' : '' ?>>Tenants</option>
            <option value="applicants" <?= isset($_POST['report_type']) && $_POST['report_type'] === 'applicants' ? 'selected' : '' ?>>Applicants</option>
            <option value="applications" <?= isset($_POST['report_type']) && $_POST['report_type'] === 'applications' ? 'selected' : '' ?>>Applications</option>
            <option value="balloting" <?= isset($_POST['report_type']) && $_POST['report_type'] === 'balloting' ? 'selected' : '' ?>>Ballots</option>
            <option value="service_requests" <?= isset($_POST['report_type']) && $_POST['report_type'] === 'service_requests' ? 'selected' : '' ?>>Service Requests</option>
            <option value="notices" <?= isset($_POST['report_type']) && $_POST['report_type'] === 'notices' ? 'selected' : '' ?>>Notices</option>
            <option value="bills" <?= isset($_POST['report_type']) && $_POST['report_type'] === 'bills' ? 'selected' : '' ?>>Bills</option>
            <option value="bill_update_logs" <?= isset($_POST['report_type']) && $_POST['report_type'] === 'bill_update_logs' ? 'selected' : '' ?>>Bill Update Logs</option>
            <option value="house_update_logs" <?= isset($_POST['report_type']) && $_POST['report_type'] === 'house_update_logs' ? 'selected' : '' ?>>House Update Logs</option>
        </select>
    </form>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_type'])) {
        $type = $_POST['report_type'];
        
        // Modify query based on report type
        $query = "SELECT * FROM $type";
        if ($type === 'house_update_logs' || $type === 'bill_update_logs') {
            $query .= " ORDER BY date_updated DESC";
        }
        
        $result = mysqli_query($conn, $query);

        if ($result && mysqli_num_rows($result) > 0) {
            $reportTitle = ucwords(str_replace('_', ' ', $type)) . " Report";
            echo "<script>document.getElementById('reportTitle').textContent = '$reportTitle';</script>";

            echo '<input type="text" id="searchInput" placeholder="Search report..." class="search-input">';
            echo '<div class="export-section">
                    <button class="export-btn" onclick="exportTable(\'pdf\')">Export PDF</button>
                    <button class="export-btn" onclick="exportTable(\'excel\')">Export Excel</button>
                    <button class="export-btn" onclick="exportTable(\'word\')">Export Word</button>
                </div>';
            
            echo "<table id='reportTable'><thead><tr>";

            // Get column names
            $columns = [];
            while ($field = mysqli_fetch_field($result)) {
                $columns[] = $field->name;
                echo "<th>" . ucwords(str_replace('_', ' ', $field->name)) . "</th>";
            }
            echo "</tr></thead><tbody>";

            // Display data rows with special formatting
            while ($row = mysqli_fetch_assoc($result)) {
                echo "<tr>";
                foreach ($columns as $col) {
                    $cellClass = '';
                    $cellValue = htmlspecialchars($row[$col]);
                    
                    // Apply special formatting
                    if (in_array($col, ['date_updated', 'date_created', 'date_settled', 'date_billed'])) {
                        $cellClass = 'timestamp';
                        $cellValue = date('M j, Y H:i', strtotime($row[$col]));
                    } elseif ($col === 'details') {
                        $cellClass = 'update-details';
                    } elseif (in_array($col, ['rent', 'amount', 'new_amount', 'old_amount'])) {
                        $cellClass = 'currency';
                        $cellValue = 'KES ' . number_format($row[$col], 2);
                    }
                    
                    echo "<td class='$cellClass'>$cellValue</td>";
                }
                echo "</tr>";
            }
            echo "</tbody></table>";
        } else {
            echo "<p>No records found for this report.</p>";
        }
    }
    ?>
</div>

<script>
// Enhanced export function with title included
function exportTable(type) {
    const table = document.getElementById("reportTable");
    const title = document.getElementById("reportTitle").textContent || "JKUAT Housing Report";
    const headerTitle = "JKUAT Staff Housing Portal - " + title;
    const exportDate = new Date().toLocaleDateString();

    if (!table) {
        alert("No report loaded!");
        return;
    }

    if (type === 'excel') {
        // Create a new workbook
        let wb = XLSX.utils.book_new();
        
        // Clone the table to add title
        const tableClone = table.cloneNode(true);
        const titleRow = tableClone.insertRow(0);
        const titleCell = titleRow.insertCell(0);
        titleCell.colSpan = table.rows[0].cells.length;
        titleCell.innerHTML = `<b>${headerTitle}</b><br><small>Generated on: ${exportDate}</small>`;
        titleCell.style.fontWeight = 'bold';
        titleCell.style.textAlign = 'center';
        
        // Convert to worksheet
        let ws = XLSX.utils.table_to_sheet(tableClone);
        XLSX.utils.book_append_sheet(wb, ws, "Report");
        
        // Export the file
        XLSX.writeFile(wb, headerTitle.replace(/ /g, "_") + ".xlsx");
        
    } else if (type === 'pdf') {
        const { jsPDF } = window.jspdf;
        let doc = new jsPDF('l', 'pt');
        
        // Add title and date
        doc.setFontSize(16);
        doc.text(headerTitle, 40, 30);
        doc.setFontSize(10);
        doc.text(`Generated on: ${exportDate}`, 40, 50);
        
        // Add table
        doc.autoTable({
            html: '#reportTable',
            startY: 70,
            styles: { 
                fontSize: 8,
                cellPadding: 4,
                overflow: 'linebreak'
            },
            headStyles: {
                fillColor: [0, 88, 38],
                textColor: 255,
                fontStyle: 'bold'
            },
            columnStyles: {
                details: {cellWidth: 'auto'},
                timestamp: {cellWidth: 'wrap'}
            }
        });
        
        doc.save(headerTitle.replace(/ /g, "_") + ".pdf");
        
    } else if (type === 'word') {
        // Clone table to add title
        const tableClone = table.cloneNode(true);
        const titleRow = tableClone.insertRow(0);
        const titleCell = titleRow.insertCell(0);
        titleCell.colSpan = table.rows[0].cells.length;
        titleCell.innerHTML = `<h2>${headerTitle}</h2><p>Generated on: ${exportDate}</p>`;
        
        // Create HTML content
        let html = `
            <html xmlns:o="urn:schemas-microsoft-com:office:office" 
                  xmlns:w="urn:schemas-microsoft-com:office:word" 
                  xmlns="http://www.w3.org/TR/REC-html40">
            <head>
                <title>${headerTitle}</title>
                <style>
                    table { border-collapse: collapse; width: 100%; }
                    th { background-color: #005826; color: white; padding: 8px; }
                    td { padding: 6px; border: 1px solid #ddd; }
                    .timestamp { color: #666; font-size: 0.9em; }
                </style>
            </head>
            <body>
                ${tableClone.outerHTML}
            </body>
            </html>
        `;
        
        // Create and download Word file
        let blob = new Blob(['\ufeff', html], {
            type: 'application/msword'
        });
        let url = URL.createObjectURL(blob);
        let link = document.createElement("a");
        link.href = url;
        link.download = headerTitle.replace(/ /g, "_") + ".doc";
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

function toggleMenu() {
    alert("Profile menu coming soon.");
}

// Enhanced search functionality
document.addEventListener('input', function(e) {
    if (e.target.id === 'searchInput') {
        const value = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('#reportTable tbody tr');
        rows.forEach(row => {
            const match = Array.from(row.cells).some(cell =>
                cell.textContent.toLowerCase().includes(value)
            );
            row.style.display = match ? '' : 'none';
        });
    }
});
</script>

</body>
</html>