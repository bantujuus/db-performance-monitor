<?php
/**
 * Dashboard API - Additional endpoints for production monitoring
 */

require_once 'config.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'health':
        getHealthSummary();
        break;
    case 'history':
        getMetricHistory();
        break;
    case 'alerts':
        getRecentAlerts();
        break;
    case 'statistics':
        getStatistics();
        break;
    case 'config':
        getConfiguration();
        break;
    case 'update_threshold':
        updateThreshold();
        break;
    case 'resolve_alert':
        resolveAlert();
        break;
    case 'system_info':
        getSystemInfo();
        break;
    default:
        respondWithError('Invalid action', 400);
}

// ==================== ENDPOINT FUNCTIONS ====================

function getHealthSummary() {
    $conn = getMonitoringConnection();
    
    try {
        // Get recent alerts
        $stmt = $conn->query("
            SELECT 
                COUNT(*) as total_alerts,
                SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical,
                SUM(CASE WHEN severity = 'warning' THEN 1 ELSE 0 END) as warning
            FROM db_alerts
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                AND resolved = FALSE
        ");
        $alerts = $stmt->fetch();
        
        // Calculate health score
        $healthScore = 100;
        $healthScore -= ($alerts['critical'] * 25);
        $healthScore -= ($alerts['warning'] * 10);
        $healthScore = max(0, $healthScore);
        
        // Determine status
        $status = 'Excellent';
        if ($alerts['critical'] > 0) $status = 'Critical';
        elseif ($alerts['warning'] > 2) $status = 'Warning';
        elseif ($alerts['warning'] > 0) $status = 'Good';
        
        // Get uptime
        $targetConn = getTargetConnection();
        $stmt = $targetConn->query("SHOW STATUS LIKE 'Uptime'");
        $uptime = $stmt->fetch();
        
        respondWithSuccess([
            'health_score' => $healthScore,
            'status' => $status,
            'alerts' => $alerts,
            'uptime_seconds' => (int)$uptime['Value']
        ]);
        
    } catch (Exception $e) {
        logError("Health summary failed: " . $e->getMessage());
        respondWithError('Failed to get health summary', 500);
    }
}

function getMetricHistory() {
    $conn = getMonitoringConnection();
    $metric = isset($_GET['metric']) ? $_GET['metric'] : 'active_connections';
    $hours = isset($_GET['hours']) ? (int)$_GET['hours'] : 24;
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                metric_name,
                metric_value,
                metric_unit,
                status,
                DATE_FORMAT(recorded_at, '%Y-%m-%d %H:%i:%s') as timestamp
            FROM db_metrics
            WHERE metric_name = ?
                AND recorded_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            ORDER BY recorded_at DESC
            LIMIT 1000
        ");
        $stmt->execute([$metric, $hours]);
        $history = $stmt->fetchAll();
        
        respondWithSuccess([
            'metric' => $metric,
            'data_points' => count($history),
            'history' => $history
        ]);
        
    } catch (Exception $e) {
        logError("Metric history failed: " . $e->getMessage());
        respondWithError('Failed to get metric history', 500);
    }
}

function getRecentAlerts() {
    $conn = getMonitoringConnection();
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $resolved = isset($_GET['resolved']) ? $_GET['resolved'] === 'true' : false;
    
    try {
        $sql = "
            SELECT 
                id,
                alert_type,
                metric_name,
                alert_message,
                severity,
                resolved,
                DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as created_at,
                DATE_FORMAT(resolved_at, '%Y-%m-%d %H:%i:%s') as resolved_at
            FROM db_alerts
            WHERE resolved = ?
            ORDER BY created_at DESC
            LIMIT ?
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$resolved ? 1 : 0, $limit]);
        $alerts = $stmt->fetchAll();
        
        respondWithSuccess([
            'count' => count($alerts),
            'resolved' => $resolved,
            'alerts' => $alerts
        ]);
        
    } catch (Exception $e) {
        logError("Get alerts failed: " . $e->getMessage());
        respondWithError('Failed to get alerts', 500);
    }
}

function getStatistics() {
    $conn = getMonitoringConnection();
    
    try {
        // Alert statistics by day
        $stmt = $conn->query("
            SELECT 
                DATE(created_at) as date,
                severity,
                COUNT(*) as count
            FROM db_alerts
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at), severity
            ORDER BY date DESC
        ");
        $alertStats = $stmt->fetchAll();
        
        // Metric trends
        $stmt = $conn->query("
            SELECT 
                metric_name,
                COUNT(*) as sample_count,
                AVG(CAST(metric_value AS DECIMAL(10,2))) as avg_value,
                MIN(CAST(metric_value AS DECIMAL(10,2))) as min_value,
                MAX(CAST(metric_value AS DECIMAL(10,2))) as max_value
            FROM db_metrics
            WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY metric_name
        ");
        $metricTrends = $stmt->fetchAll();
        
        // Top issues
        $stmt = $conn->query("
            SELECT 
                metric_name,
                COUNT(*) as occurrence_count,
                MAX(severity) as max_severity
            FROM db_alerts
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY metric_name
            ORDER BY occurrence_count DESC
            LIMIT 5
        ");
        $topIssues = $stmt->fetchAll();
        
        respondWithSuccess([
            'alert_statistics' => $alertStats,
            'metric_trends' => $metricTrends,
            'top_issues' => $topIssues
        ]);
        
    } catch (Exception $e) {
        logError("Statistics failed: " . $e->getMessage());
        respondWithError('Failed to get statistics', 500);
    }
}

function getConfiguration() {
    $conn = getMonitoringConnection();
    
    try {
        $stmt = $conn->query("
            SELECT 
                config_key,
                config_value,
                description,
                config_type
            FROM monitoring_config
            ORDER BY config_type, config_key
        ");
        $config = $stmt->fetchAll();
        
        // Group by type
        $grouped = [];
        foreach ($config as $item) {
            $type = $item['config_type'];
            if (!isset($grouped[$type])) {
                $grouped[$type] = [];
            }
            $grouped[$type][] = $item;
        }
        
        respondWithSuccess([
            'configuration' => $grouped,
            'thresholds' => getThresholds()
        ]);
        
    } catch (Exception $e) {
        logError("Get configuration failed: " . $e->getMessage());
        respondWithError('Failed to get configuration', 500);
    }
}

function updateThreshold() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respondWithError('POST request required', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $key = isset($input['key']) ? $input['key'] : '';
    $value = isset($input['value']) ? $input['value'] : '';
    
    if (empty($key) || $value === '') {
        respondWithError('Key and value required', 400);
    }
    
    try {
        $conn = getMonitoringConnection();
        $stmt = $conn->prepare("
            UPDATE monitoring_config 
            SET config_value = ? 
            WHERE config_key = ?
        ");
        $stmt->execute([$value, $key]);
        
        logInfo("Threshold updated: {$key} = {$value}");
        
        // Log audit
        $stmt = $conn->prepare("
            INSERT INTO audit_log (action, details) 
            VALUES ('UPDATE_THRESHOLD', ?)
        ");
        $stmt->execute(["Updated {$key} to {$value}"]);
        
        respondWithSuccess(['message' => 'Threshold updated successfully']);
        
    } catch (Exception $e) {
        logError("Update threshold failed: " . $e->getMessage());
        respondWithError('Failed to update threshold', 500);
    }
}

function resolveAlert() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respondWithError('POST request required', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $alertId = isset($input['alert_id']) ? (int)$input['alert_id'] : 0;
    
    if ($alertId === 0) {
        respondWithError('Alert ID required', 400);
    }
    
    try {
        $conn = getMonitoringConnection();
        $stmt = $conn->prepare("
            UPDATE db_alerts 
            SET resolved = TRUE, resolved_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$alertId]);
        
        logInfo("Alert resolved: ID {$alertId}");
        
        respondWithSuccess(['message' => 'Alert resolved successfully']);
        
    } catch (Exception $e) {
        logError("Resolve alert failed: " . $e->getMessage());
        respondWithError('Failed to resolve alert', 500);
    }
}

function getSystemInfo() {
    try {
        $targetConn = getTargetConnection();
        
        // Server version
        $stmt = $targetConn->query("SELECT VERSION() as version");
        $version = $stmt->fetch();
        
        // Server variables
        $stmt = $targetConn->query("
            SELECT * FROM (
                SELECT 'max_connections' as var_name UNION
                SELECT 'innodb_buffer_pool_size' UNION
                SELECT 'query_cache_size' UNION
                SELECT 'max_allowed_packet' UNION
                SELECT 'thread_cache_size'
            ) vars
            JOIN information_schema.GLOBAL_VARIABLES v 
                ON vars.var_name = v.VARIABLE_NAME
        ");
        $variables = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Database list
        $stmt = $targetConn->query("
            SELECT 
                SCHEMA_NAME as name,
                DEFAULT_CHARACTER_SET_NAME as charset,
                DEFAULT_COLLATION_NAME as collation
            FROM information_schema.SCHEMATA
            WHERE SCHEMA_NAME NOT IN ('information_schema', 'performance_schema', 'mysql', 'sys')
        ");
        $databases = $stmt->fetchAll();
        
        respondWithSuccess([
            'version' => $version['version'],
            'host' => TARGET_DB_HOST,
            'port' => TARGET_DB_PORT,
            'variables' => $variables,
            'databases' => $databases
        ]);
        
    } catch (Exception $e) {
        logError("System info failed: " . $e->getMessage());
        respondWithError('Failed to get system info', 500);
    }
}
?>