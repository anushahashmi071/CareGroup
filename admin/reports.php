<?php
// admin/reports.php - System Reports & Analytics
require_once '../config.php';
requireRole('admin');

// site name
$site_name = getSetting('site_name', 'CARE Group Medical Services');

$conn = getDBConnection();

// Get statistics
$total_doctors = getTotalDoctors();
$total_patients = getTotalPatients();
$total_appointments = getTotalAppointments();
$total_cities = getTotalCities();

// Monthly appointments
$monthly_query = "
    SELECT DATE_FORMAT(appointment_date, '%Y-%m') as month, COUNT(*) as count
    FROM appointments
    GROUP BY DATE_FORMAT(appointment_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
";
$monthly_appointments = $conn->query($monthly_query)->fetch_all(MYSQLI_ASSOC);

// Top doctors by appointments
$top_doctors_query = "
    SELECT d.full_name, s.specialization_name, COUNT(a.appointment_id) as total_appointments
    FROM doctors d
    LEFT JOIN appointments a ON d.doctor_id = a.doctor_id
    JOIN specializations s ON d.specialization_id = s.specialization_id
    GROUP BY d.doctor_id
    ORDER BY total_appointments DESC
    LIMIT 10
";
$top_doctors = $conn->query($top_doctors_query)->fetch_all(MYSQLI_ASSOC);

// Appointments by specialization
$spec_query = "
    SELECT s.specialization_name, COUNT(a.appointment_id) as count
    FROM specializations s
    LEFT JOIN doctors d ON s.specialization_id = d.specialization_id
    LEFT JOIN appointments a ON d.doctor_id = a.doctor_id
    GROUP BY s.specialization_id
    ORDER BY count DESC
";
$spec_stats = $conn->query($spec_query)->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Admin Panel</title>
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

        /* Content Container */
        .container {
            padding: 2rem;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 2.5rem;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-header p {
            opacity: 0.9;
            margin-top: 0.5rem;
            font-size: 1rem;
        }

        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            font-size: 0.875rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--secondary-color);
            color: white;
        }

        .btn-light {
            background: white;
            color: var(--primary-color);
        }

        /* Statistics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-info h3 {
            font-size: 0.875rem;
            color: #64748b;
            margin-bottom: 0.5rem;
        }

        .stat-info p {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .stat-icon.blue {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        }

        .stat-icon.green {
            background: linear-gradient(135deg, #10b981 0%, #047857 100%);
        }

        .stat-icon.orange {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .stat-icon.purple {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        }

        /* Report Sections */
        .content-section {
            background: white;
            border-radius: 0.75rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .section-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Chart Styles */
        .chart-container {
            padding: 2rem;
        }

        .bar-chart {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .bar-item {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .bar-label {
            min-width: 150px;
            color: #1e293b;
            font-weight: 500;
        }

        .bar-wrapper {
            flex: 1;
            background: #f1f5f9;
            border-radius: 10px;
            overflow: hidden;
            height: 30px;
            position: relative;
        }

        .bar-fill {
            height: 100%;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border-radius: 10px;
            transition: width 0.5s ease;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 0.75rem;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
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

        .rank-badge {
            width: 30px;
            height: 30px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.875rem;
        }

        /* System Health Grid */
        .health-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            padding: 2rem;
        }

        .health-item {
            text-align: center;
            padding: 1.5rem;
            background: #f8fafc;
            border-radius: 10px;
            border-left: 4px solid var(--primary-color);
        }

        .health-item h3 {
            color: #64748b;
            margin-bottom: 1rem;
            font-size: 1rem;
        }

        .health-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .health-label {
            color: #64748b;
            font-size: 0.875rem;
        }

        /* Mobile Menu Toggle */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: white;
            cursor: pointer;
            padding: 1rem;
        }

        /* Responsive Styles */
        @media (max-width: 1024px) {
            .sidebar {
                width: 220px;
            }

            .main-content {
                margin-left: 220px;
            }
        }

        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
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

            .container {
                padding: 1rem;
            }

            .page-header {
                padding: 1.5rem;
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .health-grid {
                grid-template-columns: 1fr;
                padding: 1rem;
            }

            .section-header {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .chart-container {
                padding: 1rem;
            }
        }

        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

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

            table {
                display: block;
                overflow-x: auto;
            }
        }

        @media print {
            .sidebar,
            .top-bar,
            .btn {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
            }

            .container {
                padding: 0 !important;
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
                <li><a href='manage_appointments.php'><i class='fas fa-calendar-alt'></i> Appointments</a></li>
                <li><a href='manage_specializations.php'><i class='fas fa-stethoscope'></i> Specializations</a></li>
                <li><a href='manage_content.php'><i class='fas fa-newspaper'></i> Content Management</a></li>
                <li><a href='manage_users.php'><i class='fas fa-user-cog'></i> User Management</a></li>
                <li><a href='reports.php' class='active'><i class='fas fa-chart-bar'></i> Reports</a></li>
                <li><a href='settings.php'><i class='fas fa-cog'></i> Settings</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <h1>Reports & Analytics</h1>
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

            <div class="container">
                <div class="page-header">
                    <div>
                        <h1><i class="fas fa-chart-line"></i> Reports & Analytics</h1>
                        <p>Comprehensive system insights and statistics</p>
                    </div>
                    <button class="btn btn-light" onclick="window.print()">
                        <i class="fas fa-download"></i> Download Report
                    </button>
                </div>

                <!-- Overall Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3>Total Doctors</h3>
                            <p><?php echo $total_doctors; ?></p>
                        </div>
                        <div class="stat-icon blue">
                            <i class="fas fa-user-md"></i>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-info">
                            <h3>Total Patients</h3>
                            <p><?php echo $total_patients; ?></p>
                        </div>
                        <div class="stat-icon green">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-info">
                            <h3>Total Appointments</h3>
                            <p><?php echo $total_appointments; ?></p>
                        </div>
                        <div class="stat-icon orange">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-info">
                            <h3>Cities Covered</h3>
                            <p><?php echo $total_cities; ?></p>
                        </div>
                        <div class="stat-icon purple">
                            <i class="fas fa-city"></i>
                        </div>
                    </div>
                </div>

                <!-- Monthly Appointments Trend -->
                <div class="content-section">
                    <div class="section-header">
                        <h2><i class="fas fa-chart-bar"></i> Monthly Appointments Trend</h2>
                    </div>
                    <div class="chart-container">
                        <div class="bar-chart">
                            <?php
                            $max_count = max(array_column($monthly_appointments, 'count'));
                            foreach ($monthly_appointments as $month):
                                $percentage = ($month['count'] / $max_count) * 100;
                                ?>
                                <div class="bar-item">
                                    <div class="bar-label"><?php echo date('M Y', strtotime($month['month'] . '-01')); ?></div>
                                    <div class="bar-wrapper">
                                        <div class="bar-fill" style="width: <?php echo $percentage; ?>%">
                                            <?php echo $month['count']; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Top Doctors by Appointments -->
                <div class="content-section">
                    <div class="section-header">
                        <h2><i class="fas fa-trophy"></i> Top 10 Doctors by Appointments</h2>
                    </div>
                    <div class="chart-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Doctor Name</th>
                                    <th>Specialization</th>
                                    <th>Total Appointments</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_doctors as $index => $doctor): ?>
                                    <tr>
                                        <td>
                                            <div class="rank-badge"><?php echo $index + 1; ?></div>
                                        </td>
                                        <td><strong>Dr. <?php echo $doctor['full_name']; ?></strong></td>
                                        <td><?php echo $doctor['specialization_name']; ?></td>
                                        <td><strong><?php echo $doctor['total_appointments']; ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Appointments by Specialization -->
                <div class="content-section">
                    <div class="section-header">
                        <h2><i class="fas fa-stethoscope"></i> Appointments by Specialization</h2>
                    </div>
                    <div class="chart-container">
                        <div class="bar-chart">
                            <?php
                            $max_count = max(array_column($spec_stats, 'count'));
                            foreach ($spec_stats as $spec):
                                $percentage = $spec['count'] > 0 ? ($spec['count'] / $max_count) * 100 : 0;
                                ?>
                                <div class="bar-item">
                                    <div class="bar-label"><?php echo $spec['specialization_name']; ?></div>
                                    <div class="bar-wrapper">
                                        <div class="bar-fill" style="width: <?php echo $percentage; ?>%">
                                            <?php echo $spec['count']; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- System Health Indicators -->
                <div class="content-section">
                    <div class="section-header">
                        <h2><i class="fas fa-heartbeat"></i> System Health Indicators</h2>
                    </div>
                    <div class="health-grid">
                        <div class="health-item">
                            <h3>Doctor Utilization</h3>
                            <div class="health-value">
                                <?php echo $total_doctors > 0 ? round(($total_appointments / $total_doctors), 1) : 0; ?>
                            </div>
                            <p class="health-label">Avg. appointments per doctor</p>
                        </div>

                        <div class="health-item">
                            <h3>Patient Engagement</h3>
                            <div class="health-value">
                                <?php echo $total_patients > 0 ? round(($total_appointments / $total_patients), 1) : 0; ?>
                            </div>
                            <p class="health-label">Avg. appointments per patient</p>
                        </div>

                        <div class="health-item">
                            <h3>City Coverage</h3>
                            <div class="health-value">
                                <?php echo $total_cities; ?>
                            </div>
                            <p class="health-label">Active cities</p>
                        </div>

                        <div class="health-item">
                            <h3>Platform Growth</h3>
                            <div class="health-value">
                                <?php echo count($monthly_appointments) > 1 ?
                                    round((($monthly_appointments[0]['count'] - $monthly_appointments[1]['count']) / $monthly_appointments[1]['count']) * 100, 1) : 0; ?>%
                            </div>
                            <p class="health-label">Month-over-month growth</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Mobile menu toggle
        document.getElementById('menuToggle').addEventListener('click', function () {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Animate bars on load
        window.addEventListener('load', function () {
            const bars = document.querySelectorAll('.bar-fill');
            bars.forEach((bar, index) => {
                const originalWidth = bar.style.width;
                bar.style.width = '0';
                setTimeout(() => {
                    bar.style.width = originalWidth;
                }, index * 100);
            });
        });
    </script>
</body>
</html>