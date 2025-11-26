<?php
// helpers.php - Common helper functions

if (!function_exists('calculateAge')) {
    function calculateAge($dob) {
        if (!$dob || $dob === '0000-00-00') return 'N/A';
        $birthdate = new DateTime($dob);
        $today = new DateTime();
        return $today->diff($birthdate)->y;
    }
}

if (!function_exists('formatDate')) {
    function formatDate($date) {
        if (!$date || $date === '0000-00-00') return 'N/A';
        return date('M j, Y', strtotime($date));
    }
}

if (!function_exists('formatDateTime')) {
    function formatDateTime($date, $time) {
        if (!$date) return 'N/A';
        return date('M j, Y g:i A', strtotime($date . ' ' . $time));
    }
}

if (!function_exists('getStatusBadge')) {
    function getStatusBadge($status) {
        $badges = [
            'scheduled' => 'badge-primary',
            'completed' => 'badge-success',
            'cancelled' => 'badge-danger'
        ];
        return $badges[$status] ?? 'badge-secondary';
    }
}

if (!function_exists('calculateBMI')) {
    function calculateBMI($weight, $height) {
        if (!$weight || !$height || $height == 0) return 'N/A';
        $height_in_meters = $height / 100;
        $bmi = $weight / ($height_in_meters * $height_in_meters);
        return round($bmi, 1);
    }
}

if (!function_exists('getBMICategory')) {
    function getBMICategory($bmi) {
        if ($bmi === 'N/A') return 'N/A';
        if ($bmi < 18.5) return 'Underweight';
        if ($bmi < 25) return 'Normal';
        if ($bmi < 30) return 'Overweight';
        return 'Obese';
    }
}

if (!function_exists('safe_html')) {
    function safe_html($value) {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sanitize')) {
    function sanitize($input) {
        if (is_array($input)) {
            return array_map('sanitize', $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}
function calculateBMI($weight, $height) {
    if ($weight && $height) {
        $height_m = $height / 100;
        return round($weight / ($height_m * $height_m), 1);
    }
    return null;
}

function getBMICategory($bmi) {
    if ($bmi < 18.5) return 'Underweight';
    if ($bmi < 25)   return 'Normal';
    if ($bmi < 30)   return 'Overweight';
    return 'Obese';
}
?>