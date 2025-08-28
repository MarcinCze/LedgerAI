-- Migration 002: MT940 Field Enhancement
-- Adds missing fields that are parsed from STA files but missing from database schema
-- Date: 2025-01-28
-- Description: Enhance transactions table with MT940-specific fields for better data capture

-- Add missing MT940 fields to ledgerai_transactions table
ALTER TABLE ledgerai_transactions 
ADD COLUMN entry_date DATE NULL COMMENT 'Entry date from :61: (MMDD format)' AFTER value_date,
ADD COLUMN transaction_entry_type VARCHAR(10) NULL COMMENT 'MT940 entry type: S, F, R, C, D, P, E, I' AFTER transaction_type,
ADD COLUMN transaction_subcode VARCHAR(20) NULL COMMENT 'Transaction subcode from ~00 field (e.g., VE02, IBCB)' AFTER transaction_entry_type,
ADD COLUMN bank_code VARCHAR(20) NULL COMMENT 'Bank code from ~30 field (e.g., 10501357)' AFTER transaction_subcode,
ADD COLUMN counterparty_address_or_city VARCHAR(100) NULL COMMENT 'City/address from ~33 field' AFTER counterparty_name,
ADD COLUMN counterparty_full_address TEXT NULL COMMENT 'Full address with postal code from ~62 field' AFTER counterparty_address_or_city,
ADD COLUMN merchant_info TEXT NULL COMMENT 'Merchant/website info from ~22 field' AFTER merchant_name,
ADD COLUMN additional_info TEXT NULL COMMENT 'Additional MT940 subfields (~34, ~35, ~36, etc.)' AFTER merchant_info,
ADD COLUMN raw_transaction_type CHAR(1) NULL COMMENT 'Raw D/C indicator from :61:' AFTER amount;

-- Add indexes for new fields to improve query performance
ALTER TABLE ledgerai_transactions 
ADD INDEX idx_entry_date (entry_date),
ADD INDEX idx_transaction_entry_type (transaction_entry_type),
ADD INDEX idx_transaction_subcode (transaction_subcode),
ADD INDEX idx_bank_code (bank_code),
ADD INDEX idx_counterparty_city (counterparty_address_or_city),
ADD INDEX idx_merchant_info (merchant_info(100));

-- Update existing transactions to set default values for new fields
UPDATE ledgerai_transactions 
SET 
    entry_date = value_date,
    transaction_entry_type = 'S',
    raw_transaction_type = CASE 
        WHEN transaction_type = 'DEBIT' THEN 'D'
        WHEN transaction_type = 'CREDIT' THEN 'C'
        ELSE NULL
    END
WHERE entry_date IS NULL;

-- Add comments to existing fields for better documentation
ALTER TABLE ledgerai_transactions 
MODIFY COLUMN transaction_type ENUM('DEBIT', 'CREDIT') NOT NULL COMMENT 'Transaction direction (DEBIT=out, CREDIT=in)',
MODIFY COLUMN bank_reference VARCHAR(50) NULL COMMENT 'Bank reference from ~31 field',
MODIFY COLUMN counterparty_iban VARCHAR(34) NULL COMMENT 'Counterparty IBAN from ~38 field';

-- Create a view for easier MT940 data access
CREATE OR REPLACE VIEW v_mt940_transactions AS
SELECT 
    t.*,
    -- Enhanced MT940 information
    CONCAT(t.transaction_entry_type, ' - ', t.transaction_subcode) as full_transaction_code,
    CONCAT(t.counterparty_name, ' ', COALESCE(t.counterparty_address_or_city, '')) as full_counterparty_info,
    -- Date information
    DATEDIFF(t.value_date, t.entry_date) as days_between_value_entry,
    -- Amount formatting
    CONCAT(t.amount, ' ', t.currency_code) as formatted_amount,
    -- MT940 subfield summary
    CASE 
        WHEN t.merchant_info IS NOT NULL THEN CONCAT('Merchant: ', t.merchant_info)
        WHEN t.counterparty_full_address IS NOT NULL THEN CONCAT('Address: ', LEFT(t.counterparty_full_address, 50))
        ELSE NULL
    END as additional_details
FROM ledgerai_transactions t
ORDER BY t.value_date DESC, t.id DESC;

-- Insert sample data to demonstrate new fields (optional - remove in production)
-- INSERT INTO ledgerai_transactions (
--     account_id, value_date, entry_date, transaction_type, transaction_entry_type, 
--     transaction_subcode, bank_code, amount, currency_code, transaction_title,
--     counterparty_name, counterparty_address_or_city, counterparty_full_address,
--     merchant_info, additional_info, source_bank
-- ) VALUES (
--     1, '2025-01-06', '2025-01-06', 'DEBIT', 'S', 'VE02', '10501357', 29.99, 'PLN',
--     'Płatność kartą', 'LPP SINSAY', 'BYTOM', '600 TARNOWSKIE GÓRY',
--     'Online shopping', '~34:073', 'ING'
-- );

-- Verify the migration
SELECT 
    'Migration 002 completed successfully' as status,
    COUNT(*) as total_transactions,
    COUNT(entry_date) as transactions_with_entry_date,
    COUNT(transaction_entry_type) as transactions_with_entry_type,
    COUNT(transaction_subcode) as transactions_with_subcode
FROM ledgerai_transactions;

-- Show new table structure
DESCRIBE ledgerai_transactions;
