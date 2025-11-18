<?php
session_start();
require_once 'functions.php';
// Restrict access: Only Superadmin (Role ID 1) should have 'access_licence' permission,
// so we'll use that same permission slug or a new one, but for simplicity, we check role ID 1
// If you use 'access_licence' for this too, ensure the RBAC page is configured. 
// For guaranteed Superadmin access, we'll check the role ID directly (less flexible, but safer for critical setup).
if ($_SESSION['role_id'] != 1) {
    header('Location: unauthorized.php');
    exit();
}
require_once 'db_connect.php'; 

$message = '';

// --- Handle Form Submission (Update) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_company'])) {
    $company_name = $conn->real_escape_string($_POST['company_name']);
    $address = $conn->real_escape_string($_POST['address']);
    $phone_no = $conn->real_escape_string($_POST['phone_no']);

    if (empty($company_name)) {
        $message = "<div style='color:red;'>Error: Company Name is required.</div>";
    } else {
        $conn->begin_transaction();
        try {
            // Check if a record exists (assuming only one row is used)
            $check = $conn->query("SELECT COUNT(*) FROM company")->fetch_row()[0];

            if ($check == 0) {
                // INSERT if no data exists
                $stmt = $conn->prepare("INSERT INTO company (company_name, address, phone_no) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $company_name, $address, $phone_no);
            } else {
                // UPDATE the single row (ID 1 is usually the target, or use LIMIT 1)
                $stmt = $conn->prepare("UPDATE company SET company_name = ?, address = ?, phone_no = ? LIMIT 1");
                $stmt->bind_param("sss", $company_name, $address, $phone_no);
            }
            
            if ($stmt->execute()) {
                $conn->commit();
                $message = "<div style='color:green;'>✅ Company details successfully updated.</div>";
            } else {
                throw new Exception($stmt->error);
            }
            $stmt->close();

        } catch (Exception $e) {
            $conn->rollback();
            $message = "<div style='color:red;'>Database Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

// --- Fetch Current Company Details ---
$company_data = $conn->query("SELECT company_name, address, phone_no FROM company LIMIT 1")->fetch_assoc();
$current_name = $company_data['company_name'] ?? '';
$current_address = $company_data['address'] ?? '';
$current_phone = $company_data['phone_no'] ?? '';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Company Settings</title>
    <style>
        .container { padding: 20px; font-family: Arial, sans-serif; max-width: 700px; margin: 0 auto; }
        .card { border: 1px solid #ccc; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        label { display: block; margin-top: 10px; font-weight: bold; }
        input[type="text"], textarea { width: 100%; padding: 10px; margin-top: 5px; box-sizing: border-box; }
        .btn-save { padding: 10px 15px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; margin-top: 15px; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h1>⚙️ Company & Branding Settings</h1>
        <p style="font-style: italic;">Update these details for invoices and final reports.</p>
        <?php echo $message; ?>

        <div class="card">
            <form method="post">
                <label for="company_name">Company Name* (Appears on Navbar/Reports)</label>
                <input type="text" id="company_name" name="company_name" 
                    value="<?php echo htmlspecialchars($current_name); ?>" required>
                
                <label for="address">Full Address</label>
                <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($current_address); ?></textarea>

                <label for="phone_no">Phone Number</label>
                <input type="text" id="phone_no" name="phone_no" 
                    value="<?php echo htmlspecialchars($current_phone); ?>">
                
                <button type="submit" name="update_company" class="btn-save">Save Company Details</button>
            </form>
        </div>
    </div>
</body>
</html>