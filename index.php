<?php
// 1. INCLUDE DATABASE & SESSION CONFIGURATION FIRST
require_once 'config/database.php';

// Initialize session cart if not existing
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// -------------------------------------------------------------
// 🎨 THEME SELECTOR CONFIGURATION (Options 1 to 6)
// -------------------------------------------------------------
$defaultTheme = 4; // Factory Emerald Default
$currentTheme = isset($_GET['theme']) ? (int)$_GET['theme'] : $defaultTheme;
if ($currentTheme < 1 || $currentTheme > 6) {
    $currentTheme = 4;
}

// Fetch all active products (Client-side handles instant search & sorting)
$stmt = $pdo->query("SELECT * FROM products WHERE status = 1 ORDER BY product_category ASC, id ASC");
$allProducts = $stmt->fetchAll();

// Group products by category for default view
$groupedProducts = [];
foreach ($allProducts as $p) {
    $groupedProducts[$p['product_category']][] = $p;
}

$errors = [];

// 2. HANDLE ORDER / ESTIMATE SUBMISSION & REDIRECT BEFORE HTML OUTPUT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_estimate'])) {
    $customer_name = sanitize($_POST['name'] ?? '');
    $phone         = sanitize($_POST['phone'] ?? '');
    $email         = sanitize($_POST['email'] ?? '');
    $address       = sanitize($_POST['address'] ?? '');
    $city          = sanitize($_POST['city'] ?? '');
    $state         = sanitize($_POST['state'] ?? '');
    $pincode       = sanitize($_POST['pincode'] ?? '');
    $quantities    = $_POST['quantities'] ?? [];

    $_SESSION['cart'] = [];
    foreach ($quantities as $pId => $qty) {
        $qty = (int)$qty;
        if ($qty > 0) {
            $_SESSION['cart'][$pId] = $qty;
        }
    }

    $orderedItems = [];
    $totalAmount = 0;

    foreach ($_SESSION['cart'] as $pId => $qty) {
        $pStmt = $pdo->prepare("SELECT * FROM products WHERE id = :id AND status = 1 LIMIT 1");
        $pStmt->execute([':id' => $pId]);
        $prod = $pStmt->fetch();

        if ($prod) {
            $subtotal = $prod['price'] * $qty;
            $totalAmount += $subtotal;
            $orderedItems[] = [
                'id'       => $prod['id'],
                'name'     => $prod['product_name'],
                'price'    => $prod['price'],
                'qty'      => $qty,
                'subtotal' => $subtotal
            ];
        }
    }

    if (empty($orderedItems)) $errors[] = "Please select at least one item by increasing its quantity.";
    if (empty($customer_name)) $errors[] = "Name is required.";
    if (empty($phone) || !preg_match('/^[0-9]{10}$/', $phone)) $errors[] = "Valid 10-digit mobile number is required.";
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Please enter a valid email address.";
    if (empty($address)) $errors[] = "Address is required.";
    if (empty($city)) $errors[] = "City is required.";
    if (empty($state)) $errors[] = "State is required.";
    if (empty($pincode)) $errors[] = "Pincode is required.";

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $seqStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'next_order_number' FOR UPDATE");
            $seqStmt->execute();
            $nextSeq = (int)($seqStmt->fetchColumn() ?: 1);

            $orderNo = date('Y') . '-' . $nextSeq;

            $upSeq = $pdo->prepare("UPDATE settings SET setting_value = :nextVal WHERE setting_key = 'next_order_number'");
            $upSeq->execute([':nextVal' => $nextSeq + 1]);

            $orderStmt = $pdo->prepare("
                INSERT INTO orders (order_no, customer_name, phone, email, address, city, state, pincode, total_amount, order_status)
                VALUES (:order_no, :customer_name, :phone, :email, :address, :city, :state, :pincode, :total_amount, 'Pending')
            ");

            $orderStmt->execute([
                ':order_no'      => $orderNo,
                ':customer_name' => $customer_name,
                ':phone'         => $phone,
                ':email'         => $email,
                ':address'       => $address,
                ':city'          => $city,
                ':state'         => $state,
                ':pincode'       => $pincode,
                ':total_amount'  => $totalAmount
            ]);

            $orderId = $pdo->lastInsertId();

            $itemStmt = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal)
                VALUES (:order_id, :product_id, :product_name, :price, :quantity, :subtotal)
            ");

            foreach ($orderedItems as $item) {
                $itemStmt->execute([
                    ':order_id'     => $orderId,
                    ':product_id'   => $item['id'],
                    ':product_name' => $item['name'],
                    ':price'        => $item['price'],
                    ':quantity'     => $item['qty'],
                    ':subtotal'     => $item['subtotal']
                ]);
            }

            $pdo->commit();

            $customerData = [
                'name'    => $customer_name,
                'phone'   => $phone,
                'email'   => $email,
                'address' => $address,
                'city'    => $city,
                'state'   => $state,
                'pincode' => $pincode
            ];

            if (function_exists('sendQuotationEmails')) {
                sendQuotationEmails($orderNo, $customerData, $orderedItems, $totalAmount);
            }

            unset($_SESSION['cart']);
            redirect("order-success.php?order_no=" . urlencode($orderNo));

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Submission failed: " . $e->getMessage();
        }
    }
}

$indianStates = [
    "Andhra Pradesh", "Arunachal Pradesh", "Assam", "Bihar", "Chhattisgarh", "Goa", "Gujarat", 
    "Haryana", "Himachal Pradesh", "Jharkhand", "Karnataka", "Kerala", "Madhya Pradesh", 
    "Maharashtra", "Manipur", "Meghalaya", "Mizoram", "Nagaland", "Odisha", "Punjab", 
    "Rajasthan", "Sikkim", "Tamil Nadu", "Telangana", "Tripura", "Uttar Pradesh", "Uttarakhand", 
    "West Bengal", "Andaman and Nicobar Islands", "Chandigarh", "Dadra and Nagar Haveli and Daman and Diu", 
    "Delhi", "Jammu and Kashmir", "Ladakh", "Lakshadweep", "Puducherry"
];

require_once 'includes/header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap');

/* ==========================================================================
   🎨 6 DYNAMIC COLOR THEME ENGINE
   ========================================================================== */

<?php if ($currentTheme === 1): ?>
:root {
    --bg-canvas: #FFFBEB;
    --card-bg: #FFFFFF;
    --text-primary: #1F2937;
    --text-muted: #6B7280;
    --border-color: #FDE68A;
    --primary-red: #9F1239;
    --accent-gold: #D97706;
    --primary-gradient: linear-gradient(135deg, #881337 0%, #BE123C 100%);
    --card-shadow: 0 4px 15px rgba(217, 119, 6, 0.08);
}
.sticky-bar-bg { background: rgba(255, 251, 235, 0.95); border-bottom: 2px solid #FCD34D; }
<?php elseif ($currentTheme === 2): ?>
:root {
    --bg-canvas: #0F172A;
    --card-bg: #1E293B;
    --text-primary: #F8FAFC;
    --text-muted: #94A3B8;
    --border-color: #334155;
    --primary-red: #06B6D4;
    --accent-gold: #F59E0B;
    --primary-gradient: linear-gradient(135deg, #0891B2 0%, #4F46E5 100%);
    --card-shadow: 0 10px 25px rgba(0,0,0,0.3);
}
body { background-color: var(--bg-canvas) !important; color: var(--text-primary) !important; }
.sticky-bar-bg { background: rgba(15, 23, 42, 0.95); border-bottom: 1px solid #334155; }
.metric-card, .product-card-row, .checkout-card { background: #1E293B !important; color: #FFFFFF !important; }
.form-control, .form-select, .search-pill { background-color: #0F172A !important; border-color: #334155 !important; color: #FFFFFF !important; }
.product-name { color: #FFFFFF !important; }
<?php elseif ($currentTheme === 3): ?>
:root {
    --bg-canvas: #FAF5FF;
    --card-bg: #FFFFFF;
    --text-primary: #2E1065;
    --text-muted: #7E22CE;
    --border-color: #E9D5FF;
    --primary-red: #581C87;
    --accent-gold: #EAB308;
    --primary-gradient: linear-gradient(135deg, #3B0764 0%, #581C87 100%);
    --card-shadow: 0 4px 15px rgba(88, 28, 135, 0.1);
}
.sticky-bar-bg { background: rgba(250, 245, 255, 0.95); border-bottom: 2px solid #E9D5FF; }
<?php elseif ($currentTheme === 4): ?>
:root {
    --bg-canvas: #F0FDF4;
    --card-bg: #FFFFFF;
    --text-primary: #064E3B;
    --text-muted: #047857;
    --border-color: #BBF7D0;
    --primary-red: #047857;
    --accent-gold: #10B981;
    --primary-gradient: linear-gradient(135deg, #065F46 0%, #047857 100%);
    --card-shadow: 0 4px 15px rgba(4, 120, 87, 0.08);
}
.sticky-bar-bg { background: rgba(240, 253, 244, 0.95); border-bottom: 2px solid #86EFAC; }
<?php elseif ($currentTheme === 5): ?>
:root {
    --bg-canvas: #FFF5F5;
    --card-bg: #FFFFFF;
    --text-primary: #431407;
    --text-muted: #9A3412;
    --border-color: #FECDD3;
    --primary-red: #EA580C;
    --accent-gold: #F97316;
    --primary-gradient: linear-gradient(135deg, #EA580C 0%, #E11D48 100%);
    --card-shadow: 0 4px 15px rgba(234, 88, 12, 0.12);
}
.sticky-bar-bg { background: rgba(255, 245, 245, 0.95); border-bottom: 2px solid #FDA4AF; }
<?php else: ?>
:root {
    --bg-canvas: #F8FAFC;
    --card-bg: #FFFFFF;
    --text-primary: #0F172A;
    --text-muted: #64748B;
    --border-color: #E2E8F0;
    --primary-red: #0F172A;
    --accent-gold: #E53935;
    --primary-gradient: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);
    --card-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.05);
}
.sticky-bar-bg { background: rgba(255, 255, 255, 0.88); border-bottom: 1px solid #E2E8F0; }
<?php endif; ?>

body {
    font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
    background-color: var(--bg-canvas);
    color: var(--text-primary);
    -webkit-font-smoothing: antialiased;
}

/* Strictly enforce background overlay position for canvas */
#crackerCanvas {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    pointer-events: none !important;
    z-index: 9999 !important;
}

.theme-switcher-bar {
    background: #0F172A;
    color: #FFFFFF;
    padding: 8px 0;
    font-size: 0.8rem;
    font-weight: 600;
}
.theme-btn {
    padding: 3px 10px;
    border-radius: 99px;
    font-size: 0.75rem;
    text-decoration: none;
    color: #94A3B8;
    background: rgba(255, 255, 255, 0.08);
    transition: all 0.2s ease;
}
.theme-btn.active, .theme-btn:hover {
    background: #FF9800;
    color: #0F172A;
    font-weight: 800;
}

.premium-sticky-bar {
    position: sticky;
    top: 0;
    z-index: 1020;
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    padding: 12px 0;
    margin-bottom: 20px;
    box-shadow: var(--card-shadow);
}

.metric-card {
    background: var(--card-bg);
    border: 1.5px solid var(--border-color);
    border-radius: 14px;
    padding: 10px 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease, border-color 0.3s ease;
    height: 100%;
}

.metric-card.pop-pulse {
    transform: scale(1.06);
    border-color: #10B981 !important;
    box-shadow: 0 0 18px rgba(16, 185, 129, 0.35) !important;
}

.metric-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}
.icon-amber { background: #FEF3C7; color: #D97706; }
.icon-blue { background: #DBEAFE; color: #2563EB; }
.icon-emerald { background: #D1FAE5; color: #059669; }
.icon-rose { background: #FFE4E6; color: #E11D48; }

.metric-label {
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-muted);
    margin-bottom: 2px;
}
.metric-value {
    font-size: 1.2rem;
    font-weight: 800;
    line-height: 1.2;
    transition: transform 0.2s ease;
}

.product-card-row {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 14px 16px;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
}

.product-thumb-container {
    width: 56px;
    height: 56px;
    border-radius: 10px;
    overflow: hidden;
    background: #F1F5F9;
    flex-shrink: 0;
    position: relative;
}

.product-thumb-container img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.25s ease;
}

.product-thumb-container.clickable-img {
    cursor: zoom-in;
}

.product-thumb-container.clickable-img:hover img {
    transform: scale(1.12);
}

.product-info {
    flex-grow: 1;
    min-width: 0;
}
.product-name {
    font-weight: 700;
    font-size: 0.95rem;
}
.product-desc {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin-top: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.stepper-control {
    display: flex;
    align-items: center;
    background: rgba(0, 0, 0, 0.04);
    padding: 3px;
    border-radius: 10px;
    border: 1px solid var(--border-color);
    flex-shrink: 0;
}
.stepper-btn {
    width: 30px;
    height: 30px;
    border-radius: 6px;
    border: none;
    background: #FFFFFF;
    color: #1F2937;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    cursor: pointer;
    transition: transform 0.15s ease;
    user-select: none;
}

.stepper-btn:active {
    transform: scale(0.88);
}

.stepper-input {
    width: 40px;
    border: none;
    background: transparent;
    text-align: center;
    font-weight: 700;
    font-size: 0.95rem;
    color: inherit;
    outline: none;
}

.stepper-input::-webkit-outer-spin-button,
.stepper-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.stepper-input[type=number] {
    -moz-appearance: textfield;
}

/* Fixed Layout Subtotal Container to Prevent Layout Breaks on Large Numbers */
.item-subtotal-wrap {
    min-width: 110px;
    text-align: right;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.search-wrapper {
    position: relative;
    margin-top: 10px;
}
.search-pill {
    background: var(--card-bg);
    border: 1.5px solid var(--border-color);
    border-radius: 99px;
    padding: 6px 18px 6px 42px;
    transition: all 0.25s ease;
}
.search-wrapper:focus-within .search-pill {
    border-color: var(--primary-red);
    box-shadow: 0 0 0 4px rgba(229, 57, 53, 0.12);
}
.search-pill input {
    border: none;
    outline: none;
    box-shadow: none !important;
    font-size: 0.9rem;
    font-weight: 500;
    width: 100%;
    background: transparent;
}
.search-icon-left {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: #94A3B8;
    font-size: 0.9rem;
}

.sort-select-pill {
    background: var(--card-bg);
    border: 1.5px solid var(--border-color);
    border-radius: 99px;
    padding: 6px 16px;
    font-size: 0.88rem;
    font-weight: 600;
    outline: none;
    cursor: pointer;
}

.category-accordion-btn {
    width: 100%;
    background: var(--primary-gradient);
    color: #FFFFFF;
    border: none;
    border-radius: 14px;
    padding: 12px 18px;
    font-weight: 800;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: var(--card-shadow);
    cursor: pointer;
}

.checkout-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    box-shadow: var(--card-shadow);
    overflow: hidden;
}
.checkout-card-header {
    background: var(--primary-gradient);
    padding: 16px 20px;
    color: #FFFFFF;
    font-weight: 800;
    font-size: 1rem;
}

.form-control, .form-select {
    border-radius: 12px;
    border: 1px solid var(--border-color);
    padding: 10px 14px;
    font-size: 0.95rem;
}
.form-control:focus, .form-select:focus {
    border-color: var(--primary-red);
    box-shadow: 0 0 0 4px rgba(229, 57, 53, 0.12);
}

.btn-submit-quotation {
    background: var(--primary-gradient);
    border: none;
    color: #FFFFFF;
    font-weight: 800;
    padding: 14px 36px;
    border-radius: 12px;
    box-shadow: var(--card-shadow);
    transition: all 0.3s ease;
}

.btn-submit-quotation:disabled {
    background: #CBD5E1 !important;
    color: #64748B !important;
    box-shadow: none !important;
    cursor: not-allowed !important;
    opacity: 0.8;
}

.is-invalid-field {
    border-color: #E53935 !important;
    background-color: #FEF2F2 !important;
}

.is-valid-field {
    border-color: #10B981 !important;
    background-color: #ECFDF5 !important;
}

.floating-action-bar {
    position: fixed;
    bottom: 24px;
    right: 24px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    z-index: 9990;
}

.fab-btn {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #FFFFFF;
    font-size: 1.4rem;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.18);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    text-decoration: none !important;
    border: none;
    cursor: pointer;
}

.fab-whatsapp {
    background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
    box-shadow: 0 8px 20px rgba(37, 211, 102, 0.35);
}

.fab-scroll-top {
    background: #1F2937;
    opacity: 0;
    visibility: hidden;
    transform: translateY(10px);
}

.fab-scroll-top.show-scroll-btn {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

@media (max-width: 768px) {
    .premium-sticky-bar { 
        top: 0; 
        padding: 8px 0; 
    }

    .metric-card {
        padding: 8px 10px;
    }
    .metric-label {
        font-size: 0.62rem;
        letter-spacing: 0;
    }
    .metric-value {
        font-size: 0.95rem;
    }

    .product-card-row {
        display: grid;
        grid-template-columns: 50px 1fr;
        grid-template-rows: auto auto;
        gap: 8px 10px;
        padding: 12px;
    }

    .product-thumb-container {
        width: 50px;
        height: 50px;
        grid-column: 1;
        grid-row: 1;
    }

    .product-info {
        grid-column: 2;
        grid-row: 1;
    }

    .product-actions-mobile {
        grid-column: 1 / -1;
        grid-row: 2;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-top: 8px;
        border-top: 1px dashed var(--border-color);
        margin-top: 2px;
    }

    .floating-action-bar {
        bottom: 16px;
        right: 16px;
        gap: 10px;
    }

    .fab-btn {
        width: 44px;
        height: 44px;
        font-size: 1.2rem;
    }
}
</style>

<!-- Top Theme Switcher Bar for Live Testing -->
<div class="theme-switcher-bar">
    <div class="container d-flex align-items-center justify-content-between flex-wrap gap-2">
        <span><i class="fa-solid fa-palette me-1 text-warning"></i> UI Theme Switcher:</span>
        <div class="d-flex gap-1 flex-wrap">
            <a href="?theme=1" class="theme-btn <?= ($currentTheme === 1) ? 'active' : ''; ?>">1. Gold & Crimson</a>
            <a href="?theme=2" class="theme-btn <?= ($currentTheme === 2) ? 'active' : ''; ?>">2. Dark Cyber</a>
            <a href="?theme=3" class="theme-btn <?= ($currentTheme === 3) ? 'active' : ''; ?>">3. Royal Purple</a>
            <a href="?theme=4" class="theme-btn <?= ($currentTheme === 4) ? 'active' : ''; ?>">4. Factory Emerald</a>
            <a href="?theme=5" class="theme-btn <?= ($currentTheme === 5) ? 'active' : ''; ?>">5. Sunset Fire</a>
            <a href="?theme=6" class="theme-btn <?= ($currentTheme === 6) ? 'active' : ''; ?>">6. Minimalist Glass</a>
        </div>
    </div>
</div>

<!-- SINGLE CANVAS INSTANCE FOR FIREWORKS -->
<canvas id="crackerCanvas"></canvas>

<!-- Floating Summary Header & Real-time Search -->
<div class="premium-sticky-bar sticky-bar-bg">
    <div class="container">
        <div class="row g-2 g-md-3">
            <div class="col-4">
                <div class="metric-card" id="card-items-selected">
                    <div>
                        <div class="metric-label">Items Selected</div>
                        <div class="metric-value" id="summary-item-count">0</div>
                    </div>
                    <div class="metric-icon icon-blue d-none d-sm-flex">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                </div>
            </div>

            <div class="col-4">
                <div class="metric-card" id="card-total-amount">
                    <div>
                        <div class="metric-label">Total Amount</div>
                        <div class="metric-value text-emerald">₹<span id="summary-total-amount">0.00</span></div>
                    </div>
                    <div class="metric-icon icon-emerald d-none d-sm-flex">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                </div>
            </div>

            <div class="col-4">
                <div class="metric-card" id="card-savings">
                    <div>
                        <div class="metric-label">Estimated Savings</div>
                        <div class="metric-value text-danger">₹<span id="summary-savings">0.00</span></div>
                    </div>
                    <div class="metric-icon icon-rose d-none d-sm-flex">
                        <i class="fa-solid fa-tags"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-2 align-items-center mt-2">
            <div class="col-7 col-md-8">
                <div class="search-wrapper mt-0">
                    <i class="fa-solid fa-magnifying-glass search-icon-left"></i>
                    <div class="search-pill d-flex align-items-center">
                        <input type="text" id="productSearchInput" placeholder="Search crackers, categories...">
                        <button type="button" id="clearSearchBtn" class="btn btn-link text-muted text-decoration-none p-0 ms-2" style="display: none;" title="Clear Search">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="col-5 col-md-4">
                <select id="productSortSelect" class="form-select sort-select-pill">
                    <option value="default" selected>Sort: Default</option>
                    <option value="cat_price_asc">Category: Price (Low to High)</option>
                    <option value="cat_price_desc">Category: Price (High to Low)</option>
                    <option value="price_asc">Overall: Price (Low to High)</option>
                    <option value="price_desc">Overall: Price (High to Low)</option>
                </select>
            </div>
        </div>
    </div>
</div>

<div class="container my-4">

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger shadow-sm border-0 rounded-3 mb-4">
            <ul class="mb-0 fw-semibold">
                <?php foreach ($errors as $err): ?>
                    <li><?= $err; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="index.php" id="orderForm">
        <input type="hidden" name="submit_estimate" value="1">

        <div id="noSearchProductsFound" class="text-center py-5 rounded-4 shadow-sm border my-4" style="display: none; background: var(--card-bg);">
            <i class="fa-solid fa-magnifying-glass fa-3x mb-3 text-secondary"></i>
            <h5 class="fw-bold">No products found matching your search</h5>
            <p class="text-muted small">Try searching with a different product name or category code.</p>
        </div>

        <?php if (empty($groupedProducts)): ?>
            <div class="text-center py-5 rounded-4 shadow-sm border" style="background: var(--card-bg);">
                <i class="fa-solid fa-box-open fa-3x mb-3 text-secondary"></i>
                <h5 class="fw-bold">No products found</h5>
                <a href="index.php" class="btn btn-outline-danger btn-sm rounded-pill mt-2">Reset Catalog</a>
            </div>
        <?php else: ?>
            <div id="sortedProductsWrapper" class="mb-4" style="display: none;"></div>

            <div id="categoryAccordionsWrapper">
                <?php $catIndex = 0; foreach ($groupedProducts as $catName => $products): $catIndex++; ?>
                    <div class="category-block mb-4" id="cat-block-<?= $catIndex; ?>" data-category-name="<?= sanitize($catName); ?>">
                        <button type="button" class="category-accordion-btn category-header-btn mb-3" data-bs-toggle="collapse" data-bs-target="#cat-collapse-<?= $catIndex; ?>" aria-expanded="true">
                            <span><i class="fa-solid fa-fire-flame-curved me-2"></i><?= sanitize($catName); ?> <span class="badge bg-white text-dark rounded-pill ms-2 fs-6 px-3"><?= count($products); ?> Items</span></span>
                            <i class="fa-solid fa-chevron-down chevron-icon"></i>
                        </button>

                        <div class="collapse show product-container" id="cat-collapse-<?= $catIndex; ?>">
                            <?php foreach ($products as $p): 
                                $hasRealImage = !empty($p['image']) && file_exists('uploads/products/' . $p['image']);
                                $imgPath = $hasRealImage ? 'uploads/products/' . $p['image'] : 'assets/image/no-image.jpg';
                                $savedQty = isset($_SESSION['cart'][$p['id']]) ? (int)$_SESSION['cart'][$p['id']] : 0;
                            ?>
                                <div class="product-card-row" data-price="<?= $p['price']; ?>" data-id="<?= $p['id']; ?>" data-category="<?= sanitize($p['product_category']); ?>" data-search-text="<?= strtolower(sanitize($p['product_name'] . ' ' . ($p['description'] ?? '') . ' ' . $p['sku'] . ' ' . $p['product_category'])); ?>">
                                    
                                    <div class="product-thumb-container <?= $hasRealImage ? 'clickable-img' : ''; ?>">
                                        <img src="<?= $imgPath; ?>" 
                                             alt="<?= sanitize($p['product_name']); ?>" 
                                             <?= $hasRealImage ? 'class="product-preview-trigger" data-fullimg="' . $imgPath . '" data-title="' . sanitize($p['product_name']) . '" data-price="' . number_format($p['price'], 2) . '"' : ''; ?>>
                                    </div>

                                    <div class="product-info">
                                        <div class="product-name"><?= sanitize($p['product_name']); ?></div>
                                        <?php if (!empty($p['description'])): ?>
                                            <div class="product-desc"><?= sanitize($p['description']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="product-actions-mobile d-flex align-items-center justify-content-between gap-2">
                                        <div class="text-start text-md-end">
                                            <div class="text-muted small text-decoration-line-through" style="font-size: 0.75rem;">₹<?= number_format($p['price'] * 1.25, 2); ?></div>
                                            <div class="fw-bold fs-6 text-danger">₹<span class="unit-price"><?= number_format($p['price'], 2, '.', ''); ?></span></div>
                                        </div>
                                        <div class="stepper-control">
                                            <button type="button" class="stepper-btn btn-minus" data-id="<?= $p['id']; ?>">-</button>
                                            <input type="number" name="quantities[<?= $p['id']; ?>]" id="qty-<?= $p['id']; ?>" class="stepper-input qty-input" value="<?= $savedQty; ?>" min="0" data-price="<?= $p['price']; ?>" data-id="<?= $p['id']; ?>">
                                            <button type="button" class="stepper-btn btn-plus" data-id="<?= $p['id']; ?>">+</button>
                                        </div>
                                        <!-- Fixed-width subtotal wrapper to prevent table/row collapse -->
                                        <div class="item-subtotal-wrap fw-bold fs-6">
                                            ₹<span id="subtotal-<?= $p['id']; ?>" class="item-subtotal">0.00</span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Customer Details Card -->
        <div class="checkout-card my-5">
            <div class="checkout-card-header">
                <i class="fa-solid fa-paper-plane me-2"></i>Customer Quotation Request Details
            </div>
            <div class="p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Full Name *</label>
                        <input type="text" name="name" id="customer_name" class="form-control save-field" placeholder="Your Name" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">E-Mail Address</label>
                        <input type="email" name="email" id="customer_email" class="form-control save-field" placeholder="Optional">
                        <div id="emailErrorMsg" class="form-text fw-semibold mt-1" style="font-size: 0.8rem; display: none;"></div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Mobile Number *</label>
                        <input type="tel" name="phone" id="customer_phone" class="form-control save-field" placeholder="10-digit Mobile Number" maxlength="10" required>
                        <div id="phoneErrorMsg" class="form-text fw-semibold mt-1" style="font-size: 0.8rem; display: none;"></div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Delivery Address *</label>
                        <textarea name="address" id="customer_address" class="form-control save-field" rows="3" placeholder="Door No, Street Name, Landmark..." required></textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Pincode *</label>
                        <input type="text" name="pincode" id="customer_pincode" class="form-control save-field" placeholder="Pincode" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Region / State *</label>
                        <select name="state" id="customer_state" class="form-select save-field" required>
                            <option value="">-- Select State --</option>
                            <?php foreach ($indianStates as $st): ?>
                                <option value="<?= $st; ?>" <?= ($st === 'Tamil Nadu') ? 'selected' : ''; ?>><?= $st; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">City *</label>
                        <input type="text" name="city" id="customer_city" class="form-control save-field" placeholder="Enter City" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Country *</label>
                        <input type="text" name="country" class="form-control" style="opacity: 0.7;" value="India" readonly>
                    </div>
                </div>

                <div class="text-end mt-4">
                    <div id="formValidationTip" class="fw-semibold text-danger small mb-2"></div>
                    <button type="submit" id="submitOrderBtn" class="btn btn-submit-quotation" disabled>
                        Submit Quotation<i class="fa-solid fa-arrow-right ms-2"></i>
                    </button>
                </div>
            </div>
        </div>

    </form>

</div>

<!-- Fixed Action Buttons Container -->
<div class="floating-action-bar">
    <a href="#" id="whatsappChatBtn" target="_blank" class="fab-btn fab-whatsapp" title="Chat on WhatsApp">
        <i class="fa-brands fa-whatsapp"></i>
    </a>
    <button type="button" id="scrollTopBtn" class="fab-btn fab-scroll-top" title="Scroll to Top">
        <i class="fa-solid fa-arrow-up"></i>
    </button>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const qtyInputs = document.querySelectorAll('.qty-input');
    const searchInput = document.getElementById('productSearchInput');
    const clearSearchBtn = document.getElementById('clearSearchBtn');
    const sortSelect = document.getElementById('productSortSelect');
    const sortedWrapper = document.getElementById('sortedProductsWrapper');
    const categoryAccordionsWrapper = document.getElementById('categoryAccordionsWrapper');
    const noSearchBanner = document.getElementById('noSearchProductsFound');

    const orderForm = document.getElementById('orderForm');
    const submitBtn = document.getElementById('submitOrderBtn');
    const validationTip = document.getElementById('formValidationTip');
    const phoneInput = document.getElementById('customer_phone');
    const phoneErrorMsg = document.getElementById('phoneErrorMsg');
    const emailInput = document.getElementById('customer_email');
    const emailErrorMsg = document.getElementById('emailErrorMsg');
    const nameInput = document.getElementById('customer_name');
    const cityInput = document.getElementById('customer_city');
    const stateInput = document.getElementById('customer_state');
    const addressInput = document.getElementById('customer_address');
    const pincodeInput = document.getElementById('customer_pincode');
    const saveFields = document.querySelectorAll('.save-field');

    const whatsappBtn = document.getElementById('whatsappChatBtn');
    const scrollTopBtn = document.getElementById('scrollTopBtn');

    const storeWhatsappNumber = "919876543210"; 

    const summaryItemEl = document.getElementById('summary-item-count');
    const summaryTotalEl = document.getElementById('summary-total-amount');
    const summarySavingsEl = document.getElementById('summary-savings');

    // ==========================================
    // 🖼️ PRODUCT IMAGE PREVIEW MODAL
    // ==========================================
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('product-preview-trigger')) {
            const imgSrc = e.target.dataset.fullimg;
            const title = e.target.dataset.title;
            const price = e.target.dataset.price;

            if (typeof Swal !== 'undefined' && imgSrc) {
                Swal.fire({
                    title: `<span class="fs-5 fw-bold">${title}</span>`,
                    html: `<div class="fw-bold text-danger mb-2">Offer Price: ₹${price}</div>`,
                    imageUrl: imgSrc,
                    imageAlt: title,
                    imageHeight: 320,
                    showCloseButton: true,
                    showConfirmButton: false,
                    customClass: {
                        popup: 'rounded-4 shadow-lg border-0',
                        image: 'rounded-3 object-fit-contain'
                    }
                });
            }
        }
    });

    // ==========================================
    // 🎆 FIREWORK ANIMATION SYSTEM
    // ==========================================
    var canvas = document.getElementById("crackerCanvas");
    var ctx = canvas ? canvas.getContext("2d") : null;
    var w, h, particles = [];

    function resizeCanvas() {
        if (canvas) {
            w = canvas.width = window.innerWidth;
            h = canvas.height = window.innerHeight;
        }
    }
    window.addEventListener("resize", resizeCanvas, false);
    resizeCanvas();

    function Particle(x, y) {
        this.w = this.h = Math.random() * 4 + 2;
        this.x = x - this.w / 2;
        this.y = y - this.h / 2;
        this.vx = (Math.random() - 0.5) * 12;
        this.vy = (Math.random() - 0.5) * 12;
        this.alpha = 1.0;
        this.color = "hsl(" + (~~(Math.random() * 360)) + ", 100%, 60%)";
    }

    Particle.prototype = {
        gravity: 0.12,
        move: function () {
            this.x += this.vx;
            this.vy += this.gravity;
            this.y += this.vy;
            this.alpha -= 0.018;
            return !(this.x <= 0 || this.x >= w || this.y >= h || this.alpha <= 0);
        },
        draw: function (c) {
            c.save();
            c.beginPath();
            c.translate(this.x + this.w / 2, this.y + this.h / 2);
            c.arc(0, 0, this.w, 0, Math.PI * 2);
            c.fillStyle = this.color;
            c.globalAlpha = this.alpha;
            c.closePath();
            c.fill();
            c.restore();
        }
    };

    function triggerCrackerAt(x, y, count = 70) {
        for (var i = 0; i < count; i++) {
            particles.push(new Particle(x, y));
        }
    }

    function triggerEntryFireworks() {
        let launchCount = 0;
        const interval = setInterval(() => {
            const rx = Math.random() * (w - 200) + 100;
            const ry = Math.random() * (h * 0.6) + 100;
            triggerCrackerAt(rx, ry, 90);
            launchCount++;
            if (launchCount >= 8) clearInterval(interval);
        }, 400);
    }
    triggerEntryFireworks();

    function renderWorld() {
        if (ctx) {
            ctx.clearRect(0, 0, w, h);
            ctx.globalCompositeOperation = 'lighter';
            var alive = [];
            for (var i = 0; i < particles.length; i++) {
                if (particles[i].move()) {
                    particles[i].draw(ctx);
                    alive.push(particles[i]);
                }
            }
            particles = alive;
        }
        window.requestAnimationFrame(renderWorld);
    }
    window.requestAnimationFrame(renderWorld);

    // ==========================================
    // 📊 ANIMATED NUMBER & CARD POP SYSTEM
    // ==========================================
    let currentAnimValues = { items: 0, total: 0, savings: 0 };

    function animateNumber(element, start, end, isCurrency = false, duration = 350) {
        if (!element) return;
        let startTime = null;

        function step(timestamp) {
            if (!startTime) startTime = timestamp;
            const progress = Math.min((timestamp - startTime) / duration, 1);
            const currentVal = start + (end - start) * progress;

            if (isCurrency) {
                element.textContent = currentVal.toFixed(2);
            } else {
                element.textContent = Math.round(currentVal);
            }

            if (progress < 1) {
                window.requestAnimationFrame(step);
            }
        }
        window.requestAnimationFrame(step);
    }

    function triggerCardPopAnimation() {
        const cards = [
            document.getElementById('card-items-selected'),
            document.getElementById('card-total-amount'),
            document.getElementById('card-savings')
        ];

        cards.forEach(card => {
            if (card) {
                card.classList.remove('pop-pulse');
                void card.offsetWidth;
                card.classList.add('pop-pulse');
                setTimeout(() => card.classList.remove('pop-pulse'), 350);
            }
        });
    }

    // ==========================================
    // 🛒 CART CALCULATOR
    // ==========================================
    let totalItemsCountGlobal = 0;
    let grandTotalGlobal = 0;

    function calculateTotals(triggerAnim = true) {
        let totalItemsCount = 0;
        let grandTotal = 0;
        const currentQuantities = {};

        qtyInputs.forEach(input => {
            const qty = parseInt(input.value) || 0;
            const price = parseFloat(input.dataset.price) || 0;
            const id = input.dataset.id;
            const subtotal = qty * price;

            const subtotalEl = document.getElementById(`subtotal-${id}`);
            if (subtotalEl) {
                subtotalEl.textContent = subtotal.toFixed(2);
            }

            if (qty > 0) {
                totalItemsCount += qty;
                grandTotal += subtotal;
                currentQuantities[id] = qty;
            }
        });

        totalItemsCountGlobal = totalItemsCount;
        grandTotalGlobal = grandTotal;

        try {
            localStorage.setItem('retail_cart_quantities', JSON.stringify(currentQuantities));
        } catch(e) {}

        const newSavings = grandTotal * 0.25;

        if (triggerAnim) {
            animateNumber(summaryItemEl, currentAnimValues.items, totalItemsCount, false);
            animateNumber(summaryTotalEl, currentAnimValues.total, grandTotal, true);
            animateNumber(summarySavingsEl, currentAnimValues.savings, newSavings, true);
            triggerCardPopAnimation();
        } else {
            if (summaryItemEl) summaryItemEl.textContent = totalItemsCount;
            if (summaryTotalEl) summaryTotalEl.textContent = grandTotal.toFixed(2);
            if (summarySavingsEl) summarySavingsEl.textContent = newSavings.toFixed(2);
        }

        currentAnimValues.items = totalItemsCount;
        currentAnimValues.total = grandTotal;
        currentAnimValues.savings = newSavings;

        updateWhatsappLink();
        validateFormState();
    }

    try {
        const savedLocalCart = localStorage.getItem('retail_cart_quantities');
        if (savedLocalCart) {
            const parsed = JSON.parse(savedLocalCart);
            qtyInputs.forEach(input => {
                const pId = input.dataset.id;
                if (parsed[pId] !== undefined) {
                    input.value = parsed[pId];
                }
            });
        }
    } catch (e) {}

    function updateWhatsappLink() {
        if (!whatsappBtn) return;
        let message = `Hello! I am browsing your Cracker Catalog.`;
        if (totalItemsCountGlobal > 0) {
            message += `\n\nI have selected *${totalItemsCountGlobal} items* worth *₹${grandTotalGlobal.toFixed(2)}*. I would like to place an order / get a quotation!`;
        } else {
            message += `\n\nI have a query regarding your products.`;
        }
        whatsappBtn.href = `https://wa.me/${storeWhatsappNumber}?text=${encodeURIComponent(message)}`;
    }

    // ==========================================
    // 📱 VALIDATION ENGINE
    // ==========================================
    function validatePhoneNumber() {
        if (!phoneInput) return false;
        phoneInput.value = phoneInput.value.replace(/[^0-9]/g, '');
        const phoneVal = phoneInput.value.trim();

        if (phoneVal === '') {
            if (phoneErrorMsg) phoneErrorMsg.style.display = 'none';
            phoneInput.classList.remove('is-valid-field', 'is-invalid-field');
            return false;
        } else if (!/^[0-9]{10}$/.test(phoneVal)) {
            if (phoneErrorMsg) {
                phoneErrorMsg.style.display = 'block';
                phoneErrorMsg.className = 'form-text text-danger fw-semibold mt-1';
                phoneErrorMsg.textContent = `Please enter a valid 10-digit mobile number (${phoneVal.length}/10 digits).`;
            }
            phoneInput.classList.remove('is-valid-field');
            phoneInput.classList.add('is-invalid-field');
            return false;
        } else {
            if (phoneErrorMsg) {
                phoneErrorMsg.style.display = 'block';
                phoneErrorMsg.className = 'form-text text-success fw-semibold mt-1';
                phoneErrorMsg.textContent = '✓ Valid 10-digit mobile number';
            }
            phoneInput.classList.remove('is-invalid-field');
            phoneInput.classList.add('is-valid-field');
            return true;
        }
    }

    function validateEmailAddress() {
        if (!emailInput) return true;
        const emailVal = emailInput.value.trim();
        const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;

        if (emailVal === '') {
            if (emailErrorMsg) emailErrorMsg.style.display = 'none';
            emailInput.classList.remove('is-invalid-field', 'is-valid-field');
            return true;
        }

        if (!emailRegex.test(emailVal)) {
            if (emailErrorMsg) {
                emailErrorMsg.style.display = 'block';
                emailErrorMsg.className = 'form-text text-danger fw-semibold mt-1';
                emailErrorMsg.textContent = 'Please enter a valid e-mail address.';
            }
            emailInput.classList.remove('is-valid-field');
            emailInput.classList.add('is-invalid-field');
            return false;
        } else {
            if (emailErrorMsg) {
                emailErrorMsg.style.display = 'block';
                emailErrorMsg.className = 'form-text text-success fw-semibold mt-1';
                emailErrorMsg.textContent = '✓ Valid e-mail address';
            }
            emailInput.classList.remove('is-invalid-field');
            emailInput.classList.add('is-valid-field');
            return true;
        }
    }

    function validateFormState() {
        if (!submitBtn) return;
        const isPhoneValid = validatePhoneNumber();
        const isEmailValid = validateEmailAddress();
        const isNameValid = nameInput && nameInput.value.trim() !== '';
        const isCityValid = cityInput && cityInput.value.trim() !== '';
        const isStateValid = stateInput && stateInput.value.trim() !== '';
        const isAddressValid = addressInput && addressInput.value.trim() !== '';
        const isPincodeValid = pincodeInput && pincodeInput.value.trim() !== '';
        const hasItemsInCart = totalItemsCountGlobal > 0;

        if (!hasItemsInCart) {
            if (validationTip) validationTip.textContent = '⚠️ Please select at least 1 cracker item in the catalog above.';
            submitBtn.setAttribute('disabled', 'disabled');
        } else if (!isNameValid) {
            if (validationTip) validationTip.textContent = '⚠️ Full Name is required.';
            submitBtn.setAttribute('disabled', 'disabled');
        } else if (!isPhoneValid) {
            if (validationTip) validationTip.textContent = '⚠️ Valid 10-digit Mobile Number is required.';
            submitBtn.setAttribute('disabled', 'disabled');
        } else if (!isEmailValid) {
            if (validationTip) validationTip.textContent = '⚠️ Please enter a valid e-mail address.';
            submitBtn.setAttribute('disabled', 'disabled');
        } else if (!isAddressValid || !isCityValid || !isStateValid || !isPincodeValid) {
            if (validationTip) validationTip.textContent = '⚠️ Please complete all address fields (Address, City, State, Pincode).';
            submitBtn.setAttribute('disabled', 'disabled');
        } else {
            if (validationTip) validationTip.textContent = '';
            submitBtn.removeAttribute('disabled');
        }
    }

    function restoreFormFields() {
        let restoredState = false;
        try {
            const savedFormData = localStorage.getItem('retail_customer_form');
            if (savedFormData) {
                const parsedForm = JSON.parse(savedFormData);
                saveFields.forEach(field => {
                    if (parsedForm[field.id] !== undefined && parsedForm[field.id] !== '') {
                        field.value = parsedForm[field.id];
                        if (field.id === 'customer_state') restoredState = true;
                    }
                });
            }
        } catch (e) {}

        if (!restoredState && stateInput) {
            stateInput.value = 'Tamil Nadu';
        }
    }

    function saveFormFields() {
        try {
            const formData = {};
            saveFields.forEach(field => {
                if (field.id) formData[field.id] = field.value;
            });
            localStorage.setItem('retail_customer_form', JSON.stringify(formData));
        } catch (e) {}
        validateFormState();
    }

    saveFields.forEach(field => {
        field.addEventListener('input', saveFormFields);
        field.addEventListener('change', saveFormFields);
    });

    restoreFormFields();

    if (orderForm) {
        orderForm.addEventListener('submit', function (e) {
            if (!validatePhoneNumber() || !validateEmailAddress() || totalItemsCountGlobal === 0) {
                e.preventDefault();
                alert('Please check your inputs and make sure at least one product is selected with a valid 10-digit mobile number.');
                return false;
            }
            try {
                localStorage.removeItem('retail_cart_quantities');
                localStorage.removeItem('retail_customer_form');
            } catch (e) {}
        });
    }

    // ==========================================
    // ➕ ➖ BULLETPROOF DELEGATED STEPPER LISTENER
    // ==========================================
    const handledClicks = new WeakSet();

    document.addEventListener('click', function(e) {
        const plusBtn = e.target.closest('.btn-plus');
        const minusBtn = e.target.closest('.btn-minus');

        if (plusBtn) {
            if (handledClicks.has(plusBtn)) return;
            handledClicks.add(plusBtn);
            setTimeout(() => handledClicks.delete(plusBtn), 50);

            e.preventDefault();
            e.stopPropagation();

            const id = plusBtn.dataset.id;
            const input = document.getElementById(`qty-${id}`);
            if (input) {
                input.value = (parseInt(input.value) || 0) + 1;
                calculateTotals(true);

                const rect = plusBtn.getBoundingClientRect();
                triggerCrackerAt(rect.left + rect.width / 2, rect.top + rect.height / 2, 60);
            }
        } else if (minusBtn) {
            if (handledClicks.has(minusBtn)) return;
            handledClicks.add(minusBtn);
            setTimeout(() => handledClicks.delete(minusBtn), 50);

            e.preventDefault();
            e.stopPropagation();

            const id = minusBtn.dataset.id;
            const input = document.getElementById(`qty-${id}`);
            if (input) {
                const currentVal = parseInt(input.value) || 0;
                if (currentVal > 0) {
                    input.value = currentVal - 1;
                    calculateTotals(true);
                }
            }
        }
    });

    qtyInputs.forEach(input => {
        input.addEventListener('input', function() {
            calculateTotals(true);
        });
    });

    window.addEventListener('scroll', function () {
        if (scrollTopBtn) {
            if (window.scrollY > 300) {
                scrollTopBtn.classList.add('show-scroll-btn');
            } else {
                scrollTopBtn.classList.remove('show-scroll-btn');
            }
        }
    });

    if (scrollTopBtn) {
        scrollTopBtn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // ==========================================
    // 🔍 SEARCH & SORT ROUTINES
    // ==========================================
    function filterProductsBySearch() {
        if (!searchInput) return;
        const term = searchInput.value.toLowerCase().trim();
        const productRows = document.querySelectorAll('.product-card-row');
        let totalVisibleCount = 0;

        productRows.forEach(row => {
            const searchText = row.getAttribute('data-search-text') || '';
            if (searchText.includes(term)) {
                row.style.display = '';
                totalVisibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        const categoryBlocks = document.querySelectorAll('.category-block');
        categoryBlocks.forEach(block => {
            const rows = block.querySelectorAll('.product-card-row');
            let hasVisible = false;
            rows.forEach(r => {
                if (r.style.display !== 'none') hasVisible = true;
            });
            block.style.display = hasVisible ? '' : 'none';
        });

        if (noSearchBanner) {
            noSearchBanner.style.display = (totalVisibleCount === 0 && term !== '') ? 'block' : 'none';
        }
    }

    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            if (clearSearchBtn) {
                clearSearchBtn.style.display = this.value.trim() !== '' ? 'inline-block' : 'none';
            }
            filterProductsBySearch();
        });
    }

    if (clearSearchBtn) {
        clearSearchBtn.addEventListener('click', function() {
            searchInput.value = '';
            this.style.display = 'none';
            filterProductsBySearch();
        });
    }

    function applySortingLayout() {
        if (!sortSelect) return;
        const sortVal = sortSelect.value;
        const allRows = Array.from(document.querySelectorAll('.product-card-row'));

        if (sortVal === 'price_asc' || sortVal === 'price_desc') {
            if (categoryAccordionsWrapper) categoryAccordionsWrapper.style.display = 'none';
            if (sortedWrapper) sortedWrapper.style.display = 'block';

            if (sortVal === 'price_asc') {
                allRows.sort((a, b) => parseFloat(a.dataset.price) - parseFloat(b.dataset.price));
            } else {
                allRows.sort((a, b) => parseFloat(b.dataset.price) - parseFloat(a.dataset.price));
            }

            allRows.forEach(row => sortedWrapper.appendChild(row));
        } else {
            if (sortedWrapper) sortedWrapper.style.display = 'none';
            if (categoryAccordionsWrapper) categoryAccordionsWrapper.style.display = 'block';

            const catBlocks = document.querySelectorAll('.category-block');
            catBlocks.forEach(block => {
                const categoryName = block.dataset.categoryName;
                const container = block.querySelector('.product-container');
                const catRows = allRows.filter(row => row.dataset.category === categoryName);

                if (sortVal === 'cat_price_asc') {
                    catRows.sort((a, b) => parseFloat(a.dataset.price) - parseFloat(b.dataset.price));
                } else if (sortVal === 'cat_price_desc') {
                    catRows.sort((a, b) => parseFloat(b.dataset.price) - parseFloat(a.dataset.price));
                } else {
                    catRows.sort((a, b) => parseInt(a.dataset.id) - parseInt(b.dataset.id));
                }

                if (container) {
                    catRows.forEach(row => container.appendChild(row));
                }
            });
        }

        filterProductsBySearch();
    }

    if (sortSelect) {
        sortSelect.addEventListener('change', applySortingLayout);
        applySortingLayout();
    }

    calculateTotals(false);
    validateFormState();
});
</script>