<?php
require_once 'session.php';
require_once 'db.php';

if (isset($_GET['head_id'])) {
    $head_id = $_GET['head_id'];
    
    $query = "SELECT id, name FROM account_categories WHERE head_id = ? ORDER BY name ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $head_id);
    $stmt->execute();
    $result = $stmt->get_result();

    echo '<option value="">Select Category</option>';
    while ($category = $result->fetch_assoc()) {
        echo '<option value="' . $category['id'] . '">' . htmlspecialchars($category['name']) . '</option>';
    }
}
?>