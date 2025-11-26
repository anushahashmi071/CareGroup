<?php
// patient/view_appointment.php - View Appointment Details
require_once '../config.php';
requireRole('patient');

$patient = getPatientByUserId($_SESSION['user_id']);
if (!$patient) {
    header("Location: dashboard.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: my_appointments.php");
    exit();
}

$appointment_id = intval($_GET['id']);

$conn = getDBConnection();

// Get appointment details
$stmt = $conn->prepare("
    SELECT a.*, 
           d.full_name as doctor_name,
           d.rating as doctor_rating,
           d.total_ratings,
           d.qualification,
           s.specialization_name,
           c.city_name,
           d.phone as doctor_phone,
           d.email as doctor_email
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.doctor_id
    JOIN specializations s ON d.specialization_id = s.specialization_id
    JOIN cities c ON d.city_id = c.city_id
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

$is_completed = $appointment['status'] === 'completed';
$is_scheduled = $appointment['status'] === 'scheduled';
$is_cancelled = $appointment['status'] === 'cancelled';

// Helper function to display stars
function displayStars($rating)
{
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= floor($rating)) {
            $stars .= '<i class="fas fa-star"></i>';
        } elseif ($i == ceil($rating) && fmod($rating, 1) > 0) {
            $stars .= '<i class="fas fa-star-half-alt"></i>';
        } else {
            $stars .= '<i class="far fa-star"></i>';
        }
    }
    return $stars;
}

// Handle Add/Update Medical Records
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_medical'])) {
    $diagnosis = sanitize($_POST['diagnosis']);
    $prescription = sanitize($_POST['prescription']);
    $notes = sanitize($_POST['notes']);

    $conn = getDBConnection();

    // Start transaction
    $conn->begin_transaction();

    try {
        // Update appointment table
        $stmt = $conn->prepare("UPDATE appointments SET diagnosis = ?, prescription = ?, notes = ? WHERE appointment_id = ? AND doctor_id = ?");
        $stmt->bind_param("sssii", $diagnosis, $prescription, $notes, $appointment_id, $doctor['doctor_id']);
        $stmt->execute();
        $stmt->close();

        // Get patient_id from appointment
        $stmt = $conn->prepare("SELECT patient_id FROM appointments WHERE appointment_id = ?");
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $patient_data = $result->fetch_assoc();
        $patient_id = $patient_data['patient_id'];
        $stmt->close();

        // Check if medical record already exists for this appointment
        $stmt = $conn->prepare("SELECT record_id FROM medical_records WHERE appointment_id = ?");
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Update existing record
            $record = $result->fetch_assoc();
            $record_id = $record['record_id'];
            $stmt->close();

            $description = "Diagnosis: " . $diagnosis . "\n\nPrescription: " . $prescription . "\n\nNotes: " . $notes;
            $stmt = $conn->prepare("UPDATE medical_records SET description = ?, updated_at = NOW() WHERE record_id = ?");
            $stmt->bind_param("si", $description, $record_id);
            $stmt->execute();
            $stmt->close();
        } else {
            // Create new medical record
            $stmt->close();

            $record_type = "Medical Consultation";
            $description = "Diagnosis: " . $diagnosis . "\n\nPrescription: " . $prescription . "\n\nNotes: " . $notes;

            $stmt = $conn->prepare("INSERT INTO medical_records (patient_id, doctor_id, appointment_id, record_type, description, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("iiiss", $patient_id, $doctor['doctor_id'], $appointment_id, $record_type, $description);
            $stmt->execute();
            $stmt->close();
        }

        // Commit transaction
        $conn->commit();
        $message = 'Medical records updated successfully!';

    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $error = 'Error updating records: ' . $e->getMessage();
    }

    $conn->close();
}
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

        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.3);
        }

        .btn-outline {
            background: white;
            border: 2px solid #e2e8f0;
            color: #64748b;
        }

        .btn-outline:hover {
            background: #f8fafc;
            border-color: #6366f1;
            color: #6366f1;
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

        /* Status Badge */
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
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

        /* Doctor Card */
        .doctor-card {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            padding: 1.5rem;
            background: #f8fafc;
            border-radius: 8px;
            margin: 1.5rem 0;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #6366f1;
        }

        .doctor-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
        }

        .doctor-info h3 {
            color: #1e293b;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .doctor-info p {
            color: #64748b;
            margin: 0.25rem 0;
            font-size: 0.95rem;
        }

        /* Rating Stars */
        .rating-stars {
            color: #fbbf24;
            font-size: 0.9rem;
        }

        /* Information Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }

        .info-card {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #3b82f6;
        }

        .info-card h4 {
            color: #1e293b;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .info-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .info-label {
            color: #64748b;
            font-weight: 500;
            flex: 1;
        }

        .info-value {
            color: #1e293b;
            font-weight: 600;
            flex: 1;
            text-align: right;
        }

        /* Medical Section */
        .medical-section {
            background: #f0f9ff;
            border-radius: 8px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #0ea5e9;
        }

        .medical-section h4 {
            color: #0369a1;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
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

            .info-grid {
                grid-template-columns: 1fr;
            }

            .doctor-card {
                flex-direction: column;
                text-align: center;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
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
                <h3><?= htmlspecialchars($patient['full_name']) ?></h3>
                <p>Patient ID: #<?= $patient['patient_id'] ?></p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
                <li><a href="search_doctors.php"><i class="fas fa-search"></i> Find Doctors</a></li>
                <li><a href="my_appointments.php" class="active"><i class="fas fa-calendar-alt"></i> My Appointments</a>
                </li>
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
                    <h1>Appointment Details</h1>
                    <p>Appointment ID: #<?= $appointment['appointment_id'] ?></p>
                </div>
                <div class="top-actions">
                    <a href="my_appointments.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Back to Appointments
                    </a>
                    <a href="../logout.php" class="btn logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <!-- Appointment Details -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-info-circle"></i> Appointment Information</h2>
                    <span class="status-badge status-<?= $appointment['status'] ?>">
                        <i class="fas fa-circle"></i>
                        <?= ucfirst($appointment['status']) ?>
                    </span>
                </div>

                <!-- Doctor Information -->
                <div class="doctor-card">
                    <div class="doctor-avatar">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <div class="doctor-info">
                        <h3>Dr. <?= htmlspecialchars($appointment['doctor_name']) ?></h3>
                        <p><i class="fas fa-stethoscope"></i>
                            <?= htmlspecialchars($appointment['specialization_name']) ?></p>
                        <p><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($appointment['city_name']) ?></p>
                        <?php if ($appointment['doctor_rating'] > 0): ?>
                            <div class="rating-stars">
                                <?= displayStars($appointment['doctor_rating']) ?>
                                <span class="text-muted ms-2">(<?= $appointment['doctor_rating'] ?> •
                                    <?= $appointment['total_ratings'] ?? 0 ?> reviews)</span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($appointment['qualification'])): ?>
                            <p><i class="fas fa-graduation-cap"></i> <?= htmlspecialchars($appointment['qualification']) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Information Grid -->
                <div class="info-grid">
                    <div class="info-card">
                        <h4><i class="fas fa-calendar"></i> Appointment Details</h4>
                        <div class="info-item">
                            <span class="info-label">Date:</span>
                            <span
                                class="info-value"><?= date('F j, Y', strtotime($appointment['appointment_date'])) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Time:</span>
                            <span
                                class="info-value"><?= date('g:i A', strtotime($appointment['appointment_time'])) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Status:</span>
                            <span class="info-value"><?= ucfirst($appointment['status']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Reference ID:</span>
                            <span class="info-value">#<?= $appointment['appointment_id'] ?></span>
                        </div>
                    </div>

                    <div class="info-card">
                        <h4><i class="fas fa-user-md"></i> Doctor Information</h4>
                        <div class="info-item">
                            <span class="info-label">Name:</span>
                            <span class="info-value">Dr. <?= htmlspecialchars($appointment['doctor_name']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Specialization:</span>
                            <span class="info-value"><?= htmlspecialchars($appointment['specialization_name']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Location:</span>
                            <span class="info-value"><?= htmlspecialchars($appointment['city_name']) ?></span>
                        </div>
                        <?php if (!empty($appointment['qualification'])): ?>
                            <div class="info-item">
                                <span class="info-label">Qualification:</span>
                                <span class="info-value"><?= htmlspecialchars($appointment['qualification']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Symptoms -->
                <?php if (!empty($appointment['symptoms'])): ?>
                    <div class="medical-section">
                        <h4><i class="fas fa-notes-medical"></i> Chief Complaints</h4>
                        <p><?= nl2br(htmlspecialchars($appointment['symptoms'])) ?></p>
                    </div>
                <?php endif; ?>

                <!-- Medical Records (if completed) -->
                <?php if ($is_completed && (!empty($appointment['diagnosis']) || !empty($appointment['prescription']) || !empty($appointment['notes']))): ?>
                    <div class="medical-section">
                        <h4><i class="fas fa-file-medical"></i> Medical Records</h4>

                        <?php if (!empty($appointment['diagnosis'])): ?>
                            <div class="mb-3">
                                <strong>Diagnosis:</strong>
                                <p class="mt-1"><?= nl2br(htmlspecialchars($appointment['diagnosis'])) ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($appointment['prescription'])): ?>
                            <div class="mb-3">
                                <strong>Prescription:</strong>
                                <p class="mt-1"><?= nl2br(htmlspecialchars($appointment['prescription'])) ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($appointment['notes'])): ?>
                            <div class="mb-3">
                                <strong>Additional Notes:</strong>
                                <p class="mt-1"><?= nl2br(htmlspecialchars($appointment['notes'])) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <?php if ($is_completed && empty($appointment['rating'])): ?>
                        <a href="rate_doctor.php?appointment_id=<?= $appointment['appointment_id'] ?>&doctor_id=<?= $appointment['doctor_id'] ?>"
                            class="btn btn-primary">
                            <i class="fas fa-star"></i> Rate This Appointment
                        </a>
                    <?php elseif ($is_completed && !empty($appointment['rating'])): ?>
                        <button class="btn btn-outline" disabled>
                            <i class="fas fa-check"></i> Already Rated
                        </button>
                    <?php endif; ?>

                    <a href="my_appointments.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Back to Appointments
                    </a>

                    <?php if ($is_scheduled): ?>
                        <form method="POST" action="my_appointments.php" class="d-inline">
                            <input type="hidden" name="appointment_id" value="<?= $appointment['appointment_id'] ?>">
                            <button type="submit" name="cancel_appointment" class="btn btn-danger"
                                onclick="return confirm('Are you sure you want to cancel this appointment?')">
                                <i class="fas fa-times"></i> Cancel Appointment
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>