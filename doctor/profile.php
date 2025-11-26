<?php
// doctor/profile.php - Doctor Profile Page
session_start();
require_once '../config.php';

// === AUTH CHECK ===
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../login.php");
    exit();
}

// === GET DOCTOR PROFILE ===
$doctor = getDoctorByUserId($_SESSION['user_id']);
if (!$doctor) {
    echo "<div style='text-align:center; padding:50px; font-family:Arial;'>
            <h2>Doctor Profile Not Found</h2>
            <p>User ID: " . $_SESSION['user_id'] . " is not linked to any doctor.</p>
            <a href='../logout.php'>Logout</a>
          </div>";
    exit();
}

$conn = getDBConnection();

// Get complete doctor details with joins
$stmt = $conn->prepare("
    SELECT d.*, s.specialization_name, c.city_name, u.email, u.username, u.created_at as user_created
    FROM doctors d 
    LEFT JOIN specializations s ON d.specialization_id = s.specialization_id 
    LEFT JOIN cities c ON d.city_id = c.city_id
    LEFT JOIN users u ON d.user_id = u.user_id
    WHERE d.doctor_id = ?
");
$stmt->bind_param("i", $doctor['doctor_id']);
$stmt->execute();
$doctor_details = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get doctor statistics
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_appointments,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
        SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled_appointments,
        AVG(rating) as avg_rating,
        COUNT(rating) as total_ratings
    FROM appointments 
    WHERE doctor_id = ?
");
$stats_stmt->bind_param("i", $doctor['doctor_id']);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

// Get recent reviews
$reviews_stmt = $conn->prepare("
    SELECT a.rating, a.review, p.full_name as patient_name, a.appointment_date
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    WHERE a.doctor_id = ? AND a.rating IS NOT NULL AND a.status = 'completed'
    ORDER BY a.appointment_date DESC
    LIMIT 3
");
$reviews_stmt->bind_param("i", $doctor['doctor_id']);
$reviews_stmt->execute();
$recent_reviews = $reviews_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$reviews_stmt->close();

// Get weekly appointments for chart
$weekly_appointments = [];
$week_start = date('Y-m-d', strtotime('monday this week'));
for ($i = 0; $i < 7; $i++) {
    $day = date('Y-m-d', strtotime($week_start . " +$i days"));
    $day_stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE doctor_id = ? AND appointment_date = ? AND status != 'cancelled'
    ");
    $day_stmt->bind_param("is", $doctor['doctor_id'], $day);
    $day_stmt->execute();
    $day_result = $day_stmt->get_result()->fetch_assoc();
    $weekly_appointments[] = [
        'day' => date('D', strtotime($day)),
        'count' => $day_result['count'] ?? 0
    ];
    $day_stmt->close();
}

$conn->close();

// Format experience text
function getExperienceText($years) {
    if ($years == 0) return "Fresh Graduate";
    if ($years == 1) return "1 year experience";
    return "$years years experience";
}

// Calculate age from date of birth
function calculateAge($dob) {
    if (!$dob) return null;
    $birthDate = new DateTime($dob);
    $today = new DateTime();
    $age = $today->diff($birthDate);
    return $age->y;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Doctor Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .top-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .notification-bell {
            position: relative;
            width: 45px;
            height: 45px;
            background: var(--light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            color: var(--gray);
        }

        .notification-bell:hover {
            background: var(--primary);
            color: white;
            transform: scale(1.05);
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
            background: #b91c1c;
            transform: translateY(-2px);
        }

        .profile-container {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 2rem;
        }

        .profile-sidebar {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        .profile-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            background: white;
            border-radius: 50%;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: var(--primary);
            border: 4px solid white;
            box-shadow: var(--card-shadow);
        }

        .profile-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .profile-specialization {
            opacity: 0.9;
            margin-bottom: 0.25rem;
        }

        .profile-location {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            opacity: 0.8;
            font-size: 0.9rem;
        }

        .profile-stats {
            padding: 1.5rem;
        }

        .stat-item {
            display: flex;
            justify-content: between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border);
        }

        .stat-item:last-child {
            border-bottom: none;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }

        .stat-icon.primary { background: #dbeafe; color: var(--primary); }
        .stat-icon.success { background: #dcfce7; color: var(--success); }
        .stat-icon.warning { background: #fef3c7; color: var(--warning); }
        .stat-icon.danger { background: #fee2e2; color: var(--danger); }

        .stat-info {
            flex: 1;
        }

        .stat-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--gray);
        }

        .profile-main {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .info-card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            padding: 2rem;
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border);
        }

        .card-header i {
            color: var(--primary);
            font-size: 1.5rem;
        }

        .card-header h2 {
            color: var(--dark);
            font-size: 1.5rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .info-label {
            font-weight: 600;
            color: var(--gray);
            font-size: 0.875rem;
        }

        .info-value {
            color: var(--dark);
            font-size: 1rem;
        }

        .bio-section {
            grid-column: 1 / -1;
        }

        .bio-text {
            background: var(--light);
            padding: 1.5rem;
            border-radius: 8px;
            line-height: 1.6;
            color: var(--dark);
        }

        .reviews-grid {
            display: grid;
            gap: 1rem;
        }

        .review-card {
            background: var(--light);
            padding: 1.5rem;
            border-radius: 8px;
            border-left: 4px solid var(--warning);
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .review-patient {
            font-weight: 600;
            color: var(--dark);
        }

        .review-stars {
            color: var(--warning);
        }

        .review-text {
            color: var(--gray);
            margin-bottom: 0.5rem;
            line-height: 1.5;
        }

        .review-date {
            font-size: 0.875rem;
            color: var(--secondary);
        }

        .chart-container {
            height: 200px;
            margin-top: 1rem;
        }

        .no-reviews {
            text-align: center;
            padding: 2rem;
            color: var(--gray);
        }

        .no-reviews i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
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

            .profile-container {
                grid-template-columns: 1fr;
            }

            .info-grid {
                grid-template-columns: 1fr;
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
                <h3>Dr. <?php echo $doctor_details['full_name']; ?></h3>
                <p><?php echo $doctor_details['specialization_name']; ?></p>
                <p style="color: #cbd5e1; font-size: 0.8rem; margin-top: 0.5rem;">
                    <i class="fas fa-map-marker-alt"></i> <?php echo $doctor_details['city_name']; ?>
                </p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
                <li><a href="appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a></li>
                <li><a href="availability.php"><i class="fas fa-clock"></i> Availability</a></li>
                <li><a href="patients.php"><i class="fas fa-users"></i> My Patients</a></li>
                <li><a href="profile.php" class="active"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="reviews.php"><i class="fas fa-star"></i> Reviews</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="welcome-text">
                    <h1>My Profile</h1>
                    <p>View and manage your professional profile</p>
                </div>
                <div class="top-actions">
                    <div class="notification-bell">
                        <i class="fas fa-bell"></i>
                    </div>
                    <a href="../logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <div class="profile-container">
                <!-- Profile Sidebar -->
                <div class="profile-sidebar">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <i class="fas fa-user-md"></i>
                        </div>
                        <h1 class="profile-name">Dr. <?php echo $doctor_details['full_name']; ?></h1>
                        <p class="profile-specialization"><?php echo $doctor_details['specialization_name']; ?></p>
                        <div class="profile-location">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo $doctor_details['city_name']; ?></span>
                        </div>
                    </div>
                    
                    <div class="profile-stats">
                        <div class="stat-item">
                            <div class="stat-icon primary">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="stat-info">
                                <div class="stat-value"><?php echo $stats['total_appointments'] ?? 0; ?></div>
                                <div class="stat-label">Total Appointments</div>
                            </div>
                        </div>
                        
                        <div class="stat-item">
                            <div class="stat-icon success">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-info">
                                <div class="stat-value"><?php echo $stats['completed_appointments'] ?? 0; ?></div>
                                <div class="stat-label">Completed</div>
                            </div>
                        </div>
                        
                        <div class="stat-item">
                            <div class="stat-icon warning">
                                <i class="fas fa-star"></i>
                            </div>
                            <div class="stat-info">
                                <div class="stat-value"><?php echo number_format($stats['avg_rating'] ?? 0, 1); ?></div>
                                <div class="stat-label">Average Rating</div>
                            </div>
                        </div>
                        
                        <div class="stat-item">
                            <div class="stat-icon danger">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-info">
                                <div class="stat-value"><?php echo $stats['total_ratings'] ?? 0; ?></div>
                                <div class="stat-label">Total Ratings</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profile Main Content -->
                <div class="profile-main">
                    <!-- Personal Information -->
                    <div class="info-card">
                        <div class="card-header">
                            <i class="fas fa-user-circle"></i>
                            <h2>Personal Information</h2>
                        </div>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Full Name</span>
                                <span class="info-value">Dr. <?php echo $doctor_details['full_name']; ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Email Address</span>
                                <span class="info-value"><?php echo $doctor_details['email']; ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Phone Number</span>
                                <span class="info-value"><?php echo $doctor_details['phone'] ?? 'Not provided'; ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Medical License</span>
                                <span class="info-value"><?php echo $doctor_details['license_number'] ?? 'Not provided'; ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Experience</span>
                                <span class="info-value"><?php echo getExperienceText($doctor_details['experience'] ?? 0); ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Consultation Fee</span>
                                <span class="info-value">$<?php echo number_format($doctor_details['consultation_fee'] ?? 0, 2); ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Qualification</span>
                                <span class="info-value"><?php echo $doctor_details['qualification'] ?? 'Not provided'; ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Member Since</span>
                                <span class="info-value"><?php echo date('F Y', strtotime($doctor_details['user_created'])); ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Specialization</span>
                                <span class="info-value"><?php echo $doctor_details['specialization_name']; ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Location</span>
                                <span class="info-value"><?php echo $doctor_details['city_name']; ?></span>
                            </div>
                            
                            <?php if (!empty($doctor_details['address'])): ?>
                            <div class="info-item bio-section">
                                <span class="info-label">Clinic Address</span>
                                <span class="info-value"><?php echo $doctor_details['address']; ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Professional Bio -->
                    <?php if (!empty($doctor_details['bio'])): ?>
                    <div class="info-card">
                        <div class="card-header">
                            <i class="fas fa-file-medical-alt"></i>
                            <h2>Professional Bio</h2>
                        </div>
                        <div class="bio-text">
                            <?php echo nl2br(htmlspecialchars($doctor_details['bio'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Weekly Appointments Chart -->
                    <div class="info-card">
                        <div class="card-header">
                            <i class="fas fa-chart-bar"></i>
                            <h2>This Week's Appointments</h2>
                        </div>
                        <div class="chart-container">
                            <canvas id="appointmentsChart"></canvas>
                        </div>
                    </div>

                    <!-- Recent Reviews -->
                    <div class="info-card">
                        <div class="card-header">
                            <i class="fas fa-star"></i>
                            <h2>Recent Patient Reviews</h2>
                        </div>
                        <div class="reviews-grid">
                            <?php if (!empty($recent_reviews)): ?>
                                <?php foreach ($recent_reviews as $review): ?>
                                    <div class="review-card">
                                        <div class="review-header">
                                            <span class="review-patient"><?php echo htmlspecialchars($review['patient_name']); ?></span>
                                            <div class="review-stars">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : '-o'; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        <?php if (!empty(trim($review['review']))): ?>
                                            <p class="review-text">"<?php echo htmlspecialchars($review['review']); ?>"</p>
                                        <?php endif; ?>
                                        <div class="review-date">
                                            <?php echo date('M j, Y', strtotime($review['appointment_date'])); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-reviews">
                                    <i class="fas fa-comment-slash"></i>
                                    <h3>No Reviews Yet</h3>
                                    <p>Patient reviews will appear here after appointments</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Weekly Appointments Chart
        const ctx = document.getElementById('appointmentsChart').getContext('2d');
        const appointmentsChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($weekly_appointments, 'day')); ?>,
                datasets: [{
                    label: 'Appointments',
                    data: <?php echo json_encode(array_column($weekly_appointments, 'count')); ?>,
                    backgroundColor: 'rgba(37, 99, 235, 0.6)',
                    borderColor: 'rgba(37, 99, 235, 1)',
                    borderWidth: 1,
                    borderRadius: 8,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    </script>
</body>
</html>