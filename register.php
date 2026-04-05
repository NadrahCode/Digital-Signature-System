<?php
// Start session
session_start();

// Database connection
require('inc/db_config.php');

// Initialize variables
$error = '';
$success = '';
$name = $email = '';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    // Get form data
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    $errors = [];
    
    // Name validation
    if (empty($name) || strlen($name) < 2) {
        $errors[] = 'Please enter a valid name (minimum 2 characters).';
    }
    
    // Email validation — accept any valid email (gmail, yahoo, outlook, etc.)
    $email = strtolower($email);
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address (e.g. Gmail, Yahoo, Outlook).';
    }
    
    // Password validation with special characters requirement
    if (empty($password) || strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters.';
    }

    // Special characters check
    if (!empty($password) && !preg_match('/[!@#$%^&*()]/', $password)) {
    $errors[] = 'Password must contain at least one special character (!@#$%^&*()).';
    }
    
    // Confirm password
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }
    
    
    // Check if email already exists
    if (empty($errors)) {
        $check_query = "SELECT user_id FROM users WHERE email = ? LIMIT 1";
        if ($stmt = mysqli_prepare($connect, $check_query)) {
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $errors[] = 'Email already registered. Please use a different email or login.';
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    // If no errors, create user
    if (empty($errors)) {
        // ⚠️ STORING PLAIN TEXT PASSWORD (INSECURE - FOR TESTING ONLY)
        $plain_password = $password;
        
        // Default role is 'user' (admin/superadmin must be assigned manually)
        $role = 'user';
        $status = 'active';
        
        // Insert user
        $insert_query = "INSERT INTO users (name, email, password, role, status, created_at) 
                         VALUES (?, ?, ?, ?, ?, NOW())";
        
        if ($stmt = mysqli_prepare($connect, $insert_query)) {
            mysqli_stmt_bind_param($stmt, "sssss", $name, $email, $plain_password, $role, $status);
            
            if (mysqli_stmt_execute($stmt)) {
                // Auto-login and redirect to dashboard
                $new_id = mysqli_insert_id($connect);
                $_SESSION['user_id']   = $new_id;
                $_SESSION['name']      = $name;
                $_SESSION['email']     = $email;
                $_SESSION['role']      = $role;
                $_SESSION['profile_img'] = '';
                $_SESSION['logged_in'] = true;
                header('Location: dashboard.php?welcome=1');
                exit();
            } else {
                $errors[] = 'Registration failed. Please try again.';
                error_log("Registration error: " . mysqli_error($connect));
            }
            mysqli_stmt_close($stmt);
        } else {
            $errors[] = 'Database error. Please try again.';
        }
    }
    
    // Set error message if any
    if (!empty($errors)) {
        $error = implode('<br>', $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | DIGITAL SIGNATURE SYSTEM</title>
    
    <!-- Include external links -->
    <?php require('inc/links.php'); ?>
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Combined CSS -->
    <link rel="stylesheet" href="css/design.css">
    
    <!-- Registration Page Specific CSS -->
    <link rel="stylesheet" href="css/register.css">
    
    <style>
        /* Additional inline styles */
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c7e8f3 100%);
            min-height: 100vh;
        }
        
        /* Background pattern */
        .register-page::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><rect width="100" height="100" fill="%23C7E8F3" opacity="0.1"/><circle cx="50" cy="50" r="30" fill="none" stroke="%23114B2F" stroke-width="2" opacity="0.1"/></svg>');
            opacity: 0.1;
            z-index: 0;
        }
    </style>
</head>
<body>
    <!-- Include header -->
    <?php require('inc/header.php'); ?>

    <!-- Main Registration Section -->
    <main class="register-page">
        <div class="register-container">
            <div class="register-card">
                
                <!-- Success Message (shown after successful registration) -->
                <?php if ($success): ?>
                    <div class="register-success">
                        <div class="success-icon">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <h3>Registration Successful!</h3>
                        <p><?php echo $success; ?></p>
                        <div class="login-link">
                            <a href="login.php" class="login-btn-link">
                                <i class="bi bi-box-arrow-in-right"></i>
                                Go to Login
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                
                <!-- Registration Header -->
                <div class="register-header">
                    <div class="register-icon">
                        <i class="bi bi-person-plus"></i>
                    </div>
                    <h1>CREATE ACCOUNT</h1>
                    <p>Join Digital Signature System as a user</p>
                </div>
                
                <!-- Role Notice -->
                <div class="role-notice">
                    <p>
                        <i class="bi bi-info-circle"></i>
                        <strong>Note:</strong> All new accounts are created as Users. 
                        Admin/SuperAdmin access will be granted manually by  system administrator.
                    </p>
                </div>
                
                <!-- Error Messages -->
                <?php if ($error): ?>
                    <div class="message-box message-error">
                        <i class="bi bi-exclamation-circle me-2"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Registration Form -->
                <form method="POST" id="registerForm">
                    
                    <!-- Full Name Field -->
                    <div class="input-group">
                        <label class="input-label">Full Name</label>
                        <div class="input-wrapper">
                            <span class="input-icon">
                                <i class="bi bi-person"></i>
                            </span>
                            <input 
                                type="text" 
                                name="name" 
                                class="login-input" 
                                placeholder="Enter your full name"
                                value="<?php echo htmlspecialchars($name); ?>"
                                required
                                minlength="2"
                                autocomplete="name"
                            >
                        </div>
                    </div>
                    
                    <!-- Email Field -->
                    <div class="input-group">
                        <label class="input-label">Email Address</label>
                        <div class="input-wrapper">
                            <span class="input-icon">
                                <i class="bi bi-envelope"></i>
                            </span>
                            <input 
                                type="email" 
                                name="email" 
                                class="login-input" 
                                placeholder="e.g. name@gmail.com / yahoo.com / outlook.com"
                                value="<?php echo htmlspecialchars($email); ?>"
                                required
                                autocomplete="email"
                            >
                        </div>
                    </div>
                    
                    <!-- Password Field -->
                    <div class="input-group">
                        <label class="input-label">Password</label>
                        <div class="input-wrapper">
                            <span class="input-icon">
                                <i class="bi bi-lock"></i>
                            </span>
                            <input 
                                type="password" 
                                name="password" 
                                id="password"
                                class="login-input" 
                                placeholder="Create a password (min. 8 characters)"
                                required
                                minlength="8"
                                autocomplete="new-password"
                            >
                            <button type="button" class="password-toggle" id="togglePassword">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Confirm Password Field -->
                    <div class="input-group">
                        <label class="input-label">Confirm Password</label>
                        <div class="input-wrapper">
                            <span class="input-icon">
                                <i class="bi bi-lock-fill"></i>
                            </span>
                            <input 
                                type="password" 
                                name="confirm_password" 
                                id="confirmPassword"
                                class="login-input" 
                                placeholder="Confirm your password"
                                required
                                minlength="8"
                                autocomplete="new-password"
                            >
                            <button type="button" class="password-toggle" id="toggleConfirmPassword">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div id="passwordMatch" style="font-size: 0.85rem; margin-top: 5px;"></div>
                    </div>
                    
                    <!-- Password Requirements -->
                    <div class="password-requirements">
                        <h5>Password Requirements:</h5>
                        <ul>
                            <li id="reqLength">
                                <i class="bi bi-circle"></i>
                                Minimum 8 characters
                            </li>
                            <li id="reqSpecial">
                                <i class="bi bi-circle"></i>
                                Contains special character (!@#$%^&*())
                            </li>
                            <li id="reqMatch">
                                <i class="bi bi-circle"></i>
                                Passwords match
                            </li>
                        </ul>
                    </div>
                    
                    
                    <!-- Register Button -->
                    <div class="button-container">
                        <button type="submit" name="register" class="login-btn" id="registerButton">
                            <i class="bi bi-person-plus"></i>
                            <span>CREATE ACCOUNT</span>
                        </button>
                        <button type="reset" class="reset-btn" id="resetButton">
                            <i class="bi bi-arrow-clockwise"></i>
                            <span>RESET</span>
                        </button>
                    </div>
                    
                    <!-- Already have account link -->
                    <div class="login-link">
                        <p>Already have an account?</p>
                        <a href="login.php" class="login-btn-link">
                            <i class="bi bi-box-arrow-in-right"></i>
                            SIGN IN
                        </a>
                    </div>
                    
                </form>
                <?php endif; ?>
                
            </div>
        </div>
    </main>

    <!-- Include footer -->
    <?php require('inc/footer.php'); ?>
    
    <!-- JavaScript for Registration Page -->

    <script src="js/register.js"></script>
</body>
</html>