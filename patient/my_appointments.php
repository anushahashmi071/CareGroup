<?php
// patient/my_appointments.php
require_once '../config.php';
requireRole('patient');

$patient = getPatientByUserId($_SESSION['user_id']);
if (!$patient) {
    header("Location: dashboard.php");
    exit();
}

$conn = getDBConnection();
$message = '';

// Handle appointment cancellation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_appointment'])) {
    $appointment_id = intval($_POST['appointment_id']);
    
    $stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled' WHERE appointment_id = ? AND patient_id = ? AND status = 'scheduled'");
    $stmt->bind_param("ii", $appointment_id, $patient['patient_id']);
    
    if ($stmt->execute()) {
        $message = '<div class="alert alert-success">Appointment cancelled successfully!</div>';
    } else {
        $message = '<div class="alert alert-danger">Error cancelling appointment.</div>';
    }
    $stmt->close();
}

// Get patient's appointments
$stmt = $conn->prepare("
    SELECT a.*, 
           d.full_name as doctor_name,
           d.rating as doctor_rating,
           s.specialization_name,
           c.city_name
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.doctor_id
    JOIN specializations s ON d.specialization_id = s.specialization_id
    JOIN cities c ON d.city_id = c.city_id
    WHERE a.patient_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->bind_param("i", $patient['patient_id']);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - CARE Group</title>
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

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
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

        .appointment-card.missed {
            border-left-color: #f59e0b;
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

        .status-missed {
            background: #fef3c7;
            color: #92400e;
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

        /* Rating Stars */
        .rating-stars {
            color: #fbbf24;
            font-size: 0.9rem;
        }

        /* Alert Styles */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border: 1px solid transparent;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-color: #a7f3d0;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fecaca;
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

            .appointment-meta {
                flex-direction: column;
                gap: 1rem;
            }

            .doctor-info {
                flex-direction: column;
                text-align: center;
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
                <li><a href="my_appointments.php" class="active"><i class="fas fa-calendar-alt"></i> My Appointments</a></li>
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
                    <h1>My Appointments</h1>
                    <p>Manage and view your medical appointments</p>
                </div>
                <div class="top-actions">
                    <a href="dashboard.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <a href="../logout.php" class="btn logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <?= $message ?>

            <!-- Appointments Section -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-calendar-alt"></i> All Appointments</h2>
                    <a href="search_doctors.php" class="btn btn-primary">
                        <i class="fas fa-search"></i> Find Doctors
                    </a>
                </div>

                <?php if (empty($appointments)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No Appointments Found</h3>
                        <p>You haven't booked any appointments yet.</p>
                        <a href="search_doctors.php" class="btn btn-primary" style="margin-top: 1rem;">
                            <i class="fas fa-search"></i> Find Doctors
                        </a>
                    </div>
                <?php else: ?>
                    <div class="appointments-container">
                        <?php foreach ($appointments as $appointment): ?>
                            <div class="appointment-card <?= $appointment['status'] ?>">
                                <div class="appointment-header">
                                    <div class="doctor-info">
                                        <div class="doctor-avatar">
                                            <i class="fas fa-user-md"></i>
                                        </div>
                                        <div class="doctor-details">
                                            <h4>Dr. <?= htmlspecialchars($appointment['doctor_name']) ?></h4>
                                            <p><?= htmlspecialchars($appointment['specialization_name']) ?></p>
                                            <p><?= htmlspecialchars($appointment['city_name']) ?></p>
                                            <?php if ($appointment['doctor_rating'] > 0): ?>
                                                <div class="rating-stars">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star<?= $i <= $appointment['doctor_rating'] ? '' : '-half-alt' ?>"></i>
                                                    <?php endfor; ?>
                                                    <small class="text-muted">(<?= $appointment['doctor_rating'] ?>)</small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <span class="status-badge status-<?= $appointment['status'] ?>">
                                        <?= ucfirst($appointment['status']) ?>
                                    </span>
                                </div>

                                <div class="appointment-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <span><?= date('F j, Y', strtotime($appointment['appointment_date'])) ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-clock"></i>
                                        <span><?= date('g:i A', strtotime($appointment['appointment_time'])) ?></span>
                                    </div>
                                    <?php if ($appointment['symptoms']): ?>
                                        <div class="meta-item">
                                            <i class="fas fa-notes-medical"></i>
                                            <span><?= htmlspecialchars($appointment['symptoms']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="appointment-actions">
                                    <?php if ($appointment['status'] === 'scheduled'): ?>
                                        <div class="d-flex gap-2">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="appointment_id" value="<?= $appointment['appointment_id'] ?>">
                                                <button type="submit" name="cancel_appointment" class="btn btn-danger btn-sm" 
                                                        onclick="return confirm('Are you sure you want to cancel this appointment?')">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            </form>
                                            <a href="view_appointment.php?id=<?= $appointment['appointment_id'] ?>" class="btn btn-outline btn-sm">
                                                <i class="fas fa-eye"></i> View Details
                                            </a>
                                        </div>
                                    <?php elseif ($appointment['status'] === 'completed'): ?>
                                        <div class="d-flex gap-2">
                                            <a href="view_appointment.php?id=<?= $appointment['appointment_id'] ?>" class="btn btn-outline btn-sm">
                                                <i class="fas fa-eye"></i> View Details
                                            </a>
                                            <?php if (!$appointment['rating']): ?>
                                                <a href="rate_doctor.php?appointment_id=<?= $appointment['appointment_id'] ?>&doctor_id=<?= $appointment['doctor_id'] ?>" 
                                                   class="btn btn-success btn-sm">
                                                    <i class="fas fa-star"></i> Rate Doctor
                                                </a>
                                            <?php else: ?>
                                                <span class="btn btn-outline btn-sm disabled">
                                                    <i class="fas fa-check"></i> Rated
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <a href="view_appointment.php?id=<?= $appointment['appointment_id'] ?>" class="btn btn-outline btn-sm">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>