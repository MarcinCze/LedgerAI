-- LedgerAI Database Migration 001: Authentication System
-- Add user management and API key authentication tables
-- 
-- Run this after the main database_schema.sql
-- This adds two-layer authentication: API Keys + Username/Password

-- Users table - for username/password authentication (second layer)
CREATE TABLE ledgerai_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user', 'readonly') NOT NULL DEFAULT 'user',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    last_login_at TIMESTAMP NULL,
    login_count INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_active (is_active),
    INDEX idx_role (role)
);

-- API Keys table - for service authentication (first layer)
CREATE TABLE ledgerai_api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(100) NOT NULL UNIQUE,
    api_key_hash VARCHAR(255) NOT NULL,
    service_type VARCHAR(50) NOT NULL, -- 'frontend', 'semantic_kernel', etc
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    expires_at TIMESTAMP NULL,
    last_used_at TIMESTAMP NULL,
    usage_count INT NOT NULL DEFAULT 0,
    created_by_user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_key_name (key_name),
    INDEX idx_service (service_type),
    INDEX idx_active (is_active),
    FOREIGN KEY (created_by_user_id) REFERENCES ledgerai_users(id) ON DELETE SET NULL
);

-- Example users that will be auto-created from environment variables:
-- 
-- DIRECT-USER (Human Administrator):
-- - Username: From DIRECT_USER_USERNAME environment variable
-- - Role: admin (full access)
-- - Purpose: Direct human access to API for management
--
-- AGENT-AI (Semantic Kernel Service):
-- - Username: From AGENT_AI_USERNAME environment variable  
-- - Role: user (read/write access)
-- - Purpose: Automated AI agent access for transaction processing
--
-- API Keys that will be auto-created:
-- - FRONTEND_API_KEY: For React/web frontend applications
-- - SEMANTIC_KERNEL_API_KEY: For AI agent service authentication

-- Migration complete - users and API keys will be auto-created on next deployment