<?php
require_once 'config.php';

// Get latest stream date from database
function getLatestStreamDate() {
    try {
        $conn = getDBConnection();
        $result = $conn->query("SELECT MAX(stream_date) as latest FROM streams");
        $data = $result->fetch_assoc();
        $conn->close();
        return $data['latest'] ?? date('Y-m-d');
    } catch (Exception $e) {
        return date('Y-m-d');
    }
}

// Get latest artist stats by artist_id (string)
function getLatestArtistStats($artist_id = '0Sadg1vgvaPqGTOjxu0N6c') {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT * FROM artist_stats WHERE artist_id = ? ORDER BY stat_date DESC LIMIT 1");
        $stmt->bind_param("s", $artist_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        $conn->close();
        return $data ?: [];
    } catch (Exception $e) {
        return [];
    }
}

// Get today's total streams
function getTodayTotalStreams($date = null) {
    try {
        if (!$date) $date = date('Y-m-d');
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT SUM(stream_count) as total FROM streams WHERE stream_date = ?");
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        $conn->close();
        return $data['total'] ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

// Get cumulative total streams
function getTotalStreams() {
    try {
        $conn = getDBConnection();
        $result = $conn->query("SELECT SUM(stream_count) as total FROM streams");
        $data = $result->fetch_assoc();
        $conn->close();
        return $data['total'] ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

// Get Girls' Generation daily streams comparison (latest - yesterday)
function getGGDailyStreamsComparison() {
    try {
        $conn = getDBConnection();
        $ggArtistId = '0Sadg1vgvaPqGTOjxu0N6c';
        
        // Get last 3 dates to calculate differences
        $sql = "SELECT s.stream_date, SUM(s.stream_count) as total
                FROM streams s
                JOIN track_artist ta ON s.track_id = ta.track_id
                WHERE ta.artist_id = ?
                GROUP BY s.stream_date
                ORDER BY s.stream_date DESC
                LIMIT 3";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $ggArtistId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $dates = [];
        while ($row = $result->fetch_assoc()) {
            $dates[] = $row;
        }
        
        $stmt->close();
        $conn->close();
        
        $latest = $dates[0]['total'] ?? 0;
        $yesterday = $dates[1]['total'] ?? 0;
        $dayBefore = $dates[2]['total'] ?? 0;
        
        // If no previous day data, return 0 for daily streams
        $current = ($yesterday > 0) ? ($latest - $yesterday) : 0;
        $previous = ($dayBefore > 0) ? ($yesterday - $dayBefore) : 0;
        
        return [
            'current' => $current,
            'previous' => $previous,
            'latest_total' => $latest,
            'yesterday_total' => $yesterday
        ];
    } catch (Exception $e) {
        return ['current' => 0, 'previous' => 0, 'latest_total' => 0, 'yesterday_total' => 0];
    }
}

// Get artist stats comparison (latest vs yesterday)
function getArtistStatsComparison($artistId = '0Sadg1vgvaPqGTOjxu0N6c') {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT monthly_listeners, followers, stat_date 
                               FROM artist_stats 
                               WHERE artist_id = ? 
                               ORDER BY stat_date DESC 
                               LIMIT 2");
        $stmt->bind_param("s", $artistId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $dates = [];
        while ($row = $result->fetch_assoc()) {
            $dates[] = $row;
        }
        
        $stmt->close();
        $conn->close();
        
        $latest = $dates[0] ?? ['monthly_listeners' => 0, 'followers' => 0];
        $yesterday = $dates[1] ?? ['monthly_listeners' => 0, 'followers' => 0];
        
        return [
            'monthly_listeners' => $latest['monthly_listeners'],
            'monthly_listeners_diff' => $latest['monthly_listeners'] - $yesterday['monthly_listeners'],
            'followers' => $latest['followers'],
            'followers_diff' => $latest['followers'] - $yesterday['followers']
        ];
    } catch (Exception $e) {
        return [
            'monthly_listeners' => 0,
            'monthly_listeners_diff' => 0,
            'followers' => 0,
            'followers_diff' => 0
        ];
    }
}

// Get list of all artists for dropdown
function getArtistList() {
    try {
        $conn = getDBConnection();
        $result = $conn->query("SELECT DISTINCT artist_id, artist_name FROM artist ORDER BY artist_name");
        $artists = [];
        while ($row = $result->fetch_assoc()) {
            $artists[] = $row;
        }
        $conn->close();
        return $artists;
    } catch (Exception $e) {
        return [];
    }
}

// Get artist type (group, unit, solo)
function getArtistType($artistName) {
    $groups = ["Girls' Generation", "소녀시대"];
    $units = ["Girls' Generation-Oh!GG", "Girls' Generation-TTS", "TaeTiSeo"];
    
    if (in_array($artistName, $groups)) return 'group';
    if (in_array($artistName, $units)) return 'unit';
    return 'solo';
}

// Get artist IDs based on filter type
function getArtistIdsByFilter($filter) {
    if ($filter === 'all') {
        // Return empty array - will be handled specially in queries
        return [];
    } else if ($filter === 'groups') {
        // Girls' Generation + subunits (TTS, Oh!GG)
        return ['0Sadg1vgvaPqGTOjxu0N6c', '7AKHnZVqwXYuUwWJ8UGL5q', '1foL9hLC9M6U94dINtOYfb']; 
    } else if ($filter === 'solo') {
        // All solo members (including variations)
        return [
            '3qNVuliS40BLgXGxhdBdqu', // Taeyeon
            '5IphjHq07j65nO3Pl2YOWe', // Sunny
            '1t2HKR34gLWuQyyzLHcSm4', '2lkCfFklQDBPlQzS4tR3VP', // Tiffany (both profiles)
            '0B3I6YgdnfXehUCpsO6oB8', '3U7bOaJLuFkrmDQ1C1OqKl', // Hyoyeon (both profiles)
            '2TMRvcwsmvVhvuEbKVEbZe', // Yuri
            '4k2XSHFx7PuRL7rgE3qncg', '2mTYQHj19falvbVgsh6nkg', // Sooyoung (both profiles)
            '6LCX99hubn8CejiUtMCyyk', // Yoona
            '5uM1Et50auro2hTS6ZLcmT', // Seohyun
            '7jPVuaaHLs4QVSuN561jZt'  // Jessica
        ];
    } else if ($filter === '1t2HKR34gLWuQyyzLHcSm4') {
        // Tiffany - both profiles
        return ['1t2HKR34gLWuQyyzLHcSm4', '2lkCfFklQDBPlQzS4tR3VP'];
    } else if ($filter === '0B3I6YgdnfXehUCpsO6oB8') {
        // Hyoyeon - both profiles
        return ['0B3I6YgdnfXehUCpsO6oB8', '3U7bOaJLuFkrmDQ1C1OqKl'];
    } else if ($filter === '4k2XSHFx7PuRL7rgE3qncg') {
        // Sooyoung - both profiles
        return ['4k2XSHFx7PuRL7rgE3qncg', '2mTYQHj19falvbVgsh6nkg'];
    } else {
        // Single artist_id
        return [$filter];
    }
}

// Get total streams by filter (from LATEST DAY only, since data is cumulative)
function getTotalStreamsByFilter($filter) {
    try {
        $conn = getDBConnection();
        $latestDate = getLatestStreamDate();
        
        if ($filter === 'all') {
            // Get ALL tracks from latest day (no artist filter to avoid overlaps)
            $sql = "SELECT SUM(stream_count) as total FROM streams WHERE stream_date = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $latestDate);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            $stmt->close();
            $conn->close();
            return $data['total'] ?? 0;
        }
        
        $artistIds = getArtistIdsByFilter($filter);
        
        if (empty($artistIds)) return 0;
        
        $placeholders = str_repeat('?,', count($artistIds) - 1) . '?';
        $types = str_repeat('s', count($artistIds)) . 's';
        
        $sql = "
            SELECT SUM(s.stream_count) as total
            FROM streams s
            JOIN track_artist ta ON s.track_id = ta.track_id
            WHERE ta.artist_id IN ($placeholders)
            AND s.stream_date = ?
        ";
        
        $params = array_merge($artistIds, [$latestDate]);
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        $conn->close();
        
        return $data['total'] ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

// Get daily streams comparison by filter
function getDailyStreamsComparisonByFilter($filter) {
    try {
        $conn = getDBConnection();
        
        if ($filter === 'all') {
            // Get ALL tracks (no artist filter)
            $sql = "SELECT stream_date, SUM(stream_count) as total
                    FROM streams
                    GROUP BY stream_date
                    ORDER BY stream_date DESC
                    LIMIT 3";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $dates = [];
            while ($row = $result->fetch_assoc()) {
                $dates[] = $row;
            }
            
            $stmt->close();
            $conn->close();
            
            $latest = $dates[0]['total'] ?? 0;
            $yesterday = $dates[1]['total'] ?? 0;
            $dayBefore = $dates[2]['total'] ?? 0;
            
            $current = ($yesterday > 0) ? ($latest - $yesterday) : 0;
            $previous = ($dayBefore > 0) ? ($yesterday - $dayBefore) : 0;
            
            return [
                'current' => $current,
                'previous' => $previous,
                'latest_total' => $latest,
                'yesterday_total' => $yesterday
            ];
        }
        
        $artistIds = getArtistIdsByFilter($filter);
        
        if (empty($artistIds)) {
            return ['current' => 0, 'previous' => 0, 'latest_total' => 0, 'yesterday_total' => 0];
        }
        
        $placeholders = str_repeat('?,', count($artistIds) - 1) . '?';
        $types = str_repeat('s', count($artistIds));
        
        // Get last 3 dates to calculate differences
        $sql = "SELECT s.stream_date, SUM(s.stream_count) as total
                FROM streams s
                JOIN track_artist ta ON s.track_id = ta.track_id
                WHERE ta.artist_id IN ($placeholders)
                GROUP BY s.stream_date
                ORDER BY s.stream_date DESC
                LIMIT 3";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$artistIds);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $dates = [];
        while ($row = $result->fetch_assoc()) {
            $dates[] = $row;
        }
        
        $stmt->close();
        $conn->close();
        
        $latest = $dates[0]['total'] ?? 0;
        $yesterday = $dates[1]['total'] ?? 0;
        $dayBefore = $dates[2]['total'] ?? 0;
        
        // If no previous day data, return 0 for daily streams
        $current = ($yesterday > 0) ? ($latest - $yesterday) : 0;
        $previous = ($dayBefore > 0) ? ($yesterday - $dayBefore) : 0;
        
        return [
            'current' => $current,
            'previous' => $previous,
            'latest_total' => $latest,
            'yesterday_total' => $yesterday
        ];
    } catch (Exception $e) {
        return ['current' => 0, 'previous' => 0, 'latest_total' => 0, 'yesterday_total' => 0];
    }
}

// Get artist stats comparison by filter (for ML and followers)
function getArtistStatsComparisonByFilter($filter) {
    try {
        // 'all' filter doesn't have ML/Followers stats
        if ($filter === 'all') {
            return [
                'monthly_listeners' => 0,
                'monthly_listeners_diff' => 0,
                'followers' => 0,
                'followers_diff' => 0
            ];
        }
        
        $conn = getDBConnection();
        $artistIds = getArtistIdsByFilter($filter);
        
        if (empty($artistIds)) {
            return [
                'monthly_listeners' => 0,
                'monthly_listeners_diff' => 0,
                'followers' => 0,
                'followers_diff' => 0
            ];
        }
        
        $placeholders = str_repeat('?,', count($artistIds) - 1) . '?';
        $types = str_repeat('s', count($artistIds));
        
        // Check if this is a multi-profile artist (Tiffany, Hyoyeon, Sooyoung)
        $isMultiProfile = count($artistIds) == 2 && (
            (in_array('1t2HKR34gLWuQyyzLHcSm4', $artistIds) && in_array('2lkCfFklQDBPlQzS4tR3VP', $artistIds)) || // Tiffany
            (in_array('0B3I6YgdnfXehUCpsO6oB8', $artistIds) && in_array('3U7bOaJLuFkrmDQ1C1OqKl', $artistIds)) || // Hyoyeon
            (in_array('4k2XSHFx7PuRL7rgE3qncg', $artistIds) && in_array('2mTYQHj19falvbVgsh6nkg', $artistIds))    // Sooyoung
        );
        
        if ($isMultiProfile) {
            // Get individual stats for each profile
            $sql = "
                SELECT 
                    ast.artist_id,
                    ast.monthly_listeners,
                    ast.followers
                FROM artist_stats ast
                INNER JOIN (
                    SELECT artist_id, MAX(stat_date) as max_date
                    FROM artist_stats
                    WHERE artist_id IN ($placeholders)
                    GROUP BY artist_id
                ) latest ON ast.artist_id = latest.artist_id AND ast.stat_date = latest.max_date
                ORDER BY ast.artist_id
            ";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$artistIds);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $profiles = [];
            while ($row = $result->fetch_assoc()) {
                $profiles[$row['artist_id']] = [
                    'ml' => $row['monthly_listeners'],
                    'followers' => $row['followers']
                ];
            }
            $stmt->close();
            
            // Get yesterday's stats
            $sql2 = "
                SELECT 
                    ast.artist_id,
                    ast.monthly_listeners,
                    ast.followers
                FROM artist_stats ast
                INNER JOIN (
                    SELECT artist_id, MAX(stat_date) as max_date
                    FROM artist_stats
                    WHERE artist_id IN ($placeholders)
                    AND stat_date < (
                        SELECT MAX(stat_date) FROM artist_stats WHERE artist_id IN ($placeholders)
                    )
                    GROUP BY artist_id
                ) yesterday ON ast.artist_id = yesterday.artist_id AND ast.stat_date = yesterday.max_date
                ORDER BY ast.artist_id
            ";
            
            $params = array_merge($artistIds, $artistIds);
            $types2 = str_repeat('s', count($params));
            
            $stmt2 = $conn->prepare($sql2);
            $stmt2->bind_param($types2, ...$params);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            
            $profilesYesterday = [];
            while ($row = $result2->fetch_assoc()) {
                $profilesYesterday[$row['artist_id']] = [
                    'ml' => $row['monthly_listeners'],
                    'followers' => $row['followers']
                ];
            }
            $stmt2->close();
            $conn->close();
            
            // Format as arrays for display
            $mlValues = [];
            $mlDiffs = [];
            $followersValues = [];
            $followersDiffs = [];
            
            foreach ($artistIds as $id) {
                $mlValues[] = $profiles[$id]['ml'] ?? 0;
                $mlDiffs[] = ($profiles[$id]['ml'] ?? 0) - ($profilesYesterday[$id]['ml'] ?? 0);
                $followersValues[] = $profiles[$id]['followers'] ?? 0;
                $followersDiffs[] = ($profiles[$id]['followers'] ?? 0) - ($profilesYesterday[$id]['followers'] ?? 0);
            }
            
            return [
                'monthly_listeners' => $mlValues,  // Array of values
                'monthly_listeners_diff' => $mlDiffs,  // Array of diffs
                'followers' => $followersValues,  // Array of values
                'followers_diff' => $followersDiffs,  // Array of diffs
                'is_multi_profile' => true
            ];
        } else {
            // Normal aggregation for groups, solo filter, or single artists
            $sql = "
                SELECT 
                    SUM(ast.monthly_listeners) as total_ml,
                    SUM(ast.followers) as total_followers
                FROM artist_stats ast
                INNER JOIN (
                    SELECT artist_id, MAX(stat_date) as max_date
                    FROM artist_stats
                    WHERE artist_id IN ($placeholders)
                    GROUP BY artist_id
                ) latest ON ast.artist_id = latest.artist_id AND ast.stat_date = latest.max_date
            ";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$artistIds);
            $stmt->execute();
            $result = $stmt->get_result();
            $latestData = $result->fetch_assoc();
            $stmt->close();
            
            // Get yesterday's stats
            $sql2 = "
                SELECT 
                    SUM(ast.monthly_listeners) as total_ml,
                    SUM(ast.followers) as total_followers
                FROM artist_stats ast
                INNER JOIN (
                    SELECT artist_id, MAX(stat_date) as max_date
                    FROM artist_stats
                    WHERE artist_id IN ($placeholders)
                    AND stat_date < (
                        SELECT MAX(stat_date) FROM artist_stats WHERE artist_id IN ($placeholders)
                    )
                    GROUP BY artist_id
                ) yesterday ON ast.artist_id = yesterday.artist_id AND ast.stat_date = yesterday.max_date
            ";
            
            $params = array_merge($artistIds, $artistIds);
            $types2 = str_repeat('s', count($params));
            
            $stmt2 = $conn->prepare($sql2);
            $stmt2->bind_param($types2, ...$params);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            $yesterdayData = $result2->fetch_assoc();
            $stmt2->close();
            
            $conn->close();
            
            $ml_latest = $latestData['total_ml'] ?? 0;
            $ml_yesterday = $yesterdayData['total_ml'] ?? 0;
            $followers_latest = $latestData['total_followers'] ?? 0;
            $followers_yesterday = $yesterdayData['total_followers'] ?? 0;
            
            return [
                'monthly_listeners' => $ml_latest,
                'monthly_listeners_diff' => $ml_latest - $ml_yesterday,
                'followers' => $followers_latest,
                'followers_diff' => $followers_latest - $followers_yesterday,
                'is_multi_profile' => false
            ];
        }
    } catch (Exception $e) {
        return [
            'monthly_listeners' => 0,
            'monthly_listeners_diff' => 0,
            'followers' => 0,
            'followers_diff' => 0
        ];
    }
}

// Get top tracks by DAILY INCREASE (latest - yesterday)
function getTopTracksDailyIncrease($limit = 5, $filter = '0Sadg1vgvaPqGTOjxu0N6c') {
    try {
        $conn = getDBConnection();
        $latestDate = getLatestStreamDate();
        
        // Get yesterday's date
        $stmt = $conn->prepare("SELECT MAX(stream_date) as prev FROM streams WHERE stream_date < ?");
        $stmt->bind_param("s", $latestDate);
        $stmt->execute();
        $result = $stmt->get_result();
        $prevDate = $result->fetch_assoc()['prev'] ?? null;
        $stmt->close();
        
        if (!$prevDate) {
            return [];
        }
        
        if ($filter === 'all') {
            // Get ALL tracks (no artist filter)
            $sql = "
                SELECT t.track_name,
                       COALESCE(s1.stream_count, 0) - COALESCE(s2.stream_count, 0) as daily_increase
                FROM track t
                LEFT JOIN streams s1 ON t.track_id = s1.track_id AND s1.stream_date = ?
                LEFT JOIN streams s2 ON t.track_id = s2.track_id AND s2.stream_date = ?
                GROUP BY t.track_id, t.track_name
                HAVING daily_increase > 0
                ORDER BY daily_increase DESC
                LIMIT ?
            ";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssi', $latestDate, $prevDate, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            $tracks = [];
            while ($row = $result->fetch_assoc()) {
                $tracks[] = ['track_name' => $row['track_name'], 'plays' => $row['daily_increase']];
            }
            $stmt->close();
            $conn->close();
            return $tracks;
        }
        
        $artistIds = getArtistIdsByFilter($filter);
        
        if (empty($artistIds)) return [];
        
        $placeholders = str_repeat('?,', count($artistIds) - 1) . '?';
        $types = str_repeat('s', count($artistIds)) . 'ss';
        
        $sql = "
            SELECT t.track_name,
                   COALESCE(s1.stream_count, 0) - COALESCE(s2.stream_count, 0) as daily_increase
            FROM track t
            JOIN track_artist ta ON t.track_id = ta.track_id
            LEFT JOIN streams s1 ON t.track_id = s1.track_id AND s1.stream_date = ?
            LEFT JOIN streams s2 ON t.track_id = s2.track_id AND s2.stream_date = ?
            WHERE ta.artist_id IN ($placeholders)
            GROUP BY t.track_id, t.track_name
            HAVING daily_increase > 0 
            ORDER BY daily_increase DESC 
            LIMIT ?
        ";
        
        $params = array_merge([$latestDate, $prevDate], $artistIds, [$limit]);
        $types2 = 'ss' . str_repeat('s', count($artistIds)) . 'i';
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types2, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $tracks = [];
        while ($row = $result->fetch_assoc()) {
            $tracks[] = ['track_name' => $row['track_name'], 'plays' => $row['daily_increase']];
        }
        $stmt->close();
        $conn->close();
        return $tracks;
    } catch (Exception $e) {
        return [];
    }
}

// Get top 20 tracks with rankings, daily increase, percentage change, and total streams
function getTop20TracksForCard($filter = '0Sadg1vgvaPqGTOjxu0N6c') {
    try {
        $conn = getDBConnection();
        $latestDate = getLatestStreamDate();
        
        // Get yesterday's date
        $stmt = $conn->prepare("SELECT MAX(stream_date) as prev FROM streams WHERE stream_date < ?");
        $stmt->bind_param("s", $latestDate);
        $stmt->execute();
        $result = $stmt->get_result();
        $prevDate = $result->fetch_assoc()['prev'] ?? null;
        $stmt->close();
        
        if (!$prevDate) {
            return [];
        }
        
        // Get day before yesterday for calculating yesterday's daily
        $stmt = $conn->prepare("SELECT MAX(stream_date) as day_before FROM streams WHERE stream_date < ?");
        $stmt->bind_param("s", $prevDate);
        $stmt->execute();
        $result = $stmt->get_result();
        $dayBeforeDate = $result->fetch_assoc()['day_before'] ?? null;
        $stmt->close();
        
        if ($filter === 'all') {
            // Get ALL tracks (no artist filter)
            $sql = "
                SELECT t.track_name,
                       COALESCE(s1.stream_count, 0) as current_streams,
                       COALESCE(s2.stream_count, 0) as prev_streams,
                       COALESCE(s3.stream_count, 0) as day_before_streams,
                       COALESCE(s1.stream_count, 0) - COALESCE(s2.stream_count, 0) as daily_increase,
                       COALESCE(s2.stream_count, 0) - COALESCE(s3.stream_count, 0) as prev_daily_increase
                FROM track t
                LEFT JOIN streams s1 ON t.track_id = s1.track_id AND s1.stream_date = ?
                LEFT JOIN streams s2 ON t.track_id = s2.track_id AND s2.stream_date = ?
                LEFT JOIN streams s3 ON t.track_id = s3.track_id AND s3.stream_date = ?
                GROUP BY t.track_id, t.track_name
                HAVING daily_increase > 0
                ORDER BY daily_increase DESC
                LIMIT 20
            ";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sss', $latestDate, $prevDate, $dayBeforeDate);
        } else {
            $artistIds = getArtistIdsByFilter($filter);
            
            if (empty($artistIds)) return [];
            
            $placeholders = str_repeat('?,', count($artistIds) - 1) . '?';
            
            $sql = "
                SELECT t.track_name,
                       COALESCE(s1.stream_count, 0) as current_streams,
                       COALESCE(s2.stream_count, 0) as prev_streams,
                       COALESCE(s3.stream_count, 0) as day_before_streams,
                       COALESCE(s1.stream_count, 0) - COALESCE(s2.stream_count, 0) as daily_increase,
                       COALESCE(s2.stream_count, 0) - COALESCE(s3.stream_count, 0) as prev_daily_increase
                FROM track t
                JOIN track_artist ta ON t.track_id = ta.track_id
                LEFT JOIN streams s1 ON t.track_id = s1.track_id AND s1.stream_date = ?
                LEFT JOIN streams s2 ON t.track_id = s2.track_id AND s2.stream_date = ?
                LEFT JOIN streams s3 ON t.track_id = s3.track_id AND s3.stream_date = ?
                WHERE ta.artist_id IN ($placeholders)
                GROUP BY t.track_id, t.track_name
                HAVING daily_increase > 0 
                ORDER BY daily_increase DESC 
                LIMIT 20
            ";
            
            $params = array_merge([$latestDate, $prevDate, $dayBeforeDate], $artistIds);
            $types = 'sss' . str_repeat('s', count($artistIds));
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $tracks = [];
        $rank = 1;
        
        // Get yesterday's rankings first
        $yesterdayRanks = [];
        if ($filter === 'all') {
            $sqlPrev = "
                SELECT t.track_name,
                       COALESCE(s2.stream_count, 0) - COALESCE(s3.stream_count, 0) as prev_daily_increase
                FROM track t
                LEFT JOIN streams s2 ON t.track_id = s2.track_id AND s2.stream_date = ?
                LEFT JOIN streams s3 ON t.track_id = s3.track_id AND s3.stream_date = (SELECT MAX(stream_date) FROM streams WHERE stream_date < ?)
                GROUP BY t.track_id, t.track_name
                HAVING prev_daily_increase > 0
                ORDER BY prev_daily_increase DESC
                LIMIT 20
            ";
            $stmtPrev = $conn->prepare($sqlPrev);
            $stmtPrev->bind_param('ss', $prevDate, $prevDate);
        } else {
            $artistIds = getArtistIdsByFilter($filter);
            if (!empty($artistIds)) {
                $placeholders = str_repeat('?,', count($artistIds) - 1) . '?';
                $sqlPrev = "
                    SELECT t.track_name,
                           COALESCE(s2.stream_count, 0) - COALESCE(s3.stream_count, 0) as prev_daily_increase
                    FROM track t
                    JOIN track_artist ta ON t.track_id = ta.track_id
                    LEFT JOIN streams s2 ON t.track_id = s2.track_id AND s2.stream_date = ?
                    LEFT JOIN streams s3 ON t.track_id = s3.track_id AND s3.stream_date = (SELECT MAX(stream_date) FROM streams WHERE stream_date < ?)
                    WHERE ta.artist_id IN ($placeholders)
                    GROUP BY t.track_id, t.track_name
                    HAVING prev_daily_increase > 0
                    ORDER BY prev_daily_increase DESC
                    LIMIT 20
                ";
                $paramsPrev = array_merge([$prevDate, $prevDate], $artistIds);
                $typesPrev = 'ss' . str_repeat('s', count($artistIds));
                $stmtPrev = $conn->prepare($sqlPrev);
                $stmtPrev->bind_param($typesPrev, ...$paramsPrev);
            }
        }
        
        if (isset($stmtPrev)) {
            $stmtPrev->execute();
            $resultPrev = $stmtPrev->get_result();
            $prevRank = 1;
            while ($rowPrev = $resultPrev->fetch_assoc()) {
                $yesterdayRanks[$rowPrev['track_name']] = $prevRank;
                $prevRank++;
            }
            $stmtPrev->close();
        }
        
        while ($row = $result->fetch_assoc()) {
            $dailyIncrease = $row['daily_increase'];
            $prevDailyIncrease = $row['prev_daily_increase'] ?? 0;
            $currentStreams = $row['current_streams'];
            $prevStreams = $row['prev_streams'];
            
            // Calculate percentage change: (today's daily - yesterday's daily) / yesterday's daily * 100
            $percentChange = 0;
            if ($prevDailyIncrease > 0) {
                $percentChange = (($dailyIncrease - $prevDailyIncrease) / $prevDailyIncrease) * 100;
            }
            
            // Calculate rank change
            $rankChange = '=';
            $trackName = $row['track_name'];
            if (isset($yesterdayRanks[$trackName])) {
                $prevRankNum = $yesterdayRanks[$trackName];
                $rankDiff = $prevRankNum - $rank;
                if ($rankDiff > 0) {
                    $rankChange = '+' . $rankDiff;
                } elseif ($rankDiff < 0) {
                    $rankChange = $rankDiff;
                }
            } else {
                $rankChange = 'NEW';
            }
            
            $tracks[] = [
                'rank' => $rank,
                'track_name' => $row['track_name'],
                'daily_streams' => $dailyIncrease,
                'percent_change' => $percentChange,
                'total_streams' => $currentStreams,
                'rank_change' => $rankChange
            ];
            $rank++;
        }
        
        $stmt->close();
        $conn->close();
        return $tracks;
    } catch (Exception $e) {
        return [];
    }
}

// Get top tracks by ALL TIME (using LATEST DAY only, since data is cumulative)
function getTopTracksAllTime($limit = 5, $filter = '0Sadg1vgvaPqGTOjxu0N6c') {
    try {
        $conn = getDBConnection();
        $latestDate = getLatestStreamDate();
        
        if ($filter === 'all') {
            // Get ALL tracks (no artist filter)
            $sql = "
                SELECT t.track_name, s.stream_count as total_streams
                FROM track t
                JOIN streams s ON t.track_id = s.track_id
                WHERE s.stream_date = ?
                ORDER BY total_streams DESC
                LIMIT ?
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('si', $latestDate, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            $tracks = [];
            while ($row = $result->fetch_assoc()) {
                $tracks[] = ['track_name' => $row['track_name'], 'plays' => $row['total_streams']];
            }
            $stmt->close();
            $conn->close();
            return $tracks;
        }
        
        $artistIds = getArtistIdsByFilter($filter);
        
        if (empty($artistIds)) return [];
        
        $placeholders = str_repeat('?,', count($artistIds) - 1) . '?';
        $types = str_repeat('s', count($artistIds)) . 'si';
        
        $sql = "
            SELECT t.track_name, s.stream_count as total_streams
            FROM track t
            JOIN track_artist ta ON t.track_id = ta.track_id
            JOIN streams s ON t.track_id = s.track_id
            WHERE ta.artist_id IN ($placeholders)
            AND s.stream_date = ?
            ORDER BY total_streams DESC
            LIMIT ?
        ";
        
        $params = array_merge($artistIds, [$latestDate, $limit]);
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $tracks = [];
        while ($row = $result->fetch_assoc()) {
            $tracks[] = ['track_name' => $row['track_name'], 'plays' => $row['total_streams']];
        }
        $stmt->close();
        $conn->close();
        return $tracks;
    } catch (Exception $e) {
        return [];
    }
}

// Get top tracks with filter (all, group, unit, solo, or specific artist)
function getTopTracksFiltered($date, $limit = 5, $filter = 'all') {
    try {
        $conn = getDBConnection();
        
        $sql = "
            SELECT t.track_name, s.stream_count as plays, a.artist_name
            FROM streams s
            JOIN track t ON s.track_id = t.track_id
            JOIN track_artist ta ON t.track_id = ta.track_id
            JOIN artist a ON ta.artist_id = a.artist_id
            WHERE s.stream_date = ?
        ";
        
        // Add filter conditions
        if ($filter == 'group') {
            $sql .= " AND a.artist_name IN (\"Girls' Generation\", '소녀시대')";
        } elseif ($filter == 'unit') {
            $sql .= " AND a.artist_name IN (\"Girls' Generation-Oh!GG\", \"Girls' Generation-TTS\", 'TaeTiSeo')";
        } elseif ($filter == 'solo') {
            $sql .= " AND a.artist_name NOT IN (\"Girls' Generation\", '소녀시대', \"Girls' Generation-Oh!GG\", \"Girls' Generation-TTS\", 'TaeTiSeo')";
        } elseif ($filter != 'all') {
            $sql .= " AND a.artist_name = '" . $conn->real_escape_string($filter) . "'";
        }
        
        $sql .= " GROUP BY t.track_id ORDER BY s.stream_count DESC LIMIT ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $date, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $tracks = [];
        while ($row = $result->fetch_assoc()) {
            $tracks[] = $row;
        }
        $stmt->close();
        $conn->close();
        return $tracks;
    } catch (Exception $e) {
        return [];
    }
}

// Get top artists by DAILY INCREASE (latest - yesterday)
function getTopArtistsDailyIncrease($limit = 5, $filter = '0Sadg1vgvaPqGTOjxu0N6c') {
    try {
        $conn = getDBConnection();
        $latestDate = getLatestStreamDate();
        
        // Get yesterday's date
        $stmt = $conn->prepare("SELECT MAX(stream_date) as prev FROM streams WHERE stream_date < ?");
        $stmt->bind_param("s", $latestDate);
        $stmt->execute();
        $result = $stmt->get_result();
        $prevDate = $result->fetch_assoc()['prev'] ?? null;
        $stmt->close();
        
        if (!$prevDate) {
            return [];
        }
        
        if ($filter === 'all') {
            // Get ALL artists (no filter)
            $sql = "
                SELECT a.artist_name,
                       COALESCE(SUM(s1.stream_count), 0) - COALESCE(SUM(s2.stream_count), 0) as daily_increase
                FROM artist a
                JOIN track_artist ta ON a.artist_id = ta.artist_id
                LEFT JOIN streams s1 ON ta.track_id = s1.track_id AND s1.stream_date = ?
                LEFT JOIN streams s2 ON ta.track_id = s2.track_id AND s2.stream_date = ?
                GROUP BY a.artist_id, a.artist_name
                HAVING daily_increase > 0
                ORDER BY daily_increase DESC
                LIMIT ?
            ";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssi', $latestDate, $prevDate, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            $artists = [];
            while ($row = $result->fetch_assoc()) {
                $artists[] = ['artist_name' => $row['artist_name'], 'total_streams' => $row['daily_increase']];
            }
            $stmt->close();
            $conn->close();
            return $artists;
        }
        
        $artistIds = getArtistIdsByFilter($filter);
        
        if (empty($artistIds)) return [];
        
        $placeholders = str_repeat('?,', count($artistIds) - 1) . '?';
        
        $sql = "
            SELECT a.artist_name,
                   COALESCE(SUM(s1.stream_count), 0) - COALESCE(SUM(s2.stream_count), 0) as daily_increase
            FROM artist a
            JOIN track_artist ta ON a.artist_id = ta.artist_id
            LEFT JOIN streams s1 ON ta.track_id = s1.track_id AND s1.stream_date = ?
            LEFT JOIN streams s2 ON ta.track_id = s2.track_id AND s2.stream_date = ?
            WHERE a.artist_id IN ($placeholders)
            GROUP BY a.artist_id, a.artist_name
            HAVING daily_increase > 0
            ORDER BY daily_increase DESC
            LIMIT ?
        ";
        
        $params = array_merge([$latestDate, $prevDate], $artistIds, [$limit]);
        $types = 'ss' . str_repeat('s', count($artistIds)) . 'i';
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $artists = [];
        while ($row = $result->fetch_assoc()) {
            $artists[] = ['artist_name' => $row['artist_name'], 'total_streams' => $row['daily_increase']];
        }
        $stmt->close();
        $conn->close();
        return $artists;
    } catch (Exception $e) {
        return [];
    }
}

// Get top artists ALL TIME (using LATEST DAY only, since data is cumulative)
function getTopArtistsAllTime($limit = 5, $filter = '0Sadg1vgvaPqGTOjxu0N6c') {
    try {
        $conn = getDBConnection();        $latestDate = getLatestStreamDate();
        
        if ($filter === 'all') {
            // Get ALL artists (no filter)
            $sql = "
                SELECT a.artist_name, SUM(s.stream_count) as total_streams
                FROM artist a
                JOIN track_artist ta ON a.artist_id = ta.artist_id
                JOIN streams s ON ta.track_id = s.track_id
                WHERE s.stream_date = ?
                GROUP BY a.artist_id, a.artist_name
                ORDER BY total_streams DESC
                LIMIT ?
            ";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('si', $latestDate, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            $artists = [];
            while ($row = $result->fetch_assoc()) {
                $artists[] = ['artist_name' => $row['artist_name'], 'total_streams' => $row['total_streams']];
            }
            $stmt->close();
            $conn->close();
            return $artists;
        }
                $artistIds = getArtistIdsByFilter($filter);
        
        if (empty($artistIds)) return [];
        
        $placeholders = str_repeat('?,', count($artistIds) - 1) . '?';
        
        $sql = "
            SELECT a.artist_name, SUM(s.stream_count) as total_streams
            FROM artist a
            JOIN track_artist ta ON a.artist_id = ta.artist_id
            JOIN streams s ON ta.track_id = s.track_id
            WHERE a.artist_id IN ($placeholders)
            AND s.stream_date = ?
            GROUP BY a.artist_id, a.artist_name
            ORDER BY total_streams DESC
            LIMIT ?
        ";
        
        $params = array_merge($artistIds, [$latestDate, $limit]);
        $types = str_repeat('s', count($artistIds)) . 'si';
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $artists = [];
        while ($row = $result->fetch_assoc()) {
            $artists[] = ['artist_name' => $row['artist_name'], 'total_streams' => $row['total_streams']];
        }
        $stmt->close();
        $conn->close();
        return $artists;
    } catch (Exception $e) {
        return [];
    }
}

// Get top artists with filter
function getTopArtistsFiltered($date, $limit = 5, $filter = 'all') {
    try {
        $conn = getDBConnection();
        
        $sql = "
            SELECT a.artist_name, SUM(s.stream_count) as total_streams
            FROM streams s
            JOIN track_artist ta ON s.track_id = ta.track_id
            JOIN artist a ON ta.artist_id = a.artist_id
            WHERE s.stream_date = ?
        ";
        
        // Add filter conditions
        if ($filter == 'group') {
            $sql .= " AND a.artist_name IN (\"Girls' Generation\", '소녀시대')";
        } elseif ($filter == 'unit') {
            $sql .= " AND a.artist_name IN (\"Girls' Generation-Oh!GG\", \"Girls' Generation-TTS\", 'TaeTiSeo')";
        } elseif ($filter == 'solo') {
            $sql .= " AND a.artist_name NOT IN (\"Girls' Generation\", '소녀시대', \"Girls' Generation-Oh!GG\", \"Girls' Generation-TTS\", 'TaeTiSeo')";
        }
        
        $sql .= " GROUP BY a.artist_id ORDER BY total_streams DESC LIMIT ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $date, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $artists = [];
        while ($row = $result->fetch_assoc()) {
            $artists[] = $row;
        }
        $stmt->close();
        $conn->close();
        return $artists;
    } catch (Exception $e) {
        return [];
    }
}

// Get top tracks by streams for a specific date
function getTopTracks($date = null, $limit = 5) {
    try {
        if (!$date) $date = date('Y-m-d');
        $conn = getDBConnection();
        $stmt = $conn->prepare("
            SELECT t.track_name, GROUP_CONCAT(DISTINCT a.artist_name SEPARATOR ', ') as artists, 
                   s.stream_count as plays
            FROM streams s
            JOIN track t ON s.track_id = t.track_id
            JOIN track_artist ta ON t.track_id = ta.track_id
            JOIN artist a ON ta.artist_id = a.artist_id
            WHERE s.stream_date = ?
            GROUP BY t.track_id, t.track_name, s.stream_count
            ORDER BY s.stream_count DESC
            LIMIT ?
        ");
        $stmt->bind_param("si", $date, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $tracks = [];
        while ($row = $result->fetch_assoc()) {
            $tracks[] = $row;
        }
        $stmt->close();
        $conn->close();
        return $tracks;
    } catch (Exception $e) {
        return [];
    }
}

// Get all artists with track counts
function getArtists() {
    try {
        $conn = getDBConnection();
        $result = $conn->query("
            SELECT a.artist_id, a.artist_name, COUNT(DISTINCT ta.track_id) as track_count
            FROM artist a
            LEFT JOIN track_artist ta ON a.artist_id = ta.artist_id
            GROUP BY a.artist_id, a.artist_name
            ORDER BY a.artist_name
        ");
        $artists = [];
        while ($row = $result->fetch_assoc()) {
            $artists[] = $row;
        }
        $conn->close();
        return $artists;
    } catch (Exception $e) {
        return [];
    }
}

// Get daily streams for chart - showing daily INCREASES (difference from previous day)
// First data point shows total streams (not 0)
// Excludes last data point (-1)
function getDailyStreamsChart($days = 7, $filter = '0Sadg1vgvaPqGTOjxu0N6c') {
    try {
        $conn = getDBConnection();

        // Use latest available date as reference (handles historic datasets)
        $latestDate = getLatestStreamDate();
        if (!$latestDate) return ['labels' => [], 'data' => []];

        // Calculate start date (inclusive)
        $startDate = date('Y-m-d', strtotime($latestDate . " - " . ($days - 1) . " days"));

        if ($filter === 'all') {
            // Get ALL tracks (no artist filter)
            $sql = "
                SELECT stream_date, SUM(stream_count) as total
                FROM streams
                WHERE stream_date BETWEEN ? AND ?
                GROUP BY stream_date
                ORDER BY stream_date ASC
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ss', $startDate, $latestDate);
            $stmt->execute();
        } else {
            // Build query based on filter type
            $artistIds = getArtistIdsByFilter($filter);
            if (empty($artistIds)) {
                return ['labels' => [], 'data' => []];
            }

            $placeholders = str_repeat('?,', count($artistIds) - 1) . '?';

            $sql = "
                SELECT s.stream_date, SUM(s.stream_count) as total
                FROM streams s
                JOIN track_artist ta ON s.track_id = ta.track_id
                WHERE ta.artist_id IN ($placeholders)
                AND s.stream_date BETWEEN ? AND ?
                GROUP BY s.stream_date
                ORDER BY s.stream_date ASC
            ";

            $params = array_merge($artistIds, [$startDate, $latestDate]);
            $types = str_repeat('s', count($artistIds)) . 'ss';

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
        }

        $result = $stmt->get_result();

        // Build associative map of date => total
        $totalsByDate = [];
        while ($row = $result->fetch_assoc()) {
            $totalsByDate[$row['stream_date']] = intval($row['total']);
        }

        // Fill full date range from startDate to latestDate
        $labels = [];
        $rawTotals = [];
        $current = strtotime($startDate);
        $end = strtotime($latestDate);
        while ($current <= $end) {
            $d = date('Y-m-d', $current);
            $labels[] = date('j/n', strtotime($d));
            $rawTotals[] = $totalsByDate[$d] ?? 0;
            $current = strtotime('+1 day', $current);
        }

        // Compute daily increases: for i>0: rawTotals[i] - rawTotals[i-1]
        $data = [];
        for ($i = 0; $i < count($rawTotals); $i++) {
            if ($i == 0) {
                // first point: cumulative up to that day
                // compute cumulative total up to that date for the filter
                if ($filter === 'all') {
                    $stmt2 = $conn->prepare("SELECT SUM(stream_count) as cumulative FROM streams WHERE stream_date <= ?");
                    $stmt2->bind_param('s', $startDate);
                } else {
                    // For artist-specific filters, sum joined by track_artist
                    $placeholders = str_repeat('?,', count($artistIds) - 1) . '?';
                    $sql_cum = "SELECT SUM(s.stream_count) as cumulative FROM streams s JOIN track_artist ta ON s.track_id = ta.track_id WHERE ta.artist_id IN ($placeholders) AND s.stream_date <= ?";
                    $paramsCum = array_merge($artistIds, [$startDate]);
                    $typesCum = str_repeat('s', count($artistIds)) . 's';
                    $stmt2 = $conn->prepare($sql_cum);
                    $stmt2->bind_param($typesCum, ...$paramsCum);
                }
                // Note: using startDate as boundary for cumulative
                $stmt2->execute();
                $res2 = $stmt2->get_result();
                $cumData = $res2->fetch_assoc();
                $data[] = intval($cumData['cumulative'] ?? 0);
                $stmt2->close();
            } else {
                $data[] = $rawTotals[$i] - $rawTotals[$i - 1];
            }
        }

        // Remove oldest point (first) since it is cumulative not a daily increase
        if (count($data) > 0) {
            array_shift($data);
            array_shift($labels);
        }

        // Exclude non-positive points (<=0) and outliers (>100,000,000).
        // If a point is negative (<0), also exclude the following day.
        $n = count($data);
        $include = array_fill(0, $n, true);
        for ($i = 0; $i < $n; $i++) {
            if ($data[$i] <= 0 || $data[$i] > 100000000) {
                $include[$i] = false;
            }
            if ($data[$i] < 0 && ($i + 1) < $n) {
                $include[$i + 1] = false;
            }
        }

        $filteredLabels = [];
        $filteredData = [];
        for ($i = 0; $i < $n; $i++) {
            if ($include[$i]) {
                $filteredLabels[] = $labels[$i];
                $filteredData[] = $data[$i];
            }
        }

        $stmt->close();
        $conn->close();

        return ['labels' => $filteredLabels, 'data' => $filteredData];
    } catch (Exception $e) {
        return ['labels' => [], 'data' => []];
    }
}

// Get daily streams chart for an explicit date range (inclusive)
function getDailyStreamsChartRange($startDate, $endDate, $filter = '0Sadg1vgvaPqGTOjxu0N6c') {
    try {
        $conn = getDBConnection();
        if (!$startDate || !$endDate) return ['labels' => [], 'data' => []];

        // to compute the daily increase for the user's chosen start date,
        // include the previous day in the query window (startDate - 1 day)
        $prevDate = date('Y-m-d', strtotime($startDate . ' -1 day'));

        if ($filter === 'all') {
            $sql = "
                SELECT stream_date, SUM(stream_count) as total
                FROM streams
                WHERE stream_date BETWEEN ? AND ?
                GROUP BY stream_date
                ORDER BY stream_date ASC
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ss', $prevDate, $endDate);
            $stmt->execute();
        } else {
            $artistIds = getArtistIdsByFilter($filter);
            if (empty($artistIds)) {
                return ['labels' => [], 'data' => []];
            }

            $placeholders = str_repeat('?,', count($artistIds) - 1) . '?';
            $sql = "
                SELECT s.stream_date, SUM(s.stream_count) as total
                FROM streams s
                JOIN track_artist ta ON s.track_id = ta.track_id
                WHERE ta.artist_id IN ($placeholders)
                AND s.stream_date BETWEEN ? AND ?
                GROUP BY s.stream_date
                ORDER BY s.stream_date ASC
            ";

            $params = array_merge($artistIds, [$prevDate, $endDate]);
            $types = str_repeat('s', count($artistIds)) . 'ss';

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
        }

        $result = $stmt->get_result();

        $totalsByDate = [];
        while ($row = $result->fetch_assoc()) {
            $totalsByDate[$row['stream_date']] = intval($row['total']);
        }

        // Build rawTotals starting from prevDate up to endDate
        $dates = [];
        $rawTotals = [];
        $current = strtotime($prevDate);
        $end = strtotime($endDate);
        while ($current <= $end) {
            $d = date('Y-m-d', $current);
            $dates[] = $d;
            $rawTotals[] = $totalsByDate[$d] ?? 0;
            $current = strtotime('+1 day', $current);
        }

        // Now compute daily increases for dates from startDate..endDate
        $labels = [];
        $data = [];
        for ($i = 1; $i < count($rawTotals); $i++) {
            $labelDate = $dates[$i];
            // only include labels within user-chosen window (i.e., dates >= startDate)
            if ($labelDate < $startDate) continue;
            $daily = $rawTotals[$i] - $rawTotals[$i - 1];
            $labels[] = date('j/n', strtotime($labelDate));
            $data[] = intval($daily);
        }

        // Exclude non-positive points (<=0) and outliers (>100,000,000).
        // If a point is negative (<0), also exclude the following day.
        $n = count($data);
        $include = array_fill(0, $n, true);
        for ($i = 0; $i < $n; $i++) {
            if ($data[$i] <= 0 || $data[$i] > 100000000) {
                $include[$i] = false;
            }
            if ($data[$i] < 0 && ($i + 1) < $n) {
                $include[$i + 1] = false;
            }
        }

        $filteredLabels = [];
        $filteredData = [];
        for ($i = 0; $i < $n; $i++) {
            if ($include[$i]) {
                $filteredLabels[] = $labels[$i];
                $filteredData[] = $data[$i];
            }
        }

        $stmt->close();
        $conn->close();

        return ['labels' => $filteredLabels, 'data' => $filteredData];
    } catch (Exception $e) {
        return ['labels' => [], 'data' => []];
    }
}

// Get artist stream stats by artist IDs (handles variations)
function getArtistStreamStatsByIds($artistIds) {
    try {
        $conn = getDBConnection();
        
        // Build placeholders for IN clause
        $placeholders = str_repeat('?,', count($artistIds) - 1) . '?';
        $types = str_repeat('s', count($artistIds));
        
        // Get latest stream date
        $latestDate = getLatestStreamDate();
        
        // Get total streams for latest date only
        $sql = "
            SELECT COALESCE(SUM(s.stream_count), 0) as total_streams
            FROM streams s
            JOIN track_artist ta ON s.track_id = ta.track_id
            WHERE ta.artist_id IN ($placeholders)
            AND s.stream_date = ?
        ";
        
        $params = array_merge($artistIds, [$latestDate]);
        $paramTypes = $types . 's';
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($paramTypes, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $totalStreams = $data['total_streams'] ?? 0;
        $stmt->close();
        
        // Get daily streams (difference between latest and previous day)
        $dailyStreams = 0;
        
        $sql = "
            SELECT s.stream_date, COALESCE(SUM(s.stream_count), 0) as day_streams
            FROM streams s
            JOIN track_artist ta ON s.track_id = ta.track_id
            WHERE ta.artist_id IN ($placeholders)
            GROUP BY s.stream_date
            ORDER BY s.stream_date DESC
            LIMIT 2
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$artistIds);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $days = [];
        while ($row = $result->fetch_assoc()) {
            $days[] = $row['day_streams'];
        }
        
        if (count($days) >= 2) {
            $dailyStreams = $days[0] - $days[1];
        } elseif (count($days) == 1) {
            $dailyStreams = $days[0]; // Only one day of data
        }
        
        $stmt->close();
        $conn->close();
        
        return [
            'daily_streams' => $dailyStreams,
            'total_streams' => $totalStreams
        ];
    } catch (Exception $e) {
        return [
            'daily_streams' => 0,
            'total_streams' => 0
        ];
    }
}

// Get top tracks for an artist by artist IDs
function getArtistTopTracksByIds($artistIds, $limit = 10) {
    try {
        $conn = getDBConnection();
        
        // Build placeholders for IN clause
        $placeholders = str_repeat('?,', count($artistIds) - 1) . '?';
        $types = str_repeat('s', count($artistIds)) . 'i';
        
        $latestDate = getLatestStreamDate();
        
        $sql = "
            SELECT t.track_name, COALESCE(SUM(s.stream_count), 0) as total_streams
            FROM track t
            JOIN track_artist ta ON t.track_id = ta.track_id
            LEFT JOIN streams s ON t.track_id = s.track_id AND s.stream_date = ?
            WHERE ta.artist_id IN ($placeholders)
            GROUP BY t.track_id, t.track_name
            ORDER BY total_streams DESC
            LIMIT ?
        ";
        
        $params = array_merge([$latestDate], $artistIds, [$limit]);
        $paramTypes = 's' . str_repeat('s', count($artistIds)) . 'i';
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($paramTypes, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    
        $tracks = [];
        while ($row = $result->fetch_assoc()) {
            $tracks[] = $row;
        }
        
        $stmt->close();
        $conn->close();
        
        return $tracks;
    } catch (Exception $e) {
        return [];
    }
}

// Get all tracks from database for scraping
function getAllTracks() {
    try {
        $conn = getDBConnection();
        
        $sql = "
            SELECT t.track_id, t.track_name, GROUP_CONCAT(DISTINCT a.artist_name SEPARATOR ', ') as artists
            FROM track t
            LEFT JOIN track_artist ta ON t.track_id = ta.track_id
            LEFT JOIN artist a ON ta.artist_id = a.artist_id
            GROUP BY t.track_id, t.track_name
            ORDER BY t.track_name
        ";
        
        $result = $conn->query($sql);
        $tracks = [];
        while ($row = $result->fetch_assoc()) {
            $tracks[] = $row;
        }
        
        $conn->close();
        return $tracks;
    } catch (Exception $e) {
        return [];
    }
}

// Save stream data to database
function saveStreamData($trackId, $date, $streamCount) {
    try {
        $conn = getDBConnection();
        
        $stmt = $conn->prepare("
            INSERT INTO streams (track_id, stream_date, stream_count) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE stream_count = VALUES(stream_count)
        ");
        
        $stmt->bind_param("ssi", $trackId, $date, $streamCount);
        $success = $stmt->execute();
        
        $stmt->close();
        $conn->close();
        
        return $success;
    } catch (Exception $e) {
        return false;
    }
}

// ========================================
// ANALYTICS FUNCTIONS
// ========================================
// ANALYTICS FUNCTIONS
// ========================================

// Helper function to build filter conditions
function getAnalyticsFilterCondition($filter, &$types, &$params) {
    $condition = '';
    
    if ($filter === 'groups') {
        // Groups = GG + Subunits
        $condition = " AND ar.artist_id IN ('0Sadg1vgvaPqGTOjxu0N6c', '7AKHnZVqwXYuUwWJ8UGL5q', '1foL9hLC9M6U94dINtOYfb')";
    } elseif ($filter === 'solo') {
        // Solo = All members (excluding group profiles)
        $condition = " AND ar.artist_id NOT IN ('0Sadg1vgvaPqGTOjxu0N6c', '7AKHnZVqwXYuUwWJ8UGL5q', '1foL9hLC9M6U94dINtOYfb')";
    } elseif ($filter !== 'all') {
        // Specific artist
        $condition = " AND ar.artist_id = ?";
        $types .= 's';
        $params[] = $filter;
    }
    
    return $condition;
}

// Get tracks with all-time daily record (today's streams are highest ever)
function getAllTimeRecordBreakers($filter = 'all') {
    try {
        $conn = getDBConnection();
        $latestDate = getLatestStreamDate();
        
        // Get previous date
        $prevStmt = $conn->prepare("SELECT MAX(stream_date) as prev FROM streams WHERE stream_date < ?");
        $prevStmt->bind_param('s', $latestDate);
        $prevStmt->execute();
        $prevDate = $prevStmt->get_result()->fetch_assoc()['prev'] ?? null;
        $prevStmt->close();
        
        if (!$prevDate) {
            $conn->close();
            return [];
        }
        
        $sql = "SELECT 
                    t.track_id,
                    t.track_name,
                    ar.artist_name,
                    COALESCE(s_today.stream_count, 0) as today_total,
                    COALESCE(s_yesterday.stream_count, 0) as yesterday_total,
                    (SELECT MAX(s2.stream_count - s3.stream_count)
                     FROM streams s2
                     INNER JOIN streams s3 
                         ON s2.track_id = s3.track_id 
                         AND s3.stream_date = DATE_SUB(s2.stream_date, INTERVAL 1 DAY)
                     WHERE s2.track_id = t.track_id 
                     AND s2.stream_date < ?) as previous_record
                FROM track t
                JOIN track_artist ta ON t.track_id = ta.track_id
                JOIN artist ar ON ta.artist_id = ar.artist_id
                LEFT JOIN streams s_today ON t.track_id = s_today.track_id AND s_today.stream_date = ?
                LEFT JOIN streams s_yesterday ON t.track_id = s_yesterday.track_id AND s_yesterday.stream_date = ?
                WHERE 1=1";
        
        $params = [$latestDate, $latestDate, $prevDate];
        $types = 'sss';
        
        if ($filter != 'all') {
            $sql .= getAnalyticsFilterCondition($filter, $types, $params);
        }
        
        $sql .= " HAVING yesterday_total > 0 AND today_total > yesterday_total 
                  AND previous_record IS NOT NULL 
                  AND (today_total - yesterday_total) > previous_record
                  ORDER BY (today_total - yesterday_total) DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'track_id' => $row['track_id'],
                'track_name' => $row['track_name'],
                'artist_name' => $row['artist_name'],
                'today_streams' => $row['today_total'] - $row['yesterday_total'],
                'previous_record' => $row['previous_record']
            ];
        }
        
        $stmt->close();
        $conn->close();
        
        return $data;
    } catch (Exception $e) {
        error_log("getAllTimeRecordBreakers error: " . $e->getMessage());
        return [];
    }
}

// Get tracks with 2026 daily record
function get2026RecordBreakers($filter = 'all') {
    try {
        $conn = getDBConnection();
        $latestDate = getLatestStreamDate();
        
        // Get previous date
        $prevStmt = $conn->prepare("SELECT MAX(stream_date) as prev FROM streams WHERE stream_date < ?");
        $prevStmt->bind_param('s', $latestDate);
        $prevStmt->execute();
        $prevDate = $prevStmt->get_result()->fetch_assoc()['prev'] ?? null;
        $prevStmt->close();
        
        if (!$prevDate) {
            $conn->close();
            return [];
        }
        
        $sql = "SELECT 
                    t.track_id,
                    t.track_name,
                    ar.artist_name,
                    COALESCE(s_today.stream_count, 0) as today_total,
                    COALESCE(s_yesterday.stream_count, 0) as yesterday_total,
                    (SELECT MAX(s2.stream_count - s3.stream_count)
                     FROM streams s2
                     INNER JOIN streams s3 
                         ON s2.track_id = s3.track_id 
                         AND s3.stream_date = DATE_SUB(s2.stream_date, INTERVAL 1 DAY)
                     WHERE s2.track_id = t.track_id 
                     AND s2.stream_date >= '2026-01-01'
                     AND s2.stream_date < ?) as previous_record
                FROM track t
                JOIN track_artist ta ON t.track_id = ta.track_id
                JOIN artist ar ON ta.artist_id = ar.artist_id
                LEFT JOIN streams s_today ON t.track_id = s_today.track_id AND s_today.stream_date = ?
                LEFT JOIN streams s_yesterday ON t.track_id = s_yesterday.track_id AND s_yesterday.stream_date = ?
                WHERE 1=1";
        
        $params = [$latestDate, $latestDate, $prevDate];
        $types = 'sss';
        
        if ($filter != 'all') {
            $sql .= getAnalyticsFilterCondition($filter, $types, $params);
        }
        
        $sql .= " HAVING yesterday_total > 0 AND today_total > yesterday_total 
                  AND previous_record IS NOT NULL 
                  AND (today_total - yesterday_total) > previous_record
                  ORDER BY (today_total - yesterday_total) DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'track_id' => $row['track_id'],
                'track_name' => $row['track_name'],
                'artist_name' => $row['artist_name'],
                'today_streams' => $row['today_total'] - $row['yesterday_total'],
                'previous_record' => $row['previous_record']
            ];
        }
        
        $stmt->close();
        $conn->close();
        
        return $data;
    } catch (Exception $e) {
        error_log("get2026RecordBreakers error: " . $e->getMessage());
        return [];
    }
}

// Get biggest daily changes (by value or percent)
function getBiggestDailyChanges($filter = 'all', $changeType = 'value', $limit = 20) {
    try {
        $conn = getDBConnection();
        
        // First, test if we can get dates
        $testQuery = "SELECT 
            MAX(stream_date) AS today,
            (SELECT MAX(stream_date) FROM streams WHERE stream_date < (SELECT MAX(stream_date) FROM streams)) AS yesterday
            FROM streams";
        $testResult = $conn->query($testQuery);
        if ($testResult) {
            $dates = $testResult->fetch_assoc();
            error_log("Dates found: today=" . $dates['today'] . ", yesterday=" . $dates['yesterday']);
        } else {
            error_log("Failed to get dates: " . $conn->error);
        }
        
        // Build artist filter for WHERE clause
        $artistJoin = '';
        $artistWhere = '';
        
        if ($filter != 'all') {
            if ($filter === 'groups') {
                $artistWhere = " AND ta.artist_id IN ('0Sadg1vgvaPqGTOjxu0N6c', '7AKHnZVqwXYuUwWJ8UGL5q', '1foL9hLC9M6U94dINtOYfb')";
            } elseif ($filter === 'solo') {
                $artistWhere = " AND ta.artist_id NOT IN ('0Sadg1vgvaPqGTOjxu0N6c', '7AKHnZVqwXYuUwWJ8UGL5q', '1foL9hLC9M6U94dINtOYfb')";
            } else {
                $artistWhere = " AND ta.artist_id = ?";
            }
        }
        
        $sql = "WITH latest_dates AS (
                    SELECT 
                        MAX(stream_date) AS today,
                        (SELECT MAX(stream_date) FROM streams WHERE stream_date < (SELECT MAX(stream_date) FROM streams)) AS yesterday
                    FROM streams
                ),
                daily_streams AS (
                    SELECT
                        t.track_id,
                        t.track_name,
                        GROUP_CONCAT(DISTINCT ar.artist_name SEPARATOR ', ') as artist_name,
                        (s_today.stream_count - s_yesterday.stream_count) AS today_streams,
                        (s_yesterday.stream_count - s_day_before.stream_count) AS yesterday_streams,
                        (s_today.stream_count - s_yesterday.stream_count) - (s_yesterday.stream_count - s_day_before.stream_count) AS `change`,
                        CASE
                            WHEN (s_yesterday.stream_count - s_day_before.stream_count) = 0 THEN 0
                            ELSE (((s_today.stream_count - s_yesterday.stream_count) - (s_yesterday.stream_count - s_day_before.stream_count)) / (s_yesterday.stream_count - s_day_before.stream_count)) * 100
                        END AS change_percent
                    FROM track t
                    JOIN track_artist ta ON t.track_id = ta.track_id
                    JOIN artist ar ON ta.artist_id = ar.artist_id
                    JOIN streams s_today ON t.track_id = s_today.track_id
                    JOIN streams s_yesterday ON t.track_id = s_yesterday.track_id
                    JOIN streams s_day_before ON t.track_id = s_day_before.track_id
                    CROSS JOIN latest_dates d
                    WHERE s_today.stream_date = d.today
                        AND s_yesterday.stream_date = d.yesterday
                        AND s_day_before.stream_date = DATE_SUB(d.yesterday, INTERVAL 1 DAY)
                        " . $artistWhere . "
                    GROUP BY t.track_id, t.track_name
                )
                SELECT
                    track_id,
                    track_name,
                    artist_name,
                    today_streams,
                    yesterday_streams,
                    `change`,
                    change_percent
                FROM daily_streams";
        
        // Order by the appropriate column
        if ($changeType == 'percent') {
            $sql .= " WHERE yesterday_streams > 0 ORDER BY change_percent DESC LIMIT ?";
        } else {
            $sql .= " ORDER BY `change` DESC LIMIT ?";
        }
        
        error_log("getBiggestDailyChanges SQL: " . $sql);
        error_log("getBiggestDailyChanges filter: $filter, changeType: $changeType");
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("getBiggestDailyChanges prepare failed: " . $conn->error);
            $conn->close();
            return [];
        }
        
        // Bind parameters
        if ($filter != 'all' && $filter != 'groups' && $filter != 'solo') {
            $stmt->bind_param('si', $filter, $limit);
        } else {
            $stmt->bind_param('i', $limit);
        }
        
        if (!$stmt->execute()) {
            error_log("getBiggestDailyChanges execute failed: " . $stmt->error);
            $stmt->close();
            $conn->close();
            return [];
        }
        
        $result = $stmt->get_result();
        if (!$result) {
            error_log("getBiggestDailyChanges get_result failed: " . $stmt->error);
            $stmt->close();
            $conn->close();
            return [];
        }
        
        $data = $result->fetch_all(MYSQLI_ASSOC);
        
        error_log("getBiggestDailyChanges returned " . count($data) . " rows");
        if (count($data) > 0) {
            error_log("First row: " . print_r($data[0], true));
        }
        
        $stmt->close();
        $conn->close();
        
        return $data;
    } catch (Exception $e) {
        error_log("getBiggestDailyChanges exception: " . $e->getMessage());
        error_log("getBiggestDailyChanges trace: " . $e->getTraceAsString());
        return [];
    }
}

// Helper function to calculate next milestone based on type
function getNextMilestone($current, $type) {
    if ($type === 'track') {
        // Song: 1m, 5m, 10m, 15m, then every 5m
        if ($current < 1000000) {
            return 1000000;
        } elseif ($current < 5000000) {
            return 5000000;
        } elseif ($current < 10000000) {
            return 10000000;
        } elseif ($current < 15000000) {
            return 15000000;
        } else {
            return ceil($current / 5000000) * 5000000;
        }
    } else {
        // Album: 5m, 10m, 20m, 30m, then every 10m
        if ($current < 5000000) {
            return 5000000;
        } elseif ($current < 10000000) {
            return 10000000;
        } elseif ($current < 20000000) {
            return 20000000;
        } elseif ($current < 30000000) {
            return 30000000;
        } else {
            return ceil($current / 10000000) * 10000000;
        }
    }
}

// Get tracks/albums approaching milestones
function getMilestoneTracker($filter = 'all', $maxDaysRemaining = 14) {
    try {
        $conn = getDBConnection();
        $results = [];
        
        // Get tracks approaching milestones
        $latestDate = getLatestStreamDate();
        
        // Get previous date for daily calculation
        $prevStmt = $conn->prepare("SELECT MAX(stream_date) as prev FROM streams WHERE stream_date < ?");
        $prevStmt->bind_param('s', $latestDate);
        $prevStmt->execute();
        $prevDate = $prevStmt->get_result()->fetch_assoc()['prev'] ?? null;
        $prevStmt->close();
        
        if (!$prevDate) {
            $conn->close();
            return [];
        }
        
        $sql = "SELECT 
                    'track' as type,
                    t.track_id,
                    t.track_name as name,
                    ar.artist_name,
                    COALESCE(s_today.stream_count, 0) as current_streams,
                    COALESCE(s_yesterday.stream_count, 0) as yesterday_streams
                FROM track t
                JOIN track_artist ta ON t.track_id = ta.track_id
                JOIN artist ar ON ta.artist_id = ar.artist_id
                LEFT JOIN streams s_today ON t.track_id = s_today.track_id AND s_today.stream_date = ?
                LEFT JOIN streams s_yesterday ON t.track_id = s_yesterday.track_id AND s_yesterday.stream_date = ?
                WHERE 1=1";
        
        $params = [$latestDate, $prevDate];
        $types = 'ss';
        
        if ($filter != 'all') {
            $sql .= getAnalyticsFilterCondition($filter, $types, $params);
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $trackResult = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Calculate next milestone for each track
        foreach ($trackResult as $item) {
            $current = $item['current_streams'];
            $dailyStreams = $current - $item['yesterday_streams'];
            
            if ($dailyStreams <= 0) continue; // Skip if no daily growth
            
            $nextMilestone = getNextMilestone($current, 'track');
            $remaining = $nextMilestone - $current;
            
            if ($remaining <= 0) continue; // Skip if already past milestone
            
            $daysRemaining = $remaining / $dailyStreams;
            
            // Only include if within max days threshold
            if ($daysRemaining <= $maxDaysRemaining) {
                $results[] = [
                    'type' => 'track',
                    'name' => $item['name'],
                    'artist_name' => $item['artist_name'],
                    'current_streams' => $current,
                    'daily_streams' => $dailyStreams,
                    'next_milestone' => $nextMilestone,
                    'remaining' => $remaining,
                    'days_remaining' => $daysRemaining
                ];
            }
        }
        
        // Get albums approaching milestones
        $sql2 = "SELECT 
                    'album' as type,
                    a.album_id,
                    a.album_name as name,
                    ar.artist_name,
                    SUM(COALESCE(s_today.stream_count, 0)) as current_streams,
                    SUM(COALESCE(s_yesterday.stream_count, 0)) as yesterday_streams
                FROM album a
                JOIN album_artist aa ON a.album_id = aa.album_id
                JOIN artist ar ON aa.artist_id = ar.artist_id
                JOIN album_track at ON a.album_id = at.album_id
                JOIN track t ON at.track_id = t.track_id
                LEFT JOIN streams s_today ON t.track_id = s_today.track_id AND s_today.stream_date = ?
                LEFT JOIN streams s_yesterday ON t.track_id = s_yesterday.track_id AND s_yesterday.stream_date = ?
                WHERE 1=1";
        
        $params2 = [$latestDate, $prevDate];
        $types2 = 'ss';
        
        if ($filter != 'all') {
            $sql2 .= getAnalyticsFilterCondition($filter, $types2, $params2);
        }
        
        $sql2 .= " GROUP BY a.album_id, a.album_name, ar.artist_name";
        
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param($types2, ...$params2);
        $stmt2->execute();
        $albumResult = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt2->close();
        
        foreach ($albumResult as $item) {
            $current = $item['current_streams'];
            $dailyStreams = $current - $item['yesterday_streams'];
            
            if ($dailyStreams <= 0) continue; // Skip if no daily growth
            
            $nextMilestone = getNextMilestone($current, 'album');
            $remaining = $nextMilestone - $current;
            
            if ($remaining <= 0) continue; // Skip if already past milestone
            
            $daysRemaining = $remaining / $dailyStreams;
            
            // Only include if within max days threshold
            if ($daysRemaining <= $maxDaysRemaining) {
                $results[] = [
                    'type' => 'album',
                    'name' => $item['name'],
                    'artist_name' => $item['artist_name'],
                    'current_streams' => $current,
                    'daily_streams' => $dailyStreams,
                    'next_milestone' => $nextMilestone,
                    'remaining' => $remaining,
                    'days_remaining' => $daysRemaining
                ];
            }
        }
        
        // Sort by days remaining (soonest first)
        usort($results, function($a, $b) {
            return $a['days_remaining'] <=> $b['days_remaining'];
        });
        
        $conn->close();
        return $results;
    } catch (Exception $e) {
        error_log("getMilestoneTracker error: " . $e->getMessage());
        return [];
    }
}

// Get count of record breakers
function getRecordBreakersCount($filter = 'all', $type = 'all-time') {
    if ($type == 'all-time') {
        return count(getAllTimeRecordBreakers($filter));
    } else {
        return count(get2026RecordBreakers($filter));
    }
}

// Get count of milestone trackers
function getMilestoneTrackerCount($filter = 'all') {
    return count(getMilestoneTracker($filter));
}

// Helper function to get previous milestone
function getPreviousMilestone($current, $type) {
    if ($type === 'track') {
        // Song: 1m, 5m, 10m, 15m, then every 5m
        if ($current < 1000000) {
            return 0;
        } elseif ($current < 5000000) {
            return 1000000;
        } elseif ($current < 10000000) {
            return 5000000;
        } elseif ($current < 15000000) {
            return 10000000;
        } elseif ($current < 20000000) {
            return 15000000;
        } else {
            return floor($current / 5000000) * 5000000;
        }
    } else {
        // Album: 5m, 10m, 20m, 30m, then every 10m
        if ($current < 5000000) {
            return 0;
        } elseif ($current < 10000000) {
            return 5000000;
        } elseif ($current < 20000000) {
            return 10000000;
        } elseif ($current < 30000000) {
            return 20000000;
        } elseif ($current < 40000000) {
            return 30000000;
        } else {
            return floor($current / 10000000) * 10000000;
        }
    }
}

// Get tracks/albums that recently passed milestones (within past 2 days)
function getRecentMilestonePassed($filter = 'all') {
    try {
        $conn = getDBConnection();
        $results = [];
        
        $latestDate = getLatestStreamDate();
        
        // Get previous date for daily calculation
        $prevStmt = $conn->prepare("SELECT MAX(stream_date) as prev FROM streams WHERE stream_date < ?");
        $prevStmt->bind_param('s', $latestDate);
        $prevStmt->execute();
        $prevDate = $prevStmt->get_result()->fetch_assoc()['prev'] ?? null;
        $prevStmt->close();
        
        if (!$prevDate) {
            $conn->close();
            return [];
        }
        
        // Get tracks
        $sql = "SELECT 
                    'track' as type,
                    t.track_id,
                    t.track_name as name,
                    ar.artist_name,
                    COALESCE(s_today.stream_count, 0) as current_streams,
                    COALESCE(s_yesterday.stream_count, 0) as yesterday_streams
                FROM track t
                JOIN track_artist ta ON t.track_id = ta.track_id
                JOIN artist ar ON ta.artist_id = ar.artist_id
                LEFT JOIN streams s_today ON t.track_id = s_today.track_id AND s_today.stream_date = ?
                LEFT JOIN streams s_yesterday ON t.track_id = s_yesterday.track_id AND s_yesterday.stream_date = ?
                WHERE 1=1";
        
        $params = [$latestDate, $prevDate];
        $types = 'ss';
        
        if ($filter != 'all') {
            $sql .= getAnalyticsFilterCondition($filter, $types, $params);
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $trackResult = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        foreach ($trackResult as $item) {
            $current = $item['current_streams'];
            $yesterday = $item['yesterday_streams'];
            
            if ($yesterday <= 0) continue; // Need yesterday data for daily calculation
            
            $dailyStreams = $current - $yesterday;
            if ($dailyStreams <= 0) continue; // Need positive growth
            
            // Find the most recent milestone that was passed
            $previousMilestone = getPreviousMilestone($current, 'track');
            
            if ($previousMilestone > 0 && $current >= $previousMilestone) {
                // Calculate how many days ago the milestone was passed
                $streamsSinceMilestone = $current - $previousMilestone;
                $daysAgo = $streamsSinceMilestone / $dailyStreams;
                
                // Only include if passed within last 2 days
                if ($daysAgo <= 2.0) {
                    $results[] = [
                        'type' => 'track',
                        'name' => $item['name'],
                        'artist_name' => $item['artist_name'],
                        'current_streams' => $current,
                        'daily_streams' => $dailyStreams,
                        'milestone_passed' => $previousMilestone,
                        'days_ago' => $daysAgo
                    ];
                }
            }
        }
        
        // Get albums
        $sql2 = "SELECT 
                    'album' as type,
                    a.album_id,
                    a.album_name as name,
                    ar.artist_name,
                    SUM(COALESCE(s_today.stream_count, 0)) as current_streams,
                    SUM(COALESCE(s_yesterday.stream_count, 0)) as yesterday_streams
                FROM album a
                JOIN album_artist aa ON a.album_id = aa.album_id
                JOIN artist ar ON aa.artist_id = ar.artist_id
                JOIN album_track at ON a.album_id = at.album_id
                JOIN track t ON at.track_id = t.track_id
                LEFT JOIN streams s_today ON t.track_id = s_today.track_id AND s_today.stream_date = ?
                LEFT JOIN streams s_yesterday ON t.track_id = s_yesterday.track_id AND s_yesterday.stream_date = ?
                WHERE 1=1";
        
        $params2 = [$latestDate, $prevDate];
        $types2 = 'ss';
        
        if ($filter != 'all') {
            $sql2 .= getAnalyticsFilterCondition($filter, $types2, $params2);
        }
        
        $sql2 .= " GROUP BY a.album_id, a.album_name, ar.artist_name";
        
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param($types2, ...$params2);
        $stmt2->execute();
        $albumResult = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt2->close();
        
        foreach ($albumResult as $item) {
            $current = $item['current_streams'];
            $yesterday = $item['yesterday_streams'];
            
            if ($yesterday <= 0) continue;
            
            $dailyStreams = $current - $yesterday;
            if ($dailyStreams <= 0) continue;
            
            $previousMilestone = getPreviousMilestone($current, 'album');
            
            if ($previousMilestone > 0 && $current >= $previousMilestone) {
                $streamsSinceMilestone = $current - $previousMilestone;
                $daysAgo = $streamsSinceMilestone / $dailyStreams;
                
                if ($daysAgo <= 2.0) {
                    $results[] = [
                        'type' => 'album',
                        'name' => $item['name'],
                        'artist_name' => $item['artist_name'],
                        'current_streams' => $current,
                        'daily_streams' => $dailyStreams,
                        'milestone_passed' => $previousMilestone,
                        'days_ago' => $daysAgo
                    ];
                }
            }
        }
        
        // Sort by days ago (most recent first)
        usort($results, function($a, $b) {
            return $a['days_ago'] <=> $b['days_ago'];
        });
        
        $conn->close();
        return $results;
    } catch (Exception $e) {
        error_log("getRecentMilestonePassed error: " . $e->getMessage());
        return [];
    }
}

// Get count of recent milestone passed
function getRecentMilestonePassedCount($filter = 'all') {
    return count(getRecentMilestonePassed($filter));
}
?>
