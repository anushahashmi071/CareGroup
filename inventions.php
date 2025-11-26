<?php
// inventions.php - Medical Inventions Listing Page
require_once 'config.php';

$site_name = getSetting('site_name', 'CARE Group Medical Services');
$contact_email = getSetting('contact_email', 'info@caregroup.com');
$contact_phone = getSetting('contact_phone', '+91 1800-123-4567');

// Pagination setup
$items_per_page = 9;
$current_page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// Get total inventions count
$conn = getDBConnection();
$count_query = "SELECT COUNT(*) as total FROM inventions";
$count_result = $conn->query($count_query);
$total_items = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_items / $items_per_page);

// Get inventions with pagination
$check_column = $conn->query("SHOW COLUMNS FROM inventions LIKE 'image_url'");
$has_image_column = $check_column->num_rows > 0;

$inventions_query = "SELECT * FROM inventions ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($inventions_query);
$stmt->bind_param('ii', $items_per_page, $offset);
$stmt->execute();
$inventions_result = $stmt->get_result();
$inventions = $inventions_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Inventions - <?php echo htmlspecialchars($site_name); ?></title>

    <!-- Font Awesome & Animate.css -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Your global style -->
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

    <!-- Header Section -->
    <section class="page-title">
        <div class="container">
            <h1>Medical Inventions</h1>
            <p>Discover groundbreaking medical technologies and
                innovations that are shaping the future of healthcare.</p>
        </div>
    </section>



    <!-- FIXED MEDICAL INVENTIONS SECTION  -->
    <section class="news" id="inventions">
        <div class="news-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem;">
            <?php
            $conn = getDBConnection();
            $check_column = $conn->query("SHOW COLUMNS FROM inventions LIKE 'image_url'");
            $has_image_column = $check_column->num_rows > 0;

            $inventions_query = "SELECT * FROM inventions ORDER BY created_at DESC ";
            $inventions_result = $conn->query($inventions_query);
            $inventions = $inventions_result->fetch_all(MYSQLI_ASSOC);
            $conn->close();

            if (count($inventions) > 0):
                foreach ($inventions as $item):
                    $image_url = '';
                    $show_image = false;

                    if ($has_image_column && !empty($item['image_url'])) {
                        $db_path = $item['image_url'];

                        // Try different path variations - EXACTLY LIKE NEWS
                        $paths_to_try = [
                            __DIR__ . '/' . $db_path,  // /careGroup/uploads/inventions/file.jpg
                            __DIR__ . '/admin/' . $db_path,  // /careGroup/admin/uploads/inventions/file.jpg
                        ];

                        foreach ($paths_to_try as $full_path) {
                            if (file_exists($full_path)) {
                                $show_image = true;
                                // Create web accessible URL
                                if (strpos($full_path, 'admin') !== false) {
                                    $image_url = '/careGroup/admin/' . $db_path;
                                } else {
                                    $image_url = '/careGroup/' . $db_path;
                                }
                                break;
                            }
                        }
                    }
                    ?>
                    <div class="news-card">
                        <div class="news-image">
                            <?php if ($show_image && !empty($image_url)): ?>
                                <img src="<?php echo htmlspecialchars($image_url); ?>"
                                    alt="<?php echo htmlspecialchars($item['invention_name']); ?>" class="news-img" loading="lazy"
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="news-image-placeholder" style="display: none;">
                                    <i class="fas fa-lightbulb"></i>
                                    <small>Image failed to load</small>
                                </div>
                            <?php else: ?>
                                <div class="news-image-placeholder">
                                    <i class="fas fa-lightbulb"></i>
                                    <small><?php echo empty($item['image_url']) ? 'No image' : 'Image missing'; ?></small>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="news-content">
                            <div class="news-meta">
                                <span><i
                                        class="far fa-calendar"></i><?php echo date('M d, Y', strtotime($item['created_at'])); ?></span>
                                <span><i class="fas fa-tag"></i><?php echo htmlspecialchars($item['category']); ?></span>
                            </div>
                            <h3><?php echo htmlspecialchars($item['invention_name']); ?></h3>
                            <p><?php echo htmlspecialchars(substr($item['description'], 0, 150)); ?>...</p>
                            <p style="font-size: 0.9rem; color: #64748b; margin-top: 0.5rem;">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($item['inventor']); ?>
                            </p>
                            <p style="font-size: 0.85rem;color: #7590b4ff; margin-top: 0.5rem; font-weight: 600;">
                                <i class="fas fa-check-circle"></i> Benefits:
                                <?php echo htmlspecialchars(substr($item['benefits'], 0, 80)); ?>...
                            </p>
                            <a href="news_detail.php?type=invention&id=<?php echo $item['id']; ?>" class="read-more-btn">Read
                                More →
                            </a>
                        </div>
                    </div>
                    <?php
                endforeach;
            else:
                ?>
                <div class="news-card">
                    <div class="news-image">
                        <div class="news-image-placeholder">
                            <i class="fas fa-lightbulb"></i>
                            <small>Sample Invention</small>
                        </div>
                    </div>
                    <div class="news-content">
                        <div class="news-meta">
                            <span><i class="far fa-calendar"></i> <?php echo date('M d, Y'); ?></span>
                            <span><i class="fas fa-tag"></i> Innovation</span>
                        </div>
                        <h3>Innovative Medical Devices</h3>
                        <p>Discover the latest breakthroughs in medical technology and innovation.</p>
                        <a href="#" style="color: var(--primary); font-weight: 600; margin-top: 1rem;">Read More →</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>


    </section>

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
                    <li><a href="diseases_list.php">Health Information</a></li>
                    <li><a href="news_list.php">Medical News</a></li>
                    <li><a href="inventions.php">Medical Inventions</a></li>
                    <li><a href="login.php">Login</a></li>
                </ul>
            </div>

            <div class="footer-section">
                <h5>Specializations</h5>
                <ul>
                    <?php
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
                    <li><i class="fas fa-phone"></i> <?php echo htmlspecialchars($contact_phone); ?></li>
                    <li><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($contact_email); ?></li>
                    <li><i class="fas fa-clock"></i> 24/7 Support Available</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2025 CARE Group. All rights reserved. | <a href="#">Privacy Policy</a> | <a href="#">Terms of
                    Service</a></p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Add animation on scroll
        document.addEventListener('DOMContentLoaded', function () {
            const cards = document.querySelectorAll('.invention-card');

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animation = 'fadeInUp 0.6s ease forwards';
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1 });

            cards.forEach(card => {
                observer.observe(card);
            });
        });
    </script>
</body>

</html>