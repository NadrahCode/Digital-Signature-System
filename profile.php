<?php
// Start session and check login
session_start();
require('inc/db_config.php');

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get current user info
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];
$user_email = $_SESSION['email'];
$user_role = $_SESSION['role'];
$profile_img = $_SESSION['profile_img'] ?? '';

// Initialize variables
$success_message = '';
$error_message = '';
$user_profile_data = [];
$all_users_data = [];

// Function to handle file upload
function uploadProfileImage($file, $user_id) {
    $target_dir = "uploads/profile/";
    
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    // Generate unique filename
    $file_extension = pathinfo($file["name"], PATHINFO_EXTENSION);
    $filename = "user_" . $user_id . "_" . time() . "." . $file_extension;
    $target_file = $target_dir . $filename;
    
    // Check if image file is actual image
    $check = getimagesize($file["tmp_name"]);
    if ($check === false) {
        return ['success' => false, 'message' => 'File is not an image.'];
    }
    
    // Check file size (max 2MB)
    if ($file["size"] > 2097152) {
        return ['success' => false, 'message' => 'Image size must be less than 2MB.'];
    }
    
    // Allow certain file formats
    $allowed_extensions = ["jpg", "jpeg", "png", "gif"];
    if (!in_array(strtolower($file_extension), $allowed_extensions)) {
        return ['success' => false, 'message' => 'Only JPG, JPEG, PNG & GIF files are allowed.'];
    }
    
    // Upload file
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return ['success' => true, 'filename' => $filename];
    } else {
        return ['success' => false, 'message' => 'Error uploading file.'];
    }
}

// Handle Profile Update (for user and admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile']) && $user_role !== 'superadmin') {
    $name = mysqli_real_escape_string($connect, $_POST['name']);
    $email = mysqli_real_escape_string($connect, $_POST['email']);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $use_gravatar = isset($_POST['use_gravatar']) ? 1 : 0;
    
    $errors = [];
    
    // Check if email is already taken by another user
    if ($email !== $user_email) {
        $check_email = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
        $stmt = mysqli_prepare($connect, $check_email);
        mysqli_stmt_bind_param($stmt, "si", $email, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $errors[] = 'Email is already taken by another user.';
        }
        mysqli_stmt_close($stmt);
    }
    
    // Handle password change if provided
    if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
        // Get current password hash
        $get_password = "SELECT password FROM users WHERE user_id = ?";
        $stmt = mysqli_prepare($connect, $get_password);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $db_password);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        
        // Verify current password
        if ($db_password !== $current_password) {
            $errors[] = 'Current password is incorrect.';
        }
        
        // Check new password requirements
        if (strlen($new_password) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        }
        
        // Check for special characters
        if (!preg_match('/[!@#$%^&*()]/', $new_password)) {
            $errors[] = 'New password must contain at least one special character (!@#$%^&*()).';
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = 'New passwords do not match.';
        }
    }
    
    // Handle profile image
    $profile_image_path = $profile_img;
    if (!$use_gravatar && isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $upload_result = uploadProfileImage($_FILES['profile_image'], $user_id);
        if ($upload_result['success']) {
            $profile_image_path = $upload_result['filename'];
        } else {
            $errors[] = $upload_result['message'];
        }
    } elseif ($use_gravatar) {
        $profile_image_path = '';
    }
    
    // If no errors, update profile
    if (empty($errors)) {
        if (!empty($new_password)) {
            $update_query = "UPDATE users SET name = ?, email = ?, password = ?, profile_img = ? WHERE user_id = ?";
            $stmt = mysqli_prepare($connect, $update_query);
            mysqli_stmt_bind_param($stmt, "ssssi", $name, $email, $new_password, $profile_image_path, $user_id);
        } else {
            $update_query = "UPDATE users SET name = ?, email = ?, profile_img = ? WHERE user_id = ?";
            $stmt = mysqli_prepare($connect, $update_query);
            mysqli_stmt_bind_param($stmt, "sssi", $name, $email, $profile_image_path, $user_id);
        }
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['name'] = $name;
            $_SESSION['email'] = $email;
            $_SESSION['profile_img'] = $profile_image_path;
            
            $user_name = $name;
            $user_email = $email;
            $profile_img = $profile_image_path;
            
            $success_message = 'Profile updated successfully!';
        } else {
            $errors[] = 'Error updating profile: ' . mysqli_error($connect);
        }
        mysqli_stmt_close($stmt);
    }
    
    if (!empty($errors)) {
        $error_message = implode('<br>', $errors);
    }
}

// Handle User Management Actions (Superadmin only)
if ($user_role === 'superadmin') {
    // Handle Add New User
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
        $new_name = mysqli_real_escape_string($connect, $_POST['new_name']);
        $new_email = mysqli_real_escape_string($connect, $_POST['new_email']);
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        $new_role = mysqli_real_escape_string($connect, $_POST['new_role']);
        $new_status = mysqli_real_escape_string($connect, $_POST['new_status']);
        $use_gravatar_new = isset($_POST['use_gravatar_new']) ? 1 : 0;
        
        $errors = [];
        
        // Validation
        if (strlen($new_name) < 2) {
            $errors[] = 'Name must be at least 2 characters.';
        }
        
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format.';
        }
        
        if (strlen($new_password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        
        if (!preg_match('/[!@#$%^&*()]/', $new_password)) {
            $errors[] = 'Password must contain at least one special character (!@#$%^&*()).';
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = 'Passwords do not match.';
        }
        
        // Check if email exists
        $check_email = "SELECT user_id FROM users WHERE email = ?";
        $stmt = mysqli_prepare($connect, $check_email);
        mysqli_stmt_bind_param($stmt, "s", $new_email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $errors[] = 'Email already registered.';
        }
        mysqli_stmt_close($stmt);
        
        // Handle profile image for new user
        $profile_image_path_new = '';
        if (!$use_gravatar_new && isset($_FILES['profile_image_new']) && $_FILES['profile_image_new']['error'] == 0) {
            $upload_result = uploadProfileImage($_FILES['profile_image_new'], 'new');
            if ($upload_result['success']) {
                $profile_image_path_new = $upload_result['filename'];
            } else {
                $errors[] = $upload_result['message'];
            }
        }
        
        if (empty($errors)) {
            $insert_query = "INSERT INTO users (name, email, password, role, status, profile_img, created_at) 
                             VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $stmt = mysqli_prepare($connect, $insert_query);
            mysqli_stmt_bind_param($stmt, "ssssss", $new_name, $new_email, $new_password, $new_role, $new_status, $profile_image_path_new);
            
            if (mysqli_stmt_execute($stmt)) {
                $success_message = 'User added successfully!';
            } else {
                $errors[] = 'Error adding user: ' . mysqli_error($connect);
            }
            mysqli_stmt_close($stmt);
        }
        
        if (!empty($errors)) {
            $error_message = implode('<br>', $errors);
        }
    }
    
    // Handle Update User (superadmin can edit all fields including password)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user_admin'])) {
        $update_user_id = intval($_POST['update_user_id']);
        $update_name = mysqli_real_escape_string($connect, $_POST['update_name']);
        $update_email = mysqli_real_escape_string($connect, $_POST['update_email']);
        $update_password = $_POST['update_password'];
        $update_confirm_password = $_POST['update_confirm_password'];
        $update_role = mysqli_real_escape_string($connect, $_POST['update_role']);
        $update_status = mysqli_real_escape_string($connect, $_POST['update_status']);
        $use_gravatar_update = isset($_POST['use_gravatar_update']) ? 1 : 0;
        
        $errors = [];
        
        // Don't allow superadmin to demote themselves
        if ($update_user_id == $user_id && $update_role !== 'superadmin') {
            $errors[] = 'You cannot change your own role from superadmin.';
        }
        
        // Check if email is already taken by another user
        if ($update_email !== $_POST['original_email']) {
            $check_email = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
            $stmt = mysqli_prepare($connect, $check_email);
            mysqli_stmt_bind_param($stmt, "si", $update_email, $update_user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $errors[] = 'Email is already taken by another user.';
            }
            mysqli_stmt_close($stmt);
        }
        
        // Handle password change if provided
        if (!empty($update_password)) {
            if (strlen($update_password) < 8) {
                $errors[] = 'Password must be at least 8 characters.';
            }
            
            if (!preg_match('/[!@#$%^&*()]/', $update_password)) {
                $errors[] = 'Password must contain at least one special character (!@#$%^&*()).';
            }
            
            if ($update_password !== $update_confirm_password) {
                $errors[] = 'Passwords do not match.';
            }
        }
        
        // Handle profile image update
        $profile_image_path_update = $_POST['current_profile_img'];
        if (!$use_gravatar_update && isset($_FILES['profile_image_update']) && $_FILES['profile_image_update']['error'] == 0) {
            $upload_result = uploadProfileImage($_FILES['profile_image_update'], $update_user_id);
            if ($upload_result['success']) {
                $profile_image_path_update = $upload_result['filename'];
            } else {
                $errors[] = $upload_result['message'];
            }
        } elseif ($use_gravatar_update) {
            $profile_image_path_update = '';
        }
        
        if (empty($errors)) {
            // Prepare update query based on whether password is being changed
            if (!empty($update_password)) {
                $update_query = "UPDATE users SET name = ?, email = ?, password = ?, role = ?, status = ?, profile_img = ? WHERE user_id = ?";
                $stmt = mysqli_prepare($connect, $update_query);
                mysqli_stmt_bind_param($stmt, "ssssssi", $update_name, $update_email, $update_password, $update_role, $update_status, $profile_image_path_update, $update_user_id);
            } else {
                $update_query = "UPDATE users SET name = ?, email = ?, role = ?, status = ?, profile_img = ? WHERE user_id = ?";
                $stmt = mysqli_prepare($connect, $update_query);
                mysqli_stmt_bind_param($stmt, "sssssi", $update_name, $update_email, $update_role, $update_status, $profile_image_path_update, $update_user_id);
            }
            
            if (mysqli_stmt_execute($stmt)) {
                // If updating current user, update session
                if ($update_user_id == $user_id) {
                    $_SESSION['name'] = $update_name;
                    $_SESSION['email'] = $update_email;
                    $_SESSION['role'] = $update_role;
                    $_SESSION['profile_img'] = $profile_image_path_update;
                }
                
                $success_message = 'User updated successfully!';
            } else {
                $errors[] = 'Error updating user: ' . mysqli_error($connect);
            }
            mysqli_stmt_close($stmt);
        }
        
        if (!empty($errors)) {
            $error_message = implode('<br>', $errors);
        }
    }
    
    // Handle Delete User
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
        $delete_user_id = intval($_POST['delete_user_id']);
        
        // Prevent self-deletion
        if ($delete_user_id == $user_id) {
            $error_message = 'You cannot delete your own account.';
        } else {
            $delete_query = "DELETE FROM users WHERE user_id = ?";
            $stmt = mysqli_prepare($connect, $delete_query);
            mysqli_stmt_bind_param($stmt, "i", $delete_user_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $success_message = 'User deleted successfully!';
            } else {
                $error_message = 'Error deleting user: ' . mysqli_error($connect);
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    // Handle Bulk Actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
        $bulk_action = $_POST['bulk_action'];
        $selected_users = $_POST['selected_users'] ?? [];
        
        if (empty($selected_users)) {
            $error_message = 'No users selected.';
        } else {
            $success_count = 0;
            $error_count = 0;
            
            foreach ($selected_users as $bulk_user_id) {
                $bulk_user_id = intval($bulk_user_id);
                
                // Skip self for dangerous actions
                if ($bulk_user_id == $user_id && ($bulk_action === 'delete' || $bulk_action === 'inactive')) {
                    continue;
                }
                
                if ($bulk_action === 'delete') {
                    $query = "DELETE FROM users WHERE user_id = ?";
                } elseif ($bulk_action === 'active') {
                    $query = "UPDATE users SET status = 'active' WHERE user_id = ?";
                } elseif ($bulk_action === 'inactive') {
                    $query = "UPDATE users SET status = 'inactive' WHERE user_id = ?";
                } else {
                    continue;
                }
                
                $stmt = mysqli_prepare($connect, $query);
                mysqli_stmt_bind_param($stmt, "i", $bulk_user_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $success_count++;
                } else {
                    $error_count++;
                }
                mysqli_stmt_close($stmt);
            }
            
            if ($success_count > 0) {
                $success_message = "Bulk action completed: {$success_count} user(s) updated.";
            }
            if ($error_count > 0) {
                $error_message = "Bulk action had {$error_count} error(s).";
            }
        }
    }
    
    // Fetch all users for management table
    $search_query = isset($_GET['search']) ? mysqli_real_escape_string($connect, $_GET['search']) : '';
    $role_filter = isset($_GET['role_filter']) ? mysqli_real_escape_string($connect, $_GET['role_filter']) : '';
    
    $query = "SELECT * FROM users WHERE 1=1";
    $params = [];
    $types = "";
    
    if (!empty($search_query)) {
        $query .= " AND (name LIKE ? OR email LIKE ?)";
        $search_param = "%{$search_query}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "ss";
    }
    
    if (!empty($role_filter)) {
        $query .= " AND role = ?";
        $params[] = $role_filter;
        $types .= "s";
    }
    
    $query .= " ORDER BY created_at DESC";
    
    $stmt = mysqli_prepare($connect, $query);
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $all_users_data[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// Fetch current user's data for form (only if not superadmin)
if ($user_role !== 'superadmin') {
    $get_user_query = "SELECT * FROM users WHERE user_id = ?";
    $stmt = mysqli_prepare($connect, $get_user_query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user_profile_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile | DIGITAL SIGNATURE SYSTEM</title>
    
    <!-- Include external links -->
    <?php require('inc/links.php'); ?>
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Combined CSS -->
    <link rel="stylesheet" href="css/design.css">
    
    <!-- Dashboard CSS -->
    <link rel="stylesheet" href="css/dashboard.css">
    
    <!-- Profile Specific CSS -->
    <link rel="stylesheet" href="css/profile.css">
    
    <!-- MD5 for Gravatar -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>
</head>
<body data-theme="light">
    <!-- Dashboard Container -->
    <div class="dashboard-container">
        
        <!-- Include Sidebar -->
        <?php require('inc/sidebar.php'); ?>
        
        <!-- Main Content -->
        <main class="main-content">
            
            <!-- Include Top Header -->
            <?php require('inc/topheader.php'); ?>
            
            <!-- Welcome Section -->
            <section class="welcome-section">
                <h1 class="welcome-title">Profile Management</h1>
                <h2 class="system-title">
                    <?php 
                    if ($user_role === 'superadmin') {
                        echo 'User Management System';
                    } elseif ($user_role === 'admin') {
                        echo 'Manage Your Administrator Profile';
                    } else {
                        echo 'Manage Your User Profile';
                    }
                    ?>
                </h2>
                <div class="logo-container">
                    <a href="dashboard.php" class="logo-link">
                        <img src="images/logo-main.png" alt="Digital Signature System Logo" class="system-logo">
                    </a>
                </div>
            </section>
            
            <!-- Personal Profile Card (Only for User and Admin) -->
            <?php if ($user_role !== 'superadmin'): ?>
            <section class="profile-card-section">
                <div class="profile-card">
                    <div class="card-header">
                        <h3><i class="bi bi-person-circle"></i> Personal Profile</h3>
                        <span class="role-badge profile-role"><?php echo ucfirst($user_role); ?></span>
                    </div>
                    
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data" id="profileForm">
                            <div class="form-grid">
                                <!-- Profile Image Section -->
                                <div class="form-group profile-image-section">
                                    <div class="image-preview">
                                        <?php if (!empty($user_profile_data['profile_img'])): ?>
                                            <img src="uploads/profile/<?php echo htmlspecialchars($user_profile_data['profile_img']); ?>" 
                                                 alt="Profile Image" id="imagePreview" class="profile-preview">
                                        <?php elseif (!empty($user_profile_data['email'])): ?>
                                            <img src="https://www.gravatar.com/avatar/<?php echo md5(strtolower(trim($user_profile_data['email']))); ?>?s=200&d=identicon" 
                                                 alt="Gravatar" id="imagePreview" class="profile-preview">
                                        <?php else: ?>
                                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user_profile_data['name']); ?>&size=200&background=028090&color=fff" 
                                                 alt="Profile" id="imagePreview" class="profile-preview">
                                        <?php endif; ?>
                                        <div class="image-overlay">
                                            <i class="bi bi-camera"></i>
                                            <span>Change Photo</span>
                                        </div>
                                        <input type="file" name="profile_image" id="profileImage" accept="image/*" class="image-input">
                                    </div>
                                    
                                    <div class="image-options">
                                        <label class="checkbox-option">
                                            <input type="checkbox" name="use_gravatar" id="useGravatar" 
                                                   <?php echo empty($user_profile_data['profile_img']) ? 'checked' : ''; ?>>
                                            <span>Use Gravatar</span>
                                        </label>
                                        <small class="image-hint">
                                            Upload JPG, PNG or GIF (max 2MB)<br>
                                            Or use Gravatar with your email
                                        </small>
                                    </div>
                                </div>
                                
                                <!-- Form Fields -->
                                <div class="form-fields">
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="name">
                                                <i class="bi bi-person"></i> Full Name *
                                            </label>
                                            <input type="text" id="name" name="name" class="form-control" 
                                                   value="<?php echo htmlspecialchars($user_profile_data['name']); ?>" 
                                                   required minlength="2">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="email">
                                                <i class="bi bi-envelope"></i> Email Address *
                                            </label>
                                            <input type="email" id="email" name="email" class="form-control" 
                                                   value="<?php echo htmlspecialchars($user_profile_data['email']); ?>" 
                                                   required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="current_password">
                                            <i class="bi bi-lock"></i> Current Password
                                        </label>
                                        <div class="password-wrapper">
                                            <input type="password" id="current_password" name="current_password" 
                                                   class="form-control" placeholder="Enter current password to change">
                                            <button type="button" class="password-toggle">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="new_password">
                                                <i class="bi bi-lock-fill"></i> New Password
                                            </label>
                                            <div class="password-wrapper">
                                                <input type="password" id="new_password" name="new_password" 
                                                       class="form-control" placeholder="Enter new password">
                                                <button type="button" class="password-toggle">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                            <div id="passwordStrength"></div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="confirm_password">
                                                <i class="bi bi-lock-fill"></i> Confirm Password
                                            </label>
                                            <div class="password-wrapper">
                                                <input type="password" id="confirm_password" name="confirm_password" 
                                                       class="form-control" placeholder="Confirm new password">
                                                <button type="button" class="password-toggle">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                            <div id="passwordMatch"></div>
                                        </div>
                                    </div>
                                    
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
                                    
                                    <div class="form-actions">
                                        <button type="submit" name="update_profile" class="btn btn-primary">
                                            <i class="bi bi-save"></i> Update Profile
                                        </button>
                                        <button type="button" class="btn btn-secondary" id="resetProfileForm">
                                            <i class="bi bi-arrow-clockwise"></i> Reset
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
            <?php endif; ?>
            
            <!-- User Management Card (Superadmin Only) -->
            <?php if ($user_role === 'superadmin'): ?>
            <section class="user-management-section">
                <div class="management-card">
                    <div class="card-header">
                        <h3><i class="bi bi-people"></i> User Management</h3>
                        <div class="header-actions">
                            <button class="btn btn-primary" id="addUserBtn">
                                <i class="bi bi-person-plus"></i> Add New User
                            </button>
                            <button class="btn btn-secondary" id="refreshTable">
                                <i class="bi bi-arrow-clockwise"></i> Refresh
                            </button>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <!-- Filters and Search -->
                        <div class="table-controls">
                            <form method="GET" action="" class="filter-form">
                                <div class="search-box">
                                    <i class="bi bi-search"></i>
                                    <input type="text" name="search" placeholder="Search users..." 
                                           value="<?php echo htmlspecialchars($search_query); ?>">
                                    <button type="submit" class="search-btn">
                                        <i class="bi bi-search"></i> Search
                                    </button>
                                </div>
                                
                                <div class="filter-group">
                                    <select name="role_filter" class="form-control filter-select">
                                        <option value="">All Roles</option>
                                        <option value="user" <?php echo $role_filter === 'user' ? 'selected' : ''; ?>>User</option>
                                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        <option value="superadmin" <?php echo $role_filter === 'superadmin' ? 'selected' : ''; ?>>Superadmin</option>
                                    </select>
                                    
                                    <button type="submit" class="btn btn-secondary">
                                        <i class="bi bi-filter"></i> Apply Filters
                                    </button>
                                    <a href="profile.php" class="btn btn-outline">
                                        <i class="bi bi-x-circle"></i> Clear
                                    </a>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Bulk Actions -->
                        <div class="bulk-actions">
                            <select id="bulkActionSelect" class="form-control bulk-select">
                                <option value="">Bulk Actions</option>
                                <option value="active">Activate</option>
                                <option value="inactive">Deactivate</option>
                                <option value="delete">Delete</option>
                            </select>
                            <button type="button" class="btn btn-secondary" id="applyBulkAction">
                                <i class="bi bi-check-circle"></i> Apply
                            </button>
                            <span class="selected-count">0 users selected</span>
                        </div>
                        
                        <!-- Users Table -->
                        <div class="table-responsive">
                            <table class="data-table users-table">
                                <thead>
                                    <tr>
                                        <th>
                                            <input type="checkbox" id="selectAll">
                                        </th>
                                        <th data-sort="id">ID</th>
                                        <th data-sort="name">Name</th>
                                        <th data-sort="email">Email</th>
                                        <th data-sort="role">Role</th>
                                        <th data-sort="status">Status</th>
                                        <th>Profile</th>
                                        <th data-sort="created">Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($all_users_data)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center">
                                            <div class="no-data">
                                                <i class="bi bi-people"></i>
                                                <p>No users found</p>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($all_users_data as $user): ?>
                                        <tr data-user-id="<?php echo $user['user_id']; ?>">
                                            <td>
                                                <input type="checkbox" class="user-checkbox" 
                                                       value="<?php echo $user['user_id']; ?>"
                                                       <?php echo $user['user_id'] == $user_id ? 'disabled' : ''; ?>>
                                            </td>
                                            <td><?php echo $user['user_id']; ?></td>
                                            <td>
                                                <div class="user-info">
                                                    <?php if (!empty($user['profile_img'])): ?>
                                                        <img src="uploads/profile/<?php echo htmlspecialchars($user['profile_img']); ?>" 
                                                             alt="<?php echo htmlspecialchars($user['name']); ?>" class="user-avatar-sm">
                                                    <?php else: ?>
                                                        <img src="https://www.gravatar.com/avatar/<?php echo md5(strtolower(trim($user['email']))); ?>?s=40&d=identicon" 
                                                             alt="<?php echo htmlspecialchars($user['name']); ?>" class="user-avatar-sm">
                                                    <?php endif; ?>
                                                    <span><?php echo htmlspecialchars($user['name']); ?></span>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <span class="role-badge role-<?php echo $user['role']; ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo $user['status']; ?>">
                                                    <?php echo ucfirst($user['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($user['profile_img'])): ?>
                                                    <span class="badge badge-success">Uploaded</span>
                                                <?php else: ?>
                                                    <span class="badge badge-info">Gravatar</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="action-btn btn-edit" 
                                                            data-user-id="<?php echo $user['user_id']; ?>"
                                                            data-user-name="<?php echo htmlspecialchars($user['name']); ?>"
                                                            data-user-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                            data-user-role="<?php echo $user['role']; ?>"
                                                            data-user-status="<?php echo $user['status']; ?>"
                                                            data-profile-img="<?php echo htmlspecialchars($user['profile_img'] ?? ''); ?>">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    
                                                    <button class="action-btn btn-delete" 
                                                            data-user-id="<?php echo $user['user_id']; ?>"
                                                            data-user-name="<?php echo htmlspecialchars($user['name']); ?>"
                                                            <?php echo $user['user_id'] == $user_id ? 'disabled' : ''; ?>>
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if (!empty($all_users_data)): ?>
                        <div class="pagination">
                            <span>Showing <?php echo count($all_users_data); ?> users</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>
            
            <!-- Add User Modal (Superadmin Only) -->
            <?php if ($user_role === 'superadmin'): ?>
            <div id="addUserModal" class="modal-overlay">
                <div class="modal-content wide-modal">
                    <div class="modal-header">
                        <h3><i class="bi bi-person-plus"></i> Add New User</h3>
                        <button class="close-modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="" enctype="multipart/form-data" id="addUserForm">
                            <div class="form-grid-modal">
                                <!-- Profile Image Section -->
                                <div class="form-group profile-image-section-modal">
                                    <div class="image-preview-modal">
                                        <img src="https://ui-avatars.com/api/?name=New+User&size=150&background=028090&color=fff" 
                                             alt="Profile Image" id="imagePreviewNew" class="profile-preview-modal">
                                        <div class="image-overlay-modal">
                                            <i class="bi bi-camera"></i>
                                            <span>Upload Photo</span>
                                        </div>
                                        <input type="file" name="profile_image_new" id="profileImageNew" accept="image/*" class="image-input-modal">
                                    </div>
                                    
                                    <div class="image-options-modal">
                                        <label class="checkbox-option">
                                            <input type="checkbox" name="use_gravatar_new" id="useGravatarNew" checked>
                                            <span>Use Gravatar</span>
                                        </label>
                                        <small class="image-hint-modal">
                                            Upload JPG, PNG or GIF (max 2MB)<br>
                                            Or use Gravatar with email
                                        </small>
                                    </div>
                                </div>
                                
                                <!-- Form Fields -->
                                <div class="form-fields-modal">
                                    <div class="form-row-modal">
                                        <div class="form-group">
                                            <label for="new_name">Full Name *</label>
                                            <input type="text" id="new_name" name="new_name" class="form-control" required minlength="2">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="new_email">Email Address *</label>
                                            <input type="email" id="new_email" name="new_email" class="form-control" required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-row-modal">
                                        <div class="form-group">
                                            <label for="new_role">Role *</label>
                                            <select id="new_role" name="new_role" class="form-control" required>
                                                <option value="user" selected>User</option>
                                                <option value="admin">Admin</option>
                                                <option value="superadmin">Superadmin</option>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="new_status">Status *</label>
                                            <select id="new_status" name="new_status" class="form-control" required>
                                                <option value="active" selected>Active</option>
                                                <option value="inactive">Inactive</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="form-row-modal">
                                        <div class="form-group">
                                            <label for="new_password">Password *</label>
                                            <div class="password-wrapper">
                                                <input type="password" id="new_password" name="new_password" class="form-control" required>
                                                <button type="button" class="password-toggle">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                            <div id="passwordStrengthNew"></div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="confirm_password">Confirm Password *</label>
                                            <div class="password-wrapper">
                                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                                                <button type="button" class="password-toggle">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                            <div id="passwordMatchNew"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="password-requirements-modal">
                                        <h5>Password Requirements:</h5>
                                        <ul>
                                            <li id="reqLengthNew">
                                                <i class="bi bi-circle"></i>
                                                Minimum 8 characters
                                            </li>
                                            <li id="reqSpecialNew">
                                                <i class="bi bi-circle"></i>
                                                Contains special character (!@#$%^&*())
                                            </li>
                                            <li id="reqMatchNew">
                                                <i class="bi bi-circle"></i>
                                                Passwords match
                                            </li>
                                        </ul>
                                    </div>
                                    
                                    <div class="modal-actions">
                                        <button type="submit" name="add_user" class="btn btn-primary">
                                            <i class="bi bi-person-plus"></i> Add User
                                        </button>
                                        <button type="button" class="btn btn-secondary close-modal">
                                            <i class="bi bi-x-circle"></i> Cancel
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Edit User Modal (Superadmin Only) -->
            <?php if ($user_role === 'superadmin'): ?>
            <div id="editUserModal" class="modal-overlay">
                <div class="modal-content wide-modal">
                    <div class="modal-header">
                        <h3><i class="bi bi-pencil-square"></i> Edit User</h3>
                        <button class="close-modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="" enctype="multipart/form-data" id="editUserForm">
                            <input type="hidden" name="update_user_id" id="editUserId">
                            <input type="hidden" name="original_email" id="originalEmail">
                            <input type="hidden" name="current_profile_img" id="currentProfileImg">
                            
                            <div class="form-grid-modal">
                                <!-- Profile Image Section -->
                                <div class="form-group profile-image-section-modal">
                                    <div class="image-preview-modal">
                                        <img src="" alt="Profile Image" id="imagePreviewEdit" class="profile-preview-modal">
                                        <div class="image-overlay-modal">
                                            <i class="bi bi-camera"></i>
                                            <span>Change Photo</span>
                                        </div>
                                        <input type="file" name="profile_image_update" id="profileImageEdit" accept="image/*" class="image-input-modal">
                                    </div>
                                    
                                    <div class="image-options-modal">
                                        <label class="checkbox-option">
                                            <input type="checkbox" name="use_gravatar_update" id="useGravatarEdit">
                                            <span>Use Gravatar</span>
                                        </label>
                                        <small class="image-hint-modal">
                                            Upload JPG, PNG or GIF (max 2MB)<br>
                                            Or use Gravatar with email
                                        </small>
                                    </div>
                                </div>
                                
                                <!-- Form Fields -->
                                <div class="form-fields-modal">
                                    <div class="form-row-modal">
                                        <div class="form-group">
                                            <label for="editUserName">Full Name *</label>
                                            <input type="text" id="editUserName" name="update_name" class="form-control" required minlength="2">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="editUserEmail">Email Address *</label>
                                            <input type="email" id="editUserEmail" name="update_email" class="form-control" required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-row-modal">
                                        <div class="form-group">
                                            <label for="editUserRole">Role *</label>
                                            <select id="editUserRole" name="update_role" class="form-control" required>
                                                <option value="user">User</option>
                                                <option value="admin">Admin</option>
                                                <option value="superadmin">Superadmin</option>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="editUserStatus">Status *</label>
                                            <select id="editUserStatus" name="update_status" class="form-control" required>
                                                <option value="active">Active</option>
                                                <option value="inactive">Inactive</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <div class="checkbox-option">
                                            <input type="checkbox" id="changePasswordCheckbox">
                                            <label for="changePasswordCheckbox">Change Password</label>
                                        </div>
                                    </div>
                                    
                                    <div id="passwordFieldsEdit" style="display: none;">
                                        <div class="form-row-modal">
                                            <div class="form-group">
                                                <label for="update_password">New Password</label>
                                                <div class="password-wrapper">
                                                    <input type="password" id="update_password" name="update_password" class="form-control">
                                                    <button type="button" class="password-toggle">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                </div>
                                                <div id="passwordStrengthEdit"></div>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="update_confirm_password">Confirm Password</label>
                                                <div class="password-wrapper">
                                                    <input type="password" id="update_confirm_password" name="update_confirm_password" class="form-control">
                                                    <button type="button" class="password-toggle">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                </div>
                                                <div id="passwordMatchEdit"></div>
                                            </div>
                                        </div>
                                        
                                        <div class="password-requirements-modal">
                                            <h5>Password Requirements:</h5>
                                            <ul>
                                                <li id="reqLengthEdit">
                                                    <i class="bi bi-circle"></i>
                                                    Minimum 8 characters
                                                </li>
                                                <li id="reqSpecialEdit">
                                                    <i class="bi bi-circle"></i>
                                                    Contains special character (!@#$%^&*())
                                                </li>
                                                <li id="reqMatchEdit">
                                                    <i class="bi bi-circle"></i>
                                                    Passwords match
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <div class="modal-actions">
                                        <button type="submit" name="update_user_admin" class="btn btn-primary">
                                            <i class="bi bi-save"></i> Save Changes
                                        </button>
                                        <button type="button" class="btn btn-secondary close-modal">
                                            <i class="bi bi-x-circle"></i> Cancel
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Confirmation Modal -->
            <div id="confirmationModal" class="modal-overlay">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3><i class="bi bi-exclamation-triangle"></i> Confirmation</h3>
                        <button class="close-modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <p id="confirmationMessage">Are you sure you want to proceed?</p>
                    </div>
                    <div class="modal-actions">
                        <form method="POST" action="" id="confirmationForm">
                            <input type="hidden" name="delete_user_id" id="deleteUserId">
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-check-circle"></i> Confirm
                            </button>
                            <button type="button" class="btn btn-secondary close-modal">
                                <i class="bi bi-x-circle"></i> Cancel
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Notification Modal -->
            <?php if ($success_message || $error_message): ?>
            <div id="notificationModal" class="modal-overlay" style="display: flex;">
                <div class="modal-content notification-modal">
                    <div class="modal-header">
                        <h3>
                            <i class="bi <?php echo $success_message ? 'bi-check-circle text-success' : 'bi-exclamation-circle text-error'; ?>"></i>
                            <?php echo $success_message ? 'Success' : 'Error'; ?>
                        </h3>
                        <button class="close-modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <p><?php echo $success_message ?: $error_message; ?></p>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-primary close-modal">
                            <i class="bi bi-check-circle"></i> OK
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
        </main>
    </div>
    
    <!-- JavaScript Files -->
    <script src="js/profile.js"></script>
</body>
</html>