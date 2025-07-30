<?php
/**
 * LedgerAI Transaction API - Categories Endpoint
 * CRUD operations for transaction categories
 */

function handleCategories($db, $method, $resource_id) {
    switch ($method) {
        case 'GET':
            if ($resource_id) {
                getCategory($db, $resource_id);
            } else {
                getCategories($db);
            }
            break;
            
        case 'POST':
            createCategory($db);
            break;
            
        case 'PUT':
            if ($resource_id) {
                updateCategory($db, $resource_id);
            } else {
                Response::error('Category ID required for update', 400);
            }
            break;
            
        case 'DELETE':
            if ($resource_id) {
                deleteCategory($db, $resource_id);
            } else {
                Response::error('Category ID required for deletion', 400);
            }
            break;
            
        default:
            Response::methodNotAllowed();
    }
}

/**
 * Get all categories with optional filtering
 */
function getCategories($db) {
    $where_conditions = [];
    $params = [];
    
    // Filter by active status
    if (isset($_GET['is_active'])) {
        $where_conditions[] = "is_active = ?";
        $params[] = $_GET['is_active'] === 'true' ? 1 : 0;
    }
    
    // Filter by category name
    if (isset($_GET['category'])) {
        $where_conditions[] = "category_name = ?";
        $params[] = $_GET['category'];
    }
    
    // Search in names and descriptions
    if (isset($_GET['search'])) {
        $search_term = '%' . $_GET['search'] . '%';
        $where_conditions[] = "(category_name LIKE ? OR subcategory_name LIKE ? OR description LIKE ?)";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $query = "SELECT 
                id, category_name, subcategory_name, description,
                color_hex, icon_name, is_active, created_at
              FROM ledgerai_categories 
              $where_clause 
              ORDER BY category_name, subcategory_name";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $categories = $stmt->fetchAll();
    
    // Format boolean
    foreach ($categories as &$category) {
        $category['is_active'] = (bool) $category['is_active'];
    }
    
    // Group by category if requested
    if (isset($_GET['grouped']) && $_GET['grouped'] === 'true') {
        $grouped = [];
        foreach ($categories as $category) {
            $cat_name = $category['category_name'];
            if (!isset($grouped[$cat_name])) {
                $grouped[$cat_name] = [
                    'category_name' => $cat_name,
                    'subcategories' => []
                ];
            }
            
            $grouped[$cat_name]['subcategories'][] = [
                'id' => $category['id'],
                'subcategory_name' => $category['subcategory_name'],
                'description' => $category['description'],
                'color_hex' => $category['color_hex'],
                'icon_name' => $category['icon_name'],
                'is_active' => $category['is_active'],
                'created_at' => $category['created_at']
            ];
        }
        
        Response::success(array_values($grouped), 'Categories retrieved successfully (grouped)');
    } else {
        Response::success($categories, 'Categories retrieved successfully');
    }
}

/**
 * Get single category by ID
 */
function getCategory($db, $id) {
    $query = "SELECT 
                id, category_name, subcategory_name, description,
                color_hex, icon_name, is_active, created_at
              FROM ledgerai_categories 
              WHERE id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$id]);
    $category = $stmt->fetch();
    
    if (!$category) {
        Response::notFound('Category not found');
    }
    
    $category['is_active'] = (bool) $category['is_active'];
    
    Response::success($category, 'Category retrieved successfully');
}

/**
 * Create new category
 */
function createCategory($db) {
    $data = getJsonInput();
    
    validateRequired($data, ['category_name']);
    
    // Check if category combination already exists
    $check_query = "SELECT id FROM ledgerai_categories WHERE category_name = ? AND subcategory_name = ?";
    $stmt = $db->prepare($check_query);
    $stmt->execute([
        $data['category_name'],
        $data['subcategory_name'] ?? null
    ]);
    
    if ($stmt->fetch()) {
        Response::validation(['Category with this name and subcategory already exists']);
    }
    
    // Validate color hex format if provided
    if (isset($data['color_hex']) && !preg_match('/^#[0-9A-Fa-f]{6}$/', $data['color_hex'])) {
        Response::validation(['Invalid color hex format. Use #RRGGBB format']);
    }
    
    $query = "INSERT INTO ledgerai_categories (
                category_name, subcategory_name, description,
                color_hex, icon_name, is_active
              ) VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $db->prepare($query);
    $result = $stmt->execute([
        $data['category_name'],
        $data['subcategory_name'] ?? null,
        $data['description'] ?? null,
        $data['color_hex'] ?? null,
        $data['icon_name'] ?? null,
        $data['is_active'] ?? true
    ]);
    
    if ($result) {
        $category_id = $db->lastInsertId();
        Response::created(['id' => $category_id], 'Category created successfully');
    } else {
        Response::serverError('Failed to create category');
    }
}

/**
 * Update category
 */
function updateCategory($db, $id) {
    $data = getJsonInput();
    
    // Check if category exists
    $check_query = "SELECT id, category_name, subcategory_name FROM ledgerai_categories WHERE id = ?";
    $stmt = $db->prepare($check_query);
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    
    if (!$existing) {
        Response::notFound('Category not found');
    }
    
    // If changing name/subcategory, check for duplicates
    if (isset($data['category_name']) || isset($data['subcategory_name'])) {
        $new_category = $data['category_name'] ?? $existing['category_name'];
        $new_subcategory = $data['subcategory_name'] ?? $existing['subcategory_name'];
        
        $duplicate_query = "SELECT id FROM ledgerai_categories 
                           WHERE category_name = ? AND subcategory_name = ? AND id != ?";
        $stmt = $db->prepare($duplicate_query);
        $stmt->execute([$new_category, $new_subcategory, $id]);
        
        if ($stmt->fetch()) {
            Response::validation(['Category with this name and subcategory already exists']);
        }
    }
    
    // Validate color hex format if provided
    if (isset($data['color_hex']) && !preg_match('/^#[0-9A-Fa-f]{6}$/', $data['color_hex'])) {
        Response::validation(['Invalid color hex format. Use #RRGGBB format']);
    }
    
    $updates = [];
    $params = [];
    
    $updatable_fields = [
        'category_name', 'subcategory_name', 'description',
        'color_hex', 'icon_name', 'is_active'
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
    
    $params[] = $id;
    
    $query = "UPDATE ledgerai_categories SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $db->prepare($query);
    $result = $stmt->execute($params);
    
    if ($result) {
        Response::success(['id' => $id], 'Category updated successfully');
    } else {
        Response::serverError('Failed to update category');
    }
}

/**
 * Delete category (soft delete by setting is_active = false)
 */
function deleteCategory($db, $id) {
    // Check if category exists
    $check_query = "SELECT id FROM ledgerai_categories WHERE id = ?";
    $stmt = $db->prepare($check_query);
    $stmt->execute([$id]);
    
    if (!$stmt->fetch()) {
        Response::notFound('Category not found');
    }
    
    // Check if category is being used by transactions
    $usage_query = "SELECT COUNT(*) as count FROM ledgerai_transactions 
                   WHERE category = (SELECT category_name FROM ledgerai_categories WHERE id = ?)
                   OR subcategory = (SELECT subcategory_name FROM ledgerai_categories WHERE id = ?)";
    $stmt = $db->prepare($usage_query);
    $stmt->execute([$id, $id]);
    $usage = $stmt->fetch();
    
    if ($usage['count'] > 0) {
        // Soft delete - category is in use
        $query = "UPDATE ledgerai_categories SET is_active = FALSE WHERE id = ?";
        $message = 'Category deactivated successfully (was in use by transactions)';
    } else {
        // Hard delete - category is not in use
        $query = "DELETE FROM ledgerai_categories WHERE id = ?";
        $message = 'Category deleted successfully';
    }
    
    $stmt = $db->prepare($query);
    $result = $stmt->execute([$id]);
    
    if ($result) {
        Response::success(['id' => $id], $message);
    } else {
        Response::serverError('Failed to delete category');
    }
}

/**
 * Get category statistics (how many transactions use each category)
 */
function getCategoryStats($db) {
    $query = "SELECT 
                c.id, c.category_name, c.subcategory_name, c.color_hex,
                COUNT(t.id) as transaction_count,
                SUM(CASE WHEN t.transaction_type = 'DEBIT' THEN t.amount ELSE 0 END) as total_debit,
                SUM(CASE WHEN t.transaction_type = 'CREDIT' THEN t.amount ELSE 0 END) as total_credit
              FROM ledgerai_categories c
              LEFT JOIN ledgerai_transactions t ON (
                  c.category_name = t.category AND 
                  (c.subcategory_name = t.subcategory OR (c.subcategory_name IS NULL AND t.subcategory IS NULL))
              )
              WHERE c.is_active = TRUE
              GROUP BY c.id, c.category_name, c.subcategory_name, c.color_hex
              ORDER BY transaction_count DESC, c.category_name";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats = $stmt->fetchAll();
    
    // Format numbers
    foreach ($stats as &$stat) {
        $stat['transaction_count'] = (int) $stat['transaction_count'];
        $stat['total_debit'] = (float) $stat['total_debit'];
        $stat['total_credit'] = (float) $stat['total_credit'];
        $stat['net_amount'] = $stat['total_credit'] - $stat['total_debit'];
    }
    
    Response::success($stats, 'Category statistics retrieved successfully');
}
?> 