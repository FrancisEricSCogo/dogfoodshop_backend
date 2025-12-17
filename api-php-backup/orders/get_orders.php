<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/verify_token.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$user = verifyToken($pdo);

try {
    if ($user['role'] === 'admin') {
        $stmt = $pdo->prepare("SELECT DISTINCT o.id, o.order_number, o.status, o.total_amount, o.created_at, o.updated_at,
                               u.first_name, u.last_name, u.username as customer_name,
                               (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
                               FROM orders o 
                               JOIN users u ON o.customer_id = u.id 
                               ORDER BY o.created_at DESC");
        $stmt->execute();
    } elseif ($user['role'] === 'supplier') {
        $stmt = $pdo->prepare("SELECT DISTINCT o.id, o.order_number, o.status, o.total_amount, o.created_at, o.updated_at,
                               u.first_name, u.last_name, u.username as customer_name,
                               (SELECT COUNT(*) FROM order_items oi2 JOIN products p2 ON oi2.product_id = p2.id WHERE oi2.order_id = o.id AND p2.supplier_id = ?) as item_count
                               FROM orders o 
                               JOIN order_items oi ON o.id = oi.order_id
                               JOIN products p ON oi.product_id = p.id 
                               JOIN users u ON o.customer_id = u.id 
                               WHERE p.supplier_id = ? 
                               GROUP BY o.id
                               ORDER BY o.created_at DESC");
        $stmt->execute([$user['id'], $user['id']]);
    } else {
        $stmt = $pdo->prepare("SELECT o.id, o.order_number, o.status, o.total_amount, o.created_at, o.updated_at,
                               (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
                               FROM orders o 
                               WHERE o.customer_id = ? 
                               ORDER BY o.created_at DESC");
        $stmt->execute([$user['id']]);
    }
    
    $orders = $stmt->fetchAll();
    
    // Get items for each order
    foreach ($orders as &$order) {
        $stmt = $pdo->prepare("SELECT oi.*, p.name as product_name, p.price, s.username as supplier_name 
                              FROM order_items oi 
                              JOIN products p ON oi.product_id = p.id 
                              LEFT JOIN users s ON p.supplier_id = s.id 
                              WHERE oi.order_id = ?");
        $stmt->execute([$order['id']]);
        $order['items'] = $stmt->fetchAll();
    }
    
    echo json_encode(['success' => true, 'orders' => $orders]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch orders: ' . $e->getMessage()]);
}
?>

