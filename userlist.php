<?php
session_start();
require('inc/db_config.php');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: dashboard.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];
$user_email = $_SESSION['email'];
$user_role = $_SESSION['role'];
$profile_img = $_SESSION['profile_img'] ?? '';

$error = "";
$success = "";

// Handle user assignment
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'assign_user') {
        $user_id_assign = intval($_POST['user_id']);
        $user_class = trim($_POST['user_class']);
        
        if (!empty($user_class)) {
            // Check class capacity (max 200)
            $count_res = $connect->query("SELECT COUNT(*) as c FROM users WHERE user_class='$user_class' AND role='user'");
            $current_count = $count_res->fetch_assoc()['c'];
            if ($current_count >= 200) {
                $error = "❌ Class '$user_class' is full (max 200 students).";
            } else {
                $stmt = $connect->prepare("UPDATE users SET user_class = ? WHERE user_id = ?");
                $stmt->bind_param("si", $user_class, $user_id_assign);
                if ($stmt->execute()) {
                    $success = "✅ User assigned successfully!";
                }
                $stmt->close();
            }
        }
    } 
    elseif ($action === 'bulk_assign') {
        $user_ids = $_POST['user_ids'] ?? [];
        $bulk_class = trim($_POST['bulk_class']);
        
        if (!empty($user_ids) && !empty($bulk_class)) {
            // Check remaining capacity
            $count_res = $connect->query("SELECT COUNT(*) as c FROM users WHERE user_class='$bulk_class' AND role='user'");
            $current_count = $count_res->fetch_assoc()['c'];
            $remaining = 200 - $current_count;
            if (count($user_ids) > $remaining) {
                $error = "❌ Cannot assign " . count($user_ids) . " users — class '$bulk_class' only has $remaining spot(s) left (max 200).";
            } else {
                $ids = implode(',', array_map('intval', $user_ids));
                $stmt = $connect->prepare("UPDATE users SET user_class = ? WHERE user_id IN ($ids)");
                $stmt->bind_param("s", $bulk_class);
                if ($stmt->execute()) {
                    $success = "✅ " . count($user_ids) . " user(s) assigned to '$bulk_class'!";
                }
                $stmt->close();
            }
        } else {
            $error = "Please select users and a class for bulk assignment.";
        }
    }
}

// Get classes from classes table
$classes_result = $connect->query("SELECT class_name FROM classes ORDER BY class_name");
$classes = [];
while ($row = $classes_result->fetch_assoc()) {
    $classes[] = $row['class_name'];
}

// Get all users
$users_query = "SELECT u.*, 
                (SELECT COUNT(*) FROM document_recipients dr WHERE dr.user_id = u.user_id) as doc_count
                FROM users u 
                WHERE u.role = 'user'
                ORDER BY u.user_class, u.name";
$users_result = $connect->query($users_query);

// Get stats
$stats_query = "SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN user_class IS NOT NULL AND user_class != 'unassigned' THEN 1 ELSE 0 END) as assigned_users
    FROM users WHERE role='user'";
$stats = $connect->query($stats_query)->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>User Assignment | Digital Signature System</title>
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
}
.btn {
    padding: 12px 24px;
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
.btn-success {
    background: #28a745;
    color: white;
}
.btn-secondary {
    background: #6c757d;
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
.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
}
.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #028090, #114B2F);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}
.class-badge {
    display: inline-block;
    padding: 6px 12px;
    background: #e7f3ff;
    color: #0066cc;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.class-badge.unassigned {
    background: #f8d7da;
    color: #721c24;
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
.filter-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.filter-tab {
    padding: 10px 20px;
    background: var(--table-bg);
    border: 2px solid var(--sidebar-border);
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
    color: var(--text-primary);
}
.filter-tab:hover {
    border-color: #028090;
}
.filter-tab.active {
    background: linear-gradient(135deg, #028090, #114B2F);
    color: white;
    border-color: #028090;
}
.bulk-toolbar {
    background: var(--card-bg);
    padding: 20px;
    border-radius: 10px;
    border: 2px solid var(--sidebar-border);
    margin-bottom: 20px;
}
</style>
</head>
<body data-theme="light">
<div class="dashboard-container">
    <?php require('inc/sidebar.php'); ?>
    
    <main class="main-content">
        <?php require('inc/topheader.php'); ?>
        
        <section class="welcome-section">
            <h1 class="welcome-title">User Assignment 👥</h1>
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
            
            <!-- Link to Class Management -->
            <div style="margin-bottom:20px;">
                <a href="class.php" class="btn btn-secondary">
                    📚 Manage Classes
                </a>
            </div>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="number"><?php echo $stats['total_users']; ?></div>
                    <div class="label">Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?php echo $stats['assigned_users']; ?></div>
                    <div class="label">Assigned Users</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?php echo count($classes); ?></div>
                    <div class="label">Available Classes</div>
                </div>
            </div>
            
            <!-- Search -->
            <div style="margin-bottom:20px;">
                <input type="text" id="searchInput" placeholder="🔍 Search users by name or email..." onkeyup="searchUsers()" style="width:100%; padding:12px 15px; border:2px solid var(--sidebar-border); border-radius:8px; background:var(--card-bg); color:var(--text-primary); font-size:15px;">
            </div>
            
            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <div class="filter-tab active" onclick="filterByClass('all')">
                    All Users (<?php echo $stats['total_users']; ?>)
                </div>
                <?php foreach ($classes as $class): 
                    $count = $connect->query("SELECT COUNT(*) as c FROM users WHERE user_class='$class' AND role='user'")->fetch_assoc()['c'];
                ?>
                    <div class="filter-tab" onclick="filterByClass('<?php echo htmlspecialchars($class); ?>')">
                        <?php echo htmlspecialchars($class); ?> (<?php echo $count; ?>)
                    </div>
                <?php endforeach; ?>
                <div class="filter-tab" onclick="filterByClass('unassigned')">
                    Unassigned
                </div>
            </div>
            
            <!-- Sticky bulk action bar — only visible when rows are checked -->
            <div id="bulkBar" style="display:none; position:sticky; bottom:20px; z-index:100; background:var(--card-bg); border:2px solid #028090; border-radius:12px; padding:14px 20px; box-shadow:0 4px 20px rgba(2,128,144,0.2); margin-bottom:16px; animation:slideInDown 0.25s ease;">
                <form method="post" id="bulkForm" onsubmit="return validateBulk(event)">
                    <input type="hidden" name="action" value="bulk_assign">
                    <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                        <span style="font-weight:600; color:#028090; white-space:nowrap;">
                            <span id="selectedCount">0</span> user(s) selected
                        </span>
                        <div id="selectedNames" style="flex:1; font-size:12px; color:var(--text-secondary); min-width:0; overflow:hidden; white-space:nowrap; text-overflow:ellipsis;"></div>
                        <select name="bulk_class" id="bulkClass" style="padding:10px 14px; border:2px solid var(--sidebar-border); border-radius:8px; background:var(--card-bg); color:var(--text-primary); min-width:200px; font-size:13px;">
                            <option value="">Select class to assign...</option>
                            <?php foreach ($classes as $class):
                                $cap = $connect->query("SELECT COUNT(*) as c FROM users WHERE user_class='$class' AND role='user'")->fetch_assoc()['c'];
                                $remaining = 200 - $cap;
                            ?>
                                <option value="<?php echo htmlspecialchars($class); ?>" <?php echo $remaining <= 0 ? 'disabled' : ''; ?>>
                                    <?php echo htmlspecialchars($class); ?> (<?php echo $cap; ?>/200<?php echo $remaining <= 0 ? ' — FULL' : ''; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-success" style="white-space:nowrap;">✅ Assign</button>
                        <button type="button" onclick="clearSelection()" style="padding:10px 16px; border:2px solid var(--sidebar-border); border-radius:8px; background:transparent; color:var(--text-secondary); cursor:pointer; font-size:13px;">✕ Clear</button>
                    </div>
                </form>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th style="width:50px;"><input type="checkbox" id="selectAll" onclick="toggleAll()"></th>
                            <th>User</th>
                            <th>Email</th>
                            <th>Current Class</th>
                            <th>Documents</th>
                            <th style="width:300px;">Quick Assign</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $users_result->fetch_assoc()): ?>
                            <tr class="user-row" data-class="<?php echo htmlspecialchars($user['user_class'] ?: 'unassigned'); ?>">
                                <td>
                                    <input type="checkbox" class="user-checkbox" name="user_ids[]" value="<?php echo $user['user_id']; ?>" onchange="updateSelectedCount()">
                                </td>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                        </div>
                                        <span style="font-weight:600;"><?php echo htmlspecialchars($user['name']); ?></span>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <?php if (empty($user['user_class']) || $user['user_class'] == 'unassigned'): ?>
                                        <span class="class-badge unassigned">Unassigned</span>
                                    <?php else: ?>
                                        <span class="class-badge"><?php echo htmlspecialchars($user['user_class']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $user['doc_count']; ?> docs</td>
                                <td>
                                    <form method="post" style="display:flex; gap:8px;" onsubmit="return validateQuickAssign(this)">
                                        <input type="hidden" name="action" value="assign_user">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <select name="user_class" style="flex:1; padding:8px; border-radius:6px; border:2px solid var(--sidebar-border); background:var(--card-bg); color:var(--text-primary); font-size:13px;">
                                            <option value="">Select Class...</option>
                                            <?php foreach ($classes as $class): ?>
                                                <option value="<?php echo htmlspecialchars($class); ?>" <?php echo ($user['user_class'] == $class) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($class); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-primary" style="padding:8px 16px; font-size:12px;">
                                            Update
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script src="js/dashboard.js"></script>
<script>
function toggleAll() {
    const selectAll = document.getElementById('selectAll');
    document.querySelectorAll('.user-checkbox').forEach(cb => {
        if (cb.closest('tr').style.display !== 'none') {
            cb.checked = selectAll.checked;
        }
    });
    updateSelectedCount();
}

function searchUsers() {
    const input = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('.user-row').forEach(row => {
        const name  = row.querySelector('.user-info span').textContent.toLowerCase();
        const email = row.querySelectorAll('td')[2].textContent.toLowerCase();
        row.style.display = (name.includes(input) || email.includes(input)) ? '' : 'none';
    });
    updateSelectedCount();
}

function filterByClass(className) {
    document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
    event.target.classList.add('active');
    document.querySelectorAll('.user-row').forEach(row => {
        row.style.display = (className === 'all' || row.dataset.class === className) ? '' : 'none';
    });
    document.getElementById('searchInput').value = '';
    updateSelectedCount();
}

function updateSelectedCount() {
    const checked = Array.from(document.querySelectorAll('.user-checkbox'))
        .filter(cb => cb.checked && cb.closest('tr').style.display !== 'none');

    const count = checked.length;
    document.getElementById('selectedCount').textContent = count;

    // Show/hide sticky bar
    const bar = document.getElementById('bulkBar');
    bar.style.display = count > 0 ? 'block' : 'none';

    // Show names preview (first 3 + "and X more")
    if (count > 0) {
        const names = checked.slice(0, 3).map(cb => cb.closest('tr').querySelector('.user-info span').textContent.trim());
        const extra = count > 3 ? ` and ${count - 3} more` : '';
        document.getElementById('selectedNames').textContent = names.join(', ') + extra;
    }

    // Update select-all checkbox state
    const visible = Array.from(document.querySelectorAll('.user-checkbox'))
        .filter(cb => cb.closest('tr').style.display !== 'none');
    document.getElementById('selectAll').checked = visible.length > 0 && visible.every(cb => cb.checked);
}

function clearSelection() {
    document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('selectAll').checked = false;
    updateSelectedCount();
}

function validateQuickAssign(form) {
    const select = form.querySelector('select[name="user_class"]');
    if (!select.value) { alert('Please select a class before updating.'); return false; }
    return true;
}

function validateBulk(e) {
    // Collect only checked + visible checkboxes and inject into form
    const form = document.getElementById('bulkForm');
    // Remove any previously injected hidden inputs
    form.querySelectorAll('input.injected').forEach(el => el.remove());

    const checkedVisible = Array.from(document.querySelectorAll('.user-checkbox'))
        .filter(cb => cb.checked && cb.closest('tr').style.display !== 'none');

    if (checkedVisible.length === 0) {
        e.preventDefault();
        alert('Please select at least one user.');
        return false;
    }
    if (!document.getElementById('bulkClass').value) {
        e.preventDefault();
        alert('Please select a class.');
        return false;
    }
    // Inject the visible checked IDs as hidden inputs
    checkedVisible.forEach(cb => {
        const h = document.createElement('input');
        h.type = 'hidden'; h.name = 'user_ids[]'; h.value = cb.value; h.className = 'injected';
        form.appendChild(h);
    });
    return true;
}

updateSelectedCount();
</script>
</body>
</html>