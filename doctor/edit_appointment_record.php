<?php
// doctor/edit_appointment_record.php
require_once '../config.php';
require_once '../helpers.php';
requireRole('doctor');

$doctor = getDoctorByUserId($_SESSION['user_id']);
if (!$doctor) {
    die("Unauthorized.");
}

if (!isset($_GET['appointment_id']) || !isset($_GET['patient_id'])) {
    header("Location: patients.php");
    exit();
}

$appointment_id = intval($_GET['appointment_id']);
$patient_id = intval($_GET['patient_id']);
$conn = getDBConnection();

// Fetch appointment
$stmt = $conn->prepare("
    SELECT a.*, p.full_name as patient_name 
    FROM appointments a 
    JOIN patients p ON a.patient_id = p.patient_id 
    WHERE a.appointment_id = ? AND a.doctor_id = ? AND a.patient_id = ?
");
$stmt->bind_param("iii", $appointment_id, $doctor['doctor_id'], $patient_id);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$appointment) {
    die("Appointment not found or access denied.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $prescription = trim($_POST['prescription'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    $stmt = $conn->prepare("
        UPDATE appointments 
        SET diagnosis = ?, prescription = ?, notes = ? 
        WHERE appointment_id = ? AND doctor_id = ?
    ");
    $stmt->bind_param("sssii", $diagnosis, $prescription, $notes, $appointment_id, $doctor['doctor_id']);
    $stmt->execute();
    $stmt->close();

    header("Location: patient_details.php?patient_id=$patient_id#medical-history");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Appointment Record - CARE Group</title>
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

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
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

        .appointment-info {
            background: var(--light);
            padding: 1.5rem;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
            margin-bottom: 2rem;
        }

        .appointment-info h5 {
            color: var(--dark);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .appointment-info p {
            color: var(--gray);
            margin: 0.25rem 0;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
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
                    <h1>Edit Medical Record</h1>
                    <p>Update diagnosis, prescription, and notes for this appointment</p>
                </div>
                <div>
                    <a href="patient_details.php?patient_id=<?= $patient_id ?>#medical-history" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Back to Patient
                    </a>
                </div>
            </div>

            <!-- Appointment Information -->
            <div class="appointment-info">
                <h5><i class="fas fa-calendar-check"></i> Appointment Details</h5>
                <p><strong>Patient:</strong> <?= safe_html($appointment['patient_name']) ?></p>
                <p><strong>Date:</strong> <?= formatDate($appointment['appointment_date']) ?></p>
                <p><strong>Time:</strong> <?= formatTime($appointment['appointment_time']) ?></p>
                <?php if ($appointment['symptoms']): ?>
                    <p><strong>Symptoms:</strong> <?= safe_html($appointment['symptoms']) ?></p>
                <?php endif; ?>
            </div>

            <!-- Edit Form -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-edit"></i> Medical Record Details</h2>
                </div>

                <form method="POST">
                    <!-- Diagnosis -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-stethoscope"></i> Diagnosis
                        </label>
                        <textarea name="diagnosis" class="form-control" rows="5"
                            placeholder="Enter diagnosis details..."><?= safe_html($appointment['diagnosis'] ?? '') ?></textarea>
                    </div>

                    <!-- Prescription -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-prescription"></i> Prescription
                        </label>
                        <textarea name="prescription" class="form-control" rows="5"
                            placeholder="Enter prescription details..."><?= safe_html($appointment['prescription'] ?? '') ?></textarea>
                    </div>

                    <!-- Additional Notes -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-notes-medical"></i> Additional Notes
                        </label>
                        <textarea name="notes" class="form-control" rows="4"
                            placeholder="Any additional observations or recommendations..."><?= safe_html($appointment['notes'] ?? '') ?></textarea>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <a href="patient_details.php?patient_id=<?= $patient_id ?>#medical-history" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <a href="patient_details.php?patient_id=<?= $patient_id ?>" class="btn btn-outline">
                            <i class="fas fa-user"></i> Back to Patient Details
                        </a>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Add character counters for textareas
        document.addEventListener('DOMContentLoaded', function() {
            const textareas = document.querySelectorAll('textarea');
            textareas.forEach(textarea => {
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
            autoResize(textarea); // Initial resize
        });
    </script>
</body>

</html>