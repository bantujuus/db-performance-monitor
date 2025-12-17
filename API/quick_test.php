<?php
/**
 * Quick Alert Test
 * Temporarily lowers thresholds to trigger alerts immediately
 */

require_once 'config.php';

$conn = getMonitoringConnection();

echo "🧪 QUICK ALERT TEST\n\n";
echo "This will temporarily lower thresholds to trigger alerts with current usage.\n\n";

try {
    // Store original thresholds
    $stmt = $conn->query("SELECT config_key, config_value FROM monitoring_config");
    $original = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    echo "Original Thresholds:\n";
    echo "- Connections: {$original['connections_threshold']}%\n";
    echo "- Memory: {$original['memory_threshold']}%\n";
    echo "- Slow Queries: {$original['slow_queries_threshold']}\n";
    echo "- Storage: {$original['storage_threshold']}%\n\n";
    
    // Lower thresholds temporarily
    echo "⬇️ Lowering thresholds to trigger alerts...\n";
    $conn->exec("UPDATE monitoring_config SET config_value = '0.5' WHERE config_key = 'connections_threshold'");
    $conn->exec("UPDATE monitoring_config SET config_value = '1' WHERE config_key = 'memory_threshold'");
    $conn->exec("UPDATE monitoring_config SET config_value = '0' WHERE config_key = 'slow_queries_threshold'");
    $conn->exec("UPDATE monitoring_config SET config_value = '0' WHERE config_key = 'storage_threshold'");
    
    echo "✅ Thresholds lowered!\n\n";
    
    echo "🔔 Alerts will now trigger on your dashboard!\n";
    echo "👉 Check your monitoring dashboard: http://localhost:3000\n\n";
    
    echo "⏰ Waiting 30 seconds for you to see the alerts...\n";
    echo "(Thresholds will auto-restore after 30 seconds)\n\n";
    
    for ($i = 30; $i > 0; $i--) {
        echo "\rRestoring in {$i} seconds...";
        flush();
        sleep(1);
    }
    
    echo "\n\n⬆️ Restoring original thresholds...\n";
    $conn->exec("UPDATE monitoring_config SET config_value = '{$original['connections_threshold']}' WHERE config_key = 'connections_threshold'");
    $conn->exec("UPDATE monitoring_config SET config_value = '{$original['memory_threshold']}' WHERE config_key = 'memory_threshold'");
    $conn->exec("UPDATE monitoring_config SET config_value = '{$original['slow_queries_threshold']}' WHERE config_key = 'slow_queries_threshold'");
    $conn->exec("UPDATE monitoring_config SET config_value = '{$original['storage_threshold']}' WHERE config_key = 'storage_threshold'");
    
    echo "✅ Thresholds restored to original values!\n";
    echo "\n✨ Test complete! Alerts should disappear shortly.\n";
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
}
?>