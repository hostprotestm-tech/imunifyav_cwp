<?php
/**
 * ImunifyAV AJAX Handler for CWP
 * Handles all AJAX requests from the ImunifyAV module
 */

// Security check
session_start();
if (!isset($_SESSION['cwp_admin'])) {
    die(json_encode(['success' => false, 'error' => 'Unauthorized access']));
}

// Include database connection
if (file_exists('/usr/local/cwpsrv/htdocs/resources/admin/include/db_conn.php')) {
    include_once('/usr/local/cwpsrv/htdocs/resources/admin/include/db_conn.php');
}

// Get action
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

// Create logs directory if not exists
if (!file_exists('/var/log/imunifyav_cwp')) {
    mkdir('/var/log/imunifyav_cwp', 0755, true);
}

switch ($action) {
    case 'start_scan':
        startScan();
        break;
    
    case 'check_progress':
        checkProgress();
        break;
    
    case 'clean_file':
        cleanFile();
        break;
    
    case 'quarantine_file':
        quarantineFile();
        break;
    
    case 'add_whitelist':
        addToWhitelist();
        break;
    
    case 'remove_whitelist':
        removeFromWhitelist();
        break;
    
    case 'save_schedule':
        saveSchedule();
        break;
    
    case 'remove_schedule':
        removeSchedule();
        break;
    
    case 'get_reports':
        getReports();
        break;
    
    case 'export_report':
        exportReport();
        break;
    
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

/**
 * Start malware scan
 */
function startScan() {
    $path = isset($_POST['path']) ? $_POST['path'] : '';
    $type = isset($_POST['type']) ? $_POST['type'] : 'quick';
    
    // Validate path
    if (empty($path) || !is_dir($path)) {
        echo json_encode(['success' => false, 'error' => 'Invalid path']);
        return;
    }
    
    // Generate scan ID
    $scan_id = uniqid('scan_');
    
    // Prepare scan command
    if ($type == 'quick') {
        // Quick scan - only check common malware locations
        $cmd = "imunify-antivirus malware on-demand start --path=$path --intensity=low --background";
    } else {
        // Full scan - comprehensive scan
        $cmd = "imunify-antivirus malware on-demand start --path=$path --intensity=high --background";
    }
    
    // Execute scan in background
    $output = shell_exec($cmd . " 2>&1");
    
    // Parse output to get scan task ID
    preg_match('/Task ID: (\d+)/', $output, $matches);
    $task_id = isset($matches[1]) ? $matches[1] : null;
    
    if ($task_id) {
        // Save scan info
        $scan_info = [
            'scan_id' => $scan_id,
            'task_id' => $task_id,
            'path' => $path,
            'type' => $type,
            'start_time' => date('Y-m-d H:i:s'),
            'status' => 'running',
            'progress' => 0
        ];
        
        file_put_contents(
            "/var/log/imunifyav_cwp/{$scan_id}.json",
            json_encode($scan_info)
        );
        
        echo json_encode(['success' => true, 'scan_id' => $scan_id, 'task_id' => $task_id]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to start scan']);
    }
}

/**
 * Check scan progress
 */
function checkProgress() {
    $scan_id = isset($_POST['scan_id']) ? $_POST['scan_id'] : '';
    
    if (empty($scan_id)) {
        echo json_encode(['success' => false, 'error' => 'Invalid scan ID']);
        return;
    }
    
    $scan_file = "/var/log/imunifyav_cwp/{$scan_id}.json";
    
    if (!file_exists($scan_file)) {
        echo json_encode(['success' => false, 'error' => 'Scan not found']);
        return;
    }
    
    $scan_info = json_decode(file_get_contents($scan_file), true);
    
    // Get scan status from ImunifyAV
    $cmd = "imunify-antivirus malware on-demand status --task-id={$scan_info['task_id']} --json 2>/dev/null";
    $output = shell_exec($cmd);
    $status_data = json_decode($output, true);
    
    if ($status_data) {
        $progress = isset($status_data['progress']) ? $status_data['progress'] : 0;
        $status = isset($status_data['status']) ? $status_data['status'] : 'running';
        
        // Update scan info
        $scan_info['progress'] = $progress;
        $scan_info['status'] = $status;
        
        if ($status == 'completed' || $status == 'finished') {
            // Get scan results
            $results = getScanResults($scan_info['path']);
            $scan_info['results'] = $results;
            $scan_info['end_time'] = date('Y-m-d H:i:s');
            
            // Save report
            saveReport($scan_info);
        }
        
        file_put_contents($scan_file, json_encode($scan_info));
        
        echo json_encode([
            'success' => true,
            'progress' => $progress,
            'status' => $status,
            'results' => isset($scan_info['results']) ? $scan_info['results'] : null
        ]);
    } else {
        // Fallback - estimate progress
        $elapsed = time() - strtotime($scan_info['start_time']);
        $estimated_progress = min(95, $elapsed * 2); // Rough estimate
        
        echo json_encode([
            'success' => true,
            'progress' => $estimated_progress,
            'status' => 'running'
        ]);
    }
}

/**
 * Get scan results
 */
function getScanResults($path) {
    // Get infected files from ImunifyAV
    $cmd = "imunify-antivirus malware malicious list --json 2>/dev/null";
    $output = shell_exec($cmd);
    $data = json_decode($output, true);
    
    $infected_files = [];
    $total_files = 0;
    
    if (isset($data['items']) && is_array($data['items'])) {
        foreach ($data['items'] as $item) {
            // Filter by scan path
            if (strpos($item['file'], $path) === 0) {
                $infected_files[] = [
                    'id' => $item['id'],
                    'path' => $item['file'],
                    'threat' => isset($item['type']) ? $item['type'] : 'Malware',
                    'status' => isset($item['status']) ? $item['status'] : 'infected'
                ];
            }
        }
    }
    
    // Get total files count (rough estimate)
    $total_files = intval(shell_exec("find $path -type f 2>/dev/null | wc -l"));
    
    return [
        'total_files' => $total_files,
        'infected_files' => $infected_files,
        'clean_files' => $total_files - count($infected_files)
    ];
}

/**
 * Clean infected file
 */
function cleanFile() {
    $file_id = isset($_POST['file_id']) ? $_POST['file_id'] : '';
    
    if (empty($file_id)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file ID']);
        return;
    }
    
    // Clean file using ImunifyAV
    $cmd = "imunify-antivirus malware cleanup --ids=$file_id 2>&1";
    $output = shell_exec($cmd);
    
    if (strpos($output, 'error') === false) {
        echo json_encode(['success' => true, 'message' => 'File cleaned successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to clean file']);
    }
}

/**
 * Quarantine infected file
 */
function quarantineFile() {
    $file_id = isset($_POST['file_id']) ? $_POST['file_id'] : '';
    
    if (empty($file_id)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file ID']);
        return;
    }
    
    // Quarantine file using ImunifyAV
    $cmd = "imunify-antivirus malware quarantine --ids=$file_id 2>&1";
    $output = shell_exec($cmd);
    
    if (strpos($output, 'error') === false) {
        echo json_encode(['success' => true, 'message' => 'File quarantined successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to quarantine file']);
    }
}

/**
 * Add path to whitelist
 */
function addToWhitelist() {
    $path = isset($_POST['path']) ? $_POST['path'] : '';
    
    if (empty($path)) {
        echo json_encode(['success' => false, 'error' => 'Invalid path']);
        return;
    }
    
    // Load current whitelist
    $whitelist_file = '/etc/sysconfig/imunify360/whitelist.json';
    $whitelist = ['items' => []];
    
    if (file_exists($whitelist_file)) {
        $content = file_get_contents($whitelist_file);
        $whitelist = json_decode($content, true);
    }
    
    // Add new path if not already present
    if (!in_array($path, $whitelist['items'])) {
        $whitelist['items'][] = $path;
        
        // Save whitelist
        file_put_contents($whitelist_file, json_encode($whitelist, JSON_PRETTY_PRINT));
        
        // Apply whitelist to ImunifyAV
        $cmd = "imunify-antivirus malware ignore add --path='$path' 2>&1";
        shell_exec($cmd);
        
        echo json_encode(['success' => true, 'message' => 'Added to whitelist']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Path already in whitelist']);
    }
}

/**
 * Remove path from whitelist
 */
function removeFromWhitelist() {
    $path = isset($_POST['path']) ? $_POST['path'] : '';
    
    if (empty($path)) {
        echo json_encode(['success' => false, 'error' => 'Invalid path']);
        return;
    }
    
    // Load current whitelist
    $whitelist_file = '/etc/sysconfig/imunify360/whitelist.json';
    
    if (file_exists($whitelist_file)) {
        $content = file_get_contents($whitelist_file);
        $whitelist = json_decode($content, true);
        
        // Remove path
        $key = array_search($path, $whitelist['items']);
        if ($key !== false) {
            unset($whitelist['items'][$key]);
            $whitelist['items'] = array_values($whitelist['items']); // Reindex
            
            // Save whitelist
            file_put_contents($whitelist_file, json_encode($whitelist, JSON_PRETTY_PRINT));
            
            // Remove from ImunifyAV ignore list
            $cmd = "imunify-antivirus malware ignore delete --path='$path' 2>&1";
            shell_exec($cmd);
            
            echo json_encode(['success' => true, 'message' => 'Removed from whitelist']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Path not in whitelist']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Whitelist file not found']);
    }
}

/**
 * Save scan schedule
 */
function saveSchedule() {
    $path = isset($_POST['path']) ? $_POST['path'] : '';
    $frequency = isset($_POST['frequency']) ? $_POST['frequency'] : 'daily';
    $hour = isset($_POST['hour']) ? intval($_POST['hour']) : 3;
    
    if (empty($path) || !is_dir($path)) {
        echo json_encode(['success' => false, 'error' => 'Invalid path']);
        return;
    }
    
    // Generate schedule ID
    $schedule_id = uniqid('schedule_');
    
    // Load current schedules
    $schedule_file = '/var/log/imunifyav_cwp/schedule.json';
    $schedules = [];
    
    if (file_exists($schedule_file)) {
        $content = file_get_contents($schedule_file);
        $schedules = json_decode($content, true) ?: [];
    }
    
    // Add new schedule
    $schedules[] = [
        'id' => $schedule_id,
        'path' => $path,
        'frequency' => $frequency,
        'hour' => $hour
    ];
    
    // Save schedules
    file_put_contents($schedule_file, json_encode($schedules, JSON_PRETTY_PRINT));
    
    // Create cron job
    createCronJob($schedule_id, $path, $frequency, $hour);
    
    echo json_encode(['success' => true, 'message' => 'Schedule saved successfully']);
}

/**
 * Create cron job for scheduled scan
 */
function createCronJob($id, $path, $frequency, $hour) {
    $cron_file = "/etc/cron.d/imunifyav_scan_{$id}";
    
    // Determine cron schedule
    switch ($frequency) {
        case 'daily':
            $schedule = "0 $hour * * *";
            break;
        case 'weekly':
            $schedule = "0 $hour * * 0"; // Sunday
            break;
        case 'monthly':
            $schedule = "0 $hour 1 * *"; // First day of month
            break;
        default:
            $schedule = "0 $hour * * *";
    }
    
    // Create cron job
    $cron_content = "# ImunifyAV scheduled scan for $path\n";
    $cron_content .= "$schedule root /usr/bin/imunify-antivirus malware on-demand start --path=$path --background > /dev/null 2>&1\n";
    
    file_put_contents($cron_file, $cron_content);
}

/**
 * Remove scheduled scan
 */
function removeSchedule() {
    $schedule_id = isset($_POST['schedule_id']) ? $_POST['schedule_id'] : '';
    
    if (empty($schedule_id)) {
        echo json_encode(['success' => false, 'error' => 'Invalid schedule ID']);
        return;
    }
    
    // Load schedules
    $schedule_file = '/var/log/imunifyav_cwp/schedule.json';
    
    if (file_exists($schedule_file)) {
        $schedules = json_decode(file_get_contents($schedule_file), true) ?: [];
        
        // Remove schedule
        $filtered = array_filter($schedules, function($s) use ($schedule_id) {
            return $s['id'] != $schedule_id;
        });
        
        // Save updated schedules
        file_put_contents($schedule_file, json_encode(array_values($filtered), JSON_PRETTY_PRINT));
        
        // Remove cron job
        $cron_file = "/etc/cron.d/imunifyav_scan_{$schedule_id}";
        if (file_exists($cron_file)) {
            unlink($cron_file);
        }
        
        echo json_encode(['success' => true, 'message' => 'Schedule removed']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Schedule file not found']);
    }
}

/**
 * Get scan reports
 */
function getReports() {
    $reports_dir = '/var/log/imunifyav_cwp/reports';
    $reports = [];
    
    if (is_dir($reports_dir)) {
        $files = glob($reports_dir . '/*.json');
        
        foreach ($files as $file) {
            $report = json_decode(file_get_contents($file), true);
            if ($report) {
                $reports[] = [
                    'id' => basename($file, '.json'),
                    'date' => $report['date'],
                    'path' => $report['path'],
                    'files_scanned' => $report['total_files'],
                    'threats_found' => count($report['infected_files'])
                ];
            }
        }
        
        // Sort by date (newest first)
        usort($reports, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
    }
    
    echo json_encode(['success' => true, 'reports' => $reports]);
}

/**
 * Save scan report
 */
function saveReport($scan_info) {
    $reports_dir = '/var/log/imunifyav_cwp/reports';
    
    if (!is_dir($reports_dir)) {
        mkdir($reports_dir, 0755, true);
    }
    
    $report = [
        'date' => $scan_info['start_time'],
        'path' => $scan_info['path'],
        'type' => $scan_info['type'],
        'total_files' => $scan_info['results']['total_files'],
        'infected_files' => $scan_info['results']['infected_files'],
        'duration' => strtotime($scan_info['end_time']) - strtotime($scan_info['start_time'])
    ];
    
    $report_file = $reports_dir . '/' . $scan_info['scan_id'] . '.json';
    file_put_contents($report_file, json_encode($report, JSON_PRETTY_PRINT));
}

/**
 * Export scan report
 */
function exportReport() {
    $scan_id = isset($_GET['scan_id']) ? $_GET['scan_id'] : '';
    
    if (empty($scan_id)) {
        die('Invalid scan ID');
    }
    
    // Check scan file first
    $scan_file = "/var/log/imunifyav_cwp/{$scan_id}.json";
    $report_file = "/var/log/imunifyav_cwp/reports/{$scan_id}.json";
    
    $report_data = null;
    
    if (file_exists($report_file)) {
        $report_data = json_decode(file_get_contents($report_file), true);
    } elseif (file_exists($scan_file)) {
        $scan_info = json_decode(file_get_contents($scan_file), true);
        if (isset($scan_info['results'])) {
            $report_data = [
                'date' => $scan_info['start_time'],
                'path' => $scan_info['path'],
                'total_files' => $scan_info['results']['total_files'],
                'infected_files' => $scan_info['results']['infected_files']
            ];
        }
    }
    
    if (!$report_data) {
        die('Report not found');
    }
    
    // Generate text report
    $report = "ImunifyAV Scan Report\n";
    $report .= "=====================\n\n";
    $report .= "Scan Date: " . $report_data['date'] . "\n";
    $report .= "Scanned Path: " . $report_data['path'] . "\n";
    $report .= "Total Files Scanned: " . $report_data['total_files'] . "\n";
    $report .= "Threats Found: " . count($report_data['infected_files']) . "\n\n";
    
    if (!empty($report_data['infected_files'])) {
        $report .= "Infected Files:\n";
        $report .= "---------------\n";
        foreach ($report_data['infected_files'] as $file) {
            $report .= "File: " . $file['path'] . "\n";
            $report .= "Threat: " . $file['threat'] . "\n";
            $report .= "Status: " . $file['status'] . "\n\n";
        }
    } else {
        $report .= "No threats detected.\n";
    }
    
    $report .= "\n---\nGenerated by ImunifyAV CWP Module\n";
    $report .= date('Y-m-d H:i:s');
    
    // Send as download
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="imunifyav_report_' . $scan_id . '.txt"');
    echo $report;
    exit;
}
?>
