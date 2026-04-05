<?php
// Start session and check login
session_start();
require('inc/db_config.php');

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

// Define dashboard cards based on role
$dashboard_cards = [];

if ($user_role === 'user') {
    $dashboard_cards = [
        ['icon' => 'bi-person', 'title' => 'My Profile', 'class' => 'card-profile', 'link' => 'profile.php'],
        ['icon' => 'bi-files', 'title' => 'My Files', 'class' => 'card-list', 'link' => 'documents.php'],
        ['icon' => 'bi-chat', 'title' => 'Send Feedback', 'class' => 'card-feedback', 'link' => 'feedback.php'],
    ];
} elseif ($user_role === 'admin') {
    $dashboard_cards = [
        ['icon' => 'bi-person', 'title' => 'My Profile', 'class' => 'card-profile', 'link' => 'profile.php'],
        ['icon' => 'bi-files', 'title' => 'My Files', 'class' => 'card-files', 'link' => 'documents.php'],
        ['icon' => 'bi-chat', 'title' => 'Send Feedback', 'class' => 'card-feedback', 'link' => 'feedback.php'],
        ['icon' => 'bi-upload', 'title' => 'Upload Files', 'class' => 'card-upload', 'link' => 'upload.php'],
        ['icon' => 'bi-people', 'title' => 'Manage Users List', 'class' => 'card-users', 'link' => 'userlist.php'],
        ['icon' => 'bi-people', 'title' => 'Manage Class', 'class' => 'card-class', 'link' => 'class.php'],
    ];
} elseif ($user_role === 'superadmin') {
    $dashboard_cards = [
        ['icon' => 'bi-person', 'title' => 'Manage Profile', 'class' => 'card-profile', 'link' => 'profile.php'],
        ['icon' => 'bi-files', 'title' => 'Manage Files', 'class' => 'card-files', 'link' => 'documents.php'],
        ['icon' => 'bi-chat', 'title' => 'Manage Feedback', 'class' => 'card-feedback', 'link' => 'feedback.php'],
        ['icon' => 'bi-upload', 'title' => 'Upload Files', 'class' => 'card-upload', 'link' => 'upload.php'],
        ['icon' => 'bi-people', 'title' => 'Manage Users List', 'class' => 'card-users', 'link' => 'userlist.php'],
        ['icon' => 'bi-people', 'title' => 'Manage Class', 'class' => 'card-class', 'link' => 'class.php'],
        ['icon' => 'bi-upload', 'title' => 'Activity Trails', 'class' => 'card-upload', 'link' => 'activitytrail.php'],
        ['icon' => 'bi-upload', 'title' => 'Reports', 'class' => 'card-upload', 'link' => 'report.php'],
    ];
}

// Initialize user_stats with default values
$user_stats = [
    'total_users' => 0,
    'total_documents' => 0,
    'verified_documents' => 0,
    'new_queries' => 0
];

// Fetch data based on role
$latest_queries = [];
$latest_documents = [];

try {
    // Fetch latest queries (for admin/superadmin only)
    if ($user_role === 'admin' || $user_role === 'superadmin') {
        $query = "SELECT query_id, name, email, message, status, created_at 
                 FROM queries 
                 ORDER BY created_at DESC 
                 LIMIT 2";
        
        if ($result = mysqli_query($connect, $query)) {
            while ($row = mysqli_fetch_assoc($result)) {
                $latest_queries[] = $row;
            }
            mysqli_free_result($result);
        }
    }
    
    // Fetch latest documents
    if ($user_role === 'user') {
        $query = "SELECT d.id, d.doc_name as file_name, d.created_at as upload_date, 'signed' as signature_status
                 FROM documents d
                 INNER JOIN document_recipients dr ON d.id = dr.document_id
                 WHERE dr.user_id = ?
                 ORDER BY d.created_at DESC 
                 LIMIT 2";
        
        if ($stmt = mysqli_prepare($connect, $query)) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) {
                $latest_documents[] = $row;
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        $query = "SELECT d.id, d.doc_name as file_name, d.created_at as upload_date, 
                         'signed' as signature_status, u.name as user_name
                 FROM documents d
                 LEFT JOIN users u ON d.created_by = u.user_id
                 ORDER BY d.created_at DESC 
                 LIMIT 2";
        
        if ($result = mysqli_query($connect, $query)) {
            while ($row = mysqli_fetch_assoc($result)) {
                $latest_documents[] = $row;
            }
            mysqli_free_result($result);
        }
    }
    
    // Fetch user statistics (only for admin/superadmin)
    if ($user_role === 'admin' || $user_role === 'superadmin') {
        $stats_query = "SELECT 
            (SELECT COUNT(*) FROM users WHERE status = 'active') as total_users,
            (SELECT COUNT(*) FROM documents) as total_documents,
            (SELECT COUNT(*) FROM document_recipients WHERE downloaded_at IS NOT NULL) as verified_documents,
            (SELECT COUNT(*) FROM queries WHERE status = 'new') as new_queries";
        
        if ($result = mysqli_query($connect, $stats_query)) {
            $fetched_stats = mysqli_fetch_assoc($result);
            if ($fetched_stats) {
                $user_stats = array_merge($user_stats, $fetched_stats);
            }
            mysqli_free_result($result);
        }
    }
    
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | DIGITAL SIGNATURE SYSTEM</title>
    
    <!-- Include external links -->
    <?php require('inc/links.php'); ?>
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Combined CSS -->
    <link rel="stylesheet" href="css/design.css">
    
    <!-- Dashboard Specific CSS -->
    <link rel="stylesheet" href="css/dashboard.css">
    

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
                <h1 class="welcome-title">Welcome Back, <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?>! 👋</h1>
                <h2 class="system-title">DIGITAL SIGNATURE SYSTEM</h2>
                <div class="logo-container">
                    <a href="dashboard.php">
                        <img src="images/logo-main.png" alt="Digital Signature System Logo" class="system-logo">
                    </a>
                </div>
            </section>
            
            <!-- Statistics Section (Admin only) -->
            <?php if ($user_role === 'admin'): ?>
            <section class="cards-section">
                <h3 class="section-title">System Statistics</h3>
                <div class="stats-cards">
                    <div class="stat-card">
                        <i class="bi bi-people" style="font-size: 2rem; color: #028090;"></i>
                        <div class="stat-number"><?php echo $user_stats['total_users'] ?? 0; ?></div>
                        <div class="stat-label">Active Users</div>
                    </div>
                    
                    <div class="stat-card">
                        <i class="bi bi-files" style="font-size: 2rem; color: #114B2F;"></i>
                        <div class="stat-number"><?php echo $user_stats['total_documents'] ?? 0; ?></div>
                        <div class="stat-label">Total Documents</div>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- Statistics Section (Superadmin only) -->
            <?php if ($user_role === 'superadmin'): ?>
            <section class="cards-section">
                <h3 class="section-title">System Statistics</h3>
                <div class="stats-cards">
                    <div class="stat-card">
                        <i class="bi bi-people" style="font-size: 2rem; color: #028090;"></i>
                        <div class="stat-number"><?php echo $user_stats['total_users'] ?? 0; ?></div>
                        <div class="stat-label">Active Users</div>
                    </div>
                    
                    <div class="stat-card">
                        <i class="bi bi-files" style="font-size: 2rem; color: #114B2F;"></i>
                        <div class="stat-number"><?php echo $user_stats['total_documents'] ?? 0; ?></div>
                        <div class="stat-label">Total Documents</div>
                    </div>
                    
                    <div class="stat-card">
                        <i class="bi bi-envelope" style="font-size: 2rem; color: #FF6B6B;"></i>
                        <div class="stat-number"><?php echo $user_stats['new_queries'] ?? 0; ?></div>
                        <div class="stat-label">New Queries</div>
                    </div>
                </div>
            </section>
            <?php endif; ?>
            
            <!-- Dashboard Cards -->
            <section class="cards-section">
                <h3 class="section-title">Quick Access</h3>
                <div class="cards-grid">
                    <?php foreach ($dashboard_cards as $card): ?>
                    <a href="<?php echo $card['link']; ?>" class="dashboard-card <?php echo $card['class']; ?>">
                        <div class="card-icon">
                            <i class="bi <?php echo $card['icon']; ?>"></i>
                        </div>
                        <h4 class="card-title"><?php echo $card['title']; ?></h4>
                    </a>
                    <?php endforeach; ?>
                </div>
            </section>
            
            <!-- Data Tables -->
            <section class="tables-section">
                <!-- Recent Documents Table -->
                <div class="table-container">
                    <h4 class="table-title">
                        Recent Documents
                        <button class="sort-btn" data-table="documents">
                            <i class="bi bi-sort-down"></i> Sort
                        </button>
                    </h4>
                    
                    <?php if (!empty($latest_documents)): ?>
                    <table class="data-table" id="documentsTable">
                        <thead>
                            <tr>
                                <?php if ($user_role === 'admin' || $user_role === 'superadmin'): ?>
                                <th data-sort="user">User</th>
                                <?php endif; ?>
                                <th data-sort="file">File Name</th>
                                <th data-sort="date">Upload Date</th>
                                <th data-sort="status">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($latest_documents as $doc): ?>
                            <tr>
                                <?php if ($user_role === 'admin' || $user_role === 'superadmin'): ?>
                                <td><?php echo htmlspecialchars($doc['user_name'] ?? 'You'); ?></td>
                                <?php endif; ?>
                                <td><?php echo htmlspecialchars($doc['file_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($doc['upload_date'])); ?></td>
                                <td>
                                    <?php
                                    $status_color = '';
                                    switch($doc['signature_status']) {
                                        case 'pending': $status_color = '#FFD166'; break;
                                        case 'signed': $status_color = '#4ECDC4'; break;
                                        case 'verified': $status_color = '#06D6A0'; break;
                                        default: $status_color = '#999';
                                    }
                                    ?>
                                    <span style="color: <?php echo $status_color; ?>">
                                        <?php echo ucfirst($doc['signature_status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="no-data">
                        <i class="bi bi-folder" style="font-size: 2rem; margin-bottom: 10px;"></i>
                        <p>No documents found</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Recent Queries Table (Admin/Superadmin only) -->
                <?php if (($user_role === 'admin' || $user_role === 'superadmin') && !empty($latest_queries)): ?>
                <div class="table-container">
                    <h4 class="table-title">
                        Recent Queries
                        <button class="sort-btn" data-table="queries">
                            <i class="bi bi-sort-down"></i> Sort
                        </button>
                    </h4>
                    
                    <table class="data-table" id="queriesTable">
                        <thead>
                            <tr>
                                <th data-sort="name">Name</th>
                                <th data-sort="email">Email</th>
                                <th data-sort="message">Message</th>
                                <th data-sort="date">Date</th>
                                <th data-sort="status">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($latest_queries as $query): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($query['name']); ?></td>
                                <td><?php echo htmlspecialchars($query['email']); ?></td>
                                <td><?php echo htmlspecialchars(substr($query['message'], 0, 50)) . '...'; ?></td>
                                <td><?php echo date('M d, Y', strtotime($query['created_at'])); ?></td>
                                <td>
                                    <?php
                                    $status_color = '';
                                    switch($query['status']) {
                                        case 'new': $status_color = '#FF6B6B'; break;
                                        case 'read': $status_color = '#FFD166'; break;
                                        case 'resolved': $status_color = '#06D6A0'; break;
                                        default: $status_color = '#999';
                                    }
                                    ?>
                                    <span style="color: <?php echo $status_color; ?>">
                                        <?php echo ucfirst($query['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </section>
            
        </main>
    </div>
    
    <!-- JavaScript for Dashboard -->
    <script src="js/dashboard.js"></script>
    <script>
    // Welcome toast after registration
    <?php if (isset($_GET['welcome'])): ?>
    window.addEventListener('DOMContentLoaded', function() {
        const toast = document.createElement('div');
        toast.innerHTML = '🎉 Welcome, <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?>! Your account is ready.';
        toast.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#028090;color:white;padding:14px 22px;border-radius:10px;font-size:14px;font-weight:600;z-index:9999;box-shadow:0 4px 16px rgba(0,0,0,0.2);animation:slideUp 0.4s ease;';
        document.body.appendChild(toast);
        setTimeout(() => { toast.style.opacity='0'; toast.style.transition='opacity 0.5s'; setTimeout(()=>toast.remove(),500); }, 4000);
    });
    <?php endif; ?>

    // Tutorial popup — show once per session
    window.addEventListener('DOMContentLoaded', function() {
        if (!sessionStorage.getItem('tutorialSeen')) {
            document.getElementById('tutorialOverlay').style.display = 'flex';
        }
    });
    function closeTutorial() {
        sessionStorage.setItem('tutorialSeen', '1');
        const el = document.getElementById('tutorialOverlay');
        el.style.opacity = '0';
        el.style.transition = 'opacity 0.3s';
        setTimeout(() => el.style.display = 'none', 300);
    }
    function tutorialNext() {
        const steps = document.querySelectorAll('.tutorial-step');
        const dots  = document.querySelectorAll('.tutorial-dot');
        let current = parseInt(document.getElementById('tutorialOverlay').dataset.step || 0);
        steps[current].style.display = 'none';
        dots[current].style.background = '#ccc';
        current = (current + 1) % steps.length;
        steps[current].style.display = 'block';
        dots[current].style.background = '#028090';
        document.getElementById('tutorialOverlay').dataset.step = current;
        document.getElementById('tutorialPrevBtn').style.display = current === 0 ? 'none' : 'inline-block';
        document.getElementById('tutorialNextBtn').textContent = current === steps.length - 1 ? 'Finish' : 'Next →';
        if (current === steps.length - 1) document.getElementById('tutorialNextBtn').onclick = closeTutorial;
        else document.getElementById('tutorialNextBtn').onclick = tutorialNext;
    }
    function tutorialPrev() {
        const steps = document.querySelectorAll('.tutorial-step');
        const dots  = document.querySelectorAll('.tutorial-dot');
        let current = parseInt(document.getElementById('tutorialOverlay').dataset.step || 0);
        steps[current].style.display = 'none';
        dots[current].style.background = '#ccc';
        current = Math.max(0, current - 1);
        steps[current].style.display = 'block';
        dots[current].style.background = '#028090';
        document.getElementById('tutorialOverlay').dataset.step = current;
        document.getElementById('tutorialPrevBtn').style.display = current === 0 ? 'none' : 'inline-block';
        document.getElementById('tutorialNextBtn').textContent = 'Next →';
        document.getElementById('tutorialNextBtn').onclick = tutorialNext;
    }
    </script>

    <!-- Tutorial Overlay -->
    <div id="tutorialOverlay" data-step="0" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:10000; align-items:center; justify-content:center;">
        <div style="background:var(--card-bg,#fff); border-radius:16px; padding:32px; max-width:460px; width:90%; box-shadow:0 16px 48px rgba(0,0,0,0.25); position:relative;">
            <button onclick="closeTutorial()" style="position:absolute; top:14px; right:16px; background:none; border:none; font-size:20px; cursor:pointer; color:var(--text-secondary);">✕</button>

            <!-- Step 1 -->
            <div class="tutorial-step">
                <div style="font-size:40px; margin-bottom:12px;">👋</div>
                <h3 style="margin:0 0 10px; font-size:20px; color:var(--text-primary);">Welcome to the Digital Signature System</h3>
                <p style="color:var(--text-secondary); font-size:14px; line-height:1.6;">This system lets admins upload, digitally sign, and distribute documents to students! while students can verify and download them securely.</p>
            </div>
            <!-- Step 2 -->
            <div class="tutorial-step" style="display:none;">
                <div style="font-size:40px; margin-bottom:12px;">📤</div>
                <h3 style="margin:0 0 10px; font-size:20px; color:var(--text-primary);">Uploading & Signing Documents</h3>
                <p style="color:var(--text-secondary); font-size:14px; line-height:1.6;">Admins go to <strong>Upload Files</strong> to create or upload. The system automatically generates a digital signature and embeds a QR code into the PDF.</p>
            </div>
            <!-- Step 3 -->
            <div class="tutorial-step" style="display:none;">
                <div style="font-size:40px; margin-bottom:12px;">📚</div>
                <h3 style="margin:0 0 10px; font-size:20px; color:var(--text-primary);">Sending to Classes</h3>
                <p style="color:var(--text-secondary); font-size:14px; line-height:1.6;">After uploading, use <strong>Send Document</strong> to distribute by class or to individual users. Students will see it appear in <strong>My Files</strong>.</p>
            </div>
            <!-- Step 4 -->
            <div class="tutorial-step" style="display:none;">
                <div style="font-size:40px; margin-bottom:12px;">🔍</div>
                <h3 style="margin:0 0 10px; font-size:20px; color:var(--text-primary);">Verifying Documents</h3>
                <p style="color:var(--text-secondary); font-size:14px; line-height:1.6;">Anyone can scan the QR code on a signed PDF to verify it is authentic and has not been tampered with — no login required.</p>
            </div>

            <!-- Dots -->
            <div style="display:flex; justify-content:center; gap:8px; margin:20px 0 24px;">
                <span class="tutorial-dot" style="width:8px;height:8px;border-radius:50%;background:#028090;display:inline-block;"></span>
                <span class="tutorial-dot" style="width:8px;height:8px;border-radius:50%;background:#ccc;display:inline-block;"></span>
                <span class="tutorial-dot" style="width:8px;height:8px;border-radius:50%;background:#ccc;display:inline-block;"></span>
                <span class="tutorial-dot" style="width:8px;height:8px;border-radius:50%;background:#ccc;display:inline-block;"></span>
            </div>

            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button id="tutorialPrevBtn" onclick="tutorialPrev()" style="display:none; padding:10px 20px; border:2px solid var(--sidebar-border,#ddd); border-radius:8px; background:transparent; cursor:pointer; font-size:14px; color:var(--text-primary);">← Back</button>
                <button onclick="closeTutorial()" style="padding:10px 20px; border:2px solid var(--sidebar-border,#ddd); border-radius:8px; background:transparent; cursor:pointer; font-size:14px; color:var(--text-secondary);">Skip</button>
                <button id="tutorialNextBtn" onclick="tutorialNext()" style="padding:10px 22px; border:none; border-radius:8px; background:linear-gradient(135deg,#028090,#114B2F); color:white; cursor:pointer; font-size:14px; font-weight:600;">Next →</button>
            </div>
        </div>
    </div>
    <style>
    @keyframes slideUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
    </style>
</body>
</html>