<?php
require_once 'session.php';
require_once 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    exit('Unauthorized access');
}

$type = $_GET['type'] ?? 'income';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Your existing query to fetch transactions
$query = "SELECT t.*, 
          ah.name as head_name, 
          ac.name as category_name, 
          u.username 
          FROM transactions t
          LEFT JOIN accounting_heads ah ON t.head_id = ah.id
          LEFT JOIN account_categories ac ON t.category_id = ac.id
          LEFT JOIN users u ON t.user_id = u.id
          WHERE t.type = ? 
          AND t.date BETWEEN ? AND ?
          ORDER BY t.date DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("sss", $type, $start_date, $end_date);
$stmt->execute();
$transactions = $stmt->get_result();
$total = 0;
?>

<!-- Just the table part -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            <?php echo ucfirst($type); ?> Details 
            (<?php echo date('d M Y', strtotime($start_date)); ?> - 
             <?php echo date('d M Y', strtotime($end_date)); ?>)
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover transaction-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Head</th>
                        <th>Category</th>
                        <th>Amount</th>
                        <th>Description</th>
                        <th>Added By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    while ($row = $transactions->fetch_assoc()): 
                        $total += $row['amount'];
                    ?>
                        <tr>
                            <td><?php echo date('d M Y', strtotime($row['date'])); ?></td>
                            <td><?php echo htmlspecialchars($row['head_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                            <td class="amount-cell">PKR <?php echo number_format($row['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($row['description']); ?></td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td>
                                <a href="edit_transaction.php?id=<?php echo $row['id']; ?>" 
                                   class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
                <tfoot>
                    <tr class="table-dark">
                        <td colspan="3" class="text-end"><strong>Total:</strong></td>
                        <td colspan="4" class="amount-cell">
                            <strong>PKR <?php echo number_format($total, 2); ?></strong>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>