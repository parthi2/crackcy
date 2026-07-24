<?php
require_once 'includes/header.php';
// Single query to calculate all 4 dashboard metrics at once
$metricsQuery = "
    SELECT 
        COUNT(CASE WHEN order_status = 'Pending' AND order_status != 'Cancelled' THEN 1 END) AS total_quotation,
        COUNT(CASE WHEN order_status = 'Confirmed' THEN 1 END) AS total_orders,
        COUNT(CASE WHEN DATE(created_at) = CURRENT_DATE() THEN 1 END) AS today_orders,
        COALESCE(SUM(CASE WHEN order_status = 'Confirmed' THEN total_amount END), 0) AS total_revenue
    FROM orders
";

$metrics = $pdo->query($metricsQuery)->fetch(PDO::FETCH_ASSOC);

// Extract values safely
$totalQuotation = $metrics['total_quotation'] ?? 0;
$totalOrders    = $metrics['total_orders'] ?? 0;
$todayOrders    = $metrics['today_orders'] ?? 0;
$totalRevenue   = $metrics['total_revenue'] ?? 0;

// Fetch recent 5 orders
$recentOrdersStmt = $pdo->query("SELECT * FROM orders ORDER BY id DESC LIMIT 5");
$recentOrders     = $recentOrdersStmt->fetchAll();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2 fw-bold">Dashboard</h1>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase small">Total quotation</h6>
                        <h2 class="display-6 fw-bold mb-0"><?= $totalQuotation; ?></h2>
                    </div>
                    <i class="fa-solid fa-box fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase small">Total Orders</h6>
                        <h2 class="display-6 fw-bold mb-0"><?= $totalOrders; ?></h2>
                    </div>
                    <i class="fa-solid fa-cart-shopping fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase small">Today's Orders</h6>
                        <h2 class="display-6 fw-bold mb-0"><?= $todayOrders; ?></h2>
                    </div>
                    <i class="fa-solid fa-calendar-day fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase small">Total Revenue</h6>
                        <h2 class="display-6 fw-bold mb-0">₹<?= number_format($totalRevenue, 0); ?></h2>
                    </div>
                    <i class="fa-solid fa-indian-rupee-sign fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">Recent Orders</h5>
        <a href="orders.php" class="btn btn-sm btn-outline-primary">View All Orders</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Order No</th>
                    <th>Customer</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentOrders)): ?>
                    <tr><td colspan="6" class="text-center py-3 text-muted">No orders found.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentOrders as $order): ?>
                        <tr>
                            <td class="fw-bold"><?= sanitize($order['order_no']); ?></td>
                            <td><?= sanitize($order['customer_name']); ?></td>
                            <td>₹<?= number_format($order['total_amount'], 2); ?></td>
                            <td>
                                <span class="badge bg-secondary"><?= $order['order_status']; ?></span>
                            </td>
                            <td><?= date('d M Y, h:i A', strtotime($order['created_at'])); ?></td>
                            <td>
                                <a href="order-view.php?id=<?= $order['id']; ?>" class="btn btn-sm btn-info text-white"><i class="fa-solid fa-eye"></i> View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
