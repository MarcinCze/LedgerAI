<?php
/**
 * LedgerAI Transaction API - Accounts Endpoint
 * CRUD operations for bank accounts
 */

function handleAccounts($db, $method, $resource_id) {
    switch ($method) {
        case 'GET':
            if ($resource_id) {
                getAccount($db, $resource_id);
            } else {
                getAccounts($db);
            }
            break;
            
        case 'POST':
            createAccount($db);
            break;
            
        case 'PUT':
            if ($resource_id) {
                updateAccount($db, $resource_id);
            } else {
                Response::error('Account ID required for update', 400);
            }
            break;
            
        case 'DELETE':
            if ($resource_id) {
                deleteAccount($db, $resource_id);
            } else {
                Response::error('Account ID required for deletion', 400);
            }
            break;
            
        default:
            Response::methodNotAllowed();
    }
}

/**
 * Get all accounts with optional filtering and pagination
 */
function getAccounts($db) {
    $page = (int) ($_GET['page'] ?? 1);
    $limit = min((int) ($_GET['limit'] ?? 50), 100); // Max 100 items per page
    $offset = ($page - 1) * $limit;
    
    $where_conditions = [];
    $params = [];
    
    // Filter by active status
    if (isset($_GET['is_active'])) {
        $where_conditions[] = "is_active = ?";
        $params[] = $_GET['is_active'] === 'true' ? 1 : 0;
    }
    
    // Filter by bank
    if (isset($_GET['bank_name'])) {
        $where_conditions[] = "bank_name LIKE ?";
        $params[] = '%' . $_GET['bank_name'] . '%';
    }
    
    // Filter by currency
    if (isset($_GET['currency'])) {
        $where_conditions[] = "currency_code = ?";
        $params[] = $_GET['currency'];
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM ledgerai_accounts $where_clause";
    $stmt = $db->prepare($count_query);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    
    // Get accounts
    $query = "SELECT 
                id, account_iban, account_number, account_name, account_owner,
                bank_name, bank_code, bank_swift, currency_code,
                current_balance, available_balance, last_statement_balance,
                last_statement_number, last_statement_date, last_processed_date,
                is_active, is_monitored, created_at, updated_at
              FROM ledgerai_accounts 
              $where_clause 
              ORDER BY created_at DESC 
              LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $accounts = $stmt->fetchAll();
    
    // Format numbers
    foreach ($accounts as &$account) {
        $account['current_balance'] = (float) $account['current_balance'];
        $account['available_balance'] = (float) $account['available_balance'];
        $account['last_statement_balance'] = (float) $account['last_statement_balance'];
        $account['is_active'] = (bool) $account['is_active'];
        $account['is_monitored'] = (bool) $account['is_monitored'];
    }
    
    Response::paginated($accounts, $total, $page, $limit, 'Accounts retrieved successfully');
}

/**
 * Get single account by ID
 */
function getAccount($db, $id) {
    $query = "SELECT 
                id, account_iban, account_number, account_name, account_owner,
                bank_name, bank_code, bank_swift, currency_code,
                current_balance, available_balance, last_statement_balance,
                last_statement_number, last_statement_date, last_processed_date,
                is_active, is_monitored, created_at, updated_at
              FROM ledgerai_accounts 
              WHERE id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$id]);
    $account = $stmt->fetch();
    
    if (!$account) {
        Response::notFound('Account not found');
    }
    
    // Format numbers
    $account['current_balance'] = (float) $account['current_balance'];
    $account['available_balance'] = (float) $account['available_balance'];
    $account['last_statement_balance'] = (float) $account['last_statement_balance'];
    $account['is_active'] = (bool) $account['is_active'];
    $account['is_monitored'] = (bool) $account['is_monitored'];
    
    Response::success($account, 'Account retrieved successfully');
}

/**
 * Create new account
 */
function createAccount($db) {
    $data = getJsonInput();
    
    validateRequired($data, [
        'account_iban', 'account_number', 'account_owner', 
        'bank_name', 'currency_code'
    ]);
    
    // Validate IBAN format (basic)
    if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/', $data['account_iban'])) {
        Response::validation(['Invalid IBAN format']);
    }
    
    // Check if account already exists
    $check_query = "SELECT id FROM ledgerai_accounts WHERE account_iban = ?";
    $stmt = $db->prepare($check_query);
    $stmt->execute([$data['account_iban']]);
    
    if ($stmt->fetch()) {
        Response::validation(['Account with this IBAN already exists']);
    }
    
    $query = "INSERT INTO ledgerai_accounts (
                account_iban, account_number, account_name, account_owner,
                bank_name, bank_code, bank_swift, currency_code,
                current_balance, available_balance, last_statement_balance,
                is_active, is_monitored
              ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $db->prepare($query);
    $result = $stmt->execute([
        $data['account_iban'],
        $data['account_number'],
        $data['account_name'] ?? null,
        $data['account_owner'],
        $data['bank_name'],
        $data['bank_code'] ?? null,
        $data['bank_swift'] ?? null,
        $data['currency_code'],
        $data['current_balance'] ?? 0.00,
        $data['available_balance'] ?? 0.00,
        $data['last_statement_balance'] ?? 0.00,
        $data['is_active'] ?? true,
        $data['is_monitored'] ?? true
    ]);
    
    if ($result) {
        $account_id = $db->lastInsertId();
        Response::created(['id' => $account_id], 'Account created successfully');
    } else {
        Response::serverError('Failed to create account');
    }
}

/**
 * Update account
 */
function updateAccount($db, $id) {
    $data = getJsonInput();
    
    // Check if account exists
    $check_query = "SELECT id FROM ledgerai_accounts WHERE id = ?";
    $stmt = $db->prepare($check_query);
    $stmt->execute([$id]);
    
    if (!$stmt->fetch()) {
        Response::notFound('Account not found');
    }
    
    $updates = [];
    $params = [];
    
    // Build dynamic update query
    $updatable_fields = [
        'account_name', 'account_owner', 'bank_name', 'bank_code', 'bank_swift',
        'current_balance', 'available_balance', 'last_statement_balance',
        'last_statement_number', 'last_statement_date', 'last_processed_date',
        'is_active', 'is_monitored'
    ];
    
    foreach ($updatable_fields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
        }
    }
    
    if (empty($updates)) {
        Response::validation(['No valid fields to update']);
    }
    
    $updates[] = "updated_at = NOW()";
    $params[] = $id;
    
    $query = "UPDATE ledgerai_accounts SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $db->prepare($query);
    $result = $stmt->execute($params);
    
    if ($result) {
        Response::success(['id' => $id], 'Account updated successfully');
    } else {
        Response::serverError('Failed to update account');
    }
}

/**
 * Delete account (soft delete by setting is_active = false)
 */
function deleteAccount($db, $id) {
    // Check if account exists
    $check_query = "SELECT id FROM ledgerai_accounts WHERE id = ?";
    $stmt = $db->prepare($check_query);
    $stmt->execute([$id]);
    
    if (!$stmt->fetch()) {
        Response::notFound('Account not found');
    }
    
    // Soft delete - set is_active to false
    $query = "UPDATE ledgerai_accounts SET is_active = FALSE, updated_at = NOW() WHERE id = ?";
    $stmt = $db->prepare($query);
    $result = $stmt->execute([$id]);
    
    if ($result) {
        Response::success(['id' => $id], 'Account deactivated successfully');
    } else {
        Response::serverError('Failed to deactivate account');
    }
}
?> 