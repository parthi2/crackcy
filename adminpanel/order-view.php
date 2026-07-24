<?php
require_once 'includes/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Update Status Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_status'])) {
    $newStatus = sanitize($_POST['order_status']);
    $allowed = ['Pending', 'Confirmed', 'Packed', 'Shipped', 'Delivered', 'Cancelled'];

    if (in_array($newStatus, $allowed)) {
        $updateStmt = $pdo->prepare("UPDATE orders SET order_status = :status WHERE id = :id");
        $updateStmt->execute([':status' => $newStatus, ':id' => $id]);
        setFlash('success', 'Order status updated successfully.');
        redirect("order-view.php?id=" . $id);
    }
}

// Fetch Order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$order = $stmt->fetch();

if (!$order) {
    setFlash('error', 'Order requested does not exist.');
    redirect('orders.php');
}

// Fetch Line Items
$itemsStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = :order_id");
$itemsStmt->execute([':order_id' => $id]);
$items = $itemsStmt->fetchAll();

// Calculate subtotal if subtotal_amount column is empty or fallback needed
$calculatedSubtotal = 0;
foreach ($items as $it) {
    $calculatedSubtotal += (float)$it['subtotal'];
}
$subtotal = ($order['subtotal_amount'] > 0) ? (float)$order['subtotal_amount'] : $calculatedSubtotal;
$gstPercent = (float)($order['gst_percent'] ?? 0);
$gstAmount = (float)($order['gst_amount'] ?? 0);
$grandTotal = (float)$order['total_amount'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold"><i class="fa-solid fa-receipt me-2"></i>Order Details: <?= sanitize($order['order_no']); ?></h2>
    <div>
        <a href="order-edit.php?id=<?= $order['id']; ?>" class="btn btn-warning me-1"><i class="fa-solid fa-pen-to-square me-1"></i> Edit Order</a>
        <a href="order-print.php?id=<?= $order['id']; ?>" target="_blank" class="btn btn-dark me-1"><i class="fa-solid fa-print me-1"></i> Print Invoice</a>
        <a href="orders.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-dark text-white fw-bold">Ordered Items</div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Product Name</th>
                            <th>Unit Price</th>
                            <th>Quantity</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="fw-bold"><?= sanitize($item['product_name']); ?></td>
                                <td>₹<?= number_format($item['price'], 2); ?></td>
                                <td><?= $item['quantity']; ?></td>
                                <td class="text-end fw-bold">₹<?= number_format($item['subtotal'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="border-top">
                        <tr>
                            <td colspan="3" class="text-end fw-bold">Items Subtotal:</td>
                            <td class="text-end fw-bold">₹<?= number_format($subtotal, 2); ?></td>
                        </tr>
                        <?php if ($gstAmount > 0 || $gstPercent > 0): ?>
                            <tr>
                                <td colspan="3" class="text-end fw-bold text-muted">GST (<?= number_format($gstPercent, 2); ?>%):</td>
                                <td class="text-end fw-bold text-muted">₹<?= number_format($gstAmount, 2); ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr class="table-light">
                            <td colspan="3" class="text-end fw-bold fs-5">Grand Total:</td>
                            <td class="text-end fw-bold fs-5 text-danger">₹<?= number_format($grandTotal, 2); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white fw-bold">Delivery Notes</div>
            <div class="card-body">
                <p class="mb-0 text-secondary"><?= !empty($order['notes']) ? nl2br(sanitize($order['notes'])) : 'No special notes provided.'; ?></p>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Status Update Widget -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-dark text-white fw-bold">Update Order Status</div>
            <div class="card-body">
                <form method="POST" action="order-view.php?id=<?= $order['id']; ?>">
                    <div class="mb-3">
                        <select name="order_status" class="form-select">
                            <?php foreach (['Pending', 'Confirmed', 'Packed', 'Shipped', 'Delivered', 'Cancelled'] as $st): ?>
                                <option value="<?= $st; ?>" <?= ($order['order_status'] === $st) ? 'selected' : ''; ?>><?= $st; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold">Update Status</button>
                </form>
            </div>
        </div>

        <!-- Customer Info Widget -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white fw-bold">Customer Details</div>
            <div class="card-body">
                <p class="mb-1"><strong>Name:</strong> <?= sanitize($order['customer_name']); ?></p>
                <p class="mb-1"><strong>Phone:</strong> <?= sanitize($order['phone']); ?></p>
                <p class="mb-3"><strong>Email:</strong> <?= sanitize($order['email'] ?? 'N/A'); ?></p>
                <hr>
                <h6><strong>Shipping Address:</strong></h6>
                <p class="text-muted mb-0">
                    <?= sanitize($order['address']); ?><br>
                    <?= sanitize($order['city']); ?>, <?= sanitize($order['state']); ?> - <?= sanitize($order['pincode']); ?>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>