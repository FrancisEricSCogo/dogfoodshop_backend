<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\NotificationController;
use App\Http\Middleware\VerifyToken;

// API Info endpoint (public)
Route::get('/', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Dog Food Shop API is running',
        'version' => '1.0',
        'endpoints' => [
            'auth' => [
                'login' => '/api/auth/login.php',
                'register' => '/api/auth/register.php',
                'verify_otp' => '/api/auth/verify_otp.php',
                'password_reset' => '/api/auth/request_password_reset.php'
            ],
            'products' => [
                'get_all' => '/api/products/get_products.php',
                'get_one' => '/api/products/get_product.php?id={id}'
            ],
            'orders' => [
                'get_orders' => '/api/orders/get_orders.php (requires authentication)',
                'get_order' => '/api/orders/get_order.php?id={id} (requires authentication)'
            ],
            'users' => [
                'profile' => '/api/users/profile.php (requires authentication)'
            ],
            'admin' => [
                'get_users' => '/api/admin/get_users.php (requires admin authentication)',
                'create_user' => '/api/admin/create_user.php (requires admin authentication)'
            ]
        ],
        'documentation' => 'Access specific endpoints using the paths listed above'
    ]);
});

// Auth endpoints (no authentication required)
Route::post('/auth/login.php', [AuthController::class, 'login']);
Route::post('/auth/register.php', [AuthController::class, 'register']);
Route::post('/auth/verify_otp.php', [AuthController::class, 'verifyOtp']);
Route::post('/auth/request_password_reset.php', [AuthController::class, 'requestPasswordReset']);
Route::post('/auth/reset_password.php', [AuthController::class, 'resetPassword']);
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware(VerifyToken::class);

// Products endpoints (public get, protected for add/update/delete)
Route::get('/products/get_products.php', [ProductController::class, 'getProducts']);
Route::get('/products/get_product.php', [ProductController::class, 'getProduct']);
Route::post('/products/add_product.php', [ProductController::class, 'addProduct'])->middleware(VerifyToken::class);
Route::put('/products/update_product.php', [ProductController::class, 'updateProduct'])->middleware(VerifyToken::class);
Route::delete('/products/delete_product.php', [ProductController::class, 'deleteProduct'])->middleware(VerifyToken::class);
Route::post('/products/upload_image.php', [ProductController::class, 'uploadImage'])->middleware(VerifyToken::class);

// Orders endpoints (protected)
Route::get('/orders/get_orders.php', [OrderController::class, 'getOrders'])->middleware(VerifyToken::class);
Route::get('/orders/get_order.php', [OrderController::class, 'getOrder'])->middleware(VerifyToken::class);
Route::post('/orders/create_order_bulk.php', [OrderController::class, 'createOrderBulk'])->middleware(VerifyToken::class);
Route::put('/orders/update_order_status.php', [OrderController::class, 'updateOrderStatus'])->middleware(VerifyToken::class);

// Users endpoints (protected)
Route::get('/users/profile.php', [UserController::class, 'getProfile'])->middleware(VerifyToken::class);
Route::put('/users/profile.php', [UserController::class, 'updateProfile'])->middleware(VerifyToken::class);
Route::post('/users/upload_avatar.php', [UserController::class, 'uploadAvatar'])->middleware(VerifyToken::class);

// Admin endpoints (protected, admin only)
Route::get('/admin/get_users.php', [AdminController::class, 'getUsers'])->middleware(VerifyToken::class);
Route::post('/admin/create_user.php', [AdminController::class, 'createUser'])->middleware(VerifyToken::class);
Route::put('/admin/update_user.php', [AdminController::class, 'updateUser'])->middleware(VerifyToken::class);
Route::delete('/admin/delete_user.php', [AdminController::class, 'deleteUser'])->middleware(VerifyToken::class);

// Notifications endpoints (protected)
Route::get('/notifications/get_notifications.php', [NotificationController::class, 'getNotifications'])->middleware(VerifyToken::class);
Route::put('/notifications/mark_read.php', [NotificationController::class, 'markRead'])->middleware(VerifyToken::class);

// Diagnostic route to check storage
Route::get('/storage/check', function () {
    $productsDir = storage_path('app/public/products');
    $files = [];
    
    if (is_dir($productsDir)) {
        $files = array_slice(scandir($productsDir), 2); // Remove . and ..
        $files = array_filter($files, function($file) use ($productsDir) {
            return is_file($productsDir . '/' . $file);
        });
    }
    
    return response()->json([
        'storage_path' => $productsDir,
        'directory_exists' => is_dir($productsDir),
        'files_count' => count($files),
        'files' => array_values($files),
        'first_10_files' => array_slice(array_values($files), 0, 10)
    ]);
});

// Serve avatar images from storage (public endpoint)
Route::get('/storage/avatars/{filename}', function ($filename) {
    // Clean filename to prevent directory traversal
    $filename = basename($filename);
    
    // Try multiple possible paths
    $paths = [
        storage_path('app/public/' . $filename),
        storage_path('app/public/uploads/' . $filename),
        public_path('storage/' . $filename),
    ];
    
    $path = null;
    foreach ($paths as $tryPath) {
        if (file_exists($tryPath)) {
            $path = $tryPath;
            break;
        }
    }
    
    if (!$path) {
        abort(404, 'Avatar not found');
    }
    
    $mimeType = mime_content_type($path);
    if (!$mimeType) {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp'
        ];
        $mimeType = $mimeTypes[$ext] ?? 'image/jpeg';
    }
    
    return response()->file($path, [
        'Content-Type' => $mimeType,
        'Cache-Control' => 'public, max-age=31536000'
    ]);
})->where('filename', '.*');

// Serve product images from storage (public endpoint)
Route::get('/storage/products/{filename}', function ($filename) {
    // Clean filename to prevent directory traversal
    $filename = basename($filename);
    
    // Try multiple possible paths
    $paths = [
        storage_path('app/public/products/' . $filename),
        storage_path('app/public/uploads/products/' . $filename),
        public_path('storage/products/' . $filename),
    ];
    
    $path = null;
    foreach ($paths as $tryPath) {
        if (file_exists($tryPath)) {
            $path = $tryPath;
            break;
        }
    }
    
    if (!$path) {
        // Return JSON error with diagnostic info
        return response()->json([
            'error' => 'Image not found',
            'filename' => $filename,
            'tried_paths' => $paths,
            'storage_exists' => is_dir(storage_path('app/public/products')),
            'hint' => 'Check /api/storage/check to see available files'
        ], 404);
    }
    
    $mimeType = mime_content_type($path);
    if (!$mimeType) {
        // Try to guess from extension
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp'
        ];
        $mimeType = $mimeTypes[$ext] ?? 'image/jpeg';
    }
    
    return response()->file($path, [
        'Content-Type' => $mimeType,
        'Cache-Control' => 'public, max-age=31536000'
    ]);
})->where('filename', '.*');