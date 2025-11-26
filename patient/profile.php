<?php
// patient/profile.php
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
$cities = $conn->query("SELECT city_id, city_name FROM cities ORDER BY city_name")->fetch_all(MYSQLI_ASSOC);

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $date_of_birth = $_POST['date_of_birth'];
    $gender = sanitize($_POST['gender']);
    $address = sanitize($_POST['address']);
    $city_id = (int) $_POST['city_id'];

    $stmt = $conn->prepare("
        UPDATE patients 
        SET full_name = ?, email = ?, phone = ?, date_of_birth = ?, gender = ?, address = ?, city_id = ?
        WHERE patient_id = ?");
    $stmt->bind_param("ssssssii", $full_name, $email, $phone, $date_of_birth, $gender, $address, $city_id, $patient['patient_id']);
    if ($stmt->execute()) {
        $message = '<div class="alert alert-success">Profile updated successfully</div>';
        $patient = getPatientByUserId($_SESSION['user_id']); // Refresh data
    } else {
        $message = '<div class="alert alert-danger">Failed to update profile</div>';
    }
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo htmlspecialchars(getSetting('site_name')); ?></title>
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

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.75rem;
            width: 100%;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            outline: none;
        }

        .profile-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .profile-card {
            background: #f8fafc;
            border-left: 4px solid #6366f1;
            border-radius: 10px;
            padding: 1.5rem;
            border: 1px solid #e2e8f0;
        }

        .profile-card h4 {
            color: #1e293b;
            margin-bottom: 1rem;
            font-weight: 600;
            font-size: 1.2rem;
        }

        .profile-card p {
            color: #64748b;
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
            padding: 0.25rem 0;
        }

        .profile-card p strong {
            color: #334155;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border: 1px solid transparent;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-color: #a7f3d0;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fecaca;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #374151;
            font-weight: 500;
            font-size: 0.95rem;
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

            .profile-details {
                grid-template-columns: 1fr;
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
                <h3><?php echo htmlspecialchars($patient['full_name']); ?></h3>
                <p>Patient ID: #<?php echo $patient['patient_id']; ?></p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
                <li><a href="search_doctors.php"><i class="fas fa-search"></i> Find Doctors</a></li>
                <li><a href="my_appointments.php"><i class="fas fa-calendar-alt"></i> My Appointments</a></li>
                <li><a href="medical_records.php"><i class="fas fa-file-medical"></i> Medical Records</a></li>
                <li><a href="profile.php" class="active"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="health_tips.php"><i class="fas fa-heartbeat"></i> Health Tips</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="welcome-text">
                    <h1>My Profile</h1>
                    <p>Manage your personal information</p>
                </div>
                <div class="top-actions">
                    <a href="search_doctors.php" class="btn btn-primary">
                        <i class="fas fa-search"></i> Find a Doctor
                    </a>
                    <a href="../logout.php" class="btn logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <!-- Profile Content -->
            <div class="content-section">
                <?php if ($message): ?>
                    <div>
                        <?= $message ?>
                    </div>
                <?php endif; ?>
                
                <div class="section-header">
                    <h2><i class="fas fa-user-circle"></i> Personal Information</h2>
                </div>

                <div class="profile-details">
                    <div class="profile-card">
                        <h4>Profile Details</h4>
                        <p><strong>Name:</strong> <?= htmlspecialchars($patient['full_name']) ?></p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($patient['email']) ?></p>
                        <p><strong>Phone:</strong> <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></p>
                        <p><strong>Date of Birth:</strong>
                            <?= $patient['date_of_birth'] ? formatDate($patient['date_of_birth']) : 'N/A' ?></p>
                        <p><strong>Gender:</strong> <?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></p>
                        <p><strong>Address:</strong> <?= htmlspecialchars($patient['address'] ?? 'N/A') ?></p>
                        <p><strong>City:</strong> <?= htmlspecialchars($patient['city_name'] ?? 'N/A') ?></p>
                    </div>
                </div>

                <form method="POST" action="">
                    <div class="section-header">
                        <h2><i class="fas fa-edit"></i> Edit Profile</h2>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Full Name</label>
                            <input type="text" name="full_name" class="form-control"
                                value="<?= htmlspecialchars($patient['full_name']) ?>" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control"
                                value="<?= htmlspecialchars($patient['email']) ?>" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Phone</label>
                            <input type="text" name="phone" class="form-control"
                                value="<?= htmlspecialchars($patient['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control"
                                value="<?= $patient['date_of_birth'] ?? '' ?>">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Gender</label>
                            <select name="gender" class="form-control">
                                <option value="">Select Gender</option>
                                <option value="Male" <?= $patient['gender'] == 'Male' ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= $patient['gender'] == 'Female' ? 'selected' : '' ?>>Female
                                </option>
                                <option value="Other" <?= $patient['gender'] == 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>City</label>
                            <select name="city_id" class="form-control">
                                <option value="0">Select City</option>
                                <?php foreach ($cities as $city): ?>
                                    <option value="<?= $city['city_id'] ?>" <?= $patient['city_id'] == $city['city_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($city['city_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12 form-group">
                            <label>Address</label>
                            <textarea name="address" rows="3"
                                class="form-control"><?= htmlspecialchars($patient['address'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </form>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>