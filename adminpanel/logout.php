<?php
require_once __DIR__ . '/../config/database.php';
unset($_SESSION['admin_logged_in']);
unset($_SESSION['admin_id']);
unset($_SESSION['admin_username']);
session_destroy();
header("Location: login.php");
exit;
