<?php
session_start();
require_once 'functions.php';
require_permission('access_billing'); 
require_once 'db_connect.php'; 

$message = '';
$bill_id_to_find = $_POST['bill_id'] ?? ($_GET['bill_id'] ?? null);
$bill_details = null;
$company_info = $conn->query("SELECT company_name, address, phone_no FROM company LIMIT 1")->fetch_assoc() ?? ['company_name' => 'Lab Billing System', 'address' => 'Not Set', 'phone_no' => 'Not Set'];

// --- Handle Bill Search/Verification ---
if ($bill_id_to_find) {
    $bill_id_to_find = (int)$bill_id_to_find;
    
    // Fetch bill, patient, tests, and payment details
    $sql = "
        SELECT 
            b.bill_id, b.bill_date, b.total_amount, b.discount, b.net_amount, b.status,
            p.pt_name, p.age, p.sex,
            d.doctor_name AS referring_doctor,
            GROUP_CONCAT(CONCAT(t.test_name, ':', bt.test_price) SEPARATOR ';') AS test_list,
            GROUP_CONCAT(CONCAT(pm.payment_method, ':', pm.amount) SEPARATOR ';') AS payment_details
        FROM billing b
        JOIN patients p ON b.pt_id = p.pt_id
        JOIN bill_tests bt ON b.bill_id = bt.bill_id
        JOIN tests t ON bt.test_id = t.test_id
        LEFT JOIN doctors d ON b.refer_type = 'Doctor' AND b.refer_id = d.doctor_id
        LEFT JOIN payments pm ON b.bill_id = pm.bill_id
        WHERE b.bill_id = ?
        GROUP BY b.bill_id, p.pt_name, d.doctor_name
    ";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $bill_id_to_find);
        $stmt->execute();
        $bill_details = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$bill_details) {
            $message = "<div style='color:red;'>Bill #{$bill_id_to_find} not found.</div>";
        } elseif ($bill_details['status'] === 'Cancelled') {
            $message = "<div style='color:red;'>Bill #{$bill_id_to_find} has been cancelled.</div>";
        }
    }
}

// --- Payment Processing ---
$payment_modes = [];
if ($bill_details && $bill_details['payment_details']) {
    $payments_raw = explode(';', $bill_details['payment_details']);
    foreach ($payments_raw as $payment_data) {
        if (empty($payment_data)) continue;
        list($method, $amount) = explode(':', $payment_data);
        if ((float)$amount > 0) {
             $payment_modes[] = ['method' => $method, 'amount' => (float)$amount];
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Print Bill Copy</title>
    <style>
        .container { padding: 20px; font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; }
        .search-form { border: 1px solid #ccc; padding: 20px; border-radius: 8px; margin-bottom: 20px; max-width: 400px; margin: 20px auto; }
        input[type="number"] { padding: 10px; width: 100%; box-sizing: border-box; margin-top: 5px; }
        label { display: block; margin-top: 10px; font-weight: bold; }
        .btn-search { padding: 10px 15px; background-color: #337ab7; color: white; border: none; border-radius: 4px; cursor: pointer; margin-top: 15px; }
        
        /* --- PRINT STYLES FOR INVOICE --- */
        .invoice-container { margin-top: 20px; padding: 15px; border: 1px solid #333; }
        .invoice-header, .invoice-footer { text-align: center; margin-bottom: 15px; }
        .invoice-header h2 { margin: 0; }
        .invoice-details table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .invoice-details th, .invoice-details td { padding: 8px 0; font-size: 0.9em;}
        .invoice-details th { text-align: left; border-bottom: 2px solid #333; }
        .invoice-details td { border-bottom: 1px dotted #ccc; }
        .summary-row td { border-top: 1px solid #333; font-weight: bold; }
        .payment-list { list-style: none; padding: 0; margin: 5px 0; }
        .payment-list li { display: inline; margin-right: 15px; }

        @media print {
            .navbar, .search-form, button.no-print, h1.page-title { /* Added h1.page-title */
                display: none !important; 
                visibility: hidden; 
            }
            body { 
                padding: 0; 
                margin: 0;
            }
            .invoice-container { 
                border: none !important; 
                padding: 0; 
                margin: 0; 
                width: 100%; 
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h1 class="page-title">Print Past Bill</h1>
        <?php echo $message; ?>

        <div class="search-form no-print">
            <h2>Find Bill by ID</h2>
            <form method="get" action="">
                <label for="bill_id_search">Enter Bill ID:</label>
                <input type="number" id="bill_id_search" name="bill_id" required value="<?php echo htmlspecialchars($bill_id_to_find ?? ''); ?>">
                <button type="submit" class="btn-search">View Bill</button>
            </form>
        </div>

        <?php if ($bill_details && $bill_details['status'] !== 'Cancelled'): ?>
            <div class="invoice-container">
                
                <div class="invoice-header">
                    <h2><?php echo htmlspecialchars($company_info['company_name']); ?></h2>
                    <p><?php echo htmlspecialchars($company_info['address'] ?? 'Address Not Set'); ?> | Phone: <?php echo htmlspecialchars($company_info['phone_no'] ?? 'Not Set'); ?></p>
                    <h3 style="border-bottom: 1px solid #333; padding-bottom: 5px;">BILL INVOICE</h3>
                </div>

                <div class="invoice-meta" style="display: flex; justify-content: space-between; font-size: 0.9em; margin-bottom: 15px;">
                    <div>
                        <strong>Bill No:</strong> <?php echo $bill_details['bill_id']; ?><br>
                        <strong>Date:</strong> <?php echo date('Y-m-d H:i', strtotime($bill_details['bill_date'])); ?>
                    </div>
                    <div>
                        <strong>Patient:</strong> <?php echo htmlspecialchars($bill_details['pt_name']); ?><br>
                        <strong>Age/Sex:</strong> <?php echo $bill_details['age'] . ' / ' . $bill_details['sex']; ?><br>
                        <strong>Ref. By:</strong> <?php echo htmlspecialchars($bill_details['referring_doctor'] ?: 'Self'); ?>
                    </div>
                </div>

                <div class="invoice-details">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 5%;">#</th>
                                <th style="width: 60%;">Test Description</th>
                                <th style="width: 15%; text-align: right;">Price (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                                $tests = explode(';', $bill_details['test_list']);
                                foreach ($tests as $index => $test_data):
                                    if(empty($test_data)) continue;
                                    list($name, $price) = explode(':', $test_data);
                            ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($name); ?></td>
                                    <td style="text-align: right;"><?php echo number_format((float)$price, 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <tr><td colspan="3" style="height: 10px;"></td></tr>
                            <tr class="summary-row"><td colspan="2" style="text-align: right; border: none;">SUB TOTAL:</td><td style="text-align: right;"><?php echo number_format($bill_details['total_amount'], 2); ?></td></tr>
                            <tr class="summary-row"><td colspan="2" style="text-align: right; border: none;">DISCOUNT:</td><td style="text-align: right;">- <?php echo number_format($bill_details['discount'], 2); ?></td></tr>
                            <tr class="summary-row"><td colspan="2" style="text-align: right;">NET AMOUNT:</td><td style="text-align: right;"><?php echo number_format($bill_details['net_amount'], 2); ?></td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="invoice-footer" style="margin-top: 20px; font-size: 0.9em;">
                    <p style="margin-bottom: 5px;"><strong>Payment Mode(s):</strong></p>
                    <ul class="payment-list">
                        <?php foreach ($payment_modes as $pm): ?>
                            <li><?php echo htmlspecialchars($pm['method']); ?> (₹<?php echo number_format($pm['amount'], 2); ?>)</li>
                        <?php endforeach; ?>
                    </ul>
                    <p>Thank you for choosing us.</p>
                </div>

            </div>
            <button onclick="window.print()" class="btn-search no-print" style="width: 100%; background-color: green; margin-top: 15px;">Print Bill Copy</button>
        <?php elseif ($bill_id_to_find && $bill_details && $bill_details['status'] === 'Cancelled'): ?>
            <p class="no-print" style="color: red; font-weight: bold; text-align: center;">Bill #<?php echo $bill_id_to_find; ?> is marked as cancelled and cannot be printed as a valid invoice.</p>
        <?php endif; ?>
    </div>
</body>
</html>