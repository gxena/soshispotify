<?php 
session_start();
require_once 'config.php';
require_once 'api_helper.php';

// Handle form submission
$logMessage = $_SESSION['last_log'] ?? '';
$useRealTimeLogging = true; // Set to true for real-time logging

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_data'])) {
    $date = $_POST['date'] ?? date('Y-m-d', strtotime('-1 day'));
    $clientToken = $_POST['client_token'] ?? '';
    $authBearer = $_POST['auth_bearer'] ?? '';
    $getStreams = isset($_POST['get_streams']);
    $getStats = isset($_POST['get_stats']);
    
    if (empty($clientToken) || empty($authBearer)) {
        $logMessage = "ERROR: Client Token and Authorization Bearer are required!";
    } else {
        // Try real-time logging if enabled
        if ($useRealTimeLogging) {
            // Disable output buffering for real-time updates
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', false);
            @ini_set('implicit_flush', true);
            @ob_end_clean();
            header('Content-Type: text/html; charset=utf-8');
            
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Scraping...</title>';
            echo '<link rel="stylesheet" href="assets/css/style.css"></head><body>';
            echo '<div class="dashboard-container"><main class="main-content">';
            echo '<div class="card"><div class="card-header"><h2>Scraping Progress</h2></div>';
            echo '<div class="log-body"><div class="log-output"><pre id="liveLog">';
            
            ob_flush();
            flush();
        }
        
        $logMessage = "=== Scraping Data from Spotify ===\n";
        $logMessage .= "Date: " . $date . "\n\n";
        
        if ($useRealTimeLogging) {
            echo htmlspecialchars($logMessage);
            ob_flush();
            flush();
        }
        
        // Prepare headers
        $headers = [
            "authorization: Bearer " . $authBearer,
            "client-token: " . $clientToken,
            "content-type: application/json;charset=UTF-8",
            "origin: https://open.spotify.com",
            "referer: https://open.spotify.com/",
            "user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36"
        ];
        
        $successCount = 0;
        $errorCount = 0;
        
        // Get Streams
        if ($getStreams) {
            // Get all tracks from track table
            $tracks = getAllTracksFromDB();
        
        if (empty($tracks)) {
            $logMessage .= "No tracks found in database. Please add tracks first.\n";
        } else {
            $batchSize = 10; // Process 10 tracks at once
            $batches = array_chunk($tracks, $batchSize);
            
            foreach ($batches as $batchIndex => $batch) {
                // Initialize curl multi handle
                $multiHandle = curl_multi_init();
                $curlHandles = [];
                $trackMap = [];
                
                // Add all tracks in this batch to multi handle
                foreach ($batch as $track) {
                    $trackId = $track['track_id'];
                    $trackName = $track['track_name'];
                    
                    // Build GraphQL query for Spotify API
                    $graphqlQuery = [
                        "operationName" => "getTrack",
                        "variables" => [
                            "uri" => "spotify:track:" . $trackId
                        ],
                        "extensions" => [
                            "persistedQuery" => [
                                "version" => 1,
                                "sha256Hash" => "612585ae06ba435ad26369870deaae23b5c8800a256cd8a57e08eddc25a37294"
                            ]
                        ]
                    ];
                    
                    // Create cURL handle
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, "https://api-partner.spotify.com/pathfinder/v2/query");
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($graphqlQuery));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    
                    curl_multi_add_handle($multiHandle, $ch);
                    $curlHandles[] = $ch;
                    $trackMap[(int)$ch] = ['id' => $trackId, 'name' => $trackName];
                }
                
                // Execute all requests simultaneously
                $running = null;
                do {
                    curl_multi_exec($multiHandle, $running);
                    curl_multi_select($multiHandle);
                } while ($running > 0);
                
                // Process responses
                foreach ($curlHandles as $ch) {
                    $response = curl_multi_getcontent($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $trackInfo = $trackMap[(int)$ch];
                    $trackId = $trackInfo['id'];
                    $trackName = $trackInfo['name'];
                    
                    if ($httpCode == 200 && $response) {
                        $data = json_decode($response, true);
                        
                        // Extract playcount
                        $streamCount = 0;
                        if (isset($data['data']['trackUnion']['playcount'])) {
                            $streamCount = intval($data['data']['trackUnion']['playcount']);
                        }
                        
                        if ($streamCount > 0) {
                            // Insert into streams table: track_id, stream_date, stream_count
                            if (insertStreamData($trackId, $date, $streamCount)) {
                                $msg = $trackName . " -> [" . number_format($streamCount) . "]\n";
                                $logMessage .= $msg;
                                if ($useRealTimeLogging) { echo htmlspecialchars($msg); ob_flush(); flush(); }
                                $successCount++;
                            } else {
                                $msg = "❌ " . $trackName . " -> DB Error\n";
                                $logMessage .= $msg;
                                if ($useRealTimeLogging) { echo htmlspecialchars($msg); ob_flush(); flush(); }
                                $errorCount++;
                            }
                        } else {
                            $msg = "⚠️ " . $trackName . " -> No playcount data\n";
                            $logMessage .= $msg;
                            if ($useRealTimeLogging) { echo htmlspecialchars($msg); ob_flush(); flush(); }
                            $errorCount++;
                        }
                    } else {
                        $msg = "❌ " . $trackName . " -> API Error (HTTP " . $httpCode . ")\n";
                        $logMessage .= $msg;
                        if ($useRealTimeLogging) { echo htmlspecialchars($msg); ob_flush(); flush(); }
                        if ($httpCode == 401) {
                            $msg = "   → Token expired! Get new tokens from Spotify Web Player\n";
                            $logMessage .= $msg;
                            if ($useRealTimeLogging) { echo htmlspecialchars($msg); ob_flush(); flush(); }
                        }
                        $errorCount++;
                    }
                    
                    curl_multi_remove_handle($multiHandle, $ch);
                    curl_close($ch);
                }
                
                curl_multi_close($multiHandle);
                
                // Small delay between batches to avoid rate limiting
                if ($batchIndex < count($batches) - 1) {
                    usleep(50000); // 50ms delay between batches
                }
            }
        }
        
            $summary = "\n=== Streams Summary ===\n";
            $summary .= "✅ Success: " . $successCount . " tracks\n";
            $summary .= "❌ Errors: " . $errorCount . " tracks\n";
            $logMessage .= $summary;
            if ($useRealTimeLogging) { echo htmlspecialchars($summary); ob_flush(); flush(); }
        }
        
        // Get Monthly Listeners & Followers
        if ($getStats) {
            $statsMsg = "\n=== Getting Artist Stats ===\n";
            $logMessage .= $statsMsg;
            if ($useRealTimeLogging) { echo htmlspecialchars($statsMsg); ob_flush(); flush(); }
            
            // Get all artists from database
            $artists = getArtistList();
            
            if (empty($artists)) {
                $errMsg = "⚠️ No artists found in database\n";
                $logMessage .= $errMsg;
                if ($useRealTimeLogging) { echo htmlspecialchars($errMsg); ob_flush(); flush(); }
            } else {
                $statsSuccessCount = 0;
                $statsErrorCount = 0;
                
                $batchSize = 5; // Process 5 artists at once (lebih kecil karena response lebih besar)
                $batches = array_chunk($artists, $batchSize);
                
                foreach ($batches as $batchIndex => $batch) {
                    // Initialize curl multi handle
                    $multiHandle = curl_multi_init();
                    $curlHandles = [];
                    $artistMap = [];
                    
                    // Add all artists in this batch to multi handle
                    foreach ($batch as $artist) {
                        $artistId = $artist['artist_id'];
                        $artistName = $artist['artist_name'];
                        
                        // Build GraphQL query for Artist Overview
                        $graphqlQuery = [
                            "operationName" => "queryArtistOverview",
                            "variables" => [
                                "uri" => "spotify:artist:" . $artistId,
                                "locale" => "intl-id"
                            ],
                            "extensions" => [
                                "persistedQuery" => [
                                    "version" => 1,
                                    "sha256Hash" => "446130b4a0aa6522a686aafccddb0ae849165b5e0436fd802f96e0243617b5d8"
                                ]
                            ]
                        ];
                        
                        // Create cURL handle
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, "https://api-partner.spotify.com/pathfinder/v2/query");
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($graphqlQuery));
                        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                        
                        curl_multi_add_handle($multiHandle, $ch);
                        $curlHandles[] = $ch;
                        $artistMap[(int)$ch] = ['id' => $artistId, 'name' => $artistName];
                    }
                    
                    // Execute all requests simultaneously
                    $running = null;
                    do {
                        curl_multi_exec($multiHandle, $running);
                        curl_multi_select($multiHandle);
                    } while ($running > 0);
                    
                    // Process responses
                    foreach ($curlHandles as $ch) {
                        $response = curl_multi_getcontent($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        $artistInfo = $artistMap[(int)$ch];
                        $artistId = $artistInfo['id'];
                        $artistName = $artistInfo['name'];
                        
                        try {
                            if ($httpCode == 200 && $response) {
                                $data = json_decode($response, true);
                                
                                // Extract stats
                                $monthlyListeners = 0;
                                $followers = 0;
                                
                                if (isset($data['data']['artistUnion']['stats'])) {
                                    $stats = $data['data']['artistUnion']['stats'];
                                    $monthlyListeners = $stats['monthlyListeners'] ?? 0;
                                    $followers = $stats['followers'] ?? 0;
                                }
                                
                                if ($monthlyListeners > 0 || $followers > 0) {
                                    $conn = getDBConnection();
                                    $stmt = $conn->prepare("INSERT INTO artist_stats (artist_id, stat_date, monthly_listeners, followers) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE monthly_listeners = VALUES(monthly_listeners), followers = VALUES(followers)");
                                    $stmt->bind_param("ssii", $artistId, $date, $monthlyListeners, $followers);
                                    $stmt->execute();
                                    
                                    $statsMsg = "✅ " . $artistName . " -> ML: " . number_format($monthlyListeners) . ", Followers: " . number_format($followers) . "\n";
                                    $logMessage .= $statsMsg;
                                    if ($useRealTimeLogging) { echo htmlspecialchars($statsMsg); ob_flush(); flush(); }
                                    
                                    $stmt->close();
                                    $conn->close();
                                    $statsSuccessCount++;
                                } else {
                                    $errMsg = "⚠️ " . $artistName . " -> No stats data\n";
                                    $logMessage .= $errMsg;
                                    if ($useRealTimeLogging) { echo htmlspecialchars($errMsg); ob_flush(); flush(); }
                                    $statsErrorCount++;
                                }
                            } else {
                                $errMsg = "❌ " . $artistName . " -> API Error (HTTP " . $httpCode . ")\n";
                                if ($httpCode == 401) {
                                    $errMsg .= "   → Token expired! Get new tokens from Spotify Web Player\n";
                                }
                                $logMessage .= $errMsg;
                                if ($useRealTimeLogging) { echo htmlspecialchars($errMsg); ob_flush(); flush(); }
                                $statsErrorCount++;
                            }
                        } catch (Exception $e) {
                            $errMsg = "❌ " . $artistName . " -> Error: " . $e->getMessage() . "\n";
                            $logMessage .= $errMsg;
                            if ($useRealTimeLogging) { echo htmlspecialchars($errMsg); ob_flush(); flush(); }
                            $statsErrorCount++;
                        }
                        
                        curl_multi_remove_handle($multiHandle, $ch);
                        curl_close($ch);
                    }
                    
                    curl_multi_close($multiHandle);
                    
                    // Small delay between batches
                    if ($batchIndex < count($batches) - 1) {
                        usleep(50000); // 50ms delay between batches
                    }
                }
                
                $statsSummary = "\n=== Stats Summary ===\n";
                $statsSummary .= "✅ Success: " . $statsSuccessCount . " artists\n";
                $statsSummary .= "❌ Errors: " . $statsErrorCount . " artists\n";
                $logMessage .= $statsSummary;
                if ($useRealTimeLogging) { echo htmlspecialchars($statsSummary); ob_flush(); flush(); }
            }
        }
        
        // Close real-time logging HTML
        if ($useRealTimeLogging) {
            // Save log to session for display after redirect
            $_SESSION['last_log'] = $logMessage;
            echo '</pre></div></div></div></main></div>';
            echo '<script>setTimeout(function(){ window.location.href="scrape.php"; }, 3000);</script>';
            echo '</body></html>';
            exit;
        } else {
            // Save log to session for non-realtime mode
            $_SESSION['last_log'] = $logMessage;
        }
    }
}

// Helper function to get all tracks from track table
function getAllTracksFromDB() {
    try {
        $conn = getDBConnection();
        $result = $conn->query("SELECT track_id, track_name FROM track ORDER BY track_name");
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

// Helper function to insert stream data
function insertStreamData($trackId, $streamDate, $streamCount) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("INSERT INTO streams (track_id, stream_date, stream_count) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE stream_count = VALUES(stream_count)");
        $stmt->bind_param("ssi", $trackId, $streamDate, $streamCount);
        $success = $stmt->execute();
        $stmt->close();
        $conn->close();
        return $success;
    } catch (Exception $e) {
        return false;
    }
}

// Get latest date from streams and set default to +1 day
$latestStreamDate = getLatestStreamDate();
if ($latestStreamDate) {
    $defaultDate = date('Y-m-d', strtotime($latestStreamDate . ' +1 day'));
} else {
    $defaultDate = date('Y-m-d'); // If no data, use today
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scrap Data - SoshiSpotify</title>
    <link rel="icon" type="image/png" href="PROFILE.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <?php $activePage = 'scrape'; include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <header class="topbar">
                <div class="topbar-left">
                    <h1>Scrape Data From Spotify</h1>
                </div>
            </header>

            <!-- Scrape Form -->
            <div class="scrape-container">
                <div class="card">
                    <div class="card-header">
                        <h2>Enter Information</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="form-table">
                                <div class="form-head">
                                    <span>Information</span>
                                    <span>Input</span>
                                </div>
                                
                                <div class="form-row">
                                    <label class="form-label">Client-Token</label>
                                    <input type="text" 
                                           name="client_token" 
                                           class="form-input" 
                                           placeholder="Enter here..."
                                           value="<?php echo isset($_POST['client_token']) ? htmlspecialchars($_POST['client_token']) : ''; ?>"
                                           required>
                                </div>

                                <div class="form-row">
                                    <label class="form-label">Authorization</label>
                                    <input type="text" 
                                           name="auth_bearer" 
                                           class="form-input" 
                                           placeholder="Enter here..."
                                           value="<?php echo isset($_POST['auth_bearer']) ? htmlspecialchars($_POST['auth_bearer']) : ''; ?>"
                                           required>
                                </div>

                                <div class="form-row">
                                    <label class="form-label">Date</label>
                                    <input type="date" 
                                           name="date" 
                                           id="scrapeDate"
                                           class="form-input" 
                                           value="<?php echo $defaultDate; ?>"
                                           min="<?php echo $latestStreamDate ? date('Y-m-d', strtotime($latestStreamDate . ' +1 day')) : date('Y-m-d'); ?>"
                                           onchange="validateScrapeDate()"
                                           required>
                                    <small id="dateWarning" style="color: #EF4444; display: none; margin-top: 5px;">
                                        ⚠️ Warning: Selected date is on or before the latest stream date (<?php echo $latestStreamDate; ?>). This may cause duplicate data.
                                    </small>
                                </div>
                                
                                <div class="form-row">
                                    <label class="form-label">Options</label>
                                    <div style="display: flex; gap: 1.5rem;">
                                        <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; cursor: pointer;">
                                            <input type="checkbox" name="get_streams" checked style="cursor: pointer;">
                                            Get Streams
                                        </label>
                                        <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; cursor: pointer;">
                                            <input type="checkbox" name="get_stats" checked style="cursor: pointer;">
                                            Get Monthly Listeners & Followers
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="get_data" class="btn-submit">
                                    Get Data
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Log Information -->
                <div class="card">
                    <div class="card-header">
                        <h2>Log Information</h2>
                    </div>
                    <div class="log-body">
                        <div class="log-output">
                            <?php if (!empty($logMessage)): ?>
                                <pre><?php echo htmlspecialchars($logMessage); ?></pre>
                            <?php else: ?>
                                <p class="log-placeholder">Gee Streams: XXX.XXX<br>ERROR BRUH.....</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
    function validateScrapeDate() {
        const dateInput = document.getElementById('scrapeDate');
        const warning = document.getElementById('dateWarning');
        const latestDate = '<?php echo $latestStreamDate; ?>';
        
        if (dateInput.value && latestDate) {
            const selectedDate = new Date(dateInput.value);
            const latest = new Date(latestDate);
            
            if (selectedDate <= latest) {
                warning.style.display = 'block';
            } else {
                warning.style.display = 'none';
            }
        }
    }
    
    // Run validation on page load
    window.addEventListener('DOMContentLoaded', validateScrapeDate);
    </script>
</body>
</html>
