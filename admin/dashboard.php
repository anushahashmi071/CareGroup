<?php
// admin/dashboard.php
require_once '../config.php';
requireRole('admin');

// site name
$site_name = getSetting('site_name', 'CARE Group Medical Services');

// if (!isset($_GET['id'])) {
//     $_SESSION['error'] = 'No appointment ID provided.';
//     header("Location: manage_appointments.php");
//     exit();
// }

// $appointment_id = intval($_GET['id']);
$conn = getDBConnection();

$stmt = $conn->prepare("
    SELECT a.*, 
           p.full_name as patient_name, 
           p.phone, 
           p.email as patient_email,
           p.gender, 
           p.date_of_birth,
           p.blood_group,
           p.address,
           p.allergies,
           p.medical_history,
           d.full_name as doctor_name,
           s.specialization_name,
           c.city_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    JOIN doctors d ON a.doctor_id = d.doctor_id
    JOIN specializations s ON d.specialization_id = s.specialization_id
    JOIN cities c ON p.city_id = c.city_id
    WHERE a.appointment_id = ?
");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$result = $stmt->get_result();

// if ($result->num_rows === 0) {
//     $_SESSION['error'] = 'Appointment not found.';
//     header("Location: manage_appointments.php");
//     exit();
// }


$total_doctors = getTotalDoctors();
$total_patients = getTotalPatients();
$total_appointments = getTotalAppointments();
$total_cities = getTotalCities();

// Get recent appointments
$conn = getDBConnection();
$recent_appointments = $conn->query("
    SELECT a.*, p.full_name as patient_name, d.full_name as doctor_name, s.specialization_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    JOIN doctors d ON a.doctor_id = d.doctor_id
    JOIN specializations s ON d.specialization_id = s.specialization_id
    ORDER BY a.created_at DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - CARE Group</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
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

        /* Statistics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            padding: 2rem;
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

        /* Content Sections */
        .content-section {
            background: white;
            border-radius: 0.75rem;
            box-shadow: var(--card-shadow);
            margin: 0 2rem 2rem;
            overflow: hidden;
        }

        .content-section h2 {
            padding: 1.5rem 2rem 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 2rem 0;
        }

        .section-header h2 {
            padding: 0;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            text-decoration: none;
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .btn-primary:hover {
            background: var(--secondary-color);
            color: white;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            padding: 1.5rem 2rem 2rem;
        }

        .quick-action-card {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            text-align: center;
            text-decoration: none;
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 120px;
        }

        .quick-action-card:hover {
            transform: translateY(-5px);
            color: white;
        }

        .quick-action-card i {
            font-size: 2rem;
            margin-bottom: 0.75rem;
        }

        .quick-action-card h3 {
            font-size: 1rem;
            font-weight: 500;
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

        .status-pending {
            background: #fef3c7;
            color: #d97706;
        }

        .status-confirmed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-completed {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #b91c1c;
        }

        .action-btns {
            display: flex;
            gap: 0.5rem;
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
            text-decoration: none;
            font-size: 0.75rem;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .btn-view {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .btn-view:hover {
            background: #bfdbfe;
        }

        .btn-edit {
            background: #fef3c7;
            color: #d97706;
        }

        .btn-edit:hover {
            background: #fde68a;
        }

        /* Mobile Menu Toggle */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--dark-color);
            cursor: pointer;
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

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                padding: 1rem;
            }

            .content-section {
                margin: 0 1rem 1rem;
            }

            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
                padding: 1rem;
            }

            table {
                display: block;
                overflow-x: auto;
            }
        }

        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .quick-actions {
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
                <li><a href='dashboard.php' class='active'><i class='fas fa-th-large'></i> Dashboard</a></li>
                <li><a href='manage_doctors.php'><i class='fas fa-user-md'></i> Manage Doctors</a></li>
                <li><a href='manage_patients.php'><i class='fas fa-users'></i> Manage Patients</a></li>
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
                <h1>Admin Dashboard</h1>
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

            <!-- Statistics -->
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

            <!-- Quick Actions -->
            <div class="content-section">
                <h2>Quick Actions</h2>
                <div class="quick-actions">
                    <a href="add_doctor.php" class="quick-action-card">
                        <i class="fas fa-user-md"></i>
                        <h3>Add Doctor</h3>
                    </a>
                    <a href="add_city.php" class="quick-action-card">
                        <i class="fas fa-city"></i>
                        <h3>Add City</h3>
                    </a>
                    <a href="manage_appointments.php" class="quick-action-card">
                        <i class="fas fa-calendar-alt"></i>
                        <h3>View Appointments</h3>
                    </a>
                    <a href="reports.php" class="quick-action-card">
                        <i class="fas fa-chart-bar"></i>
                        <h3>Generate Report</h3>
                    </a>
                </div>
            </div>

            <!-- Recent Appointments -->
            <div class="content-section">
                <div class="section-header">
                    <h2>Recent Appointments</h2>
                    <a href="manage_appointments.php" class="btn-primary">View All</a>
                </div>

                <?php if (count($recent_appointments) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Specialization</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_appointments as $apt): ?>
                                <tr>
                                    <td>#<?php echo $apt['appointment_id']; ?></td>
                                    <td><?php echo $apt['patient_name']; ?></td>
                                    <td><?php echo $apt['doctor_name']; ?></td>
                                    <td><?php echo $apt['specialization_name']; ?></td>
                                    <td><?php echo formatDate($apt['appointment_date']); ?></td>
                                    <td><?php echo formatTime($apt['appointment_time']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $apt['status']; ?>">
                                            <?php echo ucfirst($apt['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-btns">
                                            <a href="view_appointment.php?id=<?php echo $apt['appointment_id']; ?>"
                                                class="btn-sm btn-view" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_appointment.php?id=<?php echo $apt['appointment_id']; ?>"
                                                class="btn-sm btn-edit" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align: center; color: #64748b; padding: 2rem;">No appointments found</p>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Mobile menu toggle
        document.getElementById('menuToggle').addEventListener('click', function () {
            document.querySelector('.sidebar').classList.toggle('active');
        });
    </script>
</body>

</html>