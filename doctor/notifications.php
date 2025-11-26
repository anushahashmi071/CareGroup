<?php
// doctor/notifications.php - Doctor Notifications Page
require_once '../config.php';
requireRole('doctor');

$doctor = getDoctorByUserId($_SESSION['user_id']);
if (!$doctor) {
    die("Doctor profile not found.");
}

$conn = getDBConnection();

// Mark notification as read if requested
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notification_id = intval($_GET['mark_read']);
    markNotificationAsRead($notification_id);
    header("Location: notifications.php");
    exit();
}

// Mark all as read
if (isset($_POST['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET status = 'read' WHERE user_id = ? AND user_type = 'doctor'");
    $stmt->bind_param("i", $doctor['doctor_id']);
    $stmt->execute();
    $stmt->close();
    header("Location: notifications.php");
    exit();
}

// Delete notification
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $notification_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM notifications WHERE notification_id = ? AND user_id = ? AND user_type = 'doctor'");
    $stmt->bind_param("ii", $notification_id, $doctor['doctor_id']);
    $stmt->execute();
    $stmt->close();
    header("Location: notifications.php");
    exit();
}

// Clear all read notifications
if (isset($_POST['clear_read'])) {
    $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ? AND user_type = 'doctor' AND status = 'read'");
    $stmt->bind_param("i", $doctor['doctor_id']);
    $stmt->execute();
    $stmt->close();
    header("Location: notifications.php");
    exit();
}

// Filter parameters
$filter = $_GET['filter'] ?? 'all'; // all, unread, read
$type_filter = $_GET['type'] ?? 'all'; // all, appointment, reminder, system, alert
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Build WHERE conditions
$where_conditions = ["user_id = ?", "user_type = 'doctor'"];
$params = [$doctor['doctor_id']];
$param_types = "i";

if ($filter === 'unread') {
    $where_conditions[] = "status = 'unread'";
} elseif ($filter === 'read') {
    $where_conditions[] = "status = 'read'";
}

if ($type_filter !== 'all') {
    $where_conditions[] = "type = ?";
    $params[] = $type_filter;
    $param_types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Count total notifications
$count_query = "SELECT COUNT(*) as total FROM notifications WHERE $where_clause";
$stmt = $conn->prepare($count_query);

if (count($params) > 0) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$total_notifications = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = max(1, ceil($total_notifications / $per_page));
$stmt->close();

// Get notifications
$notifications_query = "SELECT * FROM notifications WHERE $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$param_types .= "ii";

$stmt = $conn->prepare($notifications_query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get notification statistics
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'unread' THEN 1 ELSE 0 END) as unread,
        SUM(CASE WHEN type = 'appointment' THEN 1 ELSE 0 END) as appointments,
        SUM(CASE WHEN type = 'reminder' THEN 1 ELSE 0 END) as reminders
    FROM notifications 
    WHERE user_id = ? AND user_type = 'doctor'
";

$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $doctor['doctor_id']);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$conn->close();

// Helper function to format time ago
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $time);
    }
}

// Helper function to get notification icon
function getNotificationIcon($type) {
    $icons = [
        'appointment' => 'fas fa-calendar-check',
        'reminder' => 'fas fa-clock',
        'system' => 'fas fa-cog',
        'alert' => 'fas fa-exclamation-triangle'
    ];
    return $icons[$type] ?? 'fas fa-bell';
}

// Helper function to get notification color
function getNotificationColor($type) {
    $colors = [
        'appointment' => 'primary',
        'reminder' => 'warning',
        'system' => 'info',
        'alert' => 'danger'
    ];
    return $colors[$type] ?? 'secondary';
}


// // Add this temporarily to notifications.php after the doctor data is fetched
// $test_insert = $conn->prepare("
//     INSERT IGNORE INTO notifications (user_id, user_type, type, title, message, status) 
//     VALUES (?, 'doctor', 'alert', 'Test Alert', 'This is a test missed appointment notification', 'unread')
// ");
// $test_insert->bind_param("i", $doctor['doctor_id']);
// $test_insert->execute();
// $test_insert->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Dr. <?= htmlspecialchars($doctor['full_name']) ?></title>
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

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
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

        .notification-card {
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

        .notification-card:hover {
            box-shadow: var(--hover-shadow);
            transform: translateY(-2px);
        }

        .notification-card.unread {
            border-left: 4px solid var(--primary);
            background: #f8fafc;
        }

        .notification-card.read {
            opacity: 0.8;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
        }

        .notification-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex: 1;
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
        }

        .icon-appointment { background: var(--primary); }
        .icon-reminder { background: var(--warning); }
        .icon-system { background: var(--secondary); }
        .icon-alert { background: var(--danger); }

        .notification-content h4 {
            color: var(--dark);
            margin-bottom: 0.25rem;
            font-weight: 600;
        }

        .notification-message {
            color: var(--gray);
            margin: 0.5rem 0;
            line-height: 1.5;
        }

        .notification-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--light-gray);
        }

        .notification-time {
            color: #94a3b8;
            font-size: 0.875rem;
        }

        .notification-actions {
            display: flex;
            gap: 0.5rem;
        }

        .badge {
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-unread {
            background: #dbeafe;
            color: #1e40af;
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

        .filter-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-select {
            padding: 0.5rem 1rem;
            border: 2px solid var(--light-gray);
            border-radius: 8px;
            background: white;
            color: var(--dark);
            font-size: 0.9rem;
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

            .notification-header {
                flex-direction: column;
                gap: 1rem;
            }

            .notification-actions {
                width: 100%;
                justify-content: flex-start;
            }

            .filter-bar {
                flex-direction: column;
                align-items: stretch;
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
                <li><a href="reviews.php"><i class="fas fa-star"></i> Reviews</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="welcome-text">
                    <h1>Notifications</h1>
                    <p>Manage your alerts and messages</p>
                </div>
                <div class="top-actions">
                    <a href="dashboard.php" class="btn btn-outline" style="margin-right: 1rem;">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <a href="../logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['total'] ?? 0 ?></div>
                    <div class="stat-label">Total Notifications</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['unread'] ?? 0 ?></div>
                    <div class="stat-label">Unread</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['appointments'] ?? 0 ?></div>
                    <div class="stat-label">Appointments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['reminders'] ?? 0 ?></div>
                    <div class="stat-label">Reminders</div>
                </div>
            </div>

            <!-- Notifications Section -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-bell"></i> All Notifications</h2>
                    <div>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Mark all notifications as read?')">
                            <button type="submit" name="mark_all_read" class="btn btn-outline btn-sm">
                                <i class="fas fa-check-double"></i> Mark All Read
                            </button>
                        </form>
                        <form method="POST" style="display: inline; margin-left: 0.5rem;" onsubmit="return confirm('Clear all read notifications? This action cannot be undone.')">
                            <button type="submit" name="clear_read" class="btn btn-outline btn-sm">
                                <i class="fas fa-trash"></i> Clear Read
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Filter Bar -->
                <div class="filter-bar">
                    <select name="filter" class="filter-select" onchange="updateFilters()" id="filterSelect">
                        <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Notifications</option>
                        <option value="unread" <?= $filter === 'unread' ? 'selected' : '' ?>>Unread Only</option>
                        <option value="read" <?= $filter === 'read' ? 'selected' : '' ?>>Read Only</option>
                    </select>

                    <select name="type" class="filter-select" onchange="updateFilters()" id="typeSelect">
                        <option value="all" <?= $type_filter === 'all' ? 'selected' : '' ?>>All Types</option>
                        <option value="appointment" <?= $type_filter === 'appointment' ? 'selected' : '' ?>>Appointments</option>
                        <option value="reminder" <?= $type_filter === 'reminder' ? 'selected' : '' ?>>Reminders</option>
                        <option value="system" <?= $type_filter === 'system' ? 'selected' : '' ?>>System</option>
                        <option value="alert" <?= $type_filter === 'alert' ? 'selected' : '' ?>>Alerts</option>
                    </select>
                </div>

                <?php if (!empty($notifications)): ?>
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-card <?= $notification['status'] === 'read' ? 'read' : 'unread' ?>">
                            <div class="notification-header">
                                <div class="notification-title">
                                    <div class="notification-icon icon-<?= $notification['type'] ?>">
                                        <i class="<?= getNotificationIcon($notification['type']) ?>"></i>
                                    </div>
                                    <div class="notification-content">
                                        <h4><?= htmlspecialchars($notification['title']) ?></h4>
                                        <p class="notification-message"><?= htmlspecialchars($notification['message']) ?></p>
                                    </div>
                                </div>
                                <?php if ($notification['status'] === 'unread'): ?>
                                    <span class="badge badge-unread">New</span>
                                <?php endif; ?>
                            </div>

                            <div class="notification-meta">
                                <div class="notification-time">
                                    <i class="fas fa-clock"></i> <?= timeAgo($notification['created_at']) ?>
                                </div>
                                <div class="notification-actions">
                                    <?php if ($notification['status'] === 'unread'): ?>
                                        <a href="?mark_read=<?= $notification['notification_id'] ?>&filter=<?= $filter ?>&type=<?= $type_filter ?>&page=<?= $page ?>" 
                                           class="btn btn-outline btn-sm">
                                            <i class="fas fa-check"></i> Mark Read
                                        </a>
                                    <?php endif; ?>
                                    <a href="?delete=<?= $notification['notification_id'] ?>&filter=<?= $filter ?>&type=<?= $type_filter ?>&page=<?= $page ?>" 
                                       class="btn btn-outline btn-sm"
                                       onclick="return confirm('Delete this notification?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?= $i ?>&filter=<?= $filter ?>&type=<?= $type_filter ?>" 
                               class="page-link <?= $i == $page ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <h3>No notifications found</h3>
                        <p><?= $filter !== 'all' || $type_filter !== 'all' ? 'Try changing your filters.' : 'You have no notifications yet.' ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateFilters() {
            const filter = document.getElementById('filterSelect').value;
            const type = document.getElementById('typeSelect').value;
            window.location.href = `notifications.php?filter=${filter}&type=${type}`;
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
    </script>
</body>
</html>