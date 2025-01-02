<?php
// admin_dashboard.php

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Security check
if ($_SESSION['role'] != 'admin') {
    header("Location: user_dashboard.php");
    exit();
}

// Set current month dates for the dashboard
$period_start = date('Y-m-01'); // First day of current month
$period_end = date('Y-m-t');    // Last day of current month

// Calculate totals for current month only
$totals_query = "SELECT 
    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
    SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expenses
FROM transactions 
WHERE date BETWEEN ? AND ?";

$stmt = $conn->prepare($totals_query);
$stmt->bind_param("ss", $period_start, $period_end);
$stmt->execute();
$result = $stmt->get_result();
$totals = $result->fetch_assoc();

$total_income = $totals['total_income'] ?? 0;
$total_expenses = $totals['total_expenses'] ?? 0;
$net_balance = $total_income - $total_expenses;

// Fetch total number of users
$user_count_result = $conn->query("SELECT COUNT(*) as total_users FROM users");
$user_count = $user_count_result->fetch_assoc()['total_users'] ?? 0;

// Fetch recent audit logs
$stmt = $conn->prepare("SELECT audit_logs.*, users.username FROM audit_logs 
                        LEFT JOIN users ON audit_logs.user_id = users.id 
                        ORDER BY audit_logs.timestamp DESC LIMIT 5");
$stmt->execute();
$result = $stmt->get_result();
$recent_logs = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch statistics
$transaction_count_result = $conn->query("SELECT COUNT(*) as total_transactions FROM transactions");
$total_transactions = $transaction_count_result->fetch_assoc()['total_transactions'] ?? 0;

$category_count_result = $conn->query("SELECT COUNT(*) as total_categories FROM categories");
$total_categories = $category_count_result->fetch_assoc()['total_categories'] ?? 0;

// Fetch recent income entries
$recent_income_stmt = $conn->prepare("SELECT description, amount, date FROM transactions 
                                    WHERE type = 'income' ORDER BY date DESC LIMIT 5");
$recent_income_stmt->execute();
$recent_incomes = $recent_income_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recent_income_stmt->close();

// Log dashboard view
log_action($conn, $_SESSION['user_id'], 'Viewed Admin Dashboard', 'Admin accessed the dashboard.');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Financial Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4bb543;
            --info-color: #4895ef;
            --warning-color: #f72585;
            --danger-color: #ef233c;
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
        }

        .navbar-brand {
            font-weight: 600;
            color: white !important;
        }

        .nav-link {
            color: rgba(255,255,255,0.9) !important;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            color: white !important;
            transform: translateY(-1px);
        }

        .dashboard-card {
            border-radius: 15px;
            border: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            background: white;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
        }

        .stat-card {
            padding: 1.5rem;
            border-radius: 15px;
            color: white;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 1rem;
            opacity: 0.9;
        }

        .quick-action-btn {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .quick-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
        }

        .table thead th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 500;
            border: none;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.05);
        }

        .section-title {
            color: var(--dark-color);
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary-color);
        }

        .progress {
            height: 10px;
            border-radius: 5px;
        }

        .card-footer {
            background: transparent;
            border-top: 1px solid rgba(0,0,0,0.05);
        }

        @media (max-width: 768px) {
            .stat-card {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-chart-line me-2"></i>Financial Management System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="manage_users.php">
                            <i class="fas fa-users me-1"></i> Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_transactions.php">
                            <i class="fas fa-exchange-alt me-1"></i> Transactions
                        </a>
                        
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_categories.php">
                            <i class="fas fa-tags me-1"></i> Categories
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="financial_reports.php">
                            <i class="fas fa-file-alt me-1"></i> Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="add_transaction.php">
                            <i class="fas fa-file-alt me-1"></i> Add Income
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_salaries.php">
                            <i class="fas fa-money-bill-wave me-1"></i> Salaries
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="upload_attendance.php">
                            <i class="fas fa-clock me-1"></i> Upload Attendance
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i> Logout
                            (<?php echo htmlspecialchars($_SESSION['username']); ?>)
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-4">
        <!-- Welcome Section -->
        <div class="row mb-4">
            <div class="col">
                <h2 class="section-title">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard Overview
                </h2>
            </div>
        </div>

        <!-- Stats Cards with Period -->
        <div class="period-header mb-3">
            <h4>
                <i class="fas fa-calendar me-2"></i>
                Period: <?php echo date('d M Y', strtotime($period_start)) . " - " . date('d M Y', strtotime($period_end)); ?>
            </h4>
        </div>

        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="dashboard-card stat-card" 
                     onclick="window.location='transaction_details.php?type=income&start_date=<?php echo $period_start; ?>&end_date=<?php echo $period_end; ?>'"
                     style="cursor: pointer; background: linear-gradient(135deg, var(--success-color), #2ecc71)">
                    <div class="stat-icon">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                    <div class="stat-value">PKR <?php echo number_format($total_income, 2); ?></div>
                    <div class="stat-label">Total Income</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
            <div class="dashboard-card stat-card" 
             onclick="window.location='transaction_details.php?type=expense&start_date=<?php echo $period_start; ?>&end_date=<?php echo $period_end; ?>'"
             style="cursor: pointer; background: linear-gradient(135deg, var(--danger-color), #e74c3c)">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-value">PKR <?php echo number_format($total_expenses, 2); ?></div>
                    <div class="stat-label">Total Expenses</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="dashboard-card stat-card" style="background: linear-gradient(135deg, var(--info-color), #3498db)">
                    <div class="stat-icon">
                        <i class="fas fa-balance-scale"></i>
                    </div>
                    <div class="stat-value">PKR <?php echo number_format($net_balance, 2); ?></div>
                    <div class="scounttat-label">Net Balance</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col">
                <h3 class="section-title">
                    <i class="fas fa-bolt me-2"></i>Quick Actions
                </h3>
                <div class="d-flex gap-3 flex-wrap">
                    <a href="manage_salaries.php" class="quick-action-btn btn btn-primary">
                        <i class="fas fa-money-check-alt me-2"></i>Manage Salaries
                    </a>
                    <a href="process_salaries.php" class="quick-action-btn btn btn-success">
                        <i class="fas fa-tasks me-2"></i>Process Salaries
                    </a>
                    <a href="upload_attendance.php" class="quick-action-btn btn btn-secondary">
                        <i class="fas fa-clock me-2"></i>Upload Attendance
                    </a>
                    <a href="view_audit_logs.php" class="quick-action-btn btn btn-info">
                        <i class="fas fa-history me-2"></i>View Audit Logs
                    </a>
                    <a href="financial_reports.php" class="quick-action-btn btn btn-warning">
                        <i class="fas fa-chart-bar me-2"></i>Financial Reports
                    </a>
                    <a href="accounting.php" class="quick-action-btn btn" style="background-color: #0066ff; color: white;">
                        <i class="fas fa-book-open me-2"></i>Accounting
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Activities and Income -->
        <div class="row">
            <!-- Recent Activities -->
            <div class="col-md-7 mb-4">
                <h3 class="section-title">
                    <i class="fas fa-clock me-2"></i>Recent Activities
                </h3>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_logs as $log): ?>
                            <tr>
                                <td>
                                    <i class="fas fa-user-circle me-2"></i>
                                    <?php echo htmlspecialchars($log['username'] ?? 'System'); ?>
                                </td>
                                <td><?php echo htmlspecialchars($log['action']); ?></td>
                                <td><?php echo htmlspecialchars($log['details']); ?></td>
                                <td><?php echo htmlspecialchars(date('M d, H:i', strtotime($log['timestamp']))); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Income -->
            <div class="col-md-5 mb-4">
                <h3 class="section-title">
                    <i class="fas fa-money-bill-wave me-2"></i>Recent Income
                </h3>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_incomes as $income): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($income['description']); ?></td>
                                <td>PKR <?php echo number_format($income['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars(date('M d, Y', strtotime($income['date']))); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Additional Statistics -->
        <div class="row mb-4">
            <div class="col-md-6 mb-4">
                <div class="dashboard-card">
                    <div class="card-body">
                        <h4 class="card-title">
                            <i class="fas fa-chart-pie me-2"></i>Transaction Statistics
                        </h4>
                        <div class="mt-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total Transactions</span>
                                <span class="badge bg-primary"><?php echo number_format($total_transactions); ?></span>
                            </div>
                            <div class="progress mb-4">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: 75%"></div>
                            </div>
                            
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total Categories</span>
                                <span class="badge bg-success"><?php echo number_format($total_categories); ?></span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-success" role="progressbar" style="width: 60%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-4">
                <div class="dashboard-card">
                    <div class="card-body">
                        <h4 class="card-title">
                            <i class="fas fa-dollar-sign me-2"></i>Financial Overview
                        </h4>
                        <div class="mt-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Income to Expense Ratio</span>
                                <span class="badge bg-info">
                                    <?php 
                                    $ratio = $total_expenses > 0 ? ($total_income / $total_expenses) : 0;
                                    echo number_format($ratio, 2);
                                    ?>
                                </span>
                            </div>
                            <div class="progress mb-4">
                                <div class="progress-bar bg-info" role="progressbar" 
                                     style="width: <?php echo min(100, $ratio * 100); ?>%"></div>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span>Budget Utilization</span>
                                <span class="badge bg-warning">
                                    <?php 
                                    $utilization = $total_income > 0 ? ($total_expenses / $total_income * 100) : 0;
                                    echo number_format($utilization, 1) . '%';
                                    ?>
                                </span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-warning" role="progressbar" 
                                     style="width: <?php echo min(100, $utilization); ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="mt-5 mb-4 text-center text-muted">
            <div class="container">
                <p class="mb-0">© <?php echo date('Y'); ?> Financial Management System. All rights reserved.</p>
                <small>Last login: <?php echo date('M d, Y H:i:s'); ?></small>
            </div>
        </footer>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // Add loading animation
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.dashboard-card').forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100);
            });
        });

        // Add tooltip initialization
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    </script>
</body>
</html>