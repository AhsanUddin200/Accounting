<?php
// financial_reports.php
require 'session.php';
require 'db.php';

// Initialize variables
$income = 0;
$expenses = 0;
$balance = 0;
$start_date = '';
$end_date = '';
$error = "";

// Handle Filters
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    // User Report Filters
    if (isset($_GET['export_user_report']) || isset($_GET['admin_export'])) {
        // Export is handled in export_report.php
    } else {
        if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
            $start_date = $_GET['start_date'];
            $end_date = $_GET['end_date'];

            // Validate dates
            if ($start_date > $end_date) {
                $error = "Start date cannot be greater than end date.";
            } else {
                // Calculate Total Income
                $stmt = $conn->prepare("SELECT SUM(amount) AS total_income FROM transactions WHERE user_id = ? AND type = 'income' AND date BETWEEN ? AND ?");
                $stmt->bind_param("iss", $_SESSION['user_id'], $start_date, $end_date);
                $stmt->execute();
                $stmt->bind_result($income_result);
                $stmt->fetch();
                $income = $income_result ? $income_result : 0;
                $stmt->close();

                // Calculate Total Expenses
                $stmt = $conn->prepare("SELECT SUM(amount) AS total_expenses FROM transactions WHERE user_id = ? AND type = 'expense' AND date BETWEEN ? AND ?");
                $stmt->bind_param("iss", $_SESSION['user_id'], $start_date, $end_date);
                $stmt->execute();
                $stmt->bind_result($expenses_result);
                $stmt->fetch();
                $expenses = $expenses_result ? $expenses_result : 0;
                $stmt->close();

                // Calculate Balance
                $balance = $income - $expenses;
            }
        } else {
            // If no date filter, calculate for all time
            // Total Income
            $stmt = $conn->prepare("SELECT SUM(amount) AS total_income FROM transactions WHERE user_id = ? AND type = 'income'");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $stmt->bind_result($income_result);
            $stmt->fetch();
            $income = $income_result ? $income_result : 0;
            $stmt->close();

            // Total Expenses
            $stmt = $conn->prepare("SELECT SUM(amount) AS total_expenses FROM transactions WHERE user_id = ? AND type = 'expense'");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $stmt->bind_result($expenses_result);
            $stmt->fetch();
            $expenses = $expenses_result ? $expenses_result : 0;
            $stmt->close();

            // Calculate Balance
            $balance = $income - $expenses;
        }
    }
}

// If Admin, can view all users' reports
if ($_SESSION['role'] == 'admin') {
    // Admin can filter by user as well
    $users = [];
    $result = $conn->query("SELECT id, username FROM users ORDER BY username ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    } else {
        die("Error fetching users: " . $conn->error);
    }

    // Initialize variables for admin report
    $admin_income = 0;
    $admin_expenses = 0;
    $admin_balance = 0;
    $selected_user = '';

    if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['admin_export'])) {
        $selected_user = $_GET['user_id'];
        $start_date_admin = $_GET['start_date_admin'];
        $end_date_admin = $_GET['end_date_admin'];

        // Validate dates
        if ($start_date_admin > $end_date_admin) {
            $error = "Start date cannot be greater than end date.";
        } else {
            // Calculate Total Income for Admin
            $stmt = $conn->prepare("SELECT SUM(amount) AS total_income FROM transactions WHERE user_id = ? AND type = 'income' AND date BETWEEN ? AND ?");
            $stmt->bind_param("iss", $selected_user, $start_date_admin, $end_date_admin);
            $stmt->execute();
            $stmt->bind_result($admin_income_result);
            $stmt->fetch();
            $admin_income = $admin_income_result ? $admin_income_result : 0;
            $stmt->close();

            // Calculate Total Expenses for Admin
            $stmt = $conn->prepare("SELECT SUM(amount) AS total_expenses FROM transactions WHERE user_id = ? AND type = 'expense' AND date BETWEEN ? AND ?");
            $stmt->bind_param("iss", $selected_user, $start_date_admin, $end_date_admin);
            $stmt->execute();
            $stmt->bind_result($admin_expenses_result);
            $stmt->fetch();
            $admin_expenses = $admin_expenses_result ? $admin_expenses_result : 0;
            $stmt->close();

            // Calculate Balance
            $admin_balance = $admin_income - $admin_expenses;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Financial Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

        .nav-link i {
            width: 20px;
            text-align: center;
            margin-right: 8px;
        }

        .stats-card {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            color: white;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        .stats-label {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.9;
        }

        .stats-value {
            font-size: 2rem;
            font-weight: 600;
            margin: 0.5rem 0;
        }

        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .report-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--primary-color);
            border: none;
        }

        .btn-primary:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .btn-export {
            background: var(--info-color);
            color: white;
            border: none;
        }

        .btn-export:hover {
            background: #3d7ec9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .form-control, .form-select {
            border-radius: 8px;
            padding: 0.8rem 1rem;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .section-title {
            color: var(--dark-color);
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary-color);
        }

        .table {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
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

        .table-hover tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.05);
        }

        .admin-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-top: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        @media (max-width: 768px) {
            .stats-card {
                margin-bottom: 1rem;
            }
            
            .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }
        }

        .financial-section {
            margin-bottom: 3rem;
        }
        .report-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .report-card:hover {
            transform: translateY(-5px);
        }
        .report-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #4361ee;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-chart-line me-2"></i>Financial Reports
            </a>
            <div class="ms-auto">
                <a href="<?php echo $_SESSION['role'] == 'admin' ? 'admin_dashboard.php' : 'user_dashboard.php'; ?>" 
                   class="nav-link">
                    <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- New Financial Reports Section -->
        <div class="financial-section">
            <h2 class="mb-4">Financial Reports</h2>
            <div class="row">
                <!-- Ledgers -->
                <div class="col-md-6 mb-4">
                    <div class="report-card text-center">
                        <div class="report-icon">
                            <i class="fas fa-book"></i>
                        </div>
                        <h4>Ledgers</h4>
                        <p>View and manage detailed ledger entries for all accounts</p>
                        <a href="view_ledgers.php" class="btn btn-primary">View Ledgers</a>
                    </div>
                </div>

                <!-- Trial Balance -->
                <div class="col-md-6 mb-4">
                    <div class="report-card text-center">
                        <div class="report-icon">
                            <i class="fas fa-balance-scale"></i>
                        </div>
                        <h4>Trial Balance</h4>
                        <p>View the trial balance report showing debits and credits</p>
                        <a href="trial_balance.php" class="btn btn-primary">View Trial Balance</a>
                    </div>
                </div>

                <!-- Income Statement -->
                <div class="col-md-6 mb-4">
                    <div class="report-card text-center">
                        <div class="report-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h4>Income Statement</h4>
                        <p>View profit and loss statement for selected periods</p>
                        <a href="income_statement.php" class="btn btn-primary">View Income Statement</a>
                    </div>
                </div>

                <!-- Balance Sheet -->
                <div class="col-md-6 mb-4">
                    <div class="report-card text-center">
                        <div class="report-icon">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <h4>Balance Sheet</h4>
                        <p>View assets, liabilities, and equity statement</p>
                        <a href="balance_sheet.php" class="btn btn-primary">View Balance Sheet</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Display Success or Error Messages -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Financial Overview -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-label">Total Income</div>
                    <div class="stats-value income-text">
                        $<?php echo number_format($income, 2); ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-label">Total Expenses</div>
                    <div class="stats-value expense-text">
                        $<?php echo number_format($expenses, 2); ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-label">Net Balance</div>
                    <div class="stats-value balance-text">
                        $<?php echo number_format($balance, 2); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" name="start_date" 
                           value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" name="end_date" 
                           value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                    <a href="financial_reports.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Export Section -->
        <div class="report-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0">Export Reports</h4>
                <form method="POST" action="export_report.php">
                    <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                    <button type="submit" name="export_user_report" class="btn btn-export">
                        <i class="bi bi-download"></i> Export as CSV
                    </button>
                </form>
            </div>
            <!-- Chart for User Reports -->
            <canvas id="userReportChart" height="100"></canvas>
        </div>

        <!-- Admin Section -->
        <?php if ($_SESSION['role'] == 'admin'): ?>
        <div class="admin-section">
            <h4 class="mb-4">Admin Reports</h4>
            <form method="GET" class="row g-3">
                <input type="hidden" name="admin_export" value="1">
                
                <div class="col-md-3">
                    <label class="form-label">Select User</label>
                    <select class="form-select" name="user_id" required>
                        <option value="">Choose user...</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" 
                                <?php echo (isset($_GET['user_id']) && $_GET['user_id'] == $user['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" name="start_date_admin" 
                           value="<?php echo isset($_GET['start_date_admin']) ? htmlspecialchars($_GET['start_date_admin']) : ''; ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" name="end_date_admin" 
                           value="<?php echo isset($_GET['end_date_admin']) ? htmlspecialchars($_GET['end_date_admin']) : ''; ?>">
                </div>
                
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Generate Report</button>
                </div>
            </form>

            <?php if (isset($_GET['admin_export'])): ?>
                <div class="report-card mt-4">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="stats-label">Total Income</div>
                            <div class="stats-value income-text">
                                $<?php echo number_format($admin_income, 2); ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stats-label">Total Expenses</div>
                            <div class="stats-value expense-text">
                                $<?php echo number_format($admin_expenses, 2); ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stats-label">Net Balance</div>
                            <div class="stats-value balance-text">
                                $<?php echo number_format($admin_balance, 2); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Export Admin Report</h5>
                        <form method="POST" action="export_report.php">
                            <input type="hidden" name="admin_export" value="1">
                            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($selected_user); ?>">
                            <input type="hidden" name="start_date_admin" value="<?php echo htmlspecialchars($start_date_admin); ?>">
                            <input type="hidden" name="end_date_admin" value="<?php echo htmlspecialchars($end_date_admin); ?>">
                            <button type="submit" name="export_admin_report" class="btn btn-export">
                                <i class="bi bi-download"></i> Export as CSV
                            </button>
                        </form>
                    </div>
                    <!-- Chart for Admin Reports -->
                    <canvas id="adminReportChart" height="100"></canvas>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Charts Section -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="report-card">
                    <h5 class="mb-4">Income vs Expenses Over Time</h5>
                    <canvas id="incomeExpenseChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart.js Scripts -->
    <script>
        // User Report Chart
        <?php if (!empty($start_date) && !empty($end_date)): ?>
            // Fetch data for the chart (e.g., monthly breakdown)
            // For simplicity, we'll use total income and expenses
            const userReportCtx = document.getElementById('userReportChart').getContext('2d');
            const userReportChart = new Chart(userReportCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Income', 'Expenses'],
                    datasets: [{
                        data: [<?php echo $income; ?>, <?php echo $expenses; ?>],
                        backgroundColor: ['#27AE60', '#C0392B'],
                        hoverBackgroundColor: ['#2ECC71', '#E74C3C']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        title: {
                            display: false,
                        }
                    }
                },
            });
        <?php endif; ?>

        // Admin Report Chart
        <?php if ($_SESSION['role'] == 'admin' && isset($_GET['admin_export'])): ?>
            const adminReportCtx = document.getElementById('adminReportChart').getContext('2d');
            const adminReportChart = new Chart(adminReportCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Income', 'Expenses'],
                    datasets: [{
                        data: [<?php echo $admin_income; ?>, <?php echo $admin_expenses; ?>],
                        backgroundColor: ['#27AE60', '#C0392B'],
                        hoverBackgroundColor: ['#2ECC71', '#E74C3C']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        title: {
                            display: false,
                        }
                    }
                },
            });
        <?php endif; ?>

        // Overall Income vs Expenses Chart
        <?php
        // Fetch data for the overall chart
        // Here we can create monthly data for the past 6 months
        $months = [];
        $income_data = [];
        $expense_data = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $months[] = date('M Y', strtotime($month));

            // Income
            $stmt = $conn->prepare("SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'income' AND DATE_FORMAT(date, '%Y-%m') = ?");
            $stmt->bind_param("is", $_SESSION['user_id'], $month);
            $stmt->execute();
            $stmt->bind_result($monthly_income);
            $stmt->fetch();
            $income_data[] = $monthly_income ? $monthly_income : 0;
            $stmt->close();

            // Expenses
            $stmt = $conn->prepare("SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'expense' AND DATE_FORMAT(date, '%Y-%m') = ?");
            $stmt->bind_param("is", $_SESSION['user_id'], $month);
            $stmt->execute();
            $stmt->bind_result($monthly_expenses);
            $stmt->fetch();
            $expense_data[] = $monthly_expenses ? $monthly_expenses : 0;
            $stmt->close();
        }
        ?>

        const incomeExpenseCtx = document.getElementById('incomeExpenseChart').getContext('2d');
        const incomeExpenseChart = new Chart(incomeExpenseCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($months); ?>,
                datasets: [
                    {
                        label: 'Income',
                        data: <?php echo json_encode($income_data); ?>,
                        backgroundColor: '#27AE60',
                    },
                    {
                        label: 'Expenses',
                        data: <?php echo json_encode($expense_data); ?>,
                        backgroundColor: '#C0392B',
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'Income vs Expenses Over the Past 6 Months'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value, index, values) {
                                return '$' + value;
                            }
                        }
                    }
                }
            },
        });
    </script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
