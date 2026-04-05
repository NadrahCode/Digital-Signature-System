<?php 
	
	$hname = 'sql106.infinityfree.com';
	$uname = 'if0_40720975';
	$pass = 'Dslol321';
	$db = 'if0_40720975_digitalsignature';

// Create connection
$connect = mysqli_connect($hname, $uname, $pass, $db);

// Check connection
if (!$connect) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Set charset to UTF-8
mysqli_set_charset($connect, "utf8mb4");

// Timezone setting (optional)
date_default_timezone_set('Asia/Kuala_Lumpur');
?>
