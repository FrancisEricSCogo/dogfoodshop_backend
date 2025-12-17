<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function getOrders(Request $request)
    {
        $user = $request->get('auth_user');

        try {
            if ($user['role'] === 'admin') {
                $orders = DB::table('orders as o')
                    ->join('users as u', 'o.customer_id', '=', 'u.id')
                    ->select(DB::raw('DISTINCT o.id, o.order_number, o.status, o.total_amount, o.created_at, o.updated_at,
                        u.first_name, u.last_name, u.username as customer_name,
                        (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count'))
                    ->orderBy('o.created_at', 'desc')
                    ->get();
            } elseif ($user['role'] === 'supplier') {
                // Get distinct order IDs that have products from this supplier
                $orderIds = DB::table('orders as o')
                    ->join('order_items as oi', 'o.id', '=', 'oi.order_id')
                    ->join('products as p', 'oi.product_id', '=', 'p.id')
                    ->where('p.supplier_id', $user['id'])
                    ->distinct()
                    ->pluck('o.id')
                    ->toArray();
                
                if (empty($orderIds)) {
                    return response()->json(['success' => true, 'orders' => []]);
                }
                
                // Get full order details for those orders
                $supplierId = $user['id'];
                $orders = DB::table('orders as o')
                    ->join('users as u', 'o.customer_id', '=', 'u.id')
                    ->select(DB::raw("o.id, o.order_number, o.status, o.total_amount, o.created_at, o.updated_at,
                        u.first_name, u.last_name, u.username as customer_name,
                        (SELECT COUNT(*) FROM order_items oi2 JOIN products p2 ON oi2.product_id = p2.id WHERE oi2.order_id = o.id AND p2.supplier_id = {$supplierId}) as item_count"))
                    ->whereIn('o.id', $orderIds)
                    ->orderBy('o.created_at', 'desc')
                    ->get();
            } else {
                $orders = DB::table('orders as o')
                    ->select(DB::raw('o.id, o.order_number, o.status, o.total_amount, o.created_at, o.updated_at,
                        (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count'))
                    ->where('o.customer_id', $user['id'])
                    ->orderBy('o.created_at', 'desc')
                    ->get();
            }

            // Get items for each order
            foreach ($orders as $order) {
                $items = DB::table('order_items as oi')
                    ->join('products as p', 'oi.product_id', '=', 'p.id')
                    ->leftJoin('users as s', 'p.supplier_id', '=', 's.id')
                    ->select('oi.*', 'p.name as product_name', 'p.price', 's.username as supplier_name')
                    ->where('oi.order_id', $order->id)
                    ->get();
                $order->items = $items;
            }

            return response()->json(['success' => true, 'orders' => $orders]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch orders: ' . $e->getMessage()], 500);
        }
    }

    public function getOrder(Request $request)
    {
        $user = $request->get('auth_user');
        $orderId = $request->query('id');

        if (empty($orderId)) {
            return response()->json(['error' => 'Order ID is required'], 400);
        }

        try {
            $order = DB::table('orders as o')
                ->join('users as u', 'o.customer_id', '=', 'u.id')
                ->select('o.*', 'u.first_name', 'u.last_name', 'u.email', 'u.phone', 'u.address', 'u.city', 'u.postal_code')
                ->where('o.id', $orderId)
                ->first();

            if (!$order) {
                return response()->json(['error' => 'Order not found'], 404);
            }

            // Check permissions
            if ($user['role'] === 'customer' && $order->customer_id != $user['id']) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            if ($user['role'] === 'supplier') {
                $hasProducts = DB::table('order_items as oi')
                    ->join('products as p', 'oi.product_id', '=', 'p.id')
                    ->where('oi.order_id', $orderId)
                    ->where('p.supplier_id', $user['id'])
                    ->exists();

                if (!$hasProducts) {
                    return response()->json(['error' => 'Access denied'], 403);
                }
            }

            $items = DB::table('order_items as oi')
                ->join('products as p', 'oi.product_id', '=', 'p.id')
                ->leftJoin('users as s', 'p.supplier_id', '=', 's.id')
                ->select('oi.*', 'p.name as product_name', 'p.description', 'p.image', 's.username as supplier_name')
                ->where('oi.order_id', $orderId)
                ->get();

            $address = $order->address ?? '';
            if ($order->city) {
                $address .= ($address ? ', ' : '') . $order->city;
            }
            if ($order->postal_code) {
                $address .= ($address ? ', ' : '') . $order->postal_code;
            }

            return response()->json([
                'success' => true,
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'total_amount' => $order->total_amount,
                    'created_at' => $order->created_at,
                    'updated_at' => $order->updated_at ?? $order->created_at,
                    'customer' => [
                        'name' => $order->first_name . ' ' . $order->last_name,
                        'email' => $order->email,
                        'phone' => $order->phone,
                        'address' => $address
                    ],
                    'items' => $items
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch order: ' . $e->getMessage()], 500);
        }
    }

    public function createOrderBulk(Request $request)
    {
        $user = $request->get('auth_user');

        if ($user['role'] !== 'customer') {
            return response()->json(['error' => 'Only customers can create orders'], 403);
        }

        $data = $request->json()->all();
        $items = $data['items'] ?? [];

        if (empty($items) || !is_array($items)) {
            return response()->json(['error' => 'Items array is required'], 400);
        }

        try {
            DB::beginTransaction();

            $validatedItems = [];
            $totalAmount = 0;

            foreach ($items as $item) {
                $product_id = $item['product_id'] ?? 0;
                $quantity = $item['quantity'] ?? 0;

                if (empty($product_id) || $quantity <= 0) {
                    DB::rollBack();
                    return response()->json(['error' => 'Invalid item data'], 400);
                }

                $product = DB::table('products')->where('id', $product_id)->first();

                if (!$product) {
                    DB::rollBack();
                    return response()->json(['error' => "Product ID {$product_id} not found"], 404);
                }

                if ($product->stock < $quantity) {
                    DB::rollBack();
                    return response()->json(['error' => "Insufficient stock for product: {$product->name}. Available: {$product->stock}, Requested: {$quantity}"], 400);
                }

                $itemTotal = $product->price * $quantity;
                $totalAmount += $itemTotal;

                $validatedItems[] = [
                    'product_id' => $product_id,
                    'quantity' => $quantity,
                    'price' => $product->price,
                    'product' => $product
                ];
            }

            $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

            $orderId = DB::table('orders')->insertGetId([
                'customer_id' => $user['id'],
                'order_number' => $orderNumber,
                'status' => 'pending',
                'total_amount' => $totalAmount,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            foreach ($validatedItems as $item) {
                DB::table('order_items')->insert([
                    'order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'created_at' => now()
                ]);
            }

            DB::commit();

            $order = DB::table('orders')->where('id', $orderId)->first();

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'total_amount' => $order->total_amount,
                    'created_at' => $order->created_at,
                    'items' => array_map(function($item) {
                        return [
                            'product_id' => $item['product_id'],
                            'product_name' => $item['product']->name,
                            'quantity' => $item['quantity'],
                            'price' => $item['price'],
                            'total' => $item['price'] * $item['quantity']
                        ];
                    }, $validatedItems)
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create order: ' . $e->getMessage()], 500);
        }
    }

    public function updateOrderStatus(Request $request)
    {
        $user = $request->get('auth_user');
        $data = $request->json()->all();
        $orderId = $data['order_id'] ?? 0;
        $newStatus = $data['status'] ?? '';

        if (empty($orderId) || empty($newStatus)) {
            return response()->json(['error' => 'Order ID and status are required'], 400);
        }

        $allowedStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'completed'];
        if (!in_array($newStatus, $allowedStatuses)) {
            return response()->json(['error' => 'Invalid status'], 400);
        }

        try {
            $order = DB::table('orders')->where('id', $orderId)->first();

            if (!$order) {
                return response()->json(['error' => 'Order not found'], 404);
            }

            $currentStatus = $order->status;

            if ($user['role'] === 'customer') {
                if ($newStatus === 'cancelled' && $currentStatus === 'pending') {
                    DB::table('orders')->where('id', $orderId)->update(['status' => $newStatus]);
                    
                    $items = DB::table('order_items')->where('order_id', $orderId)->get();
                    foreach ($items as $item) {
                        DB::table('products')->where('id', $item->product_id)->increment('stock', $item->quantity);
                    }
                } elseif (in_array($newStatus, ['completed', 'delivered']) && in_array($currentStatus, ['shipped', 'delivered'])) {
                    DB::table('orders')->where('id', $orderId)->update(['status' => 'completed']);
                } else {
                    return response()->json(['error' => 'Invalid status change for customer'], 403);
                }
            } elseif ($user['role'] === 'supplier') {
                $hasProducts = DB::table('order_items as oi')
                    ->join('products as p', 'oi.product_id', '=', 'p.id')
                    ->where('oi.order_id', $orderId)
                    ->where('p.supplier_id', $user['id'])
                    ->exists();

                if (!$hasProducts) {
                    return response()->json(['error' => 'You can only update orders for your products'], 403);
                }

                $finalStatuses = ['shipped', 'delivered', 'completed', 'cancelled'];
                if (in_array($currentStatus, $finalStatuses)) {
                    return response()->json(['error' => "Order is already {$currentStatus} and cannot be updated"], 400);
                }

                if ($currentStatus !== 'pending') {
                    return response()->json(['error' => 'Only pending orders can be updated'], 400);
                }

                if (!in_array($newStatus, ['shipped', 'cancelled'])) {
                    return response()->json(['error' => 'Pending orders can only be updated to "shipped" or "cancelled"'], 400);
                }

                $orderItems = DB::table('order_items as oi')
                    ->join('products as p', 'oi.product_id', '=', 'p.id')
                    ->select('oi.product_id', 'oi.quantity', 'p.stock as current_stock', 'p.name as product_name')
                    ->where('oi.order_id', $orderId)
                    ->where('p.supplier_id', $user['id'])
                    ->get();

                if ($newStatus === 'shipped') {
                    foreach ($orderItems as $orderItem) {
                        if ($orderItem->current_stock < $orderItem->quantity) {
                            return response()->json(['error' => "Insufficient stock to ship product: {$orderItem->product_name}. Available: {$orderItem->current_stock}, Required: {$orderItem->quantity}"], 400);
                        }
                    }

                    foreach ($orderItems as $orderItem) {
                        DB::table('products')->where('id', $orderItem->product_id)->decrement('stock', $orderItem->quantity);
                    }
                }

                DB::table('orders')->where('id', $orderId)->update(['status' => $newStatus]);

                $orderNumber = $order->order_number ?? '#' . $orderId;
                $message = "Your order {$orderNumber} status has been updated to: {$newStatus}";
                DB::table('notifications')->insert([
                    'user_id' => $order->customer_id,
                    'message' => $message,
                    'type' => 'order_update',
                    'created_at' => now()
                ]);
            } elseif ($user['role'] === 'admin') {
                DB::table('orders')->where('id', $orderId)->update(['status' => $newStatus]);
            } else {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $updatedOrder = DB::table('orders')->where('id', $orderId)->first();
            $items = DB::table('order_items as oi')
                ->join('products as p', 'oi.product_id', '=', 'p.id')
                ->select('oi.*', 'p.name as product_name')
                ->where('oi.order_id', $orderId)
                ->get();
            $updatedOrder->items = $items;

            return response()->json([
                'success' => true,
                'message' => 'Order status updated',
                'order' => $updatedOrder
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update order: ' . $e->getMessage()], 500);
        }
    }
}
