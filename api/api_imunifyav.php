<?php
/**
 * ImunifyAV REST API for CWP
 * Provides API endpoints for external integration
 * 
 * Usage: /api/imunifyav.php?action=ACTION&key=API_KEY
 */

header('Content-Type: application/json');

// Configuration
define('API_KEY_FILE', '/etc/sysconfig/imunify360/api_keys.json');
define('RATE_LIMIT_FILE', '/var/log/imunifyav_cwp/api_rate_limit.json');
define('MAX_REQUESTS_PER_HOUR', 100);

// Enable CORS if needed
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Authorization, Content-Type");
    exit(0);
}

// API Response class
class APIResponse {
    public static function success($data = [], $message = 'Success') {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'timestamp' => time(),
            'data' => $data
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    public static function error($message = 'Error', $code = 400) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message,
            'timestamp' => time()
        ], JSON_PRETTY_PRINT);
        exit;
    }
}

// Authentication
class APIAuth {
    private static $api_keys = [];
    
    public static function loadKeys() {
        if (file_exists(API_KEY_FILE)) {
            $content = file_get_contents(API_KEY_FILE);
            $data = json_decode($content, true);
            self::$api_keys = $data['keys'] ?? [];
        }
    }
    
    public static function validateKey($key) {
        self::loadKeys();
        
        foreach (self::$api_keys as $api_key) {
            if ($api_key['key'] === $key && $api_key['active']) {
                // Check expiration
                if (isset($api_key['expires']) && time() > strtotime($api_key['expires'])) {
                    return false;
                }
                return $api_key;
            }
        }
        return false;
    }
    
    public static function generateKey($name, $permissions = ['read'], $expires = null) {
        self::loadKeys();
        
        $key = bin2hex(random_bytes(32));
        $api_key = [
            'key' => $key,
            'name' => $name,
            'created' => date('Y-m-d H:i:s'),
            'expires' => $expires,
            'permissions' => $permissions,
            'active' => true
        ];
        
        self::$api_keys[] = $api_key;
        
        // Save keys
        $data = ['keys' => self::$api_keys];
        file_put_contents(API_KEY_FILE, json_encode($data, JSON_PRETTY_PRINT));
        
        return $key;
    }
    
    public static function checkPermission($key_data, $permission) {
        return in_array($permission, $key_data['permissions']) || in_array('admin', $key_data['permissions']);
    }
}

// Rate limiting
class RateLimiter {
    public static function check($identifier) {
        $limits = [];
        
        if (file_exists(RATE_LIMIT_FILE)) {
            $limits = json_decode(file_get_contents(RATE_LIMIT_FILE), true) ?: [];
        }
        
        $hour_ago = time() - 3600;
        
        // Clean old entries
        foreach ($limits as $id => $data) {
            $limits[$id]['requests'] = array_filter($data['requests'], function($time) use ($hour_ago) {
                return $time > $hour_ago;
            });
        }
        
        // Check current limit
        if (!isset($limits[$identifier])) {
            $limits[$identifier] = ['requests' => []];
        }
        
        if (count($limits[$identifier]['requests']) >= MAX_REQUESTS_PER_HOUR) {
            return false;
        }
        
        // Add current request
        $limits[$identifier]['requests'][] = time();
        
        // Save limits
        file_put_contents(RATE_LIMIT_FILE, json_encode($limits));
        
        return true;
    }
}

// API Actions
class ImunifyAPI {
    
    // Get system status
    public static function getStatus() {
        $status = [];
        
        // ImunifyAV version
        $version = shell_exec("imunify-antivirus version 2>/dev/null | head -n1");
        $status['version'] = trim($version);
        
        // Service status
        $service = shell_exec("systemctl is-active imunify-antivirus 2>/dev/null");
        $status['service'] = (trim($service) === 'active') ? 'running' : 'stopped';
        
        // Database status
        $db_info = shell_exec("imunify-antivirus update status 2>/dev/null");
        if (preg_match('/Last update: (.+)/', $db_info, $matches)) {
            $status['database_updated'] = $matches[1];
        }
        
        // Threat count
        $infected_json = shell_exec("imunify-antivirus malware malicious list --json 2>/dev/null");
        $infected_data = json_decode($infected_json, true);
        $status['active_threats'] = isset($infected_data['items']) ? count($infected_data['items']) : 0;
        
        return $status;
    }
    
    // Start scan
    public static function startScan($path, $type = 'quick') {
        // Validate path
        if (!is_dir($path)) {
            APIResponse::error('Invalid path', 400);
        }
        
        // Generate scan ID
        $scan_id = uniqid('api_scan_');
        
        // Prepare command
        $intensity = ($type === 'full') ? 'high' : 'low';
        $cmd = "imunify-antivirus malware on-demand start --path=$path --intensity=$intensity --background 2>&1";
        
        // Execute scan
        $output = shell_exec($cmd);
        
        // Parse task ID
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
                'status' => 'running'
            ];
            
            file_put_contents(
                "/var/log/imunifyav_cwp/{$scan_id}.json",
                json_encode($scan_info)
            );
            
            return [
                'scan_id' => $scan_id,
                'task_id' => $task_id,
                'status' => 'started'
            ];
        } else {
            APIResponse::error('Failed to start scan', 500);
        }
    }
    
    // Get scan status
    public static function getScanStatus($scan_id) {
        $scan_file = "/var/log/imunifyav_cwp/{$scan_id}.json";
        
        if (!file_exists($scan_file)) {
            APIResponse::error('Scan not found', 404);
        }
        
        $scan_info = json_decode(file_get_contents($scan_file), true);
        
        // Get current status
        $cmd = "imunify-antivirus malware on-demand status --task-id={$scan_info['task_id']} --json 2>/dev/null";
        $output = shell_exec($cmd);
        $status_data = json_decode($output, true);
        
        if ($status_data) {
            $scan_info['progress'] = $status_data['progress'] ?? 0;
            $scan_info['status'] = $status_data['status'] ?? 'running';
        }
        
        return $scan_info;
    }
    
    // Get infected files
    public static function getInfectedFiles($limit = 100, $offset = 0) {
        $cmd = "imunify-antivirus malware malicious list --json 2>/dev/null";
        $output = shell_exec($cmd);
        $data = json_decode($output, true);
        
        $files = [];
        
        if (isset($data['items']) && is_array($data['items'])) {
            // Apply pagination
            $items = array_slice($data['items'], $offset, $limit);
            
            foreach ($items as $item) {
                $files[] = [
                    'id' => $item['id'],
                    'path' => $item['file'],
                    'threat' => $item['type'] ?? 'Unknown',
                    'detected' => $item['timestamp'] ?? null
                ];
            }
        }
        
        return [
            'total' => count($data['items'] ?? []),
            'limit' => $limit,
            'offset' => $offset,
            'files' => $files
        ];
    }
    
    // Clean file
    public static function cleanFile($file_id) {
        $cmd = "imunify-antivirus malware cleanup --ids=$file_id 2>&1";
        $output = shell_exec($cmd);
        
        if (strpos($output, 'error') === false) {
            return ['status' => 'cleaned', 'file_id' => $file_id];
        } else {
            APIResponse::error('Failed to clean file', 500);
        }
    }
    
    // Quarantine file
    public static function quarantineFile($file_id) {
        $cmd = "imunify-antivirus malware quarantine --ids=$file_id 2>&1";
        $output = shell_exec($cmd);
        
        if (strpos($output, 'error') === false) {
            return ['status' => 'quarantined', 'file_id' => $file_id];
        } else {
            APIResponse::error('Failed to quarantine file', 500);
        }
    }
    
    // Get whitelist
    public static function getWhitelist() {
        $whitelist_file = '/etc/sysconfig/imunify360/whitelist.json';
        
        if (file_exists($whitelist_file)) {
            $data = json_decode(file_get_contents($whitelist_file), true);
            return $data['items'] ?? [];
        }
        
        return [];
    }
    
    // Add to whitelist
    public static function addToWhitelist($path) {
        $whitelist_file = '/etc/sysconfig/imunify360/whitelist.json';
        
        $whitelist = ['items' => []];
        if (file_exists($whitelist_file)) {
            $whitelist = json_decode(file_get_contents($whitelist_file), true);
        }
        
        if (!in_array($path, $whitelist['items'])) {
            $whitelist['items'][] = $path;
            file_put_contents($whitelist_file, json_encode($whitelist, JSON_PRETTY_PRINT));
            
            // Apply to ImunifyAV
            shell_exec("imunify-antivirus malware ignore add --path='$path' 2>&1");
            
            return ['added' => $path];
        } else {
            APIResponse::error('Path already in whitelist', 409);
        }
    }
    
    // Remove from whitelist
    public static function removeFromWhitelist($path) {
        $whitelist_file = '/etc/sysconfig/imunify360/whitelist.json';
        
        if (file_exists($whitelist_file)) {
            $whitelist = json_decode(file_get_contents($whitelist_file), true);
            
            $key = array_search($path, $whitelist['items']);
            if ($key !== false) {
                unset($whitelist['items'][$key]);
                $whitelist['items'] = array_values($whitelist['items']);
                
                file_put_contents($whitelist_file, json_encode($whitelist, JSON_PRETTY_PRINT));
                
                // Remove from ImunifyAV
                shell_exec("imunify-antivirus malware ignore delete --path='$path' 2>&1");
                
                return ['removed' => $path];
            } else {
                APIResponse::error('Path not in whitelist', 404);
            }
        }
        
        APIResponse::error('Whitelist not found', 404);
    }
    
    // Get scan reports
    public static function getReports($limit = 10, $offset = 0) {
        $reports_dir = '/var/log/imunifyav_cwp/reports';
        $reports = [];
        
        if (is_dir($reports_dir)) {
            $files = glob($reports_dir . '/*.json');
            
            // Sort by date
            usort($files, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            
            // Apply pagination
            $files = array_slice($files, $offset, $limit);
            
            foreach ($files as $file) {
                $report = json_decode(file_get_contents($file), true);
                if ($report) {
                    $reports[] = [
                        'id' => basename($file, '.json'),
                        'date' => $report['date'],
                        'path' => $report['path'],
                        'type' => $report['type'] ?? 'manual',
                        'files_scanned' => $report['total_files'],
                        'threats_found' => count($report['infected_files'] ?? [])
                    ];
                }
            }
        }
        
        return [
            'total' => count(glob($reports_dir . '/*.json')),
            'limit' => $limit,
            'offset' => $offset,
            'reports' => $reports
        ];
    }
    
    // Update database
    public static function updateDatabase() {
        $cmd = "imunify-antivirus update malware-database 2>&1";
        $output = shell_exec($cmd);
        
        if (strpos($output, 'error') === false) {
            return ['status' => 'updating', 'message' => 'Database update started'];
        } else {
            APIResponse::error('Failed to start database update', 500);
        }
    }
}

// Main execution
try {
    // Get request parameters
    $action = $_REQUEST['action'] ?? '';
    $api_key = $_REQUEST['key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
    
    // Validate API key
    if (empty($api_key)) {
        APIResponse::error('API key required', 401);
    }
    
    $key_data = APIAuth::validateKey($api_key);
    if (!$key_data) {
        APIResponse::error('Invalid API key', 401);
    }
    
    // Check rate limit
    if (!RateLimiter::check($api_key)) {
        APIResponse::error('Rate limit exceeded', 429);
    }
    
    // Route actions
    switch ($action) {
        case 'status':
            if (!APIAuth::checkPermission($key_data, 'read')) {
                APIResponse::error('Permission denied', 403);
            }
            $data = ImunifyAPI::getStatus();
            APIResponse::success($data);
            break;
            
        case 'scan':
            if (!APIAuth::checkPermission($key_data, 'scan')) {
                APIResponse::error('Permission denied', 403);
            }
            $path = $_REQUEST['path'] ?? '/home';
            $type = $_REQUEST['type'] ?? 'quick';
            $data = ImunifyAPI::startScan($path, $type);
            APIResponse::success($data, 'Scan started');
            break;
            
        case 'scan_status':
            if (!APIAuth::checkPermission($key_data, 'read')) {
                APIResponse::error('Permission denied', 403);
            }
            $scan_id = $_REQUEST['scan_id'] ?? '';
            if (empty($scan_id)) {
                APIResponse::error('Scan ID required', 400);
            }
            $data = ImunifyAPI::getScanStatus($scan_id);
            APIResponse::success($data);
            break;
            
        case 'infected_files':
            if (!APIAuth::checkPermission($key_data, 'read')) {
                APIResponse::error('Permission denied', 403);
            }
            $limit = intval($_REQUEST['limit'] ?? 100);
            $offset = intval($_REQUEST['offset'] ?? 0);
            $data = ImunifyAPI::getInfectedFiles($limit, $offset);
            APIResponse::success($data);
            break;
            
        case 'clean':
            if (!APIAuth::checkPermission($key_data, 'clean')) {
                APIResponse::error('Permission denied', 403);
            }
            $file_id = $_REQUEST['file_id'] ?? '';
            if (empty($file_id)) {
                APIResponse::error('File ID required', 400);
            }
            $data = ImunifyAPI::cleanFile($file_id);
            APIResponse::success($data, 'File cleaned');
            break;
            
        case 'quarantine':
            if (!APIAuth::checkPermission($key_data, 'quarantine')) {
                APIResponse::error('Permission denied', 403);
            }
            $file_id = $_REQUEST['file_id'] ?? '';
            if (empty($file_id)) {
                APIResponse::error('File ID required', 400);
            }
            $data = ImunifyAPI::quarantineFile($file_id);
            APIResponse::success($data, 'File quarantined');
            break;
            
        case 'whitelist':
            if (!APIAuth::checkPermission($key_data, 'read')) {
                APIResponse::error('Permission denied', 403);
            }
            $data = ImunifyAPI::getWhitelist();
            APIResponse::success($data);
            break;
            
        case 'whitelist_add':
            if (!APIAuth::checkPermission($key_data, 'whitelist')) {
                APIResponse::error('Permission denied', 403);
            }
            $path = $_REQUEST['path'] ?? '';
            if (empty($path)) {
                APIResponse::error('Path required', 400);
            }
            $data = ImunifyAPI::addToWhitelist($path);
            APIResponse::success($data, 'Added to whitelist');
            break;
            
        case 'whitelist_remove':
            if (!APIAuth::checkPermission($key_data, 'whitelist')) {
                APIResponse::error('Permission denied', 403);
            }
            $path = $_REQUEST['path'] ?? '';
            if (empty($path)) {
                APIResponse::error('Path required', 400);
            }
            $data = ImunifyAPI::removeFromWhitelist($path);
            APIResponse::success($data, 'Removed from whitelist');
            break;
            
        case 'reports':
            if (!APIAuth::checkPermission($key_data, 'read')) {
                APIResponse::error('Permission denied', 403);
            }
            $limit = intval($_REQUEST['limit'] ?? 10);
            $offset = intval($_REQUEST['offset'] ?? 0);
            $data = ImunifyAPI::getReports($limit, $offset);
            APIResponse::success($data);
            break;
            
        case 'update_database':
            if (!APIAuth::checkPermission($key_data, 'admin')) {
                APIResponse::error('Permission denied', 403);
            }
            $data = ImunifyAPI::updateDatabase();
            APIResponse::success($data, 'Database update started');
            break;
            
        case 'generate_key':
            // This should only be accessible to admin users
            if (!isset($_SESSION['cwp_admin'])) {
                APIResponse::error('Admin access required', 401);
            }
            $name = $_REQUEST['name'] ?? 'API Client';
            $permissions = $_REQUEST['permissions'] ?? ['read'];
            if (is_string($permissions)) {
                $permissions = explode(',', $permissions);
            }
            $expires = $_REQUEST['expires'] ?? null;
            
            $new_key = APIAuth::generateKey($name, $permissions, $expires);
            APIResponse::success(['api_key' => $new_key], 'API key generated');
            break;
            
        default:
            APIResponse::error('Invalid action', 400);
    }
    
} catch (Exception $e) {
    APIResponse::error($e->getMessage(), 500);
}
?>
