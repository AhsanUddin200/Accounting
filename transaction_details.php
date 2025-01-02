<?php
require_once 'session.php';
require_once 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$type = $_GET['type'] ?? '';
$start_date = $_GET['start'] ?? date('Y-m-01');
$end_date = $_GET['end'] ?? date('Y-m-t');

// Validate type
if (!in_array($type, ['income', 'expense'])) {
    header("Location: admin_dashboard.php");
    exit();
}

// Fetch detailed transactions
$query = "SELECT t.*, ah.name as head_name, ac.name as category_name, u.username 
          FROM transactions t
          LEFT JOIN accounting_heads ah ON t.head_id = ah.id
          LEFT JOIN account_categories ac ON t.category_id = ac.id
          LEFT JOIN users u ON t.user_id = u.id
          WHERE t.type = ? AND t.date BETWEEN ? AND ?
          ORDER BY t.date DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("sss", $type, $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo ucfirst($type); ?> Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container py-4">
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
                <div class="card mb-4">
                    <div class="card-body">
                        <form id="transactionForm" class="row g-3 align-items-center">
                            <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
                            <div class="col-md-4">
                                <label class="form-label">From Date</label>
                                <input type="date" name="start_date" class="form-control" 
                                       value="<?php echo $start_date; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">To Date</label>
                                <input type="date" name="end_date" class="form-control" 
                                       value="<?php echo $end_date; ?>">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search me-2"></i>View Transactions
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div id="transactionResults">
                    <?php include 'load_transactions.php'; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        $('#transactionForm').on('submit', function(e) {
            e.preventDefault();
            
            $.ajax({
                url: 'load_transactions.php',
                type: 'GET',
                data: $(this).serialize(),
                beforeSend: function() {
                    $('#transactionResults').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i></div>');
                },
                success: function(response) {
                    $('#transactionResults').html(response);
                },
                error: function() {
                    $('#transactionResults').html('<div class="alert alert-danger">Error loading transactions</div>');
                }
            });
        });
    });
    </script>
</body>
</html>