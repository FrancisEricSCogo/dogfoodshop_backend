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

$product_id = $data['product_id'] ?? 0;
$quantity = $data['quantity'] ?? 1;

if (empty($product_id) || $quantity <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid product ID and quantity are required']);
    exit();
}

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        $pdo->rollBack();
        exit();
    }
    
    if ($product['stock'] < $quantity) {
        http_response_code(400);
        echo json_encode(['error' => 'Insufficient stock']);
        $pdo->rollBack();
        exit();
    }
    
    // Check if new order structure exists
    $stmt = $pdo->query("SHOW COLUMNS FROM orders LIKE 'order_number'");
    $hasOrderNumber = $stmt->rowCount() > 0;
    
    if ($hasOrderNumber) {
        // Use new structure with order_items
        $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        $totalAmount = $product['price'] * $quantity;
        
        $stmt = $pdo->prepare("INSERT INTO orders (customer_id, order_number, status, total_amount) VALUES (?, ?, 'pending', ?)");
        $stmt->execute([$user['id'], $orderNumber, $totalAmount]);
        $orderId = $pdo->lastInsertId();
        
        // Insert into order_items
        $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        $stmt->execute([$orderId, $product_id, $quantity, $product['price']]);
        
        // Stock will be deducted when supplier ships (not on order creation)
    } else {
        // Use old structure (backward compatibility)
        $stmt = $pdo->prepare("INSERT INTO orders (customer_id, product_id, quantity, status) VALUES (?, ?, ?, 'pending')");
        $stmt->execute([$user['id'], $product_id, $quantity]);
        $orderId = $pdo->lastInsertId();
        
        // Stock will be deducted when supplier ships (not on order creation)
    }
    
    $stmt = $pdo->prepare("SELECT o.*, p.name as product_name, p.price FROM orders o JOIN products p ON o.product_id = p.id WHERE o.id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    
    $pdo->commit();
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Order created successfully',
        'order' => $order
    ]);
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create order: ' . $e->getMessage()]);
}
?>

