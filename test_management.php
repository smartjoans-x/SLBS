<?php
session_start();
require_once 'functions.php';
require_permission('manage_tests');
require_once 'db_connect.php';

//--- Friendly dev setting (uncomment while debugging) ---
// mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$message = '';
$edit_test_id = $_GET['edit_id'] ?? null;
$test_data = null;
$is_edit_mode = false;

// --- Fetch Unique/Existing Values for Main Test Dropdowns ---
$unique_departments = array_unique(array_column($conn->query("SELECT DISTINCT test_department FROM tests WHERE test_department IS NOT NULL AND test_department != ''")->fetch_all(MYSQLI_ASSOC), 'test_department'));
$unique_specimens = array_unique(array_column($conn->query("SELECT DISTINCT specimen FROM tests WHERE specimen IS NOT NULL AND specimen != ''")->fetch_all(MYSQLI_ASSOC), 'specimen'));
$unique_containers = array_unique(array_column($conn->query("SELECT DISTINCT container FROM tests WHERE container IS NOT NULL AND container != ''")->fetch_all(MYSQLI_ASSOC), 'container'));
$unique_units = array_unique(array_column($conn->query("SELECT DISTINCT unit FROM tests WHERE unit IS NOT NULL AND unit != ''")->fetch_all(MYSQLI_ASSOC), 'unit'));

// --- Fetch all tests for the sidebar list ---
$all_tests = $conn->query("SELECT test_id, test_name FROM tests ORDER BY test_name")->fetch_all(MYSQLI_ASSOC);

// --- Fetch All Main Tests for Sub-test Link Option ---
$main_tests_for_link = $conn->query("SELECT test_id, test_name, specimen, container, tat_time, report_type, decimal_places, unit FROM tests ORDER BY test_name")->fetch_all(MYSQLI_ASSOC);
$main_tests_json = json_encode(array_column($main_tests_for_link, null, 'test_id')); // Prepare data for JavaScript

// --- Prepare Parameter List for Range Assignment (Dropdown Options) ---
// (Will be built client-side using currentSubTests array)

// --- Fetch Test Data for Editing ---
if ($edit_test_id) {
    $is_edit_mode = true;
    $edit_test_id = (int)$edit_test_id;

    // 1. Fetch main test data
    $test_data = $conn->query("SELECT * FROM tests WHERE test_id = $edit_test_id")->fetch_assoc();

    if ($test_data) {
        // 2. Fetch sub-tests (ordered)
        $test_data['sub_tests'] = $conn->query("SELECT * FROM sub_tests WHERE test_id = $edit_test_id ORDER BY sub_test_id")->fetch_all(MYSQLI_ASSOC);

        // 3. Fetch reference ranges
        $test_data['ref_ranges'] = $conn->query("SELECT range_id, test_id, sub_test_id, min_age, max_age, sex, normal_value FROM ref_ranges WHERE test_id = $edit_test_id")->fetch_all(MYSQLI_ASSOC);
    } else {
        $message = "<div style='color:red;'>Error: Test not found for editing.</div>";
        $is_edit_mode = false;
    }
}


// --- Submission Handler (Handles both INSERT and UPDATE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['add_test']) || isset($_POST['update_test']))) {
    $is_update = isset($_POST['update_test']);
    $test_id = $is_update ? (int)$_POST['test_id'] : 0;

    // Basic Test Details (Main Test) - Values are read from final hidden fields set by JS
    $test_name = $conn->real_escape_string(trim($_POST['test_name'] ?? ''));
    $test_department = $conn->real_escape_string(trim($_POST['test_department'] ?? ''));
    $specimen = $conn->real_escape_string(trim($_POST['specimen'] ?? ''));
    $container = $conn->real_escape_string(trim($_POST['container'] ?? ''));
    $unit = $conn->real_escape_string(trim($_POST['unit'] ?? '')); // Final value (string)

    $price = (float)($_POST['price'] ?? 0);
    $tat_time = $conn->real_escape_string(trim($_POST['tat_time'] ?? ''));
    $report_type = $conn->real_escape_string(trim($_POST['report_type'] ?? 'text'));
    $decimal_places = ($report_type == 'numeric') ? (int)($_POST['decimal_places'] ?? 0) : 0;

    // Sub-tests data (arrays)
    $sub_test_names = $_POST['sub_test_name'] ?? [];
    $sub_test_specimens = $_POST['sub_specimen'] ?? [];
    $sub_test_containers = $_POST['sub_container'] ?? [];
    $sub_test_tats = $_POST['sub_tat_time'] ?? [];
    $sub_test_types = $_POST['sub_report_type'] ?? [];
    $sub_test_decimals = $_POST['sub_decimal_places'] ?? [];
    $sub_test_units = $_POST['sub_unit'] ?? [];

    // Reference ranges posted as: ref_range[index][parameter_link], min_age, max_age, sex, normal_value
    $ref_ranges = $_POST['ref_range'] ?? [];

    if (empty($test_name) || $price <= 0) {
        $message = "<div style='color:red;'>Test Name and Price are required.</div>";
    } else {
        $conn->begin_transaction();
        try {
            $price_str = (string)number_format($price, 2, '.', '');

            if ($is_update) {
                // 1. UPDATE tests row
                $stmt = $conn->prepare(
                    "UPDATE tests SET test_name=?, test_department=?, specimen=?, container=?, price=?, tat_time=?, report_type=?, decimal_places=?, unit=? WHERE test_id=?"
                );
                if (!$stmt) { throw new Exception("Prepare failed for UPDATE tests: " . $conn->error); }

                $bindResult = $stmt->bind_param(
                    "sssssssisi",
                    $test_name, $test_department, $specimen, $container, $price_str, $tat_time, $report_type, $decimal_places, $unit, $test_id
                );
                if ($bindResult === false) { throw new Exception("bind_param failed for UPDATE tests: " . $stmt->error); }
                $stmt->execute();
                $stmt->close();

                // Remove existing ref_ranges (we will re-insert according to submitted form)
                $conn->query("DELETE FROM ref_ranges WHERE test_id = $test_id");
                // Remove existing sub_tests -> we'll re-insert from posted sub-tests
                $conn->query("DELETE FROM sub_tests WHERE test_id = $test_id");

            } else {
                // INSERT new tests row
                $stmt = $conn->prepare(
                    "INSERT INTO tests (test_name, test_department, specimen, container, price, tat_time, report_type, decimal_places, unit) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                if (!$stmt) { throw new Exception("Prepare failed for INSERT tests: " . $conn->error); }

                $bindResult = $stmt->bind_param(
                    "sssssssis",
                    $test_name, $test_department, $specimen, $container, $price_str, $tat_time, $report_type, $decimal_places, $unit
                );
                if ($bindResult === false) { throw new Exception("bind_param failed for INSERT tests: " . $stmt->error); }
                $stmt->execute();
                $test_id = $conn->insert_id;
                $stmt->close();
            }

            // 2. Insert Sub-tests and keep mapping of indexes to new sub_test_id
            $new_sub_ids = []; // index => sub_test_id
            if (!empty($sub_test_names)) {
                $stmt_sub = $conn->prepare("INSERT INTO sub_tests (test_id, sub_test_name, specimen, container, tat_time, report_type, decimal_places, unit) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$stmt_sub) { throw new Exception("Prepare failed for sub_tests: " . $conn->error); }

                foreach ($sub_test_names as $index => $sub_name_raw) {
                    $sub_name = trim($sub_name_raw);

                    if ($sub_name !== '') {
                        $sub_specimen = $conn->real_escape_string(trim($sub_test_specimens[$index] ?? ''));
                        $sub_container = $conn->real_escape_string(trim($sub_test_containers[$index] ?? ''));
                        $sub_tat = $conn->real_escape_string(trim($sub_test_tats[$index] ?? ''));
                        $sub_type = $conn->real_escape_string(trim($sub_test_types[$index] ?? 'text'));
                        $sub_decimals_val = ($sub_type == 'numeric') ? (int)($sub_test_decimals[$index] ?? 0) : 0;
                        $sub_unit = $conn->real_escape_string(trim($sub_test_units[$index] ?? ''));

                        $bindSub = $stmt_sub->bind_param("isssssis", $test_id, $sub_name, $sub_specimen, $sub_container, $sub_tat, $sub_type, $sub_decimals_val, $sub_unit);
                        if ($bindSub === false) { throw new Exception("bind_param failed for sub_tests: " . $stmt_sub->error); }
                        $stmt_sub->execute();
                        $inserted_id = $conn->insert_id;
                        $new_sub_ids[$index] = $inserted_id;
                    } else {
                        // keep index but set to 0 (no sub-test)
                        $new_sub_ids[$index] = 0;
                    }
                }
                $stmt_sub->close();
            }

            // 3. Insert Reference Ranges
            if (!empty($ref_ranges)) {
                // Note: Use NULLIF(?,0) so passing 0 will store NULL into sub_test_id
                $stmt_ref = $conn->prepare("INSERT INTO ref_ranges (test_id, sub_test_id, min_age, max_age, sex, normal_value) VALUES (?, NULLIF(?,0), ?, ?, ?, ?)");
                if (!$stmt_ref) { throw new Exception("Prepare failed for ref_ranges: " . $conn->error); }

                foreach ($ref_ranges as $range_data) {
                    $min_age = (int)($range_data['min_age'] ?? 0);
                    $max_age = (int)($range_data['max_age'] ?? 0);
                    $sex = $conn->real_escape_string($range_data['sex'] ?? 'Any');
                    $normal_value = $conn->real_escape_string($range_data['normal_value'] ?? '');

                    // Determine link from parameter_link (client uses "main" or "sub_idx_N")
                    $param_link = $range_data['parameter_link'] ?? 'main';
                    $sub_id_to_link = 0; // default 0 -> becomes NULL due to NULLIF

                    if (strpos($param_link, 'sub_idx_') === 0) {
                        $idx = (int)str_replace('sub_idx_', '', $param_link);
                        $sub_id_to_link = isset($new_sub_ids[$idx]) ? (int)$new_sub_ids[$idx] : 0;
                    } else {
                        // main test
                        $sub_id_to_link = 0;
                    }

                    if ($normal_value !== '') {
                        $bindRef = $stmt_ref->bind_param("iiiiss", $test_id, $sub_id_to_link, $min_age, $max_age, $sex, $normal_value);
                        if ($bindRef === false) { throw new Exception("bind_param failed for ref_ranges: " . $stmt_ref->error); }
                        $stmt_ref->execute();
                    }
                }
                $stmt_ref->close();
            }

            $conn->commit();
            $action = $is_update ? 'updated' : 'added';
            $message = "<div style='color:green;'>Test '{$test_name}' successfully {$action}.</div>";

            header("Location: test_management.php?edit_id={$test_id}&message=" . urlencode($message));
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $message = "<div style='color:red;'>Error saving test: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}
// Check for message passed via redirect
if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Management</title>
    <style>
        .container { padding: 20px; font-family: Arial, sans-serif; display: flex; gap: 20px; }
        .list-panel { flex: 1; max-height: 80vh; overflow-y: auto; border-right: 1px solid #ccc; padding-right: 15px; }
        .form-panel { flex: 3; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="number"], select { padding: 8px; width: 100%; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        .section-header { background-color: #f0f0f0; padding: 10px; margin-top: 20px; border-radius: 5px 5px 0 0; }
        .sub-form { border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 5px; }
        .remove-btn { color: red; cursor: pointer; float: right; }
        .test-list-item { padding: 5px 0; border-bottom: 1px dotted #eee; }
        .test-list-item a { text-decoration: none; color: #333; display: block; }
        .test-list-item a:hover { color: #007bff; }

        .sub-test-row-template > div { padding: 0 5px; }
        .sub-test-row-template input, .sub-test-row-template select { width: 95%; }
        .sub-test-row-template { border: 1px dotted #ccc; padding: 10px 0; margin-bottom: 10px; display: flex; align-items: center; }
        
        /* New Range Styles for better usability */
        .range-header-row div { width: 20%; padding: 0 5px; font-weight: bold; }
        .range-row-template { display: flex; gap: 10px; align-items: center; margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px dotted #ccc; }
        .range-row-template select, .range-row-template input { margin-top: 0; }
        .range-row-template div { padding: 0 5px; }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">

        <div class="list-panel">
            <h2>Test List</h2>
            <input type="text" id="test_search" placeholder="Search test name..." onkeyup="filterTests()">
            <div id="test_list_container" style="margin-top: 10px;">
                <?php foreach ($all_tests as $test): ?>
                    <div class="test-list-item" data-name="<?php echo strtolower($test['test_name']); ?>">
                        <a href="?edit_id=<?php echo $test['test_id']; ?>">
                            <?php echo htmlspecialchars($test['test_name']); ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
            <hr>
            <a href="test_management.php" style="display: block; text-align: center; background: #28a745; color: white; padding: 10px; border-radius: 4px; text-decoration: none;">+ Add New Test</a>
        </div>

        <div class="form-panel">
            <h1><?php echo $is_edit_mode ? 'Edit Test: ' . htmlspecialchars($test_data['test_name'] ?? '') : 'Add New Test Definition'; ?></h1>
            <?php echo $message; ?>

            <form action="" method="post" id="testForm">
                <?php if ($is_edit_mode): ?>
                    <input type="hidden" name="test_id" value="<?php echo $edit_test_id; ?>">
                    <input type="hidden" name="update_test" value="1">
                <?php else: ?>
                    <input type="hidden" name="add_test" value="1">
                <?php endif; ?>

                <div class="section-header"><h2>1. General Test Information</h2></div>
                <div class="sub-form">
                    <div style="display: flex; gap: 20px;">
                        <div style="flex: 2;"><label>Test Name*</label><input type="text" name="test_name" value="<?php echo htmlspecialchars($test_data['test_name'] ?? ''); ?>" required></div>
                        <div style="flex: 1;">
                            <label>Department</label>
                            <select name="test_department_select" id="test_department_select" onchange="toggleInput('test_department')" style="width: 50%; float: left;">
                                <option value="">-- Select Existing --</option>
                                <?php foreach($unique_departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo (($test_data['test_department'] ?? '') == $dept) ? 'selected' : ''; ?>><?php echo htmlspecialchars($dept); ?></option>
                                <?php endforeach; ?>
                                <option value="new_input">-- Enter New --</option>
                            </select>
                            <input type="text" name="test_department_input" id="test_department_input" value="<?php echo htmlspecialchars($test_data['test_department'] ?? ''); ?>" style="width: 48%; float: right; display: none;">
                            <input type="hidden" name="test_department" id="test_department_final" value="<?php echo htmlspecialchars($test_data['test_department'] ?? ''); ?>">
                            <div style="clear: both;"></div>
                        </div>
                        <div style="flex: 1;"><label>Price (₹)*</label><input type="number" name="price" step="0.01" value="<?php echo htmlspecialchars($test_data['price'] ?? ''); ?>" required min="0.01"></div>
                    </div>

                    <div style="display: flex; gap: 20px;">
                        <div style="flex: 1;">
                            <label>Specimen</label>
                            <select name="specimen_select" id="specimen_select" onchange="toggleInput('specimen')" style="width: 50%; float: left;">
                                <option value="">-- Select Existing --</option>
                                <?php foreach($unique_specimens as $spec): ?>
                                    <option value="<?php echo htmlspecialchars($spec); ?>" <?php echo (($test_data['specimen'] ?? '') == $spec) ? 'selected' : ''; ?>><?php echo htmlspecialchars($spec); ?></option>
                                <?php endforeach; ?>
                                <option value="new_input">-- Enter New --</option>
                            </select>
                            <input type="text" name="specimen_input" id="specimen_input" value="<?php echo htmlspecialchars($test_data['specimen'] ?? ''); ?>" style="width: 48%; float: right; display: none;">
                            <input type="hidden" name="specimen" id="specimen_final" value="<?php echo htmlspecialchars($test_data['specimen'] ?? ''); ?>">
                            <div style="clear: both;"></div>
                        </div>

                        <div style="flex: 1;">
                            <label>Container</label>
                            <select name="container_select" id="container_select" onchange="toggleInput('container')" style="width: 50%; float: left;">
                                <option value="">-- Select Existing --</option>
                                <?php foreach($unique_containers as $cont): ?>
                                    <option value="<?php echo htmlspecialchars($cont); ?>" <?php echo (($test_data['container'] ?? '') == $cont) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cont); ?></option>
                                <?php endforeach; ?>
                                <option value="new_input">-- Enter New --</option>
                            </select>
                            <input type="text" name="container_input" id="container_input" value="<?php echo htmlspecialchars($test_data['container'] ?? ''); ?>" style="width: 48%; float: right; display: none;">
                            <input type="hidden" name="container" id="container_final" value="<?php echo htmlspecialchars($test_data['container'] ?? ''); ?>">
                            <div style="clear: both;"></div>
                        </div>

                        <div style="flex: 1;"><label>TAT Time</label><input type="text" name="tat_time" value="<?php echo htmlspecialchars($test_data['tat_time'] ?? ''); ?>"></div>
                    </div>

                    <div style="display: flex; gap: 20px; margin-top: 15px;">
                        <div style="flex: 1;">
                            <label>Unit (e.g., mg/dL)</label>
                            <select name="unit_select" id="unit_select" onchange="toggleInput('unit')" style="width: 50%; float: left;">
                                <option value="">-- Select Existing --</option>
                                <?php foreach($unique_units as $u): ?>
                                    <option value="<?php echo htmlspecialchars($u); ?>" <?php echo (($test_data['unit'] ?? '') == $u) ? 'selected' : ''; ?>><?php echo htmlspecialchars($u); ?></option>
                                <?php endforeach; ?>
                                <option value="new_input">-- Enter New --</option>
                            </select>
                            <input type="text" name="unit_input" id="unit_input" value="<?php echo htmlspecialchars($test_data['unit'] ?? ''); ?>" style="width: 48%; float: right; display: none;">
                            <input type="hidden" name="unit" id="unit_final" value="<?php echo htmlspecialchars($test_data['unit'] ?? ''); ?>">
                            <div style="clear: both;"></div>
                        </div>
                        <div style="flex: 1;">
                            <label>Report Input Type (Default)</label>
                            <select name="report_type" id="report_type" onchange="toggleDecimalOptions(this.value, 'decimal_options', 'decimal_places')">
                                <option value="text" <?php echo (($test_data['report_type'] ?? 'text') == 'text') ? 'selected' : ''; ?>>Text</option>
                                <option value="numeric" <?php echo (($test_data['report_type'] ?? '') == 'numeric') ? 'selected' : ''; ?>>Numeric</option>
                            </select>
                        </div>
                        <div style="flex: 1;" id="decimal_options" style="display:none;">
                            <label>Decimal Places (Default)</label>
                            <input type="number" name="decimal_places" id="decimal_places" value="<?php echo htmlspecialchars($test_data['decimal_places'] ?? '2'); ?>" min="0" max="4">
                        </div>
                    </div>
                </div>

                <div class="section-header"><h2>2. Sub-Tests (Parameters)</h2></div>
                <div class="sub-form">
                    <div id="sub_tests_container">
                        <div style="display: flex; font-weight: bold; padding-bottom: 5px; border-bottom: 2px solid #333; font-size: 0.9em;">
                            <div style="width: 15%;">Name*</div>
                            <div style="width: 15%;">Link Test</div>
                            <div style="width: 15%;">Specimen</div>
                            <div style="width: 15%;">Container</div>
                            <div style="width: 10%;">TAT</div>
                            <div style="width: 10%;">Unit</div>
                            <div style="width: 10%;">Type</div>
                            <div style="width: 5%;">Decimals</div>
                            <div style="width: 5%;"></div>
                        </div>

                        <?php
                            $sub_index = 0;
                            if ($is_edit_mode && !empty($test_data['sub_tests'])):
                        ?>
                            <?php foreach ($test_data['sub_tests'] as $sub_test): ?>
                                <div class="form-group sub-test-row-template" id="sub_row_<?php echo $sub_index; ?>">
                                    <!-- Keep an optional hidden existing id (not used for mapping in this implementation, but kept for future use) -->
                                    <input type="hidden" name="sub_existing_id[]" value="<?php echo (int)$sub_test['sub_test_id']; ?>">
                                    <div style="width: 15%;"><input type="text" name="sub_test_name[]" value="<?php echo htmlspecialchars($sub_test['sub_test_name']); ?>" required></div>
                                    <div style="width: 15%;">
                                        <select name="sub_link_id[]" onchange="linkSubTest(<?php echo $sub_index; ?>, this.value)">
                                            <option value="">-- Manual --</option>
                                            <?php foreach ($main_tests_for_link as $mt): ?>
                                                <option value="<?php echo $mt['test_id']; ?>"><?php echo htmlspecialchars($mt['test_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div style="width: 15%;"><input type="text" name="sub_specimen[]" value="<?php echo htmlspecialchars($sub_test['specimen']); ?>"></div>
                                    <div style="width: 15%;"><input type="text" name="sub_container[]" value="<?php echo htmlspecialchars($sub_test['container']); ?>"></div>
                                    <div style="width: 10%;"><input type="text" name="sub_tat_time[]" value="<?php echo htmlspecialchars($sub_test['tat_time']); ?>"></div>
                                    <div style="width: 10%;"><input type="text" name="sub_unit[]" value="<?php echo htmlspecialchars($sub_test['unit']); ?>"></div>
                                    <div style="width: 10%;">
                                        <select name="sub_report_type[]" onchange="toggleSubDecimal(<?php echo $sub_index; ?>)" id="sub_type_<?php echo $sub_index; ?>">
                                            <option value="text" <?php echo ($sub_test['report_type'] == 'text') ? 'selected' : ''; ?>>Text</option>
                                            <option value="numeric" <?php echo ($sub_test['report_type'] == 'numeric') ? 'selected' : ''; ?>>Numeric</option>
                                        </select>
                                    </div>
                                    <div style="width: 5%;" id="sub_decimal_<?php echo $sub_index; ?>">
                                        <input type="number" name="sub_decimal_places[]" value="<?php echo htmlspecialchars($sub_test['decimal_places']); ?>" min="0" max="4" style="display:<?php echo ($sub_test['report_type'] == 'numeric') ? 'block' : 'none'; ?>">
                                    </div>
                                    <div style="width: 5%;"><button type="button" class="remove-btn" onclick="this.closest('.sub-test-row-template').remove()">X</button></div>
                                </div>
                            <?php $sub_index++; endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <button type="button" onclick="addSubTest()" style="padding: 10px; margin-top: 10px;">+ Add Another Sub-Test</button>
                </div>

                <div class="section-header"><h2>3. Reference Ranges (Normal Values)</h2></div>
                <div class="sub-form">
                    
                    <div class="range-header-row" style="display: flex; margin-bottom: 10px; border-bottom: 1px solid #ccc; padding-bottom: 5px; font-weight: bold; font-size: 0.9em;">
                         <div style="width: 25%; padding-right: 5px;">Parameter</div>
                         <div style="width: 10%;">Min Age</div>
                         <div style="width: 10%;">Max Age</div>
                         <div style="width: 15%;">Sex</div>
                         <div style="width: 35%;">Normal Value / Range</div>
                         <div style="width: 5%;"></div>
                    </div>
                    
                    <div id="ref_ranges_container">
                        <?php
                            $range_index = 0;
                            if ($is_edit_mode && !empty($test_data['ref_ranges'])):
                        ?>
                            <?php foreach ($test_data['ref_ranges'] as $range):
                                // Determine parameter link format 'main' or 'sub_idx_N' where N is index in current sub_tests list
                                $param_name = 'Main Test';
                                $param_link_id = 'main';
                                if (!empty($range['sub_test_id'])) {
                                    // find index of this sub_test_id within current sub_tests
                                    $foundIndex = null;
                                    foreach ($test_data['sub_tests'] as $k => $st) {
                                        if ((int)$st['sub_test_id'] === (int)$range['sub_test_id']) { $foundIndex = $k; break; }
                                    }
                                    if ($foundIndex !== null) {
                                        $param_name = htmlspecialchars($test_data['sub_tests'][$foundIndex]['sub_test_name']);
                                        $param_link_id = 'sub_idx_' . $foundIndex;
                                    } else {
                                        // sub-test deleted (old FK) - show label and default to main
                                        $param_name = 'SUB-TEST (Deleted)';
                                        $param_link_id = 'main';
                                    }
                                }
                            ?>
                                <div class="form-group ref-range-row">
                                    <div class="range-row-template">
                                        <div style="width: 25%; padding-right: 5px;">
                                            <input type="text" readonly value="<?php echo $param_name; ?>" style="background: #f8f8f8; font-weight: bold; width: 100%;">
                                            <input type="hidden" name="ref_range[<?php echo $range_index; ?>][parameter_link]" value="<?php echo $param_link_id; ?>">
                                        </div>
                                        <div style="width: 10%;">
                                            <input type="number" name="ref_range[<?php echo $range_index; ?>][min_age]" value="<?php echo htmlspecialchars($range['min_age']); ?>" min="0" placeholder="Min">
                                        </div>
                                        <div style="width: 10%;">
                                            <input type="number" name="ref_range[<?php echo $range_index; ?>][max_age]" value="<?php echo htmlspecialchars($range['max_age']); ?>" min="0" placeholder="Max">
                                        </div>
                                        <div style="width: 15%;">
                                            <select name="ref_range[<?php echo $range_index; ?>][sex]">
                                                <option value="Any" <?php echo ($range['sex'] == 'Any') ? 'selected' : ''; ?>>Any</option>
                                                <option value="Male" <?php echo ($range['sex'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                                <option value="Female" <?php echo ($range['sex'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                            </select>
                                        </div>
                                        <div style="width: 35%;">
                                            <input type="text" name="ref_range[<?php echo $range_index; ?>][normal_value]" value="<?php echo htmlspecialchars($range['normal_value']); ?>" placeholder="13-17 g/dL or Text Result">
                                        </div>
                                        <div style="width: 5%;">
                                            <button type="button" class="remove-btn" onclick="this.closest('.ref-range-row').remove()">X</button>
                                        </div>
                                    </div>
                                </div>
                            <?php $range_index++; endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <button type="button" onclick="addRefRange()" style="padding: 10px; margin-top: 10px;">+ Add Another Reference Range</button>
                </div>

                <button type="submit" style="width: 100%; padding: 15px; background-color: <?php echo $is_edit_mode ? '#f0ad4e' : '#28a745'; ?>; color: white; border: none; font-size: 1.1em; margin-top: 20px;">
                    <?php echo $is_edit_mode ? 'Update Test Definition' : 'Save Complete Test Definition'; ?>
                </button>
            </form>
        </div>
    </div>

    <script>
        let subIndex = <?php echo $sub_index ?? 0; ?>;
        let refRangeIndex = <?php echo $range_index ?? 0; ?>;
        const mainTestsData = <?php echo $main_tests_json; ?>;
        const currentTestId = <?php echo $edit_test_id ?? 0; ?>;
        const currentTestName = "<?php echo htmlspecialchars($test_data['test_name'] ?? 'New Test', ENT_QUOTES); ?>";

        // Build currentSubTests array as { idx: n, id: sub_test_id, name: '...' }
        const currentSubTests = [
            <?php 
                if (!empty($test_data['sub_tests'])) {
                    foreach ($test_data['sub_tests'] as $k => $sub) {
                        $name_js = htmlspecialchars($sub['sub_test_name'], ENT_QUOTES);
                        echo "{ idx: $k, id: {$sub['sub_test_id']}, name: '{$name_js}' },";
                    }
                }
            ?>
        ];

        // Function to build the Parameter Selection Dropdown for ranges (uses sub_idx_N style)
        function buildParameterOptions() {
            let options = `<select name="ref_range[${refRangeIndex}][parameter_link]" required>`;
            options += `<option value="main"> ${currentTestName} (Main Test)</option>`;

            if (currentSubTests.length > 0) {
                options += '<optgroup label="--- Sub-Tests ---">';
                currentSubTests.forEach(sub => {
                    options += `<option value="sub_idx_${sub.idx}"> ${sub.name}</option>`;
                });
                options += '</optgroup>';
            }
            options += '</select>';
            return options;
        }

        function linkSubTest(index, testId) {
            const row = document.getElementById('sub_row_' + index);
            const data = mainTestsData[testId];
            
            if (!data) {
                // Clear fields if 'Manual' is selected
                row.querySelector('input[name="sub_specimen[]"]').value = '';
                row.querySelector('input[name="sub_container[]"]').value = '';
                row.querySelector('input[name="sub_tat_time[]"]').value = '';
                row.querySelector('input[name="sub_unit[]"]').value = ''; 
                row.querySelector('select[name="sub_report_type[]"]').value = 'text';
                row.querySelector('input[name="sub_decimal_places[]"]').value = '0';
                toggleSubDecimal(index);
                return;
            }
            
            // Populate fields with linked test data
            row.querySelector('input[name="sub_specimen[]"]').value = data.specimen || '';
            row.querySelector('input[name="sub_container[]"]').value = data.container || '';
            row.querySelector('input[name="sub_tat_time[]"]').value = data.tat_time || '';
            row.querySelector('input[name="sub_unit[]"]').value = data.unit || '';
            
            // Set report type and toggle decimal visibility
            const reportTypeSelect = row.querySelector('select[name="sub_report_type[]"]');
            reportTypeSelect.value = data.report_type || 'text';
            toggleSubDecimal(index);
            
            // Set decimal places input
            if (data.report_type === 'numeric') {
                row.querySelector('input[name="sub_decimal_places[]"]').value = data.decimal_places;
            } else {
                row.querySelector('input[name="sub_decimal_places[]"]').value = '0';
            }
        }

        // Toggles between select dropdown and text input for main test options
        function toggleInput(field) {
            const select = document.getElementById(field + '_select');
            const input = document.getElementById(field + '_input');
            
            if (!select || !input) return;

            if (select.value === 'new_input') {
                input.style.display = 'block';
                input.required = true;
            } else {
                input.style.display = 'none';
                input.required = false;
            }
        }
        
        // Toggles decimal input visibility for main test
        function toggleDecimalOptions(type, containerId, inputId) {
            document.getElementById(containerId).style.display = (type === 'numeric') ? 'block' : 'none';
        }

        // Toggles decimal input visibility for sub-tests
        function toggleSubDecimal(index) {
            const row = document.getElementById('sub_row_' + index);
            if (!row) return;
            const type = row.querySelector('select[name="sub_report_type[]"]').value;
            const decimalInput = row.querySelector('input[name="sub_decimal_places[]"]');
            if (decimalInput) decimalInput.style.display = (type === 'numeric') ? 'block' : 'none';
        }

        function addSubTest() {
            const container = document.getElementById('sub_tests_container');
            const newDiv = document.createElement('div');
            newDiv.className = 'form-group sub-test-row-template';
            newDiv.id = 'sub_row_' + subIndex;
            
            let linkOptions = `<select name="sub_link_id[]" onchange="linkSubTest(${subIndex}, this.value)">`;
            linkOptions += '<option value="">-- Manual --</option>';
            <?php foreach ($main_tests_for_link as $mt): ?>
                linkOptions += '<option value="<?php echo $mt['test_id']; ?>"><?php echo htmlspecialchars($mt['test_name']); ?></option>';
            <?php endforeach; ?>
            linkOptions += '</select>';

            newDiv.innerHTML = `
                <div style="display: flex; width: 100%;">
                    <input type="hidden" name="sub_existing_id[]" value="">
                    <div style="width: 15%;"><input type="text" name="sub_test_name[]" placeholder="Name" required></div>
                    <div style="width: 15%;">${linkOptions}</div>
                    <div style="width: 15%;"><input type="text" name="sub_specimen[]" placeholder="Specimen"></div>
                    <div style="width: 15%;"><input type="text" name="sub_container[]" placeholder="Container"></div>
                    <div style="width: 10%;"><input type="text" name="sub_tat_time[]" placeholder="TAT"></div>
                    <div style="width: 10%;"><input type="text" name="sub_unit[]" placeholder="Unit"></div>
                    <div style="width: 10%;">
                        <select name="sub_report_type[]" onchange="toggleSubDecimal(${subIndex})" id="sub_type_${subIndex}">
                            <option value="text">Text</option>
                            <option value="numeric">Numeric</option>
                        </select>
                    </div>
                    <div style="width: 5%;" id="sub_decimal_${subIndex}">
                        <input type="number" name="sub_decimal_places[]" value="0" min="0" max="4" style="display:none;">
                    </div>
                    <div style="width: 5%;"><button type="button" class="remove-btn" onclick="this.closest('.sub-test-row-template').remove()">X</button></div>
                </div>
            `;
            container.appendChild(newDiv);
            subIndex++;
        }

        function addRefRange() {
            const container = document.getElementById('ref_ranges_container');
            const newDiv = document.createElement('div');
            newDiv.className = 'form-group ref-range-row';

            if (currentTestId === 0) { // If adding a new test, disable range creation immediately
                alert("Please save the main test details before defining ranges.");
                return;
            }

            // Build parameter selection options (Main test + current sub-tests)
            const paramSelect = buildParameterOptions();

            newDiv.innerHTML = `
                <div class="range-row-template">
                    <div style="width: 25%; padding-right: 5px;">
                        ${paramSelect}
                    </div>
                    <div style="width: 10%;">
                        <input type="number" name="ref_range[${refRangeIndex}][min_age]" value="0" min="0" placeholder="Min">
                    </div>
                    <div style="width: 10%;">
                        <input type="number" name="ref_range[${refRangeIndex}][max_age]" value="150" min="0" placeholder="Max">
                    </div>
                    <div style="width: 15%;">
                        <select name="ref_range[${refRangeIndex}][sex]">
                            <option value="Any">Any</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div style="width: 35%;">
                        <input type="text" name="ref_range[${refRangeIndex}][normal_value]" placeholder="13-17 g/dL or Text Result">
                    </div>
                    <div style="width: 5%;">
                        <button type="button" class="remove-btn" onclick="this.closest('.ref-range-row').remove()">X</button>
                    </div>
                </div>
            `;
            container.appendChild(newDiv);
            refRangeIndex++;
        }

        function filterTests() {
            const query = $('#test_search').val().toLowerCase();
            $('.test-list-item').each(function() {
                const testName = $(this).data('name');
                if (testName.includes(query)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }

        // Initialize display states on load
        document.addEventListener('DOMContentLoaded', () => {
            // Main test decimal options
            toggleDecimalOptions(document.getElementById('report_type').value, 'decimal_options', 'decimal_places');

            // Sub-test decimal options
            for(let i = 0; i < subIndex; i++) {
                const subTypeSelect = document.getElementById('sub_type_' + i);
                if (subTypeSelect) {
                    toggleSubDecimal(i);
                }
            }

            // Initial toggle for existing main test select/input fields
            const fields = ['test_department', 'specimen', 'container', 'unit'];
            fields.forEach(field => {
                const select = document.getElementById(field + '_select');
                if (select) {
                    toggleInput(field);
                    // Ensure the hidden field starts with the selected/default value
                    if (select.value !== 'new_input') {
                        document.getElementById(field + '_final').value = select.value;
                    } else if (document.getElementById(field + '_input').value !== '') {
                         // If 'new_input' is selected and has a value on load (e.g., from an edit pre-fill)
                         document.getElementById(field + '_final').value = document.getElementById(field + '_input').value;
                    }
                }
            });
        });

        // Set final value before submission (FIX for Department/Specimen/Container/Unit update)
        document.getElementById('testForm').addEventListener('submit', function(e) {
            const fields = ['test_department', 'specimen', 'container', 'unit'];
            fields.forEach(field => {
                const select = document.getElementById(field + '_select');
                const input = document.getElementById(field + '_input');
                const final = document.getElementById(field + '_final');

                // If 'Enter New' is selected, use the input box value
                if (select && select.value === 'new_input') {
                    final.value = input ? input.value : '';
                } else if (select) {
                    // Otherwise, use the select box value
                    final.value = select.value;
                }
                // Ensure the hidden field is the one submitting the final value
                final.name = field;
            });
        });
    </script>
</body>
</html>
