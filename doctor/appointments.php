<?php
// doctor/appointments.php - Complete Appointments Management
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../login.php");
    exit();
}

$doctor = getDoctorByUserId($_SESSION['user_id']);
if (!$doctor) {
    die("Doctor profile not found");
}

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

$conn = getDBConnection();

// === AUTO-MARK MISSED APPOINTMENTS ===
$today = date('Y-m-d');
$now = date('H:i:s');

$missed_stmt = $conn->prepare("
    UPDATE appointments 
    SET status = 'missed' 
    WHERE doctor_id = ? 
      AND appointment_date < ? 
      AND status = 'scheduled'
");
$missed_stmt->bind_param("is", $doctor['doctor_id'], $today);
$missed_stmt->execute();
$missed_stmt->close();

$missed_stmt = $conn->prepare("
    UPDATE appointments 
    SET status = 'missed' 
    WHERE doctor_id = ? 
      AND appointment_date = ? 
      AND appointment_time < ? 
      AND status = 'scheduled'
");
$missed_stmt->bind_param("iss", $doctor['doctor_id'], $today, $now);
$missed_stmt->execute();
$missed_stmt->close();

// Build query
$where_clause = "a.doctor_id = ?";
$params = array($doctor['doctor_id']);
$param_types = "i";

if ($filter == 'today') {
    $where_clause .= " AND a.appointment_date = ?";
    $params[] = date('Y-m-d');
    $param_types .= "s";
} elseif ($filter == 'upcoming') {
    $where_clause .= " AND a.appointment_date >= ? AND a.status = 'scheduled'";
    $params[] = date('Y-m-d');
    $param_types .= "s";
} elseif ($filter == 'missed') {
    $where_clause .= " AND a.status = 'missed'";
} elseif ($filter == 'completed') {
    $where_clause .= " AND a.status = 'completed'";
} elseif ($filter == 'cancelled') {
    $where_clause .= " AND a.status = 'cancelled'";
}

if (!empty($search)) {
    $where_clause .= " AND p.full_name LIKE ?";
    $search_param = "%$search%";
    $params[] = $search_param;
    $param_types .= "s";
}

// DEBUG: Check if missed exists
// $debug = $conn->query("SELECT * FROM appointments WHERE doctor_id = {$doctor['doctor_id']} AND status = 'missed'");
// echo "<pre>MISSED COUNT: " . $debug->num_rows . "</pre>";

$query = "
    SELECT 
        a.*,
        p.full_name as patient_name, 
        p.phone, 
        p.gender, 
        p.blood_group, 
        p.date_of_birth,
        a.symptoms,
        CASE 
            WHEN a.status = 'scheduled' 
             AND CONCAT(a.appointment_date, ' ', a.appointment_time) < NOW()
            THEN 1 ELSE 0 
        END as is_missed
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    WHERE $where_clause
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// TEMPORARY DEBUGGING - Check if symptoms are coming from database
error_log("Total appointments found: " . count($appointments));
if (count($appointments) > 0) {
    $first_appointment = $appointments[0];
    error_log("First appointment columns: " . implode(', ', array_keys($first_appointment)));
    error_log("First appointment symptoms: " . ($first_appointment['symptoms'] ?? 'EMPTY'));
}

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - Doctor Portal</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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

        .page-header {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            color: var(--dark);
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
            box-shadow: 0 5px 15px rgba(220, 38, 38, 0.3);
        }

        .controls {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
        }

        .controls-row {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            position: relative;
            min-width: 300px;
        }

        .search-box input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 3rem;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }

        .filter-tabs {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 0.75rem 1.25rem;
            border: 2px solid var(--border);
            background: white;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            color: var(--gray);
            font-weight: 500;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .filter-tab:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-1px);
        }

        .filter-tab.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .appointments-list {
            display: grid;
            gap: 1.5rem;
        }

        .appointment-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: var(--card-shadow);
            border-left: 4px solid var(--primary);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .appointment-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--primary);
        }

        .appointment-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }

        .appointment-card.completed {
            border-left-color: var(--success);
        }

        .appointment-card.completed::before {
            background: var(--success);
        }

        .appointment-card.cancelled {
            border-left-color: var(--danger);
            opacity: 0.8;
        }

        .appointment-card.cancelled::before {
            background: var(--danger);
        }

        .appointment-header {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 2rem;
            align-items: start;
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid var(--border);
        }

        .patient-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
            box-shadow: var(--card-shadow);
        }

        .patient-info h3 {
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-size: 1.3rem;
        }

        .patient-meta {
            display: flex;
            gap: 1.5rem;
            margin-top: 0.5rem;
            color: var(--gray);
            font-size: 0.9rem;
            flex-wrap: wrap;
        }

        .patient-meta span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .appointment-datetime {
            text-align: right;
        }

        .date-badge {
            background: #dbeafe;
            color: var(--primary-dark);
            padding: 0.75rem 1.25rem;
            border-radius: 8px;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 0.75rem;
            border: 1px solid #bfdbfe;
        }

        .time-badge {
            background: #dcfce7;
            color: #065f46;
            padding: 0.75rem 1.25rem;
            border-radius: 8px;
            font-weight: 600;
            display: inline-block;
            border: 1px solid #bbf7d0;
        }

        .appointment-details {
            display: grid;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .detail-box {
            background: var(--light);
            padding: 1.5rem;
            border-radius: 8px;
            border-left: 3px solid var(--primary);
        }

        .detail-box h4 {
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .detail-box p {
            color: var(--gray);
            line-height: 1.6;
        }

        .appointment-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
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
            font-size: 0.9rem;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(37, 99, 235, 0.3);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .btn-info {
            background: var(--primary);
            color: white;
        }

        .btn-info:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }

        .status-scheduled {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .status-completed {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
        }

        .empty-state i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-size: 1.5rem;
        }

        .empty-state p {
            color: var(--gray);
            font-size: 1.1rem;
        }

        .appointment-id {
            color: var(--gray);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: var(--light);
            border-radius: 8px;
            margin-top: 1rem;
        }

        .status-missed {
            background: #f97316 !important;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
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

            .appointment-header {
                grid-template-columns: 1fr;
                text-align: center;
                gap: 1rem;
            }

            .appointment-datetime {
                text-align: center;
            }

            .controls-row {
                flex-direction: column;
            }

            .search-box {
                width: 100%;
            }

            .filter-tabs {
                width: 100%;
                justify-content: center;
            }

            .patient-meta {
                justify-content: center;
            }

            .appointment-actions {
                justify-content: center;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .page-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .appointment-header {
                grid-template-columns: 1fr;
            }

            .patient-meta {
                flex-direction: column;
                gap: 0.5rem;
                align-items: center;
            }

            .appointment-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
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
                <h3>Dr. <?php echo $doctor['full_name']; ?></h3>
                <p><?php echo $doctor['specialization_name']; ?></p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
                <li><a href="appointments.php" class="active"><i class="fas fa-calendar-alt"></i> Appointments</a></li>
                <li><a href="availability.php"><i class="fas fa-clock"></i> Availability</a></li>
                <li><a href="patients.php"><i class="fas fa-users"></i> My Patients</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="reviews.php"><i class="fas fa-star"></i> Reviews</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-calendar-alt"></i> My Appointments <span
                        style="color: var(--primary);">(<?php echo count($appointments); ?>)</span></h1>
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>

            <!-- Controls -->
            <div class="controls">
                <div class="controls-row">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <form method="GET" style="width: 100%;">
                            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                            <input type="text" name="search" placeholder="Search by patient name..."
                                value="<?php echo htmlspecialchars($search); ?>">
                        </form>
                    </div>

                    <div class="filter-tabs">
                        <a href="?filter=all" class="filter-tab <?php echo $filter == 'all' ? 'active' : ''; ?>">
                            All Appointments
                        </a>
                        <a href="?filter=today" class="filter-tab <?php echo $filter == 'today' ? 'active' : ''; ?>">
                            Today
                        </a>
                        <a href="?filter=upcoming"
                            class="filter-tab <?php echo $filter == 'upcoming' ? 'active' : ''; ?>">
                            Upcoming
                        </a>
                        <a href="?filter=completed"
                            class="filter-tab <?php echo $filter == 'completed' ? 'active' : ''; ?>">
                            Completed
                        </a>
                        <a href="?filter=cancelled"
                            class="filter-tab <?php echo $filter == 'cancelled' ? 'active' : ''; ?>">
                            Cancelled
                        </a>
                        <a href="?filter=missed"
                            class="filter-tab <?php echo $filter == 'missed' ? 'active' : ''; ?>">Missed</a>
                    </div>
                </div>
            </div>


            <!-- Appointments List -->
            <?php if (count($appointments) > 0): ?>
                <div class="appointments-list">
                    <?php foreach ($appointments as $apt): ?>
                        <div class="appointment-card <?php echo $apt['status']; ?>">
                            <div class="appointment-header">
                                <div class="patient-avatar">
                                    <?php echo strtoupper(substr($apt['patient_name'], 0, 1)); ?>
                                </div>

                                <div class="patient-info">
                                    <h3><?php echo $apt['patient_name']; ?></h3>
                                    <div class="patient-meta">
                                        <span><i class="fas fa-phone"></i> <?php echo $apt['phone']; ?></span>
                                        <span><i class="fas fa-venus-mars"></i> <?php echo $apt['gender']; ?></span>
                                        <?php if ($apt['blood_group']): ?>
                                            <span><i class="fas fa-tint"></i> <?php echo $apt['blood_group']; ?></span>
                                        <?php endif; ?>
                                        <?php if ($apt['date_of_birth']): ?>
                                            <span><i class="fas fa-birthday-cake"></i>
                                                <?php echo date('Y') - date('Y', strtotime($apt['date_of_birth'])); ?> years</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="appointment-datetime">
                                    <div class="date-badge">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo formatDate($apt['appointment_date']); ?>
                                    </div>
                                    <div class="time-badge">
                                        <i class="fas fa-clock"></i>
                                        <?php echo formatTime($apt['appointment_time']); ?>
                                    </div>
                                </div>
                            </div>

                            <div class="appointment-details">
                                <?php if (!empty($apt['symptoms'])): ?>
                                    <div class="detail-box">
                                        <h4><i class="fas fa-notes-medical"></i> Symptoms</h4>
                                        <p><?php echo nl2br(htmlspecialchars($apt['symptoms'])); ?></p>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($apt['diagnosis'])): ?>
                                    <div class="detail-box" style="border-left-color: var(--primary);">
                                        <h4><i class="fas fa-stethoscope"></i> Diagnosis</h4>
                                        <p><?php echo nl2br(htmlspecialchars($apt['diagnosis'])); ?></p>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($apt['prescription'])): ?>
                                    <div class="detail-box" style="border-left-color: var(--success);">
                                        <h4><i class="fas fa-prescription"></i> Prescription</h4>
                                        <p><?php echo nl2br(htmlspecialchars($apt['prescription'])); ?></p>
                                    </div>
                                <?php endif; ?>

                                <div class="status-container">
                                    <span class="appointment-id">
                                        <i class="fas fa-hashtag"></i> Appointment ID: #<?php echo $apt['appointment_id']; ?>
                                    </span>

                                    <span class="status-badge status-<?php echo $apt['status']; ?>">
                                        <?php echo ucfirst($apt['status']); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="appointment-actions">
                                <a href="view_appointment.php?id=<?php echo $apt['appointment_id']; ?>" class="btn btn-info">
                                    <i class="fas fa-eye"></i> View Details
                                </a>

                                <?php if ($apt['status'] == 'scheduled'): ?>
                                    <a href="complete_appointment.php?id=<?php echo $apt['appointment_id']; ?>"
                                        class="btn btn-success">
                                        <i class="fas fa-check-circle"></i> Mark Complete
                                    </a>

                                <?php endif; ?>

                                <?php if ($apt['status'] == 'scheduled' && $apt['is_missed']): ?>
                                    <a href="mark_missed.php?id=<?php echo $apt['appointment_id']; ?>" class="btn btn-warning"
                                        onclick="return confirm('Mark this appointment as missed?')">
                                        <i class="fas fa-exclamation-triangle"></i> Mark as Missed
                                    </a>
                                <?php endif; ?>

                                <?php if ($apt['status'] == 'completed'): ?>
                                    <button class="btn btn-primary" onclick="window.print()">
                                        <i class="fas fa-print"></i> Print Report
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Appointments Found</h3>
                    <p>There are no appointments matching your current filter criteria.</p>
                    <p style="margin-top: 0.5rem; font-size: 1rem;">Try changing your filter or search term.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Auto-submit search form when typing
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.querySelector('input[name="search"]');
            const searchForm = document.querySelector('.search-box form');

            if (searchInput && searchForm) {
                let timeout;
                searchInput.addEventListener('input', function () {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => {
                        searchForm.submit();
                    }, 500);
                });
            }
        });
    </script>
</body>

</html>