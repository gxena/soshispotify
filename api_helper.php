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
        
        // Get latest stats for all artists in filter
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
            'followers_diff' => $followers_latest - $followers_yesterday
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
        
        if ($filter === 'all') {
            // Get ALL tracks (no artist filter)
            $sql = "
                SELECT stream_date, SUM(stream_count) as total
                FROM streams
                WHERE stream_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                GROUP BY stream_date
                ORDER BY stream_date ASC
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $days);
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
                AND s.stream_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                GROUP BY s.stream_date
                ORDER BY s.stream_date ASC
            ";
            
            $params = array_merge($artistIds, [$days]);
            $types = str_repeat('s', count($artistIds)) . 'i';
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
        }
        $result = $stmt->get_result();
        
        $labels = [];
        $data = [];
        $rawData = [];
        
        while ($row = $result->fetch_assoc()) {
            $labels[] = date('j/n', strtotime($row['stream_date']));
            $rawData[] = $row['total'];
        }
        
        // Calculate daily increases for ALL data points DULU
        // i=0 (oldest): tidak punya data kemarin, jadi tidak valid sebagai daily stream
        // i=1, i=2, dst: daily increase yang valid (today - yesterday)
        for ($i = 0; $i < count($rawData); $i++) {
            if ($i == 0) {
                // For first/oldest day, get cumulative total up to that day
                // Ini tidak valid sebagai daily stream karena tidak ada data kemarin
                if ($filter === 'all') {
                    // For 'all' filter, no artist join needed
                    $sql_cum = "
                        SELECT SUM(stream_count) as cumulative
                        FROM streams
                        WHERE stream_date <= (
                            SELECT MIN(stream_date) 
                            FROM streams 
                            WHERE stream_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                        )
                    ";
                    $stmt2 = $conn->prepare($sql_cum);
                    $stmt2->bind_param('i', $days);
                } else {
                    // For artist-specific filters
                    $sql_cum = "
                        SELECT SUM(s.stream_count) as cumulative
                        FROM streams s
                        JOIN track_artist ta ON s.track_id = ta.track_id
                        WHERE ta.artist_id IN ($placeholders)
                        AND s.stream_date <= (
                            SELECT MIN(stream_date) 
                            FROM streams 
                            WHERE stream_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                        )
                    ";
                    $stmt2 = $conn->prepare($sql_cum);
                    $stmt2->bind_param($types, ...$params);
                }
                $stmt2->execute();
                $res2 = $stmt2->get_result();
                $cumData = $res2->fetch_assoc();
                $data[] = $cumData['cumulative'] ?? 0;
                $stmt2->close();
            } else {
                $data[] = $rawData[$i] - $rawData[$i - 1]; // Daily increase
            }
        }
        
        // SETELAH semua dihitung, hapus data point TERLAMA (oldest/index 0)
        // Karena data terlama tidak punya data kemarin untuk dibandingkan
        // Jadi bukan daily stream yang akurat
        if (count($data) > 0) {
            array_shift($data);    // Hapus data TERLAMA (oldest)
            array_shift($labels);  // Hapus label TERLAMA (oldest)
        }
        
        $stmt->close();
        $conn->close();
        
        return ['labels' => $labels, 'data' => $data];
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
?>
