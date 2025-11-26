<?php
// index.php 
require_once 'config.php';

$site_name = getSetting('site_name', 'CARE Group Medical Services');
$contact_email = getSetting('contact_email', 'info@caregroup.com');
$contact_phone = getSetting('contact_phone', '+91 1800-123-4567');

// Get cities for search dropdown
$cities = getAllCities();
$specializations = getAllSpecializations();

// Get diseases from database
$conn = getDBConnection();
$diseases_query = "SELECT * FROM diseases ORDER BY created_at DESC LIMIT 3";
$diseases = $conn->query($diseases_query)->fetch_all(MYSQLI_ASSOC);

// Get medical news from database - FIXED: Use different variable name
$news_query = "SELECT * FROM medical_news WHERE status = 'published' ORDER BY published_date DESC LIMIT 3";
$news_items = $conn->query($news_query)->fetch_all(MYSQLI_ASSOC);

// Get inventions from database
$inventions_query = "SELECT * FROM inventions ORDER BY created_at DESC LIMIT 3";
$inventions = $conn->query($inventions_query)->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(getSetting('site_name')); ?> - Where healing begins with care</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- Font Awesome (hamburger + heart) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
        integrity="sha512-XXXXX" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Slab:wght@100..900&display=swap" rel="stylesheet">
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

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-container">
            <div class="hero-content animate__animated animate__fadeInLeft">
                <h1>Your Health, Our Priority</h1>
                <p>Connect with the best doctors in your city. Book appointments online and take charge of your
                    healthcare journey.</p>
                <div class="hero-buttons">
                    <a href="register.php" class="btn btn-primary">Get Started</a>
                    <a href="#specializations" class="btn btn-secondary">Find a Doctor</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Search Section - UPDATE THIS -->
    <div class="features">
        <div class="search-section">
            <h2 style="text-align: center; margin-bottom: 1.5rem;" class="fs-1">Find Your Doctor</h2>
            <form class="search-form" action="search_doctors.php" method="GET">
                <select name="city" class="search-input" required>
                    <option value="">Select City</option>
                    <?php foreach ($cities as $city): ?>
                        <option value="<?php echo $city['city_id']; ?>">
                            <?php echo $city['city_name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="specialization" class="search-input" required>
                    <option value="">Select Specialist</option>
                    <?php foreach ($specializations as $spec): ?>
                        <option value="<?php echo $spec['specialization_id']; ?>">
                            <?php echo $spec['specialization_name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Search
                </button>
            </form>
        </div>
    </div>

    <!-- Features Section -->
    <section class="features">
        <h2 class="section-title fs-1">Why Choose CARE Group?</h2>
        <div class="features-grid">
            <div class="feature-card animate__animated animate__fadeInUp">
                <div class="feature-icon">
                    <i class="fas fa-user-md"></i>
                </div>
                <h3>Expert Doctors</h3>
                <p>Access to highly qualified and experienced medical professionals across specialties</p>
            </div>
            <div class="feature-card animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
                <div class="feature-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <h3>24/7 Booking</h3>
                <p>Book appointments anytime, anywhere with our easy-to-use online system</p>
            </div>
            <div class="feature-card animate__animated animate__fadeInUp" style="animation-delay: 0.4s;">
                <div class="feature-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3>Secure & Private</h3>
                <p>Your medical data is protected with advanced security measures</p>
            </div>
            <div class="feature-card animate__animated animate__fadeInUp" style="animation-delay: 0.6s;">
                <div class="feature-icon">
                    <i class="fas fa-star"></i>
                </div>
                <h3>Verified Reviews</h3>
                <p>Read genuine patient reviews to make informed decisions</p>
            </div>
        </div>
    </section>

    <!-- Specializations Section -->
    <section class="specializations" id="specializations">
        <h2 class="section-title fs-1">Find Specialists by Category</h2>
        <div class="spec-grid">
            <?php
            // Get specializations from database without status check
            $conn = getDBConnection();
            $spec_query = "SELECT * FROM specializations ORDER BY specialization_name";
            $spec_result = $conn->query($spec_query);

            if ($spec_result && $spec_result->num_rows > 0) {
                $specializations = $spec_result->fetch_all(MYSQLI_ASSOC);

                // Comprehensive icon mapping for specializations
                $spec_icons = [
                    'Cardiology' => 'fas fa-heartbeat',
                    'Cardiologist' => 'fas fa-heartbeat',
                    'Heart' => 'fas fa-heartbeat',

                    'Neurology' => 'fas fa-brain',
                    'Neurologist' => 'fas fa-brain',

                    'Orthopedics' => 'fas fa-bone',
                    'Orthopedic' => 'fas fa-bone',
                    'Bone' => 'fas fa-bone',

                    'Pediatrics' => 'fas fa-baby',
                    'Pediatrician' => 'fas fa-baby',
                    'Child Care' => 'fas fa-baby',

                    'Dentistry' => 'fas fa-tooth',
                    'Dental' => 'fas fa-tooth',
                    'Dentist' => 'fas fa-tooth',

                    'Ophthalmology' => 'fas fa-eye',
                    'Eye Care' => 'fas fa-eye',
                    'Ophthalmologist' => 'fas fa-eye',

                    'Dermatology' => 'fas fa-hand-sparkles',
                    'Skin Care' => 'fas fa-hand-sparkles',
                    'Dermatologist' => 'fas fa-hand-sparkles',

                    'General Physician' => 'fas fa-user-md',
                    'General Practice' => 'fas fa-user-md',
                    'Family Medicine' => 'fas fa-user-md',

                    'General Medicine' => 'fas fa-user-md',
                    'Medicine' => 'fas fa-user-md',

                    'Gynecology' => 'fas fa-female',
                    'Gynecologist' => 'fas fa-female',
                    'Women Health' => 'fas fa-female',

                    'Psychiatry' => 'fas fa-brain',
                    'Psychiatrist' => 'fas fa-brain',
                    'Mental Health' => 'fas fa-brain',

                    'ENT' => 'fas fa-ear',
                    'Ear Nose Throat' => 'fas fa-ear',
                    'Otolaryngology' => 'fas fa-ear',

                    'Urology' => 'fas fa-kidney',
                    'Urologist' => 'fas fa-kidney',
                    'Kidney' => 'fas fa-kidney',

                    'Gastroenterology' => 'fas fa-stomach',
                    'Gastroenterologist' => 'fas fa-stomach',
                    'Stomach' => 'fas fa-stomach',

                    'Endocrinology' => 'fas fa-vial',
                    'Endocrinologist' => 'fas fa-vial',
                    'Hormone' => 'fas fa-vial',

                    'Oncology' => 'fas fa-plus-square',
                    'Oncologist' => 'fas fa-plus-square',
                    'Cancer' => 'fas fa-plus-square',

                    'Rheumatology' => 'fas fa-bone',
                    'Rheumatologist' => 'fas fa-bone',
                    'Arthritis' => 'fas fa-bone',

                    'Pulmonology' => 'fas fa-lungs',
                    'Pulmonologist' => 'fas fa-lungs',
                    'Lung' => 'fas fa-lungs',

                    'Surgery' => 'fas fa-scissors',
                    'Surgeon' => 'fas fa-scissors',

                    'Radiology' => 'fas fa-x-ray',
                    'Radiologist' => 'fas fa-x-ray',

                    'Anesthesiology' => 'fas fa-syringe',
                    'Anesthesiologist' => 'fas fa-syringe',

                    'Emergency Medicine' => 'fas fa-ambulance',
                    'Emergency' => 'fas fa-ambulance',

                    'Pathology' => 'fas fa-microscope',
                    'Pathologist' => 'fas fa-microscope',

                    'Physiotherapy' => 'fas fa-hand-holding-heart',
                    'Physical Therapy' => 'fas fa-hand-holding-heart',

                    'Nutrition' => 'fas fa-apple-alt',
                    'Dietitian' => 'fas fa-apple-alt',

                    'Allergy' => 'fas fa-allergies',
                    'Immunology' => 'fas fa-shield-virus'
                ];

                foreach ($specializations as $spec):
                    $spec_name = $spec['specialization_name'];

                    // Debug: Check what specialization names are coming from database
                    // echo "<!-- Debug: Specialization Name: " . htmlspecialchars($spec_name) . " -->";
            
                    // Try exact match first
                    $icon = $spec_icons[$spec_name] ?? 'fas fa-user-md';

                    // If not found, try case-insensitive match
                    if ($icon === 'fas fa-user-md') {
                        $spec_name_lower = strtolower($spec_name);
                        foreach ($spec_icons as $key => $value) {
                            if (strtolower($key) === $spec_name_lower) {
                                $icon = $value;
                                break;
                            }
                        }
                    }

                    // Debug: Check which icon is being used
                    // echo "<!-- Debug: Using icon: " . $icon . " for " . htmlspecialchars($spec_name) . " -->";
                    ?>
                    <div class="spec-card" onclick="searchBySpecialization(<?php echo $spec['specialization_id']; ?>)"
                        data-spec-name="<?php echo htmlspecialchars($spec_name); ?>" data-icon-used="<?php echo $icon; ?>">
                        <i class="<?php echo $icon; ?>"></i>
                        <h3><?php echo htmlspecialchars($spec_name); ?></h3>
                    </div>
                <?php endforeach;
            } else {
                // Fallback to default specializations if database is empty
                $default_specializations = [
                    ['specialization_id' => 1, 'specialization_name' => 'Cardiology'],
                    ['specialization_id' => 2, 'specialization_name' => 'Neurology'],
                    ['specialization_id' => 3, 'specialization_name' => 'Orthopedics'],
                    ['specialization_id' => 4, 'specialization_name' => 'Pediatrics'],
                    ['specialization_id' => 5, 'specialization_name' => 'Dentistry'],
                    ['specialization_id' => 6, 'specialization_name' => 'Ophthalmology'],
                    ['specialization_id' => 7, 'specialization_name' => 'Dermatology'],
                    ['specialization_id' => 8, 'specialization_name' => 'General Physician']
                ];

                $spec_icons = [
                    'Cardiology' => 'fas fa-heartbeat',
                    'Neurology' => 'fas fa-brain',
                    'Orthopedics' => 'fas fa-bone',
                    'Pediatrics' => 'fas fa-baby',
                    'Dentistry' => 'fas fa-tooth',
                    'Ophthalmology' => 'fas fa-eye',
                    'Dermatology' => 'fas fa-hand-sparkles',
                    'General Physician' => 'fas fa-user-md'
                ];

                foreach ($default_specializations as $spec):
                    $icon = $spec_icons[$spec['specialization_name']] ?? 'fas fa-user-md';
                    ?>
                    <div class="spec-card" onclick="searchBySpecialization(<?php echo $spec['specialization_id']; ?>)">
                        <i class="<?php echo $icon; ?>"></i>
                        <h3><?php echo htmlspecialchars($spec['specialization_name']); ?></h3>
                    </div>
                <?php endforeach;
            }
            $conn->close();
            ?>
        </div>
    </section>



    <!-- Statistics Section -->
    <section class="stats">
        <div class="stats-grid">
            <div class="stat-item">
                <h2><span class="counter">1000</span>+</h2>
                <p>Qualified Doctors</p>
            </div>
            <div class="stat-item">
                <h2><span class="counter">50</span>+</h2>
                <p>Cities Covered</p>
            </div>
            <div class="stat-item">
                <h2><span class="counter">100000</span>+</h2>
                <p>Happy Patients</p>
            </div>
            <div class="stat-item">
                <h2><span class="counter">500000</span>+</h2>
                <p>Appointments Booked</p>
            </div>
        </div>
    </section>

    <!-- Diseases Section -->
    <section class="diseases" id="diseases">
        <h2 class="section-title fs-1">Health Information Center</h2>
        <div class="disease-cards">
            <?php if (count($diseases) > 0): ?>
                <?php foreach ($diseases as $disease): ?>
                    <div class="disease-card">
                        <div class="disease-header">
                            <h3><i class="fas fa-heartbeat"></i> <?php echo htmlspecialchars($disease['disease_name']); ?></h3>
                        </div>
                        <div class="disease-body">
                            <?php if ($disease['symptoms']): ?>
                                <div class="disease-section">
                                    <h4 class="fs-5">Symptoms</h4>
                                    <p><?php echo htmlspecialchars($disease['symptoms']); ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if ($disease['prevention']): ?>
                                <div class="disease-section">
                                    <h4 class="fs-5">Prevention</h4>
                                    <p><?php echo htmlspecialchars($disease['prevention']); ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if ($disease['cure']): ?>
                                <div class="disease-section">
                                    <h4 class="fs-5">Treatment</h4>
                                    <p><?php echo htmlspecialchars($disease['cure']); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Fallback content when no diseases are found -->
                <div class="disease-card">
                    <div class="disease-header">
                        <h3><i class="fas fa-heartbeat"></i> No Health Information Available</h3>
                    </div>
                    <div class="disease-body">
                        <p>Please check back later for health information updates.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- View More Button -->
        <div class="text-center mt-4">
            <a href="diseases_list.php" class="btn btn-primary view-more-btn">
                <i class="fas fa-arrow-circle-right me-2"></i>View More Health Information
            </a>
        </div>
    </section>

   <!-- Medical News Section -->
<section class="news" id="news">
    <h2 class="section-title fs-1">Latest Medical News & Innovations</h2>
    <div class="news-grid">
        <?php
        $conn = getDBConnection();
        $news_query = "SELECT * FROM medical_news WHERE status = 'published' ORDER BY published_date DESC LIMIT 3";
        $news_result = $conn->query($news_query);
        $news = $news_result->fetch_all(MYSQLI_ASSOC);
        $conn->close();

        if (count($news) > 0):
            foreach ($news as $item):
                // Simplified image handling
                $image_url = '';
                $show_image = false;

                if (!empty($item['image_url'])) {
                    // Database has: uploads/news/news_xxx.jpg
                    // File is at: /careGroup/admin/uploads/news/news_xxx.jpg
                    // We need URL: admin/uploads/news/news_xxx.jpg (relative to index.php)
                    $image_url = 'admin/' . $item['image_url'];
                    
                    // Check if file actually exists
                    $file_path = __DIR__ . '/admin/' . $item['image_url'];
                    if (file_exists($file_path)) {
                        $show_image = true;
                    }
                }
                ?>
                <div class="news-card">
                    <div class="news-image">
                        <?php if ($show_image): ?>
                            <img src="<?php echo htmlspecialchars($image_url); ?>"
                                alt="<?php echo htmlspecialchars($item['title']); ?>" 
                                class="news-img" 
                                loading="lazy"
                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="news-image-placeholder" style="display: none;">
                                <i class="fas fa-image"></i>
                                <small>Image failed to load</small>
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
                            <span><i class="far fa-calendar"></i> <?php echo date('M d, Y', strtotime($item['published_date'])); ?></span>
                            <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($item['category']); ?></span>
                        </div>
                        <h3><?php echo htmlspecialchars($item['title']); ?></h3>
                        <p><?php echo htmlspecialchars(substr($item['content'], 0, 150)); ?>...</p>
                        <?php if (!empty($item['author'])): ?>
                            <p style="font-size: 0.9rem; color: #64748b; margin-top: 0.5rem;">
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
            ?>
            <div class="news-card">
                <div class="news-image">
                    <div class="news-image-placeholder">
                        <i class="fas fa-image"></i>
                        <small>Sample news</small>
                    </div>
                </div>
                <div class="news-content">
                    <div class="news-meta">
                        <span><i class="far fa-calendar"></i> <?php echo date('M d, Y'); ?></span>
                        <span><i class="fas fa-tag"></i> Health</span>
                    </div>
                    <h3>Welcome to Our Medical News</h3>
                    <p>Stay updated with the latest medical breakthroughs and health innovations.</p>
                    <a href="#" style="color: var(--primary); font-weight: 600;">Read More →</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <!-- View More Button -->
    <div class="text-center mt-4">
        <a href="news_list.php" class="btn btn-primary mb-5">
            <i class="fas fa-arrow-circle-right me-2"></i>View More News
        </a>
    </div>
</section>



    <!--MEDICAL INVENTIONS SECTION  -->
    <section class="news" id="inventions">
        <h2 class="section-title fs-1">Latest Medical Inventions</h2>
        <div class="news-grid">
            <?php
            $conn = getDBConnection();
            $check_column = $conn->query("SHOW COLUMNS FROM inventions LIKE 'image_url'");
            $has_image_column = $check_column->num_rows > 0;

            $inventions_query = "SELECT * FROM inventions ORDER BY created_at DESC LIMIT 3";
            $inventions_result = $conn->query($inventions_query);
            $inventions = $inventions_result ? $inventions_result->fetch_all(MYSQLI_ASSOC) : [];
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
                                    alt="<?php echo htmlspecialchars($item['invention_name'] ?? 'Medical Invention'); ?>"
                                    class="news-img" loading="lazy"
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
                                        class="far fa-calendar"></i><?php echo date('M d, Y', strtotime($item['created_at'] ?? 'now')); ?></span>
                                <span><i
                                        class="fas fa-tag"></i><?php echo htmlspecialchars($item['category'] ?? 'General'); ?></span>
                            </div>
                            <h3><?php echo htmlspecialchars($item['invention_name'] ?? 'Untitled Invention'); ?></h3>
                            <p><?php echo htmlspecialchars(substr($item['description'] ?? 'No description available', 0, 150)); ?>...
                            </p>
                            <p style="font-size: 0.9rem; color: #64748b; margin-top: 0.5rem;">
                                <i class="fas fa-user"></i>
                                <?php echo htmlspecialchars($item['inventor'] ?? 'Unknown Inventor'); ?>
                            </p>
                            <p style="font-size: 0.85rem; color: #7590b4ff; margin-top: 0.5rem; font-weight: 600;">
                                <i class="fas fa-check-circle"></i> Benefits:
                                <?php echo htmlspecialchars(substr($item['benefits'] ?? 'No benefits information available', 0, 80)); ?>...
                            </p>
                            <a href="news_detail.php?type=invention&id=<?php echo $item['id']; ?>" class="read-more-btn">Read
                                More →</a>
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
                        <a href="inventions.php" style="color: var(--primary); font-weight: 600; margin-top: 1rem;">Read
                            More →</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- View More Button -->
        <div class="text-center mt-4">
            <a href="inventions.php" class="btn btn-primary mb-5">
                <i class="fas fa-arrow-circle-right me-2"></i>View More Inventions
            </a>
        </div>
    </section>


    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3><i class="fas fa-heartbeat"></i> <?php echo htmlspecialchars($site_name); ?></h3>
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

    <script>
        // Counter Animation
        const counters = document.querySelectorAll('.counter');
        counters.forEach(counter => {
            const target = parseInt(counter.innerText);
            let count = 0;
            const increment = target / 100;

            const updateCounter = () => {
                if (count < target) {
                    count += increment;
                    counter.innerText = Math.ceil(count);
                    setTimeout(updateCounter, 20);
                } else {
                    counter.innerText = target;
                }
            };

            const observer = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        updateCounter();
                        observer.unobserve(entry.target);
                    }
                });
            });

            observer.observe(counter.parentElement);
        });
        function searchBySpecialization(specializationId) {
            // Add loading effect
            const card = event.currentTarget;
            const originalHTML = card.innerHTML;

            // Redirect to search page with specialization filter
            setTimeout(() => {
                window.location.href = `search_doctors.php?specialization=${specializationId}`;
            }, 300);
        }
        // Alternative: If you want to use anchor tags instead of onclick
        function makeSpecCardsClickable() {
            document.querySelectorAll('.spec-card').forEach(card => {
                card.style.cursor = 'pointer';
                card.addEventListener('click', function () {
                    const specId = this.getAttribute('data-spec-id');
                    if (specId) {
                        searchBySpecialization(specId);
                    }
                });
            });
        }
        function handleImageError(img) {
            console.log('Image failed to load:', {
                src: img.src,
                title: img.getAttribute('data-title'),
                originalUrl: img.getAttribute('data-image-url')
            });

            img.style.display = 'none';
            const placeholder = img.nextElementSibling;
            if (placeholder && placeholder.classList.contains('news-image-placeholder')) {
                placeholder.style.display = 'flex';
            }
        }

        // Add image error handling
        document.addEventListener('DOMContentLoaded', function () {
            const newsImages = document.querySelectorAll('.news-img');
            newsImages.forEach(img => {
                img.addEventListener('error', function () {
                    handleImageError(this);
                });

                // Log successful image loads
                img.addEventListener('load', function () {
                    console.log('Image loaded successfully:', {
                        src: this.src,
                        title: this.getAttribute('data-title')
                    });
                });
            });

            // Debug: Check all image elements
            console.log('Total news images found:', newsImages.length);
            newsImages.forEach((img, index) => {
                console.log(`Image ${index + 1}:`, {
                    src: img.src,
                    title: img.getAttribute('data-title'),
                    exists: img.complete && img.naturalHeight !== 0
                });
            });
        });

        // Mobile Menu Toggle
        const mobileMenu = document.getElementById('mobile-menu');
        const navMenu = document.getElementById('nav-menu');

        mobileMenu.addEventListener('click', function () {
            mobileMenu.classList.toggle('active');
            navMenu.classList.toggle('active');

            // Prevent body scroll when menu is open
            document.body.style.overflow = navMenu.classList.contains('active') ? 'hidden' : '';
        });

        // Debug function to check which icons are being used
        document.addEventListener('DOMContentLoaded', function () {
            const specCards = document.querySelectorAll('.spec-card');
            console.log('Total specialization cards:', specCards.length);

            specCards.forEach(card => {
                const specName = card.getAttribute('data-spec-name');
                const iconUsed = card.getAttribute('data-icon-used');
                console.log('Specialization:', specName, '| Icon:', iconUsed);
            });
        });
        // Close menu when clicking on a link
        document.querySelectorAll('.nav-menu a').forEach(link => {
            link.addEventListener('click', () => {
                mobileMenu.classList.remove('active');
                navMenu.classList.remove('active');
                document.body.style.overflow = '';
            });
        });
        // Close menu when clicking outside
        document.addEventListener('click', function (event) {
            const isClickInsideNav = navMenu.contains(event.target) || mobileMenu.contains(event.target);

            if (!isClickInsideNav && navMenu.classList.contains('active')) {
                mobileMenu.classList.remove('active');
                navMenu.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
        // Close menu on window resize (if resizing to larger screen)
        window.addEventListener('resize', function () {
            if (window.innerWidth > 768) {
                mobileMenu.classList.remove('active');
                navMenu.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', makeSpecCardsClickable);
    </script>
</body>

</html>