<?php 
require_once 'config.php';
require_once 'api_helper.php';

// Get latest date from streams table
$latestDate = getLatestStreamDate();
if (!$latestDate) $latestDate = date('Y-m-d');

// Get filter from URL
$filter = $_GET['filter'] ?? '0Sadg1vgvaPqGTOjxu0N6c'; // Default to Girls' Generation artist_id

// Get stats based on filter
$dailyComparison = getDailyStreamsComparisonByFilter($filter);
$dailyStreams = $dailyComparison['current'];
$dailyStreamsPrev = $dailyComparison['previous'];

// Get artist stats comparison (ML and Followers)
$statsComparison = getArtistStatsComparisonByFilter($filter);
$isMultiProfile = $statsComparison['is_multi_profile'] ?? false;

if ($isMultiProfile) {
    // Format multi-profile stats as "[value1] & [value2]"
    $monthlyListeners = number_format($statsComparison['monthly_listeners'][0]) . ' & ' . number_format($statsComparison['monthly_listeners'][1]);
    $monthlyListenersDiff = $statsComparison['monthly_listeners_diff'][0] + $statsComparison['monthly_listeners_diff'][1]; // Total diff for arrow
    $followers = number_format($statsComparison['followers'][0]) . ' & ' . number_format($statsComparison['followers'][1]);
    $followersDiff = $statsComparison['followers_diff'][0] + $statsComparison['followers_diff'][1]; // Total diff for arrow
} else {
    $monthlyListeners = $statsComparison['monthly_listeners'];
    $monthlyListenersDiff = $statsComparison['monthly_listeners_diff'];
    $followers = $statsComparison['followers'];
    $followersDiff = $statsComparison['followers_diff'];
}

// Get total streams
$totalStreams = getTotalStreamsByFilter($filter);

// Get filter from URL
$filter = $_GET['filter'] ?? '0Sadg1vgvaPqGTOjxu0N6c'; // Default to Girls' Generation artist_id

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

// Get data for downloadable card (top 20 tracks)
$cardData = getTop20TracksForCard($filter);

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
$chartData = getDailyStreamsChart(31, $filter);

// Get all artists for filter dropdown
$allArtists = getArtistList();

$currentMonth = date('F Y');
$displayDate = date('d M Y', strtotime($latestDate));

// Function to darken color for table header
function darkenColor($hex, $percent = 30) {
    $hex = str_replace('#', '', $hex);
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $r = max(0, $r - ($r * $percent / 100));
    $g = max(0, $g - ($g * $percent / 100));
    $b = max(0, $b - ($b * $percent / 100));
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

// Get filter display name and card colors for download
$filterName = 'Girls\' Generation'; // Default
$cardBgColor1 = '#FF1493'; // Default pink gradient start
$cardBgColor2 = '#FF69B4'; // Default pink gradient end
$artistKorean = '소녀시대'; // Default Korean name
$artistHandle = '@GirlsGeneration'; // Default handle

if ($filter === 'all') {
    $filterName = 'All Artists';
    $artistKorean = '';
    $artistHandle = '';
} elseif ($filter === 'groups') {
    $filterName = 'Groups (GG + Subunits)';
    $artistKorean = '';
    $artistHandle = '';
} elseif ($filter === 'solo') {
    $filterName = 'Solo (All Members)';
    $artistKorean = '';
    $artistHandle = '';
} else {
    // Find artist name from list and set specific colors and Korean names
    foreach ($allArtists as $artist) {
        if ($artist['artist_id'] === $filter) {
            $filterName = $artist['artist_name'];
            
            // Map artist_id to Korean names
            switch ($artist['artist_id']) {
                // Taeyeon - Purple gradient
                case '3qNVuliS40BLgXGxhdBdqu':
                    $cardBgColor1 = '#A596D1';
                    $cardBgColor2 = '#8A7AB8';
                    $artistKorean = '태연';
                    break;
                    
                // Jessica - Gold gradient
                case '7jPVuaaHLs4QVSuN561jZt':
                    $cardBgColor1 = '#FFD700';
                    $cardBgColor2 = '#FFA500';
                    $artistKorean = '제시카';
                    break;
                    
                // Seohyun
                case '5uM1Et50auro2hTS6ZLcmT':
                    $artistKorean = '서현';
                    break;
                    
                // Sooyoung (both profiles)
                case '4k2XSHFx7PuRL7rgE3qncg':
                case '2mTYQHj19falvbVgsh6nkg':
                    $artistKorean = '수영';
                    break;
                    
                // Sunny
                case '5IphjHq07j65nO3Pl2YOWe':
                    $artistKorean = '써니';
                    break;
                    
                // Tiffany (both profiles)
                case '1t2HKR34gLWuQyyzLHcSm4':
                case '2lkCfFklQDBPlQzS4tR3VP':
                    $artistKorean = '티파니';
                    break;
                    
                // Yoona
                case '6LCX99hubn8CejiUtMCyyk':
                    $artistKorean = '윤아';
                    break;
                    
                // Yuri
                case '2TMRvcwsmvVhvuEbKVEbZe':
                    $artistKorean = '유리';
                    break;
                    
                // Hyoyeon (both profiles)
                case '0B3I6YgdnfXehUCpsO6oB8':
                case '3U7bOaJLuFkrmDQ1C1OqKl':
                    $artistKorean = '효연';
                    break;
            }
            
            break;
        }
    }
}

// Calculate dark header color
$cardHeaderColor = darkenColor($cardBgColor1, 35);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SoshiSpotify</title>
    <link rel="icon" type="image/png" href="PROFILE.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <?php $activePage = 'dashboard'; include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <header class="topbar">
                <div class="topbar-left">
                    <h1>Dashboard (<?php echo $displayDate; ?>)</h1>
                </div>
                <div class="topbar-right">
                    <button onclick="downloadCard()" class="download-btn" title="Download Card">
                        <i class="fas fa-download"></i>
                        Download Card
                    </button>
                    <button onclick="copyStats(this)" class="copy-stats-btn" title="Copy Stats\">
                        <i class="fas fa-copy"></i>
                        Copy Stats
                    </button>
                    <select class="filter-dropdown" onchange="location.href='dashboard.php?filter='+this.value">
                        <option value="all" <?php echo $filter=='all'?'selected':''; ?>>All</option>
                        <option value="0Sadg1vgvaPqGTOjxu0N6c" <?php echo $filter=='0Sadg1vgvaPqGTOjxu0N6c'?'selected':''; ?>>Girls' Generation</option>
                        <option value="groups" <?php echo $filter=='groups'?'selected':''; ?>>Groups (GG + Subunits)</option>
                        <option value="solo" <?php echo $filter=='solo'?'selected':''; ?>>Solo (All Members)</option>
                        <optgroup label="Subunits">
                            <option value="7AKHnZVqwXYuUwWJ8UGL5q" <?php echo $filter=='7AKHnZVqwXYuUwWJ8UGL5q'?'selected':''; ?>>Girls' Generation-TTS</option>
                            <option value="1foL9hLC9M6U94dINtOYfb" <?php echo $filter=='1foL9hLC9M6U94dINtOYfb'?'selected':''; ?>>Girls' Generation-Oh!GG</option>
                        </optgroup>
                        <optgroup label="Solo Members">
                            <?php 
                            $excludeIds = ['0Sadg1vgvaPqGTOjxu0N6c', '7AKHnZVqwXYuUwWJ8UGL5q', '1foL9hLC9M6U94dINtOYfb', '3U7bOaJLuFkrmDQ1C1OqKl', '2lkCfFklQDBPlQzS4tR3VP', '2mTYQHj19falvbVgsh6nkg'];
                            foreach ($allArtists as $artist): 
                                if (!in_array($artist['artist_id'], $excludeIds)):
                            ?>
                            <option value="<?php echo $artist['artist_id']; ?>" <?php echo $filter==$artist['artist_id']?'selected':''; ?>><?php echo htmlspecialchars($artist['artist_name']); ?></option>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </optgroup>
                    </select>
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
                        <h4>Daily Streams</h4>
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
                        <h2><?php echo $isMultiProfile ? $monthlyListeners : number_format($monthlyListeners); ?></h2>
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
                        <h2><?php echo $isMultiProfile ? $followers : number_format($followers); ?></h2>
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

                <!-- Top All-Time Song -->
                <div class="table-card">
                    <div class="table-header">
                        <h3>Top All-Time Song</h3>
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

                <!-- Top Daily Artist -->
                <div class="table-card">
                    <div class="table-header">
                        <h3>Top Daily Artist</h3>
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

                <!-- Top All-Time Artist -->
                <div class="table-card">
                    <div class="table-header">
                        <h3>Top All-Time Artist</h3>
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

    <!-- Hidden Card for Download -->
    <div id="downloadCard" style="position: absolute; left: -9999px; width: 768px; height: 1024px; font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
        <div style="background: linear-gradient(180deg, <?php echo $cardBgColor1; ?> 0%, <?php echo $cardBgColor2; ?> 100%); padding: 20px 25px 30px 25px; border-radius: 10px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); height: 100%; display: flex; flex-direction: column; box-sizing: border-box;">
            <!-- Header -->
            <div style="text-align: center; margin-bottom: 12px; margin-top: 15px;">
                <h1 style="color: white; font-size: 48px; font-weight: bold; margin: 0 0 4px 0; font-family: 'Poppins', sans-serif;"><?php echo htmlspecialchars($filterName); ?></h1>
                <div style="color: white; font-size: 20px; margin-bottom: -4px; font-family: 'Poppins', sans-serif;">Spotify - Total Streams</div>
                <div style="color: white; font-size: 32px; font-weight: bold; font-family: 'Poppins', sans-serif;">
                    <?php echo number_format($totalStreams); ?> 
                    <?php if ($dailyStreams - $dailyStreamsPrev != 0): ?>
                        <span style="font-size: 24px;">(<?php echo $dailyStreams - $dailyStreamsPrev > 0 ? '+' : ''; ?><?php echo number_format($dailyStreams - $dailyStreamsPrev); ?>)</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Table -->
            <table style="width: 100%; border-collapse: separate; border-spacing: 0; background: rgba(255, 255, 255, 0.95); border-radius: 10px; overflow: hidden; box-shadow: 0 8px 20px rgba(0,0,0,0.15); flex: 1;">
                <thead>
                    <tr style="background: <?php echo $cardHeaderColor; ?>; color: white;">
                        <th style="padding: 10px 6px; text-align: center; font-weight: 600; font-size: 15px; border-bottom: 2px solid rgba(255,255,255,0.2); font-family: 'Poppins', sans-serif;">#</th>
                        <th style="padding: 10px 12px; text-align: left; font-weight: 600; font-size: 15px; border-bottom: 2px solid rgba(255,255,255,0.2); font-family: 'Poppins', sans-serif;">Song</th>
                        <th style="padding: 10px 6px; text-align: right; font-weight: 600; font-size: 15px; border-bottom: 2px solid rgba(255,255,255,0.2); font-family: 'Poppins', sans-serif;">Daily Streams</th>
                        <th style="padding: 10px 6px 10px 20px; text-align: center; font-weight: 600; font-size: 15px; border-bottom: 2px solid rgba(255,255,255,0.2); font-family: 'Poppins', sans-serif;">+/-</th>
                        <th style="padding: 10px 10px; text-align: right; font-weight: 600; font-size: 15px; border-bottom: 2px solid rgba(255,255,255,0.2); font-family: 'Poppins', sans-serif;">Total Stream</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rowCount = 0;
                    foreach ($cardData as $track): 
                        $rowCount++;
                        $bgColor = $rowCount % 2 == 0 ? 'rgba(255, 228, 242, 0.5)' : 'rgba(255, 255, 255, 0.8)';
                        $percentColor = $track['percent_change'] >= 0 ? '#10B981' : '#EF4444';
                        $percentSign = $track['percent_change'] >= 0 ? '+' : '';
                    ?>
                    <tr style="background: <?php echo $bgColor; ?>;">
                        <td style="padding: 7px 6px; text-align: center; color: #1F2937; font-weight: 500; font-size: 12px; font-family: 'Poppins', sans-serif;"><?php echo $track['rank']; ?>(<?php echo $track['rank_change']; ?>)</td>
                        <td style="padding: 7px 12px; text-align: left; color: #1F2937; font-weight: 600; font-size: 14px; font-family: 'Poppins', sans-serif;"><?php echo htmlspecialchars($track['track_name']); ?></td>
                        <td style="padding: 7px 6px; text-align: right; color: #1F2937; font-weight: 500; font-size: 14px; font-family: 'Poppins', sans-serif;"><?php echo number_format($track['daily_streams']); ?></td>
                        <td style="padding: 7px 6px 7px 20px; text-align: center; color: <?php echo $percentColor; ?>; font-weight: 600; font-size: 14px; font-family: 'Poppins', sans-serif; opacity: 0.8;"><?php echo $percentSign . number_format($track['percent_change'], 2); ?>%</td>
                        <td style="padding: 7px 10px; text-align: right; color: #1F2937; font-weight: 500; font-size: 14px; font-family: 'Poppins', sans-serif;"><?php echo number_format($track['total_streams']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Footer -->
            <div style="margin-top: -4px; display: flex; justify-content: space-between; align-items: center; padding: 10px 15px 5px 15px;">
                <div style="color: white; font-size: 14px; font-weight: 500; font-family: 'Poppins', sans-serif;"><?php echo date('d F, Y', strtotime($latestDate)); ?></div>
                <div style="color: white; font-size: 14px; font-weight: 600; font-family: 'Poppins', sans-serif;">SoshiSpotify</div>
            </div>
        </div>
    </div>

    <!-- HTML2Canvas Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    
    <script>
        function copyStats(buttonEl) {
            try {
                const filterName = <?php echo json_encode($filterName); ?>;
                const artistKorean = <?php echo json_encode($artistKorean); ?>;
                const artistHandle = <?php echo json_encode($artistHandle); ?>;
                const date = '<?php echo date('M d, Y', strtotime($latestDate)); ?>';
                const isMultiProfile = <?php echo json_encode($isMultiProfile); ?>;
                const followers = <?php echo json_encode($followers); ?>;
                const monthlyListeners = <?php echo json_encode($monthlyListeners); ?>;
                const totalStreams = '<?php echo number_format($totalStreams); ?>'.replace(/,/g, '.');
                const streamsChangeVal = <?php echo ($dailyStreams - $dailyStreamsPrev); ?>;
                const streamsChange = (streamsChangeVal > 0 ? '+' : '') + streamsChangeVal.toLocaleString().replace(/,/g, '.');
                
                // Handle multi-profile diffs separately
                const followersDiffArray = <?php echo $isMultiProfile ? json_encode($statsComparison['followers_diff']) : json_encode([$followersDiff]); ?>;
                const mlDiffArray = <?php echo $isMultiProfile ? json_encode($statsComparison['monthly_listeners_diff']) : json_encode([$monthlyListenersDiff]); ?>;
                
                let followersDiffDisplay, mlDiffDisplay;
                
                let followersFormatted, mlFormatted;
                
                if (isMultiProfile) {
                    // Parse multi-profile format: "123,456 & 789,012"
                    const followersParts = followers.split(' & ');
                    const mlParts = monthlyListeners.split(' & ');
                    
                    // Format each value with dot separator
                    const followers1 = followersParts[0].replace(/,/g, '.').replace(/\./g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                    const followers2 = followersParts[1].replace(/,/g, '.').replace(/\./g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                    const ml1 = mlParts[0].replace(/,/g, '.').replace(/\./g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                    const ml2 = mlParts[1].replace(/,/g, '.').replace(/\./g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                    
                    // Format diffs
                    const followersDiff1 = (followersDiffArray[0] > 0 ? '+' : '') + followersDiffArray[0].toLocaleString().replace(/,/g, '.');
                    const followersDiff2 = (followersDiffArray[1] > 0 ? '+' : '') + followersDiffArray[1].toLocaleString().replace(/,/g, '.');
                    const mlDiff1 = (mlDiffArray[0] > 0 ? '+' : '') + mlDiffArray[0].toLocaleString().replace(/,/g, '.');
                    const mlDiff2 = (mlDiffArray[1] > 0 ? '+' : '') + mlDiffArray[1].toLocaleString().replace(/,/g, '.');
                    
                    // Format: "value1 (+diff1) & value2 (+diff2)"
                    followersFormatted = `${followers1} (${followersDiff1}) & ${followers2} (${followersDiff2})`;
                    mlFormatted = `${ml1} (${mlDiff1}) & ${ml2} (${mlDiff2})`;
                    
                    followersDiffDisplay = '';
                    mlDiffDisplay = '';
                } else {
                    // Single profile - format normally
                    followersFormatted = followers.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                    mlFormatted = monthlyListeners.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                    
                    followersDiffDisplay = (followersDiffArray[0] > 0 ? '+' : '') + followersDiffArray[0].toLocaleString().replace(/,/g, '.');
                    mlDiffDisplay = (mlDiffArray[0] > 0 ? '+' : '') + mlDiffArray[0].toLocaleString().replace(/,/g, '.');
                }
                
                // Clean hashtag (remove spaces and special characters, keep only alphanumeric)
                const cleanHashtag = filterName.replace(/[^a-zA-Z0-9가-힣]/g, '').replace(/\s+/g, '');

                console.log('filterName:', filterName);
                console.log('cleanHashtag:', cleanHashtag);
                console.log('followers:', followersFormatted);
                console.log('monthlyListeners:', mlFormatted);
                console.log('totalStreams:', totalStreams);

                let statsText = `#${cleanHashtag} on Spotify [${date}]\n\n`;
                if (isMultiProfile) {
                    // Multi-profile already has diffs inside formatted strings
                    statsText += `Followers: ${followersFormatted}\n`;
                    statsText += `Monthly Listeners: ${mlFormatted}\n`;
                } else {
                    // Single profile needs diffs in parentheses
                    statsText += `Followers: ${followersFormatted} (${followersDiffDisplay})\n`;
                    statsText += `Monthly Listeners: ${mlFormatted} (${mlDiffDisplay})\n`;
                }
                statsText += `Streams: ${totalStreams} (${streamsChange})\n\n`;
                
                // Logic:
                // - If Girls' Generation (grup) → #소녀시대 @GirlsGeneration
                // - If solo member → #소녀시대 #[member] @GirlsGeneration + member's Twitter if they have one
                
                if (artistKorean === '소녀시대') {
                    // Girls' Generation (grup) - add hashtag and mention
                    statsText += `#소녀시대 @GirlsGeneration`;
                } else if (artistKorean) {
                    // Solo member - add grup + member hashtags and mentions
                    statsText += `#소녀시대 #${artistKorean} @GirlsGeneration`;
                    
                    // Add member's own Twitter mention if they are one of the 6 members
                    if (filterName.includes('TAEYEON')) {
                        statsText += ' @TAEYEONsmtown';
                    } else if (filterName.includes('SUNNY')) {
                        statsText += ' @Sunnyday515';
                    } else if (filterName.includes('TIFFANY')) {
                        statsText += ' @tiffanyyoung';
                    } else if (filterName.includes('HYOYEON')) {
                        statsText += ' @Hyoyeon_djhyo';
                    } else if (filterName.includes('SOOYOUNG')) {
                        statsText += ' @sychoiofficial';
                    } else if (filterName.includes('SEOHYUN')) {
                        statsText += ' @sjhsjh0628';
                    }
                }

                console.log('statsText:', statsText);

                // Copy to clipboard
                navigator.clipboard.writeText(statsText).then(() => {
                    console.log('Copied successfully!');
                    // Show success feedback
                    if (!buttonEl) buttonEl = event.target.closest('button');
                    const originalHTML = buttonEl.innerHTML;
                    buttonEl.innerHTML = '<i class="fas fa-check"></i> Copied!';
                    buttonEl.style.background = 'linear-gradient(135deg, #10B981 0%, #059669 100%)';
                    
                    setTimeout(() => {
                        buttonEl.innerHTML = originalHTML;
                        buttonEl.style.background = '';
                    }, 2000);
                }).catch(err => {
                    console.error('Clipboard error:', err);
                    alert('Failed to copy stats: ' + err.message);
                });
            } catch (error) {
                console.error('copyStats error:', error);
                alert('Error: ' + error.message);
            }
        }

        function downloadCard() {
            const card = document.getElementById('downloadCard');
            
            // Show loading state
            const btn = event.target.closest('button');
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;
            
            html2canvas(card, {
                backgroundColor: null,
                scale: 3,
                logging: false,
                windowWidth: 768,
                windowHeight: 1024
            }).then(canvas => {
                // Create download link
                const link = document.createElement('a');
                const filterName = '<?php echo addslashes($filterName); ?>';
                const date = '<?php echo date('Ymd', strtotime($latestDate)); ?>';
                link.download = `${filterName.replace(/[^a-z0-9]/gi, '_')}_${date}.png`;
                link.href = canvas.toDataURL('image/png');
                link.click();
                
                // Reset button
                btn.innerHTML = originalHTML;
                btn.disabled = false;
            }).catch(err => {
                console.error('Error generating image:', err);
                alert('Failed to generate image. Please try again.');
                btn.innerHTML = originalHTML;
                btn.disabled = false;
            });
        }
    </script>
</body>
</html>
