<?php
// patient/dashboard.php
require_once '../config.php';
requireRole('patient');

// Update appointment statuses first - THIS IS IMPORTANT
updateAppointmentStatuses();

$patient = getPatientByUserId($_SESSION['user_id']);



$conn = getDBConnection();
$today = date('Y-m-d');

// Get upcoming appointments - ONLY future scheduled appointments
$stmt = $conn->prepare("
    SELECT a.*, d.full_name as doctor_name, s.specialization_name, d.consultation_fee, c.city_name
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.doctor_id
    JOIN specializations s ON d.specialization_id = s.specialization_id
    JOIN cities c ON d.city_id = c.city_id
    WHERE a.patient_id = ? 
    AND a.appointment_date >= ? 
    AND a.status = 'scheduled'
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
    LIMIT 5
");
$stmt->bind_param("is", $patient['patient_id'], $today);
$stmt->execute();
$upcoming_appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get past appointments - ALL past appointments (missed, completed, cancelled)
$stmt = $conn->prepare("
    SELECT a.*, d.full_name as doctor_name, s.specialization_name
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.doctor_id
    JOIN specializations s ON d.specialization_id = s.specialization_id
    WHERE a.patient_id = ? 
    AND (a.appointment_date < ? OR a.status IN ('completed', 'cancelled', 'missed'))
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT 5
");
$stmt->bind_param("is", $patient['patient_id'], $today);
$stmt->execute();
$past_appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM appointments WHERE patient_id = ?");
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
    <title>Dashboard - <?php echo htmlspecialchars(getSetting('site_name')); ?></title>
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
            color: #334155;
            overflow-x: hidden;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: #1e293b;
            color: white;
            padding: 0;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            background: #0f172a;
            text-align: center;
            border-bottom: 1px solid #334155;
        }

        .patient-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            border-radius: 50%;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            border: 3px solid #475569;
        }

        .sidebar-header h3 {
            margin-bottom: 0.25rem;
            color: white;
            font-size: 1.2rem;
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
            margin: 0;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.5rem;
            color: #cbd5e1;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
            font-size: 0.95rem;
        }

        .sidebar-menu a:hover {
            background: #334155;
            color: white;
            border-left-color: #6366f1;
        }

        .sidebar-menu a.active {
            background: #334155;
            color: white;
            border-left-color: #6366f1;
        }

        .sidebar-menu i {
            width: 20px;
            text-align: center;
            color: #94a3b8;
            font-size: 1.1rem;
        }

        .sidebar-menu a.active i,
        .sidebar-menu a:hover i {
            color: #6366f1;
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
            background: #f8fafc;
            min-height: 100vh;
        }

        .top-bar {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #e2e8f0;
        }

        .welcome-text h1 {
            color: #1e293b;
            margin-bottom: 0.25rem;
            font-weight: 600;
            font-size: 1.8rem;
        }

        .welcome-text p {
            color: #64748b;
            font-size: 1rem;
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
            font-size: 0.9rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.3);
        }

        .logout-btn {
            background: #ef4444;
            color: white;
        }

        .logout-btn:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.3);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            text-align: center;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 1rem;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.blue {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        .stat-icon.green {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .stat-icon.purple {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        }

        .stat-card h3 {
            font-size: 2.2rem;
            color: #1e293b;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .stat-card p {
            color: #64748b;
            font-size: 0.95rem;
            font-weight: 500;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .action-card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
            display: block;
            border: 1px solid #e2e8f0;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            border-color: #6366f1;
        }

        .action-icon {
            width: 70px;
            height: 70px;
            margin: 0 auto 1rem;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
        }

        .action-icon.blue {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        .action-icon.green {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
            font-weight: 600;
            font-size: 1.3rem;
        }

        .action-card p {
            color: #64748b;
            font-size: 0.95rem;
        }

        /* Content Sections */
        .content-section {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
            border: 1px solid #e2e8f0;
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
            font-weight: 600;
            font-size: 1.4rem;
        }

        .appointments-container {
            max-height: 400px;
            overflow-y: auto;
            padding-right: 0.5rem;
        }

        /* Scrollbar Styling */
        .appointments-container::-webkit-scrollbar {
            width: 6px;
        }

        .appointments-container::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 3px;
        }

        .appointments-container::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }

        .appointments-container::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Appointment Cards */
        .appointment-card {
            background: #f8fafc;
            border-left: 4px solid #3b82f6;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s;
            border: 1px solid #e2e8f0;
        }

        .appointment-card:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transform: translateX(5px);
        }

        .appointment-card.completed {
            border-left-color: #10b981;
        }

        .appointment-card.cancelled {
            border-left-color: #ef4444;
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
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
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
            font-weight: 600;
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
            border: 1px solid #e2e8f0;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #64748b;
            font-size: 0.9rem;
        }

        .meta-item i {
            color: #6366f1;
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

        .empty-state h3 {
            margin-bottom: 0.5rem;
            color: #475569;
        }

        /* Health Tip */
        .health-tip {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.3);
        }

        .health-tip h3 {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .status-missed {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #f59e0b;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .sidebar {
                width: 250px;
            }

            .main-content {
                margin-left: 250px;
            }
        }

        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }

            .sidebar {
                position: relative;
                width: 100%;
                display: none;
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .top-bar {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .top-actions {
                justify-content: center;
            }

            .appointment-header {
                flex-direction: column;
                gap: 1rem;
            }

            .appointment-datetime {
                text-align: left;
            }
        }

        /* Main content scrollbar */
        .main-content::-webkit-scrollbar {
            width: 8px;
        }

        .main-content::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .main-content::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        .main-content::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Sidebar scrollbar */
        .sidebar::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: #334155;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: #475569;
            border-radius: 2px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: #6366f1;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="patient-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <h3><?php echo htmlspecialchars($patient['full_name']); ?></h3>
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
                <p>Stay hydrated! Drink at least 8 glasses of water daily to keep your body functioning optimally and
                    maintain good health.</p>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h3><?php echo $total_appointments; ?></h3>
                    <p>Total Appointments</p>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <h3><?php echo count($upcoming_appointments); ?></h3>
                    <p>Upcoming Appointments</p>
                </div>
                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fas fa-history"></i>
                    </div>
                    <h3><?php echo count($past_appointments); ?></h3>
                    <p>Past Appointments</p>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="search_doctors.php" class="action-card">
                    <div class="action-icon blue">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3>Find a Doctor</h3>
                    <p>Search for specialists in your area</p>
                </a>

                <a href="book_appointment.php" class="action-card">
                    <div class="action-icon green">
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
                    <h2><i class="fas fa-calendar-day"></i> Upcoming Appointments</h2>
                    <a href="my_appointments.php" class="btn btn-primary">View All</a>
                </div>
                <div class="appointments-container">
                    <?php if (count($upcoming_appointments) > 0): ?>
                        <?php foreach ($upcoming_appointments as $appointment): ?>
                            <div class="appointment-card">
                                <div class="appointment-header">
                                    <div class="doctor-info">
                                        <div class="doctor-avatar">
                                            <i class="fas fa-user-md"></i>
                                        </div>
                                        <div class="doctor-details">
                                            <h4>Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></h4>
                                            <p><?php echo htmlspecialchars($appointment['specialization_name']); ?> •
                                                <?php echo htmlspecialchars($appointment['city_name']); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="appointment-datetime">
                                        <div class="appointment-date">
                                            <i class="far fa-calendar"></i>
                                            <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?>
                                        </div>
                                        <div class="appointment-time">
                                            <i class="far fa-clock"></i>
                                            <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="appointment-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-money-bill-wave"></i>
                                        <span>Fee: $<?php echo $appointment['consultation_fee']; ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-info-circle"></i>
                                        <span>Status: <span
                                                class="status-badge status-scheduled"><?php echo ucfirst($appointment['status']); ?></span></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h3>No Upcoming Appointments</h3>
                            <p>You don't have any scheduled appointments. Book one now!</p>
                            <a href="search_doctors.php" class="btn btn-primary" style="margin-top: 1rem;">
                                <i class="fas fa-search"></i> Find a Doctor
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Appointments -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-history"></i> Recent Appointments</h2>
                    <a href="my_appointments.php" class="btn btn-primary">View All</a>
                </div>
                <div class="appointments-container">
                    <?php if (count($past_appointments) > 0): ?>
                        <?php foreach ($past_appointments as $appointment): ?>
                            <div class="appointment-card <?php echo $appointment['status']; ?>">
                                <div class="appointment-header">
                                    <div class="doctor-info">
                                        <div class="doctor-avatar">
                                            <i class="fas fa-user-md"></i>
                                        </div>
                                        <div class="doctor-details">
                                            <h4>Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></h4>
                                            <p><?php echo htmlspecialchars($appointment['specialization_name']); ?></p>
                                        </div>
                                    </div>
                                    <div class="appointment-datetime">
                                        <div class="appointment-date">
                                            <i class="far fa-calendar"></i>
                                            <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?>
                                        </div>
                                        <div class="appointment-time">
                                            <i class="far fa-clock"></i>
                                            <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="appointment-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-info-circle"></i>
                                        <span>Status:
                                            <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                                <?php
                                                // Convert status to proper display names
                                                $status_display = [
                                                    'scheduled' => 'Scheduled',
                                                    'completed' => 'Completed',
                                                    'cancelled' => 'Cancelled',
                                                    'missed' => 'Missed Appointment'
                                                ];
                                                echo $status_display[$appointment['status']] ?? ucfirst($appointment['status']);
                                                ?>
                                            </span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <h3>No Past Appointments</h3>
                            <p>Your appointment history will appear here.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>

</html>