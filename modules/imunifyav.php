<?php
/**
 * ImunifyAV Module for CentOS Web Panel
 * Version: 1.0
 * Author: CWP Module Developer
 */

if (!isset($include_path)) {
    echo "invalid access";
    exit();
}

// Load language file
if (!isset($_GET['lang'])) {
    $lang_file = '/usr/local/cwpsrv/htdocs/resources/admin/modules/language/en/imunifyav.ini';
    if (!file_exists($lang_file)) {
        if (!file_exists('/usr/local/cwpsrv/htdocs/resources/admin/modules/language/en/')) {
            shell_exec('mkdir -p /usr/local/cwpsrv/htdocs/resources/admin/modules/language/en/');
        }
        // Create default language file
        $default_lang = <<<EOL
TITLE="ImunifyAV Scanner"
SCAN_DIRECTORY="Scan Directory"
START_SCAN="Start Scan"
SCANNING="Scanning..."
SCAN_COMPLETE="Scan Complete"
INFECTED_FILES="Infected Files"
WHITELIST="Whitelist Management"
ADD_TO_WHITELIST="Add to Whitelist"
REMOVE_FROM_WHITELIST="Remove from Whitelist"
EXPORT_REPORT="Export Report"
SCHEDULE_SCAN="Schedule Scan"
SCAN_FREQUENCY="Scan Frequency"
DAILY="Daily"
WEEKLY="Weekly"
MONTHLY="Monthly"
SAVE_SCHEDULE="Save Schedule"
LAST_SCAN="Last Scan"
STATUS="Status"
PATH="Path"
THREAT="Threat"
ACTION="Action"
NO_THREATS="No threats found"
SELECT_DIRECTORY="Select directory to scan"
WHITELIST_PATH="Path to whitelist"
SCHEDULED_SCANS="Scheduled Scans"
SCAN_REPORTS="Scan Reports"
QUICK_SCAN="Quick Scan"
FULL_SCAN="Full Scan"
CUSTOM_SCAN="Custom Scan"
VIEW_DETAILS="View Details"
DELETE="Delete"
CLEAN="Clean"
QUARANTINE="Quarantine"
IGNORE="Ignore"
EOL;
        file_put_contents($lang_file, $default_lang);
    }
    $lang = parse_ini_file($lang_file);
} else {
    $lang = parse_ini_file('/usr/local/cwpsrv/htdocs/resources/admin/modules/language/'.$_GET['lang'].'/imunifyav.ini');
}

// Check if ImunifyAV is installed
$imunify_installed = shell_exec("command -v imunify-antivirus 2>/dev/null");
if (empty($imunify_installed)) {
    echo '<div class="alert alert-danger">
            <strong>ImunifyAV is not installed!</strong><br>
            Please run the installation script first: <code>bash install_imunifyav.sh</code>
          </div>';
    exit();
}

// Load whitelist
$whitelist_file = '/etc/sysconfig/imunify360/whitelist.json';
$whitelist = [];
if (file_exists($whitelist_file)) {
    $whitelist_content = file_get_contents($whitelist_file);
    $whitelist_data = json_decode($whitelist_content, true);
    $whitelist = $whitelist_data['items'] ?? [];
}

// Load scheduled scans
$schedule_file = '/var/log/imunifyav_cwp/schedule.json';
$scheduled_scans = [];
if (file_exists($schedule_file)) {
    $schedule_content = file_get_contents($schedule_file);
    $scheduled_scans = json_decode($schedule_content, true) ?? [];
}
?>

<style>
    .imunify-container {
        padding: 20px;
    }
    .scan-controls {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    .scan-results {
        margin-top: 20px;
    }
    .threat-item {
        padding: 10px;
        border-bottom: 1px solid #dee2e6;
    }
    .threat-item:hover {
        background-color: #f8f9fa;
    }
    .status-badge {
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 12px;
    }
    .status-clean { background-color: #28a745; color: white; }
    .status-infected { background-color: #dc3545; color: white; }
    .status-scanning { background-color: #ffc107; color: black; }
    .whitelist-item {
        padding: 5px 10px;
        background: #e9ecef;
        margin: 5px 0;
        border-radius: 3px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .tab-content {
        padding: 20px;
        background: white;
        border: 1px solid #dee2e6;
        border-top: none;
    }
</style>

<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title"><?=$lang['TITLE']?></h3>
    </div>
    <div class="panel-body">
        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs" role="tablist">
            <li class="active"><a href="#scan" data-toggle="tab">Scanner</a></li>
            <li><a href="#reports" data-toggle="tab"><?=$lang['SCAN_REPORTS']?></a></li>
            <li><a href="#whitelist" data-toggle="tab"><?=$lang['WHITELIST']?></a></li>
            <li><a href="#schedule" data-toggle="tab"><?=$lang['SCHEDULED_SCANS']?></a></li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content">
            <!-- Scanner Tab -->
            <div class="tab-pane active" id="scan">
                <div class="scan-controls">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label><?=$lang['SELECT_DIRECTORY']?>:</label>
                                <div class="input-group">
                                    <input type="text" id="scan_path" class="form-control" 
                                           placeholder="/home" value="/home">
                                    <span class="input-group-btn">
                                        <button class="btn btn-default" onclick="selectDirectory()">
                                            <i class="fa fa-folder-open"></i>
                                        </button>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label>&nbsp;</label>
                            <div>
                                <button class="btn btn-primary" onclick="startScan('quick')">
                                    <i class="fa fa-bolt"></i> <?=$lang['QUICK_SCAN']?>
                                </button>
                                <button class="btn btn-success" onclick="startScan('full')">
                                    <i class="fa fa-search"></i> <?=$lang['FULL_SCAN']?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Common directories shortcuts -->
                    <div class="btn-group btn-group-sm" style="margin-top: 10px;">
                        <button class="btn btn-default" onclick="setScanPath('/home')">
                            <i class="fa fa-home"></i> /home
                        </button>
                        <button class="btn btn-default" onclick="setScanPath('/var/www')">
                            <i class="fa fa-globe"></i> /var/www
                        </button>
                        <button class="btn btn-default" onclick="setScanPath('/tmp')">
                            <i class="fa fa-folder"></i> /tmp
                        </button>
                        <button class="btn btn-default" onclick="setScanPath('/usr/local/cwpsrv')">
                            <i class="fa fa-cog"></i> CWP Directory
                        </button>
                    </div>
                </div>

                <!-- Scan Progress -->
                <div id="scan_progress" style="display:none;">
                    <div class="progress">
                        <div id="scan_progress_bar" class="progress-bar progress-bar-striped active" 
                             role="progressbar" style="width: 0%">
                            <span id="scan_progress_text">0%</span>
                        </div>
                    </div>
                    <div id="scan_status" class="text-center" style="margin-top: 10px;">
                        <span class="status-badge status-scanning"><?=$lang['SCANNING']?></span>
                    </div>
                </div>

                <!-- Scan Results -->
                <div id="scan_results" class="scan-results" style="display:none;">
                    <h4><?=$lang['SCAN_COMPLETE']?></h4>
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i> 
                        <span id="scan_summary"></span>
                    </div>
                    <div id="infected_files_list"></div>
                </div>
            </div>

            <!-- Reports Tab -->
            <div class="tab-pane" id="reports">
                <div class="row">
                    <div class="col-md-12">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Path</th>
                                    <th>Files Scanned</th>
                                    <th>Threats Found</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="reports_list">
                                <tr>
                                    <td colspan="5" class="text-center">Loading reports...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Whitelist Tab -->
            <div class="tab-pane" id="whitelist">
                <div class="row">
                    <div class="col-md-6">
                        <h4><?=$lang['ADD_TO_WHITELIST']?></h4>
                        <div class="form-group">
                            <input type="text" id="whitelist_path" class="form-control" 
                                   placeholder="<?=$lang['WHITELIST_PATH']?>">
                        </div>
                        <button class="btn btn-success" onclick="addToWhitelist()">
                            <i class="fa fa-plus"></i> <?=$lang['ADD_TO_WHITELIST']?>
                        </button>
                    </div>
                    <div class="col-md-6">
                        <h4>Current Whitelist</h4>
                        <div id="whitelist_items">
                            <?php foreach ($whitelist as $item): ?>
                            <div class="whitelist-item">
                                <span><?=htmlspecialchars($item)?></span>
                                <button class="btn btn-xs btn-danger" 
                                        onclick="removeFromWhitelist('<?=htmlspecialchars($item)?>')">
                                    <i class="fa fa-times"></i>
                                </button>
                            </div>
                            <?php endforeach; ?>
                            <?php if (empty($whitelist)): ?>
                            <p class="text-muted">No items in whitelist</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Schedule Tab -->
            <div class="tab-pane" id="schedule">
                <div class="row">
                    <div class="col-md-6">
                        <h4><?=$lang['SCHEDULE_SCAN']?></h4>
                        <form id="schedule_form">
                            <div class="form-group">
                                <label>Path to scan:</label>
                                <input type="text" id="schedule_path" class="form-control" 
                                       value="/home" required>
                            </div>
                            <div class="form-group">
                                <label><?=$lang['SCAN_FREQUENCY']?>:</label>
                                <select id="schedule_frequency" class="form-control">
                                    <option value="daily"><?=$lang['DAILY']?></option>
                                    <option value="weekly"><?=$lang['WEEKLY']?></option>
                                    <option value="monthly"><?=$lang['MONTHLY']?></option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Time (Hour):</label>
                                <select id="schedule_hour" class="form-control">
                                    <?php for($i = 0; $i < 24; $i++): ?>
                                    <option value="<?=$i?>"><?=sprintf("%02d:00", $i)?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <button type="button" class="btn btn-primary" onclick="saveSchedule()">
                                <i class="fa fa-save"></i> <?=$lang['SAVE_SCHEDULE']?>
                            </button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <h4>Active Schedules</h4>
                        <div id="schedule_list">
                            <?php if (!empty($scheduled_scans)): ?>
                                <?php foreach ($scheduled_scans as $schedule): ?>
                                <div class="panel panel-default">
                                    <div class="panel-body">
                                        <strong>Path:</strong> <?=htmlspecialchars($schedule['path'])?><br>
                                        <strong>Frequency:</strong> <?=htmlspecialchars($schedule['frequency'])?><br>
                                        <strong>Time:</strong> <?=sprintf("%02d:00", $schedule['hour'])?><br>
                                        <button class="btn btn-xs btn-danger" 
                                                onclick="removeSchedule('<?=htmlspecialchars($schedule['id'])?>')">
                                            Remove
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">No scheduled scans</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Global variables
var scanInterval;
var currentScanId;

// Set scan path
function setScanPath(path) {
    document.getElementById('scan_path').value = path;
}

// Start scan
function startScan(type) {
    var path = document.getElementById('scan_path').value;
    if (!path) {
        alert('Please select a directory to scan');
        return;
    }

    // Show progress
    document.getElementById('scan_progress').style.display = 'block';
    document.getElementById('scan_results').style.display = 'none';

    // Start scan via AJAX
    $.ajax({
        url: '/admin/addons/ajax/ajax_imunifyav.php',
        type: 'POST',
        data: {
            action: 'start_scan',
            path: path,
            type: type
        },
        success: function(response) {
            var data = JSON.parse(response);
            if (data.success) {
                currentScanId = data.scan_id;
                checkScanProgress();
            } else {
                alert('Error starting scan: ' + data.error);
                document.getElementById('scan_progress').style.display = 'none';
            }
        },
        error: function() {
            alert('Failed to start scan');
            document.getElementById('scan_progress').style.display = 'none';
        }
    });
}

// Check scan progress
function checkScanProgress() {
    scanInterval = setInterval(function() {
        $.ajax({
            url: '/admin/addons/ajax/ajax_imunifyav.php',
            type: 'POST',
            data: {
                action: 'check_progress',
                scan_id: currentScanId
            },
            success: function(response) {
                var data = JSON.parse(response);
                updateProgressBar(data.progress);
                
                if (data.status === 'completed') {
                    clearInterval(scanInterval);
                    showScanResults(data.results);
                }
            }
        });
    }, 2000);
}

// Update progress bar
function updateProgressBar(progress) {
    document.getElementById('scan_progress_bar').style.width = progress + '%';
    document.getElementById('scan_progress_text').textContent = progress + '%';
}

// Show scan results
function showScanResults(results) {
    document.getElementById('scan_progress').style.display = 'none';
    document.getElementById('scan_results').style.display = 'block';
    
    var summary = 'Scanned ' + results.total_files + ' files. ';
    if (results.infected_files.length > 0) {
        summary += 'Found ' + results.infected_files.length + ' threats.';
        
        var html = '<table class="table table-striped">';
        html += '<thead><tr><th>File</th><th>Threat</th><th>Actions</th></tr></thead>';
        html += '<tbody>';
        
        results.infected_files.forEach(function(file) {
            html += '<tr>';
            html += '<td>' + file.path + '</td>';
            html += '<td><span class="label label-danger">' + file.threat + '</span></td>';
            html += '<td>';
            html += '<button class="btn btn-xs btn-success" onclick="cleanFile(\'' + file.id + '\')">Clean</button> ';
            html += '<button class="btn btn-xs btn-warning" onclick="quarantineFile(\'' + file.id + '\')">Quarantine</button> ';
            html += '<button class="btn btn-xs btn-default" onclick="ignoreFile(\'' + file.path + '\')">Ignore</button>';
            html += '</td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        document.getElementById('infected_files_list').innerHTML = html;
    } else {
        summary += 'No threats found.';
        document.getElementById('infected_files_list').innerHTML = '<p class="text-success">All files are clean!</p>';
    }
    
    document.getElementById('scan_summary').textContent = summary;
    
    // Add export button
    document.getElementById('infected_files_list').innerHTML += 
        '<button class="btn btn-info" onclick="exportReport(\'' + currentScanId + '\')">' +
        '<i class="fa fa-download"></i> Export Report</button>';
}

// Clean infected file
function cleanFile(fileId) {
    if (confirm('Are you sure you want to clean this file?')) {
        $.ajax({
            url: '/admin/addons/ajax/ajax_imunifyav.php',
            type: 'POST',
            data: {
                action: 'clean_file',
                file_id: fileId
            },
            success: function(response) {
                var data = JSON.parse(response);
                if (data.success) {
                    alert('File cleaned successfully');
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            }
        });
    }
}

// Quarantine infected file
function quarantineFile(fileId) {
    if (confirm('Are you sure you want to quarantine this file?')) {
        $.ajax({
            url: '/admin/addons/ajax/ajax_imunifyav.php',
            type: 'POST',
            data: {
                action: 'quarantine_file',
                file_id: fileId
            },
            success: function(response) {
                var data = JSON.parse(response);
                if (data.success) {
                    alert('File quarantined successfully');
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            }
        });
    }
}

// Add to whitelist
function addToWhitelist() {
    var path = document.getElementById('whitelist_path').value;
    if (!path) {
        alert('Please enter a path');
        return;
    }
    
    $.ajax({
        url: '/admin/addons/ajax/ajax_imunifyav.php',
        type: 'POST',
        data: {
            action: 'add_whitelist',
            path: path
        },
        success: function(response) {
            var data = JSON.parse(response);
            if (data.success) {
                alert('Added to whitelist');
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        }
    });
}

// Remove from whitelist
function removeFromWhitelist(path) {
    if (confirm('Remove this path from whitelist?')) {
        $.ajax({
            url: '/admin/addons/ajax/ajax_imunifyav.php',
            type: 'POST',
            data: {
                action: 'remove_whitelist',
                path: path
            },
            success: function(response) {
                var data = JSON.parse(response);
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            }
        });
    }
}

// Save schedule
function saveSchedule() {
    var path = document.getElementById('schedule_path').value;
    var frequency = document.getElementById('schedule_frequency').value;
    var hour = document.getElementById('schedule_hour').value;
    
    if (!path) {
        alert('Please enter a path to scan');
        return;
    }
    
    $.ajax({
        url: '/admin/addons/ajax/ajax_imunifyav.php',
        type: 'POST',
        data: {
            action: 'save_schedule',
            path: path,
            frequency: frequency,
            hour: hour
        },
        success: function(response) {
            var data = JSON.parse(response);
            if (data.success) {
                alert('Schedule saved successfully');
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        }
    });
}

// Export report
function exportReport(scanId) {
    window.open('/admin/addons/ajax/ajax_imunifyav.php?action=export_report&scan_id=' + scanId, '_blank');
}

// Load reports on page load
$(document).ready(function() {
    loadReports();
});

// Load scan reports
function loadReports() {
    $.ajax({
        url: '/admin/addons/ajax/ajax_imunifyav.php',
        type: 'POST',
        data: {
            action: 'get_reports'
        },
        success: function(response) {
            var data = JSON.parse(response);
            if (data.success && data.reports.length > 0) {
                var html = '';
                data.reports.forEach(function(report) {
                    html += '<tr>';
                    html += '<td>' + report.date + '</td>';
                    html += '<td>' + report.path + '</td>';
                    html += '<td>' + report.files_scanned + '</td>';
                    html += '<td>' + (report.threats_found || 0) + '</td>';
                    html += '<td>';
                    html += '<button class="btn btn-xs btn-info" onclick="viewReport(\'' + report.id + '\')">View</button> ';
                    html += '<button class="btn btn-xs btn-success" onclick="exportReport(\'' + report.id + '\')">Export</button>';
                    html += '</td>';
                    html += '</tr>';
                });
                document.getElementById('reports_list').innerHTML = html;
            } else {
                document.getElementById('reports_list').innerHTML = 
                    '<tr><td colspan="5" class="text-center">No reports available</td></tr>';
            }
        }
    });
}
</script>
