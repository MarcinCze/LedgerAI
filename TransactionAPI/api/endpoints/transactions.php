<?php
/**
 * LedgerAI Transaction API - Transactions Endpoint
 * CRUD operations for transactions with bulk operations
 */

function handleTransactions($db, $method, $resource_id) {
    switch ($method) {
        case 'GET':
            if ($resource_id) {
                getTransaction($db, $resource_id);
            } else {
                getTransactions($db);
            }
            break;
            
        case 'POST':
            // Check if it's a bulk operation
            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['transactions']) && is_array($input['transactions'])) {
                createTransactionsBulk($db);
            } else {
                createTransaction($db);
            }
            break;
            
        case 'PUT':
            if ($resource_id) {
                updateTransaction($db, $resource_id);
            } else {
                Response::error('Transaction ID required for update', 400);
            }
            break;
            
        case 'DELETE':
            if ($resource_id) {
                deleteTransaction($db, $resource_id);
            } else {
                Response::error('Transaction ID required for deletion', 400);
            }
            break;
            
        default:
            Response::methodNotAllowed();
    }
}

/**
 * Get transactions with filtering and pagination
 */
function getTransactions($db) {
    $page = (int) ($_GET['page'] ?? 1);
    $limit = min((int) ($_GET['limit'] ?? 50), 100);
    $offset = ($page - 1) * $limit;
    
    $where_conditions = [];
    $params = [];
    
    // Filter by account ID
    if (isset($_GET['account_id'])) {
        $where_conditions[] = "t.account_id = ?";
        $params[] = $_GET['account_id'];
    }
    
    // Filter by date range
    if (isset($_GET['date_from'])) {
        $where_conditions[] = "t.value_date >= ?";
        $params[] = $_GET['date_from'];
    }
    
    if (isset($_GET['date_to'])) {
        $where_conditions[] = "t.value_date <= ?";
        $params[] = $_GET['date_to'];
    }
    
    // Filter by transaction type
    if (isset($_GET['type'])) {
        $where_conditions[] = "t.transaction_type = ?";
        $params[] = strtoupper($_GET['type']);
    }
    
    // Filter by category
    if (isset($_GET['category'])) {
        $where_conditions[] = "t.category = ?";
        $params[] = $_GET['category'];
    }
    
    // Filter by amount range
    if (isset($_GET['amount_min'])) {
        $where_conditions[] = "t.amount >= ?";
        $params[] = $_GET['amount_min'];
    }
    
    if (isset($_GET['amount_max'])) {
        $where_conditions[] = "t.amount <= ?";
        $params[] = $_GET['amount_max'];
    }
    
    // Search in transaction titles and counterparty names
    if (isset($_GET['search'])) {
        $search_term = '%' . $_GET['search'] . '%';
        $where_conditions[] = "(t.transaction_title LIKE ? OR t.counterparty_name LIKE ? OR t.merchant_name LIKE ?)";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total 
                   FROM ledgerai_transactions t 
                   LEFT JOIN ledgerai_accounts a ON t.account_id = a.id 
                   $where_clause";
    
    $stmt = $db->prepare($count_query);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    
    // Get transactions
    $query = "SELECT 
                t.id, t.account_id, t.transaction_reference, t.bank_reference,
                t.statement_number, t.value_date, t.booking_date, t.transaction_type,
                t.amount, t.currency_code, t.transaction_code, t.transaction_type_code,
                t.transaction_title, t.transaction_description,
                t.counterparty_name, t.counterparty_account, t.counterparty_iban,
                t.card_number_masked, t.merchant_name, t.merchant_city,
                t.category, t.subcategory, t.confidence_score, t.tags,
                t.source_bank, t.processed_at, t.balance_after,
                a.account_iban, a.bank_name
              FROM ledgerai_transactions t
              LEFT JOIN ledgerai_accounts a ON t.account_id = a.id
              $where_clause 
              ORDER BY t.value_date DESC, t.id DESC
              LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
    
    // Format numbers and parse JSON
    foreach ($transactions as &$transaction) {
        $transaction['amount'] = (float) $transaction['amount'];
        $transaction['balance_after'] = $transaction['balance_after'] ? (float) $transaction['balance_after'] : null;
        $transaction['confidence_score'] = $transaction['confidence_score'] ? (float) $transaction['confidence_score'] : null;
        $transaction['tags'] = $transaction['tags'] ? json_decode($transaction['tags'], true) : null;
    }
    
    Response::paginated($transactions, $total, $page, $limit, 'Transactions retrieved successfully');
}

/**
 * Get single transaction by ID
 */
function getTransaction($db, $id) {
    $query = "SELECT 
                t.*,
                a.account_iban, a.bank_name, a.account_owner
              FROM ledgerai_transactions t
              LEFT JOIN ledgerai_accounts a ON t.account_id = a.id
              WHERE t.id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$id]);
    $transaction = $stmt->fetch();
    
    if (!$transaction) {
        Response::notFound('Transaction not found');
    }
    
    // Format numbers and parse JSON
    $transaction['amount'] = (float) $transaction['amount'];
    $transaction['balance_after'] = $transaction['balance_after'] ? (float) $transaction['balance_after'] : null;
    $transaction['confidence_score'] = $transaction['confidence_score'] ? (float) $transaction['confidence_score'] : null;
    $transaction['tags'] = $transaction['tags'] ? json_decode($transaction['tags'], true) : null;
    
    Response::success($transaction, 'Transaction retrieved successfully');
}

/**
 * Create single transaction
 */
function createTransaction($db) {
    $data = getJsonInput();
    
    validateRequired($data, [
        'account_id', 'value_date', 'booking_date', 
        'transaction_type', 'amount', 'currency_code', 'source_bank'
    ]);
    
    // Validate transaction type
    if (!in_array(strtoupper($data['transaction_type']), ['DEBIT', 'CREDIT'])) {
        Response::validation(['Invalid transaction type. Must be DEBIT or CREDIT']);
    }
    
    // Verify account exists
    $check_query = "SELECT id FROM ledgerai_accounts WHERE id = ?";
    $stmt = $db->prepare($check_query);
    $stmt->execute([$data['account_id']]);
    
    if (!$stmt->fetch()) {
        Response::validation(['Account does not exist']);
    }
    
    $transaction_id = insertTransaction($db, $data);
    
    if ($transaction_id) {
        Response::created(['id' => $transaction_id], 'Transaction created successfully');
    } else {
        Response::serverError('Failed to create transaction');
    }
}

/**
 * Bulk create transactions (for Semantic Kernel agent)
 */
function createTransactionsBulk($db) {
    $input = getJsonInput();
    
    if (!isset($input['transactions']) || !is_array($input['transactions'])) {
        Response::validation(['Missing or invalid transactions array']);
    }
    
    $transactions = $input['transactions'];
    $account_id = $input['account_id'] ?? null;
    
    if (empty($transactions)) {
        Response::validation(['Transactions array cannot be empty']);
    }
    
    // Verify account exists if provided
    if ($account_id) {
        $check_query = "SELECT id FROM ledgerai_accounts WHERE id = ?";
        $stmt = $db->prepare($check_query);
        $stmt->execute([$account_id]);
        
        if (!$stmt->fetch()) {
            Response::validation(['Account does not exist']);
        }
    }
    
    $db->beginTransaction();
    
    try {
        $created_ids = [];
        $errors = [];
        
        foreach ($transactions as $index => $data) {
            // Use provided account_id if not specified in transaction
            if (!isset($data['account_id']) && $account_id) {
                $data['account_id'] = $account_id;
            }
            
            // Validate required fields
            $required = ['account_id', 'value_date', 'booking_date', 'transaction_type', 'amount', 'currency_code', 'source_bank'];
            $missing = [];
            
            foreach ($required as $field) {
                if (!isset($data[$field]) || $data[$field] === '') {
                    $missing[] = $field;
                }
            }
            
            if (!empty($missing)) {
                $errors[] = "Transaction $index: Missing required fields: " . implode(', ', $missing);
                continue;
            }
            
            // Validate transaction type
            if (!in_array(strtoupper($data['transaction_type']), ['DEBIT', 'CREDIT'])) {
                $errors[] = "Transaction $index: Invalid transaction type";
                continue;
            }
            
            $transaction_id = insertTransaction($db, $data);
            
            if ($transaction_id) {
                $created_ids[] = $transaction_id;
            } else {
                $errors[] = "Transaction $index: Failed to insert";
            }
        }
        
        if (!empty($errors) && empty($created_ids)) {
            $db->rollBack();
            Response::validation($errors);
        }
        
        $db->commit();
        
        $response_data = [
            'created_count' => count($created_ids),
            'error_count' => count($errors),
            'created_ids' => $created_ids
        ];
        
        if (!empty($errors)) {
            $response_data['errors'] = $errors;
        }
        
        Response::created($response_data, 'Bulk transaction creation completed');
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Bulk transaction error: " . $e->getMessage());
        Response::serverError('Bulk transaction creation failed');
    }
}

/**
 * Helper function to insert a transaction
 */
function insertTransaction($db, $data) {
    $query = "INSERT INTO ledgerai_transactions (
                account_id, transaction_reference, bank_reference, statement_number,
                value_date, booking_date, transaction_type, amount, currency_code,
                transaction_code, transaction_type_code, swift_code,
                transaction_title, transaction_description,
                counterparty_name, counterparty_account, counterparty_iban,
                counterparty_bank_code, counterparty_address,
                original_currency, original_amount, exchange_rate, fees,
                card_number_masked, merchant_name, merchant_city, merchant_category,
                category, subcategory, tags, confidence_score,
                source_bank, balance_after
              ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $db->prepare($query);
    $result = $stmt->execute([
        $data['account_id'],
        $data['transaction_reference'] ?? null,
        $data['bank_reference'] ?? null,
        $data['statement_number'] ?? null,
        $data['value_date'],
        $data['booking_date'],
        strtoupper($data['transaction_type']),
        $data['amount'],
        $data['currency_code'],
        $data['transaction_code'] ?? null,
        $data['transaction_type_code'] ?? null,
        $data['swift_code'] ?? null,
        $data['transaction_title'] ?? null,
        $data['transaction_description'] ?? null,
        $data['counterparty_name'] ?? null,
        $data['counterparty_account'] ?? null,
        $data['counterparty_iban'] ?? null,
        $data['counterparty_bank_code'] ?? null,
        $data['counterparty_address'] ?? null,
        $data['original_currency'] ?? null,
        $data['original_amount'] ?? null,
        $data['exchange_rate'] ?? null,
        $data['fees'] ?? null,
        $data['card_number_masked'] ?? null,
        $data['merchant_name'] ?? null,
        $data['merchant_city'] ?? null,
        $data['merchant_category'] ?? null,
        $data['category'] ?? null,
        $data['subcategory'] ?? null,
        isset($data['tags']) ? json_encode($data['tags']) : null,
        $data['confidence_score'] ?? null,
        $data['source_bank'],
        $data['balance_after'] ?? null
    ]);
    
    return $result ? $db->lastInsertId() : false;
}

/**
 * Update transaction
 */
function updateTransaction($db, $id) {
    $data = getJsonInput();
    
    // Check if transaction exists
    $check_query = "SELECT id FROM ledgerai_transactions WHERE id = ?";
    $stmt = $db->prepare($check_query);
    $stmt->execute([$id]);
    
    if (!$stmt->fetch()) {
        Response::notFound('Transaction not found');
    }
    
    $updates = [];
    $params = [];
    
    // Allow updating categorization and metadata
    $updatable_fields = [
        'category', 'subcategory', 'tags', 'confidence_score',
        'transaction_title', 'transaction_description'
    ];
    
    foreach ($updatable_fields as $field) {
        if (isset($data[$field])) {
            if ($field === 'tags') {
                $updates[] = "$field = ?";
                $params[] = json_encode($data[$field]);
            } else {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
    }
    
    if (empty($updates)) {
        Response::validation(['No valid fields to update']);
    }
    
    $params[] = $id;
    
    $query = "UPDATE ledgerai_transactions SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $db->prepare($query);
    $result = $stmt->execute($params);
    
    if ($result) {
        Response::success(['id' => $id], 'Transaction updated successfully');
    } else {
        Response::serverError('Failed to update transaction');
    }
}

/**
 * Delete transaction
 */
function deleteTransaction($db, $id) {
    // Check if transaction exists
    $check_query = "SELECT id FROM ledgerai_transactions WHERE id = ?";
    $stmt = $db->prepare($check_query);
    $stmt->execute([$id]);
    
    if (!$stmt->fetch()) {
        Response::notFound('Transaction not found');
    }
    
    $query = "DELETE FROM ledgerai_transactions WHERE id = ?";
    $stmt = $db->prepare($query);
    $result = $stmt->execute([$id]);
    
    if ($result) {
        Response::success(['id' => $id], 'Transaction deleted successfully');
    } else {
        Response::serverError('Failed to delete transaction');
    }
}
?> 