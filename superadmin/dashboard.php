<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../admin/includes/helpers.php';

// Strict Super Admin Access Control
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin' || empty($_SESSION['is_super_admin'])) {
    header('Location: ../index.php?action=login');
    exit;
}

require_once __DIR__ . '/includes/dashboard_data.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');
        body { background: #f8f9fa; font-family: 'Inter', sans-serif; color: #12141a; margin: 0; font-size: 14px; line-height: 1.45; height: 100vh; overflow: hidden; }
        .layout { height: 100vh; display: flex; width: 100%; background: #f8f9fb; overflow: hidden; }
        .sidebar { width: 210px; min-width: 210px; background: #13151a; color: #c6ccda; padding: 14px 12px; display: flex; flex-direction: column; height: 100vh; overflow-y: auto; flex-shrink: 0; }
        .brand { color: #fff; font-weight: 700; font-size: 15px; border-bottom: 1px solid #23262f; padding: 10px 8px 14px; margin-bottom: 14px; }
        .brand small { display: block; color: #7c8392; font-size: 11px; font-weight: 500; }
        .menu-label { color: #616877; font-size: 11px; margin: 24px 8px 8px; text-transform: uppercase; letter-spacing: .08em; font-weight: 700; }
        .menu-label:first-of-type { margin-top: 0; }
        .nav-link-custom { color: #adb4c2; text-decoration: none; font-size: 14px; border-radius: 10px; padding: 10px 12px; margin-bottom: 4px; display: flex; gap: 10px; align-items: center; font-weight: 500; transition: 0.2s; }
        .nav-link-custom.active { background: #f5f6f8; color: #12141a; font-weight: 600; }
        .nav-link-custom:hover { background: #1c2029; color: #fff; }
        .sidebar-bottom { margin-top: auto; border-top: 1px solid #23262f; padding: 12px 8px 6px; }
        .acc-name { color: #fff; font-size: 13px; font-weight: 600; }
        .acc-email { color: #7f8696; font-size: 11px; }
        .logout-link { color: #aab1bf; text-decoration: none; font-size: 13px; margin-top: 10px; display: inline-flex; }
        .content { flex: 1; overflow-y: auto; height: 100vh; width: 100%; transition: margin-left 0.3s; }
        .panel { background: #fff; border: 0; min-height: 100%; }
        .header { border-bottom: 1px solid #eaedf2; padding: 14px 20px 12px; }
        .page-title { margin: 0; font-size: 24px; font-weight: 700; line-height: 1.1; }
        .subtitle { color: #9ba2b0; font-size: 13px; margin-top: 4px; font-weight: 500; }
        .inner { padding: 16px 20px; }
        .stat-box { border: 1px solid #e7eaf0; border-radius: 8px; min-height: 84px; padding: 12px 14px; background: #fff; }
        .stat-box .label { color: #9ba2b0; font-size: 10px; text-transform: uppercase; letter-spacing: .1em; font-weight: 700; }
        .stat-box .value { font-size: 38px; font-weight: 700; line-height: 1; margin-top: 8px; }
        .table-wrap { border: 1px solid #e7eaf0; border-radius: 8px; overflow: hidden; background: #fff; width: 100%; }
        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .table thead th { background: #f9fafc; font-size: 11px; color: #9aa1ae; letter-spacing: .08em; text-transform: uppercase; padding: 9px 10px; border-bottom: 1px solid #eceff4; font-weight: 700; white-space: nowrap; }
        .table td { font-size: 13px; color: #4a5160; padding: 10px; vertical-align: middle; white-space: nowrap; }
        .status-pill { font-size: 11px; border-radius: 6px; border: 1px solid #d5d8df; padding: 2px 8px; display: inline-block; background: #fff; }
        .status-delivered, .status-active, .status-completed, .status-in-stock { border-color: #111; color: #111; }
        .status-suspended { background: #fff8ef; border-color: #f0c084; color: #7a4a00; }
        .status-banned { background: #fff3f4; border-color: #df9ca0; color: #8a1c1c; }
        .status-pending, .status-pending-approval, .status-inactive, .status-cancelled, .status-out-of-stock { background: #f3f4f7; color: #9aa2af; border-color: #e0e3e8; }
        .status-processing, .status-low-stock { background: #f7f8fa; color: #8e96a4; border-color: #dde1e7; }
        .btn-slim { font-size: 12px; border-radius: 8px; padding: 6px 11px; font-weight: 600; }
        .form-control, .form-select { min-height: 36px; border-color: #e4e7ee; border-radius: 8px; font-size: 13px; }
        .chart-card { border: 1px solid #e7eaf0; border-radius: 10px; padding: 16px; background: #fff; }
        
        /* New Dashboard Redesign Styles */
        .dash-card { background: #fff; border-radius: 16px; padding: 20px; border: 1px solid #f1f2f4; box-shadow: 0 4px 12px rgba(0,0,0,0.02); height: 100%; display: flex; flex-direction: column; transition: transform 0.2s; }
        .dash-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,0.04); }
        .dash-card-icon { width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 16px; color: #111; }
        .dash-pct-badge { font-size: 11px; font-weight: 700; padding: 4px 8px; border-radius: 20px; }
        .dash-card-value { font-size: 26px; font-weight: 800; color: #111; margin-top: auto; line-height: 1.2; }
        .dash-card-label { font-size: 13px; color: #7f8696; font-weight: 500; margin-top: 4px; }
        .dash-card-footer { display: flex; align-items: center; }
        
        .dash-panel { background: #fff; border-radius: 16px; padding: 24px; border: 1px solid #f1f2f4; box-shadow: 0 4px 12px rgba(0,0,0,0.02); }
        .latest-orders-list { display: flex; flex-direction: column; gap: 16px; margin-top: 16px; }
        .latest-order-item { display: flex; align-items: center; justify-content: space-between; padding-bottom: 16px; }
        .latest-order-item:last-child { padding-bottom: 0; border-bottom: none !important; }
        .lo-icon { width: 36px; height: 36px; border-radius: 10px; background: #f8f9fa; display: flex; align-items: center; justify-content: center; color: #444; font-size: 16px; }
        
        .dash-period-tabs { display: flex; background: #f8f9fa; padding: 4px; border-radius: 10px; gap: 4px; }
        .dash-period-tab { text-decoration: none; color: #666; font-size: 12px; font-weight: 600; padding: 6px 14px; border-radius: 8px; transition: 0.2s; }
        .dash-period-tab.active { background: #fff; color: #111; box-shadow: 0 2px 6px rgba(0,0,0,0.05); }
        .dash-period-tab:hover:not(.active) { color: #111; }

        /* Mobile Header & Sidebar */
        .mobile-top-header { display: none; background: #13151a; color: #fff; padding: 10px 16px; align-items: center; justify-content: space-between; position: fixed; top: 0; left: 0; width: 100%; z-index: 1040; border-bottom: 1px solid #23262f; }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1045; backdrop-filter: blur(2px); }
        .hamburger-btn { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.15); border-radius: 8px; color: #fff; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; transition: all 0.2s; cursor: pointer; }
        .hamburger-btn:hover { background: rgba(255,255,255,0.2); }
        .hamburger-btn i { font-size: 20px; }

        @media (max-width: 991.98px) {
            .sidebar { position: fixed; left: 0; top: 0; transform: translateX(-100%); z-index: 1050; transition: transform 0.3s cubic-bezier(.4,0,.2,1); width: 260px; box-shadow: 10px 0 30px rgba(0,0,0,0.2); }
            .sidebar.sidebar-open { transform: translateX(0); }
            .mobile-top-header { display: flex; }
            .sidebar-overlay.active { display: block; }
            .content { padding-top: 56px; }
            .page-title { font-size: 20px; }
            .stat-box .value { font-size: 28px; }
            .inner { padding: 14px; }
        }
    </style>
</head>
<body x-data="{ mobileMenuOpen: false }">
<div class="mobile-top-header">
    <div class="d-flex align-items-center gap-2">
        <div class="bg-white text-dark rounded-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
            <i class="bi bi-shield-lock-fill" style="font-size: 14px;"></i>
        </div>
        <div>
            <span class="fw-bold" style="font-size:14px;">School Supply</span>
            <span class="d-block" style="font-size:10px;color:#7c8392;margin-top:-2px;">Super Admin</span>
        </div>
    </div>
    <div class="hamburger-btn" @click="mobileMenuOpen = true">
        <i class="bi bi-list"></i>
    </div>
</div>

<div class="sidebar-overlay" :class="mobileMenuOpen ? 'active' : ''" @click="mobileMenuOpen = false"></div>

<div class="layout">
    <?php include __DIR__ . '/views/sidebar.php'; ?>

    <main class="content">
        <section class="panel">
            <?php if ($tab === 'dashboard'): ?>
                <div class="header d-flex justify-content-between align-items-start">
                    <div>
                        <h1 class="page-title">Super Admin Dashboard</h1>
                        <div class="subtitle">Elevated system control and management overview.</div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="actions/export_revenue.php" class="btn btn-dark btn-slim">
                            <i class="bi bi-download me-1"></i> Export Revenue
                        </a>
                    </div>
                </div>
                <div class="inner">
                    <!-- Stat Cards Row -->
                    <div class="row g-3">
                        <?php
                        $dashCards = [
                            ['icon' => 'bi-bag-check-fill', 'label' => 'Total Sales', 'value' => '₱' . number_format($totalRevenue, 2), 'pct' => $revenueChangePercent, 'bg' => '#f3f3f3'],
                            ['icon' => 'bi-cart-fill',      'label' => 'Total Orders', 'value' => $totalOrders, 'pct' => $ordersChangePercent, 'bg' => '#eaeaea'],
                            ['icon' => 'bi-people-fill',    'label' => 'Total Users',  'value' => $countUsers, 'pct' => $usersChangePercent, 'bg' => '#f0f0f0'],
                            ['icon' => 'bi-box-seam-fill',  'label' => 'Total Products','value' => $totalProducts, 'pct' => $productsChangePercent, 'bg' => '#ececec'],
                        ];
                        foreach ($dashCards as $card):
                            $isUp = $card['pct'] >= 0;
                            $arrow = $isUp ? 'bi-arrow-up-short' : 'bi-arrow-down-short';
                            $pctColor = $isUp ? '#1a8a3f' : '#c0392b';
                            $badgeBg = $isUp ? '#e8f5e9' : '#fdecea';
                        ?>
                        <div class="col-6 col-md-3">
                            <div class="dash-card">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="dash-card-icon" style="background: <?= $card['bg'] ?>;"><i class="bi <?= $card['icon'] ?>"></i></div>
                                    <span class="dash-pct-badge" style="background: <?= $badgeBg ?>; color: <?= $pctColor ?>;"><?= ($isUp ? '+' : '') . $card['pct'] ?>%</span>
                                </div>
                                <div class="dash-card-value"><?= $card['value'] ?></div>
                                <div class="dash-card-label"><?= $card['label'] ?></div>
                                <div class="dash-card-footer mt-2">
                                    <i class="bi <?= $arrow ?>" style="color: <?= $pctColor ?>; font-size: 16px;"></i>
                                    <span style="color: <?= $pctColor ?>; font-weight: 600; font-size: 11px;"><?= abs($card['pct']) ?>%</span>
                                    <span class="text-muted" style="font-size: 11px; margin-left: 2px;">vs last month</span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Activity Chart + Latest Orders -->
                    <div class="row g-3 mt-1">
                        <div class="col-md-7">
                            <div class="dash-panel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="fw-bold mb-0" style="font-size: 15px;">Activity</h6>
                                    <span class="badge" style="background: #f3f4f6; color: #555; font-size: 11px; font-weight: 600; border-radius: 6px; padding: 5px 10px;">Revenue Trend</span>
                                </div>
                                <div style="height: 260px; width: 100%;">
                                    <canvas id="revenueChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="dash-panel" style="height: 100%;">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="fw-bold mb-0" style="font-size: 15px;">Latest Orders</h6>
                                    <a href="?tab=orders" class="text-muted" style="font-size: 12px; text-decoration: none; font-weight: 500;">View all</a>
                                </div>
                                <div class="latest-orders-list">
                                    <?php if (empty($latestOrders)): ?>
                                        <div class="text-center py-4 text-muted" style="font-size: 13px;">No orders yet.</div>
                                    <?php endif; ?>
                                    <?php foreach ($latestOrders as $i => $lo):
                                        $statusIcons = ['Delivered' => 'bi-check-circle-fill', 'Pending' => 'bi-clock-fill', 'Processing' => 'bi-arrow-repeat', 'Cancelled' => 'bi-x-circle-fill'];
                                        $statusIcon = $statusIcons[$lo['status']] ?? 'bi-receipt';
                                    ?>
                                    <div class="latest-order-item<?= $i < count($latestOrders) - 1 ? ' border-bottom' : '' ?>">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="lo-icon"><i class="bi <?= $statusIcon ?>"></i></div>
                                            <div>
                                                <div class="fw-semibold" style="font-size: 13px; color: #1a1a1a;"><?= esc($lo['items']) ?></div>
                                                <div class="text-muted" style="font-size: 11px;">
                                                    <span class="fw-medium text-dark"><?= esc($lo['id']) ?></span> &bull; <?= esc($lo['customer']) ?> &bull; <?= $lo['date'] ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <span class="fw-bold" style="font-size: 13px; color: #1a1a1a;">₱<?= number_format($lo['total'], 2) ?></span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Order List Table -->
                    <div class="row g-3 mt-1">
                        <div class="col-12">
                            <div class="dash-panel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="fw-bold mb-0" style="font-size: 15px;">Order List</h6>
                                    <div class="dash-period-tabs">
                                        <a href="?tab=dashboard&period=monthly" class="dash-period-tab <?= $orderListPeriod === 'monthly' ? 'active' : '' ?>">Monthly</a>
                                        <a href="?tab=dashboard&period=weekly" class="dash-period-tab <?= $orderListPeriod === 'weekly' ? 'active' : '' ?>">Weekly</a>
                                        <a href="?tab=dashboard&period=today" class="dash-period-tab <?= $orderListPeriod === 'today' ? 'active' : '' ?>">Today</a>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Order ID</th>
                                                <th>Product</th>
                                                <th>Customer Name</th>
                                                <th>Date</th>
                                                <th>Amount</th>
                                                <th>Order Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($dashboardOrderList)): ?>
                                                <tr><td colspan="6" class="text-center py-4 text-muted">No orders found for this period.</td></tr>
                                            <?php endif; ?>
                                            <?php foreach ($dashboardOrderList as $dol): ?>
                                            <tr>
                                                <td><?= esc($dol['id']) ?></td>
                                                <td><strong><?= esc($dol['items']) ?></strong></td>
                                                <td><?= esc($dol['customer']) ?></td>
                                                <td><?= $dol['date'] ?></td>
                                                <td><strong>₱<?= number_format($dol['total'], 2) ?></strong></td>
                                                <td><span class="status-pill status-<?= strtolower($dol['status']) ?>"><?= $dol['status'] ?></span></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php elseif ($tab === 'profile'): ?>
                <div class="header d-flex justify-content-between align-items-start">
                    <div>
                        <h1 class="page-title">Profile</h1>
                        <div class="subtitle">Update your name, email, profile picture, and password</div>
                    </div>
                </div>
                <div class="inner">
                    <?php if (isset($_GET['saved']) && $_GET['saved'] === '1'): ?>
                        <div class="alert alert-success py-2 rounded-xl mb-3" style="font-size: 13px;">Profile saved successfully.</div>
                    <?php endif; ?>
                    <?php if (isset($_GET['error'])): ?>
                        <?php
                        $pe = (string) $_GET['error'];
                        $pmsg = match ($pe) {
                            'invalid' => 'Please enter your full name.',
                            'invalid_email' => 'Please enter a valid email address.',
                            'email_taken' => 'That email is already in use.',
                            'save_failed' => 'Could not save changes. Please try again.',
                            'wrong_password' => 'Current password is incorrect.',
                            'password_mismatch' => 'New passwords do not match.',
                            'weak_password' => 'New password must be at least 8 characters.',
                            default => 'Something went wrong. Please try again.',
                        };
                        ?>
                        <div class="alert alert-danger py-2 rounded-xl mb-3" style="font-size: 13px;"><?= esc($pmsg) ?></div>
                    <?php endif; ?>
                    <form method="post" action="actions/update_profile.php" enctype="multipart/form-data" class="row g-4 align-items-start">
                        <div class="col-md-4 col-lg-3">
                            <div class="dash-panel h-100 p-3">
                                <div class="detail-title fw-bold mb-3">Profile photo</div>
                                <div class="d-flex justify-content-center mb-3">
                                    <div class="rounded-circle overflow-hidden d-flex align-items-center justify-content-center bg-light border" style="width: 120px; height: 120px;">
                                        <?php if (!empty($adminProfileImageUrl)): ?>
                                            <img src="<?= esc($adminProfileImageUrl) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                                        <?php else: ?>
                                            <i class="bi bi-person text-secondary" style="font-size: 3rem;"></i>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <label class="form-label text-secondary small fw-medium mb-1">Upload image</label>
                                <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
                                <div class="text-muted small mt-2" style="font-size: 11px;">JPEG, PNG, WebP, or GIF. Max 2 MB. Leave empty to keep the current photo.</div>
                            </div>
                        </div>
                        <div class="col-md-8 col-lg-9">
                            <div class="dash-panel p-4">
                                <div class="detail-title fw-bold mb-3">Account details</div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label text-secondary small fw-medium mb-1">Full name</label>
                                        <input type="text" name="name" class="form-control" value="<?= esc($adminName) ?>" required autocomplete="name">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-secondary small fw-medium mb-1">Email</label>
                                        <input type="email" name="email" class="form-control" value="<?= esc($adminEmail) ?>" required autocomplete="email">
                                    </div>
                                </div>
                                <div class="row g-3 mt-1">
                                    <div class="col-12"><hr class="text-muted"></div>
                                    <div class="col-12">
                                        <div class="detail-title fw-bold mb-2">Change password</div>
                                        <div class="text-muted small mb-3" style="font-size: 12px;">Leave blank if you don't want to change your password.</div>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label text-secondary small fw-medium mb-1">Current password</label>
                                        <input type="password" name="current_password" class="form-control" autocomplete="current-password">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-secondary small fw-medium mb-1">New password</label>
                                        <input type="password" name="new_password" class="form-control" autocomplete="new-password">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-secondary small fw-medium mb-1">Confirm new password</label>
                                        <input type="password" name="confirm_new_password" class="form-control" autocomplete="new-password">
                                    </div>
                                </div>
                                <div class="mt-4 d-flex flex-wrap gap-2 justify-content-end">
                                    <a href="dashboard.php?tab=dashboard" class="btn btn-outline-secondary btn-slim">Cancel</a>
                                    <button type="submit" class="btn btn-dark btn-slim"><i class="bi bi-save"></i> Save changes</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            <?php elseif ($tab === 'users'): ?>
                <div class="header d-flex justify-content-between align-items-start">
                    <div>
                        <h1 class="page-title">User Management</h1>
                        <div class="subtitle">Control Admins, Sellers, and Customers across the system.</div>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-dark btn-slim" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                            <i class="bi bi-person-plus-fill me-1"></i> Add Admin
                        </button>
                    </div>
                </div>
                <div class="inner">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr><th>UID</th><th>Name</th><th>Role</th><th>Status</th><th class="text-end">Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?= esc($u['uid']) ?></td>
                                    <td><strong><?= esc($u['name']) ?></strong><br><small class="text-muted"><?= esc($u['email']) ?></small></td>
                                    <td><span class="status-pill"><?= $u['role'] ?></span></td>
                                    <td><span class="status-pill status-<?= strtolower(str_replace(' ', '-', (string)$u['status'])) ?>"><?= $u['status'] ?></span></td>
                                    <td class="text-end text-nowrap">
                                        <?php if ($u['type'] === 'admin' && !empty($u['is_super_admin'])): ?>
                                            <span class="text-muted small px-2">System Locked</span>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-dark btn-slim edit-user-btn" 
                                                    data-id="<?= $u['id'] ?>" 
                                                    data-type="<?= $u['type'] ?>"
                                                    data-name="<?= esc($u['name']) ?>"
                                                    data-email="<?= esc($u['email']) ?>">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <form method="post" action="actions/delete_user.php" class="d-inline ms-1" onsubmit="return confirm('Delete this account permanently?');">
                                                <input type="hidden" name="target_type" value="<?= $u['type'] ?>">
                                                <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                                                <button class="btn btn-sm btn-outline-danger btn-slim"><i class="bi bi-trash"></i></button>
                                            </form>
                                            <form method="post" action="actions/update_user_status.php" class="d-inline ms-1">
                                                <input type="hidden" name="admin_action" value="<?= $u['status'] === 'Banned' ? 'unban_user' : 'ban_user' ?>">
                                                <input type="hidden" name="target_type" value="<?= $u['type'] ?>">
                                                <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                                                <button class="btn btn-sm btn-outline-secondary btn-slim"><?= $u['status'] === 'Banned' ? 'Unban' : 'Ban' ?></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php elseif ($tab === 'approvals'): ?>
                <div class="header d-flex justify-content-between align-items-start">
                    <div>
                        <h1 class="page-title">Account Approvals</h1>
                        <div class="subtitle">Pending registration requests for review.</div>
                    </div>
                </div>
                <div class="inner">
                    <div class="table-wrap">
                        <div class="table-responsive">
                            <table class="table mb-0">
                                <thead>
                                    <tr><th>User ID</th><th>Name</th><th>Role</th><th>Joined</th><th>Status</th><th class="text-end">Action</th></tr>
                                </thead>
                                <tbody>
                                <?php if (count($pendingApprovals) === 0): ?>
                                    <tr><td colspan="6" class="text-center py-4 text-muted">No pending approvals.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($pendingApprovals as $u): ?>
                                    <tr>
                                        <td><?= esc($u['uid']) ?></td>
                                        <td><strong><?= esc($u['name']) ?></strong><br><small class="text-muted"><?= esc($u['email']) ?></small></td>
                                        <td><span class="status-pill"><?= esc($u['role']) ?></span></td>
                                        <td><?= esc($u['joined']) ?></td>
                                        <td><span class="status-pill status-pending">Pending Approval</span></td>
                                        <td class="text-end">
                                            <form method="post" action="actions/update_user_status.php" class="d-inline">
                                                <input type="hidden" name="admin_action" value="approve_user">
                                                <input type="hidden" name="target_type" value="<?= esc($u['type']) ?>">
                                                <input type="hidden" name="target_id" value="<?= (int) $u['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-dark btn-slim"><i class="bi bi-check2-circle"></i> Approve</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php elseif ($tab === 'products'): ?>
                <div class="header d-flex justify-content-between align-items-start">
                    <div>
                        <h1 class="page-title">Global Products</h1>
                        <div class="subtitle">Consolidated product inventory management.</div>
                    </div>
                </div>
                <div class="inner">
                    <div class="table-wrap">
                        <div class="table-responsive">
                            <table class="table mb-0">
                                <thead>
                                    <tr><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Seller</th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ($products as $p): ?>
                                    <tr>
                                        <td><strong><?= esc($p['name']) ?></strong><br><small class="text-muted"><?= esc($p['sku']) ?></small></td>
                                        <td><?= esc($p['category']) ?></td>
                                        <td><strong>₱<?= number_format($p['price'], 2) ?></strong></td>
                                        <td><?= $p['stock'] ?></td>
                                        <td><span class="status-pill status-<?= strtolower(str_replace(' ', '-', $p['status'])) ?>"><?= esc($p['status']) ?></span></td>
                                        <td><?= esc($p['seller']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php elseif ($tab === 'orders'): ?>
                <div class="header d-flex justify-content-between align-items-start">
                    <div>
                        <h1 class="page-title">All System Orders</h1>
                        <div class="subtitle">Global view of all transactions and fulfillment status.</div>
                    </div>
                </div>
                <div class="inner">
                    <div class="table-wrap">
                        <table class="table mb-0">
                            <thead>
                                <tr><th>Order ID</th><th>Customer</th><th>Date</th><th>Items</th><th>Total</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($orders as $o): ?>
                                <tr>
                                    <td><?= esc($o['id']) ?></td>
                                    <td><strong><?= esc($o['customer']) ?></strong></td>
                                    <td><?= esc($o['date']) ?></td>
                                    <td><?= esc($o['items']) ?></td>
                                    <td><strong>₱<?= number_format($o['total'], 2) ?></strong></td>
                                    <td><span class="status-pill status-<?= strtolower(str_replace(' ', '-', $o['status'])) ?>"><?= esc($o['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php elseif ($tab === 'reports'): ?>
                <div class="header d-flex justify-content-between align-items-start">
                    <div>
                        <h1 class="page-title">System Reports</h1>
                        <div class="subtitle">Aggregate performance and revenue data.</div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="actions/export_revenue.php" class="btn btn-dark btn-slim">
                            <i class="bi bi-download me-1"></i> Export CSV
                        </a>
                    </div>
                </div>
                <div class="inner">
                    <div class="table-wrap">
                        <table class="table mb-0">
                            <thead>
                                <tr><th>Date</th><th>Product</th><th>Qty</th><th>Sales</th><th>Seller</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($reportRows as $r): ?>
                                <tr>
                                    <td><?= $r['date'] ?></td>
                                    <td><strong><?= esc($r['product']) ?></strong></td>
                                    <td><?= $r['qty'] ?></td>
                                    <td><strong>₱<?= number_format($r['total'], 2) ?></strong></td>
                                    <td><?= esc($r['seller']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php elseif ($tab === 'activity_logs'): ?>
                <div class="header d-flex justify-content-between align-items-start">
                    <div>
                        <h1 class="page-title">System Activity Logs</h1>
                        <div class="subtitle">Full audit trail of all management actions.</div>
                    </div>
                </div>
                <div class="inner">
                    <div class="alert alert-info py-2" style="font-size: 13px;">Historical activity tracking is now active. All administrative actions are recorded here.</div>
                    <div class="table-wrap">
                        <table class="table mb-0">
                            <thead>
                                <tr><th>Date</th><th>User</th><th>Action</th><th>Description</th></tr>
                            </thead>
                            <tbody>
                                <?php
                                $logs = $conn->query("SELECT created_at, user_name, action_type, description FROM activity_logs ORDER BY created_at DESC LIMIT 50");
                                while($logs && $l = $logs->fetch_assoc()): ?>
                                <tr>
                                    <td class="text-nowrap"><?= date('M j, g:i A', strtotime($l['created_at'])) ?></td>
                                    <td><?= esc($l['user_name']) ?></td>
                                    <td><span class="status-pill"><?= esc($l['action_type']) ?></span></td>
                                    <td class="small"><?= esc($l['description']) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<!-- Modal: Add Admin -->
<div class="modal fade" id="addAdminModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-sm" style="border-radius: 12px;">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold">Provision New Admin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="actions/add_admin.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label small fw-medium text-secondary mb-1">Username</label><input type="text" name="username" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label small fw-medium text-secondary mb-1">Email</label><input type="email" name="email" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label small fw-medium text-secondary mb-1">Initial Password</label><input type="password" name="password" class="form-control" required minlength="8"></div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light btn-slim" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark btn-slim">Create Admin Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit User -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-sm" style="border-radius: 12px;">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold">Edit User Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="actions/edit_user.php" method="POST">
                <input type="hidden" name="target_id" id="edit-user-id">
                <input type="hidden" name="target_type" id="edit-user-type">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-medium text-secondary mb-1">Full Name</label>
                        <input type="text" name="name" id="edit-user-name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium text-secondary mb-1">Email Address</label>
                        <input type="email" name="email" id="edit-user-email" class="form-control" required>
                    </div>
                    <div class="mb-2" id="password-edit-container" style="display: none;">
                        <label class="form-label small fw-medium text-secondary mb-1">New Password (Admin Only)</label>
                        <input type="password" name="new_password" id="edit-user-password" class="form-control" placeholder="Leave blank to keep current">
                        <div class="form-text" style="font-size: 11px;">Min 8 characters if changing.</div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light btn-slim" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark btn-slim">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Edit User Modal Logic
    const editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
    document.querySelectorAll('.edit-user-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('edit-user-id').value = btn.dataset.id;
            document.getElementById('edit-user-type').value = btn.dataset.type;
            document.getElementById('edit-user-name').value = btn.dataset.name;
            document.getElementById('edit-user-email').value = btn.dataset.email;
            
            // Only show password edit field if target is admin
            if (btn.dataset.type === 'admin') {
                document.getElementById('password-edit-container').style.display = 'block';
            } else {
                document.getElementById('password-edit-container').style.display = 'none';
                document.getElementById('edit-user-password').value = '';
            }
            
            editModal.show();
        });
    });

    // Charts Initialization
    <?php if ($tab === 'dashboard'): ?>
    const ctxRevenue = document.getElementById('revenueChart').getContext('2d');
    const gradient = ctxRevenue.createLinearGradient(0, 0, 0, 260);
    gradient.addColorStop(0, 'rgba(30, 30, 30, 0.12)');
    gradient.addColorStop(1, 'rgba(30, 30, 30, 0)');
    new Chart(ctxRevenue, {
        type: 'line',
        data: {
            labels: <?= json_encode($revenueChartLabels) ?>,
            datasets: [{
                label: 'Revenue',
                data: <?= json_encode($revenueChartValues) ?>,
                borderColor: '#1a1a1a',
                backgroundColor: gradient,
                tension: 0.45,
                fill: true,
                pointRadius: 5,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#1a1a1a',
                pointBorderWidth: 2,
                pointHoverRadius: 7,
                pointHoverBackgroundColor: '#1a1a1a',
                borderWidth: 2.5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1a1a1a',
                    titleFont: { size: 12, weight: '600' },
                    bodyFont: { size: 12 },
                    padding: 10,
                    cornerRadius: 8,
                    displayColors: false,
                    callbacks: {
                        label: ctx => '₱' + ctx.parsed.y.toLocaleString()
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f3f5', drawBorder: false },
                    ticks: { font: { size: 11, weight: '500' }, color: '#aaa', callback: v => '₱' + (v >= 1000 ? (v/1000) + 'k' : v) },
                    border: { display: false }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11, weight: '500' }, color: '#aaa' },
                    border: { display: false }
                }
            }
        }
    });
    <?php endif; ?>
</script>
</body>
</html>
