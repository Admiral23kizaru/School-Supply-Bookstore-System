<!-- ===================== CART VIEW ===================== -->
<div :class="tab === 'cart' ? 'd-flex' : 'd-none'" x-cloak class="container-fluid px-0 h-100 flex-column" style="max-width: 1200px; margin: 0 auto;">
    <header class="d-flex align-items-center justify-content-between mb-4 pb-2">
        <div>
            <h2 class="fs-4 fw-bold text-dark tracking-tight mb-1">Your Cart</h2>
            <p class="text-secondary small fw-medium mt-1 mb-0"><span x-text="cartCount"></span> item(s)</p>
        </div>
        <button @click="tab = 'shop'" class="btn btn-outline-secondary bg-white text-dark fw-semibold btn-custom shadow-sm border-2">
            Continue Shopping
        </button>
    </header>

    <div class="row g-4 align-items-start pb-5">
        
        <!-- Left: Cart Items -->
        <div class="col-12 col-lg-8">
            <div class="card-custom bg-white p-4 p-sm-5 d-flex flex-column h-100 border-0 shadow-sm" style="min-height: 400px;">
                <h6 class="fw-bold text-dark mb-4 pb-2">Cart Items</h6>
                
                <!-- Empty State -->
                <div :class="cart.length === 0 ? 'd-flex' : 'd-none'" class="flex-grow-1 flex-column align-items-center justify-content-center py-5">
                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mb-4 border" style="width: 64px; height: 64px;">
                        <i class="bi bi-cart-x text-secondary fs-3"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">Your cart is empty</h5>
                    <p class="text-secondary small text-center mb-4" style="max-width: 250px;">Looks like you haven't added anything yet. Start shopping to fill your cart.</p>
                    <button @click="tab = 'shop'" class="btn bg-dark-custom btn-custom text-white d-flex align-items-center gap-2 shadow-sm">
                        <i class="bi bi-cart3"></i> Browse Products
                    </button>
                </div>
                
                <!-- Populated Cart Items List -->
                <div :class="cart.length > 0 ? 'd-block' : 'd-none'" class="flex-grow-1 overflow-auto pe-2">
                    <template x-for="(item, index) in cart" :key="item.id">
                        <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3 gap-sm-4 py-3 border-bottom border-light">
                            
                            <div class="d-flex align-items-center gap-3 gap-sm-4 flex-grow-1 min-w-0">
                                <div class="bg-light rounded-4 d-flex align-items-center justify-content-center border" style="width: 80px; height: 80px; flex-shrink:0;">
                                    <i class="bi bi-journal-text text-secondary opacity-50 fs-4"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-gray-400 fw-bold text-uppercase mb-1" style="font-size: 10px; letter-spacing: 1px;" x-text="item.category"></p>
                                    <h6 class="fw-bold text-dark text-truncate mb-1" x-text="item.name"></h6>
                                    <div class="fw-bold text-dark small" x-text="'₱' + item.price.toFixed(2)"></div>
                                </div>
                            </div>
                            
                            <div class="d-flex align-items-center justify-content-between w-100" style="max-width: 200px;">
                                <div class="d-flex align-items-center gap-2 bg-white border rounded-3 px-2 py-1 shadow-sm">
                                    <button @click="updateQty(item.id, -1)" class="btn btn-sm btn-link text-secondary p-1 text-decoration-none d-flex align-items-center justify-content-center" style="width:24px; height:24px;"><i class="bi bi-dash"></i></button>
                                    <span class="text-center fw-semibold small" style="width:20px;" x-text="item.qty"></span>
                                    <button @click="updateQty(item.id, 1)" class="btn btn-sm btn-link text-secondary p-1 text-decoration-none d-flex align-items-center justify-content-center" style="width:24px; height:24px;"><i class="bi bi-plus"></i></button>
                                </div>
                                <div class="fw-bold text-dark text-end font-monospace" style="width:80px;" x-text="'₱' + (item.price * item.qty).toFixed(2)"></div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Right: Summary -->
        <div class="col-12 col-lg-4">
            <div class="card-custom bg-white border-0 shadow-sm p-4 p-sm-5">
                <h6 class="fw-bold text-dark mb-4 pb-2">Order Summary</h6>
                
                <div class="d-flex flex-column gap-3 mb-4">
                    <div class="d-flex align-items-center justify-content-between small">
                        <span class="text-secondary fw-medium">Subtotal</span>
                        <span class="fw-semibold text-dark font-monospace" x-text="'₱' + cartTotal.toFixed(2)"></span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between small">
                        <span class="text-secondary fw-medium">Shipping</span>
                        <span class="fw-semibold text-dark">Free</span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between small">
                        <span class="text-secondary fw-medium">Tax</span>
                        <span class="fw-semibold text-dark">₱0.00</span>
                    </div>
                </div>
                
                <div class="pt-3 border-top d-flex align-items-center justify-content-between mb-4">
                    <span class="fw-bold text-dark">Total</span>
                    <span class="fs-4 fw-black text-dark font-monospace" style="font-weight: 900;" x-text="'₱' + cartTotal.toFixed(2)"></span>
                </div>
                
                <button type="button" @click="checkout()" :disabled="cart.length === 0 || isLoading" class="btn bg-dark-custom btn-custom text-white w-100 py-3 shadow-sm d-flex justify-content-center align-items-center gap-2">
                    <span x-show="!isLoading" class="d-flex align-items-center gap-2"><i class="bi bi-credit-card"></i> Checkout</span>
                    <span x-show="isLoading" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                </button>
            </div>
        </div>

    </div>
</div>
