<?php
// admin/settings.php - Complete System Settings
session_start();
require_once '../config.php';

// site name
$site_name = getSetting('site_name', 'CARE Group Medical Services');

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

/* -------------------------------------------------
   1. Get the current tab (default = system)
   ------------------------------------------------- */
$tab = $_GET['tab'] ?? 'system';

/* -------------------------------------------------
   2. Load current settings (fallback to defaults)
   ------------------------------------------------- */
$site_name      = getSetting('site_name',      'CARE Group Medical Services');
$contact_email  = getSetting('contact_email',  'info@caregroup.com');
$contact_phone  = getSetting('contact_phone',  '+91 1800-123-4567');
$timezone       = getSetting('timezone',       'Asia/Kolkata');
$date_format    = getSetting('date_format',    'm/d/Y');

/* -------------------------------------------------
   3. SAVE – only when the form is posted
   ------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_system'])) {

    $updates = [
        'site_name'      => sanitize($_POST['site_name'] ?? ''),
        'contact_email'  => sanitize($_POST['contact_email'] ?? ''),
        'contact_phone'  => sanitize($_POST['contact_phone'] ?? ''),
        'timezone'       => sanitize($_POST['timezone'] ?? 'Asia/Kolkata'),
        'date_format'    => sanitize($_POST['date_format'] ?? 'm/d/Y')
    ];

    $conn = getDBConnection();
    $stmt = $conn->prepare("
        INSERT INTO settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = ?
    ");

    $ok = true;
    foreach ($updates as $key => $value) {
        $stmt->bind_param('sss', $key, $value, $value);
        if (!$stmt->execute()) { $ok = false; }
    }
    $stmt->close();
    $conn->close();

    if ($ok) {
        $_SESSION['message'] = 'System settings saved successfully.';
    } else {
        $_SESSION['error']   = 'Failed to save one or more settings.';
    }

    // Refresh the page with the same tab
    header('Location: settings.php?tab=' . $tab);
    exit;
}

$message = '';
$error = '';
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';

$user = getUserDetails($_SESSION['user_id']);

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);

    $conn = getDBConnection();

    // Check if username/email already exists for other users
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE (username = ? OR email = ?) AND user_id != ?");
    $stmt->bind_param("ssi", $username, $email, $_SESSION['user_id']);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {
        $error = 'Username or email already exists';
    } else {
        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE user_id = ?");
        $stmt->bind_param("ssi", $username, $email, $_SESSION['user_id']);

        if ($stmt->execute()) {
            $message = 'Profile updated successfully!';
            $_SESSION['username'] = $username;
            $user = getUserDetails($_SESSION['user_id']);
        } else {
            $error = 'Error updating profile';
        }
    }

    $stmt->close();
    $conn->close();
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $error = 'New passwords do not match';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif (password_verify($current_password, $user['password'])) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $conn = getDBConnection();
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->bind_param("si", $hashed_password, $_SESSION['user_id']);

        if ($stmt->execute()) {
            $message = 'Password changed successfully!';
        } else {
            $error = 'Error changing password';
        }

        $stmt->close();
        $conn->close();
    } else {
        $error = 'Current password is incorrect';
    }
}

// Get system statistics
$conn = getDBConnection();
$stats = [
    'total_users' => $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'],
    'active_doctors' => $conn->query("SELECT COUNT(*) as count FROM doctors WHERE status = 'active'")->fetch_assoc()['count'],
    'total_patients' => $conn->query("SELECT COUNT(*) as count FROM patients")->fetch_assoc()['count'],
    'total_appointments' => $conn->query("SELECT COUNT(*) as count FROM appointments")->fetch_assoc()['count'],
    'pending_appointments' => $conn->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'scheduled'")->fetch_assoc()['count'],
    'completed_appointments' => $conn->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'completed'")->fetch_assoc()['count'],
];
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Admin Panel</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4cc9f0;
            --info-color: #4895ef;
            --warning-color: #f72585;
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

        .page-header p {
            color: #64748b;
            font-size: 1.1rem;
            margin-top: 0.5rem;
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

        /* Tabs */
        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 1rem;
        }

        .tab {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            color: #64748b;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: transparent;
        }

        .tab i {
            font-size: 1.1rem;
        }

        .tab:hover {
            background: #f1f5f9;
            color: #1e293b;
        }

        .tab.active {
            background: var(--primary-color);
            color: white;
        }

        /* Settings Card */
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
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Forms */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #1e293b;
            font-weight: 500;
        }

        .form-group label span {
            color: #dc2626;
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

        .form-control:disabled {
            background: #f8fafc;
            color: #94a3b8;
            cursor: not-allowed;
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

        .btn-danger:hover {
            background: #b91c1c;
        }

        /* Info Box */
        .info-box {
            background: #f0f9ff;
            border-left: 4px solid #3b82f6;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .info-box h3 {
            color: #1e40af;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-box p {
            color: #1e40af;
            line-height: 1.6;
            margin: 0;
        }

        /* Danger Zone */
        .danger-zone {
            background: #fef2f2;
            border: 2px solid #fecaca;
            padding: 1.5rem;
            border-radius: 10px;
            margin-top: 2rem;
        }

        .danger-zone h3 {
            color: #dc2626;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .danger-zone p {
            color: #991b1b;
            margin-bottom: 1rem;
        }

        /* Statistics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            text-align: center;
            border-left: 4px solid var(--primary-color);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #64748b;
            font-weight: 500;
        }

        /* Activity Log */
        .activity-log {
            max-height: 400px;
            overflow-y: auto;
        }

        .activity-item {
            padding: 1rem;
            border-left: 3px solid var(--primary-color);
            background: #f8fafc;
            border-radius: 8px;
            margin-bottom: 0.75rem;
        }

        .activity-item h4 {
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .activity-item p {
            color: #64748b;
            font-size: 0.9rem;
            margin: 0;
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

            .tabs {
                flex-direction: column;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .content-section {
                padding: 1.5rem;
            }
        }

        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

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
                <li><a href='manage_users.php'><i class='fas fa-user-cog'></i> User Management</a></li>
                <li><a href='reports.php'><i class='fas fa-chart-bar'></i> Reports</a></li>
                <li><a href='settings.php' class='active'><i class='fas fa-cog'></i> Settings</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <h1>Settings</h1>
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <span><?php echo $_SESSION['username']; ?></span>
                    <a href="../logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <div class="container">
                <div class="page-header">
                    <h1><i class="fas fa-cog"></i> System Settings</h1>
                    <p>Manage your admin account and system configurations</p>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <strong><?php echo $message; ?></strong>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <strong><?php echo $error; ?></strong>
                    </div>
                <?php endif; ?>

                <!-- Tabs -->
                <div class="tabs">
                    <a href="?tab=profile" class="tab <?php echo $tab == 'profile' ? 'active' : ''; ?>">
                        <i class="fas fa-user"></i> Profile
                    </a>
                    <a href="?tab=password" class="tab <?php echo $tab == 'password' ? 'active' : ''; ?>">
                        <i class="fas fa-lock"></i> Password
                    </a>
                    <a href="?tab=system" class="tab <?php echo $tab == 'system' ? 'active' : ''; ?>">
                        <i class="fas fa-cogs"></i> System
                    </a>
                    <a href="?tab=statistics" class="tab <?php echo $tab == 'statistics' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar"></i> Statistics
                    </a>
                    <a href="?tab=activity" class="tab <?php echo $tab == 'activity' ? 'active' : ''; ?>">
                        <i class="fas fa-history"></i> Activity Log
                    </a>
                </div>

                <!-- Profile Tab -->
                <?php if ($tab == 'profile'): ?>
                    <div class="content-section">
                        <div class="section-header">
                            <h2><i class="fas fa-user"></i> Profile Settings</h2>
                        </div>

                        <div class="info-box">
                            <h3><i class="fas fa-info-circle"></i> Account Information</h3>
                            <p>Your account was created on <?php echo formatDate($user['created_at']); ?>. Last login:
                                <?php echo $user['last_login'] ? formatDate($user['last_login']) : 'Never'; ?>
                            </p>
                        </div>

                        <form method="POST" action="">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Username <span>*</span></label>
                                    <input type="text" name="username" class="form-control"
                                        value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label>Email <span>*</span></label>
                                    <input type="email" name="email" class="form-control"
                                        value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label>Role</label>
                                    <input type="text" class="form-control" value="<?php echo ucfirst($user['role']); ?>"
                                        disabled>
                                </div>

                                <div class="form-group">
                                    <label>Status</label>
                                    <input type="text" class="form-control" value="<?php echo ucfirst($user['status']); ?>"
                                        disabled>
                                </div>
                            </div>

                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Password Tab -->
                <?php if ($tab == 'password'): ?>
                    <div class="content-section">
                        <div class="section-header">
                            <h2><i class="fas fa-lock"></i> Change Password</h2>
                        </div>

                        <div class="info-box">
                            <h3><i class="fas fa-shield-alt"></i> Password Security</h3>
                            <p>• Use at least 6 characters<br>
                                • Include numbers and special characters<br>
                                • Don't use common passwords<br>
                                • Change your password regularly</p>
                        </div>

                        <form method="POST" action="">
                            <div class="form-group">
                                <label>Current Password <span>*</span></label>
                                <input type="password" name="current_password" class="form-control"
                                    placeholder="Enter current password" required>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label>New Password <span>*</span></label>
                                    <input type="password" name="new_password" class="form-control"
                                        placeholder="Enter new password" minlength="6" required>
                                </div>

                                <div class="form-group">
                                    <label>Confirm New Password <span>*</span></label>
                                    <input type="password" name="confirm_password" class="form-control"
                                        placeholder="Confirm new password" minlength="6" required>
                                </div>
                            </div>

                            <button type="submit" name="change_password" class="btn btn-primary">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- System Tab -->
                <?php if ($tab === 'system'): ?>
                    <div class="content-section">
                        <div class="section-header">
                            <h2><i class="fas fa-cogs"></i> System Settings</h2>
                        </div>

                        <form method="POST" action="settings.php?tab=system">
                            <div class="form-grid">
                                <!-- Site Name -->
                                <div class="form-group full-width">
                                    <label>Site Name</label>
                                    <input type="text" name="site_name" class="form-control"
                                           value="<?php echo htmlspecialchars($site_name); ?>" required>
                                </div>

                                <!-- Contact Email -->
                                <div class="form-group">
                                    <label>Contact Email</label>
                                    <input type="email" name="contact_email" class="form-control"
                                           value="<?php echo htmlspecialchars($contact_email); ?>" required>
                                </div>

                                <!-- Contact Phone -->
                                <div class="form-group">
                                    <label>Contact Phone</label>
                                    <input type="tel" name="contact_phone" class="form-control"
                                           value="<?php echo htmlspecialchars($contact_phone); ?>" required>
                                </div>

                                <!-- Timezone -->
                                <div class="form-group">
                                    <label>Timezone</label>
                                    <select name="timezone" class="form-control" required>
                                        <option value="Asia/Kolkata"   <?php echo $timezone === 'Asia/Kolkata'   ? 'selected' : ''; ?>>Asia/Kolkata (IST)</option>
                                        <option value="Asia/Dubai"     <?php echo $timezone === 'Asia/Dubai'     ? 'selected' : ''; ?>>Asia/Dubai (GST)</option>
                                        <option value="America/New_York" <?php echo $timezone === 'America/New_York' ? 'selected' : ''; ?>>America/New_York (EST)</option>
                                    </select>
                                </div>

                                <!-- Date Format -->
                                <div class="form-group">
                                    <label>Date Format</label>
                                    <select name="date_format" class="form-control" required>
                                        <option value="d/m/Y" <?php echo $date_format === 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                        <option value="m/d/Y" <?php echo $date_format === 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                                        <option value="Y-m-d" <?php echo $date_format === 'Y-m-d' ? 'selected' : ''; ?>>YYYY‑MM‑DD</option>
                                    </select>
                                </div>
                            </div>

                            <button type="submit" name="update_system" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Settings
                            </button>
                        </form>

                        <div class="danger-zone">
                            <h3><i class="fas fa-exclamation-triangle"></i> Danger Zone</h3>
                            <p>These actions are permanent and cannot be undone. Please be careful.</p>
                            <button class="btn btn-danger"
                                    onclick="if(confirm('Are you sure? This will clear all cache.')) alert('Cache cleared!')">
                                <i class="fas fa-trash"></i> Clear Cache
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Statistics Tab -->
                <?php if ($tab == 'statistics'): ?>
                    <div class="content-section">
                        <div class="section-header">
                            <h2><i class="fas fa-chart-bar"></i> System Statistics</h2>
                        </div>

                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                                <div class="stat-label">Total Users</div>
                            </div>

                            <div class="stat-card">
                                <div class="stat-number"><?php echo $stats['active_doctors']; ?></div>
                                <div class="stat-label">Active Doctors</div>
                            </div>

                            <div class="stat-card">
                                <div class="stat-number"><?php echo $stats['total_patients']; ?></div>
                                <div class="stat-label">Total Patients</div>
                            </div>

                            <div class="stat-card">
                                <div class="stat-number"><?php echo $stats['total_appointments']; ?></div>
                                <div class="stat-label">Total Appointments</div>
                            </div>

                            <div class="stat-card">
                                <div class="stat-number"><?php echo $stats['pending_appointments']; ?></div>
                                <div class="stat-label">Pending Appointments</div>
                            </div>

                            <div class="stat-card">
                                <div class="stat-number"><?php echo $stats['completed_appointments']; ?></div>
                                <div class="stat-label">Completed</div>
                            </div>
                        </div>

                        <div class="info-box">
                            <h3><i class="fas fa-database"></i> Database Information</h3>
                            <p><strong>Database Size:</strong> Calculating...<br>
                                <strong>Last Backup:</strong> Never<br>
                                <strong>PHP Version:</strong> <?php echo phpversion(); ?><br>
                                <strong>Server:</strong> <?php echo $_SERVER['SERVER_SOFTWARE']; ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Activity Log Tab -->
                <?php if ($tab == 'activity'): ?>
                    <div class="content-section">
                        <div class="section-header">
                            <h2><i class="fas fa-history"></i> Activity Log</h2>
                        </div>

                        <div class="activity-log">
                            <div class="activity-item">
                                <h4>Admin Login</h4>
                                <p><i class="fas fa-clock"></i> <?php echo date('F j, Y - g:i A'); ?> | User:
                                    <?php echo $user['username']; ?>
                                </p>
                            </div>

                            <div class="activity-item">
                                <h4>System Settings Updated</h4>
                                <p><i class="fas fa-clock"></i> <?php echo date('F j, Y - g:i A', strtotime('-2 hours')); ?>
                                    | Profile settings modified</p>
                            </div>

                            <div class="activity-item">
                                <h4>New Doctor Added</h4>
                                <p><i class="fas fa-clock"></i> <?php echo date('F j, Y - g:i A', strtotime('-1 day')); ?> |
                                    Dr. Sarah Johnson added to system</p>
                            </div>

                            <div class="activity-item">
                                <h4>Password Changed</h4>
                                <p><i class="fas fa-clock"></i> <?php echo date('F j, Y - g:i A', strtotime('-3 days')); ?>
                                    | Admin password updated</p>
                            </div>

                            <div class="activity-item">
                                <h4>Database Backup</h4>
                                <p><i class="fas fa-clock"></i> <?php echo date('F j, Y - g:i A', strtotime('-1 week')); ?>
                                    | Automatic backup completed</p>
                            </div>
                        </div>

                        <div style="margin-top: 1.5rem; text-align: center;">
                            <button class="btn btn-primary">
                                <i class="fas fa-download"></i> Export Activity Log
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Mobile menu toggle
        document.getElementById('menuToggle').addEventListener('click', function () {
            document.querySelector('.sidebar').classList.toggle('active');
        });
    </script>
</body>
</html>