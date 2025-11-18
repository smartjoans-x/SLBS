<?php
require_once 'db_connect.php'; // Use your connection file

header('Content-Type: application/json');

$query = $_GET['q'] ?? '';
$results = [];

if (strlen($query) >= 1) { // Only search if at least one letter is typed
    $search_term = "%" . $query . "%";
    
    // Select Test ID, Name, and Price
    $stmt = $conn->prepare("SELECT test_id, test_name, price FROM tests WHERE test_name LIKE ? LIMIT 10");
    $stmt->bind_param("s", $search_term);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $results[] = [
            'id' => $row['test_id'],
            'name' => htmlspecialchars($row['test_name']),
            'price' => (float)$row['price']
        ];
    }
    $stmt->close();
}

echo json_encode($results);
?>