<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../middleware/verify_token.php';

// This is a test script to diagnose the foreign key issue
header('Content-Type: application/json');

try {
    // Get a test user (customer)
    $stmt = $pdo->query("SELECT id, username, role FROM users WHERE role = 'customer' LIMIT 1");
    $testUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$testUser) {
        echo json_encode(['error' => 'No customer found']);
        exit();
    }
    
    echo json_encode(['test_user' => $testUser]);
    
    // Get a test product
    $stmt = $pdo->query("SELECT id, name, price, stock FROM products LIMIT 1");
    $testProduct = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$testProduct) {
        echo json_encode(['error' => 'No product found']);
        exit();
    }
    
    echo json_encode(['test_product' => $testProduct]);
    
    // Test order creation
    $pdo->beginTransaction();
    
    $orderNumber = 'TEST-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    $totalAmount = $testProduct['price'] * 1;
    
    // Check if columns exist
    $stmt = $pdo->query("SHOW COLUMNS FROM orders LIKE 'order_number'");
    $hasOrderNumber = $stmt->rowCount() > 0;
    
    $stmt = $pdo->query("SHOW COLUMNS FROM orders LIKE 'total_amount'");
    $hasTotalAmount = $stmt->rowCount() > 0;
    
    echo json_encode([
        'has_order_number' => $hasOrderNumber,
        'has_total_amount' => $hasTotalAmount
    ]);
    
    if ($hasOrderNumber && $hasTotalAmount) {
        $stmt = $pdo->prepare("INSERT INTO orders (customer_id, order_number, status, total_amount) VALUES (?, ?, 'pending', ?)");
        $result = $stmt->execute([$testUser['id'], $orderNumber, $totalAmount]);
        
        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            echo json_encode(['error' => 'Failed to insert order', 'error_info' => $errorInfo]);
            $pdo->rollBack();
            exit();
        }
        
        $orderId = $pdo->lastInsertId();
        echo json_encode(['order_created' => true, 'order_id' => $orderId]);
        
        // Verify order exists
        $stmt = $pdo->prepare("SELECT id, customer_id, order_number FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $orderCheck = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode(['order_check' => $orderCheck]);
        
        // Try to insert order item
        try {
            $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
            $result = $stmt->execute([$orderId, $testProduct['id'], 1, $testProduct['price']]);
            
            if ($result) {
                echo json_encode(['order_item_created' => true]);
                $pdo->rollBack(); // Rollback test transaction
                echo json_encode(['success' => 'Test passed - order and order_item can be created']);
            } else {
                $errorInfo = $stmt->errorInfo();
                echo json_encode(['error' => 'Failed to insert order item', 'error_info' => $errorInfo]);
                $pdo->rollBack();
            }
        } catch (PDOException $e) {
            echo json_encode([
                'error' => 'Exception inserting order item',
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            $pdo->rollBack();
        }
    } else {
        echo json_encode(['error' => 'Required columns missing']);
        $pdo->rollBack();
    }
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
}
?>

