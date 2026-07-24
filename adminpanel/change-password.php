<?php
require_once 'includes/header.php';

$currentUserId = $_SESSION['user_id'] ?? 0;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_self_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password)) $errors[] = "Current password is required.";
    if (strlen($new_password) < 6) $errors[] = "New password must be at least 6 characters long.";
    if ($new_password !== $confirm_password) $errors[] = "New password and confirmation do not match.";

    if (empty($errors)) {
        // Fetch current password hash from 'admin' table
        $stmt = $pdo->prepare("SELECT password FROM admin WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $currentUserId]);
        $user = $stmt->fetch();

        if ($user && password_verify($current_password, $user['password'])) {
            $newHash = password_hash($new_password, PASSWORD_DEFAULT);
            $uStmt = $pdo->prepare("UPDATE admin SET password = :pass WHERE id = :id");
            $uStmt->execute([':pass' => $newHash, ':id' => $currentUserId]);

            setFlash('success', 'Your password has been changed successfully.');
            redirect('change-password.php');
        } else {
            $errors[] = "Incorrect current password.";
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold"><i class="fa-solid fa-key me-2"></i>Change My Password</h2>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger shadow-sm rounded-3">
        <ul class="mb-0 fw-semibold"><?php foreach ($errors as $e) echo "<li>{$e}</li>"; ?></ul>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-dark text-white fw-bold">Update Account Password</div>
            <div class="card-body p-4">
                <form method="POST" action="change-password.php">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Current Password *</label>
                        <input type="password" name="current_password" class="form-control" placeholder="••••••••" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">New Password *</label>
                        <input type="password" name="new_password" class="form-control" placeholder="At least 6 characters" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted">Confirm New Password *</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Re-type new password" required>
                    </div>

                    <button type="submit" name="update_self_password" class="btn btn-primary fw-bold w-100 py-2">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save New Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>