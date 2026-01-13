<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

$conn = getDBConnection();

// Get latest dates for calculations
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
$streamMode = $_GET['mode'] ?? 'cumulative'; // 'cumulative' or 'daily'

// Get available dates for the date columns (last 10 dates)
$datesQuery = "SELECT DISTINCT stream_date FROM streams ORDER BY stream_date DESC LIMIT 10";
$availableDates = $conn->query($datesQuery)->fetch_all(MYSQLI_ASSOC);

// Get artists for filter
$artistsQuery = "SELECT DISTINCT ar.artist_id, ar.artist_name 
                 FROM artist ar 
                 ORDER BY ar.artist_name";
$artists = $conn->query($artistsQuery)->fetch_all(MYSQLI_ASSOC);

// Build comprehensive query for albums and tracks
$sql = "SELECT DISTINCT
            'album' as row_type,
            a.album_id as id,
            a.album_name as name,
            NULL as album_name_ref,
            a.img_300 as image,
            a.img_640 as image_large,
            ar.artist_name,
            ar.artist_id
        FROM album a
        LEFT JOIN album_artist aa ON a.album_id = aa.album_id
        LEFT JOIN artist ar ON aa.artist_id = ar.artist_id
        WHERE 1=1";

if (!empty($filterArtist)) {
    $sql .= " AND ar.artist_id = '$filterArtist'";
}

$sql .= " UNION ALL 
        SELECT DISTINCT
            'track' as row_type,
            t.track_id as id,
            t.track_name as name,
            COALESCE(a.album_name, 'Orphan') as album_name_ref,
            a.img_300 as image,
            a.img_640 as image_large,
            ar.artist_name,
            ar.artist_id
        FROM track t
        LEFT JOIN track_artist ta ON t.track_id = ta.track_id
        LEFT JOIN artist ar ON ta.artist_id = ar.artist_id
        LEFT JOIN album_track at ON t.track_id = at.track_id
        LEFT JOIN album a ON at.album_id = a.album_id
        WHERE 1=1";

if (!empty($filterArtist)) {
    $sql .= " AND ar.artist_id = '$filterArtist'";
}

$sql .= " ORDER BY artist_name, album_name_ref, row_type, name";

$result = $conn->query($sql);
$itemsRaw = $result->fetch_all(MYSQLI_ASSOC);

// Remove duplicate tracks (keep only first occurrence by track_id)
$seenTracks = [];
$items = [];
foreach ($itemsRaw as $item) {
    if ($item['row_type'] === 'track') {
        if (isset($seenTracks[$item['id']])) {
            continue; // Skip duplicate track
        }
        $seenTracks[$item['id']] = true;
    }
    $items[] = $item;
}

// Now get stream data for each item
foreach ($items as &$item) {
    $item['streams'] = [];
    
    if ($item['row_type'] === 'album') {
        // Get album streams (sum of all tracks)
        foreach ($availableDates as $dateRow) {
            $date = $dateRow['stream_date'];
            $streamQuery = "SELECT SUM(s.stream_count) as total
                           FROM streams s
                           JOIN album_track at ON s.track_id = at.track_id
                           WHERE at.album_id = '{$item['id']}' 
                           AND s.stream_date = '$date'";
            $result = $conn->query($streamQuery);
            $row = $result->fetch_assoc();
            $item['streams'][$date] = $row['total'] ?? 0;
        }
    } else {
        // Get track streams
        foreach ($availableDates as $dateRow) {
            $date = $dateRow['stream_date'];
            $streamQuery = "SELECT stream_count as total
                           FROM streams
                           WHERE track_id = '{$item['id']}' 
                           AND stream_date = '$date'";
            $result = $conn->query($streamQuery);
            $row = $result->fetch_assoc();
            $item['streams'][$date] = $row['total'] ?? 0;
        }
    }
    
    // If mode is daily, calculate differences
    if ($streamMode === 'daily') {
        $dates = array_keys($item['streams']);
        rsort($dates); // Most recent first
        $dailyStreams = [];
        
        for ($i = 0; $i < count($dates); $i++) {
            if ($i === count($dates) - 1) {
                $dailyStreams[$dates[$i]] = 0; // Oldest date has no previous
            } else {
                $current = $item['streams'][$dates[$i]];
                $previous = $item['streams'][$dates[$i + 1]];
                $dailyStreams[$dates[$i]] = $current - $previous;
            }
        }
        $item['streams'] = $dailyStreams;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Database - SoshiSpotify</title>
    <link rel="icon" type="image/png" href="PROFILE.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        .filter-bar {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .filter-group label {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .filter-group select {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: 1px solid #E5E7EB;
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
            background: white;
            cursor: pointer;
        }
        
        .database-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .database-table-wrapper {
            overflow-x: auto;
        }
        
        .database-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .database-table thead {
            position: sticky;
            top: 0;
            z-index: 10;
            background: var(--primary-pink);
        }
        
        .database-table th {
            padding: 0.6rem 0.8rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.8rem;
            color: white;
            background: var(--primary-pink);
            border-bottom: 2px solid #FF1493;
            white-space: nowrap;
        }
        
        .database-table th:first-child {
            position: sticky;
            left: 0;
            z-index: 11;
            width: 80px;
            min-width: 80px;
        }
        
        .database-table th:nth-child(2) {
            position: sticky;
            left: 80px;
            z-index: 11;
            width: auto;
            min-width: auto;
        }
        
        .database-table th:not(:first-child):not(:nth-child(2)) {
            width: 120px;
            min-width: 120px;
        }
        
        .database-table tbody tr:nth-child(even) {
            background: #FFF5F9;
        }
        
        .database-table tbody tr:hover {
            background: var(--accent-pink);
        }
        
        .database-table td {
            padding: 0.5rem 0.8rem;
            font-size: 0.8rem;
            border-bottom: 1px solid #F3E8F0;
            white-space: nowrap;
        }
        
        .database-table td:first-child {
            position: sticky;
            left: 0;
            background: inherit;
            z-index: 5;
            width: 80px;
            min-width: 80px;
        }
        
        .database-table td:nth-child(2) {
            position: sticky;
            left: 80px;
            background: inherit;
            z-index: 5;
            width: auto;
            min-width: auto;
        }
        
        .album-row {
            font-weight: 600;
            background: #FFE4F0 !important;
        }
        
        .database-table .track-row {
            display: table-row !important;
        }
        
        .orphan-row {
            font-style: italic;
            color: #9CA3AF;
        }
        
        .stream-number {
            text-align: right;
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
        }
        
        .item-name {
            display: inline-block;
        }
        
        .item-image {
            width: 24px;
            height: 24px;
            border-radius: 3px;
            object-fit: cover;
            margin-right: 0.5rem;
            vertical-align: middle;
        }
        
        .type-cell {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            flex-wrap: nowrap;
        }
        
        .download-card-btn {
            padding: 0.15rem 0.3rem;
            border-radius: 4px;
            border: none;
            background: var(--primary-pink);
            color: white;
            font-size: 0.65rem;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
            flex-shrink: 0;
        }
        
        .download-card-btn:hover {
            background: var(--dark-pink);
            transform: translateY(-1px);
        }
        
        .download-card-btn i {
            font-size: 0.6rem;
        }
        
        .card-preview {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1000;
            display: none;
        }
        
        .card-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 999;
            display: none;
        }
        
        .downloadable-card {
            width: 1200px;
            height: 630px;
            background: #FFFDFD;
            border-radius: 0;
            padding: 0;
            color: var(--primary-pink);
            display: flex;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            position: relative;
        }
        
        .card-cover-wrap {
            width: 570px;
            height: 570px;
            flex-shrink: 0;
            border-radius: 8px;
            overflow: hidden;
            position: absolute;
            left: 40px;
            top: 30px;
        }
        
        .card-cover {
            width: 100%;
            height: 100%;
            object-fit: contain;
            background: #FFFDFD;
        }
        
        .card-cover.empty {
            background: #F3E8F0;
            border: 2px dashed #EC60A1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .card-content {
            width: 494px;
            height: calc(630px - 60px - 48px);
            position: absolute;
            left: 657px;
            top: 60px;
            padding-top: 24px;
            padding-bottom: 24px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .card-main-content {
            align-self: stretch;
            display: flex;
            flex-direction: column;
            gap: 37px;
            align-items: flex-end;
        }
        
        .card-header {
            align-self: stretch;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0;
            margin: 0;
            border-bottom: none !important;
        }
        
        .card-header-label {
            color: #9CA3AF;
            font-size: 20px;
            font-weight: 600;
            padding: 0;
            margin: 0;
        }
        
        .card-type {
            color: #9CA3AF;
            font-size: 20px;
            font-weight: 600;
            padding: 0;
            margin: 0;
        }
        
        .card-info-section {
            align-self: stretch;
            display: flex;
            flex-direction: column;
            gap: 30px;
        }
        
        .card-title-section {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .card-song-title {
            color: #EC60A1;
            font-size: 36px;
            font-weight: 700;
            word-wrap: break-word;
            line-height: 1.2;
        }
        
        .card-artist {
            color: #9CA3AF;
            font-size: 20px;
            font-weight: 600;
        }
        
        .card-streams-section {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .card-daily-streams {
            color: #E7388A;
            font-size: 48px;
            font-weight: 800;
            line-height: 1;
        }
        
        .card-percentage {
            padding: 2px 8px;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            font-size: 24px;
            font-weight: 700;
            width: fit-content;
        }
        
        .card-percentage.positive {
            background: #D1FAE5;
            color: #10B981;
        }
        
        .card-percentage.negative {
            background: #FEE2E2;
            color: #EF4444;
        }
        
        .card-total-streams {
            color: #9CA3AF;
            font-size: 20px;
            font-weight: 700;
        }
        
        .card-footer {
            align-self: stretch;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-footer-text {
            color: #F188B9;
            font-size: 16px;
            font-weight: 700;
        }
        
        .card-footer-date {
            color: #F188B9;
            font-size: 16px;
            font-weight: 700;
        }
        
        .close-preview {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1001;
            background: white;
            color: var(--primary-pink);
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .download-preview {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1001;
            background: var(--primary-pink);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(255, 105, 180, 0.4);
            display: none;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s;
        }
        
        .download-preview:hover {
            transform: translateX(-50%) translateY(-2px);
            box-shadow: 0 6px 16px rgba(255, 105, 180, 0.5);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php 
        $activePage = 'database';
        include 'includes/sidebar.php'; 
        ?>
        
        <main class="main-content">
            <div class="topbar">
                <div class="topbar-left">
                    <h1>Complete Database</h1>
                    <p style="display: block; margin-top: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">
                        Latest Data: <?= date('F d, Y', strtotime($latestDate)) ?>
                    </p>
                </div>
            </div>
            
            <div class="filter-bar">
                <div class="filter-group">
                    <label for="artistFilter">Artist:</label>
                    <select id="artistFilter" onchange="applyFilters()">
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
                    <label for="modeFilter">Stream Mode:</label>
                    <select id="modeFilter" onchange="applyFilters()">
                        <option value="cumulative" <?= $streamMode === 'cumulative' ? 'selected' : '' ?>>
                            Cumulative
                        </option>
                        <option value="daily" <?= $streamMode === 'daily' ? 'selected' : '' ?>>
                            Daily
                        </option>
                    </select>
                </div>
            </div>
            
            <div class="database-container">
                <div class="database-table-wrapper">
                    <table class="database-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Title</th>
                                <?php foreach ($availableDates as $dateRow): ?>
                                    <th><?= date('m/d/Y', strtotime($dateRow['stream_date'])) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $currentAlbum = null;
                            $groupedItems = [];
                            
                            // Group tracks by album
                            foreach ($items as $item) {
                                if ($item['row_type'] === 'album') {
                                    $groupedItems[$item['id']] = [
                                        'album' => $item,
                                        'tracks' => []
                                    ];
                                } else {
                                    $albumRef = $item['album_name_ref'] ?? 'Orphan';
                                    $albumKey = $albumRef === 'Orphan' ? 'orphan' : null;
                                    
                                    // Try to find the album for this track
                                    if ($albumKey !== 'orphan') {
                                        $found = false;
                                        foreach ($groupedItems as $albumId => &$group) {
                                            if ($group['album']['name'] === $albumRef) {
                                                $group['tracks'][] = $item;
                                                $found = true;
                                                break;
                                            }
                                        }
                                        if (!$found) {
                                            if (!isset($groupedItems['orphan'])) {
                                                $groupedItems['orphan'] = [
                                                    'album' => [
                                                        'row_type' => 'album',
                                                        'id' => 'orphan',
                                                        'name' => 'Orphan',
                                                        'album_name_ref' => null,
                                                        'image' => null,
                                                        'image_large' => null,
                                                        'artist_name' => $item['artist_name'],
                                                        'streams' => []
                                                    ],
                                                    'tracks' => []
                                                ];
                                            }
                                            $groupedItems['orphan']['tracks'][] = $item;
                                        }
                                    } else {
                                        if (!isset($groupedItems['orphan'])) {
                                            $groupedItems['orphan'] = [
                                                'album' => [
                                                    'row_type' => 'album',
                                                    'id' => 'orphan',
                                                    'name' => 'Orphan',
                                                    'album_name_ref' => null,
                                                    'image' => null,
                                                    'image_large' => null,
                                                    'artist_name' => $item['artist_name'],
                                                    'streams' => []
                                                ],
                                                'tracks' => []
                                            ];
                                        }
                                        $groupedItems['orphan']['tracks'][] = $item;
                                    }
                                }
                            }
                            
                            // Display grouped items
                            foreach ($groupedItems as $albumId => $group):
                                $album = $group['album'];
                                $tracks = $group['tracks'];
                                
                                // Calculate album totals for all dates
                                $albumStreams = [];
                                foreach ($availableDates as $dateRow) {
                                    $date = $dateRow['stream_date'];
                                    $total = 0;
                                    foreach ($tracks as $track) {
                                        $total += $track['streams'][$date] ?? 0;
                                    }
                                    $albumStreams[$date] = $total;
                                }
                                
                                // Get first two dates for daily calculation
                                $dateKeys = array_keys($albumStreams);
                                $albumStreams1st = $albumStreams[$dateKeys[0]] ?? 0;
                                $albumStreams2nd = $albumStreams[$dateKeys[1] ?? $dateKeys[0]] ?? 0;
                                
                                // Calculate total streams for album (cumulative latest)
                                $albumTotalStreams = 0;
                                if ($streamMode === 'cumulative') {
                                    $albumTotalStreams = $albumStreams1st;
                                } else {
                                    // For daily mode, sum all daily increments
                                    $albumTotalStreams = array_sum($albumStreams);
                                }
                            ?>
                                <!-- Album Row -->
                                <tr class="album-row <?= $albumId === 'orphan' ? 'orphan-row' : '' ?>">
                                    <td>
                                        <div class="type-cell">
                                            <i class="fas fa-folder"></i>
                                            <?php if ($albumId !== 'orphan'): 
                                                $albumNameEsc = htmlspecialchars($album['name'], ENT_QUOTES, 'UTF-8');
                                                $albumArtistEsc = htmlspecialchars($album['artist_name'] ?? '', ENT_QUOTES, 'UTF-8');
                                                $albumImage = $album['image_large'] ?? $album['image'] ?? '';
                                                
                                                // Get cumulative album values from database
                                                $albumCumulative1st = 0;
                                                $albumCumulative2nd = 0;
                                                $albumCumulative3rd = 0;
                                                if (isset($dateKeys[0])) {
                                                    $albumCumQuery1 = $conn->query("SELECT SUM(s.stream_count) as total FROM streams s JOIN album_track at ON s.track_id = at.track_id WHERE at.album_id = '{$album['id']}' AND s.stream_date = '{$dateKeys[0]}'");
                                                    $albumCumulative1st = $albumCumQuery1->fetch_assoc()['total'] ?? 0;
                                                }
                                                if (isset($dateKeys[1])) {
                                                    $albumCumQuery2 = $conn->query("SELECT SUM(s.stream_count) as total FROM streams s JOIN album_track at ON s.track_id = at.track_id WHERE at.album_id = '{$album['id']}' AND s.stream_date = '{$dateKeys[1]}'");
                                                    $albumCumulative2nd = $albumCumQuery2->fetch_assoc()['total'] ?? 0;
                                                }
                                                if (isset($dateKeys[2])) {
                                                    $albumCumQuery3 = $conn->query("SELECT SUM(s.stream_count) as total FROM streams s JOIN album_track at ON s.track_id = at.track_id WHERE at.album_id = '{$album['id']}' AND s.stream_date = '{$dateKeys[2]}'");
                                                    $albumCumulative3rd = $albumCumQuery3->fetch_assoc()['total'] ?? 0;
                                                }
                                            ?>
                                                <button class="download-card-btn" 
                                                        onclick='downloadAlbumCard("<?= $album['id'] ?>", "<?= addslashes($albumNameEsc) ?>", "<?= addslashes($albumArtistEsc) ?>", <?= $albumCumulative1st ?>, "<?= addslashes($albumImage) ?>", <?= $albumTotalStreams ?>, <?= $albumCumulative2nd ?>, <?= $albumCumulative3rd ?>, "<?= $dateKeys[0] ?>")'>
                                                    <i class="fas fa-download"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($album['image']): ?>
                                            <img src="<?= htmlspecialchars($album['image']) ?>" 
                                                 alt="Album" class="item-image">
                                        <?php endif; ?>
                                        <strong><?= htmlspecialchars($album['name']) ?></strong>
                                    </td>
                                    <?php foreach ($availableDates as $dateRow): 
                                        $date = $dateRow['stream_date'];
                                    ?>
                                        <td class="stream-number">
                                            <?= number_format($albumStreams[$date] ?? 0) ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                                
                                <!-- Track Rows -->
                                <?php foreach ($tracks as $track): 
                                    $trackNameEsc = htmlspecialchars($track['name'], ENT_QUOTES, 'UTF-8');
                                    $trackArtistEsc = htmlspecialchars($track['artist_name'] ?? '', ENT_QUOTES, 'UTF-8');
                                    $trackImage = $track['image_large'] ?? $track['image'] ?? '';
                                    
                                    // Get cumulative stream values for the first three dates
                                    $trackDateKeys = array_keys($track['streams']);
                                    $date1 = $trackDateKeys[0] ?? null;
                                    $date2 = $trackDateKeys[1] ?? $trackDateKeys[0] ?? null;
                                    $date3 = $trackDateKeys[2] ?? $trackDateKeys[1] ?? null;
                                    
                                    // Get CUMULATIVE values from database
                                    $trackCumulative1st = 0;
                                    $trackCumulative2nd = 0;
                                    $trackCumulative3rd = 0;
                                    if ($date1) {
                                        $cumQuery1 = $conn->query("SELECT stream_count FROM streams WHERE track_id = '{$track['id']}' AND stream_date = '$date1'");
                                        $trackCumulative1st = $cumQuery1->fetch_assoc()['stream_count'] ?? 0;
                                    }
                                    if ($date2) {
                                        $cumQuery2 = $conn->query("SELECT stream_count FROM streams WHERE track_id = '{$track['id']}' AND stream_date = '$date2'");
                                        $trackCumulative2nd = $cumQuery2->fetch_assoc()['stream_count'] ?? 0;
                                    }
                                    if ($date3) {
                                        $cumQuery3 = $conn->query("SELECT stream_count FROM streams WHERE track_id = '{$track['id']}' AND stream_date = '$date3'");
                                        $trackCumulative3rd = $cumQuery3->fetch_assoc()['stream_count'] ?? 0;
                                    }
                                    
                                    // Display values based on mode
                                    $trackStreams1st = $track['streams'][$date1] ?? 0;
                                    $trackStreams2nd = $track['streams'][$date2] ?? 0;
                                    
                                    // Total streams for track
                                    $trackTotalStreams = ($streamMode === 'cumulative') ? $trackStreams1st : array_sum($track['streams']);
                                ?>
                                <tr class="track-row">
                                    <td>
                                        <div class="type-cell">
                                            <i class="fas fa-music"></i>
                                            <button class="download-card-btn" 
                                                    onclick='downloadTrackCard("<?= $track['id'] ?>", "<?= addslashes($trackNameEsc) ?>", "<?= addslashes($trackArtistEsc) ?>", <?= $trackCumulative1st ?>, "<?= addslashes($trackImage) ?>", <?= $trackTotalStreams ?>, <?= $trackCumulative2nd ?>, <?= $trackCumulative3rd ?>, "<?= $date1 ?>")'>
                                                <i class="fas fa-download"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td style="padding-left: 2rem;">
                                        <?= htmlspecialchars($track['name']) ?>
                                    </td>
                                    <?php foreach ($availableDates as $dateRow): 
                                        $date = $dateRow['stream_date'];
                                    ?>
                                        <td class="stream-number">
                                            <?= number_format($track['streams'][$date] ?? 0) ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Card Preview Overlay -->
    <div class="card-overlay" id="cardOverlay" onclick="closePreview()"></div>
    <button class="close-preview" id="closePreview" onclick="closePreview()" style="display: none;">
        <i class="fas fa-times"></i>
    </button>
    <button class="download-preview" id="downloadPreview" onclick="downloadCard()">
        <i class="fas fa-download"></i> Download Card
    </button>
    <div class="card-preview" id="cardPreview">
        <div class="downloadable-card" id="downloadableCard">
            <div class="card-cover-wrap">
                <img class="card-cover" id="cardCover" alt="Cover">
            </div>
            <div class="card-content">
                <div class="card-main-content">
                    <div class="card-header">
                        <div class="card-header-label">SPOTIFY STREAMS</div>
                        <div class="card-type" id="cardType">TRACK</div>
                    </div>
                    <div class="card-info-section">
                        <div class="card-title-section">
                            <div class="card-song-title" id="cardSongTitle">Song Name</div>
                            <div class="card-artist" id="cardArtist">Artist Name</div>
                        </div>
                        <div class="card-streams-section">
                            <div class="card-daily-streams" id="cardDailyStreams">0</div>
                            <div class="card-percentage positive" id="cardPercentage">+0%</div>
                        </div>
                        <div class="card-total-streams" id="cardTotalStreams">Total Streams: 0</div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="card-footer-text">SoshiSpotify</div>
                    <div class="card-footer-date" id="cardDate">Jan 10, 2026</div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function applyFilters() {
            const artist = document.getElementById('artistFilter').value;
            const mode = document.getElementById('modeFilter').value;
            
            const params = new URLSearchParams();
            if (artist) params.append('artist', artist);
            params.append('mode', mode);
            
            window.location.href = 'database.php?' + params.toString();
        }
        
        function downloadTrackCard(trackId, trackName, artistName, cumulative1, coverImage, totalStreams, cumulative2, cumulative3, dataDate) {
            showCardPreview(trackName, artistName, cumulative1, coverImage, totalStreams, cumulative2, cumulative3, 'TRACK', dataDate);
        }
        
        function downloadAlbumCard(albumId, albumName, artistName, cumulative1, coverImage, totalStreams, cumulative2, cumulative3, dataDate) {
            showCardPreview(albumName, artistName, cumulative1, coverImage, totalStreams, cumulative2, cumulative3, 'ALBUM', dataDate);
        }
        
        function showCardPreview(title, artist, cumulative1, coverImage, totalStreams, cumulative2, cumulative3, type, dataDate) {
            // Calculate DAILY streams for today and yesterday
            const todayDaily = cumulative1 - cumulative2;  // Today's daily streams
            const yesterdayDaily = cumulative2 - cumulative3;  // Yesterday's daily streams
            
            // Calculate percentage change between today's daily vs yesterday's daily
            let changePercent = 0;
            if (yesterdayDaily > 0) {
                changePercent = ((todayDaily - yesterdayDaily) / yesterdayDaily * 100).toFixed(1);
            }
            
            document.getElementById('cardSongTitle').textContent = title;
            document.getElementById('cardArtist').textContent = artist;
            document.getElementById('cardDailyStreams').textContent = todayDaily.toLocaleString();
            document.getElementById('cardType').textContent = type || 'TRACK';
            
            // Set date from database date
            const dbDate = new Date(dataDate);
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const dateStr = months[dbDate.getMonth()] + ' ' + dbDate.getDate() + ', ' + dbDate.getFullYear();
            document.getElementById('cardDate').textContent = dateStr;
            
            const percentEl = document.getElementById('cardPercentage');
            const changeValue = parseFloat(changePercent);
            percentEl.textContent = (changeValue >= 0 ? '+' : '') + changePercent + '%';
            percentEl.className = 'card-percentage ' + (changeValue >= 0 ? 'positive' : 'negative');
            
            document.getElementById('cardTotalStreams').textContent = 'Total Streams: ' + (totalStreams || 0).toLocaleString();
            
            const coverEl = document.getElementById('cardCover');
            if (coverImage && coverImage !== 'null' && coverImage !== '') {
                coverEl.src = coverImage;
                coverEl.style.display = 'block';
                coverEl.classList.remove('empty');
            } else {
                coverEl.src = '';
                coverEl.style.display = 'none';
                coverEl.classList.add('empty');
            }
            
            document.getElementById('cardOverlay').style.display = 'block';
            document.getElementById('cardPreview').style.display = 'block';
            document.getElementById('closePreview').style.display = 'block';
            document.getElementById('downloadPreview').style.display = 'block';
        }
        
        function closePreview() {
            document.getElementById('cardOverlay').style.display = 'none';
            document.getElementById('cardPreview').style.display = 'none';
            document.getElementById('closePreview').style.display = 'none';
            document.getElementById('downloadPreview').style.display = 'none';
        }
        
        function downloadCard() {
            const card = document.getElementById('downloadableCard');
            
            html2canvas(card, {
                scale: 2,
                backgroundColor: '#FFFDFD',
                logging: false,
                useCORS: true,
                allowTaint: true,
                width: 1200,
                height: 630
            }).then(canvas => {
                const link = document.createElement('a');
                const title = document.getElementById('cardSongTitle').textContent;
                link.download = `${title.replace(/[^a-z0-9]/gi, '_')}_card.png`;
                link.href = canvas.toDataURL();
                link.click();
            });
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>
