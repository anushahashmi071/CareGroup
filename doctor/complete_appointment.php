<?php
// doctor/complete_appointment.php
require_once '../config.php';
require_once '../helpers.php';
requireRole('doctor');

$doctor = getDoctorByUserId($_SESSION['user_id']);
if (!$doctor) {
    die("Doctor profile not found.");
}

if (!isset($_GET['id'])) {
    header("Location: appointments.php");
    exit();
}

$appointment_id = intval($_GET['id']);
$conn = getDBConnection();

// Fetch appointment details
$stmt = $conn->prepare("
    SELECT a.*, p.full_name as patient_name, p.phone, p.email, p.gender, p.date_of_birth, p.blood_group
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    WHERE a.appointment_id = ? AND a.doctor_id = ?
");
$stmt->bind_param("ii", $appointment_id, $doctor['doctor_id']);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$appointment) {
    $_SESSION['error'] = "Appointment not found or access denied.";
    header("Location: appointments.php");
    exit();
}

if ($appointment['status'] === 'completed') {
    $_SESSION['info'] = "This appointment is already completed.";
    header("Location: view_appointment.php?id=" . $appointment_id);
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $prescription = trim($_POST['prescription'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $treatment = trim($_POST['treatment'] ?? '');
    $follow_up_date = !empty($_POST['follow_up_date']) ? $_POST['follow_up_date'] : null;
    $rating = isset($_POST['rating']) ? intval($_POST['rating']) : null;
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update appointment with medical details and mark as completed
        $update_sql = "
            UPDATE appointments 
            SET status = 'completed', 
                diagnosis = ?, 
                prescription = ?, 
                notes = ?, 
                treatment = ?,
                follow_up_date = ?,
                rating = ?,
                completed_at = NOW()
            WHERE appointment_id = ? AND doctor_id = ?
        ";
        
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("sssssiii", $diagnosis, $prescription, $notes, $treatment, $follow_up_date, $rating, $appointment_id, $doctor['doctor_id']);
        
        if (!$stmt->execute()) {
            throw new Exception("Error updating appointment: " . $stmt->error);
        }
        $stmt->close();
        
        // Create medical record entry
        $record_sql = "
            INSERT INTO medical_records 
            (patient_id, doctor_id, appointment_id, record_type, description, created_at) 
            VALUES (?, ?, ?, 'Consultation', ?, NOW())
        ";
        
        $record_description = "Appointment completed on " . date('Y-m-d');
        if (!empty($diagnosis)) {
            $record_description .= "\n\nDiagnosis: " . $diagnosis;
        }
        if (!empty($treatment)) {
            $record_description .= "\n\nTreatment: " . $treatment;
        }
        
        $stmt = $conn->prepare($record_sql);
        $stmt->bind_param("iiis", $appointment['patient_id'], $doctor['doctor_id'], $appointment_id, $record_description);
        
        if (!$stmt->execute()) {
            throw new Exception("Error creating medical record: " . $stmt->error);
        }
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success'] = "Appointment marked as completed successfully!";
        header("Location: view_appointment.php?id=" . $appointment_id);
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['error'] = "Error completing appointment: " . $e->getMessage();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Appointment - CARE Group</title>
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

        .patient-info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
        }

        .patient-info-header {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .patient-avatar {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            border: 3px solid rgba(255, 255, 255, 0.3);
        }

        .patient-details h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
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
            font-size: 0.95rem;
            opacity: 0.9;
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

        .rating-stars {
            display: flex;
            gap: 0.5rem;
            margin: 0.5rem 0;
        }

        .rating-star {
            font-size: 1.5rem;
            color: #e2e8f0;
            cursor: pointer;
            transition: all 0.2s;
        }

        .rating-star:hover,
        .rating-star.active {
            color: var(--warning);
            transform: scale(1.2);
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .appointment-details {
            background: var(--light);
            padding: 1.5rem;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
            margin-bottom: 2rem;
        }

        .appointment-details h5 {
            color: var(--dark);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .detail-label {
            font-weight: 600;
            color: var(--gray);
            font-size: 0.9rem;
        }

        .detail-value {
            color: var(--dark);
            font-weight: 500;
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

            .patient-info-header {
                flex-direction: column;
                text-align: center;
            }

            .patient-meta {
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
                    <h1>Complete Appointment</h1>
                    <p>Mark appointment as completed and add medical details</p>
                </div>
                <div>
                    <a href="view_appointment.php?id=<?= $appointment_id ?>" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Back to Appointment
                    </a>
                </div>
            </div>

            <!-- Patient Information -->
            <div class="patient-info-card">
                <div class="patient-info-header">
                    <div class="patient-avatar">
                        <?= strtoupper(substr($appointment['patient_name'], 0, 1)) ?>
                    </div>
                    <div class="patient-details">
                        <h3><?= safe_html($appointment['patient_name']) ?></h3>
                        <p>Appointment ID: #<?= $appointment_id ?></p>
                    </div>
                </div>
                <div class="patient-meta">
                    <div class="meta-item">
                        <i class="fas fa-phone"></i>
                        <?= safe_html($appointment['phone']) ?>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-venus-mars"></i>
                        <?= safe_html($appointment['gender']) ?>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-birthday-cake"></i>
                        <?= calculateAge($appointment['date_of_birth']) ?> years
                    </div>
                    <?php if ($appointment['blood_group']): ?>
                        <div class="meta-item">
                            <i class="fas fa-tint"></i>
                            Blood Group: <?= safe_html($appointment['blood_group']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Appointment Details -->
            <div class="appointment-details">
                <h5><i class="fas fa-calendar-alt"></i> Appointment Information</h5>
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">Appointment Date</span>
                        <span class="detail-value"><?= formatDate($appointment['appointment_date']) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Appointment Time</span>
                        <span class="detail-value"><?= formatTime($appointment['appointment_time']) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Status</span>
                        <span class="detail-value" style="color: var(--warning); font-weight: 600;">
                            <?= ucfirst($appointment['status']) ?>
                        </span>
                    </div>
                    <?php if ($appointment['symptoms']): ?>
                        <div class="detail-item" style="grid-column: 1 / -1;">
                            <span class="detail-label">Reported Symptoms</span>
                            <span class="detail-value"><?= safe_html($appointment['symptoms']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Medical Details Form -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-file-medical-alt"></i> Medical Details</h2>
                    <span style="color: var(--success); font-weight: 600;">
                        <i class="fas fa-check-circle"></i> Completing Appointment
                    </span>
                </div>

                <form method="POST">
                    <!-- Diagnosis -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-stethoscope"></i> Diagnosis *
                        </label>
                        <textarea name="diagnosis" class="form-control" rows="5" required
                            placeholder="Enter diagnosis details..."><?= safe_html($appointment['diagnosis'] ?? '') ?></textarea>
                    </div>

                    <!-- Treatment -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-hand-holding-medical"></i> Treatment / Procedure
                        </label>
                        <textarea name="treatment" class="form-control" rows="4"
                            placeholder="Enter treatment details or procedures performed..."><?= safe_html($appointment['treatment'] ?? '') ?></textarea>
                    </div>

                    <!-- Prescription -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-prescription"></i> Prescription
                        </label>
                        <textarea name="prescription" class="form-control" rows="5"
                            placeholder="Enter prescription details (medications, dosage, frequency)..."><?= safe_html($appointment['prescription'] ?? '') ?></textarea>
                    </div>

                    <!-- Additional Notes -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-notes-medical"></i> Additional Notes
                        </label>
                        <textarea name="notes" class="form-control" rows="4"
                            placeholder="Any additional observations, recommendations, or follow-up instructions..."><?= safe_html($appointment['notes'] ?? '') ?></textarea>
                    </div>

                    <!-- Follow-up and Rating -->
                    <div class="form-row">
                        <!-- Follow-up Date -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-calendar-plus"></i> Follow-up Date
                            </label>
                            <input type="date" name="follow_up_date" class="form-control" 
                                value="<?= $appointment['follow_up_date'] ?? '' ?>" 
                                min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                            <small style="color: var(--gray); margin-top: 0.5rem; display: block;">
                                Optional: Schedule a follow-up appointment
                            </small>
                        </div>

                        <!-- Rating -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-star"></i> Service Rating
                            </label>
                            <div class="rating-stars" id="ratingStars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <span class="rating-star" data-rating="<?= $i ?>">
                                        <i class="fas fa-star"></i>
                                    </span>
                                <?php endfor; ?>
                            </div>
                            <input type="hidden" name="rating" id="selectedRating" value="<?= $appointment['rating'] ?? '' ?>">
                            <small style="color: var(--gray); margin-top: 0.5rem; display: block;">
                                Rate the appointment service (optional)
                            </small>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check-circle"></i> Complete Appointment
                        </button>
                        <a href="view_appointment.php?id=<?= $appointment_id ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <a href="patient_details.php?patient_id=<?= $appointment['patient_id'] ?>" class="btn btn-outline">
                            <i class="fas fa-user"></i> View Patient Details
                        </a>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Star rating functionality
        document.addEventListener('DOMContentLoaded', function() {
            const stars = document.querySelectorAll('.rating-star');
            const ratingInput = document.getElementById('selectedRating');
            
            // Set initial rating if exists
            const initialRating = <?= $appointment['rating'] ?? 0 ?>;
            if (initialRating > 0) {
                setRating(initialRating);
            }
            
            stars.forEach(star => {
                star.addEventListener('click', function() {
                    const rating = parseInt(this.getAttribute('data-rating'));
                    setRating(rating);
                });
                
                star.addEventListener('mouseover', function() {
                    const rating = parseInt(this.getAttribute('data-rating'));
                    highlightStars(rating);
                });
            });
            
            // Reset stars when mouse leaves the container
            document.getElementById('ratingStars').addEventListener('mouseleave', function() {
                const currentRating = parseInt(ratingInput.value) || 0;
                highlightStars(currentRating);
            });
            
            function setRating(rating) {
                ratingInput.value = rating;
                highlightStars(rating);
            }
            
            function highlightStars(rating) {
                stars.forEach(star => {
                    const starRating = parseInt(star.getAttribute('data-rating'));
                    if (starRating <= rating) {
                        star.classList.add('active');
                        star.querySelector('i').style.color = '#f59e0b';
                    } else {
                        star.classList.remove('active');
                        star.querySelector('i').style.color = '#e2e8f0';
                    }
                });
            }
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

        // Character counters
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

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const diagnosis = document.querySelector('textarea[name="diagnosis"]');
            if (!diagnosis.value.trim()) {
                e.preventDefault();
                alert('Please enter a diagnosis before completing the appointment.');
                diagnosis.focus();
                return false;
            }
        });
    </script>
</body>

</html>