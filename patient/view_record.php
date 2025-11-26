<?php
// patient/view_record.php - FIXED to show Diagnosis, Prescription, Notes
require_once '../config.php';
requireRole('patient');

$patient = getPatientByUserId($_SESSION['user_id']);
if (!$patient) {
    header("Location: dashboard.php");
    exit();
}

if (!isset($_GET['record_id'])) {
    header("Location: medical_records.php");
    exit();
}

$record_id = intval($_GET['record_id']);

$conn = getDBConnection();

// FIXED QUERY - Get medical record WITH appointment diagnosis, prescription, notes
$sql = "
    SELECT mr.*, 
           d.full_name as doctor_name,
           d.qualification,
           d.specialization_id,
           s.specialization_name,
           a.appointment_date,
           a.appointment_time,
           a.symptoms,
           a.diagnosis,
           a.prescription,
           a.notes
    FROM medical_records mr
    LEFT JOIN doctors d ON mr.doctor_id = d.doctor_id
    LEFT JOIN specializations s ON d.specialization_id = s.specialization_id
    LEFT JOIN appointments a ON mr.appointment_id = a.appointment_id
    WHERE mr.record_id = ? AND mr.patient_id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Error preparing query: " . $conn->error);
}
$stmt->bind_param("ii", $record_id, $patient['patient_id']);
if (!$stmt->execute()) {
    die("Error executing query: " . $stmt->error);
}
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $error = "Medical record not found or you don't have permission to view it.";
    $record = null;
} else {
    $record = $result->fetch_assoc();
}

$stmt->close();
$conn->close();

function safeHtml($value)
{
    if ($value === null || $value === '') {
        return 'N/A';
    }
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Record Details - CARE Group</title>
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

        .medical-section {
            background: #f0f9ff;
            border-radius: 8px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            border: 1px solid #bae6fd;
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

        .medical-content {
            background: white;
            padding: 1rem;
            border-radius: 6px;
            border: 1px solid #e0f2fe;
        }

        .medical-content p {
            color: #0c4a6e;
            line-height: 1.8;
            margin: 0;
            white-space: pre-wrap;
        }

        .alert {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-left: 4px solid #ef4444;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 2rem 0;
        }

        .alert h4 {
            color: #991b1b;
            margin-bottom: 0.5rem;
        }

        .alert p {
            color: #7f1d1d;
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
                flex-wrap: wrap;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .doctor-card {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="patient-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <h3><?= safeHtml($patient['full_name']) ?></h3>
                <p>Patient ID: #<?= $patient['patient_id'] ?></p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
                <li><a href="search_doctors.php"><i class="fas fa-search"></i> Find Doctors</a></li>
                <li><a href="my_appointments.php"><i class="fas fa-calendar-alt"></i> My Appointments</a></li>
                <li><a href="medical_records.php" class="active"><i class="fas fa-file-medical"></i> Medical Records</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="health_tips.php"><i class="fas fa-heartbeat"></i> Health Tips</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <div class="welcome-text">
                    <h1>Medical Record Details</h1>
                    <?php if ($record): ?>
                        <p>Record ID: #<?= $record['record_id'] ?></p>
                    <?php endif; ?>
                </div>
                <div class="top-actions">
                    <a href="medical_records.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Back to Records
                    </a>
                    <a href="../logout.php" class="btn logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <?php if ($record === null): ?>
                <div class="content-section">
                    <div class="alert">
                        <h4><i class="fas fa-exclamation-triangle"></i> Record Not Found</h4>
                        <p><?= safeHtml($error) ?></p>
                        <a href="medical_records.php" class="btn btn-primary" style="margin-top: 1rem;">
                            <i class="fas fa-arrow-left"></i> Back to Medical Records
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="content-section">
                    <div class="section-header">
                        <h2><i class="fas fa-file-medical"></i> <?= safeHtml($record['record_type']) ?></h2>
                        <span style="color: #64748b; font-weight: 500;">
                            Created: <?= formatDate($record['created_at']) ?>
                        </span>
                    </div>

                    <div class="doctor-card">
                        <div class="doctor-avatar">
                            <i class="fas fa-user-md"></i>
                        </div>
                        <div class="doctor-info">
                            <h3>Dr. <?= safeHtml($record['doctor_name']) ?></h3>
                            <p><i class="fas fa-stethoscope"></i> <?= safeHtml($record['specialization_name']) ?></p>
                            <?php if (!empty($record['qualification'])): ?>
                                <p><i class="fas fa-graduation-cap"></i> <?= safeHtml($record['qualification']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="info-grid">
                        <div class="info-card">
                            <h4><i class="fas fa-info-circle"></i> Record Information</h4>
                            <div class="info-item">
                                <span class="info-label">Record Type:</span>
                                <span class="info-value"><?= safeHtml($record['record_type']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Record ID:</span>
                                <span class="info-value">#<?= $record['record_id'] ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Created Date:</span>
                                <span class="info-value"><?= formatDate($record['created_at']) ?></span>
                            </div>
                        </div>

                        <div class="info-card">
                            <h4><i class="fas fa-calendar"></i> Appointment Details</h4>
                            <?php if ($record['appointment_date']): ?>
                                <div class="info-item">
                                    <span class="info-label">Date:</span>
                                    <span class="info-value"><?= formatDate($record['appointment_date']) ?></span>
                                </div>
                                <?php if ($record['appointment_time']): ?>
                                    <div class="info-item">
                                        <span class="info-label">Time:</span>
                                        <span class="info-value"><?= date('g:i A', strtotime($record['appointment_time'])) ?></span>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="info-item">
                                    <span class="info-label">Status:</span>
                                    <span class="info-value">Not linked to appointment</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Medical Records Section - Doctor's Input -->
                    <div style="background: #f0fdf4; padding: 1.5rem; border-radius: 12px; border: 2px solid #86efac; margin: 2rem 0;">
                        <h3 style="color: #065f46; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-user-md"></i> Medical Records from Doctor
                        </h3>

                        <?php if (!empty($record['symptoms'])): ?>
                            <div class="medical-section">
                                <h4><i class="fas fa-notes-medical"></i> Chief Complaints & Symptoms</h4>
                                <div class="medical-content">
                                    <p><?= nl2br(safeHtml($record['symptoms'])) ?></p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php 
                        // Check if we have diagnosis, prescription, or notes from appointment
                        $hasMedicalData = !empty($record['diagnosis']) || !empty($record['prescription']) || !empty($record['notes']);
                        ?>

                        <?php if ($hasMedicalData): ?>
                            <!-- Diagnosis -->
                            <?php if (!empty($record['diagnosis'])): ?>
                                <div class="medical-section" style="background: #fef3c7; border-color: #fde047; border-left-color: #eab308;">
                                    <h4 style="color: #854d0e;"><i class="fas fa-stethoscope"></i> Diagnosis</h4>
                                    <div class="medical-content" style="background: #fefce8; border-color: #fef9c3;">
                                        <p style="color: #713f12;"><?= nl2br(safeHtml($record['diagnosis'])) ?></p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="medical-section" style="background: #f3f4f6; border-color: #d1d5db; border-left-color: #9ca3af;">
                                    <h4 style="color: #6b7280;"><i class="fas fa-stethoscope"></i> Diagnosis</h4>
                                    <div class="medical-content" style="background: #f9fafb; border-color: #e5e7eb;">
                                        <p style="color: #6b7280; font-style: italic;">No diagnosis recorded yet</p>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Prescription -->
                            <?php if (!empty($record['prescription'])): ?>
                                <div class="medical-section" style="background: #e0f2fe; border-color: #7dd3fc; border-left-color: #0ea5e9;">
                                    <h4 style="color: #0369a1;"><i class="fas fa-prescription"></i> Prescription</h4>
                                    <div class="medical-content" style="background: #f0f9ff; border-color: #bae6fd;">
                                        <p style="color: #0c4a6e;"><?= nl2br(safeHtml($record['prescription'])) ?></p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="medical-section" style="background: #f3f4f6; border-color: #d1d5db; border-left-color: #9ca3af;">
                                    <h4 style="color: #6b7280;"><i class="fas fa-prescription"></i> Prescription</h4>
                                    <div class="medical-content" style="background: #f9fafb; border-color: #e5e7eb;">
                                        <p style="color: #6b7280; font-style: italic;">No prescription recorded yet</p>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Additional Notes -->
                            <?php if (!empty($record['notes'])): ?>
                                <div class="medical-section" style="background: #f3e8ff; border-color: #d8b4fe; border-left-color: #a855f7;">
                                    <h4 style="color: #6b21a8;"><i class="fas fa-sticky-note"></i> Additional Notes</h4>
                                    <div class="medical-content" style="background: #faf5ff; border-color: #e9d5ff;">
                                        <p style="color: #581c87;"><?= nl2br(safeHtml($record['notes'])) ?></p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="medical-section" style="background: #f3f4f6; border-color: #d1d5db; border-left-color: #9ca3af;">
                                    <h4 style="color: #6b7280;"><i class="fas fa-sticky-note"></i> Additional Notes</h4>
                                    <div class="medical-content" style="background: #f9fafb; border-color: #e5e7eb;">
                                        <p style="color: #6b7280; font-style: italic;">No additional notes recorded yet</p>
                                    </div>
                                </div>
                            <?php endif; ?>

                        <?php else: ?>
                            <div style="text-align: center; padding: 2rem; background: #fef2f2; border-radius: 8px; border: 1px solid #fecaca;">
                                <i class="fas fa-exclamation-circle" style="font-size: 3rem; color: #dc2626; margin-bottom: 1rem;"></i>
                                <h4 style="color: #991b1b; margin-bottom: 0.5rem;">No Medical Records Available</h4>
                                <p style="color: #7f1d1d;">The doctor has not yet added diagnosis, prescription, or notes for this appointment.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div style="display: flex; gap: 1rem; margin-top: 2rem; flex-wrap: wrap;">
                        <a href="medical_records.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Back to Records
                        </a>
                        <button onclick="window.print()" class="btn btn-primary">
                            <i class="fas fa-print"></i> Print Record
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>

</html>