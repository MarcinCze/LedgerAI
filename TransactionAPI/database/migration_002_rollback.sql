-- Migration 002 Rollback: MT940 Field Enhancement
-- Reverts all changes made by migration_002_mt940_enhancement.sql
-- Date: 2025-01-28
-- WARNING: This will permanently delete data in the new fields!

USE ledgerai;

-- Drop the enhanced view
DROP VIEW IF EXISTS v_mt940_transactions;

-- Remove indexes for new fields
ALTER TABLE ledgerai_transactions 
DROP INDEX idx_entry_date,
DROP INDEX idx_transaction_entry_type,
DROP INDEX idx_transaction_subcode,
DROP INDEX idx_bank_code,
DROP INDEX idx_counterparty_city,
DROP INDEX idx_merchant_info;

-- Remove new MT940 fields
ALTER TABLE ledgerai_transactions 
DROP COLUMN entry_date,
DROP COLUMN transaction_entry_type,
DROP COLUMN transaction_subcode,
DROP COLUMN bank_code,
DROP COLUMN counterparty_address_or_city,
DROP COLUMN counterparty_full_address,
DROP COLUMN merchant_info,
DROP COLUMN additional_info,
DROP COLUMN raw_transaction_type;

-- Remove enhanced comments from existing fields
ALTER TABLE ledgerai_transactions 
MODIFY COLUMN transaction_type ENUM('DEBIT', 'CREDIT') NOT NULL,
MODIFY COLUMN bank_reference VARCHAR(50) NULL,
MODIFY COLUMN counterparty_iban VARCHAR(34) NULL;

-- Verify rollback
SELECT 
    'Migration 002 rollback completed successfully' as status,
    COUNT(*) as total_transactions
FROM ledgerai_transactions;

-- Show restored table structure
DESCRIBE ledgerai_transactions;
