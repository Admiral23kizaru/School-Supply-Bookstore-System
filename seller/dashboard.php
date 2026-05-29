<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/helpers.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'seller') {
    header('Location: ../index.php?action=login');
    exit;
}

require_once __DIR__ . '/includes/dashboard_data.php';

function pageUrl(array $overrides = []): string
{
    $query = array_merge($_GET, $overrides);
    foreach ($query as $k => $v) {
        if ($v === null || $v === '') {
            unset($query[$k]);
        }
    }
    return 'dashboard.php' . (count($query) ? ('?' . http_build_query($query)) : '');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');
        body { background: #f8f9fa; font-family: 'Inter', Arial, sans-serif; color: #12141a; margin: 0; font-size: 14px; line-height: 1.45; height: 100vh; overflow: hidden; }
        .seller-layout { height: 100vh; display: flex; width: 100%; background: #f8f9fb; overflow: hidden; }
        .sidebar { width: 200px; min-width: 200px; background: #13151a; color: #d4d7dd; display: flex; flex-direction: column; padding: 12px 10px; height: 100vh; overflow-y: auto; flex-shrink: 0; }
        .brand { font-weight: 700; color: #fff; font-size: 15px; margin-bottom: 20px; line-height: 1.2; border-bottom: 1px solid #22252e; padding: 10px 8px 14px; }
        .brand small { display: block; color: #808797; font-weight: 500; font-size: 11px; }
        .menu-label { color: #636a78; font-size: 11px; margin: 0 8px 8px; text-transform: uppercase; letter-spacing: .08em; font-weight: 700; }
        .nav-link-custom { color: #b4bac8; text-decoration: none; display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 10px; margin-bottom: 4px; font-size: 14px; font-weight: 500; }
        .nav-link-custom:hover { color: #fff; background: #1b1d24; }
        .nav-link-custom.active { background: #f6f7f9; color: #111; font-weight: 600; }
        .sidebar-bottom { margin-top: auto; padding: 14px 8px 8px; border-top: 1px solid #22252e; }
        .account-name { color: #fff; font-size: 13px; font-weight: 600; line-height: 1.2; }
        .account-email { color: #7f8696; font-size: 11px; }
        .logout-link { display: inline-flex; margin-top: 10px; color: #aab1bf; text-decoration: none; font-size: 13px; }
        .logout-link:hover { color: #fff; }
        .content { flex: 1; overflow-y: auto; height: 100vh; width: 100%; transition: margin-left 0.3s; }
        .panel { background: #fff; border: 0; border-radius: 0; padding: 20px 24px; min-height: 100%; }
        .page-title { font-size: 24px; margin: 0; font-weight: 700; line-height: 1.1; }
        .subtitle { color: #9aa0ad; font-size: 13px; margin-top: 4px; font-weight: 500; }
        .stat-box { border: 1px solid #e7eaf0; border-radius: 9px; padding: 12px 14px; background: #fff; min-height: 92px; }
        .stat-box .label { font-size: 10px; color: #9ba2b0; text-transform: uppercase; letter-spacing: .11em; font-weight: 600; }
        .stat-box .value { font-size: 44px; font-weight: 700; line-height: .98; margin-top: 7px; }
        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .table-wrap { border: 1px solid #e7eaf0; border-radius: 10px; overflow: hidden; background: #fff; width: 100%; }
        .table { margin-bottom: 0; }
        .table thead th { background: #f9fafc; font-size: 11px; color: #9aa1ae; text-transform: uppercase; border-bottom: 1px solid #eceff4; letter-spacing: .08em; font-weight: 700; padding: 10px 11px; white-space: nowrap; }
        .table td { vertical-align: middle; font-size: 13px; color: #49505f; padding: 10px 11px; white-space: nowrap; }
        .table tbody tr { border-color: #eff1f5; }
        .table tbody tr:last-child td { border-bottom: 0; }
        .status-pill { font-size: 11px; border-radius: 6px; border: 1px solid #d5d8df; padding: 2px 9px; display: inline-block; font-weight: 500; background: #fff; }
        .status-in-stock { border-color: #b9c3d7; }
        .status-low-stock { border-color: #f0c084; background: #fff8ef; }
        .status-out-stock { border-color: #df9ca0; background: #fff3f4; }
        .status-delivered { border-color: #b9c3d7; }
        .status-pending { border-color: #d6d9e0; background: #f7f8fa; }
        .status-processing { border-color: #d8c6a5; background: #fff9ef; }
        .status-cancelled { border-color: #d7d7da; background: #f2f3f5; color: #8a909d; }
        .toolbar-btn { border-radius: 8px; font-weight: 600; font-size: 13px; padding: 8px 14px; }
        .order-card { border: 1px solid #e8e9ed; border-radius: 10px; padding: 14px; background: #fff; min-height: 156px; }
        .order-card .title { font-size: 10px; color: #8a909d; text-transform: uppercase; letter-spacing: .1em; margin-bottom: 12px; font-weight: 700; }
        .meta-row { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 9px; }
        .meta-icon { width: 26px; height: 26px; border: 1px solid #e7e8ec; border-radius: 7px; display: inline-flex; align-items: center; justify-content: center; color: #8a909d; font-size: 12px; }
        .meta-label { color: #8a909d; font-size: 11px; line-height: 1; margin-bottom: 2px; }
        .meta-value { font-size: 13px; font-weight: 600; line-height: 1.2; color: #141820; }
        .table-footer { display: flex; justify-content: space-between; align-items: center; color: #a0a7b4; font-size: 12px; border: 1px solid #e7eaf0; border-top: 0; border-radius: 0 0 10px 10px; padding: 10px 12px; }
        .mini-btn { border: 1px solid #e6e9ef; background: #fff; color: #9fa6b3; border-radius: 6px; padding: 3px 8px; font-size: 12px; }
        .mini-page { border: 1px solid #c5cad4; color: #161b24; border-radius: 4px; padding: 1px 5px; font-size: 11px; margin: 0 6px; }
        .form-control, .form-select { border-color: #e4e7ee; border-radius: 8px; font-size: 13px; min-height: 38px; }
        .modal-content { border: 0; border-radius: 14px; box-shadow: 0 24px 70px rgba(17, 22, 30, 0.25); }
        .modal-header { padding: 16px 18px 8px; }
        .modal-body { padding: 12px 18px; }
        .modal-footer { padding: 10px 18px 16px; }
        .modal-title { font-size: 26px; line-height: 1; }
        .form-label { font-size: 12px; color: #586072; }
        .action-btn { border-radius: 8px; font-size: 12px; padding: 5px 12px; }

        /* Mobile Header & Sidebar */
        .mobile-top-header { display: none; background: #13151a; color: #fff; padding: 10px 16px; align-items: center; justify-content: space-between; position: fixed; top: 0; left: 0; width: 100%; z-index: 1040; border-bottom: 1px solid #23262f; }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1045; backdrop-filter: blur(2px); }
        .hamburger-btn { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.15); border-radius: 8px; color: #fff; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; transition: all 0.2s; cursor: pointer; }
        .hamburger-btn:hover { background: rgba(255,255,255,0.2); }
        .hamburger-btn i { font-size: 20px; }

        @media (max-width: 991.98px) {
            .sidebar { position: fixed; left: 0; top: 0; transform: translateX(-100%); z-index: 1050; transition: transform 0.3s cubic-bezier(.4,0,.2,1); width: 240px; box-shadow: 10px 0 30px rgba(0,0,0,0.2); }
            .sidebar.sidebar-open { transform: translateX(0); }
            .mobile-top-header { display: flex; }
            .sidebar-overlay.active { display: block; }
            .content { padding-top: 56px; }
            .page-title { font-size: 20px; }
            .stat-box .value { font-size: 32px; }
            .panel { padding: 14px; }
        }
    </style>
</head>
<body x-data="{ mobileMenuOpen: false }">
<div class="mobile-top-header">
    <div class="d-flex align-items-center gap-2">
        <div class="bg-white text-dark rounded-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
            <i class="bi bi-journal-bookmark-fill" style="font-size: 14px;"></i>
        </div>
        <div>
            <span class="fw-bold" style="font-size:14px;">School Supply</span>
            <span class="d-block" style="font-size:10px;color:#7c8392;margin-top:-2px;">Seller Portal</span>
        </div>
    </div>
    <div class="hamburger-btn" @click="mobileMenuOpen = true">
        <i class="bi bi-list"></i>
    </div>
</div>

<div class="sidebar-overlay" :class="mobileMenuOpen ? 'active' : ''" @click="mobileMenuOpen = false"></div>

<div class="seller-layout">
    <aside class="sidebar" :class="mobileMenuOpen ? 'sidebar-open' : ''">
        <div class="brand d-flex align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-white text-dark rounded-3 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; flex-shrink: 0;">
                    <i class="bi bi-journal-bookmark-fill" style="font-size: 16px;"></i>
                </div>
                <div>
                    School Supply
                    <small>Seller Portal</small>
                </div>
            </div>
            <button class="btn btn-link text-white d-lg-none p-0 border-0" @click="mobileMenuOpen = false">
                <i class="bi bi-x-lg fs-5"></i>
            </button>
        </div>
        <div class="menu-label">Menu</div>
        <a class="nav-link-custom <?= $tab === 'inventory' ? 'active' : '' ?>" href="dashboard.php?tab=inventory">
            <i class="bi bi-box-seam"></i> Inventory
        </a>
        <a class="nav-link-custom <?= $tab === 'sales' ? 'active' : '' ?>" href="dashboard.php?tab=sales">
            <i class="bi bi-bar-chart"></i> Sales Report
        </a>
        <a class="nav-link-custom <?= $tab === 'orders' ? 'active' : '' ?>" href="dashboard.php?tab=orders">
            <i class="bi bi-bag"></i> Orders
        </a>

        <div class="sidebar-bottom">
            <div class="d-flex align-items-center gap-2 mb-2">
                <div style="width:36px;height:36px;border-radius:50%;overflow:hidden;background:#2a2d34;flex-shrink:0;display:flex;align-items:center;justify-content:center;">
                    <?php if (!empty($sellerProfileImageUrl)): ?>
                        <img src="<?= esc($sellerProfileImageUrl) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                        <i class="bi bi-person" style="color:#aab1bf;font-size:18px;"></i>
                    <?php endif; ?>
                </div>
                <div class="overflow-hidden">
                    <div class="account-name"><?= esc($sellerName) ?></div>
                    <div class="account-email"><?= esc($sellerEmail) ?></div>
                </div>
            </div>

            <button type="button" class="btn btn-outline-secondary w-100 btn-sm" data-bs-toggle="modal" data-bs-target="#profileModal">
                <i class="bi bi-pencil-square me-1"></i> Edit Profile
            </button>

            <a class="logout-link" href="../logout.php"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
        </div>
    </aside>

    <main class="content">
        <div class="panel">
            <?php if ($tab === 'inventory'): ?>
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                    <div>
                        <h1 class="page-title">Manage Inventory</h1>
                        <div class="subtitle"><?= $totalProducts ?> items total</div>
                    </div>
                    <button class="btn btn-dark toolbar-btn" data-bs-toggle="modal" data-bs-target="#addProductModal">
                        <i class="bi bi-plus"></i> Add Item
                    </button>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="stat-box">
                            <div class="label">Total Products</div>
                            <div class="value"><?= $totalProducts ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-box">
                            <div class="label">Total Units</div>
                            <div class="value"><?= $totalUnits ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-box">
                            <div class="label">Low Stock Alerts</div>
                            <div class="value"><?= $lowStockAlerts ?></div>
                        </div>
                    </div>
                </div>

                <div class="table-wrap">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Product Name</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                            </thead>
                        <tbody>
                        <?php foreach ($inventoryPageRows as $idx => $product): ?>
                            <?php
                            $stock = (int) $product['stock'];
                            // Derive status from current stock to keep UI accurate.
                            $status = productStatusFromStock($stock);
                            $statusClass = 'status-in-stock';
                            if ($status === 'Low Stock') {
                                $statusClass = 'status-low-stock';
                            } elseif ($status === 'Out of Stock') {
                                $statusClass = 'status-out-stock';
                            }
                            $unit = 'pc';
                            if (isset($product['description']) && stripos($product['description'], 'Unit:') === 0) {
                                $unit = trim(substr($product['description'], 5));
                            }
                            ?>
                            <tr>
                                <td><?= $inventoryPager['from'] + $idx ?></td>
                                <td><strong><?= esc($product['name']) ?></strong></td>
                                <td><?= 'SKU-' . str_pad((string) $product['id'], 3, '0', STR_PAD_LEFT) ?></td>
                                <td><?= esc(ucfirst(strtolower($product['category']))) ?></td>
                                <td>₱<?= number_format((float) $product['price'], 2) ?></td>
                                <td><?= $stock ?></td>
                                <td><span class="status-pill <?= $statusClass ?>"><?= esc($status) ?></span></td>
                                <td class="text-end">
                                    <button
                                        class="btn btn-sm btn-outline-secondary me-1 edit-product-btn action-btn"
                                        data-id="<?= (int) $product['id'] ?>"
                                        data-name="<?= esc($product['name']) ?>"
                                        data-category="<?= esc($product['category']) ?>"
                                        data-price="<?= number_format((float) $product['price'], 2, '.', '') ?>"
                                        data-stock="<?= $stock ?>"
                                        data-unit="<?= esc($unit) ?>"
                                    >
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </button>
                                    <button
                                        class="btn btn-sm btn-outline-secondary delete-product-btn action-btn"
                                        data-id="<?= (int) $product['id'] ?>"
                                        data-name="<?= esc($product['name']) ?>"
                                    >
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (count($inventoryPageRows) === 0): ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">No products found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="table-footer">
                    <span>Showing <?= $inventoryPager['from'] ?>-<?= $inventoryPager['to'] ?> of <?= $inventoryPager['total'] ?> items</span>
                    <span>
                        <?php if ($inventoryPager['current'] > 1): ?>
                            <a class="mini-btn text-decoration-none" href="<?= esc(pageUrl(['inventory_page' => $inventoryPager['current'] - 1])) ?>">Previous</a>
                        <?php else: ?>
                            <button class="mini-btn" type="button" disabled>Previous</button>
                        <?php endif; ?>
                        <span class="mini-page"><?= $inventoryPager['current'] ?></span>
                        <?php if ($inventoryPager['current'] < $inventoryPager['last']): ?>
                            <a class="mini-btn text-decoration-none" href="<?= esc(pageUrl(['inventory_page' => $inventoryPager['current'] + 1])) ?>">Next</a>
                        <?php else: ?>
                            <button class="mini-btn" type="button" disabled>Next</button>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'sales'): ?>
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                    <div>
                        <h1 class="page-title">Sales Report</h1>
                        <div class="subtitle">Transaction history overview</div>
                    </div>
                </div>
                <form method="get" class="d-flex flex-wrap align-items-end gap-2 mb-3">
                    <input type="hidden" name="tab" value="sales">
                    <div>
                        <label class="form-label small text-secondary mb-1">From</label>
                        <input type="date" name="date_from" class="form-control" value="<?= esc($salesDateFrom) ?>">
                    </div>
                    <div>
                        <label class="form-label small text-secondary mb-1">To</label>
                        <input type="date" name="date_to" class="form-control" value="<?= esc($salesDateTo) ?>">
                    </div>
                    <button type="submit" class="btn btn-outline-dark">Apply</button>
                    <a href="dashboard.php?tab=sales" class="btn btn-light border">Clear</a>
                    <?php
                    $salesExportQuery = http_build_query(array_filter(['date_from' => $salesDateFrom, 'date_to' => $salesDateTo]));
                    $salesExportUrl = 'actions/export_sales.php' . ($salesExportQuery ? '?' . $salesExportQuery : '');
                    $salesPrintUrl = 'actions/print_sales.php' . ($salesExportQuery ? '?' . $salesExportQuery : '');
                    ?>
                    <a class="btn btn-outline-dark toolbar-btn" href="<?= esc($salesExportUrl) ?>">
                        <i class="bi bi-download"></i> Export CSV
                    </a>
                    <a class="btn btn-dark toolbar-btn" href="<?= esc($salesPrintUrl) ?>" target="_blank">
                        <i class="bi bi-printer"></i> Print Report
                    </a>
                </form>
                <?php if ($salesDateFrom !== '' || $salesDateTo !== ''): ?>
                    <p class="text-secondary small mb-3">
                        Showing sales
                        <?php if ($salesDateFrom !== ''): ?>from <strong><?= esc($salesDateFrom) ?></strong><?php endif; ?>
                        <?php if ($salesDateTo !== ''): ?> to <strong><?= esc($salesDateTo) ?></strong><?php endif; ?>
                    </p>
                <?php endif; ?>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="stat-box">
                            <div class="label">Total Revenue</div>
                            <div class="value">₱<?= number_format($totalRevenue, 0) ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-box">
                            <div class="label">Transactions</div>
                            <div class="value"><?= count($transactions) ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-box">
                            <div class="label">Completed</div>
                            <div class="value"><?= $completedSales ?></div>
                        </div>
                    </div>
                </div>

                <div class="table-wrap">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                            <tr>
                                <th>TXN ID</th>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Qty</th>
                                <th>Unit Price</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($salesPageRows as $row): ?>
                                <?php
                                $statusClass = 'status-pending';
                                if ($row['status'] === 'Delivered') {
                                    $statusClass = 'status-delivered';
                                } elseif ($row['status'] === 'Processing') {
                                    $statusClass = 'status-processing';
                                } elseif ($row['status'] === 'Cancelled') {
                                    $statusClass = 'status-cancelled';
                                }
                                ?>
                                <tr>
                                    <td><?= esc($row['order_id']) ?></td>
                                    <td><?= esc($row['order_date']) ?></td>
                                    <td><strong><?= esc($row['product_name']) ?></strong></td>
                                    <td><?= esc(ucfirst(strtolower($row['category']))) ?></td>
                                    <td><?= (int) $row['quantity'] ?></td>
                                    <td>₱<?= number_format((float) $row['unit_price'], 2) ?></td>
                                    <td><strong>₱<?= number_format((float) $row['line_total'], 2) ?></strong></td>
                                    <td><span class="status-pill <?= $statusClass ?>"><?= esc($row['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (count($salesPageRows) === 0): ?>
                                <tr><td colspan="8" class="text-center py-4 text-muted">No transactions found.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="table-footer">
                    <span>Showing <?= $salesPager['from'] ?>-<?= $salesPager['to'] ?> of <?= $salesPager['total'] ?> transactions</span>
                    <span>
                        <?php if ($salesPager['current'] > 1): ?>
                            <a class="mini-btn text-decoration-none" href="<?= esc(pageUrl(['sales_page' => $salesPager['current'] - 1])) ?>">Previous</a>
                        <?php else: ?>
                            <button class="mini-btn" type="button" disabled>Previous</button>
                        <?php endif; ?>
                        <span class="mini-page"><?= $salesPager['current'] ?></span>
                        <?php if ($salesPager['current'] < $salesPager['last']): ?>
                            <a class="mini-btn text-decoration-none" href="<?= esc(pageUrl(['sales_page' => $salesPager['current'] + 1])) ?>">Next</a>
                        <?php else: ?>
                            <button class="mini-btn" type="button" disabled>Next</button>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'orders'): ?>
                <?php if ($selectedOrder): ?>
                    <?php if (!empty($_GET['fulfill_error'])): ?>
                        <div class="alert alert-danger border-0 shadow-sm mb-3">
                            <?= esc((string) $_GET['fulfill_error']) ?>
                        </div>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                        <div>
                            <h1 class="page-title">Order #<?= esc($selectedOrder['id']) ?></h1>
                            <div class="subtitle">Order details and fulfillment</div>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            <a href="dashboard.php?tab=orders" class="btn btn-outline-dark toolbar-btn">
                                <i class="bi bi-arrow-left"></i> Back to Orders
                            </a>
                            <span class="status-pill status-<?= strtolower($selectedOrder['status']) === 'delivered' ? 'delivered' : strtolower($selectedOrder['status']) ?>">
                                <?= esc($selectedOrder['status']) ?>
                            </span>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="order-card">
                                <div class="title">Customer Information</div>
                                <div class="meta-row">
                                    <span class="meta-icon"><i class="bi bi-person"></i></span>
                                    <div><div class="meta-label">Customer Name</div><div class="meta-value"><?= esc($selectedOrder['customer_name']) ?></div></div>
                                </div>
                                <div class="meta-row">
                                    <span class="meta-icon"><i class="bi bi-tag"></i></span>
                                    <div><div class="meta-label">Grade Level</div><div class="meta-value">Grade <?= preg_match('/(\d+)/', (string) ($selectedOrder['address'] ?? ''), $m) ? esc($m[1]) : '10' ?></div></div>
                                </div>
                                <div class="meta-row mb-0">
                                    <span class="meta-icon"><i class="bi bi-geo-alt"></i></span>
                                    <div><div class="meta-label">Address</div><div class="meta-value"><?= esc($selectedOrder['address'] ?: 'Cebu City') ?></div></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="order-card">
                                <div class="title">Order Information</div>
                                <div class="meta-row">
                                    <span class="meta-icon"><i class="bi bi-calendar3"></i></span>
                                    <div><div class="meta-label">Date & Time</div><div class="meta-value"><?= esc(date('M j, Y · g:i A', strtotime($selectedOrder['created_at']))) ?></div></div>
                                </div>
                                <div class="meta-row">
                                    <span class="meta-icon"><i class="bi bi-credit-card"></i></span>
                                    <div><div class="meta-label">Payment Method</div><div class="meta-value"><?= esc($selectedOrder['payment_method'] ?? 'Cash') ?></div></div>
                                </div>
                                <div class="meta-row mb-0">
                                    <span class="meta-icon"><i class="bi bi-bag-check"></i></span>
                                    <div><div class="meta-label">Order Status</div><div class="meta-value"><?= esc($selectedOrder['status']) ?></div></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-wrap mb-3">
                        <div class="table-responsive">
                            <table class="table mb-0">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Category</th>
                                        <th>Qty</th>
                                        <th>Unit Price</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($selectedOrderItems as $item): ?>
                                        <tr>
                                            <td><strong><?= esc($item['name']) ?></strong></td>
                                            <td><?= esc(ucfirst(strtolower($item['category']))) ?></td>
                                            <td><?= (int) $item['quantity'] ?></td>
                                            <td>₱<?= number_format((float) $item['price'], 2) ?></td>
                                            <td class="text-end"><strong>₱<?= number_format((float) $item['subtotal'], 2) ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr>
                                        <td colspan="4"><strong>Order Total</strong></td>
                                        <td class="text-end"><strong style="font-size: 30px;">₱<?= number_format((float) $selectedOrder['total_amount'], 2) ?></strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="dashboard.php?tab=orders" class="btn btn-outline-dark toolbar-btn">
                            <i class="bi bi-arrow-left"></i> Back to Orders
                        </a>
                        <?php if ($selectedOrder['status'] === 'Delivered' || $selectedOrder['status'] === 'Fulfilled'): ?>
                            <button type="button" class="btn btn-outline-dark toolbar-btn preview-receipt-btn" data-id="<?= esc($selectedOrder['id']) ?>">
                                <i class="bi bi-eye"></i> Preview Receipt
                            </button>
                            <a href="actions/generate_receipt.php?id=<?= urlencode($selectedOrder['id']) ?>" class="btn btn-outline-dark toolbar-btn">
                                <i class="bi bi-file-earmark-pdf"></i> Generate Receipt
                            </a>
                        <?php endif; ?>
                        <?php if ($selectedOrder['status'] === 'Pending' || $selectedOrder['status'] === 'Processing'): ?>
                            <form method="post" action="actions/cancel_order.php" onsubmit="return confirm('Cancel this customer order?');">
                                <input type="hidden" name="order_id" value="<?= esc($selectedOrder['id']) ?>">
                                <button class="btn btn-outline-danger toolbar-btn">
                                    <i class="bi bi-x-circle"></i> Cancel Order
                                </button>
                            </form>
                            <form method="post" action="actions/fulfill_order.php">
                                <input type="hidden" name="order_id" value="<?= esc($selectedOrder['id']) ?>">
                                <button class="btn btn-dark toolbar-btn">
                                    <i class="bi bi-check-circle"></i> Mark as Fulfilled
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                        <div>
                            <h1 class="page-title">Customer Orders</h1>
                            <div class="subtitle">Manage incoming orders and pickup status</div>
                        </div>
                        <form method="get" class="d-flex gap-2">
                            <input type="hidden" name="tab" value="orders">
                            <div class="position-relative">
                                <i class="bi bi-search position-absolute" style="left:10px;top:10px;color:#a1a8b5;font-size:12px;"></i>
                                <input type="text" class="form-control ps-4" name="search" placeholder="Search order/customer" value="<?= esc($search) ?>">
                            </div>
                            <select class="form-select" name="status">
                                <option value="">All Order Statuses</option>
                                <option value="Pending" <?= $statusFilter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="Processing" <?= $statusFilter === 'Processing' ? 'selected' : '' ?>>Processing</option>
                                <option value="Delivered" <?= $statusFilter === 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                                <option value="Cancelled" <?= $statusFilter === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                            <button class="btn btn-outline-dark">Apply</button>
                        </form>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <div class="stat-box"><div class="label">Total Orders</div><div class="value"><?= $totalOrders ?></div></div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-box"><div class="label">Delivered</div><div class="value"><?= $deliveredOrders ?></div></div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-box"><div class="label">Pending / Processing</div><div class="value"><?= $pendingOrProcessing ?></div></div>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Date</th>
                                        <th>Customer Name</th>
                                        <th>Grade Level</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ordersPageRows as $order): ?>
                                        <?php
                                        $statusClass = 'status-pending';
                                        $gradeDisplay = 'Grade 10';
                                        if (preg_match('/(\d+)/', (string) ($order['address'] ?? ''), $m)) {
                                            $gradeDisplay = 'Grade ' . $m[1];
                                        }
                                        if ($order['status'] === 'Delivered') {
                                            $statusClass = 'status-delivered';
                                        } elseif ($order['status'] === 'Processing') {
                                            $statusClass = 'status-processing';
                                        } elseif ($order['status'] === 'Cancelled') {
                                            $statusClass = 'status-cancelled';
                                        }
                                        ?>
                                        <tr>
                                            <td><?= esc($order['id']) ?></td>
                                            <td><?= esc(date('M j, Y', strtotime($order['created_at']))) ?></td>
                                            <td><strong><?= esc($order['customer_name']) ?></strong></td>
                                            <td><?= esc($gradeDisplay) ?></td>
                                            <td><strong>₱<?= number_format((float) $order['total_amount'], 2) ?></strong></td>
                                            <td><span class="status-pill <?= $statusClass ?>"><?= esc($order['status']) ?></span></td>
                                            <td class="text-end">
                                                <a href="dashboard.php?tab=orders&view=<?= urlencode($order['id']) ?>" class="btn btn-sm btn-outline-secondary">View Details</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (count($ordersPageRows) === 0): ?>
                                        <tr><td colspan="7" class="text-center py-4 text-muted">No orders found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="table-footer">
                        <span>Showing <?= $ordersPager['from'] ?>-<?= $ordersPager['to'] ?> of <?= $ordersPager['total'] ?> orders</span>
                        <span>
                            <?php if ($ordersPager['current'] > 1): ?>
                                <a class="mini-btn text-decoration-none" href="<?= esc(pageUrl(['orders_page' => $ordersPager['current'] - 1])) ?>">Previous</a>
                            <?php else: ?>
                                <button class="mini-btn" type="button" disabled>Previous</button>
                            <?php endif; ?>
                            <span class="mini-page"><?= $ordersPager['current'] ?></span>
                            <?php if ($ordersPager['current'] < $ordersPager['last']): ?>
                                <a class="mini-btn text-decoration-none" href="<?= esc(pageUrl(['orders_page' => $ordersPager['current'] + 1])) ?>">Next</a>
                            <?php else: ?>
                                <button class="mini-btn" type="button" disabled>Next</button>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<div class="modal fade" id="addProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="actions/add_item.php" enctype="multipart/form-data">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <h5 class="modal-title fw-bold">Add New Item</h5>
                        <div class="text-muted small">Fill in the product details below</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Product Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Product Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <div class="text-muted small mt-1">Optional. JPG/PNG/WebP/GIF</div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">SKU</label>
                            <input type="text" class="form-control" placeholder="Auto-generated" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Category</label>
                            <input type="text" name="category" class="form-control" value="Paper" required>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Price (₱)</label>
                            <input type="number" step="0.01" min="0" name="price" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Stock Qty</label>
                            <input type="number" min="0" name="stock" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Unit</label>
                            <input type="text" name="unit" class="form-control" value="pc">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark"><i class="bi bi-plus"></i> Add Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="actions/edit_item.php" enctype="multipart/form-data">
                <input type="hidden" name="id" id="edit-id">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <h5 class="modal-title fw-bold">Edit Item</h5>
                        <div class="text-muted small">Update product information</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Product Name</label>
                        <input type="text" name="name" id="edit-name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Replace Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <div class="text-muted small mt-1">Optional. Leave empty to keep current image.</div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">SKU</label>
                            <input type="text" id="edit-sku" class="form-control" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Category</label>
                            <input type="text" name="category" id="edit-category" class="form-control" required>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Price (₱)</label>
                            <input type="number" step="0.01" min="0" name="price" id="edit-price" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Stock Qty</label>
                            <input type="number" min="0" name="stock" id="edit-stock" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Unit</label>
                            <input type="text" name="unit" id="edit-unit" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark"><i class="bi bi-check2"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="actions/delete_item.php">
                <input type="hidden" name="id" id="delete-id">
                <div class="modal-body py-4 text-center">
                    <div class="mb-2"><i class="bi bi-trash fs-2 text-muted"></i></div>
                    <h5 class="fw-bold mb-2">Delete Product?</h5>
                    <p class="text-muted mb-1">You are about to delete <strong id="delete-name"></strong>.</p>
                    <p class="text-muted small mb-3">This action cannot be undone.</p>
                    <div class="d-flex justify-content-center gap-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-dark"><i class="bi bi-trash"></i> Yes, Delete</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="receiptPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Receipt Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="receipt-preview-content" class="p-2">
                    <div class="text-center py-5">
                        <div class="spinner-border text-dark" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-secondary small">Preparing preview...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light btn-slim" data-bs-dismiss="modal">Close</button>
                <a href="#" id="preview-download-pdf" class="btn btn-dark btn-slim">
                    <i class="bi bi-download me-1"></i> Download PDF
                </a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="profileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="actions/update_profile.php" enctype="multipart/form-data">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <h5 class="modal-title fw-bold">Edit Profile</h5>
                        <div class="text-muted small">Update your name, email, and profile image</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Full Name</label>
                        <input type="text" name="name" class="form-control" value="<?= esc($sellerName) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= esc($sellerEmail) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Profile Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <div class="text-muted small mt-1">Optional</div>
                    </div>
                    <?php if (!empty($sellerProfileImageUrl)): ?>
                        <div class="mb-3 text-center">
                            <div class="text-muted small fw-semibold mb-2">Current image</div>
                            <div style="width:160px;height:160px;margin:0 auto;border-radius:12px;overflow:hidden;border:1px solid #e7e8ec;">
                                <img src="<?= esc($sellerProfileImageUrl) ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;">
                            </div>
                        </div>
                    <?php endif; ?>
                    <hr>
                    <div class="mb-3">
                        <div class="fw-bold mb-1">Change Password</div>
                        <div class="text-muted small mb-2">Leave blank if you don't want to change your password.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">Current password</label>
                        <input type="password" name="current_password" class="form-control" autocomplete="current-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">New password</label>
                        <input type="password" name="new_password" class="form-control" autocomplete="new-password">
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold text-secondary small">Confirm new password</label>
                        <input type="password" name="confirm_new_password" class="form-control" autocomplete="new-password">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark"><i class="bi bi-check2"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const editModal = new bootstrap.Modal(document.getElementById('editProductModal'));
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteProductModal'));

    document.querySelectorAll('.edit-product-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.id;
            document.getElementById('edit-id').value = id;
            document.getElementById('edit-name').value = btn.dataset.name;
            document.getElementById('edit-category').value = btn.dataset.category;
            document.getElementById('edit-price').value = btn.dataset.price;
            document.getElementById('edit-stock').value = btn.dataset.stock;
            document.getElementById('edit-unit').value = btn.dataset.unit || 'pc';
            document.getElementById('edit-sku').value = 'SKU-' + String(id).padStart(3, '0');
            editModal.show();
        });
    });

    document.querySelectorAll('.delete-product-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('delete-id').value = btn.dataset.id;
            document.getElementById('delete-name').textContent = btn.dataset.name;
            deleteModal.show();
        });
    });

    const previewModal = new bootstrap.Modal(document.getElementById('receiptPreviewModal'));
    document.querySelectorAll('.preview-receipt-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.id;
            const content = document.getElementById('receipt-preview-content');
            const downloadBtn = document.getElementById('preview-download-pdf');
            
            // Show modal and loading state
            previewModal.show();
            content.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-dark" role="status"></div><p class="mt-2 text-secondary small">Preparing preview...</p></div>';
            downloadBtn.href = `actions/generate_receipt.php?id=${id}`;
            
            try {
                const response = await fetch(`actions/get_receipt_preview.php?id=${id}`);
                if (response.ok) {
                    content.innerHTML = await response.text();
                } else {
                    content.innerHTML = '<div class="alert alert-danger text-center shadow-sm">Failed to load preview. Please try downloading the PDF directly.</div>';
                }
            } catch (error) {
                content.innerHTML = '<div class="alert alert-danger text-center shadow-sm">Network error occurred.</div>';
            }
        });
    });
</script>
</body>
</html>
