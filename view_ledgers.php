<?php
require_once 'session.php';
require_once 'db.php';
require_once 'functions.php';

// Calculate totals from transactions
$totals_query = "SELECT 
    SUM(debit) as total_debit,
    SUM(credit) as total_credit,
    (SUM(debit) - SUM(credit)) as net_balance
    FROM ledgers";
$totals_result = $conn->query($totals_query);
$totals = $totals_result->fetch_assoc();

$total_debit = $totals['total_debit'] ?? 0;
$total_credit = $totals['total_credit'] ?? 0;
$net_balance = $totals['net_balance'] ?? 0;

// Main query for ledger entries
$query = "SELECT 
    l.ledger_code,
    l.date,
    ah.name as account_type,
    l.description,
    l.debit,
    l.credit,
    l.balance,
    t.type as transaction_type
    FROM ledgers l
    LEFT JOIN transactions t ON l.transaction_id = t.id
    LEFT JOIN accounting_heads ah ON t.head_id = ah.id
    WHERE 1=1";

// Add filters if provided
if (!empty($_GET['from_date'])) {
    $query .= " AND l.date >= '" . $conn->real_escape_string($_GET['from_date']) . "'";
}
if (!empty($_GET['to_date'])) {
    $query .= " AND l.date <= '" . $conn->real_escape_string($_GET['to_date']) . "'";
}
if (!empty($_GET['from_code'])) {
    $query .= " AND l.ledger_code >= '" . $conn->real_escape_string($_GET['from_code']) . "'";
}
if (!empty($_GET['to_code'])) {
    $query .= " AND l.ledger_code <= '" . $conn->real_escape_string($_GET['to_code']) . "'";
}
if (!empty($_GET['account_type'])) {
    $query .= " AND ah.name = '" . $conn->real_escape_string($_GET['account_type']) . "'";
}
if (!empty($_GET['search_term'])) {
    $search = $conn->real_escape_string($_GET['search_term']);
    $query .= " AND (
        l.ledger_code LIKE '%$search%' OR 
        l.description LIKE '%$search%' OR 
        ah.name LIKE '%$search%'
    )";
}

$query .= " ORDER BY l.date DESC, l.id DESC";
$result = $conn->query($query);

if (!$result) {
    die("Error fetching ledger entries: " . $conn->error);
}
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
    <nav class="navbar navbar-expand-lg mb-4" style="background: linear-gradient(90deg, #4256e4 0%, #5e3fd3 100%);">
        <div class="container-fluid">
            <a class="navbar-brand text-white" href="index.php">
                <i class="fas fa-chart-line me-2"></i>Financial Management System
            </a>
            
            <a href="admin_dashboard.php" class="btn btn-light rounded-pill px-4">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
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

    <div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Search Filters</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <!-- Date Range -->
            <div class="col-md-3">
                <label class="form-label">From Date</label>
                <input type="date" name="from_date" class="form-control" 
                    value="<?php echo $_GET['from_date'] ?? ''; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">To Date</label>
                <input type="date" name="to_date" class="form-control" 
                    value="<?php echo $_GET['to_date'] ?? ''; ?>">
            </div>

            <!-- Ledger Code Range -->
            <div class="col-md-3">
                <label class="form-label">From Ledger Code</label>
                <input type="text" name="from_code" class="form-control" 
                    value="<?php echo $_GET['from_code'] ?? ''; ?>"
                    placeholder="e.g., IN0001">
            </div>
            <div class="col-md-3">
                <label class="form-label">To Ledger Code</label>
                <input type="text" name="to_code" class="form-control" 
                    value="<?php echo $_GET['to_code'] ?? ''; ?>"
                    placeholder="e.g., IN9999">
            </div>

            <!-- Add Account Type Filter here -->
            <div class="col-md-3">
                <label class="form-label">Account Type</label>
                <select name="account_type" class="form-select">
                    <option value="">All Account Types</option>
                    <option value="Assets" <?php echo ($_GET['account_type'] ?? '') === 'Assets' ? 'selected' : ''; ?>>Assets</option>
                    <option value="Liabilities" <?php echo ($_GET['account_type'] ?? '') === 'Liabilities' ? 'selected' : ''; ?>>Liabilities</option>
                    <option value="Equities" <?php echo ($_GET['account_type'] ?? '') === 'Equities' ? 'selected' : ''; ?>>Equities</option>
                    <option value="Income" <?php echo ($_GET['account_type'] ?? '') === 'Income' ? 'selected' : ''; ?>>Income</option>
                    <option value="Expenses" <?php echo ($_GET['account_type'] ?? '') === 'Expenses' ? 'selected' : ''; ?>>Expenses</option>
                </select>
            </div>
<!-- Add this to your search form -->
<div class="col-md-3">
    <label class="form-label">Document Number</label>
    <input type="text" name="search_term" class="form-control" 
        value="<?php echo $_GET['search_term'] ?? ''; ?>"
        placeholder="Search in any column...">
</div>

<?php
// Add this to your query conditions
if (!empty($_GET['search_term'])) {
    $search = $conn->real_escape_string($_GET['search_term']);
    $query .= " AND (
        l.ledger_code LIKE '%$search%' OR 
        l.description LIKE '%$search%' OR 
        ah.name LIKE '%$search%' OR 
        l.document_number LIKE '%$search%'
    )";
}
?>
            <!-- Submit and Reset Buttons -->
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-2"></i>Search
                </button>
                <a href="view_ledgers.php" class="btn btn-secondary">
                    <i class="fas fa-undo me-2"></i>Reset
                </a>
            </div>
        </form>
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
                    <div class="stats-value"><?php echo formatCurrency($total_debit); ?></div>
                    <div class="stats-label">Total Debit</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-icon credit-icon">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                    <div class="stats-value"><?php echo formatCurrency($total_credit); ?></div>
                    <div class="stats-label">Total Credit</div>
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
                            <th>LEDGER CODE</th>
                            <th>DATE</th>
                            <th>ACCOUNT TYPE</th>
                            <th>DESCRIPTION</th>
                            <th class="text-end">DEBIT (PKR)</th>
                            <th class="text-end">CREDIT (PKR)</th>
                            <th class="text-end">BALANCE (PKR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['ledger_code']); ?></td>
                                <td><?php echo date('d M Y', strtotime($row['date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['account_type']); ?></td>
                                <td><?php echo htmlspecialchars($row['description']); ?></td>
                                <td class="text-end"><?php echo $row['debit'] > 0 ? formatCurrency($row['debit']) : '-'; ?></td>
                                <td class="text-end"><?php echo $row['credit'] > 0 ? formatCurrency($row['credit']) : '-'; ?></td>
                                <td class="text-end"><?php echo formatCurrency($row['balance']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr class="fw-bold">
                            <td colspan="4">TOTAL</td>
                            <td class="text-end"><?php echo formatCurrency($total_debit); ?></td>
                            <td class="text-end"><?php echo formatCurrency($total_credit); ?></td>
                            <td class="text-end"><?php echo formatCurrency($net_balance); ?></td>
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