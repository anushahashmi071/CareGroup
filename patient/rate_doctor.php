<?php
// patient/rate_doctor.php
require_once '../config.php';
requireRole('patient');

$patient = getPatientByUserId($_SESSION['user_id']);
if (!$patient) {
    header("Location: dashboard.php");
    exit();
}

$conn = getDBConnection();

$message = '';

updateDoctorRating($doctor_id);

// Add this debug function
function debugDoctorRating($doctor_id) {
    $conn = getDBConnection();
    
    // Check appointments with ratings
    $stmt = $conn->prepare("
        SELECT appointment_id, rating, review 
        FROM appointments 
        WHERE doctor_id = ? AND rating IS NOT NULL
    ");
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $ratings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    error_log("DEBUG: Found " . count($ratings) . " ratings for doctor $doctor_id");
    foreach ($ratings as $r) {
        error_log(" - Appointment {$r['appointment_id']}: Rating {$r['rating']}, Review: {$r['review']}");
    }
     // Check current doctor rating
    $stmt = $conn->prepare("SELECT rating, total_ratings FROM doctors WHERE doctor_id = ?");
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $doctor = $stmt->get_result()->fetch_assoc();
    
    error_log("DEBUG: Doctor $doctor_id current - Rating: {$doctor['rating']}, Total: {$doctor['total_ratings']}");
    
    $stmt->close();
    $conn->close();
}

// Get doctor_id and appointment_id from URL
$doctor_id = isset($_GET['doctor_id']) ? (int) $_GET['doctor_id'] : 0;
$appointment_id = isset($_GET['appointment_id']) ? (int) $_GET['appointment_id'] : 0;

// Validate that appointment_id is provided
if (!$appointment_id) {
    echo '<div style="text-align:center;padding:50px;font-family:Arial,sans-serif;">
            <h2>Appointment Required</h2>
            <p>Please rate a doctor from your appointment history.</p>
            <a href="my_appointments.php" class="btn btn-primary">Back to Appointments</a>
          </div>';
    exit();
}

// Validate doctor exists
$stmt = $conn->prepare("SELECT d.full_name, s.specialization_name, c.city_name FROM doctors d JOIN specializations s ON d.specialization_id = s.specialization_id JOIN cities c ON d.city_id = c.city_id WHERE d.doctor_id = ?");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();

if (!$doctor) {
    echo '<div style="text-align:center;padding:50px;font-family:Arial,sans-serif;">
            <h2>Doctor Not Found</h2>
            <a href="my_appointments.php" class="btn btn-primary">Back to Appointments</a>
          </div>';
    exit();
}

// === CRITICAL VALIDATION: Check if appointment exists and belongs to this patient ===
$stmt = $conn->prepare("
    SELECT a.*, d.full_name as doctor_name, d.doctor_id
    FROM appointments a 
    JOIN doctors d ON a.doctor_id = d.doctor_id 
    WHERE a.appointment_id = ? AND a.patient_id = ? AND a.status = 'completed'
");
$stmt->bind_param("ii", $appointment_id, $patient['patient_id']);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();

if (!$appointment) {
    echo '<div style="text-align:center;padding:50px;font-family:Arial,sans-serif;">
            <h2>Appointment Not Found</h2>
            <p>This appointment does not exist, is not completed, or does not belong to you.</p>
            <a href="my_appointments.php" class="btn btn-primary">Back to Appointments</a>
          </div>';
    exit();
}

// === VALIDATION: Ensure doctor_id from URL matches appointment's doctor ===
if ($appointment['doctor_id'] != $doctor_id) {
    echo '<div style="text-align:center;padding:50px;font-family:Arial,sans-serif;">
            <h2>Invalid Request</h2>
            <p>The doctor does not match this appointment.</p>
            <a href="my_appointments.php" class="btn btn-primary">Back to Appointments</a>
          </div>';
    exit();
}

// === VALIDATION: Check if already rated for this specific appointment ===
$already_rated = false;
$existing_rating = 0;
$existing_review = '';

if ($appointment_id > 0) {
    $stmt = $conn->prepare("
        SELECT rating, review 
        FROM appointments 
        WHERE appointment_id = ? AND patient_id = ? AND status = 'completed'
    ");
    $stmt->bind_param("ii", $appointment_id, $patient['patient_id']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result && $result['rating'] > 0) {
        $already_rated = true;
        $existing_rating = $result['rating'];
        $existing_review = $result['review'] ?? '';
    }
}

// Debug logging
error_log("DEBUG: appointment_id = $appointment_id, patient_id = " . $patient['patient_id']);
error_log("DEBUG: already_rated = " . ($already_rated ? 'YES' : 'NO'));

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$already_rated) {
    $rating = (int) ($_POST['rating'] ?? 0);
    $review = trim($_POST['review'] ?? '');

    // Validate rating
    if ($rating < 1 || $rating > 5) {
        $message = '<div class="alert alert-danger">Please select 1-5 stars.</div>';
    } else {
        // === FINAL VALIDATION: Double-check this appointment can be rated ===
        $stmt = $conn->prepare("
            SELECT rating 
            FROM appointments 
            WHERE appointment_id = ? AND patient_id = ? AND status = 'completed' AND rating IS NULL
        ");
        $stmt->bind_param("ii", $appointment_id, $patient['patient_id']);
        $stmt->execute();
        $can_rate = $stmt->get_result()->fetch_assoc();
        
        if (!$can_rate) {
            $message = '<div class="alert alert-danger">This appointment has already been rated or cannot be rated.</div>';
        } else {
            // Save the rating and review
            error_log("DEBUG: Saving rating=$rating, review='$review', appointment_id=$appointment_id");

            $stmt = $conn->prepare("
                UPDATE appointments 
                SET rating = ?, review = ? 
                WHERE appointment_id = ? AND patient_id = ? AND status = 'completed'
            ");
            $stmt->bind_param("isii", $rating, $review, $appointment_id, $patient['patient_id']);

            if ($stmt->execute()) {
                error_log("DEBUG: Rating saved successfully for appointment $appointment_id");
                
                // Update doctor's average rating - FIXED VERSION
                updateDoctorRating($doctor_id, $conn);
                
                // Call debug function after successful rating submission
                debugDoctorRating($doctor_id);
                
                $message = '<div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> 
                                Thank you! Your rating has been saved successfully.
                            </div>';
                $already_rated = true;
                $existing_rating = $rating;
                $existing_review = $review;
            } else {
                error_log("DEBUG: SQL ERROR: " . $stmt->error);
                $message = '<div class="alert alert-danger">Failed to save rating. Please try again.</div>';
            }
            $stmt->close();
        }
    }
}

$conn->close();

// Improved function to update doctor's rating
function updateDoctorRating($doctor_id, $connection = null) {
    $close_connection = false;
    
    if ($connection === null) {
        $conn = getDBConnection();
        $close_connection = true;
    } else {
        $conn = $connection;
    }
    
    // Calculate new average rating
    $stmt = $conn->prepare("
        SELECT AVG(rating) as avg_rating, COUNT(rating) as total_ratings 
        FROM appointments 
        WHERE doctor_id = ? AND rating IS NOT NULL AND status = 'completed'
    ");
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    error_log("DEBUG: Doctor $doctor_id - Avg: " . $result['avg_rating'] . ", Total: " . $result['total_ratings']);
    
    if ($result && $result['avg_rating'] !== null) {
        $avg_rating = round($result['avg_rating'], 1);
        $total_ratings = $result['total_ratings'];
        
        $update_stmt = $conn->prepare("UPDATE doctors SET rating = ?, total_ratings = ? WHERE doctor_id = ?");
        $update_stmt->bind_param("dii", $avg_rating, $total_ratings, $doctor_id);
        
        if ($update_stmt->execute()) {
            error_log("SUCCESS: Updated doctor $doctor_id rating to $avg_rating based on $total_ratings ratings");
        } else {
            error_log("ERROR: Failed to update doctor rating: " . $update_stmt->error);
        }
        $update_stmt->close();
    } else {
        // No ratings yet, set to 0
        $update_stmt = $conn->prepare("UPDATE doctors SET rating = 0.0, total_ratings = 0 WHERE doctor_id = ?");
        $update_stmt->bind_param("i", $doctor_id);
        $update_stmt->execute();
        $update_stmt->close();
        error_log("DEBUG: Reset doctor $doctor_id rating to 0 (no ratings)");
    }
    
    $stmt->close();
    
    if ($close_connection) {
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Doctor - <?php echo htmlspecialchars(getSetting('site_name')); ?></title>
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

        .logout-btn {
            background: #ef4444;
            color: white;
        }

        .logout-btn:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.3);
        }

        /* Content Section */
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

        /* Doctor Card */
        .doctor-card {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            padding: 1.5rem;
            background: #f8fafc;
            border-radius: 12px;
            margin-bottom: 2rem;
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
            font-size: 1.3rem;
        }

        .doctor-info p {
            color: #64748b;
            font-size: 0.95rem;
            margin-bottom: 0.25rem;
        }

        /* Rating Container */
        .rating-container {
            text-align: center;
            margin: 2rem 0;
        }

        .star-slider {
            -webkit-appearance: none;
            width: 100%;
            max-width: 400px;
            height: 12px;
            background: #e2e8f0;
            border-radius: 10px;
            outline: none;
            margin: 2rem 0;
        }

        .star-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 30px;
            height: 30px;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            border-radius: 50%;
            cursor: pointer;
            border: 3px solid white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .stars-display {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin: 1rem 0;
            font-size: 2.5rem;
        }

        .stars-display i {
            color: #cbd5e1;
            transition: color 0.2s;
            cursor: pointer;
        }

        .stars-display i.filled {
            color: #fbbf24;
        }

        .rating-text {
            font-size: 1.2rem;
            color: #1e293b;
            font-weight: 600;
            margin-top: 1rem;
        }

        /* Review Box */
        .review-box {
            margin-top: 2rem;
        }

        .review-box label {
            display: block;
            margin-bottom: 0.75rem;
            color: #1e293b;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .review-box textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            resize: vertical;
            font-family: inherit;
            font-size: 1rem;
            transition: all 0.3s;
            min-height: 120px;
        }

        .review-box textarea:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        /* Alerts */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            border: 1px solid;
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

        /* Appointment Info */
        .appointment-info {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border-left: 4px solid #10b981;
        }

        .appointment-info p {
            margin-bottom: 0.5rem;
            color: #64748b;
        }

        .appointment-info strong {
            color: #1e293b;
        }

        /* Form Actions */
        .form-actions {
            text-align: center;
            margin-top: 2rem;
        }

        .btn-lg {
            padding: 1rem 2rem;
            font-size: 1.1rem;
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

            .doctor-card {
                flex-direction: column;
                text-align: center;
            }

            .stars-display {
                font-size: 2rem;
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
                    <h1>Rate Your Doctor</h1>
                    <p>Share your experience to help other patients</p>
                </div>
                <div class="top-actions">
                    <a href="my_appointments.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Appointments
                    </a>
                    <a href="../logout.php" class="btn logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <div class="content-section">
                <?= $message ?>

                <!-- Doctor Info -->
                <div class="doctor-card">
                    <div class="doctor-avatar">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <div class="doctor-info">
                        <h3>Dr. <?= htmlspecialchars($doctor['full_name']) ?></h3>
                        <p><i class="fas fa-stethoscope"></i> <?= htmlspecialchars($doctor['specialization_name']) ?></p>
                        <p><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($doctor['city_name']) ?></p>
                    </div>
                </div>

                <!-- Appointment Info -->
                <div class="appointment-info">
                    <p><strong>Appointment Reference:</strong> #<?= $appointment_id ?></p>
                    <p><strong>Appointment Date:</strong> <?= date('F j, Y', strtotime($appointment['appointment_date'])) ?></p>
                    <?php if (!empty($appointment['symptoms'])): ?>
                        <p><strong>Reason:</strong> <?= htmlspecialchars($appointment['symptoms']) ?></p>
                    <?php endif; ?>
                </div>

                <?php if ($already_rated): ?>
                    <div class="alert alert-success text-center">
                        <i class="fas fa-check-circle fa-2x mb-3"></i>
                        <h4>You have already rated this appointment</h4>
                        <div class="stars-display justify-content-center my-3">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star<?= $i <= $existing_rating ? ' filled' : '' ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <p class="mb-2"><strong>Your Rating:</strong> <?= $existing_rating ?> star<?= $existing_rating !== 1 ? 's' : '' ?></p>
                        <?php if (!empty(trim($existing_review))): ?>
                            <p class="mt-3"><strong>Your Review:</strong> "<?= htmlspecialchars($existing_review) ?>"</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <form method="POST" id="ratingForm">
                        <input type="hidden" name="doctor_id" value="<?= $doctor_id ?>">
                        <input type="hidden" name="appointment_id" value="<?= $appointment_id ?>">
                        
                        <label style="font-size:1.2rem; color:#1e293b; font-weight:600; ">
                            How was your experience with this appointment?
                        </label>
                        <div class="rating-container">
                            <input type="range" name="rating" id="rating" min="1" max="5" value="3" class="star-slider"
                                oninput="updateStars(this.value)">
                            <div class="stars-display" id="starsDisplay">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                            <div class="rating-text" id="ratingText">3 Stars - Good</div>
                        </div>

                        <div class="review-box">
                            <label for="review">
                                Write a review (optional)
                            </label>
                            <textarea name="review" id="review" placeholder="Share your experience with this specific appointment..."></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-paper-plane"></i> Submit Rating
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        const ratingDescriptions = [
            "", // index 0
            "1 Star - Poor",
            "2 Stars - Fair", 
            "3 Stars - Good",
            "4 Stars - Very Good",
            "5 Stars - Excellent"
        ];

        function updateStars(value) {
            const stars = document.querySelectorAll('#starsDisplay i');
            const text = document.getElementById('ratingText');

            stars.forEach((star, index) => {
                if (index < value) {
                    star.classList.add('filled');
                } else {
                    star.classList.remove('filled');
                }
            });

            text.textContent = ratingDescriptions[value];
        }

        // Initialize on load
        document.addEventListener('DOMContentLoaded', () => {
            const slider = document.getElementById('rating');
            updateStars(slider.value);
        });

        // Add click functionality to stars
        document.addEventListener('DOMContentLoaded', () => {
            const stars = document.querySelectorAll('#starsDisplay i');
            const slider = document.getElementById('rating');
            
            stars.forEach((star, index) => {
                star.addEventListener('click', () => {
                    const rating = index + 1;
                    slider.value = rating;
                    updateStars(rating);
                });
            });
        });
    </script>
</body>
</html>