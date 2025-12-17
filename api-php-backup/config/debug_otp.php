<?php
/**
 * Debug OTP Records
 * This will show all OTP records in the database
 */

require_once __DIR__ . '/database.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>OTP Debug Information</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1000px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #4f46e5; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #4f46e5; color: white; }
        tr:hover { background-color: #f5f5f5; }
        .expired { color: #ef4444; }
        .active { color: #10b981; }
        .info { color: #3b82f6; background: #dbeafe; padding: 15px; border-radius: 5px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 OTP Debug Information</h1>
        
        <?php
        try {
            // Get all OTP records
            $stmt = $pdo->query("SELECT * FROM otp_verifications ORDER BY created_at DESC LIMIT 20");
            $otpRecords = $stmt->fetchAll();
            
            if (empty($otpRecords)) {
                echo '<div class="info">No OTP records found in the database.</div>';
            } else {
                echo '<div class="info">Found ' . count($otpRecords) . ' OTP record(s)</div>';
                echo '<table>';
                echo '<tr>';
                echo '<th>ID</th>';
                echo '<th>Email</th>';
                echo '<th>OTP Code</th>';
                echo '<th>Username</th>';
                echo '<th>Created At</th>';
                echo '<th>Expires At</th>';
                echo '<th>Status</th>';
                echo '</tr>';
                
                foreach ($otpRecords as $record) {
                    $now = new DateTime();
                    $expires = new DateTime($record['expires_at']);
                    $isExpired = $now > $expires;
                    $statusClass = $isExpired ? 'expired' : 'active';
                    $statusText = $isExpired ? 'Expired' : 'Active';
                    
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($record['id']) . '</td>';
                    echo '<td>' . htmlspecialchars($record['email']) . '</td>';
                    echo '<td><strong>' . htmlspecialchars($record['otp_code']) . '</strong></td>';
                    echo '<td>' . htmlspecialchars($record['username']) . '</td>';
                    echo '<td>' . htmlspecialchars($record['created_at']) . '</td>';
                    echo '<td>' . htmlspecialchars($record['expires_at']) . '</td>';
                    echo '<td class="' . $statusClass . '">' . $statusText . '</td>';
                    echo '</tr>';
                }
                
                echo '</table>';
            }
            
            // Show current server time
            echo '<div class="info">';
            echo '<strong>Current Server Time:</strong> ' . date('Y-m-d H:i:s') . '<br>';
            echo '<strong>MySQL NOW():</strong> ';
            $timeStmt = $pdo->query("SELECT NOW() as current_time");
            $timeResult = $timeStmt->fetch();
            echo htmlspecialchars($timeResult['current_time']);
            echo '</div>';
            
        } catch (PDOException $e) {
            echo '<div class="info" style="background: #fee2e2; color: #ef4444;">';
            echo '<strong>Error:</strong> ' . htmlspecialchars($e->getMessage());
            echo '</div>';
        }
        ?>
        
        <hr>
        <p><a href="../../views/guest/register.html">← Back to Registration</a></p>
    </div>
</body>
</html>

