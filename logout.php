<?php
require_once 'config/database.php';
unset($_SESSION['admin_logged_in']);
unset($_SESSION['admin_username']);
session_destroy();
header("Location: index.php");
exit;
