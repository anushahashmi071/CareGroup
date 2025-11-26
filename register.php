<?php
// register.php - Patient Registration
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = sanitize($_POST['full_name']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $city_id = intval($_POST['city_id']);
    $gender = sanitize($_POST['gender']);
    $dob = sanitize($_POST['dob']);
    $blood_group = sanitize($_POST['blood_group']);
    $allergies = sanitize($_POST['allergies']);
    $medical_history = sanitize($_POST['medical_history']);

    // Validation
    if ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        // Email validation - must be @gmail.com
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address';
        } elseif (strpos($email, '@gmail.com') === false) {
            $error = 'Only Gmail addresses (@gmail.com) are allowed';
        }
        
        // Phone number validation - must be exactly 11 digits
        elseif (!preg_match('/^[0-9]{11}$/', $phone)) {
            $error = 'Phone number must be exactly 11 digits';
        }
        
        // If no validation errors, proceed with database operations
        else {
            $conn = getDBConnection();

            // Check if username exists
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error = 'Username already exists';
            } else {
                // Check if email exists
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $error = 'Email already exists';
                } else {
                    // Create user account
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, 'patient')");
                    $stmt->bind_param("sss", $username, $hashed_password, $email);

                    if ($stmt->execute()) {
                        $user_id = $conn->insert_id;

                        // *** LOGIN IMMEDIATELY ***
                        $_SESSION['user_id'] = $user_id;
                        $_SESSION['username'] = $username;
                        $_SESSION['role'] = 'patient';
                        $_SESSION['email'] = $email;

                        // Create patient profile
                        $stmt = $conn->prepare("INSERT INTO patients (user_id, full_name, phone, email, address, city_id, gender, date_of_birth, blood_group, allergies, medical_history) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("issssssssss", $user_id, $full_name, $phone, $email, $address, $city_id, $gender, $dob, $blood_group, $allergies, $medical_history);

                        if ($stmt->execute()) {
                            $success = 'Registration successful! You can now login.';
                        } else {
                            $error = 'Error creating profile';
                        }
                    } else {
                        $error = 'Error creating account';
                    }
                }
            }

            $stmt->close();
            $conn->close();
        }
    }
}

$cities = getAllCities();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo htmlspecialchars(getSetting('site_name')); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
   
</head>

<body>
    <div class="reg_bg">
        <div class="register-container">
            <div class="register-header">
                <h1><i class="fas fa-user-plus"></i> Patient Registration</h1>
                <p>Create your account to book appointments</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="registrationForm">
                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name <span>*</span></label>
                        <div class="input-group">
                            <i class="fas fa-user"></i>
                            <input type="text" name="full_name" class="form-control" placeholder="Enter full name"
                                required value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Username <span>*</span></label>
                        <div class="input-group">
                            <i class="fas fa-user-circle"></i>
                            <input type="text" name="username" class="form-control" placeholder="Choose username"
                                required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Email <span>*</span></label>
                        <div class="input-group">
                            <i class="fas fa-envelope"></i>
                            <input type="email" name="email" id="email" class="form-control" placeholder="Enter email" 
                                required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                        <span class="email-hint">Only @gmail.com addresses are allowed</span>
                    </div>

                    <div class="form-group">
                        <label>Phone Number <span>*</span></label>
                        <div class="input-group">
                            <i class="fas fa-phone"></i>
                            <input type="tel" name="phone" id="phone" class="form-control" placeholder="Enter phone number"
                                required value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                        </div>
                        <span class="phone-hint">Must be exactly 11 digits</span>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Password <span>*</span></label>
                        <div class="input-group">
                            <i class="fas fa-lock"></i>
                            <div class="password-wrapper">
                                <input type="password" name="password" id="password" class="form-control" placeholder="Enter password" required>
                                <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <span class="validation-hint">Minimum 6 characters</span>
                    </div>

                    <div class="form-group">
                        <label>Confirm Password <span>*</span></label>
                        <div class="input-group">
                            <i class="fas fa-lock"></i>
                            <div class="password-wrapper">
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Confirm password" required>
                                <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Date of Birth <span>*</span></label>
                        <div class="input-group">
                            <i class="fas fa-calendar"></i>
                            <input type="date" name="dob" class="form-control" required 
                                value="<?php echo isset($_POST['dob']) ? htmlspecialchars($_POST['dob']) : ''; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Gender <span>*</span></label>
                        <div class="input-group">
                            <i class="fas fa-venus-mars"></i>
                            <select name="gender" class="form-control" required>
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Blood Group</label>
                    <select name="blood_group" class="form-control">
                        <option value="">Select Blood Group</option>
                        <option value="A+" <?php echo (isset($_POST['blood_group']) && $_POST['blood_group'] == 'A+') ? 'selected' : ''; ?>>A+</option>
                        <option value="A-" <?php echo (isset($_POST['blood_group']) && $_POST['blood_group'] == 'A-') ? 'selected' : ''; ?>>A-</option>
                        <option value="B+" <?php echo (isset($_POST['blood_group']) && $_POST['blood_group'] == 'B+') ? 'selected' : ''; ?>>B+</option>
                        <option value="B-" <?php echo (isset($_POST['blood_group']) && $_POST['blood_group'] == 'B-') ? 'selected' : ''; ?>>B-</option>
                        <option value="AB+" <?php echo (isset($_POST['blood_group']) && $_POST['blood_group'] == 'AB+') ? 'selected' : ''; ?>>AB+</option>
                        <option value="AB-" <?php echo (isset($_POST['blood_group']) && $_POST['blood_group'] == 'AB-') ? 'selected' : ''; ?>>AB-</option>
                        <option value="O+" <?php echo (isset($_POST['blood_group']) && $_POST['blood_group'] == 'O+') ? 'selected' : ''; ?>>O+</option>
                        <option value="O-" <?php echo (isset($_POST['blood_group']) && $_POST['blood_group'] == 'O-') ? 'selected' : ''; ?>>O-</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>City <span>*</span></label>
                    <div class="input-group">
                        <i class="fas fa-city"></i>
                        <select name="city_id" class="form-control" required>
                            <option value="">Select City</option>
                            <?php foreach ($cities as $city): ?>
                                <option value="<?php echo $city['city_id']; ?>" 
                                    <?php echo (isset($_POST['city_id']) && $_POST['city_id'] == $city['city_id']) ? 'selected' : ''; ?>>
                                    <?php echo $city['city_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Address <span>*</span></label>
                    <div class="input-group">
                        <i class="fas fa-map-marker-alt"></i>
                        <input name="address" class="form-control" placeholder="Enter complete address"
                            required value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>">
                    </div>
                </div>

                <div class="form-group full-width">
                    <label>Allergies (if any)</label>
                    <textarea name="allergies" class="form-control"
                        placeholder="List any allergies you have (e.g., penicillin, peanuts, etc.)"><?php echo isset($_POST['allergies']) ? htmlspecialchars($_POST['allergies']) : ''; ?></textarea>
                </div>

                <div class="form-group full-width">
                    <label>Medical History (if any)</label>
                    <textarea name="medical_history" class="form-control"
                        placeholder="Any previous medical conditions, surgeries, or ongoing treatments"><?php echo isset($_POST['medical_history']) ? htmlspecialchars($_POST['medical_history']) : ''; ?></textarea>
                </div>

                <button type="submit" class="reg_btn">
                    <i class="fas fa-user-plus"></i> Register
                </button>
            </form>

            <div class="links">
                <p>Already have an account? <a href="login.php">Login here</a></p>
                <p><a href="index.php">← Back to Home</a></p>
            </div>
        </div>
    </div>

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

        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const phone = document.getElementById('phone').value;
            let isValid = true;

            // Reset error styles
            document.getElementById('email').classList.remove('error-field');
            document.getElementById('phone').classList.remove('error-field');

            // Email validation - must be @gmail.com
            const emailRegex = /^[a-zA-Z0-9._%+-]+@gmail\.com$/;
            if (!emailRegex.test(email)) {
                document.getElementById('email').classList.add('error-field');
                isValid = false;
            }

            // Phone validation - must be exactly 11 digits
            const phoneRegex = /^[0-9]{11}$/;
            if (!phoneRegex.test(phone)) {
                document.getElementById('phone').classList.add('error-field');
                isValid = false;
            }

            if (!isValid) {
                e.preventDefault();
                alert('Please check your email and phone number:\n- Email must be @gmail.com\n- Phone must be exactly 11 digits');
            }
        });

        // Real-time validation for better UX
        document.getElementById('email').addEventListener('blur', function() {
            const email = this.value;
            const emailRegex = /^[a-zA-Z0-9._%+-]+@gmail\.com$/;
            
            if (email && !emailRegex.test(email)) {
                this.classList.add('error-field');
            } else {
                this.classList.remove('error-field');
            }
        });

        document.getElementById('phone').addEventListener('blur', function() {
            const phone = this.value;
            const phoneRegex = /^[0-9]{11}$/;
            
            if (phone && !phoneRegex.test(phone)) {
                this.classList.add('error-field');
            } else {
                this.classList.remove('error-field');
            }
        });

        // Restrict phone input to numbers only
        document.getElementById('phone').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Real-time password match checking
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.classList.add('error-field');
            } else {
                this.classList.remove('error-field');
            }
        });

        document.getElementById('password').addEventListener('input', function() {
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (confirmPassword && this.value !== confirmPassword) {
                document.getElementById('confirm_password').classList.add('error-field');
            } else {
                document.getElementById('confirm_password').classList.remove('error-field');
            }
        });
    </script>
</body>

</html>