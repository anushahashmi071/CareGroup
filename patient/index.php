<?php
// patient/dashboard.php
require_once '../config.php';
requireRole('patient');

$patient = getPatientByUserId($_SESSION['user_id']);

if (!$patient) {
    die("Patient profile not found");
}

$conn = getDBConnection();

// Get upcoming appointments
$today = date('Y-m-d');
$stmt = $conn->prepare("
    SELECT a.*, d.full_name as doctor_name, s.specialization_name, d.consultation_fee, c.city_name
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.doctor_id
    JOIN specializations s ON d.specialization_id = s.specialization_id
    JOIN cities c ON d.city_id = c.city_id
    WHERE a.patient_id = ? AND a.appointment_date >= ? AND a.status != 'cancelled'
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
$stmt->bind_param("is", $patient['patient_id'], $today);
$stmt->execute();
$upcoming_appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get past appointments
$stmt = $conn->prepare("
    SELECT a.*, d.full_name as doctor_name, s.specialization_name
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.doctor_id
    JOIN specializations s ON d.specialization_id = s.specialization_id
    WHERE a.patient_id = ? AND (a.appointment_date < ? OR a.status = 'completed')
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT 5
");
$stmt->bind_param("is", $patient['patient_id'], $today);
$stmt->execute();
$past_appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats_query = "SELECT COUNT(*) as total FROM appointments WHERE patient_id = ?";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $patient['patient_id']);
$stmt->execute();
$total_appointments = $stmt->get_result()->fetch_assoc()['total'];

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - CARE Group</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8fafc;
        }

        .dashboard {
            display: grid;
            grid-template-columns: 260px 1fr;
            min-height: 100vh;
        }

        .sidebar {
            background: linear-gradient(180deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 2rem 0;
        }

        .sidebar-header {
            padding: 0 1.5rem 2rem;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            text-align: center;
        }

        .patient-avatar {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #10b981;
        }

        .sidebar-header h3 {
            margin-bottom: 0.25rem;
        }

        .sidebar-header p {
            font-size: 0.9rem;
            opacity: 0.8;
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
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.15);
            color: white;
        }

        .sidebar-menu i {
            width: 20px;
            text-align: center;
        }

        .main-content {
            padding: 2rem;
        }

        .top-bar {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .welcome-text h1 {
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .welcome-text p {
            color: #64748b;
        }

        .top-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn {
            padding: 0.75rem 1.5rem;
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

        .btn-primary {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
        }

        .logout-btn {
            background: #dc2626;
            color: white;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .action-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .action-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 1rem;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
        }

        .action-icon.green {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .action-icon.blue {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        .action-icon.purple {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        }

        .action-icon.orange {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .action-card h3 {
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .action-card p {
            color: #64748b;
            font-size: 0.9rem;
        }

        .content-section {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f5f9;
        }

        .section-header h2 {
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .appointment-card {
            background: #f8fafc;
            border-left: 4px solid #10b981;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s;
        }

        .appointment-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .appointment-card.upcoming {
            border-left-color: #3b82f6;
        }

        .appointment-card.completed {
            border-left-color: #10b981;
        }

        .appointment-card.cancelled {
            border-left-color: #dc2626;
        }

        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .doctor-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .doctor-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .doctor-details h4 {
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .doctor-details p {
            color: #64748b;
            font-size: 0.9rem;
        }

        .appointment-datetime {
            text-align: right;
        }

        .appointment-date {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #3b82f6;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .appointment-time {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #64748b;
            font-size: 0.9rem;
        }

        .appointment-meta {
            display: flex;
            gap: 2rem;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #64748b;
        }

        .meta-item i {
            color: #10b981;
        }

        .appointment-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }

        .btn-danger {
            background: #dc2626;
            color: white;
        }

        .btn-info {
            background: #3b82f6;
            color: white;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
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

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #64748b;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .health-tip {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }

        .health-tip h3 {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        @media (max-width: 968px) {
            .dashboard {
                grid-template-columns: 1fr;
            }

            .sidebar {
                display: none;
            }

            .appointment-header {
                flex-direction: column;
                gap: 1rem;
            }

            .appointment-datetime {
                text-align: left;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="patient-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <h3><?php echo $patient['full_name']; ?></h3>
                <p>Patient ID: #<?php echo $patient['patient_id']; ?></p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php" class="active"><i class="fas fa-th-large"></i> Dashboard</a></li>
                <li><a href="search_doctors.php"><i class="fas fa-search"></i> Find Doctors</a></li>
                <li><a href="my_appointments.php"><i class="fas fa-calendar-alt"></i> My Appointments</a></li>
                <li><a href="medical_records.php"><i class="fas fa-file-medical"></i> Medical Records</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="health_tips.php"><i class="fas fa-heartbeat"></i> Health Tips</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="welcome-text">
                    <h1>Welcome, <?php echo explode(' ', $patient['full_name'])[0]; ?>!</h1>
                    <p>Hope you're feeling well today</p>
                </div>
                <div class="top-actions">
                    <a href="search_doctors.php" class="btn btn-primary">
                        <i class="fas fa-search"></i> Find a Doctor
                    </a>
                    <a href="../logout.php" class="btn logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <!-- Health Tip -->
            <div class="health-tip">
                <h3><i class="fas fa-lightbulb"></i> Daily Health Tip</h3>
                <p>Stay hydrated! Drink at least 8 glasses of water daily to keep your body functioning optimally and maintain good health.</p>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="search_doctors.php" class="action-card">
                    <div class="action-icon green">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3>Find a Doctor</h3>
                    <p>Search for specialists in your area</p>
                </a>

                <a href="book_appointment.php" class="action-card">
                    <div class="action-icon blue">
                        <i class="fas fa-calendar-plus"></i>
                    </div>
                    <h3>Book Appointment</h3>
                    <p>Schedule your next consultation</p>
                </a>

                <a href="my_appointments.php" class="action-card">
                    <div class="action-icon purple">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h3>My Appointments</h3>
                    <p>View all your appointments</p>
                </a>

                <a href="medical_records.php" class="action-card">
                    <div class="action-icon orange">
                        <i class="fas fa-file-medical"></i>
                    </div>
                    <h3>Medical Records</h3>
                    <p>Access your health records</p>
                </a>
            </div>

            <!-- Upcoming Appointments -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-calendar-check"></i> Upcoming Appointments</h2>
                    <a href="my_appointments.php" class="btn btn-primary btn-sm">View All</a>
                </div>

                <?php if (count($upcoming_appointments) > 0): ?>
                    <?php foreach($upcoming_appointments as $apt): ?>
                    <div class="appointment-card upcoming">
                        <div class="appointment-header">
                            <div class="doctor-info">
                                <div class="doctor-avatar">
                                    <i class="fas fa-user-md"></i>
                                </div>
                                <div class="doctor-details">
                                    <h4>Dr. <?php echo $apt['doctor_name']; ?></h4>
                                    <p><?php echo $apt['specialization_name']; ?></p>
                                </div>
                            </div>
                            <div class="appointment-datetime">
                                <div class="appointment-date">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo formatDate($apt['appointment_date']); ?>
                                </div>
                                <div class="appointment-time">
                                    <i class="fas fa-clock"></i>
                                    <?php echo formatTime($apt['appointment_time']); ?>
                                </div>
                            </div>
                        </div>

                        <div class="appointment-meta">
                            <div class="meta-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo $apt['city_name']; ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-rupee-sign"></i>
                                <span>$<?php echo number_format($apt['consultation_fee'], 2); ?></span>
                            </div>
                            <div class="meta-item">
                                <span class="status-badge status-<?php echo $apt['status']; ?>">
                                    <?php echo ucfirst($apt['status']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="appointment-actions">
                            <button class="btn btn-info btn-sm">
                                <i class="fas fa-eye"></i> View Details
                            </button>
                            <button class="btn btn-danger btn-sm">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No Upcoming Appointments</h3>
                        <p>You don't have any scheduled appointments</p>
                        <a href="search_doctors.php" class="btn btn-primary" style="margin-top: 1rem;">
                            <i class="fas fa-search"></i> Find a Doctor
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Past Appointments -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-history"></i> Recent Appointments</h2>
                </div>

                <?php if (count($past_appointments) > 0): ?>
                    <?php foreach($past_appointments as $apt): ?>
                    <div class="appointment-card completed">
                        <div class="appointment-header">
                            <div class="doctor-info">
                                <div class="doctor-avatar">
                                    <i class="fas fa-user-md"></i>
                                </div>
                                <div class="doctor-details">
                                    <h4>Dr. <?php echo $apt['doctor_name']; ?></h4>
                                    <p><?php echo $apt['specialization_name']; ?></p>
                                </div>
                            </div>
                            <div class="appointment-datetime">
                                <div class="appointment-date">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo formatDate($apt['appointment_date']); ?>
                                </div>
                                <div class="appointment-time">
                                    <i class="fas fa-clock"></i>
                                    <?php echo formatTime($apt['appointment_time']); ?>
                                </div>
                            </div>
                        </div>

                        <div class="appointment-meta">
                            <div class="meta-item">
                                <span class="status-badge status-<?php echo $apt['status']; ?>">
                                    <?php echo ucfirst($apt['status']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="appointment-actions">
                            <button class="btn btn-info btn-sm">
                                <i class="fas fa-eye"></i> View Details
                            </button>
                            <?php if ($apt['status'] == 'completed'): ?>
                            <button class="btn btn-primary btn-sm">
                                <i class="fas fa-star"></i> Rate Doctor
                            </button>
                            <button class="btn btn-primary btn-sm">
                                <i class="fas fa-redo"></i> Book Again
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <h3>No Past Appointments</h3>
                        <p>Your appointment history will appear here</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>