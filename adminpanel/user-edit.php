<?php
require_once 'includes/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT * FROM admin WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$user = $stmt->fetch();

if (!$user) {
    setFlash('error', 'User account not found.');
    redirect('users.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $email    = sanitize($_POST['email'] ?? '');
    $role     = sanitize($_POST['role'] ?? 'Staff');
    $password = $_POST['password'] ?? '';

    if (empty($username)) $errors[] = "Username is required.";

    // Check username uniqueness
    $checkStmt = $pdo->prepare("SELECT id FROM admin WHERE username = :u AND id != :id LIMIT 1");
    $checkStmt->execute([':u' => $username, ':id' => $id]);
    if ($checkStmt->fetch()) {
        $errors[] = "Username belongs to another account.";
    }

    if (empty($errors)) {
        if (!empty($password)) {
            // Update with new password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("
                UPDATE admin 
                SET username = :username, email = :email, password = :password, role = :role 
                WHERE id = :id
            ");
            $updateStmt->execute([
                ':username' => $username,
                ':email'    => $email,
                ':password' => $hashedPassword,
                ':role'     => $role,
                ':id'       => $id
            ]);
        } else {
            // Keep current password
            $updateStmt = $pdo->prepare("
                UPDATE admin 
                SET username = :username, email = :email, role = :role 
                WHERE id = :id
            ");
            $updateStmt->execute([
                ':username' => $username,
                ':email'    => $email,
                ':role'     => $role,
                ':id'       => $id
            ]);
        }

        setFlash('success', 'User updated successfully.');
        redirect('users.php');
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold"><i class="fa-solid fa-user-pen me-2"></i>Edit User Account</h2>
    <a href="users.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger shadow-sm">
        <ul class="mb-0">
            <?php foreach ($errors as $e) echo "<li>{$e}</li>"; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0 col-lg-8">
    <div class="card-body p-4">
        <form method="POST" action="user-edit.php?id=<?= $id; ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Username *</label>
                    <input type="text" name="username" class="form-control" value="<?= sanitize($user['username']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?= sanitize($user['email'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">New Password (Leave blank to keep current)</label>
                    <input type="password" name="password" class="form-control" placeholder="Optional">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Role *</label>
                    <select name="role" class="form-select" required>
                        <option value="Staff" <?= ($user['role'] === 'Staff') ? 'selected' : ''; ?>>Staff</option>
                        <option value="Super Admin" <?= ($user['role'] === 'Super Admin') ? 'selected' : ''; ?>>Super Admin</option>
                    </select>
                </div>
                <div class="col-12 mt-4">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Update User</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>