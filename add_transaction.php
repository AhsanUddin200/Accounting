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

        // Get head type from accounting_heads
        $head_type_query = "SELECT name FROM accounting_heads WHERE id = ?";
        $stmt = $conn->prepare($head_type_query);
        $stmt->bind_param("i", $head_id);
        $stmt->execute();
        $head_result = $stmt->get_result();
        $head_type = $head_result->fetch_assoc()['name'];

        // Generate ledger code
        $prefix = '';
        switch($head_type) {
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
            default:
                $prefix = 'UN';
        }

        // Get last code for this prefix
        $last_code_query = "SELECT ledger_code 
                           FROM ledgers 
                           WHERE ledger_code LIKE '$prefix%' 
                           ORDER BY ledger_code DESC 
                           LIMIT 1";
        $result = $conn->query($last_code_query);

        if ($result && $result->num_rows > 0) {
            $last_code = $result->fetch_assoc()['ledger_code'];
            $number = intval(substr($last_code, 2)) + 1;
        } else {
            $number = 1;
        }

        $ledger_code = $prefix . str_pad($number, 4, '0', STR_PAD_LEFT);

        // Insert into ledgers with the generated code
        $ledger_query = "INSERT INTO ledgers (
            ledger_code,
            transaction_id, 
            account_type, 
            debit, 
            credit, 
            balance, 
            description, 
            date
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($ledger_query);
        $stmt->bind_param("sisddiss", 
            $ledger_code,
            $transaction_id,
            $head_type,
            $debit,
            $credit,
            $balance,
            $description,
            $date
        );

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

function generateLedgerCode($head_id, $conn) {
    // Get the head prefix (e.g., AS for Assets, EX for Expenses)
    $prefix_query = "SELECT 
        CASE 
            WHEN name = 'Assets' THEN 'AS'
            WHEN name = 'Liabilities' THEN 'LB'
            WHEN name = 'Equities' THEN 'EQ'
            WHEN name = 'Income' THEN 'IN'
            WHEN name = 'Expenses' THEN 'EX'
        END as prefix
        FROM accounting_heads WHERE id = ?";
    
    $stmt = $conn->prepare($prefix_query);
    $stmt->bind_param("i", $head_id);
    $stmt->execute();
    $prefix_result = $stmt->get_result();
    $prefix = $prefix_result->fetch_assoc()['prefix'];

    // Get the last number used for this prefix
    $last_code_query = "SELECT ledger_code 
                       FROM ledgers 
                       WHERE ledger_code LIKE '$prefix%' 
                       ORDER BY ledger_code DESC 
                       LIMIT 1";
    $result = $conn->query($last_code_query);
    
    if ($result->num_rows > 0) {
        $last_code = $result->fetch_assoc()['ledger_code'];
        $number = intval(substr($last_code, 2)) + 1;
    } else {
        $number = 1;
    }

    // Generate new code (e.g., AS0001)
    return $prefix . str_pad($number, 4, '0', STR_PAD_LEFT);
}
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
                            <?php foreach($heads as $head): ?>
                                <option value="<?php echo $head['id']; ?>">
                                    <?php 
                                    $type = '';
                                    switch($head['name']) {
                                        case 'Assets':
                                           
                                            break;
                                        case 'Liabilities':
                                           
                                            break;
                                        case 'Equities':
                                          
                                            break;
                                        case 'Income':
                                           
                                            break;
                                        case 'Expenses':
                                           
                                            break;
                                    }
                                    echo htmlspecialchars($head['name']) . ' ' . $type; 
                                    ?>
                                </option>
                            <?php endforeach; ?>
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