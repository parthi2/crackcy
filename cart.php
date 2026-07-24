<?php
require_once 'includes/header.php';

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

    if ($action === 'add') {
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
        if ($quantity < 1) $quantity = 1;

        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id AND status = 1 LIMIT 1");
        $stmt->execute([':id' => $product_id]);
        $prod = $stmt->fetch();

        if ($prod) {
            if (isset($_SESSION['cart'][$product_id])) {
                $_SESSION['cart'][$product_id]['quantity'] += $quantity;
            } else {
                $_SESSION['cart'][$product_id] = [
                    'id' => $prod['id'],
                    'name' => $prod['product_name'],
                    'price' => (float)$prod['price'],
                    'image' => $prod['image'],
                    'quantity' => $quantity
                ];
            }
            setFlash('success', 'Product added to shopping cart.');
        } else {
            setFlash('error', 'Selected product is invalid or inactive.');
        }
    } elseif ($action === 'update') {
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
        if ($quantity > 0 && isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]['quantity'] = $quantity;
            setFlash('success', 'Cart updated successfully.');
        } elseif ($quantity <= 0) {
            unset($_SESSION['cart'][$product_id]);
            setFlash('success', 'Item removed from cart.');
        }
    } elseif ($action === 'remove') {
        if (isset($_SESSION['cart'][$product_id])) {
            unset($_SESSION['cart'][$product_id]);
            setFlash('success', 'Item removed from cart.');
        }
    }
    redirect('cart.php');
}

$cart = $_SESSION['cart'];
$grandTotal = 0;
foreach ($cart as $item) {
    $grandTotal += $item['price'] * $item['quantity'];
}
?>

<h2 class="fw-bold mb-4"><i class="fa-solid fa-cart-shopping me-2"></i>Shopping Cart</h2>

<?php if (empty($cart)): ?>
    <div class="card shadow-sm border-0 text-center py-5">
        <div class="card-body">
            <i class="fa-solid fa-basket-shopping fa-4x text-muted mb-3"></i>
            <h4>Your cart is currently empty</h4>
            <p class="text-muted">Looks like you haven't added any products to your cart yet.</p>
            <a href="index.php" class="btn btn-primary mt-2"><i class="fa-solid fa-arrow-left me-1"></i> Continue Shopping</a>
        </div>
    </div>
<?php else: ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th style="width: 140px;">Quantity</th>
                                <th>Subtotal</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cart as $id => $item): 
                                $subtotal = $item['price'] * $item['quantity'];
                                $img = !empty($item['image']) && file_exists('uploads/products/' . $item['image']) 
                                    ? 'uploads/products/' . $item['image'] 
                                    : 'https://via.placeholder.com/80?text=No+Image';
                            ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?= $img; ?>" class="rounded me-3 object-fit-cover" style="width: 50px; height: 50px;" alt="<?= sanitize($item['name']); ?>">
                                            <div>
                                                <a href="product.php?id=<?= $id; ?>" class="fw-bold text-dark text-decoration-none"><?= sanitize($item['name']); ?></a>
                                            </div>
                                        </div>
                                    </td>
                                    <td>₹<?= number_format($item['price'], 2); ?></td>
                                    <td>
                                        <form method="POST" action="cart.php" class="d-flex align-items-center">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="product_id" value="<?= $id; ?>">
                                            <input type="number" name="quantity" class="form-control form-control-sm text-center" value="<?= $item['quantity']; ?>" min="1" max="10" onchange="this.form.submit()">
                                        </form>
                                    </td>
                                    <td class="fw-bold">₹<?= number_format($subtotal, 2); ?></td>
                                    <td class="text-end">
                                        <form method="POST" action="cart.php">
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="product_id" value="<?= $id; ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm" title="Remove Item">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title fw-bold mb-3">Order Summary</h5>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Items Subtotal</span>
                        <span>₹<?= number_format($grandTotal, 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span>Shipping</span>
                        <span class="text-success fw-bold">FREE</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-4">
                        <strong class="fs-5">Total</strong>
                        <strong class="fs-5 text-primary">₹<?= number_format($grandTotal, 2); ?></strong>
                    </div>
                    <a href="checkout.php" class="btn btn-success btn-lg w-100 fw-bold">
                        Proceed to Checkout <i class="fa-solid fa-arrow-right ms-2"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
