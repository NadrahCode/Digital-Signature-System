<?php
// inc/topheader.php
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Function to get profile image URL
function getProfileImageUrl($profile_img, $email, $name) {
    if (!empty($profile_img)) {
        // Check if it's a full URL or just filename
        if (filter_var($profile_img, FILTER_VALIDATE_URL)) {
            return $profile_img;
        } else {
            // Check if file exists
            $file_path = 'uploads/profile/' . $profile_img;
            if (file_exists($file_path)) {
                return $file_path;
            } else {
                // Fallback to Gravatar if file doesn't exist
                $gravatar_hash = md5(strtolower(trim($email)));
                return "https://www.gravatar.com/avatar/{$gravatar_hash}?s=40&d=identicon";
            }
        }
    } else {
        // Use Gravatar
        $gravatar_hash = md5(strtolower(trim($email)));
        return "https://www.gravatar.com/avatar/{$gravatar_hash}?s=40&d=identicon";
    }
}

// Get the profile image URL
$profile_image_url = getProfileImageUrl($profile_img, $user_email, $user_name);
?>

<!-- Top Header -->
<header class="top-header">
    <div class="user-info">
        <img src="<?php echo $profile_image_url; ?>" 
             alt="User Avatar" class="user-avatar" 
             onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($user_name); ?>&background=028090&color=fff'">
        <div>
            <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
            <span class="role-badge"><?php echo ucfirst($user_role); ?></span>
        </div>
    </div>
    
    <div class="header-controls">
        <!-- Theme Toggle -->
        <label class="theme-toggle">
            <input type="checkbox" id="themeToggle">
            <span class="theme-slider"></span>
        </label>
        
        <!-- Logout Button -->
        <a href="logout.php" class="logout-btn">
            <i class="bi bi-box-arrow-right"></i>
            Logout
        </a>
    </div>
</header>