# ImunifyAV CWP Module - API Documentation

## Overview

The ImunifyAV CWP Module provides a RESTful API for external integration and automation. This API allows you to perform scans, manage threats, and monitor the system programmatically.

## Base URL

```
http://YOUR_SERVER_IP:2030/api/imunifyav.php
```

## Authentication

All API requests require an API key. You can provide the API key in two ways:

1. **Query Parameter**: `?key=YOUR_API_KEY`
2. **HTTP Header**: `X-API-Key: YOUR_API_KEY`

### Generating API Keys

API keys can be generated through the CWP admin panel or via the API (requires admin session):

```bash
curl -X POST "http://SERVER:2030/api/imunifyav.php" \
  -d "action=generate_key" \
  -d "name=My Application" \
  -d "permissions=read,scan,clean" \
  -d "expires=2025-12-31"
```

### API Key Permissions

- `read` - View status, reports, and infected files
- `scan` - Start and monitor scans
- `clean` - Clean infected files
- `quarantine` - Quarantine infected files
- `whitelist` - Manage whitelist
- `admin` - Full access to all operations

## Rate Limiting

- **Limit**: 100 requests per hour per API key
- **Response Code**: `429 Too Many Requests` when exceeded
- **Reset**: Hourly rolling window

## API Endpoints

### 1. System Status

Get current ImunifyAV status and statistics.

**Request:**
```
GET /api/imunifyav.php?action=status&key=YOUR_API_KEY
```

**Response:**
```json
{
  "success": true,
  "message": "Success",
  "timestamp": 1234567890,
  "data": {
    "version": "5.6.1",
    "service": "running",
    "database_updated": "2025-09-20 10:30:00",
    "active_threats": 3
  }
}
```

### 2. Start Scan

Initiate a malware scan on specified directory.

**Request:**
```
POST /api/imunifyav.php
  action=scan
  key=YOUR_API_KEY
  path=/home/user
  type=quick|full
```

**Parameters:**
- `path` (required) - Directory to scan
- `type` (optional) - Scan type: `quick` (default) or `full`

**Response:**
```json
{
  "success": true,
  "message": "Scan started",
  "data": {
    "scan_id": "api_scan_abc123",
    "task_id": "12345",
    "status": "started"
  }
}
```

### 3. Check Scan Status

Get the status of a running or completed scan.

**Request:**
```
GET /api/imunifyav.php?action=scan_status&key=YOUR_API_KEY&scan_id=api_scan_abc123
```

**Response:**
```json
{
  "success": true,
  "data": {
    "scan_id": "api_scan_abc123",
    "task_id": "12345",
    "path": "/home/user",
    "type": "quick",
    "start_time": "2025-09-20 14:30:00",
    "status": "running",
    "progress": 45
  }
}
```

### 4. Get Infected Files

Retrieve list of detected malware files.

**Request:**
```
GET /api/imunifyav.php?action=infected_files&key=YOUR_API_KEY&limit=50&offset=0
```

**Parameters:**
- `limit` (optional) - Number of results (default: 100)
- `offset` (optional) - Pagination offset (default: 0)

**Response:**
```json
{
  "success": true,
  "data": {
    "total": 150,
    "limit": 50,
    "offset": 0,
    "files": [
      {
        "id": "file_123",
        "path": "/home/user/public_html/infected.php",
        "threat": "Backdoor.PHP.Agent",
        "detected": "2025-09-20 13:45:00"
      }
    ]
  }
}
```

### 5. Clean Infected File

Remove malware from an infected file.

**Request:**
```
POST /api/imunifyav.php
  action=clean
  key=YOUR_API_KEY
  file_id=file_123
```

**Response:**
```json
{
  "success": true,
  "message": "File cleaned",
  "data": {
    "status": "cleaned",
    "file_id": "file_123"
  }
}
```

### 6. Quarantine File

Move infected file to quarantine.

**Request:**
```
POST /api/imunifyav.php
  action=quarantine
  key=YOUR_API_KEY
  file_id=file_123
```

**Response:**
```json
{
  "success": true,
  "message": "File quarantined",
  "data": {
    "status": "quarantined",
    "file_id": "file_123"
  }
}
```

### 7. Manage Whitelist

#### Get Whitelist
```
GET /api/imunifyav.php?action=whitelist&key=YOUR_API_KEY
```

#### Add to Whitelist
```
POST /api/imunifyav.php
  action=whitelist_add
  key=YOUR_API_KEY
  path=/path/to/whitelist
```

#### Remove from Whitelist
```
POST /api/imunifyav.php
  action=whitelist_remove
  key=YOUR_API_KEY
  path=/path/to/remove
```

### 8. Get Scan Reports

Retrieve historical scan reports.

**Request:**
```
GET /api/imunifyav.php?action=reports&key=YOUR_API_KEY&limit=10&offset=0
```

**Response:**
```json
{
  "success": true,
  "data": {
    "total": 45,
    "limit": 10,
    "offset": 0,
    "reports": [
      {
        "id": "scan_xyz789",
        "date": "2025-09-20 10:00:00",
        "path": "/home",
        "type": "scheduled",
        "files_scanned": 15234,
        "threats_found": 2
      }
    ]
  }
}
```

### 9. Update Database

Trigger malware database update.

**Request:**
```
POST /api/imunifyav.php
  action=update_database
  key=YOUR_API_KEY
```

**Response:**
```json
{
  "success": true,
  "message": "Database update started",
  "data": {
    "status": "updating",
    "message": "Database update started"
  }
}
```

## Error Responses

All errors follow this format:

```json
{
  "success": false,
  "error": "Error message",
  "timestamp": 1234567890
}
```

### Common Error Codes

- `400 Bad Request` - Invalid parameters
- `401 Unauthorized` - Missing or invalid API key
- `403 Forbidden` - Insufficient permissions
- `404 Not Found` - Resource not found
- `429 Too Many Requests` - Rate limit exceeded
- `500 Internal Server Error` - Server error

## Code Examples

### Python

```python
import requests
import json

# Configuration
API_URL = "http://server:2030/api/imunifyav.php"
API_KEY = "your_api_key_here"

# Get status
response = requests.get(f"{API_URL}?action=status&key={API_KEY}")
data = response.json()
print(f"Active threats: {data['data']['active_threats']}")

# Start scan
scan_data = {
    'action': 'scan',
    'key': API_KEY,
    'path': '/home',
    'type': 'quick'
}
response = requests.post(API_URL, data=scan_data)
result = response.json()
scan_id = result['data']['scan_id']

# Check scan progress
response = requests.get(f"{API_URL}?action=scan_status&key={API_KEY}&scan_id={scan_id}")
status = response.json()
print(f"Scan progress: {status['data']['progress']}%")
```

### PHP

```php
<?php
$api_url = "http://server:2030/api/imunifyav.php";
$api_key = "your_api_key_here";

// Get status
$response = file_get_contents("{$api_url}?action=status&key={$api_key}");
$data = json_decode($response, true);
echo "Active threats: " . $data['data']['active_threats'];

// Start scan
$options = [
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/x-www-form-urlencoded',
        'content' => http_build_query([
            'action' => 'scan',
            'key' => $api_key,
            'path' => '/home',
            'type' => 'quick'
        ])
    ]
];
$context = stream_context_create($options);
$response = file_get_contents($api_url, false, $context);
$result = json_decode($response, true);
?>
```

### Bash/cURL

```bash
#!/bin/bash

API_URL="http://server:2030/api/imunifyav.php"
API_KEY="your_api_key_here"

# Get status
curl -s "${API_URL}?action=status&key=${API_KEY}" | jq

# Start scan
curl -X POST "${API_URL}" \
  -d "action=scan" \
  -d "key=${API_KEY}" \
  -d "path=/home" \
  -d "type=quick"

# Get infected files
curl -s "${API_URL}?action=infected_files&key=${API_KEY}&limit=10" | jq '.data.files'
```

### Node.js

```javascript
const axios = require('axios');

const API_URL = 'http://server:2030/api/imunifyav.php';
const API_KEY = 'your_api_key_here';

// Get status
async function getStatus() {
  const response = await axios.get(API_URL, {
    params: {
      action: 'status',
      key: API_KEY
    }
  });
  return response.data;
}

// Start scan
async function startScan(path, type = 'quick') {
  const response = await axios.post(API_URL, 
    new URLSearchParams({
      action: 'scan',
      key: API_KEY,
      path: path,
      type: type
    })
  );
  return response.data;
}

// Usage
(async () => {
  const status = await getStatus();
  console.log('Active threats:', status.data.active_threats);
  
  const scan = await startScan('/home');
  console.log('Scan started:', scan.data.scan_id);
})();
```

## Webhooks

You can configure webhooks to receive notifications about scan completions and threat detections.

### Configuration

Create a webhook configuration file at `/etc/sysconfig/imunify360/webhooks.json`:

```json
{
  "webhooks": [
    {
      "url": "https://your-server.com/webhook",
      "events": ["scan_completed", "threat_detected"],
      "secret": "your_webhook_secret"
    }
  ]
}
```

### Webhook Payload

```json
{
  "event": "scan_completed",
  "timestamp": 1234567890,
  "data": {
    "scan_id": "scan_abc123",
    "path": "/home",
    "files_scanned": 12345,
    "threats_found": 3,
    "duration": 300
  },
  "signature": "sha256_hash_of_payload_with_secret"
}
```

## Best Practices

1. **Security**
   - Store API keys securely (environment variables, secrets manager)
   - Use HTTPS in production
   - Rotate API keys regularly
   - Implement IP whitelisting for additional security

2. **Performance**
   - Cache status responses for 1-5 minutes
   - Use pagination for large result sets
   - Avoid scanning large directories during peak hours
   - Monitor API usage to stay within rate limits

3. **Error Handling**
   - Implement retry logic with exponential backoff
   - Log all API errors for debugging
   - Handle timeout errors for long-running scans
   - Validate responses before processing

4. **Monitoring**
   - Set up alerts for high threat counts
   - Monitor scan completion times
   - Track API usage and errors
   - Regular database update checks

## Support

For issues or questions about the API:

1. Check API logs: `/var/log/imunifyav_cwp/api.log`
2. Verify API key permissions
3. Ensure ImunifyAV service is running
4. Check rate limit status

## Changelog

### Version 1.0 (Initial Release)
- Basic CRUD operations
- Authentication system
- Rate limiting
- Scan management
- Threat management
- Whitelist operations
- Report generation

---

**API Version**: 1.0  
**Last Updated**: 2025-09-20  
**Maintained by**: ImunifyAV CWP Module Team
