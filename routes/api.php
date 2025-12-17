<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\NotificationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);

// Protected routes
Route::middleware('auth:api')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    
    // Products
    Route::get('/products/get_products', [ProductController::class, 'index']);
    Route::get('/products/get_product', [ProductController::class, 'show']);
    Route::post('/products/add_product', [ProductController::class, 'store']);
    Route::post('/products/update_product', [ProductController::class, 'update']);
    Route::delete('/products/delete_product/{id}', [ProductController::class, 'destroy']);
    
    // Orders
    Route::get('/orders/get_orders', [OrderController::class, 'index']);
    Route::get('/orders/get_order/{id}', [OrderController::class, 'show']);
    Route::post('/orders/create_order', [OrderController::class, 'store']);
    Route::post('/orders/update_order_status', [OrderController::class, 'updateStatus']);
    
    // Users
    Route::get('/users/profile', [UserController::class, 'profile']);
    Route::post('/users/upload_avatar', [UserController::class, 'uploadAvatar']);
    
    // Admin routes
    Route::middleware('admin')->group(function () {
        Route::get('/admin/get_users', [AdminController::class, 'getUsers']);
        Route::post('/admin/create_user', [AdminController::class, 'createUser']);
        Route::post('/admin/update_user', [AdminController::class, 'updateUser']);
        Route::delete('/admin/delete_user/{id}', [AdminController::class, 'deleteUser']);
    });
    
    // Notifications
    Route::get('/notifications/get_notifications', [NotificationController::class, 'index']);
    Route::put('/notifications/mark_read', [NotificationController::class, 'markRead']);
});

