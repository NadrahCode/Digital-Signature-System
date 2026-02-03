<?php
session_start();
require('inc/db_config.php');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: homepage.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];
$user_email = $_SESSION['email'];
$user_role = $_SESSION['role'];
$profile_img = $_SESSION['profile_img'] ?? '';

$error = "";
$success = "";

// Handle class management actions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_class') {
        $class_name = trim($_POST['class_name']);
        $description = trim($_POST['description'] ?? '');
        
        if (!empty($class_name)) {
            $stmt = $connect->prepare("INSERT INTO classes (class_name, description, created_by) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $class_name, $description, $user_id);
            
            if ($stmt->execute()) {
                $success = "✅ Class '$class_name' created successfully!";
            } else {
                if ($connect->errno === 1062) {
                    $error = "❌ Class name already exists.";
                } else {
                    $error = "❌ Failed to create class.";
                }
            }
            $stmt->close();
        } else {
            $error = "❌ Class name is required.";
        }
    } 
    elseif ($action === 'edit_class') {
        $class_id = intval($_POST['class_id']);
        $old_name = trim($_POST['old_name']);
        $new_name = trim($_POST['new_class_name']);
        $description = trim($_POST['description'] ?? '');
        
        if (!empty($new_name)) {
            // Update classes table
            $stmt = $connect->prepare("UPDATE classes SET class_name = ?, description = ? WHERE class_id = ?");
            $stmt->bind_param("ssi", $new_name, $description, $class_id);
            
            if ($stmt->execute()) {
                // Update users table to reflect new class name
                $stmt2 = $connect->prepare("UPDATE users SET user_class = ? WHERE user_class = ?");
                $stmt2->bind_param("ss", $new_name, $old_name);
                $stmt2->execute();
                $stmt2->close();
                
                $success = "✅ Class updated successfully!";
            } else {
                if ($connect->errno === 1062) {
                    $error = "❌ Class name already exists.";
                } else {
                    $error = "❌ Failed to update class.";
                }
            }
            $stmt->close();
        }
    }
    elseif ($action === 'delete_class') {
        $class_id = intval($_POST['class_id']);
        $class_name = trim($_POST['class_name']);
        
        // Unassign all users from this class
        $stmt = $connect->prepare("UPDATE users SET user_class = 'unassigned' WHERE user_class = ?");
        $stmt->bind_param("s", $class_name);
        $stmt->execute();
        $stmt->close();
        
        // Delete class
        $stmt = $connect->prepare("DELETE FROM classes WHERE class_id = ?");
        $stmt->bind_param("i", $class_id);
        
        if ($stmt->execute()) {
            $success = "✅ Class deleted and users unassigned!";
        } else {
            $error = "❌ Failed to delete class.";
        }
        $stmt->close();
    }
}

// Get all classes with student counts
$classes_query = "SELECT c.*, 
                  (SELECT COUNT(*) FROM users WHERE user_class = c.class_name AND role = 'user') as student_count,
                  (SELECT name FROM users WHERE user_id = c.created_by) as creator_name
                  FROM classes c
                  ORDER BY c.class_name";
$classes_result = $connect->query($classes_query);

// Get statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM classes) as total_classes,
    (SELECT COUNT(*) FROM users WHERE role='user' AND user_class IS NOT NULL AND user_class != 'unassigned') as assigned_students,
    (SELECT COUNT(*) FROM users WHERE role='user') as total_students
    FROM dual";
$stats = $connect->query($stats_query)->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Class Management | Digital Signature System</title>
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
.form-section {
    background: var(--card-bg);
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 4px 12px var(--shadow-color);
}
.section-title {
    font-size: 20px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--sidebar-border);
}
.input-group {
    display: grid;
    grid-template-columns: 1fr 2fr auto;
    gap: 15px;
    margin-bottom: 20px;
}
.input-group input, .input-group textarea {
    padding: 12px;
    border: 2px solid var(--sidebar-border);
    border-radius: 8px;
    background: var(--card-bg);
    color: var(--text-primary);
    font-size: 14px;
}
.input-group textarea {
    resize: vertical;
    min-height: 45px;
}
.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s;
}
.btn-success {
    background: #28a745;
    color: white;
}
.btn-primary {
    background: linear-gradient(135deg, #028090, #114B2F);
    color: white;
}
.btn-danger {
    background: #dc3545;
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
}
table {
    width: 100%;
    border-collapse: collapse;
    background: var(--card-bg);
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
.class-name {
    font-weight: 600;
    font-size: 16px;
    color: var(--text-primary);
    margin-bottom: 4px;
}
.class-desc {
    font-size: 13px;
    color: var(--text-secondary);
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
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.6);
    z-index: 9999;
    animation: fadeIn 0.3s;
}
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
.modal-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: var(--card-bg);
    padding: 30px;
    border-radius: 15px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
}
.modal-header {
    font-size: 22px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--sidebar-border);
}
.form-group {
    margin-bottom: 20px;
}
.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--text-primary);
}
.form-group input, .form-group textarea {
    width: 100%;
    padding: 12px;
    border: 2px solid var(--sidebar-border);
    border-radius: 8px;
    background: var(--card-bg);
    color: var(--text-primary);
}
.modal-actions {
    display: flex;
    gap: 10px;
    margin-top: 25px;
}
</style>
</head>
<body data-theme="light">
<div class="dashboard-container">
    <?php require('inc/sidebar.php'); ?>
    
    <main class="main-content">
        <?php require('inc/topheader.php'); ?>
        
        <section class="welcome-section">
            <h1 class="welcome-title">Class Management 📚</h1>
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
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="number"><?php echo $stats['total_classes']; ?></div>
                    <div class="label">Total Classes</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?php echo $stats['assigned_students']; ?></div>
                    <div class="label">Assigned Students</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?php echo $stats['total_students']; ?></div>
                    <div class="label">Total Students</div>
                </div>
            </div>
            
            <!-- Create Class Form -->
            <div class="form-section">
                <div class="section-title">➕ Create New Class</div>
                <form method="post">
                    <input type="hidden" name="action" value="add_class">
                    <div class="input-group">
                        <input type="text" name="class_name" placeholder="Class Name (e.g., CT206)" required>
                        <textarea name="description" placeholder="Description (optional)" rows="1"></textarea>
                        <button type="submit" class="btn btn-success">Create Class</button>
                    </div>
                </form>
            </div>
            
            <!-- Classes Table -->
            <div class="form-section">
                <div class="section-title">📋 All Classes</div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Class Name</th>
                                <th>Description</th>
                                <th>Students</th>
                                <th>Created By</th>
                                <th>Created Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($classes_result && $classes_result->num_rows > 0): ?>
                                <?php while ($class = $classes_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="class-name">📖 <?php echo htmlspecialchars($class['class_name']); ?></div>
                                        </td>
                                        <td>
                                            <div class="class-desc">
                                                <?php echo !empty($class['description']) ? htmlspecialchars($class['description']) : '<em>No description</em>'; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span style="font-weight:600; color:#028090; font-size:16px;">
                                                <?php echo $class['student_count']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($class['creator_name'] ?? 'System'); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($class['created_at'])); ?></td>
                                        <td>
                                            <div style="display:flex; gap:8px;">
                                                <button class="btn btn-primary" style="padding:8px 16px; font-size:12px;" 
                                                        onclick="editClass(<?php echo $class['class_id']; ?>, '<?php echo htmlspecialchars($class['class_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($class['description'] ?? '', ENT_QUOTES); ?>')">
                                                    ✏️ Edit
                                                </button>
                                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this class and unassign all students?')">
                                                    <input type="hidden" name="action" value="delete_class">
                                                    <input type="hidden" name="class_id" value="<?php echo $class['class_id']; ?>">
                                                    <input type="hidden" name="class_name" value="<?php echo htmlspecialchars($class['class_name']); ?>">
                                                    <button type="submit" class="btn btn-danger" style="padding:8px 16px; font-size:12px;">
                                                        🗑️ Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding:40px; color:var(--text-secondary);">
                                        No classes created yet. Create your first class above!
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div style="text-align:center; margin-top:20px;">
                <a href="user_list.php" class="btn btn-primary">
                    👥 Go to User Assignment →
                </a>
            </div>
        </div>
    </main>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">✏️ Edit Class</div>
        <form method="post">
            <input type="hidden" name="action" value="edit_class">
            <input type="hidden" name="class_id" id="edit_class_id">
            <input type="hidden" name="old_name" id="edit_old_name">
            
            <div class="form-group">
                <label>Class Name *</label>
                <input type="text" name="new_class_name" id="edit_class_name" required>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="edit_description" rows="3"></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="submit" class="btn btn-success" style="flex:1;">💾 Save Changes</button>
                <button type="button" class="btn btn-secondary" style="flex:1;" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script src="js/dashboard.js"></script>
<script>
function editClass(id, name, description) {
    document.getElementById('edit_class_id').value = id;
    document.getElementById('edit_old_name').value = name;
    document.getElementById('edit_class_name').value = name;
    document.getElementById('edit_description').value = description;
    document.getElementById('editModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editModal');
    if (event.target === modal) {
        closeModal();
    }
}
</script>
</body>
</html>