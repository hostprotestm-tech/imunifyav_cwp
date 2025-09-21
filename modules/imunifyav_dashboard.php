<?php
/**
 * ImunifyAV Dashboard Module for CWP
 * Real-time monitoring and statistics
 */

if (!isset($include_path)) {
    echo "invalid access";
    exit();
}

// Get statistics from ImunifyAV
function getImunifyStats() {
    $stats = [];
    
    // Get version
    $version = shell_exec("imunify-antivirus version 2>/dev/null | head -n1");
    $stats['version'] = trim($version);
    
    // Get database update date
    $db_info = shell_exec("imunify-antivirus update status 2>/dev/null");
    if (preg_match('/Last update: (.+)/', $db_info, $matches)) {
        $stats['db_updated'] = $matches[1];
    } else {
        $stats['db_updated'] = 'Unknown';
    }
    
    // Get infected files count
    $infected_json = shell_exec("imunify-antivirus malware malicious list --json 2>/dev/null");
    $infected_data = json_decode($infected_json, true);
    $stats['infected_count'] = isset($infected_data['items']) ? count($infected_data['items']) : 0;
    
    // Get scan history from logs
    $reports_dir = '/var/log/imunifyav_cwp/reports';
    $stats['total_scans'] = 0;
    $stats['last_scan'] = 'Never';
    
    if (is_dir($reports_dir)) {
        $reports = glob($reports_dir . '/*.json');
        $stats['total_scans'] = count($reports);
        
        if (!empty($reports)) {
            $latest = array_reduce($reports, function($a, $b) {
                return filemtime($a) > filemtime($b) ? $a : $b;
            });
            
            $report_data = json_decode(file_get_contents($latest), true);
            $stats['last_scan'] = $report_data['date'] ?? date('Y-m-d H:i:s', filemtime($latest));
        }
    }
    
    // Get scheduled scans
    $schedule_file = '/var/log/imunifyav_cwp/schedule.json';
    $stats['scheduled_scans'] = 0;
    
    if (file_exists($schedule_file)) {
        $schedules = json_decode(file_get_contents($schedule_file), true);
        $stats['scheduled_scans'] = is_array($schedules) ? count($schedules) : 0;
    }
    
    // Get whitelist count
    $whitelist_file = '/etc/sysconfig/imunify360/whitelist.json';
    $stats['whitelist_count'] = 0;
    
    if (file_exists($whitelist_file)) {
        $whitelist = json_decode(file_get_contents($whitelist_file), true);
        $stats['whitelist_count'] = isset($whitelist['items']) ? count($whitelist['items']) : 0;
    }
    
    return $stats;
}

// Get recent scan activity
function getRecentActivity() {
    $activity = [];
    $reports_dir = '/var/log/imunifyav_cwp/reports';
    
    if (is_dir($reports_dir)) {
        $reports = glob($reports_dir . '/*.json');
        
        // Sort by modification time
        usort($reports, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        // Get last 10 activities
        $reports = array_slice($reports, 0, 10);
        
        foreach ($reports as $report_file) {
            $report = json_decode(file_get_contents($report_file), true);
            if ($report) {
                $activity[] = [
                    'date' => $report['date'] ?? date('Y-m-d H:i:s', filemtime($report_file)),
                    'path' => $report['path'] ?? 'Unknown',
                    'files' => $report['total_files'] ?? 0,
                    'threats' => count($report['infected_files'] ?? []),
                    'type' => $report['type'] ?? 'manual'
                ];
            }
        }
    }
    
    return $activity;
}

// Get threat trends (last 30 days)
function getThreatTrends() {
    $trends = [];
    $reports_dir = '/var/log/imunifyav_cwp/reports';
    
    if (is_dir($reports_dir)) {
        $reports = glob($reports_dir . '/*.json');
        $thirty_days_ago = strtotime('-30 days');
        
        foreach ($reports as $report_file) {
            if (filemtime($report_file) >= $thirty_days_ago) {
                $report = json_decode(file_get_contents($report_file), true);
                $date = date('Y-m-d', strtotime($report['date'] ?? date('Y-m-d', filemtime($report_file))));
                
                if (!isset($trends[$date])) {
                    $trends[$date] = 0;
                }
                
                $trends[$date] += count($report['infected_files'] ?? []);
            }
        }
    }
    
    ksort($trends);
    return $trends;
}

// Get system status
function getSystemStatus() {
    $status = [];
    
    // Check ImunifyAV service
    $service_status = shell_exec("systemctl is-active imunify-antivirus 2>/dev/null");
    $status['service'] = (trim($service_status) === 'active') ? 'running' : 'stopped';
    
    // Check CPU usage
    $load = sys_getloadavg();
    $status['load'] = $load[0];
    
    // Check disk usage for /var/log
    $disk_free = disk_free_space('/var/log');
    $disk_total = disk_total_space('/var/log');
    $status['disk_usage'] = round(($disk_total - $disk_free) / $disk_total * 100, 2);
    
    // Check memory usage
    $mem_info = file_get_contents('/proc/meminfo');
    preg_match('/MemTotal:\s+(\d+)/', $mem_info, $matches);
    $mem_total = $matches[1];
    preg_match('/MemAvailable:\s+(\d+)/', $mem_info, $matches);
    $mem_available = $matches[1];
    $status['memory_usage'] = round(($mem_total - $mem_available) / $mem_total * 100, 2);
    
    return $status;
}

// Get data
$stats = getImunifyStats();
$activity = getRecentActivity();
$trends = getThreatTrends();
$system_status = getSystemStatus();
?>

<style>
    .dashboard-container {
        padding: 20px;
    }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .stat-card {
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 20px;
        text-align: center;
        transition: transform 0.2s;
    }
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .stat-number {
        font-size: 32px;
        font-weight: bold;
        color: #333;
    }
    .stat-label {
        color: #6c757d;
        margin-top: 5px;
        font-size: 14px;
    }
    .stat-icon {
        font-size: 24px;
        margin-bottom: 10px;
    }
    .status-indicator {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-right: 5px;
    }
    .status-running { background: #28a745; }
    .status-stopped { background: #dc3545; }
    .status-warning { background: #ffc107; }
    
    .chart-container {
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .activity-table {
        width: 100%;
        background: #fff;
    }
    .activity-table th {
        background: #f8f9fa;
        padding: 10px;
        text-align: left;
        border-bottom: 2px solid #dee2e6;
    }
    .activity-table td {
        padding: 10px;
        border-bottom: 1px solid #dee2e6;
    }
    
    .threat-badge {
        background: #dc3545;
        color: white;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 12px;
    }
    .clean-badge {
        background: #28a745;
        color: white;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 12px;
    }
    
    .system-metrics {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        margin-top: 20px;
    }
    
    .metric-bar {
        height: 20px;
        background: #e9ecef;
        border-radius: 10px;
        overflow: hidden;
        margin-top: 5px;
    }
    .metric-fill {
        height: 100%;
        background: linear-gradient(90deg, #28a745, #ffc107, #dc3545);
        transition: width 0.3s;
    }
    
    .refresh-btn {
        float: right;
        margin-top: -40px;
    }
</style>

<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">
            <i class="fa fa-dashboard"></i> ImunifyAV Dashboard
            <button class="btn btn-sm btn-default refresh-btn" onclick="location.reload()">
                <i class="fa fa-refresh"></i> Refresh
            </button>
        </h3>
    </div>
    <div class="panel-body dashboard-container">
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="color: #007bff;">
                    <i class="fa fa-shield"></i>
                </div>
                <div class="stat-number"><?=$stats['infected_count']?></div>
                <div class="stat-label">Active Threats</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="color: #28a745;">
                    <i class="fa fa-search"></i>
                </div>
                <div class="stat-number"><?=$stats['total_scans']?></div>
                <div class="stat-label">Total Scans</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="color: #ffc107;">
                    <i class="fa fa-clock-o"></i>
                </div>
                <div class="stat-number"><?=$stats['scheduled_scans']?></div>
                <div class="stat-label">Scheduled Scans</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="color: #6c757d;">
                    <i class="fa fa-list"></i>
                </div>
                <div class="stat-number"><?=$stats['whitelist_count']?></div>
                <div class="stat-label">Whitelist Items</div>
            </div>
        </div>
        
        <!-- System Status -->
        <div class="chart-container">
            <h4><i class="fa fa-heartbeat"></i> System Status</h4>
            <div class="system-metrics">
                <div>
                    <strong>ImunifyAV Service:</strong>
                    <span class="status-indicator status-<?=$system_status['service'] === 'running' ? 'running' : 'stopped'?>"></span>
                    <?=ucfirst($system_status['service'])?>
                </div>
                
                <div>
                    <strong>Version:</strong> <?=$stats['version']?>
                </div>
                
                <div>
                    <strong>Database Updated:</strong> <?=$stats['db_updated']?>
                </div>
                
                <div>
                    <strong>Last Scan:</strong> <?=$stats['last_scan']?>
                </div>
            </div>
            
            <div class="system-metrics" style="margin-top: 20px;">
                <div>
                    <strong>CPU Load:</strong> <?=number_format($system_status['load'], 2)?>
                    <div class="metric-bar">
                        <div class="metric-fill" style="width: <?=min(100, $system_status['load'] * 25)?>%"></div>
                    </div>
                </div>
                
                <div>
                    <strong>Memory Usage:</strong> <?=$system_status['memory_usage']?>%
                    <div class="metric-bar">
                        <div class="metric-fill" style="width: <?=$system_status['memory_usage']?>%"></div>
                    </div>
                </div>
                
                <div>
                    <strong>Disk Usage (/var/log):</strong> <?=$system_status['disk_usage']?>%
                    <div class="metric-bar">
                        <div class="metric-fill" style="width: <?=$system_status['disk_usage']?>%"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Threat Trends Chart -->
        <?php if (!empty($trends)): ?>
        <div class="chart-container">
            <h4><i class="fa fa-line-chart"></i> Threat Trends (Last 30 Days)</h4>
            <canvas id="threatChart" height="80"></canvas>
        </div>
        <?php endif; ?>
        
        <!-- Recent Activity -->
        <div class="chart-container">
            <h4><i class="fa fa-history"></i> Recent Scan Activity</h4>
            <?php if (!empty($activity)): ?>
            <table class="activity-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Path</th>
                        <th>Type</th>
                        <th>Files Scanned</th>
                        <th>Threats</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activity as $item): ?>
                    <tr>
                        <td><?=htmlspecialchars($item['date'])?></td>
                        <td><?=htmlspecialchars($item['path'])?></td>
                        <td>
                            <?php if ($item['type'] === 'scheduled'): ?>
                                <i class="fa fa-clock-o"></i> Scheduled
                            <?php else: ?>
                                <i class="fa fa-user"></i> Manual
                            <?php endif; ?>
                        </td>
                        <td><?=number_format($item['files'])?></td>
                        <td>
                            <?php if ($item['threats'] > 0): ?>
                                <span class="threat-badge"><?=$item['threats']?> threats</span>
                            <?php else: ?>
                                <span class="clean-badge">Clean</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="text-muted">No recent activity</p>
            <?php endif; ?>
        </div>
        
        <!-- Quick Actions -->
        <div class="chart-container">
            <h4><i class="fa fa-bolt"></i> Quick Actions</h4>
            <div class="btn-group">
                <a href="index.php?module=imunifyav" class="btn btn-primary">
                    <i class="fa fa-search"></i> Start Scan
                </a>
                <a href="index.php?module=imunifyav#reports" class="btn btn-info">
                    <i class="fa fa-file-text"></i> View Reports
                </a>
                <a href="index.php?module=imunifyav#schedule" class="btn btn-warning">
                    <i class="fa fa-clock-o"></i> Schedule Scan
                </a>
                <button class="btn btn-success" onclick="updateDatabase()">
                    <i class="fa fa-refresh"></i> Update Database
                </button>
            </div>
        </div>
        
    </div>
</div>

<!-- Chart.js for trends -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<script>
// Draw threat trends chart
<?php if (!empty($trends)): ?>
var ctx = document.getElementById('threatChart').getContext('2d');
var threatChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?=json_encode(array_keys($trends))?>,
        datasets: [{
            label: 'Threats Detected',
            data: <?=json_encode(array_values($trends))?>,
            borderColor: 'rgb(220, 53, 69)',
            backgroundColor: 'rgba(220, 53, 69, 0.1)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});
<?php endif; ?>

// Update database function
function updateDatabase() {
    if (confirm('Update ImunifyAV malware database?')) {
        $.ajax({
            url: '/admin/addons/ajax/ajax_imunifyav.php',
            type: 'POST',
            data: {
                action: 'update_database'
            },
            beforeSend: function() {
                alert('Database update started. This may take a few minutes.');
            },
            success: function(response) {
                var data = JSON.parse(response);
                if (data.success) {
                    alert('Database updated successfully!');
                    location.reload();
                } else {
                    alert('Update failed: ' + data.error);
                }
            },
            error: function() {
                alert('Failed to start database update');
            }
        });
    }
}

// Auto-refresh every 60 seconds
setTimeout(function() {
    location.reload();
}, 60000);
</script>
