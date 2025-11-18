<?php
session_start();
require_once 'db_connect.php'; 

header('Content-Type: application/json');

$pt_id = (int)($_GET['pt_id'] ?? 0);
$from_date = $_GET['from_date'] ?? date('Y-m-d');
$to_date = $_GET['to_date'] ?? date('Y-m-d');

$response = [];

if ($pt_id > 0) {
    try {
        // Fetch all bills for the patient, filtered by date
        $sql_bills = "
            SELECT 
                b.bill_id, b.net_amount, b.bill_date, b.status
            FROM billing b
            WHERE b.pt_id = ?
            AND DATE(b.bill_date) BETWEEN ? AND ?
            ORDER BY b.bill_date DESC
        ";
        
        $stmt_bills = $conn->prepare($sql_bills);
        if (!$stmt_bills) throw new Exception("Failed to prepare bills query: " . $conn->error);
        
        $stmt_bills->bind_param("iss", $pt_id, $from_date, $to_date);
        $stmt_bills->execute();
        $bills = $stmt_bills->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_bills->close();

        // Prepare test status query
        $sql_tests = "
            SELECT 
                t.test_name, st.sub_test_name,
                -- Use COALESCE to get the most specific name for display
                COALESCE(st.sub_test_name, t.test_name) AS display_name, 
                r.result_value, 
                CASE WHEN r.result_value IS NOT NULL AND r.result_value != '' THEN TRUE ELSE FALSE END AS report_ready
            FROM bill_tests bt
            JOIN tests t ON bt.test_id = t.test_id
            LEFT JOIN sub_tests st ON t.test_id = st.test_id 
            LEFT JOIN reports r ON bt.bill_id = r.bill_id AND bt.test_id = r.test_id AND (st.sub_test_id = r.sub_test_id OR st.sub_test_id IS NULL)
            WHERE bt.bill_id = ?
            -- FIX: We need to group correctly to ensure we capture all individual report entries (sub-tests)
            GROUP BY COALESCE(st.sub_test_id, bt.bt_id), t.test_name, st.sub_test_name, r.result_value 
            ORDER BY t.test_name, st.sub_test_name
        ";
        
        $stmt_tests = $conn->prepare($sql_tests);
        if (!$stmt_tests) throw new Exception("Failed to prepare tests query: " . $conn->error);

        foreach ($bills as $bill) {
            $bill_id = $bill['bill_id'];
            
            // Execute test status query
            $stmt_tests->bind_param("i", $bill_id);
            $stmt_tests->execute();
            $tests = $stmt_tests->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Structure the response
            $bill['net_amount'] = (float)$bill['net_amount'];
            $bill['tests'] = array_map(function($t) {
                $t['report_ready'] = (bool)$t['report_ready'];
                // JS must now look for 'display_name'
                return $t;
            }, $tests);
            
            $response[] = $bill;
        }
        $stmt_tests->close();

    } catch (Exception $e) {
        // Return a structured error message in JSON format for the client console
        http_response_code(500);
        echo json_encode(['error' => 'Database operation failed', 'details' => $e->getMessage()]);
        exit;
    }
}

echo json_encode($response);
?>