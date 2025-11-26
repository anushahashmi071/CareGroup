<?php
// patient/book_appointment.php - Complete Appointment Booking System
require_once '../config.php';
requireRole('patient');

$patient = getPatientByUserId($_SESSION['user_id']);
$message = '';
$error = '';
$doctor = null;
$available_slots = array();
$selected_date = '';

// Get doctor details if doctor_id is provided
if (isset($_GET['doctor_id'])) {
    $doctor_id = intval($_GET['doctor_id']);
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT d.*, s.specialization_name, c.city_name, u.status as user_status
        FROM doctors d
        JOIN specializations s ON d.specialization_id = s.specialization_id
        JOIN cities c ON d.city_id = c.city_id
        JOIN users u ON d.user_id = u.user_id
        WHERE d.doctor_id = ? AND d.status = 'active' AND u.status = 'active'
    ");
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $doctor = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
}

// Handle appointment booking
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book_appointment'])) {
    $doctor_id = intval($_POST['doctor_id']);
    $appointment_date = sanitize($_POST['appointment_date']);
    $appointment_time = sanitize($_POST['appointment_time']);
    $symptoms = sanitize($_POST['symptoms']);

    // Validate date is in future
    if (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
        $error = 'Cannot book appointment for past dates';
    } elseif (empty($appointment_time)) {
        $error = 'Please select a time slot';
    } else {
        // Check if slot is still available
        if (isSlotAvailable($doctor_id, $appointment_date, $appointment_time)) {
            $conn = getDBConnection();
            $stmt = $conn->prepare("
                INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, symptoms, status)
                VALUES (?, ?, ?, ?, ?, 'scheduled')
            ");
            $stmt->bind_param("iisss", $patient['patient_id'], $doctor_id, $appointment_date, $appointment_time, $symptoms);

            if ($stmt->execute()) {
                $appointment_id = $conn->insert_id;
                $message = "Appointment booked successfully! Appointment ID: #$appointment_id";

                // Send notifications
                $doctor_name = $doctor['full_name'];
                $patient_name = $patient['full_name'];

                // Doctor notification
                $doctor_notification_title = "New Appointment Booking";
                $doctor_notification_message = "Patient " . $patient_name . " has booked an appointment for " . $appointment_date . " at " . $appointment_time;
                createNotification($doctor_id, 'doctor', $doctor_notification_title, $doctor_notification_message, 'appointment', $appointment_id);

                // Patient notification
                $patient_notification_title = "Appointment Confirmed";
                $patient_notification_message = "Your appointment with Dr. " . $doctor_name . " is confirmed for " . $appointment_date . " at " . $appointment_time;
                createNotification($patient['patient_id'], 'patient', $patient_notification_title, $patient_notification_message, 'appointment', $appointment_id);

                // Clear form
                $_POST = array();
            } else {
                $error = 'Error booking appointment. Please try again.';
            }
            $stmt->close();
            $conn->close();
        } else {
            $error = 'This time slot is no longer available. Please choose another time.';
        }
    }
}

// Handle check availability
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['check_availability'])) {
    $doctor_id = intval($_POST['doctor_id']);
    $appointment_date = sanitize($_POST['appointment_date']);
    $selected_date = $appointment_date;

    if (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
        $error = 'Cannot check availability for past dates';
    } elseif (isDoctorOnLeave($doctor_id, $appointment_date)) {
        $error = 'Doctor is not available on this date. Please select another date.';
    } else {
        $available_slots = getAvailableSlots($doctor_id, $appointment_date);
        if (empty($available_slots)) {
            $error = 'No available slots for this date. Please try another date.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment – <?php echo htmlspecialchars(getSetting('site_name')); ?></title>
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

        .btn-secondary {
            background: #3b82f6;
            color: white;
        }

        .btn-secondary:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59, 130, 246, 0.3);
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

        /* Content Sections */
        .content-section {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
            border: 1px solid #e2e8f0;
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
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            font-size: 1.4rem;
        }

        /* Booking Form Styles */
        .booking-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
        }

        .booking-form {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
        }

        .form-header {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid #f1f5f9;
        }

        .form-header h2 {
            color: #1e293b;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .form-header p {
            color: #64748b;
            font-size: 1.1rem;
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
        }

        .progress-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e2e8f0;
            z-index: 0;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 0.5rem;
            transition: all 0.3s;
        }

        .step.active .step-circle {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white;
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.3);
        }

        .step-label {
            font-size: 0.85rem;
            color: #64748b;
            text-align: center;
        }

        .step.active .step-label {
            color: #6366f1;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #1e293b;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .form-group label span {
            color: #dc2626;
        }

        .form-control {
            width: 100%;
            padding: 0.9rem 1.1rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .slots-section {
            margin-top: 1.5rem;
        }

        .slots-header {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .slots-header h3 {
            color: #1e40af;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .slots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
            gap: 0.75rem;
        }

        .slot-btn {
            padding: 1rem;
            border: 2px solid #e2e8f0;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
            font-size: 0.95rem;
            color: #64748b;
        }

        .slot-btn:hover {
            border-color: #6366f1;
            background: #f0fdf4;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.2);
        }

        .slot-btn.selected {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white;
            border-color: #6366f1;
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.3);
        }

        /* Doctor Summary */
        .doctor-summary {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            position: sticky;
            top: 100px;
            border: 1px solid #e2e8f0;
        }

        .doctor-avatar-large {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            margin: 0 auto 1.5rem;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.3);
        }

        .doctor-name {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .doctor-name h3 {
            color: #1e293b;
            font-size: 1.4rem;
            margin-bottom: 0.5rem;
        }

        .doctor-name .specialization {
            color: #6366f1;
            font-weight: 600;
            font-size: 1rem;
        }

        .rating-badge {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
            padding: 0.75rem 1.25rem;
            border-radius: 8px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin: 1.5rem auto;
            max-width: fit-content;
            box-shadow: 0 5px 15px rgba(245, 158, 11, 0.2);
        }

        .doctor-details {
            margin-top: 2rem;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            transition: all 0.3s;
        }

        .detail-row:hover {
            background: #f8fafc;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #64748b;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .detail-label i {
            color: #6366f1;
            width: 20px;
        }

        .detail-value {
            color: #1e293b;
            font-weight: 700;
        }

        .fee-highlight {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
            margin-top: 2rem;
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.2);
        }

        .fee-highlight p {
            color: #065f46;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .fee-amount {
            color: #065f46;
            font-size: 2rem;
            font-weight: bold;
        }

        /* Alert Styles */
        .alert {
            padding: 1.2rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.3s ease;
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
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert-error {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }

        .alert-info {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }

        .alert i {
            font-size: 1.5rem;
        }

        .empty-slots {
            text-align: center;
            padding: 2rem;
            color: #64748b;
        }

        .empty-slots i {
            font-size: 3rem;
            opacity: 0.5;
            margin-bottom: 1rem;
            display: block;
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

        @media (max-width: 968px) {
            .booking-grid {
                grid-template-columns: 1fr;
            }

            .doctor-summary {
                position: relative;
                top: 0;
            }

            .slots-grid {
                grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
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
        }

        /* Scrollbar Styling */
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
                <li><a href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
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
                    <h1>Book Appointment</h1>
                    <p>Schedule your consultation with healthcare specialists</p>
                </div>
                <div class="top-actions">
                    <a href="dashboard.php" class="btn btn-primary">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a href="../logout.php" class="btn logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong><?php echo $message; ?></strong><br>
                        <a href="my_appointments.php" style="color: #065f46; text-decoration: underline;">View your
                            appointments →</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <strong><?php echo $error; ?></strong>
                </div>
            <?php endif; ?>

            <?php if (!$doctor): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Please select a doctor first</strong><br>
                        <a href="search_doctors.php" style="color: #1e40af; text-decoration: underline;">Search for doctors
                            in your area →</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="booking-grid">
                    <!-- Booking Form -->
                    <div class="booking-form">
                        <div class="form-header">
                            <h2><i class="fas fa-calendar-plus"></i> Book Your Appointment</h2>
                            <p>Schedule your consultation with Dr. <?php echo $doctor['full_name']; ?></p>
                        </div>

                        <!-- Progress Steps -->
                        <div class="progress-steps">
                            <div class="step <?php echo empty($selected_date) ? 'active' : ''; ?>">
                                <div class="step-circle">1</div>
                                <div class="step-label">Select Date</div>
                            </div>
                            <div class="step <?php echo !empty($available_slots) ? 'active' : ''; ?>">
                                <div class="step-circle">2</div>
                                <div class="step-label">Choose Time</div>
                            </div>
                            <div class="step">
                                <div class="step-circle">3</div>
                                <div class="step-label">Confirm</div>
                            </div>
                        </div>

                        <form method="POST" action="" id="bookingForm">
                            <input type="hidden" name="doctor_id" value="<?php echo $doctor['doctor_id']; ?>">

                            <!-- Step 1: Select Date -->
                            <div class="form-group">
                                <label><i class="fas fa-calendar"></i> Select Appointment Date <span>*</span></label>
                                <input type="date" name="appointment_date" id="appointmentDate" class="form-control"
                                    min="<?php echo date('Y-m-d'); ?>"
                                    value="<?php echo htmlspecialchars($selected_date); ?>" required>
                            </div>

                            <button type="submit" name="check_availability" class="btn btn-secondary">
                                <i class="fas fa-search"></i> Check Available Time Slots
                            </button>

                            <!-- Step 2: Select Time Slot -->
                            <?php if (!empty($available_slots)): ?>
                                <div class="slots-section">
                                    <div class="slots-header">
                                        <h3><i class="fas fa-clock"></i> Available Time Slots</h3>
                                        <p style="color: #1e40af; font-size: 0.9rem; margin-top: 0.5rem;">
                                            <?php echo count($available_slots); ?> slots available on
                                            <?php echo formatDate($selected_date); ?>
                                        </p>
                                    </div>
                                    <div class="slots-grid">
                                        <?php foreach ($available_slots as $slot): ?>
                                            <button type="button" class="slot-btn" data-time="<?php echo $slot; ?>">
                                                <i class="far fa-clock"></i><br>
                                                <?php echo formatTime($slot); ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" name="appointment_time" id="selectedTime" required>
                                </div>
                            <?php endif; ?>

                            <!-- Step 3: Describe Symptoms -->
                            <?php if (!empty($available_slots)): ?>
                                <div class="form-group" style="margin-top: 2rem;">
                                    <label><i class="fas fa-notes-medical"></i> Describe Your Symptoms <span>*</span></label>
                                    <textarea name="symptoms" class="form-control"
                                        placeholder="Please describe your symptoms, health concerns, or reason for consultation in detail..."
                                        required></textarea>
                                    <small style="color: #64748b; display: block; margin-top: 0.5rem;">
                                        <i class="fas fa-info-circle"></i> This information helps the doctor prepare for your
                                        consultation
                                    </small>
                                </div>

                                <button type="submit" name="book_appointment" class="btn btn-primary" id="submitBtn" disabled>
                                    <i class="fas fa-check-circle"></i> Confirm & Book Appointment
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- Doctor Summary -->
                    <div class="doctor-summary">
                        <div class="doctor-avatar-large">
                            <i class="fas fa-user-md"></i>
                        </div>

                        <div class="doctor-name">
                            <h3>Dr. <?php echo $doctor['full_name']; ?></h3>
                            <div class="specialization"><?php echo $doctor['specialization_name']; ?></div>
                        </div>

                        <div class="rating-badge">
                            <i class="fas fa-star"></i>
                            <span><?php echo number_format($doctor['rating'] ?? 0, 1); ?></span>
                            <span style="opacity: 0.7;">(<?php echo $doctor['total_reviews'] ?? 0; ?> reviews)</span>
                        </div>

                        <div class="doctor-details">
                            <div class="detail-row">
                                <span class="detail-label">
                                    <i class="fas fa-graduation-cap"></i> Qualification
                                </span>
                                <span class="detail-value"><?php echo $doctor['qualification']; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">
                                    <i class="fas fa-briefcase"></i> Experience
                                </span>
                                <span class="detail-value"><?php echo $doctor['experience_years']; ?> years</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">
                                    <i class="fas fa-map-marker-alt"></i> Location
                                </span>
                                <span class="detail-value"><?php echo $doctor['city_name']; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">
                                    <i class="fas fa-phone"></i> Phone
                                </span>
                                <span class="detail-value"><?php echo $doctor['phone']; ?></span>
                            </div>
                        </div>

                        <div class="fee-highlight">
                            <p>Consultation Fee</p>
                            <div class="fee-amount">$<?php echo number_format($doctor['consultation_fee'], 0); ?></div>
                        </div>

                        <?php if ($doctor['bio']): ?>
                            <div style="margin-top: 2rem; padding-top: 2rem; border-top: 2px solid #f1f5f9;">
                                <h4
                                    style="color: #1e293b; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-info-circle"></i> About Doctor
                                </h4>
                                <p style="color: #64748b; line-height: 1.8;"><?php echo $doctor['bio']; ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Handle time slot selection
        const slotButtons = document.querySelectorAll('.slot-btn');
        const selectedTimeInput = document.getElementById('selectedTime');
        const submitBtn = document.getElementById('submitBtn');

        slotButtons.forEach(btn => {
            btn.addEventListener('click', function () {
                // Remove selected class from all buttons
                slotButtons.forEach(b => b.classList.remove('selected'));

                // Add selected class to clicked button
                this.classList.add('selected');

                // Set hidden input value
                selectedTimeInput.value = this.dataset.time;

                // Enable submit button
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
            });
        });

        // Prevent accidental form submission
        document.getElementById('bookingForm').addEventListener('submit', function (e) {
            const submitter = e.submitter;

            // If booking appointment but no time slot selected
            if (submitter && submitter.name === 'book_appointment' && !selectedTimeInput.value) {
                e.preventDefault();
                alert('Please select a time slot before confirming your appointment');
            }
        });

        // Auto-scroll to slots when available
        <?php if (!empty($available_slots)): ?>
            window.addEventListener('load', function () {
                const slotsSection = document.querySelector('.slots-section');
                if (slotsSection) {
                    slotsSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        <?php endif; ?>
    </script>
</body>

</html>