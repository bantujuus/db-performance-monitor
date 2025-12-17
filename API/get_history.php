<?php
require_once 'config.php';

$conn = getMonitoringConnection();

try {
    // Get the last 20 metrics records grouped by timestamp
    $stmt = $conn->query("
        SELECT 
            DATE_FORMAT(recorded_at, '%H:%i:%s') as timestamp,
            metric_name,
            metric_value,
            metric_unit
        FROM db_metrics
        ORDER BY recorded_at DESC
        LIMIT 100
    ");
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group metrics by timestamp
    $history = [];
    $grouped = [];
    
    foreach ($results as $row) {
        $ts = $row['timestamp'];
        if (!isset($grouped[$ts])) {
            $grouped[$ts] = [
                'timestamp' => $ts,
                'connections' => ['active' => 0, 'usage' => 0],
                'memory' => ['used' => 0, 'usage' => 0],
                'queries' => ['slow' => 0],
                'storage' => ['used' => 0, 'usage' => 0]
            ];
        }
        
        // Map metrics to their categories
        if ($row['metric_name'] === 'active_connections') {
            $grouped[$ts]['connections']['active'] = (int)$row['metric_value'];
            $grouped[$ts]['connections']['usage'] = (int)$row['metric_value']; // Will be recalculated
        } elseif ($row['metric_name'] === 'memory_usage') {
            $grouped[$ts]['memory']['used'] = (int)$row['metric_value'];
        } elseif ($row['metric_name'] === 'slow_queries') {
            $grouped[$ts]['queries']['slow'] = (int)$row['metric_value'];
        } elseif ($row['metric_name'] === 'storage_usage') {
            $grouped[$ts]['storage']['used'] = (float)$row['metric_value'];
        }
    }
    
    // Convert to array and limit to last 20 entries
    $history = array_values($grouped);
    $history = array_slice($history, 0, 20);
    
    echo json_encode([
        'success' => true,
        'history' => $history
    ]);
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>