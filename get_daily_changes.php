<?php
require_once 'config.php';
require_once 'api_helper.php';

$filter = $_GET['filter'] ?? '0Sadg1vgvaPqGTOjxu0N6c';
$changeType = $_GET['change_type'] ?? 'value';

$biggestChanges = getBiggestDailyChanges($filter, $changeType, 20);
?>
<table class="analytics-table">
    <thead>
        <tr>
            <th>#</th>
            <th>Song</th>
            <th class="right">Today's Streams</th>
            <th class="right">Yesterday</th>
            <th class="right clickable <?php echo $changeType == 'value' ? 'active' : ''; ?>" onclick="loadDailyChanges('value')">Change</th>
            <th class="right clickable <?php echo $changeType == 'percent' ? 'active' : ''; ?>" onclick="loadDailyChanges('percent')">%</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        if (empty($biggestChanges)): 
        ?>
            <tr><td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 2rem;">No daily changes data available</td></tr>
        <?php else:
            $rank = 1;
            foreach ($biggestChanges as $track): 
            $changeClass = $track['change'] >= 0 ? 'change-positive' : 'change-negative';
            $changeIcon = $track['change'] >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
        ?>
            <tr>
                <td><strong><?php echo $rank++; ?></strong></td>
                <td>
                    <div class="track-name"><?php echo htmlspecialchars($track['track_name']); ?></div>
                    <div class="artist-name"><?php echo htmlspecialchars($track['artist_name']); ?></div>
                </td>
                <td class="right">
                    <span class="number"><?php echo number_format($track['today_streams']); ?></span>
                </td>
                <td class="right">
                    <span class="number"><?php echo number_format($track['yesterday_streams']); ?></span>
                </td>
                <td class="right">
                    <span class="<?php echo $changeClass; ?>">
                        <i class="fas <?php echo $changeIcon; ?>"></i> 
                        <?php echo $track['change'] >= 0 ? '+' : ''; ?><?php echo number_format($track['change']); ?>
                    </span>
                </td>
                <td class="right">
                    <span class="<?php echo $changeClass; ?>">
                        <?php echo $track['change_percent'] >= 0 ? '+' : ''; ?><?php echo number_format($track['change_percent'], 2); ?>%
                    </span>
                </td>
            </tr>
        <?php endforeach; 
        endif; ?>
    </tbody>
</table>
