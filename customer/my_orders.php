<!-- ===================== MY ORDERS VIEW ===================== -->
<div :class="tab === 'orders' ? 'd-flex' : 'd-none'" x-cloak class="container-fluid px-0 min-vh-100 flex-column">
    <header class="mb-5">
        <h2 class="fs-4 fw-bold text-dark tracking-tight">Order History</h2>
        <p class="text-secondary small fw-medium mt-1">View and track your previous purchases.</p>
    </header>

    <!-- Stats -->
    <div class="row g-4 mb-5">
        <div class="col-12 col-md-4">
            <div class="bg-white card-custom p-4 d-flex flex-column h-100">
                <p class="text-gray-400 fw-bold text-uppercase mb-2" style="font-size: 11px; letter-spacing: 1px;">Total Orders</p>
                <p class="text-dark mb-0 fs-3" style="font-weight: 900;" x-text="orders.length"></p>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="bg-white card-custom p-4 d-flex flex-column h-100">
                <p class="text-gray-400 fw-bold text-uppercase mb-2" style="font-size: 11px; letter-spacing: 1px;">Total Spent</p>
                <p class="text-dark mb-0 fs-3" style="font-weight: 900;" x-text="'₱' + orders.reduce((sum, order) => sum + order.total, 0).toFixed(2)"></p>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="bg-white card-custom p-4 d-flex flex-column h-100">
                <p class="text-gray-400 fw-bold text-uppercase mb-2" style="font-size: 11px; letter-spacing: 1px;">Pending Orders</p>
                <p class="text-dark mb-0 fs-3" style="font-weight: 900;" x-text="orders.filter(o => o.status === 'Pending').length"></p>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card-custom bg-white d-flex flex-column mb-4 border-0 shadow-sm" style="min-height: 400px; flex-grow: 1;">
        <div class="px-4 py-3 border-bottom d-flex align-items-center justify-content-between">
            <h3 class="fw-bold text-dark mb-0" style="font-size: 14px;">Recent Orders</h3>
        </div>
        
        <div class="table-responsive flex-grow-1">
            <table class="table table-borderless table-hover align-middle mb-0" style="min-width: 600px;">
                <thead class="bg-light border-bottom">
                    <tr>
                        <th class="text-gray-400 fw-bold text-uppercase px-4 py-3" style="font-size: 10px; letter-spacing: 0.5px;">Order ID</th>
                        <th class="text-gray-400 fw-bold text-uppercase px-4 py-3" style="font-size: 10px; letter-spacing: 0.5px;">Date</th>
                        <th class="text-gray-400 fw-bold text-uppercase px-4 py-3" style="font-size: 10px; letter-spacing: 0.5px;">Items</th>
                        <th class="text-gray-400 fw-bold text-uppercase px-4 py-3 text-end" style="font-size: 10px; letter-spacing: 0.5px;">Total</th>
                        <th class="text-gray-400 fw-bold text-uppercase px-4 py-3 text-end" style="font-size: 10px; letter-spacing: 0.5px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="order in orders" :key="order.id">
                        <tr class="border-bottom">
                            <td class="px-4 py-3">
                                <span class="fw-semibold text-dark text-nowrap" style="font-size: 13px;" x-text="order.id"></span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-secondary text-nowrap" style="font-size: 13px;" x-text="order.date"></span>
                            </td>
                            <td class="px-4 py-3 text-truncate" style="max-width: 300px;">
                                <span class="text-secondary" style="font-size: 13px;" x-text="order.items"></span>
                            </td>
                            <td class="px-4 py-3 text-end">
                                <span class="fw-bold text-dark font-monospace" style="font-size: 13px;" x-text="'₱' + order.total.toFixed(2)"></span>
                            </td>
                            <td class="px-4 py-3 text-end">
                                <span class="badge rounded-pill fw-semibold px-2 py-1" 
                                      :class="{
                                          'bg-warning-subtle text-warning border border-warning-subtle': order.status === 'Pending',
                                          'bg-info-subtle text-info border border-info-subtle': order.status === 'Processing',
                                          'bg-success-subtle text-success border border-success-subtle': order.status === 'Delivered',
                                          'bg-secondary-subtle text-secondary border border-secondary': order.status === 'Cancelled'
                                      }"
                                      style="font-size: 11px;" x-text="order.status"></span>
                            </td>
                        </tr>
                    </template>
                    <tr :class="orders.length === 0 ? '' : 'd-none'">
                        <td colspan="5" class="text-center py-5">
                            <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3" style="width: 48px; height: 48px;">
                                <i class="bi bi-receipt text-secondary fs-4"></i>
                            </div>
                            <p class="text-secondary small mb-0">You haven't placed any orders yet.</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination Footer -->
        <div class="px-4 py-3 border-top d-flex align-items-center justify-content-between mt-auto">
            <span class="text-secondary fw-medium" style="font-size: 13px;">Showing <span x-text="orders.length"></span> order(s)</span>
            <div class="d-flex align-items-center gap-1">
                <button class="btn btn-sm btn-link text-secondary text-decoration-none d-flex align-items-center gap-1 shadow-none"><i class="bi bi-chevron-left"></i> Prev</button>
                <button class="btn btn-sm bg-dark-custom text-white d-flex align-items-center justify-content-center rounded-2 shadow-none" style="width: 30px; height: 30px; font-weight:700;">1</button>
                <button class="btn btn-sm btn-link text-secondary text-decoration-none d-flex align-items-center gap-1 shadow-none">Next <i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>
</div>
