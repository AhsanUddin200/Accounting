<?php
// view_transactions.php
require 'session.php';
require 'db.php';

// Initialize variables
$transactions = [];
$categories = [];
$success = "";
$error = "";

// Fetch categories for filter
$cat_query = "SELECT id, name FROM categories ORDER BY name ASC";
$cat_result = $conn->query($cat_query);
if ($cat_result) {
    while ($row = $cat_result->fetch_assoc()) {
        $categories[] = $row;
    }
} else {
    die("Error fetching categories: " . $conn->error);
}

// Handle Filters
$where_clauses = [];
$params = [];
$types = "";

// Always filter by user_id
$where_clauses[] = "transactions.user_id = ?";
$params[] = $_SESSION['user_id'];
$types .= "i";

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    // Filter by type
    if (!empty($_GET['type'])) {
        $where_clauses[] = "transactions.type = ?";
        $params[] = $_GET['type'];
        $types .= "s";
    }

    // Filter by category
    if (!empty($_GET['category'])) {
        $where_clauses[] = "transactions.category_id = ?";
        $params[] = $_GET['category'];
        $types .= "i";
    }

    // Filter by date range
    if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
        $where_clauses[] = "transactions.date BETWEEN ? AND ?";
        $params[] = $_GET['start_date'];
        $params[] = $_GET['end_date'];
        $types .= "ss";
    }
}

// Build the query
$query = "SELECT transactions.id, transactions.amount, transactions.type, categories.name AS category, transactions.date, transactions.description,
          CASE
              WHEN transactions.contra_ref IS NOT NULL THEN 'Reversed'
              WHEN EXISTS(SELECT 1 FROM transactions WHERE contra_ref = transactions.id) THEN 'Reversed'
              ELSE 'Active'
          END as status
          FROM transactions
          JOIN categories ON transactions.category_id = categories.id
          WHERE " . implode(" AND ", $where_clauses) . "
          ORDER BY transactions.date DESC";

// Prepare and execute the statement
$stmt = $conn->prepare($query);
if ($stmt) {
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
    $stmt->close();
} else {
    die("Error preparing statement: " . $conn->error);
}

// Handle Deletion (Optional)
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    // Ensure the transaction belongs to the user
    $delete_stmt = $conn->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
    if ($delete_stmt) {
        $delete_stmt->bind_param("ii", $delete_id, $_SESSION['user_id']);
        if ($delete_stmt->execute()) {
            if ($delete_stmt->affected_rows > 0) {
                $success = "Transaction deleted successfully.";
            } else {
                $error = "No such transaction found.";
            }
        } else {
            $error = "Error deleting transaction: " . $delete_stmt->error;
        }
        $delete_stmt->close();
    } else {
        $error = "Prepare failed for deletion: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>View Transactions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #27AE60;
            --danger-color: #C0392B;
            --warning-color: #f72585;
            --info-color: #4895ef;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }

        body {
            background-color: #f5f6fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 0.5rem 0;
            min-height: 60px;
        }

        .navbar-brand {
            font-weight: 600;
            color: white !important;
            font-size: 1.1rem;
        }

        .nav-link {
            color: rgba(255,255,255,0.9) !important;
            transition: all 0.3s ease;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        .card-header {
            background: var(--primary-color);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1rem 1.5rem;
            font-weight: 600;
        }

        .form-control, .form-select {
            border-radius: 8px;
            padding: 0.6rem 1rem;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .btn-primary {
            background: var(--primary-color);
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: var(--secondary-color);
            transform: translateY(-1px);
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background: var(--primary-color);
            color: white;
            font-weight: 500;
            border: none;
            padding: 1rem;
        }

        .badge-income {
            background-color: var(--success-color);
            color: white;
            padding: 0.5em 1em;
            border-radius: 6px;
        }

        .badge-expense {
            background-color: var(--danger-color);
            color: white;
            padding: 0.5em 1em;
            border-radius: 6px;
        }

        .btn-action {
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .btn-action:hover {
            transform: translateY(-2px);
        }

        .empty-state {
            padding: 3rem 1rem;
            text-align: center;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 3rem;
            color: var(--primary-color);
            opacity: 0.5;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-list me-2"></i>Transaction History
            </a>
            <div class="ms-auto">
                <a href="user_dashboard.php" class="nav-link">
                    <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filter Card -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-filter me-2"></i>Filter Transactions</span>
                <a href="add_transaction.php" class="btn btn-light btn-sm">
                    <i class="fas fa-plus me-1"></i>New Transaction
                </a>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Transaction Type</label>
                        <select name="type" class="form-select">
                            <option value="">All Types</option>
                            <option value="income" <?php echo (isset($_GET['type']) && $_GET['type'] == 'income') ? 'selected' : ''; ?>>Income</option>
                            <option value="expense" <?php echo (isset($_GET['type']) && $_GET['type'] == 'expense') ? 'selected' : ''; ?>>Expense</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" 
                                    <?php echo (isset($_GET['category']) && $_GET['category'] == $category['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" 
                               value="<?php echo isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : ''; ?>">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" 
                               value="<?php echo isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : ''; ?>">
                    </div>

                    <div class="col-md-2 d-flex align-items-end">
                        <div class="d-grid gap-2 w-100">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i> Search
                            </button>
                            <a href="view_transactions.php" class="btn btn-outline-secondary">
                                <i class="fas fa-redo me-1"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Transactions Card -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list me-2"></i>Transactions List
            </div>
            <div class="card-body p-0">
                <?php if (count($transactions) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Category</th>
                                    <th>Amount</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td>
                                            <i class="fas fa-calendar me-2"></i>
                                            <?php echo date('M d, Y', strtotime($transaction['date'])); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                        <td><?php echo htmlspecialchars($transaction['category']); ?></td>
                                        <td class="fw-bold <?php echo $transaction['type'] == 'income' ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo ($transaction['type'] == 'income' ? '+' : '-'); ?>
                                            PKR <?php echo number_format($transaction['amount'], 2); ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $transaction['type'] == 'income' ? 'badge-income' : 'badge-expense'; ?>">
                                                <?php echo ucfirst($transaction['type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if($transaction['status'] == 'Reversed'): ?>
                                                <span class="badge bg-warning">Reversed</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="edit_transaction.php?id=<?php echo $transaction['id']; ?>" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="create_contra_entry.php?id=<?php echo $transaction['id']; ?>" 
                                                   class="btn btn-sm btn-warning"
                                                   onclick="return confirm('Are you sure you want to create a contra (reversing) entry for this transaction?');">
                                                    <i class="fas fa-exchange-alt"></i> Contra Entry
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h4>No Transactions Found</h4>
                        <p>Try adjusting your filters or add a new transaction.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
