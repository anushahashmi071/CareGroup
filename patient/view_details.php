<?php
// patient/view_details.php - View Appointment Details
session_start();
require_once '../config.php';

// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../login.php");
    exit();
}

$patient = getPatientByUserId($_SESSION['user_id']);
if (!$patient) {
    header("Location: fix_profile.php");
    exit();
}

// Get appointment ID
if (!isset($_GET['appointment_id'])) {
    header("Location: my_appointments.php");
    exit();
}

$appointment_id = intval($_GET['appointment_id']);

// Get appointment details
$conn = getDBConnection();
$stmt = $conn->prepare("
    SELECT a.*, 
           d.full_name as doctor_name, 
           d.qualification, 
           d.experience_years,
           d.phone as doctor_phone, 
           d.email as doctor_email,
           d.rating,
           d.total_reviews,
           d.consultation_fee,
           s.specialization_name,
           c.city_name,
           p.full_name as patient_name,
           p.phone as patient_phone,
           p.email as patient_email,
           p.blood_group,
           p.allergies
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.doctor_id
    JOIN specializations s ON d.specialization_id = s.specialization_id
    JOIN cities c ON d.city_id = c.city_id
    JOIN patients p ON a.patient_id = p.patient_id
    WHERE a.appointment_id = ? AND a.patient_id = ?
");
$stmt->bind_param("ii", $appointment_id, $patient['patient_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: my_appointments.php");
    exit();
}

$appointment = $result->fetch_assoc();
$stmt->close();
$conn->close();

// Determine if appointment can be cancelled
$can_cancel = ($appointment['status'] === 'scheduled' && strtotime($appointment['appointment_date']) >= strtotime(date('Y-m-d')));
$is_past = strtotime($appointment['appointment_date']) < strtotime(date('Y-m-d'));
$is_upcoming = ($appointment['status'] === 'scheduled' && !$is_past);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Details - CARE Group</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .navbar {
            background: white;
            padding: 1rem 0;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            color: #1e293b;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }

        .nav-links a:hover {
            color: #10b981;
            background: #f0fdf4;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #64748b;
            text-decoration: none;
            margin-bottom: 1.5rem;
            padding: 0.75rem 1rem;
            background: white;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .back-button:hover {
            color: #10b981;
            background: #f0fdf4;
        }

        .page-header {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white;
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(99, 102, 241, 0.3);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            opacity: 0.9;
        }

        .status-badge {
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-scheduled {
            background: white;
            color: #2563eb;
        }

        .status-completed {
            background: white;
            color: #059669;
        }

        .status-cancelled {
            background: white;
            color: #dc2626;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        .main-card {
            background: white;
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .section {
            margin-bottom: 2.5rem;
            padding-bottom: 2rem;
            border-bottom: 2px solid #f1f5f9;
        }

        .section:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .section-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .section-header h2 {
            color: #1e293b;
            font-size: 1.3rem;
        }

        .doctor-info {
            display: flex;
            gap: 2rem;
            align-items: start;
        }

        .doctor-avatar {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            flex-shrink: 0;
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
        }

        .doctor-details h3 {
            color: #1e293b;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .doctor-details .specialization {
            color: #10b981;
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }

        .doctor-meta {
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
        }

        .meta-item i {
            color: #10b981;
            width: 20px;
        }

        .rating-badge {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .info-grid {
            display: grid;
            gap: 1rem;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 10px;
            align-items: center;
        }

        .info-label {
            color: #64748b;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-label i {
            color: #6366f1;
            width: 20px;
        }

        .info-value {
            color: #1e293b;
            font-weight: 700;
        }

        .content-box {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 4px solid #6366f1;
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
            line-height: 1.8;
        }

        .content-box.diagnosis {
            border-left-color: #3b82f6;
        }

        .content-box.prescription {
            border-left-color: #10b981;
            background: #f0fdf4;
        }

        .content-box.notes {
            border-left-color: #f59e0b;
        }

        .sidebar-card {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
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

        .action-buttons {
            display: grid;
            gap: 1rem;
        }

        .btn {
            padding: 1rem;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
        }

        .btn-danger {
            background: #dc2626;
            color: white;
        }

        .btn-danger:hover {
            background: #b91c1c;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(220, 38, 38, 0.3);
        }

        .btn-secondary {
            background: #3b82f6;
            color: white;
        }

        .btn-secondary:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
        }

        .btn-outline {
            background: white;
            border: 2px solid #e2e8f0;
            color: #64748b;
        }

        .btn-outline:hover {
            border-color: #6366f1;
            color: #6366f1;
            background: #f8fafc;
        }

        .quick-info {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 1.5rem;
        }

        .quick-info-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.75rem;
        }

        .quick-info-item:last-child {
            margin-bottom: 0;
        }

        .quick-info-icon {
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #2563eb;
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
            font-size: 1.1rem;
        }

        .alert-warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 1rem;
            border-radius: 10px;
            color: #92400e;
            margin-top: 1.5rem;
        }

        .empty-content {
            text-align: center;
            padding: 2rem;
            color: #94a3b8;
        }

        .empty-content i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            opacity: 0.5;
        }

        @media (max-width: 968px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .doctor-info {
                flex-direction: column;
                text-align: center;
            }

            .doctor-avatar {
                margin: 0 auto;
            }

            .doctor-meta {
                grid-template-columns: 1fr;
            }
        }

        @media print {

            .navbar,
            .back-button,
            .action-buttons,
            .btn {
                display: none !important;
            }
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <i class="fas fa-heartbeat"></i>
                CARE Group
            </div>
            <div class="nav-links">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="my_appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a>
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <a href="my_appointments.php" class="back-button">
            <i class="fas fa-arrow-left"></i> Back to Appointments
        </a>

        <div class="page-header">
            <div>
                <h1><i class="fas fa-file-medical-alt"></i> Appointment Details</h1>
                <p>Appointment ID: #<?php echo $appointment['appointment_id']; ?></p>
            </div>
            <span class="status-badge status-<?php echo $appointment['status']; ?>">
                <i class="fas fa-circle"></i>
                <?php echo ucfirst($appointment['status']); ?>
            </span>
        </div>

        <div class="content-grid">
            <!-- Main Content -->
            <div class="main-card">
                <!-- Doctor Information -->
                <div class="section">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-user-md"></i>
                        </div>
                        <h2>Doctor Information</h2>
                    </div>
                    <div class="doctor-info">
                        <div class="doctor-avatar">
                            <i class="fas fa-user-md"></i>
                        </div>
                        <div class="doctor-details">
                            <h3>Dr. <?php echo $appointment['doctor_name']; ?></h3>
                            <div class="specialization"><?php echo $appointment['specialization_name']; ?></div>
                            <p style="color: #64748b; margin-bottom: 0.5rem;">
                                <?php echo $appointment['qualification']; ?>
                            </p>
                            <div class="rating-badge">
                                <i class="fas fa-star"></i>
                                <?php echo number_format($appointment['rating'], 1); ?>
                                <span style="opacity: 0.7;">(<?php echo $appointment['total_reviews']; ?>
                                    reviews)</span>
                            </div>
                            <div class="doctor-meta">
                                <div class="meta-item">
                                    <i class="fas fa-briefcase"></i>
                                    <?php echo $appointment['experience_years']; ?> years experience
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo $appointment['city_name']; ?>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-phone"></i>
                                    <?php echo $appointment['doctor_phone']; ?>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-envelope"></i>
                                    <?php echo $appointment['doctor_email']; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Appointment Details -->
                <div class="section">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h2>Appointment Details</h2>
                    </div>
                    <div class="info-grid">
                        <div class="info-row">
                            <span class="info-label">
                                <i class="fas fa-calendar"></i> Date
                            </span>
                            <span class="info-value"><?php echo formatDate($appointment['appointment_date']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">
                                <i class="fas fa-clock"></i> Time
                            </span>
                            <span class="info-value"><?php echo formatTime($appointment['appointment_time']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">
                                <i class="fas fa-rupee-sign"></i> Consultation Fee
                            </span>
                            <span
                                class="info-value">$<?php echo number_format($appointment['consultation_fee'], 0); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">
                                <i class="fas fa-info-circle"></i> Status
                            </span>
                            <span class="info-value" style="color: <?php
                            echo $appointment['status'] === 'completed' ? '#059669' :
                                ($appointment['status'] === 'cancelled' ? '#dc2626' : '#2563eb');
                            ?>">
                                <?php echo ucfirst($appointment['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Medical Information -->
                <div class="section">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-notes-medical"></i>
                        </div>
                        <h2>Medical Information</h2>
                    </div>

                    <?php if ($appointment['symptoms']): ?>
                        <div class="content-box" style="margin-bottom: 1rem;">
                            <h4><i class="fas fa-notes-medical"></i> Symptoms Reported</h4>
                            <p><?php echo nl2br($appointment['symptoms']); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($appointment['diagnosis']): ?>
                        <div class="content-box diagnosis" style="margin-bottom: 1rem;">
                            <h4><i class="fas fa-stethoscope"></i> Diagnosis</h4>
                            <p><?php echo nl2br($appointment['diagnosis']); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($appointment['prescription']): ?>
                        <div class="content-box prescription" style="margin-bottom: 1rem;">
                            <h4><i class="fas fa-prescription"></i> Prescription</h4>
                            <p><?php echo nl2br($appointment['prescription']); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($appointment['notes']): ?>
                        <div class="content-box notes">
                            <h4><i class="fas fa-comment-medical"></i> Doctor's Notes</h4>
                            <p><?php echo nl2br($appointment['notes']); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!$appointment['diagnosis'] && !$appointment['prescription'] && !$appointment['notes']): ?>
                        <div class="empty-content">
                            <i class="fas fa-clipboard"></i>
                            <p>Medical records will be available after your appointment is completed</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="sidebar-card">
                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>

                <div class="quick-info">
                    <div class="quick-info-item">
                        <div class="quick-info-icon">
                            <i class="fas fa-calendar"></i>
                        </div>
                        <div class="quick-info-content">
                            <h4>Appointment Date</h4>
                            <p><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></p>
                        </div>
                    </div>
                    <div class="quick-info-item">
                        <div class="quick-info-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="quick-info-content">
                            <h4>Appointment Time</h4>
                            <p><?php echo formatTime($appointment['appointment_time']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="action-buttons">
                    <button class="btn btn-outline" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Details
                    </button>

                    <button class="btn btn-outline">
                        <i class="fas fa-download"></i> Download PDF
                    </button>

                    <?php if ($appointment['status'] === 'completed'): ?>
                        <a href="book_appointment.php?doctor_id=<?php echo $appointment['doctor_id']; ?>"
                            class="btn btn-primary">
                            <i class="fas fa-redo"></i> Book Again
                        </a>
                        <button class="btn btn-secondary">
                            <i class="fas fa-star"></i> Rate Doctor
                        </button>
                    <?php endif; ?>

                    <?php if ($can_cancel): ?>
                        <button class="btn btn-danger" onclick="cancelAppointment(<?php echo $appointment_id; ?>)">
                            <i class="fas fa-times"></i> Cancel Appointment
                        </button>
                    <?php endif; ?>

                    <a href="my_appointments.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>

                <?php if ($is_upcoming): ?>
                    <div class="alert-warning">
                        <i class="fas fa-info-circle"></i>
                        <strong>Reminder:</strong> Please arrive 10 minutes before your scheduled time.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function cancelAppointment(appointmentId) {
            if (confirm('Are you sure you want to cancel this appointment?')) {
                fetch('cancel_appointment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'appointment_id=' + appointmentId
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Appointment cancelled successfully');
                            window.location.reload();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error cancelling appointment');
                    });
            }
        }
    </script>
</body>

</html>