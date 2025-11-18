<?php
session_start();
require_once 'functions.php';
require_permission('manage_users');
require_once 'db_connect.php';

// Optional: show mysqli errors as exceptions during development
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$current_user_role_id = $_SESSION['role_id'] ?? 0;
$message = '';
$selected_role_id = $_GET['role_id'] ?? null;
$roles = $conn->query("SELECT role_id, role_name FROM roles ORDER BY role_id")->fetch_all(MYSQLI_ASSOC);

// --- Submission Logic: Add New Role ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_new_role'])) {
    $new_role_name = $conn->real_escape_string(trim($_POST['new_role_name'] ?? ''));

    if (empty($new_role_name)) {
        $message = "<div style='color:red;'>Role Name cannot be empty.</div>";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO roles (role_name) VALUES (?)");
            $stmt->bind_param("s", $new_role_name);
            $stmt->execute();
            $stmt->close();
            
            $message = "<div style='color:green;'>Role '{$new_role_name}' created successfully. Select it to set permissions.</div>";
            // Refresh roles list
            $roles = $conn->query("SELECT role_id, role_name FROM roles ORDER BY role_id")->fetch_all(MYSQLI_ASSOC);

        } catch (Exception $e) {
            $message = "<div style='color:red;'>Error creating role: Role name may already exist.</div>";
        }
    }
}


// --- Submission Logic: Adding/Updating Users (with Superadmin Guardrail) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_user'])) {
    $username = $conn->real_escape_string(trim($_POST['username'] ?? ''));
    $password = $_POST['password'];
    $role_id_to_assign = (int)$_POST['role_id'];

    // GUARDRAIL: Prevent non-Superadmin from creating a Superadmin user (Role ID 1)
    if ($role_id_to_assign == 1 && $current_user_role_id != 1) {
        $message = "<div style='color:red;'>ACCESS DENIED: Only Superadmin can create a user with Superadmin role.</div>";
    } elseif (empty($username) || empty($password)) {
        $message = "<div style='color:red;'>Username and password cannot be empty.</div>";
    } else {
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO users (username, password_hash, role_id) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $username, $password_hash, $role_id_to_assign);
        
        if ($stmt->execute()) {
            $message = "<div style='color:green;'>User {$username} created successfully.</div>";
        } else {
            $message = "<div style='color:red;'>Error creating user. Username may already exist or DB failure.</div>";
        }
        $stmt->close();
    }
}

// --- Submission Logic: Updating Page Access (Permissions) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_permissions'])) {
    $role_id = (int)$_POST['role_id'];
    $permissions_granted = $_POST['permissions'] ?? [];
    
    // GUARDRAIL: Prevent non-Superadmin from modifying Superadmin's permissions
    if ($role_id == 1 && $current_user_role_id != 1) {
         $message = "<div style='color:red;'>ACCESS DENIED: Only Superadmin can modify Superadmin permissions.</div>";
    } else {
        $conn->begin_transaction();
        try {
            // 1. Clear all existing permissions for this role
            $conn->query("DELETE FROM role_permissions WHERE role_id = $role_id");

            // 2. Insert new permissions
            if (!empty($permissions_granted)) {
                $stmt = $conn->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                foreach ($permissions_granted as $permission_id) {
                    $permission_id = (int)$permission_id;
                    $stmt->bind_param("ii", $role_id, $permission_id);
                    $stmt->execute();
                }
                $stmt->close();
            }
            
            $conn->commit();
            $message = "<div style='color:green;'>Page access updated successfully for the role.</div>";

        } catch (Exception $e) {
            $conn->rollback();
            $message = "<div style='color:red;'>Error updating permissions: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

// --- Fetch Permission Data for RBAC Form ---
$all_permissions = $conn->query("SELECT permission_id, permission_slug, permission_desc FROM permissions ORDER BY permission_slug")->fetch_all(MYSQLI_ASSOC);
$role_permissions_map = [];

if ($selected_role_id) {
    // Fetch currently assigned permissions for the selected role
    $stmt = $conn->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
    $stmt->bind_param("i", $selected_role_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Map them to checkmarks
    $role_permissions_map = array_column($result, 'permission_id');
}

// Fetch all users for management table
$users_list = $conn->query("
    SELECT u.user_id, u.username, r.role_name, u.role_id, u.is_active 
    FROM users u
    JOIN roles r ON u.role_id = r.role_id
    ORDER BY u.user_id
")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>User & Role Management</title>
    <style>
        .container { padding: 20px; font-family: Arial, sans-serif; display: flex; gap: 30px; }
        .user-list-panel { flex: 2; }
        .rbac-panel { flex: 1; border-left: 1px solid #ccc; padding-left: 30px; }
        input[type="text"], input[type="password"], select { padding: 8px; margin: 5px; border: 1px solid #ced4da; border-radius: 4px; box-sizing: border-box; }
        .user-add-form input, .user-add-form select { width: 45%; margin-right: 1%; }
        .user-add-form button { padding: 8px 15px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: left; }
        .permission-item { margin: 5px 0; border-bottom: 1px dotted #eee; padding: 5px; }
        .permission-item label { display: inline; font-weight: normal; }
        .role-selector button { padding: 10px 15px; margin: 5px; background: #337ab7; color: white; border: none; cursor: pointer; border-radius: 4px; }
        .role-selector a { text-decoration: none; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        
        <div class="user-list-panel">
            <h1>User Management</h1>
            <?php echo $message; ?>

            <?php if ($current_user_role_id == 1): ?>
                <h3 style="margin-top: 20px;">+ Create New Role</h3>
                <form method="post">
                    <input type="text" name="new_role_name" placeholder="New Role Name (e.g., Doctor, Manager)" required style="width: 70%; margin-right: 10px;">
                    <button type="submit" name="add_new_role" class="user-add-form button" style="background: #28a745;">Create Role</button>
                </form>
            <?php endif; ?>
            
            <h3 style="margin-top: 20px;">Add New User</h3>
            <form method="post" class="user-add-form">
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
                <select name="role_id" required>
                    <?php foreach($roles as $role): ?>
                        <?php 
                            // GUARDRAIL: Prevent Admin (Non-Superadmin) from assigning Superadmin role (ID 1)
                            if ($role['role_id'] == 1 && $current_user_role_id != 1) continue;
                        ?>
                        <option value="<?php echo $role['role_id']; ?>"><?php echo htmlspecialchars($role['role_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="add_user" style="background: #007bff;">Create User</button>
            </form>

            <h3 style="margin-top: 20px;">Existing Users</h3>
            <table>
                <thead>
                    <tr><th>ID</th><th>Username</th><th>Role</th><th>Active</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($users_list as $user): ?>
                        <tr>
                            <td><?php echo $user['user_id']; ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['role_name']); ?></td>
                            <td><?php echo $user['is_active'] ? 'Yes' : 'No'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="rbac-panel">
            <h2>Role Access Configuration</h2>
            
            <div class="role-selector">
                <?php foreach ($roles as $role): ?>
                    <?php
                        $role_is_superadmin = ($role['role_id'] == 1);
                        // Prevent non-Superadmin from seeing/configuring Superadmin role
                        if ($role_is_superadmin && $current_user_role_id != 1) continue;
                    ?>
                    <a href="?role_id=<?php echo $role['role_id']; ?>">
                        <button style="<?php echo ($selected_role_id == $role['role_id']) ? 'background: #007bff;' : ''; ?>">
                            Configure <?php echo htmlspecialchars($role['role_name']); ?>
                        </button>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($selected_role_id): ?>
                <?php 
                    $selected_role_name = htmlspecialchars($conn->query("SELECT role_name FROM roles WHERE role_id = $selected_role_id")->fetch_assoc()['role_name']);
                    $is_superadmin_role = ($selected_role_id == 1);
                    $disable_form = ($is_superadmin_role && $current_user_role_id != 1);
                ?>
                <h3 style="margin-top: 20px;">Permissions for: <?php echo $selected_role_name; ?></h3>
                
                <form method="post">
                    <input type="hidden" name="role_id" value="<?php echo $selected_role_id; ?>">
                    <input type="hidden" name="update_permissions" value="1">
                    
                    <?php if ($disable_form): ?>
                        <p style="color: red; font-weight: bold;">VIEW ONLY: Superadmin permissions cannot be modified by other roles.</p>
                    <?php endif; ?>

                    <p style="font-size: 0.9em; font-style: italic;">Check the pages/features this role can access:</p>

                    <div style="border: 1px solid #ddd; padding: 10px; max-height: 400px; overflow-y: auto;">
                        <?php foreach ($all_permissions as $permission): ?>
                            <div class="permission-item">
                                <label>
                                    <input type="checkbox" 
                                        name="permissions[]" 
                                        value="<?php echo $permission['permission_id']; ?>"
                                        <?php echo in_array($permission['permission_id'], $role_permissions_map) ? 'checked' : ''; ?>
                                        <?php echo $disable_form ? 'disabled' : ''; ?>
                                    >
                                    <strong><?php echo htmlspecialchars($permission['permission_slug']); ?>:</strong> <?php echo htmlspecialchars($permission['permission_desc']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="submit" name="save_access" style="width: 100%; padding: 10px; margin-top: 15px; background: #28a745; color: white;" <?php echo $disable_form ? 'disabled' : ''; ?>>
                        Save Access Settings
                    </button>
                </form>
            <?php else: ?>
                <p style="margin-top: 20px;">Select a role above to manage its page access permissions.</p>
            <?php endif; ?>

        </div>
    </div>
</body>
</html>