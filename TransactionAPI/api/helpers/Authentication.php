<?php
/**
 * LedgerAI Transaction API - Authentication Helper
 * Handles user management and API key validation
 */

class Authentication {
    private $db;

    public function __construct($database) {
        $this->db = $database;
    }

    /**
     * Initialize default users and API keys from environment variables
     * Called once during application startup
     */
    public function initializeFromEnvironment() {
        error_log("Authentication: Starting environment initialization");
        
        // Initialize users from environment
        $this->createUserFromEnv('DIRECT_USER');
        $this->createUserFromEnv('AGENT_AI');
        
        // Initialize API keys from environment
        $this->createApiKeyFromEnv('FRONTEND_API_KEY', 'frontend');
        $this->createApiKeyFromEnv('SEMANTIC_KERNEL_API_KEY', 'semantic_kernel');
        
        error_log("Authentication: Environment initialization completed");
    }

    /**
     * Create user from environment variables if not exists
     */
    private function createUserFromEnv($prefix) {
        $username = $_ENV["${prefix}_USERNAME"] ?? null;
        $email = $_ENV["${prefix}_EMAIL"] ?? null;
        $password = $_ENV["${prefix}_PASSWORD"] ?? null;
        $role = $_ENV["${prefix}_ROLE"] ?? 'user';

        if (!$username || !$email || !$password) {
            error_log("Authentication: Skipping ${prefix} - missing credentials");
            return false;
        }

        // Check if user already exists
        if ($this->userExists($username)) {
            error_log("Authentication: User ${username} already exists, skipping");
            return true;
        }

        try {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $query = "INSERT INTO ledgerai_users (username, email, password_hash, role) 
                      VALUES (?, ?, ?, ?)";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$username, $email, $password_hash, $role]);

            error_log("Authentication: Created user ${username} with role ${role}");
            return true;
        } catch (Exception $e) {
            error_log("Authentication: Failed to create user ${username}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create API key from environment variables if not exists
     */
    private function createApiKeyFromEnv($env_key, $service_type) {
        $api_key = $_ENV[$env_key] ?? null;
        
        if (!$api_key) {
            error_log("Authentication: Skipping ${env_key} - not found in environment");
            return false;
        }

        $key_name = strtolower($service_type) . '_key';

        // Check if API key already exists
        if ($this->apiKeyExists($key_name)) {
            error_log("Authentication: API key ${key_name} already exists, skipping");
            return true;
        }

        try {
            $api_key_hash = password_hash($api_key, PASSWORD_DEFAULT);
            
            $query = "INSERT INTO ledgerai_api_keys (key_name, api_key_hash, service_type) 
                      VALUES (?, ?, ?)";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$key_name, $api_key_hash, $service_type]);

            error_log("Authentication: Created API key ${key_name} for ${service_type}");
            return true;
        } catch (Exception $e) {
            error_log("Authentication: Failed to create API key ${key_name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate API key (first layer authentication)
     */
    public function validateApiKey($api_key) {
        if (empty($api_key)) {
            error_log("Authentication: API key validation failed - empty key");
            return false;
        }

        try {
            $query = "SELECT id, key_name, api_key_hash, service_type 
                      FROM ledgerai_api_keys 
                      WHERE is_active = TRUE 
                      AND (expires_at IS NULL OR expires_at > NOW())";
            $stmt = $this->db->prepare($query);
            $stmt->execute();

            while ($row = $stmt->fetch()) {
                if (password_verify($api_key, $row['api_key_hash'])) {
                    // Update usage stats
                    $this->updateApiKeyUsage($row['id']);
                    error_log("Authentication: API key validation successful for " . $row['service_type']);
                    return [
                        'id' => $row['id'],
                        'key_name' => $row['key_name'],
                        'service_type' => $row['service_type']
                    ];
                }
            }

            error_log("Authentication: API key validation failed - invalid key");
            return false;
        } catch (Exception $e) {
            error_log("Authentication: API key validation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate user credentials (second layer authentication)
     */
    public function validateUserCredentials($username, $password) {
        if (empty($username) || empty($password)) {
            error_log("Authentication: User validation failed - empty credentials");
            return false;
        }

        try {
            $query = "SELECT id, username, email, password_hash, role 
                      FROM ledgerai_users 
                      WHERE username = ? AND is_active = TRUE";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Update login stats
                $this->updateUserLogin($user['id']);
                error_log("Authentication: User validation successful for " . $username);
                return [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ];
            }

            error_log("Authentication: User validation failed for " . $username);
            return false;
        } catch (Exception $e) {
            error_log("Authentication: User validation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user scope based on role
     */
    public function getUserScope($role) {
        switch ($role) {
            case 'admin':
                return ['read', 'write', 'delete', 'admin'];
            case 'user':
                return ['read', 'write'];
            case 'readonly':
                return ['read'];
            default:
                return [];
        }
    }

    /**
     * Check if user exists
     */
    private function userExists($username) {
        $query = "SELECT id FROM ledgerai_users WHERE username = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$username]);
        return $stmt->fetch() !== false;
    }

    /**
     * Check if API key exists
     */
    private function apiKeyExists($key_name) {
        $query = "SELECT id FROM ledgerai_api_keys WHERE key_name = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$key_name]);
        return $stmt->fetch() !== false;
    }

    /**
     * Update API key usage statistics
     */
    private function updateApiKeyUsage($api_key_id) {
        $query = "UPDATE ledgerai_api_keys 
                  SET last_used_at = NOW(), usage_count = usage_count + 1 
                  WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$api_key_id]);
    }

    /**
     * Update user login statistics
     */
    private function updateUserLogin($user_id) {
        $query = "UPDATE ledgerai_users 
                  SET last_login_at = NOW(), login_count = login_count + 1 
                  WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$user_id]);
    }

    /**
     * Get authentication statistics (for admin endpoints)
     */
    public function getAuthStats() {
        try {
            $stats = [];
            
            // User stats
            $query = "SELECT COUNT(*) as total_users, 
                             SUM(CASE WHEN is_active = TRUE THEN 1 ELSE 0 END) as active_users,
                             SUM(login_count) as total_logins
                      FROM ledgerai_users";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $stats['users'] = $stmt->fetch();

            // API key stats
            $query = "SELECT COUNT(*) as total_keys,
                             SUM(CASE WHEN is_active = TRUE THEN 1 ELSE 0 END) as active_keys,
                             SUM(usage_count) as total_api_calls
                      FROM ledgerai_api_keys";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $stats['api_keys'] = $stmt->fetch();

            return $stats;
        } catch (Exception $e) {
            error_log("Authentication: Stats error: " . $e->getMessage());
            return null;
        }
    }
}
?>