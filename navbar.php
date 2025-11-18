<?php
// Fetch company name from DB to display
$company_name = "Lab Billing System"; 
// Assuming $conn is available if the user is logged in
if (isset($conn)) {
    $result = $conn->query("SELECT company_name FROM company LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        $company_name = htmlspecialchars($row['company_name']);
    }
}

// Get the current user's role ID
$role_id = $_SESSION['role_id'] ?? 0;
?>

<style>
    /* ---------------- (Existing Navbar CSS - Unchanged) ---------------- */
    .navbar { background-color: #333; width: 100%; position: relative; z-index: 1000; display: flex; justify-content: space-between; box-shadow: 0 2px 5px rgba(0,0,0,0.2); } 
    .navbar-left, .navbar-right { display: flex; align-items: center; }
    .navbar a, .dropdown-toggle { padding: 14px 16px; text-decoration: none; color: #f2f2f2; background-color: #333; display: flex; align-items: center; transition: background-color 0.3s; height: 100%; box-sizing: border-box; }
    .dropdown-toggle button { background: none; border: none; color: #f2f2f2; font-size: 16px; cursor: pointer; padding: 0; margin: 0; }
    .navbar a:hover, .dropdown-toggle:hover { background-color: #ddd; color: black; }
    .navbar a:hover, .dropdown-toggle:hover button { color: black; }
    .navbar .logo { font-size: 1.2em; font-weight: bold; padding-right: 30px; }
    .navbar-right .user-info { padding: 14px 16px; color: white; }
    .dropdown { position: relative; }
    .dropdown-content { display: none; position: absolute; background-color: white; min-width: 160px; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2); z-index: 9999; top: 100%; }
    .dropdown-content a { color: black; padding: 12px 16px; text-align: left; background-color: white; }
    .dropdown-content a:hover { background-color: #f1f1f1; color: black; }
    .dropdown:hover .dropdown-content { display: block; }
    /* ------------------------------------------------ */
</style>

<div class="navbar">
    
    <div class="navbar-left">
        <a class="logo" href="dashboard.php"><?php echo $company_name; ?></a>
        
        <?php if (check_permission($role_id, 'access_billing')) : ?>
            <div class="dropdown">
                <div class="dropdown-toggle">
                    <button>Billing ▼</button>
                </div>
                <div class="dropdown-content">
                    <a href="billing.php">New Billing Entry</a>
                    <a href="print_bill_copy.php">Print Bill Copy</a>
                    <a href="print_report.php">Print Reports </a>
                    <a href="patient_history.php">Patient History</a> </div>
                     
            </div>
        <?php endif; ?>

        <?php if (check_permission($role_id, 'access_accounts')) : ?>
            <div class="dropdown">
                <div class="dropdown-toggle">
                    <button>Accounts ▼</button>
                </div>
                <div class="dropdown-content">
                    <a href="accounts.php">Daily Income Report</a>
                    <a href="cancel_bill.php">Cancel Bill</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if (check_permission($role_id, 'access_reports')) : ?>
            <div class="dropdown">
                <div class="dropdown-toggle">
                    <button>Report ▼</button>
                </div>
                <div class="dropdown-content">
                    <a href="report_entry.php">Report Entry</a>
                    <a href="view_reports.php">Report View</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if (check_permission($role_id, 'manage_users') || check_permission($role_id, 'manage_tests') || check_permission($role_id, 'access_licence') || $role_id == 1) : ?>
            <div class="dropdown">
                <div class="dropdown-toggle">
                    <button>Admin ▼</button>
                </div>
                <div class="dropdown-content">
                    
                    <?php if ($role_id == 1) : ?>
                        <a href="company_settings.php">Company Settings</a>
                    <?php endif; ?>

                    <?php if (check_permission($role_id, 'manage_users')) : ?>
                        <a href="user_management.php">User Management</a>
                    <?php endif; ?>
                    <?php if (check_permission($role_id, 'manage_tests')) : ?>
                        <a href="test_management.php">Test Management</a>
                    <?php endif; ?>
                    <?php if (check_permission($role_id, 'access_licence')) : ?>
                        <a href="licence_management.php">Licence</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="navbar-right">
        <div class="user-info">
            Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></strong>
        </div>
        <a class="logout" href="logout.php">Logout</a>
    </div>

</div>