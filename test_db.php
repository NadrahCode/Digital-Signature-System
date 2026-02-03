<?php
require('inc/db_config.php');

echo "<h2>Database Connection Test</h2>";

if ($connect) {
    echo "<p style='color: green;'>✅ Database connected successfully!</p>";
    
    // Test query
    $result = $connect->query("SHOW TABLES LIKE 'documents'");
    if ($result && $result->num_rows > 0) {
        echo "<p style='color: green;'>✅ 'documents' table exists!</p>";
        
        // Show table structure
        $result2 = $connect->query("DESCRIBE documents");
        echo "<h3>Table Structure:</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
        while ($row = $result2->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>❌ 'documents' table doesn't exist!</p>";
        echo "<p>Run this SQL to create the table:</p>";
        echo "<pre>";
        echo "CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doc_name VARCHAR(255) NOT NULL,
    description TEXT,
    token VARCHAR(50) UNIQUE NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_hash VARCHAR(64) NOT NULL,
    source_type VARCHAR(20) DEFAULT 'upload',
    content TEXT,
    original_file VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        echo "</pre>";
    }
} else {
    echo "<p style='color: red;'>❌ Database connection failed!</p>";
    echo "<p>Error: " . mysqli_connect_error() . "</p>";
}

// Test file permissions
echo "<h2>File Permissions Test</h2>";
$uploadDir = __DIR__ . "/uploads/";
if (!is_dir($uploadDir)) {
    if (mkdir($uploadDir, 0755, true)) {
        echo "<p style='color: green;'>✅ Created uploads directory</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to create uploads directory</p>";
    }
} else {
    echo "<p style='color: green;'>✅ Uploads directory exists</p>";
    
    // Check if writable
    if (is_writable($uploadDir)) {
        echo "<p style='color: green;'>✅ Uploads directory is writable</p>";
    } else {
        echo "<p style='color: red;'>❌ Uploads directory is NOT writable</p>";
        echo "<p>Run: chmod 755 uploads/</p>";
    }
}
?>