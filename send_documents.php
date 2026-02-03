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

$doc_info = null;
if ($doc_id > 0) {
    $result = $connect->query("SELECT doc_name, token, key_id FROM documents WHERE id=$doc_id");
    if ($result && $result->num_rows > 0) {
        $doc_info = $result->fetch_assoc();
    }
}

$classes_result = $connect->query("SELECT DISTINCT user_class FROM users WHERE user_class IS NOT NULL AND user_class != 'unassigned' ORDER BY user_class");
$classes = [];
while ($row = $classes_result->fetch_assoc()) {
    $classes[] = $row['user_class'];
}

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
        } else {
            $stmt = $connect->prepare("SELECT user_id FROM users WHERE user_class=? AND role='user'");
            $stmt->bind_param("s", $selected_class);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $recipients[] = $row['user_id'];
            }
            $stmt->close();
        }
    } elseif ($send_type === 'individual') {
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
                    <div style="background:rgba(2, 128, 144, 0.1); padding:20px; border-radius:10px; margin-bottom:25px; border-left:4px solid #028090;">
                        <h3 style="color:#028090; margin-bottom:8px;">📄 <?php echo htmlspecialchars($doc_info['doc_name']); ?></h3>
                        <p style="color:var(--text-secondary); margin:0;"><strong>Verification Key:</strong> <code><?php echo htmlspecialchars($doc_info['key_id']); ?></code></p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div style="background:#f8d7da; color:#721c24; padding:15px; border-radius:8px; margin-bottom:20px; border-left:4px solid #dc3545;">
                            ❌ <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div style="background:linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color:white; padding:30px; border-radius:15px; text-align:center;">
                            <h3 style="margin-bottom:15px;">✅ Document Sent Successfully!</h3>
                            <p style="margin:15px 0; opacity:0.95;">The document has been delivered to the selected recipients.</p>
                            <div style="display:flex; gap:15px; margin-top:25px;">
                                <a href="documents.php" style="flex:1; padding:12px; background:rgba(255,255,255,0.3); color:white; border-radius:8px; text-decoration:none; text-align:center; font-weight:600;">
                                    📋 View All Documents
                                </a>
                                <a href="upload.php" style="flex:1; padding:12px; background:rgba(255,255,255,0.3); color:white; border-radius:8px; text-decoration:none; text-align:center; font-weight:600;">
                                    📤 Upload New
                                </a>
                                <a href="dashboard.php" style="flex:1; padding:12px; background:rgba(255,255,255,0.3); color:white; border-radius:8px; text-decoration:none; text-align:center; font-weight:600;">
                                    🏠 Dashboard
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                    
                    <form method="post">
                        <div style="display:flex; gap:15px; margin-bottom:25px;">
                            <label style="flex:1; padding:20px; border:2px solid var(--sidebar-border); border-radius:10px; cursor:pointer; text-align:center; background:linear-gradient(135deg, #028090, #114B2F); color:white;" class="send-option active" onclick="selectSendType('class')">
                                <input type="radio" name="send_type" value="class" checked style="display:none;">
                                <div style="font-size:32px; margin-bottom:10px;">📚</div>
                                <h4 style="margin:0;">Send to Entire Class</h4>
                            </label>
                            <label style="flex:1; padding:20px; border:2px solid var(--sidebar-border); border-radius:10px; cursor:pointer; text-align:center; background:var(--card-bg); color:var(--text-primary);" class="send-option" onclick="selectSendType('individual')">
                                <input type="radio" name="send_type" value="individual" style="display:none;">
                                <div style="font-size:32px; margin-bottom:10px;">👤</div>
                                <h4 style="margin:0; color:var(--text-primary);">Send to Individuals</h4>
                            </label>
                        </div>
                        
                        <div id="class-section" class="form-section" style="display:block; margin-bottom:20px;">
                            <label style="display:block; margin-bottom:12px; font-weight:600; color:var(--text-primary); font-size:16px;">📚 Select Class</label>
                            <select name="selected_class" required style="width:100%; padding:15px; border:2px solid var(--sidebar-border); border-radius:8px; background:var(--card-bg); color:var(--text-primary); font-size:15px;">
                                <option value="">-- Choose a class --</option>
                                <?php foreach ($classes as $class): 
                                    $count = $connect->query("SELECT COUNT(*) as c FROM users WHERE user_class='$class' AND role='user'")->fetch_assoc()['c'];
                                ?>
                                    <option value="<?php echo htmlspecialchars($class); ?>">
                                        <?php echo htmlspecialchars($class); ?> (<?php echo $count; ?> students)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small style="color:var(--text-secondary); display:block; margin-top:8px;">
                                The document will be sent to all students in the selected class
                            </small>
                        </div>
                        
                        <div id="individual-section" class="form-section" style="display:none; margin-bottom:20px;">
                            <label style="display:block; margin-bottom:12px; font-weight:600; color:var(--text-primary); font-size:16px;">👥 Select Users</label>
                            <div style="margin-bottom:15px;">
                                <input type="text" id="userSearch" placeholder="🔍 Search by name or email..." style="width:100%; padding:12px; border:2px solid var(--sidebar-border); border-radius:8px; background:var(--card-bg); color:var(--text-primary);" onkeyup="filterUsers()">
                            </div>
                            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:15px; max-height:450px; overflow-y:auto; padding:15px; background:var(--table-bg); border-radius:10px; border:1px solid var(--sidebar-border);">
                                <?php 
                                $current_class = '';
                                foreach ($users as $user): 
                                    if ($user['user_class'] != $current_class && !empty($user['user_class']) && $user['user_class'] != 'unassigned') {
                                        $current_class = $user['user_class'];
                                        echo '<div style="grid-column: 1/-1; padding:10px 0; font-weight:600; color:var(--text-primary); border-bottom:2px solid var(--sidebar-border); margin-bottom:10px;">📖 ' . htmlspecialchars($current_class) . '</div>';
                                    }
                                ?>
                                    <div class="user-card" onclick="toggleUser(this, event)" style="background:var(--card-bg); padding:15px; border-radius:8px; border:2px solid var(--sidebar-border); cursor:pointer;" data-name="<?php echo strtolower($user['name']); ?>" data-email="<?php echo strtolower($user['email']); ?>">
                                        <input type="checkbox" name="user_ids[]" value="<?php echo $user['user_id']; ?>" style="display:none;">
                                        <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
                                            <div style="width:40px; height:40px; border-radius:50%; background:linear-gradient(135deg, #028090, #114B2F); color:white; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:16px;">
                                                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                            </div>
                                            <div style="flex:1;">
                                                <div style="font-weight:600; margin-bottom:3px;"><?php echo htmlspecialchars($user['name']); ?></div>
                                                <div style="font-size:12px; opacity:0.7;"><?php echo htmlspecialchars($user['email']); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div style="margin-top:10px; text-align:center; color:var(--text-secondary); font-size:14px;">
                                <span id="selectedCount">0</span> user(s) selected
                            </div>
                        </div>
                        
                        <button type="submit" style="width:100%; padding:15px; background:linear-gradient(135deg, #028090, #114B2F); color:white; border:none; border-radius:8px; font-size:18px; font-weight:600; cursor:pointer; transition:all 0.3s;">
                            📤 Send Document to Recipients
                        </button>
                    </form>
                    
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="background:#f8d7da; color:#721c24; padding:20px; border-radius:8px; border-left:4px solid #dc3545;">
                    ❌ Invalid document ID. Please select a document from the dashboard.
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script src="js/dashboard.js"></script>
<script>
function selectSendType(type) {
    document.querySelectorAll('.send-option').forEach(opt => {
        opt.classList.remove('active');
        opt.style.background = 'var(--card-bg)';
        const h4 = opt.querySelector('h4');
        if (h4) h4.style.color = 'var(--text-primary)';
    });
    event.currentTarget.classList.add('active');
    event.currentTarget.style.background = 'linear-gradient(135deg, #028090, #114B2F)';
    event.currentTarget.style.color = 'white';
    const h4 = event.currentTarget.querySelector('h4');
    if (h4) h4.style.color = 'white';
    
    document.getElementById('class-section').style.display = 'none';
    document.getElementById('individual-section').style.display = 'none';
    
    if (type === 'class') {
        document.getElementById('class-section').style.display = 'block';
        document.querySelector('select[name="selected_class"]').required = true;
    } else {
        document.getElementById('individual-section').style.display = 'block';
        document.querySelector('select[name="selected_class"]').required = false;
    }
}

function toggleUser(card, event) {
    event.stopPropagation();
    card.classList.toggle('selected');
    const checkbox = card.querySelector('input[type="checkbox"]');
    checkbox.checked = !checkbox.checked;
    
    if (card.classList.contains('selected')) {
        card.style.borderColor = '#028090';
        card.style.background = 'linear-gradient(135deg, #028090, #114B2F)';
        card.style.color = 'white';
        const divs = card.querySelectorAll('div');
        divs.forEach(d => d.style.color = 'white');
    } else {
        card.style.borderColor = 'var(--sidebar-border)';
        card.style.background = 'var(--card-bg)';
        card.style.color = 'var(--text-primary)';
    }
    
    updateSelectedCount();
}

function updateSelectedCount() {
    const count = document.querySelectorAll('.user-card.selected').length;
    document.getElementById('selectedCount').textContent = count;
}

function filterUsers() {
    const search = document.getElementById('userSearch').value.toLowerCase();
    const cards = document.querySelectorAll('.user-card');
    
    cards.forEach(card => {
        const name = card.dataset.name;
        const email = card.dataset.email;
        const matches = name.includes(search) || email.includes(search);
        card.style.display = matches ? '' : 'none';
    });
}
</script>
</body>
</html>