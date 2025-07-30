-- LedgerAI Database Schema - Simplified for CRUD API
-- MySQL/MariaDB compatible schema for OVH hosting
-- Data normalization handled by Semantic Kernel agent

-- Set SQL mode for better compatibility
SET SQL_MODE = 'NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO';

-- Accounts table - stores bank account information and current state
CREATE TABLE ledgerai_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_iban VARCHAR(34) NOT NULL UNIQUE,
    account_number VARCHAR(50) NOT NULL,
    account_name VARCHAR(100) NULL,
    account_owner VARCHAR(100) NOT NULL,
    bank_name VARCHAR(100) NOT NULL,
    bank_code VARCHAR(20) NULL,
    bank_swift VARCHAR(11) NULL,
    currency_code CHAR(3) NOT NULL DEFAULT 'PLN',
    
    -- Current balances
    current_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    available_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    last_statement_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    
    -- Statement tracking
    last_statement_number VARCHAR(20) NULL,
    last_statement_date DATE NULL,
    last_processed_date DATETIME NULL,
    
    -- Account status
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    is_monitored BOOLEAN NOT NULL DEFAULT TRUE,
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_iban (account_iban),
    INDEX idx_bank (bank_name),
    INDEX idx_active (is_active, is_monitored)
);

-- Transactions table - stores normalized transaction data from all banks
-- Data inserted by Semantic Kernel agent after processing MT940 files
CREATE TABLE ledgerai_transactions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    
    -- MT940 Core Fields
    transaction_reference VARCHAR(50) NULL,
    bank_reference VARCHAR(50) NULL,
    statement_number VARCHAR(20) NULL,
    
    -- Transaction Details
    value_date DATE NOT NULL,
    booking_date DATE NOT NULL,
    transaction_type ENUM('DEBIT', 'CREDIT') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    currency_code CHAR(3) NOT NULL,
    
    -- Bank-specific codes
    transaction_code VARCHAR(10) NULL,
    transaction_type_code VARCHAR(20) NULL,
    swift_code VARCHAR(10) NULL,
    
    -- Transaction Description
    transaction_title TEXT NULL,
    transaction_description TEXT NULL,
    
    -- Counterparty Information
    counterparty_name VARCHAR(200) NULL,
    counterparty_account VARCHAR(50) NULL,
    counterparty_iban VARCHAR(34) NULL,
    counterparty_bank_code VARCHAR(20) NULL,
    counterparty_address TEXT NULL,
    
    -- Additional Details
    original_currency CHAR(3) NULL,
    original_amount DECIMAL(15,2) NULL,
    exchange_rate DECIMAL(10,6) NULL,
    fees DECIMAL(10,2) NULL,
    
    -- Card payments specific
    card_number_masked VARCHAR(20) NULL,
    merchant_name VARCHAR(100) NULL,
    merchant_city VARCHAR(50) NULL,
    merchant_category VARCHAR(50) NULL,
    
    -- AI Categorization (populated by Semantic Kernel agent)
    category VARCHAR(50) NULL,
    subcategory VARCHAR(50) NULL,
    tags TEXT NULL, -- JSON array of tags
    confidence_score DECIMAL(3,2) NULL, -- 0.00 to 1.00
    
    -- Processing metadata
    source_bank VARCHAR(50) NOT NULL,
    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Balance tracking
    balance_after DECIMAL(15,2) NULL,
    
    FOREIGN KEY (account_id) REFERENCES ledgerai_accounts(id) ON DELETE CASCADE,
    
    INDEX idx_account_date (account_id, value_date DESC),
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_amount (amount),
    INDEX idx_counterparty (counterparty_name),
    INDEX idx_category (category, subcategory),
    INDEX idx_merchant (merchant_name),
    INDEX idx_card (card_number_masked),
    INDEX idx_bank_ref (bank_reference),
    INDEX idx_processing_date (processed_at)
);

-- Access tokens table - for JWT authentication between components
CREATE TABLE ledgerai_access_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token_id VARCHAR(50) NOT NULL UNIQUE,
    token_hash VARCHAR(255) NOT NULL, -- Store hashed token for security
    
    -- Token details
    service_name VARCHAR(50) NOT NULL, -- 'semantic_kernel', 'react_frontend'
    user_identifier VARCHAR(100) NULL, -- For user-specific tokens
    scope TEXT NULL, -- JSON array of allowed operations
    
    -- Validity
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    last_used_at TIMESTAMP NULL,
    is_revoked BOOLEAN NOT NULL DEFAULT FALSE,
    
    -- Request tracking
    usage_count INT NOT NULL DEFAULT 0,
    last_ip_address VARCHAR(45) NULL, -- Support IPv6
    user_agent TEXT NULL,
    
    INDEX idx_token_id (token_id),
    INDEX idx_service (service_name),
    INDEX idx_expires (expires_at),
    INDEX idx_active (is_revoked, expires_at)
);

-- Transaction categories - for reference and consistency
-- 
-- Note: Authentication tables moved to migration_001_authentication.sql
CREATE TABLE ledgerai_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(50) NOT NULL,
    subcategory_name VARCHAR(50) NULL,
    description TEXT NULL,
    
    -- Visual/UX for React frontend
    color_hex VARCHAR(7) NULL,
    icon_name VARCHAR(50) NULL,
    
    -- Status
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_category (category_name, subcategory_name),
    INDEX idx_active (is_active)
);

-- Insert default categories for consistency
INSERT INTO ledgerai_categories (category_name, subcategory_name, description, color_hex) VALUES
('Food & Dining', 'Groceries', 'Supermarkets and grocery stores', '#4CAF50'),
('Food & Dining', 'Restaurants', 'Restaurants and cafes', '#8BC34A'),
('Transportation', 'Gas', 'Fuel and gas stations', '#FF9800'),
('Transportation', 'Public Transport', 'Buses, trains, taxis', '#2196F3'),
('Shopping', 'Clothing', 'Clothing and accessories', '#E91E63'),
('Shopping', 'General', 'General retail purchases', '#9C27B0'),
('Bills & Utilities', 'Rent', 'Monthly rent payments', '#F44336'),
('Bills & Utilities', 'Internet', 'Internet and phone bills', '#607D8B'),
('Health & Fitness', 'Pharmacy', 'Pharmacy and medical', '#009688'),
('Entertainment', 'General', 'Movies, games, entertainment', '#3F51B5'),
('ATM & Banking', 'Fees', 'Bank fees and charges', '#795548'),
('Income', 'Salary', 'Salary and wages', '#4CAF50'),
('Income', 'Transfer', 'Incoming transfers', '#8BC34A'),
('Other', 'Uncategorized', 'Transactions to be categorized', '#9E9E9E');

-- Example CRUD operations for PHP API:

-- Create account
-- INSERT INTO ledgerai_accounts (account_iban, account_number, account_owner, bank_name, currency_code) 
-- VALUES (?, ?, ?, ?, ?);

-- Read transactions for account
-- SELECT * FROM ledgerai_transactions 
-- WHERE account_id = ? 
-- ORDER BY value_date DESC 
-- LIMIT ? OFFSET ?;

-- Update account balance
-- UPDATE ledgerai_accounts 
-- SET current_balance = ?, available_balance = ?, last_processed_date = NOW() 
-- WHERE id = ?;

-- Insert transaction (bulk operation for Semantic Kernel agent)
-- INSERT INTO ledgerai_transactions (
--     account_id, value_date, booking_date, transaction_type, amount, currency_code,
--     transaction_title, counterparty_name, merchant_name, category, subcategory
-- ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);

-- Get account summary
-- SELECT a.*, 
--        COUNT(t.id) as transaction_count,
--        SUM(CASE WHEN t.transaction_type = 'DEBIT' THEN t.amount ELSE 0 END) as total_debits,
--        SUM(CASE WHEN t.transaction_type = 'CREDIT' THEN t.amount ELSE 0 END) as total_credits
-- FROM ledgerai_accounts a
-- LEFT JOIN ledgerai_transactions t ON a.id = t.account_id AND t.value_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
-- WHERE a.id = ?
-- GROUP BY a.id; 