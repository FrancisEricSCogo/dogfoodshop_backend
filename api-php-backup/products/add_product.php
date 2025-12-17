<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/verify_token.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$user = verifyToken($pdo);

if ($user['role'] !== 'supplier' && $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Only suppliers can add products']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

$name = $data['name'] ?? '';
$description = $data['description'] ?? '';
$price = $data['price'] ?? 0;
$stock = $data['stock'] ?? 0;
$image = $data['image'] ?? '';
$supplier_id = ($user['role'] === 'admin' && isset($data['supplier_id'])) ? $data['supplier_id'] : $user['id'];

if (empty($name) || empty($price) || $price <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Name and valid price are required']);
    exit();
}

try {
    $stmt = $pdo->prepare("INSERT INTO products (supplier_id, name, description, price, stock, image) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$supplier_id, $name, $description, $price, $stock, $image]);
    
    $productId = $pdo->lastInsertId();
    
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Product added successfully',
        'product' => $product
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to add product: ' . $e->getMessage()]);
}
?>

