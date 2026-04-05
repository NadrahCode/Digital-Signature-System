<?php
session_start();
require('inc/db_config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: dashboard.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];
$user_email = $_SESSION['email'];
$user_role = $_SESSION['role'];
$profile_img = $_SESSION['profile_img'] ?? '';

// Get filter parameters
$filter_user = isset($_GET['filter_user']) ? intval($_GET['filter_user']) : 0;
$filter_action = isset($_GET['filter_action']) ? $_GET['filter_action'] : '';
$filter_date = isset($_GET['filter_date']) ? $connect->real_escape_string($_GET['filter_date']) : '';

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 10;

// If user is selected, get name/email for queries filtering
$selected_user_name = '';
$selected_user_email = '';
if ($filter_user > 0) {
    $user_info_query = "SELECT name, email FROM users WHERE user_id = $filter_user";
    $user_info_result = $connect->query($user_info_query);
    if ($user_info_result && $user_info_result->num_rows > 0) {
        $user_info = $user_info_result->fetch_assoc();
        $selected_user_name = $connect->real_escape_string($user_info['name']);
        $selected_user_email = $connect->real_escape_string($user_info['email']);
    }
}

// Helper function to add WHERE clause
function addWhere(&$query, $conditions) {
    if (!empty($conditions)) {
        $query .= " WHERE " . implode(' AND ', $conditions);
    }
}

// Build queries
$upload_query = "SELECT 
    d.created_at as activity_time,
    'document_upload' as action_type,
    'Document Upload' as action_name,
    d.created_by as user_id,
    u.name as user_name,
    u.role as user_role,
    CONCAT('Uploaded: ', d.doc_name) as details,
    d.id as reference_id
FROM documents d
LEFT JOIN users u ON d.created_by = u.user_id";
$upload_conditions = [];
if ($filter_user > 0) $upload_conditions[] = "d.created_by = $filter_user";
if ($filter_date) $upload_conditions[] = "DATE(d.created_at) = '$filter_date'";
addWhere($upload_query, $upload_conditions);

$send_query = "SELECT 
    dr.sent_at as activity_time,
    'document_send' as action_type,
    'Document Sent' as action_name,
    d.created_by as user_id,
    u.name as user_name,
    u.role as user_role,
    CONCAT('Sent: ', d.doc_name, ' to user') as details,
    dr.id as reference_id
FROM document_recipients dr
INNER JOIN documents d ON dr.document_id = d.id
LEFT JOIN users u ON d.created_by = u.user_id";
$send_conditions = [];
if ($filter_user > 0) $send_conditions[] = "d.created_by = $filter_user";
if ($filter_date) $send_conditions[] = "DATE(dr.sent_at) = '$filter_date'";
addWhere($send_query, $send_conditions);

$view_query = "SELECT 
    dr.viewed_at as activity_time,
    'document_view' as action_type,
    'Document Viewed' as action_name,
    dr.user_id,
    u.name as user_name,
    u.role as user_role,
    CONCAT('Viewed: ', d.doc_name) as details,
    dr.id as reference_id
FROM document_recipients dr
INNER JOIN documents d ON dr.document_id = d.id
LEFT JOIN users u ON dr.user_id = u.user_id";
$view_conditions = [];
if ($filter_user > 0) $view_conditions[] = "dr.user_id = $filter_user";
if ($filter_date) $view_conditions[] = "DATE(dr.viewed_at) = '$filter_date'";
addWhere($view_query, $view_conditions);

$download_query = "SELECT 
    dr.downloaded_at as activity_time,
    'document_download' as action_type,
    'Document Downloaded' as action_name,
    dr.user_id,
    u.name as user_name,
    u.role as user_role,
    CONCAT('Downloaded: ', d.doc_name) as details,
    dr.id as reference_id
FROM document_recipients dr
INNER JOIN documents d ON dr.document_id = d.id
LEFT JOIN users u ON dr.user_id = u.user_id";
$download_conditions = [];
if ($filter_user > 0) $download_conditions[] = "dr.user_id = $filter_user";
if ($filter_date) $download_conditions[] = "DATE(dr.downloaded_at) = '$filter_date'";
addWhere($download_query, $download_conditions);

$class_query = "SELECT 
    c.created_at as activity_time,
    'class_create' as action_type,
    'Class Created' as action_name,
    c.created_by as user_id,
    u.name as user_name,
    u.role as user_role,
    CONCAT('Created class: ', c.class_name) as details,
    c.class_id as reference_id
FROM classes c
LEFT JOIN users u ON c.created_by = u.user_id";
$class_conditions = [];
if ($filter_user > 0) $class_conditions[] = "c.created_by = $filter_user";
if ($filter_date) $class_conditions[] = "DATE(c.created_at) = '$filter_date'";
addWhere($class_query, $class_conditions);

$query_submit = "SELECT 
    q.created_at as activity_time,
    'query_submit' as action_type,
    'Query Submitted' as action_name,
    NULL as user_id,
    q.name as user_name,
    q.category as user_role,
    CONCAT('Submitted query: ', LEFT(q.message, 50), '...') as details,
    q.query_id as reference_id
FROM queries q";
$query_conditions = [];
if ($filter_user > 0 && !empty($selected_user_name)) {
    $query_conditions[] = "(q.name = '$selected_user_name' OR q.email = '$selected_user_email')";
}
if ($filter_date) $query_conditions[] = "DATE(q.created_at) = '$filter_date'";
addWhere($query_submit, $query_conditions);

$user_create = "SELECT 
    u.created_at as activity_time,
    'user_register' as action_type,
    'User Registered' as action_name,
    u.user_id,
    u.name as user_name,
    u.role as user_role,
    CONCAT('Registered as: ', u.role) as details,
    u.user_id as reference_id
FROM users u";
$user_conditions = [];
if ($filter_user > 0) $user_conditions[] = "u.user_id = $filter_user";
if ($filter_date) $user_conditions[] = "DATE(u.created_at) = '$filter_date'";
addWhere($user_create, $user_conditions);

// Execute all queries and collect activities
$all_activities = [];
$queries = [
    $upload_query, $send_query, $view_query, $download_query,
    $class_query, $query_submit, $user_create
];
foreach ($queries as $sql) {
    $result = $connect->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $all_activities[] = $row;
        }
    }
}

// Sort by time descending
usort($all_activities, function($a, $b) {
    return strtotime($b['activity_time']) - strtotime($a['activity_time']);
});

// Apply action filter
if ($filter_action) {
    $all_activities = array_filter($all_activities, function($row) use ($filter_action) {
        return $row['action_type'] === $filter_action;
    });
    $all_activities = array_values($all_activities); // reindex
}

// Export CSV if requested
if (isset($_GET['export']) && $_GET['export'] == '1') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="activity_trail_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    // Column headers
    fputcsv($output, ['Time', 'User', 'Role', 'Action', 'Details']);
    foreach ($all_activities as $row) {
        $time = date('Y-m-d H:i:s', strtotime($row['activity_time']));
        $user = $row['user_name'] ?? 'System';
        $role = $row['user_role'] ?? 'system';
        $action = $row['action_name'];
        $details = $row['details'];
        fputcsv($output, [$time, $user, $role, $action, $details]);
    }
    fclose($output);
    exit;
}

// Pagination
$total_records = count($all_activities);
$total_pages = ceil($total_records / $perPage);
$offset = ($page - 1) * $perPage;
$paginated_activities = array_slice($all_activities, $offset, $perPage);

// Get users for filter dropdown
$users_result = $connect->query("SELECT user_id, name, role FROM users ORDER BY name");

// Statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM documents WHERE created_by IS NOT NULL) as total_uploads,
    (SELECT COUNT(*) FROM document_recipients WHERE sent_at IS NOT NULL) as total_sends,
    (SELECT COUNT(*) FROM document_recipients WHERE viewed_at IS NOT NULL) as total_views,
    (SELECT COUNT(*) FROM document_recipients WHERE downloaded_at IS NOT NULL) as total_downloads,
    (SELECT COUNT(*) FROM classes WHERE created_by IS NOT NULL) as total_classes,
    (SELECT COUNT(*) FROM queries) as total_queries,
    (SELECT COUNT(*) FROM users) as total_users";
$stats = $connect->query($stats_query)->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Activity Trail | Digital Signature System</title>
<?php require('inc/links.php'); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="css/design.css">
<link rel="stylesheet" href="css/dashboard.css">
<style>
/* Your existing styles... */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.stat-card {
    background: linear-gradient(135deg, #028090, #114B2F);
    color: white;
    padding: 25px;
    border-radius: 12px;
    text-align: center;
}
.stat-card .number {
    font-size: 36px;
    font-weight: bold;
    margin-bottom: 8px;
}
.stat-card .label {
    font-size: 14px;
    opacity: 0.9;
}
.filter-bar {
    background: var(--card-bg);
    padding: 20px;
    border-radius: 10px;
    border: 2px solid var(--sidebar-border);
    margin-bottom: 25px;
}
.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    align-items: end;
}
.filter-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--text-primary);
    font-size: 14px;
}
.filter-group select,
.filter-group input {
    width: 100%;
    padding: 10px;
    border: 2px solid var(--sidebar-border);
    border-radius: 8px;
    background: var(--card-bg);
    color: var(--text-primary);
    font-size: 14px;
}
.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-block;
}
.btn-primary {
    background: linear-gradient(135deg, #028090, #114B2F);
    color: white;
}
.btn-secondary {
    background: #6c757d;
    color: white;
}
.btn-success {
    background: #28a745;
    color: white;
}
.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 3px 10px rgba(0,0,0,0.2);
}
.table-container {
    overflow-x: auto;
    border-radius: 10px;
    border: 1px solid var(--sidebar-border);
    background: var(--card-bg);
    margin-bottom: 20px;
}
table {
    width: 100%;
    border-collapse: collapse;
}
thead {
    background: linear-gradient(135deg, #028090, #114B2F);
    color: white;
}
th {
    padding: 15px;
    text-align: left;
    font-weight: 600;
    font-size: 14px;
}
td {
    padding: 15px;
    border-bottom: 1px solid var(--sidebar-border);
    color: var(--text-primary);
}
tr:hover {
    background: var(--table-bg);
}
.action-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.action-upload {
    background: #e7f3ff;
    color: #0066cc;
}
.action-send {
    background: #fff3cd;
    color: #856404;
}
.action-view {
    background: #d1ecf1;
    color: #0c5460;
}
.action-download {
    background: #d4edda;
    color: #155724;
}
.user-info {
    display: flex;
    align-items: center;
    gap: 10px;
}
.user-avatar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background: linear-gradient(135deg, #028090, #114B2F);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
}
.role-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
    margin-left: 8px;
}
.role-admin {
    background: #ffc107;
    color: #000;
}
.role-user {
    background: #6c757d;
    color: white;
}
.role-superadmin {
    background: #dc3545;
    color: white;
}
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-secondary);
}
.empty-state .icon {
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.5;
}
.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 20px;
}
.pagination a, .pagination span {
    padding: 8px 12px;
    border-radius: 6px;
    text-decoration: none;
    color: var(--text-primary);
    background: var(--card-bg);
    border: 1px solid var(--sidebar-border);
    transition: 0.3s;
}
.pagination a:hover {
    background: var(--sidebar-border);
}
.pagination .active {
    background: linear-gradient(135deg, #028090, #114B2F);
    color: white;
    border: none;
}
.export-btn {
    margin-bottom: 20px;
    text-align: right;
}
</style>
</head>
<body data-theme="light">
<div class="dashboard-container">
    <?php require('inc/sidebar.php'); ?>
    
    <main class="main-content">
        <?php require('inc/topheader.php'); ?>
        
        <section class="welcome-section">
            <h1 class="welcome-title">Activity Trail 📊</h1>
            <h2 class="system-title">DIGITAL SIGNATURE SYSTEM</h2>
            <div class="logo-container">
                <a href="dashboard.php">
                    <img src="images/logo-main.png" alt="Digital Signature System Logo" class="system-logo">
                </a>
            </div>
        </section>
        
        <div style="max-width:1400px; margin:0 auto; padding:30px;">
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="number"><?php echo $stats['total_uploads']; ?></div>
                    <div class="label">Documents Uploaded</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?php echo $stats['total_sends']; ?></div>
                    <div class="label">Documents Sent</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?php echo $stats['total_downloads']; ?></div>
                    <div class="label">Documents Downloaded</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?php echo $stats['total_classes']; ?></div>
                    <div class="label">Classes Created</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?php echo $stats['total_queries']; ?></div>
                    <div class="label">Queries Submitted</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?php echo $stats['total_users']; ?></div>
                    <div class="label">Users Registered</div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filter-bar">
                <form method="get" action="">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label>👤 Filter by User</label>
                            <select name="filter_user">
                                <option value="">All Users</option>
                                <?php while ($user = $users_result->fetch_assoc()): ?>
                                    <option value="<?php echo $user['user_id']; ?>" <?php echo ($filter_user == $user['user_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['name']); ?> (<?php echo $user['role']; ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>🎯 Filter by Action</label>
                            <select name="filter_action">
                                <option value="">All Actions</option>
                                <option value="document_upload" <?php echo ($filter_action == 'document_upload') ? 'selected' : ''; ?>>Document Upload</option>
                                <option value="document_send" <?php echo ($filter_action == 'document_send') ? 'selected' : ''; ?>>Document Send</option>
                                <option value="document_view" <?php echo ($filter_action == 'document_view') ? 'selected' : ''; ?>>Document View</option>
                                <option value="document_download" <?php echo ($filter_action == 'document_download') ? 'selected' : ''; ?>>Document Download</option>
                                <option value="class_create" <?php echo ($filter_action == 'class_create') ? 'selected' : ''; ?>>Class Created</option>
                                <option value="query_submit" <?php echo ($filter_action == 'query_submit') ? 'selected' : ''; ?>>Query Submitted</option>
                                <option value="user_register" <?php echo ($filter_action == 'user_register') ? 'selected' : ''; ?>>User Registered</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>📅 Filter by Date</label>
                            <input type="date" name="filter_date" value="<?php echo htmlspecialchars($filter_date); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <div style="display:flex; gap:10px;">
                                <button type="submit" class="btn btn-primary" style="flex:1;">Apply Filters</button>
                                <a href="activitytrail.php" class="btn btn-secondary">Clear</a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
           
            
            <!-- Activity Table -->
            <div class="table-container">
                <?php if (!empty($paginated_activities)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paginated_activities as $activity): ?>
                                <tr class="activity-row">
                                    <td>
                                        <div style="font-size:13px; color:var(--text-primary); font-weight:600;">
                                            <?php echo date('M j, Y', strtotime($activity['activity_time'])); ?>
                                        </div>
                                        <div style="font-size:11px; color:var(--text-secondary);">
                                            <?php echo date('g:i A', strtotime($activity['activity_time'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="user-info">
                                            <div class="user-avatar">
                                                <?php echo strtoupper(substr($activity['user_name'] ?? 'S', 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div style="font-weight:600; font-size:14px;">
                                                    <?php echo htmlspecialchars($activity['user_name'] ?? 'System'); ?>
                                                </div>
                                                <span class="role-badge role-<?php echo $activity['user_role'] ?? 'user'; ?>">
                                                    <?php echo strtoupper($activity['user_role'] ?? 'system'); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $badge_class = '';
                                        $icon = '';
                                        switch($activity['action_type']) {
                                            case 'document_upload':
                                                $badge_class = 'action-upload';
                                                $icon = '📤';
                                                break;
                                            case 'document_send':
                                                $badge_class = 'action-send';
                                                $icon = '📨';
                                                break;
                                            case 'document_view':
                                                $badge_class = 'action-view';
                                                $icon = '👁';
                                                break;
                                            case 'document_download':
                                                $badge_class = 'action-download';
                                                $icon = '⬇️';
                                                break;
                                            case 'class_create':
                                                $badge_class = 'action-upload';
                                                $icon = '📚';
                                                break;
                                            case 'query_submit':
                                                $badge_class = 'action-send';
                                                $icon = '💬';
                                                break;
                                            case 'user_register':
                                                $badge_class = 'action-download';
                                                $icon = '👤';
                                                break;
                                        }
                                        ?>
                                        <span class="action-badge <?php echo $badge_class; ?>">
                                            <?php echo $icon . ' ' . $activity['action_name']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="font-size:13px; color:var(--text-primary);">
                                            <?php echo htmlspecialchars($activity['details']); ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="icon">📊</div>
                        <h3 style="color:var(--text-primary); margin-bottom:10px;">No Activity Found</h3>
                        <p>There are no activities matching your filters.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">&laquo; Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next &raquo;</a>
                    <?php endif; ?>
                </div>
                        <!-- Export button -->
            <div class="export-btn">
                <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => '1', 'page' => null])); ?>" class="btn btn-success">
                    📥 Export to CSV
                </a>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script src="js/dashboard.js"></script>
</body>
</html>