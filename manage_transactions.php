<?php
// manage_transactions.php
require 'session.php';
require 'db.php';

// Check if the logged-in user is an admin
if ($_SESSION['role'] != 'admin') {
    header("Location: user_dashboard.php");
    exit();
}

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize variables
$success = "";
$error = "";

// Fetch categories for filter
$categories = [];
$cat_query = "SELECT id, name FROM categories ORDER BY name ASC";
$cat_result = $conn->query($cat_query);
if ($cat_result) {
    while ($row = $cat_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Build the query with filters
$where_clauses = [];
$params = [];
$types = "";

if (!empty($_GET['type'])) {
    $where_clauses[] = "transactions.type = ?";
    $params[] = $_GET['type'];
    $types .= "s";
}

if (!empty($_GET['category'])) {
    $where_clauses[] = "transactions.category_id = ?";
    $params[] = $_GET['category'];
    $types .= "i";
}

if (!empty($_GET['start_date'])) {
    $where_clauses[] = "transactions.date >= ?";
    $params[] = $_GET['start_date'];
    $types .= "s";
}

if (!empty($_GET['end_date'])) {
    $where_clauses[] = "transactions.date <= ?";
    $params[] = $_GET['end_date'];
    $types .= "s";
}

// Base query
$query = "SELECT transactions.id, users.username, transactions.amount, transactions.type, 
          categories.name AS category, transactions.date, transactions.description
          FROM transactions
          JOIN users ON transactions.user_id = users.id
          JOIN categories ON transactions.category_id = categories.id";

// Add where clauses if filters are applied
if (!empty($where_clauses)) {
    $query .= " WHERE " . implode(" AND ", $where_clauses);
}

$query .= " ORDER BY transactions.date DESC";

// Prepare and execute the query
$transactions = [];
if (!empty($params)) {
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }
        $stmt->close();
    }
} else {
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }
    }
}

// Calculate totals for filtered results
$total_income = 0;
$total_expenses = 0;
foreach ($transactions as $transaction) {
    if ($transaction['type'] == 'income') {
        $total_income += $transaction['amount'];
    } else {
        $total_expenses += $transaction['amount'];
    }
}

// Add summary section after the filter

?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Transactions</title>
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
            padding: 1rem 0;
        }

        .navbar-brand {
            font-weight: 600;
            color: white !important;
        }

        .nav-link {
            color: rgba(255,255,255,0.9) !important;
            transition: all 0.3s ease;
            padding: 0.8rem 1.2rem;
            border-radius: 8px;
            margin: 0 0.2rem;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white !important;
            transform: translateY(-1px);
        }

        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            border: none;
        }

        .card-header {
            background: var(--primary-color);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1rem 1.5rem;
            font-weight: 600;
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

        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
        }

        .badge-income {
            background-color: var(--success-color);
            padding: 0.5rem 1rem;
            border-radius: 6px;
        }

        .badge-expense {
            background-color: var(--danger-color);
            padding: 0.5rem 1rem;
            border-radius: 6px;
        }

        .btn-action {
            padding: 0.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-action:hover {
            transform: translateY(-2px);
        }

        .section-title {
            color: var(--dark-color);
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary-color);
        }

        .filter-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .filter-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            padding: 0.6rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .btn-search {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            height: 42px;
        }

        .btn-search:hover {
            background: var(--secondary-color);
            transform: translateY(-1px);
        }

        .btn-reset {
            background: #f8f9fa;
            color: var(--dark-color);
            border: 2px solid #e2e8f0;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            height: 42px;
        }

        .btn-reset:hover {
            background: #e2e8f0;
        }

        .btn-new {
            background: var(--success-color);
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-new:hover {
            background: #219a52;
            transform: translateY(-1px);
        }

        .form-label {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .summary-cards {
            margin-top: 1rem;
        }

        .summary-card {
            background-color: #fff;
            border-radius: 0.25rem;
            padding: 1rem;
            text-align: center;
        }

        .summary-card h6 {
            font-size: 0.9rem;
            color: #6c757d;
        }

        .summary-card h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-exchange-alt me-2"></i>Manage Transactions
            </a>
            <div class="ms-auto">
                <a href="admin_dashboard.php" class="nav-link">
                    <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="filter-header">
                <div class="filter-title">
                    <i class="fas fa-filter"></i>
                    Filter Transactions
                </div>
                <a href="add_transaction.php" class="btn btn-new">
                    <i class="fas fa-plus me-1"></i>
                    New Transaction
                </a>
            </div>
            
            <form method="GET" class="filter-form">
                <div>
                    <label class="form-label">Transaction Type</label>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="income" <?php echo (isset($_GET['type']) && $_GET['type'] == 'income') ? 'selected' : ''; ?>>Income</option>
                        <option value="expense" <?php echo (isset($_GET['type']) && $_GET['type'] == 'expense') ? 'selected' : ''; ?>>Expense</option>
                    </select>
                </div>

                <div>
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

                <div>
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" 
                           value="<?php echo isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : ''; ?>">
                </div>

                <div>
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" 
                           value="<?php echo isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : ''; ?>">
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-search">
                        <i class="fas fa-search me-1"></i> Search
                    </button>
                    <a href="manage_transactions.php" class="btn btn-reset">
                        <i class="fas fa-redo me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-list me-2"></i>Transactions List</span>
                <a href="add_transaction.php" class="btn btn-light btn-sm">
                    <i class="fas fa-plus me-1"></i>Add New Transaction
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>User</th>
                                <th>Description</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Category</th>
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
                                <td>
                                    <i class="fas fa-user me-2"></i>
                                    <?php echo htmlspecialchars($transaction['username']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                <td>
                                    <span class="badge <?php echo $transaction['type'] == 'income' ? 'badge-income' : 'badge-expense'; ?>">
                                        <?php echo ucfirst($transaction['type']); ?>
                                    </span>
                                </td>
                                <td>PKR <?php echo number_format($transaction['amount'], 2); ?></td>

                                <td><?php echo htmlspecialchars($transaction['category']); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="edit_transaction.php?id=<?php echo $transaction['id']; ?>" 
                                           class="btn btn-primary btn-action me-2" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="create_contra_entry.php?id=<?php echo $transaction['id']; ?>" 
                                           class="btn btn-warning btn-action"
                                           onclick="return confirm('Are you sure you want to create a contra (reversing) entry for this transaction?');"
                                           title="Create Contra Entry">
                                            <i class="fas fa-exchange-alt"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
                    