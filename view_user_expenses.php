<?php
require_once 'session.php';
require_once 'db.php';

// Check admin access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Fetch user expenses with user details
$query = "SELECT ut.*, u.username, ec.name as category_name 
          FROM user_transactions ut 
          LEFT JOIN users u ON ut.user_id = u.id
          LEFT JOIN expense_categories ec ON ut.category_id = ec.id
          ORDER BY ut.date DESC";
$expenses = $conn->query($query);
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Expenses</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container py-4">
        <h2>User Expenses</h2>
        
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>User</th>
                                <th>Category</th>
                                <th>Amount</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($expense = $expenses->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('Y-m-d', strtotime($expense['date'])); ?></td>
                                <td><?php echo htmlspecialchars($expense['username']); ?></td>
                                <td><?php echo htmlspecialchars($expense['category_name']); ?></td>
                                <td>PKR <?php echo number_format($expense['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($expense['description']); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $expense['status'] == 'approved' ? 'success' : 
                                            ($expense['status'] == 'rejected' ? 'danger' : 'warning'); 
                                    ?>">
                                        <?php echo ucfirst($expense['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if($expense['status'] == 'pending'): ?>
                                    <a href="approve_expense.php?id=<?php echo $expense['id']; ?>" 
                                       class="btn btn-sm btn-success">Approve</a>
                                    <a href="reject_expense.php?id=<?php echo $expense['id']; ?>" 
                                       class="btn btn-sm btn-danger">Reject</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 