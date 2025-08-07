<?php
require_once '../config.php';

if (!is_logged_in() || !is_admin()) {
    redirect('../login.php');
}

// Get all users
$users = [];
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? sanitize_input($_GET['role']) : '';

$query = "SELECT id, username, email, role, verified, created_at FROM users";
$conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $conditions[] = "(username LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'ss';
}

if (!empty($role_filter) && in_array($role_filter, ['admin', 'voter'])) {
    $conditions[] = "role = ?";
    $params[] = $role_filter;
    $types .= 's';
}

if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify_user'])) {
        $user_id = (int)$_POST['user_id'];
        $conn->query("UPDATE users SET verified = TRUE WHERE id = $user_id");
        log_activity('user_verify', "Verified user ID $user_id");
        $_SESSION['success'] = "User verified successfully";
        redirect('users.php');
    }
    
    if (isset($_POST['delete_user'])) {
        $user_id = (int)$_POST['user_id'];
        $conn->query("DELETE FROM users WHERE id = $user_id");
        log_activity('user_delete', "Deleted user ID $user_id");
        $_SESSION['success'] = "User deleted successfully";
        redirect('users.php');
    }
    
    if (isset($_POST['change_role'])) {
        $user_id = (int)$_POST['user_id'];
        $new_role = sanitize_input($_POST['new_role']);
        
        if (in_array($new_role, ['admin', 'voter'])) {
            $conn->query("UPDATE users SET role = '$new_role' WHERE id = $user_id");
            log_activity('role_change', "Changed role for user ID $user_id to $new_role");
            $_SESSION['success'] = "User role updated successfully";
            redirect('users.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Online Voting System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4cc9f0;
            --danger-color: #f72585;
            --warning-color: #f8961e;
            --info-color: #4895ef;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: #333;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        h2 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background-color: #6c757d;
            color: white;
            border-radius: 5px;
            text-decoration: none;
            transition: background-color 0.3s ease;
        }
        
        .back-btn:hover {
            background-color: #5a6268;
        }
        
        h2 i {
            color: var(--primary-color);
        }
        
        /* User Filters */
        .user-filters {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .filter-form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            min-width: 200px;
            flex-grow: 1;
        }
        
        .form-group label {
            font-size: 0.9rem;
            margin-bottom: 5px;
            color: #6c757d;
        }
        
        .filter-form input,
        .filter-form select {
            padding: 10px 15px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .filter-form input:focus,
        .filter-form select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        /* User Table */
        .user-table {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        th {
            background-color: var(--primary-color);
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }
        
        tr:hover {
            background-color: #f8f9fa;
        }
        
        /* Badges */
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge-success {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }
        
        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 12px;
            border-radius: 5px;
            font-size: 0.9rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }
        
        .btn-sm {
            padding: 6px 10px;
            font-size: 0.85rem;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #e11d74;
        }
        
        /* Forms */
        .role-form {
            margin: 0;
        }
        
        .role-form select {
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .role-form select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        /* No Users Message */
        .no-users {
            text-align: center;
            padding: 30px;
            color: #6c757d;
            font-size: 1.1rem;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .form-group {
                min-width: auto;
            }
            
            th, td {
                padding: 10px 8px;
                font-size: 0.9rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
        
        @media (max-width: 576px) {
            h2 {
                font-size: 1.5rem;
            }
            
            .container {
                padding: 15px;
            }
            
            .user-filters,
            .user-table {
                padding: 15px;
            }
            
            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        }
    </style>
</head>
<body>
    <?php //include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h2><i class="fas fa-users-cog"></i> Manage Users</h2>
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <div class="user-filters">
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label for="search"><i class="fas fa-search"></i> Search Users</label>
                    <input type="text" name="search" id="search" placeholder="Search by username or email..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="form-group">
                    <label for="role"><i class="fas fa-user-tag"></i> Filter by Role</label>
                    <select name="role" id="role">
                        <option value="">All Roles</option>
                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="voter" <?php echo $role_filter === 'voter' ? 'selected' : ''; ?>>Voter</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <a href="users.php" class="btn btn-secondary">
                    <i class="fas fa-sync-alt"></i> Reset
                </a>
            </form>
        </div>
        
        <div class="user-table">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td>
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($user['username']); ?>
                            </td>
                            <td>
                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?>
                            </td>
                            <td>
                                <form method="POST" class="role-form">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <select name="new_role" onchange="this.form.submit()">
                                        <option value="voter" <?php echo $user['role'] === 'voter' ? 'selected' : ''; ?>>
                                            <i class="fas fa-user"></i> Voter
                                        </option>
                                        <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>
                                            <i class="fas fa-user-shield"></i> Admin
                                        </option>
                                    </select>
                                    <input type="hidden" name="change_role">
                                </form>
                            </td>
                            <td>
                                <?php if ($user['verified']): ?>
                                    <span class="badge badge-success">
                                        <i class="fas fa-check-circle"></i> Verified
                                    </span>
                                <?php else: ?>
                                    <form method="POST">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" name="verify_user" class="btn btn-sm btn-primary">
                                            <i class="fas fa-user-check"></i> Verify
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td>
                                <i class="far fa-calendar-alt"></i> <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this user?')">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" name="delete_user" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash-alt"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (empty($users)): ?>
                <p class="no-users">
                    <i class="fas fa-user-slash"></i> No users found matching your criteria.
                </p>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="../js/admin.js"></script>
    <script>
        // Enhance the role select dropdowns
        document.querySelectorAll('.role-form select').forEach(select => {
            select.addEventListener('change', function() {
                const form = this.closest('form');
                const spinner = document.createElement('i');
                spinner.className = 'fas fa-spinner fa-spin';
                this.parentNode.insertBefore(spinner, this.nextSibling);
                
                form.submit();
            });
        });
        
        // Add confirmation for verify action
        document.querySelectorAll('button[name="verify_user"]').forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to verify this user?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>