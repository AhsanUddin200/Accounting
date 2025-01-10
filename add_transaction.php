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
    $debit_head_id = $_POST['debit_head_id'] ?? '';
    $debit_category_id = $_POST['debit_category_id'] ?? '';
    $debit_amount = $_POST['debit_amount'] ?? '';
    $credit_head_id = $_POST['credit_head_id'] ?? '';
    $credit_category_id = $_POST['credit_category_id'] ?? '';
    $credit_amount = $_POST['credit_amount'] ?? '';
    $date = $_POST['date'] ?? '';
    $description = $_POST['description'] ?? '';
    $user_id = $_SESSION['user_id'];

    try {
        // Start transaction
        $conn->begin_transaction();

        // Get head names for type determination
        $head_query = "SELECT id, name FROM accounting_heads WHERE id IN (?, ?)";
        $stmt = $conn->prepare($head_query);
        $stmt->bind_param("ii", $debit_head_id, $credit_head_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $heads = [];
        while($row = $result->fetch_assoc()) {
            $heads[$row['id']] = $row['name'];
        }

        // Insert debit transaction
        $query = "INSERT INTO transactions (user_id, head_id, category_id, amount, type, date, description) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        
        // Set type based on head for debit entry
        $debit_type = 'expense'; // Default for Assets and Expenses
        if (in_array($heads[$debit_head_id], ['Liabilities', 'Equities', 'Income'])) {
            $debit_type = 'income';
        }
        
        $stmt->bind_param("iiidsss", 
            $user_id,
            $debit_head_id,
            $debit_category_id,
            $debit_amount,
            $debit_type,
            $date,
            $description
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Error inserting debit transaction: " . $stmt->error);
        }

        // Insert credit transaction
        $query = "INSERT INTO transactions (user_id, head_id, category_id, amount, type, date, description) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        
        // Set type based on head for credit entry
        $credit_type = 'expense'; // Default for Assets and Expenses
        if (in_array($heads[$credit_head_id], ['Liabilities', 'Equities', 'Income'])) {
            $credit_type = 'income';
        }
        
        $stmt->bind_param("iiidsss", 
            $user_id,
            $credit_head_id,
            $credit_category_id,
            $credit_amount,
            $credit_type,
            $date,
            $description
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Error inserting credit transaction: " . $stmt->error);
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
                    <!-- Debit Section -->
                    <div class="row mb-4">
                        <h5>Debit Entry</h5>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Accounting Head</label>
                                <select name="debit_head_id" id="debit_head_id" class="form-select" required>
                                    <option value="">Select Head</option>
                                    <?php foreach($heads as $head): ?>
                                        <option value="<?php echo $head['id']; ?>">
                                            <?php echo htmlspecialchars($head['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">    
                                <label>Category</label>
                                <select name="debit_category_id" id="debit_category_id" class="form-select" required>
                                    <option value="">Select Head First</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Amount</label>
                                <input type="number" name="debit_amount" class="form-control" step="0.01" required>
                            </div>
                        </div>
                    </div>

                    <!-- Credit Section -->
                    <div class="row mb-4">
                        <h5>Credit Entry</h5>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Accounting Head</label>
                                <select name="credit_head_id" id="credit_head_id" class="form-select" required>
                                    <option value="">Select Head</option>
                                    <?php foreach($heads as $head): ?>
                                        <option value="<?php echo $head['id']; ?>">
                                            <?php echo htmlspecialchars($head['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">    
                                <label>Category</label>
                                <select name="credit_category_id" id="credit_category_id" class="form-select" required>
                                    <option value="">Select Head First</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Amount</label>
                                <input type="number" name="credit_amount" class="form-control" step="0.01" required>
                            </div>
                        </div>
                    </div>

                    <!-- Common Fields -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Date</label>
                                <input type="date" name="date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="description" class="form-control" rows="4"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="text-end mt-3">
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
            // When debit accounting head changes
            $('#debit_head_id').change(function() {
                var head_id = $(this).val();
                if (head_id) {
                    $.ajax({
                        url: 'get_categories.php',
                        type: 'GET',
                        data: { head_id: head_id },
                        success: function(response) {
                            $('#debit_category_id').html(response);
                        }
                    });
                } else {
                    $('#debit_category_id').html('<option value="">Select Head First</option>');
                }
            });

            // When credit accounting head changes
            $('#credit_head_id').change(function() {
                var head_id = $(this).val();
                if (head_id) {
                    $.ajax({
                        url: 'get_categories.php',
                        type: 'GET',
                        data: { head_id: head_id },
                        success: function(response) {
                            $('#credit_category_id').html(response);
                        }
                    });
                } else {
                    $('#credit_category_id').html('<option value="">Select Head First</option>');
                }
            });
        });
    </script>
</body>
</html>