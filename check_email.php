<?php
require('inc/db_config.php');
header('Content-Type: application/json');

if (isset($_GET['email'])) {
    $email = mysqli_real_escape_string($connect, $_GET['email']);
    $query = "SELECT user_id FROM users WHERE email = '$email'";
    $result = mysqli_query($connect, $query);
    $exists = mysqli_num_rows($result) > 0;
    echo json_encode(['exists' => $exists]);
} else {
    echo json_encode(['exists' => false]);
}