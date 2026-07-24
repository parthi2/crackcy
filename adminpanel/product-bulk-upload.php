<?php
require_once __DIR__ . '/../config/database.php';
requireAdminLogin();

// -------------------------------------------------------------
// 1. GENERATE & DOWNLOAD SAMPLE CSV TEMPLATE
// -------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'sample') {
    $filename = "sample_products_upload_" . date('Y-m-d') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // UTF-8 BOM for Excel
    fputs($output, $bom = chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Headers matching your Database Schema
    fputcsv($output, ['Product Name', 'Category', 'SKU', 'Price', 'Description', 'Status']);

    // Sample Row Examples
    fputcsv($output, ['Sky Rocket (Special)', 'Sky cracker', 'SKY-101', '250.00', 'High flying sky rocket cracker', '1']);
    fputcsv($output, ['Flower Pot Deluxe', 'Flower pot', 'FLW-002', '150.00', 'Sparkling ground flower pot', '1']);
    fputcsv($output, ['1000 Wala Crackers', 'Wala', 'WALA-1000', '1499.00', '1000 shots sound cracker chain', '1']);

    fclose($output);
    exit;
}

// -------------------------------------------------------------
// 2. PROCESS CSV FILE UPLOAD & BULK INSERT/UPDATE
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_bulk_upload'])) {
    
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['flash_error'] = "Please select a valid CSV file.";
        redirect("products");
    }

    $fileTmpPath = $_FILES['csv_file']['tmp_name'];
    $fileName    = $_FILES['csv_file']['name'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if ($fileExtension !== 'csv') {
        $_SESSION['flash_error'] = "Invalid file type. Please upload a .csv file.";
        redirect("products");
    }

    $handle = fopen($fileTmpPath, "r");
    if ($handle === false) {
        $_SESSION['flash_error'] = "Failed to open uploaded CSV file.";
        redirect("products");
    }

    // Skip UTF-8 BOM if present
    fseek($handle, 0);
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        fseek($handle, 0);
    }

    // Read Header Row
    $headers = fgetcsv($handle, 1000, ",");

    $insertedCount = 0;
    $updatedCount  = 0;
    $skippedCount  = 0;

    try {
        $pdo->beginTransaction();

        // Prepare Upsert SQL (Inserts new products, or updates price/description if SKU exists)
        $stmt = $pdo->prepare("
            INSERT INTO products (product_name, product_category, sku, price, description, status)
            VALUES (:name, :category, :sku, :price, :description, :status)
            ON DUPLICATE KEY UPDATE
                product_name = VALUES(product_name),
                product_category = VALUES(product_category),
                price = VALUES(price),
                description = VALUES(description),
                status = VALUES(status)
        ");

        while (($row = fgetcsv($handle, 1000, ",")) !== false) {
            // Ignore empty rows
            if (empty(array_filter($row))) continue;

            $productName = sanitize($row[0] ?? '');
            $category    = sanitize($row[1] ?? 'General');
            $sku         = sanitize($row[2] ?? '');
            $price       = (float)($row[3] ?? 0);
            $description = sanitize($row[4] ?? '');
            
            // Map status string or integer ('1', 'Active', '0', 'Inactive')
            $rawStatus   = strtolower(trim($row[5] ?? '1'));
            $status      = in_array($rawStatus, ['1', 'active', 'true']) ? 1 : 0;

            if (empty($productName) || empty($sku)) {
                $skippedCount++;
                continue;
            }

            // Check if SKU exists to track insert vs update count
            $checkStmt = $pdo->prepare("SELECT id FROM products WHERE sku = :sku LIMIT 1");
            $checkStmt->execute([':sku' => $sku]);
            $exists = $checkStmt->fetchColumn();

            $stmt->execute([
                ':name'        => $productName,
                ':category'    => $category,
                ':sku'         => $sku,
                ':price'       => $price,
                ':description' => $description,
                ':status'      => $status
            ]);

            if ($exists) {
                $updatedCount++;
            } else {
                $insertedCount++;
            }
        }

        $pdo->commit();
        fclose($handle);

        $_SESSION['flash_success'] = "Bulk upload successful! <strong>{$insertedCount}</strong> new products added, <strong>{$updatedCount}</strong> updated.";
        if ($skippedCount > 0) {
            $_SESSION['flash_warning'] = "{$skippedCount} rows skipped due to missing Product Name or SKU.";
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        fclose($handle);
        $_SESSION['flash_error'] = "Bulk Upload Failed: " . $e->getMessage();
    }

    redirect("products");
}