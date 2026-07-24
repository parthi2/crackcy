<?php
require_once __DIR__ . '/../../config/database.php';
requireAdminLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cracksy Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        .sidebar { min-height: 100vh; background-color: #212529; }
        .sidebar .nav-link { color: #adb5bd; margin-bottom: 0.2rem; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background-color: #0d6efd; border-radius: 0.375rem; }
    </style>
</head>
<body class="bg-light">

<div class="container-fluid">
    <div class="row">
        <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse p-3">
            <a href="index.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
                <i class="fa-solid fa-gauge-high fs-4 me-2 text-warning"></i>
                <span class="fs-5 fw-bold">Admin Panel</span>
            </a>
            <hr class="text-secondary">
            <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item">
                    <a href="index.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-chart-pie me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="order-settings.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'order-settings.php') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-gear me-2"></i> Order Settings
                    </a>
                </li>
                <li class="nav-item">
                    <a href="categories.php" class="nav-link <?= (strpos($_SERVER['PHP_SELF'], 'categor') !== false) ? 'active' : ''; ?>">
                        <i class="fa-solid fa-list me-2"></i> Categories
                    </a>
                </li>
                <li class="nav-item">
                    <a href="products.php" class="nav-link <?= (strpos($_SERVER['PHP_SELF'], 'product') !== false) ? 'active' : ''; ?>">
                        <i class="fa-solid fa-box-open me-2"></i> Products
                    </a>
                </li>
                <li class="nav-item">
                    <a href="orders.php" class="nav-link <?= (in_array(basename($_SERVER['PHP_SELF']), ['orders.php', 'order-view.php', 'order-edit.php'])) ? 'active' : ''; ?>">
                        <i class="fa-solid fa-receipt me-2"></i> Orders
                    </a>
                </li>
                <li class="nav-item">
                    <a href="users.php" class="nav-link <?= (strpos($_SERVER['PHP_SELF'], 'user') !== false) ? 'active' : ''; ?>">
                        <i class="fa-solid fa-users me-2"></i> Users
                    </a>
                </li>
                <li class="nav-item">
                    <a href="change-password.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'change-password.php') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-key me-2"></i> Change Password
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../index.php" target="_blank" class="nav-link">
                        <i class="fa-solid fa-store me-2"></i> View Frontend
                    </a>
                </li>
            </ul>
            <hr class="text-secondary">
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown">
                    <i class="fa-solid fa-user-gear me-2"></i>
                    <strong><?= sanitize($_SESSION['admin_username'] ?? 'Admin'); ?></strong>
                </a>
                <ul class="dropdown-menu dropdown-menu-dark text-small shadow">
                    <li><a class="dropdown-item" href="logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i> Sign out</a></li>
                </ul>
            </div>
        </nav>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
        <?php displayFlash(); ?>
