<?php
require_once 'includes/header.php';

// --- Server-Side Parameters ---
$search       = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$fromDate     = isset($_GET['from_date']) ? sanitize($_GET['from_date']) : '';
$toDate       = isset($_GET['to_date']) ? sanitize($_GET['to_date']) : '';
$page         = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit        = isset($_GET['limit']) ? max(5, min(100, (int)$_GET['limit'])) : 10;

// Allowed sort columns
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

function getSortUrl($column, $currentColumn, $currentOrder, $search, $statusFilter, $fromDate, $toDate, $limit) {
    $nextOrder = ($column === $currentColumn && $currentOrder === 'ASC') ? 'desc' : 'asc';
    return "orders.php?" . http_build_query([
        'search'    => $search,
        'status'    => $statusFilter,
        'from_date' => $fromDate,
        'to_date'   => $toDate,
        'sort'      => $column,
        'order'     => $nextOrder,
        'limit'     => $limit,
        'page'      => 1
    ]);
}

// --- Query Builder ---
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

// Export Query String
$exportQueryStr = http_build_query([
    'search'    => $search,
    'status'    => $statusFilter,
    'from_date' => $fromDate,
    'to_date'   => $toDate,
    'sort'      => $sortColumn,
    'order'     => strtolower($sortOrder)
]);

// 1. Get Total Count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE {$whereSql}");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();

// Pagination Math
$totalPages = max(1, ceil($totalRecords / $limit));
if ($page > $totalPages) { $page = $totalPages; }
$offset = ($page - 1) * $limit;

// 2. Fetch Data
$sql = "SELECT * FROM orders WHERE {$whereSql} ORDER BY {$allowedSortColumns[$sortColumn]} {$sortOrder} LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);

foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();

$orders = $stmt->fetchAll();
$hasActiveFilters = ($search !== '' || $statusFilter !== '' || $fromDate !== '' || $toDate !== '');
$hasDateFilters   = ($fromDate !== '' || $toDate !== '');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold"><i class="fa-solid fa-receipt me-2"></i>Order Management</h2>
    <a href="export-orders.php?<?= $exportQueryStr; ?>" class="btn btn-success fw-bold">
        <i class="fa-solid fa-file-csv me-1"></i> Export Orders (CSV)
    </a>
</div>

<!-- Search & Filter Controls -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="GET" action="orders.php" id="filterForm">
            <input type="hidden" name="sort" value="<?= $sortColumn; ?>">
            <input type="hidden" name="order" value="<?= strtolower($sortOrder); ?>">

            <div class="row g-2 align-items-end">
                <!-- Search Keyword -->
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted mb-1">Search Keyword</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Order No, Customer, Phone..." value="<?= sanitize($search); ?>">
                </div>

                <!-- Order Status -->
                <div class="col-md-2">
                    <label class="form-label fw-bold small text-muted mb-1">Order Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">-- All Statuses --</option>
                        <?php foreach (['Pending', 'Confirmed', 'Packed', 'Shipped', 'Delivered', 'Cancelled'] as $st): ?>
                            <option value="<?= $st; ?>" <?= ($statusFilter === $st) ? 'selected' : ''; ?>><?= $st; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- From Date -->
                <div class="col-md-2">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label fw-bold small text-muted mb-0">From Date</label>
                        <?php if ($fromDate !== ''): ?>
                            <a href="javascript:void(0)" onclick="clearSingleDate('from_date')" class="text-danger text-decoration-none fw-bold" style="font-size: 0.72rem;">Clear From</a>
                        <?php endif; ?>
                    </div>
                    <div class="input-group input-group-sm">
                        <input type="date" name="from_date" id="from_date" class="form-control form-control-sm" value="<?= sanitize($fromDate); ?>">
                        <?php if ($fromDate !== ''): ?>
                            <button type="button" class="btn btn-outline-danger" onclick="clearSingleDate('from_date')" title="Remove From Date">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- To Date -->
                <div class="col-md-2">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label fw-bold small text-muted mb-0">To Date</label>
                        <?php if ($toDate !== ''): ?>
                            <a href="javascript:void(0)" onclick="clearSingleDate('to_date')" class="text-danger text-decoration-none fw-bold" style="font-size: 0.72rem;">Clear To</a>
                        <?php endif; ?>
                    </div>
                    <div class="input-group input-group-sm">
                        <input type="date" name="to_date" id="to_date" class="form-control form-control-sm" value="<?= sanitize($toDate); ?>">
                        <?php if ($toDate !== ''): ?>
                            <button type="button" class="btn btn-outline-danger" onclick="clearSingleDate('to_date')" title="Remove To Date">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Rows per page -->
                <div class="col-md-1">
                    <label class="form-label fw-bold small text-muted mb-1">Rows</label>
                    <select name="limit" class="form-select form-select-sm" onchange="this.form.submit()">
                        <?php foreach ([5, 10, 25, 50, 100] as $lim): ?>
                            <option value="<?= $lim; ?>" <?= ($limit === $lim) ? 'selected' : ''; ?>><?= $lim; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Apply & Reset Buttons -->
                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm fw-bold w-100">
                        <i class="fa-solid fa-filter me-1"></i> Apply
                    </button>
                    <?php if ($hasActiveFilters): ?>
                        <a href="orders.php" class="btn btn-outline-secondary btn-sm" title="Reset All Filters">
                            <i class="fa-solid fa-arrow-rotate-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Data Grid Table -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light border-bottom">
                    <tr>
                        <th>
                            <a href="<?= getSortUrl('order_no', $sortColumn, $sortOrder, $search, $statusFilter, $fromDate, $toDate, $limit); ?>" class="text-dark text-decoration-none fw-bold">
                                Order No 
                                <?php if ($sortColumn === 'order_no'): ?>
                                    <i class="fa-solid fa-sort-<?= $sortOrder === 'ASC' ? 'up' : 'down'; ?> text-primary ms-1"></i>
                                <?php else: ?>
                                    <i class="fa-solid fa-sort text-muted ms-1 small"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= getSortUrl('customer_name', $sortColumn, $sortOrder, $search, $statusFilter, $fromDate, $toDate, $limit); ?>" class="text-dark text-decoration-none fw-bold">
                                Customer 
                                <?php if ($sortColumn === 'customer_name'): ?>
                                    <i class="fa-solid fa-sort-<?= $sortOrder === 'ASC' ? 'up' : 'down'; ?> text-primary ms-1"></i>
                                <?php else: ?>
                                    <i class="fa-solid fa-sort text-muted ms-1 small"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= getSortUrl('phone', $sortColumn, $sortOrder, $search, $statusFilter, $fromDate, $toDate, $limit); ?>" class="text-dark text-decoration-none fw-bold">
                                Phone 
                                <?php if ($sortColumn === 'phone'): ?>
                                    <i class="fa-solid fa-sort-<?= $sortOrder === 'ASC' ? 'up' : 'down'; ?> text-primary ms-1"></i>
                                <?php else: ?>
                                    <i class="fa-solid fa-sort text-muted ms-1 small"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= getSortUrl('total_amount', $sortColumn, $sortOrder, $search, $statusFilter, $fromDate, $toDate, $limit); ?>" class="text-dark text-decoration-none fw-bold">
                                Amount 
                                <?php if ($sortColumn === 'total_amount'): ?>
                                    <i class="fa-solid fa-sort-<?= $sortOrder === 'ASC' ? 'up' : 'down'; ?> text-primary ms-1"></i>
                                <?php else: ?>
                                    <i class="fa-solid fa-sort text-muted ms-1 small"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= getSortUrl('order_status', $sortColumn, $sortOrder, $search, $statusFilter, $fromDate, $toDate, $limit); ?>" class="text-dark text-decoration-none fw-bold">
                                Status 
                                <?php if ($sortColumn === 'order_status'): ?>
                                    <i class="fa-solid fa-sort-<?= $sortOrder === 'ASC' ? 'up' : 'down'; ?> text-primary ms-1"></i>
                                <?php else: ?>
                                    <i class="fa-solid fa-sort text-muted ms-1 small"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= getSortUrl('created_at', $sortColumn, $sortOrder, $search, $statusFilter, $fromDate, $toDate, $limit); ?>" class="text-dark text-decoration-none fw-bold">
                                Date 
                                <?php if ($sortColumn === 'created_at'): ?>
                                    <i class="fa-solid fa-sort-<?= $sortOrder === 'ASC' ? 'up' : 'down'; ?> text-primary ms-1"></i>
                                <?php else: ?>
                                    <i class="fa-solid fa-sort text-muted ms-1 small"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th class="text-end fw-bold pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">No orders match your criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($orders as $o): ?>
                            <tr>
                                <td class="fw-bold"><?= sanitize($o['order_no']); ?></td>
                                <td><?= sanitize($o['customer_name']); ?></td>
                                <td><?= sanitize($o['phone']); ?></td>
                                <td class="fw-bold">₹<?= number_format($o['total_amount'], 2); ?></td>
                                <td>
                                    <?php 
                                    $badge = match($o['order_status']) {
                                        'Pending'   => 'bg-warning text-dark',
                                        'Confirmed' => 'bg-info text-white',
                                        'Packed'    => 'bg-primary text-white',
                                        'Shipped'   => 'bg-secondary text-white',
                                        'Delivered' => 'bg-success text-white',
                                        'Cancelled' => 'bg-danger text-white',
                                        default     => 'bg-light text-dark'
                                    };
                                    ?>
                                    <span class="badge <?= $badge; ?> px-2 py-1"><?= $o['order_status']; ?></span>
                                </td>
                                <td><?= date('d M Y, h:i A', strtotime($o['created_at'])); ?></td>
                                <td class="text-end pe-3">
                                    <a href="order-view.php?id=<?= $o['id']; ?>" class="btn btn-sm btn-info text-white fw-bold"><i class="fa-solid fa-eye me-1"></i> View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Server-Side Pagination Bar -->
    <div class="card-footer bg-white border-top d-flex flex-wrap justify-content-between align-items-center py-3">
        <div class="text-muted small">
            Showing <strong><?= min($offset + 1, $totalRecords); ?></strong> to <strong><?= min($offset + $limit, $totalRecords); ?></strong> of <strong><?= $totalRecords; ?></strong> orders
        </div>

        <?php if ($totalPages > 1): ?>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="orders.php?<?= http_build_query(array_merge($_GET, ['page' => 1])); ?>"><i class="fa-solid fa-angles-left"></i></a>
                    </li>
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="orders.php?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"><i class="fa-solid fa-chevron-left"></i></a>
                    </li>

                    <?php 
                    $startPage = max(1, $page - 2);
                    $endPage   = min($totalPages, $page + 2);

                    for ($p = $startPage; $p <= $endPage; $p++): 
                    ?>
                        <li class="page-item <?= ($p === $page) ? 'active' : ''; ?>">
                            <a class="page-link" href="orders.php?<?= http_build_query(array_merge($_GET, ['page' => $p])); ?>"><?= $p; ?></a>
                        </li>
                    <?php endfor; ?>

                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="orders.php?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"><i class="fa-solid fa-chevron-right"></i></a>
                    </li>
                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="orders.php?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>"><i class="fa-solid fa-angles-right"></i></a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<script>
function clearSingleDate(inputId) {
    document.getElementById(inputId).value = '';
    document.getElementById('filterForm').submit();
}
</script>

<?php require_once 'includes/footer.php'; ?>