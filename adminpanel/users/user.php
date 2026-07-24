<?php
require_once 'includes/header.php';

// Handle Delete User Action
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = (int)($_GET['id'] ?? 0);

    // Prevent deleting primary account
    if ($id === 1 || $id === ($_SESSION['admin_id'] ?? 0)) {
        setFlash('error', 'You cannot delete your own active session or the primary Super Admin.');
    } else {
        $delStmt = $pdo->prepare("DELETE FROM admin WHERE id = :id");
        $delStmt->execute([':id' => $id]);
        setFlash('success', 'User account removed successfully.');
    }
    redirect('users.php');
}

$stmt = $pdo->query("SELECT * FROM admin ORDER BY id DESC");
$users = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold"><i class="fa-solid fa-users me-2"></i>User Management</h2>
    <a href="user-add.php" class="btn btn-primary"><i class="fa-solid fa-user-plus me-1"></i> Add New User</a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle datatable mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Created At</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= $u['id']; ?></td>
                            <td class="fw-bold"><?= sanitize($u['username']); ?></td>
                            <td><?= sanitize($u['email'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="badge <?= ($u['role'] === 'Super Admin') ? 'bg-danger' : 'bg-info text-dark'; ?>">
                                    <?= sanitize($u['role']); ?>
                                </span>
                            </td>
                            <td><?= date('d M Y, h:i A', strtotime($u['created_at'])); ?></td>
                            <td class="text-end">
                                <a href="user-edit.php?id=<?= $u['id']; ?>" class="btn btn-sm btn-warning me-1" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                <?php if ($u['id'] != 1 && $u['id'] != ($_SESSION['admin_id'] ?? 0)): ?>
                                    <a href="users.php?action=delete&id=<?= $u['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this user?');" title="Delete"><i class="fa-solid fa-trash"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>