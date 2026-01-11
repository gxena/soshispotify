<?php 
require_once 'config.php';

$queryResult = null;
$queryError = null;
$executedQuery = '';
$executionTime = 0;

// Execute query if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['query'])) {
    $query = trim($_POST['query']);
    $executedQuery = $query;
    
    if (!empty($query)) {
        try {
            $conn = getDBConnection();
            $startTime = microtime(true);
            
            // Execute query
            $result = $conn->query($query);
            
            $executionTime = round((microtime(true) - $startTime) * 1000, 2); // Convert to milliseconds
            
            if ($result === false) {
                $queryError = $conn->error;
            } elseif ($result === true) {
                // Query executed successfully (INSERT, UPDATE, DELETE, etc.)
                $queryResult = [
                    'type' => 'success',
                    'affected_rows' => $conn->affected_rows,
                    'message' => "Query executed successfully. Affected rows: " . $conn->affected_rows
                ];
            } else {
                // SELECT query - fetch results
                $queryResult = [
                    'type' => 'select',
                    'columns' => [],
                    'rows' => [],
                    'num_rows' => $result->num_rows
                ];
                
                // Get column names
                $fields = $result->fetch_fields();
                foreach ($fields as $field) {
                    $queryResult['columns'][] = $field->name;
                }
                
                // Fetch all rows
                while ($row = $result->fetch_assoc()) {
                    $queryResult['rows'][] = $row;
                }
                
                $result->free();
            }
            
            $conn->close();
        } catch (Exception $e) {
            $queryError = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SQL Query - SoshiSpotify</title>
    <link rel="icon" type="image/png" href="PROFILE.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .query-container {
            padding: 20px;
        }
        
        .query-header {
            margin-bottom: 30px;
        }
        
        .query-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
            color: #FF1493;
        }
        
        .query-header p {
            color: #666;
        }
        
        .two-column-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 1200px) {
            .two-column-layout {
                grid-template-columns: 1fr;
            }
        }
        
        .schema-section {
            background: #FFE4F2;
            border-radius: 12px;
            padding: 20px;
        }
        
        .schema-section h2 {
            font-size: 20px;
            margin-bottom: 15px;
            color: #FF1493;
        }
        
        .schema-image {
            width: 100%;
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            background: #fff;
            padding: 10px;
        }
        
        .query-section {
            background: #FFE4F2;
            border-radius: 12px;
            padding: 20px;
        }
        
        .query-section h2 {
            font-size: 20px;
            margin-bottom: 15px;
            color: #FF1493;
        }
        
        .query-form textarea {
            width: 100%;
            min-height: 150px;
            padding: 15px;
            background: #FFF0F8;
            border: 1px solid #FFB6D9;
            border-radius: 8px;
            color: #333;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            resize: vertical;
            margin-bottom: 15px;
        }
        
        .query-form textarea:focus {
            outline: none;
            border-color: #FF1493;
        }
        
        .query-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .btn-execute {
            background: #FF1493;
            color: #fff;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-execute:hover {
            background: #FF69B4;
            transform: scale(1.05);
        }
        
        .btn-clear {
            background: #FFB6D9;
            color: #333;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-clear:hover {
            background: #FFC7E3;
        }
        
        .execution-info {
            color: #666;
            font-size: 13px;
        }
        
        .result-section {
            background: #FFE4F2;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .result-header h2 {
            font-size: 20px;
            color: #FF1493;
        }
        
        .result-stats {
            color: #FF1493;
            font-size: 14px;
        }
        
        .error-message {
            background: #ff4444;
            color: #fff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .success-message {
            background: #FF1493;
            color: #fff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .result-table {
            width: 100%;
            overflow-x: auto;
        }
        
        .result-table table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        .result-table th {
            background: #FFF0F8;
            color: #FF1493;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            position: sticky;
            top: 0;
            border-bottom: 2px solid #FFB6D9;
        }
        
        .result-table td {
            padding: 12px;
            border-bottom: 1px solid #FFE4F2;
            color: #333;
        }
        
        .result-table tr:hover td {
            background: #FFF0F8;
        }
        
        .no-results {
            text-align: center;
            padding: 30px;
            color: #999;
        }
        
        .example-queries {
            margin-top: 15px;
        }
        
        .example-query {
            background: #FFF0F8;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .example-query:hover {
            background: #FFB6D9;
        }
        
        .example-query-description {
            color: #FF1493;
            font-size: 14px;
            font-weight: 600;
            font-family: Arial, sans-serif;
        }
        
        .example-query-code {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: #666;
            margin-top: 8px;
            display: none;
            line-height: 1.6;
        }
        
        .example-query:hover .example-query-code {
            display: block;
        }
        
        .example-queries h3 {
            font-size: 16px;
            margin-bottom: 10px;
            color: #FF1493;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php $activePage = 'query'; include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="query-container">
                <div class="query-header">
                    <h1><i class="fas fa-database"></i> SQL Query Console</h1>
                    <p>Run custom SQL queries for flexible analytics. Use the database schema below as reference.</p>
                </div>

                <!-- Two Column Layout: Schema + Query -->
                <div class="two-column-layout">
                    <!-- Database Schema -->
                    <div class="schema-section">
                        <h2><i class="fas fa-sitemap"></i> Database Schema</h2>
                        <img src="DATABASE RELATION.png" alt="Database Schema" class="schema-image">
                    </div>

                    <!-- Query Form -->
                    <div class="query-section">
                        <h2><i class="fas fa-code"></i> SQL Query</h2>
                        <form method="POST" class="query-form">
                            <textarea name="query" id="queryInput" placeholder="Enter your SQL query here...&#10;&#10;Example:&#10;SELECT * FROM artist LIMIT 10;"><?php echo htmlspecialchars($executedQuery); ?></textarea>
                            <div class="query-actions">
                                <button type="submit" class="btn-execute">
                                    <i class="fas fa-play"></i> Execute Query
                                </button>
                                <button type="button" class="btn-clear" onclick="document.getElementById('queryInput').value = ''; document.getElementById('queryInput').focus();">
                                    <i class="fas fa-eraser"></i> Clear
                                </button>
                                <?php if ($executionTime > 0): ?>
                                    <span class="execution-info">
                                        <i class="fas fa-clock"></i> Execution time: <?php echo $executionTime; ?>ms
                                    </span>
                                <?php endif; ?>
                            </div>
                        </form>

                        <!-- Example Queries -->
                        <div class="example-queries">
                            <h3><i class="fas fa-lightbulb"></i> Example Queries (Click to use, Hover to preview)</h3>
                            <div class="example-query" onclick="setQuery('SELECT * FROM artist ORDER BY artist_name')">
                                <div class="example-query-description">## To see list of all artists</div>
                                <div class="example-query-code">SELECT * FROM artist ORDER BY artist_name</div>
                            </div>
                            <div class="example-query" onclick="setQuery('SELECT t.track_name, s.stream_count, s.stream_date\nFROM track t\nJOIN streams s ON t.track_id = s.track_id\nORDER BY s.stream_date DESC, s.stream_count DESC\nLIMIT 10')">
                                <div class="example-query-description">## To see top 10 latest tracked songs with stream counts</div>
                                <div class="example-query-code">
                                    SELECT t.track_name, s.stream_count, s.stream_date<br>
                                    FROM track t<br>
                                    JOIN streams s ON t.track_id = s.track_id<br>
                                    ORDER BY s.stream_date DESC, s.stream_count DESC<br>
                                    LIMIT 10
                                </div>
                            </div>
                            <div class="example-query" onclick="setQuery('SELECT a.artist_name, COUNT(DISTINCT t.track_id) as total_tracks\nFROM artist a\nLEFT JOIN track_artist ta ON a.artist_id = ta.artist_id\nLEFT JOIN track t ON ta.track_id = t.track_id\nGROUP BY a.artist_id, a.artist_name\nORDER BY total_tracks DESC')">
                                <div class="example-query-description">## To count tracks per artist</div>
                                <div class="example-query-code">
                                    SELECT a.artist_name, COUNT(DISTINCT t.track_id) as total_tracks<br>
                                    FROM artist a<br>
                                    LEFT JOIN track_artist ta ON a.artist_id = ta.artist_id<br>
                                    LEFT JOIN track t ON ta.track_id = t.track_id<br>
                                    GROUP BY a.artist_id, a.artist_name<br>
                                    ORDER BY total_tracks DESC
                                </div>
                            </div>
                            <div class="example-query" onclick="setQuery('SELECT artist_name, monthly_listeners, followers, stat_date\nFROM artist_stats ast\nJOIN artist a ON ast.artist_id = a.artist_id\nWHERE stat_date = (SELECT MAX(stat_date) FROM artist_stats)\nORDER BY monthly_listeners DESC')">
                                <div class="example-query-description">## To see latest monthly listeners and followers for all artists</div>
                                <div class="example-query-code">
                                    SELECT artist_name, monthly_listeners, followers, stat_date<br>
                                    FROM artist_stats ast<br>
                                    JOIN artist a ON ast.artist_id = a.artist_id<br>
                                    WHERE stat_date = (SELECT MAX(stat_date) FROM artist_stats)<br>
                                    ORDER BY monthly_listeners DESC
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Query Results -->
                <?php if ($queryError): ?>
                    <div class="result-section">
                        <div class="error-message">
                            <strong><i class="fas fa-exclamation-circle"></i> Error:</strong> <?php echo htmlspecialchars($queryError); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($queryResult): ?>
                    <div class="result-section">
                        <div class="result-header">
                            <h2><i class="fas fa-table"></i> Query Results</h2>
                            <div class="result-stats">
                                <?php if ($queryResult['type'] === 'select'): ?>
                                    <i class="fas fa-check-circle"></i> <?php echo $queryResult['num_rows']; ?> row(s) returned
                                <?php else: ?>
                                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($queryResult['message']); ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($queryResult['type'] === 'select'): ?>
                            <?php if ($queryResult['num_rows'] > 0): ?>
                                <div class="result-table">
                                    <table>
                                        <thead>
                                            <tr>
                                                <?php foreach ($queryResult['columns'] as $column): ?>
                                                    <th><?php echo htmlspecialchars($column); ?></th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($queryResult['rows'] as $row): ?>
                                                <tr>
                                                    <?php foreach ($row as $value): ?>
                                                        <td><?php 
                                                            if ($value === null) {
                                                                echo 'NULL';
                                                            } elseif (is_numeric($value) && floor($value) == $value) {
                                                                // Format integer numbers with thousand separator
                                                                echo number_format($value);
                                                            } else {
                                                                echo htmlspecialchars($value);
                                                            }
                                                        ?></td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="no-results">
                                    <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 10px; opacity: 0.5;"></i>
                                    <p>Query returned no results.</p>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="success-message">
                                <?php echo htmlspecialchars($queryResult['message']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function setQuery(query) {
            document.getElementById('queryInput').value = query;
            document.getElementById('queryInput').focus();
            // Scroll to textarea
            document.getElementById('queryInput').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    </script>
</body>
</html>
