<?php
// doctor/availability.php - Manage Doctor Availability
// session_start();
require_once '../config.php';
requireRole('doctor');

$doctor = getDoctorByUserId($_SESSION['user_id']);
if (!$doctor) {
    die("Doctor profile not found.");
}

$conn = getDBConnection();
$message = '';

// Create doctor_availability table if it doesn't exist
$create_table = "
    CREATE TABLE IF NOT EXISTS doctor_availability (
        availability_id INT PRIMARY KEY AUTO_INCREMENT,
        doctor_id INT NOT NULL,
        availability_type ENUM('daily', 'weekly', 'monthly') NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        max_patients INT DEFAULT 5,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id) ON DELETE CASCADE
    )
";
if ($conn->query($create_table) === TRUE) {
    // Success
} else {
    die("Failed to create table: " . $conn->error);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_availability'])) {
        $availability_type = $_POST['availability_type'];
        $start_date = $_POST['start_date'];
        $end_date = $availability_type === 'daily' ? $start_date : $_POST['end_date'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $max_patients = intval($_POST['max_patients']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // Validate dates based on type
        if ($availability_type === 'weekly' && empty($end_date)) {
            $end_date = date('Y-m-d', strtotime($start_date . ' +6 days'));
        } elseif ($availability_type === 'monthly' && empty($end_date)) {
            $end_date = date('Y-m-d', strtotime($start_date . ' +1 month -1 day'));
        }

        $stmt = $conn->prepare("INSERT INTO doctor_availability (doctor_id, availability_type, start_date, end_date, start_time, end_time, max_patients, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssii", $doctor['doctor_id'], $availability_type, $start_date, $end_date, $start_time, $end_time, $max_patients, $is_active);

        if ($stmt->execute()) {
            $message = '<div class="alert alert-success">Availability added successfully!</div>';
        } else {
            $message = '<div class="alert alert-danger">Error adding availability: ' . $stmt->error . '</div>';
        }
        $stmt->close();
    } elseif (isset($_POST['update_availability'])) {
        $availability_id = intval($_POST['availability_id']);
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $max_patients = intval($_POST['max_patients']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $conn->prepare("UPDATE doctor_availability SET start_time = ?, end_time = ?, max_patients = ?, is_active = ? WHERE availability_id = ? AND doctor_id = ?");
        $stmt->bind_param("ssiiii", $start_time, $end_time, $max_patients, $is_active, $availability_id, $doctor['doctor_id']);

        if ($stmt->execute()) {
            $message = '<div class="alert alert-success">Availability updated successfully!</div>';
        } else {
            $message = '<div class="alert alert-danger">Error updating availability: ' . $stmt->error . '</div>';
        }
        $stmt->close();
    } elseif (isset($_POST['delete_availability'])) {
        $availability_id = intval($_POST['availability_id']);

        $stmt = $conn->prepare("DELETE FROM doctor_availability WHERE availability_id = ? AND doctor_id = ?");
        $stmt->bind_param("ii", $availability_id, $doctor['doctor_id']);

        if ($stmt->execute()) {
            $message = '<div class="alert alert-success">Availability deleted successfully!</div>';
        } else {
            $message = '<div class="alert alert-danger">Error deleting availability: ' . $stmt->error . '</div>';
        }
        $stmt->close();
    }
}

// Get current availability - SIMPLE QUERY WITHOUT COMPLEX JOINS
$availability_query = "
    SELECT * FROM doctor_availability 
    WHERE doctor_id = ? 
    ORDER BY start_date, start_time
";
$stmt = $conn->prepare($availability_query);
$stmt->bind_param("i", $doctor['doctor_id']);
$stmt->execute();
$availability_slots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get stats - SIMPLE SEPARATE QUERIES
// Total slots
$stats_query1 = "SELECT COUNT(*) as total_slots FROM doctor_availability WHERE doctor_id = ? AND is_active = 1";
$stmt = $conn->prepare($stats_query1);
$stmt->bind_param("i", $doctor['doctor_id']);
$stmt->execute();
$total_slots = $stmt->get_result()->fetch_assoc()['total_slots'];
$stmt->close();

// Total days
$stats_query2 = "SELECT COUNT(DISTINCT DATE(start_date)) as total_days FROM doctor_availability WHERE doctor_id = ? AND is_active = 1";
$stmt = $conn->prepare($stats_query2);
$stmt->bind_param("i", $doctor['doctor_id']);
$stmt->execute();
$total_days = $stmt->get_result()->fetch_assoc()['total_days'];
$stmt->close();

// Total capacity
$stats_query3 = "SELECT SUM(max_patients) as total_capacity FROM doctor_availability WHERE doctor_id = ? AND is_active = 1";
$stmt = $conn->prepare($stats_query3);
$stmt->bind_param("i", $doctor['doctor_id']);
$stmt->execute();
$total_capacity_result = $stmt->get_result()->fetch_assoc();
$total_capacity = $total_capacity_result['total_capacity'] ?? 0;
$stmt->close();

// Total booked appointments
$stats_query4 = "SELECT COUNT(*) as total_booked FROM appointments WHERE doctor_id = ? AND status IN ('scheduled', 'completed') AND appointment_date >= CURDATE()";
$stmt = $conn->prepare($stats_query4);
$stmt->bind_param("i", $doctor['doctor_id']);
$stmt->execute();
$total_booked = $stmt->get_result()->fetch_assoc()['total_booked'];
$stmt->close();

$conn->close();

// Helper function to format availability type
function formatAvailabilityType($type)
{
    $types = [
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'monthly' => 'Monthly'
    ];
    return $types[$type] ?? $type;
}

// Helper function to format date range
function formatDateRange($start_date, $end_date, $type)
{
    if ($type === 'daily' || $start_date === $end_date) {
        return date('M j, Y', strtotime($start_date));
    }
    return date('M j, Y', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Availability - Dr. <?= htmlspecialchars($doctor['full_name']) ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            --gradient: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%);
            --medical-gradient: linear-gradient(135deg, #1a73e8 0%, #43a047 100%);
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
            color: white;
            padding: 2rem 0;
            width: 280px;
            position: fixed;
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

        .btn-success {
            background: var(--secondary);
            color: white;
        }

        .btn-success:hover {
            background: var(--secondary-dark);
            transform: translateY(-2px);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #d32f2f;
            transform: translateY(-2px);
        }

        .btn-outline {
            background: white;
            border: 2px solid var(--light-gray);
            color: var(--gray);
        }

        .btn-outline:hover {
            background: var(--light);
            border-color: var(--primary);
            color: var(--primary);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark);
            font-weight: 600;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--light-gray);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.1);
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-check-input {
            width: 18px;
            height: 18px;
        }

        .availability-card {
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

        .availability-card:hover {
            box-shadow: var(--hover-shadow);
            transform: translateY(-5px);
        }

        .availability-card.inactive {
            opacity: 0.7;
            background: #f9f9f9;
        }

        .availability-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, var(--primary) 0%, var(--primary-light) 100%);
        }

        .availability-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .availability-type {
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .type-daily {
            background: #e3f2fd;
            color: #1565c0;
            border: 1px solid #bbdefb;
        }

        .type-weekly {
            background: #fff3e0;
            color: #e65100;
            border: 1px solid #ffe0b2;
        }

        .type-monthly {
            background: #e8f5e8;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        .availability-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 0.875rem;
            color: var(--gray);
            margin-bottom: 0.25rem;
        }

        .detail-value {
            font-weight: 600;
            color: var(--dark);
        }

        .availability-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
        }

        .badge {
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-success {
            background: #e8f5e8;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        .badge-warning {
            background: #fff3e0;
            color: #e65100;
            border: 1px solid #ffe0b2;
        }

        .badge-danger {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            text-align: center;
            border-left: 4px solid var(--primary);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--hover-shadow);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--gray);
            font-weight: 500;
        }

        .availability-form {
            display: none;
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 1rem;
            border: 1px solid var(--light-gray);
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

            .availability-header {
                flex-direction: column;
                gap: 1rem;
            }

            .availability-actions {
                width: 100%;
                justify-content: flex-start;
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
                <li><a href="availability.php" class="active"><i class="fas fa-clock"></i> Availability</a></li>
                <li><a href="patients.php"><i class="fas fa-users"></i> My Patients</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="reviews.php"><i class="fas fa-star"></i> Reviews</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="welcome-text">
                    <h1>Manage Availability</h1>
                    <p>Set your availability for days, weeks, or months</p>
                </div>
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>

            <?= $message ?>

            <!-- Stats Overview -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $total_slots ?></div>
                    <div class="stat-label">Total Slots</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $total_days ?></div>
                    <div class="stat-label">Days Covered</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $total_capacity ?></div>
                    <div class="stat-label">Total Capacity</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $total_booked ?></div>
                    <div class="stat-label">Appointments Booked</div>
                </div>
            </div>

            <!-- Add New Availability -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-plus-circle"></i> Add New Availability</h2>
                </div>

                <form method="POST" id="availabilityForm">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">Availability Type</label>
                                <select name="availability_type" class="form-control" required id="availabilityType">
                                    <option value="">Select Type</option>
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">Start Date</label>
                                <input type="date" name="start_date" class="form-control" required
                                    min="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                        <div class="col-md-3" id="endDateField" style="display: none;">
                            <div class="form-group">
                                <label class="form-label">End Date</label>
                                <input type="date" name="end_date" class="form-control" id="endDateInput">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">Start Time</label>
                                <input type="time" name="start_time" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">End Time</label>
                                <input type="time" name="end_time" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">Max Patients</label>
                                <input type="number" name="max_patients" class="form-control" min="1" max="20" value="5"
                                    required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">&nbsp;</label>
                                <div class="form-check" style="margin-top: 0.5rem;">
                                    <input type="checkbox" name="is_active" class="form-check-input" id="is_active"
                                        checked>
                                    <label class="form-check-label" for="is_active">Active</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" name="add_availability" class="btn btn-primary w-100">
                                    <i class="fas fa-plus"></i> Add Availability
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Current Availability -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-calendar-check"></i> Current Availability</h2>
                </div>

                <?php if (empty($availability_slots)): ?>
                    <div class="empty-state">
                        <i class="fas fa-clock"></i>
                        <h3>No Availability Set</h3>
                        <p>Add your first availability slot to start accepting appointments.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($availability_slots as $slot): ?>
                        <div class="availability-card <?= !$slot['is_active'] ? 'inactive' : '' ?>"
                            id="slot-<?= $slot['availability_id'] ?>">
                            <div class="availability-header">
                                <div>
                                    <h4 class="mb-1">
                                        <?= formatDateRange($slot['start_date'], $slot['end_date'], $slot['availability_type']) ?>
                                    </h4>
                                    <span class="availability-type type-<?= $slot['availability_type'] ?>">
                                        <?= formatAvailabilityType($slot['availability_type']) ?>
                                    </span>
                                    <?php if (!$slot['is_active']): ?>
                                        <span class="badge badge-warning ms-2">Inactive</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-end">
                                    <div class="detail-value">
                                        <?= date('g:i A', strtotime($slot['start_time'])) ?> -
                                        <?= date('g:i A', strtotime($slot['end_time'])) ?>
                                    </div>
                                    <div class="detail-label">
                                        Max Patients: <?= $slot['max_patients'] ?>
                                    </div>
                                </div>
                            </div>

                            <div class="availability-details">
                                <div class="detail-item">
                                    <span class="detail-label">Time Slot</span>
                                    <span class="detail-value"><?= date('g:i A', strtotime($slot['start_time'])) ?> -
                                        <?= date('g:i A', strtotime($slot['end_time'])) ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Duration</span>
                                    <span class="detail-value">
                                        <?php
                                        $start = strtotime($slot['start_time']);
                                        $end = strtotime($slot['end_time']);
                                        $hours = round(($end - $start) / 3600, 1);
                                        echo $hours . ' hours';
                                        ?>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Capacity</span>
                                    <span class="detail-value">
                                        <?= $slot['max_patients'] ?> patients max
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Status</span>
                                    <span class="detail-value">
                                        <?php if ($slot['is_active']): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Inactive</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Edit Form -->
                            <div class="availability-form" id="edit-form-<?= $slot['availability_id'] ?>">
                                <form method="POST" class="row g-3">
                                    <input type="hidden" name="availability_id" value="<?= $slot['availability_id'] ?>">
                                    <div class="col-md-3">
                                        <input type="time" name="start_time"
                                            value="<?= date('H:i', strtotime($slot['start_time'])) ?>" class="form-control"
                                            required>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="time" name="end_time"
                                            value="<?= date('H:i', strtotime($slot['end_time'])) ?>" class="form-control"
                                            required>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" name="max_patients" value="<?= $slot['max_patients'] ?>"
                                            class="form-control" min="1" max="20" required>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-check">
                                            <input type="checkbox" name="is_active" class="form-check-input"
                                                <?= $slot['is_active'] ? 'checked' : '' ?>>
                                            <label class="form-check-label">Active</label>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" name="update_availability" class="btn btn-success btn-sm w-100">
                                            <i class="fas fa-check"></i> Save
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <div class="availability-actions">
                                <button type="button" class="btn btn-outline btn-sm"
                                    onclick="toggleEdit(<?= $slot['availability_id'] ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <form method="POST" class="d-inline"
                                    onsubmit="return confirm('Are you sure you want to delete this availability?')">
                                    <input type="hidden" name="availability_id" value="<?= $slot['availability_id'] ?>">
                                    <button type="submit" name="delete_availability" class="btn btn-danger btn-sm">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle availability type changes
        document.getElementById('availabilityType').addEventListener('change', function () {
            const endDateField = document.getElementById('endDateField');
            const endDateInput = document.getElementById('endDateInput');
            const startDateInput = document.querySelector('input[name="start_date"]');

            if (this.value === 'daily') {
                endDateField.style.display = 'none';
                endDateInput.removeAttribute('required');
            } else {
                endDateField.style.display = 'block';
                endDateInput.setAttribute('required', 'required');

                // Set default end date based on type
                if (startDateInput.value) {
                    const startDate = new Date(startDateInput.value);
                    if (this.value === 'weekly') {
                        const endDate = new Date(startDate);
                        endDate.setDate(startDate.getDate() + 6);
                        endDateInput.value = endDate.toISOString().split('T')[0];
                    } else if (this.value === 'monthly') {
                        const endDate = new Date(startDate);
                        endDate.setMonth(startDate.getMonth() + 1);
                        endDate.setDate(startDate.getDate() - 1);
                        endDateInput.value = endDate.toISOString().split('T')[0];
                    }
                }
            }
        });

        // Toggle edit form
        function toggleEdit(slotId) {
            const editForm = document.getElementById('edit-form-' + slotId);
            editForm.style.display = editForm.style.display === 'none' ? 'block' : 'none';
        }

        // Auto-hide success messages after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);

        // Set minimum date to today
        document.querySelector('input[name="start_date"]').min = new Date().toISOString().split('T')[0];
    </script>
</body>

</html>