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

// Clear output buffer
while (ob_get_level()) {
    ob_end_clean();
}

// Set headers for download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $doc_name) . '.pdf"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('Expires: 0');

// Read and output file
readfile($filepath);
exit;
?>