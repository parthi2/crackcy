<?php
require_once 'includes/header.php';

// Fetch current setting value
$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'next_order_number' LIMIT 1");
$stmt->execute();
$currentNextNo = (int)($stmt->fetchColumn() ?: 1);

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_settings'])) {
    $newNextNo = (int)($_POST['next_order_number'] ?? 1);

    if ($newNextNo < 1) {
        setFlash('error', 'The next order number must be at least 1.');
    } else {
        $uStmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES ('next_order_number', :val)
            ON DUPLICATE KEY UPDATE setting_value = :val
        ");
        $uStmt->execute([':val' => $newNextNo]);
        
        $currentNextNo = $newNextNo;
        setFlash('success', 'Next Order Number sequence updated successfully.');
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold"><i class="fa-solid fa-sliders me-2"></i>Order Number Settings</h2>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white fw-bold">Configure Sequence</div>
            <div class="card-body p-4">
                <form method="POST" action="order-settings.php">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Current Year Prefix</label>
                        <input type="text" class="form-control bg-light" value="<?= date('Y'); ?>-" readonly>
                        <small class="text-muted">The year prefix updates automatically based on the current system year.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Next Order Increment Number *</label>
                        <input type="number" name="next_order_number" class="form-control" value="<?= $currentNextNo; ?>" min="1" required>
                        <small class="text-muted d-block mt-1">
                            The next order placed will generate Order ID: <strong><?= date('Y') . '-' . $currentNextNo; ?></strong>
                        </small>
                    </div>

                    <button type="submit" name="update_order_settings" class="btn btn-primary fw-bold">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Sequence
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>