<?php
require_once 'includes/header.php';

$currentAdminId = $_SESSION['user_id'] ?? 0;
$errors = [];

// 1. Handle Status Toggle (Enable / Disable)
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status' && isset($_GET['id'])) {
    $targetId = (int)$_GET['id'];

    if ($targetId === $currentAdminId) {
        setFlash('error', 'You cannot disable your own active session.');
    } else {
        $stmt = $pdo->prepare("SELECT status, username FROM admin WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $targetId]);
        $targetUser = $stmt->fetch();

        if ($targetUser) {
            $newStatus = ($targetUser['status'] == 1) ? 0 : 1;
            $uStmt = $pdo->prepare("UPDATE admin SET status = :status WHERE id = :id");
            $uStmt->execute([':status' => $newStatus, ':id' => $targetId]);

            $statusText = ($newStatus == 1) ? 'enabled' : 'disabled';
            setFlash('success', "User '{$targetUser['username']}' has been {$statusText}.");
        }
    }
    redirect('users.php');
}

// 2. Handle Admin Changing Other User's Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_user_password'])) {
    $targetId         = (int)($_POST['target_user_id'] ?? 0);
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password) || strlen($new_password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }
    if ($new_password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    if (empty($errors)) {
        $newHash = password_hash($new_password, PASSWORD_DEFAULT);
        $uStmt = $pdo->prepare("UPDATE admin SET password = :pass WHERE id = :id");
        $uStmt->execute([':pass' => $newHash, ':id' => $targetId]);

        setFlash('success', 'User password updated successfully.');
        redirect('users.php');
    }
}

// Fetch all users from 'admin' table
$users = $pdo->query("SELECT * FROM admin ORDER BY id ASC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold"><i class="fa-solid fa-users-gear me-2"></i>User Management</h2>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger shadow-sm rounded-3">
        <ul class="mb-0 fw-semibold"><?php foreach ($errors as $e) echo "<li>{$e}</li>"; ?></ul>
    </div>
<?php endif; ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <!-- <h2 class="fw-bold"><i class="fa-solid fa-user-plus me-2"></i>Add New Admin User</h2> -->
    <a href="user-add.php" class="btn btn-secondary"><i class="fa-solid fa-plus me-1"></i> Add New User</a>
</div>
<div class="card shadow-sm border-0 rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light border-bottom">
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): 
                        $isActive = (isset($u['status']) ? $u['status'] : 1) == 1;
                        $isSelf = ($u['id'] == $currentAdminId);
                    ?>
                        <tr>
                            <td class="fw-bold">#<?= $u['id']; ?></td>
                            <td class="fw-bold text-dark">
                                <?= sanitize($u['username']); ?>
                                <?php if ($isSelf): ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary ms-1 small">You</span>
                                <?php endif; ?>
                            </td>
                            <td><?= sanitize($u['email'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if ($isActive): ?>
                                    <span class="badge bg-success px-2 py-1"><i class="fa-solid fa-circle-check me-1"></i> Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger px-2 py-1"><i class="fa-solid fa-circle-xmark me-1"></i> Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-3">
                                <button type="button" class="btn btn-sm btn-outline-warning me-1 fw-bold" data-bs-toggle="modal" data-bs-target="#passModal<?= $u['id']; ?>">
                                    <i class="fa-solid fa-key me-1"></i> Change Pass
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-warning me-1 fw-bold" data-bs-toggle="modal" data-bs-target="#passModal<?= $u['id']; ?>">
                                   <a href="user-edit.php?id=<?= $u['id']; ?>" class="text-decoration-none">
                                        <i class="fa-solid fa-edit me-1"></i> Edit user
                                    </a>
                                </button>

                                <?php if (!$isSelf): ?>
                                    <?php if ($isActive): ?>
                                        <a href="users.php?action=toggle_status&id=<?= $u['id']; ?>" class="btn btn-sm btn-outline-danger fw-bold" onclick="return confirm('Are you sure you want to disable user \'<?= sanitize($u['username']); ?>\'?');">
                                            <i class="fa-solid fa-user-slash me-1"></i> Disable
                                        </a>
                                    <?php else: ?>
                                        <a href="users.php?action=toggle_status&id=<?= $u['id']; ?>" class="btn btn-sm btn-outline-success fw-bold">
                                            <i class="fa-solid fa-user-check me-1"></i> Enable
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <!-- Password Change Modal -->
                        <div class="modal fade" id="passModal<?= $u['id']; ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content border-0 shadow-lg rounded-4">
                                    <div class="modal-header bg-dark text-white">
                                        <h5 class="modal-title fw-bold"><i class="fa-solid fa-key me-2"></i>Change Password for '<?= sanitize($u['username']); ?>'</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST" action="users.php">
                                        <div class="modal-body p-4">
                                            <input type="hidden" name="target_user_id" value="<?= $u['id']; ?>">
                                            
                                            <div class="mb-3">
                                                <label class="form-label fw-bold small text-muted">New Password *</label>
                                                <input type="password" name="new_password" class="form-control" placeholder="At least 6 characters" required>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label fw-bold small text-muted">Confirm New Password *</label>
                                                <input type="password" name="confirm_password" class="form-control" placeholder="Re-type new password" required>
                                            </div>
                                        </div>
                                        <div class="modal-footer bg-light">
                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="reset_user_password" class="btn btn-primary btn-sm fw-bold">Save Password</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>