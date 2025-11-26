<?php
// patient/medical_records.php - FIXED VERSION
require_once '../config.php';
requireRole('patient');

$patient = getPatientByUserId($_SESSION['user_id']);
if (!$patient) {
    echo '<div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">';
    echo '<h2>Patient Profile Not Found</h2>';
    echo '<p>Please contact the administrator to set up your profile.</p>';
    echo '<a href="../logout.php" style="padding: 10px 20px; background: #dc2626; color: white; text-decoration: none; border-radius: 5px;">Logout</a>';
    echo '</div>';
    exit();
}

$conn = getDBConnection();

// Create medical_records table if it doesn't exist
$create_table = "
    CREATE TABLE IF NOT EXISTS medical_records (
        record_id INT PRIMARY KEY AUTO_INCREMENT,
        patient_id INT NOT NULL,
        doctor_id INT,
        appointment_id INT,
        record_type VARCHAR(100) NOT NULL,
        description TEXT,
        file_path VARCHAR(500),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
        FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id) ON DELETE SET NULL,
        FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE SET NULL
    )
";

if ($conn->query($create_table) === FALSE) {
    die("Error creating table: " . $conn->error);
}

// FIXED QUERY - Simplified to get records for current patient
$sql = "
    SELECT 
        mr.record_id,
        mr.record_type,
        mr.description,
        mr.file_path,
        mr.created_at,
        mr.appointment_id,
        COALESCE(d.full_name, 'Unknown Doctor') as doctor_name,
        a.appointment_date
    FROM medical_records mr
    LEFT JOIN doctors d ON mr.doctor_id = d.doctor_id
    LEFT JOIN appointments a ON mr.appointment_id = a.appointment_id
    WHERE mr.patient_id = ?
    ORDER BY mr.created_at DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Error preparing query: " . $conn->error);
}
$stmt->bind_param("i", $patient['patient_id']);
if (!$stmt->execute()) {
    die("Error executing query: " . $stmt->error);
}
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt->close();
$conn->close();

// Helper function to safely escape strings for HTML
function safeHtml($value) {
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
    <title>Medical Records - CARE Group</title>
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

        .btn-info {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            color: white;
        }

        .btn-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(14, 165, 233, 0.3);
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

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
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

        /* Record Cards */
        .record-card {
            background: #f8fafc;
            border-left: 4px solid #3b82f6;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s;
            border: 1px solid #e2e8f0;
        }

        .record-card:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transform: translateX(5px);
        }

        .record-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .record-info h4 {
            color: #1e293b;
            margin-bottom: 0.25rem;
            font-weight: 600;
        }

        .record-info p {
            color: #64748b;
            font-size: 0.9rem;
            margin: 0.25rem 0;
        }

        .record-meta {
            display: flex;
            gap: 2rem;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 1px solid #e2e8f0;
            flex-wrap: wrap;
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

        .meta-item a {
            color: #3b82f6;
            text-decoration: none;
        }

        .meta-item a:hover {
            text-decoration: underline;
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

        .debug-info {
            background: #fef3c7;
            border: 1px solid #fbbf24;
            border-left: 4px solid #f59e0b;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .debug-info strong {
            color: #92400e;
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
                flex-wrap: wrap;
            }

            .record-header {
                flex-direction: column;
                gap: 1rem;
            }

            .record-meta {
                flex-direction: column;
                gap: 1rem;
            }
        }

        /* Scrollbar Styles */
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

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="welcome-text">
                    <h1>Medical Records</h1>
                    <p>View your health records and medical history</p>
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

            <!-- Debug Info (Remove this after fixing) -->
            <!-- <div class="debug-info">
                <strong>Debug Info:</strong> Patient ID: <?= $patient['patient_id'] ?> | 
                Total Records Found: <?= count($records) ?> | 
                <a href="../debug_medical_records.php" style="color: #92400e; text-decoration: underline;">Run Debug Script</a>
            </div> -->

            <!-- Medical Records Section -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-file-medical"></i> Medical Records (<?= count($records) ?>)</h2>
                    <a href="search_doctors.php" class="btn btn-primary">
                        <i class="fas fa-search"></i> Find a Doctor
                    </a>
                </div>

                <?php if (count($records) > 0): ?>
                    <?php foreach ($records as $record): ?>
                        <div class="record-card">
                            <div class="record-header">
                                <div class="record-info">
                                    <h4><?= safeHtml($record['record_type']) ?></h4>
                                    <p><i class="fas fa-user-md"></i> By Dr. <?= safeHtml($record['doctor_name']) ?></p>
                                    <?php if ($record['appointment_date']): ?>
                                        <p><i class="fas fa-calendar"></i> Appointment: <?= formatDate($record['appointment_date']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <a href="view_record.php?record_id=<?= $record['record_id'] ?>" class="btn btn-info btn-sm">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                            </div>
                            
                            <div class="record-meta">
                                <div class="meta-item">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span>Created: <?= formatDate($record['created_at']) ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-hashtag"></i>
                                    <span>Record ID: #<?= $record['record_id'] ?></span>
                                </div>
                                <?php if (!empty($record['file_path'])): ?>
                                    <div class="meta-item">
                                        <i class="fas fa-file-pdf"></i>
                                        <span><a href="<?= safeHtml($record['file_path']) ?>" target="_blank">Download File</a></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($record['description'])): ?>
                                <div style="margin-top: 1rem; padding: 1rem; background: white; border-radius: 6px; border-left: 3px solid #10b981;">
                                    <strong>Description:</strong>
                                    <p style="margin-top: 0.5rem; color: #64748b; line-height: 1.5;">
                                        <?= nl2br(safeHtml(substr($record['description'], 0, 200))) ?>
                                        <?php if (strlen($record['description']) > 200): ?>
                                            <span>... <a href="view_record.php?record_id=<?= $record['record_id'] ?>" style="color: #3b82f6;">Read more</a></span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-medical-alt"></i>
                        <h3>No Medical Records Found</h3>
                        <p>Your medical records will appear here after consultations with doctors.</p>
                        <p style="margin-top: 1rem; color: #f59e0b;">
                            <strong>Note:</strong> If you've had appointments with diagnosis/prescription, 
                            the records may not have been created properly. Ask your doctor to update the appointment details.
                        </p>
                        <a href="search_doctors.php" class="btn btn-primary" style="margin-top: 1rem;">
                            <i class="fas fa-search"></i> Find a Doctor
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>