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
$notificationId = $data['notification_id'] ?? 0;

try {
    if ($notificationId > 0) {
        $stmt = $pdo->prepare("UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->execute([$notificationId, $user['id']]);
    } else {
        $stmt = $pdo->prepare("UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL");
        $stmt->execute([$user['id']]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update notification: ' . $e->getMessage()]);
}
?>

