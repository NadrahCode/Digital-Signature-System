<?php
session_start();
require('inc/db_config.php');
require_once(__DIR__ . '/lib/tcpdf/tcpdf.php');
require_once(__DIR__ . '/digital_signature.php');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
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
$doc_id = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $doc_name = trim($_POST["doc_name"]);
    $description = trim($_POST["description"]);
    $doc_source = $_POST["doc_source"] ?? 'upload';
    $doc_content = trim($_POST["doc_content"] ?? '');

    if (empty($doc_name)) {
        $error = "Please enter a document name.";
    } elseif ($doc_source === 'upload' && (!isset($_FILES["word_file"]) || $_FILES["word_file"]["error"] !== UPLOAD_ERR_OK)) {
        $error = "Please select a valid Word document to upload.";
    } elseif ($doc_source === 'text' && empty($doc_content)) {
        $error = "Please enter document content.";
    } else {
        $token = bin2hex(random_bytes(16));
        $uploadDir = __DIR__ . "/uploads/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        $verification_url = "https://digitalsignature.infinityfree.me/verify.php?token=" . $token;
        $finalFileName = $token . ".pdf";
        $finalFilePath = $uploadDir . $finalFileName;
        
        try {
            $extractedText = '';

            if ($doc_source === 'upload') {
                $file = $_FILES["word_file"];
                $content = 'UPLOADED:' . $file["name"];
                $fileExt = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
                
                if (!in_array($fileExt, ['doc', 'docx'])) {
                    throw new Exception("Invalid file type. Only .doc and .docx allowed.");
                }

                $uploadedFilePath = $uploadDir . "temp_" . $token . "." . $fileExt;
                move_uploaded_file($file["tmp_name"], $uploadedFilePath);
                
                if ($fileExt === 'docx') {
                    $zip = new ZipArchive;
                    if ($zip->open($uploadedFilePath) === TRUE) {
                        $xml = $zip->getFromName('word/document.xml');
                        if ($xml) {
                            // Convert paragraph ends and line breaks to newlines
                            $xml = str_replace(['</w:p>', '<w:br/>', '<w:pPr/>'], "\n", $xml);
                            // Convert tabs
                            $xml = str_replace('<w:tab/>', "\t", $xml);
                            // Strip all remaining XML tags
                            $extractedText = strip_tags($xml);
                            $extractedText = trim($extractedText);
                        }
                        $zip->close();
                    }
                }
                
                if (empty($extractedText)) {
                    $extractedText = "Content extracted from: " . $file["name"];
                }
            } else {
                // For Text-Based input
                $content = $doc_content;
                $extractedText = $doc_content;
                $uploadedFilePath = null;
            }
            
            $signature = DigitalSignature::createSignature($content, $doc_name, $description);
            $signature_timestamp = $signature['unix_timestamp'];
            
            // Create PDF
            $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->SetCreator('Digital Signature System');
            $pdf->SetTitle($doc_name);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(15, 15, 15);
            $pdf->SetAutoPageBreak(TRUE, 15);
            
            // PAGE 1: Content
            $pdf->AddPage();
            $pdf->SetFont('helvetica', 'B', 20);
            $pdf->Cell(0, 10, $doc_name, 0, 1, 'C');
            $pdf->Ln(10);
            
            if (!empty($description)) {
                $pdf->SetFont('helvetica', 'B', 12);
                $pdf->Cell(0, 8, 'Description:', 0, 1);
                $pdf->SetFont('helvetica', '', 11);
                $pdf->MultiCell(0, 6, $description, 0, 'L');
                $pdf->Ln(5);
            }

            $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
            $pdf->Ln(10);
            
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, 'Document Content:', 0, 1);
            $pdf->Ln(2);
            $pdf->SetFont('helvetica', '', 11);
            
            // MultiCell preserves the formatting/newlines for both Word and Text sources
            $pdf->MultiCell(0, 6, $extractedText, 0, 'L');
            
            // PAGE 2: Signature
            $pdf->AddPage();
            $pdf->SetFont('helvetica', 'B', 22);
            $pdf->SetTextColor(2, 128, 144);
            $pdf->Cell(0, 12, 'Digital Signature Verification', 0, 1, 'C');
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Ln(10);
            
            // Document Information Box (Verification Key Removed)
            $pdf->SetFillColor(247, 249, 250);
            $pdf->RoundedRect(15, $pdf->GetY(), 180, 45, 3, '1111', 'DF');
            $pdf->SetY($pdf->GetY() + 5);
            
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetX(25);
            $pdf->Cell(0, 7, 'Document Information:', 0, 1);
            $pdf->Ln(2);
            
            $pdf->SetFont('helvetica', '', 11);
            $pdf->SetX(25);
            $pdf->Cell(50, 6, 'Document Name:', 0, 0);
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(0, 6, $doc_name, 0, 1);
            
            $pdf->SetFont('helvetica', '', 11);
            $pdf->SetX(25);
            $pdf->Cell(50, 6, 'Signed On:', 0, 0);
            $pdf->Cell(0, 6, date('F j, Y \a\t g:i A', $signature_timestamp), 0, 1);
            
            $pdf->SetX(25);
            $pdf->Cell(50, 6, 'Algorithm:', 0, 0);
            $pdf->Cell(0, 6, 'SHA-256', 0, 1);
            
            $pdf->Ln(20);
            
            // QR Code (URL Text Removed)
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 7, 'Scan to Verify:', 0, 1, 'C');
            $pdf->Ln(5);
            
            $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($verification_url);
            $qrSize = 55;
            $xPos = ($pdf->getPageWidth() - $qrSize) / 2;
            
            try {
                $pdf->Image($qrCodeUrl, $xPos, $pdf->GetY(), $qrSize, $qrSize, 'PNG');
            } catch (Exception $e) {
                $pdf->Rect($xPos, $pdf->GetY(), $qrSize, $qrSize, 'F');
            }
            
            // Save PDF
            $pdf->Output($finalFilePath, 'F');
            
            // Cleanup and DB insert
            if (isset($uploadedFilePath) && file_exists($uploadedFilePath)) unlink($uploadedFilePath);
            
            $file_hash = hash_file("sha256", $finalFilePath);
            $stmt = $connect->prepare("INSERT INTO documents (doc_name, description, token, file_name, file_hash, source_type, content, signature_hash, public_key, key_id, signature_timestamp, document_id, document_hash, checksum) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssssisss", $doc_name, $description, $token, $finalFileName, $file_hash, $doc_source, $content, $signature['signature_hash'], $signature['public_key'], $signature['key_id'], $signature_timestamp, $signature['document_id'], $signature['document_hash'], $signature['checksum']);
            
            if ($stmt->execute()) {
                $doc_id = $connect->insert_id;
                $success = "✅ Document signed successfully!";
            }
            $stmt->close();
            
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Upload Document | Digital Signature System</title>
    <?php require('inc/links.php'); ?>
    <link rel="stylesheet" href="css/design.css">
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body data-theme="light">
<div class="dashboard-container">
    <?php require('inc/sidebar.php'); ?>
    <main class="main-content">
        <?php require('inc/topheader.php'); ?>
        
        <section class="welcome-section">
            <h1 class="welcome-title">Upload & Sign Document 📤</h1>
            <div class="logo-container">
                <a href="dashboard.php"><img src="images/logo-main.png" class="system-logo"></a>
            </div>
        </section>
        
        <div style="max-width:900px; margin:0 auto; padding:30px;">
            <?php if ($error): ?>
                <div class="alert alert-danger" style="padding:15px; border-left:4px solid #dc3545; margin-bottom:20px; background:#f8d7da; color:#721c24;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success && $doc_id): ?>
                <div style="background:linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color:white; padding:30px; border-radius:15px; text-align:center;">
                    <h3><?php echo $success; ?></h3>
                    <div style="display:flex; gap:15px; margin-top:25px;">
                        <a href="send_documents.php?doc_id=<?php echo $doc_id; ?>" style="flex:1; padding:12px; background:rgba(255,255,255,0.3); border-radius:8px; color:white; text-decoration:none;">📤 Send to Users</a>
                        <a href="documents.php" style="flex:1; padding:12px; background:rgba(255,255,255,0.3); border-radius:8px; color:white; text-decoration:none;">📋 View Documents</a>
                    </div>
                </div>
            <?php else: ?>
                <div style="background:var(--card-bg); border-radius:12px; padding:30px; box-shadow:0 4px 12px var(--shadow-color);">
                    <form method="post" enctype="multipart/form-data">
                        <div style="margin-bottom:20px;">
                            <label>📝 Document Name *</label>
                            <input type="text" name="doc_name" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #ccc;">
                        </div>
                        <div style="margin-bottom:20px;">
                            <label>📋 Description (Optional)</label>
                            <textarea name="description" style="width:100%; padding:10px; border-radius:8px; border:1px solid #ccc;"></textarea>
                        </div>
                        <div style="margin-bottom:20px;">
                            <label>📁 Source Type</label>
                            <div style="display:flex; gap:10px; margin-top:10px;">
                                <button type="button" class="btn btn-primary" onclick="selectSource('upload')">Upload Word</button>
                                <button type="button" class="btn btn-secondary" onclick="selectSource('text')">Enter Text</button>
                            </div>
                            <input type="hidden" name="doc_source" id="doc_source" value="upload">
                            
                            <div id="upload-section" style="margin-top:20px;">
                                <input type="file" name="word_file" accept=".docx" required>
                            </div>
                            <div id="text-section" style="margin-top:20px; display:none;">
                                <textarea name="doc_content" id="doc_content" style="width:100%; min-height:150px; padding:10px; border-radius:8px;"></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success" style="width:100%; padding:15px; font-weight:bold;">🔐 Sign & Generate PDF</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
function selectSource(source) {
    document.getElementById('doc_source').value = source;
    document.getElementById('upload-section').style.display = (source === 'upload') ? 'block' : 'none';
    document.getElementById('text-section').style.display = (source === 'text') ? 'block' : 'none';
    
    document.querySelector('input[name="word_file"]').required = (source === 'upload');
    document.getElementById('doc_content').required = (source === 'text');
}
</script>
</body>
</html>