<?php
// doctor/dashboard.php - Complete Doctor Dashboard
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

// If getDoctorByUserId doesn't fetch rating, manually fetch it
$stmt = $conn->prepare("
    SELECT d.*, s.specialization_name, c.city_name 
    FROM doctors d 
    LEFT JOIN specializations s ON d.specialization_id = s.specialization_id 
    LEFT JOIN cities c ON d.city_id = c.city_id 
    WHERE d.doctor_id = ?
");
$stmt->bind_param("i", $doctor['doctor_id']);
$stmt->execute();
$doctor_details = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Merge the details with existing doctor data
if ($doctor_details) {
    $doctor = array_merge($doctor, $doctor_details);
}

// Ensure rating is set, default to 0
if (!isset($doctor['rating']) || $doctor['rating'] === null) {
    $doctor['rating'] = 0.0;
}

// Get today's appointments
$today = date('Y-m-d');
$stmt = $conn->prepare("
    SELECT a.*, p.full_name as patient_name, p.phone, p.gender, p.date_of_birth, p.blood_group
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    WHERE a.doctor_id = ? AND a.appointment_date = ? AND a.status != 'cancelled'
    ORDER BY a.appointment_time ASC
");
$stmt->bind_param("is", $doctor['doctor_id'], $today);
$stmt->execute();
$today_appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get upcoming appointments (next 7 days) - FIXED: Using proper date range
$next_week = date('Y-m-d', strtotime('+7 days'));
$stmt = $conn->prepare("
    SELECT a.*, p.full_name as patient_name, p.phone, p.gender
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    WHERE a.doctor_id = ? AND a.appointment_date BETWEEN ? AND ? AND a.status = 'scheduled'
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
    LIMIT 5
");
$stmt->bind_param("iss", $doctor['doctor_id'], $today, $next_week);
$stmt->execute();
$upcoming_appointments_result = $stmt->get_result();
$upcoming_appointments = $upcoming_appointments_result ? $upcoming_appointments_result->fetch_all(MYSQLI_ASSOC) : [];

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_appointments,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        SUM(CASE WHEN appointment_date = ? THEN 1 ELSE 0 END) as today_count
    FROM appointments
    WHERE doctor_id = ?
";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("si", $today, $doctor['doctor_id']);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get scheduled count for upcoming appointments (next 7 days)
$upcoming_count_query = "
    SELECT COUNT(*) as upcoming_count
    FROM appointments
    WHERE doctor_id = ? AND appointment_date BETWEEN ? AND ? AND status = 'scheduled'
";
$stmt = $conn->prepare($upcoming_count_query);
$stmt->bind_param("iss", $doctor['doctor_id'], $today, $next_week);
$stmt->execute();
$upcoming_stats = $stmt->get_result()->fetch_assoc();

// === PATIENT REVIEWS & RATINGS ===
$rating_query = "
    SELECT 
        AVG(rating) as avg_rating,
        COUNT(rating) as total_ratings,
        COUNT(review) as total_reviews
    FROM appointments 
    WHERE doctor_id = ? AND rating IS NOT NULL AND status = 'completed'
";
$stmt = $conn->prepare($rating_query);
$stmt->bind_param("i", $doctor['doctor_id']);
$stmt->execute();
$rating_stats = $stmt->get_result()->fetch_assoc();

$avg_rating = round((float)($rating_stats['avg_rating'] ?? 0), 1);
$total_ratings = $rating_stats['total_ratings'];
$total_reviews = $rating_stats['total_reviews'];

$reviews_query = "
    SELECT a.rating, a.review, p.full_name AS patient_name, a.appointment_date
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    WHERE a.doctor_id = ? 
      AND a.rating IS NOT NULL 
      AND a.status = 'completed'
    ORDER BY a.appointment_date DESC
    LIMIT 5
";
$stmt = $conn->prepare($reviews_query);
$stmt->bind_param("i", $doctor['doctor_id']);
$stmt->execute();
$result = $stmt->get_result();
$recent_reviews = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Sync rating with DB
updateDoctorRating($doctor['doctor_id'], $conn);

// Reload updated rating
$rating_reload_query = "SELECT rating, total_ratings FROM doctors WHERE doctor_id = ?";
$reload_stmt = $conn->prepare($rating_reload_query);
$reload_stmt->bind_param("i", $doctor['doctor_id']);
$reload_stmt->execute();
$updated_rating = $reload_stmt->get_result()->fetch_assoc();
$reload_stmt->close();

$doctor['rating'] = $updated_rating['rating'];
$doctor['total_ratings'] = $updated_rating['total_ratings'];

// Use for avg_rating as well if needed
$avg_rating = $doctor['rating'];

// Get monthly earnings (if needed)
$monthly_earnings = $stats['completed'] * $doctor['consultation_fee'];

// Get unread notifications count for doctor
$unread_notifications_count = getUnreadNotificationsCount($doctor['doctor_id'], 'doctor');

// Get recent notifications
$recent_notifications = getNotifications($doctor['doctor_id'], 'doctor', 5);

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - CARE Group</title>
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
            --hover-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
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

        /* Simple Notification Bell Styles */
        .notification-bell {
            position: relative;
            width: 45px;
            height: 45px;
            background: var(--light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray);
            text-decoration: none;
            transition: all 0.3s;
        }

        .notification-bell:hover {
            background: var(--primary);
            color: white;
            transform: scale(1.05);
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: bold;
            border: 2px solid white;
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
            box-shadow: 0 5px 15px rgba(220, 38, 38, 0.3);
        }

        .quick-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .quick-stat-card {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            box-shadow: var(--card-shadow);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .quick-stat-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transform: rotate(45deg);
            transition: all 0.6s;
        }

        .quick-stat-card:hover::before {
            left: 100%;
        }

        .quick-stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }

        .quick-stat-card h2 {
            font-size: 3rem;
            margin-bottom: 0.5rem;
            position: relative;
        }

        .quick-stat-card p {
            opacity: 0.9;
            font-size: 1.1rem;
            position: relative;
        }

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
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, var(--primary) 0%, var(--primary-light) 100%);
        }

        .stat-card h3 {
            color: var(--gray);
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .stat-card p {
            font-size: 2rem;
            font-weight: bold;
            color: var(--dark);
        }

        .content-section {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border);
        }

        .section-header h2 {
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            padding: 0.75rem 1.5rem;
            background: var(--primary);
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

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(37, 99, 235, 0.3);
        }

        .appointment-card {
            background: var(--light);
            border-left: 4px solid var(--primary);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s;
        }

        .appointment-card:hover {
            box-shadow: var(--card-shadow);
            transform: translateX(5px);
        }

        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .patient-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .patient-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            font-weight: bold;
        }

        .patient-details h4 {
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .patient-details p {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .appointment-time {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary);
            font-weight: 600;
            font-size: 1.1rem;
        }

        .appointment-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-info {
            background: var(--primary);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
            color: var(--primary-light);
        }

        /* Reviews Section */
        .review-card {
            background: var(--light);
            border-left: 4px solid var(--warning);
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            transition: all 0.3s;
        }

        .review-card:hover {
            transform: translateX(5px);
            box-shadow: var(--card-shadow);
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .review-stars {
            color: var(--warning);
        }

        .review-text {
            color: var(--gray);
            font-size: 0.95rem;
            margin: 0.5rem 0;
            line-height: 1.5;
        }

        .review-meta {
            font-size: 0.8rem;
            color: #94a3b8;
        }

        .debug-info {
            background: #fef2f2;
            border: 1px solid #fecaca;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-family: monospace;
            font-size: 0.9rem;
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

            .quick-stats {
                grid-template-columns: 1fr;
            }

            .appointment-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
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
                <h3>Dr. <?php echo $doctor['full_name']; ?></h3>
                <p><?php echo $doctor['specialization_name']; ?></p>
                <p style="color: #cbd5e1; font-size: 0.8rem; margin-top: 0.5rem;">
                    <i class="fas fa-map-marker-alt"></i> <?php echo $doctor['city_name']; ?>
                </p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php" class="active"><i class="fas fa-th-large"></i> Dashboard</a></li>
                <li><a href="appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a></li>
                <li><a href="availability.php"><i class="fas fa-clock"></i> Availability</a></li>
                <li><a href="patients.php"><i class="fas fa-users"></i> My Patients</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="reviews.php"><i class="fas fa-star"></i> Reviews</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="welcome-text">
                    <h1>Welcome back, Dr. <?php echo explode(' ', $doctor['full_name'])[0]; ?>!</h1>
                    <p><?php echo date('l, F j, Y'); ?></p>
                </div>
                <div class="top-actions">
                    <!-- Simple Notification Bell with Link -->
                    <a href="notifications.php" class="notification-bell">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_notifications_count > 0): ?>
                            <span class="notification-badge"><?= $unread_notifications_count ?></span>
                        <?php endif; ?>
                    </a>

                    <a href="../logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="quick-stats">
                <div class="quick-stat-card">
                    <h2><?php echo count($today_appointments); ?></h2>
                    <p><i class="fas fa-calendar-day"></i> Today's Appointments</p>
                </div>
                <div class="quick-stat-card"
                    style="background: linear-gradient(135deg, var(--success) 0%, #059669 100%);">
                    <h2><?php echo $upcoming_stats['upcoming_count'] ?? 0; ?></h2>
                    <p><i class="fas fa-clock"></i> Upcoming Appointments</p>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Appointments</h3>
                    <p><?php echo $stats['total_appointments']; ?></p>
                </div>

                <div class="stat-card">
                    <h3>Completed</h3>
                    <p><?php echo $stats['completed']; ?></p>
                </div>

                <div class="stat-card">
                    <h3>Scheduled</h3>
                    <p><?php echo $stats['scheduled']; ?></p>
                </div>

                <div class="stat-card">
                    <h3>Rating</h3>
                    <p><?php echo $total_ratings; ?> <i class="fas fa-star"
                            style="color: var(--warning); font-size: 1rem;"></i></p>
                </div>
            </div>

            <!-- Today's Appointments -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-calendar-day"></i> Today's Appointments</h2>
                    <span style="color: var(--gray);"><?php echo date('F j, Y'); ?></span>
                </div>

                <?php if (count($today_appointments) > 0): ?>
                    <?php foreach ($today_appointments as $apt): ?>
                        <div class="appointment-card">
                            <div class="appointment-header">
                                <div class="patient-info">
                                    <div class="patient-avatar">
                                        <?php echo strtoupper(substr($apt['patient_name'], 0, 1)); ?>
                                    </div>
                                    <div class="patient-details">
                                        <h4><?php echo $apt['patient_name']; ?></h4>
                                        <p>
                                            <i class="fas fa-phone"></i> <?php echo $apt['phone']; ?> |
                                            <i class="fas fa-venus-mars"></i> <?php echo $apt['gender']; ?>
                                            <?php if ($apt['blood_group']): ?>
                                                | <i class="fas fa-tint"></i> <?php echo $apt['blood_group']; ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="appointment-time">
                                    <i class="fas fa-clock"></i>
                                    <?php echo formatTime($apt['appointment_time']); ?>
                                </div>
                            </div>

                            <?php if ($apt['symptoms']): ?>
                                <div style="background: white; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                                    <strong style="color: var(--dark);"><i class="fas fa-notes-medical"></i> Symptoms:</strong>
                                    <p style="color: var(--gray); margin-top: 0.5rem;"><?php echo $apt['symptoms']; ?></p>
                                </div>
                            <?php endif; ?>

                            <div class="appointment-actions">
                                <a href="view_appointment.php?id=<?php echo $apt['appointment_id']; ?>" class="btn btn-info">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                                <?php if ($apt['status'] == 'scheduled'): ?>
                                    <button class="btn btn-success" onclick="markComplete(<?php echo $apt['appointment_id']; ?>)">
                                        <i class="fas fa-check"></i> Mark Complete
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No appointments today</h3>
                        <p>You have no scheduled appointments for today</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Upcoming Appointments -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-calendar-week"></i> Upcoming Appointments</h2>
                    <a href="appointments.php" class="btn-primary">View All</a>
                </div>

                <?php if (count($upcoming_appointments) > 0): ?>
                    <?php foreach ($upcoming_appointments as $apt): ?>
                        <div class="appointment-card">
                            <div class="appointment-header">
                                <div class="patient-info">
                                    <div class="patient-avatar">
                                        <?php echo strtoupper(substr($apt['patient_name'], 0, 1)); ?>
                                    </div>
                                    <div class="patient-details">
                                        <h4><?php echo $apt['patient_name']; ?></h4>
                                        <p>
                                            <i class="fas fa-phone"></i> <?php echo $apt['phone']; ?> |
                                            <i class="fas fa-venus-mars"></i> <?php echo $apt['gender']; ?>
                                        </p>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div class="appointment-time">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo formatDate($apt['appointment_date']); ?>
                                    </div>
                                    <div class="appointment-time" style="margin-top: 0.5rem; font-size: 0.95rem;">
                                        <i class="fas fa-clock"></i>
                                        <?php echo formatTime($apt['appointment_time']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-check"></i>
                        <h3>No upcoming appointments</h3>
                        <p>You have no scheduled appointments in the next 7 days</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Patient Reviews -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-star"></i> Recent Patient Reviews</h2>
                    <a href="reviews.php" class="btn-primary">View All (<?php echo $total_ratings; ?>)</a>
                </div>

                <?php if (count($recent_reviews) > 0): ?>
                    <?php foreach ($recent_reviews as $rev): ?>
                        <div class="review-card">
                            <div class="review-header">
                                <strong><?= htmlspecialchars($rev['patient_name']) ?></strong>
                                <div class="review-stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star<?= $i <= $rev['rating'] ? '' : '-o' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <?php if (!empty(trim($rev['review']))): ?>
                                <p class="review-text"><?= nl2br(htmlspecialchars($rev['review'])) ?></p>
                            <?php else: ?>
                                <p class="review-text" style="color:#94a3b8; font-style:italic;">
                                    No written review
                                </p>
                            <?php endif; ?>
                            <div class="review-meta">
                                <i class="fas fa-calendar"></i> <?= formatDate($rev['appointment_date']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-comment-slash"></i>
                        <h3>No ratings yet</h3>
                        <p>Patient ratings will appear after completed appointments.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function markComplete(appointmentId) {
            if (confirm('Mark this appointment as completed?')) {
                window.location.href = 'complete_appointment.php?id=' + appointmentId;
            }
        }

        // Mark notification as read
        function markNotificationAsRead(notificationId) {
            fetch('mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ notification_id: notificationId })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove new badge and update count
                        const notificationBadge = document.querySelector('.notification-badge');
                        if (notificationBadge) {
                            const currentCount = parseInt(notificationBadge.textContent);
                            if (currentCount > 1) {
                                notificationBadge.textContent = currentCount - 1;
                            } else {
                                notificationBadge.remove();
                            }
                        }
                    }
                });
        }

        // Time ago helper function
        function timeAgo(timestamp) {
            const now = new Date();
            const past = new Date(timestamp);
            const diff = Math.floor((now - past) / 1000); // difference in seconds

            if (diff < 60) return 'Just now';
            if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
            if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
            if (diff < 2592000) return Math.floor(diff / 86400) + ' days ago';
            return past.toLocaleDateString();
        }

        // Debug info - remove in production
        console.log('Upcoming appointments:', <?php echo json_encode($upcoming_appointments); ?>);
        console.log('Upcoming count:', <?php echo $upcoming_stats['upcoming_count'] ?? 0; ?>);
    </script>
</body>

</html>