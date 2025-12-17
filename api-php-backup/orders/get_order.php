<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/verify_token.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$user = verifyToken($pdo);
$orderId = $_GET['id'] ?? 0;

if (empty($orderId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Order ID is required']);
    exit();
}

try {
    // Get order details
    $stmt = $pdo->prepare("SELECT o.*, u.first_name, u.last_name, u.email, u.phone, u.address, u.city, u.postal_code 
                          FROM orders o 
                          JOIN users u ON o.customer_id = u.id 
                          WHERE o.id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
        exit();
    }
    
    // Check permissions
    if ($user['role'] === 'customer' && $order['customer_id'] != $user['id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit();
    }
    
    if ($user['role'] === 'supplier') {
        // Check if supplier has products in this order
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM order_items oi 
                              JOIN products p ON oi.product_id = p.id 
                              WHERE oi.order_id = ? AND p.supplier_id = ?");
        $stmt->execute([$orderId, $user['id']]);
        $result = $stmt->fetch();
        if ($result['count'] == 0) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            exit();
        }
    }
    
    // Get order items
    $stmt = $pdo->prepare("SELECT oi.*, p.name as product_name, p.description, p.image, s.username as supplier_name 
                          FROM order_items oi 
                          JOIN products p ON oi.product_id = p.id 
                          LEFT JOIN users s ON p.supplier_id = s.id 
                          WHERE oi.order_id = ?");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll();
    
    // Build full address
    $address = $order['address'] ?? '';
    if ($order['city']) {
        $address .= ($address ? ', ' : '') . $order['city'];
    }
    if ($order['postal_code']) {
        $address .= ($address ? ', ' : '') . $order['postal_code'];
    }
    
    echo json_encode([
        'success' => true,
        'order' => [
            'id' => $order['id'],
            'order_number' => $order['order_number'],
            'status' => $order['status'],
            'total_amount' => $order['total_amount'],
            'created_at' => $order['created_at'],
            'updated_at' => $order['updated_at'] ?? $order['created_at'],
            'customer' => [
                'name' => $order['first_name'] . ' ' . $order['last_name'],
                'email' => $order['email'],
                'phone' => $order['phone'],
                'address' => $address
            ],
            'items' => $items
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch order: ' . $e->getMessage()]);
}
?>

