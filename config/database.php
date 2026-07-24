<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Database {
    private string $host = "localhost";
    private string $db_name = "shopping_cart_db";
    private string $username = "root";
    private string $password = "";
    public ?PDO $conn = null;

    public function getConnection(): ?PDO {
        if ($this->conn === null) {
            try {
                $this->conn = new PDO(
                    "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                    $this->username,
                    $this->password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );
            } catch (PDOException $exception) {
                die("Database Connection Error: " . $exception->getMessage());
            }
        }
        return $this->conn;
    }
}

$dbObj = new Database();
$pdo = $dbObj->getConnection();

function sanitize(string $data): string {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
    header("Location: " . $url);
    exit;
}

function setFlash(string $type, string $message): void {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

function displayFlash(): void {
    if (isset($_SESSION['flash_message'])) {
        $type = $_SESSION['flash_message']['type'];
        $msg = $_SESSION['flash_message']['message'];
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: '{$type}',
                    title: '" . (($type === 'success') ? 'Success' : 'Notice') . "',
                    text: '{$msg}',
                    timer: 3000,
                    showConfirmButton: false
                });
            });
        </script>";
        unset($_SESSION['flash_message']);
    }
}

function isAdminLoggedIn(): bool {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function requireAdminLogin(): void {
    if (!isAdminLoggedIn()) {
        setFlash('error', 'Please log in to access the administration panel.');
        redirect('../adminpanel/login.php');
    }
}

function getCartCount(): int {
    $count = 0;
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $count += $item['quantity'];
        }
    }
    return $count;
}
