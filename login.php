<?php
// login.php
require_once 'config.php';

// Ensure session is started
session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];

    $username = sanitize($_POST['username']);
    $password = $_POST['password'];

    // Validate inputs
    if (empty($username) || empty($password)) {
        $error = 'Username and password are required';
    } else {
        $conn = getDBConnection();
        if (!$conn) {
            $error = 'Database connection failed';
        } else {
            $stmt = $conn->prepare("SELECT user_id, username, password, role FROM users WHERE username = ? AND status = 'active'");
            if (!$stmt) {
                $error = 'Query preparation failed: ' . htmlspecialchars($conn->error);
            } else {
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();

                    if (password_verify($password, $user['password'])) {
                        // Set session variables
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['email'] = getUserDetails($user['user_id'])['email'];

                        // Remember me functionality
                        if (isset($_POST['remember_me'])) {
                            // 30 days ke liye session extend karein
                            $lifetime = 86400 * 30;
                            session_set_cookie_params($lifetime);
                            ini_set('session.gc_maxlifetime', $lifetime);
                        }

                        // *** CHECK IF PATIENT NEEDS TO SETUP PROFILE ***
                        if ($user['role'] === 'patient') {
                            $patient = getPatientByUserId($user['user_id']);
                            if (!$patient) {
                                header("Location: patient/dashboard.php");
                                exit();
                            }
                        }

                        // Update last login
                        $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                        $update_stmt->bind_param("i", $user['user_id']);
                        $update_stmt->execute();
                        $update_stmt->close();

                        // Redirect based on role
                        switch ($user['role']) {
                            case 'admin':
                                header("Location: admin/dashboard.php");
                                break;
                            case 'doctor':
                                header("Location: doctor/dashboard.php");
                                break;
                            case 'patient':
                                header("Location: patient/dashboard.php");
                                break;
                            default:
                                $error = 'Invalid user role';
                                break;
                        }
                        $stmt->close();
                        $conn->close();
                        exit();
                    } else {
                        $error = 'Invalid username or password';
                    }
                } else {
                    $error = 'Invalid username or password';
                }
                $stmt->close();
                $conn->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo htmlspecialchars(getSetting('site_name')); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Slab:wght@100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
   
</head>

<body>
    <div class="reg_bg">
        <div class="login-container animate__animated animate__fadeIn">
            <div class="login-left">
                <h1><i class="fas fa-heartbeat"></i> <?php echo htmlspecialchars(getSetting('site_name')); ?></h1>
                <p>Your Health, Our Priority</p>
                <ul class="feature-list">
                    <li>
                        <i class="fas fa-check-circle"></i>
                        <span>Access to 1000+ Expert Doctors</span>
                    </li>
                    <li>
                        <i class="fas fa-check-circle"></i>
                        <span>24/7 Online Appointment Booking</span>
                    </li>
                    <li>
                        <i class="fas fa-check-circle"></i>
                        <span>Secure & Confidential</span>
                    </li>
                    <li>
                        <i class="fas fa-check-circle"></i>
                        <span>Verified Medical Professionals</span>
                    </li>
                </ul>
            </div>

            <div class="login-right">
                <div class="login-header">
                    <h2>Welcome Back!</h2>
                    <p style="color: #64748b;">Login to your account</p>
                </div>

                <?php if ($error): ?>
                    <div class="error-message animate__animated animate__shakeX">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label>Username</label>
                        <div class="input-group">
                            <i class="fas fa-user"></i>
                            <input type="text" name="username" class="form-control" placeholder="Enter your username"
                                required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <div class="input-group">
                            <i class="fas fa-lock"></i>
                            <div class="password-wrapper">
                                <input type="password" name="password" id="password" class="form-control"
                                    placeholder="Enter your password" required>
                                <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                   
                    <button type="submit" class="reg_btn">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>

                <div class="links">
                    <p>Don't have an account? <a href="register.php">Register as Patient</a></p>
                    <p><a href="index.php">← Back to Home</a></p>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password toggle function
        function togglePassword(fieldId) {
            const passwordField = document.getElementById(fieldId);
            const toggleIcon = passwordField.parentNode.querySelector('.password-toggle i');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Optional: Add Enter key support for login
        document.addEventListener('DOMContentLoaded', function() {
            const passwordField = document.getElementById('password');
            passwordField.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    document.querySelector('form').submit();
                }
            });
        });
    </script>
</body>

</html>