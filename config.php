<?php
// Prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Database configuration for InfinityFree
define('DB_HOST', 'sql308.infinityfree.com');
define('DB_USER', 'if0_40812483');
define('DB_PASS', 'WwZ7vUYBYaYXb');
define('DB_NAME', 'if0_40812483_soshispotify');

// Membuat koneksi database
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset to UTF-8 untuk support Hangul dan karakter khusus lainnya
    $conn->set_charset("utf8mb4");
    
    return $conn;
}

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Timezone
date_default_timezone_set('Asia/Jakarta');
?>
