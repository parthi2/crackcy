<?php
require_once __DIR__ . '/../config/database.php';
requireAdminLogin();

// --- Read Filter Parameters ---
$search       = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$fromDate     = isset($_GET['from_date']) ? sanitize($_GET['from_date']) : '';
$toDate       = isset($_GET['to_date']) ? sanitize($_GET['to_date']) : '';

$allowedSortColumns = [
    'order_no'      => 'order_no',
    'customer_name' => 'customer_name',
    'phone'         => 'phone',
    'total_amount'  => 'total_amount',
    'order_status'  => 'order_status',
    'created_at'    => 'created_at'
];

$sortColumn = isset($_GET['sort']) && isset($allowedSortColumns[$_GET['sort']]) ? $_GET['sort'] : 'created_at';
$sortOrder  = isset($_GET['order']) && strtolower($_GET['order']) === 'asc' ? 'ASC' : 'DESC';

// --- Build SQL Query ---
$where  = ["1=1"];
$params = [];

if ($search !== '') {
    $where[] = "(order_no LIKE :s1 OR customer_name LIKE :s2 OR phone LIKE :s3)";
    $params[':s1'] = "%{$search}%";
    $params[':s2'] = "%{$search}%";
    $params[':s3'] = "%{$search}%";
}

if ($statusFilter !== '') {
    $where[] = "order_status = :status";
    $params[':status'] = $statusFilter;
}

if ($fromDate !== '') {
    $where[] = "DATE(created_at) >= :from_date";
    $params[':from_date'] = $fromDate;
}

if ($toDate !== '') {
    $where[] = "DATE(created_at) <= :to_date";
    $params[':to_date'] = $toDate;
}

$whereSql = implode(' AND ', $where);

$sql = "SELECT * FROM orders WHERE {$whereSql} ORDER BY {$allowedSortColumns[$sortColumn]} {$sortOrder}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// --- Export CSV Headers ---
$filename = "orders_export_" . date('Y-m-d_H-i-s') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fputs($output, $bom = chr(0xEF) . chr(0xBB) . chr(0xBF));

// CSV Column Headers
fputcsv($output, [
    'Order No', 
    'Customer Name', 
    'Phone', 
    'Email', 
    'Address', 
    'City', 
    'State', 
    'Pincode', 
    'Subtotal Amount', 
    'GST Percent', 
    'GST Amount', 
    'Total Amount', 
    'Status', 
    'Date Placed'
]);

// Write Rows
foreach ($orders as $row) {
    fputcsv($output, [
        $row['order_no'],
        $row['customer_name'],
        $row['phone'],
        $row['email'] ?? '',
        $row['address'] ?? '',
        $row['city'] ?? '',
        $row['state'] ?? '',
        $row['pincode'] ?? '',
        number_format((float)($row['subtotal_amount'] ?? $row['total_amount']), 2, '.', ''),
        number_format((float)($row['gst_percent'] ?? 0), 2, '.', ''),
        number_format((float)($row['gst_amount'] ?? 0), 2, '.', ''),
        number_format((float)$row['total_amount'], 2, '.', ''),
        $row['order_status'],
        date('Y-m-d H:i:s', strtotime($row['created_at']))
    ]);
}

fclose($output);
exit;