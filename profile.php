<?php
session_start();
require('inc/db_config.php');

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$user_id     = $_SESSION['user_id'];
$user_name   = $_SESSION['name'];
$user_email  = $_SESSION['email'];
$user_role   = $_SESSION['role'];
$profile_img = $_SESSION['profile_img'] ?? '';

$success_message   = '';
$error_message     = '';
$user_profile_data = [];
$all_users_data    = [];

function uploadProfileImage($file, $uid) {
    $dir = "uploads/profile/";
    if (!file_exists($dir)) mkdir($dir, 0777, true);
    $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    if (!getimagesize($file["tmp_name"])) return ['success'=>false,'message'=>'Not an image.'];
    if ($file["size"] > 2097152)          return ['success'=>false,'message'=>'Max 2MB.'];
    if (!in_array($ext,['jpg','jpeg','png','gif'])) return ['success'=>false,'message'=>'JPG/PNG/GIF only.'];
    $filename = "user_{$uid}_".time().".$ext";
    if (move_uploaded_file($file["tmp_name"], $dir.$filename)) return ['success'=>true,'filename'=>$filename];
    return ['success'=>false,'message'=>'Upload failed.'];
}

// ── PROFILE UPDATE (user / admin) ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_profile']) && $user_role!=='superadmin') {
    $name  = mysqli_real_escape_string($connect, $_POST['name']);
    $email = mysqli_real_escape_string($connect, $_POST['email']);
    $cur   = $_POST['current_password'] ?? '';
    $new   = $_POST['new_password'] ?? '';
    $con   = $_POST['confirm_password'] ?? '';
    $use_gravatar = isset($_POST['use_gravatar']);
    $errors = [];

    // Check for email duplication
    if ($email !== $user_email) {
        $s = mysqli_prepare($connect,"SELECT user_id FROM users WHERE email=? AND user_id!=?");
        mysqli_stmt_bind_param($s,"si",$email,$user_id);
        mysqli_stmt_execute($s); mysqli_stmt_store_result($s);
        if (mysqli_stmt_num_rows($s)>0) $errors[]='Email already taken.';
        mysqli_stmt_close($s);
    }

    // Password validation if any password field is filled
    $password_changed = false;
    if (!empty($cur) || !empty($new) || !empty($con)) {
        $s = mysqli_prepare($connect,"SELECT password FROM users WHERE user_id=?");
        mysqli_stmt_bind_param($s,"i",$user_id); mysqli_stmt_execute($s);
        mysqli_stmt_bind_result($s,$db_pw); mysqli_stmt_fetch($s); mysqli_stmt_close($s);
        if ($db_pw!==$cur) $errors[]='Current password incorrect.';
        if (strlen($new)<8) $errors[]='Min 8 characters.';
        if (!preg_match('/[!@#$%^&*()]/',$new)) $errors[]='Need a special character.';
        if ($new!==$con) $errors[]='Passwords do not match.';
        if (!empty($new) && $new === $db_pw) {
            // If new password equals old password, treat as no change
            $password_changed = false;
        } else {
            $password_changed = !empty($new);
        }
    }

    // Profile image handling
    $pic = $profile_img;
    $image_changed = false;
    if (!$use_gravatar && isset($_FILES['profile_image']) && $_FILES['profile_image']['error']==0) {
        $r = uploadProfileImage($_FILES['profile_image'],$user_id);
        if ($r['success']) {
            $pic = $r['filename'];
            $image_changed = true;
        } else {
            $errors[]=$r['message'];
        }
    } elseif ($use_gravatar) {
        // If gravatar is selected and it's different from current (i.e. current has a custom image)
        if (!empty($profile_img)) {
            $image_changed = true;
        }
        $pic = '';
    }

    // Detect if any real change happened
    $name_changed  = ($name !== $user_name);
    $email_changed = ($email !== $user_email);
    $any_change    = $name_changed || $email_changed || $password_changed || $image_changed;

    if (empty($errors) && $any_change) {
        if (!empty($new) && $password_changed) {
            $s = mysqli_prepare($connect,"UPDATE users SET name=?,email=?,password=?,profile_img=? WHERE user_id=?");
            mysqli_stmt_bind_param($s,"ssssi",$name,$email,$new,$pic,$user_id);
        } else {
            $s = mysqli_prepare($connect,"UPDATE users SET name=?,email=?,profile_img=? WHERE user_id=?");
            mysqli_stmt_bind_param($s,"sssi",$name,$email,$pic,$user_id);
        }
        if (mysqli_stmt_execute($s)) {
            $_SESSION['name']=$name; $_SESSION['email']=$email; $_SESSION['profile_img']=$pic;
            $user_name=$name; $user_email=$email; $profile_img=$pic;
            $success_message='Profile updated successfully!';
        } else {
            $errors[]='Update failed.';
        }
        mysqli_stmt_close($s);
    } elseif (empty($errors) && !$any_change) {
        $success_message = 'Nothing changes';
    }
    if (!empty($errors)) $error_message=implode('<br>',$errors);
}

// ── SUPERADMIN ACTIONS ─────────────────────────────────────────────────
if ($user_role==='superadmin') {

    // Add user
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_user'])) {
        $nn=$_POST['new_name']; $ne=$_POST['new_email'];
        $np=$_POST['new_password']; $nc=$_POST['confirm_password'];
        $nr=$_POST['new_role']; $ns=$_POST['new_status'];
        $errors=[];
        if (strlen($nn)<2) $errors[]='Name too short.';
        if (!filter_var($ne,FILTER_VALIDATE_EMAIL)) $errors[]='Invalid email.';
        if (strlen($np)<8) $errors[]='Min 8 chars.';
        if (!preg_match('/[!@#$%^&*()]/',$np)) $errors[]='Need special char.';
        if ($np!==$nc) $errors[]='Passwords mismatch.';
        $s=mysqli_prepare($connect,"SELECT user_id FROM users WHERE email=?");
        mysqli_stmt_bind_param($s,"s",$ne); mysqli_stmt_execute($s); mysqli_stmt_store_result($s);
        if (mysqli_stmt_num_rows($s)>0) $errors[]='Email taken.'; mysqli_stmt_close($s);
        $pic='';
        if (isset($_FILES['profile_image_new']) && $_FILES['profile_image_new']['error']==0) {
            $r=uploadProfileImage($_FILES['profile_image_new'],'new');
            if ($r['success']) $pic=$r['filename']; else $errors[]=$r['message'];
        }
        if (empty($errors)) {
            $s=mysqli_prepare($connect,"INSERT INTO users (name,email,password,role,status,profile_img,created_at) VALUES (?,?,?,?,?,?,NOW())");
            mysqli_stmt_bind_param($s,"ssssss",$nn,$ne,$np,$nr,$ns,$pic);
            if (mysqli_stmt_execute($s)) $success_message='User added successfully!'; else $errors[]='Insert failed.';
            mysqli_stmt_close($s);
        }
        if (!empty($errors)) $error_message=implode('<br>',$errors);
    }

    // Update user
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_user_admin'])) {
        $uid2=intval($_POST['update_user_id']);
        $un=$_POST['update_name']; $ue=$_POST['update_email'];
        $up=$_POST['update_password']; $uc=$_POST['update_confirm_password'];
        $ur=$_POST['update_role']; $us=$_POST['update_status'];
        $errors=[];
        if ($uid2==$user_id && $ur!=='superadmin') $errors[]='Cannot demote yourself.';
        if ($ue!==$_POST['original_email']) {
            $s=mysqli_prepare($connect,"SELECT user_id FROM users WHERE email=? AND user_id!=?");
            mysqli_stmt_bind_param($s,"si",$ue,$uid2); mysqli_stmt_execute($s); mysqli_stmt_store_result($s);
            if (mysqli_stmt_num_rows($s)>0) $errors[]='Email taken.'; mysqli_stmt_close($s);
        }
        if (!empty($up)) {
            if (strlen($up)<8) $errors[]='Min 8 chars.';
            if (!preg_match('/[!@#$%^&*()]/',$up)) $errors[]='Need special char.';
            if ($up!==$uc) $errors[]='Passwords mismatch.';
        }
        $pic2=$_POST['current_profile_img'];
        if (isset($_FILES['profile_image_update']) && $_FILES['profile_image_update']['error']==0) {
            $r=uploadProfileImage($_FILES['profile_image_update'],$uid2);
            if ($r['success']) $pic2=$r['filename']; else $errors[]=$r['message'];
        }
        if (isset($_POST['use_gravatar_update'])) $pic2='';
        if (empty($errors)) {
            if (!empty($up)) {
                $s=mysqli_prepare($connect,"UPDATE users SET name=?,email=?,password=?,role=?,status=?,profile_img=? WHERE user_id=?");
                mysqli_stmt_bind_param($s,"ssssssi",$un,$ue,$up,$ur,$us,$pic2,$uid2);
            } else {
                $s=mysqli_prepare($connect,"UPDATE users SET name=?,email=?,role=?,status=?,profile_img=? WHERE user_id=?");
                mysqli_stmt_bind_param($s,"sssssi",$un,$ue,$ur,$us,$pic2,$uid2);
            }
            if (mysqli_stmt_execute($s)) {
                if ($uid2==$user_id) { $_SESSION['name']=$un; $_SESSION['email']=$ue; $_SESSION['role']=$ur; $_SESSION['profile_img']=$pic2; }
                $success_message='User updated successfully!';
            } else $errors[]='Update failed.';
            mysqli_stmt_close($s);
        }
        if (!empty($errors)) $error_message=implode('<br>',$errors);
    }

    // Delete user
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_user'])) {
        $did=intval($_POST['delete_user_id']);
        if ($did==$user_id) {
            $error_message='Cannot delete your own account.';
        } else {
            $connect->query("DELETE FROM document_recipients WHERE user_id=$did");
            $s=mysqli_prepare($connect,"DELETE FROM users WHERE user_id=?");
            mysqli_stmt_bind_param($s,"i",$did);
            if (mysqli_stmt_execute($s)) $success_message='User deleted successfully!';
            else $error_message='Delete failed: '.mysqli_error($connect);
            mysqli_stmt_close($s);
        }
    }

    // Bulk actions
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['bulk_action'])) {
        $act=$_POST['bulk_action']; $sel=$_POST['selected_users']??[];
        if (empty($sel)) { $error_message='No users selected.'; }
        else {
            $ok=0;
            foreach ($sel as $bid) {
                $bid=intval($bid);
                if ($bid==$user_id && in_array($act,['delete','inactive'])) continue;
                if ($act==='delete') { $connect->query("DELETE FROM document_recipients WHERE user_id=$bid"); $q="DELETE FROM users WHERE user_id=?"; }
                elseif ($act==='active')   $q="UPDATE users SET status='active' WHERE user_id=?";
                elseif ($act==='inactive') $q="UPDATE users SET status='inactive' WHERE user_id=?";
                else continue;
                $s=mysqli_prepare($connect,$q); mysqli_stmt_bind_param($s,"i",$bid);
                if (mysqli_stmt_execute($s)) $ok++;
                mysqli_stmt_close($s);
            }
            if ($ok) $success_message="$ok user(s) updated.";
        }
    }

    // Fetch user list
    $sq = isset($_GET['search']) ? mysqli_real_escape_string($connect,$_GET['search']) : '';
    $rf = isset($_GET['role_filter']) ? mysqli_real_escape_string($connect,$_GET['role_filter']) : '';
    $q  = "SELECT * FROM users WHERE 1=1";
    $params=[]; $types='';
    if ($sq) { $q.=" AND (name LIKE ? OR email LIKE ?)"; $p="%$sq%"; $params[]=$p; $params[]=$p; $types.='ss'; }
    if ($rf) { $q.=" AND role=?"; $params[]=$rf; $types.='s'; }
    $q.=" ORDER BY created_at DESC";
    $s=mysqli_prepare($connect,$q);
    if ($params) mysqli_stmt_bind_param($s,$types,...$params);
    mysqli_stmt_execute($s);
    $result=mysqli_stmt_get_result($s);
    while ($row=mysqli_fetch_assoc($result)) $all_users_data[]=$row;
    mysqli_stmt_close($s);
}

// Fetch own profile (non-superadmin)
if ($user_role!=='superadmin') {
    $s=mysqli_prepare($connect,"SELECT * FROM users WHERE user_id=?");
    mysqli_stmt_bind_param($s,"i",$user_id); mysqli_stmt_execute($s);
    $user_profile_data=mysqli_fetch_assoc(mysqli_stmt_get_result($s));
    mysqli_stmt_close($s);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profile | DIGITAL SIGNATURE SYSTEM</title>
<?php require('inc/links.php'); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="css/design.css">
<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/profile.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>
<style>
/* ── documents.php table style ── */
.stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:20px; margin-bottom:30px; }
.stat-card { background:linear-gradient(135deg,#028090,#114B2F); color:white; padding:25px; border-radius:12px; text-align:center; }
.stat-card .number { font-size:36px; font-weight:bold; margin-bottom:8px; }
.stat-card .label  { font-size:14px; opacity:.9; }
.toolbar { display:flex; gap:15px; margin-bottom:25px; flex-wrap:wrap; align-items:center; }
.toolbar input, .toolbar select { padding:12px 15px; border:2px solid var(--sidebar-border); border-radius:8px; background:var(--card-bg); color:var(--text-primary); font-size:14px; }
.toolbar input { flex:1; min-width:220px; }
.btn { padding:10px 20px; border:none; border-radius:8px; cursor:pointer; font-size:14px; font-weight:600; transition:all .3s; text-decoration:none; display:inline-block; }
.btn-primary { background:linear-gradient(135deg,#028090,#114B2F); color:white; }
.btn-danger  { background:#dc3545; color:white; }
.btn-sec     { background:var(--table-bg); color:var(--text-primary); border:1px solid var(--sidebar-border); }
.btn:hover   { transform:translateY(-2px); box-shadow:0 3px 10px rgba(0,0,0,.2); }
.btn-sm { padding:6px 12px; font-size:12px; }
.table-container { overflow-x:auto; border-radius:10px; border:1px solid var(--sidebar-border); background:var(--card-bg); }
.table-container table { width:100%; border-collapse:collapse; }
.table-container thead { background:linear-gradient(135deg,#028090,#114B2F); color:white; }
.table-container th { padding:15px; text-align:left; font-weight:600; font-size:14px; white-space:nowrap; }
.table-container td { padding:15px; border-bottom:1px solid var(--sidebar-border); color:var(--text-primary); vertical-align:middle; }
.table-container tr:last-child td { border-bottom:none; }
.table-container tr:hover { background:var(--table-bg); }
.action-buttons { display:flex; gap:8px; flex-wrap:wrap; }
.alert { padding:15px 20px; border-radius:8px; margin-bottom:20px; }
.alert-success { background:#d4edda; color:#155724; border-left:4px solid #28a745; }
.alert-danger  { background:#f8d7da; color:#721c24; border-left:4px solid #dc3545; }
/* badges */
.rb { display:inline-block; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:600; }
.rb-user       { background:#e7f3ff; color:#0055bb; }
.rb-admin      { background:#fff3cd; color:#7a5c00; }
.rb-superadmin { background:#fde8f0; color:#8b1e3f; }
.sb { display:inline-block; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:600; }
.sb-active   { background:#d4edda; color:#155724; }
.sb-inactive { background:#f8d7da; color:#721c24; }
.av-wrap { display:flex; align-items:center; gap:10px; }
.av { width:34px; height:34px; border-radius:50%; object-fit:cover; }
.av-init { width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,#028090,#114B2F); color:#fff; font-weight:700; font-size:14px; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0; }
/* modal */
.mo { display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:9999; align-items:center; justify-content:center; }
.mo.open { display:flex; animation:fadeIn .2s ease; }
.mc { background:var(--card-bg); border-radius:16px; padding:28px; width:90%; max-width:480px; box-shadow:0 16px 48px rgba(0,0,0,.25); position:relative; }
.mc.wide { max-width:660px; }
.mc h3  { margin:0 0 20px; font-size:18px; color:var(--text-primary); }
.mc .close-x { position:absolute; top:14px; right:18px; background:none; border:none; font-size:22px; cursor:pointer; color:var(--text-secondary); }
.mc label  { display:block; margin-bottom:6px; font-size:13px; font-weight:600; color:var(--text-secondary); }
.mc input, .mc select { width:100%; padding:9px 12px; border:1px solid var(--sidebar-border); border-radius:8px; background:var(--card-bg); color:var(--text-primary); font-size:13px; margin-bottom:14px; box-sizing:border-box; }
.mc-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.mc-actions { display:flex; gap:10px; margin-top:6px; justify-content:flex-end; }
.del-box { background:#fff5f5; border:1px solid #fca5a5; border-radius:10px; padding:14px 16px; margin-bottom:18px; font-size:14px; color:#7f1d1d; }
/* bulk bar */
#bulkBar { display:none; background:var(--card-bg); border:2px solid #028090; border-radius:10px; padding:12px 18px; margin-bottom:16px; }
/* error message inside modal */
.modal-error { background:#f8d7da; color:#721c24; padding:10px 15px; border-radius:6px; margin-bottom:15px; font-size:13px; display:none; }
</style>
</head>
<body data-theme="light">
<div class="dashboard-container">
    <?php require('inc/sidebar.php'); ?>
    <main class="main-content">
        <?php require('inc/topheader.php'); ?>

        <section class="welcome-section">
            <h1 class="welcome-title">Profile Management</h1>
            <h2 class="system-title"><?php echo $user_role==='superadmin'?'User Management':'Manage Your Profile'; ?></h2>
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

        <!-- ── PERSONAL PROFILE (user / admin) ── -->
        <?php if ($user_role!=='superadmin'): ?>
        <section class="profile-card-section">
            <div class="profile-card">
                <div class="card-header">
                    <h3><i class="bi bi-person-circle"></i> Personal Profile</h3>
                    <span class="role-badge profile-role"><?php echo ucfirst($user_role); ?></span>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-grid">
                            <div class="form-group profile-image-section">
                                <div class="image-preview">
                                    <?php if (!empty($user_profile_data['profile_img'])): ?>
                                        <img src="uploads/profile/<?php echo htmlspecialchars($user_profile_data['profile_img']); ?>" id="imagePreview" class="profile-preview">
                                    <?php else: ?>
                                        <img src="https://www.gravatar.com/avatar/<?php echo md5(strtolower(trim($user_profile_data['email']))); ?>?s=200&d=identicon" id="imagePreview" class="profile-preview">
                                    <?php endif; ?>
                                    <div class="image-overlay"><i class="bi bi-camera"></i><span>Change Photo</span></div>
                                    <input type="file" name="profile_image" id="profileImage" accept="image/*" class="image-input">
                                </div>
                                <div class="image-options">
                                    <label class="checkbox-option">
                                        <input type="checkbox" id="useGravatar" name="use_gravatar" <?php echo empty($user_profile_data['profile_img'])?'checked':''; ?>>
                                        <span>Use Gravatar</span>
                                    </label>
                                </div>
                            </div>
                            <div class="form-fields">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label><i class="bi bi-person"></i> Full Name *</label>
                                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user_profile_data['name']); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label><i class="bi bi-envelope"></i> Email *</label>
                                        <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user_profile_data['email']); ?>" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label><i class="bi bi-lock"></i> Current Password</label>
                                    <input type="password" name="current_password" class="form-control" placeholder="Only needed to change password">
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label><i class="bi bi-lock-fill"></i> New Password</label>
                                        <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Leave blank to keep current">
                                    </div>
                                    <div class="form-group">
                                        <label><i class="bi bi-lock-fill"></i> Confirm</label>
                                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Repeat new password">
                                    </div>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" name="update_profile" class="btn btn-primary"><i class="bi bi-save"></i> Save Changes</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- ── USER MANAGEMENT TABLE (superadmin) ── -->
        <?php if ($user_role==='superadmin'): ?>

            <!-- Stats -->
            <?php
            $total_users = count($all_users_data);
            $active_count = count(array_filter($all_users_data, fn($u)=>$u['status']==='active'));
            $admin_count  = count(array_filter($all_users_data, fn($u)=>in_array($u['role'],['admin','superadmin'])));
            ?>
            <div class="stats-grid">
                <div class="stat-card"><div class="number"><?php echo $total_users; ?></div><div class="label">Total Users</div></div>
                <div class="stat-card"><div class="number"><?php echo $active_count; ?></div><div class="label">Active Users</div></div>
                <div class="stat-card"><div class="number"><?php echo $admin_count; ?></div><div class="label">Admins</div></div>
            </div>

            <!-- Toolbar -->
            <div class="toolbar">
                <form method="GET" style="display:contents;">
                    <input type="text" name="search" placeholder="🔍 Search users..." value="<?php echo htmlspecialchars($sq??''); ?>">
                    <select name="role_filter">
                        <option value="">All Roles</option>
                        <option value="user"       <?php echo ($rf??'')==='user'?'selected':''; ?>>User</option>
                        <option value="admin"      <?php echo ($rf??'')==='admin'?'selected':''; ?>>Admin</option>
                        <option value="superadmin" <?php echo ($rf??'')==='superadmin'?'selected':''; ?>>Superadmin</option>
                    </select>
                    <button type="submit" class="btn btn-sec"><i class="bi bi-funnel"></i> Filter</button>
                    <a href="profile.php" class="btn btn-sec"><i class="bi bi-x"></i> Clear</a>
                </form>
                <button class="btn btn-primary" onclick="openModal('addModal')"><i class="bi bi-person-plus"></i> Add User</button>
            </div>

            <!-- Bulk bar -->
            <div id="bulkBar">
                <form method="POST" id="bulkForm" onsubmit="return confirmBulk()">
                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                        <span id="bulkCount" style="font-weight:600;color:#028090;white-space:nowrap;">0 selected</span>
                        <select name="bulk_action" id="bulkActionSel" style="padding:8px 12px;border:1px solid var(--sidebar-border);border-radius:8px;background:var(--card-bg);color:var(--text-primary);font-size:13px;">
                            <option value="">Action…</option>
                            <option value="active">Activate</option>
                            <option value="inactive">Deactivate</option>
                            <option value="delete">Delete</option>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                        <button type="button" onclick="clearBulk()" class="btn btn-sec btn-sm">✕ Clear</button>
                    </div>
                </form>
            </div>

            <!-- Table -->
            <div class="table-container">
                <?php if (empty($all_users_data)): ?>
                    <div style="text-align:center;padding:60px 20px;color:var(--text-secondary);">
                        <div style="font-size:48px;margin-bottom:12px;opacity:.4;">👥</div>
                        <p>No users found.</p>
                    </div>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th style="width:36px;"><input type="checkbox" id="selAll" onchange="toggleSelAll()"></th>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($all_users_data as $u): ?>
                        <tr>
                            <td><input type="checkbox" class="usel" value="<?php echo $u['user_id']; ?>" form="bulkForm" name="selected_users[]" onchange="updateBulk()" <?php echo $u['user_id']==$user_id?'disabled':''; ?>></td>
                            <td>
                                <div class="av-wrap">
                                    <?php if (!empty($u['profile_img'])): ?>
                                        <img src="uploads/profile/<?php echo htmlspecialchars($u['profile_img']); ?>" class="av">
                                    <?php else: ?>
                                        <img src="https://www.gravatar.com/avatar/<?php echo md5(strtolower(trim($u['email']))); ?>?s=34&d=identicon" class="av">
                                    <?php endif; ?>
                                    <div>
                                        <div style="font-weight:600;"><?php echo htmlspecialchars($u['name']); ?></div>
                                        <?php if ($u['user_id']==$user_id): ?>
                                            <span style="font-size:10px;background:#e7f3ff;color:#0055bb;padding:1px 6px;border-radius:6px;">You</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td style="color:var(--text-secondary);"><?php echo htmlspecialchars($u['email']); ?></td>
                            <td><span class="rb rb-<?php echo $u['role']; ?>"><?php echo ucfirst($u['role']); ?></span></td>
                            <td><span class="sb sb-<?php echo $u['status']; ?>"><?php echo ucfirst($u['status']); ?></span></td>
                            <td style="color:var(--text-secondary);"><?php echo date('M j, Y',strtotime($u['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-sm btn-primary" title="Edit"
                                        onclick="openEdit(<?php echo $u['user_id']; ?>,'<?php echo addslashes($u['name']); ?>','<?php echo addslashes($u['email']); ?>','<?php echo $u['role']; ?>','<?php echo $u['status']; ?>','<?php echo addslashes($u['profile_img']??''); ?>')">
                                        ✏️ Edit
                                    </button>
                                    <button class="btn btn-sm btn-danger" title="Delete"
                                        onclick="openDelete(<?php echo $u['user_id']; ?>,'<?php echo addslashes($u['name']); ?>')"
                                        <?php echo $u['user_id']==$user_id?'disabled style="opacity:.4;cursor:not-allowed;"':''; ?>>
                                        🗑️ Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            <div style="margin-top:10px;font-size:12px;color:var(--text-secondary);"><?php echo count($all_users_data); ?> user(s) shown</div>

            <!-- ADD MODAL with hidden field and client-side validation -->
            <div id="addModal" class="mo">
                <div class="mc wide">
                    <button class="close-x" onclick="closeModal('addModal')">✕</button>
                    <h3><i class="bi bi-person-plus" style="color:#028090;"></i> Add New User</h3>
                    <div id="addModalError" class="modal-error"></div>
                    <form method="POST" enctype="multipart/form-data" id="addUserForm">
                        <input type="hidden" name="add_user" value="1"> <!-- FIX: ensures server sees the action -->
                        <div class="mc-row">
                            <div><label>Full Name *</label><input type="text" name="new_name" id="new_name" required minlength="2"></div>
                            <div><label>Email *</label><input type="email" name="new_email" id="new_email" required></div>
                        </div>
                        <div class="mc-row">
                            <div><label>Role *</label>
                                <select name="new_role" id="new_role">
                                    <option value="user">User</option>
                                    <option value="admin">Admin</option>
                                    <option value="superadmin">Superadmin</option>
                                </select>
                            </div>
                            <div><label>Status *</label>
                                <select name="new_status" id="new_status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="mc-row">
                            <div><label>Password *</label><input type="password" name="new_password" id="new_password" required minlength="8" pattern="(?=.*[!@#$%^&*()]).*" title="Must contain at least one special character (!@#$%^&*())"></div>
                            <div><label>Confirm Password *</label><input type="password" name="confirm_password" id="confirm_password_new" required></div>
                        </div>
                        <div class="mc-actions">
                            <button type="button" onclick="closeModal('addModal')" class="btn btn-sec">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-person-plus"></i> Add User</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- EDIT MODAL (improved password interface) -->
            <div id="editModal" class="mo">
                <div class="mc wide">
                    <button class="close-x" onclick="closeModal('editModal')">✕</button>
                    <h3><i class="bi bi-pencil-square" style="color:#028090;"></i> Edit User</h3>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="update_user_id" id="editId">
                        <input type="hidden" name="original_email" id="editOrigEmail">
                        <input type="hidden" name="current_profile_img" id="editCurPic">
                        <div class="mc-row">
                            <div><label>Full Name *</label><input type="text" name="update_name" id="editName" required></div>
                            <div><label>Email *</label><input type="email" name="update_email" id="editEmail" required></div>
                        </div>
                        <div class="mc-row">
                            <div><label>Role *</label>
                                <select name="update_role" id="editRole">
                                    <option value="user">User</option>
                                    <option value="admin">Admin</option>
                                    <option value="superadmin">Superadmin</option>
                                </select>
                            </div>
                            <div><label>Status *</label>
                                <select name="update_status" id="editStatus">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        <label style="display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:13px;cursor:pointer;">
                            <input type="checkbox" id="changePwCheck" onchange="document.getElementById('editPwFields').style.display=this.checked?'block':'none'">
                            Change password
                        </label>
                        <div id="editPwFields" style="display:none; margin-bottom:8px;">
                            <div class="mc-row">
                                <div>
                                    <label>New Password</label>
                                    <input type="password" name="update_password" id="update_password" pattern="(?=.*[!@#$%^&*()]).*" title="Must contain at least one special character (!@#$%^&*())">
                                    <small style="display:block;font-size:11px;margin-top:-8px;margin-bottom:8px;">Min 8 characters, 1 special character</small>
                                </div>
                                <div><label>Confirm</label><input type="password" name="update_confirm_password" id="update_confirm_password"></div>
                            </div>
                        </div>
                        <div class="mc-actions">
                            <button type="button" onclick="closeModal('editModal')" class="btn btn-sec">Cancel</button>
                            <button type="submit" name="update_user_admin" class="btn btn-primary"><i class="bi bi-save"></i> Save</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- DELETE MODAL -->
            <div id="delModal" class="mo">
                <div class="mc">
                    <button class="close-x" onclick="closeModal('delModal')">✕</button>
                    <h3 style="color:#dc3545;"><i class="bi bi-exclamation-triangle"></i> Delete User</h3>
                    <div class="del-box">Permanently delete <strong id="delName"></strong>? This cannot be undone.</div>
                    <form method="POST">
                        <input type="hidden" name="delete_user_id" id="delId">
                        <div class="mc-actions">
                            <button type="button" onclick="closeModal('delModal')" class="btn btn-sec">Cancel</button>
                            <button type="submit" name="delete_user" class="btn btn-danger"><i class="bi bi-trash"></i> Yes, Delete</button>
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
// ───────────────── MODALS ─────────────────
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('.mo').forEach(m => 
    m.addEventListener('click', e => { if(e.target===m) m.classList.remove('open'); })
);

function openEdit(id,name,email,role,status,pic) {
    document.getElementById('editId').value = id;
    document.getElementById('editName').value = name;
    document.getElementById('editEmail').value = email;
    document.getElementById('editOrigEmail').value = email;
    document.getElementById('editRole').value = role;
    document.getElementById('editStatus').value = status;
    document.getElementById('editCurPic').value = pic;
    document.getElementById('changePwCheck').checked = false;
    document.getElementById('editPwFields').style.display = 'none';
    // Clear password fields
    document.getElementById('update_password').value = '';
    document.getElementById('update_confirm_password').value = '';
    openModal('editModal');
}

function openDelete(id, name) {
    document.getElementById('delId').value = id;
    document.getElementById('delName').textContent = name;
    openModal('delModal');
}

// ───────────────── BULK ─────────────────
function toggleSelAll() {
    const c = document.getElementById('selAll').checked;
    document.querySelectorAll('.usel:not(:disabled)').forEach(cb => cb.checked = c);
    updateBulk();
}

function updateBulk() {
    const n = document.querySelectorAll('.usel:checked').length;
    document.getElementById('bulkCount').textContent = n + ' selected';
    document.getElementById('bulkBar').style.display = n > 0 ? 'block' : 'none';

    const all = document.querySelectorAll('.usel:not(:disabled)');
    document.getElementById('selAll').checked = all.length>0 && [...all].every(c=>c.checked);
}

function clearBulk() {
    document.querySelectorAll('.usel').forEach(c=>c.checked=false);
    document.getElementById('selAll').checked=false;
    updateBulk();
}

function confirmBulk() {
    const act = document.getElementById('bulkActionSel').value;
    const n   = document.querySelectorAll('.usel:checked').length;
    if (!act) { alert('Select an action.'); return false; }
    if (act==='delete') return confirm('Delete '+n+' user(s)? Cannot be undone.');
    return true;
}

// ───────────────── ADD USER CLIENT-SIDE VALIDATION ─────────────────
document.addEventListener('DOMContentLoaded', function() {
    const addForm = document.getElementById('addUserForm');
    if (addForm) {
        addForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const errorDiv = document.getElementById('addModalError');
            errorDiv.style.display = 'none';
            errorDiv.innerHTML = '';

            const name = document.getElementById('new_name').value.trim();
            const email = document.getElementById('new_email').value.trim();
            const password = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password_new').value;
            let errors = [];

            if (name.length < 2) errors.push('Name must be at least 2 characters.');
            if (!/^[^\s@]+@([^\s@]+\.)+[^\s@]+$/.test(email)) errors.push('Invalid email format.');
            if (password.length < 8) errors.push('Password must be at least 8 characters.');
            if (!/[!@#$%^&*()]/.test(password)) errors.push('Password must contain at least one special character (!@#$%^&*()).');
            if (password !== confirm) errors.push('Passwords do not match.');

            if (errors.length > 0) {
                errorDiv.innerHTML = errors.join('<br>');
                errorDiv.style.display = 'block';
                return;
            }

            // Optionally check email availability via AJAX
            fetch('check_email.php?email=' + encodeURIComponent(email))
                .then(res => res.json())
                .then(data => {
                    if (data.exists) {
                        errorDiv.innerHTML = 'Email already registered.';
                        errorDiv.style.display = 'block';
                    } else {
                        // Submit form
                        addForm.submit();
                    }
                })
                .catch(() => {
                    // If AJAX fails, submit anyway (server will handle duplicate)
                    addForm.submit();
                });
        });
    }

    // Real-time validation for add user fields (show errors on blur)
    const fields = ['new_name', 'new_email', 'new_password', 'confirm_password_new'];
    fields.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('blur', function() {
                const errorDiv = document.getElementById('addModalError');
                errorDiv.style.display = 'none';
                errorDiv.innerHTML = '';
            });
        }
    });
});

// ───────────────── SIDEBAR, THEME, PROFILE PREVIEW ─────────────────
document.addEventListener('DOMContentLoaded', function() {

    // Sidebar
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileToggle  = document.getElementById('mobileMenuToggle');
    const sidebar       = document.querySelector('.sidebar');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => sidebar.classList.toggle('collapsed'));
    }
    if (mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', () => sidebar.classList.toggle('show'));
    }

    // Active link
    const currentPage = window.location.pathname.split('/').pop() || 'dashboard.php';
    document.querySelectorAll('.nav-link').forEach(link => {
        if (link.getAttribute('href') === currentPage) link.classList.add('active');
    });

    // Theme
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

    // Profile Image Preview
    const profileImageInput = document.getElementById('profileImage');
    const imagePreview = document.getElementById('imagePreview');
    const useGravatar = document.getElementById('useGravatar');

    if (profileImageInput && imagePreview) {
        profileImageInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = e => imagePreview.src = e.target.result;
                reader.readAsDataURL(file);

                if (useGravatar) useGravatar.checked = false;
            }
        });

        imagePreview.parentElement.addEventListener('click', function(e) {
            if (e.target !== profileImageInput) profileImageInput.click();
        });
    }

    // Gravatar
    if (useGravatar) {
        useGravatar.addEventListener('change', function() {
            if (this.checked) {
                const email = document.getElementById('email').value;
                const hash = CryptoJS.MD5(email.trim().toLowerCase()).toString();
                imagePreview.src = `https://www.gravatar.com/avatar/${hash}?s=200&d=identicon`;
            }
        });
    }

    // Password validation
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');

    function validatePassword() {
        if (!newPassword || !confirmPassword) return;

        if (confirmPassword.value && newPassword.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity("Passwords do not match");
        } else {
            confirmPassword.setCustomValidity("");
        }
    }

    if (newPassword) newPassword.addEventListener('input', validatePassword);
    if (confirmPassword) confirmPassword.addEventListener('input', validatePassword);
});
</script>
</body>
</html>