<?php
require_once 'session.php';
require_once 'db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid transaction ID";
    header("Location: view_transactions.php");
    exit();
}

try {
    $conn->begin_transaction();

    // Get original transaction details
    $stmt = $conn->prepare("SELECT * FROM transactions WHERE id = ?");
    $stmt->bind_param("i", $_GET['id']);
    $stmt->execute();
    $original = $stmt->get_result()->fetch_assoc();

    if (!$original) {
        throw new Exception("Transaction not found");
    }

    // Create contra entry (opposite of original transaction)
    $stmt = $conn->prepare("INSERT INTO transactions 
        (user_id, amount, type, category_id, description, date, contra_ref) 
        VALUES (?, ?, ?, ?, ?, NOW(), ?)");

    // If original was income, contra is expense and vice versa
    $contra_type = ($original['type'] == 'income') ? 'expense' : 'income';
    $description = "Contra Entry for Transaction #" . $original['id'];

    $stmt->bind_param(
        "idissi",
        $_SESSION['user_id'],
        $original['amount'],
        $contra_type,
        $original['category_id'],
        $description,
        $original['id']
    );

    $stmt->execute();

    // Update original transaction to reference the contra entry
    $contra_id = $conn->insert_id;
    $stmt = $conn->prepare("UPDATE transactions SET contra_ref = ? WHERE id = ?");
    $stmt->bind_param("ii", $contra_id, $_GET['id']);
    $stmt->execute();

    $conn->commit();
    $_SESSION['success'] = "Contra entry created successfully";

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = "Error creating contra entry: " . $e->getMessage();
}

header("Location: view_transactions.php");
exit();
?> 