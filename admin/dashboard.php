<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/helpers.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
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
    <title>Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');
        body { background: #f8f9fa; font-family: 'Inter', sans-serif; color: #12141a; margin: 0; font-size: 14px; line-height: 1.45; height: 100vh; overflow: hidden; }
        .layout { height: 100vh; display: flex; width: 100%; background: #f8f9fb; overflow: hidden; }
        .sidebar { width: 210px; min-width: 210px; background: #13151a; color: #c6ccda; padding: 14px 12px; display: flex; flex-direction: column; height: 100vh; overflow-y: auto; flex-shrink: 0; }
        .brand { color: #fff; font-weight: 700; font-size: 15px; border-bottom: 1px solid #23262f; padding: 10px 8px 14px; margin-bottom: 14px; }
        .brand small { display: block; color: #7c8392; font-size: 11px; font-weight: 500; }
        .menu-label { color: #616877; font-size: 11px; margin: 0 8px 8px; text-transform: uppercase; letter-spacing: .08em; font-weight: 700; }
        .nav-link-custom { color: #adb4c2; text-decoration: none; font-size: 14px; border-radius: 10px; padding: 10px 12px; margin-bottom: 4px; display: flex; gap: 10px; align-items: center; font-weight: 500; }
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
        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .table-wrap { border: 1px solid #e7eaf0; border-radius: 8px; overflow: hidden; background: #fff; width: 100%; }
        .table thead th { background: #f9fafc; font-size: 11px; color: #9aa1ae; letter-spacing: .08em; text-transform: uppercase; padding: 9px 10px; border-bottom: 1px solid #eceff4; font-weight: 700; white-space: nowrap; }
        .table td { font-size: 13px; color: #4a5160; padding: 10px; vertical-align: middle; white-space: nowrap; }
        .status-pill { font-size: 11px; border-radius: 6px; border: 1px solid #d5d8df; padding: 2px 8px; display: inline-block; background: #fff; }
        .status-delivered, .status-active, .status-completed, .status-in-stock { border-color: #111; color: #111; }
        .status-suspended { background: #fff8ef; border-color: #f0c084; color: #7a4a00; }
        .status-banned { background: #fff3f4; border-color: #df9ca0; color: #8a1c1c; }
        .status-pending, .status-pending-approval, .status-inactive, .status-cancelled, .status-out-of-stock { background: #f3f4f7; color: #9aa2af; border-color: #e0e3e8; }
        .status-processing, .status-low-stock { background: #f7f8fa; color: #8e96a4; border-color: #dde1e7; }
        .footer { display: flex; justify-content: space-between; align-items: center; border: 1px solid #e7eaf0; border-top: 0; border-radius: 0 0 8px 8px; color: #a0a7b4; font-size: 12px; padding: 10px 12px; }
        .mini-btn { border: 1px solid #e5e8ee; color: #a2a8b5; background: #fff; border-radius: 6px; padding: 2px 8px; font-size: 12px; }
        .mini-page { border-radius: 5px; border: 1px solid #0f1115; background: #0f1115; color: #fff; padding: 1px 8px; font-size: 11px; margin: 0 5px; }
        .btn-slim { font-size: 12px; border-radius: 8px; padding: 6px 11px; font-weight: 600; }
        .form-control, .form-select { min-height: 36px; border-color: #e4e7ee; border-radius: 8px; font-size: 13px; }
        .detail-card { border: 1px solid #e7eaf0; border-radius: 10px; padding: 14px; min-height: 175px; }
        .detail-title { color: #9ba2b0; font-size: 10px; text-transform: uppercase; letter-spacing: .1em; font-weight: 700; margin-bottom: 12px; }
        .meta { display: flex; gap: 10px; margin-bottom: 10px; }
        .meta-icon { width: 27px; height: 27px; border: 1px solid #e5e8ee; border-radius: 7px; display: inline-flex; align-items: center; justify-content: center; color: #9aa1ae; font-size: 12px; }
        .meta-k { color: #8f97a4; font-size: 10px; line-height: 1; margin-bottom: 2px; }
        .meta-v { font-size: 13px; color: #141820; font-weight: 600; }
        .profile-avatar-wrap { width: 140px; height: 140px; border-radius: 14px; border: 1px solid #e7eaf0; overflow: hidden; background: #f3f4f7; flex-shrink: 0; }
        .profile-avatar-wrap img { width: 100%; height: 100%; object-fit: cover; }

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
            <i class="bi bi-journal-bookmark-fill" style="font-size: 14px;"></i>
        </div>
        <div>
            <span class="fw-bold" style="font-size:14px;">School Supply</span>
            <span class="d-block" style="font-size:10px;color:#7c8392;margin-top:-2px;">Admin Panel</span>
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
                <?php
                $pageTitle = 'Dashboard';
                $pageSubtitle = 'System overview and key metrics';
                $headerRightHtml = '';
                include __DIR__ . '/views/header.php';
                ?>
                <div class="inner">
                    <div class="row g-3 mb-3">
                        <div class="col-md-3"><div class="stat-box"><div class="label">Total Users</div><div class="value"><?= $countUsers ?></div></div></div>
                        <div class="col-md-3"><div class="stat-box"><div class="label">Total Products</div><div class="value"><?= $totalProducts ?></div></div></div>
                        <div class="col-md-3"><div class="stat-box"><div class="label">Total Orders</div><div class="value"><?= $totalOrders ?></div></div></div>
                        <div class="col-md-3"><div class="stat-box"><div class="label">Total Revenue</div><div class="value">₱<?= number_format($totalRevenue, 0) ?></div></div></div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6"><div class="stat-box"><div class="label">Sellers</div><div class="value"><?= $countSellers ?></div></div></div>
                        <div class="col-md-6"><div class="stat-box"><div class="label">Customers</div><div class="value"><?= $countCustomers ?></div></div></div>
                    </div>

                    <div class="table-wrap">
                        <div class="table-responsive">
                            <table class="table mb-0">
                                <thead><tr><th colspan="7" style="text-transform:none;letter-spacing:0;font-size:13px;color:#202430;">Recent Orders<span style="float:right;color:#9ba2b0;font-size:12px;">Latest transactions</span></th></tr></thead>
                                <thead><tr><th>Order ID</th><th>Customer</th><th>Role</th><th>Items</th><th>Total</th><th>Status</th></tr></thead>
                                <tbody>
                                <?php foreach ($dashboardOrdersPageRows as $o): ?>
                                    <tr>
                                        <td><?= esc($o['id']) ?></td><td><strong><?= esc($o['customer']) ?></strong></td><td>Customer</td><td><?= esc($o['items']) ?></td><td><strong>₱<?= number_format($o['total'], 2) ?></strong></td>
                                        <td><span class="status-pill status-<?= strtolower(str_replace(' ', '-', $o['status'])) ?>"><?= esc($o['status']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="footer">
                        <span>Showing <?= $dashboardOrdersPager['from'] ?>-<?= $dashboardOrdersPager['to'] ?> of <?= $dashboardOrdersPager['total'] ?> orders</span>
                        <span>
                            <?php if ($dashboardOrdersPager['current'] > 1): ?>
                                <a class="mini-btn text-decoration-none" href="<?= esc(pageUrl(['dashboard_orders_page' => $dashboardOrdersPager['current'] - 1])) ?>">Previous</a>
                            <?php else: ?>
                                <button class="mini-btn" disabled>Previous</button>
                            <?php endif; ?>
                            <span class="mini-page"><?= $dashboardOrdersPager['current'] ?></span>
                            <?php if ($dashboardOrdersPager['current'] < $dashboardOrdersPager['last']): ?>
                                <a class="mini-btn text-decoration-none" href="<?= esc(pageUrl(['dashboard_orders_page' => $dashboardOrdersPager['current'] + 1])) ?>">Next</a>
                            <?php else: ?>
                                <button class="mini-btn" disabled>Next</button>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'profile'): ?>
                <div class="header d-flex justify-content-between align-items-start">
                    <div>
                        <h1 class="page-title">Profile</h1>
                        <div class="subtitle">Update your name, email, and profile picture</div>
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
                            'email_taken' => 'That email is already in use by another admin account.',
                            'save_failed' => 'Could not save changes. Please try again.',
                            default => 'Something went wrong. Please try again.',
                        };
                        ?>
                        <div class="alert alert-danger py-2 rounded-xl mb-3" style="font-size: 13px;"><?= esc($pmsg) ?></div>
                    <?php endif; ?>
                    <form method="post" action="actions/update_admin_profile.php" enctype="multipart/form-data" class="row g-4 align-items-start">
                        <div class="col-md-4 col-lg-3">
                            <div class="detail-card h-100">
                                <div class="detail-title mb-3">Profile photo</div>
                                <div class="d-flex justify-content-center mb-3">
                                    <div class="profile-avatar-wrap">
                                        <?php if (!empty($adminProfileImageUrl)): ?>
                                            <img src="<?= esc($adminProfileImageUrl) ?>" alt="">
                                        <?php else: ?>
                                            <div class="d-flex align-items-center justify-content-center h-100 text-secondary"><i class="bi bi-person" style="font-size: 3rem;"></i></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <label class="form-label text-secondary small fw-medium mb-1">Upload image</label>
                                <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
                                <div class="text-muted small mt-2" style="font-size: 11px;">JPEG, PNG, WebP, or GIF. Max 2&nbsp;MB. Leave empty to keep the current photo.</div>
                            </div>
                        </div>
                        <div class="col-md-8 col-lg-9">
                            <div class="detail-card">
                                <div class="detail-title mb-3">Account details</div>
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
                                <div class="mt-4 d-flex flex-wrap gap-2 justify-content-end">
                                    <a href="dashboard.php?tab=dashboard" class="btn btn-outline-secondary btn-slim">Cancel</a>
                                    <button type="submit" class="btn btn-outline-dark btn-slim"><i class="bi bi-save"></i> Save changes</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'users'): ?>
                <?php if (isset($_GET['success']) && $_GET['success'] === 'user_updated'): ?>
                    <div class="inner pb-0"><div class="alert alert-success py-2 mb-0">User details updated successfully.</div></div>
                <?php endif; ?>
                <?php if (isset($_GET['success']) && $_GET['success'] === 'user_deleted'): ?>
                    <div class="inner pb-0"><div class="alert alert-success py-2 mb-0">User deleted successfully.</div></div>
                <?php endif; ?>

                <?php if (isset($_GET['error']) && $_GET['error'] === 'invalid_edit'): ?>
                    <div class="inner pb-0"><div class="alert alert-danger py-2 mb-0">Please provide valid name and email.</div></div>
                <?php endif; ?>
                <?php if (isset($_GET['error']) && $_GET['error'] === 'email_taken'): ?>
                    <div class="inner pb-0"><div class="alert alert-danger py-2 mb-0">Email is already used by another account.</div></div>
                <?php endif; ?>
                <?php if (isset($_GET['error']) && $_GET['error'] === 'delete_failed'): ?>
                    <div class="inner pb-0"><div class="alert alert-warning py-2 mb-0">Cannot delete this user (might have related records).</div></div>
                <?php endif; ?>
                <?php if ($selectedUser): ?>
                    <div class="header d-flex justify-content-between align-items-start">
                        <div><h1 class="page-title">User Detail</h1><div class="subtitle">Full profile and activity</div></div>
                        <div class="d-flex gap-2 align-items-center">
                            <a href="dashboard.php?tab=users" class="btn btn-outline-dark btn-slim"><i class="bi bi-arrow-left"></i> Back to Users</a>
                            <span class="status-pill"><?= esc($selectedUser['role']) ?></span>
                        </div>
                    </div>
                    <div class="inner">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6"><div class="detail-card">
                                <div class="detail-title">Account Information</div>
                                <div class="meta"><span class="meta-icon"><i class="bi bi-person"></i></span><div><div class="meta-k">Full Name</div><div class="meta-v"><?= esc($selectedUser['name']) ?></div></div></div>
                                <div class="meta"><span class="meta-icon"><i class="bi bi-envelope"></i></span><div><div class="meta-k">Email Address</div><div class="meta-v"><?= esc($selectedUser['email']) ?></div></div></div>
                                <div class="meta"><span class="meta-icon"><i class="bi bi-shield"></i></span><div><div class="meta-k">Role</div><div class="meta-v"><?= esc($selectedUser['role']) ?></div></div></div>
                                <div class="meta mb-0"><span class="meta-icon"><i class="bi bi-calendar3"></i></span><div><div class="meta-k">Date Joined</div><div class="meta-v"><?= esc($selectedUser['joined']) ?></div></div></div>
                            </div></div>
                            <div class="col-md-6"><div class="detail-card">
                                <div class="detail-title">Activity Summary</div>
                                <div class="row g-2">
                                    <div class="col-6"><div class="stat-box"><div class="label"><?= $selectedUser['role'] === 'Seller' ? 'Products Listed' : 'Orders Placed' ?></div><div class="value"><?= (int) preg_replace('/\D+/', '', $selectedUser['activity']) ?></div></div></div>
                                    <div class="col-6"><div class="stat-box"><div class="label">Orders Fulfilled</div><div class="value"><?= $selectedUser['role'] === 'Seller' ? max(0, (int) preg_replace('/\D+/', '', $selectedUser['activity']) - 1) : 0 ?></div></div></div>
                                    <div class="col-6"><div class="stat-box"><div class="label">Total Revenue</div><div class="value">₱<?= number_format($totalRevenue, 0) ?></div></div></div>
                                    <div class="col-6"><div class="stat-box"><div class="label">Account Status</div><div class="value" style="font-size:30px;"><?= esc($selectedUser['status']) ?></div></div></div>
                                </div>
                            </div></div>
                        </div>
                        <div class="table-wrap mb-3">
                            <div class="table-responsive">
                                <table class="table mb-0">
                                    <thead><tr><th colspan="7"><?= esc($selectedUser['type'] === 'seller' ? 'Listed Products' : 'Recently Ordered Products') ?></th></tr></thead>
                                    <thead><tr><th>#</th><th>Product Name</th><th>SKU</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th></tr></thead>
                                    <tbody>
                                    <?php if (count($userDetailProducts) === 0): ?>
                                        <tr><td colspan="7" class="text-center py-4 text-muted">No products found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($userDetailPageRows as $idx => $p): ?>
                                            <tr><td><?= $userDetailPager['from'] + $idx ?></td><td><strong><?= esc($p['name']) ?></strong></td><td><?= esc($p['sku']) ?></td><td><?= esc($p['category']) ?></td><td><strong>₱<?= number_format($p['price'], 2) ?></strong></td><td><?= $p['stock'] ?></td><td><span class="status-pill status-<?= strtolower(str_replace(' ', '-', $p['status'])) ?>"><?= esc($p['status']) ?></span></td></tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php if ($userDetailPager['total'] > 0): ?>
                            <div class="footer mb-3">
                                <span>Showing <?= $userDetailPager['from'] ?>-<?= $userDetailPager['to'] ?> of <?= $userDetailPager['total'] ?> records</span>
                                <span>
                                    <?php if ($userDetailPager['current'] > 1): ?>
                                        <a class="mini-btn text-decoration-none" href="<?= esc(pageUrl(['user_items_page' => $userDetailPager['current'] - 1])) ?>">Previous</a>
                                    <?php else: ?>
                                        <button class="mini-btn" disabled>Previous</button>
                                    <?php endif; ?>
                                    <span class="mini-page"><?= $userDetailPager['current'] ?></span>
                                    <?php if ($userDetailPager['current'] < $userDetailPager['last']): ?>
                                        <a class="mini-btn text-decoration-none" href="<?= esc(pageUrl(['user_items_page' => $userDetailPager['current'] + 1])) ?>">Next</a>
                                    <?php else: ?>
                                        <button class="mini-btn" disabled>Next</button>
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-end gap-2">
                            <a href="dashboard.php?tab=users" class="btn btn-outline-dark btn-slim"><i class="bi bi-arrow-left"></i> Back to Users</a>
                            <button type="button" class="btn btn-outline-secondary btn-slim" data-bs-toggle="collapse" data-bs-target="#editUserCard"><i class="bi bi-pencil-square"></i> Edit</button>
                            <form method="post" action="actions/delete_user.php" class="d-inline" onsubmit="return confirm('Delete this user account? This cannot be undone.');">
                                <input type="hidden" name="target_type" value="<?= esc($selectedUser['type'] ?? 'customer') ?>">
                                <input type="hidden" name="target_id" value="<?= (int) ($selectedUser['id'] ?? 0) ?>">
                                <input type="hidden" name="redirect" value="../dashboard.php?tab=users">
                                <button type="submit" class="btn btn-outline-danger btn-slim"><i class="bi bi-trash"></i> Delete</button>
                            </form>
                            <?php
                            $approvalState = (string) ($selectedUser['approval_status'] ?? 'Approved');
                            $currentStatus = $selectedUser['status'] ?? 'Active';
                            $actionLabel = 'Suspend Account';
                            $actionValue = 'suspend_user';
                            $actionIcon = 'bi bi-slash-circle';

                            if ($currentStatus === 'Banned') {
                                $actionLabel = 'Unban Account';
                                $actionValue = 'unban_user';
                                $actionIcon = 'bi bi-arrow-counterclockwise';
                            } elseif ($currentStatus === 'Suspended') {
                                $actionLabel = 'Activate Account';
                                $actionValue = 'activate_user';
                                $actionIcon = 'bi bi-check-circle';
                            }
                            ?>
                            <?php if (strcasecmp($approvalState, 'Approved') !== 0): ?>
                                <form method="post" action="actions/update_user_status.php" class="d-inline">
                                    <input type="hidden" name="admin_action" value="approve_user">
                                    <input type="hidden" name="target_type" value="<?= esc($selectedUser['type'] ?? 'customer') ?>">
                                    <input type="hidden" name="target_id" value="<?= (int) ($selectedUser['id'] ?? 0) ?>">
                                    <input type="hidden" name="redirect" value="../dashboard.php?tab=users&type=<?= esc($selectedUser['type'] ?? 'customer') ?>&user=<?= (int) ($selectedUser['id'] ?? 0) ?>">
                                    <button type="submit" class="btn btn-outline-dark btn-slim"><i class="bi bi-check2-circle"></i> Approve Account</button>
                                </form>
                            <?php endif; ?>
                            <?php if (strcasecmp($approvalState, 'Approved') === 0): ?>
                                <form method="post" action="actions/update_user_status.php" class="d-inline">
                                    <input type="hidden" name="admin_action" value="<?= esc($actionValue) ?>">
                                    <input type="hidden" name="target_type" value="<?= esc($selectedUser['type'] ?? 'customer') ?>">
                                    <input type="hidden" name="target_id" value="<?= (int) ($selectedUser['id'] ?? 0) ?>">
                                    <input type="hidden" name="redirect" value="../dashboard.php?tab=users&type=<?= esc($selectedUser['type'] ?? 'customer') ?>&user=<?= (int) ($selectedUser['id'] ?? 0) ?>">
                                    <button type="submit" class="btn btn-outline-secondary btn-slim"><i class="<?= esc($actionIcon) ?>"></i> <?= esc($actionLabel) ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <div class="collapse mt-3" id="editUserCard">
                            <div class="detail-card">
                                <div class="detail-title">Edit User</div>
                                <form method="post" action="actions/update_user_profile.php" class="row g-2 align-items-end">
                                    <input type="hidden" name="target_type" value="<?= esc($selectedUser['type'] ?? 'customer') ?>">
                                    <input type="hidden" name="target_id" value="<?= (int) ($selectedUser['id'] ?? 0) ?>">
                                    <input type="hidden" name="redirect" value="../dashboard.php?tab=users&type=<?= esc($selectedUser['type'] ?? 'customer') ?>&user=<?= (int) ($selectedUser['id'] ?? 0) ?>">
                                    <div class="col-md-5">
                                        <label class="form-label small text-secondary mb-1">Name</label>
                                        <input type="text" name="name" class="form-control" required value="<?= esc($selectedUser['name'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label small text-secondary mb-1">Email</label>
                                        <input type="email" name="email" class="form-control" required value="<?= esc($selectedUser['email'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-outline-dark btn-slim w-100"><i class="bi bi-save"></i> Save</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="header d-flex justify-content-between align-items-start">
                        <div><h1 class="page-title">Manage Users</h1><div class="subtitle">All registered sellers and customers</div></div>
                        <div class="d-flex gap-2">
                            <form method="get" class="d-flex gap-2"><input type="hidden" name="tab" value="users"><select name="role" class="form-select"><option value="">Filter by Role</option><option value="Customer" <?= $roleFilter === 'Customer' ? 'selected' : '' ?>>Customer</option><option value="Seller" <?= $roleFilter === 'Seller' ? 'selected' : '' ?>>Seller</option></select><button class="btn btn-outline-dark btn-slim">Apply</button></form>

                        </div>
                    </div>
                    <div class="inner">
                        <div class="row g-3 mb-3">
                            <div class="col-md-4"><div class="stat-box"><div class="label">Total Users</div><div class="value"><?= $countUsers ?></div></div></div>
                            <div class="col-md-4"><div class="stat-box"><div class="label">Sellers</div><div class="value"><?= $countSellers ?></div></div></div>
                            <div class="col-md-4"><div class="stat-box"><div class="label">Customers</div><div class="value"><?= $countCustomers ?></div></div></div>
                        </div>
                        <div class="table-wrap">
                            <div class="table-responsive">
                                <table class="table mb-0">
                                    <thead><tr><th>User ID</th><th>Name</th><th>Email</th><th>Role</th><th>Joined</th><th>Activity</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($usersPageRows as $u): ?>
                                        <tr>
                                            <td><?= esc($u['uid']) ?></td><td><strong><?= esc($u['name']) ?></strong></td><td><?= esc($u['email']) ?></td><td><span class="status-pill"><?= esc($u['role']) ?></span></td><td><?= esc($u['joined']) ?></td><td><?= esc($u['activity']) ?></td><td><span class="status-pill status-<?= strtolower(str_replace(' ', '-', (string) $u['status'])) ?>"><?= esc($u['status']) ?></span></td>
                                            <td class="text-end text-nowrap">
                                                <a class="btn btn-sm btn-outline-secondary btn-slim" href="dashboard.php?tab=users&type=<?= esc($u['type']) ?>&user=<?= (int) $u['id'] ?>"><i class="bi bi-eye"></i> View</a>
                                                <a class="btn btn-sm btn-outline-secondary btn-slim ms-1" href="dashboard.php?tab=users&type=<?= esc($u['type']) ?>&user=<?= (int) $u['id'] ?>"><i class="bi bi-pencil-square"></i> Edit</a>
                                                <form method="post" action="actions/delete_user.php" class="d-inline ms-1" onsubmit="return confirm('Delete this user account? This cannot be undone.');">
                                                    <input type="hidden" name="target_type" value="<?= esc($u['type']) ?>">
                                                    <input type="hidden" name="target_id" value="<?= (int) $u['id'] ?>">
                                                    <input type="hidden" name="redirect" value="../dashboard.php?tab=users<?= $roleFilter !== '' ? '&role=' . urlencode($roleFilter) : '' ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger btn-slim"><i class="bi bi-trash"></i> Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="footer">
                            <span>Showing <?= $usersPager['from'] ?>-<?= $usersPager['to'] ?> of <?= $usersPager['total'] ?> users</span>
                            <span>
                                <?php if ($usersPager['current'] > 1): ?>
                                    <a class="mini-btn text-decoration-none" href="<?= esc(pageUrl(['users_page' => $usersPager['current'] - 1])) ?>">Previous</a>
                                <?php else: ?>
                                    <button class="mini-btn" disabled>Previous</button>
                                <?php endif; ?>
                                <span class="mini-page"><?= $usersPager['current'] ?></span>
                                <?php if ($usersPager['current'] < $usersPager['last']): ?>
                                    <a class="mini-btn text-decoration-none" href="<?= esc(pageUrl(['users_page' => $usersPager['current'] + 1])) ?>">Next</a>
                                <?php else: ?>
                                    <button class="mini-btn" disabled>Next</button>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($tab === 'approvals'): ?>
                <div class="header d-flex justify-content-between align-items-start">
                    <div><h1 class="page-title">Approvals</h1><div class="subtitle">Pending seller and customer account approvals</div></div>
                    <span class="status-pill status-pending-approval"><?= (int) $pendingApprovalCount ?> pending</span>
                </div>
                <div class="inner">
                    <div class="table-wrap">
                        <div class="table-responsive">
                            <table class="table mb-0">
                                <thead><tr><th>User ID</th><th>Name</th><th>Email</th><th>Role</th><th>Joined</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                                <tbody>
                                <?php if (count($pendingApprovals) === 0): ?>
                                    <tr><td colspan="7" class="text-center py-4 text-muted">No pending approvals.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($pendingApprovalsPageRows as $u): ?>
                                    <tr>
                                        <td><?= esc($u['uid']) ?></td>
                                        <td><strong><?= esc($u['name']) ?></strong></td>
                                        <td><?= esc($u['email']) ?></td>
                                        <td><span class="status-pill"><?= esc($u['role']) ?></span></td>
                                        <td><?= esc($u['joined']) ?></td>
                                        <td><span class="status-pill status-pending-approval">Pending Approval</span></td>
                                        <td class="text-end">
                                            <form method="post" action="actions/update_user_status.php" class="d-inline">
                                                <input type="hidden" name="admin_action" value="approve_user">
                                                <input type="hidden" name="target_type" value="<?= esc($u['type']) ?>">
                                                <input type="hidden" name="target_id" value="<?= (int) $u['id'] ?>">
                                                <input type="hidden" name="redirect" value="../dashboard.php?tab=approvals">
                                                <button type="submit" class="btn btn-sm btn-outline-dark btn-slim"><i class="bi bi-check2-circle"></i> Approve</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php if ($approvalsPager['total'] > 0): ?>
                        <div class="footer mt-2">
                            <span>Showing <?= $approvalsPager['from'] ?>-<?= $approvalsPager['to'] ?> of <?= $approvalsPager['total'] ?> pending accounts</span>
                            <span>
                                <?php if ($approvalsPager['current'] > 1): ?>
                                    <a class="mini-btn text-decoration-none" href="<?= esc(pageUrl(['approvals_page' => $approvalsPager['current'] - 1])) ?>">Previous</a>
                                <?php else: ?>
                                    <button class="mini-btn" disabled>Previous</button>
                                <?php endif; ?>
                                <span class="mini-page"><?= $approvalsPager['current'] ?></span>
                                <?php if ($approvalsPager['current'] < $approvalsPager['last']): ?>
                                    <a class="mini-btn text-decoration-none" href="<?= esc(pageUrl(['approvals_page' => $approvalsPager['current'] + 1])) ?>">Next</a>
                                <?php else: ?>
                                    <button class="mini-btn" disabled>Next</button>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'products'): ?>
                <div class="header d-flex justify-content-between align-items-start">
                    <div><h1 class="page-title">Products</h1><div class="subtitle">All products across all sellers</div></div>
                    <form method="get" class="d-flex gap-2">
                        <input type="hidden" name="tab" value="products">
                        <input type="text" class="form-control" name="search" placeholder="Search">
                        <select name="category" class="form-select"><option value="">Filter by Category</option><option>Paper</option><option>Writing</option><option>Supply</option></select>
                        <button class="btn btn-outline-dark btn-slim">Apply</button>
                    </form>
                </div>
                <div class="inner">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4"><div class="stat-box"><div class="label">Total Products</div><div class="value"><?= $totalProducts ?></div></div></div>
                        <div class="col-md-4"><div class="stat-box"><div class="label">In Stock</div><div class="value"><?= $inStock ?></div></div></div>
                        <div class="col-md-4"><div class="stat-box"><div class="label">Low / Out of Stock</div><div class="value"><?= $lowOutStock ?></div></div></div>
                    </div>
                    <div class="table-wrap">
                        <table class="table mb-0">
                            <thead><tr><th>#</th><th>Product Name</th><th>SKU</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Seller</th><th class="text-end">Action</th></tr></thead>
                            <tbody>
                            <?php foreach ($productsPageRows as $idx => $p): ?>
                                <tr>
                                    <td><?= $productsPager['from'] + $idx ?></td><td><strong><?= esc($p['name']) ?></strong></td><td><?= esc($p['sku']) ?></td><td><?= esc($p['category']) ?></td><td><strong>₱<?= number_format($p['price'], 2) ?></strong></td><td><?= $p['stock'] ?></td>
                                    <td><span class="status-pill status-<?= strtolower(str_replace(' ', '-', $p['status'])) ?>"><?= esc($p['status']) ?></span></td><td><?= esc($p['seller']) ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-secondary btn-slim view-product-btn" 
                                                data-name="<?= esc($p['name']) ?>"
                                                data-sku="<?= esc($p['sku']) ?>"
                                                data-category="<?= esc($p['category']) ?>"
                                                data-price="₱<?= number_format($p['price'], 2) ?>"
                                                data-stock="<?= $p['stock'] ?>"
                                                data-status="<?= esc($p['status']) ?>"
                                                data-seller="<?= esc($p['seller']) ?>">
                                            <i class="bi bi-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="footer">
                        <span>Showing <?= $productsPager['from'] ?>-<?= $productsPager['to'] ?> of <?= $productsPager['total'] ?> products</span>
                        <span>
                            <?php if ($productsPager['current'] > 1): ?>
                                <a class="mini-btn text-decoration-none" href="<?= esc(pageUrl(['products_page' => $productsPager['current'] - 1])) ?>">Previous</a>
                            <?php else: ?>
                                <button class="mini-btn" disabled>Previous</button>
                            <?php endif; ?>
                            <span class="mini-page"><?= $productsPager['current'] ?></span>
                            <?php if ($productsPager['current'] < $productsPager['last']): ?>
                                <a class="mini-btn text-decoration-none" href="<?= esc(pageUrl(['products_page' => $productsPager['current'] + 1])) ?>">Next</a>
                            <?php else: ?>
                                <button class="mini-btn" disabled>Next</button>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'orders'): ?>
                <?php if ($selectedOrder): ?>
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
                                    <div><div class="meta-label">Payment Method</div><div class="meta-value">Cash</div></div>
                                </div>
                                <div class="meta-row mb-0">
                                    <span class="meta-icon"><i class="bi bi-bag-check"></i></span>
                                    <div><div class="meta-label">Order Status</div><div class="meta-value"><?= esc($selectedOrder['status']) ?></div></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-wrap mb-4">
                        <table class="table mb-0">
                            <thead>
                            <tr>
                                <th>Item Description</th>
                                <th>Seller</th>
                                <th>Category</th>
                                <th>Qty</th>
                                <th>Unit Price</th>
                                <th class="text-end">Total</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($orderItemsPageRows as $item): ?>
                                <tr>
                                    <td><strong><?= esc($item['name']) ?></strong></td>
                                    <td><?= esc($item['seller_name']) ?></td>
                                    <td><?= esc(ucfirst(strtolower($item['category']))) ?></td>
                                    <td><?= (int) $item['quantity'] ?></td>
                                    <td>₱<?= number_format((float) $item['price'], 2) ?></td>
                                    <td class="text-end fw-semibold">₱<?= number_format((float) $item['subtotal'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (count($orderItemsPageRows) === 0): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No items found for this order.</td></tr>
                            <?php endif; ?>
                            <tr style="background: #f8f9fa;">
                                <td colspan="5" class="text-end fw-bold">Grand Total</td>
                                <td class="text-end fw-bold" style="font-size: 16px;">₱<?= number_format((float) $selectedOrder['total_amount'], 2) ?></td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="footer">
                        <span>Showing <?= $orderItemsPager['from'] ?>-<?= $orderItemsPager['to'] ?> of <?= $orderItemsPager['total'] ?> items</span>
                        <span>
                            <?php if ($orderItemsPager['current'] > 1): ?>
                                <a class="mini-btn text-decoration-none" href="<?= esc(pageUrl(['order_items_page' => $orderItemsPager['current'] - 1])) ?>">Previous</a>
                            <?php else: ?>
                                <button class="mini-btn" disabled>Previous</button>
                            <?php endif; ?>
                            <span class="mini-page"><?= $orderItemsPager['current'] ?></span>
                            <?php if ($orderItemsPager['current'] < $orderItemsPager['last']): ?>
                                <a class="mini-btn text-decoration-none" href="<?= esc(pageUrl(['order_items_page' => $orderItemsPager['current'] + 1])) ?>">Next</a>
                            <?php else: ?>
                                <button class="mini-btn" disabled>Next</button>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php else: ?>
                    <div class="header d-flex justify-content-between align-items-start">
                        <div><h1 class="page-title">All Orders</h1><div class="subtitle">System-wide order management</div></div>
                        <form method="get" class="d-flex gap-2">
                            <input type="hidden" name="tab" value="orders">
                            <input type="text" class="form-control" name="search" placeholder="Search order/customer" value="<?= esc($search) ?>">
                            <select name="status" class="form-select"><option value="">Filter by Status</option><option value="Delivered">Delivered</option><option value="Processing">Processing</option><option value="Pending">Pending</option><option value="Cancelled">Cancelled</option></select>
                            <button class="btn btn-outline-dark btn-slim">Apply</button>
                        </form>
                    </div>
                    <div class="inner">
                        <div class="row g-3 mb-3">
                            <div class="col-md-3"><div class="stat-box"><div class="label">Total Orders</div><div class="value"><?= $totalOrders ?></div></div></div>
                            <div class="col-md-3"><div class="stat-box"><div class="label">Delivered</div><div class="value"><?= $deliveredCount ?></div></div></div>
                            <div class="col-md-3"><div class="stat-box"><div class="label">Processing</div><div class="value"><?= $processingCount ?></div></div></div>
                            <div class="col-md-3"><div class="stat-box"><div class="label">Pending</div><div class="value"><?= $pendingCount ?></div></div></div>
                        </div>
                        <div class="table-wrap">
                            <table class="table mb-0">
                                <thead><tr><th>Order ID</th><th>Customer</th><th>Date</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                                <tbody>
                                <?php foreach ($ordersPageRows as $o): ?>
                                    <tr>
                                        <td><?= esc($o['id']) ?></td><td><strong><?= esc($o['customer']) ?></strong></td><td><?= esc($o['date']) ?></td><td><?= esc($o['items']) ?></td><td><strong>₱<?= number_format($o['total'], 2) ?></strong></td><td><?= esc($o['payment']) ?></td>
                                        <td><span class="status-pill status-<?= strtolower(str_replace(' ', '-', $o['status'])) ?>"><?= esc($o['status']) ?></span></td><td class="text-end"><a href="dashboard.php?tab=orders&view=<?= urlencode($o['id']) ?>" class="btn btn-sm btn-outline-secondary btn-slim"><i class="bi bi-eye"></i> View</a></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="footer">
                            <span>Showing <?= $ordersPager['from'] ?>-<?= $ordersPager['to'] ?> of <?= $ordersPager['total'] ?> orders</span>
                            <span>
                                <?php if ($ordersPager['current'] > 1): ?>
                                    <a class="mini-btn text-decoration-none" href="<?= esc(pageUrl(['orders_page' => $ordersPager['current'] - 1])) ?>">Previous</a>
                                <?php else: ?>
                                    <button class="mini-btn" disabled>Previous</button>
                                <?php endif; ?>
                                <span class="mini-page"><?= $ordersPager['current'] ?></span>
                                <?php if ($ordersPager['current'] < $ordersPager['last']): ?>
                                    <a class="mini-btn text-decoration-none" href="<?= esc(pageUrl(['orders_page' => $ordersPager['current'] + 1])) ?>">Next</a>
                                <?php else: ?>
                                    <button class="mini-btn" disabled>Next</button>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($tab === 'reports'): ?>
                <?php
                $pageTitle = 'Reports';
                $pageSubtitle = 'Revenue and performance overview';
                $headerRightHtml = '<a href="actions/export_reports.php" class="btn btn-outline-dark btn-slim"><i class="bi bi-download"></i> Export CSV</a>';
                include __DIR__ . '/views/header.php';
                ?>
                <div class="inner">
                    <div class="row g-3 mb-3">
                        <div class="col-md-3"><div class="stat-box"><div class="label">Total Revenue</div><div class="value">₱<?= number_format($totalRevenue, 0) ?></div></div></div>
                        <div class="col-md-3"><div class="stat-box"><div class="label">Total Transactions</div><div class="value"><?= count($reportRows) ?></div></div></div>
                        <div class="col-md-3"><div class="stat-box"><div class="label">Completed</div><div class="value"><?= $completed ?></div></div></div>
                        <div class="col-md-3"><div class="stat-box"><div class="label">Cancelled</div><div class="value"><?= $cancelled ?></div></div></div>
                    </div>
                    <div class="table-wrap">
                        <table class="table mb-0">
                            <thead><tr><th>Date</th><th>Product</th><th>Category</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Status</th><th>Seller</th></tr></thead>
                            <tbody>
                            <?php foreach ($reportPageRows as $r): ?>
                                <tr>
                                    <td><?= esc($r['date']) ?></td><td><strong><?= esc($r['product']) ?></strong></td><td><?= esc($r['category']) ?></td><td><?= $r['qty'] ?></td><td>₱<?= number_format($r['unit_price'], 2) ?></td><td><strong>₱<?= number_format($r['total'], 2) ?></strong></td>
                                    <td><span class="status-pill status-<?= strtolower(str_replace(' ', '-', $r['status'])) ?>"><?= esc($r['status']) ?></span></td><td><?= esc($r['seller']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="footer">
                        <span>Showing <?= $reportsPager['from'] ?>-<?= $reportsPager['to'] ?> of <?= $reportsPager['total'] ?> transactions</span>
                        <span>
                            <?php if ($reportsPager['current'] > 1): ?>
                                <a class="mini-btn text-decoration-none" href="<?= esc(pageUrl(['reports_page' => $reportsPager['current'] - 1])) ?>">Previous</a>
                            <?php else: ?>
                                <button class="mini-btn" disabled>Previous</button>
                            <?php endif; ?>
                            <span class="mini-page"><?= $reportsPager['current'] ?></span>
                            <?php if ($reportsPager['current'] < $reportsPager['last']): ?>
                                <a class="mini-btn text-decoration-none" href="<?= esc(pageUrl(['reports_page' => $reportsPager['current'] + 1])) ?>">Next</a>
                            <?php else: ?>
                                <button class="mini-btn" disabled>Next</button>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>



        </section>
    </main>
</div>

<!-- ===== About Developer Modal ===== -->
<div class="modal fade" id="aboutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0" style="border-radius: 16px; overflow: hidden;">
            <!-- Header Banner -->
            <div style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%); padding: 36px 32px 28px; position: relative;">
                <button type="button" class="btn-close btn-close-white position-absolute" data-bs-dismiss="modal" style="top: 16px; right: 16px;"></button>
                <div class="d-flex align-items-center gap-4">
                    <!-- Profile Photo -->
                    <div style="width:80px;height:80px;border-radius:50%;overflow:hidden;border:3px solid rgba(255,255,255,0.25);flex-shrink:0;">
                        <img src="../profile/877cab6f-57fc-400d-9b71-f79888c46b59.jpg" alt="Algen Espinosa" style="width:100%;height:100%;object-fit:cover;">
                    </div>
                    <div>
                        <h4 class="mb-1 fw-bold" style="color:#fff;font-size:22px;letter-spacing:-0.3px;">Algen Vicente Espinosa</h4>
                        <span class="badge" style="background:rgba(255,255,255,0.15);color:#94a3b8;font-size:11px;letter-spacing:1px;font-weight:600;padding:4px 10px;">WEB DEVELOPER</span>
                        <div class="d-flex flex-wrap gap-3 mt-3">
                            <span style="color:#94a3b8;font-size:12px;"><i class="bi bi-geo-alt-fill me-1" style="color:#64748b;"></i>Tangub City, Misamis Occidental</span>
                            <span style="color:#94a3b8;font-size:12px;"><i class="bi bi-mortarboard-fill me-1" style="color:#64748b;"></i>NMSCST</span>
                        </div>
                        <div class="d-flex flex-wrap gap-3 mt-1">
                            <a href="mailto:algen.espinosa@nmscst.edu.ph" style="color:#60a5fa;font-size:12px;text-decoration:none;"><i class="bi bi-envelope-fill me-1"></i>algen.espinosa@nmscst.edu.ph</a>
                            <a href="https://github.com/algenespinosa-droid" target="_blank" style="color:#60a5fa;font-size:12px;text-decoration:none;"><i class="bi bi-github me-1"></i>github.com/algenespinosa-droid</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-body p-4" style="background:#f8fafc;">
                <div class="row g-3">
                    <!-- About -->
                    <div class="col-12">
                        <div class="bg-white rounded-3 p-4 border" style="border-color:#e2e8f0!important;">
                            <h6 class="fw-bold mb-3 d-flex align-items-center gap-2" style="font-size:13px;color:#0f172a;">
                                <span style="width:28px;height:28px;background:#f1f5f9;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;">
                                    <i class="bi bi-person-lines-fill" style="font-size:13px;color:#475569;"></i>
                                </span>
                                About the Developer
                            </h6>
                            <p class="mb-0 text-secondary" style="font-size:13px;line-height:1.7;">
                                I am a <strong>22 years-old Information Technology student</strong> at Northwestern Mindanao State College of Science and Technology with a strong interest in system development and problem-solving. I enjoy learning new technologies and continuously improving my technical and analytical skills. I am detail-oriented and deeply committed to delivering efficient and user-friendly solutions for any system I work on.
                            </p>
                        </div>
                    </div>

                    <!-- Tech Stack -->
                    <div class="col-md-6">
                        <div class="bg-white rounded-3 p-4 border h-100" style="border-color:#e2e8f0!important;">
                            <h6 class="fw-bold mb-3 d-flex align-items-center gap-2" style="font-size:13px;color:#0f172a;">
                                <span style="width:28px;height:28px;background:#f1f5f9;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;">
                                    <i class="bi bi-code-slash" style="font-size:13px;color:#475569;"></i>
                                </span>
                                Core Technologies
                            </h6>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach(['HTML5','CSS3','JavaScript','PHP','MySQL','Git / GitHub'] as $tech): ?>
                                <span style="background:#f1f5f9;color:#334155;font-size:12px;font-weight:600;padding:5px 12px;border-radius:6px;border:1px solid #e2e8f0;"><?= $tech ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Trivia -->
                    <div class="col-md-6">
                        <div class="bg-white rounded-3 p-4 border h-100" style="border-color:#e2e8f0!important;">
                            <h6 class="fw-bold mb-3 d-flex align-items-center gap-2" style="font-size:13px;color:#0f172a;">
                                <span style="width:28px;height:28px;background:#f1f5f9;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;">
                                    <i class="bi bi-lightbulb-fill" style="font-size:13px;color:#475569;"></i>
                                </span>
                                Developer Trivia
                            </h6>
                            <div class="d-flex flex-column gap-3">
                                <div class="d-flex gap-3 align-items-start">
                                    <div style="width:30px;height:30px;background:#f1f5f9;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                        <i class="bi bi-puzzle-fill" style="font-size:13px;color:#475569;"></i>
                                    </div>
                                    <div>
                                        <div class="fw-semibold" style="font-size:12px;color:#0f172a;">Problem Solver</div>
                                        <div class="text-secondary" style="font-size:11px;">Strong focus on logical architecture and untangling complex technical challenges.</div>
                                    </div>
                                </div>
                                <div class="d-flex gap-3 align-items-start">
                                    <div style="width:30px;height:30px;background:#f1f5f9;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                        <i class="bi bi-book-fill" style="font-size:13px;color:#475569;"></i>
                                    </div>
                                    <div>
                                        <div class="fw-semibold" style="font-size:12px;color:#0f172a;">Continuous Learner</div>
                                        <div class="text-secondary" style="font-size:11px;">Always hunting for the newest technologies and frameworks to master.</div>
                                    </div>
                                </div>
                                <div class="d-flex gap-3 align-items-start">
                                    <div style="width:30px;height:30px;background:#f1f5f9;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                        <i class="bi bi-search" style="font-size:13px;color:#475569;"></i>
                                    </div>
                                    <div>
                                        <div class="fw-semibold" style="font-size:12px;color:#0f172a;">Detail-Oriented</div>
                                        <div class="text-secondary" style="font-size:11px;">Committed to crafting efficient, bug-free, and highly user-friendly interfaces.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-0 px-4 pb-4 pt-0" style="background:#f8fafc;">
                <button type="button" class="btn btn-sm btn-outline-secondary px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>



<!-- ===== View Product Modal ===== -->
<div class="modal fade" id="viewProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden; border: 1px solid #eaedf2;">
            <div class="modal-header bg-light border-bottom" style="padding: 16px 20px;">
                <h5 class="modal-title m-0 fw-bold fs-5">Product Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" style="background: #fff;">
                <div class="mb-3 d-flex justify-content-between align-items-center">
                    <span id="vp-category" class="badge bg-secondary rounded-pill fw-medium tracking-wide text-uppercase" style="font-size: 10px;">Category</span>
                    <span id="vp-status" class="status-pill">Status</span>
                </div>
                <h4 id="vp-name" class="fw-bold mb-1 text-dark" style="font-size: 20px;">Product Name</h4>
                <div class="text-muted small mb-4" id="vp-sku" style="font-family: monospace;">SKU-000</div>
                
                <div class="row g-3 bg-light rounded-3 p-3 border mb-3">
                    <div class="col-6">
                        <div class="text-muted mb-1" style="font-size: 11px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Price</div>
                        <div id="vp-price" class="fw-bold text-dark fs-5">₱0.00</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted mb-1" style="font-size: 11px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Stock</div>
                        <div id="vp-stock" class="fw-bold text-dark fs-5">0</div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2 p-3 rounded-3" style="background: #f8f9fa; border: 1px solid #e2e8f0;">
                    <i class="bi bi-shop fs-4 text-muted"></i>
                    <div>
                        <div class="text-muted mb-1" style="font-size: 11px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; line-height: 1;">Listed By</div>
                        <div id="vp-seller" class="fw-semibold text-dark fs-6" style="line-height: 1;">Seller Name</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Product View Button Logic
    const viewProductBtns = document.querySelectorAll('.view-product-btn');
    if (viewProductBtns.length > 0) {
        const modalEl = document.getElementById('viewProductModal');
        const viewModal = new bootstrap.Modal(modalEl);
        
        viewProductBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const target = e.currentTarget;
                document.getElementById('vp-name').textContent = target.dataset.name;
                document.getElementById('vp-sku').textContent = target.dataset.sku;
                document.getElementById('vp-category').textContent = target.dataset.category;
                document.getElementById('vp-price').textContent = target.dataset.price;
                document.getElementById('vp-stock').textContent = target.dataset.stock;
                document.getElementById('vp-seller').textContent = target.dataset.seller;
                
                const statusEl = document.getElementById('vp-status');
                statusEl.textContent = target.dataset.status;
                statusEl.className = 'status-pill status-' + target.dataset.status.toLowerCase().replace(/ /g, '-');
                
                viewModal.show();
            });
        });
    }
});
</script>
</body>
</html>
