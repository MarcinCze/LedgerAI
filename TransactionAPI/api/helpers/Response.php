<?php
/**
 * LedgerAI Transaction API - Response Helper
 * Standardized JSON API responses
 */

class Response {
    
    public static function success($data = null, $message = 'Success', $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        
        $response = [
            'success' => true,
            'message' => $message,
            'timestamp' => date('c'),
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function error($message = 'An error occurred', $code = 400, $details = null) {
        http_response_code($code);
        header('Content-Type: application/json');
        
        $response = [
            'success' => false,
            'error' => $message,
            'timestamp' => date('c'),
        ];
        
        if ($details !== null) {
            $response['details'] = $details;
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function created($data, $message = 'Resource created successfully') {
        self::success($data, $message, 201);
    }

    public static function notFound($message = 'Resource not found') {
        self::error($message, 404);
    }

    public static function unauthorized($message = 'Unauthorized access') {
        self::error($message, 401);
    }

    public static function forbidden($message = 'Access forbidden') {
        self::error($message, 403);
    }

    public static function validation($errors, $message = 'Validation failed') {
        self::error($message, 422, $errors);
    }

    public static function serverError($message = 'Internal server error') {
        self::error($message, 500);
    }

    public static function methodNotAllowed($message = 'Method not allowed') {
        self::error($message, 405);
    }

    /**
     * Set CORS headers for cross-origin requests
     */
    public static function setCorsHeaders() {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        
        // Handle preflight OPTIONS request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }

    /**
     * Paginated response helper
     */
    public static function paginated($data, $total, $page, $limit, $message = 'Success') {
        $totalPages = ceil($total / $limit);
        
        $pagination = [
            'current_page' => (int) $page,
            'per_page' => (int) $limit,
            'total_items' => (int) $total,
            'total_pages' => (int) $totalPages,
            'has_next' => $page < $totalPages,
            'has_previous' => $page > 1
        ];
        
        self::success([
            'items' => $data,
            'pagination' => $pagination
        ], $message);
    }
}
?> 