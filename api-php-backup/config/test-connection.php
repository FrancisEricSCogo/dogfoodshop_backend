<?php
/**
 * Test Database Connection
 * Access: http://localhost/dogfoodshop/api/config/test-connection.php
 */

header('Content-Type: application/json');

$host = 'localhost';
$dbname = 'dogfoodshop';
$username = 'root';
$password = '';

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'tests' => []
];

// Test 1: Check if MySQL server is accessible
try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $results['tests']['mysql_server'] = [
        'status' => 'success',
        'message' => 'MySQL server is accessible'
    ];
} catch (PDOException $e) {
    $results['tests']['mysql_server'] = [
        'status' => 'error',
        'message' => 'Cannot connect to MySQL server: ' . $e->getMessage(),
        'hint' => 'Make sure MySQL is running in XAMPP Control Panel'
    ];
    echo json_encode($results, JSON_PRETTY_PRINT);
    exit();
}

// Test 2: Check if database exists
try {
    $stmt = $pdo->query("SHOW DATABASES LIKE '$dbname'");
    $dbExists = $stmt->rowCount() > 0;
    
    if ($dbExists) {
        $results['tests']['database_exists'] = [
            'status' => 'success',
            'message' => "Database '$dbname' exists"
        ];
    } else {
        $results['tests']['database_exists'] = [
            'status' => 'error',
            'message' => "Database '$dbname' does not exist",
            'hint' => 'Create the database in phpMyAdmin or import database_setup.sql'
        ];
        echo json_encode($results, JSON_PRETTY_PRINT);
        exit();
    }
} catch (PDOException $e) {
    $results['tests']['database_exists'] = [
        'status' => 'error',
        'message' => 'Error checking database: ' . $e->getMessage()
    ];
    echo json_encode($results, JSON_PRETTY_PRINT);
    exit();
}

// Test 3: Connect to the database
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    $results['tests']['database_connection'] = [
        'status' => 'success',
        'message' => 'Successfully connected to database'
    ];
} catch (PDOException $e) {
    $results['tests']['database_connection'] = [
        'status' => 'error',
        'message' => 'Cannot connect to database: ' . $e->getMessage()
    ];
    echo json_encode($results, JSON_PRETTY_PRINT);
    exit();
}

// Test 4: Check if users table exists
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $tableExists = $stmt->rowCount() > 0;
    
    if ($tableExists) {
        // Try a simple query
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $count = $stmt->fetch()['count'];
        
        $results['tests']['users_table'] = [
            'status' => 'success',
            'message' => "Users table exists with $count user(s)"
        ];
    } else {
        $results['tests']['users_table'] = [
            'status' => 'error',
            'message' => 'Users table does not exist',
            'hint' => 'Import database_setup.sql to create all tables'
        ];
    }
} catch (PDOException $e) {
    $results['tests']['users_table'] = [
        'status' => 'error',
        'message' => 'Error checking users table: ' . $e->getMessage()
    ];
}

// Test 5: Test a simple query
try {
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch();
    
    if ($result && $result['test'] == 1) {
        $results['tests']['query_test'] = [
            'status' => 'success',
            'message' => 'Database queries are working'
        ];
    } else {
        $results['tests']['query_test'] = [
            'status' => 'error',
            'message' => 'Query test failed'
        ];
    }
} catch (PDOException $e) {
    $results['tests']['query_test'] = [
        'status' => 'error',
        'message' => 'Query test error: ' . $e->getMessage()
    ];
}

// Calculate overall status
$allPassed = true;
foreach ($results['tests'] as $test) {
    if ($test['status'] !== 'success') {
        $allPassed = false;
        break;
    }
}

$results['overall_status'] = $allPassed ? 'success' : 'error';
$results['message'] = $allPassed 
    ? 'All database connection tests passed!' 
    : 'Some tests failed. Please check the details above.';

echo json_encode($results, JSON_PRETTY_PRINT);
?>

