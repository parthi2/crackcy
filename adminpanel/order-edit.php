<?php
// 1. INCLUDE DATABASE & SESSION CONFIGURATION FIRST
require_once '../config/database.php';

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
    $quantities     = $_POST['quantities'] ?? [];
    $delete_items   = $_POST['delete_items'] ?? [];
    $new_products   = $_POST['new_products'] ?? []; 
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

            // 2. Add Multiple New Products
            if (!empty($new_products) && is_array($new_products)) {
                $addItem = $pdo->prepare("
                    INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal)
                    VALUES (:order_id, :product_id, :product_name, :price, :quantity, :subtotal)
                ");

                foreach ($new_products as $np) {
                    $prodId = (int)($np['product_id'] ?? 0);
                    $prodQty = (int)($np['quantity'] ?? 0);

                    if ($prodId > 0 && $prodQty > 0) {
                        $pStmt = $pdo->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
                        $pStmt->execute([':id' => $prodId]);
                        $pData = $pStmt->fetch();

                        if ($pData) {
                            $subtotal = $pData['price'] * $prodQty;
                            $itemsSubtotal += $subtotal;

                            $addItem->execute([
                                ':order_id'     => $id,
                                ':product_id'   => $pData['id'],
                                ':product_name' => $pData['product_name'],
                                ':price'        => $pData['price'],
                                ':quantity'     => $prodQty,
                                ':subtotal'     => $subtotal
                            ]);
                        }
                    }
                }
            }

            // 3. Grand Total Calculation (GST Removed)
            $grandTotal = $itemsSubtotal;

            // 4. Update Order Table
            $uOrder = $pdo->prepare("
                UPDATE orders 
                SET customer_name = :name, phone = :phone, email = :email, address = :address, 
                    city = :city, state = :state, pincode = :pincode, notes = :notes, 
                    order_status = :status, subtotal_amount = :subtotal_amount, 
                    gst_percent = 0.00, gst_amount = 0.00, total_amount = :total
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

// NOW SAFE TO INCLUDE HTML HEADER AFTER REDIRECTS ARE PROCESSED
require_once 'includes/header.php';
?>

<style>
.searchable-dropdown-container {
    position: relative;
    width: 100%;
}
.searchable-dropdown-list {
    position: fixed;
    max-height: 220px;
    overflow-y: auto;
    background: #ffffff;
    border: 1px solid #ced4da;
    border-radius: 0.375rem;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    z-index: 99999;
    display: none;
}
.searchable-dropdown-item {
    padding: 10px 14px;
    cursor: pointer;
    font-size: 0.875rem;
    border-bottom: 1px solid #f1f1f1;
    color: #333;
}
.searchable-dropdown-item:hover {
    background-color: #f8f9fa;
    color: #0d6efd;
}
</style>

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
                    <small class="fw-normal text-muted">Search & Add Multiple Products</small>
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
                                <tr class="order-item-row" data-item-id="<?= $item['id']; ?>" data-product-id="<?= $item['product_id']; ?>">
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
                            <!-- Searchable Product Selection Add Row -->
                            <tr class="bg-white">
                                <td colspan="2">
                                    <div class="searchable-dropdown-container">
                                        <input type="text" id="productSearchInput" class="form-control form-control-sm" placeholder="🔍 Type to search product..." autocomplete="off">
                                        <input type="hidden" id="selectedProductId" value="0">
                                        <input type="hidden" id="selectedProductPrice" value="0">
                                        <input type="hidden" id="selectedProductName" value="">
                                        <div id="productDropdownList" class="searchable-dropdown-list">
                                            <?php foreach ($allProducts as $ap): ?>
                                                <div class="searchable-dropdown-item" data-id="<?= $ap['id']; ?>" data-price="<?= $ap['price']; ?>" data-name="<?= sanitize($ap['product_name']); ?>">
                                                    <?= sanitize($ap['product_name']); ?> — <strong class="text-success">₹<?= number_format($ap['price'], 2); ?></strong>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <input type="number" id="quickProductQty" class="form-control form-control-sm text-center" value="1" min="1" placeholder="Qty">
                                </td>
                                <td colspan="2" class="text-center">
                                    <button type="button" id="btnAddProductRow" class="btn btn-sm btn-success w-100 fw-bold">
                                        <i class="fa-solid fa-plus me-1"></i> Add to List
                                    </button>
                                </td>
                            </tr>

                            <!-- Newly Added Dynamic Items Container Header -->
                            <tr id="newItemsHeaderRow" style="display: none;">
                                <td colspan="5" class="bg-light fw-bold text-secondary small py-1">New Items to Add:</td>
                            </tr>

                            <!-- Grand Total -->
                            <tr>
                                <td colspan="3" class="text-end fw-bold">Grand Total:</td>
                                <td class="text-end fw-bold text-warning fs-5" colspan="2">₹<span id="calculated-grand-total">0.00</span></td>
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

                    <!-- <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="send_email" id="sendEmailSwitch" checked>
                        <label class="form-check-label fw-bold small" for="sendEmailSwitch">Send updated quotation to customer email</label>
                    </div> -->

                    <button type="submit" name="update_order" class="btn btn-primary w-100 fw-bold"><i class="fa-solid fa-floppy-disk me-1"></i> Save Changes</button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const lineItemsBody = document.getElementById('lineItemsBody');
    const productSearchInput = document.getElementById('productSearchInput');
    const productDropdownList = document.getElementById('productDropdownList');
    const selectedProductId = document.getElementById('selectedProductId');
    const selectedProductPrice = document.getElementById('selectedProductPrice');
    const selectedProductName = document.getElementById('selectedProductName');
    const quickProductQty = document.getElementById('quickProductQty');
    const btnAddProductRow = document.getElementById('btnAddProductRow');
    const newItemsHeaderRow = document.getElementById('newItemsHeaderRow');
    const deletedContainer = document.getElementById('deletedItemsContainer');
    const grandTotalDisplay = document.getElementById('calculated-grand-total');

    let newProductIndex = 0;

    function recalculateOrderTotals() {
        let itemsSubtotal = 0;

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

        document.querySelectorAll('.new-item-row').forEach(row => {
            const price = parseFloat(row.dataset.price) || 0;
            const qtyInput = row.querySelector('.new-qty-input');
            const subtotalEl = row.querySelector('.new-subtotal-val');

            const qty = parseInt(qtyInput.value) || 0;
            const subtotal = price * qty;

            subtotalEl.textContent = subtotal.toFixed(2);
            itemsSubtotal += subtotal;
        });

        if (grandTotalDisplay) {
            grandTotalDisplay.textContent = itemsSubtotal.toFixed(2);
        }
    }

    // Hide already selected products from the searchable dropdown list
    function updateDropdownAvailability() {
        const activeProductIds = new Set();

        // Check active database rows (that aren't deleted)
        document.querySelectorAll('.order-item-row').forEach(row => {
            if (row.style.display !== 'none') {
                activeProductIds.add(String(row.dataset.productId));
            }
        });

        // Check newly added custom rows
        document.querySelectorAll('.new-item-row').forEach(row => {
            const hiddenInput = row.querySelector('input[type="hidden"]');
            if (hiddenInput) {
                // Extract product_id from input name like new_products[0][product_id]
                const match = hiddenInput.name.match(/\[(\d+)\]\[product_id\]/);
                if (match) {
                    const prodId = hiddenInput.value;
                    activeProductIds.add(String(prodId));
                }
            }
        });

        // Loop dropdown items and hide/show based on existence
        const dropdownItems = productDropdownList.querySelectorAll('.searchable-dropdown-item');
        dropdownItems.forEach(item => {
            const itemId = String(item.dataset.id);
            if (activeProductIds.has(itemId)) {
                item.style.display = 'none';
                item.classList.add('already-added');
            } else {
                item.style.display = 'block';
                item.classList.remove('already-added');
            }
        });
    }

    function showDropdownList() {
        updateDropdownAvailability();
        const rect = productSearchInput.getBoundingClientRect();
        productDropdownList.style.top = (rect.bottom + 4) + 'px';
        productDropdownList.style.left = rect.left + 'px';
        productDropdownList.style.width = rect.width + 'px';
        productDropdownList.style.display = 'block';
    }

    productSearchInput.addEventListener('focus', function () {
        showDropdownList();
    });

    productSearchInput.addEventListener('input', function () {
        const filter = productSearchInput.value.toLowerCase().trim();
        const items = productDropdownList.querySelectorAll('.searchable-dropdown-item');
        let hasMatches = false;

        items.forEach(item => {
            if (item.classList.contains('already-added')) {
                item.style.display = 'none';
                return;
            }
            const text = item.textContent.toLowerCase();
            if (text.includes(filter)) {
                item.style.display = 'block';
                hasMatches = true;
            } else {
                item.style.display = 'none';
            }
        });

        if (hasMatches) {
            showDropdownList();
        } else {
            productDropdownList.style.display = 'none';
        }
    });

    window.addEventListener('resize', function() {
        if (productDropdownList.style.display === 'block') {
            showDropdownList();
        }
    });

    window.addEventListener('scroll', function() {
        if (productDropdownList.style.display === 'block') {
            showDropdownList();
        }
    });

    productDropdownList.addEventListener('click', function (e) {
        const item = e.target.closest('.searchable-dropdown-item');
        if (item) {
            selectedProductId.value = item.dataset.id;
            selectedProductPrice.value = item.dataset.price;
            selectedProductName.value = item.dataset.name;
            productSearchInput.value = item.dataset.name + ' (₹' + parseFloat(item.dataset.price).toFixed(2) + ')';
            productDropdownList.style.display = 'none';
        }
    });

    document.addEventListener('click', function (e) {
        if (!productSearchInput.contains(e.target) && !productDropdownList.contains(e.target)) {
            productDropdownList.style.display = 'none';
        }
    });

    if (btnAddProductRow) {
        btnAddProductRow.addEventListener('click', function () {
            const prodId = selectedProductId.value;
            const prodName = selectedProductName.value;
            const prodPrice = parseFloat(selectedProductPrice.value) || 0;
            const prodQty = parseInt(quickProductQty.value) || 1;

            if (prodId === "0" || !prodName) {
                alert("Please search and select a valid product from the dropdown list.");
                return;
            }

            newItemsHeaderRow.style.display = '';

            const subtotal = prodPrice * prodQty;
            const newRow = document.createElement('tr');
            newRow.className = 'new-item-row table-success bg-opacity-25';
            newRow.dataset.price = prodPrice;

            newRow.innerHTML = `
                <td class="fw-bold text-success">
                    <i class="fa-solid fa-circle-plus me-1"></i> ${prodName}
                    <input type="hidden" name="new_products[${newProductIndex}][product_id]" value="${prodId}">
                </td>
                <td>₹${prodPrice.toFixed(2)}</td>
                <td>
                    <input type="number" name="new_products[${newProductIndex}][quantity]" class="form-control form-control-sm text-center new-qty-input" value="${prodQty}" min="1">
                </td>
                <td class="text-end fw-bold text-success">
                    ₹<span class="new-subtotal-val">${subtotal.toFixed(2)}</span>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-new-row" title="Remove">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </td>
            `;

            lineItemsBody.appendChild(newRow);
            newProductIndex++;

            productSearchInput.value = "";
            selectedProductId.value = "0";
            selectedProductPrice.value = "0";
            selectedProductName.value = "";
            quickProductQty.value = "1";

            recalculateOrderTotals();
            updateDropdownAvailability();
        });
    }

    document.addEventListener('input', function (e) {
        if (e.target.classList.contains('edit-qty-input') || e.target.classList.contains('new-qty-input')) {
            recalculateOrderTotals();
        }
    });

    document.addEventListener('click', function (e) {
        if (e.target.closest('.btn-delete-row')) {
            const btn = e.target.closest('.btn-delete-row');
            const itemId = btn.dataset.id;
            const row = btn.closest('.order-item-row');

            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'delete_items[]';
            hiddenInput.value = itemId;
            deletedContainer.appendChild(hiddenInput);

            row.style.display = 'none';
            recalculateOrderTotals();
            updateDropdownAvailability();
        }

        if (e.target.closest('.btn-remove-new-row')) {
            const row = e.target.closest('.new-item-row');
            row.remove();

            if (document.querySelectorAll('.new-item-row').length === 0) {
                newItemsHeaderRow.style.display = 'none';
            }
            recalculateOrderTotals();
            updateDropdownAvailability();
        }
    });

    recalculateOrderTotals();
    updateDropdownAvailability();
});
</script>

<?php require_once 'includes/footer.php'; ?>