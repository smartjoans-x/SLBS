<?php
// functions.php - Requires db_connect.php to be available
require_once 'db_connect.php'; 

function check_permission($role_id, $permission_slug) {
    global $conn;
    
    // Superadmin (Role ID 1) should bypass all permission checks for simplicity
    if ($role_id == 1) {
        return true; 
    }
    
    $sql = "SELECT 1 
            FROM permissions p
            JOIN role_permissions rp ON p.permission_id = rp.permission_id
            WHERE rp.role_id = ? AND p.permission_slug = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $role_id, $permission_slug);
    $stmt->execute();
    $stmt->store_result();
    
    $has_permission = $stmt->num_rows > 0;
    $stmt->close();
    
    return $has_permission;
}

function require_permission($permission_slug) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id'])) {
        // Not logged in
        header('Location: login.php');
        exit;
    }
    
    if (!check_permission($_SESSION['role_id'], $permission_slug)) {
        // Logged in but unauthorized
        header('Location: unauthorized.php'); 
        exit;
    }
}
?>