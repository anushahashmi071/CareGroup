<?php
// doctor_profile.php
require_once 'config.php';

// Get doctor ID from URL
$doctor_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($doctor_id <= 0) {
    header('Location: search_doctors.php');
    exit();
}

$site_name = getSetting('site_name', 'CARE Group Medical Services');

// Get doctor details
$conn = getDBConnection();
$query = "SELECT 
            d.doctor_id,
            d.full_name as doctor_name,
            d.email,
            d.phone,
            d.alternate_phone,
            d.experience_years as experience,
            d.qualification,
            d.consultation_fee,
            d.rating,
            d.profile_image as profile_picture,
            d.hospital_name,
            d.hospital_address,
            d.bio,
            d.total_reviews,
            d.total_ratings,
            d.registration_number,
            d.address,
            c.city_name,
            s.specialization_name
          FROM doctors d
          LEFT JOIN cities c ON d.city_id = c.city_id
          LEFT JOIN specializations s ON d.specialization_id = s.specialization_id
          WHERE d.doctor_id = ? AND d.status = 'active'";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $doctor_id);
$stmt->execute();
$result = $stmt->get_result();
$doctor = $result->fetch_assoc();

if (!$doctor) {
    header('Location: search_doctors.php');
    exit();
}

$stmt->close();


// Get doctor availability
$availability = [];
try {
    $availability_query = "SELECT * FROM doctor_availability WHERE doctor_id = ? ORDER BY day_of_week, start_time";
    $availability_stmt = $conn->prepare($availability_query);
    if ($availability_stmt) {
        $availability_stmt->bind_param('i', $doctor_id);
        $availability_stmt->execute();
        $availability_result = $availability_stmt->get_result();
        $availability = $availability_result->fetch_all(MYSQLI_ASSOC);
        $availability_stmt->close();
    }
} catch (Exception $e) {
    // Table doesn't exist or has issues, use empty availability
    $availability = [];
}

// Get reviews from APPOINTMENTS table
$reviews = [];
$total_reviews = 0;
$average_rating = 0;

$reviews_query = "SELECT a.rating, a.review as comment, a.appointment_date as created_at, p.full_name as patient_name 
                 FROM appointments a 
                 LEFT JOIN patients p ON a.patient_id = p.patient_id 
                 WHERE a.doctor_id = ? AND a.rating IS NOT NULL AND a.status = 'completed' 
                 ORDER BY a.appointment_date DESC 
                 LIMIT 10";
$reviews_stmt = $conn->prepare($reviews_query);
if ($reviews_stmt) {
    $reviews_stmt->bind_param('i', $doctor_id);
    $reviews_stmt->execute();
    $reviews_result = $reviews_stmt->get_result();
    $reviews = $reviews_result->fetch_all(MYSQLI_ASSOC);
    $reviews_stmt->close();

    // Calculate review stats
    $total_reviews = count($reviews);
    if ($total_reviews > 0) {
        $ratings_sum = array_sum(array_column($reviews, 'rating'));
        $average_rating = round($ratings_sum / $total_reviews, 1);
    }
}

// === FINAL RATING & REVIEW COUNT ===
if ($doctor['rating'] != $average_rating || $doctor['total_ratings'] != $total_reviews) {
    $doctor_rating       = $average_rating;
    $doctor_total_reviews = $total_reviews;
} else {
    $doctor_rating       = $doctor['rating'] ?? $average_rating;
    $doctor_total_reviews = $doctor['total_ratings'] ?? $total_reviews;
}

$conn->close();

// Days of week for availability
$days_of_week = [
    1 => 'Monday',
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday',
    7 => 'Sunday'
];

// Sample availability data (fallback if table doesn't exist)
$sample_availability = [
    ['day_of_week' => 1, 'start_time' => '09:00:00', 'end_time' => '17:00:00'], // Monday
    ['day_of_week' => 2, 'start_time' => '09:00:00', 'end_time' => '17:00:00'], // Tuesday
    ['day_of_week' => 3, 'start_time' => '09:00:00', 'end_time' => '17:00:00'], // Wednesday
    ['day_of_week' => 4, 'start_time' => '09:00:00', 'end_time' => '17:00:00'], // Thursday
    ['day_of_week' => 5, 'start_time' => '09:00:00', 'end_time' => '17:00:00'], // Friday
    ['day_of_week' => 6, 'start_time' => '10:00:00', 'end_time' => '14:00:00'], // Saturday
];

// Use sample data if no real availability data
if (empty($availability)) {
    $availability = $sample_availability;
}

// Check if user is logged in as patient
session_start();
$is_patient_logged_in = isset($_SESSION['patient_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'patient';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dr. <?php echo htmlspecialchars($doctor['doctor_name']); ?> - <?php echo htmlspecialchars($site_name); ?>
    </title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <i class="fas fa-heartbeat"></i>
                <?php echo htmlspecialchars($site_name); ?>
            </div>
            <ul class="nav-menu">
                <li><a href="index.php">Home</a></li>
                <li><a href="search_doctors.php">Doctors</a></li>
                <li><a href="index.php#specializations">Specialists</a></li>
                <li><a href="diseases_list.php">Health Info</a></li>
                <li><a href="news_list.php">News</a></li>
                <li><a href="inventions.php">Inventions</a></li>
                <li><a href="login.php" class="btn btn-primary text-white">Login</a></li>
            </ul>
        </div>
    </nav>


    <!-- Login Required Modal -->
    <div id="loginRequiredModal" class="login-required-modal">
        <div class="login-required-content animate__animated animate__fadeInUp">
            <div class="login-required-icon">
                <i class="fas fa-user-lock"></i>
            </div>
            <h3 style="color: var(--dark); margin-bottom: 1rem;">Login Required</h3>
            <p style="color: #64748b; line-height: 1.6;">
                Please login or register as a patient to book an appointment with Dr.
                <?php echo htmlspecialchars($doctor['doctor_name']); ?>.
            </p>
            <div class="login-required-buttons">
                <a href="login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Login
                </a>
                <a href="register.php?type=patient&redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>"
                    class="btn btn-outline">
                    <i class="fas fa-user-plus"></i> Register
                </a>
                <button onclick="closeLoginModal()" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Profile Header -->
    <section class="profile-header">
        <div class="profile-container">
            <div class="breadcrumb">
                <a href="search_doctors.php" style="color: white; text-decoration: none;">
                    <i class="fas fa-arrow-left"></i> Back to Doctors
                </a>
            </div>
            <h1 class="animate__animated animate__fadeInUp">Dr. <?php echo htmlspecialchars($doctor['doctor_name']); ?>
            </h1>
            <p class="animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
                <?php echo htmlspecialchars($doctor['specialization_name']); ?> •
                <?php echo htmlspecialchars($doctor['city_name']); ?> •
                <?php echo $doctor['experience']; ?>+ years experience
            </p>
        </div>
    </section>

    <!-- Main Profile Content -->
    <div class="profile-container" style="padding: 3rem 2rem;">
        <div class="profile-main">
            <!-- Sidebar -->
            <div class="profile-sidebar animate__animated animate__fadeInLeft">
                <?php if (!empty($doctor['profile_picture'])): ?>
                    <img src="<?php echo htmlspecialchars($doctor['profile_picture']); ?>" 
                         alt="Dr. <?php echo htmlspecialchars($doctor['doctor_name']); ?>" 
                         class="profile-image">
                <?php else: ?>
                    <div class="profile-image-placeholder">
                        <i class="fas fa-user-md"></i>
                    </div>
                <?php endif; ?>

                <h2>Dr. <?php echo htmlspecialchars($doctor['doctor_name']); ?></h2>
                <p style="color: var(--primary); font-weight: 600; margin-bottom: 1rem;">
                    <?php echo htmlspecialchars($doctor['specialization_name']); ?>
                </p>

                <div class="doctor-rating" style="justify-content: center; margin-bottom: 1rem;">
                    <div class="stars">
                        <?php
                        $rating = $doctor_rating;  // Fixed: Use calculated/stored
                        $full_stars = floor($rating);
                        $half_star = ($rating - $full_stars) >= 0.5;
                        
                        for ($i = 1; $i <= 5; $i++):
                            if ($i <= $full_stars): ?>
                                <i class="fas fa-star"></i>
                            <?php elseif ($i == $full_stars + 1 && $half_star): ?>
                                <i class="fas fa-star-half-alt"></i>
                            <?php else: ?>
                                <i class="far fa-star"></i>
                            <?php endif;
                        endfor;
                        ?>
                    </div>
                    <span class="rating-value"><?php echo number_format($rating, 1); ?></span>
                </div>

                <p style="color: #64748b; margin-bottom: 1.5rem;">
                    <?php echo $doctor_total_reviews; ?> reviews  <!-- Fixed: Use doctor_total_reviews -->
                </p>

                <div class="consultation-fee">
                    $<?php echo number_format($doctor['consultation_fee'] ?? 500); ?>
                </div>
                <p style="color: #64748b; font-size: 0.9rem;">Consultation Fee</p>

                <div class="action-buttons" style="margin-top: 2rem;">
                    <?php if ($is_patient_logged_in): ?>
                        <!-- Patient is logged in - direct appointment booking -->
                        <a href="book_appointment.php?doctor_id=<?php echo $doctor['doctor_id']; ?>" 
                           class="btn btn-primary btn-large" style="width: 100%;">
                            <i class="fas fa-calendar-check"></i> Book Appointment
                        </a>
                    <?php else: ?>
                        <!-- Patient not logged in - show login required button -->
                        <button onclick="showLoginModal()" 
                                class="btn btn-primary btn-large" style="width: 100%;">
                            <i class="fas fa-calendar-check"></i> Book Appointment
                        </button>
                    <?php endif; ?>
                    
                    <button class="btn btn-outline" style="width: 100%; margin-top: 0.5rem;" 
                            onclick="shareProfile()">
                        <i class="fas fa-share-alt"></i> Share Profile
                    </button>
                </div>

                <?php if (!$is_patient_logged_in): ?>
                    <div style="margin-top: 1rem; padding: 1rem; background: #f0f9ff; border-radius: 8px;">
                        <p style="font-size: 0.9rem; color: #0369a1; margin: 0; text-align: left;">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Login required</strong> to book appointments and access patient dashboard.
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Main Content -->
            <div class="profile-content animate__animated animate__fadeInRight">
                <!-- About Section -->
                <section>
                    <h2 class="section-title fs-1"> Dr. <?php echo htmlspecialchars($doctor['doctor_name']); ?></h2>

                    <?php if (!empty($doctor['bio'])): ?>
                        <p style="line-height: 1.8; color: #374151; margin-bottom: 2rem;">
                            <?php echo nl2br(htmlspecialchars($doctor['bio'])); ?>
                        </p>
                    <?php else: ?>
                        <p style="color: #64748b; font-style: italic; margin-bottom: 2rem;">
                            Dr. <?php echo htmlspecialchars($doctor['doctor_name']); ?> is a dedicated
                            <?php echo htmlspecialchars($doctor['specialization_name']); ?> with
                            <?php echo $doctor['experience']; ?> years of experience in providing
                            quality healthcare services.
                        </p>
                    <?php endif; ?>

                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <div class="info-content">
                                <h4>Qualifications</h4>
                                <p><?php echo htmlspecialchars($doctor['qualification'] ?? 'MBBS'); ?></p>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-briefcase-medical"></i>
                            </div>
                            <div class="info-content">
                                <h4>Experience</h4>
                                <p><?php echo $doctor['experience']; ?>+ years</p>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-id-card"></i>
                            </div>
                            <div class="info-content">
                                <h4>Registration No.</h4>
                                <p><?php echo htmlspecialchars($doctor['registration_number'] ?? 'Not specified'); ?>
                                </p>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-hospital"></i>
                            </div>
                            <div class="info-content">
                                <h4>Hospital</h4>
                                <p><?php echo htmlspecialchars($doctor['hospital_name'] ?? 'Multiple Clinics'); ?></p>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="info-content">
                                <h4>Location</h4>
                                <p><?php echo htmlspecialchars($doctor['city_name'] ?? 'Unknown'); ?></p>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div class="info-content">
                                <h4>Contact</h4>
                                <p><?php echo htmlspecialchars($doctor['phone'] ?? 'Not available'); ?></p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Contact & Location -->
                <section style="margin-top: 3rem;">
                    <h3 class="section-title">Contact & Location</h3>
                    <div class="info-grid">
                        <?php if (!empty($doctor['hospital_address'])): ?>
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-map-marked-alt"></i>
                                </div>
                                <div class="info-content">
                                    <h4>Hospital Address</h4>
                                    <p><?php echo nl2br(htmlspecialchars($doctor['hospital_address'])); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($doctor['phone'])): ?>
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-phone-alt"></i>
                                </div>
                                <div class="info-content">
                                    <h4>Primary Phone</h4>
                                    <p><?php echo htmlspecialchars($doctor['phone']); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($doctor['alternate_phone'])): ?>
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-mobile-alt"></i>
                                </div>
                                <div class="info-content">
                                    <h4>Alternate Phone</h4>
                                    <p><?php echo htmlspecialchars($doctor['alternate_phone']); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($doctor['email'])): ?>
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="info-content">
                                    <h4>Email</h4>
                                    <p><?php echo htmlspecialchars($doctor['email']); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Availability Section -->
                <section style="margin-top: 3rem;">
                    <h3 class="section-title">Availability</h3>

                    <?php if (empty($availability) || $availability === $sample_availability): ?>
                        <div class="sample-data-notice">
                            <i class="fas fa-info-circle"></i>
                            Showing sample availability schedule. Actual timings may vary.
                        </div>
                    <?php endif; ?>

                    <?php if (count($availability) > 0): ?>
                        <div class="availability-grid">
                            <?php
                            $grouped_availability = [];
                            foreach ($availability as $slot) {
                                $day = $slot['day_of_week'];
                                if (!isset($grouped_availability[$day])) {
                                    $grouped_availability[$day] = [];
                                }
                                $grouped_availability[$day][] = $slot;
                            }

                            for ($day = 1; $day <= 7; $day++):
                                $day_slots = $grouped_availability[$day] ?? [];
                                ?>
                                <div class="availability-day">
                                    <div class="day-name"><?php echo $days_of_week[$day]; ?></div>
                                    <div class="time-slots">
                                        <?php if (!empty($day_slots)): ?>
                                            <?php foreach ($day_slots as $slot): ?>
                                                <div>
                                                    <?php echo date('g:i A', strtotime($slot['start_time'])); ?> -
                                                    <?php echo date('g:i A', strtotime($slot['end_time'])); ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div style="color: #94a3b8; font-style: italic;">Not available</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    <?php else: ?>
                        <div style="background: #f8fafc; padding: 2rem; border-radius: 10px; text-align: center;">
                            <i class="fas fa-calendar-times"
                                style="font-size: 3rem; color: #cbd5e1; margin-bottom: 1rem;"></i>
                            <h4 style="color: #64748b; margin-bottom: 0.5rem;">Availability Not Scheduled</h4>
                            <p style="color: #94a3b8;">Please contact the doctor directly for appointment availability.</p>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- Reviews Section -->
                <section class="reviews-section">
                    <h3 class="section-title">Patient Reviews</h3>

                    <?php if (count($reviews) > 0): ?>
                        <!-- Rating Overview -->
                        <div class="rating-overview">
                            <div class="rating-score">
                                <div class="rating-number"><?php echo number_format($doctor_rating, 1); ?></div>
                                <div class="rating-stars">
                                    <?php
                                    $rating = $doctor_rating;
                                    $full_stars = floor($rating);
                                    $half_star = ($rating - $full_stars) >= 0.5;

                                    for ($i = 1; $i <= 5; $i++):
                                        if ($i <= $full_stars): ?>
                                            <i class="fas fa-star" style="color: #fbbf24;"></i>
                                        <?php elseif ($i == $full_stars + 1 && $half_star): ?>
                                            <i class="fas fa-star-half-alt" style="color: #fbbf24;"></i>
                                        <?php else: ?>
                                            <i class="far fa-star" style="color: #d1d5db;"></i>
                                        <?php endif;
                                    endfor;
                                    ?>
                                </div>
                                <div class="rating-count"><?php echo $doctor_total_reviews; ?> reviews</div>
                            </div>
                            <div style="flex: 1;">
                                <p style="color: #374151; line-height: 1.6;">
                                    Based on <?php echo $doctor_total_reviews; ?> patient reviews,
                                    Dr. <?php echo htmlspecialchars($doctor['doctor_name']); ?> has an average rating of
                                    <?php echo number_format($doctor_rating, 1); ?> out of 5 stars.
                                </p>
                            </div>
                        </div>

                        <!-- Reviews List -->
                        <div class="reviews-list">
                            <?php foreach ($reviews as $review): ?>
                                <div class="review-card">
                                    <div class="review-header">
                                        <div class="review-patient">
                                            <?php echo htmlspecialchars($review['patient_name'] ?? 'Anonymous Patient'); ?>
                                        </div>
                                        <div class="review-date">
                                            <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                                        </div>
                                    </div>
                                    <div class="review-rating">
                                        <?php
                                        $review_rating = $review['rating'];
                                        for ($i = 1; $i <= 5; $i++):
                                            if ($i <= $review_rating): ?>
                                                <i class="fas fa-star" style="color: #fbbf24;"></i>
                                            <?php else: ?>
                                                <i class="far fa-star" style="color: #d1d5db;"></i>
                                            <?php endif;
                                        endfor;
                                        ?>
                                    </div>
                                    <div class="review-comment">
                                        <?php echo nl2br(htmlspecialchars($review['comment'] ?? 'No comment provided.')); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="background: #f8fafc; padding: 3rem; border-radius: 10px; text-align: center;">
                            <i class="fas fa-comment-dots"
                                style="font-size: 3rem; color: #cbd5e1; margin-bottom: 1rem;"></i>
                            <h4 style="color: #64748b; margin-bottom: 0.5rem;">No Reviews Yet</h4>
                            <p style="color: #94a3b8;">Be the first to review Dr.
                                <?php echo htmlspecialchars($doctor['doctor_name']); ?>
                            </p>

                            <?php if ($is_patient_logged_in): ?>
                                <a href="add_review.php?doctor_id=<?php echo $doctor_id; ?>" class="btn btn-primary"
                                    style="margin-top: 1rem;">
                                    <i class="fas fa-plus"></i> Write a Review
                                </a>
                            <?php else: ?>
                                <p style="color: #64748b; font-size: 0.9rem; margin-top: 1rem;">
                                    <a href="login.php" style="color: var(--primary);">Login</a> to write a review
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h5><i class="fas fa-heartbeat"></i> <?php echo htmlspecialchars($site_name); ?></h5>
                <p>Your trusted partner in healthcare. Connecting patients with the best medical professionals across
                    the country.</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>

            <div class="footer-section">
                <h5>Quick Links</h5>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="search_doctors.php">Find Doctors</a></li>
                    <li><a href="index.php#diseases">Health Information</a></li>
                    <li><a href="index.php#news">Medical News</a></li>
                </ul>
            </div>

            <div class="footer-section">
                <h5>Specializations</h5>
                <ul>
                    <?php
                    // Get specializations for footer
                    $conn = getDBConnection();
                    $footer_spec_query = "SELECT * FROM specializations ORDER BY specialization_name LIMIT 5";
                    $footer_spec_result = $conn->query($footer_spec_query);

                    if ($footer_spec_result && $footer_spec_result->num_rows > 0) {
                        $footer_specializations = $footer_spec_result->fetch_all(MYSQLI_ASSOC);
                        foreach ($footer_specializations as $spec): ?>
                            <li>
                                <a href="search_doctors.php?specialization=<?php echo $spec['specialization_id']; ?>">
                                    <?php echo htmlspecialchars($spec['specialization_name']); ?>
                                </a>
                            </li>
                        <?php endforeach;
                    } else {
                        // Fallback specializations
                        $default_specs = [
                            ['specialization_id' => 1, 'specialization_name' => 'Cardiology'],
                            ['specialization_id' => 2, 'specialization_name' => 'Neurology'],
                            ['specialization_id' => 3, 'specialization_name' => 'Orthopedics'],
                            ['specialization_id' => 4, 'specialization_name' => 'Pediatrics'],
                            ['specialization_id' => 5, 'specialization_name' => 'Dermatology']
                        ];

                        foreach ($default_specs as $spec): ?>
                            <li>
                                <a href="search_doctors.php?specialization=<?php echo $spec['specialization_id']; ?>">
                                    <?php echo htmlspecialchars($spec['specialization_name']); ?>
                                </a>
                            </li>
                        <?php endforeach;
                    }
                    $conn->close();
                    ?>
                </ul>
            </div>

            <div class="footer-section">
                <h5>Contact Us</h5>
                <ul>
                    <li><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($site_name); ?> 1600 Eureka
                        Rd, Roseville, CA. 95661 </li>
                    <li><i class="fas fa-phone"></i> +91 1800-123-4567</li>
                    <li><i class="fas fa-envelope"></i> info@caregroup.com</li>
                    <li><i class="fas fa-clock"></i> 24/7 Support Available</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2025 CARE Group. All rights reserved. | <a href="#">Privacy Policy</a> | <a href="#">Terms of
                    Service</a></p>
        </div>
    </footer>

    <script>
        function showLoginModal() {
            document.getElementById('loginRequiredModal').style.display = 'flex';
        }

        function closeLoginModal() {
            document.getElementById('loginRequiredModal').style.display = 'none';
        }

        function shareProfile() {
            if (navigator.share) {
                navigator.share({
                    title: 'Dr. <?php echo htmlspecialchars($doctor['doctor_name']); ?> - <?php echo htmlspecialchars($site_name); ?>',
                    text: 'Check out Dr. <?php echo htmlspecialchars($doctor['doctor_name']); ?>, <?php echo htmlspecialchars($doctor['specialization_name']); ?>',
                    url: window.location.href
                })
                    .then(() => console.log('Successful share'))
                    .catch((error) => console.log('Error sharing:', error));
            } else {
                // Fallback: copy to clipboard
                navigator.clipboard.writeText(window.location.href).then(() => {
                    alert('Profile link copied to clipboard!');
                });
            }
        }

        // Close modal when clicking outside
        document.getElementById('loginRequiredModal').addEventListener('click', function (e) {
            if (e.target === this) {
                closeLoginModal();
            }
        });

        // Add animation to elements when they come into view
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe all info items and review cards
        document.querySelectorAll('.info-item, .review-card, .availability-day').forEach(element => {
            element.style.opacity = '0';
            element.style.transform = 'translateY(20px)';
            element.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(element);
        });
    </script>
</body>

</html>