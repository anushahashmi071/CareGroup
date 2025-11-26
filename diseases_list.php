<?php
// diseases_list.php
require_once 'config.php';

// Get site settings
$site_name = getSetting('site_name', 'CARE Group Medical Services');
$contact_email = getSetting('contact_email', 'info@caregroup.com');
$contact_phone = getSetting('contact_phone', '+91 1800-123-4567');

$conn = getDBConnection();
$diseases_query = "SELECT * FROM diseases ORDER BY created_at DESC";
$diseases = $conn->query($diseases_query)->fetch_all(MYSQLI_ASSOC);

// Get specializations for footer
$footer_spec_query = "SELECT * FROM specializations ORDER BY specialization_name LIMIT 5";
$footer_spec_result = $conn->query($footer_spec_query);
$footer_specializations = $footer_spec_result ? $footer_spec_result->fetch_all(MYSQLI_ASSOC) : [];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Information - <?php echo htmlspecialchars($site_name); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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

    <div class="container mt-5 pt-5">
        <div class="row">
            <div class="col-12">
                <h1 class="text-center mb-4">Health Information Center</h1>
                <p class="text-center text-muted mb-5">Comprehensive health information about various diseases and conditions</p>
                
                <div class="row">
                    <?php if (count($diseases) > 0): ?>
                        <?php foreach ($diseases as $disease): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="disease-card h-100">
                                    <div class="disease-header">
                                        <h3><i class="fas fa-heartbeat"></i> <?php echo htmlspecialchars($disease['disease_name']); ?></h3>
                                    </div>
                                    <div class="disease-body">
                                        <?php if ($disease['symptoms']): ?>
                                            <div class="disease-section">
                                                <h5>Symptoms</h5>
                                                <p><?php echo htmlspecialchars($disease['symptoms']); ?></p>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($disease['prevention']): ?>
                                            <div class="disease-section">
                                                <h5>Prevention</h5>
                                                <p><?php echo htmlspecialchars($disease['prevention']); ?></p>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($disease['cure']): ?>
                                            <div class="disease-section">
                                                <h5>Treatment</h5>
                                                <p><?php echo htmlspecialchars($disease['cure']); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info text-center">
                                <h4>No Health Information Available</h4>
                                <p>Please check back later for health information updates.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h5><i class="fas fa-heartbeat"></i> <?php echo htmlspecialchars($site_name); ?></h5>
                <p>Where healing begins with care.</p>
                <div class="social-links">
                    <a target="_blank" href="https://www.facebook.com/"><i class="fab fa-facebook-f"></i></a>
                    <a target="_blank" href="https://x.com/"><i class="fab fa-twitter"></i></a>
                    <a target="_blank" href="https://www.instagram.com/"><i class="fab fa-instagram"></i></a>
                    <a target="_blank" href="https://www.linkedin.com/"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>

            <div class="footer-section">
                <h5>Quick Links</h5>
                <ul>
                    <li><a href="index.php#specializations">Find Doctors</a></li>
                    <li><a href="index.php#diseases">Health Information</a></li>
                    <li><a href="news_list.php">Medical News</a></li>
                    <li><a href="register.php">Register</a></li>
                    <li><a href="login.php">Login</a></li>
                </ul>
            </div>

            <div class="footer-section">
                <h5>Specializations</h5>
                <ul>
                    <?php if (count($footer_specializations) > 0): ?>
                        <?php foreach ($footer_specializations as $spec): ?>
                            <li>
                                <a href="search_doctors.php?specialization=<?php echo $spec['specialization_id']; ?>">
                                    <?php echo htmlspecialchars($spec['specialization_name']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Fallback specializations -->
                        <li><a href="search_doctors.php?specialization=1">Cardiology</a></li>
                        <li><a href="search_doctors.php?specialization=2">Neurology</a></li>
                        <li><a href="search_doctors.php?specialization=3">Orthopedics</a></li>
                        <li><a href="search_doctors.php?specialization=4">Pediatrics</a></li>
                        <li><a href="search_doctors.php?specialization=5">Dermatology</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="footer-section">
                <h5>Contact Us</h5>
                <ul>
                    <li><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($site_name); ?> 1600 Eureka Rd, Roseville, CA. 95661 </li>
                    <li><i class="fas fa-phone"></i> <?php echo htmlspecialchars($contact_phone); ?></li>
                    <li><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($contact_email); ?></li>
                    <li><i class="fas fa-clock"></i> 24/7 Support Available</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2025 <?php echo htmlspecialchars($site_name); ?>. All rights reserved. | <a href="#">Privacy Policy</a> | <a href="#">Terms of Service</a></p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>