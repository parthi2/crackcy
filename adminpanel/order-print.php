<?php
require_once __DIR__ . '/../config/database.php';
requireAdminLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$order = $stmt->fetch();

if (!$order) {
    die("Order reference not found.");
}

$itemsStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = :order_id");
$itemsStmt->execute([':order_id' => $id]);
$items = $itemsStmt->fetchAll();

// Calculate subtotal fallback
$calculatedSubtotal = 0;
foreach ($items as $it) {
    $calculatedSubtotal += (float)$it['subtotal'];
}

$subtotal   = ($order['subtotal_amount'] > 0) ? (float)$order['subtotal_amount'] : $calculatedSubtotal;
$gstPercent = (float)($order['gst_percent'] ?? 0);
$gstAmount  = (float)($order['gst_amount'] ?? 0);
$grandTotal = (float)$order['total_amount'];

// Strict check: GST is only applied if gst_amount is greater than 0
$isGstApplied = ($gstAmount > 0.00);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice - <?= sanitize($order['order_no']); ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-size: 14px; background-color: #fff; }
        .invoice-box { max-width: 800px; margin: auto; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body class="p-4" onload="window.print();">

<div class="container invoice-box">
    <!-- Action Buttons (Hidden when Printing) -->
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <a href="order-view.php?id=<?= $order['id']; ?>" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Order
        </a>
        <button onclick="window.print();" class="btn btn-primary">
            <i class="fa-solid fa-print me-1"></i> Print Invoice
        </button>
    </div>

    <!-- Header Section -->
    <div class="row border-bottom pb-3 mb-3">
        <div class="col-6">
            <h2 class="fw-bold">Crackcy</h2>
            <p class="text-muted mb-0">123 Business Parkway, Suite 100<br>Chennai, TN, India</p>
        </div>
        <div class="col-6 text-end">
            <h3 class="text-uppercase text-secondary">INVOICE</h3>
            <strong>Order #:</strong> <?= sanitize($order['order_no']); ?><br>
            <strong>Date:</strong> <?= date('d M Y', strtotime($order['created_at'])); ?>
        </div>
    </div>

    <!-- Billed To Section -->
    <div class="row mb-4">
        <div class="col-6">
            <h6 class="fw-bold">Billed/Shipped To:</h6>
            <p class="mb-0">
                <strong><?= sanitize($order['customer_name']); ?></strong><br>
                <?= sanitize($order['address']); ?><br>
                <?= sanitize($order['city']); ?>, <?= sanitize($order['state']); ?> - <?= sanitize($order['pincode']); ?><br>
                Phone: <?= sanitize($order['phone']); ?><br>
                Email: <?= sanitize($order['email'] ?? 'N/A'); ?>
            </p>
        </div>
        <div class="col-6 text-end">
            <h6 class="fw-bold">Order Summary:</h6>
            <p class="mb-0">
                <strong>Status:</strong> <?= sanitize($order['order_status']); ?><br>
                <strong>Payment Terms:</strong> Cash on Delivery / Quotation
            </p>
        </div>
    </div>

    <!-- Items Table -->
    <table class="table table-bordered mb-4 align-middle">
        <thead class="table-light">
            <tr>
                <th>Item Description</th>
                <th class="text-center" style="width: 120px;">Price</th>
                <th class="text-center" style="width: 80px;">Qty</th>
                <th class="text-end" style="width: 140px;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td class="fw-semibold"><?= sanitize($it['product_name']); ?></td>
                    <td class="text-center">₹<?= number_format($it['price'], 2); ?></td>
                    <td class="text-center"><?= $it['quantity']; ?></td>
                    <td class="text-end">₹<?= number_format($it['subtotal'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <?php if ($isGstApplied): ?>
                <!-- Rendered ONLY if GST amount is greater than 0 -->
                <tr>
                    <th colspan="3" class="text-end fw-bold">Items Subtotal:</th>
                    <td class="text-end fw-bold">₹<?= number_format($subtotal, 2); ?></td>
                </tr>
                <tr>
                    <th colspan="3" class="text-end fw-bold text-secondary">
                        GST (<?= number_format($gstPercent, 2); ?>%):
                    </th>
                    <td class="text-end fw-bold text-secondary">
                        ₹<?= number_format($gstAmount, 2); ?>
                    </td>
                </tr>
            <?php endif; ?>

            <tr class="table-light">
                <th colspan="3" class="text-end fs-5 fw-bold">Grand Total:</th>
                <th class="text-end fs-5 fw-bold text-danger">₹<?= number_format($grandTotal, 2); ?></th>
            </tr>
        </tfoot>
    </table>

    <?php if (!empty($order['notes'])): ?>
        <div class="border rounded p-3 mb-4 bg-light">
            <h6 class="fw-bold mb-1">Notes / Instructions:</h6>
            <p class="mb-0 text-muted small"><?= nl2br(sanitize($order['notes'])); ?></p>
        </div>
    <?php endif; ?>

    <!-- Footer -->
    <div class="text-center text-muted border-top pt-3">
        <p class="small mb-0">Thank you for your business!</p>
    </div>
</div>

</body>
</html>