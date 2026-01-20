<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

$conn = getDBConnection();

// Get latest date and previous date
$latestDate = null;
$prevDate = null;
$dateResult = $conn->query("SELECT MAX(stream_date) as latest FROM streams");
if ($dateResult) {
    $latestDate = $dateResult->fetch_assoc()['latest'] ?? date('Y-m-d');
    $prevStmt = $conn->prepare("SELECT MAX(stream_date) as prev FROM streams WHERE stream_date < ?");
    $prevStmt->bind_param('s', $latestDate);
    $prevStmt->execute();
    $prevDate = $prevStmt->get_result()->fetch_assoc()['prev'] ?? null;
    $prevStmt->close();
}

// Get filter parameters
$filterArtist = $_GET['artist'] ?? '';
$filterType = $_GET['type'] ?? '';

// Build query with filters
$sql = "SELECT DISTINCT 
            a.album_id,
            a.album_name,
            a.type,
            a.release_date,
            a.img_640,
            a.img_300,
            a.img_64,
            ar.artist_name,
            ar.artist_id,
            COALESCE(SUM(s1.stream_count - COALESCE(s2.stream_count, 0)), 0) as daily_streams,
            COALESCE(SUM(s1.stream_count), 0) as total_streams
        FROM album a
        LEFT JOIN album_artist aa ON a.album_id = aa.album_id
        LEFT JOIN artist ar ON aa.artist_id = ar.artist_id
        LEFT JOIN album_track at ON a.album_id = at.album_id
        LEFT JOIN track t ON at.track_id = t.track_id
        LEFT JOIN streams s1 ON t.track_id = s1.track_id AND s1.stream_date = '$latestDate'
        LEFT JOIN streams s2 ON t.track_id = s2.track_id AND s2.stream_date = '$prevDate'
        WHERE 1=1";

$params = [];
$types = '';

if (!empty($filterArtist)) {
    $sql .= " AND ar.artist_id = ?";
    $params[] = $filterArtist;
    $types .= 's';
}

if (!empty($filterType)) {
    $sql .= " AND a.type = ?";
    $params[] = $filterType;
    $types .= 's';
}

$sql .= " GROUP BY a.album_id, a.album_name, a.type, a.release_date, a.img_640, a.img_300, a.img_64, ar.artist_name, ar.artist_id
          ORDER BY a.release_date DESC, a.album_name ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$albums = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get artists for filter
$artistsQuery = "SELECT DISTINCT ar.artist_id, ar.artist_name 
                 FROM artist ar 
                 JOIN album_artist aa ON ar.artist_id = aa.artist_id 
                 ORDER BY ar.artist_name";
$artists = $conn->query($artistsQuery)->fetch_all(MYSQLI_ASSOC);

// Get album types for filter
$typesQuery = "SELECT DISTINCT type FROM album WHERE type IS NOT NULL ORDER BY type";
$types = $conn->query($typesQuery)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Albums - SoshiSpotify</title>
    <link rel="icon" type="image/png" href="PROFILE.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/color-thief/2.3.0/color-thief.umd.js"></script>
    <style>
        /* Sidebar Styles - Match Dashboard/Members */
        .sidebar {
            width: 60px;
            background: #FFFFFF;
            border-right: 1px solid #F3E8F0;
            display: flex;
            flex-direction: column;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            height: 100vh;
            z-index: 100;
        }
        
        .sidebar-header {
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 1px solid #F3E8F0;
        }
        
        .sidebar-header i {
            font-size: 1.25rem;
            color: var(--primary-pink);
        }
        
        .sidebar-nav {
            flex: 1;
            padding: 1rem 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.25rem;
        }
        
        .nav-item {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.2s;
        }
        
        .nav-item:hover {
            background: var(--accent-pink);
            color: var(--primary-pink);
        }
        
        .nav-item.active {
            background: var(--primary-pink);
            color: white;
        }
        
        .nav-item i {
            font-size: 1rem;
        }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        .main-content {
            flex: 1;
            margin-left: 60px;
            padding: 1.5rem 2rem;
            min-height: 100vh;
            width: calc(100% - 60px);
        }
        
        .album-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .album-item {
            background: white;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(255, 105, 180, 0.08);
        }
        
        .album-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(255, 105, 180, 0.15);
        }
        
        .album-cover {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            object-fit: cover;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .album-info {
            flex: 1;
            min-width: 0;
        }
        
        .album-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .album-artist {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }
        
        .album-meta {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .album-stats {
            display: flex;
            gap: 30px;
            align-items: center;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-label {
            font-size: 11px;
            color: var(--text-secondary);
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        
        .stat-value {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-group label {
            font-size: 12px;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 500;
        }
        
        .filter-group select {
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid rgba(255, 105, 180, 0.2);
            background: white;
            color: var(--text-primary);
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .filter-group select:hover {
            border-color: var(--primary-pink);
        }
        
        .filter-group select:focus {
            outline: none;
            border-color: var(--primary-pink);
            box-shadow: 0 0 0 3px rgba(255, 105, 180, 0.1);
        }
        
        /* Album Card Popup */
        .album-popup {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 9999;
            padding: 20px;
            overflow-y: auto;
        }
        
        .album-popup.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .album-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 800px;
            width: 100%;
            position: relative;
            box-shadow: 0 20px 60px rgba(255, 105, 180, 0.3);
        }
        
        .loading-spinner {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
        }
        
        .loading-spinner i {
            font-size: 48px;
            color: var(--primary-pink);
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .album-card-header {
            display: flex;
            gap: 25px;
            margin-bottom: 20px;
            position: relative;
        }
        
        .album-card-cover {
            width: 180px;
            height: 180px;
            border-radius: 12px;
            object-fit: cover;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            flex-shrink: 0;
        }
        
        .album-card-info {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .album-card-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            padding-top: 10px;
        }
        
        .album-card-date {
            font-size: 15px;
            color: var(--text-secondary);
            margin-bottom: 15px;
        }
        
        .album-card-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            background: var(--accent-pink);
            padding: 20px;
            border-radius: 12px;
            align-items: center;
        }
        
        .card-stat {
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .card-stat-label {
            font-size: 11px;
            color: var(--text-secondary);
            text-transform: uppercase;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .card-stat-value {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.2;
            align-items: center;
        }
        
        .card-stat-change {
            font-size: 16px;
            font-weight: 600;
            line-height: 1.2;
            align-items: center;
        }
        
        .card-stat-change.positive {
            color: #4ade80;
        }
        
        .card-stat-change.negative {
            color: #f87171;
        }
        
        .album-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid rgba(255, 105, 180, 0.1);
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .album-card-footer .stream-date {
            font-weight: 500;
        }
        
        .album-card-footer .brand {
            font-weight: 600;
            color: var(--primary-pink);
        }
        
        .album-tracks {
            margin-top: 30px;
        }
        
        .tracks-header {
            display: grid;
            grid-template-columns: 30px 3fr 1fr 1fr 1fr 75px;
            gap: 15px;
            padding: 12px 15px;
            font-size: 11px;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 600;
            background: var(--accent-pink);
            border-radius: 8px 8px 0 0;
            text-align: center;
        }
        
        .track-row {
            display: grid;
            grid-template-columns: 30px 3fr 1fr 1fr 1fr 75px;
            gap: 15px;
            padding: 14px 15px;
            font-size: 14px;
            color: var(--text-primary);
            border-bottom: 1px solid rgba(255, 105, 180, 0.1);
            align-items: center;
        }
        
        .track-row > div:not(:nth-child(2)) {
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .track-row.empty {
            opacity: 0.3;
        }
        
        .track-number {
            font-weight: 600;
            color: var(--text-secondary);
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .track-row:hover {
            background: var(--bg-secondary);
        }
        
        .track-name {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-weight: 500;
        }
        
        .track-change.positive {
            color: var(--success);
            font-weight: normal;
            opacity: 0.8;
        }
        
        .track-change.negative {
            color: var(--error);
            font-weight: normal;
            opacity: 0.8;
        }
        
        .track-percent {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .track-percent.positive {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
        }
        
        .track-percent.negative {
            background: rgba(239, 68, 68, 0.15);
            color: var(--error);
        }
        
        .popup-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
            justify-content: flex-end;
        }
        
        .btn-download {
            padding: 12px 24px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary-pink), var(--dark-pink));
            color: white;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(255, 105, 180, 0.3);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(255, 105, 180, 0.4);
        }
        
        .btn-close {
            padding: 12px 24px;
            border-radius: 10px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s;
        }
        
        .btn-close:hover {
            background: #FFD4E8;
        }
        
        .close-popup {
            position: absolute;
            top: 20px;
            right: 20px;
            background: var(--bg-secondary);
            border: none;
            color: var(--text-primary);
            font-size: 24px;
            cursor: pointer;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .close-popup:hover {
            background: #FFD4E8;
            transform: rotate(90deg);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php $activePage = 'albums'; include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <header class="topbar">
                <div class="topbar-left">
                    <h1>Albums</h1>
                </div>
            </header>

            <div class="filters">
                <div class="filter-group">
                    <label>Artist</label>
                    <select id="filterArtist" onchange="applyFilters()">
                        <option value="">All Artists</option>
                        <?php foreach ($artists as $artist): ?>
                            <option value="<?= htmlspecialchars($artist['artist_id']) ?>" 
                                    <?= $filterArtist === $artist['artist_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($artist['artist_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Type</label>
                    <select id="filterType" onchange="applyFilters()">
                        <option value="">All Types</option>
                        <?php foreach ($types as $type): ?>
                            <option value="<?= htmlspecialchars($type['type']) ?>"
                                    <?= $filterType === $type['type'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars(ucfirst($type['type'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="album-list">
                <?php foreach ($albums as $album): ?>
                    <div class="album-item" 
                         data-album-id="<?= htmlspecialchars($album['album_id']) ?>"
                         onclick="openAlbumPopup('<?= htmlspecialchars($album['album_id']) ?>')">
                        <img src="<?= htmlspecialchars($album['img_300'] ?? $album['img_640'] ?? 'assets/img/default-album.png') ?>" 
                             alt="<?= htmlspecialchars($album['album_name']) ?>"
                             class="album-cover"
                             crossorigin="anonymous">
                        <div class="album-info">
                            <div class="album-title"><?= htmlspecialchars($album['album_name']) ?></div>
                            <div class="album-artist"><?= htmlspecialchars($album['artist_name']) ?></div>
                            <div class="album-meta">
                                <?= htmlspecialchars(ucfirst($album['type'])) ?> • 
                                <?= date('M d, Y', strtotime($album['release_date'])) ?>
                            </div>
                        </div>
                        <div class="album-stats">
                            <div class="stat-item">
                                <div class="stat-label">Daily</div>
                                <div class="stat-value">+<?= number_format($album['daily_streams']) ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">Total</div>
                                <div class="stat-value"><?= number_format($album['total_streams']) ?></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>

    <!-- Album Popup -->
    <div class="album-popup" id="albumPopup">
        <div class="album-card" id="albumCard">
            <button class="close-popup" onclick="closeAlbumPopup()">×</button>
            <div id="albumCardContent"></div>
            <div class="popup-actions">
                <button class="btn-close" onclick="closeAlbumPopup()">Close</button>
                <button class="btn-download" onclick="downloadAlbumCard()">
                    <i class="fas fa-download"></i> Download
                </button>
            </div>
        </div>
    </div>

    <script>
        function applyFilters() {
            const artist = document.getElementById('filterArtist').value;
            const type = document.getElementById('filterType').value;
            
            const params = new URLSearchParams();
            if (artist) params.set('artist', artist);
            if (type) params.set('type', type);
            
            window.location.href = 'albums.php' + (params.toString() ? '?' + params.toString() : '');
        }

        function openAlbumPopup(albumId) {
            console.log('Opening album popup for:', albumId);
            
            // Show popup with loading indicator
            const popup = document.getElementById('albumPopup');
            const contentDiv = document.getElementById('albumCardContent');
            contentDiv.innerHTML = '<div class="loading-spinner"><i class="fas fa-circle-notch"></i><p>Loading album data...</p></div>';
            popup.classList.add('active');
            
            fetch(`get_album_data.php?album_id=${albumId}`)
                .then(response => {
                    console.log('Response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Album data received:', data);
                    if (data.error) {
                        contentDiv.innerHTML = `<div class="loading-spinner"><p style="color: var(--error);">Error: ${data.error}</p></div>`;
                        return;
                    }
                    renderAlbumCard(data);
                })
                .catch(error => {
                    console.error('Error loading album data:', error);
                    contentDiv.innerHTML = `<div class="loading-spinner"><p style="color: var(--error);">Failed to load album data: ${error.message}</p></div>`;
                });
        }

        function closeAlbumPopup() {
            document.getElementById('albumPopup').classList.remove('active');
        }

        function renderAlbumCard(data) {
            const todayDaily = data.daily_streams;
            const yesterdayDaily = data.prev_daily_streams || 0;
            const change = todayDaily - yesterdayDaily;
            const changePercent = yesterdayDaily > 0 ? ((change / yesterdayDaily) * 100).toFixed(2) : 0;
            const isPositive = change >= 0;
            
            // Find max track number to determine how many rows to show
            let maxTrackNumber = 0;
            const tracksByNumber = {};
            
            data.tracks.forEach(track => {
                const trackNum = parseInt(track.track_number);
                if (trackNum > maxTrackNumber) maxTrackNumber = trackNum;
                tracksByNumber[trackNum] = track;
            });
            
            // Build track rows with gaps filled
            let tracksHtml = '';
            for (let i = 1; i <= maxTrackNumber; i++) {
                const track = tracksByNumber[i];
                
                if (track) {
                    const trackTodayDaily = track.daily_streams;
                    const trackYesterdayDaily = track.prev_daily_streams || 0;
                    const trackChange = trackTodayDaily - trackYesterdayDaily;
                    const trackPercent = trackYesterdayDaily > 0 ? ((trackChange / trackYesterdayDaily) * 100).toFixed(2) : 0;
                    const trackPositive = trackChange >= 0;
                    
                    tracksHtml += `
                        <div class="track-row">
                            <div class="track-number">${i}</div>
                            <div class="track-name">${escapeHtml(track.track_name)}</div>
                            <div>${formatNumber(trackTodayDaily)}</div>
                            <div>${formatNumber(track.total_streams)}</div>
                            <div class="track-change ${trackPositive ? 'positive' : 'negative'}">
                                ${trackPositive ? '+' : ''}${formatNumber(trackChange)}
                            </div>
                            <div class="track-percent ${trackPositive ? 'positive' : 'negative'}">
                                ${trackPositive ? '+' : ''}${trackPercent}%
                            </div>
                        </div>
                    `;
                } else {
                    // Empty row for missing track
                    tracksHtml += `
                        <div class="track-row empty">
                            <div class="track-number">${i}</div>
                            <div class="track-name"></div>
                            <div></div>
                            <div></div>
                            <div></div>
                            <div></div>
                        </div>
                    `;
                }
            }
            
            const html = `
                <div class="album-card-header">
                    <img src="${escapeHtml(data.img_640 || data.img_300)}" 
                         alt="${escapeHtml(data.album_name)}"
                         class="album-card-cover"
                         id="albumCoverForColor"
                         crossorigin="anonymous">
                    <div class="album-card-info">
                        <div class="album-card-title">${escapeHtml(data.album_name)}</div>
                        <div class="album-card-date">${escapeHtml(data.artist_name)} • ${formatSimpleDate(data.release_date)}</div>
                        <div class="album-card-stats">
                            <div class="card-stat">
                                <div class="card-stat-label">Today</div>
                                <div class="card-stat-value">+${formatNumber(todayDaily)}</div>
                            </div>
                            <div class="card-stat">
                                <div class="card-stat-label">Total</div>
                                <div class="card-stat-value">${formatNumber(data.total_streams)}</div>
                            </div>
                            <div class="card-stat">
                                <div class="card-stat-label">Change</div>
                                <div class="card-stat-change ${isPositive ? 'positive' : 'negative'}">
                                    ${isPositive ? '+' : ''}${formatNumber(change)} / ${isPositive ? '+' : ''}${changePercent}%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="album-tracks">
                    <div class="tracks-header">
                        <div>#</div>
                        <div>Song</div>
                        <div>Daily</div>
                        <div>Total</div>
                        <div>Change</div>
                        <div>%</div>
                    </div>
                    ${tracksHtml}
                </div>
                <div class="album-card-footer">
                    <div class="stream-date">${formatSimpleDate(data.stream_date || <?php echo json_encode($latestDate); ?>)}</div>
                    <div class="brand">by SoshiSpotify</div>
                </div>
            `;
            
            document.getElementById('albumCardContent').innerHTML = html;
            
            // Extract dominant color from album cover
            setTimeout(() => {
                extractDominantColor();
            }, 100);
        }

        function extractDominantColor() {
            const img = document.getElementById('albumCoverForColor');
            if (!img) return;
            
            const colorThief = new ColorThief();
            
            if (img.complete) {
                applyColor(img);
            } else {
                img.addEventListener('load', function() {
                    applyColor(img);
                });
            }
            
            function applyColor(image) {
                try {
                    const dominantColor = colorThief.getColor(image);
                    const rgb = `rgb(${dominantColor[0]}, ${dominantColor[1]}, ${dominantColor[2]})`;
                    const rgba = `rgba(${dominantColor[0]}, ${dominantColor[1]}, ${dominantColor[2]}, 0.15)`;
                    
                    document.getElementById('albumCard').style.setProperty('--primary-color', rgb);
                    document.querySelector('.album-card-stats').style.background = rgba;
                } catch (e) {
                    console.error('Error extracting color:', e);
                }
            }
        }

        function downloadAlbumCard() {
            console.log('Starting album card download...');
            const card = document.getElementById('albumCard');
            const actions = card.querySelector('.popup-actions');
            const closeBtn = card.querySelector('.close-popup');
            
            if (!card) {
                console.error('Album card element not found');
                alert('Error: Cannot find album card to download');
                return;
            }
            
            // Check if html2canvas is loaded
            if (typeof html2canvas === 'undefined') {
                console.error('html2canvas library not loaded');
                alert('Error: Download library not loaded. Please refresh the page.');
                return;
            }
            
            // Hide buttons temporarily
            if (actions) actions.style.display = 'none';
            if (closeBtn) closeBtn.style.display = 'none';
            
            console.log('Capturing card with html2canvas...');
            html2canvas(card, {
                backgroundColor: '#ffffff',
                scale: 2,
                logging: true,
                useCORS: true,
                allowTaint: false,
                windowHeight: card.scrollHeight,
                height: card.scrollHeight
            }).then(canvas => {
                console.log('Canvas created successfully');
                // Restore buttons
                if (actions) actions.style.display = 'flex';
                if (closeBtn) closeBtn.style.display = 'flex';
                
                // Download with format: [artist]_[album]_[type]_[date]
                const albumName = document.querySelector('.album-card-title')?.textContent || 'album';
                const artistName = document.querySelector('.album-card-date')?.textContent.split(' •')[0] || 'artist';
                const albumType = 'album';
                const streamDate = <?php echo json_encode(date('Ymd', strtotime($latestDate))); ?>;
                
                const cleanArtist = artistName.replace(/[^a-z0-9]/gi, '_').toLowerCase();
                const cleanAlbum = albumName.replace(/[^a-z0-9]/gi, '_').toLowerCase();
                const filename = `${cleanArtist}_${cleanAlbum}_${albumType}_${streamDate}.png`;
                
                const link = document.createElement('a');
                link.download = filename;
                link.href = canvas.toDataURL('image/png');
                link.click();
                
                console.log('Download initiated:', filename);
            }).catch(error => {
                console.error('Error creating canvas:', error);
                // Restore buttons
                if (actions) actions.style.display = 'flex';
                if (closeBtn) closeBtn.style.display = 'flex';
                alert('Error downloading card: ' + error.message);
            });
        }

        function formatNumber(num) {
            return new Intl.NumberFormat('en-US').format(num);
        }

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', { 
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        function formatSimpleDate(dateStr) {
            const date = new Date(dateStr);
            const day = date.getDate();
            const month = date.toLocaleDateString('en-US', { month: 'short' });
            const year = date.getFullYear();
            return `${day} ${month} ${year}`;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Close popup when clicking outside
        document.getElementById('albumPopup').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAlbumPopup();
            }
        });
    </script>
</body>
</html>