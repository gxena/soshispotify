<?php 
require_once 'config.php';
require_once 'api_helper.php';

// Handle form submission
$logMessage = '';
$useRealTimeLogging = true; // Set to true for real-time logging

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_data'])) {
    $date = $_POST['date'] ?? date('Y-m-d', strtotime('-1 day'));
    $clientToken = $_POST['client_token'] ?? '';
    $authBearer = $_POST['auth_bearer'] ?? '';
    $monthlyListeners = $_POST['monthly_listeners'] ?? '';
    $followers = $_POST['followers'] ?? '';
    
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
        
        // Get all tracks from track table
        $tracks = getAllTracksFromDB();
        $successCount = 0;
        $errorCount = 0;
        
        if (empty($tracks)) {
            $logMessage .= "No tracks found in database. Please add tracks first.\n";
        } else {
            foreach ($tracks as $track) {
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
                
                // Make cURL request
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "https://api-partner.spotify.com/pathfinder/v2/query");
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($graphqlQuery));
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
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
                
                usleep(100000); // 100ms delay
            }
        }
        
        $summary = "\n=== Summary ===\n";
        $summary .= "✅ Success: " . $successCount . " tracks\n";
        $summary .= "❌ Errors: " . $errorCount . " tracks\n";
        $logMessage .= $summary;
        if ($useRealTimeLogging) { echo htmlspecialchars($summary); ob_flush(); flush(); }
        
        // Save artist stats if provided
        if (!empty($monthlyListeners) || !empty($followers)) {
            try {
                $conn = getDBConnection();
                $artistId = '0Sadg1vgvaPqGTOjxu0N6c'; // Girls' Generation artist ID
                
                $stmt = $conn->prepare("INSERT INTO artist_stats (artist_id, stat_date, monthly_listeners, followers) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE monthly_listeners = VALUES(monthly_listeners), followers = VALUES(followers)");
                $stmt->bind_param("ssii", $artistId, $date, $monthlyListeners, $followers);
                $stmt->execute();
                
                $statsMsg = "\n✅ Artist stats saved\n";
                if ($monthlyListeners) $statsMsg .= "Monthly Listeners: " . number_format($monthlyListeners) . "\n";
                if ($followers) $statsMsg .= "Followers: " . number_format($followers) . "\n";
                $logMessage .= $statsMsg;
                if ($useRealTimeLogging) { echo htmlspecialchars($statsMsg); ob_flush(); flush(); }
                
                $stmt->close();
                $conn->close();
            } catch (Exception $e) {
                $errMsg = "\n❌ Artist stats error: " . $e->getMessage();
                $logMessage .= $errMsg;
                if ($useRealTimeLogging) { echo htmlspecialchars($errMsg); ob_flush(); flush(); }
            }
        }
        
        // Close real-time logging HTML
        if ($useRealTimeLogging) {
            echo '</pre></div></div></div></main></div>';
            echo '<script>setTimeout(function(){ window.location.href="scrape.php"; }, 3000);</script>';
            echo '</body></html>';
            exit;
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

$defaultDate = date('Y-m-d', strtotime('-1 day'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scrap Data - SoshiSpotify</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-sliders-h"></i>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-th-large"></i>
                </a>
                <a href="members.php" class="nav-item">
                    <i class="fas fa-users"></i>
                </a>
                <a href="scrape.php" class="nav-item active">
                    <i class="fas fa-database"></i>
                </a>
            </nav>
            <div class="sidebar-footer">
                <a href="index.php" class="nav-item">
                    <i class="fas fa-arrow-left"></i>
                </a>
            </div>
        </aside>

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
                <!-- Instructions Card -->
                <div class="card" style="margin-bottom: 1rem; background: #FFF3CD; border-left: 4px solid #FFC107;">
                    <div class="card-body" style="padding: 1rem 1.5rem;">
                        <h3 style="font-size: 0.9rem; margin-bottom: 0.5rem; color: #856404;">⚠️ How to Fix API 401 Error</h3>
                        <ol style="font-size: 0.8rem; color: #856404; margin: 0; padding-left: 1.5rem; line-height: 1.6;">
                            <li>Open <strong>Spotify Web Player</strong> (open.spotify.com) in Chrome/Edge</li>
                            <li>Press <strong>F12</strong> to open Developer Tools</li>
                            <li>Go to <strong>Network</strong> tab → Click any song to play</li>
                            <li>Find request to <code>pathfinder/v1/query</code> or <code>pathfinder/v2/query</code></li>
                            <li>Click it → Go to <strong>Headers</strong> tab</li>
                            <li>Copy <strong>client-token</strong> and <strong>authorization</strong> values</li>
                            <li>Paste them below (tokens expire every ~1 hour)</li>
                        </ol>
                        <p style="font-size: 0.75rem; margin: 0.5rem 0 0 0; color: #856404; font-style: italic;">
                            💡 Real-time logging: Edit scrape.php line 7, change <code>$useRealTimeLogging = false;</code> to <code>true</code> 
                            (may not work on all servers)
                        </p>
                    </div>
                </div>

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
                                    <label class="form-label">Monthly Listeners</label>
                                    <input type="number" 
                                           name="monthly_listeners" 
                                           class="form-input" 
                                           placeholder="Enter here..."
                                           value="<?php echo isset($_POST['monthly_listeners']) ? htmlspecialchars($_POST['monthly_listeners']) : ''; ?>">
                                </div>

                                <div class="form-row">
                                    <label class="form-label">Followers</label>
                                    <input type="number" 
                                           name="followers" 
                                           class="form-input" 
                                           placeholder="Enter here..."
                                           value="<?php echo isset($_POST['followers']) ? htmlspecialchars($_POST['followers']) : ''; ?>">
                                </div>

                                <div class="form-row">
                                    <label class="form-label">Date</label>
                                    <input type="date" 
                                           name="date" 
                                           class="form-input" 
                                           value="<?php echo $defaultDate; ?>"
                                           required>
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
</body>
</html>