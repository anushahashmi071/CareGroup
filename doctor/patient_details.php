<?php
// doctor/patient_details.php - View Patient Details
require_once '../config.php';
require_once '../helpers.php';
requireRole('doctor');

$doctor = getDoctorByUserId($_SESSION['user_id']);
if (!$doctor) {
    die("Doctor profile not found.");
}

if (!isset($_GET['patient_id'])) {
    header("Location: patients.php");
    exit();
}

$patient_id = intval($_GET['patient_id']);
$conn = getDBConnection();

// Add the safe_html function
if (!function_exists('safe_html')) {
    function safe_html($value)
    {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// Get patient details
$patient_query = "
    SELECT p.*, c.city_name
    FROM patients p
    LEFT JOIN cities c ON p.city_id = c.city_id
    WHERE p.patient_id = ?
";

$stmt = $conn->prepare($patient_query);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient) {
    header("Location: patients.php");
    exit();
}

// Verify patient has appointments with this doctor
$verify_query = "SELECT COUNT(*) as count FROM appointments WHERE patient_id = ? AND doctor_id = ?";
$stmt = $conn->prepare($verify_query);
$stmt->bind_param("ii", $patient_id, $doctor['doctor_id']);
$stmt->execute();
$has_access = $stmt->get_result()->fetch_assoc()['count'] > 0;
$stmt->close();

if (!$has_access) {
    header("Location: patients.php");
    exit();
}

// Get patient statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_appointments,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
        SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled_appointments,
        MIN(appointment_date) as first_visit,
        MAX(appointment_date) as last_visit,
        AVG(rating) as avg_rating
    FROM appointments 
    WHERE patient_id = ? AND doctor_id = ?
";

$stmt = $conn->prepare($stats_query);
$stmt->bind_param("ii", $patient_id, $doctor['doctor_id']);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get recent appointments (last 5)
$appointments_query = "
    SELECT a.*, d.full_name as doctor_name
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.doctor_id
    WHERE a.patient_id = ? AND a.doctor_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT 5
";

$stmt = $conn->prepare($appointments_query);
$stmt->bind_param("ii", $patient_id, $doctor['doctor_id']);
$stmt->execute();
$recent_appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get medical records
$medical_records = [];

try {
    // Get records from medical_records table
    $records_query = "
        SELECT 
            mr.*, 
            d.full_name as doctor_name,
            a.appointment_date,
            'medical_record' as record_type
        FROM medical_records mr
        JOIN doctors d ON mr.doctor_id = d.doctor_id
        LEFT JOIN appointments a ON mr.appointment_id = a.appointment_id
        WHERE mr.patient_id = ? AND mr.doctor_id = ?
        ORDER BY mr.created_at DESC
        LIMIT 10
    ";

    $stmt = $conn->prepare($records_query);
    if ($stmt) {
        $stmt->bind_param("ii", $patient_id, $doctor['doctor_id']);
        if ($stmt->execute()) {
            $medical_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        $stmt->close();
    }

    // Also get medical records from appointments table
    $appointment_records_query = "
    SELECT 
        a.appointment_id as record_id,
        a.appointment_date,
        a.diagnosis,
        a.prescription,
        a.notes,
        a.created_at,
        d.full_name as doctor_name,
        'appointment' as record_type
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.doctor_id
    WHERE a.patient_id = ? AND a.doctor_id = ? 
    AND (a.diagnosis IS NOT NULL OR a.prescription IS NOT NULL OR a.notes IS NOT NULL)
    ORDER BY a.appointment_date DESC
    LIMIT 10    
    ";

    $stmt = $conn->prepare($appointment_records_query);
    if ($stmt) {
        $stmt->bind_param("ii", $patient_id, $doctor['doctor_id']);
        if ($stmt->execute()) {
            $appointment_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            // Merge both sets of records
            $medical_records = array_merge($medical_records, $appointment_records);
        }
        $stmt->close();
    }

    // Sort all records by date (newest first)
    usort($medical_records, function ($a, $b) {
        $dateA = $a['appointment_date'] ?? $a['created_at'] ?? '';
        $dateB = $b['appointment_date'] ?? $b['created_at'] ?? '';
        return strtotime($dateB) - strtotime($dateA);
    });

} catch (Exception $e) {
    error_log("Medical records query error: " . $e->getMessage());
    $medical_records = [];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Details - Dr. <?= htmlspecialchars($doctor['full_name']) ?></title>
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
            padding: 1.5rem 2rem;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
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
            box-shadow: 0 5px 15px rgba(37, 99, 235, 0.3);
        }

        .btn-outline {
            background: white;
            border: 2px solid var(--border);
            color: var(--gray);
        }

        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .content-section {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            transition: all 0.3s;
        }

        .content-section:hover {
            box-shadow: var(--hover-shadow);
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
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .patient-header {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 2px solid var(--border);
        }

        .patient-avatar-large {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            font-weight: bold;
            box-shadow: var(--card-shadow);
        }

        .patient-info h1 {
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-size: 2rem;
        }

        .patient-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--gray);
        }

        .meta-item i {
            color: var(--primary);
            width: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
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
        }

        .stat-card:hover {
            transform: translateY(-5px);
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

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .info-card {
            background: var(--light);
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 4px solid var(--primary);
            transition: all 0.3s;
        }

        .info-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--card-shadow);
        }

        .info-card h4 {
            color: var(--dark);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border);
        }

        .info-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .info-label {
            color: var(--gray);
            font-weight: 500;
        }

        .info-value {
            color: var(--dark);
            font-weight: 600;
        }

        .appointment-card,
        .record-card {
            background: var(--light);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            transition: all 0.3s;
        }

        .appointment-card:hover,
        .record-card:hover {
            box-shadow: var(--hover-shadow);
            transform: translateY(-2px);
        }

        .badge {
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-primary {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
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
        }

        .content-box {
            background: var(--light);
            padding: 1.5rem;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
            margin-bottom: 1rem;
        }

        .warning-box {
            background: #fef2f2;
            border-left: 4px solid var(--danger);
            padding: 1.5rem;
            border-radius: 8px;
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
                text-align: center;
            }

            .patient-meta {
                grid-template-columns: 1fr;
            }

            .info-grid {
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
                <h3>Dr. <?= htmlspecialchars($doctor['full_name']) ?></h3>
                <p><?= htmlspecialchars($doctor['specialization_name']) ?></p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
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
                    <h1>Patient Details</h1>
                    <p>Complete patient information and medical history</p>
                </div>
                <div>
                    <a href="patients.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Back to Patients
                    </a>
                    <a href="add_appointment.php?patient_id=<?= $patient_id ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> New Appointment
                    </a>
                </div>
            </div>

            <!-- Patient Header -->
            <div class="content-section">
                <div class="patient-header">
                    <div class="patient-avatar-large">
                        <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
                    </div>
                    <div class="patient-info">
                        <h1><?= htmlspecialchars($patient['full_name']) ?></h1>
                        <div class="patient-meta">
                            <div class="meta-item">
                                <i class="fas fa-phone"></i>
                                <?= htmlspecialchars($patient['phone']) ?>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-envelope"></i>
                                <?= htmlspecialchars($patient['email']) ?>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-venus-mars"></i>
                                <?= htmlspecialchars($patient['gender'] ?? 'N/A') ?>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-birthday-cake"></i>
                                <?= calculateAge($patient['date_of_birth']) ?> years
                            </div>
                            <?php if ($patient['blood_group']): ?>
                                <div class="meta-item">
                                    <i class="fas fa-tint"></i>
                                    Blood Group: <?= htmlspecialchars($patient['blood_group']) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($patient['city_name']): ?>
                                <div class="meta-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?= htmlspecialchars($patient['city_name']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Patient Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['total_appointments'] ?? 0 ?></div>
                        <div class="stat-label">Total Visits</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['completed_appointments'] ?? 0 ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['scheduled_appointments'] ?? 0 ?></div>
                        <div class="stat-label">Scheduled</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= round($stats['avg_rating'] ?? 0, 1) ?></div>
                        <div class="stat-label">Avg Rating</div>
                    </div>
                </div>

                <!-- Patient Information Grid -->
                <div class="info-grid">
                    <!-- Personal Information -->
                    <div class="info-card">
                        <h4><i class="fas fa-user"></i> Personal Information</h4>
                        <div class="info-item">
                            <span class="info-label">Full Name</span>
                            <span class="info-value"><?= htmlspecialchars($patient['full_name']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Date of Birth</span>
                            <span class="info-value"><?= formatDate($patient['date_of_birth']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Age</span>
                            <span class="info-value"><?= calculateAge($patient['date_of_birth']) ?> years</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Gender</span>
                            <span class="info-value"><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Blood Group</span>
                            <span class="info-value"><?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></span>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="info-card">
                        <h4><i class="fas fa-address-book"></i> Contact Information</h4>
                        <div class="info-item">
                            <span class="info-label">Phone</span>
                            <span class="info-value"><?= htmlspecialchars($patient['phone']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Email</span>
                            <span class="info-value"><?= htmlspecialchars($patient['email']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Address</span>
                            <span class="info-value"><?= htmlspecialchars($patient['address'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">City</span>
                            <span class="info-value"><?= htmlspecialchars($patient['city_name'] ?? 'N/A') ?></span>
                        </div>
                    </div>

                    <!-- Medical Information -->
                    <div class="info-card">
                        <h4><i class="fas fa-file-medical"></i> Medical Information</h4>
                        <?php if ($patient['allergies']): ?>
                            <div class="info-item">
                                <span class="info-label">Allergies</span>
                                <span class="info-value"><?= htmlspecialchars($patient['allergies']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($patient['medical_history']): ?>
                            <div class="info-item">
                                <span class="info-label">Medical History</span>
                                <span class="info-value"><?= nl2br(htmlspecialchars($patient['medical_history'])) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="info-item">
                            <span class="info-label">First Visit</span>
                            <span class="info-value"><?= formatDate($stats['first_visit']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Last Visit</span>
                            <span class="info-value"><?= formatDate($stats['last_visit']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Appointments -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-calendar-alt"></i> Recent Appointments</h2>
                    <a href="appointments.php?patient_id=<?= $patient_id ?>" class="btn btn-outline btn-sm">
                        View All Appointments
                    </a>
                </div>

                <?php if (empty($recent_appointments)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h4>No Appointments Found</h4>
                        <p>This patient hasn't had any appointments with you yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_appointments as $appointment): ?>
                        <div class="appointment-card">
                            <div
                                style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                                <div>
                                    <h5 style="color: var(--dark); margin-bottom: 0.5rem;">
                                        <?= formatDateTime($appointment['appointment_date'], $appointment['appointment_time']) ?>
                                    </h5>
                                    <?php if ($appointment['symptoms']): ?>
                                        <p style="color: var(--gray); margin: 0;"><?= htmlspecialchars($appointment['symptoms']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <span class="badge <?= getStatusBadge($appointment['status']) ?>">
                                    <?= ucfirst($appointment['status']) ?>
                                </span>
                            </div>
                            <?php if ($appointment['diagnosis'] || $appointment['prescription']): ?>
                                <div style="background: white; padding: 1rem; border-radius: 8px; margin-top: 1rem;">
                                    <?php if ($appointment['diagnosis']): ?>
                                        <p style="margin: 0.5rem 0;"><strong>Diagnosis:</strong>
                                            <?= htmlspecialchars($appointment['diagnosis']) ?></p>
                                    <?php endif; ?>
                                    <?php if ($appointment['prescription']): ?>
                                        <p style="margin: 0.5rem 0;"><strong>Prescription:</strong>
                                            <?= htmlspecialchars($appointment['prescription']) ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Medical Records Section -->
            <div class="content-section">
                <!-- Medical History Display -->
                <div class="section-header" style="margin-top: 2rem;">
                    <h2><i class="fas fa-history"></i> Medical History</h2>
                    <div>
                        <span class="badge badge-primary">
                            <?= count($medical_records) ?> Records
                        </span>
                    </div>
                </div>

                <?php if (empty($medical_records)): ?>
                    <div class="empty-state">
                        <i class="fas fa-file-medical"></i>
                        <h4>No Medical Records Found</h4>
                        <p>No medical records found for this patient. Records from appointments will appear here.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($medical_records as $record): ?>
                        <div class="record-card">
                            <div
                                style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                                <div>
                                    <h5 style="color: var(--dark); margin-bottom: 0.5rem;">
                                        <?php if ($record['record_type'] === 'appointment'): ?>
                                            <i class="fas fa-calendar-check"></i> Appointment Record
                                        <?php else: ?>
                                            <i class="fas fa-file-medical-alt"></i> Medical Record
                                        <?php endif; ?>
                                        from <?= formatDate($record['appointment_date'] ?? $record['created_at']) ?>
                                    </h5>
                                    <?php if (isset($record['doctor_name'])): ?>
                                        <p style="color: var(--gray); margin: 0; font-size: 0.9rem;">
                                            <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($record['doctor_name']) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <span
                                    class="badge <?= $record['record_type'] === 'appointment' ? 'badge-success' : 'badge-primary' ?>">
                                    <?php if ($record['record_type'] === 'appointment'): ?>
                                        <i class="fas fa-calendar-alt"></i> Appointment
                                    <?php else: ?>
                                        <i class="fas fa-file-medical"></i> Record
                                    <?php endif; ?>
                                </span>
                            </div>

                            <!-- Diagnosis -->
                            <?php if (!empty($record['diagnosis'])): ?>
                                <div class="content-box"
                                    style="background: #fef3c7; border-left: 4px solid #f59e0b; margin-bottom: 1rem;">
                                    <h4
                                        style="color: #92400e; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-stethoscope"></i> Diagnosis
                                    </h4>
                                    <p style="color: #92400e; line-height: 1.6; margin: 0;">
                                        <?= nl2br(htmlspecialchars($record['diagnosis'])) ?>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <!-- Prescription -->
                            <?php if (!empty($record['prescription'])): ?>
                                <div class="content-box"
                                    style="background: #d1fae5; border-left: 4px solid #10b981; margin-bottom: 1rem;">
                                    <h4
                                        style="color: #065f46; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-prescription"></i> Prescription
                                    </h4>
                                    <p style="color: #065f46; line-height: 1.6; margin: 0;">
                                        <?= nl2br(htmlspecialchars($record['prescription'])) ?>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <!-- Treatment -->
                            <?php if (!empty($record['treatment'])): ?>
                                <div class="content-box"
                                    style="background: #dbeafe; border-left: 4px solid #3b82f6; margin-bottom: 1rem;">
                                    <h4
                                        style="color: #1e40af; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-hand-holding-medical"></i> Treatment
                                    </h4>
                                    <p style="color: #1e40af; line-height: 1.6; margin: 0;">
                                        <?= nl2br(htmlspecialchars($record['treatment'])) ?>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <!-- Additional Notes -->
                            <?php if (!empty($record['notes'])): ?>
                                <div class="content-box"
                                    style="background: #f3f4f6; border-left: 4px solid #6b7280; margin-bottom: 1rem;">
                                    <h4
                                        style="color: #374151; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-notes-medical"></i> Additional Notes
                                    </h4>
                                    <p style="color: #374151; line-height: 1.6; margin: 0;">
                                        <?= nl2br(htmlspecialchars($record['notes'])) ?>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <!-- Action Buttons -->
                            <div
                                style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border); font-size: 0.875rem; color: var(--gray);">
                                <div>
                                    <?php
                                    $created_date = $record['appointment_date'] ?? $record['created_at'] ?? '';
                                    if ($created_date) {
                                        echo "Date: " . formatDate($created_date);
                                    }
                                    ?>
                                    <?php if ($record['record_type'] === 'appointment'): ?>
                                        <span style="margin-left: 1rem;">
                                            <i class="fas fa-calendar"></i> From Appointment
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php if ($record['record_type'] === 'appointment' && isset($record['record_id'])): ?>
                                        <a href="edit_appointment_record.php?appointment_id=<?= $record['record_id'] ?>&patient_id=<?= $patient_id ?>&doctor_id=<?= $doctor['doctor_id'] ?>"
                                            class="btn btn-outline btn-sm">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="view_appointment.php?id=<?= $record['record_id'] ?>" class="btn btn-outline btn-sm"
                                            style="margin-left: 0.5rem;">
                                            <i class="fas fa-external-link-alt"></i> View
                                        </a>
                                    <?php elseif (isset($record['record_id'])): ?>
                                        <a href="edit_medical_record.php?record_id=<?= $record['record_id'] ?>"
                                            class="btn btn-outline btn-sm">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>

</html>