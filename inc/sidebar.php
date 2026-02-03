<?php
// inc/sidebar.php
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Define navigation items based on role
$nav_items = [];

if ($user_role === 'user') {
    $nav_items = [
        ['icon' => 'bi-person', 'text' => 'Profile', 'link' => 'profile.php'],
        ['icon' => 'bi-list', 'text' => 'Files', 'link' => 'files.php'],
        ['icon' => 'bi-chat', 'text' => 'Feedback', 'link' => 'feedback.php'],
    ];
} elseif ($user_role === 'admin') {
    $nav_items = [
        ['icon' => 'bi-person', 'text' => 'Profile', 'link' => 'profile.php'],
        ['icon' => 'bi-files', 'text' => 'File List', 'link' => 'files.php'],
        ['icon' => 'bi-upload', 'text' => 'Upload', 'link' => 'upload.php'],
        ['icon' => 'bi-people', 'text' => 'User List', 'link' => 'user_list.php'],
        ['icon' => 'bi-chat', 'text' => 'Feedback', 'link' => 'feedback.php'],

    ];
} elseif ($user_role === 'superadmin') {
    $nav_items = [
        ['icon' => 'bi-person', 'text' => 'Profile', 'link' => 'profile.php'],
        ['icon' => 'bi-files', 'text' => 'File List', 'link' => 'files.php'],
        ['icon' => 'bi-chat', 'text' => 'Feedback', 'link' => 'feedback.php'],
    ];
}
?>
<!-- Glassmorphism Sidebar -->
<aside class="sidebar collapsed">
    <div class="nav-header">
        <button class="toggle-btn" id="sidebarToggle">
            <i class="bi bi-list"></i>
        </button>
    </div>
    
    <ul class="nav-menu">
        <?php foreach ($nav_items as $item): ?>
        <li class="nav-item">
            <a href="<?php echo $item['link']; ?>" class="nav-link">
                <i class="bi <?php echo $item['icon']; ?> nav-icon"></i>
                <span class="nav-text"><?php echo $item['text']; ?></span>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
</aside>

<!-- Mobile Menu Toggle -->
<button class="mobile-menu-toggle" id="mobileMenuToggle">
    <i class="bi bi-list"></i>
</button>