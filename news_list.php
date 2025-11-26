<?php
/* news_list.php – All published medical news (full list) */
require_once 'config.php';
// Define site_name variable to prevent undefined variable warning
$site_name = getSetting('site_name') ?? 'CARE Group';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Medical News - <?php echo htmlspecialchars(getSetting('site_name', 'CARE Group')); ?></title>

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

    <div class="news-page">
        <!-- Header Section -->
        <section class="page-title">
            <div class="container">
                <h1>Medical News & Innovations</h1>
                <p>Stay updated with the latest medical breakthroughs
                    and innovations - explore our comprehensive collection of medical inventions shaping the future of
                    healthcare.</p>
            </div>
        </section>

        <div class="news-list-grid">
            <?php
            $conn = getDBConnection();

            $sql = "SELECT * FROM medical_news WHERE status = 'published' ORDER BY published_date DESC";
            $result = $conn->query($sql);
            $all_news = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

            if (count($all_news) > 0):
                foreach ($all_news as $item):
                    /* ---- IMAGE RESOLUTION (same logic as index.php) ---- */
                    $image_url = '';
                    $show_image = false;
                    $db_path = $item['image_url'] ?? $item['image'] ?? '';

                    if ($db_path !== '') {
                        $candidates = [
                            __DIR__ . '/' . $db_path,
                            __DIR__ . '/admin/' . $db_path,
                            __DIR__ . '/uploads/news/' . basename($db_path),
                            __DIR__ . '/admin/uploads/news/' . basename($db_path)
                        ];
                        foreach ($candidates as $full) {
                            if (file_exists($full)) {
                                $show_image = true;
                                $image_url = (strpos($full, 'admin') !== false)
                                    ? '/careGroup/admin/' . $db_path
                                    : '/careGroup/' . $db_path;
                                break;
                            }
                        }
                    }
                    ?>
                    <div class="news-card">
                        <div class="news-image">
                            <?php if ($show_image): ?>
                                <img src="<?php echo htmlspecialchars($image_url); ?>"
                                    alt="<?php echo htmlspecialchars($item['title']); ?>" class="news-img" loading="lazy"
                                    onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                <div class="news-image-placeholder" style="display:none;">
                                    <i class="fas fa-image"></i><small>Image failed</small>
                                </div>
                            <?php else: ?>
                                <div class="news-image-placeholder">
                                    <i class="fas fa-image"></i>
                                    <small><?php echo empty($item['image_url']) ? 'No image' : 'Image missing'; ?></small>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="news-content">
                            <div class="news-meta">
                                <span><i class="far fa-calendar"></i>
                                    <?php echo date('M d, Y', strtotime($item['published_date'])); ?></span>
                                <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($item['category']); ?></span>
                            </div>
                            <h3><?php echo htmlspecialchars($item['title']); ?></h3>
                            <p><?php echo htmlspecialchars(substr($item['content'], 0, 120)); ?>...</p>
                            <?php if (!empty($item['author'])): ?>
                                <p style="font-size:0.9rem;color:#64748b;margin-top:0.5rem;">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($item['author']); ?>
                                </p>
                            <?php endif; ?>
                            <a href="news_detail.php?type=news&id=<?php echo $item['news_id']; ?>" class="read-more-btn">
                                Read More <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    <?php
                endforeach;
            else:
                echo '<p class="no-news">No news available at the moment.</p>';
            endif;

            $conn->close();
            ?>
        </div>
    </div>

    <!-- ====================  SAME FOOTER AS index.php  ==================== -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h5><i class="fas fa-heartbeat"></i> <?php echo htmlspecialchars(getSetting('site_name')); ?></h5>
                <p>Where healing begins with care.</p>
                <div class="social-links">
                    <a href="https://www.facebook.com/"><i class="fab fa-facebook-f"></i></a>
                    <a href="https://x.com/"><i class="fab fa-twitter"></i></a>
                    <a href="https://www.instagram.com/"><i class="fab fa-instagram"></i></a>
                    <a href="https://www.linkedin.com/"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>

            <div class="footer-section">
                <h5>Quick Links</h5>
                <ul>
                    <li><a href="index.php#specializations">Find Doctors</a></li>
                    <li><a href="index.php#diseases">Health Information</a></li>
                    <li><a href="index.php#news">Medical News</a></li>
                    <li><a href="register.php">Register</a></li>
                    <li><a href="login.php">Login</a></li>
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
                    <li><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars(getSetting('site_name')); ?>
                        1600 Eureka Rd, Roseville, CA. 95661 </li>
                    <li><i class="fas fa-phone"></i> <?php echo htmlspecialchars(getSetting('contact_phone')); ?></li>
                    <li><i class="fas fa-envelope"></i> <?php echo htmlspecialchars(getSetting('contact_email')); ?>
                    </li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© <?php echo date('Y'); ?> CARE Group. All rights reserved.</p>
        </div>
    </footer>

</body>

</html>