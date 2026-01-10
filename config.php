<?php
// Prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Database configuration for InfinityFree
define('DB_HOST', 'sql210.infinityfree.com');
define('DB_USER', 'if0_37990201');
define('DB_PASS', 'WmN7zTTPPx7z');
define('DB_NAME', 'if0_37990201_soshispotify');

// Membuat koneksi database
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Timezone
date_default_timezone_set('Asia/Jakarta');
?>
