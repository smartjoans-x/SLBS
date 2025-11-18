<?php
session_start();
require_once 'functions.php';
require_permission('access_billing');
require_once 'db_connect.php'; 

// Fetch Company Details
$company_info = $conn->query("SELECT company_name, address, phone_no FROM company LIMIT 1")->fetch_assoc() ?? ['company_name' => 'Lab Billing System', 'address' => 'Not Set', 'phone_no' => 'Not Set'];

// Get Filters
$current_day = date('Y-m-d'); 
$start_date = $_GET['start_date'] ?? $current_day; 
$end_date = $_GET['end_date'] ?? $current_day;     

// Fetch completed reports based on date filter
$completed_reports = [];
$sql_completed = "
    SELECT DISTINCT b.bill_id, p.pt_name, p.age, b.bill_date 
    FROM billing b 
    JOIN patients p ON b.pt_id = p.pt_id 
    JOIN reports r ON b.bill_id = r.bill_id
    WHERE DATE(b.bill_date) BETWEEN ? AND ?
    ORDER BY b.bill_date DESC LIMIT 50
";
$stmt_completed = $conn->prepare($sql_completed);
$stmt_completed->bind_param("ss", $start_date, $end_date);
$stmt_completed->execute();
$completed_reports = $stmt_completed->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_completed->close();


// --- Logic to fetch a single report for viewing ---
$view_bill_id = $_GET['view_bill_id'] ?? null;
$report_details = [];
$patient_info = null;

if ($view_bill_id) {
    // 1. Fetch patient/bill info
    $patient_info_sql = "
        SELECT 
            p.pt_name, p.age, p.sex, p.mobile_no, b.bill_id, b.bill_date,
            d.doctor_name AS referring_doctor
        FROM billing b 
        JOIN patients p ON b.pt_id = p.pt_id 
        LEFT JOIN doctors d ON b.refer_type = 'Doctor' AND b.refer_id = d.doctor_id
        WHERE b.bill_id = ?
    ";
    $stmt_p = $conn->prepare($patient_info_sql);
    $stmt_p->bind_param("i", $view_bill_id);
    $stmt_p->execute();
    $patient_info = $stmt_p->get_result()->fetch_assoc();
    $stmt_p->close();
    
    // 2. Fetch all report results for the bill, grouped by Department
    $report_sql = "
        SELECT 
            t.test_department, t.test_name, t.unit AS main_unit, 
            st.sub_test_name, st.unit AS sub_unit, r.result_value, 
            rr.normal_value, rr.sex, rr.min_age, rr.max_age
        FROM reports r
        JOIN tests t ON r.test_id = t.test_id
        LEFT JOIN sub_tests st ON r.sub_test_id = st.sub_test_id
        LEFT JOIN ref_ranges rr ON r.range_id = rr.range_id 
        WHERE r.bill_id = ?
        ORDER BY t.test_department, t.test_name, st.sub_test_id
    ";
    $stmt_r = $conn->prepare($report_sql);
    $stmt_r->bind_param("i", $view_bill_id);
    $stmt_r->execute();
    $results = $stmt_r->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_r->close();

    // Group results by department
    foreach ($results as $result) {
        $department = $result['test_department'] ?: 'GENERAL';
        if (!isset($report_details[$department])) {
            $report_details[$department] = [];
        }
        $report_details[$department][] = $result;
    }
}

/**
 * Checks if a result value is outside a reference range and returns formatting style.
 */
function check_abnormal($result_value, $normal_value) {
    if (!$normal_value) return '';
    
    $result_value = trim((string)$result_value);
    $normal_value = trim((string)$normal_value);

    // 1. Numerical Comparison
    if (preg_match('/^(-?\d+(\.\d+)?)\s*-\s*(-?\d+(\.\d+)?)$/', $normal_value, $matches) && is_numeric($result_value)) {
        $min = (float)$matches[1];
        $max = (float)$matches[3];
        $value = (float)$result_value;
        
        $epsilon = 0.00001; 
        
        if ($value < ($min - $epsilon) || $value > ($max + $epsilon)) {
            return 'style="font-weight: bold; color: red;"';
        }
        return '';
    }

    // 2. Categorical/String Comparison
    if (!is_numeric($result_value)) {
        if (strtolower($result_value) !== strtolower($normal_value)) {
             return 'style="font-weight: bold;"';
        }
        return '';
    }
    
    return ''; 
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Test Reports</title>
    <style>
        /* --- GENERAL PAGE STRUCTURE --- */
        .container { 
            padding: 20px; 
            font-family: Arial, sans-serif; 
            background: white; 
            border-radius: 8px; 
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            display: flex; 
            gap: 30px; 
        }
        
        /* --- LAYOUT PANELS --- */
        .report-list-panel { flex: 1; }
        .report-display-panel { flex: 2.5; border-left: 1px solid #ccc; padding-left: 30px; }
        
        /* --- BUTTON STYLING (Restored for list panel buttons) --- */
        .view-button {
            padding: 5px 10px; background-color: #007bff; color: white; text-decoration: none; border: none; border-radius: 3px; cursor: pointer;
            font-size: 0.9em; transition: background-color 0.2s; display: inline-block; text-align: center;
        }
        .view-button:hover {
            background-color: #0056b3;
        }

        /* --- FILTER RESULTS TABLE STYLING --- */
        .filter-results-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 0.9em; }
        .filter-results-table th, .filter-results-table td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        .filter-results-table th { background-color: #f0f0f0; font-weight: bold; }

        /* --- REPORT DISPLAY STRUCTURE --- */
        .patient-report-container { width: 100%; max-width: 800px; margin: 0 auto; border: none; padding: 0; }
        .report-company-header { text-align: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 3px double #333; }
        .report-patient-info { border: 1px solid #ccc; padding: 10px; margin-bottom: 20px; font-size: 0.9em; display: flex; justify-content: space-between; }
        .report-section-title { font-size: 1.1em; font-weight: bold; margin-top: 15px; border-bottom: 1px solid #000; padding-bottom: 3px; }
        .report-department-title { font-size: 1.05em; font-weight: bold; margin-top: 10px; color: #555; }
        
        /* Report Table Column Setup */
        .report-table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        .report-table thead th { border-bottom: 1px solid #000; padding: 5px 0; font-weight: bold; }
        .report-table tbody td { border-bottom: 1px dotted #ccc; padding: 6px 0; font-size: 0.95em; }
        
        /* Column Alignment */
        .td-param { width: 40%; text-align: left; }
        .td-result { width: 15%; text-align: center; } 
        .td-unit { width: 15%; text-align: left; font-size: 0.85em; color: #555; padding-left: 5px; } 
        .td-range { width: 30%; text-align: right; } 
        .report-table thead th:last-child { text-align: right; } 

        /* --- PRINT FIXES --- */
        @media print {
            .navbar, .report-list-panel, .filter-controls, button.no-print { 
                display: none !important; 
                visibility: hidden; 
                height: 0; 
                margin: 0;
            }
            .container { padding: 0 !important; display: block; width: 100%; }
            .report-display-panel { border: none; padding: 0; margin: 0 auto; width: 100%; float: none; }
            
            /* 1. Hide header entirely when class is present */
            .no-header-print .report-company-header {
                display: none !important;
            }

            /* 2. Add top spacing only when printing WITHOUT header */
            .no-header-print .report-patient-info {
                margin-top: 2in !important; /* Add required space for physical letterhead */
            }

            body * { visibility: hidden; }
            .patient-report-container, .patient-report-container * { visibility: visible; }
            .patient-report-container { position: absolute; left: 0; top: 0; }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container" style="display: flex; gap: 30px;">
        
        <div class="report-list-panel">
            <h2>Report Search & Filter</h2>
            <div class="filter-controls">
                <form method="get" action="">
                    <label>From Date:</label>
                    <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" required>
                    <label>To Date:</label>
                    <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" required>
                    <button type="submit" class="view-button" style="padding: 5px 10px; margin-top: 10px;">Filter List</button>
                </form>
            </div>
            
            <h3 style="margin-top: 20px;">Reports (<?php echo count($completed_reports); ?>)</h3>
            <div style="max-height: 450px; overflow-y: auto;">
                
                <table class="filter-results-table">
                    <thead>
                        <tr>
                            <th>Bill ID</th>
                            <th>Patient Name</th>
                            <th>Age</th>
                            <th>View</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($completed_reports)): ?>
                            <tr><td colspan="4" style="text-align: center;">No reports found in this date range.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($completed_reports as $report): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($report['bill_id']); ?></strong></td>
                                <td><?php echo htmlspecialchars($report['pt_name']); ?></td>
                                <td><?php echo htmlspecialchars($report['age']); ?></td>
                                <td>
                                    <a href="?view_bill_id=<?php echo htmlspecialchars($report['bill_id']); ?>&start_date=<?php echo htmlspecialchars($start_date); ?>&end_date=<?php echo htmlspecialchars($end_date); ?>" class="view-button">
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="report-display-panel">
            <?php if ($patient_info): ?>
                
                <div class="no-print" style="margin-bottom: 15px; display: flex; gap: 10px;">
                    <button onclick="printReport(true)" class="view-button" style="background-color: #28a745; width: 50%;">Print with Header</button>
                    <button onclick="printReport(false)" class="view-button" style="background-color: #dc3545; width: 50%;">Print without Header</button>
                </div>

                <div class="patient-report-container">
                    
                    <div class="report-company-header">
                        <h2><?php echo htmlspecialchars($company_info['company_name']); ?></h2>
                        <p><?php echo htmlspecialchars($company_info['address'] ?? 'Address Not Set'); ?> | Phone: <?php echo htmlspecialchars($company_info['phone_no'] ?? 'Not Set'); ?></p>
                    </div>

                    <div class="report-patient-info">
                        <div>
                            <strong>Patient:</strong> <?php echo htmlspecialchars($patient_info['pt_name']); ?><br>
                            <strong>Age/Sex:</strong> <?php echo htmlspecialchars($patient_info['age']); ?> / <?php echo htmlspecialchars($patient_info['sex']); ?>
                        </div>
                        <div>
                            <strong>Bill No:</strong> <?php echo htmlspecialchars($patient_info['bill_id']); ?><br>
                            <strong>Date:</strong> <?php echo date('Y-m-d H:i', strtotime($patient_info['bill_date'])); ?>
                        </div>
                        <div>
                            <strong>Ref. By:</strong> <?php echo htmlspecialchars($patient_info['referring_doctor'] ?: 'Self'); ?><br>
                            <strong>Mobile:</strong> <?php echo htmlspecialchars($patient_info['mobile_no']); ?>
                        </div>
                    </div>
                    
                    <div class="report-section-title">Finalized Report</div>

                    <table class="report-table">
                        <thead>
                            <tr>
                                <th class="td-param">Test / Parameter</th>
                                <th class="td-result">Result Value</th>
                                <th class="td-unit">Unit</th>
                                <th class="td-range">Reference Range</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_details as $department => $results_list): ?>
                                <tr>
                                    <td colspan="4" class="report-department-title"><?php echo htmlspecialchars(ucwords($department)); ?></td>
                                </tr>
                                <?php 
                                    $current_test_name = '';
                                    foreach ($results_list as $result): 
                                    
                                    // Bolding logic
                                    $style = check_abnormal($result['result_value'], $result['normal_value']);
                                    // Determine the unit: use sub_unit if available, otherwise use main_unit
                                    $unit_display = ($result['sub_unit'] ?? null) ?: ($result['main_unit'] ?? '');
                                ?>
                                    <tr>
                                        <td class="td-param">
                                            <?php 
                                                // Show Test Name once, then show sub-test name indented
                                                if ($result['test_name'] !== $current_test_name) {
                                                    echo '<strong>' . htmlspecialchars($result['test_name']) . '</strong>';
                                                    $current_test_name = $result['test_name'];
                                                }
                                                // Check if sub_test_name is not NULL or empty string before displaying
                                                if (!empty($result['sub_test_name'])) {
                                                    echo '<div style="margin-left: 15px; font-style: italic;">' . htmlspecialchars($result['sub_test_name']) . '</div>';
                                                }
                                            ?>
                                        </td>
                                        <td class="td-result">
                                            <span <?php echo $style; ?>>
                                                <?php echo htmlspecialchars($result['result_value']); ?>
                                            </span>
                                        </td>
                                        <td class="td-unit">
                                            <?php echo htmlspecialchars($unit_display); ?>
                                        </td>
                                        <td class="td-range">
                                            <?php echo htmlspecialchars($result['normal_value'] ?: 'N/A'); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div style="text-align: right; margin-top: 30px; font-size: 0.8em; border-top: 1px solid #ccc; padding-top: 10px;">
                        End of Report. Results are technically derived and require clinical correlation.
                    </div>
                </div>

            <?php elseif ($view_bill_id): ?>
                <p>Report details not found for this bill or results not yet entered.</p>
            <?php else: ?>
                <p>Select a report from the list on the left to view the finalized patient report.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<script>
    function printReport(includeHeader) {
        const reportContainer = document.querySelector('.patient-report-container');
        
        if (!reportContainer) return; // Safety check

        if (!includeHeader) {
            // Add class to signal CSS to hide the header AND add spacing
            reportContainer.classList.add('no-header-print');
        } else {
            // Ensure class is removed for printing with the header
            reportContainer.classList.remove('no-header-print');
        }
        
        // Trigger the native print dialog
        window.print();

        // Clean up the class immediately after the print dialog closes (or fails)
        setTimeout(() => {
            reportContainer.classList.remove('no-header-print');
        }, 1000);
    }
</script>