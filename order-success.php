<?php
require_once 'includes/header.php';

$orderNo = isset($_GET['order_no']) ? sanitize($_GET['order_no']) : '';

if (empty($orderNo)) {
    redirect('index.php');
}
?>

<div class="row justify-content-center my-5">
    <div class="col-md-8 text-center">
        <div class="card shadow border-0 p-4">
            <div class="card-body">
                <i class="fa-solid fa-circle-check fa-5x text-success mb-3"></i>
                <h1 class="fw-bold text-success">Quote Request Submitted Successfully!</h1>
                <p class="fs-5 text-secondary">Thank you for your quote request.</p>
                
                <div class="alert alert-light border my-4">
                    <span class="text-muted d-block mb-1">Your Quote Reference Number</span>
                    <strong class="fs-3 text-dark">EST-<?= $orderNo; ?></strong>
                </div>

                <p class="text-muted">We have logged your quote request and will get in touch shortly regarding the details.</p>
                
                <a href="index.php" class="btn btn-primary btn-lg mt-3"><i class="fa-solid fa-bag-shopping me-2"></i>Back to Shop</a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
