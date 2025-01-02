<?php
require_once 'session.php';
require_once 'db.php';
require_once 'functions.php';

// Calculate totals from all transactions
$totals_query = "SELECT 
    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
    SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense
    FROM transactions";
$totals_result = $conn->query($totals_query);
$totals = $totals_result->fetch_assoc();

$total_income = $totals['total_income'] ?? 0;
$total_expense = $totals['total_expense'] ?? 0;
$net_balance = $total_income - $total_expense;

// Fetch ledger entries with transaction details
$query = "SELECT 
    l.*,
    t.type as transaction_type,
    t.amount as transaction_amount,
    t.date as transaction_date,
    t.description as transaction_description,
    ah.name as head_name,
    ac.name as category_name
    FROM ledgers l
    LEFT JOIN transactions t ON l.transaction_id = t.id
    LEFT JOIN accounting_heads ah ON t.head_id = ah.id
    LEFT JOIN account_categories ac ON t.category_id = ac.id
    ORDER BY t.date DESC, l.id DESC";

$ledgers = $conn->query($query);

if (!$ledgers) {
    die("Error fetching ledger entries: " . $conn->error);
}

// Rest of your existing HTML code remains the same until the table body...

// Update the table body section with:
?>

<!DOCTYPE html>
<html>
<head>
    <title>General Ledger | Accounting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0061f2;
            --secondary-color: #6900f2;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Navbar Styles */
        .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            padding: 1rem 0;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(33, 40, 50, 0.15);
        }

        .navbar-brand {
            font-weight: 600;
            font-size: 1.5rem;
            color: white !important;
        }

        .navbar .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
        }

        .navbar .nav-link:hover {
            color: white !important;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 0.25rem;
        }

        .navbar .nav-link i {
            margin-right: 0.5rem;
        }

        /* Content Styles */
        .content-header {
            background: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(33, 40, 50, 0.15);
        }

        .stats-card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(33, 40, 50, 0.15);
            margin-bottom: 1.5rem;
            padding: 1.5rem;
            transition: transform 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        .stats-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .debit-icon { background: #e8f5e9; color: #2e7d32; }
        .credit-icon { background: #fbe9e7; color: #d84315; }
        .balance-icon { background: #e3f2fd; color: #1565c0; }

        .stats-value {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .stats-label {
            color: #69707a;
            font-size: 0.875rem;
        }

        /* Table Styles */
        .ledger-table {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(33, 40, 50, 0.15);
            overflow: hidden;
        }

        .table thead th {
            background: #f8f9fa;
            border: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.875rem;
            color: #69707a;
            padding: 1rem;
        }

        .table td {
            padding: 1rem;
            vertical-align: middle;
            border-color: #f8f9fa;
        }

        .account-badge {
            padding: 0.5rem 1rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .account-badge.asset { background: #e8f5e9; color: #2e7d32; }
        .account-badge.liability { background: #fbe9e7; color: #d84315; }
        .account-badge.income { background: #e3f2fd; color: #1565c0; }
        .account-badge.expense { background: #fff3e0; color: #ef6c00; }

        .amount-positive { color: #2e7d32; }
        .amount-negative { color: #d84315; }

        /* Print Styles */
        @media print {
            .navbar, .btn-print {
                display: none;
            }
            .content-header {
                margin-top: 0;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-calculator me-2"></i>
                Accounting System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="admin_dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="transactions.php">
                            <i class="fas fa-exchange-alt"></i>Transactions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="view_ledgers.php">
                            <i class="fas fa-book"></i>Ledgers
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar"></i>Reports
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i>
                            <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-id-card me-2"></i>Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">General Ledger</h1>
                    <p class="text-muted mb-0">View and manage your financial records</p>
                </div>
                <button class="btn btn-primary btn-print" onclick="window.print()">
                    <i class="fas fa-print me-2"></i>Print Ledger
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-fluid">
        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-icon debit-icon">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                    <div class="stats-value"><?php echo formatCurrency($total_income); ?></div>
                    <div class="stats-label">Total Income</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-icon credit-icon">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                    <div class="stats-value"><?php echo formatCurrency($total_expense); ?></div>
                    <div class="stats-label">Total Expense</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-icon balance-icon">
                        <i class="fas fa-balance-scale"></i>
                    </div>
                    <div class="stats-value"><?php echo formatCurrency($net_balance); ?></div>
                    <div class="stats-label">Net Balance</div>
                </div>
            </div>
        </div>

        <!-- Ledger Table -->
        <div class="ledger-table">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Account Type</th>
                            <th>Description</th>
                            <th class="text-end">Debit (PKR)</th>
                            <th class="text-end">Credit (PKR)</th>
                            <th class="text-end">Balance (PKR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $running_balance = 0;
                        while($entry = $ledgers->fetch_assoc()): 
                            // Update running balance
                            $running_balance += ($entry['debit'] - $entry['credit']);
                        ?>
                            <tr>
                                <td><?php echo date('d M Y H:i', strtotime($entry['transaction_date'])); ?></td>
                                <td>
                                    <span class="account-badge <?php echo strtolower($entry['account_type']); ?>">
                                        <?php echo ucfirst($entry['account_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($entry['head_name']); ?></strong><br>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($entry['category_name']); ?> - 
                                        <?php echo htmlspecialchars($entry['transaction_description']); ?>
                                    </small>
                                </td>
                                <td class="text-end">
                                    <?php if ($entry['debit'] > 0): ?>
                                        <span class="amount-positive">
                                            <?php echo formatCurrency($entry['debit']); ?>
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($entry['credit'] > 0): ?>
                                        <span class="amount-negative">
                                            <?php echo formatCurrency($entry['credit']); ?>
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-bold <?php echo $running_balance >= 0 ? 'amount-positive' : 'amount-negative'; ?>">
                                    <?php echo formatCurrency($running_balance); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr class="fw-bold">
                            <td colspan="3">Total Balance</td>
                            <td class="text-end"><?php echo formatCurrency($total_income); ?></td>
                            <td class="text-end"><?php echo formatCurrency($total_expense); ?></td>
                            <td class="text-end <?php echo $net_balance >= 0 ? 'amount-positive' : 'amount-negative'; ?>">
                                <?php echo formatCurrency($net_balance); ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add loading animation
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.stats-card, .ledger-table');
            elements.forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
            });

            setTimeout(() => {
                elements.forEach(el => {
                    el.style.transition = 'all 0.5s ease';
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                });
            }, 100);
        });
    </script>
</body>
</html>