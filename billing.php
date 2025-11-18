<?php
session_start();
require_once 'functions.php';
require_permission('access_billing');
require_once 'db_connect.php'; 

$message = '';
// Fetch required reference data
$doctors = $conn->query("SELECT doctor_id, doctor_name FROM doctors")->fetch_all(MYSQLI_ASSOC);
$hospitals = $conn->query("SELECT hospital_id, hospital_name FROM hospitals")->fetch_all(MYSQLI_ASSOC);
$company_info = $conn->query("SELECT company_name, address, phone_no FROM company LIMIT 1")->fetch_assoc() ?? ['company_name' => 'LAB MANAGEMENT SYSTEM', 'address' => 'Not Set', 'phone_no' => 'Not Set'];

$bill_details_for_modal = null; // Initialize modal data variable

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_billing'])) {
    // --- Data Sanitization and Validation ---
    $pt_name = $conn->real_escape_string($_POST['pt_name']);
    $age = (int)$_POST['age'];
    $sex = $conn->real_escape_string($_POST['sex']);
    $mobile_no = $conn->real_escape_string($_POST['mobile_no']);
    $refer_type = $conn->real_escape_string($_POST['refer_type']);
    $refer_id = isset($_POST['refer_id']) ? (int)$_POST['refer_id'] : 0;
    
    $selected_test_ids = $_POST['test_id'] ?? [];
    $net_amount = (float)$_POST['hidden_net_amount'];
    $discount = (float)$_POST['discount'];
    $total_amount = (float)$_POST['hidden_total_amount']; 

    // Payment details: Retrieve only selected/visible fields
    $pay_cash = (float)($_POST['payment_amount']['Cash'] ?? 0);
    $pay_card = (float)($_POST['payment_amount']['Card'] ?? 0);
    $pay_upi = (float)($_POST['payment_amount']['UPI'] ?? 0);
    $total_paid = $pay_cash + $pay_card + $pay_upi;

    if ($total_paid < $net_amount - 0.01 || $total_paid > $net_amount + 0.01) { 
        $message = "<div style='color:red;'>Error: Total paid amount (₹".number_format($total_paid, 2).") does not match Net Amount (₹".number_format($net_amount, 2).").</div>";
    } elseif (empty($selected_test_ids)) {
         $message = "<div style='color:red;'>Error: Please select at least one test.</div>";
    } else {
        $conn->begin_transaction();
        try {
            // 1. Insert/Find Patient
            $stmt = $conn->prepare("INSERT INTO patients (pt_name, age, sex, mobile_no) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("siss", $pt_name, $age, $sex, $mobile_no);
            $stmt->execute();
            $pt_id = $conn->insert_id;
            $stmt->close();

            // 2. Insert Billing
            $stmt = $conn->prepare("INSERT INTO billing (pt_id, refer_type, refer_id, total_amount, discount, net_amount) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isiddi", $pt_id, $refer_type, $refer_id, $total_amount, $discount, $net_amount);
            $stmt->execute();
            $bill_id = $conn->insert_id;
            $stmt->close();
            
            // 3. Insert Bill Tests
            $test_prices = $_POST['test_price'] ?? [];
            $tests_array = [];
            $test_names = $_POST['test_name_h'] ?? [];

            $stmt_bill_test = $conn->prepare("INSERT INTO bill_tests (bill_id, test_id, test_price) VALUES (?, ?, ?)");
            foreach ($selected_test_ids as $index => $test_id) {
                $price = (float)($test_prices[$index] ?? 0.00);
                $name = $test_names[$index] ?? 'Unknown Test';
                
                $stmt_bill_test->bind_param("iid", $bill_id, $test_id, $price);
                $stmt_bill_test->execute();
                
                $tests_array[] = ['name' => $name, 'price' => $price];
            }
            $stmt_bill_test->close();

            // 4. Insert Payments
            $payment_modes_for_modal = [];
            $stmt_pay = $conn->prepare("INSERT INTO payments (bill_id, payment_method, amount) VALUES (?, ?, ?)");
            if ($pay_cash > 0) {
                $method = 'Cash';
                $stmt_pay->bind_param("isd", $bill_id, $method, $pay_cash); $stmt_pay->execute();
                $payment_modes_for_modal[] = ['method' => $method, 'amount' => $pay_cash];
            }
            if ($pay_card > 0) {
                $method = 'Card';
                $stmt_pay->bind_param("isd", $bill_id, $method, $pay_card); $stmt_pay->execute();
                $payment_modes_for_modal[] = ['method' => $method, 'amount' => $pay_card];
            }
            if ($pay_upi > 0) {
                $method = 'UPI';
                $stmt_pay->bind_param("isd", $bill_id, $method, $pay_upi); $stmt_pay->execute();
                $payment_modes_for_modal[] = ['method' => $method, 'amount' => $pay_upi];
            }
            $stmt_pay->close();

            $conn->commit();
            
            // --- NEW: Inject button HTML into the success message ---
            $print_button_html = '<a href="print_bill_copy.php?bill_id=' . $bill_id . '" target="_blank" class="btn-action" style="margin-left: 20px; padding: 5px 15px; text-decoration: none;">Print Bill Copy</a>';

            $message = "<div style='display: flex; align-items: center; justify-content: space-between; padding: 10px; border: 1px solid green; background-color: #d4edda; border-radius: 4px;'>" .
                       "<div>Bill generated successfully! Bill ID: <strong>{$bill_id}</strong>. Total: ₹".number_format($net_amount, 2)."</div>" .
                       "{$print_button_html}</div>";
            // --- END NEW FIX ---
            
            // --- Set Modal Data for Bill Print Popup (Optional, but kept for function definition) ---
            $bill_details_for_modal = [
                'company_name' => $company_info['company_name'] ?? 'LAB MANAGEMENT SYSTEM',
                'company_address' => $company_info['address'] ?? 'N/A',
                'company_phone' => $company_info['phone_no'] ?? 'N/A',
                'bill_id' => $bill_id,
                'bill_date' => date('Y-m-d H:i:s'),
                'pt_name' => $pt_name,
                'age' => $age,
                'sex' => $sex,
                'tests' => $tests_array,
                'total_amount' => $total_amount,
                'discount' => $discount,
                'net_amount' => $net_amount,
                'payments' => $payment_modes_for_modal,
                'referral' => ($refer_type != 'Self') ? $refer_type : ''
            ];

        } catch (Exception $e) {
            $conn->rollback();
            $message = "<div style='color:red;'>Billing failed: " . $e->getMessage() . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Billing Entry</title>
    <style>
        /* Base styles */
        .container { padding: 20px; font-family: Arial, sans-serif; }
        .section { border: 1px solid #ccc; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        label { display: block; margin-top: 10px; font-weight: bold; }
        input[type="text"], input[type="number"], select { padding: 8px; margin-top: 5px; width: 100%; box-sizing: border-box; }
        .total-box { margin-top: 20px; padding: 10px; background-color: #e9ecef; border-radius: 5px; }
        
        /* New Payment Layout */
        .payment-options { margin-top: 15px; border: 1px solid #ddd; padding: 10px; border-radius: 4px; }
        .payment-input-group { display: flex; align-items: center; margin-bottom: 10px; }
        .payment-input-group label { margin-right: 15px; width: 100px; font-weight: normal; margin-top: 0; }
        .payment-input-group input[type="number"] { width: 150px; margin-top: 0; }
        .payment-status-message { margin-top: 10px; padding: 5px; text-align: center; font-weight: bold; border-radius: 4px; }
        .status-ok { background-color: #d4edda; color: green; }
        .status-error { background-color: #f8d7da; color: red; }
        
        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1001; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 450px; border-radius: 8px; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; }
        .close:hover, .close:focus { color: black; text-decoration: none; cursor: pointer; }
        .btn-primary { background-color: #28aa45; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; transition: background-color 0.2s; font-weight: bold; }
        .btn-action { background-color: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; transition: background-color 0.2s; }
        
        /* Print Specific Styles */
        .bill-header, .bill-footer { text-align: center; margin-bottom: 15px; }
        .bill-details table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .bill-details th, .bill-details td { padding: 5px 0; font-size: 0.9em;}
        .bill-details th { text-align: left; border-bottom: 1px solid #333; }
        .bill-details td { border-bottom: 1px dotted #ccc; }
        .summary-row td { border-top: 1px solid #333; font-weight: bold; }
        .payment-list { list-style: none; padding: 0; }
        .payment-list li { margin-bottom: 2px; }

        @media print {
            body * { visibility: hidden; }
            .modal-content, .modal-content * { visibility: visible; }
            .modal-content {
                position: absolute;
                left: 0;
                top: 0;
                margin: 0;
                padding: 0;
                width: 100%;
                border: none;
                box-shadow: none;
            }
            .no-print { display: none !important; }
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h1>Patient Billing & Invoice</h1>
        <?php echo $message; ?>

        <form action="" method="post" id="billingForm">
            <div class="section">
                <h2>Patient & Referral Details</h2>
                <div style="display: flex; gap: 20px;">
                    <div style="flex: 1;">
                        <label>Patient Name</label><input type="text" name="pt_name" value="<?php echo htmlspecialchars($_POST['pt_name'] ?? ''); ?>" required>
                        <label>Age</label><input type="number" name="age" value="<?php echo htmlspecialchars($_POST['age'] ?? ''); ?>" required min="1">
                        <label>Sex</label>
                        <select name="sex" required>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div style="flex: 1;">
                        <label>Mobile No</label><input type="text" name="mobile_no" value="<?php echo htmlspecialchars($_POST['mobile_no'] ?? ''); ?>" required>
                        <label>Refer By</label>
                        <select name="refer_type" id="refer_type" required onchange="toggleReferral()">
                            <option value="Self">Self</option>
                            <option value="Doctor">Doctor</option>
                            <option value="Hospital">Hospital</option>
                        </select>
                        
                        <div id="doctor_select" style="display:none;">
                            <label>Doctor Name</label>
                            <select name="refer_id">
                                <option value="0">-- Select Doctor --</option>
                                <?php foreach($doctors as $doc): ?>
                                    <option value="<?php echo $doc['doctor_id']; ?>"><?php echo htmlspecialchars($doc['doctor_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="hospital_select" style="display:none;">
                            <label>Hospital Name</label>
                            <select name="refer_id">
                                <option value="0">-- Select Hospital --</option>
                                <?php foreach($hospitals as $hosp): ?>
                                    <option value="<?php echo $hosp['hospital_id']; ?>"><?php echo htmlspecialchars($hosp['hospital_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="section">
                <h2>Test Selection</h2>
                <label>Search Test</label>
                <input type="text" id="test_search_input" placeholder="Type test name to search..." autocomplete="off">

                <div id="test_suggestions_container" style="border: 1px solid #ddd; max-height: 200px; overflow-y: auto; margin-top: 5px; background: white;">
                    <p style="padding: 10px; color: #666;">Start typing to search for tests.</p>
                </div>

                <h3 style="margin-top: 20px;">Selected Tests</h3>
                <div id="selected_tests_container" style="border: 1px solid #007bff; padding: 10px; min-height: 50px;">
                    </div>
            </div>

            <div class="section">
                <h2>Summary & Payment</h2>
                <div class="total-box">
                    <p>Total Test Amount: ₹ <span id="display_total_amount">0.00</span></p>
                    <label>Discount (-)</label>
                    <input type="number" name="discount" id="discount" value="0.00" step="0.01" oninput="calculateTotal()">
                    <p style="font-weight: bold; font-size: 1.2em;">Net Amount Payable: ₹ <span id="display_net_amount">0.00</span></p>
                    
                    <input type="hidden" name="hidden_total_amount" id="hidden_total_amount" value="0.00">
                    <input type="hidden" name="hidden_net_amount" id="hidden_net_amount" value="0.00">
                </div>
                
                <div class="payment-options">
                    <p style="font-weight: bold; margin-bottom: 10px;">Select Payment Methods:</p>
                    
                    <div style="display: flex; gap: 20px; margin-bottom: 15px;">
                        <input type="checkbox" id="check_cash" onclick="togglePaymentField('Cash')"> <label for="check_cash" style="width: auto;">Cash</label>
                        <input type="checkbox" id="check_card" onclick="togglePaymentField('Card')"> <label for="check_card" style="width: auto;">Card</label>
                        <input type="checkbox" id="check_upi" onclick="togglePaymentField('UPI')"> <label for="check_upi" style="width: auto;">UPI</label>
                    </div>

                    <div id="payment_inputs_container">
                        <div class="payment-input-group" id="input_cash" style="display: none;">
                            <label>Cash Amount (₹)</label>
                            <input type="number" name="payment_amount[Cash]" value="0.00" step="0.01" oninput="validatePayment()">
                        </div>
                        <div class="payment-input-group" id="input_card" style="display: none;">
                            <label>Card Amount (₹)</label>
                            <input type="number" name="payment_amount[Card]" value="0.00" step="0.01" oninput="validatePayment()">
                        </div>
                        <div class="payment-input-group" id="input_upi" style="display: none;">
                            <label>UPI Amount (₹)</label>
                            <input type="number" name="payment_amount[UPI]" value="0.00" step="0.01" oninput="validatePayment()">
                        </div>
                    </div>
                </div>

                <div class="payment-status-message" id="payment_status">
                    Please select tests.
                </div>

                <button type="submit" name="submit_billing" id="submit_button" class="btn-primary" style="width: 100%; margin-top: 20px;" disabled>
                    Generate Bill
                </button>
            </div>
        </form>
    </div>

    <div id="billModal" class="modal">
        <div class="modal-content">
            <span class="close no-print">&times;</span>
            <div id="bill_content">
                </div>
            <div class="no-print" style="text-align: center; margin-top: 15px;">
                <button onclick="window.print()" class="btn-primary" style="margin-right: 10px;">Print Preview</button>
                <a id="dedicated_print_link" class="btn-action" href="#" target="_blank">Print Dedicated Copy</a>
            </div>
        </div>
    </div>


    <script>
        let selectedTests = {}; 

        // -----------------------------------------------------
        // MODAL LOGIC
        // -----------------------------------------------------

        const modal = document.getElementById("billModal");
        const closeModal = document.getElementsByClassName("close")[0];
        
        if (closeModal) {
            closeModal.onclick = function() {
                modal.style.display = "none";
            }
        }
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }

        function showBillModal(details) {
            const container = document.getElementById('bill_content');
            
            let testsHtml = details.tests.map((test, index) => 
                `<tr>
                    <td>${index + 1}.</td>
                    <td>${test.name}</td>
                    <td style="text-align: right;">${test.price.toFixed(2)}</td>
                </tr>`
            ).join('');

            let paymentListHtml = details.payments.map(pm => 
                `<li><strong>${pm.method}:</strong> ₹ ${pm.amount.toFixed(2)}</li>`
            ).join('');


            const content = `
                <div class="bill-header">
                    <h2>${details.company_name}</h2>
                    <p>${details.company_address} | Phone: ${details.company_phone}</p>
                    <h3 style="border-bottom: 1px solid #333; padding-bottom: 5px;">TAX INVOICE</h3>
                </div>
                
                <div class="invoice-meta" style="display: flex; justify-content: space-between; font-size: 0.9em; margin-bottom: 15px;">
                    <div>
                        <strong>Bill No:</strong> ${details.bill_id}<br>
                        <strong>Date:</strong> ${new Date(details.bill_date).toLocaleDateString()}
                    </div>
                    <div>
                        <strong>Patient:</strong> ${details.pt_name}<br>
                        <strong>Age/Sex:</strong> ${details.age} / ${details.sex}<br>
                        <strong>Ref. By:</strong> ${details.referral || 'Self'}
                    </div>
                </div>

                <div class="bill-details">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 5%;">#</th>
                                <th style="width: 60%;">Test Description</th>
                                <th style="width: 15%; text-align: right;">Price (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${testsHtml}
                            <tr><td colspan="3" style="height: 10px;"></td></tr>
                            <tr class="summary-row"><td colspan="2" style="text-align: right; border: none; font-weight: normal;">SUB TOTAL:</td><td style="text-align: right;">${details.total_amount.toFixed(2)}</td></tr>
                            <tr class="summary-row"><td colspan="2" style="text-align: right; border: none; font-weight: normal;">DISCOUNT:</td><td style="text-align: right;">- ${details.discount.toFixed(2)}</td></tr>
                            <tr class="summary-row"><td colspan="2" style="text-align: right;">NET AMOUNT:</td><td style="text-align: right;">${details.net_amount.toFixed(2)}</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="invoice-footer" style="margin-top: 20px; font-size: 0.9em;">
                    <p style="margin-bottom: 5px;"><strong>Payment Mode(s):</strong></p>
                    <ul class="payment-list">${paymentListHtml}</ul>
                    <p>Thank you for choosing us.</p>
                </div>
            `;
            container.innerHTML = content;
            
            // --- Set dedicated print link ---
            document.getElementById('dedicated_print_link').href = `print_bill_copy.php?bill_id=${details.bill_id}`;
            
            modal.style.display = "block";
        }


        // -----------------------------------------------------
        // DYNAMIC PAYMENT FIELD TOGGLE
        // -----------------------------------------------------

        function togglePaymentField(method) {
            const inputDiv = document.getElementById('input_' + method.toLowerCase());
            const checkbox = document.getElementById('check_' + method.toLowerCase());
            const inputField = inputDiv.querySelector('input');
            
            if (checkbox.checked) {
                inputDiv.style.display = 'flex';
                
                // Auto-fill the remaining amount into the newly checked box
                const netAmount = parseFloat($('#hidden_net_amount').val()) || 0;
                const totalPaid = calculateTotalPaid(method); // Calculate total paid *excluding* the current field
                const remaining = Math.max(0, netAmount - totalPaid);
                
                // If this is the only checked box, or if remaining amount is positive, fill it.
                if (inputField.value === '0.00' || remaining > 0) {
                     inputField.value = remaining.toFixed(2);
                }
            } else {
                inputDiv.style.display = 'none';
                inputField.value = '0.00'; // Reset amount when field is hidden
            }
            validatePayment();
        }
        
        function calculateTotalPaid(excludedMethod = null) {
            let total = 0;
            const methods = ['Cash', 'Card', 'UPI'];
            
            methods.forEach(method => {
                const inputField = document.querySelector(`#input_${method.toLowerCase()} input`);
                if (document.getElementById(`check_${method.toLowerCase()}`).checked && inputField && method !== excludedMethod) {
                    total += parseFloat(inputField.value) || 0;
                }
            });
            return total;
        }

        // -----------------------------------------------------
        // CORE CALCULATION AND VALIDATION LOGIC
        // -----------------------------------------------------

        function calculateTotal() {
            let total = 0;
            
            for (const id in selectedTests) {
                total += selectedTests[id].price;
            }

            const discount = parseFloat(document.getElementById('discount').value) || 0;
            const netAmount = Math.max(0, total - discount);

            document.getElementById('display_total_amount').textContent = total.toFixed(2);
            document.getElementById('hidden_total_amount').value = total.toFixed(2);
            
            document.getElementById('display_net_amount').textContent = netAmount.toFixed(2);
            document.getElementById('hidden_net_amount').value = netAmount.toFixed(2);
            
            // Re-run validation
            validatePayment();
        }

        function validatePayment() {
            const netAmount = parseFloat($('#hidden_net_amount').val()) || 0;
            const totalPaid = calculateTotalPaid();
            const statusDiv = $('#payment_status');
            const submitBtn = $('#submit_button');

            const hasTests = Object.keys(selectedTests).length > 0;
            const hasCheckedPayments = $('#payment_inputs_container input[type="number"]:visible').length > 0;

            if (!hasTests) {
                 statusDiv.removeClass().addClass('payment-status-message status-warn').text('Please select tests first.');
                 submitBtn.prop('disabled', true);
                 return;
            }

            if (netAmount === 0) {
                 statusDiv.removeClass().addClass('payment-status-message status-ok').text('No payment required (Net Amount is zero).');
                 submitBtn.prop('disabled', false);
                 return;
            } 
            
            if (totalPaid === 0 && hasCheckedPayments) {
                 statusDiv.removeClass().addClass('payment-status-message status-error').text('Payment required: ₹' + netAmount.toFixed(2) + '. Paid: ₹0.00');
                 submitBtn.prop('disabled', true);
                 return;
            }

            if (Math.abs(totalPaid - netAmount) < 0.01) {
                statusDiv.removeClass().addClass('payment-status-message status-ok').text('Total Paid: ₹' + totalPaid.toFixed(2) + ' (MATCHES Net Amount)');
                submitBtn.prop('disabled', false);
            } else if (totalPaid > netAmount) {
                statusDiv.removeClass().addClass('payment-status-message status-error').text('Total Paid: ₹' + totalPaid.toFixed(2) + ' (OVERPAID)');
                submitBtn.prop('disabled', true);
            } else {
                const remaining = netAmount - totalPaid;
                statusDiv.removeClass().addClass('payment-status-message status-warn').text('Total Paid: ₹' + totalPaid.toFixed(2) + '. Remaining: ₹' + remaining.toFixed(2));
                submitBtn.prop('disabled', true);
            }
        }

        // -----------------------------------------------------
        // TEST SELECTION / AJAX LOGIC (Adapted jQuery from previous steps)
        // -----------------------------------------------------

        $('#test_search_input').on('keyup', function() {
            const query = $(this).val();
            const suggestionsContainer = $('#test_suggestions_container');

            if (query.length < 1) {
                suggestionsContainer.html('<p style="padding: 10px; color: #666;">Start typing to search for tests.</p>');
                return;
            }

            $.ajax({
                url: 'fetch_tests.php', 
                method: 'GET',
                data: { q: query },
                dataType: 'json',
                success: function(data) {
                    suggestionsContainer.empty();
                    if (data.length === 0) {
                        suggestionsContainer.html('<p style="padding: 10px; color: red;">No tests found matching "'+query+'"</p>');
                        return;
                    }

                    $.each(data, function(index, test) {
                        const isSelected = selectedTests.hasOwnProperty(test.id);
                        const itemClass = isSelected ? 'selected-item' : 'suggestion-item';
                        
                        const item = $('<div>')
                            .addClass(itemClass)
                            .css({
                                'padding': '8px 10px', 
                                'cursor': isSelected ? 'default' : 'pointer',
                                'border-bottom': '1px dotted #eee',
                                'display': 'flex',
                                'justify-content': 'space-between',
                                'background-color': isSelected ? '#e0f7fa' : 'white'
                            })
                            .html(`<span>${test.name}</span> <span>Price: ₹ ${test.price.toFixed(2)}</span>`);
                        
                        if (!isSelected) {
                            item.on('click', function() {
                                addTestToSelectedList(test.id, test.name, test.price);
                                $('#test_search_input').val('').keyup();
                            });
                        }
                        suggestionsContainer.append(item);
                    });
                },
                error: function() {
                    suggestionsContainer.html('<p style="padding: 10px; color: red;">Error fetching data.</p>');
                }
            });
        });

        function addTestToSelectedList(id, name, price) {
            if (selectedTests.hasOwnProperty(id)) return; 

            selectedTests[id] = { name: name, price: price };
            const container = $('#selected_tests_container');
            
            const selectedItem = $('<div>')
                .attr('data-test-id', id)
                .css({
                    'padding': '5px 0',
                    'border-bottom': '1px solid #f0f0f0',
                    'display': 'flex',
                    'justify-content': 'space-between',
                    'align-items': 'center'
                })
                .html(`
                    <input type="hidden" name="test_id[]" value="${id}">
                    <input type="hidden" name="test_price[]" value="${price.toFixed(2)}">
                    <input type="hidden" name="test_name_h[]" value="${name}">

                    <span>✅ ${name} (₹ ${price.toFixed(2)})</span>
                    <button type="button" class="remove-test-btn btn-danger" data-id="${id}" style="padding: 3px 8px; font-size: 0.8em;">Remove</button>
                `);

            container.append(selectedItem);
            calculateTotal(); 

            selectedItem.find('.remove-test-btn').on('click', function() {
                removeTestFromSelectedList(id);
            });
        }

        function removeTestFromSelectedList(id) {
            delete selectedTests[id];
            $(`#selected_tests_container div[data-test-id="${id}"]`).remove();
            calculateTotal(); 
            $('#test_search_input').keyup();
        }

        // -----------------------------------------------------
        // INITIALIZATION
        // -----------------------------------------------------
        document.addEventListener('DOMContentLoaded', () => {
            toggleReferral();
            calculateTotal(); 
            
            // Check the Cash box by default on load
            document.getElementById('check_cash').checked = true;
            togglePaymentField('Cash'); 
        });
    </script>
</body>
</html>