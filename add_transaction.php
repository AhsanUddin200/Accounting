<?php
require_once 'session.php';
require_once 'db.php';

// Check admin access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $head_id = $_POST['head_id'] ?? '';
    $amount = $_POST['amount'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $date = $_POST['date'] ?? '';
    $description = $_POST['description'] ?? '';
    $user_id = $_SESSION['user_id'];

    // Get head type from accounting_heads
    $head_query = "SELECT type FROM accounting_heads WHERE id = ?";
    $stmt = $conn->prepare($head_query);
    $stmt->bind_param("i", $head_id);
    $stmt->execute();
    $head_result = $stmt->get_result();
    $head_data = $head_result->fetch_assoc();
    
    // Determine transaction type based on head type
    $type = ($head_data['type'] == 'income') ? 'income' : 'expense';

    try {
        // Start transaction
        $conn->begin_transaction();

        // Insert into transactions table
        $query = "INSERT INTO transactions (user_id, type, amount, head_id, category_id, description, date) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("isdiiss", $user_id, $type, $amount, $head_id, $category_id, $description, $date);
        
        if (!$stmt->execute()) {
            throw new Exception("Error inserting transaction: " . $stmt->error);
        }

        $transaction_id = $conn->insert_id;

        // Insert into ledgers table
        $ledger_query = "INSERT INTO ledgers (transaction_id, account_type, debit, credit, balance, description, date) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($ledger_query);
        
        // Set debit/credit based on transaction type
        $debit = ($type == 'expense') ? $amount : 0;
        $credit = ($type == 'income') ? $amount : 0;
        $balance = $credit - $debit;
        
        $stmt->bind_param("isddiss", $transaction_id, $head_data['type'], $debit, $credit, $balance, $description, $date);
        
        if (!$stmt->execute()) {
            throw new Exception("Error inserting ledger entry: " . $stmt->error);
        }

        // Commit transaction
        $conn->commit();
        
        $_SESSION['success'] = "Transaction added successfully!";
        header("Location: accounting.php");
        exit();

    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch accounting heads in specific order
$heads_query = "SELECT * FROM accounting_heads ORDER BY FIELD(name, 'Assets', 'Liabilities', 'Equities', 'Income', 'Expenses')";
$heads = $conn->query($heads_query);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Transaction</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .form-select, .form-control {
            margin-bottom: 20px;
        }
        textarea.form-control {
            min-height: 120px;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container py-4">
        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-plus-circle"></i> Add New Transaction</h4>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <!-- Accounting Head -->
                    <div class="form-group">
                        <label>Accounting Head</label>
                        <select name="head_id" id="head_id" class="form-select" required>
                            <option value="">Select Head</option>
                            <?php while($head = $heads->fetch_assoc()): ?>
                                <option value="<?php echo $head['id']; ?>">
                                    <?php echo htmlspecialchars($head['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Amount -->
                    <div class="form-group">
                        <label>Amount</label>
                        <input type="number" name="amount" class="form-control" step="0.01" required>
                    </div>

                    <!-- Category -->
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category_id" id="category_id" class="form-select" required>
                            <option value="">Select Head First</option>
                        </select>
                    </div>

                    <!-- Date -->
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <!-- Description -->
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="4"></textarea>
                    </div>

                    <!-- Buttons -->
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Transaction
                        </button>
                        <a href="accounting.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // When accounting head changes
            $('#head_id').change(function() {
                var head_id = $(this).val();
                
                if (head_id) {
                    $.ajax({
                        url: 'get_categories.php',
                        type: 'GET',
                        data: { head_id: head_id },
                        success: function(response) {
                            $('#category_id').html(response);
                        }
                    });
                } else {
                    $('#category_id').html('<option value="">Select Head First</option>');
                }
            });
        });
    </script>
</body>
</html>
