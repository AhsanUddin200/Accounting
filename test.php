    <?php
    // सबसे पहले सेशन स्टार्ट करें
    session_start();

    // session.php और db.php इंक्लूड करें
    require_once 'session.php';
    require_once 'db.php';

    // चेक करें कि यूज़र ऐडमिन है या नहीं
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header("Location: login.php");
        exit();
    }

    // हाल ही के ट्रांज़ेक्शन्स लाने वाली क्वेरी
    $query = "
        SELECT 
            t.*,
            ah.name AS head_name,
            ac.name AS category_name,
            u.username
        FROM transactions t
        LEFT JOIN accounting_heads ah ON t.head_id = ah.id
        LEFT JOIN account_categories ac ON t.category_id = ac.id
        LEFT JOIN users u ON t.user_id = u.id
        ORDER BY t.date DESC
        LIMIT 10
    ";

    // क्वेरी रन करें और त्रुटि चेक करें
    $transactions = $conn->query($query);
    if (!$transactions) {
        die("Error fetching transactions: " . $conn->error);
    }

    // इनकम और एक्सपेंस का टोटल लाने वाली क्वेरी
    $totals_query = "
        SELECT 
            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) AS total_income,
            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) AS total_expense
        FROM transactions
    ";

    // क्वेरी रन करें और त्रुटि चेक करें
    $result = $conn->query($totals_query);
    if (!$result) {
        die("Error fetching totals: " . $conn->error);
    }

    // fetch_assoc() से डेटा प्राप्त करें
    $totals = $result->fetch_assoc();

    // सुरक्षा की दृष्टि से यदि null हो, तो 0 मानें
    $total_income  = !empty($totals['total_income'])  ? floatval($totals['total_income'])  : 0.0;
    $total_expense = !empty($totals['total_expense']) ? floatval($totals['total_expense']) : 0.0;
    $net_balance   = $total_income - $total_expense;
    ?>

    <!DOCTYPE html>
    <html>
    <head>
        <title>Accounting Management</title>
        <?php include 'includes/head.php'; ?>
        <style>
            .stats-card {
                border-left: 4px solid;
                margin-bottom: 20px;
            }
            .stats-card.income {
                border-left-color: #28a745;
            }
            .stats-card.expense {
                border-left-color: #dc3545;
            }
            .action-card {
                transition: transform 0.2s;
            }
            .action-card:hover {
                transform: translateY(-5px);
            }
        </style>
    </head>
    <body>
        <?php include 'includes/navbar.php'; ?>

        <div class="container-fluid">
            <div class="row">
                <?php include 'includes/sidebar.php'; ?>

                <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1><i class="fas fa-book-open me-2"></i>Accounting Management</h1>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card stats-card income">
                                <div class="card-body">
                                    <h5 class="card-title">Total Income</h5>
                                    <h3 class="text-success">
                                        $<?php echo number_format($total_income, 2); ?>
                                    </h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card stats-card expense">
                                <div class="card-body">
                                    <h5 class="card-title">Total Expenses</h5>
                                    <h3 class="text-danger">
                                        $<?php echo number_format($total_expense, 2); ?>
                                    </h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card stats-card">
                                <div class="card-body">
                                    <h5 class="card-title">Net Balance</h5>
                                    <h3><?php echo number_format($net_balance, 2); ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <a href="add_transaction.php" class="card action-card text-decoration-none">
                                <div class="card-body text-center">
                                    <i class="fas fa-plus-circle fa-2x mb-2 text-primary"></i>
                                    <h5 class="card-title">New Transaction</h5>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="view_transactions.php" class="card action-card text-decoration-none">
                                <div class="card-body text-center">
                                    <i class="fas fa-list fa-2x mb-2 text-info"></i>
                                    <h5 class="card-title">View Transactions</h5>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="manage_categories.php" class="card action-card text-decoration-none">
                                <div class="card-body text-center">
                                    <i class="fas fa-tags fa-2x mb-2 text-success"></i>
                                    <h5 class="card-title">Manage Categories</h5>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="reports.php" class="card action-card text-decoration-none">
                                <div class="card-body text-center">
                                    <i class="fas fa-chart-bar fa-2x mb-2 text-warning"></i>
                                    <h5 class="card-title">Reports</h5>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- Recent Transactions -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Recent Transactions</h5>
                            <a href="view_transactions.php" class="btn btn-primary btn-sm">View All</a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Category</th>
                                            <th>Amount</th>
                                            <th>Added By</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($transaction = $transactions->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($transaction['date'])); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo ($transaction['type'] == 'income') ? 'success' : 'danger'; ?>">
                                                        <?php echo ucfirst($transaction['type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($transaction['category_name']); ?></td>
                                                <td>PKR <?php echo number_format($transaction['amount'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($transaction['username']); ?></td>
                                                <td>
                                                    <a href="edit_transaction.php?id=<?php echo $transaction['id']; ?>" 
                                                    class="btn btn-sm btn-primary">
                                                    <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="view_transaction.php?id=<?php echo $transaction['id']; ?>" 
                                                    class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>

        <?php include 'includes/footer.php'; ?>
        <script>
            // Add any custom JavaScript here
            $(document).ready(function() {
                // Initialize tooltips
                $('[data-toggle="tooltip"]').tooltip();
            });
        </script>
    </body>
    </html>
