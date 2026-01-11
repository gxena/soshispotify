<?php
/**
 * Reusable Sidebar Component
 * Usage: include 'includes/sidebar.php';
 * Set $activePage variable before including this file (e.g., $activePage = 'dashboard';)
 */

$css_path = 'assets/css/style.css'; // Sesuaikan path jika sidebar ada di dalam folder
$ver = file_exists($css_path) ? filemtime($css_path) : '1';


$activePage = $activePage ?? '';
?>

<!-- Sidebar -->
<aside class="sidebar">
    <div class="sidebar-header">
        <img src="PROFILE.png" alt="Profile" style="width: 36px; height: 36px; border-radius: 8px; object-fit: cover;">
    </div>
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item <?= $activePage === 'dashboard' ? 'active' : '' ?>" title="Dashboard">
            <i class="fas fa-th-large"></i>
        </a>
        <a href="members.php" class="nav-item <?= $activePage === 'members' ? 'active' : '' ?>" title="Members">
            <i class="fas fa-users"></i>
        </a>
        <a href="albums.php" class="nav-item <?= $activePage === 'albums' ? 'active' : '' ?>" title="Albums">
            <i class="fas fa-record-vinyl"></i>
        </a>
        <a href="scrape.php" class="nav-item <?= $activePage === 'scrape' ? 'active' : '' ?>" title="Scrape Data">
            <i class="fas fa-download"></i>
        </a>
        <a href="scrape_albums.php" class="nav-item <?= $activePage === 'scrape_albums' ? 'active' : '' ?>" title="Scrape Albums">
            <i class="fas fa-compact-disc"></i>
        </a>
        <a href="query.php" class="nav-item <?= $activePage === 'query' ? 'active' : '' ?>" title="SQL Query">
            <i class="fas fa-database"></i>
        </a>
    </nav>
</aside>
