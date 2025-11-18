<?php
session_start();
require_once 'functions.php';
// This page is restricted to Superadmin via the 'access_licence' permission
require_permission('access_licence'); 
require_once 'db_connect.php'; 

$message = '';

// --- Handle Licence Update ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_licence'])) {
    $new_key = $conn->real_escape_string($_POST['licence_key']);
    $valid_until = $conn->real_escape_string($_POST['valid_upto']);

    // 1. Validation Checks
    if (strlen($new_key) !== 12 || !preg_match('/^[a-zA-Z0-9]{12}$/', $new_key)) {
        $message = "<div style='color:red;'>Error: Licence Key must be exactly 12 alphanumeric characters (e.g., SM017JO70929).</div>";
    } elseif (empty($valid_until)) {
        $message = "<div style='color:red;'>Error: Valid Up To date cannot be empty.</div>";
    } else {
        try {
            // Check if the licence table is empty (for initial setup) or contains a row
            $check = $conn->query("SELECT COUNT(*) FROM licence")->fetch_row()[0];

            if ($check == 0) {
                // INSERT if no licence exists
                $stmt = $conn->prepare("INSERT INTO licence (licence_key, valid_upto) VALUES (?, ?)");
                $stmt->bind_param("ss", $new_key, $valid_until);
                
                if ($stmt->execute()) {
                    $message = "<div style='color:green;'>Licence successfully installed.</div>";
                }
            } else {
                // UPDATE if licence already exists
                $stmt = $conn->prepare("UPDATE licence SET licence_key = ?, valid_upto = ? LIMIT 1");
                $stmt->bind_param("ss", $new_key, $valid_until);

                if ($stmt->execute()) {
                    $message = "<div style='color:green;'>Licence successfully updated/renewed.</div>";
                }
            }
            $stmt->close();

        } catch (Exception $e) {
            $message = "<div style='color:red;'>Database Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

// --- Fetch Current Licence Details ---
$licence_data = $conn->query("SELECT licence_key, valid_upto FROM licence LIMIT 1")->fetch_assoc();
$current_key = $licence_data['licence_key'] ?? 'N/A (Please set)';
$current_valid_upto = $licence_data['valid_upto'] ?? date('Y-m-d');
$is_expired = (strtotime($current_valid_upto) < time());
?>

<!DOCTYPE html>
<html>
<head>
    <title>Licence Management</title>
    <style>
        .container { padding: 20px; font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; }
        .card { border: 1px solid #ccc; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .status-box { padding: 15px; text-align: center; border-radius: 5px; font-weight: bold; font-size: 1.1em; }
        .status-valid { background-color: #d4edda; color: #155724; }
        .status-expired { background-color: #f8d7da; color: #721c24; }
        label { display: block; margin-top: 10px; font-weight: bold; }
        input[type="text"], input[type="date"] { width: 100%; padding: 10px; margin-top: 5px; box-sizing: border-box; }
        .renewal-button { padding: 10px 15px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; margin-top: 15px; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h1>Licence Management</h1>
        <p style="font-style: italic;">Only Superadmin can access and modify this information.</p>
        <?php echo $message; ?>

        <div class="card">
            <h2>Current Licence Status</h2>
            <p><strong>Licence Key:</strong> <?php echo htmlspecialchars($current_key); ?></p>
            <p><strong>Valid Until:</strong> <?php echo date('F j, Y', strtotime($current_valid_upto)); ?></p>
            
            <div class="status-box <?php echo $is_expired ? 'status-expired' : 'status-valid'; ?>" style="margin-top: 15px;">
                <?php echo $is_expired ? 'STATUS: EXPIRED' : 'STATUS: ACTIVE'; ?>
            </div>
        </div>

        <div class="card">
            <h2>Update / Renew Licence</h2>
            <form method="post">
                <label for="licence_key">New/Current Licence Key (12 Chars)</label>
                <input type="text" id="licence_key" name="licence_key" 
                    value="<?php echo htmlspecialchars($current_key !== 'N/A (Please set)' ? $current_key : ''); ?>" 
                    maxlength="12" required>

                <label for="valid_upto">Valid Up To Date</label>
                <input type="date" id="valid_upto" name="valid_upto" 
                    value="<?php echo htmlspecialchars($current_valid_upto); ?>" required>
                
                <button type="submit" name="update_licence" class="renewal-button">Save / Renew Licence</button>
            </form>
        </div>
    </div>
</body>
</html>