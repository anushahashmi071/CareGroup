<?php
// doctor/settings.php - Doctor Settings Page
session_start();
require_once '../config.php';

// === AUTH CHECK ===
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../login.php");
    exit();
}

// === GET DOCTOR PROFILE ===
$doctor = getDoctorByUserId($_SESSION['user_id']);
if (!$doctor) {
    echo "<div style='text-align:center; padding:50px; font-family:Arial;'>
            <h2>Doctor Profile Not Found</h2>
            <p>User ID: " . $_SESSION['user_id'] . " is not linked to any doctor.</p>
            <a href='../logout.php'>Logout</a>
          </div>";
    exit();
}

$conn = getDBConnection();

// Get doctor details with joins for display
$stmt = $conn->prepare("
    SELECT d.*, s.specialization_name, c.city_name, u.email, u.username
    FROM doctors d 
    LEFT JOIN specializations s ON d.specialization_id = s.specialization_id 
    LEFT JOIN cities c ON d.city_id = c.city_id
    LEFT JOIN users u ON d.user_id = u.user_id
    WHERE d.doctor_id = ?
");
$stmt->bind_param("i", $doctor['doctor_id']);
$stmt->execute();
$doctor_details = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get available specializations and cities for dropdowns
$specializations = $conn->query("SELECT * FROM specializations ORDER BY specialization_name");
$cities = $conn->query("SELECT * FROM cities ORDER BY city_name");

// Initialize message variables
$success_msg = '';
$error_msg = '';

// === HANDLE PROFILE UPDATE ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $specialization_id = intval($_POST['specialization_id']);
    $city_id = intval($_POST['city_id']);
    $license_number = trim($_POST['license_number']);
    $consultation_fee = floatval($_POST['consultation_fee']);
    $experience = intval($_POST['experience']);
    $qualification = trim($_POST['qualification']);
    $bio = trim($_POST['bio']);
    $address = trim($_POST['address']);

    // Validate required fields
    if (empty($full_name) || empty($phone) || empty($email) || empty($license_number)) {
        $error_msg = "Please fill in all required fields.";
    } else {
        try {
            $conn->begin_transaction();

            // Update doctors table
            $stmt = $conn->prepare("
                UPDATE doctors 
                SET full_name = ?, phone = ?, specialization_id = ?, city_id = ?, 
                    license_number = ?, consultation_fee = ?, experience = ?, 
                    qualification = ?, bio = ?, address = ?, updated_at = NOW()
                WHERE doctor_id = ?
            ");
            $stmt->bind_param("ssiiidissi", 
                $full_name, $phone, $specialization_id, $city_id, 
                $license_number, $consultation_fee, $experience, 
                $qualification, $bio, $address, $doctor['doctor_id']
            );
            $stmt->execute();

            // Update users table email
            $stmt = $conn->prepare("UPDATE users SET email = ? WHERE user_id = ?");
            $stmt->bind_param("si", $email, $_SESSION['user_id']);
            $stmt->execute();

            $conn->commit();
            $success_msg = "Profile updated successfully!";
            
            // Refresh doctor details
            $stmt = $conn->prepare("
                SELECT d.*, s.specialization_name, c.city_name, u.email, u.username
                FROM doctors d 
                LEFT JOIN specializations s ON d.specialization_id = s.specialization_id 
                LEFT JOIN cities c ON d.city_id = c.city_id
                LEFT JOIN users u ON d.user_id = u.user_id
                WHERE d.doctor_id = ?
            ");
            $stmt->bind_param("i", $doctor['doctor_id']);
            $stmt->execute();
            $doctor_details = $stmt->get_result()->fetch_assoc();
            $stmt->close();

        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = "Error updating profile: " . $e->getMessage();
        }
    }
}

// === HANDLE PASSWORD CHANGE ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_msg = "Please fill in all password fields.";
    } elseif ($new_password !== $confirm_password) {
        $error_msg = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error_msg = "New password must be at least 6 characters long.";
    } else {
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($current_password, $user['password'])) {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->bind_param("si", $hashed_password, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $success_msg = "Password changed successfully!";
            } else {
                $error_msg = "Error changing password.";
            }
            $stmt->close();
        } else {
            $error_msg = "Current password is incorrect.";
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Doctor Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #3b82f6;
            --secondary: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #dc2626;
            --light: #f8fafc;
            --dark: #1e293b;
            --gray: #64748b;
            --border: #e2e8f0;
            --sidebar-bg: #1e293b;
            --sidebar-hover: #334155;
            --sidebar-active: #2563eb;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f1f5f9;
            color: var(--dark);
            line-height: 1.6;
        }

        .dashboard {
            min-height: 100vh;
            display: flex;
        }

        .sidebar {
            background: linear-gradient(180deg, var(--sidebar-bg) 0%, #0f172a 100%);
            padding: 2rem 0;
            position: fixed;
            width: 280px;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }

        .sidebar-header {
            padding: 0 1.5rem 2rem;
            border-bottom: 1px solid #334155;
            text-align: center;
        }

        .doctor-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 50%;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            box-shadow: var(--card-shadow);
            border: 3px solid var(--primary-light);
        }

        .sidebar-header h3 {
            margin-bottom: 0.25rem;
            font-size: 1.2rem;
            color: white;
            font-weight: 600;
        }

        .sidebar-header p {
            font-size: 0.9rem;
            color: #94a3b8;
        }

        .sidebar-menu {
            list-style: none;
            padding: 1.5rem 0;
        }

        .sidebar-menu li {
            margin: 0.25rem 0;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.5rem;
            color: #cbd5e1;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            font-weight: 500;
        }

        .sidebar-menu a:hover {
            background: var(--sidebar-hover);
            color: white;
            border-left-color: var(--primary-light);
            padding-left: 2rem;
        }

        .sidebar-menu a.active {
            background: var(--sidebar-active);
            color: white;
            border-left-color: white;
            box-shadow: inset 0 0 20px rgba(255, 255, 255, 0.1);
        }

        .sidebar-menu i {
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        .main-content {
            margin-left: 280px;
            padding: 2rem;
            flex: 1;
            width: calc(100% - 280px);
        }

        .top-bar {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .welcome-text h1 {
            color: var(--dark);
            margin-bottom: 0.25rem;
            font-size: 1.8rem;
        }

        .welcome-text p {
            color: var(--gray);
        }

        .top-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .notification-bell {
            position: relative;
            width: 45px;
            height: 45px;
            background: var(--light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            color: var(--gray);
        }

        .notification-bell:hover {
            background: var(--primary);
            color: white;
            transform: scale(1.05);
        }

        .logout-btn {
            padding: 0.75rem 1.5rem;
            background: var(--danger);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: #b91c1c;
            transform: translateY(-2px);
        }

        .settings-container {
            display: grid;
            gap: 2rem;
        }

        .settings-section {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border);
        }

        .section-header i {
            color: var(--primary);
            font-size: 1.5rem;
        }

        .section-header h2 {
            color: var(--dark);
            font-size: 1.5rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark);
        }

        .required::after {
            content: " *";
            color: var(--danger);
        }

        input, select, textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        .btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(37, 99, 235, 0.3);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 4px solid;
        }

        .alert-success {
            background: #f0fdf4;
            border-color: var(--success);
            color: #065f46;
        }

        .alert-error {
            background: #fef2f2;
            border-color: var(--danger);
            color: #991b1b;
        }

        @media (max-width: 968px) {
            .dashboard {
                flex-direction: column;
            }

            .sidebar {
                display: none;
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="doctor-avatar">
                    <i class="fas fa-user-md"></i>
                </div>
                <h3>Dr. <?php echo $doctor_details['full_name']; ?></h3>
                <p><?php echo $doctor_details['specialization_name']; ?></p>
                <p style="color: #cbd5e1; font-size: 0.8rem; margin-top: 0.5rem;">
                    <i class="fas fa-map-marker-alt"></i> <?php echo $doctor_details['city_name']; ?>
                </p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
                <li><a href="appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a></li>
                <li><a href="availability.php"><i class="fas fa-clock"></i> Availability</a></li>
                <li><a href="patients.php"><i class="fas fa-users"></i> My Patients</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="reviews.php"><i class="fas fa-star"></i> Reviews</a></li>
                <li><a href="settings.php" class="active"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="welcome-text">
                    <h1>Settings</h1>
                    <p>Manage your account settings and preferences</p>
                </div>
                <div class="top-actions">
                    <div class="notification-bell">
                        <i class="fas fa-bell"></i>
                    </div>
                    <a href="../logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <!-- Messages -->
            <?php if ($success_msg): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_msg; ?>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>

            <div class="settings-container">
                <!-- Profile Settings -->
                <section class="settings-section">
                    <div class="section-header">
                        <i class="fas fa-user-edit"></i>
                        <h2>Profile Information</h2>
                    </div>
                    <form method="POST" action="">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="full_name" class="required">Full Name</label>
                                <input type="text" id="full_name" name="full_name" 
                                       value="<?php echo htmlspecialchars($doctor_details['full_name'] ?? ''); ?>" 
                                       required>
                            </div>

                            <div class="form-group">
                                <label for="email" class="required">Email Address</label>
                                <input type="email" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($doctor_details['email'] ?? ''); ?>" 
                                       required>
                            </div>

                            <div class="form-group">
                                <label for="phone" class="required">Phone Number</label>
                                <input type="tel" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($doctor_details['phone'] ?? ''); ?>" 
                                       required>
                            </div>

                            <div class="form-group">
                                <label for="license_number" class="required">Medical License Number</label>
                                <input type="text" id="license_number" name="license_number" 
                                       value="<?php echo htmlspecialchars($doctor_details['license_number'] ?? ''); ?>" 
                                       required>
                            </div>

                            <div class="form-group">
                                <label for="specialization_id" class="required">Specialization</label>
                                <select id="specialization_id" name="specialization_id" required>
                                    <option value="">Select Specialization</option>
                                    <?php while ($spec = $specializations->fetch_assoc()): ?>
                                        <option value="<?php echo $spec['specialization_id']; ?>" 
                                            <?php echo ($doctor_details['specialization_id'] == $spec['specialization_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($spec['specialization_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="city_id" class="required">City</label>
                                <select id="city_id" name="city_id" required>
                                    <option value="">Select City</option>
                                    <?php while ($city = $cities->fetch_assoc()): ?>
                                        <option value="<?php echo $city['city_id']; ?>" 
                                            <?php echo ($doctor_details['city_id'] == $city['city_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($city['city_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="qualification" class="required">Qualification</label>
                                <input type="text" id="qualification" name="qualification" 
                                       value="<?php echo htmlspecialchars($doctor_details['qualification'] ?? ''); ?>" 
                                       required>
                            </div>

                            <div class="form-group">
                                <label for="experience" class="required">Experience (Years)</label>
                                <input type="number" id="experience" name="experience" 
                                       value="<?php echo $doctor_details['experience'] ?? ''; ?>" 
                                       min="0" max="50" required>
                            </div>

                            <div class="form-group">
                                <label for="consultation_fee" class="required">Consultation Fee ($)</label>
                                <input type="number" id="consultation_fee" name="consultation_fee" 
                                       value="<?php echo $doctor_details['consultation_fee'] ?? ''; ?>" 
                                       min="0" step="0.01" required>
                            </div>

                            <div class="form-group full-width">
                                <label for="address">Clinic Address</label>
                                <textarea id="address" name="address"><?php echo htmlspecialchars($doctor_details['address'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-group full-width">
                                <label for="bio">Professional Bio</label>
                                <textarea id="bio" name="bio" placeholder="Tell patients about your expertise and experience..."><?php echo htmlspecialchars($doctor_details['bio'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </form>
                </section>

                <!-- Password Change -->
                <section class="settings-section">
                    <div class="section-header">
                        <i class="fas fa-lock"></i>
                        <h2>Change Password</h2>
                    </div>
                    <form method="POST" action="">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="current_password" class="required">Current Password</label>
                                <input type="password" id="current_password" name="current_password" required>
                            </div>

                            <div class="form-group">
                                <label for="new_password" class="required">New Password</label>
                                <input type="password" id="new_password" name="new_password" required>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password" class="required">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>

                        <button type="submit" name="change_password" class="btn btn-success">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </form>
                </section>
            </div>
        </main>
    </div>

    <script>
        // Add real-time password confirmation check
        document.addEventListener('DOMContentLoaded', function() {
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');

            function validatePassword() {
                if (newPassword.value !== confirmPassword.value) {
                    confirmPassword.style.borderColor = 'var(--danger)';
                } else {
                    confirmPassword.style.borderColor = 'var(--success)';
                }
            }

            newPassword.addEventListener('input', validatePassword);
            confirmPassword.addEventListener('input', validatePassword);
        });
    </script>
</body>
</html>