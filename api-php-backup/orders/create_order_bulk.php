<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/verify_token.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$user = verifyToken($pdo);

if ($user['role'] !== 'customer') {
    http_response_code(403);
    echo json_encode(['error' => 'Only customers can create orders']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

$items = $data['items'] ?? [];

if (empty($items) || !is_array($items)) {
    http_response_code(400);
    echo json_encode(['error' => 'Items array is required']);
    exit();
}

try {
    // Enable error reporting for debugging
    ob_start();
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    
    $pdo->beginTransaction();
    
    // Validate all products and quantities
    $validatedItems = [];
    $totalAmount = 0;
    
    foreach ($items as $item) {
        $product_id = $item['product_id'] ?? 0;
        $quantity = $item['quantity'] ?? 0;
        
        if (empty($product_id) || $quantity <= 0) {
            $pdo->rollBack();
            ob_clean();
            http_response_code(400);
            echo json_encode(['error' => 'Invalid item data']);
            exit();
        }
        
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            $pdo->rollBack();
            ob_clean();
            http_response_code(404);
            echo json_encode(['error' => "Product ID {$product_id} not found"]);
            exit();
        }
        
        // Check stock availability (but don't deduct yet - will deduct on shipment)
        if ($product['stock'] < $quantity) {
            $pdo->rollBack();
            ob_clean();
            http_response_code(400);
            echo json_encode(['error' => "Insufficient stock for product: {$product['name']}. Available: {$product['stock']}, Requested: {$quantity}"]);
            exit();
        }
        
        $itemTotal = $product['price'] * $quantity;
        $totalAmount += $itemTotal;
        
        $validatedItems[] = [
            'product_id' => $product_id,
            'quantity' => $quantity,
            'price' => $product['price'],
            'product' => $product
        ];
    }
    
    // Generate order number
    $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    
    // Check if order_number and total_amount columns exist
    $stmt = $pdo->query("SHOW COLUMNS FROM orders LIKE 'order_number'");
    $hasOrderNumber = $stmt->rowCount() > 0;
    
    $stmt = $pdo->query("SHOW COLUMNS FROM orders LIKE 'total_amount'");
    $hasTotalAmount = $stmt->rowCount() > 0;
    
    // Check if order_items table exists first
    $stmt = $pdo->query("SHOW TABLES LIKE 'order_items'");
    $hasOrderItemsTable = $stmt->rowCount() > 0;
    
    if (!$hasOrderItemsTable) {
        $pdo->rollBack();
        ob_clean();
        http_response_code(500);
        echo json_encode(['error' => 'Order items table does not exist. Please run the migration script: api/config/migrate_order_structure.php']);
        exit();
    }
    
    // Verify customer exists (foreign key constraint)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $customerCheck = $stmt->fetch();
    
    if (!$customerCheck) {
        $pdo->rollBack();
        ob_clean();
        http_response_code(400);
        echo json_encode(['error' => 'Invalid customer ID. User does not exist.']);
        exit();
    }
    
    // Create order with or without new columns
    try {
        if ($hasOrderNumber && $hasTotalAmount) {
            $stmt = $pdo->prepare("INSERT INTO orders (customer_id, order_number, status, total_amount) VALUES (?, ?, 'pending', ?)");
            $result = $stmt->execute([$user['id'], $orderNumber, $totalAmount]);
            
            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                throw new PDOException("Failed to insert order: " . ($errorInfo[2] ?? 'Unknown error'));
            }
            
            $orderId = $pdo->lastInsertId();
            error_log("Order created with ID: " . $orderId . ", Order Number: " . $orderNumber);
        } else {
            // Fallback: use old structure (for backward compatibility)
            // Create a single order entry for the first product (legacy support)
            $firstItem = $validatedItems[0];
            $stmt = $pdo->prepare("INSERT INTO orders (customer_id, product_id, quantity, status) VALUES (?, ?, ?, 'pending')");
            $result = $stmt->execute([$user['id'], $firstItem['product_id'], $firstItem['quantity']]);
            
            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                throw new PDOException("Failed to insert order: " . ($errorInfo[2] ?? 'Unknown error'));
            }
            
            $orderId = $pdo->lastInsertId();
            error_log("Order created with ID: " . $orderId . " (legacy structure)");
        }
        
        // Validate order was created
        if (!$orderId || $orderId == 0) {
            throw new PDOException("Invalid order ID returned: " . $orderId);
        }
        
        // Verify order exists in the same transaction
        $stmt = $pdo->prepare("SELECT id, customer_id FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $orderCheck = $stmt->fetch();
        
        if (!$orderCheck) {
            throw new PDOException("Order was not created successfully. Order ID: " . $orderId);
        }
        
        error_log("Order verified: ID=" . $orderCheck['id'] . ", Customer ID=" . $orderCheck['customer_id']);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        ob_clean();
        http_response_code(500);
        error_log('Error creating order: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        echo json_encode(['error' => 'Failed to create order: ' . $e->getMessage()]);
        exit();
    }
    
    // Create order items (stock will be deducted when supplier ships)
    $orderItems = [];
    foreach ($validatedItems as $item) {
        try {
            // Validate product exists
            $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
            $stmt->execute([$item['product_id']]);
            $productCheck = $stmt->fetch();
            
            if (!$productCheck) {
                throw new PDOException("Product ID {$item['product_id']} does not exist");
            }
            
            error_log("Inserting order item: Order ID={$orderId}, Product ID={$item['product_id']}, Quantity={$item['quantity']}, Price={$item['price']}");
            
            // Double-check order exists before inserting item (within same transaction)
            $stmt = $pdo->prepare("SELECT id, customer_id, order_number FROM orders WHERE id = ?");
            $stmt->execute([$orderId]);
            $orderExists = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$orderExists) {
                throw new PDOException("Cannot insert order item: Order ID {$orderId} does not exist in orders table");
            }
            
            error_log("Order verified before item insert: " . print_r($orderExists, true));
            
            // Verify product exists
            $stmt = $pdo->prepare("SELECT id, name FROM products WHERE id = ?");
            $stmt->execute([$item['product_id']]);
            $productExists = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$productExists) {
                throw new PDOException("Cannot insert order item: Product ID {$item['product_id']} does not exist in products table");
            }
            
            error_log("Product verified before item insert: " . print_r($productExists, true));
            
            // Insert order item with explicit error handling
            try {
                $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                $result = $stmt->execute([
                    (int)$orderId, 
                    (int)$item['product_id'], 
                    (int)$item['quantity'], 
                    (float)$item['price']
                ]);
                
                if (!$result) {
                    $errorInfo = $stmt->errorInfo();
                    $errorMsg = "Failed to insert order item: " . ($errorInfo[2] ?? 'Unknown error');
                    error_log($errorMsg);
                    error_log("Error Info: " . print_r($errorInfo, true));
                    error_log("Attempted values: order_id=" . (int)$orderId . ", product_id=" . (int)$item['product_id'] . ", quantity=" . (int)$item['quantity'] . ", price=" . (float)$item['price']);
                    throw new PDOException($errorMsg);
                }
            } catch (PDOException $e) {
                // Get more detailed error information
                $errorInfo = isset($stmt) ? $stmt->errorInfo() : ['', '', 'No statement available'];
                error_log("PDO Exception caught: " . $e->getMessage());
                error_log("PDO Error Code: " . $e->getCode());
                error_log("PDO Error Info: " . print_r($errorInfo, true));
                throw $e;
            }
            
            error_log("Order item inserted successfully: Order ID={$orderId}, Product ID={$item['product_id']}");
            
            // Note: Stock will be deducted when supplier ships the order (status = 'shipped')
            // This allows for order cancellation without stock issues
            
            $orderItems[] = [
                'product_id' => $item['product_id'],
                'product_name' => $item['product']['name'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'total' => $item['price'] * $item['quantity']
            ];
        } catch (PDOException $e) {
            $pdo->rollBack();
            ob_clean();
            http_response_code(500);
            error_log('Error inserting order item: ' . $e->getMessage());
            error_log('Order ID: ' . $orderId . ', Product ID: ' . $item['product_id']);
            error_log('Stack trace: ' . $e->getTraceAsString());
            echo json_encode(['error' => 'Failed to create order item: ' . $e->getMessage()]);
            exit();
        }
    }
    
    // Commit transaction after all order items are inserted
    $pdo->commit();
    error_log("Transaction committed successfully. Order ID: " . $orderId);
    
    // Get full order details after commit
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        ob_clean();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to retrieve created order after commit']);
        exit();
    }
    
    ob_clean();
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Order created successfully',
        'order' => [
            'id' => $order['id'],
            'order_number' => isset($order['order_number']) ? $order['order_number'] : ('ORD-' . $orderId),
            'status' => $order['status'],
            'total_amount' => isset($order['total_amount']) ? $order['total_amount'] : $totalAmount,
            'created_at' => $order['created_at'],
            'items' => $orderItems
        ]
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    ob_clean();
    http_response_code(500);
    error_log('Order creation error: ' . $e->getMessage());
    echo json_encode(['error' => 'Failed to create order: ' . $e->getMessage()]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    ob_clean();
    http_response_code(500);
    error_log('Order creation error: ' . $e->getMessage());
    echo json_encode(['error' => 'Failed to create order: ' . $e->getMessage()]);
}
?>

