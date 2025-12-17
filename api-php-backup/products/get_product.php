<?php
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$productId = $_GET['id'] ?? null;

if (empty($productId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Product ID is required']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT p.*, u.username as supplier_name, u.first_name, u.last_name FROM products p JOIN users u ON p.supplier_id = u.id WHERE p.id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        exit();
    }
    
    echo json_encode(['success' => true, 'product' => $product]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch product: ' . $e->getMessage()]);
}
?>

