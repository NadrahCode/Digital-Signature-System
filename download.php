<?php
session_start();
require('inc/db_config.php');

if (!isset($_GET['token'])) {
    die("No token provided.");
}

$token = $connect->real_escape_string($_GET['token']);

$result = $connect->query("SELECT file_name, doc_name FROM documents WHERE token='$token' LIMIT 1");
if ($result->num_rows !== 1) {
    die("Invalid token.");
}

$row = $result->fetch_assoc();
$filename = $row['file_name'];
$doc_name = $row['doc_name'];
$filepath = __DIR__ . "/uploads/" . $filename;

if (!file_exists($filepath)) {
    die("File not found.");
}

// Mark as downloaded if user is logged in
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'user') {
    $uid = intval($_SESSION['user_id']);
    $doc_result = $connect->query("SELECT id FROM documents WHERE token='$token' LIMIT 1");
    if ($doc_result && $doc_result->num_rows > 0) {
        $doc_row = $doc_result->fetch_assoc();
        $did = intval($doc_row['id']);
        $connect->query("UPDATE document_recipients SET downloaded_at = NOW() WHERE document_id = $did AND user_id = $uid AND downloaded_at IS NULL");
    }
}

// InfinityFree-safe: discard any output, then send file
@ob_end_clean();

$safe_name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $doc_name) . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $safe_name . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('Expires: 0');

readfile($filepath);
exit;
?>