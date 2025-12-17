<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/verify_token.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$user = verifyToken($pdo);

$data = json_decode(file_get_contents('php://input'), true);
$orderId = $data['order_id'] ?? 0;
$newStatus = $data['status'] ?? '';

if (empty($orderId) || empty($newStatus)) {
    http_response_code(400);
    echo json_encode(['error' => 'Order ID and status are required']);
    exit();
}

$allowedStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'completed'];
if (!in_array($newStatus, $allowedStatuses)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid status']);
    exit();
}

try {
    // Get order details
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
        exit();
    }
    
    $currentStatus = $order['status'];
    
    if ($user['role'] === 'customer') {
        if ($newStatus === 'cancelled' && $currentStatus === 'pending') {
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $orderId]);
            
            // Restore stock for all items in the order
            $stmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
            $stmt->execute([$orderId]);
            $items = $stmt->fetchAll();
            
            foreach ($items as $item) {
                $stmt = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
                $stmt->execute([$item['quantity'], $item['product_id']]);
            }
        } elseif ($newStatus === 'completed' && $currentStatus === 'shipped') {
            // Customer can mark shipped order as completed (received)
            $stmt = $pdo->prepare("UPDATE orders SET status = 'completed' WHERE id = ?");
            $stmt->execute([$orderId]);
        } elseif ($newStatus === 'delivered' && $currentStatus === 'shipped') {
            // Legacy support: allow delivered status (though we prefer completed)
            $stmt = $pdo->prepare("UPDATE orders SET status = 'delivered' WHERE id = ?");
            $stmt->execute([$orderId]);
        } elseif ($newStatus === 'completed' && $currentStatus === 'delivered') {
            // Allow delivered to be marked as completed
            $stmt = $pdo->prepare("UPDATE orders SET status = 'completed' WHERE id = ?");
            $stmt->execute([$orderId]);
        } else {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid status change for customer']);
            exit();
        }
    } elseif ($user['role'] === 'supplier') {
        // Check if supplier has products in this order
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM order_items oi 
                              JOIN products p ON oi.product_id = p.id 
                              WHERE oi.order_id = ? AND p.supplier_id = ?");
        $stmt->execute([$orderId, $user['id']]);
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            http_response_code(403);
            echo json_encode(['error' => 'You can only update orders for your products']);
            exit();
        }
        
        // Only pending orders can be updated by suppliers
        // Final statuses that cannot be changed: shipped, delivered, completed, cancelled
        $finalStatuses = ['shipped', 'delivered', 'completed', 'cancelled'];
        if (in_array($currentStatus, $finalStatuses)) {
            http_response_code(400);
            echo json_encode(['error' => "Order is already {$currentStatus} and cannot be updated"]);
            exit();
        }
        
        // Only pending orders can be updated to shipped or cancelled
        if ($currentStatus !== 'pending') {
            http_response_code(400);
            echo json_encode(['error' => 'Only pending orders can be updated']);
            exit();
        }
        
        // Valid transitions for pending orders: shipped or cancelled
        if (!in_array($newStatus, ['shipped', 'cancelled'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Pending orders can only be updated to "shipped" or "cancelled"']);
            exit();
        }
        
        // Get all order items for this supplier's products
        $stmt = $pdo->prepare("SELECT oi.product_id, oi.quantity, p.stock as current_stock, p.name as product_name
                              FROM order_items oi 
                              JOIN products p ON oi.product_id = p.id 
                              WHERE oi.order_id = ? AND p.supplier_id = ?");
        $stmt->execute([$orderId, $user['id']]);
        $orderItems = $stmt->fetchAll();
        
        // If status is changing to 'shipped', deduct stock for all items in the order
        if ($newStatus === 'shipped') {
            // Validate stock availability before deducting
            foreach ($orderItems as $orderItem) {
                if ($orderItem['current_stock'] < $orderItem['quantity']) {
                    http_response_code(400);
                    echo json_encode(['error' => "Insufficient stock to ship product: {$orderItem['product_name']}. Available: {$orderItem['current_stock']}, Required: {$orderItem['quantity']}"]);
                    exit();
                }
            }
            
            // Deduct stock for all items
            foreach ($orderItems as $orderItem) {
                $stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
                $stmt->execute([$orderItem['quantity'], $orderItem['product_id']]);
            }
        } elseif ($newStatus === 'cancelled') {
            // If order is cancelled, stock is not deducted (it was never shipped)
            // No action needed for stock as it was never deducted
        }
        
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $orderId]);
        
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, ?)");
        $orderNumber = $order['order_number'] ?? '#' . $orderId;
        $message = "Your order {$orderNumber} status has been updated to: {$newStatus}";
        $stmt->execute([$order['customer_id'], $message, 'order_update']);
    } elseif ($user['role'] === 'admin') {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $orderId]);
    } else {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit();
    }
    
    // Get updated order with items
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $updatedOrder = $stmt->fetch();
    
    // Get order items
    $stmt = $pdo->prepare("SELECT oi.*, p.name as product_name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
    $stmt->execute([$orderId]);
    $updatedOrder['items'] = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'message' => 'Order status updated',
        'order' => $updatedOrder
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update order: ' . $e->getMessage()]);
}
?>

