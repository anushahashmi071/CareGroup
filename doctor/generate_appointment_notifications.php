<?php
// doctor/generate_appointment_notifications.php
// Run this via cron job every hour or every 15 minutes

require_once '../config.php';
$conn = getDBConnection();

$current_time = date('H:i:00');
$today = date('Y-m-d');

// 1. Find TODAY'S appointments that are still 'scheduled' but time has passed → MISSED
$query = "
     SELECT a.appointment_id, a.appointment_time, a.patient_id, p.full_name as patient_name, d.doctor_id, d.full_name as doctor_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    JOIN doctors d ON a.doctor_id = d.doctor_id
    WHERE a.appointment_date = CURDATE() 
      AND a.status = 'scheduled'
      AND TIMESTAMP(a.appointment_date, a.appointment_time) < NOW()
";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();

// After the missed appointments query, add:
echo "Found " . $result->num_rows . " missed appointments for today: " . $today . "\n";


while ($apt = $result->fetch_assoc()) {
    echo "Processing missed appointment: Doctor {$apt['doctor_id']}, Patient {$apt['patient_name']}, Time {$apt['appointment_time']}\n";

    // Insert notification if not already exists
    $check = $conn->prepare("SELECT 1 FROM notifications WHERE user_id = ? AND user_type = 'doctor' AND title = ? AND message LIKE ?");
    $like_message = "%{$apt['patient_name']}%{$apt['appointment_time']}%";
    $check->bind_param("iss", $apt['doctor_id'], $title, $like_message);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
         echo "Inserting new missed appointment notification\n";
        $insert = $conn->prepare("
            INSERT INTO notifications (user_id, user_type, type, title, message, related_id, status)
            VALUES (?, 'doctor', 'alert', ?, ?, ?, 'unread')
        ");
        $related_id = $apt['appointment_id'];
        $insert->bind_param("issi", $apt['doctor_id'], $title, $message, $related_id);
        $insert->execute();
    } else {
        echo "Notification already exists, skipping\n";
    }
}


// After getting the doctor, add:
echo "Current doctor ID: " . $doctor['doctor_id'] . "\n";

// In the notifications query, temporarily add:
$debug_query = "SELECT * FROM notifications WHERE user_id = ? AND user_type = 'doctor' AND type = 'alert'";
$debug_stmt = $conn->prepare($debug_query);
$debug_stmt->bind_param("i", $doctor['doctor_id']);
$debug_stmt->execute();
$debug_result = $debug_stmt->get_result();
echo "Found " . $debug_result->num_rows . " alert notifications for this doctor\n";



// 2. Send "You have an appointment today" reminder (once per day, in the morning)
$morning_time = "08:00:00"; // Send at 8 AM
if (date('H:i') >= $morning_time && date('H:i') < date('H:i', strtotime('+15 minutes'))) {

    $query2 = "
        SELECT DISTINCT d.doctor_id, d.full_name
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.doctor_id
        WHERE a.appointment_date = ? AND a.status IN ('scheduled', 'confirmed')
    ";

    $stmt2 = $conn->prepare($query2);
    $stmt2->bind_param("s", $today);
    $stmt2->execute();
    $result2 = $stmt2->get_result();

    while ($doc = $result2->fetch_assoc()) {
        $title = "Today's Appointments Reminder";
        $message = "Good morning Dr. {$doc['full_name']}! You have appointments scheduled today. Don't forget to check your schedule.";

        // Only send once per day
        $check2 = $conn->prepare("
            SELECT 1 FROM notifications 
            WHERE user_id = ? AND user_type = 'doctor' 
              AND title = ? AND DATE(created_at) = ?
        ");
        $check2->bind_param("iss", $doc['doctor_id'], $title, $today);
        $check2->execute();
        if ($check2->get_result()->num_rows === 0) {
            $insert2 = $conn->prepare("
                INSERT INTO notifications (user_id, user_type, type, title, message, status)
                VALUES (?, 'doctor', 'reminder', ?, ?, 'unread')
            ");
            $insert2->bind_param("iss", $doc['doctor_id'], $title, $message);
            $insert2->execute();
        }
    }
}

$conn->close();
?>