<?php
// admin/edit_doctor.php - Edit Doctor Profile
session_start();
require_once '../config.php';

// site name
$site_name = getSetting('site_name', 'CARE Group Medical Services');

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: manage_doctors.php");
    exit();
}

$doctor_id = intval($_GET['id']);
$message = '';
$error = '';

$conn = getDBConnection();

// Get doctor details with statistics
$stmt = $conn->prepare("
    SELECT 
        d.*, 
        u.username, 
        u.email as user_email, 
        u.status as user_status,
        COALESCE(AVG(r.rating), 0) as avg_rating,
        COUNT(DISTINCT r.review_id) as total_reviews,
        COUNT(DISTINCT a.appointment_id) as total_appointments,
        COUNT(DISTINCT a.patient_id) as total_patients
    FROM doctors d
    JOIN users u ON d.user_id = u.user_id
    LEFT JOIN reviews r ON d.doctor_id = r.doctor_id
    LEFT JOIN appointments a ON d.doctor_id = a.doctor_id
    WHERE d.doctor_id = ?
    GROUP BY d.doctor_id, d.doctor_id, u.username, u.email, u.status
");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();



if ($result->num_rows === 0) {
    header("Location: manage_doctors.php");
    exit();
}

$doctor = $result->fetch_assoc();
$stmt->close();

// Get doctor statistics for the slider
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT a.appointment_id) as total_appointments,
        COUNT(DISTINCT a.patient_id) as total_patients,
        AVG(r.rating) as avg_rating,
        COUNT(r.review_id) as total_reviews
    FROM doctors d
    LEFT JOIN appointments a ON d.doctor_id = a.doctor_id
    LEFT JOIN reviews r ON d.doctor_id = r.doctor_id
    WHERE d.doctor_id = ?
    GROUP BY d.doctor_id
");
$stats_stmt->bind_param("i", $doctor_id);
$stats_stmt->execute();
$doctor_stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $alternate_phone = sanitize($_POST['alternate_phone']);
    $specialization_id = intval($_POST['specialization_id']);
    $city_id = intval($_POST['city_id']);
    $qualification = sanitize($_POST['qualification']);
    $experience_years = intval($_POST['experience_years']);
    $registration_number = sanitize($_POST['registration_number']);
    $consultation_fee = floatval($_POST['consultation_fee']);
    $address = sanitize($_POST['address']);
    $bio = sanitize($_POST['bio']);
    $status = sanitize($_POST['status']);
    
    // Update doctor profile
    $stmt = $conn->prepare("
        UPDATE doctors 
        SET full_name = ?, 
            specialization_id = ?, 
            qualification = ?, 
            experience_years = ?, 
            registration_number = ?, 
            phone = ?, 
            alternate_phone = ?,
            email = ?, 
            address = ?, 
            city_id = ?, 
            consultation_fee = ?, 
            bio = ?,
            status = ?
        WHERE doctor_id = ?
    ");
    $stmt->bind_param("sissssssidsssi", 
        $full_name, 
        $specialization_id, 
        $qualification, 
        $experience_years, 
        $registration_number, 
        $phone, 
        $alternate_phone,
        $email, 
        $address, 
        $city_id, 
        $consultation_fee, 
        $bio,
        $status,
        $doctor_id
    );
    
    if ($stmt->execute()) {
        // Also update user email if changed
        $stmt = $conn->prepare("UPDATE users SET email = ? WHERE user_id = ?");
        $stmt->bind_param("si", $email, $doctor['user_id']);
        $stmt->execute();
        
        $message = 'Doctor profile updated successfully!';
        
         // Refresh doctor data
        $stmt = $conn->prepare("
            SELECT 
                d.*, 
                u.username, 
                u.email as user_email, 
                u.status as user_status,
                COALESCE(AVG(r.rating), 0) as avg_rating,
                COUNT(DISTINCT r.review_id) as total_reviews,
                COUNT(DISTINCT a.appointment_id) as total_appointments,
                COUNT(DISTINCT a.patient_id) as total_patients
            FROM doctors d
            JOIN users u ON d.user_id = u.user_id
            LEFT JOIN reviews r ON d.doctor_id = r.doctor_id
            LEFT JOIN appointments a ON d.doctor_id = a.doctor_id
            WHERE d.doctor_id = ?
            GROUP BY d.doctor_id, d.doctor_id, u.username, u.email, u.status
        ");
        $stmt->bind_param("i", $doctor_id);
        $stmt->execute();
        $doctor = $stmt->get_result()->fetch_assoc();
    } else {
        $error = 'Error updating doctor profile';
    }
    $stmt->close();
}

// Get cities and specializations
$cities = getAllCities();
$specializations = getAllSpecializations();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Doctor - Admin Panel</title>
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

        /* Page Content */
        .page-content {
            padding: 2rem;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            background: white;
            color: var(--dark-color);
            text-decoration: none;
            border-radius: 0.5rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 1.5rem;
            transition: var(--transition);
        }

        .back-button:hover {
            background: #f8fafc;
            transform: translateY(-2px);
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-header h1 i {
            color: var(--primary-color);
        }

        .breadcrumb {
            color: #64748b;
            font-size: 0.875rem;
        }

        .breadcrumb a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* Alerts */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #b91c1c;
            border-left: 4px solid #ef4444;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        
        /* Form Styles */
        .form-container {
            background: white;
            border-radius: 0.75rem;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        .form-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .form-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .form-header h2 i {
            color: var(--primary-color);
        }

        form {
            padding: 2rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        label span {
            color: #ef4444;
        }

        .form-control {
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }

        .btn-primary:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
        }

        /* Sidebar Card */
        .sidebar-card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        .sidebar-card h3 {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sidebar-card h3 i {
            color: var(--primary-color);
        }

        .doctor-avatar-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            margin: 1.5rem auto;
        }

        .info-box {
            padding: 0 1.5rem 1.5rem;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #64748b;
            font-size: 0.875rem;
        }

        .info-value {
            font-weight: 600;
            color: var(--dark-color);
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
            color: #d97706;
        }

        /* Danger Zone */
        .danger-zone {
            padding: 1.5rem;
            border-top: 1px solid #e2e8f0;
            background: #fef2f2;
        }

        .danger-zone h4 {
            color: #dc2626;
            font-size: 1rem;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .danger-zone p {
            color: #b91c1c;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }

        .btn-danger {
            background: #dc2626;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
            width: 100%;
            justify-content: center;
        }

        .btn-danger:hover {
            background: #b91c1c;
        }

        /* Mobile Menu Toggle */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--dark-color);
            cursor: pointer;
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
            
            .page-content {
                padding: 1rem;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
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
            
            form {
                padding: 1.5rem;
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
                <li><a href='manage_doctors.php' class='active'><i class='fas fa-user-md'></i> Manage Doctors</a></li>
                <li><a href='manage_patients.php'><i class='fas fa-users'></i> Manage Patients</a></li>
                <li><a href='manage_cities.php'><i class='fas fa-city'></i> Manage Cities</a></li>
                <li><a href='manage_appointments.php'><i class='fas fa-calendar-alt'></i> Appointments</a></li>
                <li><a href='manage_specializations.php'><i class='fas fa-stethoscope'></i> Specializations</a></li>
                <li><a href='manage_content.php'><i class='fas fa-newspaper'></i> Content Management</a></li>
                <li><a href='manage_users.php'><i class='fas fa-user-cog'></i> User Management</a></li>
                <li><a href='reports.php'><i class='fas fa-chart-bar'></i> Reports</a></li>
                <li><a href='settings.php'><i class='fas fa-cog'></i> Settings</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <h1>Edit Doctor</h1>
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

            <div class="page-content">
                <a href="manage_doctors.php" class="back-button">
                    <i class="fas fa-arrow-left"></i> Back to Doctors
                </a>

                <div class="page-header">
                    <h1><i class="fas fa-user-edit"></i> Edit Doctor Profile</h1>
                    <div class="breadcrumb">
                        <a href="dashboard.php">Dashboard</a> / 
                        <a href="manage_doctors.php">Manage Doctors</a> / 
                        Edit Doctor
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <strong><?php echo $message; ?></strong>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <strong><?php echo $error; ?></strong>
                    </div>
                <?php endif; ?>

                <div class="content-grid">
                    <!-- Form -->
                    <div class="form-container">
                        <div class="form-header">
                            <h2><i class="fas fa-edit"></i> Doctor Information</h2>
                        </div>

                        <form method="POST" action="">
                            <div class="form-grid">
                                <div class="form-group full-width">
                                    <label>Full Name <span>*</span></label>
                                    <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($doctor['full_name']); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label>Email <span>*</span></label>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($doctor['email']); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label>Phone <span>*</span></label>
                                    <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($doctor['phone']); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label>Alternate Phone</label>
                                    <input type="tel" name="alternate_phone" class="form-control" value="<?php echo htmlspecialchars($doctor['alternate_phone'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label>Registration Number <span>*</span></label>
                                    <input type="text" name="registration_number" class="form-control" value="<?php echo htmlspecialchars($doctor['registration_number']); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label>Specialization <span>*</span></label>
                                    <select name="specialization_id" class="form-control" required>
                                        <?php foreach($specializations as $spec): ?>
                                            <option value="<?php echo $spec['specialization_id']; ?>" 
                                                <?php echo $spec['specialization_id'] == $doctor['specialization_id'] ? 'selected' : ''; ?>>
                                                <?php echo $spec['specialization_name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>City <span>*</span></label>
                                    <select name="city_id" class="form-control" required>
                                        <?php foreach($cities as $city): ?>
                                            <option value="<?php echo $city['city_id']; ?>" 
                                                <?php echo $city['city_id'] == $doctor['city_id'] ? 'selected' : ''; ?>>
                                                <?php echo $city['city_name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Qualification <span>*</span></label>
                                    <input type="text" name="qualification" class="form-control" value="<?php echo htmlspecialchars($doctor['qualification']); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label>Experience (Years) <span>*</span></label>
                                    <input type="number" name="experience_years" class="form-control" value="<?php echo $doctor['experience_years']; ?>" min="0" required>
                                </div>

                                <div class="form-group">
                                    <label>Consultation Fee ($) <span>*</span></label>
                                    <input type="number" name="consultation_fee" class="form-control" value="<?php echo $doctor['consultation_fee']; ?>" min="0" step="0.01" required>
                                </div>

                                <div class="form-group">
                                    <label>Status <span>*</span></label>
                                    <select name="status" class="form-control" required>
                                        <option value="active" <?php echo $doctor['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $doctor['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>

                                <div class="form-group full-width">
                                    <label>Address <span>*</span></label>
                                    <textarea name="address" class="form-control" required><?php echo htmlspecialchars($doctor['address']); ?></textarea>
                                </div>

                                <div class="form-group full-width">
                                    <label>Bio / About Doctor</label>
                                    <textarea name="bio" class="form-control"><?php echo htmlspecialchars($doctor['bio']); ?></textarea>
                                </div>
                            </div>

                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save"></i> Update Doctor Profile
                            </button>
                        </form>
                    </div>

                    <!-- Sidebar -->
                    <div>
                        <div class="sidebar-card">
                            <h3><i class="fas fa-info-circle"></i> Doctor Details</h3>

                            <div class="doctor-avatar-large">
                                <?php echo strtoupper(substr($doctor['full_name'], 0, 1)); ?>
                            </div>

                            <div class="info-box">
                                <div class="info-row">
                                    <span class="info-label">Doctor ID</span>
                                    <span class="info-value">#<?php echo $doctor['doctor_id']; ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Username</span>
                                    <span class="info-value"><?php echo $doctor['username']; ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Status</span>
                                    <span class="status-badge status-<?php echo $doctor['status']; ?>">
                                        <?php echo ucfirst($doctor['status']); ?>
                                    </span>
                                </div>
                               <div class="info-row">
    <span class="info-label">Rating</span>
    <span class="info-value">
        <?php 
        $rating = $doctor['avg_rating'] ?? 0;
        echo number_format($rating, 1); 
        ?> 
        <i class="fas fa-star" style="color: #f59e0b;"></i>
    </span>
</div>
<div class="info-row">
    <span class="info-label">Reviews</span>
    <span class="info-value"><?php echo $doctor['total_reviews'] ?? 0; ?></span>
</div>
                                <div class="info-row">
                                    <span class="info-label">Joined</span>
                                    <span class="info-value"><?php echo date('M d, Y', strtotime($doctor['created_at'])); ?></span>
                                </div>
                            </div>

                            <div class="danger-zone">
                                <h4><i class="fas fa-exclamation-triangle"></i> Danger Zone</h4>
                                <p>Deleting this doctor will remove all associated data including appointments.</p>
                                <a href="manage_doctors.php?delete=1&id=<?php echo $doctor_id; ?>" 
                                   class="btn-danger" 
                                   onclick="return confirm('Are you sure you want to delete this doctor? This action cannot be undone!')">
                                    <i class="fas fa-trash"></i> Delete Doctor
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Mobile menu toggle
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Statistics Slider
        const sliderTrack = document.getElementById('sliderTrack');
        const sliderDots = document.getElementById('sliderDots').children;
        let currentSlide = 0;
        const slideCount = 4;

        function updateSlider() {
            sliderTrack.style.transform = `translateX(-${currentSlide * 100}%)`;
            
            // Update dots
            Array.from(sliderDots).forEach((dot, index) => {
                dot.classList.toggle('active', index === currentSlide);
            });
        }

        // Auto-slide every 5 seconds
        setInterval(() => {
            currentSlide = (currentSlide + 1) % slideCount;
            updateSlider();
        }, 5000);

        // Dot click handlers
        Array.from(sliderDots).forEach((dot, index) => {
            dot.addEventListener('click', () => {
                currentSlide = index;
                updateSlider();
            });
        });
    </script>
</body>
</html>