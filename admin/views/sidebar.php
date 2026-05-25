<aside class="sidebar" :class="mobileMenuOpen ? 'sidebar-open' : ''">
    <div class="brand d-flex align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-white text-dark rounded-3 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; flex-shrink: 0;">
                <i class="bi bi-journal-bookmark-fill" style="font-size: 16px;"></i>
            </div>
            <div>
                School Supply<small>Admin Panel</small>
            </div>
        </div>
        <button class="btn btn-link text-white d-lg-none p-0 border-0" @click="mobileMenuOpen = false">
            <i class="bi bi-x-lg fs-5"></i>
        </button>
    </div>
    <div class="menu-label">Menu</div>
    <a class="nav-link-custom <?= $tab === 'dashboard' ? 'active' : '' ?>" href="dashboard.php?tab=dashboard"><i class="bi bi-grid"></i>Dashboard</a>
    <a class="nav-link-custom <?= $tab === 'users' ? 'active' : '' ?>" href="dashboard.php?tab=users"><i class="bi bi-people"></i>Manage Users</a>
    <a class="nav-link-custom <?= $tab === 'approvals' ? 'active' : '' ?>" href="dashboard.php?tab=approvals">
        <i class="bi bi-patch-check"></i>Approvals
        <?php if (($pendingApprovalCount ?? 0) > 0): ?>
            <span class="ms-auto badge text-bg-light"><?= (int) $pendingApprovalCount ?></span>
        <?php endif; ?>
    </a>
    <a class="nav-link-custom <?= $tab === 'products' ? 'active' : '' ?>" href="dashboard.php?tab=products"><i class="bi bi-box-seam"></i>Products</a>
    <a class="nav-link-custom <?= $tab === 'orders' ? 'active' : '' ?>" href="dashboard.php?tab=orders"><i class="bi bi-bag"></i>All Orders</a>
    <a class="nav-link-custom <?= $tab === 'reports' ? 'active' : '' ?>" href="dashboard.php?tab=reports"><i class="bi bi-bar-chart"></i>Reports</a>
    <a class="nav-link-custom <?= $tab === 'profile' ? 'active' : '' ?>" href="dashboard.php?tab=profile"><i class="bi bi-person-circle"></i>Profile</a>
    <div class="sidebar-bottom">
        <div class="d-flex align-items-center gap-2 mb-2">
            <?php if (!empty($adminProfileImageUrl)): ?>
                <div class="rounded-3 overflow-hidden flex-shrink-0" style="width:40px;height:40px;border:1px solid #3a3f4b;">
                    <img src="<?= esc($adminProfileImageUrl) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                </div>
            <?php else: ?>
                <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0 bg-secondary bg-opacity-25 text-secondary" style="width:40px;height:40px;border:1px solid #3a3f4b;">
                    <i class="bi bi-person-fill" style="font-size:1.1rem;"></i>
                </div>
            <?php endif; ?>
            <div class="min-w-0">
                <div class="acc-name text-truncate"><?= esc($adminName) ?></div>
                <div class="acc-email text-truncate"><?= esc($adminEmail) ?></div>
            </div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary w-100 mt-2 mb-1 d-flex align-items-center justify-content-center gap-2" data-bs-toggle="modal" data-bs-target="#aboutModal" style="font-size:12px; border-radius:8px; color:#aab1bf; border-color:#3a3f4b;">
            <i class="bi bi-info-circle"></i> About Developer
        </button>
        <a class="logout-link" href="../logout.php"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
    </div>
</aside>
