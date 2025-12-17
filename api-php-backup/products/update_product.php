<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/verify_token.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$user = verifyToken($pdo);

if ($user['role'] !== 'supplier' && $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$productId = $data['product_id'] ?? 0;

if (empty($productId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Product ID is required']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        exit();
    }
    
    if ($user['role'] === 'supplier' && $product['supplier_id'] != $user['id']) {
        http_response_code(403);
        echo json_encode(['error' => 'You can only update your own products']);
        exit();
    }
    
    $name = $data['name'] ?? $product['name'];
    $description = $data['description'] ?? $product['description'];
    $price = $data['price'] ?? $product['price'];
    $stock = $data['stock'] ?? $product['stock'];
    $image = $data['image'] ?? $product['image'];
    
    $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, price = ?, stock = ?, image = ? WHERE id = ?");
    $stmt->execute([$name, $description, $price, $stock, $image, $productId]);
    
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $updatedProduct = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'message' => 'Product updated successfully',
        'product' => $updatedProduct
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update product: ' . $e->getMessage()]);
}
?>

