<?php
session_start();
require('inc/db_config.php');
require_once(__DIR__ . '/digital_signature.php');

$token = isset($_GET['token']) ? $connect->real_escape_string($_GET['token']) : '';
$error = "";
$verification_result = null;
$from_qr = !empty($token); // Check if accessed via QR code

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $submitted_key = strtoupper(trim($_POST['verification_key']));
    $token = $connect->real_escape_string($_POST['token']);
    
    // Check if file was uploaded
    $uploaded_file = null;
    if (isset($_FILES['signed_file']) && $_FILES['signed_file']['error'] === UPLOAD_ERR_OK) {
        $uploaded_file = $_FILES['signed_file']['tmp_name'];
    } else {
        $error = "Please upload the signed document file for verification.";
    }
    
    if (empty($error)) {
        // Get document from database
        $query = "SELECT * FROM documents WHERE token='$token' LIMIT 1";
        $result = $connect->query($query);
        
        if ($result->num_rows === 0) {
            $error = "Document not found.";
        } else {
            $doc = $result->fetch_assoc();
            
            // Verify the key matches
            if ($submitted_key === strtoupper($doc['key_id'])) {
                // Verify uploaded file hash
                $uploaded_hash = hash_file("sha256", $uploaded_file);
                $file_integrity = ($uploaded_hash === $doc['file_hash']);
                
                // Get original file from server
                $filepath = __DIR__ . "/uploads/" . $doc['file_name'];
                $server_file_exists = file_exists($filepath);
                
                $verification_result = [
                    'status' => $file_integrity ? 'verified' : 'tampered',
                    'doc_name' => $doc['doc_name'],
                    'description' => $doc['description'],
                    'key_id' => $doc['key_id'],
                    'token' => $doc['token'],
                    'signature_hash' => $doc['signature_hash'],
                    'document_hash' => $doc['document_hash'] ?? 'N/A',
                    'document_id' => $doc['document_id'] ?? 'N/A',
                    'checksum' => $doc['checksum'] ?? 'N/A',
                    'public_key' => $doc['public_key'] ?? 'N/A',
                    'created_at' => $doc['created_at'],
                    'signature_timestamp' => $doc['signature_timestamp'],
                    'file_hash_match' => $file_integrity,
                    'uploaded_hash' => $uploaded_hash,
                    'original_hash' => $doc['file_hash'],
                    'algorithm' => 'SHA-256',
                    'server_file_exists' => $server_file_exists,
                    'from_qr' => $from_qr
                ];
                
                // Additional verification using checksum if available
                if (!empty($doc['checksum']) && $doc['checksum'] !== 'LEGACY-00000000') {
                    $expected_checksum = DigitalSignature::createChecksum($doc['signature_hash'] . '|' . ($doc['public_key'] ?? ''));
                    $verification_result['checksum_valid'] = ($expected_checksum === $doc['checksum']);
                }
                
                // Cross-verify with server file if it exists
                if ($server_file_exists) {
                    $server_hash = hash_file("sha256", $filepath);
                    $verification_result['server_hash'] = $server_hash;
                    $verification_result['server_match'] = ($uploaded_hash === $server_hash);
                }
            } else {
                $error = "Invalid verification key. Please check and try again.";
            }
        }
    }
}

// If token provided, get basic info
$doc_info = null;
if (!empty($token)) {
    $result = $connect->query("SELECT doc_name, key_id, checksum, created_at FROM documents WHERE token='$token' LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $doc_info = $result->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Verify Document | Digital Signature System</title>
<?php if (isset($_SESSION['user_id'])): ?>
    <?php require('inc/links.php'); ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/design.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
    .verify-container {
        max-width: 900px;
        margin: 0 auto;
        padding: 30px;
    }
    .verify-card {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 4px 12px var(--shadow-color);
    }
    </style>
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
    .verify-container {
        max-width: 800px;
        width: 100%;
    }
    .verify-card {
        background: white;
        padding: 40px;
        border-radius: 15px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    }
</style>
<?php endif; ?>
<style>
    h2 {
        color: #2c3e50;
        text-align: center;
        margin-bottom: 30px;
        font-size: 32px;
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
    .doc-preview {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 25px;
        border: 2px solid #dee2e6;
    }
    .doc-preview h3 {
        color: #667eea;
        margin-bottom: 10px;
    }
    .doc-preview p {
        color: #6c757d;
        font-size: 14px;
        margin: 5px 0;
    }
    .doc-preview .checksum {
        font-family: 'Courier New', monospace;
        font-size: 13px;
        background: #e7f3ff;
        padding: 8px;
        border-radius: 5px;
        margin-top: 10px;
    }
    .info-icon {
        background: #e7f3ff;
        color: #0066cc;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 25px;
        text-align: center;
        border-left: 4px solid #0066cc;
    }
    .form-group {
        margin-bottom: 25px;
    }
    label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #495057;
    }
    input[type="text"], input[type="file"] {
        width: 100%;
        padding: 15px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 16px;
        transition: border 0.3s;
    }
    input[type="text"] {
        font-family: 'Courier New', monospace;
        text-transform: uppercase;
        letter-spacing: 2px;
        text-align: center;
    }
    input[type="file"] {
        padding: 10px;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    input:focus {
        outline: none;
        border-color: #667eea;
    }
    .hint {
        font-size: 13px;
        color: #6c757d;
        text-align: center;
        margin-top: 8px;
    }
    .btn {
        width: 100%;
        padding: 15px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 18px;
        font-weight: 600;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-block;
        text-align: center;
    }
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
    }
    .result-card {
        margin-top: 30px;
        padding: 30px;
        border-radius: 15px;
        text-align: center;
        animation: slideUp 0.5s;
    }
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .result-card.verified {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        color: white;
    }
    .result-card.tampered {
        background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
        color: white;
    }
    .result-card .icon {
        font-size: 80px;
        margin-bottom: 20px;
    }
    .result-card h3 {
        font-size: 28px;
        margin-bottom: 15px;
        color: white;
    }
    .result-card .message {
        font-size: 16px;
        margin-bottom: 25px;
        opacity: 0.9;
    }
    .details-box {
        background: rgba(255,255,255,0.2);
        padding: 20px;
        border-radius: 10px;
        margin-top: 20px;
        text-align: left;
    }
    .details-box .detail-row {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid rgba(255,255,255,0.2);
        align-items: center;
    }
    .details-box .detail-row:last-child {
        border-bottom: none;
    }
    .details-box .label {
        font-weight: 600;
        opacity: 0.9;
        flex-shrink: 0;
        margin-right: 15px;
    }
    .details-box .value {
        font-family: 'Courier New', monospace;
        word-break: break-all;
        text-align: right;
        font-size: 13px;
    }
    .action-buttons {
        display: flex;
        gap: 15px;
        margin-top: 25px;
    }
    .btn-secondary {
        background: rgba(255,255,255,0.3);
        color: white;
        flex: 1;
    }
    .btn-secondary:hover {
        background: rgba(255,255,255,0.4);
    }
    .checksum-badge {
        display: inline-block;
        background: rgba(255,255,255,0.3);
        padding: 8px 15px;
        border-radius: 20px;
        font-family: 'Courier New', monospace;
        font-size: 13px;
        margin-top: 10px;
    }
    .file-upload-box {
        border: 2px dashed #667eea;
        border-radius: 10px;
        padding: 20px;
        text-align: center;
        background: #f8f9fa;
        margin-bottom: 20px;
    }
    .file-upload-box i {
        font-size: 48px;
        color: #667eea;
        margin-bottom: 10px;
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
            <h1 class="welcome-title">Verify Document 🔍</h1>
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
        <h2>🔍 Verify Document</h2>
<?php endif; ?>
        
        <?php if ($doc_info && !$verification_result): ?>
            <div class="doc-preview">
                <h3>📄 <?php echo htmlspecialchars($doc_info['doc_name']); ?></h3>
                <p>Created: <?php echo date('F j, Y \a\t g:i A', strtotime($doc_info['created_at'])); ?></p>
                <?php if (!empty($doc_info['checksum']) && $doc_info['checksum'] !== 'LEGACY-00000000'): ?>
                    <div class="checksum">✓ Checksum: <?php echo htmlspecialchars($doc_info['checksum']); ?></div>
                <?php endif; ?>
            </div>
            
            <div class="info-icon">
                🔐 This document uses SHA-256 cryptographic signatures. Upload the signed document and enter the verification key to confirm authenticity.
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <strong>❌ Verification Failed</strong><br>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!$verification_result): ?>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div class="form-group">
                    <label>📎 Upload Signed Document *</label>
                    <div class="file-upload-box">
                        <div style="font-size: 48px; margin-bottom: 10px;">📄</div>
                        <input type="file" 
                               name="signed_file" 
                               accept=".pdf,application/pdf" 
                               required
                               onchange="showFileName(this)">
                        <div class="hint" id="fileNameDisplay">
                            Select the signed PDF document to verify
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>🔑 Verification Key *</label>
                    <input type="text" 
                           name="verification_key" 
                           placeholder="Enter key (e.g., ABCD1234)" 
                           required
                           maxlength="16"
                           pattern="[A-Za-z0-9]+"
                           autocomplete="off">
                    <div class="hint">
                        The verification key is displayed on the signature page of the document
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    🔍 Verify Document & Hash Integrity
                </button>
            </form>
        <?php else: ?>
            <?php if ($verification_result['status'] === 'verified'): ?>
                <div class="result-card verified">
                    <div class="icon">✅</div>
                    <h3>Document Verified!</h3>
                    <div class="message">
                        This document is cryptographically authentic and has not been tampered with.
                    </div>
                    
                    <?php if (!empty($verification_result['checksum']) && $verification_result['checksum'] !== 'LEGACY-00000000'): ?>
                        <div class="checksum-badge">
                            ✓ Checksum Verified: <?php echo htmlspecialchars($verification_result['checksum']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="details-box">
                        <div class="detail-row">
                            <span class="label">Document Name:</span>
                            <span class="value"><?php echo htmlspecialchars($verification_result['doc_name']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Verification Key:</span>
                            <span class="value"><?php echo htmlspecialchars($verification_result['key_id']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Document ID:</span>
                            <span class="value"><?php echo substr($verification_result['document_id'], 0, 24); ?>...</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Algorithm:</span>
                            <span class="value"><?php echo $verification_result['algorithm']; ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Created:</span>
                            <span class="value"><?php echo date('F j, Y', strtotime($verification_result['created_at'])); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Signature Time:</span>
                            <span class="value"><?php echo date('Y-m-d H:i:s', $verification_result['signature_timestamp']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">File Integrity:</span>
                            <span class="value">✅ INTACT</span>
                        </div>
                        <?php if (isset($verification_result['server_match'])): ?>
                        <div class="detail-row">
                            <span class="label">Server Match:</span>
                            <span class="value"><?php echo $verification_result['server_match'] ? '✅ MATCH' : '⚠️ MISMATCH'; ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (isset($verification_result['checksum_valid'])): ?>
                        <div class="detail-row">
                            <span class="label">Checksum Valid:</span>
                            <span class="value"><?php echo $verification_result['checksum_valid'] ? '✅ VALID' : '⚠️ INVALID'; ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="action-buttons">
                        <a href="download.php?token=<?php echo urlencode($verification_result['token']); ?>" class="btn btn-secondary">
                            ⬇️ Download
                        </a>
                        <?php if (!$verification_result['from_qr']): ?>
                        <a href="verify.php" class="btn btn-secondary">
                            🔍 Verify Another
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="result-card tampered">
                    <div class="icon">⚠️</div>
                    <h3>Warning: Document Tampered!</h3>
                    <div class="message">
                        The file hash does not match. This document has been modified since it was signed.
                    </div>
                    
                    <div class="details-box">
                        <div class="detail-row">
                            <span class="label">Document Name:</span>
                            <span class="value"><?php echo htmlspecialchars($verification_result['doc_name']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">File Integrity:</span>
                            <span class="value">❌ MODIFIED</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Algorithm:</span>
                            <span class="value"><?php echo $verification_result['algorithm']; ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Original Hash:</span>
                            <span class="value" style="font-size: 10px;"><?php echo substr($verification_result['original_hash'], 0, 32); ?>...</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Uploaded Hash:</span>
                            <span class="value" style="font-size: 10px;"><?php echo substr($verification_result['uploaded_hash'], 0, 32); ?>...</span>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <?php if (!$verification_result['from_qr']): ?>
                        <a href="verify.php" class="btn btn-secondary">
                            🔍 Verify Another
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
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
function showFileName(input) {
    const display = document.getElementById('fileNameDisplay');
    if (input.files && input.files[0]) {
        display.textContent = '📄 Selected: ' + input.files[0].name;
        display.style.color = '#28a745';
        display.style.fontWeight = '600';
    }
}
</script>
</body>
</html>