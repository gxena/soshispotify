<?php 
require_once 'config.php';
require_once 'api_helper.php';

// Get artist ID from URL if clicking for details
$selectedArtist = isset($_GET['artist']) ? $_GET['artist'] : null;

// Define artist groups with actual artist_id from database
$artistGroups = [
    // GROUP
    '0Sadg1vgvaPqGTOjxu0N6c' => [
        'display_name' => "Girls' Generation",
        'type' => 'group',
        'artist_ids' => ['0Sadg1vgvaPqGTOjxu0N6c']
    ],
    // UNITS
    '7AKHnZVqwXYuUwWJ8UGL5q' => [
        'display_name' => "Girls' Generation-TTS",
        'type' => 'unit',
        'artist_ids' => ['7AKHnZVqwXYuUwWJ8UGL5q']
    ],
    '1foL9hLC9M6U94dINtOYfb' => [
        'display_name' => "Girls' Generation-Oh!GG",
        'type' => 'unit',
        'artist_ids' => ['1foL9hLC9M6U94dINtOYfb']
    ],
    // SOLO MEMBERS
    '3qNVuliS40BLgXGxhdBdqu' => [
        'display_name' => 'Taeyeon',
        'type' => 'solo',
        'artist_ids' => ['3qNVuliS40BLgXGxhdBdqu']
    ],
    '5IphjHq07j65nO3Pl2YOWe' => [
        'display_name' => 'Sunny',
        'type' => 'solo',
        'artist_ids' => ['5IphjHq07j65nO3Pl2YOWe']
    ],
    '1t2HKR34gLWuQyyzLHcSm4' => [
        'display_name' => 'Tiffany',
        'type' => 'solo',
        'artist_ids' => ['1t2HKR34gLWuQyyzLHcSm4', '2lkCfFklQDBPlQzS4tR3VP']
    ],
    '0B3I6YgdnfXehUCpsO6oB8' => [
        'display_name' => 'Hyoyeon',
        'type' => 'solo',
        'artist_ids' => ['0B3I6YgdnfXehUCpsO6oB8', '3U7bOaJLuFkrmDQ1C1OqKl']
    ],
    '2TMRvcwsmvVhvuEbKVEbZe' => [
        'display_name' => 'Yuri',
        'type' => 'solo',
        'artist_ids' => ['2TMRvcwsmvVhvuEbKVEbZe']
    ],
    '4k2XSHFx7PuRL7rgE3qncg' => [
        'display_name' => 'Sooyoung',
        'type' => 'solo',
        'artist_ids' => ['4k2XSHFx7PuRL7rgE3qncg', '2mTYQHj19falvbVgsh6nkg']
    ],
    '6LCX99hubn8CejiUtMCyyk' => [
        'display_name' => 'Yoona',
        'type' => 'solo',
        'artist_ids' => ['6LCX99hubn8CejiUtMCyyk']
    ],
    '5uM1Et50auro2hTS6ZLcmT' => [
        'display_name' => 'Seohyun',
        'type' => 'solo',
        'artist_ids' => ['5uM1Et50auro2hTS6ZLcmT']
    ],
    '7jPVuaaHLs4QVSuN561jZt' => [
        'display_name' => 'Jessica',
        'type' => 'solo',
        'artist_ids' => ['7jPVuaaHLs4QVSuN561jZt']
    ]
];

// Get stats for all artists
$artistsData = [];
foreach ($artistGroups as $key => $group) {
    $stats = getArtistStreamStatsByIds($group['artist_ids']);
    $artistsData[$key] = [
        'display_name' => $group['display_name'],
        'type' => $group['type'],
        'daily_streams' => $stats['daily_streams'],
        'total_streams' => $stats['total_streams'],
        'artist_ids' => $group['artist_ids']
    ];
}

// If artist is selected, get top 10 tracks
$topTracks = [];
if ($selectedArtist && isset($artistGroups[$selectedArtist])) {
    $topTracks = getArtistTopTracksByIds($artistGroups[$selectedArtist]['artist_ids'], 10);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members - SoshiSpotify</title>
    <link rel="icon" type="image/png" href="PROFILE.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <?php $activePage = 'members'; include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <header class="topbar">
                <div class="topbar-left">
                    <h1>Members</h1>
                </div>
            </header>

            <!-- Members List -->
            <div class="members-container">
                <!-- Group Section -->
                <div class="members-section">
                    <h2 class="section-title">Group</h2>
                    <?php foreach ($artistsData as $key => $artist): ?>
                        <?php if ($artist['type'] === 'group'): ?>
                        <div class="member-card <?php echo ($selectedArtist === $key) ? 'expanded' : ''; ?>">
                            <a href="members.php?artist=<?php echo urlencode($key); ?>" class="member-row">
                                <div class="member-name"><?php echo htmlspecialchars($artist['display_name']); ?></div>
                                <div class="member-daily">+<?php echo number_format($artist['daily_streams']); ?></div>
                                <div class="member-total"><?php echo number_format($artist['total_streams']); ?></div>
                                <div class="member-arrow"><i class="fas fa-chevron-right"></i></div>
                            </a>
                            <?php if ($selectedArtist === $key && !empty($topTracks)): ?>
                            <div class="member-details">
                                <h3>Top 10 Tracks</h3>
                                <?php foreach ($topTracks as $index => $track): ?>
                                <div class="track-row">
                                    <span class="track-rank"><?php echo $index + 1; ?></span>
                                    <span class="track-name"><?php echo htmlspecialchars($track['track_name']); ?></span>
                                    <span class="track-streams"><?php echo number_format($track['total_streams']); ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <!-- Unit Section -->
                <div class="members-section units-section">
                    <h2 class="section-title">Units</h2>
                    <div class="members-list"><?php foreach ($artistsData as $key => $artist): ?>
                        <?php if ($artist['type'] === 'unit'): ?>
                        <div class="member-card <?php echo ($selectedArtist === $key) ? 'expanded' : ''; ?>">
                            <a href="members.php?artist=<?php echo urlencode($key); ?>" class="member-row">
                                <div class="member-name"><?php echo htmlspecialchars($artist['display_name']); ?></div>
                                <div class="member-daily">+<?php echo number_format($artist['daily_streams']); ?></div>
                                <div class="member-total"><?php echo number_format($artist['total_streams']); ?></div>
                                <div class="member-arrow"><i class="fas fa-chevron-right"></i></div>
                            </a>
                            <?php if ($selectedArtist === $key && !empty($topTracks)): ?>
                            <div class="member-details">
                                <h3>Top 10 Tracks</h3>
                                <?php foreach ($topTracks as $index => $track): ?>
                                <div class="track-row">
                                    <span class="track-rank"><?php echo $index + 1; ?></span>
                                    <span class="track-name"><?php echo htmlspecialchars($track['track_name']); ?></span>
                                    <span class="track-streams"><?php echo number_format($track['total_streams']); ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </div>
                </div>

                <!-- Solo Section -->
                <div class="members-section solo-section">
                    <h2 class="section-title">Solo</h2>
                    <div class="members-list"><?php foreach ($artistsData as $key => $artist): ?>
                        <?php if ($artist['type'] === 'solo'): ?>
                        <div class="member-card <?php echo ($selectedArtist === $key) ? 'expanded' : ''; ?>">
                            <a href="members.php?artist=<?php echo urlencode($key); ?>" class="member-row">
                                <div class="member-name"><?php echo htmlspecialchars($artist['display_name']); ?></div>
                                <div class="member-daily">+<?php echo number_format($artist['daily_streams']); ?></div>
                                <div class="member-total"><?php echo number_format($artist['total_streams']); ?></div>
                                <div class="member-arrow"><i class="fas fa-chevron-right"></i></div>
                            </a>
                            <?php if ($selectedArtist === $key && !empty($topTracks)): ?>
                            <div class="member-details">
                                <h3>Top 10 Tracks</h3>
                                <?php foreach ($topTracks as $index => $track): ?>
                                <div class="track-row">
                                    <span class="track-rank"><?php echo $index + 1; ?></span>
                                    <span class="track-name"><?php echo htmlspecialchars($track['track_name']); ?></span>
                                    <span class="track-streams"><?php echo number_format($track['total_streams']); ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
