<?php
require_once 'config.php';

$city_id = isset($_GET['city']) ? intval($_GET['city']) : 0;
$specialization_id = isset($_GET['specialization']) ? intval($_GET['specialization']) : 0;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

$site_name = getSetting('site_name', 'CARE Group Medical Services');
$cities = getAllCities();
$specializations = getAllSpecializations();

// === ONE DB CONNECTION ===
$conn = getDBConnection();

// === MAIN QUERY ===
$query = "SELECT DISTINCT
            d.doctor_id,
            d.full_name as doctor_name,
            d.email,
            d.phone,
            d.experience_years as experience,
            d.qualification,
            d.consultation_fee,
            d.rating,
            d.profile_image as profile_picture,
            d.hospital_name,
            d.hospital_address,
            d.total_reviews,
            c.city_name,
            s.specialization_name
          FROM doctors d
          LEFT JOIN cities c ON d.city_id = c.city_id
          LEFT JOIN specializations s ON d.specialization_id = s.specialization_id
          WHERE d.status = 'active'";

$where_conditions = [];
$params = [];
$types = '';

if ($city_id > 0) {
    $where_conditions[] = "d.city_id = ?";
    $params[] = $city_id;
    $types .= 'i';
}
if ($specialization_id > 0) {
    $where_conditions[] = "d.specialization_id = ?";
    $params[] = $specialization_id;
    $types .= 'i';
}
if (!empty($search_query)) {
    $where_conditions[] = "(d.full_name LIKE ? OR s.specialization_name LIKE ? OR d.hospital_name LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if (!empty($where_conditions)) {
    $query .= " AND " . implode(" AND ", $where_conditions);
}

$query .= " GROUP BY d.doctor_id ORDER BY d.rating DESC, d.experience_years DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$doctors = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();  // Only close stmt, NOT $conn

// Deduplicate
$unique_doctors = [];
$seen = [];
foreach ($doctors as $doctor) {
    if (!isset($seen[$doctor['doctor_id']])) {
        $unique_doctors[] = $doctor;
        $seen[$doctor['doctor_id']] = true;
    }
}
$doctors = $unique_doctors;

// === REAL RATING (USE SAME $conn) ===
foreach ($doctors as &$doctor) {
    $rating_stmt = $conn->prepare("
        SELECT AVG(rating) as avg_rating, COUNT(*) as review_count
        FROM appointments 
        WHERE doctor_id = ? AND rating IS NOT NULL AND status = 'completed'
    ");
    $rating_stmt->bind_param("i", $doctor['doctor_id']);
    $rating_stmt->execute();
    $res = $rating_stmt->get_result()->fetch_assoc();
    $rating_stmt->close();

    $doctor['rating'] = $res['avg_rating'] ? round($res['avg_rating'], 1) : 0.0;
    $doctor['total_reviews'] = $res['review_count'];

    $upd = $conn->prepare("UPDATE doctors SET rating = ?, total_reviews = ? WHERE doctor_id = ?");
    $upd->bind_param("dii", $doctor['rating'], $doctor['total_reviews'], $doctor['doctor_id']);
    $upd->execute();
    $upd->close();
}
unset($doctor);

// Session check
session_start();
$is_patient_logged_in = isset($_SESSION['patient_id']) && $_SESSION['user_type'] === 'patient';

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Doctors - <?php echo htmlspecialchars($site_name); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
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

    <!-- Login Required Modal (Dynamic) -->
<div id="loginRequiredModal" class="login-required-modal" style="display: none;">
    <div class="login-required-content animate__animated animate__fadeInUp">
        <div class="login-required-icon">
            <i class="fas fa-user-lock"></i>
        </div>
        <h3 style="color: var(--dark); margin-bottom: 1rem;">Login Required</h3>
        <p id="modalDoctorName" style="color: #64748b; line-height: 1.6;">
            Please login or register as a patient to book an appointment.
        </p>
        <div class="login-required-buttons">
            <a href="login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" 
               class="btn btn-primary">
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


    <!-- Search Filters Section -->
    <div class="search-container">
        <div class="search-filters">
            <h2 style="color: white; text-align: center; margin-bottom: 2rem;">Find Your Perfect Doctor</h2>
            <form class="search-form-advanced" action="search_doctors.php" method="GET">
                <div class="search-input-group">
                    <label for="city"><i class="fas fa-map-marker-alt"></i> City</label>
                    <select name="city" id="city" class="search-input">
                        <option value="">All Cities</option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?php echo $city['city_id']; ?>" 
                                <?php echo $city_id == $city['city_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($city['city_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="search-input-group">
                    <label for="specialization"><i class="fas fa-stethoscope"></i> Specialization</label>
                    <select name="specialization" id="specialization" class="search-input">
                        <option value="">All Specializations</option>
                        <?php foreach ($specializations as $spec): ?>
                            <option value="<?php echo $spec['specialization_id']; ?>" 
                                <?php echo $specialization_id == $spec['specialization_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($spec['specialization_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="search-input-group">
                    <label for="search"><i class="fas fa-search"></i> Search by Name</label>
                    <input type="text" name="search" id="search" class="search-input" 
                           placeholder="Doctor name, hospital, specialty..." 
                           value="<?php echo htmlspecialchars($search_query); ?>">
                </div>

                <button type="submit" class="btn btn-primary" style="height: fit-content;">
                    <i class="fas fa-search"></i> Search Doctors
                </button>
            </form>
        </div>
    </div>

    <!-- Results Section -->
    <div class="doctors-results">
        <!-- Active Filters -->
        <?php if ($city_id > 0 || $specialization_id > 0 || !empty($search_query)): ?>
            <div class="filter-tags">
                <?php if ($city_id > 0): 
                    $selected_city = array_filter($cities, function($c) use ($city_id) { return $c['city_id'] == $city_id; });
                    $selected_city = reset($selected_city);
                ?>
                    <div class="filter-tag">
                        <i class="fas fa-map-marker-alt"></i>
                        City: <?php echo htmlspecialchars($selected_city['city_name']); ?>
                    </div>
                <?php endif; ?>

                <?php if ($specialization_id > 0): 
                    $selected_spec = array_filter($specializations, function($s) use ($specialization_id) { return $s['specialization_id'] == $specialization_id; });
                    $selected_spec = reset($selected_spec);
                ?>
                    <div class="filter-tag">
                        <i class="fas fa-stethoscope"></i>
                        Specialty: <?php echo htmlspecialchars($selected_spec['specialization_name']); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($search_query)): ?>
                    <div class="filter-tag">
                        <i class="fas fa-search"></i>
                        Search: "<?php echo htmlspecialchars($search_query); ?>"
                    </div>
                <?php endif; ?>

                <a href="search_doctors.php" class="clear-filters">
                    <i class="fas fa-times"></i> Clear All
                </a>
            </div>
        <?php endif; ?>

        <!-- Results Header -->
        <div class="results-header">
            <div class="results-count">
                <strong><?php echo count($doctors); ?></strong> doctors found
                <?php if ($city_id > 0 || $specialization_id > 0 || !empty($search_query)): ?>
                    matching your criteria
                <?php endif; ?>
            </div>
        </div>

        <!-- Doctors Grid -->
        <?php if (count($doctors) > 0): ?>
            <div class="doctor-grid">
                <?php foreach ($doctors as $doctor): ?>
                    <div class="doctor-card animate__animated animate__fadeInUp">
                        <div class="doctor-header">
                            <?php if (!empty($doctor['profile_picture'])): ?>
                                <img src="<?php echo htmlspecialchars($doctor['profile_picture']); ?>" 
                                     alt="<?php echo htmlspecialchars($doctor['doctor_name']); ?>" 
                                     class="doctor-avatar">
                            <?php else: ?>
                                <div class="avatar-placeholder">
                                    <i class="fas fa-user-md"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="doctor-info">
                                <h3 class="doctor-name">Dr. <?php echo htmlspecialchars($doctor['doctor_name']); ?></h3>
                                <div class="doctor-specialization">
                                    <?php echo htmlspecialchars($doctor['specialization_name']); ?>
                                </div>
                                <div class="doctor-rating">
                                    <div class="stars">
                                        <?php
                                        $rating = $doctor['rating'] ?? 0;
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
                                    <span class="review-count">
    (<?php echo $doctor['total_reviews']; ?> review<?php echo $doctor['total_reviews'] != 1 ? 's' : ''; ?>)
</span>
                                </div>
                            </div>
                        </div>

                        <div class="doctor-details">
                            <div class="detail-item">
                                <i class="fas fa-briefcase-medical"></i>
                                <span><?php echo $doctor['experience'] ?? '0'; ?> years experience</span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-graduation-cap"></i>
                                <span><?php echo htmlspecialchars($doctor['qualification'] ?? 'MBBS'); ?></span>
                            </div>
                            <?php if (!empty($doctor['hospital_name'])): ?>
                            <div class="detail-item">
                                <i class="fas fa-hospital"></i>
                                <span><?php echo htmlspecialchars($doctor['hospital_name']); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="detail-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo htmlspecialchars($doctor['city_name'] ?? 'Unknown'); ?></span>
                            </div>
                        </div>

                        <div class="doctor-footer">
                            <div class="consultation-fee">
                                <span>Fee: </span>
                                $<?php echo number_format($doctor['consultation_fee'] ?? 500); ?>
                            </div>
                            <div class="doctor-actions">
                                <a href="doctor_profile.php?id=<?php echo $doctor['doctor_id']; ?>" 
                                   class="btn btn-outline" style="margin-right: 0.5rem;">
                                    <i class="fas fa-eye"></i> View Profile
                                </a>
                                <div>
                    <?php if ($is_patient_logged_in): ?>
                        <!-- Patient is logged in - direct appointment booking -->
                        <a href="book_appointment.php?doctor_id=<?php echo $doctor['doctor_id']; ?>" 
                           class="btn btn-primary">
                            <i class="fas fa-calendar-check"></i> Book Now
                        </a>
                    <?php else: ?>
                        <!-- Patient not logged in - show login required button -->
                        <button onclick="showLoginModal('<?php echo htmlspecialchars($doctor['doctor_name']); ?>')" 
        class="btn btn-primary">
    <i class="fas fa-calendar-check"></i> Book Now
</button>
                    <?php endif; ?>
                    </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- No Results Found -->
            <div class="no-results animate__animated animate__fadeIn">
                <i class="fas fa-user-md"></i>
                <h3>No Doctors Found</h3>
                <p>We couldn't find any doctors matching your search criteria.</p>
                <p style="color: #64748b; margin-top: 1rem;">Try adjusting your filters or search terms.</p>
                <a href="search_doctors.php" class="btn btn-primary" style="margin-top: 1.5rem;">
                    <i class="fas fa-refresh"></i> Clear Filters
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3><i class="fas fa-heartbeat"></i> <?php echo htmlspecialchars($site_name); ?></h3>
                <p>Where healing begins with care.</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>

            <div class="footer-section">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="search_doctors.php">Find Doctors</a></li>
                    <li><a href="index.php#diseases">Health Information</a></li>
                    <li><a href="index.php#news">Medical News</a></li>
                </ul>
            </div>

            <div class="footer-section">
                <h3>Specializations</h3>
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
                <h3>Contact Us</h3>
                <ul>
                    <li><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($site_name); ?> 1600 Eureka Rd, Roseville, CA. 95661 </li>
                    <li><i class="fas fa-phone"></i> +91 1800-123-4567</li>
                    <li><i class="fas fa-envelope"></i> info@caregroup.com</li>
                    <li><i class="fas fa-clock"></i> 24/7 Support Available</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2025 CARE Group. All rights reserved. | <a href="#">Privacy Policy</a> | <a href="#">Terms of Service</a></p>
        </div>
    </footer>

    <script>
        // Add animation to cards when they come into view
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

        // Observe all doctor cards
        document.querySelectorAll('.doctor-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(card);
        });

        // Auto-submit form when filters change (optional)
        document.getElementById('city').addEventListener('change', function() {
            this.form.submit();
        });

        document.getElementById('specialization').addEventListener('change', function() {
            this.form.submit();
        });

    // Show Login Modal with dynamic doctor name
    function showLoginModal(doctorName) {
        const modal = document.getElementById('loginRequiredModal');
        const doctorNameEl = document.getElementById('modalDoctorName');
        
        doctorNameEl.innerHTML = `Please login or register as a patient to book an appointment with <strong>Dr. ${doctorName}</strong>.`;
        
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden'; // Prevent background scroll
    }

    // Close Login Modal
    function closeLoginModal() {
        const modal = document.getElementById('loginRequiredModal');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('loginRequiredModal');
        if (event.target === modal) {
            closeLoginModal();
        }
    }
    </script>
</body>

</html>