<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

// Serve product images from storage
Route::get('/storage/products/{filename}', function ($filename) {
    $path = storage_path('app/public/products/' . $filename);
    
    if (!file_exists($path)) {
        abort(404);
    }
    
    $mimeType = mime_content_type($path);
    return response()->file($path, ['Content-Type' => $mimeType]);
})->where('filename', '.*');
