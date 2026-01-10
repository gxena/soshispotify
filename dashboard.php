<?php 
require_once 'config.php';
require_once 'api_helper.php';

// Get latest date from streams table
$latestDate = getLatestStreamDate();
if (!$latestDate) $latestDate = date('Y-m-d');

// Get GG daily streams comparison
$ggDaily = getGGDailyStreamsComparison();
$dailyStreams = $ggDaily['current'];
$dailyStreamsPrev = $ggDaily['previous'];

// Get artist stats comparison
$statsComparison = getArtistStatsComparison('0Sadg1vgvaPqGTOjxu0N6c');
$monthlyListeners = $statsComparison['monthly_listeners'];
$monthlyListenersDiff = $statsComparison['monthly_listeners_diff'];
$followers = $statsComparison['followers'];
$followersDiff = $statsComparison['followers_diff'];

// Get total streams
$totalStreams = getTotalStreams();

// Get filter from URL
$filter = $_GET['filter'] ?? 'all';

// Get top 5 tracks DAILY INCREASE
$topTracksDaily = getTopTracksDailyIncrease(5, $filter);
$topTracks = [];
foreach ($topTracksDaily as $track) {
    $topTracks[] = [
        'name' => $track['track_name'],
        'plays' => $track['plays']
    ];
}

// Get top 5 tracks ALL TIME
$topTracksAllTime = getTopTracksAllTime(5, $filter);
$topTracksAll = [];
foreach ($topTracksAllTime as $track) {
    $topTracksAll[] = [
        'name' => $track['track_name'],
        'plays' => $track['plays']
    ];
}

// Get top 5 artists DAILY INCREASE
$topArtistsDaily = getTopArtistsDailyIncrease(5, $filter);
$topArtists = [];
foreach ($topArtistsDaily as $artist) {
    $topArtists[] = [
        'name' => $artist['artist_name'],
        'plays' => $artist['total_streams']
    ];
}

// Get top 5 artists ALL TIME
$topArtistsAllTime = getTopArtistsAllTime(5, $filter);
$topArtistsAll = [];
foreach ($topArtistsAllTime as $artist) {
    $topArtistsAll[] = [
        'name' => $artist['artist_name'],
        'plays' => $artist['total_streams']
    ];
}

// Get chart data for current month
$chartData = getDailyStreamsChart(31);
if (empty($chartData['data'])) {
    // Generate dummy data
    $labels = [];
    $data = [];
    for ($i = 1; $i <= 31; $i++) {
        $labels[] = $i . '/1';
        $data[] = rand(150000, 700000);
    }
    $chartData = ['labels' => $labels, 'data' => $data];
}

// Artist categories for filter
$artistCategories = [
    'all' => 'All',
    'group' => 'Group',
    'unit' => 'Unit', 
    'solo' => 'Solo'
];

// Individual artists for filter
$individualArtists = getArtistList();

$currentMonth = date('F Y');
$displayDate = date('d M Y', strtotime($latestDate));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SoshiSpotify</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-sliders-h"></i>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item active">
                    <i class="fas fa-th-large"></i>
                </a>
                <a href="members.php" class="nav-item">
                    <i class="fas fa-users"></i>
                </a>
                <a href="scrape.php" class="nav-item">
                    <i class="fas fa-download"></i>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <header class="topbar">
                <div class="topbar-left">
                    <h1>Dashboard (<?php echo $displayDate; ?>)</h1>
                </div>
            </header>

            <!-- Stats Cards - 4 columns for Girls' Generation only -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-info">
                        <h4>Total Streams</h4>
                        <h2><?php echo number_format($totalStreams); ?></h2>
                    </div>
                    <div class="stat-icon blue">
                        <i class="fas fa-headphones"></i>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h4>Daily Streams (GG)</h4>
                        <h2><?php echo number_format($dailyStreams); ?></h2>
                        <span class="stat-change <?php echo $dailyStreamsPrev >= 0 ? 'positive' : 'negative'; ?>">
                            <i class="fas fa-arrow-<?php echo $dailyStreamsPrev >= 0 ? 'up' : 'down'; ?>"></i> 
                            <?php echo ($dailyStreamsPrev >= 0 ? '+' : '') . number_format($dailyStreamsPrev); ?> from yesterday
                        </span>
                    </div>
                    <div class="stat-icon yellow">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h4>Monthly Listeners</h4>
                        <h2><?php echo number_format($monthlyListeners); ?></h2>
                        <span class="stat-change <?php echo $monthlyListenersDiff >= 0 ? 'positive' : 'negative'; ?>">
                            <i class="fas fa-arrow-<?php echo $monthlyListenersDiff >= 0 ? 'up' : 'down'; ?>"></i> 
                            <?php echo ($monthlyListenersDiff >= 0 ? '+' : '') . number_format($monthlyListenersDiff); ?> from yesterday
                        </span>
                    </div>
                    <div class="stat-icon green">
                        <i class="fas fa-users"></i>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h4>Followers</h4>
                        <h2><?php echo number_format($followers); ?></h2>
                        <span class="stat-change <?php echo $followersDiff >= 0 ? 'positive' : 'negative'; ?>">
                            <i class="fas fa-arrow-<?php echo $followersDiff >= 0 ? 'up' : 'down'; ?>"></i> 
                            <?php echo ($followersDiff >= 0 ? '+' : '') . number_format($followersDiff); ?> from yesterday
                        </span>
                    </div>
                    <div class="stat-icon red">
                        <i class="fas fa-heart"></i>
                    </div>
                </div>
            </div>

            <!-- Chart Section -->
            <div class="chart-section">
                <div class="chart-header">
                    <h3>Daily Streams of <?php echo $currentMonth; ?></h3>
                    <select onchange="location.href='dashboard.php?month='+this.value">
                        <option value="01-2026">January 2026</option>
                        <option value="12-2025">December 2025</option>
                        <option value="11-2025">November 2025</option>
                    </select>
                </div>
                <div class="chart-container">
                    <canvas id="streamsChart"></canvas>
                </div>
            </div>

            <!-- Bottom Tables -->
            <div class="tables-grid tables-grid-4">
                <!-- Top Daily Song -->
                <div class="table-card">
                    <div class="table-header">
                        <h3>Top Daily Song</h3>
                        <select onchange="location.href='dashboard.php?filter='+this.value">
                            <option value="all" <?php echo $filter=='all'?'selected':''; ?>>All</option>
                            <option value="group" <?php echo $filter=='group'?'selected':''; ?>>Group</option>
                            <option value="unit" <?php echo $filter=='unit'?'selected':''; ?>>Unit</option>
                            <option value="solo" <?php echo $filter=='solo'?'selected':''; ?>>Solo</option>
                            <?php foreach ($individualArtists as $artist): ?>
                            <option value="<?php echo urlencode($artist['artist_name']); ?>" <?php echo $filter==$artist['artist_name']?'selected':''; ?>><?php echo htmlspecialchars($artist['artist_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="data-table">
                        <div class="table-head">
                            <span>Song</span>
                            <span>Increase</span>
                        </div>
                        <?php foreach ($topTracks as $track): ?>
                        <div class="table-row">
                            <span class="song-name"><?php echo htmlspecialchars($track['name']); ?></span>
                            <span class="streams"><?php echo number_format($track['plays']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Top Daily Artist -->
                <div class="table-card">
                    <div class="table-header">
                        <h3>Top Daily Artist</h3>
                        <select onchange="location.href='dashboard.php?filter='+this.value">
                            <option value="all" <?php echo $filter=='all'?'selected':''; ?>>All</option>
                            <option value="group" <?php echo $filter=='group'?'selected':''; ?>>Group</option>
                            <option value="unit" <?php echo $filter=='unit'?'selected':''; ?>>Unit</option>
                            <option value="solo" <?php echo $filter=='solo'?'selected':''; ?>>Solo</option>
                        </select>
                    </div>
                    <div class="data-table">
                        <div class="table-head">
                            <span>Artist</span>
                            <span>Increase</span>
                        </div>
                        <?php foreach ($topArtists as $artist): ?>
                        <div class="table-row">
                            <span class="song-name"><?php echo htmlspecialchars($artist['name']); ?></span>
                            <span class="streams"><?php echo number_format($artist['plays']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Top All-Time Song -->
                <div class="table-card">
                    <div class="table-header">
                        <h3>Top All-Time Song</h3>
                        <select onchange="location.href='dashboard.php?filter='+this.value">
                            <option value="all" <?php echo $filter=='all'?'selected':''; ?>>All</option>
                            <option value="group" <?php echo $filter=='group'?'selected':''; ?>>Group</option>
                            <option value="unit" <?php echo $filter=='unit'?'selected':''; ?>>Unit</option>
                            <option value="solo" <?php echo $filter=='solo'?'selected':''; ?>>Solo</option>
                            <?php foreach ($individualArtists as $artist): ?>
                            <option value="<?php echo urlencode($artist['artist_name']); ?>" <?php echo $filter==$artist['artist_name']?'selected':''; ?>><?php echo htmlspecialchars($artist['artist_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="data-table">
                        <div class="table-head">
                            <span>Song</span>
                            <span>Total</span>
                        </div>
                        <?php foreach ($topTracksAll as $track): ?>
                        <div class="table-row">
                            <span class="song-name"><?php echo htmlspecialchars($track['name']); ?></span>
                            <span class="streams"><?php echo number_format($track['plays']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Top All-Time Artist -->
                <div class="table-card">
                    <div class="table-header">
                        <h3>Top All-Time Artist</h3>
                        <select onchange="location.href='dashboard.php?filter='+this.value">
                            <option value="all" <?php echo $filter=='all'?'selected':''; ?>>All</option>
                            <option value="group" <?php echo $filter=='group'?'selected':''; ?>>Group</option>
                            <option value="unit" <?php echo $filter=='unit'?'selected':''; ?>>Unit</option>
                            <option value="solo" <?php echo $filter=='solo'?'selected':''; ?>>Solo</option>
                        </select>
                    </div>
                    <div class="data-table">
                        <div class="table-head">
                            <span>Artist</span>
                            <span>Total</span>
                        </div>
                        <?php foreach ($topArtistsAll as $artist): ?>
                        <div class="table-row">
                            <span class="song-name"><?php echo htmlspecialchars($artist['name']); ?></span>
                            <span class="streams"><?php echo number_format($artist['plays']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        const ctx = document.getElementById('streamsChart').getContext('2d');
        const chartData = <?php echo json_encode($chartData); ?>;
        
        const gradient = ctx.createLinearGradient(0, 0, 0, 280);
        gradient.addColorStop(0, 'rgba(59, 130, 246, 0.3)');
        gradient.addColorStop(1, 'rgba(59, 130, 246, 0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: 'Streams',
                    data: chartData.data,
                    borderColor: '#3B82F6',
                    backgroundColor: gradient,
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#3B82F6',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1F2937',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        padding: 10,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y.toLocaleString() + ' streams';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0, 0, 0, 0.05)' },
                        ticks: {
                            color: '#9CA3AF',
                            font: { size: 11 },
                            callback: function(value) {
                                if (value >= 1000000) return (value / 1000000).toFixed(0) + 'M';
                                if (value >= 1000) return (value / 1000).toFixed(0) + 'K';
                                return value;
                            }
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#9CA3AF', font: { size: 11 } }
                    }
                }
            }
        });
    </script>
</body>
</html>
