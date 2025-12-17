<?php
/**
 * Production Database Metrics Collection
 * Collects comprehensive database performance metrics
 */

require_once 'config.php';

$monitorConn = getMonitoringConnection();
$targetConn = getTargetConnection();
$thresholds = getThresholds();

$metrics = [];
$alerts = [];
$healthScore = 100;

try {
    // ==================== 1. CONNECTION METRICS ====================
    $connectionMetrics = collectConnectionMetrics($targetConn, $thresholds['connections']);
    $metrics['connections'] = $connectionMetrics['data'];
    
    if ($connectionMetrics['alert']) {
        $alerts[] = $connectionMetrics['alert'];
        $healthScore -= 25;
    }
    
    storeMetric($monitorConn, 'active_connections', $connectionMetrics['data']['active'], 
                'count', $connectionMetrics['alert'] ? 'warning' : 'normal');
    
    // ==================== 2. MEMORY METRICS ====================
    $memoryMetrics = collectMemoryMetrics($targetConn, $thresholds['memory']);
    $metrics['memory'] = $memoryMetrics['data'];
    
    if ($memoryMetrics['alert']) {
        $alerts[] = $memoryMetrics['alert'];
        $healthScore -= 20;
    }
    
    storeMetric($monitorConn, 'memory_usage', $memoryMetrics['data']['used'], 
                'MB', $memoryMetrics['alert'] ? 'warning' : 'normal');
    
    // ==================== 3. QUERY PERFORMANCE METRICS ====================
    $queryMetrics = collectQueryMetrics($targetConn, $thresholds['slow_queries']);
    $metrics['queries'] = $queryMetrics['data'];
    
    if ($queryMetrics['alert']) {
        $alerts[] = $queryMetrics['alert'];
        $healthScore -= 15;
    }
    
    storeMetric($monitorConn, 'slow_queries', $queryMetrics['data']['slow'], 
                'count', $queryMetrics['alert'] ? 'warning' : 'normal');
    
    // ==================== 4. STORAGE METRICS ====================
    $storageMetrics = collectStorageMetrics($targetConn, $thresholds['storage']);
    $metrics['storage'] = $storageMetrics['data'];
    
    if ($storageMetrics['alert']) {
        $alerts[] = $storageMetrics['alert'];
        $healthScore -= 20;
    }
    
    storeMetric($monitorConn, 'storage_usage', $storageMetrics['data']['used'], 
                'GB', $storageMetrics['alert'] ? 'critical' : 'normal');
    
    // ==================== 5. ADDITIONAL METRICS ====================
    $metrics['uptime'] = getServerUptime($targetConn);
    $metrics['qps'] = getQueriesPerSecond($targetConn);
    $metrics['replication'] = getReplicationStatus($targetConn);
    
    // ==================== STORE ALERTS ====================
    foreach ($alerts as $alert) {
        storeAlert($monitorConn, $alert['type'], $alert['metric'], 
                  $alert['message'], $alert['severity']);
        
        // Send email for critical alerts
        if ($alert['severity'] === 'critical' && ENABLE_EMAIL_ALERTS) {
            sendEmailAlert(
                "CRITICAL: " . $alert['metric'],
                $alert['message']
            );
        }
    }
    
    // ==================== CLEANUP OLD DATA (every 100th request) ====================
    if (rand(1, 100) === 1) {
        cleanOldData();
    }
    
    // ==================== RESPONSE ====================
    echo json_encode([
        'success' => true,
        'metrics' => $metrics,
        'alerts' => $alerts,
        'thresholds' => $thresholds,
        'health_score' => max(0, $healthScore),
        'timestamp' => date('Y-m-d H:i:s'),
        'server_info' => [
            'host' => TARGET_DB_HOST,
            'port' => TARGET_DB_PORT,
            'version' => getServerVersion($targetConn)
        ]
    ]);
    
} catch(Exception $e) {
    logError("Metrics collection failed: " . $e->getMessage());
    respondWithError("Failed to collect metrics: " . $e->getMessage(), 500);
}

// ==================== METRIC COLLECTION FUNCTIONS ====================

function collectConnectionMetrics($conn, $threshold) {
    try {
        // Max connections
        $stmt = $conn->query("SHOW VARIABLES LIKE 'max_connections'");
        $maxConn = $stmt->fetch();
        $maxConnections = (int)$maxConn['Value'];
        
        // Active connections
        $stmt = $conn->query("SHOW STATUS LIKE 'Threads_connected'");
        $activeConn = $stmt->fetch();
        $activeConnections = (int)$activeConn['Value'];
        
        // Max used connections
        $stmt = $conn->query("SHOW STATUS LIKE 'Max_used_connections'");
        $maxUsed = $stmt->fetch();
        $maxUsedConnections = (int)$maxUsed['Value'];
        
        // Aborted connections
        $stmt = $conn->query("SHOW STATUS LIKE 'Aborted_connects'");
        $aborted = $stmt->fetch();
        $abortedConnections = (int)$aborted['Value'];
        
        $usage = round(($activeConnections / $maxConnections) * 100, 2);
        
        $data = [
            'active' => $activeConnections,
            'max' => $maxConnections,
            'max_used' => $maxUsedConnections,
            'aborted' => $abortedConnections,
            'usage' => $usage
        ];
        
        $alert = null;
        if ($usage > $threshold) {
            $alert = [
                'type' => 'critical',
                'metric' => 'Connections',
                'message' => "Connection usage at {$usage}% ({$activeConnections}/{$maxConnections})",
                'timestamp' => date('H:i:s'),
                'severity' => 'critical'
            ];
        }
        
        return ['data' => $data, 'alert' => $alert];
        
    } catch (Exception $e) {
        logError("Connection metrics failed: " . $e->getMessage());
        return ['data' => ['active' => 0, 'max' => 151, 'usage' => 0], 'alert' => null];
    }
}

function collectMemoryMetrics($conn, $threshold) {
    try {
        // InnoDB buffer pool size
        $stmt = $conn->query("SHOW VARIABLES LIKE 'innodb_buffer_pool_size'");
        $bufferPool = $stmt->fetch();
        $totalMemory = round((int)$bufferPool['Value'] / (1024 * 1024)); // MB
        
        // Buffer pool pages
        $stmt = $conn->query("SHOW STATUS LIKE 'Innodb_buffer_pool_pages_total'");
        $totalPages = $stmt->fetch();
        $total = (int)$totalPages['Value'];
        
        $stmt = $conn->query("SHOW STATUS LIKE 'Innodb_buffer_pool_pages_free'");
        $freePages = $stmt->fetch();
        $free = (int)$freePages['Value'];
        
        $used = $total - $free;
        $usedMemory = round(($used * 16) / 1024); // Each page is 16KB
        
        // Query cache (if enabled)
        $stmt = $conn->query("SHOW VARIABLES LIKE 'query_cache_size'");
        $queryCache = $stmt->fetch();
        $queryCacheSize = round((int)$queryCache['Value'] / (1024 * 1024));
        
        // Total memory calculation
        $totalMemory = max($totalMemory, $usedMemory);
        $usage = $totalMemory > 0 ? round(($usedMemory / $totalMemory) * 100, 2) : 0;
        
        $data = [
            'used' => $usedMemory,
            'total' => $totalMemory,
            'free' => $totalMemory - $usedMemory,
            'query_cache' => $queryCacheSize,
            'usage' => $usage
        ];
        
        $alert = null;
        if ($usage > $threshold) {
            $alert = [
                'type' => 'warning',
                'metric' => 'Memory',
                'message' => "Memory usage at {$usage}% ({$usedMemory}MB/{$totalMemory}MB)",
                'timestamp' => date('H:i:s'),
                'severity' => 'warning'
            ];
        }
        
        return ['data' => $data, 'alert' => $alert];
        
    } catch (Exception $e) {
        logError("Memory metrics failed: " . $e->getMessage());
        return ['data' => ['used' => 0, 'total' => 128, 'usage' => 0], 'alert' => null];
    }
}

function collectQueryMetrics($conn, $threshold) {
    try {
        // Slow queries
        $stmt = $conn->query("SHOW GLOBAL STATUS LIKE 'Slow_queries'");
        $slowQueries = $stmt->fetch();
        $slowCount = (int)$slowQueries['Value'];
        
        // Total queries
        $stmt = $conn->query("SHOW GLOBAL STATUS LIKE 'Questions'");
        $totalQueries = $stmt->fetch();
        $totalCount = (int)$totalQueries['Value'];
        
        // Select queries
        $stmt = $conn->query("SHOW GLOBAL STATUS LIKE 'Com_select'");
        $selects = $stmt->fetch();
        $selectCount = (int)$selects['Value'];
        
        // Insert queries
        $stmt = $conn->query("SHOW GLOBAL STATUS LIKE 'Com_insert'");
        $inserts = $stmt->fetch();
        $insertCount = (int)$inserts['Value'];
        
        // Update queries
        $stmt = $conn->query("SHOW GLOBAL STATUS LIKE 'Com_update'");
        $updates = $stmt->fetch();
        $updateCount = (int)$updates['Value'];
        
        // Delete queries
        $stmt = $conn->query("SHOW GLOBAL STATUS LIKE 'Com_delete'");
        $deletes = $stmt->fetch();
        $deleteCount = (int)$deletes['Value'];
        
        $data = [
            'slow' => $slowCount,
            'total' => $totalCount,
            'select' => $selectCount,
            'insert' => $insertCount,
            'update' => $updateCount,
            'delete' => $deleteCount
        ];
        
        $alert = null;
        if ($slowCount > $threshold) {
            $alert = [
                'type' => 'warning',
                'metric' => 'Slow Queries',
                'message' => "{$slowCount} slow queries detected (threshold: {$threshold})",
                'timestamp' => date('H:i:s'),
                'severity' => 'warning'
            ];
        }
        
        return ['data' => $data, 'alert' => $alert];
        
    } catch (Exception $e) {
        logError("Query metrics failed: " . $e->getMessage());
        return ['data' => ['slow' => 0, 'total' => 0], 'alert' => null];
    }
}

function collectStorageMetrics($conn, $threshold) {
    try {
        // User databases size in MB
        $stmt = $conn->query("
            SELECT 
                COALESCE(ROUND(SUM(data_length + index_length) / 1024 / 1024, 2), 0) as size_mb,
                COUNT(DISTINCT table_schema) as db_count,
                COUNT(*) as table_count
            FROM information_schema.TABLES
            WHERE table_schema NOT IN ('information_schema', 'performance_schema', 'mysql', 'sys')
        ");
        $userDb = $stmt->fetch();
        $userSizeMB = (float)$userDb['size_mb'];
        $dbCount = (int)$userDb['db_count'];
        $tableCount = (int)$userDb['table_count'];
        
        // System databases size in MB
        $stmt = $conn->query("
            SELECT 
                COALESCE(ROUND(SUM(data_length + index_length) / 1024 / 1024, 2), 0) as size_mb
            FROM information_schema.TABLES
            WHERE table_schema IN ('mysql', 'sys', 'performance_schema')
        ");
        $sysDb = $stmt->fetch();
        $systemSizeMB = (float)$sysDb['size_mb'];
        
        // Binary logs size in MB (if enabled)
        $binaryLogSizeMB = 0;
        try {
            $stmt = $conn->query("SHOW BINARY LOGS");
            $logs = $stmt->fetchAll();
            foreach ($logs as $log) {
                $binaryLogSizeMB += (int)$log['File_size'];
            }
            $binaryLogSizeMB = round($binaryLogSizeMB / 1024 / 1024, 2);
        } catch (Exception $e) {
            // Binary logs not enabled
        }
        
        $usedStorageMB = $userSizeMB + $systemSizeMB + $binaryLogSizeMB;
        
        // For very small databases, show a minimum value
        if ($usedStorageMB < 1) {
            $usedStorageMB = max(0.5, $usedStorageMB);
        }
        
        // Calculate total storage capacity
        $totalStorageMB = 10240; // 10 GB = 10,240 MB
        
        $usage = $totalStorageMB > 0 ? round(($usedStorageMB / $totalStorageMB) * 100, 2) : 0;
        
        // Get detailed breakdown by database
        $stmt = $conn->query("
            SELECT 
                table_schema as db_name,
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb,
                COUNT(*) as table_count
            FROM information_schema.TABLES
            WHERE table_schema NOT IN ('information_schema', 'performance_schema')
            GROUP BY table_schema
            ORDER BY SUM(data_length + index_length) DESC
            LIMIT 10
        ");
        $topDatabases = $stmt->fetchAll();
        
        $data = [
            'used' => $usedStorageMB,
            'total' => $totalStorageMB,
            'user_db' => $userSizeMB,
            'system_db' => $systemSizeMB,
            'binary_logs' => $binaryLogSizeMB,
            'db_count' => $dbCount,
            'table_count' => $tableCount,
            'usage' => $usage,
            'unit' => 'MB'
        ];
        
        $alert = null;
        if ($usage > $threshold) {
            $alert = [
                'type' => 'critical',
                'metric' => 'Storage',
                'message' => "Storage usage at {$usage}% ({$usedStorageMB}MB/{$totalStorageMB}MB)",
                'timestamp' => date('H:i:s'),
                'severity' => 'critical'
            ];
        }
        
        return ['data' => $data, 'alert' => $alert];
        
    } catch (Exception $e) {
        logError("Storage metrics failed: " . $e->getMessage());
        return ['data' => [
            'used' => 0.5, 
            'total' => 10240, 
            'usage' => 0.005,
            'unit' => 'MB'
        ], 'alert' => null];
    }
}
function getServerUptime($conn) {
    try {
        $stmt = $conn->query("SHOW STATUS LIKE 'Uptime'");
        $uptime = $stmt->fetch();
        $seconds = (int)$uptime['Value'];
        
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        return [
            'seconds' => $seconds,
            'formatted' => "{$days}d {$hours}h {$minutes}m"
        ];
    } catch (Exception $e) {
        return ['seconds' => 0, 'formatted' => 'Unknown'];
    }
}

function getQueriesPerSecond($conn) {
    try {
        $stmt = $conn->query("SHOW GLOBAL STATUS LIKE 'Questions'");
        $questions = $stmt->fetch();
        $totalQueries = (int)$questions['Value'];
        
        $stmt = $conn->query("SHOW STATUS LIKE 'Uptime'");
        $uptime = $stmt->fetch();
        $seconds = (int)$uptime['Value'];
        
        $qps = $seconds > 0 ? round($totalQueries / $seconds, 2) : 0;
        
        return $qps;
    } catch (Exception $e) {
        return 0;
    }
}

function getReplicationStatus($conn) {
    try {
        $stmt = $conn->query("SHOW SLAVE STATUS");
        $status = $stmt->fetch();
        
        if ($status) {
            return [
                'enabled' => true,
                'running' => $status['Slave_IO_Running'] === 'Yes' && $status['Slave_SQL_Running'] === 'Yes',
                'lag' => (int)$status['Seconds_Behind_Master']
            ];
        }
        
        return ['enabled' => false];
    } catch (Exception $e) {
        return ['enabled' => false];
    }
}

function getServerVersion($conn) {
    try {
        $stmt = $conn->query("SELECT VERSION() as version");
        $result = $stmt->fetch();
        return $result['version'];
    } catch (Exception $e) {
        return 'Unknown';
    }
}

// Helper functions
function storeMetric($conn, $name, $value, $unit, $status) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO db_metrics (metric_name, metric_value, metric_unit, status) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$name, $value, $unit, $status]);
    } catch (Exception $e) {
        logError("Failed to store metric '{$name}': " . $e->getMessage());
    }
}

function storeAlert($conn, $type, $metric, $message, $severity) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO db_alerts (alert_type, metric_name, alert_message, severity) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$type, $metric, $message, $severity]);
    } catch (Exception $e) {
        logError("Failed to store alert: " . $e->getMessage());
    }
}
?>