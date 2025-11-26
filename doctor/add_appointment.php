<?php
// doctor/add_appointment.php
require_once '../config.php';
require_once '../helpers.php';
requireRole('doctor');

$doctor = getDoctorByUserId($_SESSION['user_id']);
if (!$doctor) {
    die("Doctor profile not found.");
}

$conn = getDBConnection();

// Get patient_id from URL if provided (for quick appointment creation from patient details)
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : null;

// Fetch existing patients for this doctor
$patients_query = "
    SELECT DISTINCT p.patient_id, p.full_name, p.phone, p.email 
    FROM appointments a 
    JOIN patients p ON a.patient_id = p.patient_id 
    WHERE a.doctor_id = ? 
    ORDER BY p.full_name
";
$stmt = $conn->prepare($patients_query);
$stmt->bind_param("i", $doctor['doctor_id']);
$stmt->execute();
$existing_patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Let's first check if doctor_availability table exists and has the right structure
$table_check = $conn->query("SHOW TABLES LIKE 'doctor_availability'");
$availability_table_exists = $table_check->num_rows > 0;

$availability_slots = [];
if ($availability_table_exists) {
    // Check table structure
    $structure_check = $conn->query("DESCRIBE doctor_availability");
    $columns = [];
    while ($row = $structure_check->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    
    // Build query based on available columns
    $availability_columns = [];
    if (in_array('day_of_week', $columns)) $availability_columns[] = 'day_of_week';
    if (in_array('start_time', $columns)) $availability_columns[] = 'start_time';
    if (in_array('end_time', $columns)) $availability_columns[] = 'end_time';
    if (in_array('slot_duration', $columns)) $availability_columns[] = 'slot_duration';
    
    if (!empty($availability_columns)) {
        $availability_query = "
            SELECT " . implode(', ', $availability_columns) . " 
            FROM doctor_availability 
            WHERE doctor_id = ? AND is_active = 1
            ORDER BY " . (in_array('day_of_week', $columns) ? 'day_of_week, start_time' : 'start_time')
        ;
        
        $stmt = $conn->prepare($availability_query);
        $stmt->bind_param("i", $doctor['doctor_id']);
        $stmt->execute();
        $availability_slots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = intval($_POST['patient_id']);
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $symptoms = trim($_POST['symptoms'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $appointment_type = $_POST['appointment_type'] ?? 'consultation';
    
    // Validate required fields
    if (empty($patient_id) || empty($appointment_date) || empty($appointment_time)) {
        $_SESSION['error'] = "Please fill all required fields.";
    } else {
        // Check if patient exists and get patient details
        $patient_check = $conn->prepare("SELECT patient_id, full_name FROM patients WHERE patient_id = ?");
        $patient_check->bind_param("i", $patient_id);
        $patient_check->execute();
        $patient_result = $patient_check->get_result();
        
        if ($patient_result->num_rows === 0) {
            $_SESSION['error'] = "Selected patient not found.";
        } else {
            $patient = $patient_result->fetch_assoc();
            
            // Check for scheduling conflicts
            $conflict_check = $conn->prepare("
                SELECT appointment_id 
                FROM appointments 
                WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status != 'cancelled'
            ");
            $conflict_check->bind_param("iss", $doctor['doctor_id'], $appointment_date, $appointment_time);
            $conflict_check->execute();
            
            if ($conflict_check->get_result()->num_rows > 0) {
                $_SESSION['error'] = "Time slot already booked. Please choose a different time.";
            } else {
                // Insert new appointment
                $insert_sql = "
                    INSERT INTO appointments 
                    (patient_id, doctor_id, appointment_date, appointment_time, symptoms, notes, appointment_type, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', NOW())
                ";
                
                $stmt = $conn->prepare($insert_sql);
                $stmt->bind_param("iisssss", $patient_id, $doctor['doctor_id'], $appointment_date, $appointment_time, $symptoms, $notes, $appointment_type);
                
                if ($stmt->execute()) {
                    $appointment_id = $stmt->insert_id;
                    
                    // Create notification for patient
                    createNotification(
                        $patient_id,
                        'patient',
                        'New Appointment Scheduled',
                        "Your appointment with Dr. {$doctor['full_name']} has been scheduled for " . formatDate($appointment_date) . " at " . formatTime($appointment_time),
                        'appointment_scheduled',
                        $appointment_id
                    );
                    
                    $_SESSION['success'] = "Appointment scheduled successfully!";
                    header("Location: appointments.php");
                    exit();
                } else {
                    $_SESSION['error'] = "Error scheduling appointment: " . $stmt->error;
                }
                $stmt->close();
            }
            $conflict_check->close();
        }
        $patient_check->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule New Appointment - CARE Group</title>
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
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
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

        .btn-secondary {
            background: var(--secondary);
            color: white;
        }

        .btn-secondary:hover {
            background: #475569;
            transform: translateY(-2px);
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
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
            background: var(--light);
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            background: white;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-help {
            font-size: 0.875rem;
            color: var(--gray);
            margin-top: 0.5rem;
            display: block;
        }

        .required::after {
            content: " *";
            color: var(--danger);
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .availability-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
        }

        .availability-info h4 {
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .availability-slots {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .slot-item {
            background: rgba(255, 255, 255, 0.1);
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .slot-day {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .slot-time {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .patient-selector {
            background: var(--light);
            padding: 1.5rem;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
            margin-bottom: 2rem;
        }

        .quick-patient-info {
            background: #dbeafe;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
            margin-bottom: 1rem;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border-left: 4px solid;
        }

        .alert-success {
            background: #d1fae5;
            border-color: var(--success);
            color: #065f46;
        }

        .alert-error {
            background: #fee2e2;
            border-color: var(--danger);
            color: #991b1b;
        }

        .alert-info {
            background: #dbeafe;
            border-color: var(--primary);
            color: #1e40af;
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

            .top-bar {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .availability-slots {
                grid-template-columns: 1fr;
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
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="doctor-avatar">
                    <i class="fas fa-user-md"></i>
                </div>
                <h3>Dr. <?= htmlspecialchars($doctor['full_name']) ?></h3>
                <p><?= htmlspecialchars($doctor['specialization_name']) ?></p>
                <p style="color: #cbd5e1; font-size: 0.8rem; margin-top: 0.5rem;">
                    <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($doctor['city_name'] ?? 'N/A') ?>
                </p>
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
                    <h1>Schedule New Appointment</h1>
                    <p>Book an appointment for your patient</p>
                </div>
                <div>
                    <a href="appointments.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Back to Appointments
                    </a>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $_SESSION['success'] ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error'] ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Quick Patient Info (if patient_id provided) -->
            <?php if ($patient_id): ?>
                <?php
                $conn = getDBConnection();
                $patient_stmt = $conn->prepare("SELECT full_name, phone, email FROM patients WHERE patient_id = ?");
                $patient_stmt->bind_param("i", $patient_id);
                $patient_stmt->execute();
                $patient_info = $patient_stmt->get_result()->fetch_assoc();
                $patient_stmt->close();
                $conn->close();
                
                if ($patient_info):
                ?>
                <div class="quick-patient-info">
                    <h4><i class="fas fa-user"></i> Quick Appointment For</h4>
                    <p><strong>Patient:</strong> <?= safe_html($patient_info['full_name']) ?></p>
                    <p><strong>Phone:</strong> <?= safe_html($patient_info['phone']) ?></p>
                    <p><strong>Email:</strong> <?= safe_html($patient_info['email']) ?></p>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Availability Information -->
            <?php if ($availability_table_exists && !empty($availability_slots)): ?>
                <div class="availability-info">
                    <h4><i class="fas fa-clock"></i> Your Available Time Slots</h4>
                    <div class="availability-slots">
                        <?php foreach ($availability_slots as $slot): ?>
                            <div class="slot-item">
                                <?php if (isset($slot['day_of_week'])): ?>
                                    <?php 
                                    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                                    $day_name = $days[$slot['day_of_week']] ?? 'Unknown';
                                    ?>
                                    <div class="slot-day"><?= $day_name ?></div>
                                <?php endif; ?>
                                <?php if (isset($slot['start_time']) && isset($slot['end_time'])): ?>
                                    <div class="slot-time">
                                        <?= formatTime($slot['start_time']) ?> - <?= formatTime($slot['end_time']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($slot['slot_duration'])): ?>
                                    <div class="slot-time" style="font-size: 0.8rem; opacity: 0.7;">
                                        Slot: <?= $slot['slot_duration'] ?> minutes
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php elseif (!$availability_table_exists): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Availability System Not Set Up</strong>
                    <p style="margin: 0.5rem 0 0 0;">The availability system is not yet configured. You can still schedule appointments manually.</p>
                    <a href="availability.php" class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-cog"></i> Set Up Availability
                    </a>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 
                    <strong>No Availability Slots Set</strong>
                    <p style="margin: 0.5rem 0 0 0;">You haven't set up any availability slots yet. You can still schedule appointments manually.</p>
                    <a href="availability.php" class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-plus"></i> Add Availability Slots
                    </a>
                </div>
            <?php endif; ?>

            <!-- Appointment Form -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-calendar-plus"></i> Appointment Details</h2>
                    <span style="color: var(--primary); font-weight: 600;">
                        <i class="fas fa-info-circle"></i> All fields are required
                    </span>
                </div>

                <form method="POST" id="appointmentForm">
                    <!-- Patient Selection -->
                    <div class="form-group">
                        <label class="form-label required">
                            <i class="fas fa-user"></i> Select Patient
                        </label>
                        <select name="patient_id" class="form-control" required <?= $patient_id ? 'disabled' : '' ?>>
                            <option value="">Choose a patient...</option>
                            <?php foreach ($existing_patients as $patient): ?>
                                <option value="<?= $patient['patient_id'] ?>" 
                                    <?= ($patient_id && $patient['patient_id'] == $patient_id) ? 'selected' : '' ?>>
                                    <?= safe_html($patient['full_name']) ?> (<?= safe_html($patient['phone']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($patient_id): ?>
                            <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
                            <span class="form-help">Patient pre-selected from patient details page</span>
                        <?php else: ?>
                            <span class="form-help">Select from your existing patients</span>
                        <?php endif; ?>
                    </div>

                    <!-- Date and Time -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required">
                                <i class="fas fa-calendar"></i> Appointment Date
                            </label>
                            <input type="date" name="appointment_date" class="form-control" 
                                min="<?= date('Y-m-d') ?>" required 
                                value="<?= $_POST['appointment_date'] ?? '' ?>">
                            <span class="form-help">Select a future date for the appointment</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label required">
                                <i class="fas fa-clock"></i> Appointment Time
                            </label>
                            <input type="time" name="appointment_time" class="form-control" 
                                min="08:00" max="20:00" step="900" required
                                value="<?= $_POST['appointment_time'] ?? '' ?>">
                            <span class="form-help">Appointment time between 8:00 AM and 8:00 PM</span>
                        </div>
                    </div>

                    <!-- Appointment Type -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-stethoscope"></i> Appointment Type
                        </label>
                        <select name="appointment_type" class="form-control">
                            <option value="consultation" <?= ($_POST['appointment_type'] ?? 'consultation') === 'consultation' ? 'selected' : '' ?>>Consultation</option>
                            <option value="follow-up" <?= ($_POST['appointment_type'] ?? '') === 'follow-up' ? 'selected' : '' ?>>Follow-up</option>
                            <option value="checkup" <?= ($_POST['appointment_type'] ?? '') === 'checkup' ? 'selected' : '' ?>>Routine Checkup</option>
                            <option value="emergency" <?= ($_POST['appointment_type'] ?? '') === 'emergency' ? 'selected' : '' ?>>Emergency</option>
                            <option value="procedure" <?= ($_POST['appointment_type'] ?? '') === 'procedure' ? 'selected' : '' ?>>Procedure</option>
                        </select>
                        <span class="form-help">Select the type of appointment</span>
                    </div>

                    <!-- Symptoms -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-notes-medical"></i> Symptoms / Reason for Visit
                        </label>
                        <textarea name="symptoms" class="form-control" rows="3" 
                            placeholder="Describe patient's symptoms or reason for appointment..."><?= safe_html($_POST['symptoms'] ?? '') ?></textarea>
                        <span class="form-help">Optional: Note down main symptoms or concerns</span>
                    </div>

                    <!-- Additional Notes -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-comment-medical"></i> Additional Notes
                        </label>
                        <textarea name="notes" class="form-control" rows="3" 
                            placeholder="Any additional information or special requirements..."><?= safe_html($_POST['notes'] ?? '') ?></textarea>
                        <span class="form-help">Optional: Additional notes for the appointment</span>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-calendar-check"></i> Schedule Appointment
                        </button>
                        <a href="appointments.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <a href="patients.php" class="btn btn-outline">
                            <i class="fas fa-users"></i> View All Patients
                        </a>
                    </div>
                </form>
            </div>

            <!-- Quick Tips -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-lightbulb"></i> Scheduling Tips</h2>
                </div>
                <div style="display: grid; gap: 1rem;">
                    <div style="display: flex; align-items: start; gap: 1rem;">
                        <i class="fas fa-clock" style="color: var(--primary); margin-top: 0.25rem;"></i>
                        <div>
                            <strong>Time Management</strong>
                            <p style="color: var(--gray); margin: 0.25rem 0 0 0;">Schedule appointments with adequate time between them to handle emergencies and documentation.</p>
                        </div>
                    </div>
                    <div style="display: flex; align-items: start; gap: 1rem;">
                        <i class="fas fa-bell" style="color: var(--warning); margin-top: 0.25rem;"></i>
                        <div>
                            <strong>Patient Notifications</strong>
                            <p style="color: var(--gray); margin: 0.25rem 0 0 0;">Patients will receive automatic notifications about their scheduled appointments.</p>
                        </div>
                    </div>
                    <div style="display: flex; align-items: start; gap: 1rem;">
                        <i class="fas fa-exclamation-triangle" style="color: var(--danger); margin-top: 0.25rem;"></i>
                        <div>
                            <strong>Conflict Prevention</strong>
                            <p style="color: var(--gray); margin: 0.25rem 0 0 0;">The system automatically checks for scheduling conflicts to avoid double-booking.</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('appointmentForm');
            const dateInput = document.querySelector('input[name="appointment_date"]');
            const timeInput = document.querySelector('input[name="appointment_time"]');

            // Set minimum date to today
            const today = new Date().toISOString().split('T')[0];
            dateInput.min = today;

            // Prevent weekend appointments (optional)
            dateInput.addEventListener('change', function() {
                const selectedDate = new Date(this.value);
                const dayOfWeek = selectedDate.getDay();
                
                if (dayOfWeek === 0 || dayOfWeek === 6) { // Sunday or Saturday
                    alert('Weekend appointments may have limited availability. Please confirm with the patient.');
                }
            });

            // Form validation
            form.addEventListener('submit', function(e) {
                const selectedDate = new Date(dateInput.value);
                const today = new Date();
                today.setHours(0, 0, 0, 0);

                if (selectedDate < today) {
                    e.preventDefault();
                    alert('Please select a future date for the appointment.');
                    dateInput.focus();
                    return false;
                }

                const selectedTime = timeInput.value;
                if (selectedTime < '08:00' || selectedTime > '20:00') {
                    e.preventDefault();
                    alert('Appointment time must be between 8:00 AM and 8:00 PM.');
                    timeInput.focus();
                    return false;
                }

                // Show loading state
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Scheduling...';
                submitBtn.disabled = true;
            });

            // Auto-advance to time field after date selection
            dateInput.addEventListener('change', function() {
                if (this.value && !timeInput.value) {
                    timeInput.focus();
                }
            });

            // Character counter for textareas
            document.querySelectorAll('textarea').forEach(textarea => {
                const counter = document.createElement('div');
                counter.style.fontSize = '0.8rem';
                counter.style.color = 'var(--gray)';
                counter.style.textAlign = 'right';
                counter.style.marginTop = '0.5rem';
                
                function updateCounter() {
                    counter.textContent = `${textarea.value.length} characters`;
                }
                
                textarea.addEventListener('input', updateCounter);
                textarea.parentNode.appendChild(counter);
                updateCounter();
            });
        });

        // Auto-resize textareas
        function autoResize(textarea) {
            textarea.style.height = 'auto';
            textarea.style.height = (textarea.scrollHeight) + 'px';
        }

        document.querySelectorAll('textarea').forEach(textarea => {
            textarea.addEventListener('input', function() {
                autoResize(this);
            });
            autoResize(textarea);
        });
    </script>
</body>

</html>