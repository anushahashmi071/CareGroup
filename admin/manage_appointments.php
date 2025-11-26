<?php
// admin/manage_appointments.php - View All Appointments
require_once '../config.php';
requireRole('admin');

// site name
$site_name = getSetting('site_name', 'CARE Group Medical Services');

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

$conn = getDBConnection();

$where_clause = "1=1";
if ($filter == 'scheduled') {
    $where_clause = "a.status = 'scheduled'";
} elseif ($filter == 'completed') {
    $where_clause = "a.status = 'completed'";
} elseif ($filter == 'cancelled') {
    $where_clause = "a.status = 'cancelled'";
} elseif ($filter == 'missed') {
    $where_clause = "a.status = 'missed'";
}

$query = "
    SELECT a.*, p.full_name as patient_name, d.full_name as doctor_name, 
           s.specialization_name, c.city_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    JOIN doctors d ON a.doctor_id = d.doctor_id
    JOIN specializations s ON d.specialization_id = s.specialization_id
    JOIN cities c ON d.city_id = c.city_id
    WHERE $where_clause
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
";

$appointments = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments - Admin Panel</title>
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

        /* Filter Tabs */
        .filter-tabs {
            display: flex;
            background: white;
            border-radius: 0.75rem;
            box-shadow: var(--card-shadow);
            padding: 0.5rem;
            margin-bottom: 2rem;
            overflow-x: auto;
        }

        .filter-tab {
            padding: 0.75rem 1.5rem;
            text-decoration: none;
            color: #64748b;
            font-weight: 500;
            border-radius: 0.5rem;
            transition: var(--transition);
            white-space: nowrap;
            flex: 1;
            text-align: center;
        }

        .filter-tab:hover {
            background: #f8fafc;
            color: var(--primary-color);
        }

        .filter-tab.active {
            background: var(--primary-color);
            color: white;
            box-shadow: 0 2px 8px rgba(67, 97, 238, 0.3);
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

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-scheduled {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #b91c1c;
        }

        .btn-view {
            background: #dbeafe;
            color: #1d4ed8;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
            font-size: 0.875rem;
        }

        .btn-view:hover {
            background: #bfdbfe;
            transform: translateY(-2px);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #64748b;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #cbd5e1;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: #475569;
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
            
            .filter-tabs {
                flex-wrap: wrap;
                gap: 0.5rem;
            }
            
            .filter-tab {
                flex: 1 0 calc(50% - 0.5rem);
                min-width: 120px;
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
            
            .filter-tabs {
                flex-direction: column;
            }
            
            .filter-tab {
                flex: 1;
            }
            
            .btn-view {
                padding: 0.375rem 0.75rem;
                font-size: 0.8rem;
            }
        }

        /* Small Mobile (425px and below) */
        @media (max-width: 425px) {
            .page-content {
                padding: 0.75rem;
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
            
            th, td {
                padding: 0.375rem 0.5rem;
                font-size: 0.75rem;
            }
        }

        /* Print Styles */
        @media print {
            .sidebar, .top-bar, .menu-toggle, .filter-tabs, .btn-view {
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
            
            .top-bar, .filter-tabs, .table-container {
                background: #1e293b;
                color: #e2e8f0;
            }
            
            .top-bar h1, .page-header h1 {
                color: #f1f5f9;
            }
            
            .filter-tab {
                color: #cbd5e1;
            }
            
            .filter-tab:hover {
                background: #334155;
                color: var(--primary-color);
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
                <li><a href='manage_patients.php'><i class='fas fa-users'></i> Manage Patients</a></li>
                <li><a href='manage_cities.php'><i class='fas fa-city'></i> Manage Cities</a></li>
                <li><a href='manage_appointments.php' class='active'><i class='fas fa-calendar-alt'></i> Appointments</a></li>
                <li><a href='manage_specializations.php'><i class='fas fa-stethoscope'></i> Specializations</a></li>
                <li><a href='manage_content.php'><i class='fas fa-newspaper'></i> Content Management</a></li>
                <li><a href='manage_users.php'><i class='fas fa-user-cog'></i> User Management</a></li>
                <li><a href='reports.php'><i class='fas fa-chart-bar'></i> Reports</a></li>
                <li><a href='settings.php'><i class='fas fa-cog'></i> Settings</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <h1>Manage Appointments</h1>
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <span><?php echo $_SESSION['username']; ?></span>
                    <a href="../logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <div class="page-content">
                <div class="page-header">
                    <h1><i class="fas fa-calendar-alt"></i> Manage Appointments (<?php echo count($appointments); ?>)</h1>
                </div>

                <!-- Filter Tabs -->
                <div class="filter-tabs">
                    <a href="?filter=all" class="filter-tab <?php echo $filter == 'all' ? 'active' : ''; ?>">
                        <i class="fas fa-list"></i> All Appointments
                    </a>
                    <a href="?filter=scheduled" class="filter-tab <?php echo $filter == 'scheduled' ? 'active' : ''; ?>">
                        <i class="fas fa-clock"></i> Scheduled
                    </a>
                    <a href="?filter=completed" class="filter-tab <?php echo $filter == 'completed' ? 'active' : ''; ?>">
                        <i class="fas fa-check-circle"></i> Completed
                    </a>
                    <a href="?filter=cancelled" class="filter-tab <?php echo $filter == 'cancelled' ? 'active' : ''; ?>">
                        <i class="fas fa-times-circle"></i> Cancelled
                    </a>
                     <a href="?filter=missed" class="filter-tab <?php echo $filter == 'missed' ? 'active' : ''; ?>">  <!-- NEW -->
                        <i class="fas fa-exclamation-triangle"></i> Missed
                    </a>
                </div>

                <!-- Appointments Table -->
                <div class="table-container">
                    <?php if (count($appointments) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Patient</th>
                                    <th>Doctor</th>
                                    <th>Specialization</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>City</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appointments as $apt): ?>
                                    <tr>
                                        <td>#<?php echo $apt['appointment_id']; ?></td>
                                        <td>
                                            <strong><?php echo $apt['patient_name']; ?></strong>
                                        </td>
                                        <td>Dr. <?php echo $apt['doctor_name']; ?></td>
                                        <td><?php echo $apt['specialization_name']; ?></td>
                                        <td><?php echo formatDate($apt['appointment_date']); ?></td>
                                        <td><?php echo formatTime($apt['appointment_time']); ?></td>
                                        <td><?php echo $apt['city_name']; ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $apt['status']; ?>">
                                                <?php echo ucfirst($apt['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="view_appointment.php?id=<?php echo $apt['appointment_id']; ?>"
                                                class="btn-view">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h3>No Appointments Found</h3>
                            <p>
                                <?php if ($filter != 'all'): ?>
                                    No <?php echo $filter; ?> appointments found. 
                                    <a href="?filter=all" style="color: var(--primary-color);">View all appointments</a>
                                <?php else: ?>
                                    No appointments have been scheduled yet.
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Mobile menu toggle
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Add loading state to filter tabs
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', function(e) {
                if (!this.classList.contains('active')) {
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                }
            });
        });
    </script>
</body>
</html>