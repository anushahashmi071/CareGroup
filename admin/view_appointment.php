<?php
// admin/view_appointment.php - View Appointment Details for Admin
require_once '../config.php';
requireRole('admin');

// site name
$site_name = getSetting('site_name', 'CARE Group Medical Services');

if (!isset($_GET['id'])) {
    $_SESSION['error'] = 'No appointment ID provided.';
    header("Location: manage_appointments.php");
    exit();
}

$appointment_id = intval($_GET['id']);
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['message'], $_SESSION['error']);

// Get appointment details
$conn = getDBConnection();
$stmt = $conn->prepare("
    SELECT a.*, 
           p.full_name AS patient_name, 
           p.phone, 
           p.email AS patient_email,
           p.gender, 
           p.date_of_birth,
           p.blood_group,
           p.address,
           p.allergies,
           p.medical_history,
           d.full_name AS doctor_name,
           s.specialization_name,
           c.city_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    JOIN doctors d ON a.doctor_id = d.doctor_id
    JOIN specializations s ON d.specialization_id = s.specialization_id
    JOIN cities c ON p.city_id = c.city_id
    WHERE a.appointment_id = ?
");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = 'Appointment not found.';
    header("Location: manage_appointments.php");
    exit();
}

$appointment = $result->fetch_assoc();
$stmt->close();
$conn->close();

$patient_age = $appointment['date_of_birth'] ? date('Y') - date('Y', strtotime($appointment['date_of_birth'])) : 'N/A';

// Helper functions (in case not defined in config.php)
if (!function_exists('formatDate')) {
    function formatDate($date)
    {
        return $date ? date('F j, Y', strtotime($date)) : 'N/A';
    }
}
if (!function_exists('formatTime')) {
    function formatTime($time)
    {
        return $time ? date('h:i A', strtotime($time)) : 'N/A';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Details - Admin Portal</title>
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
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-header p {
            opacity: 0.9;
            margin-top: 0.5rem;
            font-size: 1rem;
        }

        /* Status Badge */
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-scheduled {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
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

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 2rem;
        }

        /* Cards */
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

        /* Patient Info */
        .patient-info-grid {
            display: grid;
            grid-template-columns: 100px 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .patient-avatar {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
        }

        .patient-details h3 {
            color: #1e293b;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .patient-meta {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-top: 1rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #64748b;
            font-size: 0.875rem;
        }

        .meta-item i {
            color: var(--primary-color);
            width: 16px;
        }

        /* Info Grid */
        .info-grid {
            display: grid;
            gap: 1rem;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
        }

        .info-label {
            color: #64748b;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-value {
            color: #1e293b;
            font-weight: 600;
        }

        /* Content Box */
        .content-box {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 10px;
            border-left: 4px solid var(--primary-color);
            margin-bottom: 1rem;
        }

        .content-box h4 {
            color: #1e293b;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .content-box p {
            color: #64748b;
            line-height: 1.6;
            margin: 0;
        }

        .warning-box {
            background: #fef2f2;
            border-left: 4px solid var(--danger-color);
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1rem;
        }

        .warning-box h4 {
            color: #991b1b;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .warning-box p {
            color: #7f1d1d;
            line-height: 1.6;
            margin: 0;
        }

        /* Sidebar Card */
        .sidebar-card {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .sidebar-card h3 {
            color: #1e293b;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .quick-info {
            background: #f0f9ff;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .quick-info-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .quick-info-item:last-child {
            margin-bottom: 0;
        }

        .quick-info-icon {
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-size: 1.2rem;
        }

        .quick-info-content h4 {
            color: #1e40af;
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }

        .quick-info-content p {
            color: #1e40af;
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
            text-align: center;
            justify-content: center;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--secondary-color);
            color: white;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #e2e8f0;
            color: #64748b;
        }

        .btn-outline:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            background: #f8fafc;
        }

        .action-buttons {
            display: grid;
            gap: 1rem;
        }

        /* Back Button */
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #64748b;
            text-decoration: none;
            margin-bottom: 1.5rem;
            padding: 0.75rem 1rem;
            background: white;
            border-radius: 8px;
            font-weight: 500;
            transition: var(--transition);
            box-shadow: var(--card-shadow);
        }

        .back-button:hover {
            background: var(--primary-color);
            color: white;
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

            .content-grid {
                grid-template-columns: 1fr;
            }

            .patient-info-grid {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .patient-avatar {
                margin: 0 auto;
            }

            .patient-meta {
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .content-section {
                padding: 1.5rem;
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

        @media print {
            .sidebar,
            .top-bar,
            .back-button,
            .action-buttons,
            .sidebar-card {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
            }
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class='sidebar'>
            <div class='sidebar-header'>
                <h2><i class='fas fa-heartbeat'></i> CARE</h2>
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
                <li><a href='manage_appointments.php' class='active'><i class='fas fa-calendar-alt'></i> Appointments</a></li>
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
                <h1>Appointment Details</h1>
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
                <a href="manage_appointments.php" class="back-button">
                    <i class="fas fa-arrow-left"></i> Back to Appointments
                </a>

                <div class="page-header">
                    <div>
                        <h1><i class="fas fa-file-medical-alt"></i> Appointment Details</h1>
                        <p>Appointment ID: #<?php echo $appointment['appointment_id']; ?> | Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></p>
                    </div>
                    <span class="status-badge status-<?php echo $appointment['status']; ?>">
                        <i class="fas fa-circle"></i>
                        <?php echo ucfirst($appointment['status']); ?>
                    </span>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <strong><?php echo htmlspecialchars($message); ?></strong>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <strong><?php echo htmlspecialchars($error); ?></strong>
                    </div>
                <?php endif; ?>

                <div class="content-grid">
                    <!-- Main Content -->
                    <div class="main">
                        <!-- Patient Information -->
                        <div class="content-section">
                            <div class="section-header">
                                <h2><i class="fas fa-user"></i> Patient Information</h2>
                            </div>

                            <div class="patient-info-grid">
                                <div class="patient-avatar">
                                    <?php echo strtoupper(substr($appointment['patient_name'], 0, 1)); ?>
                                </div>
                                <div class="patient-details">
                                    <h3><?php echo htmlspecialchars($appointment['patient_name']); ?></h3>
                                    <div class="patient-meta">
                                        <div class="meta-item">
                                            <i class="fas fa-phone"></i>
                                            <?php echo htmlspecialchars($appointment['phone'] ?? 'N/A'); ?>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-envelope"></i>
                                            <?php echo htmlspecialchars($appointment['patient_email'] ?? 'N/A'); ?>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-venus-mars"></i>
                                            <?php echo htmlspecialchars($appointment['gender'] ?? 'N/A'); ?>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-birthday-cake"></i>
                                            <?php echo $patient_age; ?> years
                                        </div>
                                        <?php if ($appointment['blood_group']): ?>
                                            <div class="meta-item">
                                                <i class="fas fa-tint"></i>
                                                <?php echo htmlspecialchars($appointment['blood_group']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="meta-item">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?php echo htmlspecialchars($appointment['city_name'] ?? 'N/A'); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="info-grid">
                                <div class="info-row">
                                    <span class="info-label">
                                        <i class="fas fa-map-marker-alt"></i> Address
                                    </span>
                                    <span class="info-value"><?php echo htmlspecialchars($appointment['address'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">
                                        <i class="fas fa-user-md"></i> Doctor
                                    </span>
                                    <span class="info-value"><?php echo htmlspecialchars($appointment['doctor_name']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">
                                        <i class="fas fa-stethoscope"></i> Specialization
                                    </span>
                                    <span class="info-value"><?php echo htmlspecialchars($appointment['specialization_name']); ?></span>
                                </div>
                            </div>

                            <?php if ($appointment['allergies']): ?>
                                <div class="warning-box">
                                    <h4><i class="fas fa-exclamation-triangle"></i> Allergies</h4>
                                    <p><?php echo nl2br(htmlspecialchars($appointment['allergies'])); ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if ($appointment['medical_history']): ?>
                                <div class="content-box">
                                    <h4><i class="fas fa-history"></i> Medical History</h4>
                                    <p><?php echo nl2br(htmlspecialchars($appointment['medical_history'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Symptoms -->
                        <div class="content-section">
                            <div class="section-header">
                                <h2><i class="fas fa-notes-medical"></i> Chief Complaints & Symptoms</h2>
                            </div>
                            <div class="content-box">
                                <p><?php echo nl2br(htmlspecialchars($appointment['symptoms'] ?? 'No symptoms provided')); ?></p>
                            </div>
                        </div>

                        <!-- Medical Records -->
                        <div class="content-section">
                            <div class="section-header">
                                <h2><i class="fas fa-stethoscope"></i> Medical Records</h2>
                            </div>
                            <div class="content-box">
                                <h4><i class="fas fa-stethoscope"></i> Diagnosis</h4>
                                <p><?php echo nl2br(htmlspecialchars($appointment['diagnosis'] ?? 'No diagnosis provided')); ?></p>
                            </div>
                            <div class="content-box">
                                <h4><i class="fas fa-prescription"></i> Prescription</h4>
                                <p><?php echo nl2br(htmlspecialchars($appointment['prescription'] ?? 'No prescription provided')); ?></p>
                            </div>
                            <div class="content-box">
                                <h4><i class="fas fa-comment-medical"></i> Additional Notes</h4>
                                <p><?php echo nl2br(htmlspecialchars($appointment['notes'] ?? 'No notes provided')); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="sidebar-card">
                        <h3><i class="fas fa-info-circle"></i> Appointment Info</h3>
                        <div class="quick-info">
                            <div class="quick-info-item">
                                <div class="quick-info-icon">
                                    <i class="fas fa-calendar"></i>
                                </div>
                                <div class="quick-info-content">
                                    <h4>Date</h4>
                                    <p><?php echo formatDate($appointment['appointment_date']); ?></p>
                                </div>
                            </div>
                            <div class="quick-info-item">
                                <div class="quick-info-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="quick-info-content">
                                    <h4>Time</h4>
                                    <p><?php echo formatTime($appointment['appointment_time']); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="action-buttons">
                            <a href="edit_appointment.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-primary">
                                <i class="fas fa-edit"></i> Edit Appointment
                            </a>
                            <button class="btn btn-outline" onclick="window.print()">
                                <i class="fas fa-print"></i> Print Report
                            </button>
                            <a href="manage_appointments.php" class="btn btn-outline">
                                <i class="fas fa-arrow-left"></i> Back to List
                            </a>
                        </div>
                    </div>
                </div>
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