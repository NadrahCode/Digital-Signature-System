<?php
session_start();
require('inc/db_config.php');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];
$user_email = $_SESSION['email'];
$user_role = $_SESSION['role'];
$profile_img = $_SESSION['profile_img'] ?? '';

$doc_id = isset($_GET['doc_id']) ? intval($_GET['doc_id']) : 0;
$error = "";
$success = "";

// Get document info with signer details
$doc_info = null;
if ($doc_id > 0) {
    $query = "SELECT d.*, u.name as signer_name, u.email as signer_email 
              FROM documents d 
              LEFT JOIN users u ON d.created_by = u.user_id 
              WHERE d.id = $doc_id";
    $result = $connect->query($query);
    if ($result && $result->num_rows > 0) {
        $doc_info = $result->fetch_assoc();
    }
}

// Get classes
$classes_result = $connect->query("SELECT DISTINCT user_class FROM users WHERE user_class IS NOT NULL AND user_class != 'unassigned' ORDER BY user_class");
$classes = [];
while ($row = $classes_result->fetch_assoc()) {
    $classes[] = $row['user_class'];
}

// Get users
$users_result = $connect->query("SELECT user_id, name, email, user_class FROM users WHERE role='user' ORDER BY user_class, name");
$users = [];
while ($row = $users_result->fetch_assoc()) {
    $users[] = $row;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $doc_id > 0) {
    $send_type = $_POST['send_type'] ?? '';
    $recipients = [];
    
        if ($send_type === 'class') {
            $selected_class = $_POST['selected_class'] ?? '';
            if (empty($selected_class)) {
                $error = "Please select a class.";
            } elseif ($selected_class === 'all') {
                // Get all users with role='user' (students)
                $r = $connect->query("SELECT user_id FROM users WHERE role='user'");
                while ($row = $r->fetch_assoc()) $recipients[] = $row['user_id'];
            } elseif ($selected_class === 'unassigned') {
                $r = $connect->query("SELECT user_id FROM users WHERE (user_class IS NULL OR user_class='unassigned') AND role='user'");
                while ($row = $r->fetch_assoc()) $recipients[] = $row['user_id'];
            } else {
                $stmt = $connect->prepare("SELECT user_id FROM users WHERE user_class=? AND role='user'");
                $stmt->bind_param("s", $selected_class);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) $recipients[] = $row['user_id'];
                $stmt->close();
            }
        }
    elseif ($send_type === 'individual') {
        $user_ids = $_POST['user_ids'] ?? [];
        if (empty($user_ids)) {
            $error = "Please select at least one user.";
        } else {
            $recipients = array_map('intval', $user_ids);
        }
    }
    
    if (empty($error) && !empty($recipients)) {
        $sent_count = 0;
        $sent_at = date('Y-m-d H:i:s');
        
        foreach ($recipients as $recipient_id) {
            $check = $connect->query("SELECT id FROM document_recipients WHERE document_id=$doc_id AND user_id=$recipient_id");
            if ($check->num_rows == 0) {
                $stmt = $connect->prepare("INSERT INTO document_recipients (document_id, user_id, sent_at) VALUES (?, ?, ?)");
                $stmt->bind_param("iis", $doc_id, $recipient_id, $sent_at);
                if ($stmt->execute()) {
                    $sent_count++;
                }
                $stmt->close();
            }
        }
        
        if ($sent_count > 0) {
            $success = "✅ Document sent to $sent_count user(s) successfully!";
        } else {
            $error = "Document already sent to selected recipients.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Send Document | Digital Signature System</title>
<?php require('inc/links.php'); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="css/design.css">
<link rel="stylesheet" href="css/dashboard.css">
<style>
.send-option {
    transition: all 0.3s;
}
.send-option:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px var(--shadow-color);
}
.user-card {
    transition: all 0.3s;
}
.user-card:hover {
    transform: translateY(-2px);
}
.user-card.selected {
    border-color: #028090 !important;
    background: linear-gradient(135deg, #028090, #114B2F) !important;
    color: white !important;
}
</style>
</head>
<body data-theme="light">
<div class="dashboard-container">
    <?php require('inc/sidebar.php'); ?>
    
    <main class="main-content">
        <?php require('inc/topheader.php'); ?>
        
        <section class="welcome-section">
            <h1 class="welcome-title">Send Document 📤</h1>
            <h2 class="system-title">DIGITAL SIGNATURE SYSTEM</h2>
            <div class="logo-container">
                <a href="dashboard.php">
                    <img src="images/logo-main.png" alt="Digital Signature System Logo" class="system-logo">
                </a>
            </div>
        </section>
        
        <div style="max-width:1000px; margin:0 auto; padding:30px;">
            <?php if ($doc_info): ?>
                <div style="background:var(--card-bg); border-radius:12px; padding:30px; box-shadow:0 4px 12px var(--shadow-color);">
                    
                    <!-- Document Info Box -->
                    <div style="background:rgba(2, 128, 144, 0.1); padding:20px; border-radius:10px; margin-bottom:25px; border-left:4px solid #028090;">
                        <h3 style="color:#028090; margin-bottom:12px;">📄 <?php echo htmlspecialchars($doc_info['doc_name']); ?></h3>
                        <?php if (!empty($doc_info['description'])): ?>
                            <p style="color:var(--text-secondary); margin:8px 0;"><?php echo htmlspecialchars($doc_info['description']); ?></p>
                        <?php endif; ?>
                        <div style="margin-top:12px; padding-top:12px; border-top:1px solid rgba(2, 128, 144, 0.2);">
                            <p style="color:var(--text-secondary); margin:4px 0;">
                                <strong>Signed by:</strong> <?php echo htmlspecialchars($doc_info['signer_name'] ?? 'Unknown'); ?> 
                                (<?php echo htmlspecialchars($doc_info['signer_email'] ?? 'N/A'); ?>)
                            </p>
                            <p style="color:var(--text-secondary); margin:4px 0;">
                                <strong>Created:</strong> <?php echo date('F j, Y \a\t g:i A', strtotime($doc_info['created_at'])); ?>
                            </p>
                            <p style="color:var(--text-secondary); margin:4px 0;">
                                <strong>Checksum:</strong> <code style="background:rgba(0,0,0,0.1); padding:2px 6px; border-radius:4px;"><?php echo htmlspecialchars($doc_info['checksum']); ?></code>
                            </p>
                        </div>
                    </div>
                    
                    <?php if ($error): ?>
                        <div style="background:#f8d7da; color:#721c24; padding:15px; border-radius:8px; margin-bottom:20px; border-left:4px solid #dc3545;">
                            ❌ <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div style="background:linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color:white; padding:30px; border-radius:15px; text-align:center;">
                            <div style="font-size:64px; margin-bottom:15px;">✅</div>
                            <h3 style="margin-bottom:15px;">Document Sent Successfully!</h3>
                            <p style="margin:15px 0; opacity:0.95;">The document has been delivered to the selected recipients.</p>
                            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:15px; margin-top:25px;">
                                <a href="documents.php" style="padding:15px; background:rgba(255,255,255,0.3); color:white; border-radius:8px; text-decoration:none; text-align:center; font-weight:600; transition:all 0.3s;" onmouseover="this.style.background='rgba(255,255,255,0.4)'" onmouseout="this.style.background='rgba(255,255,255,0.3)'">
                                    📋 View All Documents
                                </a>
                                <a href="upload.php" style="padding:15px; background:rgba(255,255,255,0.3); color:white; border-radius:8px; text-decoration:none; text-align:center; font-weight:600; transition:all 0.3s;" onmouseover="this.style.background='rgba(255,255,255,0.4)'" onmouseout="this.style.background='rgba(255,255,255,0.3)'">
                                    📤 Upload New Document
                                </a>
                                <a href="dashboard.php" style="padding:15px; background:rgba(255,255,255,0.3); color:white; border-radius:8px; text-decoration:none; text-align:center; font-weight:600; transition:all 0.3s;" onmouseover="this.style.background='rgba(255,255,255,0.4)'" onmouseout="this.style.background='rgba(255,255,255,0.3)'">
                                    🏠 Dashboard
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                    
                    <form method="post">
                        <!-- Send Type Selection -->
                        <div style="display:flex; gap:15px; margin-bottom:25px;">
                            <label style="flex:1; padding:20px; border:2px solid var(--sidebar-border); border-radius:10px; cursor:pointer; text-align:center; background:linear-gradient(135deg, #028090, #114B2F); color:white;" class="send-option active" onclick="selectSendType('class')">
                                <input type="radio" name="send_type" value="class" checked style="display:none;">
                                <div style="font-size:32px; margin-bottom:10px;">📚</div>
                                <h4 style="margin:0;">Send to Entire Class</h4>
                                <p style="font-size:12px; margin-top:5px; opacity:0.8;">Distribute to all students in a class</p>
                            </label>
                            <label style="flex:1; padding:20px; border:2px solid var(--sidebar-border); border-radius:10px; cursor:pointer; text-align:center; background:var(--card-bg); color:var(--text-primary);" class="send-option" onclick="selectSendType('individual')">
                                <input type="radio" name="send_type" value="individual" style="display:none;">
                                <div style="font-size:32px; margin-bottom:10px;">👤</div>
                                <h4 style="margin:0; color:var(--text-primary);">Send to Individuals</h4>
                                <p style="font-size:12px; margin-top:5px; opacity:0.7;">Select specific users</p>
                            </label>
                        </div>
                        
                        <!-- Class Selection -->
                        <div id="class-section" class="form-section" style="display:block; margin-bottom:20px;">
                            <label style="display:block; margin-bottom:12px; font-weight:600; color:var(--text-primary); font-size:16px;">📚 Select Class</label>
                            <select name="selected_class" style="width:100%; padding:15px; border:2px solid var(--sidebar-border); border-radius:8px; background:var(--card-bg); color:var(--text-primary); font-size:15px;">
                                <option value="">-- Choose a class --</option>
                                <option value="all">All Classes (All students)</option>
                                <?php foreach ($classes as $class):
                                    $count = $connect->query("SELECT COUNT(*) as c FROM users WHERE user_class='$class' AND role='user'")->fetch_assoc()['c'];
                                ?>
                                    <option value="<?php echo htmlspecialchars($class); ?>">
                                        <?php echo htmlspecialchars($class); ?> (<?php echo $count; ?> students)
                                    </option>
                                <?php endforeach; ?>
                                <?php
                                    $unassigned_count = $connect->query("SELECT COUNT(*) as c FROM users WHERE (user_class IS NULL OR user_class='unassigned') AND role='user'")->fetch_assoc()['c'];
                                    if ($unassigned_count > 0):
                                ?>
                                    <option value="unassigned">Unassigned users (<?php echo $unassigned_count; ?> students)</option>
                                <?php endif; ?>
                            </select>
                            <small style="color:var(--text-secondary); display:block; margin-top:8px;">
                                The document will be sent to all students in the selected class
                            </small>
                        </div>
                        
                        <!-- Individual Selection -->
                        <div id="individual-section" class="form-section" style="display:none; margin-bottom:20px;">
                            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; flex-wrap:wrap; gap:10px;">
                                <label style="font-weight:600; color:var(--text-primary); font-size:16px;">👥 Select Users</label>
                                <div style="display:flex; gap:8px; align-items:center;">
                                    <input type="text" id="userSearch" placeholder="🔍 Search..." style="padding:8px 12px; border:2px solid var(--sidebar-border); border-radius:8px; background:var(--card-bg); color:var(--text-primary); font-size:13px; width:200px;" onkeyup="filterUsers()">
                                    <select id="classFilter" onchange="filterByClassSend(this.value)" style="padding:8px 12px; border:2px solid var(--sidebar-border); border-radius:8px; background:var(--card-bg); color:var(--text-primary); font-size:13px;">
                                        <option value="">All classes</option>
                                        <?php foreach($classes as $c): ?>
                                            <option value="<?php echo strtolower(htmlspecialchars($c)); ?>"><?php echo htmlspecialchars($c); ?></option>
                                        <?php endforeach; ?>
                                        <option value="unassigned">Unassigned</option>
                                    </select>
                                    <button type="button" onclick="selectAllVisible()" style="padding:8px 12px; border:2px solid #028090; border-radius:8px; background:transparent; color:#028090; font-size:12px; cursor:pointer; font-weight:600;">Select all</button>
                                    <button type="button" onclick="clearAll()" style="padding:8px 12px; border:2px solid var(--sidebar-border); border-radius:8px; background:transparent; color:var(--text-secondary); font-size:12px; cursor:pointer;">Clear</button>
                                </div>
                            </div>
                            <div style="max-height:360px; overflow-y:auto; border:1px solid var(--sidebar-border); border-radius:10px;">
                                <table style="width:100%; border-collapse:collapse; font-size:13px;">
                                    <thead style="position:sticky; top:0; background:var(--card-bg); z-index:1;">
                                        <tr style="border-bottom:2px solid var(--sidebar-border);">
                                            <th style="padding:10px 12px; width:36px;"><input type="checkbox" id="selectAllVisible" onclick="selectAllVisible()"></th>
                                            <th style="padding:10px 12px; text-align:left; color:var(--text-secondary); font-weight:600;">Name</th>
                                            <th style="padding:10px 12px; text-align:left; color:var(--text-secondary); font-weight:600;">Email</th>
                                            <th style="padding:10px 12px; text-align:left; color:var(--text-secondary); font-weight:600;">Class</th>
                                        </tr>
                                    </thead>
                                    <tbody id="userTableBody">
                                        <?php foreach ($users as $user): ?>
                                        <tr class="send-user-row" data-name="<?php echo strtolower($user['name']); ?>" data-email="<?php echo strtolower($user['email']); ?>" data-class="<?php echo strtolower($user['user_class'] ?? ''); ?>" style="border-bottom:1px solid var(--sidebar-border); cursor:pointer;" onclick="toggleRowCheck(this)">
                                            <td style="padding:9px 12px;" onclick="event.stopPropagation()">
                                                <input type="checkbox" class="send-checkbox" name="user_ids[]" value="<?php echo $user['user_id']; ?>" onchange="updateSendCount()">
                                            </td>
                                            <td style="padding:9px 12px; font-weight:500; color:var(--text-primary);"><?php echo htmlspecialchars($user['name']); ?></td>
                                            <td style="padding:9px 12px; color:var(--text-secondary);"><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td style="padding:9px 12px;">
                                                <?php if (!empty($user['user_class']) && $user['user_class'] !== 'unassigned'): ?>
                                                    <span style="background:#e7f3ff; color:#0066cc; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:600;"><?php echo htmlspecialchars($user['user_class']); ?></span>
                                                <?php else: ?>
                                                    <span style="color:var(--text-secondary); font-size:12px;">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div style="margin-top:8px; color:var(--text-secondary); font-size:13px;">
                                <span id="selectedCount" style="font-weight:600; color:#028090;">0</span> user(s) selected
                            </div>
                        </div>
                        
                        <button type="submit" style="width:100%; padding:15px; background:linear-gradient(135deg, #028090, #114B2F); color:white; border:none; border-radius:8px; font-size:18px; font-weight:600; cursor:pointer; transition:all 0.3s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(2, 128, 144, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                            📤 Send Document to Recipients
                        </button>
                    </form>
                    
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="background:#f8d7da; color:#721c24; padding:20px; border-radius:8px; border-left:4px solid #dc3545; text-align:center;">
                    <div style="font-size:48px; margin-bottom:15px;">❌</div>
                    <h3>Invalid Document</h3>
                    <p style="margin:15px 0;">The document ID is invalid. Please select a document from the dashboard.</p>
                    <a href="documents.php" style="display:inline-block; margin-top:15px; padding:12px 30px; background:#dc3545; color:white; border-radius:8px; text-decoration:none; font-weight:600;">
                        📋 View Documents
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script src="js/dashboard.js"></script>
<script>
// ========== SELECT ALL / CLEAR FUNCTIONS ==========

// Get all visible checkboxes
function getVisibleCheckboxes() {
    const rows = document.querySelectorAll('.send-user-row');
    const visibleCheckboxes = [];
    rows.forEach(row => {
        const style = window.getComputedStyle(row);
        if (style.display !== 'none' && style.visibility !== 'hidden') {
            const cb = row.querySelector('.send-checkbox');
            if (cb) visibleCheckboxes.push(cb);
        }
    });
    return visibleCheckboxes;
}

// Update the selected count display
function updateSelectedCount() {
    const count = document.querySelectorAll('.send-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = count;
}

// Update the header checkbox state (checked if all visible selected, indeterminate if some)
function updateHeaderCheckbox() {
    const headerCheckbox = document.getElementById('selectAllVisible');
    if (!headerCheckbox) return;
    const visibleCheckboxes = getVisibleCheckboxes();
    const allChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.every(cb => cb.checked);
    const someChecked = visibleCheckboxes.some(cb => cb.checked);
    headerCheckbox.checked = allChecked;
    headerCheckbox.indeterminate = !allChecked && someChecked;
}

// Select all visible checkboxes
function selectAllVisible() {
    console.log("Select all visible");
    const visibleCheckboxes = getVisibleCheckboxes();
    visibleCheckboxes.forEach(cb => {
        if (!cb.checked) {
            cb.checked = true;
            // update row background
            const row = cb.closest('.send-user-row');
            if (row) row.style.background = 'rgba(2,128,144,0.08)';
        }
    });
    updateSelectedCount();
    updateHeaderCheckbox();
}

// Clear all visible checkboxes
function clearAll() {
    console.log("Clear all visible");
    const visibleCheckboxes = getVisibleCheckboxes();
    visibleCheckboxes.forEach(cb => {
        if (cb.checked) {
            cb.checked = false;
            const row = cb.closest('.send-user-row');
            if (row) row.style.background = '';
        }
    });
    updateSelectedCount();
    updateHeaderCheckbox();
}

// When header checkbox is clicked, select or deselect all visible based on its new state
function headerCheckboxClicked() {
    const headerCheckbox = document.getElementById('selectAllVisible');
    if (headerCheckbox.checked) {
        selectAllVisible();
    } else {
        clearAll();
    }
}

// ========== OTHER EXISTING FUNCTIONS (keep them as they are) ==========

function toggleRowCheck(row) {
    const cb = row.querySelector('.send-checkbox');
    cb.checked = !cb.checked;
    row.style.background = cb.checked ? 'rgba(2,128,144,0.08)' : '';
    updateSelectedCount();
    updateHeaderCheckbox();
}

function filterUsers() {
    const search = document.getElementById('userSearch').value.toLowerCase();
    const classVal = document.getElementById('classFilter').value.toLowerCase();
    document.querySelectorAll('.send-user-row').forEach(row => {
        const nameMatch = row.dataset.name.includes(search) || row.dataset.email.includes(search);
        const classMatch = !classVal || row.dataset.class === classVal;
        row.style.display = (nameMatch && classMatch) ? '' : 'none';
    });
    updateHeaderCheckbox();
}

function filterByClassSend(val) {
    const search = document.getElementById('userSearch').value.toLowerCase();
    document.querySelectorAll('.send-user-row').forEach(row => {
        const nameMatch = !search || row.dataset.name.includes(search) || row.dataset.email.includes(search);
        const classMatch = !val || row.dataset.class === val.toLowerCase();
        row.style.display = (nameMatch && classMatch) ? '' : 'none';
    });
    updateHeaderCheckbox();
}

function selectSendType(type) {
    document.querySelectorAll('.send-option').forEach(opt => {
        opt.classList.remove('active');
        opt.style.background = 'var(--card-bg)';
        opt.style.color = 'var(--text-primary)';
        const h4 = opt.querySelector('h4');
        if (h4) h4.style.color = 'var(--text-primary)';
    });
    event.currentTarget.classList.add('active');
    event.currentTarget.style.background = 'linear-gradient(135deg, #028090, #114B2F)';
    event.currentTarget.style.color = 'white';
    const h4 = event.currentTarget.querySelector('h4');
    if (h4) h4.style.color = 'white';
    document.getElementById('class-section').style.display = type === 'class' ? 'block' : 'none';
    document.getElementById('individual-section').style.display = type === 'individual' ? 'block' : 'none';
    document.querySelector('select[name="selected_class"]').required = (type === 'class');
}

// ========== INITIALIZATION ==========
document.addEventListener('DOMContentLoaded', function() {
    // Attach header checkbox event (override any existing onclick)
    const headerCheckbox = document.getElementById('selectAllVisible');
    if (headerCheckbox) {
        headerCheckbox.onclick = headerCheckboxClicked;
    }

    // Attach change listeners to individual checkboxes to keep header in sync
    document.querySelectorAll('.send-checkbox').forEach(cb => {
        cb.addEventListener('change', function() {
            const row = this.closest('.send-user-row');
            if (row) row.style.background = this.checked ? 'rgba(2,128,144,0.08)' : '';
            updateSelectedCount();
            updateHeaderCheckbox();
        });
    });

    // Initial updates
    updateSelectedCount();
    updateHeaderCheckbox();
});
</script>
</body>
</html>