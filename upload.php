<?php
session_start();
require('inc/db_config.php');
require_once(__DIR__ . '/lib/tcpdf/tcpdf.php');
require_once(__DIR__ . '/digital_signature.php');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: login.php");
    exit;
}

$user_id     = $_SESSION['user_id'];
$user_name   = $_SESSION['name'];
$user_email  = $_SESSION['email'];
$user_role   = $_SESSION['role'];
$profile_img = $_SESSION['profile_img'] ?? '';

$error   = "";
$success = "";
$doc_id  = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $doc_name    = trim($_POST["doc_name"]);
    $kepada      = trim($_POST["kepada"]   ?? 'All UPTM Staff and Students');
    $melalui     = trim($_POST["melalui"]  ?? '-');
    $description = trim($_POST["description"] ?? '');
    $doc_source  = $_POST["doc_source"] ?? 'upload';
    $doc_content = trim($_POST["doc_content"] ?? '');

    if (empty($doc_name)) {
        $error = "Please enter a memo subject.";
    } elseif ($doc_source === 'upload' && (!isset($_FILES["word_file"]) || $_FILES["word_file"]["error"] !== UPLOAD_ERR_OK)) {
        $error = "Please select a valid Word document to upload.";
    } elseif ($doc_source === 'text' && empty($doc_content)) {
        $error = "Please enter document content.";
    } else {
        $token     = bin2hex(random_bytes(16));
        $uploadDir = __DIR__ . "/uploads/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $finalFileName = $token . ".pdf";
        $finalFilePath = $uploadDir . $finalFileName;

        try {
            $extractedText    = '';
            $uploadedFilePath = null;

            if ($doc_source === 'upload') {
                $file    = $_FILES["word_file"];
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
                            $xml = str_replace(['</w:p>', '<w:br/>', '<w:pPr/>'], "\n", $xml);
                            $xml = str_replace('<w:tab/>', "\t", $xml);
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
                $content       = $doc_content;
                $extractedText = $doc_content;
            }

            $signature           = DigitalSignature::createSignature($content, $doc_name, $description);
            $signature_timestamp = $signature['unix_timestamp'];

            $hash_lock             = substr(hash('sha256', $token . '|' . $signature['signature_hash']), 0, 32);
            $locked_verification_url = "https://digitalsignature.infinityfree.me/verify.php?token=" . $token . "&h=" . $hash_lock;

            // ================================================================
            // PDF SETUP
            // ================================================================
            $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->SetCreator('Digital Signature System - UPTM');
            $pdf->SetTitle($doc_name);
            $pdf->SetAuthor($user_name);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(20, 15, 20);
            $pdf->SetAutoPageBreak(TRUE, 25);

            // ================================================================
            // PDF PROTECTION
            // ================================================================
            $ownerPass = bin2hex(random_bytes(16));
            $pdf->SetProtection(['print'], '', $ownerPass, 2, null);

            $pageW    = $pdf->getPageWidth();   // 210
            $pageH    = $pdf->getPageHeight();  // 297
            $logoPath = __DIR__ . '/images/uptm-logo.png';

            // ================================================================
            // PAGE 1: UPTM MEMO
            // ================================================================
            $pdf->AddPage();

            // --- Logo centered at top ---
            if (file_exists($logoPath)) {
                $logoW = 40;
                $logoX = ($pageW - $logoW) / 2;
                $pdf->Image($logoPath, $logoX, 10, $logoW, 0, 'PNG');
            }

            // --- Thin separator ---
            $pdf->SetDrawColor(180, 180, 180);
            $pdf->SetLineWidth(0.3);
            $pdf->Line(20, 33, $pageW - 20, 33);

            // --- Memo subject as main header ---
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(20, 36);
            $pdf->MultiCell($pageW - 40, 7, strtoupper($doc_name), 0, 'C');

            // --- Kepada / Daripada / Melalui table ---
            $yTable = $pdf->GetY() + 4;
            $labelW = 32;
            $colonW = 5;
            $valueW = $pageW - 40 - $labelW - $colonW;

            $pdf->SetDrawColor(0, 0, 0);
            $pdf->SetLineWidth(0.3);

            // 4-row table: Kepada, Daripada, Melalui, Tarikh
            $rows = [
                ['Kepada',   $kepada],
                ['Daripada', $user_name],
                ['Melalui',  empty($melalui) ? '-' : $melalui],
                ['Tarikh',   date('d F Y') . '  |  ' . getHijriApprox()],
            ];

            $y = $yTable;
            foreach ($rows as $row) {
                $pdf->SetXY(20, $y);
                $pdf->SetFont('helvetica', 'B', 10);
                $pdf->Cell($labelW, 7, $row[0], 'LTB', 0, 'L');
                $pdf->Cell($colonW, 7, ':', 'TB', 0, 'C');
                $pdf->SetFont('helvetica', '', 10);
                $pdf->Cell($valueW, 7, $row[1], 'TBR', 1, 'L');
                $y += 7;
            }

            // --- Salutation ---
            $y = $y + 6;
            $pdf->SetXY(20, $y);
            $pdf->SetFont('helvetica', 'I', 10);
            $pdf->MultiCell($pageW - 40, 5, "Assalamu’alaikum warahmatullahi wabarakatuh and Selamat Sejahtera,", 0, 'L');

            $pdf->Ln(2);
            $pdf->SetXY(20, $pdf->GetY());
            $pdf->SetFont('helvetica', '', 10);
            $pdf->MultiCell($pageW - 40, 5, "Dear Prof. Dr./ Assoc. Prof. Dr./ Dr./ Sir/ Madam,", 0, 'L');

            // --- Body content: natural, no numbering ---
            $pdf->Ln(4);
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetTextColor(0, 0, 0);

            $pdf->SetXY(20, $pdf->GetY());
            $pdf->MultiCell($pageW - 40, 5, "With due respect, we refer to the above matter.", 0, 'L');
            $pdf->Ln(3);

            // Natural body — each non-empty line becomes its own paragraph
            $paragraphs = array_filter(array_map('trim', explode("\n", $extractedText)));
            foreach ($paragraphs as $para) {
                if (empty($para)) continue;
                $pdf->SetXY(20, $pdf->GetY());
                $pdf->MultiCell($pageW - 40, 5, $para, 0, 'L');
                $pdf->Ln(3);
            }

            // --- Thank you ---
            $pdf->Ln(2);
            $pdf->SetXY(20, $pdf->GetY());
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(0, 5, "Thank you.", 0, 1, 'L');

            // --- Slogans ---
            $pdf->Ln(5);
            $pdf->SetXY(20, $pdf->GetY());
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetTextColor(0, 0, 0);
            foreach (['"MALAYSIA MADANI"', '"BERKHIDMAT UNTUK NEGARA"', '"LUAR BANDAR SEJAHTERA"'] as $slogan) {
                $pdf->Cell(0, 5, $slogan, 0, 1, 'L');
            }

            // --- Signer block ---
            $pdf->Ln(8);
            $pdf->SetXY(20, $pdf->GetY());
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(0, 5, "Saya yang menjalankan amanah,", 0, 1, 'L');
            $pdf->Ln(14); // space for physical signature

            $sigY  = $pdf->GetY();
            $qrSize = 28;
            $qrX    = 20;

            // Column to the right of QR
            $refColX = $qrX + $qrSize + 5;
            $refColW = $pageW - 20 - $refColX;

            // Short URL using just the token (fits better in memo)
            $short_url = 'digitalsignature.infinityfree.me/verify.php?token=' . $token . '&h=' . $hash_lock;

            // Reference label + blank line for manual writing
            $pdf->SetFont('helvetica', 'I', 6.5);
            $pdf->SetTextColor(120, 120, 120);
            $pdf->SetXY($refColX, $sigY);
            $pdf->Cell($refColW, 4, 'This document is digitally signed. Any modification will invalidate the signature.', 0, 1, 'L');
            $pdf->SetDrawColor(150, 150, 150);
            $pdf->SetLineWidth(0.2);
            $pdf->Line($refColX, $sigY + 9, $refColX + $refColW, $sigY + 9);

            // Link + integrity notice IN the reference area
            $pdf->SetXY($refColX, $sigY + 11);
            $pdf->SetFont('helvetica', 'I', 6.5);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell($refColW, 4, $short_url, 0, 1, 'L', false, 'https://' . $short_url);
            $pdf->SetXY($refColX, $pdf->GetY());

            // Signer name + date — below link notice
            $pdf->Ln(2);
            $pdf->SetXY($refColX, $pdf->GetY());
            $pdf->SetFont('helvetica', 'B', 5);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell($refColW, 5, strtoupper($user_name), 0, 1, 'L');
            $pdf->SetXY($refColX, $pdf->GetY());
            $pdf->SetFont('helvetica', '', 5);
            $pdf->SetTextColor(80, 80, 80);
            $pdf->Cell($refColW, 5, date('d M Y, g:i A', $signature_timestamp), 0, 1, 'L');

            // Mini QR — left, placed at sigY
            $qrStyle = ['border' => false, 'padding' => 0, 'fgcolor' => [0,0,0], 'bgcolor' => [255,255,255]];
            $pdf->write2DBarcode($locked_verification_url, 'QRCODE,H', $qrX, $sigY, $qrSize, $qrSize, $qrStyle, 'N');

            // ================================================================
            // RSA DIGITAL SIGNATURE
            // ================================================================
            $certPath       = __DIR__ . '/cert/cert.crt';
            $privateKeyPath = __DIR__ . '/cert/private.pem';

            if (file_exists($certPath) && file_exists($privateKeyPath)) {
                $pdf->setSignature(
                    'file://' . $certPath,
                    'file://' . $privateKeyPath,
                    '', '', 2,
                    [
                        'Name'        => 'Universiti Poly-Tech Malaysia',
                        'Location'    => 'Kuala Lumpur, Malaysia',
                        'Reason'      => 'Document Authentication - UPTM Digital Signature System',
                        'ContactInfo' => $user_email,
                    ]
                );
                $pdf->setSignatureAppearance($pageW - 50, $pageH - 25, 40, 12);
            }

            // Save PDF
            $pdf->Output($finalFilePath, 'F');
            if (isset($uploadedFilePath) && file_exists($uploadedFilePath)) unlink($uploadedFilePath);

            $file_hash = hash_file("sha256", $finalFilePath);
            $stmt = $connect->prepare("INSERT INTO documents (doc_name, description, token, file_name, file_hash, source_type, content, signature_hash, public_key, key_id, signature_timestamp, document_id, document_hash, checksum, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssssisssi", $doc_name, $description, $token, $finalFileName, $file_hash, $doc_source, $content, $signature['signature_hash'], $signature['public_key'], $signature['key_id'], $signature_timestamp, $signature['document_id'], $signature['document_hash'], $signature['checksum'], $user_id);
            if ($stmt->execute()) {
                $doc_id  = $connect->insert_id;
                $success = "Document signed successfully!";
            }
            $stmt->close();

        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Approximate Hijri date
function getHijriApprox() {
    $gM = (int)date('n');
    $gD = (int)date('j');
    $gY = (int)date('Y');
    $jd = gregoriantojd($gM, $gD, $gY);
    $l  = $jd - 1948440 + 10632;
    $n  = (int)(($l - 1) / 10631);
    $l  = $l - 10631 * $n + 354;
    $j  = (int)((10985 - $l) / 5316) * (int)((50 * $l) / 17719)
        + (int)($l / 5670) * (int)((43 * $l) / 15238);
    $l  = $l - (int)((30 - $j) / 15) * (int)((17719 * $j) / 50)
        - (int)($j / 16) * (int)((15238 * $j) / 43) + 29;
    $hM = (int)((24 * $l) / 709);
    $hD = $l - (int)(709 * $hM / 24);
    $hY = 30 * $n + $j - 30;
    $months = ['Muharram','Safar','Rabi\' al-Awwal','Rabi\' al-Thani',
               'Jamadil Awwal','Jamadilakhir','Rajab','Sha\'ban',
               'Ramadan','Syawal','Zulkaedah','Zulhijjah'];
    return $hD . ' ' . ($months[$hM - 1] ?? '') . ' ' . $hY . 'H';
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
                <div style="padding:15px; border-left:4px solid #dc3545; margin-bottom:20px; background:#f8d7da; color:#721c24; border-radius:8px;">
                    ❌ <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success && $doc_id): ?>
                <div style="background:linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color:white; padding:40px; border-radius:15px; text-align:center;">
                    <div style="font-size:64px; margin-bottom:20px;">✅</div>
                    <h3 style="margin-bottom:15px; font-size:24px;"><?php echo $success; ?></h3>
                    <p style="opacity:0.9; margin-bottom:30px;">Your memo has been digitally signed and is ready to be distributed.</p>
                    <div style="display:flex; gap:15px; margin-top:25px;">
                        <a href="send_documents.php?doc_id=<?php echo $doc_id; ?>"
                           style="flex:1; padding:15px; background:rgba(255,255,255,0.3); border-radius:8px; color:white; text-decoration:none; font-weight:600;">
                            📤 Send to Users
                        </a>
                        <a href="dashboard.php"
                           style="flex:1; padding:15px; background:rgba(255,255,255,0.3); border-radius:8px; color:white; text-decoration:none; font-weight:600;">
                            🏠 Go to Dashboard
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div style="background:var(--card-bg); border-radius:12px; padding:30px; box-shadow:0 4px 12px var(--shadow-color);">
                    <form method="post" enctype="multipart/form-data">

                        <div style="margin-bottom:18px;">
                            <label style="display:block; margin-bottom:6px; font-weight:600; color:var(--text-primary);">📋 TAJUK (Title) *</label>
                            <input type="text" name="doc_name" required
                                   placeholder="e.g. ONLINE CLASSES"
                                   style="width:100%; padding:11px; border-radius:8px; border:2px solid var(--sidebar-border); background:var(--card-bg); color:var(--text-primary); font-size:14px;">
                        </div>

                        <div style="margin-bottom:18px;">
                            <label style="display:block; margin-bottom:6px; font-weight:600; color:var(--text-primary);">👤 Kepada (To) *</label>
                            <input type="text" name="kepada" required
                                   placeholder="e.g. DEGREE STUDENTS"
                                   style="width:100%; padding:11px; border-radius:8px; border:2px solid var(--sidebar-border); background:var(--card-bg); color:var(--text-primary); font-size:14px;">
                        </div>

                        <div style="margin-bottom:18px;">
                            <label style="display:block; margin-bottom:6px; font-weight:600; color:var(--text-primary);">🔀 Melalui (From)</label>
                            <input type="text" name="kepada" required
                                   placeholder="e.g. LECTURER OF FCOM"
                                   style="width:100%; padding:11px; border-radius:8px; border:2px solid var(--sidebar-border); background:var(--card-bg); color:var(--text-primary); font-size:14px;">
                        </div>

                        <div style="margin-bottom:18px;">
                            <label style="display:block; margin-bottom:6px; font-weight:600; color:var(--text-primary);">📝 Internal Notes <span style="font-weight:400;color:var(--text-secondary);">(not shown in memo)</span></label>
                            <textarea name="description" rows="2"
                                      style="width:100%; padding:11px; border-radius:8px; border:2px solid var(--sidebar-border); background:var(--card-bg); color:var(--text-primary); font-size:14px; resize:vertical;"></textarea>
                        </div>

                        <div style="margin-bottom:20px;">
                            <label style="display:block; margin-bottom:6px; font-weight:600; color:var(--text-primary);">📁 Memo Content Source</label>
                            <div style="display:flex; gap:10px; margin-top:8px;">
                                <button type="button" onclick="selectSource('upload')" id="btn-upload"
                                        style="flex:1; padding:11px; background:linear-gradient(135deg, #028090, #114B2F); color:white; border:none; border-radius:8px; cursor:pointer; font-weight:600;">
                                    Upload Word Document
                                </button>
                                <button type="button" onclick="selectSource('text')" id="btn-text"
                                        style="flex:1; padding:11px; background:var(--sidebar-border); color:var(--text-primary); border:none; border-radius:8px; cursor:pointer; font-weight:600;">
                                    Enter Text
                                </button>
                            </div>
                            <input type="hidden" name="doc_source" id="doc_source" value="upload">

                            <div id="upload-section" style="margin-top:16px;">
                                <input type="file" name="word_file" accept=".docx" required
                                       style="width:100%; padding:10px; border:2px solid var(--sidebar-border); border-radius:8px; background:var(--card-bg); color:var(--text-primary);">
                                <small style="display:block; margin-top:6px; color:var(--text-secondary);">Supported: .docx files only</small>
                            </div>

                            <div id="text-section" style="margin-top:16px; display:none;">
                                <textarea name="doc_content" id="doc_content" rows="8"
                                          style="width:100%; padding:11px; border-radius:8px; border:2px solid var(--sidebar-border); background:var(--card-bg); color:var(--text-primary); font-size:14px; resize:vertical;"
                                          placeholder="Enter memo body content here..."></textarea>
                            </div>
                        </div>

                        <button type="submit"
                                style="width:100%; padding:15px; background:linear-gradient(135deg, #028090, #114B2F); color:white; border:none; border-radius:8px; font-size:17px; font-weight:600; cursor:pointer; transition:all 0.3s;"
                                onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(2,128,144,0.4)'"
                                onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                            🔐 Sign & Generate Memo PDF
                        </button>
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
    document.getElementById('text-section').style.display   = (source === 'text')   ? 'block' : 'none';
    document.querySelector('input[name="word_file"]').required = (source === 'upload');
    document.getElementById('doc_content').required            = (source === 'text');
    const btnUpload = document.getElementById('btn-upload');
    const btnText   = document.getElementById('btn-text');
    if (source === 'upload') {
        btnUpload.style.background = 'linear-gradient(135deg, #028090, #114B2F)';
        btnUpload.style.color = 'white';
        btnText.style.background = 'var(--sidebar-border)';
        btnText.style.color = 'var(--text-primary)';
    } else {
        btnText.style.background = 'linear-gradient(135deg, #028090, #114B2F)';
        btnText.style.color = 'white';
        btnUpload.style.background = 'var(--sidebar-border)';
        btnUpload.style.color = 'var(--text-primary)';
    }
}
</script>
<script src="js/dashboard.js"></script>
</body>
</html>