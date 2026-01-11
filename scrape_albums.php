<?php 
session_start();
require_once 'config.php';
require_once 'api_helper.php';

// Get database connection
$conn = getDBConnection();

// Handle form submission
$logMessage = $_SESSION['last_log'] ?? '';
$useRealTimeLogging = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['scrape_albums'])) {
    $clientToken = $_POST['client_token'] ?? '';
    $authBearer = $_POST['auth_bearer'] ?? '';
    
    if (empty($clientToken) || empty($authBearer)) {
        $logMessage = "ERROR: Client Token and Authorization Bearer are required!";
    } else {
        // Try real-time logging
        if ($useRealTimeLogging) {
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', false);
            @ini_set('implicit_flush', true);
            @ob_end_clean();
            header('Content-Type: text/html; charset=utf-8');
            
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Scraping Albums...</title>';
            echo '<link rel="stylesheet" href="assets/css/style.css"></head><body>';
            echo '<div class="dashboard-container"><main class="main-content">';
            echo '<div class="card"><div class="card-header"><h2>Album Scraping Progress</h2></div>';
            echo '<div class="log-body"><div class="log-output"><pre id="liveLog">';
            
            @ob_flush();
            flush();
        }
        
        $logMessage = "=== Scraping Album Data from Spotify ===\n\n";
        
        if ($useRealTimeLogging) {
            echo htmlspecialchars($logMessage);
            @ob_flush();
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
        
        // Get all albums that need scraping
        // Priority 1: Albums without release_date or with default 0000-00-00
        // Priority 2: Albums without any tracks in album_track (even if they have release_date)
        $sql = "SELECT DISTINCT a.album_id 
                FROM album a 
                LEFT JOIN album_track at ON a.album_id = at.album_id 
                WHERE (a.release_date IS NULL OR a.release_date = '0000-00-00') 
                   OR at.album_id IS NULL 
                ORDER BY a.album_id";
        $result = $conn->query($sql);
        
        if ($result->num_rows == 0) {
            $logMessage = "No albums found that need scraping.\n";
            if ($useRealTimeLogging) {
                echo htmlspecialchars($logMessage);
                @ob_flush();
                flush();
            }
        } else {
            $albums = [];
            while ($row = $result->fetch_assoc()) {
                $albums[] = $row['album_id'];
            }
            
            $logMessage = "Found " . count($albums) . " albums to scrape.\n\n";
            if ($useRealTimeLogging) {
                echo htmlspecialchars($logMessage);
                @ob_flush();
                flush();
            }
            
            foreach ($albums as $albumId) {
                $msg = "Processing album: $albumId\n";
                if ($useRealTimeLogging) {
                    echo htmlspecialchars($msg);
                    @ob_flush();
                    flush();
                }
                
                // Build GraphQL query for Album API
                $graphqlQuery = [
                    "operationName" => "getAlbum",
                    "variables" => [
                        "uri" => "spotify:album:" . $albumId,
                        "locale" => "",
                        "offset" => 0,
                        "limit" => 50
                    ],
                    "extensions" => [
                        "persistedQuery" => [
                            "version" => 1,
                            "sha256Hash" => "b9bfabef66ed756e5e13f68a942deb60bd4125ec1f1be8cc42769dc0259b4b10"
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
                
                if ($httpCode !== 200) {
                    $msg = "  ERROR: HTTP $httpCode\n";
                    $errorCount++;
                    if ($useRealTimeLogging) {
                        echo htmlspecialchars($msg);
                        @ob_flush();
                        flush();
                    }
                    continue;
                }
                
                $data = json_decode($response, true);
                
                if (!isset($data['data']['albumUnion'])) {
                    $msg = "  ERROR: Invalid response structure\n";
                    $errorCount++;
                    if ($useRealTimeLogging) {
                        echo htmlspecialchars($msg);
                        @ob_flush();
                        flush();
                    }
                    continue;
                }
                
                $albumData = $data['data']['albumUnion'];
                
                // Get release date
                $releaseDate = null;
                if (isset($albumData['date']['isoString'])) {
                    $releaseDate = date('Y-m-d', strtotime($albumData['date']['isoString']));
                }
                
                // Get cover art images
                $img_64 = null;
                $img_300 = null;
                $img_640 = null;
                
                if (isset($albumData['coverArt']['sources']) && is_array($albumData['coverArt']['sources'])) {
                    foreach ($albumData['coverArt']['sources'] as $source) {
                        if (isset($source['height']) && isset($source['url'])) {
                            switch ($source['height']) {
                                case 64:
                                    $img_64 = $source['url'];
                                    break;
                                case 300:
                                    $img_300 = $source['url'];
                                    break;
                                case 640:
                                    $img_640 = $source['url'];
                                    break;
                            }
                        }
                    }
                }
                
                // Update album release_date and cover images
                if ($releaseDate || $img_64 || $img_300 || $img_640) {
                    $updateParts = [];
                    $params = [];
                    $types = '';
                    
                    if ($releaseDate) {
                        $updateParts[] = "release_date = ?";
                        $params[] = $releaseDate;
                        $types .= 's';
                    }
                    if ($img_64) {
                        $updateParts[] = "img_64 = ?";
                        $params[] = $img_64;
                        $types .= 's';
                    }
                    if ($img_300) {
                        $updateParts[] = "img_300 = ?";
                        $params[] = $img_300;
                        $types .= 's';
                    }
                    if ($img_640) {
                        $updateParts[] = "img_640 = ?";
                        $params[] = $img_640;
                        $types .= 's';
                    }
                    
                    $params[] = $albumId;
                    $types .= 's';
                    
                    $sql = "UPDATE album SET " . implode(', ', $updateParts) . " WHERE album_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param($types, ...$params);
                    
                    if ($stmt->execute()) {
                        $msg = "  ✓ Album data updated";
                        if ($releaseDate) $msg .= ": $releaseDate";
                        if ($img_64 || $img_300 || $img_640) $msg .= " + cover art";
                        $msg .= "\n";
                        if ($useRealTimeLogging) {
                            echo htmlspecialchars($msg);
                            @ob_flush();
                            flush();
                        }
                    } else {
                        $msg = "  ERROR updating album data: " . $stmt->error . "\n";
                        $errorCount++;
                        if ($useRealTimeLogging) {
                            echo htmlspecialchars($msg);
                            @ob_flush();
                            flush();
                        }
                    }
                    $stmt->close();
                }
                
                // Get tracks
                if (isset($albumData['tracksV2']['items'])) {
                    $tracks = $albumData['tracksV2']['items'];
                    $msg = "  Found " . count($tracks) . " tracks\n";
                    if ($useRealTimeLogging) {
                        echo htmlspecialchars($msg);
                        @ob_flush();
                        flush();
                    }
                    
                    foreach ($tracks as $trackItem) {
                        try {
                            if (!isset($trackItem['track']['uri']) || !isset($trackItem['track']['trackNumber'])) {
                                continue;
                            }
                            
                            // Extract track_id from URI (spotify:track:XXXXX)
                            $trackUri = $trackItem['track']['uri'];
                            $trackIdFromApi = str_replace('spotify:track:', '', $trackUri);
                            
                            // Keep both versions: clean and original
                            $trackIdClean = preg_replace('/\?si.*$/', '', $trackIdFromApi);
                            $trackIdOriginal = $trackIdFromApi;
                            
                            $trackNumber = $trackItem['track']['trackNumber'];
                            
                            // Debug: show what we got from API
                            $msg = "    [Track #$trackNumber] API returned: $trackIdFromApi\n";
                            if ($trackIdClean !== $trackIdOriginal) {
                                $msg .= "    [Track #$trackNumber] Cleaned to: $trackIdClean\n";
                            }
                            if ($useRealTimeLogging) {
                                echo htmlspecialchars($msg);
                                @ob_flush();
                                flush();
                            }
                            
                            // Check if track already exists in album_track (skip duplicates)
                            $checkStmt = $conn->prepare("SELECT 1 FROM album_track WHERE album_id = ? AND track_number = ? LIMIT 1");
                            if ($checkStmt) {
                                $checkStmt->bind_param("si", $albumId, $trackNumber);
                                $checkStmt->execute();
                                $checkResult = $checkStmt->get_result();
                                
                                if ($checkResult->num_rows > 0) {
                                    $msg = "    ⊘ Track #$trackNumber: Already exists (skipped)\n";
                                    if ($useRealTimeLogging) {
                                        echo htmlspecialchars($msg);
                                        @ob_flush();
                                        flush();
                                    }
                                    $checkStmt->close();
                                    continue;
                                }
                                $checkStmt->close();
                            }
                            
                            // Try to insert into album_track
                            $stmt = $conn->prepare("INSERT INTO album_track (album_id, track_id, track_number) VALUES (?, ?, ?)");
                            if (!$stmt) {
                                $msg = "    ERROR Track #$trackNumber: Failed to prepare statement\n";
                                if ($useRealTimeLogging) {
                                    echo htmlspecialchars($msg);
                                    @ob_flush();
                                    flush();
                                }
                                continue;
                            }
                            
                            // First attempt: clean version (without ?si...)
                            $msg = "    [Attempt 1] Trying: $trackIdClean\n";
                            if ($useRealTimeLogging) {
                                echo htmlspecialchars($msg);
                                @ob_flush();
                                flush();
                            }
                            
                            $stmt->bind_param("ssi", $albumId, $trackIdClean, $trackNumber);
                            
                            $executeSuccess = false;
                            $executeError = null;
                            
                            try {
                                $executeSuccess = $stmt->execute();
                                if (!$executeSuccess) {
                                    $executeError = $stmt->error;
                                }
                            } catch (Exception $e) {
                                $executeError = $e->getMessage();
                            }
                            
                            if ($executeSuccess) {
                                $msg = "    ✓ SUCCESS with: $trackIdClean\n";
                                if ($useRealTimeLogging) {
                                    echo htmlspecialchars($msg);
                                    @ob_flush();
                                    flush();
                                }
                            } else {
                                // Check if it's a duplicate key error
                                if (strpos($executeError, 'Duplicate entry') !== false) {
                                    $msg = "    ⊘ Track #$trackNumber: Duplicate (skipped)\n";
                                    if ($useRealTimeLogging) {
                                        echo htmlspecialchars($msg);
                                        @ob_flush();
                                        flush();
                                    }
                                }
                                // Check if it's a foreign key error
                                else if (strpos($executeError, 'foreign key constraint') !== false || strpos($executeError, 'Cannot add or update a child row') !== false) {
                                    $msg = "    ⚠ FK Error: Track ID not found in track table\n";
                                    if ($useRealTimeLogging) {
                                        echo htmlspecialchars($msg);
                                        @ob_flush();
                                        flush();
                                    }
                                    
                                    // Try fuzzy search in database immediately
                                    $correctTrackId = null;
                                    $patterns = [
                                        $trackIdClean . '?si=%',
                                        $trackIdClean . '%'
                                    ];
                                    
                                    foreach ($patterns as $searchPattern) {
                                        $msg = "    [Attempt 2] Searching with pattern: $searchPattern\n";
                                        if ($useRealTimeLogging) {
                                            echo htmlspecialchars($msg);
                                            @ob_flush();
                                            flush();
                                        }
                                        
                                        $findStmt = $conn->prepare("SELECT track_id FROM track WHERE track_id LIKE ? LIMIT 1");
                                        if ($findStmt) {
                                            $findStmt->bind_param("s", $searchPattern);
                                            $findStmt->execute();
                                            $findResult = $findStmt->get_result();
                                            
                                            if ($findResult->num_rows > 0) {
                                                $foundRow = $findResult->fetch_assoc();
                                                $correctTrackId = $foundRow['track_id'];
                                                $msg = "    [Found] Database has: $correctTrackId\n";
                                                if ($useRealTimeLogging) {
                                                    echo htmlspecialchars($msg);
                                                    @ob_flush();
                                                    flush();
                                                }
                                                $findStmt->close();
                                                break;
                                            }
                                            $findStmt->close();
                                        }
                                    }
                                    
                                    if ($correctTrackId) {
                                        $msg = "    [Attempt 3] Trying found ID: $correctTrackId\n";
                                        if ($useRealTimeLogging) {
                                            echo htmlspecialchars($msg);
                                            @ob_flush();
                                            flush();
                                        }
                                        
                                        $stmt2 = $conn->prepare("INSERT INTO album_track (album_id, track_id, track_number) VALUES (?, ?, ?)");
                                        if ($stmt2) {
                                            $stmt2->bind_param("ssi", $albumId, $correctTrackId, $trackNumber);
                                            try {
                                                if ($stmt2->execute()) {
                                                    $msg = "    ✓ SUCCESS with: $correctTrackId\n";
                                                } else {
                                                    $msg = "    ⚠ FAILED: " . $stmt2->error . "\n";
                                                }
                                            } catch (Exception $e) {
                                                $msg = "    ⚠ FAILED: " . $e->getMessage() . "\n";
                                            }
                                            if ($useRealTimeLogging) {
                                                echo htmlspecialchars($msg);
                                                @ob_flush();
                                                flush();
                                            }
                                            $stmt2->close();
                                        }
                                    } else {
                                        $msg = "    ⚠ NOT FOUND in database with any pattern\n";
                                        if ($useRealTimeLogging) {
                                            echo htmlspecialchars($msg);
                                            @ob_flush();
                                            flush();
                                        }
                                    }
                                } else {
                                    $msg = "    ⚠ Other error: " . $executeError . "\n";
                                    if ($useRealTimeLogging) {
                                        echo htmlspecialchars($msg);
                                        @ob_flush();
                                        flush();
                                    }
                                }
                            }
                            $stmt->close();
                            
                        } catch (Exception $e) {
                            $msg = "    ⚠ Track exception: " . $e->getMessage() . " (skipped)\n";
                            if ($useRealTimeLogging) {
                                echo htmlspecialchars($msg);
                                @ob_flush();
                                flush();
                            }
                            continue;
                        }
                    }
                }
                
                $successCount++;
                $msg = "  ✓ Album completed\n\n";
                if ($useRealTimeLogging) {
                    echo htmlspecialchars($msg);
                    @ob_flush();
                    flush();
                }
                
                // Small delay to avoid rate limiting
                usleep(500000); // 0.5 second
            }
        }
        
        $logMessage = "\n=== Scraping Complete ===\n";
        $logMessage .= "Success: $successCount albums\n";
        $logMessage .= "Errors: $errorCount\n";
        
        if ($useRealTimeLogging) {
            echo htmlspecialchars($logMessage);
            echo '</pre></div></div></div>';
            echo '<br><a href="scrape_albums.php" class="btn">Back to Form</a>';
            echo '</main></div></body></html>';
            @ob_flush();
            flush();
            exit;
        }
        
        $_SESSION['last_log'] = $logMessage;
        header('Location: scrape_albums.php');
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scrape Albums - SoshiSpotify</title>
    <link rel="icon" type="image/png" href="PROFILE.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <?php $activePage = 'scrape_albums'; include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <header class="topbar">
                <div class="topbar-left">
                    <h1>Scrape Album Data</h1>
                </div>
            </header>

            <div class="card">
                <div class="card-header">
                    <h2>Get Album Data from Spotify</h2>
                    <p>This will scrape release dates and tracklists for albums that don't have release_date set.</p>
                </div>
                <div class="card-body">
                    <form method="POST" action="scrape_albums.php" class="scrape-form">
                        <div class="form-group">
                            <label for="client_token">Client Token <span class="required">*</span></label>
                            <input type="text" id="client_token" name="client_token" required 
                                   placeholder="Get from Network tab in browser DevTools">
                            <small>Open Spotify Web → DevTools → Network → Look for 'query' request → Copy 'client-token' header</small>
                        </div>

                        <div class="form-group">
                            <label for="auth_bearer">Authorization Bearer <span class="required">*</span></label>
                            <input type="text" id="auth_bearer" name="auth_bearer" required 
                                   placeholder="Get from Network tab in browser DevTools">
                            <small>Copy the token after 'Bearer ' from the 'authorization' header</small>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="scrape_albums" class="btn btn-primary">
                                <i class="fas fa-play"></i> Start Scraping Albums
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (!empty($logMessage)): ?>
            <div class="card">
                <div class="card-header">
                    <h2>Log Output</h2>
                </div>
                <div class="log-body">
                    <div class="log-output">
                        <pre><?php echo htmlspecialchars($logMessage); ?></pre>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h2>Instructions</h2>
                </div>
                <div class="card-body">
                    <ol>
                        <li>Open Spotify Web Player in your browser</li>
                        <li>Open Developer Tools (F12) and go to Network tab</li>
                        <li>Visit any album page on Spotify</li>
                        <li>Look for requests to <code>api-partner.spotify.com/pathfinder/v2/query</code></li>
                        <li>Copy the <code>client-token</code> and <code>authorization</code> headers</li>
                        <li>Paste them in the form above and click "Start Scraping Albums"</li>
                    </ol>
                    <p><strong>Note:</strong> This will process all albums in your database that don't have a release_date set.</p>
                    <p><strong>Track ID Handling:</strong> If a track_id has "?si..." at the end and causes a foreign key error, the system will try to find a matching track_id without the suffix.</p>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
