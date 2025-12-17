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
    echo json_encode(['error' => 'Only suppliers can upload product images']);
    exit();
}

// Check if file was uploaded
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded or upload error']);
    exit();
}

$file = $_FILES['image'];

// Validate file type
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($file['type'], $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.']);
    exit();
}

// Validate file size (5MB max)
if ($file['size'] > 5 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['error' => 'File size exceeds 5MB limit.']);
    exit();
}

// Create uploads/products directory if it doesn't exist
$uploadDir = __DIR__ . '/../../uploads/products/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'product_' . $user['id'] . '_' . time() . '_' . uniqid() . '.' . $extension;
$filepath = $uploadDir . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save file']);
    exit();
}

// Return the relative path for database storage
$relativePath = 'uploads/products/' . $filename;

// Get the base URL path (e.g., /dogfoodshop/)
$projectRoot = dirname(__DIR__, 2); // Go up 2 levels from api/products to project root
$basePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $projectRoot);
$basePath = str_replace('\\', '/', $basePath); // Fix Windows paths
if (substr($basePath, 0, 1) !== '/') {
    $basePath = '/' . $basePath;
}
if (substr($basePath, -1) !== '/') {
    $basePath .= '/';
}

echo json_encode([
    'success' => true,
    'message' => 'Image uploaded successfully',
    'image_path' => $relativePath,
    'image_url' => $basePath . $relativePath
]);
?>

