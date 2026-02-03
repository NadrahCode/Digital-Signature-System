<?php
// Start session
session_start();

// Database connection using your specific file
require('inc/db_config.php');

// Initialize variables
$error = '';
$success = '';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login'])) {
        // Get form data
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Basic validation
        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } else {
            // Check if user exists
            $query = "SELECT * FROM users WHERE email = ? AND status = 'active' LIMIT 1";
            
            if ($stmt = mysqli_prepare($connect, $query)) {
                mysqli_stmt_bind_param($stmt, "s", $email);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($result) === 1) {
                    $user = $result->fetch_assoc();
                    
                    // ⚠️ PLAIN TEXT PASSWORD COMPARISON (INSECURE - FOR TESTING ONLY)
                    if ($password === $user['password']) {
                        // Set session variables
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['name'] = $user['name'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['profile_img'] = $user['profile_img'];
                        $_SESSION['logged_in'] = true;
                        
                        // ✅ FIXED: All users go to main dashboard
                        header('Location: dashboard.php');
                        exit();
                    } else {
                        $error = 'Invalid email or password.';
                    }
                } else {
                    $error = 'Invalid email or password.';
                }
                mysqli_stmt_close($stmt);
            } else {
                $error = 'Database error. Please try again later.';
                error_log("Login prepare error: " . mysqli_error($connect));
            }
        }
    }
    
    // Handle reset button
    if (isset($_POST['reset'])) {
        // Clear form fields (handled by client-side)
        $_POST = array();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | DIGITAL SIGNATURE SYSTEM</title>
    
    <!-- Security Warning Banner -->
    <div style="background:#ff4444;color:white;padding:8px;text-align:center;font-size:14px;">
        ⚠️ WARNING: This system stores passwords in plain text - FOR TESTING/DEVELOPMENT ONLY
    </div>
    
    <!-- Include external links -->
    <?php require('inc/links.php'); ?>
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Combined CSS -->
    <link rel="stylesheet" href="css/design.css">
    
    <!-- Login Page Specific CSS -->
    <link rel="stylesheet" href="css/login.css">
    
    <style>
        /* Additional inline styles if needed */
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c7e8f3 100%);
            min-height: 100vh;
        }
        
        /* Background pattern fallback */
        .login-page::before {
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><rect width="100" height="100" fill="%23C7E8F3" opacity="0.1"/><path d="M20,20 L80,20 L80,80 L20,80 Z" fill="none" stroke="%23028090" stroke-width="2" opacity="0.2"/></svg>');
        }
    </style>
</head>
<body>
    <!-- Include header (optional for login page) -->
    <?php require('inc/header.php'); ?>

    <!-- Main Login Section -->
    <main class="login-page">
        <div class="login-container">
            <div class="login-card">
                
                <!-- Login Header with Icon -->
                <div class="login-header">
                    <div class="login-icon">
                        <i class="bi bi-person-circle"></i>
                    </div>
                    <h1>LOGIN</h1>
                    <p>Sign in to your Digital Signature System account</p>
                </div>
                
                <!-- Error/Success Messages -->
                <?php if ($error): ?>
                    <div class="message-box message-error">
                        <i class="bi bi-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="message-box message-success">
                        <i class="bi bi-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Login Form -->
                <form method="POST" id="loginForm">
                    
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
                                placeholder="Enter your email address"
                                value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
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
                                placeholder="Enter your password"
                                required
                                autocomplete="current-password"
                            >
                            <button type="button" class="password-toggle" id="togglePassword">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <!-- Optional: Password strength indicator -->
                        <div class="password-strength">
                            <div class="strength-bar" id="passwordStrength"></div>
                        </div>
                    </div>
                    
                    <!-- Login & Reset Buttons -->
                    <div class="button-container">
                        <button type="submit" name="login" class="login-btn" id="loginButton">
                            <i class="bi bi-box-arrow-in-right"></i>
                            <span>LOGIN</span>
                        </button>
                        <button type="reset" name="reset" class="reset-btn" id="resetButton">
                            <i class="bi bi-arrow-clockwise"></i>
                            <span>RESET</span>
                        </button>
                    </div>
                    
                    <!-- Sign Up Section -->
                    <div class="signup-section">
                        <p>Don't Have An Account?</p>
                        <a href="register.php" class="signup-btn">
                            <i class="bi bi-person-plus"></i>
                            SIGN UP
                        </a>
                    </div>
                    
                </form>
                
            </div>
        </div>
    </main>

    <!-- Include footer -->
    <?php require('inc/footer.php'); ?>

        <!-- JavaScript for Login Page -->
    <script src="js/login.js"></script>
</body>
</html>