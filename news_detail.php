<?php
// news_detail.php – Same theme as index.php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';

// Get site name safely
$site_name = getSetting('site_name') ?? 'CARE Group'; // Fallback to 'CARE Group'

$type = $_GET['type'] ?? '';          // 'news' or 'invention'
$id = (int) ($_GET['id'] ?? 0);
$content = null;
$page_title = '';

$conn = getDBConnection();

if ($type === 'news') {
    $stmt = $conn->prepare("SELECT * FROM medical_news WHERE news_id = ? AND status = 'published'");
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $content = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    $page_title = $content['title'] ?? 'News Not Found';

} elseif ($type === 'invention') {
    $stmt = $conn->prepare("SELECT * FROM inventions WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $content = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    $page_title = $content['invention_name'] ?? 'Invention Not Found';
}
$conn->close();

// Safe title generation
$safe_page_title = htmlspecialchars($page_title ?: 'Content Not Found');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $safe_page_title; ?> - <?php echo htmlspecialchars($site_name); ?></title>

    <!-- Font Awesome & Animate.css (same as home) -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">

    <!-- Your global style (the same file used on index.php) -->
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

    <div class="detail-container">
        <?php if ($content): ?>
            <article class="detail-card <?php echo $type; ?>">
                <!-- Header -->
                <header class="detail-header">
                    <h1 class=""><?php echo $safe_page_title; ?></h1>
                    <div class="detail-meta">
                        <?php if ($type === 'news'): ?>
                            <span><i class=" fas fa-tag"></i> <?php echo htmlspecialchars($content['category'] ?? 'Uncategorized'); ?></span>
                            <span><i class="fas fa-user"></i>
                                <?php echo htmlspecialchars($content['author'] ?? 'Unknown Author'); ?></span>
                            <span><i class="far fa-calendar"></i>
                                <?php echo date('M j, Y', strtotime($content['published_date'] ?? 'now')); ?></span>
                        <?php elseif ($type === 'invention'): ?>
                            <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($content['category'] ?? 'General'); ?></span>
                            <span><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($content['inventor'] ?? 'Unknown Inventor'); ?></span>
                            <span><i class="far fa-calendar"></i>
                                <?php echo date('M j, Y', strtotime($content['created_at'] ?? 'now')); ?></span>
                        <?php endif; ?>
                    </div>
                </header>

                <!-- Body -->
                <section class="detail-body">

                    <?php
                    $show_image = false;
                    $image_url = '';

                    // Try both possible DB columns
                    $db_path = $content['image_url'] ?? $content['image'] ?? '';

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
                    <?php if ($show_image): ?>
                        <img src="<?php echo htmlspecialchars($image_url); ?>"
                            alt="<?php echo $safe_page_title; ?>" class="detail-image"
                            onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                        <div class="detail-placeholder" style="display:none;">
                            <i class="fas fa-image"></i> Image failed to load
                        </div>
                    <?php else: ?>
                        <div class="detail-placeholder">
                            <i class="fas fa-newspaper"></i>
                            <?php echo $type === 'news' ? 'No image' : 'Invention image'; ?>
                        </div>
                    <?php endif; ?>

                    <!-- CONTENT  -->
                    <?php if ($type === 'news'): ?>
                        <div class="content-text">
                            <?php echo nl2br(htmlspecialchars($content['content'] ?? 'No content available.')); ?>
                        </div>

                    <?php elseif ($type === 'invention'): ?>
                        <h2>About this Invention</h2>
                        <div class="content-text mb-3">
                            <?php echo nl2br(htmlspecialchars($content['description'] ?? 'No description available.')); ?>
                        </div>

                        <h2>Key Benefits</h2>
                        <div class="highlight-box">
                            <?php echo nl2br(htmlspecialchars($content['benefits'] ?? 'No benefits information available.')); ?>
                        </div>

                        <h2>Impact on Healthcare</h2>
                        <div class="content-text">
                            <p>This invention brings a meaningful advance in medical technology.</p>
                            <p><strong>Inventor:</strong> <?php echo htmlspecialchars($content['inventor'] ?? 'Unknown'); ?></p>
                            <p><strong>Category:</strong>
                                <?php echo htmlspecialchars($content['category'] ?? 'General'); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </section>
            </article>

        <?php else: ?>
            <div class="detail-card">
                <div class="detail-body" style="text-align:center;padding:4rem;">
                    <i class="fas fa-exclamation-triangle" style="font-size:3rem;color:#dc2626;"></i>
                    <h2 style="color:#dc2626;margin:1rem 0;">Content Not Found</h2>
                    <p style="color:#64748b;">
                        The requested <?php echo htmlspecialchars($type ?: 'content'); ?> (ID: <?php echo $id; ?>) could not be loaded.
                    </p>
                    <a href=" index.php" class="btn btn-primary" style="margin-top:1rem;">
                        <i class="fas fa-home"></i> Back to Home
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ====================  SAME FOOTER AS index.php  ==================== -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h5><i class="fas fa-heartbeat"></i>
                    <?php echo htmlspecialchars($site_name); ?>
                </h5>
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
                    <li><a href="#specializations">Find Doctors</a></li>
                    <li><a href="#diseases">Health Information</a></li>
                    <li><a href="#news">Medical News</a></li>
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
                    <li><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($site_name); ?>
                        1600 Eureka Rd, Roseville, CA. 95661 </li>
                    <li><i class="fas fa-phone"></i> <?php echo htmlspecialchars(getSetting('contact_phone') ?? '+91-XXXXX-XXXXX'); ?></li>
                    <li><i class="fas fa-envelope"></i> <?php echo htmlspecialchars(getSetting('contact_email') ?? 'contact@caregroup.com'); ?>
                    </li>
                </ul>
            </div>
        </div>


        <div class="footer-bottom">
            <p>© <?php echo date('Y'); ?> <?php echo htmlspecialchars($site_name); ?>. All rights reserved.</p>
        </div>
    </footer>


    <?php ob_end_flush(); ?>
</body>

</html>