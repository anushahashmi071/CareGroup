<?php
// config.php - Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'medical');

// Create connection
function getDBConnection()
{
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        // More descriptive error message
        die("Database connection failed: " . $conn->connect_error . 
            ". Please check your database credentials in config.php");
    }
    
    return $conn;
}
function requireRole($role)
{
    session_start();
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
        header("Location: ../login.php");
        exit();
    }
}



// For admin/setting.php
function getSetting($key, $default = '') {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $res = $stmt->get_result();
    $value = $res->num_rows ? $res->fetch_assoc()['setting_value'] : $default;
    $stmt->close();
    $conn->close();
    return $value;
}

// Check if user is logged in
function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

// Check user role
function checkRole($role)
{
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Redirect if not logged in
function requireLogin()
{
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

// Session management functions
function checkSessionExpiration() {
    // Agar aap chahte hain ki session kabhi automatically expire na ho
    // toh is function ko completely remove kar dein ya return true karein
    return true;
    
    /* Original code comment out karein
    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 86400 * 30)) {
        session_unset();
        session_destroy();
        return false;
    }
    $_SESSION['LAST_ACTIVITY'] = time();
    return true;
    */
}

function isUserLoggedIn() {
    return isLoggedIn() && checkSessionExpiration();
}




if (session_status() === PHP_SESSION_NONE) {
    // Permanent session - browser close hone tak ya manually logout tak
    $lifetime = 0; // Browser close hone par session expire hoga
    // Ya phir bahut lamba lifetime set karein
    // $lifetime = 86400 * 365; // 1 year
    
    $path = '/';
    $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $secure = isset($_SERVER['HTTPS']);
    $httponly = true;
    $samesite = 'Lax';

    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => $path,
        'domain' => $domain,
        'secure' => $secure,
        'httponly' => $httponly,
        'samesite' => $samesite
    ]);

    // Session GC lifetime ko bhi increase karein
    ini_set('session.gc_maxlifetime', 86400 * 30); // 30 days
}
// Redirect if not specific role


/* ---------- Sanitize ---------- */
function sanitize($data) {
    return is_scalar($data) ? htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8') : '';
}

// Format date
function formatDate($date) {
    if (!$date) return 'N/A';
    return date('M j, Y', strtotime($date));
}

// Format time
function formatTime($time)
{
    return date('g:i A', strtotime($time));
}

// Generate random password
function generatePassword($length = 8)
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    return substr(str_shuffle($chars), 0, $length);
}

// Send email (placeholder - implement with PHPMailer or similar)
function sendEmail($to, $subject, $message)
{
    // Implementation depends on your email service
    return mail($to, $subject, $message);
}

// Upload file
function uploadFile($file, $target_dir = 'uploads/')
{
    $target_file = $target_dir . basename($file["name"]);
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Check if file is an actual image
    $check = getimagesize($file["tmp_name"]);
    if ($check === false) {
        return array('success' => false, 'message' => 'File is not an image.');
    }

    // Check file size (5MB max)
    if ($file["size"] > 5000000) {
        return array('success' => false, 'message' => 'File is too large.');
    }

    // Allow certain file formats
    if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
        return array('success' => false, 'message' => 'Only JPG, JPEG, PNG & GIF files are allowed.');
    }

    // Generate unique filename
    $new_filename = uniqid() . '.' . $imageFileType;
    $target_file = $target_dir . $new_filename;

    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return array('success' => true, 'filename' => $new_filename);
    } else {
        return array('success' => false, 'message' => 'Error uploading file.');
    }
}

// Get user details
function getUserDetails($user_id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->num_rows ? $res->fetch_assoc() : null;
    $stmt->close();
    $conn->close();
    return $user;
}

// Get doctor details by user_id
function getDoctorByUserId($user_id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT d.*, s.specialization_name, c.city_name 
        FROM doctors d
        JOIN specializations s ON d.specialization_id = s.specialization_id
        JOIN cities c ON d.city_id = c.city_id
        WHERE d.user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $doctor = $result->fetch_assoc();
    $stmt->close();
    return $doctor ?: null;
}

// Get patient details by user_id
function getPatientByUserId($user_id)
{
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM patients WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $patient = $result->num_rows > 0 ? $result->fetch_assoc() : null;
    $stmt->close();
    $conn->close();
    return $patient;
}

// Get all cities
function getAllCities()
{
    $conn = getDBConnection();
    $result = $conn->query("SELECT * FROM cities WHERE status = 'active' ORDER BY city_name");
    $cities = $result->fetch_all(MYSQLI_ASSOC);
    $conn->close();
    return $cities;
}

// Get all specializations
function getAllSpecializations()
{
    $conn = getDBConnection();
    $result = $conn->query("SELECT * FROM specializations ORDER BY specialization_name");
    $specializations = $result->fetch_all(MYSQLI_ASSOC);
    $conn->close();
    return $specializations;
}

// Check if appointment slot is available
function isSlotAvailable($doctor_id, $appointment_date, $appointment_time)
{
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE doctor_id = ? 
        AND appointment_date = ? 
        AND appointment_time = ? 
        AND status != 'cancelled'
    ");
    $stmt->bind_param("iss", $doctor_id, $appointment_date, $appointment_time);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $row['count'] == 0;
}
// Get available slots for a doctor on a date
function getAvailableSlots($doctor_id, $appointment_date)
{
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT appointment_time 
        FROM appointments 
        WHERE doctor_id = ? 
        AND appointment_date = ? 
        AND status != 'cancelled'
    ");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return [];
    }
    $stmt->bind_param("is", $doctor_id, $appointment_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $booked_slots = array_column($result->fetch_all(MYSQLI_ASSOC), 'appointment_time');
    $stmt->close();
    $conn->close();

    $slots = [];
    $start_time = strtotime('09:00:00');
    $end_time = strtotime('17:00:00');
    $interval = 30 * 60; // 30 minutes in seconds

    for ($time = $start_time; $time <= $end_time; $time += $interval) {
        $slot = date('H:i:s', $time);
        if (!in_array($slot, $booked_slots)) {
            $slots[] = $slot;
        }
    }

    return $slots;
}

// Book Appointment
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);



// Get doctor availability for a specific day
function getDoctorAvailability($doctor_id, $day_of_week)
{
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT * FROM doctor_availability 
        WHERE doctor_id = ? AND day_of_week = ? AND is_available = 1
    ");
    $stmt->bind_param("is", $doctor_id, $day_of_week);
    $stmt->execute();
    $result = $stmt->get_result();
    $availability = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $availability;
}



// Check if doctor is on leave
function isDoctorOnLeave($doctor_id, $date)
{
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM doctor_leaves 
        WHERE doctor_id = ? AND leave_date = ?
    ");
    $stmt->bind_param("is", $doctor_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $row['count'] > 0;
}

// Generate time slots
function generateTimeSlots($start_time, $end_time, $slot_duration = 30)
{
    $slots = array();
    $start = strtotime($start_time);
    $end = strtotime($end_time);

    while ($start < $end) {
        $slots[] = date('H:i:s', $start);
        $start += $slot_duration * 60;
    }

    return $slots;
}

// Dashboard statistics functions
function getTotalDoctors()
{
    $conn = getDBConnection();
    $result = $conn->query("SELECT COUNT(*) as count FROM doctors WHERE status = 'active'");
    $row = $result->fetch_assoc();
    $conn->close();
    return $row['count'];
}

function getTotalPatients()
{
    $conn = getDBConnection();
    $result = $conn->query("SELECT COUNT(*) as count FROM patients");
    $row = $result->fetch_assoc();
    $conn->close();
    return $row['count'];
}

function getTotalAppointments()
{
    $conn = getDBConnection();
    $result = $conn->query("SELECT COUNT(*) as count FROM appointments");
    $row = $result->fetch_assoc();
    $conn->close();
    return $row['count'];
}

function getTotalCities()
{
    $conn = getDBConnection();
    $result = $conn->query("SELECT COUNT(*) as count FROM cities WHERE status = 'active'");
    $row = $result->fetch_assoc();
    $conn->close();
    return $row['count'];
}

function createNotification($user_id, $user_type, $title, $message, $type, $reference_id = null) {
    $conn = getDBConnection();
    
    // Check if reference_id is provided
    if ($reference_id !== null) {
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, user_type, title, message, type, reference_id, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'unread')
        ");
        $stmt->bind_param("issssi", $user_id, $user_type, $title, $message, $type, $reference_id);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, user_type, title, message, type, status) 
            VALUES (?, ?, ?, ?, ?, 'unread')
        ");
        $stmt->bind_param("issss", $user_id, $user_type, $title, $message, $type);
    }
    
    $result = $stmt->execute();
    
    $stmt->close();
    $conn->close();
    
    return $result;
}

function updateAppointmentStatuses() {
    $conn = getDBConnection();
    $today = date('Y-m-d');
    
    // Update past scheduled appointments to 'missed'
    $update_stmt = $conn->prepare("
        UPDATE appointments 
        SET status = 'missed' 
        WHERE appointment_date < ? 
        AND status = 'scheduled'
    ");
    $update_stmt->bind_param("s", $today);
    $update_stmt->execute();
    $update_stmt->close();
    
    $conn->close();
}

function getUnreadNotificationsCount($user_id, $user_type) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND user_type = ? AND status = 'unread'");
    $stmt->bind_param("is", $user_id, $user_type);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    return $result['count'];
}

function getNotifications($user_id, $user_type, $limit = 10) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? AND user_type = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->bind_param("isi", $user_id, $user_type, $limit);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();
    
    return $result;
}

function markNotificationAsRead($notification_id) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("UPDATE notifications SET status = 'read' WHERE notification_id = ?");
    $stmt->bind_param("i", $notification_id);
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    
    return $result;
}

function updateDoctorRating($doctor_id) {
    $conn = getDBConnection();
    
    $query = "SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM reviews WHERE doctor_id = ? AND rating > 0";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $avg_rating = round((float)($rating_stats['avg_rating'] ?? 0), 1);
    
    $update_query = "UPDATE doctors SET rating = ?, total_reviews = ? WHERE doctor_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param('dii', $avg_rating, $total_reviews, $doctor_id);
    $update_stmt->execute();
    
    $stmt->close();
    $update_stmt->close();
    $conn->close();
}
?>