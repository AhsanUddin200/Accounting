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
          u.username,
          CASE 
              WHEN t.contra_ref IS NOT NULL THEN 'Contra Entry'
              WHEN EXISTS(SELECT 1 FROM transactions WHERE contra_ref = t.id) THEN 'Reversed'
              ELSE 'Original'
          END as entry_status
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
                        <th>Status</th>
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
                            <td>
                                <?php if($row['entry_status'] == 'Contra Entry'): ?>
                                    <span class="badge bg-warning">Contra Entry</span>
                                <?php elseif($row['entry_status'] == 'Reversed'): ?>
                                    <span class="badge bg-secondary">Reversed</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Original</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td>
                                <a href="edit_transaction.php?id=<?php echo $row['id']; ?>" 
                                   class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if($row['entry_status'] == 'Original'): ?>
                                    <a href="create_contra_entry.php?id=<?php echo $row['id']; ?>" 
                                       class="btn btn-warning btn-sm"
                                       onclick="return confirm('Create contra entry for this transaction?');">
                                        <i class="fas fa-exchange-alt"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
                <tfoot>
                    <tr class="table-dark">
                        <td colspan="6" class="text-center">
                            <div class="d-flex justify-content-center gap-4">
                                <?php
                                // Reset the result pointer first
                                mysqli_data_seek($transactions, 0);
                                
                                $original_total = 0;
                                $contra_total = 0;

                                // Calculate totals while iterating through results
                                while ($row = $transactions->fetch_assoc()) {
                                    if ($row['entry_status'] == 'Original') {
                                        $original_total += $row['amount'];
                                    } elseif ($row['entry_status'] == 'Contra Entry') {
                                        $contra_total += $row['amount'];
                                    }
                                }

                                // Reset pointer again for main table loop
                                mysqli_data_seek($transactions, 0);
                                ?>
                                <div>Original Total: PKR <?php echo number_format($original_total, 2); ?></div>
                                <div>Contra Total: PKR <?php echo number_format($contra_total, 2); ?></div>
                                <div><strong>Net Total: PKR <?php echo number_format($original_total - $contra_total, 2); ?></strong></div>
                            </div>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>