<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function getProducts(Request $request)
    {
        try {
            $supplierId = $request->query('supplier_id');
            
            $query = DB::table('products')
                ->join('users', 'products.supplier_id', '=', 'users.id')
                ->select('products.*', 'users.username as supplier_name')
                ->orderBy('products.created_at', 'desc');
            
            if ($supplierId) {
                $query->where('products.supplier_id', $supplierId);
            }
            
            $products = $query->get();
            
            return response()->json(['success' => true, 'products' => $products]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch products: ' . $e->getMessage()], 500);
        }
    }

    public function getProduct(Request $request)
    {
        try {
            $productId = $request->query('id');
            
            if (!$productId) {
                return response()->json(['error' => 'Product ID is required'], 400);
            }
            
            $product = DB::table('products')
                ->join('users', 'products.supplier_id', '=', 'users.id')
                ->select('products.*', 'users.username as supplier_name')
                ->where('products.id', $productId)
                ->first();
            
            if (!$product) {
                return response()->json(['error' => 'Product not found'], 404);
            }
            
            return response()->json(['success' => true, 'product' => $product]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch product: ' . $e->getMessage()], 500);
        }
    }

    public function addProduct(Request $request)
    {
        $user = $request->get('auth_user');
        
        if ($user['role'] !== 'supplier' && $user['role'] !== 'admin') {
            return response()->json(['error' => 'Only suppliers can add products'], 403);
        }

        $data = $request->json()->all();
        $name = $data['name'] ?? '';
        $description = $data['description'] ?? '';
        $price = $data['price'] ?? 0;
        $stock = $data['stock'] ?? 0;
        // Ensure image is always a string, never array/null
        $image = isset($data['image']) ? (is_array($data['image']) ? '' : (string)$data['image']) : '';
        $supplier_id = ($user['role'] === 'admin' && isset($data['supplier_id'])) ? $data['supplier_id'] : $user['id'];

        if (empty($name) || empty($price) || $price <= 0) {
            return response()->json(['error' => 'Name and valid price are required'], 400);
        }

        try {
            $productId = DB::table('products')->insertGetId([
                'supplier_id' => $supplier_id,
                'name' => $name,
                'description' => $description,
                'price' => $price,
                'stock' => $stock,
                'image' => $image,
                'created_at' => now()
            ]);

            $product = DB::table('products')->where('id', $productId)->first();

            return response()->json([
                'success' => true,
                'message' => 'Product added successfully',
                'product' => $product
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to add product: ' . $e->getMessage()], 500);
        }
    }

    public function updateProduct(Request $request)
    {
        $user = $request->get('auth_user');
        
        if ($user['role'] !== 'supplier' && $user['role'] !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $data = $request->json()->all();
        $productId = $data['product_id'] ?? 0;

        if (empty($productId)) {
            return response()->json(['error' => 'Product ID is required'], 400);
        }

        try {
            $product = DB::table('products')->where('id', $productId)->first();
            
            if (!$product) {
                return response()->json(['error' => 'Product not found'], 404);
            }
            
            if ($user['role'] === 'supplier' && $product->supplier_id != $user['id']) {
                return response()->json(['error' => 'You can only update your own products'], 403);
            }
            
            $updateData = [];
            if (isset($data['name'])) $updateData['name'] = $data['name'];
            if (isset($data['description'])) $updateData['description'] = $data['description'];
            if (isset($data['price'])) $updateData['price'] = $data['price'];
            if (isset($data['stock'])) $updateData['stock'] = $data['stock'];
            if (isset($data['image'])) $updateData['image'] = $data['image'];
            
            DB::table('products')->where('id', $productId)->update($updateData);
            
            $updatedProduct = DB::table('products')->where('id', $productId)->first();
            
            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'product' => $updatedProduct
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update product: ' . $e->getMessage()], 500);
        }
    }

    public function deleteProduct(Request $request)
    {
        $user = $request->get('auth_user');
        
        if ($user['role'] !== 'supplier' && $user['role'] !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $productId = $request->query('id') ?? $request->json('id') ?? 0;

        if (empty($productId)) {
            return response()->json(['error' => 'Product ID is required'], 400);
        }

        try {
            $product = DB::table('products')->where('id', $productId)->first();
            
            if (!$product) {
                return response()->json(['error' => 'Product not found'], 404);
            }
            
            if ($user['role'] === 'supplier' && $product->supplier_id != $user['id']) {
                return response()->json(['error' => 'You can only delete your own products'], 403);
            }
            
            DB::table('products')->where('id', $productId)->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete product: ' . $e->getMessage()], 500);
        }
    }

    public function uploadImage(Request $request)
    {
        $user = $request->get('auth_user');
        
        if ($user['role'] !== 'supplier' && $user['role'] !== 'admin') {
            return response()->json(['error' => 'Only suppliers can upload product images'], 403);
        }

        if (!$request->hasFile('image')) {
            return response()->json(['error' => 'No file uploaded or upload error'], 400);
        }

        $file = $request->file('image');
        
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedTypes)) {
            return response()->json(['error' => 'Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.'], 400);
        }

        // Validate file size (5MB max)
        if ($file->getSize() > 5 * 1024 * 1024) {
            return response()->json(['error' => 'File size exceeds 5MB limit.'], 400);
        }

        try {
            // Generate unique filename
            $extension = $file->getClientOriginalExtension();
            $filename = 'product_' . $user['id'] . '_' . time() . '_' . uniqid() . '.' . $extension;
            
            // Store in public/uploads/products
            $path = $file->storeAs('products', $filename, 'public');
            
            $relativePath = 'uploads/products/' . $filename;
            // Generate image URL - use base URL without /api for storage
            $baseUrl = rtrim(config('app.url'), '/');
            $baseUrl = str_replace('/api', '', $baseUrl); // Remove /api if present
            $imageUrl = $baseUrl . '/storage/products/' . $filename;

            return response()->json([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'image_path' => $relativePath,
                'image_url' => $imageUrl
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to save file: ' . $e->getMessage()], 500);
        }
    }
}
