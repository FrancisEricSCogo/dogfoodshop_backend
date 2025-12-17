<?php
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    $supplierId = $_GET['supplier_id'] ?? null;
    
    if ($supplierId) {
        $stmt = $pdo->prepare("SELECT p.*, u.username as supplier_name FROM products p JOIN users u ON p.supplier_id = u.id WHERE p.supplier_id = ? ORDER BY p.created_at DESC");
        $stmt->execute([$supplierId]);
    } else {
        $stmt = $pdo->prepare("SELECT p.*, u.username as supplier_name FROM products p JOIN users u ON p.supplier_id = u.id ORDER BY p.created_at DESC");
        $stmt->execute();
    }
    
    $products = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'products' => $products]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch products: ' . $e->getMessage()]);
}
?>

