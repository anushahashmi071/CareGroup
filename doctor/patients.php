<?php
// doctor/patients.php - View Doctor's Patients
// session_start();
require_once '../config.php';
requireRole('doctor');

$doctor = getDoctorByUserId($_SESSION['user_id']);
if (!$doctor) {
    die("Doctor profile not found.");
}

$conn = getDBConnection();

// Search and filter parameters
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? 'all';
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build WHERE clause for filtering
$where_conditions = ["a.doctor_id = ?"];
$params = [$doctor['doctor_id']];
$param_types = "i";

if ($search) {
    $where_conditions[] = "(p.full_name LIKE ? OR p.email LIKE ? OR p.phone LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $param_types .= "sss";
}

if ($status_filter !== 'all') {
    $where_conditions[] = "a.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Count total patients
$count_query = "
    SELECT COUNT(DISTINCT p.patient_id) as total
    FROM patients p
    JOIN appointments a ON p.patient_id = a.patient_id
    WHERE $where_clause
";

$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$total_patients = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = max(1, ceil($total_patients / $per_page));
$stmt->close();

// Get patients with their latest appointment and stats - SIMPLIFIED QUERY
$patients_query = "
    SELECT DISTINCT
        p.patient_id,
        p.full_name,
        p.email,
        p.phone,
        p.gender,
        p.date_of_birth,
        p.blood_group,
        p.allergies,
        p.medical_history,
        c.city_name,
        (
            SELECT COUNT(*) 
            FROM appointments a2 
            WHERE a2.patient_id = p.patient_id AND a2.doctor_id = ?
        ) as total_appointments,
        (
            SELECT COUNT(*) 
            FROM appointments a3 
            WHERE a3.patient_id = p.patient_id AND a3.doctor_id = ? AND a3.status = 'completed'
        ) as completed_appointments,
        (
            SELECT MAX(appointment_date) 
            FROM appointments a4 
            WHERE a4.patient_id = p.patient_id AND a4.doctor_id = ?
        ) as last_visit
    FROM patients p
    JOIN appointments a ON p.patient_id = a.patient_id
    LEFT JOIN cities c ON p.city_id = c.city_id
    WHERE a.doctor_id = ?
    ORDER BY last_visit DESC
    LIMIT ? OFFSET ?
";

// Prepare the statement
$stmt = $conn->prepare($patients_query);

// Bind parameters - doctor_id repeated 4 times for the subqueries and main query, plus limit and offset
$doctor_id = $doctor['doctor_id'];
$stmt->bind_param("iiiiii", 
    $doctor_id,  // for first subquery
    $doctor_id,  // for second subquery  
    $doctor_id,  // for third subquery
    $doctor_id,  // for main WHERE clause
    $per_page,   // for LIMIT
    $offset      // for OFFSET
);

$stmt->execute();
$patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get overall stats - SIMPLIFIED
$stats_query = "
    SELECT 
        COUNT(DISTINCT p.patient_id) as total_patients,
        COUNT(DISTINCT CASE WHEN a.status = 'completed' THEN p.patient_id END) as active_patients,
        COUNT(DISTINCT CASE WHEN a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN p.patient_id END) as recent_patients
    FROM patients p
    JOIN appointments a ON p.patient_id = a.patient_id
    WHERE a.doctor_id = ?
";

$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $doctor['doctor_id']);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Calculate average visits separately
$avg_query = "
    SELECT AVG(visit_count) as avg_visits
    FROM (
        SELECT COUNT(*) as visit_count
        FROM appointments 
        WHERE doctor_id = ? AND status = 'completed'
        GROUP BY patient_id
    ) as patient_visits
";

$stmt = $conn->prepare($avg_query);
$stmt->bind_param("i", $doctor['doctor_id']);
$stmt->execute();
$avg_result = $stmt->get_result()->fetch_assoc();
$stats['avg_visits'] = $avg_result['avg_visits'] ?? 0;
$stmt->close();

$conn->close();

// Helper function to calculate age from date of birth
function calculateAge($dob) {
    if (!$dob) return 'N/A';
    $birthdate = new DateTime($dob);
    $today = new DateTime();
    $age = $today->diff($birthdate);
    return $age->y;
}

// Helper function to format last visit date
function formatLastVisit($date) {
    if (!$date) return 'Never';
    
    $visit_date = new DateTime($date);
    $today = new DateTime();
    $interval = $today->diff($visit_date);
    
    if ($interval->days == 0) return 'Today';
    if ($interval->days == 1) return 'Yesterday';
    if ($interval->days < 7) return $interval->days . ' days ago';
    if ($interval->days < 30) return floor($interval->days / 7) . ' weeks ago';
    
    return $visit_date->format('M j, Y');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Patients - Dr. <?= htmlspecialchars($doctor['full_name']) ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    :root {
        --primary: #1a73e8;
        --primary-dark: #0d47a1;
        --primary-light: #64b5f6;
        --secondary: #43a047;
        --secondary-dark: #2e7d32;
        --secondary-light: #81c784;
        --accent: #ff9800;
        --accent-dark: #f57c00;
        --accent-light: #ffb74d;
        --dark: #263238;
        --light: #f5f7fa;
        --gray: #607d8b;
        --light-gray: #cfd8dc;
        --success: #4caf50;
        --warning: #ff9800;
        --danger: #f44336;
        --gradient: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%);
        --medical-gradient: linear-gradient(135deg, #1a73e8 0%, #43a047 100%);
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
        margin: 0;
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
        border-left: 4px solid var(--primary);
    }

    .welcome-text h1 {
        color: var(--dark);
        margin-bottom: 0.25rem;
        font-size: 1.8rem;
        font-weight: 700;
    }

    .welcome-text p {
        color: var(--gray);
        margin: 0;
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
        background: #d32f2f;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(244, 67, 54, 0.3);
    }

    .content-section {
        background: white;
        padding: 2rem;
        border-radius: 12px;
        box-shadow: var(--card-shadow);
        margin-bottom: 2rem;
        border: 1px solid var(--light-gray);
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid var(--light-gray);
    }

    .section-header h2 {
        color: var(--dark);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 600;
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
        background: var(--primary);
        color: white;
    }

    .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(26, 115, 232, 0.3);
    }

    .btn-success {
        background: var(--secondary);
        color: white;
    }

    .btn-success:hover {
        background: var(--secondary-dark);
        transform: translateY(-2px);
    }

    .btn-outline {
        background: white;
        border: 2px solid var(--light-gray);
        color: var(--gray);
    }

    .btn-outline:hover {
        background: var(--light);
        border-color: var(--primary);
        color: var(--primary);
    }

    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }

    .search-bar {
        display: flex;
        gap: 1rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }

    .search-input {
        flex: 1;
        padding: 0.75rem 1rem;
        border: 2px solid var(--light-gray);
        border-radius: 8px;
        font-size: 1rem;
        min-width: 200px;
        transition: all 0.3s;
    }

    .search-input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.1);
    }

    .patient-card {
        background: white;
        border: 1px solid var(--light-gray);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        transition: all 0.3s;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        position: relative;
        overflow: hidden;
    }

    .patient-card:hover {
        box-shadow: var(--hover-shadow);
        transform: translateY(-5px);
    }

    .patient-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: linear-gradient(180deg, var(--primary) 0%, var(--primary-light) 100%);
    }

    .patient-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }

    .patient-avatar {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
        font-weight: bold;
        margin-right: 1rem;
        box-shadow: var(--card-shadow);
    }

    .patient-info {
        flex: 1;
    }

    .patient-info h4 {
        color: var(--dark);
        margin-bottom: 0.25rem;
        font-weight: 600;
    }

    .patient-meta {
        display: flex;
        gap: 1rem;
        color: var(--gray);
        font-size: 0.875rem;
        margin-bottom: 0.5rem;
        flex-wrap: wrap;
    }

    .patient-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
        margin: 1rem 0;
        padding: 1rem;
        background: #f8fafc;
        border-radius: 8px;
        border: 1px solid var(--light-gray);
    }

    .stat-item {
        text-align: center;
    }

    .stat-number {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 0.25rem;
    }

    .stat-label {
        font-size: 0.875rem;
        color: var(--gray);
    }

    .patient-actions {
        display: flex;
        gap: 0.5rem;
        justify-content: flex-end;
    }

    .badge {
        padding: 0.35rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .badge-success { 
        background: #e8f5e8; 
        color: #2e7d32; 
        border: 1px solid #c8e6c9;
    }
    .badge-warning { 
        background: #fff3e0; 
        color: #e65100; 
        border: 1px solid #ffe0b2;
    }
    .badge-info { 
        background: #e3f2fd; 
        color: #1565c0; 
        border: 1px solid #bbdefb;
    }

    .empty-state {
        text-align: center;
        padding: 3rem;
        color: var(--gray);
    }

    .empty-state i {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.5;
        color: var(--primary);
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: var(--card-shadow);
        text-align: center;
        border-left: 4px solid var(--primary);
        transition: all 0.3s;
        position: relative;
        overflow: hidden;
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--hover-shadow);
    }

    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 0.5rem;
    }

    .stat-label {
        color: var(--gray);
        font-weight: 500;
    }

    .pagination {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        margin-top: 2rem;
        flex-wrap: wrap;
    }

    .page-link {
        padding: 0.5rem 1rem;
        background: white;
        color: var(--dark);
        border: 1px solid var(--light-gray);
        border-radius: 8px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s;
    }

    .page-link:hover {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }

    .page-link.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
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

        .patient-header {
            flex-direction: column;
            gap: 1rem;
        }

        .patient-actions {
            width: 100%;
            justify-content: flex-start;
        }

        .search-bar {
            flex-direction: column;
        }

        .search-input {
            min-width: 100%;
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
                <h3>Dr. <?= htmlspecialchars($doctor['full_name']) ?></h3>
                <p><?= htmlspecialchars($doctor['specialization_name']) ?></p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
                <li><a href="appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a></li>
                <li><a href="availability.php"><i class="fas fa-clock"></i> Availability</a></li>
                <li><a href="patients.php" class="active"><i class="fas fa-users"></i> My Patients</a></li>
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
                    <h1>My Patients</h1>
                    <p>Manage and view your patient records</p>
                </div>
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>

            <!-- Stats Overview -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['total_patients'] ?? 0 ?></div>
                    <div class="stat-label">Total Patients</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['active_patients'] ?? 0 ?></div>
                    <div class="stat-label">Active Patients</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['recent_patients'] ?? 0 ?></div>
                    <div class="stat-label">Recent (30 days)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= round($stats['avg_visits'] ?? 0, 1) ?></div>
                    <div class="stat-label">Avg Visits/Patient</div>
                </div>
            </div>

            <!-- Patient List -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-users"></i> Patient List</h2>
                </div>

                <?php if (empty($patients)): ?>
                    <div class="empty-state">
                        <i class="fas fa-user-injured"></i>
                        <h3>No Patients Found</h3>
                        <p>You haven't treated any patients yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($patients as $patient): ?>
                        <div class="patient-card">
                            <div class="patient-header">
                                <div style="display: flex; align-items: flex-start;">
                                    <div class="patient-avatar">
                                        <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
                                    </div>
                                    <div class="patient-info">
                                        <h4><?= htmlspecialchars($patient['full_name']) ?></h4>
                                        <div class="patient-meta">
                                            <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone']) ?></span>
                                            <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($patient['email']) ?></span>
                                            <span><i class="fas fa-venus-mars"></i> <?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span>
                                            <span><i class="fas fa-birthday-cake"></i> <?= calculateAge($patient['date_of_birth']) ?> years</span>
                                        </div>
                                        <?php if ($patient['city_name']): ?>
                                            <div class="patient-meta">
                                                <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($patient['city_name']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="badge badge-info">
                                        Last Visit: <?= formatLastVisit($patient['last_visit']) ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Patient Stats -->
                            <div class="patient-stats">
                                <div class="stat-item">
                                    <div class="stat-number"><?= $patient['total_appointments'] ?></div>
                                    <div class="stat-label">Total Visits</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number"><?= $patient['completed_appointments'] ?></div>
                                    <div class="stat-label">Completed</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number">
                                        <?php if ($patient['blood_group']): ?>
                                            <?= htmlspecialchars($patient['blood_group']) ?>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </div>
                                    <div class="stat-label">Blood Group</div>
                                </div>
                            </div>

                            <!-- Medical Information -->
                            <?php if ($patient['allergies'] || $patient['medical_history']): ?>
                                <div style="margin: 1rem 0; padding: 1rem; background: #f0f9ff; border-radius: 8px; border-left: 4px solid #0ea5e9;">
                                    <h5 style="color: #0369a1; margin-bottom: 0.5rem;"><i class="fas fa-file-medical"></i> Medical Information</h5>
                                    <?php if ($patient['allergies']): ?>
                                        <p style="margin: 0.25rem 0; font-size: 0.9rem;">
                                            <strong>Allergies:</strong> <?= htmlspecialchars($patient['allergies']) ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if ($patient['medical_history']): ?>
                                        <p style="margin: 0.25rem 0; font-size: 0.9rem;">
                                            <strong>Medical History:</strong> <?= htmlspecialchars(substr($patient['medical_history'], 0, 100)) ?><?= strlen($patient['medical_history']) > 100 ? '...' : '' ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Action Buttons -->
                            <div class="patient-actions">
                                <a href="patient_details.php?patient_id=<?= $patient['patient_id'] ?>" class="btn btn-outline btn-sm">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                                <a href="appointments.php?patient_id=<?= $patient['patient_id'] ?>" class="btn btn-outline btn-sm">
                                    <i class="fas fa-calendar-alt"></i> Appointments
                                </a>
                                <a href="add_appointment.php?patient_id=<?= $patient['patient_id'] ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus"></i> New Appointment
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>