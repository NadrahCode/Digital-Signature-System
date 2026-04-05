<?php
session_start();
require('inc/db_config.php');

$token = isset($_GET['token']) ? $connect->real_escape_string($_GET['token']) : '';
$hash_from_qr = isset($_GET['h']) ? trim($_GET['h']) : '';
$error = "";
$doc_info = null;
$integrity_status = null; // 'valid', 'tampered', 'no_hash'

if (!empty($token)) {
    $query = "SELECT d.*, u.name as signer_name, u.email as signer_email 
              FROM documents d 
              LEFT JOIN users u ON d.created_by = u.user_id 
              WHERE d.token='$token' LIMIT 1";
    $result = $connect->query($query);
    
    if ($result && $result->num_rows > 0) {
        $doc_info = $result->fetch_assoc();
        
        // Recompute the same hash_lock that was embedded in the QR at signing time.
        // Formula must match upload.php exactly: token + | + signature_hash
        $expected_lock = substr(hash('sha256', $doc_info['token'] . '|' . $doc_info['signature_hash']), 0, 32);
        
        if (empty($hash_from_qr)) {
            $integrity_status = 'no_hash'; // Old QR without lock
        } elseif (hash_equals($expected_lock, $hash_from_qr)) {
            $integrity_status = 'valid';
        } else {
            $integrity_status = 'tampered'; // QR was modified/forged
        }
        
    } else {
        $error = "Document not found.";
    }
} else {
    $error = "No document specified.";
}

// File upload integrity check
$upload_check_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['verify_file']) && $doc_info) {
    if ($_FILES['verify_file']['error'] === UPLOAD_ERR_OK) {
        $uploaded_hash = hash_file('sha256', $_FILES['verify_file']['tmp_name']);
        $upload_check_result = hash_equals($doc_info['file_hash'], $uploaded_hash) ? 'match' : 'mismatch';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Document Verification | Digital Signature System</title>
<?php if (isset($_SESSION['user_id'])): ?>
    <?php require('inc/links.php'); ?>
    <link rel="stylesheet" href="css/design.css">
    <link rel="stylesheet" href="css/dashboard.css">
<?php else: ?>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        padding: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
</style>
<?php endif; ?>
<style>
    .verify-container {
        max-width: 800px;
        width: 100%;
        margin: 0 auto;
    }
    .verify-card {
        background: white;
        padding: 40px;
        border-radius: 15px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    }
    h2 {
        color: #2c3e50;
        text-align: center;
        margin-bottom: 30px;
        font-size: 28px;
    }
    .alert {
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        text-align: center;
    }
    .alert-danger {
        background: #f8d7da;
        color: #721c24;
        border-left: 4px solid #dc3545;
    }
    .info-section {
        background: #f8f9fa;
        padding: 25px;
        border-radius: 10px;
        margin-bottom: 20px;
    }
    .info-row {
        display: flex;
        padding: 12px 0;
        border-bottom: 1px solid #dee2e6;
    }
    .info-row:last-child {
        border-bottom: none;
    }
    .info-label {
        font-weight: 600;
        color: #495057;
        min-width: 180px;
    }
    .info-value {
        color: #6c757d;
        word-break: break-all;
    }
    .signature-badge {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        color: white;
        padding: 15px 25px;
        border-radius: 10px;
        text-align: center;
        margin: 20px 0;
        font-size: 18px;
        font-weight: 600;
    }
    .btn {
        padding: 12px 25px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 16px;
        font-weight: 600;
        text-decoration: none;
        display: inline-block;
        text-align: center;
        transition: all 0.3s;
    }
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
    }
    .action-buttons {
        display: flex;
        gap: 15px;
        margin-top: 25px;
        justify-content: center;
    }
</style>
</head>
<body <?php if (isset($_SESSION['user_id'])): ?>data-theme="light"<?php endif; ?>>

<?php if (isset($_SESSION['user_id'])): ?>
<div class="dashboard-container">
    <?php 
    $user_id = $_SESSION['user_id'];
    $user_name = $_SESSION['name'];
    $user_email = $_SESSION['email'];
    $user_role = $_SESSION['role'];
    $profile_img = $_SESSION['profile_img'] ?? '';
    require('inc/sidebar.php'); 
    ?>
    
    <main class="main-content">
        <?php require('inc/topheader.php'); ?>
        
        <section class="welcome-section">
            <h1 class="welcome-title">Document Verification 🔍</h1>
            <h2 class="system-title">DIGITAL SIGNATURE SYSTEM</h2>
            <div class="logo-container">
                <a href="dashboard.php">
                    <img src="images/logo-main.png" alt="Digital Signature System Logo" class="system-logo">
                </a>
            </div>
        </section>
        
        <div class="verify-container">
            <div class="verify-card">
<?php else: ?>
<div class="verify-container">
    <div class="verify-card">
        <h2>🔍 Document Verification</h2>
<?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <strong>❌ Error</strong><br>
                <?php echo $error; ?>
            </div>
            <div class="action-buttons">
                <a href="index.php" class="btn btn-primary">🏠 Go to Home</a>
            </div>
        <?php elseif ($doc_info): ?>
            
            <?php if ($integrity_status === 'valid'): ?>
                <div class="signature-badge" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                    ✅ Digitally Signed Document — QR Integrity Verified
                </div>
            <?php elseif ($integrity_status === 'tampered'): ?>
                <div class="signature-badge" style="background: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%); animation: pulse-red 1.5s infinite;">
                    ⛔ QR CODE INVALID — DOCUMENT FORMAT HAS BEEN ALTERED
                </div>
                <div style="background:#fff0f0; border:2px solid #e74c3c; border-radius:10px; padding:20px; margin-bottom:20px; text-align:center;">
                    <p style="color:#721c24; font-size:15px; margin:0;">
                        <strong>This document has been converted or tampered.</strong><br>
                        The QR code fingerprint does not match the original signed PDF.<br>
                        This may mean the file was converted to Word, image, or another format.<br><br>
                        <em>Do not trust this document. Request the original signed PDF from the sender.</em>
                    </p>
                </div>
            <?php elseif ($integrity_status === 'no_hash'): ?>
                <div class="signature-badge" style="background: linear-gradient(135deg, #f39c12 0%, #f1c40f 100%);">
                    ⚠️ Document Found — QR Integrity Lock Not Present
                </div>
                <div style="background:#fff8e1; border:2px solid #f39c12; border-radius:10px; padding:20px; margin-bottom:20px; text-align:center;">
                    <p style="color:#856404; font-size:15px; margin:0;">
                        This QR code was generated without an integrity lock.<br>
                        Use the file upload check below to manually verify this document.
                    </p>
                </div>
            <?php endif; ?>
            
            <style>
            @keyframes pulse-red {
                0%, 100% { box-shadow: 0 0 0 0 rgba(231,76,60,0.4); }
                50% { box-shadow: 0 0 0 12px rgba(231,76,60,0); }
            }
            </style>
            
            <div class="info-section">
                <h3 style="margin-bottom: 15px; color: #2c3e50;">📄 Document Information</h3>
                
                <div class="info-row">
                    <span class="info-label">Document Name:</span>
                    <span class="info-value"><?php echo htmlspecialchars($doc_info['doc_name']); ?></span>
                </div>
                
                <?php if (!empty($doc_info['description'])): ?>
                <div class="info-row">
                    <span class="info-label">Description:</span>
                    <span class="info-value"><?php echo htmlspecialchars($doc_info['description']); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="info-row">
                    <span class="info-label">Created:</span>
                    <span class="info-value"><?php echo date('F j, Y', strtotime($doc_info['created_at'])); ?></span>
                </div>
            </div>
            
            <div class="info-section">
                <h3 style="margin-bottom: 15px; color: #2c3e50;">🔐 Signature Information</h3>
                
                <div class="info-row">
                    <span class="info-label">Signed By:</span>
                    <span class="info-value"><?php echo htmlspecialchars($doc_info['signer_name'] ?? 'System'); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Signer Email:</span>
                    <span class="info-value"><?php echo htmlspecialchars($doc_info['signer_email'] ?? 'N/A'); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Signature Time:</span>
                    <span class="info-value"><?php echo date('Y-m-d H:i:s', $doc_info['signature_timestamp']); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Algorithm:</span>
                    <span class="info-value">SHA-256</span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Document ID:</span>
                        <span class="info-value" style="font-family: monospace; font-size: 12px;"><?php echo $doc_info['document_id']; ?>
                </div>
                
                <?php if (!empty($doc_info['checksum'])): ?>
                <div class="info-row">
                    <span class="info-label">Checksum:</span>
                    <span class="info-value" style="font-family: monospace;"><?php echo htmlspecialchars($doc_info['checksum']); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="action-buttons">
                <?php /* Download button removed — use file upload check below */ ?>
            </div>
            
            <!-- FILE INTEGRITY UPLOAD CHECK -->
            <div style="margin-top:30px; padding:25px; background:#f8f9fa; border-radius:12px; border:2px dashed <?php echo $integrity_status === 'tampered' ? '#e74c3c' : '#dee2e6'; ?>;">
                <h4 style="margin-bottom:10px; color:#495057;">🔍 Upload File to Verify Format Integrity</h4>
                <p style="font-size:13px; color:#6c757d; margin-bottom:15px;">
                    Upload the document you received. The system will check if it matches the original signed file.
                    If the file has been converted to Word, image, or any other format — it will be flagged as <strong>INVALID</strong>.
                </p>
                
                <?php if ($upload_check_result === 'mismatch'): ?>
                    <div style="background:#f8d7da; border:2px solid #dc3545; border-radius:8px; padding:15px; text-align:center; margin-bottom:15px;">
                        ⛔ <strong>FILE DOES NOT MATCH!</strong><br>
                        This file has been <strong>converted or tampered</strong>. It is NOT the original signed PDF.<br>
                        <small>The file hash is different from what was signed and stored.</small>
                    </div>
                <?php elseif ($upload_check_result === 'match'): ?>
                    <div style="background:#d4edda; border:2px solid #28a745; border-radius:8px; padding:15px; text-align:center; margin-bottom:15px;">
                        ✅ <strong>FILE MATCHES!</strong><br>
                        This is the <strong>original signed PDF</strong>. No tampering or format conversion detected.<br>
                        <small>The file hash matches exactly what was signed and stored.</small>
                    </div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <div style="display:flex; gap:10px; align-items:center;">
                        <input type="file" name="verify_file" required
                               style="flex:1; padding:10px; border:2px solid #dee2e6; border-radius:8px; background:white; font-size:13px;">
                        <button type="submit"
                                style="padding:10px 20px; background:linear-gradient(135deg,#667eea,#764ba2); color:white; border:none; border-radius:8px; cursor:pointer; font-weight:600; white-space:nowrap;">
                            🔐 Check Integrity
                        </button>
                    </div>
                    <small style="color:#6c757d; margin-top:8px; display:block;">
                        Accepts any file format. Only the original signed PDF will pass.
                    </small>
                </form>
            </div>

            
        <?php endif; ?>

<?php if (isset($_SESSION['user_id'])): ?>
            </div>
        </div>
    </main>
</div>
<script src="js/dashboard.js"></script>
<?php else: ?>
    </div>
</div>
<?php endif; ?>

<script>
</script>

</body>
</html>