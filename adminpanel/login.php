<?php
require_once __DIR__ . '/../config/database.php';

if (isAdminLoggedIn()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {    
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        // Query the 'admin' table
        $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = :username LIMIT 1");
        $stmt->execute([':username' => $username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            // Check if user account is disabled
            if (isset($admin['status']) && $admin['status'] == 0) {
                $error = "Your account has been disabled by the administrator.";
            } else {
                // Successful Login
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id']        = $admin['id'];
                $_SESSION['admin_username']  = $admin['username'];
                $_SESSION['user_id']         = $admin['id'];
                
                setFlash('success', 'Logged in successfully.');
                redirect('index.php');
            }
        } else {
            $error = "Invalid username or password credentials.";
        }
    } else {
        $error = "Please fill in all required fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - RetailStore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-secondary d-flex align-items-center min-vh-100">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="card shadow-lg border-0 rounded-3">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <i class="fa-solid fa-user-shield fa-3x text-primary mb-2"></i>
                        <h4 class="fw-bold">Admin Portal Login</h4>
                        <p class="text-muted small">Sign in to manage store inventory & orders</p>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger py-2 small"><?= $error; ?></div>
                    <?php endif; ?>

                    <form method="POST" action="login.php">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" placeholder="Enter username" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 fw-bold">Sign In</button>
                    </form>
                    <div class="text-center mt-3">
                        <a href="../index.php" class="text-decoration-none small text-muted"><i class="fa-solid fa-arrow-left"></i> Return to Frontend Store</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>