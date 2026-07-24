<?php
require_once 'includes/header.php';

$errors = [];
$username = $email = $role = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $email    = sanitize($_POST['email'] ?? '');
    $role     = sanitize($_POST['role'] ?? 'Staff');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validations
    if (empty($username)) $errors[] = "Username is required.";
    if (empty($password)) $errors[] = "Password is required.";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match.";

    // Check if username exists
    $checkStmt = $pdo->prepare("SELECT id FROM admin WHERE username = :u LIMIT 1");
    $checkStmt->execute([':u' => $username]);
    if ($checkStmt->fetch()) {
        $errors[] = "Username already exists. Please pick another one.";
    }

    if (empty($errors)) {
        // Secure Password Hash
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO admin (username, email, password, role) 
            VALUES (:username, :email, :password, :role)
        ");

        $stmt->execute([
            ':username' => $username,
            ':email'    => $email,
            ':password' => $hashedPassword,
            ':role'     => $role
        ]);

        setFlash('success', 'New user account created successfully.');
        redirect('users.php');
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold"><i class="fa-solid fa-user-plus me-2"></i>Add New Admin User</h2>
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
        <form method="POST" action="user-add.php" class="needs-validation" novalidate>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Username *</label>
                    <input type="text" name="username" class="form-control" value="<?= $username; ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?= $email; ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Password *</label>
                    <input type="password" name="password" class="form-control" required minlength="4">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Confirm Password *</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Role *</label>
                    <select name="role" class="form-select" required>
                        <option value="Staff" <?= ($role === 'Staff') ? 'selected' : ''; ?>>Staff</option>
                        <option value="Super Admin" <?= ($role === 'Super Admin') ? 'selected' : ''; ?>>Super Admin</option>
                    </select>
                </div>
                <div class="col-12 mt-4">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Save User</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>