<?php
require_once 'db.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // First, get all ledger entries that don't have a code
    $query = "SELECT l.id, ah.name as head_type 
             FROM ledgers l
             JOIN transactions t ON l.transaction_id = t.id 
             JOIN accounting_heads ah ON t.head_id = ah.id 
             WHERE l.ledger_code IS NULL OR l.ledger_code = ''";
    
    $result = $conn->query($query);
    
    // Initialize counters for each type
    $counters = [
        'Assets' => 1,
        'Liabilities' => 1,
        'Equities' => 1,
        'Income' => 1,
        'Expenses' => 1
    ];
    
    // Process each ledger entry
    while($row = $result->fetch_assoc()) {
        // Get prefix based on head type
        $prefix = '';
        switch($row['head_type']) {
            case 'Assets':
                $prefix = 'AS';
                break;
            case 'Liabilities':
                $prefix = 'LB';
                break;
            case 'Equities':
                $prefix = 'EQ';
                break;
            case 'Income':
                $prefix = 'IN';
                break;
            case 'Expenses':
                $prefix = 'EX';
                break;
        }
        
        // Generate code
        $code = $prefix . str_pad($counters[$row['head_type']]++, 4, '0', STR_PAD_LEFT);
        
        // Update the record
        $update = "UPDATE ledgers SET ledger_code = ? WHERE id = ?";
        $stmt = $conn->prepare($update);
        $stmt->bind_param("si", $code, $row['id']);
        $stmt->execute();
        
        echo "Updated ledger ID {$row['id']} with code {$code}<br>";
    }
    
    echo "All ledger codes have been updated successfully!";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 