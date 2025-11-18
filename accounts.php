<?php
session_start();
require_once 'functions.php';
require_permission('access_accounts');
require_once 'db_connect.php'; 

$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

$income_by_method = ['Cash' => 0.00, 'Card' => 0.00, 'UPI' => 0.00];

// --- Income Calculation Logic ---
$sql_income = "
    SELECT 
        p.payment_method, SUM(p.amount) as total_received
    FROM payments p
    JOIN billing b ON p.bill_id = b.bill_id
    WHERE DATE(p.payment_date) BETWEEN ? AND ? 
    AND b.status = 'Paid'
    GROUP BY p.payment_method
";

$stmt_income = $conn->prepare($sql_income);
$stmt_income->bind_param("ss", $start_date, $end_date);
$stmt_income->execute();
$result_income = $stmt_income->get_result();

while ($row = $result_income->fetch_assoc()) {
    $income_by_method[$row['payment_method']] = (float)$row['total_received'];
}
$stmt_income->close();


// --- Cancellation Logic ---
// Fetch amounts from cancelled bills to subtract them from the respective method income
$sql_cancellation = "
    SELECT 
        p.payment_method, SUM(p.amount) as total_cancelled
    FROM payments p
    JOIN cancelled_bills cb ON p.bill_id = cb.bill_id
    WHERE DATE(cb.cancel_date) BETWEEN ? AND ? 
    GROUP BY p.payment_method
";

$stmt_cancel = $conn->prepare($sql_cancellation);
$stmt_cancel->bind_param("ss", $start_date, $end_date);
$stmt_cancel->execute();
$result_cancel = $stmt_cancel->get_result();

while ($row = $result_cancel->fetch_assoc()) {
    // Subtract the cancelled amount from the income
    $income_by_method[$row['payment_method']] -= (float)$row['total_cancelled'];
}
$stmt_cancel->close();

$net_total_income = array_sum($income_by_method);

// Fetch cancelled bills list
$cancelled_list = $conn->query("
    SELECT cb.bill_id, p.pt_name, cb.cancel_date 
    FROM cancelled_bills cb
    JOIN billing b ON cb.bill_id = b.bill_id
    JOIN patients p ON b.pt_id = p.pt_id
    WHERE DATE(cb.cancel_date) BETWEEN '{$start_date}' AND '{$end_date}'
    ORDER BY cb.cancel_date DESC
")->fetch_all(MYSQLI_ASSOC);

?>

<!DOCTYPE html>
<html>
<head>
    <title>Accounts & Income Report</title>
    <style>
        .container { padding: 20px; font-family: Arial, sans-serif; }
        .report-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-top: 20px; }
        .card { border: 1px solid #ddd; padding: 20px; border-radius: 8px; text-align: center; }
        .card h3 { margin-top: 0; }
        .card .amount { font-size: 2em; font-weight: bold; color: #007bff; }
        #net_total .amount { color: #28a745; }
        .filter-form { margin-bottom: 20px; padding: 10px; border: 1px solid #ccc; border-radius: 5px; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h1>Accounts & Income Report</h1>

        <div class="filter-form">
            <form method="get" action="">
                <label>Start Date:</label>
                <input type="date" name="start_date" value="<?php echo $start_date; ?>">
                <label>End Date:</label>
                <input type="date" name="end_date" value="<?php echo $end_date; ?>">
                <button type="submit">Filter Report</button>
            </form>
            <p>Showing report from **<?php echo $start_date; ?>** to **<?php echo $end_date; ?>**.</p>
        </div>

        <div class="report-grid">
            <div class="card" id="cash_total">
                <h3>Cash Income</h3>
                <div class="amount">₹ <?php echo number_format($income_by_method['Cash'], 2); ?></div>
            </div>
            <div class="card" id="card_total">
                <h3>Card Income</h3>
                <div class="amount">₹ <?php echo number_format($income_by_method['Card'], 2); ?></div>
            </div>
            <div class="card" id="upi_total">
                <h3>UPI Income</h3>
                <div class="amount">₹ <?php echo number_format($income_by_method['UPI'], 2); ?></div>
            </div>
            <div class="card" id="net_total" style="border-color: #28a745;">
                <h3>NET INCOME</h3>
                <div class="amount">₹ <?php echo number_format($net_total_income, 2); ?></div>
            </div>
        </div>

        <h2 style="margin-top: 40px;">Cancelled Bills Log (Affecting Income)</h2>
        <table>
            <thead>
                <tr><th>Bill ID</th><th>Patient Name</th><th>Cancellation Date</th></tr>
            </thead>
            <tbody>
                <?php if (empty($cancelled_list)): ?>
                    <tr><td colspan="3">No cancelled bills found in this period.</td></tr>
                <?php endif; ?>
                <?php foreach ($cancelled_list as $item): ?>
                    <tr>
                        <td><?php echo $item['bill_id']; ?></td>
                        <td><?php echo htmlspecialchars($item['pt_name']); ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($item['cancel_date'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    </div>
</body>
</html>