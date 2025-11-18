<?php
// Define configuration details
define('DB_SERVER', 'localhost'); 
define('DB_USERNAME', 'smartjoa_demo');
define('DB_PASSWORD', 'Mrsmjo@17'); // Ensure this is correct
define('DB_NAME', 'smartjoa_demo');

// Attempt to establish connection
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection and output error if it fails
if ($conn->connect_error) {
    // Stop execution and show a descriptive error message
    die("<h2>Database Connection Error:</h2> " . $conn->connect_error . 
        "<p>Please check your DB_SERVER, DB_USERNAME, DB_PASSWORD, and DB_NAME in db_connect.php.</p>");
}

// Set character set
$conn->set_charset("utf8");
// Start session for use across all pages
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>