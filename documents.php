<?php
session_start();
require('inc/db_config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];
$user_email = $_SESSION['email'];
$user_role = $_SESSION['role'];
$profile_img = $_SESSION['profile_img'] ?? '';

$error = "";
$success = "";

// Handle delete action
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (in_array($user_role, ['admin', 'superadmin'])) {
        $doc_id = intval($_POST['doc_id']);
        
        // Get file name
        $result = $connect->query("SELECT file_name FROM documents WHERE id=$doc_id");
        if ($result && $result->num_rows > 0) {
            $doc = $result->fetch_assoc();
            $file_path = __DIR__ . "/uploads/" . $doc['file_name'];
            
            // Delete recipients first
            $connect->query("DELETE FROM document_recipients WHERE document_id=$doc_id");
            
            // Delete document record
            if ($connect->query("DELETE FROM documents WHERE id=$doc_id")) {
                // Delete physical file
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
                $success = "✅ Document deleted successfully!";
            } else {
                $error = "Failed to delete document.";
            }
        }
    }
}

// Get documents based on role
if ($user_role === 'user') {
    // Regular users see only documents sent to them
    $query = "SELECT d.*, 
              dr.sent_at, dr.viewed_at, dr.downloaded_at,
              (SELECT COUNT(*) FROM document_recipients WHERE document_id = d.id) as recipient_count
              FROM documents d
              INNER JOIN document_recipients dr ON d.id = dr.document_id
              WHERE dr.user_id = $user_id
              ORDER BY dr.sent_at DESC";
} else {
    // Admin and superadmin see all documents
    $query = "SELECT d.*,
              (SELECT COUNT(*) FROM document_recipients WHERE document_id = d.id) as recipient_count
              FROM documents d
              ORDER BY d.created_at DESC";
}

$documents_result = $connect->query($query);

// Get statistics
$stats = [];
if ($user_role === 'user') {
    $stats_query = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN viewed_at IS NOT NULL THEN 1 ELSE 0 END) as viewed,
        SUM(CASE WHEN downloaded_at IS NOT NULL THEN 1 ELSE 0 END) as downloaded
        FROM document_recipients WHERE user_id = $user_id";
} else {
    $stats_query = "SELECT 
        COUNT(*) as total,
        (SELECT COUNT(DISTINCT user_id) FROM document_recipients) as total_recipients,
        (SELECT COUNT(*) FROM document_recipients) as total_sent
        FROM documents";
}
$stats = $connect->query($stats_query)->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Documents | Digital Signature System</title>
<?php require('inc/links.php'); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="css/design.css">
<link rel="stylesheet" href="css/dashboard.css">
<style>
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
.toolbar {
    display: flex;
    gap: 15px;
    margin-bottom: 25px;
    flex-wrap: wrap;
    align-items: center;
}
.search-box {
    flex: 1;
    min-width: 250px;
}
.search-box input {
    width: 100%;
    padding: 12px 15px;
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
.btn-danger {
    background: #dc3545;
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
.doc-name {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}
.doc-meta {
    font-size: 12px;
    color: var(--text-secondary);
}
.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}
.status-new {
    background: #e7f3ff;
    color: #0066cc;
}
.status-viewed {
    background: #fff3cd;
    color: #856404;
}
.status-downloaded {
    background: #d4edda;
    color: #155724;
}
.action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}
.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}
.alert-success {
    background: #d4edda;
    color: #155724;
    border-left: 4px solid #28a745;
}
.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border-left: 4px solid #dc3545;
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
</style>
</head>
<body data-theme="light">
<div class="dashboard-container">
    <?php require('inc/sidebar.php'); ?>
    
    <main class="main-content">
        <?php require('inc/topheader.php'); ?>
        
        <section class="welcome-section">
            <h1 class="welcome-title">Documents 📋</h1>
            <h2 class="system-title">DIGITAL SIGNATURE SYSTEM</h2>
            <div class="logo-container">
                <a href="dashboard.php">
                    <img src="images/logo-main.png" alt="Digital Signature System Logo" class="system-logo">
                </a>
            </div>
        </section>
        
        <div style="max-width:1400px; margin:0 auto; padding:30px;">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="stats-grid">
                <?php if ($user_role === 'user'): ?>
                    <div class="stat-card">
                        <div class="number"><?php echo $stats['total']; ?></div>
                        <div class="label">Total Documents</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo $stats['viewed']; ?></div>
                        <div class="label">Viewed</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo $stats['downloaded']; ?></div>
                        <div class="label">Downloaded</div>
                    </div>
                <?php else: ?>
                    <div class="stat-card">
                        <div class="number"><?php echo $stats['total']; ?></div>
                        <div class="label">Total Documents</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo $stats['total_recipients']; ?></div>
                        <div class="label">Total Recipients</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo $stats['total_sent']; ?></div>
                        <div class="label">Documents Sent</div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="toolbar">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="🔍 Search documents..." onkeyup="searchDocuments()">
                </div>
                <?php if (in_array($user_role, ['admin', 'superadmin'])): ?>
                    <a href="upload.php" class="btn btn-primary">📤 Upload New Document</a>
                <?php endif; ?>
            </div>
            
            <div class="table-container">
                <?php if ($documents_result && $documents_result->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Document</th>
                                <?php if ($user_role === 'user'): ?>
                                    <th>Key ID</th>
                                    <th>Status</th>
                                    <th>Received</th>
                                <?php else: ?>
                                    <th>Key ID</th>
                                    <th>Recipients</th>
                                    <th>Created</th>
                                <?php endif; ?>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($doc = $documents_result->fetch_assoc()): ?>
                                <tr class="doc-row">
                                    <td>
                                        <div class="doc-name">📄 <?php echo htmlspecialchars($doc['doc_name']); ?></div>
                                        <?php if (!empty($doc['description'])): ?>
                                            <div class="doc-meta"><?php echo htmlspecialchars(substr($doc['description'], 0, 60)); ?><?php echo strlen($doc['description']) > 60 ? '...' : ''; ?></div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <?php if ($user_role === 'user'): ?>
                                        <td>
                                            <code style="background:var(--table-bg); padding:4px 8px; border-radius:4px; font-size:12px;">
                                                <?php echo htmlspecialchars($doc['key_id']); ?>
                                            </code>
                                        </td>
                                        <td>
                                            <?php if (!empty($doc['downloaded_at'])): ?>
                                                <span class="status-badge status-downloaded">✓ Downloaded</span>
                                            <?php elseif (!empty($doc['viewed_at'])): ?>
                                                <span class="status-badge status-viewed">👁 Viewed</span>
                                            <?php else: ?>
                                                <span class="status-badge status-new">🆕 New</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="font-size:13px; color:var(--text-primary);">
                                                <?php echo date('M j, Y', strtotime($doc['sent_at'])); ?>
                                            </div>
                                            <div style="font-size:11px; color:var(--text-secondary);">
                                                <?php echo date('g:i A', strtotime($doc['sent_at'])); ?>
                                            </div>
                                        </td>
                                    <?php else: ?>
                                        <td>
                                            <code style="background:var(--table-bg); padding:4px 8px; border-radius:4px; font-size:12px;">
                                                <?php echo htmlspecialchars($doc['key_id']); ?>
                                            </code>
                                        </td>
                                        <td>
                                            <span style="font-weight:600; color:#028090;">
                                                <?php echo $doc['recipient_count']; ?>
                                            </span> 
                                            <span style="font-size:12px; color:var(--text-secondary);">users</span>
                                        </td>
                                        <td>
                                            <div style="font-size:13px; color:var(--text-primary);">
                                                <?php echo date('M j, Y', strtotime($doc['created_at'])); ?>
                                            </div>
                                            <div style="font-size:11px; color:var(--text-secondary);">
                                                <?php echo date('g:i A', strtotime($doc['created_at'])); ?>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                    
                                    <td>
                                        <div class="action-buttons">
                                            <a href="verify.php?token=<?php echo urlencode($doc['token']); ?>" class="btn btn-sm btn-primary" title="Verify">
                                                🔍 Verify
                                            </a>
                                            <a href="download.php?token=<?php echo urlencode($doc['token']); ?>" class="btn btn-sm btn-primary" title="Download">
                                                ⬇️ Download
                                            </a>
                                            <?php if (in_array($user_role, ['admin', 'superadmin'])): ?>
                                                <a href="send_documents.php?doc_id=<?php echo $doc['id']; ?>" class="btn btn-sm btn-primary" title="Send">
                                                    📤 Send
                                                </a>
                                                <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this document?')">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="doc_id" value="<?php echo $doc['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                                        🗑️
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="icon">📄</div>
                        <h3 style="color:var(--text-primary); margin-bottom:10px;">No Documents Found</h3>
                        <p style="margin-bottom:20px;">
                            <?php if ($user_role === 'user'): ?>
                                You don't have any documents yet. Check back later when documents are shared with you.
                            <?php else: ?>
                                Start by uploading your first document.
                            <?php endif; ?>
                        </p>
                        <?php if (in_array($user_role, ['admin', 'superadmin'])): ?>
                            <a href="upload.php" class="btn btn-primary">📤 Upload Document</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script src="js/dashboard.js"></script>
<script>
function searchDocuments() {
    const input = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('.doc-row');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(input) ? '' : 'none';
    });
}
</script>
</body>
</html>