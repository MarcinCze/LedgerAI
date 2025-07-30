<?php
/**
 * LedgerAI Transaction API - Main Entry Point
 * Routes requests to appropriate CRUD endpoints.
 */

// Error reporting configuration
// In production, errors should be logged but not displayed
$is_development = ($_ENV['ENVIRONMENT'] ?? 'production') === 'development';

if ($is_development) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// Load environment variables from .env file in parent directory
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // Skip comments
        }
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Include required files from api directory
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../api/helpers/JWT.php';
require_once __DIR__ . '/../api/helpers/Response.php';
require_once __DIR__ . '/../api/endpoints/accounts.php';
require_once __DIR__ . '/../api/endpoints/transactions.php';
require_once __DIR__ . '/../api/endpoints/categories.php';

// Set CORS headers
Response::setCorsHeaders();

// Parse the request URL
$request_uri = $_SERVER['REQUEST_URI'];
$request_method = $_SERVER['REQUEST_METHOD'];

// Remove query parameters and clean path
$path = parse_url($request_uri, PHP_URL_PATH);
$path = trim($path, '/');

// Split path into segments
$segments = explode('/', $path);
$endpoint = $segments[0] ?? '';
$resource_id = $segments[1] ?? null;

// Validate critical environment variables are set
$critical_env_vars = ['DB_HOST', 'DB_NAME', 'DB_USERNAME', 'DB_PASSWORD', 'JWT_SECRET'];
$missing_vars = [];

foreach ($critical_env_vars as $var) {
    if (empty($_ENV[$var])) {
        $missing_vars[] = $var;
    }
}

if (!empty($missing_vars) && !$is_development) {
    error_log('Missing critical environment variables: ' . implode(', ', $missing_vars));
    Response::serverError('Server configuration error');
}

// Initialize database connection
try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    Response::serverError('Database connection failed');
}

// Initialize JWT helper
$jwt = new JWT($db);

// Authentication middleware (skip for health check)
if ($endpoint !== 'health' && $endpoint !== '') {
    $headers = getallheaders();
    $auth_header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (!preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
        Response::unauthorized('Missing or invalid authorization header');
    }
    
    $token = $matches[1];
    $payload = $jwt->validateToken($token);
    
    if (!$payload) {
        Response::unauthorized('Invalid or expired token');
    }
    
    // Store user info for use in endpoints
    $_REQUEST['user_payload'] = $payload;
}

// Route to appropriate endpoint
switch ($endpoint) {
    case '':
    case 'health':
        // Health check endpoint
        Response::success([
            'api' => 'LedgerAI Transaction API',
            'version' => '1.0.0',
            'status' => 'healthy',
            'database' => 'connected'
        ], 'API is running');
        break;
        
    case 'accounts':
        handleAccounts($db, $request_method, $resource_id);
        break;
        
    case 'transactions':
        handleTransactions($db, $request_method, $resource_id);
        break;
        
    case 'categories':
        handleCategories($db, $request_method, $resource_id);
        break;
        
    case 'auth':
        // Token generation endpoint (for testing)
        if ($request_method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $service_name = $input['service_name'] ?? 'test';
            $scope = $input['scope'] ?? ['read', 'write'];
            
            $token = $jwt->generateToken($service_name, null, $scope, 86400); // 24 hours
            
            Response::success([
                'token' => $token,
                'expires_in' => 86400,
                'service_name' => $service_name
            ], 'Token generated successfully');
        } else {
            Response::methodNotAllowed();
        }
        break;
        
    default:
        Response::notFound('Endpoint not found');
        break;
}

/**
 * Helper function to get JSON input
 */
function getJsonInput() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        Response::error('Invalid JSON input', 400);
    }
    
    return $data;
}

/**
 * Helper function to validate required fields
 */
function validateRequired($data, $required_fields) {
    $errors = [];
    
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            $errors[] = "Field '{$field}' is required";
        }
    }
    
    if (!empty($errors)) {
        Response::validation($errors);
    }
}
?> 