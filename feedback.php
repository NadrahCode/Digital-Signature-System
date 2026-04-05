<?php
session_start();
require('inc/db_config.php');

// Add category column if missing
$r = mysqli_query($connect,"SHOW COLUMNS FROM queries LIKE 'category'");
if (mysqli_num_rows($r)==0)
    mysqli_query($connect,"ALTER TABLE queries ADD COLUMN category VARCHAR(20) DEFAULT 'user' AFTER message");

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$user_id    = $_SESSION['user_id'];
$user_name  = $_SESSION['name'];
$user_email = $_SESSION['email'];
$user_role  = $_SESSION['role'];
$profile_img = $_SESSION['profile_img'] ?? '';

$success_message = '';
$error_message   = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {

    // Submit feedback (user / admin)
    if (isset($_POST['submit_feedback']) && $user_role!=='superadmin') {
        $subject = mysqli_real_escape_string($connect, $_POST['subject']);
        $message = mysqli_real_escape_string($connect, $_POST['message']);
        $full    = $subject.': '.$message;
        $stmt = mysqli_prepare($connect,"INSERT INTO queries (name,email,message,category,status) VALUES (?,?,?,?,'new')");
        mysqli_stmt_bind_param($stmt,"ssss",$user_name,$user_email,$full,$user_role);
        if (mysqli_stmt_execute($stmt)) $success_message='Feedback submitted successfully!';
        else $error_message='Error: '.mysqli_error($connect);
        mysqli_stmt_close($stmt);
    }

    // Update status (superadmin)
    if (isset($_POST['update_status']) && $user_role==='superadmin') {
        $qid    = intval($_POST['query_id']);
        $status = mysqli_real_escape_string($connect,$_POST['status']);
        $stmt   = mysqli_prepare($connect,"UPDATE queries SET status=? WHERE query_id=?");
        mysqli_stmt_bind_param($stmt,"si",$status,$qid);
        if (mysqli_stmt_execute($stmt)) $success_message='Status updated!';
        else $error_message='Error: '.mysqli_error($connect);
        mysqli_stmt_close($stmt);
    }

    // Delete (superadmin)
    if (isset($_POST['delete_feedback']) && $user_role==='superadmin') {
        $qid  = intval($_POST['query_id']);
        $stmt = mysqli_prepare($connect,"DELETE FROM queries WHERE query_id=?");
        mysqli_stmt_bind_param($stmt,"i",$qid);
        if (mysqli_stmt_execute($stmt)) $success_message='Feedback deleted!';
        else $error_message='Error: '.mysqli_error($connect);
        mysqli_stmt_close($stmt);
    }
}

// Fetch feedback
$sq = isset($_GET['search']) ? mysqli_real_escape_string($connect,$_GET['search']) : '';
$feedback_data = [];
try {
    if ($user_role==='superadmin') {
        $q = "SELECT * FROM queries";
        if ($sq) { $q.=" WHERE name LIKE ? OR email LIKE ? OR message LIKE ?"; }
        $q.=" ORDER BY created_at DESC";
        $stmt = mysqli_prepare($connect,$q);
        if ($sq) { $p="%$sq%"; mysqli_stmt_bind_param($stmt,"sss",$p,$p,$p); }
    } else {
        $q = "SELECT * FROM queries WHERE email=?";
        if ($sq) { $q.=" AND (name LIKE ? OR message LIKE ?)"; }
        $q.=" ORDER BY created_at DESC";
        $stmt = mysqli_prepare($connect,$q);
        if ($sq) { $p="%$sq%"; mysqli_stmt_bind_param($stmt,"sss",$user_email,$p,$p); }
        else      mysqli_stmt_bind_param($stmt,"s",$user_email);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row=mysqli_fetch_assoc($res)) $feedback_data[]=$row;
    mysqli_stmt_close($stmt);
} catch (Exception $e) { $error_message='Error fetching feedback.'; }

// Stats
$total_fb   = count($feedback_data);
$new_fb     = count(array_filter($feedback_data, fn($f)=>$f['status']==='new'));
$resolved_fb= count(array_filter($feedback_data, fn($f)=>$f['status']==='resolved'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Feedback | DIGITAL SIGNATURE SYSTEM</title>
<?php require('inc/links.php'); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="css/design.css">
<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/feedback.css">
<style>
/* ── documents.php table style ── */
.stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:20px; margin-bottom:30px; }
.stat-card { background:linear-gradient(135deg,#028090,#114B2F); color:white; padding:25px; border-radius:12px; text-align:center; }
.stat-card .number { font-size:36px; font-weight:bold; margin-bottom:8px; }
.stat-card .label  { font-size:14px; opacity:.9; }
.toolbar { display:flex; gap:15px; margin-bottom:25px; flex-wrap:wrap; align-items:center; }
.toolbar input { flex:1; min-width:250px; padding:12px 15px; border:2px solid var(--sidebar-border); border-radius:8px; background:var(--card-bg); color:var(--text-primary); font-size:14px; }
.btn { padding:10px 20px; border:none; border-radius:8px; cursor:pointer; font-size:14px; font-weight:600; transition:all .3s; text-decoration:none; display:inline-block; }
.btn-primary { background:linear-gradient(135deg,#028090,#114B2F); color:white; }
.btn-danger  { background:#dc3545; color:white; }
.btn-sec     { background:var(--table-bg); color:var(--text-primary); border:1px solid var(--sidebar-border); }
.btn:hover   { transform:translateY(-2px); box-shadow:0 3px 10px rgba(0,0,0,.2); }
.btn-sm { padding:6px 12px; font-size:12px; }
.table-container { overflow-x:auto; border-radius:10px; border:1px solid var(--sidebar-border); background:var(--card-bg); }
table { width:100%; border-collapse:collapse; }
thead { background:linear-gradient(135deg,#028090,#114B2F); color:white; }
th { padding:15px; text-align:left; font-weight:600; font-size:14px; white-space:nowrap; }
td { padding:15px; border-bottom:1px solid var(--sidebar-border); color:var(--text-primary); vertical-align:top; }
tr:last-child td { border-bottom:none; }
tr:hover { background:var(--table-bg); }
.action-buttons { display:flex; gap:8px; }
.alert { padding:15px 20px; border-radius:8px; margin-bottom:20px; }
.alert-success { background:#d4edda; color:#155724; border-left:4px solid #28a745; }
.alert-danger  { background:#f8d7da; color:#721c24; border-left:4px solid #dc3545; }
/* status / category badges */
.status-badge,.category-badge { display:inline-block; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:600; }
.status-new      { background:#e7f3ff; color:#0066cc; }
.status-read     { background:#fff3cd; color:#856404; }
.status-resolved { background:#d4edda; color:#155724; }
.category-user   { background:#e7f3ff; color:#0055bb; }
.category-admin  { background:#fff3cd; color:#7a5c00; }
.empty-state { text-align:center; padding:60px 20px; color:var(--text-secondary); }
.empty-state .icon { font-size:64px; margin-bottom:20px; opacity:.4; }
/* modal */
.mo { display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:9999; align-items:center; justify-content:center; }
.mo.open { display:flex; animation:fadeIn .2s ease; }
.mc { background:var(--card-bg); border-radius:16px; padding:28px; width:90%; max-width:420px; box-shadow:0 16px 48px rgba(0,0,0,.25); position:relative; }
.mc h3 { margin:0 0 20px; font-size:18px; color:var(--text-primary); }
.mc .close-x { position:absolute; top:14px; right:18px; background:none; border:none; font-size:22px; cursor:pointer; color:var(--text-secondary); }
.mc label  { display:block; margin-bottom:6px; font-size:13px; font-weight:600; color:var(--text-secondary); }
.mc select { width:100%; padding:10px 12px; border:1px solid var(--sidebar-border); border-radius:8px; background:var(--card-bg); color:var(--text-primary); font-size:13px; margin-bottom:18px; }
.mc-actions { display:flex; gap:10px; justify-content:flex-end; }
</style>
</head>
<body data-theme="light">
<div class="dashboard-container">
    <?php require('inc/sidebar.php'); ?>
    <main class="main-content">
        <?php require('inc/topheader.php'); ?>

        <section class="welcome-section">
            <h1 class="welcome-title"><?php echo $user_role==='superadmin'?'Manage Feedback 💬':'Feedback & Inquiries 💬'; ?></h1>
            <h2 class="system-title">DIGITAL SIGNATURE SYSTEM</h2>
            <div class="logo-container">
                <a href="dashboard.php"><img src="images/logo-main.png" alt="Logo" class="system-logo"></a>
            </div>
        </section>

        <div style="max-width:1400px; margin:0 auto; padding:30px;">

        <?php if ($success_message): ?>
            <div class="alert alert-success">✅ <?php echo $success_message; ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger">❌ <?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card"><div class="number"><?php echo $total_fb; ?></div><div class="label">Total Feedback</div></div>
            <div class="stat-card"><div class="number"><?php echo $new_fb; ?></div><div class="label">New / Unread</div></div>
            <div class="stat-card"><div class="number"><?php echo $resolved_fb; ?></div><div class="label">Resolved</div></div>
        </div>

        <!-- Submit form (user / admin) -->
        <?php if ($user_role!=='superadmin'): ?>
        <section class="feedback-form-section" style="margin-bottom:30px;">
            <div class="profile-card">
                <div class="card-header">
                    <h3><i class="bi bi-chat-left-text"></i> Submit New Feedback</h3>
                    <span class="category-badge category-<?php echo $user_role; ?>"><?php echo ucfirst($user_role); ?> Feedback</span>
                </div>
                <div class="card-body">
                    <form method="POST" class="feedback-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="bi bi-card-heading"></i> Subject *</label>
                                <input type="text" name="subject" class="form-control" required placeholder="Enter subject">
                            </div>
                            <div class="form-group">
                                <label><i class="bi bi-envelope"></i> Your Email</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($user_email); ?>" disabled>
                            </div>
                        </div>
                        <div class="form-group">
                            <label><i class="bi bi-chat-square-text"></i> Message *</label>
                            <textarea name="message" class="form-control" required rows="4" placeholder="Describe your feedback…"></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="submit_feedback" class="btn btn-primary"><i class="bi bi-send-fill"></i> Submit Feedback</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Toolbar -->
        <div class="toolbar">
            <form method="GET" style="display:contents;">
                <input type="text" name="search" placeholder="🔍 Search feedback..." value="<?php echo htmlspecialchars($sq); ?>">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Search</button>
                <?php if ($sq): ?>
                    <a href="feedback.php" class="btn btn-sec"><i class="bi bi-x"></i> Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Table -->
        <div class="table-container">
            <?php if (empty($feedback_data)): ?>
                <div class="empty-state">
                    <div class="icon">💬</div>
                    <h3 style="color:var(--text-primary);margin-bottom:10px;">No Feedback Found</h3>
                    <p><?php echo $user_role==='superadmin'?'No feedback submitted yet.':($sq?'Try a different search.':'Submit your first feedback above.'); ?></p>
                </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <?php if ($user_role==='superadmin'): ?>
                        <th>Sender</th>
                        <th>Email</th>
                        <?php endif; ?>
                        <th>Message</th>
                        <th>Category</th>
                        <th>Status</th>
                        <?php if ($user_role==='superadmin'): ?>
                        <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($feedback_data as $fb): ?>
                    <tr>
                        <td style="white-space:nowrap;">
                            <div><?php echo date('M j, Y',strtotime($fb['created_at'])); ?></div>
                            <div style="font-size:11px;color:var(--text-secondary);"><?php echo date('g:i A',strtotime($fb['created_at'])); ?></div>
                        </td>
                        <?php if ($user_role==='superadmin'): ?>
                        <td>
                            <div style="font-weight:600;"><?php echo htmlspecialchars($fb['name']); ?></div>
                            <div style="font-size:11px;color:var(--text-secondary);"><?php echo ucfirst($fb['category']??'user'); ?></div>
                        </td>
                        <td style="color:var(--text-secondary);font-size:13px;"><?php echo htmlspecialchars($fb['email']); ?></td>
                        <?php endif; ?>
                        <td style="max-width:360px;"><?php echo htmlspecialchars($fb['message']); ?></td>
                        <td><span class="category-badge category-<?php echo strtolower($fb['category']??'user'); ?>"><?php echo ucfirst($fb['category']??'user'); ?></span></td>
                        <td><span class="status-badge status-<?php echo strtolower($fb['status']); ?>"><?php echo ucfirst($fb['status']); ?></span></td>
                        <?php if ($user_role==='superadmin'): ?>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-sm btn-primary"
                                    onclick="openStatusModal(<?php echo $fb['query_id']; ?>,'<?php echo $fb['status']; ?>')">
                                    ✏️ Status
                                </button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this feedback?')">
                                    <input type="hidden" name="query_id" value="<?php echo $fb['query_id']; ?>">
                                    <button type="submit" name="delete_feedback" class="btn btn-sm btn-danger">🗑️ Delete</button>
                                </form>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <div style="margin-top:10px;font-size:12px;color:var(--text-secondary);"><?php echo count($feedback_data); ?> record(s) shown</div>

        <!-- STATUS MODAL (superadmin) -->
        <?php if ($user_role==='superadmin'): ?>
        <div id="statusModal" class="mo">
            <div class="mc">
                <button class="close-x" onclick="closeModal('statusModal')">✕</button>
                <h3><i class="bi bi-pencil-square" style="color:#028090;"></i> Update Status</h3>
                <form method="POST">
                    <input type="hidden" name="query_id" id="modalQueryId">
                    <label>Status</label>
                    <select name="status" id="modalStatus">
                        <option value="new">New</option>
                        <option value="read">Read</option>
                        <option value="resolved">Resolved</option>
                    </select>
                    <div class="mc-actions">
                        <button type="button" onclick="closeModal('statusModal')" class="btn btn-sec">Cancel</button>
                        <button type="submit" name="update_status" class="btn btn-primary"><i class="bi bi-save"></i> Update</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        </div><!-- /wrapper -->
    </main>
</div>

<script src="js/dashboard.js"></script>

<script>
// ───────────── EXISTING MODAL (KEEP) ─────────────
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('.mo').forEach(m => 
    m.addEventListener('click', e => { if(e.target===m) m.classList.remove('open'); })
);

function openStatusModal(id, status) {
    document.getElementById('modalQueryId').value = id;
    document.getElementById('modalStatus').value  = status;
    openModal('statusModal');
}

// ───────────── MERGED feedback.js ─────────────
document.addEventListener('DOMContentLoaded', function() {

    // ── SIDEBAR ──
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const sidebar = document.querySelector('.sidebar');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => sidebar.classList.toggle('collapsed'));
    }

    if (mobileMenuToggle && sidebar) {
        mobileMenuToggle.addEventListener('click', () => sidebar.classList.toggle('show'));
    }

    document.addEventListener('click', function(event) {
        if (window.innerWidth <= 992 && sidebar) {
            if (!sidebar.contains(event.target) &&
                mobileMenuToggle && !mobileMenuToggle.contains(event.target) &&
                sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
            }
        }
    });

    // ── THEME ──
    const themeToggle = document.getElementById('themeToggle');
    const body = document.body;
    const savedTheme = localStorage.getItem('theme') || 'light';

    body.setAttribute('data-theme', savedTheme);

    if (themeToggle) {
        themeToggle.checked = savedTheme === 'dark';

        themeToggle.addEventListener('change', function() {
            const theme = this.checked ? 'dark' : 'light';
            body.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
        });
    }

    // ── TABLE SORTING (OPTIONAL BUT INCLUDED) ──
    const tableHeaders = document.querySelectorAll('th[data-sort]');
    
    tableHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const table = this.closest('table');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));

            const isAscending = !this.classList.contains('asc');

            tableHeaders.forEach(h => h.classList.remove('asc','desc'));
            this.classList.toggle('asc', isAscending);
            this.classList.toggle('desc', !isAscending);

            const colIndex = Array.from(this.parentElement.children).indexOf(this);

            rows.sort((a,b)=>{
                let aText = a.children[colIndex].textContent.trim();
                let bText = b.children[colIndex].textContent.trim();

                if (isAscending) return aText.localeCompare(bText);
                else return bText.localeCompare(aText);
            });

            rows.forEach(row => tbody.appendChild(row));
        });
    });

    // ── AUTO HIDE ALERTS ──
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.opacity = '0';
            alert.style.transition = '0.5s';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);

    // ── RESPONSIVE SIDEBAR RESET ──
    function handleResize() {
        if (window.innerWidth <= 992 && sidebar) {
            sidebar.classList.remove('collapsed');
            sidebar.classList.remove('show');
        }
    }

    window.addEventListener('resize', handleResize);
    handleResize();
});
</script>
</body>
</html>