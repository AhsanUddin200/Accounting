<?php
// user_dashboard.php
require 'session.php';
require 'db.php';

// Check if the user is not an admin
if ($_SESSION['role'] == 'admin') {
    header("Location: admin_dashboard.php");
    exit();
}

// Fetch user's total income
$income_result = $conn->prepare("SELECT SUM(amount) as total_income FROM transactions WHERE user_id = ? AND type = 'income'");
if (!$income_result) {
    die("Prepare failed (Total Income): (" . $conn->errno . ") " . $conn->error);
}
$income_result->bind_param("i", $_SESSION['user_id']);
if (!$income_result->execute()) {
    die("Execute failed (Total Income): (" . $income_result->errno . ") " . $income_result->error);
}
$income = $income_result->get_result()->fetch_assoc()['total_income'] ?? 0;
$income_result->close();

// Fetch user's total expenses
$expense_result = $conn->prepare("SELECT SUM(amount) as total_expenses FROM transactions WHERE user_id = ? AND type = 'expense'");
if (!$expense_result) {
    die("Prepare failed (Total Expenses): (" . $conn->errno . ") " . $conn->error);
}
$expense_result->bind_param("i", $_SESSION['user_id']);
if (!$expense_result->execute()) {
    die("Execute failed (Total Expenses): (" . $expense_result->errno . ") " . $expense_result->error);
}
$expenses = $expense_result->get_result()->fetch_assoc()['total_expenses'] ?? 0;
$expense_result->close();

// Calculate net balance
$net_balance = $income - $expenses;

// Fetch recent transactions (last 5)
$stmt = $conn->prepare("SELECT transactions.*, categories.name as category_name FROM transactions 
                        JOIN categories ON transactions.category_id = categories.id 
                        WHERE transactions.user_id = ? 
                        ORDER BY transactions.date DESC LIMIT 5");
if (!$stmt) {
    die("Prepare failed (Recent Transactions): (" . $conn->errno . ") " . $conn->error);
}
$stmt->bind_param("i", $_SESSION['user_id']);
if (!$stmt->execute()) {
    die("Execute failed (Recent Transactions): (" . $stmt->errno . ") " . $stmt->error);
}
$result = $stmt->get_result();
$recent_transactions = [];
while ($row = $result->fetch_assoc()) {
    $recent_transactions[] = $row;
}
$stmt->close();

// Fetch user's avatar
$stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
if (!$stmt) {
    die("Prepare failed (Fetch Avatar): (" . $conn->errno . ") " . $conn->error);
}
$stmt->bind_param("i", $_SESSION['user_id']);
if (!$stmt->execute()) {
    die("Execute failed (Fetch Avatar): (" . $stmt->errno . ") " . $stmt->error);
}
$stmt->bind_result($avatar_path);
$stmt->fetch();
$stmt->close();

// If avatar path is empty, set to default
if (empty($avatar_path)) {
    $avatar_path = 'uploads/avatars/default_avatar.png';
}

// Log dashboard view
log_action($conn, $_SESSION['user_id'], 'Viewed User Dashboard', 'User accessed the dashboard.');
?>
<!DOCTYPE html>
<html>
<head>
    <title>User Dashboard</title>
    <!-- Include Bootstrap CSS for styling (Optional but recommended) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card { margin: 15px 0; }
        .table-responsive { max-height: 400px; }
        .avatar-img {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 50%;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Accounting Software</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item">
                        <a class="nav-link active" href="user_dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view_transactions.php">View Transactions</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="add_transaction.php">Add Transaction</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="financial_reports.php">Financial Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="edit_profile.php">Edit Profile</a>
                    </li>
                  
                    <li class="nav-item d-flex align-items-center">
                        <img src="<?php echo htmlspecialchars($avatar_path); ?>" alt="Avatar" class="avatar-img me-2">
                        <span class="nav-link">Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</span>
                      
                </ul>
            </div>
        </div>
    </nav>

    <!-- Dashboard Content -->
    <div class="container mt-4">
        <h2>User Dashboard</h2>
        <div class="row">
            <!-- Income Card -->
            <div class="col-md-4">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <h5 class="card-title">Total Income</h5>
                        <p class="card-text" style="font-size: 2em;">$<?php echo number_format($income, 2); ?></p>
                    </div>
                </div>
            </div>
            <!-- Expenses Card -->
            <div class="col-md-4">
                <div class="card text-white bg-danger">
                    <div class="card-body">
                        <h5 class="card-title">Total Expenses</h5>
                        <p class="card-text" style="font-size: 2em;">$<?php echo number_format($expenses, 2); ?></p>
                    </div>
                </div>
            </div>
            <!-- Net Balance Card -->
            <div class="col-md-4">
                <div class="card text-white bg-dark">
                    <div class="card-body">
                        <h5 class="card-title">Net Balance</h5>
                        <p class="card-text" style="font-size: 2em;">$<?php echo number_format($net_balance, 2); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="mt-5">
            <h3>Recent Transactions</h3>
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Amount</th>
                            <th>Type</th>
                            <th>Category</th>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recent_transactions) > 0): ?>
                            <?php foreach ($recent_transactions as $transaction): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($transaction['id']); ?></td>
                                    <td>$<?php echo number_format($transaction['amount'], 2); ?></td>
                                    <td><?php echo ucfirst(htmlspecialchars($transaction['type'])); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['category_name']); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['date']); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                    <td>
                                        <a href="edit_transaction.php?id=<?php echo $transaction['id']; ?>" class="btn btn-sm btn-primary">Edit</a> | 
                                        <a href="view_transactions.php?delete=<?php echo $transaction['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this transaction?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No recent transactions found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <a href="view_transactions.php" class="btn btn-secondary">View All Transactions</a>
            <a href="add_transaction.php" class="btn btn-success">Add New Transaction</a>
        </div>
    </div>

    <!-- Include Bootstrap JS (Optional) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>