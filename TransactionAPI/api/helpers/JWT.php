<?php
/**
 * LedgerAI Transaction API - JWT Helper
 * Handles JWT authentication for secure API access
 */

class JWT {
    private $secret_key;
    private $algorithm = 'HS256';
    private $db;

    public function __construct($database) {
        $this->secret_key = $_ENV['JWT_SECRET'] ?? 'your-secret-key-change-this';
        $this->db = $database;
    }

    /**
     * Generate JWT token for service
     */
    public function generateToken($service_name, $user_identifier = null, $scope = [], $expires_in = 3600) {
        $token_id = bin2hex(random_bytes(16));
        $issued_at = time();
        $expires_at = $issued_at + $expires_in;

        $payload = [
            'iss' => 'ledgerai-api',
            'aud' => $service_name,
            'iat' => $issued_at,
            'exp' => $expires_at,
            'jti' => $token_id,
            'uid' => $user_identifier,
            'scope' => $scope
        ];

        $token = $this->encode($payload);
        $token_hash = password_hash($token, PASSWORD_DEFAULT);

        // Store token in database
        $query = "INSERT INTO ledgerai_access_tokens 
                  (token_id, token_hash, service_name, user_identifier, scope, expires_at) 
                  VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            $token_id,
            $token_hash,
            $service_name,
            $user_identifier,
            json_encode($scope),
            date('Y-m-d H:i:s', $expires_at)
        ]);

        return $token;
    }

    /**
     * Validate JWT token
     */
    public function validateToken($token) {
        try {
            error_log("JWT Validation: Starting validation for token");
            $payload = $this->decode($token);
            error_log("JWT Validation: Token decoded successfully, jti: " . $payload['jti']);
            
            // Check if token exists and is not revoked
            $query = "SELECT * FROM ledgerai_access_tokens 
                     WHERE token_id = ? AND is_revoked = FALSE AND expires_at > NOW()";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$payload['jti']]);
            $token_record = $stmt->fetch();

            if (!$token_record) {
                error_log("JWT Validation: Token not found in database or expired");
                return false;
            }
            
            error_log("JWT Validation: Token found in database, validation successful");

            // Update last used
            $update_query = "UPDATE ledgerai_access_tokens 
                           SET last_used_at = NOW(), usage_count = usage_count + 1,
                               last_ip_address = ?, user_agent = ?
                           WHERE token_id = ?";
            
            $stmt = $this->db->prepare($update_query);
            $stmt->execute([
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $payload['jti']
            ]);

            return $payload;
        } catch (Exception $e) {
            error_log("JWT validation error: " . $e->getMessage());
            error_log("JWT validation error file: " . $e->getFile() . " line: " . $e->getLine());
            return false;
        }
    }

    /**
     * Simple JWT encode (basic implementation)
     */
    private function encode($payload) {
        $header = json_encode(['typ' => 'JWT', 'alg' => $this->algorithm]);
        $payload = json_encode($payload);
        
        $base64_header = $this->base64url_encode($header);
        $base64_payload = $this->base64url_encode($payload);
        
        $signature = hash_hmac('sha256', $base64_header . "." . $base64_payload, $this->secret_key, true);
        $base64_signature = $this->base64url_encode($signature);
        
        return $base64_header . "." . $base64_payload . "." . $base64_signature;
    }

    /**
     * Simple JWT decode (basic implementation)
     */
    private function decode($jwt) {
        error_log("JWT Decode: Starting decode process");
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new Exception('Invalid JWT format');
        }

        $header = json_decode($this->base64url_decode($parts[0]), true);
        $payload = json_decode($this->base64url_decode($parts[1]), true);
        $signature = $this->base64url_decode($parts[2]);

        error_log("JWT Decode: Header and payload decoded successfully");
        
        // Verify signature
        $expected_signature = hash_hmac('sha256', $parts[0] . "." . $parts[1], $this->secret_key, true);
        
        error_log("JWT Decode: Secret key being used: " . (empty($this->secret_key) ? "EMPTY" : "SET"));
        
        if (!hash_equals($signature, $expected_signature)) {
            error_log("JWT Decode: Signature verification failed");
            throw new Exception('Invalid JWT signature');
        }
        
        error_log("JWT Decode: Signature verified successfully");

        // Check expiration
        if ($payload['exp'] < time()) {
            throw new Exception('JWT token expired');
        }

        return $payload;
    }

    private function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64url_decode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}
?> 