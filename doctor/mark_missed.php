<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../login.php");
    exit();
}

$doctor = getDoctorByUserId($_SESSION['user_id']);
if (!$doctor) die("Unauthorized");

if (!isset($_GET['id'])) {
    header("Location: appointments.php");
    exit();
}

$appt_id = (int)$_GET['id'];
$conn = getDBConnection();

$stmt = $conn->prepare("
    UPDATE appointments 
    SET status = 'missed' 
    WHERE appointment_id = ? AND doctor_id = ? AND status = 'scheduled'
");
$stmt->bind_param("ii", $appt_id, $doctor['doctor_id']);
$stmt->execute();

$stmt->close();
$conn->close();

header("Location: appointments.php?filter=all");
exit();
?>