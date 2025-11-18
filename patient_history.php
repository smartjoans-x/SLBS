<?php
session_start();
require_once 'functions.php';
require_permission('access_billing'); 
require_once 'db_connect.php'; 

$search_query = $_GET['search'] ?? '';
$current_date = date('Y-m-d');
$from_date = $_GET['from_date'] ?? $current_date; // Default to current date
$to_date = $_GET['to_date'] ?? $current_date;     // Default to current date

// --- Fetch Patient List with Latest Bill Info (Filtered by dates) ---
$patients_list = [];
$sql_patients = "
    SELECT 
        p.pt_id, p.pt_name, p.age, p.sex,
        b.bill_id, b.net_amount, b.bill_date, b.status
    FROM patients p
    JOIN (
        SELECT 
            pt_id, bill_id, net_amount, bill_date, status,
            ROW_NUMBER() OVER(PARTITION BY pt_id ORDER BY bill_date DESC) as rn
        FROM billing
        WHERE DATE(bill_date) BETWEEN ? AND ?  /* APPLY DATE FILTER */
    ) b ON p.pt_id = b.pt_id AND b.rn = 1
    WHERE p.pt_name LIKE ? OR p.mobile_no LIKE ?
    ORDER BY b.bill_date DESC
";

$search_param = "%" . $search_query . "%";
$stmt = $conn->prepare($sql_patients);
$stmt->bind_param("ssss", $from_date, $to_date, $search_param, $search_param);
$stmt->execute();
$patients_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Patient History</title>
    <style>
        .container { padding: 20px; font-family: Arial, sans-serif; }
        .search-controls { margin-bottom: 20px; border: 1px solid #ddd; padding: 15px; border-radius: 5px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .data-table th, .data-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        .data-table th { background-color: #f0f0f0; }
        
        .patient-link { color: #007bff; cursor: pointer; text-decoration: underline; font-weight: bold; }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1001; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 700px; border-radius: 8px; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; }
        .close:hover, .close:focus { color: black; text-decoration: none; cursor: pointer; }
        
        .bill-record { border: 1px solid #ccc; margin-bottom: 15px; padding: 10px; border-radius: 4px; }
        .test-status-table { width: 100%; margin-top: 10px; font-size: 0.9em; border-collapse: collapse; }
        .test-status-table th, .test-status-table td { border: 1px solid #eee; padding: 5px; }
        .status-ready { color: green; font-weight: bold; }
        .status-pending { color: orange; }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h1>Patient History & Billing Lookup</h1>

        <div class="search-controls">
            <form method="get" action="">
                <div style="display: flex; gap: 10px; align-items: flex-end;">
                    <div style="flex: 1;"><label>From Date:</label><input type="date" name="from_date" value="<?php echo htmlspecialchars($from_date); ?>" required></div>
                    <div style="flex: 1;"><label>To Date:</label><input type="date" name="to_date" value="<?php echo htmlspecialchars($to_date); ?>" required></div>
                    <div style="flex: 2;"><label>Search Name/Mobile:</label><input type="text" name="search" placeholder="Search by Name or Mobile..." value="<?php echo htmlspecialchars($search_query); ?>"></div>
                    <button type="submit" class="btn-action" style="padding: 8px 15px;">Filter & Search</button>
                </div>
            </form>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Latest Bill ID</th>
                    <th>Patient Name</th> 
                    <th>Age/Sex</th>
                    <th>Latest Bill Amount (₹)</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($patients_list)): ?>
                    <tr><td colspan="5" style="text-align: center;">No patients found matching the criteria.</td></tr>
                <?php endif; ?>
                <?php foreach ($patients_list as $patient): ?>
                    <tr>
                        <td><?php echo $patient['bill_id']; ?></td>
                        <td>
                            <span class="patient-link" onclick="fetchPatientHistory(<?php echo $patient['pt_id']; ?>, '<?php echo htmlspecialchars($patient['pt_name']); ?>', '<?php echo htmlspecialchars($from_date); ?>', '<?php echo htmlspecialchars($to_date); ?>')">
                                <?php echo htmlspecialchars($patient['pt_name']); ?>
                            </span>
                        </td>
                        <td><?php echo "{$patient['age']} / {$patient['sex']}"; ?></td>
                        <td><?php echo number_format($patient['net_amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($patient['status']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div id="historyModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('historyModal').style.display='none'">&times;</span>
            <h2 id="modal-patient-name"></h2>
            <div id="history-content">
                Loading history...
            </div>
        </div>
    </div>

    <script>
        const historyModal = document.getElementById('historyModal');

        // Note: Added fromDate and toDate to the function signature
        function fetchPatientHistory(patientId, patientName, fromDate, toDate) {
            $('#modal-patient-name').text('Billing & Report History for ' + patientName);
            $('#history-content').html('Loading...');
            historyModal.style.display = 'block';

            $.ajax({
                url: 'fetch_bill_history.php', 
                method: 'GET',
                data: { pt_id: patientId, from_date: fromDate, to_date: toDate }, // Passing dates to the endpoint
                dataType: 'json',
                success: function(data) {
                    displayHistory(data);
                },
                error: function(xhr, status, error) {
                    $('#history-content').html('<p style="color:red;">Error fetching detailed history. Check console for details.</p>');
                    console.error("AJAX Error Status:", status);
                    console.error("AJAX Error Response:", xhr.responseText);
                    console.error("AJAX Error Message:", error);
                }
            });
        }

        function displayHistory(data) {
            let html = '<h3>History (' + data.length + ' Bills)</h3>';
            
            if (data.length === 0) {
                html += '<p>No bills found for this patient in the selected date range.</p>';
                $('#history-content').html(html);
                return;
            }

            data.forEach(bill => {
                let billHtml = `<div class="bill-record">
                    <h4>Bill #${bill.bill_id} (Net: ₹${bill.net_amount.toFixed(2)}) - Status: ${bill.status} (Date: ${new Date(bill.bill_date).toLocaleDateString()})</h4>
                    <table class="test-status-table">
                        <thead>
                            <tr>
                                <th>Test</th>
                                <th>Result Value</th>
                                <th>Report Status</th>
                            </tr>
                        </thead>
                        <tbody>`;
                
                bill.tests.forEach(test => {
                    const isReady = test.report_ready;
                    const statusClass = isReady ? 'status-ready' : 'status-pending';
                    const statusText = isReady ? 'READY' : 'PENDING';
                    
                    // Use 'display_name' retrieved from the AJAX endpoint
                    const testName = test.display_name || test.test_name; 

                    billHtml += `<tr>
                        <td>${testName}</td>
                        <td>${test.result_value || '---'}</td>
                        <td class="${statusClass}">${statusText}</td>
                    </tr>`;
                });

                billHtml += `</tbody></table></div>`;
                html += billHtml;
            });

            $('#history-content').html(html);
        }

        // Close modal setup
        document.querySelector('#historyModal .close').onclick = function() {
            historyModal.style.display = 'none';
        }
    </script>
</body>
</html>