<?php
require_once 'includes/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch Order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$order = $stmt->fetch();

if (!$order) {
    setFlash('error', 'Order requested does not exist.');
    redirect('orders.php');
}

// Fetch Items
$itemsStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = :order_id");
$itemsStmt->execute([':order_id' => $id]);
$items = $itemsStmt->fetchAll();

// Fetch Active Products
$allProducts = $pdo->query("SELECT * FROM products WHERE status = 1 ORDER BY product_name ASC")->fetchAll();

$errors = [];

// Default GST values if missing
$currentGstPercent = isset($order['gst_percent']) && $order['gst_percent'] > 0 ? (float)$order['gst_percent'] : 18.00;
$isGstEnabled = ($order['gst_amount'] > 0 || !isset($order['gst_amount']));

// Handle Form Update & Mail Dispatch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order'])) {
    $customer_name  = sanitize($_POST['customer_name'] ?? '');
    $phone          = sanitize($_POST['phone'] ?? '');
    $email          = sanitize($_POST['email'] ?? '');
    $address        = sanitize($_POST['address'] ?? '');
    $city           = sanitize($_POST['city'] ?? '');
    $state          = sanitize($_POST['state'] ?? '');
    $pincode        = sanitize($_POST['pincode'] ?? '');
    $notes          = sanitize($_POST['notes'] ?? '');
    $order_status   = sanitize($_POST['order_status'] ?? 'Pending');
    $apply_gst      = isset($_POST['apply_gst']) ? true : false;
    $gst_percent    = $apply_gst ? (float)($_POST['gst_percent'] ?? 18.00) : 0.00;
    $quantities     = $_POST['quantities'] ?? [];
    $delete_items   = $_POST['delete_items'] ?? [];
    $new_product    = (int)($_POST['new_product_id'] ?? 0);
    $new_qty        = (int)($_POST['new_product_qty'] ?? 0);
    $send_email     = isset($_POST['send_email']) ? true : false;

    if (empty($customer_name)) $errors[] = "Customer Name is required.";
    if (empty($phone)) $errors[] = "Phone is required.";

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1. Process Existing Line Items & Deletions
            $itemsSubtotal = 0;
            foreach ($quantities as $itemId => $qty) {
                $qty = (int)$qty;
                if (in_array($itemId, $delete_items) || $qty <= 0) {
                    $delItem = $pdo->prepare("DELETE FROM order_items WHERE id = :id AND order_id = :order_id");
                    $delItem->execute([':id' => $itemId, ':order_id' => $id]);
                } else {
                    $pGet = $pdo->prepare("SELECT price FROM order_items WHERE id = :id");
                    $pGet->execute([':id' => $itemId]);
                    $unitPrice = (float)$pGet->fetchColumn();
                    $subtotal = $unitPrice * $qty;
                    $itemsSubtotal += $subtotal;

                    $uItem = $pdo->prepare("UPDATE order_items SET quantity = :qty, subtotal = :subtotal WHERE id = :id");
                    $uItem->execute([':qty' => $qty, ':subtotal' => $subtotal, ':id' => $itemId]);
                }
            }

            // 2. Add New Product
            if ($new_product > 0 && $new_qty > 0) {
                $pStmt = $pdo->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
                $pStmt->execute([':id' => $new_product]);
                $pData = $pStmt->fetch();

                if ($pData) {
                    $subtotal = $pData['price'] * $new_qty;
                    $itemsSubtotal += $subtotal;

                    $addItem = $pdo->prepare("
                        INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal)
                        VALUES (:order_id, :product_id, :product_name, :price, :quantity, :subtotal)
                    ");
                    $addItem->execute([
                        ':order_id'     => $id,
                        ':product_id'   => $pData['id'],
                        ':product_name' => $pData['product_name'],
                        ':price'        => $pData['price'],
                        ':quantity'     => $new_qty,
                        ':subtotal'     => $subtotal
                    ]);
                }
            }

            // 3. GST Calculation
            $gstAmount = $apply_gst ? ($itemsSubtotal * ($gst_percent / 100)) : 0.00;
            $grandTotal = $itemsSubtotal + $gstAmount;

            // 4. Update Order Table
            $uOrder = $pdo->prepare("
                UPDATE orders 
                SET customer_name = :name, phone = :phone, email = :email, address = :address, 
                    city = :city, state = :state, pincode = :pincode, notes = :notes, 
                    order_status = :status, subtotal_amount = :subtotal_amount, 
                    gst_percent = :gst_percent, gst_amount = :gst_amount, total_amount = :total
                WHERE id = :id
            ");
            $uOrder->execute([
                ':name'            => $customer_name,
                ':phone'           => $phone,
                ':email'           => $email,
                ':address'         => $address,
                ':city'            => $city,
                ':state'           => $state,
                ':pincode'         => $pincode,
                ':notes'           => $notes,
                ':status'          => $order_status,
                ':subtotal_amount' => $itemsSubtotal,
                ':gst_percent'     => $gst_percent,
                ':gst_amount'      => $gstAmount,
                ':total'           => $grandTotal,
                ':id'              => $id
            ]);

            $pdo->commit();

            // 5. Send Mail
            if ($send_email && !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $latestItemsStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = :order_id");
                $latestItemsStmt->execute([':order_id' => $id]);
                $updatedItems = $latestItemsStmt->fetchAll();

                $subject = "Updated Order Quotation - #" . $order['order_no'];
                $message = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; color: #333; }
                        .container { padding: 20px; border: 1px solid #ddd; max-width: 600px; margin: 0 auto; }
                        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f4f4f4; }
                        .total { font-size: 16px; font-weight: bold; color: #e53935; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <h2>Updated Quotation Details</h2>
                        <p>Dear <strong>" . htmlspecialchars($customer_name) . "</strong>,</p>
                        <p>Your quotation order <strong>#" . htmlspecialchars($order['order_no']) . "</strong> has been updated. Details below:</p>
                        <table>
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Price</th>
                                    <th>Qty</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>";
                            
                foreach ($updatedItems as $it) {
                    $message .= "
                                <tr>
                                    <td>" . htmlspecialchars($it['product_name']) . "</td>
                                    <td>₹" . number_format($it['price'], 2) . "</td>
                                    <td>" . $it['quantity'] . "</td>
                                    <td>₹" . number_format($it['subtotal'], 2) . "</td>
                                </tr>";
                }

                $message .= "
                            </tbody>
                        </table>
                        <p>Items Subtotal: ₹" . number_format($itemsSubtotal, 2) . "</p>";
                if ($apply_gst) {
                    $message .= "<p>GST (" . $gst_percent . "%): ₹" . number_format($gstAmount, 2) . "</p>";
                }
                $message .= "
                        <p class='total'>Grand Total: ₹" . number_format($grandTotal, 2) . "</p>
                        <p><strong>Shipping Address:</strong><br>" . nl2br(htmlspecialchars($address)) . "<br>" . htmlspecialchars($city) . ", " . htmlspecialchars($state) . " - " . htmlspecialchars($pincode) . "</p>
                    </div>
                </body>
                </html>";

                $headers  = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= "From: RetailStore <no-reply@" . $_SERVER['SERVER_NAME'] . ">" . "\r\n";

                @mail($email, $subject, $message, $headers);
                setFlash('success', 'Order updated successfully & email sent to ' . $email);
            } else {
                setFlash('success', 'Order details updated successfully.');
            }

            redirect("order-view.php?id=" . $id);

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Update failed: " . $e->getMessage();
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold"><i class="fa-solid fa-pen-to-square me-2"></i>Edit Order: <?= sanitize($order['order_no']); ?></h2>
    <a href="order-view.php?id=<?= $id; ?>" class="btn btn-secondary"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo "<li>{$e}</li>"; ?></ul></div>
<?php endif; ?>

<form method="POST" action="order-edit.php?id=<?= $id; ?>" id="orderEditForm">
    <div id="deletedItemsContainer"></div>

    <div class="row g-4">
        <!-- Order Items Table -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between align-items-center">
                    <span>Modify Line Items</span>
                    <small class="fw-normal text-muted">In-row Add & Remove</small>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Product Name</th>
                                <th>Unit Price</th>
                                <th style="width: 100px;">Quantity</th>
                                <th class="text-end">Subtotal</th>
                                <th class="text-center" style="width: 50px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="lineItemsBody">
                            <?php foreach ($items as $item): ?>
                                <tr class="order-item-row" data-item-id="<?= $item['id']; ?>">
                                    <td class="fw-bold"><?= sanitize($item['product_name']); ?></td>
                                    <td>₹<span class="unit-price" data-price="<?= $item['price']; ?>"><?= number_format($item['price'], 2); ?></span></td>
                                    <td>
                                        <input type="number" name="quantities[<?= $item['id']; ?>]" class="form-control form-control-sm text-center edit-qty-input" value="<?= $item['quantity']; ?>" min="0">
                                    </td>
                                    <td class="text-end fw-bold text-danger">
                                        ₹<span class="item-subtotal-val"><?= number_format($item['subtotal'], 2); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete-row" data-id="<?= $item['id']; ?>" title="Delete Row">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light border-top">
                            <!-- In-row Add New Product Row -->
                            <tr class="bg-white">
                                <td colspan="2">
                                    <select name="new_product_id" id="newProductSelect" class="form-select form-select-sm">
                                        <option value="0" data-price="0">-- + Add Product to Row --</option>
                                        <?php foreach ($allProducts as $ap): ?>
                                            <option value="<?= $ap['id']; ?>" data-price="<?= $ap['price']; ?>"><?= sanitize($ap['product_name']); ?> (₹<?= number_format($ap['price'], 2); ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="new_product_qty" id="newProductQty" class="form-control form-control-sm text-center" value="1" min="1" placeholder="Qty">
                                </td>
                                <td colspan="2" class="text-end">
                                    <span class="text-muted small me-2">Selected Subtotal:</span>
                                    <strong class="text-primary">₹<span id="newProductSubtotalDisplay">0.00</span></strong>
                                </td>
                            </tr>

                            <!-- Subtotal -->
                            <tr>
                                <td colspan="3" class="text-end fw-bold">Items Subtotal:</td>
                                <td class="text-end fw-bold text-dark" colspan="2">₹<span id="calc-items-subtotal">0.00</span></td>
                            </tr>

                            <!-- GST Row -->
                            <tr>
                                <td colspan="3" class="text-end fw-bold">
                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                        <div class="form-check form-switch m-0">
                                            <input class="form-check-input" type="checkbox" name="apply_gst" id="applyGstCheckbox" <?= $isGstEnabled ? 'checked' : ''; ?>>
                                            <label class="form-check-label fw-bold" for="applyGstCheckbox">Apply GST Tax</label>
                                        </div>
                                        <div class="input-group input-group-sm ms-2" style="width: 100px;">
                                            <input type="number" step="0.01" name="gst_percent" id="gstPercentInput" class="form-control text-center" value="<?= $currentGstPercent; ?>">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-end fw-bold text-warning" colspan="2">₹<span id="calc-gst-amount">0.00</span></td>
                            </tr>

                            <!-- Grand Total -->
                            <tr class="table-dark text-white">
                                <td colspan="3" class="text-end fw-bold fs-5">Grand Total (Incl. GST):</td>
                                <td class="text-end fw-bold fs-5 text-warning" colspan="2">₹<span id="calculated-grand-total">0.00</span></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Customer Info & Action Side Panel -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-dark text-white fw-bold">Customer Details</div>
                <div class="card-body">
                    <div class="mb-2">
                        <label class="form-label fw-bold small">Customer Name *</label>
                        <input type="text" name="customer_name" class="form-control form-control-sm" value="<?= sanitize($order['customer_name']); ?>" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold small">Phone *</label>
                        <input type="text" name="phone" class="form-control form-control-sm" value="<?= sanitize($order['phone']); ?>" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold small">Email Address</label>
                        <input type="email" name="email" class="form-control form-control-sm" value="<?= sanitize($order['email']); ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold small">Status</label>
                        <select name="order_status" class="form-select form-select-sm">
                            <?php foreach (['Pending', 'Confirmed', 'Packed', 'Shipped', 'Delivered', 'Cancelled'] as $st): ?>
                                <option value="<?= $st; ?>" <?= ($order['order_status'] === $st) ? 'selected' : ''; ?>><?= $st; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold small">Address</label>
                        <textarea name="address" class="form-control form-control-sm" rows="2"><?= sanitize($order['address']); ?></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold small">City</label>
                        <input type="text" name="city" class="form-control form-control-sm" value="<?= sanitize($order['city']); ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold small">State</label>
                        <input type="text" name="state" class="form-control form-control-sm" value="<?= sanitize($order['state']); ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold small">Pincode</label>
                        <input type="text" name="pincode" class="form-control form-control-sm" value="<?= sanitize($order['pincode']); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Notes</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="2"><?= sanitize($order['notes'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="send_email" id="sendEmailSwitch" checked>
                        <label class="form-check-label fw-bold small" for="sendEmailSwitch">Send updated quotation to customer email</label>
                    </div>

                    <button type="submit" name="update_order" class="btn btn-primary w-100 fw-bold"><i class="fa-solid fa-floppy-disk me-1"></i> Save Changes</button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const newProductSelect = document.getElementById('newProductSelect');
    const newProductQty = document.getElementById('newProductQty');
    const newSubtotalDisplay = document.getElementById('newProductSubtotalDisplay');
    const deletedContainer = document.getElementById('deletedItemsContainer');
    const applyGstCheckbox = document.getElementById('applyGstCheckbox');
    const gstPercentInput = document.getElementById('gstPercentInput');

    function recalculateOrderTotals() {
        let itemsSubtotal = 0;

        // 1. Calculate existing line items
        document.querySelectorAll('.order-item-row').forEach(row => {
            if (row.style.display !== 'none') {
                const priceEl = row.querySelector('.unit-price');
                const qtyInput = row.querySelector('.edit-qty-input');
                const subtotalEl = row.querySelector('.item-subtotal-val');

                const price = parseFloat(priceEl.dataset.price) || 0;
                const qty = parseInt(qtyInput.value) || 0;
                const subtotal = price * qty;

                subtotalEl.textContent = subtotal.toFixed(2);
                itemsSubtotal += subtotal;
            }
        });

        // 2. Add extra subtotal if new product selected
        if (newProductSelect && newProductQty) {
            const selectedOption = newProductSelect.options[newProductSelect.selectedIndex];
            const extraPrice = parseFloat(selectedOption.dataset.price) || 0;
            const extraQty = parseInt(newProductQty.value) || 0;
            const extraSubtotal = extraPrice * extraQty;

            if (newSubtotalDisplay) {
                newSubtotalDisplay.textContent = extraSubtotal.toFixed(2);
            }

            if (selectedOption.value !== "0" && extraQty > 0) {
                itemsSubtotal += extraSubtotal;
            }
        }

        // 3. GST Calculations
        const applyGst = applyGstCheckbox ? applyGstCheckbox.checked : false;
        const gstPercent = gstPercentInput ? (parseFloat(gstPercentInput.value) || 0) : 0;
        let gstAmount = 0;

        if (applyGst) {
            gstAmount = itemsSubtotal * (gstPercent / 100);
            gstPercentInput.disabled = false;
        } else {
            gstPercentInput.disabled = true;
        }

        const grandTotal = itemsSubtotal + gstAmount;

        // Update UI
        document.getElementById('calc-items-subtotal').textContent = itemsSubtotal.toFixed(2);
        document.getElementById('calc-gst-amount').textContent = gstAmount.toFixed(2);
        document.getElementById('calculated-grand-total').textContent = grandTotal.toFixed(2);
    }

    // Input Listeners
    document.querySelectorAll('.edit-qty-input').forEach(input => {
        input.addEventListener('input', recalculateOrderTotals);
    });

    document.querySelectorAll('.btn-delete-row').forEach(btn => {
        btn.addEventListener('click', function () {
            const itemId = this.dataset.id;
            const row = this.closest('.order-item-row');

            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'delete_items[]';
            hiddenInput.value = itemId;
            deletedContainer.appendChild(hiddenInput);

            row.style.display = 'none';
            recalculateOrderTotals();
        });
    });

    if (newProductSelect) newProductSelect.addEventListener('change', recalculateOrderTotals);
    if (newProductQty) newProductQty.addEventListener('input', recalculateOrderTotals);
    if (applyGstCheckbox) applyGstCheckbox.addEventListener('change', recalculateOrderTotals);
    if (gstPercentInput) gstPercentInput.addEventListener('input', recalculateOrderTotals);

    recalculateOrderTotals();
});
</script>

<?php require_once 'includes/footer.php'; ?>