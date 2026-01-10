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

// Get daily streams for chart (last 7 days)
function getDailyStreamsChart($days = 7) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("
            SELECT stream_date, SUM(stream_count) as total
            FROM streams
            WHERE stream_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            GROUP BY stream_date
            ORDER BY stream_date ASC
        ");
        $stmt->bind_param("i", $days);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $labels = [];
        $data = [];
        
        while ($row = $result->fetch_assoc()) {
            $labels[] = date('j/n', strtotime($row['stream_date']));
            $data[] = $row['total'];
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
