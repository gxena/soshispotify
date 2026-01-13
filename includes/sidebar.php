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
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="PROFILE.png" alt="Profile" style="width: 36px; height: 36px; border-radius: 8px; object-fit: cover;">
        <span class="sidebar-title">SoshiSpotify</span>
    </div>
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item <?= $activePage === 'dashboard' ? 'active' : '' ?>" title="Dashboard">
            <i class="fas fa-th-large"></i>
            <span class="nav-text">Dashboard</span>
        </a>
        <a href="members.php" class="nav-item <?= $activePage === 'members' ? 'active' : '' ?>" title="Members">
            <i class="fas fa-users"></i>
            <span class="nav-text">Members</span>
        </a>
        <a href="albums.php" class="nav-item <?= $activePage === 'albums' ? 'active' : '' ?>" title="Albums">
            <i class="fas fa-record-vinyl"></i>
            <span class="nav-text">Albums</span>
        </a>
        <a href="analytics.php" class="nav-item <?= $activePage === 'analytics' ? 'active' : '' ?>" title="Analytics">
            <i class="fas fa-chart-bar"></i>
            <span class="nav-text">Analytics</span>
        </a>
        <a href="database.php" class="nav-item <?= $activePage === 'database' ? 'active' : '' ?>" title="Complete Database">
            <i class="fas fa-table"></i>
            <span class="nav-text">Database</span>
        </a>
        <a href="scrape.php" class="nav-item <?= $activePage === 'scrape' ? 'active' : '' ?>" title="Scrape Data">
            <i class="fas fa-download"></i>
            <span class="nav-text">Scrape Data</span>
        </a>
        <a href="scrape_albums.php" class="nav-item <?= $activePage === 'scrape_albums' ? 'active' : '' ?>" title="Scrape Albums">
            <i class="fas fa-compact-disc"></i>
            <span class="nav-text">Scrape Albums</span>
        </a>
        <a href="query.php" class="nav-item <?= $activePage === 'query' ? 'active' : '' ?>" title="SQL Query">
            <i class="fas fa-database"></i>
            <span class="nav-text">SQL Query</span>
        </a>
    </nav>
    <div class="sidebar-footer">
        <button class="sidebar-toggle" id="sidebarToggle" title="Expand Sidebar">
            <i class="fas fa-chevron-right"></i>
        </button>
    </div>
</aside>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const mainContent = document.querySelector('.main-content');
    
    toggleBtn.addEventListener('click', function() {
        sidebar.classList.toggle('expanded');
        if (mainContent) {
            mainContent.classList.toggle('sidebar-expanded');
        }
        
        const icon = toggleBtn.querySelector('i');
        if (sidebar.classList.contains('expanded')) {
            icon.classList.remove('fa-chevron-right');
            icon.classList.add('fa-chevron-left');
        } else {
            icon.classList.remove('fa-chevron-left');
            icon.classList.add('fa-chevron-right');
        }
    });
});
</script>
