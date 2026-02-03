<?php
session_start();
require('inc/db_config.php');

// Only for logged-in users
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    exit('Unauthorized');
}

$doc_id = isset($_GET['doc_id']) ? intval($_GET['doc_id']) : 0;
$user_id = $_SESSION['user_id'];

if ($doc_id > 0) {
    $stmt = $connect->prepare("UPDATE document_recipients SET downloaded_at = NOW() WHERE document_id = ? AND user_id = ? AND downloaded_at IS NULL");
    $stmt->bind_param("ii", $doc_id, $user_id);
    $stmt->execute();
    $stmt->close();
}

exit('OK');
?>