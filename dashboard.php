<?php
session_start();
require_once 'functions.php';

// Check if logged in (no specific permission required for dashboard, just login)
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Database connection is available via functions.php -> db_connect.php
require_once 'db_connect.php'; 

?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div style="padding: 20px;">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>!</h1>
        <p>This is your main dashboard. Use the navigation bar above to access different sections of the Lab Billing System.</p>
        
        <h2>Quick Access:</h2>
        <ul>
            <?php if (check_permission($_SESSION['role_id'], 'access_billing')) : ?>
                <li><a href="billing.php">Start a New Billing</a></li>
            <?php endif; ?>
            <?php if (check_permission($_SESSION['role_id'], 'access_reports')) : ?>
                <li><a href="report_entry.php">Enter Test Results</a></li>
            <?php endif; ?>
            <?php if (check_permission($_SESSION['role_id'], 'access_accounts')) : ?>
                <li><a href="accounts.php">View Today's Income</a></li>
            <?php endif; ?>
        </ul>
    </div>
</body>
</html>