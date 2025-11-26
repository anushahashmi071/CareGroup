<?php
// admin/manage_users.php - User Management
require_once '../config.php';
requireRole('admin');

// site name
$site_name = getSetting('site_name', 'CARE Group Medical Services');

$conn = getDBConnection();
$action_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? '');
        $status = trim($_POST['status'] ?? '');
        $password = password_hash('default123', PASSWORD_DEFAULT); // Default password for new users
        
        // Validate inputs
        if (!empty($username) && !empty($email) && !empty($role) && !empty($status)) {
            // Sanitize inputs
            $username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
            $email = filter_var($email, FILTER_SANITIZE_EMAIL);
            $role = htmlspecialchars($role, ENT_QUOTES, 'UTF-8');
            $status = htmlspecialchars($status, ENT_QUOTES, 'UTF-8');
            
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $action_message = '<div class="alert alert-warning">Invalid email format!</div>';
            } else {
                // Check if username or email already exists
                $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
                $check_stmt->bind_param("ss", $username, $email);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $action_message = '<div class="alert alert-warning">Username or email already exists!</div>';
                } else {
                    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmt->bind_param("sssss", $username, $email, $password, $role, $status);
                    if ($stmt->execute()) {
                        $action_message = '<div class="alert alert-success">User added successfully! Default password: default123</div>';
                    } else {
                        $action_message = '<div class="alert alert-danger">Error adding user: ' . htmlspecialchars($conn->error) . '</div>';
                    }
                    $stmt->close();
                }
                $check_stmt->close();
            }
        } else {
            $action_message = '<div class="alert alert-warning">All fields are required.</div>';
        }
    } elseif (isset($_POST['update_user'])) {
        $user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? '');
        $status = trim($_POST['status'] ?? '');
        
        if ($user_id && !empty($username) && !empty($email) && !empty($role) && !empty($status)) {
            // Sanitize inputs
            $username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
            $email = filter_var($email, FILTER_SANITIZE_EMAIL);
            $role = htmlspecialchars($role, ENT_QUOTES, 'UTF-8');
            $status = htmlspecialchars($status, ENT_QUOTES, 'UTF-8');
            
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $action_message = '<div class="alert alert-warning">Invalid email format!</div>';
            } else {
                // Check if username or email already exists for other users
                $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE (username = ? OR email = ?) AND user_id != ?");
                $check_stmt->bind_param("ssi", $username, $email, $user_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $action_message = '<div class="alert alert-warning">Username or email already exists for another user!</div>';
                } else {
                    $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ?, status = ? WHERE user_id = ?");
                    $stmt->bind_param("ssssi", $username, $email, $role, $status, $user_id);
                    if ($stmt->execute()) {
                        $action_message = '<div class="alert alert-success">User updated successfully!</div>';
                    } else {
                        $action_message = '<div class="alert alert-danger">Error updating user: ' . htmlspecialchars($conn->error) . '</div>';
                    }
                    $stmt->close();
                }
                $check_stmt->close();
            }
        } else {
            $action_message = '<div class="alert alert-warning">All fields are required.</div>';
        }
    } elseif (isset($_POST['delete_user'])) {
        $user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
        
        // Prevent admin from deleting their own account
        if ($user_id == $_SESSION['user_id']) {
            $action_message = '<div class="alert alert-danger">You cannot delete your own account!</div>';
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $action_message = '<div class="alert alert-success">User deleted successfully!</div>';
            } else {
                $action_message = '<div class="alert alert-danger">Error deleting user: ' . htmlspecialchars($conn->error) . '</div>';
            }
            $stmt->close();
        }
    } elseif (isset($_POST['reset_password'])) {
        $user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
        $password = password_hash('default123', PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->bind_param("si", $password, $user_id);
        if ($stmt->execute()) {
            $action_message = '<div class="alert alert-success">Password reset successfully! New password: default123</div>';
        } else {
            $action_message = '<div class="alert alert-danger">Error resetting password: ' . htmlspecialchars($conn->error) . '</div>';
        }
        $stmt->close();
    }
}

// Fetch all users
$users = $conn->query("SELECT * FROM users ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - CARE Group</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4cc9f0;
            --info-color: #4895ef;
            --warning-color: #0365c0ff;
            --danger-color: #e63946;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --sidebar-bg: #1e293b;
            --sidebar-hover: #334155;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f1f5f9;
            color: #334155;
            overflow-x: hidden;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 260px;
            background: var(--sidebar-bg);
            color: white;
            transition: var(--transition);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 1.5rem 1rem;
            border-bottom: 1px solid #334155;
            text-align: center;
        }

        .sidebar-header h2 {
            color: #fff;
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
        }

        .sidebar-header p {
            color: #94a3b8;
            font-size: 0.875rem;
        }

        .sidebar-menu {
            list-style: none;
            padding: 1rem 0;
        }

        .sidebar-menu li {
            margin-bottom: 0.25rem;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: #cbd5e1;
            text-decoration: none;
            transition: var(--transition);
        }

        .sidebar-menu a:hover {
            background-color: var(--sidebar-hover);
            color: white;
        }

        .sidebar-menu a.active {
            background-color: var(--primary-color);
            color: white;
            border-right: 4px solid var(--success-color);
        }

        .sidebar-menu i {
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            transition: var(--transition);
        }

        /* Top Bar */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            background: white;
            box-shadow: var(--card-shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .top-bar h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--light-color);
            color: var(--dark-color);
            text-decoration: none;
            border-radius: 0.375rem;
            transition: var(--transition);
        }

        .logout-btn:hover {
            background: #e2e8f0;
        }

        /* Content Container */
        .container {
            padding: 2rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            color: var(--dark-color);
            font-size: 2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        /* Content Section */
        .content-section {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f5f9;
        }

        .section-header h2 {
            color: #1e293b;
            font-size: 1.5rem;
            font-weight: 600;
        }

        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            font-size: 0.875rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--secondary-color);
            color: white;
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-warning {
            background: var(--warning-color);
            color: white;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
        }

        /* Table Styles */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f8fafc;
        }

        th {
            padding: 1rem 1.5rem;
            text-align: left;
            font-weight: 600;
            color: #475569;
            font-size: 0.875rem;
            border-bottom: 1px solid #e2e8f0;
        }

        td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-active {
            background: #d1fae5;
            color: #065f46;
        }

        .status-inactive {
            background: #fef3c7;
            color: #92400e;
        }

        .status-suspended {
            background: #fee2e2;
            color: #991b1b;
        }

        .action-btns {
            display: flex;
            gap: 0.5rem;
        }

        /* Forms */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #1e293b;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        /* Alert */
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border-left: 4px solid;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left-color: #10b981;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left-color: #dc2626;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-left-color: #f59e0b;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border-left-color: #3b82f6;
        }

        /* Modal */
        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: var(--card-shadow);
        }

        .modal-header {
            background: var(--primary-color);
            color: white;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
            border-bottom: none;
        }

        .modal-header .btn-close {
            filter: invert(1);
        }

        /* Mobile Menu Toggle */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: white;
            cursor: pointer;
            padding: 1rem;
        }

        /* Responsive Styles */
        @media (max-width: 1024px) {
            .sidebar {
                width: 220px;
            }

            .main-content {
                margin-left: 220px;
            }
        }

        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }

            .main-content {
                margin-left: 0;
            }

            .menu-toggle {
                display: block;
            }

            .sidebar-menu {
                display: none;
            }

            .sidebar.active .sidebar-menu {
                display: block;
            }

            .container {
                padding: 1rem;
            }

            .content-section {
                padding: 1.5rem;
            }

            .section-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .action-btns {
                flex-direction: column;
            }

            table {
                display: block;
                overflow-x: auto;
            }
        }

        @media (max-width: 576px) {
            .top-bar {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .user-info {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class='sidebar'>
            <div class='sidebar-header'>
                <h2><i class='fas fa-heartbeat'></i> <?php echo htmlspecialchars($site_name); ?></h2>
                <p>Admin Panel</p>
            </div>
            <button class="menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
            </button>
            <ul class='sidebar-menu'>
                <li><a href='dashboard.php'><i class='fas fa-th-large'></i> Dashboard</a></li>
                <li><a href='manage_doctors.php'><i class='fas fa-user-md'></i> Manage Doctors</a></li>
                <li><a href='manage_patients.php'><i class='fas fa-users'></i> Manage Patients</a></li>
                <li><a href='manage_cities.php'><i class='fas fa-city'></i> Manage Cities</a></li>
                <li><a href='manage_appointments.php'><i class='fas fa-calendar-alt'></i> Appointments</a></li>
                <li><a href='manage_specializations.php'><i class='fas fa-stethoscope'></i> Specializations</a></li>
                <li><a href='manage_content.php'><i class='fas fa-newspaper'></i> Content Management</a></li>
                <li><a href='manage_users.php' class='active'><i class='fas fa-user-cog'></i> User Management</a></li>
                <li><a href='reports.php'><i class='fas fa-chart-bar'></i> Reports</a></li>
                <li><a href='settings.php'><i class='fas fa-cog'></i> Settings</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <h1>Manage Users</h1>
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <a href="../logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <div class="container">
                <div class="page-header">
                    <h1><i class="fas fa-user-cog"></i> User Management</h1>
                </div>

                <?= $action_message ?>

                <div class="content-section">
                    <div class="section-header">
                        <h2>User Accounts</h2>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                            <i class="fas fa-plus"></i> Add New User
                        </button>
                    </div>

                    <!-- User List -->
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Created At</th>
                                    <th>Last Login</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center">No users found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>#<?= $user['user_id'] ?></td>
                                            <td><?= htmlspecialchars($user['username']) ?></td>
                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                            <td><?= ucfirst($user['role']) ?></td>
                                            <td>
                                                <span class="status-badge status-<?= $user['status'] ?>">
                                                    <?= ucfirst($user['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                            <td><?= $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never' ?></td>
                                            <td>
                                                <div class="action-btns">
                                                    <button class="btn btn-sm btn-secondary" data-bs-toggle="modal"
                                                        data-bs-target="#editModal" data-id="<?= $user['user_id'] ?>"
                                                        data-username="<?= htmlspecialchars($user['username']) ?>"
                                                        data-email="<?= htmlspecialchars($user['email']) ?>"
                                                        data-role="<?= $user['role'] ?>" data-status="<?= $user['status'] ?>">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                                        <button type="submit" name="reset_password" class="btn btn-sm btn-warning"
                                                            onclick="return confirm('Reset password to default123?');">
                                                            <i class="fas fa-key"></i> Reset
                                                        </button>
                                                    </form>
                                                    <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                                            <button type="submit" name="delete_user" class="btn btn-sm btn-danger"
                                                                onclick="return confirm('Are you sure you want to delete this user?');">
                                                                <i class="fas fa-trash"></i> Delete
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addModalLabel">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <div class="form-group">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" name="username" id="username" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" name="email" id="email" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="role" class="form-label">Role</label>
                            <select name="role" id="role" class="form-control" required>
                                <option value="admin">Admin</option>
                                <option value="doctor">Doctor</option>
                                <option value="patient">Patient</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="status" class="form-label">Status</label>
                            <select name="status" id="status" class="form-control" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Default password will be set to: <strong>default123</strong>
                        </div>
                        <button type="submit" name="add_user" class="btn btn-primary">
                            <i class="fas fa-save"></i> Add User
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="form-group">
                            <label for="edit_username" class="form-label">Username</label>
                            <input type="text" name="username" id="edit_username" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" name="email" id="edit_email" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_role" class="form-label">Role</label>
                            <select name="role" id="edit_role" class="form-control" required>
                                <option value="admin">Admin</option>
                                <option value="doctor">Doctor</option>
                                <option value="patient">Patient</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_status" class="form-label">Status</label>
                            <select name="status" id="edit_status" class="form-control" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                        <button type="submit" name="update_user" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update User
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile menu toggle
        document.getElementById('menuToggle').addEventListener('click', function () {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Populate Edit Modal
        const editModal = document.getElementById('editModal');
        editModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const username = button.getAttribute('data-username');
            const email = button.getAttribute('data-email');
            const role = button.getAttribute('data-role');
            const status = button.getAttribute('data-status');

            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_role').value = role;
            document.getElementById('edit_status').value = status;
        });
    </script>
</body>
</html>