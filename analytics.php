<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'api_helper.php';

$latestDate = getLatestStreamDate();
if (!$latestDate) $latestDate = date('Y-m-d');

// Get filter from URL
$filter = $_GET['filter'] ?? '0Sadg1vgvaPqGTOjxu0N6c';
$changeType = $_GET['change_type'] ?? 'value'; // 'value' or 'percent'

// Get all artists for filter dropdown
$allArtists = getArtistList();

$displayDate = date('d M Y', strtotime($latestDate));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - SoshiSpotify</title>
    <link rel="icon" type="image/png" href="PROFILE.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
    <style>
        .analytics-section {
            background: white;
            border-radius: 14px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--accent-pink);
        }
        
        .section-header h2 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .section-header .badge {
            background: var(--primary-pink);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .analytics-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .analytics-table thead {
            background: linear-gradient(135deg, #FF69B4 0%, #FF1493 100%);
            color: white;
        }
        
        .analytics-table th {
            padding: 12px 16px;
            text-align: left;
            font-size: 0.875rem;
            font-weight: 600;
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .analytics-table th.center {
            text-align: center;
        }
        
        .analytics-table th.right {
            text-align: right;
        }
        
        .analytics-table th.clickable {
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        
        .analytics-table th.clickable:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .analytics-table th.clickable.active {
            background: rgba(255, 255, 255, 0.15);
        }
        
        .analytics-table th.clickable.active::after {
            content: '▼';
            margin-left: 5px;
            font-size: 0.7rem;
        }
        
        .analytics-table td {
            padding: 12px 16px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-size: 0.85rem;
        }
        
        .analytics-table td.right {
            text-align: right;
        }
        
        .analytics-table tbody tr {
            background: rgba(255, 255, 255, 0.8);
            transition: all 0.2s ease;
        }
        
        .analytics-table tbody tr:nth-child(even) {
            background: rgba(255, 228, 242, 0.3);
        }
        
        .analytics-table tbody tr:hover {
            background: rgba(255, 228, 242, 0.6);
            transform: scale(1.002);
        }
        
        .analytics-table tr:last-child td {
            border-bottom: none;
        }
        
        .track-name {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .artist-name {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        .number {
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .change-positive {
            color: var(--success);
            font-weight: 600;
        }
        
        .change-negative {
            color: var(--error);
            font-weight: 600;
        }
        
        .milestone-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            background: var(--card-yellow);
            color: #F59E0B;
        }
        
        .record-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            background: var(--card-red);
            color: var(--error);
        }
        
        .filter-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .filter-tab {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: 1px solid #E5E7EB;
            background: white;
            color: var(--text-secondary);
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .filter-tab:hover {
            border-color: var(--primary-pink);
            color: var(--primary-pink);
        }
        
        .filter-tab.active {
            background: var(--primary-pink);
            color: white;
            border-color: var(--primary-pink);
        }
        
        .progress-bar {
            height: 6px;
            background: #E5E7EB;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 0.25rem;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-pink), var(--dark-pink));
            transition: width 0.3s;
        }
        
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php $activePage = 'analytics'; include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="topbar">
                <div class="topbar-left">
                    <h1>Analytics (<?php echo $displayDate; ?>)</h1>
                </div>
                <div class="topbar-right">
                    <select class="filter-dropdown" onchange="location.href='analytics.php?filter='+this.value+'&change_type=<?php echo $changeType; ?>'">
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

            <!-- Milestone Tracker -->
            <div class="analytics-section">
                <div class="section-header">
                    <h2><i class="fas fa-flag-checkered"></i> Approaching Milestones</h2>
                    <span class="badge"><?php echo getMilestoneTrackerCount($filter); ?></span>
                </div>
                <table class="analytics-table">
                    <thead>
                        <tr>
                            <th>Song / Album</th>
                            <th>Type</th>
                            <th class="right">Current Streams</th>
                            <th class="right">Daily Streams</th>
                            <th class="right">Next Milestone</th>
                            <th class="right">Remaining</th>
                            <th class="right">Days Left</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $milestones = getMilestoneTracker($filter);
                        if (empty($milestones)): 
                        ?>
                            <tr><td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 2rem;">No items approaching milestones within 30 days</td></tr>
                        <?php else: 
                            foreach ($milestones as $item): 
                                $progressPercent = ($item['current_streams'] / $item['next_milestone']) * 100;
                        ?>
                            <tr>
                                <td>
                                    <div class="track-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                    <?php if ($item['type'] == 'track'): ?>
                                        <div class="artist-name"><?php echo htmlspecialchars($item['artist_name']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="milestone-badge">
                                        <?php echo $item['type'] == 'track' ? 'Song' : 'Album'; ?>
                                    </span>
                                </td>
                                <td class="right">
                                    <span class="number"><?php echo number_format($item['current_streams']); ?></span>
                                </td>
                                <td class="right">
                                    <span class="number"><?php echo number_format($item['daily_streams']); ?></span>
                                </td>
                                <td class="right">
                                    <strong><?php echo number_format($item['next_milestone']); ?></strong>
                                </td>
                                <td class="right">
                                    <span class="number"><?php echo number_format($item['remaining']); ?></span>
                                </td>
                                <td class="right">
                                    <strong style="color: var(--accent);"><?php echo number_format($item['days_remaining'], 1); ?> days</strong>
                                </td>
                            </tr>
                        <?php 
                            endforeach;
                        endif; 
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- Recent Milestones Passed -->
            <div class="analytics-section">
                <div class="section-header">
                    <h2><i class="fas fa-check-circle"></i> Recently Passed Milestones</h2>
                    <span class="badge"><?php echo getRecentMilestonePassedCount($filter); ?></span>
                </div>
                <table class="analytics-table">
                    <thead>
                        <tr>
                            <th>Song / Album</th>
                            <th>Type</th>
                            <th class="right">Current Streams</th>
                            <th class="right">Daily Streams</th>
                            <th class="right">Milestone Passed</th>
                            <th class="right">Days Ago</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $recentPassed = getRecentMilestonePassed($filter);
                        if (empty($recentPassed)): 
                        ?>
                            <tr><td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 2rem;">No milestones passed in the last 2 days</td></tr>
                        <?php else: 
                            foreach ($recentPassed as $item): 
                        ?>
                            <tr>
                                <td>
                                    <div class="track-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                    <?php if ($item['type'] == 'track'): ?>
                                        <div class="artist-name"><?php echo htmlspecialchars($item['artist_name']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="milestone-badge">
                                        <?php echo $item['type'] == 'track' ? 'Song' : 'Album'; ?>
                                    </span>
                                </td>
                                <td class="right">
                                    <span class="number"><?php echo number_format($item['current_streams']); ?></span>
                                </td>
                                <td class="right">
                                    <span class="number"><?php echo number_format($item['daily_streams']); ?></span>
                                </td>
                                <td class="right">
                                    <strong style="color: var(--success);">
                                        <i class="fas fa-trophy"></i> <?php echo number_format($item['milestone_passed']); ?>
                                    </strong>
                                </td>
                                <td class="right">
                                    <strong style="color: var(--accent);"><?php echo number_format($item['days_ago'], 1); ?> days</strong>
                                </td>
                            </tr>
                        <?php 
                            endforeach;
                        endif; 
                        ?>
                    </tbody>
                </table>
            </div>

            <div class="grid-2">
                <!-- All-Time Record Breakers -->
                <div class="analytics-section">
                    <div class="section-header">
                        <h2><i class="fas fa-trophy"></i> All-Time Daily Record</h2>
                        <span class="badge"><?php echo getRecordBreakersCount($filter, 'all-time'); ?></span>
                    </div>
                    <table class="analytics-table">
                        <thead>
                            <tr>
                                <th>Song</th>
                                <th class="right">Today's Streams</th>
                                <th class="right">Previous Record</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $recordBreakers = getAllTimeRecordBreakers($filter);
                            if (empty($recordBreakers)): 
                            ?>
                                <tr><td colspan="3" style="text-align: center; color: var(--text-secondary); padding: 2rem;">No all-time records broken today</td></tr>
                            <?php else: 
                                foreach ($recordBreakers as $track): 
                            ?>
                                <tr>
                                    <td>
                                        <div class="track-name"><?php echo htmlspecialchars($track['track_name']); ?></div>
                                        <div class="artist-name"><?php echo htmlspecialchars($track['artist_name']); ?></div>
                                    </td>
                                    <td class="right">
                                        <span class="number change-positive">
                                            <i class="fas fa-arrow-up"></i> <?php echo number_format($track['today_streams']); ?>
                                        </span>
                                    </td>
                                    <td class="right">
                                        <span class="number"><?php echo number_format($track['previous_record']); ?></span>
                                    </td>
                                </tr>
                            <?php 
                                endforeach;
                            endif; 
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- 2026 Record Breakers -->
                <div class="analytics-section">
                    <div class="section-header">
                        <h2><i class="fas fa-calendar-alt"></i> 2026 Daily Record</h2>
                        <span class="badge"><?php echo getRecordBreakersCount($filter, '2026'); ?></span>
                    </div>
                    <table class="analytics-table">
                        <thead>
                            <tr>
                                <th>Song</th>
                                <th class="right">Today's Streams</th>
                                <th class="right">Previous 2026 Best</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $recordBreakers2026 = get2026RecordBreakers($filter);
                            if (empty($recordBreakers2026)): 
                            ?>
                                <tr><td colspan="3" style="text-align: center; color: var(--text-secondary); padding: 2rem;">No 2026 records broken today</td></tr>
                            <?php else: 
                                foreach ($recordBreakers2026 as $track): 
                            ?>
                                <tr>
                                    <td>
                                        <div class="track-name"><?php echo htmlspecialchars($track['track_name']); ?></div>
                                        <div class="artist-name"><?php echo htmlspecialchars($track['artist_name']); ?></div>
                                    </td>
                                    <td class="right">
                                        <span class="number change-positive">
                                            <i class="fas fa-arrow-up"></i> <?php echo number_format($track['today_streams']); ?>
                                        </span>
                                    </td>
                                    <td class="right">
                                        <span class="number"><?php echo number_format($track['previous_record']); ?></span>
                                    </td>
                                </tr>
                            <?php 
                                endforeach;
                            endif; 
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Biggest Daily Changes -->
            <div class="analytics-section">
                <div class="section-header">
                    <h2><i class="fas fa-chart-line"></i> Top 20 Daily Changes</h2>
                </div>
                <div id="daily-changes-container">
                    <?php include 'get_daily_changes.php'; ?>
                </div>
            </div>
                </table>
            </div>
            </div>
        </main>
    </div>
    
    <script>
    function loadDailyChanges(changeType) {
        const filter = '<?php echo $filter; ?>';
        const container = document.getElementById('daily-changes-container');
        
        fetch(`get_daily_changes.php?filter=${filter}&change_type=${changeType}`)
            .then(response => response.text())
            .then(html => {
                container.innerHTML = html;
            })
            .catch(error => {
                console.error('Error loading daily changes:', error);
            });
    }
    </script>
</body>
</html>
