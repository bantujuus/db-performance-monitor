import React, { useState, useEffect } from 'react';
import { Activity, Database, AlertTriangle, Clock, HardDrive, Zap, TrendingUp, CheckCircle, RefreshCw } from 'lucide-react';

const DatabaseMonitoringTool = () => {
  const [metrics, setMetrics] = useState({
    connections: { active: 0, max: 151, usage: 0 },
    memory: { used: 0, total: 1024, usage: 0 },
    queries: { slow: 0, total: 0 },
    storage: { used: 0, total: 100, usage: 0 }
  });
  
  const [alerts, setAlerts] = useState([]);
  const [history, setHistory] = useState([]);
  const [isMonitoring, setIsMonitoring] = useState(false);
  const [lastUpdate, setLastUpdate] = useState('Never');
  const [error, setError] = useState(null);
  const [thresholds, setThresholds] = useState({
    connections: 80,
    memory: 85,
    slow_queries: 10,
    storage: 90
  });

  // Fetch real metrics from PHP backend
  const collectMetrics = async () => {
    try {
      const response = await fetch('http://localhost/db-monitor-api/collect_metrics.php');
      const data = await response.json();
      
      if (data.success) {
        setMetrics(data.metrics);
        setThresholds(data.thresholds);
        setLastUpdate(new Date().toLocaleTimeString());
        setError(null);
        
        // Add alerts if any
        if (data.alerts && data.alerts.length > 0) {
          setAlerts(prev => {
            const newAlerts = data.alerts.map(alert => ({
              ...alert,
              id: Date.now() + Math.random()
            }));
            return [...newAlerts, ...prev].slice(0, 10);
          });
        }
        
        // Add to history
        const historyEntry = {
          timestamp: new Date().toLocaleTimeString(),
          ...data.metrics
        };
        setHistory(prev => [...prev.slice(-19), historyEntry]);
      } else {
        setError(data.error || 'Failed to fetch metrics');
      }
    } catch (err) {
      setError('Cannot connect to PHP backend. Make sure Apache and MySQL are running in XAMPP.');
      console.error('Fetch error:', err);
    }
  };

  useEffect(() => {
    let interval;
    if (isMonitoring) {
      collectMetrics(); // Initial fetch
      interval = setInterval(collectMetrics, 5000); // Every 5 seconds
    }
    return () => clearInterval(interval);
  }, [isMonitoring]);

  const MetricCard = ({ icon: Icon, title, value, max, usage, threshold, unit = '' }) => {
    const status = usage > threshold ? 'critical' : usage > threshold - 10 ? 'warning' : 'good';
    const colors = {
      critical: 'bg-red-500',
      warning: 'bg-yellow-500',
      good: 'bg-green-500'
    };

    return (
      <div className="bg-white rounded-lg shadow-md p-6 border-l-4" style={{ borderColor: usage > threshold ? '#ef4444' : usage > threshold - 10 ? '#eab308' : '#22c55e' }}>
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center gap-3">
            <Icon className="text-blue-600" size={24} />
            <h3 className="font-semibold text-gray-700">{title}</h3>
          </div>
          <span className={`px-2 py-1 rounded text-xs font-semibold text-white ${colors[status]}`}>
            {usage}%
          </span>
        </div>
        <div className="space-y-2">
          <div className="flex justify-between text-sm">
            <span className="text-gray-600">Current: {value}{unit}</span>
            <span className="text-gray-600">Max: {max}{unit}</span>
          </div>
          <div className="w-full bg-gray-200 rounded-full h-3">
            <div 
              className={`h-3 rounded-full transition-all duration-500 ${colors[status]}`}
              style={{ width: `${Math.min(usage, 100)}%` }}
            />
          </div>
        </div>
      </div>
    );
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 p-8">
      <div className="max-w-7xl mx-auto">
        {/* Header */}
        <div className="bg-white rounded-lg shadow-lg p-6 mb-8">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <Database className="text-blue-600" size={32} />
              <div>
                <h1 className="text-3xl font-bold text-gray-800">Database Performance Monitor</h1>
                <p className="text-gray-600">Real-time MySQL/MariaDB Monitoring System</p>
                <p className="text-sm text-gray-500 mt-1">Last updated: {lastUpdate}</p>
              </div>
            </div>
            <div className="flex gap-3">
              <button
                onClick={collectMetrics}
                disabled={!isMonitoring}
                className={`px-4 py-2 rounded-lg font-semibold transition-all flex items-center gap-2 ${
                  isMonitoring 
                    ? 'bg-blue-500 hover:bg-blue-600 text-white' 
                    : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                }`}
              >
                <RefreshCw size={18} />
                Refresh
              </button>
              <button
                onClick={() => setIsMonitoring(!isMonitoring)}
                className={`px-6 py-3 rounded-lg font-semibold transition-all ${
                  isMonitoring 
                    ? 'bg-red-500 hover:bg-red-600 text-white' 
                    : 'bg-green-500 hover:bg-green-600 text-white'
                }`}
              >
                {isMonitoring ? 'Stop Monitoring' : 'Start Monitoring'}
              </button>
            </div>
          </div>
          
          {error && (
            <div className="mt-4 p-4 bg-red-50 border-l-4 border-red-500 text-red-700">
              <p className="font-semibold">Error:</p>
              <p className="text-sm">{error}</p>
              <p className="text-xs mt-2">Make sure XAMPP Apache and MySQL are running!</p>
            </div>
          )}
        </div>

        {/* Metrics Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          <MetricCard
            icon={Activity}
            title="Connections"
            value={metrics.connections.active}
            max={metrics.connections.max}
            usage={metrics.connections.usage}
            threshold={thresholds.connections}
          />
          <MetricCard
            icon={Zap}
            title="Memory Usage"
            value={metrics.memory.used}
            max={metrics.memory.total}
            usage={metrics.memory.usage}
            threshold={thresholds.memory}
            unit="MB"
          />
          <MetricCard
            icon={Clock}
            title="Slow Queries"
            value={metrics.queries.slow}
            max={thresholds.slow_queries * 2}
            usage={Math.min((metrics.queries.slow / (thresholds.slow_queries * 2)) * 100, 100)}
            threshold={50}
          />
          <MetricCard
            icon={HardDrive}
            title="Storage"
            value={metrics.storage.used}
            max={metrics.storage.total}
            usage={metrics.storage.usage}
            threshold={thresholds.storage}
            unit="GB"
          />
        </div>

        {/* Alerts Panel */}
        <div className="bg-white rounded-lg shadow-lg p-6 mb-8">
          <div className="flex items-center gap-3 mb-4">
            <AlertTriangle className="text-orange-500" size={24} />
            <h2 className="text-xl font-bold text-gray-800">Active Alerts</h2>
            <span className="bg-red-500 text-white px-2 py-1 rounded-full text-xs font-semibold">
              {alerts.length}
            </span>
          </div>
          <div className="space-y-2 max-h-64 overflow-y-auto">
            {alerts.length === 0 ? (
              <div className="flex items-center gap-2 text-green-600 py-4">
                <CheckCircle size={20} />
                <span>No alerts - All systems operating normally</span>
              </div>
            ) : (
              alerts.map(alert => (
                <div 
                  key={alert.id}
                  className={`p-4 rounded-lg border-l-4 ${
                    alert.type === 'critical' 
                      ? 'bg-red-50 border-red-500' 
                      : 'bg-yellow-50 border-yellow-500'
                  }`}
                >
                  <div className="flex justify-between items-start">
                    <div>
                      <span className={`font-semibold ${
                        alert.type === 'critical' ? 'text-red-700' : 'text-yellow-700'
                      }`}>
                        {alert.metric}
                      </span>
                      <p className="text-gray-700 text-sm mt-1">{alert.message}</p>
                    </div>
                    <span className="text-xs text-gray-500">{alert.timestamp}</span>
                  </div>
                </div>
              ))
            )}
          </div>
        </div>

        {/* Performance History */}
        <div className="bg-white rounded-lg shadow-lg p-6">
          <div className="flex items-center gap-3 mb-4">
            <TrendingUp className="text-blue-600" size={24} />
            <h2 className="text-xl font-bold text-gray-800">Performance History</h2>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-4 py-3 text-left font-semibold text-gray-700">Time</th>
                  <th className="px-4 py-3 text-left font-semibold text-gray-700">Connections</th>
                  <th className="px-4 py-3 text-left font-semibold text-gray-700">Memory</th>
                  <th className="px-4 py-3 text-left font-semibold text-gray-700">Slow Queries</th>
                  <th className="px-4 py-3 text-left font-semibold text-gray-700">Storage</th>
                </tr>
              </thead>
              <tbody>
                {history.length === 0 ? (
                  <tr>
                    <td colSpan="5" className="px-4 py-8 text-center text-gray-500">
                      No data collected yet. Start monitoring to see history.
                    </td>
                  </tr>
                ) : (
                  history.slice(-10).reverse().map((entry, idx) => (
                    <tr key={idx} className="border-t hover:bg-gray-50">
                      <td className="px-4 py-3 text-gray-700">{entry.timestamp}</td>
                      <td className="px-4 py-3">
                        <span className={entry.connections.usage > thresholds.connections ? 'text-red-600 font-semibold' : 'text-gray-700'}>
                          {entry.connections.active} ({entry.connections.usage}%)
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        <span className={entry.memory.usage > thresholds.memory ? 'text-yellow-600 font-semibold' : 'text-gray-700'}>
                          {entry.memory.used}MB ({entry.memory.usage}%)
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        <span className={entry.queries.slow > thresholds.slow_queries ? 'text-yellow-600 font-semibold' : 'text-gray-700'}>
                          {entry.queries.slow}
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        <span className={entry.storage.usage > thresholds.storage ? 'text-red-600 font-semibold' : 'text-gray-700'}>
                          {entry.storage.used}GB ({entry.storage.usage}%)
                        </span>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>

        {/* Footer Info */}
        <div className="mt-8 text-center text-gray-600 text-sm">
          <p>Monitoring interval: 5 seconds | Thresholds: Connections {thresholds.connections}% | Memory {thresholds.memory}% | Slow Queries {thresholds.slow_queries} | Storage {thresholds.storage}%</p>
          <p className="mt-2 text-xs text-gray-500">Backend: PHP | Database: MySQL/MariaDB via XAMPP</p>
        </div>
      </div>
    </div>
  );
};

export default DatabaseMonitoringTool;