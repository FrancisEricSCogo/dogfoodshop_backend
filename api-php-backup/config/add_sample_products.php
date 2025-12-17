<?php
/**
 * Add Sample Products Script
 * This script adds 5 sample dog food products to the database
 * 
 * Usage: Open in browser: http://localhost/dogfoodshop/api/config/add_sample_products.php
 */

require_once __DIR__ . '/database.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Sample Products</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #4f46e5;
            padding-bottom: 10px;
        }
        .success {
            background: #d1fae5;
            color: #065f46;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #10b981;
        }
        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #ef4444;
        }
        .info {
            background: #dbeafe;
            color: #1e40af;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #3b82f6;
        }
        .product-item {
            background: #f8fafc;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            border-left: 4px solid #4f46e5;
        }
        .product-item strong {
            color: #1e293b;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🐕 Add Sample Products</h1>
        
        <?php
        try {
            // Check if supplier exists, if not create one
            $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'supplier' LIMIT 1");
            $stmt->execute();
            $supplier = $stmt->fetch();
            
            if (!$supplier) {
                // Create a default supplier
                $supplierPassword = password_hash('supplier123', PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, username, email, password, role) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute(['Sample', 'Supplier', 'supplier', 'supplier@dogfoodshop.com', $supplierPassword, 'supplier']);
                $supplierId = $pdo->lastInsertId();
                echo '<div class="info">✓ Created default supplier account (username: supplier, password: supplier123)</div>';
            } else {
                $supplierId = $supplier['id'];
                echo '<div class="info">✓ Using existing supplier (ID: ' . $supplierId . ')</div>';
            }
            
            // Sample products data
            $sampleProducts = [
                [
                    'name' => 'Premium Dry Dog Food - Chicken & Rice',
                    'description' => 'High-quality dry dog food made with real chicken and brown rice. Rich in protein and essential nutrients for your dog\'s health. Suitable for all dog breeds and sizes. Contains omega-3 fatty acids for healthy skin and coat.',
                    'price' => 29.99,
                    'stock' => 50,
                    'image' => 'premium-chicken-rice.jpg'
                ],
                [
                    'name' => 'Grain-Free Salmon Formula',
                    'description' => 'Natural grain-free dog food with fresh salmon as the first ingredient. Perfect for dogs with sensitive stomachs or grain allergies. Packed with antioxidants and probiotics for digestive health.',
                    'price' => 34.99,
                    'stock' => 35,
                    'image' => 'grain-free-salmon.jpg'
                ],
                [
                    'name' => 'Puppy Growth Formula',
                    'description' => 'Specially formulated for puppies up to 12 months. Contains DHA for brain development and calcium for strong bones. Easy to digest and supports healthy growth. Made with real chicken and vegetables.',
                    'price' => 27.99,
                    'stock' => 40,
                    'image' => 'puppy-growth.jpg'
                ],
                [
                    'name' => 'Senior Dog Health Formula',
                    'description' => 'Designed for senior dogs (7+ years). Lower in calories but rich in nutrients. Contains glucosamine for joint health and fiber for digestive wellness. Made with real lamb and sweet potatoes.',
                    'price' => 32.99,
                    'stock' => 30,
                    'image' => 'senior-health.jpg'
                ],
                [
                    'name' => 'Organic Beef & Vegetable Mix',
                    'description' => '100% organic dog food with grass-fed beef and fresh vegetables. No artificial preservatives, colors, or flavors. Certified organic and sustainably sourced. Perfect for health-conscious pet owners.',
                    'price' => 39.99,
                    'stock' => 25,
                    'image' => 'organic-beef-veggie.jpg'
                ]
            ];
            
            // Check if products already exist
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE supplier_id = ?");
            $stmt->execute([$supplierId]);
            $existingCount = $stmt->fetch()['count'];
            
            if ($existingCount > 0) {
                echo '<div class="info">ℹ️ Found ' . $existingCount . ' existing product(s). Adding new sample products...</div>';
            }
            
            $addedCount = 0;
            $skippedCount = 0;
            
            foreach ($sampleProducts as $product) {
                // Check if product with same name already exists
                $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ? AND supplier_id = ?");
                $stmt->execute([$product['name'], $supplierId]);
                
                if ($stmt->fetch()) {
                    $skippedCount++;
                    echo '<div class="info">⊘ Skipped: ' . htmlspecialchars($product['name']) . ' (already exists)</div>';
                    continue;
                }
                
                // Insert product
                $stmt = $pdo->prepare("INSERT INTO products (supplier_id, name, description, price, stock, image) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $supplierId,
                    $product['name'],
                    $product['description'],
                    $product['price'],
                    $product['stock'],
                    $product['image']
                ]);
                
                $addedCount++;
                echo '<div class="product-item">';
                echo '<strong>✓ Added:</strong> ' . htmlspecialchars($product['name']) . '<br>';
                echo 'Price: ₱' . number_format($product['price'], 2) . ' | Stock: ' . $product['stock'] . ' units';
                echo '</div>';
            }
            
            echo '<div class="success">';
            echo '<h2>✅ Success!</h2>';
            echo '<p><strong>' . $addedCount . '</strong> product(s) added successfully!</p>';
            if ($skippedCount > 0) {
                echo '<p><strong>' . $skippedCount . '</strong> product(s) skipped (already exist).</p>';
            }
            echo '<p>You can now view these products in the dashboard.</p>';
            echo '</div>';
            
            // Display all products
            $stmt = $pdo->prepare("SELECT * FROM products WHERE supplier_id = ? ORDER BY created_at DESC");
            $stmt->execute([$supplierId]);
            $allProducts = $stmt->fetchAll();
            
            if (count($allProducts) > 0) {
                echo '<h2>All Products in Database:</h2>';
                foreach ($allProducts as $prod) {
                    echo '<div class="product-item">';
                    echo '<strong>ID ' . $prod['id'] . ':</strong> ' . htmlspecialchars($prod['name']) . '<br>';
                    echo 'Price: ₱' . number_format($prod['price'], 2) . ' | Stock: ' . $prod['stock'] . ' units';
                    echo '</div>';
                }
            }
            
        } catch (PDOException $e) {
            echo '<div class="error">';
            echo '<h2>❌ Error!</h2>';
            echo '<p>Failed to add sample products: ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '</div>';
        }
        ?>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e2e8f0;">
            <p><a href="../../views/customer/dashboard.html" style="color: #4f46e5; text-decoration: none; font-weight: 600;">→ Go to Dashboard</a></p>
        </div>
    </div>
</body>
</html>

