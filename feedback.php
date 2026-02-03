<?php
// Start session and check login
session_start();
require('inc/db_config.php');

// First, let's add the category column if it doesn't exist
$check_column = "SHOW COLUMNS FROM queries LIKE 'category'";
$result = mysqli_query($connect, $check_column);

if (mysqli_num_rows($result) == 0) {
    // Add category column if it doesn't exist
    $add_column = "ALTER TABLE queries 
                   ADD COLUMN category VARCHAR(20) DEFAULT 'user' AFTER message";
    mysqli_query($connect, $add_column);
}

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user info
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];
$user_email = $_SESSION['email'];
$user_role = $_SESSION['role'];
$profile_img = $_SESSION['profile_img'] ?? '';

// Handle form submissions
$success_message = '';
$error_message = '';

// Only allow user and admin to submit feedback
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Submit new feedback (user and admin only)
    if (isset($_POST['submit_feedback']) && $user_role !== 'superadmin') {
        $subject = mysqli_real_escape_string($connect, $_POST['subject']);
        $message = mysqli_real_escape_string($connect, $_POST['message']);
        
        // Combine subject and message since queries table doesn't have subject column
        $full_message = $subject . ": " . $message;
        
        // Insert into queries table WITH category
        $query = "INSERT INTO queries (name, email, message, category, status) 
                  VALUES (?, ?, ?, ?, 'new')";
        
        if ($stmt = mysqli_prepare($connect, $query)) {
            mysqli_stmt_bind_param($stmt, "ssss", $user_name, $user_email, $full_message, $user_role);
            if (mysqli_stmt_execute($stmt)) {
                $success_message = "Feedback submitted successfully!";
            } else {
                $error_message = "Error submitting feedback: " . mysqli_error($connect);
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    // Update feedback status (superadmin only)
    if (isset($_POST['update_status']) && $user_role === 'superadmin') {
        $query_id = intval($_POST['query_id']);
        $status = mysqli_real_escape_string($connect, $_POST['status']);
        
        $query = "UPDATE queries SET status = ? WHERE query_id = ?";
        
        if ($stmt = mysqli_prepare($connect, $query)) {
            mysqli_stmt_bind_param($stmt, "si", $status, $query_id);
            if (mysqli_stmt_execute($stmt)) {
                $success_message = "Feedback status updated successfully!";
            } else {
                $error_message = "Error updating feedback: " . mysqli_error($connect);
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    // Delete feedback (superadmin only)
    if (isset($_POST['delete_feedback']) && $user_role === 'superadmin') {
        $query_id = intval($_POST['query_id']);
        
        $query = "DELETE FROM queries WHERE query_id = ?";
        
        if ($stmt = mysqli_prepare($connect, $query)) {
            mysqli_stmt_bind_param($stmt, "i", $query_id);
            if (mysqli_stmt_execute($stmt)) {
                $success_message = "Feedback deleted successfully!";
            } else {
                $error_message = "Error deleting feedback: " . mysqli_error($connect);
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Fetch feedback data based on role
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($connect, $_GET['search']) : '';
$feedback_data = [];

try {
    if ($user_role === 'superadmin') {
        // Superadmin can see all feedback
        $query = "SELECT * FROM queries";
        if ($search_query) {
            $query .= " WHERE name LIKE ? OR email LIKE ? OR message LIKE ? OR category LIKE ?";
            $search_param = "%$search_query%";
        }
        $query .= " ORDER BY created_at DESC";
        
        $stmt = mysqli_prepare($connect, $query);
        if ($search_query) {
            mysqli_stmt_bind_param($stmt, "ssss", $search_param, $search_param, $search_param, $search_param);
        }
    } else {
        // User and Admin can only see their own feedback
        $query = "SELECT * FROM queries WHERE email = ?";
        if ($search_query) {
            $query .= " AND (name LIKE ? OR email LIKE ? OR message LIKE ? OR category LIKE ?)";
        }
        $query .= " ORDER BY created_at DESC";
        
        $stmt = mysqli_prepare($connect, $query);
        if ($search_query) {
            $search_param = "%$search_query%";
            mysqli_stmt_bind_param($stmt, "sssss", $user_email, $search_param, $search_param, $search_param, $search_param);
        } else {
            mysqli_stmt_bind_param($stmt, "s", $user_email);
        }
    }
    
    if (isset($stmt)) {
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $feedback_data[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
    
} catch (Exception $e) {
    error_log("Feedback error: " . $e->getMessage());
    $error_message = "Error fetching feedback data.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback | DIGITAL SIGNATURE SYSTEM</title>
    
    <!-- Include external links -->
    <?php require('inc/links.php'); ?>
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Combined CSS -->
    <link rel="stylesheet" href="css/design.css">
    
    <!-- Dashboard Specific CSS -->
    <link rel="stylesheet" href="css/dashboard.css">
    
    <!-- Feedback Specific CSS -->
    <link rel="stylesheet" href="css/feedback.css">
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
            
            <!-- Welcome Section with Clickable Logo -->
            <section class="welcome-section">
                <h1 class="welcome-title">
                    <?php echo $user_role === 'superadmin' ? 'Manage Feedback' : 'Feedback & Inquiries'; ?>
                </h1>
                <h2 class="system-title">
                    <?php echo $user_role === 'superadmin' ? 'View and manage user feedback' : 'Submit and track your feedback'; ?>
                </h2>
                <div class="logo-container">
                    <a href="dashboard.php" class="logo-link">
                        <img src="images/logo-main.png" alt="Digital Signature System Logo" class="system-logo">
                    </a>
                </div>
            </section>
            
            <!-- Success/Error Messages -->
            <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill"></i> <?php echo $success_message; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="bi bi-exclamation-circle-fill"></i> <?php echo $error_message; ?>
            </div>
            <?php endif; ?>
            
            <!-- Feedback Form Section (Only for user and admin) -->
            <?php if ($user_role !== 'superadmin'): ?>
            <section class="feedback-form-section">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="bi bi-chat-left-text"></i> Submit New Feedback
                    </h3>
                    <div class="form-info">
                        <span class="category-badge category-<?php echo $user_role; ?>">
                            <?php echo ucfirst($user_role); ?> Feedback
                        </span>
                    </div>
                </div>
                
                <form method="POST" action="" class="feedback-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="subject">
                                <i class="bi bi-card-heading"></i> Subject *
                            </label>
                            <input type="text" id="subject" name="subject" class="form-control" required 
                                   placeholder="Enter feedback subject">
                        </div>
                        <div class="form-group">
                            <label for="email">
                                <i class="bi bi-envelope"></i> Your Email
                            </label>
                            <input type="email" id="email" class="form-control" value="<?php echo htmlspecialchars($user_email); ?>" 
                                   disabled>
                            <small>Email is automatically set from your account</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">
                            <i class="bi bi-chat-square-text"></i> Message *
                        </label>
                        <textarea id="message" name="message" class="form-control" required 
                                  rows="5" placeholder="Describe your feedback or inquiry in detail"></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="submit_feedback" class="btn btn-primary">
                            <i class="bi bi-send-fill"></i> Submit Feedback
                        </button>
                    </div>
                </form>
            </section>
            <?php endif; ?>
            
            <!-- Feedback Table Section -->
            <section class="feedback-table-section">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="bi bi-clock-history"></i> 
                        <?php echo $user_role === 'superadmin' ? 'All Feedback Records' : 'My Feedback History'; ?>
                    </h3>
                    
                    <div class="table-controls">
                        <form method="GET" action="" class="search-form">
                            <div class="search-box">
                                <i class="bi bi-search"></i>
                                <input type="text" name="search" placeholder="Search feedback..." 
                                       value="<?php echo htmlspecialchars($search_query); ?>">
                                <button type="submit" class="search-btn">Search</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php if (empty($feedback_data)): ?>
                <div class="no-data">
                    <i class="bi bi-chat-square"></i>
                    <h4>No Feedback Found</h4>
                    <p>
                        <?php echo $user_role === 'superadmin' 
                            ? 'No feedback has been submitted yet.' 
                            : ($search_query ? 'Try a different search term' : 'Submit your first feedback above'); ?>
                    </p>
                </div>
                <?php else: ?>
                <div class="table-container">
                    <table class="data-table feedback-table">
                        <thead>
                            <tr>
                                <th data-sort="date">Date</th>
                                <?php if ($user_role === 'superadmin'): ?>
                                <th data-sort="name">Sender</th>
                                <th data-sort="email">Email</th>
                                <?php endif; ?>
                                <th data-sort="message">Message</th>
                                <th data-sort="category">Category</th>
                                <th data-sort="status">Status</th>
                                <?php if ($user_role === 'superadmin'): ?>
                                <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feedback_data as $feedback): ?>
                            <tr>
                                <td>
                                    <?php echo date('M d, Y', strtotime($feedback['created_at'])); ?>
                                    <br>
                                    <small><?php echo date('h:i A', strtotime($feedback['created_at'])); ?></small>
                                </td>
                                
                                <?php if ($user_role === 'superadmin'): ?>
                                <td>
                                    <?php echo htmlspecialchars($feedback['name']); ?>
                                    <br>
                                    <small><?php echo ucfirst($feedback['category'] ?? 'user'); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($feedback['email']); ?></td>
                                <?php endif; ?>
                                
                                <td><?php echo htmlspecialchars($feedback['message']); ?></td>
                                
                                <td>
                                    <?php 
                                    $feedback_category = $feedback['category'] ?? 'user';
                                    $category_class = 'category-' . strtolower($feedback_category);
                                    ?>
                                    <span class="category-badge <?php echo $category_class; ?>">
                                        <?php echo ucfirst($feedback_category); ?>
                                    </span>
                                </td>
                                
                                <td>
                                    <?php 
                                    $status_class = 'status-' . strtolower($feedback['status']);
                                    ?>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo ucfirst($feedback['status']); ?>
                                    </span>
                                </td>
                                
                                <?php if ($user_role === 'superadmin'): ?>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn btn-edit" 
                                                data-query-id="<?php echo $feedback['query_id']; ?>"
                                                data-current-status="<?php echo $feedback['status']; ?>">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        
                                        <form method="POST" action="" class="delete-form"
                                              onsubmit="return confirm('Are you sure you want to delete this feedback?');">
                                            <input type="hidden" name="query_id" value="<?php echo $feedback['query_id']; ?>">
                                            <button type="submit" name="delete_feedback" class="action-btn btn-delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </section>
            
            <!-- Status Edit Modal (Superadmin only) -->
            <?php if ($user_role === 'superadmin'): ?>
            <div id="statusModal" class="modal-overlay">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Update Feedback Status</h3>
                        <button class="close-modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form id="statusForm" method="POST" action="">
                            <input type="hidden" name="query_id" id="modalQueryId">
                            
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select name="status" id="modalStatus" class="form-control" required>
                                    <option value="new">New</option>
                                    <option value="read">Read</option>
                                    <option value="resolved">Resolved</option>
                                </select>
                            </div>
                            
                            <div class="modal-actions">
                                <button type="submit" name="update_status" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Update Status
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="closeStatusModal()">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
        </main>
    </div>
    
    <!-- JavaScript for Feedback Page -->
    <script src="js/feedback.js"></script>
</body>
</html>