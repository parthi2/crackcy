<?php
require_once __DIR__ . '/../config/database.php';
requireAdminLogin();

// -------------------------------------------------------------
// 1. HANDLE SINGLE PRODUCT DELETE
// -------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $deleteId = (int)$_GET['id'];

    $pStmt = $pdo->prepare("SELECT image FROM products WHERE id = :id LIMIT 1");
    $pStmt->execute([':id' => $deleteId]);
    $img = $pStmt->fetchColumn();

    if (!empty($img)) {
        $imgPath = __DIR__ . '/../uploads/products/' . $img;
        if (file_exists($imgPath)) {
            @unlink($imgPath);
        }
    }

    $delStmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
    $delStmt->execute([':id' => $deleteId]);

    $_SESSION['flash_success'] = "Product deleted successfully.";
    redirect("products");
}

// -------------------------------------------------------------
// 2. HANDLE BULK PRODUCT DELETE
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete_submit'])) {
    $productIds = $_POST['product_ids'] ?? [];

    if (!empty($productIds) && is_array($productIds)) {
        $cleanIds = array_map('intval', $productIds);
        $inClause = implode(',', array_fill(0, count($cleanIds), '?'));

        $imgStmt = $pdo->prepare("SELECT image FROM products WHERE id IN ($inClause)");
        $imgStmt->execute($cleanIds);
        $images = $imgStmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($images as $img) {
            if (!empty($img)) {
                $imgPath = __DIR__ . '/../uploads/products/' . $img;
                if (file_exists($imgPath)) {
                    @unlink($imgPath);
                }
            }
        }

        $delBulkStmt = $pdo->prepare("DELETE FROM products WHERE id IN ($inClause)");
        $delBulkStmt->execute($cleanIds);

        $deletedCount = count($cleanIds);
        $_SESSION['flash_success'] = "Successfully deleted <strong>{$deletedCount}</strong> selected products!";
    } else {
        $_SESSION['flash_error'] = "No products were selected for deletion.";
    }

    redirect("products");
}

// -------------------------------------------------------------
// 3. FETCH ALL PRODUCTS FOR CLIENT-SIDE HANDLING
// -------------------------------------------------------------
$stmt = $pdo->query("SELECT * FROM products ORDER BY id DESC");
$products = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <h2 class="fw-bold m-0"><i class="fa-solid fa-boxes-stacked me-2"></i>Product Management</h2>
    
    <div class="d-flex align-items-center gap-2">
        <!-- Bulk Delete Action Button -->
        <button type="button" id="btnBulkDelete" class="btn btn-danger fw-bold" disabled onclick="confirmBulkDelete()">
            <i class="fa-solid fa-trash-can me-1"></i> Delete Selected (<span id="selectedCount">0</span>)
        </button>

        <!-- Download Sample CSV -->
        <a href="product-bulk-upload.php?action=sample" class="btn btn-outline-secondary fw-bold">
            <i class="fa-solid fa-file-csv me-1"></i> Sample CSV
        </a>

        <!-- Bulk Upload Trigger -->
        <button type="button" class="btn btn-success fw-bold" data-bs-toggle="modal" data-bs-target="#bulkUploadModal">
            <i class="fa-solid fa-file-import me-1"></i> Bulk Upload
        </button>

        <!-- Add Single Product -->
        <a href="product-add" class="btn btn-primary fw-bold">
            <i class="fa-solid fa-plus me-1"></i> Add New Product
        </a>
    </div>
</div>

<!-- Flash Messages -->
<?php if (isset($_SESSION['flash_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
        <i class="fa-solid fa-circle-check me-2"></i><?= $_SESSION['flash_success']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
        <i class="fa-solid fa-circle-exclamation me-2"></i><?= $_SESSION['flash_error']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<!-- Top Toolbar: Page Size Selector & Live Client Search -->
<div class="card shadow-sm border-0 rounded-4 mb-3">
    <div class="card-body p-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <!-- Page Size Dropdown -->
        <div class="d-flex align-items-center gap-2">
            <label class="small fw-bold text-muted mb-0">Show</label>
            <select id="pageSizeSelect" class="form-select form-select-sm fw-bold style-select" style="width: 80px;">
                <option value="10" selected>10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
            <label class="small fw-bold text-muted mb-0">entries</label>
        </div>

        <!-- Real-time Client Search Input -->
        <div class="d-flex align-items-center gap-2" style="width: 250px;">
            <label for="tableSearchInput" class="small fw-bold text-muted mb-0">Search:</label>
            <input type="text" id="tableSearchInput" class="form-control form-control-sm" placeholder="Type to search...">
        </div>
    </div>
</div>

<!-- Main Form Wrapper for Bulk Delete -->
<form id="bulkDeleteForm" action="products" method="POST">
    <input type="hidden" name="bulk_delete_submit" value="1">

    <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="productsTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width: 40px;">
                                <input class="form-check-input" type="checkbox" id="selectAll">
                            </th>
                            <th style="width: 80px;">Image</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>SKU</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="productsTableBody">
                        <?php if (empty($products)): ?>
                            <tr id="noDataRow">
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-box-open fa-2x mb-2 d-block"></i>
                                    No products found in database.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $p): 
                                $searchData = strtolower(sanitize($p['product_name'] . ' ' . $p['product_category'] . ' ' . $p['sku']));
                            ?>
                                <tr class="product-row" data-search="<?= $searchData; ?>">
                                    <td class="ps-3">
                                        <input class="form-check-input product-select-chk" type="checkbox" name="product_ids[]" value="<?= $p['id']; ?>">
                                    </td>
                                    <td>
                                        <div class="rounded-3 border overflow-hidden bg-light" style="width: 48px; height: 48px;">
                                            <?php 
                                            $imgPath = !empty($p['image']) && file_exists(__DIR__ . '/../uploads/products/' . $p['image'])
                                                ? '../uploads/products/' . $p['image']
                                                : '../assets/image/no-image.jpg';
                                            ?>
                                            <img src="<?= $imgPath; ?>" alt="<?= sanitize($p['product_name']); ?>" class="w-100 h-100 object-fit-cover">
                                        </div>
                                    </td>
                                    <td class="fw-bold text-dark"><?= sanitize($p['product_name']); ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= sanitize($p['product_category']); ?></span></td>
                                    <td><code class="text-danger fw-semibold"><?= sanitize($p['sku']); ?></code></td>
                                    <td class="fw-bold">₹<?= number_format($p['price'], 2); ?></td>
                                    <td>
                                        <?php if ($p['status'] == 1): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <a href="product-edit?id=<?= $p['id']; ?>" class="btn btn-warning btn-sm fw-bold me-1" title="Edit">
                                            <i class="fa-solid fa-pen"></i>
                                        </a>
                                        <a href="products?action=delete&id=<?= $p['id']; ?>" class="btn btn-danger btn-sm fw-bold" onclick="return confirm('Are you sure you want to delete this product?');" title="Delete">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <!-- Dynamic No Matches Fallback -->
                        <tr id="noMatchRow" style="display: none;">
                            <td colspan="8" class="text-center py-4 text-muted fw-semibold">
                                <i class="fa-solid fa-magnifying-glass me-2"></i>No matching products found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</form>

<!-- Bottom Pagination Bar -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="small fw-semibold text-muted" id="paginationInfo">
        Showing 0 to 0 of 0 entries
    </div>
    <nav>
        <ul class="pagination pagination-sm mb-0" id="paginationControls"></ul>
    </nav>
</div>

<!-- Confirmation Modal before Bulk Delete -->
<div class="modal fade" id="confirmBulkDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-triangle-exclamation me-2"></i>Warning: Delete Products</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="text-danger mb-3"><i class="fa-solid fa-trash-can fa-3x"></i></div>
                <h5 class="fw-bold text-dark mb-2">Are you permanently sure?</h5>
                <p class="text-muted mb-0">
                    You are about to permanently delete <strong id="modalDeleteCount" class="text-danger">0</strong> selected product(s).
                </p>
            </div>
            <div class="modal-footer bg-light justify-content-center">
                <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger fw-bold px-4" onclick="executeBulkDelete()">Yes, Delete All Selected</button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Upload Modal -->
<div class="modal fade" id="bulkUploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-file-csv me-2"></i>Bulk Upload Products</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="product-bulk-upload.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select CSV File (.csv)</label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="submit_bulk_upload" class="btn btn-success btn-sm fw-bold">Upload CSV</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('tableSearchInput');
    const pageSizeSelect = document.getElementById('pageSizeSelect');
    const allRows = Array.from(document.querySelectorAll('.product-row'));
    const noMatchRow = document.getElementById('noMatchRow');
    const paginationInfo = document.getElementById('paginationInfo');
    const paginationControls = document.getElementById('paginationControls');

    let currentPage = 1;
    let pageSize = parseInt(pageSizeSelect.value) || 10;
    let filteredRows = [...allRows];

    // Filter & Paginate Execution
    function updateTable() {
        const searchTerm = searchInput.value.toLowerCase().trim();

        // 1. Filter rows
        filteredRows = allRows.filter(row => {
            const data = row.getAttribute('data-search') || '';
            return data.includes(searchTerm);
        });

        // 2. Hide all rows initially
        allRows.forEach(row => row.style.display = 'none');

        const totalEntries = filteredRows.length;
        const totalPages = Math.ceil(totalEntries / pageSize) || 1;

        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        // 3. Show rows for current page
        const startIdx = (currentPage - 1) * pageSize;
        const endIdx = startIdx + pageSize;
        const visibleRows = filteredRows.slice(startIdx, endIdx);

        visibleRows.forEach(row => row.style.display = '');

        // 4. Show/Hide No Match Row
        if (noMatchRow) {
            noMatchRow.style.display = (totalEntries === 0 && allRows.length > 0) ? '' : 'none';
        }

        // 5. Update Entry Info Text
        const startEntry = totalEntries === 0 ? 0 : startIdx + 1;
        const endEntry = Math.min(endIdx, totalEntries);
        paginationInfo.textContent = `Showing ${startEntry} to ${endEntry} of ${totalEntries} entries`;

        // 6. Render Pagination Controls
        renderPagination(totalPages);
        updateBulkDeleteButton();
    }

    function renderPagination(totalPages) {
        paginationControls.innerHTML = '';
        if (totalPages <= 1) return;

        // Prev Button
        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
        prevLi.innerHTML = `<a class="page-link" href="#">Previous</a>`;
        prevLi.addEventListener('click', (e) => {
            e.preventDefault();
            if (currentPage > 1) {
                currentPage--;
                updateTable();
            }
        });
        paginationControls.appendChild(prevLi);

        // Page Numbers
        for (let i = 1; i <= totalPages; i++) {
            const li = document.createElement('li');
            li.className = `page-item ${i === currentPage ? 'active' : ''}`;
            li.innerHTML = `<a class="page-link" href="#">${i}</a>`;
            li.addEventListener('click', (e) => {
                e.preventDefault();
                currentPage = i;
                updateTable();
            });
            paginationControls.appendChild(li);
        }

        // Next Button
        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
        nextLi.innerHTML = `<a class="page-link" href="#">Next</a>`;
        nextLi.addEventListener('click', (e) => {
            e.preventDefault();
            if (currentPage < totalPages) {
                currentPage++;
                updateTable();
            }
        });
        paginationControls.appendChild(nextLi);
    }

    // Event Listeners for Live Search & Page Size
    searchInput.addEventListener('keyup', () => {
        currentPage = 1;
        updateTable();
    });

    pageSizeSelect.addEventListener('change', () => {
        pageSize = parseInt(pageSizeSelect.value);
        currentPage = 1;
        updateTable();
    });

    // Checkbox & Bulk Delete Handler
    const selectAllChk = document.getElementById('selectAll');
    const rowCheckboxes = document.querySelectorAll('.product-select-chk');
    const btnBulkDelete = document.getElementById('btnBulkDelete');
    const selectedCountSpan = document.getElementById('selectedCount');
    const modalDeleteCount = document.getElementById('modalDeleteCount');

    function updateBulkDeleteButton() {
        const checkedBoxes = document.querySelectorAll('.product-select-chk:checked');
        const count = checkedBoxes.length;

        selectedCountSpan.textContent = count;
        if (modalDeleteCount) modalDeleteCount.textContent = count;

        if (count > 0) {
            btnBulkDelete.removeAttribute('disabled');
        } else {
            btnBulkDelete.setAttribute('disabled', 'disabled');
        }

        if (selectAllChk) {
            selectAllChk.checked = (count > 0 && count === rowCheckboxes.length);
        }
    }

    if (selectAllChk) {
        selectAllChk.addEventListener('change', function () {
            rowCheckboxes.forEach(chk => {
                // Only select currently visible/filtered rows
                if (chk.closest('tr').style.display !== 'none') {
                    chk.checked = this.checked;
                }
            });
            updateBulkDeleteButton();
        });
    }

    rowCheckboxes.forEach(chk => {
        chk.addEventListener('change', updateBulkDeleteButton);
    });

    // Initial Load
    updateTable();
});

function confirmBulkDelete() {
    const checkedBoxes = document.querySelectorAll('.product-select-chk:checked');
    if (checkedBoxes.length === 0) return;

    const modal = new bootstrap.Modal(document.getElementById('confirmBulkDeleteModal'));
    modal.show();
}

function executeBulkDelete() {
    document.getElementById('bulkDeleteForm').submit();
}
</script>

<?php require_once 'includes/footer.php'; ?>