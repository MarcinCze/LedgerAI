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

// Authentication middleware (skip for health check and auth endpoint)
if ($endpoint !== 'health' && $endpoint !== '' && $endpoint !== 'auth') {
    error_log("Auth Middleware: Validating token for endpoint: " . $endpoint);
    
    // Multiple ways to get Authorization header (OVH compatibility)
    $headers = getallheaders();
    $auth_header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    // Fallback methods for servers that strip Authorization header
    if (empty($auth_header)) {
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    }
    if (empty($auth_header)) {
        $auth_header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    }
    if (empty($auth_header) && function_exists('apache_request_headers')) {
        $apache_headers = apache_request_headers();
        $auth_header = $apache_headers['Authorization'] ?? $apache_headers['authorization'] ?? '';
    }
    
    error_log("Auth Middleware: Authorization header: " . (empty($auth_header) ? "MISSING" : "PRESENT"));
    
    if (!preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
        error_log("Auth Middleware: Bearer token format invalid");
        Response::error('Missing or invalid authorization header', 401, [
            'header_present' => !empty($auth_header),
            'header_preview' => !empty($auth_header) ? substr($auth_header, 0, 20) . '...' : 'EMPTY',
            'bearer_format_ok' => false,
            'debug_headers' => [
                'getallheaders_auth' => $headers['Authorization'] ?? 'NOT_FOUND',
                'getallheaders_auth_lower' => $headers['authorization'] ?? 'NOT_FOUND',
                'server_http_auth' => $_SERVER['HTTP_AUTHORIZATION'] ?? 'NOT_FOUND',
                'server_redirect_auth' => $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? 'NOT_FOUND',
                'available_server_vars' => array_keys(array_filter($_SERVER, function($key) {
                    return strpos(strtolower($key), 'auth') !== false;
                }, ARRAY_FILTER_USE_KEY))
            ]
        ]);
    }
    
    $token = $matches[1];
    error_log("Auth Middleware: Extracted token (first 20 chars): " . substr($token, 0, 20) . "...");
    
    $payload = $jwt->validateToken($token);
    
    if (!$payload) {
        error_log("Auth Middleware: Token validation failed");
        Response::error('Invalid or expired token', 401, [
            'token_length' => strlen($token),
            'token_parts' => count(explode('.', $token)),
            'token_preview' => substr($token, 0, 30) . '...',
            'suggestion' => 'Use /debug endpoint to analyze this token'
        ]);
    }
    
    error_log("Auth Middleware: Token validation successful");
    
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
            'database' => 'connected',
            'jwt_secret_loaded' => !empty($_ENV['JWT_SECRET']),
            'jwt_secret_fallback' => $_ENV['JWT_SECRET'] === 'your-secret-key-change-this'
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
            
            $token = $jwt->generateToken($service_name, null, $scope, 14400); // 4 hours
            
            Response::success([
                'token' => $token,
                'expires_in' => 14400,
                'service_name' => $service_name
            ], 'Token generated successfully');
        } else {
            Response::methodNotAllowed();
        }
        break;
        
    case 'debug':
        // Debug endpoint to test JWT validation step by step
        // TODO: Remove in production!
        if ($_ENV['ENVIRONMENT'] !== 'production' && $request_method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $test_token = $input['token'] ?? '';
            
            if (empty($test_token)) {
                Response::error('Token required for debug test', 400);
            }
            
            $debug_info = [
                'step1_token_received' => !empty($test_token),
                'step2_token_length' => strlen($test_token),
                'step3_token_parts' => count(explode('.', $test_token)),
                'step4_secret_available' => !empty($_ENV['JWT_SECRET']),
                'step5_secret_is_fallback' => $_ENV['JWT_SECRET'] === 'your-secret-key-change-this'
            ];
            
            try {
                // Test JWT decode
                $parts = explode('.', $test_token);
                if (count($parts) === 3) {
                    // Simple base64url decode for debug
                    $header = json_decode(base64_decode(str_pad(strtr($parts[0], '-_', '+/'), strlen($parts[0]) % 4, '=', STR_PAD_RIGHT)), true);
                    $payload = json_decode(base64_decode(str_pad(strtr($parts[1], '-_', '+/'), strlen($parts[1]) % 4, '=', STR_PAD_RIGHT)), true);
                    
                    $debug_info['step6_header_decoded'] = $header !== null;
                    $debug_info['step7_payload_decoded'] = $payload !== null;
                    $debug_info['step8_jti_found'] = isset($payload['jti']);
                    $debug_info['step9_exp_found'] = isset($payload['exp']);
                    $debug_info['step10_exp_time'] = $payload['exp'] ?? null;
                    $debug_info['step11_current_time'] = time();
                    $debug_info['step12_token_expired'] = isset($payload['exp']) ? ($payload['exp'] < time()) : null;
                    
                    if (isset($payload['jti'])) {
                        // Check database
                        $query = "SELECT token_id, expires_at, is_revoked FROM ledgerai_access_tokens WHERE token_id = ?";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$payload['jti']]);
                        $db_record = $stmt->fetch();
                        
                        $debug_info['step13_db_record_found'] = $db_record !== false;
                        $debug_info['step14_db_revoked'] = $db_record ? (bool)$db_record['is_revoked'] : null;
                        $debug_info['step15_db_expires_at'] = $db_record ? $db_record['expires_at'] : null;
                    }
                }
                
                // Test full validation
                $validation_result = $jwt->validateToken($test_token);
                $debug_info['step16_validation_result'] = $validation_result !== false;
                
            } catch (Exception $e) {
                $debug_info['validation_error'] = $e->getMessage();
            }
            
            Response::success($debug_info, 'Debug validation completed');
        } else {
            Response::methodNotAllowed();
        }
        break;
        
    case 'headers':
        // Debug endpoint to see all available headers and server vars
        // TODO: Remove in production!
        if ($_ENV['ENVIRONMENT'] !== 'production') {
            $all_headers = getallheaders();
        $auth_vars = array_filter($_SERVER, function($key) {
            return strpos(strtolower($key), 'auth') !== false;
        }, ARRAY_FILTER_USE_KEY);
        
        Response::success([
            'all_headers' => $all_headers,
            'server_auth_vars' => $auth_vars,
            'request_method' => $_SERVER['REQUEST_METHOD'],
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'NOT_SET',
            'http_host' => $_SERVER['HTTP_HOST'] ?? 'NOT_SET'
        ], 'Headers debug information');
        } else {
            Response::notFound('Debug endpoint not available in production');
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