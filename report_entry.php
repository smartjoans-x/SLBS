<?php
session_start();
require_once 'functions.php';
require_permission('access_reports');
require_once 'db_connect.php'; 

// Optional: show mysqli errors as exceptions during development
// NOTE: Retaining this is good, but proper error handling must account for it.
// mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); 

$message = '';
// FIX 1: Initialize all variables that might be checked or counted later.
$bill_id_to_fetch = $_GET['bill_id'] ?? null; // Line 10 (FIXED by adding ?? null)
$bill_data = null;
$tests_to_report = [];
$existing_results = []; 
$is_edit_mode = false;
$pending_bills = []; // FIX 2: Initialize as array

$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-0 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');


// --- Utility Function to Check for Existing Report ---
function has_report($conn, $bill_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM reports WHERE bill_id = ?");
    if ($stmt === false) {
        // If prepare fails, return false safely
        return false;
    }
    $stmt->bind_param("i", $bill_id);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count > 0;
}


// --- Fetch Bills List (Pending/Reported) ---
$sql_bills = "
    SELECT b.bill_id, p.pt_name, p.age, p.sex, b.bill_date 
    FROM billing b 
    JOIN patients p ON b.pt_id = p.pt_id 
    WHERE b.status = 'Paid' 
    AND DATE(b.bill_date) BETWEEN ? AND ?
    ORDER BY b.bill_date DESC LIMIT 50
";

try {
    $stmt_bills = $conn->prepare($sql_bills);
    if ($stmt_bills === false) {
        throw new Exception("SQL Prepare failed for bill list: " . $conn->error);
    }
    $stmt_bills->bind_param("ss", $start_date, $end_date);
    $stmt_bills->execute();
    $all_bills = $stmt_bills->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_bills->close();

    foreach ($all_bills as $bill) {
        $bill['status'] = has_report($conn, $bill['bill_id']) ? 'Reported' : 'Pending';
        $pending_bills[] = $bill;
    }
} catch (Exception $e) {
    // If fetching bills fails, $pending_bills remains [] (empty array)
    error_log("Failed to load pending bills: " . $e->getMessage());
    $message = "<div style='color:red;'>Warning: Could not load the bill list due to a database error.</div>";
}


// --- Fetch Report Data for a Selected Bill (Logic Unchanged) ---
if ($bill_id_to_fetch) {
    $is_edit_mode = has_report($conn, $bill_id_to_fetch);

    // 1. Fetch bill details and associated tests
    $sql_tests = "
        SELECT 
            b.bill_id, p.pt_name, p.age, p.sex,
            t.test_id, t.test_name, t.test_department, t.report_type, t.decimal_places, t.unit
        FROM billing b
        JOIN patients p ON b.pt_id = p.pt_id
        JOIN bill_tests bt ON b.bill_id = bt.bill_id
        JOIN tests t ON bt.test_id = t.test_id
        WHERE b.bill_id = ? AND b.status = 'Paid'
    ";
    
    $stmt = $conn->prepare($sql_tests);
    if ($stmt === false) { die("Prepare failed (tests): " . $conn->error); }
    $stmt->bind_param("i", $bill_id_to_fetch);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $tests_grouped_by_dept = [];
        
        while ($row = $result->fetch_assoc()) {
            if (!$bill_data) {
                $bill_data = ['bill_id' => $row['bill_id'], 'pt_name' => $row['pt_name'], 'age' => $row['age'], 'sex' => $row['sex']];
            }
            
            $test_id = (int)$row['test_id'];
            $department = $row['test_department'] ?: 'GENERAL';
            
            if (!isset($tests_grouped_by_dept[$department])) {
                $tests_grouped_by_dept[$department] = [];
            }
            
            $tests_grouped_by_dept[$department][$test_id] = [
                'test_name' => $row['test_name'],
                'report_type' => $row['report_type'],
                'decimal_places' => (int)$row['decimal_places'],
                'unit' => $row['unit'],
                'sub_tests' => [],
                'ranges' => []
            ];
            
            // Fetch sub_tests
            $sub_stmt = $conn->prepare("SELECT sub_test_id, sub_test_name, report_type, decimal_places, unit FROM sub_tests WHERE test_id = ?");
            if ($sub_stmt) {
                $sub_stmt->bind_param("i", $test_id);
                $sub_stmt->execute();
                $sub_tests = $sub_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $sub_stmt->close();
            } else {
                $sub_tests = [];
            }
            $tests_grouped_by_dept[$department][$test_id]['sub_tests'] = $sub_tests;

            // Fetch reference ranges INCLUDING sub_test_id (PATCHED)
            // Note: use COALESCE for min/max age to be defensive if they are NULL in DB
            $age = (int)$bill_data['age'];
            $sex = $bill_data['sex'];
            $ranges_sql = "
                SELECT range_id, sub_test_id, normal_value, min_age, max_age, sex
                FROM ref_ranges
                WHERE test_id = ?
                  AND (? BETWEEN COALESCE(min_age, 0) AND COALESCE(max_age, 999))
                  AND (sex = ? OR sex = 'Any')
            ";
            $stmt_range = $conn->prepare($ranges_sql);
            if ($stmt_range) {
                $stmt_range->bind_param("iis", $test_id, $age, $sex);
                $stmt_range->execute();
                $ranges_result = $stmt_range->get_result();
                $tests_grouped_by_dept[$department][$test_id]['ranges'] = $ranges_result ? $ranges_result->fetch_all(MYSQLI_ASSOC) : [];
                $stmt_range->close();
            } else {
                $tests_grouped_by_dept[$department][$test_id]['ranges'] = [];
            }
        }
        $tests_to_report = $tests_grouped_by_dept;

        // 2. Fetch Existing Results for Edit Mode
        if ($is_edit_mode) {
            $sql_existing = "
                SELECT r.test_id, r.sub_test_id, r.result_value, r.range_id 
                FROM reports r 
                WHERE r.bill_id = ?
            ";
            $stmt_e = $conn->prepare($sql_existing);
            if ($stmt_e === false) { die("Prepare failed (existing): " . $conn->error); }
            $stmt_e->bind_param("i", $bill_id_to_fetch);
            $stmt_e->execute();
            $result_e = $stmt_e->get_result();

            while ($row = $result_e->fetch_assoc()) {
                $sub_id = isset($row['sub_test_id']) ? $row['sub_test_id'] : 0;
                $key = $row['test_id'] . '_' . $sub_id;
                $existing_results[$key] = [
                    'result_value' => $row['result_value'],
                    'range_id' => $row['range_id']
                ];
            }
            $stmt_e->close();
        }

    } else {
        $message = "<div style='color:red;'>Bill not found or not paid.</div>";
    }
    $stmt->close();
}


// --- Handle Report Submission (Decimal Saving Fix) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_report'])) {
    // (Submission logic remains unchanged)
    $bill_id = (int)$_POST['bill_id'];
    $report_data = $_POST['report_data'] ?? [];
    $mode = $_POST['mode'] ?? 'insert'; 

    $conn->begin_transaction();
    try {
        if ($mode === 'edit') {
            $del_stmt = $conn->prepare("DELETE FROM reports WHERE bill_id = ?");
            if ($del_stmt === false) { throw new Exception("Prepare failed (delete reports): " . $conn->error); }
            $del_stmt->bind_param("i", $bill_id);
            $del_stmt->execute();
            $del_stmt->close();
        }
        
        $stmt_safe = $conn->prepare("INSERT INTO reports (bill_id, test_id, sub_test_id, result_value, range_id) VALUES (?, ?, ?, ?, ?)");
        if ($stmt_safe === false) { throw new Exception("Prepare failed (insert reports): " . $conn->error); }
        
        foreach ($report_data as $test_id_str => $sub_test_data) {
            $test_id = (int)$test_id_str;

            // Fetch test details for formatting 
            $test_details_q = $conn->query("SELECT report_type, decimal_places FROM tests WHERE test_id = $test_id");
            $test_details = $test_details_q->fetch_assoc() ?: [];
            $main_report_type = $test_details['report_type'] ?? 'text';
            $main_decimal_places = (int)($test_details['decimal_places'] ?? 0);
            
            foreach ($sub_test_data as $sub_test_id_str => $result_entry) {
                $sub_test_id = ($sub_test_id_str === '') ? 0 : (int)$sub_test_id_str;
                $raw_result_value = $result_entry['result_value'] ?? '';

                // Determine effective settings
                $effective_report_type = $main_report_type;
                $effective_decimal_places = $main_decimal_places;

                if ($sub_test_id !== 0) {
                     $sub_q = $conn->prepare("SELECT report_type, decimal_places FROM sub_tests WHERE sub_test_id = ?");
                     if ($sub_q) {
                         $sub_q->bind_param("i", $sub_test_id);
                         $sub_q->execute();
                         $sub_details = $sub_q->get_result()->fetch_assoc();
                         $sub_q->close();

                         if ($sub_details) {
                             $effective_report_type = $sub_details['report_type'] ?: $main_report_type;
                             $effective_decimal_places = (int)($sub_details['decimal_places'] ?? 0);
                         }
                     }
                }
                
                // --- AGGRESSIVE DECIMAL FIX LOGIC ---
                if ($effective_report_type === 'numeric' && $raw_result_value !== '' && is_numeric($raw_result_value)) {
                    $result_value = number_format((float)$raw_result_value, max(0, $effective_decimal_places), '.', '');
                } else {
                    $result_value = (string)$raw_result_value;
                }

                $result_value = trim($result_value);

                // Handle nullable IDs
                $range_id_raw = $result_entry['range_id'] ?? '';
                $range_id_nullable = ($range_id_raw === '' || $range_id_raw === null) ? null : (int)$range_id_raw;
                $sub_test_id_nullable = ($sub_test_id === 0) ? null : $sub_test_id;

                if ($result_value !== '') {
                    // bind_param expects variables; nulls are allowed in PHP mysqli and will insert NULL
                    $bind_bill = $bill_id;
                    $bind_test = $test_id;
                    $bind_sub = $sub_test_id_nullable;
                    $bind_result = $result_value; 
                    $bind_range = $range_id_nullable;

                    // types: i (bill_id), i (test_id), i (sub_test_id), s (result_value), i (range_id)
                    $stmt_safe->bind_param("iiisi", $bind_bill, $bind_test, $bind_sub, $bind_result, $bind_range);
                    $stmt_safe->execute();
                }
            }
        }
        $stmt_safe->close();
        $conn->commit();
        $action_msg = ($mode === 'edit') ? 'updated' : 'saved';
        $message = "<div style='color:green;'>Report results {$action_msg} successfully for Bill ID: {$bill_id}</div>";
        
        header("Location: report_entry.php?msg=" . urlencode($message));
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $message = "<div style='color:red;'>Report saving failed: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Check for success message passed via redirect
if (isset($_GET['msg'])) {
    $message = urldecode($_GET['msg']);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Report Entry</title>
    <style>
        .container { padding: 20px; font-family: Arial, sans-serif; display: flex; gap: 20px; }
        .list-panel { flex: 1; border-right: 1px solid #ccc; padding-right: 15px; }
        .entry-panel { flex: 3; padding-left: 15px; }
        .test-department-group { border: 2px solid #007bff; margin-bottom: 25px; padding: 15px; border-radius: 8px; }
        .department-header { background-color: #007bff; color: white; padding: 10px; margin: -15px -15px 15px -15px; border-radius: 6px 6px 0 0; font-size: 1.3em; }
        .test-group { border: 1px dashed #ccc; margin-bottom: 10px; padding: 10px; border-radius: 5px; }
        .subtest-row { display: flex; align-items: center; margin-top: 5px; border-bottom: 1px dotted #eee; padding-bottom: 5px; }
        .subtest-row > div { flex: 1; padding: 0 10px; }
        .subtest-row input, .subtest-row select { width: 100%; padding: 5px; box-sizing: border-box; }
        .header-row { font-weight: bold; background-color: #f0f0f0; padding: 5px 0; }
        .filter-form-list { margin-bottom: 15px; padding: 10px; border: 1px solid #eee; border-radius: 5px; }
        .pending-bill-card { border: 1px solid #ddd; padding: 10px; margin-bottom: 8px; border-radius: 4px; display: flex; justify-content: space-between; align-items: center; }
        .status-reported { color: green; font-weight: bold; }
        .status-pending { color: orange; font-weight: bold; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        
        <div class="list-panel">
            <h2>Select Bill</h2>
            
            <div class="filter-form-list">
                <form method="get" action="" style="display: flex; flex-direction: column; gap: 10px;">
                    <label>From Date:</label>
                    <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" required>
                    <label>To Date:</label>
                    <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" required>
                    <button type="submit" style="padding: 8px 15px; background-color: #5bc0de; color: white; border: none;">Apply Filter</button>
                    <input type="hidden" name="bill_id" value=""> 
                </form>
            </div>

            <p>Bills (<?php echo count($pending_bills); ?>) in range:</p>

            <div style="max-height: 500px; overflow-y: auto;">
                <?php if (empty($pending_bills)): ?>
                    <p style="color: gray;">No paid bills found.</p>
                <?php endif; ?>
                <?php foreach ($pending_bills as $bill): ?>
                    <div class="pending-bill-card">
                        <div>
                            <strong>Bill #<?php echo htmlspecialchars($bill['bill_id']); ?></strong><br>
                            <span style="font-size: 0.9em;"><?php echo htmlspecialchars($bill['pt_name']); ?></span>
                        </div>
                        <div>
                            <span class="<?php echo ($bill['status'] == 'Reported') ? 'status-reported' : 'status-pending'; ?>">
                                <?php echo htmlspecialchars($bill['status']); ?>
                            </span>
                            <a href="?bill_id=<?php echo htmlspecialchars($bill['bill_id']); ?>&start_date=<?php echo htmlspecialchars($start_date); ?>&end_date=<?php echo htmlspecialchars($end_date); ?>">
                                <button style="padding: 8px; margin-left: 5px; background-color: #f0ad4e; color: white; border: none;">
                                    <?php echo ($bill['status'] == 'Reported') ? 'Edit' : 'Enter'; ?>
                                </button>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="entry-panel">
            <h1>Test Result Entry</h1>
            <?php echo $message; ?>
            
            <?php if ($bill_data): ?>
                
                <h2 style="border-bottom: 2px solid #333; padding-bottom: 5px; margin-top: 0;">
                    Report Entry for Bill #<?php echo htmlspecialchars($bill_data['bill_id']); ?> 
                    <span style="font-size: 0.8em; font-weight: normal;">
                        (Patient: <strong><?php echo htmlspecialchars($bill_data['pt_name']); ?></strong>, Age: <?php echo htmlspecialchars($bill_data['age']); ?>)
                    </span>
                </h2>

                <form action="" method="post">
                    <input type="hidden" name="bill_id" value="<?php echo htmlspecialchars($bill_data['bill_id']); ?>">
                    <input type="hidden" name="mode" value="<?php echo $is_edit_mode ? 'edit' : 'insert'; ?>">

                    <?php foreach ($tests_to_report as $department => $tests_in_dept): ?>
                        <div class="test-department-group">
                            <div class="department-header"><?php echo htmlspecialchars(ucwords($department)); ?></div>
                            
                            <div class="header-row subtest-row" style="margin-bottom: 5px;">
                                <div style="flex: 2;">Test / Sub-Test Name</div>
                                <div style="flex: 1;">Result Value</div>
                                <div style="flex: 1;">Unit</div>
                                <div style="flex: 2;">Reference Range</div>
                            </div>
                            
                            <?php foreach ($tests_in_dept as $test_id => $test): ?>
                                <div class="test-group">
                                    <h4><?php echo htmlspecialchars($test['test_name']); ?></h4>
                                    <?php 
                                    $sub_tests = empty($test['sub_tests']) 
                                        ? [['sub_test_id' => 0, 'sub_test_name' => $test['test_name'], 'report_type' => $test['report_type'], 'decimal_places' => $test['decimal_places'], 'unit' => $test['unit']]] 
                                        : $test['sub_tests'];
                                    
                                    // Base settings
                                    $base_decimal_places = $test['decimal_places'];
                                    $base_unit = $test['unit'];
                                    $base_report_type = $test['report_type'];
                                    $all_ranges_for_test = $test['ranges'] ?? [];
                                    ?>

                                    <?php foreach ($sub_tests as $subtest): 
                                        $sub_id = $subtest['sub_test_id'] ?? 0;
                                        $key = $test_id . '_' . $sub_id;
                                        $existing = $existing_results[$key] ?? ['result_value' => '', 'range_id' => ''];

                                        // Determine effective settings (Sub-test overrides Main Test)
                                        $effective_type = $subtest['report_type'] ?? $base_report_type;
                                        $effective_decimals = $subtest['decimal_places'] ?? $base_decimal_places;
                                        $effective_unit = $subtest['unit'] ?? $base_unit; 

                                        $input_type = ($effective_type == 'numeric') ? 'number' : 'text';
                                        $step_value = ($effective_decimals > 0) 
                                                      ? ('0.' . str_repeat('0', $effective_decimals - 1) . '1')
                                                      : '1';
                                        $step_attr = ($input_type == 'number') ? 'step="' . $step_value . '"' : '';

                                        // --- BUILD parameter_ranges robustly (PATCHED) ---
                                        $parameter_ranges = [];

                                        // If this is a sub-test (sub_id != 0), prefer ranges where sub_test_id matches this sub test
                                        if (!empty($all_ranges_for_test) && $sub_id != 0) {
                                            foreach ($all_ranges_for_test as $range_row) {
                                                if (isset($range_row['sub_test_id']) && $range_row['sub_test_id'] !== null && $range_row['sub_test_id'] !== '' && (int)$range_row['sub_test_id'] === (int)$sub_id) {
                                                    $parameter_ranges[] = $range_row;
                                                }
                                            }
                                        }

                                        // If no sub-test-specific ranges found (or sub_id == 0), fall back to main-test ranges (sub_test_id IS NULL)
                                        if (empty($parameter_ranges)) {
                                            foreach ($all_ranges_for_test as $range_row) {
                                                if (!isset($range_row['sub_test_id']) || $range_row['sub_test_id'] === null || $range_row['sub_test_id'] === '') {
                                                    $parameter_ranges[] = $range_row;
                                                }
                                            }
                                        }

                                    ?>
                                        <div class="subtest-row">
                                            <div style="flex: 2; font-weight: 500;"><?php echo htmlspecialchars($subtest['sub_test_name']); ?></div>
                                            <div style="flex: 1;">
                                                <input type="<?php echo htmlspecialchars($input_type); ?>" 
                                                    name="report_data[<?php echo htmlspecialchars($test_id); ?>][<?php echo htmlspecialchars($sub_id); ?>][result_value]"
                                                    value="<?php echo htmlspecialchars($existing['result_value']); ?>"
                                                    <?php echo htmlspecialchars($step_attr); ?> >
                                            </div>
                                            <div style="flex: 1; padding-left: 10px;">
                                                <?php echo htmlspecialchars($effective_unit ?? ''); ?>
                                            </div>
                                            <div style="flex: 2;">
                                                <select name="report_data[<?php echo htmlspecialchars($test_id); ?>][<?php echo htmlspecialchars($sub_id); ?>][range_id]">
                                                    <option value="">-- Select Ref Range --</option>
                                                    <?php 
                                                    if (!empty($parameter_ranges)) {
                                                        foreach ($parameter_ranges as $range): 
                                                            $range_id_val = $range['range_id'] ?? '';
                                                            $min = $range['min_age'] ?? '';
                                                            $max = $range['max_age'] ?? '';
                                                            $sex_r = $range['sex'] ?? '';
                                                            $normal_value_str = $range['normal_value'] ?? '';
                                                    ?>
                                                            <option value="<?php echo htmlspecialchars($range_id_val); ?>"
                                                                <?php echo ($existing['range_id'] == $range_id_val) ? 'selected' : ''; ?>>
                                                                <?php 
                                                                    // friendly label: normal_value (min-max y sex)
                                                                    $label = trim($normal_value_str);
                                                                    $age_label = ($min !== '' || $max !== '') ? " ({$min}-{$max}y {$sex_r})" : "";
                                                                    echo htmlspecialchars($label . $age_label);
                                                                ?>
                                                            </option>
                                                        <?php endforeach; 
                                                    } else { ?>
                                                        <option value="" disabled>No reference ranges defined</option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    <button type="submit" name="submit_report" style="width: 100%; padding: 15px; margin-top: 20px; background-color: #337ab7; color: white; border: none;">
                        <?php echo $is_edit_mode ? 'UPDATE FINAL REPORT' : 'SAVE FINAL REPORT'; ?>
                    </button>
                </form>
            <?php else: ?>
                <p style="text-align: center; margin-top: 50px;">Select a bill from the list on the left to begin data entry or editing.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
