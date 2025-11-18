<?php
session_start();
require_once 'functions.php';
// Access restricted to Accounts and Admin roles
require_permission('access_accounts'); 
require_once 'db_connect.php'; 

$message = '';
$bill_details = null;
$bill_id_to_find = $_POST['bill_id'] ?? ($_GET['bill_id'] ?? null);

// --- 1. Handle Bill Search/Verification ---
if ($bill_id_to_find) {
    $bill_id_to_find = (int)$bill_id_to_find;
    
    // Fetch bill and payment details
    $sql = "
        SELECT 
            b.bill_id, p.pt_name, b.net_amount, b.status, b.bill_date,
            GROUP_CONCAT(CONCAT(pm.payment_method, ': ', pm.amount) SEPARATOR ' | ') AS payment_summary
        FROM billing b
        JOIN patients p ON b.pt_id = p.pt_id
        LEFT JOIN payments pm ON b.bill_id = pm.bill_id
        WHERE b.bill_id = ?
        GROUP BY b.bill_id
    ";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $bill_id_to_find);
        $stmt->execute();
        $bill_details = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// --- 2. Handle Bill Cancellation Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_cancel'])) {
    $bill_id = (int)$_POST['bill_id'];
    $reason = $conn->real_escape_string($_POST['reason']);
    $user_id = $_SESSION['user_id']; // Get current user ID

    $conn->begin_transaction();
    try {
        // A. Check current status
        $current_status = $conn->query("SELECT status FROM billing WHERE bill_id = $bill_id")->fetch_assoc()['status'] ?? 'Cancelled';
        
        if ($current_status === 'Cancelled') {
            throw new Exception("Bill $bill_id is already marked as Cancelled.");
        }

        // B. Update status in `billing` table
        $stmt_bill = $conn->prepare("UPDATE billing SET status = 'Cancelled' WHERE bill_id = ?");
        $stmt_bill->bind_param("i", $bill_id);
        $stmt_bill->execute();
        $stmt_bill->close();
        
        // C. Log cancellation in `cancelled_bills` table
        // Payments table is NOT physically updated. The reversal is handled implicitly by the accounts report logic.
        $stmt_log = $conn->prepare("INSERT INTO cancelled_bills (bill_id, reason, cancelled_by_user_id) VALUES (?, ?, ?)");
        $stmt_log->bind_param("isi", $bill_id, $reason, $user_id);
        $stmt_log->execute();
        $stmt_log->close();

        $conn->commit();
        $message = "<div style='color:green;'>✅ Bill #{$bill_id} successfully cancelled and reversal logged.</div>";
        $bill_details = null; // Clear details after success

    } catch (Exception $e) {
        $conn->rollback();
        $message = "<div style='color:red;'>❌ Cancellation failed: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Cancel Bill</title>
    <style>
        .container { padding: 20px; font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; }
        .search-form, .cancel-card { border: 1px solid #ccc; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .details-box { background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin-top: 15px; }
        input[type="number"], input[type="text"], textarea { padding: 10px; width: 100%; box-sizing: border-box; margin-top: 5px; }
        label { display: block; margin-top: 10px; font-weight: bold; }
        .btn-search { padding: 10px 15px; background-color: #337ab7; color: white; border: none; border-radius: 4px; cursor: pointer; margin-top: 15px; }
        .btn-cancel { padding: 10px 15px; background-color: #d9534f; color: white; border: none; border-radius: 4px; cursor: pointer; margin-top: 15px; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h1>Cancel Bill and Reversal Log</h1>
        <?php echo $message; ?>

        <div class="search-form">
            <h2>Find Bill to Cancel</h2>
            <form method="get" action="">
                <label for="bill_id_search">Enter Bill ID:</label>
                <input type="number" id="bill_id_search" name="bill_id" required value="<?php echo htmlspecialchars($bill_id_to_find ?? ''); ?>">
                <button type="submit" class="btn-search">Search Bill</button>
            </form>
        </div>

        <?php if ($bill_details): ?>
            <div class="cancel-card">
                <h2>Bill Details: #<?php echo $bill_details['bill_id']; ?></h2>
                <div class="details-box">
                    <p><strong>Patient:</strong> <?php echo htmlspecialchars($bill_details['pt_name']); ?></p>
                    <p><strong>Bill Date:</strong> <?php echo date('Y-m-d', strtotime($bill_details['bill_date'])); ?></p>
                    <p><strong>Net Amount Paid:</strong> ₹ <?php echo number_format($bill_details['net_amount'], 2); ?></p>
                    <p><strong>Status:</strong> <span style="color: <?php echo ($bill_details['status'] === 'Cancelled') ? 'red' : 'green'; ?>;"><?php echo htmlspecialchars($bill_details['status']); ?></span></p>
                    <p><strong>Payments:</strong> <?php echo htmlspecialchars($bill_details['payment_summary']); ?></p>
                </div>

                <?php if ($bill_details['status'] !== 'Cancelled'): ?>
                    <form method="post" action="">
                        <input type="hidden" name="bill_id" value="<?php echo $bill_details['bill_id']; ?>">
                        <label for="reason">Reason for Cancellation:</label>
                        <textarea id="reason" name="reason" rows="3" required></textarea>
                        <button type="submit" name="confirm_cancel" class="btn-cancel" onclick="return confirm('Are you sure you want to cancel Bill #<?php echo $bill_details['bill_id']; ?>? This action is permanent.');">
                            CONFIRM CANCELLATION
                        </button>
                    </form>
                <?php else: ?>
                    <p style="color: red; font-weight: bold; margin-top: 15px;">This bill is already cancelled.</p>
                <?php endif; ?>
            </div>
        <?php elseif ($bill_id_to_find): ?>
             <div class="cancel-card">
                <p style="color: red;">No active bill found with ID #<?php echo htmlspecialchars($bill_id_to_find); ?>.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>