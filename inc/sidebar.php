<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$nav_items = [];

if ($user_role === 'user') {
    $nav_items = [
        ['icon' => 'bi-speedometer2', 'text' => 'Dashboard',  'link' => 'dashboard.php'],
        ['icon' => 'bi-person',       'text' => 'Profile',    'link' => 'profile.php'],
        ['icon' => 'bi-files',        'text' => 'My Files',   'link' => 'documents.php'],
        ['icon' => 'bi-chat',         'text' => 'Feedback',   'link' => 'feedback.php'],
    ];
} elseif ($user_role === 'admin') {
    $nav_items = [
        ['icon' => 'bi-speedometer2', 'text' => 'Dashboard',  'link' => 'dashboard.php'],
        ['icon' => 'bi-person',       'text' => 'Profile',    'link' => 'profile.php'],
        ['icon' => 'bi-files',        'text' => 'File List',  'link' => 'documents.php'],
        ['icon' => 'bi-upload',       'text' => 'Upload',     'link' => 'upload.php'],
        ['icon' => 'bi-people',       'text' => 'User List',  'link' => 'userlist.php'],
        ['icon' => 'bi-book',         'text' => 'Classes',    'link' => 'class.php'],
        ['icon' => 'bi-chat',         'text' => 'Feedback',   'link' => 'feedback.php'],
    ];
} elseif ($user_role === 'superadmin') {
    $nav_items = [
        ['icon' => 'bi-speedometer2', 'text' => 'Dashboard',      'link' => 'dashboard.php'],
        ['icon' => 'bi-person',       'text' => 'Profile',        'link' => 'profile.php'],
        ['icon' => 'bi-files',        'text' => 'File List',      'link' => 'documents.php'],
        ['icon' => 'bi-upload',       'text' => 'Upload',         'link' => 'upload.php'],
        ['icon' => 'bi-people',       'text' => 'User List',      'link' => 'userlist.php'],
        ['icon' => 'bi-book',         'text' => 'Classes',        'link' => 'class.php'],
        ['icon' => 'bi-chat',         'text' => 'Feedback',       'link' => 'feedback.php'],
        ['icon' => 'bi-clock-history','text' => 'Activity Trail', 'link' => 'activitytrail.php'],
        ['icon' => 'bi-bar-chart',    'text' => 'Reports',        'link' => 'report.php'],
    ];
}
?>
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
<button class="mobile-menu-toggle" id="mobileMenuToggle">
    <i class="bi bi-list"></i>
</button>