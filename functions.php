<?php
// functions.php

/**
 * Logs an action to the audit_logs table.
 *
 * @param mysqli $conn Database connection
 * @param int $user_id ID of the user performing the action
 * @param string $action Description of the action
 * @param string $details Additional details
 */
function log_action($conn, $user_id, $action, $details = '') {
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("iss", $user_id, $action, $details);
        $stmt->execute();
        $stmt->close();
    } else {
        error_log("Failed to prepare log_action statement: (" . $conn->errno . ") " . $conn->error);
    }
}

function formatCurrency($amount) {
    return 'PKR ' . number_format($amount, 2);
}

/**
 * Records a transaction in the ledger system using double-entry bookkeeping
 *
 * @param mysqli $conn Database connection
 * @param int $transaction_id ID of the transaction
 * @param string $type Type of transaction (income/expense)
 * @param float $amount Transaction amount
 * @param string $description Transaction description
 * @return bool Returns true if successful, false otherwise
 */
function recordLedgerEntry($conn, $transaction_id, $type, $amount, $description) {
    try {
        // Start transaction
        $conn->begin_transaction();

        // Validate inputs
        if (empty($transaction_id) || empty($type) || empty($amount)) {
            throw new Exception("Missing required parameters");
        }

        if ($type == 'income') {
            $stmt = $conn->prepare("INSERT INTO ledgers 
                (transaction_id, account_type, debit, credit, balance, description, date) 
                VALUES 
                (?, 'asset', ?, 0, ?, ?, NOW()),
                (?, 'income', 0, ?, ?, ?, NOW())");
            
            if (!$stmt) {
                throw new Exception("Failed to prepare statement: " . $conn->error);
            }

            $stmt->bind_param("iddsidds", 
                $transaction_id, $amount, $amount, $description,
                $transaction_id, $amount, $amount, $description
            );
        } else {
            $stmt = $conn->prepare("INSERT INTO ledgers 
                (transaction_id, account_type, debit, credit, balance, description, date) 
                VALUES 
                (?, 'expense', ?, 0, ?, ?, NOW()),
                (?, 'asset', 0, ?, ?, ?, NOW())");
            
            if (!$stmt) {
                throw new Exception("Failed to prepare statement: " . $conn->error);
            }

            $stmt->bind_param("iddsidds",
                $transaction_id, $amount, $amount, $description,
                $transaction_id, $amount, -$amount, $description
            );
        }

        // Execute the statement
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute statement: " . $stmt->error);
        }

        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        // Log the successful entry
        log_action($conn, $_SESSION['user_id'] ?? 0, 
                  "Ledger Entry Created", 
                  "Transaction ID: $transaction_id, Type: $type, Amount: " . formatCurrency($amount));
        
        return true;

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        // Log the error
        error_log("Error recording ledger entry: " . $e->getMessage());
        error_log("Transaction details - ID: $transaction_id, Type: $type, Amount: $amount");
        
        return false;
    }
}

/**
 * Gets the current balance for a specific account type
 *
 * @param mysqli $conn Database connection
 * @param string $account_type The type of account (asset/liability/income/expense/equity)
 * @return float Returns the current balance
 */
function getLedgerBalance($conn, $account_type) {
    $stmt = $conn->prepare("SELECT 
        SUM(debit) - SUM(credit) as balance 
        FROM ledgers 
        WHERE account_type = ?");
    
    if ($stmt) {
        $stmt->bind_param("s", $account_type);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['balance'] ?? 0;
    }
    return 0;
}
?>
