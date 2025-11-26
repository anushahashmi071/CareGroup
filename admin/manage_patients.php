<?php
// admin/manage_patients.php - Manage All Patients
require_once '../config.php';
requireRole('admin');

// site name
$site_name = getSetting('site_name', 'CARE Group Medical Services');

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

$conn = getDBConnection();

$query = "
    SELECT p.*, c.city_name, u.status as user_status
    FROM patients p
    JOIN cities c ON p.city_id = c.city_id
    JOIN users u ON p.user_id = u.user_id
";

if (!empty($search)) {
    $query .= " WHERE p.full_name LIKE ? OR p.phone LIKE ? OR p.email LIKE ?";
}

$query .= " ORDER BY p.created_at DESC";

$stmt = $conn->prepare($query);

if (!empty($search)) {
    $search_param = "%$search%";
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
}

$stmt->execute();
$patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get patient statistics for the slider
$stats_query = "
    SELECT 
        COUNT(*) as total_patients,
        COUNT(CASE WHEN gender = 'Male' THEN 1 END) as male_patients,
        COUNT(CASE WHEN gender = 'Female' THEN 1 END) as female_patients,
        AVG(YEAR(CURDATE()) - YEAR(date_of_birth)) as avg_age,
        COUNT(DISTINCT city_id) as cities_covered
    FROM patients
    WHERE date_of_birth IS NOT NULL
";
$stats_result = $conn->query($stats_query);
$patient_stats = $stats_result->fetch_assoc();

// Handle delete
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $patient_id = intval($_GET['id']);
    $stmt = $conn->prepare("DELETE FROM patients WHERE patient_id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    header("Location: manage_patients.php");
    exit();
}

$stmt->close();
$conn->close();

// Helper function for date formatting
if (!function_exists('formatDate')) {
    function formatDate($date) {
        return $date ? date('F j, Y', strtotime($date)) : 'N/A';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Patients - Admin Panel</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4cc9f0;
            --info-color: #4895ef;
            --warning-color: #f72585;
            --danger-color: #e63946;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --sidebar-bg: #1e293b;
            --sidebar-hover: #334155;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f1f5f9;
            color: #334155;
            overflow-x: hidden;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 260px;
            background: var(--sidebar-bg);
            color: white;
            transition: var(--transition);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 1.5rem 1rem;
            border-bottom: 1px solid #334155;
            text-align: center;
        }

        .sidebar-header h2 {
            color: #fff;
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
        }

        .sidebar-header p {
            color: #94a3b8;
            font-size: 0.875rem;
        }

        .sidebar-menu {
            list-style: none;
            padding: 1rem 0;
        }

        .sidebar-menu li {
            margin-bottom: 0.25rem;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: #cbd5e1;
            text-decoration: none;
            transition: var(--transition);
        }

        .sidebar-menu a:hover {
            background-color: var(--sidebar-hover);
            color: white;
        }

        .sidebar-menu a.active {
            background-color: var(--primary-color);
            color: white;
            border-right: 4px solid var(--success-color);
        }

        .sidebar-menu i {
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            transition: var(--transition);
        }

        /* Top Bar */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            background: white;
            box-shadow: var(--card-shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .top-bar h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--light-color);
            color: var(--dark-color);
            text-decoration: none;
            border-radius: 0.375rem;
            transition: var(--transition);
        }

        .logout-btn:hover {
            background: #e2e8f0;
        }

        /* Page Content */
        .page-content {
            padding: 2rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-header h1 i {
            color: var(--primary-color);
        }

        /* Controls Section */
        .controls {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 1.5rem;
        }

        .search-box {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            color: #64748b;
        }

        .search-box input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: var(--transition);
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        /* Table Container */
        .table-container {
            background: white;
            border-radius: 0.75rem;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        /* Table Styles */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f8fafc;
        }

        th {
            padding: 1rem 1.5rem;
            text-align: left;
            font-weight: 600;
            color: #475569;
            font-size: 0.875rem;
            border-bottom: 1px solid #e2e8f0;
        }

        td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        .action-btns {
            display: flex;
            gap: 0.5rem;
        }

        .btn {
            padding: 0.5rem;
            border-radius: 0.375rem;
            text-decoration: none;
            font-size: 0.75rem;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border: none;
            cursor: pointer;
        }

        .btn-view {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .btn-view:hover {
            background: #bfdbfe;
            transform: translateY(-2px);
        }

        .btn-delete {
            background: #fee2e2;
            color: #dc2626;
        }

        .btn-delete:hover {
            background: #fecaca;
            transform: translateY(-2px);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .modal-content {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #64748b;
            transition: var(--transition);
        }

        .modal-close:hover {
            color: #dc2626;
        }

        .modal-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f5f9;
        }

        .modal-header h2 {
            color: var(--dark-color);
            margin-top: 1rem;
        }

        .patient-info-grid {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 1.5rem;
            align-items: start;
            margin-bottom: 2rem;
        }

        .patient-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
        }

        .patient-details h3 {
            color: var(--dark-color);
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .patient-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #64748b;
            font-size: 0.9rem;
        }

        .meta-item i {
            color: var(--primary-color);
            width: 16px;
        }

        .info-box, .warning-box {
            padding: 1.5rem;
            border-radius: 0.75rem;
            margin-bottom: 1rem;
        }

        .info-box {
            background: #f8fafc;
            border-left: 4px solid var(--primary-color);
        }

        .warning-box {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
        }

        .info-box h4, .warning-box h4 {
            color: var(--dark-color);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .warning-box h4 i {
            color: #d97706;
        }

        .info-box p, .warning-box p {
            color: #64748b;
            line-height: 1.6;
        }

        /* Mobile Menu Toggle */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--dark-color);
            cursor: pointer;
            position: absolute;
            top: 1rem;
            right: 1rem;
            z-index: 1001;
        }

        /* ============================================
           RESPONSIVE MEDIA QUERIES
        ============================================ */

        /* Large Desktop Screens (1440px and above) */
        @media (min-width: 1440px) {
            .main-content {
                margin-left: 280px;
            }
            
            .sidebar {
                width: 280px;
            }
            
            .page-content {
                padding: 2.5rem;
            }
        }

        /* Desktop Screens (1025px to 1439px) */
        @media (max-width: 1439px) and (min-width: 1025px) {
            .sidebar {
                width: 240px;
            }
            
            .main-content {
                margin-left: 240px;
            }
        }

        /* Tablet Landscape (1024px and below) */
        @media (max-width: 1024px) {
            .sidebar {
                width: 220px;
            }
            
            .main-content {
                margin-left: 220px;
            }
            
            .page-content {
                padding: 1.5rem;
            }
            
            .top-bar {
                padding: 1rem 1.5rem;
            }
            
            .stat-value {
                font-size: 2rem;
            }
        }

        /* Tablet Portrait (768px and below) */
        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                max-height: 70px;
                overflow: hidden;
                transition: max-height 0.3s ease;
            }
            
            .sidebar.active {
                max-height: 500px;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .menu-toggle {
                display: block;
            }
            
            .sidebar-menu {
                display: none;
            }
            
            .sidebar.active .sidebar-menu {
                display: block;
            }
            
            .page-content {
                padding: 1rem;
            }
            
            .page-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .page-header h1 {
                font-size: 1.5rem;
            }
            
            .patient-meta {
                grid-template-columns: 1fr;
            }
            
            /* Table responsive styles */
            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            
            th, td {
                padding: 0.75rem 1rem;
            }
        }

        /* Large Mobile (576px and below) */
        @media (max-width: 576px) {
            .top-bar {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .user-info {
                width: 100%;
                justify-content: space-between;
            }
            
            .page-header h1 {
                font-size: 1.25rem;
            }
            
            .patient-info-grid {
                grid-template-columns: 1fr;
                text-align: center;
                gap: 1rem;
            }
            
            .patient-avatar {
                margin: 0 auto;
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
            
            .patient-details h3 {
                font-size: 1.25rem;
            }
            
            .action-btns {
                flex-direction: column;
                gap: 0.25rem;
            }
            
            .btn {
                width: 32px;
                height: 32px;
            }
            
            .modal-content {
                padding: 1.5rem;
                margin: 1rem;
            }
            
            .modal-header h2 {
                font-size: 1.25rem;
            }
            
            .info-box, .warning-box {
                padding: 1rem;
            }
            
            .controls {
                padding: 1rem;
            }
            
            .search-box input {
                padding: 0.625rem 0.875rem 0.625rem 2.25rem;
                font-size: 0.9rem;
            }
        }

        /* Small Mobile (425px and below) */
        @media (max-width: 425px) {
            .page-content {
                padding: 0.75rem;
            }
            
            .stat-slide {
                padding: 0.75rem;
            }
            
            .stat-value {
                font-size: 1.5rem;
            }
            
            .stat-slide h3 {
                font-size: 0.8rem;
            }
            
            th, td {
                padding: 0.5rem 0.75rem;
                font-size: 0.8rem;
            }
            
            .page-header h1 {
                font-size: 1.1rem;
            }
            
            .page-header h1 i {
                font-size: 1.2rem;
            }
            
            .modal-content {
                padding: 1rem;
                margin: 0.5rem;
            }
            
            .patient-meta {
                gap: 0.5rem;
            }
            
            .meta-item {
                font-size: 0.8rem;
            }
        }

        /* Extra Small Mobile (320px and below) */
        @media (max-width: 320px) {
            .page-content {
                padding: 0.5rem;
            }
            
            .top-bar h1 {
                font-size: 1.1rem;
            }
            
            .user-avatar {
                width: 32px;
                height: 32px;
            }
            
            .logout-btn {
                padding: 0.375rem 0.75rem;
                font-size: 0.8rem;
            }
            
            .stat-slide {
                padding: 0.5rem;
            }
            
            .stat-value {
                font-size: 1.25rem;
            }
            
            th, td {
                padding: 0.375rem 0.5rem;
                font-size: 0.75rem;
            }
            
            .btn {
                width: 28px;
                height: 28px;
            }
        }

        /* Print Styles */
        @media print {
            .sidebar, .top-bar, .menu-toggle, .action-btns {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
            }
            
            .page-content {
                padding: 0 !important;
            }
            
            body {
                background: white !important;
                color: black !important;
            }
            
            .table-container {
                box-shadow: none !important;
                border: 1px solid #ccc !important;
            }
        }

        /* High Contrast Mode Support */
        @media (prefers-contrast: high) {
            :root {
                --primary-color: #0000ff;
                --secondary-color: #0000cc;
                --sidebar-bg: #000000;
                --sidebar-hover: #333333;
            }
            
            body {
                background: #ffffff;
                color: #000000;
            }
            
            .card-shadow {
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.8);
            }
        }

        /* Reduced Motion Support */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
            
            .slider-track {
                transition: none !important;
            }
        }

        /* Dark Mode Support */
        @media (prefers-color-scheme: dark) {
            body {
                background-color: #0f172a;
                color: #e2e8f0;
            }
            
            .main-content {
                background: #0f172a;
            }
            
            .top-bar,  .controls, .table-container, .modal-content {
                background: #1e293b;
                color: #e2e8f0;
            }
            
            .top-bar h1, .page-header h1, .patient-details h3 {
                color: #f1f5f9;
            }
            
            .search-box input {
                background: #334155;
                border-color: #475569;
                color: #e2e8f0;
            }
            
            .search-box input:focus {
                border-color: var(--primary-color);
            }
            
            table th {
                background: #334155;
                color: #e2e8f0;
            }
            
            table td {
                border-color: #475569;
                color: #cbd5e1;
            }
            
            tbody tr:hover {
                background: #334155;
            }
            
            .info-box {
                background: #334155;
                border-left-color: var(--primary-color);
            }
            
            .warning-box {
                background: #7c2d12;
                border-left-color: #ea580c;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class='sidebar'>
            <div class='sidebar-header'>
                <h2><i class='fas fa-heartbeat'></i> <?php echo htmlspecialchars($site_name); ?></h2>
                <p>Admin Panel</p>
            </div>
            <button class="menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
            </button>
            <ul class='sidebar-menu'>
                <li><a href='dashboard.php'><i class='fas fa-th-large'></i> Dashboard</a></li>
                <li><a href='manage_doctors.php'><i class='fas fa-user-md'></i> Manage Doctors</a></li>
                <li><a href='manage_patients.php' class='active'><i class='fas fa-users'></i> Manage Patients</a></li>
                <li><a href='manage_cities.php'><i class='fas fa-city'></i> Manage Cities</a></li>
                <li><a href='manage_appointments.php'><i class='fas fa-calendar-alt'></i> Appointments</a></li>
                <li><a href='manage_specializations.php'><i class='fas fa-stethoscope'></i> Specializations</a></li>
                <li><a href='manage_content.php'><i class='fas fa-newspaper'></i> Content Management</a></li>
                <li><a href='manage_users.php'><i class='fas fa-user-cog'></i> User Management</a></li>
                <li><a href='reports.php'><i class='fas fa-chart-bar'></i> Reports</a></li>
                <li><a href='settings.php'><i class='fas fa-cog'></i> Settings</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <h1>Manage Patients</h1>
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <a href="../logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <div class="page-content">
                <div class="page-header">
                    <h1><i class="fas fa-users"></i> Manage Patients (<?php echo count($patients); ?>)</h1>
                </div>

                <div class="controls">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <form method="GET">
                            <input type="text" name="search" placeholder="Search patients by name, phone, or email..."
                                value="<?php echo htmlspecialchars($search); ?>">
                        </form>
                    </div>
                </div>

                <div class="table-container">
                    <?php if (count($patients) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Patient Name</th>
                                    <th>Gender</th>
                                    <th>Phone</th>
                                    <th>Email</th>
                                    <th>City</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($patients as $patient): ?>
                                    <?php
                                    $patient_age = $patient['date_of_birth'] ? date('Y') - date('Y', strtotime($patient['date_of_birth'])) : 'N/A';
                                    ?>
                                    <tr>
                                        <td>#<?php echo $patient['patient_id']; ?></td>
                                        <td>
                                            <strong style="color: #1e293b;"><?php echo htmlspecialchars($patient['full_name']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($patient['gender'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($patient['email'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($patient['city_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo formatDate($patient['created_at']); ?></td>
                                        <td>
                                            <div class="action-btns">
                                                <button class="btn btn-view" onclick="showPatientModal('patient-<?php echo $patient['patient_id']; ?>')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <a href="?delete=1&id=<?php echo $patient['patient_id']; ?>"
                                                   class="btn btn-delete" onclick="return confirm('Are you sure you want to delete this patient? This action cannot be undone.')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="text-align: center; padding: 3rem; color: #64748b;">
                            <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <h3>No Patients Found</h3>
                            <p>No patients match your search criteria.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Patient Modals -->
    <?php foreach ($patients as $patient): ?>
        <?php
        $patient_age = $patient['date_of_birth'] ? date('Y') - date('Y', strtotime($patient['date_of_birth'])) : 'N/A';
        ?>
        <div class="modal" id="patient-<?php echo $patient['patient_id']; ?>">
            <div class="modal-content">
                <button class="modal-close" onclick="closeModal('patient-<?php echo $patient['patient_id']; ?>')">&times;</button>
                <div class="modal-header">
                    <i class="fas fa-user fa-2x" style="color: #667eea;"></i>
                    <h2>Patient Details</h2>
                </div>
                <div class="patient-info-grid">
                    <div class="patient-avatar">
                        <?php echo strtoupper(substr($patient['full_name'], 0, 1)); ?>
                    </div>
                    <div class="patient-details">
                        <h3><?php echo htmlspecialchars($patient['full_name']); ?></h3>
                        <div class="patient-meta">
                            <div class="meta-item">
                                <i class="fas fa-phone"></i>
                                <?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-envelope"></i>
                                <?php echo htmlspecialchars($patient['email'] ?? 'N/A'); ?>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-venus-mars"></i>
                                <?php echo htmlspecialchars($patient['gender'] ?? 'N/A'); ?>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-birthday-cake"></i>
                                <?php echo $patient_age; ?> years
                            </div>
                            <?php if ($patient['blood_group']): ?>
                                <div class="meta-item">
                                    <i class="fas fa-tint"></i>
                                    <?php echo htmlspecialchars($patient['blood_group']); ?>
                                </div>
                            <?php endif; ?>
                            <div class="meta-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($patient['city_name'] ?? 'N/A'); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="info-box">
                    <h4><i class="fas fa-map-marker-alt"></i> Address</h4>
                    <p><?php echo nl2br(htmlspecialchars($patient['address'] ?? 'N/A')); ?></p>
                </div>
                <?php if ($patient['allergies']): ?>
                    <div class="warning-box">
                        <h4><i class="fas fa-exclamation-triangle"></i> Allergies</h4>
                        <p><?php echo nl2br(htmlspecialchars($patient['allergies'])); ?></p>
                    </div>
                <?php endif; ?>
                <?php if ($patient['medical_history']): ?>
                    <div class="info-box">
                        <h4><i class="fas fa-history"></i> Medical History</h4>
                        <p><?php echo nl2br(htmlspecialchars($patient['medical_history'])); ?></p>
                    </div>
                <?php endif; ?>
                <div class="info-box">
                    <h4><i class="fas fa-user-check"></i> Account Status</h4>
                    <p><?php echo ucfirst(htmlspecialchars($patient['user_status'] ?? 'N/A')); ?></p>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script>
       
        // Modal functions
        function showPatientModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        // Handle window resize
        window.addEventListener('resize', function() {
            // Recalculate slider position on resize
            updateSlider();
        });

        // Handle touch events for mobile swiping
        let touchStartX = 0;
        let touchEndX = 0;

        sliderTrack.addEventListener('touchstart', e => {
            touchStartX = e.changedTouches[0].screenX;
        });

        sliderTrack.addEventListener('touchend', e => {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        });

        function handleSwipe() {
            const swipeThreshold = 50;
            
            if (touchEndX < touchStartX - swipeThreshold) {
                // Swipe left - next slide
                currentSlide = (currentSlide + 1) % slideCount;
                updateSlider();
            }
            
            if (touchEndX > touchStartX + swipeThreshold) {
                // Swipe right - previous slide
                currentSlide = (currentSlide - 1 + slideCount) % slideCount;
                updateSlider();
            }
        }
    </script>
</body>
</html>