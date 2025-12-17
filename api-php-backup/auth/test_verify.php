<?php
/**
 * Test endpoint to debug verify_otp issues
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$rawInput = file_get_contents('php://input');
$method = $_SERVER['REQUEST_METHOD'];
$contentType = $_SERVER['CONTENT_TYPE'] ?? 'NOT SET';

$response = [
    'method' => $method,
    'content_type' => $contentType,
    'raw_input' => $rawInput,
    'raw_input_length' => strlen($rawInput),
    'post_data' => $_POST,
    'get_data' => $_GET,
    'json_decode_result' => null,
    'json_error' => null
];

if ($rawInput) {
    $decoded = json_decode($rawInput, true);
    $response['json_decode_result'] = $decoded;
    $response['json_error'] = json_last_error_msg();
    $response['json_error_code'] = json_last_error();
    
    if ($decoded) {
        $response['email'] = $decoded['email'] ?? 'NOT FOUND';
        $response['otp'] = $decoded['otp'] ?? 'NOT FOUND';
        $response['email_length'] = strlen($response['email']);
        $response['otp_length'] = strlen($response['otp']);
    }
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>

