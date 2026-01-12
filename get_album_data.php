<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
require_once 'config.php';

try {
    $conn = getDBConnection();
    $albumId = $_GET['album_id'] ?? '';

    if (empty($albumId)) {
        echo json_encode(['error' => 'Album ID required']);
        exit;
    }

    // Get latest date and previous date
    $latestDate = null;
    $prevDate = null;
    $dayBeforeDate = null;
    $dateResult = $conn->query("SELECT MAX(stream_date) as latest FROM streams");
    if ($dateResult) {
        $latestDate = $dateResult->fetch_assoc()['latest'] ?? date('Y-m-d');
        $prevStmt = $conn->prepare("SELECT MAX(stream_date) as prev FROM streams WHERE stream_date < ?");
        $prevStmt->bind_param('s', $latestDate);
        $prevStmt->execute();
        $prevDate = $prevStmt->get_result()->fetch_assoc()['prev'] ?? null;
        $prevStmt->close();
        
        if ($prevDate) {
            $dayBeforeStmt = $conn->prepare("SELECT MAX(stream_date) as day_before FROM streams WHERE stream_date < ?");
            $dayBeforeStmt->bind_param('s', $prevDate);
            $dayBeforeStmt->execute();
            $dayBeforeDate = $dayBeforeStmt->get_result()->fetch_assoc()['day_before'] ?? null;
            $dayBeforeStmt->close();
        }
    }

    // Get album basic info (use 'type' instead of 'album_type')
    $albumQuery = "SELECT 
                    a.album_id,
                    a.album_name,
                    a.type as album_type,
                    a.release_date,
                    a.img_640,
                    a.img_300,
                    a.img_64,
                    ar.artist_name,
                    COALESCE(SUM(s1.stream_count - COALESCE(s2.stream_count, 0)), 0) as daily_streams,
                    COALESCE(SUM(s2.stream_count - COALESCE(s3.stream_count, 0)), 0) as prev_daily_streams,
                    COALESCE(SUM(s1.stream_count), 0) as total_streams
                FROM album a
                LEFT JOIN album_artist aa ON a.album_id = aa.album_id
                LEFT JOIN artist ar ON aa.artist_id = ar.artist_id
                LEFT JOIN album_track at ON a.album_id = at.album_id
                LEFT JOIN track t ON at.track_id = t.track_id
                LEFT JOIN streams s1 ON t.track_id = s1.track_id AND s1.stream_date = ?
                LEFT JOIN streams s2 ON t.track_id = s2.track_id AND s2.stream_date = ?
                LEFT JOIN streams s3 ON t.track_id = s3.track_id AND s3.stream_date = ?
                WHERE a.album_id = ?
                GROUP BY a.album_id, a.album_name, a.type, a.release_date, a.img_640, a.img_300, a.img_64, ar.artist_name";

    $stmt = $conn->prepare($albumQuery);
    if (!$stmt) {
        throw new Exception("Prepare failed for album query: " . $conn->error);
    }
    
    $stmt->bind_param("ssss", $latestDate, $prevDate, $dayBeforeDate, $albumId);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed for album query: " . $stmt->error);
    }
    
    $album = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$album) {
        echo json_encode(['error' => 'Album not found', 'album_id' => $albumId]);
        exit;
    }

    // Get tracks for this album
    $tracksQuery = "SELECT 
                    t.track_id,
                    t.track_name,
                    at.track_number,
                    COALESCE(s1.stream_count - COALESCE(s2.stream_count, 0), 0) as daily_streams,
                    COALESCE(s2.stream_count - COALESCE(s3.stream_count, 0), 0) as prev_daily_streams,
                    COALESCE(s1.stream_count, 0) as total_streams
                FROM album_track at
                JOIN track t ON at.track_id = t.track_id
                LEFT JOIN streams s1 ON t.track_id = s1.track_id AND s1.stream_date = ?
                LEFT JOIN streams s2 ON t.track_id = s2.track_id AND s2.stream_date = ?
                LEFT JOIN streams s3 ON t.track_id = s3.track_id AND s3.stream_date = ?
                WHERE at.album_id = ?
                ORDER BY at.track_number ASC";

    $stmt = $conn->prepare($tracksQuery);
    if (!$stmt) {
        throw new Exception("Prepare failed for tracks query: " . $conn->error);
    }
    
    $stmt->bind_param("ssss", $latestDate, $prevDate, $dayBeforeDate, $albumId);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed for tracks query: " . $stmt->error);
    }
    
    $tracks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $album['tracks'] = $tracks;
    $album['stream_date'] = $latestDate;

    echo json_encode($album);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
