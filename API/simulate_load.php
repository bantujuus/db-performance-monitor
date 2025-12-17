<?php
/**
 * Database Load Simulator
 * This script simulates high database usage to test the alert system
 */

require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Load Simulator</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2563eb;
            margin-bottom: 10px;
        }
        .warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            margin: 10px 5px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            text-decoration: none;
            color: white;
        }
        .btn-danger { background: #ef4444; }
        .btn-danger:hover { background: #dc2626; }
        .btn-warning { background: #f59e0b; }
        .btn-warning:hover { background: #d97706; }
        .btn-success { background: #10b981; }
        .btn-success:hover { background: #059669; }
        .btn-info { background: #3b82f6; }
        .btn-info:hover { background: #2563eb; }
        .result {
            background: #f0f9ff;
            border: 1px solid #3b82f6;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            white-space: pre-wrap;
        }
        .success { background: #dcfce7; border-color: #10b981; }
        .error { background: #fee2e2; border-color: #ef4444; }
        .info { background: #e0e7ff; border-color: #6366f1; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔥 Database Load Simulator</h1>
        <p>Test your monitoring system by simulating different load scenarios</p>
        
        <div class="warning">
            <strong>⚠️ Warning:</strong> These tests will create temporary load on your database. 
            Make sure you're on a test database!
        </div>

        <h2>Available Tests:</h2>
        
        <div style="margin: 20px 0;">
            <a href="?action=connections" class="button btn-danger">
                🔌 Simulate High Connections
            </a>
            <p style="margin: 5px 0 20px 0; color: #666;">Opens multiple database connections (alerts at 80%)</p>
        </div>

        <div style="margin: 20px 0;">
            <a href="?action=slow_queries" class="button btn-warning">
                🐌 Generate Slow Queries
            </a>
            <p style="margin: 5px 0 20px 0; color: #666;">Runs intentionally slow queries (alerts at 10 slow queries)</p>
        </div>

        <div style="margin: 20px 0;">
            <a href="?action=memory" class="button btn-info">
                💾 Simulate Memory Usage
            </a>
            <p style="margin: 5px 0 20px 0; color: #666;">Creates temporary tables to increase memory usage</p>
        </div>

        <div style="margin: 20px 0;">
            <a href="?action=all" class="button btn-danger">
                🚨 Run All Tests
            </a>
            <p style="margin: 5px 0 20px 0; color: #666;">Triggers multiple alerts simultaneously</p>
        </div>

        <div style="margin: 20px 0;">
            <a href="?action=cleanup" class="button btn-success">
                🧹 Cleanup & Reset
            </a>
            <p style="margin: 5px 0 20px 0; color: #666;">Close all connections and clean up test data</p>
        </div>

        <?php
        if (isset($_GET['action'])) {
            $action = $_GET['action'];
            echo '<div class="result">';
            
            switch($action) {
                case 'connections':
                    simulateConnections();
                    break;
                case 'slow_queries':
                    simulateSlowQueries();
                    break;
                case 'memory':
                    simulateMemoryUsage();
                    break;
                case 'all':
                    simulateConnections();
                    echo "<hr>";
                    simulateSlowQueries();
                    echo "<hr>";
                    simulateMemoryUsage();
                    break;
                case 'cleanup':
                    cleanup();
                    break;
                default:
                    echo "Unknown action";
            }
            
            echo '</div>';
            echo '<div class="info result">';
            echo '<strong>📊 Now check your monitoring dashboard!</strong><br>';
            echo 'Go to: <a href="http://localhost:3000" target="_blank">http://localhost:3000</a><br>';
            echo 'You should see alerts appearing!';
            echo '</div>';
        }
        ?>
    </div>
</body>
</html>

<?php

function simulateConnections() {
    echo "<h3>🔌 Simulating High Connection Load...</h3>\n";
    
    $connections = [];
    $targetConnections = 125; // This will trigger the 80% threshold (125/151 = 82.7%)
    
    try {
        echo "Opening {$targetConnections} database connections...\n";
        
        for ($i = 0; $i < $targetConnections; $i++) {
            $conn = new PDO(
                "mysql:host=" . TARGET_DB_HOST . ";dbname=" . TARGET_DB_NAME,
                TARGET_DB_USER,
                TARGET_DB_PASS
            );
            $connections[] = $conn;
            
            if ($i % 25 == 0) {
                echo "Opened {$i} connections...\n";
                flush();
            }
        }
        
        echo "\n✅ Successfully opened {$targetConnections} connections!\n";
        echo "Connection usage: " . round(($targetConnections / 151) * 100, 2) . "%\n";
        echo "\n⚠️ ALERT SHOULD BE TRIGGERED!\n";
        echo "\nKeeping connections open for 15 seconds...\n";
        
        // Keep connections open
        sleep(15);
        
        echo "\n🧹 Closing connections...\n";
        $connections = null; // Close all connections
        
        echo "✅ Cleanup complete!\n";
        
    } catch (Exception $e) {
        echo "\n❌ Error: " . $e->getMessage() . "\n";
    }
}

function simulateSlowQueries() {
    echo "<h3>🐌 Generating Slow Queries...</h3>\n";
    
    try {
        $conn = getTargetConnection();
        
        // First, set slow query time to 1 second temporarily
        $conn->exec("SET SESSION long_query_time = 1");
        
        echo "Running 15 intentionally slow queries...\n";
        
        for ($i = 1; $i <= 15; $i++) {
            echo "Running slow query {$i}/15...\n";
            flush();
            
            // Run a query that will take ~2 seconds
            $stmt = $conn->query("
                SELECT SLEEP(2), 
                       t1.*, t2.*
                FROM information_schema.TABLES t1
                CROSS JOIN information_schema.TABLES t2
                LIMIT 1
            ");
            
            $stmt->fetch();
        }
        
        echo "\n✅ Generated 15 slow queries!\n";
        echo "\n⚠️ SLOW QUERY ALERT SHOULD BE TRIGGERED!\n";
        echo "(Threshold: 10 slow queries)\n";
        
    } catch (Exception $e) {
        echo "\n❌ Error: " . $e->getMessage() . "\n";
    }
}

function simulateMemoryUsage() {
    echo "<h3>💾 Simulating Memory Usage...</h3>\n";
    
    try {
        $conn = getTargetConnection();
        
        echo "Creating temporary tables with large data...\n";
        
        // Create a temporary table with lots of data
        $conn->exec("
            CREATE TEMPORARY TABLE temp_load_test (
                id INT AUTO_INCREMENT PRIMARY KEY,
                data VARCHAR(1000),
                more_data TEXT,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=MEMORY
        ");
        
        echo "Inserting 10,000 rows into memory table...\n";
        
        for ($i = 0; $i < 100; $i++) {
            $conn->exec("
                INSERT INTO temp_load_test (data, more_data)
                SELECT 
                    REPEAT('X', 1000),
                    REPEAT('This is test data for memory simulation. ', 50)
                FROM information_schema.TABLES
                LIMIT 100
            ");
            
            if ($i % 20 == 0) {
                echo "Inserted " . ($i * 100) . " rows...\n";
                flush();
            }
        }
        
        echo "\n✅ Created large memory table!\n";
        echo "This will increase InnoDB buffer pool usage.\n";
        echo "\n⚠️ If memory usage was low, you might see an alert!\n";
        
        echo "\nKeeping data in memory for 10 seconds...\n";
        sleep(10);
        
        echo "\n🧹 Dropping temporary table...\n";
        $conn->exec("DROP TEMPORARY TABLE IF EXISTS temp_load_test");
        
        echo "✅ Cleanup complete!\n";
        
    } catch (Exception $e) {
        echo "\n❌ Error: " . $e->getMessage() . "\n";
    }
}

function cleanup() {
    echo "<h3>🧹 Cleaning Up...</h3>\n";
    
    try {
        $conn = getTargetConnection();
        
        echo "Dropping any temporary tables...\n";
        $conn->exec("DROP TEMPORARY TABLE IF EXISTS temp_load_test");
        
        echo "Closing all user connections...\n";
        // Note: We can't force close other connections, but we can clean up our own
        $conn = null;
        
        echo "\n✅ Cleanup complete!\n";
        echo "All test connections and temporary data removed.\n";
        
    } catch (Exception $e) {
        echo "\n❌ Error: " . $e->getMessage() . "\n";
    }
}
?>