<?php
// doctor/reviews.php - All Patient Reviews
require_once '../config.php';
requireRole('doctor');

$doctor = getDoctorByUserId($_SESSION['user_id']);
if (!$doctor) {
    die("Doctor profile not found.");
}

$conn = getDBConnection();

// Search & Pagination
$search = trim($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Count total reviews
$count_query = "
    SELECT COUNT(*) as total
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    WHERE a.doctor_id = ? AND a.rating IS NOT NULL AND a.rating > 0 AND a.status = 'completed'
";
if ($search) {
    $count_query .= " AND p.full_name LIKE ?";
}

$stmt = $conn->prepare($count_query);
if ($search) {
    $like = "%$search%";
    $stmt->bind_param("is", $doctor['doctor_id'], $like);
} else {
    $stmt->bind_param("i", $doctor['doctor_id']);
}
$stmt->execute();
$count_result = $stmt->get_result()->fetch_assoc();
$total_reviews = $count_result['total'] ?? 0;
$total_pages = max(1, ceil($total_reviews / $per_page));
$stmt->close();

// Fetch reviews
$reviews_query = "
    SELECT a.rating, a.review, a.appointment_id, p.full_name AS patient_name, a.appointment_date
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    WHERE a.doctor_id = ? 
      AND a.rating IS NOT NULL 
      AND a.rating > 0
      AND a.status = 'completed'
";
if ($search) {
    $reviews_query .= " AND p.full_name LIKE ?";
}
$reviews_query .= " ORDER BY a.appointment_date DESC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($reviews_query);
if ($search) {
    $like = "%$search%";
    $stmt->bind_param("isii", $doctor['doctor_id'], $like, $per_page, $offset);
} else {
    $stmt->bind_param("iii", $doctor['doctor_id'], $per_page, $offset);
}
$stmt->execute();
$reviews_result = $stmt->get_result();
$reviews = $reviews_result->fetch_all(MYSQLI_ASSOC);

// Average rating
$avg_query = "
    SELECT AVG(rating) as avg_rating, COUNT(rating) as total_ratings 
    FROM appointments 
    WHERE doctor_id = ? AND rating IS NOT NULL AND rating > 0 AND status = 'completed'
";
$stmt = $conn->prepare($avg_query);
$stmt->bind_param("i", $doctor['doctor_id']);
$stmt->execute();
$avg_result = $stmt->get_result()->fetch_assoc();
$avg_rating = round($avg_result['avg_rating'] ?? 0, 1);
$total_reviews = $avg_result['total_ratings'] ?? 0;

$stmt->close();
$conn->close();

// Helper function to display stars
function displayStars($rating) {
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= floor($rating)) {
            $stars .= '<i class="fas fa-star"></i>';
        } elseif ($i == ceil($rating) && fmod($rating, 1) > 0) {
            $stars .= '<i class="fas fa-star-half-alt"></i>';
        } else {
            $stars .= '<i class="far fa-star"></i>';
        }
    }
    return $stars;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Reviews - Dr. <?= htmlspecialchars($doctor['full_name']) ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #1a73e8;
            --primary-dark: #0d47a1;
            --primary-light: #64b5f6;
            --secondary: #43a047;
            --secondary-dark: #2e7d32;
            --secondary-light: #81c784;
            --accent: #ff9800;
            --accent-dark: #f57c00;
            --accent-light: #ffb74d;
            --dark: #263238;
            --light: #f5f7fa;
            --gray: #607d8b;
            --light-gray: #cfd8dc;
            --success: #4caf50;
            --warning: #ff9800;
            --danger: #f44336;
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
            margin: 0;
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
            border-left: 4px solid var(--primary);
        }

        .welcome-text h1 {
            color: var(--dark);
            margin-bottom: 0.25rem;
            font-size: 1.8rem;
            font-weight: 700;
        }

        .welcome-text p {
            color: var(--gray);
            margin: 0;
        }

        .logout-btn {
            padding: 0.75rem 1.5rem;
            background: var(--danger);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: #d32f2f;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(244, 67, 54, 0.3);
        }

        .content-section {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            border: 1px solid var(--light-gray);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--light-gray);
        }

        .section-header h2 {
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
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
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(26, 115, 232, 0.3);
        }

        .search-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 1;
            padding: 0.75rem 1rem;
            border: 2px solid var(--light-gray);
            border-radius: 8px;
            font-size: 1rem;
            min-width: 200px;
            transition: all 0.3s;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.1);
        }

        .btn-search {
            padding: 0.75rem 1.5rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-search:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .rating-summary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
        }

        .rating-summary h1 {
            font-size: 3rem;
            margin: 0.5rem 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .rating-summary .stars {
            color: #fbbf24;
            font-size: 1.8rem;
        }

        .review-card {
            background: white;
            border: 1px solid var(--light-gray);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
        }

        .review-card:hover {
            box-shadow: var(--hover-shadow);
            transform: translateY(-5px);
        }

        .review-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, var(--warning) 0%, var(--accent) 100%);
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .review-header strong {
            color: var(--dark);
            font-weight: 600;
        }

        .review-stars {
            color: #fbbf24;
            font-size: 1.1rem;
        }

        .review-text {
            color: var(--gray);
            font-size: 0.95rem;
            margin: 0.5rem 0;
            line-height: 1.5;
        }

        .review-text em {
            color: #94a3b8;
            font-style: italic;
        }

        .review-meta {
            font-size: 0.8rem;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
            color: var(--primary);
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .page-link {
            padding: 0.5rem 1rem;
            background: white;
            color: var(--dark);
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }

        .page-link:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .page-link.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
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

            .search-bar {
                flex-direction: column;
            }

            .search-input {
                min-width: 100%;
            }

            .review-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
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
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
                <li><a href="appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a></li>
                <li><a href="availability.php"><i class="fas fa-clock"></i> Availability</a></li>
                <li><a href="patients.php"><i class="fas fa-users"></i> My Patients</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="reviews.php" class="active"><i class="fas fa-star"></i> Reviews</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="welcome-text">
                    <h1>Patient Reviews</h1>
                    <p>View and manage patient feedback</p>
                </div>
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>

            <!-- Rating Summary -->
            <div class="rating-summary">
                <h1>
                    <?= $avg_rating ?>
                    <span class="stars">
                        <?= displayStars($avg_rating) ?>
                    </span>
                </h1>
                <p><strong><?= $total_reviews ?></strong> total review<?= $total_reviews !== 1 ? 's' : '' ?></p>
            </div>

            <!-- Search Bar -->
            <form method="GET" class="search-bar">
                <input type="text" name="search" class="search-input" placeholder="Search by patient name..."
                    value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn-search">
                    <i class="fas fa-search"></i> Search
                </button>
            </form>

            <!-- Reviews List -->
            <div class="content-section">
                <?php if (!empty($reviews)): ?>
                    <?php foreach ($reviews as $rev): ?>
                        <div class="review-card">
                            <div class="review-header">
                                <strong><?= htmlspecialchars($rev['patient_name']) ?></strong>
                                <div class="review-stars">
                                    <?= displayStars($rev['rating']) ?>
                                    <small style="color: #666; margin-left: 0.5rem;">(<?= $rev['rating'] ?>)</small>
                                </div>
                            </div>
                            <?php if (!empty(trim($rev['review']))): ?>
                                <p class="review-text">"<?= nl2br(htmlspecialchars($rev['review'])) ?>"</p>
                            <?php else: ?>
                                <p class="review-text"><em>No written review provided</em></p>
                            <?php endif; ?>
                            <div class="review-meta">
                                <i class="fas fa-calendar"></i> 
                                <?= date('F j, Y', strtotime($rev['appointment_date'])) ?>
                                <i class="fas fa-hashtag"></i>
                                Appointment #<?= $rev['appointment_id'] ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" 
                               class="page-link <?= $i == $page ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-star"></i>
                        <h3>No ratings found</h3>
                        <p><?= $search ? 'Try a different search.' : 'No patient ratings yet.' ?></p>
                        <?php if (!$search): ?>
                        <p style="margin-top: 1rem;"><small>Ratings will appear here after patients rate their completed appointments.</small></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>