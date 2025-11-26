<?php
// doctor/view_appointment.php - PROPERLY FIXED VERSION
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../login.php");
    exit();
}

$doctor = getDoctorByUserId($_SESSION['user_id']);
if (!$doctor) {
    die("Doctor profile not found");
}

if (!isset($_GET['id'])) {
    header("Location: appointments.php");
    exit();
}

$appointment_id = intval($_GET['id']);
$message = '';
$error = '';

// Handle Add/Update Medical Records - PROPERLY FIXED
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_medical'])) {
    $diagnosis = sanitize($_POST['diagnosis']);
    $prescription = sanitize($_POST['prescription']);
    $notes = sanitize($_POST['notes']);

    $conn = getDBConnection();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // 1. Update appointment table
        $stmt = $conn->prepare("UPDATE appointments SET diagnosis = ?, prescription = ?, notes = ? WHERE appointment_id = ? AND doctor_id = ?");
        $stmt->bind_param("sssii", $diagnosis, $prescription, $notes, $appointment_id, $doctor['doctor_id']);
        $stmt->execute();
        $stmt->close();
        
        // 2. Get appointment details including patient_id
        $stmt = $conn->prepare("SELECT patient_id, appointment_date FROM appointments WHERE appointment_id = ?");
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $appointment_data = $result->fetch_assoc();
        $patient_id = $appointment_data['patient_id'];
        $appointment_date = $appointment_data['appointment_date'];
        $stmt->close();
        
        // 3. Create description from diagnosis, prescription, and notes
        $description_parts = [];
        if (!empty($diagnosis)) {
            $description_parts[] = "**Diagnosis:**\n" . $diagnosis;
        }
        if (!empty($prescription)) {
            $description_parts[] = "**Prescription:**\n" . $prescription;
        }
        if (!empty($notes)) {
            $description_parts[] = "**Additional Notes:**\n" . $notes;
        }
        
        $description = implode("\n\n", $description_parts);
        
        // 4. Check if medical record already exists for this appointment
        $stmt = $conn->prepare("SELECT record_id FROM medical_records WHERE appointment_id = ?");
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // UPDATE existing record
            $record = $result->fetch_assoc();
            $record_id = $record['record_id'];
            $stmt->close();
            
            $stmt = $conn->prepare("UPDATE medical_records SET description = ? WHERE record_id = ?");
            $stmt->bind_param("si", $description, $record_id);
            $stmt->execute();
            $stmt->close();
        } else {
            // INSERT new medical record
            $stmt->close();
            
            $record_type = "Medical Consultation";
            
            $stmt = $conn->prepare("INSERT INTO medical_records (patient_id, doctor_id, appointment_id, record_type, description, created_at) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiisss", $patient_id, $doctor['doctor_id'], $appointment_id, $record_type, $description, $appointment_date);
            $stmt->execute();
            $stmt->close();
        }
        
        // Commit transaction
        $conn->commit();
        $message = 'Medical records updated successfully and saved to patient records!';
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $error = 'Error updating records: ' . $e->getMessage();
    }
    
    $conn->close();
}

// Handle Mark Complete
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_complete'])) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("UPDATE appointments SET status = 'completed' WHERE appointment_id = ? AND doctor_id = ?");
    $stmt->bind_param("ii", $appointment_id, $doctor['doctor_id']);

    if ($stmt->execute()) {
        $message = 'Appointment marked as completed!';
    } else {
        $error = 'Error updating status';
    }
    $stmt->close();
    $conn->close();
}

// Get appointment details
$conn = getDBConnection();
$stmt = $conn->prepare("
    SELECT a.*, 
           p.full_name as patient_name, 
           p.phone, 
           p.email as patient_email,
           p.gender, 
           p.date_of_birth,
           p.blood_group,
           p.address,
           p.allergies,
           p.medical_history,
           c.city_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    JOIN cities c ON p.city_id = c.city_id
    WHERE a.appointment_id = ? AND a.doctor_id = ?
");
$stmt->bind_param("ii", $appointment_id, $doctor['doctor_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: appointments.php");
    exit();
}

$appointment = $result->fetch_assoc();
$stmt->close();
$conn->close();

// Helper function to safely escape values for HTML output
function safe_html($value)
{
    if ($value === null) {
        return '';
    }
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$is_scheduled = $appointment['status'] === 'scheduled';
$is_completed = $appointment['status'] === 'completed';
$patient_age = $appointment['date_of_birth'] ? date('Y') - date('Y', strtotime($appointment['date_of_birth'])) : 'N/A';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Details - Doctor Portal</title>
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

        .page-header {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            color: var(--dark);
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--gray);
            text-decoration: none;
            margin-bottom: 1.5rem;
            padding: 0.75rem 1rem;
            background: white;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
            box-shadow: var(--card-shadow);
        }

        .back-button:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
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

        .status-badge {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-scheduled {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .status-completed {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert {
            padding: 1.2rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.3s ease;
            border-left: 4px solid;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
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

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
            align-items: start;
        }

        .main-content-section {
            display: grid;
            gap: 2rem;
        }

        .card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s;
        }

        .card:hover {
            box-shadow: var(--hover-shadow);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border);
        }

        .card-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .card-header h2 {
            color: var(--dark);
            font-size: 1.5rem;
        }

        .patient-info-grid {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .patient-avatar-large {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: bold;
            box-shadow: var(--card-shadow);
        }

        .patient-details {
            flex: 1;
        }

        .patient-details h3 {
            color: var(--dark);
            font-size: 1.8rem;
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
            color: var(--gray);
        }

        .meta-item i {
            color: var(--primary);
            width: 20px;
        }

        .info-grid {
            display: grid;
            gap: 1rem;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 1rem;
            background: var(--light);
            border-radius: 8px;
        }

        .info-label {
            color: var(--gray);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-value {
            color: var(--dark);
            font-weight: 700;
        }

        .content-box {
            background: var(--light);
            padding: 1.5rem;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
            margin-bottom: 1rem;
        }

        .content-box h4 {
            color: var(--dark);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .content-box p {
            color: var(--gray);
            line-height: 1.8;
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

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark);
            font-weight: 600;
        }

        .form-control {
            width: 100%;
            padding: 0.9rem;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1rem;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            width: 100%;
            justify-content: center;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(37, 99, 235, 0.3);
        }

        .btn-success {
            background: var(--success);
            color: white;
            width: 100%;
            justify-content: center;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        /* Fixed Sidebar Card - Sticky Positioning */
        /* Sidebar Card - Fixed Position (No Scrolling) */
        .sidebar-card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            height: fit-content;
            position: relative;
            /* Remove sticky behavior */
            top: auto;
            /* Reset top position */
        }

        .sidebar-card h3 {
            color: var(--dark);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .quick-info {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            padding: 1.5rem;
            border-radius: 8px;
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
            color: var(--primary);
            font-size: 1.2rem;
        }

        .quick-info-content h4 {
            color: #1e40af;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .quick-info-content p {
            color: #1e40af;
            font-weight: 700;
        }

        .action-buttons {
            display: grid;
            gap: 1rem;
        }

        .action-buttons {
            display: grid;
            gap: 1rem;
        }

        .btn-outline {
            background: white;
            border: 2px solid var(--border);
            color: var(--gray);
        }

        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--light);
            transform: translateY(-2px);
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

            .content-grid {
                grid-template-columns: 1fr;
            }

            .patient-info-grid {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .patient-avatar-large {
                margin: 0 auto;
            }

            .patient-meta {
                grid-template-columns: 1fr;
            }

            .sidebar-card {
                position: relative;
                top: 0;
            }
        }

        @media print {

            .sidebar,
            .back-button,
            .action-buttons,
            .sidebar-card {
                display: none !important;
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
            <a href="appointments.php" class="back-button">
                <i class="fas fa-arrow-left"></i> Back to Appointments
            </a>

            <div class="page-header">
                <div>
                    <h1><i class="fas fa-file-medical-alt"></i> Appointment Details</h1>
                    <p style="color: var(--gray); margin-top: 0.5rem;">
                        Appointment ID: #<?php echo $appointment['appointment_id']; ?> | Dr.
                        <?php echo $doctor['full_name']; ?>
                    </p>
                </div>
                <span class="status-badge status-<?php echo $appointment['status']; ?>">
                    <i class="fas fa-circle"></i>
                    <?php echo ucfirst($appointment['status']); ?>
                </span>
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
                <!-- Main Content -->
                <div class="main-content-section">
                    <!-- Patient Information -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <h2>Patient Information</h2>
                        </div>

                        <div class="patient-info-grid">
                            <div class="patient-avatar-large">
                                <?php echo strtoupper(substr($appointment['patient_name'], 0, 1)); ?>
                            </div>
                            <div class="patient-details">
                                <h3><?php echo safe_html($appointment['patient_name']); ?></h3>
                                <div class="patient-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-phone"></i>
                                        <?php echo safe_html($appointment['phone']); ?>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-envelope"></i>
                                        <?php echo safe_html($appointment['patient_email']); ?>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-venus-mars"></i>
                                        <?php echo safe_html($appointment['gender']); ?>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-birthday-cake"></i>
                                        <?php echo $patient_age; ?> years
                                    </div>
                                    <?php if ($appointment['blood_group']): ?>
                                        <div class="meta-item">
                                            <i class="fas fa-tint"></i>
                                            <?php echo safe_html($appointment['blood_group']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="meta-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo safe_html($appointment['city_name']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="info-grid">
                            <div class="info-row">
                                <span class="info-label">
                                    <i class="fas fa-map-marker-alt"></i> Address
                                </span>
                                <span class="info-value"><?php echo safe_html($appointment['address']); ?></span>
                            </div>
                        </div>

                        <?php if ($appointment['allergies']): ?>
                            <div class="warning-box">
                                <h4><i class="fas fa-exclamation-triangle"></i> Allergies</h4>
                                <p><?php echo nl2br(safe_html($appointment['allergies'])); ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if ($appointment['medical_history']): ?>
                            <div class="content-box" style="border-left-color: var(--primary);">
                                <h4><i class="fas fa-history"></i> Medical History</h4>
                                <p><?php echo nl2br(safe_html($appointment['medical_history'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Symptoms -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-icon">
                                <i class="fas fa-notes-medical"></i>
                            </div>
                            <h2>Chief Complaints & Symptoms</h2>
                        </div>

                        <div class="content-box">
                            <p><?php echo nl2br(safe_html($appointment['symptoms'])); ?></p>
                        </div>
                    </div>

                    <!-- Medical Records Form -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-icon">
                                <i class="fas fa-stethoscope"></i>
                            </div>
                            <h2>Medical Records</h2>
                        </div>

                        <form method="POST">
                            <div class="form-group">
                                <label><i class="fas fa-stethoscope"></i> Diagnosis</label>
                                <textarea name="diagnosis" class="form-control" placeholder="Enter diagnosis..." <?php echo $is_completed ? 'readonly' : ''; ?>><?php echo safe_html($appointment['diagnosis']); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-prescription"></i> Prescription</label>
                                <textarea name="prescription" class="form-control"
                                    placeholder="Enter prescription details..." <?php echo $is_completed ? 'readonly' : ''; ?>><?php echo safe_html($appointment['prescription']); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-comment-medical"></i> Additional Notes</label>
                                <textarea name="notes" class="form-control" placeholder="Enter any additional notes..."
                                    <?php echo $is_completed ? 'readonly' : ''; ?>><?php echo safe_html($appointment['notes']); ?></textarea>
                            </div>

                            <?php if ($is_scheduled): ?>
                                <button type="submit" name="update_medical" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Medical Records
                                </button>
                            <?php endif; ?>
                        </form>
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
                        <?php if ($is_scheduled): ?>
                            <form method="POST">
                                <button type="submit" name="mark_complete" class="btn btn-success"
                                    onclick="return confirm('Mark this appointment as completed?')">
                                    <i class="fas fa-check-circle"></i> Mark as Completed
                                </button>
                            </form>
                        <?php endif; ?>

                        <button class="btn btn-outline" onclick="window.print()">
                            <i class="fas fa-print"></i> Print Report
                        </button>

                        <button class="btn btn-outline">
                            <i class="fas fa-download"></i> Download PDF
                        </button>

                        <a href="appointments.php" class="btn btn-outline" style="text-align: center;">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>

</html>