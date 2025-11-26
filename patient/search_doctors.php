<?php
// patient/search_doctors.php
require_once '../config.php';
requireRole('patient');

$patient = getPatientByUserId($_SESSION['user_id']);
if (!$patient) {
    echo '<div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">';
    echo '<h2>Patient Profile Not Found</h2>';
    echo '<p>Please contact the administrator to set up your profile.</p>';
    echo '<a href="../logout.php" style="padding: 10px 20px; background: #dc2626; color: white; text-decoration: none; border-radius: 5px;">Logout</a>';
    echo '</div>';
    exit();
}

$conn = getDBConnection();

// Get specializations and cities for filters
$specializations = $conn->query("SELECT specialization_id, specialization_name FROM specializations ORDER BY specialization_name")->fetch_all(MYSQLI_ASSOC);
$cities = $conn->query("SELECT city_id, city_name FROM cities ORDER BY city_name")->fetch_all(MYSQLI_ASSOC);

// Handle search and filters
$search_query = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$specialization_id = isset($_GET['specialization_id']) ? (int) $_GET['specialization_id'] : 0;
$city_id = isset($_GET['city_id']) ? (int) $_GET['city_id'] : 0;

$sql = "
    SELECT d.doctor_id, d.full_name, d.qualification, d.experience_years, d.consultation_fee, d.rating, 
           s.specialization_name, c.city_name
    FROM doctors d
    JOIN specializations s ON d.specialization_id = s.specialization_id
    JOIN cities c ON d.city_id = c.city_id
    WHERE d.status = 'active'";
$params = [];
$types = '';

if ($search_query) {
    $sql .= " AND d.full_name LIKE ?";
    $params[] = "%$search_query%";
    $types .= 's';
}
if ($specialization_id) {
    $sql .= " AND d.specialization_id = ?";
    $params[] = $specialization_id;
    $types .= 'i';
}
if ($city_id) {
    $sql .= " AND d.city_id = ?";
    $params[] = $city_id;
    $types .= 'i';
}

$sql .= " ORDER BY d.rating DESC, d.full_name ASC";
$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$doctors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Doctors - CARE Group</title>
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

        /* Content Sections */
        .content-section {
            background: white;
            padding: 1.5rem;
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

        /* Search Form */
        .search-form {
            display: grid;
            grid-template-columns: 1fr auto auto auto;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.75rem;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            outline: none;
        }

        /* Doctor Cards */
        .doctor-card {
            background: #f8fafc;
            border-left: 4px solid #3b82f6;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s;
            border: 1px solid #e2e8f0;
        }

        .doctor-card:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transform: translateX(5px);
        }

        .doctor-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .doctor-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .doctor-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .doctor-details h4 {
            color: #1e293b;
            margin-bottom: 0.25rem;
            font-weight: 600;
        }

        .doctor-details p {
            color: #64748b;
            font-size: 0.9rem;
        }

        .doctor-meta {
            display: flex;
            gap: 2rem;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 1px solid #e2e8f0;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #64748b;
            font-size: 0.9rem;
        }

        .meta-item i {
            color: #6366f1;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #64748b;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            margin-bottom: 0.5rem;
            color: #475569;
        }

        /* Rating Stars */
        .rating-stars {
            color: #fbbf24;
            font-size: 0.9rem;
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

            .search-form {
                grid-template-columns: 1fr;
            }

            .doctor-header {
                flex-direction: column;
                gap: 1rem;
            }

            .doctor-meta {
                flex-direction: column;
                gap: 1rem;
            }
        }

        /* Main content scrollbar */
        .main-content::-webkit-scrollbar {
            width: 8px;
        }

        .main-content::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .main-content::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        .main-content::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Sidebar scrollbar */
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
                <h3><?= htmlspecialchars($patient['full_name']) ?></h3>
                <p>Patient ID: #<?= $patient['patient_id'] ?></p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
                <li><a href="search_doctors.php" class="active"><i class="fas fa-search"></i> Find Doctors</a></li>
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
                    <h1>Find Doctors</h1>
                    <p>Search for specialists in your area</p>
                </div>
                <div class="top-actions">
                    <a href="dashboard.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <a href="../logout.php" class="btn logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <!-- Search Section -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-search"></i> Search Filters</h2>
                </div>
                
                <form class="search-form" method="GET">
                    <input type="text" name="search" class="form-control" placeholder="Search by doctor name"
                        value="<?= htmlspecialchars($search_query) ?>">
                    <select name="specialization_id" class="form-control">
                        <option value="0">All Specializations</option>
                        <?php foreach ($specializations as $spec): ?>
                            <option value="<?= $spec['specialization_id'] ?>"
                                <?= $specialization_id == $spec['specialization_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($spec['specialization_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="city_id" class="form-control">
                        <option value="0">All Cities</option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?= $city['city_id'] ?>" <?= $city_id == $city['city_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($city['city_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                </form>

                <?php if (count($doctors) > 0): ?>
                    <div class="section-header">
                        <h2><i class="fas fa-user-md"></i> Available Doctors (<?= count($doctors) ?>)</h2>
                    </div>
                    
                    <?php foreach ($doctors as $doctor): ?>
                        <div class="doctor-card">
                            <div class="doctor-header">
                                <div class="doctor-info">
                                    <div class="doctor-avatar">
                                        <i class="fas fa-user-md"></i>
                                    </div>
                                    <div class="doctor-details">
                                        <h4>Dr. <?= htmlspecialchars($doctor['full_name']) ?></h4>
                                        <p><?= htmlspecialchars($doctor['specialization_name']) ?></p>
                                        <p><?= htmlspecialchars($doctor['qualification'] ?? 'N/A') ?></p>
                                    </div>
                                </div>
                                <a href="book_appointment.php?doctor_id=<?= $doctor['doctor_id'] ?>"
                                    class="btn btn-primary">
                                    <i class="fas fa-calendar-plus"></i> Book Appointment
                                </a>
                            </div>
                            <div class="doctor-meta">
                                <div class="meta-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?= htmlspecialchars($doctor['city_name']) ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-rupee-sign"></i>
                                    <span>$<?= number_format($doctor['consultation_fee'], 2) ?></span>
                                </div>
                               <div class="meta-item">
    <i class="fas fa-star"></i>
    <span><?= number_format($doctor['rating'] ?? 0, 1) ?> / 5</span>
</div> 
                                <div class="meta-item">
                                    <i class="fas fa-briefcase"></i>
                                    <span><?= $doctor['experience_years'] ?> years experience</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h3>No Doctors Found</h3>
                        <p>Try adjusting your search criteria</p>
                        <a href="search_doctors.php" class="btn btn-primary" style="margin-top: 1rem;">
                            <i class="fas fa-redo"></i> Clear Filters
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>