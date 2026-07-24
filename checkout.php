<?php
require_once 'includes/header.php';

$cart = $_SESSION['cart'] ?? [];

if (empty($cart)) {
    setFlash('error', 'Your shopping cart is empty.');
    redirect('index.php');
}

$grandTotal = 0;
foreach ($cart as $item) {
    $grandTotal += $item['price'] * $item['quantity'];
}

$errors = [];
$formData = [
    'customer_name' => '', 'phone' => '', 'email' => '',
    'address' => '', 'city' => '', 'state' => '', 'pincode' => '', 'notes' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($formData as $key => $val) {
        $formData[$key] = sanitize($_POST[$key] ?? '');
    }

    if (empty($formData['customer_name'])) $errors[] = "Customer name is required.";
    if (empty($formData['phone']) || !preg_match('/^[0-9]{10}$/', $formData['phone'])) $errors[] = "Valid 10-digit phone number is required.";
    if (empty($formData['email']) || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email address is required.";
    if (empty($formData['address'])) $errors[] = "Delivery address is required.";
    if (empty($formData['city'])) $errors[] = "City is required.";
    if (empty($formData['state'])) $errors[] = "State is required.";
    if (empty($formData['pincode'])) $errors[] = "Pincode is required.";

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $orderNo = 'ORD-' . strtoupper(uniqid());

            $orderStmt = $pdo->prepare("
                INSERT INTO orders (order_no, customer_name, phone, email, address, city, state, pincode, notes, total_amount, order_status)
                VALUES (:order_no, :customer_name, :phone, :email, :address, :city, :state, :pincode, :notes, :total_amount, 'Pending')
            ");

            $orderStmt->execute([
                ':order_no' => $orderNo,
                ':customer_name' => $formData['customer_name'],
                ':phone' => $formData['phone'],
                ':email' => $formData['email'],
                ':address' => $formData['address'],
                ':city' => $formData['city'],
                ':state' => $formData['state'],
                ':pincode' => $formData['pincode'],
                ':notes' => $formData['notes'],
                ':total_amount' => $grandTotal
            ]);

            $orderId = $pdo->lastInsertId();

            $itemStmt = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal)
                VALUES (:order_id, :product_id, :product_name, :price, :quantity, :subtotal)
            ");

            foreach ($cart as $productId => $item) {
                $subtotal = $item['price'] * $item['quantity'];
                $itemStmt->execute([
                    ':order_id' => $orderId,
                    ':product_id' => $productId,
                    ':product_name' => $item['name'],
                    ':price' => $item['price'],
                    ':quantity' => $item['quantity'],
                    ':subtotal' => $subtotal
                ]);
            }

            $pdo->commit();
            unset($_SESSION['cart']);

            redirect("order-success.php?order_no=" . urlencode($orderNo));

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Transaction failed: " . $e->getMessage();
        }
    }
}
?>

<h2 class="fw-bold mb-4">Checkout</h2>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger shadow-sm">
        <ul class="mb-0">
            <?php foreach ($errors as $err): ?>
                <li><?= $err; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" action="checkout.php" class="needs-validation" novalidate>
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title fw-bold mb-3"><i class="fa-solid fa-truck me-2 text-primary"></i>Shipping Information</h5>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="customer_name" class="form-control" value="<?= $formData['customer_name']; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone Number *</label>
                            <input type="tel" name="phone" class="form-control" placeholder="10 digit mobile" value="<?= $formData['phone']; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email Address *</label>
                            <input type="email" name="email" class="form-control" value="<?= $formData['email']; ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address *</label>
                            <textarea name="address" class="form-control" rows="2" required><?= $formData['address']; ?></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">City *</label>
                            <input type="text" name="city" class="form-control" value="<?= $formData['city']; ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">State *</label>
                            <input type="text" name="state" class="form-control" value="<?= $formData['state']; ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Pincode *</label>
                            <input type="text" name="pincode" class="form-control" value="<?= $formData['pincode']; ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Order Notes (Optional)</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Delivery instructions..."><?= $formData['notes']; ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title fw-bold mb-3">Your Order</h5>
                    <ul class="list-group list-group-flush mb-3">
                        <?php foreach ($cart as $item): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <div>
                                    <h6 class="my-0"><?= sanitize($item['name']); ?></h6>
                                    <small class="text-muted">Qty: <?= $item['quantity']; ?> x ₹<?= number_format($item['price'], 2); ?></small>
                                </div>
                                <span class="fw-bold">₹<?= number_format($item['price'] * $item['quantity'], 2); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="d-flex justify-content-between border-top pt-3">
                        <strong class="fs-5">Total Payable</strong>
                        <strong class="fs-5 text-primary">₹<?= number_format($grandTotal, 2); ?></strong>
                    </div>
                    <button type="submit" class="btn btn-success btn-lg w-100 mt-4 fw-bold">Place Order (Cash on Delivery)</button>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require_once 'includes/footer.php'; ?>
